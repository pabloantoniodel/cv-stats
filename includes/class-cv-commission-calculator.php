<?php
/**
 * Calculadora de Comisiones
 * Convierte las funciones del snippet original en una clase organizada
 */

if (!defined('ABSPATH')) {
    exit;
}

class CV_Commission_Calculator {
    
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
     * Calcular total de comisiones para carrito o pedido
     * Equivalente a: calcula_total_comisiones()
     */
    public function calculate_total_commissions($is_cart = true, $order_id = 0) {
        global $woocommerce;
        
        $commission_total = 0;
        
        if ($is_cart) {
            $items = $woocommerce->cart->get_cart();
            $order = new WC_Order($order_id);
        } else {
            $order = new WC_Order($order_id);
            global $WCFM;
            
            $is_order_for_vendor = $WCFM->wcfm_vendor_support->wcfm_is_order_for_vendor($order_id);
            $items = $order->get_items('line_item');
        }
        
        foreach ($items as $item) {
            // Obtener product ID
            if (isset($item->product_id)) {
                $product_id = $item->product_id;
            } else {
                $product_id = $item['product_id'];
            }
            
            // Obtener datos según sea carrito o pedido
            if ($is_cart) {
                $_product = wc_get_product($item['data']->get_id());
                $price = get_post_meta($item['product_id'], '_price', true); // Precio UNITARIO
                $commission = get_post_meta($item['product_id'], '_wcfmmp_commission', true);
                $quantity = $item['quantity'];
                $vendor_id = 3; // Default
                $vendor_data = get_user_meta($vendor_id, 'wcfmmp_profile_settings', true);
            } else {
                $_product = wc_get_product($product_id);
                // CORRECCIÓN DEL BUG: Usar precio unitario en lugar de subtotal
                // para evitar multiplicar por quantity dos veces
                
                // Producto especial (Ticket) usa 'total' según snippet original
                $special_product_id = $this->config['special_product_id'];
                if ($product_id == $special_product_id) {
                    $price = $item['total'] / $item['quantity']; // Precio unitario desde total
                } else {
                    $price = $_product->get_price(); // Precio UNITARIO (ej: 1.95)
                }
                
                $commission = get_post_meta($product_id, '_wcfmmp_commission', true);
                $quantity = $item['quantity']; // Cantidad real (ej: 40)
                $vendor_id = $this->get_vendor_from_order($order);
                $vendor_data = get_user_meta($vendor_id, 'wcfmmp_profile_settings', true);
            }
            
            // Calcular comisión del item
            $item_commission = $this->calculate_item_commission(
                $product_id,
                $price,
                $quantity,
                $commission,
                $vendor_data
            );
            
            $commission_total += $item_commission;
        }
        
        if ($this->config['enable_logging']) {
            error_log('CV Commissions: Total de comisión = ' . $commission_total);
        }
        
        return $commission_total;
    }
    
    /**
     * Calcular comisión de un item individual
     */
    private function calculate_item_commission($product_id, $price, $quantity, $commission, $vendor_data) {
        $special_product_id = $this->config['special_product_id'];
        $cashback_percent = $this->config['cashback_percent'];
        
        // Si el producto no tiene configuración de comisión
        if (empty($commission)) {
            if ($this->config['enable_logging']) {
                error_log('CV Commissions: Producto sin configuración de comisión: ' . $product_id);
            }
            
            return $this->calculate_commission_without_config(
                $product_id,
                $price,
                $quantity,
                $vendor_data
            );
        }
        
        // Producto tiene configuración de comisión
        if ($commission['commission_mode'] == 'percent') {
            // Comisión por porcentaje
            if ($product_id == $special_product_id) {
                // Producto especial (Ticket)
                return $this->calculate_special_product_commission(
                    $price,
                    $quantity,
                    $vendor_data
                );
            } else {
                // Producto normal con porcentaje
                $commission_amount = (floatval($quantity) * floatval($price)) - 
                    (floatval($quantity) * floatval($price) * floatval($commission['commission_percent']) / 100);
                
                return floatval($commission_amount * $cashback_percent / 100);
            }
        } else {
            // Comisión no es por porcentaje
            if ($product_id == $special_product_id) {
                // Ticket sin porcentaje definido
                return $this->calculate_special_product_commission(
                    $price,
                    $quantity,
                    $vendor_data
                );
            } else {
                // Producto normal sin porcentaje
                return $this->calculate_commission_from_vendor(
                    $price,
                    $quantity,
                    $vendor_data
                );
            }
        }
    }
    
    /**
     * Calcular comisión para producto especial (Ticket)
     */
    private function calculate_special_product_commission($price, $quantity, $vendor_data) {
        $special_commission = $this->config['special_product_commission']; // 90%
        $cashback_percent = $this->config['cashback_percent']; // 10%
        
        if (isset($vendor_data['commission']) && 
            $vendor_data['commission']['commission_mode'] == 'percent') {
            
            $vendor_percent = floatval($vendor_data['commission']['commission_percent']);
            $commission = (floatval($quantity) * floatval($price)) - 
                (floatval($quantity) * floatval($price) * $vendor_percent / 100);
            
            return floatval($commission * $cashback_percent / 100);
        } else {
            $commission = (floatval($quantity) * floatval($price)) - 
                (floatval($quantity) * floatval($price) * $special_commission / 100);
            
            return floatval($commission * $cashback_percent / 100);
        }
    }
    
