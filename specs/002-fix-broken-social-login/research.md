# Phase 0 Research: Blindar la URL de login social y eliminar los SVG inline

**Feature**: 002-fix-broken-social-login | **Fecha**: 2026-10-02
**Revisión**: v2. La primera versión de este documento partió de una premisa
incorrecta; ver §1.

---

## 1. Corrección de la premisa original (lectura obligatoria)

La primera versión de esta investigación concluyo que los cuatro `href` con
`site_url( '/wp-login.php' )` estaban rotos porque WPS Hide Login devuelve 404 a
esa ruta. **Era falso.**

Evidencia verificada el 2026-10-02 contra producción:

| Fuente | Valor |
|---|---|
| Código en el repositorio (`inc/auth-modal.php:106`) | `site_url( '/wp-login.php?loginSocial=google...' )` |
| HTML servido en producción | `https://muyunicos.com/login/?loginSocial=google...` |

El mismo archivo, sin modifications, produce la ruta vigente. La explicación:
**WPS Hide Login hookea el filtro `site_url` de WordPress**, de modo que
`site_url( '/wp-login.php' )` devuelve la ruta personalizada.

Confirmación adicional: el formulario de la pantalla de login sirve
`action="https://muyunicos.com/login/"`.

**Consecuencia**: el login social nunca estuvo roto. No hay ningún fix
desplegado fuera del control de versiones, porque no hubo nada que arreglar. Las
tareas de "reconstruir la URL" de la v1 de este plan resolvían un problema
inexistente.

**Lo que sí estuvo roto**, y se comprobó después: el WAF del edge bloqueaba el
callback de OAuth con 403 (ver §6).

---

## 2. Por qué la construcción actual es frágil, aunque hoy funcione

**Decisión**: seguir construyendo la URL de login por API, no por
`site_url( '/wp-login.php' )`.

**Rationale**: el comportamiento actual depende por completo de que WPS Hide
Login esté activo y hookeando `site_url`. Si se desactiva el plugin, o si su
filtro cambia, los cuatro enlaces vuelven a apuntar a una ruta que da 404, y no
hay ningún aviso hasta que un comprador reporte que el botón no funciona.

La API de WordPress (`wp_login_url()`) expresses la intención correcta: "la URL
de login, sea cual sea". Es la misma que usa el propio WordPress para
`wp_login_url()` en el admin, y la que cualquier plugin que목을 la ruta respeta.
Cambiar a ella elimina la dependencia implícita, no agrega comportamiento nuevo.

**Alternativas consideradas**:

| Alternativa | Por qué se descartó |
|---|---|
| Dejar `site_url( '/wp-login.php' )` como está | Funciona, pero ata el login social a un plugin que el código no menciona. Un cambio de plugin rompe cuatro enlaces sin que nada lo indique |
| Construir la URL completa a mano con la ruta ya resuelta | Un punto único de construcción, pero la resolución queda en código en lugar de en la API |

---

## 3. El formato de la URL no puede cambiar

**Decisión**: reproducir exactamente el formato vigente.

**Rationale**: el proveedor construye la URL de redirección OAuth a partir de la
URL de login **con el parámetro del proveedor dentro**. Verificado siguiendo el
flujo completo contra producción:

```
GET /login/?loginSocial=google
  → 302 https://accounts.google.com/o/oauth2/v2/auth?...
    redirect_uri=https%3A%2F%2Fmuyunicos.com%2Flogin%2F%3FloginSocial%3Dgoogle

GET /login/?loginSocial=facebook
  → 302 https://www.facebook.com/v19.0/dialog/oauth?...
```

Si el formato pasara de `RUTA?loginSocial=PROVEEDOR&redirect=DESTINO` a
`RUTA?redirect=DESTINO&loginSocial=PROVEEDOR` (orden distinto) o a
`RUTA?action=login&loginSocial=...`, la `redirect_uri` cambiaría y el proveedor
podría rechazarla.

