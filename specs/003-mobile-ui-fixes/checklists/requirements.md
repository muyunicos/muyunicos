# Specification Quality Checklist: Correcciones y mejoras de la interfaz en el móvil
**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-10-02
**Feature**: [spec.md](../spec.md)
## Content Quality
- [x] CHK001 No implementation details (languages, frameworks, APIs)
- [x] CHK002 Focused on user value and business needs
- [x] CHK003 Written for non-technical stakeholders
- [x] CHK004 All mandatory sections completed
## Requirement Completeness
- [x] CHK005 No [NEEDS CLARIFICATION] markers remain
- [x] CHK006 Requirements are testable and unambiguous
- [x] CHK007 Success criteria are measurable
- [x] CHK008 Success criteria are technology-agnostic (no implementation details)
- [x] CHK009 All acceptance scenarios are defined
- [x] CHK010 Edge cases are identified
- [x] CHK011 Scope is clearly bounded
- [x] CHK012 Dependencies and assumptions identified
## Feature Readiness
- [x] CHK013 All functional requirements have clear acceptance criteria
- [x] CHK014 User scenarios cover primary flows
- [x] CHK015 Feature meets measurable outcomes defined in Success Criteria
- [x] CHK016 No implementation details leak into specification
## Notas de validación
### Iteración 1 — redacción inicial
Dos requisitos fallaron CHK006 por ambigüedad, y se corrigieron antes de dar la
validación por buena:
- **FR-020** decía que la animación del botón de contacto se ejecutaba "sin bloquear
  la respuesta del sitio" y que la página respondía "con fluidez". "Con fluidez" no es
  un criterio: dos personas pueden discrepar y ambas tener razón. Reescrito como
  comportamiento comprobable: mientras la animación corre, el desplazamiento de la
  página y la pulsación de cualquier otro control **siguen respondiendo**. Eso se
  verifica desplazando la página con la animación en curso y pulsando el carrito.
