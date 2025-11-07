# 💰 Ciudad Virtual - Sistema de Comisiones MLM

Plugin de WordPress que implementa un sistema completo de comisiones multinivel (MLM) con pirámide de afiliados para marketplace.

## 🚨 ADVERTENCIA CRÍTICA - BREAKING CHANGE

**⚠️ Este plugin CORRIGE UN BUG del snippet original que multiplicaba las comisiones incorrectamente.**

- ❌ **Snippet original**: Calculaba ~40x más comisiones (incorrecto)
- ✅ **Este plugin**: Calcula comisiones correctas (10% del pedido, no 400%)

**ANTES DE ACTIVAR**: Lee `BREAKING-CHANGE-CORRECCION-BUG.md` para entender el impacto.

Las comisiones serán significativamente **menores pero matemáticamente correctas**.

---

## 📋 Descripción

Este plugin convierte el **Snippet 24** "Calculo el importe para el monedero y piramide de comisionistas" en un plugin independiente y configurable, **CORRIGIENDO** el bug de multiplicación doble por cantidad. Gestiona la distribución automática de comisiones entre programador, comprador, empresa y una pirámide de 10 niveles de afiliados.

## ✨ Características

- ✅ **Sistema MLM con 10 niveles** - Distribución automática en pirámide de compradores y vendedores
- ✅ **Comisiones configurables** - Todos los porcentajes y IDs son ajustables desde el admin
- ✅ **Integración completa** - WooCommerce, WCFM, WCFM Marketplace, Indeed Affiliate Pro
- ✅ **Notificaciones Firebase** - Push notifications a vendedores en pedidos nuevos
- ✅ **Panel de administración** - Interfaz completa para configurar el sistema
- ✅ **Verificación de dependencias** - Comprueba que todos los plugins necesarios estén activos
- ✅ **Sistema de logging** - Seguimiento detallado de operaciones para debugging

## 📦 Dependencias Requeridas

### Plugins Obligatorios
1. **WooCommerce** - Sistema de e-commerce
2. **WCFM (WC Frontend Manager)** - Sistema multi-vendedor
3. **WCFM Marketplace** - Gestión de comisiones de marketplace
4. **Indeed Ultimate Affiliate Pro** - Sistema de afiliados y MLM

### Plugins Opcionales
- **WooCommerce Wallet** - Para sistema de monedero/cashback

## 🚀 Instalación

1. Subir la carpeta `cv-commissions` a `/wp-content/plugins/`
2. Activar el plugin desde el panel de WordPress
3. Ir a **CV Comisiones** en el menú de administración
4. Configurar los parámetros según tus necesidades
5. Guardar cambios

## ⚙️ Configuración

### IDs de Usuarios Especiales

- **Programador**: Usuario que recibe comisión fija por cada venta
- **Empresa**: Usuario/empresa que recibe el resto de comisiones no distribuidas

### Porcentajes de Comisión

- **Comisión Programador**: % de cada venta (por defecto: 2%)
- **Comisión Comprador**: % de la comisión del marketplace (por defecto: 10%)
- **Cashback Monedero**: % que se devuelve al monedero (por defecto: 10%)

### Pirámide MLM

- **Niveles**: Cantidad de niveles en la pirámide (por defecto: 10)
- **Porcentaje por Nivel**: % que recibe cada nivel del anterior (por defecto: 10%)

### Producto Especial (Ticket)

- **Product ID**: ID del producto con comisión especial
- **Comisión Especial**: % de comisión para este producto (por defecto: 90%)

### Firebase

- **API Key**: Server Key de Firebase Cloud Messaging
- **URL Cloud Function**: Endpoint para obtener tokens de dispositivos

## 💡 Funcionamiento

### Flujo de Comisiones

Cuando se procesa un pedido:

1. **Notificación**: Se envía push notification al vendedor via Firebase
2. **Cálculo**: Se calculan todas las comisiones del pedido
3. **Distribución**:
   - Programador recibe su comisión fija
   - Comprador recibe su porcentaje
   - Se construye la pirámide MLM de 10 niveles (compradores y vendedores)
   - Empresa recibe el resto

