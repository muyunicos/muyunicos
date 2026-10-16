# Feature Specification: Conservar el idioma (wpLingua) al iniciar sesión

**Feature Branch**: `005-login-language-redirect`

**Created**: 2026-10-16

**Status**: Draft

**Input**: User description: "cuando el cliente inicia sesion y esta en algun idioma de wplingua pt/en redirecciona a la base (hay que tener cuidado que no rompa el inicio de sesion con facebook, google o usuario/contraseña)"

## Problema

Un comprador que navega en `br.muyunicos.com/pt/` o `us.muyunicos.com/en/` inicia
sesión y termina en la URL base de su subdominio —la del idioma origen, español—
en lugar de volver a la página y al idioma desde donde empezó.

Medido el 2026-10-16 contra producción:

| Comprobación | Resultado |
|---|---|
| Enlace de login social en `br.muyunicos.com/pt/tienda/` | destino `https://br.muyunicos.com/tienda/` (sin `/pt`) |
| Enlace de login social en `br.muyunicos.com/pt/` | destino `https://br.muyunicos.com/` (la base) |
| Enlace de login social en `us.muyunicos.com/en/` | destino `https://us.muyunicos.com/` (la base) |
| Enlaces de cuenta en esas mismas páginas | `href="/pt/mi-cuenta/downloads/"` (sí localizados) |
| `br.muyunicos.com/login/` | `<html lang="es">`, sin ningún enlace `/pt/`, sin cookie de wpLingua |
| Cookies de `br.muyunicos.com/pt/` | solo la sesión de la tienda y la cookie de variante de caché |

La causa ya está documentada en `MIGRATION-GUIDE.md` §7 ("LECCIÓN CLAVE"): wpLingua
reescribe la URL de la petición para resolver la página original y traduce el HTML
después. El modal de autenticación arma su "volver a donde estabas" leyendo esa URL
en el pie de página, cuando el idioma ya no está ahí: el destino que entrega al
proveedor de login social es la URL base. El mismo patrón afecta a la redirección
de clientes a "Mi Cuenta" después del login por usuario y contraseña, y a
cualquier destino que se construya leyendo la URL actual fuera del punto donde
wpLingua todavía la conserva.

Agrava el problema que la pantalla de acceso **no tiene ninguna señal de idioma**:
wpLingua no guarda estado en cookie ni en sesión (medido), así que esa pantalla se
sirve siempre en el idioma origen y el único indicio del idioma del visitante es el
que traiga la propia petición (destino pedido o procedencia).

Impacto: el comprador vuelve al catálogo del idioma equivocado, con textos y
navegación en español, en el momento exacto en que el sistema lo identifica.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - El comprador que entra con Google o Facebook vuelve a su idioma (Priority: P1)

Un comprador que navega en `/pt/` o `/en/` quiere entrar con su cuenta de Google o
de Facebook sin perder su ubicación. Hoy completa la autorización correctamente y
vuelve al sitio, pero aterriza en la base de su subdominio, en español.

**Why this priority**: es el defecto reportado y la única fuga que rompe el
recorrido de compra de forma visible: el comprador percibe que su idioma "se
reinició" justo al identificarse.

**Independent Test**: desde `br.muyunicos.com/pt/tienda/`, pulsar "Google",
completar la autorización real y comprobar que el navegador vuelve a
`https://br.muyunicos.com/pt/tienda/`. Se verifica sin tocar ninguna otra parte del
sitio y se repite igual en `/en/`.

**Acceptance Scenarios**:

1. **Given** un visitante no autenticado en `/pt/tienda/`, **When** completa el login con Google o con Facebook, **Then** vuelve a `/pt/tienda/` de su subdominio, en portugués.
2. **Given** un visitante no autenticado en una página de `/en/`, **When** completa el login social, **Then** vuelve a esa misma URL de su subdominio, en inglés.
3. **Given** un visitante en el dominio raíz o en un subdominio sin prefijo de idioma, **When** completa el login social, **Then** vuelve a la misma URL, sin prefijo agregado.
4. **Given** una página ya prefijada, **When** se inspecciona el enlace del botón antes de usarlo, **Then** el destino es el mismo host, la misma ruta y el mismo idioma desde donde se pidió: ni otro subdominio, ni el prefijo duplicado.
5. **Given** el mismo botón, **When** se compara su enlace antes y después del cambio, **Then** la forma del enlace (ruta de acceso, parámetro del proveedor y destino, en ese orden) no cambió y el proveedor lo sigue aceptando.

---

### User Story 2 - El comprador que entra con usuario y contraseña conserva el idioma (Priority: P1)

