# Feature Specification: Traducción con IA en contexto de tienda (reseñas y banners)

**Feature Branch**: `006-ai-translate-core`

**Created**: 2026-10-16

**Status**: Draft

**Input**: User description: "tengo api key de gemini gratuita, se podria usar para traducir en contexto con ia las reviews? como se podria utilizar (y que sirva en diferentes ambitos (secciones de la pagina) para traducir en contexto de tienda online)"

## Problema

La tienda publica cada página en tres idiomas: español en el dominio raíz y en los
subdominios de habla hispana, y `/pt/` y `/en/` en `br.` y `us.`. El contenido
editorial de las páginas lo traduce el plugin de idiomas; **los textos que el tema
guarda en base de datos no**: hoy las reseñas tienen campos por idioma
(`text_pt`, `text_en`) y los hero banners también (título, descripción y CTAs), pero
se completan **a mano, uno por uno**.

Medido el 2026-10-16 en producción:

| Comprobación | Resultado |
|---|---|
| Reseñas guardadas | 25 |
| Reseñas con traducción a portugués o inglés | 0 |
| Idioma en que llegan las reseñas importadas de Google | español (la importación pide `language=es`) |
| Consultas externas del tema hoy | solo la de reseñas, con `?force_reviews=1` y solo administradores (guía §7) |

Cada reseña nueva y cada banner nuevo quedan en español en `/pt/` y `/en/`: los dos
idiomas que el negocio quiere abrir. Traducir a mano no escala, y hoy no existe
ninguna herramienta que traduzca **en contexto** (tono de tienda, glosario propio,
qué no traducir y el formato del campo destino).

Impacto: el catálogo se expande a países de habla portuguesa e inglesa con secciones
que siguen hablando español, y el mantenedor no tiene forma de cerrar esa brecha sin
escribir cada traducción él mismo.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - El mantenedor configura el traductor y comprueba que responde (Priority: P1)

El mantenedor dispone de una clave del servicio de traducción con IA (hoy: Google
Gemini, clave propia y gratuita). Quiere cargarla **sin exponerla** y verificar desde
su propio panel que el servicio responde, con qué modelo trabaja, cuánto consumió y
qué textos de la tienda se traducirán.

**Why this priority**: sin esta verificación no hay forma de saber si el servicio está
bien configurado antes de tocar contenido real, y cualquier error se descubriría recién
al traducir una reseña en vivo.

**Independent Test**: con la clave configurada, la comprobación de conexión responde
correctamente e informa el modelo en uso y el consumo; al quitar la clave, la misma
pantalla lo indica y **el sitio sigue funcionando exactamente igual** (secciones en
español).

**Acceptance Scenarios**:

1. **Given** una clave válida configurada, **When** el mantenedor ejecuta la
   comprobación de conexión, **Then** obtiene una respuesta correcta con el modelo en
   uso y el consumo registrado.
2. **Given** una clave ausente o inválida, **When** abre la pantalla, **Then** ve el
   estado ("sin configurar" o "clave rechazada") y **no** hay ningún cambio en el
   sitio público.
3. **Given** una clave válida, **When** inspecciona el HTML público y los archivos del
   tema, **Then** la clave no aparece en ninguno de los dos.
4. **Given** la cuota agotada, **When** intenta traducir, **Then** recibe un aviso
   claro de límite alcanzado y **no** se modifica ni se pierde contenido.

---

### User Story 2 - El mantenedor traduce el comentario de una reseña con un botón (Priority: P1)

En el editor de reseñas (el mismo donde ya elige ES/PT/EN), el mantenedor quiere
apretar un botón chico **"Traducir"** y que el comentario del idioma activo se
complete con la traducción propuesta. Nada se publica solo: él revisa, corrige si hace
falta y guarda.

**Why this priority**: es el caso que motivó la feature (25 reseñas sin traducir) y el
que desbloquea `/pt/` y `/en/` con contenido social real, que es lo que sostiene la
confianza de compra.

**Independent Test**: abrir una reseña existente, elegir PT, apretar "Traducir",
comprobar que el campo se completa con una traducción fiel, guardar y ver el comentario
en portugués en la sección de reseñas de `br.muyunicos.com/pt/`.

**Acceptance Scenarios**:

