# Tasks: Alcance por país de rutas y elementos

**Input**: Design documents from `/specs/004-country-scope/`

**Prerequisites**: plan.md, spec.md, research.md, contracts/country-scope.md, quickstart.md

**Tests**: no se generan tareas de pruebas automatizadas: la constitución IX prohíbe añadir
suite, CI o gestor de paquetes a este proyecto. Las tareas de verificación son los pasos de
`quickstart.md` y son obligatorias.

**Organization**: tareas agrupadas por historia de usuario. `[X]` = ejecutada y medida en
esta sesión; `[ ]` = pendiente, con su dependencia indicada.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: puede ir en paralelo (archivos distintos, sin dependencias)
- **[Story]**: US1 (el comprador no recibe la página), US2 (declaración reutilizable),
  US3 (SEO)

## Convenciones de este repositorio

- Rutas relativas a la raíz del tema hijo: `inc/`, `functions.php`, `MIGRATION-GUIDE.md`
- Tema padre `Tema-GeneratePress/`: **no se toca** (principio X)
- Documentación de la feature en `specs/004-country-scope/`

---

## Phase 1: Setup — baseline medido (2026-10-16)

- [X] T001 Medir el estado "antes" en producción: `curl.exe -sI https://{mexico,co,br,us}.muyunicos.com/envios/` y `br…/pt/envios/` → los cinco devuelven `200 OK` con el mismo post (`Link: …/pages/20036`)
- [X] T002 [P] Medir el pie de página: `curl.exe -s https://mexico.muyunicos.com/ | Select-String 'mu-footer-links' -Context 0,8` → el enlace `/envios/` aparece en el subdominio
- [X] T003 [P] Medir el SEO actual: 11 alternates del tema + 4 de wpLingua, `canonical` self, `robots: index` y `/envios/` listada en `page-sitemap.xml` del subdominio
- [X] T004 [P] Confirmar el destino del redirect: `https://mexico.muyunicos.com/tienda/`, `https://br.muyunicos.com/pt/tienda/` y `https://muyunicos.com/tienda/` → `200` en las tres

## Phase 2: Foundational — artefactos de diseño

- [X] T005 [P] `specs/004-country-scope/spec.md`: problema medido, 3 historias con prioridad, criterios de aceptación, FR-001 a FR-015, SC-001 a SC-007, supuestos y fuera de alcance
- [X] T006 [P] `specs/004-country-scope/research.md`: 10 decisiones con evidencia y alternativas descartadas (wp-admin, plugin, `.htaccess`, CSS; país = host; `302` vs `404`/`410`; prioridad del guard; detección de ruta; hreflang; sitemap; módulo nuevo)
- [X] T007 [P] `specs/004-country-scope/contracts/country-scope.md`: contrato de la API reutilizable, contrato del guard HTTP y los tres patrones de restricción por país
- [X] T008 `specs/004-country-scope/plan.md`: contexto técnico, Constitution Check principio por principio y estructura de archivos

---

## Phase 3: User Story 1 — El comprador de otro país no recibe la página ni su enlace (P1)

- [X] T009 [US1] Crear `inc/country-scope.php` con el contexto de país (`muyu_get_current_country()`, `muyu_get_current_host()`, `muyu_is_country()`), reutilizando la fuente de `inc/geo.php`
- [X] T010 [US1] Implementar el registro `muyu_get_country_scope_routes()` con la entrada `envios` (`countries: ['AR']`, `redirect: 'shop'`) y el filtro de extensión `muyu_country_scope_routes`
- [X] T011 [US1] Implementar `muyu_get_scoped_request_path()` (URI original de `mu_wplingua_original_uri()`, sin query y sin prefijo de idioma) y `muyu_get_country_scope_route_for_request()` (`is_page()` primero, ruta normalizada como respaldo)
- [X] T012 [US1] Implementar `muyu_get_country_scope_redirect_url()` (mismo host, prefijo de idioma con `muyu_clean_uri()`) y `mu_country_scope_guard()` en `template_redirect` p15, con bypass de admin/REST/AJAX/cron/feed y cabecera `X-MU-Country-Scope`
- [X] T013 [US1] Registrar el módulo en `functions.php` (tras `compat-wplingua`) con su comentario de dependencias
- [X] T014 [US1] Condicionar el `<li>` de `/envios/` en `inc/ui.php` con el registro y `muyu_is_country()`
- [X] T015 [US1] `php -l` de los cuatro archivos PHP tocados → sin errores
- [X] T016 [US1] Verificar la lógica y el guard con el arnés de stubs (`quickstart.md` §A.6): 11 escenarios, incluidos `/tienda/` sin efecto y el prefijo `/pt/` sin duplicar

