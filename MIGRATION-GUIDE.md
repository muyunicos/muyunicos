# MIGRATION-GUIDE.md
> Guía operativa para migración de Code Snippets → Arquitectura Modular  
> **Uso**: Este archivo es contexto vivo para asistencia de IA y referencia técnica del proyecto.  
> Repositorio: `muyunicos/muyunicos` | Tema: GeneratePress Child

---

## 0. Estado de la Migración

### Archivos CSS

| Archivo | Tipo | Tamaño | Estado |
|---|---|---|---|
| `css/components/header.css` | Componente | 9.4 KB | ✅ Migrado |
| `css/components/footer.css` | Componente | 8.0 KB | ✅ Migrado |
| `css/components/modal-auth.css` | Componente | 8.3 KB | ✅ Migrado |
| `css/components/share-button.css` | Componente | 1.4 KB | ✅ Migrado |
| `css/cart.css` | Página | 9.9 KB | ✅ Migrado |
| `css/checkout.css` | Página | 9.7 KB | ✅ Migrado |
| `css/home.css` | Página | 0 KB | 📋 Pendiente (placeholder creado) |
| `css/product.css` | Página | 0 KB | 📋 Pendiente (placeholder creado) |
| `css/shop.css` | Página | — | ❌ Archivo no creado aún |

### Snippets PHP → `functions.php`

| Snippet / Funcionalidad | Función(es) en `functions.php` | Estado |
|---|---|---|
| Google Site Kit: canonical home URL | `mu_googlesitekit_canonical_home_url()` | ✅ Migrado |
| Botón Compartir HTML + shortcode `[dcms_share]` | `dcms_render_share_button()` | ✅ Migrado |
| WooCommerce: agregar múltiples productos por URL | `woo_add_multiple_products_to_cart()` | ✅ Migrado |
| BACS: reemplazar NUMERODEPEDIDO en página y emails | `bacs_buffer_start/end`, `bacs_email_buffer_start/end` | ✅ Migrado |
| WooCommerce: mover descripción de categoría | `muyunicos_move_category_description()` | ✅ Migrado |
| Sistema Restricción de Contenido Digital v2.2 | `MUYU_Digital_Restriction_System` (Singleton) | ✅ Migrado |

**Progreso**: 6 de 9 archivos CSS migrados · 6 de 6 snippets PHP migrados · ~46.7 KB de CSS modular activo

---

### Sistema de Restricción Digital — Operación y Configuración

> Clase: `MUYU_Digital_Restriction_System` · Archivo: `functions.php` · Patrón: Singleton · Versión: 2.2.0

| Ítem | Valor |
|---|---|
| Clase | `MUYU_Digital_Restriction_System` (protegida con `class_exists`) |
| Inicialización | `muyu_digital_restriction_init()` en hook `plugins_loaded`, prioridad 5 |
| Criterio de restricción | Solo `muyunicos.com` (sin subdominio) ve todos los productos; cualquier subdominio ve únicamente digitales |
| Formato físico (`pa_formato`) | `PHYSICAL_FORMAT_ID = 112` (Imprimible) |
| Formato digital (`pa_formato`) | `DIGITAL_FORMAT_ID = 111` (Digital) |

**Option Keys** (guardadas en `wp_options`, `autoload = false`)

| Option Key | Descripción |
|---|---|
| `muyu_digital_product_ids` | Array de IDs de productos digitales indexados |
| `muyu_digital_category_ids` | Array de IDs de categorías con productos digitales (incluye ancestros) |
| `muyu_digital_tag_ids` | Array de IDs de tags de productos digitales |
| `muyu_phys_to_dig_map` | Mapa `[id_físico => id_digital]` para redirecciones directas vía slug `-imprimible` |
| `muyu_digital_list_updated` | Timestamp del último rebuild (formato datetime MySQL) |

