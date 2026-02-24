MUY ÚNICOS — ARCHITECTURE & MIGRATION GUIDE

Estado: Refactor Modular Pragmático · v1.9.3 · Feb 24, 2026

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
│   ├── orders-workflow.php    # ✅ Workflow: Status 'Production', Smart Emails, Admin UI (WhatsApp link, Indicador Virtual Manual)
│   └── downloads-bonus.php    # ✅ Dynamic Bonus: Inyección de archivos digitales condicionales en Account & Emails
│
├── css/                       # 🎨 CSS MODULAR (Pragmático)
│   ├── admin.css              # is_admin() — Botones reindex, tools internas
│   ├── admin-order-files.css  # ✅ is_admin() && order_edit — Dropzone, Modal Files
│   ├── admin-orders.css       # ✅ is_admin() && order_edit — Status Badge, Indicador Virtual Manual
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
    ├── admin.js               # is_admin() — Crea botón #muyu-rebuild + WC-AJAX handler. Sin jQuery, usa fetch(). Nonce y WC-AJAX URL vía wp_localize_script (muyuAdminData).
    ├── admin-order-files.js   # ✅ is_admin() && order_edit — Drag&Drop, Ajax Uploads
    ├── admin-orders.js        # ✅ is_admin() && order_edit — WhatsApp Link Replacement
    ├── global-ui.js           # ✅ Global: country selector (hover), WPLingua toggle, share button, Carrusel Híbrido Lógica
    ├── header.js              # Global: menú móvil, submenús, dropdown cuenta
    ├── footer.js              # Global: comportamiento footer
    ├── cart.js                # is_cart() — depende de: jquery
    ├── checkout.js            # ✅ Validación WA (libphonenumber) + Toggle Dirección + Check Email
    ├── modal-auth.js          # ! is_user_logged_in()
    ├── country-modal.js       # Condicional — encolado por inc/geo.php
    └── shop.js                # ✅ is_shop() || is_product_category() || is_product_tag() || is_product() — Lógica de Infinite Scroll JS (Optimized)\n\n3. INVENTARIO DE ARCHIVOS (Estado Actual)\n\nPHP · inc/\n\nArchivo | Responsabilidad principal\n---|---\ninc/icons.php | mu_get_icon() — todos los SVGs del tema\ninc/geo.php | Detección de país por dominio, control de decimales por moneda (0 para AR/CL/CO), redirect selector en header, modal sugerencia, prefijo idioma.\ninc/digital-restriction.php | Restricción de productos físicos en subdominios v3.1.1. Auto-Rebuild en fallo de índice, Protección de loops.\ninc/auth-modal.php | HTML modal auth, endpoints wc_ajax_mu_*\ninc/checkout.php | Campos, validaciones, optimizaciones Checkout, Título "Pedido Recibido"\ninc/cart.php | Añadir múltiples ítems al carrito, buffers BACS\ninc/ui.php | Header icons, Cart badge fragment, WhatsApp btn, Custom Search form, Custom Footer, Share shortcode, Google Site Kit canonical, WPLingua body class, Category Description Mover, Reemplazo precio $0 a "Gratis", Disable GP Featured image HTML\ninc/orders-files.php | Gestor de archivos: Hooks Admin (Upload/Delete/PDF), Hooks Email (Links), Hook Account (Tabla Descargas).\ninc/orders-workflow.php | Flujo de pedidos: Estado 'wc-production', Helper mu_order_has_virtual_manual_item, Emails inteligentes (Físico/Digital), Admin UI (WhatsApp link, Indicador Virtual Manual).\ninc/downloads-bonus.php | Inyección dinámica de archivos bonus para usuarios con compras previas de productos manuales + productos específicos (ej. Líneas de Corte).\n\nCSS · css/\n\nArchivo | Condición de carga en functions.php\n---|---\nstyle.css (raíz) | Global (base)\ncss/admin.css | is_admin() && current_screen == 'product'\ncss/admin-order-files.css | is_admin() && order_edit (Dropzone styles)\ncss/admin-orders.css | is_admin() && order_edit (Badge styles, Indicador Virtual Manual)\ncss/account-downloads.css | is_account_page() && is_wc_endpoint_url('downloads')\ncss/components/global-ui.css | Global (Share Button, WhatsApp flotante, Search Form, WPLingua estilos, Carrusel Híbrido CSS)\ncss/components/header.css | Global (Header, Navegación, Country Selector con hover v1.8.7)\ncss/components/footer.css | Global\ncss/components/modal-auth.css | ! is_user_logged_in()\ncss/components/country-modal.css | Condicional — encolado por inc/geo.php\ncss/cart.css | is_cart()\ncss/checkout.css | is_checkout() && ! is_order_received_page()\ncss/home.css | is_front_page() (actualmente vacío)\ncss/shop.css | is_shop() || is_product_category() || is_product_tag() || is_product() (Auto-variaciones, Infinite Scroll)\n\nJS · js/\n\nArchivo | Condición de carga en functions.php\n---|---\njs/admin.js | is_admin() — Crea botón #muyu-rebuild + WC-AJAX handler. Sin jQuery, usa fetch(). Nonce y WC-AJAX URL vía wp_localize_script (muyuAdminData).\njs/admin-order-files.js | is_admin() && order_edit — Lógica Drag&Drop, Ajax Uploads, Modal Manager.\njs/admin-orders.js | is_admin() && order_edit — Reemplazo link teléfono por API WhatsApp.\njs/global-ui.js | Global (country selector, WPLingua toggle, share button, lógica drag Carrusel Híbrido)\njs/header.js | Global\njs/footer.js | Global\njs/modal-auth.js | ! is_user_logged_in()\njs/cart.js | is_cart() — depende de: jquery\njs/checkout.js | is_checkout() && ! is_order_received_page() — depende de: jquery, libphonenumber-js\njs/country-modal.js | Condicional — encolado por inc/geo.php\njs/shop.js | is_shop() || is_product_category() || is_product_tag() || is_product() — Lógica de Infinite Scroll JS (Optimized).\n\n4. SISTEMA DE DISEÑO (API Exclusiva)\n\n⚠️ NO inventar variables nuevas. Usar solo las listadas aquí (:root en style.css).\n\nVariables CSS (Extracto)\n\nCategoría | Variables Clave\n---|---\nColores | --primario (#2B9FCF), --secundario (#FFD77A), --texto, --blanco, --fondo\nSpacing | --mu-space-xs (5px), --mu-space-sm (10px), --mu-space-md (20px), --mu-space-lg (40px)\nRadius | --mu-radius-sm (6px), --mu-radius (12px), --mu-radius-md, --mu-radius-full (9999px)\nSombras | --mu-shadow-sm, --mu-shadow, --mu-shadow-md, --mu-shadow-lg\nTipografía | --mu-font-display (Fredoka One), --mu-font-base (Inter)\n\nAPI de Iconos SVG (inc/icons.php)\n\necho mu_get_icon('name'); // NUNCA inline SVG directo\n\nDisponibles: arrow, search, help, account, cart, close, share, check, lock, instagram, facebook, pinterest, tiktok, youtube\n\n5. ROUTING DE DESARROLLO — ¿Dónde va el código nuevo?\n\n¿Qué necesitás agregar? | PHP (inc/) | CSS (css/) | JS (js/)\n---|---|---\nAjuste UI pequeño (< 50 líneas) | ui.php | components/global-ui.css | global-ui.js\nElemento pesado Header/Footer | ui.php | components/header.css o footer.css | header.js o footer.js\nLógica multi-país | geo.php | components/country-modal.css | country-modal.js\nLógica Restricción Subdominios | digital-restriction.php | admin.css / shop.css | admin.js / shop.js\nFlujo de Carrito | cart.php | cart.css | cart.js\nLogin / Registro Modal | auth-modal.php | components/modal-auth.css | modal-auth.js\nFlujo Checkout | checkout.php | checkout.css | checkout.js\nCatálogo / Single Product | ui.php / geo.php | shop.css | shop.js\nGestor Archivos Pedido | orders-files.php | admin-order-files.css | admin-order-files.js\nWorkflow Pedidos | orders-workflow.php | admin-orders.css | admin-orders.js\nInyección Descargas Bonus | downloads-bonus.php | — | —\nNuevo ícono SVG | icons.php | — | —\n\n6. CONVENCIONES DE CÓDIGO & RENDIMIENTO\n\nPHP\n- Protección: if ( ! function_exists( 'mu_function_name' ) ) { ... } incluyendo el add_action/add_filter correspondiente dentro del bloque.\n- AJAX WC: Usar prefijo wc_ajax_mu_\n- Rendimiento: Evitar hooks pesados (init/wp_loaded) si hay hooks específicos o carga condicional.\n- CSS: NUNCA usar wp_add_inline_style() o wp_add_inline_script(). Todo estilo debe residir en un .css/.js cacheable.\n- Hooks: NUNCA anidar add_filter/add_action dentro de otras funciones hookeadas (e.g., dentro de wp_enqueue_scripts). Cada hook debe declararse en el scope global del módulo.\n\nJavaScript\n- Aislamiento: IIFE + 'use strict';.\n- Ejecución: DOMContentLoaded.\n- Cero jQuery salvo obligación de WooCommerce legacy (cart/checkout/shop).\n- Pasar datos PHP→JS vía wp_localize_script. NUNCA emitir <script> inline con lógica.\n\nCSS\n- Prefijos: .mu-[componente]__elem--[mod] (BEM).\n- Sobrescrituras: /* override GP: [motivo] */.\n- Variables: SIEMPRE usar variables CSS existentes (--primario, --blanco, --texto, etc.). NUNCA hardcodear colores que tengan variable disponible. Esto aplica también a valores de design tokens como border-radius (--mu-radius-full, --mu-radius-sm, etc.).\n\n7. PENDIENTES / DEUDA TÉCNICA\n\n- Evaluar auto-host de libphonenumber-js para eliminar dependencia CDN en checkout.\n- Llenar archivos vacíos: css/home.css\n- Migrar bulk actions de Legacy a HPOS (woocommerce_order_list_table_bulk_actions).\n