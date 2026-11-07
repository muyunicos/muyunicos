# Visor (`MU.visor`, ámbito 2) — playground y veredicto

**Estado**: **V0** (2026-11-07). El visor ya se prueba **en vivo** en
`tests/galeria-demo.html` (panel "Visor (ambito 2)") y tiene puerta propia:
`node tests/visor.browser.js` → **VISOR OK (32/32 checks, 3 hallazgos)**.
Complementa `spec.md` §3 (FR-024…FR-030) y `contracts/galeria.md` (API del visor).

Este documento es el **tablero de decisión**: lo medido, lo que falta, lo que sobra y lo
que está de más. La columna *Decisión* queda para marcar `SÍ` / `NO` / `después` y así
alimentar las fases V1…V5 (no se implementa nada que no esté marcado).

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
| `#visor?indice=3` | base en la 4ta | `MU.ir(3)` (reemplaza a `#visor3`) |

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
| 8 | 1 solo item | contador `1 / 1` **siempre visible** | **Sobra hoy**: necesita `contador:false` |
| 9 | Imagen rota | marco **vacío**, sin mensaje ni reintento | **Falta** estado de error |
| 10 | Meta larga | 1440×960: entra (877/877). 390×844: entra (844/844) | **Riesgo abierto**: la caja es `92vh` + `overflow:hidden` (`galeria.css:55`); una meta mayor **recorta sin scrollear** |
| 11 | 390 px | imagen y cerrar dentro del viewport; banda scrollea 4 px | **OK** |
| 12 | Precios | solo `precio{valor,anterior,aviso}` (`js:465`) | **Falta** `precios[]` (Regular/Mayor), `nota`, `badge{texto,tono}` |
| 13 | Acciones | solo `etiqueta` + `clase` (`js:713`) | **Falta** `icono` y variantes propias |
| 14 | Marco de la foto | damero siempre (`galeria.css:172`) | **Falta** `marco:'oscuro'` / `tono:'foto'` |
| 15 | Ancho de la caja | 880 px fijo (`galeria.css:52`) | **Falta** `ancho` |
| 16 | Flechas | al costado, `position: static` | **Decidir**: `flechas:'superpuestas'` para el caso tienda |
| 17 | Contenido libre | no existe | **Decidir**: `bloque` (nodo, nunca HTML) |
| 18 | Instancia | `visor()` crea el DOM y `cerrar()` lo borra (`js:586`) | **Falta** singleton + `MU.visor.actualizar()` |

## 3. Inventario: qué falta, qué sobra, qué está de más

### 3.1 Falta (y es necesario para reemplazar los visores existentes)

| Pieza | Por qué | Dónde se nota hoy |
|---|---|---|
| `scroll:'pie'` | la caja recorta con meta larga (1 caso real ya medido) | `galeria.css:55` |
| `contador:false` | con 1 item `1 / 1` es ruido | `js:490` (se pinta siempre) |
| `precios[]` + `nota` + `badge{texto,tono}` | el caso "Regular $153 / Mayor $105 + 31% OFF + nota pie" | `js:458-479` |
| `acciones[].icono` + variante | el botón "Personalizar" lleva icono | `js:712-719` |
| `marco:'oscuro'` | fondo sólido detrás de la foto (el damero es solo para transparencia) | `galeria.css:172` |
| `ancho` | 672 / 880 / 1000 según el consumidor | `galeria.css:52` |
| estado de error de imagen | marco vacío sin explicación ni reintento | `js:673-679` |
| `MU.visor.actualizar()` + singleton | reemplazar un visor **no puede** crear/borrar nodos por apertura | `js:586-588` |
| `[data-mu-galeria-visor]` declarativo | un consumidor sin JS propio | no existe |

### 3.2 Falta pero **opcional** (decidir)

| Pieza | Para qué |
|---|---|
| `flechas:'superpuestas'` | flechas encima de la imagen (hoy van al costado) |
| `bloque` | contenido arbitrario del consumidor (nodo del DOM, nunca HTML) |
| zoom/pan | ampliar dentro del marco |
| `tono:'foto'` | fondo neutro para fotos, distinto de claro/oscuro |

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

## 6. Próximo paso (V1, cuando lo marques)

Orden propuesto, cada uno con su check nuevo en `tests/visor.browser.js` y `GALERIA OK`:

1. `scroll:'pie'` + `ancho` + `contador:false` (arregla el recorte y el ruido).
2. `marco:'oscuro'` + `flechas:'superpuestas'`.
3. `precios[]` + `nota` + `badge{texto,tono}`.
4. `acciones[].icono` + variantes.
5. Estado de error de imagen (mensaje + reintento).
6. Singleton + `MU.visor.actualizar()` + `[data-mu-galeria-visor]`.

Nada de esto se implementa sin la marca en la columna *Decisión* de §2.