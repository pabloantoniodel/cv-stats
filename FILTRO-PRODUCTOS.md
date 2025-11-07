# 🔒 Filtro de Productos Internos

## Versión 1.0.1

---

## 📋 Descripción

Este módulo oculta automáticamente productos específicos del catálogo público, búsquedas y listados, manteniéndolos disponibles solo para uso interno del sistema.

### Productos Ocultos por Defecto:

1. **`ticket-de-compra`** - Producto interno para procesamiento de pagos
2. **`ticket-compra`** - Variante del anterior
3. **`wallet-topup`** - Producto de recarga de wallet

---

## 🎯 Funcionalidad

### ✅ Dónde se Ocultan los Productos:

- **Catálogo principal** (`/shop/`)
- **Páginas de categorías** (`/product-cat/...`)
- **Páginas de etiquetas** (`/product-tag/...`)
- **Resultados de búsqueda**
- **Widgets de WooCommerce** (productos destacados, recientes, etc.)
- **Shortcodes de WooCommerce** (`[products]`, `[recent_products]`, etc.)
- **Tiendas de vendedores WCFM** (`/store/vendedor/`)
- **Listados de productos WCFM**

### ❌ Dónde NO se Ocultan (Uso Interno):

- **Procesos de checkout** (pueden seguir comprándose programáticamente)
- **API de WooCommerce** (accesibles por REST API)
- **Backend de WordPress** (Admin puede ver/editar)
- **Carritos guardados** (si ya estaban añadidos)
- **Enlaces directos al producto** (URL directa sigue funcionando)

---

## 🔧 Uso

### Añadir Productos a la Lista de Ocultos

```php
// En tu tema o plugin
CV_Product_Filters::add_hidden_product('mi-producto-interno');
```

### Remover Productos de la Lista

```php
CV_Product_Filters::remove_hidden_product('ticket-de-compra');
```

### Verificar si un Producto está Oculto

```php
// Por slug
if (CV_Product_Filters::is_product_hidden('ticket-de-compra')) {
    // Producto está oculto
}

// Por ID
if (CV_Product_Filters::is_product_hidden(123)) {
    // Producto está oculto
}
```

### Obtener Lista de Productos Ocultos

```php
$hidden_products = CV_Product_Filters::get_hidden_products_list();
// Returns: ['ticket-de-compra', 'ticket-compra', 'wallet-topup']
```

---

## 🎨 Filtros y Hooks

### Filtro: `cv_hidden_products_list`

Permite añadir o modificar la lista de productos ocultos:

```php
add_filter('cv_hidden_products_list', function($hidden_products) {
    // Añadir más productos
    $hidden_products[] = 'producto-secreto';
    $hidden_products[] = 'producto-beta';
    
    return $hidden_products;
});
```

### Filtro: `cv_hidden_products`

Permite modificar la lista de productos ocultos dinámicamente:

```php
add_filter('cv_hidden_products', function($hidden_products) {
    // Remover un producto de la lista
    $key = array_search('wallet-topup', $hidden_products);
    if ($key !== false) {
        unset($hidden_products[$key]);
    }
    
    return array_values($hidden_products);
});
```

---

## 💡 Ejemplos de Uso

### Ejemplo 1: Ocultar Productos de Prueba

```php
// En functions.php del tema o en tu plugin

add_action('init', function() {
    // Ocultar productos de prueba en producción
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        CV_Product_Filters::add_hidden_product('producto-test-1');
        CV_Product_Filters::add_hidden_product('producto-test-2');
    }
});
```

### Ejemplo 2: Ocultar Productos por Categoría

```php
add_filter('cv_hidden_products_list', function($hidden_products) {
    // Obtener todos los productos de la categoría "internos"
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'tax_query' => array(
            array(
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => 'internos'
            )
        ),
        'fields' => 'ids'
    );
    
    $internal_products = get_posts($args);
    
    foreach ($internal_products as $product_id) {
        $product = get_post($product_id);
        $hidden_products[] = $product->post_name;
    }
    
    return $hidden_products;
});
```

### Ejemplo 3: Mostrar Productos Ocultos Solo para Admins

```php
add_filter('cv_hidden_products_list', function($hidden_products) {
    // Si el usuario es administrador, no ocultar nada
    if (current_user_can('manage_options')) {
        return array();
    }
    
    return $hidden_products;
});
```

