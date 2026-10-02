# Feature Specification: Correcciones y mejoras de la interfaz en el móvil

**Feature Branch**: `003-mobile-ui-fixes`

**Created**: 2026-10-02

**Status**: Draft

**Input**: User description: "cuando toco country-selector-trigger no se abre la lista de paises en el movil. en movil mu-sub-menu es un menu blanco con letra blanca, no se lee. deberia mantener coherencia con el resto de menus. estaria bueno que boton-whatsapp tenga un efecto similar al de las burbujas de facebook. considera mejoras en la interfaz movil de https://ec.muyunicos.com/mi-cuenta/edit-account/ https://ec.muyunicos.com/mi-cuenta/ etc"

## Problema

Cuatro defectos de interfaz en el móvil, en tres componentes que el comprador ya
usa a diario y en las pantallas donde gestiona su cuenta:

1. Al tocar la bandera del selector de país no se abre la lista de países. En el
   teléfono el control parece muerto, y con él desaparece la única forma de cambiar
   de país sin usar el teclado.
2. El submenú de Mi Cuenta es un panel blanco con texto blanco. Es literalmente
   ilegible: el comprador autenticado ve un menú que no puede leer y del que no
   puede saber qué opciones tiene.
3. El botón flotante de WhatsApp no invita a pulsarse. No cambia de estado, no
   reacciona al dedo y no dice qué hace, así que la mayoría pasa por encima.
4. Las pantallas de Mi Cuenta y de Detalles de la cuenta no tienen trabajo de
   interfaz móvil: se muestran con los estilos genéricos de la plataforma, sin la
   tipografía, los colores ni las medidas táctiles del resto del sitio.

Los cuatro comparten una misma raíz: el sitio se diseñó y se ajustó mirando
escritorio, y las correcciones puntuales que llegaron al móvil quedaron a medias.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - La bandera del país abre su lista al tocarla (Priority: P1)

Un comprador en el teléfono toca la bandera del encabezado esperando ver la lista
de países para cambiar de subdominio. Hoy no ve nada: el control responde al dedo
y la lista no aparece, como si estuviera roto.

**Why this priority**: es el único de los cuatro puntos que **bloquea una
función**. El submenú ilegible tiene rutas alternativas (el pie de página), el
botón de WhatsApp es una invitación, y las pantallas de cuenta se pueden usar con
esfuerzo. La bandera, en cambio, es el único mecanismo de cambio de país
disponible en pantalla táctil: sin él, el comprador que necesita otro país no tiene
camino.

**Independent Test**: con un teléfono real en el subdominio de Ecuador, tocar la
bandera y comprobar que aparece la lista de países. Se puede validar entero sin
involucrar a nadie más y sin tocar ninguna otra parte del sitio.

**Acceptance Scenarios**:

1. **Given** un comprador en el teléfono en cualquier página, **When** toca la
   bandera del encabezado, **Then** aparece la lista de países con todas las
   opciones disponibles, sin necesidad de tocar dos veces ni mantener el dedo
   apoyado.
2. **Given** la lista de países abierta, **When** el comprador toca un país
   distinto al actual, **Then** el navegador lo lleva al subdominio equivalente de
   ese país, conservando la página que estaba viendo y el idioma que corresponde a
   ese subdominio.
3. **Given** la lista de países abierta, **When** el comprador toca en cualquier
   lugar fuera de ella, **Then** se cierra y el encabezado queda como estaba.
4. **Given** un comprador en el teléfono, **When** abre el menú hamburguesa del
   sitio y después abre la lista de países, **Then** ambos pueden verse y ninguno
   tapa al otro de forma que impida ver o tocar sus opciones.
5. **Given** la lista de países abierta, **When** el lector de pantalla consulta el
   estado del control, **Then** informa que la lista está desplegada.

---

### User Story 2 - El submenú de Mi Cuenta se lee y se usa (Priority: P2)

Un comprador autenticado en el teléfono toca el ícono de Mi Cuenta esperando ver
sus accesos: Detalles de la cuenta, Mis Descargas, Órdenes y Salir. El panel se
abre, pero es blanco con letras blancas: se ve un bloque vacío. No puede leer una
sola opción y no sabe si hay algo que tocar.

