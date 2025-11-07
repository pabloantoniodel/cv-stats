<?php
/**
 * Vista de tickets para clientes en WooCommerce My Account
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="cv-customer-tickets-wrapper">
    
    <div class="cv-customer-tickets-header">
        <h2><?php _e('Mis Tickets', 'cv-commissions'); ?></h2>
        <p class="cv-description"><?php _e('Aquí puedes ver todos los tickets que has enviado a los comercios', 'cv-commissions'); ?></p>
    </div>
    
    <!-- Estadísticas -->
    <div class="cv-customer-stats">
        <div class="cv-stat-card cv-stat-total">
            <div class="cv-stat-icon">📋</div>
            <div class="cv-stat-content">
                <div class="cv-stat-value"><?php echo esc_html($stats['total']); ?></div>
                <div class="cv-stat-label"><?php _e('Total Tickets', 'cv-commissions'); ?></div>
            </div>
        </div>
        
        <div class="cv-stat-card cv-stat-pending">
            <div class="cv-stat-icon">⏳</div>
            <div class="cv-stat-content">
                <div class="cv-stat-value"><?php echo esc_html($stats['pending']); ?></div>
                <div class="cv-stat-label"><?php _e('Pendientes', 'cv-commissions'); ?></div>
            </div>
        </div>
        
        <div class="cv-stat-card cv-stat-validated">
            <div class="cv-stat-icon">✅</div>
            <div class="cv-stat-content">
                <div class="cv-stat-value"><?php echo esc_html($stats['validated']); ?></div>
                <div class="cv-stat-label"><?php _e('Validados', 'cv-commissions'); ?></div>
            </div>
        </div>
        
        <div class="cv-stat-card cv-stat-amount">
            <div class="cv-stat-icon">💰</div>
            <div class="cv-stat-content">
                <div class="cv-stat-value"><?php echo wc_price($stats['total_amount']); ?></div>
                <div class="cv-stat-label"><?php _e('Importe Validado', 'cv-commissions'); ?></div>
            </div>
        </div>
    </div>
    
    <!-- Lista de tickets -->
    <div class="cv-customer-tickets-list">
        <!-- Debug Info (solo visible si está activado en opciones del plugin) -->
        <?php if (get_option('cv_ticket_debug_mode', false)): ?>
        <div class="cv-debug-info" style="background: #f0f0f0; padding: 15px; margin-bottom: 20px; border-radius: 8px; font-family: monospace; font-size: 12px; display: none;">
            <strong>📊 DEBUG INFO:</strong>
            <div id="cv-debug-output"></div>
        </div>
        <?php endif; ?>
        
        <div class="cv-tickets-loading" style="display:none;">
            <div class="cv-spinner"></div>
            <p><?php _e('Cargando tickets...', 'cv-commissions'); ?></p>
        </div>
        
        <div class="cv-tickets-container"></div>
        
        <div class="cv-tickets-empty" style="display:none;">
            <div class="cv-empty-icon">📄</div>
            <h3><?php _e('No tienes tickets todavía', 'cv-commissions'); ?></h3>
            <p><?php _e('Los tickets que envíes a los comercios aparecerán aquí', 'cv-commissions'); ?></p>
        </div>
        
        <div class="cv-tickets-pagination" style="display:none;">
            <button class="cv-btn cv-btn-secondary" id="cv-prev-page" disabled>
                <span class="wcfmfa fa-arrow-left"></span> <?php _e('Anterior', 'cv-commissions'); ?>
            </button>
            <span class="cv-page-info">
                <span id="cv-current-page">1</span> / <span id="cv-total-pages">1</span>
            </span>
            <button class="cv-btn cv-btn-secondary" id="cv-next-page" disabled>
                <?php _e('Siguiente', 'cv-commissions'); ?> <span class="wcfmfa fa-arrow-right"></span>
            </button>
        </div>
    </div>
    
</div>

<!-- Template para ticket item -->
<script type="text/html" id="cv-ticket-template">
    <div class="cv-ticket-item" data-ticket-id="{ticket_id}">
        <div class="cv-ticket-photo">
            <img src="{photo_url}" alt="Ticket">
        </div>
        <div class="cv-ticket-info">
            <div class="cv-ticket-vendor">
                <span class="wcfmfa fa-store"></span>
                <strong>{vendor_name}</strong>
            </div>
            <div class="cv-ticket-date">
                <span class="wcfmfa fa-calendar"></span>
                {formatted_date}
            </div>
            <div class="cv-ticket-amount">
                <span class="wcfmfa fa-euro-sign"></span>
                <strong>{amount}€</strong>
            </div>
        </div>
        <div class="cv-ticket-status">
            <span class="cv-status-badge cv-status-{status}">
                {status_label}
            </span>
        </div>
    </div>
</script>

