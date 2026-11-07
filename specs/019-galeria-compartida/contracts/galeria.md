# Contract — Galeria compartida (`window.MUGaleria`)

Modulo: `js/galeria.js`. Estilos: `css/components/galeria.css`. Registro y encolado:
`inc/galeria.php`. Spec: [../spec.md](../spec.md).

Sin dependencias. ES5, IIFE, mismo estilo que `js/global-ui.js`. No usa jQuery.
No pide datos, no guarda nada, no conoce endpoints (D10).

## C0 — Como se carga

`inc/galeria.php` **registra** (no encola) los handles y los encola solo en `is_product()`:

```php
wp_register_style( 'mu-galeria', "$uri/css/components/galeria.css", [ 'mu-base' ], $ver );
wp_register_script( 'mu-galeria', "$uri/js/galeria.js", [], $ver, true );
```

Cualquier consumidor (plugin, shortcode, plantilla, cuenta, banners) los encole por handle:

```php
if ( function_exists( 'mu_galeria_encolar' ) ) { mu_galeria_encolar(); }
```

`mu_galeria_encolar()` es idempotente: llamarla N veces encola una sola vez.
**El tema nunca encola la galeria por su cuenta fuera de `is_product()`.**

## C1 — `MUGaleria.visor(opciones)`

```js
MUGaleria.visor({
  items: [ { url, thumb, etiqueta, meta, transparente } ],  // obligatorio, >= 1
  indice: 0,                                    // posicion inicial (acotada)
  titulo: 'Ideas',                              // encabezado
  tono: 'claro' | 'oscuro',                     // default 'claro'
  contador: 'auto' | true | false,              // default 'auto': oculto con 1 item
  /* 'auto' (default) = lienzo del item PROMEDIO | 'completo' = ocupa el espacio */
  ancho: 'auto' | 'completo',                   // ver C1.1 (ya no ajusta al contenido)
  segmentos: [ { etiqueta, activo, onElegir() } ],   // pestañas: NO filtran (ver C1.2)
  acciones:  [ { etiqueta, icono, aria, clase, onAccion(indice) } ],
  meta:      { titulo, descripcion, nota,
                badge: '31% OFF' | { texto, tono },              // + badgeTipo (forma vieja)
                precios: [ { etiqueta, valor, anterior, aviso } ],  // o precio: {...} (vieja)
              },
  listado:   'abajo' | false,                   // banda de miniaturas (default false)
  alCambiar: function (indice) {},
  alCerrar:  function () {}
});
```

### C1.1 — DOS MODOS de tamano (no es decoracion: es la estabilidad del visor)

El visor tiene **dos modos y nada mas**. La caja **nunca** cambia de tamano al navegar:
antes era `fit-content` y cada foto re-dimensionaba la caja (el `mu-gal-cuerpo` media su
`min-content`, el marco desbordaba y las flechas se salian, sobre todo en la franja
**811–820 px**). Ese modelo esta retirado.

| Modo | Cuando | Que hace |
|---|---|---|
| `adaptive` | ventana **<= 600 px** | pantalla completa (`@media`): `100vw` x `100dvh`, sin lienzo. |
| `responsive` | ventana **> 600 px** | la caja se dimensiona con el **item PROMEDIO** (`MU.puro.lienzo`), una vez por apertura. |

**`responsive` en detalle**:

- `MU.puro.lienzo(items, {ancho, alto, minimoAncho, minimoAlto, llenar})` mide el item
  promedio: `aspecto` = media de `w/h`, `area` = media de `w*h` y el rectangulo
  representativo es `h = sqrt(area / aspecto)`, `w = aspecto * h` (media **geometrica**:
  respeta tamano y forma). Se acota a lo disponible con `contain` (nunca deforma) y
  respeta minimos de escritorio. Sin medidas cae al clasico **4:3**, acotado por el ancho
  **y** por el alto disponibles (`min(maxW, maxH * aspecto)`).
- El JS publica `--mu-gal-lienzo-w/h` en la caja; el **marco** toma ese tamano **fijo** y
  la imagen se encaja dentro con `object-fit: contain`. Navegar solo cambia el `src`.
- `ancho` deja de ser un tamano: `'auto'` (default) usa el item promedio;
  `'completo'` **llena** el espacio disponible (`llenar: true`, tope `min(96vw, 1400px)`)
  conservando el aspecto promedio. Un numero en px ya **no** tiene efecto.
