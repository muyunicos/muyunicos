# Spec 020 — Temas visuales de la galeria, filtros del visor y cotizador versionado

**Estado**: en curso (2026-11-07). Nace de la spec **019** (motor unico: `MU.coleccion` =
ambito 1, `MU.visor` = ambito 2), que queda cerrada ahi. Detalle de tareas en `tasks.md` y de
fases en `plan.md`.

## 1. Contexto y problema

La 019 unifico el MOTOR pero dejo **un solo aspecto**: el mismo visor y la misma tira se ven
igual en la ficha, en la biblioteca del plugin y en el cotizador. Medido en el laboratorio:

1. **El cotizador perdio su aspecto.** Su lightbox propio (`MiniGallery.tsx`) tenia caja
   blanca, precios en **dos columnas** con jerarquia (etiqueta chica + valor grande), `% OFF`
   **al lado del precio** y el boton "Personalizar" en la **misma fila** que los precios. Con
   el motor unico eso desaparecio: los precios se apilan, el badge queda suelto arriba y las
   acciones van en una fila aparte al pie.
2. **El tono no era el problema del motor**: `tono` es una **opcion** (`claro`/`oscuro`) y se
   pasa al abrir. El cotizador estaba abriendo en `oscuro` por decision del adaptador.
3. **La tira del cotizador sigue siendo React**: solo el visor delega en el tema. La tira se
   dibuja con `MiniGallery.tsx` (y su precio "compacto" no tiene equivalente en el motor).
4. **El visor no puede filtrar**: quiere pills arriba (TODOS / Comprados / Guardados) y hoy
   `segmentos` solo avisa (contrato `galeria.md` C1.2), no recorta.
5. **El componente se apoya en CSS ajeno**: el visor usa `.mu-modal-close` de
   `global-ui.css` (`position: absolute`, `color:#6b7280`, hover gris) y lo neutraliza a
   medias; en una pagina con otro CSS (Tailwind del cotizador, `.button-primary` de WP) se
   puede pisar.
6. **El cotizador no esta versionado**: el shortcode vive **solo en el servidor**
   (`mu_sticker_calculator_shortcode()`), el plugin del carrito vive solo en
   `wp-content/plugins/` y los assets se copian a `generatepress-child/assets/` (ver
   `CHECKLIST-DEPLOY.md` del repo `calculadora`). El laboratorio no tiene `/cotizador/`
   (404), asi que ninguna puerta de navegador del cotizador se puede correr.

## 2. Decisiones

- **D1 — Un TEMA es CSS, no configuracion.** El tema no agrega claves de datos ni cambia el
  DOM: es una hoja de estilos con un bloque por tema. `config.json`/opciones no se tocan.
- **D2 — El tema se resuelve por ATRIBUTO.** `data-mu-tema="cotizador"` en el contenedor (o
  en cualquier ancestro); el motor lo resuelve con `closest()` y lo **publica** en la raiz de
  la coleccion y en el overlay del visor. Sin atributo = tema `base`.
- **D3 — Un tema cubre los DOS ambitos.** El mismo `data-mu-tema` pinta la tira (ambito 1) y
  el visor (ambito 2): el CSS entra por `[data-mu-tema="x"] .mu-gal-…` y por
  `.mu-gal-visor[data-mu-tema="x"] …`.
- **D4 — Un tema NO cambia datos, opciones ni estructura.** Solo presentacion: colores,
  tamanos y disposicion de lo que YA existe en el DOM. Si algo no se puede con CSS, se mejora
  el MOTOR (D5), no se inventa una opcion por tema.
- **D5 — `.mu-gal-pie` agrupa meta y acciones.** El motor envuelve `.mu-gal-meta-caja` y
  `.mu-gal-acciones` en un contenedor comun; el tema `base` los apila (aspecto de hoy) y otro
  tema los pone en la misma fila. Asi las combinaciones titulo/descripcion/precios/acciones
  se resuelven con CSS sin duplicar el motor.
