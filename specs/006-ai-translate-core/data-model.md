# Data Model: Traducción con IA en contexto de tienda

**Feature**: `006-ai-translate-core` | **Fecha**: 2026-10-16 | **Plan**: [plan.md](./plan.md)

Sin tablas nuevas, sin `postmeta`, sin `termmeta` en esta entrega: todo lo que la feature
necesita son **tres opciones** con prefijo `_mu_*` (copia permanente en `wp_options`,
constitución VI), la clave fuera del contenido, y los campos por idioma que **ya existen**
en reseñas y banners.

## 1. Configuración — `_mu_ai_settings`

```php
[
  'enabled'  => true,
  'model'    => 'gemini-flash-lite-latest',   // editable; la pantalla lista los válidos
  'limits'   => [ 'per_action' => 5, 'per_minute' => 10, 'per_day' => 120 ],
  'contexts' => [
    'reviews'     => [ 'role' => '…', 'tone' => '…', 'glossary' => '…', 'exclude' => '…', 'max_chars' => 700,  'allow_html' => false ],
    'hero_banner' => [ 'role' => '…', 'tone' => '…', 'glossary' => '…', 'exclude' => '…', 'max_chars' => 1200, 'allow_html' => true  ],
  ],
]
```

- **Clave**: constante `MU_GEMINI_API_KEY` en la configuración del servidor. Nunca en la
  opción, nunca en el repositorio, nunca en el HTML (FR-001).
- `contexts` se fusiona con los valores por defecto en cada lectura: agregar un ámbito
  futuro no rompe la configuración guardada.
- La opción se guarda sin autoload: no se lee en la navegación de visitantes.

## 2. Caché de traducciones — `_mu_ai_cache`

Mapa `clave => [ 't' => texto_traducido, 'lang' => 'pt', 'ambito' => 'reviews', 'at' => 1780000000, 'model' => '…' ]`.

- `clave = md5( texto_normalizado . '|' . lang . '|' . ambito . '|' . version_glosario . '|' . model )`
- `texto_normalizado = trim( wp_strip_all_tags( texto ) )`
- `version_glosario = substr( md5( glosario . tono ), 0, 8 )` — si el mantenedor cambia el
  glosario, la caché anterior deja de reutilizarse (y no se borra: se poda sola).
- **Poda**: máximo 500 entradas y 180 días; al superar cualquiera de los dos límites se
  descartan las más antiguas.
- Reutilización entre ámbitos: el texto normalizado es el mismo, la clave incluye el
  ámbito y la versión del glosario, así que "Envío gratis" se traduce una vez por contexto.

## 3. Consumo — `_mu_ai_usage`

```php
[ 'day' => '2026-10-16', 'day_requests' => 12, 'day_chars' => 4800,
  'month' => '2026-10', 'month_requests' => 12, 'month_chars' => 4800,
  'last_error' => [ 'code' => 'quota', 'msg' => 'Límite alcanzado', 'at' => 1780000000 ] ]
```

- Se reinician `day_*` y `month_*` al cambiar de día o de mes (hora local del sitio).
- Se incrementa **solo** cuando se contabiliza una solicitud: respuesta recibida del
  proveedor, incluso si es un error de cuota.
- `last_error` guarda un mensaje legible y **nunca** el texto enviado (principio III).

## 4. Marca de origen por campo traducido

Convención: por cada campo `<campo>_<lang>` puede existir `<campo>_<lang>_hash`.

| Ámbito | Campos traducibles | Marca de origen |
|---|---|---|
| Reseñas | `text_pt`, `text_en` | `text_pt_hash`, `text_en_hash` |
| Hero banners | `titulo_pt`, `descripcion_pt`, `eyebrow_pt`, `cta_texto_pt`, `cta_secundario_texto_pt`, `free_badge_text_pt` (+ `_en`) | `<campo>_hash` por cada uno |

- `hash = substr( md5( trim( texto_es ) ), 0, 12 )`.
- **Desactualizada**: la marca existe, la traducción no está vacía y la marca no coincide
  con el español actual. Se informa en el editor; **no** se reescribe sola (FR-013).
- **Compatibilidad**: las reseñas y banners ya guardados no tienen marca. Se tratan como
  "sin marca" (no se consideran desactualizados) hasta la primera traducción asistida.

## 5. Registro de ámbitos (código, no datos)

`mu_ai_ambitos()` declara por ámbito: etiqueta, campos traducibles, perfil de contexto,
largo máximo y si el destino admite marcado. Primera entrega:

- `reviews` → campo `text` (solo el comentario, decisión Q3), sin marcado.
- `hero_banner` → los seis campos de texto del banner; marcado permitido en `titulo` y
  `free_badge_text` (son los que hoy admiten `<span class="mu-highlight">`), URLs fuera
  del alcance del servicio (no se traducen URLs).

## 6. Entidades de la spec ↔ almacenamiento

| Entidad (spec) | Dónde vive |
|---|---|
| Clave del servicio | Constante del servidor; estado verificado en línea |
| Perfil de contexto | `_mu_ai_settings['contexts'][<ámbito>]` |
| Solicitud de traducción | Efímera: el request del administrador que aprieta el botón |
| Resultado | Respuesta al editor; no se persiste |
| Consumo | `_mu_ai_usage` |
| Traducción almacenada | Campos por idioma existentes + marca de origen de la §4 |

## 7. Validaciones de entrada

| Validación | Regla | Error |
|---|---|---|
| Ámbito | Debe estar declarado en `mu_ai_ambitos()` | `bad_scope` |
| Idioma | `pt` o `en` (nunca `es`) | `bad_lang` |
| Texto | No vacío después de recortar y quitar marcado | `empty` |
| Largo | ≤ `max_chars` del ámbito | `too_long` |
| Secreto | Sin correo, teléfono, número de pedido, enlace de descarga | `personal_data` |
| Marcado | Solo el permitido por el ámbito; el resto se descarta antes de enviar | — |
| Respuesta | JSON válido, un item por clave, `translation` de tipo texto | `bad_response` |

## 8. Retención y rollback

- Caché: 500 entradas / 180 días. Consumo: día y mes en curso. Log de error: un único
  registro (`last_error`).
- **Rollback**: revertir el commit del módulo y borrar las tres opciones `_mu_ai_*`. El
  contenido traducido (reseñas y banners) queda intacto y el sitio sigue funcionando sin
  el servicio, mostrando lo que esté guardado y español donde falte.
