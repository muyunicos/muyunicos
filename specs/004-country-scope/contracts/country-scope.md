# Contrato — Alcance por país (`inc/country-scope.php`)

**Versión**: 1.0.0 | **Desde**: 2026-10-16 | **Guía**: §6 del `MIGRATION-GUIDE.md`

Este documento es la referencia para **declarar una ruta o un elemento que sólo existe en
países determinados**, sin volver a resolver el problema. Todo lo que sigue está
implementado y verificado en `inc/country-scope.php`.

## 0. Regla de oro

El **país es el host**. `muyunicos.com` es Argentina; `mexico.muyunicos.com`, `co.`, `es.`,
`cl.`, `pe.`, `br.`, `ec.`, `us.`, `cr.` son sus países. La fuente de país es
`inc/geo.php` y no se duplica. El idioma (los prefijos `/pt/` y `/en/` de wpLingua) es otra
dimensión: esta API **no** restringe por idioma.

## 1. Contexto de país

| Función | Entrada | Salida | Notas |
|---|---|---|---|
| `muyu_get_current_country()` | — | `'AR'`, `'MX'`, `'CO'`, … | Wrapper de `muyu_get_current_country_from_subdomain()`, en mayúsculas |
| `muyu_get_current_host()` | — | `'mexico.muyunicos.com'` | Sin puerto ni `www.`; `''` si no se pudo determinar |
| `muyu_is_country( $countries )` | `'AR'` \| `[ 'AR', 'MX' ]` \| `''` | `bool` | `''` o `[]` = todos los países (sin restricción) |

```php
if ( muyu_is_country( [ 'AR' ] ) ) {
    // sólo Argentina
}

if ( muyu_is_country( [ 'AR', 'MX', 'CL' ] ) ) {
    // tres países
}
```

## 2. Registro de rutas con alcance por país

`muyu_get_country_scope_routes()` devuelve el registro normalizado (países en mayúsculas,
sin duplicados). Estructura de cada entrada:

```php
'slug-de-la-pagina' => [
    'countries' => [ 'AR' ],   // países donde la ruta se publica
    'redirect'  => 'shop',     // destino en el resto: 'shop' | 'home' | URL absoluta
    'label'     => 'Texto',    // texto del enlace, cuando hay uno
],
```

| Función | Entrada | Salida |
|---|---|---|
| `muyu_get_country_scope_routes()` | — | registro completo (normalizado) |
| `muyu_get_country_scope_route( $slug )` | `'envios'` | entrada normalizada \| `null` |
| `muyu_get_country_scope_route_for_request()` | — | slug de la ruta pedida \| `null` |
| `muyu_get_country_scope_redirect_url( $route )` | entrada | URL absoluta en el host actual |
| `muyu_get_country_scope_hreflang_tags()` | — | `[ [ 'hreflang' => …, 'url' => … ] ]` \| `[]` |

**Extensión sin editar el módulo** (el registro es filtrable):

```php
add_filter( 'muyu_country_scope_routes', function ( $routes ) {
    $routes['calculadora-de-stickers'] = [
        'countries' => [ 'AR' ],
        'redirect'  => 'shop',
        'label'     => 'Calculadora de Stickers',
    ];
    return $routes;
} );
```

## 3. Contrato del guard HTTP

Corre en `template_redirect` **prioridad 15** (después del 301 canónico del core en p10,
antes de `handle_redirects` de `digital-restriction` en p20 y del render del tema).

| Host | Ruta | Respuesta |
|---|---|---|
| `muyunicos.com` | `/envios/` | `200` (la página se sirve normal) |
| cualquiera de los 9 subdominios | `/envios/` | `302` → `/tienda/` del mismo host |
| `br.muyunicos.com` | `/pt/envios/` | `302` → `https://br.muyunicos.com/pt/tienda/` |
| `us.muyunicos.com` | `/envios/` | `302` → `https://us.muyunicos.com/en/tienda/` |
| cualquier host | cualquier otra ruta | sin cambios (el guard sale sin emitir nada) |
| cualquier host | `/envios/` en admin, REST, AJAX, cron, feed | sin cambios |

Invariantes del `302`:

