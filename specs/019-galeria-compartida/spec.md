# Spec 019 — Galeria compartida del tema (MUGaleria)

**Estado**: F0 + F1 (2026-11-05) en produccion de tema, **F2.5b** (modelo v2: chip
acumulativo, `navegable` auto, `tope` en celdas, cota `filas`), **cota exacta**, **V1** y **V2
del visor** (2026-11-07: playground propio en `tests/galeria-demo.html`, puerta
`tests/visor.browser.js` en 66/66 con 0 hallazgos — error de imagen con reintento, `icono` en
las acciones, meta rica normalizada y `MU.visor.actualizar()`) implementadas. Pendiente:
SC-003 en el laboratorio (T022) y las fases F2 (plugin) y F3 (calculadora), que NO entran en
esta spec. Detalle en `tasks.md`.

**F5 (temas visuales, filtros del visor y cotizador versionado)** vive en
[`specs/020-temas-galeria`](../020-temas-galeria/spec.md): un tema es **CSS** (no toca
opciones ni datos), los filtros del visor reusan C10 y el cotizador se versiona en el tema.

## 1. Contexto y problema

Hoy conviven **cuatro** formas distintas de "galeria" en el ecosistema, cada una con su
propio codigo y su propio aspecto:

| Superficie | Implementacion | Problema |
|---|---|---|
| Calculadora (repo hermano `calculadora`) | `MiniGallery.tsx`, `TiraIdeas.tsx`, `ImageLightbox.tsx` | La mas cuidada, pero **React/TS**, y **copia** los tokens del tema (`--primario`, `--mu-radius-*`) |
| Plugin Personalizador PDF | `assets/visor-pmu.js`, `biblioteca-pmu.js::pintarTira`, `cargador-pmu.js::tira` | Tres implementaciones de la misma tira; la galeria de la ficha se dibuja con `style.cssText` inline (no hay CSS) |
| Tema — miniaturas de producto | `css/product.css` + `js/product.js` | El **aspecto** correcto, pero el drag-to-scroll esta duplicado en `js/global-ui.js:400-493` |
| Tema — modal | `css/components/global-ui.css:657-747` | El **modal** ya existe (`.mu-modal-*`), pero ninguna galeria lo usa |

El tema es la capa visual del sitio y sus tokens (`style.css:14-75`) ya son la fuente de los
colores, radios, sombras y transiciones. La galeria unica debe vivir aqui.

## 2. Decisiones

- **D1 — Una sola galeria, tres piezas**: `visor` (modal), `tira` (miniaturas) y `tarjeta`
  (catalogo con metadatos). No se crea un cuarto componente.
- **D2 — El modal es el del tema**: `MUGaleria.visor` reutiliza `.mu-modal-overlay` /
  `.mu-modal-container` / `.mu-modal-close` y la clase de estado `is-visible`. **No** se crea
  un sistema de modal nuevo.
- **D3 — Declarativa por defecto**: cualquier galeria futura es HTML + atributo
  `data-mu-galeria` (con `data-mu-galeria-items` JSON opcional) y `MUGaleria.autoInit()`.
  Agregar una galeria no debe requerir JS nuevo.
- **D4 — Adaptadores, no datos**: `MUGaleria.desdeWoo(raiz)` y `MUGaleria.desdeEnlaces(el)`
  leen el DOM existente. La galeria **nunca** pide datos al servidor ni guarda nada.
- **D5 — Dos tonos**: `claro` (tienda: blanco/slate) y `oscuro` (visor fullscreen que hoy usa
  el plugin). Es el mismo componente: solo cambia la clase de tono.
- **D6 — El aspecto de la tira lo manda `css/product.css`**: desktop `grid auto-fill
  minmax(70px,1fr)`, movil `flex nowrap + overflow-x:auto + scroll-snap-type: x mandatory`,
  scrollbar oculta, y la miniatura activa con `border-color: var(--primario)` +
  `box-shadow: var(--mu-shadow)`. La galeria hereda ese criterio; no inventa otro.
