<!--
SYNC IMPACT REPORT — material temporal de revisión humana.
Eliminar este bloque antes de commitear la constitución enmendada.

HISTORIAL DE VERSIONES DE ESTE REPORTE
  v2.0.0 (previo): la v1.1.0 afirmaba hechos que pasaron a ser falsos.
    - El Pass/Fail del principio IV invocaba `cf-cache-status: HIT`, cabecera del
      proveedor de CDN retirado. Con la CDN vigente esa cabecera no existe: el
      criterio de verificación era inejecutable.
    - La premisa del principio VI nombraba al proveedor de CDN como único CDN.
      La CDN cambió a Hostinger por subdominio, de modo que la razón del
      principio cambió de raíz.
    Ese bump fue MAJOR por invalidar reglas que la constitución daba por
    ciertas, no por añadir materia.

  v2.1.0 (actual): reforma de una regla que el proyecto no podía cumplir.
    La v2.0.0 prohibía toda lógica de negocio ejecutada fuera del tema. La
    auditoría del código externo mostró que existe código legítimo que debe
    vivir fuera y que el proyecto mantiene. Una prohibición incumplible entrena
    al revisor a ignorar la norma, así que se reformula: lo que se prohíbe no es
    el código externo, sino el código externo NO DECLARADO.

Principio reformulado (1):
  - X. Perímetro de Seguridad y Plugins: la prohibición total pasa a requisito de
    declaración. El código externo declarado es una excepción acotada que exige
    inventario, ausencia de colisión de nombres y fuente identificable.
  - X. Pass/Fail: se añade el criterio de que todo el código ejecutado fuera del
    tema figure en el inventario del guide.
  - X. Rationale: se documenta por qué una prohibición incumplible es peor que
    ninguna, y por qué la regla útil es prohibir lo oculto.

Secciones eliminadas: ninguna
Principios eliminados: ninguno

Cambios de infraestructura registrados por el mantenedor. NO son norma: van al
guide §2, y el guide está pendiente de actualizar.
  - LiteSpeed Memcached (LSMCD) activado como object cache.
  - CDN de Hostinger por subdominio con nivel de seguridad alto; el proveedor
    anterior ya no se usa. Bloqueo de tráfico por país en el edge. TLS 1.3.
  - Inventario de 33 plugins conviviendo. El principio X delimita qué es
    territorio del plugin y qué del tema.

Placeholders diferidos (TODOs): ninguno

Notas:
  - Ratified 2026-10-01, la fecha de adopción inicial, se conserva.
  - Principios con Pass/Fail binario: 10 de 10, sin cambios en este bump.
  - El documento es agnóstico del proveedor de CDN: un cambio de
    infraestructura futuro ya no obliga a enmendarlo.
-->

# Muy Únicos Constitution

Norma de desarrollo del tema hijo GeneratePress de muyunicos.com. Este documento
manda sobre cualquier otra práctica del repositorio: los principios son
normativos y verificables; el detalle operativo que cambia con cada versión
vive en `MIGRATION-GUIDE.md`.

## Project Overview

muyunicos.com es una tienda online de productos personalizados —etiquetas
escolares y addons de nombre y de etiquetas— construida sobre WordPress +
WooCommerce como **tema hijo de GeneratePress**, con arquitectura monolítica
modular: `functions.php` queda reducido al enqueue central y al cargador de
módulos, mientras la lógica de negocio vive en módulos PHP de `inc/`, con CSS y
JS modulares de carga condicional por página.

**Modelo comercial híbrido**: productos digitales de entrega inmediata
disponibles globalmente, y productos físicos (stickers, outlet, gaming)
restringidos a países con subdominio propio. Opera **N zonas de país**, cada una
con su subdominio, con precios por país **sin impuestos incluidos**: el impuesto
se calcula por dirección de envío. El detalle de zonas, logística y pasarelas
de pago se registra en `MIGRATION-GUIDE.md` §3.

**Restricción dominante**: **producción es el único entorno**. No hay staging,
no hay integración continua y no hay suite de pruebas automatizadas. El
despliegue es manual y el respaldo diario del hosting es la única red de
seguridad. Todos los principios de este documento derivan de esa realidad: el
diseño de una funcionalidad se valida primero contra caché, encapsulamiento y
reversibilidad, nunca contra elegancia.

