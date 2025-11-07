<?php
/**
 * Verificador de dependencias
 */

if (!defined('ABSPATH')) {
    exit;
}

class CV_Dependencies_Checker {
    
    /**
     * Plugins requeridos
     */
    private $required_plugins = array(
        'woocommerce/woocommerce.php' => 'WooCommerce',
        'wc-frontend-manager/wc_frontend_manager.php' => 'WCFM - WC Frontend Manager',
        'wc-multivendor-marketplace/wc_multivendor_marketplace.php' => 'WCFM Marketplace',
        'indeed-affiliate-pro/indeed-affiliate-pro.php' => 'Indeed Ultimate Affiliate Pro',
    );
    
    /**
     * Plugins opcionales
     */
    private $optional_plugins = array(
        'woo-wallet/woo-wallet.php' => 'WooCommerce Wallet',
    );
    
    /**
     * Verificar todas las dependencias
     */
    public function check_all_dependencies() {
        foreach ($this->required_plugins as $plugin => $name) {
            if (!$this->is_plugin_active($plugin)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Obtener plugins faltantes
     */
    public function get_missing_dependencies() {
        $missing = array();
        
        foreach ($this->required_plugins as $plugin => $name) {
            if (!$this->is_plugin_active($plugin)) {
                $missing[] = $name;
            }
        }
        
        return $missing;
    }
    
    /**
     * Obtener plugins opcionales faltantes
     */
    public function get_missing_optional() {
        $missing = array();
        
        foreach ($this->optional_plugins as $plugin => $name) {
            if (!$this->is_plugin_active($plugin)) {
                $missing[] = $name;
            }
        }
        
        return $missing;
    }
    
    /**
     * Verificar si un plugin está activo
     */
    private function is_plugin_active($plugin) {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        
        return is_plugin_active($plugin);
    }
    
    /**
     * Verificar clases globales requeridas
     */
    public function check_global_classes() {
        $missing = array();
        
        // Verificar $WCFM
        global $WCFM;
        if (!isset($WCFM) || !is_object($WCFM)) {
            $missing[] = 'Variable global $WCFM no disponible';
        }
        
        // Verificar $indeed_db
        global $indeed_db;
        if (!isset($indeed_db) || !is_object($indeed_db)) {
            $missing[] = 'Variable global $indeed_db no disponible';
        }
        
        // Verificar clase Referral_Main
        if (!class_exists('Referral_Main')) {
            $missing[] = 'Clase Referral_Main no disponible';
        }
        
        return $missing;
    }
    
    /**
     * Obtener estado completo de dependencias
     */
    public function get_status() {
        $status = array(
            'all_ok' => true,
            'required' => array(),
            'optional' => array(),
            'global_classes' => array(),
        );
        
        // Verificar requeridos
        foreach ($this->required_plugins as $plugin => $name) {
            $is_active = $this->is_plugin_active($plugin);
            $status['required'][$name] = $is_active;
            
            if (!$is_active) {
                $status['all_ok'] = false;
            }
        }
        
        // Verificar opcionales
        foreach ($this->optional_plugins as $plugin => $name) {
            $status['optional'][$name] = $this->is_plugin_active($plugin);
        }
        
        // Verificar clases globales
        $missing_classes = $this->check_global_classes();
        $status['global_classes'] = array(
            'ok' => empty($missing_classes),
            'missing' => $missing_classes,
        );
        
        if (!empty($missing_classes)) {
            $status['all_ok'] = false;
        }
        
        return $status;
    }
}

