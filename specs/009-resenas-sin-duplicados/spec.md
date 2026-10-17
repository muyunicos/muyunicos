# 009 — Reseñas sin duplicados (carga manual ↔ importación)

**Estado**: implementado (2026-10-16).

## Problema

Al cargar una reseña a mano y volver a importar desde Google aparecía **duplicada**. La
causa estaba en la llave de comparación de la importación (`inc/ui.php`):

```php
if ( $stored['time'] == $item['time'] && $stored['author_name'] === $item['author_name'] )
```

1. **La fecha no puede coincidir**: Google manda un timestamp con hora; una reseña manual
   tiene la fecha del formulario (`<input type="date">` → 00:00) o el momento en que se creó.
2. **El nombre debe ser idéntico**: Google devuelve "Jonatan S." donde a mano se escribe
   "Jonatan" (o al revés, con acentos o espacios distintos).
3. No existía ningún identificador ni huella que ligara la copia manual con el original.

Contexto que agrava el síntoma: la API de Places devuelve **como máximo 5 reseñas** (las más
relevantes), así que la importación vuelve siempre sobre las mismas.

## Solución

| # | Cambio |
|---|---|
| 1 | Módulo nuevo `inc/reviews-identity.php` con funciones **puras**: `mu_review_normalize()`, `mu_review_author_key()`, `mu_review_same_author()` (igualdad normalizada o prefijo con 4+ letras), `mu_review_identity_key()` (autor + 80 caracteres del texto), `mu_reviews_find_same_author()`. Sin migración: todo se calcula al vuelo. |
| 2 | La importación se extrae a **`mu_reviews_import_merge( $db, $batch, $max )`** (antes eran 20 líneas dentro del shortcode, imposibles de probar). Devuelve base nueva, agregadas, ya existentes y marcadas. |
| 3 | **Criterio**: coincidencia exacta → se omite; mismo autor (o texto idéntico) → se agrega la de Google y la vieja queda con `posible_duplicado = true`; el resto se agrega normal. |
| 4 | Al marcar, la de Google **hereda** lo que la vieja tenía y Google no trae: foto de compra y traducciones (`text_pt`/`text_en` con su marca de origen). **No** hereda `hidden`. |
| 5 | Los **visitantes no ven** las marcadas (filtro en `js/testimonials.js`); el mantenedor las ve con la etiqueta *posible duplicado*. |
| 6 | Aviso del import informativo: agregadas · ya estaban · marcadas (con los nombres). |
| 7 | **Bloque de resolución** (solo mantenedor, arriba de la grilla) con dos acciones por reseña: *No es duplicada* (saca la marca, sin tocar `hidden`) y *Eliminar*. Acción AJAX `mu_resolve_review_duplicate`. |
| 8 | **Guardado manual**: al crear una reseña nueva, si ya hay una del mismo autor, el servidor responde `duplicado_probable` y el editor ofrece *"Guardar igual (es otra persona)"* (reenvía con `forzar_duplicado=1`). |

### Decisiones

- **La de Google es la fuente de verdad** (decisión del mantenedor): se agrega, y la copia
  vieja queda marcada para revisar en vez de bloquear la importación.
- Las marcadas se ocultan a los visitantes hasta que el mantenedor decida, y la decisión
  `hidden` propia del mantenedor se respeta (son campos distintos).
- Sin heurísticas de fecha (no ayudan: el problema es que la fecha manual es un día o el
  momento de creación). El criterio es **autor** (normalizado) y, como red, texto idéntico.
- Nombres de menos de 4 letras no se comparan por prefijo (evita "Ana" ≈ "Ana María" por
  accidente); la igualdad normalizada sí se aplica siempre.

## Criterios de aceptación

1. Importar dos veces el mismo lote: la segunda vez no agrega nada y lo informa.
2. Cargar a mano una de las 5 reseñas de Google (con la fecha del día) e importar: no quedan
   dos visibles; la vieja queda marcada y la de Google con su foto de compra y traducciones.
