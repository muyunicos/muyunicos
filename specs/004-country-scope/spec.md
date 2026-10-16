# Feature Specification: Alcance por país de rutas y elementos

**Feature Branch**: `004-country-scope`

**Created**: 2026-10-16

**Status**: Draft

**Input**: User description: "de qué manera limpia corrijo https://muyunicos.com/envios/ para que solo se muestre en argentina" + "documenta esa función para reutilizarla más adelante en lugares o elementos que solo se rendericen en países determinados"

## Problema

`/envios/` (página WP ID 20036) describe las zonas de cobertura de Mar del Plata, los
costos en pesos, el retiro en Catamarca 2255 y el WhatsApp del local: información que
sólo es cierta en Argentina. Los subdominios venden únicamente productos digitales
(guía §3) y no tienen envío físico que informar.

Medido el 2026-10-16 contra producción:

| Comprobación | Resultado |
|---|---|
| `curl -sI https://{mexico,co,br,us}.muyunicos.com/envios/` y `br…/pt/envios/` | `200 OK` con el contenido argentino completo |
| Pie de página en los subdominios | `<li><a href="/envios/">Información de Envíos</a></li>` en los 10 hosts |
| `hreflang` de `inc/seo-hreflang.php` en `/envios/` | 11 alternates hacia todos los subdominios + `x-default` al raíz |
| `page-sitemap.xml` de un subdominio | incluye `/envios/` con sus alternates |
| meta `robots` | `follow, index` en todos los hosts |

La causa no es un enlace mal puesto: el sitio es **una sola instalación de WordPress**
servida en 10 hosts, así que páginas, menús y bloques son globales. No existe un
"publicar sólo en Argentina" en wp-admin ni en los plugins del sitio (WCPBC ajusta
precios por país y wpLingua ajusta idioma: ninguno tiene alcance por país de una URL).
La corrección tiene que vivir en el tema, y el mismo problema va a reaparecer con
cualquier ruta o elemento que sólo valga en un país.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - El comprador de otro país no recibe la página ni su enlace (Priority: P1)

Un comprador que navega en `mexico.muyunicos.com` (o cualquiera de los otros ocho
subdominios) abre `/envios/` —desde un enlace guardado, un resultado de búsqueda o el
pie de página— y hoy recibe información de logística de Mar del Plata, con costos en
pesos y un teléfono local: datos que no aplican a su compra. Debe llegar a la tienda de
su país, y el enlace no debe aparecerle en el pie.

**Why this priority**: es el defecto que perjudica al comprador y el único visible en
producción. La corrección tiene que ser del lado del servidor: esconder el enlace u
ocultar con CSS no evita que la URL responda y se indexe.

**Independent Test**: abrir `/envios/` en `mexico.muyunicos.com` y comprobar que el
navegador termina en `/tienda/` del mismo subdominio, y que el pie ya no lista
"Información de Envíos". Se verifica sin tocar ninguna otra parte del sitio.

**Acceptance Scenarios**:

1. **Given** el subdominio de cualquier país que no sea Argentina, **When** el
   comprador abre `/envios/`, **Then** recibe un `302` hacia `/tienda/` del mismo
   subdominio (en `br.` y `us.`, con el prefijo de idioma `/pt` y `/en`).
2. **Given** el dominio `muyunicos.com`, **When** cualquiera abre `/envios/`, **Then**
   recibe `200` con el contenido completo: la página sigue publicada en Argentina.
3. **Given** el pie de página en un subdominio, **When** el comprador lo lee, **Then**
   no aparece el enlace "Información de Envíos"; en `muyunicos.com` sí aparece.
4. **Given** una URL con idioma en un subdominio (`br…/pt/envios/`), **When** se abre,
   **Then** también redirige, sin duplicar el prefijo (`/pt/tienda/`).

---

### User Story 2 - Cualquier ruta o elemento por país se declara en un solo lugar (Priority: P2)

El mantenedor necesita repetir este criterio más adelante (una ruta, un `<li>`, un
botón, un bloque que sólo valga en ciertos países) sin volver a resolver el problema
completo ni copiar la lógica en cada archivo.

