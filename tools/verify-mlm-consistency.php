<?php
/**
 * Verificar consistencia entre tablas MLM
 * 
 * Compara wp_uap_mlm_relations con wp_cvapp_mlm_relations
 * y detecta inconsistencias
 * 
 * Uso: wp eval-file wp-content/plugins/cv-commissions/tools/verify-mlm-consistency.php
 */

if (!defined('ABSPATH') && !defined('WP_CLI')) {
    require_once('../../../../../wp-load.php');
}

global $wpdb, $indeed_db;

echo "🔍 VERIFICACIÓN DE CONSISTENCIA ENTRE TABLAS MLM\n";
echo "==================================================\n\n";

// 1. Verificar afiliados en UAP sin relación MLM
echo "📋 1. Afiliados UAP sin relación MLM:\n";
echo "--------------------------------------\n";

$orphan_uap = $wpdb->get_results("
    SELECT ua.id as affiliate_id, ua.uid, u.user_login, u.user_email
    FROM wp_uap_affiliates ua
    LEFT JOIN wp_uap_mlm_relations umlm ON ua.id = umlm.child
    LEFT JOIN wp_users u ON ua.uid = u.ID
    WHERE umlm.child IS NULL
    ORDER BY ua.id DESC
    LIMIT 20
");

if (empty($orphan_uap)) {
    echo "✅ Todos los afiliados UAP tienen relación MLM\n\n";
} else {
    echo "⚠️ Encontrados " . count($orphan_uap) . " afiliados sin relación MLM en UAP:\n\n";
    foreach ($orphan_uap as $aff) {
        echo "   - Affiliate ID: {$aff->affiliate_id} | User: {$aff->user_login} (UID: {$aff->uid})\n";
    }
    echo "\n";
}

// 2. Verificar afiliados en tabla CV sin relación MLM
echo "📋 2. Afiliados en tabla CV personalizada sin relación MLM:\n";
echo "-----------------------------------------------------------\n";

$orphan_cv = $wpdb->get_results("
    SELECT ua.id as affiliate_id, ua.uid, u.user_login, u.user_email
    FROM wp_uap_affiliates ua
    LEFT JOIN wp_cvapp_mlm_relations cmlm ON ua.id = cmlm.affiliate_id
    LEFT JOIN wp_users u ON ua.uid = u.ID
    WHERE cmlm.affiliate_id IS NULL
    ORDER BY ua.id DESC
    LIMIT 20
");

if (empty($orphan_cv)) {
    echo "✅ Todos los afiliados tienen relación en tabla CV\n\n";
} else {
    echo "⚠️ Encontrados " . count($orphan_cv) . " afiliados sin relación en tabla CV:\n\n";
    foreach ($orphan_cv as $aff) {
        echo "   - Affiliate ID: {$aff->affiliate_id} | User: {$aff->user_login} (UID: {$aff->uid})\n";
    }
    echo "\n";
}

// 3. Comparar padres entre ambas tablas
echo "📋 3. Comparación de padres entre tablas:\n";
echo "-------------------------------------------\n";

$comparison = $wpdb->get_results("
    SELECT 
        ua.id as affiliate_id,
        ua.uid,
        u.user_login,
        umlm.parent as uap_parent,
        cmlm.parent_affiliate_id as cv_parent,
        CASE 
            WHEN umlm.parent = cmlm.parent_affiliate_id THEN 'OK'
            WHEN umlm.parent IS NULL AND cmlm.parent_affiliate_id IS NULL THEN 'AMBOS_NULL'
            WHEN umlm.parent IS NULL THEN 'UAP_NULL'
            WHEN cmlm.parent_affiliate_id IS NULL THEN 'CV_NULL'
            ELSE 'DIFERENTES'
        END as estado
    FROM wp_uap_affiliates ua
    LEFT JOIN wp_uap_mlm_relations umlm ON ua.id = umlm.child
    LEFT JOIN wp_cvapp_mlm_relations cmlm ON ua.id = cmlm.affiliate_id
    LEFT JOIN wp_users u ON ua.uid = u.ID
    WHERE ua.uid > 0
    ORDER BY ua.id DESC
    LIMIT 50
");

$ok = 0;
$diferentes = 0;
$uap_null = 0;
$cv_null = 0;
$ambos_null = 0;

foreach ($comparison as $row) {
    switch ($row->estado) {
        case 'OK':
            $ok++;
            break;
        case 'DIFERENTES':
            $diferentes++;
            echo "   ⚠️ DIFERENCIA: {$row->user_login} (Aff {$row->affiliate_id}) - UAP: {$row->uap_parent} | CV: {$row->cv_parent}\n";
            break;
        case 'UAP_NULL':
            $uap_null++;
            echo "   ❌ UAP NULL: {$row->user_login} (Aff {$row->affiliate_id}) - CV: {$row->cv_parent}\n";
            break;
        case 'CV_NULL':
            $cv_null++;
            echo "   ❌ CV NULL: {$row->user_login} (Aff {$row->affiliate_id}) - UAP: {$row->uap_parent}\n";
            break;
        case 'AMBOS_NULL':
            $ambos_null++;
            echo "   ⚠️ AMBOS NULL: {$row->user_login} (Aff {$row->affiliate_id})\n";
            break;
    }
}

echo "\n";
echo "📊 Estadísticas (últimos 50 afiliados):\n";
echo "   ✅ Consistentes: {$ok}\n";
echo "   ⚠️ Padres diferentes: {$diferentes}\n";
echo "   ❌ UAP NULL: {$uap_null}\n";
echo "   ❌ CV NULL: {$cv_null}\n";
echo "   ⚠️ Ambos NULL: {$ambos_null}\n";
echo "\n";

// 4. Verificar duplicados en tabla CV
echo "📋 4. Verificar duplicados en tabla CV:\n";
echo "----------------------------------------\n";

$duplicates = $wpdb->get_results("
    SELECT affiliate_id, COUNT(*) as count
    FROM wp_cvapp_mlm_relations
    GROUP BY affiliate_id
    HAVING COUNT(*) > 1
");

if (empty($duplicates)) {
    echo "✅ No hay duplicados en tabla CV\n\n";
} else {
    echo "⚠️ Encontrados " . count($duplicates) . " afiliados con múltiples relaciones:\n\n";
    foreach ($duplicates as $dup) {
        $relations = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM wp_cvapp_mlm_relations WHERE affiliate_id = %d",
            $dup->affiliate_id
        ));
        
        echo "   - Affiliate ID {$dup->affiliate_id} tiene {$dup->count} relaciones:\n";
        foreach ($relations as $rel) {
            echo "      → Padre: {$rel->parent_affiliate_id}\n";
        }
    }
    echo "\n";
}

// 5. Resumen de totales
echo "📊 TOTALES GENERALES:\n";
echo "=====================\n";

$total_affiliates = $wpdb->get_var("SELECT COUNT(*) FROM wp_uap_affiliates WHERE uid > 0");
$total_uap_relations = $wpdb->get_var("SELECT COUNT(*) FROM wp_uap_mlm_relations");
$total_cv_relations = $wpdb->get_var("SELECT COUNT(*) FROM wp_cvapp_mlm_relations");

echo "Total afiliados activos: {$total_affiliates}\n";
echo "Total relaciones UAP: {$total_uap_relations}\n";
echo "Total relaciones CV: {$total_cv_relations}\n";

$diff = abs($total_uap_relations - $total_cv_relations);
if ($diff > 0) {
    echo "⚠️ Diferencia: {$diff} relaciones\n";
} else {
    echo "✅ Ambas tablas tienen el mismo número de relaciones\n";
}

echo "\n";
echo "==================================================\n";
echo "🔧 ACCIONES SUGERIDAS:\n";
echo "==================================================\n\n";

if ($cv_null > 0) {
    echo "1. ⚠️ Hay {$cv_null} afiliados sin relación en tabla CV\n";
    echo "   Acción: Ejecutar sincronización desde UAP a CV\n\n";
}

if ($uap_null > 0) {
    echo "2. ⚠️ Hay {$uap_null} afiliados sin relación en tabla UAP\n";
    echo "   Acción: Revisar por qué no se crearon en UAP\n\n";
}

if ($diferentes > 0) {
    echo "3. ⚠️ Hay {$diferentes} afiliados con padres diferentes entre tablas\n";
    echo "   Acción: Decidir qué tabla es la fuente de verdad y sincronizar\n\n";
}

if (!empty($duplicates)) {
    echo "4. ⚠️ Hay duplicados en tabla CV\n";
    echo "   Acción: Limpiar duplicados manteniendo solo una relación\n\n";
}

if ($ok == count($comparison) && empty($duplicates)) {
    echo "✅ TODO CORRECTO: Las tablas están sincronizadas\n\n";
}

echo "Para corregir automáticamente, ejecuta:\n";
echo "wp eval-file wp-content/plugins/cv-commissions/tools/sync-mlm-tables.php\n";



