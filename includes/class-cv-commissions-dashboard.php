<?php
/**
 * Dashboard de Comisiones para Vendedores
 * 
 * Muestra el historial de comisiones y estado del monedero para cada vendedor
 * Integración con WCFM panel de vendedor
 * 
 * @package CV_Commissions
 * @since 1.0.3
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase CV_Commissions_Dashboard
 * 
 * Integra un dashboard de comisiones en el panel de vendedor de WCFM
 * mostrando:
 * - Historial de comisiones recibidas
 * - Estado del monedero
 * - Estadísticas generales
 */
class CV_Commissions_Dashboard {
    
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
        
        // Ajax handler para obtener datos
        add_action('wp_ajax_cv_get_commissions_data', array($this, 'ajax_get_commissions_data'));
        add_action('wp_ajax_cv_get_commissions_page', array($this, 'ajax_get_commissions_page'));
        add_action('wp_ajax_cv_get_order_summary', array($this, 'ajax_get_order_summary'));
        
        // Widget en dashboard de WCFM
        add_action('wcfm_after_dashboard_setup', array($this, 'add_dashboard_widget'), 10);
        
        // Widget en dashboard de WooCommerce My Account
        add_action('woocommerce_account_dashboard', array($this, 'add_woocommerce_dashboard_widget'), 10);
        
        // Agregar enlace en menú de My Account
        add_filter('woocommerce_account_menu_items', array($this, 'add_woocommerce_menu_item'), 40);
        
        // Agregar endpoint de WooCommerce
        add_action('init', array($this, 'add_woocommerce_endpoints'));
        add_filter('woocommerce_get_query_vars', array($this, 'add_woocommerce_query_vars'));
        
        // Template de WooCommerce My Account
        add_action('woocommerce_account_cv-comisiones_endpoint', array($this, 'woocommerce_commissions_content'));
        
