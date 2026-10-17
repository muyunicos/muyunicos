# Specification Quality Checklist: Traducción con IA en contexto de tienda

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-10-16
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

### Iteración 1 — redacción inicial (2026-10-16)

- **CHK001 / CHK008 / CHK016 — sin detalles de implementación.** Los FR y los SC
  describen comportamiento y resultado ("el campo del idioma activo se completa",
  "0 datos de clientes enviados"), no archivos, funciones ni parámetros. La sección
  Problema cita la medición del 2026-10-16 y la guía operativa porque es evidencia
  del hueco, con el mismo criterio que las specs anteriores del repositorio.
- **Excepción declarada (no es detalle de implementación):** el proveedor de
  traducción con IA se nombra una vez, como **dependencia externa declarada** (la
  constitución y la guía §7 exigen declarar el código externo). El requisito sigue
  siendo de comportamiento: si el mantenedor cambia de proveedor, la spec no cambia.
- **CHK002 / CHK003 — valor y audiencia.** El usuario es el mantenedor de la tienda,
  que hoy traduce a mano; ningún ítem exige saber programar. El beneficio se mide en
  contenido publicado en `/pt/` y `/en/`, no en tecnología.
- **CHK004 — secciones obligatorias.** User Scenarios (4 historias: P1, P1, P2 y P3),
  Requirements, Success Criteria y Assumptions completas, más Key Entities y Edge
  Cases (11 casos).
- **CHK005 — sin marcadores pendientes.** Las tres decisiones abiertas (alcance de la
  primera entrega, modo de disparo y qué se traduce de una reseña) fueron resueltas
  por el mantenedor el 2026-10-16 y están registradas en "Preguntas resueltas" y en
  "Fuera de alcance (declarado)".
- **CHK006 / CHK013 — requisitos testeables.** Cada FR tiene su escenario de
  aceptación en la historia correspondiente; los FR transversales (privacidad, cuota,
  caché, respaldo ante fallos) se verifican con los casos concretos de Edge Cases y
  con las comprobaciones del quickstart.
- **CHK007 — SC medibles.** SC-001 a SC-009 se miden por conteo (0 llamadas desde
  visitantes, 0 datos personales enviados, 0 cambios visibles sin clave o sin cuota,
  0 ediciones humanas pisadas) y por una medición de tiempo percibido. SC-006 exige
  una persona: es el criterio de revisión humana, y la constitución ya establece que
  producción es el único entorno.
- **CHK009 — escenarios de aceptación.** 16 escenarios en 4 historias (4 + 5 + 4 + 3),
  todos en formato Given/When/Then.
- **CHK010 — casos límite.** 11 casos, incluidos los que gobiernan el diseño: texto ya
  en el idioma destino, traducción idéntica al español, cuota agotada, timeout,
  respuesta inválida del proveedor, dato personal detectado, HTML permitido en el
  campo destino y edición humana posterior.
- **CHK011 — alcance acotado.** "Fuera de alcance (declarado)" separa 8 puntos:
  automatización y programación, traducción masiva, productos y categorías, correos y
  mensajes al comprador, SEO, idiomas nuevos, contenido editorial de páginas (es del
  plugin de idiomas) y traducción de slugs o URLs.
- **CHK012 — supuestos documentados.** 8 supuestos, incluidos los tres que sostienen
  el diseño: el idioma destino de cada visitante se resuelve por URL (nunca por
  navegador), la traducción se guarda en los mismos campos por idioma que ya existen,
  y el mantenedor revisa antes de publicar.

### Preguntas resueltas (2026-10-16 — mantenedor)

- **Q1 — alcance de la primera entrega.** Se implementa primero el **servicio base**
  (configuración, contexto de tienda, caché, cuota y trazabilidad) y enseguida los
  **dos ámbitos con botón**: reseñas y hero banners. Productos, categorías, correos y
  SEO quedan para etapas siguientes.
- **Q2 — modo de disparo.** **Manual con botón chico** por campo: la traducción se
  propone, el humano revisa y guarda. La automatización (lote y programada) queda fuera
  de esta entrega y se decide después de ver calidad y consumo real.
- **Q3 — qué se traduce de una reseña.** Solo el **comentario**; el nombre del cliente,
  la calificación, la fecha y las fotos son iguales en los tres idiomas.

### Riesgos registrados

- **Fidelidad del contenido de terceros**: las reseñas son opiniones de clientes. La
  spec exige que la traducción no altere el sentido (FR-016) y que el resultado quede
  identificable como automático; la revisión humana antes de publicar es el control.
- **Cuota del plan gratuito**: los límites son por proyecto y se agotan (FR-011). El
  diseño evita consumo repetido con caché y limita el uso por acción; aun así, un texto
  nuevo nunca antes traducido consume una solicitud.
- **Datos en el plan gratuito del proveedor**: los planes gratuitos pueden usar el
  contenido enviado para mejorar sus productos. FR-012 restringe el envío a textos
  publicables y prohíbe datos de clientes.
- **Solapamiento con el plugin de idiomas**: el plugin traduce las páginas; el tema
  traduce lo que guarda en base de datos. El criterio de propiedad queda escrito
  (FR-004) y se verifica en `/pt/` que un texto no se traduzca dos veces.
- **Primer envío saliente del tema**: la guía §7 afirma hoy que el tema no hace envíos
  de escritura. FR-020 exige actualizar la documentación operativa en el mismo cambio,
  con su comando de verificación y su rollback.

