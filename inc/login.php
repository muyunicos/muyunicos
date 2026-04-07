<?php
/**
 * Muy Únicos — Personalización de wp-login.php + Redirección Inteligente
 *
 * Responsabilidades:
 *  - Cargar css/login.css vía wp_enqueue_style en login_enqueue_scripts
 *  - Customizar logo URL, texto ALT y mensaje de error genérico
 *  - Redirigir clientes/suscriptores a Mi Cuenta post-login
 *
 * NO contiene lógica de contraseñas (vive en inc/checkout.php).
 *
 * @package    MuyUnicos
 * @subpackage Core/Login
 * @version    1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* =========================================
   1. ESTILOS DE LA PÁGINA DE LOGIN
   ========================================= */

if ( ! function_exists( 'mu_login_enqueue_styles' ) ) {
    function mu_login_enqueue_styles() {
        wp_enqueue_style(
            'mu-login',
            get_stylesheet_directory_uri() . '/css/login.css',
            [],
            filemtime( get_stylesheet_directory() . '/css/login.css' )
        );
    }
}
add_action( 'login_enqueue_scripts', 'mu_login_enqueue_styles' );

/* =========================================
   2. FILTROS DE LOGO Y ERRORES
   ========================================= */

// URL del logo lleva al inicio del sitio
add_filter( 'login_headerurl', function() {
    return home_url();
} );

// Texto ALT del logo
add_filter( 'login_headertext', function() {
    return 'Muy Únicos - Ir a la página de inicio';
} );

// Mensajes de error genéricos (Security through obscurity)
add_filter( 'login_errors', function() {
    return '<strong>Ups!</strong> Los datos de acceso no son correctos. Por favor intenta nuevamente.';
} );

/* =========================================
   3. REDIRECCIÓN INTELIGENTE POST-LOGIN
   ========================================= */

if ( ! function_exists( 'mu_smart_login_redirect' ) ) {
    function mu_smart_login_redirect( $redirect_to, $request, $user ) {
        // Si hay error o no hay objeto usuario, devolver standard
        if ( ! is_a( $user, 'WP_User' ) ) {
            return $redirect_to;
        }

        // Roles que van al Admin Panel — se respeta $redirect_to del admin
        $admin_roles = [ 'administrator', 'editor', 'shop_manager' ];
        if ( array_intersect( $admin_roles, (array) $user->roles ) ) {
            return $redirect_to;
        }

        // Clientes y Suscriptores → Mi Cuenta
        return wc_get_page_permalink( 'myaccount' );
    }
}
add_filter( 'login_redirect', 'mu_smart_login_redirect', 10, 3 );
