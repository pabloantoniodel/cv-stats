<?php
/**
 * Vista del Dashboard de Tickets para Vendedores
 * 
 * @package CV_Commissions
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Obtener datos
$vendor_id = get_current_user_id();
$tickets_dashboard = new CV_Tickets_Dashboard();
$tickets = $tickets_dashboard->get_vendor_tickets($vendor_id, 20, 0);
$stats = $tickets_dashboard->get_vendor_tickets_stats($vendor_id);
?>

<div class="collapse wcfm-collapse">
    <div class="wcfm-page-headig">
        <span class="wcfmfa fa-ticket-alt"></span>
        <span class="wcfm-page-heading-text"><?php _e('Mis Tickets', 'cv-commissions'); ?></span>
        <?php
        // Incluir el header panel oficial de WCFM con notificaciones
        $wcfm_views_path = WP_PLUGIN_DIR . '/wc-frontend-manager/views/';
        if (file_exists($wcfm_views_path . 'wcfm-view-header-panels.php')) {
            include($wcfm_views_path . 'wcfm-view-header-panels.php');
        }
        ?>
    </div>
    <div class="wcfm-collapse-content">
        <div id="wcfm_page_load"></div>
        
        <div class="wcfm-container">
            <div id="cv-tickets-dashboard-content" class="wcfm-content">
                
                <!-- Estadísticas -->
                <div class="cv-tickets-stats-container">
                    <div class="cv-tickets-stat-box cv-stat-total">
                        <div class="cv-stat-icon">
                            <span class="wcfmfa fa-ticket-alt"></span>
                        </div>
                        <div class="cv-stat-content">
                            <div class="cv-stat-value"><?php echo $stats->total_tickets; ?></div>
                            <div class="cv-stat-label"><?php _e('Total Tickets', 'cv-commissions'); ?></div>
                        </div>
                    </div>
                    
                    <div class="cv-tickets-stat-box cv-stat-pending">
                        <div class="cv-stat-icon">
                            <span class="wcfmfa fa-clock"></span>
                        </div>
                        <div class="cv-stat-content">
                            <div class="cv-stat-value"><?php echo $stats->pending_tickets; ?></div>
                            <div class="cv-stat-label"><?php _e('Tickets Pendientes', 'cv-commissions'); ?></div>
                            <div class="cv-stat-amount"><?php echo wc_price($stats->pending_amount); ?></div>
                        </div>
                    </div>
                    
                    <div class="cv-tickets-stat-box cv-stat-validated">
                        <div class="cv-stat-icon">
                            <span class="wcfmfa fa-check-circle"></span>
                        </div>
                        <div class="cv-stat-content">
                            <div class="cv-stat-value"><?php echo $stats->validated_tickets; ?></div>
                            <div class="cv-stat-label"><?php _e('Tickets Validados', 'cv-commissions'); ?></div>
                            <div class="cv-stat-amount"><?php echo wc_price($stats->validated_amount); ?></div>
                        </div>
                    </div>
                    
                    <div class="cv-tickets-stat-box cv-stat-total-amount">
                        <div class="cv-stat-icon">
                            <span class="wcfmfa fa-euro-sign"></span>
                        </div>
                        <div class="cv-stat-content">
                            <div class="cv-stat-value"><?php echo wc_price($stats->total_amount); ?></div>
                            <div class="cv-stat-label"><?php _e('Importe Total', 'cv-commissions'); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Lista de Tickets -->
                <div class="cv-tickets-table-container">
                    <h3><?php _e('Historial de Tickets', 'cv-commissions'); ?></h3>
                    
                    <?php if (empty($tickets)) : ?>
                        <div class="cv-no-tickets">
                            <span class="wcfmfa fa-ticket-alt"></span>
                            <p><?php _e('Aún no tienes tickets capturados', 'cv-commissions'); ?></p>
                        </div>
                    <?php else : ?>
                        <table class="cv-tickets-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Fecha', 'cv-commissions'); ?></th>
                                    <th><?php _e('Foto', 'cv-commissions'); ?></th>
                                    <th><?php _e('Importe', 'cv-commissions'); ?></th>
                                    <th><?php _e('Estado', 'cv-commissions'); ?></th>
                                    <th><?php _e('Validado', 'cv-commissions'); ?></th>
                                    <th><?php _e('Acción', 'cv-commissions'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="cv-tickets-list">
                                <?php foreach ($tickets as $ticket) : ?>
                                    <tr data-ticket-id="<?php echo $ticket->id; ?>" class="cv-ticket-row cv-ticket-<?php echo $ticket->status; ?>">
                                        <td><?php echo date('d/m/Y H:i', strtotime($ticket->created_at)); ?></td>
                        <td>
                            <a href="#" class="cv-ticket-photo-link" data-photo-url="<?php echo esc_url($ticket->photo_url); ?>">
                                <img src="<?php echo esc_url($ticket->photo_url); ?>" alt="Ticket" class="cv-ticket-photo-thumb">
                            </a>
                        </td>
                                        <td class="cv-ticket-amount"><?php echo wc_price($ticket->amount); ?></td>
                                        <td>
                                            <?php if ($ticket->status === 'pending') : ?>
                                                <span class="cv-ticket-badge cv-badge-pending">
                                                    <span class="wcfmfa fa-clock"></span>
                                                    <?php _e('Pendiente', 'cv-commissions'); ?>
                                                </span>
                                            <?php else : ?>
                                                <span class="cv-ticket-badge cv-badge-validated">
                                                    <span class="wcfmfa fa-check-circle"></span>
                                                    <?php _e('Validado', 'cv-commissions'); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($ticket->validated_at) : ?>
                                                <small><?php echo date('d/m/Y H:i', strtotime($ticket->validated_at)); ?></small>
                                            <?php else : ?>
                                                <small>-</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($ticket->status === 'pending') : ?>
                                                <button type="button" 
                                                        class="wcfm_submit_button cv-validate-ticket-btn" 
                                                        data-ticket-id="<?php echo $ticket->id; ?>">
                                                    <span class="wcfmfa fa-check"></span>
                                                    <?php _e('Validar', 'cv-commissions'); ?>
                                                </button>
                                            <?php else : ?>
                                                <span class="cv-validated-label">
                                                    <span class="wcfmfa fa-check-double"></span>
                                                    <?php _e('Validado', 'cv-commissions'); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- Paginación -->
                        <div class="cv-tickets-pagination">
                            <button type="button" id="cv-tickets-prev" class="wcfm_submit_button" disabled>
                                <span class="wcfmfa fa-arrow-left"></span>
                                <?php _e('Anterior', 'cv-commissions'); ?>
                            </button>
                            <span id="cv-tickets-page-info">
                                <?php _e('Página', 'cv-commissions'); ?> <span id="cv-current-page">1</span>
                            </span>
                            <button type="button" id="cv-tickets-next" class="wcfm_submit_button">
                                <?php _e('Siguiente', 'cv-commissions'); ?>
                                <span class="wcfmfa fa-arrow-right"></span>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                
            </div>
        </div>
    </div>
</div>

<!-- Modal para ver foto del ticket -->
<div id="cv-ticket-photo-modal" class="cv-photo-modal" style="display: none;">
    <div class="cv-photo-modal-overlay"></div>
    <div class="cv-photo-modal-content">
        <button type="button" class="cv-photo-modal-close">
            <span class="wcfmfa fa-times"></span>
        </button>
        <img id="cv-photo-modal-img" src="" alt="Ticket">
    </div>
</div>