**Why this priority**: bloquea la navegación dentro de la sección de cuenta desde
el encabezado, pero no impide completar la tarea: existe una ruta alternativa en el
pie de página y un toque accidental sobre el ícono sí llega a Mi Cuenta. Es grave,
pero no es una función perdida.

**Independent Test**: con un teléfono real y una sesión iniciada, abrir el submenú
de Mi Cuenta y comprobar que cada opción es legible y pulsable. No requiere tocar
ningún otro componente.

**Acceptance Scenarios**:

1. **Given** un comprador autenticado en el teléfono, **When** toca el ícono de
   Mi Cuenta, **Then** el submenú se abre y **todas** sus opciones son legibles: el
   texto se distingue del fondo.
2. **Given** el submenú abierto, **When** el comprador mira las opciones, **Then**
   ve el mismo tratamiento visual que las demás listas desplegables del sitio en
   móvil (mismo fondo, mismo tipo de letra, mismos bordes y sombra), no una
   excepción.
3. **Given** el submenú abierto, **When** el comprador toca una opción, **Then** el
   enlace responde al primer toque y lo lleva a su destino.
4. **Given** el submenú abierto, **When** el comprador toca fuera de él, **Then**
   se cierra.
5. **Given** un comprador **sin** sesión iniciada, **When** toca el ícono de
   Mi Cuenta, **Then** lo lleva a Mi Cuenta como hoy, y no aparece ningún panel en
   blanco.
6. **Given** el submenú abierto y el comprador gira el teléfono, **When** la
   pantalla pasa a tamaño de escritorio, **Then** el panel no queda flotando a medio
   camino ni tapa el contenido.

---

### User Story 3 - Mis pantallas de cuenta se pueden usar en el teléfono (Priority: P3)

Un comprador autenticado entra a Mi Cuenta y a Detalles de la cuenta desde el
teléfono. Necesita leer los pedidos, actualizar su email o su teléfono, y cambiar su
contraseña. Las pantallas se muestran con el aspecto genérico de la plataforma:
campos apretados, controles pequeños, sin la tipografía del resto del sitio.

**Why this priority**: no impide completar la tarea, pero la vuelve incómoda y
frágil, sobre todo al escribir en los campos. En el carrito y el checkout sí se
trabajó el móvil; en la sección de cuenta, que es donde el comprador vuelve
constantemente, no.

**Independent Test**: con un teléfono real y una sesión iniciada, recorrer Mi
Cuenta y Detalles de la cuenta, comprobando que nada desborda, que todo se lee y
que se puede completar la edición de datos. No toca carrito ni checkout.

**Acceptance Scenarios**:

1. **Given** un comprador autenticado en el teléfono, **When** abre "Detalles de
   la cuenta", **Then** el formulario ocupa un ancho cómodo de leer, los campos se
   pueden completar y guardar, y los botones tienen el mismo aspecto y tamaño que
   los del resto del sitio.
2. **Given** un comprador autenticado en el teléfono, **When** abre "Mi cuenta",
   **Then** la navegación lateral de la sección y el contenido se leen sin
   desplazamiento horizontal y se alcanzan con el dedo.
3. **Given** un teléfono angosto (320 píxeles de ancho), **When** el comprador
   recorre cualquiera de las dos pantallas, **Then** ninguna parte del contenido se
   sale de la pantalla ni obliga a desplazar la página de lado a lado.
4. **Given** cualquier pantalla de cuenta en el teléfono, **When** el comprador
   recorre la página, **Then** el diseño es coherente con el resto del sitio: mismos
   colores, tipografía, bordes y separación entre bloques.
5. **Given** el comprador no autenticado, **When** entra a la pantalla de Mi Cuenta,
   **Then** ve su formulario de acceso con el mismo cuidado de diseño que las
   pantallas de usuario autenticado.
---

### User Story 4 - El botón de WhatsApp invita a pulsarse (Priority: P4)

