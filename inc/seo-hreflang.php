<?php
/**
 * Muy Únicos - SEO Multi-País (Hreflang y Localización)
 * 
 * Genera etiquetas hreflang para todos los subdominios
 * y localiza títulos/meta descriptions por país.
 * 
 * @package GeneratePress_Child
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================
// HREFLANG TAGS GENERATION
// ============================================

if ( ! function_exists( 'mu_add_hreflang_tags' ) ) {
    /**
     * Genera y agrega etiquetas hreflang al head
     * para todos los subdominios configurados.
     */
    function mu_add_hreflang_tags() {
        // Solo ejecutar en el frontend
        if ( is_admin() ) return;

        // Rutas con alcance por país (inc/country-scope.php): el conjunto de
        // alternates se reduce al host actual. Declarar los 9 subdominios para una
        // ruta que sólo existe en Argentina era un hreflang asimétrico: esos hosts
        // no sirven la página (302 a la tienda), así que los alternates apuntaban a
        // redirects (constitución, principio V). Medido el 2026-10-16: /envios/
        // emitía 11 alternates más un x-default al dominio raíz.
        $scope_tags = function_exists( 'muyu_get_country_scope_hreflang_tags' )
            ? muyu_get_country_scope_hreflang_tags()
            : [];

        if ( ! empty( $scope_tags ) ) {
            foreach ( $scope_tags as $scope_tag ) {
                printf(
                    '<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
                    esc_attr( $scope_tag['hreflang'] ),
                    esc_url( $scope_tag['url'] )
                );
            }
            return;
        }
        
        // Obtener datos de países
        $countries = muyu_get_countries_data();
        if ( empty( $countries ) ) return;
        
        // Obtener URL actual
        $current_url = home_url( $_SERVER['REQUEST_URI'] );
        $current_host = parse_url( $current_url, PHP_URL_HOST );
        
        // Obtener país actual
        $current_country = muyu_get_current_country_from_subdomain();
        
        // Generar hreflang tags para cada país
        $hreflang_tags = [];
        
        foreach ( $countries as $country_code => $country_data ) {
            // AR es el x-default (raíz): se emite una sola vez como x-default, no como es-AR.
            if ( 'AR' === $country_code ) {
                continue;
            }

            $country_host = $country_data['host'];
            $country_lang = $country_data['lang'];
            
            // Construir URL para este país
            $country_url = str_replace( $current_host, $country_host, $current_url );
            
            // Agregar prefijo de idioma si es necesario, preservando host y query.
            // (El str_replace sobre la URL completa corrompía la home: '/' → https:/pt//pt/...)
            $prefix = muyu_country_language_prefix( $country_code );
            if ( $prefix ) {
                $parts = parse_url( $country_url );
                $path  = isset( $parts['path'] ) && '' !== $parts['path'] ? (string) $parts['path'] : '/';
                if ( 0 !== strpos( $path, $prefix . '/' ) && $path !== $prefix ) {
                    $nuevo = ( ! empty( $parts['scheme'] ) ? $parts['scheme'] : 'https' ) . '://' . $parts['host'];
                    if ( ! empty( $parts['port'] ) ) {
                        $nuevo .= ':' . (int) $parts['port'];
                    }
                    $nuevo .= $prefix . $path;
                    if ( ! empty( $parts['query'] ) ) {
                        $nuevo .= '?' . $parts['query'];
                    }
                    if ( ! empty( $parts['fragment'] ) ) {
                        $nuevo .= '#' . $parts['fragment'];
                    }
                    $country_url = $nuevo;
                }
            }
            
            // Código hreflang por región: idioma (ISO 639-1) + país (ISO 3166-1 alpha-2),
            // p. ej. es-MX, es-CO, es-ES, es-CL, es-PE, es-EC, es-CR, pt-BR, en-US.
            // Debe ser ÚNICO por URL: el 'es' genérico repetido era inválido.
            $hreflang_code = $country_lang . '-' . $country_code;
            
            $hreflang_tags[] = [
                'url' => $country_url,
                'hreflang' => $hreflang_code,
            ];
        }
        
        // Agregar x-default apuntando a la versión principal (AR)
        $main_country = 'AR';
        if ( isset( $countries[ $main_country ] ) ) {
            $main_host = $countries[ $main_country ]['host'];
            $default_url = str_replace( $current_host, $main_host, $current_url );
            $hreflang_tags[] = [
                'url' => $default_url,
                'hreflang' => 'x-default',
            ];
        }
        
        // Output hreflang tags
        foreach ( $hreflang_tags as $tag ) {
            printf(
                '<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
                esc_attr( $tag['hreflang'] ),
                esc_url( $tag['url'] )
            );
        }
    }
    add_action( 'wp_head', 'mu_add_hreflang_tags', 5 );
}

// ============================================
// LOCALIZED TITLES AND META DESCRIPTIONS
// ============================================

