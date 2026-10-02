# Tasks: Correcciones y mejoras de la interfaz en el móvil

**Input**: Design documents from `/specs/003-mobile-ui-fixes/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/ui-states.md, quickstart.md

**Tests**: No se generan tareas de pruebas automatizadas. La constitución IX prohíbe
añadir suite de pruebas a este proyecto. En su lugar, las tareas de **verificación**
de cada historia son los pasos de `quickstart.md`, y son obligatorias.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3, US4)
- Include exact file paths in descriptions

## Convenciones de este repositorio

- Rutas relativas a la raíz del tema hijo: `css/`, `js/`, `inc/`, `style.css`, `functions.php`
- Es un tema **hijo** de WordPress: no hay `src/`, ni `tests/`, ni gestor de paquetes
- `Tema-GeneratePress/` es copia del tema parent y **no se toca** (principio X, FR-033)
- El versionado de assets sale de `Version:` en `style.css`; no se inventan query strings

---

## Phase 1: Setup (Baseline antes de tocar nada)

**Purpose**: Capturar el estado "antes" de producción. Sin baseline no hay forma de
demostrar que nada se rompió, y el principio IX exige verificación reproducible.

- [X] T001 Ejecutar `curl -sI https://ec.muyunicos.com/mi-cuenta/` y registrar en el PR la salida de las cabeceras `cache-control` y `x-litespeed`. Valor esperado: `no-cache, no-store` y `X-LiteSpeed-Cache-Control: no-cache`
- [X] T002 [P] Ejecutar `curl -sI` sobre `https://ec.muyunicos.com/mi-cuenta/` y `https://br.muyunicos.com/mi-cuenta/` y registrar en el PR todas las cabeceras `link:` (canonical y hreflang) como baseline de SEO
- [X] T003 [P] Ejecutar `curl -s https://ec.muyunicos.com/ | grep -o 'aria-haspopup="true"\|aria-expanded="false"\|role="button"'` y registrar la salida: son atributos que el contrato C-1.6 declara intocables y no deben cambiar
- [X] T004 [P] Capturar screenshots "antes" a 1024px o más de: la lista de países abierta por hover, el submenú de Mi Cuenta abierto, el botón de WhatsApp, la pantalla Mi Cuenta y la pantalla Detalles de la cuenta. Son la comparación de no-regresión de FR-032 y SC-014
- [X] T005 [P] Ejecutar `php -l inc/ui.php` y `php -l functions.php`, registrar salida limpia, y anotar en el PR que el PHP local es 8.5.9 mientras producción corre 8.5.4

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Verificaciones que deben pasar antes de abrir cualquier historia.

**Nota de honestidad sobre esta fase**: es deliberadamente corta. Esta feature no
tiene capa de datos, ni esquema, ni API, ni autenticación, ni pipeline: son
correcciones de presentación sobre un tema que ya existe. No hay infraestructura
compartida que construir. Lo único que bloquea a todas las historias es no partir de
un árbol de trabajo sucio.

- [X] T006 Confirmar que la rama activa es `fix/mobile-ui` y que `git status --short` devuelve salida vacía antes de modificar ningún archivo
- [X] T007 Leer `contracts/ui-states.md` completo y tener presentes los identificadores del contrato: la clase `is-open` (C-1.2), la clase `active` que **no** se renombra (C-2.1) y las dos variables de WhatsApp (C-3.8). Estos nombres están fijados por contrato y no se improvisan en la implementación

**Checkpoint**: Baseline capturado y contrato leído. Las historias pueden empezar.

## Phase 3: User Story 1 - La bandera abre su lista al tocarla (Priority: P1) — MVP

**Goal**: Tocar la bandera en el teléfono abre la lista de países con un solo toque,
y deja de depender de un evento de puntero que en táctil no existe de forma fiable.

**Independent Test**: En un teléfono real, en `https://ec.muyunicos.com/`, tocar la
bandera y comprobar que la lista aparece. Repetir 10 veces: las 10 debe abrir. Se
puede validar entero sin tocar ningún otro componente.

### Implementación para User Story 1

Causa raíz y decisiones: `research.md` §1 (D-1 a D-5). Contrato:
`contracts/ui-states.md` Contrato 1 (C-1.1 a C-1.7).