Un comprador con una duda antes de comprar busca un canal para preguntar. El botón
de WhatsApp está en el teléfono, pero es un círculo plano y estático: no cambia al
acercarse, no cambia al pulsarlo y no explica para qué sirve.

**Why this priority**: es una mejora de conversión y no de función. El comprador
que busca ayuda ya conoce el ícono y lo pulsa; el que no lo conoce simplemente lo
ignora. Nadie queda bloqueado por esto.

**Independent Test**: con un teléfono real, observar el botón de WhatsApp al
recorrer la página y al pulsarlo, comprobando que responde y que no tapa nada
importante.

**Acceptance Scenarios**:

1. **Given** un comprador en el teléfono, **When** ve el botón de WhatsApp, **Then**
   comunica que es un canal de contacto y que puede pulsarse, con una invitación
   visual equiparable a la burbuja de contacto de Facebook.
2. **Given** el botón de WhatsApp a la vista, **When** el comprador acerca el dedo
   o el puntero, **Then** el botón reacciona de forma visible, y al pulsarlo muestra
   que ha sido reconocido.
3. **Given** un teléfono angosto (320 píxeles), **When** el botón está visible,
   **Then** no tapa el carrito, ni ningún botón, ni la barra de direcciones del
   navegador.
4. **Given** un comprador que usa un sistema con la opción de reducir el movimiento
   activada, **When** ve el botón de WhatsApp, **Then** no hay animación continua ni
   repetida: solo cambia de estado al rozarlo y al pulsarlo.
5. **Given** el botón de WhatsApp, **When** el comprador lo pulsa, **Then** se abre
   la conversación de WhatsApp con el mismo número y el mismo mensaje de hoy.

---

### Edge Cases

- **Sesión iniciada y sesión cerrada**: el submenú de Mi Cuenta solo existe para el
  comprador autenticado. El ícono, en cambio, existe siempre y cambia de
  comportamiento según haya sesión o no. El arreglo no puede romper el caso de
  visitante.
- **Apilado en el encabezado**: la bandera y el ícono de Mi Cuenta conviven en
  pantalla angosta con el botón del menú hamburguesa. Los tres se tocan, se abren y
  se cierran, y dos pueden quedar abiertos a la vez. Ninguno puede tapar al otro de
  forma que impida ver o tocar sus opciones.
- **Rotación del dispositivo**: con un panel abierto, girar el teléfono cambia el
  ancho y puede cruzar el punto en que el diseño pasa a versión escritorio. El panel
  no debe quedar flotando, ni a medio camino, ni tapando el contenido.
- **Punto exacto de cambio de tamaño**: el sitio cambia de versión móvil a escritorio
  en un ancho concreto. Un panel abierto justo antes de ese cambio debe cerrarse o
  volver a comportarse como el de escritorio, nunca quedar en un estado intermedio.
- **Teclado en pantalla**: al abrir el teclado del teléfono para editar un dato, la
  pantalla útil se reduce a la mitad. El botón de contacto y los paneles abiertos no
  deben quedar atrapados ni tapando el campo que se está editando.
- **iPhone con barra de direcciones y muesca**: el borde inferior de la pantalla no
  es área visible. El botón de contacto debe quedar por encima de esa barra, no
  debajo.
- **Comprador con daltonismo**: el submenú actual falla por contraste de color, no
  solo por brillo. La corrección tiene que hacer legible el texto en condiciones
  normales de luz y de sol, no solo para quien no distingue ese tono.
- **Navegación sin eventos de puntero fino**: algunos navegadores y ajustes de
  accesibilidad no generan eventos de "el puntero pasó por encima". La apertura de
  la lista de países no puede depender de esa clase de evento.
- **Lectores de pantalla y teclado físico**: cualquiera de los cuatro componentes
  debe poder abrirse, recorrerse y cerrarse sin depender del tacto.
- **Preferencia de reducir movimiento**: la animación del botón de contacto puede
  provocar mareo o migrañas en personas sensibles. Debe ser desactivable por
  preferencia del sistema.

## Requirements *(mandatory)*

### Functional Requirements

#### Selector de país

