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
                'title' => '¡Olá!',
                'question' => 'Você deseja comprar do %s?',
                'stay' => 'Permanecer neste site e não perguntar novamente'
            ],
            'en' => [
                'title' => 'Hi!',
                'question' => 'Do you want to shop from %s?',
                'stay' => 'Stay on this site and do not ask again'
            ],
            'es' => [
                'title' => '¡Hola!',
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
// GEOLOCALIZACIÓN CACHEADA AGRESIVA (24+ hour TTL)
// ============================================

if ( ! function_exists( 'muyu_get_geolocation_cache_key' ) ) {
    /**
     * Genera una clave de caché única para geolocalización basada en IP
     *
     * @return string Clave de caché
     */
    function muyu_get_geolocation_cache_key() {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : 'unknown';
        return 'mu_geo_' . md5( $ip );
    }
}

if ( ! function_exists( 'muyu_get_cached_geolocation' ) ) {
    /**
     * Devuelve el resultado de wc_get_customer_geolocation() con caché agresiva.
     * ORDEN DE PRIORIDAD EMERGENCIA (para reducir DB load):
     * 1. Caché de request (static variable)
     * 2. Detección por dominio (más rápido, sin DB)
     * 3. Cookie del cliente (24+ horas)
     * 4. Transient WordPress (24+ horas)
     * 5. Llamada a wc_get_customer_geolocation() (último recurso)
     *
     * @return array|null Array con clave 'country', o null si WC no está disponible.
     */
    function muyu_get_cached_geolocation() {
        static $geo = null;

        // 1. Caché de request (ya existente)
        if ( $geo !== null ) {
            return $geo;
        }

        // 2. PRIORIDAD: Detección por dominio (sin DB)
        $country_from_domain = muyu_get_current_country_from_subdomain();
        if ( ! empty( $country_from_domain ) ) {
            $geo = [ 'country' => $country_from_domain ];
            // Hook para performance monitoring (stats en memoria, sin log)
            do_action( 'muyu_geolocation_cache_hit', 'domain' );
            return $geo;
        }

        // 3. Verificar cookie de geolocalización caché (24+ horas)
        if ( isset( $_COOKIE['muyu_geo_country'] ) && ! empty( $_COOKIE['muyu_geo_country'] ) ) {
            $geo = [ 'country' => sanitize_text_field( $_COOKIE['muyu_geo_country'] ) ];
            // Hook para performance monitoring (stats en memoria, sin log)
            do_action( 'muyu_geolocation_cache_hit', 'cookie' );
            return $geo;
        }

        // 4. Verificar transient WordPress (24+ horas)
        $cache_key = muyu_get_geolocation_cache_key();
        $cached_geo = get_transient( $cache_key );
        
        if ( $cached_geo !== false ) {
            $geo = $cached_geo;
            // Establecer cookie para futuras request
            muyu_set_geolocation_cookie( $geo['country'] );
            // Hook para performance monitoring (stats en memoria, sin log)
            do_action( 'muyu_geolocation_cache_hit', 'transient' );
            return $geo;
        }

        // 5. Llamada real a WooCommerce (último recurso)
        if ( ! function_exists( 'wc_get_customer_geolocation' ) ||
             ! function_exists( 'WC' ) ||
             ! WC()->customer ) {
            // Hook para cache miss cuando WC no está disponible
            do_action( 'muyu_geolocation_cache_miss' );
            return null;
        }

        // Hook para cache miss - estamos llamando a la DB
        do_action( 'muyu_geolocation_cache_miss' );

        if ( function_exists( 'mu_perf_log' ) ) {
            mu_perf_log( 'Geolocation cache miss - calling DB', [
                'cache_key' => $cache_key,
                'cookies' => array_keys( $_COOKIE ),
                'has_geo_cookie' => isset( $_COOKIE['muyu_geo_country'] )
            ] );
        }

        try {
            $geo = wc_get_customer_geolocation();

            // Guardar en transient (24+ horas) y cookie
            if ( ! empty( $geo['country'] ) ) {
                set_transient( $cache_key, $geo, DAY_IN_SECONDS );
                muyu_set_geolocation_cookie( $geo['country'] );
                
                if ( function_exists( 'mu_perf_log' ) ) {
                    mu_perf_log( 'Geolocation cached from DB', [
                        'country' => $geo['country'],
                        'cache_key' => $cache_key
                    ] );
                }
            }

            return $geo;
        } catch ( Exception $e ) {
            // Fallback en caso de error de DB (como los "Commands out of sync" en los logs)
            if ( function_exists( 'mu_perf_log' ) ) {
                mu_perf_log( 'Geolocation DB error, using domain fallback', [ 'error' => $e->getMessage() ] );
            }
            
            // Fallback: usar detección por dominio actual
            if ( ! empty( $country_from_domain ) ) {
                $geo = [ 'country' => $country_from_domain ];
                // Guardar en transient para evitar futuros errores
                set_transient( $cache_key, $geo, DAY_IN_SECONDS );
                muyu_set_geolocation_cookie( $country_from_domain );
                
                if ( function_exists( 'mu_perf_log' ) ) {
                    mu_perf_log( 'Geolocation fallback to domain', [ 'country' => $country_from_domain ] );
                }
                
                return $geo;
            }
            
            return null;
        }
    }
}

if ( ! function_exists( 'muyu_set_geolocation_cookie' ) ) {
    /**
     * Establece cookie de geolocalización con 24+ horas de duración
     * Configurada para funcionar en todos los subdominios
     *
     * @param string $country_code Código de país (ej: 'AR', 'MX')
     */
    function muyu_set_geolocation_cookie( $country_code ) {
        if ( headers_sent() ) {
            return;
        }

        $host = str_replace( 'www.', '', preg_replace( '/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '' ));
        $country_code = strtoupper( sanitize_text_field( $country_code ) );

        // Obtener dominio principal para cookie multi-subdominio
        $main_domain = muyu_get_main_domain();
        
        // Intentar múltiples métodos de cookie para máxima compatibilidad
        // Método 1: Cookie multi-subdominio (falla en algunos navegadores)
        $success1 = setcookie( 'muyu_geo_country', $country_code, time() + DAY_IN_SECONDS, '/', '.' . $main_domain, is_ssl(), true );
        
        // Método 2: Cookie solo para este dominio (fallback)
        if ( ! $success1 ) {
            setcookie( 'muyu_geo_country', $country_code, time() + DAY_IN_SECONDS, '/', $host, is_ssl(), true );
        }
        
        // Método 3: Cookie sin dominio específico (último fallback)
        if ( ! $success1 ) {
            setcookie( 'muyu_geo_country', $country_code, time() + DAY_IN_SECONDS, '/', '', is_ssl(), true );
        }
    }
}

if ( ! function_exists( 'muyu_refresh_geolocation' ) ) {
    /**
     * Endpoint AJAX para refrescar geolocalización manualmente
     * Útil cuando el usuario viaja o cambia de ubicación
     */
    function muyu_refresh_geolocation() {
        check_ajax_referer( 'mu-geo-refresh', 'nonce' );

        // Eliminar caches
        $cache_key = muyu_get_geolocation_cache_key();
        delete_transient( $cache_key );
        
        // Eliminar cookie
        $host = str_replace( 'www.', '', preg_replace( '/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '' ));
        setcookie( 'muyu_geo_country', '', time() - YEAR_IN_SECONDS, '/', $host, is_ssl(), true );

        // Forzar nueva detección
        if ( function_exists( 'wc_get_customer_geolocation' ) ) {
            $geo = wc_get_customer_geolocation();
            
            if ( ! empty( $geo['country'] ) ) {
                set_transient( $cache_key, $geo, DAY_IN_SECONDS );
                muyu_set_geolocation_cookie( $geo['country'] );
                wp_send_json_success( [ 'country' => $geo['country'] ] );
            }
        }

        wp_send_json_error( [ 'message' => 'No se pudo determinar la ubicación' ] );
    }
    add_action( 'wp_ajax_mu_refresh_geolocation', 'muyu_refresh_geolocation' );
    add_action( 'wp_ajax_nopriv_mu_refresh_geolocation', 'muyu_refresh_geolocation' );
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
        // Verificar que WCPBC esté disponible para evitar conflictos
        if ( ! class_exists( 'WC_Product_Price_Based_Country' ) ) {
            return $decimals; // Fallback si WCPBC no está cargado
        }
        
        $country = muyu_get_current_country_from_subdomain();
        
        // Países que no utilizan decimales en su e-commerce
        $zero_decimals_countries = [ 'AR', 'CL', 'CO' ];
        
        if ( in_array( $country, $zero_decimals_countries, true ) ) {
            return 0;
        }
        
        return 2;
    }
}
add_filter( 'wc_get_price_decimals', 'mu_custom_price_decimals', 99 );

// ============================================
// AUTO-DETECCIÓN DE PAÍS POR DOMINIO
// ============================================

if ( ! function_exists( 'mu_apply_country_by_domain' ) ) {
    /**
     * Detecta automáticamente el país según el subdominio y lo aplica
     * de forma consistente a WC Customer y a la sesión WC.
     *
     * El subdominio SIEMPRE manda: es la señal correcta de país en esta
     * arquitectura multi-país. No se hace skip para usuarios logueados,
     * porque un cliente con billing de Argentina que entra por
     * mexico.muyunicos.com debe ver precios en MXN.
     *
     * Se ejecuta en múltiples hooks para cubrir todo el flujo:
     * - template_redirect (carga inicial de página)
     * - woocommerce_checkout_update_order_review (AJAX del checkout)
     * - woocommerce_cart_loaded_from_session (carga del carrito desde sesión)
     *
     * @return bool True si se aplicó un país, false si no.
     */
    function mu_apply_country_by_domain() {
        if ( is_admin() || ! function_exists( 'WC' ) || ! WC()->customer ) return false;
        
        // Verificar que WCPBC esté disponible para evitar conflictos
        if ( ! class_exists( 'WC_Product_Price_Based_Country' ) ) {
            return false; // Si WCPBC no está cargado, no interferir
        }
        
        $current_host = preg_replace( '/:\d+$/', '', trim( $_SERVER['HTTP_HOST'] ?? '' ) );
        $current_host = preg_replace( '/^www\./i', '', $current_host );
        
        $host_to_country_map = [];
        foreach ( muyu_get_countries_data() as $code => $data ) {
            $host_to_country_map[ $data['host'] ] = $code;
        }
        
        if ( ! array_key_exists( $current_host, $host_to_country_map ) ) return false;
        
        $detected_country_code = $host_to_country_map[ $current_host ];
        $current_billing       = WC()->customer->get_billing_country();
        
        // Si ya está correcto, no hacer nada (evita escrituras innecesarias)
        if ( $detected_country_code === $current_billing ) return false;
        
        // Inicializar sesión si no existe (requerido para invitados)
        if ( WC()->session && ! WC()->session->has_session() ) {
            WC()->session->set_customer_session_cookie( true );
        }
        
        // Actualizar país del cliente (billing + shipping)
        WC()->customer->set_billing_country( $detected_country_code );
        WC()->customer->set_shipping_country( $detected_country_code );
        WC()->customer->save();
        
        // Persistir en sesión WC para que WCPBC lea el país correcto
        // durante el recálculo del checkout (woocommerce_checkout_update_order_review)
        if ( WC()->session ) {
            $customer_data = WC()->session->get( 'customer' );
            if ( ! is_array( $customer_data ) ) {
                $customer_data = [];
            }
            $customer_data['country']  = $detected_country_code;
            $customer_data['billing_country']  = $detected_country_code;
            $customer_data['shipping_country'] = $detected_country_code;
            WC()->session->set( 'customer', $customer_data );
        }
        
        return true;
    }
}

/**
 * Hook en template_redirect: carga inicial de página.
 * Prioridad 10 (antes de que WCPBC calcule precios).
 */
if ( ! function_exists( 'mu_auto_detect_country_by_domain' ) ) {
    function mu_auto_detect_country_by_domain() {
        mu_apply_country_by_domain();
    }
}
add_action( 'template_redirect', 'mu_auto_detect_country_by_domain', 10 );

/**
 * Hook en woocommerce_checkout_update_order_review: AJAX del checkout.
 * Es aquí donde WCPBC recalcula la moneda; si el país no está sincronizado,
 * el carrito queda en ARS en subdominios extranjeros.
 * Prioridad 5 (antes de que WCPBC procese el recálculo).
 */
if ( ! function_exists( 'mu_apply_country_on_checkout_update' ) ) {
    function mu_apply_country_on_checkout_update() {
        mu_apply_country_by_domain();
    }
}
add_action( 'woocommerce_checkout_update_order_review', 'mu_apply_country_on_checkout_update', 5 );

/**
 * Hook en woocommerce_cart_loaded_from_session: carga del carrito desde sesión.
 * Garantiza que el carrito nunca quede en ARS en un subdominio extranjero,
 * incluso si la sesión fue creada en otro subdominio.
 * Prioridad 5 (antes de que WCPBC calcule precios del carrito).
 */
if ( ! function_exists( 'mu_apply_country_on_cart_loaded' ) ) {
    function mu_apply_country_on_cart_loaded() {
        mu_apply_country_by_domain();
    }
}
add_action( 'woocommerce_cart_loaded_from_session', 'mu_apply_country_on_cart_loaded', 5 );

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
     * Determina si debe mostrarse el modal de sugerencia de país.
     * Consume muyu_get_cached_geolocation() para evitar doble request.
     *
     * @return bool True si debe mostrarse
     */
    function mu_should_show_country_modal() {
        $current_domain = preg_replace( '/:\d+$/', '', trim( $_SERVER['HTTP_HOST'] ?? '' ) );
        
        // Si ya eligió quedarse, no mostrar
        if ( isset( $_COOKIE['muyu_stay_here'] ) && $_COOKIE['muyu_stay_here'] == $current_domain ) {
            return false;
        }
        
        // Geolocalización cacheada — una sola llamada por request
        $geo = muyu_get_cached_geolocation();
        $user_country = ( ! empty( $geo['country'] ) ) ? strtoupper( $geo['country'] ) : null;
        
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
 * Enqueue condicional del modal de país.
 * Protegido con function_exists() para compatibilidad con snippets/plugins externos.
 */
if ( ! function_exists( 'mu_country_modal_enqueue' ) ) {
    function mu_country_modal_enqueue() {
        if ( is_admin() || ! mu_should_show_country_modal() ) return;
        
        $theme_version = wp_get_theme()->get( 'Version' );
        $theme_uri = get_stylesheet_directory_uri();
        
        wp_enqueue_style( 'mu-country-modal', $theme_uri . '/css/components/country-modal.css', [ 'mu-base' ], $theme_version );
        wp_enqueue_script( 'mu-country-modal-js', $theme_uri . '/js/country-modal.js', [], $theme_version, true );
        
        // Pasar datos AJAX para refresh de geolocalización
        // Solo localizar si el script fue enqueued correctamente
        if ( wp_script_is( 'mu-country-modal-js', 'enqueued' ) ) {
            wp_localize_script( 'mu-country-modal-js', 'muGeoData', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'mu-geo-refresh' ),
                'refreshEndpoint' => 'mu_refresh_geolocation',
            ]);
        }
    }
    add_action( 'wp_enqueue_scripts', 'mu_country_modal_enqueue', 30 );
}

/**
 * Renderiza el HTML del modal de país en wp_footer.
 * Reutiliza la geolocalización ya cacheada por muyu_get_cached_geolocation().
 * Protegido con function_exists() para compatibilidad con snippets/plugins externos.
 */
if ( ! function_exists( 'mu_country_modal_html' ) ) {
    function mu_country_modal_html() {
        if ( is_admin() || ! mu_should_show_country_modal() ) return;
        
        $countries    = muyu_get_countries_data();
        $request_uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $current_domain = preg_replace( '/:\d+$/', '', trim( $_SERVER['HTTP_HOST'] ?? '' ) );
        
        // Geolocalización cacheada — sin segunda llamada a wc_get_customer_geolocation()
        $geo          = muyu_get_cached_geolocation();
        $user_country = ( ! empty( $geo['country'] ) ) ? strtoupper( $geo['country'] ) : null;
        
        if ( ! $user_country || ! isset( $countries[ $user_country ] ) ) return;
        
        $target        = $countries[ $user_country ];
        $prefix        = muyu_country_language_prefix( $user_country );
        $final_request = muyu_clean_uri( $prefix, $request_uri );
        $target_url    = 'https://' . rtrim( $target['host'], '/' ) . $final_request;
        $modal_title = muyu_country_modal_text( $user_country, 'title' );
        $modal_question = sprintf( muyu_country_modal_text( $user_country, 'question' ), $target['name'] );
        $modal_stay     = muyu_country_modal_text( $user_country, 'stay' );
        $flag_url       = 'https://flagcdn.com/w40/' . esc_attr( $target['flag'] ) . '.png';
        ?>
        <div id="muyu-country-modal-overlay" class="mu-modal-overlay" 
     data-current-domain="<?php echo esc_attr( $current_domain ); ?>"
     role="dialog" 
     aria-modal="true" 
     aria-labelledby="mu-modal-title" 
     aria-describedby="mu-modal-subtitle">
            <div id="muyu-country-modal" class="mu-modal-container">
                <div class="mu-modal-content">  
                   <button id="muyu-country-close" class="mu-modal-close" type="button" aria-label="Cerrar">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="18" y1="6" x2="6" y2="18"></line>
        <line x1="6" y1="6" x2="18" y2="18"></line>
    </svg>
</button>
<div class="mu-modal-header">
                    <h2 id="mu-modal-title"><?php echo esc_html( $modal_title ); ?></h2>
                    <p class="mu-modal-subtitle" id="mu-modal-subtitle">
                          <?php echo esc_html( $modal_question ); ?>
                            <img src="<?php echo esc_attr( $flag_url ); ?>" alt="<?php echo esc_attr( $target['name'] ); ?>" />
                        </p>
                </div>
                    <div>
                        <a href="<?php echo esc_url( $target_url ); ?>" rel="nofollow" class="muyu-country-btn">
                            Ir a Muy Únicos <?php echo esc_html( $target['name'] ); ?>
                        </a>
                    </div>
                    <button id="muyu-country-stay" class="muyu-country-stay-btn">
                        <?php echo esc_html( $modal_stay ); ?>
                    </button>
                </div>
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
 *
 * HOOK: 'generate_before_header_content' — NO usar 'generate_header'.
 * En GeneratePress, `generate_header` dispara ANTES del `<header>`: el
 * marcado quedaba fuera de la barra y, al no estar posicionado en
 * absoluto, ocupaba su propia franja vacía encima del encabezado.
 * `generate_before_header_content` dispara dentro de `.inside-header`,
 * que es donde la bandera debe vivir. Verificado contra producción el
 * 2026-10-02 con el orden real del DOM.
 */
if ( ! function_exists( 'mu_inject_country_selector_header' ) ) {
    function mu_inject_country_selector_header() {
        if ( ! function_exists( 'render_country_redirect_selector' ) ) return;
        ?>
        <div class="mu-header-country-item">
            <?php echo render_country_redirect_selector(); ?>
        </div>
        <?php
    }
    add_action( 'generate_before_header_content', 'mu_inject_country_selector_header', 1 );
}

// ============================================
// BODY DATA ATTRIBUTE PARA CSS-ONLY CURRENCY SYMBOLS
// ============================================

if ( ! function_exists( 'mu_add_country_body_attribute' ) ) {
    /**
     * Agrega data-country al body para CSS-only currency symbol replacement
     * Permite que CSS seleccione símbolos de moneda específicos por país
     * 
     * @param array $classes Clases existentes del body
     * @return array Clases modificadas
     */
    function mu_add_country_body_attribute( $classes ) {
        // Obtener país actual del subdominio
        $current_country = function_exists( 'muyu_get_current_country_from_subdomain' ) 
            ? muyu_get_current_country_from_subdomain() 
            : 'AR';
        
        // Agregar el país como clase para que CSS pueda seleccionarlo
        $classes[] = 'mu-country-' . strtolower( $current_country );
        
        return $classes;
    }
    add_filter( 'body_class', 'mu_add_country_body_attribute' );
}
