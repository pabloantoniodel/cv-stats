<?php
/**
 * CV MLM Children Manager
 * 
 * Gestiona la obtención recursiva de todos los descendientes (hijos) en una red MLM
 * Calcula comisiones por nivel/rango para cada afiliado
 * 
 * Migrado desde Code Snippet #55 - MLMGetchildren Class
 * 
 * @package CV_Commissions
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase para obtener todos los hijos/descendientes de un afiliado en estructura MLM
 */
class CV_MLM_Children {
    
    /**
     * Array de todos los descendientes encontrados
     * @var array
     */
    private $children = array();
    
    /**
     * Profundidad máxima de niveles a recorrer
     * @var int
     */
    private $max_depth = 1;
    
    /**
     * Tipo de comisión por nivel (flat, percentage)
     * @var array
     */
    private $amount_per_level_type = array();
    
    /**
     * Valor de comisión por nivel
     * @var array
     */
    private $amount_per_level_value = array();
    
    /**
     * Tipo de comisión por rango de usuario
     * @var array
     */
    private $amount_per_rank_type = array();
    
    /**
     * Valor de comisión por rango de usuario
     * @var array
     */
    private $amount_per_rank_value = array();
    
    /**
     * Tipo de comisión general por defecto
     * @var string
     */
    private $general_amount_type = '';
    
    /**
     * Valor de comisión general por defecto
     * @var string
     */
    private $general_amount_value = '';
    
    /**
     * Constructor
     * 
     * @param int $affiliate_id ID del afiliado padre
     */
    public function __construct($affiliate_id = 0) {
        if (!$affiliate_id) {
            return;
        }
        
        global $indeed_db;
        
        // Verificar que Indeed Affiliate Pro esté activo
        if (!isset($indeed_db) || !is_object($indeed_db)) {
            error_log('⚠️ CV_MLM_Children: Indeed Affiliate Pro no está disponible');
            return;
        }
        
        // Obtener profundidad máxima desde configuración MLM
        $this->max_depth = get_option('uap_mlm_matrix_depth', 5);
        
        // Obtener moneda del sistema
        $currency = get_option('uap_currency', '$');
        
        // CARGAR COMISIONES POR RANGO DE USUARIO
        $this->load_rank_commissions($affiliate_id, $currency, $indeed_db);
        
        // CARGAR COMISIONES POR NIVEL
        $this->load_level_commissions($currency);
        
        // CARGAR COMISIONES GENERALES POR DEFECTO
        $this->load_general_commissions($currency, $indeed_db);
        
        // Obtener primera línea de descendientes
        $children = $indeed_db->mlm_get_children($affiliate_id);
        
        // BUG FIX: Validar que $children sea un array válido
        if ($this->max_depth > 0 && is_array($children) && !empty($children)) {
            $current_depth = 2;
            
            foreach ($children as $child_id) {
                // BUG FIX: Validar que child_id sea válido
                if (empty($child_id) || !is_numeric($child_id)) {
                    continue;
                }
                
                $level = 1;
                $this->add_child_to_array($child_id, $affiliate_id, $level, $indeed_db);
                $this->get_childs_for_child($child_id, $current_depth, $indeed_db);
            }
            
            error_log('✅ CV_MLM_Children: Procesados ' . count($this->children) . ' descendientes de afiliado #' . $affiliate_id);
        } else {
            error_log('ℹ️ CV_MLM_Children: No se encontraron descendientes para afiliado #' . $affiliate_id);
        }
    }
    
    /**
     * Cargar configuración de comisiones por rango de usuario
     * 
     * @param int $affiliate_id ID del afiliado
     * @param string $currency Símbolo de moneda
     * @param object $indeed_db Instancia de Indeed DB
     */
    private function load_rank_commissions($affiliate_id, $currency, $indeed_db) {
        // BUG FIX: Validar que el método exista antes de llamarlo
        if (!method_exists($indeed_db, 'get_mlm_amount_value_for_rank_by_aff_id')) {
            return;
        }
        
        $temp_data = $indeed_db->get_mlm_amount_value_for_rank_by_aff_id($affiliate_id);
        
        if ($temp_data && isset($temp_data['types']) && isset($temp_data['values'])) {
            $this->amount_per_rank_type = $temp_data['types'];
            $this->amount_per_rank_value = $temp_data['values'];
            
            // BUG FIX: Validar que sea un array antes de iterar
            if (is_array($this->amount_per_rank_type)) {
                foreach ($this->amount_per_rank_type as $key => $amount_type) {
                    if ($amount_type == 'flat') {
                        $this->amount_per_rank_type[$key] = $currency;
                    } else {
                        $this->amount_per_rank_type[$key] = '%';
                    }
                }
            }
        }
    }
    
