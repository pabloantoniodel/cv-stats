<?php
/**
 * Clase para gestionar el flujo de captura de tickets
 *
 * @package CV_Commissions
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CV_Ticket_Capture {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Whitelist de endpoints de ticket en firewalls
        add_action('init', array($this, 'whitelist_ticket_endpoints'), 1);
        
        // REST API endpoints (evita bloqueos de admin-ajax.php)
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Interceptar la página captura-tu-ticket
        add_filter('template_include', array($this, 'intercept_capture_page'), 99);
        
        // Interceptar la página validar-cuenta
        add_filter('template_include', array($this, 'intercept_validation_page'), 99);
        
        // Enqueue scripts y styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Modificar la redirección del QR Scanner en la página /qr/
        add_action('wp_footer', array($this, 'redirect_qr_to_capture_page'), 1);
        
        // AJAX para subir la foto del ticket
        add_action('wp_ajax_cv_upload_ticket_photo', array($this, 'upload_ticket_photo'));
        add_action('wp_ajax_nopriv_cv_upload_ticket_photo', array($this, 'upload_ticket_photo'));
        
        // AJAX para enviar el ticket completo
        add_action('wp_ajax_cv_submit_ticket', array($this, 'submit_ticket'));
        add_action('wp_ajax_nopriv_cv_submit_ticket', array($this, 'submit_ticket'));
        
        // AJAX para login
        add_action('wp_ajax_cv_ticket_login', array($this, 'ticket_login'));
        add_action('wp_ajax_nopriv_cv_ticket_login', array($this, 'ticket_login'));
        
        // AJAX para registro
        add_action('wp_ajax_cv_ticket_register', array($this, 'ticket_register'));
        add_action('wp_ajax_nopriv_cv_ticket_register', array($this, 'ticket_register'));
    }
    
    /**
     * Registrar rutas REST API (alternativa a admin-ajax.php)
     */
    public function register_rest_routes() {
        register_rest_route('cv-ticket/v1', '/login', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_ticket_login'),
            'permission_callback' => '__return_true', // Público
        ));
        
        register_rest_route('cv-ticket/v1', '/register', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_ticket_register'),
            'permission_callback' => '__return_true', // Público
        ));
    }
    
    /**
     * REST API: Login (evita bloqueos de admin-ajax.php)
     */
    public function rest_ticket_login($request) {
        // Iniciar captura de output
        ob_start();
        
        error_log('REST Login: Iniciando...');
        
        $params = $request->get_json_params();
        error_log('REST Login: JSON params: ' . print_r($params, true));
        
        if (empty($params)) {
            $params = $request->get_params(); // Fallback a GET/POST params
            error_log('REST Login: Usando params normales: ' . print_r($params, true));
        }
        
        $username = isset($params['username']) ? sanitize_text_field($params['username']) : '';
        $password = isset($params['password']) ? $params['password'] : '';
        
        error_log('REST Login: Username: ' . $username);
        error_log('REST Login: Password length: ' . strlen($password));
        
        if (empty($username) || empty($password)) {
            error_log('REST Login: Credenciales vacías');
            ob_end_clean();
            return new WP_Error('missing_credentials', 'Usuario y contraseña requeridos', array('status' => 400));
        }
        
        $creds = array(
            'user_login' => $username,
            'user_password' => $password,
            'remember' => true
        );
        
        error_log('REST Login: Intentando wp_signon...');
        $user = wp_signon($creds, false);
        
        if (is_wp_error($user)) {
            error_log('REST Login: Error en signon: ' . $user->get_error_message());
            ob_end_clean();
            return new WP_Error('login_failed', 'Usuario o contraseña incorrectos', array('status' => 401));
        }
        
        error_log('REST Login: Login exitoso - User ID: ' . $user->ID);
        
        // Capturar cualquier output
        $output = ob_get_contents();
        if (!empty($output)) {
            error_log('REST Login: Output capturado: ' . $output);
        }
        
        // Limpiar output antes de devolver
        ob_end_clean();
        
        $response = array(
            'success' => true,
            'message' => 'Login exitoso',
            'user_id' => $user->ID
        );
        
        error_log('REST Login: Devolviendo respuesta: ' . print_r($response, true));
        
        return rest_ensure_response($response);
    }
    
    /**
     * REST API: Registro (evita bloqueos de admin-ajax.php)
     */
    public function rest_ticket_register($request) {
        $params = $request->get_json_params();
        
        // Implementar lógica de registro aquí (similar a ticket_register)
        return array(
            'success' => true,
            'message' => 'Registro en desarrollo'
        );
    }
    
    /**
     * Whitelist de endpoints de ticket en firewalls
     */
    public function whitelist_ticket_endpoints() {
        // Si es una petición AJAX de ticket, marcarla como confiable
        if (defined('DOING_AJAX') && DOING_AJAX && isset($_POST['action'])) {
            $allowed_actions = array('cv_ticket_login', 'cv_ticket_register', 'cv_upload_ticket_photo', 'cv_submit_ticket');
            if (in_array($_POST['action'], $allowed_actions)) {
                // Desactivar Wordfence temporalmente para estas acciones
                if (class_exists('wfConfig')) {
                    add_filter('wordfence_ls_require_captcha', '__return_false', 999);
                    add_filter('wordfence_ip_is_blocked', '__return_false', 999);
                }
                
                // Marcar como bypass de ModSecurity
                $_SERVER['HTTP_USER_AGENT'] = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Mozilla/5.0';
            }
        }
    }
    
    /**
     * Redirigir QR Scanner a página de captura en lugar de a la tienda
     */
    public function redirect_qr_to_capture_page() {
        if (!is_page('qr')) {
            return;
        }
        
        ?>
        <script>
        // Interceptar QR Scanner y redirigir a /captura-tu-ticket
        (function() {
            console.log('CVTicket QR: Interceptor de redirección instalado');
            
            var storeUrlDetected = false;
            
            // Observar cambios en el DOM para detectar la URL de la tienda
            var observer = new MutationObserver(function(mutations) {
                if (storeUrlDetected) return;
                
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === Node.ELEMENT_NODE || node.nodeType === Node.TEXT_NODE) {
                            var text = (node.textContent || node.innerText || '').trim();
                            
                            // Buscar URL de tienda
                            var urlMatch = text.match(/(https?:\/\/[^\s"'<>]+\/store\/([^\s"'<>/]+))/);
                            
                            if (urlMatch && urlMatch[1] && !storeUrlDetected) {
                                storeUrlDetected = true;
                                var storeUrl = urlMatch[1];
                                var vendorSlug = urlMatch[2];
                                
                                console.log('CVTicket QR: ✅ Tienda detectada:', storeUrl);
                                console.log('CVTicket QR: Vendor slug:', vendorSlug);
                                
                                // Detener observer
                                observer.disconnect();
                                
                                // Obtener información del comercio via AJAX
                                fetch(storeUrl)
                                    .then(function(response) { return response.text(); })
                                    .then(function(html) {
                                        // Parsear el HTML para extraer información
                                        var parser = new DOMParser();
                                        var doc = parser.parseFromString(html, 'text/html');
                                        
                                        // Buscar el botón CAPTURA TICKET
                                        var capturaButton = doc.querySelector('a[href*="captura-tu-ticket"]');
                                        
                                        if (capturaButton) {
                                            var capturaUrl = capturaButton.getAttribute('href');
                                            console.log('CVTicket QR: ✅ URL de captura encontrada:', capturaUrl);
                                            
                                            // Extraer vendor_id de la URL (parámetro codigo_comercio)
                                            try {
                                                var urlObj = new URL(capturaUrl, window.location.origin);
                                                var vendorId = urlObj.searchParams.get('codigo_comercio');
                                                
                                                console.log('CVTicket QR: 🔍 Parámetros de URL:', {
                                                    comercio: urlObj.searchParams.get('comercio'),
                                                    email_comercio: urlObj.searchParams.get('email_comercio'),
                                                    codigo_comercio: vendorId
                                                });
                                                
                                                if (vendorId) {
                                                    console.log('CVTicket QR: 💾 Guardando vendor_id en localStorage:', vendorId);
                                                    localStorage.setItem('cv_ticket_vendor_id', vendorId);
                                                } else {
                                                    console.warn('CVTicket QR: ⚠️ No se encontró codigo_comercio en la URL');
                                                }
                                            } catch(e) {
                                                console.error('CVTicket QR: ❌ Error parseando URL:', e);
                                            }
                                            
                                            // Redirigir a la página de captura
                                            console.log('CVTicket QR: 🔄 Redirigiendo a captura de tickets...');
                                            window.location.href = capturaUrl;
                                        } else {
                                            console.error('CVTicket QR: ❌ No se encontró el botón CAPTURA TICKET');
                                            alert('Error: Este comercio no tiene configurado el sistema de tickets');
                                        }
                                    })
                                    .catch(function(error) {
                                        console.error('CVTicket QR: ❌ Error obteniendo información:', error);
                                        alert('Error de conexión. Por favor, intenta de nuevo.');
                                    });
                            }
                        }
                    });
                });
            });
            
            // Observar el contenedor del QR Scanner
            setTimeout(function() {
                var qrContainer = document.getElementById('qrscannerredirect');
                if (qrContainer) {
                    observer.observe(qrContainer, {
                        childList: true,
                        subtree: true,
                        characterData: true,
                        characterDataOldValue: false
                    });
                    console.log('CVTicket QR: 👀 Observando QR Scanner');
                } else {
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                    console.log('CVTicket QR: 👀 Observando body');
                }
            }, 100);
            
        })();
        </script>
        <?php
    }
    
    /**
     * Interceptar la página captura-tu-ticket y mostrar nuestro sistema
     */
    public function intercept_capture_page($template) {
        if (!is_page('captura-tu-ticket')) {
            return $template;
        }
        
        error_log('CVTicket: Interceptando página captura-tu-ticket');
        error_log('CVTicket: GET params: ' . print_r($_GET, true));
        
        // Obtener parámetros
        $comercio = isset($_GET['comercio']) ? sanitize_text_field($_GET['comercio']) : '';
        $email_comercio = isset($_GET['email_comercio']) ? sanitize_email($_GET['email_comercio']) : '';
        $codigo_comercio = isset($_GET['codigo_comercio']) ? sanitize_text_field($_GET['codigo_comercio']) : '';
        
        error_log('CVTicket: Comercio: ' . $comercio);
        error_log('CVTicket: Email: ' . $email_comercio);
        error_log('CVTicket: Código: ' . $codigo_comercio);
        
        if (empty($comercio) || empty($email_comercio)) {
            // Si no hay parámetros, redirigir a /qr/
            error_log('CVTicket: Faltan parámetros, redirigiendo a /qr/');
            wp_redirect(home_url('/qr/'));
            exit;
        }
        
        error_log('CVTicket: Parámetros válidos, procesando...');
        
        // Guardar en sesión
        if (!session_id()) {
            session_start();
        }
        
        // Buscar el vendor por email
        $vendor = get_user_by('email', $email_comercio);
        
        if (!$vendor) {
            wp_die('Comercio no encontrado.');
        }
        
        $_SESSION['cv_ticket_vendor_id'] = $vendor->ID;
        $_SESSION['cv_ticket_vendor_name'] = $comercio;
        $_SESSION['cv_ticket_vendor_code'] = $codigo_comercio;
        
        error_log('CVTicket: Sesión guardada, vendor ID: ' . $vendor->ID);
        
        // Renderizar nuestra página personalizada
        $this->render_capture_page($comercio, $codigo_comercio);
        
        error_log('CVTicket: Después de render_capture_page - esto NO debería aparecer');
        
        // No retornar el template, forzar salida
        die();
    }
    
    /**
     * Interceptar la página validar-cuenta y procesar token
     */
    public function intercept_validation_page($template) {
        if (!is_page('validar-cuenta')) {
            return $template;
        }
        
        // Obtener parámetros
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        $phone = isset($_GET['phone']) ? sanitize_text_field($_GET['phone']) : '';
        
        if (empty($token) || empty($phone)) {
            $this->render_validation_error('Enlace de validación inválido');
            die();
        }
        
        // Buscar usuario por teléfono
        $user = get_user_by('login', $phone);
        
        if (!$user) {
            $this->render_validation_error('Usuario no encontrado');
            die();
        }
        
        // Verificar token
        $stored_token = get_user_meta($user->ID, 'cv_validation_token', true);
        $token_expires = get_user_meta($user->ID, 'cv_validation_token_expires', true);
        
        if ($stored_token !== $token) {
            $this->render_validation_error('Token de validación inválido');
            die();
        }
        
        if ($token_expires < time()) {
            $this->render_validation_error('El enlace de validación ha expirado. Por favor, contacta con soporte.');
            die();
        }
        
        // Activar cuenta
        delete_user_meta($user->ID, 'cv_account_pending_validation');
        delete_user_meta($user->ID, 'cv_validation_token');
        delete_user_meta($user->ID, 'cv_validation_token_expires');
        
        // Auto-login
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        
        // Si hay ticket pendiente en sesión, crearlo ahora
        if (!session_id()) {
            session_start();
        }
        
        if (isset($_SESSION['cv_pending_user'])) {
            $pending_data = $_SESSION['cv_pending_user'];
            
            // Crear el ticket
            global $wpdb;
            $table_name = $wpdb->prefix . 'cv_tickets';
            
            $wpdb->insert(
                $table_name,
                array(
                    'ticket_photo_id' => $pending_data['photo_id'],
                    'vendor_id' => $pending_data['vendor_id'],
                    'customer_id' => $user->ID,
                    'amount' => 0, // Se actualizará después
                    'status' => 'pending',
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%d', '%d', '%f', '%s', '%s')
            );
            
            unset($_SESSION['cv_pending_user']);
        }
        
        // Mostrar mensaje de éxito
        $password = get_user_meta($user->ID, 'cv_validation_password', true);
        delete_user_meta($user->ID, 'cv_validation_password');
        
        $this->render_validation_success($user->display_name, $phone, $password);
        die();
    }
    
    /**
     * Renderizar página de error de validación
     */
    private function render_validation_error($message) {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Error de Validación</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .validation-box {
                    background: white;
                    border-radius: 20px;
                    padding: 40px;
                    max-width: 500px;
                    text-align: center;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                }
                .validation-icon {
                    font-size: 80px;
                    margin-bottom: 20px;
                }
                h1 {
                    font-size: 28px;
                    color: #e74c3c;
                    margin-bottom: 15px;
                }
                p {
                    font-size: 16px;
                    color: #666;
                    margin-bottom: 30px;
                    line-height: 1.6;
                }
                .btn {
                    display: inline-block;
                    padding: 15px 40px;
                    background: #667eea;
                    color: white;
                    text-decoration: none;
                    border-radius: 10px;
                    font-weight: 600;
                    font-size: 16px;
                    transition: all 0.3s ease;
                }
                .btn:hover {
                    background: #5568d3;
                    transform: translateY(-2px);
                    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
                }
            </style>
        </head>
        <body>
            <div class="validation-box">
                <div class="validation-icon">❌</div>
                <h1>Error de Validación</h1>
                <p><?php echo esc_html($message); ?></p>
                <a href="<?php echo esc_url(home_url('/')); ?>" class="btn">Volver al Inicio</a>
            </div>
        </body>
        </html>
        <?php
    }
    
    /**
     * Renderizar página de éxito de validación
     */
    private function render_validation_success($name, $phone, $password) {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Cuenta Validada</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    padding: 20px;
                    overflow-y: auto;
                    -webkit-overflow-scrolling: touch;
                }
                .validation-box {
                    background: white;
                    border-radius: 20px;
                    padding: 40px;
                    max-width: 600px;
                    margin: 20px auto;
                    text-align: center;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                }
                .validation-icon {
                    font-size: 80px;
                    margin-bottom: 20px;
                    animation: bounce 1s ease-in-out infinite;
                }
                @keyframes bounce {
                    0%, 100% { transform: translateY(0); }
                    50% { transform: translateY(-10px); }
                }
                h1 {
                    font-size: 32px;
                    color: #27ae60;
                    margin-bottom: 15px;
                }
                .welcome-name {
                    font-size: 24px;
                    color: #333;
                    margin-bottom: 30px;
                }
                .info-box {
                    background: #f8f9fa;
                    border-radius: 15px;
                    padding: 25px;
                    margin-bottom: 30px;
                    text-align: left;
                }
                .info-item {
                    margin-bottom: 15px;
                    padding-bottom: 15px;
                    border-bottom: 1px solid #e0e0e0;
                }
                .info-item:last-child {
                    margin-bottom: 0;
                    padding-bottom: 0;
                    border-bottom: none;
                }
                .info-label {
                    font-weight: 600;
                    color: #666;
                    font-size: 14px;
                    margin-bottom: 5px;
                }
                .info-value {
                    font-size: 18px;
                    color: #333;
                    font-weight: 600;
                    font-family: 'Courier New', monospace;
                }
                .benefits {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    border-radius: 15px;
                    padding: 20px;
                    margin-bottom: 30px;
                    text-align: left;
                }
                .benefits h3 {
                    font-size: 18px;
                    margin-bottom: 15px;
                }
                .benefits ul {
                    list-style: none;
                }
                .benefits li {
                    padding: 8px 0;
                    padding-left: 30px;
                    position: relative;
                }
                .benefits li:before {
                    content: '✓';
                    position: absolute;
                    left: 0;
                    font-weight: bold;
                    font-size: 20px;
                }
                .btn {
                    display: inline-block;
                    padding: 15px 50px;
                    background: #667eea;
                    color: white;
                    text-decoration: none;
                    border-radius: 10px;
                    font-weight: 600;
                    font-size: 18px;
                    transition: all 0.3s ease;
                }
                .btn:hover {
                    background: #5568d3;
                    transform: translateY(-2px);
                    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
                }
                .note {
                    margin-top: 20px;
                    font-size: 13px;
                    color: #999;
                }
            </style>
        </head>
        <body>
            <div class="validation-box">
                <div class="validation-icon">🎉</div>
                <h1>¡Cuenta Validada!</h1>
                <p class="welcome-name">Bienvenido, <?php echo esc_html($name); ?></p>
                
                <div class="info-box">
                    <div class="info-item">
                        <div class="info-label">Tu Usuario:</div>
                        <div class="info-value"><?php echo esc_html($phone); ?></div>
                    </div>
                    <?php if ($password): ?>
                    <div class="info-item">
                        <div class="info-label">Tu Contraseña:</div>
                        <div class="info-value"><?php echo esc_html($password); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="benefits">
                    <h3>✨ Ahora puedes disfrutar de:</h3>
                    <ul>
                        <li>Acumular descuentos en tus compras</li>
                        <li>Recibir promociones exclusivas</li>
                        <li>Controlar todos tus tickets</li>
                        <li>Acceder a toda la plataforma</li>
                    </ul>
                </div>
                
                <a href="<?php echo esc_url(home_url('/')); ?>" class="btn">Ir a la Plataforma</a>
                
                <p class="note">
                    💡 Guarda tu usuario y contraseña en un lugar seguro.
                </p>
            </div>
        </body>
        </html>
        <?php
    }
    
    /**
     * Renderizar página de captura de tickets
     */
    private function render_capture_page($comercio, $codigo_comercio) {
        // FORZAR CARGA DE ASSETS ANTES DEL HTML
        wp_enqueue_style(
            'cv-ticket-capture',
            CV_COMMISSIONS_PLUGIN_URL . 'assets/css/ticket-capture.css',
            array(),
            CV_COMMISSIONS_VERSION
        );
        
        wp_enqueue_script(
            'cv-ticket-capture',
            CV_COMMISSIONS_PLUGIN_URL . 'assets/js/ticket-capture.js',
            array('jquery'),
            CV_COMMISSIONS_VERSION,
            false // Cargar en el HEAD, no en el footer
        );
        
        // Obtener vendor_id de la sesión
        if (!session_id()) {
            session_start();
        }
        $vendor_id = isset($_SESSION['cv_ticket_vendor_id']) ? $_SESSION['cv_ticket_vendor_id'] : 0;
        
        // Debug
        error_log('CVTicket Enqueue: vendor_id desde sesión: ' . $vendor_id);
        error_log('CVTicket Enqueue: Session ID: ' . session_id());
        error_log('CVTicket Enqueue: Session data: ' . print_r($_SESSION, true));
        
        wp_localize_script('cv-ticket-capture', 'cvTicketCapture', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url('cv-ticket/v1'),
            'nonce' => wp_create_nonce('cv_ticket_capture_nonce'),
            'vendor_id' => $vendor_id,
            'strings' => array(
                'camera_title' => __('Permisos de Cámara', 'cv-commissions'),
                'camera_instruction' => __('Para capturar el ticket, necesitamos acceso a tu cámara.', 'cv-commissions'),
                'camera_steps' => array(
                    __('1. El navegador te pedirá permiso para usar la cámara', 'cv-commissions'),
                    __('2. Haz clic en "Permitir" cuando aparezca el mensaje', 'cv-commissions'),
                    __('3. Apunta la cámara al ticket y toma la foto', 'cv-commissions'),
                    __('4. Verifica que la foto sea legible antes de continuar', 'cv-commissions'),
                ),
                'permissions_ok' => __('Entiendo, tengo los permisos', 'cv-commissions'),
                'btn_next' => __('Siguiente', 'cv-commissions'),
                'btn_cancel' => __('Cancelar', 'cv-commissions'),
                'btn_retake' => __('Repetir Foto', 'cv-commissions'),
                'btn_send' => __('Enviar Ticket', 'cv-commissions'),
                'capture_title' => __('Capturar Ticket', 'cv-commissions'),
                'review_title' => __('Revisar y Enviar', 'cv-commissions'),
                'amount_label' => __('Importe del Ticket (€)', 'cv-commissions'),
                'amount_placeholder' => __('Ej: 25.50', 'cv-commissions'),
                'store_label' => __('Comercio:', 'cv-commissions'),
                'error_no_vendor' => __('Error: No se detectó el comercio.', 'cv-commissions'),
                'error_camera' => __('Error al acceder a la cámara. Verifica los permisos.', 'cv-commissions'),
                'error_upload' => __('Error al subir la foto. Inténtalo de nuevo.', 'cv-commissions'),
                'error_amount' => __('Por favor, introduce un importe válido.', 'cv-commissions'),
                'success' => __('¡Ticket enviado correctamente!', 'cv-commissions'),
            ),
        ));
        
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Captura tu Ticket - <?php echo esc_html($comercio); ?></title>
            <?php wp_head(); ?>
            <style>
                /* Ocultar todo el contenido del tema */
                header,
                footer,
                #header,
                #footer,
                .site-header,
                .site-footer,
                .header,
                .footer,
                #masthead,
                #colophon,
                nav,
                .navigation,
                .nav,
                #content,
                .content,
                .site-content,
                #primary,
                #main,
                .breadcrumbs,
                .woocommerce-breadcrumb,
                .entry-header,
                .entry-content,
                .entry-footer {
                    display: none !important;
                }
                
                body {
                    margin: 0 !important;
                    padding: 0 !important;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
                    min-height: 100vh !important;
                    overflow-x: hidden !important;
                }
                
                #cv-capture-page-header {
                    display: block !important;
                    background: rgba(255, 255, 255, 0.95);
                    padding: 20px;
                    text-align: center;
                    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                }
                
                #cv-capture-page-header h1 {
                    margin: 0 0 10px;
                    font-size: 28px;
                    color: #333;
                }
                
                #cv-capture-page-header p {
                    margin: 0;
                    font-size: 18px;
                    color: #666;
                }
                
                #cv-capture-page-header .store-code {
                    display: inline-block;
                    margin-top: 10px;
                    padding: 8px 16px;
                    background: #667eea;
                    color: #fff;
                    border-radius: 20px;
                    font-weight: 600;
                    font-size: 14px;
                }
                
                #cv-ticket-capture-wrapper {
                    display: block !important;
                }
                
                /* Pantalla de éxito */
                .cv-success-screen {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                    z-index: 10000;
                    overflow-y: auto;
                    overflow-x: hidden;
                }
                
                .cv-success-container {
                    background: white;
                    border-radius: 20px;
                    padding: 40px 30px;
                    max-width: 500px;
                    width: 100%;
                    text-align: center;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                    margin: auto;
                    max-height: none;
                }
                
                .cv-success-icon {
                    font-size: 80px;
                    margin-bottom: 20px;
                    animation: successPop 0.6s ease-out;
                }
                
                @keyframes successPop {
                    0% { transform: scale(0); opacity: 0; }
                    50% { transform: scale(1.2); }
                    100% { transform: scale(1); opacity: 1; }
                }
                
                #cv-success-title {
                    font-size: 32px;
                    color: #333;
                    margin: 0 0 10px 0;
                    font-weight: bold;
                }
                
                #cv-success-message {
                    font-size: 18px;
                    color: #666;
                    margin: 0 0 30px 0;
                }
                
                .cv-success-summary {
                    background: #f8f9fa;
                    border-radius: 15px;
                    padding: 25px;
                    margin-bottom: 30px;
                }
                
                .cv-success-item {
                    margin-bottom: 20px;
                }
                
                .cv-success-item:last-child {
                    margin-bottom: 0;
                }
                
                .cv-success-item strong {
                    display: block;
                    font-size: 14px;
                    color: #999;
                    margin-bottom: 5px;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }
                
                .cv-success-item span {
                    display: block;
                    font-size: 20px;
                    color: #333;
                    font-weight: 600;
                }
                
                .cv-success-item img {
                    max-width: 300px;
                    width: 100%;
                    height: auto;
                    border-radius: 10px;
                    margin: 10px auto;
                    display: block;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                }
                
                .cv-success-amount span {
                    font-size: 36px;
                    color: #667eea;
                    font-weight: bold;
                }
                
                .cv-btn-large {
                    padding: 18px 60px !important;
                    font-size: 20px !important;
                    min-width: 250px !important;
                    background: #667eea !important;
                    color: white !important;
                    border: none !important;
                    border-radius: 12px !important;
                    font-weight: bold !important;
                    cursor: pointer !important;
                    transition: all 0.3s ease !important;
                    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4) !important;
                }
                
                .cv-btn-large:hover {
                    background: #5568d3 !important;
                    transform: translateY(-2px) !important;
                    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6) !important;
                }
                
                /* Asegurar altura uniforme en todos los botones de cámara */
                #cv-capture-photo,
                #cv-retake-photo,
                #cv-confirm-photo,
                #cv-cancel-capture {
                    min-height: 50px;
                    height: auto;
                    padding: 12px 24px;
                    font-size: 16px;
                    line-height: 1.5;
                    align-items: center;
                    justify-content: center;
                    vertical-align: middle;
                }
                
                /* Los botones ocultos mantienen width:0 */
                #cv-retake-photo[style*="width:0"],
                #cv-confirm-photo[style*="width:0"] {
                    min-height: 0 !important;
                }
            </style>
        </head>
        <body class="page-captura-ticket">
            
            <div id="cv-capture-page-header">
                <h1>📸 Captura tu Ticket</h1>
                <p><strong><?php echo esc_html($comercio); ?></strong></p>
                <?php if (!empty($codigo_comercio)): ?>
                    <span class="store-code">Código: <?php echo esc_html($codigo_comercio); ?></span>
                <?php endif; ?>
            </div>
            
            <?php echo $this->get_capture_html(); ?>
            
            <script>
            jQuery(document).ready(function($) {
                // Sistema de debug - controlado por configuración del plugin
                var debugEnabled = <?php echo (get_option('cv_ticket_debug_mode', false) ? 'true' : 'false'); ?>;
                var debugDiv = null;
                
                if (debugEnabled) {
                    debugDiv = $('<div id="cv-debug" style="position:fixed;top:0;left:0;right:0;background:rgba(0,0,0,0.9);color:#0f0;padding:10px;font-size:12px;z-index:999999;max-height:150px;overflow-y:auto;"></div>');
                    $('body').append(debugDiv);
                }
                
                function debug(msg) {
                    console.log('CVTicket: ' + msg);
                    if (debugEnabled && debugDiv) {
                        debugDiv.append('<div>' + new Date().toLocaleTimeString() + ': ' + msg + '</div>');
                        debugDiv.scrollTop(debugDiv[0].scrollHeight);
                    }
                }
                
                debug('Página cargada - Comercio: <?php echo esc_js($comercio); ?>');
                
                // Verificar que cvTicketCapture esté definido
                if (typeof cvTicketCapture === 'undefined') {
                    console.error('CVTicket: ERROR - cvTicketCapture no definido');
                    alert('Error de configuración. Recarga la página.');
                    return;
                }
                
                debug('Sistema inicializado correctamente');
                
                // Ir directamente a capturar foto (sin modal de permisos)
                setTimeout(function() {
                    debug('🎬 Iniciando captura...');
                    
                    // DETENER TODAS LAS CÁMARAS ACTIVAS (del QR scanner)
                    debug('🛑 Deteniendo cámaras activas...');
                    
                    // Método 1: Detener todos los MediaStreams activos
                    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                        navigator.mediaDevices.enumerateDevices().then(function(devices) {
                            debug('📹 Dispositivos encontrados: ' + devices.length);
                        });
                    }
                    
                    // Método 2: Buscar y detener videos activos en la página
                    $('video').each(function() {
                        var video = this;
                        if (video.srcObject) {
                            debug('🛑 Deteniendo video: ' + video.id);
                            var tracks = video.srcObject.getTracks();
                            tracks.forEach(function(track) {
                                track.stop();
                                debug('✅ Track detenido: ' + track.kind);
                            });
                            video.srcObject = null;
                        }
                    });
                    
                    // Método 3: Si existe el plugin QR Scanner, detenerlo
                    if (window.qrscannerredirect && window.qrscannerredirect.stop) {
                        debug('🛑 Deteniendo QR Scanner...');
                        window.qrscannerredirect.stop();
                    }
                    
                    debug('✅ Cámaras liberadas');
                    
                    // Esperar un poco para que el SO libere la cámara completamente
                    setTimeout(function() {
                        debug('⏱️ Esperando liberación completa...');
                        
                        // Mostrar wrapper
                        $('#cv-ticket-capture-wrapper').show();
                        
                        // Llenar textos con iconos
                        $('#cv-capture-title').text(cvTicketCapture.strings.capture_title);
                        $('#cv-capture-photo').html('📷 Capturar');
                        $('#cv-retake-photo').html('🔄 ' + cvTicketCapture.strings.btn_retake);
                        $('#cv-confirm-photo').html('✅ ' + cvTicketCapture.strings.btn_next);
                        $('#cv-cancel-capture').html('❌ ' + cvTicketCapture.strings.btn_cancel);
                        
                        debug('📝 Textos configurados');
                        
                        // Forzar estado inicial de botones ANTES del fadeIn (ya están bien configurados en HTML)
                        // Los botones tienen style inline con !important, no es necesario JS
                        debug('🔘 Estado inicial de botones configurado en HTML');
                        
                        // Mostrar interfaz de cámara y vincular eventos DESPUÉS del fadeIn
                        $('#cv-camera-capture').fadeIn(400, function() {
                            debug('👁️ Cámara visible');
                        
                        // SISTEMA INLINE - No depende de archivos externos
                        var CVCapture = {
                            stream: null,
                            photoBlob: null,
                            photoId: null,
                            savedPhotoUrl: null,
                            submittedAmount: 0,
                            
                            initCamera: function() {
                                debug('📸 Solicitando cámara...');
                                var video = document.getElementById('cv-camera-video');
                                var constraints = {
                                    video: {
                                        facingMode: { ideal: 'environment' },
                                        width: { ideal: 1280 },
                                        height: { ideal: 720 }
                                    }
                                };
                                
                                navigator.mediaDevices.getUserMedia(constraints)
                                    .then(function(stream) {
                                        CVCapture.stream = stream;
                                        video.srcObject = stream;
                                        video.play();
                                        debug('✅ Cámara activa');
                                    })
                                    .catch(function(error) {
                                        debug('❌ Error cámara: ' + error.message);
                                        alert('Error al acceder a la cámara: ' + error.message);
                                    });
                            },
                            
                            capturePhoto: function() {
                                debug('📸 Capturando foto...');
                                var video = document.getElementById('cv-camera-video');
                                var canvas = document.getElementById('cv-camera-canvas');
                                var image = document.getElementById('cv-captured-image');
                                var context = canvas.getContext('2d');
                                
                                canvas.width = video.videoWidth;
                                canvas.height = video.videoHeight;
                                context.drawImage(video, 0, 0, canvas.width, canvas.height);
                                
                                var imageDataUrl = canvas.toDataURL('image/jpeg', 0.8);
                                image.src = imageDataUrl;
                                
                                canvas.toBlob(function(blob) {
                                    CVCapture.photoBlob = blob;
                                    debug('✅ Foto capturada');
                                }, 'image/jpeg', 0.8);
                                
                                // Ocultar video y mostrar imagen
                                $(video).hide();
                                $(image).show();
                                
                                // Cambiar botones: ocultar Capturar, mostrar Repetir y Siguiente
                                $('#cv-capture-photo').attr('style', 'display:none !important; visibility:hidden !important; width:0; height:0; padding:0; margin:0; border:0; overflow:hidden;');
                                $('#cv-retake-photo').attr('style', 'display:inline-block !important;');
                                $('#cv-confirm-photo').attr('style', 'display:inline-block !important;');
                                
                                debug('🔘 Botones actualizados después de captura');
                                
                                if (CVCapture.stream) {
                                    CVCapture.stream.getTracks().forEach(track => track.stop());
                                }
                            },
                            
                            retakePhoto: function() {
                                debug('🔄 Repitiendo foto...');
                                var video = document.getElementById('cv-camera-video');
                                var image = document.getElementById('cv-captured-image');
                                
                                // Ocultar imagen, mostrar video
                                $(image).hide();
                                $(video).show();
                                
                                // Cambiar botones: mostrar Capturar, ocultar Repetir y Siguiente
                                $('#cv-capture-photo').attr('style', 'display:inline-block !important;');
                                $('#cv-retake-photo').attr('style', 'display:none !important; visibility:hidden !important; width:0; height:0; padding:0; margin:0; border:0; overflow:hidden;');
                                $('#cv-confirm-photo').attr('style', 'display:none !important; visibility:hidden !important; width:0; height:0; padding:0; margin:0; border:0; overflow:hidden;');
                                
                                debug('🔘 Botones restaurados para nueva captura');
                                
                                CVCapture.initCamera();
                            },
                            
                            uploadPhoto: function() {
                                debug('📤 Subiendo foto...');
                                if (!CVCapture.photoBlob) {
                                    alert('Error: No hay foto');
                                    return;
                                }
                                
                                debug('Blob size: ' + CVCapture.photoBlob.size);
                                debug('Vendor ID: ' + cvTicketCapture.vendor_id);
                                debug('Nonce: ' + cvTicketCapture.nonce.substring(0, 10) + '...');
                                
                                $('#cv-confirm-photo').prop('disabled', true).text('Subiendo...');
                                
                                var formData = new FormData();
                                formData.append('action', 'cv_upload_ticket_photo');
                                formData.append('nonce', cvTicketCapture.nonce);
                                formData.append('vendor_id', cvTicketCapture.vendor_id);
                                formData.append('ticket_photo', CVCapture.photoBlob, 'ticket.jpg');
                                
                                debug('Enviando AJAX a: ' + cvTicketCapture.ajax_url);
                                
                                $.ajax({
                                    url: cvTicketCapture.ajax_url,
                                    type: 'POST',
                                    data: formData,
                                    processData: false,
                                    contentType: false,
                                    success: function(response) {
                                        debug('✅ AJAX success');
                                        debug('Response type: ' + typeof response);
                                        try {
                                            var respStr = JSON.stringify(response);
                                            debug('Respuesta: ' + respStr.substring(0, 150));
                                        } catch(e) {
                                            debug('Response: ' + response);
                                        }
                                        
                                        if (response && response.success) {
                                            CVCapture.photoId = response.data.photo_id;
                                            debug('✅ Foto subida ID: ' + CVCapture.photoId);
                                            CVCapture.showForm(response.data.photo_url);
                                        } else {
                                            var msg = (response && response.data && response.data.message) ? response.data.message : 'Error desconocido';
                                            debug('❌ Error: ' + msg);
                                            alert(msg);
                                            $('#cv-confirm-photo').prop('disabled', false).text('Siguiente');
                                        }
                                    },
                                    error: function(xhr, status, error) {
                                        debug('❌ AJAX ERROR');
                                        debug('Status: ' + status);
                                        debug('Error: ' + error);
                                        debug('HTTP Code: ' + xhr.status);
                                        debug('Response Text: ' + (xhr.responseText ? xhr.responseText.substring(0, 200) : 'vacío'));
                                        alert('Error de conexión: ' + status);
                                        $('#cv-confirm-photo').prop('disabled', false).text('Siguiente');
                                    }
                                });
                            },
                            
                            showForm: function(photoUrl) {
                                debug('📋 Mostrando formulario de importe...');
                                
                                $('#cv-camera-capture').fadeOut(function() {
                                    $('#cv-review-title').text('Revisar y Enviar');
                                    $('#cv-store-label').text('Comercio:');
                                    $('#cv-store-name').text('<?php echo esc_js($comercio); ?>');
                                    $('#cv-ticket-preview').attr('src', photoUrl);
                                    $('#cv-amount-label').text('Importe del Ticket (€)');
                                    $('#cv-ticket-amount').attr('placeholder', 'Ej: 25.50');
                                    $('#cv-submit-ticket').text('Enviar Ticket');
                                    $('#cv-cancel-form').text('Cancelar');
                                    $('#cv-ticket-form').fadeIn();
                                });
                            },
                            
                            submitTicket: function() {
                                debug('📨 Preparando envío de ticket...');
                                var amount = parseFloat($('#cv-ticket-amount').val());
                                
                                if (isNaN(amount) || amount <= 0) {
                                    alert('Introduce un importe válido');
                                    return;
                                }
                                
                                // Guardar datos para usarlos después
                                CVCapture.submittedAmount = amount;
                                
                                // Verificar si el usuario está logueado
                                var isLoggedIn = <?php echo is_user_logged_in() ? 'true' : 'false'; ?>;
                                
                                if (!isLoggedIn) {
                                    // Ocultar formulario y mostrar auth
                                    debug('👤 Usuario NO logueado - Solicitando autenticación');
                                    $('#cv-ticket-form').fadeOut(function() {
                                        CVCapture.showAuthForm();
                                    });
                                } else {
                                    // Usuario ya logueado, enviar directamente
                                    debug('✅ Usuario logueado - Enviando ticket');
                                    CVCapture.sendTicketToServer();
                                }
                            },
                            
                            showAuthForm: function() {
                                debug('🔐 Mostrando formulario de autenticación...');
                                
                                $('#cv-auth-mode-login').show();
                                $('#cv-auth-mode-register').hide();
                                $('#cv-tab-login').addClass('active');
                                $('#cv-tab-register').removeClass('active');
                                $('#cv-auth-form').fadeIn(function() {
                                    debug('✅ Formulario AUTH visible');
                                    debug('Login button exists: ' + ($('#cv-do-login').length > 0 ? 'YES' : 'NO'));
                                    debug('Login button visible: ' + ($('#cv-do-login').is(':visible') ? 'YES' : 'NO'));
                                });
                            },
                            
                            sendTicketToServer: function() {
                                debug('📨 Enviando ticket al servidor...');
                                debug('Importe: ' + CVCapture.submittedAmount);
                                debug('Photo ID: ' + CVCapture.photoId);
                                debug('Vendor ID: ' + cvTicketCapture.vendor_id);
                                
                                var $btn = $('#cv-submit-ticket').length ? $('#cv-submit-ticket') : $('#cv-do-login, #cv-do-register').filter(':visible');
                                $btn.prop('disabled', true).text('Enviando...');
                                
                                $.ajax({
                                    url: cvTicketCapture.ajax_url,
                                    type: 'POST',
                                    data: {
                                        action: 'cv_submit_ticket',
                                        nonce: cvTicketCapture.nonce,
                                        amount: CVCapture.submittedAmount,
                                        vendor_id: cvTicketCapture.vendor_id,
                                        photo_id: CVCapture.photoId,
                                        vendor_name: '<?php echo esc_js($comercio); ?>'
                                    },
                                    success: function(response) {
                                        debug('✅ AJAX Submit success');
                                        debug('Response: ' + JSON.stringify(response).substring(0, 150));
                                        
                                        if (response && response.success) {
                                            debug('✅ Ticket enviado!');
                                            CVCapture.showSuccess();
                                        } else {
                                            var msg = (response && response.data && response.data.message) ? response.data.message : 'Error desconocido';
                                            debug('❌ Error: ' + msg);
                                            alert(msg);
                                            $btn.prop('disabled', false).text('Enviar');
                                        }
                                    },
                                    error: function(xhr, status, error) {
                                        debug('❌ AJAX Submit ERROR');
                                        debug('Status: ' + status);
                                        debug('Error: ' + error);
                                        debug('HTTP: ' + xhr.status);
                                        debug('Text: ' + (xhr.responseText ? xhr.responseText.substring(0, 200) : 'vacío'));
                                        alert('Error de conexión: ' + status);
                                        $btn.prop('disabled', false).text('Enviar');
                                    }
                                });
                            },
                            
                            showSuccess: function() {
                                debug('🎉 Mostrando pantalla de éxito...');
                                
                                // Ocultar header y formulario
                                $('#cv-capture-page-header').fadeOut(200);
                                $('#cv-ticket-form').fadeOut(300, function() {
                                    // Llenar datos de la pantalla de éxito
                                    $('#cv-success-store').text('<?php echo esc_js($comercio); ?>');
                                    $('#cv-success-photo').attr('src', $('#cv-ticket-preview').attr('src'));
                                    $('#cv-success-amount').text(CVCapture.submittedAmount.toFixed(2) + ' €');
                                    
                                    // Mostrar pantalla de éxito
                                    $('#cv-success-screen').fadeIn(400);
                                });
                            },
                            
                            showValidationPending: function(phone) {
                                debug('📱 Mostrando pantalla de validación pendiente...');
                                
                                // Ocultar header
                                $('#cv-capture-page-header').fadeOut(200);
                                
                                // Mostrar pantalla de validación
                                $('#cv-validation-phone').text(phone);
                                $('#cv-validation-screen').fadeIn(400);
                            },
                            
                            doLogin: function() {
                                debug('🔐 Procesando login...');
                                
                                var username = $('#cv-login-username').val().trim();
                                var password = $('#cv-login-password').val();
                                
                                if (!username || !password) {
                                    alert('Por favor introduce usuario y contraseña');
                                    return;
                                }
                                
                                $('#cv-do-login').prop('disabled', true).text('Iniciando sesión...');
                                
                                debug('🌐 AJAX URL: ' + cvTicketCapture.ajax_url);
                                debug('🌐 Username: ' + username);
                                
                                // Usar admin-ajax.php (más compatible)
                                $.ajax({
                                    url: cvTicketCapture.ajax_url,
                                    type: 'POST',
                                    data: {
                                        action: 'cv_ticket_login',
                                        nonce: cvTicketCapture.nonce,
                                        username: username,
                                        password: password
                                    },
                                    success: function(response) {
                                        debug('✅ Login response via AJAX');
                                        debug(response);
                                        
                                        if (response && response.success) {
                                            debug('✅ Login exitoso - Enviando ticket');
                                            // Ocultar auth y enviar ticket directamente
                                            $('#cv-auth-form').fadeOut(function() {
                                                CVCapture.sendTicketToServer();
                                            });
                                        } else {
                                            var msg = response.message || 'Error de login';
                                            debug('❌ Error login: ' + msg);
                                            alert(msg);
                                            $('#cv-do-login').prop('disabled', false).text('Iniciar Sesión');
                                        }
                                    },
                                    error: function(xhr, status, error) {
                                        debug('❌ Error AJAX login');
                                        debug('Status: ' + status);
                                        debug('Error: ' + error);
                                        debug('XHR Status Code: ' + xhr.status);
                                        debug('Response Text: ' + xhr.responseText);
                                        debug('Response JSON: ' + JSON.stringify(xhr.responseJSON));
                                        debug('Headers: ' + xhr.getAllResponseHeaders());
                                        
                                        var errorMsg = 'Error de conexión';
                                        
                                        // Intentar parsear respuesta JSON
                                        try {
                                            if (xhr.responseJSON) {
                                                if (xhr.responseJSON.message) {
                                                    errorMsg = xhr.responseJSON.message;
                                                } else if (xhr.responseJSON.code) {
                                                    errorMsg = 'Error: ' + xhr.responseJSON.code;
                                                }
                                            } else if (xhr.responseText) {
                                                // Intentar parsear manualmente
                                                var parsed = JSON.parse(xhr.responseText);
                                                if (parsed.message) {
                                                    errorMsg = parsed.message;
                                                }
                                            }
                                        } catch(e) {
                                            debug('No se pudo parsear JSON: ' + e.message);
                                            errorMsg = 'Error de servidor (HTTP ' + xhr.status + ')';
                                        }
                                        
                                        alert(errorMsg);
                                        $('#cv-do-login').prop('disabled', false).text('Iniciar Sesión');
                                    }
                                });
                            },
                            
                            doRegister: function() {
                                debug('📝 Procesando registro...');
                                
                                var phone = $('#cv-register-phone').val().trim();
                                var name = $('#cv-register-name').val().trim();
                                var email = $('#cv-register-email').val().trim();
                                
                                if (!phone || !name) {
                                    alert('Por favor introduce teléfono y nombre');
                                    return;
                                }
                                
                                // Validar formato de teléfono básico
                                if (phone.length < 9) {
                                    alert('El teléfono debe tener al menos 9 dígitos');
                                    return;
                                }
                                
                                debug('Teléfono: ' + phone);
                                debug('Nombre: ' + name);
                                debug('Email: ' + (email || 'no proporcionado'));
                                
                                $('#cv-do-register').prop('disabled', true).text('Registrando...');
                                
                                $.ajax({
                                    url: cvTicketCapture.ajax_url,
                                    type: 'POST',
                                    data: {
                                        action: 'cv_ticket_register',
                                        nonce: cvTicketCapture.nonce,
                                        phone: phone,
                                        name: name,
                                        email: email,
                                        photo_id: CVCapture.photoId,
                                        vendor_id: cvTicketCapture.vendor_id
                                    },
                                    success: function(response) {
                                        debug('✅ Register response');
                                        
                                        if (response && response.success) {
                                            debug('✅ Registro exitoso - User ID: ' + response.data.user_id);
                                            debug('📱 WhatsApp enviado: ' + (response.data.whatsapp_sent ? 'SÍ' : 'NO'));
                                            
                                            // Mostrar mensaje de validación pendiente
                                            $('#cv-auth-form').fadeOut(function() {
                                                CVCapture.showValidationPending(response.data.phone);
                                            });
                                        } else {
                                            var msg = (response && response.data && response.data.message) ? response.data.message : 'Error de registro';
                                            debug('❌ Error registro: ' + msg);
                                            alert(msg);
                                            $('#cv-do-register').prop('disabled', false).text('✨ Registrarse Gratis');
                                        }
                                    },
                                    error: function(xhr, status, error) {
                                        debug('❌ Error AJAX registro');
                                        alert('Error de conexión');
                                        $('#cv-do-register').prop('disabled', false).text('Registrarse y Continuar');
                                    }
                                });
                            }
                        };
                        
                        // VINCULAR EVENTOS
                        $('#cv-capture-photo').off('click').on('click', function(e) {
                            e.preventDefault();
                            debug('📷 CLICK CAPTURAR');
                            CVCapture.capturePhoto();
                        });
                        
                        $('#cv-retake-photo').off('click').on('click', function(e) {
                            e.preventDefault();
                            debug('🔄 CLICK REPETIR');
                            CVCapture.retakePhoto();
                        });
                        
                        $('#cv-confirm-photo').off('click').on('click', function(e) {
                            e.preventDefault();
                            debug('✅ CLICK CONFIRMAR');
                            CVCapture.uploadPhoto();
                        });
                        
                        $('#cv-cancel-capture').off('click').on('click', function(e) {
                            e.preventDefault();
                            debug('❌ CLICK CANCELAR');
                            window.history.back();
                        });
                        
                        $('#cv-submit-ticket').off('click').on('click', function(e) {
                            e.preventDefault();
                            debug('📤 CLICK ENVIAR');
                            CVCapture.submitTicket();
                        });
                        
                        $('#cv-cancel-form').off('click').on('click', function(e) {
                            e.preventDefault();
                            debug('❌ CLICK CANCELAR FORM');
                            window.history.back();
                        });
                        
                        // Botón Aceptar en pantalla de éxito
                        $('#cv-success-ok').off('click').on('click', function(e) {
                            e.preventDefault();
                            debug('✅ CLICK ACEPTAR - Ir a inicio');
                            window.location.href = '<?php echo esc_js(home_url('/')); ?>';
                        });
                        
                        // Botón Entendido en pantalla de validación
                        $('#cv-validation-ok').off('click').on('click', function(e) {
                            e.preventDefault();
                            debug('📱 CLICK ENTENDIDO - Ir a inicio');
                            window.location.href = '<?php echo esc_js(home_url('/')); ?>';
                        });
                        
                        // Tabs de Login/Registro (delegación de eventos)
                        $(document).off('click', '#cv-tab-login').on('click', '#cv-tab-login', function(e) {
                            e.preventDefault();
                            debug('🔄 Cambiar a Login');
                            $('#cv-tab-login').addClass('active');
                            $('#cv-tab-register').removeClass('active');
                            $('#cv-auth-mode-login').show();
                            $('#cv-auth-mode-register').hide();
                        });
                        
                        $(document).off('click', '#cv-tab-register').on('click', '#cv-tab-register', function(e) {
                            e.preventDefault();
                            debug('🔄 Cambiar a Registro');
                            $('#cv-tab-register').addClass('active');
                            $('#cv-tab-login').removeClass('active');
                            $('#cv-auth-mode-register').show();
                            $('#cv-auth-mode-login').hide();
                        });
                        
                        // Botón Login (delegación de eventos)
                        $(document).off('click', '#cv-do-login').on('click', '#cv-do-login', function(e) {
                            e.preventDefault();
                            debug('🔐 CLICK LOGIN BUTTON');
                            debug('Target: ' + e.target.id);
                            debug('Current target: ' + e.currentTarget.id);
                            CVCapture.doLogin();
                        });
                        
                        // Botón Registro (delegación de eventos)
                        $(document).off('click', '#cv-do-register').on('click', '#cv-do-register', function(e) {
                            e.preventDefault();
                            debug('📝 CLICK REGISTRO');
                            CVCapture.doRegister();
                        });
                        
                        // Botones Cancelar en auth (delegación de eventos)
                        $(document).off('click', '#cv-cancel-auth, #cv-cancel-auth-register').on('click', '#cv-cancel-auth, #cv-cancel-auth-register', function(e) {
                            e.preventDefault();
                            debug('❌ CLICK CANCELAR AUTH');
                            window.history.back();
                        });
                        
                        debug('✅ Eventos vinculados');
                        
                        // Si vendor_id no está disponible desde PHP, intentar desde localStorage
                        if (!cvTicketCapture.vendor_id || cvTicketCapture.vendor_id == 0) {
                            var storedVendorId = localStorage.getItem('cv_ticket_vendor_id');
                            if (storedVendorId) {
                                debug('💾 Recuperando vendor_id desde localStorage: ' + storedVendorId);
                                cvTicketCapture.vendor_id = parseInt(storedVendorId);
                            }
                        }
                        
                        debug('📊 Vendor ID disponible: ' + cvTicketCapture.vendor_id);
                        debug('📊 AJAX URL: ' + cvTicketCapture.ajax_url);
                        debug('📊 REST URL: ' + cvTicketCapture.rest_url);
                        
                        // Inicializar cámara
                        CVCapture.initCamera();
                        }); // Fin fadeIn
                    }, 800); // Fin setTimeout para esperar liberación de cámara (800ms)
                }, 500); // Fin setTimeout inicial
            });
            </script>
            
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets() {
        // Cargar solo en la página de captura de tickets
        if (!is_page('captura-tu-ticket')) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'cv-ticket-capture',
            CV_COMMISSIONS_PLUGIN_URL . 'assets/css/ticket-capture.css',
            array(),
            CV_COMMISSIONS_VERSION
        );
        
        // JavaScript principal
        wp_enqueue_script(
            'cv-ticket-capture',
            CV_COMMISSIONS_PLUGIN_URL . 'assets/js/ticket-capture.js',
            array('jquery'),
            CV_COMMISSIONS_VERSION,
            true
        );
        
        // Localizar script
        wp_localize_script('cv-ticket-capture', 'cvTicketCapture', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cv_ticket_capture_nonce'),
            'strings' => array(
                'camera_title' => __('Permisos de Cámara', 'cv-commissions'),
                'camera_instruction' => __('Para capturar el ticket, necesitamos acceso a tu cámara.', 'cv-commissions'),
                'camera_steps' => array(
                    __('1. El navegador te pedirá permiso para usar la cámara', 'cv-commissions'),
                    __('2. Haz clic en "Permitir" cuando aparezca el mensaje', 'cv-commissions'),
                    __('3. Apunta la cámara al ticket y toma la foto', 'cv-commissions'),
                    __('4. Verifica que la foto sea legible antes de continuar', 'cv-commissions'),
                ),
                'permissions_ok' => __('Entiendo, tengo los permisos', 'cv-commissions'),
                'btn_next' => __('Siguiente', 'cv-commissions'),
                'btn_cancel' => __('Cancelar', 'cv-commissions'),
                'btn_retake' => __('Repetir Foto', 'cv-commissions'),
                'btn_send' => __('Enviar Ticket', 'cv-commissions'),
                'capture_title' => __('Capturar Ticket', 'cv-commissions'),
                'review_title' => __('Revisar y Enviar', 'cv-commissions'),
                'amount_label' => __('Importe del Ticket (€)', 'cv-commissions'),
                'amount_placeholder' => __('Ej: 25.50', 'cv-commissions'),
                'store_label' => __('Comercio:', 'cv-commissions'),
                'error_no_vendor' => __('Error: No se detectó el comercio.', 'cv-commissions'),
                'error_camera' => __('Error al acceder a la cámara. Verifica los permisos.', 'cv-commissions'),
                'error_upload' => __('Error al subir la foto. Inténtalo de nuevo.', 'cv-commissions'),
                'error_amount' => __('Por favor, introduce un importe válido.', 'cv-commissions'),
                'success' => __('¡Ticket enviado correctamente!', 'cv-commissions'),
            ),
        ));
    }
    
    /**
     * Obtener HTML del formulario de captura
     */
    private function get_capture_html() {
        ob_start();
        ?>
        <div id="cv-ticket-capture-wrapper" style="display:none;">
            <!-- Interfaz de captura de foto -->
            <div id="cv-camera-capture" class="cv-camera-interface" style="display:none;">
                <div class="cv-camera-container">
                    <h2 id="cv-capture-title"></h2>
                    <video id="cv-camera-video" autoplay playsinline></video>
                    <canvas id="cv-camera-canvas" style="display:none;"></canvas>
                    <img id="cv-captured-image" style="display:none;">
                    <div class="cv-camera-controls">
                        <button id="cv-capture-photo" class="cv-btn cv-btn-primary" style="display:inline-block;">📷 Capturar</button>
                        <button id="cv-retake-photo" class="cv-btn cv-btn-secondary" style="display:none !important; visibility:hidden !important; width:0; height:0; padding:0; margin:0; border:0; overflow:hidden;"></button>
                        <button id="cv-confirm-photo" class="cv-btn cv-btn-primary" style="display:none !important; visibility:hidden !important; width:0; height:0; padding:0; margin:0; border:0; overflow:hidden;"></button>
                        <button id="cv-cancel-capture" class="cv-btn cv-btn-danger" style="display:inline-block;">Cancelar</button>
                    </div>
                </div>
            </div>
            
            <!-- Formulario de Login/Registro -->
            <div id="cv-auth-form" class="cv-auth-form" style="display:none;">
                <div class="cv-form-container">
                    <h2>🎉 ¡Únete a Ciudad Virtual!</h2>
                    
                    <!-- Mensaje de bienvenida y beneficios -->
                    <div class="cv-auth-welcome">
                        <p class="cv-auth-intro">Para continuar, inicia sesión o regístrate gratis:</p>
                        <div class="cv-auth-benefits">
                            <div class="cv-benefit-item">
                                <span class="cv-benefit-icon">💰</span>
                                <span>Acumula descuentos</span>
                            </div>
                            <div class="cv-benefit-item">
                                <span class="cv-benefit-icon">🎁</span>
                                <span>Promociones exclusivas</span>
                            </div>
                            <div class="cv-benefit-item">
                                <span class="cv-benefit-icon">📊</span>
                                <span>Controla tus tickets</span>
                            </div>
                            <div class="cv-benefit-item">
                                <span class="cv-benefit-icon">🏆</span>
                                <span>Acceso a la plataforma</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Botones de selección Login/Registro -->
                    <div class="cv-auth-tabs">
                        <button id="cv-tab-login" class="cv-auth-tab active">Iniciar Sesión</button>
                        <button id="cv-tab-register" class="cv-auth-tab">Registrarse Gratis</button>
                    </div>
                    
                    <!-- Modo Login -->
                    <div id="cv-auth-mode-login" class="cv-auth-mode">
                        <div class="cv-auth-field">
                            <label for="cv-login-username">Usuario o Teléfono</label>
                            <input type="text" id="cv-login-username" placeholder="Ej: 612345678" autocomplete="username">
                        </div>
                        <div class="cv-auth-field">
                            <label for="cv-login-password">Contraseña</label>
                            <input type="password" id="cv-login-password" placeholder="Tu contraseña" autocomplete="current-password">
                        </div>
                        <div class="cv-form-actions">
                            <button id="cv-do-login" class="cv-btn cv-btn-primary">Iniciar Sesión</button>
                            <button id="cv-cancel-auth" class="cv-btn cv-btn-danger">Cancelar</button>
                        </div>
                    </div>
                    
                    <!-- Modo Registro -->
                    <div id="cv-auth-mode-register" class="cv-auth-mode" style="display:none;">
                        <div class="cv-register-info">
                            <p><strong>📱 Registro rápido con WhatsApp</strong></p>
                            <p class="cv-info-text">Recibirás tu contraseña por WhatsApp y podrás acceder a todos los beneficios de la plataforma</p>
                        </div>
                        
                        <div class="cv-auth-field">
                            <label for="cv-register-phone">Teléfono WhatsApp <span class="required">*</span></label>
                            <input type="tel" id="cv-register-phone" placeholder="Ej: 612345678" required autocomplete="tel">
                            <small>Se usará como nombre de usuario</small>
                        </div>
                        <div class="cv-auth-field">
                            <label for="cv-register-name">Nombre Completo <span class="required">*</span></label>
                            <input type="text" id="cv-register-name" placeholder="Tu nombre" required autocomplete="name">
                        </div>
                        <div class="cv-auth-field">
                            <label for="cv-register-email">Email (opcional)</label>
                            <input type="email" id="cv-register-email" placeholder="tu@email.com" autocomplete="email">
                            <small>Para recibir notificaciones y promociones</small>
                        </div>
                        <div class="cv-form-actions">
                            <button id="cv-do-register" class="cv-btn cv-btn-success">✨ Registrarse Gratis</button>
                            <button id="cv-cancel-auth-register" class="cv-btn cv-btn-danger">Cancelar</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Formulario de resumen y envío -->
            <div id="cv-ticket-form" class="cv-ticket-form" style="display:none;">
                <div class="cv-form-container">
                    <h2 id="cv-review-title"></h2>
                    <div class="cv-ticket-summary">
                        <div class="cv-summary-item">
                            <strong id="cv-store-label"></strong>
                            <span id="cv-store-name"></span>
                        </div>
                        <div class="cv-summary-item">
                            <img id="cv-ticket-preview" src="" alt="Ticket">
                        </div>
                        <div class="cv-summary-item cv-amount-input">
                            <label for="cv-ticket-amount" id="cv-amount-label"></label>
                            <input type="number" id="cv-ticket-amount" step="0.01" min="0.01" placeholder="">
                        </div>
                    </div>
                    <div class="cv-form-actions">
                        <button id="cv-submit-ticket" class="cv-btn cv-btn-success"></button>
                        <button id="cv-cancel-form" class="cv-btn cv-btn-danger"></button>
                    </div>
                </div>
            </div>
            
            <!-- Pantalla de validación pendiente -->
            <div id="cv-validation-screen" class="cv-validation-screen" style="display:none;">
                <div class="cv-validation-container">
                    <div class="cv-validation-icon">📱</div>
                    <h2>¡Cuenta creada!</h2>
                    <p class="cv-validation-message">Revisa tu WhatsApp</p>
                    
                    <div class="cv-validation-info">
                        <p><strong>Hemos enviado un mensaje al:</strong></p>
                        <p class="cv-validation-phone-display">
                            <span class="wcfmfa fa-whatsapp"></span>
                            <span id="cv-validation-phone"></span>
                        </p>
                        
                        <div class="cv-validation-steps">
                            <div class="cv-validation-step">
                                <span class="cv-step-number">1</span>
                                <span class="cv-step-text">Revisa tu WhatsApp</span>
                            </div>
                            <div class="cv-validation-step">
                                <span class="cv-step-number">2</span>
                                <span class="cv-step-text">Haz clic en el enlace</span>
                            </div>
                            <div class="cv-validation-step">
                                <span class="cv-step-number">3</span>
                                <span class="cv-step-text">Valida tu cuenta</span>
                            </div>
                        </div>
                        
                        <p class="cv-validation-note">
                            <span class="wcfmfa fa-info-circle"></span>
                            Recibirás tu contraseña de acceso y un enlace de validación. El enlace es válido por 24 horas.
                        </p>
                        
                        <p class="cv-validation-benefits">
                            <strong>✨ Una vez validado podrás:</strong><br>
                            💰 Acumular descuentos<br>
                            🎁 Recibir promociones exclusivas<br>
                            📊 Controlar tus tickets<br>
                            🏆 Acceder a toda la plataforma
                        </p>
                    </div>
                    
                    <button id="cv-validation-ok" class="cv-btn cv-btn-primary cv-btn-large">Entendido</button>
                </div>
            </div>
            
            <!-- Pantalla de confirmación/éxito -->
            <div id="cv-success-screen" class="cv-success-screen" style="display:none;">
                <div class="cv-success-container">
                    <div class="cv-success-icon">✅</div>
                    <h2 id="cv-success-title">¡Gracias!</h2>
                    <p id="cv-success-message">Tu ticket ha sido enviado correctamente</p>
                    
                    <div class="cv-success-summary">
                        <div class="cv-success-item">
                            <strong>Comercio:</strong>
                            <span id="cv-success-store"></span>
                        </div>
                        <div class="cv-success-item">
                            <img id="cv-success-photo" src="" alt="Ticket">
                        </div>
                        <div class="cv-success-item cv-success-amount">
                            <strong>Importe:</strong>
                            <span id="cv-success-amount"></span>
                        </div>
                    </div>
                    
                    <button id="cv-success-ok" class="cv-btn cv-btn-primary cv-btn-large">Aceptar</button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Subir foto del ticket
     */
    public function upload_ticket_photo() {
        $debug = get_option('cv_ticket_debug_mode', false);
        
        if ($debug) {
            error_log('CVTicket Upload: ========== INICIO ==========');
            error_log('CVTicket Upload: POST: ' . print_r($_POST, true));
            error_log('CVTicket Upload: FILES: ' . print_r($_FILES, true));
        }
        
        check_ajax_referer('cv_ticket_capture_nonce', 'nonce');
        
        if (!isset($_FILES['ticket_photo'])) {
            error_log('CVTicket Upload: ❌ No se recibió archivo');
            wp_send_json_error(array('message' => 'No se recibió la foto'));
        }
        
        // Obtener vendor_id del POST (más confiable que sesión)
        $vendor_id = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : 0;
        
        // Si no viene por POST, intentar obtenerlo de sesión
        if (empty($vendor_id)) {
            if (!session_id()) {
                session_start();
            }
            $vendor_id = isset($_SESSION['cv_ticket_vendor_id']) ? intval($_SESSION['cv_ticket_vendor_id']) : 0;
        }
        
        if (empty($vendor_id)) {
            if ($debug) {
                error_log('CVTicket Upload: ❌ Vendor ID vacío');
            }
            wp_send_json_error(array('message' => 'Datos incompletos'));
        }
        
        if ($debug) {
            error_log('CVTicket Upload: ✅ Vendor ID válido: ' . $vendor_id);
        }
        
        // Guardar en sesión para el siguiente paso
        if (!session_id()) {
            session_start();
        }
        $_SESSION['cv_ticket_vendor_id'] = $vendor_id;
        
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        // Subir la imagen
        $attachment_id = media_handle_upload('ticket_photo', 0);
        
        if (is_wp_error($attachment_id)) {
            wp_send_json_error(array('message' => 'Error al subir la imagen: ' . $attachment_id->get_error_message()));
        }
        
        // Guardar el attachment_id en sesión
        $_SESSION['cv_ticket_photo_id'] = $attachment_id;
        
        $photo_url = wp_get_attachment_url($attachment_id);
        
        wp_send_json_success(array(
            'photo_id' => $attachment_id,
            'photo_url' => $photo_url,
        ));
    }
    
    /**
     * Enviar ticket completo
     */
    public function submit_ticket() {
        // Iniciar captura de output
        ob_start();
        
        $debug = get_option('cv_ticket_debug_mode', false);
        
        if ($debug) {
            error_log('CVTicket Submit: ========== INICIO SUBMIT ==========');
            error_log('CVTicket Submit: POST: ' . print_r($_POST, true));
            error_log('CVTicket Submit: User logged in: ' . (is_user_logged_in() ? 'YES' : 'NO'));
        }
        
        // Verificar nonce solo si está presente
        if (isset($_POST['nonce']) && !empty($_POST['nonce'])) {
            $nonce_check = check_ajax_referer('cv_ticket_capture_nonce', 'nonce', false);
            if (!$nonce_check) {
                if ($debug) {
                    error_log('CVTicket Submit: Nonce inválido, continuando sin verificación');
                }
                // Continuar sin nonce si el usuario está logueado
                if (!is_user_logged_in()) {
                    ob_end_clean();
                    wp_send_json_error(array('message' => 'Sesión expirada, por favor recarga la página'));
                    return;
                }
            }
        }
        
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        
        if ($amount <= 0) {
            ob_end_clean();
            wp_send_json_error(array('message' => 'Importe no válido'));
            return;
        }
        
        // Obtener datos del POST primero, luego de sesión
        $vendor_id = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : 0;
        $photo_id = isset($_POST['photo_id']) ? intval($_POST['photo_id']) : 0;
        
        // Si no vienen por POST, intentar sesión
        if (empty($vendor_id) || empty($photo_id)) {
            if (!session_id()) {
                session_start();
            }
            
            if (empty($vendor_id)) {
                $vendor_id = isset($_SESSION['cv_ticket_vendor_id']) ? intval($_SESSION['cv_ticket_vendor_id']) : 0;
            }
            if (empty($photo_id)) {
                $photo_id = isset($_SESSION['cv_ticket_photo_id']) ? intval($_SESSION['cv_ticket_photo_id']) : 0;
            }
        }
        
        $vendor_name = isset($_POST['vendor_name']) ? sanitize_text_field($_POST['vendor_name']) : '';
        if (empty($vendor_name)) {
            if (!session_id()) {
                session_start();
            }
            $vendor_name = isset($_SESSION['cv_ticket_vendor_name']) ? $_SESSION['cv_ticket_vendor_name'] : '';
        }
        
        if ($debug) {
            error_log('CVTicket Submit: Vendor ID: ' . $vendor_id);
            error_log('CVTicket Submit: Photo ID: ' . $photo_id);
            error_log('CVTicket Submit: Vendor Name: ' . $vendor_name);
        }
        
        if (empty($vendor_id) || empty($photo_id)) {
            if ($debug) {
                error_log('CVTicket Submit: Vendor ID: ' . $vendor_id);
                error_log('CVTicket Submit: Photo ID: ' . $photo_id);
            }
            ob_end_clean();
            wp_send_json_error(array('message' => 'Datos incompletos'));
            return;
        }
        
        // Obtener usuario actual
        $user_id = get_current_user_id();
        $user_email = $user_id ? wp_get_current_user()->user_email : '';
        $user_phone = $user_id ? get_user_meta($user_id, 'billing_phone', true) : '';
        
        // Crear tabla si no existe
        $this->create_tickets_table();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cv_tickets';
        
        $insert_data = array(
            'vendor_id' => $vendor_id,
            'customer_id' => $user_id,
            'customer_email' => $user_email,
            'customer_phone' => $user_phone,
            'ticket_photo_id' => $photo_id,
            'amount' => $amount,
            'status' => 'pending',
            'created_at' => current_time('mysql'),
        );
        
        $result = $wpdb->insert($table_name, $insert_data);
        
        if ($result === false) {
            if ($debug) {
                error_log('CVTicket Submit: Error al insertar en base de datos');
                error_log('CVTicket Submit: WP DB Last Error: ' . $wpdb->last_error);
            }
            ob_end_clean();
            wp_send_json_error(array('message' => 'Error al guardar el ticket'));
            return;
        }
        
        $ticket_id = $wpdb->insert_id;
        
        // Limpiar sesión
        unset($_SESSION['cv_ticket_vendor_id']);
        unset($_SESSION['cv_ticket_vendor_name']);
        unset($_SESSION['cv_ticket_vendor_code']);
        unset($_SESSION['cv_ticket_photo_id']);
        
        // Notificar al vendedor
        error_log('CVTicket: 🚀 DISPARANDO HOOK cv_ticket_submitted - Ticket: ' . $ticket_id . ' Vendor: ' . $vendor_id . ' User: ' . $user_id . ' Amount: ' . $amount);
        do_action('cv_ticket_submitted', $ticket_id, $vendor_id, $user_id, $amount);
        error_log('CVTicket: ✅ Hook cv_ticket_submitted completado');
        
        // Limpiar output antes de respuesta exitosa
        $output = ob_get_contents();
        if (!empty($output) && $debug) {
            error_log('CVTicket Submit: Output capturado: ' . $output);
        }
        ob_end_clean();
        
        wp_send_json_success(array(
            'message' => '¡Ticket enviado correctamente al comercio!',
            'ticket_id' => $ticket_id,
        ));
    }
    
    /**
     * Crear tabla de tickets si no existe
     */
    private function create_tickets_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cv_tickets';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            vendor_id bigint(20) NOT NULL,
            customer_id bigint(20) DEFAULT 0,
            customer_email varchar(100) DEFAULT '',
            customer_phone varchar(20) DEFAULT '',
            ticket_photo_id bigint(20) NOT NULL,
            amount decimal(10,2) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY vendor_id (vendor_id),
            KEY customer_id (customer_id),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * AJAX: Login de usuario
     */
    public function ticket_login() {
        // Iniciar captura de output para evitar contaminar el JSON
        ob_start();
        
        // Permitir CORS y bypass de verificaciones de seguridad para este endpoint público
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Credentials: true');
        header('Content-Type: application/json; charset=utf-8');
        
        // Bypass de Wordfence y otros firewalls
        if (class_exists('wordfence')) {
            remove_action('init', array('wordfence', 'restOfInit'));
        }
        
        $debug = get_option('cv_ticket_debug_mode', false);
        
        if ($debug) {
            error_log('CVTicket Login: Iniciando proceso de login');
            error_log('CVTicket Login: POST data: ' . print_r($_POST, true));
            error_log('CVTicket Login: COOKIE data: ' . print_r($_COOKIE, true));
            error_log('CVTicket Login: User logged in: ' . (is_user_logged_in() ? 'YES' : 'NO'));
        }
        
        // Verificar que sea una petición AJAX válida
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            ob_end_clean();
            wp_send_json_error(array('message' => 'Petición inválida'));
            return;
        }
        
        $username = isset($_POST['username']) ? sanitize_text_field($_POST['username']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        if (empty($username) || empty($password)) {
            ob_end_clean();
            wp_send_json_error(array('message' => 'Usuario y contraseña requeridos'));
            return;
        }
        
        // Intentar login
        $creds = array(
            'user_login' => $username,
            'user_password' => $password,
            'remember' => true
        );
        
        $user = wp_signon($creds, false);
        
        if (is_wp_error($user)) {
            if ($debug) {
                error_log('CVTicket Login: Error - ' . $user->get_error_message());
            }
            ob_end_clean();
            wp_send_json_error(array('message' => 'Usuario o contraseña incorrectos'));
            return;
        }
        
        if ($debug) {
            error_log('CVTicket Login: Éxito - User ID: ' . $user->ID);
            $output = ob_get_contents();
            if (!empty($output)) {
                error_log('CVTicket Login: Output capturado: ' . $output);
            }
        }
        
        // Limpiar cualquier output capturado antes de enviar JSON
        ob_end_clean();
        
        wp_send_json_success(array(
            'message' => 'Login exitoso',
            'user_id' => $user->ID
        ));
    }
    
    /**
     * AJAX: Registro de usuario
     */
    public function ticket_register() {
        // Iniciar captura de output para evitar contaminar el JSON
        ob_start();
        
        // Permitir CORS y bypass de verificaciones de seguridad para este endpoint público
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Credentials: true');
        header('Content-Type: application/json; charset=utf-8');
        
        // Bypass de Wordfence y otros firewalls
        if (class_exists('wordfence')) {
            remove_action('init', array('wordfence', 'restOfInit'));
        }
        
        $debug = get_option('cv_ticket_debug_mode', false);
        
        if ($debug) {
            error_log('CVTicket Register: Iniciando proceso de registro');
            error_log('CVTicket Register: POST data: ' . print_r($_POST, true));
        }
        
        // Verificar que sea una petición AJAX válida
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            ob_end_clean();
            wp_send_json_error(array('message' => 'Petición inválida'));
            return;
        }
        
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $photo_id = isset($_POST['photo_id']) ? intval($_POST['photo_id']) : 0;
        $vendor_id = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : 0;
        
        if (empty($phone) || empty($name)) {
            ob_end_clean();
            wp_send_json_error(array('message' => 'Teléfono y nombre son obligatorios'));
            return;
        }
        
        // Limpiar teléfono (quitar espacios, guiones, etc.)
        $phone_clean = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($phone_clean) < 9) {
            ob_end_clean();
            wp_send_json_error(array('message' => 'El teléfono debe tener al menos 9 dígitos'));
            return;
        }
        
        // Verificar si el teléfono ya está registrado
        $existing_user = get_user_by('login', $phone_clean);
        if ($existing_user) {
            ob_end_clean();
            wp_send_json_error(array('message' => 'Este teléfono ya está registrado. Por favor inicia sesión.'));
            return;
        }
        
        // Si proporcionó email, verificar que no exista
        if (!empty($email)) {
            $existing_email = get_user_by('email', $email);
            if ($existing_email) {
                ob_end_clean();
                wp_send_json_error(array('message' => 'Este email ya está registrado'));
                return;
            }
        }
        
        // Generar contraseña aleatoria
        $password = wp_generate_password(12, false);
        
        // Preparar datos del usuario
        $userdata = array(
            'user_login' => $phone_clean,
            'user_pass' => $password,
            'display_name' => $name,
            'first_name' => $name,
            'role' => 'customer'
        );
        
        // Solo agregar email si se proporcionó
        if (!empty($email)) {
            $userdata['user_email'] = $email;
        } else {
            // Email ficticio usando el teléfono
            $userdata['user_email'] = $phone_clean . '@ticket.ciudadvirtual.app';
        }
        
        // Crear usuario
        $user_id = wp_insert_user($userdata);
        
        if (is_wp_error($user_id)) {
            if ($debug) {
                error_log('CVTicket Register: Error al crear usuario - ' . $user_id->get_error_message());
            }
            ob_end_clean();
            wp_send_json_error(array('message' => 'Error al crear la cuenta: ' . $user_id->get_error_message()));
            return;
        }
        
        // Guardar teléfono y datos como meta
        update_user_meta($user_id, 'billing_phone', $phone);
        update_user_meta($user_id, 'cv_whatsapp_phone', $phone);
        update_user_meta($user_id, 'cv_account_pending_validation', '1');
        update_user_meta($user_id, 'cv_validation_password', $password);
        
        // Generar token de validación único
        $validation_token = wp_generate_password(32, false, false);
        update_user_meta($user_id, 'cv_validation_token', $validation_token);
        update_user_meta($user_id, 'cv_validation_token_expires', time() + (24 * 60 * 60)); // Expira en 24 horas
        
        // Construir link de validación
        $validation_link = home_url('/validar-cuenta/?token=' . $validation_token . '&phone=' . urlencode($phone_clean));
        
        if ($debug) {
            error_log('CVTicket Register: Usuario creado - ID: ' . $user_id);
            error_log('CVTicket Register: Teléfono: ' . $phone);
            error_log('CVTicket Register: Email: ' . $userdata['user_email']);
            error_log('CVTicket Register: Link validación: ' . $validation_link);
        }
        
        // Enviar WhatsApp con Ultramsg
        $whatsapp_sent = $this->send_validation_whatsapp($phone, $name, $password, $validation_link);
        
        if ($debug) {
            error_log('CVTicket Register: WhatsApp enviado: ' . ($whatsapp_sent ? 'SÍ' : 'NO'));
        }
        
        // NO hacer auto-login - el usuario debe validar primero
        // Pero guardamos sus datos en sesión para el ticket
        if (!session_id()) {
            session_start();
        }
        $_SESSION['cv_pending_user'] = array(
            'user_id' => $user_id,
            'phone' => $phone,
            'photo_id' => $photo_id,
            'vendor_id' => $vendor_id
        );
        
        if ($debug) {
            $output = ob_get_contents();
            if (!empty($output)) {
                error_log('CVTicket Register: Output capturado: ' . $output);
            }
        }
        
        // Limpiar cualquier output capturado antes de enviar JSON
        ob_end_clean();
        
        wp_send_json_success(array(
            'message' => 'Registro exitoso',
            'user_id' => $user_id,
            'phone' => $phone,
            'whatsapp_sent' => $whatsapp_sent,
            'requires_validation' => true
        ));
    }
    
    /**
     * Enviar WhatsApp de validación con Ultramsg
     */
    private function send_validation_whatsapp($phone, $name, $password, $validation_link) {
        $debug = get_option('cv_ticket_debug_mode', false);
        
        // Obtener configuración de Ultramsg
        $ultramsg_instance = get_option('cv_ultramsg_instance', '');
        $ultramsg_token = get_option('cv_ultramsg_token', '');
        
        if (empty($ultramsg_instance) || empty($ultramsg_token)) {
            if ($debug) {
                error_log('CVTicket WhatsApp: Ultramsg no configurado');
            }
            return false;
        }
        
        // Limpiar teléfono para formato internacional
        $phone_clean = preg_replace('/[^0-9]/', '', $phone);
        
        // Si no empieza con código de país, agregar +34 (España)
        if (!preg_match('/^(34|0034|\+34)/', $phone_clean)) {
            $phone_international = '34' . $phone_clean;
        } else {
            $phone_international = preg_replace('/^(0034|\+34)/', '34', $phone_clean);
        }
        
        // Mensaje de WhatsApp - Links separados por líneas en blanco para que sean clicables
        $message = "🎉 *¡Bienvenido a Ciudad Virtual!*\n\n";
        $message .= "Hola *{$name}*,\n\n";
        $message .= "Tu cuenta ha sido creada exitosamente.\n\n";
        $message .= "📱 *Usuario:* {$phone}\n";
        $message .= "🔑 *Contraseña:* {$password}\n\n";
        $message .= "⚠️ *IMPORTANTE:* Para activar tu cuenta haz clic aquí:\n\n";
        $message .= "{$validation_link}\n\n"; // Link sin emojis ni texto alrededor
        $message .= "💰 *Beneficios de tu cuenta:*\n";
        $message .= "• Acumula descuentos\n";
        $message .= "• Promociones exclusivas\n";
        $message .= "• Control de tus tickets\n";
        $message .= "• Acceso completo a la plataforma\n\n";
        $message .= "⏰ Este enlace es válido por 24 horas.\n\n";
        $message .= "¡Gracias por confiar en Ciudad Virtual! 🏆";
        
        // API de Ultramsg
        $api_url = "https://api.ultramsg.com/{$ultramsg_instance}/messages/chat";
        
        $data = array(
            'token' => $ultramsg_token,
            'to' => $phone_international,
            'body' => $message,
            'priority' => 10
        );
        
        if ($debug) {
            error_log('CVTicket WhatsApp: Enviando a ' . $phone_international);
            error_log('CVTicket WhatsApp: URL: ' . $api_url);
        }
        
        // Enviar solicitud
        $response = wp_remote_post($api_url, array(
            'timeout' => 30,
            'body' => $data
        ));
        
        if (is_wp_error($response)) {
            if ($debug) {
                error_log('CVTicket WhatsApp: Error - ' . $response->get_error_message());
            }
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($debug) {
            error_log('CVTicket WhatsApp: Código HTTP: ' . $response_code);
            error_log('CVTicket WhatsApp: Respuesta: ' . $response_body);
        }
        
        if ($response_code == 200) {
            return true;
        }
        
        return false;
    }
}

// Inicializar
new CV_Ticket_Capture();
