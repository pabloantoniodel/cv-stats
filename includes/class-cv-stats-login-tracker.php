<?php
/**
 * Rastreador de logins de usuarios
 * Registra cada vez que un usuario inicia sesión
 *
 * @package CV_Stats
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CV_Stats_Login_Tracker {

    /**
     * Evitar recrear la tabla en cada petición
     *
     * @var bool
     */
    private static $sessions_table_ready = false;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Hook cuando un usuario inicia sesión
        add_action('wp_login', array($this, 'track_login'), 10, 2);

        // Registrar actividad continua de usuarios ya logueados (frontend y admin)
        add_action('init', array($this, 'track_activity'));
    }
    
    /**
     * Registrar login del usuario
     */
    public function track_login($user_login, $user) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cv_user_logins';
        
        // Obtener datos del login
        $user_id = $user->ID;
        $login_time = current_time('mysql');
        $ip_address = $this->get_user_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        
        // Insertar registro
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'login_time' => $login_time,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent
            ),
            array('%d', '%s', '%s', '%s')
        );
        
        error_log('📊 CV Stats: Login registrado - User: ' . $user_login . ' (ID: ' . $user_id . ')');
    }

    /**
     * Registrar actividad del usuario logueado para saber si está navegando
     */
    public function track_activity() {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        $now = time();
        $last_ping = (int) get_user_meta($user_id, 'cv_stats_last_ping', true);

        // Evitar escrituras constantes: actualizar como máximo una vez por minuto
        if ($last_ping && ($now - $last_ping) < 60) {
            return;
        }

        update_user_meta($user_id, 'cv_stats_last_ping', $now);

        global $wpdb;
        $table_name = $wpdb->prefix . 'cv_user_sessions';

        $data = array(
            'user_id'    => $user_id,
            'last_seen'  => current_time('mysql'),
            'ip_address' => $this->get_user_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
            'current_url'=> $this->get_current_url(),
        );

        $formats = array('%d', '%s', '%s', '%s', '%s');

        $this->ensure_sessions_table();
        $wpdb->replace($table_name, $data, $formats);
    }
    
    /**
     * Obtener IP del usuario
     */
    private function get_user_ip() {
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        );

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $parts = explode(',', $ip);
                    $ip = trim($parts[0]);
                }
                return sanitize_text_field($ip);
            }
        }

        return '0.0.0.0';
    }

    /**
     * Obtener URL actual (limitada)
     */
    private function get_current_url() {
        $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $scheme = is_ssl() ? 'https://' : 'http://';

        $url = $scheme . $host . $request_uri;
        return substr($url, 0, 500);
    }
    
    /**
     * Obtener usuarios que se loguearon hoy
     */
    public static function get_todays_logins() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cv_user_logins';
        
        $today_start = date('Y-m-d 00:00:00');
        $today_end = date('Y-m-d 23:59:59');
        
        // Subconsulta para obtener el último login de cada usuario
        $query = $wpdb->prepare("
            SELECT 
                l.user_id, 
                l.login_time as last_login,
                l.ip_address,
                l.user_agent
            FROM {$table_name} l
            INNER JOIN (
                SELECT user_id, MAX(login_time) as max_login
                FROM {$table_name}
                WHERE login_time >= %s AND login_time <= %s
                GROUP BY user_id
            ) latest ON l.user_id = latest.user_id AND l.login_time = latest.max_login
            ORDER BY l.login_time DESC
        ", $today_start, $today_end);
        
        $results = $wpdb->get_results($query);
        
        // Enriquecer con datos de usuario
        $logins = array();
        foreach ($results as $row) {
            $user = get_userdata($row->user_id);
            if ($user) {
                $logins[] = array(
                    'user_id' => $row->user_id,
                    'username' => $user->user_login,
                    'display_name' => $user->display_name,
                    'email' => $user->user_email,
                    'roles' => $user->roles,
                    'last_login' => $row->last_login,
                    'ip_address' => $row->ip_address,
                    'user_agent' => $row->user_agent
                );
            }
        }
        
        return $logins;
    }
    
    /**
     * Obtener cantidad de logins de hoy
     */
    public static function get_todays_login_count() {
        $logins = self::get_todays_logins();
        return count($logins);
    }
    
    /**
     * Obtener usuarios que se loguearon en un rango de fechas
     */
    public static function get_logins_by_date_range($start_date, $end_date) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cv_user_logins';
        
        // Subconsulta para obtener el último login de cada usuario
        $query = $wpdb->prepare("
            SELECT 
                l.user_id, 
                l.login_time as last_login,
                l.ip_address,
                l.user_agent
            FROM {$table_name} l
            INNER JOIN (
                SELECT user_id, MAX(login_time) as max_login
                FROM {$table_name}
                WHERE login_time >= %s AND login_time <= %s
                GROUP BY user_id
            ) latest ON l.user_id = latest.user_id AND l.login_time = latest.max_login
            ORDER BY l.login_time DESC
        ", $start_date, $end_date);
        
        $results = $wpdb->get_results($query);
        
        // Enriquecer con datos de usuario
        $logins = array();
        foreach ($results as $row) {
            $user = get_userdata($row->user_id);
            if ($user) {
                $logins[] = array(
                    'user_id' => $row->user_id,
                    'username' => $user->user_login,
                    'display_name' => $user->display_name,
                    'email' => $user->user_email,
                    'roles' => $user->roles,
                    'last_login' => $row->last_login,
                    'ip_address' => $row->ip_address,
                    'user_agent' => $row->user_agent
                );
            }
        }
        
        return $logins;
    }

    /**
     * Obtener sesiones activas (usuarios navegando recientemente)
     *
     * @param int $minutes Ventana de tiempo en minutos para considerar activo
     * @return array
     */
    public static function get_active_sessions($minutes = 10) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cv_user_sessions';
        $threshold = date('Y-m-d H:i:s', current_time('timestamp') - ($minutes * 60));

        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE last_seen >= %s ORDER BY last_seen DESC",
            $threshold
        ));

        $active = array();

        foreach ($sessions as $session) {
            $user = get_userdata($session->user_id);
            if (!$user) {
                continue;
            }

            $active[] = array(
                'user_id'    => (int) $session->user_id,
                'username'   => $user->user_login,
                'display_name'=> $user->display_name,
                'email'      => $user->user_email,
                'roles'      => $user->roles,
                'last_seen'  => $session->last_seen,
                'ip_address' => $session->ip_address,
                'current_url'=> $session->current_url,
                'user_agent' => $session->user_agent,
            );
        }

        return $active;
    }

    /**
     * Obtener sesiones registradas desde un momento exacto (ej. todo el día actual)
     *
     * @param string $since Fecha/hora en formato Y-m-d H:i:s
     * @return array
     */
    public static function get_sessions_since($since) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cv_user_sessions';
        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE last_seen >= %s ORDER BY last_seen DESC",
            $since
        ));

        $list = array();
        foreach ($sessions as $session) {
            $user = get_userdata($session->user_id);
            if (!$user) {
                continue;
            }
            $list[] = array(
                'user_id'     => (int) $session->user_id,
                'username'    => $user->user_login,
                'display_name'=> $user->display_name,
                'email'       => $user->user_email,
                'roles'       => $user->roles,
                'last_seen'   => $session->last_seen,
                'ip_address'  => $session->ip_address,
                'current_url' => $session->current_url,
                'user_agent'  => $session->user_agent,
            );
        }

        return $list;
    }

    /**
     * Asegura que la tabla de sesiones exista (para instalaciones previas)
     */
    private function ensure_sessions_table() {
        if (self::$sessions_table_ready) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'cv_user_sessions';

        $table_exists = $wpdb->get_var($wpdb->prepare(
            'SHOW TABLES LIKE %s',
            $table_name
        ));

        if (!$table_exists) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$table_name} (
                user_id bigint(20) NOT NULL,
                last_seen datetime NOT NULL,
                ip_address varchar(100) DEFAULT '',
                user_agent text DEFAULT '',
                current_url varchar(500) DEFAULT '',
                PRIMARY KEY (user_id),
                KEY last_seen (last_seen)
            ) {$charset_collate};";
            dbDelta($sql);
        }

        self::$sessions_table_ready = true;
    }
}

