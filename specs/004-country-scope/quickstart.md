# Quickstart — Validación de `004-country-scope`

**Feature**: `004-country-scope` | **Fecha**: 2026-10-16 | **Plan**: [plan.md](./plan.md)

## Antes de empezar: qué se verifica dónde

Este proyecto **no tiene suite de pruebas, integración continua ni staging**. Producción
es el único entorno. Por eso esta guía tiene dos mitades y no conviene mezclarlas:

| Tipo | Qué es | Cuándo |
|---|---|---|
| **Automatizable (Fase A)** | Sintaxis PHP, alcance del diff, invariantes del código y la lógica pura del módulo con stubs | Antes del commit, en local |
| **Manual (Fase B)** | La respuesta HTTP real por host, el pie de página renderizado, el sitemap y la purga de caché | Después del deploy, contra producción |

**La Fase B no es opcional.** Si no se ejecuta, la feature se declara **NO VERIFICADA EN
PRODUCCIÓN** en el pull request, nunca como verificada.

---

## Fase A — Verificaciones automatizables (local)

Ejecutar desde la raíz del repositorio, en PowerShell 7.

### A.1 — Sintaxis PHP

```powershell
php -l inc\country-scope.php
php -l inc\ui.php
php -l inc\seo-hreflang.php
php -l functions.php
```

**Medido el 2026-10-16**: `No syntax errors detected` en los cuatro (PHP local 8.5.9;
producción corre 8.5.4 — `php -l` verifica sintaxis, no comportamiento).

### A.2 — El tema padre no se toca y el alcance del diff es el esperado

```powershell
git diff --name-only main...HEAD
```

**Esperado** (y medido):

```text
MIGRATION-GUIDE.md
functions.php
inc/country-scope.php   (nuevo)
inc/seo-hreflang.php
inc/ui.php
specs/004-country-scope/… (documentación de la feature)
```

**Falla si** aparece algo bajo `Tema-GeneratePress/` (principio X).

### A.3 — El registro es la fuente única

```powershell
Select-String -Path inc\*.php,functions.php -Pattern "muyu_get_country_scope_routes|muyu_country_scope_routes" |
  ForEach-Object { "$($_.Filename):$($_.LineNumber)" }
```

**Esperado**: las definiciones y consumidores en `inc/country-scope.php` (registro, guard,
hreflang, sitemap) y ninguna lista paralela de rutas ni de países en otros módulos.
Verificado el 2026-10-16.

### A.4 — El enlace del pie ya no se emite incondicionalmente

```powershell
Select-String -Path inc\ui.php -Pattern 'href="/envios/"' -Context 6,0 |
  ForEach-Object { $_.Context.PreContext; $_.Line.Trim() }
```

**Esperado**: la línea del `<li>` queda dentro del `if ( … muyu_is_country( … ) )` añadido.

### A.5 — El hreflang del tema ya no declara los 9 subdominios para la ruta

```powershell
Select-String -Path inc\seo-hreflang.php -Pattern 'muyu_get_country_scope_hreflang_tags' -Context 0,2
```

**Esperado**: el `return` temprano con el conjunto reducido, antes del bucle de países. La
salida real se comprueba en producción (§B.3): en local no hay `wp_head`.

### A.6 — Lógica pura del módulo, con arnés de stubs (sin WordPress)

Se escribe un arnés en `%TEMP%` (no se versiona: el principio IX no autoriza añadir
infraestructura de pruebas al proyecto) con los stubs mínimos —`ABSPATH`, `apply_filters`,
`add_action`, `add_filter`, `wp_unslash`, `wp_parse_url`, `home_url`, `is_page`,
`is_admin`, `wp_doing_ajax`, `wp_doing_cron`, `is_feed`, `wc_get_page_permalink`, y
`wp_safe_redirect`, que lanza una excepción para simular el `exit` del guard— más las
funciones de `geo.php` y `compat-wplingua.php` que el módulo reutiliza
(`muyu_get_current_country_from_subdomain`, `muyu_country_language_prefix`,
`muyu_clean_uri`, `mu_wplingua_original_uri`, `mu_wplingua_language_prefixes`,
`muyu_get_current_language`). Luego se la incluye y se invoca con `host` y `request_uri`.

