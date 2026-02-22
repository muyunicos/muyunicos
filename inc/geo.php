<?php
/**
 * Muy Únicos - Sistema Multi-País y Modal de Sugerencia
 * 
 * Incluye:
 * - Funciones auxiliares multi-país (CORE)
 * - Auto-detección de país por dominio (Esencial para "WooCommerce Price Based on Country")
 * - Shortcode país de facturación
 * - Modal de sugerencia de país (geolocalización)
 * - Selector de país en header
 * - Digital_Restriction_System (lógica de restricción)
 * 
 * @package GeneratePress_Child
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================
// FUNCIONES AUXILIARES MULTI-PAÍS (CORE)
// ============================================

if ( ! function_exists( 'muyu_get_main_domain' ) ) {
    /**
     * Obtiene el dominio principal (cacheado)
     * 
     * @return string Dominio principal (ej: 'muyunicos.com')
     */
    function muyu_get_main_domain() {
        static $main_domain = null;
        
        if ( $main_domain === null ) {
            $siteurl = get_option( 'siteurl' );
            $main_domain = wp_parse_url( $siteurl, PHP_URL_HOST );
            if ( empty( $main_domain ) ) $main_domain = 'muyunicos.com';
        }
        
        return $main_domain;
    }
}

if ( ! function_exists( 'muyu_country_language_prefix' ) ) {
    /**
     * Retorna el prefijo de idioma para un código de país
     * 
     * @param string $code Código de país (BR, US, etc.)
     * @return string Prefijo de idioma ('/pt', '/en', '')
     */
    function muyu_country_language_prefix( $code ) {
        $prefixes = [
            'BR' => '/pt',
            'US' => '/en'
        ];
        
        return $prefixes[ $code ] ?? '';
    }
}

if ( ! function_exists( 'muyu_get_countries_data' ) ) {
    /**
     * Retorna el array completo de configuración de países
     * 
     * @return array Array asociativo con configuración de cada país
     */
    function muyu_get_countries_data() {
        return [
            'MX' => [ 'name' => 'México',        'host' => 'mexico.muyunicos.com', 'flag' => 'mx', 'lang' => 'es' ],
            'CO' => [ 'name' => 'Colombia',      'host' => 'co.muyunicos.com',     'flag' => 'co', 'lang' => 'es' ],
            'ES' => [ 'name' => 'España',        'host' => 'es.muyunicos.com',     'flag' => 'es', 'lang' => 'es' ],
            'CL' => [ 'name' => 'Chile',         'host' => 'cl.muyunicos.com',     'flag' => 'cl', 'lang' => 'es' ],
            'PE' => [ 'name' => 'Perú',          'host' => 'pe.muyunicos.com',     'flag' => 'pe', 'lang' => 'es' ],
            'BR' => [ 'name' => 'Brasil',        'host' => 'br.muyunicos.com',     'flag' => 'br', 'lang' => 'pt' ],
            'EC' => [ 'name' => 'Ecuador',       'host' => 'ec.muyunicos.com',     'flag' => 'ec', 'lang' => 'es' ],
            'AR' => [ 'name' => 'Argentina',     'host' => 'muyunicos.com',        'flag' => 'ar', 'lang' => 'es' ],
            'US' => [ 'name' => 'United States', 'host' => 'us.muyunicos.com',     'flag' => 'us', 'lang' => 'en' ],
            'CR' => [ 'name' => 'Costa Rica',    'host' => 'cr.muyunicos.com',     'flag' => 'cr', 'lang' => 'es' ],
        ];
    }
}

