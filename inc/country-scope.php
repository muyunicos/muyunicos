<?php
/**
 * Muy Únicos — Alcance por país de rutas y elementos
 *
 * QUÉ RESUELVE
 * ------------
 * El sitio es UNA instalación de WordPress servida en 10 hosts (muyunicos.com y
 * los 9 subdominios de país). Las páginas son globales: WordPress no tiene un
 * "publicar sólo en Argentina". Una página que describe algo que sólo existe en
 * un país —/envios/ son las zonas de Mar del Plata, los costos en ARS y el retiro
 * en Catamarca 2255— se publica, se indexa y se enlaza en los nueve subdominios
 * donde no aplica. Medido el 2026-10-16: /envios/ devolvía 200 con ese contenido
 * en mexico, co, es, cl, pe, br, ec, us y cr, con el enlace en el pie de todos
 * ellos y presente en cada page-sitemap.xml.
 *
 * QUÉ HACE
 * --------
 * Un registro único de rutas con los países donde cada ruta existe. Declarada la
 * ruta, el módulo resuelve tres cosas por sí solo:
 *   1. Guard HTTP: en un país que no publica la ruta responde 302 a la tienda del
 *      subdominio actual (mismo criterio que el resto del sitio: destino útil para
 *      el comprador y la URL sale del índice de ese país).
 *   2. Enlace: muyu_is_country() evita emitir el <li> del pie en el resto de los
 *      países (inc/ui.php).
 *   3. hreflang: el conjunto de alternates del tema se reduce al host actual
 *      (inc/seo-hreflang.php). Declarar 9 subdominios que no sirven la página es
 *      un hreflang asimétrico (constitución, principio V).
 *
 * API — para reutilizar en cualquier ruta o elemento por país (ver guía §6):
 *   muyu_get_current_country()                     → 'AR' | 'MX' | 'CO' | ...
 *   muyu_get_current_host()                        → 'mexico.muyunicos.com' | ...
 *   muyu_is_country( 'AR' )                        → bool (elementos sueltos)
 *   muyu_is_country( [ 'AR', 'MX' ] )              → bool (varios países)
 *   muyu_get_country_scope_routes()                → registro completo
 *   muyu_get_country_scope_route( 'envios' )       → entrada del registro | null
 *   muyu_get_country_scope_route_for_request()     → slug de la ruta pedida | null
 *   muyu_get_country_scope_hreflang_tags()         → alternates de la ruta | []
 *
 * TRES MANERAS DE RESTRINGIR ALGO A UN PAÍS (elegir la más barata)
 * ---------------------------------------------------------------
 * 1. Ruta completa (página o plantilla): agregarla a muyu_get_country_scope_routes().
 *    Guard HTTP y hreflang quedan cubiertos sin tocar nada más, y la ruta queda
 *    declarada en un solo lugar auditable. El enlace que apunte a ella se
 *    condiciona con el mismo registro, para que ruta y enlace no se desincronicen.
 * 2. Elemento que imprime el tema (un <li>, un botón, un bloque):
 *      if ( muyu_is_country( [ 'AR' ] ) ) { ... }
 *    No se emite HTML de más y no depende de CSS.
 * 3. Elemento ya impreso por un plugin o por wp-admin, sin PHP disponible: usar
 *    la body class `mu-country-<xx>` que ya inyecta inc/geo.php (línea 864):
 *      body:not(.mu-country-ar) .mi-elemento { display: none; }
 *    Precedente vigente: css/components/global-ui.css (precios de Jetpack Search).
 *
 * LÍMITES (honestos)
 * ------------------
 * - El país es el HOST (inc/geo.php: muyu_get_current_country_from_subdomain()).
 *   Los idiomas /pt/ y /en/ del dominio raíz siguen siendo Argentina: esta API NO
 *   excluye por idioma (eso es wpLingua).
 * - Un elemento del menú de WordPress (Apariencia → Menús) no se puede restringir
 *   por código: sale de la base de datos. Se resuelve a mano en wp-admin.
 * - La ruta se identifica por su slug. Si la página se renombra en wp-admin hay
 *   que renombrar la clave del registro en el mismo commit.
 *
 * @package GeneratePress_Child
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================
// CONTEXTO DE PAÍS Y HOST (wrapper de inc/geo.php)
// ============================================

if ( ! function_exists( 'muyu_get_current_country' ) ) {
    /**
     * País del request actual, según el host (fuente única: inc/geo.php).
     *
     * @return string Código ISO-3166 alpha-2 en mayúsculas ('AR' en el dominio
     *                principal; 'MX', 'CO', … en subdominios).
     */
    function muyu_get_current_country(): string {
        if ( function_exists( 'muyu_get_current_country_from_subdomain' ) ) {
            return strtoupper( (string) muyu_get_current_country_from_subdomain() );
        }

        return 'AR'; // mismo fallback que inc/geo.php
    }
}

