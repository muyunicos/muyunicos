<?php
/**
 * Muy Únicos - Digital Restriction System
 * * Sistema de restricción de contenido digital v3.1.1 (Hotfix: Empty Index & Redirect Loop)
 * Propósito: Restringir productos físicos en subdominios, mostrando solo 
 * productos digitales. Optimizado para rendimiento y compatibilidad.
 * * @package GeneratePress_Child
 * @since 3.1.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Constantes de entorno con fallback seguro
defined( 'MUYU_PHYSICAL_FORMAT_ID' ) || define( 'MUYU_PHYSICAL_FORMAT_ID', 112 );
defined( 'MUYU_DIGITAL_FORMAT_ID' )  || define( 'MUYU_DIGITAL_FORMAT_ID', 111 );

if ( ! class_exists( 'MUYU_Digital_Restriction_System' ) ) {

    class MUYU_Digital_Restriction_System {
        
        private static ?self $instance = null;
        
        const OPTION_PRODUCT_IDS  = 'muyu_digital_product_ids';
        const OPTION_CATEGORY_IDS = 'muyu_digital_category_ids';
        const OPTION_TAG_IDS      = 'muyu_digital_tag_ids';
        const OPTION_REDIRECT_MAP = 'muyu_phys_to_dig_map';
        const OPTION_LAST_UPDATE  = 'muyu_digital_list_updated';
        const TRANSIENT_REBUILD   = 'muyu_rebuild_scheduled';
        
        public static function get_instance(): self {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        
        private function __construct() {
            $this->init_hooks();
        }
        
        private function init_hooks(): void {
            // ---- Gestión de índices (Admin) ----
            add_action( 'wc_ajax_mu_rebuild_digital_list', [ $this, 'ajax_rebuild_indexes' ] );
            add_action( 'woocommerce_update_product', [ $this, 'schedule_rebuild' ] );
            add_action( 'admin_init', [ $this, 'ensure_indexes_exist' ], 5 );
            add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
            add_action( 'shutdown', [ $this, 'execute_scheduled_rebuild' ] );
            
            // ---- Filtrado de contenido (Frontend) ----
            add_action( 'pre_get_posts', [ $this, 'filter_product_queries' ], 50 );
            add_action( 'template_redirect', [ $this, 'handle_redirects' ], 20 );
            add_filter( 'get_terms_args', [ $this, 'filter_category_terms' ], 10, 2 );
            add_filter( 'wp_get_nav_menu_items', [ $this, 'filter_menu_items' ], 10, 3 );
            
            // ---- Variaciones y Precios ----
            add_filter( 'woocommerce_variation_is_visible', [ $this, 'hide_physical_variation' ], 10, 4 );
            add_filter( 'woocommerce_dropdown_variation_attribute_options_args', [ $this, 'clean_variation_dropdown' ], 10, 1 );
            add_filter( 'woocommerce_available_variation', [ $this, 'filter_available_variation_data' ], 10, 3 );
            add_filter( 'woocommerce_product_get_default_attributes', [ $this, 'set_format_default' ], 20, 2 );
            add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'autoselect_format_variation_bridge' ], 5 );
            
            // FIX PRECIOS: Prioridad altísima para sobreescribir Price Based on Country y Cache Busting
            add_filter( 'woocommerce_variation_prices', [ $this, 'filter_variation_prices' ], 999, 3 );
            add_filter( 'woocommerce_get_variation_prices_hash', [ $this, 'price_hash_cache_bust' ], 10, 1 );
        }
        
        // =====================================================================
        // HELPERS
        // =====================================================================
        
        private function extract_format( array $attributes ): string {
            return $attributes['pa_formato'] ?? $attributes['attribute_pa_formato'] ?? $attributes['formato'] ?? $attributes['attribute_formato'] ?? '';
        }

        public function is_restricted_user(): bool {
            $host = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) );
            $host = preg_replace( '/:\d+$/', '', $host );
            return 'muyunicos.com' !== str_replace( 'www.', '', $host );
        }

        // Cache bust para aislar los precios de Price Based on Country según el dominio
        public function price_hash_cache_bust( $hash ) {
            return $hash . ( $this->is_restricted_user() ? '_dig' : '_full' );
        }

        // =====================================================================
        // GESTIÓN DE ÍNDICES (Background)
        // =====================================================================
        
        public function rebuild_digital_indexes(): int {
            $digital_product_ids = $this->get_digital_product_ids();
            
            if ( empty( $digital_product_ids ) ) {
                $this->save_indexes( [], [], [], [] );
                return 0;
            }
            
            list( $category_ids, $tag_ids ) = $this->get_product_terms( $digital_product_ids );
            $category_ids = $this->expand_category_hierarchy( $category_ids );
            $redirect_map = $this->build_redirect_map( $digital_product_ids );
            
            $this->save_indexes( $digital_product_ids, $category_ids, $tag_ids, $redirect_map );
            return count( $digital_product_ids );
        }
        
        private function get_digital_product_ids(): array {
            global $wpdb;
            $sql = "
                SELECT DISTINCT p.ID as product_id FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'product' AND p.post_status = 'publish' AND pm.meta_key IN ('_virtual', '_downloadable') AND pm.meta_value = 'yes'
                UNION
                SELECT DISTINCT p.post_parent as product_id FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'product_variation' AND p.post_status = 'publish' AND pm.meta_key IN ('_virtual', '_downloadable') AND pm.meta_value = 'yes' AND p.post_parent > 0
            ";
            return array_filter( array_unique( array_map( 'intval', $wpdb->get_col( $sql ) ) ) );
        }
        
        private function get_product_terms( array $product_ids ): array {
            global $wpdb;
            $ids_string = implode( ',', array_map( 'intval', $product_ids ) );
            $sql = "
                SELECT DISTINCT t.term_id, tt.taxonomy FROM {$wpdb->terms} t
                INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                WHERE tr.object_id IN ($ids_string) AND tt.taxonomy IN ('product_cat', 'product_tag')
            ";
            
            $category_ids = []; $tag_ids = [];
            foreach ( $wpdb->get_results( $sql ) as $term ) {
                if ( 'product_cat' === $term->taxonomy ) $category_ids[] = (int) $term->term_id;
                else $tag_ids[] = (int) $term->term_id;
            }
            return [ array_unique( $category_ids ), array_unique( $tag_ids ) ];
        }
        
        private function expand_category_hierarchy( array $category_ids ): array {
            $expanded = $category_ids;
            foreach ( $category_ids as $cat_id ) {
                array_push( $expanded, ...get_ancestors( $cat_id, 'product_cat', 'taxonomy' ) );
            }
            return array_unique( array_map( 'intval', $expanded ) );
        }
        
        private function build_redirect_map( array $digital_product_ids ): array {
            global $wpdb;
            $ids_string = implode( ',', array_map( 'intval', $digital_product_ids ) );
            $digital_products = $wpdb->get_results( "SELECT ID, post_name FROM {$wpdb->posts} WHERE ID IN ($ids_string) AND post_name LIKE '%-imprimible%'" );
            
            if ( empty( $digital_products ) ) return [];

            $base_slugs_map = [];
            foreach ( $digital_products as $product ) {
                $base_slugs_map[ str_replace( '-imprimible', '', $product->post_name ) ] = (int) $product->ID;
            }

            $slugs_in = "'" . implode( "','", array_map( 'esc_sql', array_keys( $base_slugs_map ) ) ) . "'";
            $physical_products = $wpdb->get_results( "SELECT ID, post_name FROM {$wpdb->posts} WHERE post_name IN ($slugs_in) AND post_type = 'product' AND post_status = 'publish'" );

            $redirect_map = [];
            foreach ( $physical_products as $phys ) {
                if ( ! in_array( (int) $phys->ID, $digital_product_ids, true ) ) {
                    $redirect_map[ (int) $phys->ID ] = $base_slugs_map[ $phys->post_name ];
                }
            }
            return $redirect_map;
        }
        
        private function save_indexes( array $product_ids, array $category_ids, array $tag_ids, array $redirect_map ): void {
            update_option( self::OPTION_PRODUCT_IDS, $product_ids, false );
            update_option( self::OPTION_CATEGORY_IDS, $category_ids, false );
            update_option( self::OPTION_TAG_IDS, $tag_ids, false );
            update_option( self::OPTION_REDIRECT_MAP, $redirect_map, false );
            update_option( self::OPTION_LAST_UPDATE, current_time( 'mysql' ), false );
            delete_transient( self::TRANSIENT_REBUILD );
        }

        // =====================================================================
        // HANDLERS EVENTOS & REDIRECCIONES
        // =====================================================================
        
        public function ajax_rebuild_indexes(): void {
            check_ajax_referer( 'muyu-rebuild-nonce', 'nonce' );
            if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'Permisos insuficientes' );
            wp_send_json_success( sprintf( 'Índice reconstruido correctamente. Total productos digitales: %d', $this->rebuild_digital_indexes() ) );
        }
        
        public function schedule_rebuild(): void {
            if ( ! get_transient( self::TRANSIENT_REBUILD ) ) set_transient( self::TRANSIENT_REBUILD, true, 120 );
        }
        
        public function execute_scheduled_rebuild(): void {
            if ( get_transient( self::TRANSIENT_REBUILD ) ) $this->rebuild_digital_indexes();
        }
        
        public function ensure_indexes_exist(): void {
            // FIX: Reconstruir si no existe O si está vacío (prevención de corrupción)
            $ids = get_option( self::OPTION_PRODUCT_IDS, false );
            if ( false === $ids || empty( $ids ) ) {
                $this->rebuild_digital_indexes();
            }
        }
        
        public function enqueue_admin_assets( string $hook ): void {
            if ( 'edit.php' !== $hook ) return;
            $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
            if ( ( $screen?->id !== 'edit-product' ) && ( ($_GET['post_type'] ?? '') !== 'product' ) ) return;

            $theme_uri = get_stylesheet_directory_uri();
            $ver       = wp_get_theme()->get( 'Version' );
            wp_enqueue_style( 'mu-admin', $theme_uri . '/css/admin.css', [], $ver );
            wp_enqueue_script( 'mu-admin-js', $theme_uri . '/js/admin.js', [], $ver, true );
            wp_localize_script( 'mu-admin-js', 'muyuAdminData', [
                'nonce'       => wp_create_nonce( 'muyu-rebuild-nonce' ),
                'label'       => '⚡ Reindexar Digitales',
                'wc_ajax_url' => \WC_AJAX::get_endpoint( 'mu_rebuild_digital_list' )
            ] );
        }

        public function filter_product_queries( $query ): void {
            if ( is_admin() || ! $query->is_main_query() ) return;
            if ( $query->is_product() || ( $query->is_singular() && 'product' === $query->get( 'post_type' ) ) ) return;
            
            $is_shop_query = ( ( function_exists( 'is_shop' ) && is_shop() ) || ( function_exists( 'is_product_category' ) && is_product_category() ) || ( function_exists( 'is_product_tag' ) && is_product_tag() ) || is_search() || 'product' === $query->get( 'post_type' ) );
            if ( ! $is_shop_query || ! $this->is_restricted_user() ) return;
            
            $digital_ids = get_option( self::OPTION_PRODUCT_IDS, false );
            if ( false === $digital_ids ) {
                $this->rebuild_digital_indexes();
                $digital_ids = get_option( self::OPTION_PRODUCT_IDS, [] );
            }
            $query->set( 'post__in', empty( $digital_ids ) ? [ 0 ] : array_map( 'intval', (array) $digital_ids ) );
        }

        public function filter_category_terms( array $args, array $taxonomies ): array {
            if ( is_admin() || wp_is_json_request() ) return $args;
            if ( ! in_array( 'product_cat', $taxonomies, true ) || ! $this->is_restricted_user() ) return $args;
            
            $digital_cat_ids = array_map( 'intval', (array) get_option( self::OPTION_CATEGORY_IDS, [] ) );
            if ( ! empty( $args['include'] ) ) {
                $current = array_map( 'intval', is_array( $args['include'] ) ? $args['include'] : explode( ',', $args['include'] ) );
                $args['include'] = array_intersect( $current, $digital_cat_ids ) ?: [ 0 ];
            } else {
                $args['include'] = empty( $digital_cat_ids ) ? [ 0 ] : $digital_cat_ids;
            }
            return $args;
        }
        
        public function filter_menu_items( array $items, $menu, array $args ): array {
            if ( is_admin() || wp_is_json_request() || ! $this->is_restricted_user() ) return $items;
            $digital_cat_ids = array_map( 'intval', (array) get_option( self::OPTION_CATEGORY_IDS, [] ) );
            return array_filter( $items, fn($item) => ! (isset($item->object) && 'product_cat' === $item->object) || in_array((int)$item->object_id, $digital_cat_ids, true) );
        }

        public function handle_redirects(): void {
            if ( is_admin() || ! $this->is_restricted_user() ) return;
            if ( ! is_product() && ! is_product_category() && ! is_product_tag() ) return;
            
            $target_url = ''; $should_redirect = false;
            
            if ( is_product_category() ) list( $should_redirect, $target_url ) = $this->handle_category_redirect();
            elseif ( is_product_tag() ) list( $should_redirect, $target_url ) = $this->handle_tag_redirect();
            elseif ( is_product() ) list( $should_redirect, $target_url ) = $this->handle_product_redirect();
            
            if ( $should_redirect ) $this->execute_redirect( $target_url );
        }
        
        private function handle_category_redirect(): array {
            $queried_object = get_queried_object();
            $digital_cats = array_map( 'intval', (array) get_option( self::OPTION_CATEGORY_IDS, [] ) );
            if ( ! $queried_object || in_array( (int) $queried_object->term_id, $digital_cats, true ) ) return [ false, '' ];
            
            $parent_id = $queried_object->parent;
            while ( $parent_id ) {
                if ( in_array( (int) $parent_id, $digital_cats, true ) ) return [ true, get_term_link( $parent_id, 'product_cat' ) ];
                $term = get_term( $parent_id, 'product_cat' );
                $parent_id = ( $term && ! is_wp_error( $term ) ) ? $term->parent : 0;
            }
            return [ true, '' ];
        }
        
        private function handle_tag_redirect(): array {
            $queried_object = get_queried_object();
            $digital_tags = array_map( 'intval', (array) get_option( self::OPTION_TAG_IDS, [] ) );
            return [ ( $queried_object && ! in_array( (int) $queried_object->term_id, $digital_tags, true ) ), '' ];
        }
        
        private function handle_product_redirect(): array {
            global $post;
            
            // FIX: Recuperar índice. Si está vacío o no existe, reconstruir forzosamente.
            $digital_ids = (array) get_option( self::OPTION_PRODUCT_IDS, [] );
            if ( empty( $digital_ids ) ) {
                $this->rebuild_digital_indexes();
                $digital_ids = (array) get_option( self::OPTION_PRODUCT_IDS, [] );
            }
            
            // Si el producto actual (Variable o Simple) ya está en la lista permitida, NO redirigir.
            if ( ! $post || in_array( (int) $post->ID, array_map( 'intval', $digital_ids ), true ) ) {
                return [ false, '' ];
            }
            
            $redirect_map = get_option( self::OPTION_REDIRECT_MAP, [] );
            if ( isset( $redirect_map[ $post->ID ] ) ) {
                // Validar destino
                $target_id = $redirect_map[ $post->ID ];
                if ( 'publish' === get_post_status( $target_id ) ) {
                    return [ true, get_permalink( $target_id ) ];
                }
            }
            
            return [ true, $this->find_digital_category_for_product( $post->ID ) ];
        }
        
        private function find_digital_category_for_product( int $product_id ): string {
            $digital_cats = array_map( 'intval', (array) get_option( self::OPTION_CATEGORY_IDS, [] ) );
            $product_cats = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'ids' ] );
            if ( empty( $product_cats ) || is_wp_error( $product_cats ) ) return '';
            
            foreach ( $product_cats as $cat_id ) {
                if ( in_array( (int) $cat_id, $digital_cats, true ) ) return get_term_link( (int) $cat_id, 'product_cat' );
            }
            foreach ( $product_cats as $cat_id ) {
                foreach ( get_ancestors( $cat_id, 'product_cat', 'taxonomy' ) as $ancestor_id ) {
                    if ( in_array( (int) $ancestor_id, $digital_cats, true ) ) return get_term_link( (int) $ancestor_id, 'product_cat' );
                }
            }
            return '';
        }
        
        private function execute_redirect( string $target_url ): void {
            global $post;
            if ( empty( $target_url ) || is_wp_error( $target_url ) ) {
                $target_url = ( is_product() && isset( $post->post_title ) ) ? home_url( '/?s=' . urlencode( $post->post_title ) . '&post_type=product' ) : wc_get_page_permalink( 'shop' );
            }
            
            if ( function_exists( 'insertar_prefijo_idioma' ) && function_exists( 'muyu_country_language_prefix' ) ) {
                // Extractor inline minificado ya que no necesitamos la función global
                $sub = strtolower( explode( '.', $_SERVER['HTTP_HOST'] ?? '' )[0] );
                $country = match($sub) { 'mexico'=>'MX','br'=>'BR','co'=>'CO','ec'=>'EC','cl'=>'CL','pe'=>'PE','ar'=>'AR', default=> strtoupper(substr($sub,0,2)) };
                $prefix = muyu_country_language_prefix( $country );
                if ( $prefix ) $target_url = insertar_prefijo_idioma( $target_url, $prefix );
            }
            
            wp_redirect( $target_url, 302 );
            exit;
        }

        // =====================================================================
        // VARIACIONES Y FRONTEND JS BRIDGE
        // =====================================================================
        
        public function hide_physical_variation( bool $visible, $variation_id, $product_id, $variation ): bool {
            if ( ! $visible || ! $this->is_restricted_user() ) return $visible;
            $physical_term = get_term( MUYU_PHYSICAL_FORMAT_ID, 'pa_formato' );
            return ( $physical_term && ! is_wp_error( $physical_term ) ) ? ( $this->extract_format( $variation->get_attributes() ) !== $physical_term->slug ) : $visible;
        }
        
        public function clean_variation_dropdown( array $args ): array {
            if ( ! $this->is_restricted_user() || empty( $args['options'] ) || ! in_array( $args['attribute'] ?? '', ['pa_formato', 'attribute_pa_formato', 'formato', 'attribute_formato'], true ) ) return $args;
            $physical_term = get_term( MUYU_PHYSICAL_FORMAT_ID, 'pa_formato' );
            if ( ! $physical_term || is_wp_error( $physical_term ) ) return $args;
            
            foreach ( $args['options'] as $key => $option ) {
                if ( ( is_object( $option ) && isset( $option->term_id ) && $option->term_id == MUYU_PHYSICAL_FORMAT_ID ) || ( is_string( $option ) && $option === $physical_term->slug ) ) {
                    unset( $args['options'][ $key ] );
                }
            }
            return $args;
        }
        
        public function filter_variation_prices( array $prices_array, $product, $for_display ): array {
            if ( ! $this->is_restricted_user() || empty( $prices_array['price'] ) ) return $prices_array;
            $physical_term = get_term( MUYU_PHYSICAL_FORMAT_ID, 'pa_formato' );
            if ( ! $physical_term || is_wp_error( $physical_term ) ) return $prices_array;
            
            $removed = false;
            foreach ( $prices_array['price'] as $variation_id => $amount ) {
                $format_slug = get_post_meta( $variation_id, 'attribute_pa_formato', true ) ?: get_post_meta( $variation_id, 'attribute_formato', true );
                if ( empty( $format_slug ) && ( $v_obj = wc_get_product( $variation_id ) ) ) $format_slug = $this->extract_format( $v_obj->get_attributes() );
                
                if ( $format_slug === $physical_term->slug ) {
                    unset( $prices_array['price'][ $variation_id ], $prices_array['regular_price'][ $variation_id ], $prices_array['sale_price'][ $variation_id ] );
                    $removed = true;
                }
            }
            
            if ( $removed ) {
                if ( ! empty( $prices_array['price'] ) ) asort( $prices_array['price'] );
                if ( ! empty( $prices_array['regular_price'] ) ) asort( $prices_array['regular_price'] );
                if ( ! empty( $prices_array['sale_price'] ) ) asort( $prices_array['sale_price'] );
            }
            return $prices_array;
        }
        
        public function filter_available_variation_data( $data, $product, $variation ) {
            if ( ! $this->is_restricted_user() ) return $data;
            $physical_term = get_term( MUYU_PHYSICAL_FORMAT_ID, 'pa_formato' );
            return ( $physical_term && ! is_wp_error( $physical_term ) && $this->extract_format( $variation->get_attributes() ) === $physical_term->slug ) ? false : $data;
        }
        
        public function set_format_default( array $defaults, $product ): array {
            $term_id = $this->is_restricted_user() ? MUYU_DIGITAL_FORMAT_ID : MUYU_PHYSICAL_FORMAT_ID;
            $term = get_term( $term_id, 'pa_formato' );
            
            if ( $term && ! is_wp_error( $term ) ) {
                $attributes = ( $product && is_a( $product, 'WC_Product_Variable' ) ) ? $product->get_variation_attributes() : [];
                $key = isset( $attributes['pa_formato'] ) ? 'pa_formato' : ( isset( $attributes['attribute_pa_formato'] ) ? 'attribute_pa_formato' : ( isset( $attributes['formato'] ) ? 'formato' : 'pa_formato' ) );
                $defaults[$key] = $term->slug;
            }
            return $defaults;
        }
        
        public function autoselect_format_variation_bridge(): void {
            global $product;
            if ( ! $product || ! $product->is_type( 'variable' ) ) return;

            $is_restricted = $this->is_restricted_user();
            $attributes = $product->get_variation_attributes();
            if ( ! isset( $attributes['pa_formato'] ) && ! isset( $attributes['attribute_pa_formato'] ) && ! isset( $attributes['formato'] ) ) return;

            $target_term = get_term( $is_restricted ? MUYU_DIGITAL_FORMAT_ID : MUYU_PHYSICAL_FORMAT_ID, 'pa_formato' );
            if ( ! $target_term || is_wp_error( $target_term ) ) return;
            
            $available_slugs = array_merge( $attributes['pa_formato'] ?? [], $attributes['attribute_pa_formato'] ?? [], $attributes['formato'] ?? [] );
            if ( ! in_array( $target_term->slug, $available_slugs, true ) ) return;

            printf( '<span id="mu-format-autoselect-data" style="display:none" data-target-slug="%s" data-hide-row="%s"></span>', esc_attr( $target_term->slug ), $is_restricted ? 'true' : 'false' );
        }
    }
}

if ( ! function_exists( 'muyu_digital_restriction_init' ) ) {
    function muyu_digital_restriction_init(): MUYU_Digital_Restriction_System { return MUYU_Digital_Restriction_System::get_instance(); }
    add_action( 'after_setup_theme', 'muyu_digital_restriction_init', 5 );
}

if ( ! function_exists( 'muyu_is_restricted_user' ) ) {
    function muyu_is_restricted_user(): bool { return muyu_digital_restriction_init()->is_restricted_user(); }
}

if ( ! function_exists( 'muyu_get_digital_product_ids' ) ) {
    function muyu_get_digital_product_ids(): array { return (array) get_option( MUYU_Digital_Restriction_System::OPTION_PRODUCT_IDS, [] ); }
}

if ( ! function_exists( 'muyu_rebuild_digital_indexes_optimized' ) ) {
    function muyu_rebuild_digital_indexes_optimized(): int { return muyu_digital_restriction_init()->rebuild_digital_indexes(); }
}
