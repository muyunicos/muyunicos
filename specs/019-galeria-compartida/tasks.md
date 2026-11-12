# Tasks: Galeria compartida del tema (MUGaleria)

**Input**: `/specs/019-galeria-compartida/` — `spec.md` (D1-D24), `contracts/coleccion.md`
(C0-C9), `contracts/galeria.md` (visor C0-C8).

**Prerequisites**: `js/galeria.js`, `css/components/galeria.css`, `inc/galeria.php`,
`tests/galeria-demo.html`.

**Tests**: `node tests/galeria.test.js` (GALERIA OK), `node --check js/galeria.js`,
`php -l inc/galeria.php`, revision visual de `tests/galeria-demo.html` y —para las
puertas de geometria— Playwright sobre el demo (`scrollHeight == clientHeight`).

## Format: `[ID] [P?] Description`

- `[X]` ejecutada; `[ ]` pendiente; `[P]` va en paralelo (archivos distintos).

---

## Fase F0 — Base compartida (visor + tiras + tarjetas)

- [X] T001 `js/galeria.js`: API publica (`MU.visor`, `MU.tira`, `MU.tarjeta`, `MU.arrastrar`,
      `MU.navegarWoo`, `MU.autoInit`) y `MU.puro` (`acotar`, `preCargar`, `orden`,
      `formatearPrecio`) — D1-D10.
- [X] T002 Visor con el modal del TEMA (`.mu-modal-container` / `.mu-modal-close` /
      `.is-visible`): no se crea un modal nuevo (D2) y las clases propias van con prefijo
      `mu-gal-` (C7).
- [X] T003 `css/components/galeria.css`: celdas, item, slots, hueco y visor; cero datos en CSS
      (las medidas salen de `--mu-gal-tam` / `--mu-gal-gap`).
- [X] T004 `inc/galeria.php`: registra `mu-galeria` (998) y la encola SOLO en `is_product()`
      (1001), con el filtro `mu_galeria_en_ficha` y el respaldo del footer.
- [X] T005 `js/product.js`: el gesto drag-to-scroll de WooCommerce vive en `MU.arrastrar`
      (una sola implementacion, FR-014) y la lupa en `MU.navegarWoo`.
- [X] T006 `tests/galeria.test.js` como puerta + `tests/galeria-demo.html` como laboratorio.

## Fase F1 — Coleccion y presets

- [X] T007 `MU.coleccion(el, opciones)`: `layout` (`fila`/`grilla`), `preset`
      (`mini`/`tarjeta`/`producto`/`opcion`), `slots`, `activo`, `borrar`, `min`,
      `capacidad`, `tope`, `contador`.
- [X] T008 `MU.puro.celdas` (D21), `pie` (D22), `caben` (D23) y `carga` (C1bis) con sus
      checks de aritmetica pura.
- [X] T009 `ResizeObserver` con `scrollLeft` y foco conservados (FR-016).

## Fase F2.5b — Modelo v2 (chip acumulativo + navegable auto)

- [X] T010 `MU.puro.carga`: chip ACUMULATIVO (suma una pantalla, `tandas` como estado) y
      `agotado` en el `tope` (C1bis).
- [X] T011 `navegable: 'auto' | true | false` (alias `deslizable`) con `navegableDe`; `navega`
      es DERIVADO de lo revelado vs la vista.
- [X] T012 `tope` cuenta TODAS las celdas visibles (`--chip`, `--vacia`) sin perder ninguna
      variante de `celdas`.
- [X] T013 `MU.puro.ventana` (C9): `filas` es cota DECLARADA (no usa el marco medido) y el
      `pie` se resta del presupuesto.
- [X] T014 Raiz `[data-mu-gal-layout]` en columna: pie fijo arriba, `.mu-gal-caja-linea` toma
      el alto restante (el marco nunca desborda el bloque).
- [X] T015 `MU.visor` con `listado` (banda en preset mini), `arrastrar`, `navegarWoo`,
      `desdeWoo` y `desdeEnlaces` (FR-029).
- [X] T016 Estado publicado para QA: `data-mu-gal-estado`, `data-mu-gal-cota`,
      `data-mu-gal-ancho` + hashes del demo (`#grilla-f2`, `#tope`, `#sin-limite`, ...).

## Fase Cota exacta (D24, C9)

