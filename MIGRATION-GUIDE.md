MUY ÜNCOS — ARCHITECTURE & MIGRATION GUIDE

Estado: Modular Pragmático · v2.9.0 · Apr 28, 2026

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
│   │                       # Sirve templates/coming-soon.php con status 503 y exit().
│   │                       # IMPORTANTE: la plantilla es standalone (no wp_head/wp_footer).
│   │                       # Bypass: admin, AJAX, REST, wc-ajax, manage_options.
│   │                       # css/coming-soon.css YA NO SE ENCOLA (CSS inline en template).
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
│   ├── hero-banners.php    # Hero Banners Manager v1.0.0 — admin submenu bajo WC Marketing
│   │                       # (parent slug 'woocommerce-marketing' → screen
│   │                       # 'marketing_page_mu-hero-banners'). Storage en wp_option
│   │                       # 'mu_hero_banners' (array de promos). Cache transient
│   │                       # 'mu_hero_banners_active' (TTL 12h, invalidado en
│   │                       # update_option/add_option). Helpers públicos:
│   │                       # mu_get_hero_banners() — devuelve activos por fecha (filtrado
│   │                       # con DateTime::createFromFormat 'dmY'); mu_get_hero_banners_raw()
│   │                       # — devuelve la lista cruda (admin). Si la opción no existe,
│   │                       # mu_hero_banners_default_seed() provee la semilla legacy.
│   │                       # Admin UI: WP Media picker para imagen, add/remove rows,
│   │                       # PRG redirect tras guardar. Capability: 'manage_woocommerce'.
│   │                       # Assets admin (css/admin-hero-banners.css + js/admin-hero-banners.js)
│   │                       # SOLO en hook 'marketing_page_mu-hero-banners'.
│   ├── ui.php              # Header icons, Cart badge, WhatsApp, Search, Footer custom,
│   │                       # Share shortcode, canonical GSK, WPLingua body class,
│   │                       # Category desc mover, precio $0, disable GP featured img.
│   │                       # Shortcodes: [mu_testimonios_section] [mu_bestsellers_section]
│   │                       #             [mu_popcat_section] [mu_hero_section]
│   │                       # [mu_hero_section] consume mu_get_hero_banners() de hero-banners.php.
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
│   └── coming-soon.php     # STANDALONE v2.0.0 — NO usa wp_head() ni wp_footer().
│                           # CSS inlineado en <style> + JS mínimo inline.
│                           # Descarga total < 30 KB. Cero recursos WP/WC/plugins.
│                           # Contenido: logo, título multi-idioma rotativo (ES/EN/PT/IT/FR),
│                           # subtítulo fijo, botón WhatsApp con SVG inline.
│                           # Número WA y logo hardcodeados (estables, evita llamadas DB).
│
├── css/                    # CSS modular — siempre carga condicional
│   ├── components/
│   │   ├── global-ui.css        # Global: Share, WhatsApp, Search, WPLingua, Carrusel
│   │   ├── header.css           # Global
│   │   ├── footer.css           # Global
│   │   ├── modal-auth.css       # !is_user_logged_in()
│   │   ├── country-modal.css    # Condicional (inc/geo.php → mu_country_modal_enqueue)
│   │   └── navigation-chips.css # is_shop() || is_product_category() || is_product_tag() || is_product()
│   │                            # Fix v2.8.3: chip actual (.mu-navchips-current span) normalizado
│   │                            # con font-size/padding/line-height iguales a los crumbs anteriores.
│   │                            # .mu-share-btn dentro del chip: display:inline-flex, padding:0,
│   │                            # svg 14×14px — no altera la altura del chip.
│   │                            # SVGs dentro de .mu-navchips-icon-link forzados a 16×16px.
│   ├── admin.css                # is_admin()
│   ├── admin-hero-banners.css   # SOLO hook 'marketing_page_mu-hero-banners' (hero-banners.php)
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
│   │                            # .mu-refresh-satellite: transiciones separadas botón/SVG.
│   │                            # Botón: transition background+box-shadow.
│   │                            # SVG: transition transform + will-change:transform (GPU).
│   │                            # Click: clase .is-spinning dispara @keyframes mu-spin-once.
│   ├── coming-soon.css          # DEPRECATED — CSS ya no se encola (inline en template v2).
│   │                            # Mantener archivo como referencia pero no encolarlo.
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
    │                            # Format toggle (variación #pa_formato): "impresas" activa
    │                            # el builder; cualquier otro valor (ej. archivo-digital-pdf)
    │                            # lo desactiva. State stash/restore: MU.snapshotState() guarda
    │                            # la selección en MU.stashedState al cambiar a digital y
    │                            # MU.restoreState() + MU.rebuildUIFromState() la reponen al
    │                            # volver a "impresas". En digital: mu_data_input queda vacío
    │                            # y disabled, #mu-selection-summary se limpia, #mu-final-price
    │                            # = $0, #mu-total-wrapper oculto y precio WooCommerce nativo
    │                            # visible (no se aplica .mu-replaced cuando isBuilderActive=false).
    ├── product-builder.js       # (reservado — lógica builder si se separa de addons)
    ├── cart.js                  # is_cart()
    ├── flexible-price.js        # is_cart() || is_checkout() — mu_flexible_price_enqueue()
    ├── checkout.js              # is_checkout() && !is_order_received_page()
    ├── testimonials.js          # has_shortcode('mu_testimonios_section')
    │                            # Click en .mu-refresh-satellite: clase .is-spinning
    │                            # (sin style.transform inline). Sync con animación CSS 400ms.
    ├── admin.js                 # is_admin()
    ├── admin-hero-banners.js    # SOLO hook 'marketing_page_mu-hero-banners' (jQuery + wp.media picker)
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
Hero promos dinámicas (storage)     | hero-banners.php         | admin-hero-banners.css       | admin-hero-banners.js
Hero promos dinámicas (render)      | ui.php (mu_hero_section) | home.css                     | hero.js
Nuevo icóno SVG                     | icons.php                | —                            | —
Pantalla Coming Soon custom         | coming-soon.php          | inline en template           | inline en template

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
  ⚠️  EXCEPCIÓN: templates/coming-soon.php usa SVG inline directo porque
      mu_get_icon() requiere ABSPATH y el template se sirve antes del
      ciclo completo de WP (standalone). En ese contexto es correcto.

  Disponibles: arrow · search · help · account · cart · close · share · check
               lock · home · book · instagram · facebook · pinterest · tiktok · youtube

  Notas:
  · home  → usado en breadcrumb (.mu-navchips-icon-link, primer chip)
  · book  → usado en breadcrumb contexto blog (.mu-navchips-icon-link--context)

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
  Datos vienen de mu_get_hero_banners() (inc/hero-banners.php) — NO hardcodear arrays nuevos en mu_hero_section().
  Para editar contenido: WP Admin → WooCommerce → Marketing → Hero Banners.
