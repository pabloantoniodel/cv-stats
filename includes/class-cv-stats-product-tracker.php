<?php
/**
 * Rastreador de Productos
 * 
 * Trackea:
 * - Productos creados hoy
 * - Productos actualizados hoy
 * - Quién los creó/modificó
 * 
 * @package CV_Stats
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CV_Stats_Product_Tracker {
    
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
        // Hook para productos nuevos (cuando se publica por primera vez)
        add_action('transition_post_status', array($this, 'track_product_creation'), 10, 3);
        
        // Hook para productos actualizados
        add_action('post_updated', array($this, 'track_product_update'), 10, 3);
    }
    
    /**
     * Trackear creación de productos
     */
    public function track_product_creation($new_status, $old_status, $post) {
        // Solo trackear productos
        if ($post->post_type !== 'product') {
            return;
        }
        
        // Solo si pasa de draft/pending a publish
        if ($new_status === 'publish' && ($old_status === 'draft' || $old_status === 'pending' || $old_status === 'auto-draft')) {
            global $wpdb;
            
            $table_name = $wpdb->prefix . 'cv_product_activities';
            
            // Obtener usuario actual (quien creó el producto)
            $created_by = get_current_user_id();
            if (!$created_by) {
                $created_by = $post->post_author; // Fallback al autor del post
            }
            
            // Verificar si ya existe un registro para este producto HOY
            $today_start = date('Y-m-d 00:00:00');
            $existing = $wpdb->get_var($wpdb->prepare("
                SELECT id FROM {$table_name}
                WHERE product_id = %d
                AND activity_type = 'created'
                AND activity_time >= %s
            ", $post->ID, $today_start));
            
            // Si no existe, insertar
            if (!$existing) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'product_id' => $post->ID,
                        'vendor_id' => $post->post_author,
                        'activity_type' => 'created',
                        'modified_by' => $created_by,
                        'activity_time' => current_time('mysql'),
                        'ip_address' => $this->get_ip_address(),
                        'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : ''
                    ),
                    array('%d', '%d', '%s', '%d', '%s', '%s', '%s')
                );
                
                error_log("✅ CV Stats: Producto creado trackeado - ID: {$post->ID}, Vendedor: {$post->post_author}, Creado por: {$created_by}");
            }
        }
    }
    
    /**
     * Trackear actualización de productos
     */
    public function track_product_update($post_id, $post_after, $post_before) {
        // Solo trackear productos publicados
        if ($post_after->post_type !== 'product' || $post_after->post_status !== 'publish') {
            return;
        }
        
        // Si es la misma fecha de modificación, no trackear (evita duplicados en el mismo request)
        if ($post_after->post_modified === $post_before->post_modified) {
            return;
        }
        
        // No trackear si acabamos de crear el producto (evita doble registro)
        $created_recently = (strtotime($post_after->post_date) > (time() - 10)); // 10 segundos
        if ($created_recently) {
            return;
        }
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cv_product_activities';
        
        // Obtener usuario actual (quien modificó el producto)
        $modified_by = get_current_user_id();
        if (!$modified_by) {
            $modified_by = $post_after->post_author; // Fallback al autor
        }
        
        // Insertar actividad de actualización
        $wpdb->insert(
            $table_name,
            array(
                'product_id' => $post_id,
                'vendor_id' => $post_after->post_author,
                'activity_type' => 'updated',
                'modified_by' => $modified_by,
                'activity_time' => current_time('mysql'),
                'ip_address' => $this->get_ip_address(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : ''
            ),
            array('%d', '%d', '%s', '%d', '%s', '%s', '%s')
        );
        
        error_log("✅ CV Stats: Producto actualizado trackeado - ID: {$post_id}, Vendedor: {$post_after->post_author}, Modificado por: {$modified_by}");
    }
    
    /**
     * Obtener dirección IP del usuario
     */
    private function get_ip_address() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return sanitize_text_field($ip);
    }
    
    /**
     * Obtener productos creados hoy
     */
    public static function get_todays_created_products() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cv_product_activities';
        $today_start = date('Y-m-d 00:00:00');
        $today_end = date('Y-m-d 23:59:59');
        
        $query = $wpdb->prepare("
            SELECT *
            FROM {$table_name}
            WHERE activity_type = 'created'
            AND activity_time >= %s
            AND activity_time <= %s
            ORDER BY activity_time DESC
        ", $today_start, $today_end);
        
        $results = $wpdb->get_results($query);
        
        // Enriquecer con datos del producto y usuarios
        $products = array();
        foreach ($results as $row) {
            $product = wc_get_product($row->product_id);
            if (!$product) {
                continue; // Producto eliminado
            }
            
            $vendor = get_userdata($row->vendor_id);
            $created_by_user = get_userdata($row->modified_by);
            
            $products[] = array(
                'product_id' => $row->product_id,
                'product_name' => $product->get_name(),
                'product_url' => get_permalink($row->product_id),
                'vendor_id' => $row->vendor_id,
                'vendor_name' => $vendor ? $vendor->display_name : 'Usuario desconocido',
                'vendor_username' => $vendor ? $vendor->user_login : '',
                'created_by_id' => $row->modified_by,
                'created_by_name' => $created_by_user ? $created_by_user->display_name : 'Desconocido',
                'created_by_username' => $created_by_user ? $created_by_user->user_login : '',
                'activity_time' => $row->activity_time,
                'ip_address' => $row->ip_address
            );
        }
        
        return $products;
    }
    
    /**
     * Obtener productos actualizados hoy
     */
    public static function get_todays_updated_products() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cv_product_activities';
        $today_start = date('Y-m-d 00:00:00');
        $today_end = date('Y-m-d 23:59:59');
        
        $query = $wpdb->prepare("
            SELECT *
            FROM {$table_name}
            WHERE activity_type = 'updated'
            AND activity_time >= %s
            AND activity_time <= %s
            ORDER BY activity_time DESC
        ", $today_start, $today_end);
        
        $results = $wpdb->get_results($query);
        
        // Enriquecer con datos del producto y usuarios
        $products = array();
        foreach ($results as $row) {
            $product = wc_get_product($row->product_id);
            if (!$product) {
                continue; // Producto eliminado
            }
            
            $vendor = get_userdata($row->vendor_id);
            $modified_by_user = get_userdata($row->modified_by);
            
            $products[] = array(
                'product_id' => $row->product_id,
                'product_name' => $product->get_name(),
                'product_url' => get_permalink($row->product_id),
                'vendor_id' => $row->vendor_id,
                'vendor_name' => $vendor ? $vendor->display_name : 'Usuario desconocido',
                'vendor_username' => $vendor ? $vendor->user_login : '',
                'modified_by_id' => $row->modified_by,
                'modified_by_name' => $modified_by_user ? $modified_by_user->display_name : 'Desconocido',
                'modified_by_username' => $modified_by_user ? $modified_by_user->user_login : '',
                'activity_time' => $row->activity_time,
                'ip_address' => $row->ip_address
            );
        }
        
        return $products;
    }
    
    /**
     * Obtener contadores de hoy
     */
    public static function get_todays_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cv_product_activities';
        $today_start = date('Y-m-d 00:00:00');
        $today_end = date('Y-m-d 23:59:59');
        
        $created_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$table_name}
            WHERE activity_type = 'created'
            AND activity_time >= %s
            AND activity_time <= %s
        ", $today_start, $today_end));
        
        $updated_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$table_name}
            WHERE activity_type = 'updated'
            AND activity_time >= %s
            AND activity_time <= %s
        ", $today_start, $today_end));
        
        return array(
            'created' => intval($created_count),
            'updated' => intval($updated_count)
        );
    }
    
    /**
     * Obtener productos creados por rango de fechas
     */
    public static function get_created_products_by_date_range($start_date, $end_date) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cv_product_activities';
        
        $query = $wpdb->prepare("
            SELECT *
            FROM {$table_name}
            WHERE activity_type = 'created'
            AND activity_time >= %s AND activity_time <= %s
            ORDER BY activity_time DESC
        ", $start_date, $end_date);
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Obtener productos actualizados por rango de fechas
     */
    public static function get_updated_products_by_date_range($start_date, $end_date) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cv_product_activities';
        
        $query = $wpdb->prepare("
            SELECT *
            FROM {$table_name}
            WHERE activity_type = 'updated'
            AND activity_time >= %s AND activity_time <= %s
            ORDER BY activity_time DESC
        ", $start_date, $end_date);
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Obtener estadísticas de productos por rango de fechas
     */
    public static function get_stats_by_date_range($start_date, $end_date) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cv_product_activities';
        
        $created_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$table_name}
            WHERE activity_type = 'created'
            AND activity_time >= %s AND activity_time <= %s
        ", $start_date, $end_date));
        
        $updated_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$table_name}
            WHERE activity_type = 'updated'
            AND activity_time >= %s
            AND activity_time <= %s
        ", $start_date, $end_date));
        
        return array(
            'created' => intval($created_count),
            'updated' => intval($updated_count)
        );
    }
}

new CV_Stats_Product_Tracker();