**Why this priority**: sin esto la corrección es un parche de un caso, y el próximo
elemento por país se resuelve de nuevo —probablemente distinto—. No es P1 porque no
cambia lo que ve el comprador hoy.

**Independent Test**: agregar una ruta nueva al registro y comprobar, en los escenarios
locales de `quickstart.md` §A.6, que el guard y el `hreflang` la cubren sin tocar otro
archivo.

**Acceptance Scenarios**:

1. **Given** el registro de rutas con alcance por país, **When** se agrega una entrada
   con su lista de países, **Then** el guard HTTP y el hreflang la cubren sin más
   cambios.
2. **Given** un elemento que el tema imprime, **When** se lo envuelve con el helper de
   país, **Then** no se emite HTML en los países excluidos.
3. **Given** la API documentada, **When** otro agente la lee en la guía §6, **Then**
   encuentra las tres formas de restringir por país, con el límite de cada una.

---

### User Story 3 - La página deja de indexarse en los países que no la publican (Priority: P3)

Hoy cada subdominio declara `/envios/` en su sitemap y en su `hreflang` como una página
propia, y responde `index`. Un comprador puede llegar desde un buscador a una página
que no es de su país.

**Why this priority**: es consecuencia de US1 y se resuelve con ella (el `302` retira la
URL), pero el `hreflang` es código propio y no debe declarar hosts que no sirven la
página.

**Independent Test**: `curl` sobre la página en Argentina y sobre el sitemap de un
subdominio, comparando antes y después.

**Acceptance Scenarios**:

1. **Given** `/envios/` en Argentina, **When** se leen sus `hreflang`, **Then** declara
   una sola entrada autorreferencial en el idioma de la página (no los 9 subdominios).
2. **Given** el `page-sitemap.xml` de un subdominio, **When** se lo consulta, **Then**
   no incluye `/envios/`.
3. **Given** `canonical` y `robots` en Argentina, **When** se los consulta, **Then**
   siguen siendo `self` e `index` (sin cambios).

---

### Edge Cases

- **Enlace del menú de WordPress**: si `/envios/` está en un menú (Apariencia → Menús),
  sale de la base de datos y no lo cubre ningún código. Se verifica a mano y se
  documenta en la guía §3.
- **Guard sin desplegar**: si `functions.php` no llega actualizado al servidor, el
  archivo del módulo queda en disco sin cargarse y el síntoma es idéntico al actual. La
  cabecera de trazabilidad del guard es lo que permite distinguirlo.
- **`/pt/envios/` y `/en/envios/` del dominio raíz**: siguen publicándose (el país es
  Argentina; el idioma es otra dimensión). Declarado fuera de alcance.
- **Bots**: reciben el mismo `302` que una persona, para que la URL salga del índice.
- **Caché de página**: la respuesta anterior está cacheada 7 días por host; sin purga,
  el `200` viejo se sigue sirviendo.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE seguir sirviendo `/envios/` con `200` en el dominio
  `muyunicos.com`.
- **FR-002**: El sistema DEBE responder `302` a `/envios/` en los 9 subdominios de
  país, con destino la tienda del mismo subdominio.
- **FR-003**: El destino del `302` DEBE conservar el host del comprador y aplicar el
  prefijo de idioma del país cuando corresponda (`BR→/pt`, `US→/en`), sin duplicarlo.
- **FR-004**: El destino DEBE construirse con una sola forma canónica de URL.
- **FR-005**: El pie de página NO DEBE emitir el enlace "Información de Envíos" fuera de
  Argentina.
- **FR-006**: La respuesta del `302` DEBE ser identificable por una cabecera propia, de
  modo que se distinga "el guard corrió" de "el archivo no está cargado".
- **FR-007**: El `hreflang` de una ruta con alcance por país NO DEBE declarar hosts que
  no la sirven.
- **FR-008**: El `canonical` y el meta `robots` de la página en Argentina NO DEBEN
  cambiar.
