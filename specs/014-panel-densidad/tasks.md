# Tasks: Densidad del panel de banners (014)

**Input**: `specs/014-panel-densidad/spec.md` · **Verificación**: sin framework (constitución IX), arneses + quickstart.

## Format: `[ID] [P?] Description`

- `[X]` = ejecutada; `[ ]` = pendiente

---

## Fase 1 · Lógica pura

- [X] T001 `mu_hero_banner_excepciones( $promo )`: países con ventana propia (espejo del saneado), filtrados por país válido, sin entradas vacías, en orden canónico
- [X] T002 `mu_hero_banner_pais_es_excepcion( $promo, $code )`
- [X] T003 `mu_hero_banner_estado()`: cuarto caso `is-sin-paises` (dato, no fecha) sin romper los tres previos
- [X] T004 Arnés 013 sección 7 (14 aserciones nuevas) — y una aserción previa migrada al estado nuevo

## Fase 2 · Estructura

- [X] T005 `render_row( $index, $b, $colapsada = false )` + `is-folded` en el render de la lista cuando hay más de un banner
- [X] T006 Acciones en menú `⋯` (Duplicar visible; Subir · Bajar · Eliminar adentro)
- [X] T007 Chips: sólo los tildados + selector `+N` (mismo checkbox, un lugar por país) con `$chip_markup()` reutilizado
- [X] T008 JS: `sincronizarChips` mueve el chip entre el resumen y el selector y actualiza `+N`
- [ ] T009 Contenido agrupado (Texto · Botones · Imagen) **en el orden del banner**
- [ ] T010 El badge en un solo lugar (casilla junto a sus campos) y «Identidad» → Avanzado plegado

## Fase 3 · Vigencia por excepción

- [X] T011 `$render_fila_vigencia()` marca cada fila con `is-excepcion` / `is-hereda` y `data-es-excepcion` (Global nunca se oculta: bug detectado y corregido en el camino)
- [X] T012 Fila de acciones en la tabla: contador (*«la ventana general la usan N de M países»*), `+ excepción por país` y `ver todos los países`
- [X] T013 JS: `mostrarExcepcion()` / `ocultarExcepcion()`, handler de `+ excepción` (promueve y enfoca Inicio), `ver todos` (clase en la tabla) y `×` que devuelve a la herencia
- [X] T014 CSS: `is-hereda` oculto salvo `is-ver-todos`; estilo de la fila de acciones y de los selectores

## Fase 4 · Cierre

- [ ] T015 Lienzo pegajoso en dos columnas + hint en una línea (densidad visual)
- [ ] T016 Huso del sitio legible en la fila Global (`-03:00` → «huso del sitio»)
- [ ] T017 Arnés: aserciones de marcado de los tramos 1/2/4/7 (hoy cubiertos por las secciones 8 y 9 de 013) y no-regresión completa
- [X] T018 `spec.md` + `tasks.md` + `quickstart.md` + `MIGRATION-GUIDE.md` §8
- [ ] T019 Fase B manual (mantenedor): plegado, chips que se mueven, excepción creada/borrada, «ver todos», menú ⋯ y el estado rojo de «sin países»
