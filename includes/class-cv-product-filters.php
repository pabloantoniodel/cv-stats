<?php
/**
 * Filtros de Productos - Oculta productos específicos del catálogo
 * 
 * Oculta productos que solo deben usarse internamente (como "ticket de compra")
 * del catálogo público, búsquedas, widgets y shortcodes.
 * 
 * @package CV_Commissions
 * @since 1.0.1
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class CV_Product_Filters {

    /**
     * Lista de slugs de productos a ocultar del catálogo
     * 
     * Estos productos se mantienen disponibles para uso interno
     * pero no aparecen en el catálogo público ni búsquedas.
     * 
     * @var array
     */
    private static $hidden_products = array(
        'ticket-de-compra',
        'ticket-compra',
        'wallet-topup', // Producto de recarga de wallet
    );

    /**
     * Constructor
     */
    public function __construct() {
        // Filtrar productos del catálogo principal (shop, categorías)
        add_action('pre_get_posts', array($this, 'hide_products_from_catalog'), 10);
        
        // Filtrar productos de las búsquedas
        add_action('pre_get_posts', array($this, 'hide_products_from_search'), 10);
        
        // Filtrar productos de los widgets de WooCommerce
        add_filter('woocommerce_products_widget_query_args', array($this, 'filter_widget_products'), 10);
        
        // Filtrar productos de los shortcodes de WooCommerce ([products], [recent_products], etc)
        add_filter('woocommerce_shortcode_products_query', array($this, 'filter_shortcode_products'), 10);
        
        // Filtrar de WCFM si está activo
        if (class_exists('WCFM')) {
            add_filter('wcfm_products_args', array($this, 'filter_wcfm_products'), 10);
        }
        
        // Filtrar de tiendas de vendedores
        add_filter('wcfmmp_store_products_args', array($this, 'filter_store_products'), 10);
        
        // Hook para permitir añadir más productos vía filtro
        add_filter('cv_hidden_products', array($this, 'get_hidden_products'), 10);
    }

    /**
     * Obtener lista de productos ocultos
     * 
     * Permite a otros plugins/temas añadir productos a la lista
     * usando el filtro 'cv_hidden_products_list'
     * 
     * @return array Lista de slugs de productos a ocultar
     */
    public function get_hidden_products() {
        return apply_filters('cv_hidden_products_list', self::$hidden_products);
    }

    /**
     * Ocultar productos del catálogo (shop, categorías, etiquetas)
     * 
     * @param WP_Query $query Query de WordPress
     */
    public function hide_products_from_catalog($query) {
        // Solo en el frontend y en queries principales
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        // Solo en páginas de catálogo de productos
        if (!is_post_type_archive('product') && 
            !is_tax('product_cat') && 
            !is_tax('product_tag') && 
            !is_shop()) {
            return;
        }

        $this->apply_product_filter($query);
    }

    /**
     * Ocultar productos de las búsquedas
     * 
     * @param WP_Query $query Query de WordPress
     */
    public function hide_products_from_search($query) {
        // Solo en el frontend y en búsquedas
        if (is_admin() || !$query->is_search()) {
            return;
        }

        // Solo si se buscan productos
        $post_type = $query->get('post_type');
        if (empty($post_type)) {
            // Búsqueda global, aplicar filtro
            $this->apply_product_filter($query);
        } elseif ($post_type === 'product' || in_array('product', (array) $post_type)) {
            // Búsqueda específica de productos
            $this->apply_product_filter($query);
        }
    }

    /**
     * Aplicar filtro de productos a una query
     * 
     * @param WP_Query $query Query de WordPress
     */
    private function apply_product_filter($query) {
        $hidden_products = $this->get_hidden_products();
        
        if (empty($hidden_products)) {
            return;
        }

        // Obtener IDs de los productos a ocultar
        $hidden_product_ids = $this->get_product_ids_by_slugs($hidden_products);

        if (empty($hidden_product_ids)) {
            return;
        }

        // Excluir productos de la query
        $post__not_in = $query->get('post__not_in');
        if (!is_array($post__not_in)) {
            $post__not_in = array();
        }

        $post__not_in = array_merge($post__not_in, $hidden_product_ids);
        $query->set('post__not_in', array_unique($post__not_in));

        error_log('CV Product Filters: Ocultados ' . count($hidden_product_ids) . ' productos del catálogo');
    }

    /**
     * Filtrar productos de widgets de WooCommerce
     * 
     * @param array $query_args Argumentos de la query
     * @return array Argumentos modificados
     */
    public function filter_widget_products($query_args) {
        $hidden_products = $this->get_hidden_products();
        $hidden_product_ids = $this->get_product_ids_by_slugs($hidden_products);

        if (!empty($hidden_product_ids)) {
            if (!isset($query_args['post__not_in'])) {
                $query_args['post__not_in'] = array();
            }
            $query_args['post__not_in'] = array_merge(
                (array) $query_args['post__not_in'], 
                $hidden_product_ids
            );
            $query_args['post__not_in'] = array_unique($query_args['post__not_in']);
        }

        return $query_args;
    }

    /**
     * Filtrar productos de shortcodes de WooCommerce
     * 
     * @param array $query_args Argumentos de la query
     * @return array Argumentos modificados
     */
    public function filter_shortcode_products($query_args) {
        $hidden_products = $this->get_hidden_products();
        $hidden_product_ids = $this->get_product_ids_by_slugs($hidden_products);

        if (!empty($hidden_product_ids)) {
            if (!isset($query_args['post__not_in'])) {
                $query_args['post__not_in'] = array();
            }
            $query_args['post__not_in'] = array_merge(
                (array) $query_args['post__not_in'], 
                $hidden_product_ids
            );
            $query_args['post__not_in'] = array_unique($query_args['post__not_in']);
        }

        return $query_args;
    }

    /**
     * Filtrar productos de WCFM
     * 
     * @param array $args Argumentos de la query
     * @return array Argumentos modificados
     */
    public function filter_wcfm_products($args) {
        $hidden_products = $this->get_hidden_products();
        $hidden_product_ids = $this->get_product_ids_by_slugs($hidden_products);

        if (!empty($hidden_product_ids)) {
            if (!isset($args['post__not_in'])) {
                $args['post__not_in'] = array();
            }
            $args['post__not_in'] = array_merge(
                (array) $args['post__not_in'], 
                $hidden_product_ids
            );
            $args['post__not_in'] = array_unique($args['post__not_in']);
        }

        return $args;
    }

    /**
     * Filtrar productos de tiendas de vendedores
     * 
     * @param array $args Argumentos de la query
     * @return array Argumentos modificados
     */
    public function filter_store_products($args) {
        $hidden_products = $this->get_hidden_products();
        $hidden_product_ids = $this->get_product_ids_by_slugs($hidden_products);

        if (!empty($hidden_product_ids)) {
            if (!isset($args['post__not_in'])) {
                $args['post__not_in'] = array();
            }
            $args['post__not_in'] = array_merge(
                (array) $args['post__not_in'], 
                $hidden_product_ids
            );
            $args['post__not_in'] = array_unique($args['post__not_in']);
        }

        return $args;
    }

    /**
     * Obtener IDs de productos por sus slugs
     * 
     * @param array $slugs Lista de slugs de productos
     * @return array Lista de IDs de productos
     */
    private function get_product_ids_by_slugs($slugs) {
        if (empty($slugs)) {
            return array();
        }

        global $wpdb;

        // Escapar slugs para SQL
        $slugs_escaped = array_map('esc_sql', $slugs);
        $slugs_sql = "'" . implode("','", $slugs_escaped) . "'";

        $query = "
            SELECT ID 
            FROM {$wpdb->posts} 
            WHERE post_type = 'product' 
            AND post_name IN ({$slugs_sql})
            AND post_status IN ('publish', 'private', 'draft')
        ";

        $product_ids = $wpdb->get_col($query);

        return array_map('intval', $product_ids);
    }

    /**
     * Añadir un producto a la lista de ocultos
     * 
     * @param string $slug Slug del producto
     */
    public static function add_hidden_product($slug) {
        if (!in_array($slug, self::$hidden_products)) {
            self::$hidden_products[] = sanitize_title($slug);
        }
    }

    /**
     * Remover un producto de la lista de ocultos
     * 
     * @param string $slug Slug del producto
     */
    public static function remove_hidden_product($slug) {
        $key = array_search($slug, self::$hidden_products);
        if ($key !== false) {
            unset(self::$hidden_products[$key]);
            self::$hidden_products = array_values(self::$hidden_products); // Reindexar
        }
    }

    /**
     * Obtener productos ocultos actuales
     * 
     * @return array Lista de slugs de productos ocultos
     */
    public static function get_hidden_products_list() {
        return self::$hidden_products;
    }

    /**
     * Verificar si un producto está oculto
     * 
     * @param string|int $product ID o slug del producto
     * @return bool True si está oculto, false si no
     */
    public static function is_product_hidden($product) {
        if (is_numeric($product)) {
            // Es un ID, obtener el slug
            $product_post = get_post($product);
            if (!$product_post) {
                return false;
            }
            $slug = $product_post->post_name;
        } else {
            $slug = $product;
        }

        return in_array($slug, self::$hidden_products);
    }
}

// Inicializar la clase
new CV_Product_Filters();












