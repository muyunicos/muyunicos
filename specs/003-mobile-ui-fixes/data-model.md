# Phase 1 Data Model: Correcciones y mejoras de la interfaz en el móvil

**Feature**: `003-mobile-ui-fixes` | **Fecha**: 2026-10-02 | **Research**: [research.md](./research.md)

## Natureza de este "modelo de datos"

Esta feature **no introduce datos**. No hay tablas nuevas, no hay migraciones, no
hay endpoints, no hay consultas, no hay estado en sesión. Lo que sí introduce son
**máquinas de estado de interfaz**: cada componente pasa de "un estado implícito" a
"estados declarados, observables y verificables".

El modelo que sigue documenta esas máquinas. Es el equivalente de esta feature a un
data model, y se escribe con la misma regla que la constitución exige para el
código: un comportamiento que no se puede observar no se puede verificar, y aquí no
hay suite de pruebas que lo cubra.

Todo el estado vive en el DOM, en atributos y clases. Nada se persiste, nada viaja
al servidor, nada sobrevive a la recarga de la página.

---

## Entidad 1: Estado del selector de país

**Qué representa**: si la lista de países está desplegada o plegada, y por qué
contexto de interacción se controla.

**Atributos**

| Atributo | Valores | Dónde vive | Quién lo escribe |
|---|---|---|---|
| `aria-expanded` del disparador | `"true"` / `"false"` | `.country-selector-trigger` | `js/global-ui.js` |
| Clase de estado del contenedor | presente / ausente | `.country-redirect-container` | `js/global-ui.js` |
| Visibilidad de la lista | visible / oculta | `.country-selector-dropdown` | CSS, a partir de la clase |
| Contexto de puntero | fino / grueso | `matchMedia` | `js/global-ui.js`, al inicializar |

**Transiciones**

```text
                 ┌──────────────────────────────────────┐
                 │                                      │
   [inicial] ──tap o clic──> [abierto] ──tap o clic──> [cerrado]
                 │                                      ▲
                 └── pasa el puntero (solo escritorio) ──┘
                                  │
                                  └── el puntero sale (tras 200 ms) ──> [cerrado]

   [abierto] ──toque fuera / Escape──> [cerrado]
   [cualquiera] ──cambio de tamaño de pantalla──> [cerrado]
```

**Reglas de validación derivadas de los requisitos**

| Regla | Origen | Cómo se verifica |
|---|---|---|
| El estado abierto es observable por clase y por atributo de accesibilidad, nunca por un estilo en línea | FR-003 | Inspeccionar el DOM: la clase está, el atributo dice `"true"`, y no hay atributo `style` controlando la visibilidad |
| La lista se abre con un solo toque, sin gesto sostenido | FR-001, FR-002 | Un toque en un teléfono real muestra la lista |
| El mismo toque no cierra lo que acaba de abrir | FR-002 | Repetir el paso anterior 10 veces seguidas |
| El cierre al tocar fuera sigue funcionando | FR-004 | Tocar el cuerpo de la página |
| El teclado abre y cierra, e informa el estado | FR-005 | Recorrer con `Tab` y activar con `Enter` y `Espacio` |

**Nota sobre el retraso de 200 ms**: es intencional y no es un estado. Es el tiempo
de gracia para que el puntero viaje del disparador a la lista en escritorio
---

## Entidad 2: Estado del submenú de Mi Cuenta

**Qué representa**: si el panel de accesos de la cuenta está abierto.

**Atributos**

| Atributo | Valores | Dónde vive | Quién lo escribe |
|---|---|---|---|
| `.active` en el contenedor | presente / ausente | `.mu-account-dropdown-wrap` | `js/header.js` (ya existe, no se modifica) |
| Contraste del texto | legible / ilegible | derivado de CSS | CSS |
| Área táctil del enlace | ≥ 44 px / menor | derivado de CSS | CSS |

**Transiciones**

```text
   [cerrado] ──toque en el ícono (móvil, con sesión)──> [abierto]
   [abierto] ──toque fuera──> [cerrado]
   [abierto] ──ancho > 768 px──> [cerrado]
   [cerrado] ──toque en el ícono (SIN sesión)──> navega a Mi Cuenta
```

**Reglas de validación**

| Regla | Origen | Cómo se verifica |
|---|---|---|
| El texto es legible sobre su propio fondo | FR-010 | Medidor de contraste sobre el píxel real: ≥ 4.5:1 |
| Comparte tratamiento con los submenús nativos | FR-011 | Comparación visual lado a lado con un submenú nativo en el mismo teléfono |
| Cada enlace tiene área táctil ≥ 44×44 px | FR-012 | Medir el alto del elemento en el inspector |
| Responde al primer toque | FR-013 | Un toque y navega, sin repetir |
| Sin sesión, no hay panel | FR-015 | Entrar sin sesión y tocar el ícono: navega, no aparece panel |
| El escritorio no cambia | FR-017 | Comparar antes/después a 1024 px |