- **D7 — Fin de la duplicacion del arrastre**: `js/product.js` deja de tener su drag-to-scroll
  y delega en `MUGaleria.arrastrar()` (el gesto vive en un solo lugar).
  `initCarousels()` (`global-ui.js`) **no se toca** en esta spec: otro contrato
  (`.mu-carousel-wrapper`).
- **D8 — Galeria de WooCommerce**: se **intercepta** el trigger
  (`.woocommerce-product-gallery__trigger`) y se abre `MUGaleria` con las
  `data-large_image` del slider. **No** se quita `wc-product-gallery-lightbox` en F1: es un
  cambio de comportamiento global y se decide con prueba real en el laboratorio.
- **D9 — Registro y encolado condicional**: `inc/galeria.php` **registra** `mu-galeria` /
  `mu-galeria-css` y los encola en `is_product()`; ademas expone `mu_galeria_encolar()`
  para que cualquier consumidor (plugin, shortcodes, plantillas) los encole por handle.
  Nada de peso extra en el resto del sitio.
- **D10 — El tema no aporta datos**: la galeria no conoce endpoints, nonces ni rutas. Quien
  tiene los datos (plugin, calculadora, cuenta) le pasa `items`.
- **D11 — Dependencia blanda**: el plugin y la calculadora detectan `window.MUGaleria`; si no
  esta, siguen con su visor actual. El plugin es distribuible (`Stable tag`, GPL) y no puede
  depender del tema.
- **D12 — Cache-bust**: fuente unica `Version:` de `style.css` (`$ver` de
  `mu_enqueue_assets()`). Tocar `js/galeria.js` o `css/components/galeria.css` obliga a
  bump de la cabecera.

### Enmienda 2026-11-05 — tira v1.1

- **D13 — `modo` por ANCHO DEL CONTENEDOR, no por viewport**: el criterio anterior
  (`@media (max-width: 768px)`) dejaba una tira angosta en un monitor grande **sin scroll**.
  `auto` mide `clientWidth`: si todo cabe en una linea -> `linea`; si no y el contenedor
  mide >= 480 px -> `grilla`; si no -> `linea` con scroll (nunca inaccesible). Se repinta
  con `ResizeObserver` conservando scroll y foco.
- **D14 — La miniatura es un CONTENEDOR**: `<div class="mu-gal-mini">` con
  `mu-gal-mini-abrir` + `mu-gal-mini-borrar`. Un boton dentro de un boton es HTML
  invalido, y el tachito lo exige.
- **D15 — `min` dibuja ranuras a llenar**: `min` ranuras vacias (`mu-gal-mini--vacia`,
  borde punteado como `cpmu-zona` del plugin) que disparan `onRanura(indice)`. Con
  `contador: true` se muestra "1 de 3". Reusa el lenguaje visual del cargador del plugin
  para que F2 no tenga dos implementaciones del minimo.
- **D16 — El tachito confirma**: `borrar: true` + `confirmar` (`true` = `confirm()`
  nativo, `false` = borra directo, funcion = confirmacion propia). Visible siempre en
  tactil (`@media (hover: none)`) y en hover/foco con puntero fino.
- **D17 — El componente no hereda bordes ni sombras del tema**: el tema y WooCommerce
  pintan bordes en imagenes dentro de enlaces y tarjetas; dentro de la galeria se
  neutralizan (`border/outline/box-shadow: 0`) para que el mismo HTML se vea igual en la
  tienda, en la cuenta y en el cotizador. Lo revelo la demo con el CSS del tema cargado.
- **D18 — Las flechas son `.mu-nav-btn`, del tema, y se encienden por NECESIDAD**: la
  galeria no tiene flechas propias. En el visor reusa `.mu-nav-btn prev/next` (con su SVG
  chevron) dentro del flex del cuerpo, y las oculta con `hidden` cuando hay una sola
  imagen. En los carruseles, `initCarousels()` decide con `scrollWidth/clientWidth` +
  `scrollLeft`: la flecha se enciende **solo si el track desborda y queda recorrido en ese
  extremo**. Antes la visibilidad era una media query por ancho de pantalla.

