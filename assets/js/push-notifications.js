/**
 * Sistema de notificaciones push
 */

(function($) {
    'use strict';
    
    var PushNotifications = {
        
        init: function() {
            // Registrar Service Worker si está disponible
            if ('serviceWorker' in navigator) {
                this.registerServiceWorker();
            }
            
            // Verificar si el navegador soporta notificaciones
            if (!('Notification' in window)) {
                return;
            }
            
            // Verificar estado actual
            if (Notification.permission === 'default') {
                // Verificar si marcó "No mostrar de nuevo"
                var neverShow = localStorage.getItem('cv_notification_never_show');
                if (neverShow === 'true') {
                    console.log('🚫 CV Push: Usuario marcó "No mostrar de nuevo"');
                    return;
                }
                
                // Verificar si fue descartado y aún no pasaron 24 horas
                var dismissedUntil = localStorage.getItem('cv_notification_dismissed');
                var now = Date.now();
                
                if (dismissedUntil && now < parseInt(dismissedUntil)) {
                    console.log('🔕 CV Push: Prompt descartado hasta mañana');
                    return;
                }
                
                // Mostrar prompt después de 3 segundos
                setTimeout(function() {
                    $('#cv-notification-prompt').fadeIn(400);
                }, 3000);
            } else if (Notification.permission === 'granted') {
                console.log('🔔 CV Push: Notificaciones GRANTED - Iniciando polling');
                // Si ya tiene permisos, verificar notificaciones pendientes
                setTimeout(function() {
                    console.log('🔔 CV Push: Primera verificación de notificaciones...');
                    PushNotifications.checkPendingNotifications();
                }, 2000);
                
                // Verificar cada 30 segundos
                setInterval(function() {
                    console.log('🔔 CV Push: Polling de notificaciones (cada 30s)...');
                    PushNotifications.checkPendingNotifications();
                }, 30000);
            } else {
                console.log('⛔ CV Push: Notificaciones bloqueadas por el usuario');
            }
            
            this.bindEvents();
        },
        
        registerServiceWorker: function() {
            var swPath = cvPushNotifications.sw_url || '/wp-content/plugins/cv-commissions/assets/js/notification-sw.js';
            
            // Añadir versión para forzar actualización
            swPath = swPath + '?v=' + Date.now();
            
            console.log('🔄 CV Push: Registrando Service Worker:', swPath);
            
            navigator.serviceWorker.register(swPath)
                .then(function(registration) {
                    console.log('✅ CV Push: Service Worker registrado');
                    
                    // Forzar actualización inmediata
                    registration.update();
                    
                    // Forzar activación inmediata si hay uno esperando
                    if (registration.waiting) {
                        console.log('⏳ CV Push: Service Worker esperando, forzando activación...');
                        registration.waiting.postMessage({type: 'SKIP_WAITING'});
                    }
                    
                    // Si hay uno instalándose
                    if (registration.installing) {
                        console.log('📥 CV Push: Service Worker instalándose...');
                        registration.installing.addEventListener('statechange', function(e) {
                            if (e.target.state === 'installed') {
                                console.log('✅ CV Push: Service Worker instalado, activando...');
                                if (registration.waiting) {
                                    registration.waiting.postMessage({type: 'SKIP_WAITING'});
                                }
                            }
                        });
                    }
                })
                .catch(function(error) {
                    console.error('❌ CV Push: Error al registrar Service Worker:', error);
                });
        },
        
        bindEvents: function() {
            // Activar notificaciones
            $('#cv-enable-notifications').on('click', function(e) {
                e.preventDefault();
                PushNotifications.requestPermission();
            });
            
            // Descartar prompt (hasta mañana)
            $('#cv-dismiss-notifications').on('click', function(e) {
                e.preventDefault();
                PushNotifications.dismissPrompt();
            });
            
            // No mostrar de nuevo (permanente)
            $('#cv-never-show-notifications').on('click', function(e) {
                e.preventDefault();
                if (confirm('¿Estás seguro?\n\nNo volverás a ver este mensaje. Podrás reactivar las notificaciones desde el botón flotante de la campana.')) {
                    PushNotifications.neverShowAgain();
                }
            });
        },
        
        requestPermission: function() {
            Notification.requestPermission().then(function(permission) {
                if (permission === 'granted') {
                    PushNotifications.saveNotificationStatus('granted');
                    PushNotifications.showWelcomeNotification();
                    $('#cv-notification-prompt').fadeOut(300);
                } else {
                    PushNotifications.saveNotificationStatus('denied');
                    $('#cv-notification-prompt').fadeOut(300);
                }
            }).catch(function(error) {
                console.error('Error al solicitar permiso:', error);
            });
        },
        
        saveNotificationStatus: function(status) {
            $.ajax({
                url: cvPushNotifications.ajax_url,
                type: 'POST',
                data: {
                    action: 'cv_save_notification_token',
                    nonce: cvPushNotifications.nonce,
                    token: this.generateToken(),
                    status: status
                }
            });
        },
        
        dismissPrompt: function() {
            $('#cv-notification-prompt').fadeOut(300);
            
            // Guardar timestamp de cuando se descartó (no mostrar hasta el día siguiente)
            var tomorrow = Date.now() + (24 * 60 * 60 * 1000); // 24 horas
            localStorage.setItem('cv_notification_dismissed', tomorrow);
            console.log('🔕 CV Push: Notificación descartada hasta mañana');
        },
        
        neverShowAgain: function() {
            $('#cv-notification-prompt').fadeOut(300);
            
            // Guardar estado permanente de "denied"
            this.saveNotificationStatus('denied');
            
            // Marcar en localStorage como permanente
            localStorage.setItem('cv_notification_never_show', 'true');
            console.log('🚫 CV Push: No mostrar de nuevo - PERMANENTE');
        },
        
        showWelcomeNotification: function() {
            new Notification('¡Notificaciones Activadas!', {
                body: 'Te avisaremos cuando recibas nuevos tickets de clientes',
                icon: cvPushNotifications.icon || '',
                badge: cvPushNotifications.badge || ''
            });
        },
        
        generateToken: function() {
            // Generar un token único para este dispositivo
            var token = localStorage.getItem('cv_notification_token');
            
            if (!token) {
                token = 'cv_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                localStorage.setItem('cv_notification_token', token);
            }
            
            return token;
        },
        
        checkPendingNotifications: function() {
            console.log('🔍 CV Push: Consultando servidor por notificaciones...');
            $.ajax({
                url: cvPushNotifications.ajax_url,
                type: 'POST',
                data: {
                    action: 'cv_get_pending_notifications',
                    nonce: cvPushNotifications.nonce
                },
                success: function(response) {
                    console.log('📥 CV Push: Respuesta del servidor:', response);
                    if (response.success && response.data.notifications) {
                        console.log('✅ CV Push: ' + response.data.notifications.length + ' notificaciones pendientes');
                        response.data.notifications.forEach(function(notification) {
                            console.log('🔔 CV Push: Mostrando notificación:', notification);
                            PushNotifications.showNotification(notification);
                        });
                    } else {
                        console.log('ℹ️ CV Push: Sin notificaciones pendientes o respuesta incorrecta');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ CV Push: Error al consultar notificaciones:', error, xhr);
                }
            });
        },
        
        showNotification: function(notification) {
            console.log('💬 CV Push: showNotification llamado con:', notification);
            var title = '🎟️ Nuevo Ticket Recibido';
            var body = notification.customer_name + ' te ha enviado un ticket de ' + notification.amount + '€';
            
            console.log('📄 CV Push: Mostrando notificación en página...');
            // Mostrar notificación flotante en la página (siempre visible)
            this.showInPageNotification(notification, title, body);
            
            console.log('🌐 CV Push: Intentando mostrar notificación del navegador...');
            // También intentar mostrar notificación del navegador
            try {
                var browserNotification = new Notification(title, {
                    body: body,
                    icon: cvPushNotifications.icon || '',
                    badge: cvPushNotifications.badge || '',
                    tag: notification.id,
                    requireInteraction: true,
                    vibrate: [200, 100, 200]
                });
                
                console.log('✅ CV Push: Notificación del navegador mostrada');
                
                browserNotification.onclick = function(event) {
                    window.focus();
                    this.close();
                    PushNotifications.markAsRead(notification.id);
                    window.location.href = '/store-manager/cv-tickets/';
                };
            } catch (error) {
                console.warn('⚠️ CV Push: No se pudo mostrar notificación del navegador:', error);
            }
        },
        
        showInPageNotification: function(notification, title, body) {
            console.log('🎨 CV Push: showInPageNotification llamado');
            var container = $('#cv-notifications-container');
            
            console.log('📦 CV Push: Contenedor encontrado:', container.length > 0 ? 'SÍ' : 'NO');
            
            // Si no existe el contenedor, crearlo
            if (!container.length) {
                console.log('🔨 CV Push: Creando contenedor de notificaciones...');
                $('body').append('<div id="cv-notifications-container" style="' +
                    'position: fixed;' +
                    'top: 80px;' +
                    'right: 20px;' +
                    'z-index: 999999;' +
                    'max-width: 400px;' +
                '"></div>');
                container = $('#cv-notifications-container');
                console.log('✅ CV Push: Contenedor creado');
            }
            
            var notifHtml = $('<div class="cv-in-page-notification" data-notification-id="' + notification.id + '" style="' +
                'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);' +
                'color: white;' +
                'padding: 20px;' +
                'border-radius: 12px;' +
                'margin-bottom: 15px;' +
                'box-shadow: 0 10px 30px rgba(0,0,0,0.3);' +
                'animation: cvSlideInRight 0.4s ease-out;' +
                'cursor: pointer;' +
            '">' +
                '<div style="display: flex; align-items: start; gap: 15px;">' +
                    '<div style="font-size: 40px;">🎟️</div>' +
                    '<div style="flex: 1;">' +
                        '<div style="font-size: 18px; font-weight: 700; margin-bottom: 5px;">' + title + '</div>' +
                        '<div style="font-size: 14px; opacity: 0.9;">' + body + '</div>' +
                        '<div style="margin-top: 15px; display: flex; gap: 10px;">' +
                            '<button class="cv-notif-validate" data-ticket-id="' + notification.ticket_id + '" style="' +
                                'flex: 1;' +
                                'padding: 10px 15px;' +
                                'background: white;' +
                                'color: #667eea;' +
                                'border: none;' +
                                'border-radius: 6px;' +
                                'font-weight: 600;' +
                                'cursor: pointer;' +
                            '">✅ Validar</button>' +
                            '<button class="cv-notif-view" style="' +
                                'flex: 1;' +
                                'padding: 10px 15px;' +
                                'background: rgba(255,255,255,0.2);' +
                                'color: white;' +
                                'border: none;' +
                                'border-radius: 6px;' +
                                'font-weight: 600;' +
                                'cursor: pointer;' +
                            '">👁️ Ver</button>' +
                            '<button class="cv-notif-close" style="' +
                                'padding: 10px;' +
                                'background: rgba(255,255,255,0.2);' +
                                'color: white;' +
                                'border: none;' +
                                'border-radius: 6px;' +
                                'cursor: pointer;' +
                            '">✕</button>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>');
            
            container.append(notifHtml);
            
            // Eventos de los botones
            notifHtml.find('.cv-notif-validate').on('click', function(e) {
                e.stopPropagation();
                PushNotifications.markAsRead(notification.id);
                window.location.href = '/store-manager/cv-tickets/?action=validate&ticket_id=' + notification.ticket_id;
            });
            
            notifHtml.find('.cv-notif-view').on('click', function(e) {
                e.stopPropagation();
                PushNotifications.markAsRead(notification.id);
                window.location.href = '/store-manager/cv-tickets/';
            });
            
            notifHtml.find('.cv-notif-close').on('click', function(e) {
                e.stopPropagation();
                notifHtml.fadeOut(300, function() {
                    $(this).remove();
                });
                PushNotifications.markAsRead(notification.id);
            });
            
            // Auto-cerrar después de 30 segundos si no hay interacción
            setTimeout(function() {
                if (notifHtml.is(':visible')) {
                    notifHtml.fadeOut(300, function() {
                        $(this).remove();
                    });
                    PushNotifications.markAsRead(notification.id);
                }
            }, 30000);
        },
        
        markAsRead: function(notificationId) {
            $.ajax({
                url: cvPushNotifications.ajax_url,
                type: 'POST',
                data: {
                    action: 'cv_mark_notification_read',
                    nonce: cvPushNotifications.nonce,
                    notification_id: notificationId
                },
                success: function(response) {
                    console.log('Notificación marcada como leída');
                }
            });
        }
    };
    
    // Escuchar mensajes del Service Worker (cuando se hace click en notificación)
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', function(event) {
            console.log('📨 CV Push: Mensaje recibido del SW:', event.data);
            
            if (event.data && event.data.type === 'SHOW_TICKET_POPUP') {
                // Mostrar el popup flotante con los datos del ticket
                var data = event.data.data || {};
                var action = event.data.action;
                
                console.log('🔔 CV Push: Mostrando popup para ticket:', data);
                
                // Construir título y cuerpo de la notificación
                var title = '🎟️ Nuevo Ticket Recibido';
                var body = 'Ticket #' + (data.ticket_id || '?') + ' - ' + (data.amount || '0') + '€';
                
                // Mostrar el popup flotante existente
                PushNotifications.showInPageNotification(data, title, body);
            }
        });
    }
    
    // Inicializar cuando el DOM esté listo
    $(document).ready(function() {
        PushNotifications.init();
    });
    
})(jQuery);

