# Quickstart: Conservar el idioma (wpLingua) al iniciar sesión

**Feature**: `005-login-language-redirect`
**Fase A**: local, ejecutable ahora (harness de lógica pura + invariantes de texto).
**Fase B**: contra producción, requiere el deploy del mantenedor y, en los pasos marcados,
una sesión real.

## Fase A — Verificación local (ejecutada el 2026-10-16)

### A.1 Sintaxis (principio IX)

```powershell
php -l inc\compat-wplingua.php; php -l inc\auth-modal.php; php -l inc\login.php; php -l inc\checkout.php
# esperado: "No syntax errors detected" en los cuatro (PHP 8.5.9 local)
```

### A.2 Harness de la lógica pura (stubs, sin WordPress)

**El harness de esta feature NO está versionado** (vivía en `%TEMP%` y se perdió con la limpieza
el temporal): es deuda declarada. Definía `ABSPATH`, los stubs mínimos (`wp_unslash`,
`wp_parse_url`, `add_filter`) y la lógica real de `geo.php` que el módulo consume
(`muyu_clean_uri`, `muyu_country_language_prefix`, `muyu_get_countries_data`), e incluía el
módulo real `inc/compat-wplingua.php`. Para volver a correrlo hay que reconstruirlo con el
patrón vigente: arnés **versionado** que ejecuta el módulo real (ver
`specs/007-tarjetas-reloj-resenas/harness/README.md` y `specs/006-ai-translate-core/harness/`).

Cobertura del harness:

| Escenario (`MU_TEST_URI`) | Aserciones |
|---|---|
| `/pt/tienda/?orderby=popularity` | prefijo `/pt`; agrega el prefijo; no lo duplica; respeta el prefijo explícito vacío; conserva host, query y fragmento; no toca rutas relativas; detecta `/pt` y `/en` en las URLs candidatas |
| `/tienda/` (idioma origen en `br.`) | prefijo `''`; `localize_uri` y `localize_url` **sin cambios**; `prefix_in_urls` no inventa idioma |
| `/entrega/` | no confunde `/en` con `/entrega/` (falso positivo descartado) |
| `/en/tienda/` | detecta `/en`; idempotente |

### A.3 Invariantes de texto

```powershell
Select-String -Path inc\*.php -Pattern "=> '/pt'|=> '/en'"      # esperado: solo geo.php:68-69
Select-String -Path inc\*.php,functions.php -Pattern 'wp-login'  # esperado: el fallback de auth-modal.php:55 y la exclusión técnica de compat-wplingua.php
git diff --name-only                                             # esperado: nada bajo Tema-GeneratePress/
```

## Fase B — Verificación en producción (mantenedor)

### B.1 Antes de medir: purgar la caché del host

El `href` viaja dentro del HTML cacheado por host (guía §2). Purgar LiteSpeed en el host a
medir y repetir la request; si el enlace viejo sigue apareciendo, la respuesta estaba
cacheada, no es un fallo del fix.

### B.2 Enlace de login social por host (automatizable)

```powershell
curl.exe -s -A "Mozilla/5.0" "https://br.muyunicos.com/pt/tienda/" |
  Select-String 'loginSocial'
# esperado: redirect=https%3A%2F%2Fbr.muyunicos.com%2Fpt%2Ftienda%2F

curl.exe -s -A "Mozilla/5.0" "https://br.muyunicos.com/pt/" | Select-String 'loginSocial'
# esperado: redirect=https%3A%2F%2Fbr.muyunicos.com%2Fpt%2F

curl.exe -s -A "Mozilla/5.0" "https://us.muyunicos.com/en/" | Select-String 'loginSocial'
# esperado: redirect=https%3A%2F%2Fus.muyunicos.com%2Fen%2F

curl.exe -s -A "Mozilla/5.0" "https://muyunicos.com/tienda/" | Select-String 'loginSocial'
# esperado: redirect=https%3A%2F%2Fmuyunicos.com%2Ftienda%2F   (sin prefijo: idioma origen)
```

### B.3 Casos de fallo (declarados en `plan.md`, principio IX)

1. **Prefijo duplicado**: el destino no puede contener `/pt/pt/` ni `/en/en/` en ningún host.
2. **Prefijo donde no corresponde**: en el dominio raíz y en las páginas sin prefijo, el
   destino debe ser idéntico al de antes del cambio.
3. **Host ajeno**: ningún destino puede apuntar a otro subdominio (el host del enlace debe
   coincidir con el host de la página).
4. **Formato del proveedor**: la forma `RUTA?loginSocial=PROVEEDOR&redirect=DESTINO` y el
   orden de los parámetros deben ser idénticos a los de antes del cambio.
5. **Canonical y hreflang de la página con prefijo** (medido el 2026-10-16 y no afectado):
   `<link rel="canonical" href="https://br.muyunicos.com/pt/tienda/" />`. Se reverifica para
   descartar una regresión de SEO multi-país (principio V).

### B.4 Pasos manuales (no automatizables: requieren sesión o proveedor real)

1. **OAuth real, Google y Facebook** desde `/pt/` y desde `/en/`: debe volver a la misma
   página, mismo idioma y mismo subdominio (SC-004, US1).
2. **Usuario y contraseña por el modal** en `/pt/`: la página se recarga y sigue en portugués
   (US2, escenario 1).
3. **Usuario y contraseña en la pantalla de acceso** con `redirect_to` prefijado: termina en
   "Mi Cuenta" del mismo idioma (US2, escenario 2).
4. **Restablecer contraseña** de una cuenta de prueba: el flujo se completa y el destino
   respeta el criterio de idioma (US2, escenario 5).
5. **Enlace de salir (FR-013)**: con sesión abierta en `/pt/` y `/en/`, leer el `href` de
   "Salir". Si ya conserva el idioma, se registra la evidencia y no se toca código; si no lo
   conserva, FR-013 exige corregirlo antes de cerrar la feature.
6. **Checkout con carrito con contenido**: el bloque social del checkout conserva host e
   idioma (el permalink del checkout no se pudo medir con el carrito vacío: responde 302 al
   carrito).
7. **Roles con acceso al panel**: un usuario `shop_manager` sigue siendo enviado al destino
   que pidió (FR-006).

### B.5 Lo que queda declarado como NO verificado

- Redirects de la tienda que pierden el prefijo (FR-014): fuera de alcance, registrado en la
  guía §8. No se mide acá como requisito de esta feature.
- Cualquier flujo que exija pago real: no se ejecuta.