    /**
     * Cargar configuración de comisiones por nivel MLM
     * 
     * @param string $currency Símbolo de moneda
     */
    private function load_level_commissions($currency) {
        $this->amount_per_level_type = get_option('mlm_amount_type_per_level', array());
        $this->amount_per_level_value = get_option('mlm_amount_value_per_level', array());
        
        // BUG FIX: Validar que sea un array antes de iterar
        if (is_array($this->amount_per_level_type) && !empty($this->amount_per_level_type)) {
            foreach ($this->amount_per_level_type as $key => $amount_type) {
                if ($amount_type == 'flat') {
                    $this->amount_per_level_type[$key] = $currency;
                } else {
                    $this->amount_per_level_type[$key] = '%';
                }
            }
        }
    }
    
    /**
     * Cargar configuración de comisiones generales por defecto
     * 
     * @param string $currency Símbolo de moneda
     * @param object $indeed_db Instancia de Indeed DB
     */
    private function load_general_commissions($currency, $indeed_db) {
        // BUG FIX: Validar que el método exista antes de llamarlo
        if (!method_exists($indeed_db, 'return_settings_from_wp_option')) {
            $this->general_amount_value = '';
            $this->general_amount_type = '%';
            return;
        }
        
        $general_settings = $indeed_db->return_settings_from_wp_option('mlm');
        
        // BUG FIX: Validar que $general_settings sea un array
        if (!is_array($general_settings)) {
            $general_settings = array();
        }
        
        $this->general_amount_value = isset($general_settings['uap_mlm_default_amount_value']) ? $general_settings['uap_mlm_default_amount_value'] : '';
        $this->general_amount_type = isset($general_settings['uap_mlm_default_amount_type']) ? $general_settings['uap_mlm_default_amount_type'] : 'percentage';
        
        if ($this->general_amount_type == 'flat') {
            $this->general_amount_type = $currency;
        } else {
            $this->general_amount_type = '%';
        }
    }
    
    /**
     * Añadir un descendiente al array de resultados
     * 
     * @param int $child_id ID del descendiente
     * @param int $parent_id ID del padre
     * @param int $level Nivel en la estructura
     * @param object $indeed_db Instancia de Indeed DB
     */
    private function add_child_to_array($child_id, $parent_id, $level, $indeed_db) {
        // BUG FIX: Validar que los IDs sean válidos
        if (empty($child_id) || empty($parent_id) || !is_numeric($child_id) || !is_numeric($parent_id)) {
            return;
        }
        
        $temp = array();
        
        // Información básica
        $temp['parent'] = $indeed_db->get_wp_username_by_affiliate_id($parent_id);
        $temp['parent_id'] = $parent_id;
        $temp['level'] = $level;
        $temp['username'] = $indeed_db->get_wp_username_by_affiliate_id($child_id);
        $temp['email'] = $indeed_db->get_email_by_affiliate_id($child_id);
        
        // BUG FIX: Si no se puede obtener username o email, no añadir este afiliado
        if (empty($temp['username']) && empty($temp['email'])) {
            error_log('⚠️ CV_MLM_Children: Afiliado #' . $child_id . ' sin username ni email, omitiendo...');
            return;
        }
        
        // Nombre completo
        $temp['full_name'] = $indeed_db->get_full_name_of_user($child_id);
        if (empty($temp['full_name'])) {
            $temp['full_name'] = $temp['username'];
        }
        
        // Avatar
        $childuid = $indeed_db->get_uid_by_affiliate_id($child_id);
        $temp['avatar'] = function_exists('uap_get_avatar_for_uid') ? uap_get_avatar_for_uid($childuid) : '';
        $temp['id'] = $child_id;
        $temp['uid'] = $childuid ? $childuid : 0;
        
        // Calcular comisión para este nivel
        $temp['amount_value'] = '';
        $amount_type = '';
        
        // Prioridad 1: Comisión por rango de usuario
        if (isset($this->amount_per_rank_type[$level]) && isset($this->amount_per_rank_value[$level])) {
            $temp['amount_value'] = $this->amount_per_rank_value[$level];
            if (!empty($temp['amount_value'])) {
                $amount_type = $this->amount_per_rank_type[$level];
            }
        }
        
        // Prioridad 2: Comisión por nivel MLM
        if (empty($temp['amount_value']) && isset($this->amount_per_level_type[$level]) && isset($this->amount_per_level_value[$level])) {
            $temp['amount_value'] = $this->amount_per_level_value[$level];
            if (!empty($temp['amount_value'])) {
                $amount_type = $this->amount_per_level_type[$level];
            }
        }
        
        // Prioridad 3: Comisión por defecto
        if (empty($temp['amount_value'])) {
            $temp['amount_value'] = $this->general_amount_value;
            if (!empty($temp['amount_value'])) {
                $amount_type = $this->general_amount_type;
            }
        }
        
        // BUG FIX: Solo añadir el tipo si hay un valor de comisión
        if (!empty($temp['amount_value'])) {
            $temp['amount_value'] .= ' ' . $amount_type;
        }
        
        // BUG FIX: Prevenir duplicados - no sobrescribir si ya existe
        // Esto evita bucles infinitos en estructuras MLM mal configuradas
        if (isset($this->children[$child_id])) {
            error_log('⚠️ CV_MLM_Children: Afiliado #' . $child_id . ' ya procesado, evitando duplicado');
            return;
        }
        
        // Añadir al array de resultados
        $this->children[$child_id] = $temp;
    }
    
