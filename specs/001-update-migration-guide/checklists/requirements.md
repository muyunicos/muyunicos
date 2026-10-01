# Specification Quality Checklist: Actualizar MIGRATION-GUIDE.md tras el cambio de CDN

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-10-01
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

Los 16 ítems pasaron. Las tres decisiones que bloqueaban el alcance se
resolvieron con el mantenedor antes de redactar: granularidad del inventario de
plugins (solo los acoplados), alcance del rename (incluido) y destino de las
reglas de mitigación pendientes (deuda técnica). Quedan registradas en
Assumptions, no como marcadores de aclaración.

Detalle por ítem:

- **CHK001 / CHK008 / CHK016 — sin detalles de implementación.** Los FR
  expresan qué información debe contener el documento, no cómo escribirla ni con
  qué lenguaje. Las menciones técnicas son subjects del documento (la CDN, el
  bloqueo, la versión de TLS), no decisiones de implementación. Los SC se
  expresan como resultados observables: cero menciones de un proveedor, cero
  referencias a un nombre anterior, el 100% de incidentes con comando vigente.
- **CHK002 / CHK003 — valor y audiencia.** El usuario es el agente que entra al
  repositorio y el mantenedor que audita el stack. Los seis stories describen
  decisiones que se toman con el documento abierto, no tareas técnicas.
- **CHK004 — secciones obligatorias.** User Scenarios, Requirements, Success
  Criteria y Assumptions completadas. Key Entities incluida por haber entidades
  de configuración. Edge Cases cubierta con 8 casos.
- **CHK005 — sin aclaraciones pendientes.** Cero marcadores en el documento.
- **CHK006 / CHK013 — requisitos testeables.** Cada FR tiene un criterio
  verificable: FR-001, FR-009, FR-013 y FR-014 se comprueban leyendo el
  documento; FR-005 y FR-010 con búsqueda de texto en el repositorio; FR-007
  contra el código del tema; FR-011 contra la sección de deuda técnica; FR-015
  contra las entidades con prefijo del tema.
- **CHK007 — SC medibles.** SC-001 a SC-011 son conteos o proporciones
  comprobables sin criterio subjetivo.
- **CHK009 — escenarios de aceptación.** 18 escenarios distribuidos en 6 stories,
  cada uno en formato Given/When/Then.
- **CHK010 — casos límite.** 8 casos. Los dos que gobiernan este cambio son el
  rollback parcial y el orden código-primero cuando el guide y el código se
  despliegan juntos.
- **CHK011 — alcance acotado.** Out of Scope separa lo que la spec hace de lo que
  no: enmendar la constitución, implementar mitigaciones, migrar o reescribir el
  código externo, compilar el bundle, corregir defectos preexistentes ajenos al
  módulo renombrado y tocar el panel de hosting.
- **CHK012 — supuestos documentados.** 13 supuestos en Assumptions, cada uno con
  su razón y su consecuencia.

### Iteración 2 — ampliación tras la auditoría del código externo

Se añadieron US5, US6, FR-013 a FR-015, SC-009 a SC-011, dos entidades y dos
casos límite. Se revalidaron los 16 ítems: todos siguen pasando. La ampliación no
introduce detalle de implementación, ni marcadores de aclaración, ni alcance sin
acotar. Detalle de los ítems afectados:

- **CHK010 — casos límite.** Se añadieron la colisión de nombres entre el código
  externo y el tema, que aborta PHP por función redeclarada, y la
  desincronización entre el bundle servido y su fuente.
- **CHK011 — alcance acotado.** Se añadió explícitamente que documentar el código
  externo no implica migrarlo ni retirarlo, para que la spec no se lea como una
  autorización de traslado.
- **CHK012 — supuestos documentados.** Se añadió que el repositorio de la
  Calculadora es la fuente de verdad del bundle y que el guide documenta el flujo
  de compilación, no el código.

## Notes

- Items marked incomplete require spec updates before `/speckit-clarify` or `/speckit-plan`
- Riesgo asumido y documentado: el alcance mezcla documentación y código, porque
  el rename del módulo entra junto con la actualización del guide. El orden de
  cambio código-primero está fijado en Assumptions para que un fallo sea
  atribuible a una de las dos partes.
- La constitución se enmendó a v2.1.0 en el mismo trabajo: la prohibición total del
  código externo pasó a requisito de declaración. Sin esa enmienda, el guide
  documentaría un estado que la constitución prohíbe.
- El repositorio de la Calculadora tiene cambios sin commitear, incluido el
  bundle compilado. Mientras no se commiteen, el flujo documentado en el guide no
  produce un artefacto versionado.
