# Visor (`MU.visor`, ámbito 2) — playground y veredicto

**Estado**: **V1** (2026-11-07). El visor se prueba **en vivo** en
`tests/galeria-demo.html` (panel "Visor (ambito 2)") y tiene puerta propia:
`node tests/visor.browser.js` → **VISOR OK (66/66 checks, 0 hallazgos)**.
Complementa `spec.md` §3 (FR-024…FR-030) y `contracts/galeria.md` (API del visor).

Este documento es el **tablero de decisión**: lo medido, lo que falta, lo que sobra y lo
que está de más. Las decisiones del usuario (2026-11-07) están en §7 y ya implementadas en
V1; lo que sigue sin marcar **no se implementa**.

## 1. Cómo probarlo

1. Abrir `tests/galeria-demo.html`.
2. Panel izquierdo, grupo **Visor (ambito 2)**: tono, titulo, items, indice, listado,
   segmentos, meta (5 estados), acciones (0-4), imágenes rotas.
3. **Abrir con esto** reproduce el escenario; **Abrir visor** usa los items de la galería.
4. También desde la galería: tocar una imagen (pasa el índice real) o el chip `+N`.

Cada hash describe una vista **completa** (recargar = mismo estado):

| Hash | Escenario | Qué se mira |
|---|---|---|
| `#visor` | 9 items, meta simple, 1 accion, listado | base: contador, flechas, banda |
| `#visor-oscuro` | tono oscuro | contraste de título/contador/meta |
| `#visor-1` | 1 item, sin listado | contador `1 / 1` y flechas ocultas |
| `#visor-meta-rica` | badge + precio anterior/valor | jerarquía del pie |
| `#visor-meta-larga` | descripción de ~600 letras | **¿el pie scrollea o recorta?** |
| `#visor-meta-item` | meta solo en el 2do item | la del item pisa la global |
| `#visor-40` | 40 items | banda con muchos, sincronía |
| `#visor-roto` | urls inexistentes | qué muestra una imagen rota |
| `#visor-sin-lista` | sin banda | layout sin listado |
| `#visor-segmentos` | 2 segmentos | `aria-pressed` |
| `#visor-todo` | oscuro + meta larga + 3 segmentos + 3 acciones + 12 items | todo junto |
| `#visor-transparente` | items con `transparente: true` | el damero solo aparece ahi |
| `#visor-completo` | `ancho:'completo'` | la caja pasa de 880 px |
| `#visor?indice=3` | base en la 4ta | `MU.ir(3)` (reemplaza a `#visor3`) |
| `#estado` | preset `opcion` + `activo:1` (sin `item.estado`) | el slot `estado` cae a "Elegida" |
| `#filtro-etiqueta` | galeria con `filtros:'etiqueta'` | pills Todos/Oferta/Nuevo/Pack |
| `#filtro-estado` | galeria con `filtros:'estado'` (preset `opcion`) | filtro por estado + chip por item |

`#visor3` sigue funcionando como **alias deprecado** (no agrega comportamiento).
El JSON editable acepta `visor:{tono,meta,items,listado,segmentos,acciones,roto}` para
fijar un escenario de un pegado.

## 2. Veredicto medido

Puerta: `tests/visor.browser.js` (Chrome real vía Playwright). Lo que falla rompe la
puerta; los **hallazgos** se reportan sin romperla (son decisiones de producto, no
regresiones).

