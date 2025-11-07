<?php
/**
 * Integración con WooCommerce Wallet
 * 
 * Maneja el cálculo automático de comisiones para el monedero de Ciudad Virtual
 * 
 * @package CV_Commissions
 * @since 1.0.2
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase CV_Wallet_Integration
 * 
 * Integra el sistema de comisiones de CV con el plugin WooCommerce Wallet
 * calculando automáticamente el monto correcto a acreditar en el monedero
 * del comprador basado en las comisiones configuradas.
 */
class CV_Wallet_Integration {
    
    /**
     * Modo debug
     * 
     * @var bool
     */
    private $debug_mode = false;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
        
        // Activar debug desde configuración si existe
        if (defined('CV_WALLET_DEBUG') && CV_WALLET_DEBUG) {
            $this->debug_mode = true;
        }
    }
    
    /**
     * Inicializar hooks de WordPress
     */
    private function init_hooks() {
        // Hook para filtrar transacciones del monedero
        add_filter('woo_wallet_transactions_args', array($this, 'filter_wallet_transaction'), 10, 1);
    }
    
    /**
     * Filtrar argumentos de transacción del monedero
     * 
     * Este filtro se ejecuta antes de crear una transacción en el monedero.
     * Calcula el monto correcto basándose en las comisiones de CV configuradas
     * para el pedido específico.
     * 
     * @param array $args Argumentos de la transacción del wallet
     * @return array Argumentos modificados con el monto calculado
     */
    public function filter_wallet_transaction($args) {
        $this->log("=== FILTRO MONEDERO CV ACTIVADO ===");
        $this->log("Args originales: " . print_r($args, true));
        
        // Extraer ID del pedido desde los detalles
        $order_id = $this->extract_order_id_from_details($args);
        
        if (!$order_id) {
            $this->log("No se pudo extraer order_id, retornando args sin modificar");
            return $args;
        }
        
        $this->log("Order ID extraído: " . $order_id);
        
        // Verificar que el pedido existe
        $order = wc_get_order($order_id);
        if (!$order) {
            $this->log("Pedido no encontrado: " . $order_id);
            return $args;
        }
        
        // Calcular comisiones usando la función del plugin
        $comisiones = $this->calculate_commissions($order_id);
        
        if ($comisiones === false) {
            $this->log("Error al calcular comisiones, retornando args sin modificar");
            return $args;
        }
        
        $this->log("Comisiones calculadas: " . $comisiones);
        
        // Actualizar el monto de la transacción
        $args['amount'] = $comisiones;
        
        $this->log("Amount actualizado para el monedero: " . $args['amount']);
        $this->log("=== FIN FILTRO MONEDERO CV ===");
        
        return $args;
    }
    
    /**
     * Extraer ID del pedido desde los detalles
     * 
     * Los detalles suelen venir en formato: "Texto descriptivo #123"
     * donde 123 es el ID del pedido.
     * 
     * @param array $args Argumentos de la transacción
     * @return int|false ID del pedido o false si no se encuentra
     */
    private function extract_order_id_from_details($args) {
        if (empty($args['details'])) {
            $this->log("Campo 'details' vacío en args");
            return false;
        }
        
        $details = $args['details'];
        $this->log("Details recibidos: " . $details);
        
        // Buscar el símbolo # y extraer el número después
        $pos = strpos($details, '#');
        
        if ($pos === false) {
            $this->log("No se encontró símbolo '#' en details");
            return false;
        }
        
        // Extraer todo después del #
        $order_id_string = substr($details, $pos + 1);
        
        // Extraer solo números (por si hay más texto después)
        preg_match('/^\d+/', $order_id_string, $matches);
        
        if (empty($matches[0])) {
            $this->log("No se pudo extraer número después de '#'");
            return false;
        }
        
        return intval($matches[0]);
    }
    
    /**
     * Calcular comisiones para un pedido
     * 
     * Utiliza la función de compatibilidad calcula_total_comisiones()
     * que ya existe en el plugin.
     * 
     * @param int $order_id ID del pedido
     * @return float|false Monto de comisiones o false en caso de error
     */
    private function calculate_commissions($order_id) {
        try {
            // Verificar que la función existe
            if (!function_exists('calcula_total_comisiones')) {
                $this->log("ERROR: Función calcula_total_comisiones() no existe");
                return false;
            }
            
            // Calcular comisiones (segundo parámetro = order_id)
            $comisiones = calcula_total_comisiones(false, $order_id);
            
            $this->log("Resultado de calcula_total_comisiones: " . print_r($comisiones, true));
            
            // Convertir a float si es necesario
            if (is_numeric($comisiones)) {
                return floatval($comisiones);
            }
            
            // Si es un array, intentar obtener el valor del comprador
            if (is_array($comisiones) && isset($comisiones['comprador'])) {
                return floatval($comisiones['comprador']);
            }
            
            $this->log("Valor de comisiones no es numérico ni array válido");
            return false;
            
        } catch (Exception $e) {
            $this->log("EXCEPCIÓN al calcular comisiones: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log debug
     * 
     * @param string $message Mensaje a registrar
     */
    private function log($message) {
        if ($this->debug_mode) {
            error_log('[CV Wallet Integration] ' . $message);
        }
    }
    
    /**
     * Activar/desactivar modo debug
     * 
     * @param bool $enable True para activar, false para desactivar
     */
    public function set_debug_mode($enable) {
        $this->debug_mode = (bool)$enable;
    }
}





