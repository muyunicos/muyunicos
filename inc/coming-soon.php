<?php
/**
 * Muy Únicos — Coming Soon Override v1.0.0
 *
 * Intercepta el modo Coming Soon del plugin Hostinger y sirve
 * una plantilla propia del child theme, respetando admins y bots WC.
 *
 * Hook: template_redirect (prioridad 0 — antes que cualquier otro)
 *
 * @package GeneratePress_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─────────────────────────────────────────────
// 1. Detectar si Hostinger Coming Soon está activo
// ─────────────────────────────────────────────

if ( ! function_exists( 'mu_is_hostinger_coming_soon_active' ) ) {
	function mu_is_hostinger_coming_soon_active() {
		// Intentamos todas las keys conocidas del plugin de Hostinger.
		// Si en el futuro cambia, agregar la nueva aquí sin tocar el plugin.
		$candidate_options = [
			'hostinger_coming_soon',
			'hostinger_coming_soon_enabled',
			'hts_coming_soon',
			'hostinger_maintenance_mode',
		];

		foreach ( $candidate_options as $key ) {
			$value = get_option( $key, null );
			if ( null !== $value && false !== $value && '0' !== (string) $value && 0 !== (int) $value ) {
				return true;
			}
		}

		return false;
	}
}

// ─────────────────────────────────────────────
// 2. Decidir si esta petición debe bypassar
// ─────────────────────────────────────────────

if ( ! function_exists( 'mu_coming_soon_should_bypass' ) ) {
	function mu_coming_soon_should_bypass() {
		// Admin, AJAX y Cron: nunca interceptar.
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return true;
		}

		// REST API: no interceptar.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		// WooCommerce AJAX custom (wc-ajax=*).
		if ( isset( $_GET['wc-ajax'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return true;
		}

		// Administradores y editores logueados: ver el sitio normalmente.
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return true;
		}

		return false;
	}
}

// ─────────────────────────────────────────────
// 3. Interceptar y servir plantilla propia
// ─────────────────────────────────────────────

if ( ! function_exists( 'mu_render_coming_soon_override' ) ) {
	function mu_render_coming_soon_override() {
		if ( mu_coming_soon_should_bypass() ) {
			return;
		}

		if ( ! mu_is_hostinger_coming_soon_active() ) {
			return;
		}

		$template = get_stylesheet_directory() . '/templates/coming-soon.php';

		if ( ! file_exists( $template ) ) {
			return; // Fallback seguro: deja que Hostinger sirva la suya.
		}

		// Marcar para el enqueue condicional del CSS.
		$GLOBALS['mu_is_custom_coming_soon'] = true;

		status_header( 503 );
		header( 'Retry-After: 3600' );
		nocache_headers();

		include $template;
		exit;
	}
}
add_action( 'template_redirect', 'mu_render_coming_soon_override', 0 );

// ─────────────────────────────────────────────
// 4. Enqueue CSS — solo en la vista custom
// ─────────────────────────────────────────────

if ( ! function_exists( 'mu_coming_soon_enqueue' ) ) {
	function mu_coming_soon_enqueue() {
		if ( empty( $GLOBALS['mu_is_custom_coming_soon'] ) ) {
			return;
		}

		$ver = wp_get_theme()->get( 'Version' );
		$uri = get_stylesheet_directory_uri();

		wp_enqueue_style(
			'mu-coming-soon',
			"$uri/css/coming-soon.css",
			[ 'mu-base' ],
			$ver
		);
	}
}
add_action( 'wp_enqueue_scripts', 'mu_coming_soon_enqueue', 20 );
