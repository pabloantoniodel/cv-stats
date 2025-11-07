# CV MLM Children - Gestor de Descendientes MLM

## 📋 Descripción

La clase `CV_MLM_Children` gestiona la obtención recursiva de todos los descendientes (hijos, nietos, etc.) de un afiliado en una estructura MLM (Marketing Multinivel).

**Migrado desde**: Code Snippet #55 - MLMGetchildren Class  
**Uso actual**: Snippet #26 - "añadir mi RED al menu de usuario"

## 🎯 ¿Para qué sirve?

1. **Obtener toda la red MLM** de un afiliado de forma recursiva
2. **Calcular comisiones** por nivel/rango para cada descendiente
3. **Organizar información** de cada miembro de la red
4. **Navegar por niveles** de profundidad de la pirámide MLM

## 🚀 Uso Básico

### Ejemplo 1: Obtener todos los descendientes

```php
// Obtener todos los descendientes del usuario actual
$user_id = get_current_user_id();
$affiliate_id = $indeed_db->get_affiliate_id_by_wpuid($user_id);

// Instanciar la clase
$mlm = new CV_MLM_Children($affiliate_id);

// Obtener resultados
$descendants = $mlm->get_results();

// Recorrer resultados
foreach ($descendants as $affiliate_id => $data) {
    echo 'Nombre: ' . $data['full_name'] . '<br>';
    echo 'Email: ' . $data['email'] . '<br>';
    echo 'Nivel: ' . $data['level'] . '<br>';
    echo 'Comisión: ' . $data['amount_value'] . '<br>';
    echo '---<br>';
}
```

### Ejemplo 2: Filtrar por nivel específico

```php
$mlm = new CV_MLM_Children($affiliate_id);

// Solo descendientes de nivel 1 (primera línea)
$first_line = $mlm->get_children_by_level(1);

// Solo descendientes de nivel 2
$second_line = $mlm->get_children_by_level(2);
```

### Ejemplo 3: Obtener estadísticas

```php
$mlm = new CV_MLM_Children($affiliate_id);

// Total de descendientes
$total = $mlm->count_children();
echo "Total en red: $total personas";

// Contar por nivel
$counts = $mlm->count_by_level();
foreach ($counts as $level => $count) {
    echo "Nivel $level: $count personas<br>";
}
```

## 📊 Estructura de Datos Retornados

Cada descendiente contiene:

```php
array(
    'id' => 123,                    // ID de afiliado
    'uid' => 456,                   // ID de usuario WordPress
    'username' => 'usuario123',     // Login del usuario
    'email' => 'user@mail.com',     // Email
    'full_name' => 'Juan Pérez',    // Nombre completo
    'level' => 2,                   // Nivel en la red (1, 2, 3...)
    'parent' => 'patrocinador',     // Username del padre directo
    'parent_id' => 789,             // ID del afiliado padre
    'amount_value' => '10 %',       // Comisión configurada
    'avatar' => 'http://...',       // URL del avatar
)
```

## 🔧 Métodos Disponibles

### `__construct($affiliate_id)`
Inicializa la clase y obtiene todos los descendientes recursivamente.

**Parámetros:**
- `$affiliate_id` (int): ID del afiliado padre

### `get_results()`
Retorna array completo con todos los descendientes.

**Return:** `array`

### `get_children_ids()`
Retorna solo los IDs de los descendientes.

**Return:** `array` de integers

### `get_children_by_level($level)`
Filtra descendientes por nivel específico.

**Parámetros:**
- `$level` (int): Número de nivel (1, 2, 3, etc.)

**Return:** `array`

### `count_children()`
Cuenta total de descendientes.

**Return:** `int`

### `count_by_level()`
Cuenta descendientes agrupados por nivel.

**Return:** `array` [1 => 5, 2 => 15, 3 => 30, ...]

## ⚙️ Configuración

La clase usa las siguientes opciones de WordPress:

### Configuración MLM (Ultimate Affiliate Pro)

- `uap_mlm_matrix_depth` - Profundidad máxima de niveles (por defecto: 5)
- `mlm_amount_type_per_level` - Tipos de comisión por nivel
- `mlm_amount_value_per_level` - Valores de comisión por nivel
- `uap_mlm_default_amount_type` - Tipo de comisión por defecto (flat/percentage)
- `uap_mlm_default_amount_value` - Valor de comisión por defecto
- `uap_currency` - Símbolo de moneda

### Tipos de Comisión (Prioridad)