if ( ! function_exists( 'muyu_get_current_country_from_subdomain' ) ) {
    /**
     * Detecta el código de país según el subdominio actual
     * 
     * @return string Código de país (AR por defecto)
     */
    function muyu_get_current_country_from_subdomain() {
        // Eliminar puerto si existe (ej: :80, :443, :8080)
        $current_host = preg_replace( '/:\d+$/', '', trim( $_SERVER['HTTP_HOST'] ?? '' ) );
        $main_domain = muyu_get_main_domain();
        
        // Si es el dominio principal o no contiene el dominio base, es AR
        if ( $current_host === $main_domain || strpos( $current_host, $main_domain ) === false ) {
            return 'AR';
        }
        
        $subdomain = str_replace( '.' . $main_domain, '', $current_host );
        $subdomain = strtolower( $subdomain );
        
        // Construir mapa optimizado solo una vez por petición
        static $subdomain_map = null;
        if ( $subdomain_map === null ) {
            $subdomain_map = [];
            foreach ( muyu_get_countries_data() as $code => $data ) {
                if ( $data['host'] === $main_domain ) continue;
                $host_parts = explode( '.', $data['host'] );
                $subdomain_map[ strtolower( $host_parts[0] ) ] = $code;
            }
            // Alias manuales históricos (compatibilidad hacia atrás)
            $subdomain_map['mexico'] = 'MX';
        }
        
        return $subdomain_map[ $subdomain ] ?? 'AR';
    }
}

if ( ! function_exists( 'muyu_clean_uri' ) ) {
    /**
     * Normaliza una URI agregando el prefijo de idioma si corresponde
     * 
     * @param string $prefix Prefijo de idioma ('/pt', '/en', '')
     * @param string $uri URI a normalizar
     * @return string URI normalizada
     */
    function muyu_clean_uri( $prefix, $uri ) {
        $uri = '/' . ltrim( preg_replace( '#/+#', '/', $uri ), '/' );
        if ( $prefix && strpos( $uri, $prefix ) === 0 ) return $uri;
        return $prefix . $uri;
    }
}

if ( ! function_exists( 'muyu_country_modal_text' ) ) {
    /**
     * Helper para obtener textos localizados del modal de país
     * 
     * @param string $code Código de país
     * @param string $type Tipo de texto ('question', 'stay')
     * @return string Texto localizado
     */
    function muyu_country_modal_text( $code, $type = 'question' ) {
        $text = [
            'pt' => [
                'question' => 'Você deseja comprar do %s?',
                'stay' => 'Permanecer neste site e não perguntar novamente'
            ],
            'en' => [
                'question' => 'Do you want to shop from %s?',
                'stay' => 'Stay on this site and do not ask again'
            ],
            'es' => [
                'question' => '¿Quieres comprar desde %s?',
                'stay' => 'Quedarme en este sitio'
            ]
        ];
        
        $countries = muyu_get_countries_data();
        $lang = $countries[ $code ]['lang'] ?? 'es';
        
        return $text[ $lang ][ $type ] ?? $text['es'][ $type ];
    }
}

// ============================================
// AUTO-DETECCIÓN DE PAÍS POR DOMINIO
// ============================================

if ( ! function_exists( 'mu_auto_detect_country_by_domain' ) ) {
    /**
     * Detecta automáticamente el país según el dominio y actualiza WC Customer.
     * Esencial para el funcionamiento de "WooCommerce Price Based on Country".
     */
    function mu_auto_detect_country_by_domain() {
        if ( is_admin() || ! function_exists( 'WC' ) || ! WC()->customer ) return;
        
        // Obtener el host actual limpiando el puerto
        $current_host = preg_replace( '/:\d+$/', '', trim( $_SERVER['HTTP_HOST'] ?? '' ) );
        
        $host_to_country_map = [];
        foreach ( muyu_get_countries_data() as $code => $data ) {
            $host_to_country_map[ $data['host'] ] = $code;
        }
        
        if ( ! array_key_exists( $current_host, $host_to_country_map ) ) return;
        
        $detected_country_code = $host_to_country_map[ $current_host ];
        if ( $detected_country_code === WC()->customer->get_billing_country() ) return;
        
        // Inicializar sesión si no existe (requerido para invitados)
        if ( WC()->session && ! WC()->session->has_session() ) {
            WC()->session->set_customer_session_cookie( true );
        }
        
        // Actualizar país del cliente
        WC()->customer->set_billing_country( $detected_country_code );
        WC()->customer->set_shipping_country( $detected_country_code );
        WC()->customer->save();
    }
}
add_action( 'template_redirect', 'mu_auto_detect_country_by_domain', 1 );

// ============================================
// SHORTCODE PAÍS DE FACTURACIÓN
// ============================================

