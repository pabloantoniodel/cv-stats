# 🧪 Test Pedido #87076 - CON Cadena MLM Completa

## 📦 Datos del Pedido

**Pedido ID**: 87076  
**Fecha**: 2024-09-25 11:42:37  
**Total**: 30.00€

### Producto
- **ID**: 1788
- **Nombre**: AGUAPANELA CON LIMON
- **Cantidad**: 30 unidades
- **Precio unitario**: 1.00€
- **Subtotal**: 30.00€
- **Total línea**: 30.00€

### Comprador (CON cadena MLM ✓)
- **User ID**: 1114
- **Nombre**: juan carlos.san vicente marin
- **Affiliate ID**: 1018
- **Padre MLM**: Affiliate 29 (User 85 - El vendedor!)

### Vendedor (CON cadena MLM ✓)
- **User ID**: 85
- **Nombre**: LASDELICIASDEGUS
- **Affiliate ID**: 29
- **Padre MLM**: Affiliate 20 (User 77)

### 🎯 Particularidad Especial
**El vendedor (Affiliate 29) es el padre MLM del comprador (Affiliate 1018)**

---

## 🧮 Cálculos Detallados

### ❌ Cálculo con BUG (Snippet Original)

```php
$price = $item['subtotal'];  // 30€ (YA incluye 30 unidades)
$quantity = 30;

$s_comision = ($quantity × $price) - (($quantity × $price × 90) / 100);
$s_comision = (30 × 30) - ((30 × 30 × 90) / 100);
$s_comision = 900 - 810 = 90€  ❌ INCORRECTO

$s_comision_devuelta = $s_comision × 10 / 100;
$s_comision_devuelta = 90 × 0.10 = 9.00€

Total a repartir = $s_comision_devuelta × 10 = 90€
```

**Comisión base**: 9.00€  
**Total a repartir**: 90.00€

### ✅ Cálculo CORRECTO (Plugin)

```php
$price = $_product->get_price();  // 1€ (precio UNITARIO)
$quantity = 30;

$s_comision = ($quantity × $price) - (($quantity × $price × 90) / 100);
$s_comision = (30 × 1) - ((30 × 1 × 90) / 100);
$s_comision = 30 - 27 = 3€  ✅ CORRECTO

$s_comision_devuelta = $s_comision × 10 / 100;
$s_comision_devuelta = 3 × 0.10 = 0.30€

Total a repartir = $s_comision_devuelta × 10 = 3€
```

**Comisión base**: 0.30€  
**Total a repartir**: 3.00€

---

## 📊 Comisiones Registradas (BD)

### Comisiones Principales

| ID | Usuario | Affiliate | Concepto | Monto (Bug) | Monto (Correcto) |
|----|---------|-----------|----------|-------------|------------------|
| 1318 | 3 | 2 | Programador | 9.000€ | 0.300€ |
| 1319 | 1114 | 1018 | Comprador | 9.000€ | 0.300€ |
| 1320 | 63 | 11 | Empresa | 37.800€ | 1.260€ |

**Total principales**: 55.80€ (Bug) vs 1.86€ (Correcto)

### Comisiones MLM Registradas ✓

| ID | Usuario | Affiliate | Concepto | Monto | Nivel |
|----|---------|-----------|----------|-------|-------|
| 1321 | **85** | **29** | MLM comprador | 9.000€ | Nivel 1 comprador |
| 1322 | **77** | **20** | MLM vendedor | 9.000€ | Nivel 1 vendedor |
| 1323 | 77 | 20 | MLM comprador | 0.900€ | Nivel 2 comprador |
| 1324 | 68 | 11 | MLM vendedor | 0.900€ | Nivel 2 vendedor |
| 1325 | 68 | 11 | MLM comprador | 0.900€ | Nivel 3 comprador |

**Total MLM**: 20.70€ (Bug)

**Total General**: 76.50€ (Bug) vs 2.55€ (Correcto)

---

## 🔍 Análisis de la Cadena MLM

### Cadena del Comprador (Affiliate 1018)
```
Nivel 0: Comprador 1018 (User 1114) → 9.00€
Nivel 1: Padre 29 (User 85 - EL VENDEDOR) → 9.00€ ✓ REGISTRADO
Nivel 2: Abuelo 20 (User 77) → 0.90€ ✓ REGISTRADO
Nivel 3-10: Ciudad Virtual (Affiliate 11) → 0.90€ c/u
```

### Cadena del Vendedor (Affiliate 29)
```
Nivel 0: Vendedor 29 (User 85) → 9.00€
Nivel 1: Padre 20 (User 77) → 9.00€ ✓ REGISTRADO
Nivel 2-10: Ciudad Virtual (Affiliate 11/68) → 0.90€ c/u
```

---

## ✅ Verificación de Cálculos

### Con el BUG (Snippet Original)

| Concepto | Esperado | Real en BD | Estado |
|----------|----------|------------|--------|
| **Programador** | 9.00€ | 9.000€ | ✅ Coincide |
| **Comprador** | 9.00€ | 9.000€ | ✅ Coincide |
| **Empresa** | 37.80€ | 37.800€ | ✅ Coincide |
| **MLM Comprador L1** | 9.00€ | 9.000€ | ✅ Coincide |
| **MLM Vendedor L1** | 9.00€ | 9.000€ | ✅ Coincide |
| **MLM Comprador L2** | 0.90€ | 0.900€ | ✅ Coincide |

**✅ TODOS los cálculos del snippet original coinciden con la BD**