**Equipo**: un único mantenedor asistido por agentes de IA bajo Spec Kit. La
documentación es parte del producto, no un accesorio: lo que no está escrito, no
existe para el próximo agente que abra el repositorio.

## Alcance y Fronteras

**Dentro del alcance**: `functions.php`, `inc/`, `css/`, `js/`, `templates/` y
`style.css`, es decir el tema hijo completo.

**Fuera del alcance — prohibido tocar desde este repositorio**:

- El tema parent **GeneratePress**. Toda personalización vive en el hijo; una
  corrección que exija editar el parent se resuelve con un override en el hijo,
  nunca editando `wp-content/themes/generatepress/`.
- Los **plugins** (WooCommerce, Jetpack, LiteSpeed Cache, WP Mail SMTP, Mercado
  Pago, etc.). Si un plugin es la causa, la respuesta es configuración o un
  override del hijo, nunca una edición del plugin.
- El **servidor**, el DNS, la configuración de Hostinger (CDN por subdominio,
  bloqueo de tráfico por país, TLS) y el object cache. Se documentan en
  `MIGRATION-GUIDE.md`; se cambian desde el panel, no desde un commit.
- El **contenido editorial** (productos, páginas, entradas, medios).

**Regla de despliegue**: este repositorio **no despliega**. El agente produce
código y documentación; el despliegue es una operación manual del mantenedor
según §1 del guide. Ninguna tarea de IA termina con un paso de despliegue.

## Core Principles

Cada principio declara sus reglas no negociables, su fundamento y un criterio
**Pass/Fail** binario. Un principio sin criterio verificable es una sugerencia y
no cumple esta constitución.

### I. Modularidad Pragmática (Goldilocks)

El `functions.php` monolítico está **DEPRECADO**: solo conserva el enqueue
central, el cargador `mu_load_module()` y la cabecera del child theme. Toda la
lógica de negocio vive en módulos de `inc/`.

Reglas no negociables:

- Cada funcionalidad reside en su propio `inc/[modulo].php`, cargado con
  `mu_load_module( '[modulo]' )`.
- El orden de carga respeta dependencias y **debe comentarse** cuando la
  dependencia sea real (ej. `jetpack-search-integration` depende de `geo`;
  `hero-banners` debe preceder a `ui`).
- Ajustes de UI menores a ~50 líneas se agrupan en `css/components/global-ui.css`
  y `js/global-ui.js`. Funcionalidad compleja o aislada **debe** ir en archivo
  propio con carga condicional.
- CSS y JS siguen la misma granularidad; `css/components/` queda reservado a
  elementos transversales.
- Las plantillas PHP autocontenidas y livianas van en `templates/`.

**Pass/Fail**
- Pasa: `functions.php` no contiene lógica de negocio; todo módulo nuevo
  aparece en la lista de `mu_load_module()` con su comentario de dependencia;
  su CSS y JS son archivos físicos y no bloques inline.
- Falla: existe lógica de negocio en `functions.php`, o un módulo se carga sin
  registrar, o una funcionalidad nueva incrusta CSS/JS dentro de un `<style>` o
  `<script>` en el marcado.

Rationale: el archivo monolítico dejó de ser mantenible (superó los 15 KB
mezclando enqueue, admin, checkout y SEO) y su carga completa en toda página es
incompatible con el objetivo de caché. La regla "Goldilocks" evita el extremo
opuesto: un archivo por cada detalle trivial.

### II. Carga Condicional Estricta de Assets

Ningún asset se carga globalmente salvo que pertenezca a header, footer o UI
transversal. Todo lo demás se condiciona al contexto de la request.

Reglas no negociables:

- El enqueue se decide con condicionales explícitos: `is_front_page()`,
  `is_user_logged_in()`, `mu_wc_is_shop()`, `mu_wc_is_product_category()`,
  `mu_wc_is_product()`, `mu_wc_is_cart()` y `mu_wc_is_checkout()`.
- **PROHIBIDO** `wp_add_inline_style()` y `wp_add_inline_script()` para lógica o
  estilos del tema. Excepciones acotadas y documentadas: `login_enqueue_scripts`
  (solo propiedades dinámicas calculadas en PHP) y fragmentos de email, donde el
  `style=""` inline es obligatorio por compatibilidad de clientes de correo.
