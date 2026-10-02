# Tasks: Blindar la URL de login social y eliminar los SVG inline

**Input**: Design documents from `/specs/002-fix-broken-social-login/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, quickstart.md

**Tests**: no se incluyen tareas de pruebas automatizadas. El proyecto no tiene
suite, ni CI, ni staging. La verificación es manual y reproducible contra
producción, y ya está codificada como escenarios en `quickstart.md`. Los
escenarios que requieren una persona están marcados como tales.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: puede ejecutarse en paralelo (archivo distinto, sin dependencias)
- **[Story]**: user story de la spec a la que pertenece la tarea

---

## Phase 1: Foundational (bloqueante)

**Purpose**: el punto único de construcción de la URL. Ninguna story puede
empezar sin él.

- [X] T001 [US1] El cambio es de ORIGEN, no de comportamiento: hoy el login ya funciona porque WPS Hide Login reescribe la ruta que el tema escribe a mano. Este helper elimina esa dependencia. Crear `mu_social_login_url( $provider, $redirect_to )` en `inc/auth-modal.php`, envuelta en `if ( ! function_exists() )`. Debe: obtener la ruta con `wp_login_url()`, quedarse con su parte de ruta, y devolver `RUTA?loginSocial=PROVEEDOR&redirect=DESTINO` con el proveedor y el destino escapados. NO usar `add_query_arg()`: el orden de los parámetros es parte del contrato con el proveedor de login social (ver research.md §2)
- [X] T002 [US1] En la misma función, validar que `$provider` sea solo `google` o `facebook`; si no lo es, devolver cadena vacía para que el enlace no se renderice en lugar de renderizarse roto (data-model.md §1)
- [X] T003 [US1] Preservar el host de la petición en la URL: el helper no debe forzar el dominio raíz, para que un comprador de `us.` vuelva a `us.` (research.md §3)
- [X] T004 Ejecutar `php -l inc/auth-modal.php` (debe salir sin errores)

**Checkpoint**: el helper existe y es sintácticamente válido.

---

## Phase 2: User Story 1 — El login social no depende de otro plugin (P1)

**Objetivo**: que los cuatro enlaces se construyan por API y no dependan de que el
plugin que oculta la URL de login los reescriba. El login YA funciona hoy; el
cambio no altera el comportamiento observable. Cubre FR-001 a FR-006.

**Test independiente**: desactivar el plugin que oculta la URL de login y
completar el flujo con una persona real. Si funciona sin ese plugin, la URL deja
de depender de el (quickstart.md escenarios 1 y 2).

### Implementación

- [X] T005 [US1] Reemplazar el `href` de Google en `inc/auth-modal.php:106` por una llamada a `mu_social_login_url( 'google', $current_url )`, conservando las clases y los atributos de la ventana emergente que ya tiene
- [X] T006 [US1] Reemplazar el `href` de Facebook en `inc/auth-modal.php:109` por una llamada a `mu_social_login_url( 'facebook', $current_url )`, conservando clases y atributos
- [X] T007 [US1] Reemplazar el `href` de Google en `inc/checkout.php:374` por `mu_social_login_url( 'google', $current_url )`, donde `$current_url` es `wc_get_checkout_url()` (línea 348). Añadir un comentario que indique de dónde viene la dependencia
- [X] T008 [US1] Reemplazar el `href` de Facebook en `inc/checkout.php:378` por `mu_social_login_url( 'facebook', $current_url )`, conservando clases y atributos
- [X] T009 [US1] Ejecutar `php -l inc/auth-modal.php` y `php -l inc/checkout.php` (sin errores)
- [X] T010 [US1] Verificar que no queda ninguna ruta de login escrita a mano: `grep -rn "wp-login.php" inc/ functions.php` (debe devolver cero)
- [X] T011 [US1] Verificar que los cuatro usos salen del helper: contar las llamadas a `mu_social_login_url` en ambos archivos (deben ser 4)
- [X] T012 [US1] Comparar la URL generada contra la que hoy sirve producción: deben tener la MISMA forma `RUTA?loginSocial=PROVEEDOR&redirect=DESTINO` y el mismo subdominio. Si difieren, no desplegar: el proveedor puede rechazar la redirección
- [X] T013 [US1] Comprobar que la redirección preserva el subdominio: leer el `href` del botón social en `https://us.muyunicos.com/` y confirmar que contiene `us.muyunicos.com`