    /**
     * Calcular comisión desde configuración del vendedor
     */
    private function calculate_commission_from_vendor($price, $quantity, $vendor_data) {
        $default_percent = 90.0;
        $cashback_percent = $this->config['cashback_percent'];
        
        if (isset($vendor_data['commission']) && 
            $vendor_data['commission']['commission_mode'] == 'percent') {
            
            $vendor_percent = floatval($vendor_data['commission']['commission_percent']);
            $commission = (floatval($quantity) * floatval($price)) - 
                (floatval($quantity) * floatval($price) * $vendor_percent / 100);
            
            return floatval($commission * $cashback_percent / 100);
        } else {
            $commission = (floatval($quantity) * floatval($price)) - 
                (floatval($quantity) * floatval($price) * $default_percent / 100);
            
            return floatval($commission * $cashback_percent / 100);
        }
    }
    
    /**
     * Calcular comisión sin configuración de producto
     */
    private function calculate_commission_without_config($product_id, $price, $quantity, $vendor_data) {
        return $this->calculate_commission_from_vendor($price, $quantity, $vendor_data);
    }
    
    /**
     * Calcular todas las comisiones de un pedido
     * Equivalente a: calcula_order_comisions()
     */
    public function calculate_order_commissions($order_id) {
        global $indeed_db;
        
        if ($this->config['enable_logging']) {
            error_log('CV Commissions: Calculando comisiones para pedido #' . $order_id);
        }
        
        $order = new WC_Order($order_id);
        
        // Calcular comisión base
        $base_commission = $this->calculate_total_commissions(false, $order_id);
        
        // Estructura de comisiones
        $commissions = array();
        
        // Comisión del programador (2% de cada venta)
        $commissions['programador'] = $base_commission;
        $commissions['programador_id'] = $this->config['programmer_user_id'];
        $commissions['total'] = $commissions['programador'] * 10;
        $commissions['order_id'] = $order_id;
        
        // Comisión del comprador
        $commissions['comprador'] = $base_commission;
        $commissions['comprador_affiliate_id'] = absint(
            $indeed_db->affiliate_get_id_by_uid($order->get_user_id())
        );
        $commissions['comprador_user_id'] = absint($order->get_user_id());
        
        // Pirámide de comisionistas de ventas (10 niveles)
        // Nivel 0 = comisión del comprador
        // Niveles 1-9 = 10% del nivel 0 (TODOS reciben el mismo valor, no es piramidal)
        $mlm_percent = $this->config['mlm_level_percent'] / 100; // 10% = 0.10
        for ($i = 0; $i < $this->config['mlm_levels']; $i++) {
            if ($i == 0) {
                $commissions['comisista_ventas'][$i] = $commissions['comprador'];
            } else {
                // IMPORTANTE: Todos los niveles 1-9 reciben el mismo porcentaje del nivel 0
                $commissions['comisista_ventas'][$i] = $commissions['comprador'] * $mlm_percent;
            }
        }
        
        // Pirámide de comisionistas de compras (10 niveles)
        // Misma lógica: nivel 0 = comprador, niveles 1-9 = 10% del nivel 0
        for ($i = 0; $i < $this->config['mlm_levels']; $i++) {
            if ($i == 0) {
                $commissions['comisista_compras'][$i] = $commissions['comprador'];
            } else {
                // IMPORTANTE: Todos los niveles 1-9 reciben el mismo porcentaje del nivel 0
                $commissions['comisista_compras'][$i] = $commissions['comprador'] * $mlm_percent;
            }
        }
        
        // Calcular lo que le toca a la empresa (lo que sobra)
        $total_distributed = $commissions['programador'] + 
                           $commissions['comprador'] + 
                           ($commissions['comisista_compras'][1] * 18) + 
                           ($commissions['comprador'] * 2);
        
        $commissions['empresa'] = $commissions['total'] - $total_distributed;
        
        // Obtener pirámide de afiliados
        $pyramid = new CV_MLM_Pyramid($this->config);
        $commissions['comisionstas'] = $pyramid->build_pyramid($order_id, $commissions);
        
        if ($this->config['enable_logging']) {
            error_log('CV Commissions: Comisiones calculadas: ' . print_r($commissions, true));
        }
        
        return $commissions;
    }
    
    /**
     * Obtener vendor ID de un pedido
     * Equivalente a: obten_vendedores_order()
     */
    public function get_vendor_from_order($order) {
        if ($this->config['enable_logging']) {
            error_log('CV Commissions: Obteniendo vendor del pedido');
        }
        
        $items = $order->get_items();
        $vendor_id = 0;
        
        foreach ($items as $item) {
            $product_id = $item->get_product_id();
            $vendor_id = wcfm_get_vendor_id_by_post($product_id);
            
            if ($vendor_id == 0) {
                $order_item_id = $item->get_id();
                $vendor_id = wc_get_order_item_meta($order_item_id, '_vendor_id', true);
                
                if ($this->config['enable_logging']) {
                    error_log('CV Commissions: Vendor obtenido de meta: ' . $vendor_id);
                }
            }
            
            if ($vendor_id) {
                break;
            }
        }
        
        return $vendor_id;
    }
}

