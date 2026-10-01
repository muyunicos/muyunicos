# Feature Specification: Actualizar MIGRATION-GUIDE.md tras el cambio de CDN

**Feature Branch**: `001-update-migration-guide`

**Created**: 2026-10-01
**Status**: Draft
**Input**: User description: "para actualizar MIGRATION-GUIDE.md"

## Problema

El proyecto cambió de proveedor de CDN y activó nuevas capas de infraestructura,
pero `MIGRATION-GUIDE.md` sigue documentando el estado anterior. La constitución
v2.0.0 ya es agnóstica del proveedor y apunta al guide §2 como fuente de verdad
del perímetro de red: hoy esa referencia apunta a información que es falsa.

Un agente que lea el guide antes de trabajar en caché aplicaría reglas de un
proveedor retirado, buscaría una integración de API que ya no existe, y
diagnosticaría con comandos que no reproducen el comportamiento actual.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Un agente no aplica reglas de un proveedor retirado (Priority: P1)

Un agente de IA abre `MIGRATION-GUIDE.md` antes de tocar la capa de caché, como
manda la directiva del documento. Hoy encuentra que un proveedor retirado es
"la ÚNICA CDN activa", que existe un token de API integrado en LiteSpeed y que
hay reglas de bloqueo por lista de rechazados de cuatro países. Con la
infraestructura real, las tres cosas son falsas: aplicar cualquiera de ellas no
lleva a ningún lado.

**Why this priority**: es el escenario de mayor daño. Un agente que siga la guía
literalmente configurará un token que no existe o asumirá un modelo de bloqueo
invertido. El error no es visible hasta que el comportamiento real difiere del
documento.

**Independent Test**: buscar en el guide cualquier mención del proveedor de CDN
retirado. Si aparece como regla vigente en lugar de histórico, el story falla.

**Acceptance Scenarios**:

1. **Given** el guide en su estado actual, **When** se busca la configuración de
   CDN, **Then** describe la CDN vigente y el proveedor anterior no aparece como
   regla aplicable.
2. **Given** el modelo de bloqueo geográfico, **When** se lee la regla, **Then**
   describe una lista de permitidos (se bloquea todo lo que no está en la lista),
   que es el comportamiento real.
3. **Given** la integración de purga de caché, **When** se busca un token de API
   externo, **Then** no se documenta como vigente porque esa integración ya no
   existe.

---

### User Story 2 - Un agente puede diagnosticar un fallo de caché (Priority: P1)

Un agente investiga por qué una página no se cachea. El guide §7 contiene los
comandos `curl` de verificación de cada incidente. Necesita que esos comandos
reproduzcan el comportamiento actual y que describan la pila vigente.

**Why this priority**: sin comandos verificables, el principio IX de la
constitución (verificación reproducible) es inejecutable. Cada incidente
documentado sin comando vigente es una hipótesis, no un diagnóstico.

**Independent Test**: tomar un incidente documentado, ejecutar su comando de
verificación contra un subdominio y comprobar que la respuesta esperada sigue
siendo la que el guide predice.

**Acceptance Scenarios**:

1. **Given** un incidente documentado en §7, **When** se ejecuta su comando de
   verificación, **Then** la respuesta observada coincide con la predicha.
2. **Given** la verificación de caché por subdominio, **When** se consulta la
   segunda respuesta, **Then** el guide describe la cabecera de caché que la
   pila vigente emite.
3. **Given** la pila de object cache, **When** se busca su configuración,
   **Then** el guide distingue el cacheo de datos del cacheo de páginas, porque
   no son la misma capa y confundirlas lleva a diagnóstico erróneo.

---

### User Story 3 - Un agente sabe qué territorio es del plugin y qué del tema (Priority: P2)

Un agente va a integrar comportamiento que cruza la frontera del tema y un
plugin. El principio X de la constitución delimita esa frontera, pero el guide no
documenta qué plugins tienen acoplamiento real con el tema ni cómo se enganchan.

**Why this priority**: el principio X es norma nueva y no tiene respaldo
documental. Sin el inventario de acoplamiento, un agente no puede saber si debe
escribir un override en el tema o consumir una API del plugin.

**Independent Test**: elegir un comportamiento de la lista y verificar que el
guide indica el mecanismo de integración sin necesidad de leer el código fuente.

**Acceptance Scenarios**:

1. **Given** un plugin con acoplamiento al tema, **When** se busca en el guide,
   **Then** aparece con el hook, filtro o API por el que el tema lo consume.
2. **Given** un plugin sin acoplamiento, **When** el agente busca su
   documentación, **Then** el guide no lo lista, porque listarlo le sugeriría que
   existe una integración que revisar.
