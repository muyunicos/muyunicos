# Contract — `MU.coleccion` (ambito 1) y `MU.visor` (ambito 2)

Contrato v2 (enmienda 2026-11-05). Reemplaza el modelo de "tira + tarjeta": ahora hay
**un solo item con slots** y un **layout configurable**. La aritmetica vive en
`MU.puro.celdas()` y esta congelada por `tests/galeria.test.js`.

## C0 — Nombres

| Ambito | API | Que es |
|---|---|---|
| 1 | **`MU.coleccion(el, opciones)`** | galeria embebida en cualquier lugar (catalogo, producto, opciones, cotizador) |
| 2 | **`MU.visor(opciones)`** | modal de ampliacion; puede montar una coleccion abajo |

`window.MU` agrupa: `coleccion`, `visor`, `arrastrar`, `navegarWoo`, `puro`, `version`.
`window.MUGaleria` queda como **alias** una release (`tira`/`tarjeta` -> `coleccion`).

## C1 — `MU.puro.celdas({cargadas, min, capacidad, tope})`

Devuelve `{visiblesItems, huecos, chip, reservaChip, obligatorios, invitacion, celdas}`.

```
n = cargadas
obligatorios = max(0, min - n)
invitacion   = (min > 0 || capacidad > 0) && n >= min && (capacidad == 0 || capacidad > n) ? 1 : 0

reservaChip  = tope > 0 && n > tope
celdasItems  = tope == 0 ? n : (reservaChip ? tope - 1 : min(tope, n))
visiblesItems = min(n, celdasItems)
chip          = reservaChip ? n - visiblesItems : 0
libres        = tope == 0 ? (obligatorios + invitacion)
                        : max(0, tope - visiblesItems - (reservaChip ? 1 : 0))
huecos        = min(obligatorios + invitacion, libres)
```

Reglas cerradas con el mantenedor:

1. **Las imagenes van primero**; los huecos rellenan lo que quede.
2. **El chip cuenta SOLO items ocultos**. Los huecos los cuenta el `pie` (C2), no el chip.
3. Si no caben todos los items, el chip **reserva** una celda (`tope - 1`).
4. Los huecos **obligatorios** compiten por celda; la **invitacion** solo rellena si sobra.
5. `tope` **gana** sobre `min`: la fila no se estira para mostrar lo obligatorio.

Tabla de referencia (`min 4`, `capacidad 8`, `tope 3`), verificada en la puerta:

| Cargadas | Resultado | Pie |
|---|---|---|
| 0 | `[+][+][+]` | Carga 4 imagenes |
| 1 | `[O][+][+]` | Carga 3 imagenes |
| 2 | `[O][O][+]` | Carga 2 imagenes |
| 3 | `[O][O][O]` | Carga 1 imagen |
| 4 | `[O][O][+2]` | Puedes agregar 4 mas |
| 7 | `[O][O][+5]` | Puedes agregar 1 mas |
| 8 | `[O][O][+6]` | Maximo alcanzado |

## C1bis — `MU.puro.carga({items, tope, caben, huecos, tandas})`

La **carga** decide `chipAccion:'auto'`: cuantas imagenes se ven, cuanto **SUMA** el chip y
cuando deja de sumar para **abrir el visor**. Es **acumulativa**: lo revelado nunca se oculta.

```
topeC     = tope                                       // 0 = sin tope
capBase   = max(1, caben - huecos)                      // celdas de IMAGEN sin contar chip
topeBase  = tope > 0 ? max(0, tope - huecos) : Infinity
hayChip   = items > min(capBase, topeBase)              // quedaran imagenes ocultas
capacidad = max(1, capBase - (hayChip ? 1 : 0))          // una "pantalla" FIJA
topeItems = tope > 0 ? max(0, topeBase - (hayChip ? 1 : 0)) : Infinity
visibles  = min(items, topeItems, max(min(capacidad, topeItems, items),
                                      capacidad * (1 + tandas)))
ocultas   = max(0, items - visibles)
siguiente = min(topeItems, visibles + capacidad)
navega    = visibles > capacidad                        // DERIVADO
agotado   = visibles >= min(topeItems, items) && ocultas > 0   // el chip ABRE el visor
```

Devuelve `{capacidad, tandas, revelados, visibles, ocultas, paso, siguiente, navega,
agotado, topeCeldas, topeItems, hayChip}`.

Reglas:

0. **Chip ⇔ cota** (C9). El chip existe solo si la vista esta **acotada** (`tope > 0`, la
   ventana de la grilla por `filas`/`alto`/marco que recorta, o el ancho en `fila`) y no
   entra todo. **Sin cota** `caben` llega como `items + huecos` y no hay chip: se ve todo.
