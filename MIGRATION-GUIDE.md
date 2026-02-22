MUY ÚNICOS — ARCHITECTURE & MIGRATION GUIDE

Estado: Refactor Modular Pragmático · v1.1.0 · Feb 2026

Monolithic functions.php DEPRECATED. Toda la lógica vive en inc/, css/ y js/.

⚠️ IA / LLM DIRECTIVE: Read this document carefully before suggesting architecture changes. Strict compliance with "Pragmatic Modularity" and "Pull Request Workflow" is required.

1. REGLAS CORE DE ARQUITECTURA Y FLUJO DE TRABAJO

Modularidad Pragmática (Regla "Goldilocks"): - NO a la micro-fragmentación. Pequeños ajustes de UI (botones flotantes, iconos, toggles < 50 líneas) DEBEN agruparse en css/components/global-ui.css y js/global-ui.js.

SÍ al aislamiento por contexto. Funcionalidades complejas (Checkout, Cart, Auth) deben tener sus propios archivos y cargarse condicionalmente.

Carga Condicional Estricta: - Usa is_shop(), is_checkout(), is_cart(), is_user_logged_in() en functions.php para evitar bloquear el renderizado global (Render-blocking bloat).

Flujo GitHub (PROHIBIDO COMMIT A MAIN):

Todo cambio debe ir en una rama semántica (perf/, refactor/, fix/, feat/).

Todo cambio requiere un Pull Request (PR). El título debe ser descriptivo.

Actualiza SIEMPRE las tablas de este archivo al hacer un PR, modificando los tamaños o agregando nuevas rutas si es estrictamente necesario.

2. ÁRBOL DE DIRECTORIOS

muyunicos/ (generatepress-child)
│
├── functions.php              # SOLO: mu_enqueue_assets (condicional) + mu_load_module
├── style.css                  # Variables CSS, reset, y child theme header
│
├── inc/                       # ⚙️ MÓDULOS PHP (Lógica de negocio y hooks)
│   ├── icons.php              # [CARGA PRIMERO] mu_get_icon() — repositorio de SVGs
│   ├── geo.php                # Sistema multi-país: detección, routing
│   ├── auth-modal.php         # Modal Login/Registro + endpoints WC-AJAX
│   ├── checkout.php           # Optimizaciones WC Checkout + validación
│   ├── cart.php               # Lógica de carrito, buffers BACS
│   ├── product.php            # mu_render_linked_product, lógica físico/digital
│   └── ui.php                 # Header, Footer, shortcodes (búsqueda, WhatsApp)
│
├── css/                       # 🎨 CSS MODULAR (Pragmático)
│   ├── components/            # Componentes compartidos
│   │   ├── global-ui.css      # [NUEVO] Agrupa: WhatsApp, Share, Search icon, WPLingua
│   │   ├── header.css         # Estilos header, navegación, country selector
│   │   ├── footer.css         # Estilos footer y columnas
│   │   ├── modal-auth.css     # Modal login/registro (solo !is_user_logged_in)
│   │   └── country-modal.css  # Modal de selección de país (geo)
│   ├── cart.css               # is_cart()
│   ├── checkout.css           # is_checkout()
│   ├── home.css               # is_front_page()
│   ├── product.css            # is_product()
│   └── shop.css               # is_shop() || is_product_category()
│
└── js/                        # ⚡ JS MODULAR (IIFE + strict mode + DOMContentLoaded)
    ├── global-ui.js           # [NUEVO] Agrupa: WPLingua toggle, Share button logic
    ├── header.js              # Menú móvil, submenús, dropdown cuenta, country selector
    ├── footer.js              # Comportamiento footer
    ├── cart.js                # Interactividad carrito
    ├── checkout.js            # Validación + libphonenumber
    ├── modal-auth.js          # Flujo login/registro AJAX
    └── country-modal.js       # Modal de cambio de país


3. INVENTARIO DE ARCHIVOS (Estado Actual)

PHP · inc/

Archivo

Tamaño

Responsabilidad principal

inc/icons.php

7.0 KB

mu_get_icon() — todos los SVGs del tema

inc/geo.php

21.8 KB

Detección de país, redirección de dominio

inc/auth-modal.php

12.1 KB

HTML modal auth, endpoints wc_ajax_mu_*

inc/checkout.php

10.0 KB

Campos, validaciones y optimizaciones de WC Checkout

inc/cart.php

2.9 KB

