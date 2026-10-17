# 014 — Densidad del panel de banners

**Estado**: tramos 1, 2, 4 y 7 implementados (2026-10-17); 3, 5, 6 y 8 pendientes.
**Rama**: `refactor/panel-densidad` · **Riesgo**: medio (sólo panel; el front no se toca).

## Problema

Con el editor real en uso, una fila con 11 países satura:

| Bloque | Antes | Después |
|---|---|---|
| Tabla de vigencia | 11 filas × 6 columnas = **96 controles**, casi todas mostrando la **misma** ventana heredada | **Global (3 controles)** + sólo los países que difieren; el resto se resume en una línea (*«la ventana general la usan 11 de 12 países»*) y la tabla completa sigue detrás de **«ver todos los países»** |
| Chips de país | 10, tildados o no | sólo los **tildados** + selector **`+N`** |
| Fila al abrir el panel | ~600 px desplegada | ~60 px **plegada** (título · estado · países · acciones) |
| Acciones | 4 botones sueltos | Duplicar + menú **⋯** (Subir · Bajar · Eliminar) |
| Estado «no se ve» | *No se ve en ningún país* (sonaba a fecha vencida) | **un caso propio**: *«No está tildado en ningún país: no se verá en el sitio»*, en rojo |

## Cambios

| # | Cambio | Estado |
|---|---|---|
| **1** | **Vigencia por excepción**: `mu_hero_banner_excepciones()` (espejo del saneado: `fechas[PAIS]` existe sólo si difiere) + `mu_hero_banner_pais_es_excepcion()`. La tabla muestra Global + excepciones; `+ excepción por país` promueve la fila de un país y lleva el foco a Inicio; `×` la devuelve a la herencia; `ver todos los países` muestra la tabla completa. Los países que heredan siguen en el HTML (`is-hereda`, ocultos): **el POST no cambia**. | ✅ |
| **2** | **Fila plegada por defecto** cuando hay más de un banner (`render_row( $index, $b, $colapsada )`). Se mantiene el botón de plegado (y no `<details>`) porque el resumen es interactivo: chips, menú y botones dentro de un `<summary>` son un footgun. | ✅ |
| **3** | **Contenido agrupado y en el orden del banner**: grupos **Texto** (eyebrow → título → descripción), **Botones** (CTA 1, CTA 2, badge) e **Imagen**, con el lienzo en columna pegajosa. | ⬜ |
| **4** | **Chips de sólo los tildados** + selector `+N` con los libres. El JS **mueve el mismo nodo** (label + checkbox) entre el resumen y el selector: cada país aparece una sola vez. | ✅ |
| **5** | El **badge en un solo lugar** (la casilla «mostrar» junto a sus campos) y «Identidad» a **Avanzado** plegado. | ⬜ |
| **6** | **Lienzo pegajoso** en dos columnas (contenido a la izquierda) y hint en una línea. | ⬜ |
| **7** | **Estado «sin países»**: `mu_hero_banner_estado()` distingue los 4 casos (`is-activo`, `is-programado`, `is-inactivo`, `is-sin-paises`). | ✅ |
| **8** | Huso del sitio legible (`-03:00` → «huso del sitio») en la fila Global. | ⬜ |

### Decisiones

- **La tabla completa no se elimina**: queda detrás de «ver todos los países». Nada se pierde y quien quiera comparar los 11 ámbitos lo hace con un clic.
- **Cero cambios de datos y de contrato del POST**: mismos nombres de campo, mismos checkboxes, mismo saneado. Los países que heredan siguen renderizados (ocultos) justamente por eso.
- **Plegado por defecto sólo con 2+ banners**: con uno solo, mostrarlo entero no molesta y evita un clic.
- El estado «sin países» es **un dato, no una fecha**: se muestra en rojo y con el motivo completo, porque es la diferencia entre «el banner no se ve porque la promo terminó» y «no se ve porque nunca se activó ningún país».

### Criterios de aceptación (tramos implementados)

1. `mu_hero_banner_excepciones()` devuelve sólo países válidos con dato propio, en orden canónico; una entrada vacía o un país inexistente no cuentan (arnés 013, sección 7).
2. Sin países tildados el estado es `is-sin-paises` con su texto; con un país tildado y ventana vencida sigue siendo `is-inactivo` (arnés 013, sección 7).
3. La tabla muestra **Global siempre** (nunca se oculta) y cada país con `data-es-excepcion="1|0"` (arnés 010 y 013).
4. Los chips tildados y los libres suman el total de países del sitio, y `+N` los cuenta (arnés 013, sección 8).
5. Con más de un banner las filas nacen plegadas; con uno, desplegada (verificación manual).

### Verificación

- **Automática**: arnés 013 PHP **110 OK** y JS **17 OK**; no-regresión 010 (**99** + 28), 012 (87 + 18), 011 (18), 008 (30 + 17), 006 (54). `php -l` y `node --check` limpios. Dos aserciones de 010 se migraron (una regex de clase y una nueva para «Global nunca se oculta»).
- **Manual** (Fase B del quickstart): fila plegada/desplegada, chips que se mueven al selector, excepción creada y devuelta a la herencia, «ver todos», menú ⋯, estado rojo de «sin países».
