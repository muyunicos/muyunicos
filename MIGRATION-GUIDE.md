MUY ÚNCOS — ARCHITECTURE & MIGRATION GUIDE

Estado: Refactor Modular Pragmático · v2.1.0 · Apr 7, 2026

Monolithic functions.php DEPRECATED. Toda la lógica vive en inc/, css/ y js/.

⚠️ IA / LLM DIRECTIVE: Read this document carefully before suggesting arquitectura changes. Strict compliance con "Pragmatic Modularity" y "Pull Request Workflow" is required.

1. REGLAS CORE DE ARQUITECTURA Y FLUJO DE TRABAJO

Modularidad Pragmática (Regla "Goldilocks")
- NO a la micro-fragmentación.
- Ajustes pequeños de UI (botones, toggles, iconos, micro-interacciones < 50 líneas) DEBEN agruparse en:
  - css/components/global-ui.css
  - js/global-ui.js

SÍ al aislamiento por contexto
- Funcionalidades complejas (Checkout, Cart, Auth, Shop, Orders, Navigation Chips, Login) deben tener sus propios archivos y cargarse condicionalmente.

Carga Condicional Estricta
- Nunca cargar assets globales si no aplican a header/footer o UI transversal.
- Usar is_shop(), is_checkout(), is_cart(), is_user_logged_in(), etc. en functions.php.
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
│   ├── checkout.php           # ✅ Checkout Híbrido Optimizado (Físico/Digital) + Validación WA + Gestión contraseñas WC
│   ├── cart.php               # Lógica de carrito, buffers BACS
│   ├── flexible-price.php     # ✅ Sistema de Precio Flexible v4.0: IDs configurables, validación, captura, precio dinámico, AJAX handler, enqueue condicional de js/flexible-price.js
│   ├── ui.php                 # ✅ Header, Footer, search form, WhatsApp btn, Canonical fix, WPLingua body class
│   ├── orders-files.php       # ✅ File Manager (Admin/Frontend): Uploads, PDF gen, Downloads endpoint
│   ├── orders-workflow.php    # ✅ Workflow: Status 'Production', Smart Emails, Admin UI (WhatsApp link, Indicador Virtual Manual)
│   ├── downloads-bonus.php    # ✅ Dynamic Bonus & Guides: Archivo bonus + Guía inline para Cat. 18 (Account + Emails)
│   ├── navigation-chips.php   # ✅ Navigation Chips v8: Breadcrumb global + índice compacto de productos + chips de categorías/etiquetas (catálogo WooCommerce)
│   ├── products-core.php      # ✅ Productos Personalizados Core v2.1: MU_UI_Helper, backend automático (carrito/orden), constantes
│   ├── addon-nombre.php       # ✅ Addon Nombre v3.0: Campo nombre personalizado, validación, guardado carrito/orden, editor inline AJAX
│   └── addon-etiquetas.php    # ✅ Addon Etiquetas v3.0: Builder de etiquetas, config, render UI, enqueue JS+config, variaciones
│
├── css/                       # 🎨 CSS MODULAR (Pragmático)
│   ├── admin.css              # is_admin() — Botones reindex, tools internas
│   ├── admin-order-files.css  # ✅ is_admin() && order_edit — Dropzone, Modal Files
│   ├── admin-orders.css       # ✅ is_admin() && order_edit — Status Badge, Indicador Virtual Manual
│   ├── login.css              # ✅ login_enqueue_scripts — Estilos de marca para wp-login.php (cacheable)
│   ├── components/            # Componentes compartidos
│   │   ├── global-ui.css      # ✅ Global: micro UI (Share, WhatsApp flotante, Search, estilos de WPLingua, Carrusel Híbrido)
│   │   ├── header.css         # Global: header, navegación, Country Selector (con hover automático v1.8.7)
│   │   ├── footer.css         # Global: footer y columnas
│   │   ├── modal-auth.css     # ! is_user_logged_in()
│   │   ├── country-modal.css  # Condicional — encolado por inc/geo.php (mu_should_show_country_modal)
│   │   └── navigation-chips.css # ✅ is_shop() || is_product_category() || is_product_tag() || is_product() — estilos de breadcrumb chips + filtros
│   ├── cart.css               # is_cart() — incluye sección "7. PRECIO FLEXIBLE" (.mu-cp-*)
│   ├── checkout.css           # ✅ is_checkout() && ! is_order_received_page()
│   ├── home.css               # is_front_page()
│   ├── shop.css               # ✅ is_shop() || is_product_category() || is_product_tag() || is_product() (Infinite Scroll estilos)
│   ├── product-builder.css    # ✅ is_product() || is_cart() — Estilos unificados: builder, secciones, filas, controles qty, extras, totales, addon nombre (input + editor carrito)
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
    ├── flexible-price.js      # ✅ is_cart() || is_checkout() — Widget edición inline de precio. IIFE+strict. Datos vía wp_localize_script (muFlexiblePrice: ajaxUrl, nonce, i18n). Depende de: jquery.
    ├── checkout.js            # ✅ is_checkout() && ! is_order_received_page() — depende de: jquery, libphonenumber-js
    ├── modal-auth.js          # ! is_user_logged_in()
    ├── country-modal.js       # Condicional — encolado por inc/geo.php
    ├── shop.js                # ✅ is_shop() || is_product_category() || is_product_tag() || is_product() — Lógica de Infinite Scroll JS (Optimized)
    ├── navigation-chips.js    # ✅ is_shop() || is_product_category() || is_product_tag() || is_product() — toggles "Más" de chips de categorías y etiquetas
    ├── addon-nombre.js        # ✅ is_product() || is_cart() — muTransformName, validación add-to-cart, editor inline AJAX. Datos PHP→JS vía muNombreData (ajaxUrl, nonce). Depende de: jquery.
    └── addon-etiquetas.js     # ✅ is_product() (cat 18/19) — Objeto MU completo: init, setupFormatListener, toggleBuilder, setMode, calculate, generateSummary, formatMoney, toggleSubmit, variationInit (reemplaza mu_core_variation_scripts). Datos PHP→JS vía wp_localize_script: MU_Config (general, extras_definitions, items) + muEtiquetasData (currencySymbol). Depende de: jquery.