### Enmienda 2026-11-07 (bis) — dos modos de tamano del visor

- **D19 — El visor tiene DOS modos y la caja NO cambia al navegar**: `adaptive`
  (`<= 600 px`, pantalla completa) y `responsive` (`> 600 px`) dimensionado por el
  **item PROMEDIO** (`MU.puro.lienzo`: media geometrica de aspecto y area, acotada con
  `contain`). Retira `fit-content`, `ajustarFlechas()` y las clases
  `--auto`/`--completo`/`--justo`. `ancho:'completo'` pasa a significar "llenar el
  espacio disponible" y un numero en px ya no tiene efecto. Motivo medido: la caja media
  su `min-content` (el modal base es `width: 100%`), el marco desbordaba y las flechas se
  salian de la caja en la franja **811–820 px**.
- **D20 — El acabado no lleva rayas**: el visor no tiene lineas divisorias internas
  (cabecera, meta, acciones y banda se separan con **espacio**) y el activo de la banda es
  un anillo **interno** + `aria-current`, no un `outline` externo (la banda lo recortaba y
  se sumaba al borde del item = doble marco).

### Enmienda 2026-11-05 (bis) — flechas del sistema

- **D18** (arriba) reemplaza el media query de `.mu-nav-btn.prev/.next`: `display: flex` solo
  con `.is-visible`, y `[disabled]` oculta la flecha en el extremo.
- **El visor no desborda en pantallas angostas**: el marco de la imagen usa
  `flex: 1 1 0%` (no `auto`, que partia del tamaño natural de la imagen) y cuerpo/cabecera
  llevan `min-width: 0`. En 390 px se ven las dos flechas, el contador y el cerrar.

### Enmienda 2026-11-05 (ter) — modelo v2: `MU.coleccion` + `MU.visor`

Contrato: [contracts/coleccion.md](./contracts/coleccion.md). Reemplaza "tira + tarjeta".

- **D19 — Dos ambitos con nombre propio**: `MU.coleccion` (galeria embebida: catalogo,
  producto, opciones, cotizador) y `MU.visor` (modal de ampliacion, con la coleccion como
  listado inferior). `MUGaleria` queda como alias una release.
- **D20 — Un item con SLOTS**: el item se dibuja segun `slots` (imagen, etiqueta, favorito,
  eliminar, titulo, descripcion, precio, acciones, estado). `preset` (`mini`/`tarjeta`/
  `producto`/`opcion`) es el atajo, y `slots` lo pisa.
- **D21 — Aritmetica de celdas pura** (`MU.puro.celdas`): las imagenes van primero; el chip
  cuenta **solo items ocultos** y **reserva** una celda; los huecos obligatorios compiten y
  la invitacion solo rellena si sobra; **`tope` gana sobre `min`**.
- **D22 — Los huecos los cuenta el PIE, no el chip**: `MU.puro.pie` dice "Carga 4 imagenes"
  / "Puedes agregar N mas" / "Maximo alcanzado". Un chip "+N" nunca habla de huecos.
- **D23 — `capacidad`**: maximo de cargas, distinto de `tope` (maximo de **CELDAS**
  visibles: imagen + chip + hueco, igual que en `MU.puro.celdas`). Con `min 4` /
  `capacidad 8` / `tope 3` la secuencia es la tabla del contrato (C1).