- [X] T008 [US1] En `js/global-ui.js`, al inicio de `initCountrySelector()`, detectar el contexto de puntero con `window.matchMedia('(hover: hover) and (pointer: fine)')` y guardar el `MediaQueryList` en una variable de scope. Este es el mecanismo de D-1: separa las escuchas por contexto en vez de adivinar por tiempo
- [X] T009 [US1] En `js/global-ui.js`, reestructurar el registro de escuchas del disparador: registrar `mouseenter` y `mouseleave` **solo** cuando la media query coincide; registrar el `click` conmutativo **solo** cuando no coincide. Eliminar el registro incondicional actual de ambas, que en táctil hace que un toque abra y cierre en el mismo gesto
- [X] T010 [US1] En `js/global-ui.js`, reemplazar todas las escrituras `dropdown.style.display = 'block'` y `dropdown.style.display = 'none'` por la alternancia de la clase `is-open` en `.country-redirect-container`, manteniendo `aria-expanded` sincronizado en cada cambio. Contrato C-1.2 y C-1.3
- [X] T011 [US1] En `js/global-ui.js`, añadir un listener `change` sobre el `MediaQueryList` que cierre el desplegable y re-registre las escuchas cuando el contexto de puntero cambia, por ejemplo al conectar un ratón a una tableta
- [X] T012 [US1] En `js/global-ui.js`, añadir un listener de `resize` que cierre el desplegable ante cualquier cambio de ancho, para que un panel no quede abierto a medio camino al rotar el dispositivo
- [X] T013 [US1] En `js/global-ui.js`, añadir manejo de teclado sobre el disparador: `Enter` y `Space` alternan el estado, `Escape` cierra y devuelve el foco al disparador. Contrato C-1.5, y hace la lista operable por teclado
- [X] T014 [US1] En `css/components/header.css`, reemplazar el selector `.country-selector-dropdown[style*="display: block"]` (línea ~346) por una regla controlada por la clase `.country-redirect-container.is-open`. Ese selector de atributo en línea es el acoplamiento frágil que la regla C-1.4 elimina
- [X] T015 [US1] En `css/components/header.css`, eliminar `position: absolute`, `left`, `top` y `z-index` de `.mu-header-country-item` (líneas ~284-289) para que la bandera ocupe su lugar en el flujo de la barra, y eliminar el override móvil equivalente (líneas ~545-548). Decisión D-3: el control estaba anclado al documento, fuera del `<header>`
- [X] T016 [US1] En `css/components/header.css`, añadir el comentario `/* override GP: [motivo] */` a cada regla de esta historia que neutralice una declaración de GeneratePress. Exigido por la constitución VIII
- [ ] T017 [US1] Verificar: ejecutar los 7 pasos de `quickstart.md` B.1 en un teléfono real sobre `ec.muyunicos.com` y registrar el resultado en el PR. Es el criterio que fallaba antes de este trabajo

---

## Phase 4: User Story 2 - El submenú de Mi Cuenta se lee y se usa (Priority: P2)

**Goal**: El submenú deja de ser blanco sobre blanco y adopta el mismo tratamiento
que los submenús nativos del sitio en móvil.

**Independent Test**: En un teléfono real con sesión iniciada, abrir el submenú de
Mi Cuenta y comprobar que todo su texto se lee y que cada enlace responde al primer
toque. No requiere tocar ningún otro componente.

> **Conflicto de archivo**: esta historia edita `css/components/header.css`, el
> mismo archivo que la historia 1. **No puede ejecutarse en paralelo con US1.**

Decisiones: `research.md` §2 (D-6 a D-8).

- [X] T018 [US2] En `css/components/header.css`, dentro del bloque `@media (max-width: 768px)` existente, cambiar `background-color: var(--blanco)` por `rgba(255, 255, 255, 0.12)` en la regla `.mu-sub-menu` (línea ~523). Ese es el velo translúcido que los submenús nativos ya usan (línea ~468) y es lo que produce el contraste que exige SC-003
- [X] T019 [US2] En `css/components/header.css`, dentro del mismo media query, añadir `min-height: 44px` y `padding: 12px 20px` a `.mu-sub-menu li a`, con un comentario que referencie el motivo de hit-area en iOS Safari ya documentado en las líneas ~425-446 de este mismo archivo. Áreas táctiles distintas de las de escritorio, que ya son correctas
- [X] T020 [US2] Verificar con `git diff css/components/header.css` que la regla global de la línea ~236 que fija `color: var(--blanco)` **no** fue modificada. Decisión D-8: esa regla es correcta en escritorio, y tocarla provocaría una regresión ahí (FR-017)
- [ ] T021 [US2] Verificar: ejecutar los 7 pasos de `quickstart.md` B.2 con sesión iniciada, medir la relación de contraste del texto contra su fondo con un medidor sobre el píxel real y registrar el valor medido en el PR. Si da menos de 4.5:1, ajustar y volver a medir