| # | Punto | Medición | Veredicto |
|---|---|---|---|
| 1 | Foco inicial al abrir | **Fallaba**: abriendo por click `activeElement` quedaba en el disparador | **CORREGIDO en V0** (ver §5) |
| 2 | `Tab` no escapa | 13 tabbables; ciclo cerrado | **OK** |
| 3 | `Escape` cierra y devuelve foco | vuelve a `#pg-visor`, scroll restaurado | **OK** |
| 4 | Banda ↔ imagen (dos vías) | clic en la celda 5 → `5 / 40` + activa | **OK** |
| 5 | 40 items sin `thumb` | cae a `url` sin romper | **OK** |
| 6 | Tono oscuro | `mu-gal-visor--oscuro` aplicada | **OK** |
| 7 | Meta por item | la del item manda sobre la global | **OK** |
| 8 | 1 solo item | el contador se oculta solo (`contador:'auto'`) | **RESUELTO en V1** |
| 9 | Imagen rota | mensaje + **Reintentar** (`?r=<ts>`) y la rota oculta (`mu-gal-img--roto`) | **RESUELTO en V2** (T041) |
| 10 | Meta larga | cuerpo/banda/meta viven en `.mu-gal-scroll` (`overflow-y:auto`): no recorta | **RESUELTO en V1** |
| 11 | 390 px | flechas superpuestas sobre el marco; la imagen usa >82% del ancho | **RESUELTO en V1** |
| 12 | Precios | `pintarMeta` pasa por `normalizarMeta`: `precios[]` con etiqueta y `nota` | **RESUELTO en V2** (T043) |
| 13 | Damero permanente | se veia detras de TODA foto opaca (bandas grises) | **RESUELTO en V1**: damero solo con `item.transparente` |
| 14 | Ancho fijo 880 | una foto vertical dejaba aire a los lados | **RESUELTO en V1** y **rehecho en V3**: `ancho` auto = item promedio, `completo` = llenar (T046) |
| 15 | Segmentos | prometian filtros y no filtraban | **RESUELTO en V1**: son pestañas informativas; los filtros viven en la coleccion (C10) |
| 16 | Borde y separadores | 3px dorado (casi invisible) vs grises frios vs punteado azul | **RESUELTO en V1**: 1px con `--mu-gal-sep` unico |
| 17 | Contraste en oscuro | precio `#047857` sobre `#111827` (~3:1) | **RESUELTO en V1**: `#34d399` |
| 18 | Badge tonal | `normalizarMeta` acepta `badge{texto,tono}` y la forma vieja; el tono va a la clase | **RESUELTO en V2** (T043) |
| 19 | Acciones | solo `etiqueta` + `clase` en `visor()` | **RESUELTO en V2** (T042): `icono` de la familia local + `aria` |
| 20 | Marco de la foto (`tono:'foto'`) | el damero ya es opt-in (V1); falta un marco oscuro propio para fotos | **Decidir** (no es requisito de la V2) |
| 21 | Ancho de la caja | era 880 px fijo | **RESUELTO en V1** y **V3**: dos modos por item promedio (T046) |
| 22 | Flechas | al costado y superpuestas en movil | **RESUELTO en V1** (T036) y **V3**: sin `ajustarFlechas` (T046) |
| 25 | **Flechas fuera del cuerpo** (reportado) | a 700-820 px `prev.left = -9` y `next` 37 px fuera de la caja | **CORREGIDO en V3** (T046): la caja es `lienzo + cromo`; 8 anchos chequeados |
| 26 | **Franja 811-820 px "todo mal"** (reportado) | `fit-content` + marco `flex: 1 1 0%` -> cuerpo de 351 px en una caja de 746 | **CORREGIDO en V3** (T046): sin `fit-content` |
| 27 | **Activo de la banda feo** (reportado) | `outline` externo recortado por `overflow: hidden` + borde del item = doble marco | **CORREGIDO en V3** (T047): anillo interno + `aria-current` |
| 28 | **Flechas de la fila sobresalen** (reportado) | `left/right: -6px` fuera del bloque | **CORREGIDO en V3** (T047): `0` + la fila reserva el ancho |
| 29 | **Lineas divisorias** (reportado) | cuatro rayas apiladas (cabecera, meta, acciones, banda) | **CORREGIDO en V3** (T047): cero lineas, separacion por espacio |
| 30 | **La caja se escapa del 92vh con 768 px de ventana** (medido) | el alto del lienzo era `vh * 0.62` a ojo: la caja pasaba de su `max-height` y el marco empujaba la banda y la meta fuera (el `overflow: hidden` las cortaba) | **CORREGIDO en V4** (T049/T050): alto = 92vh - cromo VERTICAL medido; el cuerpo no encoge y el scroll absorbe |
| 31 | **La banda del visor se aplasta** (reportado) | la mini caia de 74 a 0-62 px: la RAIZ de la coleccion es un flex item encogible y con `overflow: hidden` su minimo automatico pasa a 0 | **CORREGIDO en V5** (T052): piso de la raiz en el AMBITO 1 (`--mu-gal-alto-min` = alto de la celda MAS ALTA), no en el visor |
| 23 | Contenido libre (`bloque`) | no existe | **Decidir** (no es requisito de la V2) |
| 24 | Instancia | `visor.actualizar()` cambia el contenido con el modal abierto y sin recrear el DOM | **RESUELTO en V2** (T044) |

