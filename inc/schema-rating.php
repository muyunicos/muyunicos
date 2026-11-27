<?php
/**
 * Muy Únicos - AggregateRating / Review (schema.org)
 *
 * Inyecta `AggregateRating` (Trustindex 4.9/5, 108 reseñas) y `Review`
 * (reseñas reales y visibles del set `mu_reviews_master_db`) en los nodos
 * `Organization`/`LocalBusiness` y `Product` que ya emite Rank Math.
 *
 * Decisiones:
 * - NO emite un `Organization` propio: se engancha al grafo JSON-LD de Rank
 *   Math (`rank_math/json_ld`) y solo ENRIQUECE nodos existentes. Así nunca
 *   hay duplicado de Organization (edge case del plan).
 * - Si Rank Math no está activo, el módulo es un no-op (degradación limpia).
 * - Los valores 4.9/108 son los del sello Trustindex visible en el footer;
 *   se pueden sobreescribir con el filtro `mu_schema_rating_data`.
 * - El array `review` sale de reseñas REALES (no fabricadas): cumple la
 *   política de Google. Se puede desactivar la inyección en Product con el
 *   filtro `mu_schema_rating_product` (por defecto true, según el plan).
 *
 * Depende de: Rank Math (filtro `rank_math/json_ld`) y la opción
 * `mu_reviews_master_db` (reseñas reales). functions.php lo carga tras
 * seo-hreflang.
 *
 * @package GeneratePress_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'mu_schema_rating_data' ) ) {
    /**
     * Valores del AggregateRating (fuente: sello Trustindex visible).
     *
     * @return array{ratingValue:float,reviewCount:int,bestRating:int,worstRating:int}
     */
    function mu_schema_rating_data() {
        return apply_filters( 'mu_schema_rating_data', [
            'ratingValue' => 4.9,
            'reviewCount' => 108,
            'bestRating'  => 5,
            'worstRating' => 1,
        ] );
    }
}

if ( ! function_exists( 'mu_schema_rating_reviews' ) ) {
    /**
     * Reseñas reales (no ocultas) del set maestro, mapeadas a `Review`.
     * Se limitan para no inflar el JSON-LD; el AggregateRating es el total.
     *
     * @param int $limit Tope de reseñas a emitir.
     * @return array<int,array<string,mixed>>
     */
    function mu_schema_rating_reviews( $limit = 10 ) {
        $db = get_option( 'mu_reviews_master_db', [] );

        if ( ! is_array( $db ) || empty( $db ) ) {
            return [];
        }

        $reviews = [];

        foreach ( $db as $row ) {
            if ( count( $reviews ) >= $limit ) {
                break;
            }

            if ( ! is_array( $row ) || ! empty( $row['hidden'] ) ) {
                continue;
            }

            $author = trim( (string) ( $row['author_name'] ?? '' ) );
            $text   = trim( (string) ( $row['text'] ?? '' ) );

            if ( '' === $author || '' === $text ) {
                continue;
            }

            $text = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $text ) : strip_tags( $text );

            $rating = (int) ( $row['rating'] ?? 5 );
            if ( $rating < 1 || $rating > 5 ) {
                $rating = 5;
            }

            $review = [
                '@type'        => 'Review',
                'author'       => [ '@type' => 'Person', 'name' => $author ],
                'reviewRating' => [
                    '@type'       => 'Rating',
                    'ratingValue' => $rating,
                    'bestRating'  => 5,
                    'worstRating' => 1,
                ],
                'reviewBody'   => $text,
            ];

            if ( ! empty( $row['time'] ) ) {
                $review['datePublished'] = gmdate( 'c', (int) $row['time'] );
            }

            $reviews[] = $review;
        }

        return $reviews;
    }
}

if ( ! function_exists( 'mu_schema_rating_inject' ) ) {
    /**
     * Enriquece el grafo JSON-LD de Rank Math con AggregateRating/Review.
     * Solo toca nodos Organization/LocalBusiness y Product existentes.
     *
     * @param array  $data     Grafo JSON-LD de Rank Math.
     * @param string $position Posición que reporta Rank Math.
     * @return array
     */
    function mu_schema_rating_inject( $data, $position = '' ) {
        if ( ! is_array( $data ) || empty( $data ) ) {
            return $data;
        }

        $values = mu_schema_rating_data();

        $aggregate = [
            '@type'       => 'AggregateRating',
            'ratingValue' => (string) $values['ratingValue'],
            'reviewCount' => (int) $values['reviewCount'],
            'bestRating'  => (string) $values['bestRating'],
            'worstRating' => (string) $values['worstRating'],
        ];

        $reviews         = mu_schema_rating_reviews();
        $inject_product  = (bool) apply_filters( 'mu_schema_rating_product', true );

        foreach ( $data as $key => $schema ) {
            if ( ! is_array( $schema ) ) {
                continue;
            }

            $types    = (array) ( $schema['@type'] ?? [] );
            $is_org   = in_array( 'Organization', $types, true ) || in_array( 'LocalBusiness', $types, true );
            $is_prod  = $inject_product && in_array( 'Product', $types, true );

            if ( ! $is_org && ! $is_prod ) {
                continue;
            }

            $data[ $key ]['aggregateRating'] = $aggregate;

            if ( $is_org && ! empty( $reviews ) && empty( $schema['review'] ) ) {
                $data[ $key ]['review'] = $reviews;
            }
        }

        return $data;
    }
    add_filter( 'rank_math/json_ld', 'mu_schema_rating_inject', 20, 2 );
}