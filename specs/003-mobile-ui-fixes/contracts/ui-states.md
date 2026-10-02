# Contrato de estados de interfaz: `003-mobile-ui-fixes`

**Feature**: `003-mobile-ui-fixes` | **Fecha**: 2026-10-02 | **Spec**: [spec.md](../spec.md)

## Por qué existe este contrato

Esta feature no expone una API pública, ni endpoints REST, ni una CLI. Su único
"contrato" es el que media entre el JavaScript, el CSS y las tecnologías de
accesibilidad: **qué clase y qué atributo significan qué estado**.

Ese contrato no es trivial. Es exactamente donde está la causa del defecto
reportado: el CSS de la lista de países lee `[style*="display: block"]`, un
acoplamiento frágil entre dos archivos que nadie contrató. Este documento fija el
acoplamiento correcto para que no vuelva a romperse.

**Regla que establece este contrato**: un estado se comunica con una **clase** en el
contenedor, nunca escribiendo un estilo en línea. Quien rompe esta regla rompe el
contrato.

---

## Contrato 1: Selector de país

### Vocabulario

| Término | Significado |
|---|---|
| Disparador | El elemento que abre la lista: la bandera |
| Contenedor | El elemento que lleva el estado: `.country-redirect-container` |
| Lista | El elemento que se muestra y se oculta: `.country-selector-dropdown` |

### Reglas

**C-1.1** El estado del selector vive en el **contenedor**, no en la lista. La lista
se muestra y se oculta por consecuencia de la clase del contenedor, nunca por un
estilo propio.

**C-1.2** La clase de estado abierto es `is-open` y se aplica **en el contenedor**.
Es la única clase de estado. No hay un segundo nombre para el mismo estado.

**C-1.3** El disparador mantiene su `aria-expanded` sincronizado con la clase.
Cuando el contenedor tiene `is-open`, el disparador tiene `aria-expanded="true"`.
Cuando no la tiene, `"false"`. La sincronización es responsabilidad del JavaScript y
su fallo es detectable: si la clase y el atributo discrepan, hay un error.

**C-1.4** La visibilidad de la lista **nunca** se controla como criterio de
apariencia mediante un estilo en línea. La clase del contenedor es la fuente de
verdad.

> Nota de implementación: la versión actual escribe `style.display` en la lista
> porque el selector de CSS lo busca por texto. Con C-1.2 el CSS pasa a leer
> `.is-open`, y esa escritura deja de ser necesaria. Si `tasks.md` decide conservarla
> como refuerzo, debe anotarse como tal, porque es exactamente el acoplamiento que
> este contrato elimina.

**C-1.5** El disparador es operable con teclado: `Enter` y `Espacio` alternan el
estado, y `Escape` lo cierra. El foco permanece en el disparador tras cerrar con
`Escape`.

**C-1.6** El disparador expone `aria-haspopup="true"` y `role="button"`, y el
atributo `tabindex="0"`. **Esto ya existe** en `inc/geo.php` y no se modifica: es
la parte del contrato que ya está bien.

**C-1.7** La lista es un `ul` con `aria-label`, y cada opción es un `li` que
contiene un `a`. La estructura no cambia. Solo cambia la que se abre.

### Estados

| Estado | Contenedor | Disparador | Lista |
|---|---|---|---|
| Cerrado | sin `is-open` | `aria-expanded="false"` | oculta |
| Abierto | con `is-open` | `aria-expanded="true"` | visible |

### Transiciones permitidas

| Desde | Evento | Hacia | Nota |
|---|---|---|---|
| Cerrado | toque o clic (táctil) | Abierto | Un solo toque, sin gesto sostenido |
| Abierto | toque o clic (táctil) | Cerrado | Alterna |
| Cerrado | el puntero entra (escritorio) | Abierto | Solo con puntero fino |
| Abierto | el puntero sale (escritorio) | Cerrado | Tras 200 ms de gracia |
| Abierto | toque fuera | Cerrado | |
| Abierto | `Escape` | Cerrado | El foco vuelve al disparador |
---

## Contrato 2: Submenú de Mi Cuenta

### Vocabulario

| Término | Significado |
|---|---|
| Disparador | El ícono de Mi Cuenta |
| Contenedor | `.mu-account-dropdown-wrap` |
| Panel | `.mu-sub-menu` |

### Reglas

**C-2.1** El estado del submenú vive en la clase `active` del **contenedor**. Este
contrato **no cambia** el nombre de la clase: `js/header.js` ya la usa y no se
modifica. Cambiarla obligaría a tocar el JavaScript para nada.

**C-2.2** El panel se muestra y se oculta por la clase `active` del contenedor.
Ningún estilo en línea controla su visibilidad.

**C-2.3** El panel existe **solo** cuando hay sesión iniciada. Sin sesión, el
disparador navega a Mi Cuenta y no hay panel. Esta regla la cumple el marcado en el
servidor (`inc/ui.php`), no CSS ni JavaScript.