Desde la V2 la banda inferior en 390 px **afirma** que scrollea (es un carrusel por diseno): dejo de
ser un aviso y paso a check. La imagen rota ya tiene estado propio (fila 9), las acciones su
icono (fila 19), la meta su forma unica (filas 12 y 18) y la instancia su `actualizar()` (fila 24):
la puerta queda en **66/66 con 0 hallazgos**. El **unico** pendiente medido es SC-003 en el
laboratorio (T022/T023), que necesita `C:\wp-lab` corriendo.

**V3 (2026-11-07 bis)** cierra las cinco correcciones que reporto el mantenedor (filas 25-29) y
reescribe el modelo de tamano (D19): la puerta queda en **VISOR OK (97/97, 0 hallazgos)** +
**GALERIA OK (245 checks)**. El pendiente de laboratorio sigue siendo el mismo (SC-003).

**V4 (2026-11-07 ter)** corrige el ALTO de la caja (fila 30): el lienzo deja de salir de
`vh * 0.62` a ojo y pasa a `min(92vh - cromo, 720)`, con el cromo VERTICAL medido en el
DOM (cabecera, segmentos, padding del cuerpo, **pie natural** con `scroll.scrollHeight`,
acciones, gaps y bordes); el cuerpo no encoge y `.mu-gal-scroll` absorbe. Medido con
`#visor-todo`: a **1000x768** caja 567x707 / marco 427x320 / banda con 180 px utiles; a
**820x768** caja 541x707 / marco 401x301; a **1440x960** el pie entra exacto (0 px de
scroll interno). En 768 px de alto el pie conserva ~180 px utiles y puede quedar con
20-30 px de scroll dentro de `.mu-gal-scroll` (el lienzo mas chico ensancha el texto de la
meta: nunca se recorta). Puertas: **VISOR OK (107/107 checks, 0 hallazgos)** + **GALERIA
OK (249 checks)**.

**V5 (2026-11-07 quater)** corrige la banda aplastada (fila 31), y la arregla **en el ambito
1**, no en el visor: `coleccion()` publica el piso (`--mu-gal-alto-min`, alto de la celda MAS
ALTA medido con `max(offsetHeight, scrollHeight)`) y `[data-mu-gal-layout]` lo aplica como
`min-height`. La mini vuelve a **74 px** en 4 escenarios x 4 viewports y en movil; en el
ambito 1 un contenedor corto con `overflow: hidden` ya no la recorta. Consecuencia: el cromo
de la V4 ahora mide el pie REAL, asi que el lienzo cede lo suyo (a 1000x768: caja 463x707,
marco 323x242; a 820x768: caja 437x707, marco 297x223) y el pie se ve entero: los numeros de
la V4 quedan superados. Tambien se corrigio `recorta` en la puerta: un pie que **scrollea**
ya no cuenta como recorte (se mide contenido inalcanzable). Puertas: **VISOR OK (141/141
checks, 0 hallazgos)** + **GALERIA OK (252 checks)**.

## 3. Inventario: qué falta, qué sobra, qué está de más

### 3.1 Falta (y es necesario para reemplazar los visores existentes)

