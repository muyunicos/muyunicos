# Contrato: API de prefijo de idioma de la petición

**Feature**: `005-login-language-redirect`
**Módulo**: `inc/compat-wplingua.php` (autocargado desde `inc/geo.php`; ya cargado por
`functions.php` antes de `auth-modal`, `login` y `checkout`)

## C-1. `mu_wplingua_request_prefix(): string`

- **Devuelve**: `''`, `/pt` o `/en`. Nunca otro valor.
- **Fuente**: la URI ORIGINAL de la petición capturada al cargar el módulo (antes de
  `init`, donde wpLingua reescribe la URL) y la lista de prefijos que ya mantiene el
  tema (`mu_wplingua_language_prefixes()`).
- **Garantías**: idempotente entre llamadas (valor memoizado con `static`), sin I/O y
  sin escritura. Si no hay prefijo detectable, devuelve `''` (idioma origen).
- **No hace**: no consulta la API de wpLingua, no mira el país del subdominio, no mira
  el navegador, no falla si wpLingua está desactivado.

## C-2. `mu_wplingua_localize_uri( string $uri ): string`

- **Entrada**: ruta o URI con o sin query (`/tienda/?orderby=popularity`).
- **Devuelve**: la misma URI con el prefijo de la petición delante.
- **Garantías**:
  - Si el prefijo es `''`, devuelve la entrada sin cambios.
  - Si la URI ya lleva el prefijo, NO lo duplica (delega en `muyu_clean_uri()`).
  - No normaliza ni reordena la query; no toca el fragmento.
  - Si `muyu_clean_uri()` no está disponible, devuelve la entrada sin cambios.

## C-3. `mu_wplingua_localize_url( string $url ): string`

- **Entrada**: URL absoluta (`https://br.muyunicos.com/tienda/`).
- **Devuelve**: la misma URL con la ruta localizada (C-2).
- **Garantías**: conserva scheme, host, puerto, query y fragmento. Si la ruta no cambia,
  devuelve exactamente la URL de entrada. No cambia el host: no se puede usar para
  "mover" un enlace a otro subdominio.

## C-4. `mu_wplingua_prefix_in_urls( array $urls ): string`

- **Entrada**: lista de URLs o rutas (candidatos: destino pedido, destino original,
  procedencia de la petición).
- **Devuelve**: el primer prefijo de la lista de prefijos encontrado en cualquiera de
  los candidatos, o `''` si ninguno la lleva.
- **Uso**: la pantalla de acceso (`/login/`) no tiene prefijo ni estado de idioma, así
  que el idioma del visitante se deduce sólo de las URLs que trae la propia petición.
- **No hace**: no inventa idioma por país, geolocalización ni navegador (FR-005).

## Consumidores

| Consumidor | Contrato usado | Destino resultante |
|---|---|---|
| `inc/auth-modal.php` (destino del login social) | C-2 sobre la URL actual | Mismo host, ruta e idioma de la página; el formato `RUTA?loginSocial=PROVEEDOR&redirect=DESTINO` no cambia |
| `inc/checkout.php` (destino del bloque social) | C-3 sobre el permalink del checkout | Idempotente: si el permalink ya viene localizado, no cambia |
| `inc/login.php` (destino de clientes) | C-4 + C-2 | "Mi Cuenta" en el idioma de la petición; sin señal, comportamiento actual |

## Fuera del contrato (prohibido)

- Aplicar el prefijo a rutas técnicas (`/wp-admin`, `/wp-json`, `/wp-login`, la ruta de
  acceso vigente, AJAX) o a destinos de otros hosts.
- Duplicar el prefijo o forzar el dominio raíz.
- Cambiar el orden o los nombres de los parámetros del enlace de login social.
