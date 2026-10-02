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

    // Remove duplicate style.css loaded by GeneratePress parent theme
    wp_dequeue_style( 'generate-child' );
    wp_deregister_style( 'generate-child' );

    // Base styles
    wp_enqueue_style( 'mu-base', get_stylesheet_uri(), [], $ver );

    // Componentes globales
    wp_enqueue_style( 'mu-global-ui', "$uri/css/components/global-ui.css", [ 'mu-base' ], $ver );
    wp_enqueue_style( 'mu-header', "$uri/css/components/header.css", [ 'mu-base' ], $ver );
    wp_enqueue_style( 'mu-footer', "$uri/css/components/footer.css", [ 'mu-base' ], $ver );

    // JavaScript global
    wp_enqueue_script( 'mu-global-ui-js', "$uri/js/global-ui.js", [], $ver, true );
    if ( wp_script_is( 'mu-global-ui-js', 'enqueued' ) ) {
        wp_localize_script( 'mu-global-ui-js', 'muGlobalVars', [
            'checkIcon' => function_exists( 'mu_get_icon' ) ? mu_get_icon( 'check' ) : ''
        ] );
    }

    // Modal de autenticación (solo usuarios no logueados)
    if ( ! is_user_logged_in() ) {
        wp_enqueue_style( 'mu-modal-auth', "$uri/css/components/modal-auth.css", [ 'mu-base' ], $ver );
        wp_enqueue_script( 'mu-modal-auth-js', "$uri/js/modal-auth.js", [], $ver, true );

        // Localize inmediatamente después del enqueue para garantizar que muAuthData exista.
        // (Antes vivía en inc/auth-modal.php en prioridad 25, pero el enqueue ocurre en la 999,
        //  por lo que el guardia wp_script_is() nunca lo detectaba.)
        if ( mu_is_woocommerce_active() ) {
            wp_localize_script( 'mu-modal-auth-js', 'muAuthData', [
                'ajax_url' => WC_AJAX::get_endpoint( '%%endpoint%%' ),
                'nonce'    => wp_create_nonce( 'mu_auth_nonce' ),
                'home_url' => home_url( '/' )
            ] );
        }
    }

    // Estilos condicionales por página
    if ( is_front_page() ) {
        wp_enqueue_style( 'mu-home', "$uri/css/home.css", [ 'mu-base' ], $ver );
    }

    if ( mu_wc_is_shop() || mu_wc_is_product_category() || mu_wc_is_product_tag() || mu_wc_is_product() ) {
        wp_enqueue_style( 'mu-shop', "$uri/css/shop.css", [ 'mu-base' ], $ver );
        wp_enqueue_style( 'mu-navigation-chips', "$uri/css/components/navigation-chips.css", [ 'mu-base' ], $ver );
        wp_enqueue_script( 'mu-shop-js', "$uri/js/shop.js", [], $ver, true );
        wp_enqueue_script( 'mu-navigation-chips-js', "$uri/js/navigation-chips.js", [], $ver, true );
    }

    // Ficha de producto individual
    if ( mu_wc_is_product() ) {
        wp_enqueue_style( 'mu-product', "$uri/css/product.css", [ 'mu-base' ], $ver );
        wp_enqueue_script( 'mu-product-js', "$uri/js/product.js", [ 'wc-single-product' ], $ver, true );
    }

    // Product Builder (Core + Addons Etiquetas/Nombre)
    if ( mu_wc_is_product() || mu_wc_is_cart() ) {
        wp_enqueue_style( 'mu-product-builder', "$uri/css/product-builder.css", [ 'mu-base' ], $ver );
        wp_enqueue_script( 'mu-addon-nombre', "$uri/js/addon-nombre.js", [ 'jquery' ], $ver, true );
    }

    // Addon Nombre: pasar datos AJAX solo en carrito/checkout
    if ( mu_wc_is_cart() || mu_wc_is_checkout() ) {
        if ( wp_script_is( 'mu-addon-nombre', 'enqueued' ) ) {
            wp_localize_script( 'mu-addon-nombre', 'muNombreData', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'update-cart-name' ),
            ] );
        }
    }

    if ( mu_wc_is_cart() ) {
        wp_enqueue_style( 'mu-cart', "$uri/css/cart.css", [ 'mu-base' ], $ver );
        wp_enqueue_script( 'mu-cart-js', "$uri/js/cart.js", [ 'jquery' ], $ver, true );
        if ( wp_script_is( 'mu-cart-js', 'enqueued' ) ) {
            wp_localize_script( 'mu-cart-js', 'muCartVars', [
                'closeIcon' => function_exists( 'mu_get_icon' ) ? mu_get_icon( 'close' ) : ''
            ] );
        }
    }

    if ( mu_wc_is_checkout() && ! mu_wc_is_wc_endpoint_url( 'order-received' ) ) {
        wp_enqueue_style( 'mu-checkout', "$uri/css/checkout.css", [ 'mu-base' ], $ver );
        wp_register_script( 'libphonenumber-js', 'https://unpkg.com/libphonenumber-js@1.10.49/bundle/libphonenumber-js.min.js', [], '1.10.49', true );
        wp_enqueue_script( 'mu-checkout-js', "$uri/js/checkout.js", [ 'jquery', 'libphonenumber-js' ], $ver, true );
        if ( wp_script_is( 'mu-checkout-js', 'enqueued' ) ) {
            // Datos de países para redirección al subdominio al cambiar de país
            $mu_countries = [];
            if ( function_exists( 'muyu_get_countries_data' ) ) {
                foreach ( muyu_get_countries_data() as $code => $data ) {
                    $mu_countries[ $code ] = [
                        'host'   => $data['host'],
                        'prefix' => function_exists( 'muyu_country_language_prefix' ) ? muyu_country_language_prefix( $code ) : '',
                    ];
                }
            }

            wp_localize_script( 'mu-checkout-js', 'muCheckout', [
                'isLoggedIn'     => is_user_logged_in(),
                'ajaxUrl'        => WC_AJAX::get_endpoint( 'mu_check_email' ),
                'nonce'          => wp_create_nonce( 'check-email-nonce' ),
                'myAccountUrl'   => wc_get_page_permalink( 'myaccount' ),
                'countries'      => $mu_countries,
                'currentCountry' => function_exists( 'muyu_get_current_country_from_subdomain' ) ? muyu_get_current_country_from_subdomain() : 'AR',
            ] );
        }
    }

    // Mi Cuenta > Descargas (Custom Styles)
    if ( mu_wc_is_account_page() && mu_wc_is_wc_endpoint_url( 'downloads' ) ) {
        wp_enqueue_style( 'mu-account-downloads', "$uri/css/account-downloads.css", [ 'mu-base' ], $ver );
    }

    // Scripts globales
    wp_enqueue_script( 'mu-header-js', "$uri/js/header.js", [], $ver, true );
    wp_enqueue_script( 'mu-footer-js', "$uri/js/footer.js", [], $ver, true );
}
add_action( 'wp_enqueue_scripts', 'mu_enqueue_assets', 999 );