3. **Given** el nombre del módulo de caché, **When** el agente navega el árbol
   de directorios, **Then** el nombre coincide con lo que el módulo hace hoy.

---

### User Story 4 - El mantenedor audita el stack vivo (Priority: P2)

El mantenedor necesita revisar en un solo lugar qué cambió respecto del
documento: versión del stack, capas de seguridad perimetral y protocolo de
transporte.

**Why this priority**: es la vista de conjunto que hace confiable el resto del
documento. Sin ella, cada sección se corrige por separado y el documento se
desincroniza de nuevo.

**Independent Test**: comparar la sección de infraestructura del guide contra el
panel de hosting y contra la lista de plugins activos, y confirmar que no queda
ninguna discrepancia.

**Acceptance Scenarios**:

1. **Given** el stack activo, **When** el mantenedor lee §2, **Then** las
   versiones y capas de caché son las vigentes.
2. **Given** el perímetro de red, **When** lee §2, **Then** están documentadas la
   CDN por subdominio, su nivel de seguridad, el bloqueo por país y la versión
   de TLS.
3. **Given** una recomendación de mitigación que no está configurada, **When**
   el mantenedor la busca, **Then** figura como pendiente de configurar y no
   como capacidad disponible.

### User Story 5 - El código que vive fuera del tema está declarado (Priority: P2)

Un agente busca la Calculadora de Stickers en `inc/` y no la encuentra: está
ejecutándose desde código alojado fuera del repositorio. Hoy no hay ningún
documento que diga que existe, dónde vive ni de dónde viene. El agente concluirá
que la funcionalidad no existe, o peor, la reconstruirá desde cero.

**Why this priority**: es el escenario que convierte una funcionalidad viva en
un bug fantasma. El código existe y funciona, pero es invisible para todo el
que trabaje sobre el repositorio versionado.

**Independent Test**: enumerar el código que el sitio ejecuta fuera del tema y
comprobar que cada elemento aparece en el guide con su propósito y su fuente.

**Acceptance Scenarios**:

1. **Given** una funcionalidad que se ejecuta fuera del tema, **When** un agente
   la busca en el árbol del repositorio y no la encuentra, **Then** el guide le
   indica que existe, qué hace y dónde está su código.
2. **Given** un fragmento que vive fuera del tema, **When** se lee su entrada en
   el inventario, **Then** dice qué es lo que hace y cuál es su fuente, para que
   un agente pueda ir a leerlo.
3. **Given** código externo que declara nombres con el prefijo del tema,
   **When** se documenta, **Then** se advierte su riesgo de colisión y la forma
   de evitar un fallo fatal por función redeclarada.

---

### User Story 6 - La Calculadora de Stickers es reproducible (Priority: P3)

Un agente o el mantenedor necesita corregir la Calculadora. El bundle compilado
que se sirve en producción existe, pero el guide no dice de dónde sale ni cómo
se regenera, así que la única fuente posible parece ser el servidor de producción.

**Why this priority**: sin el flujo de compilación documentado, cualquier
corrección a la Calculadora obliga a improvisar sobre el servidor, que es
precisamente lo que el principio IX prohíbe.

**Independent Test**: seguir las instrucciones de compilación del guide y
verificar que el resultado reproduce el bundle que se sirve.

**Acceptance Scenarios**:

1. **Given** la necesidad de cambiar la Calculadora, **When** se lee el guide,
   **Then** indica que el código fuente vive en un repositorio aparte y cuál es
   su comando de compilación.
2. **Given** una compilación correcta, **When** el bundle resultante se coloca en
   el tema, **Then** el guide explica el destino de cada archivo generado, para
   que se sepa qué hay que subir al servidor.
3. **Given** el endpoint de guardado de la configuración, **When** se documenta,
   **Then** se explica por qué vive dentro de los assets y qué lo protege, para
   que nadie lo lea como un endpoint desprotegido.

---
---

### Edge Cases

- **Búsqueda del proveedor anterior**: un agente que busque el nombre del
  proveedor retirado debe encontrar, como máximo, una mención que explique que
  fue retirado. No debe poder seguir una instrucción a partir de ella.
- **Rollback parcial**: si el cambio de código del módulo se revierte pero el
  guide no, el guide describe un nombre que ya no existe en el repositorio.
- **Documento y código en el mismo cambio**: si el rename del módulo y la
  actualización del guide se despliegan juntos, un fallo obliga a revertir
  ambos; el orden de cambio debe hacer el fallo atribuible.
- **Plugin retirado**: si un plugin acoplado se desactiva, el guide debe quedar
  marcado como pendiente de actualizar, no seguir afirmando que existe la
  integración.
- **Comando de verificación dependiente del proveedor**: un comando que
  verificaría una cabecera del proveedor anterior debe reescribirse contra la
  pila vigente o declararse no aplicable.
