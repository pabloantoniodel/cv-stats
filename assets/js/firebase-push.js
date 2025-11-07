/**
 * Firebase Cloud Messaging - Notificaciones Push
 * Funciona incluso con el navegador cerrado
 */

(function($) {
    'use strict';
    
    var FirebasePush = {
        
        messaging: null,
        app: null,
        
        init: function() {
            console.log('🔥 Firebase Push: Inicializando...');
            
            // Verificar soporte
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                console.warn('⚠️ Firebase Push: Navegador no soporta push notifications');
                return;
            }
            
            // Cargar Firebase SDK via CDN
            this.loadFirebaseSDK();
        },
        
        loadFirebaseSDK: function() {
            var self = this;
            
            // Cargar Firebase App - USAR VERSIONES COMPAT (no modulares)
            if (typeof firebase === 'undefined') {
                $.getScript('https://www.gstatic.com/firebasejs/12.4.0/firebase-app-compat.js', function() {
                    $.getScript('https://www.gstatic.com/firebasejs/12.4.0/firebase-messaging-compat.js', function() {
                        self.initializeFirebase();
                    });
                });
            } else {
                this.initializeFirebase();
            }
        },
        
        initializeFirebase: function() {
            var self = this;
            
            try {
                // Inicializar Firebase
                this.app = firebase.initializeApp(cvFirebaseConfig.config);
                this.messaging = firebase.messaging();
                
                console.log('✅ Firebase Push: Firebase inicializado');
                
                // Registrar Service Worker
                this.registerServiceWorker();
                
            } catch (error) {
                console.error('❌ Firebase Push: Error al inicializar:', error);
            }
        },
        
        registerServiceWorker: function() {
            var self = this;
            
            // Firebase Messaging ya registra su propio Service Worker automáticamente
            // Solo necesitamos esperar a que esté listo
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.ready.then(function(registration) {
                    console.log('✅ Firebase SW: Service Worker listo', registration);
                    
                    // Ahora solicitar permiso y token
                    self.requestPermission();
                    
                    // Escuchar mensajes cuando la app está en foreground
                    self.messaging.onMessage(function(payload) {
                        console.log('📨 Firebase: Mensaje recibido en foreground:', payload);
                        self.showNotification(payload);
                    });
                    
                }).catch(function(err) {
                    console.error('❌ Firebase SW: Error con Service Worker:', err);
                });
            }
        },
        
        requestPermission: function() {
            var self = this;
            
            console.log('🔔 Firebase: Solicitando permiso de notificaciones...');
            
            Notification.requestPermission().then(function(permission) {
                if (permission === 'granted') {
                    console.log('✅ Firebase: Permiso concedido, obteniendo token...');
                    self.getToken();
                } else {
                    console.warn('⛔ Firebase: Permiso denegado');
                }
            });
        },
        
        getToken: function() {
            var self = this;
            
            // VAPID key (Web Push certificate)
            var vapidKey = cvFirebaseConfig.vapid_key;
            
            if (!vapidKey) {
                console.warn('⚠️ Firebase: No hay VAPID key configurada');
                // Intentar obtener token sin VAPID (deprecado pero funciona)
            }
            
            var options = vapidKey ? { vapidKey: vapidKey } : {};
            
            this.messaging.getToken(options).then(function(currentToken) {
                if (currentToken) {
                    console.log('✅ Firebase: Token FCM obtenido:', currentToken.substr(0, 20) + '...');
                    self.saveToken(currentToken);
                } else {
                    console.warn('⚠️ Firebase: No se pudo obtener token. Permisos denegados?');
                }
            }).catch(function(err) {
                console.error('❌ Firebase: Error al obtener token:', err);
            });
        },
        
        saveToken: function(token) {
            console.log('💾 Firebase: Guardando token en servidor...');
            
            $.ajax({
                url: cvFirebaseConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'cv_save_fcm_token',
                    nonce: cvFirebaseConfig.nonce,
                    token: token
                },
                success: function(response) {
                    if (response.success) {
                        console.log('✅ Firebase: Token guardado correctamente');
                    } else {
                        console.error('❌ Firebase: Error al guardar token:', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Firebase: Error AJAX al guardar token:', error);
                }
            });
        },
        
        showNotification: function(payload) {
            console.log('🔔 Firebase: Mostrando notificación:', payload);
            
            var notification = payload.notification || {};
            var data = payload.data || {};
            
            var title = notification.title || '🎟️ Nuevo Ticket';
            var body = notification.body || 'Has recibido un nuevo ticket';
            
            // Mostrar notificación flotante en la página
            this.showInPageNotification(title, body, data);
            
            // La notificación del navegador ya la muestra Firebase automáticamente
        },
        
        showInPageNotification: function(title, body, data) {
            // Crear contenedor si no existe
            var container = $('#cv-firebase-notifications');
            if (!container.length) {
                $('body').append('<div id="cv-firebase-notifications" style="position:fixed;top:80px;right:20px;z-index:999999;max-width:400px;"></div>');
                container = $('#cv-firebase-notifications');
            }
            
            var notifHtml = $('<div class="cv-firebase-notif" style="' +
                'background: linear-gradient(135deg, #FF6B6B 0%, #FF8E53 100%);' +
                'color: white;' +
                'padding: 20px;' +
                'border-radius: 12px;' +
                'margin-bottom: 15px;' +
                'box-shadow: 0 10px 30px rgba(0,0,0,0.3);' +
                'animation: slideInRight 0.4s ease-out;' +
                'cursor: pointer;' +
            '">' +
                '<div style="display: flex; align-items: start; gap: 15px;">' +
                    '<div style="font-size: 40px;">🔥</div>' +
                    '<div style="flex: 1;">' +
                        '<h4 style="margin: 0 0 8px 0; font-size: 16px; font-weight: 700;">' + title + '</h4>' +
                        '<p style="margin: 0; font-size: 14px; opacity: 0.95;">' + body + '</p>' +
                    '</div>' +
                    '<button class="cv-notif-close" style="background: transparent; border: none; color: white; font-size: 24px; cursor: pointer; opacity: 0.7; padding: 0; width: 30px; height: 30px;">×</button>' +
                '</div>' +
                '<div style="margin-top: 15px; display: flex; gap: 10px;">' +
                    '<button class="cv-notif-view" style="flex: 1; background: white; color: #FF6B6B; border: none; padding: 10px; border-radius: 6px; font-weight: 600; cursor: pointer;">Ver Ticket</button>' +
                '</div>' +
            '</div>');
            
            container.append(notifHtml);
            
            // Eventos
            notifHtml.find('.cv-notif-view').on('click', function() {
                window.location.href = '/store-manager/cv-tickets/';
            });
            
            notifHtml.find('.cv-notif-close').on('click', function(e) {
                e.stopPropagation();
                notifHtml.fadeOut(300, function() {
                    $(this).remove();
                });
            });
            
            // Auto-cerrar después de 60 segundos
            setTimeout(function() {
                notifHtml.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 60000);
        }
    };
    
    // Escuchar mensajes del Service Worker (cuando se hace click en notificación)
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', function(event) {
            console.log('📨 Firebase: Mensaje recibido del SW:', event.data);
            
            if (event.data && event.data.type === 'SHOW_TICKET_POPUP') {
                // Mostrar el popup flotante con los datos del ticket
                var data = event.data.data || {};
                var action = event.data.action;
                
                console.log('🔔 Firebase: Mostrando popup para ticket:', data);
                
                // Construir título y cuerpo de la notificación
                var title = '🎟️ Nuevo Ticket Recibido';
                var body = 'Ticket #' + (data.ticket_id || '?') + ' - ' + (data.amount || '0') + '€';
                
                // Mostrar el popup flotante existente
                FirebasePush.showInPageNotification(data, title, body);
            }
        });
    }
    
    // Inicializar cuando jQuery esté listo
    $(document).ready(function() {
        FirebasePush.init();
    });
    
})(jQuery);

// CSS para animación
var style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
`;
document.head.appendChild(style);
