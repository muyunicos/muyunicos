# Feature Specification: Restaurar el login social y eliminar los SVG inline

**Feature Branch**: `002-fix-broken-social-login`

**Created**: 2026-10-02
**Status**: Draft
**Input**: El login con Google y Facebook devuelve 404 porque el tema
construye la URL a mano con wp-login.php, y WPS Hide Login mueve esa ruta.

## Problema

Cuatro enlaces de login social apuntan a una URL que ya no existe. Un
comprador que hace clic en "Continuar con Google" o "Facebook" llega a un 404.
Es un bug activo en produccion, no una hipotesis.

La causa es que el tema construye la URL con `site_url( "/wp-login.php?..." )` en
lugar de dejar que WordPress resuelva la ruta de login. WPS Hide Login
intercepta wp-login.php y lo devuelve como 404; la documentacion del plugin lo
advierte explicitamente: "no funciona con plugins o temas que tienen wp-login.php
hardcodeado".

Aprovechamos el mismo trabajo para eliminar los SVG inline del modal de
autenticacion, que violan el principio VIII y estan en las lineas contiguas a las
que hay que tocar. Separarlos obligaria a editar el mismo archivo dos veces por
dos motivos distintos.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - El comprador puede autenticarse con Google o Facebook (Priority: P1)

Un comprador que no tiene cuenta quiere entrar con su cuenta de Google o de
Facebook. El modal de autenticacion le ofrece ambos botones. Hoy, al hacer clic
en cualquiera de los dos, llega a una pagina de error 404 y no puede comprar.

**Why this priority**: es un bug activo que corta la compra. Afecta a la
conversion y no tiene workaround para el usuario.

**Independent Test**: pulsar un boton de login social y completar el flujo con un
proveedor real. Si el proveedor no llega a pedir permisos, el story falla.

**Acceptance Scenarios**:

1. **Given** un visitante no autenticado, **When** abre el modal de
   autenticacion y pulsa "Google", **Then** el navegador navega a la pantalla
   de permisos de Google, no a un error 404.
2. **Given** un visitante no autenticado, **When** pulsa "Facebook" desde el
   checkout, **Then** el navegador navega a los permisos de Facebook, no a un
   error 404.
3. **Given** un visitante en un subdominio de otro pais, **When** completa el
   login, **Then** vuelve al mismo subdominio donde empezo, con sus precios y su
   moneda.
4. **Given** un visitante, **When** se desactiva el plugin que oculta la URL de
   login, **Then** el login social sigue funcionando en la ruta por defecto.

---

### User Story 2 - Los iconos del modal salen del repositorio de iconos (Priority: P2)

Un mantenedor cambia el color de un icono y descubre que el SVG esta pegado
dentro del HTML del modal: hay que buscarlo en el archivo. El mismo icono
existe ya en el repositorio central, pero este modal no lo usa.

**Why this priority**: no rompe nada visible. Es deuda de consistencia: el mismo
proyecto tiene dos formas de hacer lo mismo, y la constitution prohibe la
primera.

**Independent Test**: buscar cualquier elemento `<svg>` dentro del tema fuera
del repositorio de iconos. Los que quedan son fallbacks deliberados o SVG
decorativos que no son iconos.

**Acceptance Scenarios**:

1. **Given** un icono reutilizable del modal, **When** se busca su equivalente en
   el repositorio central de iconos, **Then** el modal lo consume desde ahí en
   lugar de tener su propia copia.
2. **Given** un icono que no existe en el repositorio, **When** se usa en el
   modal, **Then** se agrega al repositorio en lugar de quedar pegado en el HTML.
3. **Given** el modal renderizado, **When** se compara con el anterior, **Then**
   los iconos se ven idénticos: el cambio es de origen, no de apariencia.

---

### Edge Cases

- **Cambio futuro de la ruta de login**: si el plugin de ocultamiento cambia su
  slug o se desactiva, el login social debe seguir funcionando sin tocar el
  tema. Un valor fijo en el código volvería a romperse.
- **Desactivar el plugin que oculta la URL**: al desactivarlo, la ruta vuelve a
  ser la de por defecto. El login social no debe romperse.
- **Formato de la URL de redireccion**: el proveedor recibe como destino la
  misma URL de login con el parametro del proveedor dentro. Si ese formato
  cambia, el proveedor puede rechazarlo como no valido. El fix debe reproducir
  exactamente el formato que ya funciona hoy.
- **Subdominio con prefijo de idioma**: en los subdominios que agregan prefijo de
  idioma, la redireccion debe respetar ese prefijo, no solo el host.
- **Icono que no tiene equivalente**: si el repositorio no tiene un icono
  equivalente, hay que agregarlo; no dejar una copia suelta ni reemplazar por
  otro icono que se vea parecido pero no sea el mismo.
- **Icono decorativo**: un SVG que no es un icono (por ejemplo, un gráfico de
  fondo) no pertenece al repositorio y puede quedarse donde está.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El tema MUST NOT construir la URL de login social concatenando a
  mano el nombre del archivo de login por defecto de WordPress.
- **FR-002**: El tema MUST obtener la ruta de login mediante la API de WordPress,
  que el plugin de ocultamiento modifica por sí solo, de modo que el cambio de
  ruta no requiera tocar el tema.