- Todo el CSS y JS reside en archivos físicos, cacheables. Nunca se inyecta
  lógica en el HTML de salida.
- La versión de los assets se toma de `Version:` en `style.css`; no se inventan
  query strings de cache-busting por archivo.
- Cada asset declara sus dependencias en `wp_enqueue_style()` /
  `wp_enqueue_script()` (ej. `[ 'mu-base' ]`), para que el orden sea explícito.
- Los módulos que encolan por su cuenta (ej. `flexible-price`) **no** se
  registran también en `mu_enqueue_assets()`; la excepción se documenta en un
  comentario junto a la llamada.

**Pass/Fail**
- Pasa: cada `wp_enqueue_*` del tema está dentro de un condicional de contexto
  o corresponde a un componente transversal; `grep -rn "wp_add_inline_"
  inc/ functions.php` no devuelve resultados fuera de las excepciones
  documentadas.
- Falla: un asset se sirve en páginas donde no aplica, o aparece CSS/JS inline
  fuera de las dos excepciones.

Rationale: cada KB de CSS/JS no cacheado se sirve en cada request no cacheable.
La caché solo es rentable si el HTML servido es estable, lo que exige que el set
de assets dependa del contexto y no se contamine con estilos inline.

### III. Código WordPress/WooCommerce Seguro

Reglas no negociables:

- **Toda** función y clase se declara tras una guarda
  `if ( ! function_exists( 'mu_fn' ) ) { }`, y toda constante con
  `if ( ! defined( 'MU_CONST' ) ) { }`. Es obligatorio porque no hay staging: el
  código se despliega y se prueba en producción.
- El tema **no** registra hooks de `shutdown`, **no** usa
  `register_shutdown_function()` y **no** llama a `wp_remote_post()` durante el
  ciclo de vida del pedido. Los rebuilds pesados se delegan **siempre** a cron
  real (`wp_schedule_single_event()`), nunca a un request AJAX ni a `shutdown`.
- **HPOS es obligatorio**: `OrdersTableDataStore` está activo. Queda **PROHIBIDO**
  leer o escribir pedidos en `wp_posts` / `wp_postmeta`. Se usa exclusivamente el
  CRUD de WooCommerce (`$order->get_meta()`, `$order->update_meta_data()`,
  `wc_get_orders()`, etc.).
- **PROHIBIDO** `'limit' => -1` en consultas de frontend. Toda consulta se
  limita y se cachea con un transient.
- Claves de transient con formato `mu_[contexto]_[id]`, invalidadas en el hook del
  evento de cambio de estado que las origina, con guard anti-vacío: un rebuild
  **jamás** sobrescribe un índice bueno con uno vacío.
- Todo output pasa por `esc_html()`, `esc_attr()`, `esc_url()` o `wp_kses_post()`
  según corresponda; toda entrada por `sanitize_text_field()`, `absint()`,
  `wp_unslash()` o el sanizador del dominio.
- Toda operación sensible (AJAX, endpoints `wc_ajax_mu_*`, handlers REST) exige
  verificación de nonce (`wp_verify_nonce()` / `check_ajax_referer()`) y control
  de permisos (`current_user_can()`).
- Los hooks que dependan del orden llevan prioridad explícita (ej.
  `template_redirect` p1, `pre_get_posts` p60) con comentario explicando por qué.
- **Cero datos personales o credenciales en logs y en respuestas de AJAX**. No se
  registran emails, teléfonos, direcciones, IPs de cliente ni tokens. Un dato
  propio de debugging (subdominio, User-Agent truncado, contadores) sí es válido.
- Toda excepción a este principio se registra en el registro de excepciones del
  guide §8 con alcance, justificación y fecha de expiración.

**Pass/Fail**
- Pasa: cada handler AJAX encontrado con `grep -rn "wp_ajax_" inc/` verifica
  nonce y permisos; ninguna función o clase nueva queda sin guarda; el linter
  de WordPress no reporta escapes ni nonces faltantes en los archivos tocados.
- Falla: un handler AJAX accesible sin nonce, una consulta a `wp_postmeta` para
  leer datos de un pedido, o una cadena de log con email, teléfono o token.

Rationale: sin staging, un fallo se paga directamente en producción, sobre
checkout y pedidos reales con pagos. Las guardas, el aislamiento de dependencias
y la verificación de permisos son la única red de seguridad disponible.

