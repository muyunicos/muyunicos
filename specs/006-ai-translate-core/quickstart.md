# Quickstart: Traducción con IA en contexto de tienda

**Feature**: `006-ai-translate-core` | **Plan**: [plan.md](./plan.md) | **Contrato**: [contracts/ai-translate.md](./contracts/ai-translate.md)

**Fase A**: local, ejecutable ahora (sintaxis, invariantes y lógica pura con el envío
externo simulado).
**Fase B**: en el panel y contra producción; requiere el despliegue del mantenedor y, en
los pasos marcados, su clave real.
**Fase C**: cierre (rollback, guía y lo que queda declarado como no verificado).

## Fase A — Verificación local

### A.1 Sintaxis (principio IX)

```powershell
php -l inc\ai-translate.php; php -l inc\ai-translate-admin.php; php -l functions.php
node --check js\admin-ai-translate.js; node --check js\reviews-admin-modal.js
# esperado: "No syntax errors detected" en los PHP y sin salida en los JS (PHP local 8.5.9)
```

### A.2 Arnés de lógica pura (envío externo simulado, sin WordPress)

El arnés vive en `%TEMP%` (no se versiona: el principio IX no autoriza agregar
infraestructura de pruebas al proyecto). Define `ABSPATH` y los stubs mínimos
(`wp_remote_post`, `wp_remote_retrieve_response_code`, `wp_remote_retrieve_body`,
`get_option`/`update_option` sobre un arreglo en memoria, `sanitize_text_field`,
`wp_strip_all_tags`, `wp_kses`, `wp_unslash`, `add_action`, `current_user_can`,
`check_ajax_referer`, `wp_send_json_*` que lanzan excepción) e incluye el módulo real por
ruta absoluta.

Escenarios que el arnés debe cubrir y su resultado esperado:

| Escenario | Esperado |
|---|---|
| Traducción nueva (respuesta simulada válida) | `ok=true`, un item traducido, `requested=1`, consumo `+1` |
| Misma traducción otra vez | `cached=true`, `requested=0`, **sin red** (SC-002) |
| Sin clave configurada | `ok=false`, `code=no_key`, **sin red** (FR-018) |
| Texto ya en el idioma destino (respuesta "sin cambios") | `unchanged=true`, el editor no guarda (FR-009) |
| Texto con correo, teléfono o número de pedido | `ok=false`, `code=personal_data`, **sin red** (FR-012) |
| Texto más largo que el máximo del ámbito | `ok=false`, `code=too_long` |
| Respuesta del proveedor con JSON inválido | `ok=false`, `code=bad_response`, nada escrito |
| Error de cuota del proveedor | `ok=false`, `code=quota`, consumo registrado y último error legible |
| Límite por ventana alcanzado (varias llamadas seguidas) | `ok=false`, `code=rate_limited` |
| Título de banner con marcado permitido | El marcado permitido se conserva; el resto se descarta |
| Glosario cambiado | La caché anterior no se reutiliza (la clave incluye la versión del glosario) |

```powershell
php "$env:TEMP\mu006-arnes.php"
# esperado: todas las aserciones OK y exit=0
```

**Medido el 2026-10-16 (PHP 8.5.9 local): 36 aserciones OK / 0 fallas**, sobre el módulo
real `inc/ai-translate.php` con el envío externo simulado. El arnés usado vive en
`%TEMP%\mu006\` (`stubs.php` + `tests.php`) y cubre: sin clave (0 solicitudes), traducción
nueva (consumo +1, clave en cabecera y **no** en la URL), caché (0 solicitudes nuevas y
consumo intacto), dato personal por correo / número de pedido / teléfono (rechazo sin
enviar), texto demasiado largo, texto ya en el destino, respuesta con JSON inválido,
cuota agotada, clave rechazada, límite por minuto, límite por acción, marcado permitido
del banner (y `<script>` descartado), cambio de glosario que invalida la caché, prueba de
conexión correcta y rechazada, ámbito desconocido e idioma no soportado.

### A.3 Invariantes de texto

```powershell
# Un solo envío externo de escritura en el tema, y solo en el módulo
Select-String -Path inc\*.php,functions.php -Pattern 'wp_remote_post' |
  ForEach-Object { "$($_.Filename):$($_.LineNumber)" }

# Ningún consumidor llama al proveedor por su cuenta
Select-String -Path inc\*.php,js\*.js -Pattern 'generativelanguage|MU_GEMINI_API_KEY' |
  ForEach-Object { "$($_.Filename):$($_.LineNumber)" }

