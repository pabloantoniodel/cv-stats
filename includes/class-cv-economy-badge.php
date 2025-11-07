<?php
/**
 * Mostrar badge de aportación a la economía colaborativa en productos
 */

if (!defined('ABSPATH')) {
    exit;
}

class CV_Economy_Badge {
    
    public function __construct() {
        // Mostrar badge en página de producto
        add_action('woocommerce_single_product_summary', array($this, 'show_economy_badge'), 25);
    }
    
    /**
     * Mostrar badge de economía colaborativa
     */
    public function show_economy_badge() {
        global $product;
        
        if (!$product) {
            return;
        }
        
        $product_id = $product->get_id();
        $vendor_id = wcfm_get_vendor_id_by_post($product_id);
        
        if (!$vendor_id) {
            return;
        }
        
        // Obtener configuración de comisión del vendedor
        $vendor_data = get_user_meta($vendor_id, 'wcfmmp_profile_settings', true);
        
        if (!isset($vendor_data["commission"]["commission_percent"])) {
            return;
        }
        
        $commission_percent = floatval($vendor_data["commission"]["commission_percent"]);
        $economy_percent = 100 - $commission_percent;
        
        // HTML del badge con diseño moderno
        echo '<div class="cv-economy-badge" style="
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin: 25px 0;
            text-align: center;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
            animation: fadeInScale 0.6s ease-out;
        ">';
        
        echo '<div style="font-size: 14px; opacity: 0.9; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px;">
            🌱 Economía Colaborativa Circular
        </div>';
        
        echo '<div style="font-size: 32px; font-weight: 800; margin: 10px 0;">
            ' . number_format($economy_percent, 1) . '%
        </div>';
        
        echo '<div style="font-size: 14px; line-height: 1.5; opacity: 0.95;">
            Este establecimiento aporta a la economía colaborativa circular el <strong>' . number_format($economy_percent, 1) . '%</strong> de todas las compras
        </div>';
        
        echo '</div>';
        
        // Añadir CSS de animación
        echo '<style>
        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .cv-economy-badge:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
            transition: all 0.3s ease;
        }
        </style>';
    }
}