if ( ! function_exists( 'muyu_get_current_host' ) ) {
    /**
     * Host del request actual, sin puerto ni 'www.'.
     *
     * El redirect del guard tiene que quedarse en el host que el comprador está
     * navegando. En un request vivo home_url() y wc_get_page_permalink() ya
     * devuelven ese host (verificado el 2026-10-16 en el breadcrumb de
     * navigation-chips sobre mexico.muyunicos.com), pero el host se fija
     * explícitamente por dos motivos: el incidente de Jetpack Search demostró que
     * la opción homeUrl de un plugin puede quedar clavada en el dominio principal,
     * y wp_safe_redirect() cae a wp-admin si el host destino no está permitido.
     *
     * @return string Host actual, o '' si no se pudo determinar.
     */
    function muyu_get_current_host(): string {
        $host = preg_replace( '/:\d+$/', '', trim( (string) ( $_SERVER['HTTP_HOST'] ?? '' ) ) );

        return strtolower( str_replace( 'www.', '', (string) $host ) );
    }
}

if ( ! function_exists( 'muyu_is_country' ) ) {
    /**
     * ¿El país del request actual está entre los permitidos?
     *
     * Es el helper para "elementos que sólo se renderizan en países determinados":
     * envolver la salida con esto evita emitir HTML que después habría que ocultar
     * con CSS.
     *
     *   if ( muyu_is_country( [ 'AR' ] ) )             → sólo Argentina
     *   if ( muyu_is_country( [ 'AR', 'MX', 'CL' ] ) ) → tres países
     *   if ( muyu_is_country( '' ) )                   → todos (sin restricción)
     *
     * @param string|string[] $countries País o lista de países permitidos. Vacío
     *                                   ('' o []) equivale a "todos".
     * @return bool True si el país actual está permitido.
     */
    function muyu_is_country( $countries ): bool {
        $allowed = [];

        foreach ( (array) $countries as $code ) {
            $code = strtoupper( trim( (string) $code ) );
            if ( '' !== $code ) {
                $allowed[] = $code;
            }
        }

        if ( empty( $allowed ) ) {
            return true; // sin restricción declarada
        }

        return in_array( muyu_get_current_country(), $allowed, true );
    }
}

// ============================================
// REGISTRO DE RUTAS CON ALCANCE POR PAÍS (fuente única)
// ============================================

if ( ! function_exists( 'muyu_get_country_scope_routes' ) ) {
    /**
     * Registro de rutas que existen sólo en países determinados.
     *
     * Estructura de cada entrada:
     *   'slug-de-la-pagina' => [
     *       'countries' => [ 'AR' ],   // países donde la ruta se publica
     *       'redirect'  => 'shop',     // destino en el resto: 'shop' | 'home' | URL absoluta
     *       'label'     => 'Texto',    // texto del enlace, cuando hay uno
     *   ]
     *
     * Agregar una ruta acá alcanza para que el guard HTTP y el hreflang la cubran.
     * El enlace que apunte a la ruta se condiciona con muyu_is_country() y los
     * mismos 'countries', para que ruta y enlace no puedan desincronizarse.
     *
     * @return array Registro normalizado: países en mayúsculas, sin duplicados.
     */
    function muyu_get_country_scope_routes(): array {
        static $routes = null;
        if ( null !== $routes ) {
            return $routes;
        }

        $routes = [
            // /envios/ describe las zonas de cobertura, los costos y el retiro en
            // Mar del Plata (Catamarca 2255). Los subdominios venden sólo
            // productos digitales (guía §3): ahí no hay envío físico que informar.
            'envios' => [
                'countries' => [ 'AR' ],
                'redirect'  => 'shop',
                'label'     => 'Información de Envíos',
            ],
        ];

        /**
         * Filtro de extensión del registro — permite sumar rutas desde otro módulo
         * sin editar este archivo.
         *
         * @param array $routes Registro de rutas.
         */
        $routes = apply_filters( 'muyu_country_scope_routes', $routes );

        if ( ! is_array( $routes ) ) {
            $routes = [];
        }

        // Normalización: sin esto, un registro escrito con 'ar' en minúsculas o
        // sin la clave 'countries' se comportaría distinto según quién lo escriba.
        foreach ( $routes as $slug => $route ) {
            if ( ! is_array( $route ) || '' === trim( (string) $slug ) ) {
                unset( $routes[ $slug ] );
                continue;
            }

            $countries = [];
            foreach ( (array) ( $route['countries'] ?? [] ) as $code ) {
                $code = strtoupper( trim( (string) $code ) );
                if ( '' !== $code ) {
                    $countries[] = $code;
                }
            }

            $routes[ $slug ] = [
                'countries' => array_values( array_unique( $countries ) ),
                'redirect'  => (string) ( $route['redirect'] ?? 'shop' ),
                'label'     => (string) ( $route['label'] ?? '' ),
            ];
        }

        return $routes;
    }
}

