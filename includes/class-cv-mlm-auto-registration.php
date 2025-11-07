<?php
/**
 * Auto-registro MLM en Compras
 * Conecta automáticamente compradores con vendedores en la pirámide MLM
 * 
 * Basado en Snippet 23: "Guardar afiliado"
 */

if (!defined('ABSPATH')) {
    exit;
}

class CV_MLM_Auto_Registration {
    
    /**
     * Configuración
     */
    private $config;
    
    /**
     * Constructor
     */
    public function __construct($config) {
        $this->config = $config;
        
        // Solo activar si está habilitado en la configuración
        if ($this->config['mlm_auto_registration_enabled']) {
            $this->init_hooks();
        }
        
        // Siempre activar auto-registro de nuevos usuarios
        $this->init_user_registration_hooks();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Hook cuando se crea un pedido (antes de procesarlo)
        add_action('woocommerce_checkout_create_order', array($this, 'auto_register_buyer'), 999, 2);
    }
    
    /**
     * Inicializar hooks de registro de usuarios
     * Migrado desde Snippet #28 y #31
     */
    private function init_user_registration_hooks() {
        // Hook cuando se registra un nuevo usuario
        add_action('user_register', array($this, 'register_new_user_in_mlm'), 150, 1);
        
        // Hook ADICIONAL después de guardar meta de User Registration
        // Este se ejecuta DESPUÉS de que user_registration_referido se haya guardado
        add_action('user_registration_after_user_meta_update', array($this, 'update_mlm_after_meta'), 999, 3);
        
        // Hook cuando se borra un usuario
        add_action('deleted_user', array($this, 'delete_user_from_mlm'), 96, 1);
    }
    
    /**
     * Auto-registrar comprador en MLM
     * Equivalente a: afiliado()
     */
    public function auto_register_buyer($order, $data) {
        if ($this->config['enable_logging']) {
            error_log('CV Commissions MLM: Verificando auto-registro para pedido');
        }
        
        // Obtener items del pedido
        $items = $order->get_items();
        
        foreach ($items as $item) {
            $product_id = isset($item['product_id']) ? $item['product_id'] : $item->get_product_id();
            
            if ($this->config['enable_logging']) {
                error_log('CV Commissions MLM: Producto ID: ' . $product_id);
            }
            
            // Obtener vendor del producto
            $vendor_id = wcfm_get_vendor_id_by_post($product_id);
            
            if ($this->config['enable_logging']) {
                error_log('CV Commissions MLM: Vendor ID: ' . $vendor_id);
            }
            
            if ($vendor_id && $vendor_id > 0) {
                // Verificar y registrar afiliado
                $this->check_and_register_affiliate($vendor_id);
                break; // Solo procesar el primer producto
            }
        }
    }
    
