<?php
/**
 * Plugin Name: Ciudad Virtual - Estadísticas
 * Plugin URI: https://ciudadvirtual.app
 * Description: Panel de estadísticas avanzadas para el dashboard de WordPress (incluye rastreo de búsquedas con detección de país mejorada)
 * Version: 1.3.4
 * Author: Ciudad Virtual
 * Author URI: https://ciudadvirtual.app
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cv-stats
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// Si se accede directamente, salir
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('CV_STATS_VERSION', '1.3.4');
define('CV_STATS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CV_STATS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CV_STATS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Clase principal del plugin
 */
class CV_Stats {
    
    /**
     * Instancia única del plugin
     */
    private static $instance = null;
    
    /**
     * Obtener instancia única
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Cargar dependencias
     */
    private function load_dependencies() {
        // Widget de dashboard
        require_once CV_STATS_PLUGIN_DIR . 'includes/class-cv-stats-dashboard-widget.php';
        
        // Rastreador de logins
        require_once CV_STATS_PLUGIN_DIR . 'includes/class-cv-stats-login-tracker.php';
        
        // Rastreador de tarjetas
        require_once CV_STATS_PLUGIN_DIR . 'includes/class-cv-stats-card-tracker.php';
        
        // Rastreador de productos
        require_once CV_STATS_PLUGIN_DIR . 'includes/class-cv-stats-product-tracker.php';
        require_once CV_STATS_PLUGIN_DIR . 'includes/class-cv-stats-product-simple.php';
        
        // Rastreador de consultas de contacto
        require_once CV_STATS_PLUGIN_DIR . 'includes/class-cv-stats-contact-tracker.php';
        
        // Rastreador de referencias desde buscadores - DESACTIVADO (no interferir con WP Statistics)
        // require_once CV_STATS_PLUGIN_DIR . 'includes/class-cv-search-referral-tracker.php';
        
        // Reporte diario por WhatsApp
        require_once CV_STATS_PLUGIN_DIR . 'includes/class-cv-stats-daily-report.php';
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Inicializar rastreador de logins
        new CV_Stats_Login_Tracker();
        
        // Inicializar rastreador de tarjetas
        new CV_Stats_Card_Tracker();
        
        // Inicializar widget de dashboard
        new CV_Stats_Dashboard_Widget();
        
        // Añadir menú de administración
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Enqueue admin styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Activación del plugin
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Desactivación del plugin
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Añadir menú de administración
     */
    public function add_admin_menu() {
        add_menu_page(
            'CV Estadísticas',
            'Estadísticas',
            'manage_options',
            'cv-stats',
            array($this, 'render_stats_page'),
            'dashicons-chart-bar',
            58
        );
    }
    
    /**
     * Renderizar página de estadísticas
     */
    public function render_stats_page() {
        include CV_STATS_PLUGIN_DIR . 'admin/views/stats-page.php';
    }
    
    /**
     * Cargar assets de admin
     */
    public function enqueue_admin_assets($hook) {
        // Solo cargar en páginas de este plugin
        if (strpos($hook, 'cv-stats') === false && $hook !== 'index.php') {
            return;
        }
        
        wp_enqueue_style(
            'cv-stats-admin',
            CV_STATS_PLUGIN_URL . 'assets/css/admin-stats.css',
            array(),
            CV_STATS_VERSION
        );
        
        wp_enqueue_script(
            'cv-stats-admin',
            CV_STATS_PLUGIN_URL . 'assets/js/admin-stats.js',
            array('jquery'),
            CV_STATS_VERSION,
            true
        );
    }
    
    /**
     * Activar plugin
     */
    public function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Tabla para almacenar logins
        $table_logins = $wpdb->prefix . 'cv_user_logins';
        $sql_logins = "CREATE TABLE IF NOT EXISTS $table_logins (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            login_time datetime NOT NULL,
            ip_address varchar(100) DEFAULT '',
            user_agent text DEFAULT '',
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY login_time (login_time)
        ) $charset_collate;";
        dbDelta($sql_logins);
        
        // Tabla para vistas de tarjetas
        $table_card_views = $wpdb->prefix . 'cv_card_views';
        $sql_card_views = "CREATE TABLE IF NOT EXISTS $table_card_views (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            card_owner_id bigint(20) NOT NULL,
            visitor_id bigint(20) DEFAULT 0,
            view_time datetime NOT NULL,
            ip_address varchar(100) DEFAULT '',
            user_agent text DEFAULT '',
            PRIMARY KEY (id),
            KEY card_owner_id (card_owner_id),
            KEY view_time (view_time)
        ) $charset_collate;";
        dbDelta($sql_card_views);
        
        // Tabla para envíos de tarjetas por WhatsApp
        $table_whatsapp = $wpdb->prefix . 'cv_card_whatsapp_sends';
        $sql_whatsapp = "CREATE TABLE IF NOT EXISTS $table_whatsapp (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            card_owner_id bigint(20) NOT NULL,
            visitor_id bigint(20) DEFAULT 0,
            phone_number varchar(50) NOT NULL,
            send_time datetime NOT NULL,
            ip_address varchar(100) DEFAULT '',
            PRIMARY KEY (id),
            KEY card_owner_id (card_owner_id),
            KEY send_time (send_time)
        ) $charset_collate;";
        dbDelta($sql_whatsapp);
        
        // Tabla para actividades de productos
        $table_product_activities = $wpdb->prefix . 'cv_product_activities';
        $sql_product_activities = "CREATE TABLE IF NOT EXISTS $table_product_activities (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            vendor_id bigint(20) NOT NULL,
            activity_type varchar(20) NOT NULL,
            modified_by bigint(20) NOT NULL,
            activity_time datetime NOT NULL,
            ip_address varchar(100) DEFAULT '',
            user_agent text DEFAULT '',
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY vendor_id (vendor_id),
            KEY activity_type (activity_type),
            KEY activity_time (activity_time)
        ) $charset_collate;";
        dbDelta($sql_product_activities);
        
        error_log('✅ CV Stats: Plugin activado - Tablas creadas/actualizadas (logins, card_views, whatsapp_sends, product_activities)');
    }
    
    /**
     * Desactivar plugin
     */
    public function deactivate() {
        error_log('ℹ️ CV Stats: Plugin desactivado');
    }
}

// Inicializar plugin
CV_Stats::get_instance();