- **FR-001**: El selector de país MUST abrir su lista de países ante un toque en el
  teléfono, con un solo toque y sin necesidad de mantener el dedo apoyado.
- **FR-002**: El selector de país MUST NOT requerir dos toques ni un gesto sostenido
  para abrirse, y MUST NOT responder al toque cerrando lo que acaba de abrir.
- **FR-003**: El estado abierto del selector de país MUST ser observable por el
  estilo de la página y por el atributo de estado accesible del control, de modo que
  su apariencia no dependa de una marca técnica frágil.
- **FR-004**: El selector de país MUST cerrarse al tocar fuera de él.
- **FR-005**: El selector de país MUST poder abrirse y cerrarse con teclado y con
  lector de pantalla, y MUST informar su estado desplegado o plegado.
- **FR-006**: Elegir un país en la lista MUST llevar al comprador al subdominio
  equivalente de ese país, conservando la página que estaba viendo.
- **FR-007**: El selector de país MUST seguir funcionando en los subdominios que
  agregan un prefijo de idioma, respetando ese prefijo al cambiar de país.
- **FR-008**: La bandera MUST NOT tapar ni impedir el uso del botón del menú
  principal en pantallas angostas.
- **FR-009**: El texto visible de la lista de países y la descripción de la bandera
  MUST permanecer los mismos que hoy.

#### Submenú de Mi Cuenta

- **FR-010**: El submenú de Mi Cuenta MUST tener en el móvil texto legible sobre su
  propio fondo, con un contraste suficiente para leerse en condiciones normales de
  luz y de sol.
- **FR-011**: El submenú de Mi Cuenta MUST compartir el tratamiento visual con las
  demás listas desplegables del sitio en el móvil: mismo fondo, misma tipografía,
  mismos bordes y misma sombra.
- **FR-012**: Cada enlace del submenú de Mi Cuenta MUST tener un área táctil
  suficiente para pulsarse con el dedo sin acertar.
- **FR-013**: Cada enlace del submenú de Mi Cuenta MUST responder al primer toque.
- **FR-014**: El submenú de Mi Cuenta MUST cerrarse al tocar fuera de él y MUST
  cerrarse al pasar el sitio a la versión de escritorio.
- **FR-015**: El ícono de Mi Cuenta MUST conservar su comportamiento actual cuando no
  hay sesión iniciada: llevar a la pantalla de Mi Cuenta sin abrir ningún panel.
- **FR-016**: El submenú de Mi Cuenta MUST NOT aparecer vacío o ilegible en ninguna
  combinación de estado de sesión, tamaño de pantalla y orientación.
- **FR-017**: El comportamiento de escritorio del submenú de Mi Cuenta MUST NOT
  cambiar.

#### Botón de contacto

- **FR-018**: El botón de contacto MUST comunicar visualmente que es un canal de
  contacto y que puede pulsarse, con una invitación equivalente a la de la burbuja
  de contacto de Facebook.
- **FR-019**: El botón de contacto MUST tener estados distinguibles de reposo,
  acercamiento y pulsación, e MUST indicar que ha sido reconocido al pulsarlo.
- **FR-020**: La invitación visual del botón de contacto MUST NOT bloquear la respuesta
  del sitio mientras se juega: el desplazamiento de la página y la pulsación de
  cualquier otro control MUST seguir respondiendo mientras la animación se ejecuta.
- **FR-021**: La invitación visual del botón de contacto MUST quedar anulada cuando el
  sistema declara que se prefiere reducir el movimiento: en ese caso el botón solo
  MUST mostrar sus estados de reposo, acercamiento y pulsación, sin animación
  continua ni repetida.
- **FR-022**: El botón de contacto MUST tener un nombre accesible que describa su
  función sin depender del ícono.
- **FR-023**: El botón de contacto MUST NOT tapar el carrito, ningún otro control
  pulsable ni la barra de direcciones del navegador, en anchos de entre 320 y 430
  píxeles.
- **FR-024**: El botón de contacto MUST conservar el mismo destino, el mismo número y
  el mismo mensaje de la conversación que usa hoy, y MUST abrirse en una pestaña
  nueva sin exponer la página de origen.
