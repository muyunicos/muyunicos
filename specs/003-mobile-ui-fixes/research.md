# Phase 0 Research: Correcciones y mejoras de la interfaz en el móvil

**Feature**: `003-mobile-ui-fixes` | **Fecha**: 2026-10-02 | **Plan**: [plan.md](./plan.md)

Todas las decisiones de esta feature salen de **tres fuentes**: la lectura del
código del tema, una verificación con `curl` contra producción y las reglas de la
constitución. No hay documentación externa que consultar: el fallo reportado es
local y su causa está en el repositorio.

Método: para cada componente se siguió la misma secuencia — leer el código,
identificar la causa concreta, buscar el patrón que **ya funciona** en el mismo
sitio, y decidir. Donde el sitio ya tiene un componente equivalente y corregido,
ese componente es la referencia.

---

## 1. El selector de país no abre al tocar

### Lo que dice el código

`js/global-ui.js`, función `initCountrySelector()`, líneas 13-115. El mismo
disparador tiene **cinco** escuchas:

```text
línea 58   trigger  mouseenter  → abre
línea 62   trigger  mouseleave  → agenda cierre
línea 67   dropdown mouseenter  → cancela el cierre
línea 74   dropdown mouseleave  → agenda cierre
línea 79   trigger  click       → alterna abierto/cerrado
```

### Causa raíz

En un dispositivo táctil, un toque **no** dispara solo `click`: los navegadores
móviles emulan un `mouseenter` en el primer toque sobre un elemento. Un toque
produce esta secuencia:

```text
1. touchstart / touchend
2. mouseenter   → openDropdown()  → display: block   (abre)
3. click        → lee isVisible   → true             (cierra)
```

El mismo toque que abre el menú lo cierra. Para el usuario el control parece
muerto. En escritorio con ratón el problema **no** aparece, porque un ratón real
genera `mouseenter` al entrar y `click` solo al pulsar: son eventos distintos en el
tiempo. Eso explica por qué el reporte dice "cuando **toco**" y no "no funciona".

### Problema secundario: el estado visible depende de un atributo en línea

`css/components/header.css` línea 346:

```css
.country-selector-dropdown[style*="display: block"] {
    opacity: 1;
    transform: translateY(0);
    pointer-events: auto;
}
```

El JavaScript controla la visibilidad escribiendo `element.style.display` y el CSS
la detecta **buscando texto dentro del atributo**. Es un acoplamiento frágil: si
alguien cambia `display: block` por `display: flex`, usa `hidden`, o quita el
estilo en línea, el menú se abre pero sigue invisible. La spec lo captura en
FR-003.

### Problema terciario: posicionamiento absoluto que compite con el menú

`css/components/header.css` líneas 284-289 y 545-548:

```css
.mu-header-country-item {
    position: absolute;
    left: 30px;   /* 20px en móvil */
    top: 30px;    /* 20px en móvil */
    z-index: 1000;
}
```

El contenedor del selector está **fuera** del `<header>`. Verificado con `curl`
contra `https://ec.muyunicos.com/`: el marcado sale antes de
`<header class="site-header">`, directamente después del enlace de salto. Un
posicionamiento absoluto sin contenedor relativo padre se ancla al documento
entero, y su `left/top` fijo no responde a la geometría de la barra.

Orden real del documento confirmado:

```text
skip-link → mu-header-country-item → header.site-header → nav.main-navigation
```

El `menu-toggle` que GeneratePress inserta dentro del `nav` y la bandera son dos
elementos de la misma franja con `z-index` distintos. Eso es exactamente el caso de
borde "apilado en el encabezado" de la spec.

### Decisiones

**D-1. Separar la interacción de escritorio de la de táctil.**
Se detectan dos contextos y se registran escuchas distintas para cada uno:

| Contexto | Detección | Escuchas | Comportamiento |
|---|---|---|---|
| Puntero fino (escritorio) | `matchMedia('(hover: hover) and (pointer: fine)')` | `mouseenter` / `mouseleave` | Abre al pasar, cierra al salir, como hoy |
| Sin puntero fino (móvil) | el mismo `matchMedia`, resultado inverso | `click` | Alterna abierto/cerrado, sin apertura emulada |

**Por qué esta forma y no otra.** Se evaluaron tres alternativas:

| Alternativa | Por qué se descarta |
|---|---|
| Agregar `touchstart` con `preventDefault()` para anular la emulación de `mouseenter` | `preventDefault` sobre `touchstart` cancela también el zoom por doble toque. Rompería el zoom del usuario, que es un problema de accesibilidad mayor que el que resuelve |
| Detectar el tipo de puntero **dentro** del manejador de `click` y salir temprano | No funciona: el problema es que el `mouseenter` emulado ya abrió el menú. Un guarda dentro del `click` llega tarde, cuando el estado ya cambió |
| Registrar siempre ambos y usar una bandera de tiempo para descartar la apertura emulada | Funciona, pero depende de una heurística temporal. Si el sistema está lento y los dos eventos llegan con diferencia perceptible, la bandera deja de ser fiable |

`matchMedia` no es heurística: el navegador responde con la verdad del dispositivo.
Y hay un segundo beneficio que la spec exige en el caso de borde "navegación sin
eventos de puntero fino": con esta detección, un dispositivo que nunca emite
`mouseenter` **no registra esa escucha**, así que no puede depender de un evento que
nunca llega.

**D-2. La visibilidad pasa de atributo en línea a clase.**
El JavaScript alterna una clase en el contenedor y el CSS aplica el estado a partir
de esa clase. Se cumple FR-003 y desaparece el acoplamiento frágil. La clase es
además un punto de enganche para que `tasks.md` verifique el estado sin inspeccionar
estilos en línea.

**D-3. La bandera sale del posicionamiento absoluto.**
`.mu-header-country-item` deja de posicionarse contra el documento y pasa a ocupar
su lugar en el flujo del encabezado. Se evaluó mantener el absoluto y solo subir el
`z-index`, que es el cambio más pequeño, y se descartó: el problema no es de orden
de dibujo sino de que el control vive fuera de la barra que lo contiene y por eso
no responde a su geometría. Un `z-index` más alto lo haría visible **encima** del
menú, que es el otro ítem de FR-008.

**D-4. El hover de escritorio conserva el retraso de cierre de 200 ms.**
Ese retraso existe para que el cursor llegue del disparador a la lista sin que se
cierre en el camino. Es comportamiento correcto y no se toca. Lo que se elimina es
únicamente el enganche de `mouseenter` en táctil.

**D-5. La bandera mantiene su lugar actual.**
La spec no pide moverla, y este plan confirma la decisión que el checklist dejó
abierta: se corrige **cómo responde**, no **dónde está**. Moverla sería un rediseño
del encabezado, que la spec saca de alcance explícitamente.

### Lo que NO cambia

`inc/geo.php` no se toca. La construcción de los destinos —subdominio, prefijo de
idioma, URI limpia— se queda como está, y con ella se cumplen FR-006 y FR-007 sin
riesgar la lógica multi-país (principio V). El texto de la lista y el `alt` de la
bandera tampoco cambian (FR-009).

---

## 2. El submenú de Mi Cuenta es blanco sobre blanco

### Lo que dice el código

La causa está confirmada literalmente y son dos reglas que se contradicen:

```css
/* header.css línea 236 — SIN media query, aplica a todos los anchos */
.main-navigation .main-nav ul li.menu-item-has-children .sub-menu li a,
.mu-sub-menu li a {
    color: var(--blanco);          /* texto blanco */
}

/* header.css línea 523 — DENTRO de @media (max-width: 768px) */
.mu-sub-menu {
    background-color: var(--blanco);  /* fondo blanco */
}
```

En escritorio el submenú tiene fondo `var(--primario)` (línea 195, dentro de
`@media (min-width: 769px)`), así que el texto blanco se lee bien. En móvil el
fondo pasa a blanco y el texto **sigue siendo blanco**: ratio 1:1.

