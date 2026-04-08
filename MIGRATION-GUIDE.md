MUY ÍNICOS — ARCHITECTURE & MIGRATION GUIDE

Estado: Refactor Modular Pragmático · v2.5.0 · Apr 8, 2026

Monolithic functions.php DEPRECATED. Toda la lógica vive en inc/, css/ y js/.

⚠️ IA / LLM DIRECTIVE: Read this document carefully before suggesting arquitectura changes. Strict compliance con "Pragmatic Modularity" y "Pull Request Workflow" is required.

1. REGLAS CORE DE ARQUITECTURA Y FLUJO DE TRABAJO

Modularidad Pragmática (Regla "Goldilocks")
- NO a la micro-fragmentación.
- Ajustes pequeños de UI (botones, toggles, iconos, micro-interacciones < 50 líneas) DEBEN agruparse en:
  - css/components/global-ui.css
  - js/global-ui.js

SÍ al aislamiento por contexto
- Funcionalidades complejas (Checkout, Cart, Auth, Shop, Orders, Navigation Chips, Login, Testimonials) deben tener sus propios archivos y cargarse condicionalmente.

Carga Condicional Estricta
- Nunca cargar assets globales si no aplican a header/footer o UI transversal.
- Usar is_shop(), is_checkout(), is_cart(), is_user_logged_in(), is_product(), has_shortcode(), etc. en functions.php.
- NUNCA usar wp_add_inline_style() o wp_add_inline_script(). Todo CSS/JS debe estar en archivos cacheables.
- EXCEPCIÓN VÁLIDA: wp_add_inline_style() está permitido dentro del hook login_enqueue_scripts exclusivamente para inyectar propiedades dinámicas generadas por PHP (ej: URLs de imágenes). El bloque CSS principal debe residir siempre en un archivo .css cacheable.

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
│   ├── digital-restriction.php# ✅ Digital Restriction System v4.1.0 (Fix Memory: Rebuild vía WP Cron)
│   ├── auth-modal.php         # Modal Login/Registro + endpoints WC-AJAX
│   ├── login.php              # ✅ Login Page v2.1.0: UI marca (css/login.css), logo URL/ALT, error genérico, redirección inteligente post-login
│   ├── checkout.php           # ✅ Checkout Híbrido Optimizado (Físico/Digital) + Validación WA + Gestión contraseñas WC + Login Gate (HTML)
│   ├── cart.php               # Lógica de carrito, buffers BACS
│   ├── flexible-price.php     # ✅ Sistema de Precio Flexible v4.0
│   ├── ui.php                 # ✅ Header, Footer, Search, WhatsApp, Canonical, WPLingua, Gratis/$0,
│   │                          #   Shortcodes: [mu_testimonios_section] [mu_bestsellers_section] [mu_popcat_section]
│   ├── orders-files.php       # ✅ File Manager (Admin/Frontend): Uploads, PDF gen, Downloads endpoint
│   ├── orders-workflow.php    # ✅ Workflow: Status 'Production', Smart Emails, Admin UI
│   ├── downloads-bonus.php    # ✅ Dynamic Bonus & Guides: Archivo bonus + Guía inline para Cat. 18
│   ├── navigation-chips.php   # ✅ Navigation Chips v8: Breadcrumb global + índice compacto + chips
│   ├── products-core.php      # ✅ Productos Personalizados Core v2.1
│   ├── addon-nombre.php       # ✅ Addon Nombre v3.0
│   └── addon-etiquetas.php    # ✅ Addon Etiquetas v3.0
│
├── css/                       # 🎨 CSS MODULAR (Pragmático)
│   ├── admin.css              # is_admin()
│   ├── admin-order-files.css  # is_admin() && order_edit
│   ├── admin-orders.css       # is_admin() && order_edit
│   ├── login.css              # login_enqueue_scripts
│   ├── product.css            # is_product()
│   ├── testimonials.css       # has_shortcode('mu_testimonios_section')
│   ├── home.css               # ✅ is_front_page() — tarjetas de producto (.mu-product-card) y categoría (.mu-category-card)
│   │                          #   El carrusel híbrido reutiliza css/components/global-ui.css + js/global-ui.js::initCarousels()
│   ├── components/
│   │   ├── global-ui.css      # ✅ Global: Share, WhatsApp, Search, WPLingua, Carrusel Híbrido
│   │   ├── header.css         # Global
│   │   ├── footer.css         # Global
│   │   ├── modal-auth.css     # ! is_user_logged_in()
│   │   ├── country-modal.css  # Condicional (inc/geo.php)
│   │   └── navigation-chips.css # is_shop() || is_product_category() || ...
│   ├── cart.css               # is_cart()
│   ├── checkout.css           # is_checkout() && ! is_order_received_page()
│   ├── shop.css               # is_shop() || is_product_category() || ...
│   ├── product-builder.css    # is_product() || is_cart()
│   └── account-downloads.css  # is_account_page() && is_wc_endpoint_url('downloads')
│
└── js/                        # ⚡ JS MODULAR (IIFE + strict mode + DOMContentLoaded)
    ├── admin.js               # is_admin()
    ├── admin-order-files.js   # is_admin() && order_edit
    ├── admin-orders.js        # is_admin() && order_edit
    ├── global-ui.js           # ✅ Global — initCarousels() cubre TODOS los .mu-carousel-wrapper
    ├── header.js              # Global
    ├── footer.js              # Global
    ├── product.js             # is_product()
    ├── cart.js                # is_cart()
    ├── flexible-price.js      # is_cart() || is_checkout()
    ├── checkout.js            # is_checkout() && ! is_order_received_page()
    ├── modal-auth.js          # ! is_user_logged_in()
    ├── country-modal.js       # Condicional (inc/geo.php)
    ├── shop.js                # is_shop() || ...
    ├── navigation-chips.js    # is_shop() || ...
    ├── testimonials.js        # has_shortcode('mu_testimonios_section') — Fisher-Yates, render dinámico
    ├── addon-nombre.js        # is_product() || is_cart()
    └── addon-etiquetas.js     # is_product() (cat 18/19)