### Ejemplo de Distribución (Venta de 100€)

```
Total venta: 100€
Comisión marketplace (10%): 10€

Distribución:
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

## 🔌 Hooks Utilizados

### Actions

- `wcfmmp_order_processed` - Procesar comisiones cuando se completa un pedido

### Filters

- `woo_wallet_form_cart_cashback_amount` - Calcular cashback del carrito

## 📂 Estructura del Plugin

```
cv-commissions/
├── cv-commissions.php                          # Archivo principal
├── README.md                                    # Este archivo
├── ANALISIS-DEPENDENCIAS.md                    # Análisis técnico completo
├── config/
│   └── default-config.php                      # Configuración por defecto
├── includes/
│   ├── class-cv-dependencies-checker.php       # Verificador de dependencias
│   ├── class-cv-commission-calculator.php      # Calculadora de comisiones
│   ├── class-cv-mlm-pyramid.php                # Constructor de pirámide MLM
│   ├── class-cv-firebase-notifier.php          # Notificaciones Firebase
│   └── class-cv-commission-distributor.php     # Distribuidor de comisiones
└── admin/
    ├── class-cv-admin-settings.php             # Controlador de admin
    └── views/
        └── settings.php                         # Vista de configuración
```

## 🔧 Clases Principales

### `CV_Commission_Calculator`
Calcula todas las comisiones de un pedido basándose en:
- Configuración del producto
- Configuración del vendedor
- Producto especial (tickets)
- Porcentajes configurados

### `CV_MLM_Pyramid`
Construye la pirámide de 10 niveles:
- Obtiene padres en la cadena MLM
- Calcula comisiones por nivel
- Rellena niveles faltantes con la empresa

### `CV_Commission_Distributor`
Orquesta todo el proceso:
- Envía notificaciones
- Calcula comisiones
- Guarda referidos en Indeed Affiliate Pro

### `CV_Firebase_Notifier`
Gestiona notificaciones push:
- Obtiene token del vendedor
- Envía notificación via FCM

## 📊 Base de Datos

El plugin utiliza las tablas de **Indeed Ultimate Affiliate Pro**:

- `wp_uap_referrals` - Donde se guardan todas las comisiones
- `wp_uap_mlm_relations` - Relaciones de la pirámide MLM

## 🐛 Debugging

### Activar Logging

En la página de configuración, activa:
- **Habilitar Logging**: Para registrar eventos básicos
- **Modo Debug**: Para información detallada

### Ver Logs

Los logs se escriben en `wp-content/debug.log` (si `WP_DEBUG_LOG` está activado en `wp-config.php`)

```php
// En wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

## 🔒 Seguridad

- ✅ Verificación de permisos (`manage_options`)
- ✅ Nonces en formularios
- ✅ Sanitización de inputs
- ✅ Escape de outputs
- ✅ Verificación de dependencias al activar

## 📝 Changelog

### Version 1.0.0 (2025-10-21)
- 🎉 Versión inicial
- ✅ Conversión del Snippet 24 a plugin
- ✅ Panel de administración completo
- ✅ Sistema de configuración
- ✅ Verificación de dependencias
- ✅ Sistema de logging

## 👨‍💻 Desarrollador

**Ciudad Virtual**
- Web: https://ciudadvirtual.app
- Email: soporte@ciudadvirtual.app

## 📄 Licencia

GPL v2 o superior

## 🤝 Contribuir

Para reportar bugs o solicitar features, contactar con el equipo de desarrollo.

## ⚠️ Notas Importantes

1. **Backup**: Siempre hacer backup antes de instalar
2. **Testing**: Probar en entorno de desarrollo primero
3. **Dependencias**: Verificar que todos los plugins requeridos estén activos
4. **Configuración**: Revisar y ajustar los valores por defecto según tus necesidades
5. **Logs**: Activar logging durante la configuración inicial para verificar funcionamiento

## 📚 Documentación Adicional

Ver `ANALISIS-DEPENDENCIAS.md` para documentación técnica detallada sobre:
- Dependencias específicas
- Estructura de datos
- Flujos de proceso
- IDs hardcodeados
- Tablas de base de datos