### El patrón correcto ya existe en el mismo archivo

Los submenús **nativos** del menú principal ya fueron corregidos para móvil, y la
corrección está documentada en un comentario de 18 líneas en el propio archivo
(`header.css` líneas 425-446). Aplican:

```css
background-color: rgba(255, 255, 255, 0.12);   /* velo translúcido */
```

Ese es el patrón: sobre el azul del encabezado, un velo blanco al 12% mantiene el
tono de la marca y deja el texto blanco por encima. El submenú de Mi Cuenta es el
único que quedó fuera de la corrección.

### Decisiones

**D-6. `mu-sub-menu` adopta el mismo velo translúcido que los submenús nativos.**
No es una invención: es copiar el tratamiento que el propio sitio ya aplicó y
verificó en el mismo breakpoint. Con esto se cumplen FR-010 (contraste) y FR-011
(coherencia) con el cambio más pequeño posible, y sin inventar un valor de color:
es un canal alfa sobre el color de marca, no un color nuevo.

**Contraste resultante.** Con `--primario` (`#2B9FCF`) de fondo y texto
`--blanco` (`#FFFFFF`), la luminancia de `#2B9FCF` es ≈ 0.30 y la relación de
contraste es **5.02:1**, por encima del 4.5:1 que exige SC-003. El velo translúcido
al 12% aclara ligeramente el fondo y baja la cifra, que se mantiene por encima de
4.5:1. El valor exacto se mide en `quickstart.md` sobre el píxel real, no se
declara calculado: si la medición difiere, manda la medición.

**D-7. El área táctil se alinea con la de los submenús nativos.**
Los enlaces nativos en móvil ya tienen `min-height: 44px` y `padding: 12px 20px`
(`header.css` líneas 495-505), con un comentario que explica que además resuelven
un problema real de hit-area en iOS Safari. `mu-sub-menu li a` no tiene ninguno de
los dos. Se le aplican las mismas medidas, con el mismo motivo anotado.

**D-8. La regla global de color se conserva intacta.**
La regla de la línea 236 **no se toca**, porque en escritorio es correcta: el
submenú tiene fondo azul y necesita texto blanco. Corregir el móvil sin tocar la
regla global garantiza que el escritorio no cambie (FR-017, FR-032). La corrección
se aplica como una regla más específica dentro del media query móvil, que es donde
vive el error.

### Lo que NO cambia

`js/header.js` no se modifica. El submenú ya abre y cierra con una clase
`.active`, ya cierra al tocar fuera, y ya se cierra al pasar a escritorio (líneas
43-51, con `resize` y `clearTimeout`). El comportamiento es correcto; lo que estaba
roto era solo la apariencia.

---

## 3. El botón de WhatsApp no invita a pulsarse

### Lo que dice el código

`css/components/global-ui.css` líneas 89-105, el bloque completo:

```css
.boton-whatsapp {
    position: fixed;
    width: 50px; height: 50px;
    bottom: 82px; right: 25px;
    background-color: #25d366;            /* literal */
    box-shadow: 2px 2px 0px 0px #339db7; /* literal */
    z-index: 100;
}
```

Cuatro problemas, tres de ellos confirmables sin discusión:

| Problema | Ubicación | Impacto |
|---|---|---|
| Sin `:hover`, sin `:focus-visible`, sin `:active` | bloque completo | FR-019 incumplido: no hay ningún estado |
| `#25d366` y `#339db7` literales | líneas 95, 100 | Violación directa del principio VIII |
| El enlace no tiene nombre accesible | `inc/ui.php` líneas 123-126: solo `alt` en la imagen | FR-022: un lector de pantalla anuncia el archivo, no la función |
| `bottom: 82px` fijo, sin zona segura | línea 93 | FR-023: en iPhone la barra de direcciones ocupa esa franja |

### El efecto "burbuja de Facebook" sin código de Facebook

Se evaluaron tres formas de lograr la invitación visual:

| Alternativa | Evaluación |
|---|---|
| Incluir el widget oficial de Facebook Messenger | **Descartada.** Contradice el principio X (el tema no integra plataformas externas) y el Out of Scope de la spec. Agrega un script de terceros y una capa de privacidad |
| Reimplementar el widget con SVG y JS propios | **Descartada.** Duplica un servicio que ya existe y agrega peso a todas las páginas para un efecto decorativo |
| Resolver la invitación con **CSS sobre el control actual** | **Elegida.** El control ya existe, ya está posicionado y ya enlaza a WhatsApp. Lo que falta es que se vea que se puede pulsar |

Se eligió CSS por una razón que no es estética: es la única opción que **no depende
de que el dispositivo tenga un puntero fino**. Un `mouseenter` no existe en un
teléfono. Todo lo que se exprese en `:hover` puro desaparece en táctil, que es
exactamente el caso que la spec quiere cubrir. La invitación se resuelve con estados
de foco, de pulsación y con la presencia de una etiqueta de texto, que sí se ven en
todos los dispositivos.

**D-9. La invitación visual es una etiqueta de texto que acompaña al ícono.**
El botón pasa de ser un círculo de 50 px a una píldora con el ícono y una etiqueta
corta. Esto resuelve varias cosas a la vez:

- **FR-018** — comunica qué es y que se puede pulsar, sin depender del ícono.
- **FR-023** — en 320 px de ancho, una etiqueta hace que el control sea más
  claramente pulsable que un círculo pequeño; la geometría se verifica en
  `quickstart.md` en los cinco anchos que nombra SC-007.
- **Coherencia** — el sitio ya tiene esta misma forma en `.wplng-restore-tab`
  (`global-ui.css` líneas 49-65), un control flotante con etiqueta. El botón de
  WhatsApp se le parece, lo que resuelve la incoherencia sin inventar un patrón.

**D-10. La etiqueta se muestra siempre en móvil y se revela al enfocar en
escritorio.**
Esta es la respuesta a la pregunta que el checklist dejó abierta. En escritorio
aparece al pasar el puntero o al enfocarlo con teclado; en móvil está siempre
visible. La razón: en un teléfono no existe "pasar el puntero", así que una etiqueta
que solo aparece al hover sería invisible para el usuario al que la spec prioriza.

**D-11. La animación usa `transform` y `opacity` solamente.**
El principio VIII prohíbe `style.transform` en línea y exige que las animaciones se
ejecuten en la compositora. El pulso de atención se define con una animación CSS
que anima `transform` y `opacity`, y se anula con
`@media (prefers-reduced-motion: reduce)`. El patrón ya existe dos veces en el
repositorio: `css/components/country-modal.css` línea 94 y `templates/coming-soon.php`.

El pulso es **único y no repetitivo**: una sola pulsación al aparecer, no un latido
infinito. Un latido perpetuo en una esquina de la pantalla es un anti-patrón de
accesibilidad conocido, y el criterio es que se invite a pulsar, no que se persiga al
usuario.

**D-12. Los dos colores literales pasan a variables, con excepción documentada.**

`#25d366` es el verde institucional de WhatsApp y `#339db7` la sombra de acento.
Ninguno tiene equivalente en el `:root` de `style.css`, y el principio VIII
prohíbe inventar variables nuevas *cuando ya existe una equivalente* — aquí no
existe. Decisión: se declaran como variables nuevas **con prefijo y comentario que
justifica que no son un color de marca del sitio**, y se registran en el §8 del guide
como excepción acotada con su origen. Es la lectura que respeta la constitución sin
perder la identidad visual del canal de contacto.

**D-13. El nombre accesible se agrega en PHP, con escape.**
`inc/ui.php` líneas 123-126 gana un `aria-label` con `esc_attr()`. Es el único
cambio de PHP de toda la feature, y no declara ninguna función: solo un atributo en
el enlace que ya existe. El destino, el número y el mensaje no cambian (FR-024).

