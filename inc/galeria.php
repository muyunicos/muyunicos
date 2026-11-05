<?php
/**
 * Galeria compartida del tema (spec 019).
 *
 * Registra y encola `js/galeria.js` + `css/components/galeria.css`, que exponen
 * `window.MUGaleria` (visor + tira + tarjeta). Canonico: specs/019-galeria-compartida.
 *
 * Reglas (decisiones D9/D10):
 *   - Este archivo NO imprime HTML: solo registra/encola. El markup lo arma el JS o
 *     el consumidor, porque la galeria de WooCommerce la dibuja WooCommerce.
 *   - Los handles se REGISTRAN siempre (prioridad 1000, despues de mu_enqueue_assets)
 *     y se ENCOLAN solo en la ficha de producto, o cuando un consumidor los pide por
 *     `mu_galeria_encolar()` (plugin, shortcode, plantilla, cuenta, banners).
 *   - El tema no aporta datos: la galeria no conoce endpoints ni nonces (D10).
 *   - Fuente unica de cache-bust: `Version:` de style.css (D12).
 *
 * @package MuyUnicos
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'mu_galeria_registrar' ) ) {
    /**
     * Registra los handles SIN encolarlos.
     *
     * Prioridad 998: `mu_enqueue_assets()` corre en la 999 y ya registro `mu-base`
     * (dependencia del CSS), y `mu-product-js` (999) declara `mu-galeria` como
     * dependencia: el handle TIENE que existir antes o WordPress omite el script.
     */
    function mu_galeria_registrar() {
        if ( wp_style_is( 'mu-galeria', 'registered' ) ) {
            return;
        }
        $ver = wp_get_theme()->get( 'Version' );
        $uri = get_stylesheet_directory_uri();

        wp_register_style( 'mu-galeria', $uri . '/css/components/galeria.css', array( 'mu-base' ), $ver );
        wp_register_script( 'mu-galeria', $uri . '/js/galeria.js', array(), $ver, true );
    }
    add_action( 'wp_enqueue_scripts', 'mu_galeria_registrar', 998 );
}

if ( ! function_exists( 'mu_galeria_imprimir_tarde' ) ) {
    /**
     * Imprime los handles pedidos DESPUES de `wp_head` desde el footer.
     *
     * Un consumidor que pinta su panel durante el render (ej. `woocommerce_before_add_to_cart_form`)
     * puede encolar tarde; sin esto el CSS no llegaria a la pagina y la galeria saldria sin estilo.
     */
    function mu_galeria_imprimir_tarde() {
        wp_print_styles( 'mu-galeria' );
        wp_print_scripts( 'mu-galeria' );
    }
}

if ( ! function_exists( 'mu_galeria_encolar' ) ) {
    /**
     * Encola la galeria compartida (idempotente). API publica para consumidores.
     *
     * Llamarla en `wp_enqueue_scripts` (temprano) es lo recomendado. Si se llama
     * despues de `wp_head`, se imprime en el footer en vez de perderse.
     *
     * @return bool Siempre true (permite `if ( mu_galeria_encolar() ) { ... }`).
     */
    function mu_galeria_encolar() {
        static $pedida = false;
        if ( $pedida ) {
            return true;
        }
        $pedida = true;

        if ( ! wp_style_is( 'mu-galeria', 'registered' ) ) {
            mu_galeria_registrar();
        }
        wp_enqueue_style( 'mu-galeria' );
        wp_enqueue_script( 'mu-galeria' );

        if ( did_action( 'wp_head' ) > 0 && did_action( 'wp_footer' ) === 0 ) {
            add_action( 'wp_footer', 'mu_galeria_imprimir_tarde', 1 );
        }
        return true;
    }
}

if ( ! function_exists( 'mu_galeria_auto_ficha' ) ) {
    /**
     * La ficha de producto lleva la galeria por defecto: reemplaza el trigger de
     * Photoswipe de WooCommerce (D8). El resto del sitio NO paga el peso (SC-004).
     *
     * `mu_galeria_en_ficha` permite apagarlo sin tocar codigo (ej. para depurar).
     */
    function mu_galeria_auto_ficha() {
        if ( ! function_exists( 'is_product' ) || ! is_product() ) {
            return;
        }
        if ( ! apply_filters( 'mu_galeria_en_ficha', true ) ) {
            return;
        }
        mu_galeria_encolar();
    }
    add_action( 'wp_enqueue_scripts', 'mu_galeria_auto_ficha', 1001 );
}