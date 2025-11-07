# Snippets Integrados en CV Commissions

Este documento lista los snippets que han sido integrados en el plugin `cv-commissions` y pueden ser desactivados.

## ✅ Snippets Completamente Integrados (PUEDEN DESACTIVARSE)

### Snippet #26: "añadir mi RED al menu de usuario"
- **Estado**: ✅ DESACTIVADO
- **Integrado en**: `includes/class-cv-my-network-endpoint.php`
- **Funcionalidad**:
  - Endpoint `/my-account/mired/`
  - Menú "Mi Red" en My Account
  - Vista de sponsor con avatar
  - Navegación jerárquica por niveles (hasta 10 niveles)
  - Avatares de tarjeta de visita digital
  
### Snippet #28: "Clase de formulario afiliados dentro afiliados"
- **Estado**: ⚠️ PENDIENTE DE DESACTIVAR
- **Integrado en**: `includes/class-cv-user-auto-register.php`
- **Funcionalidad**:
  - Hook `user_register` para auto-registro en UAP
  - Asignación automática de rango
  - Creación de relación MLM
  - **NOTA**: La clase completa `UapMainPublic_cvapp` del snippet #28 NO se ha migrado porque ya existe en el core de UAP. Solo se migró el hook `user_register`.

### Snippet #31: "CAPTURA FORMULARIO ADD MI RED Y ADd MI RED QR" (PARCIAL)
- **Estado**: ⚠️ MANTENER ACTIVO (contiene otras funcionalidades)
- **Integrado en**: `includes/class-cv-user-auto-register.php`
- **Funcionalidades integradas**:
  - `add_new_mlm_relation_cvapp()` - Inserción en tabla `wp_cvapp_mlm_relations`
  - `cvapp_delete_affiliate_by_uid()` - Eliminación de usuario del MLM
  - Hook `deleted_user` para limpiar relaciones MLM
  
- **Funcionalidades NO integradas (mantener en snippet)**:
  - `cvapp_category_filter()` - Filtro de categorías de productos
  - `cvapp_vendor()` - Formulario de suscripción a comercios
  - `cvapp_remove_affiliate()` - Eliminar afiliado de la red
  - `handle_request_add_user()` - Captura de formularios add_user_mired
  - `comunadd()` - Lógica compleja de añadir usuarios a la red
  - `add_user_wordpress()` - Creación de usuarios desde formularios
  - `perfil_actualizado()` - Actualización de avatar en Yoast SEO

## ⚠️ Instrucciones de Desactivación

### Para desactivar Snippet #28:
```sql
UPDATE wp_snippets SET active = 0 WHERE id = 28;
```

**IMPORTANTE**: El snippet #28 contiene la clase `UapMainPublic_cvapp` que es una extensión del sistema de afiliados. Solo desactivar si estás seguro de que no se usa en otros lugares.

### Para desactivar Snippet #31:
**NO DESACTIVAR TODAVÍA** - Contiene múltiples funcionalidades críticas que aún no se han migrado.

## 📋 Próximos Pasos

1. ✅ Snippet #26 - COMPLETADO Y DESACTIVADO
2. ⏳ Snippet #28 - MIGRADO (pendiente desactivar)
3. ⏳ Snippet #31 - PARCIALMENTE MIGRADO (requiere más trabajo)

## 🔍 Verificación

Para verificar que el auto-registro funciona:

1. Registrar un nuevo usuario desde `/become-an-affiliate/?ref=USUARIO`
2. Verificar en logs: `CV Auto Register: Afiliado creado`
3. Verificar en BD: `SELECT * FROM wp_cvapp_mlm_relations WHERE affiliate_id = [nuevo_id]`
4. Verificar en UAP: `SELECT * FROM wp_uap_affiliates WHERE uid = [nuevo_uid]`

## 📝 Notas Técnicas

- El auto-registro usa prioridad 150 en el hook `user_register` (igual que el snippet original)
- Se mantiene compatibilidad con cookies de referral (`uap_referral`)
- Los logs incluyen emojis para fácil identificación: 🔄 Inicio, ✅ Éxito, ❌ Error
- Si no hay padre en cookie/UAP, usa affiliate_id 1 como padre por defecto



