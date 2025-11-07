# 🚀 Instrucciones de Instalación - CV Commissions

## 🚨 ADVERTENCIA CRÍTICA - LEE PRIMERO

**⚠️ ESTE PLUGIN CORRIGE UN BUG CRÍTICO DEL SNIPPET ORIGINAL**

El snippet multiplicaba por cantidad dos veces, resultando en comisiones ~40x más altas de lo correcto.

**IMPACTO**:
- ❌ Snippet: En pedido de 78€, repartía 312€ (400% del pedido)
- ✅ Plugin: En pedido de 78€, reparte 7.8€ (10% del pedido)

**LEE `BREAKING-CHANGE-CORRECCION-BUG.md` ANTES DE CONTINUAR**

---

## ✅ Pre-requisitos

Antes de instalar este plugin, asegúrate de tener instalados y activos:

1. ✅ WooCommerce
2. ✅ WCFM (WC Frontend Manager)
3. ✅ WCFM Marketplace (WC Multivendor Marketplace)
4. ✅ Indeed Ultimate Affiliate Pro
5. ⭕ WooCommerce Wallet (opcional, para cashback)

## 📝 Pasos de Instalación

### 1. Verificar Plugins Requeridos

```bash
# En WP-CLI
wp plugin list --status=active
```

Verifica que estén activos todos los plugins requeridos.

### 2. Desactivar el Snippet Original

⚠️ **MUY IMPORTANTE**: Antes de activar el plugin, debes desactivar el **Snippet 24** para evitar conflictos.

**Opción A: Desde Code Snippets**
1. Ir a `Snippets > Todos los Snippets`
2. Buscar "Snippet 24: Calculo el importe para el monedero y piramide de comisionistas"
3. Desactivar el snippet
4. **NO LO BORRES** - Mantenerlo desactivado como backup

**Opción B: Desde Base de Datos**
```sql
UPDATE wp_snippets SET active = 0 WHERE id = 24;
```

### 3. Activar el Plugin

**Opción A: Desde WordPress Admin**
1. Ir a `Plugins > Plugins instalados`
2. Buscar "Ciudad Virtual - Sistema de Comisiones MLM"
3. Click en "Activar"

**Opción B: Desde WP-CLI**
```bash
wp plugin activate cv-commissions
```

### 4. Verificar Activación

El plugin verificará automáticamente las dependencias. Si falta algún plugin requerido, la activación fallará con un mensaje detallado.

### 5. Configurar el Plugin

1. Ir a `CV Comisiones` en el menú de administración
2. Verificar el **Estado de Dependencias** (todo debe estar en verde ✅)
3. Revisar la configuración:

#### Configuraciones Críticas a Revisar:

##### IDs de Usuarios (IMPORTANTE!)
- **User ID Programador**: Por defecto `3` - Cambiar si es necesario
- **Affiliate ID Programador**: Por defecto `2` - Cambiar si es necesario
- **User ID Empresa**: Por defecto `63` - Cambiar si es necesario
- **Affiliate ID Empresa**: Por defecto `11` - Cambiar si es necesario

Para obtener estos IDs:
```sql
-- User IDs
SELECT ID, user_login, user_email FROM wp_users WHERE user_login IN ('programador', 'ciudadvirtual');

-- Affiliate IDs
SELECT id, uid FROM wp_uap_affiliates WHERE uid IN (3, 63);
```

##### Porcentajes
- **Comisión Programador**: Por defecto `2%`
- **Comisión Comprador**: Por defecto `10%`
- **Cashback**: Por defecto `10%`

##### Producto Especial
- **Product ID**: Por defecto `4379` (Ticket)
- **Comisión Especial**: Por defecto `90%`

Para verificar el ID del producto ticket:
```sql
SELECT ID, post_title FROM wp_posts WHERE post_type = 'product' AND post_title LIKE '%ticket%';
```

##### Firebase
- **Habilitar**: Activar checkbox si quieres notificaciones push
- **API Key**: Tu Server Key de Firebase
- **URL Cloud Function**: URL de tu función para obtener tokens

4. Click en **💾 Guardar Configuración**

### 6. Activar Logging (Recomendado para inicio)

1. En la configuración del plugin, activar:
   - ✅ **Habilitar Logging**
   - ✅ **Modo Debug** (opcional, para más detalles)

2. En `wp-config.php`, asegurar que esté activo el debug log:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### 7. Probar con un Pedido de Prueba

1. Crear un pedido de prueba en WooCommerce
2. Completar el pedido
3. Verificar en los logs: `wp-content/debug.log`

