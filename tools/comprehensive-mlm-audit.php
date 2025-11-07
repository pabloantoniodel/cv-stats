<?php
/**
 * Auditoría completa de tablas MLM
 * 
 * Verificación exhaustiva de consistencia, integridad y estructura
 * 
 * Uso: wp eval-file wp-content/plugins/cv-commissions/tools/comprehensive-mlm-audit.php
 */

if (!defined('ABSPATH') && !defined('WP_CLI')) {
    require_once('../../../../../wp-load.php');
}

global $wpdb, $indeed_db;

echo "🔍 AUDITORÍA COMPLETA DE TABLAS MLM\n";
echo "==================================================\n\n";

$issues = array();
$warnings = array();
$ok_count = 0;

// ============================================================
// 1. VERIFICACIÓN DE SINCRONIZACIÓN ENTRE TABLAS
// ============================================================
echo "📋 1. SINCRONIZACIÓN ENTRE TABLAS\n";
echo "==================================================\n";

$total_uap = $wpdb->get_var("SELECT COUNT(*) FROM wp_uap_mlm_relations");
$total_cv = $wpdb->get_var("SELECT COUNT(*) FROM wp_cvapp_mlm_relations");

echo "Total relaciones UAP: {$total_uap}\n";
echo "Total relaciones CV:  {$total_cv}\n";

if ($total_uap == $total_cv) {
    echo "✅ Ambas tablas tienen el mismo número de relaciones\n\n";
    $ok_count++;
} else {
    $diff = abs($total_uap - $total_cv);
    echo "⚠️ Diferencia: {$diff} relaciones\n\n";
    $warnings[] = "Diferencia de {$diff} relaciones entre tablas";
}