- **Alto**: la caja **entera** cabe en `92vh` (su `max-height`). El alto del lienzo es
  `min(92vh - cromo, 720)`, con el cromo **medido en el DOM** al aplicar: cabecera,
  segmentos, padding vertical del cuerpo, **el pie con su alto natural** (banda + meta,
  `scroll.scrollHeight`), acciones, gaps y bordes; suelo de **120 px**. El pie entra en la
  cuenta a proposito: sin el, en ventanas de 768 px el scroll quedaba en **0 px** y la
  banda y la meta desaparecian (medido). Si aun asi no entra, `.mu-gal-caja--fija
  .mu-gal-cuerpo` (`flex: 0 0 auto` + `overflow: hidden`) **no encoge** y
  `.mu-gal-caja--fija .mu-gal-scroll` (`flex: 1 1 auto`, `min-height: 0`) absorbe y
  scrollea: el marco **nunca** se escapa de la caja.
- Se recalcula al montar, al redimensionar la ventana y cuando `actualizar()` cambia
  `items`/`ancho`. **Nunca** al navegar.

**Medidas de donde salen**: `item.w`/`item.h` si el consumidor los declara; si no, se lee
`naturalWidth/naturalHeight` de una `<img>` del DOM que apunte a la **misma url** (la
galeria de Woo ya las tiene pintadas: medir no cuesta una descarga). Sin ninguna medida,
4:3.

**Acabado (mismo bloque de trabajo)**: el activo de la banda es un anillo **interno**
(`box-shadow` inset) + `aria-current`, sin `outline` externo (el `overflow: hidden` de la
banda lo recortaba); las flechas de `mu-gal-caja-linea` van **dentro** del bloque
(`left/right: 0`) y la fila les reserva el ancho; el visor **no** lleva lineas divisorias
internas (cabecera, meta, acciones y banda se separan con espacio).

### C1.2 — `segmentos` NO filtra

Las pestañas de la cabecera solo **avisan** al consumidor (`onElegir`): el visor nunca recorta
`items`. Filtrar es del **ambito 1** (`MU.coleccion`, `coleccion.md` C10) y el visor recibe la
lista **ya filtrada**. Antes parecian filtros (y el demo las llamaba Fotos/Diseños/Formas) sin
serlo; queda escrito para que no se confunda otra vez.

`listado:'abajo'` monta una `MU.coleccion` (preset `mini`, tamano `chico`) como banda inferior,
sincronizada dos vias con la imagen grande (`MU.ir(i)`): ver `coleccion.md` C8.

- Devuelve `true` si abrio; `false` si no hay `items` o no hay `document`.
- **`MUGaleria.visor.actualizar(parcial)`** (V2, T044): con el visor **abierto** cambia
  `items`, `indice`, `titulo`, `meta`, `contador`, `ancho`, `segmentos` y `acciones` **sin
  cerrar** ni recrear el DOM (los listeners de la caja y de las flechas se conservan). Devuelve
  `true` si habia un visor. Con `items` vacio cierra. **`listado` no se re-monta**: si cambia la
  banda inferior hay que cerrar y volver a abrir. El modo declarativo **no** agrega un
  `[data-mu-galeria-visor]`: `[data-mu-galeria]` + `[data-mu-galeria-abrir]` ya abren el visor
  (D3) y un segundo atributo seria un duplicado.
- **Instancia**: `visor()` retira el anterior y monta uno nuevo; `cerrar()` lo retira a los
  250 ms de la transicion. Nunca hay dos `.mu-gal-visor` en el documento.
- Reutiliza `.mu-modal-overlay` + `.mu-modal-container` + `.mu-modal-close` y
  `.is-visible` (D2). **No** crea otro modal.
- Clases propias (todas con prefijo `mu-gal-`): `mu-gal-visor`, `mu-gal-cabecera`,
  `mu-gal-titulo`, `mu-gal-contador`, `mu-gal-segs`, `mu-gal-seg`, `mu-gal-cuerpo`,
  `mu-gal-img`, `mu-gal-prev`, `mu-gal-next`, `mu-gal-meta`, `mu-gal-acciones`,
  `mu-gal-visor-lista` (banda del listado, solo con `listado:'abajo'`).
  Con estado de error (V2): `mu-gal-error`, `mu-gal-error-texto`, `mu-gal-error-reintentar`,
  `mu-gal-marco--error` (el marco reserva alto) y `mu-gal-img--roto`.
  Las **flechas no son clases propias**: son `.mu-nav-btn prev/next` del tema (C8).
- Accesibilidad: `role="dialog"`, `aria-modal="true"`, `aria-label` = `titulo`,
  `aria-hidden` en las flechas cuando `n <= 1`, foco al boton de cerrar,
  `Escape` cierra, `ArrowLeft`/`ArrowRight` navegan, `Tab` no sale del modal.
