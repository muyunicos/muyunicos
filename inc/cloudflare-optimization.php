<?php
/**
 * Muy Únicos — Cloudflare CDN Optimization
 *
 * Optimizaciones para trabajar con Cloudflare CDN:
 * - Bypass de caché para contenido dinámico (carrito/checkout/cuenta)
 *
 * LIMPIEZA v2.0 (25-Aug-2026): se eliminaron 4 bloques problemáticos:
 * - mu_cloudflare_cache_headers(): el hook send_headers corre en el request
 *   de la página HTML, no en el de los assets estáticos (servidos por
 *   LiteSpeed/nginx sin pasar por PHP). El check de extensión casi nunca
 *   matcheaba y, cuando lo hacía, cacheaba contenido dinámico por 1 año
 *   (Cache-Control: immutable). Cloudflare no cachea HTML en esta
 *   arquitectura (sin "Cache Everything"), así que era inútil Y peligroso.
 * - mu_cloudflare_asset_version(): file_exists() + filemtime() en cada asset
 *   de cada página (I/O innecesario bajo saturación), bug en subdominios
 *   (check de 'muyunicos.com' fallaba con hosts relativos) y redundante:
 *   functions.php ya versiona los assets con wp_get_theme()->get('Version').
 * - mu_cloudflare_lazy_loading(): loading="lazy" es nativo de WordPress
 *   desde 5.5 (2020) para the_content y post_thumbnail_html.
 * - mu_cloudflare_page_rule_hints(): comentarios HTML de debug; el check
 *   de /wp-admin/ nunca matcheaba porque wp_head no corre en admin.
 *
 * @package GeneratePress_Child
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================
// BYPASS CLOUDFLARE CACHE FOR DYNAMIC CONTENT
// ============================================

if ( ! function_exists( 'mu_cloudflare_bypass_cache' ) ) {
    /**
     * Agrega headers para bypass de caché de Cloudflare en contenido dinámico
     */
    function mu_cloudflare_bypass_cache() {
        // Bypass para páginas dinámicas
        if ( is_cart() || is_checkout() || is_account_page() || is_wc_endpoint_url() ) {
            header( 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0' );
            header( 'Pragma: no-cache' );
            header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT' );
        }
    }
    add_action( 'template_redirect', 'mu_cloudflare_bypass_cache' );
}