<?php
/**
 * Sistema de notificaciones push para vendedores
 * Notifica cuando llegan nuevos tickets
 */

if (!defined('ABSPATH')) {
    exit;
}

class CV_Push_Notifications {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Mostrar prompt de notificaciones para vendedores (frontend y admin)
        add_action('wp_footer', array($this, 'show_notification_prompt'));
        add_action('admin_footer', array($this, 'show_notification_prompt'));
        
        // Mostrar contenedor de notificaciones flotantes en la página (frontend y admin)
        add_action('wp_footer', array($this, 'show_in_page_notifications'));
        add_action('admin_footer', array($this, 'show_in_page_notifications'));
        
        // AJAX: Guardar token de notificación
        add_action('wp_ajax_cv_save_notification_token', array($this, 'save_notification_token'));
        
        // AJAX: Obtener notificaciones pendientes
        add_action('wp_ajax_cv_get_pending_notifications', array($this, 'get_pending_notifications'));
        
        // AJAX: Marcar notificación como leída
        add_action('wp_ajax_cv_mark_notification_read', array($this, 'mark_notification_read'));
        
        // Procesar acción de validar desde notificación
        add_action('admin_init', array($this, 'handle_notification_action'));
        
        // Hook cuando se envía un ticket
        add_action('cv_ticket_submitted', array($this, 'notify_vendor_new_ticket'), 10, 4);
        
