<?php
/**
 * Muy Únicos - Sistema Multi-País y Modal de Sugerencia
 * 
 * Incluye:
 * - Funciones auxiliares multi-país (CORE)
 * - Auto-detección de país por dominio
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
        $current_host = trim( $_SERVER['HTTP_HOST'] ?? '', ':80' );
        $main_domain = muyu_get_main_domain();
        
        // Si es el dominio principal o no contiene el dominio base, es AR
        if ( $current_host === $main_domain || strpos( $current_host, $main_domain ) === false ) {
            return 'AR';
        }
        
        $subdomain = str_replace( '.' . $main_domain, '', $current_host );
        $subdomain_map = [];
        
        foreach ( muyu_get_countries_data() as $code => $data ) {
            $host_parts = explode( '.', $data['host'] );
            $sub = strtolower( $host_parts[0] );
            if ( $data['host'] === $main_domain ) continue;
            $subdomain_map[ $sub ] = $code;
        }
        
        return $subdomain_map[ strtolower( $subdomain ) ] ?? 'AR';
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
     * Detecta automáticamente el país según el dominio y actualiza WC Customer
     */
    function mu_auto_detect_country_by_domain() {
        if ( is_admin() || ! function_exists( 'WC' ) || ! WC()->customer ) return;
        
        $host_to_country_map = [];
        foreach ( muyu_get_countries_data() as $code => $data ) {
            $host_to_country_map[ $data['host'] ] = $code;
        }
        
        $current_host = $_SERVER['HTTP_HOST'] ?? '';
        if ( ! array_key_exists( $current_host, $host_to_country_map ) ) return;
        
        $detected_country_code = $host_to_country_map[ $current_host ];
        if ( $detected_country_code === WC()->customer->get_billing_country() ) return;
        
        if ( WC()->session && ! WC()->session->has_session() ) {
            WC()->session->set_customer_session_cookie( true );
        }
        
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
        $current_domain = $_SERVER['HTTP_HOST'] ?? '';
        
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
    $current_domain = $_SERVER['HTTP_HOST'] ?? '';
    
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
            $host = isset( $_SERVER['HTTP_HOST'] ) ? str_replace( 'www.', '', trim( $_SERVER['HTTP_HOST'], ':80' ) ) : '';
            
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
        
        // ... (Resto de métodos de la clase se mantienen iguales)
        // Omitidos por brevedad, se copian tal cual del original
        
        public function rebuild_digital_indexes() { /* código original */ }
        private function get_digital_product_ids() { /* código original */ }
        private function get_product_terms( $product_ids ) { /* código original */ }
        private function expand_category_hierarchy( $category_ids ) { /* código original */ }
        private function build_redirect_map( $digital_product_ids ) { /* código original */ }
        private function save_indexes( $product_ids, $category_ids, $tag_ids, $redirect_map ) { /* código original */ }
        private function save_empty_indexes() { /* código original */ }
        public function ajax_rebuild_indexes() { /* código original */ }
        public function schedule_rebuild( $product_id ) { /* código original */ }
        public function ensure_indexes_exist() { /* código original */ }
        public function filter_product_queries( $query ) { /* código original */ }
        public function handle_redirects() { /* código original */ }
        private function handle_category_redirect() { /* código original */ }
        private function handle_tag_redirect() { /* código original */ }
        private function handle_product_redirect() { /* código original */ }
        private function find_digital_category_for_product( $product_id ) { /* código original */ }
        private function execute_redirect( $target_url ) { /* código original */ }
        public function init_frontend_filters() { /* código original */ }
        public function filter_category_terms( $args, $taxonomies ) { /* código original */ }
        public function filter_menu_items( $items, $menu, $args ) { /* código original */ }
        public function hide_physical_variation( $visible, $variation_id, $product_id, $variation ) { /* código original */ }
        public function clean_variation_dropdown( $args ) { /* código original */ }
        public function filter_variation_prices( $prices_array, $product, $for_display ) { /* código original */ }
        private function get_physical_term_slug() { /* código original */ }
        public function set_format_default( $defaults, $product ) { /* código original */ }
        public function autoselect_format_variation() { /* código original */ }
        public function add_rebuild_button() { /* código original */ }
    }
}

if ( ! function_exists( 'muyu_digital_restriction_init' ) ) {
    function muyu_digital_restriction_init() {
        return MUYU_Digital_Restriction_System::get_instance();
    }
}
add_action( 'plugins_loaded', 'muyu_digital_restriction_init', 5 );

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
