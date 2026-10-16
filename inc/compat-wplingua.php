<?php
/**
 * Muy Únicos — Compatibilidad wpLingua: conservar el prefijo de idioma en el 301 canónico
 *
 * PROBLEMA (medido en producción el 2026-10-16, sobre los 4 hosts):
 *
 *     GET /pt/?_gl=1*abc  →  301  Location: /?_gl=1%2Aabc   (pierde el idioma)
 *     GET /pt/?_gl=abc    →  200  (sin redirección)
 *     GET /pt/?a=b+c      →  301  Location: /?a=b%20c
 *     GET /pt/?a=b@c      →  301  Location: /?a=b%40c
 *
 * Ese 301 lo emite el CORE (`X-Redirect-By: WordPress`), no wpLingua, ni el
 * tema, ni el CDN de Hostinger. Cadena causal:
 *
 *  1. wpLingua resuelve /pt/ como la home (su `rel=shortlink` apunta a /), así
 *     que el objeto consultado es la home.
 *  2. `redirect_canonical()` reconstruye la URL canónica escapando los
 *     caracteres que venían crudos (`*` → %2A, `+` → %20, `@` → %40). Al no
 *     coincidir con la solicitada, responde 301 a la canónica, que es la home
 *     SIN el prefijo de idioma.
 *  3. Esos caracteres crudos llegan en `_gl`, el parámetro que el linker de
 *     Google inyecta al hacer clic en un enlace cross-domain. Los enlaces del
 *     selector de país son cross-domain (*.muyunicos.com), así que son los
 *     candidatos naturales a recibirlo.
 *
 * Impacto: quien hacía clic en la bandera de Brasil (→ /pt/) aterrizaba en la
 * home en español del subdominio correcto, con la URL sin prefijo; y el Hero
 * Banner, que resolvía el idioma por país al no encontrar el prefijo, mostraba
 * el idioma equivocado (br/en/ → PT, us/pt/ → EN).
 *
 * SOLUCIÓN: el filtro `wp_redirect` — que el core aplica a wp_redirect() y a
 * wp_safe_redirect(), incluido redirect_canonical() — re-inyecta el prefijo de
 * idioma cuando el destino es el mismo host con la misma ruta pero sin él, y
 * normaliza la query del destino. Medido: la query normalizada (%2A) ya no
 * provoca el 301, así que la redirección se corta en la URL correcta.
 *
 * Salvaguardas:
 *  - No actúa en el admin.
 *  - Solo en redirecciones 301 / 302 / 307.
 *  - Solo hacia hosts del proyecto: nunca pasarelas de pago ni terceros.
 *  - Nunca en rutas técnicas (/wp-admin, /wp-json, archivos .php...).
 *  - No reescribe si el destino ya trae el prefijo (anti-bucle).
 *  - Cabecera `X-MU-WpLingua-Lang: kept` para verificar vía curl/DevTools.
 *
 * @package GeneratePress_Child
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'mu_wplingua_language_prefixes' ) ) {
    /**
     * Prefijos de idioma del proyecto, derivados de la fuente única de verdad
     * (muyu_get_countries_data() + muyu_country_language_prefix()).
     *
     * @return string[] Prefijos, ej: [ '/pt', '/en' ].
     */
    function mu_wplingua_language_prefixes() {
        static $prefixes = null;

        if ( null !== $prefixes ) {
            return $prefixes;
        }

        $prefixes = [];

        if ( function_exists( 'muyu_get_countries_data' ) && function_exists( 'muyu_country_language_prefix' ) ) {
            foreach ( array_keys( muyu_get_countries_data() ) as $code ) {
                $prefix = muyu_country_language_prefix( $code );
                if ( is_string( $prefix ) && '' !== $prefix && ! in_array( $prefix, $prefixes, true ) ) {
                    $prefixes[] = $prefix;
                }
            }
        }

        return $prefixes;
    }
}

if ( ! function_exists( 'mu_wplingua_normalize_query' ) ) {
    /**
     * Normaliza la query del destino, delegando en geo.php cuando está
     * disponible, para que el core deje de verla como no canónica.
     *
     * @param string $query Query string sin '?'.
     * @return string Query normalizada (con '?' inicial) o ''.
     */
    function mu_wplingua_normalize_query( $query ) {
        if ( '' === (string) $query ) {
            return '';
        }

        if ( function_exists( 'muyu_normalize_query_string' ) ) {
            return muyu_normalize_query_string( $query );
        }

        // Fallback defensivo por si geo.php no estuviera cargado.
        if ( ! preg_match( '/[*+@]/', $query ) ) {
            return '?' . $query;
        }

        $args = [];
        parse_str( $query, $args );

        return empty( $args ) ? '' : '?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );
    }
}