- **FR-021** ofrecía dos salidas ("no se ejecuta, **o** se reduce a un cambio de estado
  simple"), de modo que un implementador podía dar por cumplido el requisito con
  cualquiera de las dos. Una disyunción así no es testeable. Reescrito como un solo
### Detalle por ítem
- **CHK001 / CHK008 / CHK016 — sin detalles de implementación.** La spec no nombra
  archivos, clases, funciones, propiedades CSS ni atributos HTML. Los FR expresan qué
  debe cumplirse ("el estado abierto es observable por el estilo de la página y por el
  atributo de estado accesible"), no cómo escribirlo. El texto original del mantenedor
  sí menciona nombres técnicos, y se conserva **solo** dentro del bloque `Input`, que
  es la cita textual del pedido y no una especificación. Los SC son resultados
  observables: "un toque en la bandera muestra la lista de países en menos de un
  segundo", "ninguna pantalla requiere desplazamiento horizontal a 320 píxeles".
- **CHK002 / CHK003 — valor y audiencia.** El usuario es el comprador que navega
  desde el teléfono y el mantenedor que verifica. Ningún ítem requiere saber
  programación: la verificación es con el dedo sobre la pantalla y con un medidor de
  contraste.
- **CHK004 — secciones obligatorias.** User Scenarios & Testing (4 historias),
  Requirements (34 FR), Key Entities (5) y Success Criteria (15 SC) completadas.
  Edge Cases con 10 casos. Se agregaron Problema y Out of Scope, que son secciones
  opcionales pero necesarias aquí: el problema es visible y el alcance es el riesgo
  real de esta feature.
- **CHK005 — sin aclaraciones pendientes.** Cero marcadores. Las tres decisiones que
  podrían haber requerido aclaración se resolvieron con supuestos razonables y
  quedan escritos en Assumptions, no enterrados: el corte de tamaño de pantalla, la
  referencia visual del botón de contacto, y el hecho de que el comportamiento de
  escritorio se preserva.
- **CHK006 / CHK013 — requisitos testeables.** Cada FR tiene un criterio comprobable:
  FR-001 a FR-009 con una acción y un resultado observable; FR-010 a FR-016 con un
  medidor de contraste y un área táctil medida; FR-018 a FR-025 con estados
  comprobables al pasar el dedo y con la preferencia de reducir movimiento activada en
  el sistema; FR-026 a FR-031 con los anchos de pantalla donde se prueba y con una
  comprobación de que los estilos no aparecen fuera de la sección de cuenta; FR-032 a
  FR-034 con comparación antes/después en escritorio.
- **CHK007 / CHK015 — SC medibles.** SC-003 y SC-005 llevan cifra (4.5:1, 44×44).
  SC-001, SC-007, SC-008, SC-009 y SC-013 llevan cantidad o ancho concreto. SC-011 y
  SC-012 son verificables por búsqueda de texto y comparación de peso. SC-014 y SC-015
  son las guardas de no-regresión que corresponden a un proyecto sin entorno de
  pruebas: si algo se rompe, no se descubre hasta producción.
- **CHK009 — escenarios de aceptación.** 21 escenarios en 4 historias, todos en
  formato Given/When/Then. Cada historia tiene al menos un escenario para el caso sin
  sesión iniciada o sin autenticación, porque los cuatro componentes cambian de
  comportamiento según haya sesión.
- **CHK010 — casos límite.** 10 casos. Los tres que gobiernan el cambio: apertura
  doble (el fallo reportado), rotación y punto exacto de cambio de tamaño (donde un
  panel abierto puede quedar en un estado intermedio), y teclado en pantalla (reduce a
  la mitad el área útil y es el escenario real de quien está editando su email).
- **CHK011 — alcance acotado.** Out of Scope separa lo que se hace de lo que no:
  rediseño de escritorio, cambio de canal de contacto, integración con otras
  plataformas, cambios de contenido o URLs en la sección de cuenta, rediseño de
  carrito y checkout, y cualquier ajuste de servidor o caché.
- **CHK012 — supuestos documentados.** 9 supuestos, cada uno con su razón. Los tres
  que conviene explicitar en la fase de plan: el corte de 768 píxeles ya existe en el
  sitio y no se redefine; el efecto pedido para el botón se toma como **referencia**
  visual y no implica integrar nada de Facebook; y la verificación es manual porque
  el proyecto no tiene entorno de pruebas.
  comportamiento: con la preferencia de reducir movimiento activa, no hay animación
  continua ni repetida, y solo quedan los estados de reposo, acercamiento y
  pulsación. El escenario 4 de la historia 4 se alineó con la misma redacción.
### Punto de riesgo registrado
La spec agrupa cuatro correcciones que parecen independientes porque las reportó el
mantenedor en cuatro momentos distintos. Se mantienen juntas por dos razones
verificadas en el código, no por conveniencia: los tres primeros componentes comparten
los mismos archivos de encabezado y de UI global, y los tres se tocan en el mismo
punto de la pantalla angosta. Separarlas en cuatro features obligaría a editar esos
archivos cuatro veces y a verificar la pantalla angosta cuatro veces, con el riesgo de
que una corrección deshaga el apilado de otra.
Se deja constancia de dos cosas que la spec **no** promete, para que no se lean como
prometidas:
- El criterio SC-011 ("el sitio no pesa más fuera de la sección de cuenta") depende de
  que los estilos de cuenta se carguen solo en páginas de cuenta. Esa regla ya está
  enunciada como FR-031 y es verificable, pero la medición del peso es del
  mantenedor, porque la variación de caché de producción puede ser mayor que el
  peso del cambio.
- El orden de las historias (P1 selector de país, P2 submenú, P3 pantallas de cuenta,
  P4 botón de WhatsApp) refleja impacto sobre el comprador, no dificultad técnica. La
  historia 4 es la más barata de las cuatro y la 3 es la más larga. Si el mantenedor
  prefiere avanzar por costo, esa decisión es suya y no cambia el alcance.
### Preguntas que la fase de plan debe responder

Estas tres decisiones no bloquean la especificación (por eso no son marcadores de
aclaración), pero la fase de plan tiene que fijar una respuesta explícita y
consignarla:
1. Si la navegación lateral de la sección de cuenta se reordena en móvil o se
   mantiene como lista vertical. La spec solo exige que sea alcanzable y legible
   (FR-028), y las dos opciones lo cumplen.
2. Si la etiqueta de texto del botón de contacto aparece siempre en móvil o solo al
   rozarlo. FR-018 pide que "comunique que es un canal de contacto", y las dos
   opciones lo cumplen.
3. Si el arreglo de la apertura del selector de país se resuelve solo con el
   comportamiento correcto o si además cambia su posición en la barra. La spec exige
   que no tape (FR-008) y que responda (FR-001), no dónde se ubica.

### Resultado

Los 16 ítems del checklist pasan en la iteración 1 tras corregir FR-020 y FR-021, que
son las dos únicas ambigüedades detectadas. No hace falta una segunda iteración.

Pendiente de la fase de plan, no de esta spec: las tres decisiones listadas arriba y la
verificación en un teléfono real, que en este proyecto no puede automatizarse.

