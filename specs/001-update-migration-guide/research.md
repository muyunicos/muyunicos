# Phase 0 Research: Actualizar MIGRATION-GUIDE.md

**Feature**: 001-update-migration-guide | **Date**: 2026-10-02

Toda la investigación de este documento se hizo **verificando el código**, no
asumiendo. Cuando la spec y el código discrepan, gana el código y la
discrepancia se documenta aquí.

---

## 1. Plugins acoplados al tema — verificado

La spec (FR-007) pedía listar nueve plugins. Se verificó cada uno buscando
hooks, filtros, clases o llamadas en `inc/` y `functions.php`.

| Plugin | Punto de acoplamiento verificado | Archivo:línea |
|---|---|---|
| **Rank Math** | `add_filter( 'rank_math_description', 'mu_localize_meta_description', 10 )` y `add_filter( 'rank_math_canonical', 'mu_fix_canonical_url', 10 )` | `inc/seo-hreflang.php:146,173` |
| **Jetpack Search** | `add_filter( 'jetpack_search_instant_search_options', 'mu_jetpack_search_fix_home_url', 10 )` y `..._maybe_hide_prices', 15` | `inc/jetpack-search-integration.php:93,132` |
| **Hostinger Tools** | `get_option( 'hostinger_tools' )` lee `maintenance_mode` y `bypass_code` | `inc/coming-soon.php:56` |
| **wpLingua** | Consume sus clases `.wplng-switcher` / `.wplng-close-btn`; inyecta `body_class` `mu-wplng-hide` | `inc/ui.php:39`, `css/components/global-ui.css:13`, `js/global-ui.js:121` |
| **WooCommerce Price Based on Country** | `class_exists( 'WC_Product_Price_Based_Country' )` como guarda de no interferencia | `inc/geo.php:395,438` |
| **Nextend Social Login** | `shortcode_exists( 'nextend_social_login' )` y URLs `?loginSocial=google|facebook` | `inc/checkout.php:372`, `inc/auth-modal.php:106` |
| **LiteSpeed Cache** | Filtros `litespeed_vary`, `litespeed_optimize_js_excludes` (ya eliminado) y `do_action( 'litespeed_purge_all' )` | `inc/compat-litespeed.php:66`, `inc/navigation-chips.php:280` |

### Corrección a la spec: PayPal y Mercado Pago NO están acoplados

**Decisión**: documentar la corrección (opción A del mantenedor).

| Plugin | Resultado de la búsqueda | Destino correcto |
|---|---|---|
| **PayPal** | **Cero referencias** en el tema. Ningún hook, filtro, clase ni string | §3 "Pagos, Analítica y Correos" como pasarela configurada |
| **Mercado Pago** | **Una sola aparición**, y está dentro de un bloque PHPDoc a modo de ejemplo en `inc/checkout.php:425` (`$rules['MX'] = [ 'allow' => [ 'woo-mercado-pago-custom' ] ]`) | §3 como pasarela configurada |

Razón de fondo: el FR-008 de la spec establece que el guide **no debe listar
plugins sin acoplamiento** "para no sugerir una integración que no existe".
Listar PayPal como acoplado sería exactamente el error que el FR evita. Ambos
plugins son reales y están instalados; lo que no existe es código del tema que
los consuma. Son dos hechos distintos y el guide debe separarlos.

**Nota para el revisor**: que PayPal no aparezca en el código no es un defecto.
El checkout delega la autorización del cobro en WooCommerce y en el propio
plugin. El tema solo interviene en el filtro `mu_payment_gateways_country_rules`,
que opera sobre el array de pasarelas sin conocer las clases de cada plugin.

---

## 2. Renombre del módulo de caché

**Decisión**: renombrar `inc/cloudflare-optimization.php`.

**Rationale**: el nombre del módulo es la primera señal que un agente lee para
entender qué hace. `cloudflare-optimization` describe un proveedor que no se
usa. La función que contiene, `mu_cloudflare_bypass_cache()`, ya es agnóstica:
emite `Cache-Control: no-cache, no-store, must-revalidate, max-age=0`, `Pragma`
y `Expires` para cart, checkout y account. Esas cabeceras son estándar y
funcionan con cualquier CDN.

