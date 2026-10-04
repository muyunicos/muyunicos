# MUY ÚNICOS — ARCHITECTURE GUIDE

Monolithic functions.php DEPRECATED. Toda la lógica vive en inc/, css/ y js/.

⚠️ IA / LLM DIRECTIVE: Leer este documento antes de sugerir cambios de arquitectura.
Compliance estricto con "Pragmatic Modularity", "Pull Request Workflow" y "HPOS" es obligatorio.

════════════════════════════════════════════════════════════════
1. REGLAS CORE
════════════════════════════════════════════════════════════════

MODULARIDAD PRAGMÁTICA ("Goldilocks")
- Ajustes pequeños de UI < 50 líneas → agrupar en global-ui.css / global-ui.js
- Funcionalidades complejas o aisladas → archivo propio, carga condicional

CARGA CONDICIONAL ESTRICTA
- Nunca cargar assets globalmente si no aplican a header/footer/UI transversal.
- Usar is_shop(), is_checkout(), is_cart(), is_user_logged_in(), is_product(), etc.
- NUNCA wp_add_inline_style() / wp_add_inline_script() — todo CSS/JS en archivos cacheables.
  · EXCEPCIÓN login: wp_add_inline_style() dentro de login_enqueue_scripts está permitido
    solo para propiedades dinámicas PHP.
  · EXCEPCIÓN emails: style="" inline es obligatorio en fragmentos HTML de email.

FLUJO DE TRABAJO Y DESPLIEGUE
- Rama semántica obligatoria: perf/, refactor/, fix/, feat/
- Los comandos locales se ejecutan desde la raíz del repositorio, en PowerShell 7
  (pwsh). El entorno local completo está documentado en §9.
- No hay entorno de Staging. Las pruebas se realizan en Producción, por lo que todo código
  debe estar encapsulado (if !function_exists), ser defensivo y estar exhaustivamente revisado.
- Despliegue MANUAL vía FTP/Administrador de archivos a /generatepress-child/.
- Backups automáticos diarios de Hostinger actúan como red de seguridad principal.
- Actualizar SIEMPRE este archivo en el PR o antes del despliegue.

════════════════════════════════════════════════════════════════
2. INFRAESTRUCTURA, SERVIDOR Y OPERACIONES
════════════════════════════════════════════════════════════════

Hosting: Hostinger Plan Business (Espacio: 200GB | RAM: 3072 MB | Núcleos: 2 | PHP Workers: 60)
Stack: PHP 8.5.4 | MySQL 11.8.3-MariaDB-log | LiteSpeed Cache 7.9
  (PHP confirmado contra `X-Powered-By` en producción el 2026-10-02.)
Tema: GeneratePress 3.6.1 + GeneratePress Child

Dominio y Seguridad:
- Dominio principal (muyunicos.com) en DonWeb.
- Redirección automática de muyunicos.com.ar a muyunicos.com.
- El bloqueo por país se aplica en el EDGE (CDN de Hostinger), como lista de
  permitidos. Ver §2 "Caché y CDN".

Caché y CDN (LiteSpeed + CDN de Hostinger):
- Preajuste de LiteSpeed Cache: CACHÉ POR DEFECTO (aplicado 2026-10-02).
  NO combinar CSS/JS: rompe la carga condicional por página que sostiene el tema
  (ver Principio I y II de la constitución) y multiplica la cardinalidad de caché
  al cruzarse con los subdominios.
