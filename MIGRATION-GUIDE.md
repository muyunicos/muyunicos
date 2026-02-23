MUY ÚNICOS — ARCHITECTURE & MIGRATION GUIDE

Estado: Refactor Modular Pragmático · v1.9.0 · Feb 23, 2026

Monolithic functions.php DEPRECATED. Toda la lógica vive en inc/, css/ y js/.

⚠️ IA / LLM DIRECTIVE: Read this document carefully before suggesting architecture changes. Strict compliance con "Pragmatic Modularity" y "Pull Request Workflow" is required.

1. REGLAS CORE DE ARQUITECTURA Y FLUJO DE TRABAJO

Modularidad Pragmática (Regla "Goldilocks")
- NO a la micro-fragmentación.
- Ajustes pequeños de UI (botones, toggles, iconos, micro-interacciones < 50 líneas) DEBEN agruparse en:
  - css/components/global-ui.css
  - js/global-ui.js

SÍ al aislamiento por contexto
- Funcionalidades complejas (Checkout, Cart, Auth, Shop, Orders) deben tener sus propios archivos y cargarse condicionalmente.

Carga Condicional Estricta
- Nunca cargar assets globales si no aplican a header/footer o UI transversal.
- Usar is_shop(), is_checkout(), is_cart(), is_user_logged_in(), etc. en functions.php.
- NUNCA usar wp_add_inline_style() o wp_add_inline_script(). Todo CSS/JS debe estar en archivos cacheables.

Flujo GitHub (PROHIBIDO COMMIT A MAIN)
- Todo cambio debe ir en una rama semántica (perf/, refactor/, fix/, feat/).
- Todo cambio requiere un Pull Request (PR). El título debe ser descriptivo.
- Actualiza SIEMPRE este archivo en el PR, manteniendo el "System Map" como estado actual (no changelog).

2. ÁRBOL DE DIRECTORIOS (System Map)

muyunicos/ (generatepress-child)
│
├── functions.php              # SOLO: mu_enqueue_assets (condicional) + mu_load_module
├── style.css                  # Variables CSS, reset, y child theme header
│
├── inc/                       # ⚙️ MÓDULOS PHP (Lógica de negocio y hooks)
│   ├── icons.php              # [CARGA PRIMERO] mu_get_icon() — repositorio de SVGs
│   ├── geo.php                # Sistema multi-país + Auto-Detección + Decimales + Modal + Selector
│   ├── digital-restriction.php# ✅ Digital Restriction System v3.1.1 (Hotfix Index Rebuild, Redirect Protection)
│   ├── auth-modal.php         # Modal Login/Registro + endpoints WC-AJAX
│   ├── checkout.php           # ✅ Checkout Híbrido Optimizado (Físico/Digital) + Validación WA
│   ├── cart.php               # Lógica de carrito, buffers BACS
│   ├── ui.php                 # ✅ Header, Footer, search form, WhatsApp btn, Canonical fix, WPLingua body class
│   ├── orders-files.php       # ✅ File Manager (Admin/Frontend): Uploads, PDF gen, Downloads endpoint
│   └── orders-workflow.php    # ✅ Workflow: Status 'Production', Smart Emails, Admin WhatsApp link
│
├── css/                       # 🎨 CSS MODULAR (Pragmático)
│   ├── admin.css              # is_admin() — Botones reindex, tools internas
│   ├── admin-order-files.css  # ✅ is_admin() && order_edit — Dropzone, Modal Files
│   ├── admin-orders.css       # ✅ is_admin() && order_edit — Status Badge
│   ├── components/            # Componentes compartidos
│   │   ├── global-ui.css      # ✅ Global: micro UI (Share, WhatsApp flotante, Search, estilos de WPLingua, Carrusel Híbrido)
│   │   ├── header.css         # Global: header, navegación, Country Selector (con hover automático v1.8.7)
│   │   ├── footer.css         # Global: footer y columnas
│   │   ├── modal-auth.css     # ! is_user_logged_in()
│   │   └── country-modal.css  # Condicional — encolado por inc/geo.php (mu_should_show_country_modal)
│   ├── cart.css               # is_cart()
│   ├── checkout.css           # ✅ Checkout Moderno (Grid Desktop + Mobile Fix)
│   ├── home.css               # is_front_page()
│   ├── shop.css               # ✅ is_shop() || is_product_category() || is_product_tag() || is_product() (Infinite Scroll estilos)
│   └── account-downloads.css  # ✅ is_account_page() && is_wc_endpoint_url('downloads')
│
└── js/                        # ⚡ JS MODULAR (IIFE + strict mode + DOMContentLoaded)
    ├── admin.js               # is_admin() — Crea botón #muyu-rebuild + WC-AJAX handler.
    ├── admin-order-files.js   # ✅ is_admin() && order_edit — Drag&Drop, Ajax Uploads
    ├── admin-orders.js        # ✅ is_admin() && order_edit — WhatsApp Link Replacement
    ├── global-ui.js           # ✅ Global: country selector (hover), WPLingua toggle, share button, Carrusel Híbrido Lógica
    ├── header.js              # Global: menú móvil, submenús, dropdown cuenta
    ├── footer.js              # Global: comportamiento footer
    ├── cart.js                # is_cart() — depende de: jquery
    ├── checkout.js            # ✅ Validación WA (libphonenumber) + Toggle Dirección + Check Email
    ├── modal-auth.js          # ! is_user_logged_in()
    ├── country-modal.js       # Condicional — encolado por inc/geo.php
    └── shop.js                # ✅ is_shop() || is_product_category() || is_product_tag() || is_product() — Autoselect form via data bridge (#mu-format-autoselect-data), Lógica de Infinite Scroll JS

