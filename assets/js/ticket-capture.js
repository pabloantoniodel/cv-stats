/**
 * JavaScript para el nuevo flujo de captura de tickets
 * @package CV_Commissions
 * @since 1.2.0
 */

(function($) {
    'use strict';
    
    var TicketCapture = {
        vendorId: null,
        vendorName: '',
        photoBlob: null,
        photoId: null,
        stream: null,
        
        /**
         * Inicializar
         */
        init: function() {
            this.bindEvents();
            this.interceptQRScan();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;
            
            // Checkbox de permisos
            $('#cv-permissions-checkbox').on('change', function() {
                $('#cv-permissions-next').prop('disabled', !$(this).is(':checked'));
            });
            
            // Botón siguiente en modal de permisos
            $('#cv-permissions-next').on('click', function() {
                self.startCameraCapture();
            });
            
            // Botón cancelar en modal de permisos
            $('#cv-permissions-cancel').on('click', function() {
                self.cancelProcess();
            });
            
            // Capturar foto
            $('#cv-capture-photo').on('click', function() {
                self.capturePhoto();
            });
            
            // Repetir foto
            $('#cv-retake-photo').on('click', function() {
                self.retakePhoto();
            });
            
            // Confirmar foto
            $('#cv-confirm-photo').on('click', function() {
                self.uploadPhoto();
            });
            
            // Cancelar captura
            $('#cv-cancel-capture').on('click', function() {
                self.cancelProcess();
            });
            
            // Enviar ticket
            $('#cv-submit-ticket').on('click', function() {
                self.submitTicket();
            });
            
            // Cancelar formulario
            $('#cv-cancel-form').on('click', function() {
                self.cancelProcess();
            });
        },
        
        /**
         * Interceptar el escaneo de QR
         */
        interceptQRScan: function() {
            var self = this;
            
            // Método 1: Interceptar window.location antes de que se ejecute
            var originalLocation = window.location;
            var originalAssign = window.location.assign;
            var originalReplace = window.location.replace;
            var originalHref = Object.getOwnPropertyDescriptor(window.location, 'href');
            
            // Interceptar location.href =
            Object.defineProperty(window.location, 'href', {
                set: function(url) {
                    console.log('CVTicket: Interceptando location.href =', url);
                    if (url.indexOf('/store/') !== -1) {
                        self.handleStoreQRScan(url);
                        return; // Bloquear redirección
                    }
                    if (originalHref && originalHref.set) {
                        originalHref.set.call(this, url);
                    }
                },
                get: function() {
                    if (originalHref && originalHref.get) {
                        return originalHref.get.call(this);
                    }
                    return window.location.toString();
                }
            });
            
            // Interceptar location.assign()
            window.location.assign = function(url) {
                console.log('CVTicket: Interceptando location.assign()', url);
                if (url.indexOf('/store/') !== -1) {
                    self.handleStoreQRScan(url);
                    return;
                }
                originalAssign.call(originalLocation, url);
            };
            
            // Interceptar location.replace()
            window.location.replace = function(url) {
                console.log('CVTicket: Interceptando location.replace()', url);
                if (url.indexOf('/store/') !== -1) {
                    self.handleStoreQRScan(url);
                    return;
                }
                originalReplace.call(originalLocation, url);
            };
            
            // Método 2: Interceptar el plugin QR Scanner directamente
            // Esperar a que el plugin se cargue
            setTimeout(function() {
                // Buscar el objeto del plugin
                if (window.qrscannerredirect && window.qrscannerredirect.settings) {
                    console.log('CVTicket: Plugin QR Scanner encontrado');
                    
                    // Sobrescribir la función de redirección
                    var originalOnScan = window.qrscannerredirect.onScan || function() {};
                    
                    window.qrscannerredirect.onScan = function(url) {
                        console.log('CVTicket: QR escaneado via plugin:', url);
                        
                        if (url.indexOf('/store/') !== -1) {
                            self.handleStoreQRScan(url);
                            return false; // Bloquear redirección
                        }
                        
                        // Si no es un comercio, ejecutar la función original
                        return originalOnScan.call(this, url);
                    };
                }
            }, 1000);
            
            // Método 3: MutationObserver como respaldo
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList') {
                        // Buscar cambios que indiquen un escaneo exitoso
                        var qrContainer = document.getElementById('qrscannerredirect');
                        if (qrContainer) {
                            // Buscar texto que contenga URL de tienda
                            var textContent = qrContainer.textContent || qrContainer.innerText;
                            if (textContent.indexOf('/store/') !== -1) {
                                // Extraer la URL
                                var match = textContent.match(/(https?:\/\/[^\s]+\/store\/[^\s/]+)/);
                                if (match && match[1]) {
                                    console.log('CVTicket: URL detectada via MutationObserver:', match[1]);
                                    self.handleStoreQRScan(match[1]);
                                }
                            }
                        }
                    }
                });
            });
            
            // Observar cambios en el contenedor del QR scanner
            var targetNode = document.getElementById('qrscannerredirect');
            if (targetNode) {
                observer.observe(targetNode, { childList: true, subtree: true, characterData: true });
            }
        },
        
        /**
         * Manejar escaneo de QR de tienda
         */
        handleStoreQRScan: function(url) {
            var self = this;
            
            console.log('CVTicket: Procesando QR de tienda:', url);
            
            // Ocultar el scanner
            $('#qrscannerredirect').hide();
            
            // Guardar el vendor ID vía AJAX
            $.ajax({
                url: cvTicketCapture.ajax_url,
                type: 'POST',
                data: {
                    action: 'cv_save_scanned_vendor',
                    nonce: cvTicketCapture.nonce,
                    vendor_url: url
                },
                success: function(response) {
                    if (response.success) {
                        self.vendorId = response.data.vendor_id;
                        self.vendorName = response.data.vendor_name;
                        
                        console.log('CVTicket: Vendor guardado:', self.vendorId, self.vendorName);
                        
                        // Mostrar modal de permisos
                        self.showPermissionsModal();
                    } else {
                        alert(response.data.message || cvTicketCapture.strings.error_no_vendor);
                        self.resetProcess();
                    }
                },
                error: function() {
                    alert('Error de conexión. Inténtalo de nuevo.');
                    self.resetProcess();
                }
            });
        },
        
        /**
         * Mostrar modal de permisos de cámara
         */
        showPermissionsModal: function() {
            var strings = cvTicketCapture.strings;
            
            // Llenar contenido
            $('#cv-modal-title').text(strings.camera_title);
            
            var instructionsHtml = '<p>' + strings.camera_instruction + '</p><ol>';
            strings.camera_steps.forEach(function(step) {
                instructionsHtml += '<li>' + step + '</li>';
            });
            instructionsHtml += '</ol>';
            
            $('#cv-modal-body').html(instructionsHtml);
            $('#cv-permissions-ok-text').text(strings.permissions_ok);
            $('#cv-permissions-next').text(strings.btn_next);
            $('#cv-permissions-cancel').text(strings.btn_cancel);
            
            // Reset checkbox
            $('#cv-permissions-checkbox').prop('checked', false);
            $('#cv-permissions-next').prop('disabled', true);
            
            // Mostrar modal
            $('#cv-ticket-capture-wrapper').show();
            $('#cv-camera-permissions-modal').fadeIn();
        },
        
        /**
         * Iniciar captura con cámara
         */
        startCameraCapture: function() {
            var self = this;
            
            // Ocultar modal de permisos
            $('#cv-camera-permissions-modal').fadeOut(function() {
                // Mostrar interfaz de cámara
                $('#cv-camera-capture').fadeIn();
                $('#cv-capture-title').text(cvTicketCapture.strings.capture_title);
                $('#cv-capture-photo').text('📷 ' + 'Capturar');
                $('#cv-retake-photo').text(cvTicketCapture.strings.btn_retake);
                $('#cv-confirm-photo').text(cvTicketCapture.strings.btn_next);
                $('#cv-cancel-capture').text(cvTicketCapture.strings.btn_cancel);
                
                // Solicitar acceso a la cámara
                self.initCamera();
            });
        },
        
        /**
         * Inicializar cámara
         */
        initCamera: function() {
            var self = this;
            var video = document.getElementById('cv-camera-video');
            
            // Configuración para cámara trasera en móviles
            var constraints = {
                video: {
                    facingMode: { ideal: 'environment' },
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                }
            };
            
            navigator.mediaDevices.getUserMedia(constraints)
                .then(function(stream) {
                    self.stream = stream;
                    video.srcObject = stream;
                    video.play();
                })
                .catch(function(error) {
                    console.error('Error al acceder a la cámara:', error);
                    alert(cvTicketCapture.strings.error_camera);
                    self.cancelProcess();
                });
        },
        
        /**
         * Capturar foto
         */
        capturePhoto: function() {
            var video = document.getElementById('cv-camera-video');
            var canvas = document.getElementById('cv-camera-canvas');
            var image = document.getElementById('cv-captured-image');
            var context = canvas.getContext('2d');
            
            // Establecer dimensiones del canvas
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            
            // Dibujar el frame actual del video en el canvas
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            // Convertir a imagen
            var imageDataUrl = canvas.toDataURL('image/jpeg', 0.8);
            image.src = imageDataUrl;
            
            // Convertir a blob para subir
            canvas.toBlob(function(blob) {
                this.photoBlob = blob;
            }.bind(this), 'image/jpeg', 0.8);
            
            // Ocultar video, mostrar imagen capturada
            $(video).hide();
            $(image).show();
            
            // Cambiar botones
            $('#cv-capture-photo').hide();
            $('#cv-retake-photo').show();
            $('#cv-confirm-photo').show();
            
            // Detener stream
            if (this.stream) {
                this.stream.getTracks().forEach(track => track.stop());
            }
        },
        
        /**
         * Repetir foto
         */
        retakePhoto: function() {
            var video = document.getElementById('cv-camera-video');
            var image = document.getElementById('cv-captured-image');
            
            // Mostrar video, ocultar imagen
            $(image).hide();
            $(video).show();
            
            // Cambiar botones
            $('#cv-retake-photo').hide();
            $('#cv-confirm-photo').hide();
            $('#cv-capture-photo').show();
            
            // Reiniciar cámara
            this.initCamera();
        },
        
        /**
         * Subir foto al servidor
         */
        uploadPhoto: function() {
            var self = this;
            
            if (!this.photoBlob) {
                alert('Error: No hay foto capturada');
                return;
            }
            
            // Mostrar loading
            $('#cv-confirm-photo').prop('disabled', true).text('Subiendo...');
            
            var formData = new FormData();
            formData.append('action', 'cv_upload_ticket_photo');
            formData.append('nonce', cvTicketCapture.nonce);
            formData.append('ticket_photo', this.photoBlob, 'ticket.jpg');
            
            $.ajax({
                url: cvTicketCapture.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        self.photoId = response.data.photo_id;
                        
                        // Mostrar formulario de resumen
                        self.showTicketForm(response.data.photo_url);
                    } else {
                        alert(response.data.message || cvTicketCapture.strings.error_upload);
                        $('#cv-confirm-photo').prop('disabled', false).text(cvTicketCapture.strings.btn_next);
                    }
                },
                error: function() {
                    alert(cvTicketCapture.strings.error_upload);
                    $('#cv-confirm-photo').prop('disabled', false).text(cvTicketCapture.strings.btn_next);
                }
            });
        },
        
        /**
         * Mostrar formulario de ticket
         */
        showTicketForm: function(photoUrl) {
            var strings = cvTicketCapture.strings;
            
            // Ocultar captura de cámara
            $('#cv-camera-capture').fadeOut(function() {
                // Llenar formulario
                $('#cv-review-title').text(strings.review_title);
                $('#cv-store-label').text(strings.store_label);
                $('#cv-store-name').text(this.vendorName);
                $('#cv-ticket-preview').attr('src', photoUrl);
                $('#cv-amount-label').text(strings.amount_label);
                $('#cv-ticket-amount').attr('placeholder', strings.amount_placeholder);
                $('#cv-submit-ticket').text(strings.btn_send);
                $('#cv-cancel-form').text(strings.btn_cancel);
                
                // Mostrar formulario
                $('#cv-ticket-form').fadeIn();
            }.bind(this));
        },
        
        /**
         * Enviar ticket
         */
        submitTicket: function() {
            var self = this;
            var amount = parseFloat($('#cv-ticket-amount').val());
            
            if (isNaN(amount) || amount <= 0) {
                alert(cvTicketCapture.strings.error_amount);
                return;
            }
            
            // Mostrar loading
            $('#cv-submit-ticket').prop('disabled', true).text('Enviando...');
            
            $.ajax({
                url: cvTicketCapture.ajax_url,
                type: 'POST',
                data: {
                    action: 'cv_submit_ticket',
                    nonce: cvTicketCapture.nonce,
                    amount: amount
                },
                success: function(response) {
                    if (response.success) {
                        alert(cvTicketCapture.strings.success);
                        self.resetProcess();
                        
                        // Redirigir o mostrar mensaje de éxito
                        window.location.reload();
                    } else {
                        alert(response.data.message || 'Error al enviar el ticket');
                        $('#cv-submit-ticket').prop('disabled', false).text(cvTicketCapture.strings.btn_send);
                    }
                },
                error: function() {
                    alert('Error de conexión. Inténtalo de nuevo.');
                    $('#cv-submit-ticket').prop('disabled', false).text(cvTicketCapture.strings.btn_send);
                }
            });
        },
        
        /**
         * Cancelar proceso
         */
        cancelProcess: function() {
            if (confirm('¿Seguro que quieres cancelar? Se perderá todo el progreso.')) {
                this.resetProcess();
            }
        },
        
        /**
         * Resetear proceso
         */
        resetProcess: function() {
            // Detener stream si existe
            if (this.stream) {
                this.stream.getTracks().forEach(track => track.stop());
                this.stream = null;
            }
            
            // Reset variables
            this.vendorId = null;
            this.vendorName = '';
            this.photoBlob = null;
            this.photoId = null;
            
            // Ocultar todos los elementos
            $('#cv-ticket-capture-wrapper').hide();
            $('#cv-camera-permissions-modal').hide();
            $('#cv-camera-capture').hide();
            $('#cv-ticket-form').hide();
            
            // Mostrar scanner nuevamente
            $('#qrscannerredirect').show();
            
            // Reset video
            var video = document.getElementById('cv-camera-video');
            var image = document.getElementById('cv-captured-image');
            $(video).show();
            $(image).hide();
            $('#cv-capture-photo').show();
            $('#cv-retake-photo').hide();
            $('#cv-confirm-photo').hide();
            
            // Reset form
            $('#cv-ticket-amount').val('');
        }
    };
    
    // Exponer TicketCapture globalmente INMEDIATAMENTE
    window.TicketCapture = TicketCapture;
    
    // Inicializar cuando el DOM esté listo
    $(document).ready(function() {
        // Inicializar en /qr/ y /captura-tu-ticket/
        var isQRPage = $('body').hasClass('page-qr') || window.location.pathname.indexOf('/qr') !== -1;
        var isCapturePage = window.location.pathname.indexOf('/captura-tu-ticket') !== -1;
        
        if (isQRPage) {
            TicketCapture.init();
            
            // Escuchar el evento personalizado del interceptor
            document.addEventListener('cvTicketStoreScanned', function(e) {
                console.log('CVTicket: Evento cvTicketStoreScanned recibido:', e.detail.url);
                TicketCapture.handleStoreQRScan(e.detail.url);
            });
        }
        
        // En captura-tu-ticket solo necesitamos que el objeto esté disponible
        // La página PHP se encarga de llamar a los métodos directamente
    });
    
})(jQuery);

// Asegurar que TicketCapture esté disponible ANTES de que se cargue el DOM
console.log('✅ ticket-capture.js cargado, TicketCapture disponible:', typeof window.TicketCapture);