- JS Delay (Retraso de JS): DESACTIVADO. Ver incidente §7 "wp.i18n is not defined":
  rompía el orden de dependencias de los paquetes @wordpress/*. Si se reactiva,
  excluir PRIMERO wp-hooks, wp-i18n y a18n del filtro litespeed_optimize_js_excludes;
  excluir solo los consumidores no alcanza.
- QUIC.cloud: integración ACTIVA (servicios ccss/ucss conectados al node123), pero
  su CDN está DESACTIVADA. Ambas CDNs no pueden coexistir; la activa es Hostinger.
- CDN de Hostinger (hcdn): ÚNICA CDN activa. El HTML cacheado por subdominio lo
  maneja estrictamente LiteSpeed; la CDN de Hostinger no almacena HTML de catálogo.
- TLS 1.3 activo.
- Bloqueo de tráfico por PAÍS en el edge: lista de permitidos. Todo lo que no esté
  en la lista se rechaza antes de llegar a PHP.

AISLAMIENTO DE CACHÉ ENTRE SUBDOMINIOS (mecanismo real):
- LiteSpeed separa por variante con la cookie _lscache_vary, de dominio
  .muyunicos.com, con un valor derivado del host. Verificado 2026-10-02: el catálogo
  de muyunicos.com (con productos físicos) y el de los subdominios (solo digitales)
  sirven HTML distinto (md5 y tamaño diferentes). Los productos físicos
  desaparecen en los subdominios POR DISEÑO (digital-restriction.php), no es un fallo.
- El header Vary observado en producción trae solo Accept-Encoding. El filtro
  litespeed_vary de inc/compat-litespeed.php queda como refuerzo, no como mecanismo
  principal. NO tocarlo sin medir antes: romperlo reintroduce productos físicos
  cruzados entre países.

Object Cache (LiteSpeed):
- ⚠️ NO confundir con la caché de páginas. Son dos capas distintas:
  · Object cache (esta sección): guarda DATOS (transients, opciones, índices de
    catálogo). La consume PHP en cada request.
  · Caché de páginas (LiteSpeed Cache): guarda HTML por subdominio. La consume el
    navegador y la CDN.
  Un error de TTL o de purga en una capa NO se propaga a la otra, y diagnosticar
  una mirando la otra lleva a conclusiones equivocadas.
- Caché de objetos: ON (Memcached, prueba de conexión exitosa).
- Extensión: Memcached (Redis también disponible y activado).
- Configuración: Método Memcached | Host ::1 | Puerto 11211.
- TTL por defecto del objeto: 360 segundos.
- Conexión persistente: ON.
- Caché WP-Admin: ON.
- Grupos globales: users, userlogins, useremail, userslugs, usermeta, user_meta,
  site-transient, site-options, site-lookup, site-details, blog-lookup, blog-details,
  blog-id-cache, rss, global-posts, global-cache-test.
- Grupos NO cacheables: comment, counts, plugins, wc_session_id.
- Implicación: wp_using_ext_object_cache() = true → mu_ajax_rate_limit_check()
  usa la capa en memoria (Memcached) como primaria, sin escrituras DB directas.

Cronjobs:
- DISABLE_WP_CRON está activo.
- Dependencia exclusiva del System Cron de Hostinger. 
  Las tareas pesadas (reindexado, transient clears) se ejecutan a nivel servidor para 
  evitar penalizar el rendimiento del frontend.

════════════════════════════════════════════════════════════════
3. CONFIGURACIÓN COMERCIAL Y LOGÍSTICA
════════════════════════════════════════════════════════════════

Configuración Multi-País (17 Zonas Activas):
- Base: Argentina (ARS). Geoposicionamiento activo y precios dinámicos según país.
- Precios sin impuestos incluidos (impuesto calculado por dirección de envío).

Modelo Híbrido:
- Digitales (Global): Etiquetas escolares, PDFs. Sin costo de envío, entrega instantánea.
- Físicos (Solo AR): Stickers, outlet, gaming Wii. 
  · Logística: Entregas coordinadas de forma directa o retiros locales en Mar del Plata.
  · Control de Checkout: Si un producto físico está en el carrito y el cliente cambia 
    a un país extranjero, se debe bloquear la compra con el aviso: 
    "Este artículo no está disponible en tu ubicación actual".

Pagos, Analítica y Correos:
- Pasarelas: Mercado Pago (principal LATAM) y PayPal. (Tarifas dinámicas por pasarela).
  ⚠️ Son pasarelas CONFIGURADAS, no plugins acoplados al tema: el tema no
  referencia las clases de Mercado Pago ni de PayPal. El checkout delega la
  autorización del cobro en WooCommerce y en cada plugin; el tema solo opera
  sobre el array de pasarelas con mu_payment_gateways_country_rules.
- Correos Transaccionales: WP Mail SMTP 4.9.0. (Sin plataformas de automatización externas).
- Analítica: Google Analytics y Meta Pixel activos.

PLUGINS ACOPLADOS AL TEMA (verificado 2026-10-02 por grep en inc/):
- Solo se listan los que el tema consume por hook, filtro o API. Un plugin
  instalado sin acoplamiento NO va aquí: listarlo sugeriría una integración que
  no existe.
- Rank Math SEO → rank_math_description, rank_math_canonical (inc/seo-hreflang.php).
  También engancha The SEO Framework y Yoast por si se activan.
- Jetpack Search → jetpack_search_instant_search_options p10 y p15
  (inc/jetpack-search-integration.php): corrige homeUrl al subdominio actual y
  oculta precios en países restringidos.
- Hostinger Tools → get_option('hostinger_tools') (inc/coming-soon.php): lee
  maintenance_mode y bypass_code para el override del Coming Soon.
- wpLingua → clases .wplng-switcher y .wplng-close-btn (css/ y js/global-ui.js);
  el tema inyecta la body class mu-wplng-hide para ocultarlo en subdominios sin
  multiidioma (inc/ui.php).
- WooCommerce Price Based on Country → class_exists() como guarda
  (inc/geo.php): el tema ajusta decimales y formateo solo si el plugin está.
- Nextend Social Login → mu_social_login_url() en inc/auth-modal.php, usada por
  inc/auth-modal.php e inc/checkout.php. Construye RUTA?loginSocial=PROVEEDOR
  &redirect=DESTINO. El formato es contrato con el proveedor: el destino que
  devuelve a Google y Facebook es la propia ruta de login con el parámetro del
  proveedor dentro, y si el orden cambia el proveedor lo rechaza.
- WPS Hide Login → hookea el filtro site_url de WordPress, de modo que
  site_url('/wp-login.php') devuelve la ruta vigente. El tema NO depende de ese
  hook: mu_social_login_url() resuelve la ruta con wp_login_url(), que es la API
  que el plugin modifica. Si se desactivara el plugin, el login seguiría
  funcionando en la ruta por defecto.
- LiteSpeed Cache → litespeed_vary y litespeed_purge_all
  (inc/compat-litespeed.php, inc/navigation-chips.php).

CÓDIGO QUE SE EJECUTA FUERA DEL TEMA (Code Snippets, verificado 2026-10-02):
- ⚠️ Estos fragmentos NO viven en el repositorio versionado. Se documentan aquí
  para que sean auditables: un fragmento declarado se puede revisar; uno no
  declarado no existe para el siguiente agente que abra el proyecto.
  Documentarlos NO autoriza moverlos al tema.
- Filtro de shortcodes → plugin Code Snippets. Propósito: quitar del contenido
  los shortcodes no registrados para que el comprador no vea etiquetas sin
  renderizar. Riesgo: closure sin nombre, no desactivable de forma selectiva.
- Calculadora de Stickers → plugin Code Snippets + repositorio externo. Propósito:
  shortcode que encola el bundle compilado y expone los datos al JS. Ver
  "CALCULADORA DE STICKERS" más abajo.
- Ajuste de wpLingua → plugin Code Snippets. Propósito: ajustar la configuración
  del plugin de traducción. Riesgo: declara define() y funciones sin guardas.
- ⚠️ RIESGO DE COLISIÓN DE NOMBRES: el fragmento de la Calculadora declara
  `mu_sticker_calculator_shortcode()` con el prefijo `mu_` del tema y SIN guarda
  `if ( ! function_exists() )`. El repositorio externo usa el mismo prefijo.
  Si el tema llegara a declarar ese nombre, PHP aborta con error fatal por
  función redeclarada. Verificar antes de tocar cualquier cosa que declare
  entidades `mu_`.

CALCULADORA DE STICKERS (flujo de compilación):
- El código fuente NO está en este repositorio: vive en un repositorio aparte
  (GitHub, carpeta `calculadora/`). Este repositorio solo guarda los ARTEFACTOS
  compilados que se sirven.
- Comandos de compilación (se ejecutan en el repositorio de la Calculadora):
  · npm run build:js   → genera el bundle JS
  · npm run build:css  → genera el CSS de Tailwind
  · npm run build      → ambos
- Destino de los artefactos dentro de este tema:
  · assets/js/calculadora_stickers.js
  · assets/css/calculadora_stickers.css
- El endpoint assets/guardar_datos.php persiste la configuración de la
  calculadora. Vive dentro de assets/ y por lo tanto es accesible por HTTP, así
  que exige `manage_options` y hace bootstrap de WordPress antes de escribir.
- ⚠️ La fuente es la autoridad. Editar el bundle compilado directamente se
  pierde en el próximo build. Hay cambios sin commitear en el repositorio de la
  Calculadora (ver §8).

════════════════════════════════════════════════════════════════
4. SYSTEM MAP — ÁRBOL DE DIRECTORIOS
════════════════════════════════════════════════════════════════

muyunicos/ (generatepress-child)
│
├── functions.php           # Enqueue central (mu_enqueue_assets) + mu_load_module()
├── style.css               # Variables CSS globales (:root), reset, child theme header
│
├── inc/                    # Módulos PHP — lógica de negocio y hooks
│   ├── icons.php           # [PRIMERO] mu_get_icon() — repositorio SVG
│   ├── compat-litespeed.php # [SEGUNDO] Compatibilidad LiteSpeed Cache.
│   │                        # 1. mu_litespeed_vary_by_subdomain() — refuerzo de la
│   │                        #    separación por subdominio. El aislamiento real lo
│   │                        #    hace la cookie _lscache_vary (ver §2).
│   │                        # 2. mu_litespeed_nocache_404() — evita cachear 404s
│   │                        #    causados por errores transitorios de infraestructura.
│   │                        # 3. mu_is_bot() + mu_litespeed_bot_404_fast_exit() —
│   │                        #    salida instantánea (exit) de 404s para bots/indexadores
│   │                        #    de IA: HTML mínimo sin render de tema ni consultas SQL.
│   │                        #    Segunda línea de defensa: el CDN de Hostinger ya
│   │                        #    responde 429 antes de que llegue a PHP.
│   │                        # 4. mu_litespeed_bot_tag_filter_redirect() — 301 de bots
│   │                        #    con query product_tag a la URL canónica.
│   │                        # (Eliminado 2026-10-02: el filtro litespeed_optimize_js_excludes.
│   │                        #  Ver incidente "wp.i18n is not defined" en §7.)
│   ├── cdn-cache-bypass.php # Bypass de caché CDN para contenido dinámico
│   │                        # (cart/checkout/account). Emite cabeceras HTTP
│   │                        # estándar, agnóstico al proveedor: el nombre se
│   │                        # cambió el 2026-10-02 al migrar de Cloudflare a la
│   │                        # CDN de Hostinger, sin cambiar el comportamiento.
│   ├── performance-monitor.php # Monitoreo de rendimiento: logging, cache stats,
│   │                        # AJAX performance, rate limiting, dashboard widget
│   │                        # (incluye sección "Bot 404 Fast-Exit" con contadores
│   │                        # diarios/acumulados vía mu_bot404_get_stats()).
│   ├── coming-soon.php     # Override del Coming Soon de Hostinger Tools v2.3.0.
│   │                        # Intercepta template_redirect p1 (antes del plugin p10).
│   ├── geo.php             # Multi-país: detección por dominio, geolocalización cacheada
│   │                        # (24h TTL), modal sugerencia, selector header, decimales.
│   │                        # Fallback por dominio ante errores de DB ("Commands out of sync").
│   ├── jetpack-search-integration.php # Jetpack Search: corrige homeUrl vía filtro
│   │                        # jetpack_search_instant_search_options para que los resultados
│   │                        # apunten al subdominio actual (+ prefijo idioma BR/pt, US/en).
│   ├── seo-hreflang.php    # SEO Multi-país: hreflang tags, títulos/meta localizados,
│   │                        # canonical URL fix.
│   ├── digital-restriction.php # Restricción productos físicos por subdominio e índices.
│   │                        # Clase MUYU_Digital_Restriction_System. Rebuild en cron real.
│   ├── auth-modal.php      # Modal Login/Registro + endpoints wc_ajax_mu_*
│   ├── login.php           # Personalización wp-login.php
│   ├── checkout.php        # Checkout Híbrido + Login Gate + filtro pasarelas por país
│   │                        # + filtro woocommerce_countries_allowed (solo países con
│   │                        # subdominio) + redirección al subdominio al cambiar país.
│   ├── cart.php            # Multi-item add, buffers BACS
│   ├── flexible-price.php  # Precio Flexible: mapa O(1), validación, AJAX handler.
│   ├── hero-banners.php    # Hero Banners Manager (UI Admin y render).
│   ├── ui.php              # Header icons, Cart badge, WhatsApp, shortcodes, bestsellers,
│   │                        # testimonios Google Places, hero promos, popcat.
│   ├── orders-files.php    # Gestor de archivos de pedido: Admin + Email + Mi Cuenta
│   ├── orders-workflow.php # Estado 'wc-production', emails inteligentes, Admin UI
│   ├── downloads-bonus.php # Bonus & Guías: inyección tabla descargas + emails.
│   ├── navigation-chips.php # Navigation Chips: breadcrumb, índice compacto, chips.
│   │                        # mu_navchips_build_product_index() — índice de productos
│   │                        # con HASH GATE: compara contra el índice almacenado y SOLO
│   │                        # dispara litespeed_purge_all si cambió realmente. Ediciones
│   │                        # de precio/descripción/stock sin cambios en cats/tags ya NO
│   │                        # purgan toda la caché. Log "[MU-NAVCHIPS]" en debug.log
│   │                        # registra las omisiones (rate-limit 1/10min).
│   ├── tag-groups.php      # Tag Groups System: agrupamiento por etiquetas en catálogo.
│   │                        # Config dinámica en wp_options + rebuild asíncrono por cron.
│   ├── products-core.php   # Core: constantes, hooks carrito/orden, MU_UI_Helper.
│   ├── addon-nombre.php    # Addon Nombre v3.0 (campo nombre personalizado)
│   └── addon-etiquetas.php # Addon Etiquetas v3.0 (builder de etiquetas)
│
├── templates/              # Plantillas PHP standalone
│   └── coming-soon.php     # CSS inline, logo hardcodeado, bypass de hooks pesados WP.
│
├── css/                    # CSS modular
│   ├── components/         # global-ui.css, header.css, footer.css, modal-auth.css,
│   │                        # country-modal.css, navigation-chips.css
│   ├── admin*.css          # admin.css, admin-hero-banners.css, admin-order-files.css,
│   │                        # admin-orders.css
│   └── [secciones].css     # home, shop, product, product-builder, cart, checkout,
│                            # login, testimonials, tag-groups, account-downloads,
│                            # account, coming-soon
│
│   NOTA — css/account.css: hoja de la sección de cuenta completa
│   (mi-cuenta, edit-account, downloads, orders). Handle 'mu-account',
│   encolada en functions.php → mu_enqueue_assets() dentro de
│   `if ( mu_wc_is_account_page() )` con dependencia 'mu-base'. Antes de
│   2026-10-02 la sección no tenía hoja propia: account-downloads.css
│   estaba acotada al endpoint 'downloads' y el resto de las pantallas se
│   mostraba con los estilos genéricos de WooCommerce, pensados para
│   escritorio. Solo se carga en la sección de cuenta (principio II).
│
└── js/                     # JS modular
    ├── global-ui.js        # initCarousels()
    ├── jetpack-search.js   # Reescribe links de resultados Jetpack Search
    │                        # al subdominio actual (multi-país) vía MutationObserver.
    ├── header.js           # Header interactions
    ├── footer.js           # Footer interactions
    ├── hero.js             # Hero slider (autoplay, swipe, dots)
    ├── shop.js             # Shop/catalog interactions
    ├── product.js          # Product page interactions
    ├── cart.js             # Cart interactions
    ├── checkout.js         # Checkout (libphonenumber-js)
    ├── modal-auth.js       # Auth modal
    ├── country-modal.js    # Country suggestion modal
    ├── navigation-chips.js # Navigation chips
    ├── tag-groups.js       # Tag groups
    ├── flexible-price.js   # Flexible price
    ├── addon-nombre.js     # Addon nombre
    ├── addon-etiquetas.js  # Addon etiquetas
    ├── testimonials.js     # Testimonials
    ├── admin.js            # Admin general
    ├── admin-hero-banners.js # Admin hero banners
    ├── admin-order-files.js  # Admin order files
    └── admin-orders.js     # Admin orders

════════════════════════════════════════════════════════════════
5. SISTEMA DE DISEÑO (API EXCLUSIVA)
════════════════════════════════════════════════════════════════

⚠️ NO inventar variables nuevas. Solo las definidas en :root de style.css.

Categoría    | Variables
-------------|--------------------------------------------------------------------------
Colores      | --primario (#2B9FCF)  --secundario (#FFD77A)  --texto  --blanco  --fondo
Spacing      | --mu-space-xs (5px)  --mu-space-sm (10px)  --mu-space-md (20px)  --mu-space-lg (40px)
Radius       | --mu-radius-sm (6px)  --mu-radius (12px)  --mu-radius-md  --mu-radius-full
Tipografía   | --mu-font-display (Fredoka One)  --mu-font-base (Inter)

EXCEPCIÓN — botón de contacto (WhatsApp):
--mu-wa-green (#25d366)  --mu-wa-green-hover (#1eb455)  --mu-wa-accent (#339db7)
Estas tres NO son colores de marca de Muy Únicos: son los colores
institucionales de WhatsApp, y no tienen equivalente en el bloque anterior.
Se declararon en style.css el 2026-10-02 para eliminar los literales que
estaban escritos a mano en css/components/global-ui.css. Sustituirlas por
--primario haría que el botón dejara de reconocerse como WhatsApp. Si la
marca adopta un verde propio, se cambia el valor en :root y en ningún otro
archivo. Ver el registro de excepciones en §8.

ICONOS SVG
echo mu_get_icon('name'); // NUNCA inline SVG directo (excepto en templates standalone)

════════════════════════════════════════════════════════════════
6. CONVENCIONES DE CÓDIGO
════════════════════════════════════════════════════════════════

BASE DE DATOS Y HPOS (High-Performance Order Storage)
- WooCommerce tiene activo HPOS (OrdersTableDataStore) por defecto.
- PROHIBIDO interactuar con la tabla `wp_posts` o `wp_postmeta` para leer/guardar pedidos.
- Utilizar estrictamente el CRUD de WooCommerce: $order->get_meta(), $order->update_meta_data(), etc.

PHP
- Siempre: if ( ! function_exists( 'mu_fn' ) ) { ... }
- WP Cron: wp_schedule_single_event() delega al System Cron nativo de Hostinger.
- DB Queries: NUNCA 'limit' => -1 en frontend. Siempre limitar + transient.
- Transients: clave mu_[contexto]_{id}. Invalidar en el hook de cambio de estado.
- Rebuilds pesados: SIEMPRE delegar a cron real (nunca en shutdown ni en request AJAX).

JavaScript
- IIFE + 'use strict' + DOMContentLoaded. Cero jQuery salvo obligación WC legacy.
- Datos PHP→JS: wp_localize_script().
- Animaciones de botón: NUNCA style.transform inline. Usar clases CSS delegadas a GPU.

CSS
- Prefijo + BEM: .mu-[componente]__elem--[mod]
- Sobrescrituras GP: /* override GP: [motivo] */