if ( ! function_exists( 'mu_localize_document_title' ) ) {
    /**
     * Localiza el título del documento según el país
     */
    function mu_localize_document_title( $title ) {
        if ( is_admin() ) return $title;
        
        $current_country = muyu_get_current_country_from_subdomain();
        $countries = muyu_get_countries_data();
        
        if ( ! isset( $countries[ $current_country ] ) ) return $title;
        
        $country_name = $countries[ $current_country ]['name'];
        
        // Agregar nombre del país al título si no está presente
        if ( strpos( $title, $country_name ) === false ) {
            $title = $title . ' | ' . $country_name;
        }
        
        return $title;
    }
    add_filter( 'pre_get_document_title', 'mu_localize_document_title', 10 );
    add_filter( 'wp_title', 'mu_localize_document_title', 10 );
}

// ============================================
// TITULO DE HOME POR PAIS (general no-AR)
// ============================================

if ( ! function_exists( 'mu_home_title_general' ) ) {
    /**
     * Descriptor general del titulo de la home para paises no-AR.
     * AR conserva su titulo especifico (Rank Math -> Titulos y metadatos).
     *
     * @return string
     */
    function mu_home_title_general() {
        $lang = function_exists( 'muyu_get_current_language' ) ? muyu_get_current_language() : 'es';
        $map  = [
            'en' => 'Custom Printed Products',
            'pt' => 'Personalizados para imprimir',
            'es' => 'Personalizados para imprimir',
        ];
        $base = isset( $map[ $lang ] ) ? $map[ $lang ] : $map['es'];

        return (string) apply_filters( 'mu_home_title_general', $base, $lang );
    }
}

if ( ! function_exists( 'mu_home_title_pais' ) ) {
    /**
     * Titulo de la home para paises no-AR: general + pais.
     * Se engancha en `pre_get_document_title` con prioridad muy alta para
     * imponerse sobre el titulo que arma Rank Math. AR queda intacto.
     *
     * @param string $title Titulo previo.
     * @return string
     */
    function mu_home_title_pais( $title ) {
        if ( is_admin() || ! is_front_page() ) {
            return $title;
        }

        $country = function_exists( 'muyu_get_current_country_from_subdomain' )
            ? muyu_get_current_country_from_subdomain()
            : 'AR';

        if ( 'AR' === $country ) {
            return $title;
        }

        $countries = function_exists( 'muyu_get_countries_data' ) ? muyu_get_countries_data() : [];
        $name      = isset( $countries[ $country ]['name'] ) ? $countries[ $country ]['name'] : '';
        $site      = get_bloginfo( 'name' );

        if ( '' === $site ) {
            $site = 'Muy Únicos';
        }

        $out = $site . ' — ' . mu_home_title_general();

        if ( '' !== $name ) {
            $out .= ' | ' . $name;
        }

        return $out;
    }
    add_filter( 'pre_get_document_title', 'mu_home_title_pais', 99999 );
    // Solo para og:title/twitter:title de Rank Math (si el filtro existe):
    // mantiene consistencia con el <title>. Inofensivo si Rank Math no lo usa.
    add_filter( 'rank_math/frontend/title', 'mu_home_title_pais', 99999 );
}

if ( ! function_exists( 'mu_localize_meta_description' ) ) {
    /**
     * Localiza la meta description según el país
     */
    function mu_localize_meta_description( $description ) {
        if ( is_admin() || empty( $description ) ) return $description;
        
        $current_country = muyu_get_current_country_from_subdomain();
        $countries = muyu_get_countries_data();
        
        if ( ! isset( $countries[ $current_country ] ) ) return $description;
        
        $country_name = $countries[ $current_country ]['name'];
        
        // Agregar localización a la descripción
        $localized_suffix = ' Compra online en ' . $country_name;
        
        if ( strpos( $description, $country_name ) === false ) {
            $description = rtrim( $description, '. ' ) . $localized_suffix . '.';
        }
        
        return $description;
    }
    add_filter( 'the_seo_framework_description', 'mu_localize_meta_description', 10 );
    add_filter( 'wpseo_metadesc', 'mu_localize_meta_description', 10 );
    add_filter( 'rank_math_description', 'mu_localize_meta_description', 10 );
}

// ============================================
// CANONICAL URLS BY COUNTRY
// ============================================

if ( ! function_exists( 'mu_fix_canonical_url' ) ) {
    /**
     * Asegura que las URLs canónicas sean específicas del país
     */
    function mu_fix_canonical_url( $canonical_url ) {
        if ( is_admin() ) return $canonical_url;
        
        $current_host = parse_url( $canonical_url, PHP_URL_HOST );
        $expected_host = $_SERVER['HTTP_HOST'] ?? '';
        
        // Si el host canonical no coincide con el actual, corregirlo
        if ( $current_host !== $expected_host ) {
            $canonical_url = str_replace( $current_host, $expected_host, $canonical_url );
        }
        
        return $canonical_url;
    }
    add_filter( 'get_canonical_url', 'mu_fix_canonical_url', 10 );
    add_filter( 'the_seo_framework_rel_canonical_output', 'mu_fix_canonical_url', 10 );
    add_filter( 'wpseo_canonical', 'mu_fix_canonical_url', 10 );
    add_filter( 'rank_math_canonical', 'mu_fix_canonical_url', 10 );
}
