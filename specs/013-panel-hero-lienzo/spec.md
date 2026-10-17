# 013 — Panel del banner: render compartido + lienzo vivo (sin iframe)

**Estado**: Fase 0 implementada (2026-10-17); Fases 1-3 diseñadas, sin empezar.
**Rama**: `refactor/panel-hero-lienzo` · **Riesgo global**: alto en Fase 0 (toca la home), medio en el resto.

## Problema

El editor de banners tiene tres problemas que se refuerzan entre sí:

1. **El marcado del banner vivía inline en `mu_hero_section()`** (`inc/ui.php`): cualquier vista
   previa tenía que duplicar HTML y CSS, con deriva garantizada.
2. **Los estilos del hero vivían dentro de `css/home.css`**, que sólo se encola en la home: el panel
   no tenía ni una regla del banner.
3. **La fila del panel no se parece al banner**: los campos están en un grid genérico, la vigencia
   (lo que más se toca) quedó al final después de ~30 campos, y no hay forma de *ver* lo que se
   edita — con 4 banners cargados, editar es a ciegas.

## Fase 0 — Render compartido (IMPLEMENTADA)

| # | Cambio | Archivos |
|---|---|---|
| 1 | **`mu_hero_slide_render( $promo, $lang, $args )`**: el marcado del slide (clases, orden de atributos, escape y defaults del badge) pasa a una función con `function_exists`. Vive en `inc/hero-banners.php` por el orden de carga (`functions.php`: `icons` 360 → `hero-banners` 379 → `ui` 381): el panel se renderiza desde este módulo, así que el renderer debe cargarse antes que `ui.php`. | `inc/hero-banners.php` |
| 2 | **`mu_hero_section()` usa el renderer** y se queda sólo con el wrapper, el slider, los dots y el helper de idioma; el `$mu_hero_field` inline y los defaults del badge se retiran (están en el renderer). | `inc/ui.php` |
| 3 | **`css/components/hero-promo.css`**: las 301 líneas del bloque del hero (base + `@media (max-width: 968px)` + `@keyframes mu-hero-float`) se **mueven** desde `css/home.css` (§3), sin tocar una sola regla. | `css/home.css`, `css/components/hero-promo.css` |
| 4 | **Encolado** del componente en la home (`mu_home_sections_enqueue()`) con `filemtime`; en Fase 2 se sumará al panel. | `inc/ui.php` |
| 5 | **Arnés de igualdad** con fixture de producción capturado **antes** de la extracción. | `specs/013-panel-hero-lienzo/harness/` |

### Criterios de aceptación de la Fase 0

1. El arnés reproduce el slide de producción: igualdad normalizada del bloque completo, **byte a
   byte** en `<h1>`, `<p>`, `src`, `alt`, `href` y texto del CTA principal (37 asertos).
2. Cada campo sigue pasando por **la misma función de escape** que antes (marcadores del arnés:
   título → `wp_kses_post`, descripción/eyebrow/CTA → `esc_html`, `alt` → `esc_attr`, URLs → `esc_url`).
3. `mu-hero-promo` no aparece en `css/home.css` (0 reglas) y sí en el componente (34 selectores,
   1 `@media`, 1 `@keyframes`).
4. Los arneses 006/008/010/011/012 mantienen sus conteos y `php -l`/`node --check` están limpios.
5. **En producción** (Fase B): el hero de `muyunicos.com` y de 2 subdominios es idéntico al fixture.

### Cómo se verifica la igualdad (y qué NO se compara)

- **Fixture de producción**: `harness/fixtures/hero-slide-produccion-ar.html`, capturado con
  `curl` **antes** de la extracción (1 slide; el banner del Cotizador, con eyebrow, CTA principal y
  CTA secundario, sin badge).
- **Normalización**: se colapsan los espacios en blanco (dentro y entre etiquetas). No es laxitud:
  la indentación de un bucle inline no es reproducible dentro de una función y el navegador la
  ignora. **Etiquetas, atributos, su orden y los textos deben coincidir** o el arnés falla.
- **Comparación como DOM**: ambos lados se serializan con `DOMDocument` (libxml baja `viewBox` a
  `viewbox`; comparar crudo contra serializado daría un falso fallo). El `viewBox` se verifica
  aparte sobre el HTML crudo del renderer.