### IV. Corrección Comercial

Reglas no negociables:

- Precio final, impuesto, descuento, disponibilidad y pasarela se calculan y
  validan **en el servidor**. El front presenta; nunca decide.
- **PROHIBIDO** una regla de precio, descuento o disponibilidad que exista solo en
  JavaScript. Toda regla del front tiene su equivalente servidor, y el servidor
  es la autoridad.
- Los precios mostrados son **sin impuestos incluidos** y el impuesto se calcula
  por dirección de envío. Ninguna lógica presume que el precio mostrado lo
  incluye.
- `cart`, `checkout` y `account` **nunca** se sirven desde caché pública; el
  bypass de caché CDN para contenido dinámico es la opción por defecto.
- Los estados propios del negocio (ej. producción) se registran como estados de
  pedido válidos, nunca como columnas nuevas ni como campos sueltos en postmeta.
- La descarga de archivos de un pedido se autoriza en servidor contra el pedido
  del usuario; un enlace de descarga no es un permiso.
- Los textos de aviso visibles al comprador (ej. el bloqueo de artículos físicos
  fuera de su país) son parte de la especificación funcional: su texto se acuerda
  antes de implementar y no se altera sin validación.

**Pass/Fail**
- Pasa: el total mostrado para un carrito de prueba, con impuesto por dirección de
  envío, coincide exactamente con el total que calcula WooCommerce para el mismo
  `cart_item_key`; y `curl -sI` sobre cart, checkout y account **no** devuelve
  ninguna cabecera de caché pública del CDN activo.
- Falla: cualquier discrepancia de importe entre lo que ve el comprador y lo que
  cobra el servidor, o una regla de precio que exista solo en el front.

Rationale: es el único dominio donde un error cuesta dinero real y donde un error
de caché expone datos de otro comprador. El servidor es la única autoridad fiable
cuando conviven caché de página, varios países y varias pasarelas de pago.

### V. SEO e i18n Multi-País

El subdominio, el `canonical` y el `hreflang` son **invariantes**: definen qué
página es cuál para cada país. No son un extra de SEO, son la estructura del
sitio.

Reglas no negociables:

- Toda ruta, página o plantilla nueva **debe declarar su contrato SEO** en la
  spec: canonical propio, `hreflang` hacia todos los subdominios equivalentes y
  comportamiento ante idioma ausente.
- El canonical de una página en un subdominio **apunta a sí mismo**, nunca al
  dominio raíz. Una fuga de canonical entre subdominios duplica la página en
  buscadores.
- `hreflang` es simétrico: si A declara B, B declara A. Todo conjunto nuevo se
  verifica subdominio por subdominio.
- Títulos y meta se generan por país desde el servidor; **PROHIBIDO** hardcodear
  un título o una meta estática en una ruta multi-país.
- Los enlaces internos y los resultados de búsqueda apuntan al subdominio actual.
  Cualquier integración de terceros se corrige con un filtro o hook del hijo,
  nunca editando el plugin.
- El cambio de país de un visitante redirige al subdominio equivalente; cambiar
  de país con un producto físico en el carrito dispara el bloqueo del principio
  IV.

**Pass/Fail**
- Pasa: `curl -sI` en cada subdominio afectado devuelve un `canonical` que
  contiene ese mismo subdominio, y el conjunto `hreflang` es simétrico; un
  resultado de búsqueda apunta al subdominio activo.
- Falla: un canonical que apunta al dominio raíz desde un subdominio, un
  `hreflang` sin su recíproco, o una ruta nueva sin contrato SEO declarado.

Rationale: el tráfico de cada país es orgánico y se posiciona por subdominio.
Una fuga de canonical o un `hreflang` asimétrico no se ve en el código: se ve
meses después como tráfico perdido, y no se diagnostica.

### VI. Rendimiento y Caché como Restricción de Diseño

Cualquier funcionalidad se valida primero contra caché y consumo de CPU: una
funcionalidad correcta pero no cacheable se considera un defecto, no un detalle
pendiente.

Reglas no negociables:

- El **object cache** de LiteSpeed (Memcached, `LSMCD`) cachea datos, no páginas.
  El HTML de cada subdominio lo cachea exclusivamente LiteSpeed, separado por
  `Vary: mu_host` vía `mu_litespeed_vary_by_subdomain()`.
