
# MUY ÚNICOS — ARCHITECTURE & MIGRATION GUIDE

**Estado:** Refactor Modular Completo · **v1.0.0** · Feb 2026  
**Monolithic `functions.php` DEPRECATED.** Toda la lógica vive en `inc/`, `css/` y `js/`.

---

## 1. ÁRBOL DE DIRECTORIOS

```
muyunicos/ (generatepress-child)
│
├── functions.php              # SOLO: mu_enqueue_assets + mu_load_module + mu_hide_wplingua_switcher
├── style.css                  # Variables CSS, reset, utilidades globales y child theme header
│
├── inc/                       # ⚙️ MÓDULOS PHP
│   ├── icons.php              # [CARGA PRIMERO] mu_get_icon() — repositorio de SVGs
│   ├── geo.php                # Sistema multi-país: detección, routing, Digital Restriction
│   ├── auth-modal.php         # Modal Login/Registro + endpoints WC-AJAX
│   ├── checkout.php           # Optimizaciones WC Checkout + validación de campos
│   ├── cart.php               # Agregar múltiples productos, buffers BACS
│   ├── product.php            # Lógica producto físico/digital (mu_render_linked_product)
│   └── ui.php                 # Header, Footer, Search, WhatsApp, Share shortcodes
│
├── css/                       # 🎨 CSS MODULAR (carga condicional vía functions.php)
│   ├── components/            # Componentes globales (se cargan en TODAS las páginas)
│   │   ├── header.css         # Estilos header, navegación, country selector dropdown
│   │   ├── footer.css         # Estilos footer y columnas
│   │   ├── modal-auth.css     # Modal login/registro (solo usuarios no logueados)
│   │   ├── share-button.css   # Botón compartir flotante
│   │   └── country-modal.css  # Modal de selección de país (geo)
│   ├── cart.css               # Página carrito (is_cart())
│   ├── checkout.css           # Página checkout (is_checkout())
│   ├── home.css               # Página principal (is_front_page()) — actualmente vacío
│   ├── product.css            # Página de producto individual (is_product())
│   └── shop.css               # Tienda / categorías / etiquetas — actualmente vacío
│
└── js/                        # ⚡ JS MODULAR (IIFE + strict mode + DOMContentLoaded)
    ├── mu-ui-scripts.js       # Helpers globales misceláneos
    ├── header.js              # Comportamiento header (menú móvil, scroll, etc.)
    ├── footer.js              # Comportamiento footer
    ├── cart.js                # Lógica interactiva del carrito
    ├── checkout.js            # Validación checkout + libphonenumber
    ├── modal-auth.js          # Flujo login/registro AJAX
    ├── share-button.js        # Lógica botón compartir
    └── country-modal.js       # Modal de cambio de país
```

---

## 2. INVENTARIO DE ARCHIVOS (Tamaños reales)

### PHP · `inc/`

| Archivo | Tamaño | Responsabilidad principal |
|---|---|---|
| `inc/icons.php` | 7.0 KB | `mu_get_icon()` — todos los SVGs del tema |
| `inc/geo.php` | 21.8 KB | Detección de país, redirección de dominio, restricción digital |
| `inc/auth-modal.php` | 12.1 KB | HTML modal auth, endpoints `wc_ajax_mu_*` |
| `inc/checkout.php` | 10.0 KB | Campos, validaciones y optimizaciones de WC Checkout |
| `inc/cart.php` | 2.9 KB | Añadir múltiples ítems al carrito, buffers BACS |
| `inc/product.php` | 4.9 KB | `mu_render_linked_product()`, lógica físico/digital |
| `inc/ui.php` | 12.5 KB | Header, footer, búsqueda, WhatsApp, share shortcodes |

### CSS · `css/`

| Archivo | Tamaño | Carga en |
|---|---|---|
| `style.css` (raíz) | ~9 KB | Global (base) |
| `css/components/header.css` | 9.4 KB | Global |
| `css/components/footer.css` | 7.9 KB | Global |
| `css/components/modal-auth.css` | 8.3 KB | Global (no logueados) |
| `css/components/share-button.css` | 2.4 KB | Global |
| `css/components/country-modal.css` | 3.7 KB | Global (geo) |
| `css/cart.css` | 9.7 KB | `is_cart()` |
| `css/checkout.css` | 9.4 KB | `is_checkout()` |
| `css/product.css` | 0.6 KB | `is_product()` |
| `css/home.css` | 0 B | `is_front_page()` — pendiente contenido |
| `css/shop.css` | ~0 B | `is_shop()` — pendiente contenido |

### JS · `js/`

| Archivo | Tamaño | Carga en |
|---|---|---|
| `js/mu-ui-scripts.js` | 8.7 KB | Global |
| `js/header.js` | 4.9 KB | Global |
| `js/footer.js` | 0.9 KB | Global |
| `js/modal-auth.js` | 15.5 KB | Global (no logueados) |
| `js/share-button.js` | 3.4 KB | Global |
| `js/cart.js` | 6.4 KB | `is_cart()` |
| `js/checkout.js` | 6.7 KB | `is_checkout()` |
| `js/country-modal.js` | 3.5 KB | Global (geo) |

---

## 3. SISTEMA DE DISEÑO (API Exclusiva)

> ⚠️ **NO inventar variables nuevas.** Usar solo las listadas aquí.  
> Todas definidas en `style.css` `:root {}`.

