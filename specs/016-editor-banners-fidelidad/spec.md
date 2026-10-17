# 016 — Editor de banners: fidelidad del lienzo y densidad

**Estado**: implementada y desplegada (2026-10-17). **Rama**: `fix/editor-banners-fidelidad`.
**Origen**: cinco observaciones del mantenedor sobre el panel ya en producción (014).

## 1. `data-has-translation="1"` siempre, aunque no hubiera traducciones — bug real

- **Servidor (correcto)**: `mu_hero_banners_sanitize_one()` (`inc/hero-banners.php:135`) persiste
  **vacío** lo idéntico al español (sobrescritura dispersa), así que
  `mu_hero_banner_lang_has_translation()` («algún campo no vacío») acierta.
- **Cliente (el culpable)**: `actualizarPunto()` comparaba el campo del idioma con el español y
  **un campo vacío difiere** (`'' !== 'texto ES'`), así que marcaba la píldora en **todas** las
  filas y pisaba el valor correcto que ya venía del PHP.
- **Arreglo**: helper puro `tieneContenidoPropio(valor, base)` = `v !== '' && v !== base` (con
  `trim()` en los dos lados, porque el guardado sanea y recorta). Expuesto en
  `window.muHeroBanners` y cubierto por **10 aserciones** nuevas en el arnés JS de 010
  (vacío · igual · distinto · español vacío · espacios · `undefined`/`null`).

## 2. La vista previa no se veía como la home — 4 causas concretas

| # | Causa | Arreglo |
|---|---|---|
| 1 | Las variables locales del hero (`--mu-hero-h-desktop`, `--mu-hero-transition`, `--mu-hero-eyebrow-bg` = **el rosa #f14085 del eyebrow**) se declaraban en `.mu-hero-promo`, la **sección**, que el lienzo no incluye (entra directo en `.mu-hero-promo-slider`): en la preview el eyebrow salía sin fondo, el slider sin altura mínima y el slide con la transición por defecto | Se declaran en `.mu-hero-promo-slider`, la raíz que **ambos** contextos comparten. Verificado con `Select-String`: el slider y sus descendientes son los únicos consumidores (`.mu-hero-promo-dots` no las usa) ⇒ **el front no cambia** |
| 2 | Los CTA copiaban `.mu-btn*` pero **no sus estados** | Se suman `.mu-btn:focus-visible`, `.mu-btn-primary:hover`, `.mu-btn-outline:hover` con alcance al lienzo (misma deuda declarada: `style.css` es la fuente de verdad) |
| 3 | El componente trae un `@media (max-width: 968px)` que en el admin responde al ancho de la **ventana**: con la ventana angosta y «Escritorio» elegido, la preview mostraba el diseño móvil | Bloque **espejo** `@container (min-width: 969px)` con los valores de escritorio del componente |
| 4 | *(declarado, no verificado)* la tipografía display del título la encola el tema padre **sólo en el front** (`Tema-GeneratePress/inc/general.php:259`); en el admin puede degradar a la fuente de reemplazo | Se mantiene el `wp_enqueue_style('generate-google-fonts')` y queda declarado en §8: si el mantenedor ve otra tipografía en la preview, se registra el mismo handle en la pantalla del panel |

**Autoverificación (nace de este bug)**: el arnés de CSS de 015 ganó **12 aserciones** que
hubieran cazado los puntos 1-3: toda variable usada por el componente tiene que estar declarada en
un ancestro que el lienzo incluya · las 3 variables del hero tienen que vivir en el slider · cada
`@media` del componente necesita su espejo `@container` · los `.mu-btn*` del hero tienen que estar
en el lienzo **con estados** · la caja del bloque de idioma aplica a los tres idiomas.
**Validado en orden**: contra el CSS anterior (`d6db174`) los tres chequeos **fallan**; con el
arreglo pasan.

## 3. Densidad: el hueco de abajo y los anchos

- **Causa del hueco**: el lienzo se colocaba en `grid-row: 1 / span 30`, lo que crea **30 filas
  implícitas**; el contenido vive en las filas 1-2 y el resto quedaba vacío (por eso al
  desactivar esa regla el contenido «subía»).
- **Reparto acordado con el mantenedor** (≥1100px): fila 1 = **bloque de idioma (hasta 420px) +
  lienzo (hasta 400px) lado a lado**; fila 2 = **ajustes y vigencia a todo el ancho**, que es la
  fila que necesita ≥720px (son filas y una tabla). El lienzo **deja de ser pegajoso** (queda
  arriba, junto a los campos).
- `grid-template-columns: minmax(320px, 420px) minmax(320px, 400px) minmax(0, 1fr)`: los topes se
  respetan cuando hay lugar y las columnas **encogen** en vez de desbordar cerca de 1100px.
- La tabla de vigencia se envuelve en `.mu-hero-banner-tabla-scroll { overflow-x: auto }`: en
  paneles angostos se desplaza en lugar de romper el diseño.
- El bloque de idioma lleva el tope **sólo** en este modo: en pantalla angosta sigue a todo el ancho.

## 4. El bloque de idioma español no tenía la caja de PT/EN

La caja (borde + fondo + padding) estaba declarada sólo para `[data-lang="pt"]` y `[data-lang="en"]`.
Ahora aplica a `.mu-hero-banner-lang-block`: **mismo tratamiento visual en los tres idiomas**.

## 5. «Todavía no hubo purgas por borde de fecha» era correcto, pero no verificable

No había pasado ningún borde desde el deploy (el cron corre: el evento queda agendado). Se agrega
al panel **«Próximo borde: dd/mm/aaaa hh:mm»** (de `wp_next_scheduled`), o *«Sin borde agendado
(ninguna ventana de fecha tiene fin)»*, junto a la última purga: el estado del cron se lee sin
esperar ni abrir logs.

## Criterios de aceptación

1. La píldora de idioma queda **sin punto** en un banner sin traducciones, y con punto al escribir
   un texto propio (verificación manual en Fase B).
2. La vista previa muestra el eyebrow **rosa**, los CTA reaccionan al hover y no cambia a móvil
   cuando la ventana del admin es angosta.
3. El panel no tiene hueco debajo del contenido; idioma (≤420) y lienzo lado a lado; ajustes y
   vigencia a todo el ancho.
4. Los tres bloques de idioma se ven como el mismo grupo.
5. Los arneses siguen verdes: CSS **82**, 013 **118 + 17**, 012 **102 + 18**, 010 **99 + 38**,
   011 **18**, 008 **30 + 17**, 006 **54**; `php -l` y `node --check` limpios.

## Desviación del plan declarada

El plan partía la implementación en dos commits (fidelidad / densidad). Se hizo **un solo commit**:
los cambios se cruzan en el mismo archivo (`css/admin-hero-banners.css`) y separarlos habría exigido
un staging parcial artificial. El orden de revisión es el de este documento.