if ( ! function_exists( 'mostrar_nombre_pais_facturacion' ) ) {
    /**
     * Shortcode que muestra el nombre del país de facturación actual
     * 
     * @return string Nombre del país o string vacío
     */
    function mostrar_nombre_pais_facturacion() {
        if ( ! function_exists( 'WC' ) || ! WC()->customer ) return '';
        
        $country_code = WC()->customer->get_billing_country();
        if ( empty( $country_code ) ) return '';
        
        $countries = WC()->countries->get_countries();
        return isset( $countries[ $country_code ] ) ? esc_html( $countries[ $country_code ] ) : '';
    }
}
add_shortcode( 'mi_pais_facturacion', 'mostrar_nombre_pais_facturacion' );

// ============================================
// MODAL DE SUGERENCIA DE PAÍS
// ============================================

if ( ! function_exists( 'mu_should_show_country_modal' ) ) {
    /**
     * Determina si debe mostrarse el modal de sugerencia de país
     * 
     * @return bool True si debe mostrarse
     */
    function mu_should_show_country_modal() {
        $current_domain = preg_replace( '/:\d+$/', '', trim( $_SERVER['HTTP_HOST'] ?? '' ) );
        
        // Si ya eligió quedarse, no mostrar
        if ( isset( $_COOKIE['muyu_stay_here'] ) && $_COOKIE['muyu_stay_here'] == $current_domain ) {
            return false;
        }
        
        // Obtener país del usuario por geolocalización
        $user_country = null;
        if ( function_exists( 'wc_get_customer_geolocation' ) && function_exists( 'WC' ) && WC()->customer ) {
            $geo = wc_get_customer_geolocation();
            $user_country = ! empty( $geo['country'] ) ? strtoupper( $geo['country'] ) : null;
        }
        
        if ( ! $user_country ) return false;
        
        // Verificar si el país del usuario está configurado
        $countries = muyu_get_countries_data();
        if ( ! isset( $countries[ $user_country ] ) ) return false;
        
        // Si ya está en el dominio correcto, no mostrar
        $target = $countries[ $user_country ];
        if ( $target['host'] === $current_domain ) return false;
        
        return true;
    }
}

/**
 * Enqueue condicional del modal de país
 */
function mu_country_modal_enqueue() {
    if ( is_admin() || ! mu_should_show_country_modal() ) return;
    
    $theme_version = wp_get_theme()->get( 'Version' );
    $theme_uri = get_stylesheet_directory_uri();
    
    wp_enqueue_style( 'mu-country-modal', $theme_uri . '/css/components/country-modal.css', [ 'mu-base' ], $theme_version );
    wp_enqueue_script( 'mu-country-modal-js', $theme_uri . '/js/country-modal.js', [], $theme_version, true );
}
add_action( 'wp_enqueue_scripts', 'mu_country_modal_enqueue', 30 );

/**
 * Renderiza el HTML del modal de país en wp_footer
 */
function mu_country_modal_html() {
    if ( is_admin() || ! mu_should_show_country_modal() ) return;
    
    $countries = muyu_get_countries_data();
    $request_uri = $_SERVER['REQUEST_URI'] ?? '/';
    $current_domain = preg_replace( '/:\d+$/', '', trim( $_SERVER['HTTP_HOST'] ?? '' ) );
    
    $geo = wc_get_customer_geolocation();
    $user_country = ! empty( $geo['country'] ) ? strtoupper( $geo['country'] ) : null;
    
    if ( ! $user_country || ! isset( $countries[ $user_country ] ) ) return;
    
    $target = $countries[ $user_country ];
    $prefix = muyu_country_language_prefix( $user_country );
    $final_request = muyu_clean_uri( $prefix, $request_uri );
    $target_url = 'https://' . rtrim( $target['host'], '/' ) . $final_request;
    
    $modal_question = sprintf( muyu_country_modal_text( $user_country, 'question' ), $target['name'] );
    $modal_stay = muyu_country_modal_text( $user_country, 'stay' );
    $flag_url = 'https://flagcdn.com/w40/' . esc_attr( $target['flag'] ) . '.png';
    ?>
    <div id="muyu-country-modal-overlay" data-current-domain="<?php echo esc_attr( $current_domain ); ?>">
        <div id="muyu-country-modal">
            <button id="muyu-country-close" title="Cerrar" aria-label="Cerrar">&times;</button>
            <div>
                <div>
                    <?php echo esc_html( $modal_question ); ?>
                    <img src="<?php echo esc_attr( $flag_url ); ?>" alt="<?php echo esc_attr( $target['name'] ); ?>" />
                </div>
                <a href="<?php echo esc_url( $target_url ); ?>" rel="nofollow" class="muyu-country-btn">
                    Ir a Muy Únicos <?php echo esc_html( $target['name'] ); ?>
                </a>
            </div>
            <button id="muyu-country-stay" class="muyu-country-stay-btn">
                <?php echo esc_html( $modal_stay ); ?>
            </button>
        </div>
    </div>
    <?php
}
add_action( 'wp_footer', 'mu_country_modal_html', 100 );