if ( ! function_exists( 'muyu_get_country_scope_route' ) ) {
    /**
     * Entrada del registro para un slug.
     *
     * @param string $slug Slug de la ruta (ej: 'envios').
     * @return array|null Entrada normalizada, o null si la ruta no está registrada.
     */
    function muyu_get_country_scope_route( string $slug ): ?array {
        $slug   = trim( $slug, "/ \t\n\r\0\x0B" );
        $routes = muyu_get_country_scope_routes();

        return $routes[ $slug ] ?? null;
    }
}

if ( ! function_exists( 'muyu_get_scoped_request_path' ) ) {
    /**
     * Ruta pedida, normalizada: sin query, sin prefijo de idioma, con barra final.
     *
     * ⚠️ Se lee la URI ORIGINAL (mu_wplingua_original_uri()) y no
     * $_SERVER['REQUEST_URI'] a secas: wpLingua reescribe esa variable en 'init'
     * para quitarle el prefijo de idioma, así que en 'template_redirect' ya no
     * tiene el /pt ni el /en (guía §7, "LECCIÓN CLAVE"). El prefijo se elimina
     * igual acá, para que el registro no dependa del idioma con el que se pidió.
     *
     * @return string Ruta normalizada, ej: '/envios/'.
     */
    function muyu_get_scoped_request_path(): string {
        static $path = null;
        if ( null !== $path ) {
            return $path;
        }

        if ( function_exists( 'mu_wplingua_original_uri' ) ) {
            $uri = (string) mu_wplingua_original_uri();
        } else {
            $uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        }

        $segments = array_values( array_filter( explode( '/', (string) wp_parse_url( $uri, PHP_URL_PATH ) ), 'strlen' ) );

        // Prefijos de idioma desde su fuente única (inc/compat-wplingua.php), para
        // no repetir acá la lista '/pt', '/en'.
        $prefixes  = function_exists( 'mu_wplingua_language_prefixes' ) ? (array) mu_wplingua_language_prefixes() : [];
        $languages = [];
        foreach ( $prefixes as $prefix ) {
            $languages[] = ltrim( strtolower( (string) $prefix ), '/' );
        }

        if ( ! empty( $segments ) && in_array( strtolower( $segments[0] ), $languages, true ) ) {
            array_shift( $segments );
        }

        $path = empty( $segments ) ? '/' : '/' . implode( '/', $segments ) . '/';

        return $path;
    }
}

if ( ! function_exists( 'muyu_get_country_scope_route_for_request' ) ) {
    /**
     * Slug de la ruta del registro que corresponde al request actual.
     *
     * Se resuelve primero por el main query (`is_page()`): es inmune a la
     * reescritura de la URI de wpLingua y no depende del prefijo de idioma. La
     * comparación por ruta normalizada queda como defensa en profundidad (cubre
     * un request donde la página se resuelva por otra vía).
     *
     * ⚠️ Llamar sólo desde 'template_redirect' o después: antes de esa instancia el
     * main query no está resuelto y `is_page()` avisa por _doing_it_wrong().
     *
     * @return string|null Slug registrado, o null si la ruta actual no está en el registro.
     */
    function muyu_get_country_scope_route_for_request(): ?string {
        static $slug = null;
        if ( null !== $slug ) {
            return '' === $slug ? null : $slug;
        }

        $slug       = '';
        $candidates = array_keys( muyu_get_country_scope_routes() );

        if ( empty( $candidates ) ) {
            return null;
        }

        if ( function_exists( 'is_page' ) ) {
            foreach ( $candidates as $candidate ) {
                if ( is_page( $candidate ) ) {
                    $slug = $candidate;
                    break;
                }
            }
        }

        if ( '' === $slug ) {
            $path = muyu_get_scoped_request_path();
            foreach ( $candidates as $candidate ) {
                if ( '/' . trim( (string) $candidate, '/' ) . '/' === $path ) {
                    $slug = $candidate;
                    break;
                }
            }
        }

        return '' === $slug ? null : $slug;
    }
}

