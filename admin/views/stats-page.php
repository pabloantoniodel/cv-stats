<?php
/**
 * Vista de página de estadísticas
 */

if (!defined('ABSPATH')) {
    exit;
}

// Obtener fechas del filtro (por defecto hoy)
$date_from = isset($_GET['date_from']) && !empty($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-d');
$date_to = isset($_GET['date_to']) && !empty($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d');

// Validar fechas
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
    $date_from = date('Y-m-d');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
    $date_to = date('Y-m-d');
}

// Asegurar que date_to sea mayor o igual a date_from
if (strtotime($date_to) < strtotime($date_from)) {
    $date_to = $date_from;
}

$period_start = $date_from . ' 00:00:00';
$period_end = $date_to . ' 23:59:59';

// Determinar si es un solo día o un rango
$is_single_day = ($date_from === $date_to);
$period_label = $is_single_day ? date('d/m/Y', strtotime($date_from)) : date('d/m/Y', strtotime($date_from)) . ' - ' . date('d/m/Y', strtotime($date_to));

// Obtener estadísticas con el rango de fechas
$logins_today = CV_Stats_Login_Tracker::get_logins_by_date_range($period_start, $period_end);
$count_today = count($logins_today);
$active_sessions = CV_Stats_Login_Tracker::get_sessions_since($period_start);
$count_active_sessions = count($active_sessions);

// Agrupar logins por IP
$logins_by_ip = array();
$unique_ips = array();
foreach ($logins_today as $login) {
    $ip = $login['ip_address'] ?? 'N/A';
    if (!isset($logins_by_ip[$ip])) {
        $logins_by_ip[$ip] = array(
            'count' => 0,
            'users' => array()
        );
        $unique_ips[] = $ip;
    }
    $logins_by_ip[$ip]['count']++;
    
    // Añadir usuario a la lista (evitar duplicados)
    $user_key = $login['user_id'];
    if (!isset($logins_by_ip[$ip]['users'][$user_key])) {
        $logins_by_ip[$ip]['users'][$user_key] = array(
            'username' => $login['username'],
            'display_name' => $login['display_name']
        );
    }
}
$count_unique_ips = count($unique_ips);

// Ordenar por cantidad de logins descendente
uasort($logins_by_ip, function($a, $b) {
    return $b['count'] - $a['count'];
});

// Paginación para Tarjetas Vistas
$views_per_page = 25;
$views_paged = isset($_GET['views_paged']) ? max(1, intval($_GET['views_paged'])) : 1;

// Obtener estadísticas de tarjetas
$card_views_today = CV_Stats_Card_Tracker::get_card_views_by_date_range($period_start, $period_end);
$card_views_detailed_full = CV_Stats_Card_Tracker::get_card_views_detailed_by_date_range($period_start, $period_end);
$count_card_views = count($card_views_today);
$total_views = count($card_views_detailed_full);

// Agrupar vistas por tarjeta (owner_id)
$card_views_grouped = array();
foreach ($card_views_detailed_full as $view) {
    $owner_id = $view['owner_id'];
    if (!isset($card_views_grouped[$owner_id])) {
        $card_views_grouped[$owner_id] = array(
            'owner_id' => $view['owner_id'],
            'owner_username' => $view['owner_username'],
            'owner_display_name' => $view['owner_display_name'],
            'total_views' => 0,
            'details' => array()
        );
    }
    $card_views_grouped[$owner_id]['total_views']++;
    $card_views_grouped[$owner_id]['details'][] = $view;
}

// Ordenar por total de vistas descendente
usort($card_views_grouped, function($a, $b) {
    return $b['total_views'] - $a['total_views'];
});

// Paginar los resultados agrupados
$views_offset = ($views_paged - 1) * $views_per_page;
$card_views_grouped_paged = array_slice($card_views_grouped, $views_offset, $views_per_page);
$views_total_pages = ceil(count($card_views_grouped) / $views_per_page);

$whatsapp_sends_today = CV_Stats_Card_Tracker::get_whatsapp_sends_by_date_range($period_start, $period_end);
$count_whatsapp_sends = count($whatsapp_sends_today);

// Obtener tarjetas creadas (basado en fecha de registro del usuario)
// Solo usuarios con roles válidos que tengan tarjetas reales del plugin
global $wpdb;

$cards_created_today = $wpdb->get_results($wpdb->prepare("
    SELECT p.ID, p.post_author, p.post_title, u.user_registered, u.user_login, u.display_name, u.user_email
    FROM {$wpdb->users} u
    INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
    LEFT JOIN {$wpdb->posts} p ON u.ID = p.post_author AND p.post_type = 'card' AND p.post_status IN ('publish', 'draft', 'pending')
    WHERE u.user_registered >= %s
    AND u.user_registered <= %s
    AND um.meta_key = '{$wpdb->prefix}capabilities'
    AND (
        um.meta_value LIKE '%%wcfm_vendor%%' 
        OR um.meta_value LIKE '%%dc_vendor%%'
        OR um.meta_value LIKE '%%customer%%'
    )
    AND p.ID IS NOT NULL
    GROUP BY u.ID
    ORDER BY u.user_registered DESC
", $period_start, $period_end), ARRAY_A);

$count_cards_created = count($cards_created_today);

// Obtener estadísticas de productos (usando post_date y post_modified directamente)
$product_stats = CV_Stats_Product_Simple::get_stats($date_from, $date_to);
$products_created = CV_Stats_Product_Simple::get_created_products($date_from, $date_to);
$products_updated = CV_Stats_Product_Simple::get_updated_products($date_from, $date_to);
$products_affiliated = CV_Stats_Product_Simple::get_affiliated_products($date_from, $date_to);

// Debug
error_log('📊 CV Stats: Productos creados: ' . count($products_created) . ', Actualizados: ' . count($products_updated) . ', Afiliados: ' . count($products_affiliated));

// Obtener productos afiliados
$products_affiliated_count = $wpdb->get_var($wpdb->prepare("
    SELECT COUNT(*)
    FROM {$wpdb->prefix}wcfm_product_affiliates
    WHERE created_at >= %s AND created_at <= %s
", $period_start, $period_end));

// Obtener estadísticas de consultas de contacto
$days_diff = ceil((strtotime($period_end) - strtotime($period_start)) / (60 * 60 * 24));
$contact_stats = CV_Stats_Contact_Tracker::get_stats($days_diff);
$contact_queries = $wpdb->get_results($wpdb->prepare("
    SELECT * FROM {$wpdb->prefix}cv_stats_contact_queries
    WHERE created_at >= %s AND created_at <= %s
    ORDER BY created_at DESC
", $period_start, $period_end), ARRAY_A);
?>

<div class="wrap cv-stats-page">
    <h1>📊 Estadísticas de Ciudad Virtual</h1>
    
    <!-- Filtro de Fechas -->
    <div class="cv-stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; margin-bottom: 30px;">
        <form method="get" action="" style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
            <input type="hidden" name="page" value="cv-stats">
            
            <div style="display: flex; align-items: center; gap: 10px;">
                <label for="date_from" style="font-weight: 600; font-size: 16px;">📅 Desde:</label>
                <input type="date" id="date_from" name="date_from" value="<?php echo esc_attr($date_from); ?>" 
                       style="padding: 8px 12px; border-radius: 6px; border: 2px solid rgba(255,255,255,0.3); background: rgba(255,255,255,0.9); font-size: 14px;">
            </div>
            
            <div style="display: flex; align-items: center; gap: 10px;">
                <label for="date_to" style="font-weight: 600; font-size: 16px;">📅 Hasta:</label>
                <input type="date" id="date_to" name="date_to" value="<?php echo esc_attr($date_to); ?>" 
                       style="padding: 8px 12px; border-radius: 6px; border: 2px solid rgba(255,255,255,0.3); background: rgba(255,255,255,0.9); font-size: 14px;">
            </div>
            
            <button type="submit" class="button button-primary" style="padding: 8px 20px; font-size: 14px; font-weight: 600; height: auto; background: rgba(255,255,255,0.25); border: 2px solid rgba(255,255,255,0.5); color: white; text-shadow: 0 1px 2px rgba(0,0,0,0.2);">
                🔍 Filtrar
            </button>
            
            <a href="?page=cv-stats" class="button" style="padding: 8px 20px; font-size: 14px; font-weight: 600; height: auto; background: rgba(0,0,0,0.2); border: 2px solid rgba(255,255,255,0.3); color: white; text-decoration: none; text-shadow: 0 1px 2px rgba(0,0,0,0.2);">
                🔄 Hoy
            </a>
            
            <div style="margin-left: auto; font-size: 18px; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                Período: <?php echo $period_label; ?>
            </div>
        </form>
    </div>
    
    <!-- Tarjetas Creadas (PRIMERA SECCIÓN) -->
    <div class="cv-stats-card">
        <h2>🎴 Tarjetas Creadas</h2>
        <div class="cv-stats-summary-grid">
            <div class="cv-stats-summary-item" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="cv-stats-big-number"><?php echo $count_cards_created; ?></div>
                <div class="cv-stats-big-label">Nuevas Tarjetas</div>
            </div>
        </div>
        
        <?php if ($count_cards_created > 0): ?>
            <div class="cv-stats-table-container">
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Título</th>
                            <th>Usuario</th>
                            <th>Sponsor (Referido por)</th>
                            <th>Fecha/Hora</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cards_created_today as $card): 
                            // Obtener el sponsor desde user_registration_referido
                            $sponsor_id_or_login = get_user_meta($card['post_author'], 'user_registration_referido', true);
                            $sponsor_name = '—';
                            $sponsor_link = '';
                            
                            if (!empty($sponsor_id_or_login)) {
                                // Intentar obtener usuario sponsor
                                $sponsor_user = false;
                                if (is_numeric($sponsor_id_or_login)) {
                                    $sponsor_user = get_user_by('id', intval($sponsor_id_or_login));
                                } elseif (strpos($sponsor_id_or_login, '@') !== false) {
                                    $sponsor_user = get_user_by('email', $sponsor_id_or_login);
                                } else {
                                    $sponsor_user = get_user_by('login', $sponsor_id_or_login);
                                }
                                
                                if ($sponsor_user) {
                                    $sponsor_store_name = get_user_meta($sponsor_user->ID, 'store_name', true);
                                    if (empty($sponsor_store_name)) {
                                        $sponsor_store_name = $sponsor_user->display_name;
                                    }
                                    $sponsor_name = $sponsor_store_name;
                                    $sponsor_link = admin_url('user-edit.php?user_id=' . $sponsor_user->ID);
                                }
                            }
                            
                            // URL de la tarjeta
                            $card_url = get_permalink($card['ID']);
                        ?>
                            <tr>
                                <td><strong><?php echo $card['ID']; ?></strong></td>
                                <td><strong><?php echo esc_html($card['post_title']); ?></strong></td>
                                <td>
                                    <a href="<?php echo admin_url('user-edit.php?user_id=' . $card['post_author']); ?>">
                                        <?php echo esc_html($card['display_name']); ?>
                                    </a>
                                    <br>
                                    <small style="color: #666;">@<?php echo esc_html($card['user_login']); ?></small>
                                </td>
                                <td>
                                    <?php if ($sponsor_link): ?>
                                        <a href="<?php echo $sponsor_link; ?>" style="color: #667eea; font-weight: 600;">
                                            🎯 <?php echo esc_html($sponsor_name); ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #999;">Sin sponsor</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="cv-stats-time"><?php echo date('d/m/Y H:i:s', strtotime($card['user_registered'])); ?></span>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url($card_url); ?>" target="_blank" class="button button-small">Ver Tarjeta →</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="cv-stats-empty-state">
                <p>📭 No se crearon tarjetas hoy.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Resumen de Logins de Hoy -->
    <div class="cv-stats-card">
        <h2>👥 Usuarios Conectados Hoy</h2>
        
        <div class="cv-stats-summary-grid">
            <div class="cv-stats-summary-item">
                <div class="cv-stats-big-number"><?php echo $count_today; ?></div>
                <div class="cv-stats-big-label">Total Logins</div>
            </div>
            <div class="cv-stats-summary-item">
                <div class="cv-stats-big-number"><?php echo $count_unique_ips; ?></div>
                <div class="cv-stats-big-label">IPs Diferentes</div>
            </div>
            <div class="cv-stats-summary-item">
                <div class="cv-stats-big-number">
                    <?php 
                    if ($count_unique_ips > 0) {
                        echo number_format(round($count_today / $count_unique_ips, 1), 1);
                    } else {
                        echo '0';
                    }
                    ?>
                </div>
                <div class="cv-stats-big-label">Promedio Logins/IP</div>
            </div>
            <div class="cv-stats-summary-item" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white;">
                <div class="cv-stats-big-number"><?php echo $count_active_sessions; ?></div>
                <div class="cv-stats-big-label">Usuarios activos hoy</div>
            </div>
        </div>
        
        <?php if (count($logins_by_ip) > 0): ?>
            <div class="cv-stats-card" style="margin-top: 20px;">
                <h3>🌐 Logins Agrupados por IP</h3>
                <div class="cv-stats-table-container">
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th style="width: 20%;">IP Address</th>
                                <th style="width: 40%;">Usuarios/Comercios</th>
                                <th style="width: 15%;">Cantidad de Logins</th>
                                <th style="width: 25%;">Porcentaje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logins_by_ip as $ip => $data): ?>
                                <tr>
                                    <td>
                                        <code style="font-size: 11px;"><?php echo esc_html($ip); ?></code>
                                    </td>
                                    <td>
                                        <?php 
                                        $user_names = array();
                                        foreach ($data['users'] as $user) {
                                            $user_names[] = '<strong>' . esc_html($user['display_name']) . '</strong> (' . esc_html($user['username']) . ')';
                                        }
                                        echo implode('<br>', $user_names);
                                        ?>
                                    </td>
                                    <td>
                                        <strong><?php echo $data['count']; ?></strong>
                                    </td>
                                    <td>
                                        <div class="cv-stats-progress-bar">
                                            <div class="cv-stats-progress-fill" style="width: <?php echo ($count_today > 0) ? round(($data['count'] / $count_today) * 100, 1) : 0; ?>%"></div>
                                        </div>
                                        <span class="cv-stats-percentage">
                                            <?php echo ($count_today > 0) ? round(($data['count'] / $count_today) * 100, 1) : 0; ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($count_today > 0): ?>
            <div style="margin-top: 30px;">
                <h3>📋 Detalle de Usuarios Conectados</h3>
                <div class="cv-stats-table-container">
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Usuario</th>
                                <th>Nombre Completo</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>IP de Login</th>
                                <th>Último Login</th>
                            </tr>
                        </thead>
                    <tbody>
                        <?php foreach ($logins_today as $login): ?>
                            <tr>
                                <td><?php echo $login['user_id']; ?></td>
                                <td>
                                    <strong>
                                        <a href="<?php echo admin_url('user-edit.php?user_id=' . $login['user_id']); ?>">
                                            <?php echo esc_html($login['username']); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?php echo esc_html($login['display_name']); ?></td>
                                <td>
                                    <a href="mailto:<?php echo esc_attr($login['email']); ?>">
                                        <?php echo esc_html($login['email']); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php 
                                    $roles = $login['roles'];
                                    $role_names = array();
                                    
                                    // Traducir roles
                                    $role_translations = array(
                                        'administrator' => '👑 Administrador',
                                        'wcfm_vendor' => '🏪 Vendedor',
                                        'customer' => '🛍️ Cliente',
                                        'shop_manager' => '⚙️ Gestor',
                                        'editor' => '✏️ Editor',
                                        'author' => '📝 Autor'
                                    );
                                    
                                    foreach ($roles as $role) {
                                        $role_names[] = isset($role_translations[$role]) ? $role_translations[$role] : ucfirst($role);
                                    }
                                    
                                    echo implode(', ', $role_names);
                                    ?>
                                </td>
                                <td>
                                    <code style="font-size: 11px;">
                                        <?php echo esc_html($login['ip_address'] ?? 'N/A'); ?>
                                    </code>
                                </td>
                                <td>
                                    <span class="cv-stats-time">
                                        <?php echo date('d/m/Y H:i:s', strtotime($login['last_login'])); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            </div>
        <?php else: ?>
            <div class="cv-stats-empty-state">
                <p>📭 No hay usuarios conectados hoy.</p>
            </div>
        <?php endif; ?>

        <?php if ($count_active_sessions > 0): ?>
            <div class="cv-stats-card" style="margin-top: 30px;">
                <h3>🟢 Usuarios activos durante el día</h3>
                <p style="margin: -10px 0 15px 0; color: #6b7280;">
                    Lista basada en la última actividad registrada dentro del período seleccionado (panel y frontend).
                </p>
                <div class="cv-stats-table-container">
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Rol</th>
                                <th>Última actividad</th>
                                <th>IP</th>
                                <th>Página actual</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_sessions as $session): ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <a href="<?php echo admin_url('user-edit.php?user_id=' . $session['user_id']); ?>">
                                                <?php echo esc_html($session['display_name']); ?>
                                            </a>
                                        </strong>
                                        <br>
                                        <small>@<?php echo esc_html($session['username']); ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $role_names = array(
                                            'administrator' => '👑 Administrador',
                                            'wcfm_vendor'   => '🏪 Vendedor',
                                            'customer'      => '🛍️ Cliente',
                                            'shop_manager'  => '⚙️ Gestor',
                                        );
                                        $roles_to_show = array();
                                        foreach ($session['roles'] as $role) {
                                            $roles_to_show[] = isset($role_names[$role]) ? $role_names[$role] : $role;
                                        }
                                        echo implode(', ', $roles_to_show);
                                        ?>
                                    </td>
                                    <td>
                                        <span class="cv-stats-time">
                                            <?php echo esc_html(date('d/m/Y H:i:s', strtotime($session['last_seen']))); ?>
                                        </span>
                                        <br>
                                        <small><?php echo human_time_diff(strtotime($session['last_seen']), current_time('timestamp')); ?> atrás</small>
                                    </td>
                                    <td>
                                        <code style="font-size: 11px;"><?php echo esc_html($session['ip_address']); ?></code>
                                    </td>
                                    <td>
                                        <?php if (!empty($session['current_url'])): ?>
                                            <?php
                                                $current_url_text = $session['current_url'];
                                                if (function_exists('mb_strimwidth')) {
                                                    $current_url_text = mb_strimwidth($current_url_text, 0, 60, '…', 'UTF-8');
                                                } elseif (strlen($current_url_text) > 60) {
                                                    $current_url_text = substr($current_url_text, 0, 57) . '...';
                                                }
                                            ?>
                                            <a href="<?php echo esc_url($session['current_url']); ?>" target="_blank">
                                                <?php echo esc_html($current_url_text); ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #94a3b8;">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="cv-stats-empty-state" style="margin-top: 30px;">
                <p>🟡 No se registraron usuarios activos en este período.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Estadísticas de Tarjetas Vistas Hoy -->
    <div class="cv-stats-card">
        <h2>👁️ Tarjetas Vistas Hoy</h2>
        
        <div class="cv-stats-summary-grid">
            <div class="cv-stats-summary-item">
                <div class="cv-stats-big-number"><?php echo $count_card_views; ?></div>
                <div class="cv-stats-big-label">Tarjetas Diferentes</div>
            </div>
            <div class="cv-stats-summary-item" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <div class="cv-stats-big-number"><?php echo $total_views; ?></div>
                <div class="cv-stats-big-label">Vistas Totales</div>
            </div>
        </div>
        
        <?php if ($total_views > 0): ?>
            <div class="cv-stats-table-container">
                <table class="widefat striped cv-views-grouped-table">
                    <thead>
                        <tr>
                            <th>Tarjeta de</th>
                            <th>Nombre</th>
                            <th>Total Vistas</th>
                            <th>Última Vista</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($card_views_grouped_paged as $card): ?>
                            <?php 
                            // Obtener la última vista
                            $last_view = end($card['details']);
                            $card_id = 'card-' . $card['owner_id'];
                            ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?php echo admin_url('user-edit.php?user_id=' . $card['owner_id']); ?>">
                                            <?php echo esc_html($card['owner_username']); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?php echo esc_html($card['owner_display_name']); ?></td>
                                <td>
                                    <span style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 4px 12px; border-radius: 12px; font-weight: 600;">
                                        <?php echo $card['total_views']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="cv-stats-time">
                                        <?php echo date('H:i:s', strtotime($last_view['view_time'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="https://ciudadvirtual.app/card/<?php echo urlencode($card['owner_username']); ?>" 
                                       target="_blank" 
                                       class="button button-small">
                                        Ver Tarjeta →
                                    </a>
                                    <button type="button" class="button button-small cv-toggle-details" data-target="<?php echo $card_id; ?>" style="margin-left: 5px;">
                                        📋 Ver Detalles
                                    </button>
                                </td>
                            </tr>
                            <!-- Fila de detalles (oculta por defecto) -->
                            <tr class="cv-details-row" id="<?php echo $card_id; ?>" style="display: none;">
                                <td colspan="5" style="padding: 0; background: #f9f9f9;">
                                    <div style="padding: 15px 20px;">
                                        <h4 style="margin: 0 0 10px 0; color: #667eea;">📊 Detalles de Vistas</h4>
                                        <table class="widefat" style="margin: 0;">
                                            <thead>
                                                <tr style="background: #f0f0f0;">
                                                    <th>Visto por</th>
                                                    <th>Nombre Visitante</th>
                                                    <th>Hora</th>
                                                    <th>IP</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($card['details'] as $detail): ?>
                                                    <tr>
                                                        <td>
                                                            <?php if ($detail['visitor_id'] > 0): ?>
                                                                <a href="<?php echo admin_url('user-edit.php?user_id=' . $detail['visitor_id']); ?>">
                                                                    👤 <?php echo esc_html($detail['visitor_username']); ?>
                                                                </a>
                                                            <?php else: ?>
                                                                <span style="color: #999;">👻 Anónimo</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($detail['visitor_id'] > 0): ?>
                                                                <?php echo esc_html($detail['visitor_display_name']); ?>
                                                            <?php else: ?>
                                                                <span style="color: #999;">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="cv-stats-time">
                                                                <?php echo date('H:i:s', strtotime($detail['view_time'])); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <code style="font-size: 11px;"><?php echo esc_html($detail['ip_address']); ?></code>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($views_total_pages > 1): ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <span class="displaying-num"><?php echo count($card_views_grouped); ?> tarjetas (<?php echo $total_views; ?> vistas totales)</span>
                            <span class="pagination-links">
                                <?php
                                $base_url = remove_query_arg('views_paged');
                                
                                // Primera página
                                if ($views_paged > 1) {
                                    echo '<a class="first-page button" href="' . add_query_arg('views_paged', 1, $base_url) . '">«</a> ';
                                    echo '<a class="prev-page button" href="' . add_query_arg('views_paged', $views_paged - 1, $base_url) . '">‹</a> ';
                                } else {
                                    echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span> ';
                                    echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span> ';
                                }
                                
                                echo '<span class="paging-input">';
                                echo '<span class="tablenav-paging-text">' . $views_paged . ' de <span class="total-pages">' . $views_total_pages . '</span></span>';
                                echo '</span> ';
                                
                                // Última página
                                if ($views_paged < $views_total_pages) {
                                    echo '<a class="next-page button" href="' . add_query_arg('views_paged', $views_paged + 1, $base_url) . '">›</a> ';
                                    echo '<a class="last-page button" href="' . add_query_arg('views_paged', $views_total_pages, $base_url) . '">»</a>';
                                } else {
                                    echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span> ';
                                    echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="cv-stats-empty-state">
                <p>📭 No hay vistas de tarjetas hoy.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Estadísticas de Envíos por WhatsApp -->
    <div class="cv-stats-card">
        <h2>📤 Tarjetas Enviadas por WhatsApp Hoy</h2>
        
        <div class="cv-stats-summary-grid">
            <div class="cv-stats-summary-item" style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);">
                <div class="cv-stats-big-number"><?php echo $count_whatsapp_sends; ?></div>
                <div class="cv-stats-big-label">Envíos por WhatsApp</div>
            </div>
        </div>
        
        <?php if ($count_whatsapp_sends > 0): ?>
            <div class="cv-stats-table-container">
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tarjeta de</th>
                            <th>Nombre</th>
                            <th>Enviado a Teléfono</th>
                            <th>Fecha y Hora</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($whatsapp_sends_today as $send): ?>
                            <tr>
                                <td><?php echo $send['user_id']; ?></td>
                                <td>
                                    <strong>
                                        <a href="<?php echo admin_url('user-edit.php?user_id=' . $send['user_id']); ?>">
                                            <?php echo esc_html($send['username']); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?php echo esc_html($send['display_name']); ?></td>
                                <td>
                                    <span class="cv-stats-phone">
                                        📱 <?php echo esc_html($send['phone_number']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="cv-stats-time">
                                        <?php echo date('d/m/Y H:i:s', strtotime($send['send_time'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="https://ciudadvirtual.app/card/<?php echo urlencode($send['username']); ?>" 
                                       target="_blank" 
                                       class="button button-small">
                                        Ver Tarjeta →
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="cv-stats-empty-state">
                <p>📭 No hay envíos por WhatsApp hoy.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Estadísticas de Productos Hoy -->
    <div class="cv-stats-card">
        <h2>📦 Actividad de Productos Hoy</h2>
        
        <div class="cv-stats-summary-grid">
            <div class="cv-stats-summary-item" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <div class="cv-stats-big-number"><?php echo $product_stats['created']; ?></div>
                <div class="cv-stats-big-label">Productos Creados</div>
            </div>
            <div class="cv-stats-summary-item" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                <div class="cv-stats-big-number"><?php echo $product_stats['updated']; ?></div>
                <div class="cv-stats-big-label">Productos Actualizados</div>
            </div>
            <div class="cv-stats-summary-item" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                <div class="cv-stats-big-number"><?php echo $product_stats['affiliated']; ?></div>
                <div class="cv-stats-big-label">Productos Afiliados</div>
            </div>
        </div>
        
        <!-- Productos Creados Hoy -->
        <?php if (count($products_created) > 0): ?>
            <h3 style="margin-top: 30px; color: #10b981;">✨ Productos Creados Hoy (<?php echo count($products_created); ?>)</h3>
            <div class="cv-stats-table-container">
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Producto</th>
                            <th>Vendedor</th>
                            <th>Creado por</th>
                            <th>Hora</th>
                            <th>IP</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products_created as $product): ?>
                            <tr>
                                <td><strong><?php echo $product['product_id']; ?></strong></td>
                                <td>
                                    <strong><?php echo esc_html($product['product_name']); ?></strong>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('user-edit.php?user_id=' . $product['vendor_id']); ?>">
                                        <?php echo esc_html($product['vendor_name']); ?>
                                    </a>
                                    <br>
                                    <small style="color: #666;">@<?php echo esc_html($product['vendor_username']); ?></small>
                                </td>
                                <td>
                                    <?php if ($product['created_by_id'] == $product['vendor_id']): ?>
                                        <span style="color: #10b981;">✓ El mismo vendedor</span>
                                    <?php else: ?>
                                        <a href="<?php echo admin_url('user-edit.php?user_id=' . $product['created_by_id']); ?>">
                                            <?php echo esc_html($product['created_by_name']); ?>
                                        </a>
                                        <br>
                                        <small style="color: #666;">@<?php echo esc_html($product['created_by_username']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="cv-stats-time">
                                        <?php echo date('H:i:s', strtotime($product['activity_time'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <code style="font-size: 11px;"><?php echo esc_html($product['ip_address']); ?></code>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url($product['product_url']); ?>" 
                                       target="_blank" 
                                       class="button button-small">
                                        Ver →
                                    </a>
                                    <a href="<?php echo admin_url('post.php?post=' . $product['product_id'] . '&action=edit'); ?>" 
                                       class="button button-small">
                                        Editar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="cv-stats-empty-state" style="margin-top: 20px;">
                <p>📭 No se crearon productos hoy.</p>
            </div>
        <?php endif; ?>
        
        <!-- Productos Actualizados Hoy -->
        <?php if (count($products_updated) > 0): ?>
            <h3 style="margin-top: 30px; color: #3b82f6;">🔄 Productos Actualizados Hoy (<?php echo count($products_updated); ?>)</h3>
            <div class="cv-stats-table-container">
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Producto</th>
                            <th>Vendedor</th>
                            <th>Modificado por</th>
                            <th>Hora</th>
                            <th>IP</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Debug HTML
                        echo '<!-- DEBUG: Total productos updated: ' . count($products_updated) . ' -->';
                        foreach ($products_updated as $product): 
                            echo '<!-- DEBUG Producto: ' . print_r($product, true) . ' -->';
                        ?>
                            <tr>
                                <td><strong><?php echo $product['product_id']; ?></strong></td>
                                <td>
                                    <strong><?php echo esc_html($product['product_name']); ?></strong>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('user-edit.php?user_id=' . $product['vendor_id']); ?>">
                                        <?php echo esc_html($product['vendor_name']); ?>
                                    </a>
                                    <br>
                                    <small style="color: #666;">@<?php echo esc_html($product['vendor_username']); ?></small>
                                </td>
                                <td>
                                    <?php if ($product['modified_by_id'] == $product['vendor_id']): ?>
                                        <span style="color: #10b981;">✓ El mismo vendedor</span>
                                    <?php else: ?>
                                        <a href="<?php echo admin_url('user-edit.php?user_id=' . $product['modified_by_id']); ?>">
                                            <?php echo esc_html($product['modified_by_name']); ?>
                                        </a>
                                        <br>
                                        <small style="color: #666;">@<?php echo esc_html($product['modified_by_username']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="cv-stats-time">
                                        <?php echo date('H:i:s', strtotime($product['activity_time'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <code style="font-size: 11px;"><?php echo esc_html($product['ip_address']); ?></code>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url($product['product_url']); ?>" 
                                       target="_blank" 
                                       class="button button-small">
                                        Ver →
                                    </a>
                                    <a href="<?php echo admin_url('post.php?post=' . $product['product_id'] . '&action=edit'); ?>" 
                                       class="button button-small">
                                        Editar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="cv-stats-empty-state" style="margin-top: 20px;">
                <p>📭 No se actualizaron productos hoy.</p>
            </div>
        <?php endif; ?>
        
        <!-- Productos Afiliados (Copias) -->
        <?php if (count($products_affiliated) > 0): ?>
            <h3 style="margin-top: 30px; color: #8b5cf6;">🔗 Productos Afiliados Hoy (<?php echo count($products_affiliated); ?>)</h3>
            <div class="cv-stats-table-container">
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Producto</th>
                            <th>Afiliado</th>
                            <th>Propietario Original</th>
                            <th>Comisión</th>
                            <th>Hora</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products_affiliated as $aff): ?>
                            <tr>
                                <td><strong><?php echo $aff['product_id']; ?></strong></td>
                                <td>
                                    <strong><?php echo esc_html($aff['product_name']); ?></strong>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('user-edit.php?user_id=' . $aff['vendor_id']); ?>">
                                        <?php echo esc_html($aff['vendor_name']); ?>
                                    </a>
                                    <br>
                                    <small style="color: #666;">@<?php echo esc_html($aff['vendor_username']); ?></small>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('user-edit.php?user_id=' . $aff['owner_id']); ?>">
                                        <?php echo esc_html($aff['owner_name']); ?>
                                    </a>
                                    <br>
                                    <small style="color: #666;">@<?php echo esc_html($aff['owner_username']); ?></small>
                                </td>
                                <td>
                                    <span class="cv-stats-commission">
                                        <?php echo $aff['commission_rate']; ?>%
                                    </span>
                                </td>
                                <td>
                                    <span class="cv-stats-time">
                                        <?php echo date('H:i:s', strtotime($aff['created_at'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url($aff['product_url']); ?>" 
                                       target="_blank" 
                                       class="button button-small">
                                        Ver →
                                    </a>
                                    <a href="<?php echo admin_url('post.php?post=' . $aff['product_id'] . '&action=edit'); ?>" 
                                       class="button button-small">
                                        Editar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="cv-stats-empty-state" style="margin-top: 20px;">
                <p>📭 No se afiliaron productos hoy.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Consultas de Contacto -->
    <div class="cv-stats-card">
        <h2>📧 Consultas de Contacto</h2>
        
        <div class="cv-stats-summary-grid">
            <div class="cv-stats-summary-item" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="cv-stats-big-number"><?php echo count($contact_queries); ?></div>
                <div class="cv-stats-big-label">Total Consultas</div>
            </div>
            
            <?php foreach ($contact_stats['by_category'] as $cat): ?>
                <div class="cv-stats-summary-item" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="cv-stats-big-number"><?php echo $cat['count']; ?></div>
                    <div class="cv-stats-big-label">
                        <?php echo $cat['category'] === 'plataforma' ? '💻 Plataforma' : '🏪 Comercio'; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (count($contact_queries) > 0): ?>
            <div class="cv-stats-table-container" style="margin-top: 30px;">
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Categoría</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Teléfono</th>
                            <th>Asunto</th>
                            <th>Fecha/Hora</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contact_queries as $query): ?>
                            <?php
                            $query_id = 'contact-' . $query['id'];
                            $category_badge = $query['category'] === 'plataforma' 
                                ? '<span style="background: #667eea; color: white; padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 600;">💻 Plataforma</span>'
                                : '<span style="background: #10b981; color: white; padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 600;">🏪 Comercio</span>';
                            ?>
                            <tr>
                                <td><strong><?php echo $query['id']; ?></strong></td>
                                <td><?php echo $category_badge; ?></td>
                                <td>
                                    <strong><?php echo esc_html($query['name']); ?></strong>
                                    <?php if ($query['user_id']): ?>
                                        <br>
                                        <small>
                                            <a href="<?php echo admin_url('user-edit.php?user_id=' . $query['user_id']); ?>">
                                                👤 Usuario ID: <?php echo $query['user_id']; ?>
                                            </a>
                                        </small>
                                    <?php else: ?>
                                        <br><small style="color: #999;">👻 No registrado</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="mailto:<?php echo esc_attr($query['email']); ?>">
                                        <?php echo esc_html($query['email']); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if (!empty($query['phone'])): ?>
                                        <span class="cv-stats-phone">📱 <?php echo esc_html($query['phone']); ?></span>
                                    <?php else: ?>
                                        <span style="color: #999;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-size: 13px;"><?php echo esc_html($query['subject']); ?></span>
                                </td>
                                <td>
                                    <span class="cv-stats-time">
                                        <?php echo date('d/m/Y H:i:s', strtotime($query['created_at'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="button button-small cv-toggle-contact-details" data-target="<?php echo $query_id; ?>">
                                        📋 Ver Mensaje
                                    </button>
                                </td>
                            </tr>
                            <!-- Fila de detalles del mensaje -->
                            <tr class="cv-contact-details-row" id="<?php echo $query_id; ?>" style="display: none;">
                                <td colspan="8" style="padding: 0; background: #f9f9f9;">
                                    <div style="padding: 20px; border-left: 4px solid <?php echo $query['category'] === 'plataforma' ? '#667eea' : '#10b981'; ?>;">
                                        <h4 style="margin: 0 0 15px 0; color: <?php echo $query['category'] === 'plataforma' ? '#667eea' : '#10b981'; ?>;">
                                            💬 Mensaje Completo
                                        </h4>
                                        <div style="background: white; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                                            <p style="margin: 0; white-space: pre-wrap; line-height: 1.6;">
                                                <?php echo esc_html($query['message']); ?>
                                            </p>
                                        </div>
                                        <div style="display: flex; gap: 20px; font-size: 13px; color: #666;">
                                            <div><strong>IP:</strong> <code><?php echo esc_html($query['ip_address']); ?></code></div>
                                            <?php if (!empty($query['user_agent'])): ?>
                                                <div><strong>Navegador:</strong> <?php echo esc_html(substr($query['user_agent'], 0, 50)); ?>...</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="cv-stats-empty-state">
                <p>📭 No hay consultas de contacto en este período.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Referencias desde Buscadores -->
    <?php include CV_STATS_PLUGIN_DIR . 'views/search-referrals-card.php'; ?>
    
</div>

<script>
jQuery(document).ready(function($) {
    // Toggle detalles de mensajes de contacto
    $('.cv-toggle-contact-details').on('click', function() {
        var targetId = $(this).data('target');
        var $detailsRow = $('#' + targetId);
        
        if ($detailsRow.is(':visible')) {
            $detailsRow.hide();
            $(this).text('📋 Ver Mensaje');
        } else {
            // Ocultar todas las demás filas de detalles
            $('.cv-contact-details-row').hide();
            $('.cv-toggle-contact-details').text('📋 Ver Mensaje');
            
            // Mostrar esta fila
            $detailsRow.show();
            $(this).text('🔼 Ocultar Mensaje');
        }
    });
    
    // Toggle detalles de tarjetas vistas
    $('.cv-toggle-details').on('click', function() {
        var targetId = $(this).data('target');
        var $detailsRow = $('#' + targetId);
        
        if ($detailsRow.is(':visible')) {
            $detailsRow.hide();
            $(this).text('📋 Ver Detalles');
        } else {
            // Ocultar todas las demás filas de detalles
            $('.cv-details-row').hide();
            $('.cv-toggle-details').text('📋 Ver Detalles');
            
            // Mostrar esta fila
            $detailsRow.show();
            $(this).text('🔼 Ocultar Detalles');
        }
    });
});
</script>