- **Una sola CDN activa**, configurada por subdominio. El proveedor concreto
  (y sus cabeceras) es configuración del guide §2, no norma: ninguna regla puede
  depender de un proveedor específico, porque cambiarlo no debe invalidar el
  sistema de reglas. Ninguna funcionalidad puede romper la separación de caché
  entre subdominios.
- **PROHIBIDO** `litespeed_purge_all()` incondicional. Todo rebuild compara el
  índice nuevo contra el almacenado (Hash Gate con `maybe_serialize()`) y solo
  purga si difieren.
- Los índices mantienen copia permanente en `wp_options` con prefijo `_mu_*` para
  sobrevivir a flushes del object cache y a evicciones LRU.
- Los cálculos repetidos se cachean dentro del request: helpers como
  `is_restricted_user()` o `get_cached_digital_product_ids()` se resuelven
  **una sola vez** por request.
- Toda respuesta dependiente del `User-Agent` **nunca** se marca como cacheable
  para humanos, para no envenenar su caché. Ningún `exit` escribe en el object
  cache, porque LiteSpeed persiste Memcached en `shutdown`, que el `exit` salta;
  para contadores se usa archivo plano con `LOCK_EX`.
- No se agregan consultas a rutas calientes de catálogo sin medir su impacto
  sobre el request no cacheado.
- Todo ajuste de caché, JS Delay o regla de CDN se documenta en el guide §2 y §7
  junto con su comando de verificación.

**Pass/Fail**
- Pasa: la segunda request `curl` a una URL cacheada de cada subdominio devuelve
  `x-litespeed-cache: hit`; una edición que no cambia categorías ni etiquetas deja
  el log con la purga **OMITIDA**; un bot recibe el fast-exit mientras el flujo
  humano en la misma URL mantiene su respuesta normal.
- Falla: una purga total disparada sin cambio real, un 404 cacheado servido a un
  humano, o una respuesta dependiente de `User-Agent` marcada como cacheable.

Rationale: el sitio sostiene su carga gracias a la caché sobre todas las zonas.
Una purga total por cada edición de precio regeneraba todas las páginas y saturaba
el hosting, y marcar como cacheable una respuesta de bot envenenaba la caché de
usuarios humanos. Ambos incidentes ya ocurrieron en producción.

### VII. Observabilidad e Incidentes

Lo que no deja rastro no se puede diagnosticar, y en producción el único
diagnóstico disponible es la traza. Cada decisión difícil queda explicada por el
incidente que la motivó.

Reglas no negociables:

- Toda lógica sensible deja traza: tag fijo `[MU-MODULO]` al inicio de la línea,
  con el subdominio y el dato que explica la decisión.
- **Prohibido el PII en logs**: ni emails, ni teléfonos, ni direcciones, ni IPs de
  cliente, ni tokens, ni claves de API. Un identificador de pedido o de carrito sí
  es válido.
- Los logs de alta frecuencia se **rate-limitan** para no degradar el request que
  los escribe; el límite se declara en el propio módulo.
- Los contadores que sobreviven a un `exit` temprano van a archivo plano con
  `LOCK_EX`, nunca al object cache.
- Los contadores y el estado del sistema se exponen en el widget de administración
  del módulo de performance, leyendo de archivo o caché, sin consultas SQL.
- Un incidente que dolió una vez se documenta en el guide §7 con fecha, síntoma,
  causa raíz y mitigación. **Si el incidente cambia una invariante, además se
  escala a principio** en este documento.
- La mitigación de un incidente se acompaña de su comando de verificación, para
  que cualquiera pueda reproducirla.

**Pass/Fail**
- Pasa: `grep "\[MU-MODULO\]" debug.log` devuelve la traza esperada del módulo
  con su rate-limit; ningún log contiene email, teléfono, dirección o token; cada
  incidente del guide §7 tiene comando de verificación.
- Falla: una lógica sensible sin traza, o una traza con dato personal, o un
  incidente documentado sin causa raíz ni verificación.

Rationale: el proyecto no tiene staging ni alertas. El log de depuración y el
widget de administración son el único sistema de observabilidad, y son la razón
por la que los cuatro incidentes ya registrados pudieron diagnosticarse. Un
principio sin incidente documentado detrás es una opinión; con incidente, es ley.

