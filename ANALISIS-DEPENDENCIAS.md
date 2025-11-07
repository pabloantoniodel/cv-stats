# 📋 Análisis de Dependencias - Sistema de Comisiones MLM

## 🎯 Resumen Ejecutivo

Este documento analiza todas las dependencias del **Snippet 24 "Calculo el importe para el monedero y piramide de comisionistas"** para convertirlo en un plugin independiente llamado **CV Commissions**.

---

## 📦 Plugins Requeridos (Hard Dependencies)

### 1. **WooCommerce** ⭐ CRÍTICO
- **Función**: E-commerce base del sistema
- **Clases usadas**:
  - `WC_Order` - Manejo de pedidos
  - `wc_get_order()` - Obtener pedido por ID
  - `wc_get_product()` - Obtener producto por ID
  - `wc_get_order_item_meta()` - Meta datos de items
  
- **Hooks usados**:
  - `woocommerce_checkout_order_processed` - NO se usa directamente
  - `woocommerce_order_status_changed` - NO se usa directamente
  
- **Métodos del order**:
  - `$order->get_items()` - Items del pedido
  - `$order->get_id()` - ID del pedido
  - `$order->get_user_id()` - Usuario comprador
  - `$order->get_status()` - Estado del pedido
  
### 2. **WCFM (WC Frontend Manager)** ⭐ CRÍTICO
- **Función**: Sistema de multi-vendedor
- **Variable global**: `$WCFM`
- **Funciones usadas**:
  - `wcfm_get_vendor_id_by_post($product_id)` - Obtener vendedor de un producto
  - `$WCFM->wcfm_vendor_support->wcfm_is_order_for_vendor($order_id)` - Verificar si pedido es de vendedor
  - `$WCFM->wcfm_vendor_support->wcfm_get_vendor_store_name_by_vendor($vendor_id)` - Nombre de tienda
  
- **Hook usado**:
  - `wcfmmp_order_processed` - **PRINCIPAL** - Se ejecuta cuando se procesa un pedido
  
- **Meta fields usados**:
  - `wcfmmp_profile_settings` - Configuración del vendedor
  - `_wcfmmp_commission` - Configuración de comisión del producto
  - `_vendor_id` - ID del vendedor en item del pedido

### 3. **WCFM Marketplace (WC Multivendor Marketplace)** ⭐ CRÍTICO
- **Función**: Gestión de comisiones de marketplace
- **Variable global**: `$WCFMmp`
- **Tabla**: `wp_wcfm_marketplace_orders` - Órdenes del marketplace
- **Estructura de comisión**:
  ```php
  [
      'commission_mode' => 'percent' | 'fixed',
      'commission_percent' => float,
  ]
  ```

### 4. **Indeed Ultimate Affiliate Pro** ⭐ CRÍTICO
- **Función**: Sistema de afiliados y MLM (Multi-Level Marketing)
- **Variable global**: `$indeed_db` (instancia de `UapDb`)
- **Clase**: `Referral_Main` - Guardar comisiones/referidos
- **Métodos usados**:
  - `$indeed_db->affiliate_get_id_by_uid($user_id)` - Obtener affiliate ID por user ID
  - `$indeed_db->mlm_get_parent($affiliate_id)` - Obtener padre en pirámide MLM
  - `$indeed_db->get_affiliate($affiliate_id)` - Obtener datos de afiliado
  - `Referral_Main::save_referral_unverified($args)` - Guardar comisión como referido
  
- **Tabla**: `wp_uap_referrals` - Almacena los referidos/comisiones
- **Estructura de referral**:
  ```php
  [
      'refferal_wp_uid' => int,      // User ID de WordPress
      'campaign' => string,
      'affiliate_id' => int,         // ID en sistema de afiliados
      'visit_id' => string,
      'description' => string,
      'source' => string,            // 'Calculo privado'
      'reference' => int,            // Order ID
      'reference_details' => string,
      'amount' => float,             // Cantidad de comisión
      'currency' => string,          // 'EUR'
  ]
  ```

### 5. **WooCommerce Wallet** 🔶 IMPORTANTE
- **Función**: Sistema de monedero virtual
- **Hook usado**:
  - `woo_wallet_form_cart_cashback_amount` - Calcular cashback del carrito