- **Documentación histórica**: las lecciones de los incidentes siguen siendo
  válidas aunque el proveedor haya cambiado; solo cambia el comando de
  verificación, no el aprendizaje.
- **Colisión de nombres**: el código externo y el tema declaran entidades con el
  mismo prefijo. Si ambos se cargan a la vez, PHP aborta con un error fatal por
  función redeclarada, y ninguno de los dos lados tiene guardas que lo impidan.
- **Fuente desincronizada**: el bundle compilado que se sirve y el código fuente
  del que debería generarse pueden divergir si alguien sube el bundle sin
  recompilar. El guide debe indicar cuál manda.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El guide MUST describir la pila de caché vigente distinguiendo el
  cacheo de datos del cacheo de páginas, porque son capas distintas con
  consecuencias distintas.
- **FR-002**: El guide MUST identificar un único proveedor de CDN activo,
  configurado por subdominio, con su nivel de seguridad.
- **FR-003**: El guide MUST describir el bloqueo geográfico como lista de
  permitidos por país.
- **FR-004**: El guide MUST documentar la versión de TLS activa.
- **FR-005**: El guide MUST NOT presentar como vigente ninguna integración,
  token, cabecera o regla del proveedor de CDN retirado.
- **FR-006**: Cada incidente documentado MUST conservar el aprendizaje que lo
  originó y su comando de verificación; si el comando dependía del proveedor
  retirado, MUST reemplazarse por uno vigente o marcarse no aplicable con la
  razón.
- **FR-007**: El guide MUST listar los plugins con acoplamiento real al tema,
  indicando el hook, filtro o API mediante el cual el tema los consume.
- **FR-008**: El guide MUST NOT listar plugins sin acoplamiento en la sección de
  integración, para no sugerir una integración que no existe.
- **FR-009**: El nombre del módulo de bypass de caché MUST describir lo que el
  módulo hace, y su documentación interna MUST describir únicamente el
  comportamiento vigente del módulo.
- **FR-010**: Las referencias al nombre del módulo MUST ser coherentes en todo
  el repositorio, incluidos el cargador de módulos y el árbol de directorios del
  guide.
- **FR-011**: Toda mitigación recomendada que no esté configurada MUST figurar en
  la sección de deuda técnica como pendiente, conservando el criterio técnico que
  la justifica.
- **FR-012**: El guide MUST NOT contener ninguna directiva que entre en conflicto
  con la constitución vigente; ante conflicto, la constitución prevalece y el
  guide se corrige.
- **FR-013**: El guide MUST inventariar el código que el sitio ejecuta fuera del
  tema, indicando qué hace, dónde reside y cuál es su fuente, de modo que la
  revisión pueda auditarlo aunque no esté en el repositorio.
- **FR-014**: El guide MUST documentar que la Calculadora de Stickers se compila
  desde un repositorio de fuente aparte, MUST publicar el comando de compilación y
  MUST indicar el destino de cada archivo generado dentro del tema.
- **FR-015**: Las entidades con el prefijo del tema que se declaran fuera del
  repositorio MUST documentar su riesgo de colisión con el tema y la guarda que
  evita un error fatal por función redeclarada.

### Key Entities

- **Capa de caché**: Dos capas con función distinta: el cacheo de datos en
  memoria y el cacheo de páginas por subdominio. Atributos: qué almacena, quién
  lo consume, cómo se verifica.
- **Perímetro de red**: Configuración del borde que queda fuera del
  repositorio. Atributos: proveedor de CDN, granularidad, control de tráfico
  geográfico, protocolo de transporte.
- **Plugin acoplado**: Plugin con el que el tema interactúa por hook, filtro o
  API. Atributos: nombre, punto de integración, qué comportamiento aporta.
- **Incidente**: Evento documentado que originó una mitigación. Atributos:
  síntoma, causa raíz, mitigación, comando de verificación vigente.
- **Deuda técnica**: Trabajo pendiente conocido, con su justificación.
- **Código externo**: Fragmento de código que el sitio ejecuta y que no reside
  en el repositorio versionado. Atributos: propósito, ubicación, fuente, riesgo de
  colisión con el tema.
- **Artefacto compilado**: Archivo generado a partir de una fuente externa y
  servido por el tema. Atributos: origen, comando de compilación, destino dentro
  del tema.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Cero menciones del proveedor de CDN retirado en las secciones de
  configuración e incidentes del guide. Verificable con una búsqueda de texto.
- **SC-002**: El bloque de caché nombra las dos capas de caché vigentes, el
  proveedor de CDN activo, el control geográfico y la versión de TLS.
- **SC-003**: Cero referencias en todo el repositorio al nombre anterior del
  módulo de bypass de caché.