**Alternativas consideradas**:

| Alternativa | Por qué se descartó |
|---|---|
| Mantener el nombre y solo corregir el comentario | El comentario ya se corrigió en el trabajo de caché. El nombre sigue mintiendo, y es lo que un agente lee primero |
| Eliminar el módulo y mover la función a `compat-litespeed.php` | Sería más coherente temáticamente (ambos son de caché), pero mezcla un módulo de "bypass de CDN" con uno de "compatibilidad de caché" y agrupa responsabilidades distintas. Un módulo por responsabilidad es el Principio I |
| Reemplazar `header()` por `wp_send_headers()` | Fuera de alcance. El código actual funciona y verificado; cambiarlo introduce riesgo en producción sin benefit para esta feature |

**Nombre destino**: `cdn-cache-bypass.php` con la función
`mu_cdn_cache_bypass()`. Descriptivo, agnóstico del proveedor y alineado con
`.mu-` / `mu_` que usa el resto del tema.

**Radio del cambio (verificado)**: 2 referencias en todo el repositorio.
- `functions.php:352` — `mu_load_module( 'cloudflare-optimization' )`
- `MIGRATION-GUIDE.md:141` — entrada del árbol de directorios

---

## 3. Mecanismo real de aislamiento de caché entre subdominios

**Decisión**: documentar la cookie `_lscache_vary` como mecanismo real, y
describir el filtro `litespeed_vary` como refuerzo.

**Rationale (verificado con `curl` contra producción el 2026-10-02)**:

| Host | MD5 del catálogo | Bytes | Coincidencias de físicos |
|---|---|---|---|
| `muyunicos.com` (AR) | `e304868281ed` | 160.987 | 1 (sí hay) |
| `us.muyunicos.com` | `614a3351fb1e` | 154.700 | 0 |
| `es.muyunicos.com` | `2f9e9e4a4321` | 154.938 | 0 |
| `br.muyunicos.com` | `b8b31b9259a9` | 155.001 | 0 |

Hash y tamaño distintos → el aislamiento **funciona**. Los productos físicos
desaparecen en los subdominios por diseño (`digital-restriction.php`), no es un
fallo de caché.

**Mecanismo**: LiteSpeed separa por variante con la cookie `_lscache_vary`, de
dominio `.muyunicos.com`, cuyo valor es un hash derivado del host. El header
`Vary` observado en producción trae **solo** `Accept-Encoding`, por lo que el
filtro `litespeed_vary` del tema probablemente no se está aplicando con el
preajuste vigente.

**Decisión derivada**: **no tocar el filtro**. Se verificó que el aislamiento
funciona; reconfigurar caché en producción sin staging es más riesgoso que
dejarlo. El guide documenta el mecanismo real y advierte que tocar el filtro
sin medir puede reintroducir productos físicos cruzados.

**Alternativa considerada**: forzar el filtro `litespeed_vary` leyendo el
resultado real de `Vary` en cada subdominio. Se descartó porque la doc de
LiteSpeed indica que el filtro requiere que el modo avanzado esté activo, y con
"Caché por defecto" no lo estaría — el cambio probablemente rompería la caché
que hoy funciona.

---

## 4. Código que se ejecuta fuera del tema

**Decisión**: documentar los tres fragmentos por nombre y propósito, sin
publicar su contenido.

**Rationale**: el Principio X (v2.1.0) reformulado exige que el código ejecutado
fuera del tema esté declarado. Un fragmento declarado se audita; uno no
declarado no existe para el siguiente agente. El inventario hace auditable el
código sin moverlo.

| Fragmento | Propósito | Fuente | Riesgo documentado |
|---|---|---|---|
| Filtro de shortcodes | Elimina del contenido los shortcodes no registrados, para que el comprador no vea etiquetas sin renderizar | Code Snippets | Closure sin nombre; no se puede desactivar de forma selectiva |
| Calculadora de Stickers | Shortcode que encola el bundle y expone datos al JS | Code Snippets + repo externo | Declara `mu_sticker_calculator_shortcode()` con el prefijo del tema, sin guarda `function_exists`. Si el tema llegara a declarar el mismo nombre, PHP aborta con error fatal |
| Ajuste de wpLingua | Ajusta la configuración del plugin de traducción | Code Snippets | Declara `define()` y funciones `wplng_bypass_*` sin guardas |

