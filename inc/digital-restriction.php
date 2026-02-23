<?php
/**
 * Muy Únicos - Digital Restriction System
 * 
 * Sistema de restricción de contenido digital v2.6.1 (Refactor WC-AJAX)
 * Propósito: Restringir productos físicos en subdominios, mostrando solo 
 * productos digitales. Optimizado para rendimiento y compatibilidad.
 * 
 * @package GeneratePress_Child
 * @since 2.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( ! defined( 'MUYU_PHYSICAL_FORMAT_ID' ) ) {
    define( 'MUYU_PHYSICAL_FORMAT_ID', 112 );
}
if ( ! defined( 'MUYU_DIGITAL_FORMAT_ID' ) ) {
    define( 'MUYU_DIGITAL_FORMAT_ID', 111 );
}

if ( ! class_exists( 'MUYU_Digital_Restriction_System' ) ) {

    /**
     * Clase Principal - Patrón Singleton
     */
    class MUYU_Digital_Restriction_System {
        
        private static $instance = null;
        private $cache = [];
        
        const OPTION_PRODUCT_IDS  = 'muyu_digital_product_ids';
        const OPTION_CATEGORY_IDS = 'muyu_digital_category_ids';
        const OPTION_TAG_IDS      = 'muyu_digital_tag_ids';
        const OPTION_REDIRECT_MAP = 'muyu_phys_to_dig_map';
        const OPTION_LAST_UPDATE  = 'muyu_digital_list_updated';
        const TRANSIENT_REBUILD   = 'muyu_rebuild_scheduled';
        
        public static function get_instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        
        private function __construct() {
            $this->init_hooks();
        }
        
        private function init_hooks() {
            // ---- Gestión de índices (Admin) ----
            
            // Refactorizado a Endpoint WC-AJAX
            add_action( 'wc_ajax_mu_rebuild_digital_list', [ $this, 'ajax_rebuild_indexes' ] );
            
            add_action( 'woocommerce_update_product', [ $this, 'schedule_rebuild' ], 10, 1 );
            add_action( 'admin_init', [ $this, 'ensure_indexes_exist' ], 5 );
            add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
            add_action( 'shutdown', [ $this, 'execute_scheduled_rebuild' ] );
            
            // ---- Filtrado de contenido (Frontend) ----
            add_action( 'pre_get_posts', [ $this, 'filter_product_queries' ], 50 );
            add_action( 'template_redirect', [ $this, 'handle_redirects' ], 20 );
            
            // ---- Filtros de Menús y Taxonomías ----
            add_filter( 'get_terms_args', [ $this, 'filter_category_terms' ], 10, 2 );
            add_filter( 'wp_get_nav_menu_items', [ $this, 'filter_menu_items' ], 10, 3 );
            
            // ---- Variaciones (Productos variables) ----
            add_filter( 'woocommerce_variation_is_visible', [ $this, 'hide_physical_variation' ], 10, 4 );
            add_filter( 'woocommerce_dropdown_variation_attribute_options_args', [ $this, 'clean_variation_dropdown' ], 10, 1 );
            add_filter( 'woocommerce_variation_prices', [ $this, 'filter_variation_prices' ], 10, 3 );
            
            // ---- Auto-selección inteligente de variación ----
            add_filter( 'woocommerce_product_get_default_attributes', [ $this, 'set_format_default' ], 20, 2 );
            add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'autoselect_format_variation_bridge' ], 5 );
        }
        
        // =====================================================================
        // DETECCIÓN DE USUARIOS Y CONTEXTO
        // =====================================================================
        
        public function is_restricted_user() {
            if ( isset( $this->cache['is_restricted'] ) ) {
                return $this->cache['is_restricted'];
            }
            
            if ( current_user_can( 'manage_woocommerce' ) || ( is_admin() && ! wp_doing_ajax() ) ) {
                $this->cache['is_restricted'] = false;
                return false;
            }
            
            $main_domain = function_exists( 'muyu_get_main_domain' ) ? muyu_get_main_domain() : 'muyunicos.com';
            $host = preg_replace( '/:\d+$/', '', trim( $_SERVER['HTTP_HOST'] ?? '' ) );
            $host = str_replace( 'www.', '', $host );
            
            $this->cache['is_restricted'] = ( $main_domain !== $host );
            return $this->cache['is_restricted'];
        }
        
        public function get_user_country_code() {
            if ( isset( $this->cache['country_code'] ) ) {
                return $this->cache['country_code'];
            }
            
            if ( function_exists( 'muyu_get_current_country_from_subdomain' ) ) {
                $code = muyu_get_current_country_from_subdomain();
            } else {
                $host = preg_replace( '/:\d+$/', '', trim( $_SERVER['HTTP_HOST'] ?? '' ) );
                $parts = explode( '.', $host );
                if ( count( $parts ) >= 3 ) {
                    $subdomain = strtolower( $parts[0] );
                    $subdomain_map = [
                        'mexico' => 'MX', 'br' => 'BR', 'co' => 'CO', 'ec' => 'EC',
                        'cl' => 'CL', 'pe' => 'PE', 'ar' => 'AR'
                    ];
                    $code = $subdomain_map[ $subdomain ] ?? ( 2 === strlen( $subdomain ) ? strtoupper( $subdomain ) : 'AR' );
                } else {
                    $code = 'AR';
                }
            }
            
            $this->cache['country_code'] = $code;
            return $code;
        }

        // =====================================================================
        // GESTIÓN DE ÍNDICES (REBUILD OPTIMIZADO)
        // =====================================================================
        
        public function rebuild_digital_indexes() {
            $digital_product_ids = $this->get_digital_product_ids();
            
            if ( empty( $digital_product_ids ) ) {
                $this->save_empty_indexes();
                return 0;
            }
            
            list( $category_ids, $tag_ids ) = $this->get_product_terms( $digital_product_ids );
            $category_ids = $this->expand_category_hierarchy( $category_ids );
            $redirect_map = $this->build_redirect_map( $digital_product_ids );
            
            $this->save_indexes( $digital_product_ids, $category_ids, $tag_ids, $redirect_map );
            return count( $digital_product_ids );
        }
        
        private function get_digital_product_ids() {
            global $wpdb;
            $sql = "
                SELECT DISTINCT p.ID as product_id
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'product' 
                AND p.post_status = 'publish'
                AND pm.meta_key IN ('_virtual', '_downloadable')
                AND pm.meta_value = 'yes'
                
                UNION
                
                SELECT DISTINCT p.post_parent as product_id
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'product_variation' 
                AND p.post_status = 'publish'
                AND pm.meta_key IN ('_virtual', '_downloadable')
                AND pm.meta_value = 'yes'
                AND p.post_parent > 0
            ";
            
            $ids = $wpdb->get_col( $sql );
            return array_filter( array_unique( array_map( 'intval', $ids ) ) );
        }
        
        private function get_product_terms( $product_ids ) {
            global $wpdb;
            $ids_string = implode( ',', array_map( 'intval', $product_ids ) );
            if ( empty( $ids_string ) ) return [ [], [] ];

            $sql = "
                SELECT DISTINCT t.term_id, tt.taxonomy 
                FROM {$wpdb->terms} t
                INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                WHERE tr.object_id IN ($ids_string)
                AND tt.taxonomy IN ('product_cat', 'product_tag')
            ";
            
            $terms = $wpdb->get_results( $sql );
            $category_ids = [];
            $tag_ids = [];
            
            foreach ( $terms as $term ) {
                if ( 'product_cat' === $term->taxonomy ) {
                    $category_ids[] = (int) $term->term_id;
                } elseif ( 'product_tag' === $term->taxonomy ) {
                    $tag_ids[] = (int) $term->term_id;
                }
            }
            
            return [ array_unique( $category_ids ), array_unique( $tag_ids ) ];
        }
        
        private function expand_category_hierarchy( $category_ids ) {
            $expanded = $category_ids;
            foreach ( $category_ids as $cat_id ) {
                $ancestors = get_ancestors( $cat_id, 'product_cat', 'taxonomy' );
                if ( ! empty( $ancestors ) ) {
                    $expanded = array_merge( $expanded, $ancestors );
                }
            }
            return array_unique( array_map( 'intval', $expanded ) );
        }
        
        private function build_redirect_map( $digital_product_ids ) {
            global $wpdb;
            if ( empty( $digital_product_ids ) ) return [];
            
            $ids_string = implode( ',', array_map( 'intval', $digital_product_ids ) );
            $sql = "SELECT ID, post_name FROM {$wpdb->posts} WHERE ID IN ($ids_string)";
            $digital_products = $wpdb->get_results( $sql );
            
            $redirect_map = [];
            foreach ( $digital_products as $product ) {
                if ( false !== strpos( $product->post_name, '-imprimible' ) ) {
                    $base_slug = str_replace( '-imprimible', '', $product->post_name );
                    $physical_id = $wpdb->get_var( 
                        $wpdb->prepare(
                            "SELECT ID FROM {$wpdb->posts} 
                            WHERE post_name = %s 
                            AND post_type = 'product' 
                            AND post_status = 'publish' 
                            LIMIT 1",
                            $base_slug
                        )
                    );
                    
                    if ( $physical_id && ! in_array( (int) $physical_id, $digital_product_ids, true ) ) {
                        $redirect_map[ (int) $physical_id ] = (int) $product->ID;
                    }
                }
            }
            return $redirect_map;
        }
        
        private function save_indexes( $product_ids, $category_ids, $tag_ids, $redirect_map ) {
            update_option( self::OPTION_PRODUCT_IDS, $product_ids, false );
            update_option( self::OPTION_CATEGORY_IDS, $category_ids, false );
            update_option( self::OPTION_TAG_IDS, $tag_ids, false );
            update_option( self::OPTION_REDIRECT_MAP, $redirect_map, false );
            update_option( self::OPTION_LAST_UPDATE, current_time( 'mysql' ), false );
            delete_transient( self::TRANSIENT_REBUILD );
        }
        
        private function save_empty_indexes() {
            update_option( self::OPTION_PRODUCT_IDS, [], false );
            update_option( self::OPTION_CATEGORY_IDS, [], false );
            update_option( self::OPTION_TAG_IDS, [], false );
            update_option( self::OPTION_REDIRECT_MAP, [], false );
            update_option( self::OPTION_LAST_UPDATE, current_time( 'mysql' ), false );
        }

        // =====================================================================
        // HANDLERS DE EVENTOS Y ADMIN UI
        // =====================================================================
        
        public function ajax_rebuild_indexes() {
            check_ajax_referer( 'muyu-rebuild-nonce', 'nonce' );
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error( 'Permisos insuficientes' );
            }
            
            $count = $this->rebuild_digital_indexes();
            wp_send_json_success( sprintf( 'Índice reconstruido correctamente. Total productos digitales: %d', $count ) );
        }
        
        public function schedule_rebuild( $product_id ) {
            if ( ! get_transient( self::TRANSIENT_REBUILD ) ) {
                set_transient( self::TRANSIENT_REBUILD, true, 120 );
            }
        }
        
        public function execute_scheduled_rebuild() {
            if ( get_transient( self::TRANSIENT_REBUILD ) ) {
                $this->rebuild_digital_indexes();
            }
        }
        
        public function ensure_indexes_exist() {
            if ( false === get_option( self::OPTION_PRODUCT_IDS ) ) {
                $this->rebuild_digital_indexes();
            }
        }
        
        public function enqueue_admin_assets( $hook ) {
            global $typenow;
            if ( 'edit.php' !== $hook || 'product' !== $typenow ) return;

            $theme_uri = get_stylesheet_directory_uri();
            $ver       = wp_get_theme()->get( 'Version' );

            wp_enqueue_style( 'mu-admin', $theme_uri . '/css/admin.css', [], $ver );
            wp_enqueue_script( 'mu-admin-js', $theme_uri . '/js/admin.js', [], $ver, true );
            wp_localize_script( 'mu-admin-js', 'muyuAdminData', [
                'nonce'       => wp_create_nonce( 'muyu-rebuild-nonce' ),
                'label'       => '⚡ Reindexar Digitales',
                'wc_ajax_url' => \WC_AJAX::get_endpoint( '%%endpoint%%' )
            ] );
        }

        // =====================================================================
        // FILTRADO DE QUERIES (PRE_GET_POSTS)
        // =====================================================================
        
        public function filter_product_queries( $query ) {
            if ( is_admin() || ! $query->is_main_query() ) return;
            if ( $query->is_product() || ( $query->is_singular() && 'product' === $query->get( 'post_type' ) ) ) return;
            
            $is_shop_query = (
                ( function_exists( 'is_shop' ) && is_shop() ) ||
                ( function_exists( 'is_product_category' ) && is_product_category() ) ||
                ( function_exists( 'is_product_tag' ) && is_product_tag() ) ||
                is_search() ||
                'product' === $query->get( 'post_type' )
            );
            
            if ( ! $is_shop_query ) return;
            
            if ( $this->is_restricted_user() ) {
                $digital_ids = get_option( self::OPTION_PRODUCT_IDS, [] );
                $digital_ids = array_map( 'intval', (array) $digital_ids );
                $query->set( 'post__in', ! empty( $digital_ids ) ? $digital_ids : [ 0 ] );
            }
        }

        // =====================================================================
        // FILTROS FRONTEND (MENÚS Y TAXONOMÍAS)
        // =====================================================================
        
        public function filter_category_terms( $args, $taxonomies ) {
            if ( is_admin() || wp_is_json_request() ) return $args;
            if ( ! in_array( 'product_cat', (array) $taxonomies, true ) || ! $this->is_restricted_user() ) return $args;
            
            $digital_cat_ids = get_option( self::OPTION_CATEGORY_IDS, [] );
            $digital_cat_ids = array_map( 'intval', (array) $digital_cat_ids );
            
            if ( ! empty( $args['include'] ) ) {
                $current = array_map( 'intval', is_array( $args['include'] ) ? $args['include'] : explode( ',', $args['include'] ) );
                $args['include'] = array_intersect( $current, $digital_cat_ids );
            } else {
                $args['include'] = empty( $digital_cat_ids ) ? [ 0 ] : $digital_cat_ids;
            }
            
            return $args;
        }
        
        public function filter_menu_items( $items, $menu, $args ) {
            if ( is_admin() || wp_is_json_request() || ! $this->is_restricted_user() ) return $items;
            
            $digital_cat_ids = get_option( self::OPTION_CATEGORY_IDS, [] );
            $digital_cat_ids = array_map( 'intval', (array) $digital_cat_ids );
            
            return array_filter( $items, function( $item ) use ( $digital_cat_ids ) {
                if ( isset( $item->object ) && 'product_cat' === $item->object ) {
                    return in_array( (int) $item->object_id, $digital_cat_ids, true );
                }
                return true;
            });
        }

        // =====================================================================
        // REDIRECCIONES (REFACTORIZADO)
        // =====================================================================
        
        public function handle_redirects() {
            if ( is_admin() || ! $this->is_restricted_user() ) return;
            if ( ! is_product() && ! is_product_category() && ! is_product_tag() ) return;
            
            $target_url = '';
            $should_redirect = false;
            
            if ( is_product_category() ) {
                list( $should_redirect, $target_url ) = $this->handle_category_redirect();
            } elseif ( is_product_tag() ) {
                list( $should_redirect, $target_url ) = $this->handle_tag_redirect();
            } elseif ( is_product() ) {
                list( $should_redirect, $target_url ) = $this->handle_product_redirect();
            }
            
            if ( $should_redirect ) {
                $this->execute_redirect( $target_url );
            }
        }
        
        private function handle_category_redirect() {
            $queried_object = get_queried_object();
            $digital_cats = get_option( self::OPTION_CATEGORY_IDS, [] );
            $digital_cats = array_map( 'intval', (array) $digital_cats );
            
            if ( ! $queried_object || in_array( (int) $queried_object->term_id, $digital_cats, true ) ) {
                return [ false, '' ];
            }
            
            $parent_id = $queried_object->parent;
            while ( $parent_id ) {
                if ( in_array( (int) $parent_id, $digital_cats, true ) ) {
                    return [ true, get_term_link( $parent_id, 'product_cat' ) ];
                }
                $term = get_term( $parent_id, 'product_cat' );
                $parent_id = ( $term && ! is_wp_error( $term ) ) ? $term->parent : 0;
            }
            
            return [ true, '' ];
        }
        
        private function handle_tag_redirect() {
            $queried_object = get_queried_object();
            $digital_tags = get_option( self::OPTION_TAG_IDS, [] );
            $digital_tags = array_map( 'intval', (array) $digital_tags );
            
            if ( ! $queried_object || in_array( (int) $queried_object->term_id, $digital_tags, true ) ) {
                return [ false, '' ];
            }
            
            return [ true, '' ];
        }
        
        private function handle_product_redirect() {
            global $post;
            $digital_ids = get_option( self::OPTION_PRODUCT_IDS, [] );
            $digital_ids = array_map( 'intval', (array) $digital_ids );
            
            if ( ! $post || in_array( (int) $post->ID, $digital_ids, true ) ) {
                return [ false, '' ];
            }
            
            $redirect_map = get_option( self::OPTION_REDIRECT_MAP, [] );
            if ( isset( $redirect_map[ $post->ID ] ) ) {
                return [ true, get_permalink( $redirect_map[ $post->ID ] ) ];
            }
            
            $target_url = $this->find_digital_category_for_product( $post->ID );
            return [ true, $target_url ];
        }
        
        private function find_digital_category_for_product( $product_id ) {
            $digital_cats = get_option( self::OPTION_CATEGORY_IDS, [] );
            $digital_cats = array_map( 'intval', (array) $digital_cats );
            $product_cats = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'ids' ] );
            
            if ( empty( $product_cats ) || is_wp_error( $product_cats ) ) {
                return '';
            }
            
            foreach ( $product_cats as $cat_id ) {
                if ( in_array( (int) $cat_id, $digital_cats, true ) ) {
                    return get_term_link( (int) $cat_id, 'product_cat' );
                }
            }
            
            foreach ( $product_cats as $cat_id ) {
                $ancestors = get_ancestors( $cat_id, 'product_cat', 'taxonomy' );
                foreach ( $ancestors as $ancestor_id ) {
                    if ( in_array( (int) $ancestor_id, $digital_cats, true ) ) {
                        return get_term_link( (int) $ancestor_id, 'product_cat' );
                    }
                }
            }
            
            return '';
        }
        
        private function execute_redirect( $target_url ) {
            global $post;
            
            if ( empty( $target_url ) || is_wp_error( $target_url ) ) {
                if ( is_product() && isset( $post->post_title ) ) {
                    $target_url = home_url( '/?s=' . urlencode( $post->post_title ) . '&post_type=product' );
                } else {
                    $target_url = wc_get_page_permalink( 'shop' );
                }
            }
            
            if ( function_exists( 'insertar_prefijo_idioma' ) && function_exists( 'muyu_country_language_prefix' ) ) {
                $prefix = muyu_country_language_prefix( $this->get_user_country_code() );
                if ( $prefix ) {
                    $target_url = insertar_prefijo_idioma( $target_url, $prefix );
                }
            }
            
            wp_redirect( $target_url, 302 );
            exit;
        }

        // =====================================================================
        // GESTIÓN DE VARIACIONES (pa_formato)
        // =====================================================================
        
        public function hide_physical_variation( $visible, $variation_id, $product_id, $variation ) {
            if ( ! $visible || ! $this->is_restricted_user() ) return $visible;
            
            $attributes = $variation->get_attributes();
            $physical_term = get_term( MUYU_PHYSICAL_FORMAT_ID, 'pa_formato' );
            
            if ( $physical_term && ! is_wp_error( $physical_term ) ) {
                if ( isset( $attributes['pa_formato'] ) && $attributes['pa_formato'] === $physical_term->slug ) {
                    return false;
                }
            }
            return $visible;
        }
        
        public function clean_variation_dropdown( $args ) {
            if ( ! $this->is_restricted_user() || ! isset( $args['attribute'] ) || 'pa_formato' !== $args['attribute'] ) return $args;
            if ( empty( $args['options'] ) ) return $args;
            
            $physical_term = get_term( MUYU_PHYSICAL_FORMAT_ID, 'pa_formato' );
            if ( ! $physical_term || is_wp_error( $physical_term ) ) return $args;
            
            foreach ( $args['options'] as $key => $option ) {
                if ( ( is_object( $option ) && isset( $option->term_id ) && $option->term_id == MUYU_PHYSICAL_FORMAT_ID ) ||
                     ( is_string( $option ) && $option === $physical_term->slug ) ) {
                    unset( $args['options'][ $key ] );
                }
            }
            return $args;
        }
        
        public function filter_variation_prices( $prices_array, $product, $for_display ) {
            if ( ! $this->is_restricted_user() || empty( $prices_array['price'] ) ) return $prices_array;
            
            $physical_term = get_term( MUYU_PHYSICAL_FORMAT_ID, 'pa_formato' );
            if ( ! $physical_term || is_wp_error( $physical_term ) ) return $prices_array;
            
            foreach ( $prices_array['price'] as $variation_id => $amount ) {
                $format_slug = get_post_meta( $variation_id, 'attribute_pa_formato', true );
                if ( $format_slug === $physical_term->slug ) {
                    unset( 
                        $prices_array['price'][ $variation_id ], 
                        $prices_array['regular_price'][ $variation_id ], 
                        $prices_array['sale_price'][ $variation_id ] 
                    );
                }
            }
            return $prices_array;
        }
        
        public function set_format_default( $defaults, $product ) {
            $is_restricted = $this->is_restricted_user();
            $country       = $this->get_user_country_code();
            
            if ( $is_restricted ) {
                $term_id = MUYU_DIGITAL_FORMAT_ID;
            } elseif ( 'AR' === $country ) {
                $term_id = MUYU_PHYSICAL_FORMAT_ID;
            } else {
                return $defaults;
            }
            
            $term = get_term( $term_id, 'pa_formato' );
            if ( $term && ! is_wp_error( $term ) ) {
                $defaults['pa_formato'] = $term->slug;
            }
            return $defaults;
        }
        
        public function autoselect_format_variation_bridge() {
            global $product;
            if ( ! $product || ! $product->is_type( 'variable' ) ) return;

            $is_restricted = $this->is_restricted_user();
            $country       = $this->get_user_country_code();

            if ( $is_restricted ) {
                $target_term_id = MUYU_DIGITAL_FORMAT_ID;
                $hide_row       = true;
            } elseif ( 'AR' === $country ) {
                $target_term_id = MUYU_PHYSICAL_FORMAT_ID;
                $hide_row       = false;
            } else {
                return;
            }

            $attributes = $product->get_variation_attributes();
            if ( ! isset( $attributes['pa_formato'] ) ) return;

            $target_term = get_term( $target_term_id, 'pa_formato' );
            if ( ! $target_term || is_wp_error( $target_term ) ) return;
            if ( ! in_array( $target_term->slug, $attributes['pa_formato'], true ) ) return;

            // Bridge para js/shop.js (Evita <script> inline según MIGRATION-GUIDE.md)
            printf(
                '<span id="mu-format-autoselect-data" style="display:none" data-target-slug="%s" data-hide-row="%s"></span>',
                esc_attr( $target_term->slug ),
                $hide_row ? 'true' : 'false'
            );
        }
    }
}

/**
 * ============================================================================
 * INICIALIZACIÓN Y COMPATIBILIDAD
 * ============================================================================
 */

if ( ! function_exists( 'muyu_digital_restriction_init' ) ) {
    function muyu_digital_restriction_init() {
        return MUYU_Digital_Restriction_System::get_instance();
    }
    // Prioridad temprana para registrar hooks correctamente
    add_action( 'plugins_loaded', 'muyu_digital_restriction_init', 5 );
}

/* --- Helpers Funcionales para Backward Compatibility --- */

if ( ! function_exists( 'muyu_is_restricted_user' ) ) {
    function muyu_is_restricted_user() {
        return muyu_digital_restriction_init()->is_restricted_user();
    }
}

if ( ! function_exists( 'muyu_get_digital_product_ids' ) ) {
    function muyu_get_digital_product_ids() {
        return get_option( MUYU_Digital_Restriction_System::OPTION_PRODUCT_IDS, [] );
    }
}

if ( ! function_exists( 'muyu_rebuild_digital_indexes_optimized' ) ) {
    function muyu_rebuild_digital_indexes_optimized() {
        return muyu_digital_restriction_init()->rebuild_digital_indexes();
    }
}