- **Función conectada**: `calcula_comision_retorno_carrito()`

---

## 🔧 Funciones Propias del Snippet

### Funciones Principales

#### 1. `add_comision_order($order_id)`
**Función principal** que se ejecuta en el hook `wcfmmp_order_processed`

**Dependencias**:
- `calcula_order_comisions()` - Calcular comisiones
- `send_firebase_notification()` - Notificar al vendedor
- `Referral_Main` - Guardar comisiones
- `$indeed_db` - Base de datos de afiliados

**Flujo**:
1. Calcula todas las comisiones del pedido
2. Envía notificación Firebase
3. Guarda comisión del programador (User ID 3, Affiliate ID 2)
4. Guarda comisión del comprador
5. Guarda comisión de la empresa (User ID 63, Affiliate ID 11)
6. Guarda comisiones de la pirámide MLM (10 niveles compradores + 10 niveles vendedores)

#### 2. `calcula_order_comisions($order_id)`
Calcula todas las comisiones de un pedido

**Retorna**:
```php
[
    'order_id' => int,
    'programador' => float,              // 2% de cada venta
    'programador_id' => 3,
    'comprador' => float,                // 10% de la comisión
    'comprador_affiliate_id' => int,
    'comprador_user_id' => int,
    'comisista_ventas' => [              // 10 niveles
        0 => comprador,
        1 => comprador * 0.10,
        2 => comprador * 0.10,
        ... hasta nivel 9
    ],
    'comisista_compras' => [             // 10 niveles
        0 => comprador,
        1 => comprador * 0.10,
        2 => comprador * 0.10,
        ... hasta nivel 9
    ],
    'empresa' => float,                  // Lo que sobra
    'comisionstas' => [                  // Pirámide de afiliados
        [
            'comprador' => [...],
            'vendedor' => [...]
        ],
        ...
    ]
]
```

#### 3. `calcula_total_comisiones($carrito, $order_id)`
Calcula el total de comisiones para carrito o pedido

**Lógica especial**:
- **Producto 4379 (Ticket)**: Comisión 90% al vendedor, 10% de eso se devuelve
- **Otros productos**: Comisión configurada en producto o vendedor, 10% de eso se devuelve

**Fórmula**:
```
s_comision = total_item - (total_item * comision_percent / 100)
s_comision_devuelta = s_comision * 10 / 100
```

#### 4. `obten_pidamide_compradores($order_id, $piramide)`
Construye la pirámide de 10 niveles de comisionistas

**Lógica**:
- Obtiene 10 niveles hacia arriba desde el comprador
- Obtiene 10 niveles hacia arriba desde el vendedor
- Si no hay suficientes niveles, asigna a Ciudad Virtual (User 63, Affiliate 11)

#### 5. `obten_vendedores_order($order)`
Obtiene el vendor ID del primer producto del pedido

#### 6. `send_firebase_notification($order_id)`
Envía notificación push via Firebase al vendedor

**Dependencias externas**:
- Firebase Cloud Messaging API
- Cloud Function: `https://us-central1-ciudadvitual.cloudfunctions.net/getToken`

---

## 🔢 IDs Hardcodeados

### Usuario/Afiliado Programador
- **User ID**: 3
- **Affiliate ID**: 2
- **Comisión**: 2% de cada venta
- **Descripción**: "Parte programador"

### Usuario/Afiliado Empresa Ciudad Virtual
- **User ID**: 63
- **Affiliate ID**: 11
- **Comisión**: Lo que sobra después de distribuir todo
- **Descripción**: "Parte Empresa"
- **Nombre**: "Francisco Sánchez"
- **Empresa**: "CIUDADVIRTUAL"

### Producto Especial
- **Product ID**: 4379
- **Tipo**: Ticket
- **Comisión vendedor**: 90%
- **Cashback**: 10% de la comisión

---

## 📊 Porcentajes de Distribución

