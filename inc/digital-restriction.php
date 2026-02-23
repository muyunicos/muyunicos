<?php
/**
 * Muy Únicos - Digital Restriction System
 * * Sistema de restricción de contenido digital v3.3.0 (Clean + Debug Mode)
 * Propósito: Restringir productos físicos en subdominios, mostrando solo 
 * productos digitales. Optimizado para rendimiento y compatibilidad.
 * * @package GeneratePress_Child
 * @since 3.3.0
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
        
        // VARIABLE DE DIAGNÓSTICO: Cambiar a 'false' cuando termines de revisar
        private bool $debug_mode = true;
        
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
            add_action( 'wc_ajax_mu_rebuild_digital_list', [ $this, 'ajax_rebuild_indexes' ] );
            add_action( 'woocommerce_update_product', [ $this, 'schedule_rebuild' ] );
            add_action( 'admin_init', [ $this, 'ensure_indexes_exist' ], 5 );
            add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
            add_action( 'shutdown', [ $this, 'execute_scheduled_rebuild' ] );
            
            add_action( 'pre_get_posts', [ $this, 'filter_product_queries' ], 50 );
            add_action( 'template_redirect', [ $this, 'handle_redirects' ], 20 );
            add_filter( 'get_terms_args', [ $this, 'filter_category_terms' ], 10, 2 );
            add_filter( 'wp_get_nav_menu_items', [ $this, 'filter_menu_items' ], 10, 3 );
            
            add_filter( 'woocommerce_product_get_default_attributes', [ $this, 'set_format_default' ], 20, 2 );
            add_filter( 'woocommerce_variable_price_html', [ $this, 'display_digital_price_only' ], 99, 2 );
            add_filter( 'woocommerce_variable_sale_price_html', [ $this, 'display_digital_price_only' ], 99, 2 );
            add_action( 'wp_footer', [ $this, 'frontend_variation_script' ], 99 );

            // HOOK DE DIAGNÓSTICO
            if ( $this->debug_mode ) {
                add_action( 'woocommerce_before_single_product', [ $this, 'render_debug_panel' ] );
            }
        }
        
        // =====================================================================
        // HERRAMIENTA DE DIAGNÓSTICO (Visible solo para administradores)
        // =====================================================================
        public function render_debug_panel(): void {
            if ( ! current_user_can( 'administrator' ) ) return;
            global $product;
            if ( ! $product ) return;

            echo '<div style="background:#111; color:#0f0; padding:20px; margin-bottom:20px; font-family:monospace; font-size:13px; border-left:5px solid #0f0; z-index:99999; position:relative;">';
            echo '<h3 style="color:#fff; margin-top:0;">🔍 MUYU Debugger (Solo visible para ti)</h3>';
            echo '<strong>Host actual:</strong> ' . $_SERVER['HTTP_HOST'] . '<br>';
            echo '<strong>¿Es usuario restringido?</strong> ' . ($this->is_restricted_user() ? 'SÍ (Subdominio internacional)' : 'NO (Argentina)') . '<br>';
            echo '<strong>Tipo de producto detectado por WC:</strong> <span style="color:yellow; font-size:16px;">' . strtoupper($product->get_type()) . '</span><br>';
            
            if ( $product->is_type('variable') ) {
                $attrs = $product->get_variation_attributes();
                echo '<strong>Atributos registrados en este producto (Nombres exactos):</strong><br>';
                foreach ($attrs as $key => $val) {
                    echo "- <code>" . esc_html($key) . "</code><br>";
                }
                
                echo '<strong>Variaciones hijas activas (IDs):</strong> ';
                $children = $product->get_children();
                echo implode(', ', $children) . '<br>';
            } else {
                echo '<br><strong style="color:red;">⚠️ ALERTA: WooCommerce cree que esto es un producto SIMPLE.</strong><br>';
                echo 'Posibles causas:<br>';
                echo '1. El plugin "Price Based on Country" desactivó todas las variaciones porque no tienen precio en Euros (€).<br>';
                echo '2. Tienes un "Code Snippet" antiguo aún activo que está filtrando el tipo de producto.<br>';
            }
            echo '</div>';
        }

        // =====================================================================
        // HELPERS
        // =====================================================================

        public function is_restricted_user(): bool {
            $host = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) );
            $host = preg_replace( '/:\d+$/', '', $host );
            return 'muyunicos.com' !== str_replace( 'www.', '', $host );
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
            
            $digital_ids = (array) get_option( self::OPTION_PRODUCT_IDS, [] );
            if ( empty( $digital_ids ) ) {
                $this->rebuild_digital_indexes();
                $digital_ids = (array) get_option( self::OPTION_PRODUCT_IDS, [] );
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
                $target_url = ( is_product() && isset( $post->post_title ) ) ? home_url( '/?s=' . urlencode( $post->post_title ) . '&post_type=product' ) : wc_get_page_permalink( 'shop' );
            }
            
            if ( function_exists( 'insertar_prefijo_idioma' ) && function_exists( 'muyu_country_language_prefix' ) ) {
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
        
        public function set_format_default( array $defaults, $product ): array {
            $term_id = $this->is_restricted_user() ? MUYU_DIGITAL_FORMAT_ID : MUYU_PHYSICAL_FORMAT_ID;
            $term = get_term( $term_id, 'pa_formato' );
            
            if ( $term && ! is_wp_error( $term ) ) {
                $attributes = ( $product && is_a( $product, 'WC_Product_Variable' ) ) ? $product->get_variation_attributes() : [];
                
                // Limpieza: Ahora comprobamos de forma genérica cuál es el nombre real del atributo
                $key = '';
                if ( isset( $attributes['pa_formato'] ) ) $key = 'pa_formato';
                elseif ( isset( $attributes['attribute_pa_formato'] ) ) $key = 'attribute_pa_formato';
                elseif ( isset( $attributes['formato'] ) ) $key = 'formato';
                
                if ( !empty($key) ) {
                    $defaults[$key] = $term->slug;
                }
            }
            return $defaults;
        }
        
        public function display_digital_price_only( $price, $product ) {
            if ( ! $this->is_restricted_user() ) return $price;
            
            $digital_term = get_term( MUYU_DIGITAL_FORMAT_ID, 'pa_formato' );
            if ( ! $digital_term || is_wp_error( $digital_term ) ) return $price;

            $digital_slug = $digital_term->slug;
            $variations = $product->get_children();
            
            foreach ( $variations as $variation_id ) {
                $variation = wc_get_product( $variation_id );
                if ( ! $variation ) continue;
                
                $attributes = $variation->get_attributes();
                // Limpieza: Buscar el valor del formato sin importar el prefijo
                $format = $attributes['pa_formato'] ?? $attributes['attribute_pa_formato'] ?? $attributes['formato'] ?? '';
                
                if ( $format === $digital_slug ) {
                    return $variation->get_price_html();
                }
            }
            
            return $price;
        }
        
        public function frontend_variation_script(): void {
            if ( ! $this->is_restricted_user() ) return;
            
            $phys_term = get_term( MUYU_PHYSICAL_FORMAT_ID, 'pa_formato' );
            if ( ! $phys_term || is_wp_error( $phys_term ) ) return;
            
            $phys_slug = $phys_term->slug;
            ?>
            <style>
                select[name^="attribute_pa_formato"] option[value="<?php echo esc_attr($phys_slug); ?>"],
                select[name^="attribute_formato"] option[value="<?php echo esc_attr($phys_slug); ?>"] {
                    display: none !important;
                }
                .variations_form .reset_variations {
                    display: none !important;
                    visibility: hidden !important;
                }
            </style>
            <script type="text/javascript">
                document.addEventListener('DOMContentLoaded', function() {
                    if (typeof jQuery !== 'undefined') {
                        jQuery(document).on('woocommerce_update_variation_values', function(e) {
                            var $form = jQuery(e.target);
                            var $select = $form.find('select[name^="attribute_pa_formato"], select[name^="attribute_formato"]');
                            
                            if ($select.length) {
                                var $physOption = $select.find('option[value="<?php echo esc_js($phys_slug); ?>"]');
                                if ($physOption.length) {
                                    $physOption.remove();
                                    $select.trigger('change');
                                }
                                
                                if ($select.find('option').length <= 2) {
                                    $select.closest('tr').hide();
                                    if ($form.find('table.variations tbody tr:visible').length === 0) {
                                        $form.find('table.variations').hide();
                                    }
                                }
                            }
                        });
                    }
                });
            </script>
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