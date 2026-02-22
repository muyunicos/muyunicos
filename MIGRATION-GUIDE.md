MUY ÚNICOS — ARCHITECTURE & MIGRATION GUIDE

Estado: Refactor Modular Pragmático · v1.3.5 · Feb 22, 2026

Monolithic functions.php DEPRECATED. Toda la lógica vive en inc/, css/ y js/.

⚠️ IA / LLM DIRECTIVE: Read this document carefully before suggesting architecture changes. Strict compliance with "Pragmatic Modularity" and "Pull Request Workflow" is required.

1. REGLAS CORE DE ARQUITECTURA Y FLUJO DE TRABAJO

Modularidad Pragmática (Regla "Goldilocks")
- NO a la micro-fragmentación.
- Ajustes pequeños de UI (botones, toggles, iconos, micro-interacciones < 50 líneas) DEBEN agruparse en:
  - css/components/global-ui.css
  - js/global-ui.js

SÍ al aislamiento por contexto
- Funcionalidades complejas (Checkout, Cart, Auth) deben tener sus propios archivos y cargarse condicionalmente.

Carga Condicional Estricta
- Nunca cargar assets globales si no aplican a header/footer o UI transversal.
- Usar is_shop(), is_checkout(), is_cart(), is_user_logged_in(), etc. en functions.php.
- NUNCA usar wp_add_inline_style(). Todo CSS debe estar en archivos .css cacheables.

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
│   ├── geo.php                # Sistema multi-país + Digital Restriction (detect, routing, modal)
│   ├── auth-modal.php         # Modal Login/Registro + endpoints WC-AJAX
│   ├── checkout.php           # Optimizaciones WC Checkout + validación + Title fix
│   ├── cart.php               # Lógica de carrito, buffers BACS
│   ├── product.php            # mu_render_linked_product, lógica físico/digital
│   └── ui.php                 # Header, Footer, search form, WhatsApp btn, Canonical fix, WPLingua body class
│
├── css/                       # 🎨 CSS MODULAR (Pragmático)
│   ├── components/            # Componentes compartidos
│   │   ├── global-ui.css      # Global: micro UI (Share, WhatsApp flotante, Search, estilos de WPLingua)
│   │   ├── header.css         # Global: header, navegación, Country Selector
│   │   ├── footer.css         # Global: footer y columnas
│   │   ├── modal-auth.css     # ! is_user_logged_in()
│   │   └── country-modal.css  # Condicional vía inc/geo.php (mu_should_show_country_modal)
│   ├── cart.css               # is_cart()
│   ├── checkout.css           # is_checkout() && ! is_order_received_page()
│   ├── home.css               # is_front_page()
│   ├── product.css            # is_product()
│   └── shop.css               # is_shop() || is_product_category() || is_product_tag()
│
└── js/                        # ⚡ JS MODULAR (IIFE + strict mode + DOMContentLoaded)
    ├── global-ui.js           # Global: country selector, WPLingua toggle, share button
    ├── header.js              # Global: menú móvil, submenús, dropdown cuenta
    ├── footer.js              # Global: comportamiento footer
    ├── cart.js                # is_cart()
    ├── checkout.js            # is_checkout() && ! is_order_received_page()
    ├── modal-auth.js          # ! is_user_logged_in()
    └── country-modal.js       # Condicional — encolado por inc/geo.php

3. INVENTARIO DE ARCHIVOS (Estado Actual)

PHP · inc/

Archivo | Responsabilidad principal
---|---
inc/icons.php | mu_get_icon() — todos los SVGs del tema
inc/geo.php | Detección de país, redirección de dominio, modal de país (enqueue propio), MUYU_Digital_Restriction_System
inc/auth-modal.php | HTML modal auth, endpoints wc_ajax_mu_*
inc/checkout.php | Campos, validaciones, optimizaciones Checkout, Título "Pedido Recibido"
inc/cart.php | Añadir múltiples ítems al carrito, buffers BACS
inc/product.php | mu_render_linked_product(), lógica físico/digital
inc/ui.php | Header icons, Cart badge fragment, WhatsApp btn, Custom Search form, Custom Footer, Share shortcode, Google Site Kit canonical, WPLingua body class (mu_wplng_body_class)

