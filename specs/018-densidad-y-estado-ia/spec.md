# 018 — Densidad del panel y estado de la IA

**Estado**: implementada (2026-10-17). **Rama**: `feat/densidad-y-estado-ia`.
**Origen**: tres observaciones del mantenedor sobre el panel en producción.

## A · El sobrante del panel va al lienzo, no a una celda vacía

La grilla de 016 usaba **tres** columnas (`minmax(320px, 420px) minmax(320px, 400px) minmax(0, 1fr)`):
la tercera existía sólo para que la fila de ajustes llegara al ancho completo con `1 / -1`, y se
comía todo el sobrante. Medido (panel = `.wrap` sin `max-width` propio, ancho del grid ≈ ventana
− ~200px):

| Ventana | Columna 3 | Lienzo | Escala |
|---|---|---|---|
| 1100px | ~40px | 400px | 0,31 |
| 1440px | ~380px **vacía** | 400px | 0,31 |
| 1600px | ~540px **vacía** | 400px | 0,31 |

Ahora son **dos** columnas: `minmax(320px, 420px) minmax(400px, 1fr)` ⇒ el sobrante engorda **la
vista previa** (a 1440px ≈ 820px ⇒ escala **0,64** en lugar de 0,31) y la fila de ajustes sigue
llegando al ancho completo con `1 / -1` ✔. **No hubo que tocar el JS**: `escalarLienzo()` ya usa el
ancho real del lienzo y `escalaCanvas()` nunca agranda más allá de 1:1.

## B · El estado de la IA: fuera el «verde»

`setAiStatus()` mostraba el éxito en el renglón de la fila («Listo: N campos traducidos…») y lo
pintaba de verde. Ahora:

- **éxito** ⇒ el mensaje va al **`title` del botón «Traducir»** (se lee al pasar el mouse) y el
  renglón queda limpio;
- **problemas** (el servicio no devolvió traducción, o cree que ya está en el idioma) ⇒ siguen
  visibles, en rojo y cortos;
- al limpiar se **restaura el tooltip original** del botón (guardado la primera vez en
  `data('tituloBase')`).

Además el `max-width` del estado bajó de **320px a 200px**: desde 017 el toolbar vive en una
columna de 420px y con 320px obligaba a envolver en dos/tres líneas. Los **textos no cambiaron**
(por eso los arneses de 008/011 que verifican `mensajeTraduccion` y `mensajeVariante` siguen
verdes sin tocarlos): sólo cambia **dónde** se muestran.

## C · Cada idioma conserva sus opciones de traducción

**Causa exacta** de lo reportado («aprieto ES para ver el original y pierdo las traducciones»):
`setLang()` llamaba a `limpiarVariantes($row)` en **cada** cambio de idioma, y esa función hacía
`$row.data('aiBatch', null)` + vaciaba el contenedor de botones. Los **textos** nunca se perdían
(están en inputs con `hidden`, siguen en el DOM y viajan en el POST); lo que se descartaba eran las
**opciones 1·2·3** que ya se habían generado.

Ahora:

- las tandas se guardan **por idioma** (`$row.data('aiBatches')`, con `tandaDeIdioma()` pura y
  verificada por arnés);
- al cambiar de idioma se **repintan las del idioma activo** en vez de descartarlas;
- `aplicarVariante()` usa la tanda del idioma activo y **recuerda qué número quedó puesto en cada
  idioma** (`activa`), así al volver el botón marcado es el correcto;
- `limpiarVariantes()` se retiró (era el descarte automático; ya no se llama desde ningún lado).

## Reglas / invariantes

1. **`hidden`, nunca `disabled`** (017) sigue vigente: los textos de los tres idiomas viajan siempre.
2. Un solo `.mu-hero-banner-lang-block` (017) ⇒ el toolbar no se mueve ni se oculta.
3. El lienzo **nunca** tiene tope de ancho rígido: su escala la decide su propio ancho.

## Verificación

```
CSS 86 OK (+1) · 011 28 OK (+10) · 013 (130 + 17) · 012 (102 + 18) · 010 (99 + 38)
008 (30 + 17) · 007 41 · 006 54   php -l y node --check limpios
git diff --name-only → nada bajo Tema-GeneratePress/
```

Las aserciones nuevas del arnés de CSS: el lienzo es flexible y **falla si alguien vuelve** a la
tercera columna vacía. Las del arnés de 011: `tandaDeIdioma()` devuelve la del idioma pedido,
recuerda el número activo, normaliza `PT` → `pt`, y **consultar un idioma no borra los otros** (el
bug, ahora cubierto).

## Fase B manual (mantenedor)

1. La vista previa se ve **más grande** y sin espacio muerto a la derecha (a 1440px el lienzo ocupa
   todo el ancho disponible).
2. Traducir ya **no** deja un mensaje verde permanente: el resultado queda en el tooltip del botón
   «Traducir»; si algo falla, **sí** avisa en rojo (y corto).
3. Generar variantes en PT, pasar a **ES** a mirar el original y volver a **PT** ⇒ los botones
   **1·2·3 siguen ahí** (y el marcado es el que habías aplicado). En EN, las suyas.