**Medido el 2026-10-16** (salida textual del arnés):

| Host | Ruta pedida | Resultado medido |
|---|---|---|
| `muyunicos.com` | `/envios/` | país `AR`; `route_for_request: envios`; hreflang `[{es, https://muyunicos.com/envios/}]`; sitemap conserva la URL |
| `mexico.muyunicos.com` | `/envios/` | país `MX`; `302 → https://mexico.muyunicos.com/tienda/`; hreflang `[]`; sitemap excluye la URL |
| `co.muyunicos.com` | `/pt/envios/` | `302 → https://co.muyunicos.com/tienda/` (CO no tiene prefijo; el `/pt/` se descarta) |
| `br.muyunicos.com` | `/pt/envios/` | `302 → https://br.muyunicos.com/pt/tienda/` (prefijo aplicado, sin duplicar) |
| `us.muyunicos.com` | `/envios/` | `302 → https://us.muyunicos.com/en/tienda/` (prefijo del país) |
| `mexico.muyunicos.com` | `/pt/envios/` | `302 → https://mexico.muyunicos.com/tienda/`; `normalized_path: /envios/` |
| `muyunicos.com` | `/pt/envios/` | país `AR`; hreflang `[{pt, https://muyunicos.com/pt/envios/}]`; sin `x-default` propio |
| `muyunicos.com` | `/tienda/` | `route_for_request: null` → el guard no interviene; sitemap intacto |
| `mexico.muyunicos.com` | `/envios/` con `?utm=…` | igual que la fila 2: la query no altera la resolución |
| `muyunicos.com` | `/envios/` (modo guard) | `sin redirect: la ruta se sirve en este país` |
| `co.muyunicos.com` | `/tienda/` (modo guard) | `sin redirect`: sólo se bloquean las rutas registradas |

> El arnés imprime, en modo normal, la traza JSON del request y en modo `guard` el
> resultado del guard (el `header()` se emite en CLI sin efecto; la cabecera real se
> verifica en §B.1).

**Falla si**: algún host de la tabla devuelve país distinto, un `302` fuera del host
pedido, un prefijo duplicado (`/pt/pt/tienda/`), o un `route_for_request` no nulo en
`/tienda/`.

---

## Fase B — Producción (obligatoria, la ejecuta el mantenedor)

### B.0 — Purga antes de medir

El `200` de `/envios/` está cacheado **7 días por host**
(`X-LiteSpeed-Cache-Control: public,max-age=604800`). Purgar la caché de LiteSpeed (Purga
total) y, si el guide §1 lo indica para este deploy, purgar OPcache. **Sin esto, la matriz
de B.1 mide la respuesta vieja.**

### B.1 — Matriz de respuesta por host (SC-001, SC-002, FR-002, FR-003, FR-006)

```powershell
'mexico','co','es','cl','pe','br','ec','us','cr' | ForEach-Object {
  "$_ → " + ((curl.exe -sI "https://$_.muyunicos.com/envios/" | Select-String 'HTTP/|Location|X-MU-Country-Scope' | ForEach-Object { $_.Line.Trim() }) -join ' | ')
}
curl.exe -sI https://muyunicos.com/envios/ | Select-Object -First 1
```

**Esperado**: `9 × "HTTP/1.1 302 ... Location: https://<host>/tienda/ | X-MU-Country-Scope: redirect"`
y `HTTP/1.1 200 OK` en el dominio raíz.

**Falla si**: algún subdominio responde `200` **con** la cabecera `X-MU-Country-Scope` (el
guard corrió y algo lo anuló: revisar caché y `wp_safe_redirect`) o `200` **sin** la
cabecera (el guard no está cargado: revisar el orden de la guía §1 — archivo en disco,
`functions.php`, OPcache, pull, caché de página).

### B.2 — Pie de página y menú (SC-003, FR-005)

```powershell
curl.exe -s https://muyunicos.com/ | Select-String 'href="/envios/"'          # esperado: 1 resultado
curl.exe -s https://mexico.muyunicos.com/ | Select-String 'href="/envios/"'   # esperado: sin resultados
```