    /**
     * Obtener resultados (todos los descendientes encontrados)
     * 
     * @return array Array con información de todos los descendientes
     */
    public function get_results() {
        return $this->children;
    }
    
    /**
     * Obtener descendientes de un hijo específico (RECURSIVO)
     * 
     * @param int $parent_id ID del padre
     * @param int $current_depth Profundidad actual
     * @param object $indeed_db Instancia de Indeed DB
     */
    private function get_childs_for_child($parent_id = 0, $current_depth = 0, $indeed_db = null) {
        // Verificar límite de profundidad
        if (!$parent_id || $current_depth > $this->max_depth) {
            return;
        }
        
        // Si no se pasó $indeed_db, usar el global
        if (!$indeed_db) {
            global $indeed_db;
        }
        
        // BUG FIX: Verificar que $indeed_db esté disponible
        if (!isset($indeed_db) || !is_object($indeed_db)) {
            error_log('⚠️ CV_MLM_Children: $indeed_db no disponible en nivel ' . $current_depth);
            return;
        }
        
        $current_depth++;
        $children = $indeed_db->mlm_get_children($parent_id);
        
        // BUG FIX: Validar que $children sea un array válido
        if (!is_array($children) || empty($children)) {
            return;
        }
        
        foreach ($children as $child_id) {
            // BUG FIX: Validar que child_id sea válido
            if (empty($child_id) || !is_numeric($child_id)) {
                continue;
            }
            
            $level = $current_depth - 1;
            $this->add_child_to_array($child_id, $parent_id, $level, $indeed_db);
            $this->get_childs_for_child($child_id, $current_depth, $indeed_db);
        }
    }
    
    /**
     * Obtener solo IDs de los descendientes
     * 
     * @return array Array de IDs de afiliados
     */
    public function get_children_ids() {
        return array_keys($this->children);
    }
    
    /**
     * Obtener descendientes de un nivel específico
     * 
     * @param int $level Nivel a filtrar (1, 2, 3, etc.)
     * @return array Array de descendientes del nivel especificado
     */
    public function get_children_by_level($level) {
        // BUG FIX: Validar que level sea un número válido
        if (!is_numeric($level) || $level < 1) {
            return array();
        }
        
        return array_filter($this->children, function($child) use ($level) {
            return isset($child['level']) && $child['level'] == $level;
        });
    }
    
    /**
     * Contar descendientes totales
     * 
     * @return int Número total de descendientes
     */
    public function count_children() {
        return count($this->children);
    }
    
    /**
     * Contar descendientes por nivel
     * 
     * @return array Array con count por nivel [1 => 5, 2 => 15, etc.]
     */
    public function count_by_level() {
        $counts = array();
        
        foreach ($this->children as $child) {
            // BUG FIX: Validar que el nivel exista en los datos del hijo
            if (!isset($child['level'])) {
                continue;
            }
            
            $level = $child['level'];
            if (!isset($counts[$level])) {
                $counts[$level] = 0;
            }
            $counts[$level]++;
        }
        
        return $counts;
    }
}

/**
 * Alias de compatibilidad con el snippet original
 * Mantener por compatibilidad con código existente
 */
if (!class_exists('MLMGetChildren_2')) {
    class MLMGetChildren_2 extends CV_MLM_Children {
        // Heredar toda la funcionalidad de CV_MLM_Children
        // Esto mantiene compatibilidad con el snippet #26
    }
}