- **FR-025**: Los colores del botón de contacto MUST tomarse del sistema de diseño
  del sitio, sin valores sueltos que dupliquen colores ya definidos por el sitio.

#### Pantallas de cuenta

- **FR-026**: Las pantallas de Mi Cuenta y de Detalles de la cuenta MUST adaptarse al
  ancho de la pantalla del teléfono sin desbordes ni desplazamiento horizontal.
- **FR-027**: Los campos, botones y enlaces de las pantallas de cuenta MUST tener
  tipografía, color, separación y tamaño de área táctil coherentes con el resto del
  sitio.
- **FR-028**: La navegación de la sección de cuenta MUST ser alcanzable y legible en
  el teléfono, sin quedar truncada ni fuera de pantalla.
- **FR-029**: Los formularios de las pantallas de cuenta MUST poder completarse y
  guardarse desde el teléfono, incluidos los campos de datos de contacto y los de
  cambio de contraseña.
- **FR-030**: El formulario de acceso de la pantalla de Mi Cuenta, para compradores
  sin sesión iniciada, MUST tener el mismo cuidado de diseño que las pantallas de
  usuario autenticado.
- **FR-031**: El trabajo visual de las pantallas de cuenta MUST NOT cargarse en
  páginas que no son de la sección de cuenta.

#### Alcance transversal

- **FR-032**: Ninguno de los cuatro componentes MUST modificar su comportamiento ni
  su apariencia en la versión de escritorio, salvo el botón de contacto, que sí gana
  estados de interacción que no tenía.
- **FR-033**: La corrección de los cuatro componentes MUST NOT requerir modificar el
  tema base del que el sitio deriva ni ningún complemento instalado; todo MUST
  resolverse dentro del tema propio del sitio.
- **FR-034**: Las URLs de referencia y las etiquetas de idioma de cada página MUST
  NOT cambiar como consecuencia de este trabajo.

### Key Entities

- **Selector de país**: Control del encabezado compuesto por una bandera que actúa
  de disparador y una lista de destinos. Atributos: país actual, lista de países
  disponibles, estado desplegado o plegado. Relaciones: cada destino apunta al
  subdominio de ese país con la página actual y, si corresponde, el prefijo de
  idioma.
- **Submenú de Mi Cuenta**: Panel con los accesos a la sección de cuenta. Atributos:
  sesión iniciada o no, lista de accesos (detalles de la cuenta, descargas, órdenes,
  salir), estado abierto o cerrado. Relaciones: se muestra y se oculta en función de
  la sesión del comprador.
- **Botón de contacto**: Acceso flotante y permanente al canal de conversación.
  Atributos: destino de la conversación, número, mensaje previo, conjunto de estados
  interactivos, nombre accesible. Relaciones: fijo a la ventana, por encima del
  contenido y por debajo de las ventanas emergentes del sitio.
- **Pantalla de cuenta**: Cualquiera de las vistas de la sección de cuenta (mi
  cuenta, detalles de la cuenta, descargas, órdenes, pagos y salidas). Atributos:
  tipo de pantalla, sesión requerida o no, bloques de contenido que presenta.
  Relaciones: navegables entre sí mediante la navegación de la sección.
- **Preferencia de reducir movimiento**: Señal del sistema del comprador que indica
  que las animaciones deben minimizarse. Atributos: activada o no. Relaciones:
  condiciona toda la invitación visual del botón de contacto.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: En un teléfono real, un toque en la bandera muestra la lista de países
  en menos de un segundo, el cien por cien de las veces, sin necesidad de repetir
  el toque.
- **SC-002**: El comprador completa el cambio de país en un máximo de dos toques
  desde que ve la bandera, en cualquier página del sitio.
- **SC-003**: El submenú de Mi Cuenta es totalmente legible: todo su texto alcanza
  una relación de contraste de 4.5 a 1 o superior contra su fondo, verificable con
  cualquier medidor de contraste.
- **SC-004**: Ningún elemento de texto del submenú de Mi Cuenta queda invisible
  sobre su fondo, verificado a simple vista con el brillo normal de la pantalla y a
  pleno sol.