---
## Phase 5: User Story 3 - Mis pantallas de cuenta se pueden usar en el teléfono (Priority: P3)

**Goal**: La sección de cuenta deja de depender de los estilos genéricos de la
plataforma y adopta el diseño del sitio, sin desbordes en pantallas angostas.

**Independent Test**: En un teléfono real con sesión iniciada, recorrer
`/mi-cuenta/` y `/mi-cuenta/edit-account/`, comprobar que nada desborda y que un
formulario se completa y se guarda. No toca carrito ni checkout.

Decisiones: `research.md` §4 (D-15 a D-18). Contrato: `contracts/ui-states.md`
Contrato 4 (C-4.1 a C-4.5).

- [X] T022 En `functions.php`, dentro de `mu_enqueue_assets()`, añadir el enqueue de la hoja de cuenta con el handle `mu-account`, la ruta `"$uri/css/account.css"`, la dependencia `[ 'mu-base' ]` y la versión `$ver`, protegido por la condición `mu_wc_is_account_page()`. Colocarlo junto al enqueue existente de `mu-account-downloads` (~línea 132), que hoy está acotado al endpoint `downloads`
- [X] T023 [US3] Crear `css/account.css` con un comentario de cabecera que declare su alcance (pantallas de la sección de cuenta), su dependencia de `mu-base` y que solo se carga en esas páginas. Contrato C-4.1
- [X] T024 [US3] En `css/account.css`, adaptar `.woocommerce-MyAccount` para móvil: apilar la navegación encima del contenido, eliminar el desbordamiento horizontal a 320px de ancho y dar a los enlaces de navegación una altura mínima de 44px. Contrato C-4.3 y FR-028
- [X] T025 [US3] En `css/account.css`, dar estilo a los formularios de la sección (`.woocommerce-form-login`, `.woocommerce-account-fields`, campos de contraseña) usando **únicamente** variables de `:root` y `clamp()` para la tipografía fluida, con altura mínima de 44px en los controles. Decisión D-18 y contrato C-4.4
- [X] T026 [US3] En `css/account.css`, añadir estilos `:focus-visible` para todo elemento interactivo de la sección, con contorno visible. Contrato C-4.5, exigido por la constitución VIII
- [X] T027 [US3] En `css/account.css`, dar al formulario de acceso del comprador sin sesión el mismo tratamiento que a las pantallas de usuario autenticado, de modo que `/mi-cuenta/` se vea igual de cuidada antes y después de iniciar sesión. FR-030
- [X] T028 [US3] Verificar: ejecutar `php -l functions.php` y `php -l inc/ui.php` y registrar salida limpia en el PR
- [ ] T029 [US3] Verificar: ejecutar `curl -s https://ec.muyunicos.com/tienda/ | grep -c 'account.css'` esperando `0`, y `curl -s https://ec.muyunicos.com/mi-cuenta/ | grep -c 'account.css'` esperando `1` o más. Comprueba FR-031 y da la evidencia de SC-011
- [ ] T030 [US3] Verificar: ejecutar los 7 pasos de `quickstart.md` B.3, recorriendo la sección en los cinco anchos de 320, 375, 390, 414 y 430px, y registrar el resultado

---

## Phase 6: User Story 4 - El botón de WhatsApp invita a pulsarse (Priority: P4)

**Goal**: El acceso flotante a WhatsApp deja de ser un círculo plano y pasa a
comunicar que es pulsable, sin depender de que el dispositivo tenga un puntero fino.

**Independent Test**: En un teléfono real, observar el botón al recorrer la página y
al pulsarlo, comprobando que responde y que no tapa ningún control.

Decisiones: `research.md` §3 (D-9 a D-14). Contrato: `contracts/ui-states.md`
Contrato 3 (C-3.1 a C-3.8).