**C-2.4** El panel de móvil comparte tratamiento visual con los submenús nativos de
móvil: mismo velo translúcido, mismo radio, misma sombra, mismas medidas táctiles. La
diferencia con el escritorio (fondo azul sólido) es **intencional** y es lo que hace
que el arreglo no afecte a escritorio.

### Estados

| Estado | Contenedor | Panel | Cuándo |
|---|---|---|---|
| Cerrado | sin `active` | oculto | Por defecto |
| Abierto | con `active` | visible | Tras tocar el ícono en móvil |
| Inexistente | — | no existe | Sin sesión iniciada |

---

## Contrato 3: Botón de contacto

### Vocabulario

| Término | Significado |
|---|---|
| Control | El enlace `.boton-whatsapp` |
| Etiqueta | El texto que acompaña al ícono |
| Zona segura | El área que el sistema reserva para barras del navegador |

### Reglas

**C-3.1** El control **no tiene estado en JavaScript**. Todos sus estados son
estados CSS. No es una preferencia estética: garantiza que el control se ve igual en
cualquier dispositivo, incluidos los que no generan eventos de puntero.

**C-3.2** El control tiene un nombre accesible, en el elemento `a`, mediante
`aria-label`. La imagen conserva su `alt`. El nombre accesible describe la
**función** ("Escribir por WhatsApp"), no el archivo ni el ícono.

**C-3.3** La etiqueta es siempre visible en el breakpoint móvil. En escritorio se
revela al enfocar o al pasar el puntero.

**C-3.4** La animación se define en CSS con `transform` y `opacity`. **Prohibido**
`style.transform` en línea. **Prohibida** cualquier propiedad que provoque `layout`
o `paint` en cada frame (`width`, `height`, `top`, `margin`, `box-shadow`).

**C-3.5** La animación se anula por completo bajo
`@media (prefers-reduced-motion: reduce)`. Bajo esa preferencia, el control conserva
sus estados de reposo, cercanía y pulsación, y no ejecuta el pulso de atención.

**C-3.6** El pulso de atención ocurre **una vez** al cargar la página. No es
repetitivo. Un latido infinito en una esquina de la pantalla es un anti-patrón de
accesibilidad.

**C-3.7** La posición vertical del control combina el margen actual con la zona
segura del dispositivo, de modo que queda por encima de la barra de direcciones en
iPhone y por encima del indicador de inicio.

**C-3.8** Los colores del control provienen de variables CSS. Las dos variables
nuevas (el verde de WhatsApp y su acento) están declaradas en `style.css` con un
comentario que explica que no son colores de marca del sitio, y quedan registradas
como excepción en el §8 del guide.

### Estados

| Estado | Dispara | Efecto visual |
|---|---|---|
| Reposo | — | Estado base |
| Cercano | `:hover` o `:focus-visible` | Etiqueta visible (en escritorio) |
| Pulsado | `:active` | Confirmación de la pulsación |
| Movimiento reducido | preferencia del sistema | Sin pulso; los otros tres intactos |
---

## Contrato 4: Sección de cuenta

### Reglas

**C-4.1** La hoja de estilos de la sección se carga **únicamente** en páginas de la
sección de cuenta. En ningún otro lugar del sitio aparece su nombre en el HTML.

**C-4.2** La hoja no reescribe plantillas. Todo lo que hace es adaptar el marcado que
WooCommerce ya produce, y no añade ni quita contenido.

**C-4.3** La hoja no introduce selectores que dependan del orden de los endpoints de
la sección: los estilos valen por igual en Mi Cuenta, en Detalles, en Descargas, en
Órdenes y en Salidas.

**C-4.4** La hoja usa únicamente las variables declaradas en `:root`. Ningún color
literal, ninguna medida inventada. La tipografía fluida usa `clamp()`.

**C-4.5** Ningún elemento interactivo de la sección se deja sin estado de foco
visible.

---

## Compatibilidad hacia atrás

Este contrato introduce **un solo** nombre nuevo: la clase `is-open` del selector de
país. Todo lo demás ya existía:

| Nombre | Estado | Nota |
|---|---|---|
| `active` en `.mu-account-dropdown-wrap` | **Ya existe** | No se renombra |
| `aria-expanded` en el disparador | **Ya existe** | Se mantiene y se sincroniza |
| `role="button"`, `tabindex`, `aria-haspopup` | **Ya existen** | No se tocan |
| `is-open` en `.country-redirect-container` | **Nuevo** | Único nombre de clase nuevo |
| `aria-label` en `.boton-whatsapp` | **Nuevo** | Único atributo nuevo |

**Consecuencia**: el único cambio de contrato observable hacia afuera es la clase
`is-open` y el `aria-label`. Un fragmento de CSS existente que apunte a
`.country-selector-dropdown[style*="display: block"]` se desactualiza, y esa es
exactamente la intención: el contrato viejo era frágil y se reemplaza.
| Cualquiera | cambio de tamaño de pantalla | Cerrado | Para no dejar un panel a medio camino |