| Pieza | Por qué | Dónde se nota hoy |
|---|---|---|
| `scroll:'pie'` | la caja recorta con meta larga (1 caso real ya medido) | `galeria.css:55` |
| `contador:false` | con 1 item `1 / 1` es ruido | `js:490` (se pinta siempre) |
| `precios[]` + `nota` + `badge{texto,tono}` | el caso "Regular $153 / Mayor $105 + 31% OFF + nota pie" | `js:458-479` |
| `acciones[].icono` + variante | el botón "Personalizar" lleva icono | `js:712-719` |
| `marco:'oscuro'` | fondo sólido detrás de la foto (el damero es solo para transparencia) | `galeria.css:172` |
| `ancho` | **decide el TAMANO del lienzo** (`auto` = item promedio, `completo` = llenar) | `galeria.css:52`, `js:643` |
| estado de error de imagen | marco vacío sin explicación ni reintento | `js:673-679` |
| `MU.visor.actualizar()` + singleton | reemplazar un visor **no puede** crear/borrar nodos por apertura | `js:586-588` |
| `[data-mu-galeria-visor]` declarativo | un consumidor sin JS propio | no existe |

### 3.2 Falta pero **opcional** (decidir)

| Pieza | Para qué |
|---|---|
| `bloque` | contenido arbitrario del consumidor (nodo del DOM, nunca HTML) |
| zoom/pan | ampliar dentro del marco |
| `tono:'foto'` | fondo neutro para fotos, distinto de claro/oscuro |

> `flechas:'superpuestas'` **ya no es una opcion** (V1) y desde la **V3** tampoco hay
> `mu-gal-cuerpo--justo`: en `responsive` el lienzo se acota y las flechas van siempre al
> costado; en `adaptive` las superpone el `@media` (D19). `marco:'oscuro'`/`marco` solido:
> **descartado** por el usuario (2026-11-07).

### 3.3 Sobra / está de más (candidato a retiro)

| Pieza | Evidencia de que nadie la usa | Línea |
|---|---|---|
| `MU.tira` (v1) | ni el demo ni `product.js` la llaman | `js:997`, API `js:1901` |
| `MU.tarjeta` (v1) | idem | `js:1126`, API `js:1902` |
| `MU.desdeWoo` / `MU.desdeEnlaces` | el adaptador real es `navegarWoo`/`arrastrar` | API `js:1903-1904` |
| `MU.autoInit` | el demo llama `MU.coleccion` a mano; las fichas también | API `js:1905` |
| alias `MUGaleria` | nadie lo referencia; ya hay dos ámbitos con nombre | `js:1921` |
| `version` duplicada (`js:1912`, hoy `1.0.1`) | son DOS fuentes de versión: el cache-bust real es `style.css` (D12) y ademas el API publica la suya, que hay que bumpear a mano y el test fija con un literal | `js:1912` + `tests/galeria.test.js:53` |

**Condición para retirar** (no antes): una puerta que falle si vuelve a aparecer un
consumidor que los nombre, y el visto bueno del usuario. Mientras tanto: **no se tocan**.

### 3.4 Está de más en el tema (no en la librería)

- `.mu-gal-accion.pmu-visor-peligro` (`galeria.css:258` y alrededores): una clase del
  **plugin** con nombre propio dentro del CSS del tema. Debería ser una variante
  (`mu-gal-peligro`), no el nombre de un consumidor.

## 4. Lo que el playground NO puede probar (y por qué)

- **El modal real de la calculadora** (React/Tailwind): es otro repo (F3/T901). El tema
  entrega componente y contrato; la migración la decide el consumidor.
- **El visor del plugin** (`PMUVisor`, `biblioteca-pmu`): F2/T900, depende de que el
  plugin acepte el tema como dependencia **blanda** (D11).
- **`z-index` real sobre el header sticky**: el demo no carga el header; el overlay del
  tema es `99999` (`global-ui.css:670`) y gana, pero falta la ficha real (T022).

## 5. Bug corregido en V0 (para el changelog)

**Síntoma**: al abrir el visor **por click** (no por hash) el `activeElement` quedaba en
el disparador y el primer `Tab` se iba al documento.

