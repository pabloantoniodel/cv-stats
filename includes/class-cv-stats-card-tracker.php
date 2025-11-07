<?php
/**
 * Rastreador de vistas y envíos de tarjetas
 * Registra cuando se visualiza una tarjeta y cuando se comparte por WhatsApp
 *
 * @package CV_Stats
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CV_Stats_Card_Tracker {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Hook para rastrear vistas de tarjetas (wp es más tardío que template_redirect)
        add_action('wp', array($this, 'track_card_view'));
        
        // AJAX para rastrear envíos por WhatsApp
        add_action('wp_ajax_cv_track_whatsapp_send', array($this, 'track_whatsapp_send'));
        add_action('wp_ajax_nopriv_cv_track_whatsapp_send', array($this, 'track_whatsapp_send'));
        
        // Cargar script de rastreo en tarjetas
        add_action('wp_footer', array($this, 'enqueue_tracking_script'), 999);
    }
    
    /**
     * Rastrear vista de tarjeta
     */
    public function track_card_view() {
        // Detectar si estamos en una página de tarjeta
        if (!$this->is_card_page()) {
            return;
        }
        
        global $wpdb;
        
        // Obtener el usuario de la tarjeta
        $card_owner_id = $this->get_card_owner_id();
        if (!$card_owner_id) {
            return;
        }
        
        // Obtener datos del visitante
        $visitor_id = get_current_user_id(); // 0 si no está logueado
        $ip_address = $this->get_user_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $view_time = current_time('mysql');
        
        // Insertar registro de vista
        $table_name = $wpdb->prefix . 'cv_card_views';
        $wpdb->insert(
            $table_name,
            array(
                'card_owner_id' => $card_owner_id,
                'visitor_id' => $visitor_id,
                'view_time' => $view_time,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent
            ),
            array('%d', '%d', '%s', '%s', '%s')
        );
        
        error_log('👁️ CV Stats: Vista de tarjeta registrada - Owner: ' . $card_owner_id);
    }
    
    /**
     * Rastrear envío por WhatsApp (AJAX)
     */
    public function track_whatsapp_send() {
        global $wpdb;
        
        $card_owner_id = isset($_POST['card_owner_id']) ? intval($_POST['card_owner_id']) : 0;
        $phone_number = isset($_POST['phone_number']) ? sanitize_text_field($_POST['phone_number']) : '';
        
        if (!$card_owner_id || !$phone_number) {
            wp_send_json_error(array('message' => 'Datos incompletos'));
            return;
        }
        
        // Obtener datos del visitante
        $visitor_id = get_current_user_id();
        $ip_address = $this->get_user_ip();
        $send_time = current_time('mysql');
        
        // Insertar SOLO en tabla antigua wp_cvapp_envios
        $owner = get_userdata($card_owner_id);
        if ($owner) {
            $wpdb->insert(
                'wp_cvapp_envios',
                array(
                    'user_alias' => $owner->user_login,
                    'telefono' => $phone_number,
                    'nombre' => '',
                    'notas' => 'Enviado desde tarjeta digital',
                    'fecha' => $send_time,
                    'card_id' => null
                ),
                array('%s', '%s', '%s', '%s', '%s', '%d')
            );
            
            error_log('📤 CV Stats: Envío WhatsApp registrado - Owner: ' . $owner->user_login . ' (ID: ' . $card_owner_id . '), Phone: ' . $phone_number);
        }
        
        wp_send_json_success(array('message' => 'Envío registrado'));
    }
    
    /**
     * Detectar si estamos en una página de tarjeta
     */
    private function is_card_page() {
        // Detectar URL /card/USERNAME o /mi-tarjeta-ver/?ref-tarjeta=USERNAME
        $uri = $_SERVER['REQUEST_URI'];
        
        if (strpos($uri, '/card/') !== false) {
            return true;
        }
        
        if (strpos($uri, '/mi-tarjeta-ver') !== false && isset($_GET['ref-tarjeta'])) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Obtener ID del dueño de la tarjeta
     */
    private function get_card_owner_id() {
        // Método 1: Si estamos en un single post de tipo 'card', usar post_author
        if (is_singular('card')) {
            global $post;
            if ($post && $post->post_type === 'card') {
                return $post->post_author;
            }
        }
        
        // Método 2: Desde URL /card/USERNAME
        if (preg_match('#/card/([^/]+)#', $_SERVER['REQUEST_URI'], $matches)) {
            $username = urldecode($matches[1]);
            // Limpiar el username (quitar puntos finales, espacios, etc.)
            $username = trim($username, '. ');
            $username = str_replace('./', '', $username);
            
            $user = get_user_by('login', $username);
            if ($user) {
                return $user->ID;
            }
            
            // Intentar buscar el post de tipo card por slug
            global $wpdb;
            $card_post = $wpdb->get_row($wpdb->prepare("
                SELECT post_author 
                FROM {$wpdb->posts} 
                WHERE post_type = 'card' 
                AND post_name LIKE %s 
                AND post_status IN ('publish', 'draft')
                LIMIT 1
            ", '%' . $wpdb->esc_like($username) . '%'));
            
            if ($card_post) {
                return $card_post->post_author;
            }
        }
        
        // Método 3: Desde parámetro ref-tarjeta
        if (isset($_GET['ref-tarjeta'])) {
            $username = sanitize_text_field($_GET['ref-tarjeta']);
            $user = get_user_by('login', $username);
            return $user ? $user->ID : 0;
        }
        
        return 0;
    }
    
    /**
     * Obtener IP del usuario
     */
    private function get_user_ip() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return sanitize_text_field($ip);
    }
    
    /**
     * Script para rastrear clicks en WhatsApp
     */
    public function enqueue_tracking_script() {
        if (!$this->is_card_page()) {
            return;
        }
        
        $card_owner_id = $this->get_card_owner_id();
        if (!$card_owner_id) {
            return;
        }
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Rastrear clicks en enlaces de WhatsApp
            $(document).on('click', 'a[href*="whatsapp.com"], a[href*="wa.me"]', function(e) {
                var href = $(this).attr('href');
                
                // Extraer número de teléfono del enlace
                var phoneMatch = href.match(/phone=([^&]+)/) || href.match(/wa\.me\/([^?&]+)/);
                if (phoneMatch && phoneMatch[1]) {
                    var phone = decodeURIComponent(phoneMatch[1]);
                    
                    // Enviar a servidor
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'cv_track_whatsapp_send',
                            card_owner_id: <?php echo $card_owner_id; ?>,
                            phone_number: phone
                        },
                        success: function(response) {
                            console.log('📊 CV Stats: Envío WhatsApp rastreado');
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Obtener vistas de tarjetas de hoy (todas las vistas individuales)
     */
    public static function get_todays_card_views_detailed() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cv_card_views';
        $today_start = date('Y-m-d 00:00:00');
        $today_end = date('Y-m-d 23:59:59');
        
        $query = $wpdb->prepare("
            SELECT *
            FROM {$table_name}
            WHERE view_time >= %s AND view_time <= %s
            ORDER BY view_time DESC
        ", $today_start, $today_end);
        
        $results = $wpdb->get_results($query);
        
        // Enriquecer con datos de usuario
        $views = array();
        foreach ($results as $row) {
            $owner = get_userdata($row->card_owner_id);
            if ($owner) {
                $visitor = null;
                if ($row->visitor_id > 0) {
                    $visitor = get_userdata($row->visitor_id);
                }
                
                $views[] = array(
                    'owner_id' => $row->card_owner_id,
                    'owner_username' => $owner->user_login,
                    'owner_display_name' => $owner->display_name,
                    'visitor_id' => $row->visitor_id,
                    'visitor_username' => $visitor ? $visitor->user_login : 'Anónimo',
                    'visitor_display_name' => $visitor ? $visitor->display_name : 'Visitante no registrado',
                    'view_time' => $row->view_time,
                    'ip_address' => $row->ip_address
                );
            }
        }
        
        return $views;
    }
    
    /**
     * Obtener vistas de tarjetas de hoy (agrupadas por tarjeta)
     */
    public static function get_todays_card_views() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cv_card_views';
        $today_start = date('Y-m-d 00:00:00');
        $today_end = date('Y-m-d 23:59:59');
        
        $query = $wpdb->prepare("
            SELECT 
                card_owner_id,
                COUNT(*) as view_count,
                MAX(view_time) as last_view
            FROM {$table_name}
            WHERE view_time >= %s AND view_time <= %s
            GROUP BY card_owner_id
            ORDER BY view_count DESC
        ", $today_start, $today_end);
        
        $results = $wpdb->get_results($query);
        
        // Enriquecer con datos de usuario
        $views = array();
        foreach ($results as $row) {
            $user = get_userdata($row->card_owner_id);
            if ($user) {
                $views[] = array(
                    'user_id' => $row->card_owner_id,
                    'username' => $user->user_login,
                    'display_name' => $user->display_name,
                    'view_count' => $row->view_count,
                    'last_view' => $row->last_view
                );
            }
        }
        
        return $views;
    }
    
    /**
     * Obtener envíos de WhatsApp de hoy
     */
    public static function get_todays_whatsapp_sends() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cv_card_whatsapp_sends';
        $today_start = date('Y-m-d 00:00:00');
        $today_end = date('Y-m-d 23:59:59');
        
        $query = $wpdb->prepare("
            SELECT *
            FROM {$table_name}
            WHERE send_time >= %s AND send_time <= %s
            ORDER BY send_time DESC
        ", $today_start, $today_end);
        
        $results = $wpdb->get_results($query);
        
        // Enriquecer con datos de usuario
        $sends = array();
        foreach ($results as $row) {
            $user = get_userdata($row->card_owner_id);
            if ($user) {
                $sends[] = array(
                    'user_id' => $row->card_owner_id,
                    'username' => $user->user_login,
                    'display_name' => $user->display_name,
                    'phone_number' => $row->phone_number,
                    'send_time' => $row->send_time
                );
            }
        }
        
        return $sends;
    }
    
    /**
     * Obtener vistas de tarjetas detalladas por rango de fechas
     */
    public static function get_card_views_detailed_by_date_range($start_date, $end_date) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cv_card_views';
        
        $query = $wpdb->prepare("
            SELECT *
            FROM {$table_name}
            WHERE view_time >= %s AND view_time <= %s
            ORDER BY view_time DESC
        ", $start_date, $end_date);
        
        $results = $wpdb->get_results($query);
        
        // Enriquecer con datos de usuario (mismo formato que get_todays_card_views_detailed)
        $views = array();
        foreach ($results as $row) {
            $owner = get_userdata($row->card_owner_id);
            if ($owner) {
                $visitor = null;
                if ($row->visitor_id > 0) {
                    $visitor = get_userdata($row->visitor_id);
                }
                
                $views[] = array(
                    'owner_id' => $row->card_owner_id,
                    'owner_username' => $owner->user_login,
                    'owner_display_name' => $owner->display_name,
                    'visitor_id' => $row->visitor_id,
                    'visitor_username' => $visitor ? $visitor->user_login : '',
                    'visitor_display_name' => $visitor ? $visitor->display_name : '',
                    'ip_address' => $row->ip_address,
                    'view_time' => $row->view_time
                );
            }
        }
        
        return $views;
    }
    
    /**
     * Obtener tarjetas vistas (únicas) por rango de fechas
     */
    public static function get_card_views_by_date_range($start_date, $end_date) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cv_card_views';
        
        $query = $wpdb->prepare("
            SELECT DISTINCT card_owner_id, MAX(view_time) as last_view
            FROM {$table_name}
            WHERE view_time >= %s AND view_time <= %s
            GROUP BY card_owner_id
            ORDER BY last_view DESC
        ", $start_date, $end_date);
        
        $results = $wpdb->get_results($query);
        
        // Enriquecer con datos de usuario
        $views = array();
        foreach ($results as $row) {
            $user = get_userdata($row->card_owner_id);
            if ($user) {
                $views[] = array(
                    'user_id' => $row->card_owner_id,
                    'username' => $user->user_login,
                    'display_name' => $user->display_name,
                    'last_view' => $row->last_view
                );
            }
        }
        
        return $views;
    }
    
    /**
     * Obtener envíos de WhatsApp por rango de fechas
     */
    public static function get_whatsapp_sends_by_date_range($start_date, $end_date) {
        global $wpdb;
        
        // Consultar tabla antigua wp_cvapp_envios
        $query = $wpdb->prepare("
            SELECT 
                e.user_alias,
                e.telefono as phone_number,
                e.fecha as send_time,
                u.ID as user_id,
                u.display_name
            FROM wp_cvapp_envios e
            LEFT JOIN {$wpdb->users} u ON e.user_alias = u.user_login
            WHERE e.telefono IS NOT NULL 
            AND e.telefono != ''
            AND e.fecha >= %s 
            AND e.fecha <= %s
            ORDER BY e.fecha DESC
        ", $start_date, $end_date);
        
        $results = $wpdb->get_results($query);
        
        // Formatear resultados
        $sends = array();
        foreach ($results as $row) {
            $sends[] = array(
                'user_id' => $row->user_id ? $row->user_id : 0,
                'username' => $row->user_alias,
                'display_name' => $row->display_name ? $row->display_name : $row->user_alias,
                'phone_number' => $row->phone_number,
                'send_time' => $row->send_time
            );
        }
        
        return $sends;
    }
}