- [X] T017 Medir el defecto: en `#grilla-f2` la celda real mide 214.25 px (112 de imagen +
      100.25 de cuerpo + 2 de borde) pero `ventana()` recibe 204 (estimacion por slot:
      `112 + 80 + 12`); el marco queda en 416 y el contenido en 437 ⇒ **21 px** de desborde
      con scroll interno.
- [X] T018 `js/galeria.js`: publicar `data-cota` y `--fila-alto` en la LINEA con el alto de
      celda REAL (medido despues de insertar la linea en el DOM, redondeado hacia ARRIBA para
      que el grid nunca quede medio pixel corto) y recalcular el marco con la misma funcion
      pura `ventana()` — sin duplicar aritmetica.
- [X] T019 `js/galeria.js`: `observarFuentes()` re-mide UNA vez cuando asienta la fuente del
      tema (`document.fonts.ready`), porque el cuerpo cambia de alto ~10 px y la firma no
      cambia (nadie repintaba).
- [X] T020 `css/components/galeria.css`: `.mu-gal-linea--grilla[data-cota]` fija
      `grid-auto-rows: var(--fila-alto)` y la celda obedece (`max-height` + `overflow:hidden`).
      Sin `data-cota` (vista libre) las filas siguen creciendo con el contenido.
- [X] T021 Puertas: `GALERIA OK (204 checks)` con 9 checks nuevos de la cota + Playwright
      sin desborde (`scrollHeight == clientHeight`, `desborde 0` y `clip 0` en los 12 hashes;
      `#grilla-f2` paso de 437/416 a 438/438) y regresion en las vistas SIN cota
      (`#sin-limite`, `#tope`, `#huecos`, `#mini`, `#visor`: sin `data-cota` ni `--fila-alto`).

## Fase SC-003 — Ficha real en el laboratorio

- [ ] T022 `tests/galeria.browser.js`: ficha real (`C:\wp-lab`) con las thumbs (un solo gesto
      de arrastre), `.woocommerce-product-gallery__trigger` abriendo `MU.visor` con TODAS las
      fotos en la activa, `Escape` cerrando y restauracion del scroll, zoom de Woo sin abrir
      Photoswipe y `galeria.js` ausente fuera de `is_product()`.
- [ ] T023 Registrar la corrida en `spec.md` §4 (SC-003) con el numero de comprobaciones.

## Fase C1bis — El chip carga una pantalla y cierra fila (2026-11-07)

- [X] T024 `js/galeria.js`: `MU.puro.carga()` toma el paso de `caben` (una PANTALLA de celdas)
      en vez de `capacidad`, y `siguiente` suma ese paso. Nuevo `MU.puro.topeEnFilas(tope,
      porFila)`; `coleccion` resuelve `porFila` (columnas o la linea completa) y le pasa a
      `carga` el `tope` ya materializado en filas completas (C1ter).
- [X] T025 `js/galeria.js`: el chip `+1` desaparece cuando el item entra en el `tope`
      (`ocultas === 1 && visibles + 1 <= topeItems` -> se muestra) y `hayChip` devuelve
      `ocultas > 0` (el chip de AHORA, no el potencial).
- [X] T026 Puertas: `GALERIA OK (211 checks)` con 7 checks nuevos (paso = pantalla, el caso
      1 hueco / 14 imagenes / 3 columnas, alineacion `(huecos + visibles + 1) % columnas === 0`
      para 3/4/6 columnas y 0..3 huecos en 24 tandas, el `+1` que desaparece y el que el `tope`
      conserva, `topeEnFilas` + su cableado) y `tests/galeria-demo.html` con `#carga-huecos` y
      `#grilla-f2x` (`#carga` pasa a `tope 12` = 2 filas de 6), mas recarga por `hashchange`
      para que cada hash sea una vista COMPLETA.
- [X] T027 Playwright con Chrome real (`CHIP OK (26/26)`): el caso del reporte (1 hueco +
      4 imagenes + `+10` -> al tocar 10 + `+4` en 12 celdas = 3 filas, con el chip en la ULTIMA
      celda de su fila), `+1` inexistente con 16 items, el `tope` en filas completas y la
      regresion de la cota (`#grilla-f2` sin desborde) y de las vistas sin cota.

## Fase V0 — Playground y puerta del visor (2026-11-07)