3. Los visitantes ven una sola reseña; el mantenedor ve la marcada con su etiqueta.
4. *No es duplicada* la devuelve al front; *Eliminar* la borra.
5. Al crear a mano una reseña con un nombre ya existente, el editor avisa y solo guarda si se
   confirma ("es otra persona").
6. Nada bajo `Tema-GeneratePress/`; sin JS inline nuevo; los duplicados existentes **no** se
   tocan (la marca es hacia adelante).

## Verificación

```powershell
php -l inc/reviews-identity.php ; php -l inc/ui.php
node --check js/testimonials.js ; node --check js/reviews-admin-modal.js

Copy-Item 'inc\reviews-identity.php' "$env:TEMP\mu009\reviews-identity.php" -Force
php "$env:TEMP\mu009\arnes-resenas.php"   # esperado: 26 OK / 0 FAIL
```

El arnés ejecuta el módulo real con lo mínimo de WordPress simulado y cubre: mismo autor con
apellido agregado por Google, acentos/mayúsculas, prefijos cortos, huella de contenido,
`rating < 5`, reimportación exacta, reseña manual marcada, herencia de foto y traducciones,
no-herencia de `hidden`, autor distinto, tope de reseñas y la detección del guardado manual.

**Manual (mantenedor)**: importar dos veces; cargar a mano una de las 5 y reimportar; resolver
las marcadas en ambos sentidos; crear a mano una reseña con un nombre repetido (probar el
aviso y el "Guardar igual").

## Fuera de alcance

- Limpiar los duplicados que ya existen en la base (la marca es hacia adelante). Se puede
  agregar un asistente que corra la misma regla sobre toda la base.
- Identificador oficial de Google: la API legacy (la que usa el tema) no lo devuelve; se
  evitaría con la Places API (New), que es un cambio mayor.

## Ampliación (2026-10-16): el enlace como identidad + importación semanal

**Contexto**: el mantenedor tiene una automatización que carga reseñas manualmente **con el
enlace del perfil del reseñador** (`/maps/contrib/<ID>/…`). Ese enlace identifica a la
persona de forma estable (mejor que el nombre, que Google reescribe), así que pasa a ser la
llave primaria de identidad.

| # | Cambio |
|---|---|
| 1 | `mu_review_link_key()`: normaliza el enlace (host + ID del perfil, ignora `/reviews`, query y fragmento) y **solo lo acepta si es un perfil de contribuciones**. Un enlace del lugar o genérico devuelve `''`, porque lo compartirían todas las reseñas. |
| 2 | **Regla nueva del merge**: mismo enlace → la de Google se conserva, **absorbe** traducciones (con su marca de origen), foto de compra y `hidden`, y **la manual se elimina**. Guarda: si el enlace está repetido en 3+ reseñas (señal de enlace genérico) no se borra nada y cae a la regla por nombre (marcar). |
| 3 | Se mantiene intacta la regla por nombre (marcar, sin borrar) para las reseñas sin enlace confiable. |
| 4 | **Exclusiones**: al eliminar una reseña a mano se recuerda su enlace y su huella (`mu_reviews_blocked`, últimas 100) para que la importación no la reviva. |
| 5 | **Importación semanal**: `mu_reviews_import_from_google()` (única ruta de import, la comparte el enlace `?force_reviews=1`) + evento WP-Cron `mu_reviews_weekly_import` ejecutado por el System Cron de Hostinger (DISABLE_WP_CRON está activo). Deja rastro en la opción `mu_reviews_last_import`, visible en el panel. |
| 6 | Aviso del import ampliado: agregadas · manual absorbida y eliminada · ya estaban · excluidas por haberse borrado a mano. |

**Verificación**: el arnés de esta feature pasó a **47 aserciones OK / 0 fallas**, incluyendo la
llave de enlace (perfil válido, enlace del lugar, enlace genérico, sin enlace), la absorción
completa con borrado de la manual, la guarda de enlace repetido en 3+ y las exclusiones por
enlace y por huella.

**Rollback**: revertir el commit; borrar las opciones `mu_reviews_blocked` y
`mu_reviews_last_import`; el evento cron se elimina solo con
`wp_clear_scheduled_hook( 'mu_reviews_weekly_import' )` (o queda inerte al revertir el hook).
