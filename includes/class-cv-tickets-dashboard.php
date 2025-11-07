    <?php
/**
 * Dashboard de Tickets para Vendedores
 * 
 * Muestra el historial de tickets capturados y permite su validación
 * Integración con WCFM panel de vendedor
 * 
 * @package CV_Commissions
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase CV_Tickets_Dashboard
 * 
 * Integra un dashboard de tickets en el panel de vendedor de WCFM
 * mostrando:
 * - Historial de tickets capturados
 * - Estado de cada ticket (pendiente/validado)
 * - Estadísticas generales
 * - Funcionalidad de validación
 */
class CV_Tickets_Dashboard {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks de WordPress/WCFM
     */
    private function init_hooks() {
        // Agregar query vars para el endpoint
        add_filter('wcfm_query_vars', array($this, 'add_query_vars'), 10);
        
        // Agregar título del endpoint
        add_filter('wcfm_endpoint_title', array($this, 'endpoint_title'), 10, 2);
        
        // Inicializar endpoint
        add_action('init', array($this, 'init_endpoint'), 120);
        
        // Agregar slug del endpoint
        add_filter('wcfm_endpoints_slug', array($this, 'endpoints_slug'));
        
        // Agregar menú al panel WCFM
        add_filter('wcfm_menus', array($this, 'add_menu'), 40);
        
        // Cargar vista
        add_action('wcfm_load_views', array($this, 'load_views'), 10);
        
        // Cargar estilos
        add_action('wcfm_load_styles', array($this, 'load_styles'), 10);
        
        // Cargar scripts
        add_action('wcfm_load_scripts', array($this, 'load_scripts'), 10);
        
        // Ajax handler para validar tickets
        add_action('wp_ajax_cv_validate_ticket', array($this, 'ajax_validate_ticket'));
        
        // Ajax handler para obtener tickets
        add_action('wp_ajax_cv_get_tickets', array($this, 'ajax_get_tickets'));
    }
    
    /**
     * Agregar query vars
     */
    public function add_query_vars($query_vars) {
        $wcfm_modified_endpoints = wcfm_get_option('wcfm_endpoints', array());
        
        $cv_query_vars = array(
            'cv-tickets' => !empty($wcfm_modified_endpoints['cv-tickets']) 
                ? $wcfm_modified_endpoints['cv-tickets'] 
                : 'cv-tickets',
        );
        
        $query_vars = array_merge($query_vars, $cv_query_vars);
        
        return $query_vars;
    }
    
    /**
     * Título del endpoint
     */
    public function endpoint_title($title, $endpoint) {
        if ($endpoint === 'cv-tickets') {
            $title = __('Mis Tickets', 'cv-commissions');
        }
        
        return $title;
    }
    
    /**
     * Inicializar endpoint
     */
    public function init_endpoint() {
        global $WCFM_Query;
        
        // Inicializar endpoints de WCFM
        if ($WCFM_Query) {
            $WCFM_Query->init_query_vars();
            $WCFM_Query->add_endpoints();
        }
        
        if (!get_option('cv_tickets_endpoint_added')) {
            // Flush rewrite rules
            flush_rewrite_rules();
            update_option('cv_tickets_endpoint_added', 1);
        }
    }
    
    /**
     * Slug del endpoint
     */
    public function endpoints_slug($endpoints) {
        $cv_endpoints = array(
            'cv-tickets' => 'cv-tickets',
        );
        
        $endpoints = array_merge($endpoints, $cv_endpoints);
        
        return $endpoints;
    }
    
    /**
     * Agregar menú al panel WCFM
     */
    public function add_menu($menus) {
        // Solo mostrar para vendedores
        if (!wcfm_is_vendor()) {
            return $menus;
        }
        
        // Agregar después de "Mis Comisiones CV"
        $menus['cv-tickets'] = array(
            'label'    => __('Mis Tickets', 'cv-commissions'),
            'url'      => wcfm_get_endpoint_url('cv-tickets'),
            'icon'     => 'ticket-alt',
            'menu_for' => 'vendor',
            'priority' => 40
        );
        
        return $menus;
    }
    
    /**
     * Cargar vista
     */
    public function load_views($end_point) {
        if ($end_point == 'cv-tickets') {
            // Cargar directamente desde nuestro plugin
            $template_path = CV_COMMISSIONS_PLUGIN_DIR . 'views/tickets-dashboard.php';
            if (file_exists($template_path)) {
                include $template_path;
            } else {
                echo '<p>Error: Template no encontrado en ' . esc_html($template_path) . '</p>';
            }
        }
    }
    
    /**
     * Cargar estilos
     */
    public function load_styles($end_point) {
        // Cargar en dashboard de tickets
        if ($end_point == 'cv-tickets') {
            wp_enqueue_style('cv-tickets-dashboard', 
                CV_COMMISSIONS_PLUGIN_URL . 'assets/css/tickets-dashboard.css',
                array(), 
                CV_COMMISSIONS_VERSION
            );
        }
    }
    
