# Tasks: Panel del banner — render compartido + lienzo vivo

**Input**: Design documents from `/specs/013-panel-hero-lienzo/`

**Prerequisites**: spec.md, quickstart.md

**Tests**: sin framework de pruebas (constitución IX). La verificación es el arnés versionado
(`harness/`) más los pasos del quickstart.

## Format: `[ID] [P?] Description`

- **[P]**: puede ir en paralelo (archivos distintos, sin dependencias)
- `[X]` = ejecutada en esta sesión; `[ ]` = pendiente

---

## Fase 0 — Render compartido (commiteable y desplegable sola)

- [X] T001 Capturar el fixture de producción con `curl` **antes** de tocar código (`harness/fixtures/hero-slide-produccion-ar.html`)
- [X] T002 `inc/hero-banners.php`: `mu_hero_slide_render( $promo, $lang, $args )` — marcado, orden de atributos, escape y defaults del badge movidos tal cual
- [X] T003 `inc/ui.php`: `mu_hero_section()` usa el renderer; se retiran el helper `$mu_hero_field` y `$badge_defaults` inline
- [X] T004 `css/components/hero-promo.css`: mover el bloque 186-486 de `css/home.css` (301 líneas) sin cambio de reglas + encolarlo en `mu_home_sections_enqueue()` con `filemtime`
- [X] T005 `harness/arnes-render-hero.php`: igualdad normalizada contra el fixture, byte a byte en los nodos con contenido, marcadores de escape por campo, `first`/badge/idioma y casos defensivos — **37 OK / 0 FAIL**
- [X] T006 No-regresión: arneses 006 (54), 008 (30 + 17), 010 (96 + 28), 011 (18), 012 (87 + 18) y `php -l`/`node --check`
- [X] T007 `spec.md` + `quickstart.md` + `tasks.md` + `MIGRATION-GUIDE.md` (§4/§5/§8)
- [ ] T008 Fase B en producción (mantenedor): hero idéntico en `muyunicos.com` + 2 subdominios y `hero-promo.css` servido
- [ ] T009 Declarar el resultado: sin T008 la Fase 0 se marca NO VERIFICADA en producción

## Fase 2 — Lienzo (implementada ANTES de la Fase 1: es aditiva y así el panel nunca queda roto a mitad de camino)

- [X] T015 `inc/hero-banners.php`: el lienzo renderiza `mu_hero_slide_render( …, [ 'mu' => true ] )` con los datos de la fila (PHP; funciona sin JS)
- [X] T016 `data-mu="campo"` en los 10 nodos + `pintarLienzo()` en `js/admin-hero-banners.js` (idioma activo con respaldo al español, visibilidad del badge/CTA secundario/eyebrow; sin generar HTML en JS y sin requests)
- [X] T017 CSS del lienzo: tokens del sistema de diseño y botones con alcance al lienzo, `container-type: inline-size`, `4cqw` en lugar de `4vw`, `@container (max-width: 968px)` para el modo móvil, `transform: scale()` (JS) y alto calculado
- [X] T018 Encolar `css/components/hero-promo.css` y el handle `generate-google-fonts` en la pantalla del panel + selector Escritorio/Móvil
- [X] T019 **N1**: clic-para-foco con resaltado del campo y nombre del nodo al pasar el mouse; los CTA no navegan desde el editor
- [ ] T019b Verificación manual del lienzo en el panel (alcance, tipografía, sincronización, móvil, clic-para-foco): Fase B del quickstart

## Fase 1 — Jerarquía de la fila (implementada en parte)

- [X] T010 Fila-resumen con **estado calculado**: `mu_hero_banner_estado()` (pura, con arnés) resume en una frase dónde se ve, dónde quedó fuera de ventana y cuándo cambia; más miniatura del banner.
- [X] T013 Plegado por fila (`is-folded` + `aria-expanded`) y las acciones de la fila: **Duplicar** (renumera todos los `name="mu_banner[N]"` del clon), **Ordenar ▲▼** (el orden del DOM es el orden de los slides) y **Eliminar con deshacer** (el mismo botón vuelve atrás y deja los valores intactos).
- [X] T014 Arnés de marcado del resumen + 19 aserciones nuevas del estado (total 013 PHP: **82 OK**).
- [ ] T011 **Chips de país unificados** (activar + elegir ámbito con el mismo control, retirando el dropdown «Países»): PENDIENTE.
- [ ] T012 **Vigencia como tabla** comparable (Global + países a la vez, subiendo al primer lugar): PENDIENTE.

### Desviaciones declaradas de la Fase 1

- Las **píldoras de idioma** y el botón **Traducir** se quedan en la barra de la fila (no dentro de una sección «Contenido»): son herramientas de fila que también gobiernan el idioma del lienzo, y moverlas a una sección las escondería al plegar.
- **Sin plegado automático**: las filas nacen desplegadas. El criterio de la spec («plegada por defecto si hay más de un banner») se descarta a propósito: plegar por defecto esconde justamente el lienzo, que es el objetivo de la feature. Se pliega lo que no se está usando.

## Fase 2 — Lienzo

- [ ] T015 `inc/hero-banners.php`: el lienzo renderiza `mu_hero_slide_render()` con los datos de la fila (PHP; funciona sin JS)
- [ ] T016 `data-mu="campo"` en los nodos del lienzo + patch por nodos en `js/admin-hero-banners.js` (idioma activo, sin generar HTML en JS)
- [ ] T017 CSS del lienzo: `container-type: inline-size`, `4cqw` en lugar de `4vw`, modificador de modo móvil, `transform: scale()` por JS y `position: sticky`
- [ ] T018 Encolar `css/components/hero-promo.css` en la pantalla del panel + selector Escritorio/Móvil

## Fase 3 — Interacción (según las decisiones abiertas de la spec)

- [ ] T019 N1: click-para-foco con resaltado del campo y badges de descubrimiento
- [ ] T020 N2: `contenteditable="plaintext-only"` en eyebrow, descripción, textos de CTA y badge (dos vías con el input)
- [ ] T021 N2-bis (opcional): edición del título en el lienzo con «resaltar selección»

## Fase 4 — Cierre

- [ ] T022 Arnés JS de las funciones puras nuevas + no-regresión completa
- [ ] T023 `MIGRATION-GUIDE.md` §5/§8 y quickstart actualizado con la verificación manual del lienzo
- [ ] T024 (aparte, opcional) `fix/`: `.mu-btn-outline` del CTA secundario del hero, definido donde corresponde