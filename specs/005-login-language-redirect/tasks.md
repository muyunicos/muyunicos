# Tasks: Conservar el idioma (wpLingua) al iniciar sesión

**Feature**: `005-login-language-redirect` | **Spec**: [spec.md](./spec.md) | **Plan**: [plan.md](./plan.md)

Formato: `- [ ] Tnnn [P] [USx] descripción`. Los comandos se ejecutan desde la raíz del
repositorio, en PowerShell 7 (`pwsh`). `[P]` = paralelizable con otra tarea de la misma
fase. Toda tarea de código se cierra marcándola acá, no en la publicación.

## Fase 1 — Helpers de prefijo (US1, US2)

- [X] T001 [US1] En `inc/compat-wplingua.php`, agregar `mu_wplingua_request_prefix()` con guarda `function_exists`, valor memoizado con `static`, derivado de `mu_wplingua_original_uri()` y contrastado contra `mu_wplingua_language_prefixes()`. Devuelve `''` cuando la petición no trae prefijo. Contrato C-1
- [X] T002 [US1] En el mismo archivo, agregar `mu_wplingua_localize_uri( $uri )` (contrato C-2): devuelve la URI sin cambios si el prefijo es `''`, no duplica el prefijo (delega en `muyu_clean_uri()` cuando existe) y no toca la query ni el fragmento
- [X] T003 [US1] En el mismo archivo, agregar `mu_wplingua_localize_url( $url )` (contrato C-3): localiza sólo la ruta de una URL absoluta y conserva scheme, host, puerto, query y fragmento; si la ruta no cambia, devuelve la URL de entrada
- [X] T004 [US2] En el mismo archivo, agregar `mu_wplingua_prefix_in_urls( array $urls )` (contrato C-4): primer prefijo encontrado en una lista de URLs; `''` si ninguna lo lleva
- [X] T005 [P] Comentar en la cabecera del archivo por qué estos helpers viven acá (mismo problema: el prefijo que wpLingua reescribe) y actualizar la línea del árbol de la guía §4 si cambia el resumen del módulo

## Fase 2 — Consumidores del contrato (US1, US2)

- [X] T006 [US1] `inc/auth-modal.php`: el destino del login social se construye con C-2 sobre la URL actual (`$_SERVER['HTTP_HOST']` + ruta localizada), sin cambiar la llamada a `mu_social_login_url()` ni el orden de los parámetros
- [X] T007 [US2] `inc/login.php`: `mu_smart_login_redirect()` conserva el idioma del visitante deducido con C-4 (destino pedido, destino original, procedencia) y aplica C-2 a la ruta de "Mi Cuenta"; los roles con acceso al panel siguen devolviendo su destino sin cambios
- [X] T008 [US1] `inc/checkout.php`: aplicar C-3 al destino del bloque social del checkout, guardado con `function_exists` (idempotente: si el permalink ya viene localizado, no cambia)

## Fase 3 — Verificación local (principio IX)

- [X] T009 `php -l inc/compat-wplingua.php; php -l inc/auth-modal.php; php -l inc/login.php; php -l inc/checkout.php` (sin errores)
- [X] T010 [P] `Select-String` de invariantes: una sola lista de prefijos (`Select-String -Path inc\\*.php -Pattern '=> ''/pt'''`), ninguna ruta de login escrita a mano (`wp-login.php` fuera de `inc/auth-modal.php` y `inc/login.php`), y cero destinos armados con `$_SERVER['REQUEST_URI']` sin `mu_wplingua_localize`
- [X] T011 [P] `git diff --name-only` (nada bajo `Tema-GeneratePress/`, nada fuera de `inc/` y `MIGRATION-GUIDE.md`)

## Fase 4 — Documentación operativa (mismo PR)

- [X] T012 Registrar en `MIGRATION-GUIDE.md` §7 el incidente: el destino del login social perdía el prefijo de idioma; causa (la URL actual ya no tiene el prefijo en `wp_footer`), medición y aprendizaje
- [X] T013 Actualizar §3: el acoplamiento con Nextend Social Login y con la pantalla de login, con el criterio de idioma del destino
- [X] T014 Registrar en §8 la observación medida de FR-014 (redirects de la tienda que pierden el prefijo), sin corregirla en esta feature

## Fase 5 — Verificación en producción (mantenedor)

- [ ] T015 Purgar la caché de LiteSpeed de los hosts afectados y comparar el `href` del botón social en `/pt/`, `/pt/tienda/`, `/en/` y en el dominio raíz (esperado: prefijo presente en pt/en, ausente en el raíz)
- [ ] T016 [US1] Login real con Google y con Facebook desde `/pt/` y `/en/`: debe volver a la misma página y al mismo idioma
- [ ] T017 [US2] Login por usuario y contraseña (modal) desde `/pt/`; verificación del enlace de salir (FR-013) con sesión abierta en `/pt/` y `/en/`
- [ ] T018 [US1] Checkout con carrito: el bloque social del checkout conserva el idioma y el host
- [ ] T019 Declarar la feature como verificada o como NO verificada en el PR, con la salida de los `curl` y el resultado del login real (principio IX)