**Checkpoint**: US1 verificada en código. El flujo con persona real es
quickstart.md escenario 2 y lo hace el mantenedor.

---

## Phase 3: User Story 2 — Los iconos salen del repositorio (P2)

**Objetivo**: que el modal deje de tener SVG pegados. Cubre FR-007 a FR-010.

**Test independiente**: `grep -c "<svg" inc/auth-modal.php` devuelve 0, y los
iconos se ven iguales (quickstart.md escenario 3).

### Implementación

- [X] T014 [P] [US2] Agregar a `inc/icons.php` el icono `close` si no está: verificar primero con `grep "'close'" inc/icons.php`. La línea 32 de `inc/auth-modal.php` tiene una X de 24x24 con `stroke="currentColor"`, `stroke-width="2"`, `stroke-linecap="round"`, `stroke-linejoin="round"` y dos `<line>`. El markup debe quedar idéntico
- [X] T015 [P] [US2] Agregar a `inc/icons.php` el icono `chevron-down`: es un `<polyline points="15 18 9 12 15 6">` de 16x16 con `class="mu-icon-svg"`, `stroke="currentColor"`, `stroke-width="2"`. **Un solo icono**: las líneas 54, 70 y 90 de `inc/auth-modal.php` son idénticas (verificado), así que las tres comparten la misma entrada del repositorio
- [X] T016 [P] [US2] Agregar a `inc/icons.php` el icono `google`: es un SVG de 18x18 con `viewBox="0 0 18 18"` y cuatro `<path>` con los colores de marca. **No existe todavía** en el repositorio: hay que crearlo. `facebook` sí existe y se reutiliza
- [X] T017 [US2] Reemplazar el SVG de la línea 32 de `inc/auth-modal.php` por `<?php echo mu_get_icon( 'close' ); ?>`
- [X] T018 [US2] Reemplazar los SVG de las líneas 54, 70 y 90 de `inc/auth-modal.php` por `<?php echo mu_get_icon( 'chevron-down' ); ?>`, en las tres
- [X] T019 [US2] Reemplazar el SVG de la línea 107 de `inc/auth-modal.php` (Google) por `<?php echo mu_get_icon( 'google' ); ?>`
- [X] T020 [US2] Reemplazar el SVG de la línea 110 de `inc/auth-modal.php` (Facebook) por `<?php echo mu_get_icon( 'facebook' ); ?>`
- [X] T021 [US2] Ejecutar `php -l inc/auth-modal.php` y `php -l inc/icons.php` (sin errores)
- [X] T022 [US2] Verificar `grep -c "<svg" inc/auth-modal.php` devuelve 0
- [X] T023 [US2] Confirmar que los SVG fuera de alcance siguen intactos: los 2 fallbacks de `inc/ui.php` (líneas 139 y 204), y los de `products-core.php`, `geo.php` y `cart-restriction.php`

**Checkpoint**: US2 verificada. La comparación visual es quickstart.md
escenario 3 y la hace el mantenedor.

---

## Phase 4: Validación y cierre

- [X] T024 Ejecutar el escenario 1 de `quickstart.md`: la ruta responde 200, los `href` extraídos usan la ruta vigente, cero rutas escritas a mano, 4 usos del helper, subdominio preservado, y el proveedor acepta el formato
- [X] T034 Verificar que el filtrado del edge no bloquea el retorno de OAuth: `curl -sI "https://muyunicos.com/login/?loginSocial=google&state=t&code=TEST&scope=email+profile+https://www.googleapis.com/auth/userinfo.profile"` debe devolver 200 y no 403. Si devuelve 403, el WAF volvió a bloquear el callback: el fix de URLs es correcto pero no alcanza (research.md §6)
- [X] T025 Ejecutar el escenario 5 de `quickstart.md`: sin errores fatales en la home, y el bypass de caché del checkout intacto
- [X] T026 Registrar en `MIGRATION-GUIDE.md` §7 el incidente del WAF: el filtrado del edge bloqueaba con 403 el retorno de OAuth de Google por la secuencia `.profile` en los parametros; solo se resolvió bajando el nivel de seguridad de la CDN de Alto a Medio. Contexto, regla aislada, resolución y el aprendizaje de que una regla anti-inyección puede bloquear un callback legítimo
- [X] T027 Registrar en `MIGRATION-GUIDE.md` §3 que el tema obtiene la ruta de login mediante la API de WordPress, y que el plugin que la oculta hookea `site_url`: hoy la ruta correcta depende de ese hook, y por eso el tema debe resolverla por API en lugar de escribirla a mano
- [X] T028 Revisar que `MIGRATION-GUIDE.md` no contradice la constitución v2.1.0
- [X] T029 Revisar los 9 Success Criteria de `spec.md` y confirmar que cada uno tiene tarea o evidencia