// Verificar relaciones que están en UAP pero no en CV
$missing_in_cv = $wpdb->get_results("
    SELECT umlm.affiliate_id, umlm.parent_affiliate_id, u.user_login, ua.uid
    FROM wp_uap_mlm_relations umlm
    LEFT JOIN wp_cvapp_mlm_relations cmlm ON umlm.affiliate_id = cmlm.affiliate_id 
        AND umlm.parent_affiliate_id = cmlm.parent_affiliate_id
    INNER JOIN wp_uap_affiliates ua ON umlm.affiliate_id = ua.id
    LEFT JOIN wp_users u ON ua.uid = u.ID
    WHERE cmlm.affiliate_id IS NULL
    AND ua.uid > 0
");

if (empty($missing_in_cv)) {
    echo "✅ Todas las relaciones UAP existen en CV\n";
    $ok_count++;
} else {
    echo "⚠️ Encontradas " . count($missing_in_cv) . " relaciones en UAP que no están en CV:\n";
    foreach ($missing_in_cv as $rel) {
        echo "   - Aff ID {$rel->affiliate_id} (User: {$rel->user_login}, UID: {$rel->uid}) → Padre {$rel->parent_affiliate_id}\n";
    }
    $issues[] = count($missing_in_cv) . " relaciones faltantes en CV";
}
echo "\n";

// Verificar relaciones que están en CV pero no en UAP
$extra_in_cv = $wpdb->get_results("
    SELECT cmlm.affiliate_id, cmlm.parent_affiliate_id, u.user_login, ua.uid
    FROM wp_cvapp_mlm_relations cmlm
    LEFT JOIN wp_uap_mlm_relations umlm ON cmlm.affiliate_id = umlm.affiliate_id 
        AND cmlm.parent_affiliate_id = umlm.parent_affiliate_id
    LEFT JOIN wp_uap_affiliates ua ON cmlm.affiliate_id = ua.id
    LEFT JOIN wp_users u ON ua.uid = u.ID
    WHERE umlm.affiliate_id IS NULL
");

if (empty($extra_in_cv)) {
    echo "✅ No hay relaciones huérfanas en CV\n";
    $ok_count++;
} else {
    echo "⚠️ Encontradas " . count($extra_in_cv) . " relaciones en CV que no existen en UAP:\n";
    foreach ($extra_in_cv as $rel) {
        echo "   - Aff ID {$rel->affiliate_id} (User: {$rel->user_login}, UID: {$rel->uid}) → Padre {$rel->parent_affiliate_id}\n";
    }
    $issues[] = count($extra_in_cv) . " relaciones huérfanas en CV";
}
echo "\n";

// Verificar inconsistencias en padres
$inconsistent_parents = $wpdb->get_results("
    SELECT 
        ua.id as affiliate_id,
        u.user_login,
        ua.uid,
        umlm.parent_affiliate_id as uap_parent,
        cmlm.parent_affiliate_id as cv_parent
    FROM wp_uap_affiliates ua
    INNER JOIN wp_uap_mlm_relations umlm ON ua.id = umlm.affiliate_id
    INNER JOIN wp_cvapp_mlm_relations cmlm ON ua.id = cmlm.affiliate_id
    LEFT JOIN wp_users u ON ua.uid = u.ID
    WHERE umlm.parent_affiliate_id != cmlm.parent_affiliate_id
    AND ua.uid > 0
");

if (empty($inconsistent_parents)) {
    echo "✅ Todas las relaciones tienen el mismo padre en ambas tablas\n";
    $ok_count++;
} else {
    echo "⚠️ Encontradas " . count($inconsistent_parents) . " inconsistencias de padres:\n";
    foreach ($inconsistent_parents as $inc) {
        echo "   - {$inc->user_login} (Aff {$inc->affiliate_id}): UAP padre={$inc->uap_parent}, CV padre={$inc->cv_parent}\n";
    }
    $issues[] = count($inconsistent_parents) . " inconsistencias de padres";
}
echo "\n";

// ============================================================
// 2. VERIFICACIÓN DE DUPLICADOS
// ============================================================
echo "📋 2. VERIFICACIÓN DE DUPLICADOS\n";
echo "==================================================\n";

// Duplicados en UAP
$duplicates_uap = $wpdb->get_results("
    SELECT affiliate_id, COUNT(*) as count
    FROM wp_uap_mlm_relations
    GROUP BY affiliate_id
    HAVING COUNT(*) > 1
");

if (empty($duplicates_uap)) {
    echo "✅ No hay duplicados en UAP\n";
    $ok_count++;
} else {
    echo "⚠️ Encontrados " . count($duplicates_uap) . " duplicados en UAP:\n";
    foreach ($duplicates_uap as $dup) {
        echo "   - Affiliate ID {$dup->affiliate_id}: {$dup->count} relaciones\n";
    }
    $issues[] = count($duplicates_uap) . " duplicados en UAP";
}
echo "\n";

// Duplicados en CV
$duplicates_cv = $wpdb->get_results("
    SELECT affiliate_id, COUNT(*) as count
    FROM wp_cvapp_mlm_relations
    GROUP BY affiliate_id
    HAVING COUNT(*) > 1
");

if (empty($duplicates_cv)) {
    echo "✅ No hay duplicados en CV\n";
    $ok_count++;
} else {
    echo "⚠️ Encontrados " . count($duplicates_cv) . " duplicados en CV:\n";
    foreach ($duplicates_cv as $dup) {
        echo "   - Affiliate ID {$dup->affiliate_id}: {$dup->count} relaciones\n";
    }
    $issues[] = count($duplicates_cv) . " duplicados en CV";
}
echo "\n";

// ============================================================
// 3. VERIFICACIÓN DE INTEGRIDAD REFERENCIAL
// ============================================================
echo "📋 3. INTEGRIDAD REFERENCIAL\n";
echo "==================================================\n";

// Afiliados en relaciones que no existen en wp_uap_affiliates
$invalid_affiliates = $wpdb->get_results("
    SELECT DISTINCT umlm.affiliate_id
    FROM wp_uap_mlm_relations umlm
    LEFT JOIN wp_uap_affiliates ua ON umlm.affiliate_id = ua.id
    WHERE ua.id IS NULL
");

if (empty($invalid_affiliates)) {
    echo "✅ Todas las relaciones UAP referencian afiliados válidos\n";
    $ok_count++;
} else {
    echo "⚠️ Encontrados " . count($invalid_affiliates) . " affiliate_id inválidos en UAP:\n";
    foreach ($invalid_affiliates as $inv) {
        echo "   - Affiliate ID {$inv->affiliate_id}\n";
    }
    $issues[] = count($invalid_affiliates) . " affiliate_id inválidos en UAP";
}
echo "\n";

// Padres que no existen como afiliados (excepto si padre = 0, que es root)
$invalid_parents = $wpdb->get_results("
    SELECT DISTINCT umlm.parent_affiliate_id
    FROM wp_uap_mlm_relations umlm
    LEFT JOIN wp_uap_affiliates ua ON umlm.parent_affiliate_id = ua.id
    WHERE ua.id IS NULL 
    AND umlm.parent_affiliate_id != 0
    AND umlm.parent_affiliate_id != 1
");

if (empty($invalid_parents)) {
    echo "✅ Todas las relaciones UAP referencian padres válidos\n";
    $ok_count++;
} else {
    echo "⚠️ Encontrados " . count($invalid_parents) . " parent_affiliate_id inválidos en UAP:\n";
    foreach ($invalid_parents as $inv) {
        echo "   - Parent ID {$inv->parent_affiliate_id}\n";
    }
    $issues[] = count($invalid_parents) . " parent_affiliate_id inválidos en UAP";
}
echo "\n";

// Afiliados en CV que no existen en UAP
$invalid_cv_affiliates = $wpdb->get_results("
    SELECT DISTINCT cmlm.affiliate_id
    FROM wp_cvapp_mlm_relations cmlm
    LEFT JOIN wp_uap_affiliates ua ON cmlm.affiliate_id = ua.id
    WHERE ua.id IS NULL
");

if (empty($invalid_cv_affiliates)) {
    echo "✅ Todas las relaciones CV referencian afiliados válidos\n";
    $ok_count++;
} else {
    echo "⚠️ Encontrados " . count($invalid_cv_affiliates) . " affiliate_id inválidos en CV:\n";
    foreach ($invalid_cv_affiliates as $inv) {
        echo "   - Affiliate ID {$inv->affiliate_id}\n";
    }
    $issues[] = count($invalid_cv_affiliates) . " affiliate_id inválidos en CV";
}
echo "\n";

// ============================================================
// 4. VERIFICACIÓN DE CICLOS EN EL ÁRBOL MLM
// ============================================================
echo "📋 4. VERIFICACIÓN DE CICLOS (Auto-referencias)\n";
echo "==================================================\n";

$self_references = $wpdb->get_results("
    SELECT affiliate_id, parent_affiliate_id
    FROM wp_uap_mlm_relations
    WHERE affiliate_id = parent_affiliate_id
");

if (empty($self_references)) {
    echo "✅ No hay auto-referencias (un afiliado siendo su propio padre)\n";
    $ok_count++;
} else {
    echo "⚠️ Encontradas " . count($self_references) . " auto-referencias:\n";
    foreach ($self_references as $self) {
        echo "   - Affiliate ID {$self->affiliate_id} es su propio padre\n";
    }
    $issues[] = count($self_references) . " auto-referencias detectadas";
}
echo "\n";

// ============================================================
// 5. ESTADÍSTICAS DETALLADAS
// ============================================================
echo "📋 5. ESTADÍSTICAS DETALLADAS\n";
echo "==================================================\n";

$total_affiliates = $wpdb->get_var("SELECT COUNT(*) FROM wp_uap_affiliates WHERE uid > 0");
$affiliates_with_relations = $wpdb->get_var("SELECT COUNT(DISTINCT affiliate_id) FROM wp_uap_mlm_relations");
$affiliates_without_relations = $total_affiliates - $affiliates_with_relations;

echo "Total afiliados activos (uid > 0): {$total_affiliates}\n";
echo "Afiliados con relación MLM: {$affiliates_with_relations}\n";
echo "Afiliados sin relación MLM: {$affiliates_without_relations}\n\n";

// Distribución por niveles
echo "Distribución de padres (top 10):\n";
$parent_distribution = $wpdb->get_results("
    SELECT parent_affiliate_id, COUNT(*) as children_count
    FROM wp_uap_mlm_relations
    GROUP BY parent_affiliate_id
    ORDER BY children_count DESC
    LIMIT 10
");

foreach ($parent_distribution as $dist) {
    $parent_info = $wpdb->get_row($wpdb->prepare(
        "SELECT ua.id, u.user_login FROM wp_uap_affiliates ua 
         LEFT JOIN wp_users u ON ua.uid = u.ID 
         WHERE ua.id = %d",
        $dist->parent_affiliate_id
    ));
    $parent_name = $parent_info ? ($parent_info->user_login ?: "ID {$dist->parent_affiliate_id}") : "ID {$dist->parent_affiliate_id}";
    echo "   - Padre {$dist->parent_affiliate_id} ({$parent_name}): {$dist->children_count} hijos\n";
}
echo "\n";

// ============================================================
// 6. RESUMEN FINAL
// ============================================================
echo "==================================================\n";
echo "📊 RESUMEN FINAL\n";
echo "==================================================\n";
echo "✅ Verificaciones correctas: {$ok_count}\n";
echo "⚠️ Advertencias: " . count($warnings) . "\n";
echo "❌ Problemas encontrados: " . count($issues) . "\n\n";

if (empty($issues) && empty($warnings)) {
    echo "🎉 ¡PERFECTO! Todas las verificaciones pasaron correctamente.\n";
    echo "Las tablas MLM están completamente sincronizadas y sin errores.\n\n";
} else {
    if (!empty($issues)) {
        echo "❌ PROBLEMAS CRÍTICOS:\n";
        foreach ($issues as $issue) {
            echo "   - {$issue}\n";
        }
        echo "\n";
    }
    
    if (!empty($warnings)) {
        echo "⚠️ ADVERTENCIAS:\n";
        foreach ($warnings as $warning) {
            echo "   - {$warning}\n";
        }
        echo "\n";
    }
    
    echo "🔧 Para corregir automáticamente, ejecuta:\n";
    echo "   wp eval-file wp-content/plugins/cv-commissions/tools/sync-mlm-tables.php\n\n";
}

echo "==================================================\n";



