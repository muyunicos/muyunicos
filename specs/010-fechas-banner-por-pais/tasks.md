# Tasks: Fechas por país en Hero Banners

**Input**: Design documents from `/specs/010-fechas-banner-por-pais/`

**Prerequisites**: plan.md, spec.md, quickstart.md

**Tests**: no se generan tareas de prueba de framework: la constitución IX prohíbe añadir suite, CI
o gestor de paquetes a este proyecto. Las tareas de verificación son los arneses versionados
(`harness/`) y los pasos de `quickstart.md`, y son obligatorias.

**Organization**: `[X]` = ejecutada en esta sesión; `[ ]` = pendiente (verificación manual en
producción, exclusiva del mantenedor).

## Format: `[ID] [P?] Description`

- **[P]**: puede ir en paralelo (archivos distintos, sin dependencias)

## Convenciones de este repositorio

- Rutas relativas a la raíz del tema hijo: `inc/`, `css/`, `js/`, `MIGRATION-GUIDE.md`
- Tema padre `Tema-GeneratePress/`: **no se toca** (principio X)
- Documentación de la feature en `specs/010-fechas-banner-por-pais/`

---

## Phase 1: Datos y lógica pura (front)

- [X] T001 `inc/hero-banners.php`: saneado de `fechas[PAIS]` en `mu_hero_banners_sanitize_one()` (sólo países tildados, `\d{8}`, vacío si es idéntico a la general del mismo campo, entrada descartada si ambos quedan vacíos)
- [X] T002 `inc/hero-banners.php`: `mu_hero_banner_pais_fechas()` — ventana efectiva con herencia campo por campo
- [X] T003 `inc/hero-banners.php`: `mu_hero_banner_is_active_for_pais()` — evaluación de la ventana con timestamp inyectable (sin fechas = siempre; inicio 00:00:00 y fin 23:59:59 inclusive; rango invertido = nunca)
- [X] T004 `inc/hero-banners.php`: `mu_get_hero_banners()` usa T003 y pierde el bloque de fechas inline (la clave del transient por país no cambia)
- [X] T005 `inc/hero-banners.php`: defaults de fila con `'fechas' => []` y docblock del módulo a `Version: 1.1.0`

## Phase 2: Panel de administración

- [X] T006 [P] `inc/hero-banners.php`: píldoras de país (`Global` + países tildados) en el bloque de ajustes generales, con `data-pais`, `is-active` y `data-has-fechas`; se retiran las píldoras decorativas de la cabecera
- [X] T007 [P] `inc/hero-banners.php`: paneles de fecha por país (`mu_banner[i][fechas][PAIS][inicio|fin]`) ocultos, con el valor general como placeholder y aviso de rango invertido
- [X] T008 `inc/hero-banners.php`: texto de ayuda de la pantalla y cadena localizada `fechasMalas` para `muHeroBannersData`
- [X] T009 `js/admin-hero-banners.js`: helpers de fechas (`parseDdmmaaaa`, `rangoInvertido`, `setPaisFechas`, `actualizarPuntoFechas`, `sincronizarPillsPais`, `aplicarPlaceholdersFechas`, `avisarRangoInvertido`)
- [X] T010 `js/admin-hero-banners.js`: handlers de píldora y de inputs de fecha; el `change` de países deja de generar HTML y sólo alterna visibilidad; `refrescarFila()` extendido
- [X] T011 [P] `css/admin-hero-banners.css`: píldora interactiva (`is-selectable`, `is-active`, punto `data-has-fechas`), grilla de fechas, avisos, título en flex y mobile

## Phase 3: Verificación automática (local)

- [X] T012 [P] `specs/010-fechas-banner-por-pais/harness/arnes-fechas-por-pais.php` — módulo real con WordPress simulado: 96 OK / 0 FAIL (marcado de la fila del panel + integración del filtro del front + huso/DST + validación + avisos + TTL adaptativo)
- [X] T013 [P] `specs/010-fechas-banner-por-pais/harness/fechas-js.js` — funciones puras del JS: 28 OK / 0 FAIL
- [X] T014 No-regresión de la feature 008: `php specs\008-banners-pildoras-ia\harness\arnes-banners.php` → 30 OK / 0 FAIL; `node specs\008-banners-pildoras-ia\harness\banners-js.js` → 17 OK / 0 FAIL
- [X] T015 `php -l inc/hero-banners.php` y `node --check js/admin-hero-banners.js` sin errores; `git diff --name-only` sin archivos bajo `Tema-GeneratePress/`

## Phase 4: Documentación

- [X] T016 [P] `specs/010-fechas-banner-por-pais/spec.md` y `plan.md`
- [X] T017 [P] `specs/010-fechas-banner-por-pais/quickstart.md` (verificación + reversión)
- [X] T018 `MIGRATION-GUIDE.md`: §4 (comentario del módulo) y §8 (deuda de verificación manual del panel)

## Phase 3.b: Mejoras posteriores (M1–M7, 2026-10-17)

- [X] T019 `inc/geo.php`: clave `tz` por país en `muyu_get_countries_data()` (huso del destino para cualquier fecha local)
- [X] T020 `inc/hero-banners.php`: `mu_hero_banner_fecha_valida()` (checkdate) en el saneado y en el front
- [X] T021 `inc/hero-banners.php`: `mu_hero_banner_pais_tz()` + `mu_hero_banner_ventana_instantes()` (ventana en el huso del país, fin = inicio del día siguiente − 1 s)
- [X] T022 `inc/hero-banners.php`: `mu_hero_banner_proximo_borde()` + TTL del transient acortado hasta el próximo borde
- [X] T023 `inc/hero-banners.php`: `mu_hero_banner_avisos()` y aviso en la fila (fechas inválidas y rangos invertidos, con el ámbito)
- [X] T024 `inc/hero-banners.php`: las fechas de países válidos no tildados ya no se descartan (destildar y volver a tildar no borra la ventana)
- [X] T025 `inc/hero-banners.php` + `inc/ai-translate-admin.php`: huso y ventana efectiva a la vista en cada panel de país; el panel general aclara que la fecha es medianoche local
- [X] T026 `js/admin-hero-banners.js`: validación de fecha real (bisiesto incluido) para el aviso del panel
- [X] T027 `css/admin-hero-banners.css`: estilo del aviso de la fila
- [X] T028 (`specs/006-ai-translate-core`) tope de textos por acción por ámbito: `mu_ai_ambito_per_action()` + campo en la pantalla del servicio + 9 aserciones nuevas (54 OK / 0 FAIL)
- [X] T029 Arnés 010: secciones 7 a 10 (validación, huso/DST, próximo borde y TTL, avisos) — 96 OK / 0 FAIL

## Phase 5: Verificación manual en producción (mantenedor)

- [ ] T019 Fase B de `quickstart.md`: píldoras, puntos, placeholders, guardado y ventanas distintas por subdominio (con purga previa de caché de cada host)
- [ ] T020 Declarar el resultado en el PR: la feature se marca NO VERIFICADA mientras T019 no se ejecute