// ============================================
// GUARD HTTP
// ============================================

if ( ! function_exists( 'muyu_get_country_scope_redirect_url' ) ) {
    /**
     * Destino del 302 para un país que no publica la ruta.
     *
     * Reutiliza los mismos helpers que execute_redirect() de
     * inc/digital-restriction.php (línea 1016): muyu_country_language_prefix() para
     * el idioma del país y muyu_clean_uri(), que no duplica un prefijo ya presente
     * —importante porque wpLingua puede haber reescrito el permalink de la tienda
     * con el prefijo.
     *
     * El host se fija al del request actual a propósito: el comprador no debe salir
     * del subdominio que está navegando (con precios en otra moneda y sin envíos),
     * y wp_safe_redirect() derivaría a wp-admin si el destino apuntara a un host no
     * permitido.
     *
     * @param array $route Entrada del registro.
     * @return string URL absoluta en el host actual.
     */
    function muyu_get_country_scope_redirect_url( array $route ): string {
        $target = (string) ( $route['redirect'] ?? 'shop' );

        if ( 'shop' === $target ) {
            $shop   = function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'shop' ) : '';
            $target = '' !== $shop ? $shop : home_url( '/tienda/' );
        } elseif ( 'home' === $target ) {
            $target = home_url( '/' );
        }

        if ( '' === $target ) {
            $target = home_url( '/' );
        }

        $parts = wp_parse_url( $target );
        $host  = muyu_get_current_host();
        $path  = (string) ( $parts['path'] ?? '/' );
        $query = isset( $parts['query'] ) ? '?' . $parts['query'] : '';

        if ( '' === $host ) {
            $host = (string) ( $parts['host'] ?? muyu_get_main_domain() );
        }

        $prefix = function_exists( 'muyu_country_language_prefix' )
            ? (string) muyu_country_language_prefix( muyu_get_current_country() )
            : '';

        if ( '' !== $prefix && function_exists( 'muyu_clean_uri' ) ) {
            $path = muyu_clean_uri( $prefix, $path );
        }

        return 'https://' . $host . $path . $query;
    }
}

if ( ! function_exists( 'mu_country_scope_guard' ) ) {
    /**
     * Una ruta con alcance por país sólo responde en los países que la publican.
     *
     * Prioridad 15: después del 301 canónico del core (p10) y de
     * mu_litespeed_nocache_404() (p5), antes de handle_redirects() de
     * inc/digital-restriction.php (p20) y del render del tema.
     *
     * Se aplica también a bots, a propósito: es una comprobación O(1) sin SQL y el
     * 302 es la señal que retira la URL del índice de ese país. La alternativa (404)
     * queda descartada porque mu_litespeed_bot_404_fast_exit() corre en p1 y le daría
     * a los bots una respuesta distinta de la del comprador.
     *
     * @return void
     */
    function mu_country_scope_guard(): void {
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() ) {
            return;
        }
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            return;
        }
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            return;
        }

        $slug = muyu_get_country_scope_route_for_request();
        if ( null === $slug ) {
            return; // no es una ruta con alcance por país
        }

        $route = muyu_get_country_scope_route( $slug );
        if ( null === $route ) {
            return;
        }

        if ( muyu_is_country( $route['countries'] ) ) {
            return; // país que publica la ruta: se sirve normal (200)
        }

        // Trazabilidad, misma convención que X-MU-WpLingua-Lang y X-MU-Bot-404:
        // distingue por curl "el guard corrió" de "el archivo no llegó al servidor",
        // que es el fallo silencioso normal de este deploy (guía §1).
        header( 'X-MU-Country-Scope: redirect' );

        wp_safe_redirect( muyu_get_country_scope_redirect_url( $route ), 302 );
        exit;
    }
    add_action( 'template_redirect', 'mu_country_scope_guard', 15 );
}