- **FR-009**: El sistema DEBE exponer un registro único de rutas con sus países, y el
  guard y el hreflang DEBEN derivarse de ese registro.
- **FR-010**: El sistema DEBE exponer un helper para condicionar la emisión de cualquier
  elemento del tema a una lista de países.
- **FR-011**: El registro DEBE ser extensible sin editar el archivo del módulo.
- **FR-012**: El guard NO DEBE ejecutarse en admin, REST, AJAX, cron ni feeds.
- **FR-013**: El guard NO DEBE alterar ninguna otra ruta del sitio.
- **FR-014**: La página NO DEBE listarse en el sitemap de un subdominio.
- **FR-015**: La documentación DEBE declarar las tres formas de restringir por país y el
  límite de cada una (ruta, elemento del tema, elemento de un plugin).

### Key Entities

- **Registro de alcance por país**: mapa `slug de ruta → { países, destino, etiqueta }`.
  Es la fuente única del alcance de una ruta; el guard y el hreflang lo consumen.
- **País del request**: derivado del host (fuente existente: `inc/geo.php`). No se
  introduce ninguna fuente nueva de país.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 9 de 9 subdominios dejan de servir el contenido argentino en `/envios/`
  (hoy: 9 de 9 lo sirven).
- **SC-002**: 1 de 1 dominio (Argentina) sigue sirviendo la página con `200`.
- **SC-003**: 0 de 9 subdominios listan el enlace en el pie (hoy: 9 de 9).
- **SC-004**: 1 de 1 página declara un `hreflang` reducido a su propio host (hoy: 12
  entradas hacia 10 hosts).
- **SC-005**: 0 de 9 sitemaps de subdominio listan `/envios/`.
- **SC-006**: 0 rutas afectadas fuera de las registradas: `/tienda/`, `/blog/`,
  categorías de producto y productos siguen respondiendo igual.
- **SC-007**: 0 consultas SQL nuevas por request (el guard es O(1) sobre el host y el
  main query).

## Assumptions

- El país se define por el host, no por el idioma ni por la geolocalización del
  visitante: es la regla que ya usa todo el sitio (precios, catálogo, checkout).
- En un país que no publica la ruta, el destino útil es la tienda de ese subdominio
  (decisión del mantenedor, 2026-10-16, entre `302` a tienda, `404`, `410` y aviso).
- Se acepta que un enlace externo viejo (bio de Instagram, mensaje guardado) lleve a la
  tienda en lugar de a la página, en los países que no la publican.

## Fuera de alcance (declarado)

- `/reembolso_devoluciones/` ("Botón de arrepentimiento"): tiene el mismo defecto
  estructural, pero es una decisión comercial si se restringe fuera de Argentina. Queda
  como candidata para el registro, no incluida.
- `/pt/envios/` y `/en/envios/` del dominio raíz: el país es Argentina.
- Elementos de menú de WordPress: se resuelven en wp-admin, no por código.
- Las páginas institucionales (`/nuestra-historia/`, políticas): se sirven a propósito en
  todos los países.

## Verificación declarada

- La lógica pura del módulo (país, ruta normalizada, destino del redirect, hreflang y
  filtro de sitemap) se verificó localmente con un arnés de stubs: 11 escenarios con su
  salida registrada en `quickstart.md` §A.6.
- La respuesta HTTP real por host, la cabecera de trazabilidad y el sitemap **no se
  verifican en local**: requieren producción (`quickstart.md` §B). Hasta ejecutar esa
  fase esta feature se declara **NO VERIFICADA EN PRODUCCIÓN**.
- El filtro de Rank Math que saca la ruta del sitemap se verificó contra el código de la
  versión instalada (seo-by-rank-math 1.0.280): el hook es `rank_math/sitemap/entry` y
  recibe un array `[ 'loc' => … ]`; devolver `[]` excluye la entrada. La primera medición en
  producción (2026-10-16) falló porque el callback exigía un string y devolvía la entrada sin
  tocar; corregido, verificado en local y con la remedición en producción pendiente en §B.4
  de `quickstart.md`.


