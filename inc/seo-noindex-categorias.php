<?php
/**
 * Muy Únicos — noindex del árbol de categorías no-core
 *
 * Las categorías de Wii/electrónica/hogar/librería diluyen la autoridad
 * temática del dominio (etiquetas/stickers). Se marcan como noindex,follow
 * para que Google se enfoque en el core, sin tocar los productos (siguen
 * accesibles). El core queda indexable.
 *
 * @package GeneratePress_Child
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'mu_noindex_categorias_slugs' ) ) {
	/**
	 * Slugs de categorías no-core (noindex).
	 *
	 * @return array
	 */
	function mu_noindex_categorias_slugs() {
		return array(
			'tienda-juegosparawii',
			'accesorios',
			'consolas',
			'juegos',
			'repuestos',
			'varios',
			'electronica-audio-y-video',
			'accesorios-para-audio-y-video',
			'lentes-de-realidad-virtual',
			'organizadores-de-cables',
			'placas-de-video',
			'hogar-muebles-y-jardin',
			'banos',
			'accesorios-para-banos',
			'organizadores',
			'bazar-y-cocina',
			'utensilios-de-preparacion',
			'cortadores-y-ralladores',
			'ralladores',
			'interruptores',
			'libreria',
			'escolar',
			'lapiceras',
			'papeles-para-impresion',
			'pegamentos-y-cortantes',
			'pegamentos',
			'deportes',
			'alforjas-bolsos-y-cartucheras',
			'sin-categorizar',
		);
	}
}

if ( ! function_exists( 'mu_noindex_categoria_actual' ) ) {
	/**
	 * Slug de la categoría de producto actual ('' si no aplica).
	 *
	 * @return string
	 */
	function mu_noindex_categoria_actual() {
		if ( ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
			return '';
		}

		$term = get_queried_object();

		if ( ! $term || ! isset( $term->slug ) ) {
			return '';
		}

		return (string) $term->slug;
	}
}

if ( ! function_exists( 'mu_noindex_aplicar' ) ) {
	/**
	 * Aplica noindex,follow a las categorías no-core.
	 *
	 * @param array|string $robots Directivas robots.
	 * @return array|string
	 */
	function mu_noindex_aplicar( $robots ) {
		$slug = mu_noindex_categoria_actual();

		if ( '' === $slug || ! in_array( $slug, mu_noindex_categorias_slugs(), true ) ) {
			return $robots;
		}

		if ( is_array( $robots ) ) {
			$robots['noindex'] = true;
			unset( $robots['index'] );

			return $robots;
		}

		return $robots . ', noindex, follow';
	}

	add_filter( 'rank_math/frontend/robots', 'mu_noindex_aplicar' );
	add_filter( 'wp_robots', 'mu_noindex_aplicar' );
}