3. INVENTARIO DE ARCHIVOS (Estado Actual)

PHP · inc/

Archivo | Responsabilidad principal
---|---
inc/icons.php | mu_get_icon() — todos los SVGs del tema
inc/geo.php | Detección de país por dominio, control de decimales por moneda, redirect selector en header, modal sugerencia, prefijo idioma.
inc/digital-restriction.php | Restricción de productos físicos en subdominios v4.1.0. Rebuild de índices via WP Cron (wp_schedule_single_event). ensure_indexes_exist() programa Cron en lugar de ejecutar rebuild síncrono.
inc/auth-modal.php | HTML modal auth, endpoints wc_ajax_mu_*
inc/login.php | Personalización wp-login.php v2.1.0: enqueue de css/login.css + inline background-image del logo (wp_add_inline_style), login_headerurl, login_errors genérico, mu_smart_login_redirect.
inc/checkout.php | Campos, validaciones, optimizaciones Checkout, Gestión contraseñas WC. Login Gate: mu_checkout_login_notice (woocommerce_before_checkout_form, prioridad 5).
inc/cart.php | Añadir múltiples ítems al carrito, buffers BACS
inc/flexible-price.php | Sistema de Precio Flexible v4.0: mapa O(1), validación, captura, precio dinámico, AJAX handler, enqueue condicional de js/flexible-price.js vía wp_localize_script (muFlexiblePrice).
inc/ui.php | Header icons, Cart badge fragment, WhatsApp btn, Custom Search form, Custom Footer, Share shortcode, Google Site Kit canonical, WPLingua body class, Category Description Mover, Reemplazo precio $0, Disable GP Featured image. Shortcodes: [mu_testimonios_section] (assets has_shortcode condicional) · [mu_bestsellers_section] (transient 12h, assets is_front_page) · [mu_popcat_section] (estático, reutiliza carrusel global).
inc/orders-files.php | Gestor de archivos: Hooks Admin, Email, Account.
inc/orders-workflow.php | Flujo de pedidos: Estado 'wc-production', Emails inteligentes, Admin UI.
inc/downloads-bonus.php | Inyección dinámica de archivos bonus + guía de uso para Cat. 18 virtuales.
inc/navigation-chips.php | Navigation Chips v8: Breadcrumb global, índice compacto, chips, transient mu_navchips_product_index.
inc/products-core.php | Productos Personalizados Core v2.1: constantes, MU_UI_Helper, hooks de carrito y orden.
inc/addon-nombre.php | Addon Nombre v3.0. Campo nombre, validación, guardado carrito/orden, editor inline AJAX.
inc/addon-etiquetas.php | Addon Etiquetas v3.0. Builder de etiquetas, config, render UI, enqueue JS+config.

CSS · css/

