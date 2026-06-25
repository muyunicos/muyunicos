<?php
/**
 * Muy Únicos — Compatibilidad LiteSpeed Cache
 *
 * Problema: gla-gtag-events.js (Google Listings & Ads) depende de window.wp.hooks
 * (wp-hooks handle). Cuando LiteSpeed aplica "Load JS Delayed", ejecuta los scripts
 * en orden asíncrono sin respetar el árbol de dependencias de wp_register_script.
 * En visitantes (sin admin bar), wp-hooks no se carga antes que gtag-events.js,
 * resultando en: "Uncaught TypeError: Cannot read properties of undefined (reading 'hooks')"
 *
 * Solución: excluir los handles problemáticos del JS Delay de LiteSpeed vía filtro PHP,
 * forzando su carga en el orden normal del navegador.
 *
 * NO toca la config del plugin LiteSpeed (que se resetea con actualizaciones).
 *
 * @package GeneratePress_Child
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Excluye scripts críticos con dependencias de @wordpress/* del JS Delay de LiteSpeed.
 *
 * LiteSpeed Cache lee la opción 'litespeed.conf.optm-js_exc' pero también expone
 * el filtro 'litespeed_optimize_js_excludes' para exclusiones programáticas.
 * Usamos el filtro para mayor robustez (no depende de la configuración guardada).
 *
 * @param array $excludes Lista actual de patrones de exclusión.
 * @return array Lista ampliada.
 */
if ( ! function_exists( 'mu_litespeed_js_delay_excludes' ) ) {
    function mu_litespeed_js_delay_excludes( $excludes ) {
        /*
         * Excluir por fragmento de URL (LiteSpeed hace strpos contra la src del script).
         * - gtag-events.js        : GLA — necesita window.wp.hooks (wp-hooks)
         * - 101.js                : chunk interno de GLA que acompaña a gtag-events.js
         *
         * No excluir jquery.min.js ni wp-hooks completo ya que LiteSpeed los
         * gestiona bien cuando no tienen scripts dependientes siendo retrasados.
         */
        $mu_excludes = [
            'google-listings-and-ads/js/build/gtag-events.js',
            'google-listings-and-ads/js/build/101.js',
        ];

        return array_merge( (array) $excludes, $mu_excludes );
    }
    add_filter( 'litespeed_optimize_js_excludes', 'mu_litespeed_js_delay_excludes' );
}
/**
 * Fuerza variación de caché por host (subdominio) usando cookies de vary.
 * Evita que LiteSpeed sirva a muyunicos.com una página cacheada
 * desde us.muyunicos.com o cualquier otro subdominio restringido.
 *
 * Usa el filtro litespeed_vary_cookies para registrar una cookie de vary
 * basada en el host. Esto es el método correcto según la documentación de LiteSpeed.
 * Documentación: https://docs.litespeedtech.com/lscache/lscwp/api/
 */
if ( ! function_exists( 'mu_litespeed_vary_by_host' ) ) {
    function mu_litespeed_vary_by_host( $cookies ) {
        $host = str_replace( 'www.', '', preg_replace( '/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '' ));
        
        // Registrar cookie de vary basada en el host
        // LiteSpeed reconocerá cualquier cookie que empiece con _lscache_vary
        $cookies[] = '_lscache_vary_mu_host';
        
        return $cookies;
    }
    add_filter( 'litespeed_vary_cookies', 'mu_litespeed_vary_by_host' );
}

/**
 * Establece el valor de la cookie de vary basada en el host.
 * Esta cookie se usa para diferenciar la caché entre subdominios.
 */
if ( ! function_exists( 'mu_litespeed_set_vary_cookie' ) ) {
    function mu_litespeed_set_vary_cookie() {
        if ( is_admin() ) return;
        
        $host = str_replace( 'www.', '', preg_replace( '/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '' ));
        
        // Solo establecer la cookie si no existe o si el valor cambió
        if ( ! isset( $_COOKIE['_lscache_vary_mu_host'] ) || $_COOKIE['_lscache_vary_mu_host'] !== $host ) {
            setcookie( '_lscache_vary_mu_host', $host, time() + ( 30 * DAY_IN_SECONDS ), '/', $host, is_ssl(), true );
        }
    }
    add_action( 'init', 'mu_litespeed_set_vary_cookie', 1 );
}