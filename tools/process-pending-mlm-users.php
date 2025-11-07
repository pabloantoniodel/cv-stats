<?php
/**
 * Procesar usuarios registrados sin relación MLM
 * 
 * Este script procesa usuarios que se registraron pero no fueron
 * añadidos correctamente al sistema MLM.
 * 
 * Uso: wp eval-file wp-content/plugins/cv-commissions/tools/process-pending-mlm-users.php
 */

if (!defined('ABSPATH') && !defined('WP_CLI')) {
    // Cargar WordPress si se ejecuta directamente
    require_once('../../../../../wp-load.php');
}

global $wpdb, $indeed_db;

echo "🔄 Iniciando procesamiento de usuarios pendientes...\n\n";

// Obtener usuarios de los últimos 2 días
$users = $wpdb->get_results("
    SELECT u.ID, u.user_login, u.user_email, u.user_registered, 
           um.meta_value as referido,
           ua.id as affiliate_id
    FROM wp_users u
    LEFT JOIN wp_usermeta um ON u.ID = um.user_id AND um.meta_key = 'user_registration_referido'
    LEFT JOIN wp_uap_affiliates ua ON u.ID = ua.uid
    WHERE DATE(u.user_registered) >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)
    ORDER BY u.user_registered DESC
");

$processed = 0;
$skipped = 0;
$errors = 0;

foreach ($users as $user) {
    echo "\n---\n";
    echo "👤 Usuario: {$user->user_login} (ID: {$user->ID})\n";
    echo "   Email: {$user->user_email}\n";
    echo "   Registrado: {$user->user_registered}\n";
    echo "   Referido: " . ($user->referido ?: 'Sin referido') . "\n";
    
    // 1. Verificar/Crear afiliado en UAP
    $affiliate_id = $user->affiliate_id;
    
    if (!$affiliate_id) {
        echo "   ⚠️ No es afiliado, creando...\n";
        $affiliate_id = $indeed_db->save_affiliate($user->ID);
        
        if ($affiliate_id) {
            echo "   ✅ Afiliado creado: ID {$affiliate_id}\n";
            
            // Asignar rango por defecto
            $settings = $indeed_db->return_settings_from_wp_option('register');
            if (!empty($settings['uap_register_new_user_rank'])) {
                $indeed_db->update_affiliate_rank_by_uid($user->ID, $settings['uap_register_new_user_rank']);
                echo "   ✅ Rango asignado: {$settings['uap_register_new_user_rank']}\n";
            }
        } else {
            echo "   ❌ Error al crear afiliado\n";
            $errors++;
            continue;
        }
    } else {
        echo "   ℹ️ Ya es afiliado: ID {$affiliate_id}\n";
    }
    
    // 2. Determinar padre
    $parent_affiliate_id = 0;
    
    if ($user->referido) {
        // El referido puede ser un ID de usuario o un email
        if (is_numeric($user->referido)) {
            // Es un ID de usuario
            $parent_user_id = intval($user->referido);
        } else {
            // Es un email, buscar usuario
            $parent_user = get_user_by('email', $user->referido);
            $parent_user_id = $parent_user ? $parent_user->ID : 0;
        }
        
        if ($parent_user_id) {
            $parent_affiliate_id = $indeed_db->get_affiliate_id_by_wpuid($parent_user_id);
            echo "   🔍 Padre identificado: User ID {$parent_user_id} → Affiliate ID {$parent_affiliate_id}\n";
        }
    }
    
    // Si no hay referido, usar affiliate_id 1 por defecto
    if (!$parent_affiliate_id) {
        $parent_affiliate_id = 1;
        echo "   🔍 Sin referido, usando padre por defecto: Affiliate ID 1\n";
    }
    
    // 3. Verificar si ya tiene relación MLM en UAP
    $existing_parent_uap = $indeed_db->mlm_get_parent($affiliate_id);
    
    if (!$existing_parent_uap || $existing_parent_uap == 0) {
        echo "   ⚠️ No tiene relación MLM en UAP, creando...\n";
        
        // VALIDACIÓN: Prevenir auto-referencias
        if ($affiliate_id == $parent_affiliate_id) {
            echo "   ❌ PREVENCIÓN AUTO-REFERENCIA: Afiliado {$affiliate_id} no puede ser su propio padre. Usando padre 0.\n";
            $parent_affiliate_id = 0;
        }
        
        $indeed_db->add_new_mlm_relation($affiliate_id, $parent_affiliate_id);
        echo "   ✅ Relación UAP creada: {$affiliate_id} → {$parent_affiliate_id}\n";
    } else {
        echo "   ℹ️ Ya tiene relación MLM en UAP: Padre {$existing_parent_uap}\n";
        $parent_affiliate_id = $existing_parent_uap; // Usar el existente
    }
    
    // 4. Verificar si ya tiene relación en tabla CV personalizada
    $existing_parent_cv = $wpdb->get_var($wpdb->prepare(
        "SELECT parent_affiliate_id FROM wp_cvapp_mlm_relations WHERE affiliate_id = %d",
        $affiliate_id
    ));
    
    if (!$existing_parent_cv) {
        echo "   ⚠️ No tiene relación MLM en tabla CV, creando...\n";
        
        // VALIDACIÓN FINAL: Prevenir auto-referencias antes de insertar en CV
        if ($affiliate_id == $parent_affiliate_id) {
            echo "   ❌ PREVENCIÓN AUTO-REFERENCIA FINAL: No se insertará auto-referencia en CV.\n";
        } else {
            // Eliminar relaciones previas
            $wpdb->query($wpdb->prepare(
                "DELETE FROM wp_cvapp_mlm_relations WHERE affiliate_id = %d",
                $affiliate_id
            ));
            
            // Insertar nueva relación
            $result = $wpdb->query($wpdb->prepare(
                "INSERT INTO wp_cvapp_mlm_relations VALUES(NULL, %d, %d)",
                $affiliate_id,
                $parent_affiliate_id
            ));
            
            if ($result) {
                echo "   ✅ Relación CV creada: {$affiliate_id} → {$parent_affiliate_id}\n";
                $processed++;
            } else {
                echo "   ❌ Error al crear relación CV: {$wpdb->last_error}\n";
                $errors++;
            }
        }
    } else {
        echo "   ℹ️ Ya tiene relación MLM en tabla CV: Padre {$existing_parent_cv}\n";
        $skipped++;
    }
}

echo "\n\n";
echo "=====================================\n";
echo "📊 RESUMEN\n";
echo "=====================================\n";
echo "Total usuarios procesados: " . count($users) . "\n";
echo "✅ Procesados correctamente: {$processed}\n";
echo "⏭️ Omitidos (ya existían): {$skipped}\n";
echo "❌ Errores: {$errors}\n";
echo "=====================================\n";