**Nota de estado**: esta entidad **no cambia de comportamiento**. El JavaScript ya
funcionaba. Lo único que cambia es la presentación, que es el defecto reportado.

---

## Entidad 3: Estados del botón de contacto

**Qué representa**: la fase visual del acceso flotante a WhatsApp.

**Atributos**

| Atributo | Valores | Dónde vive | Quién lo escribe |
|---|---|---|---|
| Estado de puntero | reposo / hover / focus / active | CSS | CSS puro |
| Etiqueta visible | visible / oculta | CSS, según media query | CSS puro |
| Preferencia de movimiento | normal / reducido | `@media (prefers-reduced-motion)` | CSS puro |
| Nombre accesible | texto fijo | `aria-label` del enlace | `inc/ui.php` |
| Color de marca | verde de WhatsApp | variable CSS | `style.css` |

**Transiciones**

```text
   [reposo] ──puntero pasa / entra el foco──> [interactivo: etiqueta visible]
   [interactivo] ──puntero sale / sale el foco──> [reposo]
   [reposo o interactivo] ──pulsación──> [pulsado] ──se abre WhatsApp──> [salida]

   Al cargar la página: [reposo] ──una vez──> [pulso de atención] ──> [reposo]
   Con movimiento reducido: el paso del pulso NO ocurre
```

**Reglas de validación**

| Regla | Origen | Cómo se verifica |
|---|---|---|
| Tiene estados distinguibles de reposo, acercamiento y pulsación | FR-019 | Ver los tres estados en un teléfono y en un escritorio |
| Comunica que es un canal de contacto pulsable | FR-018 | La etiqueta dice qué es, sin depender del ícono |
| La animación no bloquea la respuesta de la página | FR-020 | Desplazar la página y pulsar el carrito mientras la animación corre |
| Se anula con movimiento reducido | FR-021 | Activar "reducir movimiento" en el sistema y recargar |
| Tiene nombre accesible | FR-022 | Lector de pantalla: anuncia la función, no el archivo |
| No tapa controles en 320–430 px | FR-023 | Verificar en los cinco anchos de SC-007 |
| Conserva destino, número y mensaje | FR-024 | Pulsar y comprobar la conversación |
| Sin colores literales | FR-025 | Búsqueda de texto sobre el CSS modificado |

**Nota sobre la pulsación**: el destino abre en pestaña nueva. El estado "pulsado"
es momentáneo y no deja rastro: no hay estado persistente que limpiar, lo que evita
toda una clase de errores de estado atascado.
---

## Entidad 4: Presentación de la sección de cuenta

**Qué representa**: la adaptación visual de las pantallas de la sección, y el hecho
de que esa adaptación **solo exista** en esas pantallas.

**Atributos**

| Atributo | Valores | Dónde vive | Quién lo escribe |
|---|---|---|---|
| Hoja de estilos de cuenta | cargada / no cargada | `mu-account` | `functions.php` |
| Ámbito de carga | sección de cuenta / resto del sitio | condicional de contexto | `functions.php` |
| Tipografía fluida | cualquier valor entre los extremos | CSS | `css/account.css` |

**Transiciones**: no aplica. No hay máquina de estados: es una capa de presentación
estática y condicional.

**Reglas de validación**

| Regla | Origen | Cómo se verifica |
|---|---|---|
| Sin desbordes ni desplazamiento horizontal en 320 px | FR-026 | Reducir a 320 px y recorrer la pantalla |
| Campos y botones coherentes con el resto del sitio | FR-027 | Comparar con el checkout y con el carrito |
| La navegación de la sección es alcanzable y legible | FR-028 | En 320 px, recorrer los 6 accesos |
| Los formularios se completan y se guardan | FR-029 | Editar email, teléfono y contraseña, y guardar |
| La pantalla de acceso sin sesión tiene el mismo cuidado | FR-030 | Entrar sin sesión y comparar |
| La hoja no se carga fuera de la sección | FR-031 | Ver el peso de la página de tienda antes y después |

**Nota de verificación**: la presencia del archivo en el HTML de la página de tienda
es la prueba de que la hoja no se está colando (SC-011). La comparación se hace
sobre esa presencia, que no es ambigua, y no solo sobre los bytes totales, porque la
variación de caché de producción puede ser mayor que el peso del archivo.

---

## Reglas transversales

| Regla | Origen | Dónde se verifica |
|---|---|---|
| Ningún componente cambia en escritorio, salvo el botón de contacto | FR-032 | Comparación antes/después a 1024 px |
| No se modifica el tema base ni los complementos | FR-033 | `git diff` sobre el árbol: solo el tema hijo cambia |
| No cambian las URLs de referencia ni las etiquetas de idioma | FR-034 | `curl -sI` sobre las páginas de cuenta en dos subdominios |

**Sobre FR-033**: el repositorio contiene una copia del tema parent en
`Tema-GeneratePress/`. Esa carpeta **no se toca** en esta feature, y `git diff` lo
verifica de forma mecánica: si aparece en el diff, el gate falla.
(`research.md` D-4). En táctil no aplica, porque no hay puntero que viaje.