### VIII. Sistema de Diseño y Convenciones de Código (API Exclusiva)

Reglas no negociables:

- CSS: se usan **únicamente** las variables declaradas en `:root` de `style.css`.
  **PROHIBIDO** inventar variables nuevas o hardcodear colores y espacios cuando
  ya existe una variable equivalente. La lista vigente está en el guide §5.
- Iconos: `mu_get_icon( 'nombre' )`. **PROHIBIDO** SVG inline en el tema, salvo
  en las plantillas standalone de `templates/`. Todo SVG nuevo se registra en
  `inc/icons.php`.
- CSS: nomenclatura BEM con prefijo `.mu-[componente]__[elemento]--[modificador]`.
  Todo override sobre GeneratePress se anota con `/* override GP: [motivo] */`.
- JS: IIFE + `'use strict'` + inicialización en `DOMContentLoaded`. Cero jQuery
  salvo dependencia legacy de WooCommerce ineludible.
- JS: los datos de PHP a JS viajan por `wp_localize_script()`, nunca por variables
  globales escritas a mano ni por `echo` dentro del HTML.
- JS/CSS de animación: **PROHIBIDO** `style.transform` inline. Las animaciones
  se resuelven con clases CSS para que se ejecuten en la compositora (GPU).
- Accesibilidad: todo elemento interactivo necesita `:focus-visible` visible y
  estados de hover y foco definidos; los iconos solos requieren etiqueta
  accesible o `aria-hidden` según el caso.
- Layout: mobile-first y tipografía fluida con `clamp()`, sin anchos fijos que
  rompan en los breakpoints de WooCommerce.

**Pass/Fail**
- Pasa: `grep` sobre los archivos CSS tocados no encuentra colores literales
  donde existe una variable equivalente; ningún SVG inline fuera de `templates/`;
  los overrides de GeneratePress llevan su comentario de motivo; los JS nuevos
  usan IIFE y `wp_localize_script()` para sus datos.
- Falla: un color literal duplicando una variable existente, un SVG inline en el
  tema, o un dato de PHP pasado a JS por `echo`.

Rationale: la dispersión de estilos y el uso de valores literales rompieron la
coherencia visual y encarecieron cada ajuste de marca. Centralizar los tokens en
un único archivo convierte un cambio de identidad en una edición y no en una
búsqueda.

### IX. Verificación antes de Desplegar

**Declaración de hueco, explícita y honesta**: este proyecto **no tiene suite de
pruebas automatizadas, ni integración continua, ni composer, ni package.json**.
No se van a añadir en esta constitución. En su lugar, la verificación se hace con
las herramientas que existen: el intérprete de PHP, `curl` contra producción y
revisión del caso de fallo. Este principio define qué se exige dado ese hueco.

Reglas no negociables:

- **Todo archivo tocado pasa `php -l`** sin errores antes de cualquier commit.
- **Ninguna funcionalidad se da por terminada sin verificación reproducible**: el
  PR incluye los comandos `curl` ejecutados y su salida relevante, no una
  descripción del resultado esperado.
- Toda lógica pura nueva (cálculo de precio, filtro de catálogo, transformación de
  índice) llega acompañada de su caso de verificación, ejecutado contra una
  instalación local equivalente.
- El trabajo de riesgo alto (checkout, pagos, precios, seguridad, caché,
  restricciones) **exige** que el caso de fallo se describa y se verifique antes
  de desplegar.
- Sin `var_dump`, `console.log`, `print_r` ni claves de API residuales.
- La verificación de caché, del fast-exit de bots y de la separación por
  subdominio usa los comandos `curl` documentados en el guide §7.
- Si una funcionalidad no puede verificarse de forma reproducible, se declara
  explícitamente como no verificada en el PR; nunca se presenta como verificada.

**Pass/Fail**
- Pasa: el PR incluye la salida de `php -l` de cada archivo tocado, los `curl` de
  verificación con su respuesta, y la descripción del caso de fallo cuando aplica.
- Falla: "funciona en local" sin verificación en el subdominio afectado, o un
  cambio de riesgo alto sin caso de fallo descrito.

Rationale: no hay staging que absorba un error. La verificación manual y
reproducible no es el ideal, es el único mecanismo disponible; convertirla en un
requisito explícito evita que la ausencia de tests se convierta en la excusa
para no verificar nada.

