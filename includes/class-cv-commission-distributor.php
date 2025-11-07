<?php
/**
 * Distribuidor de Comisiones
 * Orquesta todo el proceso de cálculo y distribución de comisiones
 */

if (!defined('ABSPATH')) {
    exit;
}

class CV_Commission_Distributor {
    
    /**
     * Configuración
     */
    private $config;
    
    /**
     * Calculator
     */
    private $calculator;
    
    /**
     * Notifier
     */
    private $notifier;
    
    /**
     * Constructor
     */
    public function __construct($config) {
        $this->config = $config;
        $this->calculator = new CV_Commission_Calculator($config);
        $this->notifier = new CV_Firebase_Notifier($config);
    }
    
    /**
     * Procesar pedido completo
     * Equivalente a: add_comision_order()
     */
    public function process_order($order_id) {
        if ($this->config['enable_logging']) {
            error_log('🎯 CV Commissions: Iniciando procesamiento de pedido #' . $order_id);
        }
        
        try {
            // 1. Enviar notificación Firebase al vendedor
            $this->notifier->send_order_notification($order_id);
            
            // 2. Calcular todas las comisiones
            $commissions = $this->calculator->calculate_order_commissions($order_id);
            
            if ($this->config['enable_logging']) {
                error_log('CV Commissions: Comisiones calculadas: ' . print_r($commissions, true));
            }
            
            // 3. Verificar que existe la clase Referral_Main
            if (!class_exists('Referral_Main')) {
                // Cargar la clase si no está disponible
                if (defined('UAP_PATH') && file_exists(UAP_PATH . 'public/Referral_Main.class.php')) {
                    require_once UAP_PATH . 'public/Referral_Main.class.php';
                } else {
                    error_log('❌ CV Commissions: Clase Referral_Main no disponible');
                    return false;
                }
            }
            
            // 4. Guardar comisión del programador
            $this->save_programmer_commission($commissions);
            
            // 5. Guardar comisión del comprador
            $this->save_buyer_commission($commissions);
            
            // 6. Guardar comisión de la empresa
            $this->save_company_commission($commissions);
            
            // 7. Guardar comisiones de la pirámide MLM
            $this->save_mlm_commissions($commissions);
            
            if ($this->config['enable_logging']) {
                error_log('✅ CV Commissions: Pedido #' . $order_id . ' procesado exitosamente');
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('❌ CV Commissions: Error procesando pedido #' . $order_id . ': ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Guardar comisión del programador
     */
    private function save_programmer_commission($commissions) {
        $linea_comision = new Referral_Main(
            $this->config['programmer_user_id'],
            $this->config['programmer_affiliate_id']
        );
        
        $args = array(
            'refferal_wp_uid' => $this->config['programmer_user_id'],
            'campaign' => '',
            'affiliate_id' => $this->config['programmer_affiliate_id'],
            'visit_id' => '',
            'description' => 'Parte programador pedido ' . $commissions['order_id'],
            'source' => 'Calculo privado',
            'reference' => $commissions['order_id'],
            'reference_details' => 'Parte programador',
            'amount' => $commissions['programador'],
            'currency' => $this->config['currency'],
        );
        
        $linea_comision->save_referral_unverified($args);
        
        if ($this->config['enable_logging']) {
            error_log('CV Commissions: Comisión programador guardada: ' . $commissions['programador'] . ' ' . $this->config['currency']);
        }
    }
    
    /**
     * Guardar comisión del comprador
     */
    private function save_buyer_commission($commissions) {
        $linea_comision = new Referral_Main(
            $commissions['comprador_user_id'],
            $commissions['comprador_affiliate_id']
        );
        
        $args = array(
            'refferal_wp_uid' => $commissions['comprador_user_id'],
            'campaign' => '',
            'affiliate_id' => $commissions['comprador_affiliate_id'],
            'visit_id' => '',
            'description' => 'Parte comprador pedido ' . $commissions['order_id'],
            'source' => 'Calculo privado',
            'reference' => $commissions['order_id'],
            'reference_details' => 'Parte comprador pedido ' . $commissions['order_id'],
            'amount' => $commissions['comprador'],
            'currency' => $this->config['currency'],
        );
        
        $linea_comision->save_referral_unverified($args);
        
        if ($this->config['enable_logging']) {
            error_log('CV Commissions: Comisión comprador guardada: ' . $commissions['comprador'] . ' ' . $this->config['currency']);
        }
    }
    
    /**
     * Guardar comisión de la empresa
     */
    private function save_company_commission($commissions) {
        $linea_comision = new Referral_Main(
            $this->config['company_user_id'],
            $this->config['company_affiliate_id']
        );
        
        $args = array(
            'refferal_wp_uid' => $this->config['company_user_id'],
            'campaign' => '',
            'affiliate_id' => $this->config['company_affiliate_id'],
            'visit_id' => '',
            'description' => 'Parte Empresa pedido ' . $commissions['order_id'],
            'source' => 'Calculo privado',
            'reference' => $commissions['order_id'],
            'reference_details' => 'Parte Empresa pedido ' . $commissions['order_id'],
            'amount' => $commissions['empresa'],
            'currency' => $this->config['currency'],
        );
        
        $linea_comision->save_referral_unverified($args);
        
        if ($this->config['enable_logging']) {
            error_log('CV Commissions: Comisión empresa guardada: ' . $commissions['empresa'] . ' ' . $this->config['currency']);
        }
    }
    
    /**
     * Guardar comisiones de la pirámide MLM
     */
    private function save_mlm_commissions($commissions) {
        if (!isset($commissions['comisionstas']) || empty($commissions['comisionstas'])) {
            if ($this->config['enable_logging']) {
                error_log('CV Commissions: No hay comisionistas MLM para procesar');
            }
            return;
        }
        
        $count_buyer = 0;
        $count_vendor = 0;
        
        foreach ($commissions['comisionstas'] as $level => $comisionista) {
            // Comisión del comprador en este nivel
            if (isset($comisionista['comprador']) && $comisionista['comprador']['total'] > 0) {
                $linea_comision = new Referral_Main(
                    $comisionista['comprador']['user_id'],
                    $comisionista['comprador']['id']
                );
                
                $args = array(
                    'refferal_wp_uid' => $comisionista['comprador']['user_id'],
                    'campaign' => '',
                    'affiliate_id' => $comisionista['comprador']['id'],
                    'visit_id' => '',
                    'description' => 'Parte MLM comprador nivel ' . ($level + 1) . ' pedido ' . $commissions['order_id'],
                    'source' => 'Calculo privado',
                    'reference' => $commissions['order_id'],
                    'reference_details' => 'MLM Comprador Nivel ' . ($level + 1),
                    'amount' => $comisionista['comprador']['total'],
                    'currency' => $this->config['currency'],
                );
                
                $linea_comision->save_referral_unverified($args);
                $count_buyer++;
            }
            
            // Comisión del vendedor en este nivel
            if (isset($comisionista['vendedor']) && $comisionista['vendedor']['total'] > 0) {
                $linea_comision = new Referral_Main(
                    $comisionista['vendedor']['user_id'],
                    $comisionista['vendedor']['id']
                );
                
                $args = array(
                    'refferal_wp_uid' => $comisionista['vendedor']['user_id'],
                    'campaign' => '',
                    'affiliate_id' => $comisionista['vendedor']['id'],
                    'visit_id' => '',
                    'description' => 'Parte MLM vendedor nivel ' . ($level + 1) . ' pedido ' . $commissions['order_id'],
                    'source' => 'Calculo privado',
                    'reference' => $commissions['order_id'],
                    'reference_details' => 'MLM Vendedor Nivel ' . ($level + 1),
                    'amount' => $comisionista['vendedor']['total'],
                    'currency' => $this->config['currency'],
                );
                
                $linea_comision->save_referral_unverified($args);
                $count_vendor++;
            }
        }
        
        if ($this->config['enable_logging']) {
            error_log('CV Commissions: Comisiones MLM guardadas - Compradores: ' . $count_buyer . ' - Vendedores: ' . $count_vendor);
        }
    }
}