- **SC-005**: Cada enlace del submenú de Mi Cuenta se activa al primer toque, con un
  área táctil de al menos 44 por 44 píxeles.
- **SC-006**: Los cuatro componentes se pueden abrir, recorrer y cerrar solo con
  teclado, sin depender del ratón ni del tacto.
- **SC-007**: Ninguna pantalla de la sección de cuenta requiere desplazamiento
  horizontal en anchos de 320, 375, 390, 414 y 430 píxeles.
- **SC-008**: El botón de contacto es visible, pulsable y no tapa ningún control en
  anchos de entre 320 y 430 píxeles, y mantiene su distancia respecto de la barra de
  direcciones del navegador.
- **SC-009**: El botón de contacto cambia de estado al pulsarse en menos de un
  segundo, y su invitación visual no produce tirones al desplazar la página.
- **SC-010**: El conjunto de páginas de la sección de cuenta se recorre de principio
  a fin sin encontrar un solo elemento fuera de pantalla.
- **SC-011**: El sitio no pesa más en las páginas que no son de la sección de cuenta:
  el peso de la página de tienda y de la página de un producto queda igual antes y
  después del cambio, dentro de la variación habitual de caché.
- **SC-012**: Cero colores escritos a mano en los archivos modificados en los que ya
  existe un color equivalente definido por el sitio. Verificable con una búsqueda
  de texto.
- **SC-013**: Los cuatro puntos se verifican en un teléfono real y en el subdominio
  de Ecuador, que es donde se reportó el problema, y no solo en un escritorio.
- **SC-014**: La lista de países, el submenú de Mi Cuenta, el botón de contacto y el
  carrito siguen funcionando sin regresiones a partir de 1024 píxeles de ancho.
- **SC-015**: La compra completa, desde la página de producto hasta la confirmación
  del pedido, sigue funcionando sin cambios en el teléfono.

## Assumptions

- El corte entre versión móvil y de escritorio es el que el sitio ya usa en todos
  sus componentes (hasta 768 píxeles de ancho). Esta feature no lo redefine ni lo
  cambia.
- El sitio deriva de un tema base y de un conjunto de complementos instalados que
  no se modifican desde aquí. Todo se resuelve mediante adaptación propia del sitio.
- Las cuatro correcciones comparten los mismos archivos de estilos y de
  comportamiento del encabezado y de los componentes globales del sitio, y por eso
  se resuelven en un solo trabajo y no en cuatro.
- El efecto pedido para el botón de contacto se toma como referencia el
  comportamiento de la burbuja de contacto de Facebook, sin incorporar a este sitio
  ninguna pieza, servicio o código de esa plataforma.
- El comportamiento de escritorio de los cuatro componentes se considera correcto y
  se preserva; el único que gana comportamiento nuevo es el botón de contacto, que
  no tenía ningún estado de interacción.
- La verificación se realiza contra el sitio en producción, desde un teléfono real,
  porque este proyecto no cuenta con un entorno de pruebas ni con verificación
  automática.
- La apertura y el cierre del teclado en pantalla se consideran parte de la
  experiencia móvil del comprador, no un detalle del navegador.
- El número, el mensaje y el destino de la conversación de WhatsApp son decisiones de
  negocio ya tomadas; este trabajo no las modifica.

## Out of Scope

- Rediseñar la versión de escritorio de cualquiera de los cuatro componentes: en
  escritorio funcionan correctamente.
- Cambiar el canal de contacto, el número de teléfono o el mensaje previo de la
  conversación.
- Incorporar canales de contacto adicionales (mensajería instantánea, redes
  sociales, chat en vivo) o cualquier integración con plataformas externas.
- Cambiar los textos, las URLs o la estructura de las pantallas de la sección de
  cuenta.
- Rediseñar el carrito y el checkout, aunque algunos de sus archivos se compartan
  con las pantallas de cuenta.
- Cualquier ajuste del servidor, de la CDN, de la caché o de los paneles de
  administración.
- Agregar la sección de cuenta a sitios que no la usen, o cambiar el comportamiento
  del sitio en versiones que no sean de teléfono.
