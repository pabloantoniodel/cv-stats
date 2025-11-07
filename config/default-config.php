<?php
/**
 * Configuración por defecto del plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

function cv_commissions_default_config() {
    return array(
        // IDs de usuarios especiales
        'programmer_user_id' => 3,
        'programmer_affiliate_id' => 2,
        'company_user_id' => 63,
        'company_affiliate_id' => 11,
        'company_name' => 'CIUDADVIRTUAL',
        'company_contact_name' => 'Francisco Sánchez',
        
        // Porcentajes de comisión
        'programmer_commission_percent' => 2.0,    // 2% de cada venta
        'buyer_commission_percent' => 10.0,        // 10% de la comisión
        'cashback_percent' => 10.0,                // 10% devuelto al monedero
        
        // Configuración de pirámide MLM
        'mlm_levels' => 10,                        // 10 niveles de profundidad
        'mlm_level_percent' => 10.0,               // Cada nivel recibe 10% del anterior
        'mlm_auto_registration_enabled' => true,   // Auto-registrar compradores bajo vendedores
        
        // Producto especial (Ticket)
        'special_product_id' => 4379,
        'special_product_commission' => 90.0,      // 90% al vendedor
        
        // Firebase
        'firebase_enabled' => true,
        'firebase_api_key' => 'AAAA6niQtZQ:APA91bHqRNg7fmnfpnSv5O3WSg-W7YCh4GZhIVUCS7b8C2bsZ4KTfTH87QCuR2BkTuf87pU15ZKEhNyyA4gd382IKRk499p-kBtDzRE2CtxZoL7gOPoEXIPdd3wMEiQHOwXfwmLk18km',
        'firebase_token_url' => 'https://us-central1-ciudadvitual.cloudfunctions.net/getToken',
        
        // Configuración general
        'currency' => 'EUR',
        'enable_logging' => true,
        'debug_mode' => false,
        
        // Integración Ultramsg (WhatsApp)
        'ultramsg_token' => '',
        'ultramsg_instance' => '',
        'ultramsg_secondary_phone' => '',
        'ultramsg_secondary_prefix' => '📋 Copia de mensaje:',
        'ultramsg_notify_enquiry' => true,
        'ultramsg_notify_commission' => false,
        
        // Productos destacados/anuncios (Snippet 33)
        'featured_products_enabled' => false, // Desactivado por defecto
    );
}