1. **`tope` es de CELDAS**, igual que en `celdas` (C1): las imagenes, el chip
   (`mu-gal-celda--chip`) y los huecos (`mu-gal-celda--vacia`) que se ven cuentan dentro
   de el. Con `tope 4`, 2 huecos y 5 imagenes: `1 imagen + 2 huecos + chip = 4 celdas`.
2. Si el chip aparece, **ocupa una celda** de la vista fija (`capacidad`) y del `tope`
   (`topeItems`). Con 2 celdas y chip se ve 1 imagen, no 2.
3. **`navega` es DERIVADO, no una opcion**: en cuanto `visibles > capacidad` la galeria pasa a
   navegable (scroll + flechas) sola. En la vista fija no hay scroll.
4. El estado que se conserva es **`tandas`** (cuantas pantallas pidio el usuario), **no** un
   numero absoluto: si la vista cambia de capacidad (`resize`, `filas`, `alto`, o la medicion
   real de la celda) lo revelado se **re-ajusta solo**. La firma
   (`items/tope/min/capacidad/layout/filas/columnas/alto/navegable`) lo resetea a 0;
   **redimensionar lo conserva**.
5. Los **huecos** (`min`/`invitacion`) van **siempre primero** y descuentan tanto la vista
   fija como el `tope`.
6. **`agotado`** = ya se llego al `tope` y **aun** quedan imagenes: ahi el chip deja de sumar
   y abre el visor (nunca deja imagenes inalcanzables). Con `tope 1` la unica celda es el
   chip: `visibles 0`, `agotado`.

Tabla de referencia (`items 12`, `caben 4` -> `capacidad 3`):

| Tandas | Ve | Chip | Accion del chip | Estado |
|---|---|---|---|---|
| 0 (arranque) | 3 (0..2) | `+9` | revelar (suma 3) | fija |
| 1 | 6 (0..5) | `+6` | revelar (suma 3) | **navegable** |
| 3 | 12 (0..11) | — | — | navegable |

Que celdas entran en el `tope` (`caben 6`, `items 12`):

| `tope` | huecos | Ve | Chip | Celdas ocupadas |
|---|---|---|---|---|
| 0 | 0 | 5 | `+7` | 6 (lo limita la vista) |
| 4 | 0 | 3 | `+9` | **4** |
| 4 | 2 | 1 | `+4` | **4** (1 + 2 + chip) |
| 2 | 0 | 1 | `+11` | **2** (1 + chip) |
| 1 | 0 | 0 | `+12` | **1** (solo el chip, abre visor) |

Con `tope 6` (celdas) y `caben 4`: `topeItems 5`, asi que a las 2 tandas ve 5 y el chip pasa
a **abrir visor** (`+7`, `agotado`) — 5 imagenes + chip = 6 celdas.

**`chipAccion:'auto'`** (default): `navegable === false` -> `'abrir'` (no acumula);
`agotado` -> `'abrir'`; si no, `'revelar'`. `'abrir'` abre siempre; `'cargar'` delega en
`onRanura`; `'paginar'` es el nombre viejo de `'revelar'`.

## C2 — `MU.puro.pie({cargadas, min, capacidad, sustantivo, sustantivoSingular})`

Texto que reemplaza al chip para los huecos:

| Estado | Texto |
|---|---|
| `n < min` | `Carga {min - n} {sustantivo}` (singular si falta 1) |
| `min <= n < capacidad` | `Puedes agregar {capacidad - n} mas` |
| `n >= capacidad` | `Maximo alcanzado` |
| sin `min` ni `capacidad` | `''` (no hay pie) |

## C3 — `MU.puro.caben(ancho, celda, gap)`

`max(1, floor((ancho + gap) / (celda + gap)))`. Con `100 / 30 / 0` -> **3**: dos items y
el chip. Es el `tope` efectivo de una fila **fija** (se recalcula en resize).

## C4 — Opciones de `MU.coleccion`

```js
MU.coleccion(el, {
  items: [],                    // datos; `vacio:true` = ranura

  // forma
  layout: 'fila',               // 'fila' | 'grilla'
  navegable: 'auto',            // 'auto' | true | false   (alias: deslizable)
  tamano: 'medio',              // 'chico' | 'medio' | 'grande' | {ancho, alto}
  gap: 8,
  filas: 0,                     // solo grilla: filas de la vista (0 = por `alto`); acota
  columnas: 0,                  // solo grilla: 0 = auto-fill segun el ancho
  alto: 0,                      // solo grilla: ventana del bloque en px, PIE INCLUIDO
                                //   (0 = el marco, solo si recorta; ver C9)

  // cantidad
  tope: 0,                      // max. CELDAS visibles: imagen + chip + hueco (0 = todas)
  min: 0,                       // min. CARGAS (huecos obligatorios)
  capacidad: 0,                 // max. CARGAS (0 = sin tope de invitacion)
  chip: 'auto',                 // 'auto' | false
  chipAccion: 'auto',           // 'auto' | 'abrir' | 'cargar' | fn

  // contenido
  preset: 'mini',               // 'mini' | 'tarjeta' | 'producto' | 'opcion'
  slots: null,                  // pisa el preset: {precio:false} o lista de nombres
  activo: -1,

  // pie
  pie: 'auto',                  // 'auto' | false | texto
  sustantivo: 'imagenes',
  sustantivoSingular: 'imagen',

  // eventos
  onAbrir, onRanura, onChip, onEliminar, onFavorito, onAccion, onCambiar,
  onPintado                     // (res) se llama en CADA pintado, incluidos los propios
});
```