════════════════════════════════════════════════════════════════
7. DIAGNÓSTICO DE ERRORES CONOCIDOS
════════════════════════════════════════════════════════════════

Antes de diagnosticar cualquier fallo, verificar el entorno (§9): shell activo
(pwsh 7), directorio actual, PATH y disponibilidad de la herramienta.

ERRORES "Commands out of sync" (MySQL)
- Causa: Plugins de terceros (Jetpack, Action Scheduler, Rank Math, Facebook Pixel,
  WooCommerce Sessions) ejecutan consultas SQL durante el hook `shutdown` de WordPress,
  DESPUÉS de que la conexión a la BD ya se cerró.
- El tema NO es responsable: No registra hooks de shutdown, no usa register_shutdown_function,
  no hace wp_remote_post. El único wp_remote_get (testimonios Google Places) solo se ejecuta
  con ?force_reviews=1 y solo para admins.
- Mitigaciones implementadas en el tema:
  · inc/geo.php — Fallback por dominio ante errores de DB en geolocalización.
  · inc/compat-litespeed.php — Evita cachear 404s causados por errores transitorios.
  · inc/digital-restriction.php — Rebuild de índices en cron real, NO en shutdown.
- Recomendación: Actualizar plugins a últimas versiones. Si persiste, desactivar
  Jetpack Sync o configurar Action Scheduler para no ejecutarse en shutdown.