- **SC-004**: Todos los plugins acoplados aparecen en el guide con su punto de
  integración, y la lista es verificable contra el código del tema.
- **SC-005**: El 100% de los incidentes documentados conservan un comando de
  verificación ejecutable contra la pila vigente, o están marcados como no
  aplicables con la razón.
- **SC-006**: Un mantenedor puede responder "¿qué cambió respecto del documento?"
  sin acceso al panel, leyendo únicamente §2.
- **SC-007**: Las mitigaciones no configuradas aparecen en la sección de deuda
  técnica, ninguna en las secciones normativas.
- **SC-008**: La verificación de caché por subdominio predice la respuesta que
  devuelve la pila vigente en la segunda petición.
- **SC-009**: El 100% del código que el sitio ejecuta fuera del tema figura en el
  inventario del guide, con propósito y fuente. Verificable enumerando lo
  declarado frente a lo declarado en la plataforma.
- **SC-010**: El guide permite regenerar el bundle de la Calculadora siguiendo sus
  instrucciones, sin necesidad de consultar el servidor de producción.
- **SC-011**: Un lector del guide puede determinar, sin leer código, qué entidades
  externas comparten prefijo con el tema y qué ocurre si se cargan a la vez.

## Assumptions

- El proyecto se mantiene en un único proveedor de CDN: la configuración actual
  usa una CDN por subdominio y no habrá coexistencia de proveedores a corto plazo.
- El bloqueo geográfico es una lista de permitidos: el comportamiento declarado es
  bloquear todo tráfico que no provenga de un país habilitado, y así se documenta.
- Solo se documentan los plugins con acoplamiento real al tema: Rank Math,
  wpLingua, WCPBC, Mercado Pago, PayPal, Hostinger Tools, Jetpack, LiteSpeed
  Cache y Nextend. El inventario completo de la pila de plugins no forma parte de
  este documento, porque no aporta información de integración y envejece sin
  valor operativo.
- El inventario no registra versiones de plugins: la versión es dato de
  plataforma, no de integración, y una línea con versiones caduca sin aportar.
- El rename del módulo de caché, sus funciones y su documentación interna entra
  en el mismo alcance que la actualización del guide, según decisión del
  mantenedor. Consecuencia asumida: el cambio mezcla documentación y código, de
  modo que un fallo obliga a revertir ambos. El orden de cambio debe ser código
  primero y guide después, para que un fallo sea atribuible a una de las dos
  partes.
- Las lecciones de los incidentes se conservan íntegras aunque cambie el
  proveedor: solo se actualiza el comando de verificación cuando dependía del
  proveedor retirado.
- Las dos recomendaciones de mitigación pendientes de configurar (desafío a
  peticiones con combinaciones de etiquetas y excepción para el rastreador de
  previews de redes sociales) se trasladan a la sección de deuda técnica
  conservando su criterio técnico.
- La constitución v2.0.0 es la norma vigente y ya no nombra al proveedor
  retirado; el guide debe quedar alineado con ella, no al revés.
- El repositorio de la Calculadora de Stickers es la fuente de verdad de su
  código: el guide documenta el flujo de compilación y su destino dentro del
  tema, no replica el código ni sus reglas internas.
- El inventario de código externo describe lo que existe y por qué vive fuera
  del tema. Documentar no implica migrar, reevaluar ni retirar ese código: su
  ubicación se mantiene tal como está.
- El código externo mantiene sus propias convenciones porque no lo gobierna el
  estilo del tema; el guide documenta su ubicación, no reescribe su código.
- El inventario no publica el contenido del código externo, solo su propósito y
  su ubicación: documentarlo es hacerlo auditable, no Incorporarlo al
  repositorio.
- La verificación de esta spec se realiza contra los subdominios de producción,
  ya que el proyecto no tiene entorno de pruebas.

## Out of Scope

- Modificar la constitución: ya es agnóstica del proveedor de CDN.
- Implementar las mitigaciones pendientes de configurar: solo se documentan
  como deuda técnica.
- Migrar, reescribir o retirar el código que vive fuera del tema: el inventario
  lo hace auditable, no lo traslada. Su ubicación se mantiene.
- Incorporar al repositorio el código que hoy se ejecuta fuera del tema.
- Compilar o redistribuir el bundle de la Calculadora: la spec documenta el
  flujo, no lo ejecuta.
- Corregir hallazgos de código ajenos al módulo renombrado, como el icono SVG
  embebido en un archivo de autenticación o las URLs de acceso construidas a
  mano: son defectos preexistentes y se tratan por separado.
- Revisar si dos plugins antispam activos se solapan.
- Cualquier configuración en el panel de hosting: el guide la documenta, no la
  aplica.