1. **Given** una reseña con comentario en español y sin traducción a portugués,
   **When** el mantenedor abre el editor, elige PT y aprieta "Traducir", **Then** el
   campo de portugués se completa con una traducción fiel al original y **sin guardar
   todavía**.
2. **Given** esa traducción propuesta, **When** el mantenedor guarda, **Then** el
   comentario aparece en portugués en la sección pública de `/pt/`, y el español no
   cambia.
3. **Given** una reseña cuyo comentario ya está en portugués, **When** aprieta
   "Traducir", **Then** el sistema no propone nada y lo informa: no se gasta una
   solicitud ni se duplica contenido.
4. **Given** una reseña traducida a mano por el mantenedor, **When** vuelve a abrir el
   editor, **Then** su texto se conserva intacto (la traducción automática no pisa una
   edición humana).
5. **Given** un comentario con un dato personal (correo, teléfono o número de pedido),
   **When** aprieta "Traducir", **Then** el sistema rechaza el envío e informa el
   motivo: los datos de clientes no salen del sitio.

---

### User Story 3 - El mantenedor traduce un hero banner (Priority: P2)

El mantenedor publica banners con título, descripción, textos de botón y badge de
envío gratis. Quiere el **mismo botón** en la fila del banner para completar las
versiones portuguesa e inglesa, respetando la regla vigente del módulo: si el texto
queda igual al español, no se guarda, y el sitio muestra la versión en español.

**Why this priority**: los banners son la portada de cada subdominio y hoy se publican
en español en `/pt/` y `/en/`. Es el segundo ámbito con campos por idioma ya
existentes, así que aprovecha el mismo servicio y el mismo botón.

**Independent Test**: abrir un banner real, elegir PT, apretar "Traducir", guardar y
ver el banner traducido en `br.muyunicos.com/pt/`; luego vaciar el texto de portugués y
guardar: el sitio vuelve a mostrar la versión en español.

**Acceptance Scenarios**:

1. **Given** un banner con título, descripción y texto de botón en español y sin
   traducción, **When** el mantenedor traduce a PT, **Then** los campos de portugués se
   completan conservando el marcado permitido del título (resaltados) y sin tocar las
   URLs.
2. **Given** ese banner traducido, **When** guarda, **Then** `/pt/` muestra la versión
   portuguesa y la versión en español no cambia.
3. **Given** un texto que el servicio devuelve idéntico al español, **When** guarda,
   **Then** no se persiste una copia y el sitio sigue mostrando el español.
4. **Given** un banner sin CTA secundario o sin badge, **When** traduce, **Then** solo
   se completan los campos que tienen contenido en español.

---

### User Story 4 - Traducción en lote y automática (Priority: P3 — etapa siguiente)

Más adelante el mantenedor quiere traducir los pendientes en lote ("12 reseñas sin PT")
y que las reseñas importadas de Google se traduzcan solas en segundo plano, con un
presupuesto diario de consumo. Esta historia queda **declarada y fuera de esta
entrega**: su alcance se decide después de medir calidad y consumo de las historias
P1 y P2.

**Why this priority**: multiplica el valor del servicio, pero depende de haber validado
antes la calidad de las traducciones y el consumo real del plan gratuito.

**Independent Test** (de la etapa siguiente, no verificable ahora): seleccionar 12
reseñas sin portugués, comprobar que el sistema informa cuántas solicitudes consumirá
y que solo traduce lo seleccionado.

**Acceptance Scenarios**:

1. **Given** 12 reseñas sin traducción a PT, **When** el mantenedor lanza el lote,
   **Then** el sistema informa el consumo estimado antes de ejecutar.
2. **Given** una reseña importada de Google sin traducción, **When** se importa,
   **Then** la traducción se completa en segundo plano sin intervención del mantenedor.

---

### Edge Cases

- **Texto ya en el idioma destino**: no se propone nada, no se consume una solicitud y
  se informa al mantenedor.
- **Traducción idéntica al español** (nombres propios, "ok", emojis): se guarda como
  "sin traducción"; el sitio muestra el español.
- **Texto vacío, solo emojis o solo signos**: no se envía nada al servicio.
- **Cuota agotada**: aviso claro de límite alcanzado, sin cambios en el contenido y sin
  alterar el consumo registrado.
- **Servicio lento o sin respuesta**: se corta la espera, se informa y no queda nada a
  medias.
- **Respuesta con formato inesperado**: se descarta por completo; nunca se escribe en un
  campo un texto dudoso.