// ============================================
// SELECTOR DE PAÍS EN HEADER
// ============================================

if ( ! function_exists( 'render_country_redirect_selector' ) ) {
    /**
     * Renderiza el selector de país con banderas
     * 
     * @return string HTML del selector
     */
    function render_country_redirect_selector() {
        if ( ! function_exists( 'WC' ) || ! WC()->customer ) return '';
        
        $countries_data = muyu_get_countries_data();
        $current_country_code = WC()->customer->get_billing_country() ?: 'AR';
        
        if ( ! isset( $countries_data[ $current_country_code ] ) ) $current_country_code = 'AR';
        
        $current_country_data = $countries_data[ $current_country_code ];
        $request_uri = $_SERVER['REQUEST_URI'] ?? '/';
        $scheme = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) ? 'https' : 'http';

        ob_start();
        ?>
        <div id="country-redirect-selector" class="country-redirect-container">
            <div class="country-selector-trigger" title="Cambiar de País" tabindex="0" role="button" aria-haspopup="true" aria-expanded="false">
                <img src="https://flagcdn.com/w40/<?php echo esc_attr( $current_country_data['flag'] ); ?>.png" alt="<?php echo esc_attr( $current_country_data['name'] ); ?>" />
            </div>
            <ul class="country-selector-dropdown" aria-label="Cambiar país">
                <div class="dropdown-header"><p>Selecciona tu país</p></div>
                <?php foreach ( $countries_data as $code => $country ) : ?>
                    <?php if ( $code !== $current_country_code ) : ?>
                        <?php
                        $prefix = muyu_country_language_prefix( $code );
                        $target_url = $scheme . '://' . rtrim( $country['host'], '/' ) . muyu_clean_uri( $prefix, $request_uri );
                        ?>
                        <li>
                            <a href="<?php echo esc_url( $target_url ); ?>">
                                <img src="https://flagcdn.com/w40/<?php echo esc_attr( $country['flag'] ); ?>.png" alt="<?php echo esc_attr( $country['name'] ); ?>" />
                                <span><?php echo esc_html( $country['name'] ); ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }
}
add_shortcode( 'country_redirect_selector', 'render_country_redirect_selector' );

/**
 * Inyecta el selector de país en el header
 */
function mu_inject_country_selector_header() {
    if ( ! function_exists( 'render_country_redirect_selector' ) ) return;
    ?>
    <div class="mu-header-country-item">
        <?php echo render_country_redirect_selector(); ?>
    </div>
    <?php
}
add_action( 'generate_header', 'mu_inject_country_selector_header', 1 );

// ============================================
// DIGITAL RESTRICTION SYSTEM v2.2
// ============================================

