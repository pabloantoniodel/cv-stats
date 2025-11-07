# Dashboard de Comisiones para Vendedores

## 📋 Descripción

Dashboard completo de comisiones integrado en el panel WCFM del vendedor que muestra:

- **Historial de comisiones** desde todas las fuentes (UAP Referrals, WCFM Ledger)
- **Estado del monedero** (WooCommerce Wallet)
- **Estadísticas generales** (aprobadas, pendientes, balance)
- **Detalles de pedidos** con comisiones calculadas

---

## ✅ Funcionalidades

### 1. **Widget en Dashboard Principal de WCFM**

Widget automático que aparece en el dashboard principal del vendedor mostrando:

- 💰 **Monedero**: Balance disponible
- ✅ **Aprobadas**: Total aprobado
- ⏰ **Pendientes**: Total pendiente
- 📖 **Balance**: Créditos - Débitos
- 📊 **Total transacciones**: Contador
- 🔗 **Enlace directo**: "Ver Dashboard Completo"

**Hook utilizado**: `wcfm_after_dashboard_setup`

### 2. **Shortcode para Páginas**

Usa `[cv_commissions_summary]` en cualquier página para mostrar el resumen de comisiones del usuario actual.

**Características**:
- Solo visible para usuarios logueados
- Muestra las 4 estadísticas principales
- Enlace al dashboard completo
- Responsive automático

### 3. **Tarjetas de Resumen (Dashboard Completo)**

Muestra 4 tarjetas con información clave:

- **Balance Monedero**: Saldo disponible para retiro
- **Comisiones Aprobadas**: Total de comisiones ya aprobadas
- **Comisiones Pendientes**: Total pendiente de aprobación
- **Balance Libro Contable**: Créditos y débitos del vendedor

### 4. **Pestaña: Comisiones UAP**

Tabla con todas las comisiones de Ultimate Affiliate Pro:

| Campo | Descripción |
|-------|-------------|
| ID | ID de la comisión |
| Fecha | Fecha de creación |
| Pedido | Link al pedido (#ID) |
| Descripción | Detalles de la comisión |
| Monto | Cantidad de la comisión |
| Estado | approved / pending / refused |
| Pago | paid / unpaid / pending |
| Fuente | UAP / WCFM_LEDGER |

### 5. **Pestaña: Pedidos WCFM**

Tabla con pedidos y comisiones del marketplace:

| Campo | Descripción |
|-------|-------------|
| ID | ID del registro |
| Pedido | Link al pedido |
| Producto | Nombre del producto |
| Cantidad | Unidades vendidas |
| Total Item | Total del item |
| Comisión | Monto de comisión |
| Total Comisión | Total calculado |
| Estado Pedido | processing / completed / etc |
| Estado Comisión | approved / pending |
| Fecha | Fecha del pedido |

### 6. **Pestaña: Transacciones Monedero**

Tabla con transacciones del WooCommerce Wallet:

| Campo | Descripción |
|-------|-------------|
| ID | ID de transacción |
| Fecha | Fecha de la transacción |
| Tipo | credit / debit |
| Detalles | Descripción |
| Monto | +/- según tipo |
| Balance | Balance después de transacción |

---

## 🗄️ Tablas de Base de Datos Consultadas

### 1. **`wp_uap_referrals`** (Indeed Ultimate Affiliate Pro)

```sql
SELECT 
    id,
    refferal_wp_uid,  -- vendor_id
    reference,         -- order_id
    reference_details, -- descripción
    amount,           -- monto
    currency,
    date,
    status,           -- 0=refused, 1=pending, 2=approved
    payment           -- 0=unpaid, 1=pending, 2=paid
FROM wp_uap_referrals
WHERE refferal_wp_uid = {vendor_id}
```

### 2. **`wp_wcfm_marketplace_vendor_ledger`** (WCFM Marketplace)

```sql
SELECT 
    ID,
    vendor_id,
    credit,              -- créditos
    debit,               -- débitos
    reference_id,        -- order_id
    reference,           -- tipo de referencia
    reference_details,   -- detalles
    reference_status,    -- estado
    created
FROM wp_wcfm_marketplace_vendor_ledger
WHERE vendor_id = {vendor_id}
```

### 3. **`wp_wcfm_marketplace_orders`** (WCFM Marketplace)

```sql
SELECT 
    ID,
    order_id,
    product_id,
    quantity,
    item_total,
    commission_amount,
    total_commission,
    order_status,
    commission_status,
    withdraw_status,
    created,
    commission_paid_date
FROM wp_wcfm_marketplace_orders
WHERE vendor_id = {vendor_id}
```

### 4. **`wp_woo_wallet_transactions`** (WooCommerce Wallet)

```sql
SELECT *
FROM wp_woo_wallet_transactions
WHERE user_id = {vendor_id}
ORDER BY date DESC
```

---

## 🎨 Estructura de Archivos

```
cv-commissions/
├── includes/
│   └── class-cv-commissions-dashboard.php   # Clase principal
├── views/
│   └── dashboard.php                        # Template HTML
├── assets/
│   ├── css/
│   │   └── dashboard.css                    # Estilos
│   └── js/
│       └── dashboard.js                     # JavaScript
└── DASHBOARD-COMISIONES.md                  # Esta documentación
```

---

## 🔌 Integración con WCFM

### Hooks utilizados:

| Hook | Función | Descripción |
|------|---------|-------------|
| `wcfm_query_vars` | `add_query_vars()` | Agregar query var del endpoint |
| `wcfm_endpoint_title` | `endpoint_title()` | Título del endpoint |
| `init` | `init_endpoint()` | Inicializar endpoint |
| `wcfm_endpoints_slug` | `endpoints_slug()` | Slug del endpoint |
| `wcfm_menus` | `add_menu()` | Agregar menú al panel |
| `wcfm_load_views` | `load_views()` | Cargar vista |
| `wcfm_load_styles` | `load_styles()` | Cargar estilos |
| `wcfm_load_scripts` | `load_scripts()` | Cargar scripts |
| `wp_ajax_cv_get_commissions_data` | `ajax_get_commissions_data()` | Handler AJAX |
| `wcfm_after_dashboard_setup` | `add_dashboard_widget()` | Widget en dashboard |

### Endpoint creado:

- **Slug**: `cv-commissions-dashboard`
- **URL amigable**: `/mis-comisiones-cv/`
- **Posición menú**: 39 (después del dashboard)
- **Icono**: `money-alt`
- **Solo vendedores**: Sí

---

## 📊 Cálculos y Totales

### Totales UAP:
- `uap_total_count`: Total de registros
- `uap_approved`: Suma de comisiones aprobadas (status=2)
- `uap_pending`: Suma de comisiones pendientes (status=1)
- `uap_refused`: Suma de comisiones rechazadas (status=0)
- `uap_paid`: Suma de comisiones pagadas (payment=2)

### Totales WCFM Ledger:
- `ledger_count`: Total de registros
- `ledger_credits`: Suma de créditos
- `ledger_debits`: Suma de débitos
- `ledger_balance`: Créditos - Débitos

### Totales WCFM Orders:
- `orders_count`: Total de pedidos
- `orders_commissions`: Suma de todas las comisiones
- `orders_approved`: Suma de comisiones aprobadas
- `orders_withdrawn`: Suma de comisiones retiradas

### Totales Combinados:
- `total_count`: Suma de todas las fuentes
- `total_approved`: UAP approved + Orders approved
- `total_pending`: UAP pending
- `total_balance`: Ledger balance

---

## 🎯 AJAX

### Endpoint AJAX:
```javascript
cvCommissionsData = {
    ajax_url: '/wp-admin/admin-ajax.php',
    nonce: 'generated_nonce'
}
```

### Request:
```javascript
{
    action: 'cv_get_commissions_data',
    nonce: cvCommissionsData.nonce,
    page: 1
}
```

### Response:
```json
{
    "success": true,
    "data": {
        "commissions": [...],
        "wcfm_orders": [...],
        "totals": {...},
        "wallet": {...},
        "wallet_transactions": [...],
        "page": 1,
        "per_page": 20
    }
}
```

---

## 🎨 Estilos

### Clases CSS principales:

- `.cv-dashboard-cards`: Grid de tarjetas
- `.cv-card`: Tarjeta individual
- `.cv-tabs`: Contenedor de pestañas
- `.cv-tab-btn`: Botón de pestaña
- `.cv-tab-content`: Contenido de pestaña
- `.cv-table`: Tabla de datos
- `.cv-status`: Badge de estado
- `.cv-badge`: Badge de fuente
- `.cv-positive`: Monto positivo (verde)
- `.cv-negative`: Monto negativo (rojo)

### Responsive:
- Breakpoint: `768px`
- Grid de tarjetas: `1 columna` en móvil
- Pestañas: `vertical` en móvil

---

## ✅ Verificación

### Checklist de funcionamiento:

- [ ] Widget aparece en dashboard principal de WCFM
- [ ] Widget muestra estadísticas correctas
- [ ] Enlace "Ver Dashboard Completo" funciona
- [ ] Menú aparece en panel WCFM
- [ ] Tarjetas muestran datos correctos
- [ ] Pestañas cambian correctamente
- [ ] Tablas muestran comisiones UAP
- [ ] Tablas muestran pedidos WCFM
- [ ] Transacciones de wallet aparecen
- [ ] Links a pedidos funcionan
- [ ] Estados se muestran con colores correctos
- [ ] Shortcode [cv_commissions_summary] funciona
- [ ] Responsive funciona en móvil

### Logs de debug:
```javascript
console.log('✅ CV Commissions Dashboard inicializado');
```

---

## 🔧 Personalización

### Cambiar número de items por página:

```php
// En class-cv-commissions-dashboard.php
$per_page = 20; // Cambiar a 50, 100, etc
```

### Agregar nueva pestaña:

1. Actualizar HTML en `views/dashboard.php`
2. Agregar método en clase dashboard
3. Actualizar JavaScript en `assets/js/dashboard.js`

---

## 🐛 Troubleshooting

### Dashboard no aparece:
- Verificar que el usuario sea vendedor (`wcfm_is_vendor()`)
- Verificar que WCFM esté activo
- Limpiar caché de permalinks (`flush_rewrite_rules()`)

### Datos no cargan:
- Verificar que las tablas existan
- Revisar logs de PHP (`error_log()`)
- Verificar permisos de usuario

### Estilos no aplican:
- Verificar que el CSS se cargue (DevTools → Network)
- Verificar selector `#wcfm_cv_commissions_dashboard`
- Limpiar caché del navegador

---

## 📝 Changelog

### Versión 1.0.3
- ✅ Implementación inicial del dashboard
- ✅ Integración con WCFM
- ✅ Consulta de 3 tablas principales
- ✅ Sistema de pestañas
- ✅ Tarjetas de resumen
- ✅ Integración con WooCommerce Wallet
- ✅ Widget en dashboard principal
- ✅ Shortcode para páginas personalizadas

---

## 🚀 Próximas Mejoras

- [ ] Paginación en tablas
- [ ] Filtros por fecha
- [ ] Exportar a CSV/PDF
- [ ] Gráficos de evolución
- [ ] Notificaciones en tiempo real
- [ ] Búsqueda en tablas