### Verificaciones manuales (las hace el mantenedor, no el agente)

- [X] T030 [US1] Completar el escenario 2 de `quickstart.md`: login real con Google y con Facebook, desde un subdominio, confirmando que vuelve a su zona con su moneda
- [X] T031 [US1] Completar el escenario 4 de `quickstart.md`: desactivar el plugin de ocultación desde el panel, probar el login, y **reactivarlo** antes de terminar
- [X] T032 [US1] Realizar una compra de prueba completa para confirmar que el checkout no tenga regresiones
- [X] T033 [US2] Comparar visualmente los iconos del modal antes y después: deben verse idénticos

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Foundational)**: sin dependencias. Bloquea la Phase 2
- **Phase 2 (US1, P1)**: depende de Phase 1 completa
- **Phase 3 (US2, P2)**: sin dependencias de Phase 2. Puede solaparse con ella
- **Phase 4 (Validación)**: depende de Phase 2 y Phase 3

### Paralelismo real

Con un solo mantenedor, el paralelismo es limitado pero existe:

- **T014, T015, T016** tocan `inc/icons.php` y no dependen de nada: se pueden
  resolver en cualquier orden
- **Phase 2 y Phase 3** son independientes a nivel de archivo, aunque ambas tocan
  `inc/auth-modal.php`: si se ejecutan en paralelo hay que serializar las
  ediciones de ese archivo
- **T030 a T033** son manuales y secuenciales: requieren a la misma persona

**Realidad**: un solo mantenedor y un archivo compartido entre las dos stories.
El paralelismo real está dentro de Phase 3, no entre fases.

### Orden crítico

1. **Phase 1 completa** antes de Phase 2: los cuatro enlaces necesitan el helper
2. **T012 antes de desplegar**: es el control que evita romper el contrato con el
   proveedor de login social
3. **T030 y T031 antes de cerrar**: sin ellos, el FR-006 y el SC-001 quedan sin
   verificar

---

## Notas de ejecución

- **Sin staging**: todo se verifica en producción. Los escenarios automatizables
  son de lectura.
- **Riesgo del formato de URL**: si el proveedor rechaza la redirección con un
  error de tipo "redirect mismatch", el primer punto a mirar es research.md §2.
  El fix reproduce el formato vigente justamente para evitarlo.
- **T031 es la más delicada**: desactivar el plugin de ocultación en producción
  deja el login por contraseña accesible en la ruta estándar mientras dura la
  prueba. Reactivarlo es parte de la tarea, no un paso opcional.
- **Un solo commit**: el fix de URLs y el de iconos se despliegan juntos porque
  tocan el mismo archivo y el guide los describe a los dos.
- **Los 27 SVG restantes del tema no se tocan**: quedan documentados, no
  resueltos (spec.md Out of Scope).

### Trazabilidad de requisitos

| FR | Tarea(s) | Historia |
|---|---|---|
| FR-001 | T005, T006, T007, T008, T010 | US1 |
| FR-002 | T001 | US1 |
| FR-003 | T001, T005, T006, T007, T008, T011 | US1 |
| FR-004 | T001, T012 | US1 |
| FR-005 | T003, T013 | US1 |
| FR-006 | T031 | US1 |
| FR-007 | T014, T015, T016, T017, T018, T019, T020 | US2 |
| FR-008 | T015, T016 | US2 |
| FR-009 | T022, T023 | US2 |
| FR-010 | T023 | US2 |

### Trazabilidad de criterios

| SC | Tarea(s) | Verificación |
|---|---|---|
| SC-001 | T010, T024, T030 | automática + manual |
| SC-002 | T013, T030 | manual |
| SC-003 | T010, T011, T024 | automática |
| SC-004 | T022 | automática |
| SC-005 | T033 | manual |
| SC-006 | T031 | manual |
| SC-007 | T001, T011 | automática |
| SC-008 | T025, T032 | automática + manual |
| SC-009 | T034, T030 | automática + manual |
