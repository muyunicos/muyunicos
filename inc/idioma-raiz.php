<?php
/**
 * Muy Únicos — Raíz en el idioma local, sin redirect (wpLingua 3.2.1)
 *
 * PROBLEMA
 * --------
 * wpLingua deriva el idioma de `$_SERVER['REQUEST_URI']` capturado en
 * `plugins_loaded` (wplng_start()) y memoiza el resultado en
 * `$wplng_language_current_id`. Con el default global `es`, la raíz de un
 * subdominio (p. ej. br.muyunicos.com/) se sirve SIEMPRE en español: no hay
 * resolución por host ni por navegador. El `.htaccess` resolvía la raíz con un
 * 301 a /pt/ (o /en/), pero eso generaba URL con prefijo y un duplicado real
 * (la raíz y /pt/ servían el mismo contenido).
 *
 * SOLUCIÓN
 * --------
 * Sobrescribir el global `$wplng_request_uri` en `init` prioridad 0 —antes del
 * `wplng_ob_start` de wpLingua en `init` prioridad 1— SOLO para la raíz `/` y
 * SOLO en hosts br./us. Así wpLingua resuelve el idioma local (pt/en) y abre el
 * buffer que traduce el HTML, pero `parse_request` sigue resolviendo `/` (sin
 * redirect). La URL queda en la raíz, en el idioma correcto.
 *
 * NOVEDAD CLAVE respecto al handover: un rewrite interno en `.htaccess` NO
 * sirve, porque no cambia `REQUEST_URI` (wpLingua seguiría leyendo `/` → es).
 *
 * Salvaguardas:
 *  - No-op si wpLingua no está activo (wplng_get_language_current_id no existe).
 *  - Sólo frontend (no admin/ajax/cron/json/REST/CLI).
 *  - Sólo la ruta raíz real (mu_wplingua_original_uri() === '/'), capturada en
 *    `after_setup_theme`, antes de este override.
 *  - Sólo hosts con prefijo de idioma (br. → pt, us. → en); el resto no toca nada.
 *
 * @package GeneratePress_Child
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'mu_idioma_raiz_prefijo' ) ) {
    /**
     * Prefijo de idioma de la raíz para el país del host actual.
     *
     * Fuente única: geo.php (muyu_get_current_country_from_subdomain() +
     * muyu_country_language_prefix()). Devuelve 'pt' (br), 'en' (us) o '' (resto).
     *
     * @return string 'pt' | 'en' | ''.
     */
    function mu_idioma_raiz_prefijo() {
        if ( ! function_exists( 'muyu_get_current_country_from_subdomain' )
            || ! function_exists( 'muyu_country_language_prefix' )
        ) {
            return '';
        }

        $pais   = muyu_get_current_country_from_subdomain();
        $prefix = (string) muyu_country_language_prefix( $pais );

        return ltrim( $prefix, '/' );
    }
}

if ( ! function_exists( 'mu_idioma_raiz_fijar' ) ) {
    /**
     * Fija el idioma de la raíz en el subdominio con prefijo, sin redirect.
     *
     * Corre en `init` prioridad 0, antes del `wplng_ob_start` de wpLingua (p1).
     * Sólo actúa sobre la raíz `/` de hosts br./us.; en cualquier otro caso es
     * no-op, de modo que el resto del enrutado (301 de rutas internas, canónico,
     * hreflang) queda intacto.
     *
     * @return void
     */
    function mu_idioma_raiz_fijar() {
        // wpLingua apagado: nada que interceptar.
        if ( ! function_exists( 'wplng_get_language_current_id' ) ) {
            return;
        }

        // Sólo frontend real: el override no debe tocar peticiones técnicas.
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
            return;
        }
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            return;
        }
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            return;
        }
        if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
            return;
        }

        // Sólo la raíz real (URI original capturada antes de este override).
        $uri  = function_exists( 'mu_wplingua_original_uri' )
            ? (string) mu_wplingua_original_uri()
            : ( isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '' );
        $path = (string) wp_parse_url( $uri, PHP_URL_PATH );

        if ( '/' !== $path ) {
            return;
        }

        // Sólo hosts con prefijo de idioma (br./us.); el resto no cambia.
        $prefijo = mu_idioma_raiz_prefijo();
        if ( '' === $prefijo ) {
            return;
        }

        // Resuelve el idioma local: wpLingua leerá '/pt/' o '/en' y memoizará
        // ese idioma; se resetea la memo por si un consumidor previo fijó 'es'.
        $GLOBALS['wplng_request_uri']         = '/' . $prefijo . '/';
        $GLOBALS['wplng_language_current_id'] = null;
    }
    add_action( 'init', 'mu_idioma_raiz_fijar', 0 );
}