- `body` con `is-scroll-locked` mientras esta abierto; se restaura al cerrar
  (guarda el `overflow` previo).
- Imagen: `opacity-0` hasta `load` (clase `mu-gal-img--cargada`), `object-fit: contain`,
  `decoding="async"`.
- **Imagen que no carga** (V2, T041): el marco pinta `mu-gal-error` con el texto "No se pudo
  cargar la imagen" y un boton **Reintentar** que vuelve a pedir la misma url con `?r=<ts>`
  (el navegador cachea el fallo). La imagen rota queda con `opacity: 0` (`mu-gal-img--roto`),
  asi no se ve el icono del navegador. El estado vive en el DOM del **marco**: al cambiar de
  imagen el `src` nuevo lo limpia solo, sin estado extra. El marco reserva `min-height: 220px`
  (`mu-gal-marco--error`) para que el mensaje no colapse cuando no hay alto natural.
- **Meta normalizada** (V2, T043): `MU.puro.normalizarMeta(meta)` deja **una** forma y acepta la
  vieja (retrocompatible, no rompe llamadores): `badge` string + `badgeTipo` -> `{texto, tono}`, y
  `precio:{valor, anterior, aviso}` -> `precios:[{etiqueta, valor, anterior, aviso}]`. Tonos:
  `tienda` (default), `nuevo`, `oferta`, `peligro`, `guardada` y `comprada` (los dos ultimos los usa
  la biblioteca del plugin); un tono desconocido cae a `tienda`. `precios[]` pinta **una fila por
  precio** con su `etiqueta` (Regular/Mayor) y `nota` va al pie de la meta. **El mismo normalizador
  pinta el badge de la tarjeta**, asi no quedan dos formatos de badge en el sistema.
- **Acciones con icono** (V2, T042): `acciones[].icono` acepta `usar`, `descargar`, `corazon` o
  `basura`. **No es un set del tema**: son cuatro SVG de la familia del chevron, creados con
  `createElementNS` (el componente no usa `innerHTML`) y decorativos (`aria-hidden`), asi que el
  nombre accesible sigue siendo el **texto**. Un nombre desconocido no rompe la accion: el boton
  queda con su etiqueta. Una accion **sin** `etiqueta` toma el nombre accesible de `aria` (o del
  `icono`). La clase destructiva buena es `mu-gal-peligro`; `pmu-visor-peligro` sobrevive como
  **puente** hasta F2/T900 (hoy la usa el plugin).
- **Marco**: lienzo neutro (`--mu-gal-lienzo`, transparente = el fondo de la caja) y **damero
  solo si el item declara `transparente: true`**. El damero permanente dejaba bandas grises
  alrededor de cualquier foto opaca (defecto medido en `visor-evaluacion.md`).
- **Flechas**: su posicion es una **decision del componente**, no una opcion. Al costado
  consumen `44 x 2 + gaps + padding`; si el marco quedaria por debajo de 280px utiles, el JS
  marca `mu-gal-cuerpo--justo` y las **superpone** sobre la imagen a media altura (en movil
  siempre). Se recalcula al redimensionar.
- **Scroll**: cabecera y acciones quedan fijas; el cuerpo, la banda y la meta viven en
  `.mu-gal-scroll` (`overflow-y: auto`). Una meta larga ya **no se recorta**.
- **Contador**: `contador: 'auto'` lo oculta con un solo item (el "1 / 1" es ruido).
- Precarga de vecinos: `VECINOS = 1` a cada lado, con `new Image()`.
- Reabrir con otro `items` **reemplaza** el contenido (un unico modal en el documento).

## C2 — `MUGaleria.tira(el, opciones)`

```js
MUGaleria.tira(el, {
  items: [ { url, thumb, etiqueta } ],
  modo: 'auto' | 'linea' | 'grilla',  // default 'auto' (mide el CONTENEDOR, D13)
  filas: 2,                 // solo 'grilla': tope de filas (0/ausente = todas)
  tope: 10,                 // cuantas fotos reales antes del "+N" (0/ausente = todas)
  min: 3,                   // ranuras a llenar: dibuja (min - items.length) vacias
  mas: true,                // "+N" con las que FALTAN
  activo: -1,               // indice marcado; -1/ausente = ninguna (FR-020)
  forma: 'cuadrada' | 'circulo',
  borrar: true,             // tachito por miniatura (requiere onBorrar)
  confirmar: true,          // true = confirm() nativo | false = directo | fn
  contador: true,           // "1 de 3" cuando hay `min`
  onAbrir:  function (indice) {},
  onBorrar: function (indice, item) {},   // quitar esa imagen
  onRanura: function (indice) {},         // tocar una ranura vacia
  onMas:    function (desde) {}           // el "+N" (sin onMas -> onAbrir)
});
```