if ( ! class_exists( 'MUYU_Digital_Restriction_System' ) ) {
    /**
     * Sistema de Restricción de Contenido Digital
     * 
     * Gestiona la visibilidad de productos digitales vs físicos
     * según el país/subdominio del usuario.
     * 
     * @since 2.2.0
     */
    class MUYU_Digital_Restriction_System {
        private static $instance = null;
        private $cache = [];
        
        const OPTION_PRODUCT_IDS = 'muyu_digital_product_ids';
        const OPTION_CATEGORY_IDS = 'muyu_digital_category_ids';
        const OPTION_TAG_IDS = 'muyu_digital_tag_ids';
        const OPTION_REDIRECT_MAP = 'muyu_phys_to_dig_map';
        const OPTION_LAST_UPDATE = 'muyu_digital_list_updated';
        const TRANSIENT_REBUILD = 'muyu_rebuild_scheduled';
        const PHYSICAL_FORMAT_ID = 112;
        const DIGITAL_FORMAT_ID = 111;
        
        public static function get_instance() {
            if ( null === self::$instance ) self::$instance = new self();
            return self::$instance;
        }
        
        private function __construct() {
            $this->init_hooks();
        }
        
        private function init_hooks() {
            add_action( 'wp_ajax_muyu_rebuild_digital_list', [ $this, 'ajax_rebuild_indexes' ] );
            add_action( 'woocommerce_update_product', [ $this, 'schedule_rebuild' ], 10, 1 );
            add_action( 'admin_init', [ $this, 'ensure_indexes_exist' ], 5 );
            add_action( 'admin_head-edit.php', [ $this, 'add_rebuild_button' ] );
            add_action( 'pre_get_posts', [ $this, 'filter_product_queries' ], 50 );
            add_action( 'template_redirect', [ $this, 'handle_redirects' ], 20 );
            add_action( 'wp', [ $this, 'init_frontend_filters' ], 5 );
            add_filter( 'woocommerce_variation_is_visible', [ $this, 'hide_physical_variation' ], 10, 4 );
            add_filter( 'woocommerce_dropdown_variation_attribute_options_args', [ $this, 'clean_variation_dropdown' ], 10, 1 );
            add_filter( 'woocommerce_variation_prices', [ $this, 'filter_variation_prices' ], 10, 3 );
            add_filter( 'woocommerce_product_get_default_attributes', [ $this, 'set_format_default' ], 20, 2 );
            add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'autoselect_format_variation' ], 5 );
        }
        
        public function is_restricted_user() {
            if ( isset( $this->cache['is_restricted'] ) ) return $this->cache['is_restricted'];
            if ( current_user_can( 'manage_woocommerce' ) || is_admin() ) return ( $this->cache['is_restricted'] = false );
            
            $main_domain = function_exists( 'muyu_get_main_domain' ) ? muyu_get_main_domain() : 'muyunicos.com';
            $host = preg_replace( '/:\d+$/', '', trim( $_SERVER['HTTP_HOST'] ?? '' ) );
            $host = str_replace( 'www.', '', $host );
            
            return ( $this->cache['is_restricted'] = ( $main_domain !== $host ) );
        }
        
        public function get_user_country_code() {
            if ( isset( $this->cache['country_code'] ) ) return $this->cache['country_code'];
            
            if ( function_exists( 'muyu_get_current_country_from_subdomain' ) ) {
                $code = muyu_get_current_country_from_subdomain();
            } else {
                $code = 'AR';
            }
            
            return ( $this->cache['country_code'] = $code );
        }
        
        public function rebuild_digital_indexes() {
            global $wpdb;
            
            // 1. Obtener IDs de productos digitales
            $digital_product_ids = $this->get_digital_product_ids();
            
            if ( empty( $digital_product_ids ) ) {
                $this->save_empty_indexes();
                return 0;
            }
            
            // 2. Obtener términos (categorías y tags)
            list( $category_ids, $tag_ids ) = $this->get_product_terms( $digital_product_ids );
            
            // 3. Expandir jerarquía de categorías
            $category_ids = $this->expand_category_hierarchy( $category_ids );
            
            // 4. Construir mapa de redirección
            $redirect_map = $this->build_redirect_map( $digital_product_ids );
            
            // 5. Guardar índices
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
            
            $ids_string = implode( ',', $product_ids );
            
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
            
            if ( empty( $digital_product_ids ) ) {
                return [];
            }
            
            $ids_string = implode( ',', $digital_product_ids );
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

        public function ajax_rebuild_indexes() {
            check_ajax_referer( 'muyu-rebuild-nonce', 'nonce' );
            
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error( 'Permisos insuficientes' );
            }
            
            $count = $this->rebuild_digital_indexes();
            
            wp_send_json_success( sprintf( 
                'Índice reconstruido correctamente. Total productos digitales: %d', 
                $count 
            ) );
        }

        public function schedule_rebuild( $product_id ) {
            if ( get_transient( self::TRANSIENT_REBUILD ) ) {
                return;
            }
            
            set_transient( self::TRANSIENT_REBUILD, true, 120 );
            
            add_action( 'shutdown', [ $this, 'rebuild_digital_indexes' ] );
        }

        public function ensure_indexes_exist() {
            if ( false === get_option( self::OPTION_PRODUCT_IDS ) ) {
                $this->rebuild_digital_indexes();
            }
        }

        public function filter_product_queries( $query ) {
            // Solo queries principales del frontend
            if ( is_admin() || ! $query->is_main_query() ) {
                return;
            }
            
            // No filtrar páginas de producto individual
            if ( $query->is_product() || ( $query->is_singular() && 'product' === $query->get( 'post_type' ) ) ) {
                return;
            }
            
            // Detectar queries de tienda
            $is_shop_query = (
                ( function_exists( 'is_shop' ) && is_shop() ) ||
                ( function_exists( 'is_product_category' ) && is_product_category() ) ||
                ( function_exists( 'is_product_tag' ) && is_product_tag() ) ||
                is_search() ||
                'product' === $query->get( 'post_type' )
            );
            
            if ( ! $is_shop_query ) {
                return;
            }
            
            // Aplicar restricción para usuarios de subdominios
            if ( $this->is_restricted_user() ) {
                $digital_ids = get_option( self::OPTION_PRODUCT_IDS, [] );
                
                $query->set( 'post__in', ! empty( $digital_ids ) ? $digital_ids : [ 0 ] );
            }
        }

        public function handle_redirects() {
            if ( is_admin() || ! $this->is_restricted_user() ) {
                return;
            }
            
            if ( ! is_product() && ! is_product_category() && ! is_product_tag() ) {
                return;
            }
            
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
            
            if ( ! $queried_object || in_array( $queried_object->term_id, $digital_cats, true ) ) {
                return [ false, '' ];
            }
            
            // Buscar categoría padre digital
            $parent_id = $queried_object->parent;
            while ( $parent_id ) {
                if ( in_array( $parent_id, $digital_cats, true ) ) {
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
            
            if ( ! $queried_object || in_array( $queried_object->term_id, $digital_tags, true ) ) {
                return [ false, '' ];
            }
            
            return [ true, '' ];
        }

        private function handle_product_redirect() {
            global $post;
            
            $digital_ids = get_option( self::OPTION_PRODUCT_IDS, [] );
            
            if ( ! $post || in_array( $post->ID, $digital_ids, true ) ) {
                return [ false, '' ];
            }
            
            // Buscar en mapa de redirección
            $redirect_map = get_option( self::OPTION_REDIRECT_MAP, [] );
            if ( isset( $redirect_map[ $post->ID ] ) ) {
                return [ true, get_permalink( $redirect_map[ $post->ID ] ) ];
            }
            
            // Buscar categoría digital del producto
            $target_url = $this->find_digital_category_for_product( $post->ID );
            
            return [ true, $target_url ];
        }

        private function find_digital_category_for_product( $product_id ) {
            $digital_cats = get_option( self::OPTION_CATEGORY_IDS, [] );
            $product_cats = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'ids' ] );
            
            if ( empty( $product_cats ) || is_wp_error( $product_cats ) ) {
                return '';
            }
            
            // Buscar categoría directa
            foreach ( $product_cats as $cat_id ) {
                if ( in_array( $cat_id, $digital_cats, true ) ) {
                    return get_term_link( $cat_id, 'product_cat' );
                }
            }
            
            // Buscar en ancestros
            foreach ( $product_cats as $cat_id ) {
                $ancestors = get_ancestors( $cat_id, 'product_cat', 'taxonomy' );
                foreach ( $ancestors as $ancestor_id ) {
                    if ( in_array( $ancestor_id, $digital_cats, true ) ) {
                        return get_term_link( $ancestor_id, 'product_cat' );
                    }
                }
            }
            
            return '';
        }

        private function execute_redirect( $target_url ) {
            global $post;
            
            // Fallback
            if ( empty( $target_url ) || is_wp_error( $target_url ) ) {
                if ( is_product() && isset( $post->post_title ) ) {
                    $target_url = home_url( '/?s=' . urlencode( $post->post_title ) . '&post_type=product' );
                } else {
                    $target_url = wc_get_page_permalink( 'shop' );
                }
            }
            
            // Agregar prefijo de idioma si existe
            if ( function_exists( 'insertar_prefijo_idioma' ) && function_exists( 'muyu_country_language_prefix' ) ) {
                $prefix = muyu_country_language_prefix( $this->get_user_country_code() );
                if ( $prefix ) {
                    $target_url = insertar_prefijo_idioma( $target_url, $prefix );
                }
            }
            
            wp_redirect( $target_url, 302 );
            exit;
        }

        public function init_frontend_filters() {
            add_filter( 'get_terms_args', [ $this, 'filter_category_terms' ], 10, 2 );
            add_filter( 'wp_get_nav_menu_items', [ $this, 'filter_menu_items' ], 10, 3 );
        }

        public function filter_category_terms( $args, $taxonomies ) {
            // No filtrar en admin
            if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX && is_user_logged_in() ) ) {
                return $args;
            }
            
            if ( ! in_array( 'product_cat', (array) $taxonomies, true ) || ! $this->is_restricted_user() ) {
                return $args;
            }
            
            $digital_cat_ids = get_option( self::OPTION_CATEGORY_IDS, [] );
            
            if ( ! empty( $args['include'] ) ) {
                $current = array_map( 'intval', is_array( $args['include'] ) ? $args['include'] : explode( ',', $args['include'] ) );
                $args['include'] = array_intersect( $current, $digital_cat_ids );
            } else {
                $args['include'] = empty( $digital_cat_ids ) ? [ 0 ] : $digital_cat_ids;
            }
            
            return $args;
        }

        public function filter_menu_items( $items, $menu, $args ) {
            if ( is_admin() || ! $this->is_restricted_user() ) {
                return $items;
            }
            
            $digital_cat_ids = get_option( self::OPTION_CATEGORY_IDS, [] );
            
            return array_filter( $items, function( $item ) use ( $digital_cat_ids ) {
                if ( isset( $item->object ) && 'product_cat' === $item->object ) {
                    return in_array( (int) $item->object_id, $digital_cat_ids, true );
                }
                return true;
            });
        }

        public function hide_physical_variation( $visible, $variation_id, $product_id, $variation ) {
            if ( ! $visible || ! $this->is_restricted_user() ) {
                return $visible;
            }
            
            $attributes = $variation->get_attributes();
            $physical_term = get_term( self::PHYSICAL_FORMAT_ID, 'pa_formato' );
            
            if ( $physical_term && ! is_wp_error( $physical_term ) ) {
                if ( isset( $attributes['pa_formato'] ) && $attributes['pa_formato'] === $physical_term->slug ) {
                    return false;
                }
            }
            
            return $visible;
        }

        public function clean_variation_dropdown( $args ) {
            if ( ! $this->is_restricted_user() || ! isset( $args['attribute'] ) || 'pa_formato' !== $args['attribute'] ) {
                return $args;
            }
            
            if ( empty( $args['options'] ) ) {
                return $args;
            }
            
            $physical_term = get_term( self::PHYSICAL_FORMAT_ID, 'pa_formato' );
            
            if ( ! $physical_term || is_wp_error( $physical_term ) ) {
                return $args;
            }
            
            foreach ( $args['options'] as $key => $option ) {
                if ( ( is_object( $option ) && isset( $option->term_id ) && $option->term_id == self::PHYSICAL_FORMAT_ID ) ||
                     ( is_string( $option ) && $option === $physical_term->slug ) ) {
                    unset( $args['options'][ $key ] );
                }
            }
            
            return $args;
        }

        public function filter_variation_prices( $prices_array, $product, $for_display ) {
            if ( ! $this->is_restricted_user() || empty( $prices_array['price'] ) ) {
                return $prices_array;
            }
            
            $physical_term = get_term( self::PHYSICAL_FORMAT_ID, 'pa_formato' );
            
            if ( ! $physical_term || is_wp_error( $physical_term ) ) {
                return $prices_array;
            }
            
            foreach ( $prices_array['price'] as $variation_id => $amount ) {
                $format_slug = get_post_meta( $variation_id, 'attribute_pa_formato', true );
                
                if ( $format_slug === $physical_term->slug ) {
                    unset( $prices_array['price'][ $variation_id ] );
                    unset( $prices_array['regular_price'][ $variation_id ] );
                    unset( $prices_array['sale_price'][ $variation_id ] );
                }
            }
            
            return $prices_array;
        }

        public function set_format_default( $defaults, $product ) {
            $is_restricted = $this->is_restricted_user();
            $country       = $this->get_user_country_code();
            
            if ( $is_restricted ) {
                $term_id = self::DIGITAL_FORMAT_ID;
            } elseif ( 'AR' === $country ) {
                $term_id = self::PHYSICAL_FORMAT_ID;
            } else {
                return $defaults;
            }
            
            $term = get_term( $term_id, 'pa_formato' );
            
            if ( $term && ! is_wp_error( $term ) ) {
                $defaults['pa_formato'] = $term->slug;
            }
            
            return $defaults;
        }

        public function autoselect_format_variation() {
            global $product;
            if ( ! $product || ! $product->is_type( 'variable' ) ) return;

            $is_restricted  = $this->is_restricted_user();
            $country        = $this->get_user_country_code();

            if ( $is_restricted ) {
                $target_term_id = self::DIGITAL_FORMAT_ID;
                $hide_row       = true;
            } elseif ( 'AR' === $country ) {
                $target_term_id = self::PHYSICAL_FORMAT_ID;
                $hide_row       = false;
            } else {
                return;
            }

            $attributes = $product->get_variation_attributes();
            if ( ! isset( $attributes['pa_formato'] ) ) return;

            $target_term = get_term( $target_term_id, 'pa_formato' );
            if ( ! $target_term || is_wp_error( $target_term ) ) return;
            if ( ! in_array( $target_term->slug, $attributes['pa_formato'], true ) ) return;

            // Data bridge para js/shop.js — sin inline JS/CSS
            printf(
                '<span id="mu-format-autoselect-data" style="display:none" data-target-slug="%s" data-hide-row="%s"></span>',
                esc_attr( $target_term->slug ),
                $hide_row ? 'true' : 'false'
            );
        }

        public function add_rebuild_button() {
            global $typenow;
            if ( 'product' !== $typenow ) return;
            
            $nonce     = wp_create_nonce( 'muyu-rebuild-nonce' );
            $theme_uri = get_stylesheet_directory_uri();
            $ver       = wp_get_theme()->get( 'Version' );

            wp_enqueue_style(  'mu-admin', $theme_uri . '/css/admin.css', [], $ver );
            wp_enqueue_script( 'mu-admin-js', $theme_uri . '/js/admin.js', [ 'jquery' ], $ver, true );
            ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var lastBtn = document.querySelectorAll('.page-title-action');
                if ( lastBtn.length ) {
                    var btn = document.createElement('button');
                    btn.id = 'muyu-rebuild';
                    btn.className = 'page-title-action';
                    btn.dataset.nonce = '<?php echo esc_js( $nonce ); ?>';
                    btn.textContent = '⚡ Reindexar Digitales';
                    lastBtn[lastBtn.length - 1].after(btn);
                }
            });
            </script>
            <?php
        }
    }
}

if ( ! function_exists( 'muyu_digital_restriction_init' ) ) {
    function muyu_digital_restriction_init() {
        return MUYU_Digital_Restriction_System::get_instance();
    }
}
// En child theme el hook apropiado es after_setup_theme
add_action( 'after_setup_theme', 'muyu_digital_restriction_init', 5 );

if ( ! function_exists( 'muyu_is_restricted_user' ) ) {
    function muyu_is_restricted_user() {
        return muyu_digital_restriction_init()->is_restricted_user();
    }
}

if ( ! function_exists( 'muyu_get_user_country_code' ) ) {
    function muyu_get_user_country_code() {
        return muyu_digital_restriction_init()->get_user_country_code();
    }
}

if ( ! function_exists( 'muyu_rebuild_digital_indexes_optimized' ) ) {
    function muyu_rebuild_digital_indexes_optimized() {
        return muyu_digital_restriction_init()->rebuild_digital_indexes();
    }
}
