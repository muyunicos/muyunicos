<?php
/**
 * Muy Únicos - Feed Google Shopping por país (WCPBC)
 *
 * Genera un feed RSS 2.0 + namespace g: por país, con el precio de la zona
 * WCPBC que corresponde al subdominio (o a ?wcpbc-manual-country=XX). Se
 * sirve en https://{subdominio}/feed-google.xml sin depender de un flush de
 * reglas (match de REQUEST_URI en template_redirect p0).
 *
 * @package GeneratePress_Child
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'mu_feed_google_country' ) ) {
	/**
	 * Resuelve el país del feed: override manual (whitelist ISO de los países
	 * configurados) o el subdominio actual.
	 *
	 * @return string Código de país ISO alpha-2.
	 */
	function mu_feed_google_country() {
		$countries = muyu_get_countries_data();

		$manual = isset( $_GET['wcpbc-manual-country'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_GET['wcpbc-manual-country'] ) ) )
			: '';

		if ( '' !== $manual && isset( $countries[ $manual ] ) ) {
			return $manual;
		}

		$country = muyu_get_current_country_from_subdomain();

		return isset( $countries[ $country ] ) ? $country : 'AR';
	}
}

if ( ! function_exists( 'mu_feed_google_zone' ) ) {
	/**
	 * Devuelve la zona WCPBC que cubre el país indicado, o null.
	 *
	 * @param string $country Código de país.
	 * @return WCPBC_Pricing_Zone|null
	 */
	function mu_feed_google_zone( $country ) {
		if ( ! class_exists( 'WCPBC_Pricing_Zones' ) ) {
			return null;
		}

		$zones = WCPBC_Pricing_Zones::get_zones();

		if ( ! is_array( $zones ) ) {
			return null;
		}

		foreach ( $zones as $zone ) {
			if ( ! is_object( $zone ) || ! method_exists( $zone, 'get_countries' ) ) {
				continue;
			}
			$zone_countries = $zone->get_countries();
			if ( is_array( $zone_countries ) && in_array( $country, $zone_countries, true ) ) {
				return $zone;
			}
		}

		return null;
	}
}

if ( ! function_exists( 'mu_feed_google_decimals' ) ) {
	/**
	 * Decimales del precio según el país (espejo de mu_custom_price_decimals).
	 *
	 * @param string $country Código de país.
	 * @return int
	 */
	function mu_feed_google_decimals( $country ) {
		return in_array( $country, array( 'AR', 'CL', 'CO' ), true ) ? 0 : 2;
	}
}

if ( ! function_exists( 'mu_feed_google_price' ) ) {
	/**
	 * Precio del producto en la zona indicada. Para productos variables usa el
	 * precio mínimo de la variación. Devuelve null si no hay precio en la zona
	 * (el item se omite; no se inventa ARS).
	 *
	 * @param WCPBC_Pricing_Zone|null $zone       Zona WCPBC (o null).
	 * @param int                     $product_id ID del producto.
	 * @return float|null
	 */
	function mu_feed_google_price( $zone, $product_id ) {
		if ( $zone && method_exists( $zone, 'get_post_price' ) ) {
			$price = $zone->get_post_price( $product_id, '_price' );

			if ( ( '' === $price || null === $price || false === $price ) && method_exists( $zone, 'get_post_price' ) ) {
				$price = $zone->get_post_price( $product_id, '_min_variation_price' );
			}

			if ( is_numeric( $price ) && (float) $price >= 0 ) {
				return (float) $price;
			}

			return null;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return null;
		}

		$base = $product->get_price();

		return ( '' !== $base && null !== $base && is_numeric( $base ) ) ? (float) $base : null;
	}
}

if ( ! function_exists( 'mu_feed_google_availability' ) ) {
	/**
	 * Mapea el stock status de WooCommerce a g:availability.
	 *
	 * @param string $status Stock status.
	 * @return string
	 */
	function mu_feed_google_availability( $status ) {
		switch ( $status ) {
			case 'outofstock':
				return 'out of stock';
			case 'onbackorder':
				return 'backorder';
			case 'instock':
			default:
				return 'in stock';
		}
	}
}

if ( ! function_exists( 'mu_feed_google_product_type' ) ) {
	/**
	 * Categorías Woo del producto como g:product_type ("A > B").
	 *
	 * @param int $product_id ID del producto.
	 * @return string
	 */
	function mu_feed_google_product_type( $product_id ) {
		$terms = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}

		return implode( ' > ', $terms );
	}
}

if ( ! function_exists( 'mu_feed_google_link' ) ) {
	/**
	 * Enlace del producto en el subdominio del país, con el override manual.
	 *
	 * @param int    $product_id ID del producto.
	 * @param string $country    Código de país.
	 * @param array  $data       Datos del país (muyu_get_countries_data()).
	 * @return string
	 */
	function mu_feed_google_link( $product_id, $country, $data ) {
		$permalink = get_permalink( $product_id );

		if ( ! $permalink ) {
			return '';
		}

		$host = isset( $data['host'] ) ? $data['host'] : ( isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '' );
		$link = preg_replace( '#^https?://[^/]+#', 'https://' . $host, $permalink );
		$sep  = ( false !== strpos( $link, '?' ) ) ? '&' : '?';

		return $link . $sep . 'wcpbc-manual-country=' . rawurlencode( $country );
	}
}

