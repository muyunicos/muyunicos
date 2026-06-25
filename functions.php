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
 * @version 1.3.1
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================
// SISTEMA DE ENQUEUE MODULAR
// ============================================

function mu_enqueue_assets() {
    $ver = wp_get_theme()->get( 'Version' );
    $uri = get_stylesheet_directory_uri();

    // GeneratePress parent theme already loads style.css as 'generate-child-css'
    // We don't need to load it again to avoid duplication

    // Componentes globales (dependen de generate-style-css para cargar después)
    wp_enqueue_style( 'mu-global-ui', "$uri/css/components/global-ui.css", [ 'generate-style-css' ], $ver );
    wp_enqueue_style( 'mu-header', "$uri/css/components/header.css", [ 'generate-style-css' ], $ver );
    wp_enqueue_style( 'mu-footer', "$uri/css/components/footer.css", [ 'generate-style-css' ], $ver );

    // JavaScript global
    wp_enqueue_script( 'mu-global-ui-js', "$uri/js/global-ui.js", [], $ver, true );
    wp_localize_script( 'mu-global-ui-js', 'muGlobalVars', [
        'checkIcon' => function_exists( 'mu_get_icon' ) ? mu_get_icon( 'check' ) : ''
    ] );

    // Modal de autenticación (solo usuarios no logueados)
    if ( ! is_user_logged_in() ) {
        wp_enqueue_style( 'mu-modal-auth', "$uri/css/components/modal-auth.css", [ 'generate-style-css' ], $ver );
        wp_enqueue_script( 'mu-modal-auth-js', "$uri/js/modal-auth.js", [], $ver, true );
    }

    // Estilos condicionales por página
    if ( is_front_page() ) {
        wp_enqueue_style( 'mu-home', "$uri/css/home.css", [ 'generate-style-css' ], $ver );
    }

    if ( is_shop() || is_product_category() || is_product_tag() || is_product() ) {
        wp_enqueue_style( 'mu-shop', "$uri/css/shop.css", [ 'generate-style-css' ], $ver );
        wp_enqueue_style( 'mu-navigation-chips', "$uri/css/components/navigation-chips.css", [ 'generate-style-css' ], $ver );
        wp_enqueue_script( 'mu-shop-js', "$uri/js/shop.js", [ 'jquery' ], $ver, true );
        wp_enqueue_script( 'mu-navigation-chips-js', "$uri/js/navigation-chips.js", [], $ver, true );
    }

    // Ficha de producto individual
    if ( is_product() ) {
        wp_enqueue_style( 'mu-product', "$uri/css/product.css", [ 'generate-style-css' ], $ver );
        wp_enqueue_script( 'mu-product-js', "$uri/js/product.js", [ 'wc-single-product' ], $ver, true );
    }

    // Product Builder (Core + Addons Etiquetas/Nombre)
    if ( is_product() || is_cart() ) {
        wp_enqueue_style( 'mu-product-builder', "$uri/css/product-builder.css", [ 'generate-style-css' ], $ver );
        wp_enqueue_script( 'mu-addon-nombre', "$uri/js/addon-nombre.js", [ 'jquery' ], $ver, true );
    }

    // Addon Nombre: pasar datos AJAX solo en carrito/checkout
    if ( is_cart() || is_checkout() ) {
        wp_localize_script( 'mu-addon-nombre', 'muNombreData', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'update-cart-name' ),
        ] );
    }

    if ( is_cart() ) {
        wp_enqueue_style( 'mu-cart', "$uri/css/cart.css", [ 'generate-style-css' ], $ver );
        wp_enqueue_script( 'mu-cart-js', "$uri/js/cart.js", [ 'jquery' ], $ver, true );
        wp_localize_script( 'mu-cart-js', 'muCartVars', [
            'closeIcon' => function_exists( 'mu_get_icon' ) ? mu_get_icon( 'close' ) : ''
        ] );
    }

    if ( is_checkout() && ! is_order_received_page() ) {
        wp_enqueue_style( 'mu-checkout', "$uri/css/checkout.css", [ 'generate-style-css' ], $ver );
        wp_register_script( 'libphonenumber-js', 'https://unpkg.com/libphonenumber-js@1.10.49/bundle/libphonenumber-js.min.js', [], '1.10.49', true );
        wp_enqueue_script( 'mu-checkout-js', "$uri/js/checkout.js", [ 'jquery', 'libphonenumber-js' ], $ver, true );
        wp_localize_script( 'mu-checkout-js', 'muCheckout', [
            'isLoggedIn'   => is_user_logged_in(),
            'ajaxUrl'      => WC_AJAX::get_endpoint( 'mu_check_email' ),
            'nonce'        => wp_create_nonce( 'check-email-nonce' ),
            'myAccountUrl' => wc_get_page_permalink( 'myaccount' ),
        ] );
    }

    // Mi Cuenta > Descargas (Custom Styles)
    if ( is_account_page() && is_wc_endpoint_url( 'downloads' ) ) {
        wp_enqueue_style( 'mu-account-downloads', "$uri/css/account-downloads.css", [ 'generate-style-css' ], $ver );
    }

    // Scripts globales
    wp_enqueue_script( 'mu-header-js', "$uri/js/header.js", [], $ver, true );
    wp_enqueue_script( 'mu-footer-js', "$uri/js/footer.js", [], $ver, true );
}
add_action( 'wp_enqueue_scripts', 'mu_enqueue_assets', 100 );

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
mu_load_module( 'icons' );               // SVG icons repository
mu_load_module( 'compat-litespeed' );    // Compatibilidad LiteSpeed Cache — exclusiones JS Delay
mu_load_module( 'coming-soon' );         // Coming Soon override (intercepta template_redirect antes que Hostinger)
mu_load_module( 'geo' );                 // Multi-country system
mu_load_module( 'digital-restriction' ); // Digital Restriction System
mu_load_module( 'auth-modal' );          // Authentication modal
mu_load_module( 'login' );               // wp-login.php customization (hooks only fire on login screen)
mu_load_module( 'checkout' );            // Checkout optimizations
mu_load_module( 'cart' );               // Cart functionality
mu_load_module( 'flexible-price' );      // Sistema de Precio Flexible v4.0 — encola js/flexible-price.js via mu_flexible_price_enqueue(). NO agregar a mu_enqueue_assets() para evitar duplicado.
mu_load_module( 'hero-banners' );        // Hero Banners Manager (admin submenu bajo WC Marketing) — debe ir antes de ui.php para que mu_get_hero_banners() esté disponible al renderizar [mu_hero_section]
mu_load_module( 'ui' );                  // UI components (header, footer, search, wplng body class)
mu_load_module( 'orders-files' );        // Order File Manager (Admin/Frontend)
mu_load_module( 'orders-workflow' );     // Order Workflow (Status, Email, WhatsApp)
mu_load_module( 'downloads-bonus' );     // Dynamic Downloads Injections
mu_load_module( 'navigation-chips' );    // Navigation Chips v8 (breadcrumb + filtros catálogo)
mu_load_module( 'products-core' );       // Productos Personalizados Core v2.1
mu_load_module( 'addon-nombre' );        // Addon Nombre v3.0 (campo nombre personalizado)
mu_load_module( 'addon-etiquetas' );     // Addon Etiquetas v3.0 (builder de etiquetas)
