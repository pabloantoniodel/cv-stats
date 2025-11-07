<?php
/**
 * Funciones de compatibilidad con snippets antiguos
 * Estas funciones permiten que otros snippets que dependían del Snippet 24 sigan funcionando
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Función de compatibilidad: calcula_order_comisions()
 * Usada por Snippet 22 para mostrar comisiones en WCFM
 */
if (!function_exists('calcula_order_comisions')) {
    function calcula_order_comisions($order_id) {
        // Obtener instancia del plugin
        $plugin = CV_Commissions::get_instance();
        $config = $plugin->get_config();
        
        // Usar la calculadora del plugin
        $calculator = new CV_Commission_Calculator($config);
        return $calculator->calculate_order_commissions($order_id);
    }
}

/**
 * Función de compatibilidad: calcula_total_comisiones()
 */
if (!function_exists('calcula_total_comisiones')) {
    function calcula_total_comisiones($carrito = true, $order_id = 0) {
        // Obtener instancia del plugin
        $plugin = CV_Commissions::get_instance();
        $config = $plugin->get_config();
        
        // Usar la calculadora del plugin
        $calculator = new CV_Commission_Calculator($config);
        return $calculator->calculate_total_commissions($carrito, $order_id);
    }
}

/**
 * Función de compatibilidad: calcula_comision_retorno_carrito()
 */
if (!function_exists('calcula_comision_retorno_carrito')) {
    function calcula_comision_retorno_carrito($importe) {
        error_log('FUNCTION calcula_comision_retorno_carrito (compatibility)');
        return calcula_total_comisiones(true, 0);
    }
}

/**
 * Función de compatibilidad: obten_vendedores_order()
 */
if (!function_exists('obten_vendedores_order')) {
    function obten_vendedores_order($order) {
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

/**
 * Función de compatibilidad: send_firebase_notification()
 */
if (!function_exists('send_firebase_notification')) {
    function send_firebase_notification($order_id) {
        $plugin = CV_Commissions::get_instance();
        $config = $plugin->get_config();
        
        $notifier = new CV_Firebase_Notifier($config);
        return $notifier->send_order_notification($order_id);
    }
}

/**
 * Función de compatibilidad: referidos_guardar()
 * Usada como filtro en Indeed Affiliate Pro
 */
if (!function_exists('referidos_guardar')) {
    function referidos_guardar($args) {
        error_log("CV Commissions: PasaReferidos > Guardar");
        error_log(print_r($args, true));
        
        if (isset($args['reference'])) {
            $comision = calcula_order_comisions($args['reference']);
            error_log(print_r($comision, true));
            
            // La línea original estaba comentada:
            // $args['amount']=$comision['total']-$comision['empresa']-$comision['programador']-$comision['comprador']-($comision['programador']/10 *9 );
            
            error_log(print_r($args, true));
        }
        
        return $args;
    }
}

/**
 * Función de compatibilidad: obten_vendedores_carrito()
 * Obtiene vendor del carrito
 */
if (!function_exists('obten_vendedores_carrito')) {
    function obten_vendedores_carrito() {
        global $woocommerce;
        
        $items = $woocommerce->cart->get_cart();
        $vendor_id = 0;
        
        foreach ($items as $item) {
            $product_id = $item['product_id'];
            $vendor_id = wcfm_get_vendor_id_by_post($product_id);
            
            if ($vendor_id == 0) {
                $order_item_id = $item->get_id();
                $vendor_id = wc_get_order_item_meta($order_item_id, '_vendor_id', true);
                error_log("CV Commissions: Pasa por obten_vendedores_carrito vendor_id ===0->" . $vendor_id);
            }
            
            if ($vendor_id) {
                break;
            }
        }
        
        return $vendor_id;
    }
}

/**
 * Función de compatibilidad: obten_pidamide_compradores()
 * Construye la pirámide MLM (wrapper a la clase del plugin)
 */
if (!function_exists('obten_pidamide_compradores')) {
    function obten_pidamide_compradores($order_id, $piramide) {
        $plugin = CV_Commissions::get_instance();
        $config = $plugin->get_config();
        
        $pyramid = new CV_MLM_Pyramid($config);
        return $pyramid->build_pyramid($order_id, $piramide);
    }
}

