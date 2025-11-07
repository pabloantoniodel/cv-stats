<?php
/**
 * Gestión de tickets para clientes en WooCommerce My Account
 */

if (!defined('ABSPATH')) {
    exit;
}

class CV_Customer_Tickets {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Agregar endpoint a My Account
        add_action('init', array($this, 'add_endpoints'));
        
        // Agregar item al menú de My Account
        add_filter('woocommerce_account_menu_items', array($this, 'add_menu_item'), 40);
        
        // Registrar contenido del endpoint
        add_action('woocommerce_account_mis-tickets_endpoint', array($this, 'render_tickets_page'));
        
        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // AJAX para obtener tickets
        add_action('wp_ajax_cv_get_customer_tickets', array($this, 'ajax_get_tickets'));
    }
    
    /**
     * Agregar endpoint a WooCommerce
     */
    public function add_endpoints() {
        add_rewrite_endpoint('mis-tickets', EP_ROOT | EP_PAGES);
    }
    
    /**
     * Agregar item al menú de My Account
     */
    public function add_menu_item($items) {
        // Insertar antes de 'customer-logout'
        $logout = $items['customer-logout'];
        unset($items['customer-logout']);
        
        $items['mis-tickets'] = __('Mis Tickets', 'cv-commissions');
        $items['customer-logout'] = $logout;
        
        return $items;
    }
    
    /**
     * Renderizar página de tickets del cliente
     */
    public function render_tickets_page() {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            echo '<p>' . __('Debes iniciar sesión para ver tus tickets.', 'cv-commissions') . '</p>';
            return;
        }
        
        // Obtener estadísticas
        $stats = $this->get_customer_stats($user_id);
        
        include CV_COMMISSIONS_PLUGIN_DIR . 'views/customer-tickets.php';
    }
    
    /**
     * Obtener estadísticas del cliente
     */
    public function get_customer_stats($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cv_tickets';
        
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE customer_id = %d",
            $user_id
        ));
        
        $pending = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE customer_id = %d AND status = 'pending'",
            $user_id
        ));
        
        $validated = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE customer_id = %d AND status = 'validated'",
            $user_id
        ));
        
        $total_amount = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM {$table_name} WHERE customer_id = %d AND status = 'validated'",
            $user_id
        ));
        
        return array(
            'total' => intval($total),
            'pending' => intval($pending),
            'validated' => intval($validated),
            'total_amount' => floatval($total_amount)
        );
    }
    
    /**
     * AJAX: Obtener tickets del cliente
     */
    public function ajax_get_tickets() {
        check_ajax_referer('cv_customer_tickets_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => 'No autorizado'));
            return;
        }
        
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        $tickets = $this->get_customer_tickets($user_id, $per_page, $offset);
        $total = $this->get_customer_total_tickets($user_id);
        
        wp_send_json_success(array(
            'tickets' => $tickets,
            'total' => $total,
            'pages' => ceil($total / $per_page),
            'current_page' => $page
        ));
    }
    
    /**
     * Obtener tickets del cliente
     */
    public function get_customer_tickets($user_id, $limit = 20, $offset = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cv_tickets';
        
        $query = $wpdb->prepare("
            SELECT *
            FROM {$table_name}
            WHERE customer_id = %d
            ORDER BY created_at DESC
            LIMIT %d OFFSET %d
        ", $user_id, $limit, $offset);
        
        $results = $wpdb->get_results($query);
        
        // Agregar URL de la foto y nombre del vendor a cada ticket
        foreach ($results as $ticket) {
            $ticket->photo_url = wp_get_attachment_url($ticket->ticket_photo_id);
            if (!$ticket->photo_url) {
                $ticket->photo_url = CV_COMMISSIONS_PLUGIN_URL . 'assets/images/no-image.png';
            }
            
            // Obtener nombre del comercio
            $vendor = get_userdata($ticket->vendor_id);
            if ($vendor) {
                $store_settings = get_user_meta($ticket->vendor_id, 'wcfmmp_profile_settings', true);
                $ticket->vendor_name = isset($store_settings['store_name']) ? $store_settings['store_name'] : $vendor->display_name;
            } else {
                $ticket->vendor_name = 'Comercio desconocido';
            }
            
            // Formatear fecha
            $ticket->formatted_date = date_i18n('d/m/Y H:i', strtotime($ticket->created_at));
        }
        
        return $results;
    }
    
    /**
     * Obtener total de tickets del cliente
     */
    public function get_customer_total_tickets($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cv_tickets';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE customer_id = %d",
            $user_id
        ));
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets() {
        // Cargar en toda la página My Account (simplificado)
        if (is_account_page()) {
            wp_enqueue_style(
                'cv-customer-tickets',
                CV_COMMISSIONS_PLUGIN_URL . 'assets/css/customer-tickets.css',
                array(),
                CV_COMMISSIONS_VERSION
            );
            
            wp_enqueue_script(
                'cv-customer-tickets',
                CV_COMMISSIONS_PLUGIN_URL . 'assets/js/customer-tickets.js',
                array('jquery'),
                CV_COMMISSIONS_VERSION,
                true
            );
            
            wp_localize_script('cv-customer-tickets', 'cvCustomerTickets', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cv_customer_tickets_nonce'),
                'debug_mode' => get_option('cv_ticket_debug_mode', false) ? true : false
            ));
        }
    }
}

// Inicializar
new CV_Customer_Tickets();