Archivo | Condición de carga
---|---
style.css (raíz) | Global (base)
css/admin.css | is_admin()
css/admin-order-files.css | is_admin() && order_edit
css/admin-orders.css | is_admin() && order_edit
css/login.css | login_enqueue_scripts
css/account-downloads.css | is_account_page() && is_wc_endpoint_url('downloads')
css/product.css | is_product()
css/testimonials.css | has_shortcode('mu_testimonios_section') — via mu_testimonios_enqueue
css/home.css | ✅ is_front_page() — .mu-product-card (bestsellers) + .mu-category-card (popcat). Carrusel: reutiliza global-ui.css + global-ui.js.
css/components/global-ui.css | Global — Share, WhatsApp, Search, WPLingua, Carrusel Híbrido
css/components/header.css | Global
css/components/footer.css | Global
css/components/modal-auth.css | ! is_user_logged_in()
css/components/country-modal.css | Condicional (inc/geo.php)
css/components/navigation-chips.css | is_shop() \|\| is_product_category() \|\| is_product_tag() \|\| is_product()
css/cart.css | is_cart()
css/checkout.css | is_checkout() && ! is_order_received_page()
css/shop.css | is_shop() \|\| is_product_category() \|\| is_product_tag() \|\| is_product()
css/product-builder.css | is_product() \|\| is_cart()

JS · js/