- **Dato personal en el texto** (correo, teléfono, número de pedido, enlace de
  descarga): se rechaza el envío completo y se explica el motivo.
- **Campo destino con marcado permitido** (título del banner): el marcado se conserva y
  cualquier otra etiqueta se descarta.
- **Texto desproporcionado** (más largo que un comentario razonable): se informa y no se
  envía; no se gasta cuota ni se pierde contenido.
- **El español cambió después de traducir**: la traducción existente se marca como
  desactualizada en el editor y **no** se reescribe sola.
- **El campo destino ya tiene una traducción escrita a mano**: el botón no la
  reemplaza sin confirmación explícita del mantenedor.

---

## Requirements *(mandatory)*

### Functional Requirements

**Configuración y seguridad**

- **FR-001**: El mantenedor DEBE poder configurar la clave del servicio de traducción con IA desde su panel, y la clave DEBE vivir en la configuración del servidor o de WordPress: nunca en el repositorio, nunca en el contenido, nunca en páginas públicas.
- **FR-002**: El sistema DEBE informar el estado de la configuración —sin configurar, configurada y válida, o rechazada— junto con el modelo en uso.
- **FR-003**: La pantalla y todas las acciones de traducción DEBEN estar restringidas a quien administra la tienda, con verificación de permiso en el servidor para cada acción y protección contra peticiones ajenas.
- **FR-004**: El sistema DEBE traducir únicamente lo que el tema guarda en base de datos (reseñas y banners). El contenido editorial de las páginas sigue siendo responsabilidad del plugin de idiomas, y un mismo texto no puede quedar traducido dos veces.
- **FR-005**: Ninguna acción de traducción DEBE ejecutarse durante la navegación de un visitante: solo desde el panel y, en la etapa siguiente, desde tareas programadas.

**Contexto y calidad**

- **FR-006**: Cada ámbito (reseñas, banners y los que se sumen) DEBE declarar su contexto: qué representa el texto, para quién se escribe, el tono, el glosario de la tienda y qué no debe traducirse (marcas, URLs, nombres propios, medidas).
- **FR-007**: El mantenedor DEBE poder ajustar el glosario y el tono sin tocar código.
- **FR-008**: El sistema DEBE respetar el formato del campo destino: conservar el marcado admitido (por ejemplo, los resaltados del título de un banner), no alterar URLs ni emojis, y devolver únicamente texto.
- **FR-009**: El sistema DEBE detectar cuándo el texto ya está en el idioma destino y, en ese caso, no proponer ni guardar nada.

**Traducción y publicación**

- **FR-010**: El mantenedor DEBE poder pedir la traducción de un campo concreto desde un botón en el editor de cada ámbito, y el resultado DEBE quedar en el campo editable del idioma activo, sin publicarse automáticamente.
- **FR-011**: Cuando el texto de un idioma sea vacío o idéntico al español, el sistema DEBE persistirlo como "sin traducción": el sitio muestra la versión en español.
- **FR-012**: El sistema NO DEBE enviar datos de clientes ni de pedidos —correo, teléfono, número de pedido, enlaces de descarga o cualquier dato no publicable— y DEBE rechazar el envío completo cuando detecte alguno.
- **FR-013**: El sistema NO DEBE sobrescribir una traducción escrita o corregida a mano sin confirmación explícita del mantenedor, y DEBE marcar las traducciones cuyo texto de origen cambió después.
- **FR-014**: Toda traducción DEBE pasar por una revisión humana antes de publicarse; en los textos dirigidos al comprador, la revisión es obligatoria.

**Consumo, respaldo y operación**

- **FR-015**: El sistema DEBE registrar el consumo del servicio (solicitudes y uso del día y del mes) y permitir consultarlo desde el panel.
- **FR-016**: En las reseñas, la traducción NO DEBE alterar el sentido de la opinión del cliente, y el resultado DEBE quedar identificable como traducción automática en la administración.
- **FR-017**: El mismo texto, idioma y contexto NO DEBE traducirse dos veces: el resultado se reutiliza.
- **FR-018**: Si el servicio no está configurado, no responde, tarda más de lo tolerable o agota la cuota, el sitio DEBE seguir funcionando igual que hoy (texto en español) y el mantenedor DEBE recibir un aviso legible.
- **FR-019**: El sistema DEBE limitar el uso por acción y por período para no agotar la cuota del plan contratado, e informar cuando un límite impide continuar.
- **FR-020**: El cambio DEBE declarar en la documentación operativa el nuevo envío externo (qué se envía, a dónde, cuándo se ejecuta), su comando de verificación y cómo se revierte.

