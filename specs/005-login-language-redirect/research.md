# Research: Conservar el idioma (wpLingua) al iniciar sesión

**Feature**: `005-login-language-redirect` | **Date**: 2026-10-16

## 1. Fuente del idioma: la URI original, no los permalinks

**Decisión**: el prefijo se lee de la URI ORIGINAL de la petición
(`mu_wplingua_original_uri()`, `inc/compat-wplingua.php`), se contrasta contra la
lista de prefijos que ya mantiene el tema (`mu_wplingua_language_prefixes()`) y se
aplica con `muyu_clean_uri()` (`inc/geo.php`), que no duplica un prefijo presente.

**Por qué**: `$_SERVER['REQUEST_URI']` pierde el prefijo en `init` (guía §7, "LECCIÓN
CLAVE"), confirmado otra vez al medir. La alternativa era derivar el idioma de
`muyu_get_current_language()`, que delega en la API de wpLingua
(`wplng_get_language_current_id()`) y cae —como último recurso— al país del
subdominio (BR→pt, US→en): en `br.muyunicos.com` una página en español recibiría
`/pt`. La URI original no tiene ese riesgo.

**Evidencia medida** (2026-10-16, producción): `br.muyunicos.com/pt/tienda/` entrega
`redirect=https://br.muyunicos.com/tienda/`; `br.muyunicos.com/pt/` entrega
`redirect=https://br.muyunicos.com/`. En las mismas páginas, los enlaces de cuenta
sí aparecen prefijados (`/pt/mi-cuenta/downloads/`).

## 2. No confiar en que el permalink venga localizado

**Decisión**: al destino de "Mi Cuenta" se le aplica el prefijo de forma explícita e
idempotente, en lugar de asumir que el permalink ya lo trae.

**Evidencia**: en la misma instalación, `br.muyunicos.com/pt/finalizar-compra/` con el
carrito vacío responde `302` a `https://br.muyunicos.com/carrito/` (sin prefijo),
mientras que el enlace del carrito dentro de `/pt/tienda/` sí se sirve como
`/pt/carrito/`. El permalink localizado no es una garantía por request.

## 3. En `/login/` la única señal de idioma es la propia petición

**Decisión**: el idioma del visitante para el login por usuario y contraseña se
deduce **solo** de URLs presentes en la petición (destino pedido, destino original,
procedencia) con `mu_wplingua_prefix_from_urls()`. Sin señal, no se aplica prefijo:
se conserva el comportamiento actual.

**Evidencia**: `br.muyunicos.com/login/` responde `200` con `<html lang="es">`, sin
un solo enlace `/pt/` y sin cookie de wpLingua. Las únicas cookies que emite el sitio
son la sesión de la tienda y `_lscache_vary`: wpLingua no tiene estado de idioma
fuera de la URL.

**Descartado**: deducir el idioma del país del subdominio (rompería a un comprador
brasileño navegando en español) y deducirlo del navegador (no hay dato y wpLingua no
lo persiste).

## 4. El contrato con el proveedor de login social no se toca

**Decisión**: cambia **solo el valor** del parámetro de destino de
`mu_social_login_url()`; la forma `RUTA?loginSocial=PROVEEDOR&redirect=DESTINO` y el
orden de los parámetros quedan intactos.

**Por qué**: la guía §3 lo declara contrato con el proveedor y la feature 002
documentó que alterar el orden hace que el proveedor rechace la redirección. El
prefijo no cambia de host, así que el destino sigue dentro del sitio permitido.

**Alternativa descartada**: construir la URL de login social con `add_query_arg()`
u otra función que reordene parámetros.

## 5. Caché: el enlace vive dentro del HTML servido

**Decisión**: la verificación exige purgar la caché del host antes de medir.

**Por qué**: el `href` se imprime en el HTML; mientras el HTML cacheado por host
siga vigente, el enlace corregido no se sirve. Guía §2: la caché se aísla y se purga
por host.

## 6. Alcance y límites de la verificación

- **Enlace de salir (FR-013)**: verificación con sesión real en `/pt/` y `/en/`; sin
  cambio de código si ya conserva el idioma. Medido parcialmente: `home_url()` sale
  localizado en `/pt/` (6 enlaces a `https://br.muyunicos.com/pt/` en esa página).
- **Redirects de la tienda (FR-014)**: fuera de alcance; se registra la medición como
  observación en la guía §8.
- **Checkout**: el bloque social usa el permalink del checkout y se le aplica el mismo
  helper de forma idempotente. No se pudo medir sin un carrito con contenido: queda
  como paso manual del quickstart, no como supuesto silencioso.
- **Flujo OAuth real**: no es automatizable (requiere credenciales del proveedor y una
  persona); es el único criterio de la spec que queda para el mantenedor.