3. INVENTARIO DE ARCHIVOS (Estado Actual)

PHP · inc/

Archivo | Responsabilidad principal
---|---
inc/icons.php | mu_get_icon() — todos los SVGs del tema
inc/geo.php | Detección de país por dominio, control de decimales por moneda (0 para AR/CL/CO), redirect selector en header, modal sugerencia, prefijo idioma.
inc/digital-restriction.php | Restricción de productos físicos en subdominios v3.1.1. Auto-Rebuild en fallo de índice, Protección de loops.
inc/auth-modal.php | HTML modal auth, endpoints wc_ajax_mu_*
inc/checkout.php | Campos, validaciones, optimizaciones Checkout, Título "Pedido Recibido"
inc/cart.php | Añadir múltiples ítems al carrito, buffers BACS
inc/ui.php | Header icons, Cart badge fragment, WhatsApp btn, Custom Search form, Custom Footer, Share shortcode, Google Site Kit canonical, WPLingua body class, Category Description Mover, Reemplazo precio $0 a "Gratis", Disable GP Featured image HTML
inc/orders-files.php | Gestor de archivos: Hooks Admin (Upload/Delete/PDF), Hooks Email (Links), Hook Account (Tabla Descargas).
inc/orders-workflow.php | Flujo de pedidos: Estado 'wc-production', Filtro virtual no-descargable, Emails inteligentes (Físico/Digital), Admin UI (WhatsApp link).

CSS · css/

Archivo | Condición de carga en functions.php
---|---
style.css (raíz) | Global (base)
css/admin.css | is_admin() && current_screen == 'product'
css/admin-order-files.css | is_admin() && order_edit (Dropzone styles)
css/admin-orders.css | is_admin() && order_edit (Badge styles)
css/account-downloads.css | is_account_page() && is_wc_endpoint_url('downloads')
css/components/global-ui.css | Global (Share Button, WhatsApp flotante, Search Form, WPLingua estilos, Carrusel Híbrido CSS)
css/components/header.css | Global (Header, Navegación, Country Selector con hover v1.8.7)
css/components/footer.css | Global
css/components/modal-auth.css | ! is_user_logged_in()
css/components/country-modal.css | Condicional — encolado por inc/geo.php
css/cart.css | is_cart()
css/checkout.css | is_checkout() && ! is_order_received_page()
css/home.css | is_front_page() (actualmente vacío)
css/shop.css | is_shop() || is_product_category() || is_product_tag() || is_product() (Auto-variaciones, Infinite Scroll)

JS · js/

