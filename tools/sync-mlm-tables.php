<?php
/**
 * Sincronizar tablas MLM
 * 
 * Sincroniza wp_cvapp_mlm_relations con wp_uap_mlm_relations (fuente de verdad)
 * y limpia duplicados
 * 
 * Uso: wp eval-file wp-content/plugins/cv-commissions/tools/sync-mlm-tables.php
 */

if (!defined('ABSPATH') && !defined('WP_CLI')) {
    require_once('../../../../../wp-load.php');
}

global $wpdb, $indeed_db;

echo "🔧 SINCRONIZACIÓN DE TABLAS MLM\n";
echo "==================================================\n\n";

$fixed = 0;
$duplicates_removed = 0;
$added = 0;
$errors = 0;

// PASO 1: Limpiar duplicados en tabla CV
echo "📋 PASO 1: Limpiando duplicados en tabla CV...\n";
echo "------------------------------------------------\n";

$duplicates = $wpdb->get_results("
    SELECT affiliate_id, COUNT(*) as count
    FROM wp_cvapp_mlm_relations
    GROUP BY affiliate_id
    HAVING COUNT(*) > 1
");

foreach ($duplicates as $dup) {
    echo "   🔍 Affiliate ID {$dup->affiliate_id} tiene {$dup->count} relaciones\n";
    
    // Obtener el padre correcto desde UAP
    $correct_parent = $indeed_db->mlm_get_parent($dup->affiliate_id);
    
    if ($correct_parent) {
        // Eliminar todas las relaciones
        $wpdb->query($wpdb->prepare(
            "DELETE FROM wp_cvapp_mlm_relations WHERE affiliate_id = %d",
            $dup->affiliate_id
        ));
        
        // Insertar solo la correcta
        $wpdb->query($wpdb->prepare(
            "INSERT INTO wp_cvapp_mlm_relations VALUES(NULL, %d, %d)",
            $dup->affiliate_id,
            $correct_parent
        ));
        
        echo "   ✅ Duplicados eliminados, mantenida relación con padre {$correct_parent}\n";
        $duplicates_removed += ($dup->count - 1);
    } else {
        echo "   ⚠️ No se encontró padre en UAP, eliminando todas\n";
        $wpdb->query($wpdb->prepare(
            "DELETE FROM wp_cvapp_mlm_relations WHERE affiliate_id = %d",
            $dup->affiliate_id
        ));
        $duplicates_removed += $dup->count;
    }
}

echo "   Total duplicados eliminados: {$duplicates_removed}\n\n";

// PASO 2: Sincronizar relaciones faltantes desde UAP a CV
echo "📋 PASO 2: Sincronizando relaciones desde UAP a CV...\n";
echo "-------------------------------------------------------\n";

$missing = $wpdb->get_results("
    SELECT 
        ua.id as affiliate_id,
        ua.uid,
        u.user_login,
        umlm.parent as uap_parent
    FROM wp_uap_affiliates ua
    INNER JOIN wp_uap_mlm_relations umlm ON ua.id = umlm.child
    LEFT JOIN wp_cvapp_mlm_relations cmlm ON ua.id = cmlm.affiliate_id
    LEFT JOIN wp_users u ON ua.uid = u.ID
    WHERE cmlm.affiliate_id IS NULL
    AND ua.uid > 0
");

foreach ($missing as $row) {
    echo "   🔄 Sincronizando: {$row->user_login} (Aff {$row->affiliate_id}) → Padre {$row->uap_parent}\n";
    
    $result = $wpdb->query($wpdb->prepare(
        "INSERT INTO wp_cvapp_mlm_relations VALUES(NULL, %d, %d)",
        $row->affiliate_id,
        $row->uap_parent
    ));
    
    if ($result) {
        echo "   ✅ Relación añadida\n";
        $added++;
    } else {
        echo "   ❌ Error: {$wpdb->last_error}\n";
        $errors++;
    }
}

echo "   Total relaciones añadidas: {$added}\n\n";

// PASO 3: Verificar inconsistencias (padres diferentes)
echo "📋 PASO 3: Verificando y corrigiendo inconsistencias...\n";
echo "--------------------------------------------------------\n";

$inconsistent = $wpdb->get_results("
    SELECT 
        ua.id as affiliate_id,
        u.user_login,
        umlm.parent as uap_parent,
        cmlm.parent_affiliate_id as cv_parent
    FROM wp_uap_affiliates ua
    INNER JOIN wp_uap_mlm_relations umlm ON ua.id = umlm.child
    INNER JOIN wp_cvapp_mlm_relations cmlm ON ua.id = cmlm.affiliate_id
    LEFT JOIN wp_users u ON ua.uid = u.ID
    WHERE umlm.parent != cmlm.parent_affiliate_id
    AND ua.uid > 0
");

foreach ($inconsistent as $row) {
    echo "   ⚠️ Inconsistencia: {$row->user_login} (Aff {$row->affiliate_id})\n";
    echo "      UAP: {$row->uap_parent} | CV: {$row->cv_parent}\n";
    echo "      Corrigiendo a UAP como fuente de verdad...\n";
    
    // Actualizar tabla CV con el padre de UAP
    $result = $wpdb->query($wpdb->prepare(
        "UPDATE wp_cvapp_mlm_relations SET parent_affiliate_id = %d WHERE affiliate_id = %d",
        $row->uap_parent,
        $row->affiliate_id
    ));
    
    if ($result) {
        echo "   ✅ Corregido: Ahora padre es {$row->uap_parent}\n";
        $fixed++;
    } else {
        echo "   ❌ Error: {$wpdb->last_error}\n";
        $errors++;
    }
}

if (empty($inconsistent)) {
    echo "   ✅ No hay inconsistencias\n";
}

echo "\n";

// RESUMEN FINAL
echo "\n";
echo "==================================================\n";
echo "📊 RESUMEN DE SINCRONIZACIÓN\n";
echo "==================================================\n";
echo "🗑️ Duplicados eliminados: {$duplicates_removed}\n";
echo "➕ Relaciones añadidas: {$added}\n";
echo "🔧 Inconsistencias corregidas: {$fixed}\n";
echo "❌ Errores: {$errors}\n";
echo "==================================================\n\n";

// Verificación final
$total_uap = $wpdb->get_var("SELECT COUNT(*) FROM wp_uap_mlm_relations");
$total_cv = $wpdb->get_var("SELECT COUNT(*) FROM wp_cvapp_mlm_relations");

echo "📊 TOTALES FINALES:\n";
echo "   UAP: {$total_uap} relaciones\n";
echo "   CV:  {$total_cv} relaciones\n";

if ($total_uap == $total_cv) {
    echo "   ✅ Ambas tablas están sincronizadas\n";
} else {
    $diff = abs($total_uap - $total_cv);
    echo "   ⚠️ Diferencia: {$diff} relaciones\n";
}

echo "\n✅ Sincronización completada\n";



