# Research: Traducción con IA en contexto de tienda

**Feature**: `006-ai-translate-core` | **Fecha**: 2026-10-16 | **Spec**: [spec.md](./spec.md)

Cada decisión se registra con su alternativa descartada, según el principio VII (lo que
no deja rastro no se puede diagnosticar).

## R1 — Transporte: REST del proveedor desde el servidor

**Decisión**: llamar al servicio con IA por su API REST desde PHP (`wp_remote_post`,
timeout 20 s, lectura explícita del código de respuesta), con la clave enviada en una
cabecera y no en la query.

**Razón**: el mantenedor ya tiene la clave; el tema ya usa el mismo stack HTTP para la
única consulta externa que existe hoy (reseñas de Google). No se incorpora ningún SDK
ni dependencia nueva (constitución I).

**Descartado**: el SDK oficial (dependencia nueva con autoload propio) y llamar al
servicio desde el navegador (expone la clave en el HTML y multiplica el consumo por
visitante).

## R2 — El modelo no se fija en código

**Decisión**: el identificador del modelo vive en la configuración editable, con un
valor inicial razonable, y la pantalla lista los modelos que la clave puede usar.

**Razón**: los identificadores de modelo del proveedor rotan y las cuotas del plan
gratuito cambian; fijarlos obligaría a desplegar código para un cambio de modelo.

**Descartado**: constante fija en el módulo; y autodetección silenciosa (el mantenedor
debe saber con qué modelo trabaja, FR-002).

**Medido el 2026-10-16 contra la API real** (quickstart §A.4): el alias
`gemini-flash-lite-latest` se resolvió a `gemini-3.5-flash-lite` y `gemini-flash-latest`
a `gemini-3.8-flash`, con `306` y `697` tokens para dos textos cortos. Confirma la
decisión: fijar la versión en código obligaría a desplegar en cada rotación de modelo, y
por eso la pantalla guarda y muestra el **modelo resuelto** que informa el proveedor.

## R3 — Salida estructurada y validación

**Decisión**: pedir la respuesta en formato JSON con un esquema explícito de items
(clave, traducción, sin cambios, notas) y **validar cada campo** antes de usarlo: el
texto del modelo es entrada no confiable.

**Razón**: evita interpretar texto libre, permite traducir varios campos en una sola
solicitud y detectar "ya estaba en el idioma destino" (FR-009).

**Descartado**: texto plano con delimitadores (frágil) y confiar en el modelo sin
validar (un error se escribiría en el contenido publicado).

## R4 — Lote por acción

**Decisión**: agrupar los textos de una misma acción en una sola solicitud (los campos
de un banner; en la etapa siguiente, varias reseñas).

**Razón**: el límite del plan gratuito se mide en solicitudes por minuto; agrupar es la
forma más barata de respetarlo (FR-019).

**Descartado**: una solicitud por campo (multiplica el consumo por cuatro en un banner).

## R5 — Caché de traducciones por contenido

**Decisión**: guardar cada traducción en `wp_options` con prefijo `_mu_*`, con clave
derivada del texto, el idioma, el contexto y la versión del glosario; poda por tamaño y
antigüedad.

**Razón**: un mismo texto ("Envío gratis") se traduce una vez y sirve a todos los
ámbitos; sin caché, cada reapertura del editor podría consumir cuota (SC-002). La
constitución VI exige copia permanente en `wp_options` (sobrevive al vaciado del object
cache).

**Descartado**: transients (se pierden con el vaciado y no son auditables) y traducir en
cada render (imposible: el sitio no puede depender de un servicio externo).

## R6 — Consumo y límites

**Decisión**: contador persistente de solicitudes y caracteres por día y por mes, más un
límite por ventana reutilizando el patrón de rate-limit que ya existe en el tema (con
clave **global**, no por visitante).

**Razón**: la cuota del plan es por proyecto, no por visitante; el patrón existente ya
resuelve "una sola vez por request" con object cache y su respaldo en transient.

