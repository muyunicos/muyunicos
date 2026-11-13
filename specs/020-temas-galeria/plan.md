# Plan — Spec 020

> **Estado (2026-11-07)**: **F5a cerrada** (motor, temas CSS, `.mu-gal-pie` y filtros del visor;
  `VISOR 152/152` + `GALERIA 240`, `MU 1.10.0`). **F5b cerrada** (cotizador versionado en el
  tema + calculadora nativa; `/cotizador/` verificado en produccion). **F6 en curso**: retirados
  la v1 del tema y `cpmu-visor`; falta `docs/errores.md` del plugin (T015 parcial).

## Por que existe

La 019 dejo el MOTOR unico pero un solo ASPECTO. Este plan agrega **temas (CSS)**, **filtros
del visor**, dos mejoras de motor (`.mu-gal-pie` y la auditoria del CSS prestado) y termina de
sacar el motor de los consumidores: la calculadora se rehace nativa y el cotizador se
**versiona** en el tema (hoy vive solo en el servidor).

## Orden de ejecucion

| Fase | Alcance | Puertas |
|---|---|---|
| **F5a** ✅ | motor: `data-mu-tema`, `galeria-temas.css`, `.mu-gal-pie`, auditoria CSS, filtros del visor | `galeria.test.js`, `visor.browser.js`, `ficha-pedido` 52/52 |
| **F5b** | cotizador versionado + instalacion en el lab + calculadora nativa | `php -l`, `100`/`200` del lab, `cotizador-pedido.browser.js`, `npm test`/`typecheck` |
| **F6** | retiro de la v1 del tema y de `cpmu-visor` | todas las suites + recorridos |

## F5a — detalle tecnico

1. **Resolucion del tema** (`js/galeria.js`):
   - helper `temaDe(el, opts)`: `opts.tema` → `el.closest('[data-mu-tema]')` → `base`.
   - en `coleccion()`: tras `nodo.setAttribute('data-mu-gal-layout', …)`, publicar
     `data-mu-tema` si el consumidor no lo puso.
   - en `visor()`: al crear el overlay, publicar `data-mu-tema` resuelto desde
     `document.activeElement` (el boton de la miniatura) o `opts.tema`.
2. **CSS** (`css/components/galeria-temas.css`): bloques por atributo. Sin JS adicional.
   `base` = vacio. `cotizador` = T7 del contrato.
3. **`.mu-gal-pie`** (`montarAcciones`/`montarMeta`): el JS crea el contenedor y mueve dentro
   `.mu-gal-meta-caja` y `.mu-gal-acciones`. `base` recupera el aspecto por CSS
   (`flex-direction: column`), por lo que el DOM cambia pero el render no.
4. **Filtros**: `visor()` acepta `filtros`/`filtroActivo`; el estado vive en `estado.filtro`
   (por campo). `pintarVisor()` trabaja sobre `itemsFiltrados()`; `acotar()` se aplica al
   indice de la lista filtrada; los pills se montan como segmentos con `aria-pressed`.
5. **Auditoria**: `.mu-gal-cerrar` con estilo propio (color/tamano/hover/foco) y sin la clase
   `.mu-modal-close`; `.mu-gal-acciones .mu-gal-accion` (0,2,0).

## F5b — detalle tecnico

1. `inc/cotizador.php`: shortcode versionado (mismo contrato con el bundle: `#mu-sticker-calculator-root`,
   `WP_STICKER_DATA`), `mu_galeria_encolar()` y `data-mu-tema="cotizador"`.
2. Assets del cotizador al tema; `datos_config.json` fuera de git.
3. Laboratorio: plugin del carrito + pagina `cotizador` → `PMU_COTIZADOR_URL`.
4. Calculadora: `MiniGallery` monta `MU.coleccion` en un `div` con `data-mu-tema` y abre
   `MU.visor({tono:'claro', filtros:'estado'})`; `ImageLightbox`/`TiraIdeas` dejan de ser
   galeria.

## Riesgos y mitigaciones

- **Cambio de DOM (`.mu-gal-pie`)**: afecta a todos los consumidores. Mitigacion: el tema
  `base` reproduce el aspecto exacto y la puerta `ficha-pedido` (52/52) es la red.
- **Filtros acotan el indice**: puede quedar una posicion invalida. Mitigacion: `acotar()` y
  prueba en el demo con el ultimo item seleccionado + filtro.
- **El cotizador en el servidor no esta versionado**: al versionarlo hay que comparar con el
  del servidor (el que el usuario paso) para no perder nada. Mitigacion: T009 usa el shortcode
  textual del usuario y solo agrega encolado/deps/atributo.
- **Datos del cotizador**: `datos_config.json` es del admin. Mitigacion: fuera de git y
  semilla solo para el lab.
- **Artefactos en DOS repos (2026-11-08)**: el bundle, el CSS y los dos PHP viven en
  `calculadora` (fuente) y en el tema (desplegable). Mitigacion: `npm run pub:tema` los
  publica con SHA256 verificado y `node tests/cotizador-assets.js` falla si difieren.
  **Nunca** se copian al servidor por FTP: deja archivos *untracked* y el `git pull` del
  deploy ABORTA (paso el 2026-11-08: el tema no se actualizo y la calculadora desaparecio).
- **Bundle de la calculadora con trabajo ajeno sin commitear**: el repo `calculadora` tiene la
  spec 037 en curso; el build arrastra eso. Mitigacion: commitear por separado (T012) o
  regenerar despues de ese commit.

## Definition of done

- SC-001..SC-007 del `spec.md` verdes.
- `galeria.test.js`, `visor.browser.js`, `ficha-pedido.browser.js` (52/52),
  `cotizador-pedido.browser.js` (con URL) y `npm test`/`typecheck` de la calculadora.
- Docs de F5b/T008/T013-T015 cerrados, `MU.version` y `Version:` de `style.css` bumpeados.