- Devuelve **cuantas dibujo** (fotos reales + ranuras).
- Limpia `el` y dibuja `mu-gal-tira--linea` o `--grilla` segun `modo`.
- **`+N` = las que faltan** (`items.length - tope`), no el total. Con `filas` se agrega
  **automaticamente** aunque no se pida `mas` (si no, el usuario no sabe que hay mas).
- Con `min` y `contador` antepone "N de min".
- **No** pide nada al servidor. Repintar es seguro e idempotente.

### C2.1 — Modo `auto` (D13)

1. Si `tope + ranuras` caben en una linea (`n * 70 + (n - 1) * 6 <= el.clientWidth`) -> `linea`.
2. Si no caben y `el.clientWidth >= 480` -> `grilla`.
3. Si no caben y es mas angosto -> `linea` con scroll (nunca inaccesible).

Se recalcula con un `ResizeObserver` compartido **solo si `modo` es `auto`**, y repinta
conservando `scrollLeft` y el foco de la miniatura. `el` expone `data-mu-gal-modo`.

### C2.2 — DOM de la miniatura (D14)

```html
<div class="mu-gal-mini mu-gal-mini--activa" data-mu-gal-mini="0">
  <button class="mu-gal-mini-abrir" aria-label="Imagen 1"><img src="..." alt=""></button>
  <button class="mu-gal-mini-borrar" aria-label="Quitar imagen 1">&times;</button>
</div>

<!-- ranura a llenar (min) -->
<button class="mu-gal-mini mu-gal-mini--vacia" data-mu-gal-ranura="1"
        aria-label="Agregar imagen 2 de 3"><span class="mu-gal-mini-mas">+</span></button>
```

**Nunca** un `<button>` dentro de otro (C7). El chip `+N` es `button.mu-gal-mas`.

## C2b — `MUGaleria.arrastrar(el)`

```js
MUGaleria.arrastrar(document.querySelector('.flex-control-thumbs'));  // -> true si engancho
```

- Drag-to-scroll generico: **la unica implementacion del gesto en el tema** (antes estaba
  duplicada en `js/product.js` y `js/global-ui.js`).
- Solo actua si hay overflow real (`scrollWidth > clientWidth`); desactiva
  `scroll-snap-type` mientras arrastra y lo restaura al soltar.
- Si hubo arrastre, bloquea 100 ms el click de los hijos (evita cambiar de foto sin querer).
- Idempotente: marca `data-mu-gal-arrastre="1"` en el contenedor.
- En tactil no hace nada: el navegador ya desplaza de forma nativa.

## C2c — `MUGaleria.navegarWoo(raiz)`

- Engancha **una** vez el trigger `.woocommerce-product-gallery__trigger` de la ficha.
- Abre `visor({items: desdeWoo(galeria), indice: indiceActivo(galeria)})`.
- Si `desdeWoo()` no devuelve items, **no intercepta**: WooCommerce sigue con su lightbox.

## C3 — `MUGaleria.tarjeta(item, opciones)`

```js
MUGaleria.tarjeta(
  { url, thumb, titulo, descripcion, badge, precio, favorito, orden, creado },
  { onUsar(), onBorrar(), onEstrella(valor) }
);
```

- Devuelve un `article.mu-gal-tarjeta` (no lo inserta: lo inserta el llamador).
- Clases: `mu-gal-tarjeta`, `mu-gal-tarjeta-img`, `mu-gal-tarjeta-cuerpo`,
  `mu-gal-badge`, `mu-gal-badge--tienda|--guardada|--comprada`,
  `mu-gal-estrella`, `mu-gal-precio`, `mu-gal-precio-aviso`.
- `precio`: `{ valor, anterior, aviso }`; `aviso: true` = etiqueta "actualizado".
- Sin `onBorrar` no se dibuja el boton de borrar (solo las ideas propias tienen borrado).

## C4 — Adaptadores

| Funcion | Fuente | Devuelve |
|---|---|---|
| `MUGaleria.desdeWoo(raiz)` | `.woocommerce-product-gallery` del DOM | `items` con `url` = `data-large_image`, `thumb` = `data-thumb`, `etiqueta` = `alt` |
| `MUGaleria.desdeEnlaces(el)` | `a[href]` de imagen o `img` dentro de `el` | `items` con `url` resuelta |
| `MUGaleria.autoInit(raiz)` | `[data-mu-galeria]` | inicializa cada contenedor |

`data-mu-galeria` acepta:

```html
<!-- items explicitos -->
<div data-mu-galeria data-mu-galeria-items='[{"url":"foto1.jpg","etiqueta":"Foto 1"}]'>
  <button data-mu-galeria-abrir>Ver galeria</button>
</div>

<!-- desde el DOM -->
<div data-mu-galeria data-mu-galeria-titulo="Mis fotos">
  <a href="foto1.jpg"><img src="foto1.jpg" alt="Foto 1"></a>
  <a href="foto2.jpg"><img src="foto2.jpg" alt="Foto 2"></a>
</div>
```

- El JSON invalido **no rompe** nada: cae a `desdeEnlaces()`.
- Cualquier elemento con `[data-mu-galeria-abrir]` dentro del contenedor abre el visor.
  Si no hay ninguno, el **primer** `a`/`img` del contenedor abre (delegado, sin listeners
  por elemento).
- `data-mu-galeria-indice` (opcional) fija la posicion inicial del visor.

## C5 — Navegacion de la galeria de producto (WooCommerce)

- `js/product.js` engancha **una** vez, delegado en `document`, sobre
  `.woocommerce-product-gallery__trigger`: `preventDefault()` +
  `stopImmediatePropagation()` en fase de **captura** y abre `MUGaleria.visor` con
  `desdeWoo()` en la foto activa (`flex-active` / primer slide).
- **No** se quita `wc-product-gallery-lightbox` (D8): si Photoswipe se abre igual, se
  resuelve en F2 con evidencia del laboratorio.
- `mu-product-js` declara `mu-galeria` como dependencia: el modulo existe antes de correr
  `js/product.js`. Sin el modulo, la ficha pierde solo el arrastre (FR-015).

## C6 — Puros (Node, sin DOM)

API completa: `visor`, `cerrar`, `estaAbierto`, `tira`, `tarjeta`, `desdeWoo`,
`desdeEnlaces`, `autoInit`, `arrastrar`, `navegarWoo`, `puro`, `version`.

```js
MUGaleria.puro.acotar(indice, total)      // posicion valida
MUGaleria.puro.preCargar(items, i, k)     // indices vecinos
MUGaleria.puro.orden(a, b)                // favorito -> orden -> creado -> clave
MUGaleria.puro.formatearPrecio(n)         // 'es-AR', 0 decimales
```

Exportan tambien en `module.exports` cuando corren en Node (patron de `visor-pmu.js`
del plugin), para que `tests/galeria.test.js` los ejercite sin DOM.

## C8 — Flechas (D18)

- El visor usa **`.mu-nav-btn prev/next`** del tema con su SVG chevron
  (`createElementNS`, nunca `innerHTML`), mas las clases-hook `mu-gal-prev`/`mu-gal-next`
  para el comportamiento (`hidden` cuando hay una sola imagen).
- Dentro del visor el boton pierde el posicionamiento absoluto del carrusel
  (`.mu-gal-cuerpo .mu-nav-btn.prev/.next { position: static }`) y va en el flex del cuerpo.
- **Visibilidad por necesidad**: `.mu-nav-btn` solo se ve con `.is-visible`; en un extremo
  el boton lleva `disabled` y el CSS lo oculta (`[disabled] { display: none }`).
- `initCarousels()` (`js/global-ui.js`) enciende cada flecha si
  `scrollWidth - clientWidth > 1` **y** queda recorrido en ese extremo
  (`scrollLeft > 1` / `< sobra - 1`); lo recalcula en `scroll` y `resize`.
- **Prohibido**: mostrar flechas por media query (era el mecanismo anterior) o re-declarar
  `.mu-nav-btn` en otro CSS.

## C7 — Lo que este contrato NO permite

- Pedir datos, conocer endpoints o nonces (D10).
- Crear un modal propio (D2): el modal es `.mu-modal-*`.
- Usar `innerHTML` con datos del usuario: todo texto va por `textContent`.
- Encolarse a si mismo: el encolado lo decide `inc/galeria.php` o el consumidor.
- Cambiar `--primario`/`--secundario`: se consumen, no se redefinen.
- **Anidar interactivos**: la miniatura es un `<div>` con dos botones, nunca un
  `<button>` que contenga otro (D14).
- **Decidir el modo por el viewport**: se mide el CONTENEDOR (D13). El CSS de la tira no
  tiene `@media` de ancho: la clase `--linea`/`--grilla` la pone el JS.
- **Re-declarar `.mu-nav-btn`**: es el boton del tema (C8). Cada consumidor solo agrega
  posicionamiento con su propio prefijo (`.mu-gal-cuerpo .mu-nav-btn...`).