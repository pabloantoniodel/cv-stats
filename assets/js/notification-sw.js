/**
 * Service Worker para manejar notificaciones push
 */

console.log('Service Worker de notificaciones cargado');

// Activación inmediata
self.addEventListener('install', function(event) {
    console.log('Service Worker: Instalando...');
    self.skipWaiting();
});

self.addEventListener('activate', function(event) {
    console.log('Service Worker: Activando...');
    event.waitUntil(self.clients.claim());
});

// Escuchar mensaje SKIP_WAITING
self.addEventListener('message', function(event) {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        console.log('Service Worker: Recibido SKIP_WAITING');
        self.skipWaiting();
    }
});

// Escuchar eventos de notificationclick
self.addEventListener('notificationclick', function(event) {
    console.log('Notification click recibido:', event);
    
    event.notification.close();
    
    const action = event.action;
    const data = event.notification.data;
    
    console.log('Acción:', action);
    console.log('Data:', data);
    
    if (action === 'validate') {
        // Validar el ticket directamente
        console.log('Validando ticket:', data.ticket_id);
        
        event.waitUntil(
            clients.openWindow('/store-manager/cv-tickets/?action=validate&ticket_id=' + data.ticket_id)
        );
        
    } else if (action === 'view') {
        // Ver detalles del ticket
        console.log('Viendo ticket:', data.ticket_id);
        
        event.waitUntil(
            clients.openWindow('/store-manager/cv-tickets/?ticket_id=' + data.ticket_id)
        );
        
    } else {
        // Click en la notificación (no en botones)
        event.waitUntil(
            clients.openWindow('/store-manager/cv-tickets/')
        );
    }
});

// Escuchar evento de push (para futuras notificaciones en tiempo real)
self.addEventListener('push', function(event) {
    console.log('Push recibido:', event);
    
    if (event.data) {
        const data = event.data.json();
        
        const options = {
            body: data.body,
            icon: data.icon || '/wp-content/plugins/cv-commissions/assets/images/ticket-icon.png',
            badge: data.badge || '/wp-content/plugins/cv-commissions/assets/images/badge-icon.png',
            tag: data.tag || 'ticket-notification',
            requireInteraction: true,
            vibrate: [200, 100, 200],
            actions: [
                {
                    action: 'validate',
                    title: '✅ Validar'
                },
                {
                    action: 'view',
                    title: '👁️ Ver'
                }
            ],
            data: data.data || {}
        };
        
        event.waitUntil(
            self.registration.showNotification(data.title, options)
        );
    }
});

