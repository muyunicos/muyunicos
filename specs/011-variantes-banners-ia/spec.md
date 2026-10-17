# 011 — Hero banners: variantes de traducción (personas) en el editor

**Estado**: implementado (2026-10-17). Rama `feat/fechas-banner-por-pais` (junto con 010).

## Problema

El servicio de traducción puede devolver **una versión por persona** (`variants[]`, ver
`inc/ai-translate.php`), y el editor de reseñas ya ofrece botones numerados `1·2·3…` para
elegir cuál queda. El editor de banners, en cambio, consumía sólo `item.translation` —que el
servicio define como `variants[0]`—, así que con 3 personas y 3 variantes configuradas:

- el servicio generaba (y cacheaba) 3 versiones por campo,
- el banner aplicaba **siempre la primera persona**,
- y las otras dos no había forma de verlas ni de usarlas.

El único camino era reordenar las personas del ámbito o dejar `variants = 1`, decisiones que
afectan a la configuración global del ámbito y no al banner que se está editando.

## Cambios

| # | Cambio | Archivos |
|---|---|---|
| 1 | **Contenedor de variantes en la cabecera de la fila** (`.mu-ai-variants`, componente compartido con el editor de reseñas) al lado del botón «Traducir». | `inc/hero-banners.php` |
| 2 | **Botones numerados por variante** con el nombre de la persona en el `title`; el número activo es la versión que quedó en los campos del idioma activo. | `js/admin-hero-banners.js` |
| 3 | **Aplicar una variante es de la fila completa**: el índice elegido se aplica a los 6 campos de texto a la vez (las variantes vienen alineadas por índice dentro del lote). | `js/admin-hero-banners.js` |
| 4 | **La cantidad de versiones la decide el ámbito** (`personas` × `variants`, vía el servicio): el editor **no** pide un número fijo. Sin personas no hay botones y queda la traducción estándar. | `js/admin-hero-banners.js` |
| 5 | **Segundo clic = tanda nueva** (`force=1` cuando ya hay una tanda para ese idioma), sin volver a pagar por la anterior. | `js/admin-hero-banners.js` |
| 6 | **La tanda se descarta al cambiar de idioma** (es de un idioma concreto) y los campos faltantes de una versión se informan en el estado. | `js/admin-hero-banners.js` |
| 7 | **Funciones puras nuevas** (`variantesDeFila`, `mensajeVariante`) expuestas para el arnés. | `js/admin-hero-banners.js` |
| 8 | **Arnés de la feature** con las funciones puras. | `specs/011-variantes-banners-ia/harness/variantes-js.js` |

### Decisiones

- **No se manda `variants` desde el banner** (a diferencia del editor de reseñas, que pide
  `variants=5`): así manda la configuración del ámbito y nadie paga por versiones que no pidió.
  Para tener variantes alcanza con cargar **personas** en el ámbito `hero_banner`.
- **El botón aplica a toda la fila, no campo por campo**: dividir la elección por campo
  multiplicaría los clics y las combinaciones posibles sin aportar nada (cada índice es *una
  persona* escribiendo todo el banner).
- **Nada se guarda hasta «Guardar cambios»**: el servicio nunca escribe (contrato C-1 de 006) y
  el editor tampoco.
- **Sin variantes no cambia nada**: el botón sigue mostrando el mensaje de siempre y la
  navegación no consume solicitudes.
- **Un campo sin esa versión no se inventa**: se lista en `faltantes` y el estado avisa
  (“Sin texto en: eyebrow, cta_texto”). Es la pista honesta de que el modelo omitió una persona
  en ese campo (el servicio descarta las variantes vacías).

## Criterios de aceptación

1. Con personas cargadas en `hero_banner` y 2+ variantes configuradas, «Traducir» completa los
   campos y aparecen los botones `1·2·3…` con la persona en el `title`; el `1` queda activo.
2. Hacer clic en `2` reemplaza los 6 campos del idioma activo por la segunda versión, deja el
   botón activo y refresca el punto del idioma y los placeholders; el estado nombra la persona.
3. Con el ámbito sin personas, no aparecen botones y el comportamiento es el de hoy
   (una sola traducción).
4. Cambiar de idioma descarta los botones (no se mezclan versiones de idiomas distintos).
5. Un segundo clic en «Traducir» con la misma tanda pide una nueva (`force`) y vuelve a ofrecer
   botones; la tanda anterior se sirvió de caché.
6. Si la respuesta omite una versión en algún campo, el estado lo dice y ese campo **no** se toca.
7. Nada bajo `Tema-GeneratePress/`; sin CSS/JS inline nuevo (los botones son dinámicos porque la
   cantidad la decide la respuesta, igual que en el editor de reseñas).

## Fuera de alcance

- Elegir la persona de forma independiente por campo.
- Traducir un idioma distinto del activo en el mismo clic.
- Prompt de “pedir más variantes” desde el banner (para eso está el ámbito).
- Guardar cuál variante quedó aplicada (el dato que vale es el texto final, que ya se guarda).

## Limitaciones declaradas

- **Verificación del panel**: la interacción de los botones no es automatizable en este proyecto
  (jQuery + navegador); se verifican las funciones puras con arnés y el resto a mano (Fase B del
  quickstart, misma deuda declarada para 008/010).
- **Índice ↔ persona**: el servicio arma `variants[]` descartando los textos vacíos, así que si el
  modelo omite una versión las siguientes **se corren de índice** y el `title` puede no coincidir
  con la persona real. Se mitiga mostrando los campos faltantes; corregirlo del lado del servicio
  es una decisión aparte (afecta también a reseñas).
