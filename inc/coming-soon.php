<?php
/**
 * Muy Únicos — Coming Soon Override v2.1.0
 *
 * Basado en lectura directa de ComingSoon.php del plugin Hostinger Tools.
 *
 * Cómo funciona el plugin (confirmado en código fuente):
 *   - Registra: add_action( 'template_redirect', [$this, 'coming_soon'] ) — prioridad 10.
 *   - Dentro de coming_soon(): si can_bypass() es false → include_once View + die.
 *   - La clase está bajo un namespace PHP → class_exists('ComingSoon') FALLA en scope global.
 *   - HOSTINGER_ABSPATH es una constante que el plugin define al cargarse.
 *
 * Nuestra estrategia (v2.1.0 — fix):
 *   - Detección: defined('HOSTINGER_ABSPATH') — sin queries DB, sin namespace issues.
 *   - Fallback: verificar si el View file existe en disco (extra seguridad).
 *   - Hook: template_redirect prioridad 1 → corremos ANTES que el plugin (prioridad 10).
 *   - Bypass espejo exacto del plugin: is_admin, update_plugins, cookie, AJAX, REST, wc-ajax.
 *
 * @package GeneratePress_Child
 * @since   2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. Enqueue — se llama desde dentro de la plantilla vía add_action('wp_head')
// ─────────────────────────────────────────────────────────────────────────────

if ( ! function_exists( 'mu_coming_soon_enqueue' ) ) {
	function mu_coming_soon_enqueue(): void {
		$ver = wp_get_theme()->get( 'Version' );
		$uri = get_stylesheet_directory_uri();
		wp_enqueue_style( 'mu-coming-soon', "$uri/css/coming-soon.css", [], $ver );
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. Bypass: replicar exactamente can_bypass_coming_soon() del plugin
// ─────────────────────────────────────────────────────────────────────────────

if ( ! function_exists( 'mu_coming_soon_should_bypass' ) ) {
	function mu_coming_soon_should_bypass(): bool {
		// Admin panel.
		if ( is_admin() ) {
			return true;
		}

		// AJAX, Cron, REST API.
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return true;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		// WooCommerce AJAX personalizado (wc-ajax=*).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['wc-ajax'] ) ) {
			return true;
		}

		// El plugin usa update_plugins (más restrictivo que manage_options).
		if ( current_user_can( 'update_plugins' ) ) {
			return true;
		}

		// Bypass por cookie que Hostinger setea cuando valida GET bypass_code.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$bypass_cookie = isset( $_COOKIE['hostinger_bypass_code'] )
			? sanitize_text_field( wp_unslash( $_COOKIE['hostinger_bypass_code'] ) )
			: '';
		if ( ! empty( $bypass_cookie ) ) {
			return true;
		}

		return false;
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. Detección: ¿está activo el Coming Soon de Hostinger?
//
//    v2.1.0 FIX: Reemplaza class_exists() que fallaba por namespace PHP.
//    HOSTINGER_ABSPATH es definida por el plugin al cargarse — es O(1),
//    sin queries DB y 100% agnóstica al namespace de la clase.
//    Doble verificación con el View file como fallback extra.
// ─────────────────────────────────────────────────────────────────────────────

if ( ! function_exists( 'mu_is_hostinger_coming_soon_active' ) ) {
	function mu_is_hostinger_coming_soon_active(): bool {
		// Constante definida por Hostinger Tools al activarse.
		if ( ! defined( 'HOSTINGER_ABSPATH' ) ) {
			return false;
		}

		// El plugin tiene su View en includes/Views/ComingSoon.php.
		// Si ese archivo existe → el plugin está instalado y activo.
		$view_file = HOSTINGER_ABSPATH . 'includes/Views/ComingSoon.php';

		return file_exists( $view_file );
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. Hook principal — prioridad 1, corre antes que el plugin (prioridad 10)
// ─────────────────────────────────────────────────────────────────────────────

if ( ! function_exists( 'mu_render_coming_soon_override' ) ) {
	function mu_render_coming_soon_override(): void {
		if ( mu_coming_soon_should_bypass() ) {
			return;
		}

		if ( ! mu_is_hostinger_coming_soon_active() ) {
			return;
		}

		$template = get_stylesheet_directory() . '/templates/coming-soon.php';

		if ( ! file_exists( $template ) ) {
			// Fallback seguro: dejamos que el plugin sirva su pantalla.
			return;
		}

		status_header( 503 );
		header( 'Retry-After: 3600' );
		nocache_headers();

		// Registrar CSS antes de wp_head() en la plantilla.
		add_action( 'wp_enqueue_scripts', 'mu_coming_soon_enqueue', 5 );

		include $template;
		die; // Bloquea que el plugin sirva su propia pantalla.
	}
}
add_action( 'template_redirect', 'mu_render_coming_soon_override', 1 );
