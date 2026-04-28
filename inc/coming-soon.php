<?php
/**
 * Muy Únicos — Coming Soon Override v2.3.0
 *
 * Cómo funciona el plugin Hostinger Tools (CONFIRMADO leyendo código fuente real):
 *
 *   TODA la configuración vive en UNA sola WP option:
 *       get_option( 'hostinger_tools' )  →  array(
 *           'maintenance_mode' => bool,
 *           'bypass_code'      => string (16 chars, generado en activación),
 *           ...
 *       )
 *   Constante: HOSTINGER_PLUGIN_SETTINGS_OPTION = 'hostinger_tools'.
 *
 *   El plugin instancia ComingSoon.php solo cuando el modo está activo.
 *   add_action( 'template_redirect', [$this, 'coming_soon'] )  <- sin prioridad = p10.
 *
 *   can_bypass_coming_soon() del plugin (orden exacto):
 *       1. Lee $_COOKIE['hostinger_bypass_code']
 *       2. Si GET bypass_code === stored_code  →  setcookie() simple (sin flags) + $bypass_cookie = stored
 *       3. is_admin()                          →  true
 *       4. current_user_can('update_plugins')  →  true
 *       5. $bypass_cookie != '' && === stored  →  true
 *
 * Nuestra estrategia (v2.3.0):
 *   - Detección: leer hostinger_tools['maintenance_mode']
 *     BUGS anteriores: v2.1 usaba file_exists() (siempre true si plugin instalado).
 *                      v2.2 usaba get_option('hostinger_maintenance_mode') (option inexistente).
 *   - Bypass: leer hostinger_tools['bypass_code']
 *     BUG anterior: v2.2 usaba get_option('hostinger_coming_soon_bypass_code') (inexistente).
 *   - Cookie: setcookie() simple sin flags, igual que el plugin original.
 *   - Hook: template_redirect p1 (antes del plugin en p10).
 *   - Helper estático: mu_get_hostinger_settings() — UNA sola query DB por request.
 *
 * @package GeneratePress_Child
 * @since   2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helper: leer hostinger_tools una sola vez por request (static cache).
//
// Hardcodeamos 'hostinger_tools' en lugar de usar HOSTINGER_PLUGIN_SETTINGS_OPTION
// porque corremos en p1 y la constante puede no estar definida todavía si el
// plugin se carga después de nuestro inc/. En la práctica la constante siempre
// está disponible en plugins_loaded, pero este método es más robusto.
// ─────────────────────────────────────────────────────────────────────────────

if ( ! function_exists( 'mu_get_hostinger_settings' ) ) {
	function mu_get_hostinger_settings(): array {
		static $settings = null;
		if ( null === $settings ) {
			$raw      = get_option( 'hostinger_tools', array() );
			$settings = is_array( $raw ) ? $raw : array();
		}
		return $settings;
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. Detección: ¿está activo el modo mantenimiento de Hostinger?
// ─────────────────────────────────────────────────────────────────────────────

if ( ! function_exists( 'mu_is_hostinger_coming_soon_active' ) ) {
	function mu_is_hostinger_coming_soon_active(): bool {
		$settings = mu_get_hostinger_settings();
		return ! empty( $settings['maintenance_mode'] );
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. Bypass: espejo de can_bypass_coming_soon() del plugin
// ─────────────────────────────────────────────────────────────────────────────

if ( ! function_exists( 'mu_coming_soon_should_bypass' ) ) {
	function mu_coming_soon_should_bypass(): bool {
		if ( is_admin() ) {
			return true;
		}

		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return true;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['wc-ajax'] ) ) {
			return true;
		}

		if ( current_user_can( 'update_plugins' ) ) {
			return true;
		}

		// Leer bypass_code desde hostinger_tools['bypass_code'].
		$settings    = mu_get_hostinger_settings();
		$stored_code = isset( $settings['bypass_code'] ) ? (string) $settings['bypass_code'] : '';

		if ( empty( $stored_code ) ) {
			return false;
		}

		// El plugin lee primero el cookie y luego procesa el GET.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$bypass_cookie = isset( $_COOKIE['hostinger_bypass_code'] )
			? sanitize_text_field( wp_unslash( $_COOKIE['hostinger_bypass_code'] ) )
			: '';

		// GET bypass_code: si coincide, setear cookie (mismo comportamiento que el plugin).
		// El plugin usa setcookie() simple sin flags — replicamos igual para compatibilidad.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['bypass_code'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$get_code = sanitize_text_field( wp_unslash( $_GET['bypass_code'] ) );
			if ( hash_equals( $stored_code, $get_code ) ) {
				setcookie( 'hostinger_bypass_code', $stored_code ); // igual que el plugin original.
				$bypass_cookie = $stored_code;
			}
		}

		// Cookie válido → bypass.
		if ( ! empty( $bypass_cookie ) && hash_equals( $stored_code, $bypass_cookie ) ) {
			return true;
		}

		return false;
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. Hook principal — prioridad 1, corre antes que el plugin (prioridad 10)
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
			// Fallback seguro: el plugin sirve su propia pantalla.
			return;
		}

		status_header( 503 );
		header( 'Retry-After: 3600' );
		nocache_headers();

		// Plantilla standalone v2.0.0+: CSS y JS inlineados, sin wp_head/wp_footer.
		include $template;
		die;
	}
}
add_action( 'template_redirect', 'mu_render_coming_soon_override', 1 );