- **D24 — La cota define el chip; sin cota no hay chip**: en `grilla` la ventana sale de
  `filas` (manda), si no `alto`, si no un marco que **recorta de verdad**
  (`scrollHeight > clientHeight`). Si ninguna acota, la coleccion crece con el contenido:
  se pintan TODAS las filas y `navegable:'auto'` equivale a `true` (sin chip). En `fila` la
  cota es siempre el **ancho** (una linea). `filas` es una cota **DECLARADA** y basta por si
  sola (no necesita `alto` ni que el marco recorte: el marco medido mide el recorte que esta
  misma galeria acaba de hacer y usarlo como presupuesto clavaba la vista en una fila).
  `filas`/`alto` fijan tambien el ALTO del marco
  (`filas*altoCelda + (filas-1)*gap`, con `alto` como maximo) y el **pie** se resta siempre:
  el marco nunca desborda el bloque (`MU.puro.ventana`, contrato C9).
  **La fila la manda la cota** (D24bis): cuando la cota existe, la linea publica
  `data-cota` + `--fila-alto` (alto de celda **real**, medido tras insertar la linea, y
  re-medido cuando asienta la fuente del tema) y el CSS fija `grid-auto-rows` con ese
  numero: la celda OBEDECE (`max-height` + `overflow`), asi `filas*alto + gaps` es exacto
  y el grid no puede desbordar ni abrir scroll interno. Sin cota las filas crecen con el
  contenido (comportamiento historico).

- **D24ter — El chip carga una PANTALLA y cierra fila** (2026-11-07): el paso de una tanda es
  `caben` (una pantalla de celdas), **no** `capacidad`. `capacidad` descuenta los huecos y la
  celda del chip, asi que usarlo de paso corria el residuo `mod columnas` y el chip terminaba
  solo en una fila nueva (con 3 columnas, 1 hueco y 14 imagenes revelaba 4 en vez de 6 y dejaba
  un `[+6]` suelto). Como `caben` en grilla es `porFila * porAlto`, el residuo se conserva y el
  chip cierra fila **siempre**: `(huecos + visibles + 1) % porFila === 0` en todas las tandas.
  El `tope` se materializa en filas completas (`MU.puro.topeEnFilas`, contrato C1ter) y **el
  chip `+1` no existe**: si queda un solo item oculto y el `tope` lo deja entrar, se muestra;
  el `+1` solo queda cuando el `tope` de celdas lo impide, y ahi abre el visor.

- **D25 — La caja entera cabe en 92vh: el alto del lienzo se MIDE** (2026-11-07): el alto
  del marco era `min(vh * 0.62, 720)` a ojo, asi que con 768 px de ventana la caja se
  pasaba de su `max-height: 92vh`, el marco empujaba la banda y la meta fuera del area
  visible y el `overflow: hidden` las cortaba. Ahora `aplicarLienzo()` mide el **cromo
  vertical** en el DOM (cabecera, segmentos, padding del cuerpo, **pie natural** con
  `scroll.scrollHeight`, acciones, gaps y bordes) y calcula `min(92vh - cromo, 720)` con
  suelo de 120 px. El cuerpo **no encoge** (`flex: 0 0 auto` + `overflow: hidden`) y
  `.mu-gal-scroll` absorbe: el marco nunca se escapa y el pie nunca desaparece (con el
  cromo sin el pie, a 1000x768 el scroll media 0 px: probado y descartado). Sin medidas,
  el 4:3 tambien se acota por el alto (`min(maxW, maxH * aspecto)`). Se re-mide al montar
  y al redimensionar, **nunca** al navegar (D19).

- **D26 — El piso de la coleccion es UNA celda, y es del ambito 1** (2026-11-07): la banda
  del visor se aplastaba (mini de 74 -> 0 px) y el defecto **no** era del visor: la RAIZ de
  la coleccion es un flex item del consumidor y, con `overflow: hidden` encima (la banda),
  su minimo automatico pasa a 0. Arreglarlo solo en el visor habria dejado el mismo agujero
  en cualquier consumidor presente o futuro (ficha, cotizador, biblioteca). Ahora
  `coleccion()` publica `--mu-gal-alto-celda` (alto **natural** de la celda MAS ALTA:
  `max(offsetHeight, scrollHeight)`) y `--mu-gal-alto-min` (ese alto + pie + paddings), y el
  CSS aplica `min-height: var(--mu-gal-alto-min)` a `[data-mu-gal-layout]`. **Manda el
  contenido, no el preset**: hay celdas con titulo, precio o acciones y `tamano` acepta
  `{ancho, alto}` (contrato `coleccion.md`, C11). Validado: mini de 74 px en 4 escenarios x
  4 viewports y en movil, y un contenedor corto del ambito 1 ya no la recorta.