Por eso el fix no puede ser un `add_query_arg()` sobre la URL de login: hay que
reconstruir la misma cadena, cambiando solo cómo se obtiene la ruta.

---

## 4. Preservación del subdominio

**Decisión**: construir sobre el host de la petición.

**Rationale**: verificado que el proveedor respeta el host de origen.

| Origen | `redirect_uri` |
|---|---|
| `us.muyunicos.com/login/?loginSocial=google` | `us.muyunicos.com/login/?loginSocial=google` |
| `mexico.muyunicos.com/login/?loginSocial=google` | `mexico.muyunicos.com/login/?loginSocial=google` |

Un comprador de `us.` que autentica y vuelve a la raíz perdería su moneda y su
zona de precios.

---

## 5. Los dos destinos de redirección

**Decisión**: el helper acepta el destino como parámetro.

**Rationale** (verificado en código):

| Archivo | Línea | Cómo calcula el destino |
|---|---|---|
| `inc/auth-modal.php` | 26 | `$_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']` con esquema detectado |
| `inc/checkout.php` | 348 | `wc_get_checkout_url()` |

Ambos son correctos en su contexto: el modal vuelve a la página desde la que se
abrió, el checkout vuelve al checkout. Un destino fijo rompería uno de los dos.

---

## 6. El WAF: un segundo bloqueo, de otra naturaleza

**Decisión**: documentarlo como incidente y como restricción de la verificación,
sin incluir un cambio de configuración en el alcance de esta feature.

**Rationale**: durante la verificación se descubrió que el WAF del edge en nivel
de seguridad Alto bloqueaba el callback de OAuth con **403 Forbidden**.

Aislado parametro a parametro:

| Parámetro | Resultado en nivel Alto |
|---|---|
| `?loginSocial=google` | 302 (pasa) |
| `?scope=email+profile` | 302 (pasa) |
| `?scope=userinfo.email` | 302 (pasa) |
| `?scope=userinfo.profile` | **403** |
| `?z=a.profile` | **403** |
| `?z=a.pro`, `?z=profilea`, `?z=a.bprofile` | 200 (pasan) |

**El disparador es la secuencia `.profile`** (punto seguido de profile), en
cualquier parámetro y en cualquier ruta del sitio. Google siempre devuelve
`userinfo.profile` dentro del `scope` al volver del consentimiento, de modo que el
callback quedaba bloqueado siempre, con un código de autenticación válido.

El WAF respondió con `Server: hcdn`, `Content-Type: text/plain` y cuerpo
`Forbidden`: WordPress no se ejecutaba.

**Resolución (2026-10-02)**: el mantenedor ajustó el nivel de seguridad de la CDN
de Hostinger de **Alto** a **Medio**. Verificado tras el cambio:

| Prueba | Nivel Medio |
|---|---|
| Callback de Google con `userinfo.profile` | 200 (funciona) |
| Login real con Google y Facebook | Completado (verificado por el mantenedor) |
| Inyección SQL cruda (`' OR '1'='1`) | 301 a página de aviso, no ejecuta |
| `<script>alert(1)</script>` | 301 a página de aviso, no ejecuta |
| Bloqueo de bots por país en subdominio | Sigue activo: bot 429, humano 302 |

**Alcance para esta feature**: ninguno del lado del código. El fix de URLs no
arregla el WAF ni al revés: son dos capas independientes. La feature solo
documenta el hallazgo y verifica que, tras el ajuste del WAF, el flujo completo
funcione.

**Nota operativa**: el ajuste Alto → Medio fue necesario porque el WAF no
contempla callbacks OAuth legítimos. Si en el futuro el nivel vuelve a subir y
el login se rompe, el primer sospechoso es esta regla, no el tema.

---

## 7. Dónde vive el helper

**Decisión**: en `inc/auth-modal.php`, no en un módulo nuevo.

