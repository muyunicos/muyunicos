MUY ÜNCOS — ARCHITECTURE & MIGRATION GUIDE

Estado: Modular Pragmático · v2.12.0 · May 23, 2026

Monolithic functions.php DEPRECATED. Toda la lógica vive en inc/, css/ y js/.

⚠️ IA / LLM DIRECTIVE: Leer este documento antes de sugerir cambios de arquitectura.
Compliance estricto con "Pragmatic Modularity" y "Pull Request Workflow" es obligatorio.

════════════════════════════════════════════════════════════════
1. REGLAS CORE
════════════════════════════════════════════════════════════════

MODALIDAD PRAGMÁTICA ("Goldilocks")
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
│   ├── compat-litespeed.php # [SEGUNDO] Compatibilidad LiteSpeed Cache v1.0.0
│   ├── coming-soon.php     # Coming Soon override v1.0.0.
│   ├── geo.php             # Multi-país: detección por subdominio, decimales, modal
│   │                       # sugerencia de país, selector de header, prefijo idioma.
│   │                       # muyu_get_cached_geolocation() — una sola llamada/request.
│   │                       # HTML del modal usa clases BEM .mu-country-modal-* (v3.0.0).
│   │                       # Depende de global-ui.css para .mu-modal-overlay--full,
│   │                       # .mu-modal-box, .mu-modal-close, @keyframes muModalSlideUp.
│   ├── digital-restriction.php
│   ├── auth-modal.php      # Modal Login/Registro + endpoints wc_ajax_mu_*
│   ├── login.php           # Personalización wp-login.php v2.1.0
│   ├── checkout.php        # Checkout Híbrido + Login Gate
│   ├── cart.php            # Multi-item add, buffers BACS
│   ├── flexible-price.php  # Precio Flexible v4.0
│   ├── hero-banners.php    # Hero Banners Manager v1.0.1
│   ├── ui.php              # Header icons, Cart badge, WhatsApp, Search, Footer custom,
│   │                       # Share shortcode, canonical GSK, WPLingua body class,
│   │                       # Category desc mover, precio $0, disable GP featured img.
│   │                       # Shortcodes: [mu_testimonios_section] [mu_bestsellers_section]
│   │                       #             [mu_popcat_section] [mu_hero_section]
│   ├── orders-files.php
│   ├── orders-workflow.php
│   ├── downloads-bonus.php
│   ├── navigation-chips.php
│   ├── products-core.php
│   ├── addon-nombre.php
│   └── addon-etiquetas.php
│
├── templates/
│   └── coming-soon.php
│
├── css/
│   ├── components/
│   │   ├── global-ui.css        # Global: Share, WhatsApp, Search, WPLingua, Carrusel,
│   │   │                        # Breadcrumb, MODAL BASE (v2.12.0):
│   │   │                        #   .mu-modal-overlay--full  — overlay full-screen + backdrop blur
│   │   │                        #   .mu-modal-backdrop       — capa semitransparente independiente
│   │   │                        #   .mu-modal-box            — caja flotante del modal
│   │   │                        #   .mu-modal-close          — botón cerrar estándar (SVG icon)
│   │   │                        #   @keyframes muModalSlideUp — animación de entrada unificada
│   │   ├── header.css
│   │   ├── footer.css
│   │   ├── modal-auth.css       # !is_user_logged_in()
│   │   │                        # Depende de global-ui.css (modal base).
│   │   │                        # Estilos propios: tamaño (max-width:440px), formularios,
│   │   │                        # botones de acción (.mu-btn-*), social login, mensajes.
│   │   ├── country-modal.css    # Condicional (inc/geo.php → mu_country_modal_enqueue)
│   │   │                        # Depende de global-ui.css (modal base) + mu-base.
│   │   │                        # Nomenclatura BEM .mu-country-modal-* (v3.0.0).
│   │   │                        # Estilos propios: tamaño compacto (max-width:370px),
│   │   │                        # botones .mu-country-modal__btn-go / __btn-stay.
│   │   │                        # CLASES ELIMINADAS: #muyu-country-modal-overlay (ID),
│   │   │                        # #muyu-country-modal (ID), #muyu-country-close (ID),
│   │   │                        # .muyu-country-btn, .muyu-country-stay-btn.
│   │   └── navigation-chips.css
│   ├── admin.css
│   ├── admin-hero-banners.css
│   ├── admin-order-files.css
│   ├── admin-orders.css
│   ├── login.css
│   ├── home.css
│   ├── shop.css
│   ├── product.css
│   ├── product-builder.css
│   ├── cart.css
│   ├── checkout.css
│   ├── testimonials.css
│   ├── coming-soon.css          # DEPRECATED — CSS ya no se encola (inline en template v2).
│   └── account-downloads.css
│
└── js/
    ├── global-ui.js
    ├── header.js
    ├── footer.js
    ├── hero.js
    ├── modal-auth.js
    ├── country-modal.js         # Condicional (inc/geo.php)
    │                            # Selectores actualizados a .mu-country-modal-* (v3.0.0).
    │                            # Maneja: show (is-visible), close btn, stay btn, Escape, click-outside.
    ├── shop.js
    ├── navigation-chips.js
    ├── product.js
    ├── addon-nombre.js
    ├── addon-etiquetas.js
    ├── product-builder.js
    ├── cart.js
    ├── flexible-price.js
    ├── checkout.js
    ├── testimonials.js
    ├── admin.js
    ├── admin-hero-banners.js
    ├── admin-order-files.js
    └── admin-orders.js

