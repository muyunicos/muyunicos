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
  items: [ { url, thumb, etiqueta, forma } ],
  tope: 10,                // cuantas se dibujan (default: todas)
  scroll: true,            // true => carrusel con snap; false => grilla/wrap
  forma: 'cuadrada' | 'circulo',   // default 'cuadrada'
  mas: true,               // agrega el boton "+N" para abrir el visor en la N+1
  activo: 0,               // indice marcado (clase mu-gal-mini--activa)
  onAbrir: function (indice) {}
});
```

- Devuelve la cantidad dibujada.
- Limpia `el` y dibuja: `mu-gal-tira` (+ `mu-gal-tira--scroll` si `scroll`) con
  `button.mu-gal-mini` (+ `mu-gal-mini--circulo`).
- Si `mas` y `items.length > tope`: agrega `button.mu-gal-mas` con `"+N"`.
- **No** pide nada al servidor. Repintar es seguro e idempotente.

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