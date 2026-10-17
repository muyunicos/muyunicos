# Implementation Plan: Traducción con IA en contexto de tienda (servicio base + botón)

**Branch**: `006-ai-translate-core` | **Date**: 2026-10-16 | **Spec**: [spec.md](./spec.md) | **Research**: [research.md](./research.md) | **Contract**: [contracts/ai-translate.md](./contracts/ai-translate.md) | **Data**: [data-model.md](./data-model.md)

## Summary

Se agrega el servicio de traducción con IA como **módulo propio** (`inc/ai-translate.php`)
con su pantalla de configuración y estado (`inc/ai-translate-admin.php`, submenú de
WC → Marketing), y se conecta a los dos ámbitos que ya tienen campos por idioma: las
**reseñas** (`text_pt` / `text_en`) y los **hero banners** (título, descripción y CTAs).
El servicio **propone**; el guardado que ya existe **publica**. La clave vive en la
configuración del servidor; el consumo, la caché y los perfiles de contexto en
`wp_options` con prefijo `_mu_*`. Ninguna llamada en la navegación de visitantes.

Alcance de esta entrega (resuelto con el mantenedor el 2026-10-16): **F1** (servicio base
y pantalla) y **F2** (botón "Traducir" en reseñas y banners). El lote y la automatización
son la historia US4, etapa siguiente.

## Technical Context

- **PHP**: 8.5 (local 8.5.9 · producción 8.5.4). `php -l` obligatorio (principio IX).
- **Dependencias**: ninguna nueva. WordPress + WooCommerce, GeneratePress 3.6.1 (padre,
  solo lectura). El servicio externo se llama con la misma familia HTTP que la única
  consulta saliente que el tema ya hace (reseñas de Google Places).
- **Datos**: tres opciones nuevas (`_mu_ai_settings`, `_mu_ai_cache`, `_mu_ai_usage`) y
  una marca `_hash` por campo traducido. Sin tablas, sin `postmeta`, sin `termmeta`, sin
  migración de contenido.
- **Assets**: `css/admin-ai-translate.css` y `js/admin-ai-translate.js` con versión por
  `filemtime` (patrón de hero-banners); el editor de reseñas pasa a tener su JS en
  archivo físico (`js/reviews-admin-modal.js`), cerrando la deuda del principio II.
- **Entorno**: 10 hosts servidos por una sola instalación. Producción es el único entorno.
- **Verificación**: `php -l`, `node --check`, arnés de lógica pura con el envío externo
  simulado en `%TEMP%`, `Select-String` de invariantes, y prueba manual del mantenedor
  desde el panel.

## Constitution Check

*GATE: revisado antes de la investigación y después del diseño.*

| Principio | Cómo lo cumple |
|---|---|
| I. Modularidad pragmática | Dos módulos nuevos, declarados en `mu_load_module()` con su comentario de dependencia (`ai-translate` antes de `ai-translate-admin` y antes de sus consumidores). Sin lógica de negocio en `functions.php`. |
| II. Carga condicional de assets | El CSS/JS de la pantalla se encola solo en esa pantalla; el editor de reseñas encola su JS solo para quien puede gestionar reseñas y donde está el shortcode. Sin `wp_add_inline_*` y, con esta feature, **sin el JS inline** que hoy vive dentro del shortcode. |
| III. Datos y pedidos | El servicio nunca envía ni registra datos de clientes: filtro previo por patrón (FR-012) y el último error se guarda sin el texto enviado. |
| V. SEO e i18n multi-país | No toca canonical, hreflang ni rutas. Refuerza la regla de idioma por URL: el idioma destino sale de la misma fuente única que ya usa el tema. |
| VI. Caché como restricción | Cero llamadas durante la navegación de visitantes; caché de traducciones y contador en `wp_options` con prefijo `_mu_*` (sobreviven al vaciado del object cache); purga por host **solo si el contenido cambió** (Hash Gate). |
| VII. Observabilidad | Estado de configuración, modelo en uso, consumo del día y del mes, último error legible y contador por solicitud: todo visible en la pantalla. |
| VIII. Mensajes al comprador | Los textos de aviso al comprador quedan **fuera de alcance**: exigen revisión humana obligatoria. |
| IX. Verificación antes de desplegar | `php -l`, `node --check`, arnés de lógica pura y quickstart con el caso de fallo declarado. |
| X. Perímetro y dependencias | La dependencia externa se declara en la guía §7 con su destino, su condición (solo administradores) y su rollback. |

**Gate**: sin violaciones. La dependencia externa declarada se registra en la guía
(§7/§8) y no exige enmienda de la constitución (ningún principio cambia).

## Project Structure

