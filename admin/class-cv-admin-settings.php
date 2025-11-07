<?php
/**
 * Página de administración del plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class CV_Admin_Settings {
    
    /**
     * Configuración actual
     */
    private $config;
    
    /**
     * Constructor
     */
    public function __construct($config) {
        $this->config = $config;
    }
    
    /**
     * Renderizar página
     */
    public function render() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para acceder a esta página.');
        }
        
        // Guardar configuración si se envió el formulario
        if (isset($_POST['cv_commissions_save'])) {
            $this->save_settings();
        }
        
        // Obtener estado de dependencias
        $checker = new CV_Dependencies_Checker();
        $status = $checker->get_status();
        
        // Renderizar vista
        include CV_COMMISSIONS_PLUGIN_DIR . 'admin/views/settings.php';
    }
    
    /**
     * Guardar configuración
     */
    private function save_settings() {
        check_admin_referer('cv_commissions_settings');
        
        $new_config = array(
            // IDs de usuarios
            'programmer_user_id' => absint($_POST['programmer_user_id']),
            'programmer_affiliate_id' => absint($_POST['programmer_affiliate_id']),
            'company_user_id' => absint($_POST['company_user_id']),
            'company_affiliate_id' => absint($_POST['company_affiliate_id']),
            'company_name' => sanitize_text_field($_POST['company_name']),
            'company_contact_name' => sanitize_text_field($_POST['company_contact_name']),
            
            // Porcentajes
            'programmer_commission_percent' => floatval($_POST['programmer_commission_percent']),
            'buyer_commission_percent' => floatval($_POST['buyer_commission_percent']),
            'cashback_percent' => floatval($_POST['cashback_percent']),
            
            // MLM
            'mlm_levels' => absint($_POST['mlm_levels']),
            'mlm_level_percent' => floatval($_POST['mlm_level_percent']),
            'mlm_auto_registration_enabled' => isset($_POST['mlm_auto_registration_enabled']) ? true : false,
            
            // Producto especial
            'special_product_id' => absint($_POST['special_product_id']),
            'special_product_commission' => floatval($_POST['special_product_commission']),
            
            // Firebase
            'firebase_enabled' => isset($_POST['firebase_enabled']) ? true : false,
            'firebase_api_key' => sanitize_text_field($_POST['firebase_api_key']),
            'firebase_token_url' => esc_url_raw($_POST['firebase_token_url']),
            
            // Ultramsg (WhatsApp)
            'ultramsg_token' => sanitize_text_field($_POST['ultramsg_token'] ?? ''),
            'ultramsg_instance' => sanitize_text_field($_POST['ultramsg_instance'] ?? ''),
            'ultramsg_secondary_phone' => sanitize_text_field($_POST['ultramsg_secondary_phone'] ?? ''),
            'ultramsg_secondary_prefix' => sanitize_text_field($_POST['ultramsg_secondary_prefix'] ?? '📋 Copia de mensaje:'),
            'ultramsg_notify_enquiry' => isset($_POST['ultramsg_notify_enquiry']) ? true : false,
            'ultramsg_notify_commission' => isset($_POST['ultramsg_notify_commission']) ? true : false,
            
            // Productos destacados/anuncios
            'featured_products_enabled' => isset($_POST['featured_products_enabled']) ? true : false,
            
            // General
            'currency' => sanitize_text_field($_POST['currency']),
            'enable_logging' => isset($_POST['enable_logging']) ? true : false,
            'debug_mode' => isset($_POST['debug_mode']) ? true : false,
        );
        
        // Guardar opción de debug de tickets (separada)
        update_option('cv_ticket_debug_mode', isset($_POST['cv_ticket_debug_mode']) ? true : false);
        
        update_option('cv_commissions_config', $new_config);
        
        echo '<div class="notice notice-success"><p>¡Configuración guardada correctamente!</p></div>';
        
        // Actualizar config local
        $this->config = $new_config;
    }
}

