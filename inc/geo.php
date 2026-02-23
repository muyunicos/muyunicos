<?php
/**
 * Muy Únicos - Sistema Multi-País y Modal de Sugerencia
 * 
 * Incluye:
 * - Funciones auxiliares multi-país (CORE)
 * - Auto-detección de país por dominio (Esencial para "WooCommerce Price Based on Country")
 * - Configuración de decimales según el país
 * - Shortcode país de facturación
 * - Modal de sugerencia de país (geolocalización)
 * - Selector de país en header
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
     * Extrae de forma robusta el dominio base limpiando subdominios conocidos,
     * previniendo fallos en entornos donde siteurl es dinámico (ej: Price Based on Country).
     * 
     * @return string Dominio principal (ej: 'muyunicos.com')
     */
    function muyu_get_main_domain() {
        static $main_domain = null;
        
        if ( $main_domain === null ) {
            $host = preg_replace( '/:\d+$/', '', trim( $_SERVER['HTTP_HOST'] ?? '' ) );
            $host = str_replace( 'www.', '', $host );
            
            // Subdominios de países conocidos
            $known_subs = ['mexico.', 'co.', 'es.', 'cl.', 'pe.', 'br.', 'ec.', 'us.', 'cr.'];
            
            foreach ( $known_subs as $sub ) {
                if ( strpos( $host, $sub ) === 0 ) {
                    $main_domain = substr( $host, strlen( $sub ) );
                    return $main_domain;
                }
            }
            
            // Si no tiene prefijo conocido, el host actual es el dominio principal
            $main_domain = $host;
            if ( empty( $main_domain ) ) {
                $main_domain = 'muyunicos.com'; // fallback extremo
            }
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
        $current_host = str_replace( 'www.', '', $current_host );
        $main_domain = muyu_get_main_domain();
        
        // Si es el dominio principal, es AR
        if ( $current_host === $main_domain ) {
            return 'AR';
        }
        
        $subdomain = str_replace( '.' . $main_domain, '', $current_host );
        $subdomain = strtolower( $subdomain );
        
        // Construir mapa optimizado solo una vez por petición
        static $subdomain_map = null;
        if ( $subdomain_map === null ) {
            $subdomain_map = [];
            foreach ( muyu_get_countries_data() as $code => $data ) {
                $host_parts = explode( '.', $data['host'] );
                if ( $host_parts[0] !== 'muyunicos' ) { // Ignorar el main_domain que es 'muyunicos.com'
                    $subdomain_map[ strtolower( $host_parts[0] ) ] = $code;
                }
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
        $uri = '/' . ltrim( preg_replace( '#/+#', '/' , $uri ), '/' );
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
// DECIMALES DE PRECIO POR PAÍS
// ============================================

if ( ! function_exists( 'mu_custom_price_decimals' ) ) {
    /**
     * Ajusta el número de decimales según el país detectado por URL.
     * AR, CL y CO no usan decimales en la práctica.
     * Los demás (MX, ES, PE, BR, EC, US, CR) usan 2 decimales de forma predeterminada.
     * 
     * @param int $decimals Cantidad de decimales configurada en WooCommerce.
     * @return int Cantidad de decimales adaptada al país actual.
     */
    function mu_custom_price_decimals( $decimals ) {
        $country = muyu_get_current_country_from_subdomain();
        
        // Países que no utilizan decimales en su e-commerce
        $zero_decimals_countries = [ 'AR', 'CL', 'CO' ];
        
        if ( in_array( $country, $zero_decimals_countries, true ) ) {
            return 0;
        }
        
        return 2;
    }
}
add_filter( 'wc_get_price_decimals', 'mu_custom_price_decimals' );

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