---

## Phase 4: User Story 2 — Declaración reutilizable por país (P2)

- [X] T017 [US2] Documentar en el docblock del módulo las tres formas de restringir por país y sus límites
- [X] T018 [US2] Exponer `muyu_get_country_scope_route()` / `muyu_get_country_scope_routes()` para que el enlace consuma el mismo registro que la ruta
- [X] T019 [US2] Documentar la API en `specs/004-country-scope/contracts/country-scope.md`, incluido el procedimiento de alta de una ruta nueva

## Phase 5: User Story 3 — La página deja de indexarse donde no existe (P3)

- [X] T020 [US3] Implementar `muyu_get_country_scope_hreflang_tags()` (una entrada autorreferencial, sin `x-default` propio)
- [X] T021 [US3] Consumir el conjunto reducido en `inc/seo-hreflang.php` con `return` temprano
- [X] T022 [US3] Implementar `mu_country_scope_exclude_from_sitemap()` (filtro `rank_math/sitemap/entry`: el argumento es un array `[ 'loc' => … ]` y se devuelve `[]` para excluir — corregido tras la medición fallida en producción del 2026-10-16)
- [X] T023 [US3] Verificar el contrato del hook contra el código del plugin instalado (seo-by-rank-math 1.0.280, `sitemap/providers/class-post-type.php:222`), documentarlo en el docblock y en §8 del guide, incluida la trampa del `is_string()` que desactivaba la exclusión en silencio

---

## Phase 6: Polish y verificación pendiente

- [X] T024 [P] `MIGRATION-GUIDE.md` §3: regla de negocio de las rutas por país y su registro
- [X] T025 [P] `MIGRATION-GUIDE.md` §4: módulo `country-scope.php` en el mapa de directorios (y `compat-wplingua.php`, que faltaba)
- [X] T026 [P] `MIGRATION-GUIDE.md` §6: API de alcance por país y los tres patrones de uso
- [X] T027 [P] `MIGRATION-GUIDE.md` §7: entrada de diagnóstico con el orden de comprobación y los dos fallos posibles
- [X] T028 [P] `MIGRATION-GUIDE.md` §8: huecos declarados (filtro de sitemap, candidatas al registro, variantes de idioma)
- [X] T029 `git diff --name-only` sin nada bajo `Tema-GeneratePress/` y sin el arnés de `%TEMP%` en el repo
- [ ] T030 [US1] **Producción** (`quickstart.md` §B.0 y §B.1): purga de caché y matriz de `curl` en los 10 hosts — depende del mantenedor (deploy + purga)
- [ ] T031 [US1] **Producción** (`quickstart.md` §B.2): pie de página en dos hosts y control manual del menú en wp-admin
- [ ] T032 [US3] **Producción** (`quickstart.md` §B.3): `hreflang`/`canonical`/`robots` en Argentina
- [ ] T033 [US1] **Producción** (`quickstart.md` §B.5): no-regresión en tienda, blog, categoría y producto de dos hosts
- [ ] T034 [US3] **Producción** (`quickstart.md` §B.4): remedir el sitemap del subdominio tras el deploy de la corrección del callback (la primera medición falló: ver §8 del guide)

**Hasta ejecutar T030 a T034, la feature se declara NO VERIFICADA EN PRODUCCIÓN.**

