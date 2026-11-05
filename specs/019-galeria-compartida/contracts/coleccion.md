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
el chip. Es el `tope` efectivo de una fila **no deslizable** (se recalcula en resize).

## C4 — Opciones de `MU.coleccion`

```js
MU.coleccion(el, {
  items: [],                    // datos; `vacio:true` = ranura

  // forma
  layout: 'fila',               // 'fila' | 'grilla'
  deslizable: 'auto',           // 'auto' | true | false   (solo fila)
  tamano: 'medio',              // 'chico' | 'medio' | 'grande' | {ancho, alto}
  gap: 8,
  filas: 0,                     // solo grilla: tope de filas (0 = todas)
  columnas: 0,                  // solo grilla: 0 = auto-fill segun el ancho

  // cantidad
  tope: 0,                      // max. ITEMS visibles (0 = todos)
  min: 0,                       // min. CARGAS (huecos obligatorios)
  capacidad: 0,                 // max. CARGAS (0 = sin tope de invitacion)
  chip: 'auto',                 // 'auto' | false
  chipAccion: 'abrir',          // 'abrir' | 'cargar' | fn

  // contenido
  preset: 'mini',               // 'mini' | 'tarjeta' | 'producto' | 'opcion'
  slots: null,                  // pisa el preset: {precio:false} o lista de nombres
  activo: -1,

  // pie
  pie: 'auto',                  // 'auto' | false | texto
  sustantivo: 'imagenes',
  sustantivoSingular: 'imagen',

  // eventos
  onAbrir, onRanura, onChip, onEliminar, onFavorito, onAccion, onCambiar
});
```

**`deslizable`**:
- `'auto'` (default): en `fila`, desliza **solo si no caben** (`caben < items`); en `grilla` no aplica.
- `true`: fila con scroll-snap + flechas `.mu-nav-btn` (C8 del contrato del visor).
- `false`: se calcula `tope = min(tope || ∞, caben)` y **no** hay scroll.

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
<div class="mu-gal-coleccion mu-gal-coleccion--fila|--grilla" data-mu-gal-modo="...">
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
- El pie va **arriba** de la linea y se actualiza con cada cambio (`role="status"`).

## C7 — Lo que este contrato NO permite

- Mezclar `min`/`capacidad` con `tope` para "estirar" la fila: **gana `tope`** (C1.5).
- Contar huecos en el chip: los huecos son del `pie`.
- Decidir el layout por media query o por el viewport: se mide el **contenedor**.
- Duplicar el render: `tira` y `tarjeta` pasan a ser **adaptadores** de `coleccion`
  (retiro del codigo viejo en F3).
- Usar `innerHTML` con datos del usuario: todo va por `textContent` / `createElementNS`.

## C8 — Relacion con el visor (ambito 2)

`MU.visor({items, indice, slots, listado: 'abajo'})` **monta una `coleccion`** como listado
inferior con `preset:'mini'`, `deslizable:true` y `activo` sincronizado en dos vias:
- clic en una miniatura -> `visor.ir(i)`;
- cambio en el visor -> marca `activo` y `scrollIntoView({block:'nearest', inline:'center'})`.

El visor **no** implementa su propia tira: es el mismo componente (D2).