        // Enqueue scripts (frontend y admin)
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets() {
        // Solo para vendedores/gerentes de tienda
        if (!$this->is_vendor()) {
            return;
        }
        
        wp_enqueue_script(
            'cv-push-notifications',
            CV_COMMISSIONS_PLUGIN_URL . 'assets/js/push-notifications.js',
            array('jquery'),
            CV_COMMISSIONS_VERSION,
            true
        );
        
        wp_localize_script('cv-push-notifications', 'cvPushNotifications', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cv_push_notifications_nonce'),
            'public_key' => $this->get_vapid_public_key(),
            'sw_url' => CV_COMMISSIONS_PLUGIN_URL . 'assets/js/notification-sw.js'
        ));
    }
    
    /**
     * Verificar si el usuario actual es vendedor
     */
    private function is_vendor() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        return in_array('wcfm_vendor', $user->roles) || 
               in_array('dc_vendor', $user->roles) || 
               in_array('seller', $user->roles);
    }
    
    /**
     * Mostrar prompt de notificaciones
     */
    public function show_notification_prompt() {
        if (!$this->is_vendor()) {
            return;
        }
        
        // Verificar si ya aceptó o rechazó las notificaciones
        $user_id = get_current_user_id();
        $notification_status = get_user_meta($user_id, 'cv_notification_status', true);
        
        if ($notification_status === 'granted' || $notification_status === 'denied') {
            return; // Ya respondió
        }
        
        ?>
        <!-- Prompt de notificaciones flotante -->
        <div id="cv-notification-prompt" class="cv-notification-prompt" style="display: none;">
            <div class="cv-notification-prompt-content">
                <div class="cv-notification-icon">🔔</div>
                <div class="cv-notification-text">
                    <h3><?php _e('Recibe notificaciones en tiempo real', 'cv-commissions'); ?></h3>
                    <p><?php _e('Te avisaremos cuando recibas pedidos, consultas, mensajes y tickets de clientes', 'cv-commissions'); ?></p>
                </div>
                <div class="cv-notification-actions">
                    <button id="cv-enable-notifications" class="cv-btn cv-btn-primary">
                        <span class="wcfmfa fa-bell"></span>
                        <?php _e('Activar', 'cv-commissions'); ?>
                    </button>
                    <button id="cv-dismiss-notifications" class="cv-btn cv-btn-secondary">
                        <?php _e('Ahora no', 'cv-commissions'); ?>
                    </button>
                    <button id="cv-never-show-notifications" class="cv-btn cv-btn-tertiary">
                        <span class="wcfmfa fa-ban"></span>
                        <?php _e('No mostrar de nuevo', 'cv-commissions'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <style>
        .cv-notification-prompt {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 999999;
            max-width: 400px;
            animation: cvSlideInRight 0.4s ease-out;
        }
        
        .cv-notification-prompt-content {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }
        
        .cv-notification-icon {
            font-size: 50px;
            text-align: center;
            margin-bottom: 15px;
            animation: cvBell 1s ease-in-out infinite;
        }
        
        .cv-notification-text h3 {
            margin: 0 0 10px 0;
            font-size: 20px;
            font-weight: 700;
            color: #fff;
        }
        
        .cv-notification-text p {
            margin: 0 0 20px 0;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.5;
        }
        
        .cv-notification-actions {
            display: flex;
            gap: 10px;
        }
        
        .cv-notification-actions .cv-btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .cv-btn-primary {
            background: #fff;
            color: #667eea;
        }
        
        .cv-btn-primary:hover {
            background: #f0f0f0;
            transform: translateY(-2px);
        }
        
        .cv-btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
        }
        
        .cv-btn-secondary:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .cv-btn-tertiary {
            background: rgba(255, 77, 87, 0.9);
            color: #fff;
            font-size: 12px;
            padding: 8px 16px;
        }
        
        .cv-btn-tertiary:hover {
            background: rgba(255, 77, 87, 1);
        }
        
        @keyframes cvSlideInRight {
            from {
                transform: translateX(500px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes cvBell {
            0%, 100% { transform: rotate(0deg); }
            10%, 30% { transform: rotate(-10deg); }
            20%, 40% { transform: rotate(10deg); }
        }
        
        @media (max-width: 640px) {
            .cv-notification-prompt {
                bottom: 10px;
                right: 10px;
                left: 10px;
                max-width: none;
            }
            
            .cv-notification-actions {
                flex-direction: column;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Mostrar notificaciones flotantes en la página
     */
    public function show_in_page_notifications() {
        if (!$this->is_vendor()) {
            return;
        }
        
        $user_id = get_current_user_id();
        $notification_status = get_user_meta($user_id, 'cv_notification_status', true);
        
        if ($notification_status !== 'granted') {
            return;
        }
        
        ?>
        <!-- Contenedor de notificaciones flotantes -->
        <div id="cv-notifications-container" style="
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 999998;
            max-width: 400px;
        "></div>
        <?php
    }
    
    /**
     * AJAX: Guardar token de notificación
     */
    public function save_notification_token() {
        check_ajax_referer('cv_push_notifications_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        if (!$user_id || !$this->is_vendor()) {
            wp_send_json_error(array('message' => 'No autorizado'));
            return;
        }
        
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        
        if (empty($token) && $status !== 'denied') {
            wp_send_json_error(array('message' => 'Token inválido'));
            return;
        }
        
        // Guardar token y estado
        if ($status === 'granted') {
            update_user_meta($user_id, 'cv_notification_token', $token);
            update_user_meta($user_id, 'cv_notification_status', 'granted');
        } else {
            update_user_meta($user_id, 'cv_notification_status', 'denied');
        }
        
        wp_send_json_success(array('message' => 'Token guardado'));
    }
    
    /**
     * Notificar al vendedor cuando recibe un ticket
     */
    public function notify_vendor_new_ticket($ticket_id, $vendor_id, $user_id, $amount) {
        // Log inicial
        error_log('CV Notification HOOK DISPARADO - Ticket: ' . $ticket_id . ' Vendor: ' . $vendor_id . ' User: ' . $user_id . ' Amount: ' . $amount);
        
        // Verificar si el vendedor tiene notificaciones activadas
        $notification_status = get_user_meta($vendor_id, 'cv_notification_status', true);
        error_log('CV Notification Status del vendedor ' . $vendor_id . ': ' . $notification_status);
        
        if ($notification_status !== 'granted') {
            error_log('CV Notification: Vendedor NO tiene notificaciones activadas');
            return; // No tiene notificaciones activadas
        }
        
        // Obtener datos del cliente
        $customer = get_userdata($user_id);
        $customer_name = $customer ? $customer->display_name : __('Cliente', 'cv-commissions');
        
        // Guardar notificación pendiente
        $notifications = get_user_meta($vendor_id, 'cv_pending_notifications', true);
        if (!is_array($notifications)) {
            $notifications = array();
        }
        
        $notifications[] = array(
            'id' => uniqid('notification_'),
            'ticket_id' => $ticket_id,
            'customer_name' => $customer_name,
            'amount' => $amount,
            'timestamp' => current_time('timestamp'),
            'read' => false
        );
        
        // Guardar solo las últimas 10 notificaciones
        $notifications = array_slice($notifications, -10);
        $result = update_user_meta($vendor_id, 'cv_pending_notifications', $notifications);
        error_log('CV Notification: Guardada notificación pendiente - Result: ' . ($result ? 'OK' : 'FAIL'));
        error_log('CV Notification: Total notificaciones pendientes: ' . count($notifications));
        
        // Incrementar contador de notificaciones
        $count = get_user_meta($vendor_id, 'cv_notifications_count', true);
        update_user_meta($vendor_id, 'cv_notifications_count', intval($count) + 1);
        error_log('CV Notification: Contador actualizado a: ' . (intval($count) + 1));
    }
    
    /**
     * AJAX: Obtener notificaciones pendientes
     */
    public function get_pending_notifications() {
        check_ajax_referer('cv_push_notifications_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        if (!$user_id || !$this->is_vendor()) {
            wp_send_json_error(array('message' => 'No autorizado'));
            return;
        }
        
        $notifications = get_user_meta($user_id, 'cv_pending_notifications', true);
        if (!is_array($notifications)) {
            $notifications = array();
        }
        
        // Filtrar solo las no leídas
        $unread = array_filter($notifications, function($n) {
            return !$n['read'];
        });
        
        wp_send_json_success(array(
            'notifications' => array_values($unread),
            'count' => count($unread)
        ));
    }
    
    /**
     * AJAX: Marcar notificación como leída
     */
    public function mark_notification_read() {
        check_ajax_referer('cv_push_notifications_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        if (!$user_id || !$this->is_vendor()) {
            wp_send_json_error(array('message' => 'No autorizado'));
            return;
        }
        
        $notification_id = isset($_POST['notification_id']) ? sanitize_text_field($_POST['notification_id']) : '';
        
        if (empty($notification_id)) {
            wp_send_json_error(array('message' => 'ID inválido'));
            return;
        }
        
        $notifications = get_user_meta($user_id, 'cv_pending_notifications', true);
        if (!is_array($notifications)) {
            $notifications = array();
        }
        
        // Marcar como leída
        foreach ($notifications as &$notification) {
            if ($notification['id'] === $notification_id) {
                $notification['read'] = true;
                break;
            }
        }
        
        update_user_meta($user_id, 'cv_pending_notifications', $notifications);
        
        // Decrementar contador
        $count = get_user_meta($user_id, 'cv_notifications_count', true);
        update_user_meta($user_id, 'cv_notifications_count', max(0, intval($count) - 1));
        
        wp_send_json_success(array('message' => 'Notificación marcada'));
    }
    
    /**
     * Enviar notificación push usando Web Push
     */
    private function send_push_notification($token, $notification) {
        // Por ahora solo guardamos, la implementación real requiere un servidor de push
        // En producción se usaría Firebase Cloud Messaging o similar
        
        $debug = get_option('cv_ticket_debug_mode', false);
        if ($debug) {
            error_log('CV Push Notification enviada: ' . print_r($notification, true));
        }
        
        // TODO: Implementar envío real con FCM o Web Push Protocol
        return true;
    }
    
    /**
     * Manejar acción desde notificación (validar ticket)
     */
    public function handle_notification_action() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'validate') {
            return;
        }
        
        if (!isset($_GET['ticket_id'])) {
            return;
        }
        
        $ticket_id = intval($_GET['ticket_id']);
        $user_id = get_current_user_id();
        
        if (!$user_id || !$this->is_vendor()) {
            return;
        }
        
        // Validar el ticket usando la clase de dashboard
        if (class_exists('CV_Tickets_Dashboard')) {
            $dashboard = new CV_Tickets_Dashboard();
            
            // Verificar que el ticket pertenece a este vendedor
            global $wpdb;
            $table_name = $wpdb->prefix . 'cv_tickets';
            
            $ticket = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d AND vendor_id = %d",
                $ticket_id,
                $user_id
            ));
            
            if ($ticket && $ticket->status === 'pending') {
                // Validar ticket
                $wpdb->update(
                    $table_name,
                    array(
                        'status' => 'validated',
                        'validated_at' => current_time('mysql')
                    ),
                    array('id' => $ticket_id),
                    array('%s', '%s'),
                    array('%d')
                );
                
                // Redirigir con mensaje
                wp_redirect(add_query_arg('cv_validated', '1', admin_url('admin.php?page=wcfm-tickets')));
                exit;
            }
        }
    }
    
    /**
     * Obtener clave pública VAPID (para Web Push)
     */
    private function get_vapid_public_key() {
        // Esta sería la clave pública VAPID para Web Push
        // Por ahora devolvemos vacío, se implementaría con Firebase
        return get_option('cv_vapid_public_key', '');
    }
}

// Inicializar
new CV_Push_Notifications();

