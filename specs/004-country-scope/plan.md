# Implementation Plan: Alcance por país de rutas y elementos

**Branch**: `main` (ver "Nota de despliegue", al final) | **Date**: 2026-10-16 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/004-country-scope/spec.md`

## Summary

`/envios/` describe logística argentina y hoy se sirve, se enlaza y se indexa en los 9
subdominios de país. La corrección se implementa como una capacidad reusable del tema,
no como un parche de esa página:

1. Un módulo nuevo, `inc/country-scope.php`, con un **registro único de rutas con
   alcance por país** y un **guard HTTP** en `template_redirect` p15 que responde `302`
   a la tienda del subdominio actual cuando el país no publica la ruta.
2. Un **helper de país** (`muyu_is_country()`) que el pie de página usa para no emitir el
   enlace, y que sirve para cualquier elemento del tema que deba renderizarse sólo en
   países determinados.
3. El **contrato SEO**: `inc/seo-hreflang.php` deja de declarar los 9 subdominios para
   una ruta que sólo existe en uno, y se saca la ruta del sitemap de los subdominios.

No cambia precios, checkout, catálogo, canonical ni robots. El dominio de Argentina
sirve la página exactamente como hoy.

## Technical Context

**Language/Version**: PHP 8.5.4 (producción, `X-Powered-By`, 2026-10-16), Markdown para
la documentación.

**Primary Dependencies**: WordPress, WooCommerce, GeneratePress (tema padre, sólo
lectura), wpLingua 2.13.0, Rank Math, LiteSpeed Cache. **Sin dependencias nuevas.**

**Storage**: No aplica. El registro vive en código; el módulo sólo lee el host y el main
query ya resuelto por WordPress. No escribe opciones, transients ni sesión.

**Testing**: este proyecto no tiene suite, CI, composer ni package.json (constitución
IX, y no se añaden). La verificación es: `php -l` de cada archivo tocado, un arnés local
de stubs para la lógica pura (11 escenarios, §A.6 de `quickstart.md`) y `curl` contra
producción para la respuesta HTTP real.

**Target Platform**: 10 hosts (dominio raíz + 9 subdominios de país), navegador y bots,
Linux detrás del CDN de Hostinger. Producción es el único entorno.

**Project Type**: tema hijo de WordPress (arquitectura modular de `inc/`).

**Performance Goals**: 0 consultas SQL nuevas. El guard cuesta una lectura de host
(cacheada con static) más `is_page()` sobre el main query ya resuelto: si el país publica
la ruta o la página no está en el registro, sale sin emitir nada. El caso más común
(cualquier otra página) es un `return` temprano.

**Constraints**:
- No se toca `Tema-GeneratePress/` (principio X).
- No se modifica el comportamiento de Argentina: `200`, canonical self, `robots: index`.
- La respuesta anterior está cacheada 7 días **por host** (`X-LiteSpeed-Cache-Control:
  public,max-age=604800`): el despliegue exige purga.
- Un elemento de menú de WordPress no es alcanzable por código: queda verificado y
  documentado a mano (§B.2 de `quickstart.md`).

## Constitution Check

| Principio | Cómo lo cumple este diseño |
|---|---|
| I. Modularidad pragmática | Un módulo nuevo (`country-scope.php`) en lugar de lógica repartida: tiene una responsabilidad propia (registro + guard) y tres consumidores (guard, pie, SEO). El ajuste de UI va en `ui.php` y el SEO en `seo-hreflang.php`, donde ya viven |
| II. Carga condicional de assets | No se agrega CSS ni JS: el guard responde antes de renderizar y el enlace simplemente no se emite |
| III. Código WordPress/WooCommerce seguro | Guards `function_exists()`, cabecera HTTP explícita, `wp_safe_redirect()`, escape de salida (`esc_html`, `esc_url`, `esc_attr`), sin SQL |
| IV. Corrección comercial | No toca precios, impuestos, pasarelas ni el carrito. No altera el bloqueo de productos físicos (vive en `cart-restriction`) |
| V. SEO e i18n multi-país | Es el objetivo de US3: el `hreflang` deja de declarar hosts que no sirven la página; el canonical de Argentina queda self; se quita la ruta del sitemap de subdominios |
| VI. Rendimiento y caché | 0 consultas SQL; el registro y el host se cachean en static por request; el guard sale antes del render. Se declara la purga obligatoria por host |
| VII. Observabilidad | Cabecera de trazabilidad `X-MU-Country-Scope`, misma convención que `X-MU-WpLingua-Lang` y `X-MU-Bot-404` |
| VIII. Sistema de diseño | Sin CSS nuevo, sin variables nuevas, sin SVG (no aplica) |
| IX. Verificación | `php -l`, arnés local reproducible y matriz de `curl` por host. La fase de producción se declara explícitamente por ejecutar |
| X. Perímetro de seguridad y plugins | No se activa ningún plugin ni se edita uno. El único acoplamiento es el filtro de sitemap de Rank Math, ya declarado como plugin acoplado en la guía §3, y el callback es defensivo |

**Sin violaciones.** El único punto abierto —el contrato del filtro de sitemap de Rank Math—
se cerró el 2026-10-16 verificando el código del plugin instalado tras una medición fallida
en producción: ver Complexity Tracking y §8 del guide.

## Project Structure

### Documentation (this feature)

```text
specs/004-country-scope/
├── plan.md                    # Este archivo
├── spec.md                    # Especificación (de /speckit-specify)
├── research.md                # Phase 0: decisiones y alternativas descartadas
├── contracts/
│   └── country-scope.md       # Contrato de la API reutilizable y del comportamiento HTTP
├── quickstart.md              # Verificación: local automatizable + producción manual
└── tasks.md                   # Phase 2 (de /speckit-tasks)
```

### Source Code (repository root)

```text
generatepress-child/
├── functions.php              # SE MODIFICA: 1 línea de carga del módulo (tras compat-wplingua)
├── inc/
│   ├── country-scope.php      # SE CREA: registro, guard, helper de país, hreflang y sitemap
│   ├── ui.php                 # SE MODIFICA: el <li> de "Información de Envíos" se condiciona
│   └── seo-hreflang.php       # SE MODIFICA: alternates reducidos para rutas con alcance
└── MIGRATION-GUIDE.md         # SE ACTUALIZA: §3 (regla), §4 (módulo), §6 (API), §7 (diagnóstico), §8 (huecos)
```

**Structure Decision**: módulo nuevo justificado por el principio I (una capacidad
aislada con varios consumidores, no un ajuste de un archivo), y por una razón práctica:
el registro tiene que ser la fuente única de ruta + enlace + SEO. Repartir esas tres
cosas entre `geo.php`, `ui.php` y `seo-hreflang.php` haría que la próxima ruta solo-AR se
declare en tres lugares y se desincronice. `inc/geo.php` **no se toca**: se reutiliza
(`muyu_get_current_country_from_subdomain()`, `muyu_country_language_prefix()`,
`muyu_clean_uri()`), que es lo que la guía §7 pide para no duplicar la fuente de país.

## Complexity Tracking

> Sin violaciones de la constitución. Se registra el único punto que sí costó verificación,
> con su cierre medido:

| Punto abierto | Por qué se aceptó | Cómo se cerró |
|---|---|---|
| Contrato del filtro de sitemap de Rank Math | La documentación pública no expone la firma (la página de hooks se trunca antes de la sección de sitemap) y el repo no versiona plugins: la primera versión del callback llevaba una guarda `is_string( $url )` que devolvía la entrada sin tocar y desactivaba la exclusión en silencio | Cerrado el 2026-10-16 leyendo el código de la versión instalada (seo-by-rank-math 1.0.280, descargada de wordpress.org — el `readme.txt` de producción confirma la misma versión): `rank_math/sitemap/entry` recibe `[ 'loc' => … ]` y `[]` excluye la entrada. Callback corregido y verificado en local (`quickstart.md` §A.6). La remedición en producción queda en `quickstart.md` §B.4 |

## Nota de despliegue (decisión del mantenedor, 2026-10-16)

La feature se commiteó directamente en `main`, no en una rama semántica. Motivo: Hostinger
despliega con su función Git al recibir el push a `main`, así que trabajar en una rama
obligaba a un merge posterior sólo para que el deploy la viera, con el riesgo de dejar el
trabajo sin desplegar.

La constitución pide ramas `fix/`, `perf/`, `refactor/`, `feat/`. Queda registrado que en
este flujo el mantenedor publica desde `main`: el trabajo va íntegro en un solo commit
(revertible con `git revert`), y si la práctica se repite corresponde enmendarla con
`/speckit-constitution`, no esquivar la regla en silencio.