ERRORES 404 INTERMITENTE EN CATEGORÍAS FÍSICAS (v4.7.0)
- Síntoma: /outlet/ (y otras categorías físicas) devuelve 404 tras unos días.
  "Actualizar" en el admin de la categoría lo arregla temporalmente.
- Causa raíz probable: El rebuild de índices (muyu_cron_rebuild_digital_indexes)
  puede fallar (timeout SQL, "Commands out of sync", fatal de WCPBC) y devolver
  vacío. save_indexes() sobrescribía los índices buenos con vacío, destruyendo
  el mapa de categorías y rompiendo el routing de WooCommerce.
- Mitigaciones implementadas en v4.7.0 (vigentes):
  · Guard anti-vacío en rebuild_digital_indexes() — no sobrescribe índices buenos.
  · Guard anti-vacío en filter_category_terms() — no filtra con include=[0].
  · Verificación de integridad en ensure_indexes_exist() (admin).
- Mitigaciones REMOVIDAS en v4.8.0 (no cumplieron su propósito):
  · handle_404_category_canonical_ar() con ?mu_restore=1 — eliminada (causaba los
    avisos "Purgar la URL" en wp-admin; innecesaria porque mu_litespeed_nocache_404()
    ya evita que LiteSpeed cachee 404s).
  · Cron diario muyu_cron_verify_category_urls + verify_physical_category_urls().
  · Sistema de logging mu_log_event() (wp-content/uploads/mu-logs/mu-debug.log) y
    filtro mu_enable_debug_log — eliminados de digital-restriction.php y functions.php.
- Monitoreo actual: Widget del dashboard ("Muy Únicos - Performance Monitor") +
  debug.log ([MU-BOT404], [MU-NAVCHIPS]) + cabecera X-MU-Bot-404 vía curl.

