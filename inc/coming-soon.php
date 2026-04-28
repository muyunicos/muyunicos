<?php
/**
 * Muy Únicos — Coming Soon Override v2.2.0
 *
 * Basado en lectura directa de ComingSoon.php del plugin Hostinger Tools.
 *
 * Cómo funciona el plugin (confirmado en código fuente):
 *   - Registra: add_action( 'template_redirect', [$this, 'coming_soon'] ) — prioridad 10.
 *   - Dentro de coming_soon(): si can_bypass() es false → include_once View + die.
 *   - La clase está bajo un namespace PHP → class_exists('ComingSoon') FALLA en scope global.
 *   - HOSTINGER_ABSPATH es una constante que el plugin define al cargarse.
 *
 * Nuestra estrategia (v2.2.0):
 *   - Detección: defined('HOSTINGER_ABSPATH') — sin queries DB, sin namespace issues.
 *   - Fallback: verificar si el View file existe en disco (extra seguridad).
 *   - Hook: template_redirect prioridad 1 → corremos ANTES que el plugin (prioridad 10).
 *   - Bypass espejo exacto del plugin: is_admin, update_plugins, cookie, AJAX, REST, wc-ajax.
 *   - FIX v2.2.0: GET bypass_code validado ANTES de mostrar pantalla (Hostinger en p10
 *     nunca llegaba a setear el cookie porque nosotros hacíamos die en p1).
 *   - FIX v2.2.0: Eliminado add_action('wp_enqueue_scripts') — la plantilla es standalone
 *     (sin wp_head()), ese enqueue era código muerto.
 *
 * @package GeneratePress_Child
 * @since   2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. Bypass: replicar exactamente can_bypass_coming_soon() del plugin
//    + validar GET bypass_code ANTES de mostrar pantalla (fix v2.2.0)
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

		// ── FIX v2.2.0: GET bypass_code ──────────────────────────────────────
		// El plugin Hostinger valida ?bypass_code= en su can_bypass() y setea
		// el cookie en prioridad 10. Como nosotros corremos en prioridad 1,
		// nunca le dábamos chance al plugin de validar el código ni setear el
		// cookie — hacíamos die antes. Solución: replicar la misma lógica aquí.
		//
		// El plugin lee el bypass_code desde las opciones de Hostinger:
		//   get_option('hostinger_coming_soon_bypass_code')
		// y compara con el valor del GET. Si coincide, setea el cookie.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$get_code = isset( $_GET['bypass_code'] )
			? sanitize_text_field( wp_unslash( $_GET['bypass_code'] ) )
			: '';

		if ( ! empty( $get_code ) ) {
			$stored_code = get_option( 'hostinger_coming_soon_bypass_code', '' );
			if ( ! empty( $stored_code ) && hash_equals( (string) $stored_code, $get_code ) ) {
				// Replicar el setcookie del plugin para que las visitas siguientes
				// también tengan bypass sin necesitar el GET de nuevo.
				setcookie(
					'hostinger_bypass_code',
					$get_code,
					[
						'expires'  => time() + WEEK_IN_SECONDS,
						'path'     => '/',
						'secure'   => is_ssl(),
						'httponly' => true,
						'samesite' => 'Lax',
					]
				);
				return true;
			}
		}

		// Bypass por cookie (seteado por el plugin o por nosotros arriba).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$bypass_cookie = isset( $_COOKIE['hostinger_bypass_code'] )
			? sanitize_text_field( wp_unslash( $_COOKIE['hostinger_bypass_code'] ) )
			: '';
		if ( ! empty( $bypass_cookie ) ) {
			// Validar que el cookie coincida con el código almacenado
			// (el plugin hace la misma verificación).
			$stored_code = get_option( 'hostinger_coming_soon_bypass_code', '' );
			if ( ! empty( $stored_code ) && hash_equals( (string) $stored_code, $bypass_cookie ) ) {
				return true;
			}
		}

		return false;
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. Detección: ¿está activo el Coming Soon de Hostinger?
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
			// Fallback seguro: dejamos que el plugin sirva su pantalla.
			return;
		}

		status_header( 503 );
		header( 'Retry-After: 3600' );
		nocache_headers();

		// NOTA: NO llamar mu_coming_soon_enqueue() aquí.
		// La plantilla es standalone (sin wp_head/wp_footer) — v2.0.0+.
		// Todo el CSS y JS van inlineados directamente en templates/coming-soon.php.

		include $template;
		die; // Bloquea que el plugin sirva su propia pantalla.
	}
}
add_action( 'template_redirect', 'mu_render_coming_soon_override', 1 );