**Descartado**: contar solo en el object cache (se pierde en el vaciado) y no contar
nada (imposible de diagnosticar cuando la cuota se agota).

## R7 — Privacidad: filtro previo obligatorio

**Decisión**: antes de enviar nada, un filtro rechaza la solicitud completa si el texto
parece contener correo, teléfono, número de pedido o enlace de descarga; los perfiles de
contexto declaran además sus exclusiones (marcas, URLs, nombres propios).

**Razón**: los planes gratuitos del proveedor pueden usar el contenido para mejorar sus
productos, y los textos traducibles son publicables por definición (reseñas públicas,
textos de portada). Un dato de cliente nunca sale del sitio (FR-012, SC-007).

**Descartado**: enviar y confiar en que el proveedor no retenga (no verificable) y
anonimizar automáticamente (alteraría el texto que se va a traducir).

## R8 — Fallo seguro y errores tipados

**Decisión**: sin clave, clave rechazada, cuota agotada, tiempo excedido o respuesta
inválida ⇒ no se escribe nada, el editor recibe un mensaje legible y el sitio público
queda idéntico. El consumo no se altera cuando la solicitud no se contabiliza.

**Razón**: FR-018 y SC-003. La traducción es una ayuda, nunca una dependencia del sitio.

**Descartado**: reintentos automáticos dentro del request del administrador (duplican
consumo y esconden el problema).

## R9 — El módulo propone; el guardado existente publica

**Decisión**: el servicio devuelve la traducción al editor y **no** escribe contenido. El
guardado sigue siendo el que ya existe en cada ámbito (reseñas y banners), con su
revisión humana y sus reglas (idéntico al español ⇒ se guarda vacío).

**Razón**: FR-010, FR-011 y FR-013, con SC-004 y SC-008. Se mantiene un único camino de
escritura por ámbito y una única fuente de verdad.

**Descartado**: que el servicio guarde por su cuenta (saltaría la revisión humana y
pisaría correcciones del mantenedor).

## R10 — Marca de origen para detectar desactualización

**Decisión**: junto al texto traducido se guarda el hash del texto en español que lo
originó; si el español cambia, el editor lo marca como "desactualizada".

**Razón**: FR-013. Sin esa marca, una traducción vieja se ve igual que una al día.

**Descartado**: re-traducir automáticamente al cambiar el español (pisaría decisiones
humanas y consumiría cuota sin aviso).

## R11 — Ámbitos como registro declarativo

**Decisión**: un registro declara, por ámbito: etiqueta, perfil de contexto, largo
máximo, marcado admitido y campos traducibles. La primera entrega declara **reseñas** y
**hero banners**.

**Razón**: es lo que hace que el servicio sirva en diferentes ámbitos sin duplicar lógica
(el objetivo de la consulta del mantenedor). Sumar productos o cadenas del tema después
es declarar un ámbito, no escribir un segundo servicio.

**Descartado**: un servicio por sección (duplicaría clave, caché, cuotas y errores).

## R12 — Propiedad frente al plugin de idiomas

**Decisión**: el plugin de idiomas traduce las páginas; el tema traduce lo que guarda en
base de datos. Se verifica en `/pt/` que un texto traducido por el tema no se traduzca
otra vez.

**Razón**: FR-004. Es el riesgo de doble traducción detectado al analizar la feature.

**Descartado**: delegar todo al plugin (no alcanza a los datos guardados en la base:
reseñas, banners) y traducir también el contenido editorial (duplicaría sistemas).

## R13 — Documentación e invariante operativa

**Decisión**: la guía operativa se actualiza en el mismo cambio: el tema pasa a tener un
envío saliente de escritura, declarado con su destino, su condición (solo
administradores, nunca en la navegación de un visitante), su comando de verificación y
su rollback. No se enmienda la constitución: no cambia ningún principio; la dependencia
externa queda declarada en el guide.

**Razón**: FR-020 y guía §7, que hoy afirma que el tema no hace envíos de escritura.

**Descartado**: dejar la afirmación de la guía sin corregir (documentación que miente es
peor que documentación incompleta).

