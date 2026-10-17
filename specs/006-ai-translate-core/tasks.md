# Tasks: Traducción con IA en contexto de tienda (servicio base + botón)

**Input**: Design documents from `/specs/006-ai-translate-core/`

**Prerequisites**: [plan.md](./plan.md) · [spec.md](./spec.md) · [research.md](./research.md) · [data-model.md](./data-model.md) · [contracts/ai-translate.md](./contracts/ai-translate.md)

**Entrega**: F1 (servicio base + pantalla) y F2 (botón "Traducir" en reseñas y banners).
US4 (lote y automatización) queda **fuera** de esta entrega (decisión del mantenedor,
2026-10-16).

## Fase 1 — Esqueleto y registro

- [X] T001 Registrar los módulos nuevos en `functions.php` con su comentario de dependencia: `mu_load_module( 'ai-translate' )` antes de `hero-banners` y de `ui` (los consumidores usan su API), y `mu_load_module( 'ai-translate-admin' )` después del anterior.
- [X] T002 [P] CREAR `inc/ai-translate.php` con guarda `function_exists`, las constantes de opción y de clave, y los esqueletos de la API pública del contrato C-1 (devuelven "sin configurar" sin tocar la red).
- [X] T003 [P] CREAR `css/admin-ai-translate.css` y `js/admin-ai-translate.js` con su encabezado y sin lógica todavía.

## Fase 2 — US1: servicio base y pantalla (Priority: P1)

**Goal**: el mantenedor configura el servicio y comprueba que responde, con contexto de tienda y consumo a la vista.

**Independent Test**: "Probar conexión" responde con el modelo y la latencia; sin clave, el sitio público no cambia.

- [X] T004 [US1] Configuración: `mu_ai_settings_defaults()`, `mu_ai_settings()` y `mu_ai_save_settings()` sobre `_mu_ai_settings` (modelo, límites, contextos), con la clave leída de la constante del servidor y **nunca** persistida.
- [X] T005 [P] [US1] Registro de ámbitos `mu_ai_ambitos()` + perfiles de contexto `reviews` y `hero_banner` (rol de tienda, tono, glosario editable, exclusiones, largo máximo, marcado admitido).
- [X] T006 [US1] Caché `_mu_ai_cache`: clave por hash (texto + idioma + ámbito + versión del glosario + modelo), lectura/escritura y poda (500 entradas / 180 días).
- [X] T007 [US1] Consumo `_mu_ai_usage`: contador por día y por mes, límites por acción/minuto/día reutilizando el patrón de rate-limit del tema, reinicio de día y mes, y último error legible.
- [X] T008 [P] [US1] Filtro de datos personales (`mu_ai_looks_personal()`) y `mu_ai_sanitize_for_field()` (marcado permitido por ámbito).
- [X] T009 [US1] Transporte al proveedor: `wp_remote_post` con la clave en cabecera, tiempo máximo 20 s, salida JSON con esquema, y mapeo de errores (`no_key`, `bad_key`, `quota`, `timeout`, `bad_response`).
- [X] T010 [US1] `mu_ai_translate()` (lote, caché, "sin cambios", errores tipados) y `mu_ai_test_connection()` (lista de modelos + verificación mínima con texto del sistema).
- [X] T011 [US1] Pantalla `inc/ai-translate-admin.php` (WC → Marketing) + acción del administrador con nonce y permiso: prueba de conexión y guardado de ajustes.
- [X] T012 [US1] CSS/JS de la pantalla (estado, consumo, prueba de conexión), encolados solo en esa pantalla con versión por `filemtime`.
- [X] T013 [US1] Verificación de la fase: `php -l`, `node --check`, arnés de §A.2 del quickstart (caché, cuota, PII, respuesta inválida, glosario) e invariantes de §A.3.

## Fase 3 — US2: botón "Traducir" en el editor de reseñas (Priority: P1)

**Goal**: traducir el comentario de una reseña al idioma activo, revisar y guardar.

**Independent Test**: traducir una reseña a PT, guardar y verla en portugués en `/pt/`.

- [X] T014 [US2] Extraer el JS del editor de reseñas de `inc/ui.php` a `js/reviews-admin-modal.js` y encolarlo condicionalmente (solo quien gestiona reseñas y donde está el shortcode), cerrando la deuda del principio II.
- [X] T015 [US2] Botón "Traducir" en el modal de reseñas: llama a la acción del administrador, completa el campo del idioma activo, muestra estados (cargando, sin cambios, error tipado) y **no guarda**.
- [X] T016 [US2] Marca de origen en el guardado de reseñas (`text_pt_hash` / `text_en_hash`) y aviso de "traducción desactualizada" en el editor.
- [X] T017 [US2] Verificación: arnés JS del modal (como en `005`) + prueba manual de §B.2 del quickstart.

## Fase 4 — US3: botón "Traducir" en hero banners (Priority: P2)

**Goal**: traducir un banner completo (título, descripción, CTAs) por idioma.

**Independent Test**: traducir un banner a PT, guardar y verlo traducido en `/pt/` sin cambios en español.

- [X] T018 [US3] Botón "Traducir" en `js/admin-hero-banners.js` (por fila y por idioma activo) + estilo en `css/admin-hero-banners.css`.
- [X] T019 [US3] Marca de origen en el guardado de banners (`mu_hero_banners_sanitize_one`) y aviso de desactualización.
- [ ] T020 [US3] Verificación: `php -l`, prueba manual de §B.3 y caso de fallo de §B.4.

## Fase 5 — Documentación y cierre

- [ ] T021 `MIGRATION-GUIDE.md`: §4 (módulos nuevos con su dependencia), §6 (API del servicio) y §7 (envío externo declarado: qué se envía, a dónde, cuándo se ejecuta, cómo se verifica y cómo se revierte).
- [ ] T022 Ejecutar el quickstart completo y registrar en la feature la salida obtenida (Fase A) y lo que queda pendiente de la Fase B.
- [ ] T023 `git diff --name-only` sin nada bajo `Tema-GeneratePress/`; `git status --short` limpio; actualizar el estado de la spec a "Implementada" cuando la Fase B esté hecha.

## Dependencias

- T002 → T004…T012 (el módulo debe existir).
- T005 → T009 (el perfil define el largo y el marcado antes de enviar).
- T002 + T010 → T011 (la pantalla consume la API).
- T013 cierra US1; T014 → T015 → T016 → T017 cierran US2; T018 → T019 → T020 cierran US3.
- T021 al final: documenta lo que quedó implementado.

## Fuera de esta entrega (US4)

Lote de pendientes, disparo automático al importar reseñas y ejecución programada: se
planifican después de medir calidad y consumo reales (spec, historia US4).



