MUY ÚNCOS — ARCHITECTURE & MIGRATION GUIDE

Estado: Modular Pragmático · v2.7.0 · Apr 27, 2026

Monolithic functions.php DEPRECATED. Toda la lógica vive en inc/, css/ y js/.

⚠️ IA / LLM DIRECTIVE: Leer este documento antes de sugerir cambios de arquitectura.
Compliance estricto con "Pragmatic Modularity" y "Pull Request Workflow" es obligatorio.

════════════════════════════════════════════════════════════════
1. REGLAS CORE
════════════════════════════════════════════════════════════════

MODULARIDAD PRAGMÁTICA ("Goldilocks")
- Ajustes pequeños de UI < 50 líneas → agrupar en global-ui.css / global-ui.js
- Funcionalidades complejas o aisladas → archivo propio, carga condicional

CARGA CONDICIONAL ESTRICTA
- Nunca cargar assets globalmente si no aplican a header/footer/UI transversal.
- Usar is_shop(), is_checkout(), is_cart(), is_user_logged_in(), is_product(),
  has_shortcode(), is_front_page(), etc.
- NUNCA wp_add_inline_style() / wp_add_inline_script() — todo CSS/JS en archivos cacheables.
  · EXCEPCIÓN login: wp_add_inline_style() dentro de login_enqueue_scripts está permitido
    solo para propiedades dinámicas PHP (ej: URL de imagen). CSS base siempre en login.css.
  · EXCEPCIÓN emails: style="" inline es obligatorio en fragmentos HTML de email.
    Los clientes de correo no soportan hojas externas.

FLUJO GITHUB (PROHIBIDO COMMIT DIRECTO A MAIN)
- Rama semántica obligatoria: perf/, refactor/, fix/, feat/
- Todo cambio requiere Pull Request contra main.
- Actualizar SIEMPRE este archivo en el PR (estado actual, no changelog).

════════════════════════════════════════════════════════════════
2. SYSTEM MAP — ÁRBOL DE DIRECTORIOS
════════════════════════════════════════════════════════════════

