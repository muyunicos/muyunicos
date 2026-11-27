<?php
/**
 * Muy Únicos - Schema base (Organization + LocalBusiness + WebSite)
 *
 * Añade al grafo JSON-LD de Rank Math (`rank_math/json_ld`) los nodos base que
 * necesita el SEO local, SIN duplicar lo que Rank Math ya emita:
 * - Si no hay `Organization`/`LocalBusiness`, agrega uno con el NAP real.
 * - Si ya existe, le FUSIONA los campos faltantes (address, email, teléfono,
 *   areaServed, logo, sameAs) y le agrega el tipo `LocalBusiness`.
 * - Si no hay `WebSite`, agrega uno con `SearchAction`.
 *
 * El módulo de AggregateRating (`schema-rating.php`, prioridad 20) enriquece
 * después el Organization resultante. Todo es opcional y filtrable.
 *
 * Depende de: Rank Math (filtro `rank_math/json_ld`) y geo.php (países).
 * functions.php lo carga ANTES de schema-rating.
 *
 * @package GeneratePress_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'mu_schema_org_fields' ) ) {
    /**
     * Campos del NAP real (solo lo que se conoce; logo/sameAs/horario son opcionales).
     *
     * @return array<string,mixed>
     */
    function mu_schema_org_fields() {
        $countries = function_exists( 'muyu_get_countries_data' ) ? muyu_get_countries_data() : [];
        $area      = [];

        foreach ( $countries as $code => $data ) {
            if ( ! empty( $data['name'] ) ) {
                $area[] = $data['name'];
            }
        }

        $fields = [
            'address' => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => 'Catamarca 2255, Piso 10, Dto F',
                'addressLocality' => 'Mar del Plata',
                'addressRegion'   => 'Buenos Aires',
                'postalCode'      => '7600',
                'addressCountry'  => 'AR',
            ],
            'email'     => 'muyunicos@gmail.com',
            'telephone' => '+5492235331311',
        ];

        if ( ! empty( $area ) ) {
            $fields['areaServed'] = array_values( $area );
        }

        $logo_id = function_exists( 'get_theme_mod' ) ? get_theme_mod( 'custom_logo' ) : 0;

        if ( $logo_id && function_exists( 'wp_get_attachment_image_url' ) ) {
            $logo = wp_get_attachment_image_url( (int) $logo_id, 'full' );

            if ( $logo ) {
                $fields['logo'] = $logo;
            }
        }

        $same_as = (array) apply_filters( 'mu_schema_org_sameas', [] );

        if ( ! empty( $same_as ) ) {
            $fields['sameAs'] = array_values( $same_as );
        }

        $hours = (string) apply_filters( 'mu_schema_org_hours', '' );

        if ( '' !== $hours ) {
            $fields['openingHours'] = $hours;
        }

        return apply_filters( 'mu_schema_org_fields', $fields );
    }
}

if ( ! function_exists( 'mu_schema_org_data' ) ) {
    /**
     * Nodo Organization/LocalBusiness completo (cuando Rank Math no emite ninguno).
     *
     * @return array<string,mixed>
     */
    function mu_schema_org_data() {
        $home = home_url( '/' );
        $name = get_bloginfo( 'name' );

        if ( '' === $name ) {
            $name = 'Muy Únicos';
        }

        $node = array_merge(
            [
                '@type' => [ 'Organization', 'LocalBusiness' ],
                '@id'   => $home . '#organization',
                'name'  => $name,
                'url'   => $home,
            ],
            mu_schema_org_fields()
        );

        return apply_filters( 'mu_schema_org_data', $node );
    }
}

if ( ! function_exists( 'mu_schema_org_merge' ) ) {
    /**
     * Fusiona el NAP en un Organization existente sin pisar lo que ya tenga.
     *
     * @param array $schema Nodo existente.
     * @return array
     */
    function mu_schema_org_merge( $schema ) {
        $types = (array) ( $schema['@type'] ?? [ 'Organization' ] );

        if ( ! in_array( 'Organization', $types, true ) ) {
            $types[] = 'Organization';
        }

        if ( ! in_array( 'LocalBusiness', $types, true ) ) {
            $types[] = 'LocalBusiness';
        }

        $schema['@type'] = $types;

        foreach ( mu_schema_org_fields() as $key => $value ) {
            if ( empty( $schema[ $key ] ) ) {
                $schema[ $key ] = $value;
            }
        }

        return $schema;
    }
}

if ( ! function_exists( 'mu_schema_web_data' ) ) {
    /**
     * Nodo WebSite con SearchAction (cuando Rank Math no emite ninguno).
     *
     * @return array<string,mixed>
     */
    function mu_schema_web_data() {
        $home = home_url( '/' );
        $name = get_bloginfo( 'name' );

        if ( '' === $name ) {
            $name = 'Muy Únicos';
        }

        $node = [
            '@type'           => 'WebSite',
            '@id'             => $home . '#website',
            'name'            => $name,
            'url'             => $home,
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => [
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => $home . '?s={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];

        return apply_filters( 'mu_schema_web_data', $node );
    }
}

if ( ! function_exists( 'mu_schema_org_inject' ) ) {
    /**
     * Añade/fusiona Organization/LocalBusiness y WebSite en el grafo de Rank Math.
     *
     * @param array  $data     Grafo JSON-LD.
     * @param string $position Posición que reporta Rank Math.
     * @return array
     */
    function mu_schema_org_inject( $data, $position = '' ) {
        if ( ! is_array( $data ) || empty( $data ) ) {
            return $data;
        }

        $has_org = false;
        $has_web = false;

        foreach ( $data as $key => $schema ) {
            if ( ! is_array( $schema ) ) {
                continue;
            }

            $types = (array) ( $schema['@type'] ?? [] );

            if ( in_array( 'Organization', $types, true ) || in_array( 'LocalBusiness', $types, true ) ) {
                $data[ $key ] = mu_schema_org_merge( $schema );
                $has_org      = true;
            }

            if ( in_array( 'WebSite', $types, true ) ) {
                $has_web = true;
            }
        }

        if ( ! $has_org ) {
            $data[] = mu_schema_org_data();
        }

        if ( ! $has_web ) {
            $data[] = mu_schema_web_data();
        }

        return $data;
    }
    add_filter( 'rank_math/json_ld', 'mu_schema_org_inject', 10, 2 );
}