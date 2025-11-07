# 🔄 Herramienta de Recálculo de Comisiones

## 📋 Descripción

Herramienta administrativa que recalcula todas las comisiones existentes en `uap_referrals` usando la lógica actual del plugin `CV_Commissions`.

---

## 🎯 Casos de Uso

### ¿Cuándo usar esta herramienta?

- ✅ Después de cambiar los porcentajes de comisión en la configuración
- ✅ Si se detectan inconsistencias en comisiones antiguas
- ✅ Después de corregir bugs en el cálculo de comisiones
- ✅ Para actualizar comisiones calculadas con lógica antigua
- ✅ Migración de datos de otro sistema

### ⚠️ Advertencias

- 🔴 **HACE CAMBIOS EN LA BASE DE DATOS**: Esta herramienta modifica directamente los registros de comisiones
- 🟡 **BACKUP RECOMENDADO**: Siempre haz un backup antes de ejecutar en modo REAL
- 🟢 **USA SIMULACIÓN PRIMERO**: Ejecuta en modo dry_run para ver qué cambiaría

---

## 🚀 Acceso

### Opción 1: Desde el Admin de WordPress

1. Ve a **Dashboard** → **CV Comisiones** → **Herramientas**
2. Click en uno de los botones:
   - 🧪 **Simular 10 Registros** - Prueba rápida
   - 🔍 **Simular Todos** - Ver todos los cambios sin aplicarlos
   - 🚀 **Ejecutar REAL** - Aplicar cambios realmente

### Opción 2: URL Directa

```
https://ciudadvirtual.app/wp-content/plugins/cv-commissions/tools/recalculate-commissions.php
```

**Parámetros URL:**
- `?dry_run=1` - Modo simulación (no guarda cambios)
- `?limit=10` - Procesar solo 10 registros
- Sin parámetros = Modo REAL (guarda cambios)

---

## 📊 Funcionalidades

### 1. **Modo Simulación (Dry Run)**

```
?dry_run=1
```

- ✅ Muestra qué cambiaría
- ✅ NO modifica la base de datos
- ✅ Ideal para verificar antes de ejecutar

### 2. **Modo Real**

```
(sin parámetros)
```

- ✅ Actualiza los registros en `uap_referrals`
- ✅ Guarda log completo de cambios
- ✅ Muestra estadísticas finales

### 3. **Límite de Registros**

```
?limit=10
```

- ✅ Procesa solo N registros
- ✅ Útil para pruebas
- ✅ Combinable con dry_run

---

## 🗄️ Proceso de Recálculo

### Paso 1: Obtener Registros

```sql
SELECT * FROM wp_uap_referrals 
WHERE reference IS NOT NULL 
AND reference != '' 
AND reference REGEXP '^[0-9]+$'
```

### Paso 2: Para Cada Registro

1. **Extraer order_id** del campo `reference`
2. **Ejecutar** `calculate_order_commissions($order_id)`
3. **Detectar tipo** de comisión por `reference_details`:
   - "programador" → `commissions['programador']`
   - "comprador" → `commissions['comprador']`
   - "empresa" → `commissions['empresa']`
   - "MLM nivel X" → `commissions['comisionstas'][X-1]`
4. **Comparar** monto actual vs calculado
5. **Actualizar** si hay diferencia > €0.01

### Paso 3: Guardar Log

```json
{
  "timestamp": "2025-10-22 14:30:00",
  "stats": {
    "total": 150,
    "changed": 45,
    "unchanged": 100,
    "errors": 5
  },
  "changes": [
    {
      "id": 123,
      "order_id": 154561,
      "description": "Parte comprador pedido 154561",
      "old_amount": 10.50,
      "new_amount": 12.30,
      "difference": 1.80
    }
  ]
}
```

---

## 📊 Información Mostrada

### Tabla de Resultados

| Columna | Descripción |
|---------|-------------|
| **ID** | ID del registro en uap_referrals |
| **Pedido** | Link al pedido en WordPress |
| **Descripción** | Tipo de comisión |
| **Monto Actual** | Valor actual en BD |
| **Monto Recalculado** | Valor calculado con plugin |
| **Diferencia** | Cambio (verde +, rojo -) |
| **Estado** | ✅ Actualizado / ➖ Sin cambio / ❌ Error |

### Tarjetas de Estadísticas

- **Total Procesados**: Cantidad de registros procesados
- **Modificados**: Registros que cambiaron (fondo amarillo)
- **Sin Cambios**: Registros que no cambiaron
- **Errores**: Registros que fallaron

### Resumen Financiero

- **Total Anterior**: Suma de montos antes del recálculo
- **Total Nuevo**: Suma de montos después del recálculo
- **Diferencia**: Impacto total del recálculo
- **Tiempo de ejecución**: Duración del proceso

