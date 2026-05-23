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
     */
    function muyu_get_main_domain() {
        static $main_domain = null;
        
        if ( $main_domain === null ) {
            $host = preg_replace( '/:\d+$/', '', trim( $_SERVER['HTTP_HOST'] ?? '' ) );
            $host = str_replace( 'www.', '', $host );
            
            $known_subs = ['mexico.', 'co.', 'es.', 'cl.', 'pe.', 'br.', 'ec.', 'us.', 'cr.'];
            
            foreach ( $known_subs as $sub ) {
                if ( strpos( $host, $sub ) === 0 ) {
                    $main_domain = substr( $host, strlen( $sub ) );
                    return $main_domain;
                }
            }
            
            $main_domain = $host;
            if ( empty( $main_domain ) ) {
                $main_domain = 'muyunicos.com';
            }
        }
        
        return $main_domain;
    }
}

if ( ! function_exists( 'muyu_country_language_prefix' ) ) {
    function muyu_country_language_prefix( $code ) {
        $prefixes = [
            'BR' => '/pt',
            'US' => '/en'
        ];
        return $prefixes[ $code ] ?? '';
    }
}

if ( ! function_exists( 'muyu_get_countries_data' ) ) {
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
    function muyu_get_current_country_from_subdomain() {
        $current_host = preg_replace( '/:\d+$/', '', trim( $_SERVER['HTTP_HOST'] ?? '' ) );
        $current_host = str_replace( 'www.', '', $current_host );
        $main_domain = muyu_get_main_domain();
        
        if ( $current_host === $main_domain ) {
            return 'AR';
        }
        
        $subdomain = str_replace( '.' . $main_domain, '', $current_host );
        $subdomain = strtolower( $subdomain );
        
        static $subdomain_map = null;
        if ( $subdomain_map === null ) {
            $subdomain_map = [];
            foreach ( muyu_get_countries_data() as $code => $data ) {
                $host_parts = explode( '.', $data['host'] );
                if ( $host_parts[0] !== 'muyunicos' ) {
                    $subdomain_map[ strtolower( $host_parts[0] ) ] = $code;
                }
            }
            $subdomain_map['mexico'] = 'MX';
        }
        
        return $subdomain_map[ $subdomain ] ?? 'AR';
    }
}

if ( ! function_exists( 'muyu_clean_uri' ) ) {
    function muyu_clean_uri( $prefix, $uri ) {
        $uri = '/' . ltrim( preg_replace( '#/+#', '/' , $uri ), '/' );
        if ( $prefix && strpos( $uri, $prefix ) === 0 ) return $uri;
        return $prefix . $uri;
    }
}