Archivo | Condición de carga
---|---
js/admin.js | is_admin()
js/admin-order-files.js | is_admin() && order_edit
js/admin-orders.js | is_admin() && order_edit
js/global-ui.js | ✅ Global — initCarousels() cubre todos los .mu-carousel-wrapper del sitio (home: bestsellers + popcat, y cualquier otro futuro)
js/header.js | Global
js/footer.js | Global
js/product.js | is_product()
js/cart.js | is_cart()
js/flexible-price.js | is_cart() \|\| is_checkout()
js/checkout.js | is_checkout() && ! is_order_received_page()
js/modal-auth.js | ! is_user_logged_in()
js/country-modal.js | Condicional (inc/geo.php)
js/shop.js | is_shop() \|\| ...
js/navigation-chips.js | is_shop() \|\| ...
js/testimonials.js | has_shortcode('mu_testimonios_section') — IIFE, Fisher-Yates shuffle, render dinámico 3 reseñas, botón satélite. Datos PHP→JS vía muTestimonials.reviews.
js/addon-nombre.js | is_product() \|\| is_cart()
js/addon-etiquetas.js | is_product() (cat 18/19)

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
Product Builder | --mu-builder-accent (#6c5ce7), --mu-builder-accent-hover, --mu-builder-accent-bg, --mu-builder-accent-bg-light, --mu-builder-text, --mu-builder-text-muted, --mu-builder-success, --mu-builder-danger, --mu-builder-danger-dark, --mu-builder-border, --mu-builder-border-dark, --mu-builder-bg-light, --mu-builder-bg-subtle, --mu-builder-bg-muted, --mu-builder-bg-option

API de Iconos SVG (inc/icons.php)

echo mu_get_icon('name'); // NUNCA inline SVG directo

Disponibles: arrow, search, help, account, cart, close, share, check, lock, instagram, facebook, pinterest, tiktok, youtube

5. ROUTING DE DESARROLLO — ¿dónde va el código nuevo?

¿Qué necesitás agregar? | PHP (inc/) | CSS (css/) | JS (js/)
---|---|---
Ajuste UI pequeño (< 50 líneas) | ui.php | components/global-ui.css | global-ui.js
Elemento pesado Header/Footer | ui.php | components/header.css o footer.css | header.js o footer.js
Lógica multi-país | geo.php | components/country-modal.css | country-modal.js
Lógica Restricción Subdominios | digital-restriction.php | admin.css / shop.css | admin.js / shop.js
Flujo de Carrito | cart.php | cart.css | cart.js
Precio Flexible | flexible-price.php | cart.css (§ Precio Flexible) | flexible-price.js
Login / Registro Modal | auth-modal.php | components/modal-auth.css | modal-auth.js
Personalización wp-login.php | login.php | css/login.css | —
Flujo Checkout | checkout.php | checkout.css | checkout.js
Catálogo / Grid de Productos | navigation-chips.php | shop.css | shop.js / navigation-chips.js
Ficha de Producto (Single) | ui.php | product.css | product.js
Gestor Archivos Pedido | orders-files.php | admin-order-files.css | admin-order-files.js
Workflow Pedidos | orders-workflow.php | admin-orders.css | admin-orders.js
Inyección Descargas Bonus + Guías | downloads-bonus.php | — | —
Nuevo icóno SVG | icons.php | — | —
Builder de producto personalizado | products-core.php | product-builder.css | —
Addon campo nombre etiquetas | addon-nombre.php | product-builder.css (§10,§11) | addon-nombre.js
Addon builder etiquetas | addon-etiquetas.php | product-builder.css | addon-etiquetas.js
Shortcode Testimonios / Reseñas Google | ui.php | testimonials.css | testimonials.js
Shortcode Home (carrusel/sección) | ui.php | home.css | — (reutiliza global-ui.js::initCarousels)

6. CONVENCIONES DE CÓDIGO & RENDIMIENTO

PHP
- Protección: if ( ! function_exists( 'mu_function_name' ) ) { ... } incluyendo el add_action/add_filter correspondiente dentro del bloque.
- AJAX WC: Usar prefijo wc_ajax_mu_
- Rendimiento: Evitar hooks pesados (init/wp_loaded) si hay hooks específicos o carga condicional.
- CSS: NUNCA usar wp_add_inline_style() o wp_add_inline_script(). Todo estilo debe residir en un .css/.js cacheable.
- EXCEPCIÓN login: wp_add_inline_style() está permitido dentro de login_enqueue_scripts exclusivamente para propiedades dinámicas PHP (ej: URL de imagen de logo). El CSS base debe estar en css/login.css.
- Hooks: NUNCA anidar add_filter/add_action dentro de otras funciones hookeadas. Cada hook debe declararse en el scope global del módulo.
- WP Cron: Usar wp_schedule_single_event() para tareas pesadas en background. NUNCA ejecutar queries masivas en shutdown, admin_init o template_redirect de forma síncrona.
- API Keys: NUNCA hardcodear API keys en archivos del repositorio. Usar constantes definidas en wp-config.php.
- DB Queries: NUNCA usar WP_Query / wc_get_orders() con 'limit' => -1 en frontend. Siempre limitar (ej: 8) y cachear con transient cuando aplique.
- Transients: Para shortcodes con WP_Query en frontend, usar set_transient() con TTL razonable (ej: 12h). Clave de transient: mu_[shortcode]_html.

JavaScript
- Aislamiento: IIFE + 'use strict';.
- Ejecución: DOMContentLoaded.
- Cero jQuery salvo obligación de WooCommerce legacy.
- Pasar datos PHP→JS vía wp_localize_script. NUNCA emitir <script> inline con lógica.
- Shuffle arrays: SIEMPRE usar Fisher-Yates. NUNCA usar sort(() => 0.5 - Math.random()).
- Carrusel: NUNCA duplicar la lógica de initCarousels(). Todo nuevo carrusel que use .mu-carousel-wrapper es automáticamente cubierto por js/global-ui.js.

CSS
- Prefijos: .mu-[componente]__elem--[mod] (BEM).
- Sobrescrituras: /* override GP: [motivo] */.
- Variables: SIEMPRE usar variables CSS existentes. NUNCA hardcodear colores con variable disponible.

7. PENDIENTES / DEUDA TÉCNICA

- Evaluar auto-host de libphonenumber-js para eliminar dependencia CDN en checkout.
- Migrar bulk actions de Legacy a HPOS (woocommerce_order_list_table_bulk_actions).
- [PENDIENTE PERFORMANCE] downloads-bonus.php: limitar wc_get_orders() a 'limit' => 50 en mu_user_has_virtual_manual_purchases.
- [PENDIENTE PERFORMANCE] geo.php: evitar doble llamada a wc_get_customer_geolocation() por página.
- [PENDIENTE PERFORMANCE] digital-restriction.php: display_digital_price_in_catalog usa wc_get_product() por variación (N+1). Evaluar get_post_meta() directo.
- [PENDIENTE] flexible-price.php: mu_get_flexible_product_ids() hardcoded. Migrar a get_option().
- [ACCIÓN REQUERIDA] Agregar myAccountUrl al wp_localize_script de muCheckout en functions.php.
- [ACCIÓN REQUERIDA] Definir constante MU_GOOGLE_PLACES_API_KEY en wp-config.php.
- [MIGRADO] Code Snippets "MU Core v2.1", "Addon Nombre v3.0" y "Addon Etiquetas v3.0" migrados al repositorio. Desactivar los 3 snippets en Code Snippets plugin.
- [ACCIÓN REQUERIDA] Desactivar snippet "Login + Password" en Code Snippets.
- [ACCIÓN REQUERIDA] Desactivar y eliminar plugin "DCMS - Estilos Premium Ficha de Producto".
- [ACCIÓN REQUERIDA] Desactivar y eliminar el snippet de Code Snippets que contenga los shortcodes [mu_bestsellers_section] y/o [mu_popcat_section] del panel, una vez mergeado este PR.