// ============================================
// WRAPPER FUNCTIONS PARA WOOCOMMERCE
// ============================================

if ( ! function_exists( 'mu_is_woocommerce_active' ) ) {
    /**
     * Verifica si WooCommerce está activo
     *
     * @return bool True si WooCommerce está activo, false en caso contrario
     */
    function mu_is_woocommerce_active() {
        return function_exists( 'is_woocommerce' );
    }
}

if ( ! function_exists( 'mu_wc_is_shop' ) ) {
    /**
     * Wrapper seguro para is_shop()
     *
     * @return bool True si es página de tienda, false en caso contrario
     */
    function mu_wc_is_shop() {
        if ( ! mu_is_woocommerce_active() ) {
            return false;
        }
        return is_shop();
    }
}

if ( ! function_exists( 'mu_wc_is_product_category' ) ) {
    /**
     * Wrapper seguro para is_product_category()
     *
     * @return bool True si es página de categoría de producto, false en caso contrario
     */
    function mu_wc_is_product_category() {
        if ( ! mu_is_woocommerce_active() ) {
            return false;
        }
        return is_product_category();
    }
}

if ( ! function_exists( 'mu_wc_is_product_tag' ) ) {
    /**
     * Wrapper seguro para is_product_tag()
     *
     * @return bool True si es página de etiqueta de producto, false en caso contrario
     */
    function mu_wc_is_product_tag() {
        if ( ! mu_is_woocommerce_active() ) {
            return false;
        }
        return is_product_tag();
    }
}

if ( ! function_exists( 'mu_wc_is_product' ) ) {
    /**
     * Wrapper seguro para is_product()
     *
     * @return bool True si es página de producto individual, false en caso contrario
     */
    function mu_wc_is_product() {
        if ( ! mu_is_woocommerce_active() ) {
            return false;
        }
        return is_product();
    }
}

if ( ! function_exists( 'mu_wc_is_cart' ) ) {
    /**
     * Wrapper seguro para is_cart()
     *
     * @return bool True si es página de carrito, false en caso contrario
     */
    function mu_wc_is_cart() {
        if ( ! mu_is_woocommerce_active() ) {
            return false;
        }
        return is_cart();
    }
}

if ( ! function_exists( 'mu_wc_is_checkout' ) ) {
    /**
     * Wrapper seguro para is_checkout()
     *
     * @return bool True si es página de checkout, false en caso contrario
     */
    function mu_wc_is_checkout() {
        if ( ! mu_is_woocommerce_active() ) {
            return false;
        }
        return is_checkout();
    }
}

if ( ! function_exists( 'mu_wc_is_account_page' ) ) {
    /**
     * Wrapper seguro para is_account_page()
     *
     * @return bool True si es página de mi cuenta, false en caso contrario
     */
    function mu_wc_is_account_page() {
        if ( ! mu_is_woocommerce_active() ) {
            return false;
        }
        return is_account_page();
    }
}