Un comprador que ya tiene cuenta entra con su correo y su contraseña. Según el punto
de entrada, hoy vive dos experiencias distintas: por el modal del sitio conserva el
idioma; por la pantalla de acceso de WordPress el sistema lo manda a "Mi Cuenta" en
el idioma origen.

**Why this priority**: el pedido incluye expresamente no romper este flujo, y una de
sus variantes ya está afectada por el mismo defecto. Se corrige con el mismo
criterio que la historia 1 y sin cambiar a dónde va hoy el comprador.

**Independent Test**: completar el login por usuario y contraseña en una página
`/pt/` y comprobar que la sesión continúa en portugués; repetir desde el dominio
raíz y comprobar que el destino es idéntico al de hoy.

**Acceptance Scenarios**:

1. **Given** un comprador no autenticado en una página con prefijo, **When** entra con usuario y contraseña desde el modal del sitio, **Then** la página se recarga en el mismo idioma (comportamiento actual, sin regresión).
2. **Given** una petición de login en la pantalla de acceso cuyo destino pedido o cuya procedencia es una URL con prefijo `/pt` o `/en`, **When** el comprador entra con usuario y contraseña, **Then** termina en "Mi Cuenta" de ese idioma, en su subdominio.
3. **Given** una petición de login en la pantalla de acceso **sin** ninguna señal de idioma, **When** el comprador entra, **Then** el destino es exactamente el de hoy: no se inventa un idioma ni se le asigna el del país.
4. **Given** un usuario con acceso al panel (`administrator`, `editor` o `shop_manager`), **When** inicia sesión, **Then** su destino sigue siendo el que él pidió, sin intervención.
5. **Given** el flujo de restablecer contraseña, **When** el comprador define una contraseña nueva, **Then** el flujo sigue funcionando y su destino respeta el mismo criterio de idioma que los escenarios anteriores.

---

### User Story 3 - Los flujos vecinos con el mismo defecto se resuelven o se declaran (Priority: P2)

Al medir aparecieron dos flujos con el mismo patrón: el enlace para **salir** del
menú de cuenta y los **redirects de la tienda** (abrir `/pt/finalizar-compra/` con
el carrito vacío responde `302` a `https://br.muyunicos.com/carrito/`, sin el
prefijo).

**Why this priority**: es el mismo defecto con otro disparador. No forman parte de
lo reportado y el segundo vive en código de la tienda, no del login, así que la
decisión de incluirlos o no es del mantenedor; la historia existe para que la
decisión quede escrita y no se resuelva por omisión.

**Independent Test**: cerrar sesión desde `/pt/` y observar el destino del enlace de
salida; repetir la medición del carrito vacío en `/pt/finalizar-compra/`.

**Acceptance Scenarios**:

1. **Given** el enlace "Salir" en una página `/pt/` o `/en/`, **When** el comprador cierra su sesión, **Then** se cumple lo que decida FR-013.
2. **Given** un redirect de la tienda disparado desde una URL con prefijo, **When** se ejecuta, **Then** se cumple lo que decida FR-014.
3. **Given** cualquiera de los dos flujos, **When** la decisión es dejarlo fuera, **Then** la medición queda registrada en la documentación operativa para que no se relea como un defecto nuevo.

### Edge Cases

