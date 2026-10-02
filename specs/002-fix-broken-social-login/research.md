# Phase 0 Research: Restaurar el login social y eliminar los SVG inline

**Feature**: 002-fix-broken-social-login | **Date**: 2026-10-02

Toda la investigación se verificó empíricamente contra producción o leyendo el
código. Cuando la documentación y el comportamiento difieren, gana el
comportamiento.

---

## 1. La ruta de login real

**Decisión**: resolver la ruta mediante la API de WordPress, no fijarla.

**Rationale (verificado el 2026-10-02 contra producción)**:

| Petición | Respuesta |
|---|---|
| `GET /wp-login.php` | **404** |
| `GET /wp-login.php?loginSocial=google` | **404** |
| `GET /login/` | **200** (título "Acceder", markup de Nextend) |

WPS Hide Login hookea los filtros `site_url` y `login_url` de WordPress. Por eso
`wp_login_url()` devuelve la ruta vigente mientras que `site_url( '/wp-login.php' )`
devuelve la ruta que ya no existe. La propia documentación del plugin lo dice:
*"obviously it doesn't work with plugins or themes that hardcoded wp-login.php"*.

**Alternativas consideradas**:

| Alternativa | Por qué se descartó |
|---|---|
| Fijar la ruta a `/login/` en el código | Funciona hoy y se rompe la próxima vez que se cambie el slug o se desactive el plugin. Es el mismo bug con otra constante |
| Usar `add_query_arg()` sobre `wp_login_url()` | Produce `/login/?action=...&loginSocial=...`: formato distinto al que el proveedor ya acepta. Ver sección 2 |
| Filtrar `site_url` desde el tema para reescribir la ruta | Duplica lo que el plugin ya hace, y agrega un segundo punto de falla |

---

## 2. El formato de la URL no puede cambiar (restricción crítica)

**Decisión**: reproducir exactamente el formato vigente.

**Rationale**: el proveedor de login social construye la URL de redirección OAuth
partiendo de la URL de login **con el parámetro del proveedor dentro**. Se
verificó el comportamiento real contra producción:

```
GET /login/?loginSocial=google
  → 302 https://accounts.google.com/o/oauth2/v2/auth?...
    redirect_uri=https%3A%2F%2Fmuyunicos.com%2Flogin%2F%3FloginSocial%3Dgoogle

GET /login/?loginSocial=facebook
  → 302 https://www.facebook.com/v19.0/dialog/oauth?...
    redirect_uri=https%3A%2F%2Fmuyunicos.com%2Flogin%2F%3FloginSocial%3Dfacebook
```

El proveedor manda a Google y a Facebook una `redirect_uri` que es la URL de
login completa **con `?loginSocial=` dentro**. Ese valor se valida contra lo
registrado en el panel del proveedor.

Consecuencia: si la URL pasara de
`/login/?loginSocial=google&redirect=X` a
`/login/?redirect=X&loginSocial=google` (orden distinto) o a
`/login/?action=login&loginSocial=google`, la `redirect_uri` cambiaría y el
proveedor podría rechazarla por no coincidir con lo registrado.

**Esta restricción es la razón por la que el fix no puede ser un simple
`add_query_arg()`.** Debe construir la misma cadena, cambiando solo la ruta.

**Presupuesto que se asume**: el panel del proveedor ya tiene dado de alta el
formato vigente. El fix reproduce una URL que hoy está verificada como
funcional, así que no debería requerir reconfigurar nada. Si al probar el flujo
completo el proveedor rechazara la redirección, ese es el primer punto a
investigar.

---

## 3. Preservación del subdominio

**Decisión**: construir la URL sobre el host de la petición, no sobre el dominio
raíz.

**Rationale**: verificado que el proveedor respeta el host de origen.

| Origen | `redirect_uri` devuelto |
|---|---|
| `us.muyunicos.com/login/?loginSocial=google` | `us.muyunicos.com/login/?loginSocial=google` |
| `mexico.muyunicos.com/login/?loginSocial=google` | `mexico.muyunicos.com/login/?loginSocial=google` |

**Implicación para el fix**: usar `site_url()` con la ruta es correcto siempre
que WordPress resuelva el dominio correcto del subdominio actual, que es lo que
ocurre en este proyecto: el subdominio es el mismo sitio, no un multisitio. Un
comprador de `us.` que autentica y vuelve a `muyunicos.com` perdería su zona de
precios y su moneda.

---

## 4. Los dos lugares que calculan el destino de redirección

**Decisión**: que el helper acepte el destino como parámetro.

**Rationale (verificado en código)**:

| Archivo | Línea | Cómo calcula el destino |
|---|---|---|
| `inc/auth-modal.php` | 26 | `$_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']` con esquema detectado |
| `inc/checkout.php` | 348 | `wc_get_checkout_url()` |

Son dos mecanismos distintos y ambos son correctos en su contexto: el modal
vuelve a la página desde la que se abrió, el checkout vuelve al checkout. Un
helper que hardcodeara uno de los dos rompería el otro caso. El parámetro
`$redirect_to` resuelve la diferencia sin duplicar la lógica.

---

## 5. Dónde vive el helper

**Decisión**: en `inc/auth-modal.php`, no en un módulo nuevo.

**Rationale**: `auth-modal.php` ya es el dueño de la autenticación del frontend,
y `checkout.php` ya consume sus enlaces. Crear `inc/social-login.php` para una
función de una docena de líneas sería fragmentar sin benefit, en contra del
principio I. `inc/icons.php` se carga una vez y muy temprano, así que podría
alojar el helper, pero mezclaría dos responsabilidades sin relación.

**Coste aceptado**: el checkout depende de un módulo que representa la
autenticación modal. Se documenta con un comentario en el punto de uso.

---

## 6. Los iconos del modal

**Decisión**: mover 4 iconos a `inc/icons.php`.

**Rationale (verificado en código)**:

| Línea | Icono | ¿Existe en `inc/icons.php`? |
|---|---|---|
| `auth-modal.php:32` | cerrar (X) | ✅ `close` |
| `auth-modal.php:54` | desplegar (chevron) | ❌ agregar |
| `auth-modal.php:70` | desplegar (chevron) | ❌ mismo icono, una sola entrada |
| `auth-modal.php:90` | desplegar (chevron) | ❌ mismo icono |
| `auth-modal.php:107` | Google | ✅ `google` ya usado en checkout |
| `auth-modal.php:110` | Facebook | ✅ `facebook` |

Tres iconos distintos a agregar (`chevron-down`, más los que usen las líneas 70 y
90, que se verifican en el plan por ser distintas de la 54). Google y Facebook ya
existen: `checkout.php:376` y `:380` ya los consume con `mu_get_icon()`. Es
inconsistencia dentro del mismo proyecto, no una decisión de diseño.

**SVG que quedan fuera de alcance y por qué**:

| Ubicación | Razón para no tocarlo |
|---|---|
| `inc/ui.php:139` y `:204` | Son fallbacks deliberados dentro de `function_exists( 'mu_get_icon' )` |
| `inc/products-core.php:45` | SVG de un solo uso, no es un icono reutilizable |
| `inc/geo.php:645` | SVG embebido en un bloque de salida |
| `inc/cart-restriction.php:160` | Ídem |
| `inc/icons.php` | Es el repositorio: ahí van, no se tocan |

**Total real**: 31 SVG inline en 6 archivos del tema, de los cuales 4 están en el
alcance de esta feature. Los otros 27 quedan documentados, no resueltos.

---

## 7. Verificación del caso sin el plugin de ocultación

**Decisión**: registrarlo como verificación manual del mantenedor, no como gate
automático.

**Rationale**: el FR-006 pide que el login funcione con el plugin de ocultación
activado y desactivado. Desactivar un plugin en producción no se puede hacer
desde el repositorio, y no hay staging donde probar el escenario. La
verificación requiere que el mantenedor desactive el plugin en el panel, pruebe,
y lo reactive.

**Riesgo documentado**: si algo sale mal durante esa prueba, el login por
usuario y contraseña queda accesible en la ruta estándar. Es reversible
reactivando el plugin, pero hay que saber que el procedimiento existe.

---

## Resumen de decisiones

| # | Decisión | Alternativa descartada |
|---|---|---|
| 1 | Resolver la ruta con la API de WordPress | Fijar `/login/` en el código |
| 2 | Reproducir el formato exacto de la URL actual | `add_query_arg()` sobre la URL de login |
| 3 | Construir sobre el host de la petición | Usar el dominio raíz y redirigir después |
| 4 | El helper acepta el destino como parámetro | Un destino fijo, que rompería un caso de los dos |
| 5 | El helper vive en `inc/auth-modal.php` | Un módulo nuevo de una función |
| 6 | Mover 4 iconos al repositorio | Solo los 2 sociales; los otros 2 quedarían fuera |
| 7 | Verificación sin el plugin = manual | Gate automático, imposible sin staging |

## NEEDS CLARIFICATION

Ninguna. La ruta vigente se verificó empíricamente contra producción, el formato
de la URL se verificó siguiendo el flujo OAuth completo hasta la redirección del
proveedor, y los dos mecanismos de redirección se leyeron en el código.
