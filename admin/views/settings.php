<?php
/**
 * Vista de configuración
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>⚙️ Ciudad Virtual - Configuración de Comisiones MLM</h1>
    
    <!-- Estado de Dependencias -->
    <div class="card">
        <h2>📦 Estado de Dependencias</h2>
        
        <h3>Plugins Requeridos</h3>
        <table class="widefat">
            <thead>
                <tr>
                    <th>Plugin</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($status['required'] as $plugin => $is_active): ?>
                <tr>
                    <td><?php echo esc_html($plugin); ?></td>
                    <td>
                        <?php if ($is_active): ?>
                            <span style="color: green;">✅ Activo</span>
                        <?php else: ?>
                            <span style="color: red;">❌ No activo</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <h3>Plugins Opcionales</h3>
        <table class="widefat">
            <thead>
                <tr>
                    <th>Plugin</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($status['optional'] as $plugin => $is_active): ?>
                <tr>
                    <td><?php echo esc_html($plugin); ?></td>
                    <td>
                        <?php if ($is_active): ?>
                            <span style="color: green;">✅ Activo</span>
                        <?php else: ?>
                            <span style="color: orange;">⚠️ No activo (opcional)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <h3>Clases Globales</h3>
        <?php if ($status['global_classes']['ok']): ?>
            <p style="color: green;">✅ Todas las clases globales están disponibles</p>
        <?php else: ?>
            <p style="color: red;">❌ Algunas clases no están disponibles:</p>
            <ul>
                <?php foreach ($status['global_classes']['missing'] as $missing): ?>
                    <li><?php echo esc_html($missing); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    
    <!-- Formulario de Configuración -->
    <form method="post" action="">
        <?php wp_nonce_field('cv_commissions_settings'); ?>
        
        <!-- IDs de Usuarios Especiales -->
        <div class="card" style="margin-top: 20px;">
            <h2>👥 IDs de Usuarios Especiales</h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="programmer_user_id">User ID Programador</label></th>
                    <td>
                        <input type="number" name="programmer_user_id" id="programmer_user_id" 
                               value="<?php echo esc_attr($this->config['programmer_user_id']); ?>" class="regular-text">
                        <p class="description">ID de usuario de WordPress del programador</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="programmer_affiliate_id">Affiliate ID Programador</label></th>
                    <td>
                        <input type="number" name="programmer_affiliate_id" id="programmer_affiliate_id" 
                               value="<?php echo esc_attr($this->config['programmer_affiliate_id']); ?>" class="regular-text">
                        <p class="description">ID de afiliado del programador en Indeed Affiliate Pro</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="company_user_id">User ID Empresa</label></th>
                    <td>
                        <input type="number" name="company_user_id" id="company_user_id" 
                               value="<?php echo esc_attr($this->config['company_user_id']); ?>" class="regular-text">
                        <p class="description">ID de usuario de WordPress de la empresa</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="company_affiliate_id">Affiliate ID Empresa</label></th>
                    <td>
                        <input type="number" name="company_affiliate_id" id="company_affiliate_id" 
                               value="<?php echo esc_attr($this->config['company_affiliate_id']); ?>" class="regular-text">
                        <p class="description">ID de afiliado de la empresa en Indeed Affiliate Pro</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="company_name">Nombre de la Empresa</label></th>
                    <td>
                        <input type="text" name="company_name" id="company_name" 
                               value="<?php echo esc_attr($this->config['company_name']); ?>" class="regular-text">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="company_contact_name">Nombre Contacto Empresa</label></th>
                    <td>
                        <input type="text" name="company_contact_name" id="company_contact_name" 
                               value="<?php echo esc_attr($this->config['company_contact_name']); ?>" class="regular-text">
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Porcentajes de Comisión -->
        <div class="card" style="margin-top: 20px;">
            <h2>💰 Porcentajes de Comisión</h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="programmer_commission_percent">Comisión Programador (%)</label></th>
                    <td>
                        <input type="number" step="0.01" name="programmer_commission_percent" id="programmer_commission_percent" 
                               value="<?php echo esc_attr($this->config['programmer_commission_percent']); ?>" class="small-text">
                        <span>%</span>
                        <p class="description">Porcentaje de cada venta que va al programador (ej: 2 = 2%)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="buyer_commission_percent">Comisión Comprador (%)</label></th>
                    <td>
                        <input type="number" step="0.01" name="buyer_commission_percent" id="buyer_commission_percent" 
                               value="<?php echo esc_attr($this->config['buyer_commission_percent']); ?>" class="small-text">
                        <span>%</span>
                        <p class="description">Porcentaje de la comisión del marketplace que va al comprador</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="cashback_percent">Cashback Monedero (%)</label></th>
                    <td>
                        <input type="number" step="0.01" name="cashback_percent" id="cashback_percent" 
                               value="<?php echo esc_attr($this->config['cashback_percent']); ?>" class="small-text">
                        <span>%</span>
                        <p class="description">Porcentaje de la comisión que se devuelve al monedero</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Configuración MLM -->
        <div class="card" style="margin-top: 20px;">
            <h2>🔺 Configuración de Pirámide MLM</h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="mlm_levels">Niveles de Pirámide</label></th>
                    <td>
                        <input type="number" name="mlm_levels" id="mlm_levels" 
                               value="<?php echo esc_attr($this->config['mlm_levels']); ?>" class="small-text">
                        <p class="description">Número de niveles en la pirámide MLM (por defecto: 10)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="mlm_level_percent">Porcentaje por Nivel (%)</label></th>
                    <td>
                        <input type="number" step="0.01" name="mlm_level_percent" id="mlm_level_percent" 
                               value="<?php echo esc_attr($this->config['mlm_level_percent']); ?>" class="small-text">
                        <span>%</span>
                        <p class="description">Porcentaje que recibe cada nivel del nivel anterior (ej: 10 = 10%)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="mlm_auto_registration_enabled">Auto-registro MLM</label></th>
                    <td>
                        <input type="checkbox" name="mlm_auto_registration_enabled" id="mlm_auto_registration_enabled" 
                               <?php checked($this->config['mlm_auto_registration_enabled'], true); ?>>
                        <label for="mlm_auto_registration_enabled">Conectar automáticamente compradores con vendedores en la pirámide MLM</label>
                        <p class="description">
                            Cuando un usuario compra, si NO tiene padre MLM, se asigna automáticamente debajo del vendedor.<br>
                            <strong>Nota:</strong> Solo afecta a compradores que sean afiliados y no tengan padre MLM.
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Producto Especial -->
        <div class="card" style="margin-top: 20px;">
            <h2>🎫 Producto Especial (Ticket)</h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="special_product_id">Product ID del Ticket</label></th>
                    <td>
                        <input type="number" name="special_product_id" id="special_product_id" 
                               value="<?php echo esc_attr($this->config['special_product_id']); ?>" class="regular-text">
                        <p class="description">ID del producto que tiene comisión especial (tickets)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="special_product_commission">Comisión Especial (%)</label></th>
                    <td>
                        <input type="number" step="0.01" name="special_product_commission" id="special_product_commission" 
                               value="<?php echo esc_attr($this->config['special_product_commission']); ?>" class="small-text">
                        <span>%</span>
                        <p class="description">Porcentaje de comisión para el producto especial (ej: 90 = 90%)</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Firebase -->
        <div class="card" style="margin-top: 20px;">
            <h2>🔔 Configuración Firebase</h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="firebase_enabled">Habilitar Firebase</label></th>
                    <td>
                        <input type="checkbox" name="firebase_enabled" id="firebase_enabled" 
                               <?php checked($this->config['firebase_enabled'], true); ?>>
                        <label for="firebase_enabled">Enviar notificaciones push via Firebase</label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="firebase_api_key">API Key de Firebase</label></th>
                    <td>
                        <input type="text" name="firebase_api_key" id="firebase_api_key" 
                               value="<?php echo esc_attr($this->config['firebase_api_key']); ?>" class="large-text">
                        <p class="description">Server Key de Firebase Cloud Messaging</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="firebase_token_url">URL de Cloud Function</label></th>
                    <td>
                        <input type="url" name="firebase_token_url" id="firebase_token_url" 
                               value="<?php echo esc_attr($this->config['firebase_token_url']); ?>" class="large-text">
                        <p class="description">URL de la función de Firebase para obtener tokens</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Productos Destacados/Anuncios -->
        <div class="card" style="margin-top: 20px;">
            <h2>⭐ Productos Destacados/Anuncios</h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="featured_products_enabled">Habilitar Productos Destacados</label></th>
                    <td>
                        <input type="checkbox" name="featured_products_enabled" id="featured_products_enabled" 
                               <?php checked($this->config['featured_products_enabled'] ?? false, true); ?>>
                        <label for="featured_products_enabled">Mostrar productos de anuncios "De tu interés" y "Por Anunciante" en el shop</label>
                        <p class="description">
                            ⚠️ <strong>Actualmente desactivado por defecto</strong><br>
                            Esta funcionalidad muestra productos personalizados basados en anuncios enviados al usuario.<br>
                            • <strong>"De tu interés"</strong>: Productos de la tabla cvapp_anuncios para usuarios logueados<br>
                            • <strong>"Por Anunciante"</strong>: Productos filtrados por anunciante cuando hay búsqueda activa<br>
                            <em>Migrado desde Snippet 33</em>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Configuración General -->
        <div class="card" style="margin-top: 20px;">
            <h2>⚙️ Configuración General</h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="currency">Moneda</label></th>
                    <td>
                        <input type="text" name="currency" id="currency" 
                               value="<?php echo esc_attr($this->config['currency']); ?>" class="small-text">
                        <p class="description">Código de moneda (ej: EUR, USD)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="enable_logging">Habilitar Logging</label></th>
                    <td>
                        <input type="checkbox" name="enable_logging" id="enable_logging" 
                               <?php checked($this->config['enable_logging'], true); ?>>
                        <label for="enable_logging">Registrar eventos en error_log para debugging</label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="debug_mode">Modo Debug</label></th>
                    <td>
                        <input type="checkbox" name="debug_mode" id="debug_mode" 
                               <?php checked($this->config['debug_mode'], true); ?>>
                        <label for="debug_mode">Modo debug avanzado (más información en logs)</label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="cv_ticket_debug_mode">Debug de Tickets (Móvil)</label></th>
                    <td>
                        <input type="checkbox" name="cv_ticket_debug_mode" id="cv_ticket_debug_mode" 
                               <?php checked(get_option('cv_ticket_debug_mode', false), true); ?>>
                        <label for="cv_ticket_debug_mode">Mostrar logs de debug en consola móvil para tickets y notificaciones</label>
                        <p class="description">Actívalo solo para desarrollo. Muestra mensajes de consola en el móvil.</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Integración Ultramsg (WhatsApp) -->
        <div class="card" style="margin-top: 20px;">
            <h2>📱 Integración Ultramsg (WhatsApp)</h2>
            <p class="description" style="margin-bottom: 15px;">
                Configura la integración con Ultramsg para enviar notificaciones automáticas por WhatsApp a los vendedores.
                <br><strong>Nota:</strong> Necesitas una cuenta activa en <a href="https://ultramsg.com" target="_blank">Ultramsg</a>.
            </p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="ultramsg_token">Token de Ultramsg</label></th>
                    <td>
                        <input type="text" name="ultramsg_token" id="ultramsg_token" 
                               value="<?php echo esc_attr($this->config['ultramsg_token'] ?? ''); ?>" 
                               class="regular-text" placeholder="que2toe66utdasoy">
                        <p class="description">
                            Token de autenticación de tu instancia de Ultramsg
                            <br>Obtén tu token en: <a href="https://ultramsg.com/dashboard" target="_blank">Dashboard de Ultramsg</a>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="ultramsg_instance">ID de Instancia</label></th>
                    <td>
                        <input type="text" name="ultramsg_instance" id="ultramsg_instance" 
                               value="<?php echo esc_attr($this->config['ultramsg_instance'] ?? ''); ?>" 
                               class="regular-text" placeholder="instance71598">
                        <p class="description">
                            ID de tu instancia de Ultramsg (ejemplo: instance71598)
                            <br>Lo encuentras en la URL de tu dashboard: https://ultramsg.com/<strong>instance71598</strong>/
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="ultramsg_secondary_phone">Teléfono Secundario (Copia)</label></th>
                    <td>
                        <input type="text" name="ultramsg_secondary_phone" id="ultramsg_secondary_phone" 
                               value="<?php echo esc_attr($this->config['ultramsg_secondary_phone'] ?? ''); ?>" 
                               class="regular-text" placeholder="+34600000000">
                        <p class="description">
                            Teléfono que recibirá una copia de TODOS los mensajes enviados (formato internacional con +)
                            <br>Ejemplo: +34600000000 o +1234567890
                            <br><strong>Dejar vacío para no enviar copias</strong>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="ultramsg_secondary_prefix">Prefijo Mensaje Secundario</label></th>
                    <td>
                        <input type="text" name="ultramsg_secondary_prefix" id="ultramsg_secondary_prefix" 
                               value="<?php echo esc_attr($this->config['ultramsg_secondary_prefix'] ?? '📋 Copia de mensaje:'); ?>" 
                               class="regular-text" placeholder="📋 Copia de mensaje:">
                        <p class="description">
                            Texto que aparecerá al inicio de los mensajes enviados al teléfono secundario
                            <br>Esto ayuda a identificar que es una copia de un mensaje enviado a otro destinatario
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="ultramsg_notify_enquiry">Notificar Consultas</label></th>
                    <td>
                        <input type="checkbox" name="ultramsg_notify_enquiry" id="ultramsg_notify_enquiry" 
                               <?php checked($this->config['ultramsg_notify_enquiry'] ?? true, true); ?>>
                        <label for="ultramsg_notify_enquiry">Enviar notificación por WhatsApp cuando haya nueva consulta a un vendedor</label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="ultramsg_notify_commission">Notificar Comisiones</label></th>
                    <td>
                        <input type="checkbox" name="ultramsg_notify_commission" id="ultramsg_notify_commission" 
                               <?php checked($this->config['ultramsg_notify_commission'] ?? false, true); ?>>
                        <label for="ultramsg_notify_commission">Enviar notificación cuando se apruebe una comisión</label>
                        <p class="description">⚠️ Funcionalidad en desarrollo</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Estado de la Integración</th>
                    <td>
                        <?php 
                        $ultramsg_configured = !empty($this->config['ultramsg_token']) && !empty($this->config['ultramsg_instance']);
                        $secondary_configured = !empty($this->config['ultramsg_secondary_phone']);
                        ?>
                        <?php if ($ultramsg_configured): ?>
                            <span style="color: green; font-weight: bold;">✅ Configurado y activo</span>
                            <p class="description">
                                API URL: <code>https://api.ultramsg.com/<?php echo esc_html($this->config['ultramsg_instance']); ?>/messages/chat</code>
                            </p>
                            <?php if ($secondary_configured): ?>
                                <p class="description" style="margin-top: 10px;">
                                    <strong>📱 Teléfono secundario:</strong> <code><?php echo esc_html($this->config['ultramsg_secondary_phone']); ?></code>
                                    <br><strong>Prefijo:</strong> "<?php echo esc_html($this->config['ultramsg_secondary_prefix'] ?? '📋 Copia de mensaje:'); ?>"
                                    <br><em>✅ Todos los mensajes se enviarán también a este número</em>
                                </p>
                            <?php else: ?>
                                <p class="description" style="margin-top: 10px; color: #666;">
                                    ℹ️ <em>No hay teléfono secundario configurado</em>
                                </p>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: orange; font-weight: bold;">⚠️ No configurado</span>
                            <p class="description">Completa el token y la instancia para activar las notificaciones de WhatsApp</p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
        
        <p class="submit">
            <input type="submit" name="cv_commissions_save" class="button button-primary" value="💾 Guardar Configuración">
        </p>
    </form>
    
    <!-- Herramientas -->
    <div class="card" style="margin-top: 20px;">
        <h2>🛠️ Herramientas de Mantenimiento</h2>
        
        <div style="background: #d1ecf1; border-left: 4px solid #17a2b8; padding: 15px; margin-bottom: 20px;">
            <strong>ℹ️ Herramientas avanzadas</strong><br>
            Estas herramientas te permiten mantener y verificar las comisiones del sistema.
        </div>
        
        <h3>🔄 Recalcular Todas las Comisiones</h3>
        <p>Recalcula todas las comisiones existentes en <code>uap_referrals</code> usando la configuración actual del plugin.</p>
        
        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0;">
            <strong>⚠️ Advertencia:</strong> Esta operación modifica datos en la base de datos. 
            Se recomienda hacer un <strong>backup</strong> antes de ejecutarla en modo REAL.
        </div>
        
        <h4>📋 ¿Qué hace?</h4>
        <ul>
            <li>✅ Lee todos los registros de comisiones con <code>order_id</code></li>
            <li>✅ Recalcula usando <code>CV_Commission_Calculator</code></li>
            <li>✅ Detecta automáticamente: Programador, Comprador, Empresa, MLM (todos los niveles)</li>
            <li>✅ Compara monto actual vs recalculado</li>
            <li>✅ Actualiza si hay diferencia > €0.01</li>
            <li>✅ Guarda log completo en JSON</li>
        </ul>
        
        <h4>🎯 Casos de uso:</h4>
        <ul>
            <li>Después de cambiar los porcentajes de comisión arriba</li>
            <li>Si detectas inconsistencias en comisiones antiguas</li>
            <li>Después de corregir bugs en el cálculo</li>
            <li>Para migrar de sistema antiguo a nuevo</li>
        </ul>
        
        <div style="margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
            <h4 style="margin-top: 0;">Opciones de Ejecución:</h4>
            
            <p>
                <a href="<?php echo CV_COMMISSIONS_PLUGIN_URL; ?>tools/recalculate-commissions.php?dry_run=1&limit=10" 
                   class="button button-secondary" target="_blank" style="margin-right: 10px;">
                    🧪 Simular 10 Registros
                </a>
                <small style="color: #666;">Prueba rápida para ver cómo funciona (2-5 segundos)</small>
            </p>
            
            <p>
                <a href="<?php echo CV_COMMISSIONS_PLUGIN_URL; ?>tools/recalculate-commissions.php?dry_run=1" 
                   class="button button-secondary" target="_blank" style="margin-right: 10px;">
                    🔍 Simular Todos los Registros
                </a>
                <small style="color: #666;">Ver qué cambiaría SIN guardar en BD (30-60 segundos)</small>
            </p>
            
            <p>
                <a href="<?php echo CV_COMMISSIONS_PLUGIN_URL; ?>tools/recalculate-commissions.php" 
                   class="button button-primary" target="_blank" style="margin-right: 10px;"
                   onclick="return confirm('⚠️ ATENCIÓN:\n\nEsto actualizará TODAS las comisiones en la base de datos usando la configuración actual.\n\n¿Has hecho un backup?\n¿Estás seguro de continuar?');">
                    🚀 Ejecutar REAL (Actualizar Base de Datos)
                </a>
                <small style="color: #dc3545;"><strong>⚠️ Modifica la BD - Requiere backup</strong></small>
            </p>
        </div>
        
        <h4>📊 Información Mostrada:</h4>
        <ul>
            <li><strong>Tabla comparativa</strong>: ID, Pedido, Descripción, Actual, Recalculado, Diferencia, Estado</li>
            <li><strong>Barra de progreso</strong>: Actualización en tiempo real</li>
            <li><strong>Estadísticas</strong>: Total procesados, modificados, sin cambios, errores</li>
            <li><strong>Resumen financiero</strong>: Total anterior, nuevo, diferencia (con colores)</li>
            <li><strong>Log JSON</strong>: Guardado en <code>/logs/recalculation-YYYY-MM-DD-HH-II-SS.json</code></li>
        </ul>
        
        <h4>🎨 Códigos de Color:</h4>
        <ul>
            <li>🟡 <strong>Fondo amarillo</strong> = Registro que cambió</li>
            <li>🟢 <strong>Verde +€XX.XX</strong> = Aumentó la comisión</li>
            <li>🔴 <strong>Rojo -€XX.XX</strong> = Disminuyó la comisión</li>
            <li>⚫ <strong>Gris</strong> = Sin cambios</li>
        </ul>
    </div>
    
    <!-- Información del Sistema -->
    <div class="card" style="margin-top: 20px;">
        <h2>ℹ️ Información del Sistema</h2>
        <table class="widefat">
            <tr>
                <td><strong>Versión del Plugin:</strong></td>
                <td><?php echo CV_COMMISSIONS_VERSION; ?></td>
            </tr>
            <tr>
                <td><strong>WordPress:</strong></td>
                <td><?php echo get_bloginfo('version'); ?></td>
            </tr>
            <tr>
                <td><strong>PHP:</strong></td>
                <td><?php echo phpversion(); ?></td>
            </tr>
        </table>
    </div>
    
    <!-- Documentación -->
    <div class="card" style="margin-top: 20px;">
        <h2>📚 Documentación</h2>
        <p>
            Este plugin convierte el sistema de comisiones MLM del Snippet 24 en un plugin independiente y configurable.
        </p>
        <h3>Características:</h3>
        <ul>
            <li>✅ Sistema de comisiones multinivel (MLM) con pirámide de 10 niveles</li>
            <li>✅ Distribución automática entre programador, comprador, empresa y afiliados</li>
            <li>✅ Notificaciones push via Firebase</li>
            <li>✅ Integración con WooCommerce, WCFM y Ultimate Affiliate Pro</li>
            <li>✅ Configuración completamente personalizable</li>
            <li>✅ Sistema de logging para debugging</li>
        </ul>
        <h3>Hooks Utilizados:</h3>
        <ul>
            <li><code>wcfmmp_order_processed</code> - Procesar comisiones cuando se completa un pedido</li>
            <li><code>woo_wallet_form_cart_cashback_amount</code> - Calcular cashback del carrito</li>
        </ul>
    </div>
</div>

<style>
    .card {
        background: #fff;
        border: 1px solid #ccd0d4;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        padding: 20px;
    }
    
    .card h2 {
        margin-top: 0;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }
    
    .widefat th,
    .widefat td {
        padding: 8px 10px;
    }
</style>

