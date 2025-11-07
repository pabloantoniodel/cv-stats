/**
 * Interceptor para el plugin QR Scanner Redirect
 * Este script se carga ANTES que el plugin para interceptar las redirecciones
 * 
 * @package CV_Commissions
 * @since 1.2.0
 */

(function() {
    'use strict';
    
    console.log('CVTicket Interceptor: Cargado');
    
    // Variable global para almacenar la URL escaneada
    window.cvTicketScannedURL = null;
    window.cvTicketShouldIntercept = false;
    
    // Método 1: Sobrescribir window.location.href ANTES de que se use
    var originalLocationDescriptor = Object.getOwnPropertyDescriptor(window, 'location');
    var originalLocation = window.location;
    
    // Guardar métodos originales
    var originalAssign = window.location.assign.bind(window.location);
    var originalReplace = window.location.replace.bind(window.location);
    
    // Interceptar location.assign()
    window.location.assign = function(url) {
        console.log('CVTicket Interceptor: location.assign() llamado con:', url);
        
        if (url && url.indexOf && url.indexOf('/store/') !== -1) {
            console.log('CVTicket Interceptor: URL de tienda detectada, bloqueando redirección');
            window.cvTicketScannedURL = url;
            window.cvTicketShouldIntercept = true;
            
            // Disparar evento personalizado
            var event = new CustomEvent('cvTicketStoreScanned', { detail: { url: url } });
            document.dispatchEvent(event);
            
            return; // Bloquear redirección
        }
        
        // Si no es una tienda, ejecutar normalmente
        originalAssign(url);
    };
    
    // Interceptar location.replace()
    window.location.replace = function(url) {
        console.log('CVTicket Interceptor: location.replace() llamado con:', url);
        
        if (url && url.indexOf && url.indexOf('/store/') !== -1) {
            console.log('CVTicket Interceptor: URL de tienda detectada, bloqueando redirección');
            window.cvTicketScannedURL = url;
            window.cvTicketShouldIntercept = true;
            
            // Disparar evento personalizado
            var event = new CustomEvent('cvTicketStoreScanned', { detail: { url: url } });
            document.dispatchEvent(event);
            
            return; // Bloquear redirección
        }
        
        // Si no es una tienda, ejecutar normalmente
        originalReplace(url);
    };
    
    // Método 2: Interceptar cuando se intenta cambiar window.location.href directamente
    // Esto es más complicado porque location.href es una propiedad del objeto location
    // Vamos a usar un Proxy si está disponible
    if (typeof Proxy !== 'undefined') {
        try {
            var locationProxy = new Proxy(originalLocation, {
                set: function(target, property, value) {
                    console.log('CVTicket Interceptor: Intentando modificar location.' + property + ' =', value);
                    
                    if (property === 'href' && value && value.indexOf && value.indexOf('/store/') !== -1) {
                        console.log('CVTicket Interceptor: URL de tienda detectada en href, bloqueando redirección');
                        window.cvTicketScannedURL = value;
                        window.cvTicketShouldIntercept = true;
                        
                        // Disparar evento personalizado
                        var event = new CustomEvent('cvTicketStoreScanned', { detail: { url: value } });
                        document.dispatchEvent(event);
                        
                        return true; // Bloquear redirección
                    }
                    
                    // Si no es una tienda, permitir el cambio
                    target[property] = value;
                    return true;
                },
                get: function(target, property) {
                    return target[property];
                }
            });
            
            // Intentar reemplazar window.location con el proxy
            // Nota: Esto puede no funcionar en todos los navegadores debido a restricciones de seguridad
            Object.defineProperty(window, 'location', {
                get: function() {
                    return locationProxy;
                },
                set: function(value) {
                    if (typeof value === 'string' && value.indexOf('/store/') !== -1) {
                        console.log('CVTicket Interceptor: URL de tienda detectada en asignación directa');
                        window.cvTicketScannedURL = value;
                        window.cvTicketShouldIntercept = true;
                        
                        var event = new CustomEvent('cvTicketStoreScanned', { detail: { url: value } });
                        document.dispatchEvent(event);
                        
                        return;
                    }
                    originalLocation.href = value;
                }
            });
        } catch (e) {
            console.log('CVTicket Interceptor: No se pudo crear Proxy para location:', e.message);
        }
    }
    
    // Método 3: Monitorizar beforeunload para detectar intentos de redirección
    window.addEventListener('beforeunload', function(e) {
        if (window.cvTicketShouldIntercept) {
            console.log('CVTicket Interceptor: beforeunload detectado, cancelando');
            e.preventDefault();
            e.returnValue = '';
            window.cvTicketShouldIntercept = false;
            return '';
        }
    }, true); // useCapture = true para que se ejecute primero
    
    console.log('CVTicket Interceptor: Interceptores instalados');
    
})();

