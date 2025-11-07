<?php
/**
 * Sistema MLM de Afiliados
 * 
 * Gestiona la red de afiliados MLM usando Indeed Affiliate Pro
 * Migrado desde snippet 48 función repasa_affiliado()
 * 
 * @package CV_Commissions
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CV_Affiliate_MLM {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Hook cuando se actualiza user_registration_referido
        add_action('add_user_meta', array($this, 'procesar_referido'), 20, 3);
        add_action('updated_user_meta', array($this, 'procesar_referido_update'), 20, 4);
    }
    
    /**
     * Procesar referido cuando se añade user meta (nuevo usuario)
     */
    public function procesar_referido($object_id, $meta_key, $_meta_value) {
        if ($meta_key !== 'user_registration_referido') {
            return;
        }
        
        error_log('🔗 CV MLM: Procesando nuevo referido para usuario: ' . $object_id);
        $this->repasa_affiliado($object_id, $_meta_value);
    }
    
    /**
     * Procesar referido cuando se actualiza user meta
     */
    public function procesar_referido_update($meta_id, $object_id, $meta_key, $_meta_value) {
        if ($meta_key !== 'user_registration_referido') {
            return;
        }
        
        error_log('🔗 CV MLM: Actualizando referido para usuario: ' . $object_id);
        $this->repasa_affiliado($object_id, $_meta_value);
    }
    
    /**
     * Repasa afiliado - Crea estructura MLM
     * Migrado desde snippet 48
     */
    private function repasa_affiliado($user_id, $id_padre = 0) {
        // Verificar que Indeed Affiliate Pro esté activo
        if (!class_exists('Uap_Db')) {
            error_log('⚠️ CV MLM: Indeed Affiliate Pro no está activo');
            return;
        }
        
        global $wpdb, $indeed_db;
        
        $user_meta = get_user_meta($user_id);
        
        error_log('🔍 CV MLM: Analizando usuario ID: ' . $user_id);
        
        // Si ya está registrado como afiliado, obtener su ID
        if (isset($user_meta['uap_affiliate_payment_type'])) {
            $aff_id = $indeed_db->get_affiliate_id_by_wpuid($user_id);
            error_log('ℹ️ CV MLM: Usuario ya es afiliado - Affiliate ID: ' . $aff_id);
            return;
        }
        
        // Si tiene referido, procesarlo
        if (isset($user_meta['user_registration_referido'])) {
            $referido_login = $user_meta['user_registration_referido'][0];
            
            error_log('🔍 CV MLM: Buscando usuario referido: ' . $referido_login);
            
            // Buscar usuario referido
            $referido = get_user_by('login', $referido_login);
            
            // Si no se encuentra, intentar decodificar
            if (!$referido && strpos($referido_login, '%') !== false) {
                $referido = get_user_by('login', urldecode($referido_login));
            }
            
            // Si aún no se encuentra, intentar por email
            if (!$referido && strpos($referido_login, '@') !== false) {
                $referido = get_user_by('email', $referido_login);
            }
            
            if (!$referido) {
                error_log('❌ CV MLM: Referido no encontrado: ' . $referido_login);
                return;
            }
            
            error_log('✅ CV MLM: Referido encontrado - ID: ' . $referido->ID);
            
            // Obtener affiliate ID del referido
            $user_affiliate = $indeed_db->get_affiliate_id_by_wpuid($referido->ID);
            
            if (!$user_affiliate) {
                error_log('⚠️ CV MLM: El referido no es afiliado - ID: ' . $referido->ID);
                // Crear afiliado para el referido primero
                $user_affiliate = $indeed_db->save_affiliate($referido->ID);
                error_log('✅ CV MLM: Afiliado creado para referido - Affiliate ID: ' . $user_affiliate);
            }
            
            // Crear afiliado para el nuevo usuario
            $aff_id = $indeed_db->save_affiliate($user_id);
            error_log('✅ CV MLM: Afiliado creado para nuevo usuario - Affiliate ID: ' . $aff_id);
            
            // VALIDACIÓN: Prevenir auto-referencias
            if ($aff_id == $user_affiliate) {
                error_log('⚠️ CV MLM: PREVENCIÓN AUTO-REFERENCIA - Afiliado ' . $aff_id . ' intentó ser su propio padre. No se crea relación.');
                return;
            }
            
            // Crear relación MLM en Indeed Affiliate Pro
            $result = $indeed_db->add_new_mlm_relation($aff_id, $user_affiliate);
            error_log('🔗 CV MLM: Relación MLM creada (Indeed) - Resultado: ' . $result);
            
            // Crear relación MLM en tabla custom (si existe la función)
            if (function_exists('add_new_mlm_relation_cvapp')) {
                add_new_mlm_relation_cvapp($aff_id, $user_affiliate);
                error_log('🔗 CV MLM: Relación MLM creada (CVApp custom table)');
            }
            
        } elseif ($id_padre != 0) {
            // Caso alternativo: ID padre viene como parámetro
            error_log('🔍 CV MLM: ID Padre viene de update: ' . $id_padre);
            
            $referido = get_user_by('login', $id_padre);
            
            if (!$referido) {
                error_log('❌ CV MLM: Referido no encontrado por ID padre: ' . $id_padre);
                return;
            }
            
            $user_affiliate = $indeed_db->get_affiliate_id_by_wpuid($referido->ID);
            
            if (!$user_affiliate) {
                $user_affiliate = $indeed_db->save_affiliate($referido->ID);
            }
            
            $aff_id = $indeed_db->save_affiliate($user_id);
            
            // VALIDACIÓN: Prevenir auto-referencias
            if ($aff_id == $user_affiliate) {
                error_log('⚠️ CV MLM: PREVENCIÓN AUTO-REFERENCIA - Afiliado ' . $aff_id . ' intentó ser su propio padre. No se crea relación.');
                return;
            }
            
            $indeed_db->add_new_mlm_relation($aff_id, $user_affiliate);
            
            if (function_exists('add_new_mlm_relation_cvapp')) {
                add_new_mlm_relation_cvapp($aff_id, $user_affiliate);
            }
            
            error_log('✅ CV MLM: Relación creada con ID padre: ' . $id_padre);
        }
    }
}

new CV_Affiliate_MLM();