### Key Entities *(include if feature involves data)*

- **Clave del servicio**: credencial del mantenedor. Vive en la configuración del
  servidor; se verifica desde el panel y nunca se muestra.
- **Perfil de contexto**: uno por ámbito. Atributos: qué representa el texto, para
  quién se escribe, tono, glosario de la tienda, exclusiones (marcas, URLs, nombres
  propios) y largo máximo admitido.
- **Solicitud de traducción**: los textos a traducir, el idioma destino, el perfil de
  contexto, el ámbito y la referencia del campo destino.
- **Resultado**: traducción propuesta, indicador de "ya estaba en el idioma destino",
  notas del servicio y estado de la solicitud (correcta, límite alcanzado o error).
- **Consumo**: solicitudes y uso registrados por día y por mes, con techo configurable.
- **Traducción almacenada**: el texto por idioma en el campo que ya existe hoy, más la
  marca de qué texto en español lo originó (para detectar que quedó desactualizada).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 0 solicitudes salientes durante la navegación de visitantes: todas las
  entradas del registro de consumo corresponden a acciones del mantenedor.
- **SC-002**: La segunda traducción del mismo texto, idioma y contexto consume 0
  solicitudes.
- **SC-003**: Con la clave ausente, rechazada o la cuota agotada: 0 cambios visibles en
  el sitio público y 0 pérdidas de contenido.
- **SC-004**: 100 % de los textos publicados en otro idioma pasan por una acción humana
  (guardar) después de la propuesta automática.
- **SC-005**: Traducir un comentario de hasta 40 palabras se percibe como inmediato
  (menos de 5 segundos) desde el botón.
- **SC-006**: Revisar una reseña traducida lleva menos de 1 minuto por reseña (hoy:
  escribir la traducción completa desde cero).
- **SC-007**: 0 datos personales de clientes enviados al servicio, comprobado con casos
  de prueba que contienen correo, teléfono y número de pedido.
- **SC-008**: 0 traducciones automáticas que pisen una corrección manual.
- **SC-009**: 0 archivos del tema padre, 0 plugins y 0 contenidos existentes alterados
  por la feature: verificable con la lista de archivos modificados y con el contenido
  actual intacto.

## Assumptions

- El idioma de cada visitante se resuelve por la URL de la petición (`/pt/`, `/en/`,
  sin prefijo para español), nunca por el navegador ni por el país.
- La traducción se guarda en los campos por idioma que ya existen en reseñas y
  banners: no se agregan estructuras de contenido nuevas ni se migran datos.
- El mantenedor revisa y guarda: la feature propone, no publica.
- El plan gratuito del servicio alcanza para el volumen actual (decenas de reseñas y
  menos de 20 banners), gracias a la reutilización de traducciones y a los límites por
  acción.
- Los textos traducibles no contienen datos de clientes: los campos con información
  personal quedan fuera del alcance por diseño.
- La sección de reseñas y los banners ya resuelven el idioma del visitante y usan el
  español como respaldo: el servicio solo completa campos que hoy se llenan a mano.
- Producción es el único entorno: la calidad se evalúa con traducciones reales
  revisadas por el mantenedor.
- Después de aplicar una traducción hay que purgar la caché del host correspondiente
  (con el criterio vigente de purgar solo si algo cambió), porque el texto viaja dentro
  del HTML cacheado.

## Fuera de alcance (declarado)

- Traducción en lote y traducción automática (programada, o al importar reseñas de
  Google): es la historia US4, se decide después de esta entrega.
- Productos, categorías y etiquetas del catálogo.
- Correos transaccionales, mensajes de WhatsApp y textos del checkout: exigen revisión
  humana obligatoria y se tratan aparte.
- SEO (títulos y descripciones por idioma) y cualquier cambio de canonical o hreflang.
- Idiomas nuevos: solo `es` → `pt` y `es` → `en`.
- Traducción de slugs, URLs o nombres de archivo.
- El contenido editorial de las páginas: es responsabilidad del plugin de idiomas.
- Cambiar de proveedor, de clave o la configuración del plugin de idiomas.