**D-14. `bottom` pasa a considerar la zona segura del dispositivo.**
`bottom: 82px` se combina con la zona segura del sistema para que el control quede
por encima de la barra de direcciones y del indicador de inicio en iPhone. El valor
actual es un número fijo elegido a ojo, sin ninguna relación con el dispositivo.

---

## 4. Las pantallas de la sección de cuenta no tienen diseño propio

### Lo que dice el código

Búsqueda exhaustiva en todos los CSS del tema:

```text
grep -rn "woocommerce-account" css/ style.css  →  0 resultados
grep -rn "woocommerce-form-login"  css/        →  0 resultados
grep -rn "MyAccount-navigation"   css/        →  0 resultados
```

El único archivo de la sección es `css/account-downloads.css`, de 17 líneas, y se
carga **solo** en el endpoint de descargas (`functions.php` línea 133, con la
condición `mu_wc_is_account_page() && mu_wc_is_wc_endpoint_url('downloads')`).

Es decir: la pantalla de Mi Cuenta y la de Detalles de la cuenta no tienen **ningún**
estilo propio. Se muestran con lo que GeneratePress y WooCommerce definen, que está
diseñado para escritorio.

Verificado contra producción con `curl` sobre `https://ec.muyunicos.com/mi-cuenta/`:
el marcado es el estándar de WooCommerce (`.woocommerce-MyAccount`,
`.woocommerce-form-login`, `.woocommerce-form-row--wide`), sin ninguna clase `mu-` en
la pantalla. Confirma la premisa de la spec.

**D-15. `css/account.css` nuevo, cargado con `mu_wc_is_account_page()`.**
Cumple FR-031 (no se carga fuera de la sección) y el principio II. La dependencia se
declara como `['mu-base']`, igual que el resto de los componentes.

**D-16. No se reescriben las plantillas de cuenta.**
Se descarta explícitamente usar `woocommerce_account_content` o cualquier template
override: son muchas más rutas de fallo —hooks que se dejan de disparar, datos que
no aparecen— a cambio de un beneficio nulo, porque el problema es de estilo, no de
estructura. El tema solo añade una capa visual encima del marcado que WooCommerce ya
produce.

**D-17. La navegación de la sección se mantiene como lista vertical.**
Esta es la respuesta a la pregunta que el checklist dejó abierta. FR-028 solo pide
que sea alcanzable y legible. Una lista vertical de 6 enlaces cumple sin más, y
convertirla en acordeón en móvil exigiría JavaScript nuevo, un estado más que
mantener, y un componente que ya existe con otro comportamiento —el acordeón del
menú principal, con su propia lógica de GeneratePress. Añadir un segundo patrón de
acordeón al sitio es más deuda de la que resuelve.

**D-18. Se reutilizan los tokens y no se inventan medidas.**
La hoja de cuenta usa las variables de `:root` que la constitución VIII obliga, y
`clamp()` para la tipografía fluida, como exige el mismo principio. La verificación
de que no queda ningún color literal ni variable inventada es un paso de
`quickstart.md`, porque el principio VIII define un criterio **binario** y "parece
que está bien" no lo satisface.

---

## 5. Verificaciones de caché y de no-regresión

Comprobado antes de diseñar, para no construir sobre una premisa falsa:

| Verificación | Comando | Resultado |
|---|---|---|
| La sección de cuenta no se cachea | `curl -sI https://ec.muyunicos.com/mi-cuenta/` | `Cache-Control: no-cache, no-store, must-revalidate` y `X-LiteSpeed-Cache-Control: no-cache` — **correcto**, principio IV vigente |
| La versión de PHP en producción | mismo `curl` | `X-Powered-By: PHP/8.5.4` — coincide con el guide §2 |
| El orden real del encabezado | `curl` del HTML con User-Agent de iPhone | `skip-link` → `mu-header-country-item` → `header.site-header` → `nav.main-navigation` — **confirma D-3** |
| El marcado de la sección de cuenta | `curl` de `/mi-cuenta/` | Clases de WooCommerce, cero clases `mu-` — **confirma D-16** |
| La versión local de PHP | `php -v` | 8.5.9. **Distinta de producción (8.5.4)** |

