---
description: "Task list for feature 001-update-migration-guide"
---

# Tasks: Actualizar MIGRATION-GUIDE.md tras el cambio de CDN

**Input**: Design documents from `/specs/001-update-migration-guide/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, quickstart.md

**Tests**: no se incluyen tareas de pruebas automatizadas. El proyecto no tiene
suite, ni CI, ni package.json. La verificación es manual y reproducible contra
producción, y ya está codificada como escenarios en `quickstart.md`. Cada tarea
de abajo indica su comando de verificación.

**Organización**: las tareas siguen el orden del plan: código primero, guide después.
Ese orden existe para que un fallo sea atribuible a una de las dos partes, ya que
la feature mezcla un rename en producción con cambios documentales.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: puede ejecutarse en paralelo (archivos distintos, sin dependencias)
- **[Story]**: user story de la spec a la que pertenece la tarea
- Las rutas son exactas y relativas al repositorio

---

## Phase 1: Code Change (bloquea todo lo demás)

**Purpose**: el rename del módulo. Va primero porque el guide debe reflejar el
nombre nuevo; si se hiciera al revés, el guide describiría un nombre inexistente.

- [X] T001 [US3] Renombrar `inc/cloudflare-optimization.php` a `inc/cdn-cache-bypass.php` con `git mv` para preservar el historial
- [X] T002 [US3] Renombrar la función `mu_cloudflare_bypass_cache()` a `mu_cdn_cache_bypass()` en el archivo movido
- [X] T003 [US3] Actualizar la llamada en `functions.php:352` a `mu_load_module( 'cdn-cache-bypass' )` y corregir su comentario de cola
- [X] T004 [US3] Reescribir el docblock del módulo: describir que emite cabeceras HTTP estándar para cart/checkout/account, y que el nombre ya no referencia ningún proveedor (FR-009)
- [X] T005 [US3] Ejecutar `php -l inc/cdn-cache-bypass.php` y `php -l functions.php` (debe salir sin errores)
- [X] T006 [US3] Verificar que no quedan referencias al nombre anterior: `grep -rn "cloudflare-optimization\|mu_cloudflare" inc/ functions.php` (solo puede quedar la mención histórica del docblock)

**Checkpoint**: el módulo renombrado carga sin error fatal. Un fallo aquí se
revierte sin tocar documentación.

---

## Phase 2: User Story 1 — Un agente no aplica reglas de un proveedor retirado (P1)

**Objetivo**: que ninguna regla vigente del guide dependa de un proveedor que no
se usa. Cubre FR-001 a FR-006.

**Test independiente**: `grep -n -i "cloudflare" MIGRATION-GUIDE.md` no debe
devolver menciones en secciones normativas. Solo se admite la entrada del árbol
de directorios, que T015 corrige.

### Implementación

- [X] T007 [P] [US1] Reescribir el bloque "Caché y CDN" de `MIGRATION-GUIDE.md` §2: preajuste "CACHÉ POR DEFECTO", CDN de Hostinger como única activa, QUIC.cloud con integración activa y CDN desactivada, TLS 1.3, bloqueo por país como lista de permitidos (FR-001, FR-002, FR-003, FR-004)
- [X] T008 [US1] Separar object cache y caché de páginas en la descripción de §2, con la advertencia de que confundirlas lleva a diagnóstico erróneo (FR-001)
- [X] T009 [US1] Eliminar de §2 la línea del Retraso de JS y reemplazarla por una nota que remita al incidente de §7 (FR-005)
- [X] T010 [US1] Sustituir en §2 "Dominio y Seguridad" la regla de bloqueo por DNS de la lista de rechazados por la del edge con lista de permitidos, y duplicar la referencia al nuevo bloque de caché (FR-003)
- [X] T011 [US1] Eliminar de §2 toda mención a la integración de API con el proveedor retirado y a su token (FR-005)
- [X] T012 [US1] Registrar en `MIGRATION-GUIDE.md` §7 el incidente `wp.i18n is not defined` con fecha, síntoma literal, causa raíz (orden de dependencias de `@wordpress/*`), el error de diagnóstico previo (se excluían consumidores, no el proveedor), la mitigación y el aprendizaje sobre qué excluir si se reactiva el JS Delay (FR-006)
- [X] T013 [US1] Registrar en `MIGRATION-GUIDE.md` §7 el incidente de bloqueo de bots en el edge: 429 desde la CDN antes de PHP, y el fast-exit del tema queda como segunda línea de defensa (FR-006)

**Checkpoint**: US1 verificada. `grep -i cloudflare MIGRATION-GUIDE.md` no
devuelve reglas vigentes.

---

## Phase 3: User Story 2 — Un agente puede diagnosticar un fallo de caché (P1)

**Objetivo**: que los comandos de verificación del guide reproduzcan el
comportamiento actual. Cubre FR-006 y SC-008.

**Test independiente**: ejecutar los escenarios 1 y 5 de `quickstart.md`.

### Implementación

- [X] T014 [US2] Añadir en `MIGRATION-GUIDE.md` §2 el bloque "AISLAMIENTO DE CACHÉ ENTRE SUBDOMINIOS": la cookie `_lscache_vary` con dominio `.muyunicos.com` como mecanismo real, los hashes de catálogo que prueban que los subdominios sirven HTML distinto, y la advertencia de que los productos físicos desaparecen por diseño y no es un fallo
- [X] T015 [US2] Corregir en `MIGRATION-GUIDE.md` §4 la entrada del árbol de `cloudflare-optimization.php` al nombre nuevo, y re-describir `compat-litespeed.php` con sus cuatro funciones vigentes (FR-010)
- [X] T016 [US2] Corregir el docblock de `mu_litespeed_vary_by_subdomain()` en `inc/compat-litespeed.php`: documentar que el aislamiento real lo hace la cookie y que el filtro es refuerzo (FR-006)
- [X] T017 [US2] Revisar en `MIGRATION-GUIDE.md` §7 que cada incidente conserve su comando `curl` y que todos apunten a la pila vigente (FR-006)

**Checkpoint**: US2 verificada. Los escenarios 1 y 5 de `quickstart.md` pasan.

---

## Phase 4: User Story 3 — Un agente sabe qué territorio es del plugin y qué del tema (P2)

**Objetivo**: documentar los puntos de acoplamiento reales. Cubre FR-007, FR-008.

**Test independiente**: cada plugin del inventario tiene un `grep` que devuelve
su hook en `inc/`.

### Implementación

- [X] T018 [P] [US3] Añadir en `MIGRATION-GUIDE.md` una sección de plugins acoplados con su punto de integración: Rank Math, Jetpack Search, Hostinger Tools, wpLingua, WCPBC, Nextend y LiteSpeed Cache, cada uno con el hook o filtro exacto y el archivo donde se engancha (FR-007)
- [X] T019 [US3] Verificar cada punto de acoplamiento con `grep` antes de documentarlo, y omitir del inventario cualquier plugin sin resultado (FR-008)
- [X] T020 [US3] Mantener en §3 la lista de pasarelas de pago con una nota que distingue "pasarela configurada" de "plugin acoplado al tema": PayPal y Mercado Pago no tienen acoplamiento de código (FR-008)

**Checkpoint**: US3 verificada. Todo plugin del inventario tiene un `grep` que
lo confirma.

---

## Phase 5: User Story 4 — El mantenedor audita el stack vivo (P2)

**Objetivo**: que §2 responda "¿qué cambió?" sin consultar el panel. Cubre
SC-006.

**Test independiente**: leer §2 de principio a fin y contrastar con el estado
conocido del panel de hosting.

### Implementación

- [X] T021 [US4] Corregir en `MIGRATION-GUIDE.md` §2 la línea de versión de PHP o marcarla como pendiente de verificar: el guide declara 8.3.28 y producción responde 8.5.4
- [X] T022 [P] [US4] Registrar en `MIGRATION-GUIDE.md` §8 los pendientes que la auditoría detectó: versión de PHP sin confirmar, el repositorio de la Calculadora con cambios sin commitear, y la configuración del WAF pendiente en el edge
- [X] T023 [US4] Mover de `MIGRATION-GUIDE.md` §7 a §8 las dos recomendaciones de mitigación del proveedor retirado (desafío a combinaciones de etiquetas y excepción para el rastreador de previews), conservando su criterio técnico y marcándolas como pendientes de configurar (FR-011)

**Checkpoint**: US4 verificada. §2 es un resumen fiel del stack vivo.

---

## Phase 6: User Story 5 — El código que vive fuera del tema está declarado (P2)

**Objetivo**: que el código ejecutado fuera del tema sea auditable. Cubre
FR-013 y FR-015.

**Test independiente**: un agente que busque la Calculadora en `inc/` la
encuentra en el inventario del guide.

### Implementación

- [X] T024 [US5] Añadir en `MIGRATION-GUIDE.md` una sección de código externo con los tres fragmentos, cada uno con propósito, ubicación y fuente, **sin publicar su contenido** (FR-013)
- [X] T025 [US5] Documentar en esa misma sección el riesgo de colisión de nombres: el fragmento de la Calculadora declara `mu_sticker_calculator_shortcode()` con el prefijo del tema y sin guarda `function_exists`, igual que el repositorio externo (FR-015)
- [X] T026 [US5] Documentar en esa sección que el inventario no autoriza el traslado del código, y que documentar no es migrar (FR-013)
- [X] T027 [US5] Añadir en `MIGRATION-GUIDE.md` §8 el pendiente del repositorio de la Calculadora: 7 archivos modificados sin commitear, incluido el bundle que se sirve en producción

**Checkpoint**: US5 verificada. El inventario nombra los tres fragmentos con
su propósito.

---

## Phase 7: User Story 6 — La Calculadora de Stickers es reproducible (P3)

**Objetivo**: que el bundle se pueda regenerar sin consultar el servidor. Cubre
FR-014.

**Test independiente**: seguir las instrucciones de compilación del guide y
comparar el bundle resultante con el que se sirve.

### Implementación

- [X] T028 [US6] Documentar en `MIGRATION-GUIDE.md` que la Calculadora se compila desde un repositorio externo, indicando los comandos `npm run build:js` y `npm run build:css` (FR-014)
- [X] T029 [US6] Documentar el destino de cada artefacto generado dentro del tema: `assets/js/calculadora_stickers.js` y `assets/css/calculadora_stickers.css` (FR-014)
- [X] T030 [US6] Documentar por qué el endpoint `assets/guardar_datos.php` vive dentro de los assets: está accesible por HTTP, así que exige `manage_options` y bootstrap de WordPress (FR-014)
- [X] T031 [US6] Indicar en esa sección que el repositorio externo es la fuente de verdad del bundle, para que nadie edite el archivo generado (FR-014)

**Checkpoint**: US6 verificada. El guide permite reconstruir el bundle desde la
fuente.

---

## Phase 8: Validación y cierre

**Purpose**: comprobar que el guide describe la realidad y que nada se rompió.

- [X] T032 Ejecutar `php -l` sobre los dos archivos PHP tocados: `inc/cdn-cache-bypass.php` y `functions.php`
- [X] T033 Ejecutar el escenario 1 de `quickstart.md`: la caché responde `hit` en raíz y subdominio
- [X] T034 Ejecutar el escenario 2: los catálogos de raíz y subdominio difieren, y la raíz conserva los productos físicos
- [X] T035 Ejecutar el escenario 3: `grep -i "cloudflare" MIGRATION-GUIDE.md` no devuelve reglas vigentes
- [X] T036 Ejecutar el escenario 5: el bot recibe 429 desde el edge y el humano recibe 302
- [X] T037 [P] [US1] Verificar el criterio SC-001: `grep -i "cloudflare" MIGRATION-GUIDE.md` no devuelve reglas vigentes (solo la mención de QUIC.cloud en §2)
- [X] T038 [P] [US1] Verificar el criterio SC-002: el bloque de caché nombra las dos capas, el proveedor CDN activo, el control geográfico y la versión de TLS
- [X] T039 [P] [US3] Verificar el criterio SC-003: `grep -rn "cloudflare-optimization" . --include=*.php --include=*.md` no devuelve resultados
- [ ] T040 [P] [US1] Verificar el criterio SC-004: el total de un carrito de prueba con impuesto por dirección de envío coincide con el que calcula WooCommerce para el mismo `cart_item_key`
- [X] T041 [P] [US1] Verificar el criterio SC-005: cada incidente de §7 tiene un comando de verificación vigente o está marcado como no aplicable con su razón
- [X] T042 [P] [US4] Verificar el criterio SC-006: leer §2 responde "¿qué cambió respecto del documento?" sin consultar el panel
- [X] T043 [P] [US1] Verificar el criterio SC-007: las mitigaciones no configuradas están todas en §8 y ninguna en secciones normativas
- [X] T044 [P] [US2] Verificar el criterio SC-008: la segunda request a una URL cacheada de cada subdominio devuelve `x-litespeed-cache: hit` (escenario 1 de quickstart.md)
- [X] T045 [P] [US5] Verificar el criterio SC-009: el 100% del código ejecutado fuera del tema figura en el inventario con propósito y fuente
- [X] T046 [P] [US6] Verificar el criterio SC-010: el guide permite regenerar el bundle siguiendo sus instrucciones, sin consultar el servidor
- [X] T047 [P] [US5] Verificar el criterio SC-011: un lector del guide puede determinar qué entidades externas comparten prefijo con el tema y qué ocurre si se cargan a la vez
- [X] T048 Revisar que `MIGRATION-GUIDE.md` no contiene ninguna directiva que contradiga la constitución v2.1.0 (FR-012)
- [X] T049 Eliminar el bloque `SYNC IMPACT REPORT` de `.specify/memory/constitution.md` antes de commitear: es material temporal de revisión
- [ ] T050 Confirmar con el mantenedor la versión real de PHP en el panel y corregir el dato en `MIGRATION-GUIDE.md` §2

### Trazabilidad de criterios

Los once Success Criteria de `spec.md` tienen tarea de verificación propia:

| SC | Tarea | Historia |
|---|---|---|
| SC-001 | T037 | US1 |
| SC-002 | T038 | US1 |
| SC-003 | T039 | US3 |
| SC-004 | T040 | US1 |
| SC-005 | T041 | US1 |
| SC-006 | T042 | US4 |
| SC-007 | T043 | US1 |
| SC-008 | T044 | US2 |
| SC-009 | T045 | US5 |
| SC-010 | T046 | US6 |
| SC-011 | T047 | US5 |

Los quince Functional Requirements están referenciados en al menos una tarea:
FR-001 a FR-006 en Phase 2, FR-007 y FR-008 en Phase 4, FR-009 en T004,
FR-010 en T015, FR-011 en T023, FR-012 en T048, FR-013 a FR-015 en Phase 6.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Code Change)**: sin dependencias. Bloquea las fases 2 y 3, porque
  el guide debe reflejar el nombre nuevo del módulo
- **Phase 2 (US1, P1)**: depende de Phase 3 completada para el nombre del módulo,
  pero el resto de §2 puede escribirse en cualquier momento
- **Phase 3 (US2, P1)**: depende de Phase 1
- **Phase 4 (US3, P2)**: sin dependencias. Puede correr en paralelo con Phase 1
- **Phase 5 (US4, P2)**: depende de Phase 2
- **Phase 6 (US5, P2)**: sin dependencias
- **Phase 7 (US6, P3)**: sin dependencias
- **Phase 8 (Validación)**: depende de todas las anteriores

### Paralelismo real

Con un solo mantenedor, las fases no se ejecutan en paralelo. Las marcas `[P]`
identifican tareas que **no comparten archivo** y que podrían solaparse:

- T007-T011, T012-T013 tocan líneas distintas de `MIGRATION-GUIDE.md`, pero
  editan el mismo archivo: secuenciales
- T018-T020 tocan el guide, secuenciales entre sí
- T024-T027 tocan el guide, secuenciales entre sí
- T001-T006 tocan `inc/` y `functions.php`, secuenciales

**Realidad**: este trabajo es de un solo mantenedor y un solo archivo
documental. El paralelismo real existe entre el trabajo en `inc/` (Phase 1) y el
trabajo de investigación ya completado. Las marcas `[P]` se conservan para
trazabilidad, no para ganar tiempo real.

### Orden crítico

1. **Phase 1 completa y verificada** antes de tocar el árbol del guide
2. **Phase 2 antes de Phase 3**: §2 describe la pila antes que §7 narrar los incidentes
3. **Phase 8 al final**: sin validación, el trabajo queda sin cerrar

---

## Notas de ejecución

- **Un solo commit** (decisión del mantenedor). Si el código falla, el guide se
  puede revertir por separado: no tiene efecto en runtime.
- **Sin staging**: toda la verificación ocurre en producción. Los escenarios de
  `quickstart.md` son de lectura, no modifican nada.
- **Orden dentro de `MIGRATION-GUIDE.md`**: §2 antes que §4 antes que §7 antes
  que §8. Editarlos en otro orden genera estados intermedios inconsistentes.
- **T039 es obligatoria**: el `SYNC IMPACT REPORT` de la constitución es
  material temporal de revisión y no debe llegar al commit.
- **T040 requiere acceso al panel**: no se puede resolver desde el repositorio.