════════════════════════════════════════════════════════════════
3. ROUTING — ¿DÓNDE VA EL CÓDIGO NUEVO?
════════════════════════════════════════════════════════════════

¿Qué necesitás agregar?             | PHP (inc/)               | CSS                          | JS
------------------------------------|--------------------------|------------------------------|----------------------------
Ajuste UI pequeño (< 50 líneas)     | ui.php                   | components/global-ui.css     | global-ui.js
Header / Footer (elemento pesado)   | ui.php                   | header.css / footer.css      | header.js / footer.js
Multi-país / Geolocalización       | geo.php                  | components/country-modal.css | country-modal.js
Restricción subdominios             | digital-restriction.php  | shop.css / admin.css         | shop.js / admin.js
Redirección categoría en 404        | digital-restriction.php  | —                            | —
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
Breadcrumb (estilos)                | navigation-chips.php     | components/global-ui.css     | —
Pantalla Coming Soon custom         | coming-soon.php          | inline en template           | inline en template
Compatibilidad plugins/caché        | compat-litespeed.php     | —                            | —
Nuevo modal (overlay+caja+close)    | inc/ según contexto      | Extender clases de global-ui | —

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
  Admin UI acepta rutas relativas o URLs absolutas para CTAs y badge promo.
  Para editar contenido: WP Admin → WooCommerce → Marketing → Hero Banners.
- Coming Soon standalone: logo y número WA hardcodeados. Cambios → editar templates/coming-soon.php directamente.
- Category Redirect Map: OPTION_CATEGORY_REDIRECT_MAP se construye en rebuild_digital_indexes().
  Tras agregar productos a una categoría nueva, forzar rebuild desde Admin → Productos → Reindexar Digitales.
- 404 routing: filter_category_terms() NO filtra cuando is_404() = true. Esto es intencional.

JavaScript
- IIFE + 'use strict' + DOMContentLoaded. Cero jQuery salvo obligación WC legacy.
- Datos PHP→JS: wp_localize_script(). NUNCA <script> inline con lógica.
  ⚠️  EXCEPCIÓN: templates/coming-soon.php usa <script> inline porque es standalone.
- Arrays: Fisher-Yates. NUNCA sort(() => 0.5 - Math.random()).
- Carrusel: NUNCA duplicar initCarousels(). Cualquier .mu-carousel-wrapper es automático.
- Hero slider: dots por data-hero-dot (sin onclick inline). Swipe con {passive:true}.
- Animaciones de botón: NUNCA style.transform inline. Usar clases CSS (.is-spinning, etc.)
  que deleguen al motor de animación del browser (GPU-composited).
- Modales: activar/desactivar con clase .is-visible en el overlay.
  NUNCA manipular display:flex directamente desde JS en modales nuevos.

CSS
- Prefijo + BEM: .mu-[componente]__elem--[mod]
- Sobrescrituras GP: /* override GP: [motivo] */
- SIEMPRE variables CSS. NUNCA hardcodear colores con variable disponible.
  ⚠️  EXCEPCIÓN: templates/coming-soon.php inlinea valores fallback porque
      las CSS variables del :root no están disponibles en modo standalone.
- Animaciones: separar transiciones del contenedor y del hijo SVG/icon.
  El hijo SVG debe tener su propio transition:transform + will-change:transform.
- Modales nuevos: extender clases base de global-ui.css (.mu-modal-overlay--full,
  .mu-modal-box, .mu-modal-close) en lugar de redefinir el patrón.

════════════════════════════════════════════════════════════════
6. DEUDA TÉCNICA
════════════════════════════════════════════════════════════════

- [ ] checkout.js: libphonenumber-js desde CDN unpkg.com — evaluar auto-host local.
- [ ] orders-workflow.php: bulk actions Legacy → migrar a HPOS (woocommerce_order_list_table_bulk_actions).
- [ ] digital-restriction.php: N+1 en display_digital_price_in_catalog — evaluar get_post_meta() directo.
- [ ] coming-soon.css: archivo deprecado pero presente. Evaluar eliminarlo si inc/coming-soon.php
      ya no lo encola tras esta migración.
- [ ] digital-restriction.php: OPTION_CATEGORY_REDIRECT_MAP se invalida solo al actualizar productos.
      Evaluar también invalidar en save_term / delete_term si se crean/eliminan categorías.
- [ ] modal-auth.css: .mu-auth-modal usa [style*="display: flex"] como trigger de visibilidad (legacy).
      Evaluar migrar a clase .is-visible (igual que country-modal) para consistencia total.