3. INVENTARIO DE ARCHIVOS (Estado Actual)

PHP · inc/

Archivo | Responsabilidad principal
---|---
inc/icons.php | mu_get_icon() — todos los SVGs del tema
inc/geo.php | Detección de país por dominio, control de decimales por moneda (0 para AR/CL/CO), redirect selector en header, modal sugerencia, prefijo idioma.
inc/digital-restriction.php | Restricción de productos físicos en subdominios v4.1.0. Rebuild de índices via WP Cron (wp_schedule_single_event). ensure_indexes_exist() programa Cron en lugar de ejecutar rebuild síncrono. ELIMINADO: hook shutdown + TRANSIENT_REBUILD. NO ejecutar rebuild directo en admin_init.
inc/auth-modal.php | HTML modal auth, endpoints wc_ajax_mu_*
inc/login.php | Personalización wp-login.php v2.1.0: enqueue de css/login.css + inline background-image del logo (wp_add_inline_style), login_headerurl → home_url(), login_headertext, login_errors → mensaje genérico, mu_smart_login_redirect (admins → URL origen, clientes → myaccount).
inc/checkout.php | Campos, validaciones, optimizaciones Checkout, Título "Pedido Recibido", Gestión contraseñas WC (woocommerce_min_password_strength → 0, dequeue wc-password-strength-meter).
inc/cart.php | Añadir múltiples ítems al carrito, buffers BACS
inc/flexible-price.php | Sistema de Precio Flexible v4.0: mu_get_flexible_product_ids() (mapa O(1)), mu_is_flexible_product(), validación (precio negativo + instancia única), captura con wc_format_decimal, aplicación de precio en woocommerce_before_calculate_totals, guardado de metadatos en orden (_custom_price + Precio Acordado), bloqueo en checkout, widget HTML en woocommerce_after_cart_item_name, AJAX handler mu_ajax_update_flexible_price (nonce mu-price-nonce), enqueue condicional de js/flexible-price.js vía wp_localize_script (muFlexiblePrice).
inc/ui.php | Header icons, Cart badge fragment, WhatsApp btn, Custom Search form, Custom Footer, Share shortcode, Google Site Kit canonical, WPLingua body class, Category Description Mover, Reemplazo precio $0 a "Gratis", Disable GP Featured image HTML
inc/orders-files.php | Gestor de archivos: Hooks Admin (Upload/Delete/PDF), Hooks Email (Links), Hook Account (Tabla Descargas).
inc/orders-workflow.php | Flujo de pedidos: Estado 'wc-production', Helper mu_order_has_virtual_manual_item, Emails inteligentes (Físico/Digital), Admin UI (WhatsApp link, Indicador Virtual Manual).
inc/downloads-bonus.php | Inyección dinámica de archivos bonus para usuarios con compras previas de productos manuales + productos específicos (ej. Líneas de Corte). Inyección inline de guía de uso para productos Cat. 18 virtuales (Email + Account Downloads).
inc/navigation-chips.php | Navigation Chips v8: Breadcrumb global con chips + índice compacto de productos (transient mu_navchips_product_index) + chips de categorías/etiquetas con conteos y herramientas admin para regenerar índice.
inc/products-core.php | Productos Personalizados Core v2.1: MU_CORE_VERSION + MU_CORE_ACTIVE constantes, mu_core_is_active(), MU_UI_Helper (render_section, render_row), Backend automático: mu_core_add_cart_item_data (woocommerce_add_cart_item_data), mu_core_set_cart_price (woocommerce_before_calculate_totals), mu_core_display_cart_meta (woocommerce_get_item_data), mu_core_save_order_meta (woocommerce_checkout_create_order_line_item). Admin notice en code-snippets screen.
inc/addon-nombre.php | Addon Nombre v3.0. Dependencia: products-core.php. mu_nombre_agregar_campo (woocommerce_before_add_to_cart_button, prioridad 15) para cat 18/62/19. mu_nombre_guardar_en_carrito (woocommerce_add_cart_item_data). mu_nombre_mostrar_en_carrito (woocommerce_get_item_data). mu_nombre_guardar_en_orden (woocommerce_checkout_create_order_line_item). mu_nombre_validar_campo (woocommerce_add_to_cart_validation, prioridad 20). mu_nombre_cart_edit_html (woocommerce_after_cart_item_name). AJAX handler: mu_update_cart_custom_name (wp_ajax + wp_ajax_nopriv).
inc/addon-etiquetas.php | Addon Etiquetas v3.0. Dependencia: products-core.php. mu_etiquetas_get_configuracion (config de productos/extras/items). mu_etiquetas_render_v3 (woocommerce_before_add_to_cart_button, prioridad 10) para cat 19/18. mu_etiquetas_render_total_box (woocommerce_before_add_to_cart_button, prioridad 20). mu_render_group + mu_render_extra_specific (helpers de renderizado). mu_etiquetas_enqueue_builder_js (wp_enqueue_scripts, prioridad 25): enqueue condicional de js/addon-etiquetas.js + wp_localize_script MU_Config y muEtiquetasData.

