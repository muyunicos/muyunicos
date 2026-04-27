<?php
/**
 * Muy Únicos — Coming Soon Override v2.0.0
 *
 * Basado en lectura directa de ComingSoon.php del plugin Hostinger.
 *
 * Cómo funciona el plugin:
 *   - Instancia la clase con `new ComingSoon()` al final del archivo.
 *   - Registra: add_action( 'template_redirect', array( $this, 'coming_soon' ) )
 *     sin prioridad explícita → prioridad por defecto = 10.
 *   - No existe ninguna get_option() que indique si está activo:
 *     si la clase ComingSoon existe en memoria → el modo está activo.
 *   - Bypass nativo: is_admin() || current_user_can('update_plugins') || bypass_code.
 *
 * Nuestra estrategia:
 *   - Enganchamos template_redirect con prioridad 1 (antes que el plugin).
 *   - Replicamos su bypass exacto para no romper acceso a admins.
 *   - Servimos nuestra plantilla y llamamos die(), bloqueando la del plugin.
 *
 * @package GeneratePress_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─────────────────────────────────────────────────────────
// 1. ¿Debe esta request ignorar el override?
//    Espejo de can_bypass_coming_soon() del plugin Hostinger.
// ─────────────────────────────────────────────────────────

if ( ! function_exists( 'mu_coming_soon_should_bypass' ) ) {
	function mu_coming_soon_should_bypass(): bool {
		// Admin panel: el plugin también lo permite.
		if ( is_admin() ) {
			return true;
		}

		// AJAX, Cron, REST: no interceptar nunca.
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return true;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		// WooCommerce AJAX custom (wc-ajax=*).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['wc-ajax'] ) ) {
			return true;
		}

		// El plugin usa update_plugins (no manage_options) — respetamos eso exacto.
		if ( current_user_can( 'update_plugins' ) ) {
			return true;
		}

		// Bypass por cookie que el plugin mismo setea cuando validate GET bypass_code.
		// Si la cookie existe y no está vacía, el plugin ya la validó antes.
		$bypass_cookie = isset( $_COOKIE['hostinger_bypass_code'] ) // phpcs:ignore WordPress.Security.NonceVerification
			? sanitize_text_field( wp_unslash( $_COOKIE['hostinger_bypass_code'] ) )
			: '';

		if ( ! empty( $bypass_cookie ) ) {
			return true;
		}

		return false;
	}
}

// ─────────────────────────────────────────────────────────
// 2. ¿Está activo el Coming Soon de Hostinger?
//    La clase solo existe en memoria si el plugin cargó su archivo.
// ─────────────────────────────────────────────────────────

if ( ! function_exists( 'mu_is_hostinger_coming_soon_active' ) ) {
	function mu_is_hostinger_coming_soon_active(): bool {
		// class_exists() es O(1) — sin queries a la DB.
		// Cubrimos el nombre de clase en versiones antiguas y nuevas del plugin.
		return class_exists( 'ComingSoon' )
			|| class_exists( 'Hostinger\\Includes\\ComingSoon' )
			|| class_exists( 'Hostinger_Coming_Soon' );
	}
}

// ─────────────────────────────────────────────────────────
// 3. Interceptar con prioridad 1 → antes que el plugin (prioridad 10)
// ─────────────────────────────────────────────────────────

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
			// Fallback seguro: dejamos que el plugin sirva la suya.
			return;
		}

		status_header( 503 );
		header( 'Retry-After: 3600' );
		nocache_headers();

		// Enqueue el CSS antes de que wp_head() corra dentro de la plantilla.
		add_action(
			'wp_enqueue_scripts',
			function () {
				$ver = wp_get_theme()->get( 'Version' );
				$uri = get_stylesheet_directory_uri();
				wp_enqueue_style( 'mu-coming-soon', "$uri/css/coming-soon.css", [], $ver );
			},
			5
		);

		include $template;
		die; // Igual que el plugin: corta la ejecución de WordPress.
	}
}
add_action( 'template_redirect', 'mu_render_coming_soon_override', 1 );
