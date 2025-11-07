<?php
/**
 * Clase para mostrar productos destacados/anuncios en el shop
 * Migrado desde snippet 33
 */

if (!defined('ABSPATH')) {
    exit;
}

class CV_Featured_Products {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Solo activar si la opción está habilitada
        $config = get_option('cv_commissions_config', array());
        $enabled = isset($config['featured_products_enabled']) ? $config['featured_products_enabled'] : false;
        
        if ($enabled) {
            add_action('woocommerce_after_shop_loop', array($this, 'mostrar_articulos_anuncio'), 10);
            add_action('woocommerce_no_products_found', array($this, 'mostrar_articulos_anunciante'), 10);
            
            error_log('✅ CV Featured Products: Activado');
        } else {
            error_log('⏸️ CV Featured Products: Desactivado en configuración');
        }
    }
    
    /**
     * Mostrar artículos de anuncio para el usuario logueado
     */
    public function mostrar_articulos_anuncio() {
        global $indeed_db, $wpdb;
        
        if (!is_user_logged_in()) {
            return null;
        }
        
        $products = "";
        $table = $wpdb->prefix . 'cvapp_anuncios';
        $aff_id = $indeed_db->get_affiliate_id_by_wpuid(get_current_user_id());
        $cols = $wpdb->get_results("SELECT product_id FROM $table WHERE affiliate_id=" . $aff_id . " AND visto=0");
        
        if ($cols) {
            $index = 0;
            foreach ($cols as $item) {
                if ($index == 0) {
                    $products = $item->product_id;
                } else {
                    $products .= ',' . $item->product_id;
                }
                $index++;
            }
            
            if ($products != "") {
                echo '<div class="has-text-align-center productos_patrocinados has-luminous-vivid-orange-color has-text-color has-medium-font-size" style="text-align:center"><em><strong>De tu interés</strong></em></div>';
                echo do_shortcode('[products ids="' . $products . '" limit="' . $index . '" orderby="popularity"]');
            }
        }
    }
    
    /**
     * Mostrar artículos del anunciante cuando hay búsqueda
     */
    public function mostrar_articulos_anunciante() {
        if (is_shop() == false) {
            return "";
        }
        
        global $WCFM, $WCFMmp;
        
        // Toma Search query y saca todos los artículos de un anunciante o anunciantes determinados
        $products_ids = $this->analize_query_vars_products();
        
        if ($products_ids) {
            $n = 0;
            $products = '';
            foreach ($products_ids as $item) {
                if ($n == 0) {
                    $products = (string)$item;
                } else {
                    $products .= "," . $item;
                }
                $n++;
            }
            
            if (isset($_REQUEST['s']) && $_REQUEST['s'] !== '') {
                echo '<div class="has-text-align-center productos_patrocinados has-luminous-vivid-orange-color has-text-color has-medium-font-size" style="text-align:center"><em><strong>Por Anunciante</strong></em></div>';
                echo do_shortcode('[products ids="' . $products . '"]');
            }
        }
    }
    
    /**
     * Analizar query vars para obtener productos
     */
    private function analize_query_vars_products() {
        global $WCFM, $WCFMmp;
        
        $search_category = isset($_REQUEST['wcfmmp_store_category']) ? sanitize_text_field($_REQUEST['wcfmmp_store_category']) : '';
        
        if (isset($_GET['s'])) {
            $search_term     = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
            error_log("search_term=$search_term");
            
            $search_category = isset($_REQUEST['wcfmmp_store_category']) ? sanitize_text_field($_REQUEST['wcfmmp_store_category']) : '';
            $pagination_base = isset($_REQUEST['pagination_base']) ? sanitize_text_field($_REQUEST['pagination_base']) : '';
            $paged           = 1;
            $per_row         = isset($_REQUEST['per_row']) ? absint($_REQUEST['per_row']) : 3;
            $per_page        = isset($_REQUEST['per_page']) ? absint($_REQUEST['per_page']) : 24;
            $includes        = isset($_REQUEST['includes']) ? sanitize_text_field($_REQUEST['includes']) : '';
            $excludes        = isset($_REQUEST['excludes']) ? sanitize_text_field($_REQUEST['excludes']) : '';
            $orderby         = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'newness_asc';
            $has_orderby     = isset($_REQUEST['has_orderby']) ? sanitize_text_field($_REQUEST['has_orderby']) : '';
            $has_product     = isset($_REQUEST['has_product']) ? sanitize_text_field($_REQUEST['has_product']) : '';
            $sidebar         = isset($_REQUEST['sidebar']) ? sanitize_text_field($_REQUEST['sidebar']) : '';
            $theme           = isset($_REQUEST['theme']) ? sanitize_text_field($_REQUEST['theme']) : 'simple';
            $search_data     = array();
            
            if (isset($_REQUEST['search_data'])) {
                parse_str($_REQUEST['search_data'], $search_data);
            }
            
            $length  = absint($per_page);
            $offset  = ($paged - 1) * $length;
            
            $search_data['excludes'] = $excludes;
            
            if ($includes) {
                $includes = explode(",", $includes);
            } else {
                $includes = array();
            }
            
            $stores = $WCFMmp->wcfmmp_vendor->wcfmmp_search_vendor_list(true, $offset, $length, $search_term, $search_category, $search_data, $has_product, $includes);
            error_log('CVAPP add_query_vars_filter STORES');
            
            $keys = array_keys($stores);
            $products_ids = array();
            
            if ($search_term !== '') {
                foreach ($keys as $item) {
                    error_log('CVAPP add_query_vars_filter ITEM');
                    error_log(print_r($item, true));
                    
                    $vendor_products = $WCFM->wcfm_vendor_support->wcfm_get_products_by_vendor($item, 'publish');
                    error_log('CVAPP add_query_vars_filter PRODUCTS');
                    error_log(print_r($vendor_products, true));
                    
                    foreach ($vendor_products as $p) {
                        array_push($products_ids, $p->ID);
                    }
                }
            }
            
            error_log('CVAPP add_query_vars_filter Array filter PRODUCTS_IDS');
            error_log(print_r($products_ids, true));
            error_log('CVAPP add_query_vars_filter busqueda=' . $_GET['s']);
            
            return $products_ids;
        }
        
        return false;
    }
}

new CV_Featured_Products();