Archivo | Condición de carga en functions.php
---|---
js/admin.js | is_admin() — Crea botón #muyu-rebuild + WC-AJAX handler. Sin jQuery, usa fetch(). Nonce y WC-AJAX URL vía wp_localize_script (muyuAdminData).
js/admin-order-files.js | is_admin() && order_edit — Lógica Drag&Drop, Ajax Uploads, Modal Manager.
js/admin-orders.js | is_admin() && order_edit — Reemplazo link teléfono por API WhatsApp.
js/global-ui.js | Global (country selector, WPLingua toggle, share button, lógica drag Carrusel Híbrido)
js/header.js | Global
js/footer.js | Global
js/modal-auth.js | ! is_user_logged_in()
js/cart.js | is_cart() — depende de: jquery
js/checkout.js | is_checkout() && ! is_order_received_page() — depende de: jquery, libphonenumber-js
js/country-modal.js | Condicional — encolado por inc/geo.php
js/shop.js | is_shop() || is_product_category() || is_product_tag() || is_product() — Autoselect form via data bridge (#mu-format-autoselect-data), Lógica de Infinite Scroll JS

4. SISTEMA DE DISEÑO (API Exclusiva)

⚠️ NO inventar variables nuevas. Usar solo las listadas aquí (:root en style.css).

Variables CSS (Extracto)

Categoría | Variables Clave
---|---
Colores | --primario (#2B9FCF), --secundario (#FFD77A), --texto, --blanco, --fondo
Spacing | --mu-space-xs (5px), --mu-space-sm (10px), --mu-space-md (20px), --mu-space-lg (40px)
Radius | --mu-radius-sm (6px), --mu-radius (12px), --mu-radius-md, --mu-radius-full (9999px)
Sombras | --mu-shadow-sm, --mu-shadow, --mu-shadow-md, --mu-shadow-lg
Tipografía | --mu-font-display (Fredoka One), --mu-font-base (Inter)

API de Iconos SVG (inc/icons.php)

echo mu_get_icon('name'); // NUNCA inline SVG directo

Disponibles: arrow, search, help, account, cart, close, share, check, lock, instagram, facebook, pinterest, tiktok, youtube

5. ROUTING DE DESARROLLO — ¿Dónde va el código nuevo?

¿Qué necesitás agregar? | PHP (inc/) | CSS (css/) | JS (js/)
---|---|---
Ajuste UI pequeño (< 50 líneas) | ui.php | components/global-ui.css | global-ui.js
Elemento pesado Header/Footer | ui.php | components/header.css o footer.css | header.js o footer.js
Lógica multi-país | geo.php | components/country-modal.css | country-modal.js
Lógica Restricción Subdominios | digital-restriction.php | admin.css / shop.css | admin.js / shop.js
Flujo de Carrito | cart.php | cart.css | cart.js
Login / Registro Modal | auth-modal.php | components/modal-auth.css | modal-auth.js
Flujo Checkout | checkout.php | checkout.css | checkout.js
Catálogo / Single Product | ui.php / geo.php | shop.css | shop.js
Gestor Archivos Pedido | orders-files.php | admin-order-files.css | admin-order-files.js
Workflow Pedidos | orders-workflow.php | admin-orders.css | admin-orders.js
Nuevo ícono SVG | icons.php | — | —

6. CONVENCIONES DE CÓDIGO & RENDIMIENTO

PHP
- Protección: if ( ! function_exists( 'mu_function_name' ) ) { ... } incluyendo el add_action/add_filter correspondiente dentro del bloque.
- AJAX WC: Usar prefijo wc_ajax_mu_
- Rendimiento: Evitar hooks pesados (init/wp_loaded) si hay hooks específicos o carga condicional.
- CSS: NUNCA usar wp_add_inline_style() o wp_add_inline_script(). Todo estilo debe residir en un .css/.js cacheable.
- Hooks: NUNCA anidar add_filter/add_action dentro de otras funciones hookeadas (e.g., dentro de wp_enqueue_scripts). Cada hook debe declararse en el scope global del módulo.

JavaScript
- Aislamiento: IIFE + 'use strict';.
- Ejecución: DOMContentLoaded.
- Cero jQuery salvo obligación de WooCommerce legacy (cart/checkout/shop).
- Pasar datos PHP→JS vía wp_localize_script. NUNCA emitir <script> inline con lógica.

CSS
- Prefijos: .mu-[componente]__elem--[mod] (BEM).
- Sobrescrituras: /* override GP: [motivo] */.
- Variables: SIEMPRE usar variables CSS existentes (--primario, --blanco, --texto, etc.). NUNCA hardcodear colores que tengan variable disponible. Esto aplica también a valores de design tokens como border-radius (--mu-radius-full, --mu-radius-sm, etc.).

7. PENDIENTES / DEUDA TÉCNICA

- Evaluar auto-host de libphonenumber-js para eliminar dependencia CDN en checkout.
- Llenar archivos vacíos: css/home.css
- Migrar bulk actions de Legacy a HPOS (woocommerce_order_list_table_bulk_actions).