**Admin / Operación**
- Botón **⚡ Reindexar Digitales** disponible en `/wp-admin/edit.php?post_type=product`
- Endpoint AJAX: `action = muyu_rebuild_digital_list` · Nonce: `muyu-rebuild-nonce`
- Rebuild automático: se encola en `shutdown` al guardar/actualizar cualquier producto, protegido por transient `muyu_rebuild_scheduled` (TTL 120 s) para evitar ejecuciones múltiples
- Bootstrap de índices: `ensure_indexes_exist()` en `admin_init` — si la option no existe, lanza rebuild automático

**Funciones de compatibilidad (backward compat)**

| Función | Retorno | Descripción |
|---|---|---|
| `muyu_is_restricted_user()` | `bool` | `true` si el usuario está en un subdominio |
| `muyu_get_user_country_code()` | `string` | Código ISO 3166-1 alpha-2 derivado del subdominio |
| `muyu_rebuild_digital_indexes_optimized()` | `int` | Total de productos digitales indexados |

**Mapeo de subdominios → país**

| Subdominio | Código país |
|---|---|
| `mexico.*` | `MX` |
| `br.*` | `BR` |
| `co.*` | `CO` |
| `ec.*` | `EC` |
| `cl.*` | `CL` |
| `pe.*` | `PE` |
| `ar.*` | `AR` |
| Cualquier 2 letras no listado | uppercase del subdominio |
| Sin subdominio / dominio principal | `AR` (default) |

**Auto-selección de variación `pa_formato`**
- Usuarios restringidos (subdominio extranjero): selecciona Digital (ID 111) y **oculta** la fila del selector de variación
- Argentina (`muyunicos.com`): selecciona Físico/Imprimible (ID 112) y **deja el selector visible**
- PHP: implementado via `woocommerce_product_get_default_attributes` (prioridad 20) + `woocommerce_before_add_to_cart_button` (prioridad 5)
- JS: inyectado via `wc_enqueue_js()` (se encola después de las dependencias de WooCommerce)

**Hooks registrados**

| Hook | Método | Tipo | Prioridad |
|---|---|---|---|
| `wp_ajax_muyu_rebuild_digital_list` | `ajax_rebuild_indexes` | action | — |
| `woocommerce_update_product` | `schedule_rebuild` | action | 10 |
| `admin_init` | `ensure_indexes_exist` | action | 5 |
| `admin_head-edit.php` | `add_rebuild_button` | action | — |
| `pre_get_posts` | `filter_product_queries` | action | 50 |
| `template_redirect` | `handle_redirects` | action | 20 |
| `wp` | `init_frontend_filters` | action | 5 |
| `woocommerce_variation_is_visible` | `hide_physical_variation` | filter | 10 |
| `woocommerce_dropdown_variation_attribute_options_args` | `clean_variation_dropdown` | filter | 10 |
| `woocommerce_variation_prices` | `filter_variation_prices` | filter | 10 |
| `woocommerce_product_get_default_attributes` | `set_format_default` | filter | 20 |
| `woocommerce_before_add_to_cart_button` | `autoselect_format_variation` | action | 5 |
| `get_terms_args` *(via init_frontend_filters)* | `filter_category_terms` | filter | 10 |
| `wp_get_nav_menu_items` *(via init_frontend_filters)* | `filter_menu_items` | filter | 10 |

---

## 1. Arquitectura del Repositorio

### Archivos Raíz

| Archivo | Rol |
|---|---|
| `style.css` | CSS base global, variables CSS, CSS estático global migrado |
| `functions.php` | Sistema de enqueue (`mu_enqueue_assets()`), hooks WC, AJAX handlers, helpers PHP globales |
| `MIGRATION-GUIDE.md` | Este archivo |

### assets/ (tema padre — solo referencia)

> Copia de archivos del tema padre GeneratePress incluida en el repositorio **únicamente como referencia**.  
> **Nunca modificar estos archivos.** No pertenecen al child theme.

| Archivo | Rol |
|---|---|
| `assets/css/main.min.css` | CSS compilado del tema padre GeneratePress. **Solo lectura — referencia anti-duplicación.** |

---

