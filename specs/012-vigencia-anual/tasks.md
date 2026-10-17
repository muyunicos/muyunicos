# Tasks: Vigencia anual del banner (repetir cada año)

**Input**: Design documents from `/specs/012-vigencia-anual/`

**Prerequisites**: plan.md, spec.md, quickstart.md

**Tests**: sin framework de pruebas (constitución IX). La verificación son el arnés versionado
(`harness/`) y los pasos del quickstart.

## Format: `[ID] [P?] Description`

- **[P]**: puede ir en paralelo (archivos distintos, sin dependencias)
- `[X]` = ejecutada en esta sesión; `[ ]` = pendiente

---

## Phase 1: Lógica pura

- [X] T001 `inc/hero-banners.php`: `mu_hero_banner_mmdd()` (día/mes de una fecha válida)
- [X] T002 `inc/hero-banners.php`: `mu_hero_banner_anual_efectivo()` (tri-estado propio/heredado)
- [X] T003 `inc/hero-banners.php`: `mu_hero_banner_ventana_anual()` — cruce de fin de año, candidatas, *clamp* de 29/02, fin inclusivo DST-safe
- [X] T004 `inc/hero-banners.php`: `mu_hero_banner_ventana_instantes( $promo, $pais, $now = null )` delega en T003; `mu_hero_banner_is_active_for_pais()` propaga su `$now`
- [X] T005 `inc/hero-banners.php`: `mu_hero_banner_avisos()` suma `anuales_incompletas`

## Phase 2: Saneado y datos

- [X] T006 `inc/hero-banners.php`: saneado del flag general (`'1'`/`'0'`) y por país (sólo si difiere), sin perder el país cuyo único dato propio es el flag
- [X] T007 `inc/hero-banners.php`: docblock del módulo (Version 1.2.0) con la convención de `anual`

## Phase 3: Panel (marcado + JS + CSS)

- [X] T008 `inc/hero-banners.php`: checkbox «Se repite cada año» en el panel general y en el de cada país (valor efectivo) + aviso `anuales_incompletas` en el bloque de alertas de la fila
- [X] T009 `inc/hero-banners.php`: el hint M6 muestra la instancia con año real y aclara «y se repite cada año»
- [X] T010 [P] `js/admin-hero-banners.js`: `anualIncompleta()`/`anualDeFila()`/`anualPropio()` puras o de estado, el aviso del panel para «anual + una sola fecha», el punto de la píldora contando el flag y la sincronización con la casilla de Global
- [X] T011 [P] `css/admin-hero-banners.css`: estilo del checkbox del flag dentro del panel de fechas

## Phase 4: Purga en el borde

- [X] T012 `inc/hero-banners.php`: `mu_hero_banner_proximo_borde_global()`
- [X] T013 `inc/hero-banners.php`: `mu_hero_banner_programar_purga_borde()` (evento único con guard) + `mu_hero_banner_purge_borde_ejecutar()` (purga por host con respaldo + `[MU-HERO-BORDE]` + reprogramación)
- [X] T014 `inc/hero-banners.php`: enganche del handler y reprogramación al guardar (`mu_hero_banner_reprogramar_purga_borde()`)

## Phase 5: Verificación automática

- [X] T015 [P] `specs/012-vigencia-anual/harness/arnes-anual.php` — 87 OK / 0 FAIL
- [X] T016 [P] `specs/012-vigencia-anual/harness/anual-js.js` — 18 OK / 0 FAIL
- [X] T017 `specs/010-…/harness/arnes-fechas-por-pais.php`: ajuste declarado de la aserción exacta de avisos
- [X] T018 No-regresión: arnés 010 (96), arnés JS 010 (28), 011 (18), 006 (54), 008 (30 + 17) y `php -l` / `node --check`

## Phase 6: Documentación

- [X] T019 [P] `specs/012-vigencia-anual/`: spec.md, plan.md, tasks.md, quickstart.md
- [X] T020 `MIGRATION-GUIDE.md`: §2 (TTL de páginas medido: 24 h), §4 (árbol/comentario del módulo), §8 (cierre del pendiente de purga en el borde + registro de 012)

## Phase 7: Verificación manual en producción (mantenedor)

- [ ] T021 Fase B del quickstart: banner anual en el panel (checkbox, hints, avisos) y comportamiento del front en el borde
- [ ] T022 Declarar el resultado en el PR: sin T021 la feature se marca NO VERIFICADA
