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
     * Constructor
     */
    public function __construct() {
        // Hook cuando un usuario inicia sesión
        add_action('wp_login', array($this, 'track_login'), 10, 2);
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
}

