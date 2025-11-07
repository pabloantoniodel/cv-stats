<?php
/**
 * CV Search Referral Tracker
 * 
 * Rastrea visitas desde motores de búsqueda (Google, Bing, etc.)
 * y guarda los términos de búsqueda utilizados
 * 
 * @package CV_Stats
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CV_Search_Referral_Tracker {
    
    private $table_name;
    private $search_engines = array(
        'google'      => array('Google', 'q'),
        'bing'        => array('Bing', 'q'),
        'yahoo'       => array('Yahoo', 'p'),
        'duckduckgo'  => array('DuckDuckGo', 'q'),
        'yandex'      => array('Yandex', 'text'),
        'baidu'       => array('Baidu', 'wd'),
        'ecosia'      => array('Ecosia', 'q'),
        'ask'         => array('Ask', 'q'),
        'aol'         => array('AOL', 'q'),
    );
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'cv_search_referrals';
        
        // Crear tabla al activar
        register_activation_hook(CV_STATS_PLUGIN_BASENAME, array($this, 'create_table'));
        
        // Rastrear visitas solo en frontend
        // Prioridad 999 para ejecutar DESPUÉS de WP Statistics y otros trackers
        if (!is_admin()) {
            add_action('wp', array($this, 'track_search_referral'), 999);
        }
    }
    
    /**
     * Crear tabla en la base de datos
     */
    public function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            search_engine varchar(50) NOT NULL,
            search_terms varchar(500) DEFAULT NULL,
            landing_page varchar(500) NOT NULL,
            user_ip varchar(45) NOT NULL,
            country_code varchar(2) DEFAULT 'XX',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY search_engine (search_engine),
            KEY created_at (created_at),
            KEY country_code (country_code)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Rastrear visita desde buscador
     */
    public function track_search_referral() {
        // Obtener referrer
        $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        
        if (empty($referrer)) {
            return;
        }
        
        // Detectar buscador
        $search_engine_data = $this->detect_search_engine($referrer);
        
        if (!$search_engine_data) {
            return; // No es un buscador
        }
        
        // Extraer términos de búsqueda
        $search_terms = $this->extract_search_terms($referrer, $search_engine_data['param']);
        
        // Obtener datos de la visita
        $data = array(
            'search_engine' => $search_engine_data['name'],
            'search_terms'  => !empty($search_terms) ? substr($search_terms, 0, 500) : null,
            'landing_page'  => substr($_SERVER['REQUEST_URI'], 0, 500),
            'user_ip'       => $this->get_user_ip(),
            'country_code'  => $this->get_user_country(),
        );
        
        // Guardar en BD
        $this->save_referral($data);
    }
    
    /**
     * Guardar referral en la base de datos
     */
    private function save_referral($data) {
        global $wpdb;
        
        $wpdb->insert(
            $this->table_name,
            $data,
            array('%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Detectar motor de búsqueda
     */
    private function detect_search_engine($referrer) {
        foreach ($this->search_engines as $domain => $data) {
            if (stripos($referrer, $domain) !== false) {
                return array(
                    'name'  => $data[0],
                    'param' => $data[1]
                );
            }
        }
        return false;
    }
    
    /**
     * Extraer términos de búsqueda
     */
    private function extract_search_terms($referrer, $param) {
        $parsed = parse_url($referrer);
        
        if (!isset($parsed['query'])) {
            return '';
        }
        
        parse_str($parsed['query'], $query_params);
        
        if (isset($query_params[$param])) {
            return urldecode($query_params[$param]);
        }
        
        return '';
    }
    
    /**
     * Obtener IP del usuario
     */
    private function get_user_ip() {
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                return substr($ip, 0, 45);
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Obtener país del usuario (múltiples métodos)
     */
    private function get_user_country() {
        $ip = $this->get_user_ip();
        
        // Si la IP es local/privada, devolver ES (España) por defecto
        if ($ip === 'unknown' || $this->is_private_ip($ip)) {
            return 'ES';
        }
        
        // MÉTODO 1: Usar API de ip-api.com (rápido y confiable)
        $country = $this->get_country_from_api($ip);
        if ($country && $country !== 'XX') {
            return $country;
        }
        
        // MÉTODO 2: Usar IP2Location Country Blocker DB (si está instalado)
        if (class_exists('IP2LocationCountryBlocker')) {
            global $wpdb;
            $ip2location_db = $wpdb->prefix . 'ip2location_country_blocker';
            
            // Verificar si la tabla existe
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$ip2location_db}'");
            
            if ($table_exists) {
                // Consultar la base de datos de IP2Location directamente
                $ip_number = $this->ip_to_number($ip);
                
                if ($ip_number !== null) {
                    $result = $wpdb->get_row($wpdb->prepare(
                        "SELECT country_code FROM {$ip2location_db} 
                        WHERE ip_from <= %s AND ip_to >= %s 
                        LIMIT 1",
                        $ip_number,
                        $ip_number
                    ));
                    
                    if ($result && !empty($result->country_code)) {
                        return $result->country_code;
                    }
                }
            }
        }
        
        // MÉTODO 3: Usar WP Statistics (si está instalado)
        if (function_exists('ip2location_get_country_short')) {
            $country = ip2location_get_country_short($ip);
            if ($country && $country !== '-') {
                return $country;
            }
        }
        
        return 'XX'; // Desconocido
    }
    
    /**
     * Convertir IP a número para consulta en base de datos
     */
    private function ip_to_number($ip) {
        // Solo soportamos IPv4 por ahora
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return sprintf('%u', ip2long($ip));
        }
        
        // Para IPv6, usar API de fallback
        return null;
    }
    
    /**
     * Obtener país desde API externa (fallback)
     */
    private function get_country_from_api($ip) {
        // Solo usar esto si es una IP pública
        if ($ip === 'unknown' || $this->is_private_ip($ip)) {
            return null;
        }
        
        // Usar ip-api.com (gratis, 45 req/min)
        $response = wp_remote_get("http://ip-api.com/json/{$ip}?fields=countryCode", array(
            'timeout' => 2,
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['countryCode']) && !empty($data['countryCode'])) {
            return $data['countryCode'];
        }
        
        return null;
    }
    
    /**
     * Verificar si la IP es privada/local
     */
    private function is_private_ip($ip) {
        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
    
    /**
     * Obtener total de visitas desde buscadores (por rango de fechas)
     */
    public function get_total_referrals_by_date_range($date_start, $date_end) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
            WHERE created_at >= %s AND created_at <= %s",
            $date_start,
            $date_end
        ));
    }
    
    /**
     * Obtener estadísticas por buscador (por rango de fechas)
     */
    public function get_stats_by_search_engine_date_range($date_start, $date_end) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                search_engine,
                COUNT(*) as total_visits,
                COUNT(DISTINCT search_terms) as unique_terms
            FROM {$this->table_name}
            WHERE created_at >= %s AND created_at <= %s
            GROUP BY search_engine
            ORDER BY total_visits DESC",
            $date_start,
            $date_end
        ));
    }
    
    /**
     * Obtener top términos de búsqueda (por rango de fechas)
     */
    public function get_top_search_terms_date_range($date_start, $date_end, $limit = 20) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                search_terms,
                search_engine,
                COUNT(*) as visits,
                MAX(created_at) as last_visit
            FROM {$this->table_name}
            WHERE created_at >= %s AND created_at <= %s AND search_terms IS NOT NULL
            GROUP BY search_terms, search_engine
            ORDER BY visits DESC
            LIMIT %d",
            $date_start,
            $date_end,
            $limit
        ));
    }
    
    /**
     * Obtener visitas recientes (por rango de fechas)
     */
    public function get_recent_referrals_date_range($date_start, $date_end, $limit = 10) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                search_engine,
                search_terms,
                landing_page,
                country_code,
                created_at
            FROM {$this->table_name}
            WHERE created_at >= %s AND created_at <= %s
            ORDER BY created_at DESC
            LIMIT %d",
            $date_start,
            $date_end,
            $limit
        ));
    }
    
    /**
     * Obtener estadísticas por país (por rango de fechas)
     */
    public function get_stats_by_country_date_range($date_start, $date_end) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                country_code,
                COUNT(*) as total_visits
            FROM {$this->table_name}
            WHERE created_at >= %s AND created_at <= %s
            GROUP BY country_code
            ORDER BY total_visits DESC
            LIMIT 10",
            $date_start,
            $date_end
        ));
    }
}

// Inicializar tracker
new CV_Search_Referral_Tracker();