- **FR-003**: El tema MUST construir las cuatro URLs de login social (Google y
  Facebook, en el modal de autenticacion y en el checkout) a traves de un unico
  punto de codigo, de modo que un cambio futuro se aplique en los cuatro usos.
- **FR-004**: La URL generada MUST conservar el formato que el proveedor de
  login social ya acepta: la ruta de login seguida del parametro del proveedor
  y el parametro de redireccion, en ese orden.
- **FR-005**: La redireccion posterior al login MUST preservar el subdominio y
  la ruta desde la que el usuario empezo.
- **FR-006**: El tema MUST seguir funcionando si el plugin que oculta la URL de
  login esta desactivado, usando la ruta de login por defecto.
- **FR-007**: El modal de autenticacion MUST obtener sus iconos del repositorio
  central de iconos del tema.
- **FR-008**: Todo icono reutilizable que hoy esta pegado dentro del HTML del
  modal MUST pasar al repositorio central, conservando su apariencia.
- **FR-009**: El tema MUST NOT pegar SVG directamente dentro del HTML, salvo
  fallback deliberado cuando el repositorio de iconos no esta disponible, o
  Excepto en las plantillas standalone de la carpeta de plantillas.
- **FR-010**: Los SVG que no son iconos reutilizables MUST quedar fuera del
  alcance de esta feature, y quedar documentados como tales para no intentar
  convertirlos.

### Key Entities

- **URL de login social**: Enlace de autenticacion con un proveedor. Atributos:
  proveedor (google, facebook), ruta de login resuelta, destino de redireccion.
  Relaciones: se usa en el modal de autenticacion y en el checkout.
- **Punto de construccion de la URL**: Funcion unica del tema que genera todas
  las URLs de login social. Evita que el formato se defina en cuatro lugares.
- **Icono del repositorio**: SVG versionado y reutilizable. Atributos: nombre,
  markup. Relaciones: lo consumen el modal, el checkout, el header y el footer.
- **Proveedor de login social**: Servicio externo de autenticacion. Atributos:
  identificador, nombre visible, formato de URL que acepta.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Pulsar cualquiera de los cuatro botones de login social lleva al
  proveedor de autenticacion, no a un error. Verificable por cualquier persona
  sin conocimiento tecnico.
- **SC-002**: Un comprador autenticado en un subdominio de otro pais vuelve al
  mismo subdominio, con la misma moneda y el mismo catalogo.
- **SC-003**: Cero referencias a la ruta de login por defecto de WordPress
  construidas a mano en el codigo del tema. Verificable con una busqueda de
  texto.
- **SC-004**: Cero SVG pegados dentro del HTML del modal de autenticacion.
  Verificable con una busqueda de texto.
- **SC-005**: Los iconos del modal se ven identicos antes y despues del cambio.
  Verificable por comparacion visual.
- **SC-006**: El login social sigue funcionando con el plugin que oculta la URL
  activado y desactivado.
- **SC-007**: Los cuatro botones de login social se construyen desde un unico
  punto de codigo, no cuatro.
- **SC-008**: El login por usuario y contrasena, y el resto del checkout, siguen
  funcionando sin regresiones.

## Assumptions

- La ruta de login vigente es `/login/`, configurada en el panel del plugin que
  oculta la URL de login. Verificada en produccion el 2026-10-02: responde 200 y
  completa el flujo de autorizacion con Google y con Facebook.
- El proveedor de login social ya tiene dado de alta el formato de URL actual
  (ruta de login seguida del parametro del proveedor). El fix reproduce ese mismo
  formato, por lo que no requiere reconfigurar nada en el panel del proveedor.
- El helper unico de construccion de la URL vive en un modulo del tema, no en el
  plugin de login social, porque es una necesidad de presentacion del tema.
- Los cuatro SVG del modal de autenticacion son iconos reutilizables: cerrar,
  desplegar, usuario y correo. Los dos ultimos no existen todavia en el
  repositorio central y se agregan.
- Los dos SVG que quedan en `inc/ui.php` dentro de una comprobacion
  `function_exists()` son fallbacks deliberados y quedan fuera de alcance.
- Los SVG decorativos o de un solo uso en otros modulos quedan fuera de alcance y
  se documentan, para no intentar convertirlos sin saber si son iconos.
- No hay entorno de pruebas: toda verificacion se hace contra produccion, y el
  flujo de autenticacion requiere completar un permisos real de un proveedor
  externo.
- La compra de prueba del criterio SC-004 la realiza el mantenedor, porque
  requiere completar el pago.

## Out of Scope

- Migrar o cambiar de proveedor de login social.
- Reconfigurar el panel del proveedor de login social o del plugin que oculta la
  URL de login.
- Cambiar el texto visible de los botones, los tamanos de la ventana emergente o
  el diseno del modal.
- Limpiar los SVG inline del resto del tema: quedan fuera salvo los del modal de
  autenticacion, que se editara de todos modos en este trabajo.
- Agregar proveedores de login social que hoy no estan configurados.
- Revisar el solapamiento entre los dos plugins de filtrado de spam activos.
