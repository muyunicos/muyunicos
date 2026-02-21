<?php
/**
 * Muy Únicos - GeneratePress Child Theme
 * 
 * Arquitectura modular:
 * - Enqueue system centralizado
 * - Módulos organizados en inc/
 * - CSS/JS condicional por página
 * 
 * @package GeneratePress_Child
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================
// PARENT THEME ENQUEUE (AUTO-GENERATED)
// ============================================

if ( ! function_exists( 'chld_thm_cfg_locale_css' ) ) {
    function chld_thm_cfg_locale_css( $uri ) {
        if ( empty( $uri ) && is_rtl() && file_exists( get_template_directory() . '/rtl.css' ) ) {
            $uri = get_template_directory_uri() . '/rtl.css';
        }
        return $uri;
    }
}
add_filter( 'locale_stylesheet_uri', 'chld_thm_cfg_locale_css' );

// ============================================
// SISTEMA DE ENQUEUE MODULAR
// ============================================

function mu_enqueue_assets() {
    $ver = wp_get_theme()->get( 'Version' );
    $uri = get_stylesheet_directory_uri();
    
    // Base styles
    wp_enqueue_style( 'mu-base', get_stylesheet_uri(), [], $ver );
    
    // Componentes globales
    wp_enqueue_style( 'mu-header', "$uri/css/components/header.css", [ 'mu-base' ], $ver );
    wp_enqueue_style( 'mu-footer', "$uri/css/components/footer.css", [ 'mu-base' ], $ver );
    wp_enqueue_style( 'mu-share', "$uri/css/components/share-button.css", [ 'mu-base' ], $ver );
    
    // JavaScript global
    wp_enqueue_script( 'mu-share-js', "$uri/js/share-button.js", [], $ver, true );
    wp_localize_script( 'mu-share-js', 'muShareVars', [
        'checkIcon' => function_exists( 'mu_get_icon' ) ? mu_get_icon( 'check' ) : ''
    ] );

    // Modal de autenticación (solo usuarios no logueados)
    if ( ! is_user_logged_in() ) {
        wp_enqueue_style( 'mu-modal-auth', "$uri/css/components/modal-auth.css", [ 'mu-base' ], $ver );
        wp_enqueue_script( 'mu-modal-auth-js', "$uri/js/modal-auth.js", [], $ver, true );
    }
    
    // Estilos condicionales por página
    if ( is_front_page() ) {
        wp_enqueue_style( 'mu-home', "$uri/css/home.css", [ 'mu-base' ], $ver );
    }
    
    if ( is_shop() || is_product_category() || is_product_tag() ) {
        wp_enqueue_style( 'mu-shop', "$uri/css/shop.css", [ 'mu-base' ], $ver );
    }
    
    if ( is_product() ) {
        wp_enqueue_style( 'mu-product', "$uri/css/product.css", [ 'mu-base' ], $ver );
    }
    
    if ( is_cart() ) {
        wp_enqueue_style( 'mu-cart', "$uri/css/cart.css", [ 'mu-base' ], $ver );
        wp_enqueue_script( 'mu-cart-js', "$uri/js/cart.js", [ 'jquery' ], $ver, true );
        wp_localize_script( 'mu-cart-js', 'muCartVars', [
            'closeIcon' => function_exists( 'mu_get_icon' ) ? mu_get_icon( 'close' ) : ''
        ] );
    }

    if ( is_checkout() && ! is_order_received_page() ) {
        wp_enqueue_style( 'mu-checkout', "$uri/css/checkout.css", [ 'mu-base' ], $ver );
        wp_register_script( 'libphonenumber-js', 'https://unpkg.com/libphonenumber-js@1.10.49/bundle/libphonenumber-js.min.js', [], '1.10.49', true );
        wp_enqueue_script( 'mu-checkout-js', "$uri/js/checkout.js", [ 'jquery', 'libphonenumber-js' ], $ver, true );
        wp_localize_script( 'mu-checkout-js', 'muCheckout', [
            'isLoggedIn' => is_user_logged_in(),
            'ajaxUrl'    => WC_AJAX::get_endpoint( 'mu_check_email' ),
            'nonce'      => wp_create_nonce( 'check-email-nonce' ),
        ] );
    }
    
    // Scripts globales
    wp_enqueue_script( 'mu-header-js', "$uri/js/header.js", [], $ver, true );
    wp_enqueue_script( 'mu-footer-js', "$uri/js/footer.js", [], $ver, true );
    wp_enqueue_script( 'mu-ui-scripts', "$uri/js/mu-ui-scripts.js", [], $ver, true );
}
add_action( 'wp_enqueue_scripts', 'mu_enqueue_assets', 20 );

// ============================================
// CSS CONDICIONAL - WPLingua Switcher
// ============================================

function mu_hide_wplingua_switcher() {
    if ( is_admin() ) return;
    
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ( ! in_array( $host, [ 'us.muyunicos.com', 'br.muyunicos.com' ], true ) ) {
        wp_add_inline_style( 'mu-base', '.wplng-switcher { display: none !important; }' );
    }
}
add_action( 'wp_enqueue_scripts', 'mu_hide_wplingua_switcher', 25 );

// ============================================
// CARGA DE MÓDULOS
// ============================================

/**
 * Carga un módulo PHP si existe
 * 
 * @param string $module Nombre del módulo (sin extensión)
 */
function mu_load_module( $module ) {
    $file = get_stylesheet_directory() . '/inc/' . $module . '.php';
    
    if ( file_exists( $file ) ) {
        require_once $file;
    }
}

// Orden de carga (respetando dependencias)
mu_load_module( 'icons' );         // SVG icons repository
mu_load_module( 'geo' );           // Multi-country system + Digital Restriction
mu_load_module( 'auth-modal' );    // Authentication modal
mu_load_module( 'checkout' );      // Checkout optimizations
mu_load_module( 'cart' );          // Cart functionality
mu_load_module( 'product' );       // Product UX (physical/digital linking)
mu_load_module( 'ui' );            // UI components (header, footer, search)
