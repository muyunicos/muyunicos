# Implementation Plan: Conservar el idioma (wpLingua) al iniciar sesión

**Branch**: `005-login-language-redirect` | **Date**: 2026-10-16 | **Spec**: [spec.md](./spec.md) | **Research**: [research.md](./research.md) | **Contract**: [contracts/language-prefix.md](./contracts/language-prefix.md)

## Summary

Dos puntos del tema construyen un destino de redirección leyendo la URL actual en un
momento en que wpLingua ya le quitó el prefijo de idioma (`init`, prioridad 1): el
modal de autenticación (destino del login social, `inc/auth-modal.php`) y la
redirección de clientes a "Mi Cuenta" (`inc/login.php`). Se agregan cuatro helpers al
módulo que ya resuelve ese mismo problema para el 301 canónico
(`inc/compat-wplingua.php`) y se los aplica en esos dos puntos, más una aplicación
defensiva e idempotente en el bloque social del checkout.

## Technical Context

- **PHP**: 8.5 (local 8.5.9 · producción 8.5.4). `php -l` obligatorio (principio IX).
- **Dependencias**: WordPress + WooCommerce, GeneratePress 3.6.1 (padre, solo lectura),
  wpLingua, Nextend Social Login, LiteSpeed Cache 7.9. **Sin dependencias nuevas.**
- **Datos**: ninguno. No se lee ni se escribe base de datos, opciones ni transients.
- **Entorno**: 10 hosts servidos por una sola instalación. Producción es el único entorno.
- **Verificación**: `php -l`, `Select-String` (invariantes), `curl.exe` contra
  producción y un login real del mantenedor para el flujo OAuth.

## Constitution Check

| Principio | Cómo lo cumple |
|---|---|
| I. Modularidad pragmática | No hay funcionalidad nueva: los helpers extienden la responsabilidad del módulo que ya posee el problema, en vez de crear una segunda fuente. `inc/compat-wplingua.php` ya se autocarga desde `inc/geo.php` (guía §4) y no se agrega nada a `functions.php`. |
| II. Carga condicional de assets | No se agrega CSS/JS ni inline. Los puntos que se tocan (modal en el pie y pantalla de login) ya se ejecutan hoy. |
| V. SEO e i18n multi-país | Refuerza la invariante de que los enlaces internos apunten al subdominio y al idioma actuales. No se toca canonical ni hreflang. |
| VI. Caché como restricción de diseño | No altera la separación de caché por host ni purga nada. El prefijo se resuelve una vez por request (`static`), sin consultas ni I/O. Como el `href` vive en HTML cacheado, la verificación purga por host antes de medir. |
| VII. Observabilidad e incidentes | No se agrega logging ni dato de usuario; el incidente queda documentado en la guía §7 con su medición. |
| IX. Verificación antes de desplegar | `php -l` de cada archivo; el quickstart incluye los `curl.exe` con su salida y el caso de fallo. Lo no automatizable (OAuth real) se declara como tal. |
| X. Perímetro de seguridad y plugins | No se implementa autenticación propia: el tema corrige el destino que ya construía. Rutas técnicas y destinos de terceros quedan fuera (FR-012). |

**Gate**: sin violaciones; no se registra excepción en §8.

## Project Structure

| Archivo | Cambio |
|---|---|
| `inc/compat-wplingua.php` | 4 helpers nuevos (contrato en `contracts/language-prefix.md`) |
| `inc/auth-modal.php` | El destino del login social usa el helper (solo el valor del destino) |
| `inc/login.php` | El destino de clientes conserva el idioma deducido de la propia petición |
| `inc/checkout.php` | Aplicación idempotente del helper al destino del bloque social |
| `MIGRATION-GUIDE.md` | §3 (acoplamiento), §7 (incidente), §8 (observación de los redirects de la tienda) |

Sin cambios en `functions.php`, `Tema-GeneratePress/`, plugins, `css/` ni `js/`.

## Diseño

1. `mu_wplingua_request_prefix()` → `''` | `/pt` | `/en`, leído de la URI original
   capturada antes de `init` y contrastado contra `mu_wplingua_language_prefixes()`.
2. `mu_wplingua_localize_uri( $uri )` → aplica el prefijo si corresponde, sin
   duplicarlo (delega en `muyu_clean_uri()`; si no está disponible, devuelve la URI).
3. `mu_wplingua_localize_url( $url )` → mismo criterio sobre una URL absoluta,
   conservando host, puerto, query y fragmento.
4. `mu_wplingua_prefix_in_urls( array $urls )` → primer prefijo encontrado (para `/login/`).

Los cuatro con guarda `function_exists`, sin estado global nuevo y sin escritura.

**No cambia**: la forma de la URL de login social ni el orden de sus parámetros; el
destino de los roles con acceso al panel; la ruta de `/login/`; la lista de prefijos;
ninguna otra ruta del sitio.

## Riesgos y reversibilidad

| Riesgo | Mitigación |
|---|---|
| El proveedor de login social rechaza el destino | Sólo cambia el valor del destino: mismo host y misma ruta de acceso (research §4) |
| Prefijo duplicado | `muyu_clean_uri()` no lo duplica; hay caso límite explícito en la spec |
| Un comprador en español recibe portugués | El prefijo sale sólo de la URL pedida; nunca del país ni del navegador (FR-005) |
| El HTML cacheado sirve el enlace viejo | Purga por host antes de medir (guía §2) |
| Regresión en el login de usuarios con panel | El filtro de roles no se altera: siguen recibiendo su destino pedido |

**Rollback**: revertir los 4 archivos del tema (`git revert` del commit) y purgar la
caché de los hosts afectados. Sin migración de datos, sin configuración, sin pasos en
el servidor.

## Verificación (detalle en `quickstart.md`)

1. `php -l` de cada archivo tocado (principio IX).
2. `Select-String` de invariantes: una sola lista de prefijos, ninguna ruta de login
   escrita a mano, ningún destino armado desde `$_SERVER['REQUEST_URI']` sin el helper.
3. `curl.exe` contra producción: enlace social en `/pt/`, `/en/`, `/pt/tienda/` y en el
   dominio raíz; comparación antes y después.
4. Manual (mantenedor): OAuth real con Google y Facebook desde `/pt/` y `/en/`, login
   por usuario y contraseña, enlace de salir (FR-013) y checkout con carrito.
5. `git diff --name-only`: nada fuera del child theme.

**Caso de fallo declarado** (principio IX, riesgo alto por ser autenticación): si el
prefijo se aplicara a una URL sin idioma, el comprador de `br.`/`us.` que navega en
español terminaría en portugués o inglés; si no se aplicara, volvería a la base. Ambos
se verifican con los `curl` del quickstart y con el login real antes de cerrar.

## Fases de implementación

Ver `tasks.md`.