ERRORES DE CRON "invalid_schedule" (LiteSpeed)
- Causa: LiteSpeed Cache intenta reprogramar eventos cron con schedules que ya no existen
  (litespeed_filter, once_in_week) tras actualizaciones del plugin.
- Mitigación: El tema no interfiere. Se resuelve actualizando LiteSpeed Cache o
  limpiando los eventos cron huérfanos en wp_options.

CICLO DE VIDA DE TRANSIENTS DEL SISTEMA DE FILTRADO (importante)
- mu_digital_cat_has_visible_{term_id} (visibilidad por categoría, TTL 30 días):
  · Se invalida en save_indexes() SOLO si cambió el set digital (Hash Gate,
    v4.8.1). Antes se borraban TODOS en cada rebuild — editar el precio de un
    producto volaba el cache de todas las categorías y el WP_Query caro
    (post__in con todos los digitales + tax_query) re-corria varias veces al día.
  · Factores externos que pueden romper el TTL: flush del object cache
    (herramientas de Hostinger / purge de LiteSpeed según config) y evicción
    LRU de Memcached bajo presión de memoria (tráfico de bots). No tienen
    fallback a wp_options — si se evictan, el query re-corre una vez por
    categoría y se re-cachea.
- mu_navchips_product_index / mu_tag_groups_index (TTL 30 días):
  · Fallback permanente a wp_options (_mu_navchips_permanent_*) — sobreviven
    flushes y evicciones. Refrescados en cada rebuild (escritura barata).
- OPTIMIZACIONES DE FILTRADO v4.8.1 (static cache por request):
  · is_restricted_user(): se llamaba 3-5 veces por request re-procesando el
    host → ahora una sola vez.
  · get_cached_digital_product_ids(): get_option deserializaba ~1000 enteros
    en cada llamada → una sola deserialización por request.
  · get_excluded_category_term_ids() (nuevo helper público): los 3 term IDs
    de categorías excluidas se resolvían con 3 get_term_by() en
    filter_product_queries() + 3 más en el render de chips → ahora 3 lookups
    totales por request, compartidos entre ambos módulos.
  · Veredicto de la auditoría de saturación (25-Aug-2026): el filtrado es un
    AMPLIFICADOR (hace cada request no-cacheado ~20-40% más lento) pero NO la
    causa raíz — la saturación de CPU/RAM correlaciona con el volumen de bots
    (~208k bot-404/día + full-renders en URLs con query strings), no con
    patrones de tráfico humano. Las páginas cacheadas no ejecutan PHP.

HASH GATE EN REBUILDS DE ÍNDICES (anti-purgas-innecesarias)
- Síntoma: Cada save_post_product / edición de categoría disparaba un rebuild del
  índice navchips + tag-groups seguido de litespeed_purge_all INCONDICIONAL —
  aunque el índice nuevo fuera idéntico al anterior (ej: editar solo precio o
  descripción). Cada purga total regeneraba todas las páginas en los 17 subdominios.
- Mitigación implementada (Hash Gate):
  · inc/navigation-chips.php — mu_navchips_build_product_index(): bool; compara
    $compact_index contra la option permanente y escribe/purga SOLO si difiere.
    Los transients se refrescan siempre (baratos, evitan expiry → rebuild fantasma).
  · inc/navigation-chips.php — mu_build_tag_groups_index(): bool; misma comparación
    vía maybe_serialize(). El índice es determinista (el shuffle interno no afecta
    al output final), por lo que la comparación es estable entre rebuilds.
  · La purga ocurre solo si $index_changed || $groups_changed. Si nada cambió,
    mu_navchips_log_purge_skipped() registra "[MU-NAVCHIPS] ... OMITIDO" en
    debug.log (rate-limit global 1/10min vía object cache, cero SQL).
  · inc/digital-restriction.php — mu_rebuild_all_indexes() retorna 'purged' => bool
    para trazabilidad desde el AJAX "Reindexar".
- Verificación: Editar solo el precio de un producto → esperar cron (+30s) →
  debug.log debe mostrar "[MU-NAVCHIPS] Rebuild sin cambios — OMITIDO" y NO debe
  aparecer aviso de purga en wp-admin.

SATURACIÓN POR RASTREO MASIVO DE BOTS/IA EN URLs INEXISTENTES
- Síntoma: Miles de peticiones a URLs basura (query strings inválidos, rutas viejas,
  combinaciones product_tag+add-to-cart) desde GPTBot, CCBot, ClaudeBot, Bytespider, etc.
  Cada petición ejecutaba WordPress completo (routing, menús, widgets, render del tema).
- Mitigaciones implementadas:
  · inc/compat-litespeed.php — mu_is_bot(): detección O(1) por User-Agent
    (usa wp_is_bot() del core si existe, fallback regex propio).
  · inc/compat-litespeed.php — mu_litespeed_bot_404_fast_exit() (template_redirect p1):
    si bot + 404 → HTML mínimo (~100 bytes) + exit inmediato, sin render de tema ni SQL.
    Envía X-LiteSpeed-Cache-Control: public,max-age=86400 para que LiteSpeed cachee el
    404 y las repeticiones no toquen PHP. El vary mu_host mantiene cachés separadas
    por subdominio. Envía X-Robots-Tag: noindex,nofollow para desincentivar re-rastreo.
  · inc/digital-restriction.php — handle_redirects() retorna temprano si mu_is_bot():
    los bots no ejecutan lógica de redirección ni programan rebuilds de índices.
- Nota: El fast-exit NO envía cabeceras de caché pública (fix cache-poisoning):
  la caché de LiteSpeed es compartida entre bots y humanos (el vary solo separa
  por subdominio). Un 404 cacheado por un bot sería servido a humanos en vez de
  la redirección 302 a su categoría digital. Respuestas dependientes del
  User-Agent jamás deben marcarse cacheables.
- MITIGACIÓN ADICIONAL (rastreo de permutaciones de tags):
  · inc/compat-litespeed.php — mu_litespeed_bot_tag_filter_redirect() (p1):
    bot + ?product_tag= → 301 a la URL sin query string (~1ms, cero DB). El 301
    enseña la URL canónica y reduce el rastreo de permutaciones con el tiempo.
  · inc/navigation-chips.php — mu_navchips_shortcircuit_empty_tag_combos()
    (pre_get_posts p60): intersección multi-tag en MEMORIA vía índice navchips;
    combinación vacía o tag inexistente → post__in=[0] (SQL trivial) en vez del
    join N-vías de WooCommerce. Ayuda a humanos y bots indetectables.
  · inc/navigation-chips.php — calculate_tag_stats(): saltada la pasada de
    'count' en vistas filtradas (no se usa en el render; reduce a la mitad la
    iteración del índice).
