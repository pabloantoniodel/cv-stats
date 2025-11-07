/**
 * JavaScript para el Dashboard de Tickets
 * 
 * @package CV_Commissions
 * @since 1.2.0
 */

jQuery(document).ready(function($) {
    'use strict';
    
    var currentPage = 1;
    var isLoading = false;
    
    /**
     * Abrir modal de foto
     */
    $(document).on('click', '.cv-ticket-photo-link', function(e) {
        e.preventDefault();
        
        var photoUrl = $(this).data('photo-url');
        
        if (photoUrl) {
            $('#cv-photo-modal-img').attr('src', photoUrl);
            $('#cv-ticket-photo-modal').fadeIn(300);
            $('body').css('overflow', 'hidden'); // Prevenir scroll
        }
    });
    
    /**
     * Cerrar modal de foto
     */
    function closePhotoModal() {
        $('#cv-ticket-photo-modal').fadeOut(300);
        $('body').css('overflow', ''); // Restaurar scroll
    }
    
    // Cerrar al hacer clic en el botón X
    $(document).on('click', '.cv-photo-modal-close', function(e) {
        e.preventDefault();
        closePhotoModal();
    });
    
    // Cerrar al hacer clic en el overlay
    $(document).on('click', '.cv-photo-modal-overlay', function() {
        closePhotoModal();
    });
    
    // Cerrar con tecla ESC
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#cv-ticket-photo-modal').is(':visible')) {
            closePhotoModal();
        }
    });
    
    /**
     * Validar un ticket
     */
    $(document).on('click', '.cv-validate-ticket-btn', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var ticketId = $btn.data('ticket-id');
        
        if (isLoading) {
            return;
        }
        
        if (!confirm('¿Estás seguro de que quieres validar este ticket?')) {
            return;
        }
        
        isLoading = true;
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="wcfmfa fa-spinner fa-spin"></span> Validando...');
        
        $.ajax({
            url: cvTicketsData.ajax_url,
            type: 'POST',
            data: {
                action: 'cv_validate_ticket',
                nonce: cvTicketsData.nonce,
                ticket_id: ticketId
            },
            success: function(response) {
                if (response.success) {
                    // Actualizar la fila
                    var $row = $btn.closest('tr');
                    
                    // Cambiar estado visual
                    $row.removeClass('cv-ticket-pending').addClass('cv-ticket-validated');
                    
                    // Actualizar badge de estado
                    $row.find('.cv-ticket-badge').removeClass('cv-badge-pending').addClass('cv-badge-validated')
                        .html('<span class="wcfmfa fa-check-circle"></span> Validado');
                    
                    // Actualizar fecha de validación
                    var now = new Date();
                    var dateStr = formatDate(now);
                    $row.find('td:nth-child(5)').html('<small>' + dateStr + '</small>');
                    
                    // Reemplazar botón por label
                    $btn.parent().html('<span class="cv-validated-label"><span class="wcfmfa fa-check-double"></span> Validado</span>');
                    
                    // Actualizar estadísticas
                    updateStats();
                    
                    // Mostrar mensaje de éxito
                    showNotice('Ticket validado correctamente', 'success');
                } else {
                    alert(response.data.message || 'Error al validar el ticket');
                    $btn.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                alert('Error de conexión al validar el ticket');
                $btn.prop('disabled', false).html(originalText);
            },
            complete: function() {
                isLoading = false;
            }
        });
    });
    
    /**
     * Paginación - Página anterior
     */
    $('#cv-tickets-prev').on('click', function() {
        if (currentPage > 1) {
            currentPage--;
            loadTickets(currentPage);
        }
    });
    
    /**
     * Paginación - Página siguiente
     */
    $('#cv-tickets-next').on('click', function() {
        currentPage++;
        loadTickets(currentPage);
    });
    
    /**
     * Cargar tickets vía AJAX
     */
    function loadTickets(page) {
        if (isLoading) {
            return;
        }
        
        isLoading = true;
        $('#cv-tickets-list').html('<tr><td colspan="6" class="cv-tickets-loading">Cargando...</td></tr>');
        
        $.ajax({
            url: cvTicketsData.ajax_url,
            type: 'POST',
            data: {
                action: 'cv_get_tickets',
                nonce: cvTicketsData.nonce,
                page: page
            },
            success: function(response) {
                if (response.success) {
                    renderTickets(response.data.tickets);
                    updatePagination(page, response.data.tickets.length, response.data.per_page);
                    updateStatsFromData(response.data.stats);
                } else {
                    $('#cv-tickets-list').html('<tr><td colspan="6">Error al cargar tickets</td></tr>');
                }
            },
            error: function() {
                $('#cv-tickets-list').html('<tr><td colspan="6">Error de conexión</td></tr>');
            },
            complete: function() {
                isLoading = false;
            }
        });
    }
    
    /**
     * Renderizar tickets en la tabla
     */
    function renderTickets(tickets) {
        var html = '';
        
        if (tickets.length === 0) {
            html = '<tr><td colspan="6" style="text-align: center; padding: 40px;">No hay más tickets</td></tr>';
        } else {
            tickets.forEach(function(ticket) {
                var statusClass = ticket.status === 'pending' ? 'cv-ticket-pending' : 'cv-ticket-validated';
                var badgeClass = ticket.status === 'pending' ? 'cv-badge-pending' : 'cv-badge-validated';
                var statusIcon = ticket.status === 'pending' ? 'fa-clock' : 'fa-check-circle';
                var statusText = ticket.status === 'pending' ? 'Pendiente' : 'Validado';
                var validatedDate = ticket.validated_at ? '<small>' + ticket.validated_at + '</small>' : '<small>-</small>';
                
                var actionHtml = '';
                if (ticket.status === 'pending') {
                    actionHtml = '<button type="button" class="wcfm_submit_button cv-validate-ticket-btn" data-ticket-id="' + ticket.id + '">' +
                                 '<span class="wcfmfa fa-check"></span> Validar</button>';
                } else {
                    actionHtml = '<span class="cv-validated-label"><span class="wcfmfa fa-check-double"></span> Validado</span>';
                }
                
                html += '<tr data-ticket-id="' + ticket.id + '" class="cv-ticket-row ' + statusClass + '">' +
                        '<td>' + ticket.date + '</td>' +
                        '<td><a href="' + ticket.photo_url + '" target="_blank" class="cv-ticket-photo-link">' +
                        '<img src="' + ticket.photo_url + '" alt="Ticket" class="cv-ticket-photo-thumb"></a></td>' +
                        '<td class="cv-ticket-amount">' + ticket.amount + '</td>' +
                        '<td><span class="cv-ticket-badge ' + badgeClass + '">' +
                        '<span class="wcfmfa ' + statusIcon + '"></span> ' + statusText + '</span></td>' +
                        '<td>' + validatedDate + '</td>' +
                        '<td>' + actionHtml + '</td>' +
                        '</tr>';
            });
        }
        
        $('#cv-tickets-list').html(html);
    }
    
    /**
     * Actualizar paginación
     */
    function updatePagination(page, itemsCount, perPage) {
        $('#cv-current-page').text(page);
        
        // Deshabilitar "Anterior" si estamos en página 1
        if (page === 1) {
            $('#cv-tickets-prev').prop('disabled', true);
        } else {
            $('#cv-tickets-prev').prop('disabled', false);
        }
        
        // Deshabilitar "Siguiente" si hay menos items que el límite
        if (itemsCount < perPage) {
            $('#cv-tickets-next').prop('disabled', true);
        } else {
            $('#cv-tickets-next').prop('disabled', false);
        }
    }
    
    /**
     * Actualizar estadísticas
     */
    function updateStats() {
        // Recargar la página para actualizar estadísticas
        location.reload();
    }
    
    /**
     * Actualizar estadísticas desde datos AJAX
     */
    function updateStatsFromData(stats) {
        $('.cv-stat-total .cv-stat-value').text(stats.total_tickets);
        $('.cv-stat-pending .cv-stat-value').text(stats.pending_tickets);
        $('.cv-stat-validated .cv-stat-value').text(stats.validated_tickets);
    }
    
    /**
     * Formatear fecha
     */
    function formatDate(date) {
        var day = ('0' + date.getDate()).slice(-2);
        var month = ('0' + (date.getMonth() + 1)).slice(-2);
        var year = date.getFullYear();
        var hours = ('0' + date.getHours()).slice(-2);
        var minutes = ('0' + date.getMinutes()).slice(-2);
        
        return day + '/' + month + '/' + year + ' ' + hours + ':' + minutes;
    }
    
    /**
     * Mostrar notificación
     */
    function showNotice(message, type) {
        var bgColor = type === 'success' ? '#d4edda' : '#f8d7da';
        var textColor = type === 'success' ? '#155724' : '#721c24';
        
        var $notice = $('<div class="cv-ticket-notice"></div>')
            .css({
                'position': 'fixed',
                'top': '20px',
                'right': '20px',
                'background': bgColor,
                'color': textColor,
                'padding': '15px 20px',
                'border-radius': '4px',
                'box-shadow': '0 4px 12px rgba(0,0,0,0.15)',
                'z-index': '999999',
                'max-width': '300px'
            })
            .text(message)
            .appendTo('body');
        
        setTimeout(function() {
            $notice.fadeOut(function() {
                $notice.remove();
            });
        }, 3000);
    }
});