- **FR-024** `MU.coleccion(el, opciones)` con `layout` (fila/grilla), `navegable`
  (`auto`/true/false; alias `deslizable`), `tamano`, `gap`, `filas`, `columnas`, `alto`.
- **FR-025** `MU.puro.celdas()` decide `visiblesItems`, `huecos` y `chip` (D21).
- **FR-026** `MU.puro.pie()` produce el texto de D22.
- **FR-027** `MU.puro.caben(ancho, celda, gap)` da el `tope` efectivo de una fila que no
  desliza (`100/30/0 -> 3`).
- **FR-028** El chip es una **celda** de la linea (`mu-gal-celda--chip`), nunca algo que
  flota fuera y se recorta.
- **FR-029** `MU.visor` monta la coleccion como listado inferior y sincroniza `activo` (F2).
- **FR-030** `MU.puro.carga()` suma **una pantalla** (`caben`) por tanda, nunca `capacidad`, y
  `MU.puro.topeEnFilas()` materializa el `tope` en filas completas: con chip,
  `(huecos + visibles + 1) % columnas === 0` y el chip es la ultima celda de su fila. Un item
  oculto que entra en el `tope` se muestra (nunca un chip `+1`).

## 3. Requisitos funcionales

- **FR-001** `MUGaleria.visor({items, indice, titulo, tono, segmentos, acciones, meta})` abre
  un modal accesible (`role="dialog"`, `aria-modal="true"`), con contador `n / total`.
- **FR-002** Navegacion: flechas anterior/siguiente, teclado (`Escape`, `ArrowLeft`,
  `ArrowRight`) y swipe tactil (`> 40 px`).
- **FR-003** Precarga de vecinos (`preCargar`) y `opacity-0 -> onLoad` para que la imagen no
  aparezca a medio cargar.
- **FR-004** Scroll lock del `body` mientras el modal esta abierto, restaurado al cerrar.
- **FR-005** `segmentos` (dos fuentes de imagenes) y `acciones` declaradas por contexto
  (`Elegir`, `Borrar`, `Personalizar`, `Reemplazar`).
- **FR-006** `meta` opcional bajo la imagen: titulo, descripcion, badge, precio y su aviso
  de "actualizado".
- **FR-007** `MUGaleria.tira(el, {items, modo, filas, tope, min, mas, activo, forma,
  borrar, confirmar, contador, onAbrir, onBorrar, onRanura, onMas})`: dibuja miniaturas.
  `modo` es `auto`|`linea`|`grilla` (ver D13); `+N` son las imagenes que **faltan**.
- **FR-008** `MUGaleria.tarjeta(item, {badge, precio, favorito, onUsar, onBorrar})`: tarjeta
  de catalogo con estrella y acciones.
- **FR-009** `MUGaleria.desdeWoo(raiz)` devuelve `items` desde `.woocommerce-product-gallery`
  (`data-large_image`, `data-thumb`, `alt`), sin peticiones.
- **FR-010** `MUGaleria.desdeEnlaces(el)` devuelve `items` desde cualquier `a[href]` de imagen
  o `img` (cuenta, banners, opciones, contenido).
- **FR-011** `[data-mu-galeria]` se auto-inicializa: abre el visor con `data-mu-galeria-items`
  o, si no existe, con `desdeEnlaces()`. Si `data-mu-galeria-vinculo="1"`, el trigger propio
  abre el modal.
- **FR-012** Galeria de WooCommerce: el trigger abre `MUGaleria` en la foto activa con **todas**
  las fotos del producto.