| Archivo | Cambio |
|---|---|
| `inc/ai-translate.php` | **Nuevo**: servicio — configuración, registro de ámbitos, perfiles de contexto, caché, cuotas, llamada al proveedor, API pública y acción del administrador |
| `inc/ai-translate-admin.php` | **Nuevo**: pantalla WC → Marketing — estado de la clave, modelo, contextos editables, consumo, último error y prueba de conexión |
| `css/admin-ai-translate.css` | **Nuevo**: estilos de la pantalla |
| `js/admin-ai-translate.js` | **Nuevo**: prueba de conexión y aviso de guardado |
| `functions.php` | Registrar los dos módulos con su comentario de dependencia |
| `js/reviews-admin-modal.js` | **Nuevo**: el JS del editor de reseñas sale del inline del shortcode (deuda del principio II) y recibe el botón "Traducir" |
| `inc/ui.php` | El editor de reseñas encola el JS físico (solo para quien gestiona reseñas y donde está el shortcode) + marca "traducción desactualizada" |
| `inc/hero-banners.php` | El guardado de banners conserva y actualiza la marca de origen por campo traducido |
| `js/admin-hero-banners.js` | Botón "Traducir" por fila de banner y por idioma |
| `css/admin-hero-banners.css` | Estilo del botón dentro de la fila del banner |
| `MIGRATION-GUIDE.md` | §4 (módulos nuevos), §6 (API del servicio), §7 (envío externo declarado + comando de verificación) |

## Diseño

1. **Servicio** (`inc/ai-translate.php`): `mu_ai_settings()` · `mu_ai_ambitos()` ·
   `mu_ai_context_profile()` · `mu_ai_hash()` · `mu_ai_is_stale()` ·
   `mu_ai_looks_personal()` · `mu_ai_sanitize_for_field()` · `mu_ai_translate()` ·
   `mu_ai_usage()` · `mu_ai_test_connection()` (contrato C-1).
2. **Transporte**: envío saliente con la clave en cabecera, tiempo máximo 20 s y
   validación estricta de la respuesta; errores tipados (C-2).
3. **Contexto de tienda**: perfiles por defecto para `reviews` y `hero_banner`
   (qué es el texto, público, tono, glosario editable y exclusiones: marcas, URLs,
   medidas, nombres propios).
4. **Caché y cuotas**: clave derivada de texto + idioma + ámbito + versión del glosario +
   modelo; contador persistente por día y por mes; límites por acción, por minuto y por
   día (data-model §2 y §3).
5. **Pantalla**: estado de la clave (sin configurar / válida / rechazada), modelo en uso
   con lista de disponibles, contextos editables, consumo, último error y prueba de
   conexión con un texto fijo del sistema (nunca contenido del sitio).
6. **Botón "Traducir"**: en el editor de reseñas (idioma activo) y en cada banner (por
   idioma). Llama a la acción del administrador, completa los campos, informa si algo se
   rechaza y **no guarda**.
7. **Marca de origen**: al guardar, cada campo traducido guarda el hash del español que lo
   originó; si el español cambia, el editor marca la traducción como desactualizada y no
   la reescribe sola.

**No cambia**: el camino de lectura del front (idioma + respaldo a español), la
importación de reseñas, la estructura de reseñas y banners (solo se agregan claves de
marca), ninguna ruta, canonical ni hreflang.

## Riesgos y reversibilidad

| Riesgo | Mitigación |
|---|---|
| La cuota del plan gratuito se agota | Caché, límites por acción/minuto/día, aviso claro y contador visible (FR-019) |
| Traducción infiel de una reseña | Instrucción de fidelidad + revisión humana antes de guardar + marca de traducción automática (FR-016) |
| Doble traducción con el plugin de idiomas | Propiedad declarada (R12) y verificación manual en `/pt/` |
| Consumo desde la navegación | El módulo solo actúa en el panel y en la acción del botón; los visitantes no tocan el contador (FR-005, SC-001) |
| Datos personales enviados al proveedor | Filtro previo + casos de prueba con correo, teléfono y número de pedido (SC-007) |
| La documentación queda desactualizada | FR-020: la guía §7 se actualiza en el mismo cambio |
| Perder una corrección humana | La IA no pisa una traducción existente sin confirmación (FR-013) |

**Rollback**: revertir el commit y borrar las tres opciones `_mu_ai_*`. El contenido
traducido queda intacto y el sitio sigue funcionando sin el servicio (muestra lo guardado
y español donde falte). Sin migración, sin pasos en el servidor, sin purga especial.

## Verificación (detalle en `quickstart.md`)

1. `php -l` de cada PHP tocado (principio IX) y `node --check` de cada JS.
2. Arnés en `%TEMP%` con el envío externo simulado: caché (segunda vez ⇒ 0 solicitudes),
   cuota agotada, respuesta inválida, dato personal, texto ya en el idioma destino,
   marcado permitido y la regla "idéntico al español ⇒ se guarda vacío".
3. `Select-String` de invariantes: un solo envío externo y solo en el módulo; ningún
   consumidor llama al proveedor por su cuenta; la clave fuera del contenido y del HTML.
4. Manual (mantenedor): probar conexión; traducir una reseña a PT, revisar y guardar; ver
   el comentario en `br.muyunicos.com/pt/` después de purgar; traducir un banner y verlo en
   `/pt/`; comprobar que sin clave el sitio no cambia.

**Caso de fallo declarado** (principio IX, riesgo alto por ser el primer envío externo de
escritura del tema): si la clave estuviera mal configurada o la cuota agotada, el servicio
debe fallar **cerrado** — sin escribir, sin romper el editor y sin dejar el sitio a medias.
Se verifica con el arnés (respuestas simuladas) y con la prueba manual de la pantalla.

## Fases de implementación

Ver `tasks.md`: Fase 1 (esqueleto y registro), Fase 2 (servicio base = US1), Fase 3
(botón en reseñas = US2), Fase 4 (botón en banners = US3), Fase 5 (documentación y cierre).
US4 (lote y automatización) queda fuera de esta entrega.

