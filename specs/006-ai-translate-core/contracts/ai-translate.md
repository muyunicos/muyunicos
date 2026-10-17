# Contract: Servicio de traducción con IA

**Feature**: `006-ai-translate-core` | **Fecha**: 2026-10-16 | **Plan**: [../plan.md](../plan.md)

## C-1 — API pública del módulo (PHP)

Todas las funciones con guarda `function_exists`, sin estado global nuevo y sin ninguna
escritura de contenido. Ninguna se ejecuta en la navegación de un visitante.

### `mu_ai_api_key(): string` · `mu_ai_key_source(): string` · `mu_ai_save_key( string $key ): bool` · `mu_ai_key_masked(): string`
- `mu_ai_api_key()`: clave en uso; prioridad para la constante `MU_GEMINI_API_KEY` y, si
  no está definida, la opción `_mu_ai_key` que el mantenedor pega en la pantalla.
- `mu_ai_key_source()`: `'constant'`, `'option'` o `''`.
- `mu_ai_save_key()`: guarda (o borra, con `''`) la clave de la pantalla en su propia
  opción, **sin autoload**. Nunca escribe la clave en la opción de ajustes.
- `mu_ai_key_masked()`: versión enmascarada para mostrar (`AQ.A…c60w`). La clave completa
  nunca se devuelve al navegador ni a un log.

### `mu_ai_is_configured(): bool`
`true` cuando hay clave (de cualquiera de las dos fuentes) y el servicio está habilitado.
Sin red, sin consultas pesadas.

### `mu_ai_settings(): array` · `mu_ai_settings_defaults(): array` · `mu_ai_save_settings( array $patch ): bool`
Lectura, valores de fábrica y guardado validado (modelo, límites, contextos). **Nunca**
guarda la clave; la clave se lee de la configuración del servidor.

### `mu_ai_ambitos(): array` · `mu_ai_ambito( string $ambito ): array`
Registro declarativo de ámbitos (etiqueta, campos, perfil, largo máximo, marcado
admitido). Ámbito desconocido ⇒ `[]`.

### `mu_ai_translate( array $items, string $lang, string $ambito ): array`
- **Entrada**: `items = [ [ 'key' => 'text', 'text' => 'Los super recomiendo' ] ]`, `lang ∈ {pt,en}`.
- **Salida**:
```php
[ 'ok' => true, 'code' => '', 'error' => '',
  'items' => [ 'text' => [ 'translation' => 'Super recomendo', 'unchanged' => false, 'cached' => false ] ],
  'requested' => 1, 'usage' => [ 'day_requests' => 12, 'day_chars' => 4820 ] ]
```
- **Garantías**:
  1. No escribe contenido, ni opciones de contenido, ni campos por idioma.
  2. Devuelve `ok=false` **sin llamada de red** cuando: no hay clave o está deshabilitado
     (`no_key`), el texto está vacío (`empty`), supera el largo del ámbito (`too_long`),
     contiene datos personales (`personal_data`) o la ventana de límite está llena
     (`rate_limited`) o se superó el techo diario (`quota`).
  3. Los items presentes en caché se devuelven con `cached=true` y **no** generan
     solicitud; si todos vienen de caché, `requested=0` y no hay red (SC-002).
  4. `unchanged=true` cuando el proveedor informa que el texto ya estaba en el idioma
     destino: el editor no debe guardar nada (FR-009).
  5. Un fallo del proveedor no modifica contenido y deja el error en
     `_mu_ai_usage['last_error']` con un mensaje legible (FR-018).
  6. El consumo se incrementa una sola vez por solicitud efectivamente enviada.

### `mu_ai_usage(): array` · `mu_ai_reset_usage(): bool`
Lectura del contador y reinicio explícito desde la pantalla.

### `mu_ai_test_connection(): array`
- **Salida**: `[ 'ok' => bool, 'model' => '…', 'models' => [ 'ids…' ], 'latency_ms' => int, 'error' => '' ]`.
- Consulta la lista de modelos disponibles y hace una verificación mínima con un texto
  fijo del propio sistema: **nunca** contenido del sitio.

### `mu_ai_looks_personal( string $text ): bool`
`true` si detecta correo, teléfono, número de pedido, enlace de descarga o un patrón
declarado como no publicable. Es la puerta del FR-012.

### `mu_ai_hash( string $text ): string` · `mu_ai_is_stale( string $source_es, string $traduccion, string $hash ): bool`
Marca de origen y detección de traducción desactualizada (FR-013).

### `mu_ai_sanitize_for_field( string $text, string $ambito ): string`
Descarta el marcado no permitido por el ámbito (los títulos de banner conservan sus
resaltados) y normaliza espacios. Se aplica **también** a lo que devuelve el proveedor.

## C-2 — Contrato con el proveedor (envío externo)

| Aspecto | Definición |
|---|---|
| Método y destino | `POST` a la API REST del proveedor, con la clave en una cabecera (nunca en la query ni en logs) |
| Cuerpo | Instrucción de sistema (rol, tono, glosario, exclusiones y formato del ámbito) + lote de textos numerados por clave |
| Salida exigida | JSON con un item por clave y los campos `translation`, `unchanged`, `notes` |
| Formato | Se pide salida JSON con esquema; **todo** se valida antes de usarse |
| Tiempo máximo | 20 s; al excederse ⇒ `timeout`, sin escribir nada |
| Errores | Sin autorización ⇒ `bad_key`; cuota ⇒ `quota`; formato inesperado ⇒ `bad_response` |
| Prohibido enviar | Correo, teléfono, número de pedido, enlace de descarga, datos de cuenta o contenido de pedidos |
| Trazabilidad | Se guardan fecha, modelo y largo del lote en el consumo; **nunca** el texto enviado |

## C-3 — Acción del administrador (AJAX)

- **Acción**: `mu_ai_translate` (solo usuarios con permiso de gestión de la tienda).
- **Protección**: verificación de permiso y de nonce en el servidor; el nonce viaja en la
  petición, no en la URL.
- **Entrada**: `ambito`, `lang`, `fields` (JSON con los textos de los campos del
  formulario que se quieren traducir).
- **Salida**: la de `mu_ai_translate()` más `ambito` y `lang`, para que el editor pinte
  cada campo por separado.
- **Efectos sobre el contenido**: **ninguno**. Guardar sigue siendo una acción posterior
  del mantenedor por el camino que ya existe en cada ámbito (FR-010, R9).
- **Errores**: `error` legible + `code` para que el editor distinga "sin clave", "cuota" y
  "dato personal".

## C-4 — Invariantes verificables (quickstart)

1. Una sola aparición del envío saliente en el tema, y solo en `inc/ai-translate.php`.
2. Ningún consumidor (reseñas, banners) llama al proveedor por su cuenta: todos usan la
   API de C-1.
3. La clave no aparece en la base de contenido ni en el HTML público.
4. Ninguna función del módulo se ejecuta en la navegación de un visitante: el módulo solo
   actúa en el panel y en la acción del botón.
5. Los ámbitos siguen guardando por sus handlers existentes; el módulo no publica.