- [X] T028 `tests/galeria-demo.html`: grupo **"Visor (ambito 2)"** con las opciones REALES
      del visor (tono, titulo, items propios o los de la galeria, indice, listado,
      segmentos, meta con 5 estados, acciones 0-4, imagenes rotas) + **11 hashes** de
      escenario (`#visor`, `-oscuro`, `-1`, `-meta-rica`, `-meta-larga`, `-meta-item`,
      `-40`, `-roto`, `-sin-lista`, `-segmentos`, `-todo`) y `#visor3` degradado a alias
      de `#visor?indice=3` (el `abrirVisor` ya no lleva opciones fijas y el JSON acepta
      `visor:{...}`). Un comentario del panel lista lo PENDIENTE (lo que el visor NO
      acepta hoy): no se ofrece como control para no mentir.
- [X] T029 `tests/visor.browser.js` (Playwright + Chrome real): **VISOR OK (32/32 checks,
      3 hallazgos)**. Mide apertura/`aria-modal`, foco, scroll-lock, contador, flechas,
      banda sincronizada (celda 5 -> `5 / 40`), meta por item, 40 items, imagen rota,
      segmentos, `ArrowLeft/Right`, trampa de foco con 10 `Tab`, `Escape` + foco devuelto,
      movil 390x844 y el recorte del pie. Los hallazgos (contador de 1 item, imagen rota
      sin mensaje, banda con scroll horizontal) se **reportan sin romper** la puerta.
- [X] T030 `specs/019-galeria-compartida/visor-evaluacion.md`: tablero de decision con lo
      medido, el inventario **falta / sobra / de mas / necesario** (con archivo:linea) y el
      orden propuesto para V1…V5.
- [X] T031 Fix medido del visor: el foco inicial **no entraba** al abrir por click. Causa:
      `.mu-modal-overlay` arranca en `visibility: hidden` y su `transition` mantiene el
      valor computado en `hidden`, y Chromium ignora `.focus()` en silencio. Ahora el foco
      entra despues de `is-visible` con reintento acotado (8 x 50 ms) y sin robar el foco
      si ya hay uno dentro del modal.

## Fase V1 — Pulido del visor + filtros de la coleccion (2026-11-07)

Decisiones del usuario en `visor-evaluacion.md` §7. Puerta al cierre: **VISOR OK
(52/52, 1 hallazgo)** + **GALERIA OK (222)**.

- [X] T032 CSS del visor: tokens LOCALES (`--mu-gal-pad/sep/lienzo/banda`), borde de 1px
      (el dorado de 3px era casi invisible sobre blanco), separador unico para cabecera,
      meta, banda y acciones, ritmo de padding, `:focus-visible` en `.mu-gal-seg`, contraste
      del precio en tono oscuro (`#34d399`) y banda con el tono del visor (sin cajitas
      claras en oscuro).
- [X] T033 Damero **opt-in**: el marco pinta el damero solo si `item.transparente`; con una
      foto opaca eran bandas grises alrededor (defecto visual nº1 medido). Checks: `#visor`
      sin damero, `#visor-transparente` con damero.
- [X] T034 `contador:'auto'|true|false` (default `auto`: oculto con 1 item) y
      `ancho:'auto'|'completo'|n` (`auto` = la caja se ajusta al contenido en escritorio).
      Checks: `1 item` sin contador, `auto` <= 880px, `completo` > 880px.
      **SUPERADO por T046**: `auto` ya no ajusta al contenido (lienzo del item promedio).
- [X] T035 Scroll del cuerpo: cabecera y acciones fijas, cuerpo/banda/meta en
      `.mu-gal-scroll` (`overflow-y:auto`). La meta larga ya no se recorta (check en movil).
- [X] T036 Flechas **superpuestas automaticas** (decision del componente, no opcion):
      `ajustarFlechas()` mide el ancho y marca `mu-gal-cuerpo--justo`; en movil quedan sobre
      el marco a media altura y la imagen usa >82% del ancho. Se recalcula al redimensionar.
      **SUPERADO por T046**: `ajustarFlechas()` se retira; en `responsive` las flechas van
      siempre al costado porque el lienzo ya se acota, y en `adaptive` lo hace el `@media`.
- [X] T037 `MU.coleccion` **filtros** (contrato C10): `filtros:'campo'|[campos]` +
      `filtroActivo`; valores derivados de los datos, solo campos con slot visible, "Todos"
      primero, `<select>` con 8+ valores, reseteo de tanda y chip/pie/min sobre lo filtrado.
      Puros nuevos `filtrar`/`valoresFiltro` + checks de navegador (`#filtro-etiqueta`,
      `#filtro-estado`).
