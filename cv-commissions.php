<?php
/**
 * Plugin Name: Ciudad Virtual - Sistema de Comisiones MLM
 * Plugin URI: https://ciudadvirtual.app
 * Description: Sistema completo de comisiones multinivel para marketplace con integración WooCommerce, WCFM y Ultimate Affiliate Pro
 * Version: 1.3.0
 * Author: Ciudad Virtual
 * Author URI: https://ciudadvirtual.app
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cv-commissions
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * 
 * Changelog:
 * 1.2.6 - Hook post-meta para actualizar padre MLM después de guardar user_registration_referido
 * 1.2.5 - Priorizar user_registration_referido como padre MLM sobre cookie/UAP
 * 1.2.4 - Protección contra auto-referencias en MLM (un afiliado no puede ser su propio padre)
 * 1.2.3 - Auto registro de usuarios en sistema MLM (Snippets 28 y 31 integrados)
 * 1.2.2 - Navegación jerárquica por niveles en Mi Red con avatares y badge de afiliados
 * 1.2.1 - Endpoint "Mi Red" mejorado: Sponsor con avatar + eliminadas secciones obsoletas (Snippet 26)
 * 1.2.0 - Nuevo sistema de captura de tickets con QR, cámara y formulario de envío
 * 1.1.2 - Badge de economía colaborativa en productos (Snippet 35 punto 4 integrado)
 * 1.0.5 - Paginación 10 en 10, separación Wallet WC vs UAP, info box explicativo
 * 1.0.4 - Modal de pedidos, columnas adicionales (Total, %), integración WooCommerce My Account
 * 1.0.3 - Añadido dashboard de comisiones para vendedores (integración WCFM)
 * 1.0.2 - Añadida integración con WooCommerce Wallet (Snippet 36: Calculo monedero a CV)
 * 1.0.1 - Añadido filtro de productos internos (oculta ticket-de-compra del catálogo)
 * 1.0.0 - Versión inicial
 */