if ( ! function_exists( 'muyu_country_modal_text' ) ) {
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
// GEOLOCALIZACIÓN CACHEADA
// ============================================

if ( ! function_exists( 'muyu_get_cached_geolocation' ) ) {
    function muyu_get_cached_geolocation() {
        static $geo = null;

        if ( $geo === null ) {
            if ( ! function_exists( 'wc_get_customer_geolocation' ) ||
                 ! function_exists( 'WC' ) ||
                 ! WC()->customer ) {
                return null;
            }
            $geo = wc_get_customer_geolocation();
        }

        return $geo;
    }
}

// ============================================
// DECIMALES DE PRECIO POR PAÍS
// ============================================

if ( ! function_exists( 'mu_custom_price_decimals' ) ) {
    function mu_custom_price_decimals( $decimals ) {
        $country = muyu_get_current_country_from_subdomain();
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
    function mu_auto_detect_country_by_domain() {
        if ( is_admin() || ! function_exists( 'WC' ) || ! WC()->customer ) return;
        
        $current_host = preg_replace( '/:\d+$/', '', trim( $_SERVER['HTTP_HOST'] ?? '' ) );
        
        $host_to_country_map = [];
        foreach ( muyu_get_countries_data() as $code => $data ) {
            $host_to_country_map[ $data['host'] ] = $code;
        }
        
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
    function mu_should_show_country_modal() {
        $current_domain = preg_replace( '/:\d+$/', '', trim( $_SERVER['HTTP_HOST'] ?? '' ) );
        
        if ( isset( $_COOKIE['muyu_stay_here'] ) && $_COOKIE['muyu_stay_here'] == $current_domain ) {
            return false;
        }
        
        $geo = muyu_get_cached_geolocation();
        $user_country = ( ! empty( $geo['country'] ) ) ? strtoupper( $geo['country'] ) : null;
        
        if ( ! $user_country ) return false;
        
        $countries = muyu_get_countries_data();
        if ( ! isset( $countries[ $user_country ] ) ) return false;
        
        $target = $countries[ $user_country ];
        if ( $target['host'] === $current_domain ) return false;
        
        return true;
    }
}

if ( ! function_exists( 'mu_country_modal_enqueue' ) ) {
    function mu_country_modal_enqueue() {
        if ( is_admin() || ! mu_should_show_country_modal() ) return;
        
        $theme_version = wp_get_theme()->get( 'Version' );
        $theme_uri = get_stylesheet_directory_uri();
        
        wp_enqueue_style(
            'mu-country-modal',
            $theme_uri . '/css/components/country-modal.css',
            [ 'mu-base', 'mu-global-ui' ], // depende de global-ui.css para el modal base
            $theme_version
        );
        wp_enqueue_script( 'mu-country-modal-js', $theme_uri . '/js/country-modal.js', [], $theme_version, true );
    }
    add_action( 'wp_enqueue_scripts', 'mu_country_modal_enqueue', 30 );
}

if ( ! function_exists( 'mu_country_modal_html' ) ) {
    function mu_country_modal_html() {
        if ( is_admin() || ! mu_should_show_country_modal() ) return;
        
        $countries      = muyu_get_countries_data();
        $request_uri    = $_SERVER['REQUEST_URI'] ?? '/';
        $current_domain = preg_replace( '/:\d+$/', '', trim( $_SERVER['HTTP_HOST'] ?? '' ) );
        
        $geo          = muyu_get_cached_geolocation();
        $user_country = ( ! empty( $geo['country'] ) ) ? strtoupper( $geo['country'] ) : null;
        
        if ( ! $user_country || ! isset( $countries[ $user_country ] ) ) return;
        
        $target        = $countries[ $user_country ];
        $prefix        = muyu_country_language_prefix( $user_country );
        $final_request = muyu_clean_uri( $prefix, $request_uri );
        $target_url    = 'https://' . rtrim( $target['host'], '/' ) . $final_request;
        
        $modal_question = sprintf( muyu_country_modal_text( $user_country, 'question' ), $target['name'] );
        $modal_stay     = muyu_country_modal_text( $user_country, 'stay' );
        $flag_url       = 'https://flagcdn.com/w40/' . esc_attr( $target['flag'] ) . '.png';
        ?>
        <div
            class="mu-modal-overlay--full mu-country-modal-overlay"
            data-current-domain="<?php echo esc_attr( $current_domain ); ?>"
            role="dialog"
            aria-modal="true"
            aria-label="<?php esc_attr_e( 'Sugerencia de país', 'generatepress-child' ); ?>"
        >
            <div class="mu-modal-box mu-country-modal-box">
                <button
                    class="mu-modal-close mu-country-modal__close"
                    aria-label="<?php esc_attr_e( 'Cerrar', 'generatepress-child' ); ?>"
                ><?php echo mu_get_icon( 'close' ); ?></button>

                <div class="mu-country-modal__body">
                    <p class="mu-country-modal__question">
                        <?php echo esc_html( $modal_question ); ?>
                        <img
                            class="mu-country-modal__flag"
                            src="<?php echo esc_attr( $flag_url ); ?>"
                            alt="<?php echo esc_attr( $target['name'] ); ?>"
                            width="20"
                            height="15"
                            loading="lazy"
                        />
                    </p>
                    <a
                        href="<?php echo esc_url( $target_url ); ?>"
                        rel="nofollow"
                        class="mu-country-modal__btn-go"
                    >
                        <?php echo esc_html( 'Ir a Muy Únicos ' . $target['name'] ); ?>
                    </a>
                </div>

                <button class="mu-country-modal__btn-stay">
                    <?php echo esc_html( $modal_stay ); ?>
                </button>
            </div>
        </div>
        <?php
    }
    add_action( 'wp_footer', 'mu_country_modal_html', 100 );
}

// ============================================
// SELECTOR DE PAÍS EN HEADER
// ============================================

if ( ! function_exists( 'render_country_redirect_selector' ) ) {
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

if ( ! function_exists( 'mu_inject_country_selector_header' ) ) {
    function mu_inject_country_selector_header() {
        if ( ! function_exists( 'render_country_redirect_selector' ) ) return;
        ?>
        <div class="mu-header-country-item">
            <?php echo render_country_redirect_selector(); ?>
        </div>
        <?php
    }
    add_action( 'generate_header', 'mu_inject_country_selector_header', 1 );
}