if ( ! function_exists( 'mu_feed_google_translate' ) ) {
	/**
	 * Traduce un texto al idioma del pais usando las traducciones de wpLingua.
	 *
	 * @param string $text Texto original (es).
	 * @param string $lang Idioma destino (es/pt/en).
	 * @return string Texto traducido, o el original si no hay traduccion.
	 */
	function mu_feed_google_translate( $text, $lang ) {
		$text = (string) $text;
		$lang = strtolower( (string) $lang );

		if ( '' === trim( $text ) || 'es' === $lang || ! function_exists( 'wplng_get_translations' ) ) {
			return $text;
		}

		static $map = null;
		if ( null === $map ) {
			$map = array();
			$all = wplng_get_translations();

			if ( is_array( $all ) ) {
				foreach ( $all as $group ) {
					if ( ! is_array( $group ) ) {
						continue;
					}
					foreach ( $group as $entry ) {
						if ( isset( $entry['source'] ) && is_string( $entry['source'] )
							&& ! empty( $entry['translations'] ) && is_array( $entry['translations'] ) ) {
							$map[ $entry['source'] ] = $entry['translations'];
						}
					}
				}
			}
		}

		$needle = function_exists( 'wplng_text_esc' ) ? wplng_text_esc( trim( $text ) ) : trim( $text );

		if ( isset( $map[ $needle ][ $lang ] ) && is_string( $map[ $needle ][ $lang ] ) ) {
			return $map[ $needle ][ $lang ];
		}

		return $text;
	}
}

if ( ! function_exists( 'mu_feed_google_item' ) ) {
	/**
	 * Arma el XML de un item del feed.
	 *
	 * @param int                     $product_id ID del producto.
	 * @param WCPBC_Pricing_Zone|null $zone       Zona WCPBC (o null).
	 * @param string                  $country    Código de país.
	 * @param array                   $data       Datos del país.
	 * @param string                  $currency   Moneda ISO.
	 * @param int                     $decimals   Decimales del precio.
	 * @return string XML del item (vacío si se omite).
	 */
	function mu_feed_google_item( $product_id, $zone, $country, $data, $currency, $decimals ) {
		if ( function_exists( 'mu_feed_exclusion_is_excluded' ) && mu_feed_exclusion_is_excluded( $product_id ) ) {
			return '';
		}

		$product = wc_get_product( $product_id );

		if ( ! $product || ! $product->is_visible() ) {
			return '';
		}

		$lang        = isset( $data['lang'] ) ? (string) $data['lang'] : 'es';
		$title       = mu_feed_google_translate( $product->get_name(), $lang );
		$description = mu_feed_google_translate( $product->get_short_description(), $lang );

		$price = mu_feed_google_price( $zone, $product_id );
		if ( null === $price ) {
			return '';
		}

		$link = mu_feed_google_link( $product_id, $country, $data );
		if ( '' === $link ) {
			return '';
		}

		$image_id = $product->get_image_id();
		$image    = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';

		$brand  = apply_filters( 'mu_feed_google_brand', 'Muy Únicos', $product_id );
		$google = apply_filters( 'mu_feed_google_product_category', '500044', $product_id );

		$item  = "\t<item>\n";
		$item .= "\t\t<g:id>" . esc_xml( $product_id . '-' . $country ) . "</g:id>\n";
		$item .= "\t\t<g:title>" . esc_xml( $title ) . "</g:title>\n";
		$item .= "\t\t<g:description>" . esc_xml( $description ) . "</g:description>\n";
		$item .= "\t\t<g:link>" . esc_xml( $link ) . "</g:link>\n";
		$item .= "\t\t<g:price>" . esc_xml( number_format( $price, $decimals, '.', '' ) . ' ' . $currency ) . "</g:price>\n";
		$item .= "\t\t<g:availability>" . esc_xml( mu_feed_google_availability( $product->get_stock_status() ) ) . "</g:availability>\n";
		$item .= "\t\t<g:condition>new</g:condition>\n";
		$item .= "\t\t<g:brand>" . esc_xml( $brand ) . "</g:brand>\n";

		if ( $image ) {
			$item .= "\t\t<g:image_link>" . esc_xml( $image ) . "</g:image_link>\n";
		}

		if ( '' !== $google ) {
			$item .= "\t\t<g:google_product_category>" . esc_xml( $google ) . "</g:google_product_category>\n";
		}

		$product_type = mu_feed_google_product_type( $product_id );
		if ( '' !== $product_type ) {
			$item .= "\t\t<g:product_type>" . esc_xml( $product_type ) . "</g:product_type>\n";
		}

		$item .= "\t\t<g:identifier_exists>false</g:identifier_exists>\n";
		$item .= "\t</item>\n";

		return $item;
	}
}

