MUY ÚNICOS - ARCHITECTURE & MIGRATION GUIDE

State: Modular Refactor (v1.0.0 - Feb 2026). Monolithic functions.php is deprecated.

1. DIRECTORY TREE & DEPENDENCIES

muyunicos/ (generatepress-child)
├── functions.php              # ONLY core enqueue logic (mu_enqueue_assets, mu_load_module)
├── style.css                  # CSS Variables & child theme header ONLY
├── inc/                       # ⚙️ PHP MODULES
│   ├── icons.php              # LOAD FIRST: mu_get_icon() repository
│   ├── geo.php                # CORE: Country detection, domain routing, Digital Restrictions
│   ├── auth-modal.php         # Login/Register modal HTML & WC-AJAX endpoints
│   ├── checkout.php           # WC Checkout optimizations & field validation
│   ├── cart.php               # Add multiple products, BACS buffers
│   ├── product.php            # Physical/Digital link logic (mu_render_linked_product)
│   └── ui.php                 # Header, Footer, Search, WhatsApp, Share shortcodes
├── css/                       # 🎨 MODULAR CSS
│   ├── components/            # header.css, footer.css, modal-auth.css, share-button.css
│   └── *.css                  # cart.css, checkout.css, home.css, product.css, shop.css
└── js/                        # ⚡ MODULAR JS
    ├── mu-ui-scripts.js       # Global small helpers
    └── *.js                   # header.js, checkout.js, modal-auth.js, etc.


2. THE DESIGN SYSTEM API (CRITICAL)

DO NOT invent new variables. Use these exclusively.

CSS Variables (defined in style.css)

Category

Variables

Colors

--primario (#2B9FCF), --secundario (#FFD77A), --texto (#277292), --exito (#a3ffbc)

Spacing

--mu-space-xs (5px), -sm (10px), -md (20px), -lg (40px), -xl (40px)

Radius

--mu-radius-sm (6px), --mu-radius (12px), -md (16px), -lg (20px), -xl (32px)

Shadows

--mu-shadow-sm, --mu-shadow, --mu-shadow-md, --mu-shadow-lg

Transitions

--mu-transition (0.3s cubic-bezier), --mu-transition-fast (0.2s ease)

SVG Icons API (defined in inc/icons.php)

Call via PHP: echo mu_get_icon('name');
Available icons: arrow, search, close, share, check, instagram, facebook, pinterest, tiktok, youtube.

3. STRICT CODING CONVENTIONS

PHP Guidelines

Protection: Every function MUST be wrapped: if ( ! function_exists('mu_function_name') ) { ... }

Prefixes: Use mu_ for general functions, muyu_ for core/geo functions.

AJAX: Use wc_ajax_ prefix for WooCommerce endpoints (e.g., wc_ajax_mu_check_email).

JS Guidelines

Encapsulation: MUST use IIFE + Strict Mode + DOMContentLoaded.

(function() {
    'use strict';
    const init = () => { /* logic */ };
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();
})();


CSS Guidelines

Prefix: All custom classes MUST start with .mu-.

Breakpoints: Mobile-first. Use @media (min-width: 769px) for desktop overrides.

4. MODULE ROUTING (Where to put new code?)

New Header/Footer element? -> inc/ui.php + css/components/ + js/

New multi-country logic? -> inc/geo.php

Cart logic changes? -> inc/cart.php + css/cart.css

User auth/login changes? -> inc/auth-modal.php
