<?php
/**
 * Dashboard de Comisiones CV
 * Vista para el panel de vendedor en WCFM
 * 
 * @package CV_Commissions
 * @since 1.0.3
 */

if (!defined('ABSPATH')) {
    exit;
}

global $WCFM, $WCFMu;

$vendor_id = get_current_user_id();

// Obtener instancia del dashboard
$dashboard = new CV_Commissions_Dashboard();

// Obtener datos iniciales (10 por página)
$per_page = 10;
$totals = $dashboard->get_vendor_commissions_total($vendor_id);
$wallet = $dashboard->get_wallet_balance($vendor_id);
$periods = $dashboard->get_commissions_by_period($vendor_id);
$commissions = $dashboard->get_vendor_commissions($vendor_id, $per_page, 0);
$wcfm_orders = $dashboard->get_wcfm_order_commissions($vendor_id, $per_page, 0);
?>

<div class="collapse wcfm-collapse" id="wcfm_cv_commissions_dashboard">
    
    <div class="wcfm-page-headig">
        <span class="wcfmfa fa-money-bill-wave"></span>
        <span class="wcfm-page-heading-text"><?php _e('Dashboard de Comisiones CV', 'cv-commissions'); ?></span>
        <?php
        // Solo mostrar header panel si estamos en /store-manager/ (panel WCFM)
        // NO mostrarlo en /my-account/cv-comisiones/ (WooCommerce My Account)
        $current_url = $_SERVER['REQUEST_URI'];
        if (strpos($current_url, '/store-manager/') !== false) {
            // Incluir el header panel oficial de WCFM con notificaciones
            $wcfm_views_path = WP_PLUGIN_DIR . '/wc-frontend-manager/views/';
            if (file_exists($wcfm_views_path . 'wcfm-view-header-panels.php')) {
                include($wcfm_views_path . 'wcfm-view-header-panels.php');
            }
        }
        ?>
    </div>
    
    <div class="wcfm-container">
        <div id="wcfm-main-contentainer">
            <div class="wcfm-clearfix"></div>
            
            <!-- Tarjetas de resumen -->
            <div class="cv-dashboard-cards">
                
                <!-- Monedero WooCommerce -->
                <div class="cv-card cv-card-wallet">
                    <div class="cv-card-icon">
                        <span class="wcfmfa fa-wallet"></span>
                    </div>
                    <div class="cv-card-content">
                        <h3><?php _e('Monedero WooCommerce', 'cv-commissions'); ?></h3>
                        <?php if ($wallet['woo_available']): ?>
                            <div class="cv-card-amount"><?php echo wc_price($wallet['woo_balance']); ?></div>
                            <p class="cv-card-label"><?php _e('Balance disponible', 'cv-commissions'); ?></p>
                        <?php else: ?>
                            <p class="cv-card-label"><?php _e('Monedero no disponible', 'cv-commissions'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Comisiones UAP Disponibles -->
                <div class="cv-card cv-card-uap">
                    <div class="cv-card-icon">
                        <span class="wcfmfa fa-coins"></span>
                    </div>
                    <div class="cv-card-content">
                        <h3><?php _e('Comisiones UAP Aprobadas', 'cv-commissions'); ?></h3>
                        <div class="cv-card-amount"><?php echo wc_price($wallet['uap_total_available']); ?></div>
                        <p class="cv-card-label">
                            <?php printf(
                                __('Pagadas: %s | Pendiente pago: %s', 'cv-commissions'),
                                wc_price($wallet['uap_paid']),
                                wc_price($wallet['uap_approved_unpaid'])
                            ); ?>
                        </p>
                    </div>
                </div>
                
                <!-- Comisiones aprobadas -->
                <div class="cv-card cv-card-approved">
                    <div class="cv-card-icon">
                        <span class="wcfmfa fa-check-circle"></span>
                    </div>
                    <div class="cv-card-content">
                        <h3><?php _e('Comisiones Aprobadas', 'cv-commissions'); ?></h3>
                        <div class="cv-card-amount"><?php echo wc_price($totals->total_approved); ?></div>
                        <p class="cv-card-label"><?php printf(__('%d transacciones', 'cv-commissions'), $totals->uap_total_count); ?></p>
                    </div>
                </div>
                
                <!-- Comisiones pendientes -->
                <div class="cv-card cv-card-pending">
                    <div class="cv-card-icon">
                        <span class="wcfmfa fa-clock"></span>
                    </div>
                    <div class="cv-card-content">
                        <h3><?php _e('Comisiones Pendientes', 'cv-commissions'); ?></h3>
                        <div class="cv-card-amount"><?php echo wc_price($totals->total_pending); ?></div>
                        <p class="cv-card-label"><?php _e('En proceso de aprobación', 'cv-commissions'); ?></p>
                    </div>
                </div>
                
                <!-- Balance libro contable -->
                <div class="cv-card cv-card-ledger">
                    <div class="cv-card-icon">
                        <span class="wcfmfa fa-book"></span>
                    </div>
                    <div class="cv-card-content">
                        <h3><?php _e('Balance Libro Contable', 'cv-commissions'); ?></h3>
                        <div class="cv-card-amount"><?php echo wc_price($totals->ledger_balance); ?></div>
                        <p class="cv-card-label">
                            <?php printf(
                                __('Créditos: %s | Débitos: %s', 'cv-commissions'),
                                wc_price($totals->ledger_credits),
                                wc_price($totals->ledger_debits)
                            ); ?>
                        </p>
                    </div>
                </div>
                
            </div>
            
            <div class="wcfm-clearfix"></div>
            <br />
            
            <!-- Tarjetas de estadísticas por período -->
            <div class="cv-dashboard-cards">
                
                <!-- Mes Actual -->
                <div class="cv-card cv-card-period-current">
                    <div class="cv-card-icon">
                        <span class="wcfmfa fa-calendar-day"></span>
                    </div>
                    <div class="cv-card-content">
                        <h3><?php echo $periods['current_month_name']; ?></h3>
                        <div class="cv-card-amount"><?php echo wc_price($periods['current_month']); ?></div>
                        <p class="cv-card-label"><?php echo $periods['current_month_count']; ?> transacciones este mes</p>
                    </div>
                </div>
                
                <!-- Mes Pasado -->
                <div class="cv-card cv-card-period-last">
                    <div class="cv-card-icon">
                        <span class="wcfmfa fa-calendar-check"></span>
                    </div>
                    <div class="cv-card-content">
                        <h3><?php echo $periods['last_month_name']; ?></h3>
                        <div class="cv-card-amount"><?php echo wc_price($periods['last_month']); ?></div>
                        <p class="cv-card-label">
                            <?php echo $periods['last_month_count']; ?> transacciones
                            <?php 
                            $diff = $periods['current_month'] - $periods['last_month'];
                            if (abs($diff) > 0.01) :
                                $color = $diff > 0 ? '#28a745' : '#dc3545';
                                $icon = $diff > 0 ? '↗' : '↘';
                            ?>
                                <br><span style="color: <?php echo $color; ?>; font-weight: 700;">
                                    <?php echo $icon; ?> <?php echo ($diff > 0 ? '+' : '') . wc_price(abs($diff)); ?> vs este mes
                                </span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                
                <!-- Año Actual -->
                <div class="cv-card cv-card-period-year">
                    <div class="cv-card-icon">
                        <span class="wcfmfa fa-calendar-alt"></span>
                    </div>
                    <div class="cv-card-content">
                        <h3>Total <?php echo $periods['current_year_name']; ?></h3>
                        <div class="cv-card-amount"><?php echo wc_price($periods['current_year']); ?></div>
                        <p class="cv-card-label">
                            <?php echo $periods['current_year_count']; ?> transacciones
                            <?php 
                            $avg_month = $periods['current_year'] / max(1, intval(date('n')));
                            ?>
                            <br>Promedio: <?php echo wc_price($avg_month); ?>/mes
                        </p>
                    </div>
                </div>
                
            </div>
            
            <div class="wcfm-clearfix"></div>
            <br />
            
            <!-- Aviso informativo -->
            <div class="cv-info-box">
                <div class="cv-info-icon">ℹ️</div>
                <div class="cv-info-content">
                    <strong><?php _e('Diferencia entre Wallet WC y UAP Aprobadas:', 'cv-commissions'); ?></strong>
                    <ul style="margin: 5px 0 0 20px; padding: 0;">
                        <li><strong>Wallet WC (Monedero WooCommerce)</strong>: Balance disponible para compras inmediatas en la tienda.</li>
                        <li><strong>UAP Aprobadas</strong>: Comisiones aprobadas en el sistema de afiliados que pueden ser retiradas o transferidas al wallet.</li>
                        <li><strong>Pendientes UAP</strong>: Comisiones pendientes de aprobación por el administrador.</li>
                        <li><strong>Libro Contable</strong>: Registro histórico de créditos y débitos del marketplace WCFM.</li>
                    </ul>
                </div>
            </div>
            
            <div class="wcfm-clearfix"></div>
            <br />
            
            <!-- Pestañas -->
            <div class="cv-tabs">
                <div class="cv-tabs-nav">
                    <button class="cv-tab-btn active" data-tab="commissions-uap">
                        <?php _e('Comisiones UAP', 'cv-commissions'); ?>
                    </button>
                    <button class="cv-tab-btn" data-tab="commissions-wcfm">
                        <?php _e('Pedidos WCFM', 'cv-commissions'); ?>
                    </button>
                    <button class="cv-tab-btn" data-tab="wallet-transactions">
                        <?php _e('Transacciones Monedero', 'cv-commissions'); ?>
                    </button>
                </div>
                
                <!-- Tab: Comisiones UAP -->
                <div class="cv-tab-content active" id="tab-commissions-uap">
                    <div class="wcfm-container">
                        <div class="wcfm-clearfix"></div>
                        <table class="cv-table cv-table-commissions">
                            <thead>
                                <tr>
                                    <th><?php _e('ID', 'cv-commissions'); ?></th>
                                    <th><?php _e('Fecha', 'cv-commissions'); ?></th>
                                    <th><?php _e('Pedido', 'cv-commissions'); ?></th>
                                    <th><?php _e('Descripción', 'cv-commissions'); ?></th>
                                    <th><?php _e('Total Pedido', 'cv-commissions'); ?></th>
                                    <th><?php _e('Comisión', 'cv-commissions'); ?></th>
                                    <th><?php _e('%', 'cv-commissions'); ?></th>
                                    <th><?php _e('Estado', 'cv-commissions'); ?></th>
                                    <th><?php _e('Pago', 'cv-commissions'); ?></th>
                                    <th><?php _e('Fuente', 'cv-commissions'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($commissions)): ?>
                                    <?php foreach ($commissions as $commission): ?>
                                        <tr>
                                            <td><?php echo esc_html($commission->id); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($commission->created_date)); ?></td>
                                            <td>
                                                <?php if ($commission->order_id): ?>
                                                    <a href="#" class="cv-view-order" data-order-id="<?php echo esc_attr($commission->order_id); ?>">
                                                        📋 #<?php echo esc_html($commission->order_id); ?>
                                                    </a>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo esc_html($commission->description); ?></td>
                                            <td>
                                                <?php 
                                                if (!empty($commission->order_total)) {
                                                    echo wc_price($commission->order_total);
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <strong>
                                                    <?php 
                                                    $amount = floatval($commission->commission);
                                                    if ($amount >= 0) {
                                                        echo wc_price($amount); 
                                                    } else {
                                                        echo '<span class="cv-negative">' . wc_price($amount) . '</span>';
                                                    }
                                                    ?>
                                                </strong>
                                            </td>
                                            <td>
                                                <?php 
                                                if (!empty($commission->percentage)) {
                                                    echo '<span class="cv-percentage">' . esc_html(number_format($commission->percentage, 2)) . '%</span>';
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <span class="cv-status cv-status-<?php echo esc_attr($commission->status); ?>">
                                                    <?php echo esc_html(ucfirst($commission->status)); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($commission->payment_status)): ?>
                                                    <span class="cv-status cv-status-<?php echo esc_attr($commission->payment_status); ?>">
                                                        <?php echo esc_html(ucfirst($commission->payment_status)); ?>
                                                    </span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="cv-badge cv-badge-<?php echo esc_attr($commission->source); ?>">
                                                    <?php echo esc_html(strtoupper($commission->source)); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="10" class="cv-no-data">
                                            <?php _e('No hay comisiones registradas', 'cv-commissions'); ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        
                        <!-- Paginación UAP -->
                        <div class="cv-pagination">
                            <button class="cv-pagination-btn cv-pagination-prev" data-table="uap" data-page="1" disabled>
                                ← Anterior
                            </button>
                            <span class="cv-pagination-info">
                                Página <span id="cv-uap-current-page">1</span> | 
                                Mostrando <span id="cv-uap-showing">1-<?php echo min($per_page, count($commissions)); ?></span> de 
                                <span id="cv-uap-total"><?php echo $totals->uap_total_count; ?></span>
                            </span>
                            <button class="cv-pagination-btn cv-pagination-next" data-table="uap" data-page="2" 
                                    <?php echo count($commissions) < $per_page ? 'disabled' : ''; ?>>
                                Siguiente →
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Tab: Pedidos WCFM -->
                <div class="cv-tab-content" id="tab-commissions-wcfm">
                    <div class="wcfm-container">
                        <div class="wcfm-clearfix"></div>
                        <table class="cv-table cv-table-orders">
                            <thead>
                                <tr>
                                    <th><?php _e('ID', 'cv-commissions'); ?></th>
                                    <th><?php _e('Pedido', 'cv-commissions'); ?></th>
                                    <th><?php _e('Producto', 'cv-commissions'); ?></th>
                                    <th><?php _e('Cantidad', 'cv-commissions'); ?></th>
                                    <th><?php _e('Total Item', 'cv-commissions'); ?></th>
                                    <th><?php _e('Comisión', 'cv-commissions'); ?></th>
                                    <th><?php _e('Total Comisión', 'cv-commissions'); ?></th>
                                    <th><?php _e('Estado Pedido', 'cv-commissions'); ?></th>
                                    <th><?php _e('Estado Comisión', 'cv-commissions'); ?></th>
                                    <th><?php _e('Fecha', 'cv-commissions'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($wcfm_orders)): ?>
                                    <?php foreach ($wcfm_orders as $order): ?>
                                        <tr>
                                            <td><?php echo esc_html($order->ID); ?></td>
                                            <td>
                                                <a href="<?php echo admin_url('post.php?post=' . $order->order_id . '&action=edit'); ?>" target="_blank">
                                                    #<?php echo esc_html($order->order_id); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <?php 
                                                $product = wc_get_product($order->product_id);
                                                echo $product ? esc_html($product->get_name()) : '#' . $order->product_id;
                                                ?>
                                            </td>
                                            <td><?php echo esc_html($order->quantity); ?></td>
                                            <td><?php echo wc_price($order->item_total); ?></td>
                                            <td><?php echo wc_price($order->commission_amount); ?></td>
                                            <td><strong><?php echo wc_price($order->total_commission); ?></strong></td>
                                            <td>
                                                <span class="cv-status cv-status-<?php echo esc_attr($order->order_status); ?>">
                                                    <?php echo esc_html(ucfirst($order->order_status)); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="cv-status cv-status-<?php echo esc_attr($order->commission_status); ?>">
                                                    <?php echo esc_html(ucfirst($order->commission_status)); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($order->created)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="10" class="cv-no-data">
                                            <?php _e('No hay pedidos registrados', 'cv-commissions'); ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        
                        <!-- Paginación WCFM -->
                        <div class="cv-pagination">
                            <button class="cv-pagination-btn cv-pagination-prev" data-table="wcfm" data-page="1" disabled>
                                ← Anterior
                            </button>
                            <span class="cv-pagination-info">
                                Página <span id="cv-wcfm-current-page">1</span> | 
                                Mostrando <span id="cv-wcfm-showing">1-<?php echo min($per_page, count($wcfm_orders)); ?></span> de 
                                <span id="cv-wcfm-total"><?php echo $totals->orders_count; ?></span>
                            </span>
                            <button class="cv-pagination-btn cv-pagination-next" data-table="wcfm" data-page="2"
                                    <?php echo count($wcfm_orders) < $per_page ? 'disabled' : ''; ?>>
                                Siguiente →
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Tab: Transacciones Monedero -->
                <div class="cv-tab-content" id="tab-wallet-transactions">
                    <div class="wcfm-container">
                        <div class="wcfm-clearfix"></div>
                        <?php if ($wallet['available']): ?>
                            <?php 
                            $wallet_transactions = $dashboard->get_wallet_transactions($vendor_id, 20);
                            ?>
                            <table class="cv-table cv-table-wallet">
                                <thead>
                                    <tr>
                                        <th><?php _e('ID', 'cv-commissions'); ?></th>
                                        <th><?php _e('Fecha', 'cv-commissions'); ?></th>
                                        <th><?php _e('Tipo', 'cv-commissions'); ?></th>
                                        <th><?php _e('Detalles', 'cv-commissions'); ?></th>
                                        <th><?php _e('Monto', 'cv-commissions'); ?></th>
                                        <th><?php _e('Balance', 'cv-commissions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($wallet_transactions)): ?>
                                        <?php foreach ($wallet_transactions as $transaction): ?>
                                            <tr>
                                                <td><?php echo esc_html($transaction->transaction_id); ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($transaction->date)); ?></td>
                                                <td>
                                                    <span class="cv-badge cv-badge-<?php echo esc_attr($transaction->type); ?>">
                                                        <?php echo esc_html(ucfirst($transaction->type)); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo esc_html($transaction->details); ?></td>
                                                <td>
                                                    <?php 
                                                    $amount = floatval($transaction->amount);
                                                    if ($transaction->type == 'credit') {
                                                        echo '<span class="cv-positive">+' . wc_price($amount) . '</span>';
                                                    } else {
                                                        echo '<span class="cv-negative">-' . wc_price($amount) . '</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td><strong><?php echo wc_price($transaction->balance); ?></strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="cv-no-data">
                                                <?php _e('No hay transacciones en el monedero', 'cv-commissions'); ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            
                            <!-- Paginación Wallet -->
                            <div class="cv-pagination">
                                <button class="cv-pagination-btn cv-pagination-prev" data-table="wallet" data-page="1" disabled>
                                    ← Anterior
                                </button>
                                <span class="cv-pagination-info">
                                    Página <span id="cv-wallet-current-page">1</span>
                                </span>
                                <button class="cv-pagination-btn cv-pagination-next" data-table="wallet" data-page="2"
                                        <?php echo count($wallet_transactions) < 10 ? 'disabled' : ''; ?>>
                                    Siguiente →
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="cv-no-data">
                                <p><?php _e('El plugin WooCommerce Wallet no está activo.', 'cv-commissions'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            </div>
            
            <div class="wcfm-clearfix"></div>
        </div>
    </div>
</div>

<!-- Modal para ver resumen del pedido -->
<div id="cv-order-modal" class="cv-modal" style="display:none;">
    <div class="cv-modal-overlay"></div>
    <div class="cv-modal-content">
        <div class="cv-modal-header">
            <h3><?php _e('Resumen del Pedido', 'cv-commissions'); ?> <span id="cv-modal-order-number"></span></h3>
            <button class="cv-modal-close">&times;</button>
        </div>
        <div class="cv-modal-body" id="cv-modal-body">
            <div class="cv-modal-loading">
                <span class="wcfmfa fa-spinner fa-spin"></span>
                <?php _e('Cargando...', 'cv-commissions'); ?>
            </div>
        </div>
    </div>
</div>