- Coming Soon standalone: logo y número WA hardcodeados. Cambios → editar templates/coming-soon.php directamente.

JavaScript
- IIFE + 'use strict' + DOMContentLoaded. Cero jQuery salvo obligación WC legacy.
- Datos PHP→JS: wp_localize_script(). NUNCA <script> inline con lógica.
  ⚠️  EXCEPCIÓN: templates/coming-soon.php usa <script> inline porque es standalone.
- Arrays: Fisher-Yates. NUNCA sort(() => 0.5 - Math.random()).
- Carrusel: NUNCA duplicar initCarousels(). Cualquier .mu-carousel-wrapper es automático.
- Hero slider: dots por data-hero-dot (sin onclick inline). Swipe con {passive:true}.
- Animaciones de botón: NUNCA style.transform inline. Usar clases CSS (.is-spinning, etc.)
  que deleguen al motor de animación del browser (GPU-composited).

CSS
- Prefijo + BEM: .mu-[componente]__elem--[mod]
- Sobrescrituras GP: /* override GP: [motivo] */
- SIEMPRE variables CSS. NUNCA hardcodear colores con variable disponible.
  ⚠️  EXCEPCIÓN: templates/coming-soon.php inlinea valores fallback porque
      las CSS variables del :root no están disponibles en modo standalone.
- Animaciones: separar transiciones del contenedor y del hijo SVG/icon.
  El hijo SVG debe tener su propio transition:transform + will-change:transform.

════════════════════════════════════════════════════════════════
6. DEUDA TÉCNICA
════════════════════════════════════════════════════════════════

- [ ] checkout.js: libphonenumber-js desde CDN unpkg.com — evaluar auto-host local.
- [ ] orders-workflow.php: bulk actions Legacy → migrar a HPOS (woocommerce_order_list_table_bulk_actions).
- [ ] digital-restriction.php: N+1 en display_digital_price_in_catalog — evaluar get_post_meta() directo.
- [ ] coming-soon.css: archivo deprecado pero presente. Evaluar eliminarlo si inc/coming-soon.php
      ya no lo encola tras esta migración.
