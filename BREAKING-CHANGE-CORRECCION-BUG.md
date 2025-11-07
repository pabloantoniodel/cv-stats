# 🚨 BREAKING CHANGE - Corrección de Bug Crítico

## ⚠️ ADVERTENCIA IMPORTANTE

Este plugin **CORRIGE UN BUG CRÍTICO** del snippet original que causaba que las comisiones se calcularan **incorrectamente** (multiplicando por cantidad dos veces).

---

## 🐛 El Bug del Snippet Original

### ❌ Cálculo Incorrecto (Snippet Original)

```php
// Para pedidos:
$price = $item['subtotal'];  // Ya incluye quantity (ej: 40 × 1.95 = 78€)
$quantity = 40;

$s_comision = ($quantity × $price) - (($quantity × $price × 90) / 100);
// = (40 × 78) - ((40 × 78 × 90) / 100)
// = 3120 - 2808 = 312€  ❌ INCORRECTO
```

**Resultado**: En un pedido de 78€, se repartían 312€ en comisiones ❌

### ✅ Cálculo Correcto (Plugin)

```php
// Para pedidos:
$price = $_product->get_price();  // Precio unitario (1.95€)
$quantity = 40;

$s_comision = ($quantity × $price) - (($quantity × $price × 90) / 100);
// = (40 × 1.95) - ((40 × 1.95 × 90) / 100)
// = 78 - 70.2 = 7.8€  ✅ CORRECTO
```

**Resultado**: En un pedido de 78€, se reparten 7.8€ en comisiones ✅

---

## 📊 Impacto del Cambio

### Ejemplo Real: Pedido #154275

| Concepto | Snippet Original (BUG) | Plugin (CORRECTO) | Diferencia |
|----------|------------------------|-------------------|------------|
| **Total pedido** | 78.00€ | 78.00€ | - |
| **Comisión marketplace** | 312.00€ ❌ | 7.80€ ✅ | **40x menos** |
| **Programador** | 31.20€ ❌ | 0.78€ ✅ | **40x menos** |
| **Comprador** | 31.20€ ❌ | 0.78€ ✅ | **40x menos** |
| **Empresa** | 131.04€ ❌ | 3.28€ ✅ | **40x menos** |
| **Cada nivel MLM** | 3.12€ ❌ | 0.078€ ✅ | **40x menos** |

### 🔢 Fórmula del Factor de Reducción

```
Factor = Quantity del producto más común
```

En el ejemplo: 40 unidades → Factor de reducción = 40x

**Productos con diferentes quantities tendrán diferentes factores de reducción**.

---

## 💰 Impacto Financiero

### Si estabas vendiendo con el bug activo:

1. **Has estado pagando ~40x más** en comisiones de lo que debías
2. Las comisiones venían de:
   - ¿Tu margen de ganancia?
   - ¿Fondos externos?
   - ¿Se registraban pero no se pagaban?

### Con el plugin corregido:

1. ✅ **Pagarás el porcentaje correcto** de comisiones
2. ✅ **Sostenible financieramente**
3. ✅ **Matemáticamente correcto**

---

## 🔍 ¿Por Qué Se Produjo Este Bug?

El snippet original tiene lógica diferente para **carrito** vs **pedido**:

### Para CARRITO (correcto):
```php
$price = get_post_meta($product_id, '_price', true);  // Precio UNITARIO
$quantity = $item['quantity'];
// Correcto: 40 × 1.95 = 78€
```

### Para PEDIDO (incorrecto):
```php
$price = $item['subtotal'];  // YA incluye quantity (78€)
$quantity = $item['quantity'];  // 40
// Incorrecto: 40 × 78 = 3120€  ❌ MULTIPLICA 2 VECES
```

---

## ✅ Corrección Implementada

El plugin ahora usa **precio unitario en ambos casos**:

```php
if ($is_cart) {
    $price = get_post_meta($product_id, '_price', true);  // Unitario
} else {
    $price = $_product->get_price();  // Unitario ✅ CORREGIDO
}
```

---

## 📋 Checklist de Migración

### Antes de Activar el Plugin

- [ ] **Entender** que las comisiones serán ~40x menores (correcto)
- [ ] **Revisar** el modelo de negocio con comisiones correctas
- [ ] **Decidir** sobre comisiones pasadas (¿recalcular? ¿compensar?)
- [ ] **Comunicar** el cambio a todos los afiliados/vendedores

### Al Activar el Plugin

- [ ] **Desactivar** el Snippet 24
- [ ] **Activar** el plugin CV Commissions
- [ ] **Configurar** los porcentajes deseados
- [ ] **Hacer** un pedido de prueba
- [ ] **Verificar** que las comisiones son correctas

### Después de Activar

- [ ] **Monitorear** los primeros pedidos
- [ ] **Verificar** cálculos en Indeed Affiliate Pro
- [ ] **Ajustar** porcentajes si es necesario
- [ ] **Documentar** el cambio para contabilidad

---

## 🎯 Opciones de Comisiones Ajustadas

Si quieres mantener montos similares a antes (no recomendado pero posible):

### Opción 1: Aumentar el porcentaje de comisión del marketplace

```
Antes: 10% marketplace → Bug calculaba 312€ en pedido de 78€
Ahora: Si quieres mantener ~312€, necesitarías:
       312€ ÷ 78€ = 400% ❌ IMPOSIBLE
```

**No es viable** mantener los montos anteriores con cálculo correcto.

### Opción 2: Aceptar comisiones correctas

```
Pedido: 78€
Marketplace: 10% = 7.8€
De eso, repartir:
  - Programador: 0.78€ (10%)
  - Comprador: 0.78€ (10%)  
  - Empresa: 3.28€ (resto)
  - MLM: 0.078€ × nivel
```

**Recomendado**: Usar porcentajes correctos y sostenibles.

---

## 📚 Documentación Relacionada

- `TEST-PEDIDO-REAL.md` - Análisis del pedido con bug
- `REVISION-CALCULOS.md` - Verificación de fórmulas
- `README.md` - Documentación general

---

## ❓ FAQ

### ¿Por qué no mantener el bug para compatibilidad?

- ❌ **No sostenible**: Pagas 40x más de lo debido
- ❌ **Matemáticamente incorrecto**: 312€ de comisiones en venta de 78€
- ❌ **No escalable**: A mayor volumen, más pérdidas

### ¿Qué hago con las comisiones ya pagadas?

Opciones:
1. **Ignorar**: Considerar como "inversión en arranque"
2. **Ajustar futuros**: Compensar en próximos pagos
3. **Recalcular**: Solo si tienes registro detallado

### ¿Puedo ajustar los porcentajes?

✅ **Sí**, todos los porcentajes son configurables en:
- CV Comisiones → Configuración
- Puedes subir porcentajes, pero mantén lógica correcta

---

## ⚠️ Resumen Ejecutivo

### 🐛 El Problema
Snippet multiplicaba por quantity dos veces → Comisiones 40x más altas

### ✅ La Solución  
Plugin usa precio unitario siempre → Comisiones correctas

### 💡 El Resultado
- Comisiones **matemáticamente correctas**
- Modelo de negocio **financieramente sostenible**
- Cálculos **transparentes y auditables**

---

**Fecha**: 21 de Octubre, 2025  
**Tipo**: BREAKING CHANGE  
**Impacto**: CRÍTICO  
**Acción Requerida**: Revisar modelo de comisiones