if ( ! function_exists( 'mu_feed_google_build' ) ) {
	/**
	 * Construye el XML completo del feed para un país (con caché de 1 h).
	 *
	 * @param string $country Código de país.
	 * @return string XML.
	 */
	function mu_feed_google_build( $country ) {
		$cache_key = 'pmu_feed_' . $country;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_string( $cached ) ) {
			return $cached;
		}

		$countries = muyu_get_countries_data();
		$data      = isset( $countries[ $country ] ) ? $countries[ $country ] : array( 'host' => '', 'name' => $country, 'lang' => 'es' );

		// Evita heredar la zona del visitante; leemos la zona del país explícitamente.
		if ( class_exists( 'WCPBC_Frontend_Pricing' ) && method_exists( 'WCPBC_Frontend_Pricing', 'unset' ) ) {
			WCPBC_Frontend_Pricing::unset();
		}

		$zone     = mu_feed_google_zone( $country );
		$currency = ( $zone && method_exists( $zone, 'get_currency' ) ) ? $zone->get_currency() : get_option( 'woocommerce_currency' );
		$decimals = mu_feed_google_decimals( $country );

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n";
		$xml .= "\t<channel>\n";
		$xml .= "\t\t<title>Muy Únicos - " . esc_xml( $data['name'] ) . "</title>\n";
		$xml .= "\t\t<link>" . esc_xml( 'https://' . $data['host'] ) . "</link>\n";
		$xml .= "\t\t<description>Productos Muy Únicos</description>\n";

		if ( mu_is_woocommerce_active() ) {
			$args = array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			);

			// En subdominios (usuario restringido) el feed solo lleva productos
			// digitales; en AR (raiz) van todos. Guard anti-vacio: si el indice
			// digital no esta armado, no filtrar (mandaria un feed vacio).
			if ( class_exists( 'MUYU_Digital_Restriction_System' )
				&& MUYU_Digital_Restriction_System::get_instance()->is_restricted_user()
				&& function_exists( 'muyu_get_digital_product_ids' ) ) {
				$digital_ids = array_map( 'intval', muyu_get_digital_product_ids() );

				if ( ! empty( $digital_ids ) ) {
					$args['post__in'] = $digital_ids;
				}
			}

			// Fuera de las categorias excluidas del feed (Wii/Outlet/Stickers):
			// se sacan de la query para no iterarlas (cache 1 h).
			if ( function_exists( 'mu_feed_exclusion_term_ids' ) ) {
				$excl = mu_feed_exclusion_term_ids();

				if ( ! empty( $excl ) ) {
					$args['tax_query'] = array(
						array(
							'taxonomy'         => 'product_cat',
							'field'            => 'term_id',
							'terms'            => $excl,
							'operator'         => 'NOT IN',
							'include_children' => false,
						),
					);
				}
			}

			$query = new WP_Query( $args );

			foreach ( $query->posts as $product_id ) {
				$xml .= mu_feed_google_item( $product_id, $zone, $country, $data, $currency, $decimals );
			}
		}

		$xml .= "\t</channel>\n";
		$xml .= '</rss>' . "\n";

		set_transient( $cache_key, $xml, HOUR_IN_SECONDS );

		return $xml;
	}
}

if ( ! function_exists( 'mu_feed_google_render' ) ) {
	/**
	 * Emite el feed y termina la petición.
	 */
	function mu_feed_google_render() {
		$country = mu_feed_google_country();
		$xml     = mu_feed_google_build( $country );

		status_header( 200 );
		header( 'Content-Type: application/xml; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		header( 'Cache-Control: public, max-age=3600' );

		echo $xml;
		exit;
	}
}

if ( ! function_exists( 'mu_feed_google_intercept' ) ) {
	/**
	 * Intercepta /feed-google.xml en template_redirect (p0, antes del canonical).
	 */
	function mu_feed_google_intercept() {
		if ( is_admin() ) {
			return;
		}

		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );

		if ( ! preg_match( '#/feed-google\.xml/?$#i', $path ) ) {
			return;
		}

		mu_feed_google_render();
	}
}
add_action( 'template_redirect', 'mu_feed_google_intercept', 0 );

if ( ! function_exists( 'mu_feed_google_purge_cache' ) ) {
	/**
	 * Borra el cache del feed de todos los paises (todos los subdominios).
	 */
	function mu_feed_google_purge_cache() {
		foreach ( muyu_get_countries_data() as $code => $data ) {
			delete_transient( 'pmu_feed_' . $code );
		}
	}
}

if ( ! function_exists( 'mu_feed_google_flush_cache' ) ) {
	/**
	 * Invalida la caché del feed al guardar/actualizar un producto.
	 *
	 * @param int $post_id ID del post.
	 */
	function mu_feed_google_flush_cache( $post_id ) {
		if ( 'product' !== get_post_type( $post_id ) ) {
			return;
		}

		mu_feed_google_purge_cache();
	}
}
add_action( 'save_post_product', 'mu_feed_google_flush_cache' );
add_action( 'woocommerce_update_product', 'mu_feed_google_flush_cache' );