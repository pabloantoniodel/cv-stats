<?php
/**
 * Notificador Firebase
 * Envía notificaciones push a vendedores
 */

if (!defined('ABSPATH')) {
    exit;
}

class CV_Firebase_Notifier {
    
    /**
     * Configuración
     */
    private $config;
    
    /**
     * Constructor
     */
    public function __construct($config) {
        $this->config = $config;
    }
    
    /**
     * Enviar notificación de nuevo pedido
     * Equivalente a: send_firebase_notification()
     */
    public function send_order_notification($order_id) {
        // Verificar si Firebase está habilitado
        if (!$this->config['firebase_enabled']) {
            if ($this->config['enable_logging']) {
                error_log('CV Commissions: Firebase deshabilitado, no se envía notificación');
            }
            return false;
        }
        
        $order = new WC_Order($order_id);
        
        // Obtener vendedor
        $vendor_id = $this->get_vendor_from_order($order);
        $vendor_data = get_userdata($vendor_id);
        
        if (!$vendor_data) {
            if ($this->config['enable_logging']) {
                error_log('CV Commissions: No se pudo obtener datos del vendedor para notificación');
            }
            return false;
        }
        
        $email = $vendor_data->user_email;
        
        // Obtener token de Firebase
        $token = $this->get_firebase_token($email);
        
        if (!$token) {
            if ($this->config['enable_logging']) {
                error_log('CV Commissions: No se pudo obtener token de Firebase para: ' . $email);
            }
            return false;
        }
        
        // Enviar notificación
        return $this->send_push_notification($token, $order_id);
    }
    
    /**
     * Obtener token de Firebase
     * Equivalente a: obtenfirestoreToken()
     */
    private function get_firebase_token($email) {
        $url = $this->config['firebase_token_url'] . '?user=' . urlencode($email);
        
        try {
            $response = file_get_contents($url);
            
            if ($response === false) {
                return false;
            }
            
            $data = json_decode($response);
            
            if (isset($data->token)) {
                return trim($data->token);
            }
            
            return false;
            
        } catch (Exception $e) {
            if ($this->config['enable_logging']) {
                error_log('CV Commissions: Error obteniendo token Firebase: ' . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Enviar notificación push via Firebase Cloud Messaging
     */
    private function send_push_notification($token, $order_id) {
        $url = 'https://fcm.googleapis.com/fcm/send';
        $api_key = $this->config['firebase_api_key'];
        
        // Headers
        $headers = array(
            'Authorization:key=' . $api_key,
            'Content-Type:application/json'
        );
        
        // Datos de la notificación
        $notification_data = array(
            'pedido' => $order_id,
            'title' => 'Pedido Ciudad Virtual APP',
            'body' => 'Tienes un nuevo pedido en Ciudad Virtual',
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
        );
        
        // Body de la API
        $api_body = array(
            'notification' => $notification_data,
            'data' => $notification_data,
            'to' => $token
        );
        
        // Inicializar cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($api_body));
        
        // Ejecutar
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Cerrar cURL
        curl_close($ch);
        
        if ($this->config['enable_logging']) {
            error_log('CV Commissions: Notificación Firebase enviada. HTTP Code: ' . $http_code . ' - Response: ' . $result);
        }
        
        return $http_code == 200;
    }
    
    /**
     * Obtener vendor de un pedido
     */
    private function get_vendor_from_order($order) {
        $items = $order->get_items();
        $vendor_id = 0;
        
        foreach ($items as $item) {
            $product_id = $item->get_product_id();
            $vendor_id = wcfm_get_vendor_id_by_post($product_id);
            
            if ($vendor_id == 0) {
                $order_item_id = $item->get_id();
                $vendor_id = wc_get_order_item_meta($order_item_id, '_vendor_id', true);
            }
            
            if ($vendor_id) {
                break;
            }
        }
        
        return $vendor_id;
    }
}