// Si se accede directamente, salir
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('CV_COMMISSIONS_VERSION', '1.3.0');
define('CV_COMMISSIONS_FILE', __FILE__);
define('CV_COMMISSIONS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CV_COMMISSIONS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CV_COMMISSIONS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Clase principal del plugin
 */
class CV_Commissions {
    
    /**
     * Instancia única del plugin
     */
    private static $instance = null;
    
    /**
     * Configuración del plugin
     */
    private $config = array();
    
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
        $this->load_config();
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Cargar configuración
     */
    private function load_config() {
        // Cargar configuración por defecto
        require_once CV_COMMISSIONS_PLUGIN_DIR . 'config/default-config.php';
        
        // Obtener configuración guardada
        $saved_config = get_option('cv_commissions_config', array());
        
        // Mezclar con valores por defecto
        $this->config = wp_parse_args($saved_config, cv_commissions_default_config());
    }
    
    /**
     * Cargar dependencias
     */
    private function load_dependencies() {
        // Verificador de dependencias
        require_once CV_COMMISSIONS_PLUGIN_DIR . 'includes/class-cv-dependencies-checker.php';
        
        // Clases principales
        require_once CV_COMMISSIONS_PLUGIN_DIR . 'includes/class-cv-commission-calculator.php';
        require_once CV_COMMISSIONS_PLUGIN_DIR . 'includes/class-cv-mlm-pyramid.php';
        require_once CV_COMMISSIONS_PLUGIN_DIR . 'includes/class-cv-mlm-children.php'; // Gestor de descendientes MLM
        require_once CV_COMMISSIONS_PLUGIN_DIR . 'includes/class-cv-firebase-notifier.php';
        require_once CV_COMMISSIONS_PLUGIN_DIR . 'includes/class-cv-commission-distributor.php';
        require_once CV_COMMISSIONS_PLUGIN_DIR . 'includes/class-cv-mlm-auto-registration.php';
        require_once CV_COMMISSIONS_PLUGIN_DIR . 'includes/class-cv-product-filters.php'; // Filtro de productos internos
        require_once CV_COMMISSIONS_PLUGIN_DIR . 'includes/class-cv-wallet-integration.php'; // Integración con WooCommerce Wallet
        require_once CV_COMMISSIONS_PLUGIN_DIR . 'includes/class-cv-economy-badge.php'; // Badge de economía colaborativa
        require_once CV_COMMISSIONS_PLUGIN_DIR . 'includes/class-cv-commissions-dashboard.php'; // Dashboard de comisiones para vendedores
        require_once CV_COMMISSIONS_PLUGIN_DIR . 'includes/class-cv-ultramsg.php'; // Integración con Ultramsg (WhatsApp)
        require_once CV_COMMISSIONS_PLUGIN_DIR . 'includes/class-cv-ticket-capture.php'; // Sistema de captura de tickets
        require_once CV_COMMISSIONS_PLUGIN_DIR . 'includes/class-cv-tickets-dashboard.php'; // Dashboard de tickets para vendedores
        require_once CV_COMMISSIONS_PLUGIN_DIR . 'includes/class-cv-customer-tickets.php'; // Mis Tickets para clientes
        require_once CV_COMMISSIONS_PLUGIN_DIR . 'includes/class-cv-push-notifications.php'; // Notificaciones push para vendedores
        require_once CV_COMMISSIONS_PLUGIN_DIR . 'includes/class-cv-firebase-push.php'; // Firebase Cloud Messaging (notificaciones persistentes)
        require_once CV_COMMISSIONS_PLUGIN_DIR . 'includes/class-cv-affiliate-mlm.php'; // Sistema MLM de afiliados (migrado desde snippet 48)
        require_once CV_COMMISSIONS_PLUGIN_DIR . 'includes/class-cv-featured-products.php'; // Productos destacados/anuncios (migrado desde snippet 33)
        require_once CV_COMMISSIONS_PLUGIN_DIR . 'includes/class-cv-my-network-endpoint.php'; // Endpoint "Mi Red" (Snippet 26)
        
        // Funciones de compatibilidad para otros snippets
        require_once CV_COMMISSIONS_PLUGIN_DIR . 'includes/compatibility-functions.php';
        
        // Admin
        if (is_admin()) {
            require_once CV_COMMISSIONS_PLUGIN_DIR . 'admin/class-cv-admin-settings.php';
        }
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Activación del plugin
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Desactivación del plugin
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Hook principal - procesar comisiones cuando se procesa un pedido
        add_action('wcfmmp_order_processed', array($this, 'process_order_commissions'), 10, 1);
        
        // Hook secundario - calcular cashback del carrito
        add_filter('woo_wallet_form_cart_cashback_amount', array($this, 'calculate_cart_cashback'), 10, 1);
        
        // Inicializar auto-registro MLM (Snippet 23 integrado)
        new CV_MLM_Auto_Registration($this->config);
        
        // Inicializar integración con WooCommerce Wallet (Snippet 36 integrado)
        new CV_Wallet_Integration();
        
        // Inicializar dashboard de comisiones para vendedores
        if (class_exists('WCFM')) {
            new CV_Commissions_Dashboard();
            new CV_Tickets_Dashboard();
        }
        
        // Inicializar integración con Ultramsg (WhatsApp)
        new CV_Ultramsg();
        
        // Inicializar badge de economía colaborativa (Snippet 35 integrado)
        new CV_Economy_Badge();
        
        // Admin
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'register_settings'));
        }
    }
    
    /**
     * Activar plugin
     */
    public function activate() {
        // Verificar dependencias
        $checker = new CV_Dependencies_Checker();
        
        if (!$checker->check_all_dependencies()) {
            $missing = $checker->get_missing_dependencies();
            
            deactivate_plugins(CV_COMMISSIONS_PLUGIN_BASENAME);
            
            wp_die(
                '<h1>Error al activar CV Commissions</h1>' .
                '<p>Este plugin requiere los siguientes plugins activos:</p>' .
                '<ul><li>' . implode('</li><li>', $missing) . '</li></ul>' .
                '<p><a href="' . admin_url('plugins.php') . '">Volver a plugins</a></p>'
            );
        }
        
        // Guardar configuración por defecto si no existe
        if (!get_option('cv_commissions_config')) {
            update_option('cv_commissions_config', cv_commissions_default_config());
        }
        
        error_log('✅ CV Commissions: Plugin activado correctamente');
    }
    
    /**
     * Desactivar plugin
     */
    public function deactivate() {
        error_log('ℹ️ CV Commissions: Plugin desactivado');
    }
    
    /**
     * Procesar comisiones de un pedido
     * FUNCIÓN PRINCIPAL - Se ejecuta en hook wcfmmp_order_processed
     */
    public function process_order_commissions($order_id) {
        error_log('🎯 CV Commissions: Procesando comisiones para pedido #' . $order_id);
        
        try {
            // Inicializar distribuidor
            $distributor = new CV_Commission_Distributor($this->config);
            
            // Procesar comisiones
            $result = $distributor->process_order($order_id);
            
            if ($result) {
                error_log('✅ CV Commissions: Comisiones procesadas correctamente para pedido #' . $order_id);
            } else {
                error_log('❌ CV Commissions: Error al procesar comisiones para pedido #' . $order_id);
            }
            
        } catch (Exception $e) {
            error_log('❌ CV Commissions: Excepción al procesar pedido #' . $order_id . ': ' . $e->getMessage());
        }
    }
    
    /**
     * Calcular cashback del carrito
     */
    public function calculate_cart_cashback($amount) {
        $calculator = new CV_Commission_Calculator($this->config);
        return $calculator->calculate_total_commissions(true, 0);
    }
    
    /**
     * Añadir menú de administración
     */
    public function add_admin_menu() {
        add_menu_page(
            'CV Comisiones',
            'CV Comisiones',
            'manage_options',
            'cv-commissions',
            array($this, 'render_admin_page'),
            'dashicons-money-alt',
            56
        );
    }
    
    /**
     * Renderizar página de administración
     */
    public function render_admin_page() {
        $admin = new CV_Admin_Settings($this->config);
        $admin->render();
    }
    
    /**
     * Registrar settings
     */
    public function register_settings() {
        register_setting('cv_commissions_settings', 'cv_commissions_config');
    }
    
    /**
     * Obtener configuración
     */
    public function get_config($key = null) {
        if ($key === null) {
            return $this->config;
        }
        
        return isset($this->config[$key]) ? $this->config[$key] : null;
    }
}

/**
 * Inicializar el plugin
 */
function cv_commissions_init() {
    return CV_Commissions::get_instance();
}

// Iniciar el plugin
add_action('plugins_loaded', 'cv_commissions_init');