### Variables CSS

| Categoría | Variable | Valor |
|---|---|---|
| **Colores** | `--primario` | `#2B9FCF` |
| | `--secundario` | `#FFD77A` |
| | `--texto` | `#277292` |
| | `--texto-light` | `#6C6F7A` |
| | `--fondo` | `#fbf7f5` |
| | `--blanco` | `#FFFFFF` |
| | `--exito` | `#a3ffbc` |
| | `--resaltado` | `#237FA9` |
| **Spacing** | `--mu-space-xs` | `5px` |
| | `--mu-space-sm` | `10px` |
| | `--mu-space-md` | `20px` |
| | `--mu-space-lg` | `40px` |
| | `--mu-space-xl` | `40px` |
| **Radius** | `--mu-radius-sm` | `6px` |
| | `--mu-radius` | `12px` |
| | `--mu-radius-md` | `16px` |
| | `--mu-radius-lg` | `20px` |
| | `--mu-radius-xl` | `32px` |
| | `--mu-radius-full` | `9999px` |
| **Sombras** | `--mu-shadow-sm` | `0 2px 4px rgba(0,0,0,0.1)` |
| | `--mu-shadow` | `0 4px 6px rgba(0,0,0,0.1)` |
| | `--mu-shadow-md` | `0 8px 16px rgba(0,0,0,0.15)` |
| | `--mu-shadow-lg` | `0 10px 25px rgba(0,0,0,0.15)` |
| **Transiciones** | `--mu-transition` | `all 0.3s cubic-bezier(0.4, 0, 0.2, 1)` |
| | `--mu-transition-fast` | `all 0.2s ease` |
| **Tipografía** | `--mu-font-display` | `'Fredoka One', display, sans-serif` |
| | `--mu-font-base` | `Inter, sans-serif` |

### API de Iconos SVG (`inc/icons.php`)

```php
echo mu_get_icon('name'); // NUNCA inline SVG directo
```

**Íconos disponibles:** `arrow`, `search`, `close`, `share`, `check`, `instagram`, `facebook`, `pinterest`, `tiktok`, `youtube`

---

## 4. CONVENCIONES DE CÓDIGO

### PHP

```php
// Protección obligatoria en TODAS las funciones
if ( ! function_exists( 'mu_function_name' ) ) {
    function mu_function_name() {
        // ...
    }
}
```

- **Prefijos:** `mu_` funciones generales · `muyu_` funciones core/geo
- **AJAX WooCommerce:** prefijo `wc_ajax_` (ej: `wc_ajax_mu_check_email`)
- **Hooks:** usar hooks exactos, nunca `init` para lógica de WC

### JavaScript

```js
(function() {
    'use strict';
    const init = () => { /* lógica */ };
    document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', init)
        : init();
})();
```

- **NUNCA** jQuery raw, siempre vanilla JS o WP API
- `wp_localize_script()` para pasar datos PHP → JS

### CSS

- **Prefijo obligatorio:** `.mu-` en todas las clases custom
- **Metodología:** BEM — `.mu-cart`, `.mu-cart__item`, `.mu-cart__item--active`
- **Breakpoints:** Mobile-first · `@media (min-width: 769px)` para desktop
- **Override GP:** comentar `/* override GP: [motivo] */` cuando se pise GeneratePress

---

## 5. ROUTING — ¿Dónde va el código nuevo?

| ¿Qué necesitás agregar? | PHP | CSS | JS |
|---|---|---|---|
| Elemento Header/Footer | `inc/ui.php` | `css/components/` | `js/header.js` o `js/footer.js` |
| Lógica multi-país | `inc/geo.php` | `css/components/country-modal.css` | `js/country-modal.js` |
| Cambio en carrito | `inc/cart.php` | `css/cart.css` | `js/cart.js` |
| Login / Registro | `inc/auth-modal.php` | `css/components/modal-auth.css` | `js/modal-auth.js` |
| Checkout | `inc/checkout.php` | `css/checkout.css` | `js/checkout.js` |
| Página de producto | `inc/product.php` | `css/product.css` | — |
| Tienda / categorías | — | `css/shop.css` | — |
| Home | — | `css/home.css` | — |
| Nuevo ícono SVG | `inc/icons.php` | — | — |
| Helper global UI | `inc/ui.php` | `style.css` | `js/mu-ui-scripts.js` |

---

## 6. ARCHIVOS RAÍZ (No modificar sin razón)

| Archivo | Estado | Notas |
|---|---|---|
| `functions.php` | ✅ Activo | Solo enqueue + load_module. No agregar lógica de negocio |
| `style.css` | ✅ Activo | Variables + utilidades globales. NO tocar `assets/css/main.min.css` del parent |
| `assets/css/main.min.css` | 🚫 Prohibido | Es del tema padre GeneratePress. Nunca modificar |

---

## 7. PENDIENTES / DEUDA TÉCNICA

- `css/home.css` — Vacío. Pendiente estilos de la home.
- `css/shop.css` — Vacío (1 byte). Pendiente estilos de tienda/categorías.
- `css/product.css` — Muy pequeño (596 B). Probablemente incompleto.
- Revisar si `country-modal.css` / `country-modal.js` deben cargarse condicionalmente (solo si `inc/geo.php` está activo) en lugar de globalmente.
