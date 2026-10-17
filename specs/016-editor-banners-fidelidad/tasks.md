# Tasks: Editor de banners — fidelidad del lienzo y densidad (016)

**Input**: `specs/016-editor-banners-fidelidad/spec.md`

## Format: `[ID] Description` — `[X]` = ejecutada · `[ ]` = pendiente

## Tramo 1 · Fidelidad

- [X] T001 `js/admin-hero-banners.js`: helper puro `tieneContenidoPropio(valor, base)` (`trim()` en ambos lados) y uso en `actualizarPunto`; expuesto en `window.muHeroBanners`
- [X] T002 `specs/010-…/harness/fechas-js.js`: 10 aserciones nuevas (vacío · igual · distinto · español vacío · espacios · `undefined`/`null`) — el arnés queda en **38 OK**
- [X] T003 `css/components/hero-promo.css`: las 3 variables locales del hero pasan a `.mu-hero-promo-slider` (raíz compartida); verificado que el front no cambia
- [X] T004 `css/admin-hero-banners.css`: estados de botón del lienzo (`:focus-visible`, `.mu-btn-primary:hover`, `.mu-btn-outline:hover`)
- [X] T005 `css/admin-hero-banners.css`: espejo de escritorio `@container (min-width: 969px)` con los valores del componente
- [X] T006 `css/admin-hero-banners.css`: la caja del bloque de idioma aplica a los tres idiomas
- [X] T007 `specs/015-…/harness/css-check.js`: 12 aserciones de fidelidad + validación contra el CSS anterior (`d6db174`) donde fallan
- [X] T008 *(declarado)* tipografía display del panel: depende del handle del tema padre; si el mantenedor ve otra fuente, registrar el mismo handle en la pantalla del panel

## Tramo 2 · Densidad

- [X] T010 `css/admin-hero-banners.css`: fuera `grid-row: 1 / span 30` y el `sticky`; dos filas explícitas: idioma (≤420) + lienzo (≤400) arriba, ajustes/vigencia a todo el ancho abajo
- [X] T011 `css/admin-hero-banners.css`: `minmax(320px, …)` para que encoja antes de desbordar + `.mu-hero-banner-tabla-scroll { overflow-x: auto }`
- [X] T012 `inc/hero-banners.php`: envolver la tabla de vigencia en `.mu-hero-banner-tabla-scroll`
- [X] T013 `inc/hero-banners.php`: «Próximo borde: …» (o «Sin borde agendado…») junto a la última purga
- [X] T014 `specs/015-…/harness/css-check.js`: la invariante «lienzo pegajoso» pasa a «lienzo al lado del idioma, sin filas infinitas» + ancho completo de ajustes + scroll de la tabla

## Verificación

- [X] T020 `php -l inc/hero-banners.php` y `node --check js/admin-hero-banners.js`
- [X] T021 Los 11 arneses verdes (CSS **82**, 013 **118+17**, 012 **102+18**, 010 **99+38**, 011 **18**, 008 **30+17**, 006 **54**)
- [X] T022 `git diff --name-only` sin nada bajo `Tema-GeneratePress/`
- [ ] T023 **Fase B manual** (mantenedor): píldora sin punto · preview con eyebrow rosa, hover y sin móvil fantasma · layout sin hueco · los tres bloques con caja · «Próximo borde» visible
- [ ] T024 Documentar en `MIGRATION-GUIDE.md` §8 (hecho junto con la spec) y actualizar los quickstarts de 013/014 con el nuevo layout