### X. Perímetro de Seguridad y Plugins

El sitio convive con una pila de plugins que cubre perímetro, autenticación,
antispam, SEO y caché. El tema **no** reimplementa ninguna de esas capas, y todo
su PHP debe seguir siendo localizable y auditable.

Reglas no negociables:

- El tema **no implementa** autenticación propia, firewall, antispam ni SEO
  técnico. Eso es territorio de los plugins de seguridad y SEO; el tema solo
  aporta overrides por hook y filtro cuando un comportamiento debe adaptarse al
  diseño.
- Todo el PHP de negocio **vive en el tema hijo** o, cuando debe vivir fuera,
  **está declarado en el guide con su propósito, su ubicación y su fuente**. El
  código no declarado equivale a código inexistente para la revisión: no se puede
  auditar lo que no se sabe que existe.
- El **código externo declarado** es una excepción acotada, no un hueco. Exige
  tres cosas: figurar en el inventario del guide, no compartir nombres con el tema
  salvo declaración explícita, y una fuente identificable a la que se pueda
  volver.
- Los plugins de pago (Mercado Pago, PayPal, pasarela por país) y de precio
  dinámico son la autoridad del cobro y del precio final; el tema consume sus
  APIs, nunca las reescribe ni las parchea.
- El bypass de caché del contenido dinámico (cart, checkout, account) se emite
  con cabeceras HTTP estándar (`Cache-Control`, `Pragma`, `Expires`), **nunca**
  atadas a un proveedor de CDN específico. Cambiar de CDN no debe requerir tocar
  el tema.
- La configuración de seguridad del perímetro (TLS, bloqueo por país, reglas de
  CDN) pertenece al panel del hosting y al guide §2, no al código.
- El antispam es del plugin correspondiente; el tema no añade un segundo filtro de
  spam propio que duplique la lógica.

**Pass/Fail**
- Pasa: `grep` sobre `inc/` y `functions.php` no encuentra autenticación, WAF,
  antispam ni lógica de SEO técnica propia; ningún archivo del tema referencia
  cabeceras o APIs de un proveedor de CDN con nombre propio; el bypass de
  contenido dinámico solo emite cabeceras estándar; y todo el código que el sitio
  ejecuta fuera del tema figura en el inventario del guide con su fuente.
- Falla: hay lógica fuera del tema que no está en el inventario, o el tema
  reimplementa autenticación/antispam/SEO, o una regla de caché nombra un
  proveedor de CDN concreto.

Rationale: con más de treinta plugins conviviendo, el riesgo no es el plugin: es
la lógica que se esconde entre ellos y queda fuera de todo control de versión.
Una prohibición total del código externo resultó ser una regla que el proyecto no
puede cumplir, y una regla incumplible es peor que ninguna: entrena al revisor a
ignorar la norma. La regla útil es la contraria: no se tolera el código
**oculto**. Un fragmento declarado se audita; uno no declarado no existe para el
siguiente agente. Y atar una regla de caché a un proveedor significa que cambiar
de CDN rompe la gobernanza justo cuando la infraestructura ya cambió.

## Mapa de Documentación

Este proyecto tiene **dos** documentos de referencia y ninguno más. La
separación es por naturaleza del contenido, no por preferencia:

| Documento | Responde a | Contiene | Cambia |
|---|---|---|---|
| Esta constitución | **Qué es verdad aquí** | Principios normativos, alcance, Pass/Fail, gobernanza | Rara vez, y solo por enmienda |
| `MIGRATION-GUIDE.md` | **Cómo está construido hoy** | Versiones, configuración de servidor, árbol de módulos, incidentes, deuda técnica | Con cada módulo y cada incidente |
| La spec de la feature | **Qué hay que construir esta vez** | Alcance y criterios de la funcionalidad puntual | En cada `/speckit-specify` |

Reglas de este mapa:

- Un principio **nunca** se duplica en el guide: el guide lo referencia. Un
  valor de configuración **nunca** entra en la constitución: el guide lo posee.
- Ante conflicto, esta constitución manda; el guide se corrige.
- El guide es la lectura obligatoria antes de tocar arquitectura; su directiva
  para agentes IA se respeta.

## Restricciones Técnicas