1. **Por Rango de Usuario** - Configuración específica del usuario según su rango
2. **Por Nivel MLM** - Comisión configurada para cada nivel (1, 2, 3, etc.)
3. **General/Default** - Comisión por defecto si no hay configuración específica

## 🔄 Compatibilidad con Snippet Original

La clase incluye un alias para mantener compatibilidad:

```php
// Ambas formas funcionan:
$mlm = new CV_MLM_Children($affiliate_id);      // Nueva forma
$mlm = new MLMGetChildren_2($affiliate_id);     // Forma antigua (compatibilidad)
```

Esto asegura que el **Snippet #26** siga funcionando sin modificaciones.

## 📍 Uso Actual en el Sistema

### Snippet #26: "añadir mi RED al menu de usuario"

**Función:** `menu_mired_2($uid)`

**Código actual:**
```php
$affiliate_id = $indeed_db->get_affiliate_id_by_wpuid($uid);
$mlm = new MLMGetChildren_2($affiliate_id);  // Ahora usa CV_MLM_Children
$children = $mlm->get_results();

foreach ($children as $item) {
    // Mostrar en tabla HTML
    echo '<tr>';
    echo '<td>' . $item['email'] . '</td>';
    echo '<td>' . $item['level'] . '</td>';
    echo '<td>' . $item['phone'] . '</td>';
    // ...
    echo '</tr>';
}
```

**Ubicación:** Menu Usuario → "Tarjeta fidelización"

## 🚨 Requisitos

- **WordPress** 5.0+
- **WooCommerce** 3.0+
- **WCFM** 6.0+
- **Ultimate Affiliate Pro** (Indeed)
- Variable global `$indeed_db` debe estar disponible

## 📝 Logging

La clase incluye logging para debugging:

```
✅ CV_MLM_Children: Procesados 45 descendientes de afiliado #123
⚠️ CV_MLM_Children: Indeed Affiliate Pro no está disponible
```

## 🔍 Ejemplo Completo: Mostrar Red en Tabla

```php
function mostrar_mi_red() {
    global $indeed_db;
    
    $user_id = get_current_user_id();
    $affiliate_id = $indeed_db->get_affiliate_id_by_wpuid($user_id);
    
    // Obtener todos los descendientes
    $mlm = new CV_MLM_Children($affiliate_id);
    $children = $mlm->get_results();
    
    echo '<table class="wp-list-table widefat">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Nombre</th>';
    echo '<th>Email</th>';
    echo '<th>Nivel</th>';
    echo '<th>Comisión</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($children as $child) {
        echo '<tr>';
        echo '<td>' . esc_html($child['full_name']) . '</td>';
        echo '<td>' . esc_html($child['email']) . '</td>';
        echo '<td>' . esc_html($child['level']) . '</td>';
        echo '<td>' . esc_html($child['amount_value']) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    
    // Mostrar estadísticas
    $counts = $mlm->count_by_level();
    echo '<div class="stats">';
    echo '<h3>Estadísticas de tu Red</h3>';
    echo '<p>Total: ' . $mlm->count_children() . ' personas</p>';
    foreach ($counts as $level => $count) {
        echo '<p>Nivel ' . $level . ': ' . $count . ' personas</p>';
    }
    echo '</div>';
}

add_shortcode('mi_red_mlm', 'mostrar_mi_red');
```

## 🎨 Personalización

### Cambiar profundidad máxima

```php
// Aumentar a 10 niveles
update_option('uap_mlm_matrix_depth', 10);
```

### Configurar comisiones por nivel

```php
// Nivel 1: 10%, Nivel 2: 5%, Nivel 3: 2%
update_option('mlm_amount_type_per_level', [1 => 'percentage', 2 => 'percentage', 3 => 'percentage']);
update_option('mlm_amount_value_per_level', [1 => 10, 2 => 5, 3 => 2]);
```

## 🔗 Integración con Sistema de Comisiones

Esta clase se integra perfectamente con el plugin `CV_Commissions`:

```php
// Obtener red MLM
$mlm = new CV_MLM_Children($affiliate_id);

// Usar con el calculador de comisiones
$calculator = new CV_Commission_Calculator();
foreach ($mlm->get_results() as $child) {
    $commission = $calculator->calculate_mlm_commission($child['id'], $order_total);
    // ...
}
```

---

**Versión**: 1.0.0  
**Plugin**: CV Commissions  
**Última actualización**: 2025-10-21












