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

**Progreso**: 6 de 9 archivos CSS migrados · 5 de 5 snippets PHP migrados · ~46.7 KB de CSS modular activo

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
