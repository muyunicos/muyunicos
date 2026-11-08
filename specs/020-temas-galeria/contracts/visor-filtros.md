# Contract — Filtros del visor (`filtros`)

Spec **020**, decision **D6**. El visor puede **filtrar** sus items con el MISMO criterio que
la coleccion (contrato `coleccion.md`, C10): no se inventa un motor de filtros nuevo.

## F1 — API

```js
MU.visor({
  items: [{ url, thumb, etiqueta, estado, meta }],   // `estado`/`etiqueta` opcionales
  filtros: 'estado' | 'etiqueta' | ['estado','etiqueta'],   // ausente = sin pills
  filtroActivo: 'todos',        // valor inicial; se sanea contra los datos
  onFiltrar: function (campo, valor, res) {}               // opcional
});
```

- `filtros` ausente o `null` = **comportamiento de hoy** (sin pills).
- Los valores se calculan con `MU.puro.valoresFiltro(items, campo)` y el recorte con
  `MU.puro.filtrar(items, campo, valor)` (puros ya testeados).
- "todos" siempre existe y es el valor por defecto.

## F2 — Comportamiento

1. Los pills van **arriba**, en `.mu-gal-segs`, con `aria-pressed` (mismo lenguaje visual que
   los segmentos).
2. Al filtrar: se recalcula la lista visible, se **acota** el indice (si la posicion actual
   desaparece, cae al primero), se repinta imagen/contador/banda y se precargan los vecinos.
3. El **contador** cuenta solo los items filtrados (`2 / 5`), y la **banda** muestra solo los
   filtrados; la celda activa sigue a `MU.ir/actualizar`.
4. Si un filtro deja **cero** items, se muestra el estado vacio del marco (mismo mensaje de
   error de imagen) y el contador queda oculto; "TODOS" siempre vuelve.
5. Filtrar **no** muta `items` del consumidor (se trabaja sobre una copia filtrada).

## F3 — Relacion con `segmentos` (enmienda a `galeria.md` C1.2)

- `segmentos` sigue siendo **informativo**: solo avisa (`onElegir`) y NO recorta. C1.2 se
  mantiene tal cual.
- `filtros` es la clave que **recorta**. Un consumidor puede usar ambas: segmentos para
  "En este campo / Mis imagenes" y filtros para "TODOS / Comprados / Guardados".

## F4 — Consumidores

| Consumidor | Uso |
|---|---|
| biblioteca del plugin (`biblioteca-pmu.js`) | `filtros:'estado'` con `guardada`/`comprada` → TODOS / Comprados / Guardados |
| calculadora (`/cotizador/`) | mismo caso para ideas guardadas y del historial |
| demo (`tests/galeria-demo.html`) | escenario `#visor-filtros` con 3 estados |

## F5 — Puertas

- `tests/galeria.test.js`: los puros (`valoresFiltro`/`filtrar`) siguen cubiertos; se agrega
  el contrato de que el visor usa esas funciones y no una copia.
- `tests/visor.browser.js`: escenario `#visor-filtros` → cada pill recorta, el contador
  cambia, "TODOS" restaura y no se dispara ninguna peticion.
- `tests/ficha-pedido.browser.js` (plugin): la biblioteca sigue funcionando con y sin
  `filtros` (52/52).