# Nada fuera del child theme
git diff --name-only
```

**Esperado**: el envío externo aparece una sola vez (en `inc/ai-translate.php`); la
constante de la clave solo en el módulo y en la pantalla (para leer el estado); ningún
archivo bajo `Tema-GeneratePress/`.

### A.4 Medición contra la API real (2026-10-16)

Con una clave real (clave del mantenedor, nunca versionada) se verificó la forma completa
del contrato C-2, que es lo que el arnés no puede probar:

| Comprobación | Resultado medido |
|---|---|
| Listado de modelos (`GET /v1beta/models`) | `200`, 50 modelos; incluye `gemini-flash-latest` y `gemini-flash-lite-latest` |
| Traducción (`POST …/gemini-flash-latest:generateContent`) | `200`, `finishReason=STOP`, alias resuelto a `gemini-3.8-flash`, `697` tokens para 2 textos |
| Traducción con el modelo por defecto (`gemini-flash-lite-latest`) | `200`, resuelto a `gemini-3.5-flash-lite`, `306` tokens para 2 textos |
| Forma de la respuesta | El JSON pedido llega en `candidates[0].content.parts[0].text` (una vez compacto y otra con saltos de línea): ambas formas se decodifican igual |
| Fidelidad (reseña) | «Excelente atención! Me llegó en 3 días a Rosario y la calidad es increíble.» → «Excelente atendimento! Chegou em 3 dias em Rosário e a qualidade é incrível.» |
| Marcado del banner | El `<span class="mu-highlight">…</span>` vuelve intacto (con escapes de JSON que se resuelven al decodificar) |
| Texto ya en el idioma destino | El proveedor devolvió `unchanged: true` para la reseña escrita en portugués |
| Clave | Viaja en la cabecera `x-goog-api-key`; no queda en el repositorio, ni en la base, ni en la URL |

Conclusiones registradas: el modelo por defecto (`gemini-flash-lite-latest`) alcanza para
el volumen de la tienda y es el más económico de los dos probados; el alias se resuelve
solo, así que **no** hay que fijar la versión en código (R2); y el gasto observado (~150
tokens por reseña corta) deja el techo diario del tema muy por debajo de la cuota del plan
gratuito.

## Fase B — Verificación en el panel y contra producción (mantenedor)

### B.1 Configuración y estado (US1)

1. Definir la clave en la configuración del servidor y abrir la pantalla del servicio.
2. **Probar conexión**: debe responder correctamente, indicar el modelo en uso y la
   latencia, y listar los modelos disponibles.
3. Quitar la clave y abrir la pantalla: debe indicar "sin configurar" y el sitio público
   debe verse **idéntico** (SC-003).
4. Con la clave puesta, buscar la clave en el HTML público y en el contenido: no debe
   aparecer en ninguno de los dos (FR-001).
5. Consumo: después de las pruebas, el contador del día debe reflejar exactamente las
   solicitudes efectivamente enviadas (sin contar las resueltas por caché).

### B.2 Reseña traducida (US2)

1. Abrir una reseña real en el editor, elegir PT y apretar "Traducir".
2. Revisar el texto propuesto: debe ser fiel al original, sin agregar ni quitar sentido.
3. Guardar y purgar la caché del host donde se mide.
4. Verificar la sección de reseñas en `br.muyunicos.com/pt/`: el comentario debe aparecer en
   portugués; en español no debe haber cambios.
5. Repetir "Traducir" sobre la misma reseña: no debe consumir una solicitud nueva.
6. Vaciar el campo de portugués y guardar: el sitio debe volver a mostrar el español.

### B.3 Banner traducido (US3)

1. Abrir un banner en el editor, elegir PT y traducir título, descripción y textos de botón.
2. Guardar, purgar el host de `br.` y verificar `/pt/`: el banner se muestra traducido y los
   enlaces siguen apuntando a las mismas URLs.
3. Comprobar que el título conserva sus resaltados y que no aparece marcado inesperado.
4. Verificar que en español el banner no cambió.

### B.4 Casos de fallo declarados (principio IX)

| Caso | Cómo se provoca | Esperado |
|---|---|---|
| Clave inválida | Clave alterada en la configuración | Estado "rechazada"; el sitio no cambia; el editor informa el motivo |
| Cuota agotada | Límite del día alcanzado | Aviso claro; nada se escribe; el contador no cuenta lo no enviado |
| Sin respuesta del proveedor | Bloqueo temporal de la salida | Corte por tiempo excedido; el editor informa; nada queda a medias |
| Texto con dato personal | Comentario con un correo o un número de pedido | Rechazo con motivo; **nada** se envía |
| Navegación de un visitante | Navegar el sitio en `/pt/` y `/en/` con el servicio activo | 0 solicitudes nuevas en el contador (SC-001) |

### B.5 Doble traducción con el plugin de idiomas (R12)

En `/pt/`, comprobar que el texto traducido por el tema aparece **una vez** y correcto (no
re-traducido por el plugin) y que el texto que el plugin ya traducía sigue igual. Si
apareciera un conflicto, decide el mantenedor: los datos que guarda el tema se sirven tal
como están guardados.

### B.6 Lo que queda declarado como NO verificado

- Traducción en lote y automática (US4): fuera de esta entrega.
- Productos, categorías, correos, WhatsApp y SEO: fuera de alcance declarado.
- Calidad de textos largos en portugués e inglés: se evalúa con las primeras traducciones
  reales revisadas por el mantenedor.

## Fase C — Cierre

1. `git status --short` sin archivos sin intención (el arnés de §A.2 vive en `%TEMP%`).
2. `MIGRATION-GUIDE.md` actualizado: §4 (módulos nuevos), §6 (API del servicio) y §7 (envío
   externo declarado con su comando de verificación).
3. `specs/006-ai-translate-core/` con spec, checklist, research, data-model, contrato, plan,
   quickstart y tasks.
4. Rollback definido en `plan.md`: revertir el commit y borrar las tres opciones `_mu_ai_*`.

