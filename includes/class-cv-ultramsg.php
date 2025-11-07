<?php
/**
 * Integración con Ultramsg (WhatsApp API)
 * 
 * Maneja el envío de notificaciones por WhatsApp usando Ultramsg API
 * 
 * @package CV_Commissions
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase CV_Ultramsg
 * 
 * Gestiona la comunicación con la API de Ultramsg para enviar
 * mensajes de WhatsApp
 */
class CV_Ultramsg {
    
    /**
     * Token de autenticación de Ultramsg
     */
    private $token;
    
    /**
     * ID de la instancia de Ultramsg
     */
    private $instance;
    
    /**
     * URL base de la API
     */
    private $api_url;
    
    /**
     * Teléfono secundario para copiar todos los mensajes
     */
    private $secondary_phone;
    
    /**
     * Prefijo para mensajes al teléfono secundario
     */
    private $secondary_prefix;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_config();
        $this->init_hooks();
    }
    
    /**
     * Cargar configuración desde la base de datos
     */
    private function load_config() {
        $config = get_option('cv_commissions_config', array());
        
        $this->token = isset($config['ultramsg_token']) ? $config['ultramsg_token'] : '';
        $this->instance = isset($config['ultramsg_instance']) ? $config['ultramsg_instance'] : '';
        $this->secondary_phone = isset($config['ultramsg_secondary_phone']) ? $config['ultramsg_secondary_phone'] : '';
        $this->secondary_prefix = isset($config['ultramsg_secondary_prefix']) ? $config['ultramsg_secondary_prefix'] : '📋 Copia de mensaje:';
        
        if (!empty($this->instance)) {
            $this->api_url = "https://api.ultramsg.com/{$this->instance}/messages/chat";
        }
    }
    
    /**
     * Inicializar hooks de WordPress
     */
    private function init_hooks() {
        // Notificación cuando hay nueva consulta en WCFM
        add_action('wcfm_after_enquiry_submit', array($this, 'notify_new_enquiry'), 10, 1);
    }
    
    /**
     * Verificar si Ultramsg está configurado
     */
    public function is_configured() {
        return !empty($this->token) && !empty($this->instance);
    }
    
    /**
     * Enviar mensaje de WhatsApp
     * 
     * @param string $phone Número de teléfono (formato internacional sin +)
     * @param string $message Mensaje a enviar
     * @return array Respuesta de la API
     */
    public function send_message($phone, $message) {
        if (!$this->is_configured()) {
            error_log('CV Ultramsg: API no configurada');
            return array(
                'success' => false,
                'error' => 'Ultramsg no está configurado'
            );
        }
        
        // Limpiar el número de teléfono
        $phone = $this->clean_phone_number($phone);
        
        $params = array(
            'token' => $this->token,
            'to' => $phone,
            'body' => $message
        );
        
        error_log(sprintf(
            'CV Ultramsg: Enviando mensaje a %s - %s',
            $phone,
            substr($message, 0, 50) . '...'
        ));
        
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->api_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_HTTPHEADER => array(
                "content-type: application/x-www-form-urlencoded"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($err) {
            error_log("CV Ultramsg: cURL Error - " . $err);
            $result = array(
                'success' => false,
                'error' => $err
            );
        } else {
            error_log("CV Ultramsg: Respuesta - " . $response);
            $result = array(
                'success' => $http_code == 200,
                'response' => json_decode($response, true),
                'http_code' => $http_code
            );
        }
        
        // Enviar copia al teléfono secundario si está configurado
        if (!empty($this->secondary_phone) && $result['success']) {
            $this->send_to_secondary_phone($phone, $message);
        }
        
        return $result;
    }
    
    /**
     * Enviar copia del mensaje al teléfono secundario
     * 
     * @param string $original_phone Teléfono del destinatario original
     * @param string $original_message Mensaje original
     */
    private function send_to_secondary_phone($original_phone, $original_message) {
        $secondary_phone_clean = $this->clean_phone_number($this->secondary_phone);
        
        // Preparar mensaje con prefijo y datos del destinatario original
        $prefixed_message = sprintf(
            "%s\n\n" .
            "📱 Destinatario: %s\n" .
            "---\n" .
            "%s",
            $this->secondary_prefix,
            $original_phone,
            $original_message
        );
        
        $params = array(
            'token' => $this->token,
            'to' => $secondary_phone_clean,
            'body' => $prefixed_message
        );
        
        error_log(sprintf(
            'CV Ultramsg: Enviando copia a teléfono secundario %s',
            $secondary_phone_clean
        ));
        
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->api_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_HTTPHEADER => array(
                "content-type: application/x-www-form-urlencoded"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($err) {
            error_log("CV Ultramsg: Error enviando a secundario - " . $err);
        } else {
            error_log("CV Ultramsg: Copia enviada a secundario - HTTP " . $http_code);
        }
    }
    
    /**
     * Limpiar número de teléfono al formato requerido
     * 
     * @param string $phone Número de teléfono
     * @return string Número limpio
     */
    private function clean_phone_number($phone) {
        // Eliminar espacios, guiones, paréntesis
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Si no tiene +, agregarlo
        if (strpos($phone, '+') !== 0) {
            $phone = '+' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Notificar nueva consulta al vendedor
     * 
     * @param int $enquiry_id ID de la consulta
     */
    public function notify_new_enquiry($enquiry_id) {
        if (!$this->is_configured()) {
            return;
        }
        
        global $wpdb;
        
        // Obtener datos de la consulta
        $enquiry_query = "SELECT * FROM {$wpdb->prefix}wcfm_enquiries WHERE ID=%d";
        $enquiry = $wpdb->get_row($wpdb->prepare($enquiry_query, $enquiry_id));
        
        if (!$enquiry) {
            error_log('CV Ultramsg: Consulta no encontrada - ID: ' . $enquiry_id);
            return;
        }
        
        $vendor_id = $enquiry->vendor_id;
        $customer_name = !empty($enquiry->customer_name) ? $enquiry->customer_name : 'Un cliente';
        $customer_email = !empty($enquiry->customer_email) ? $enquiry->customer_email : '';
        $enquiry_text = !empty($enquiry->enquiry) ? $enquiry->enquiry : '';
        
        // Obtener teléfono del vendedor
        $phone = $this->get_vendor_phone($vendor_id);
        
        if (empty($phone)) {
            error_log('CV Ultramsg: Vendedor sin teléfono - Vendor ID: ' . $vendor_id);
            return;
        }
        
        // Preparar mensaje - Link separado para que sea clicable
        $message = "🔔 *Nueva consulta en Ciudad Virtual*\n\n";
        $message .= "👤 *{$customer_name}* te ha hecho una consulta";
        
        if (!empty($customer_email)) {
            $message .= "\n📧 {$customer_email}";
        }
        
        // Agregar preview de la consulta (primeros 100 caracteres)
        if (!empty($enquiry_text)) {
            $preview = strlen($enquiry_text) > 100 ? substr($enquiry_text, 0, 100) . '...' : $enquiry_text;
            $message .= "\n\n💬 _{$preview}_";
        }
        
        $message .= "\n\nVer detalles completos:\n\n";
        $message .= "https://ciudadvirtual.app/store-manager/enquiry-manage/{$enquiry_id}/\n\n";
        $message .= "Responde lo antes posible para mejor atención al cliente. ⚡";
        
        // Enviar mensaje
        $result = $this->send_message($phone, $message);
        
        if ($result['success']) {
            error_log('CV Ultramsg: ✅ Notificación enviada - Enquiry ID: ' . $enquiry_id);
        } else {
            error_log('CV Ultramsg: ❌ Error al enviar - ' . print_r($result, true));
        }
    }
    
    /**
     * Obtener teléfono del vendedor
     * 
     * @param int $vendor_id ID del vendedor
     * @return string Teléfono o cadena vacía
     */
    private function get_vendor_phone($vendor_id) {
        // Intentar obtener de diferentes campos
        $phone = get_user_meta($vendor_id, 'telefono-whatsapp', true);
        
        if (empty($phone)) {
            $phone = get_user_meta($vendor_id, 'user_registration_txtTelefono', true);
        }
        
        if (empty($phone)) {
            $phone = get_user_meta($vendor_id, 'billing_phone', true);
        }
        
        return $phone;
    }
    
    /**
     * Enviar notificación de comisión aprobada
     * 
     * @param int $vendor_id ID del vendedor
     * @param float $amount Monto de la comisión
     * @param int $order_id ID del pedido
     */
    public function notify_commission_approved($vendor_id, $amount, $order_id) {
        if (!$this->is_configured()) {
            return;
        }
        
        $phone = $this->get_vendor_phone($vendor_id);
        
        if (empty($phone)) {
            return;
        }
        
        $message = sprintf(
            "💰 *¡Comisión Aprobada!*\n\n" .
            "Has recibido una comisión de %s\n" .
            "Pedido #%d\n\n" .
            "Ver tus comisiones:\n\n" .
            "https://ciudadvirtual.app/store-manager/cv-commissions-dashboard/\n\n" .
            "¡Felicidades! 🎉",
            wc_price($amount),
            $order_id
        );
        
        $this->send_message($phone, $message);
    }
    
    /**
     * Enviar notificación personalizada a un vendedor
     * 
     * @param int $vendor_id ID del vendedor
     * @param string $message Mensaje a enviar
     * @return array Resultado del envío
     */
    public function notify_vendor($vendor_id, $message) {
        if (!$this->is_configured()) {
            return array('success' => false, 'error' => 'No configurado');
        }
        
        $phone = $this->get_vendor_phone($vendor_id);
        
        if (empty($phone)) {
            return array('success' => false, 'error' => 'Sin teléfono');
        }
        
        return $this->send_message($phone, $message);
    }
}

