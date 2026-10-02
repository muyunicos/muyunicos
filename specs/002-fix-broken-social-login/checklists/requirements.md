# Specification Quality Checklist: Restaurar el login social y eliminar los SVG inline

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

Los 16 ítems pasan. Detalle por ítem:

- **CHK001 / CHK008 / CHK016 — sin detalles de implementación.** Los FR
  expresan qué debe cumplirse ("el tema obtiene la ruta mediante la API de
  WordPress"), no cómo escribirlo. El nombre del filtro o de la función no
  aparece en la spec: es detalle de la fase de plan. Los SC se expresan como
  resultados observables ("pulsar el botón lleva al proveedor, no a un error").
- **CHK002 / CHK003 — valor y audiencia.** El usuario es el comprador que
  quiere entrar con su cuenta y el mantenedor que mantiene el tema. Ningún ítem
  requiere saber PHP.
- **CHK004 — secciones obligatorias.** User Scenarios, Requirements, Success
  Criteria y Assumptions completadas. Key Entities incluida por haber entidades
  de comportamiento. Edge Cases con 6 casos.
- **CHK005 — sin aclaraciones pendientes.** Cero marcadores. La ruta de login
  vigente se verificó empíricamente contra producción antes de redactar.
- **CHK006 / CHK013 — requisitos testeables.** Cada FR tiene un criterio
  comprobable: FR-001, FR-002, FR-003 y FR-004 con búsqueda de texto en el
  código; FR-005 y FR-006 con un flujo de autenticación real; FR-007 a FR-010
  con búsqueda de SVG y comparación visual.
- **CHK007 — SC medibles.** SC-001 a SC-008 son verificables por acción
  (pulsar un botón), por búsqueda (cero referencias) o por comparación
  (iconos idénticos).
- **CHK009 — escenarios de aceptación.** 7 escenarios en 2 stories, en formato
  Given/When/Then.
- **CHK010 — casos límite.** 6 casos. Los tres que gobiernan el cambio: cambio
  futuro de la ruta, desactivación del plugin que la oculta, y preservación del
  formato de la URL que el proveedor ya acepta.
- **CHK011 — alcance acotado.** Out of Scope separa lo que se hace de lo que no:
  migrar de proveedor, reconfigurar paneles, cambiar el diseño del modal, y
  limpiar los SVG del resto del tema.
- **CHK012 — supuestos documentados.** 8 supuestos con su razón, incluido el
  más delicado: el formato de la URL que el proveedor ya acepta y que el fix
  debe reproducir sin cambiar.

### Punto de riesgo registrado

La spec cubre dos cambios (URL de login e iconos) porque tocan el mismo archivo.
Si se separaran en dos features, el modal de autenticación se editaría dos veces
por dos motivos distintos, y el segundo cambio volvería a abrir un archivo ya
modificado. Se asume explícitamente en Out of Scope que el resto de los SVG
inline del tema no se tocan.

El hallazgo de que existen 31 SVG inline en 6 archivos del tema, y no solo en el
modal, se documenta en la sección Out of Scope para que no se interprete como
que el problema está resuelto en todo el proyecto.
