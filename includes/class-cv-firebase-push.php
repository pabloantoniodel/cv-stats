<?php
/**
 * Firebase Cloud Messaging Integration
 * 
 * Sistema de notificaciones push que funciona incluso con el navegador cerrado
 *
 * @package CV_Commissions
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CV_Firebase_Push {
    
    /**
     * Configuración de Firebase
     */
    private $firebase_config = array(
        'apiKey' => 'AIzaSyDsOra9U9p9fzAigFrbxbj83KcBN7LdK6w',
        'authDomain' => 'ciudadvirtual-48edd.firebaseapp.com',
        'projectId' => 'ciudadvirtual-48edd',
        'storageBucket' => 'ciudadvirtual-48edd.firebasestorage.app',
        'messagingSenderId' => '685228701255',
        'appId' => '1:685228701255:web:f76422bf30aadfc3056362',
        'measurementId' => 'G-3C144SBCWN'
    );
    
    /**
     * Server Key de Firebase (para enviar notificaciones desde PHP)
     * Obtenerlo de: Firebase Console > Project Settings > Cloud Messaging > Server Key
     */
    private $server_key = ''; // Se configurará más tarde
    
    public function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Enqueue scripts de Firebase
        add_action('wp_enqueue_scripts', array($this, 'enqueue_firebase_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_firebase_scripts'));
        
        // AJAX: Guardar token FCM
        add_action('wp_ajax_cv_save_fcm_token', array($this, 'save_fcm_token'));
        
        // Hook cuando se envía un ticket
        add_action('cv_ticket_submitted', array($this, 'send_push_notification'), 10, 4);
    }
    
    /**
     * Enqueue scripts de Firebase
     */
    public function enqueue_firebase_scripts() {
        // Solo para vendedores
        if (!$this->is_vendor()) {
            return;
        }
        
        wp_enqueue_script(
            'cv-firebase-push',
            CV_COMMISSIONS_PLUGIN_URL . 'assets/js/firebase-push.js',
            array('jquery'),
            CV_COMMISSIONS_VERSION,
            true
        );
        
        wp_localize_script('cv-firebase-push', 'cvFirebaseConfig', array(
            'config' => $this->firebase_config,
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cv_firebase_nonce'),
            'vapid_key' => $this->get_vapid_key()
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
     * Obtener VAPID key (Web Push certificate)
     * Se obtiene de: Firebase Console > Project Settings > Cloud Messaging > Web Push certificates
     */
    private function get_vapid_key() {
        // Esto hay que generarlo en Firebase Console
        return get_option('cv_firebase_vapid_key', '');
    }
    
    /**
     * AJAX: Guardar token FCM del dispositivo
     */
    public function save_fcm_token() {
        check_ajax_referer('cv_firebase_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        if (!$user_id || !$this->is_vendor()) {
            wp_send_json_error(array('message' => 'No autorizado'));
            return;
        }
        
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        
        if (empty($token)) {
            wp_send_json_error(array('message' => 'Token inválido'));
            return;
        }
        
        // Guardar token FCM
        update_user_meta($user_id, 'cv_fcm_token', $token);
        update_user_meta($user_id, 'cv_fcm_token_updated', current_time('mysql'));
        
        error_log('CV Firebase: Token FCM guardado para user ' . $user_id);
        
        wp_send_json_success(array('message' => 'Token guardado correctamente'));
    }
    
    /**
     * Enviar notificación push cuando se envía un ticket (API v1)
     */
    public function send_push_notification($ticket_id, $vendor_id, $customer_id, $amount) {
        error_log('CV Firebase: Intentando enviar push para ticket ' . $ticket_id . ' a vendor ' . $vendor_id);
        
        // Obtener token FCM del vendedor
        $fcm_token = get_user_meta($vendor_id, 'cv_fcm_token', true);
        
        if (empty($fcm_token)) {
            error_log('CV Firebase: Vendor ' . $vendor_id . ' no tiene token FCM');
            return;
        }
        
        // Obtener nombre del cliente
        $customer = get_userdata($customer_id);
        $customer_name = $customer ? $customer->display_name : 'Un cliente';
        
        // Preparar mensaje
        $title = '🎟️ Nuevo Ticket Recibido';
        $body = $customer_name . ' te ha enviado un ticket de ' . number_format($amount, 2) . '€';
        
        // Enviar notificación via FCM v1
        $result = $this->send_fcm_v1_notification($fcm_token, $title, $body, array(
            'ticket_id' => (string)$ticket_id,
            'vendor_id' => (string)$vendor_id,
            'amount' => (string)$amount,
            'click_action' => home_url('/store-manager/cv-tickets/')
        ));
        
        if ($result) {
            error_log('CV Firebase: ✅ Notificación push enviada correctamente');
        } else {
            error_log('CV Firebase: ❌ Error al enviar notificación push');
        }
    }
    
    /**
     * Base64 URL encoding (sin padding)
     */
    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Generar OAuth 2.0 Access Token usando el Service Account
     */
    private function get_oauth_token() {
        $credentials_file = WP_CONTENT_DIR . '/uploads/firebase-credentials.json';
        
        if (!file_exists($credentials_file)) {
            error_log('CV Firebase: Archivo de credenciales no encontrado');
            return false;
        }
        
        $credentials = json_decode(file_get_contents($credentials_file), true);
        
        // Crear JWT con base64url encoding
        $now = time();
        $jwt_header = $this->base64url_encode(json_encode(array('alg' => 'RS256', 'typ' => 'JWT')));
        
        $jwt_claim = $this->base64url_encode(json_encode(array(
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now
        )));
        
        $jwt_signature_input = $jwt_header . '.' . $jwt_claim;
        
        // Firmar con la clave privada
        openssl_sign($jwt_signature_input, $jwt_signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);
        $jwt_signature = $this->base64url_encode($jwt_signature);
        
        // JWT completo
        $jwt = $jwt_signature_input . '.' . $jwt_signature;
        
        // Intercambiar JWT por Access Token
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            )
        ));
        
        if (is_wp_error($response)) {
            error_log('CV Firebase: Error OAuth: ' . $response->get_error_message());
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['access_token'])) {
            error_log('CV Firebase: ✅ Access token obtenido');
            return $body['access_token'];
        }
        
        error_log('CV Firebase: Error obteniendo token: ' . print_r($body, true));
        return false;
    }
    
    /**
     * Enviar notificación via FCM API v1
     */
    private function send_fcm_v1_notification($token, $title, $body, $data = array()) {
        $access_token = $this->get_oauth_token();
        
        if (!$access_token) {
            error_log('CV Firebase: No se pudo obtener access token');
            return false;
        }
        
        $project_id = 'ciudadvirtual-48edd';
        $url = 'https://fcm.googleapis.com/v1/projects/' . $project_id . '/messages:send';
        
        $message = array(
            'message' => array(
                'token' => $token,
                'notification' => array(
                    'title' => $title,
                    'body' => $body
                ),
                'data' => $data,
                'webpush' => array(
                    'notification' => array(
                        'icon' => home_url('/wp-content/uploads/2024/03/cropped-logo_bajo_3.png'),
                        'badge' => home_url('/wp-content/uploads/2024/03/cropped-logo_bajo_3.png'),
                        'requireInteraction' => true,
                        'vibrate' => array(200, 100, 200),
                        'tag' => 'ticket_' . ($data['ticket_id'] ?? time())
                    ),
                    'fcm_options' => array(
                        'link' => $data['click_action'] ?? home_url('/store-manager/cv-tickets/')
                    )
                )
            )
        );
        
        error_log('CV Firebase v1: URL: ' . $url);
        error_log('CV Firebase v1: Message: ' . json_encode($message));
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($message),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('CV Firebase v1: Error: ' . $response->get_error_message());
            return false;
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        error_log('CV Firebase v1: Response (' . $http_code . '): ' . $response_body);
        
        return $http_code === 200;
    }
    
    /**
     * Enviar notificación via FCM API (LEGACY - DEPRECADO)
     */
    private function send_fcm_notification($token, $title, $body, $data = array()) {
        $server_key = get_option('cv_firebase_server_key', '');
        
        if (empty($server_key)) {
            error_log('CV Firebase: No hay Server Key configurada');
            return false;
        }
        
        error_log('CV Firebase: Server Key: ' . substr($server_key, 0, 20) . '...');
        error_log('CV Firebase: Token: ' . substr($token, 0, 50) . '...');
        
        $url = 'https://fcm.googleapis.com/fcm/send';
        
        $notification = array(
            'title' => $title,
            'body' => $body,
            'icon' => home_url('/wp-content/uploads/2024/03/cropped-logo_bajo_3.png'),
            'badge' => home_url('/wp-content/uploads/2024/03/cropped-logo_bajo_3.png'),
            'requireInteraction' => true,
            'vibrate' => array(200, 100, 200),
            'tag' => 'ticket_' . ($data['ticket_id'] ?? time())
        );
        
        $payload = array(
            'to' => $token,
            'notification' => $notification,
            'data' => $data,
            'webpush' => array(
                'fcm_options' => array(
                    'link' => $data['click_action'] ?? home_url('/store-manager/cv-tickets/')
                )
            )
        );
        
        error_log('CV Firebase: Payload: ' . json_encode($payload));
        
        $headers = array(
            'Authorization: key=' . $server_key,
            'Content-Type: application/json'
        );
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Cambiar a true para producción
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        error_log('CV Firebase: FCM URL: ' . $url);
        error_log('CV Firebase: FCM Response (' . $http_code . '): ' . $result);
        if ($curl_error) {
            error_log('CV Firebase: cURL Error: ' . $curl_error);
        }
        
        return $http_code === 200;
    }
}

new CV_Firebase_Push();