CSS · css/

Archivo | Condición de carga en functions.php
---|---
style.css (raíz) | Global (base)
css/admin.css | is_admin() && current_screen == 'product'
css/admin-order-files.css | is_admin() && order_edit (Dropzone styles)
css/admin-orders.css | is_admin() && order_edit (Badge styles, Indicador Virtual Manual)
css/login.css | login_enqueue_scripts — exclusivo para wp-login.php (encolado desde inc/login.php)
css/account-downloads.css | is_account_page() && is_wc_endpoint_url('downloads')
css/components/global-ui.css | Global (Share Button, WhatsApp flotante, Search Form, WPLingua estilos, Carrusel Híbrido CSS)
css/components/header.css | Global (Header, Navegación, Country Selector con hover v1.8.7)
css/components/footer.css | Global
css/components/modal-auth.css | ! is_user_logged_in()
css/components/country-modal.css | Condicional — encolado por inc/geo.php
css/components/navigation-chips.css | is_shop() || is_product_category() || is_product_tag() || is_product() (Breadcrumb chips + filtros)
css/cart.css | is_cart() — incluye sección de estilos .mu-cp-* para widget de precio flexible
css/checkout.css | is_checkout() && ! is_order_received_page()
css/home.css | is_front_page() (actualmente vacío)
css/product-builder.css | is_product() || is_cart() — Estilos unificados del Product Builder: secciones acordeón, filas de productos, controles qty, extras (checkbox/radio/textarea), caja de totales, addon nombre (input, editor inline carrito). Variables --mu-builder-* definidas en style.css.
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
js/flexible-price.js | is_cart() || is_checkout() — Widget inline precio flexible. IIFE+strict. Datos PHP→JS vía muFlexiblePrice (ajaxUrl, nonce, i18n). Depende de: jquery.
js/checkout.js | is_checkout() && ! is_order_received_page() — depende de: jquery, libphonenumber-js
js/country-modal.js | Condicional — encolado por inc/geo.php
js/shop.js | is_shop() || is_product_category() || is_product_tag() || is_product() — Lógica de Infinite Scroll JS (Optimized).
js/navigation-chips.js | is_shop() || is_product_category() || is_product_tag() || is_product() — toggles "Más" de chips de categorías y etiquetas
js/addon-nombre.js | is_product() || is_cart() — muTransformName() (title/upper), validación client-side al add-to-cart, editor inline AJAX en carrito. Datos PHP→JS vía muNombreData (ajaxUrl, nonce). Depende de: jquery.
js/addon-etiquetas.js | is_product() (cat 18/19) — Objeto MU completo: init, setupFormatListener, toggleBuilder, setMode, calculate, generateSummary, formatMoney, toggleSubmit, variationInit (reemplaza mu_core_variation_scripts). Datos PHP→JS vía wp_localize_script: MU_Config (general, extras_definitions, items) + muEtiquetasData (currencySymbol). Depende de: jquery.

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
Precio Flexible (productos con monto libre) | flexible-price.php | cart.css (§ Precio Flexible) | flexible-price.js
Login / Registro Modal | auth-modal.php | components/modal-auth.css | modal-auth.js
Personalización wp-login.php | login.php | css/login.css | —
Flujo Checkout | checkout.php | checkout.css | checkout.js
Catálogo / Single Product | ui.php / geo.php / navigation-chips.php | shop.css / components/navigation-chips.css | shop.js / navigation-chips.js
Gestor Archivos Pedido | orders-files.php | admin-order-files.css | admin-order-files.js
Workflow Pedidos | orders-workflow.php | admin-orders.css | admin-orders.js
Inyección Descargas Bonus + Guías | downloads-bonus.php | — | —
Nuevo ícono SVG | icons.php | — | —
Builder de producto personalizado | products-core.php | product-builder.css | —
Addon campo nombre etiquetas | addon-nombre.php | product-builder.css (§10,§11) | addon-nombre.js
Addon builder etiquetas | addon-etiquetas.php | product-builder.css | addon-etiquetas.js

