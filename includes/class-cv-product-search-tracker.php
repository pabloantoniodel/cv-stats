<?php
/**
 * Rastreador de búsquedas internas del catálogo de productos.
 *
 * @package CV_Stats
 */

if (!defined('ABSPATH')) {
    exit;
}

class CV_Product_Search_Tracker {

    private $table_name;
    private $standard_logged = false;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'cv_product_searches';

        register_activation_hook(CV_STATS_PLUGIN_BASENAME, array($this, 'create_table'));

        add_action('wp', array($this, 'maybe_log_standard_search'), 20);
        add_filter('aws_search_results_products_ids', array($this, 'log_aws_search'), 999, 3);
    }

    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            search_term varchar(255) NOT NULL,
            source varchar(50) NOT NULL DEFAULT 'frontend',
            results_count int(11) NOT NULL DEFAULT 0,
            user_id bigint(20) unsigned DEFAULT 0,
            ip_address varchar(100) DEFAULT '',
            user_agent text DEFAULT '',
            page_url varchar(500) DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY created_at (created_at),
            KEY search_term (search_term)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function maybe_log_standard_search() {
        if ($this->standard_logged || is_admin() || !is_search()) {
            return;
        }

        if (!$this->is_product_search_request()) {
            return;
        }

        $term = get_search_query(false);
        if ($term === '') {
            return;
        }

        global $wp_query;
        $results = isset($wp_query->found_posts) ? (int) $wp_query->found_posts : 0;

        $this->insert_record($term, 'frontend', $results);
        $this->standard_logged = true;
    }

    public function log_aws_search($posts_ids, $search_string, $data) {
        if (empty($search_string)) {
            return $posts_ids;
        }

        if (!$this->is_product_post_type($data['post_type'] ?? null)) {
            return $posts_ids;
        }

        $results = is_array($posts_ids) ? count($posts_ids) : 0;
        $this->insert_record($search_string, 'aws', $results);

        return $posts_ids;
    }

    private function insert_record($term, $source, $results) {
        global $wpdb;

        $clean_term = sanitize_text_field($term);
        if ($clean_term === '') {
            return;
        }

        $wpdb->insert(
            $this->table_name,
            array(
                'search_term'   => $clean_term,
                'source'        => sanitize_key($source),
                'results_count' => max(0, (int) $results),
                'user_id'       => get_current_user_id(),
                'ip_address'    => $this->get_user_ip(),
                'user_agent'    => $this->get_user_agent(),
                'page_url'      => $this->get_current_url(),
                'created_at'    => current_time('mysql'),
            ),
            array('%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s')
        );
    }

    private function is_product_search_request() {
        if (!$this->is_product_post_type(get_query_var('post_type'))) {
            $get_post_type = isset($_GET['post_type']) ? wp_unslash($_GET['post_type']) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if (!$this->is_product_post_type($get_post_type)) {
                return false;
            }
        }
        return true;
    }

    private function is_product_post_type($post_type) {
        if (empty($post_type)) {
            return false;
        }

        if (is_array($post_type)) {
            return in_array('product', $post_type, true);
        }

        return $post_type === 'product';
    }

    private function get_user_ip() {
        $headers = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        );

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $parts = explode(',', $ip);
                    $ip = trim($parts[0]);
                }
                return sanitize_text_field($ip);
            }
        }

        return '0.0.0.0';
    }

    private function get_user_agent() {
        return isset($_SERVER['HTTP_USER_AGENT'])
            ? substr(wp_strip_all_tags(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 500)
            : '';
    }

    private function get_current_url() {
        $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        $uri  = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $scheme = is_ssl() ? 'https://' : 'http://';
        return substr($scheme . $host . $uri, 0, 500);
    }

    public static function get_summary($date_start, $date_end) {
        global $wpdb;
        $table = $wpdb->prefix . 'cv_product_searches';

        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE created_at BETWEEN %s AND %s",
                $date_start,
                $date_end
            )
        );

        $unique = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT search_term) FROM {$table} WHERE created_at BETWEEN %s AND %s",
                $date_start,
                $date_end
            )
        );

        $avg_results = (float) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT AVG(results_count) FROM {$table} WHERE created_at BETWEEN %s AND %s",
                $date_start,
                $date_end
            )
        );

        return array(
            'total'        => $total,
            'unique_terms' => $unique,
            'avg_results'  => $avg_results ? round($avg_results, 1) : 0,
        );
    }

    public static function get_top_terms($date_start, $date_end, $limit = 10) {
        global $wpdb;
        $table = $wpdb->prefix . 'cv_product_searches';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT search_term, COUNT(*) as total, AVG(results_count) as avg_results
                 FROM {$table}
                 WHERE created_at BETWEEN %s AND %s
                 GROUP BY search_term
                 ORDER BY total DESC
                 LIMIT %d",
                $date_start,
                $date_end,
                $limit
            )
        );
    }

    public static function get_recent_searches($date_start, $date_end, $limit = 10) {
        global $wpdb;
        $table = $wpdb->prefix . 'cv_product_searches';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE created_at BETWEEN %s AND %s
                 ORDER BY created_at DESC
                 LIMIT %d",
                $date_start,
                $date_end,
                $limit
            )
        );
    }
}