muyunicos/ (generatepress-child)
│
├── functions.php           # Enqueue central (mu_enqueue_assets) + mu_load_module()
├── style.css               # Variables CSS globales (:root), reset, child theme header
│
├── inc/                    # Módulos PHP — lógica de negocio y hooks
│   ├── icons.php           # [PRIMERO] mu_get_icon() — repositorio SVG
│   ├── coming-soon.php     # Coming Soon override v1.0.0. Intercepta template_redirect
│   │                       # (prioridad 0) cuando Hostinger Coming Soon está activo.
│   │                       # Sirve templates/coming-soon.php con status 503.
│   │                       # Bypass: admin, AJAX, REST, wc-ajax, manage_options.
│   │                       # Enqueue css/coming-soon.css vía mu_coming_soon_enqueue().
│   ├── geo.php             # Multi-país: detección por subdominio, decimales, modal
│   │                       # sugerencia de país, selector de header, prefijo idioma.
│   │                       # muyu_get_cached_geolocation() — una sola llamada/request.
│   ├── digital-restriction.php  # Restricción productos físicos por subdominio v4.1.0.
│   │                            # Rebuild de índice vía wp_schedule_single_event().
│   ├── auth-modal.php      # Modal Login/Registro + endpoints wc_ajax_mu_*
│   ├── login.php           # Personalización wp-login.php v2.1.0
│   ├── checkout.php        # Checkout Híbrido + Login Gate (mu_checkout_login_notice p5)
│   ├── cart.php            # Multi-item add, buffers BACS
│   ├── flexible-price.php  # Precio Flexible v4.0: mapa O(1), validación, AJAX handler.
│   │                       # Encola flexible-price.js vía mu_flexible_price_enqueue().
│   │                       # NO agregar a mu_enqueue_assets() — causaría duplicado.
│   ├── ui.php              # Header icons, Cart badge, WhatsApp, Search, Footer custom,
│   │                       # Share shortcode, canonical GSK, WPLingua body class,
│   │                       # Category desc mover, precio $0, disable GP featured img.
│   │                       # Shortcodes: [mu_testimonios_section] [mu_bestsellers_section]
│   │                       #             [mu_popcat_section] [mu_hero_section]
│   │                       # mu_home_sections_enqueue() → home.css + hero.js en front_page.
│   ├── orders-files.php    # Gestor de archivos de pedido: Admin + Email + Mi Cuenta
│   ├── orders-workflow.php # Estado 'wc-production', emails inteligentes, Admin UI
│   ├── downloads-bonus.php # Bonus & Guías v1.2.0: inyección tabla descargas + emails.
│   │                       # mu_user_has_cat_18_custom_files() — transient mu_cat18_files_{uid}
│   │                       # (TTL 12h), invalidado en woocommerce_order_status_changed.
│   ├── navigation-chips.php # Navigation Chips v8: breadcrumb, índice compacto, chips,
│   │                        # transient por categoría.
│   ├── products-core.php   # Core v2.1: constantes, MU_UI_Helper, hooks carrito/orden
│   ├── addon-nombre.php    # Addon Nombre v3.0: campo, validación, editor inline AJAX
│   └── addon-etiquetas.php # Addon Etiquetas v3.0: builder, config, render UI, enqueue
│
├── templates/              # Plantillas PHP standalone (fuera del loop de GP)
│   └── coming-soon.php     # Pantalla Coming Soon custom. Servida por inc/coming-soon.php.
│                           # Incluye logo, título, tagline, CTA WhatsApp, shapes deco.
│
├── css/                    # CSS modular — siempre carga condicional
│   ├── components/
│   │   ├── global-ui.css        # Global: Share, WhatsApp, Search, WPLingua, Carrusel
│   │   ├── header.css           # Global
│   │   ├── footer.css           # Global
│   │   ├── modal-auth.css       # !is_user_logged_in()
│   │   ├── country-modal.css    # Condicional (inc/geo.php → mu_country_modal_enqueue)
│   │   └── navigation-chips.css # is_shop() || is_product_category() || is_product_tag() || is_product()
│   ├── admin.css                # is_admin()
│   ├── admin-order-files.css    # is_admin() + order edit
│   ├── admin-orders.css         # is_admin() + order edit
│   ├── login.css                # login_enqueue_scripts
│   ├── home.css                 # is_front_page() — tarjetas, Hero Promos (.mu-hero-promo-*)
│   ├── shop.css                 # is_shop() || ...
│   ├── product.css              # is_product()
│   ├── product-builder.css      # is_product() || is_cart()
│   ├── cart.css                 # is_cart()
│   ├── checkout.css             # is_checkout() && !is_order_received_page()
│   ├── testimonials.css         # has_shortcode('mu_testimonios_section')
│   ├── coming-soon.css          # Condicional: solo cuando mu_is_hostinger_coming_soon_active()
│   └── account-downloads.css   # is_account_page() && is_wc_endpoint_url('downloads')
│                                # .mu-custom-downloads · .mu-guide-link
│
└── js/                     # JS modular — IIFE + 'use strict' + DOMContentLoaded
    ├── global-ui.js             # Global — initCarousels() para todo .mu-carousel-wrapper
    ├── header.js                # Global
    ├── footer.js                # Global
    ├── hero.js                  # is_front_page() — slider, autoplay 7s, swipe, dots
    ├── modal-auth.js            # !is_user_logged_in()
    ├── country-modal.js         # Condicional (inc/geo.php)
    ├── shop.js                  # is_shop() || ...
    ├── navigation-chips.js      # is_shop() || ...
    ├── product.js               # is_product()
    ├── addon-nombre.js          # is_product() || is_cart()
    ├── addon-etiquetas.js       # is_product() (cat 18/19)
    ├── product-builder.js       # (reservado — lógica builder si se separa de addons)
    ├── cart.js                  # is_cart()
    ├── flexible-price.js        # is_cart() || is_checkout() — mu_flexible_price_enqueue()
    ├── checkout.js              # is_checkout() && !is_order_received_page()
    ├── testimonials.js          # has_shortcode('mu_testimonios_section')
    ├── admin.js                 # is_admin()
    ├── admin-order-files.js     # is_admin() + order edit
    └── admin-orders.js          # is_admin() + order edit

════════════════════════════════════════════════════════════════
3. ROUTING — ¿DÓNDE VA EL CÓDIGO NUEVO?
════════════════════════════════════════════════════════════════

¿Qué necesitás agregar?             | PHP (inc/)               | CSS                          | JS
------------------------------------|--------------------------|------------------------------|----------------------------
Ajuste UI pequeño (< 50 líneas)     | ui.php                   | components/global-ui.css     | global-ui.js
Header / Footer (elemento pesado)   | ui.php                   | header.css / footer.css      | header.js / footer.js
Multi-país / Geolocalización       | geo.php                  | components/country-modal.css | country-modal.js
Restricción subdominios             | digital-restriction.php  | shop.css / admin.css         | shop.js / admin.js
Carrito                             | cart.php                 | cart.css                     | cart.js
Precio Flexible                     | flexible-price.php       | cart.css                     | flexible-price.js
Login/Registro Modal                | auth-modal.php           | components/modal-auth.css    | modal-auth.js
Personalización wp-login.php        | login.php                | login.css                    | —
Checkout                            | checkout.php             | checkout.css                 | checkout.js
Catálogo / Grid                     | navigation-chips.php     | shop.css                     | shop.js / navigation-chips.js
Ficha de producto (single)          | ui.php                   | product.css                  | product.js
Gestor archivos pedido              | orders-files.php         | admin-order-files.css        | admin-order-files.js
Workflow pedidos                    | orders-workflow.php      | admin-orders.css             | admin-orders.js
Descargas Bonus + Guías             | downloads-bonus.php      | account-downloads.css        | —
Builder producto personalizado      | products-core.php        | product-builder.css          | —
Addon campo nombre                  | addon-nombre.php         | product-builder.css          | addon-nombre.js
Addon builder etiquetas             | addon-etiquetas.php      | product-builder.css          | addon-etiquetas.js
Shortcode Testimonios               | ui.php                   | testimonials.css             | testimonials.js
Shortcodes Home (carrusel/sección)  | ui.php                   | home.css                     | — (reutiliza initCarousels)
Hero promos dinámicas               | ui.php                   | home.css                     | hero.js
Nuevo icóno SVG                     | icons.php                | —                            | —
Pantalla Coming Soon custom         | coming-soon.php          | coming-soon.css              | —

