<?php
/**
 * Reporte Diario de Estadísticas
 * 
 * Envía un resumen diario de estadísticas por WhatsApp a las 8:00 AM
 * 
 * @package CV_Stats
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CV_Stats_Daily_Report {
    
    /**
     * Teléfonos de administradores para recibir el reporte
     */
    private $admin_phones = array(
        '+34629159114',
        '+34655573433'
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Registrar el cron job
        add_action('wp', array($this, 'schedule_daily_report'));
        
        // Hook para ejecutar el reporte
        add_action('cv_stats_daily_report', array($this, 'send_daily_report'));
        
        // Desactivación del plugin
        register_deactivation_hook(CV_STATS_PLUGIN_DIR . 'cv-stats.php', array($this, 'clear_scheduled_event'));
    }
    
    /**
     * Programar el cron job diario
     */
    public function schedule_daily_report() {
        if (!wp_next_scheduled('cv_stats_daily_report')) {
            // Programar para las 8:00 AM todos los días
            $timestamp = strtotime('tomorrow 08:00:00');
            wp_schedule_event($timestamp, 'daily', 'cv_stats_daily_report');
            
            error_log('✅ CV Stats: Cron job programado para las 8:00 AM diarias');
        }
    }
    
    /**
     * Limpiar eventos programados al desactivar
     */
    public function clear_scheduled_event() {
        $timestamp = wp_next_scheduled('cv_stats_daily_report');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'cv_stats_daily_report');
            error_log('🧹 CV Stats: Cron job eliminado');
        }
    }
    
    /**
     * Enviar reporte diario
     */
    public function send_daily_report() {
        error_log('📊 CV Stats: Generando reporte diario...');
        
        // Obtener estadísticas del día anterior
        $stats = $this->get_yesterday_stats();
        
        // Generar mensaje de WhatsApp
        $message = $this->generate_whatsapp_message($stats);
        
        // Enviar a cada administrador
        foreach ($this->admin_phones as $phone) {
            $sent = $this->send_whatsapp($phone, $message);
            
            if ($sent) {
                error_log("✅ CV Stats: Reporte enviado a {$phone}");
            } else {
                error_log("❌ CV Stats: Error al enviar reporte a {$phone}");
            }
        }
        
        error_log('📊 CV Stats: Reporte diario completado');
    }
    
    /**
     * Obtener estadísticas del día anterior
     */
    private function get_yesterday_stats() {
        global $wpdb;
        
        // Fechas del día anterior
        $yesterday_start = date('Y-m-d 00:00:00', strtotime('yesterday'));
        $yesterday_end = date('Y-m-d 23:59:59', strtotime('yesterday'));
        
        $stats = array(
            'date' => date('d/m/Y', strtotime('yesterday')),
            'day_name' => $this->get_spanish_day_name(date('N', strtotime('yesterday')))
        );
        
        // 1. Usuarios logueados
        $table_logins = $wpdb->prefix . 'cv_user_logins';
        $stats['logins'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT user_id)
            FROM {$table_logins}
            WHERE login_time >= %s AND login_time <= %s
        ", $yesterday_start, $yesterday_end));
        
        // Detalle de usuarios logueados
        $stats['logins_details'] = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT l.user_id, u.user_login, u.display_name
            FROM {$table_logins} l
            LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
            WHERE l.login_time >= %s AND l.login_time <= %s
            ORDER BY u.display_name
            LIMIT 10
        ", $yesterday_start, $yesterday_end), ARRAY_A);
        
        // 2. Tarjetas vistas
        $table_card_views = $wpdb->prefix . 'cv_card_views';
        $stats['card_views_unique'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT card_owner_id)
            FROM {$table_card_views}
            WHERE view_time >= %s AND view_time <= %s
        ", $yesterday_start, $yesterday_end));
        
        $stats['card_views_total'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$table_card_views}
            WHERE view_time >= %s AND view_time <= %s
        ", $yesterday_start, $yesterday_end));
        
        // 3. Tarjetas enviadas por WhatsApp
        $table_whatsapp = $wpdb->prefix . 'cv_card_whatsapp_sends';
        $stats['whatsapp_sends'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$table_whatsapp}
            WHERE send_time >= %s AND send_time <= %s
        ", $yesterday_start, $yesterday_end));
        
        // 4. Tarjetas creadas (basado en fecha de registro del usuario)
        // Solo usuarios con roles válidos que tengan tarjetas reales del plugin
        $stats['cards_created'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT u.ID)
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            LEFT JOIN {$wpdb->posts} p ON u.ID = p.post_author AND p.post_type = 'card' AND p.post_status IN ('publish', 'draft', 'pending')
            WHERE u.user_registered >= %s 
            AND u.user_registered <= %s
            AND um.meta_key = '{$wpdb->prefix}capabilities'
            AND (
                um.meta_value LIKE '%%wcfm_vendor%%' 
                OR um.meta_value LIKE '%%dc_vendor%%'
                OR um.meta_value LIKE '%%customer%%'
            )
            AND p.ID IS NOT NULL
        ", $yesterday_start, $yesterday_end));
        
        // Detalles de tarjetas creadas (para el mensaje)
        $stats['cards_created_details'] = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, p.post_title, p.post_author, u.user_login, u.display_name, u.user_registered
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            LEFT JOIN {$wpdb->posts} p ON u.ID = p.post_author AND p.post_type = 'card' AND p.post_status IN ('publish', 'draft', 'pending')
            WHERE u.user_registered >= %s 
            AND u.user_registered <= %s
            AND um.meta_key = '{$wpdb->prefix}capabilities'
            AND (
                um.meta_value LIKE '%%wcfm_vendor%%' 
                OR um.meta_value LIKE '%%dc_vendor%%'
                OR um.meta_value LIKE '%%customer%%'
            )
            AND p.ID IS NOT NULL
            GROUP BY u.ID
            ORDER BY u.user_registered DESC
            LIMIT 10
        ", $yesterday_start, $yesterday_end), ARRAY_A);
        
        // 5. Productos creados (usando post_date directamente)
        $yesterday_date = date('Y-m-d', strtotime('yesterday'));
        $products_created = CV_Stats_Product_Simple::get_created_products($yesterday_date, $yesterday_date);
        $stats['products_created'] = count($products_created);
        $stats['products_created_details'] = array_slice($products_created, 0, 10); // Top 10
        
        // 6. Productos actualizados (usando post_modified, excluyendo recién creados)
        $products_updated = CV_Stats_Product_Simple::get_updated_products($yesterday_date, $yesterday_date);
        $stats['products_updated'] = count($products_updated);
        $stats['products_updated_details'] = array_slice($products_updated, 0, 10); // Top 10
        
        // 7. Productos afiliados (copias de productos)
        $products_affiliated = CV_Stats_Product_Simple::get_affiliated_products($yesterday_date, $yesterday_date);
        $stats['products_affiliated'] = count($products_affiliated);
        $stats['products_affiliated_details'] = array_slice($products_affiliated, 0, 10); // Top 10
        
        // 8. Consultas de contacto
        $table_contact = $wpdb->prefix . 'cv_stats_contact_queries';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_contact}'") === $table_contact) {
            $stats['contact_queries_total'] = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*)
                FROM {$table_contact}
                WHERE created_at >= %s AND created_at <= %s
            ", $yesterday_start, $yesterday_end));
            
            $stats['contact_plataforma'] = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*)
                FROM {$table_contact}
                WHERE category = 'plataforma'
                AND created_at >= %s AND created_at <= %s
            ", $yesterday_start, $yesterday_end));
            
            $stats['contact_comercio'] = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*)
                FROM {$table_contact}
                WHERE category = 'comercio'
                AND created_at >= %s AND created_at <= %s
            ", $yesterday_start, $yesterday_end));
            
            // Detalles de consultas
            $stats['contact_details'] = $wpdb->get_results($wpdb->prepare("
                SELECT name, email, category, subject, created_at
                FROM {$table_contact}
                WHERE created_at >= %s AND created_at <= %s
                ORDER BY created_at DESC
                LIMIT 5
            ", $yesterday_start, $yesterday_end), ARRAY_A);
        } else {
            $stats['contact_queries_total'] = 0;
            $stats['contact_plataforma'] = 0;
            $stats['contact_comercio'] = 0;
            $stats['contact_details'] = array();
        }
        
        // 8. Tickets (si existe la tabla)
        $table_tickets = $wpdb->prefix . 'cv_tickets';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_tickets}'") === $table_tickets) {
            $stats['tickets_received'] = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*)
                FROM {$table_tickets}
                WHERE created_at >= %s AND created_at <= %s
            ", $yesterday_start, $yesterday_end));
            
            $stats['tickets_validated'] = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*)
                FROM {$table_tickets}
                WHERE status = 'validated'
                AND validated_at >= %s AND validated_at <= %s
            ", $yesterday_start, $yesterday_end));
            
            $stats['tickets_amount'] = $wpdb->get_var($wpdb->prepare("
                SELECT SUM(amount)
                FROM {$table_tickets}
                WHERE created_at >= %s AND created_at <= %s
            ", $yesterday_start, $yesterday_end));
        } else {
            $stats['tickets_received'] = 0;
            $stats['tickets_validated'] = 0;
            $stats['tickets_amount'] = 0;
        }
        
        // Convertir a enteros/flotantes (excepto arrays y strings)
        $array_keys = array('date', 'day_name', 'cards_created_details', 'logins_details', 'contact_details', 
                            'products_created_details', 'products_updated_details', 'products_affiliated_details');
        foreach ($stats as $key => $value) {
            if (!in_array($key, $array_keys) && !is_array($value)) {
                $stats[$key] = $key === 'tickets_amount' ? floatval($value) : intval($value);
            }
        }
        
        return $stats;
    }
    
    /**
     * Generar mensaje de WhatsApp
     */
    private function generate_whatsapp_message($stats) {
        $message = "📊 *RESUMEN DIARIO - Ciudad Virtual Marketplace*\n\n";
        $message .= "📅 {$stats['day_name']}, {$stats['date']}\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━\n\n";
        
        // Usuarios
        $message .= "👥 *USUARIOS CONECTADOS*\n";
        $message .= "• Logins: *{$stats['logins']}* usuarios\n";
        
        if (!empty($stats['logins_details']) && $stats['logins'] > 0) {
            $message .= "\n_Usuarios que se conectaron:_\n";
            foreach ($stats['logins_details'] as $login) {
                $message .= "  • *{$login['display_name']}* (@{$login['user_login']})\n";
            }
        }
        $message .= "\n";
        
        // Tarjetas creadas (NUEVO - PRIMERA SECCIÓN)
        $message .= "🎴 *TARJETAS DIGITALES CREADAS*\n";
        $message .= "• Nuevas tarjetas: *{$stats['cards_created']}*\n";
        
        if (!empty($stats['cards_created_details']) && $stats['cards_created'] > 0) {
            $message .= "\n_Detalle de tarjetas creadas:_\n";
            foreach ($stats['cards_created_details'] as $card) {
                // Obtener sponsor
                $sponsor_id = get_user_meta($card['post_author'], 'user_registration_referido', true);
                $sponsor_name = '';
                
                if (!empty($sponsor_id)) {
                    $sponsor_user = false;
                    if (is_numeric($sponsor_id)) {
                        $sponsor_user = get_user_by('id', intval($sponsor_id));
                    } elseif (strpos($sponsor_id, '@') !== false) {
                        $sponsor_user = get_user_by('email', $sponsor_id);
                    } else {
                        $sponsor_user = get_user_by('login', $sponsor_id);
                    }
                    
                    if ($sponsor_user) {
                        $sponsor_store = get_user_meta($sponsor_user->ID, 'store_name', true);
                        $sponsor_name = !empty($sponsor_store) ? $sponsor_store : $sponsor_user->display_name;
                    }
                }
                
                $card_url = get_permalink($card['ID']);
                $message .= "  • *{$card['display_name']}* (@{$card['user_login']})\n";
                
                if ($sponsor_name) {
                    $message .= "    Sponsor: 🎯 {$sponsor_name}\n";
                }
                
                $message .= "    {$card_url}\n";
            }
        }
        $message .= "\n";
        
        // Tarjetas vistas
        $message .= "💳 *ACTIVIDAD DE TARJETAS*\n";
        $message .= "• Tarjetas vistas: *{$stats['card_views_unique']}* diferentes\n";
        $message .= "• Total de vistas: *{$stats['card_views_total']}*\n";
        $message .= "• Enviadas por WhatsApp: *{$stats['whatsapp_sends']}*\n\n";
        
        // Productos
        $message .= "📦 *PRODUCTOS*\n";
        $message .= "• Creados: *{$stats['products_created']}* nuevos\n";
        $message .= "• Actualizados: *{$stats['products_updated']}*\n";
        $message .= "• Afiliados: *{$stats['products_affiliated']}*\n";
        
        // Detalles de productos creados
        if (!empty($stats['products_created_details']) && $stats['products_created'] > 0) {
            $message .= "\n_Productos creados:_\n";
            foreach ($stats['products_created_details'] as $product) {
                $message .= "  • *{$product['product_name']}*\n";
                $message .= "    Por: {$product['vendor_name']} (@{$product['vendor_username']})\n";
            }
            // Si hay más de 10, mostrar cuántos más
            if ($stats['products_created'] > 10) {
                $remaining = $stats['products_created'] - 10;
                $message .= "  _...y otros {$remaining} más_\n";
            }
        }
        
        // Detalles de productos actualizados
        if (!empty($stats['products_updated_details']) && $stats['products_updated'] > 0) {
            $message .= "\n_Productos actualizados:_\n";
            foreach ($stats['products_updated_details'] as $product) {
                $message .= "  • *{$product['product_name']}*\n";
                $message .= "    Por: {$product['modified_by_name']} (@{$product['modified_by_username']})\n";
            }
            // Si hay más de 10, mostrar cuántos más
            if ($stats['products_updated'] > 10) {
                $remaining = $stats['products_updated'] - 10;
                $message .= "  _...y otros {$remaining} más_\n";
            }
        }
        
        // Detalles de productos afiliados
        if (!empty($stats['products_affiliated_details']) && $stats['products_affiliated'] > 0) {
            $message .= "\n_Productos afiliados:_\n";
            foreach ($stats['products_affiliated_details'] as $aff) {
                $message .= "  • *{$aff['product_name']}*\n";
                $message .= "    Afiliado: {$aff['vendor_name']} (@{$aff['vendor_username']})\n";
                $message .= "    Original: {$aff['owner_name']} - Comisión: {$aff['commission_rate']}%\n";
            }
            // Si hay más de 10, mostrar cuántos más
            if ($stats['products_affiliated'] > 10) {
                $remaining = $stats['products_affiliated'] - 10;
                $message .= "  _...y otros {$remaining} más_\n";
            }
        }
        
        $message .= "\n";
        
        // Consultas de contacto (siempre se muestra)
        $message .= "📧 *CONSULTAS DE CONTACTO*\n";
        $message .= "• Total consultas: *{$stats['contact_queries_total']}*\n";
        $message .= "  - 💻 Plataforma: *{$stats['contact_plataforma']}*\n";
        $message .= "  - 🏪 Comercio: *{$stats['contact_comercio']}*\n";
        
        if (!empty($stats['contact_details'])) {
            $message .= "\n_Últimas consultas:_\n";
            foreach ($stats['contact_details'] as $contact) {
                $cat_icon = $contact['category'] === 'plataforma' ? '💻' : '🏪';
                $message .= "  {$cat_icon} *{$contact['name']}* ({$contact['email']})\n";
                $message .= "    Asunto: _{$contact['subject']}_\n";
            }
        }
        $message .= "\n";
        
        // Tickets (solo si hay datos)
        if ($stats['tickets_received'] > 0 || $stats['tickets_validated'] > 0) {
            $message .= "🎟️ *TICKETS*\n";
            $message .= "• Recibidos: *{$stats['tickets_received']}*\n";
            $message .= "• Validados: *{$stats['tickets_validated']}*\n";
            $message .= "• Importe total: *" . number_format($stats['tickets_amount'], 2) . "€*\n\n";
        }
        
        $message .= "━━━━━━━━━━━━━━━━━━━━━━\n\n";
        
        // Link al panel
        $message .= "🔗 *Ver estadísticas completas:*\n\n";
        $message .= home_url('/wp-admin/admin.php?page=cv-stats') . "\n\n";
        
        // Nota de privacidad
        $message .= "⚠️ *SOLO ADMINISTRADORES*\n";
        $message .= "_Este mensaje es confidencial y solo para uso interno del equipo administrativo de Ciudad Virtual._\n\n";
        
        $message .= "🤖 _Mensaje automático generado a las " . date('H:i') . "h_";
        
        return $message;
    }
    
    /**
     * Enviar mensaje por WhatsApp usando Ultramsg
     */
    private function send_whatsapp($phone, $message) {
        // Obtener credenciales de Ultramsg desde la configuración de cv-commissions
        $config = get_option('cv_commissions_config', array());
        $ultramsg_instance = $config['ultramsg_instance'] ?? '';
        $ultramsg_token = $config['ultramsg_token'] ?? '';
        
        if (empty($ultramsg_instance) || empty($ultramsg_token)) {
            error_log('❌ CV Stats: Credenciales de Ultramsg no configuradas');
            return false;
        }
        
        // Limpiar número de teléfono (eliminar espacios, guiones, etc.)
        $phone_clean = preg_replace('/[^0-9+]/', '', $phone);
        
        // API de Ultramsg
        $url = "https://api.ultramsg.com/{$ultramsg_instance}/messages/chat";
        
        $params = array(
            'token' => $ultramsg_token,
            'to' => $phone_clean,
            'body' => $message,
            'priority' => '10'
        );
        
        $response = wp_remote_post($url, array(
            'body' => $params,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('❌ CV Stats: Error Ultramsg - ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code === 200) {
            $result = json_decode($response_body, true);
            error_log('✅ CV Stats: Respuesta Ultramsg (' . $response_code . '): ' . $response_body);
            if (isset($result['sent']) && $result['sent'] === true) {
                return true;
            }
        }
        
        error_log('❌ CV Stats: Respuesta Ultramsg (' . $response_code . '): ' . $response_body);
        return false;
    }
    
    /**
     * Obtener nombre del día en español
     */
    private function get_spanish_day_name($day_number) {
        $days = array(
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo'
        );
        
        return isset($days[$day_number]) ? $days[$day_number] : '';
    }
    
    /**
     * Método manual para probar el envío
     * USO: wp eval 'CV_Stats_Daily_Report::test_report();' --allow-root
     */
    public static function test_report() {
        $reporter = new self();
        $reporter->send_daily_report();
        echo "✅ Reporte de prueba enviado\n";
    }
}

new CV_Stats_Daily_Report();

