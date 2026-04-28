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