if ( ! function_exists( 'mu_wplingua_keep_language_prefix' ) ) {
    /**
     * Filtro `wp_redirect`: conserva el prefijo de idioma en el 301 canónico.
     *
     * Prioridad 15 (explícita): el filtro no depende de otros, pero la fijamos
     * para que un cambio accidental de orden no altere el resultado. El core ya
     * construyó su Location antes (prioridad por defecto) y nadie lo consume
     * después del filtro.
     *
     * @param string $location URL de destino de la redirección.
     * @param int    $status   Código de estado HTTP.
     * @return string URL de destino, con el prefijo de idioma restaurado.
     */
    function mu_wplingua_keep_language_prefix( $location, $status ) {
        if ( is_admin() || ! is_string( $location ) || '' === $location ) {
            return $location;
        }

        if ( ! in_array( (int) $status, [ 301, 302, 307 ], true ) ) {
            return $location;
        }

        // 1) ¿La petición original venía a un idioma con prefijo?
        $prefixes = mu_wplingua_language_prefixes();
        if ( empty( $prefixes ) ) {
            return $location;
        }

        $request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        $request_path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );

        $pattern = '#^(' . implode( '|', array_map( 'preg_quote', $prefixes ) ) . ')(/|$)#i';
        if ( ! preg_match( $pattern, $request_path, $matches ) ) {
            return $location;
        }
        $prefix = $matches[1];

        // 2) Analizar el destino. Las URLs relativas no se tocan.
        $parts = wp_parse_url( $location );
        if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['path'] ) ) {
            return $location;
        }

        // 3) Solo el MISMO host. El 301 del core siempre apunta al host
        //    solicitado; no se tocan destinos de otros hosts (pasarelas,
        //    terceros, ni otros subdominios de país, donde el prefijo correcto
        //    sería otro distinto al de esta petición).
        $host_destino = strtolower( (string) $parts['host'] );
        $host_actual  = strtolower( preg_replace( '/:\d+$/', '', (string) ( $_SERVER['HTTP_HOST'] ?? '' ) ) );

        if ( '' === $host_actual || $host_destino !== $host_actual ) {
            return $location;
        }

        $path_destino    = (string) $parts['path'];
        $path_destino_lc = strtolower( $path_destino );

        // 4) Nunca en rutas técnicas ni en archivos (rompería login/admin/ajax).
        if ( preg_match( '#^/(wp-admin|wp-json|wp-login|wp-cron|admin-ajax|xmlrpc)#', $path_destino_lc )
            || preg_match( '#\.[a-z0-9]{2,5}$#i', $path_destino )
        ) {
            return $location;
        }

        // 5) Anti-bucle: si el destino ya conserva el prefijo, no hay nada que hacer.
        $prefijo_lc = strtolower( $prefix );
        if ( 0 === strpos( $path_destino_lc, $prefijo_lc . '/' ) || $path_destino_lc === $prefijo_lc ) {
            return $location;
        }

        // 6) Solo el patrón exacto del core: el destino debe ser la MISMA ruta
        //    que la solicitada, sin el prefijo. Cualquier otro destino es un
        //    redirect legítimo de un plugin (carrito, checkout, login...) y no
        //    se toca.
        $path_solicitado_sin_prefijo = substr( $request_path, strlen( $prefix ) );
        if ( '' === $path_solicitado_sin_prefijo ) {
            $path_solicitado_sin_prefijo = '/';
        }
        if ( rtrim( $path_solicitado_sin_prefijo, '/' ) !== rtrim( $path_destino, '/' ) ) {
            return $location;
        }

        // 7) Reconstruir la URL con el prefijo delante y la query normalizada.
        $nuevo_path = 0 === strpos( $path_destino, '/' ) ? $prefix . $path_destino : $prefix . '/' . $path_destino;

        $nuevo = ( ! empty( $parts['scheme'] ) ? $parts['scheme'] : 'https' ) . '://' . $parts['host'];
        if ( ! empty( $parts['port'] ) ) {
            $nuevo .= ':' . (int) $parts['port'];
        }
        $nuevo .= $nuevo_path;
        $nuevo .= mu_wplingua_normalize_query( $parts['query'] ?? '' );
        if ( ! empty( $parts['fragment'] ) ) {
            $nuevo .= '#' . $parts['fragment'];
        }

        if ( $nuevo === $location ) {
            return $location;
        }

        // Trazabilidad: confirma en curl/DevTools que la corrección se aplicó.
        header( 'X-MU-WpLingua-Lang: kept' );

        return $nuevo;
    }

    add_filter( 'wp_redirect', 'mu_wplingua_keep_language_prefix', 15, 2 );
}