### Distribución General (ejemplo con 100€)
```
Total venta: 100€
Comisión marketplace (10%): 10€

Distribución del 10€:
├─ Programador: 2€ (2% de 100€)
├─ Comprador: 1€ (10% de 10€)
├─ Pirámide Comprador (10 niveles):
│  ├─ Nivel 1: 1€
│  ├─ Nivel 2: 0.10€
│  ├─ Nivel 3: 0.01€
│  └─ ... (cada nivel 10% del anterior)
├─ Pirámide Vendedor (10 niveles):
│  ├─ Nivel 1: 1€
│  ├─ Nivel 2: 0.10€
│  └─ ... (cada nivel 10% del anterior)
└─ Empresa: Resto
```

### Fórmula Empresa
```php
$empresa = $total - $programador - $comprador - ($comisista_compras[1] * 18) - ($comprador * 2);
```

---

## 🗄️ Tablas de Base de Datos Utilizadas

### Del Sistema Actual (Indeed Affiliate Pro)
- `wp_uap_referrals` - Comisiones/referidos
- `wp_uap_affiliates` - Afiliados
- `wp_uap_mlm_relations` - Relaciones MLM

### Del Sistema WCFM
- `wp_wcfm_marketplace_orders` - Órdenes del marketplace

---

## ⚠️ Puntos Críticos para el Plugin

### 1. Verificación de Plugins Activos
El plugin debe verificar que estén activos:
- WooCommerce
- WCFM
- WCFM Marketplace
- Indeed Ultimate Affiliate Pro
- WooCommerce Wallet (opcional)

### 2. Acceso a Variables Globales
```php
global $woocommerce;  // Carrito
global $WCFM;         // WCFM Core
global $WCFMmp;       // WCFM Marketplace
global $indeed_db;    // Indeed Affiliate Pro DB
```

### 3. Hooks Críticos
```php
// Hook principal - procesar comisiones
add_action('wcfmmp_order_processed', 'add_comision_order', 10, 1);

// Hook secundario - calcular cashback carrito
add_filter('woo_wallet_form_cart_cashback_amount', 'calcula_comision_retorno_carrito', 10, 1);
```

### 4. Clase Externa Requerida
```php
// Cargar clase de referidos
require_once UAP_PATH . 'public/Referral_Main.class.php';
$linea_comision = new Referral_Main($user_id, $affiliate_id);
```

---

## 🎨 Propuesta de Estructura del Plugin

```
cv-commissions/
├── cv-commissions.php              # Plugin principal
├── README.md
├── ANALISIS-DEPENDENCIAS.md        # Este archivo
├── includes/
│   ├── class-cv-commission-calculator.php    # Cálculo de comisiones
│   ├── class-cv-mlm-pyramid.php              # Pirámide MLM
│   ├── class-cv-firebase-notifier.php        # Notificaciones Firebase
│   ├── class-cv-commission-distributor.php   # Distribución de comisiones
│   └── class-cv-dependencies-checker.php     # Verificar dependencias
├── admin/
│   ├── class-cv-admin-settings.php           # Página de configuración
│   └── views/
│       └── settings.php                       # Vista de configuración
└── config/
    └── default-config.php                     # Configuración por defecto
```

---

## 🔄 Mejoras Propuestas

### 1. Hacer Configurables los IDs Hardcodeados
- ID Programador: Opción en admin
- ID Empresa: Opción en admin
- Porcentajes: Configurables

### 2. Separar Lógica de Firebase
- Hacer opcional las notificaciones
- Permitir configurar la URL de Cloud Function

### 3. Logging y Debug
- Sistema de logs para tracking
- Panel de debug en admin

### 4. Soporte Multi-Currency
- Actualmente solo EUR
- Permitir otras monedas

---

## ✅ Checklist de Conversión a Plugin

- [ ] Crear estructura de directorios
- [ ] Verificar dependencias al activar
- [ ] Extraer IDs hardcodeados a configuración
- [ ] Separar funciones en clases
- [ ] Crear página de administración
- [ ] Implementar sistema de logs
- [ ] Añadir filtros y acciones para extensibilidad
- [ ] Documentar hooks disponibles
- [ ] Crear tests básicos
- [ ] Preparar para traducción (i18n)

---

## 📝 Notas Adicionales

- El sistema es complejo y tiene muchas interdependencias
- Es fundamental mantener la lógica de cálculo intacta
- Se debe testear exhaustivamente con pedidos reales
- Considerar impacto en rendimiento (muchas llamadas a BD)