        // Cargar estilos y scripts en WooCommerce My Account
        add_action('wp_enqueue_scripts', array($this, 'enqueue_woocommerce_styles'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_woocommerce_scripts'));
        
        // Shortcode para mostrar resumen
        add_shortcode('cv_commissions_summary', array($this, 'commissions_summary_shortcode'));
    }
    
    /**
     * Agregar query vars
     */
    public function add_query_vars($query_vars) {
        $wcfm_modified_endpoints = wcfm_get_option('wcfm_endpoints', array());
        
        $cv_query_vars = array(
            'cv-commissions-dashboard' => !empty($wcfm_modified_endpoints['cv-commissions-dashboard']) 
                ? $wcfm_modified_endpoints['cv-commissions-dashboard'] 
                : 'cv-commissions-dashboard',
        );
        
        $query_vars = array_merge($query_vars, $cv_query_vars);
        
        return $query_vars;
    }
    
    /**
     * Título del endpoint
     */
    public function endpoint_title($title, $endpoint) {
        if ($endpoint === 'cv-commissions-dashboard') {
            $title = __('Mis Comisiones CV', 'cv-commissions');
        }
        
        return $title;
    }
    
    /**
     * Inicializar endpoint
     */
    public function init_endpoint() {
        global $WCFM_Query;
        
        // Inicializar endpoints de WCFM
        $WCFM_Query->init_query_vars();
        $WCFM_Query->add_endpoints();
        
        if (!get_option('cv_commissions_dashboard_endpoint_added')) {
            // Flush rewrite rules
            flush_rewrite_rules();
            update_option('cv_commissions_dashboard_endpoint_added', 1);
        }
    }
    
    /**
     * Slug del endpoint
     */
    public function endpoints_slug($endpoints) {
        $cv_endpoints = array(
            'cv-commissions-dashboard' => 'mis-comisiones-cv',
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
        
        // Insertar después del dashboard (posición 3)
        $menus = array_slice($menus, 0, 3, true) +
            array('cv-commissions-dashboard' => array(
                'label'    => __('Mis Comisiones CV', 'cv-commissions'),
                'url'      => wcfm_get_endpoint_url('cv-commissions-dashboard'),
                'icon'     => 'money-alt',
                'menu_for' => 'vendor',
                'priority' => 39
            )) +
            array_slice($menus, 3, count($menus) - 3, true);
        
        return $menus;
    }
    
    /**
     * Cargar vista
     */
    public function load_views($end_point) {
        if ($end_point == 'cv-commissions-dashboard') {
            // Cargar directamente desde nuestro plugin
            $template_path = CV_COMMISSIONS_PLUGIN_DIR . 'views/dashboard.php';
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
        // Cargar en dashboard de comisiones
        if ($end_point == 'cv-commissions-dashboard') {
            wp_enqueue_style('cv-commissions-dashboard', 
                CV_COMMISSIONS_PLUGIN_URL . 'assets/css/dashboard.css',
                array(), 
                CV_COMMISSIONS_VERSION
            );
        }
        
        // Cargar también en dashboard principal para el widget
        if ($end_point == 'wcfm-dashboard' || $end_point == '') {
            wp_enqueue_style('cv-commissions-widget', 
                CV_COMMISSIONS_PLUGIN_URL . 'assets/css/dashboard.css',
                array(), 
                CV_COMMISSIONS_VERSION
            );
        }
    }
    
    /**
     * Cargar scripts
     */
    public function load_scripts($end_point) {
        if ($end_point == 'cv-commissions-dashboard') {
            wp_enqueue_script('cv-commissions-dashboard',
                CV_COMMISSIONS_PLUGIN_URL . 'assets/js/dashboard.js',
                array('jquery'),
                CV_COMMISSIONS_VERSION,
                true
            );
            
            // Localizar script
            wp_localize_script('cv-commissions-dashboard', 'cvCommissionsData', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cv_commissions_dashboard'),
            ));
        }
    }
    
    /**
     * Cargar scripts en páginas de WooCommerce
     */
    public function enqueue_woocommerce_scripts() {
        // Cargar en My Account
        if (is_account_page()) {
            wp_enqueue_script('cv-commissions-woo-dashboard',
                CV_COMMISSIONS_PLUGIN_URL . 'assets/js/dashboard.js',
                array('jquery'),
                CV_COMMISSIONS_VERSION,
                true
            );
            
            // Localizar script para WooCommerce también
            wp_localize_script('cv-commissions-woo-dashboard', 'cvCommissionsData', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cv_commissions_dashboard'),
            ));
        }
    }
    
    /**
     * Obtener comisiones del vendedor desde ambas tablas
     * 
     * Combina datos de:
     * - uap_referrals (Indeed Ultimate Affiliate Pro)
     * - wcfm_marketplace_vendor_ledger (WCFM Marketplace)
     * - wcfm_marketplace_orders (WCFM Marketplace)
     */
    public function get_vendor_commissions($vendor_id, $limit = 50, $offset = 0) {
        global $wpdb;
        
        // Tablas
        $uap_referrals = $wpdb->prefix . 'uap_referrals';
        $wcfm_ledger = $wpdb->prefix . 'wcfm_marketplace_vendor_ledger';
        $wcfm_orders = $wpdb->prefix . 'wcfm_marketplace_orders';
        
        // Consulta unificada con UNION - incluye total del pedido y porcentaje
        $query = $wpdb->prepare("
            SELECT 
                'uap' as source,
                r.id,
                r.refferal_wp_uid as vendor_id,
                r.reference as order_id,
                r.reference_details as description,
                r.amount as commission,
                r.currency,
                r.date as created_date,
                CASE r.status
                    WHEN 0 THEN 'refused'
                    WHEN 1 THEN 'pending'
                    WHEN 2 THEN 'approved'
                    ELSE 'unknown'
                END as status,
                CASE r.payment
                    WHEN 0 THEN 'unpaid'
                    WHEN 1 THEN 'pending'
                    WHEN 2 THEN 'paid'
                    ELSE 'unknown'
                END as payment_status,
                (SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE post_id = r.reference AND meta_key = '_order_total' LIMIT 1) as order_total,
                CASE 
                    WHEN (SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE post_id = r.reference AND meta_key = '_order_total' LIMIT 1) > 0
                    THEN ROUND((r.amount / (SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE post_id = r.reference AND meta_key = '_order_total' LIMIT 1)) * 100, 2)
                    ELSE 0
                END as percentage
            FROM {$uap_referrals} r
            WHERE r.refferal_wp_uid = %d
            
            UNION ALL
            
            SELECT 
                'wcfm_ledger' as source,
                l.ID as id,
                l.vendor_id,
                l.reference_id as order_id,
                CONCAT(l.reference, ' - ', l.reference_details) as description,
                CASE 
                    WHEN l.credit > 0 THEN l.credit
                    WHEN l.debit > 0 THEN CONCAT('-', l.debit)
                    ELSE '0'
                END as commission,
                '' as currency,
                l.created as created_date,
                l.reference_status as status,
                '' as payment_status,
                (SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE post_id = l.reference_id AND meta_key = '_order_total' LIMIT 1) as order_total,
                CASE 
                    WHEN (SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE post_id = l.reference_id AND meta_key = '_order_total' LIMIT 1) > 0
                    THEN ROUND((CAST(l.credit AS DECIMAL(10,2)) / (SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE post_id = l.reference_id AND meta_key = '_order_total' LIMIT 1)) * 100, 2)
                    ELSE 0
                END as percentage
            FROM {$wcfm_ledger} l
            WHERE l.vendor_id = %d
            
            ORDER BY created_date DESC
            LIMIT %d OFFSET %d
        ", $vendor_id, $vendor_id, $limit, $offset);
        
        $results = $wpdb->get_results($query);
        
        return $results;
    }
    
    /**
     * Obtener comisiones de pedidos WCFM
     */
    public function get_wcfm_order_commissions($vendor_id, $limit = 50, $offset = 0) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wcfm_marketplace_orders';
        
        $query = $wpdb->prepare("
            SELECT 
                o.ID,
                o.vendor_id,
                o.order_id,
                o.product_id,
                o.quantity,
                o.item_total,
                o.commission_amount,
                o.total_commission,
                o.order_status,
                o.commission_status,
                o.withdraw_status,
                o.created,
                o.commission_paid_date
            FROM {$table_name} o
            WHERE o.vendor_id = %d
            ORDER BY o.created DESC
            LIMIT %d OFFSET %d
        ", $vendor_id, $limit, $offset);
        
        $results = $wpdb->get_results($query);
        
        return $results;
    }
    
    /**
     * Obtener total de comisiones del vendedor desde todas las fuentes
     */
    public function get_vendor_commissions_total($vendor_id) {
        global $wpdb;
        
        // Totales de UAP Referrals
        $uap_referrals = $wpdb->prefix . 'uap_referrals';
        $uap_query = $wpdb->prepare("
            SELECT 
                COUNT(*) as total_count,
                SUM(CASE WHEN status = 2 THEN amount ELSE 0 END) as total_approved,
                SUM(CASE WHEN status = 1 THEN amount ELSE 0 END) as total_pending,
                SUM(CASE WHEN status = 0 THEN amount ELSE 0 END) as total_refused,
                SUM(CASE WHEN payment = 2 THEN amount ELSE 0 END) as total_paid
            FROM {$uap_referrals}
            WHERE refferal_wp_uid = %d
        ", $vendor_id);
        
        $uap_result = $wpdb->get_row($uap_query);
        
        // Totales de WCFM Ledger
        $wcfm_ledger = $wpdb->prefix . 'wcfm_marketplace_vendor_ledger';
        $ledger_query = $wpdb->prepare("
            SELECT 
                COUNT(*) as ledger_count,
                SUM(CAST(credit AS DECIMAL(10,2))) as total_credits,
                SUM(CAST(debit AS DECIMAL(10,2))) as total_debits,
                (SUM(CAST(credit AS DECIMAL(10,2))) - SUM(CAST(debit AS DECIMAL(10,2)))) as ledger_balance
            FROM {$wcfm_ledger}
            WHERE vendor_id = %d
        ", $vendor_id);
        
        $ledger_result = $wpdb->get_row($ledger_query);
        
        // Totales de WCFM Orders
        $wcfm_orders = $wpdb->prefix . 'wcfm_marketplace_orders';
        $orders_query = $wpdb->prepare("
            SELECT 
                COUNT(*) as orders_count,
                SUM(CAST(total_commission AS DECIMAL(10,2))) as total_commissions,
                SUM(CASE WHEN commission_status = 'approved' THEN CAST(total_commission AS DECIMAL(10,2)) ELSE 0 END) as approved_commissions,
                SUM(CASE WHEN withdraw_status = 'completed' THEN CAST(total_commission AS DECIMAL(10,2)) ELSE 0 END) as withdrawn_commissions
            FROM {$wcfm_orders}
            WHERE vendor_id = %d
        ", $vendor_id);
        
        $orders_result = $wpdb->get_row($orders_query);
        
        // Combinar resultados
        return (object) array(
            // UAP Referrals
            'uap_total_count' => $uap_result->total_count,
            'uap_approved' => $uap_result->total_approved,
            'uap_pending' => $uap_result->total_pending,
            'uap_refused' => $uap_result->total_refused,
            'uap_paid' => $uap_result->total_paid,
            
            // WCFM Ledger
            'ledger_count' => $ledger_result->ledger_count,
            'ledger_credits' => $ledger_result->total_credits,
            'ledger_debits' => $ledger_result->total_debits,
            'ledger_balance' => $ledger_result->ledger_balance,
            
            // WCFM Orders
            'orders_count' => $orders_result->orders_count,
            'orders_commissions' => $orders_result->total_commissions,
            'orders_approved' => $orders_result->approved_commissions,
            'orders_withdrawn' => $orders_result->withdrawn_commissions,
            
            // Totales combinados
            'total_count' => $uap_result->total_count + $ledger_result->ledger_count + $orders_result->orders_count,
            'total_approved' => $uap_result->total_approved + $orders_result->approved_commissions,
            'total_pending' => $uap_result->total_pending,
            'total_balance' => $ledger_result->ledger_balance
        );
    }
    
    /**
     * Obtener estado del monedero y balance de UAP
     */
    public function get_wallet_balance($vendor_id) {
        global $wpdb;
        
        // Balance de WooCommerce Wallet - Calculado SIEMPRE directamente desde la BD
        $woo_wallet_balance = 0;
        $woo_wallet_available = false;
        
        // Calcular directamente desde la base de datos para máxima precisión
        $wallet_table = $wpdb->prefix . 'woo_wallet_transactions';
        $wallet_query = $wpdb->prepare("
            SELECT 
                SUM(CASE 
                    WHEN type = 'credit' THEN amount 
                    WHEN type = 'debit' THEN -amount 
                    ELSE 0 
                END) as balance
            FROM {$wallet_table}
            WHERE user_id = %d
            AND deleted = 0
        ", $vendor_id);
        
        $wallet_result = $wpdb->get_row($wallet_query);
        
        if ($wallet_result && $wallet_result->balance !== null) {
            $woo_wallet_balance = floatval($wallet_result->balance);
            $woo_wallet_available = true;
            
            // Log para debugging (puedes comentar esto después)
            error_log(sprintf(
                'CV Commissions - Wallet Balance User %d: %.8f',
                $vendor_id,
                $woo_wallet_balance
            ));
        } else {
            error_log(sprintf(
                'CV Commissions - No wallet transactions found for user %d',
                $vendor_id
            ));
        }
        
        // Balance de UAP Referrals (comisiones aprobadas y pagadas)
        $uap_referrals = $wpdb->prefix . 'uap_referrals';
        $uap_query = $wpdb->prepare("
            SELECT 
                SUM(CASE WHEN status = 2 AND payment = 2 THEN amount ELSE 0 END) as uap_paid,
                SUM(CASE WHEN status = 2 AND payment IN (0,1) THEN amount ELSE 0 END) as uap_approved_unpaid,
                SUM(CASE WHEN status = 1 THEN amount ELSE 0 END) as uap_pending
            FROM {$uap_referrals}
            WHERE refferal_wp_uid = %d
            AND source = 'Calculo privado'
        ", $vendor_id);
        
        $uap_balance = $wpdb->get_row($uap_query);
        
        return array(
            // WooCommerce Wallet (TeraWallet / Woo Wallet)
            'woo_balance' => $woo_wallet_balance,
            'woo_available' => $woo_wallet_available,
            
            // UAP Referrals
            'uap_paid' => $uap_balance->uap_paid ?? 0,
            'uap_approved_unpaid' => $uap_balance->uap_approved_unpaid ?? 0,
            'uap_pending' => $uap_balance->uap_pending ?? 0,
            'uap_total_available' => ($uap_balance->uap_paid ?? 0) + ($uap_balance->uap_approved_unpaid ?? 0),
            
            // General
            'currency' => get_woocommerce_currency(),
            'available' => true
        );
    }
    
    /**
     * Obtener comisiones por período (mes actual, mes pasado, año)
     */
    public function get_commissions_by_period($vendor_id) {
        global $wpdb;
        
        $uap_referrals = $wpdb->prefix . 'uap_referrals';
        
        // Fechas
        $current_month_start = date('Y-m-01 00:00:00');
        $current_month_end = date('Y-m-t 23:59:59');
        
        $last_month_start = date('Y-m-01 00:00:00', strtotime('first day of last month'));
        $last_month_end = date('Y-m-t 23:59:59', strtotime('last day of last month'));
        
        $year_start = date('Y-01-01 00:00:00');
        $year_end = date('Y-12-31 23:59:59');
        
        $query = $wpdb->prepare("
            SELECT 
                SUM(CASE 
                    WHEN date >= %s AND date <= %s AND status = 2 
                    THEN amount ELSE 0 
                END) as current_month,
                
                SUM(CASE 
                    WHEN date >= %s AND date <= %s AND status = 2 
                    THEN amount ELSE 0 
                END) as last_month,
                
                SUM(CASE 
                    WHEN date >= %s AND date <= %s AND status = 2 
                    THEN amount ELSE 0 
                END) as current_year,
                
                COUNT(CASE 
                    WHEN date >= %s AND date <= %s 
                    THEN 1 
                END) as current_month_count,
                
                COUNT(CASE 
                    WHEN date >= %s AND date <= %s 
                    THEN 1 
                END) as last_month_count,
                
                COUNT(CASE 
                    WHEN date >= %s AND date <= %s 
                    THEN 1 
                END) as current_year_count
            FROM {$uap_referrals}
            WHERE refferal_wp_uid = %d
            AND source = 'Calculo privado'
        ", 
            $current_month_start, $current_month_end,
            $last_month_start, $last_month_end,
            $year_start, $year_end,
            $current_month_start, $current_month_end,
            $last_month_start, $last_month_end,
            $year_start, $year_end,
            $vendor_id
        );
        
        $result = $wpdb->get_row($query);
        
        // Nombres de meses en español
        $meses = array(
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        );
        
        $current_month_num = intval(date('n'));
        $last_month_num = intval(date('n', strtotime('last month')));
        $year = date('Y');
        
        return array(
            'current_month' => $result->current_month ?? 0,
            'last_month' => $result->last_month ?? 0,
            'current_year' => $result->current_year ?? 0,
            'current_month_count' => $result->current_month_count ?? 0,
            'last_month_count' => $result->last_month_count ?? 0,
            'current_year_count' => $result->current_year_count ?? 0,
            'current_month_name' => $meses[$current_month_num] . ' ' . $year,
            'last_month_name' => $meses[$last_month_num] . ' ' . date('Y', strtotime('last month')),
            'current_year_name' => $year
        );
    }
    
    /**
     * Obtener transacciones del monedero
     */
    public function get_wallet_transactions($vendor_id, $limit = 10) {
        if (!function_exists('woo_wallet')) {
            return array();
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'woo_wallet_transactions';
        
        $query = $wpdb->prepare("
            SELECT *
            FROM {$table_name}
            WHERE user_id = %d
            ORDER BY date DESC
            LIMIT %d
        ", $vendor_id, $limit);
        
        $results = $wpdb->get_results($query);
        
        return $results;
    }
    
    /**
     * Ajax: obtener datos de comisiones
     */
    public function ajax_get_commissions_data() {
        // Verificar nonce
        check_ajax_referer('cv_commissions_dashboard', 'nonce');
        
        // Verificar que sea vendedor
        if (!wcfm_is_vendor()) {
            wp_send_json_error(array('message' => 'No autorizado'));
        }
        
        $vendor_id = get_current_user_id();
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 10; // Cambiado de 20 a 10
        $offset = ($page - 1) * $per_page;
        
        // Obtener datos
        $commissions = $this->get_vendor_commissions($vendor_id, $per_page, $offset);
        $wcfm_orders = $this->get_wcfm_order_commissions($vendor_id, $per_page, $offset);
        $totals = $this->get_vendor_commissions_total($vendor_id);
        $wallet = $this->get_wallet_balance($vendor_id);
        $wallet_transactions = $this->get_wallet_transactions($vendor_id, $per_page);
        
        wp_send_json_success(array(
            'commissions' => $commissions,
            'wcfm_orders' => $wcfm_orders,
            'totals' => $totals,
            'wallet' => $wallet,
            'wallet_transactions' => $wallet_transactions,
            'page' => $page,
            'per_page' => $per_page
        ));
    }
    
    /**
     * Agregar widget al dashboard de WCFM
     */
    public function add_dashboard_widget() {
        global $WCFM;
        
        if (!wcfm_is_vendor()) {
            return;
        }
        
        $vendor_id = get_current_user_id();
        $totals = $this->get_vendor_commissions_total($vendor_id);
        $wallet = $this->get_wallet_balance($vendor_id);
        
        ?>
        <div class="wcfm-container cv-dashboard-widget">
            <div class="wcfm-clearfix"></div>
            <div class="wcfm_dashboard_stats_head">
                <span class="wcfmfa fa-money-bill-wave"></span>
                <span class="wcfm_dashboard_stats_title">
                    <?php _e('Resumen de Comisiones CV', 'cv-commissions'); ?>
                </span>
                <a href="<?php echo wcfm_get_endpoint_url('cv-commissions-dashboard'); ?>" class="cv-view-all">
                    <?php _e('Ver Detalle Completo', 'cv-commissions'); ?> →
                </a>
            </div>
            <div class="wcfm-clearfix"></div>
            
            <div class="cv-widget-stats">
                <div class="cv-widget-stat">
                    <div class="cv-widget-stat-icon cv-wallet">
                        <span class="wcfmfa fa-wallet"></span>
                    </div>
                    <div class="cv-widget-stat-content">
                        <div class="cv-widget-stat-label"><?php _e('Wallet WC', 'cv-commissions'); ?></div>
                        <div class="cv-widget-stat-value">
                            <?php echo $wallet['woo_available'] ? wc_price($wallet['woo_balance']) : '-'; ?>
                        </div>
                    </div>
                </div>
                
                <div class="cv-widget-stat">
                    <div class="cv-widget-stat-icon cv-uap">
                        <span class="wcfmfa fa-coins"></span>
                    </div>
                    <div class="cv-widget-stat-content">
                        <div class="cv-widget-stat-label"><?php _e('UAP Aprobadas', 'cv-commissions'); ?></div>
                        <div class="cv-widget-stat-value">
                            <?php echo wc_price($wallet['uap_total_available']); ?>
                        </div>
                    </div>
                </div>
                
                <div class="cv-widget-stat">
                    <div class="cv-widget-stat-icon cv-approved">
                        <span class="wcfmfa fa-check-circle"></span>
                    </div>
                    <div class="cv-widget-stat-content">
                        <div class="cv-widget-stat-label"><?php _e('Aprobadas', 'cv-commissions'); ?></div>
                        <div class="cv-widget-stat-value"><?php echo wc_price($totals->total_approved); ?></div>
                    </div>
                </div>
                
                <div class="cv-widget-stat">
                    <div class="cv-widget-stat-icon cv-pending">
                        <span class="wcfmfa fa-clock"></span>
                    </div>
                    <div class="cv-widget-stat-content">
                        <div class="cv-widget-stat-label"><?php _e('Pendientes', 'cv-commissions'); ?></div>
                        <div class="cv-widget-stat-value"><?php echo wc_price($totals->total_pending); ?></div>
                    </div>
                </div>
                
                <div class="cv-widget-stat">
                    <div class="cv-widget-stat-icon cv-ledger">
                        <span class="wcfmfa fa-book"></span>
                    </div>
                    <div class="cv-widget-stat-content">
                        <div class="cv-widget-stat-label"><?php _e('Balance', 'cv-commissions'); ?></div>
                        <div class="cv-widget-stat-value"><?php echo wc_price($totals->ledger_balance); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="wcfm-clearfix"></div>
            <div class="cv-widget-footer">
                <div class="cv-widget-info">
                    <span class="wcfmfa fa-info-circle"></span>
                    <?php printf(
                        __('Total de %d transacciones registradas', 'cv-commissions'),
                        $totals->total_count
                    ); ?>
                </div>
                <a href="<?php echo wcfm_get_endpoint_url('cv-commissions-dashboard'); ?>" class="wcfm_submit_button cv-widget-btn">
                    <?php _e('Ver Dashboard Completo', 'cv-commissions'); ?>
                </a>
            </div>
        </div>
        <div class="wcfm-clearfix"></div>
        <br />
        <?php
    }
    
    /**
     * Shortcode para mostrar resumen de comisiones
     * 
     * Uso: [cv_commissions_summary]
     */
    public function commissions_summary_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('Debes iniciar sesión para ver tus comisiones.', 'cv-commissions') . '</p>';
        }
        
        $vendor_id = get_current_user_id();
        $totals = $this->get_vendor_commissions_total($vendor_id);
        $wallet = $this->get_wallet_balance($vendor_id);
        
        ob_start();
        ?>
        <div class="cv-commissions-shortcode">
            <h3><?php _e('Resumen de Comisiones', 'cv-commissions'); ?></h3>
            
            <div class="cv-shortcode-stats">
                <div class="cv-shortcode-stat">
                    <div class="cv-shortcode-stat-icon">💰</div>
                    <div class="cv-shortcode-stat-label"><?php _e('Wallet WC', 'cv-commissions'); ?></div>
                    <div class="cv-shortcode-stat-value">
                        <?php echo $wallet['woo_available'] ? wc_price($wallet['woo_balance']) : '-'; ?>
                    </div>
                </div>
                
                <div class="cv-shortcode-stat">
                    <div class="cv-shortcode-stat-icon">💎</div>
                    <div class="cv-shortcode-stat-label"><?php _e('UAP Aprobadas', 'cv-commissions'); ?></div>
                    <div class="cv-shortcode-stat-value">
                        <?php echo wc_price($wallet['uap_total_available']); ?>
                    </div>
                </div>
                
                <div class="cv-shortcode-stat">
                    <div class="cv-shortcode-stat-icon">✅</div>
                    <div class="cv-shortcode-stat-label"><?php _e('Aprobadas', 'cv-commissions'); ?></div>
                    <div class="cv-shortcode-stat-value"><?php echo wc_price($totals->total_approved); ?></div>
                </div>
                
                <div class="cv-shortcode-stat">
                    <div class="cv-shortcode-stat-icon">⏰</div>
                    <div class="cv-shortcode-stat-label"><?php _e('Pendientes', 'cv-commissions'); ?></div>
                    <div class="cv-shortcode-stat-value"><?php echo wc_price($totals->total_pending); ?></div>
                </div>
                
                <div class="cv-shortcode-stat">
                    <div class="cv-shortcode-stat-icon">📖</div>
                    <div class="cv-shortcode-stat-label"><?php _e('Balance', 'cv-commissions'); ?></div>
                    <div class="cv-shortcode-stat-value"><?php echo wc_price($totals->ledger_balance); ?></div>
                </div>
            </div>
            
            <div class="cv-shortcode-footer">
                <a href="<?php echo wcfm_get_endpoint_url('cv-commissions-dashboard'); ?>" class="button">
                    <?php _e('Ver Dashboard Completo', 'cv-commissions'); ?>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Agregar widget al dashboard de WooCommerce My Account
     */
    public function add_woocommerce_dashboard_widget() {
        if (!is_user_logged_in()) {
            return;
        }
        
        $vendor_id = get_current_user_id();
        $totals = $this->get_vendor_commissions_total($vendor_id);
        $wallet = $this->get_wallet_balance($vendor_id);
        
        ?>
        <div class="cv-woo-dashboard-widget woocommerce-MyAccount-content">
            <h3><?php _e('💰 Mis Comisiones CV', 'cv-commissions'); ?></h3>
            
            <div class="cv-woo-widget-stats">
                <div class="cv-woo-stat">
                    <div class="cv-woo-stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <span>💰</span>
                    </div>
                    <div class="cv-woo-stat-content">
                        <div class="cv-woo-stat-label"><?php _e('Wallet WC', 'cv-commissions'); ?></div>
                        <div class="cv-woo-stat-value">
                            <?php echo $wallet['woo_available'] ? wc_price($wallet['woo_balance']) : '-'; ?>
                        </div>
                    </div>
                </div>
                
                <div class="cv-woo-stat">
                    <div class="cv-woo-stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <span>💎</span>
                    </div>
                    <div class="cv-woo-stat-content">
                        <div class="cv-woo-stat-label"><?php _e('UAP Aprobadas', 'cv-commissions'); ?></div>
                        <div class="cv-woo-stat-value">
                            <?php echo wc_price($wallet['uap_total_available']); ?>
                        </div>
                    </div>
                </div>
                
                <div class="cv-woo-stat">
                    <div class="cv-woo-stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                        <span>✅</span>
                    </div>
                    <div class="cv-woo-stat-content">
                        <div class="cv-woo-stat-label"><?php _e('Aprobadas', 'cv-commissions'); ?></div>
                        <div class="cv-woo-stat-value"><?php echo wc_price($totals->total_approved); ?></div>
                    </div>
                </div>
                
                <div class="cv-woo-stat">
                    <div class="cv-woo-stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                        <span>⏰</span>
                    </div>
                    <div class="cv-woo-stat-content">
                        <div class="cv-woo-stat-label"><?php _e('Pendientes', 'cv-commissions'); ?></div>
                        <div class="cv-woo-stat-value"><?php echo wc_price($totals->total_pending); ?></div>
                    </div>
                </div>
                
                <div class="cv-woo-stat">
                    <div class="cv-woo-stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <span>📖</span>
                    </div>
                    <div class="cv-woo-stat-content">
                        <div class="cv-woo-stat-label"><?php _e('Balance', 'cv-commissions'); ?></div>
                        <div class="cv-woo-stat-value"><?php echo wc_price($totals->ledger_balance); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="cv-woo-widget-footer">
                <p class="cv-woo-widget-info">
                    <?php printf(
                        __('ℹ️ Total de %d transacciones registradas', 'cv-commissions'),
                        $totals->total_count
                    ); ?>
                </p>
                <a href="<?php echo wc_get_endpoint_url('cv-comisiones'); ?>" class="button wc-forward">
                    <?php _e('Ver Dashboard Completo', 'cv-commissions'); ?>
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Agregar item al menú de WooCommerce My Account
     */
    public function add_woocommerce_menu_item($items) {
        // Insertar antes de "Cerrar sesión"
        $logout = $items['customer-logout'];
        unset($items['customer-logout']);
        
        $items['cv-comisiones'] = __('Mis Comisiones CV', 'cv-commissions');
        $items['customer-logout'] = $logout;
        
        return $items;
    }
    
    /**
     * Agregar endpoints de WooCommerce
     */
    public function add_woocommerce_endpoints() {
        add_rewrite_endpoint('cv-comisiones', EP_ROOT | EP_PAGES);
        
        // Flush rewrite rules solo una vez
        if (!get_option('cv_commissions_woo_endpoint_added')) {
            flush_rewrite_rules();
            update_option('cv_commissions_woo_endpoint_added', 1);
        }
    }
    
    /**
     * Agregar query vars de WooCommerce
     */
    public function add_woocommerce_query_vars($vars) {
        $vars['cv-comisiones'] = 'cv-comisiones';
        return $vars;
    }
    
    /**
     * Contenido de la página de comisiones en My Account
     */
    public function woocommerce_commissions_content() {
        $vendor_id = get_current_user_id();
        $totals = $this->get_vendor_commissions_total($vendor_id);
        $wallet = $this->get_wallet_balance($vendor_id);
        $commissions = $this->get_vendor_commissions($vendor_id, 20, 0);
        $wcfm_orders = $this->get_wcfm_order_commissions($vendor_id, 20, 0);
        
        // Cargar la vista del dashboard (reutilizar la misma)
        include CV_COMMISSIONS_PLUGIN_DIR . 'views/dashboard.php';
    }
    
    /**
     * Ajax: obtener página específica de datos
     */
    public function ajax_get_commissions_page() {
        // Verificar nonce
        check_ajax_referer('cv_commissions_dashboard', 'nonce');
        
        // Verificar que sea un usuario logueado
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'No autorizado'));
        }
        
        $vendor_id = get_current_user_id();
        $table = isset($_POST['table']) ? sanitize_text_field($_POST['table']) : 'uap';
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 10;
        $offset = ($page - 1) * $per_page;
        
        $response = array(
            'page' => $page,
            'per_page' => $per_page,
            'items' => array()
        );
        
        if ($table === 'uap') {
            $items = $this->get_vendor_commissions($vendor_id, $per_page, $offset);
            $totals = $this->get_vendor_commissions_total($vendor_id);
            $response['total'] = $totals->uap_total_count;
            
            // Formatear datos
            foreach ($items as $item) {
                $response['items'][] = array(
                    'id' => $item->id,
                    'date' => date('d/m/Y H:i', strtotime($item->created_date)),
                    'order_id' => $item->order_id,
                    'description' => $item->description,
                    'order_total' => !empty($item->order_total) ? wc_price($item->order_total) : '-',
                    'commission' => wc_price($item->commission),
                    'percentage' => !empty($item->percentage) ? number_format($item->percentage, 2) . '%' : '-',
                    'status' => $item->status,
                    'payment_status' => $item->payment_status ?? '',
                    'source' => $item->source
                );
            }
            
        } elseif ($table === 'wcfm') {
            $items = $this->get_wcfm_order_commissions($vendor_id, $per_page, $offset);
            $totals = $this->get_vendor_commissions_total($vendor_id);
            $response['total'] = $totals->orders_count;
            
            // Formatear datos
            foreach ($items as $item) {
                $product = wc_get_product($item->product_id);
                
                $response['items'][] = array(
                    'ID' => $item->ID,
                    'order_id' => $item->order_id,
                    'product_name' => $product ? $product->get_name() : '#' . $item->product_id,
                    'quantity' => $item->quantity,
                    'item_total' => wc_price($item->item_total),
                    'commission_amount' => wc_price($item->commission_amount),
                    'total_commission' => wc_price($item->total_commission),
                    'order_status' => $item->order_status,
                    'commission_status' => $item->commission_status,
                    'date' => date('d/m/Y H:i', strtotime($item->created))
                );
            }
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * Ajax: obtener resumen del pedido
     */
    public function ajax_get_order_summary() {
        // Verificar nonce
        check_ajax_referer('cv_commissions_dashboard', 'nonce');
        
        // Verificar que sea un usuario logueado
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'No autorizado'));
        }
        
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        
        if (!$order_id) {
            wp_send_json_error(array('message' => 'ID de pedido no válido'));
        }
        
        // Obtener el pedido
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error(array('message' => 'Pedido no encontrado'));
        }
        
        // Preparar datos del pedido
        $order_data = array(
            'order_number' => $order->get_order_number(),
            'date' => $order->get_date_created()->date('d/m/Y H:i'),
            'status' => wc_get_order_status_name($order->get_status()),
            'total' => $order->get_total(),
            'subtotal' => $order->get_subtotal(),
            'shipping' => $order->get_shipping_total(),
            'tax' => $order->get_total_tax(),
            'payment_method' => $order->get_payment_method_title(),
            'currency' => $order->get_currency(),
            'items' => array(),
            'billing' => array(
                'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
                'address' => $order->get_formatted_billing_address()
            )
        );
        
        // Obtener items del pedido
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $order_data['items'][] = array(
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'subtotal' => $item->get_subtotal(),
                'total' => $item->get_total(),
                'sku' => $product ? $product->get_sku() : '',
            );
        }
        
        wp_send_json_success($order_data);
    }
    
    /**
     * Cargar estilos en páginas de WooCommerce
     */
    public function enqueue_woocommerce_styles() {
        // Cargar en My Account dashboard y página de comisiones
        if (is_account_page()) {
            wp_enqueue_style('cv-commissions-woo', 
                CV_COMMISSIONS_PLUGIN_URL . 'assets/css/dashboard.css',
                array(), 
                CV_COMMISSIONS_VERSION
            );
        }
    }
}