Esa última fila es una advertencia que se propaga a `quickstart.md`: la verificación
de sintaxis se puede hacer en local, pero **el comportamiento verificado en
producción es 8.5.4**, y no hay forma de probar localmente contra esa versión.

---

## 6. Riesgos técnicos identificados

| Riesgo | Impacto | Mitigación |
|---|---|---|
| El `menu-toggle` de GeneratePress y la bandera comparten franja | Si la bandera se posiciona mal, tapa el botón de menú o al revés | FR-008 lo exige explícitamente; se verifica en los 5 anchos de SC-007 |
| Regresión en escritorio al tocar reglas globales | El arreglo de `mu-sub-menu` es tentador tocar la regla de la línea 236, que también aplica en escritorio | D-8: la corrección va **dentro** del media query móvil, nunca en la regla global |
| La animación del botón consume CPU | Un pulso mal hecho puede bajar el rendimiento de todo el sitio | D-11: solo `transform` y `opacity`, una pulsación, anulada con `prefers-reduced-motion` |
| Los estilos de cuenta rompen un breakpoint de WooCommerce | WooCommerce tiene breakpoints propios para la navegación de cuenta | La hoja declara los rangos explícitamente y SC-007 los verifica en 5 anchos |
| La versión local de PHP difiere de producción | Un `php -l` local no garantiza nada del servidor | Declarado en §5 y en `quickstart.md` |
| El `z-index` del botón de contacto (100) queda por debajo de los modales | El botón puede verse por encima de una ventana emergente | Se conserva la prioridad actual: el botón va **debajo** de los modales, que es lo correcto. Se documenta, no se cambia |

---

## 7. Resumen de decisiones

| # | Decisión | Componente |
|---|---|---|
| D-1 | Interacción de escritorio y táctil separadas por `matchMedia` | Selector de país |
| D-2 | Estado visible por clase, no por atributo de estilo en línea | Selector de país |
| D-3 | La bandera sale del posicionamiento absoluto contra el documento | Selector de país |
| D-4 | El retraso de cierre de 200 ms se conserva en escritorio | Selector de país |
| D-5 | La bandera no cambia de lugar | Selector de país |
| D-6 | `mu-sub-menu` adopta el velo translúcido de los submenús nativos | Submenú |
| D-7 | Área táctil alineada con la de los submenús nativos | Submenú |
| D-8 | La regla global de color no se toca; la corrección es del media query móvil | Submenú |
| D-9 | El botón se convierte en píldora con etiqueta de texto | Botón de contacto |
| D-10 | Etiqueta siempre visible en móvil, revelada al enfocar en escritorio | Botón de contacto |
| D-11 | Animación solo con `transform` y `opacity`, única y anulable | Botón de contacto |
| D-12 | Los colores literales pasan a variables con excepción documentada | Botón de contacto |
| D-13 | Nombre accesible agregado en PHP con `esc_attr()` | Botón de contacto |
| D-14 | Posición considera la zona segura del dispositivo | Botón de contacto |
| D-15 | `css/account.css` nuevo con carga condicional | Pantallas de cuenta |
| D-16 | Sin overrides de plantillas de WooCommerce | Pantallas de cuenta |
| D-17 | La navegación de cuenta se mantiene como lista vertical | Pantallas de cuenta |
| D-18 | Reutilización estricta de tokens y `clamp()` | Pantallas de cuenta |

**Sin NEEDS CLARIFICATION pendientes.** El comando `/speckit-plan` exige resolverlos
todos en esta fase, y las tres decisiones que el checklist dejó abiertas para esta
fase quedan contestadas: D-5 (la bandera no se mueve), D-10 (etiqueta siempre visible
en móvil) y D-17 (navegación de cuenta como lista vertical).
borde "apilado en el encabezado" de la spec.