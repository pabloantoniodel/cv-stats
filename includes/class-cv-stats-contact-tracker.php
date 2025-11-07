<?php
/**
 * Rastreador de Consultas de Contacto
 * 
 * Registra todas las consultas enviadas desde el formulario de contacto
 * 
 * @package CV_Stats
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CV_Stats_Contact_Tracker {
    
    /**
     * Nombre de la tabla
     */
    const TABLE_NAME = 'cv_stats_contact_queries';
    
    /**
     * Constructor
     */
    public function __construct() {
        // Hook para registrar consultas
        add_action('cv_contact_form_sent', array($this, 'register_contact_query'), 10, 2);
        
        // Crear tabla en activación
        register_activation_hook(CV_STATS_PLUGIN_DIR . 'cv-stats.php', array($this, 'create_table'));
    }
    
    /**
     * Crear tabla de consultas de contacto
     */
    public function create_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            category varchar(50) NOT NULL,
            name varchar(200) NOT NULL,
            email varchar(200) NOT NULL,
            phone varchar(50) DEFAULT NULL,
            subject varchar(500) NOT NULL,
            message text NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(100) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY category (category),
            KEY email (email),
            KEY created_at (created_at),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        error_log('✅ CV Stats Contact: Tabla creada/actualizada');
    }
    
    /**
     * Registrar consulta de contacto
     * 
     * @param string $category Categoría de la consulta
     * @param array $data Datos del formulario
     */
    public function register_contact_query($category, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        
        // Obtener información adicional
        $user_id = get_current_user_id();
        $ip_address = $this->get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        
        // Insertar en base de datos
        $result = $wpdb->insert(
            $table_name,
            array(
                'category' => $category,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => isset($data['phone']) ? $data['phone'] : null,
                'subject' => $data['subject'],
                'message' => $data['message'],
                'user_id' => $user_id > 0 ? $user_id : null,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );
        
        if ($result) {
            error_log('✅ CV Stats Contact: Consulta registrada - ID: ' . $wpdb->insert_id . ' - Categoría: ' . $category . ' - Email: ' . $data['email']);
        } else {
            error_log('❌ CV Stats Contact: Error registrando consulta - ' . $wpdb->last_error);
        }
    }
    
    /**
     * Obtener IP del cliente
     * 
     * @return string IP del cliente
     */
    private function get_client_ip() {
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
     * Obtener estadísticas de consultas
     * 
     * @param int $days Número de días a consultar
     * @return array Estadísticas
     */
    public static function get_stats($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Total de consultas
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE created_at >= %s",
            $date_from
        ));
        
        // Por categoría
        $by_category = $wpdb->get_results($wpdb->prepare(
            "SELECT category, COUNT(*) as count 
            FROM $table_name 
            WHERE created_at >= %s 
            GROUP BY category 
            ORDER BY count DESC",
            $date_from
        ), ARRAY_A);
        
        // Últimas consultas
        $recent = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE created_at >= %s 
            ORDER BY created_at DESC 
            LIMIT 10",
            $date_from
        ), ARRAY_A);
        
        return array(
            'total' => $total,
            'by_category' => $by_category,
            'recent' => $recent
        );
    }
}

// Inicializar
new CV_Stats_Contact_Tracker();