Además, **a mano en wp-admin** (Apariencia → Menús): comprobar que `/envios/` no está en
ningún menú del sitio. Si estuviera, quitarlo ahí: un menú es dato, no código.

### B.3 — SEO en Argentina (SC-004, FR-007, FR-008)

```powershell
curl.exe -s https://muyunicos.com/envios/ | Select-String 'rel="canonical"|hreflang|name="robots"'
```

**Esperado**: `canonical` self (`https://muyunicos.com/envios/`), `robots` con `index`, un
`hreflang="es"` autorreferencial del tema (además de los de wpLingua) y **ningún**
`alternate` del tema hacia `mexico.`, `co.`, `es.`, `cl.`, `pe.`, `br.`, `ec.`, `us.`, `cr.`.

### B.4 — Sitemap de un subdominio (SC-005, FR-014)

```powershell
curl.exe -s https://mexico.muyunicos.com/page-sitemap.xml | Select-String 'envios'
```

**Esperado**: sin resultados (`/envios/` ya no se lista).

**Si sigue listada**: el filtro `rank_math/sitemap/entry` no disparó en esa versión del
plugin. No es un error del tema ni rompe nada (la URL simplemente redirige). Registrar el
hueco en §8 del guide con las dos salidas: encontrar el hook correcto de Rank Math, o
aceptar la entrada declarándola como hueco conocido.

### B.5 — No-regresión (SC-006, FR-013)

```powershell
foreach ($u in @('https://mexico.muyunicos.com/tienda/','https://mexico.muyunicos.com/blog/','https://muyunicos.com/tienda/')) {
  "$u → " + (curl.exe -sI $u | Select-Object -First 1)
}
```

**Esperado**: `200` en las tres (el guard sólo actúa sobre rutas registradas). Y en el
navegador: una categoría de producto y una página de producto de un subdominio siguen
abriendo con sus precios en la moneda del país.

### B.6 — Caso de fallo, con su diagnóstico

Los dos fallos posibles y cómo se distinguen (orden de diagnóstico de la guía §1):

| Síntoma | Causa | Arreglo |
|---|---|---|
| `200` en un subdominio **sin** `X-MU-Country-Scope` | El guard no se cargó: el archivo no llegó, o `functions.php` quedó modificado en el servidor (el deploy respeta los cambios locales sin avisar) | Comparar el archivo del servidor con el del repo (`git diff --no-index`) y volver a subir el del repo; después purgar OPcache y caché de página |
| `200` en un subdominio **con** `X-MU-Country-Scope` | El guard corrió pero la respuesta quedó cacheada antes, o el destino fue rechazado por `wp_safe_redirect` | Purgar la caché de ese host y repetir; si persiste, revisar el `Location` (debe ser el mismo host) |

### B.7 — Registro del resultado

Dejar en el pull request:

- La salida de `php -l` de los cuatro archivos.
- La salida de la matriz de `curl` de B.1 (los 10 hosts) y de B.2 a B.4.
- El resultado de B.2 (menú en wp-admin) y de B.5 (no-regresión en el navegador).
- La confirmación de la purga de caché (B.0).

---

## Fase C — Cierre

Si A y B pasaron, la feature está verificada. Si la Fase B no se ejecutó, el PR declara la
feature **NO VERIFICADA EN PRODUCCIÓN** y no se presenta de otra manera.

Comprobaciones de cierre:

1. `git status --short` sin archivos sin intención (el arnés de §A.6 vive en `%TEMP%`, no en
   el repo).
2. `MIGRATION-GUIDE.md` actualizado: §3 (regla de negocio), §4 (módulo nuevo), §6 (API de
   alcance por país), §7 (diagnóstico de la página visible en subdominios) y §8 (huecos).
3. `specs/004-country-scope/` con spec, plan, research, contrato, quickstart y tasks.
4. Rollback definido: `git revert` del commit del fix + purga de caché. El cambio es
   aditivo (un módulo nuevo y dos condicionales) y no toca base de datos, precios ni
   checkout.


