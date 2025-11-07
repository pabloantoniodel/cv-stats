<?php
/**
 * CV My Network Endpoint
 *
 * Maneja el endpoint "mired" (Tarjeta fidelización) en My Account
 *
 * @package CV_Commissions
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CV_My_Network_Endpoint {

    /**
     * Constructor
     */
    public function __construct() {
        // Registrar endpoint
        add_action('init', array($this, 'add_endpoints'));
        
        // Añadir query vars
        add_filter('query_vars', array($this, 'add_query_vars'), 0);
        
        // Añadir al menú de My Account
        add_filter('woocommerce_account_menu_items', array($this, 'add_menu_item'), 1);
        
        // Contenido del endpoint
        add_action('woocommerce_account_mired_endpoint', array($this, 'endpoint_content'));
        
        // AJAX handlers
        add_action('wp_ajax_cv_get_network_members', array($this, 'ajax_get_network_members'));
        add_action('wp_ajax_cv_get_user_avatar', array($this, 'ajax_get_user_avatar'));
        
        // Flush rewrite rules on activation
        register_activation_hook(CV_COMMISSIONS_FILE, array($this, 'flush_rewrite_rules'));
    }

    /**
     * Registrar endpoints
     */
    public function add_endpoints() {
        add_rewrite_endpoint('mired', EP_ROOT | EP_PAGES);
    }

    /**
     * Añadir query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'mired';
        return $vars;
    }

    /**
     * Flush rewrite rules
     */
    public function flush_rewrite_rules() {
        add_rewrite_endpoint('mired', EP_ROOT | EP_PAGES);
        flush_rewrite_rules();
    }

    /**
     * Añadir item al menú de My Account
     */
    public function add_menu_item($items) {
        $items['mired'] = 'Mi Red';
        return $items;
    }

    /**
     * Contenido del endpoint
     */
    public function endpoint_content() {
        $this->render_my_network_page(get_current_user_id());
    }

    /**
     * Obtener sponsor del usuario
     */
    private function get_sponsor() {
        global $indeed_db;
        
        $mlmParent = $indeed_db->mlm_get_parent($indeed_db->get_affiliate_id_by_wpuid(get_current_user_id()));
        $parentUid = $indeed_db->get_uid_by_affiliate_id($mlmParent);
        
        return array(
            'parent_id' => $parentUid,
            'parent_full_name' => $indeed_db->get_full_name_of_user($mlmParent),
            'parent_username' => $indeed_db->get_username_by_wpuid($parentUid)
        );
    }

    /**
     * Renderizar página de Mi Red
     */
    private function render_my_network_page($uid) {
        global $wpdb, $indeed_db;

        $a = $indeed_db->get_affiliate_id_by_wpuid($uid);
        $user = get_user_by('id', $uid);
        $sponsor = $this->get_sponsor();

        ?>
        <div class="cv-my-network-page">
            
            <!-- SECCIÓN SPONSOR -->
            <div class="cv-sponsor-section" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 20px 0; font-size: 20px; font-weight: 600;">👤 Mi Sponsor</h3>
                
                <?php
                $sponsor_user = get_user_by('login', $sponsor['parent_username']);
                if ($sponsor_user) {
                    $sponsor_first_name = get_user_meta($sponsor_user->ID, 'first_name', true);
                    $sponsor_last_name = get_user_meta($sponsor_user->ID, 'last_name', true);
                    $sponsor_full_name = trim($sponsor_first_name . ' ' . $sponsor_last_name);
                    
                    // Avatar del sponsor (mismo método que la tarjeta de visita)
                    global $wpdb;
                    $blog_id = get_current_blog_id();
                    $avatar_meta_key = $wpdb->get_blog_prefix($blog_id) . 'user_avatar';
                    $avatar_attachment_id = get_user_meta($sponsor_user->ID, $avatar_meta_key, true);
                    
                    if ($avatar_attachment_id && wp_attachment_is_image($avatar_attachment_id)) {
                        $avatar_data = wp_get_attachment_image_src($avatar_attachment_id, 'thumbnail');
                        $sponsor_avatar_url = $avatar_data ? $avatar_data[0] : get_avatar_url($sponsor_user->ID, array('size' => 80));
                    } else {
                        $sponsor_avatar_url = get_avatar_url($sponsor_user->ID, array('size' => 80));
                    }
                    
                    $sponsor_avatar = '<img src="' . esc_url($sponsor_avatar_url) . '" alt="' . esc_attr($sponsor_full_name) . '" class="avatar-sponsor" width="80" height="80">';
                    
                    ?>
                    <div style="display: flex; align-items: center; gap: 20px; background: rgba(255,255,255,0.1); padding: 20px; border-radius: 10px; backdrop-filter: blur(10px);">
                        <div style="flex-shrink: 0;">
                            <?php echo $sponsor_avatar; ?>
                        </div>
                        <div style="flex: 1;">
                            <p style="margin: 0 0 5px 0; font-size: 18px; font-weight: 600;">
                                <?php echo esc_html(!empty($sponsor_full_name) ? $sponsor_full_name : $sponsor['parent_username']); ?>
                            </p>
                            <p style="margin: 0; font-size: 14px; opacity: 0.9;">
                                @<?php echo esc_html($sponsor['parent_username']); ?>
                            </p>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>

            <!-- NAVEGACIÓN JERÁRQUICA DE MI RED -->
            <div class="cv-network-hierarchy-section">
                <h3 style="margin: 0 0 20px 0; color: #667eea; font-size: 20px; font-weight: 600;">
                    👥 Mi Red de Fidelización
                </h3>

                <?php
                if (isset($_GET['exito'])) {
                    echo '<div style="background: #d4edda; border: 1px solid #28a745; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;">✅ Cambios realizados con éxito</div>';
                }
                ?>

                <!-- Breadcrumb de navegación -->
                <div id="cv-network-breadcrumb" style="margin-bottom: 20px; padding: 15px; background: #f5f7fa; border-radius: 8px; display: none;">
                    <button onclick="cvNetworkGoBack()" style="background: #667eea; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600;">
                        ← Volver al nivel anterior
                    </button>
                    <span id="cv-breadcrumb-text" style="margin-left: 15px; color: #666; font-size: 14px;"></span>
                </div>

                <!-- Contenedor de miembros -->
                <div id="cv-network-members-container"></div>

                <script>
                // Estado de navegación
                let cvNetworkState = {
                    currentLevel: 0,
                    currentParent: <?php echo $a; ?>,
                    history: [],
                    maxLevel: 10
                };

                // Obtener avatar de usuario
                function cvGetUserAvatar(userId, callback) {
                    jQuery.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'cv_get_user_avatar',
                            user_id: userId
                        },
                        success: function(response) {
                            if (response.success) {
                                callback(response.data.avatar_url);
                            } else {
                                callback('<?php echo esc_url(get_avatar_url(0, array('size' => 60))); ?>');
                            }
                        },
                        error: function() {
                            callback('<?php echo esc_url(get_avatar_url(0, array('size' => 60))); ?>');
                        }
                    });
                }

                // Cargar miembros de un nivel
                function cvLoadNetworkLevel(parentId, level) {
                    const container = jQuery('#cv-network-members-container');
                    container.html('<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 32px; color: #667eea;"></i></div>');

                    jQuery.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'cv_get_network_members',
                            parent_id: parentId,
                            level: level
                        },
                        success: function(response) {
                            if (response.success && response.data.members.length > 0) {
                                let html = '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;">';
                                
                                response.data.members.forEach(function(member) {
                                    const hasChildren = member.children_count > 0;
                                    const cardClass = hasChildren ? 'cv-member-card-clickable' : 'cv-member-card';
                                    
                                    html += '<div class="' + cardClass + '" data-user-id="' + member.user_id + '" data-affiliate-id="' + member.affiliate_id + '" data-has-children="' + hasChildren + '">';
                                    html += '  <div class="cv-member-avatar-container">';
                                    html += '    <img src="' + member.avatar + '" alt="' + member.name + '" class="cv-member-avatar">';
                                    if (hasChildren) {
                                        html += '    <div class="cv-member-badge">' + member.children_count + '</div>';
                                    }
                                    html += '  </div>';
                                    html += '  <div class="cv-member-info">';
                                    html += '    <h4>' + member.name + '</h4>';
                                    html += '    <p class="cv-member-email">' + member.email + '</p>';
                                    html += '    <span class="cv-member-level">Nivel ' + member.level + '</span>';
                                    html += '  </div>';
                                    html += '</div>';
                                });
                                
                                html += '</div>';
                                container.html(html);
                                
                                // Añadir eventos de clic
                                jQuery('.cv-member-card-clickable').on('click', function() {
                                    const affiliateId = jQuery(this).data('affiliate-id');
                                    const hasChildren = jQuery(this).data('has-children');
                                    
                                    if (hasChildren && cvNetworkState.currentLevel < cvNetworkState.maxLevel) {
                                        cvNetworkState.history.push({
                                            parent: cvNetworkState.currentParent,
                                            level: cvNetworkState.currentLevel
                                        });
                                        cvNetworkState.currentParent = affiliateId;
                                        cvNetworkState.currentLevel++;
                                        cvLoadNetworkLevel(affiliateId, cvNetworkState.currentLevel);
                                        cvUpdateBreadcrumb();
                                    }
                                });
                            } else {
                                container.html('<div style="text-align: center; padding: 40px; color: #999;"><i class="fas fa-users" style="font-size: 48px; margin-bottom: 15px;"></i><p>No hay miembros en este nivel</p></div>');
                            }
                        },
                        error: function() {
                            container.html('<div style="text-align: center; padding: 40px; color: #dc3545;">Error al cargar los miembros</div>');
                        }
                    });
                }

                // Volver al nivel anterior
                function cvNetworkGoBack() {
                    if (cvNetworkState.history.length > 0) {
                        const previous = cvNetworkState.history.pop();
                        cvNetworkState.currentParent = previous.parent;
                        cvNetworkState.currentLevel = previous.level;
                        cvLoadNetworkLevel(previous.parent, previous.level);
                        cvUpdateBreadcrumb();
                    }
                }

                // Actualizar breadcrumb
                function cvUpdateBreadcrumb() {
                    const breadcrumb = jQuery('#cv-network-breadcrumb');
                    const text = jQuery('#cv-breadcrumb-text');
                    
                    if (cvNetworkState.currentLevel > 0) {
                        breadcrumb.show();
                        text.text('Nivel ' + (cvNetworkState.currentLevel + 1) + ' de ' + cvNetworkState.maxLevel);
                    } else {
                        breadcrumb.hide();
                    }
                }

                // Inicializar
                jQuery(document).ready(function() {
                    cvLoadNetworkLevel(<?php echo $a; ?>, 0);
                });
                </script>
            </div>
        </div>

        <style>
        .cv-my-network-page {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .cv-my-network-page .avatar-sponsor {
            border-radius: 50%;
            border: 3px solid rgba(255,255,255,0.5);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        
        /* Tarjetas de miembros */
        .cv-member-card,
        .cv-member-card-clickable {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .cv-member-card-clickable {
            cursor: pointer;
        }
        
        .cv-member-card-clickable:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
            border: 2px solid #667eea;
        }
        
        .cv-member-avatar-container {
            position: relative;
            width: 80px;
            height: 80px;
            margin: 0 auto 15px;
        }
        
        .cv-member-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid #667eea;
            object-fit: cover;
        }
        
        .cv-member-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #28a745;
            color: white;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .cv-member-info h4 {
            margin: 0 0 8px 0;
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        
        .cv-member-email {
            margin: 0 0 10px 0;
            font-size: 13px;
            color: #666;
            word-break: break-all;
        }
        
        .cv-member-level {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .cv-member-card,
            .cv-member-card-clickable {
                padding: 15px;
            }
            
            .cv-member-avatar-container {
                width: 60px;
                height: 60px;
            }
            
            .cv-member-avatar {
                width: 60px;
                height: 60px;
            }
        }
        </style>
        <?php
    }

    /**
     * AJAX: Obtener miembros de un nivel específico
     */
    public function ajax_get_network_members() {
        global $wpdb, $indeed_db;
        
        if (!is_user_logged_in()) {
            wp_send_json_error('No autorizado');
        }
        
        $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
        $level = isset($_POST['level']) ? intval($_POST['level']) : 0;
        
        // Obtener miembros directos de este padre
        $query = $wpdb->prepare(
            "SELECT affiliate_id, parent_affiliate_id 
             FROM wp_cvapp_mlm_relations 
             WHERE parent_affiliate_id = %d",
            $parent_id
        );
        
        $results = $wpdb->get_results($query);
        $members = array();
        
        foreach ($results as $result) {
            $username = $indeed_db->get_wp_username_by_affiliate_id($result->affiliate_id);
            $user = get_user_by('login', $username);
            
            if (!$user) continue;
            
            // Obtener nombre completo
            $first_name = get_user_meta($user->ID, 'first_name', true);
            $last_name = get_user_meta($user->ID, 'last_name', true);
            $full_name = trim($first_name . ' ' . $last_name);
            
            if (empty($full_name)) {
                $full_name = $user->display_name;
            }
            
            // Obtener avatar (mismo método que la tarjeta)
            $blog_id = get_current_blog_id();
            $avatar_meta_key = $wpdb->get_blog_prefix($blog_id) . 'user_avatar';
            $avatar_attachment_id = get_user_meta($user->ID, $avatar_meta_key, true);
            
            if ($avatar_attachment_id && wp_attachment_is_image($avatar_attachment_id)) {
                $avatar_data = wp_get_attachment_image_src($avatar_attachment_id, 'thumbnail');
                $avatar_url = $avatar_data ? $avatar_data[0] : get_avatar_url($user->ID, array('size' => 80));
            } else {
                $avatar_url = get_avatar_url($user->ID, array('size' => 80));
            }
            
            // Contar hijos
            $children_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM wp_cvapp_mlm_relations WHERE parent_affiliate_id = %d",
                $result->affiliate_id
            ));
            
            $members[] = array(
                'user_id' => $user->ID,
                'affiliate_id' => $result->affiliate_id,
                'name' => $full_name,
                'email' => $user->user_email,
                'avatar' => $avatar_url,
                'level' => $level + 1,
                'children_count' => intval($children_count)
            );
        }
        
        wp_send_json_success(array('members' => $members));
    }

    /**
     * AJAX: Obtener avatar de usuario
     */
    public function ajax_get_user_avatar() {
        global $wpdb;
        
        if (!is_user_logged_in()) {
            wp_send_json_error('No autorizado');
        }
        
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        
        if (!$user_id) {
            wp_send_json_error('ID de usuario inválido');
        }
        
        // Obtener avatar (mismo método que la tarjeta)
        $blog_id = get_current_blog_id();
        $avatar_meta_key = $wpdb->get_blog_prefix($blog_id) . 'user_avatar';
        $avatar_attachment_id = get_user_meta($user_id, $avatar_meta_key, true);
        
        if ($avatar_attachment_id && wp_attachment_is_image($avatar_attachment_id)) {
            $avatar_data = wp_get_attachment_image_src($avatar_attachment_id, 'thumbnail');
            $avatar_url = $avatar_data ? $avatar_data[0] : get_avatar_url($user_id, array('size' => 80));
        } else {
            $avatar_url = get_avatar_url($user_id, array('size' => 80));
        }
        
        wp_send_json_success(array('avatar_url' => $avatar_url));
    }
}

// Inicializar
new CV_My_Network_Endpoint();