**Riesgo de colisión de nombres** (documentado en spec como caso límite): el
fragmento de la Calculadora y el repositorio externo usan el prefijo `mu_`, igual
que el tema. Ninguno de los dos lados tiene guardas. Si ambos se cargan a la
vez, PHP aborta.

**Fuera de alcance (decidido por el mantenedor)**: migrar, reescribir o retirar
estos fragmentos. Documentarlos no autoriza su traslado.

---

## 5. Incidente `wp.i18n is not defined` — causa raíz real

**Decisión**: registrar el incidente en §7 con su causa raíz y su aprendizaje.

**Rationale**: el error visible era `gtag-events.js`, y el filtro existente
excluía precisamente `gtag-events.js` del JS Delay. La causa real era otra: el
JS Delay rompía el orden de dependencias de los paquetes `@wordpress/*`, que se
servían como blobs `data:text/javascript;base64` y llegaban **después** de sus
consumidores. Se excluían los consumidores, no el proveedor.

Decodificar los payloads del error confirmó la hipótesis: eran
`wp.i18n.setLocaleData` y el bloque de Jetpack Search que invoca
`wp.i18n.setLocaleData`.

**Resolución**: aplicar el preajuste "Caché por defecto", que desactiva el JS
Delay. Verificado: cero errores `wp`/`hooks`/`i18n` en consola, y la caché sigue
respondiendo `hit`.

**Aprendizaje a documentar**: si se reactiva el JS Delay, excluir **primero**
`wp-hooks`, `wp-i18n` y `a18n`. Alternativa a evaluar: activar Guest Mode y
excluir por paquete en lugar de por ruta.

---

## 6. Discrepancias adicionales detectadas

| # | Hallazgo | Acción en esta feature |
|---|---|---|
| 1 | Versión de PHP: el guide declara 8.3.28, producción responde `X-Powered-By: PHP/8.5.4` | Registrar como pendiente de verificar; no se cambia el dato sin confirmación del panel |
| 2 | `Server: hcdn` y `x-hcdn-cache-status` aparecen en las respuestas, confirmando que la CDN de Hostinger es la capa de edge | Ya documentado en §2 del guide |
| 3 | El fast-exit de bots del tema (`mu_litespeed_bot_404_fast_exit()`) ya no se ejecuta en la mayoría de los casos: el edge responde 429 antes de que PHP corra | Documentado en §7 como defensa en dos capas |
| 4 | El bundle de la Calculadora y su fuente viven en un repositorio externo, con comandos `npm run build:js` y `npm run build:css` | Documentar el flujo de compilación (FR-014) |
| 5 | El repositorio de la Calculadora tiene 7 archivos modificados sin commitear, incluido el bundle | Registrar como pendiente; no se modifica en esta feature |

---

## Resumen de decisiones

| # | Decisión | Alternativa descartada |
|---|---|---|
| 1 | PayPal y Mercado Pago van a §3, no al inventario de acoplados | Listarlos como acoplados contradice el FR-008 |
| 2 | Renombrar a `cdn-cache-bypass.php` | Mantener el nombre mintiendo; o fusionar con `compat-litespeed.php` |
| 3 | No tocar el filtro `litespeed_vary` | Forzarlo podría romper el aislamiento que hoy funciona |
| 4 | Documentar el código externo sin publicarlo | Publicar el contenido expone lógica fuera de control de versiones |
| 5 | Registrar el incidente `wp.i18n` con la causa raíz real | Registrar solo la symptomática de `gtag-events.js` |

## NEEDS CLARIFICATION

Ninguna. Todas las decisiones que bloqueaban el alcance se resolvieron con el
mantenedor antes de redactar la spec, y las cinco decisiones técnicas de este
documento están resueltas con evidencia verificada en el código.