    /**
     * Verificar y registrar afiliado en MLM
     * Equivalente a: revisar_afiliado_2()
     */
    private function check_and_register_affiliate($vendor_id) {
        global $indeed_db;
        
        // Obtener usuario actual (comprador)
        $user_data = wp_get_current_user();
        
        if (!$user_data->ID) {
            if ($this->config['enable_logging']) {
                error_log('CV Commissions MLM: No hay usuario logueado');
            }
            return;
        }
        
        // Obtener affiliate ID del comprador
        $buyer_affiliate_id = $indeed_db->get_affiliate_id_by_wpuid($user_data->ID);
        
        if ($this->config['enable_logging']) {
            error_log('CV Commissions MLM: Buyer Affiliate ID: ' . $buyer_affiliate_id);
        }
        
        if (!$buyer_affiliate_id) {
            if ($this->config['enable_logging']) {
                error_log('CV Commissions MLM: Comprador no es afiliado, no se puede auto-registrar');
            }
            return;
        }
        
        // Verificar si ya tiene padre MLM
        $parent_id = $indeed_db->mlm_get_parent($buyer_affiliate_id);
        
        if ($this->config['enable_logging']) {
            error_log('CV Commissions MLM: Parent ID actual: ' . $parent_id);
        }
        
        if ($parent_id == 0 || $parent_id == '' || $parent_id === false) {
            // NO tiene padre (huérfano) - Asignar al vendedor
            $vendor_affiliate_id = $indeed_db->get_affiliate_id_by_wpuid($vendor_id);
            
            if ($vendor_affiliate_id) {
                // VALIDACIÓN: Prevenir auto-referencias
                if ($buyer_affiliate_id == $vendor_affiliate_id) {
                    if ($this->config['enable_logging']) {
                        error_log('CV Commissions MLM: ⚠️ PREVENCIÓN AUTO-REFERENCIA - Comprador ' . $buyer_affiliate_id . ' intentó ser su propio padre (vendor). No se crea relación.');
                    }
                    return;
                }
                
                // Añadir relación MLM
                $result = $indeed_db->add_new_mlm_relation($buyer_affiliate_id, $vendor_affiliate_id);
                
                if ($this->config['enable_logging']) {
                    error_log('CV Commissions MLM: ✅ Relación MLM creada - Comprador: ' . $buyer_affiliate_id . ' → Padre: ' . $vendor_affiliate_id);
                }
                
                // Log para auditoría
                error_log(sprintf(
                    'CV Commissions MLM: Auto-registro - User %d (Affiliate %d) asignado bajo Vendor %d (Affiliate %d)',
                    $user_data->ID,
                    $buyer_affiliate_id,
                    $vendor_id,
                    $vendor_affiliate_id
                ));
            } else {
                if ($this->config['enable_logging']) {
                    error_log('CV Commissions MLM: ⚠️ Vendor ' . $vendor_id . ' no es afiliado, no se puede crear relación');
                }
            }
        } else {
            if ($this->config['enable_logging']) {
                error_log('CV Commissions MLM: Comprador ya tiene padre MLM (' . $parent_id . '), no se modifica');
            }
        }
    }
    