### Con la CORRECCIÓN (Plugin)

| Concepto | Calculado | Diferencia vs Bug |
|----------|-----------|-------------------|
| **Programador** | 0.30€ | **30x menos** |
| **Comprador** | 0.30€ | **30x menos** |
| **Empresa** | 1.26€ | **30x menos** |
| **MLM Comprador L1** | 0.30€ | **30x menos** |
| **MLM Vendedor L1** | 0.30€ | **30x menos** |
| **MLM Comprador L2** | 0.03€ | **30x menos** |
| **TOTAL** | 3.00€ | **30x menos** (90€ → 3€) |

---

## 💡 Observaciones Importantes

### 1. Particularidad del Pedido
El **vendedor es padre MLM del comprador**, lo que significa:
- El vendedor (User 85, Affiliate 29) aparece en dos lados:
  - Como vendedor (nivel 0 ventas)
  - Como padre del comprador (nivel 1 compras)
- Esto es legítimo y el sistema lo maneja correctamente

### 2. MLM Procesado Correctamente
✅ El snippet original **SÍ procesó la cadena MLM** en este pedido:
- Nivel 1 de compradores (User 85): 9.00€
- Nivel 1 de vendedores (User 77): 9.00€
- Nivel 2 de compradores (User 77): 0.90€
- Nivel 2 de vendedores (User 68): 0.90€
- Nivel 3 de compradores (User 68): 0.90€

**Total**: 5 comisiones MLM adicionales a las 3 principales

### 3. Resto de Niveles
Los niveles 3-10 (o 4-10 según la cadena) debieron ir a Ciudad Virtual (63/11) pero no se registraron todas. Probablemente porque:
- La cadena MLM se agotó
- Ciudad Virtual ya tiene algunas asignadas (User 68 es affiliate 11)

---

## 📈 Distribución Visual

### Snippet Original (Bug) - Total: 90€
```
Pedido: 30€
Comisión calculada: 90€ (300% del pedido!) ❌

Distribución:
├─ Programador:     9.00€ (10%)
├─ Comprador:       9.00€ (10%)
├─ MLM Comprador:
│  ├─ Nivel 1 (85): 9.00€
│  ├─ Nivel 2 (77): 0.90€
│  ├─ Nivel 3 (68): 0.90€
│  └─ Nivel 4-10:   0.90€ × 7 = 6.30€ (no procesados)
├─ MLM Vendedor:
│  ├─ Nivel 1 (77): 9.00€
│  ├─ Nivel 2 (68): 0.90€
│  └─ Nivel 3-10:   0.90€ × 8 = 7.20€ (no procesados)
└─ Empresa:        37.80€

Total registrado: 76.50€ (debería ser 90€ completos)
```

### Plugin Correcto - Total: 3€
```
Pedido: 30€
Comisión calculada: 3€ (10% del pedido) ✅

Distribución:
├─ Programador:     0.30€ (10%)
├─ Comprador:       0.30€ (10%)
├─ MLM Comprador:
│  ├─ Nivel 1 (85): 0.30€
│  ├─ Nivel 2 (77): 0.03€
│  ├─ Nivel 3 (68): 0.03€
│  └─ Nivel 4-10:   0.03€ × 7 = 0.21€
├─ MLM Vendedor:
│  ├─ Nivel 1 (77): 0.30€
│  ├─ Nivel 2 (68): 0.03€
│  └─ Nivel 3-10:   0.03€ × 8 = 0.24€
└─ Empresa:        1.26€

Total a distribuir: 3.00€ ✅
```

---

## 🎯 Conclusiones del Test

### ✅ Verificaciones Exitosas

1. **✓ El snippet SÍ procesa cadenas MLM** cuando existen
2. **✓ Los cálculos del bug coinciden** exactamente con la BD
3. **✓ El sistema maneja correctamente** cuando vendedor es padre MLM del comprador
4. **✓ Las fórmulas son consistentes** en todos los niveles

### ⚠️ Confirmación del Bug

1. **Factor de multiplicación**: 30x (igual a la cantidad de unidades)
2. **Comisión incorrecta**: 90€ en pedido de 30€ (300%)
3. **Comisión correcta sería**: 3€ en pedido de 30€ (10%)

### 💡 Qué Cambiaría con el Plugin

Con el plugin corregido:
- **Vendedor (User 85)** recibiría: 0.30€ (nivel 1 comprador) vs 9.00€ actual
- **User 77** recibiría: 0.33€ (nivel 1 vendedor + nivel 2 comprador) vs 9.90€ actual
- **Ciudad Virtual** recibiría: 1.71€ (empresa + niveles restantes) vs 37.80€+ actual

---

## 📝 Recomendaciones

### Si Activas el Plugin Corregido

1. **Comunicar el cambio** a todos los afiliados
2. **Explicar** que las comisiones anteriores eran por un bug
3. **Ajustar expectativas** sobre montos futuros
4. **Considerar** período de transición
5. **Monitorear** primeras semanas de actividad

### Alternativa

Si las comisiones actuales son **intencionadas** (no un bug):
- Añadir opción de "Modo compatibilidad" al plugin
- Permitir elegir entre cálculo "legacy" y "correcto"
- Documentar claramente la diferencia

---

**Fecha del Test**: 21 de Octubre, 2025  
**Pedido Analizado**: #87076  
**Estado**: ✅ Bug confirmado con pedido real que tiene cadena MLM
**Factor de corrección**: 30x menos (según quantity del producto)

