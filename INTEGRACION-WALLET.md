# 💰 Integración con WooCommerce Wallet

## 📋 Índice
1. [Descripción](#descripción)
2. [Origen](#origen)
3. [Funcionalidad](#funcionalidad)
4. [Implementación](#implementación)
5. [Uso](#uso)
6. [Debug](#debug)
7. [Código Original](#código-original)

---

## 🎯 Descripción

Esta integración permite que el sistema de comisiones de Ciudad Virtual calcule automáticamente el monto correcto a acreditar en el monedero (wallet) de WooCommerce para cada transacción basándose en las comisiones configuradas.

### Versión
- **Integrado en:** v1.0.2
- **Snippet Original:** #36 - "Calculo monedero a CV"

---

## 📦 Origen

### Snippet Original (Code Snippets #36)
```php
add_filter('woo_wallet_transactions_args','filtroMonedero',10,1);
function filtroMonedero($args){
    error_log("FILTRO MONEDERO");
    error_log(print_r($args,true));    
    $pos = strpos( $args["details"],"#")+1;
    error_log("Pos:".$pos);
    $order_id=substr($args["details"],$pos);
    error_log("order:".$order_id);
    $comisiones=calcula_total_comisiones(false,$order_id);
    error_log("Comisiones");
    $args['amount']=$comisiones;
    error_log("Amount -de order:".$args['amount']);
    return $args;
}
```

### Migración al Plugin
- **Clase:** `CV_Wallet_Integration`
- **Archivo:** `includes/class-cv-wallet-integration.php`
- **Hook:** `woo_wallet_transactions_args`

---

## ⚙️ Funcionalidad

### ¿Qué hace?

Cuando WooCommerce Wallet va a crear una transacción (por ejemplo, al acreditar cashback por una compra), este filtro intercepta la operación y:

1. **Extrae el ID del pedido** desde los detalles de la transacción
2. **Calcula las comisiones** usando el sistema de comisiones de CV
3. **Actualiza el monto** de la transacción con el valor calculado
4. **Registra logs** (si está en modo debug) para tracking

### Flujo de Ejecución

```
Pedido completado
    ↓
WooCommerce Wallet va a crear transacción
    ↓
Hook: woo_wallet_transactions_args
    ↓
CV_Wallet_Integration intercepta
    ↓
Extrae order_id de los detalles
    ↓
Llama a calcula_total_comisiones()
    ↓
Actualiza $args['amount']
    ↓
Wallet crea transacción con monto correcto
```

---

## 💻 Implementación

### Clase Principal

**Ubicación:** `includes/class-cv-wallet-integration.php`

```php
class CV_Wallet_Integration {
    
    // Hook principal
    add_filter('woo_wallet_transactions_args', 
               array($this, 'filter_wallet_transaction'), 10, 1);
    
    // Filtrar transacción
    public function filter_wallet_transaction($args) {
        // 1. Extraer order_id
        $order_id = $this->extract_order_id_from_details($args);
        
        // 2. Calcular comisiones
        $comisiones = $this->calculate_commissions($order_id);
        
        // 3. Actualizar monto
        $args['amount'] = $comisiones;
        
        return $args;
    }
}
```

### Inicialización

**Ubicación:** `cv-commissions.php` línea 126

```php
// Inicializar integración con WooCommerce Wallet (Snippet 36 integrado)
new CV_Wallet_Integration();
```

---

## 🎮 Uso

### Activación Automática

La integración se activa automáticamente cuando el plugin `cv-commissions` está activo.

**Requisitos:**
- ✅ Plugin `CV Commissions` activado
- ✅ Plugin `WooCommerce Wallet` activado
- ✅ Sistema de comisiones configurado

### Ejemplo de Uso Real

**Escenario:** Cliente compra producto por 100€

1. **Pedido completado** → `order_id = 1234`
2. **Wallet intenta acreditar** → Valor por defecto: 10€ (cashback configurado)
3. **Filtro CV intercepta** → Calcula comisiones reales según configuración CV
4. **Comisión calculada** → 15€ (por ejemplo, según pirámide MLM)
5. **Wallet acredita** → 15€ en lugar de 10€

---

## 🐛 Debug

### Activar Modo Debug

#### Opción 1: Constante en wp-config.php
```php
define('CV_WALLET_DEBUG', true);
```

#### Opción 2: Programáticamente
```php
$wallet_integration = new CV_Wallet_Integration();
$wallet_integration->set_debug_mode(true);
```

### Logs Generados

Con debug activado, se registran en `wp-content/debug.log`:

```
[CV Wallet Integration] === FILTRO MONEDERO CV ACTIVADO ===
[CV Wallet Integration] Args originales: Array(...)
[CV Wallet Integration] Details recibidos: Cashback para pedido #1234
[CV Wallet Integration] Order ID extraído: 1234
[CV Wallet Integration] Comisiones calculadas: 15.50
[CV Wallet Integration] Amount actualizado para el monedero: 15.50
[CV Wallet Integration] === FIN FILTRO MONEDERO CV ===
```

### Verificar Funcionamiento

```php
// En functions.php o snippet temporal
add_action('woo_wallet_transactions_args', function($args) {
    error_log('Wallet Args: ' . print_r($args, true));
    return $args;
}, 5, 1); // Prioridad 5 (antes del filtro CV que tiene 10)
```

---

## 🔧 Características Técnicas

### Extracción del Order ID

La clase maneja diferentes formatos de detalles:

```php
"Cashback para pedido #1234"          → 1234
"Comisión pedido #1234 - extra text"  → 1234
"Order #1234"                          → 1234
```

Usa regex para ser más robusto:
```php
preg_match('/^\d+/', $order_id_string, $matches);
```

### Manejo de Errores

La clase tiene múltiples validaciones:

1. ✅ Verifica que exista `$args['details']`
2. ✅ Verifica que encuentre el símbolo `#`
3. ✅ Verifica que extraiga un número válido
4. ✅ Verifica que el pedido exista
5. ✅ Verifica que `calcula_total_comisiones()` exista
6. ✅ Maneja excepciones en cálculos

### Compatibilidad con Tipos de Retorno

La función `calcula_total_comisiones()` puede devolver:

- **Float/Int:** Se usa directamente
- **Array con 'comprador':** Se extrae `$comisiones['comprador']`
- **Otro:** Se retorna false y no se modifica el monto

```php
// Si es numérico
if (is_numeric($comisiones)) {
    return floatval($comisiones);
}

// Si es array con clave 'comprador'
if (is_array($comisiones) && isset($comisiones['comprador'])) {
    return floatval($comisiones['comprador']);
}
```

---

## 📊 Diferencias con el Snippet Original

| Aspecto | Snippet Original | Clase Integrada |
|---------|------------------|-----------------|
| **Organización** | Función global | Clase encapsulada |
| **Logs** | Siempre activos | Solo en modo debug |
| **Manejo errores** | Básico | Completo con validaciones |
| **Extracción ID** | `substr()` simple | Regex robusto |
| **Documentación** | Sin documentar | Completamente documentado |
| **Testing** | Difícil | Fácil (métodos privados testeables) |
| **Modo debug** | No configurable | Configurable vía constante |

---

## 🚀 Mejoras Implementadas

### 1. **Extracción Robusta del Order ID**
- Usa regex en lugar de `substr()` simple
- Maneja múltiples formatos de texto
- Extrae solo números aunque haya más texto

### 2. **Logging Inteligente**
- Solo se activa en modo debug
- Mensajes más claros y estructurados
- Prefijo `[CV Wallet Integration]` para fácil filtrado

### 3. **Validaciones Completas**
- Verifica existencia del pedido
- Verifica que la función de cálculo existe
- Manejo de excepciones

### 4. **Compatibilidad con Retornos Múltiples**
- Soporta retorno numérico directo
- Soporta array con clave 'comprador'
- Fallback seguro si no reconoce el formato

### 5. **Documentación PHPDoc**
- Cada método documentado
- Tipos de parámetros claros
- Explicación de retornos

---

## 🔗 Dependencias

Esta integración depende de:

1. **WooCommerce Wallet**
   - Proporciona el hook `woo_wallet_transactions_args`
   - Maneja las transacciones del monedero

2. **Función `calcula_total_comisiones()`**
   - Definida en `includes/compatibility-functions.php`
   - Calcula las comisiones según configuración CV

3. **Sistema de Comisiones CV**
   - Configuración de porcentajes
   - Pirámide MLM
   - Cálculo de cashback

---

## ✅ Testing

### Prueba Manual

1. **Configurar cashback en WooCommerce Wallet**
2. **Realizar una compra de prueba**
3. **Verificar en Wallet del comprador** que se acredita el monto correcto
4. **Revisar logs** (si debug está activo)

### Verificar Integración

```php
// Verificar que la clase está cargada
if (class_exists('CV_Wallet_Integration')) {
    echo "✅ Integración Wallet cargada";
}

// Verificar que el filtro está registrado
if (has_filter('woo_wallet_transactions_args')) {
    echo "✅ Filtro registrado";
}
```

---

## 📝 Notas Importantes

### ⚠️ Advertencias

1. **Requiere WooCommerce Wallet activo** - Si no está activo, el hook no se ejecuta pero no causa errores
2. **Extracción del Order ID** - Depende del formato del campo `details` del wallet
3. **Modo Debug** - Recuerda desactivarlo en producción para evitar logs excesivos

### 💡 Tips

1. Activa debug solo cuando necesites troubleshooting
2. El formato de `details` debe incluir `#` seguido del order ID
3. Si usas un formato personalizado en Wallet, puede que necesites ajustar `extract_order_id_from_details()`

---

## 🎯 Casos de Uso

### Caso 1: Cashback Automático
Cliente compra → Sistema calcula comisión → Se acredita en wallet automáticamente

### Caso 2: Comisiones MLM
Cliente referido compra → Se calculan comisiones para toda la pirámide → Se acredita al comprador su parte

### Caso 3: Promociones Especiales
Durante promoción → Comisiones aumentadas → Wallet refleja el monto correcto

---

## 📚 Referencias

- **Snippet Original:** Code Snippets #36
- **Clase:** `CV_Wallet_Integration`
- **Hook WordPress:** `woo_wallet_transactions_args`
- **Documentación WooCommerce Wallet:** [Plugin oficial](https://wordpress.org/plugins/woo-wallet/)

---

## 🔄 Changelog de la Integración

### v1.0.2 (2025-10-22)
- ✅ Integración inicial del Snippet 36
- ✅ Creación de clase `CV_Wallet_Integration`
- ✅ Modo debug configurable
- ✅ Extracción robusta de Order ID con regex
- ✅ Documentación completa

---

## 👨‍💻 Mantenimiento

Si necesitas modificar el comportamiento:

1. **Archivo:** `includes/class-cv-wallet-integration.php`
2. **Método principal:** `filter_wallet_transaction()`
3. **Extracción ID:** `extract_order_id_from_details()`
4. **Cálculo:** `calculate_commissions()`

---

**Desarrollado para:** Ciudad Virtual  
**Fecha:** 22 de Octubre, 2025  
**Versión Plugin:** 1.0.2





