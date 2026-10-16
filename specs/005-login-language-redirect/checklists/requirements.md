# Specification Quality Checklist: Conservar el idioma (wpLingua) al iniciar sesión

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-10-16
**Feature**: [spec.md](../spec.md)

## Content Quality

- [x] CHK001 No implementation details (languages, frameworks, APIs)
- [x] CHK002 Focused on user value and business needs
- [x] CHK003 Written for non-technical stakeholders
- [x] CHK004 All mandatory sections completed

## Requirement Completeness

- [ ] CHK005 No [NEEDS CLARIFICATION] markers remain
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

15 de 16 ítems pasan. El único pendiente es CHK005, por dos decisiones de
alcance que corresponden al mantenedor (ver "Preguntas abiertas").

- **CHK001 / CHK008 / CHK016 — sin detalles de implementación.** Los FR y los SC
  no nombran archivos, funciones, filtros ni plugins: expresan qué debe
  cumplirse ("el comprador vuelve al mismo host, ruta e idioma") y cómo se
  verifica ("comparar los enlaces antes y después"). La sección Problema sí cita
  la medición y la guía operativa, porque es evidencia del defecto, no diseño:
  es el mismo criterio que usan las specs anteriores del repositorio.
- **CHK002 / CHK003 — valor y audiencia.** El usuario es el comprador que entra
  con Google, Facebook o usuario/contraseña en `pt`/`en`, y el mantenedor que
  verifica contra producción. Ningún ítem exige saber PHP.
- **CHK004 — secciones obligatorias.** User Scenarios, Requirements, Success
  Criteria y Assumptions completas, más Key Entities (hay entidades de
  comportamiento: prefijo de la petición, destino, enlace social, página de
  cuenta) y Edge Cases con 8 casos.
- **CHK005 — PENDIENTE.** Quedan 2 marcadores, ambos de alcance (FR-013: enlace
  de salir; FR-014: redirects de la tienda que pierden el prefijo). No se
  resuelven por suposición razonable: el segundo vive en código de la tienda y
  no del login, así que incluirlo o no cambia el alcance del trabajo y la
  superficie de riesgo. Se presentan como Q1 y Q2.
- **CHK006 / CHK013 — requisitos testeables.** FR-001 a FR-003 y FR-011 con
  inspección del enlace contra producción; FR-004 y FR-005 con login real desde
  páginas prefijadas y desde el dominio raíz; FR-006 con un usuario de cada rol;
  FR-007 con una URL ya prefijada; FR-008 con los 10 hosts; FR-009 con los tres
  métodos de acceso; FR-010 con búsqueda de texto (una sola lista de prefijos);
  FR-012 con rutas técnicas y destinos de terceros.
- **CHK007 — SC medibles.** SC-001 (100 % contra 0 % hoy) y SC-002 a SC-007
  (cero cambios, cero regresiones, cero archivos fuera del tema) se verifican por
  comparación de enlaces, por conteo de elementos y por lista de archivos
  modificados. SC-004 requiere una persona: es el único criterio no automatizable
  y la constitución ya lo exige así (producción es el único entorno).
- **CHK009 — escenarios de aceptación.** 13 escenarios en 3 historias (5 + 5 +
  3), todos en formato Given/When/Then.
- **CHK010 — casos límite.** 8 casos. Los tres que gobiernan el cambio: URL sin
  prefijo (no debe cambiar nada), prefijo ya presente (no duplicar) y wpLingua
  desactivado (el sitio sigue funcionando).
- **CHK011 — alcance acotado.** "Fuera de alcance" separa 6 puntos: traducir la
  pantalla de acceso, reconfigurar plugins/CDN, el 301 canónico ya resuelto,
  idiomas nuevos, cambiar la regla de destino a "Mi Cuenta" y migrar el
  mecanismo de autenticación.
- **CHK012 — supuestos documentados.** 6 supuestos, incluidos los dos que
  sostienen la corrección: el idioma vive solo en la URL (sin prefijo no hay
  idioma) y la captura de la URL original es la fuente correcta del prefijo.

### Preguntas abiertas (bloquean CHK005)

- **Q1 (FR-013)** — enlace de salir del menú de cuenta: ¿se exige que conserve el
  idioma en esta feature, o se declara fuera con la medición documentada?
- **Q2 (FR-014)** — redirects de la tienda que pierden el prefijo (caso medido:
  `/pt/finalizar-compra/` con carrito vacío → `/carrito/`): ¿se corrigen aquí o
  se registran como observación y se tratan aparte?

### Riesgos registrados

- La corrección del login por usuario/contraseña depende de una señal de idioma
  que hoy solo existe en la propia petición (destino pedido o procedencia).
  Cuando no existe, se conserva el comportamiento actual **a propósito**
  (FR-005): la feature no adivina el idioma por país. El coste declarado es que
  un login directo a la pantalla de acceso sigue terminando en el idioma origen.
- El enlace del botón social vive dentro de HTML cacheado por host: la
  verificación exige purgar la caché antes de medir (Edge Cases y Assumptions).
- No se pudo medir el enlace social del checkout sin una sesión con carrito (la
  URL responde `302` al carrito cuando está vacío): queda como verificación
  manual en el quickstart, no como supuesto silencioso.