- **Byte a byte** en los nodos con contenido y en `src`/`alt`/`href`, donde no hay ambigüedad de
  espacios.

## Fases 1-3 — Lienzo vivo + jerarquía (diseñadas, sin empezar)

Se descartó el iframe con `?mu_banner_preview`: para lograr fidelidad había que resolver flag
público, nonce entre subdominios, admin bar, `nocache_headers`, caché por query y
`modal-auth.css` condicional (todo medido). El lienzo **no necesita nada de eso** porque no hace
ninguna request: usa el mismo renderer y el mismo CSS que el front.

| Fase | Contenido |
|---|---|
| 1 · Jerarquía | Fila-resumen (título + estado calculado con `is_active_for_pais`/`proximo_borde`); **chips de país unificados** (se retira el dropdown «Países»); secciones `Contenido` / `Vigencia` (tabla con Global + países, todos a la vez) / `Identidad`; plegado por fila; Duplicar / Ordenar / Eliminar con deshacer. |
| 2 · Lienzo | El lienzo renderiza `mu_hero_slide_render()` con los datos de la fila, en el **idioma activo** (las píldoras ES\|PT\|EN pasan a tener sentido), `container-type: inline-size` + `4cqw` en lugar de `4vw`, selector Escritorio (1280) / Móvil (390) que aplica el modificador de las reglas móviles, `transform: scale()` por JS para encajar en la columna y `position: sticky` en pantalla ancha. **Vivo por patch de nodos** (`data-mu="campo"`), sin generar HTML en JS y sin request. |
| 3 · Interacción | **N1** click-para-foco (clic en el lienzo → foco y scroll al campo del form, con resaltado). **N2** edición directa `contenteditable="plaintext-only"` sólo en los campos de texto plano (eyebrow, descripción, textos de CTA y del badge); el título NO (admite `<span class="mu-highlight">`). **N2-bis** opcional: título editable con un botón «resaltar selección». |

### Decisiones abiertas (Fase 3)

1. N2 (edición directa) vs N1 (sólo navegación por clic).
2. ¿N2-bis para el título (editar + resaltar) o el título queda por el formulario?
3. Lienzo pegajoso a la derecha (recomendado) o arriba del bloque.

## Riesgos medidos (2026-10-17)

| Riesgo | Medición | Estado |
|---|---|---|
| El CSS del hero estaba mezclado con otras secciones | Bloque 186-486 de `home.css`, autocontenido (su `@media` y su `@keyframes` incluidos); **ningún** otro CSS define `.mu-hero-promo-*` | ✅ movimiento limpio (0 reglas quedan en `home.css`) |
| El problema de fidelidad sin iframe | **Una sola** regla usa `vw` (`home.css:292`, el título): se resuelve con `4cqw` + contenedor | ✅ acotado a una línea en Fase 2 |
| El orden de carga impedía compartir el renderer | `icons` 360 → `hero-banners` 379 → `ui` 381 | ✅ el renderer va en `hero-banners` |
| **El front cambia** (riesgo real de la Fase 0) | 37 asertos contra el fixture de producción, escape por campo verificado; Fase B con `curl` en 3 hosts | ✅ cubierto; pendiente la verificación en producción |
| Hallazgo **previo** (no lo introduce esta spec) | `.mu-btn-outline` (CTA secundario del hero) está definido en `css/cart.css` y `css/checkout.css`, hojas que la home **no** carga: el botón secundario no tiene estilo propio en el front. El lienzo lo mostrará igual (fiel) | 📋 reportado; se resuelve en un commit `fix/` aparte si se decide |

## Limitaciones declaradas

- El lienzo **no** reproduce las media queries continuas (el modo móvil se conmuta a mano) ni el
  autoplay/dots del slider: para eso queda el enlace «Ver en el sitio ↗» (simple, sin flag).
- Fases 1-3 no se despliegan con la Fase 0: van en commits separados y con su propia verificación.
- El manejo de jQuery del panel no es automatizable en este proyecto: se verifica a mano
  (`MIGRATION-GUIDE.md` §8).