    /**
     * Cargar scripts
     */
    public function load_scripts($end_point) {
        if ($end_point == 'cv-tickets') {
            wp_enqueue_script('cv-tickets-dashboard',
                CV_COMMISSIONS_PLUGIN_URL . 'assets/js/tickets-dashboard.js',
                array('jquery'),
                CV_COMMISSIONS_VERSION,
                true
            );
            
            // Localizar script
            wp_localize_script('cv-tickets-dashboard', 'cvTicketsData', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cv_tickets_dashboard'),
            ));
        }
    }
    
    /**
     * Obtener tickets del vendedor
     */
    public function get_vendor_tickets($vendor_id, $limit = 50, $offset = 0) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cv_tickets';
        
        $query = $wpdb->prepare("
            SELECT *
            FROM {$table_name}
            WHERE vendor_id = %d
            ORDER BY created_at DESC
            LIMIT %d OFFSET %d
        ", $vendor_id, $limit, $offset);
        
        $results = $wpdb->get_results($query);
        
        // Agregar URL de la foto a cada ticket
        foreach ($results as $ticket) {
            $ticket->photo_url = wp_get_attachment_url($ticket->ticket_photo_id);
            if (!$ticket->photo_url) {
                $ticket->photo_url = CV_COMMISSIONS_PLUGIN_URL . 'assets/images/no-image.png';
            }
        }
        
        return $results;
    }
    
    /**
     * Obtener estadísticas de tickets del vendedor
     */
    public function get_vendor_tickets_stats($vendor_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cv_tickets';
        
        $query = $wpdb->prepare("
            SELECT 
                COUNT(*) as total_tickets,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tickets,
                SUM(CASE WHEN status = 'validated' THEN 1 ELSE 0 END) as validated_tickets,
                SUM(amount) as total_amount,
                SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
                SUM(CASE WHEN status = 'validated' THEN amount ELSE 0 END) as validated_amount
            FROM {$table_name}
            WHERE vendor_id = %d
        ", $vendor_id);
        
        $result = $wpdb->get_row($query);
        
        return $result;
    }
    
    /**
     * Ajax: validar ticket
     */
    public function ajax_validate_ticket() {
        // Verificar nonce
        check_ajax_referer('cv_tickets_dashboard', 'nonce');
        
        // Verificar que sea vendedor
        if (!wcfm_is_vendor()) {
            wp_send_json_error(array('message' => 'No autorizado'));
        }
        
        $ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
        $vendor_id = get_current_user_id();
        
        if (!$ticket_id) {
            wp_send_json_error(array('message' => 'ID de ticket no válido'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cv_tickets';
        
        // Verificar que el ticket pertenece al vendedor
        $ticket = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$table_name} WHERE id = %d AND vendor_id = %d
        ", $ticket_id, $vendor_id));
        
        if (!$ticket) {
            wp_send_json_error(array('message' => 'Ticket no encontrado o no autorizado'));
        }
        
        if ($ticket->status === 'validated') {
            wp_send_json_error(array('message' => 'El ticket ya está validado'));
        }
        
        // Actualizar estado del ticket
        $updated = $wpdb->update(
            $table_name,
            array(
                'status' => 'validated',
                'validated_at' => current_time('mysql')
            ),
            array('id' => $ticket_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($updated === false) {
            wp_send_json_error(array('message' => 'Error al validar el ticket'));
        }
        
        wp_send_json_success(array(
            'message' => 'Ticket validado correctamente',
            'ticket_id' => $ticket_id
        ));
    }
    
    /**
     * Ajax: obtener tickets
     */
    public function ajax_get_tickets() {
        // Verificar nonce
        check_ajax_referer('cv_tickets_dashboard', 'nonce');
        
        // Verificar que sea vendedor
        if (!wcfm_is_vendor()) {
            wp_send_json_error(array('message' => 'No autorizado'));
        }
        
        $vendor_id = get_current_user_id();
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        $tickets = $this->get_vendor_tickets($vendor_id, $per_page, $offset);
        $stats = $this->get_vendor_tickets_stats($vendor_id);
        
        // Formatear datos
        $items = array();
        foreach ($tickets as $ticket) {
            // Obtener URL de la foto
            $photo_url = wp_get_attachment_url($ticket->ticket_photo_id);
            if (!$photo_url) {
                $photo_url = CV_COMMISSIONS_PLUGIN_URL . 'assets/images/no-image.png';
            }
            
            $items[] = array(
                'id' => $ticket->id,
                'date' => date('d/m/Y H:i', strtotime($ticket->created_at)),
                'amount' => wc_price($ticket->amount),
                'amount_raw' => $ticket->amount,
                'photo_url' => $photo_url,
                'status' => $ticket->status,
                'validated_at' => $ticket->validated_at ? date('d/m/Y H:i', strtotime($ticket->validated_at)) : null
            );
        }
        
        wp_send_json_success(array(
            'tickets' => $items,
            'stats' => $stats,
            'page' => $page,
            'per_page' => $per_page
        ));
    }
}