- CONFIGURACIÓN RECOMENDADA (panel, no código):
  · LiteSpeed → Cache Settings → "Include Query Strings": agregar product_tag.
    EL fix multiplicador: cada combinación se renderiza una sola vez y luego
    se sirve desde caché (también hace la navegación por chips instantánea).
    Verificar: 2º curl a la misma URL con ?product_tag= debe dar x-litespeed-cache: hit.
  · PENDIENTE DE CONFIGURAR (ver §8): WAF challenge a requests con
    product_tag= multi-tag de bots no verificados, y excepción para
    meta-webindexer. No están activos: el edge no los implementa todavía.
- VERIFICACIÓN (curl):
  · ⚠️ Para verificar CACHÉ usar GET, no HEAD (`curl -sI`). LiteSpeed no puebla
    la caché con peticiones HEAD: `curl -sI` devuelve "miss" aunque la caché
    esté sana y hace pensar en una regresión inexistente. Verificado
    2026-10-02. Para leer solo cabeceras con GET: `curl -s -D - -o /dev/null`.
  · Bot contra URL inexistente (el edge debe responder 429 antes que PHP):
      curl -sI -A "GPTBot/1.0" https://us.muyunicos.com/pt/outlet
  · Humano en la MISMA URL (debe mostrar redirect 302 al shop):
      curl -sI -A "Mozilla/5.0 (Windows NT 10.0; Win64; x64)" https://us.muyunicos.com/pt/outlet
  · Caché por subdominio (2ª request a cada host = "x-litespeed-cache: hit";
    la 1ª da miss porque genera la variante del visitante):
      curl -s -D - -o /dev/null -c /tmp/mu-root.txt https://muyunicos.com/tienda/ | grep -i x-litespeed-cache
      curl -s -D - -o /dev/null -b /tmp/mu-root.txt -c /tmp/mu-root.txt https://muyunicos.com/tienda/ | grep -i x-litespeed-cache
      curl -s -D - -o /dev/null -c /tmp/mu-us.txt https://us.muyunicos.com/tienda/ | grep -i x-litespeed-cache
      curl -s -D - -o /dev/null -b /tmp/mu-us.txt -c /tmp/mu-us.txt https://us.muyunicos.com/tienda/ | grep -i x-litespeed-cache
  · Aislamiento del catálogo (RAÍZ con físicos, subdominio sin ellos):
      curl -s https://muyunicos.com/tienda/ | grep -oc "outlet\|wii\|consola\|sticker"
      curl -s https://us.muyunicos.com/tienda/ | grep -oc "outlet\|wii\|consola\|sticker"
  · Log rate-limitado en debug.log: buscar "[MU-BOT404]" (máx 1 línea cada 5 min,
    con host, UA, contador diario y total).
  · Widget del dashboard: sección "Bot 404 Fast-Exit (Anti-Bots)" con contadores
    diarios/acumulados leídos de wp-content/uploads/mu-logs/bot404-stats.json (cero SQL).
- NOTA TÉCNICA (hallazgo en producción 24-Aug-2026): los contadores y el rate-limit
  del fast-exit NO pueden usar object cache (Memcached) — LiteSpeed persiste su
  object cache durante el shutdown de WordPress, que el exit del fast-exit salta.
  Evidencia: cada petición bot registraba daily=1 total=1 y el rate-limit no
  sostenía. Solución: archivo plano bot404-stats.json con LOCK_EX (mu_bot404_record_hit),
  que sobrevive al exit. Verificado en producción: [MU-BOT404] capturó Amzn-SearchBot,
  Claude-SearchBot y crawlers de Meta spoofeando Chrome (sufijo "compatible; ..."),
  sin falsos positivos de navegadores humanos reales.

ERRORES "wp.i18n is not defined" / gtag-events "reading 'hooks'" (2026-10-02)
- Síntoma: en la consola del navegador, tres "Uncaught ReferenceError: wp is not
  defined" (wp.i18n.setLocaleData ×2 + el blob de Jetpack Search), un
  "TypeError: Cannot read properties of undefined (reading 'hooks')" en
  gtag-events.js y un "wp.jpI18nLoader.state is not set" de JQMIGRATE.
