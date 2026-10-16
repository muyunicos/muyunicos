# Research — `004-country-scope`

**Fecha**: 2026-10-16 | **Plan**: [plan.md](./plan.md) | **Spec**: [spec.md](./spec.md)

Cada decisión: qué se evaluó, qué se eligió, con qué evidencia y qué se descartó.

## 1. Dónde vive el alcance por país: wp-admin, plugin, servidor o tema

**Decisión**: en el tema (un registro en código + un guard en `template_redirect`).

**Evidencia**: el sitio es una instalación única servida en 10 hosts: el mismo post ID
20036 responde en `muyunicos.com` y en `mexico.muyunicos.com` (`Link: …/wp/v2/pages/20036`
en las dos respuestas). No hay publicación por host en WordPress. De los plugins del
sitio, WCPBC cambia precios por país y wpLingua cambia idioma: ninguno tiene alcance por
país de una URL.

**Descartado**:
- *`.htaccess` en la raíz*: no está en el repositorio (el deploy de Hostinger baja el repo
  a `/generatepress-child/`; `raiz-tienda/.htaccess` es una copia local ignorada) y la guía
  §1 prohíbe editar el servidor a mano. Un fix ahí no es reproducible.
- *Plugin nuevo de restricción por país*: fuera del perímetro aprobado (principio X) y
  añade una capa que nadie del proyecto mantiene.
- *Duplicar la página por país*: no existe tal cosa en una instalación única.
- *Sólo ocultar el enlace y el contenido con CSS*: deja la URL respondiendo `200` e
  indexándose; el defecto central —una página que dice cosas falsas para ese comprador—
  sigue en pie.

## 2. Qué significa "Argentina" en esta arquitectura

**Decisión**: país = host, vía `muyu_get_current_country_from_subdomain()`.

**Evidencia**: es la regla que ya usan precios, catálogo, checkout y bloqueo de carrito
(`is_restricted_user()` en `digital-restriction.php:132` compara el host con
`muyunicos.com`). Medir el país de otra forma (geolocalización de la IP, idioma del
navegador) haría que un comprador en Argentina entrando por el subdominio de México
recibiera la página una vez sí y otra no.

**Consecuencia declarada**: `/pt/envios/` y `/en/envios/` del dominio raíz siguen
publicándose: son Argentina en otro idioma. Excluirlos exige una segunda dimensión
(idioma) y verificar los filtros de wpLingua: fuera de alcance.

## 3. Qué respuesta merece la ruta en los países que no la publican

**Decisión** (del mantenedor, 2026-10-16): `302` a la tienda del subdominio actual.

**Alternativas evaluadas**:
- `404` / `410`: señal de desindexación más fuerte, pero el comprador que llegó por un
  enlace viejo queda en una pantalla de error y en cada país aparecerían errores de
  rastreo evitables.
- `200` con aviso ("envíos físicos sólo en Argentina"): conserva la URL y su indexación por
  país; contradice el objetivo de la feature.
- `301` a la tienda: declara que la página "se mudó", lo cual es falso, y fija un juicio
  permanente para una decisión reversible.
- `302`: el comprador sigue navegando en su país, Google retira la URL de ese subdominio, y
  revertir la decisión no deja marcas.

## 4. En qué instancia corre el guard y quién más lo ve

**Decisión**: `template_redirect` prioridad **15**, aplicado también a bots.

**Evidencia**: el orden de instancias ya está ocupado así:
`mu_litespeed_bot_404_fast_exit` (p1), `mu_litespeed_nocache_404` (p5),
`mu_auto_detect_country_by_domain` (p10) y `handle_redirects` de `digital-restriction`
(p20). En p15 el 301 canónico del core (p10) ya normalizó la URL y todavía no corrió la
lógica de catálogo.

**Por qué también a bots**: el `302` es lo que retira la URL del índice del país. Con
`404` habría quedado la asimetría conocida del repo: `mu_litespeed_bot_404_fast_exit`
(p1) le da a los bots un HTML mínimo y a las personas la 404 del tema (documentado en
`compat-litespeed.php`, con el antecedente de cache-poisoning).