    /**
     * Actualizar relaciones MLM después de guardar user_registration_referido
     * Se ejecuta DESPUÉS de que User Registration guarde todos los metas
     * 
     * @param array $valid_form_data Datos del formulario
     * @param int $form_id ID del formulario
     * @param int $user_id ID del usuario
     */
    public function update_mlm_after_meta($valid_form_data, $form_id, $user_id) {
        if (!$user_id) {
            return;
        }
        
        error_log('🔄 CV MLM: update_mlm_after_meta called for user ' . $user_id);
        
        global $wpdb, $indeed_db;
        
        // Verificar si ya tiene relación MLM
        $has_mlm = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cvapp_mlm_relations WHERE affiliate_id IN (
                SELECT id FROM {$wpdb->prefix}uap_affiliates WHERE uid = %d
            )",
            $user_id
        ));
        
        if ($has_mlm > 0) {
            error_log('✅ CV MLM: Usuario ' . $user_id . ' ya tiene relación MLM, actualizando si es necesario');
            
            // Obtener referido del meta
            $referido = get_user_meta($user_id, 'user_registration_referido', true);
            
            if ($referido) {
                error_log('🔍 CV MLM: Referido encontrado: ' . $referido);
                
                // Obtener affiliate_id del usuario
                $affiliate_id = $indeed_db->get_affiliate_id_by_wpuid($user_id);
                
                // Obtener affiliate_id del referidor
                $sponsor_affiliate_id = 0;
                if (is_numeric($referido)) {
                    $sponsor_affiliate_id = $indeed_db->get_affiliate_id_by_wpuid((int)$referido);
                } else if (strpos($referido, '@') !== false) {
                    $ref_user = get_user_by('email', $referido);
                    $sponsor_affiliate_id = $ref_user ? $indeed_db->get_affiliate_id_by_wpuid($ref_user->ID) : 0;
                } else {
                    $ref_user = get_user_by('login', $referido);
                    $sponsor_affiliate_id = $ref_user ? $indeed_db->get_affiliate_id_by_wpuid($ref_user->ID) : 0;
                }
                
                error_log('🔍 CV MLM: Sponsor affiliate_id: ' . ($sponsor_affiliate_id ? $sponsor_affiliate_id : '0'));
                
                if ($sponsor_affiliate_id && $affiliate_id && $sponsor_affiliate_id != $affiliate_id) {
                    // Actualizar ambas tablas
                    $wpdb->update(
                        $wpdb->prefix . 'uap_mlm_relations',
                        array('parent_affiliate_id' => $sponsor_affiliate_id),
                        array('affiliate_id' => $affiliate_id),
                        array('%d'),
                        array('%d')
                    );
                    
                    $wpdb->update(
                        $wpdb->prefix . 'cvapp_mlm_relations',
                        array('parent_affiliate_id' => $sponsor_affiliate_id),
                        array('affiliate_id' => $affiliate_id),
                        array('%d'),
                        array('%d')
                    );
                    
                    error_log('✅ CV MLM: Actualizado padre de affiliate ' . $affiliate_id . ' a ' . $sponsor_affiliate_id);
                }
            }
        }
    }
    
    /**
     * Registrar nuevo usuario automáticamente en sistema de afiliados y MLM
     * Migrado desde Snippet #28
     * 
     * @param int $uid ID del usuario recién registrado
     */
    public function register_new_user_in_mlm($uid) {
        if (!$uid) {
            error_log('❌ CV User Register: UID vacío');
            return;
        }

        global $indeed_db;
        
        if (!$indeed_db) {
            error_log('❌ CV User Register: indeed_db no disponible');
            return;
        }

        error_log('🔄 CV User Register: Iniciando registro para usuario ' . $uid);

        // Registrar como afiliado en UAP
        $affiliate_id = $indeed_db->save_affiliate($uid);
        
        if (!$affiliate_id) {
            error_log('❌ CV User Register: Error al crear afiliado para usuario ' . $uid);
            return;
        }

        error_log('✅ CV User Register: Afiliado creado - ID: ' . $affiliate_id . ' para usuario: ' . $uid);

        // Asignar rango por defecto
        $settings = $indeed_db->return_settings_from_wp_option('register');
        if (!empty($settings['uap_register_new_user_rank'])) {
            $indeed_db->update_affiliate_rank_by_uid($uid, $settings['uap_register_new_user_rank']);
            error_log('✅ CV User Register: Rango asignado: ' . $settings['uap_register_new_user_rank']);
        }

        // Establecer relación MLM en tabla UAP
        $indeed_db->set_mlm_relation_on_new_affiliate($affiliate_id);

        // Establecer relación MLM en tabla CV personalizada
        $this->add_mlm_relation_cvapp($affiliate_id);
        
        error_log('✅ CV User Register: Usuario ' . $uid . ' registrado completamente en MLM');
    }

    /**
     * Añadir relación MLM en tabla personalizada de CV
     * Migrado desde snippet #31
     * 
     * @param int $affiliate_id ID del afiliado
     */
    private function add_mlm_relation_cvapp($affiliate_id) {
        global $wpdb, $indeed_db;

        $parent_affiliate_id = 0;

        // IDENTIFICAR USER_ID a partir del affiliate_id
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT uid FROM wp_uap_affiliates WHERE id = %d",
            $affiliate_id
        ));
        
        // 0. PRIORIDAD: buscar sponsor por meta 'user_registration_referido'
        if ($user_id) {
            $referido = get_user_meta($user_id, 'user_registration_referido', true);
            error_log('🔍 CV MLM Custom: user_id=' . $user_id . ' - referido meta=' . ($referido ? $referido : 'VACÍO'));
            
            if ($referido) {
                $sponsor_affiliate_id = 0;
                // Puede ser ID numérico o email/login
                if (is_numeric($referido)) {
                    error_log('🔍 CV MLM Custom: Referido es numérico: ' . $referido);
                    $sponsor_affiliate_id = $indeed_db->get_affiliate_id_by_wpuid((int)$referido);
                    error_log('🔍 CV MLM Custom: Affiliate ID obtenido: ' . ($sponsor_affiliate_id ? $sponsor_affiliate_id : 'NULL/0'));
                } else if (strpos($referido, '@') !== false) {
                    $ref_user = get_user_by('email', $referido);
                    $sponsor_affiliate_id = $ref_user ? $indeed_db->get_affiliate_id_by_wpuid($ref_user->ID) : 0;
                } else {
                    $ref_user = get_user_by('login', $referido);
                    $sponsor_affiliate_id = $ref_user ? $indeed_db->get_affiliate_id_by_wpuid($ref_user->ID) : 0;
                }
                
                error_log('🔍 CV MLM Custom: sponsor_affiliate_id final=' . ($sponsor_affiliate_id ? $sponsor_affiliate_id : '0/false'));
                
                if ($sponsor_affiliate_id) {
                    $parent_affiliate_id = $sponsor_affiliate_id;
                    error_log('🔝 CV MLM Custom: PRIORIDAD referer meta: Usando referer (affiliate_id) '.$parent_affiliate_id.' para '.$affiliate_id);
                }
            }
        }

        // 1. Si NO se encontró en meta, probar cookie
        if (!$parent_affiliate_id && isset($_COOKIE['uap_referral'])) {
            $cookie_data = unserialize(stripslashes($_COOKIE['uap_referral']));
            if (!empty($cookie_data['affiliate_id'])) {
                $parent_affiliate_id = intval($cookie_data['affiliate_id']);
                error_log('🔍 CV MLM Custom: Padre desde cookie: ' . $parent_affiliate_id);
            }
        }

        // 2. Si tampoco, intentar obtener desde UAP
        if (!$parent_affiliate_id) {
            $parent_affiliate_id = $indeed_db->mlm_get_parent($affiliate_id);
            error_log('🔍 CV MLM Custom: Padre desde UAP: ' . $parent_affiliate_id);
        }

        // 3. Si tampoco hay padre en UAP, buscar el affiliate_id 1 como padre por defecto
        if (!$parent_affiliate_id) {
            $parent_affiliate_id = 1; // ID del afiliado raíz
            error_log('🔍 CV MLM Custom: Usando padre por defecto (ID 1)');
        }

        // VALIDACIÓN: Prevenir auto-referencias (un afiliado no puede ser su propio padre)
        if ($affiliate_id == $parent_affiliate_id) {
            error_log('⚠️ CV MLM Custom: PREVENCIÓN AUTO-REFERENCIA - Afiliado ' . $affiliate_id . ' intentó ser su propio padre. Usando padre por defecto (0).');
            $parent_affiliate_id = 0;
        }
        
        if ($affiliate_id && $parent_affiliate_id !== false && $parent_affiliate_id !== null) {
            $table = $wpdb->prefix . 'cvapp_mlm_relations';

            // Eliminar relaciones previas (por si acaso)
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $table WHERE affiliate_id = %d",
                $affiliate_id
            ));

            // Insertar nueva relación
            $result = $wpdb->query($wpdb->prepare(
                "INSERT INTO $table VALUES(NULL, %d, %d)",
                $affiliate_id,
                $parent_affiliate_id
            ));

            if ($result) {
                error_log('✅ CV MLM Custom: Relación creada - Afiliado: ' . $affiliate_id . ' - Padre: ' . $parent_affiliate_id);
            } else {
                error_log('❌ CV MLM Custom: Error al crear relación - ' . $wpdb->last_error);
            }
        } else {
            error_log('⚠️ CV MLM Custom: No se pudo crear relación - Afiliado: ' . $affiliate_id . ' - Padre: ' . $parent_affiliate_id);
        }
    }

    /**
     * Eliminar usuario del sistema MLM
     * Migrado desde snippet #31
     * 
     * @param int $uid ID del usuario eliminado
     */
    public function delete_user_from_mlm($uid) {
        global $wpdb, $indeed_db;

        error_log('🗑️ CV User Register: Eliminando usuario ' . $uid . ' del sistema MLM');

        $affiliate_id = $indeed_db->get_affiliate_id_by_wpuid($uid);

        if (!$affiliate_id) {
            error_log('⚠️ CV User Register: Usuario ' . $uid . ' no tiene affiliate_id');
            return;
        }

        $table = $wpdb->prefix . 'cvapp_mlm_relations';

        // Eliminar como hijo
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE affiliate_id = %d",
            $affiliate_id
        ));

        // Eliminar como padre
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE parent_affiliate_id = %d",
            $affiliate_id
        ));

        error_log('✅ CV User Register: Usuario ' . $uid . ' eliminado del sistema MLM');
    }
}