**Rationale**: `auth-modal.php` ya es el dueño de la autenticación del frontend y
`checkout.php` ya consume sus enlaces. Un módulo `social-login.php` para una
función de una docena de líneas sería fragmentar sin benefit, contra el principio
I. `inc/icons.php` se carga una vez y muy temprano, así que podría alojar el
helper, pero mezclaría dos responsabilidades sin relación.

**Coste aceptado**: el checkout depende de un módulo que representa la
autenticación del modal. Se documenta con un comentario en el punto de uso.

---

## 8. Los iconos del modal

**Decisión**: mover 4 iconos a `inc/icons.php`.

**Rationale** (verificado en código, línea por línea):

| Línea de `inc/auth-modal.php` | Icono | ¿Existe en `inc/icons.php`? |
|---|---|---|
| 32 | cerrar (X) | ✅ `close` |
| 54 | chevron | ❌ agregar |
| 70 | chevron | ❌ **mismo icono** que la 54 (verificado con `md5sum`) |
| 90 | chevron | ❌ **mismo icono** que las anteriores |
| 107 | Google | ❌ **agregar** (no existe; `facebook` sí existe) |

Las líneas 54, 70 y 90 son **el mismo SVG**: `md5sum` idéntico. Tres usos, una
sola entrada al repositorio.

Google no existía en el repositorio: la v1 de esta investigación afirmó que sí,
por_error, al ver que `checkout.php` lo consumía con `mu_get_icon()`. El consumo
existe pero la entrada no.

**SVG que quedan fuera de alcance y por qué**:

| Ubicación | Razón |
|---|---|
| `inc/ui.php:139` y `:204` | Fallbacks deliberados dentro de `function_exists( 'mu_get_icon' )` |
| `inc/products-core.php:45` | SVG de un solo uso, no es un icono reutilizable |
| `inc/geo.php:645` | SVG embebido en un bloque de salida |
| `inc/cart-restriction.php:160` | Ídem |
| `inc/icons.php` | Es el repositorio: ahí van, no se tocan |

**Total real**: 31 SVG inline en 6 archivos del tema, de los cuales 4 están en el
alcance de esta feature. Los otros 27 quedan documentados, no resueltos.

---

## 9. Verificación del caso sin el plugin de ocultación

**Decisión**: registrarlo como verificación manual del mantenedor.

**Rationale**: el FR-006 pide que el login funcione con el plugin activo y
desactivado. Desactivar un plugin en producción no se puede hacer desde el
repositorio, y no hay staging. La verificación requiere que el mantenedor lo
desactive en el panel, pruebe, y lo reactive.

**Riesgo documentado**: si algo sale mal durante esa prueba, el login por usuario
y contraseña queda accesible en la ruta estándar. Es reversible reactivando el
plugin, pero hay que saber que el procedimiento existe.

---

## Resumen de decisiones

| # | Decisión | Alternativa descartada |
|---|---|---|
| 1 | Construir la URL por API, no por `site_url()` | Dejar el código actual: ata el login a un plugin que el código no menciona |
| 2 | Reproducir el formato exacto de la URL actual | `add_query_arg()` sobre la URL de login |
| 3 | Construir sobre el host de la petición | Usar el dominio raíz y redirigir después |
| 4 | El helper acepta el destino como parámetro | Un destino fijo, que rompería uno de los dos contextos |
| 5 | El helper vive en `inc/auth-modal.php` | Un módulo nuevo para una función |
| 6 | Mover 4 iconos (3 usos del mismo chevron) | Solo los 2 sociales |
| 7 | Verificación sin el plugin = manual | Gate automático, imposible sin staging |
| 8 | El WAF se documenta, no se arregla en código | Es una configuración del panel, ajena a esta feature |

## Aclaraciones

Ninguna pendiente. La premisa original se corrigió con evidencia verificada
contra producción, el formato de la URL se comprobó siguiendo el flujo OAuth
completo, y el comportamiento del WAF se aisló parámetro a parámetro.