CSS · css/

Archivo | Condición de carga en functions.php
---|---
style.css (raíz) | Global (base)
css/components/global-ui.css | Global (Share Button, WhatsApp flotante, Search Form, WPLingua estilos)
css/components/header.css | Global (Header, Navegación, Country Selector)
css/components/footer.css | Global
css/components/modal-auth.css | ! is_user_logged_in()
css/components/country-modal.css | Condicional — encolado por inc/geo.php
css/cart.css | is_cart()
css/checkout.css | is_checkout() && ! is_order_received_page()
css/product.css | is_product()
css/home.css | is_front_page() (actualmente vacío)
css/shop.css | is_shop() || is_product_category() || is_product_tag() (actualmente vacío)

JS · js/

Archivo | Condición de carga en functions.php
---|---
js/global-ui.js | Global (country selector, WPLingua toggle, share button)
js/header.js | Global
js/footer.js | Global
js/modal-auth.js | ! is_user_logged_in()
js/cart.js | is_cart() — depende de: jquery
js/checkout.js | is_checkout() && ! is_order_received_page() — depende de: jquery, libphonenumber-js
js/country-modal.js | Condicional — encolado por inc/geo.php

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

Disponibles: arrow, search, help, account, cart, close, share, check, instagram, facebook, pinterest, tiktok, youtube

5. ROUTING DE DESARROLLO — ¿Dónde va el código nuevo?

¿Qué necesitás agregar? | PHP (inc/) | CSS (css/) | JS (js/)
---|---|---|---
Ajuste UI pequeño (< 50 líneas) | ui.php | components/global-ui.css | global-ui.js
Elemento pesado Header/Footer | ui.php | components/header.css o footer.css | header.js o footer.js
Lógica multi-país | geo.php | components/country-modal.css | country-modal.js
Flujo de Carrito | cart.php | cart.css | cart.js
Login / Registro Modal | auth-modal.php | components/modal-auth.css | modal-auth.js
Flujo Checkout | checkout.php | checkout.css | checkout.js
Nuevo ícono SVG | icons.php | — | —

6. CONVENCIONES DE CÓDIGO & RENDIMIENTO

PHP
- Protección: if ( ! function_exists( 'mu_function_name' ) ) { ... } incluyendo el add_action/add_filter correspondiente dentro del bloque.
- AJAX WC: Usar prefijo wc_ajax_mu_
- Rendimiento: Evitar hooks pesados (init/wp_loaded) si hay hooks específicos o carga condicional.
- CSS: NUNCA usar wp_add_inline_style(). Todo estilo debe residir en un .css cacheable.
- Hooks: NUNCA anidar add_filter/add_action dentro de otras funciones hookeadas (e.g., dentro de wp_enqueue_scripts). Cada hook debe declararse en el scope global del módulo.

JavaScript
- Aislamiento: IIFE + 'use strict';.
- Ejecución: DOMContentLoaded.
- Cero jQuery salvo obligación de WooCommerce legacy (cart/checkout).

CSS
- Prefijos: .mu-[componente]__[elemento]--[modificador] (BEM).
- Sobrescrituras: /* override GP: [motivo] */.
- Variables: SIEMPRE usar variables CSS existentes (--primario, --blanco, --texto, etc.). NUNCA hardcodear colores que tengan variable disponible.

7. PENDIENTES / DEUDA TÉCNICA

- Evaluar auto-host de libphonenumber-js para eliminar dependencia CDN en checkout.
- Llenar archivos vacíos: css/home.css, css/shop.css.
- Refactor de SVGs en Header Icons hacia inc/icons.php y limpieza de Snippets migrados completada y validada (v1.3.5).