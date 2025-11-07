# Protección contra Auto-Referencias en Sistema MLM

## Problema Detectado

Durante la auditoría de consistencia de las tablas MLM, se detectó que el **Affiliate ID 2** (`pabloantoniodel`) tenía una auto-referencia: **era su propio padre** en ambas tablas MLM.

### Estado antes de la corrección:
```
affiliate_id: 2
parent_affiliate_id: 2  ← ❌ AUTO-REFERENCIA
```

Esto es un problema crítico porque:
- Rompe la lógica del árbol MLM
- Puede causar bucles infinitos en recorridos del árbol
- Es conceptualmente incorrecto (nadie puede ser su propio sponsor)

---

## Corrección Aplicada

### 1. Corrección de datos existentes
```sql
-- Corregida auto-referencia existente
UPDATE wp_uap_mlm_relations 
SET parent_affiliate_id = 0 
WHERE affiliate_id = 2 AND parent_affiliate_id = 2;

UPDATE wp_cvapp_mlm_relations 
SET parent_affiliate_id = 0 
WHERE affiliate_id = 2 AND parent_affiliate_id = 2;
```

**Resultado**: Affiliate ID 2 ahora tiene `parent_affiliate_id = 0` (root/sin padre)

---

## Protecciones Implementadas en el Código

Para **prevenir futuras auto-referencias**, se añadió validación en todos los puntos del código donde se crean relaciones MLM:

### Archivos modificados:

#### 1. `class-cv-mlm-auto-registration.php`
**Líneas 239-243**: Validación en registro de nuevos usuarios
```php
// VALIDACIÓN: Prevenir auto-referencias (un afiliado no puede ser su propio padre)
if ($affiliate_id == $parent_affiliate_id) {
    error_log('⚠️ CV MLM Custom: PREVENCIÓN AUTO-REFERENCIA - Afiliado ' . $affiliate_id . ' intentó ser su propio padre. Usando padre por defecto (0).');
    $parent_affiliate_id = 0;
}
```

**Líneas 132-138**: Validación en auto-registro de compradores
```php
// VALIDACIÓN: Prevenir auto-referencias
if ($buyer_affiliate_id == $vendor_affiliate_id) {
    if ($this->config['enable_logging']) {
        error_log('CV Commissions MLM: ⚠️ PREVENCIÓN AUTO-REFERENCIA - Comprador ' . $buyer_affiliate_id . ' intentó ser su propio padre (vendor). No se crea relación.');
    }
    return;
}
```

#### 2. `class-cv-affiliate-mlm.php`
**Líneas 116-120**: Validación en creación de afiliados vía referido
```php
// VALIDACIÓN: Prevenir auto-referencias
if ($aff_id == $user_affiliate) {
    error_log('⚠️ CV MLM: PREVENCIÓN AUTO-REFERENCIA - Afiliado ' . $aff_id . ' intentó ser su propio padre. No se crea relación.');
    return;
}
```

**Líneas 151-155**: Validación en caso alternativo (ID padre como parámetro)
```php
// VALIDACIÓN: Prevenir auto-referencias
if ($aff_id == $user_affiliate) {
    error_log('⚠️ CV MLM: PREVENCIÓN AUTO-REFERENCIA - Afiliado ' . $aff_id . ' intentó ser su propio padre. No se crea relación.');
    return;
}
```

#### 3. `tools/process-pending-mlm-users.php`
**Líneas 100-104**: Validación en procesamiento retroactivo de usuarios
```php
// VALIDACIÓN: Prevenir auto-referencias
if ($affiliate_id == $parent_affiliate_id) {
    echo "   ❌ PREVENCIÓN AUTO-REFERENCIA: Afiliado {$affiliate_id} no puede ser su propio padre. Usando padre 0.\n";
    $parent_affiliate_id = 0;
}
```

**Líneas 122-124**: Validación antes de insertar en tabla CV
```php
// VALIDACIÓN FINAL: Prevenir auto-referencias antes de insertar en CV
if ($affiliate_id == $parent_affiliate_id) {
    echo "   ❌ PREVENCIÓN AUTO-REFERENCIA FINAL: No se insertará auto-referencia en CV.\n";
}
```

---

## Scripts de Auditoría Creados

### 1. `verify-mlm-consistency.php`
**Funcionalidad**:
- Verifica sincronización entre tablas UAP y CV
- Detecta duplicados
- Identifica auto-referencias
- Compara padres entre tablas

**Uso**:
```bash
wp eval-file wp-content/plugins/cv-commissions/tools/verify-mlm-consistency.php
```

### 2. `sync-mlm-tables.php`
**Funcionalidad**:
- Limpia duplicados (mantiene padre correcto según UAP)
- Sincroniza relaciones faltantes desde UAP a CV
- Corrige inconsistencias de padres
- Usa UAP como fuente de verdad

**Uso**:
```bash
wp eval-file wp-content/plugins/cv-commissions/tools/sync-mlm-tables.php
```

### 3. `comprehensive-mlm-audit.php`
**Funcionalidad completa**:
- ✅ Sincronización entre tablas
- ✅ Verificación de duplicados
- ✅ Integridad referencial (IDs válidos)
- ✅ Detección de auto-referencias
- ✅ Estadísticas detalladas

**Uso**:
```bash
wp eval-file wp-content/plugins/cv-commissions/tools/comprehensive-mlm-audit.php
```

---

## Resultado de la Auditoría Final

```
📊 RESUMEN FINAL
==================================================
✅ Verificaciones correctas: 10/10
⚠️ Advertencias: 0
❌ Problemas encontrados: 0

🎉 ¡PERFECTO! Todas las verificaciones pasaron correctamente.
Las tablas MLM están completamente sincronizadas y sin errores.
```

### Estadísticas del sistema:
- Total afiliados activos: **821**
- Relaciones MLM: **656** (UAP y CV sincronizadas)
- Duplicados eliminados: **8**
- Relaciones huérfanas eliminadas: **37**
- Auto-referencias corregidas: **1**

---

## Conclusión

✅ **Sistema MLM totalmente protegido contra auto-referencias**

Ahora, en cualquier punto del código donde se intente crear una relación MLM donde `affiliate_id == parent_affiliate_id`, el sistema:

1. **Detecta** la auto-referencia
2. **Registra** un mensaje de error en el log
3. **Previene** la creación de la relación (o usa padre = 0)
4. **Continúa** la ejecución sin romper el flujo

**Fecha de implementación**: $(date)
**Versión del plugin**: 1.2.4



