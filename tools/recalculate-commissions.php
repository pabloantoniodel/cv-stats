<?php
/**
 * Herramienta para Recalcular Comisiones
 * 
 * Recalcula todas las comisiones en uap_referrals usando
 * la lógica actual del plugin CV_Commissions
 * 
 * @package CV_Commissions
 * @since 1.0.4
 */

// Cargar WordPress
require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/wp-load.php';

// Verificar que solo administradores puedan ejecutar esto
if (!current_user_can('manage_options')) {
    die('❌ No tienes permisos para ejecutar esta herramienta');
}

// Modo de ejecución
$dry_run = isset($_GET['dry_run']) ? true : false; // dry_run=1 para simular sin guardar
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 0; // limit=10 para procesar solo 10

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recalcular Comisiones CV</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #667eea; padding-bottom: 10px; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
        .success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8; padding: 15px; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 13px; }
        th { background: #667eea; color: #fff; padding: 12px 8px; text-align: left; }
        td { padding: 10px 8px; border-bottom: 1px solid #e0e0e0; }
        tr:hover { background: #f8f9fa; }
        .changed { background: #fff3cd; }
        .btn { display: inline-block; padding: 12px 24px; background: #667eea; color: #fff; text-decoration: none; border-radius: 6px; margin: 10px 5px; font-weight: 600; }
        .btn:hover { background: #5568d3; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
        .stat-card { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; }
        .stat-card h3 { margin: 0 0 10px 0; color: #666; font-size: 14px; }
        .stat-card .value { font-size: 32px; font-weight: bold; color: #667eea; }
        .progress { background: #e0e0e0; height: 30px; border-radius: 15px; overflow: hidden; margin: 20px 0; }
        .progress-bar { background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); height: 100%; text-align: center; line-height: 30px; color: #fff; font-weight: 600; transition: width 0.3s ease; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Recalcular Comisiones CV</h1>
        
        <?php if ($dry_run): ?>
            <div class="warning">
                <strong>⚠️ MODO SIMULACIÓN (Dry Run)</strong><br>
                Los cambios se mostrarán pero NO se guardarán en la base de datos.
                <br><br>
                <a href="?dry_run=0" class="btn btn-danger">Ejecutar REAL (Guardar Cambios)</a>
            </div>
        <?php else: ?>
            <div class="error">
                <strong>🚨 MODO REAL - Los cambios se guardarán en la base de datos</strong><br>
                Se recomienda hacer un backup antes de continuar.
                <br><br>
                <a href="?dry_run=1" class="btn">Ver Simulación Primero</a>
            </div>
        <?php endif; ?>
        
        <?php
        
        // Iniciar proceso
        $start_time = microtime(true);
        
        global $wpdb;
        $uap_referrals = $wpdb->prefix . 'uap_referrals';
        
        // Obtener configuración del plugin
        $config = get_option('cv_commissions_config', array());
        
        // Si está vacía, cargar configuración por defecto
        if (empty($config)) {
            echo '<div class="warning">';
            echo '<strong>⚠️ Advertencia:</strong> No se encontró configuración guardada. ';
            echo 'Usando configuración por defecto del plugin.<br>';
            echo '<small>Ve a <a href="' . admin_url('admin.php?page=cv-commissions') . '">CV Comisiones → Configuración</a> para personalizar los valores.</small>';
            echo '</div>';
            
            // Cargar configuración por defecto
            require_once CV_COMMISSIONS_PLUGIN_DIR . 'config/default-config.php';
            $config = cv_commissions_default_config();
        }
        
        // Verificar que la configuración tenga los campos necesarios
        $required_fields = array('programmer_commission_percent', 'buyer_commission_percent', 'mlm_levels', 'mlm_level_percent');
        $missing_fields = array();
        
        foreach ($required_fields as $field) {
            if (!isset($config[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            echo '<div class="error">';
            echo '❌ Error: La configuración está incompleta. Faltan campos: <strong>' . implode(', ', $missing_fields) . '</strong><br>';
            echo 'Ve a <a href="' . admin_url('admin.php?page=cv-commissions') . '">CV Comisiones → Configuración</a> y guarda la configuración.';
            echo '</div>';
            exit;
        }
        
        // Cargar clases necesarias
        require_once CV_COMMISSIONS_PLUGIN_DIR . 'includes/class-cv-commission-calculator.php';
        $calculator = new CV_Commission_Calculator($config);
        
        echo '<div class="info">';
        echo '<strong>✅ Configuración cargada correctamente:</strong><br>';
        echo '- Comisión Programador: <strong>' . $config['programmer_commission_percent'] . '%</strong><br>';
        echo '- Comisión Comprador: <strong>' . $config['buyer_commission_percent'] . '%</strong><br>';
        echo '- Niveles MLM: <strong>' . $config['mlm_levels'] . '</strong><br>';
        echo '- Porcentaje por Nivel MLM: <strong>' . $config['mlm_level_percent'] . '%</strong>';
        echo '</div>';
        
        // Obtener todos los referrals de comisiones CV (source='Calculo privado')
        $query = "SELECT * FROM {$uap_referrals} 
                  WHERE source='Calculo privado' 
                  AND reference IS NOT NULL 
                  AND reference != '' 
                  AND reference REGEXP '^[0-9]+$'
                  ORDER BY date DESC";
        
        if ($limit > 0) {
            $query .= " LIMIT " . $limit;
        }
        
        $referrals = $wpdb->get_results($query);
        
        echo '<div class="info">';
        echo '<strong>ℹ️ Información:</strong><br>';
        echo 'Total de registros a procesar: <strong>' . count($referrals) . '</strong><br>';
        echo 'Modo: <strong>' . ($dry_run ? 'SIMULACIÓN' : 'REAL') . '</strong><br>';
        echo '</div>';
        
        // Estadísticas
        $stats = array(
            'total' => count($referrals),
            'processed' => 0,
            'changed' => 0,
            'unchanged' => 0,
            'errors' => 0,
            'old_total' => 0,
            'new_total' => 0,
            'difference' => 0
        );
        
        $changes = array();
        
        echo '<div class="progress">';
        echo '<div class="progress-bar" id="progress-bar" style="width: 0%;">0%</div>';
        echo '</div>';
        
        // Debug: mostrar primeros 5 registros
        if (count($referrals) > 0) {
            echo '<div class="info">';
            echo '<strong>🔍 Debug - Primeros 5 registros en BD:</strong><br>';
            echo '<pre style="background:#f8f9fa;padding:10px;border-radius:4px;overflow:auto;font-size:12px;">';
            foreach (array_slice($referrals, 0, 5) as $r) {
                echo sprintf(
                    "ID: %d | User: %d | Aff: %d | Order: %s | Amount: %s | Desc: %s | Details: %s\n",
                    $r->id,
                    $r->refferal_wp_uid,
                    $r->affiliate_id,
                    $r->reference,
                    $r->amount,
                    $r->description,
                    $r->reference_details
                );
            }
            echo '</pre>';
            echo '</div>';
        }
        
        echo '<table>';
        echo '<thead><tr>';
        echo '<th>ID</th>';
        echo '<th>Pedido</th>';
        echo '<th>Descripción Original</th>';
        echo '<th>Tipo Detectado</th>';
        echo '<th>Monto Actual (BD)</th>';
        echo '<th>Monto Recalculado</th>';
        echo '<th>Diferencia</th>';
        echo '<th>Estado</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($referrals as $index => $referral) {
            $stats['processed']++;
            
            $order_id = intval($referral->reference);
            $old_amount = floatval($referral->amount);
            
            // Debug del primer registro
            if ($index == 0) {
                error_log('CV Recalc Debug - Primer registro:');
                error_log('  ID: ' . $referral->id);
                error_log('  Order ID: ' . $order_id);
                error_log('  Old Amount (BD): ' . $old_amount);
                error_log('  Description: ' . $referral->description);
                error_log('  Reference Details: ' . $referral->reference_details);
                error_log('  User ID: ' . $referral->refferal_wp_uid);
                error_log('  Affiliate ID: ' . $referral->affiliate_id);
            }
            
            // Recalcular comisiones
            try {
                $commissions = $calculator->calculate_order_commissions($order_id);
                
                // Debug del primer recálculo
                if ($index == 0) {
                    error_log('CV Recalc Debug - Resultado del cálculo:');
                    error_log(print_r($commissions, true));
                }
                
                if (!$commissions || !isset($commissions['order_id'])) {
                    echo '<tr class="error">';
                    echo '<td>' . $referral->id . '</td>';
                    echo '<td>#' . $order_id . '</td>';
                    echo '<td>' . esc_html($referral->reference_details) . '</td>';
                    echo '<td>-</td>';
                    echo '<td>' . wc_price($old_amount) . '</td>';
                    echo '<td colspan="2">❌ Error al calcular (commissions vacío)</td>';
                    echo '<td>ERROR</td>';
                    echo '</tr>';
                    
                    $stats['errors']++;
                    continue;
                }
                
                // Determinar qué comisión corresponde a este registro
                $new_amount = 0;
                $commission_type = 'Desconocido';
                $detection_debug = array();
                
                // Usar description (tiene más info) y reference_details como fallback
                $search_text = $referral->description . ' ' . $referral->reference_details;
                
                // Detectar tipo de comisión por la descripción
                if (stripos($search_text, 'programador') !== false) {
                    $new_amount = isset($commissions['programador']) ? $commissions['programador'] : 0;
                    $commission_type = 'Programador';
                    $detection_debug[] = 'Detectado: Programador';
                    
                } elseif (stripos($search_text, 'mlm') !== false) {
                    // Comisiones MLM - extraer nivel
                    if (preg_match('/nivel (\d+)/i', $search_text, $matches)) {
                        $level = intval($matches[1]) - 1;
                        $detection_debug[] = 'MLM detectado - Nivel: ' . ($level + 1) . ' (index: ' . $level . ')';
                        
                        if (isset($commissions['comisionstas'][$level])) {
                            if (stripos($search_text, 'comprador') !== false) {
                                $new_amount = $commissions['comisionstas'][$level]['comprador']['total'] ?? 0;
                                $commission_type = 'MLM Comprador Nivel ' . ($level + 1);
                                $detection_debug[] = 'MLM Comprador encontrado';
                            } elseif (stripos($search_text, 'vendedor') !== false) {
                                $new_amount = $commissions['comisionstas'][$level]['vendedor']['total'] ?? 0;
                                $commission_type = 'MLM Vendedor Nivel ' . ($level + 1);
                                $detection_debug[] = 'MLM Vendedor encontrado';
                            }
                        } else {
                            $detection_debug[] = 'ERROR: Nivel MLM ' . ($level + 1) . ' no existe en commissions';
                        }
                    } else {
                        $detection_debug[] = 'ERROR: No se pudo extraer nivel MLM';
                    }
                    
                } elseif (stripos($search_text, 'comprador') !== false) {
                    $new_amount = isset($commissions['comprador']) ? $commissions['comprador'] : 0;
                    $commission_type = 'Comprador';
                    $detection_debug[] = 'Detectado: Comprador';
                    
                } elseif (stripos($search_text, 'empresa') !== false) {
                    $new_amount = isset($commissions['empresa']) ? $commissions['empresa'] : 0;
                    $commission_type = 'Empresa';
                    $detection_debug[] = 'Detectado: Empresa';
                }
                
                // Debug del primer registro
                if ($index == 0) {
                    error_log('CV Recalc Debug - Detección:');
                    error_log('  Tipo detectado: ' . $commission_type);
                    error_log('  New Amount: ' . $new_amount);
                    error_log('  Detection log: ' . implode(' | ', $detection_debug));
                }
                
                $difference = $new_amount - $old_amount;
                $is_changed = abs($difference) > 0.01; // Diferencia mayor a 1 céntimo
                
                $row_class = $is_changed ? 'changed' : '';
                
                echo '<tr class="' . $row_class . '" title="User: ' . $referral->refferal_wp_uid . ' | Affiliate: ' . $referral->affiliate_id . '">';
                echo '<td>' . $referral->id . '</td>';
                echo '<td><a href="' . admin_url('post.php?post=' . $order_id . '&action=edit') . '" target="_blank">#' . $order_id . '</a></td>';
                echo '<td><small>' . esc_html($referral->description) . '</small></td>';
                echo '<td><strong style="color:#667eea;">' . $commission_type . '</strong>';
                if (!empty($detection_debug) && $index < 5) {
                    echo '<br><small style="color:#999;">' . implode('<br>', $detection_debug) . '</small>';
                }
                echo '</td>';
                echo '<td><strong>' . wc_price($old_amount) . '</strong></td>';
                echo '<td><strong style="color:#43e97b;">' . wc_price($new_amount) . '</strong></td>';
                echo '<td>';
                if ($is_changed) {
                    $color = $difference > 0 ? 'green' : 'red';
                    echo '<strong style="color:' . $color . '">' . ($difference > 0 ? '+' : '') . wc_price($difference) . '</strong>';
                } else {
                    echo '-';
                }
                echo '</td>';
                echo '<td>';
                
                if ($is_changed) {
                    $stats['changed']++;
                    $stats['old_total'] += $old_amount;
                    $stats['new_total'] += $new_amount;
                    
                    // Actualizar en base de datos si no es dry run
                    if (!$dry_run) {
                        $updated = $wpdb->update(
                            $uap_referrals,
                            array('amount' => $new_amount),
                            array('id' => $referral->id),
                            array('%f'),
                            array('%d')
                        );
                        
                        if ($updated) {
                            echo '<span style="color:green;">✅ ACTUALIZADO</span>';
                            
                            // Guardar en log
                            $changes[] = array(
                                'id' => $referral->id,
                                'order_id' => $order_id,
                                'description' => $referral->reference_details,
                                'old_amount' => $old_amount,
                                'new_amount' => $new_amount,
                                'difference' => $difference
                            );
                        } else {
                            echo '<span style="color:red;">❌ ERROR AL ACTUALIZAR</span>';
                            $stats['errors']++;
                        }
                    } else {
                        echo '<span style="color:orange;">⚠️ CAMBIARÍA</span>';
                    }
                } else {
                    $stats['unchanged']++;
                    echo '<span style="color:gray;">➖ Sin cambio</span>';
                }
                
                echo '</td>';
                echo '</tr>';
                
                // Flush output para mostrar progreso
                if ($index % 10 == 0) {
                    $progress = round(($index / count($referrals)) * 100);
                    echo '<script>document.getElementById("progress-bar").style.width = "' . $progress . '%"; document.getElementById("progress-bar").innerText = "' . $progress . '%";</script>';
                    flush();
                }
                
            } catch (Exception $e) {
                echo '<tr class="error">';
                echo '<td>' . $referral->id . '</td>';
                echo '<td>#' . $order_id . '</td>';
                echo '<td colspan="5">❌ Excepción: ' . esc_html($e->getMessage()) . '</td>';
                echo '<td>ERROR</td>';
                echo '</tr>';
                
                $stats['errors']++;
            }
        }
        
        echo '</tbody></table>';
        
        // Completar barra de progreso
        echo '<script>document.getElementById("progress-bar").style.width = "100%"; document.getElementById("progress-bar").innerText = "100%";</script>';
        
        // Estadísticas finales
        $stats['difference'] = $stats['new_total'] - $stats['old_total'];
        $elapsed_time = round(microtime(true) - $start_time, 2);
        
        echo '<div class="stats">';
        echo '<div class="stat-card">';
        echo '<h3>Total Procesados</h3>';
        echo '<div class="value">' . $stats['processed'] . '</div>';
        echo '</div>';
        
        echo '<div class="stat-card">';
        echo '<h3>Modificados</h3>';
        echo '<div class="value" style="color:#ffc107;">' . $stats['changed'] . '</div>';
        echo '</div>';
        
        echo '<div class="stat-card">';
        echo '<h3>Sin Cambios</h3>';
        echo '<div class="value" style="color:#28a745;">' . $stats['unchanged'] . '</div>';
        echo '</div>';
        
        echo '<div class="stat-card">';
        echo '<h3>Errores</h3>';
        echo '<div class="value" style="color:#dc3545;">' . $stats['errors'] . '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="info">';
        echo '<h3>📊 Resumen Financiero:</h3>';
        echo '<p><strong>Total Anterior:</strong> ' . wc_price($stats['old_total']) . '</p>';
        echo '<p><strong>Total Nuevo:</strong> ' . wc_price($stats['new_total']) . '</p>';
        echo '<p><strong>Diferencia:</strong> ';
        $diff_color = $stats['difference'] > 0 ? 'green' : ($stats['difference'] < 0 ? 'red' : 'gray');
        echo '<span style="color:' . $diff_color . '; font-size:20px; font-weight:bold;">';
        echo ($stats['difference'] > 0 ? '+' : '') . wc_price($stats['difference']);
        echo '</span></p>';
        echo '<p><strong>Tiempo de ejecución:</strong> ' . $elapsed_time . ' segundos</p>';
        echo '</div>';
        
        // Guardar log de cambios
        if (!$dry_run && !empty($changes)) {
            $log_file = CV_COMMISSIONS_PLUGIN_DIR . 'logs/recalculation-' . date('Y-m-d-H-i-s') . '.json';
            $log_dir = dirname($log_file);
            
            if (!file_exists($log_dir)) {
                mkdir($log_dir, 0755, true);
            }
            
            file_put_contents($log_file, json_encode(array(
                'timestamp' => date('Y-m-d H:i:s'),
                'stats' => $stats,
                'changes' => $changes
            ), JSON_PRETTY_PRINT));
            
            echo '<div class="success">';
            echo '<strong>✅ Log guardado en:</strong><br>';
            echo '<code>' . esc_html($log_file) . '</code>';
            echo '</div>';
        }
        
        if ($dry_run) {
            echo '<div class="warning">';
            echo '<strong>⚠️ Recordatorio: Esto fue una SIMULACIÓN</strong><br>';
            echo 'Para aplicar los cambios realmente, ejecuta sin dry_run:';
            echo '<br><br>';
            echo '<a href="?" class="btn btn-danger">Ejecutar REAL (Guardar Cambios)</a>';
            echo '</div>';
        } else {
            echo '<div class="success">';
            echo '<strong>✅ Proceso completado</strong><br>';
            echo 'Las comisiones han sido recalculadas y actualizadas.';
            echo '</div>';
        }
        
        ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e0e0e0;">
            <a href="<?php echo admin_url('admin.php?page=cv-commissions'); ?>" class="btn">← Volver a CV Comisiones</a>
            <a href="?dry_run=1&limit=10" class="btn">Simular 10 registros</a>
            <a href="?dry_run=1" class="btn">Simular Todos</a>
            <?php if ($dry_run): ?>
                <a href="?" class="btn btn-danger">Ejecutar REAL</a>
            <?php endif; ?>
        </div>
        
    </div>
</body>
</html>

