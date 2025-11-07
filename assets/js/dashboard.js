/**
 * Dashboard de Comisiones CV - JavaScript
 * 
 * @package CV_Commissions
 * @since 1.0.3
 */

(function($) {
    'use strict';
    
    /**
     * Clase principal del Dashboard
     */
    class CVCommissionsDashboard {
        
        constructor() {
            this.currentPages = {
                uap: 1,
                wcfm: 1,
                wallet: 1
            };
            this.currentTab = 'commissions-uap';
            this.init();
        }
        
        /**
         * Inicializar dashboard
         */
        init() {
            console.log('CVCommissionsDashboard.init() llamado');
            this.bindEvents();
            this.initTabs();
            console.log('✅ CV Commissions Dashboard inicializado');
            console.log('Links de pedidos encontrados:', $('.cv-view-order').length);
        }
        
        /**
         * Vincular eventos
         */
        bindEvents() {
            const self = this;
            
            // Cambiar pestañas
            $('.cv-tab-btn').on('click', (e) => {
                this.switchTab($(e.currentTarget));
            });
            
            // Actualizar datos (si existe botón de refresh)
            $('#cv-refresh-data').on('click', () => {
                this.loadData();
            });
            
            // Ver resumen de pedido
            $(document).on('click', '.cv-view-order', function(e) {
                e.preventDefault();
                console.log('Click en .cv-view-order detectado');
                const orderId = $(this).data('order-id');
                console.log('Order ID:', orderId);
                
                // Verificar que cvCommissionsData esté disponible
                if (typeof cvCommissionsData === 'undefined') {
                    console.error('cvCommissionsData no está definido!');
                    alert('Error: Configuración no disponible. Recarga la página.');
                    return;
                }
                
                self.showOrderModal(orderId);
            });
            
            // Cerrar modal
            $(document).on('click', '.cv-modal-close, .cv-modal-overlay', function() {
                self.closeModal();
            });
            
            // Cerrar con ESC
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('#cv-order-modal').is(':visible')) {
                    self.closeModal();
                }
            });
            
            // Paginación
            $(document).on('click', '.cv-pagination-btn', function(e) {
                e.preventDefault();
                console.log('Click en botón paginación');
                
                // Si está disabled, no hacer nada
                if ($(this).is(':disabled') || $(this).attr('disabled')) {
                    console.log('Botón disabled, ignorando click');
                    return false;
                }
                
                const table = $(this).data('table');
                const page = parseInt($(this).data('page'));
                console.log('Tabla:', table, '| Página:', page);
                
                if (!table || !page) {
                    console.error('Faltan datos: table o page');
                    return false;
                }
                
                self.loadPage(table, page);
            });
        }
        
        /**
         * Inicializar sistema de pestañas
         */
        initTabs() {
            // Mostrar la primera pestaña por defecto
            $('.cv-tab-btn').first().addClass('active');
            $('.cv-tab-content').first().addClass('active');
        }
        
        /**
         * Cambiar pestaña
         */
        switchTab($btn) {
            const tabId = $btn.data('tab');
            
            // Actualizar botones
            $('.cv-tab-btn').removeClass('active');
            $btn.addClass('active');
            
            // Actualizar contenido
            $('.cv-tab-content').removeClass('active');
            $('#tab-' + tabId).addClass('active');
            
            this.currentTab = tabId;
            
            console.log('Pestaña cambiada a: ' + tabId);
        }
        
        /**
         * Cargar página específica de una tabla
         */
        loadPage(table, page) {
            const self = this;
            
            console.log('Cargando página ' + page + ' de tabla ' + table);
            
            this.currentPages[table] = page;
            
            $.ajax({
                url: cvCommissionsData.ajax_url,
                type: 'POST',
                data: {
                    action: 'cv_get_commissions_page',
                    nonce: cvCommissionsData.nonce,
                    table: table,
                    page: page
                },
                beforeSend: function() {
                    // Mostrar loading en la tabla específica
                    $('#tab-commissions-' + table + ' tbody').html('<tr><td colspan="10" style="text-align:center;padding:20px;"><span class="wcfmfa fa-spinner fa-spin"></span> Cargando...</td></tr>');
                },
                success: function(response) {
                    if (response.success) {
                        console.log('✅ Página ' + page + ' cargada');
                        self.updateTable(table, response.data);
                    } else {
                        console.error('❌ Error:', response.data.message);
                        self.showError(response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Error AJAX:', error);
                    $('#tab-commissions-' + table + ' tbody').html('<tr><td colspan="10" style="text-align:center;padding:20px;color:red;">Error al cargar datos</td></tr>');
                }
            });
        }
        
        /**
         * Actualizar tabla con datos de paginación
         */
        updateTable(table, data) {
            let tbody = '';
            
            if (table === 'uap') {
                data.items.forEach((commission) => {
                    tbody += '<tr>';
                    tbody += '<td>' + commission.id + '</td>';
                    tbody += '<td>' + commission.date + '</td>';
                    tbody += '<td><a href="#" class="cv-view-order" data-order-id="' + commission.order_id + '">📋 #' + commission.order_id + '</a></td>';
                    tbody += '<td>' + commission.description + '</td>';
                    tbody += '<td>' + (commission.order_total || '-') + '</td>';
                    tbody += '<td><strong>' + commission.commission + '</strong></td>';
                    tbody += '<td>' + (commission.percentage || '-') + '</td>';
                    tbody += '<td><span class="cv-status cv-status-' + commission.status + '">' + commission.status + '</span></td>';
                    tbody += '<td>' + (commission.payment_status || '-') + '</td>';
                    tbody += '<td><span class="cv-badge cv-badge-' + commission.source + '">' + commission.source.toUpperCase() + '</span></td>';
                    tbody += '</tr>';
                });
                
                $('#tab-commissions-uap tbody').html(tbody);
                this.updatePagination('uap', data.page, data.total, data.per_page);
                
            } else if (table === 'wcfm') {
                // Similar para WCFM
                data.items.forEach((order) => {
                    tbody += '<tr>';
                    tbody += '<td>' + order.ID + '</td>';
                    tbody += '<td><a href="#" class="cv-view-order" data-order-id="' + order.order_id + '">📋 #' + order.order_id + '</a></td>';
                    tbody += '<td>' + order.product_name + '</td>';
                    tbody += '<td>' + order.quantity + '</td>';
                    tbody += '<td>' + order.item_total + '</td>';
                    tbody += '<td>' + order.commission_amount + '</td>';
                    tbody += '<td><strong>' + order.total_commission + '</strong></td>';
                    tbody += '<td><span class="cv-status cv-status-' + order.order_status + '">' + order.order_status + '</span></td>';
                    tbody += '<td><span class="cv-status cv-status-' + order.commission_status + '">' + order.commission_status + '</span></td>';
                    tbody += '<td>' + order.date + '</td>';
                    tbody += '</tr>';
                });
                
                $('#tab-commissions-wcfm tbody').html(tbody);
                this.updatePagination('wcfm', data.page, data.total, data.per_page);
            }
        }
        
        /**
         * Actualizar controles de paginación
         */
        updatePagination(table, page, total, perPage) {
            const totalPages = Math.ceil(total / perPage);
            const start = ((page - 1) * perPage) + 1;
            const end = Math.min(page * perPage, total);
            
            // Actualizar info
            $('#cv-' + table + '-current-page').text(page);
            $('#cv-' + table + '-showing').text(start + '-' + end);
            $('#cv-' + table + '-total').text(total);
            
            // Actualizar botones
            const $prevBtn = $('.cv-pagination-btn[data-table="' + table + '"].cv-pagination-prev');
            const $nextBtn = $('.cv-pagination-btn[data-table="' + table + '"].cv-pagination-next');
            
            if (page <= 1) {
                $prevBtn.attr('disabled', true).data('page', 1);
            } else {
                $prevBtn.attr('disabled', false).data('page', page - 1);
            }
            
            if (page >= totalPages) {
                $nextBtn.attr('disabled', true).data('page', page);
            } else {
                $nextBtn.attr('disabled', false).data('page', page + 1);
            }
        }
        
        /**
         * Cargar datos vía AJAX (método antiguo - mantener por compatibilidad)
         */
        loadData() {
            console.log('loadData() - función legacy');
        }
        
        /**
         * Actualizar dashboard con nuevos datos
         */
        updateDashboard(data) {
            // Actualizar tarjetas de resumen
            if (data.totals) {
                this.updateTotals(data.totals);
            }
            
            // Actualizar wallet
            if (data.wallet) {
                this.updateWallet(data.wallet);
            }
            
            // Actualizar tablas
            if (data.commissions) {
                this.updateCommissionsTable(data.commissions);
            }
            
            if (data.wcfm_orders) {
                this.updateOrdersTable(data.wcfm_orders);
            }
            
            if (data.wallet_transactions) {
                this.updateWalletTable(data.wallet_transactions);
            }
        }
        
        /**
         * Actualizar totales
         */
        updateTotals(totals) {
            // Implementar según necesidades
            console.log('Totales actualizados:', totals);
        }
        
        /**
         * Actualizar wallet
         */
        updateWallet(wallet) {
            // Implementar según necesidades
            console.log('Wallet actualizado:', wallet);
        }
        
        /**
         * Actualizar tabla de comisiones
         */
        updateCommissionsTable(commissions) {
            // Implementar según necesidades
            console.log('Tabla de comisiones actualizada:', commissions.length + ' items');
        }
        
        /**
         * Actualizar tabla de pedidos
         */
        updateOrdersTable(orders) {
            // Implementar según necesidades
            console.log('Tabla de pedidos actualizada:', orders.length + ' items');
        }
        
        /**
         * Actualizar tabla de wallet
         */
        updateWalletTable(transactions) {
            // Implementar según necesidades
            console.log('Tabla de wallet actualizada:', transactions.length + ' items');
        }
        
        /**
         * Mostrar loading
         */
        showLoading() {
            // Agregar overlay de loading si no existe
            if ($('#cv-loading-overlay').length === 0) {
                $('body').append('<div id="cv-loading-overlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:999999;display:flex;align-items:center;justify-content:center;"><div style="background:#fff;padding:20px;border-radius:8px;"><span class="wcfmfa fa-spinner fa-spin"></span> Cargando...</div></div>');
            }
        }
        
        /**
         * Ocultar loading
         */
        hideLoading() {
            $('#cv-loading-overlay').remove();
        }
        
        /**
         * Mostrar error
         */
        showError(message) {
            alert(message); // Puedes mejorar esto con un modal o notificación mejor
        }
        
        /**
         * Mostrar modal con resumen del pedido
         */
        showOrderModal(orderId) {
            const self = this;
            
            console.log('showOrderModal() llamado con orderId:', orderId);
            console.log('Modal existe?', $('#cv-order-modal').length);
            
            if ($('#cv-order-modal').length === 0) {
                console.error('Modal #cv-order-modal no encontrado en el DOM!');
                alert('Error: Modal no encontrado. Contacta al administrador.');
                return;
            }
            
            // Mostrar modal
            $('#cv-order-modal').fadeIn(300);
            $('#cv-modal-order-number').text('#' + orderId);
            $('#cv-modal-body').html('<div class="cv-modal-loading"><span class="wcfmfa fa-spinner fa-spin"></span> Cargando...</div>');
            
            console.log('Modal mostrado, cargando datos...');
            
            // Cargar datos del pedido
            $.ajax({
                url: cvCommissionsData.ajax_url,
                type: 'POST',
                data: {
                    action: 'cv_get_order_summary',
                    nonce: cvCommissionsData.nonce,
                    order_id: orderId
                },
                success: function(response) {
                    if (response.success) {
                        self.renderOrderSummary(response.data);
                    } else {
                        $('#cv-modal-body').html('<p class="cv-error">❌ ' + response.data.message + '</p>');
                    }
                },
                error: function() {
                    $('#cv-modal-body').html('<p class="cv-error">❌ Error al cargar el pedido</p>');
                }
            });
        }
        
        /**
         * Renderizar resumen del pedido
         */
        renderOrderSummary(order) {
            const self = this; // Guardar referencia
            let html = '<div class="cv-order-summary">';
            
            // Información general
            html += '<div class="cv-order-info">';
            html += '<div class="cv-order-info-item">';
            html += '<strong>📅 Fecha:</strong> ' + order.date;
            html += '</div>';
            html += '<div class="cv-order-info-item">';
            html += '<strong>📊 Estado:</strong> <span class="cv-order-status">' + order.status + '</span>';
            html += '</div>';
            html += '<div class="cv-order-info-item">';
            html += '<strong>💳 Pago:</strong> ' + order.payment_method;
            html += '</div>';
            html += '</div>';
            
            // Productos
            html += '<h4>🛍️ Productos</h4>';
            html += '<table class="cv-modal-table">';
            html += '<thead><tr>';
            html += '<th>Producto</th>';
            html += '<th>SKU</th>';
            html += '<th>Cant.</th>';
            html += '<th>Precio</th>';
            html += '<th>Total</th>';
            html += '</tr></thead>';
            html += '<tbody>';
            
            // Usar arrow function para mantener el contexto
            order.items.forEach((item) => {
                html += '<tr>';
                html += '<td>' + item.name + '</td>';
                html += '<td>' + (item.sku || '-') + '</td>';
                html += '<td>' + item.quantity + '</td>';
                html += '<td>' + this.formatPrice(item.subtotal / item.quantity, order.currency) + '</td>';
                html += '<td><strong>' + this.formatPrice(item.total, order.currency) + '</strong></td>';
                html += '</tr>';
            });
            
            html += '</tbody></table>';
            
            // Totales
            html += '<div class="cv-order-totals">';
            html += '<div class="cv-order-total-row">';
            html += '<span>Subtotal:</span>';
            html += '<span>' + this.formatPrice(order.subtotal, order.currency) + '</span>';
            html += '</div>';
            html += '<div class="cv-order-total-row">';
            html += '<span>Envío:</span>';
            html += '<span>' + this.formatPrice(order.shipping, order.currency) + '</span>';
            html += '</div>';
            html += '<div class="cv-order-total-row">';
            html += '<span>Impuestos:</span>';
            html += '<span>' + this.formatPrice(order.tax, order.currency) + '</span>';
            html += '</div>';
            html += '<div class="cv-order-total-row cv-order-total-final">';
            html += '<span><strong>Total:</strong></span>';
            html += '<span><strong>' + this.formatPrice(order.total, order.currency) + '</strong></span>';
            html += '</div>';
            html += '</div>';
            
            // Información de facturación
            html += '<h4>📧 Cliente</h4>';
            html += '<div class="cv-order-billing">';
            html += '<p><strong>Nombre:</strong> ' + order.billing.name + '</p>';
            html += '<p><strong>Email:</strong> ' + order.billing.email + '</p>';
            html += '<p><strong>Teléfono:</strong> ' + order.billing.phone + '</p>';
            html += '<p><strong>Dirección:</strong><br>' + order.billing.address.replace(/\n/g, '<br>') + '</p>';
            html += '</div>';
            
            html += '</div>';
            
            $('#cv-modal-body').html(html);
        }
        
        /**
         * Formatear precio
         */
        formatPrice(amount, currency) {
            return new Intl.NumberFormat('es-ES', {
                style: 'currency',
                currency: currency || 'EUR'
            }).format(amount);
        }
        
        /**
         * Cerrar modal
         */
        closeModal() {
            $('#cv-order-modal').fadeOut(300);
        }
    }
    
    /**
     * Inicializar cuando el DOM esté listo
     */
    $(document).ready(function() {
        console.log('CV Commissions: DOM ready');
        console.log('WCFM dashboard:', $('#wcfm_cv_commissions_dashboard').length);
        console.log('cvCommissionsData:', typeof cvCommissionsData !== 'undefined' ? cvCommissionsData : 'NO DEFINIDO');
        
        // Inicializar si estamos en la página correcta O si hay tablas de comisiones
        if ($('#wcfm_cv_commissions_dashboard').length > 0 || $('.cv-table-commissions').length > 0 || $('.cv-woo-dashboard-widget').length > 0) {
            console.log('Inicializando CV Commissions Dashboard...');
            window.cvDashboard = new CVCommissionsDashboard();
        } else {
            console.log('CV Commissions: No se encontró contenedor');
        }
    });
    
})(jQuery);

