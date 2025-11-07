<?php
/**
 * Widget de estadísticas para el Dashboard de WordPress
 *
 * @package CV_Stats
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CV_Stats_Dashboard_Widget {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
    }
    
    /**
     * Añadir widget al dashboard
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'cv_stats_logins_today',
            '📊 Usuarios Conectados Hoy',
            array($this, 'render_widget')
        );
    }
    
    /**
     * Renderizar contenido del widget
     */
    public function render_widget() {
        $logins = CV_Stats_Login_Tracker::get_todays_logins();
        $count = count($logins);
        
        ?>
        <div class="cv-stats-widget">
            <div class="cv-stats-summary">
                <div class="cv-stats-count">
                    <span class="cv-stats-number"><?php echo $count; ?></span>
                    <span class="cv-stats-label">Usuarios conectados hoy</span>
                </div>
            </div>
            
            <?php if ($count > 0): ?>
                <div class="cv-stats-list">
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Nombre</th>
                                <th>Rol</th>
                                <th>Último Login</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logins as $login): ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo admin_url('user-edit.php?user_id=' . $login['user_id']); ?>">
                                            <?php echo esc_html($login['username']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo esc_html($login['display_name']); ?></td>
                                    <td>
                                        <?php 
                                        $roles = $login['roles'];
                                        $role_names = array();
                                        
                                        // Traducir roles
                                        $role_translations = array(
                                            'administrator' => 'Administrador',
                                            'wcfm_vendor' => 'Vendedor',
                                            'customer' => 'Cliente',
                                            'shop_manager' => 'Gestor Tienda',
                                            'editor' => 'Editor',
                                            'author' => 'Autor'
                                        );
                                        
                                        foreach ($roles as $role) {
                                            $role_names[] = isset($role_translations[$role]) ? $role_translations[$role] : ucfirst($role);
                                        }
                                        
                                        echo esc_html(implode(', ', $role_names));
                                        ?>
                                    </td>
                                    <td><?php echo date('H:i', strtotime($login['last_login'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="cv-stats-footer">
                    <a href="<?php echo admin_url('admin.php?page=cv-stats'); ?>" class="button button-primary">
                        Ver Estadísticas Completas →
                    </a>
                </div>
            <?php else: ?>
                <div class="cv-stats-empty">
                    <p>No hay usuarios conectados hoy.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