### css/components/
Componentes globales reutilizables, cargados en todas las páginas salvo indicación.

| Archivo | Descripción | Handle | Tamaño |
|---|---|---|---|
| `header.css` | Header global: nav, logo, menú móvil, sticky | `mu-header` | 9.4 KB |
| `footer.css` | Footer global: columnas, social links, legal | `mu-footer` | 8.0 KB |
| `modal-auth.css` | Modal login/registro: layout, animaciones, responsive | `mu-modal-auth`* | 8.3 KB |
| `share-button.css` | Botón compartir: native share + clipboard + tooltip | `mu-share` | 1.4 KB |

*`mu-modal-auth` carga condicional: `!is_user_logged_in()`

---

### css/ (páginas)
Estilos específicos por contexto de página. Carga condicional.

| Archivo | Condición de carga | Tamaño | Estado |
|---|---|---|---|
| `cart.css` | `is_cart()` | 9.9 KB | ✅ Migrado |
| `checkout.css` | `is_checkout()` | 9.7 KB | ✅ Migrado |
| `home.css` | `is_front_page()` | 0 KB | 📋 Pendiente |
| `product.css` | `is_product()` | 0 KB | 📋 Pendiente |
| `shop.css` | `is_shop() \|\| is_product_category()` | — | ❌ No creado |

---

### js/
Scripts del child theme. Cargados en footer.

| Archivo | Descripción | Carga | Dependencias |
|---|---|---|---|
| `header.js` | Menú móvil toggle, sticky header | Footer, defer | Ninguna |
| `footer.js` | Accordion footer mobile | Footer, defer | Ninguna |
| `modal-auth.js` | Auth modal AJAX | Footer, `!is_user_logged_in()` | Ninguna |
| `mu-ui-scripts.js` | UI helpers: Country selector + WPLingua toggle + Share button | Footer, defer | Ninguna |
| `cart.js` | Carrito | Footer, `is_cart()` | `['jquery']` |
| `checkout.js` | Checkout | Footer, `is_checkout()` | `['jquery', 'libphonenumber-js']` |

---

### Sistema de Iconos: `mu_get_icon($name)`
Función en `functions.php` (~línea 120). Devuelve SVG inline.  
**Iconos disponibles**: `arrow`, `search`, `close`, `share`, `check`, `instagram`, `facebook`, `pinterest`, `tiktok`, `youtube`.  
**Siempre usar esta función** — nunca SVG inline directo en templates.

---

## 2. Convenciones del Proyecto

### Anti-duplicación con tema padre
Antes de declarar cualquier regla CSS, verificar si GeneratePress ya la provee en `assets/css/main.min.css`.
Solo sobreescribir cuando sea necesario cambiar el comportamiento base; en ese caso, documentar el override con un comentario `/* override GP: [razón] */`.

### Nomenclatura CSS
- Prefijo universal: `.mu-*`
- Componentes: `.mu-card`, `.mu-btn`, `.mu-badge`, `.mu-modal`
- Utilidades: `.mu-flex`, `.mu-gap-md`, `.mu-mt-lg`
- Estados: `.is-active`, `.is-open`, `.is-loading`

### Variables CSS Globales (definidas en `style.css`)
```css
--primario          /* Color principal de marca */
--mu-radius         /* Border radius estándar */
--mu-shadow-sm      /* Sombra suave */
--mu-space-md       /* Spacing medio */
```
**Siempre usar variables** en lugar de valores hardcoded.

### Reglas de JavaScript
- Siempre envolver en IIFE: `(function() { 'use strict'; ... })()`
- Inicializar con DOMContentLoaded guard (ver plantilla)
- Scripts < 2 KB sin dependencias → consolidar en `mu-ui-scripts.js`
- Scripts > 5 KB o con carga condicional → archivo propio

### Reglas de PHP
- Siempre usar `if ( !function_exists('nombre') )` antes de declarar funciones
- AJAX handlers: usar `wc_ajax_` (WC-AJAX) en lugar de `admin-ajax` cuando sea posible
- CSS condicional mínimo → `wp_add_inline_style()` (excepción justificada)
- CSS de tamaño real → siempre archivo separado

