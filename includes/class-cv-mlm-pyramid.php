<?php
/**
 * Pirámide MLM (Multi-Level Marketing)
 * Construye la estructura de 10 niveles de comisionistas
 */

if (!defined('ABSPATH')) {
    exit;
}

class CV_MLM_Pyramid {
    
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
     * Construir pirámide de comisionistas
     * Equivalente a: obten_pidamide_compradores()
     */
    public function build_pyramid($order_id, $commissions) {
        global $indeed_db, $WCFM;
        
        $order = new WC_Order($order_id);
        $pyramid = array();
        
        // Obtener affiliate_id del comprador
        $buyer_affiliate_id = absint($indeed_db->affiliate_get_id_by_uid($order->get_user_id()));
        
        // Construir pirámide de compradores (10 niveles hacia arriba)
        $pyramid = $this->build_buyer_pyramid($buyer_affiliate_id, $commissions, $pyramid);
        
        // Construir pirámide de vendedores (10 niveles hacia arriba)
        $vendor_id = $this->get_vendor_from_order($order);
        $vendor_affiliate_id = absint($indeed_db->affiliate_get_id_by_uid($vendor_id));
        $pyramid = $this->build_vendor_pyramid($vendor_affiliate_id, $commissions, $pyramid);
        
        if ($this->config['enable_logging']) {
            error_log('CV Commissions: Pirámide construida con ' . count($pyramid) . ' niveles');
        }
        
        return $pyramid;
    }
    
    /**
     * Construir pirámide de compradores
     */
    private function build_buyer_pyramid($affiliate_id, $commissions, $pyramid) {
        global $indeed_db, $WCFM;
        
        $level = 0;
        $max_levels = $this->config['mlm_levels'];
        
        do {
            $affiliate_id = $indeed_db->mlm_get_parent($affiliate_id);
            
            if ($this->config['enable_logging']) {
                error_log('CV Commissions: Nivel comprador ' . $level . ' - Affiliate ID: ' . $affiliate_id);
            }
            
            if ($affiliate_id != '' && $level < $max_levels) {
                // Obtener datos del afiliado
                $affiliate = $indeed_db->get_affiliate($affiliate_id);
                
                // Verificar que el afiliado existe y tiene uid
                if ($affiliate) {
                    $affiliate_uid = is_object($affiliate) ? $affiliate->uid : (isset($affiliate['uid']) ? $affiliate['uid'] : 0);
                    
                    if ($affiliate_uid > 0) {
                        $user = get_user_by('id', $affiliate_uid);
                        
                        if ($user) {
                            $pyramid[$level]['comprador']['id'] = $affiliate_id;
                            $pyramid[$level]['comprador']['user_id'] = $affiliate_uid;
                            $pyramid[$level]['comprador']['empresa'] = $WCFM->wcfm_vendor_support->wcfm_get_vendor_store_name_by_vendor($affiliate_uid);
                            $pyramid[$level]['comprador']['nombre'] = $user->data->display_name;
                            // IMPORTANTE: level+1 porque comisista_compras[0] es para el comprador directo
                            $pyramid[$level]['comprador']['total'] = $commissions['comisista_compras'][$level + 1];
                        }
                    }
                }
                
                $level++;
            } else {
                if ($this->config['enable_logging']) {
                    error_log('CV Commissions: Saliendo del bucle de compradores');
                }
                break;
            }
            
        } while ($affiliate_id != '' && $level < $max_levels);
        
        // Rellenar niveles faltantes con Ciudad Virtual
        for ($n = $level; $n < $max_levels; $n++) {
            $pyramid[$n]['comprador']['id'] = $this->config['company_affiliate_id'];
            $pyramid[$n]['comprador']['user_id'] = $this->config['company_user_id'];
            $pyramid[$n]['comprador']['empresa'] = $this->config['company_name'];
            $pyramid[$n]['comprador']['nombre'] = $this->config['company_contact_name'];
            // IMPORTANTE: n+1 porque comisista_compras[0] es para el comprador directo
            $pyramid[$n]['comprador']['total'] = $commissions['comisista_compras'][$n + 1];
        }
        
        return $pyramid;
    }
    
    /**
     * Construir pirámide de vendedores
     */
    private function build_vendor_pyramid($affiliate_id, $commissions, $pyramid) {
        global $indeed_db, $WCFM;
        
        $level = 0;
        $max_levels = $this->config['mlm_levels'];
        
        do {
            $affiliate_id = $indeed_db->mlm_get_parent($affiliate_id);
            
            if ($this->config['enable_logging']) {
                error_log('CV Commissions: Nivel vendedor ' . $level . ' - Affiliate ID: ' . $affiliate_id);
            }
            
            if ($affiliate_id != '' && $level < $max_levels) {
                // Obtener datos del afiliado
                $affiliate = $indeed_db->get_affiliate($affiliate_id);
                
                // Verificar que el afiliado existe y tiene uid
                if ($affiliate) {
                    $affiliate_uid = is_object($affiliate) ? $affiliate->uid : (isset($affiliate['uid']) ? $affiliate['uid'] : 0);
                    
                    if ($affiliate_uid > 0) {
                        $user = get_user_by('id', $affiliate_uid);
                        
                        if ($user) {
                            $pyramid[$level]['vendedor']['id'] = $affiliate_id;
                            $pyramid[$level]['vendedor']['user_id'] = $affiliate_uid;
                            $pyramid[$level]['vendedor']['empresa'] = $WCFM->wcfm_vendor_support->wcfm_get_vendor_store_name_by_vendor($affiliate_uid);
                            $pyramid[$level]['vendedor']['nombre'] = $user->data->display_name;
                            // IMPORTANTE: level+1 porque comisista_ventas[0] es para el vendedor directo
                            $pyramid[$level]['vendedor']['total'] = $commissions['comisista_ventas'][$level + 1];
                        }
                    }
                }
                
                $level++;
            } else {
                if ($this->config['enable_logging']) {
                    error_log('CV Commissions: Saliendo del bucle de vendedores');
                }
                break;
            }
            
        } while ($affiliate_id != '' && $level < $max_levels);
        
        // Rellenar niveles faltantes con Ciudad Virtual
        for ($n = $level; $n < $max_levels; $n++) {
            $pyramid[$n]['vendedor']['id'] = $this->config['company_affiliate_id'];
            $pyramid[$n]['vendedor']['user_id'] = $this->config['company_user_id'];
            $pyramid[$n]['vendedor']['empresa'] = $this->config['company_name'];
            $pyramid[$n]['vendedor']['nombre'] = $this->config['company_contact_name'];
            // IMPORTANTE: n+1 porque comisista_ventas[0] es para el vendedor directo
            $pyramid[$n]['vendedor']['total'] = $commissions['comisista_ventas'][$n + 1];
        }
        
        return $pyramid;
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

