<?php
/**
 * Muy Únicos — Integración Jetpack Search (URLs por Subdominio)
 *
 * Problema: Jetpack Instant Search construye los enlaces de los resultados
 * usando la opción `homeUrl` de WordPress, que SIEMPRE apunta al dominio
 * principal (muyunicos.com / Argentina). Si un usuario navega en
 * mexico.muyunicos.com, co.muyunicos.com, es.muyunicos.com, etc., al hacer
 * clic en un resultado de búsqueda es sacado del subdominio y enviado al
 * sitio de Argentina (precios ARS, envío AR, etc.).
 *
 * Solución: interceptar las opciones de Jetpack Instant Search vía el filtro
 * oficial `jetpack_search_instant_search_options` y reemplazar `homeUrl`
 * con el host actual + prefijo de idioma (BR→/pt, US→/en).
 *
 * @package GeneratePress_Child
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Verifica si Jetpack Search (Instant Search) está activo.
 *
 * @return bool True si Jetpack Search está disponible.
 */
if ( ! function_exists( 'mu_jetpack_search_is_active' ) ) {
    function mu_jetpack_search_is_active() {
        // Clases históricas + clase del paquete modular actual (Jetpack Search
        // se distribuye como paquete independiente desde 2023-2024).
        return class_exists( 'Jetpack_Search' )
            || class_exists( 'Automattic\Jetpack\Search\Classic_Search' )
            || class_exists( 'Automattic\Jetpack\Search\Package' )
            || class_exists( 'Automattic\Jetpack\Search\Search_Instant' );
    }
}

/**
 * Construye el homeUrl correcto según el subdominio actual.
 *
 * Reutiliza las funciones de inc/geo.php para mantener consistencia:
 * - muyu_get_current_country_from_subdomain() → código de país (AR, MX, BR...)
 * - muyu_country_language_prefix() → prefijo de idioma ('/pt', '/en', '')
 *
 * @return string URL base correcta (ej: 'https://mexico.muyunicos.com',
 *                'https://br.muyunicos.com/pt', 'https://muyunicos.com').
 */
if ( ! function_exists( 'mu_jetpack_search_get_home_url' ) ) {
    function mu_jetpack_search_get_home_url() {
        // Host actual sin puerto ni www
        $host = preg_replace( '/:\d+$/', '', trim( $_SERVER['HTTP_HOST'] ?? '' ) );
        $host = str_replace( 'www.', '', $host );

        if ( empty( $host ) ) {
            return home_url();
        }

        // Prefijo de idioma según país (BR→/pt, US→/en)
        $prefix = '';
        if ( function_exists( 'muyu_get_current_country_from_subdomain' ) &&
             function_exists( 'muyu_country_language_prefix' ) ) {
            $country = muyu_get_current_country_from_subdomain();
            $prefix  = muyu_country_language_prefix( $country );
        }

        $scheme = is_ssl() ? 'https' : 'http';

        return $scheme . '://' . $host . $prefix;
    }
}

/**
 * Corrige el homeUrl de Jetpack Instant Search para que los resultados
 * apunten al subdominio actual (y no siempre a muyunicos.com).
 *
 * @param array $options Opciones de Jetpack Instant Search.
 * @return array Opciones modificadas.
 */
if ( ! function_exists( 'mu_jetpack_search_fix_home_url' ) ) {
    function mu_jetpack_search_fix_home_url( $options ) {
        if ( ! is_array( $options ) ) {
            return $options;
        }

        $correct_home_url = mu_jetpack_search_get_home_url();

        // Solo modificar si el homeUrl actual difiere del correcto
        if ( isset( $options['homeUrl'] ) && $options['homeUrl'] !== $correct_home_url ) {
            $options['homeUrl'] = $correct_home_url;
        }

        return $options;
    }
    add_filter( 'jetpack_search_instant_search_options', 'mu_jetpack_search_fix_home_url', 10 );
}

/**
 * Oculta los precios de Jetpack Instant Search fuera de Argentina.
 *
 * Problema: el índice de Jetpack Search guarda `price_html` con los precios
 * base en ARS. WCPBC (WooCommerce Price Based on Country) NO puede convertirlos
 * en el overlay porque los resultados de Jetpack no usan la clase `.wcpbc-price`
 * ni `data-product-id` — la opción "Cargar los precios del producto en segundo
 * plano" solo aplica a los elementos WCPBC del DOM de la página, no al overlay.
 *
 * Solución: desactivar `enableProductPrice` en el overlay para cualquier país
 * que no sea AR. El índice de búsqueda es global y siempre contiene ARS; el
 * usuario verá el precio correcto (convertido por WCPBC) recién al entrar al
 * producto.
 *
 * @param array $options Opciones de Jetpack Instant Search.
 * @return array Opciones modificadas.
 */
if ( ! function_exists( 'mu_jetpack_search_maybe_hide_prices' ) ) {
    function mu_jetpack_search_maybe_hide_prices( $options ) {
        if ( ! is_array( $options ) ) {
            return $options;
        }

        $country = function_exists( 'muyu_get_current_country_from_subdomain' )
            ? muyu_get_current_country_from_subdomain()
            : 'AR';

        // Solo Argentina (dominio principal) muestra precios en el overlay.
        if ( 'AR' !== $country ) {
            if ( isset( $options['overlayOptions'] ) && is_array( $options['overlayOptions'] ) ) {
                $options['overlayOptions']['enableProductPrice'] = false;
            }
        }

        return $options;
    }
    add_filter( 'jetpack_search_instant_search_options', 'mu_jetpack_search_maybe_hide_prices', 15 );
}

/**
 * Encola el script de integración que reescribe los links de los resultados
 * de Jetpack Instant Search para que apunten al subdominio actual.
 *
 * Los resultados individuales del API de Jetpack Search usan get_permalink(),
 * que siempre devuelve el dominio principal (muyunicos.com). Este JS corrige
 * los href renderizados en el overlay para mantener al usuario en su subdominio.
 */
if ( ! function_exists( 'mu_jetpack_search_enqueue_assets' ) ) {
    function mu_jetpack_search_enqueue_assets() {
        if ( is_admin() || ! mu_jetpack_search_is_active() ) {
            return;
        }

        $ver = wp_get_theme()->get( 'Version' );
        $uri = get_stylesheet_directory_uri();

        wp_enqueue_script( 'mu-jetpack-search-js', $uri . '/js/jetpack-search.js', [], $ver, true );

        if ( wp_script_is( 'mu-jetpack-search-js', 'enqueued' ) ) {
            $host = preg_replace( '/:\d+$/', '', trim( $_SERVER['HTTP_HOST'] ?? '' ) );
            $host = str_replace( 'www.', '', $host );

            $current_country = function_exists( 'muyu_get_current_country_from_subdomain' )
                ? muyu_get_current_country_from_subdomain()
                : 'AR';

            $prefix = '';
            if ( function_exists( 'muyu_country_language_prefix' ) ) {
                $prefix = muyu_country_language_prefix( $current_country );
            }

            wp_localize_script( 'mu-jetpack-search-js', 'muJetpackSearchData', [
                'homeUrl'        => mu_jetpack_search_get_home_url(),
                'languagePrefix' => $prefix,
                'currentHost'    => $host,
                'mainDomain'     => muyu_get_main_domain(),
                'showPrice'      => ( 'AR' === $current_country ),
            ] );
        }
    }
    add_action( 'wp_enqueue_scripts', 'mu_jetpack_search_enqueue_assets', 20 );
}