Deberías ver algo como:
```
🎯 CV Commissions: Procesando comisiones para pedido #1234
CV Commissions: Comisiones calculadas: ...
✅ CV Commissions: Pedido #1234 procesado exitosamente
```

4. Verificar en Indeed Affiliate Pro:
   - Ir a `Ultimate Affiliate Pro > Referrals`
   - Buscar referidos con referencia al pedido
   - Deberían aparecer múltiples entradas para el pedido

### 8. Verificar Comisiones en Base de Datos

```sql
-- Ver comisiones del último pedido
SELECT * FROM wp_uap_referrals 
WHERE reference = '1234' 
ORDER BY id DESC;
```

Deberías ver:
- 1 entrada para el programador
- 1 entrada para el comprador
- 1 entrada para la empresa
- Múltiples entradas para la pirámide MLM (hasta 20: 10 compradores + 10 vendedores)

## 🔄 Migración desde Snippet

### Ventajas del Plugin vs Snippet

| Aspecto | Snippet | Plugin |
|---------|---------|--------|
| Configuración | Hardcodeada | Interfaz admin |
| IDs | Fijos en código | Configurables |
| Porcentajes | Fijos en código | Configurables |
| Dependencias | No verifica | Verifica al activar |
| Logging | Mínimo | Completo y configurable |
| Mantenimiento | Difícil | Fácil |
| Actualización | Manual | Por WordPress |

### Compatibilidad

El plugin es 100% compatible con el snippet original. Usa exactamente la misma lógica de cálculo y distribución.

## 🐛 Solución de Problemas

### Plugin no se activa

**Error**: "Este plugin requiere los siguientes plugins activos..."

**Solución**: Activar todos los plugins requeridos antes de activar CV Commissions.

### No se crean comisiones

1. **Verificar logs**: Revisar `wp-content/debug.log`
2. **Verificar hook**: Asegurar que el hook `wcfmmp_order_processed` se ejecuta
3. **Verificar pedido**: Confirmar que el pedido tiene productos de vendedores WCFM

### Comisiones incorrectas

1. **Verificar configuración**: Revisar porcentajes en `CV Comisiones`
2. **Verificar IDs**: Confirmar que los IDs de programador/empresa son correctos
3. **Verificar logs**: Buscar mensajes de error en debug.log

### Firebase no envía notificaciones

1. **Verificar habilitado**: Checkbox de Firebase debe estar activo
2. **Verificar API Key**: Debe ser el Server Key correcto
3. **Verificar URL**: Cloud Function debe estar accesible
4. **Verificar logs**: Buscar mensajes de Firebase en debug.log

## 📊 Monitoreo Post-Instalación

### Primeros 7 días

- [ ] Verificar logs diariamente
- [ ] Revisar comisiones en Indeed Affiliate Pro
- [ ] Confirmar que todos los pedidos generan comisiones
- [ ] Verificar que las notificaciones Firebase funcionan
- [ ] Comprobar que no hay errores PHP

### Después de 7 días

- [ ] Desactivar "Modo Debug" si todo funciona bien
- [ ] Mantener "Habilitar Logging" activo (bajo impacto)
- [ ] Revisar logs semanalmente
- [ ] Considerar borrar el Snippet 24 (después de confirmar que todo funciona)

## 🔄 Rollback (Volver al Snippet)

Si necesitas volver al snippet original:

1. Desactivar el plugin CV Commissions
2. Reactivar el Snippet 24 en Code Snippets
3. Las comisiones volverán a funcionar como antes

**No hay pérdida de datos** - Todos los referidos guardados permanecen en la base de datos.

## 📞 Soporte

Si tienes problemas:

1. Revisar este documento
2. Revisar `ANALISIS-DEPENDENCIAS.md`
3. Revisar `README.md`
4. Activar logging y revisar logs
5. Contactar soporte: soporte@ciudadvirtual.app

## ✅ Checklist Final

Antes de considerar la instalación completa:

- [ ] Todos los plugins requeridos están activos
- [ ] Snippet 24 está desactivado
- [ ] Plugin CV Commissions está activo
- [ ] Estado de dependencias todo en verde
- [ ] Configuración revisada y ajustada
- [ ] Logging activado
- [ ] Pedido de prueba procesado correctamente
- [ ] Comisiones verificadas en Indeed Affiliate Pro
- [ ] Notificaciones Firebase funcionando (si está habilitado)
- [ ] No hay errores en debug.log

¡Felicidades! 🎉 El plugin está correctamente instalado y funcionando.

