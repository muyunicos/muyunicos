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
- DESPLIEGUE AUTOMÁTICO por Git: la función Git de Hostinger (hPanel → Avanzado →
  Git) sincroniza el repositorio con /generatepress-child/. El push a GitHub NO
  despliega por sí solo: es Hostinger quien trae los cambios a la carpeta del
  tema. Verificado 2026-10-16: /generatepress-child/ es un working copy con .git/
  (bloqueado por el servidor, 403), y geo.php / compat-wplingua.php llegaron por
  esa vía.
  · El deploy trae EL REPOSITORIO COMPLETO, así que la documentación y los
    artefactos de trabajo quedan dentro de la carpeta del tema y son accesibles
    por HTTP: MIGRATION-GUIDE.md y specs/*.md responden 200, y Tema-GeneratePress/
    queda anidado dentro del child. NO meter secretos ni credenciales aquí.
  · No subir archivos a mano (FTP/Administrador de archivos): un archivo editado o
    subido directamente en el servidor deja el pull siguiente sin traer ESE
    archivo, sin error visible (pasó con functions.php el 2026-10-16). Por eso los
    módulos críticos se autocargan desde su dependencia: ver la autocarga de
    compat-wplingua.php al final de inc/geo.php.
  · Si un cambio no aparece en producción, revisar en ESTE orden. Medido el
    2026-10-16 con el bug del prefijo de idioma: el deploy había llegado completo
    (probado comparando los .gitignore y .md remotos contra el repo) y aun así
    inc/geo.php no surtía efecto.
      1. El archivo EN DISCO no es el del repo. Un archivo subido a mano o editado
         en el servidor queda "modificado localmente": el deploy lo respeta y no
         lo pisa, sin error visible (pasó con functions.php e inc/geo.php). Es el
         caso normal cuando antes se desplegaba por FTP. Verificar descargando el
         archivo del servidor y comparándolo con el repo:
           git diff --no-index -- inc/geo.php ruta\descargada\geo.php
         Arreglo: volver a subir ESE archivo con el contenido exacto del repo (o
         en el servidor: git checkout -- <archivo>). Al quedar sin diferencias, el
         deploy siguiente ya lo actualiza solo.
      2. Caché de OPCODE: solo si el archivo en disco coincide con el repo y el
         cambio no surte efecto. Purgar OPcache / reiniciar los workers PHP
         (con opcache.validate_timestamps en 0, cada deploy necesita purge).
      3. Pull fallido o conflicto en el hPanel, y por último caché de página
         (LiteSpeed y CDN de Hostinger).
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
- ⚠️ TTL de la caché de PÁGINAS: MEDIDO 2026-10-17 → **24 h**
  (`X-LiteSpeed-Cache-Control: public,max-age=86400` en la home; primera visita con
  `X-Litespeed-Cache: miss`; igual en muyunicos.com y br.muyunicos.com). El valor se
  cambia desde el panel de LiteSpeed, no desde un commit: si alguien lo toca, esta línea
  queda vieja.
  Medición: curl.exe -sI https://br.muyunicos.com/ | Select-String 'x-litespeed-cache|age'
  Consecuencia: ningún cambio "a hora exacta" se ve solo antes de 24 h. Los cambios que
  dependen de un borde de fecha programan un evento único en ese borde que purga la home
  (`mu_hero_banner_purge_borde`, feature 012, ver §8). El aislamiento por subdominio sigue
  intacto: la cookie _lscache_vary es un hash del host.
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
- Comando del System Cron de Hostinger (verificado 2026-10-17):
  `*/5 * * * * /usr/bin/php /home/u274449136/domains/muyunicos.com/public_html/wp-cron.php >/dev/null 2>&1`
  ⇒ wp-cron.php corre **cada 5 minutos**, así que cualquier evento agendado se ejecuta con
  **hasta 5 minutos de retraso**. Es aceptable para todo lo que hay hoy (la caché de páginas
  dura 24 h); no hace falta un cron por minuto.
- ⚠️ Ese `>/dev/null 2>&1` descarta stdout **y stderr**, y en CLI `error_log()` escribe a
  stderr cuando el php.ini no define `error_log` (o `WP_DEBUG_LOG` no está activo): **un rastro
  hecho sólo con error_log() desde el cron puede perderse**. Para el borde del hero (feature
  015) se escribe además un archivo plano en `wp-content/uploads/mu-logs/hero-borde.log`
  (mismo directorio que los contadores de `mu_bot404_record_hit()`), y el estado se ve en el
  panel de banners («Última purga por borde de fecha: … · N host(s) · modo»).
- Dependencia exclusiva del System Cron de Hostinger. 
  Las tareas pesadas (reindexado, transient clears) se ejecutan a nivel servidor para 
  evitar penalizar el rendimiento del frontend.
- Importación semanal de reseñas (evento `mu_reviews_weekly_import`): lo ejecuta el System
  Cron igual que el resto de las tareas. Consulta a Google Places 1 vez por semana y no
  escribe ni purga caché si no hay nada nuevo. Rastro visible en la sección de reseñas
  ("Última importación automática: …").

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

Rutas y elementos que existen en un solo país (MEDIDO 2026-10-16):
- El sitio es UNA instalación de WordPress servida en 10 hosts: páginas, menús y bloques
  son globales, y no existe "publicar sólo en Argentina" en wp-admin ni en los plugins
  (WCPBC ajusta precios, wpLingua ajusta idioma: ninguno restringe una URL por país).
  La regla vive en el tema: inc/country-scope.php (API en §6, "ALCANCE POR PAÍS").
- Registrada hoy: /envios/ → sólo Argentina. Describe las zonas de cobertura, los costos
  en ARS y el retiro en Catamarca 2255. En los 9 subdominios: 302 a /tienda/ del mismo
  host (con el prefijo de idioma del país), sin el enlace en el pie, con el hreflang del
  tema reducido al host actual y sin figurar en el page-sitemap.xml del subdominio.
  Antes del fix (medido): 200 con el contenido argentino, con el enlace en el pie de los
  10 hosts y con 11 alternates hacia todos los subdominios.
- Candidata declarada, NO registrada: /reembolso_devoluciones/ ("Botón de
  arrepentimiento", figura legal argentina). Restringirla fuera del país es una decisión
  comercial: ver §8.
- Fuera de alcance por diseño: /pt/envios/ y /en/envios/ del dominio raíz (el país es
  Argentina; el idioma es otra dimensión) y los elementos de menú de WordPress (son dato
  de la base: se quitan en wp-admin → Apariencia → Menús).

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
- Rank Math SEO → rank_math_description, rank_math_canonical (inc/seo-hreflang.php) y
  rank_math/sitemap/entry (inc/country-scope.php: saca del sitemap las rutas con alcance
  por país en los hosts que no las sirven; el primer argumento es un ARRAY, ver §8).
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
  proveedor dentro, y si el orden cambia el proveedor lo rechaza. El DESTINO conserva host,
  ruta e idioma de la página (feature 005: se localiza con mu_wplingua_localize_uri(); el
  formato y el orden de los parámetros no cambian).
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
│   │                        # muyu_get_countries_data() es la fuente única de la config
│   │                        # del país e incluye 'tz' (huso del DESTINO): lo usa el Hero
│   │                        # para resolver fechas locales, nunca el huso del servidor.
│   │                        # Fallback por dominio ante errores de DB ("Commands out of sync").
│   ├── compat-wplingua.php # wpLingua: conserva el prefijo de idioma (/pt, /en) en el 301
│   │                        # canónico del core. También expone el prefijo de la petición
│   │                        # (mu_wplingua_request_prefix / localize_uri / localize_url /
│   │                        # prefix_in_urls) para los destinos que se arman con la URL
│   │                        # actual: login social y "Mi Cuenta" (feature 005; contrato en
│   │                        # specs/005-login-language-redirect/contracts/language-prefix.md).
│   │                        # Módulo crítico: se autocarga desde functions.php y desde
│   │                        # inc/geo.php, para que el filtro se registre aunque functions.php
│   │                        # no llegue actualizado (ver §1).
│   ├── country-scope.php   # Alcance por país de rutas y elementos: registro único
│   │                        # (muyu_get_country_scope_routes), guard HTTP 302 en
│   │                        # template_redirect p15, helper muyu_is_country() y hreflang
│   │                        # reducido. Hoy: /envios/ sólo en AR. API en §6.
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
│   ├── hero-banners.php    # Hero Banners Manager (UI Admin y render). Fechas por país:
│   │                        # 'inicio'/'fin' generales + 'fechas[PAIS]' que las
│   │                        # sobrescribe (píldoras de país en el bloque de ajustes
│   │                        # generales). La ventana se resuelve en el HUSO DEL PAÍS
│   │                        # (mu_hero_banner_ventana_instantes) y el transient por país
│   │                        # caduca en el próximo borde de fecha (TTL adaptativo).
│   │                        # Vigencia ANUAL (feature 012): 'anual' /
│   │                        # 'fechas[PAIS][anual]' ignoran el año guardado (sólo día y
│   │                        # mes) y soportan el cruce de fin de año. Un evento único
│   │                        # purga la home en cada borde (ver §8).
│   │                        # RENDER COMPARTIDO (feature 013): mu_hero_slide_render() es
│   │                        # la fuente ÚNICA del HTML del slide (front + lienzo del
│   │                        # editor). Vive acá porque hero-banners carga antes que ui.
│   ├── ui.php              # Header icons, Cart badge, WhatsApp, shortcodes, bestsellers,
│   │                        # testimonios Google Places, hero promos, popcat.
│   ├── ai-translate.php     # Servicio de traducción con IA (Gemini): API interna
│   │                        # mu_ai_translate() + caché + contador de uso. Tope de textos
│   │                        # por acción POR ÁMBITO (mu_ai_ambito_per_action).
│   │                        # Único envío saliente con contenido del sitio (ver §7).
│   ├── ai-translate-admin.php # Pantalla del servicio (WC → Marketing → Traducciones IA).
│   ├── reviews-identity.php # Identidad de reseñas: deduplicación por enlace/nombre (puro).
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
│   │                        # country-modal.css, navigation-chips.css, hero-promo.css
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
│   NOTA — css/components/hero-promo.css: estilos del banner del hero, MOVIDOS
│   desde home.css §3 (feature 013, Fase 0) sin cambiar ninguna regla, y
│   encolados también en el panel de banners. El marcado tiene una única fuente:
│   mu_hero_slide_render() en inc/hero-banners.php, que usan el front y el lienzo
│   del editor. No es una hoja de sección: si se toca, se ve en el front Y en el
│   editor (ver §8).
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

SERVICIO DE TRADUCCIÓN CON IA (API INTERNA)
- Módulo: inc/ai-translate.php. Pantalla: inc/ai-translate-admin.php (WC → Marketing).
- Único punto de salida: mu_ai_translate( $items, $lang, $ambito, $opts ) → array con items,
  variants, missing y usage. Nunca lanza: siempre devuelve estado.
- Ámbitos en mu_ai_ambitos(): reviews (el comentario) y hero_banner (6 textos).
- Perfil por ámbito (ajustes → contexts[ámbito]): role, tone, glossary, exclude, personas,
  variants (1..5), per_action, temperature, max_chars, allow_html.
- Tope de textos por acción: es POR ÁMBITO (mu_ai_ambito_per_action()). Por defecto, la cantidad
  de campos del ámbito (reviews 1, hero_banner 6) y el perfil puede ajustarlo (0 = automático).
  Antes era una sola cifra global (5) y un banner completo (6 textos) quedaba bloqueado con
  "la acción tiene 6 textos y el límite por acción es 5". Los límites de servicio (por minuto y
  por día) siguen en limits[].
- Consumidores de variantes: el editor de reseñas pide una tanda de 5 (acotada a las personas) y
  ofrece botones numerados; el de banners deja que la cantidad la fije el ámbito y ofrece los
  mismos botones cuando la respuesta trae más de una versión (feature 011).
- Firma de instrucciones: mu_ai_glossary_version() incluye TODOS los campos del perfil; si se
  edita cualquiera, la caché anterior deja de reutilizarse (es lo buscado).
- Caché: opción _mu_ai_cache (500 entradas, 180 días). Guarda la tanda completa de variantes en
  JSON y al leer acepta también las entradas viejas de texto plano.
- Contador propio: _mu_ai_usage (solicitudes, caracteres y variantes del día y del mes).
- Consumidores: el editor de reseñas (js/reviews-admin-modal.js) y el de banners
  (js/admin-hero-banners.js). Ninguno llama al proveedor por su cuenta.
- Verificación: specs/006-ai-translate-core/harness/arnes.php (módulo real, WordPress simulado).
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

ALCANCE POR PAÍS (API REUTILIZABLE — inc/country-scope.php)
- PROBLEMA QUE RESUELVE: el sitio es UNA instalación de WordPress en 10 hosts. WordPress no
  tiene "publicar sólo en un país": una página, un menú o un bloque son globales. Sin esta
  API, cualquier elemento que sólo valga en un país se sirve en los 10.
- REGLA DE ORO: el país es el HOST (fuente única: muyu_get_current_country_from_subdomain()
  en inc/geo.php). El idioma (/pt, /en de wpLingua) es otra dimensión: esta API NO
  restringe por idioma.

    API
    muyu_get_current_country()                  → 'AR' | 'MX' | 'CO' | ...
    muyu_get_current_host()                     → 'mexico.muyunicos.com' | ...
    muyu_is_country( 'AR' | [ 'AR', 'MX' ] )    → bool   ( '' o [] = todos )
    muyu_get_country_scope_routes()             → registro normalizado
    muyu_get_country_scope_route( 'envios' )    → entrada | null
    muyu_get_country_scope_route_for_request()  → slug de la ruta pedida | null
    muyu_get_country_scope_hreflang_tags()      → alternates de la ruta | []
    Filtro muyu_country_scope_routes            → suma rutas sin editar el módulo

    CÓMO RESTRINGIR ALGO (elegir lo más barato)
    1) Ruta completa (página o plantilla): agregarla a muyu_get_country_scope_routes() con
       'countries', 'redirect' ('shop' | 'home' | URL absoluta) y 'label'. Con eso quedan
       cubiertos el 302 en los países que no la publican y el hreflang reducido. El enlace
       que apunte a la ruta se condiciona con el MISMO registro, para que ruta y enlace no
       se desincronicen: así está hecho el <li> de "Información de Envíos" en inc/ui.php.
    2) Elemento que imprime el tema:   if ( muyu_is_country( [ 'AR' ] ) ) { ... }
    3) Elemento que imprime un plugin o wp-admin: CSS con la body class que ya inyecta
       geo.php (línea 864):  body:not(.mu-country-ar) .mi-elemento { display: none; }
       (2) es preferible a (3): no emite HTML ni trabajo de pintado.

    CONTRATO DEL GUARD: template_redirect prioridad 15 (después del 301 canónico del core
    y de mu_litespeed_nocache_404, antes de handle_redirects de digital-restriction); 302
    al MISMO host con el prefijo de idioma del país (BR→/pt, US→/en, sin duplicarlo); se
    aplica también a bots; no corre en admin/REST/AJAX/cron/feed; la respuesta lleva la
    cabecera X-MU-Country-Scope: redirect (su ausencia con un 200 significa que el guard no
    se cargó, no que la ruta esté bien).

- LÍMITES: no cubre elementos de menú de WordPress (son dato de la base), ni las variantes
  de idioma del dominio raíz, ni restricciones por visitante (IP, sesión, rol).
- DETALLE Y ALTA DE UNA RUTA NUEVA: specs/004-country-scope/contracts/country-scope.md.
  VERIFICACIÓN: specs/004-country-scope/quickstart.md (Fase A local con stubs, Fase B en
  producción).

PREFIJO DE IDIOMA DE LA PETICIÓN (API REUTILIZABLE — inc/compat-wplingua.php)
- PROBLEMA QUE RESUELVE: wpLingua reescribe $_SERVER['REQUEST_URI'] en 'init' (p1)
  quitándole el prefijo (/pt, /en). Cualquier destino armado con la URL actual en una página
  viva —o en /login/, donde no hay prefijo ni estado de idioma— pierde el idioma y devuelve
  al comprador a la base. Ver §7 "EL LOGIN SOCIAL DEVOLVÍA A LA BASE SIN EL PREFIJO DE IDIOMA".
- REGLA DE ORO: el idioma es la URL, no el país. La fuente del prefijo es la URI ORIGINAL
  capturada al cargar el módulo (mu_wplingua_original_uri()), y la lista de prefijos sale de
  geo.php (mu_wplingua_language_prefixes()). Nunca se deduce por país ni por navegador.

    API
    mu_wplingua_request_prefix()                → '' | '/pt' | '/en'  (idioma de la petición)
    mu_wplingua_localize_uri( $uri, $prefix )   → ruta con el prefijo, sin duplicarlo
    mu_wplingua_localize_url( $url, $prefix )   → URL absoluta localizada (host intacto)
    mu_wplingua_prefix_in_urls( [ $urls ] )     → prefijo detectado en URLs de la petición

    CUÁNDO USARLA
    1) Destino de redirección en una página viva (login social, checkout): localize_uri()
       sobre la URL actual. No rearmar la URL con add_query_arg() cuando el destino es
       contrato con un proveedor (formato de mu_social_login_url()).
    2) Destino resuelto fuera de la página (/login/): prefix_in_urls() con las URLs que trae la
       petición (destino pedido, destino original, procedencia) + localize_url() con ese
       prefijo. Sin señal se conserva el idioma origen: NO adivinar.
    3) Nunca localizar rutas técnicas (/wp-admin, /wp-json, /wp-login, AJAX), ni destinos de
       terceros o de otro host.

- LÍMITES: no traduce slugs ni contenido (eso es de wpLingua), no cambia el idioma de una
  página sin prefijo y no reemplaza a la API de alcance por país (arriba): son dimensiones
  distintas, idioma vs país.
- CONTRATO: specs/005-login-language-redirect/contracts/language-prefix.md.
  VERIFICACIÓN: specs/005-login-language-redirect/quickstart.md.
- AL AGREGAR O CAMBIAR UNA RUTA: además de desplegar, purgar la CACHÉ DE SITEMAP de Rank
  Math. Es un archivo XML por host en wp-content/uploads/rank-math/ (ver §8): si no se purga,
  el sitemap del host afectado sigue sirviendo la copia vieja y el cambio parece no haber
  llegado. Medido el 2026-10-16 con /envios/: ocho hosts quedaron corregidos solos y uno
  (mexico) siguió listando la ruta por una copia de 5 minutos antes del deploy.

════════════════════════════════════════════════════════════════
7. DIAGNÓSTICO DE ERRORES CONOCIDOS
════════════════════════════════════════════════════════════════

Antes de diagnosticar cualquier fallo, verificar el entorno (§9): shell activo
(pwsh 7), directorio actual, PATH y disponibilidad de la herramienta.
ENVÍO EXTERNO DECLARADO — TRADUCCIÓN CON IA (Google Gemini)
- Qué se envía: SOLO los textos que el mantenedor decide traducir (comentarios de reseñas o
  textos de banner) más las instrucciones del ámbito. Nunca datos de clientes: si el módulo
  detecta un correo, teléfono o número de pedido, rechaza el lote entero sin enviarlo
  (mu_ai_looks_personal()).
- A dónde: https://generativelanguage.googleapis.com (v1beta).
- Cuándo: sólo al pulsar «Traducir» en un editor del panel. Nunca en el request de un
  visitante.
- Clave: MU_GEMINI_API_KEY en la configuración del servidor (tiene prioridad) o la opción
  _mu_ai_key (sin carga automática, enmascarada en la pantalla). Viaja en la cabecera
  x-goog-api-key: no queda en el repo, ni en la base, ni en la URL.
- Verificación: WC → Marketing → Traducciones IA → «Probar conexión» (devuelve el modelo y la
  latencia) + el contador de consumo del día.
- Reversión: revertir el commit y borrar las opciones _mu_ai_settings, _mu_ai_key, _mu_ai_cache
  y _mu_ai_usage. Sin clave el servicio queda inerte y el sitio se ve idéntico.

ERRORES "Commands out of sync" (MySQL)
- Causa: Plugins de terceros (Jetpack, Action Scheduler, Rank Math, Facebook Pixel,
  WooCommerce Sessions) ejecutan consultas SQL durante el hook `shutdown` de WordPress,
  DESPUÉS de que la conexión a la BD ya se cerró.
- El tema NO es responsable: No registra hooks de shutdown, no usa register_shutdown_function,
  no hace wp_remote_post. El único wp_remote_get es el de testimonios (Google Places) y corre
  en dos casos, ninguno en el request de un visitante: el enlace manual ?force_reviews=1 (solo
  admins) y la tarea programada semanal `mu_reviews_weekly_import`.
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

EL 301 CANÓNICO DESCARTA EL PREFIJO DE IDIOMA EN LA HOME (2026-10-16)
- Síntoma: al hacer clic en la bandera de país (enlace cross-domain del selector,
  que el linker de Google decora con `_gl`), la URL con prefijo aterrizaba en la
  home SIN el prefijo: br.muyunicos.com/pt/?_gl=1*abc → 301 → /?_gl=1%2Aabc.
  Secuela: el Hero Banner mostraba el idioma del PAÍS en lugar del de la página.
- Causa: con la query cruda (`*`, `+`, `@`) redirect_canonical() reconstruye la
  canónica escapando esos caracteres, no coincide con la solicitada y responde
  301 a la home, que en el idioma origen es `/` (sin prefijo). Medido con un
  mu-plugin temporal: el 301 sale de canonical.php:840 → wp_redirect. (Los 302 de
  wpLingua — redirección por idioma del navegador y por slug — no aplican acá.)
- ⚠️ LECCIÓN CLAVE (para cualquier módulo que lea la URL): wpLingua REESCRIBE
  $_SERVER['REQUEST_URI'] quitando el prefijo de idioma en wplng_ob_start (hook
  'init', prioridad 1), para que WordPress resuelva la página original y después
  traducir el HTML. En 'template_redirect' (donde corre el canonical) esa
  variable YA NO tiene el prefijo: un filtro que la lea ahí no reconoce el
  idioma. Hay que capturar la URI original al cargar el módulo (functions.php se
  incluye antes de 'init') y usarla después: ver mu_wplingua_original_uri() en
  inc/compat-wplingua.php.
- Fix: filtro wp_redirect (prioridad 15) que re-inyecta el prefijo cuando el
  destino es el mismo host con la misma ruta sin él, y normaliza la query del
  destino (`*` → `%2A`, que el core ya acepta como canónico ⇒ el 301 no se
  repite). Solo en 301/302/307, mismo host, misma ruta y fuera de rutas técnicas.
  Trazabilidad: cabecera X-MU-WpLingua-Lang: kept.
- Verificación (PowerShell 7):
    curl.exe -s -I -A "Mozilla/5.0" "https://br.muyunicos.com/pt/?_gl=1*abc"
    # esperado: 301 + Location .../pt/?_gl=1%2Aabc + X-MU-WpLingua-Lang: kept
    curl.exe -sL -A "Mozilla/5.0" "https://br.muyunicos.com/pt/?_gl=1*abc" |
      Select-String '<html[^>]*lang="pt"'
- APRENDIZAJE: los caracteres que el saneado de WordPress escapa no son un
  detalle cosmético de la query: cambian la comparación canónica y pueden mover
  al visitante de página y de idioma. Y antes de dar por fallido un fix, medir
  DENTRO de WordPress (mu-plugin temporal) en vez de deducir desde afuera: acá el
  filtro estaba registrado y los archivos correctos; lo que fallaba era la
  variable que el código leía.


EL LOGIN SOCIAL DEVOLVÍA A LA BASE SIN EL PREFIJO DE IDIOMA (2026-10-16)
- Síntoma: un comprador que entraba con Google o Facebook desde br…/pt/ o us…/en/
  aterrizaba en la base de su subdominio, en español. El destino ya venía mal formado:
  medido con curl, el enlace del botón en /pt/tienda/ era
  redirect=https://br.muyunicos.com/tienda/ (sin /pt).
- Causa: el modal arma su "volver a donde estabas" con $_SERVER['REQUEST_URI'] en
  wp_footer, y ahí wpLingua YA le quitó el prefijo (misma LECCIÓN CLAVE de arriba). El
  destino de "Mi Cuenta" tras el login por usuario/contraseña tenía el mismo defecto,
  porque /login/ se sirve siempre en el idioma origen: medido, responde 200 con
  <html lang="es"> y sin cookies de wpLingua (el idioma vive sólo en la URL).
- Fix: helpers en inc/compat-wplingua.php (prefijo de la petición desde la URI original +
  localización idempotente de rutas y URLs) usados por inc/auth-modal.php, inc/login.php
  e inc/checkout.php. El formato de la URL del proveedor social NO cambia: sólo su destino.
- Verificación (PowerShell 7), después de purgar la caché del host:
    curl.exe -s -A "Mozilla/5.0" "https://br.muyunicos.com/pt/tienda/" |
      Select-String 'loginSocial'
    # esperado: redirect=https%3A%2F%2Fbr.muyunicos.com%2Fpt%2Ftienda%2F
    curl.exe -s -A "Mozilla/5.0" "https://muyunicos.com/tienda/" | Select-String 'loginSocial'
    # esperado: sin prefijo (idioma origen)
  Más el login real con Google y Facebook desde /pt/ y /en/, que no es automatizable.
- APRENDIZAJE: el prefijo de idioma no se pierde sólo en el 301 canónico; cualquier
  destino construido con la URL actual hereda el mismo problema, y el síntoma aparece en
  el peor momento (al identificarse el comprador). Antes de armar una redirección, leer la
  URI con mu_wplingua_original_uri() y NO con $_SERVER['REQUEST_URI'].


ERRORES PÁGINA SÓLO-AR VISIBLE EN SUBDOMINIOS (/envios/, MEDIDO 2026-10-16)
- Síntoma: /envios/ responde 200 en los 9 subdominios con el contenido de Mar del Plata
  (zonas de cobertura, costos en ARS, teléfono local, retiro en Catamarca 2255), el enlace
  "Información de Envíos" aparece en el pie de los 10 hosts y la URL figura en cada
  page-sitemap.xml. Medido antes del fix: 11 alternates hreflang hacia todos los subdominios.
- Causa: el sitio es UNA instalación de WordPress servida en 10 hosts: la página, el menú y
  los bloques son globales, y ningún plugin del sitio restringe una URL por país (WCPBC
  ajusta precios, wpLingua ajusta idioma). Ver §3 y la API de §6.
- Fix (feature 004-country-scope, en main): registro único en
  inc/country-scope.php + guard en template_redirect p15 (302 a la tienda del mismo host) +
  enlace condicionado en inc/ui.php + hreflang reducido en inc/seo-hreflang.php + filtro de
  sitemap de Rank Math.
- Diagnóstico (el orden importa):
    curl.exe -sI https://mexico.muyunicos.com/envios/ | Select-String 'HTTP/|Location|X-MU-Country-Scope'
  · 302 con Location del mismo host y X-MU-Country-Scope: redirect → correcto.
  · 200 sin la cabecera → el guard no se cargó: revisar el orden de §1 (la causa habitual es
    functions.php modificado en el servidor, que el deploy respeta sin avisar).
  · 200 con la cabecera → la respuesta estaba cacheada de antes: purgar la caché de ese host
    (el 200 de /envios/ se cachea 7 días por host) y repetir.
  · Nunca decidir la ruta leyendo $_SERVER['REQUEST_URI'] en template_redirect: wpLingua ya
    le quitó el prefijo de idioma (ver "LECCIÓN CLAVE" arriba). El guard usa is_page() y
    mu_wplingua_original_uri().
- Caso de fallo esperado si el deploy no llegó: el síntoma es idéntico al estado anterior
  (200 en los subdominios), sin error visible. Por eso el guard emite una cabecera propia.
- Síntoma relacionado, en el sitemap: si un host sigue listando la ruta en su
  page-sitemap.xml mientras los demás ya no, no es el código: es el archivo de caché de
  sitemap de Rank Math de ese host (wp-content/uploads/rank-math/). Cómo purgarlo: §8,
  "SEGUNDA TRAMPA".


════════════════════════════════════════════════════════════════
8. DEUDA TÉCNICA

FECHAS POR PAÍS EN EL HERO (2026-10-17, feature 010-fechas-banner-por-pais):
- Estado: cada país puede sobrescribir la ventana de fechas del banner en 'fechas[PAIS]';
  un campo vacío (o idéntico a la general del mismo campo) hereda 'inicio'/'fin'. La
  clave del transient no cambió: sigue siendo por país (mu_hero_banners_active_{PAIS}).
- Huso horario: la ventana se resuelve en el huso del DESTINO ('tz' de
  muyu_get_countries_data(), respaldo wp_timezone()). Antes el mismo '01/06' caía a horas
  distintas por subdominio (Madrid y Buenos Aires están a 5 h en junio). El fin del día se
  calcula como "inicio del día siguiente − 1 s" para que un cambio de horario de verano no
  deje el borde en una hora inexistente.
- Fechas inválidas: se validan con checkdate() al guardar ('99012026' ya no se interpreta
  como 2026-04-09 por rollover). Un valor inválido guardado se trata como "sin límite" (no
  se oculta la promo) y la fila del panel lo avisa, junto con los rangos invertidos.
- TTL adaptativo: el transient caduca en el próximo borde de fecha del país
  (mu_hero_banner_proximo_borde()), con piso de 60 s y techo de 12 h.
- Verificación automática: specs/010-fechas-banner-por-pais/quickstart.md Fase A
  (arnés PHP 96 OK + arnés JS 28 OK, más la no-regresión de 008 —30 OK + 17 OK—,
  011 —18 OK— y 006 —54 OK—).
- [ ] Verificación manual del PANEL (píldoras de país, puntos, placeholders, avisos, huso a
  la vista, guardado): no es automatizable en este proyecto — el manejo de jQuery del editor
  sigue sin arnés, la misma deuda que la feature 008. Fase B del quickstart de 010.
- [x] RESUELTO en la feature 012 (2026-10-17): el TTL de páginas se MIDIÓ (24 h, §2) y el
  evento único en el borde quedó implementado (mu_hero_banner_programar_purga_borde +
  mu_hero_banner_purge_borde_ejecutar). Ver el bloque "VIGENCIA ANUAL" más abajo.

VARIANTES DE TRADUCCIÓN EN EL EDITOR DE BANNERS (2026-10-17, feature 011):
- El editor de banners ahora ofrece los botones numerados 1·2·3… (uno por persona) igual que
  el de reseñas: elige la versión que queda en los 6 campos del idioma activo. La cantidad la
  fija el ámbito (personas × variantes); el banner NO pide un número fijo de variantes (a
  diferencia de reseñas, que pide 5). Ver specs/011-variantes-banners-ia/.
- Tope de textos por acción por ámbito: ver §6 (mu_ai_ambito_per_action()). Antes, un banner
  con los 6 campos cargados quedaba bloqueado por el tope global de 5.
- [ ] Verificación manual de los botones (jQuery + navegador): Fase B del quickstart de 011.
  Misma deuda declarada que 008/010.
- Índice ↔ persona: si el modelo omite una versión, las siguientes se corren de índice
  (el servicio descarta los textos vacíos) y el title del botón puede no coincidir con la
  persona real. Se mitiga mostrando los campos sin texto; corregirlo del lado del servicio
  afecta también a reseñas: decisión aparte.

VIGENCIA ANUAL DEL BANNER (2026-10-17, feature 012-vigencia-anual):
- Estado: un banner (o un país) puede repetir su ventana todos los años con 'anual' /
  'fechas[PAIS][anual]': se ignoran los años guardados y sólo cuentan día y mes. Soporta el
  cruce de fin de año (20/12 → 10/01) y acota el 29/02 al 28/02 en años no bisiestos. La
  clave por país se persiste SÓLO si difiere de la general (ausente = hereda); el panel
  manda un campo oculto junto a la casilla para que "destildado" no se confunda con
  "ausente" (un país añadido después no se guarda como excepción por accidente).
- Cascada: mu_hero_banner_ventana_instantes() recibió un $now OPCIONAL y delega en
  mu_hero_banner_ventana_anual(); is_active_for_pais() y proximo_borde() le pasan el MISMO
  instante (antes proximo_borde resolvía la ventana con time(): con ventanas anuales habría
  devuelto un borde del pasado y el TTL habría quedado corto).
- Caché de páginas: TTL medido en 24 h (§2). Por eso el borde programa un EVENTO ÚNICO
  (wp_schedule_single_event 'mu_hero_banner_purge_borde') que purga la home de cada host
  —por URL si el plugin expone litespeed_purge_url; si no, litespeed_purge_all, siempre
  condicionado a un borde real—, limpia los transients y se reprograma. Rastro
  "[MU-HERO-BORDE]" en debug.log. Al guardar se descarta el evento viejo y se recalcula.
- ⚠️ Primer guardado tras el deploy: la opción gana la clave 'anual' en todos los banners,
  así que el Hash Gate detecta un cambio real y purga la caché UNA vez (esperado).
- Verificación automática: specs/012-vigencia-anual/quickstart.md Fase A (arnés PHP 87 OK +
  arnés JS 18 OK, más la no-regresión de 006/008/010/011). 010 se ajustó en UNA aserción:
  la forma exacta del array de mu_hero_banner_avisos(), que ahora incluye
  'anuales_incompletas'.
- [ ] Verificación manual del PANEL y del CRON (Fase B del quickstart de 012): la casilla y
  su herencia, los avisos, y el disparo del borde con su purga. Misma deuda declarada que
  008/010/011 (el manejo de jQuery no es automatizable en este proyecto).
- [ ] Purga en el borde y cron: DISABLE_WP_CRON está activo y las tareas las ejecuta el
  System Cron de Hostinger. Si el sitio quedara sin visitas justo en el borde, la home se
  regeneraría en la primera visita posterior (el transient del país —la fuente de verdad
  del filtro— caduca igual en el borde).

RENDER COMPARTIDO DEL HERO + LIENZO DEL PANEL (2026-10-17, feature 013-panel-hero-lienzo):
- Fase 0 IMPLEMENTADA (front): el marcado del slide pasó de ui.php inline a
  mu_hero_slide_render() (inc/hero-banners.php) y los estilos del hero de home.css §3 a
  css/components/hero-promo.css, SIN cambiar una regla ni un atributo. Objetivo: que el
  front y el futuro lienzo del editor usen la MISMA fuente (antes, cualquier vista previa
  habría duplicado HTML y CSS, con deriva garantizada).
- Por qué el renderer vive en hero-banners.php: functions.php carga icons (360) →
  hero-banners (379) → ui (381) y el panel renderiza la fila desde hero-banners; un
  renderer en ui.php no existiría cuando el panel lo necesita.
- Verificación automática: specs/013-panel-hero-lienzo/harness/arnes-render-hero.php (37 OK)
  compara contra un fixture capturado de PRODUCCIÓN antes de la extracción: igualdad
  normalizada del slide, byte a byte en h1/p/src/alt/href y textos de CTA, y QUÉ función de
  escape usa cada campo (wp_kses_post el título, esc_html el resto del texto, esc_attr el
  alt, esc_url las URLs). La normalización de espacios es deliberada: la indentación de un
  bucle inline no es reproducible en una función y el navegador la ignora. Atención al dato
  medido: se comparan ambos lados serializados como DOM, porque libxml baja viewBox a
  viewbox (el viewBox se verifica aparte sobre el HTML crudo).
- [ ] Verificación manual en producción (Fase B del quickstart de 013): hero de
  muyunicos.com + 2 subdominios idéntico al fixture y 'hero-promo.css' servido. Sin eso, la
  Fase 0 se marca NO VERIFICADA.
- [x] Fase 1 (jerarquía de la fila, COMPLETA): fila-resumen siempre visible con ESTADO calculado
  (mu_hero_banner_estado(): dónde se ve, quién quedó fuera de ventana y cuándo cambia, en el huso
  del país), miniatura, plegado por fila, Duplicar (renumera los name="mu_banner[N]" del clon),
  Orden ▲▼ (el orden del DOM es el orden de los slides) y Eliminar con deshacer. **Chips de país
  unificados**: el checkbox de paises[] ES el chip (un solo control para activar; el punto marca los
  países con ventana propia) y el dropdown «Países» se retiró. **Vigencia como TABLA**: una fila por
  ámbito (Global + cada país) con Inicio, Fin, «Cada año», la columna «Se ve» (ventana efectiva en el
  huso del ámbito, con el aviso por fila) y un × para limpiar esa ventana; los países sin tildar
  quedan atenuados y su ventana se conserva (M7). Antes había una píldora = pestaña por país, así que
  no se podían comparar dos ventanas.
- [x] Fase 3 · N1: clic en cualquier nodo del lienzo → foco y scroll al campo real, con resaltado.
  **N2 (COMPLETA)**: contenteditable="plaintext-only" en eyebrow, descripción, textos de CTA y texto
  del badge, con dos vías con el formulario (textoPlanoDeNodo() normaliza los <br>/<div> que inserta
  el navegador a saltos de línea para el textarea). El TÍTULO queda fuera a propósito: admite HTML
  (<span class="mu-highlight">) y editarlo en texto plano rompería el marcado.
- Desviaciones declaradas de la Fase 1: las píldoras de idioma y «Traducir» se quedan en la barra de
  la fila (gobiernan el idioma del lienzo y no deben esconderse al plegar) y las filas nacen
  desplegadas (plegar por defecto escondería el lienzo). El código muerto del dropdown se borró
  (T011b): posicionarDropdown(), el handler de cierre y el CSS de las píldoras/dropdown (−94 líneas).
- Verificación automática de 013: arnés PHP 97 OK (Fase 0 + lienzo + resumen + chips + tabla) y JS
  17 OK (escalaCanvas + textoPlanoDeNodo). El arnés 010 se ajustó en 5 aserciones (medían las
  píldoras y los paneles alternados, que ya no existen: ahora miden chips y filas de la tabla) y el
  012 en 2 (la nota «cada año» y el conteo de filas).

DENSIDAD DEL PANEL DE BANNERS (2026-10-17, feature 014-panel-densidad):
- Motivo medido con el editor en uso y 11 países: la tabla de vigencia mostraba 11 filas × 6
  columnas (96 controles) repitiendo la MISMA ventana heredada, los 10 chips estaban siempre
  visibles y cada fila arrancaba desplegada (~600 px). El caso típico (todos heredan) gastaba la
  pantalla en decir que 11 países son iguales.
- Implementado: (1) VIGENCIA POR EXCEPCIÓN —mu_hero_banner_excepciones() es un espejo del saneado
  (fechas[PAIS] existe sólo si difiere), así que la tabla muestra Global + las excepciones, con
  + excepción por país, × para devolver a la herencia y «ver todos los países» para la tabla
  completa (que NO se elimina)—; (2) filas PLEGADAS por defecto cuando hay 2+ banners (se mantiene
  el botón de plegado y no <details>: el resumen es interactivo); (3) chips de SÓLO los tildados +
  selector +N (el JS mueve el mismo nodo: cada país aparece una vez en el POST); (4) estado
  is-sin-paises: «no está tildado en ningún país» se distingue de «la ventana cerró» (en rojo,
  porque es un dato y rompe el banner en el sitio); (5) acciones de la fila en un menú ⋯.
- Cero cambios de datos y de contrato del POST: mismos nombres de campo, mismo saneado. Los países
  que heredan siguen renderizados (ocultos con is-hereda) justamente para no cambiar el POST.
- Pendiente (mismo spec): NADA. Los cuatro tramos restantes se implementaron en el mismo día:
  contenido agrupado en Texto/Botones/Imagen EN EL ORDEN DEL BANNER ($grupos_campos reordena el
  render), «Ajustes de la fila» con el hint de dónde se edita el texto del badge, lienzo pegajoso
  en dos columnas desde 1100px (por CSS, sin mover el DOM) y el huso del sitio legible («-03:00» →
  «huso del sitio») en la fila Global.
- Verificación: arnés 013 PHP 118 OK (14 aserciones nuevas de excepciones/estado y 8 de los grupos
  y el huso del sitio) y JS 17 OK;
  010 migró 2 aserciones (una regex de clase y una nueva de «Global nunca se oculta») y quedó en
  99 OK + 28; 012 (87 + 18), 011 (18), 008 (30 + 17) y 006 (54) sin cambios.
- ⚠️ Bug detectado y corregido en la implementación: la fila de Global recibía is-hereda y el CSS
  la habría ocultado (Global nunca se oculta). Lo cazó el arnés de 010.
- [ ] Verificación manual (Fase B del quickstart de 014): plegado, chips que se mueven entre el
  resumen y el selector, excepción creada y devuelta a la herencia, «ver todos», menú ⋯ y el estado
  rojo de «sin países».

VERIFICACIÓN DE CSS + CRON DE BORDES (2026-10-17, feature 015):
- Motivo (bug reportado): «ver todos los países» parecía no funcionar porque los 11 países se veían
  siempre. Causa: una edición anterior usó como ancla la PRIMERA línea de un comentario de dos, así
  que quedó su segunda línea suelta («   Global siempre; … */» sin su /*). El navegador descarta
  entonces la regla siguiente (.is-hereda { display: none }) y nada se ocultaba. Ningún arnés de
  PHP/JS podía verlo: el marcado estaba bien y el CSS no se ejecuta en el arnés.
- Arreglo (commit d6db174, desplegado solo porque era un bug visible) + ARNÉS DE CSS nuevo
  (specs/015-…/harness/css-check.js, node sin dependencias): comentarios balanceados (dice la línea
  del cierre huérfano), llaves balanceadas por archivo ignorando comentarios, y las reglas clave del
  panel. Se validó EN ORDEN (con el bug falla; después pasa, 68 OK) y volvió a cazar el MISMO error
  minutos después, en otra edición mía → de ahora en más, cualquier edición de CSS se verifica con
  este arnés antes de commitear. ⚠️ Lección de proceso: NO usar como ancla la primera línea de un
  comentario multilínea.
- CRON (mismo feature): el comando real del hosting es `*/5 * * * * /usr/bin/php …/wp-cron.php
  >/dev/null 2>&1` (documentado en §2) ⇒ los eventos corren con hasta 5 min de retraso, aceptable
  contra la caché de páginas de 24 h. Verificado además que ese `2>&1 >/dev/null` PUEDE descartar el
  rastro de error_log() en CLI, así que el borde escribe también un archivo plano
  (wp-content/uploads/mu-logs/hero-borde.log) y su estado se ve en el panel.
- Implementado: mu_hero_banner_borde_esperado (al programar), mu_hero_banner_revisar_bordes() como
  red de seguridad DIARIA (purga una vez si el borde esperado quedó atrás sin evento pendiente, y
  rearma el evento único si falta o está viejo; en el caso normal no hace nada),
  mu_hero_banner_ultima_purga + renglón «Última purga por borde de fecha: … · N host(s) · modo» en
  el panel (rojo si hay un borde pasado sin purgar) y modo url|all declarado.
- Verificación: arnés de CSS 68 OK; arnés 012 PHP 102 OK (15 aserciones nuevas en la sección 11,
  incluidos los dos modos de purga) y JS 18 OK; 013 (118 + 17), 010 (99 + 28), 011 (18), 008
  (30 + 17) y 006 (54) sin cambios.
- [ ] Verificación manual (Fase B del quickstart de 015): «ver todos» alternando de verdad, los dos
  eventos agendados, «Última purga por borde» actualizándose sin leer logs, y el archivo plano.

FIDELIDAD DEL LIENZO Y DENSIDAD (2026-10-17, feature 016):
- Bug real (observado por el mantenedor): el punto de la píldora de idioma salía SIEMPRE. El
  servidor estaba bien (guarda vacío lo idéntico al español); el cliente comparaba el campo del
  idioma con el español sin exigir contenido, y un campo VACÍO difiere ⇒ marcaba todas las filas y
  pisaba el valor correcto del PHP. Arreglado con el helper puro tieneContenidoPropio(valor, base)
  (no vacío Y distinto, con trim como el saneado) + 10 aserciones nuevas en el arnés JS de 010.
- La vista previa NO se veía como la home, por 4 causas concretas y verificables: (1) las variables
  locales del hero (--mu-hero-h-desktop, --mu-hero-transition y --mu-hero-eyebrow-bg = el rosa del
  eyebrow) se declaraban en .mu-hero-promo, la SECCIÓN, que el lienzo no incluye ⇒ en la preview el
  eyebrow salía sin fondo, sin altura mínima y sin transición; ahora se declaran en
  .mu-hero-promo-slider, la raíz que ambos contextos comparten (verificado: el front no cambia,
  porque los consumidores son el slider y sus descendientes); (2) los CTA copiaban .mu-btn* pero no
  sus estados :hover/:focus-visible; (3) el @media (max-width: 968px) del componente responde en el
  admin al ancho de la VENTANA ⇒ con la ventana angosta la preview se volvía móvil: se agrega el
  espejo @container (min-width: 969px); (4) la tipografía display la encola el tema padre sólo en el
  front ⇒ si en la preview el título se ve con otra fuente, hay que registrar el mismo handle en la
  pantalla del panel (declarado, sin verificar).
- El arnés de CSS de 015 ganó 12 aserciones para que esto no vuelva a pasar sin que lo cace:
  variables del componente declaradas en un ancestro que el lienzo incluya · las 3 del hero en el
  slider · cada @media con su espejo @container · .mu-btn* con estados · la caja del bloque de
  idioma en los tres idiomas. Validado contra el CSS anterior: falla; con el arreglo pasa (82 OK).
- Densidad (acordado con el mantenedor): el hueco vacío de abajo venía de colocar el lienzo en
  grid-row «1 / span 30» (30 filas implícitas). Ahora ≥1100px: fila 1 = bloque de idioma (hasta
  420px) + lienzo (hasta 400px) lado a lado; fila 2 = ajustes y vigencia a TODO el ancho (la fila
  que necesita ≥720px, con la tabla en un envoltorio con scroll horizontal). El lienzo ya no es
  pegajoso. Los anchos usan minmax(320px, …) para que encojan antes de desbordar.
- El bloque de idioma ESPAÑOL ahora lleva la misma caja que PT y EN (antes sólo los traducidos).
- El panel muestra también «Próximo borde: …» (de wp_next_scheduled) junto a la última purga: así
  «Todavía no hubo purgas por borde» deja de ser ambiguo — se ve si el cron está armado.
- Verificación: CSS 82 OK · 013 (118 + 17) · 012 (102 + 18) · 010 (99 + 38) · 011 18 · 008 (30 + 17)
  · 006 54; php -l y node --check limpios; sin cambios bajo Tema-GeneratePress/.
- [ ] Verificación manual (Fase B del quickstart de 016): píldora sin punto · preview con eyebrow
  rosa + hover + sin móvil fantasma · layout sin hueco · los tres bloques con caja · «Próximo
  borde» visible; y adelantar un borde (Fin = hoy) para ver la purga real.

UN SOLO BLOQUE DE IDIOMA POR EDITOR (2026-10-17, feature 017, idea del mantenedor):
- Qué cambió y por qué: había TRES .mu-hero-banner-lang-block (ES, PT, EN) y se ocultaban enteros al
  cambiar de idioma. Por eso el toolbar de idioma (píldoras + variantes de IA + «Traducir») no podía
  vivir junto a los campos —se habría ocultado con su bloque— y ocupaba una barra propia
  (row__head) arriba del contenido. Ahora hay UN bloque y los idiomas son VARIANTES DE CADA CAMPO
  (.mu-hero-banner-variante[data-lang], con `hidden` en las inactivas). El toolbar vive en la línea
  del primer grupo («Texto»), a la derecha del rótulo: no se mueve ni se oculta nunca.
- Menos marcado: 3 títulos de grupo en vez de 9 y una sola caja por fila (la observación «el bloque
  de ES no tiene la caja de PT/EN» de 016 desaparece por construcción).
- Regla crítica, con arnés: ocultar con `hidden` y NUNCA con `disabled`. Un campo disabled no viaja
  en el POST: guardar estando en PT habría borrado las traducciones sin aviso. El arnés cuenta los
  `disabled` en campos y exige 0. Los `name` de las tres lenguas no se tocaron (…[titulo],
  …[titulo_pt], …[titulo_en]), así que el guardado y el front quedan idénticos.
- Trampa que apareció en el modal de reseñas (inc/ui.php, el que usa el administrador desde el
  front): el textarea ES tiene `required` y, al editar en PT, quedaba requerido pero OCULTO. Algunos
  navegadores bloquean el submit con un mensaje invisible («not focusable»). Ahora setLang() pone
  `required` sólo cuando el español está a la vista.
- El formulario de reseñas del CLIENTE mantiene su estructura: no tiene campos por idioma.
- Verificación: CSS 85 OK · 013 arnés PHP 130 OK (+12) · 007 41 OK (+5) · 012 (102 + 18) · 010
  (99 + 38) · 011 18 · 008 (30 + 17) · 006 54; php -l y node --check limpios.
- [ ] Verificación manual (Fase B del quickstart de 017): guardar ESTANDO EN PT y recargar (las tres
  versiones persisten: es la prueba del `disabled`) y el toolbar que no se mueve al alternar idiomas.

DENSIDAD DEL PANEL Y ESTADO DE LA IA (2026-10-17, feature 018):
- Sobrante al lienzo: la grilla de 016 usaba tres columnas (420 / 400 / 1fr) y la tercera existía
  sólo para que la fila de ajustes llegara al ancho completo con «1 / -1». Medido: a 1440px quedaban
  ~380px VACÍOS a la derecha de la vista previa y a 1600px ~540px, con el lienzo congelado en 400px
  (escala 0,31). Ahora son dos columnas (minmax(320px, 420px) minmax(400px, 1fr)): la preview crece
  con el panel (escala ~0,64 a 1440px) y los ajustes siguen a ancho completo. No hubo que tocar el
  JS: escalarLienzo() ya usa el ancho real del lienzo. El arnés de CSS falla si alguien vuelve a la
  tercera columna vacía.
- Estado de la IA: el mensaje de ÉXITO ya no ocupa la fila; va al title del botón «Traducir» (se lee
  al pasar el mouse) y el renglón queda limpio. Los avisos que importan (no se pudo traducir, el
  servicio cree que ya está en el idioma) siguen visibles en rojo y cortos; el max-width del estado
  bajó de 320px a 200px porque desde 017 el toolbar vive en una columna de 420px. Los textos de los
  mensajes no cambiaron, así que los arneses de 008/011 siguen verdes sin tocarlos.
- Las variantes por idioma NO se pierden al cambiar (lo reportado: «aprieto ES para ver el original
  y pierdo las traducciones de PT/EN»). Causa exacta: setLang() llamaba a limpiarVariantes() en CADA
  cambio de idioma y esa función descartaba la tanda (los TEXTOS nunca se perdían: están en inputs
  con hidden y viajan en el POST; lo que se descartaba eran las opciones 1·2·3 ya generadas). Ahora
  las tandas se guardan por idioma ($row.data('aiBatches'), con tandaDeIdioma() pura y verificada),
  al cambiar se repintan las del idioma activo, aplicarVariante() usa la del idioma activo y
  recuerda qué número quedó puesto en cada uno. limpiarVariantes() se retiró.
- Verificación: CSS 86 OK (+1) · 011 28 OK (+10) · 013 (130 + 17) · 012 (102 + 18) · 010 (99 + 38) ·
  008 (30 + 17) · 007 41 · 006 54; php -l y node --check limpios.
- [ ] Verificación manual (Fase B del quickstart de 018): preview más grande y sin espacio muerto ·
  sin mensaje verde permanente (y aviso rojo cuando algo falla) · las variantes que sobreviven a ir
  y volver de ES, cada idioma con las suyas.
- [x] Fase 2 (LIENZO VIVO, misma fecha): el banner REAL dentro de la fila del panel. El
  renderer compartido acepta 'mu' => true, que marca los 10 nodos con data-mu y emite siempre
  eyebrow/CTA secundario/badge (para poder editarlos); el JS los repinta desde el formulario en
  cada tecla, con el respaldo al español y las mismas condiciones de visibilidad del front. Sin
  requests y sin generar HTML en JS. El panel encola el componente hero-promo.css (y el handle
  'generate-google-fonts' del padre) y su CSS declara los tokens del sistema de diseño y las 3
  reglas de botón con alcance al lienzo, porque style.css (mu-base) NO se carga en el admin.
- Fidelidad del lienzo sin iframe (medido y resuelto): el front usa 4vw (ancho de la VENTANA) y
  un @media de 968px; el lienzo usa container-type: inline-size + 4cqw y las reglas móviles
  replicadas como @container, para que respondan al ancho del LIENZO. El ancho real (1280/390)
  se escala con transform para encajar en la columna; escalaCanvas() es pura y tiene arnés.
- Al redimensionar la ventana el lienzo se reencaja; al cambiar de idioma y al tildar el badge
  se repinta. El clic en cualquier nodo lleva (con scroll y resaltado) al campo real del
  formulario, y los CTA nunca navegan desde el editor.
- - Verificación automática de 013: arnés PHP 63 OK (incluye la Fase 0 y el lienzo en la fila) +
  arnés JS 9 OK (escalaCanvas). Los arneses 010 y 012 necesitaron un `require` del repositorio
  real de iconos (inc/icons.php): el lienzo llama mu_get_icon() al renderizar la fila.
- ⚠️ CORRECCIÓN de un hallazgo anterior (era mío y estaba mal): .mu-btn, .mu-btn-primary y
  .mu-btn-outline SÍ están definidos para el front en style.css (:root y líneas 101-112). El
  problema real no es que falte el estilo en el front, sino que el ADMIN no carga style.css
  (mu-base se encola en wp_enqueue_scripts): por eso el lienzo declara los tokens y esos tres
  botones con alcance propio —deuda declarada: si cambian en style.css, cambian acá—.
- [ ] Verificación manual del LIENZO en el panel (Fase B del quickstart de 013): alcance y
  encaje, tipografía (la fuente display la aporta el padre: si no está en el admin, degrada a la
  de reemplazo), sincronización en vivo, modo móvil y clic-para-foco.

HUECOS Y DECISIONES ABIERTAS (2026-10-16, feature 004-country-scope):
- [x] RESUELTO 2026-10-16 — Sitemap de Rank Math: el hook es `rank_math/sitemap/entry` y
  su PRIMER ARGUMENTO ES UN ARRAY (`[ 'loc' => … ]`), no un string. Devolver `[]` excluye la
  entrada (el plugin hace `continue` con un array vacío). Contrato verificado en el código
  de la versión instalada: seo-by-rank-math 1.0.280,
  includes/modules/sitemap/providers/class-post-type.php:222 (la misma línea del sitemap HTML,
  html-sitemap/class-posts.php:215, se excluye con `empty( $url['loc'] )`).
  · TRAMPA MEDIDA (y lección): la primera versión del callback llevaba una guarda
    `is_string( $url )` —prudente en apariencia— que devolvía la entrada sin tocar y
    desactivaba la exclusión en SILENCIO. Medido en producción: /envios/ seguía en
    page-sitemap.xml. Una lectura defensiva no puede ser más estricta que el contrato real
    del argumento.
  · Verificación: curl.exe -s https://mexico.muyunicos.com/page-sitemap.xml |
    Select-String 'envios'  → esperado: sin resultados. Y en el dominio raíz la URL debe
    SEGUIR listada (la exclusión es por host).
  · SEGUNDA TRAMPA (medida 2026-10-16, después del fix del callback): el código quedó
    correcto en los 9 hosts y aun así mexico seguía listando /envios/. Causa: la CACHÉ DE
    SITEMAP de Rank Math, que es un archivo XML por host —
    wp-content/uploads/rank-math/rank_math_<md5("page_1_" + home_url())>.xml (física y
    públicamente accesible; el archivo de mexico, creado a las 22:27, se sirvió con la copia
    vieja mientras los de co/br/cr, escritos a las 22:32, ya salían sin la ruta). Es el
    artefacto de despliegue, no un defecto del tema: la caché no se invalida al desplegar.
    Arreglo (cualquiera de los tres, en wp-admin):
      1. Rank Math → Sitemap Settings → guardar los ajustes sin cambiar nada (dispara
         rank_math/settings/after_save → Cache::invalidate_storage()).
      2. Editar y actualizar cualquier página (un guardado de post invalida la caché del tipo).
      3. Borrar la carpeta wp-content/uploads/rank-math/ desde el Administrador de archivos
         de hPanel (se regenera sola).
    Con cualquier ruta nueva del registro aplica lo mismo: §6, "AL AGREGAR O CAMBIAR UNA RUTA".
- [ ] Candidatas al registro de alcance por país, sin decidir: /reembolso_devoluciones/
  ("Botón de arrepentimiento", figura legal argentina) y cualquier ruta que se agregue a
  futuro. El procedimiento de alta está en
  specs/004-country-scope/contracts/country-scope.md, sección "Agregar una ruta nueva".
- [ ] Redirects de la tienda que pierden el prefijo de idioma (medido 2026-10-16: /pt/finalizar-compra/
  con el carrito vacío responde 302 a /carrito/ sin prefijo). Es el mismo patrón que el login social
  (corregido en la feature 005) pero el redirect lo genera WooCommerce, no el tema: quedó fuera de
  alcance declarado (FR-014 de specs/005-login-language-redirect/spec.md). Tratar aparte.
- [ ] Variantes de idioma del dominio raíz (/pt/envios/, /en/envios/): siguen publicándose
  porque el país es Argentina. Restringirlas exige la dimensión idioma (filtros de
  wpLingua), que no se verificó. Decisión abierta, no deuda técnica.
- [ ] Verificación en producción de la feature 004-country-scope: Fase B de
  specs/004-country-scope/quickstart.md (matriz de curl por host, pie de página, sitemap,
  no-regresión). No es ejecutable en local. Sin esa fase, la feature se declara NO
  VERIFICADA.

════════════════════════════════════════════════════════════════

- [ ] digital-restriction.php / cart.php: Implementar validación `woocommerce_check_cart_items` para bloquear checkout de productos físicos si el país cambia. ("Este artículo no está disponible en tu ubicación actual").
- [ ] checkout.js: libphonenumber-js desde CDN unpkg.com — evaluar auto-host local.
- [ ] orders-workflow.php: bulk actions Legacy → migrar definitivamente a hooks de HPOS (woocommerce_order_list_table_bulk_actions).
- [ ] digital-restriction.php: N+1 en display_digital_price_in_catalog — evaluar get_post_meta() directo.
- [ ] coming-soon.css: archivo deprecado pero presente. Eliminar tras confirmar inactividad.
- [x] ui.php: mu_testimonios_section() — el wp_remote_get a Google Places salió del request de admin: la importación vive en mu_reviews_import_from_google() y corre por tarea programada semanal (mu_reviews_weekly_import) además del enlace manual ?force_reviews=1. (2026-10-16)
- [ ] Arnés de la feature 005 (login/redirect por idioma): NO está versionado (vivía en %TEMP% y se perdió con la limpieza del temporal). Reconstruirlo con el patrón vigente —módulo real + entorno simulado, versionado en `specs/005-login-language-redirect/harness/`— y actualizar su quickstart, que hoy lo declara como deuda.
- [ ] js/admin-hero-banners.js: el arnés (specs/008-banners-pildoras-ia/harness/banners-js.js) cubre las funciones puras (clasificarCampos/mensajeTraduccion); el manejo de jQuery (píldoras, puntos, guardado, botón Traducir) sigue siendo verificación manual. Extraer más lógica a funciones puras si vuelve a fallar algo de esa capa.
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
- PROHIBIDO desplegar a producción: el deploy lo dispara Hostinger desde su
  función Git al recibir el push (§1) y ninguna tarea de IA termina con un paso
  de despliegue (constitución, "Regla de despliegue"). Tampoco se sube nada a mano
  por FTP/Administrador de archivos: los cambios llegan al servidor solo por el
  repositorio, para que no existan ediciones fuera del control de versiones.
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
