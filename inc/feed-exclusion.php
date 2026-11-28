<?php
/**
 * Muy Únicos — Exclusión de categorías del feed de Google
 *
 * Excluye del feed de Google for WooCommerce (y del feed propio
 * `feed-google.php`) los productos de las categorías indicadas y sus
 * subcategorías. Los slugs salen de `muyu_get_excluded_catalog_category_slugs()`
 * (fuente unica: outlet, tienda-juegosparawii, stickers).
 *
 * Reparto por host (lo decide `feed-google.php`):
 *   - AR (raiz): entran TODOS los productos menos esas 3 categorias.
 *   - Subdominios: entra SOLO lo digital (via `muyu_get_digital_product_ids()`)
 *     menos esas 3 categorias.
 *
 * Se engancha a los filtros del plugin:
 *   - woocommerce_gla_get_sync_ready_products_pre_filter
 *   - woocommerce_gla_product_is_sync_ready
 * y a `mu_feed_google_item` (feed propio) y `rank_math/sitemap/entry` (sitemap).
 *
 * @package GeneratePress_Child
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'mu_feed_exclusion_slugs' ) ) {
	/**
	 * Slugs de categorías excluidas del feed (y sus subcategorías).
	 *
	 * @return array
	 */
	function mu_feed_exclusion_slugs() {
		if ( function_exists( 'muyu_get_excluded_catalog_category_slugs' ) ) {
			return muyu_get_excluded_catalog_category_slugs();
		}

		return array( 'tienda-juegosparawii', 'outlet', 'stickers' );
	}
}

if ( ! function_exists( 'mu_feed_exclusion_term_ids' ) ) {
	/**
	 * IDs de los términos excluidos (incluye subcategorías), con caché.
	 *
	 * @return array
	 */
	function mu_feed_exclusion_term_ids() {
		$cached = get_transient( 'mu_feed_exclusion_ids' );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$ids = array();

		foreach ( mu_feed_exclusion_slugs() as $slug ) {
			$term = get_term_by( 'slug', $slug, 'product_cat' );

			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}

			$ids[] = (int) $term->term_id;

			$children = get_term_children( (int) $term->term_id, 'product_cat' );

			if ( ! is_wp_error( $children ) ) {
				foreach ( $children as $child ) {
					$ids[] = (int) $child;
				}
			}
		}

		$ids = array_values( array_unique( $ids ) );

		set_transient( 'mu_feed_exclusion_ids', $ids, 12 * HOUR_IN_SECONDS );

		return $ids;
	}
}

if ( ! function_exists( 'mu_feed_exclusion_product_ids' ) ) {
	/**
	 * IDs de productos excluidos (lookup id => true), con caché.
	 *
	 * Se resuelve con UNA sola query (tax_query sobre los términos ya
	 * expandidos) y se cachea; así el chequeo por producto es O(1).
	 *
	 * @return array
	 */
	function mu_feed_exclusion_product_ids() {
		$cached = get_transient( 'mu_feed_exclusion_product_ids' );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$term_ids = mu_feed_exclusion_term_ids();
		$lookup   = array();

		if ( ! empty( $term_ids ) ) {
			$query = new WP_Query(
				array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'suppress_filters' => true,
					'tax_query'      => array(
						array(
							'taxonomy'         => 'product_cat',
							'field'            => 'term_id',
							'terms'            => $term_ids,
							'include_children' => false,
						),
					),
				)
			);

			$lookup = array_fill_keys( array_map( 'intval', $query->posts ), true );
		}

		set_transient( 'mu_feed_exclusion_product_ids', $lookup, 12 * HOUR_IN_SECONDS );

		return $lookup;
	}
}

if ( ! function_exists( 'mu_feed_exclusion_is_excluded' ) ) {
	/**
	 * Indica si un producto pertenece a una categoría excluida.
	 *
	 * @param int $product_id ID del producto.
	 * @return bool
	 */
	function mu_feed_exclusion_is_excluded( $product_id ) {
		$lookup = mu_feed_exclusion_product_ids();

		return ! empty( $lookup[ (int) $product_id ] );
	}
}