- [X] T038 Slot `estado` por item: pinta `item.estado` cuando existe y cae a "Elegida" solo
      para la celda `activo` (antes dependia de un `activo` que nadie pasaba).
- [X] T039 Demo: select de imagenes del visor (en vez del numero criptico), control de
      "imagen elegida", control de filtros, hashes `#visor-transparente`, `#visor-completo`,
      `#estado`, `#filtro-etiqueta`, `#filtro-estado`.
- [X] T040 Docs: `contracts/galeria.md` (C1 con `contador`/`ancho`/`transparente`, C1.1, C1.2
      "segmentos NO filtra"), `contracts/coleccion.md` (C10 + slot `estado`),
      `visor-evaluacion.md` (§2 actualizado, §7 decisiones) y bump `MU.version` 1.1.0.

## Fase V2 — Visor V2 (2026-11-07, en curso)

Arranca con la puerta en **VISOR OK (52/52, 1 hallazgo)** + **GALERIA OK (222)**: el
hallazgo del carrusel en movil paso a ser un check afirmado (scrollea porque es un
carrusel), asi que el unico hallazgo abierto es la imagen rota. Un commit por tema; cada
uno suma checks a `tests/visor.browser.js` y, si toca los puros, a `tests/galeria.test.js`.

- [X] T041 Estado de error de imagen: `.mu-gal-error` (mensaje + boton Reintentar) dentro de
      `.mu-gal-marco`, `error`/`load` en el montaje y reset al cambiar de `src`; el reintento
      pide `url?r=<ts>` (cache-bust). Cierra el hallazgo 1 de §2 y el punto 1 de §6.
- [X] T042 `acciones[].icono` con el mapa LOCAL `ICONOS` (`usar`/`descargar`/`corazon`/
      `basura`, familia del chevron: `createElementNS`, sin `innerHTML`) y clase buena
      `mu-gal-peligro` (`pmu-visor-peligro` se conserva como puente hasta T900).
- [X] T043 Meta rica: `MU.puro.normalizarMeta()` (retrocompatible con `badge` string y
      `precio{valor,anterior,aviso}`) -> `badge{texto,tono}`, `precios[]` y `nota`; lo usan
      `pintarMeta` del visor y `MU.tarjeta`, para no tener dos formatos.
- [X] T044 Singleton + `MU.visor.actualizar(parcial)` (items/meta/titulo/acciones/segmentos/
      contador/ancho sobre el modal abierto, sin cerrar ni recrear el DOM). El declarativo NO se
      duplica: `[data-mu-galeria]` + `[data-mu-galeria-abrir]` ya abren el visor (D3), asi que
      `[data-mu-galeria-visor]` se descarta por ser un segundo nombre de lo mismo.
- [X] T045 Docs: `contracts/galeria.md` (C1 con error/iconos/meta v2/`actualizar`),
      `visor-evaluacion.md` (§2 y §6 cerrados) y bump de `MU.version` a 1.2.0 +
      `Version:` de `style.css` (cache-bust, D12).

- [X] T046 **Dos modos de tamano** (C1.1 v3): `adaptive` (<=600 px, pantalla completa) y
      `responsive` (>600 px) con el **lienzo del item PROMEDIO**. Puro nuevo
      `MU.puro.lienzo()` (media geometrica de aspecto/area + `contain` + minimos + `llenar`
      para `completo`); `medirItems()` completa `w`/`h` desde `naturalWidth` de una `<img>`
      del DOM con la misma url. El JS publica `--mu-gal-lienzo-w/h` y retira `fit-content`,
      `ajustarFlechas()` y las clases `--auto`/`--completo`/`--justo`: el lienzo queda FIJO
      y navegar no mueve la caja. Checks: 8 anchos (601/700/768/811/815/820/900/1200) x
      (caja fija + flechas dentro + marco dentro del cuerpo) + estabilidad al navegar a
      900 px y en la franja reportada de 811 + modo `adaptive` a 520 px.
- [X] T047 Acabado del visor: activo de la banda con anillo INTERNO (`box-shadow` inset) +
      `aria-current` y sin el `outline` externo que recortaba la banda; flechas de
      `mu-gal-caja-linea` dentro del bloque (`left/right: 0`) con la fila reservandoles el
      ancho; y **cero** lineas divisorias (cabecera/meta/acciones/banda se separan con
      espacio, no con rayas).
