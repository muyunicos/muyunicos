# Tasks: Un solo bloque de idioma por editor (017)

**Input**: `specs/017-bloque-idioma-unico/spec.md`

## Format: `[ID] Description` — `[X]` = ejecutada · `[ ]` = pendiente

## Tramo H · Editor de banners

- [X] T001 `inc/hero-banners.php`: `$render_campo()` — un `<p>` por campo con sus tres variantes (`.mu-hero-banner-variante[data-lang]`, `hidden` en las no activas) y los `name` de siempre
- [X] T002 `inc/hero-banners.php`: `$render_toolbar_idioma()` — píldoras + variantes de IA + «Traducir», ahora en la línea del primer grupo; se retira la barra `row__head` y queda sólo el input del idioma activo
- [X] T003 `inc/hero-banners.php`: bloque **único**: avisos por idioma con `[data-lang][hidden]`, tres grupos y el toolbar en «Texto»
- [X] T004 `js/admin-hero-banners.js`: `setLang()` togglea variantes con `prop('hidden')`; los ~11 selectores pasan a `.mu-hero-banner-variante[data-lang=X]`; el selector de imagen se acota a la variante
- [X] T005 `css/admin-hero-banners.css`: línea de grupo flex · toolbar sin borde y sin la tipografía del rótulo · `.mu-hero-banner-variante:not([hidden]) { display: block }`
- [X] T006 `specs/013-…/harness/arnes-render-hero.php`: 9 → 3 grupos **y 12 aserciones nuevas** (un bloque · 30 variantes · toolbar en la línea · los `name` intactos · **0 `disabled`** · visibilidad por idioma) — **130 OK**
- [X] T007 `specs/015-…/harness/css-check.js`: 3 aserciones nuevas (hidden vs display · línea flex · toolbar sin borde) — **85 OK**

## Tramo R · Modal de reseñas del front

- [X] T010 `inc/ui.php`: un `.mu-review-lang-block` con las tres variantes del textarea (`data-lang` + `hidden`, `id`/`name` intactos)
- [X] T011 `js/reviews-admin-modal.js`: `setLang()` togglea `[data-lang]` con `hidden` y **el `required` viaja con la visibilidad** (un `required` oculto bloquea el submit con un mensaje invisible)
- [X] T012 `specs/007-…/harness/editor-resenas.js`: 5 aserciones nuevas (variante visible/oculta + `required` por idioma) — **41 OK**

## Verificación

- [X] T020 `php -l inc/hero-banners.php inc/ui.php` y `node --check` de los dos JS
- [X] T021 Los 12 arneses verdes: CSS 85 · 013 130 + 17 · 012 102 + 18 · 010 99 + 38 · 011 18 · 008 30 + 17 · 006 54 · 007 41
- [X] T022 `git diff --name-only` sin nada bajo `Tema-GeneratePress/`
- [ ] T023 **Fase B manual**: guardar estando en PT y recargar (las tres versiones persisten) y el toolbar que no se mueve ni desaparece