6. CONVENCIONES DE CÓDIGO & RENDIMIENTO

PHP
- Protección: if ( ! function_exists( 'mu_function_name' ) ) { ... } incluyendo el add_action/add_filter correspondiente dentro del bloque.
- AJAX WC: Usar prefijo wc_ajax_mu_
- Rendimiento: Evitar hooks pesados (init/wp_loaded) si hay hooks específicos o carga condicional.
- CSS: NUNCA usar wp_add_inline_style() o wp_add_inline_script(). Todo estilo debe residir en un .css/.js cacheable.
- EXCEPCIÓN login: wp_add_inline_style() está permitido dentro de login_enqueue_scripts exclusivamente para propiedades dinámicas PHP (ej: URL de imagen de logo). El CSS base debe estar en css/login.css.
- Hooks: NUNCA anidar add_filter/add_action dentro de otras funciones hookeadas (e.g., dentro de wp_enqueue_scripts). Cada hook debe declararse en el scope global del módulo.
- WP Cron: Usar wp_schedule_single_event() para tareas pesadas en background (ej: rebuild de índices). NUNCA ejecutar queries masivas en shutdown, admin_init o template_redirect de forma síncrona.

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
- [PENDIENTE PERFORMANCE] downloads-bonus.php: limitar wc_get_orders() a 'limit' => 50 en mu_user_has_virtual_manual_purchases para evitar carga masiva en usuarios con muchos pedidos.
- [PENDIENTE PERFORMANCE] geo.php: evitar doble llamada a wc_get_customer_geolocation() por página (mu_should_show_country_modal + mu_country_modal_html).
- [PENDIENTE PERFORMANCE] digital-restriction.php: display_digital_price_in_catalog usa wc_get_product() por variación en catálogo (N+1). Evaluar reemplazar con get_post_meta() directo.
- [PENDIENTE] flexible-price.php: mu_get_flexible_product_ids() actualmente hardcoded con IDs 1 y 2. Migrar a opción de WordPress (get_option) o custom field de producto para administración sin tocar código.
- [MIGRADO] Code Snippets "MU Core v2.1", "Addon Nombre v3.0" y "Addon Etiquetas v3.0" migrados al repositorio. Desactivar los 3 snippets en Code Snippets plugin después de verificar el PR.
- [ACCIÓN REQUERIDA] Desactivar el snippet "Login + Password" en Code Snippets después de mergear este PR.
