/**
 * JavaScript para Mis Tickets (WooCommerce My Account)
 */

(function($) {
    'use strict';
    
    var CustomerTickets = {
        currentPage: 1,
        totalPages: 1,
        loading: false,
        
        init: function() {
            console.log('CustomerTickets: Inicializando...');
            this.showDebug('✅ Inicializando sistema de tickets...');
            this.bindEvents();
            this.loadTickets();
        },
        
        showDebug: function(msg) {
            // Solo mostrar debug si está activado en las opciones del plugin
            if (typeof cvCustomerTickets !== 'undefined' && cvCustomerTickets.debug_mode && $('.cv-debug-info').length) {
                console.log('[DEBUG] ' + msg);
                $('.cv-debug-info').show();
                $('#cv-debug-output').append('<div>' + msg + '</div>');
            }
        },
        
        bindEvents: function() {
            $('#cv-prev-page').on('click', function(e) {
                e.preventDefault();
                if (CustomerTickets.currentPage > 1) {
                    CustomerTickets.currentPage--;
                    CustomerTickets.loadTickets();
                }
            });
            
            $('#cv-next-page').on('click', function(e) {
                e.preventDefault();
                if (CustomerTickets.currentPage < CustomerTickets.totalPages) {
                    CustomerTickets.currentPage++;
                    CustomerTickets.loadTickets();
                }
            });
        },
        
        loadTickets: function() {
            if (this.loading) return;
            
            console.log('CustomerTickets: Cargando tickets, página:', this.currentPage);
            this.showDebug('📡 Enviando petición AJAX - Página: ' + this.currentPage);
            
            this.loading = true;
            $('.cv-tickets-loading').show();
            $('.cv-tickets-container').hide();
            $('.cv-tickets-empty').hide();
            $('.cv-tickets-pagination').hide();
            
            $.ajax({
                url: cvCustomerTickets.ajax_url,
                type: 'POST',
                data: {
                    action: 'cv_get_customer_tickets',
                    nonce: cvCustomerTickets.nonce,
                    page: this.currentPage
                },
                success: function(response) {
                    console.log('CustomerTickets: Respuesta recibida:', response);
                    CustomerTickets.loading = false;
                    $('.cv-tickets-loading').hide();
                    
                    if (response.success && response.data.tickets) {
                        console.log('CustomerTickets: Tickets encontrados:', response.data.tickets.length);
                        CustomerTickets.showDebug('✅ Respuesta OK - Tickets: ' + response.data.tickets.length);
                        CustomerTickets.showDebug('📦 Datos: ' + JSON.stringify(response.data.tickets));
                        CustomerTickets.renderTickets(response.data.tickets);
                        CustomerTickets.totalPages = response.data.pages;
                        CustomerTickets.updatePagination();
                    } else {
                        console.log('CustomerTickets: No hay tickets');
                        CustomerTickets.showDebug('⚠️ No hay tickets o error en respuesta');
                        $('.cv-tickets-empty').show();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('CustomerTickets: Error AJAX:', error);
                    CustomerTickets.showDebug('❌ ERROR AJAX: ' + error + ' - Status: ' + status);
                    CustomerTickets.showDebug('Response: ' + xhr.responseText);
                    CustomerTickets.loading = false;
                    $('.cv-tickets-loading').hide();
                }
            });
        },
        
        renderTickets: function(tickets) {
            console.log('CustomerTickets: Renderizando tickets...', tickets);
            this.showDebug('🎨 Renderizando ' + tickets.length + ' tickets...');
            
            var container = $('.cv-tickets-container');
            container.empty();
            
            if (tickets.length === 0) {
                console.log('CustomerTickets: Array vacío, mostrando empty');
                this.showDebug('❌ Array de tickets vacío');
                $('.cv-tickets-empty').show();
                return;
            }
            
            var template = $('#cv-ticket-template').html();
            console.log('CustomerTickets: Template encontrado:', template ? 'SÍ' : 'NO');
            
            if (!template) {
                console.error('CustomerTickets: Template no encontrado!');
                this.showDebug('❌ ERROR: Template HTML no encontrado!');
                return;
            }
            
            this.showDebug('✅ Template encontrado, procesando tickets...');
            
            tickets.forEach(function(ticket) {
                console.log('CustomerTickets: Procesando ticket:', ticket);
                CustomerTickets.showDebug('🔹 Ticket #' + ticket.id + ' - ' + ticket.amount + '€ - ' + ticket.vendor_name);
                
                var html = template
                    .replace(/{ticket_id}/g, ticket.id)
                    .replace(/{photo_url}/g, ticket.photo_url)
                    .replace(/{vendor_name}/g, ticket.vendor_name)
                    .replace(/{formatted_date}/g, ticket.formatted_date)
                    .replace(/{amount}/g, parseFloat(ticket.amount).toFixed(2))
                    .replace(/{status}/g, ticket.status)
                    .replace(/{status_label}/g, CustomerTickets.getStatusLabel(ticket.status));
                
                console.log('CustomerTickets: HTML generado');
                container.append(html);
            });
            
            console.log('CustomerTickets: Mostrando contenedor');
            this.showDebug('✅ Tickets renderizados, mostrando lista...');
            container.show();
        },
        
        getStatusLabel: function(status) {
            var labels = {
                'pending': 'Pendiente',
                'validated': 'Validado',
                'rejected': 'Rechazado'
            };
            return labels[status] || status;
        },
        
        updatePagination: function() {
            if (this.totalPages <= 1) {
                $('.cv-tickets-pagination').hide();
                return;
            }
            
            $('.cv-tickets-pagination').show();
            $('#cv-current-page').text(this.currentPage);
            $('#cv-total-pages').text(this.totalPages);
            
            // Actualizar botones
            $('#cv-prev-page').prop('disabled', this.currentPage <= 1);
            $('#cv-next-page').prop('disabled', this.currentPage >= this.totalPages);
        }
    };
    
    // Inicializar cuando el DOM esté listo
    $(document).ready(function() {
        if ($('.cv-customer-tickets-wrapper').length) {
            CustomerTickets.init();
        }
    });
    
})(jQuery);