════════════════════════════════════════════════════════════════
4. SISTEMA DE DISEÑO (API EXCLUSIVA)
════════════════════════════════════════════════════════════════

⚠️ NO inventar variables nuevas. Solo las definidas en :root de style.css.

VARIABLES CSS

Categoría    | Variables
-------------|--------------------------------------------------------------------------
Colores      | --primario (#2B9FCF)  --secundario (#FFD77A)  --texto  --blanco  --fondo
Spacing      | --mu-space-xs (5px)  --mu-space-sm (10px)  --mu-space-md (20px)  --mu-space-lg (40px)
Radius       | --mu-radius-sm (6px)  --mu-radius (12px)  --mu-radius-md  --mu-radius-full (9999px)
Sombras      | --mu-shadow-sm  --mu-shadow  --mu-shadow-md  --mu-shadow-lg
Tipografía   | --mu-font-display (Fredoka One)  --mu-font-base (Inter)
Builder      | --mu-builder-accent (#6c5ce7)  --mu-builder-accent-hover  --mu-builder-accent-bg
             | --mu-builder-text  --mu-builder-text-muted  --mu-builder-success
             | --mu-builder-danger  --mu-builder-danger-dark  --mu-builder-border
             | --mu-builder-border-dark  --mu-builder-bg-light  --mu-builder-bg-subtle
             | --mu-builder-bg-muted  --mu-builder-bg-option

ICONOS SVG
  echo mu_get_icon('name');  // NUNCA inline SVG directo

  Disponibles: arrow · search · help · account · cart · close · share · check
               lock · instagram · facebook · pinterest · tiktok · youtube

════════════════════════════════════════════════════════════════
5. CONVENCIONES DE CÓDIGO
════════════════════════════════════════════════════════════════

PHP
- Siempre: if ( ! function_exists( 'mu_fn' ) ) { ... add_action/filter dentro del bloque }
- AJAX WC: prefijo wc_ajax_mu_
- Evitar hooks pesados (init/wp_loaded) cuando hay alternativas más específicas.
- WP Cron: wp_schedule_single_event() para tareas pesadas. NUNCA en shutdown/admin_init/template_redirect.
- API Keys: NUNCA hardcodear. Usar constantes en wp-config.php.
- DB Queries: NUNCA 'limit' => -1 en frontend. Siempre limitar + transient.
- Transients: clave mu_[contexto]_{id}. TTL razonable. Invalidar en el hook de cambio de estado.
- Geolocalización: SIEMPRE muyu_get_cached_geolocation(). NUNCA wc_get_customer_geolocation() directo.
- Hero promos: fechas con DateTime::createFromFormat('dmY'). Primer slide: loading="eager" fetchpriority="high".

JavaScript
- IIFE + 'use strict' + DOMContentLoaded. Cero jQuery salvo obligación WC legacy.
- Datos PHP→JS: wp_localize_script(). NUNCA <script> inline con lógica.
- Arrays: Fisher-Yates. NUNCA sort(() => 0.5 - Math.random()).
- Carrusel: NUNCA duplicar initCarousels(). Cualquier .mu-carousel-wrapper es automático.
- Hero slider: dots por data-hero-dot (sin onclick inline). Swipe con {passive:true}.

CSS
- Prefijo + BEM: .mu-[componente]__elem--[mod]
- Sobrescrituras GP: /* override GP: [motivo] */
- SIEMPRE variables CSS. NUNCA hardcodear colores con variable disponible.

════════════════════════════════════════════════════════════════
6. DEUDA TÉCNICA
════════════════════════════════════════════════════════════════

- [ ] checkout.js: libphonenumber-js desde CDN unpkg.com — evaluar auto-host local.
- [ ] orders-workflow.php: bulk actions Legacy → migrar a HPOS (woocommerce_order_list_table_bulk_actions).
- [ ] digital-restriction.php: N+1 en display_digital_price_in_catalog — evaluar get_post_meta() directo.
