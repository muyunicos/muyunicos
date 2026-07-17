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
            $country_host = $country_data['host'];
            $country_lang = $country_data['lang'];
            
            // Construir URL para este país
            $country_url = str_replace( $current_host, $country_host, $current_url );
            
            // Agregar prefijo de idioma si es necesario
            $prefix = muyu_country_language_prefix( $country_code );
            if ( $prefix && strpos( $country_url, $prefix ) === false ) {
                $path = parse_url( $country_url, PHP_URL_PATH );
                $country_url = str_replace( $path, $prefix . $path, $country_url );
            }
            
            // Generar código de idioma-país para hreflang
            $hreflang_code = $country_lang . '-' . strtolower( $country_code );
            
            // Para español genérico, usar solo 'es'
            if ( $country_lang === 'es' ) {
                $hreflang_code = 'es';
            }
            
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