- **URL sin prefijo** (dominio raíz y los subdominios que navegan en el idioma origen): el destino debe quedar igual que hoy, carácter por carácter.
- **Prefijo ya presente**: no se duplica (una ruta `/pt/...` no se convierte en `/pt/pt/...`).
- **Rutas técnicas** (`/wp-admin`, `/wp-json`, `/wp-login`, la ruta de acceso vigente, las llamadas AJAX y los archivos): nunca reciben prefijo.
- **Query string** (`?orderby=popularity`, `?_gl=1*abc`): se conserva tal como viaja hoy; el login no es un mecanismo de saneado de la query.
- **Host de destino**: el login social vuelve al mismo subdominio donde se pidió (garantizado en el trabajo anterior sobre login social y no debe degradarse).
- **Usuarios con acceso al panel**: sin cambios de destino.
- **Caché de página**: el enlace vive dentro de HTML cacheado; la corrección no se ve hasta purgar la caché del host correspondiente (LiteSpeed y CDN).
- **wpLingua desactivado**: sin prefijo detectable, el sitio sigue funcionando y el destino queda como hoy.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Al autenticarse, el comprador DEBE volver al mismo host, la misma ruta y el mismo idioma desde los que inició sesión.
- **FR-002**: El destino DEBE preservar el subdominio desde el que se pidió el login; no puede fijarse el dominio raíz.
- **FR-003**: El formato del enlace de login social NO DEBE cambiar: misma ruta de acceso, mismos parámetros y mismo orden, porque es contrato con el proveedor de identidad.
- **FR-004**: El login por usuario y contraseña DEBE llevar a "Mi Cuenta" en el idioma de la página desde la que se pidió el login **cuando esa información esté presente en la propia petición**.
- **FR-005**: Si la petición no trae ninguna señal de idioma, el sistema NO DEBE deducirlo por país, por geolocalización ni por el idioma del navegador.
- **FR-006**: Los roles `administrator`, `editor` y `shop_manager` NO DEBEN ver alterado su destino.
- **FR-007**: El prefijo de idioma NO DEBE duplicarse cuando la URL ya lo contiene.
- **FR-008**: Nada DEBE cambiar en el idioma origen ni en el dominio raíz.
- **FR-009**: Los tres métodos de acceso (Google, Facebook y usuario/contraseña) DEBEN seguir funcionando, sin errores del proveedor ni del filtrado del edge.
- **FR-010**: El idioma DEBE resolverse desde la fuente única de verdad que ya existe en el tema, sin listas de prefijos paralelas.
- **FR-011**: Ningún destino DEBE construirse leyendo una URL que wpLingua ya reescribió.
- **FR-012**: Las rutas técnicas y los destinos de terceros (pasarelas de pago, proveedores de identidad) NO DEBEN recibir prefijo.
- **FR-013**: **[NEEDS CLARIFICATION: enlace de salir — ¿se exige que conserve el idioma en esta feature, o se declara fuera de alcance con la medición documentada?]**
- **FR-014**: **[NEEDS CLARIFICATION: redirects de la tienda que pierden el prefijo (por ejemplo, el carrito vacío) — ¿se corrigen en esta feature o se registran como observación y se tratan aparte?]**

### Key Entities

- **Prefijo de idioma de la petición**: `''`, `/pt` o `/en`. Se deriva de la URL que el visitante pidió, con la lista de prefijos que el tema ya mantiene.
- **Destino de autenticación**: URL a la que vuelve el comprador. Atributos: host, ruta, idioma y query.
- **Enlace de login social**: URL hacia el proveedor de identidad. Atributos: ruta de acceso, proveedor y destino.
- **Página de cuenta**: "Mi Cuenta" del comprador. Atributos: ruta canónica y su versión por idioma.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100 % de los enlaces de login social en páginas `/pt/` y `/en/` llevan el prefijo de idioma (hoy: 0 %).
- **SC-002**: 0 cambios en las URLs del dominio raíz y de las páginas sin prefijo, comprobado comparando los enlaces antes y después del cambio.
- **SC-003**: 0 enlaces de login social que cambien de formato o de host respecto de hoy.
- **SC-004**: El flujo real de autorización con Google y con Facebook desde `/pt/` y desde `/en/` se completa y vuelve al idioma de origen, verificado por una persona.
- **SC-005**: 0 regresiones en el acceso por usuario y contraseña: modal del sitio, "Mi Cuenta", pantalla de acceso y restablecer contraseña.
- **SC-006**: 0 rutas técnicas alcanzadas por el prefijo y 0 destinos de terceros modificados.
- **SC-007**: 0 plugins, 0 datos y 0 archivos del tema padre tocados (verificable con la lista de archivos modificados del cambio).

## Assumptions

- wpLingua reescribe la URL de la petición y traduce el HTML después: es un hecho ya documentado en la guía operativa y vuelto a medir aquí.
- El idioma de wpLingua vive **solo en la URL**: sin prefijo no hay idioma, y la pantalla de acceso se sirve siempre en el idioma origen.
- La captura de la URL original que el tema ya hace para el 301 canónico es la fuente correcta del prefijo, y la lista de prefijos por país sigue siendo la fuente única de esa lista.
- El proveedor de login social valida el destino contra el mismo sitio: el prefijo no cambia de host, así que el destino sigue siendo aceptado.
- Producción es el único entorno: la corrección se verifica con mediciones y con un login real hecho por el mantenedor.
- El HTML de las páginas afectadas puede estar cacheado por host: la verificación exige purgar la caché antes de medir.

## Fuera de alcance (declarado)

- Traducir o rediseñar la pantalla de acceso: allí no existe contexto de idioma y lograrlo exige configuración del plugin de traducción, no código del tema.
- Cambiar la configuración de wpLingua, del proveedor de login social, o la regla vigente que manda a los clientes a "Mi Cuenta".
- Reconfigurar LiteSpeed, la CDN de Hostinger o el bloqueo por país del edge.
- El 301 canónico de la home con prefijo: ya resuelto en el módulo de compatibilidad con wpLingua.
- Agregar idiomas nuevos (hoy: `es`, `pt`, `en`).
- Migrar el flujo de autenticación a otro mecanismo o agregar proveedores de login.