if ( ! function_exists( 'mu_wc_is_wc_endpoint_url' ) ) {
    /**
     * Wrapper seguro para is_wc_endpoint_url()
     *
     * @param string $endpoint Endpoint a verificar
     * @return bool True si es el endpoint especificado, false en caso contrario
     */
    function mu_wc_is_wc_endpoint_url( $endpoint = '' ) {
        if ( ! mu_is_woocommerce_active() ) {
            return false;
        }
        return is_wc_endpoint_url( $endpoint );
    }
}

// ============================================
// GLOBAL AJAX THROTTLING
// ============================================

if ( ! function_exists( 'mu_ajax_rate_limit_check' ) ) {
    /**
     * Verifica rate limiting para solicitudes AJAX
     * Prevenir abusos y sobrecarga del servidor
     *
     * Usa object cache (wp_cache_*) como capa primaria cuando está disponible
     * (evita escrituras a DB si Hostinger provee Redis/LiteSpeed object cache).
     * Degrada a transient solo si no hay object cache persistente.
     *
     * Algoritmo de ventana fija con contador [count, start] — datos livianos.
     *
     * @param string $action Identificador de la acción AJAX
     * @param int $max_requests Máximo de solicitudes permitidas
     * @param int $time_window Ventana de tiempo en segundos
     * @return bool True si la solicitud está permitida
     */
    function mu_ajax_rate_limit_check( $action, $max_requests = 10, $time_window = 60 ) {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : 'unknown';
        $cache_key = 'mu_ajax_rate_' . md5( $ip . '_' . $action );
        $now = time();

        // Capa primaria: object cache (en memoria, sin DB si hay Redis/LiteSpeed)
        if ( wp_using_ext_object_cache() ) {
            $data = wp_cache_get( $cache_key, 'mu_rate_limit' );

            if ( false === $data || ( $now - $data['start'] ) >= $time_window ) {
                wp_cache_set( $cache_key, [ 'count' => 1, 'start' => $now ], 'mu_rate_limit', $time_window );
                return true;
            }

            if ( $data['count'] >= $max_requests ) {
                return false;
            }

            $data['count']++;
            wp_cache_set( $cache_key, $data, 'mu_rate_limit', $time_window );
            return true;
        }

        // Fallback: transient (solo si no hay object cache persistente)
        $data = get_transient( $cache_key );

        if ( false === $data || ( $now - $data['start'] ) >= $time_window ) {
            set_transient( $cache_key, [ 'count' => 1, 'start' => $now ], $time_window );
            return true;
        }

        if ( $data['count'] >= $max_requests ) {
            return false;
        }

        $data['count']++;
        set_transient( $cache_key, $data, $time_window );
        return true;
    }
}

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
mu_load_module( 'cdn-cache-bypass' ); // Bypass de caché CDN para contenido dinámico
mu_load_module( 'performance-monitor' ); // Performance monitoring system
mu_load_module( 'coming-soon' );         // Coming Soon override (intercepta template_redirect antes que Hostinger)
mu_load_module( 'geo' );                 // Multi-country system
mu_load_module( 'jetpack-search-integration' ); // Jetpack Search: corrige homeUrl para que los resultados apunten al subdominio actual (depende de geo.php)
mu_load_module( 'seo-hreflang' );        // SEO Multi-país (hreflang, títulos localizados)
mu_load_module( 'digital-restriction' ); // Digital Restriction System
mu_load_module( 'auth-modal' );          // Authentication modal
mu_load_module( 'login' );               // wp-login.php customization (hooks only fire on login screen)
mu_load_module( 'checkout' );            // Checkout optimizations
mu_load_module( 'cart' );               // Cart functionality
mu_load_module( 'cart-restriction' );   // Cart restriction by country (physical products in restricted subdomains)
mu_load_module( 'flexible-price' );      // Sistema de Precio Flexible v4.0 — encola js/flexible-price.js via mu_flexible_price_enqueue(). NO agregar a mu_enqueue_assets() para evitar duplicado.
mu_load_module( 'hero-banners' );        // Hero Banners Manager (admin submenu bajo WC Marketing) — debe ir antes de ui.php para que mu_get_hero_banners() esté disponible al renderizar [mu_hero_section]
mu_load_module( 'ui' );                  // UI components (header, footer, search, wplng body class)
mu_load_module( 'orders-files' );        // Order File Manager (Admin/Frontend)
mu_load_module( 'orders-workflow' );     // Order Workflow (Status, Email, WhatsApp)
mu_load_module( 'downloads-bonus' );     // Dynamic Downloads Injections
mu_load_module( 'navigation-chips' );    // Navigation Chips v8 (breadcrumb + filtros catálogo)
mu_load_module( 'tag-groups' );          // Tag Groups System (agrupamiento por etiquetas en catálogo)
mu_load_module( 'products-core' );       // Productos Personalizados Core v2.1
mu_load_module( 'addon-nombre' );        // Addon Nombre v3.0 (campo nombre personalizado)
mu_load_module( 'addon-etiquetas' );     // Addon Etiquetas v3.0 (builder de etiquetas)