Añadir múltiples ítems al carrito, buffers BACS

inc/product.php

4.9 KB

mu_render_linked_product(), lógica físico/digital

inc/ui.php

12.5 KB

Lógica para Header, footer, shortcodes

CSS · css/

Archivo

Tamaño

Condición de Carga en functions.php

style.css (raíz)

~9 KB

Global (base)

css/components/global-ui.css

[COMPLETAR]

Global

css/components/header.css

9.4 KB

Global

css/components/footer.css

7.9 KB

Global

css/components/modal-auth.css

8.3 KB

! is_user_logged_in()

css/components/country-modal.css

3.7 KB

Global (Evaluar condicional si geo está activo)

css/cart.css

9.7 KB

is_cart()

css/checkout.css

9.4 KB

is_checkout() && ! is_order_received_page()

css/product.css

0.6 KB

is_product()

css/home.css

0 B

is_front_page()

css/shop.css

0 B

`is_shop()

JS · js/

Archivo

Tamaño

Condición de Carga en functions.php

js/global-ui.js

[COMPLETAR]

Global

js/header.js

4.9 KB

Global

js/footer.js

0.9 KB

Global

js/modal-auth.js

15.5 KB

! is_user_logged_in()

js/cart.js

6.4 KB

is_cart()

js/checkout.js

6.7 KB

is_checkout() && ! is_order_received_page()

js/country-modal.js

3.5 KB

Global

4. SISTEMA DE DISEÑO (API Exclusiva)

⚠️ NO inventar variables nuevas. Usar solo las listadas aquí (:root en style.css).

Variables CSS (Extracto)

Categoría

Variables Clave

Colores

--primario (#2B9FCF), --secundario (#FFD77A), --texto, --blanco, --fondo

Spacing

--mu-space-xs (5px), --mu-space-sm (10px), --mu-space-md (20px), --mu-space-lg (40px)

Radius

--mu-radius-sm (6px), --mu-radius (12px), --mu-radius-md, --mu-radius-full (9999px)

Sombras

--mu-shadow-sm, --mu-shadow, --mu-shadow-md, --mu-shadow-lg

Tipografía

--mu-font-display (Fredoka One), --mu-font-base (Inter)

API de Iconos SVG (inc/icons.php)

echo mu_get_icon('name'); // NUNCA inline SVG directo


Disponibles: arrow, search, close, share, check, instagram, facebook, pinterest, tiktok, youtube

5. ROUTING DE DESARROLLO — ¿Dónde va el código nuevo?

¿Qué necesitás agregar?

PHP (inc/)

CSS (css/)

JS (js/)

Ajuste UI pequeño (< 50 líneas)

ui.php

components/global-ui.css

global-ui.js

Elemento pesado Header/Footer

ui.php

components/header.css o footer.css

header.js o footer.js

Lógica multi-país

geo.php

components/country-modal.css

country-modal.js

Flujo de Carrito

cart.php

cart.css

cart.js

Login / Registro Modal

auth-modal.php

components/modal-auth.css

modal-auth.js

Flujo Checkout

checkout.php

checkout.css

checkout.js

Nuevo ícono SVG

icons.php

—

—

6. CONVENCIONES DE CÓDIGO & RENDIMIENTO

PHP

Protección: if ( ! function_exists( 'mu_function_name' ) ) { ... }

AJAX WC: Usar prefijo wc_ajax_mu_ (ej: wc_ajax_mu_check_email).

Rendimiento: NUNCA usar hooks pesados como init o wp_loaded si se puede resolver con un hook específico de WooCommerce o cargarlo condicionalmente.

JavaScript

Aislamiento: Siempre encapsular en IIFE con 'use strict';.

Ejecución: Escuchar DOMContentLoaded.

Cero jQuery: Solo Vanilla JS (excepto si es obligación estricta de la API legacy de WooCommerce en cart/checkout).

CSS

Prefijos: .mu-[componente]__[elemento]--[modificador] (BEM).

Sobrescrituras: Si pisas un estilo del tema padre, añade /* override GP: [motivo] */.

7. PENDIENTES / DEUDA TÉCNICA

Consolidar archivos minúsculos (share-button.css/js, fragmentos de mu-ui-scripts.js) dentro de la nueva estructura global-ui. (En proceso)

Llenar archivos vacíos: css/home.css, css/shop.css.

Revisar si country-modal.css/js debe cargarse condicionalmente.