- [X] T048 Docs: `contracts/galeria.md` C1.1 reescrito (dos modos) y bump `MU.version`
      a 1.3.0 + `Version:` de `style.css` (cache-bust, D12).

- [X] T049 CSS (C1.1, V4): `.mu-gal-caja--fija .mu-gal-cuerpo` pasa a `flex: 0 0 auto`
      + `overflow: hidden` y `.mu-gal-caja--fija .mu-gal-scroll` a `flex: 1 1 auto` +
      `min-height: 0`: el marco nunca se escapa de la caja y el scroll absorbe lo que
      queda. El comentario de `.mu-gal-scroll` decia que el cuerpo scrolleaba (falso
      desde F2): corregido.
- [X] T050 `aplicarLienzo()` mide el cromo VERTICAL (`cromoVertical()`: cabecera,
      segmentos, padding del cuerpo, **pie natural** con `scroll.scrollHeight`, acciones,
      gaps y bordes) y el alto del lienzo pasa a `min(92vh - cromo, 720)` con suelo
      `ALTO_MINIMO = 120`; `minimoAlto` se acota al alto disponible. Sin medidas, el 4:3
      se acota tambien por el alto (D25). Medido: con el cromo SIN el pie, a 1000x768 el
      scroll quedaba en 0 px (banda y meta invisibles) -> el pie entra en la cuenta.
- [X] T051 Puertas: `visor.browser.js` (`medirResponsive` con `t`/`b` + `cabecera`,
      `meta`, `scroll` y `lista`) afirma a **820x768** y **1000x768** que la caja cabe en
      92vh, que el marco cabe en el cuerpo, que nada se solapa y que la banda conserva
      alto util; `galeria.test.js` afirma el CSS del cuerpo/scroll, el `vh * 0.92` (ya sin
      `vh * 0.62`) y la rama sin medidas con el alto mandando. Docs: `contracts/galeria.md`
      C1.1, `spec.md` D25 y `visor-evaluacion.md` fila 30; bump `MU.version` a 1.4.0 +
      `Version:` de `style.css` (cache-bust, D12).

- [X] T052 Piso de la RAIZ en el ambito 1 (D26, C11): `coleccion()` publica
      `--mu-gal-alto-celda` y `--mu-gal-alto-min` (celda MAS ALTA medida con
      `max(offsetHeight, scrollHeight)` + pie + paddings + filtros) y
      `[data-mu-gal-layout]` pasa de `min-height: 0` a `min-height: var(--mu-gal-alto-min)`;
      `res.altoCelda`/`res.altoMin` para QA. Puertas: 32 checks de mini cuadrada en
      `visor.browser.js` (4 escenarios x 4 viewports) + el bloque de contenedor corto del
      ambito 1 + `recorta` pasa a significar "contenido inalcanzable" (un pie que scrollea
      no es un recorte); 3 checks en `galeria.test.js`.

- [X] T900 F2 — migrar `PMUVisor` (plugin) y la biblioteca del comprador a `MU.coleccion` /
      `MU.visor` (D11: dependencia BLANDA, el plugin no puede exigir el tema).
      **Hecho 2026-11-07** (commits `3d2fedd`, `5133b71`, `ef78c37`, `0d7ddcf`): `visor-pmu.js`
      delega en `MU.visor` (con `pmu-visor--sobre` para pasar por encima del modal del plugin y
      fallback propio), `biblioteca-pmu.js` delega la tira en `MU.coleccion`, y el visor PROPIO del
      cargador (`cpmu-visor`) **retirado** (si falta `PMUVisor` se avisa con causa). Puertas:
      `biblioteca-contrato` (48), `selector-contrato`, `ficha-pedido` **52/52** en el laboratorio.
- [X] T901 F3 — la calculadora de `muyunicos` (repo `calculadora`) como consumidor de la coleccion.
      **Hecho 2026-11-07**: `MiniGallery` monta la TIRA con `MU.coleccion` (preset del tema
      `cotizador`) y abre `MU.visor({tono:'claro'})` con meta de precios y accion "Personalizar"; el
      shortcode del cotizador queda **versionado en el tema** (`inc/cotizador.php`, spec 020 D8) con
      `mu_galeria_encolar()`. Puertas: `npm test` (28) + `typecheck` y `/cotizador/` verificado en el
      sitio (`MU 1.10.0`, tira del motor con 4 celdas, visor con tema). `ImageLightbox`/`TiraIdeas`
      quedan como UI de negocio del cotizador (no son galeria de lista).