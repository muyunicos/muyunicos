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
  items: [ { url, thumb, etiqueta, meta } ],   // obligatorio, >= 1
  indice: 0,                                    // posicion inicial (acotada)
  titulo: 'Ideas',                              // encabezado
  tono: 'claro' | 'oscuro',                     // default 'claro'
  segmentos: [ { etiqueta, activo, onElegir() } ],
  acciones:  [ { etiqueta, clase, onAccion(indice) } ],
  meta:      { titulo, descripcion, badge, precio: { valor, anterior, aviso } },
  alCambiar: function (indice) {},
  alCerrar:  function () {}
});
```

- Devuelve `true` si abrio; `false` si no hay `items` o no hay `document`.
- Reutiliza `.mu-modal-overlay` + `.mu-modal-container` + `.mu-modal-close` y
  `.is-visible` (D2). **No** crea otro modal.
- Clases propias (todas con prefijo `mu-gal-`): `mu-gal-visor`, `mu-gal-cabecera`,
  `mu-gal-titulo`, `mu-gal-contador`, `mu-gal-segs`, `mu-gal-seg`, `mu-gal-cuerpo`,
  `mu-gal-img`, `mu-gal-nav`, `mu-gal-prev`, `mu-gal-next`, `mu-gal-meta`, `mu-gal-acciones`.
- Accesibilidad: `role="dialog"`, `aria-modal="true"`, `aria-label` = `titulo`,
  `aria-hidden` en las flechas cuando `n <= 1`, foco al boton de cerrar,
  `Escape` cierra, `ArrowLeft`/`ArrowRight` navegan, `Tab` no sale del modal.
- `body` con `is-scroll-locked` mientras esta abierto; se restaura al cerrar
  (guarda el `overflow` previo).
- Imagen: `opacity-0` hasta `load` (clase `mu-gal-img--cargada`), `object-fit: contain`,
  fondo damero, `decoding="async"`.
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