**`navegable`** (alias historico `deslizable`, misma clave):
- `'auto'` (default): arranca **fija** (entra lo que cabe) con el chip `+N`; al tocarlo **suma**
  una pantalla mas y, como ya no cabe, la galeria pasa a navegable sola (scroll + flechas).
- `true`: revela todo hasta el `tope` y es navegable desde el inicio.
- `false`: nunca navega; el chip **abre el visor** (no acumula).

**Vista fija**: fila = las celdas que entran en el ancho. Grilla = `columnas x filas`: la
ventana la decide **C9** (`filas` > `alto` > marco que recorta), el **pie** se descuenta
siempre y, si **nada acota**, se ven todas (sin chip). El alto de celda se **mide** sobre el
pintado anterior y, si el nodo esta vacio, se estima; tras esa medicion la galeria se
repinta UNA vez (auto-ajuste) y avisa por `onPintado`.

**`tamano`** fija el lado de la celda: `chico 72`, `medio 112`, `grande 160`, o `{ancho,alto}`
para medidas libres (el calculo usa el numero declarado, nunca mide el DOM).

## C5 — Slots y presets

Un item se dibuja con **slots** (que se ve) y **preset** (atajo):

| Slot | Que dibuja | Fuente del dato |
|---|---|---|
| `imagen` | la imagen | `thumb \|\| url` |
| `etiqueta` | chip corto | `item.etiqueta` |
| `favorito` | estrella toggle | `item.favorito` + `onFavorito` |
| `eliminar` | tachito con confirmacion | `onEliminar` |
| `titulo` | titulo | `item.titulo` |
| `descripcion` | descripcion | `item.descripcion` |
| `precio` | `$valor` + `anterior` tachado + `promo` + `aviso` | `item.precio` |
| `acciones` | botones declarados | `item.acciones` |

| Preset | Slots |
|---|---|
| `mini` | imagen, eliminar |
| `tarjeta` | imagen, etiqueta, favorito, titulo, descripcion, precio, acciones |
| `producto` | imagen, etiqueta, titulo, precio |
| `opcion` | imagen, titulo, estado (activo) |

`slots` **pisa** el preset: `{precio: false}` quita el precio; un array reemplaza la lista.

## C6 — DOM

```html
<div class="mu-gal-coleccion mu-gal-coleccion--fila|--grilla" data-mu-gal-modo="..."
     data-mu-gal-estado="fija|navegable" data-mu-gal-ancho="740">
  <p class="mu-gal-pie" role="status">Carga 4 imagenes</p>          <!-- si hay pie -->
  <div class="mu-gal-linea" data-mu-gal-modo="linea|grilla">
    <div class="mu-gal-celda" data-mu-gal-mini="0">
      <div class="mu-gal-item">  <!-- slots -->
        <button class="mu-gal-item-abrir" aria-label="..."><img ...></button>
        <span class="mu-gal-item-etiqueta">Oferta</span>
        <button class="mu-gal-item-favorito" aria-pressed="false">☆</button>
        <button class="mu-gal-item-eliminar" aria-label="Quitar ...">×</button>
        <div class="mu-gal-item-cuerpo">
          <p class="mu-gal-item-titulo">...</p>
          <p class="mu-gal-item-desc">...</p>
          <p class="mu-gal-item-precio">...</p>
          <div class="mu-gal-item-acciones">...</div>
        </div>
      </div>
    </div>
    <button class="mu-gal-celda mu-gal-celda--vacia" data-mu-gal-ranura="1">+</button>
    <button class="mu-gal-celda mu-gal-celda--chip" data-mu-gal-chip="6">+6</button>
  </div>
</div>
```

- La miniatura sigue siendo un **contenedor** con botones (nunca boton anidado, D14).
- El chip es una **celda mas** (no flota fuera): es lo que arregla el `+N` recortado.
- El chip publica su accion en `data-mu-gal-chip-accion` (`revelar|abrir|cargar`).
- La raiz publica el estado de la vista (`data-mu-gal-estado`): `fija` mientras entra todo lo
  revelado, `navegable` cuando ya no cabe. `data-mu-gal-ancho` es el ancho medido del contenedor
  y `data-mu-gal-cota` la **fuente** de la cota (`ancho|filas|alto|marco|libre`).
