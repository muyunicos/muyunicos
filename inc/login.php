<?php
/**
 * Muy Únicos — Login Page Customization
 *
 * Personaliza wp-login.php con estilos de marca, seguridad y
 * redirección inteligente post-login.
 *
 * @package    MuyUnicos
 * @subpackage Core/Login
 * @version    2.1.0
 *
 * CARGA: mu_load_module( 'login' ) en functions.php.
 * Sin condicional adicional: todos los hooks aquí sólo disparan
 * en la pantalla de login (login_enqueue_scripts, login_headerurl,
 * login_redirect, etc.) — no afectan el frontend.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================
// 1. ESTILOS Y LOGO — wp-login.php
// ============================================================

if ( ! function_exists( 'mu_login_enqueue_assets' ) ) {
    /**
     * Registra y encola css/login.css + inyecta la URL del logo
     * vía wp_add_inline_style (sólo la propiedad background-image,
     * el resto del CSS vive en el archivo cacheable).
     */
    function mu_login_enqueue_assets() {
        $logo_url = 'https://muyunicos.com/wp-content/uploads/2026/02/logo-circular-600x600-1.webp';

        wp_enqueue_style(
            'mu-login',
            get_stylesheet_directory_uri() . '/css/login.css',
            [],
            '2.1.0'
        );

        // Solo la propiedad dinámica (URL del logo) va inline.
        // El resto de los estilos están en el CSS cacheable.
        wp_add_inline_style(
            'mu-login',
            '#login h1 a, .login h1 a { background-image: url("' . esc_url( $logo_url ) . '"); }'
        );
    }
}
add_action( 'login_enqueue_scripts', 'mu_login_enqueue_assets' );

// ============================================================
// 2. FILTROS VISUALES Y DE SEGURIDAD
// ============================================================

// URL del logo → página de inicio
if ( ! function_exists( 'mu_login_logo_url' ) ) {
    function mu_login_logo_url() {
        return home_url();
    }
}
add_filter( 'login_headerurl', 'mu_login_logo_url' );

// Texto ALT del logo
if ( ! function_exists( 'mu_login_logo_text' ) ) {
    function mu_login_logo_text() {
        return 'Muy Únicos - Ir a la página de inicio';
    }
}
add_filter( 'login_headertext', 'mu_login_logo_text' );

// Mensaje de error genérico ("Security through obscurity")
if ( ! function_exists( 'mu_login_error_message' ) ) {
    function mu_login_error_message() {
        return '<strong>Ups!</strong> Los datos de acceso no son correctos. Por favor intenta nuevamente.';
    }
}
add_filter( 'login_errors', 'mu_login_error_message' );

// ============================================================
// 3. REDIRECCIÓN INTELIGENTE POST-LOGIN
// ============================================================

if ( ! function_exists( 'mu_smart_login_redirect' ) ) {
    /**
     * Redirige clientes y suscriptores a "Mi Cuenta".
     * Admins, editores y shop managers respetan la URL de origen.
     *
     * @param string  $redirect_to URL de destino solicitada.
     * @param string  $request     URL solicitada antes del login.
     * @param WP_User $user        Objeto usuario autenticado.
     * @return string URL de redirección final.
     */
    function mu_smart_login_redirect( $redirect_to, $request, $user ) {
        if ( ! is_a( $user, 'WP_User' ) ) {
            return $redirect_to;
        }

        $admin_roles = [ 'administrator', 'editor', 'shop_manager' ];

        if ( array_intersect( $admin_roles, (array) $user->roles ) ) {
            return $redirect_to; // Respetar URL de origen para roles con acceso admin
        }

        return wc_get_page_permalink( 'myaccount' );
    }
}
add_filter( 'login_redirect', 'mu_smart_login_redirect', 10, 3 );