- [X] T031 [P] [US4] En `style.css`, dentro de `:root`, declarar `--mu-wa-green` y `--mu-wa-accent` con los valores actuales `#25d366` y `#339db7`, y añadir un comentario que aclare que son colores de marca de WhatsApp y no del sitio, y que quedan registrados como excepción en el §8 del guide. Contrato C-3.8 y decisión D-12
- [X] T032 [P] [US4] En `inc/ui.php`, añadir `aria-label="Escribir por WhatsApp"` al elemento `<a class="boton-whatsapp">` de la función `mu_boton_flotante_whatsapp()`, usando `esc_attr()`. Único cambio de PHP de la historia. El destino, el número y el mensaje no se tocan
- [X] T033 [US4] En `css/components/global-ui.css`, reemplazar los literales `#25d366` y `#339db7` del bloque `.boton-whatsapp` por `var(--mu-wa-green)` y `var(--mu-wa-accent)`. Depende de T031
- [X] T034 [US4] En `css/components/global-ui.css`, convertir `.boton-whatsapp` de círculo de 50px en píldora con el ícono y una etiqueta de texto corta, usando `--mu-radius-full`, `display: inline-flex` y `gap`. Decisión D-9: una etiqueta hace que el control sea inequívocamente pulsable en un teléfono, cosa que un círculo pequeño no es
- [X] T035 [US4] En `css/components/global-ui.css`, añadir estados `:hover`, `:focus-visible` y `:active` con contorno de foco visible, y hacer que la etiqueta sea siempre visible por debajo de 768px mientras que en escritorio se revela al enfocar o al pasar el puntero. Contrato C-3.3 y decisión D-10
- [X] T036 [US4] En `css/components/global-ui.css`, añadir un pulso de atención que se ejecute **una sola vez** al cargar la página, animando **únicamente** `transform` y `opacity`, sin `infinite` y sin ninguna propiedad que dispare layout o paint. Contrato C-3.4 y C-3.6
- [X] T037 [US4] En `css/components/global-ui.css`, añadir `@media (prefers-reduced-motion: reduce)` que desactive el pulso y conserve los tres estados de interacción. Contrato C-3.5, y es lo que permite afirmar FR-021
- [X] T038 [US4] En `css/components/global-ui.css`, sustituir el `bottom: 82px` fijo por un valor que lo combine con `env(safe-area-inset-bottom)`, de modo que el control quede por encima de la barra de direcciones y del indicador de inicio en iPhone. Contrato C-3.7
- [ ] T039 [US4] Verificar: ejecutar los 7 pasos de `quickstart.md` B.4, incluyendo el paso de activar "reducir movimiento" en el sistema y recargar, y registrar el resultado en el PR

---
## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Cambios que afectan a varias historias y documentación que el proyecto
exige mantener al día.

- [X] T040 [P] Actualizar `MIGRATION-GUIDE.md` §4 para documentar el nuevo archivo `css/account.css` y el nuevo handle `mu-account` en el árbol de módulos
- [X] T041 [P] Actualizar `MIGRATION-GUIDE.md` §5 para documentar `--mu-wa-green` y `--mu-wa-accent` en la tabla del sistema de diseño, con la nota de que son excepción
- [X] T042 [P] Actualizar `MIGRATION-GUIDE.md` §8 para registrar la excepción de color y para dejar constancia de que la verificación de comportamiento móvil es manual y no automatizable
- [X] T043 Incrementar el valor `Version:` del comentario de cabecera de `style.css`. Sin esto, los navegadores siguen sirviendo el CSS y el JS viejos desde caché y **toda la feature llega vacía a los usuarios**. La constitución II prohíbe inventar query strings de cache-busting por archivo. Si se despliega de forma incremental tras US1, hay que incrementar de nuevo en cada despliegue
- [X] T044 Ejecutar la Fase A completa de `quickstart.md` (A.1 a A.8) y pegar toda la salida en el PR
- [ ] T045 Ejecutar la Fase B completa de `quickstart.md` (B.1 a B.7) en un teléfono real, incluida la compra completa de punta a punta, y pegar los resultados
- [X] T046 Ejecutar `git diff --name-only main...HEAD` y confirmar que **ningún** archivo bajo `Tema-GeneratePress/` aparece en la lista. Si aparece, el principio X y FR-033 están violados
- [ ] T047 Comparar los screenshots "antes" de T004 contra el estado actual a 1024px o más, y registrar en el PR que el único cambio visual deliberado en escritorio es el botón de WhatsApp. Es la evidencia de FR-032 y SC-014

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias, empieza de inmediato
- **Foundational (Phase 2)**: depende de Setup. Bloquea todo el trabajo de implementación
- **User Stories (Phase 3 a 6)**: dependen de Foundational
- **Polish (Phase 7)**: depende de que estén completas las historias deseadas

### Dependencias entre historias

Esta feature tiene un dato estructural que no es evidente y que condiciona el
planificado: **US1 y US2 editan el mismo archivo**.

| Historia | Archivos que toca | ¿Puede solaparse? |
|---|---|---|
| US1 (P1) | `js/global-ui.js`, `css/components/header.css` | — |
| US2 (P2) | `css/components/header.css` | **NO con US1**: mismo archivo |
| US3 (P3) | `functions.php`, `css/account.css` | Sí, con cualquiera |
| US4 (P4) | `style.css`, `inc/ui.php`, `css/components/global-ui.css` | Sí, con cualquiera |

