# Tasks: Variantes de traducción (personas) en el editor de banners

**Input**: Design documents from `/specs/011-variantes-banners-ia/`

**Prerequisites**: plan.md (ver `specs/006-ai-translate-core/plan.md`), spec.md, quickstart.md

**Tests**: sin framework de pruebas (constitución IX). La verificación son el arnés versionado
(`harness/`) y los pasos del quickstart.

## Format: `[ID] [P?] Description`

- **[P]**: puede ir en paralelo (archivos distintos, sin dependencias)
- `[X]` = ejecutada en esta sesión; `[ ]` = pendiente

---

## Phase 1: UI y lógica

- [X] T001 `inc/hero-banners.php`: contenedor `.mu-ai-variants` (`data-role="ai-variants"`) en la cabecera de la fila, al lado del botón «Traducir» (componente compartido con reseñas)
- [X] T002 `js/admin-hero-banners.js`: `variantesDeFila()` (función pura: textos de la variante pedida por campo + faltantes) y `mensajeVariante()` (función pura del estado)
- [X] T003 `js/admin-hero-banners.js`: `pintarVariantes()` (botones numerados con la persona en el `title` y el ticket activo en el 1), `aplicarVariante()` (aplica el índice a los 6 campos del idioma activo + refresca la fila) y `limpiarVariantes()`
- [X] T004 `js/admin-hero-banners.js`: el botón «Traducir» ya no pide un número fijo de variantes; manda `force=1` cuando ya hay tanda para ese idioma y pinta los botones con la respuesta
- [X] T005 `js/admin-hero-banners.js`: al cambiar de idioma se descarta la tanda; handler delegado del clic en los botones; funciones puras expuestas en `window.muHeroBanners`
- [X] T006 [P] `css/admin-hero-banners.css`: separación de los botones en la cabecera (el aspecto vive en el componente compartido)

## Phase 2: Verificación automática

- [X] T007 [P] `specs/011-variantes-banners-ia/harness/variantes-js.js` — 18 OK / 0 FAIL
- [X] T008 No-regresión: arnés 010 (96 OK), arnés JS 010 (28 OK), arnés 008 (30 OK + 17 OK) y `php -l` / `node --check` limpios
- [X] T009 Invariante de marcado: el arnés 010 verifica que la fila trae un solo `data-role="ai-variants"`

## Phase 3: Documentación

- [X] T010 [P] `specs/011-variantes-banners-ia/spec.md` y `quickstart.md`
- [X] T011 `MIGRATION-GUIDE.md`: §6 (consumidores del servicio de IA) y §8 (deuda de verificación del panel)

## Phase 4: Verificación manual en producción (mantenedor)

- [ ] T012 Cargar personas en el ámbito `hero_banner` (WC → Marketing → Traducciones IA) y probar los botones en un banner (Fase B del quickstart)
- [ ] T013 Declarar el resultado en el PR: sin T012 la feature se marca NO VERIFICADA
