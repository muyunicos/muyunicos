# MIGRATION-GUIDE.md
> Guía operativa para migración de Code Snippets → Arquitectura Modular  
> **Uso**: Este archivo es contexto vivo para asistencia de IA y referencia técnica del proyecto.  
> Repositorio: `muyunicos/muyunicos` | Tema: GeneratePress Child

---

## 1. Arquitectura Actual del Repositorio

### Archivos Raíz

| Archivo | Rol |
|---|---|
| `style.css` | CSS base global, variables CSS, CSS estático global migrado |
| `functions.php` | Sistema de enqueue (`mu_enqueue_assets()`), hooks WC, AJAX handlers, helpers PHP globales |
| `MIGRATION-GUIDE.md` | Este archivo |

### css/components/
Componentes globales reutilizables, cargados en todas las páginas salvo indicación.

| Archivo | Descripción | Enqueue Handle |
|---|---|---|
| `header.css` | Header global: nav, logo, menú móvil, sticky | `mu-header` |
| `footer.css` | Footer global: columnas, social links, legal | `mu-footer` |
| `modal-auth.css` | Modal login/registro: layout, animaciones, responsive | `mu-modal-auth` (condicional: `!is_user_logged_in()`) |

### css/ (páginas)
Estilos específicos por contexto de página. Carga condicional.

| Archivo | Condición de carga |
|---|---|
| `shop.css` | `is_shop() \|\| is_product_category()` |
| `product.css` | `is_product()` |
| `cart.css` | `is_cart()` |
| `checkout.css` | `is_checkout()` |
| `home.css` | `is_front_page()` |

### js/
| Archivo | Descripción | Carga |
|---|---|---|
| `header.js` | Menú móvil toggle, sticky header | Footer, defer |
| `footer.js` | Accordion footer mobile | Footer, defer |
| `modal-auth.js` | Auth modal: open/close, AJAX login/register via WC-AJAX | Footer, condicional `!is_user_logged_in()` |
| `mu-ui-scripts.js` | WhatsApp flotante, Search toggle, Country selector | Footer, defer |
| `cart.js` | Carrito (JS listo, CSS pendiente) | Footer, `is_cart()` |
| `checkout.js` | Checkout (JS listo, CSS pendiente) | Footer, `is_checkout()` |

### Sistema de Iconos: `mu_get_icon($name)`
Función en `functions.php` (~línea 120). Devuelve SVG inline.  
Iconos disponibles: `arrow`, `search`, `close`, `instagram`, `facebook`, `tiktok`, `youtube`, `pinterest`, ...  
**Siempre usar esta función** — nunca SVG inline directo en templates.

---

## 2. Convenciones del Proyecto

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

### Paso 1 — Extraer del Snippet Dado
Identificar si el snippet es global o condicional a una página/rol

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
Reemplazar valores hardcoded con variables CSS
Eliminar duplicaciones con style.css base
Usar clases con prefijo .mu-*
Agrupar @media queries al final del archivo
Añadir comentario de sección en el encabezado del archivo
Verificar accesibilidad: contrast, :focus-visible, ARIA
```

### Paso 4 — Registrar en `functions.php`

```php
// Componente global:
wp_enqueue_style('mu-[nombre]', $theme_uri . '/css/components/[nombre].css', ['mu-base'], $theme_version);

// Página específica:
if (is_front_page()) {
    wp_enqueue_style('mu-home', $theme_uri . '/css/home.css', ['mu-base'], $theme_version);
}

// JavaScript en footer:
wp_enqueue_script('mu-[nombre]', $theme_uri . '/js/[nombre].js', [], $theme_version, true);
```

---

## 4. Plantillas

### CSS Component
```css
/* ========================================
   [NOMBRE] - [DESCRIPCIÓN BREVE]
   ======================================== */

.mu-[componente] {
    --local-spacing: var(--mu-space-md);
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
> Consultar §1 para verificar si el archivo destino ya existe antes de crearlo.  
> Consultar §2 para aplicar convenciones de naming y variables CSS correctas.