- **FR-013** `MUGaleria.cerrar()`, `MUGaleria.estaAbierta()` y
  `MUGaleria.puro = {acotar, preCargar, orden, formatearPrecio}` (testeable en Node).
- **FR-014** `MUGaleria.arrastrar(el)`: el gesto drag-to-scroll de las tiras, en UN solo
  lugar. Lo usa `js/product.js` sobre el slider de WooCommerce (que antes tenia su copia).
- **FR-015** Degradacion: `mu-product-js` declara `mu-galeria` como dependencia, asi el
  orden es determinista. Sin el modulo, la ficha pierde solo el arrastre: el slider y el
  click de WooCommerce siguen funcionando.
- **FR-016** `modo: 'auto'` resuelve por `clientWidth` del contenedor (D13) y se repinta
  con `ResizeObserver` sin perder `scrollLeft` ni el foco.
- **FR-017** `filas` limita el alto de la grilla (`--filas`; 2 filas = `max-height` de
  146 px) y lo que no entra se resume en el `+N`. Con cota la fila mide el alto de celda
  real (`--fila-alto`) y el marco se calcula con ese numero: `scrollHeight` nunca excede
  `max-height` (D24bis).
- **FR-018** `min` dibuja `min - items.length` ranuras vacias accesibles
  (`aria-label="Agregar imagen N de M"`) que llaman `onRanura(indice)`; `contador` muestra
  "1 de 3".
- **FR-019** `borrar: true` + `onBorrar(indice, item)` dibuja el tachito con `confirmar`
  configurable (D16) y `stopPropagation` para no abrir el visor.
- **FR-020** `activo` por defecto es `-1` (ninguna marcada): la marca solo aparece cuando
  el consumidor la pide.
- **FR-021** El visor usa `.mu-nav-btn prev/next` del tema con su SVG chevron (sin flechas
  propias) y las oculta con `hidden` cuando hay una sola imagen.
- **FR-022** `initCarousels()` enciende cada flecha **solo si el track desborda y queda
  recorrido en ese extremo**; si no desborda, ninguna. Se recalcula en `scroll` y `resize`.
- **FR-023** El unico mecanismo de visibilidad es `.is-visible` (+ `[disabled]` en el
  extremo): ningun `display` por media query.

## 4. Criterios de exito

- **SC-001** La demo `tests/galeria-demo.html` muestra los dos tonos, la tira, la tarjeta y
  las acciones sin errores de consola.
- **SC-002** `node tests/galeria.test.js` en verde (API completa, puros, prefijo de clases,
  sin `innerHTML` con datos del usuario, flechas del sistema).
- **SC-003** En el laboratorio, la ficha de producto: `tests/galeria.browser.js` (**9/9**). El
  **ARRASTRE** queda **conectado** en el slider real (`data-mu-gal-arrastre="1"`, aunque Woo
  repinte la galeria tras `DOMContentLoaded`), la **lupa abre el VISOR COMPARTIDO** (`MU.visor`)
  y **no** Photoswipe, recorrer el visor no pide red y no hay errores. Con el grid actual (11
  miniaturas en 3 filas) **no hay desborde horizontal**: la puerta lo **declara**.
- **SC-004** El resto del sitio no cambia el peso de sus paginas (registro + encolado
  condicional).
- **SC-005** El plugin y la calculadora **no se tocan** en esta spec y sus puertas siguen
  igual.

## 5. Fuera de alcance

- Migrar el plugin (`PMUVisor`) y la calculadora a `MUGaleria` (fases F2 y F3).
- Quitar `wc-product-gallery-lightbox` / desactivar Photoswipe (se decide en F2 con evidencia).
- `initCarousels()` de `global-ui.js`.
- Cualquier endpoint, dato nuevo o permisos.

## 6. Puertas

```powershell
node --check js/galeria.js
node tests/galeria.test.js          # GALERIA OK (N checks)
php -l inc/galeria.php
php -l functions.php
```

Y la revision visual de `tests/galeria-demo.html` (tono claro y oscuro).