---

## 🔍 Depuración

### Ver Qué Productos se Están Ocultando

El filtro registra en el log cada vez que oculta productos:

```
CV Product Filters: Ocultados 3 productos del catálogo
```

Para ver estos logs:

```bash
tail -f /wp-content/debug.log | grep "CV Product Filters"
```

### Comprobar Manualmente

```php
// En tu tema o plugin de pruebas
add_action('wp_footer', function() {
    if (current_user_can('manage_options')) {
        $hidden = CV_Product_Filters::get_hidden_products_list();
        echo '<div style="position:fixed; bottom:0; right:0; background:#000; color:#fff; padding:10px; z-index:9999;">';
        echo '<strong>Productos ocultos:</strong><br>';
        echo implode(', ', $hidden);
        echo '</div>';
    }
});
```

---

## 🚨 Advertencias Importantes

### ⚠️ No Borrar Productos Ocultos

Los productos ocultos **NO deben borrarse de la base de datos**. El sistema los necesita para funcionar correctamente.

### ⚠️ URLs Directas Siguen Funcionando

Si alguien tiene el enlace directo al producto oculto, podrá acceder a él. Para bloquear completamente el acceso:

```php
add_action('template_redirect', function() {
    if (is_product()) {
        global $post;
        if (CV_Product_Filters::is_product_hidden($post->ID)) {
            // Redirigir a la tienda
            wp_redirect(home_url('/shop/'));
            exit;
        }
    }
});
```

### ⚠️ Productos en Carrito

Si un producto ya está en el carrito cuando se oculta, seguirá ahí. Para limpiarlo:

```php
add_action('woocommerce_before_cart', function() {
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        if (CV_Product_Filters::is_product_hidden($cart_item['product_id'])) {
            WC()->cart->remove_cart_item($cart_item_key);
        }
    }
});
```

---

## 🧪 Testing

### Test 1: Verificar Ocultación en Shop

1. Ve a `/shop/`
2. Busca "ticket de compra"
3. ✅ No debe aparecer en el listado

### Test 2: Verificar Búsqueda

1. Usa el buscador del sitio
2. Busca "ticket de compra"
3. ✅ No debe aparecer en resultados

### Test 3: Verificar URL Directa

1. Ve directamente a `/product/ticket-de-compra/`
2. ⚠️ Debe cargar (a menos que hayas bloqueado con `template_redirect`)

### Test 4: Verificar API

```bash
# El producto debe seguir disponible por API
curl -X GET "https://tudominio.com/wp-json/wc/v3/products?slug=ticket-de-compra" \
  -u consumer_key:consumer_secret
```

---

## 📊 Rendimiento

### Impacto en Rendimiento

- **Mínimo**: Solo añade 1 query SQL simple
- **Cacheable**: Los IDs de productos ocultos se pueden cachear
- **Optimizado**: Usa índices de base de datos

### Optimización con Cache

```php
add_filter('cv_hidden_products_list', function($hidden_products) {
    // Cachear IDs de productos ocultos por 24 horas
    $cache_key = 'cv_hidden_product_ids';
    $cached_ids = wp_cache_get($cache_key);
    
    if ($cached_ids === false) {
        // Calcular IDs...
        wp_cache_set($cache_key, $cached_ids, '', DAY_IN_SECONDS);
    }
    
    return $hidden_products;
});
```

---

## 📝 Changelog

### 1.0.1 (2025-10-21)
- ✨ Implementación inicial
- ✅ Oculta `ticket-de-compra` del catálogo
- ✅ Oculta `wallet-topup` del catálogo
- ✅ Filtros para shop, búsquedas, widgets y shortcodes
- ✅ Integración con WCFM
- ✅ Métodos estáticos para gestión de lista

---

## 🤝 Soporte

Para añadir más productos a la lista de ocultos o reportar problemas:

**Email**: soporte@ciudadvirtual.app  
**Documentación**: `/wp-content/plugins/cv-commissions/FILTRO-PRODUCTOS.md`

---

## 📄 Licencia

GPL v2 or later

---

**Desarrollado para**: Ciudad Virtual Marketplace  
**Versión**: 1.0.1  
**Fecha**: Octubre 2025