- **D6 — Filtros en el visor = el mismo criterio de C10.** `filtros` + `filtroActivo`
  recortan los items con `MU.puro.filtrar/valoresFiltro`; los pills van arriba
  (`.mu-gal-segs`) y **filtran de verdad** (acotan indice, contador, banda y precarga).
  `segmentos` sigue siendo **informativo** (C1.2 se mantiene) y se agrega `filtros`.
- **D7 — El componente PISA el CSS que presta.** El visor deja de reusar `.mu-modal-close`
  (su cerrar es `mu-gal-cerrar` autosuficiente) y las acciones se refuerzan como
  `.mu-gal-acciones .mu-gal-accion` para que `.button`/`.button-primary` de WP no las tiña.
- **D8 — El cotizador se versiona en el TEMA.** `inc/cotizador.php` con
  `mu_sticker_calculator_shortcode()` (mismo id `mu-sticker-calculator-root`, mismas URLs,
  `filemtime` como version), `mu_galeria_encolar()` adentro y `data-mu-tema="cotizador"`; los
  assets (`js`, `css`, `datos_config_etag.php`, `guardar_datos.php`) viven en
  `muyunicos/assets/`. **`datos_config.json` NO se versiona** (es dato del admin).
- **D9 — La calculadora se rehace NATIVA.** Puede modificarse por completo: la tira se monta
  con `MU.coleccion` y el visor con `MU.visor({tono:'claro', filtros:'estado'})`; se retiran
  `MiniGallery.tsx` / `ImageLightbox.tsx` como galeria. Sin parches de adaptacion.
- **D10 — Lo que NO es un tema.** `tono` (es opcion), el marco oscuro del lienzo y el slot
  `badge` en la celda de la tira. El `% OFF` que el cotizador extraña es el
  `mu-gal-badge--tienda` **del visor**, y va al lado de `mu-gal-precio-ya` por CSS.

## 3. Temas iniciales

| Tema | Donde se usa | Que aporta |
|---|---|---|
| `base` | ficha, biblioteca del plugin, demo | **nada**: es el aspecto actual (sin CSS) |
| `cotizador` | `/cotizador/` | precios en **dos columnas** con jerarquia, `% OFF` junto al precio, **zoom** en hover, etiqueta/descripcion con **velo**, **precio compacto** en la tira y acciones en la misma fila que los precios |

Contrato tecnico: `contracts/temas.md`. Filtros del visor: `contracts/visor-filtros.md`.
## 4. Fuera de alcance

- Zoom/pan dentro del marco y `marco:'oscuro'`: **descartados** por el usuario.
- Temas por producto o por PDF: un tema es por **contexto de pagina**, no por dato.
- Cambiar `MU.puro.celdas/carga/ventana`: la aritmetica queda como esta (spec 019).
- Migrar los visores de la cuenta del comprador (`spec 030` P1): sigue fuera.

## 5. Criterios de aceptacion

- **SC-001** Un contenedor con `data-mu-tema="cotizador"` pinta la tira con el tema y el visor
  que abre hereda el mismo tema, sin pasar ninguna opcion nueva.
- **SC-002** El tema `base` reproduce **exactamente** el aspecto de hoy: la puerta
  `ficha-pedido.browser.js` sigue en 52/52 y el demo no cambia.
- **SC-003** En el visor con tema `cotizador`: los precios se ven en dos columnas, el badge
  `% OFF` queda junto al valor y las acciones comparten fila con los precios (medido, no
  "a ojo").
- **SC-004** `MU.visor({filtros:'estado'})` con TODOS / Comprados / Guardados recorta la lista
  de verdad, el contador y la banda quedan coherentes y "TODOS" vuelve a la lista completa.
- **SC-005** Ninguna clase ajena manda sobre el componente: `.mu-gal-cerrar` se ve igual con
  `global-ui.css` y con el CSS del cotizador cargado, y `.button-primary` no tiña las
  acciones.
- **SC-006** `/cotizador/` existe en el laboratorio con el motor encolado (`window.MU`), el
  plugin del carrito activo y la pagina publicada; `cotizador-pedido.browser.js` corre con
  `PMU_COTIZADOR_URL`.
- **SC-007** La tira del cotizador la pinta `MU.coleccion` (motor del tema) y no
  `MiniGallery.tsx`; el visor es `MU.visor`.