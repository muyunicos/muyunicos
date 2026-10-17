# Tasks: Densidad del panel y estado de la IA (018)

**Input**: `specs/018-densidad-y-estado-ia/spec.md`

## Format: `[ID] Description` — `[X]` = ejecutada · `[ ]` = pendiente

## A · Dos columnas, sobrante al lienzo

- [X] T001 `css/admin-hero-banners.css`: `grid-template-columns: minmax(320px, 420px) minmax(400px, 1fr)` (se va la tercera columna vacía) con el comentario de los números medidos
- [X] T002 `specs/015-…/harness/css-check.js`: actualizar la aserción del layout a la cadena de 2 columnas + **nueva** aserción que falla si alguien vuelve a la 3ª columna (86 OK)

## B · Estado de la IA

- [X] T010 `js/admin-hero-banners.js`: `setAiStatus()` — el éxito va al `title` del botón «Traducir» (con `data('tituloBase')` para restaurarlo), los avisos importantes siguen visibles en rojo, y sin texto se limpia todo
- [X] T011 `css/admin-hero-banners.css`: `max-width` del estado 320px → **200px** (el toolbar vive en una columna de 420px desde 017)
- [X] T012 Los textos de los mensajes **no** se tocaron (los arneses 008/011 verifican su contenido y siguen verdes sin cambios)

## C · Variantes por idioma

- [X] T020 `js/admin-hero-banners.js`: `tandasDeVariantes()` (`aiBatches` por idioma) + `tandaDeIdioma()` **pura** (expuesta en `window.muHeroBanners`)
- [X] T021 `js/admin-hero-banners.js`: `pintarVariantesDeIdioma()` + `pintarBotonesVariantes()`; `pintarVariantes()` guarda por idioma; `aplicarVariante()` usa la tanda del idioma activo y recuerda `activa`
- [X] T022 `js/admin-hero-banners.js`: `setLang()` repinta en vez de llamar a `limpiarVariantes()` (que se retiró: era el descarte automático al cambiar de idioma)
- [X] T023 `specs/011-…/harness/variantes-js.js`: sección 7 con 10 aserciones (tanda por idioma · número activo · normalización · null sin tanda · **consultar un idioma no borra los otros**) — **28 OK**

## Verificación

- [X] T030 `php -l` y `node --check` limpios
- [X] T031 Los 12 arneses verdes: CSS 86 · 013 130 + 17 · 012 102 + 18 · 010 99 + 38 · 011 28 · 008 30 + 17 · 007 41 · 006 54
- [X] T032 `git diff --name-only` sin nada bajo `Tema-GeneratePress/`
- [ ] T033 **Fase B manual**: preview más grande · sin mensaje verde permanente (y aviso rojo si falla) · **las variantes sobreviven al ir y volver de ES**
