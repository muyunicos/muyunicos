<?php
/**
 * Cotizador de stickers — shortcode y assets (spec 020, T009).
 *
 * ANTES vivia SOLO en el servidor (snippet del tema hijo, editado por FTP). Se
 * versiona con el contrato del bundle INTACTO: mismo id `mu-sticker-calculator-root`,
 * mismas URLs (`assets/js|css/calculadora_stickers.*`, `datos_config_etag.php`,
 * `guardar_datos.php`) y `filemtime` como version (cache-bust por archivo).
 *
 * Lo que se agrega:
 *   - registro/encolado con las dependencias del TEMA, incluida `mu-galeria` cuando
 *     esta registrada (spec 019 D26 + spec 020 D9: el bundle CONSUME el motor de
 *     galeria/visor del tema; dependencia BLANDA),
 *   - `mu_galeria_encolar()` dentro del shortcode,
 *   - `data-mu-tema="cotizador"` en la raiz (temas CSS, spec 020 D2/D3).
 *
 * Datos: `assets/datos_config.json` es del ADMIN y **no se versiona**; el bundle lo
 * lee por `datos_config_etag.php` y el admin guarda por `guardar_datos.php`.
 *
 * @package MuyUnicos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * URL de los assets del cotizador dentro del tema.
 *
 * @return string
 */
function mu_cotizador_assets_url() {
	return get_stylesheet_directory_uri() . '/assets';
}

/**
 * Registra CSS y JS del cotizador (se encolan desde el shortcode).
 *
 * `mu-galeria` se declara como dependencia SOLO si esta registrada: sin tema/motor el
 * bundle sigue funcionando con sus componentes propios (dependencia blanda, D11).
 */
function mu_cotizador_registrar() {
	$dir = get_stylesheet_directory() . '/assets';
	$uri = mu_cotizador_assets_url();
	$ver = wp_get_theme()->get( 'Version' );

	$css = $dir . '/css/calculadora_stickers.css';
	wp_register_style(
		'mu-sticker-calculator-css',
		$uri . '/css/calculadora_stickers.css',
		array(),
		file_exists( $css ) ? filemtime( $css ) : $ver
	);

	$js   = $dir . '/js/calculadora_stickers.js';
	$deps = wp_script_is( 'mu-galeria', 'registered' ) ? array( 'mu-galeria' ) : array();
	wp_register_script(
		'mu-sticker-calculator-js',
		$uri . '/js/calculadora_stickers.js',
		$deps,
		file_exists( $js ) ? filemtime( $js ) : $ver,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'mu_cotizador_registrar', 998 );

/**
 * Shortcode `[calculadora_stickers]` (contrato del bundle, sin cambios).
 *
 * @return string HTML del contenedor donde monta React.
 */
function mu_sticker_calculator_shortcode() {
	// Motor de galeria/visor del TEMA (spec 019 D26): se encola ANTES del bundle.
	if ( function_exists( 'mu_galeria_encolar' ) ) {
		mu_galeria_encolar();
	}

	wp_enqueue_style( 'mu-sticker-calculator-css' );
	wp_enqueue_script( 'mu-sticker-calculator-js' );

	$is_admin   = current_user_can( 'manage_options' );
	$assets_url = mu_cotizador_assets_url();

	wp_localize_script(
		'mu-sticker-calculator-js',
		'WP_STICKER_DATA',
		array(
			'isAdmin'   => $is_admin,
			'assetsUrl' => $assets_url,
			'configUrl' => $assets_url . '/datos_config_etag.php',
			'saveUrl'   => $assets_url . '/guardar_datos.php',
		)
	);

	// Media Library para el boton de galeria del admin (varios cientos de KB).
	if ( $is_admin ) {
		wp_enqueue_media();
	}

	// `data-mu-tema` (spec 020): el contenedor declara el tema visual del cotizador.
	return '<div id="mu-sticker-calculator-root" data-mu-tema="cotizador">Cargando cotizador pro...</div>';
}
add_shortcode( 'calculadora_stickers', 'mu_sticker_calculator_shortcode' );