# Contract — Temas visuales (`data-mu-tema`)

Spec **020**. Un **tema** es un bloque de CSS que cambia la PRESENTACION de la galeria
(ambito 1) y del visor (ambito 2). **No** agrega opciones, **no** cambia datos y **no**
modifica el DOM: si algo no se puede con CSS, se mejora el motor (spec 020 D4/D5).

## T1 — Declaracion

```html
<div data-mu-tema="cotizador">   <!-- contenedor del consumidor, o cualquier ancestro -->
  <div id="mu-sticker-calculator-root">…</div>
</div>
```

Sin atributo (o con un valor desconocido) el tema es **`base`** = el aspecto de hoy.

## T2 — Resolucion y publicacion (motor)

1. `MU.coleccion(el, …)` resuelve `el.closest('[data-mu-tema]')` y **publica** el valor en la
   raiz de la coleccion: `nodo.setAttribute('data-mu-tema', valor)` (no pisa uno propio).
2. `MU.visor({…})` resuelve, en este orden: `opts.tema` → `closest` del `document.activeElement`
   → `closest` del contenedor de la coleccion abierta → `base`; y **publica** el valor en el
   overlay (`<div class="mu-modal-overlay mu-gal-visor" data-mu-tema="cotizador">`).
3. `opts.tema` existe solo como **atajo** de presentacion para quien abre el visor sin foco
   (p. ej. programaticamente). No es configuracion de datos.

## T3 — Reglas duras

1. **Solo CSS.** Un tema no puede: agregar/quitar nodos, cambiar clases existentes, cambiar
   textos ni leer datos.
2. **No duplica el motor.** Lo que necesite estructura nueva se resuelve en el motor una vez
   (D5) y queda disponible para todos los temas.
3. **Los selectores parten del atributo**: `[data-mu-tema="x"] .mu-gal-…` y
   `.mu-gal-visor[data-mu-tema="x"] .mu-gal-…`. Prohibido `!important` salvo para pisar una
   clase AJENA documentada (T5).
4. **`base` no tiene bloque**: si el CSS del tema se borra, la galeria debe verse como hoy.

## T4 — Superficie que un tema puede tocar

| Ambito | Selectores estables |
|---|---|
| tira | `.mu-gal-celda`, `.mu-gal-item`, `.mu-gal-item-abrir`, `.mu-gal-item-abrir img`, `.mu-gal-item-etiqueta`, `.mu-gal-item-titulo/-desc/-precio/-ya/-antes`, `.mu-gal-celda--chip`, `.mu-gal-caja-linea` |
| visor | `.mu-gal-caja`, `.mu-gal-cabecera`, `.mu-gal-segs`, `.mu-gal-cuerpo`, `.mu-gal-marco`, `.mu-gal-img`, `.mu-gal-scroll`, `.mu-gal-visor-lista`, `.mu-gal-meta-caja`, `.mu-gal-badge`, `.mu-gal-precio*`, `.mu-gal-nota`, `.mu-gal-pie`, `.mu-gal-acciones`, `.mu-gal-accion` |

## T5 — Auditoria del CSS prestado (D7)

| Prestado | Problema | Regla del componente |
|---|---|---|
| `.mu-modal-close` (`global-ui.css`) | `position:absolute`, `color:#6b7280`, hover gris: sobre el visor se pisaba | el visor **deja de usarla**; `mu-gal-cerrar` trae color, tamano, hover y foco propios |
| `.button` / `.button-primary` (WP) | tiñen las acciones (caso real: biblioteca del plugin) | `.mu-gal-acciones .mu-gal-accion` (especificidad 0,2,0) gana siempre |
| `.mu-nav-btn` | el tema las posiciona en absoluto | ya se pisa (spec 019 D18); se mantiene |

## T6 — `.mu-gal-pie` (D5)

El motor envuelve la meta y las acciones:

```
.mu-gal-caja
  .mu-gal-scroll          (cuerpo + banda + meta scrolleable)
  .mu-gal-pie             (NUEVO)
    .mu-gal-meta-caja
    .mu-gal-acciones
```

- `base`: `.mu-gal-pie { display: flex; flex-direction: column; }` → aspecto de hoy (meta
  arriba, acciones al pie).
- Otro tema: `flex-direction: row` + `align-items: flex-end` para poner los botones en la
  misma fila que los precios. El tope de 92vh (spec 019 D25) sigue mandando: el pie entra en
  el cromo medido.

## T7 — Tema `cotizador`

| Punto | CSS |
|---|---|
| precios en **2 columnas** | `.mu-gal-meta-caja` en `grid` (3 col.): los `.mu-gal-precio` en col 1 y 2; etiqueta 10px uppercase, valor 18-20px, `c/u` 11px, anterior tachado 11px |
| `% OFF` **junto al precio** | `.mu-gal-badge` (orden en el DOM: antes del titulo) se ubica en la **col 3** alineado con la fila de precios; color/tono del propio badge (`--tienda`) |
| **acciones en la fila** | `.mu-gal-pie { flex-direction: row }` + `.mu-gal-acciones { margin-left: auto }` |
| **zoom** en hover | `.mu-gal-item:hover .mu-gal-item-abrir img { transform: scale(1.05) }` (+ `prefers-reduced-motion: reduce` → `none`) |
| **etiqueta con velo** | `.mu-gal-item-etiqueta { top:auto; bottom:0; left:0; right:0; border-radius:0; background: linear-gradient(transparent, rgb(0 0 0 / 55%)) }` |
| **precio compacto** | `.mu-gal-item-precio` en una linea: `$antes ~ $ya c/u` (`~` y `c/u` por `::before`/`::after`) |

## T8 — Puertas

- `tests/galeria.test.js`: resolucion/publicacion del atributo, existencia de un bloque por
  tema declarado, `.mu-gal-pie` en el contrato, `.mu-gal-cerrar` sin `.mu-modal-close` y
  especificidad de `.mu-gal-acciones .mu-gal-accion`.
- `tests/visor.browser.js`: escenario con tema `cotizador` (2 columnas, badge junto al valor,
  acciones en la misma fila, zoom/velo medidos) y `base` intacto.
- `tests/ficha-pedido.browser.js` (plugin): 52/52 con el tema `base`.