## 5. Cómo se reconoce la ruta pedida

**Decisión**: `is_page( 'envios' )` como vía principal y la ruta normalizada de
`mu_wplingua_original_uri()` como defensa en profundidad.

**Evidencia**: guía §7, "LECCIÓN CLAVE": wpLingua reescribe `$_SERVER['REQUEST_URI']` en
`init` (p1) quitándole el prefijo de idioma, así que en `template_redirect` esa variable ya
no tiene el `/pt` ni el `/en`. `is_page()` se resuelve contra el main query y es inmune a
esa reescritura. El prefijo de idioma se lee de su fuente única
(`mu_wplingua_language_prefixes()`), no de una lista repetida.

**Descartado**: comparar `$_SERVER['REQUEST_URI']` crudo; y usar el ID de la página
(20036) como clave, que no dice nada en el código y cambia si la página se recrea.

## 6. Cómo queda el `hreflang`

**Decisión**: una sola entrada autorreferencial, en el idioma de la página, sin
`x-default`.

**Evidencia**: hoy `/envios/` lleva dos conjuntos de `hreflang`: el del tema
(`inc/seo-hreflang.php`: 11 alternates a todos los subdominios + `x-default` al raíz) y el
de wpLingua (`es`, `pt`, `en` del mismo host + su propio `x-default`). Si la ruta sólo
existe en un host, los alternates del tema apuntan a URLs que responden `302`: es la fuga
que prohíbe el principio V, y su Pass/Fail la nombra explícitamente ("un `hreflang` sin su
recíproco"). Emitir un segundo `x-default` competiría con el de wpLingua, así que el
conjunto del tema no lo declara.

## 7. El sitemap

**Decisión**: filtrar la entrada de Rank Math por URL, con un callback defensivo, y
declararlo como punto a confirmar.

**Evidencia**: `curl -s https://mexico.muyunicos.com/page-sitemap.xml` lista `/envios/` con
sus alternates. La documentación pública de Rank Math no expone la firma exacta del filtro
de sitemap (la página de hooks se trunca antes de esa sección), así que el callback:
(a) sólo actúa si recibe una URL string, (b) reconoce la ruta por el último segmento de la
URL y no por el argumento `$post` —cuyo orden de argumentos es lo que no se pudo
confirmar— y (c) si no dispara, el estado es el actual (entrada listada), nunca un sitemap
roto. Se verifica en §B.4 del quickstart.

## 8. Por qué un módulo nuevo y no `geo.php` / `ui.php`

**Decisión**: `inc/country-scope.php`.

**Evidencia**: principio I. La capacidad tiene tres consumidores (guard HTTP, pie de
página y SEO) y una fuente única (el registro). Poner el registro en `geo.php` (país), el
enlace en `ui.php` y el hreflang en `seo-hreflang.php` repartiría el criterio en tres
archivos: la próxima ruta por país se declararía en tres lugares y se desincronizaría.
`geo.php` no se toca: se reutiliza su fuente de país, que es lo que la guía §7 pide para no
duplicar la señal de país.

## 9. Cómo se restringe un elemento suelto

**Decisión**: tres formas, en orden de preferencia: (1) registrar la ruta; (2)
`muyu_is_country( [...] )` alrededor de la salida; (3) `body.mu-country-xx` + CSS cuando el
elemento lo imprime un plugin o wp-admin.

**Evidencia**: la body class ya existe y ya se usa con este propósito
(`css/components/global-ui.css`, precios del overlay de Jetpack Search). Preferir (2) sobre
(3) evita emitir HTML —y trabajo de pintado— que después hay que ocultar.

## 10. Riesgo de la decisión, declarado

- La caché de página: la respuesta `200` de `/envios/` está cacheada 7 días **por host**
  (`X-LiteSpeed-Cache-Control: public,max-age=604800`). Sin purga después del deploy, los
  subdominios seguirían sirviendo el `200` viejo y el fix parecería no haber llegado.
- El deploy: si `functions.php` no llega actualizado, el módulo queda en disco sin
  cargarse y el síntoma es idéntico al actual (guía §1). Por eso el guard emite su propia
  cabecera de trazabilidad.