if ( ! function_exists( 'mu_feed_exclusion_flush_cache' ) ) {
	/**
	 * Invalida la caché de IDs al editar términos o productos.
	 *
	 * @return void
	 */
	function mu_feed_exclusion_flush_cache() {
		delete_transient( 'mu_feed_exclusion_ids' );
		delete_transient( 'mu_feed_exclusion_product_ids' );

		if ( function_exists( 'mu_feed_google_purge_cache' ) ) {
			mu_feed_google_purge_cache();
		}
	}
	add_action( 'edited_product_cat', 'mu_feed_exclusion_flush_cache' );
	add_action( 'create_product_cat', 'mu_feed_exclusion_flush_cache' );
	add_action( 'delete_product_cat', 'mu_feed_exclusion_flush_cache' );
	add_action( 'save_post_product', 'mu_feed_exclusion_flush_cache' );
	add_action( 'woocommerce_update_product', 'mu_feed_exclusion_flush_cache' );
}

if ( ! function_exists( 'mu_feed_exclusion_plugin_sync_ready' ) ) {
	/**
	 * Excluye del sync de Google for WooCommerce los productos de categorías
	 * excluidas (filtro previo al armado de la lista).
	 *
	 * @param WC_Product[] $products Productos sync-ready.
	 * @return WC_Product[]
	 */
	function mu_feed_exclusion_plugin_sync_ready( $products ) {
		if ( ! is_array( $products ) ) {
			return $products;
		}

		return array_values(
			array_filter(
				$products,
				function ( $product ) {
					return ! ( $product && method_exists( $product, 'get_id' ) && mu_feed_exclusion_is_excluded( $product->get_id() ) );
				}
			)
		);
	}
	add_filter( 'woocommerce_gla_get_sync_ready_products_pre_filter', 'mu_feed_exclusion_plugin_sync_ready', 10, 1 );
}

if ( ! function_exists( 'mu_feed_exclusion_plugin_is_sync_ready' ) ) {
	/**
	 * Excluye por producto en el chequeo de sync-ready del plugin.
	 *
	 * @param bool       $ready   Estado previo.
	 * @param WC_Product $product Producto.
	 * @return bool
	 */
	function mu_feed_exclusion_plugin_is_sync_ready( $ready, $product ) {
		if ( $ready && $product && method_exists( $product, 'get_id' ) && mu_feed_exclusion_is_excluded( $product->get_id() ) ) {
			return false;
		}

		return $ready;
	}
	add_filter( 'woocommerce_gla_product_is_sync_ready', 'mu_feed_exclusion_plugin_is_sync_ready', 10, 2 );
}

if ( ! function_exists( 'mu_feed_exclusion_sitemap_entry' ) ) {
	/**
	 * Saca del sitemap de Rank Math los productos excluidos del feed: los de las
	 * categorias (outlet/wii/stickers) y, en subdominios, los no digitales.
	 *
	 * @param mixed  $url  Entrada del sitemap (array con 'loc').
	 * @param string $type Tipo ('post','term','user').
	 * @param mixed  $post Objeto de la entrada.
	 * @return mixed La entrada, o [] para excluirla.
	 */
	function mu_feed_exclusion_sitemap_entry( $url, $type = '', $post = null ) {
		if ( 'post' !== $type || ! is_object( $post ) || ! isset( $post->post_type ) || 'product' !== $post->post_type ) {
			return $url;
		}

		$product_id = (int) $post->ID;

		if ( mu_feed_exclusion_is_excluded( $product_id ) ) {
			return array();
		}

		static $restricted = null;
		static $digital_ids = null;
		if ( null === $restricted ) {
			$restricted = class_exists( 'MUYU_Digital_Restriction_System' )
				&& MUYU_Digital_Restriction_System::get_instance()->is_restricted_user();
			$digital_ids = ( $restricted && function_exists( 'muyu_get_digital_product_ids' ) )
				? array_map( 'intval', muyu_get_digital_product_ids() )
				: array();
		}

		if ( $restricted && ! empty( $digital_ids ) && ! in_array( $product_id, $digital_ids, true ) ) {
			return array();
		}

		return $url;
	}
	add_filter( 'rank_math/sitemap/entry', 'mu_feed_exclusion_sitemap_entry', 10, 3 );
}