**Causa**: `.mu-modal-overlay` arranca con `visibility: hidden` y tiene
`transition: opacity .25s, visibility .25s` (`global-ui.css:668-688`). Aunque
`is-visible` ya esté en el `className`, el valor **computado** sigue siendo `hidden`
durante la transición y Chromium **ignora `.focus()` en silencio** (verificado con un
parche a `HTMLElement.prototype.focus`: 1er intento `vis=hidden` sin efecto, 2do a los
50 ms `vis=visible` con foco aplicado).

**Corrección** (`js/galeria.js`, apertura): el foco entra **después** de `is-visible` y
se reintenta cada 50 ms (hasta 8 veces) hasta que el navegador lo acepte, **sin robar el
foco** si ya hay uno dentro del modal.

## 6. V2 en curso (2026-11-07)

Cada punto, con su check nuevo en `tests/visor.browser.js` y `GALERIA OK`:

1. ~~Estado de error de imagen (mensaje + reintento).~~ **hecho** (T041).
2. ~~`acciones[].icono` + variantes tonales.~~ **hecho** (T042: familia local, sin set del tema).
3. ~~`precios[]` + `nota` + `badge{texto,tono}`, unificando con `MU.tarjeta`.~~ **hecho** (T043).
4. ~~Singleton + `MU.visor.actualizar()` + `[data-mu-galeria-visor]` declarativo.~~ **hecho**
   (T044; el atributo declarativo se descarto porque ya existe `[data-mu-galeria-abrir]`).

La **V2 queda cerrada**. Lo unico abierto de la spec es SC-003 en el laboratorio (T022/T023).

Orden de implementacion: **T041** (error de imagen) -> **T042** (`icono`) -> **T043** (meta rica) ->
**T044** (singleton + `actualizar`) -> **T045** (docs y bump). Cada paso entra con su commit y sus
checks; el detalle vive en `tasks.md` (fase V2). Lo que sigue sin decision (`bloque`, `tono:'foto'`)
**no** se implementa.

## 7. Decisiones cerradas (2026-11-07)

Preguntas del usuario y como quedaron resueltas en V1:

| # | Pregunta | Decisión |
|---|---|---|
| 1 | "Items (0 = los de la galeria)" no se entiende | era un control **del playground**: 0 = los 9 items reales, N = N items fabricados. Ahora es un `<select>` con opciones nombradas ("las de la galeria", "3 propias", "40 propias"…) |
| 2 | ¿Qué hacen los segmentos? ¿Pueden filtrar por slot? | **NO en el visor**: se quedan como pestañas informativas (`onElegir`). Los filtros son de la **galeria** (`MU.coleccion` C10) y filtran por el slot visible (`etiqueta`, `estado`), con "Todos" y `<select>` cuando hay 8+ valores |
| 3 | El slot `estado` no se ve nunca | doble causa: solo se pintaba en la celda `activo` y el demo no la pasaba; ademas `tarjeta` no trae el slot. Ahora `slots.estado` pinta `item.estado` en **cada** item y cae a "Elegida" en la activa; el demo tiene control de "imagen elegida" y hash `#estado` |
| 4 | ¿Qué es el contador? | "3 / 9". Ahora `contador:'auto'` (oculto con 1 item), `true` o `false` |
| 5 | Ancho de caja: ¿auto / pantalla completa? | `ancho:'auto'` (ajusta al contenido, escritorio) y `'completo'` (casi pantalla); el numero queda como legado |
| 6 | Flechas superpuestas: no deben ser opcion | **decision del componente**: se superponen cuando el marco no llega a 280px utiles (siempre en movil) y si no, van al costado |
| 7 | Marco solido | **descartado**: lo que cambia es que el damero deja de ser permanente (solo con `item.transparente`) |
| 8 | "Falta un pulido de CSS" | tokens locales, un solo separador, ritmo de padding, scroll del cuerpo, contraste del precio en oscuro, `:focus-visible` en segmentos y banda con el tono del visor |