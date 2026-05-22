<?php
/**
 * Muy Únicos - Digital Restriction System
 * Sistema de restricción de contenido digital v4.6.0
 *
 * CHANGELOG v4.6.0:
 * - FIX: categoría con cobertura digital pero único producto oculto del catálogo
 *   (publish + hidden / exclude-from-catalog) entraba como ruta válida y mostraba
 *   página vacía tras filter_product_queries. handle_category_redirect() ahora
 *   redirige al shop si la categoría no tiene ningún producto digital visible.
 *   filter_menu_items() excluye esas categorías del menú con el mismo criterio.
 * - NEW: category_has_visible_digital_products() helper — WP_Query limitada a 1,
 *   tax_query con product_visibility NOT IN exclude-from-catalog, cache transient
 *   mu_digital_cat_has_visible_{term_id} (TTL 12h, invalidado en save_indexes()).
 *
 * CHANGELOG v4.5.0:
 * - FIX #1: handle_404_category_redirect(): lookup by_slug ANTES del by_id.
 *   El mapa by_slug es O(1) sobre el slug extraído del path, no requiere
 *   resolver term_id y es más robusto ante mapas stale o primer acceso.
 * - FIX #2: get_category_from_request_path(): itera TODOS los segmentos del
 *   path de más específico a más genérico (end → inicio). Cubre URLs WC donde
 *   el segmento válido como categoría no es necesariamente el último.
 * - FIX #3: handle_404_category_redirect(): si $source_id está en $digital_cats,
 *   solo redirigir al canonical si la URL actual no coincide ya con
 *   get_term_link() — previene redirect infinito si la categoría digital
 *   también genera 404 por otro motivo.
 * - FIX #4 (clave): filter_category_terms(): guard is_404() — si WP ya
 *   resolvió como 404, no filtrar terms. Corta el problema en la raíz:
 *   filter_category_terms() no puede romper nada en un 404 porque WC_Query
 *   ya falló en el routing; al no filtrar, handle_404_category_redirect()
 *   corre en un contexto más limpio.
 *
 * @package GeneratePress_Child
 * @since 4.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'MUYU_Digital_Restriction_System' ) ) {

    class MUYU_Digital_Restriction_System {
        
        private static ?self $instance = null;
        
        const OPTION_PRODUCT_IDS            = 'muyu_digital_product_ids';
        const OPTION_CATEGORY_IDS           = 'muyu_digital_category_ids';
        const OPTION_DIRECT_CATEGORY_IDS    = 'muyu_digital_direct_category_ids';
        const OPTION_TAG_IDS                = 'muyu_digital_tag_ids';
        const OPTION_REDIRECT_MAP           = 'muyu_phys_to_dig_map';
        const OPTION_CATEGORY_REDIRECT_MAP  = 'muyu_cat_redirect_map';
        const OPTION_LAST_UPDATE            = 'muyu_digital_list_updated';
        const CRON_HOOK                     = 'muyu_cron_rebuild_digital_indexes';
        
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
            add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

            // ---- Cron: ejecuta rebuild en background real (NO en shutdown) ----
            add_action( self::CRON_HOOK, [ $this, 'rebuild_digital_indexes' ] );

            // ---- Verificación silenciosa de índices solo en admin (lazy, sin rebuild síncrono) ----
            add_action( 'admin_init', [ $this, 'ensure_indexes_exist' ], 5 );
            
            // ---- Filtrado de contenido (Frontend) ----
            add_action( 'pre_get_posts', [ $this, 'filter_product_queries' ], 50 );
            add_action( 'template_redirect', [ $this, 'handle_redirects' ], 20 );
            add_filter( 'get_terms_args', [ $this, 'filter_category_terms' ], 10, 2 );
            add_filter( 'wp_get_nav_menu_items', [ $this, 'filter_menu_items' ], 10, 3 );
            
            // ---- Auto-selección Global (Para todos los países) ----
            add_filter( 'woocommerce_product_get_default_attributes', [ $this, 'set_format_default' ], 20, 2 );
            add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'autoselect_format_variation' ], 5 );
            
            // ---- Variaciones y Precios (Solo en subdominios restringidos) ----
            if ( $this->is_restricted_user() ) {
                add_filter( 'woocommerce_dropdown_variation_attribute_options_args', [ $this, 'clean_variation_dropdown' ], 10, 1 );
                add_filter( 'woocommerce_variable_price_html', [ $this, 'display_digital_price_in_catalog' ], 99, 2 );
                add_filter( 'woocommerce_variable_sale_price_html', [ $this, 'display_digital_price_in_catalog' ], 99, 2 );
            }
        }

        // =====================================================================
        // HELPERS
        // =====================================================================

        public function is_restricted_user(): bool {
            $host = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) );
            $host = preg_replace( '/:\d+$/', '', $host );
            return 'muyunicos.com' !== str_replace( 'www.', '', $host );
        }

        private function get_cached_digital_product_ids(): array {
            return array_map( 'intval', (array) get_option( self::OPTION_PRODUCT_IDS, [] ) );
        }

        /**
         * Intenta resolver un WP_Term de product_cat a partir del path de la
         * request actual, sin depender de is_product_category() (que devuelve
         * false en 404).
         *
         * v4.5.0 FIX #2: itera TODOS los segmentos del path de más específico
         * (end) a más genérico (inicio), no solo el último. Esto cubre URLs
         * donde la base WC (/tienda/) es parte del path y el segmento que
         * corresponde a una categoría puede ser cualquiera de los fragmentos.
         *
         * @return WP_Term|null
         */
        private function get_category_from_request_path(): ?\WP_Term {
            $request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
            $path        = trim( strtok( $request_uri, '?' ), '/' );
            if ( empty( $path ) ) return null;

            // Iterar segmentos de más específico (último) a más genérico (primero)
            $segments = array_values( array_filter( explode( '/', $path ) ) );
            if ( empty( $segments ) ) return null;

            foreach ( array_reverse( $segments ) as $slug ) {
                if ( empty( $slug ) ) continue;
                $term = get_term_by( 'slug', $slug, 'product_cat' );
                if ( $term && ! is_wp_error( $term ) ) {
                    return $term;
                }
            }
            return null;
        }

        /**
         * ¿La categoría tiene al menos 1 producto visible en catálogo?
         *
         * En subdominios digitales, OPTION_CATEGORY_IDS incluye categorías cuyo
         * único producto digital puede estar oculto (catalog_visibility=hidden o
         * exclude-from-catalog). Esas categorías no se redirigen por índice pero
         * al entrar muestran 0 resultados tras filter_product_queries.
         *
         * Este helper detecta esos casos: contra el set de OPTION_PRODUCT_IDS
         * (productos digitales publicados), verifica que al menos 1 esté en la
         * categoría y NO esté excluido del catálogo via product_visibility.
         *
         * Transient mu_digital_cat_has_visible_{term_id} (TTL 12h),
         * invalidado en save_indexes() tras rebuild.
         */
        private function category_has_visible_digital_products( int $term_id ): bool {
            if ( $term_id <= 0 ) return false;

            $cache_key = 'mu_digital_cat_has_visible_' . $term_id;
            $cached    = get_transient( $cache_key );
            if ( false !== $cached ) {
                return '1' === $cached;
            }

            $digital_ids = $this->get_cached_digital_product_ids();
            if ( empty( $digital_ids ) ) {
                set_transient( $cache_key, '0', 12 * HOUR_IN_SECONDS );
                return false;
            }

            $query = new \WP_Query( [
                'post_type'              => 'product',
                'post_status'            => 'publish',
                'posts_per_page'         => 1,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'post__in'               => $digital_ids,
                'tax_query'              => [
                    'relation' => 'AND',
                    [
                        'taxonomy'         => 'product_cat',
                        'field'            => 'term_id',
                        'terms'            => [ $term_id ],
                        'include_children' => true,
                    ],
                    [
                        'taxonomy' => 'product_visibility',
                        'field'    => 'name',
                        'terms'    => [ 'exclude-from-catalog' ],
                        'operator' => 'NOT IN',
                    ],
                ],
            ] );

            $has = ! empty( $query->posts );
            set_transient( $cache_key, $has ? '1' : '0', 12 * HOUR_IN_SECONDS );
            return $has;
        }

        // =====================================================================
        // GESTIÓN DE ÍNDICES (Background)
        // =====================================================================
        
        public function rebuild_digital_indexes(): int {
            $digital_product_ids = $this->get_digital_product_ids();
            
            if ( empty( $digital_product_ids ) ) {
                $this->save_indexes( [], [], [], [], [], [] );
                return 0;
            }
            
            list( $category_ids, $tag_ids ) = $this->get_product_terms( $digital_product_ids );
            // $direct_category_ids: categorías con productos digitales DIRECTOS (sin expansión)
            $direct_category_ids = $category_ids;
            // $category_ids: expandido con ancestros, para filtros de navegación
            $category_ids        = $this->expand_category_hierarchy( $category_ids );
            $redirect_map        = $this->build_redirect_map( $digital_product_ids );
            $cat_redirect_map    = $this->build_category_redirect_map(
                $digital_product_ids,
                $category_ids,
                $direct_category_ids
            );
            
            $this->save_indexes( $digital_product_ids, $category_ids, $tag_ids, $redirect_map, $cat_redirect_map, $direct_category_ids );
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

        /**
         * Construye el mapa de redirección de categorías — v4.4.2 (sin cambios en v4.5.0)
         *
         * @param int[]   $digital_product_ids    IDs de productos digitales.
         * @param int[]   $digital_category_ids   IDs con cobertura (incluye ancestros).
         * @param int[]   $direct_category_ids    IDs con productos digitales DIRECTOS.
         * @return array{ 'by_id' => array, 'by_slug' => array }
         */
        private function build_category_redirect_map(
            array $digital_product_ids,
            array $digital_category_ids,
            array $direct_category_ids
        ): array {
            $all_cats = get_terms( [
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
                'fields'     => 'all',
            ] );

            if ( is_wp_error( $all_cats ) || empty( $all_cats ) ) {
                return [ 'by_id' => [], 'by_slug' => [] ];
            }

            $term_index = [];
            foreach ( $all_cats as $term ) {
                $term_index[ (int) $term->term_id ] = $term;
            }

            $children_index = [];
            foreach ( $all_cats as $term ) {
                $parent = (int) $term->parent;
                if ( $parent ) {
                    $children_index[ $parent ][] = (int) $term->term_id;
                }
            }

            $by_id   = [];
            $by_slug = [];

            foreach ( $all_cats as $source_term ) {
                $source_id = (int) $source_term->term_id;

                if ( in_array( $source_id, $direct_category_ids, true ) ) {
                    continue;
                }

                $dest_id = $this->find_nearest_digital_ancestor(
                    $source_id,
                    $direct_category_ids,
                    $term_index
                );

                if ( ! $dest_id ) {
                    $dest_id = $this->find_nearest_direct_digital_child(
                        $source_id,
                        $direct_category_ids,
                        $children_index
                    );
                }

                if ( $dest_id && isset( $term_index[ $dest_id ] ) ) {
                    $by_id[ $source_id ]           = $dest_id;
                    $by_slug[ $source_term->slug ] = $term_index[ $dest_id ]->slug;
                }
            }

            return [ 'by_id' => $by_id, 'by_slug' => $by_slug ];
        }

        private function find_nearest_digital_ancestor( int $term_id, array $digital_category_ids, array $term_index ): ?int {
            $visited   = [];
            $current   = $term_index[ $term_id ] ?? null;
            $parent_id = $current ? (int) $current->parent : 0;

            while ( $parent_id && ! isset( $visited[ $parent_id ] ) ) {
                $visited[ $parent_id ] = true;
                if ( in_array( $parent_id, $digital_category_ids, true ) ) {
                    return $parent_id;
                }
                $parent_term = $term_index[ $parent_id ] ?? null;
                $parent_id   = $parent_term ? (int) $parent_term->parent : 0;
            }
            return null;
        }

        private function find_nearest_direct_digital_child(
            int $term_id,
            array $direct_category_ids,
            array $children_index
        ): ?int {
            $queue   = $children_index[ $term_id ] ?? [];
            $visited = [];

            while ( ! empty( $queue ) ) {
                $current_id = array_shift( $queue );
                if ( isset( $visited[ $current_id ] ) ) continue;
                $visited[ $current_id ] = true;

                if ( in_array( $current_id, $direct_category_ids, true ) ) {
                    return $current_id;
                }

                foreach ( ( $children_index[ $current_id ] ?? [] ) as $child_id ) {
                    if ( ! isset( $visited[ $child_id ] ) ) {
                        $queue[] = $child_id;
                    }
                }
            }
            return null;
        }
        
        private function save_indexes(
            array $product_ids,
            array $category_ids,
            array $tag_ids,
            array $redirect_map,
            array $cat_redirect_map = [],
            array $direct_category_ids = []
        ): void {
            $previous_category_ids = array_map( 'intval', (array) get_option( self::OPTION_CATEGORY_IDS, [] ) );

            update_option( self::OPTION_PRODUCT_IDS,            $product_ids,         false );
            update_option( self::OPTION_CATEGORY_IDS,           $category_ids,        false );
            update_option( self::OPTION_DIRECT_CATEGORY_IDS,    $direct_category_ids, false );
            update_option( self::OPTION_TAG_IDS,                $tag_ids,             false );
            update_option( self::OPTION_REDIRECT_MAP,           $redirect_map,        false );
            update_option( self::OPTION_CATEGORY_REDIRECT_MAP,  $cat_redirect_map,    false );
            update_option( self::OPTION_LAST_UPDATE,            current_time( 'mysql' ), false );

            // Invalidar transients de visibilidad de categorías (helper
            // category_has_visible_digital_products). Cubre tanto las categorías
            // previas como las actuales por si una saliera del índice.
            foreach ( array_unique( array_merge( $previous_category_ids, $category_ids ) ) as $cid ) {
                delete_transient( 'mu_digital_cat_has_visible_' . (int) $cid );
            }
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
            if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
                wp_schedule_single_event( time() + 30, self::CRON_HOOK );
            }
        }
        
        public function ensure_indexes_exist(): void {
            $ids = get_option( self::OPTION_PRODUCT_IDS, false );
            if ( false === $ids || empty( $ids ) ) {
                $this->schedule_rebuild();
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
            
            $is_shop_query = (
                ( function_exists( 'is_shop' ) && is_shop() ) ||
                ( function_exists( 'is_product_category' ) && is_product_category() ) ||
                ( function_exists( 'is_product_tag' ) && is_product_tag() ) ||
                is_search() ||
                'product' === $query->get( 'post_type' )
            );
            if ( ! $is_shop_query || ! $this->is_restricted_user() ) return;
            
            $digital_ids = get_option( self::OPTION_PRODUCT_IDS, false );
            if ( false === $digital_ids || empty( $digital_ids ) ) {
                $this->schedule_rebuild();
                return;
            }

            $query->set( 'post__in', array_map( 'intval', (array) $digital_ids ) );
        }

        /**
         * filter_category_terms() — v4.5.0 FIX #4
         *
         * Guard is_404() AÑADIDO: si WP ya resolvió esta request como 404,
         * no filtrar los terms de product_cat. Si filtramos en un 404, estamos
         * excluyendo del include la categoría cuya URL intentamos resolver,
         * lo que impide que WC_Query la mapee como ruta válida en requests
         * posteriores y confunde a handle_404_category_redirect().
         *
         * Al no filtrar en 404, handle_404_category_redirect() corre en un
         * contexto más limpio: get_term_by() resuelve el term correctamente
         * (ya lo hacía, bypasea get_terms_args), y el lookup by_slug/by_id
         * puede completar la redirección sin interferencias.
         */
        public function filter_category_terms( array $args, array $taxonomies ): array {
            if ( is_admin() || wp_doing_ajax() || wp_is_json_request() ) return $args;
            if ( ! empty( $args['object_ids'] ) ) return $args;
            
            // FIX #4: no filtrar si WP ya resolvió como 404 — la request ya está
            // rota; filtrar aquí solo agravaría la situación.
            if ( is_404() ) return $args;

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
            return array_filter( $items, function( $item ) use ( $digital_cat_ids ) {
                if ( ! isset( $item->object ) || 'product_cat' !== $item->object ) {
                    return true;
                }
                $cat_id = (int) $item->object_id;
                if ( ! in_array( $cat_id, $digital_cat_ids, true ) ) {
                    return false;
                }
                return $this->category_has_visible_digital_products( $cat_id );
            } );
        }

        public function handle_redirects(): void {
            if ( is_admin() || ! $this->is_restricted_user() ) return;

            $target_url      = '';
            $should_redirect = false;

            if ( is_product_category() ) {
                list( $should_redirect, $target_url ) = $this->handle_category_redirect();

            } elseif ( is_product_tag() ) {
                list( $should_redirect, $target_url ) = $this->handle_tag_redirect();

            } elseif ( is_product() ) {
                list( $should_redirect, $target_url ) = $this->handle_product_redirect();

            } elseif ( is_404() ) {
                list( $should_redirect, $target_url ) = $this->handle_404_category_redirect();
            }

            if ( $should_redirect ) {
                $this->execute_redirect( $target_url );
            }
        }

        private function handle_category_redirect(): array {
            $queried_object = get_queried_object();
            if ( ! $queried_object || ! isset( $queried_object->term_id ) ) {
                return [ false, '' ];
            }

            $term_id      = (int) $queried_object->term_id;
            $digital_cats = array_map( 'intval', (array) get_option( self::OPTION_CATEGORY_IDS, [] ) );

            if ( ! in_array( $term_id, $digital_cats, true ) ) {
                $cat_redirect_map = get_option( self::OPTION_CATEGORY_REDIRECT_MAP, [] );
                $by_id = $cat_redirect_map['by_id'] ?? [];

                if ( isset( $by_id[ $term_id ] ) ) {
                    $dest_id  = (int) $by_id[ $term_id ];
                    $dest_url = get_term_link( $dest_id, 'product_cat' );
                    if ( ! is_wp_error( $dest_url ) ) {
                        return [ true, $dest_url ];
                    }
                }

                $parent_id = $queried_object->parent;
                while ( $parent_id ) {
                    if ( in_array( (int) $parent_id, $digital_cats, true ) ) {
                        return [ true, get_term_link( $parent_id, 'product_cat' ) ];
                    }
                    $term      = get_term( $parent_id, 'product_cat' );
                    $parent_id = ( $term && ! is_wp_error( $term ) ) ? $term->parent : 0;
                }

                return [ true, '' ];
            }

            // Segundo check: categoría en índice digital pero sin productos
            // visibles en catálogo (oculto / exclude-from-catalog). Redirigir
            // al shop para evitar página vacía tras filter_product_queries.
            if ( ! $this->category_has_visible_digital_products( $term_id ) ) {
                return [ true, '' ];
            }

            return [ false, '' ];
        }

        /**
         * handle_404_category_redirect() — v4.5.0
         *
         * FIX #1: lookup by_slug ANTES del by_id.
         *   El mapa by_slug es O(1) sobre el slug del path, no requiere
         *   resolver term_id. Más robusto ante mapas stale.
         *
         * FIX #3: anti-loop canonical.
         *   Si $source_id está en $digital_cats, solo redirigir si la URL
         *   actual NO coincide ya con get_term_link() del término.
         *   Previene redirect infinito si la categoría digital también
         *   genera 404 por algún motivo externo.
         */
        private function handle_404_category_redirect(): array {
            $term = $this->get_category_from_request_path();
            if ( ! $term ) {
                return [ false, '' ];
            }

            $source_id    = (int) $term->term_id;
            $source_slug  = $term->slug;
            $digital_cats = array_map( 'intval', (array) get_option( self::OPTION_CATEGORY_IDS, [] ) );

            // FIX #3: categoría digital que genera 404 — redirigir solo si la URL
            // actual difiere del canonical, para evitar redirect infinito.
            if ( in_array( $source_id, $digital_cats, true ) ) {
                $canonical = get_term_link( $source_id, 'product_cat' );
                if ( ! is_wp_error( $canonical ) ) {
                    $current_url = ( is_ssl() ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                    if ( untrailingslashit( $current_url ) !== untrailingslashit( $canonical ) ) {
                        return [ true, $canonical ];
                    }
                }
                return [ false, '' ];
            }

            $cat_redirect_map = get_option( self::OPTION_CATEGORY_REDIRECT_MAP, [] );
            $by_slug = $cat_redirect_map['by_slug'] ?? [];
            $by_id   = $cat_redirect_map['by_id']   ?? [];

            // FIX #1: lookup by_slug primero — O(1), no requiere term_id, más robusto ante mapas stale.
            if ( isset( $by_slug[ $source_slug ] ) ) {
                $dest_slug = $by_slug[ $source_slug ];
                $dest_term = get_term_by( 'slug', $dest_slug, 'product_cat' );
                if ( $dest_term && ! is_wp_error( $dest_term ) ) {
                    $dest_url = get_term_link( $dest_term->term_id, 'product_cat' );
                    if ( ! is_wp_error( $dest_url ) ) {
                        return [ true, $dest_url ];
                    }
                }
            }

            // Lookup by_id como fallback del mapa (en caso de que by_slug falle)
            if ( isset( $by_id[ $source_id ] ) ) {
                $dest_id  = (int) $by_id[ $source_id ];
                $dest_url = get_term_link( $dest_id, 'product_cat' );
                if ( ! is_wp_error( $dest_url ) ) {
                    return [ true, $dest_url ];
                }
            }

            // Fallback final: subir por padres
            $parent_id = (int) $term->parent;
            while ( $parent_id ) {
                if ( in_array( $parent_id, $digital_cats, true ) ) {
                    $dest_url = get_term_link( $parent_id, 'product_cat' );
                    if ( ! is_wp_error( $dest_url ) ) {
                        $this->schedule_rebuild();
                        return [ true, $dest_url ];
                    }
                }
                $parent_term = get_term( $parent_id, 'product_cat' );
                $parent_id   = ( $parent_term && ! is_wp_error( $parent_term ) ) ? (int) $parent_term->parent : 0;
            }

            return [ true, '' ];
        }
        
        private function handle_tag_redirect(): array {
            $queried_object = get_queried_object();
            $digital_tags   = array_map( 'intval', (array) get_option( self::OPTION_TAG_IDS, [] ) );
            return [ ( $queried_object && ! in_array( (int) $queried_object->term_id, $digital_tags, true ) ), '' ];
        }
        
        private function handle_product_redirect(): array {
            global $post;
            
            $digital_ids = (array) get_option( self::OPTION_PRODUCT_IDS, [] );
            if ( empty( $digital_ids ) ) {
                $this->schedule_rebuild();
                return [ false, '' ];
            }
            
            if ( ! $post || in_array( (int) $post->ID, array_map( 'intval', $digital_ids ), true ) ) {
                return [ false, '' ];
            }
            
            $redirect_map = get_option( self::OPTION_REDIRECT_MAP, [] );
            if ( isset( $redirect_map[ $post->ID ] ) ) {
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
                $target_url = ( is_product() && isset( $post->post_title ) )
                    ? home_url( '/?s=' . urlencode( $post->post_title ) . '&post_type=product' )
                    : wc_get_page_permalink( 'shop' );
            }
            
            if ( function_exists( 'insertar_prefijo_idioma' ) && function_exists( 'muyu_country_language_prefix' ) ) {
                $sub     = strtolower( explode( '.', $_SERVER['HTTP_HOST'] ?? '' )[0] );
                $country = match ( $sub ) {
                    'mexico' => 'MX',
                    'br'     => 'BR',
                    'co'     => 'CO',
                    'ec'     => 'EC',
                    'cl'     => 'CL',
                    'pe'     => 'PE',
                    'ar'     => 'AR',
                    default  => strtoupper( substr( $sub, 0, 2 ) ),
                };
                $prefix = muyu_country_language_prefix( $country );
                if ( $prefix ) {
                    $target_url = insertar_prefijo_idioma( $target_url, $prefix );
                }
            }
            
            wp_redirect( $target_url, 302 );
            exit;
        }

        // =====================================================================
        // VARIACIONES FRONTEND Y PRECIOS
        // =====================================================================
        
        public function clean_variation_dropdown( $args ) {
            $attribute = $args['attribute'] ?? '';
            if ( ! in_array( $attribute, ['pa_formato', 'formato', 'attribute_pa_formato'], true ) ) return $args;

            if ( ! empty( $args['options'] ) ) {
                foreach ( $args['options'] as $key => $option ) {
                    if ( is_string( $option ) && 'impresas' === strtolower( $option ) ) {
                        unset( $args['options'][ $key ] );
                    } elseif ( is_object( $option ) && isset( $option->slug ) && 'impresas' === strtolower( $option->slug ) ) {
                        unset( $args['options'][ $key ] );
                    }
                }
            }
            return $args;
        }
        
        public function set_format_default( array $defaults, $product ): array {
            $defaults['pa_formato'] = $this->is_restricted_user() ? 'digitales' : 'impresas';
            return $defaults;
        }

        public function display_digital_price_in_catalog( $price, $product ) {
            if ( is_product() ) return $price;

            $variations = $product->get_children();
            if ( empty( $variations ) ) return $price;

            update_meta_cache( 'post', $variations );

            foreach ( $variations as $var_id ) {
                $format = get_post_meta( $var_id, 'attribute_pa_formato', true )
                       ?: get_post_meta( $var_id, 'attribute_formato', true );
                if ( 'digitales' === $format ) {
                    $var_product = wc_get_product( $var_id );
                    if ( $var_product ) return $var_product->get_price_html();
                }
            }
            return $price;
        }
        
        public function autoselect_format_variation() {
            global $product;
            if ( ! $product || ! $product->is_type( 'variable' ) ) return;
            
            if ( ! $this->is_restricted_user() ) {
                return;
            }

            $target_slug = 'digitales'; 
            ?>
            <script type="text/javascript">
            (function($) {
                'use strict';
                if ( 'undefined' === typeof $ || ! $.fn ) return;
                
                $(document).ready(function() {
                    var $form = $('form.variations_form');
                    if ( ! $form.length ) return;
                    
                    $form.on('wc_variation_form woocommerce_update_variation_values', function() {
                        setTimeout(autoSelectFormatVariation, 100);
                    });
                    
                    setTimeout(autoSelectFormatVariation, 150);
                    
                    function autoSelectFormatVariation() {
                        var $select = $form.find('select[name^="attribute_pa_formato"], select[name^="attribute_formato"]');
                        if ( ! $select.length ) return;
                        
                        var targetSlug = '<?php echo esc_js($target_slug); ?>';
                        
                        if ( $select.val() === targetSlug ) {
                            hideRowAndTable($select, $form);
                            return;
                        }
                        
                        $select.val(targetSlug).trigger('change');
                        $form.trigger('check_variations');
                        
                        hideRowAndTable($select, $form);
                    }
                    
                    function hideRowAndTable($select, $form) {
                        var $row = $select.closest('tr');
                        $row.hide();
                        
                        if ( $form.find('table.variations tr:visible').length === 0 ) {
                            $form.find('.variations').fadeOut(200);
                        }
                        $form.find('.reset_variations').hide();
                    }
                });
            })(jQuery);
            </script>
            <style>
                form.variations_form .variations, form.variations_form tr {
                    transition: opacity 0.2s ease-out;
                }
                .variations_form .reset_variations {
                    display: none !important;
                    visibility: hidden !important;
                }
            </style>
            <?php
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