// ============================================
// SEO — HREFLANG DE LA RUTA
// ============================================

if ( ! function_exists( 'muyu_get_country_scope_hreflang_tags' ) ) {
    /**
     * Alternates hreflang de una ruta con alcance por país.
     *
     * El conjunto se reduce a UNA entrada autorreferencial: la URL actual en el
     * idioma actual. Motivo: la ruta no existe en los otros hosts —ahí responde el
     * guard— y declarar los 9 subdominios era un hreflang que apuntaba a redirects
     * (constitución, principio V). No se emite x-default: wpLingua ya declara el
     * suyo para las variantes de idioma del mismo host, y dos x-default en la misma
     * página se contradicen entre sí.
     *
     * @return array Lista de ['hreflang' => string, 'url' => string]. Vacía si la
     *               request actual no es una ruta con alcance por país.
     */
    function muyu_get_country_scope_hreflang_tags(): array {
        $slug = muyu_get_country_scope_route_for_request();
        if ( null === $slug ) {
            return [];
        }

        $route = muyu_get_country_scope_route( $slug );
        if ( null === $route || ! muyu_is_country( $route['countries'] ) ) {
            return [];
        }

        $language = function_exists( 'muyu_get_current_language' ) ? (string) muyu_get_current_language() : 'es';
        $prefix   = in_array( $language, [ 'pt', 'en' ], true ) ? '/' . $language : '';

        return [
            [
                'hreflang' => $language,
                'url'      => home_url( $prefix . '/' . trim( $slug, '/' ) . '/' ),
            ],
        ];
    }
}

// ============================================
// SEO — SITEMAP DE RANK MATH
// ============================================

if ( ! function_exists( 'mu_country_scope_exclude_from_sitemap' ) ) {
    /**
     * Saca del sitemap XML las rutas con alcance por país en los hosts que no las
     * sirven (el listado de /envios/ en cada page-sitemap.xml de subdominio).
     *
     * El callback es defensivo a propósito (guía §3: Rank Math es un plugin
     * acoplado, no código propio): si el filtro cambiara de firma en una versión del
     * plugin, la peor consecuencia es que la URL siga listada — nunca un error fatal
     * ni un sitemap roto. Por eso la ruta se reconoce por la URL y no por el
     * argumento $post, que depende del orden de los argumentos.
     *
     * ⚠️ Filtro NO verificado contra el plugin al momento de escribirlo: la
     * documentación pública no expone la firma exacta. Se verifica con curl sobre el
     * page-sitemap.xml de un subdominio (quickstart §B.4); si no dispara, la entrada
     * sigue listada y queda registrada como hueco en §8 del guide.
     *
     * @param mixed  $url  URL de la entrada del sitemap.
     * @param string $type Tipo de entrada del sitemap (no se usa).
     * @param mixed  $post Post de la entrada del sitemap (no se usa).
     * @return mixed URL original, o '' para excluir la entrada.
     */
    function mu_country_scope_exclude_from_sitemap( $url, $type = '', $post = null ) {
        if ( ! is_string( $url ) || '' === $url ) {
            return $url;
        }

        $segments = array_values( array_filter( explode( '/', (string) wp_parse_url( $url, PHP_URL_PATH ) ), 'strlen' ) );
        if ( empty( $segments ) ) {
            return $url;
        }

        // Descartar el prefijo de idioma si lo hubiera: /pt/envios/ → envios.
        $prefixes  = function_exists( 'mu_wplingua_language_prefixes' ) ? (array) mu_wplingua_language_prefixes() : [];
        $languages = [];
        foreach ( $prefixes as $prefix ) {
            $languages[] = ltrim( strtolower( (string) $prefix ), '/' );
        }

        if ( in_array( strtolower( (string) $segments[0] ), $languages, true ) ) {
            array_shift( $segments );
        }
        if ( empty( $segments ) ) {
            return $url;
        }

        $route = muyu_get_country_scope_route( strtolower( (string) end( $segments ) ) );
        if ( null === $route ) {
            return $url;
        }

        return muyu_is_country( $route['countries'] ) ? $url : '';
    }
    add_filter( 'rank_math/sitemap/entry', 'mu_country_scope_exclude_from_sitemap', 10, 3 );
}
