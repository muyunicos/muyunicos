# 017 — Un solo bloque de idioma por editor

**Estado**: implementada (2026-10-17). **Rama**: `refactor/bloque-idioma-unico`.
**Origen**: idea del mantenedor. Antes había **tres** `.mu-hero-banner-lang-block` (ES, PT, EN) y se
ocultaban **enteros** al cambiar de idioma; por eso el toolbar de idioma no podía vivir junto a los
campos (se habría ocultado con su bloque) y ocupaba una barra propia (`row__head`) arriba del
contenido.

## La idea

Un **único bloque** por editor, con los **tres idiomas como variantes de cada campo**:
`.mu-hero-banner-variante[data-lang]` (una por idioma) y sólo la activa visible. El toolbar
(píldoras + variantes de IA + «Traducir») vive en la **línea del primer grupo de campos**
(«Texto»), donde no se mueve ni se oculta nunca. Es más simple que moverlo o duplicarlo.

## Tramo H · Editor de banners

| # | Cambio | Archivo |
|---|---|---|
| 1 | Un solo `.mu-hero-banner-lang-block`; cada campo (`$render_campo`) trae sus tres variantes con `data-lang` y `hidden`; los avisos por idioma (stale y «lo que dejes vacío») viajan como `[data-lang][hidden]`; el toolbar se mudó a la línea del primer grupo (`$render_toolbar_idioma`) | `inc/hero-banners.php` |
| 2 | `setLang()` deja de ocultar bloques: togglear **variantes** con `prop('hidden')`. Los selectores pasan de `.mu-hero-banner-lang-block[data-lang=X]` a `.mu-hero-banner-variante[data-lang=X]` (misma forma, un solo reemplazo) y el selector de imagen se acota a la variante | `js/admin-hero-banners.js` |
| 3 | La línea del grupo pasa a `display:flex; justify-content:space-between`; el toolbar pierde borde/margen y no hereda la tipografía del rótulo; `.mu-hero-banner-variante:not([hidden]) { display: block }` (un `display:block` a secas anularía el atributo `hidden`) | `css/admin-hero-banners.css` |
| 4 | 12 aserciones nuevas: un solo bloque · 30 variantes (10 campos × 3) · el toolbar en la línea del primer grupo · los `name` de las tres lenguas **intactos** · **ningún campo `disabled`** · la variante del idioma activo visible y las otras dos ocultas | `specs/013-…/harness/arnes-render-hero.php` (**130 OK**) |
| 5 | 3 aserciones nuevas: variantes ocultas con `hidden` (no con `display`) · la línea del grupo es flex · el toolbar ya no es una barra con borde | `specs/015-…/harness/css-check.js` (**85 OK**) |

## Tramo R · Modal de reseñas del front

`inc/ui.php` imprime el modal de administración de reseñas (sólo con `mu_can_manage_reviews()`), que
tenía el mismo patrón. Ahora es **un** `.mu-review-lang-block` con las tres variantes del textarea.

| # | Cambio | Archivo |
|---|---|---|
| 1 | Un bloque con `data-lang` + `hidden` por textarea (`text`, `text_pt`, `text_en`: **`name` e `id` intactos**) | `inc/ui.php` |
| 2 | `setLang()` togglea `[data-lang]` con `hidden` — y además el **`required` viaja con la visibilidad** | `js/reviews-admin-modal.js` |
| 3 | 5 aserciones nuevas: la variante activa visible, las otras ocultas y el `required` del español según el idioma | `specs/007-…/harness/editor-resenas.js` (**41 OK**) |

### La trampa que apareció en este tramo (y que arreglamos)

El textarea de español tiene `required`. Con los tres bloques antiguos, al editar en PT el campo
requerido quedaba oculto y algunos navegadores **bloquean el submit con un mensaje invisible**
(«An invalid form control with name='text' is not focusable»). Ahora `setLang()` pone
`required` **sólo** cuando el español está a la vista: el submit nunca queda bloqueado por un campo
que el usuario no puede ver ni enfocar, y la validación sigue existiendo cuando de verdad importa.

## Reglas que quedan escritas (y cubiertas por arnés)

1. **`hidden`, nunca `disabled`**: un campo `disabled` no viaja en el POST — se perderían las
   traducciones al guardar. Hay una aserción que cuenta los `disabled` en campos: debe dar **0**.
2. **Los `name` no cambian** en ninguno de los dos editores ⇒ el contrato de guardado y el front
   quedan intactos.
3. La variante visible se logra con `:not([hidden])`, jamás con un `display:block` incondicional
   (anularía el atributo y se verían los tres idiomas a la vez).

## Divergencia declarada

El **formulario de reseñas del front** (`inc/ui.php`, el que usa el cliente) mantiene su estructura
propia: no tiene campos por idioma, así que la idea no aplica ahí.

## Verificación

```
CSS 85 · 013 130 + 17 · 012 102 + 18 · 010 99 + 38 · 011 18 · 008 30 + 17 · 006 54 · 007 41
php -l (hero-banners, ui) y node --check (ambos JS) limpios
git diff --name-only → nada bajo Tema-GeneratePress/
```

## Fase B manual (mantenedor)

En **ambos** editores: escribir en ES, pasar a PT, escribir distinto, **guardar estando en PT y
recargar** — las tres versiones tienen que seguir ahí (si algo hubiera quedado `disabled`, se
perderían justo así). Y en el panel de banners: el rótulo «Texto» con la botonera a su derecha, que
**no se mueve ni desaparece** al alternar ES/PT/EN.