- Causa raíz: el JS Delay de LiteSpeed ejecutaba los scripts de forma asíncrona
  sin respetar el árbol de dependencias de wp_register_script. Los paquetes
  @wordpress/* (wp-hooks, wp-i18n, a18n) se servían como blobs
  data:text/javascript;base64 y llegaban DESPUÉS de sus consumidores, con lo que
  el objeto global wp todavía no existía. El síntoma visible era gtag-events.js,
  pero el problema estaba en el proveedor, no en el consumidor.
- Error de diagnóstico previo: el filtro litespeed_optimize_js_excludes excluía
  gtag-events.js, 101.js, jetpack-search.js y modal-auth.js, es decir los
  CONSUMIDORES. Su comentario afirmaba que no hacía falta excluir wp-hooks porque
  "LiteSpeed lo gestiona bien", supuesto que los logs refutaron.
- Mitigación aplicada: preajuste de LiteSpeed "CACHÉ POR DEFECTO", que desactiva
  el JS Delay. Verificado tras aplicar: 0 errores wp/hooks/i18n, y el catálogo
  sigue cacheando (hit) en raíz y subdominios.
- APRENDIZAJE: si se reactiva el JS Delay, excluir PRIMERO wp-hooks, wp-i18n y
  a18n; excluir solo los consumidores no alcanza. Alternativa a evaluar: activar
  Guest Mode y excluir por paquete, no por ruta.

WAF BLOQUEA EL RETORNO DE OAUTH DE GOOGLE (2026-10-02)
- Síntoma: el login con Google completaba la autorización y, al volver al sitio,
  la respuesta era "403 Forbidden" en texto plano. El flujo llegaba completo
  (con un código de autenticación válido) y se rompía en el último paso.
- REGLA AISLADA: el WAF del edge bloqueaba con 403 cualquier parámetro que
  contuviera la secuencia ".profile" (punto seguido de profile), en cualquier
  página y cualquier subdominio. Google siempre devuelve "userinfo.profile"
  dentro del scope del retorno, de modo que el callback quedaba bloqueado
  siempre. Contraste verificado el mismo día:
    ?scope=userinfo.email    → 302 (pasaba)
    ?scope=userinfo.profile  → 403 (bloqueaba)
    ?z=a.profile             → 403
    ?z=a.pro / ?z=profilea   → 200 (pasaban)
- El 403 lo emitía el edge (Server: hcdn, Content-Type: text/plain, cuerpo
  "Forbidden"): WordPress no se ejecutaba, así que ningún cambio en el tema podía
  arreglarlo.
- Mitigación: se bajó el nivel de seguridad de la CDN de Hostinger de Alto a
  Medio. Verificado después: el callback devuelve 200, el login con Google y
  Facebook se completa, la inyección SQL cruda y un <script> malicioso se
  redirigen a una página de aviso (301) en lugar de ejecutar, y el bloqueo de
  bots por país sigue activo (bot 429, humano 302).
- APRENDIZAJE: una regla anti-inyección del borde puede bloquear una URL de
  retorno legítima. Los proveedores de autenticación devuelven parámetros con
  formatos raros (identificadores largos, puntos y guiones bajos). Si el login
  vuelve a romperse después de subir el nivel de seguridad, el primer sospechoso
  es el WAF, no el tema. Y al revés: arreglar el tema no arregla el WAF. Son
  capas independientes.

BLOQUEO DE BOTS POR PAÍS EN EL EDGE (2026-10-02)
- Verificado: un User-Agent de bot (GPTBot) contra una URL inexistente en un
  subdominio recibe 429 Too Many Requests desde el CDN de Hostinger
  (Server: hcdn, sin X-Litespeed-Cache, sin X-MU-Bot-404, Content-Length 0),
  mientras el mismo User-Agent humano recibe el 302 de redirección normal.
- Consecuencia: mu_litespeed_bot_404_fast_exit() sigue en el tema pero deja de
  ejecutarse en la mayoría de los casos, porque WordPress no llega a correr. Es
  una segunda línea de defensa, no un mecanismo inútil: si el edge deja de
  filtrar, el fast-exit vuelve a actuar.
- APRENDIZAJE: al verificar el fast-exit, distinguir si el 429/404 viene del edge
  o de PHP (presencia de Server: hcdn y ausencia de X-Litespeed-Cache indica edge).

════════════════════════════════════════════════════════════════
8. DEUDA TÉCNICA

════════════════════════════════════════════════════════════════

- [ ] digital-restriction.php / cart.php: Implementar validación `woocommerce_check_cart_items` para bloquear checkout de productos físicos si el país cambia. ("Este artículo no está disponible en tu ubicación actual").
- [ ] checkout.js: libphonenumber-js desde CDN unpkg.com — evaluar auto-host local.
- [ ] orders-workflow.php: bulk actions Legacy → migrar definitivamente a hooks de HPOS (woocommerce_order_list_table_bulk_actions).
- [ ] digital-restriction.php: N+1 en display_digital_price_in_catalog — evaluar get_post_meta() directo.
- [ ] coming-soon.css: archivo deprecado pero presente. Eliminar tras confirmar inactividad.
- [ ] ui.php: mu_testimonios_section() — el wp_remote_get a Google Places solo se ejecuta con ?force_reviews=1. Evaluar mover a cron para evitar timeouts en request de admin.
- [ ] navigation-chips.php: mu_navchips_build_product_index() — evaluar si el índice puede crecer demasiado en wp_options con muchos productos.
- [ ] WAF en el edge: configurar un challenge para requests con `product_tag=` multi-tag provenientes de bots no verificados. Criterio: "que no intenten ninguna combinación de etiquetas en el borde". Implementar en el WAF de la CDN de Hostinger; el edge hoy no lo soporta. OJO: verificar antes que no bloquee a Google (que rastrea combinaciones de etiquetas).
- [ ] Excepción en el WAF para `meta-webindexer` (crawler de previews de Facebook/WhatsApp) si se desea preservar las preview cards compartidas. Criterio: las previews de enlaces en redes deben seguir renderizando la imagen.
- [ ] Repositorio de la Calculadora de Stickers: 7 archivos modificados sin commitear, incluido el bundle `calculadora_stickers.js` que se sirve en producción. Mientras no se commiteen, el bundle servido no es reproducible desde su fuente.
- [ ] `assets/css/main.min.css`: archivo versionado (commit "Add files via upload") que NO está encolado en ningún `wp_enqueue_*` del tema — verificado por grep sobre todo el repo. Es un reset minificado de terceros, así que NO se editó ni se purgó: se excluyó del lint con `files.exclude` en `.vscode/settings.json` para que sus avisos de vendor-prefix no mezclen código propio con código de terceros. Decidir si se borra del repo o se documenta como archivo de respaldo; no se borró por no saber si alguien lo subió a propósito.
- [ ] `ui.php`: el `href` del botón AFIP sigue en `http://` porque NO se pudo verificar que `qr.afip.gob.ar` sirva HTTPS con certificado válido (el dominio no responde a clientes automatizados). El `src` de la imagen SÍ se migró a `https://` (verificado: responde 200 con `image/jpeg`). Mientras el `href` sea `http://`, el navegador puede avisar "conexión no segura" al navegar a AFIP. Verificar a mano y migrar a `https://` en cuanto se confirme.

REGISTRO DE EXCEPCIONES (2026-10-02, feature 003-mobile-ui-fixes):
- Colores --mu-wa-green / --mu-wa-green-hover / --mu-wa-accent: excepción al
  principio VIII (sistema de diseño). Motivo: son los colores institucionales de
  WhatsApp y no tienen equivalente entre los tokens de marca. Sustituirlos por
  --primario haría que el botón de contacto dejara de ser reconocible como
  WhatsApp, que es lo que comunica al comprador que puede escribirnos. Ubicación:
  declaradas en :root de style.css con su comentario justificativo y usadas solo en
  css/components/global-ui.css. Sin fecha de expiración: siguen siendo válidas
  mientras el canal de contacto sea WhatsApp. Verificado: 0 literales de color
  restantes en el bloque del botón.

DEUDA DE VERIFICACIÓN (2026-10-02, feature 003-mobile-ui-fixes):
- La verificación del comportamiento en móvil NO es automatizable en este
  proyecto. Un toque en pantalla angosta no se reproduce con `curl`, y el contraste
  real y el área táctil se miden sobre el píxel renderizado. La verificación de la
  feature 003 es manual: specs/003-mobile-ui-fixes/quickstart.md Fase B, 7 pasos en
  un teléfono real sobre ec.muyunicos.com. Criterio: si esa fase no se ejecuta, la
  feature se declara NO VERIFICADA en el pull request y nunca como verificada. Los
  pasos automatizables de la Fase A sí se ejecutan por comando y su salida va al PR.
- Hueco conocido y aceptado: `php -l` local corre PHP 8.5.9 y producción 8.5.4. La
  verificación de sintaxis es local; el comportamiento se verifica contra
  producción, que es la única red disponible.

RESUELTOS el 2026-10-02:
- [x] Versión de PHP: confirmada 8.5.4 contra `X-Powered-By`. El §2 ya declara el
  valor correcto. El dato anterior (8.3.28) estaba desactualizado.
- [x] SC-004: el total del carrito con impuesto por dirección de envío coincide
  con el que calcula WooCommerce. Verificado por el mantenedor sobre producción.

════════════════════════════════════════════════════════════════
9. ENTORNO DE DESARROLLO LOCAL (WINDOWS + POWERSHELL 7 + SPEC KIT)
════════════════════════════════════════════════════════════════

Este apartado describe únicamente el entorno desde el que se edita y verifica el
repositorio. No es el servidor (eso es §2): nada de lo que aquí se documenta se
ejecuta en producción.

STACK LOCAL (verificado 2026-10-03):
- SO: Windows (10.0.26200), 64 bits.
- Shell activo y único soportado: PowerShell 7 (pwsh 7.6.6, instalado como app
  de Microsoft Store). NO asumir bash, WSL, cmd ni Windows PowerShell 5.1.
  · La terminal integrada de VS Code y Cline debe abrir pwsh 7; el perfil
    "PowerShell 7" está definido y versionado en .vscode/settings.json.
- Editor: VS Code + extensión Cline.
  · Intelephense (extensión PHP de VS Code) SIN stubs solo trae las funciones
    de PHP: marca como "Undefined function" cada add_action(), esc_attr(),
    wc_price(), etc. del core de WordPress y de WooCommerce. Con ~600 llamadas
    al core, el panel de problemas queda inutilizable y los errores REALES se
    pierden en el ruido. Por eso .vscode/settings.json declara intelephense.stubs.
    · Tras clonar el repo hay que DESCARGAR los stubs (no vienen versionados:
      vendor/ está en .gitignore, línea 37). Desde la raíz, en PowerShell 7:
        New-Item -ItemType Directory -Force -Path vendor\stubs | Out-Null
        curl.exe -sL -o vendor\stubs\wordpress-stubs.php https://raw.githubusercontent.com/php-stubs/wordpress-stubs/master/wordpress-stubs.php
        curl.exe -sL -o vendor\stubs/woocommerce-stubs.php https://raw.githubusercontent.com/php-stubs/woocommerce-stubs/master/woocommerce-stubs.php
    · woocommerce-stubs.php NO se autodeclara: hay que pasar los DOS archivos a
      intelephense.stubs (ya están en .vscode/settings.json).
    · Tras descargarlos: recargar la ventana y ejecutar "Intelephense: Index all".
    · assets/css/main.min.css está excluido con files.exclude: es un reset
      minificado de terceros, no está encolado en ningún wp_enqueue_* del tema,
      y sus avisos de vendor-prefix no son código nuestro.
- PHP local: 8.5.9 (CLI, ZTS) — solo para php -l (§8: producción corre 8.5.4).
- uv 0.12.3 — con él está instalado specify-cli (uv tool list → specify-cli v1.1.0).
- specify-cli 1.1.0 (Spec Kit) — %USERPROFILE%\.local\bin\specify.exe.
- Spec Kit: integración cline única y predeterminada, con scripts PowerShell
  (init-options.json → "script": "ps"). Los workflows /speckit-* para Cline
  viven en .clinerules/workflows/ y los scripts en .specify/scripts/powershell/.
  · .clinerules/workflows/ y .specify/ son archivos GESTIONADOS por Spec Kit:
    no editarlos a mano, no copiarlos ni moverlos.

COMPROBACIÓN DEL SHELL (primer paso de todo diagnóstico):
- $PSVersionTable.PSVersion → esperado: Major 7.
- (Get-Process -Id $PID).Path → esperado: ruta terminada en pwsh.exe.
- Si el shell no es pwsh 7, se corrige el entorno ANTES de diagnosticar el
  repositorio: no se atribuye al tema lo que es deriva del shell.

ESTADO DE SPEC KIT:
- specify version → CLI 1.1.0 · Platform Windows · Python 3.14.6.
- specify integration status → esperado: State OK; default e installed: cline;
  Modified managed files: 0; Missing managed files: 0.
- specify check → esperado: "Specify CLI is ready to use!" (Cline figura como
  IDE-based, no tiene chequeo por CLI: es correcto).

ACTUALIZAR LA INTEGRACIÓN (solo cuando corresponda):
- specify integration upgrade cline --script ps, desde la raíz del repositorio.
- Revisar SIEMPRE el resultado con git status y git diff antes de commitear: la
  actualización reescribe .clinerules/workflows/ y .specify/ (manifiestos y
  scripts incluidos). Commitear la actualización como cambio propio, separada
  de cambios funcionales o de documentación.
- La CLI se gestiona aparte: specify self check (solo lectura) y specify self
  upgrade para actualizarla.

REGLAS OPERATIVAS (aplican a personas y a agentes de IA):
- Ejecutar los comandos desde la raíz del repositorio salvo indicación explícita.
- Ante un fallo, diagnosticar en este orden: shell activo (pwsh 7), directorio
  actual, PATH y disponibilidad de la herramienta. Recién después revisar el código.
- Los ejemplos POSIX (grep, sed, export, rutas con /) no aplican aquí. Los
  scripts de Spec Kit se invocan como PowerShell, por ejemplo:
  .specify/scripts/powershell/check-prerequisites.ps1 -Json -PathsOnly
- Traducciones directas de §7 y de los quickstarts:
  · grep -nE 'patrón' archivo  →  Select-String -Path archivo -Pattern 'patrón'
  · grep -rn 'patrón' carpeta  →  Get-ChildItem carpeta -Recurse -File |
    Select-String -Pattern 'patrón'
  · curl ...                   →  curl.exe ...  (en PowerShell, curl es alias
    de Invoke-WebRequest; el binario real es curl.exe)
  · php -l archivo.php         →  igual; usa el PHP local 8.5.9
  · && y || entre comandos     →  soportados en PowerShell 7; un error de parser
    ante ellos delata Windows PowerShell 5.1 (shell incorrecto)
- Rutas con espacios o caracteres especiales: entre comillas dobles.

LÍMITES DE SEGURIDAD (también para agentes de IA):
- PROHIBIDO modificar bases de datos, credenciales o servicios externos desde el
  entorno local.
- PROHIBIDO desplegar a producción: el despliegue es manual por FTP (§1) y
  ninguna tarea de IA termina con un paso de despliegue (constitución, "Regla de
  despliegue").
- Cualquier operación fuera de estos límites requiere aprobación explícita del
  mantenedor antes de ejecutarse.

VERIFICACIÓN REPRODUCIBLE EN LOCAL (lo que sí se prueba aquí):
- php -l sobre los PHP tocados (sintaxis; no verifica comportamiento).
- Select-String para invariantes: literales de color, wp_add_inline_*,
  style.transform, enqueue condicional, etc. (§7 y quickstarts de specs/).
- git diff --name-only para comprobar el alcance: nada bajo Tema-GeneratePress/
  ni plugins; el parent no se toca (principio X).
- Navegador, sesión real, caché o pago: verificación manual contra producción
  según §7. Este proyecto no tiene suite de pruebas, CI ni staging
  (constitución, "Restricción dominante").