---

## 🎨 Interfaz

### Barra de Progreso

```
[████████████████░░░░] 80%
```

Se actualiza cada 10 registros procesados.

### Filas Resaltadas

- 🟡 **Fondo amarillo** = Registro que cambió
- ⚪ **Fondo blanco** = Sin cambios
- 🔴 **Fondo rojo** = Error

### Estados

- ✅ **ACTUALIZADO** (verde) - Guardado correctamente
- ⚠️ **CAMBIARÍA** (naranja) - En dry_run
- ➖ **Sin cambio** (gris) - No hubo diferencia
- ❌ **ERROR** (rojo) - Falló el proceso

---

## 🔧 Detección de Tipo de Comisión

### Por `reference_details`:

| Texto | Tipo | Campo usado |
|-------|------|-------------|
| "programador" | Programador | `commissions['programador']` |
| "comprador" (sin MLM) | Comprador | `commissions['comprador']` |
| "empresa" | Empresa | `commissions['empresa']` |
| "MLM comprador nivel 1" | MLM Comprador | `commissions['comisionstas'][0]['comprador']['total']` |
| "MLM vendedor nivel 2" | MLM Vendedor | `commissions['comisionstas'][1]['vendedor']['total']` |

### Regex para Niveles MLM:

```php
preg_match('/nivel (\d+)/i', $reference_details, $matches)
$level = intval($matches[1]) - 1; // Nivel 1 = index 0
```

---

## 📁 Logs Generados

### Ubicación:
```
wp-content/plugins/cv-commissions/logs/recalculation-YYYY-MM-DD-HH-II-SS.json
```

### Estructura:
```json
{
  "timestamp": "2025-10-22 14:30:00",
  "stats": {
    "total": 150,
    "processed": 150,
    "changed": 45,
    "unchanged": 100,
    "errors": 5,
    "old_total": 1234.56,
    "new_total": 1345.67,
    "difference": 111.11
  },
  "changes": [...]
}
```

### .gitignore:

Los logs **NO se suben a GitHub** (están en .gitignore).

---

## ⚡ Ejemplos de Uso

### Ejemplo 1: Simulación de 10 Registros

```
URL: .../recalculate-commissions.php?dry_run=1&limit=10

Resultado:
- Procesa 10 registros
- Muestra qué cambiaría
- NO guarda cambios
- Tiempo: ~2 segundos
```

### Ejemplo 2: Simulación Completa

```
URL: .../recalculate-commissions.php?dry_run=1

Resultado:
- Procesa TODOS los registros
- Muestra estadísticas completas
- NO guarda cambios
- Tiempo: ~30-60 segundos (depende cantidad)
```

### Ejemplo 3: Ejecución Real

```
URL: .../recalculate-commissions.php

Resultado:
- Procesa TODOS los registros
- ACTUALIZA la base de datos
- Guarda log en JSON
- Tiempo: ~30-60 segundos
```

---

## 🐛 Troubleshooting

### Error: "No tienes permisos"
- **Causa**: No eres administrador
- **Solución**: Accede con una cuenta de administrador

### Error al calcular comisión
- **Causa**: El pedido no existe o está corrupto
- **Solución**: Se marca como error y se salta

### Timeout en navegador
- **Causa**: Demasiados registros
- **Solución**: Usa `?limit=100` para procesar en lotes

### Diferencias muy grandes
- **Causa**: La configuración cambió mucho
- **Solución**: Revisa la configuración actual vs la que se usó originalmente

---

## ✅ Verificación Post-Recálculo

### 1. Revisar el Log

```bash
cat wp-content/plugins/cv-commissions/logs/recalculation-*.json
```

### 2. Verificar Totales

```sql
SELECT 
    COUNT(*) as total,
    SUM(amount) as sum_amount
FROM wp_uap_referrals
WHERE reference IS NOT NULL;
```

### 3. Comparar con WCFM

```sql
SELECT 
    vendor_id,
    SUM(credit) as credits,
    SUM(debit) as debits
FROM wp_wcfm_marketplace_vendor_ledger
GROUP BY vendor_id;
```

---

## 📝 Changelog

### Versión 1.0.4
- ✅ Implementación inicial
- ✅ Modo dry_run y real
- ✅ Límite de registros
- ✅ Logs en JSON
- ✅ Barra de progreso
- ✅ Estadísticas completas
- ✅ Detección automática de tipo de comisión
- ✅ Integración en admin de WordPress

---

## 🚀 Próximas Mejoras

- [ ] Recalcular también WCFM Ledger
- [ ] Backup automático antes de ejecutar
- [ ] Rollback de cambios
- [ ] Filtros por fecha
- [ ] Filtros por vendedor
- [ ] Exportar reporte PDF
- [ ] Programar recálculo automático
- [ ] API REST para integración