---

## 3. Protocolo de Migración

### Paso 0 — Verificar tema padre
Antes de escribir cualquier CSS nuevo, buscar en `assets/css/main.min.css` si el estilo ya existe.
- Si ya existe → usar/extender la clase del tema padre, no duplicar.
- Si no existe → continuar con el Paso 1.

### Paso 1 — Extraer del Snippet
Identificar si el snippet es global o condicional a una página/rol.

### Paso 2 — Clasificar destino

**CSS:**
- Componente global (visible en todas las páginas) → `css/components/[nombre].css`
- Específico de una página → `css/[pagina].css`
- CSS estático pequeño y global → sección apropiada en `style.css`
- CSS que requiere variables PHP dinámicas → `wp_add_inline_style()` como excepción

**JavaScript:**
- Script pequeño (< 2 KB), sin dependencias → añadir a `mu-ui-scripts.js`
- Script mediano/grande o condicional → `js/[nombre].js` propio

**PHP:**
- Todo va a `functions.php` (hooks, handlers AJAX, helpers, HTML generators)

### Paso 3 — Refactorizar

```
[ ] Reemplazar valores hardcoded con variables CSS
[ ] Eliminar duplicaciones con style.css base
[ ] Usar clases con prefijo .mu-*
[ ] Agrupar @media queries al final del archivo
[ ] Añadir comentario de sección en el encabezado del archivo
[ ] Verificar accesibilidad: contrast, :focus-visible, ARIA
```

### Paso 4 — Registrar en `functions.php`

```php
// $theme_version = wp_get_theme()->get('Version'); // Se obtiene globalmente en mu_enqueue_assets()

// Componente global:
wp_enqueue_style('mu-[nombre]', $theme_uri . '/css/components/[nombre].css', ['mu-base'], $theme_version);

// Página específica:
if (is_front_page()) {
    wp_enqueue_style('mu-home', $theme_uri . '/css/home.css', ['mu-base'], $theme_version);
}

// JavaScript en footer:
wp_enqueue_script('mu-[nombre]', $theme_uri . '/js/[nombre].js', [], $theme_version, true);
```

### Paso 5 — Actualizar este archivo
Actualizar la tabla §0 con el nuevo estado (tamaño real, ✅ Migrado) y registrar
cualquier cambio de arquitectura o nuevo archivo creado.

---

## 4. Plantillas

### CSS Component
```css
/* ========================================
   [NOMBRE] - [DESCRIPCIÓN BREVE]
   ======================================== */

.mu-[componente] {
    --local-spacing: var(--mu-space-md);
    /* override GP: adaptado al diseño de la marca */
    /* estilos base */
}

.mu-[componente]:hover { }
.mu-[componente]:focus-visible { }
.mu-[componente].is-active { }

@media (max-width: 768px) {
    .mu-[componente] { }
}
```

### JavaScript Module
```javascript
/**
 * [Nombre Módulo]
 */
(function() {
    'use strict';

    function init() {
        // lógica principal
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
```

### PHP Helper
```php
if ( !function_exists( 'mu_helper' ) ) {
    /**
     * @param string $param
     * @return mixed
     */
    function mu_helper( $param ) {
        return $result;
    }
}
```

---

> **Nota para IA**: Al recibir un snippet para migrar, seguir el Protocolo §3 en orden.  
> **Paso previo obligatorio**: Verificar en `assets/css/main.min.css` que el CSS a crear no duplique estilos del tema padre.  
> Consultar **§0** para el estado actual antes de crear nuevos archivos.  
> Consultar **§1** para verificar si el archivo destino ya existe antes de crearlo.  
> Consultar **§2** para aplicar convenciones de naming y variables CSS correctas.  
> **Siempre actualizar §0** al finalizar una migración.  
> Los snippets se entregan de a uno; no preguntar cuándo desactivar — eso lo gestiona el usuario.