1. **Mismo host**: el destino se construye con `muyu_get_current_host()`. El comprador no
   sale del subdominio que está navegando (precios en su moneda, catálogo digital) y
   `wp_safe_redirect()` no puede derivarlo a wp-admin por un host no permitido.
2. **Prefijo de idioma del país** (`BR→/pt`, `US→/en`) aplicado con `muyu_clean_uri()`, que
   no duplica un prefijo ya presente (wpLingua puede haberlo reescrito en el permalink).
3. **Aplicado también a bots**: es una comprobación O(1) y el `302` es lo que retira la URL
   del índice de ese país.
4. **Trazabilidad**: la respuesta lleva `X-MU-Country-Scope: redirect`. Su ausencia con un
   `200` significa que el guard no corrió (típicamente `functions.php` sin actualizar en el
   servidor, guía §1), no que la ruta esté bien.

## 4. Elementos del tema (no rutas)

Para un `<li>`, un botón, un bloque o un fragmento de plantilla:

```php
if ( muyu_is_country( [ 'AR' ] ) ) {
    echo '<li>…</li>';
}
```

Caso real implementado en `inc/ui.php` (pie de página): el enlace sale del **mismo
registro** que la ruta, para que no puedan desincronizarse.

```php
$mu_envios = function_exists( 'muyu_get_country_scope_route' )
    ? muyu_get_country_scope_route( 'envios' )
    : null;

if ( null === $mu_envios || muyu_is_country( $mu_envios['countries'] ) ) {
    echo '<li><a href="/envios/">' . esc_html( $mu_envios['label'] ) . '</a></li>';
}
```

Regla: preferir no emitir el HTML (esto) antes que emitirlo y ocultarlo con CSS. Menos
bytes, menos pintado y nada que se pueda ver por accidente si el CSS no carga.

## 5. Elementos que imprime un plugin o wp-admin (sólo CSS)

Cuando el elemento no lo genera el tema no hay PHP donde intervenir. `inc/geo.php` ya
inyecta la clase del país en el `<body>` (`mu-country-ar`, `mu-country-mx`, …):

```css
/* override GP: elemento que sólo debe verse en Argentina */
body:not(.mu-country-ar) .mi-elemento {
    display: none;
}
```

Precedente vigente: precios del overlay de Jetpack Search en
`css/components/global-ui.css`. Límite: el HTML igual viaja al navegador.

## 6. Casos que esta API no cubre (por diseño)

| Caso | Por qué | Qué hacer |
|---|---|---|
| Elemento de un menú de WordPress | Sale de la base de datos, no de código | Quitarlo del menú en wp-admin (Apariencia → Menús) y verificarlo a mano |
| Variante de idioma del dominio raíz (`/pt/envios/`) | El país es Argentina; el idioma es otra dimensión | Es alcance de wpLingua, no de este registro |
| Contenido dentro de una página compartida (un bloque al final) | La página es una sola en los 10 hosts | Envolver el bloque con `muyu_is_country()` si el bloque lo imprime el tema; si lo imprime el editor, no hay intervención limpia |
| Restringir por visitante (IP, sesión, rol) | No es país | Fuera de esta API |

## 7. Agregar una ruta nueva (procedimiento)

1. Agregar la entrada en `muyu_get_country_scope_routes()` (o vía el filtro
   `muyu_country_scope_routes`): slug, `countries`, `redirect`, `label`.
2. Condicionar el enlace que apunte a esa ruta con `muyu_is_country( $route['countries'] )`.
3. Verificar en local con los escenarios de `quickstart.md` §A.6 (host + ruta).
4. Verificar en producción con `curl -sI` sobre dos hosts (uno publicador, uno no) y
   purgar la caché del host afectado.

No hace falta tocar el guard, `seo-hreflang.php` ni el sitemap: los tres se derivan del
registro.

## 8. Verificación

- Lógica pura (país, ruta normalizada, destino, hreflang, sitemap): `quickstart.md` §A.6,
  ejecutable en local con stubs, sin WordPress.
- Respuesta HTTP real, cabecera de trazabilidad, sitemap y pie de página: `quickstart.md`
  §B, contra producción.

