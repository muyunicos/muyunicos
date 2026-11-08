# Tareas — Spec 020 (temas, filtros del visor, cotizador versionado)

Estados: `[ ]` pendiente, `[X]` hecho, `[~]` hecho parcial (se aclara).

## F5a — Motor (tema `muyunicos`)

- [X] T001 **`data-mu-tema` en el motor**: resolver con `closest('[data-mu-tema]')` en
      `MU.coleccion` (no pisa un atributo propio), publicarlo en la raiz de la coleccion y en
      el overlay del visor; `opts.tema` como atajo. Contrato: `contracts/temas.md` T1-T3.
- [X] T002 **`css/components/galeria-temas.css`** (nuevo) encolado desde `inc/galeria.php`
      con la `$ver` del tema y dependencia `mu-galeria`. Bloque `cotizador`: precios en 2
      columnas con jerarquia, `% OFF` junto al valor, zoom en hover (respeta
      `prefers-reduced-motion`), etiqueta con velo, precio compacto. Contrato: T7.
- [X] T003 **`.mu-gal-pie`**: variante **B** aprobada (2026-11-07): el motor NO mueve
      nodos; el tema `cotizador` saca las acciones del flujo con `position:absolute` y el
      cromo ya no las cuenta (`csEl.position === 'absolute'`). `base` queda intacto.
      `base` los apila (aspecto de hoy) y `cotizador` los pone en fila. Contrato: T6.
- [X] T004 **Auditoria del CSS prestado**: el visor deja de usar `.mu-modal-close`
      (`mu-gal-cerrar` autosuficiente) y `.mu-gal-acciones .mu-gal-accion` gana a
      `.button`/`.button-primary`. Contrato: T5.
- [X] T005 **Filtros del visor**: `filtros`/`filtroActivo`/`onFiltrar` reusando
      `MU.puro.valoresFiltro/filtrar`; pills en `.mu-gal-segs`; acota indice, contador, banda
      y precarga. Contrato: `contracts/visor-filtros.md`.
- [X] T006 **Demo**: escenario `#visor-filtros` (3 estados) + `#tema-cotizador` y controles
      del playground para el atributo del tema.
- [X] T007 **Puertas**: `galeria.test.js` (atributo, un bloque por tema, `.mu-gal-pie`,
      `.mu-gal-cerrar` sin `.mu-modal-close`, especificidad de acciones, filtros) y
      `visor.browser.js` (2 columnas, badge junto al valor, acciones en la fila, zoom/velo,
      filtros que recortan). `base` intacto.
- [ ] T008 **Docs**: `contracts/coleccion.md` (C12 temas + C4 con `filtros` tambien en el
      visor), `contracts/galeria.md` (C1.3 + puntero a la enmienda de C1.2),
      `visor-evaluacion.md` fila 32 + V6, `MU.version` 1.6.0 y `Version:` de `style.css`.

## F5b — Cotizador versionado y calculadora nativa

- [X] T009 **`inc/cotizador.php`** en el tema: `mu_sticker_calculator_shortcode()` con el
      mismo id/URLs/`filemtime`, `mu_galeria_encolar()` adentro, `data-mu-tema="cotizador"` y
      `deps: ['mu-galeria']` en el script; `require_once` en `functions.php`. Contrato: spec
      D8.
- [X] T010 **Assets al tema**: `assets/js/calculadora_stickers.js`,
      `assets/css/calculadora_stickers.css`, `assets/datos_config_etag.php`,
      `assets/guardar_datos.php`. `datos_config.json` fuera de git (semilla para el lab).
- [X] T011 **Laboratorio**: plugin del carrito instalado/activo, assets por junction y pagina
      `cotizador` publicada; verificar `window.MU`, el widget montado y
      `cotizador-pedido.browser.js` con `PMU_COTIZADOR_URL`. Retirar el
      `mu_galeria_encolar()` que se agrego al plugin del carrito (queda en el shortcode).
- [X] T012 **Calculadora nativa**: la tira de `MiniGallery` se monta con `MU.coleccion`
      (`preset:'producto'` + precio compacto del tema) y el visor es
      `MU.visor({tono:'claro'})` con la accion "Personalizar"; sin motor queda la tira
      propia (blanda). `TiraIdeas`/`ImageLightbox` quedan como UI de negocio del cotizador
      (no son galeria de lista). Verificado en `/cotizador/` del laboratorio: 4 celdas del
      motor, meta en grilla, 2 precios, badge junto al valor y acciones alineadas.

## F6 — Retiro del rastro viejo (despues de F5 y del deploy)

- [X] T013 **Tema**: borrar la v1. **Hecho (2026-11-08)**: `MU.tarjeta` y `MU.tira` retirados
      con TODO su codigo (`pintarTira`, `observarTira`, `construirMini`, `construirRanura`,
      `modoEfectivo`, `cabenEnLinea`, mapa `tiras`, constantes `ANCHO_*`/`HUECO_*`), su CSS
      (`.mu-gal-tira*`, `.mu-gal-mini*`, `.mu-gal-mas*`, `.mu-gal-cuenta` + media queries +
      reduced-motion + neutralizacion) y sus 20 checks de "Tira v1.1". **`desdeWoo` y
      `desdeEnlaces` se QUEDAN**: los usa el declarativo (`itemsDe`) y `navegarWoo`. El
      contrato `galeria.md` marca C2 como retirado y actualiza la lista de API.
      (`.mu-gal-tira*`, `.mu-gal-tarjeta*`, `.mu-gal-mini*`) y los checks de la v1; actualizar
      `coleccion.md` C0 y el demo.
- [ ] T014 **Plugin**: retirar `cpmu-visor` (fallback del cargador, inalcanzable desde la
      spec 030 D28) y el puente `pmu-visor-peligro` si ya nadie lo pasa; anotarlo en
      `specs/030-biblioteca-imagenes/tasks.md`.
- [ ] T015 **Barrido final**: `docs/errores.md`, `docs/inventario-repo.md`, `AGENTS.md` de
      ambos repos y `specs/INDICE.md` del plugin.
- [X] T016 **Publicacion de artefactos (2026-11-08)**: `npm run pub:tema` en el repo
      `calculadora` (`scripts/publicar-tema.ps1`) copia los 4 artefactos al repo del tema
      con SHA256 verificado y exige que el bundle no sea mas viejo que `src/`; la puerta
      `node tests/cotizador-assets.js` compara los hashes tema↔calculadora (pendiente si no
      hay repo al lado) y verifica `inc/cotizador.php`. **Nunca** se copian assets al
      servidor por FTP/panel: deja archivos untracked y el `pull` del deploy aborta.