- El **stack soportado** (versiones de PHP, base de datos, servidor de caché, CDN
  y tema parent) se registra en el guide §2. Cualquier cambio de stack exige
  justificación explícita en la spec **antes** de implementarse; no se asume.
- El **perímetro de la red** también vive en el guide §2: CDN activa por
  subdominio con su nivel de seguridad, bloqueo de tráfico por país, versión de
  TLS y el object cache (`LSMCD`). Son ajustes de panel, no de código: cambiarlos
  no requiere una enmienda de esta constitución, pero sí actualizar el guide.
- El **modelo comercial por zonas** (países, precios, fiscalidad, logística,
  pasarelas) se registra en el guide §3 y es una restricción técnica de primer
  orden: un cambio de checkout o catálogo no puede romper el bloqueo de artículos
  físicos ni la lógica de precios por país.
- **No existe entorno de staging**: producción es el único entorno. De ahí la
  obligatoriedad de guardas, fallbacks y código defensivo.
- El despliegue es **manual**, por FTP o Administrador de archivos, a
  `/generatepress-child/`. El respaldo automático diario del hosting es la red de
  seguridad principal y **no** es un plan de rollback.
- Secrets, API keys y tokens viven en la configuración del servidor o de
  WordPress, **nunca** en el repositorio.
- El correo electrónico transaccional y la analítica son los flujos que notifican
  y miden; su implementación respeta el principio II (archivos, sin inline fuera
  de email) y el principio III (sin PII en logs).

## Flujo de Desarrollo

- Toda funcionalidad pasa por Spec Kit: `/speckit-specify` → `/speckit-plan` →
  `/speckit-tasks` → `/speckit-implement`. Esta constitución es de lectura
  obligatoria en la fase de plan.
- Ramas semánticas obligatorias: `perf/`, `refactor/`, `fix/`, `feat/`.
- `MIGRATION-GUIDE.md` **debe** actualizarse en el mismo PR, o antes del
  despliegue, cuando cambie arquitectura, caché, hooks, precios o cualquier
  decisión operativa. Es documentación subordinada a esta constitución, no su
  sustituta.
- Los **criterios de verificación** son los del principio IX; los **comandos
  concretos** de cada verificación están en el guide §7 y no se reescriben aquí.
- **Rollback**: antes de desplegar cualquier cambio que toque caché, checkout,
  precios o restricciones, se define cómo se revierte (qué archivos, en qué
  orden, y cómo se purga la caché). Un cambio sin reversibilidad definida no se
  despliega.
- La deuda técnica conocida vive en el registro del guide §8 y no se resuelve en
  silencio: o se cierra, o se mantiene con su justificación y su fecha.

## Governance

- Esta constitución prevalece sobre cualquier otra práctica, guía o preferencia
  del repositorio. Ante conflicto, manda este documento y se enmenda en vez de
  esquivarlo.
- **Procedimiento de enmienda**: toda modificación se hace invocando
  `/speckit-constitution`. La enmienda incluye la razón del cambio, los
  principios afectados y su impacto sobre las funcionalidades existentes.
  `.specify/memory/constitution.md` no se edita a mano.
- **Política de versionado** (semántica):
  - MAJOR: eliminación o redefinición incompatible de un principio o sección.
  - MINOR: principio o sección nueva, o ampliación material de la guía existente.
  - PATCH: aclaraciones, redacción, correcciones de typos, refinamientos no
    semánticos.
- **Revisión de cumplimiento**: en cada PR o cambio de arquitectura, la revisión
  verifica el cumplimiento de los principios I a X y sus Pass/Fail. La
  complejidad o responsabilidad fuera de estos principios requiere justificación
  explícita por escrito, en la spec o en el PR; en su ausencia, el cambio se
  rechaza.
- **Registro de excepciones**: toda excepción a un principio se registra en el
  guide §8 con identificador, principio afectado, alcance, justificación, fecha de
  aprobación y fecha de expiración. Una excepción sin fecha de expiración se
  revisa en la siguiente enmienda. Una excepción registrada no es una excepción
  silenciosa.
- El detalle operativo de estas reglas se mantiene en `MIGRATION-GUIDE.md` y se
  revisa junto con esta constitución.

**Version**: 2.1.0 | **Ratified**: 2026-10-01 | **Last Amended**: 2026-10-01