Por lo tanto hay **dos cadenas**, no cuatro bloques independientes:

```text
Cadena A (secuencial, obligatorio):  US1  →  US2
Cadena B (paralela):                 US3
Cadena C (paralela):                 US4
```

US1 debe preceder a US2 porque comparten `css/components/header.css`. US3 y US4 no
comparten archivo con ninguna otra historia y pueden ejecutarse en cualquier momento,
incluso en paralelo con US1.

**Dentro de cada historia**:
- Las tareas sobre un mismo archivo son secuenciales por definición
- Las tareas de verificación van **al final** de la historia y dependen de todas las de implementación
- Una historia se da por terminada cuando su verificación pasó, no cuando su código está escrito

---

## Parallel Opportunities

- T002, T003, T004 y T005 pueden ejecutarse en paralelo entre sí
- T022 puede ejecutarse en paralelo con T023, porque tocan archivos distintos
- T031 y T032 pueden ejecutarse en paralelo
- US3 y US4 pueden ejecutarse en paralelo entre sí, y con US1
- Las historias completas US3 y US4 pueden ir en paralelo una vez pasado Foundational

**Paralelismo real con un solo mantenedor**: 3 de 4 historias (US1, US3, US4) son
independientes entre sí. Solo US2 está encadenada a US1. Con una persona, el camino
más corto es US1 → US2, con US3 y US4 intercaladas donde quepan sin tocar el mismo
archivo que la historia en curso.
---

## Parallel Example: User Story 4

```bash
# Al mismo tiempo, archivos distintos:
Task: "Declarar --mu-wa-green y --mu-wa-accent en style.css"
Task: "Añadir aria-label al enlace .boton-whatsapp en inc/ui.php"

# Después, secuencial porque las seis tocan el mismo archivo:
Task: "Sustituir literales por variables en global-ui.css"
Task: "Convertir el círculo en píldora con etiqueta"
Task: "Añadir estados hover, focus-visible y active"
Task: "Añadir el pulso de atención único"
Task: "Añadir el bloque prefers-reduced-motion"
Task: "Ajustar bottom con env(safe-area-inset-bottom)"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Completar Phase 1: Setup (baseline)
2. Completar Phase 2: Foundational
3. Completar Phase 3: User Story 1
4. **PARAR Y VALIDAR**: ejecutar `quickstart.md` B.1 en un teléfono real
5. Si pasa, ejecutar T043 (incrementar `Version:`) y desplegar

El MVP es defendible por sí mismo: es el único de los cuatro que **bloquea una
función**. Un comprador en el teléfono no puede cambiar de país, y con el resto de
historias sin hacer, el sitio no queda peor que antes.

### Entrega incremental

1. Setup + Foundational → base lista
2. US1 → verificar B.1 → desplegar (MVP, desbloquea cambiar de país)
3. US2 → verificar B.2 → desplegar (desbloquea la navegación de cuenta en móvil)
4. US3 → verificar B.3 → desplegar
5. US4 → verificar B.4 → desplegar
6. Polish → T040 a T047

Cada historia aporta valor sin romper las anteriores, y cada una se puede revertir
sola porque toca archivos distintos. La excepción es US2, que depende de US1 por
compartir archivo: revertir US2 sin revertir US1 dejaría el submenú con el
tratamiento viejo, que es exactamente el defecto reportado.

### Nota sobre el orden de despliegue

El orden P1 → P4 refleja **impacto sobre el comprador**, no dificultad. US4 (el botón
de WhatsApp) es la más barata de las cuatro y US3 (las pantallas de cuenta) la más
larga. Si el criterio real es ahorrar tiempo, US4 puede adelantarse: no comparte
archivos con nadie más. Eso no cambia el alcance, solo el orden.

---

## Notes

- [P] = archivos distintos, sin dependencias en tareas incompletas
- El proyecto no tiene suite de pruebas; las tareas de verificación son pasos de `quickstart.md` y son obligatorias
- **Ninguna historia se da por terminada sin su verificación manual**: el principio IX lo exige y el proyecto no tiene otra red
- Todo archivo PHP tocado pasa `php -l` antes de cualquier commit
- El despliegue es manual y **no** lo hace el agente
- Evitar: tareas vagas, conflictos sobre un mismo archivo, y cualquier dependencia cruzada que rompa la independencia de las historias
---