<?php
/**
 * Estadísticas de productos usando directamente WP_Query
 * Más simple y confiable que el tracker
 * 
 * @package CV_Stats
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CV_Stats_Product_Simple {
    
    /**
     * Obtener productos creados en un rango de fechas
     */
    public static function get_created_products($date_from, $date_to) {
        $args = array(
            'post_type' => 'product',
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => -1,
            'date_query' => array(
                array(
                    'column' => 'post_date',
                    'after' => $date_from . ' 00:00:00',
                    'before' => $date_to . ' 23:59:59',
                    'inclusive' => true,
                ),
            ),
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        $query = new WP_Query($args);
        $products = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product_id = get_the_ID();
                $product = wc_get_product($product_id);
                
                if (!$product) {
                    continue;
                }
                
                $author = get_userdata(get_the_author_meta('ID'));
                
                $products[] = array(
                    'product_id' => $product_id,
                    'product_name' => get_the_title(),
                    'product_url' => get_permalink($product_id),
                    'vendor_id' => get_the_author_meta('ID'),
                    'vendor_name' => $author ? $author->display_name : 'Desconocido',
                    'vendor_username' => $author ? $author->user_login : '',
                    'created_by_id' => get_the_author_meta('ID'),
                    'created_by_name' => $author ? $author->display_name : 'Desconocido',
                    'created_by_username' => $author ? $author->user_login : '',
                    'activity_time' => get_the_date('Y-m-d H:i:s'),
                    'post_status' => get_post_status()
                );
            }
            wp_reset_postdata();
        }
        
        return $products;
    }
    
    /**
     * Obtener productos modificados en un rango de fechas
     * (que NO fueron creados en el mismo día)
     */
    public static function get_updated_products($date_from, $date_to) {
        global $wpdb;
        
        $date_from_sql = $date_from . ' 00:00:00';
        $date_to_sql = $date_to . ' 23:59:59';
        
        // Query SQL personalizada para productos modificados PERO NO creados el mismo día
        $query = $wpdb->prepare("
            SELECT ID, post_author, post_title, post_date, post_modified, post_status
            FROM {$wpdb->posts}
            WHERE post_type = 'product'
            AND post_status IN ('publish', 'draft', 'pending', 'private')
            AND post_modified >= %s
            AND post_modified <= %s
            AND DATE(post_date) != DATE(post_modified)
            ORDER BY post_modified DESC
        ", $date_from_sql, $date_to_sql);
        
        $results = $wpdb->get_results($query);
        $products = array();
        
        foreach ($results as $row) {
            $product = wc_get_product($row->ID);
            if (!$product) {
                continue;
            }
            
            $author = get_userdata($row->post_author);
            
            // Intentar obtener quién modificó (si es diferente al autor)
            $modified_by = get_post_meta($row->ID, '_edit_last', true);
            if ($modified_by) {
                $modified_by_user = get_userdata($modified_by);
            } else {
                $modified_by = $row->post_author;
                $modified_by_user = $author;
            }
            
            $products[] = array(
                'product_id' => $row->ID,
                'product_name' => $row->post_title,
                'product_url' => get_permalink($row->ID),
                'vendor_id' => $row->post_author,
                'vendor_name' => $author ? $author->display_name : 'Desconocido',
                'vendor_username' => $author ? $author->user_login : '',
                'modified_by_id' => $modified_by,
                'modified_by_name' => $modified_by_user ? $modified_by_user->display_name : 'Desconocido',
                'modified_by_username' => $modified_by_user ? $modified_by_user->user_login : '',
                'activity_time' => $row->post_modified,
                'post_status' => $row->post_status,
                'created_at' => $row->post_date
            );
        }
        
        return $products;
    }
    
    /**
     * Obtener productos afiliados en un rango de fechas
     */
    public static function get_affiliated_products($date_from, $date_to) {
        global $wpdb;
        
        $date_from_sql = $date_from . ' 00:00:00';
        $date_to_sql = $date_to . ' 23:59:59';
        
        $query = $wpdb->prepare("
            SELECT 
                a.id,
                a.vendor_id,
                a.product_id,
                a.product_owner_id,
                a.commission_rate,
                a.commission_type,
                a.status,
                a.created_at,
                p.post_title as product_name
            FROM {$wpdb->prefix}wcfm_product_affiliates a
            LEFT JOIN {$wpdb->posts} p ON a.product_id = p.ID
            WHERE a.created_at >= %s
            AND a.created_at <= %s
            AND a.status = 'active'
            ORDER BY a.created_at DESC
        ", $date_from_sql, $date_to_sql);
        
        $results = $wpdb->get_results($query);
        $products = array();
        
        foreach ($results as $row) {
            // Datos del vendedor (afiliado)
            $vendor = get_userdata($row->vendor_id);
            
            // Datos del propietario original
            $owner = get_userdata($row->product_owner_id);
            
            $products[] = array(
                'id' => $row->id,
                'product_id' => $row->product_id,
                'product_name' => $row->product_name,
                'product_url' => get_permalink($row->product_id),
                'vendor_id' => $row->vendor_id,
                'vendor_name' => $vendor ? $vendor->display_name : 'Desconocido',
                'vendor_username' => $vendor ? $vendor->user_login : '',
                'owner_id' => $row->product_owner_id,
                'owner_name' => $owner ? $owner->display_name : 'Desconocido',
                'owner_username' => $owner ? $owner->user_login : '',
                'commission_rate' => $row->commission_rate,
                'commission_type' => $row->commission_type,
                'status' => $row->status,
                'created_at' => $row->created_at
            );
        }
        
        return $products;
    }
    
    /**
     * Obtener estadísticas resumidas
     */
    public static function get_stats($date_from, $date_to) {
        $created = self::get_created_products($date_from, $date_to);
        $updated = self::get_updated_products($date_from, $date_to);
        $affiliated = self::get_affiliated_products($date_from, $date_to);
        
        return array(
            'created' => count($created),
            'updated' => count($updated),
            'affiliated' => count($affiliated),
            'total' => count($created) + count($updated)
        );
    }
}


