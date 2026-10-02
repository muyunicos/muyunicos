<?php
/**
 * Muy Únicos — Bypass de caché de CDN
 *
 * Evita que la CDN sirva desde caché el contenido dinámico del comprador
 * (carrito, checkout y cuenta), que es personalizado y nunca debe quedar
 * almacenado en un nodo intermedio.
 *
 * Emite cabeceras HTTP ESTÁNDAR (Cache-Control, Pragma, Expires), por lo que
 * funciona con cualquier CDN. El nombre del módulo y de la función son
 * agnósticos a propósito: cambiarlos cuando cambia el proveedor de CDN sería
 * ruido en el historial, y atar la lógica a un proveedor hizo que este módulo
 * pareciera muerto cuando Cloudflare dejó de usarse.
 *
 * No toca la configuración del plugin de caché: se aplica en cada request.
 *
 * @package GeneratePress_Child
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================
// BYPASS DE CACHÉ CDN PARA CONTENIDO DINÁMICO
// ============================================

if ( ! function_exists( 'mu_cdn_cache_bypass' ) ) {
    /**
     * Marca como no cacheable el contenido que depende del usuario.
     *
     * @return void
     */
    function mu_cdn_cache_bypass() {
        if ( is_cart() || is_checkout() || is_account_page() || is_wc_endpoint_url() ) {
            header( 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0' );
            header( 'Pragma: no-cache' );
            header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT' );
        }
    }
    add_action( 'template_redirect', 'mu_cdn_cache_bypass' );
}