- El pie va **arriba** de la linea y se actualiza con cada cambio (`role="status"`).

## C7 — Lo que este contrato NO permite

- Mezclar `min`/`capacidad` con `tope` para "estirar" la fila: **gana `tope`** (C1.5).
- Contar huecos en el chip: los huecos son del `pie`.
- Decidir el layout por media query o por el viewport: se mide el **contenedor**.
- Duplicar el render: `tira` y `tarjeta` pasan a ser **adaptadores** de `coleccion`
  (retiro del codigo viejo en F3).
- Usar `innerHTML` con datos del usuario: todo va por `textContent` / `createElementNS`.

## C8 — Relacion con el visor (ambito 2)

`MU.visor({items, indice, listado: 'abajo'})` **monta una `coleccion`** (la misma pieza, D2) como
banda inferior, con `preset:'mini'`, `tamano:'chico'`, `pie:false`, `tope:0` y
`navegable:true` (todo revelado y con scroll: el listado nunca lleva chip, porque la celda
activa tiene que existir para que `MU.ir`/`marcarLista` puedan acercarla). Sincronizacion
**dos vias**:

- clic en una miniatura -> `MU.ir(i)`;
- `MU.ir(i)` o las flechas/teclado -> marca `mu-gal-celda--activa` en la celda `i` y la acerca
  con `scrollIntoView({block:'nearest', inline:'nearest'})` (no salta si ya se ve).

`MU.ir(i)` es **publico** (`MU.ir`), acota el indice en circular y repinta: no hay una segunda
tira que mantener en sincronia a mano. El **repintado por resize** conserva la activa
(`marcarLista` guarda `activo` en el estado del contenedor); sin eso el observer volvia a marcar
la celda del montaje y se perdia la sincronizacion.

El visor **no** implementa su propia tira: es el mismo componente (D2).

## C9 — Cota y ventana (`MU.puro.ventana`)

La vista fija **solo existe si algo la acota**. De ahi sale la regla que decide si hay chip:

| layout | cota | sin cota |
|---|---|---|
| `fila` | **siempre** el ancho: `caben(ancho, celda, gap)` = una linea | no aplica |
| `grilla` | `filas > 0` (manda) → `alto > 0` → **marco que recorta de verdad** | se pintan TODAS las filas, sin chip |

```js
MU.puro.ventana({alto, filas, altoCelda, gap, pie, marco, acota})
// -> {acota, porAlto, altoMarco, motivo}   motivo: 'filas'|'alto'|'marco'|'libre'
```

1. **`acota`** (el parametro) es SOLO la evidencia del marco: `scrollHeight > clientHeight + 1` medido **antes**
   de vaciar el nodo. Un marco que crece con el contenido **no acota** (`clientHeight` es el
   alto del contenido; comparar solo `clientHeight > 0` era el bug: el marco medía su propio
   recorte y la grilla mostraba una fila con 3 columnas). Las cotas **declaradas** (`alto`,
   `filas`) viajan por su propio parametro: pasarlas aca metia el marco medido en el calculo
   y dejaba la vista clavada (ver el punto 3).
2. **El `pie` se resta siempre**: `alto` es la ventana TOTAL del bloque (pie incluido). Con
   `alto 420` y `pie 15` el marco mide `405`, no 420 (si no, la galeria desbordaba el bloque).
3. **`filas`** es una cota **DECLARADA**: acota aunque el marco crezca y **basta por si sola**
   (sin `alto`). Su presupuesto NO se toma del marco medido, que es justo lo que esta misma
   galeria acabo de recortar; tomarlo del marco cierra el calculo en una fila
   (`alto:0, filas:3, altoCelda:246, marco:246` daba `porAlto 1` / `altoMarco 246`; ahora da
   `3` / `754`). **Fija el alto del marco**:
   `altoMarco = filas*altoCelda + (filas-1)*gap`, con `alto` como maximo. No hay scroll
   hasta que se toca el chip (ahi lo revelado ya no cabe y la galeria pasa a navegable).
4. **Sin cota**: `porAlto = 0`, `caben` llega a `carga` como `items + huecos`, `capacidad`
   infinita ⇒ todo visible, `chip 0`, `navegable:'auto'` ≡ `true`.
5. **Auto-ajuste por evidencia**: si el marco recorta pero se pinto sin cota, o si el alto de
   celda venia estimado, la galeria se repinta UNA vez (marca `firma|recorta`). El recorte
   medido se guarda en el estado del contenedor: sin eso el siguiente pintado volveria a
   "no acota" y entraria en ciclo.
6. **`ventana` es puro** y no toca el DOM: se prueba con `node tests/galeria.test.js`.