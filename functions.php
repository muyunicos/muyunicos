<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// BEGIN ENQUEUE PARENT ACTION
// AUTO GENERATED - Do not modify or remove comment markers above or below:
if ( !function_exists( 'chld_thm_cfg_locale_css' ) ):
    function chld_thm_cfg_locale_css( $uri ){
        if ( empty( $uri ) && is_rtl() && file_exists( get_template_directory() . '/rtl.css' ) )
            $uri = get_template_directory_uri() . '/rtl.css';
        return $uri;
    }
endif;
add_filter( 'locale_stylesheet_uri', 'chld_thm_cfg_locale_css' );
// END ENQUEUE PARENT ACTION

// --- SISTEMA DE ENQUEUE MODULAR ---
function mu_enqueue_assets() {
    $ver = wp_get_theme()->get('Version');
    $uri = get_stylesheet_directory_uri();
    
    wp_enqueue_style('mu-base', get_stylesheet_uri(), [], $ver);
    wp_enqueue_style('mu-header', "$uri/css/components/header.css", ['mu-base'], $ver);
    wp_enqueue_style('mu-footer', "$uri/css/components/footer.css", ['mu-base'], $ver);
    wp_enqueue_style('mu-share', "$uri/css/components/share-button.css", ['mu-base'], $ver);
    
    wp_enqueue_script('mu-share-js', "$uri/js/share-button.js", [], $ver, true);
    wp_localize_script('mu-share-js', 'muShareVars', [
        'checkIcon' => function_exists('mu_get_icon') ? mu_get_icon('check') : ''
    ]);

    if (!is_user_logged_in()) {
        wp_enqueue_style('mu-modal-auth', "$uri/css/components/modal-auth.css", ['mu-base'], $ver);
        wp_enqueue_script('mu-modal-auth-js', "$uri/js/modal-auth.js", [], $ver, true);
    }
    
    if (is_front_page()) wp_enqueue_style('mu-home', "$uri/css/home.css", ['mu-base'], $ver);
    if (is_shop() || is_product_category() || is_product_tag()) wp_enqueue_style('mu-shop', "$uri/css/shop.css", ['mu-base'], $ver);
    if (is_product()) wp_enqueue_style('mu-product', "$uri/css/product.css", ['mu-base'], $ver);
    
    if (is_cart()) {
        wp_enqueue_style('mu-cart', "$uri/css/cart.css", ['mu-base'], $ver);
        wp_enqueue_script('mu-cart-js', "$uri/js/cart.js", ['jquery'], $ver, true);
        wp_localize_script('mu-cart-js', 'muCartVars', [
            'closeIcon' => function_exists('mu_get_icon') ? mu_get_icon('close') : ''
        ]);
    }

    if (is_checkout() && !is_order_received_page()) {
        wp_enqueue_style('mu-checkout', "$uri/css/checkout.css", ['mu-base'], $ver);
        wp_register_script('libphonenumber-js', 'https://unpkg.com/libphonenumber-js@1.10.49/bundle/libphonenumber-js.min.js', [], '1.10.49', true);
        wp_enqueue_script('mu-checkout-js', "$uri/js/checkout.js", ['jquery', 'libphonenumber-js'], $ver, true);
        wp_localize_script('mu-checkout-js', 'muCheckout', [
            'isLoggedIn' => is_user_logged_in(),
            'ajaxUrl'    => WC_AJAX::get_endpoint('mu_check_email'),
            'nonce'      => wp_create_nonce('check-email-nonce'),
        ]);
    }
    
    wp_enqueue_script('mu-header-js', "$uri/js/header.js", [], $ver, true);
    wp_enqueue_script('mu-footer-js', "$uri/js/footer.js", [], $ver, true);
    wp_enqueue_script('mu-ui-scripts', "$uri/js/mu-ui-scripts.js", [], $ver, true);
}
add_action('wp_enqueue_scripts', 'mu_enqueue_assets', 20);

// --- CSS CONDICIONAL - WPLingua Switcher ---
function mu_hide_wplingua_switcher() {
    if (is_admin()) return;
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (!in_array($host, ['us.muyunicos.com', 'br.muyunicos.com'], true)) {
        wp_add_inline_style('mu-base', '.wplng-switcher { display: none !important; }');
    }
}
add_action('wp_enqueue_scripts', 'mu_hide_wplingua_switcher', 25);

// --- MODAL AUTH - LOCALIZE SCRIPT ---
function mu_auth_localize_script() {
    if (!is_user_logged_in()) {
        wp_localize_script('mu-modal-auth-js', 'muAuthData', [
            'ajax_url' => WC_AJAX::get_endpoint('%%endpoint%%'),
            'nonce'    => wp_create_nonce('mu_auth_nonce'),
            'home_url' => home_url('/')
        ]);
    }
}
add_action('wp_enqueue_scripts', 'mu_auth_localize_script', 25);

// --- REPOSITORIO DE ICONOS SVG ---
if ( !function_exists( 'mu_get_icon' ) ) {
    function mu_get_icon($name) {
        $icons = [
            'arrow'     => '<svg class="mu-icon-svg muy-svg" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg>',
            'search'    => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>',
            'close'     => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>',
            'share'     => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>',
            'check'     => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>',
            'instagram' => '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12,4.622c2.403,0,2.688,0.009,3.637,0.052c0.877,0.04,1.354,0.187,1.671,0.31c0.42,0.163,0.72,0.358,1.035,0.673 c0.315,0.315,0.51,0.615,0.673,1.035c0.123,0.317,0.27,0.794,0.31,1.671c0.043,0.949,0.052,1.234,0.052,3.637 s-0.009,2.688-0.052,3.637c-0.04,0.877-0.187,1.354-0.31,1.671c-0.163,0.42-0.358,0.72-0.673,1.035 c-0.315-0.315-0.615,0.51-1.035,0.673c-0.317,0.123-0.794,0.27-1.671,0.31c-0.949,0.043-1.233,0.052-3.637,0.052 s-2.688-0.009-3.637-0.052c-0.877-0.04-1.354-0.187-1.671-0.31c-0.42-0.163-0.72-0.358-1.035-0.673 c-0.315-0.315-0.51-0.615-0.673-1.035c-0.123-0.317-0.27-0.794-0.31-1.671C4.631,14.688,4.622,14.403,4.622,12 s0.009-2.688,0.052-3.637c0.04-0.877,0.187-1.354,0.31-1.671c0.163-0.42,0.358-0.72,0.673-1.035 c0.315-0.315,0.615-0.51,1.035-0.673c0.317-0.123,0.794-0.27,1.671-0.31C9.312,4.631,9.597,4.622,12,4.622 M12,3 C9.556,3,9.249,3.01,8.289,3.054C7.331,3.098,6.677,3.25,6.105,3.472C5.513,3.702,5.011,4.01,4.511,4.511 c-0.5,0.5-0.808,1.002-1.038,1.594C3.25,6.677,3.098,7.331,3.054,8.289C3.01,9.249,3,9.556,3,12c0,2.444,0.01,2.751,0.054,3.711 c0.044,0.958,0.196,1.612,0.418,2.185c0.23,0.592,0.538,1.094,1.038,1.594c0.5,0.5,1.002,0.808,1.594,1.038 c0.572,0.222,1.227,0.375,2.185,0.418C9.249,20.99,9.556,21,12,21s2.751-0.01,3.711-0.054c0.958-0.044,1.612-0.196,2.185-0.418 c0.592-0.23,1.094-0.538,1.594-1.038c0.5-0.5,0.808-1.002,1.038-1.594c0.222-0.572,0.375-1.227,0.418-2.185 C20.99,14.751,21,14.444,21,12s-0.01-2.751-0.054-3.711c-0.044-0.958-0.196-1.612-0.418-2.185c-0.23-0.592-0.538-1.094-1.038-1.594 c-0.5-0.5-1.002-0.808-1.594-1.038c-0.572-0.222-1.227-0.375-2.185-0.418C14.751,3.01,14.444,3,12,3L12,3z M12,7.378 c-2.552,0-4.622,2.069-4.622,4.622S9.448,16.622,12,16.622s4.622-2.069,4.622-4.622S14.552,7.378,12,7.378z M12,15 c-1.657,0-3-1.343-3-3s1.343-3,3-3s3,1.343,3,3S13.657,15,12,15z M16.804,6.116c-0.596,0-1.08,0.484-1.08,1.08 s0.484,1.08,1.08,1.08c0.596,0,1.08-0.484,1.08-1.08S17.401,6.116,16.804,6.116z"></path></svg>',
            'facebook'  => '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 2C6.5 2 2 6.5 2 12c0 5 3.7 9.1 8.4 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.3v7C18.3 21.1 22 17 22 12c0-5.5-4.5-10-10-10z"></path></svg>',
            'pinterest' => '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12.289,2C6.617,2,3.606,5.648,3.606,9.622c0,1.846,1.025,4.146,2.666,4.878c0.25,0.111,0.381,0.063,0.439-0.169 c0.044-0.175,0.267-1.029,0.365-1.428c0.032-0.128,0.017-0.237-0.091-0.362C6.445,11.911,6.01,10.75,6.01,9.668 c0-2.777,2.194-5.464,5.933-5.464c3.23,0,5.49,2.108,5.49,5.122c0,3.407-1.794,5.768-4.13,5.768c-1.291,0-2.257-1.021-1.948-2.277 c0.372-1.495,1.089-3.112,1.089-4.191c0-0.967-0.542-1.775-1.663-1.775c-1.319,0-2.379,1.309-2.379,3.059 c0,1.115,0.394,1.869,0.394,1.869s-1.302,5.279-1.54,6.261c-0.405,1.666,0.053,4.368,0.094,4.604 c0.021,0.126,0.167,0.169,0.25,0.063c0.129-0.165,1.699-2.419,2.142-4.051c0.158-0.59,0.817-2.995,0.817-2.995 c0.43,0.784,1.681,1.446,3.013,1.446c3.963,0,6.822-3.494,6.822-7.833C20.394,5.112,16.849,2,12.289,2"></path></svg>',
            'tiktok'    => '<svg width="24" height="24" viewBox="0 0 32 32" aria-hidden="true" fill="currentColor"><path d="M16.708 0.027c1.745-0.027 3.48-0.011 5.213-0.027 0.105 2.041 0.839 4.12 2.333 5.563 1.491 1.479 3.6 2.156 5.652 2.385v5.369c-1.923-0.063-3.855-0.463-5.6-1.291-0.76-0.344-1.468-0.787-2.161-1.24-0.009 3.896 0.016 7.787-0.025 11.667-0.104 1.864-0.719 3.719-1.803 5.255-1.744 2.557-4.771 4.224-7.88 4.276-1.907 0.109-3.812-0.411-5.437-1.369-2.693-1.588-4.588-4.495-4.864-7.615-0.032-0.667-0.043-1.333-0.016-1.984 0.24-2.537 1.495-4.964 3.443-6.615 2.208-1.923 5.301-2.839 8.197-2.297 0.027 1.975-0.052 3.948-0.052 5.923-1.323-0.428-2.869-0.308-4.025 0.495-0.844 0.547-1.485 1.385-1.819 2.333-0.276 0.676-0.197 1.427-0.181 2.145 0.317 2.188 2.421 4.027 4.667 3.828 1.489-0.016 2.916-0.88 3.692-2.145 0.251-0.443 0.532-0.896 0.547-1.417 0.131-2.385 0.079-4.76 0.095-7.145 0.011-5.375-0.016-10.735 0.025-16.093z"></path></svg>',
            'youtube'   => '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M21.8,8.001c0,0-0.195-1.378-0.795-1.985c-0.76-0.797-1.613-0.801-2.004-0.847c-2.799-0.202-6.997-0.202-6.997-0.202 h-0.009c0,0-4.198,0-6.997,0.202C4.608,5.216,3.756,5.22,2.995,6.016C2.395,6.623,2.2,8.001,2.2,8.001S2,9.62,2,11.238v1.517 c0,1.618,0.2,3.237,0.2,3.237s0.195,1.378,0.795,1.985c0.761,0.797,1.76,0.771,2.205,0.855c1.6,0.153,6.8,0.201,6.8,0.201 s4.203-0.006,7.001-0.209c0.391-0.047,1.243-0.051,2.004-0.847c0.6-0.607,0.795-1.985,0.795-1.985s0.2-1.618,0.2-3.237v-1.517 C22,9.62,21.8,8.001,21.8,8.001z M9.935,14.594l-0.001-5.62l5.404,2.82L9.935,14.594z"></path></svg>'
        ];
        return $icons[$name] ?? '';
    }
}

// --- FUNCIONES AUXILIARES MULTI-PAÍS (CORE) ---
if ( !function_exists('muyu_get_main_domain') ) {
    function muyu_get_main_domain() {
        static $main_domain = null;
        if ( $main_domain === null ) {
            $siteurl = get_option('siteurl');
            $main_domain = wp_parse_url($siteurl, PHP_URL_HOST);
            if ( empty($main_domain) ) $main_domain = 'muyunicos.com';
        }
        return $main_domain;
    }
}

if ( !function_exists('muyu_country_language_prefix') ) {
    function muyu_country_language_prefix($code) {
        $prefixes = ['BR' => '/pt', 'US' => '/en'];
        return $prefixes[$code] ?? '';
    }
}

if ( !function_exists('muyu_get_countries_data') ) {
    function muyu_get_countries_data() {
        return [
            'MX' => ['name' => 'México',        'host' => 'mexico.muyunicos.com', 'flag' => 'mx', 'lang' => 'es'],
            'CO' => ['name' => 'Colombia',      'host' => 'co.muyunicos.com',     'flag' => 'co', 'lang' => 'es'],
            'ES' => ['name' => 'España',        'host' => 'es.muyunicos.com',     'flag' => 'es', 'lang' => 'es'],
            'CL' => ['name' => 'Chile',         'host' => 'cl.muyunicos.com',     'flag' => 'cl', 'lang' => 'es'],
            'PE' => ['name' => 'Perú',          'host' => 'pe.muyunicos.com',     'flag' => 'pe', 'lang' => 'es'],
            'BR' => ['name' => 'Brasil',        'host' => 'br.muyunicos.com',     'flag' => 'br', 'lang' => 'pt'],
            'EC' => ['name' => 'Ecuador',       'host' => 'ec.muyunicos.com',     'flag' => 'ec', 'lang' => 'es'],
            'AR' => ['name' => 'Argentina',     'host' => 'muyunicos.com',        'flag' => 'ar', 'lang' => 'es'],
            'US' => ['name' => 'United States', 'host' => 'us.muyunicos.com',     'flag' => 'us', 'lang' => 'en'],
            'CR' => ['name' => 'Costa Rica',    'host' => 'cr.muyunicos.com',     'flag' => 'cr', 'lang' => 'es'],
        ];
    }
}

if ( !function_exists('muyu_get_current_country_from_subdomain') ) {
    function muyu_get_current_country_from_subdomain() {
        $current_host = trim($_SERVER['HTTP_HOST'] ?? '', ':80');
        $main_domain = muyu_get_main_domain();
        
        if ( $current_host === $main_domain || strpos($current_host, $main_domain) === false ) {
            return 'AR';
        }
        
        $subdomain = str_replace('.' . $main_domain, '', $current_host);
        $subdomain_map = [];
        
        foreach ( muyu_get_countries_data() as $code => $data ) {
            $host_parts = explode('.', $data['host']);
            $sub = strtolower($host_parts[0]);
            if ( $data['host'] === $main_domain ) continue;
            $subdomain_map[$sub] = $code;
        }
        
        return $subdomain_map[strtolower($subdomain)] ?? 'AR';
    }
}

if ( !function_exists('muyu_clean_uri') ) {
    function muyu_clean_uri($prefix, $uri) {
        $uri = '/' . ltrim(preg_replace('#/+#', '/', $uri), '/');
        if ( $prefix && strpos($uri, $prefix) === 0 ) return $uri;
        return $prefix . $uri;
    }
}

// --- HELPER PARA TEXTOS MULTI-IDIOMA DEL MODAL ---
if (!function_exists('muyu_country_modal_text')) {
    function muyu_country_modal_text($code, $type = 'question') {
        $text = [
            'pt' => ['question' => 'Você deseja comprar do %s?', 'stay' => 'Permanecer neste site e não perguntar novamente'],
            'en' => ['question' => 'Do you want to shop from %s?', 'stay' => 'Stay on this site and do not ask again'],
            'es' => ['question' => '¿Quieres comprar desde %s?', 'stay' => 'Quedarme en este sitio']
        ];
        
        $countries = muyu_get_countries_data();
        $lang = $countries[$code]['lang'] ?? 'es';
        
        return $text[$lang][$type] ?? $text['es'][$type];
    }
}

// --- AUTO-DETECCIÓN DE PAÍS POR DOMINIO ---
if ( !function_exists('mu_auto_detect_country_by_domain') ) {
    function mu_auto_detect_country_by_domain() {
        if ( is_admin() || !function_exists('WC') || !WC()->customer ) return;
        
        $host_to_country_map = [];
        foreach ( muyu_get_countries_data() as $code => $data ) {
            $host_to_country_map[$data['host']] = $code;
        }
        
        $current_host = $_SERVER['HTTP_HOST'] ?? '';
        if ( !array_key_exists($current_host, $host_to_country_map) ) return;
        
        $detected_country_code = $host_to_country_map[$current_host];
        if ( $detected_country_code === WC()->customer->get_billing_country() ) return;
        
        if ( WC()->session && !WC()->session->has_session() ) {
            WC()->session->set_customer_session_cookie(true);
        }
        
        WC()->customer->set_billing_country($detected_country_code);
        WC()->customer->set_shipping_country($detected_country_code);
        WC()->customer->save();
    }
    add_action('template_redirect', 'mu_auto_detect_country_by_domain', 1);
}

// --- SHORTCODE PAÍS DE FACTURACIÓN ---
if ( !function_exists('mostrar_nombre_pais_facturacion') ) {
    function mostrar_nombre_pais_facturacion() {
        if ( !function_exists('WC') || !WC()->customer ) return '';
        $country_code = WC()->customer->get_billing_country();
        if ( empty($country_code) ) return '';
        
        $countries = WC()->countries->get_countries();
        return isset($countries[$country_code]) ? esc_html($countries[$country_code]) : '';
    }
    add_shortcode('mi_pais_facturacion', 'mostrar_nombre_pais_facturacion');
}

// --- MODAL DE SUGERENCIA DE PAÍS ---
if (!function_exists('mu_should_show_country_modal')) {
    function mu_should_show_country_modal() {
        $current_domain = $_SERVER['HTTP_HOST'] ?? '';
        if (isset($_COOKIE['muyu_stay_here']) && $_COOKIE['muyu_stay_here'] == $current_domain) return false;
        
        $user_country = null;
        if (function_exists('wc_get_customer_geolocation') && function_exists('WC') && WC()->customer) {
            $geo = wc_get_customer_geolocation();
            $user_country = !empty($geo['country']) ? strtoupper($geo['country']) : null;
        }
        
        if (!$user_country) return false;
        
        $countries = muyu_get_countries_data();
        if (!isset($countries[$user_country])) return false;
        
        $target = $countries[$user_country];
        if ($target['host'] === $current_domain) return false;
        
        return true;
    }
}

function mu_country_modal_enqueue() {
    if (is_admin() || !mu_should_show_country_modal()) return;
    
    $theme_version = wp_get_theme()->get('Version');
    $theme_uri = get_stylesheet_directory_uri();
    
    wp_enqueue_style('mu-country-modal', $theme_uri . '/css/components/country-modal.css', ['mu-base'], $theme_version);
    wp_enqueue_script('mu-country-modal-js', $theme_uri . '/js/country-modal.js', [], $theme_version, true);
}
add_action('wp_enqueue_scripts', 'mu_country_modal_enqueue', 30);

function mu_country_modal_html() {
    if (is_admin() || !mu_should_show_country_modal()) return;
    
    $countries = muyu_get_countries_data();
    $request_uri = $_SERVER['REQUEST_URI'] ?? '/';
    $current_domain = $_SERVER['HTTP_HOST'] ?? '';
    
    $geo = wc_get_customer_geolocation();
    $user_country = !empty($geo['country']) ? strtoupper($geo['country']) : null;
    
    if (!$user_country || !isset($countries[$user_country])) return;
    
    $target = $countries[$user_country];
    $prefix = muyu_country_language_prefix($user_country);
    $final_request = muyu_clean_uri($prefix, $request_uri);
    $target_url = 'https://' . rtrim($target['host'], '/') . $final_request;
    
    $modal_question = sprintf(muyu_country_modal_text($user_country, 'question'), $target['name']);
    $modal_stay = muyu_country_modal_text($user_country, 'stay');
    $flag_url = 'https://flagcdn.com/w40/' . esc_attr($target['flag']) . '.png';
    ?>
    <div id="muyu-country-modal-overlay" data-current-domain="<?php echo esc_attr($current_domain); ?>">
        <div id="muyu-country-modal">
            <button id="muyu-country-close" title="Cerrar" aria-label="Cerrar">&times;</button>
            <div>
                <div>
                    <?php echo esc_html($modal_question); ?>
                    <img src="<?php echo esc_attr($flag_url); ?>" alt="<?php echo esc_attr($target['name']); ?>" />
                </div>
                <a href="<?php echo esc_url($target_url); ?>" rel="nofollow" class="muyu-country-btn">
                    Ir a Muy Únicos <?php echo esc_html($target['name']); ?>
                </a>
            </div>
            <button id="muyu-country-stay" class="muyu-country-stay-btn">
                <?php echo esc_html($modal_stay); ?>
            </button>
        </div>
    </div>
    <?php
}
add_action('wp_footer', 'mu_country_modal_html', 100);

// --- MODAL AUTH - HTML OUTPUT ---
function mu_auth_modal_html() {
    if (is_user_logged_in()) return;
    $current_url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    ?>
    <div id="mu-auth-modal" class="mu-auth-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="mu-modal-title">
        <div class="mu-modal-overlay" aria-hidden="true"></div>
        <div class="mu-modal-container">
            <div class="mu-modal-content">
                <button class="mu-modal-close" aria-label="Cerrar" type="button">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>

                <div class="mu-modal-header">
                    <h2 id="mu-modal-title">¡Te damos la bienvenida!</h2>
                    <p class="mu-modal-subtitle" id="mu-modal-subtitle">Ingresa a tu cuenta o creá una nueva</p>
                </div>

                <form id="mu-auth-form" class="mu-modal-body">
                    <!-- STEP 1: Identificación -->
                    <div id="mu-step-1" class="mu-form-step">
                        <div class="mu-form-group">
                            <label for="mu-user-input">Tu Email o usuario</label>
                            <input type="text" id="mu-user-input" name="user_login" class="mu-input" placeholder="tu@email.com" required autocomplete="username">
                        </div>
                        <button type="button" id="mu-continue-btn" class="mu-btn mu-btn-primary mu-btn-block">Continuar</button>
                    </div>

                    <!-- STEP 2: Login -->
                    <div id="mu-step-2-login" class="mu-form-step" style="display:none;">
                        <div class="mu-back-link">
                            <button type="button" id="mu-back-to-step1" class="mu-link-back">
                                <svg class="mu-icon-svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg> Cambiar usuario
                            </button>
                        </div>
                        <p class="mu-welcome-text">Hola de nuevo, <strong id="mu-user-display"></strong></p>
                        <div class="mu-form-group">
                            <label for="mu-password-login">Tu contraseña</label>
                            <input type="password" id="mu-password-login" name="password" class="mu-input" placeholder="Tu contraseña" autocomplete="current-password">
                        </div>
                        <button type="submit" id="mu-login-btn" class="mu-btn mu-btn-primary mu-btn-block">Entrar</button>
                        <a href="#" id="mu-forgot-link" class="mu-forgot-link">¿Has olvidado tu contraseña?</a>
                    </div>

                    <!-- STEP 2: Registro -->
                    <div id="mu-step-2-register" class="mu-form-step" style="display:none;">
                        <div class="mu-back-link">
                            <button type="button" id="mu-back-to-step1-reg" class="mu-link-back">
                                <svg class="mu-icon-svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg> Cambiar email
                            </button>
                        </div>
                        <p class="mu-welcome-new">🎉 Primera vez por aquí</p>
                        <div class="mu-form-group" id="mu-email-group" style="display:none;">
                            <label for="mu-email-register">Email</label>
                            <input type="email" id="mu-email-register" name="email" class="mu-input" placeholder="tu@email.com" autocomplete="email">
                        </div>
                        <div class="mu-form-group">
                            <label for="mu-password-register">Creá una contraseña</label>
                            <input type="password" id="mu-password-register" name="password" class="mu-input" placeholder="Mínimo 6 caracteres" autocomplete="new-password">
                        </div>
                        <button type="submit" id="mu-register-btn" class="mu-btn mu-btn-primary mu-btn-block">Crear cuenta</button>
                        <p class="mu-terms-text">Aceptás nuestros <a href="/terminos/" target="_blank">términos y condiciones</a></p>
                    </div>

                    <!-- STEP: Recupero -->
                    <div id="mu-step-forgot" class="mu-form-step" style="display:none;">
                         <div class="mu-back-link">
                            <button type="button" id="mu-back-to-login" class="mu-link-back">
                                <svg class="mu-icon-svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg> Volver
                            </button>
                        </div>
                        <p class="mu-welcome-text" style="background:#f3f4f6;">Te enviaremos un enlace para crear una nueva clave.</p>
                        <div class="mu-form-group">
                            <label for="mu-forgot-email">Confirma tu email</label>
                            <input type="text" id="mu-forgot-email" class="mu-input" placeholder="tu@email.com">
                        </div>
                        <button type="button" id="mu-send-reset-btn" class="mu-btn mu-btn-primary mu-btn-block">Enviar enlace</button>
                    </div>
                    <div id="mu-auth-message" class="mu-auth-message" style="display:none;"></div>
                </form>

                <div id="mu-social-section">
                    <div class="mu-divider"><span>o ingresa directamente con</span></div>
                    <div class="mu-social-buttons">
                        <a href="<?php echo esc_url(site_url('/wp-login.php?loginSocial=google&redirect=' . urlencode($current_url))); ?>" class="mu-btn-social mu-btn-google" data-plugin="nsl" data-action="connect" data-provider="google" data-popupwidth="600" data-popupheight="600">
                            <svg width="18" height="18" viewBox="0 0 18 18"><path d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.615z" fill="#4285F4"/><path d="M9.003 18c2.43 0 4.467-.806 5.956-2.18L12.05 13.56c-.806.54-1.836.86-3.047.86-2.344 0-4.328-1.584-5.036-3.711H.96v2.332C2.44 15.983 5.485 18 9.003 18z" fill="#34A853"/><path d="M3.964 10.712c-.18-.54-.282-1.117-.282-1.71 0-.593.102-1.17.282-1.71V4.96H.957C.347 6.175 0 7.55 0 9.002c0 1.452.348 2.827.957 4.042l3.007-2.332z" fill="#FBBC05"/><path d="M9.003 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.464.891 11.428 0 9.003 0 5.485 0 2.44 2.017.96 4.958L3.967 7.29c.708-2.127 2.692-3.71 5.036-3.71z" fill="#EA4335"/></svg> Google
                        </a>
                        <a href="<?php echo esc_url(site_url('/wp-login.php?loginSocial=facebook&redirect=' . urlencode($current_url))); ?>" class="mu-btn-social mu-btn-facebook" data-plugin="nsl" data-action="connect" data-provider="facebook" data-popupwidth="600" data-popupheight="679">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="#fff"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg> Facebook
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    if (class_exists('NextendSocialLogin', false)) do_action('nsl_render_login_form');
}
add_action('wp_footer', 'mu_auth_modal_html', 5);

// --- MODAL AUTH - WC-AJAX HANDLERS ---
function mu_check_user_exists() {
    check_ajax_referer('mu_auth_nonce', 'nonce');
    $input = sanitize_text_field($_POST['user_input']);
    $user = is_email($input) ? get_user_by('email', $input) : get_user_by('login', $input);
    if ($user) {
        wp_send_json_success(['exists' => true, 'display_name' => $user->display_name]);
    } else {
        wp_send_json_success(['exists' => false]);
    }
}
add_action('wc_ajax_mu_check_user', 'mu_check_user_exists');

function mu_handle_login() {
    check_ajax_referer('mu_auth_nonce', 'nonce');
    $creds = [
        'user_login'    => sanitize_text_field($_POST['user_login']),
        'user_password' => $_POST['password'],
        'remember'      => true
    ];
    $user = wp_signon($creds, is_ssl());
    if (is_wp_error($user)) wp_send_json_error(['message' => 'Contraseña incorrecta']);
    wp_send_json_success();
}
add_action('wc_ajax_mu_login_user', 'mu_handle_login');

function mu_handle_register() {
    check_ajax_referer('mu_auth_nonce', 'nonce');
    $email    = sanitize_email($_POST['email']);
    $username = sanitize_user($_POST['username']);
    $password = $_POST['password'];
    
    if (email_exists($email)) wp_send_json_error(['message' => 'Email ya registrado']);
    
    $user_id = wc_create_new_customer($email, $username, $password);
    if (is_wp_error($user_id)) wp_send_json_error(['message' => $user_id->get_error_message()]);
    
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true, is_ssl());
    wp_send_json_success();
}
add_action('wc_ajax_mu_register_user', 'mu_handle_register');

function mu_handle_reset_password() {
    check_ajax_referer('mu_auth_nonce', 'nonce');
    $login = sanitize_text_field($_POST['user_login']);
    $user = is_email($login) ? get_user_by('email', $login) : get_user_by('login', $login);
    
    if (!$user) wp_send_json_error(['message' => 'No encontramos esa cuenta.']);
    
    $key = get_password_reset_key($user);
    if (is_wp_error($key)) wp_send_json_error(['message' => 'Error del sistema.']);
    
    try {
        $mailer = WC()->mailer();
        $email = $mailer->get_emails()['WC_Email_Customer_Reset_Password'];
        if ($email) {
            $email->trigger($user->user_login, $key);
            wp_send_json_success(['message' => '¡Enviado! Ten en cuenta que puede demorar o marcarse como spam.']);
        }
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Error al enviar correo.']);
    }
}
add_action('wc_ajax_mu_reset_password', 'mu_handle_reset_password');

// --- HEADER - ICONOS Y FUNCIONALIDAD ---
function mu_header_icons() {
    $cart_count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
    $is_logged_in = is_user_logged_in();
    $my_account_url = get_permalink( get_option('woocommerce_myaccount_page_id') );
    $edit_account_url = wc_get_account_endpoint_url( 'edit-account' );
    $downloads_url    = wc_get_account_endpoint_url( 'downloads' );
    $logout_url       = wp_logout_url( home_url() );
    $account_label = $is_logged_in ? 'Mi cuenta' : 'Ingresar';
    ?>
    <div class="mu-header-icons">
        <a class="mu-header-icon mu-icon-help" href="/terminos/" title="Ayuda">
            <span class="mu-icon-wrapper">
                <svg class="mu-icon-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </span>
            <span class="mu-icon-label"></span>
        </a>
        <a class="mu-header-icon mu-icon-search" href="#" role="button" aria-label="Buscar" data-gpmodal-trigger="gp-search">
            <span class="mu-icon-wrapper">
                <svg class="mu-icon-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
            </span>
            <span class="mu-icon-label">Buscar</span>
        </a>
        <div class="mu-account-dropdown-wrap">
            <a class="mu-header-icon mu-icon-account mu-open-auth-modal" href="<?php echo esc_url($my_account_url); ?>" title="<?php echo esc_attr($account_label); ?>">
                <span class="mu-icon-wrapper">
                    <svg class="mu-icon-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    </svg>
                </span>
                <span class="mu-icon-label">
                    <?php echo esc_html($account_label); ?>
                    <?php if ( $is_logged_in ) : ?>
                         <span class="gp-icon icon-arrow"> <?php echo mu_get_icon('arrow'); ?> </span>
                    <?php endif; ?>
                </span>
            </a>
            <?php if ( $is_logged_in ) : ?>
            <ul class="mu-sub-menu">
                <li><a href="<?php echo esc_url($edit_account_url); ?>">Detalles de la cuenta</a></li>
                <li><a href="<?php echo esc_url($downloads_url); ?>">Mis Descargas</a></li>
                <li class="mu-logout-item"><a href="<?php echo esc_url($logout_url); ?>">Salir</a></li>
            </ul>
            <?php endif; ?>
        </div>
        <a class="mu-header-icon mu-icon-cart" href="/carrito/" title="Carrito">
            <span class="mu-icon-wrapper">
                <svg class="mu-icon-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
                <span class="mu-cart-badge <?php echo ($cart_count > 0) ? 'is-visible' : ''; ?>">
                    <?php echo esc_html( $cart_count ); ?>
                </span>
            </span>
            <span class="mu-icon-label">Carrito</span>
        </a>
    </div>
    <?php
}
add_action( 'generate_after_primary_menu', 'mu_header_icons' );

function mu_update_cart_badge( $fragments ) {
    $cart_count = WC()->cart->get_cart_contents_count();
    ob_start();
    ?>
    <span class="mu-cart-badge <?php echo ($cart_count > 0) ? 'is-visible' : ''; ?>">
        <?php echo esc_html( $cart_count ); ?>
    </span>
    <?php
    $fragments['.mu-cart-badge'] = ob_get_clean();
    return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'mu_update_cart_badge' );

function mu_boton_flotante_whatsapp() {
    ?>
    <a href="https://api.whatsapp.com/send?phone=542235331311&amp;text=Hola!%20te%20escribo%20de%20la%20p%C3%A1gina%20muyunicos.com"
       class="boton-whatsapp" target="_blank" rel="noopener noreferrer">
        <img src="https://muyunicos.com/wp-content/uploads/2025/10/whatsapp.webp" alt="Contacto por WhatsApp">
    </a>
    <?php
}
add_action( 'wp_footer', 'mu_boton_flotante_whatsapp' );

function mu_custom_search_form_logic( $form ) {
    $unique_id = uniqid( 'search-form-' );
    $icon_html = function_exists( 'mu_get_icon' ) ? mu_get_icon( 'search' ) : '<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>';
    $form = '<form role="search" method="get" class="woocommerce-product-search mu-product-search" action="' . esc_url( home_url( '/' ) ) . '"><label class="screen-reader-text" for="' . esc_attr( $unique_id ) . '">Buscar productos:</label><div class="mu-search-group"><input type="search" id="' . esc_attr( $unique_id ) . '" class="search-field" placeholder="Buscar en la tienda..." value="' . get_search_query() . '" name="s" /><button type="submit" class="mu-search-submit" aria-label="Buscar">' . $icon_html . '</button><input type="hidden" name="post_type" value="product" /></div></form>';
    return $form;
}
add_filter( 'get_product_search_form', 'mu_custom_search_form_logic' );

// --- MUYUNICOS - Selector de País en Header ---
if ( ! function_exists( 'render_country_redirect_selector' ) ) {
    function render_country_redirect_selector() {
        if ( ! function_exists( 'WC' ) || ! WC()->customer ) return '';
        
        $countries_data        = muyu_get_countries_data();
        $current_country_code  = WC()->customer->get_billing_country() ?: 'AR';
        
        if ( ! isset( $countries_data[ $current_country_code ] ) ) $current_country_code = 'AR';
        
        $current_country_data = $countries_data[ $current_country_code ];
        $request_uri          = $_SERVER['REQUEST_URI'] ?? '/';
        $scheme               = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) ? 'https' : 'http';

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
                        $prefix     = muyu_country_language_prefix( $code );
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
    add_shortcode( 'country_redirect_selector', 'render_country_redirect_selector' );
}

function mu_inject_country_selector_header() {
    if ( ! function_exists( 'render_country_redirect_selector' ) ) return;
    ?>
    <div class="mu-header-country-item">
        <?php echo render_country_redirect_selector(); ?>
    </div>
    <?php
}
add_action( 'generate_header', 'mu_inject_country_selector_header', 1 );

// --- FOOTER - ESTRUCTURA CUSTOM ---
function muyunicos_custom_footer_structure() {
    $social_networks = [
        ['name' => 'Instagram', 'url' => 'https://www.instagram.com/muyunicos', 'id' => 'instagram'],
        ['name' => 'Facebook',  'url' => 'https://www.facebook.com/muyunicos',  'id' => 'facebook'],
        ['name' => 'TikTok',    'url' => 'https://www.tiktok.com/@muyunicos',   'id' => 'tiktok'],
        ['name' => 'YouTube',   'url' => 'https://www.youtube.com/@muyunicos',  'id' => 'youtube'],
        ['name' => 'Pinterest', 'url' => 'https://www.pinterest.com/muyunicos', 'id' => 'pinterest'], 
    ];
    ?>
    <footer class="mu-custom-footer site-footer">
        <div class="mu-container">
            <div class="mu-footer-grid">
                <!-- Columna: Marca -->
                <div class="mu-footer-col mu-col-brand">
                    <h3 class="mu-footer-title">Muy Únicos</h3>
                    <p style="opacity: 0.8; line-height: 1.6; margin-bottom: 15px;">Diseños exclusivos y productos personalizados hechos con pasión en Mar del Plata.</p>
                    <div class="mu-trust-wrapper">
                        <a href="https://www.trustindex.io/reviews/muyunicos.com" target="_blank" class="mu-trust-badge">
                             <span class="ti-stars">★★★★★</span>
                             <span class="ti-text">4.9/5 en Trustindex</span>
                        </a>
                    </div>
                </div>

                <!-- Columna: Enlaces -->
                <div class="mu-footer-col mu-col-links">
                    <details class="mu-accordion">
                        <summary class="mu-footer-title">
                            Te ayudamos <span class="gp-icon mu-arrow-icon"><?php echo mu_get_icon('arrow'); ?></span>
                        </summary>
                        <div class="mu-accordion-content">
                            <ul class="mu-footer-links">
                                <li><a href="/mi-cuenta/">Mi Cuenta</a></li>
                                <li><a href="/mi-cuenta/downloads/">Mis Descargas</a></li>
                                <li><a href="/envios/">Información de Envíos</a></li>
                                <li><a href="/privacy-policy/">Políticas</a></li>
                                <li><a href="/reembolso_devoluciones/" class="mu-regret-btn">Botón de arrepentimiento</a></li>
                            </ul>
                        </div>
                    </details>
                </div>

                <!-- Columna: Medios de Pago -->
                <div class="mu-footer-col mu-col-pay">
                    <h3 class="mu-footer-title">Pagá seguro</h3>
                    <div class="mu-payment-icons">
                        <img decoding="async" src="https://muyunicos.com/wp-content/uploads/2026/01/medios.png" alt="Medios de Pago" width="200">
                    </div>
                    <div class="mu-secure-badge">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg> Compra 100% Protegida
                    </div>
                </div>

                <!-- Columna: Buscador -->
                <div class="mu-footer-col mu-col-search">
                    <h3 class="mu-footer-title">¿Buscás algo?</h3>
                    <div class="mu-footer-search">
                        <?php if(function_exists('get_product_search_form')) { 
                            get_product_search_form(); 
                        } else { ?>
                            <form role="search" method="get" class="woocommerce-product-search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
                                <input type="search" class="search-field" placeholder="Buscar productos..." value="<?php echo get_search_query(); ?>" name="s" />
                                <button type="submit">Buscar</button>
                                <input type="hidden" name="post_type" value="product" />
                            </form>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Socket (Barra Inferior) -->
        <div class="mu-socket">
            <div class="mu-container mu-socket-inner">
                <div class="mu-copyright">
                    © 2022-<?php echo date('Y'); ?> <strong>Muy Únicos</strong>. Mar del Plata.
                </div>
                <div class="mu-social-icons">
                    <?php foreach ($social_networks as $net): ?>
                        <a href="<?php echo esc_url($net['url']); ?>" class="mu-social-link" target="_blank" aria-label="<?php echo esc_attr($net['name']); ?>">
                            <?php echo mu_get_icon($net['id']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </footer>
    <?php
}
add_action('generate_before_footer', 'muyunicos_custom_footer_structure');

// --- WC CHECKOUT - OPTIMIZACIONES ---
add_filter( 'woocommerce_enable_checkout_login_reminder', '__return_false' );
add_filter( 'woocommerce_checkout_registration_enabled', '__return_true' );
add_filter( 'woocommerce_checkout_registration_required', '__return_false' );
add_filter( 'woocommerce_create_account_default_checked', '__return_true' );
add_filter( 'woocommerce_terms_is_checked_default', '__return_true' );

function muyunicos_get_terms_and_conditions_checkbox_text($text) {
    return 'He leído y acepto los <a href="/terminos/" target="_blank">términos y condiciones</a> de la web.';
}
add_filter( 'woocommerce_get_terms_and_conditions_checkbox_text', 'muyunicos_get_terms_and_conditions_checkbox_text' );

if ( ! function_exists( 'muyunicos_has_physical_products' ) ) {
    function muyunicos_has_physical_products() {
        static $has_physical = null;
        if ( $has_physical !== null ) return $has_physical;

        $has_physical = false;
        if ( WC()->cart ) {
            foreach ( WC()->cart->get_cart() as $cart_item ) {
                if ( ! $cart_item['data']->is_virtual() && ! $cart_item['data']->is_downloadable() ) {
                    $has_physical = true;
                    break;
                }
            }
        }
        return $has_physical;
    }
}

if ( ! function_exists( 'muyunicos_optimize_checkout_fields' ) ) {
    function muyunicos_optimize_checkout_fields( $fields ) {
        $fields['billing']['billing_full_name'] = [
            'label'       => 'Nombre y Apellido',
            'placeholder' => 'Ej: Juan Pérez',
            'required'    => true,
            'class'       => ['form-row-wide', 'mu-smart-field'],
            'clear'       => true,
            'priority'    => 10, 
        ];

        if ( isset( $fields['billing']['billing_country'] ) ) {
            $fields['billing']['billing_country']['priority'] = 20;
            $fields['billing']['billing_country']['class']    = ['form-row-wide'];
        }

        $fields['billing']['billing_contact_header'] = [
            'type'     => 'text',
            'label'    => '', 
            'required' => false,
            'class'    => ['form-row-wide'],
            'priority' => 25,
        ];

        $fields['billing']['billing_email']['priority'] = 30;
        $fields['billing']['billing_email']['class']    = ['form-row-wide', 'mu-contact-field'];
        $fields['billing']['billing_email']['label']    = '<span class="mu-verified-badge" style="display:none;">✓</span> E-Mail';

        if (isset($fields['billing']['billing_phone'])) {
            $fields['billing']['billing_phone']['priority']    = 40;
            $fields['billing']['billing_phone']['label']       = 'WhatsApp';
            $fields['billing']['billing_phone']['required']    = false; 
            $fields['billing']['billing_phone']['placeholder'] = 'Ej: 9 223 123 4567';
            $fields['billing']['billing_phone']['class']       = ['form-row-wide', 'mu-contact-field'];
        }

        $is_physical = muyunicos_has_physical_products();
        $address_fields = ['billing_address_1', 'billing_address_2', 'billing_city', 'billing_postcode', 'billing_state'];

        unset( $fields['billing']['billing_company'] );

        if ( ! $is_physical ) {
            foreach ( $address_fields as $key ) unset( $fields['billing'][$key] );
            add_filter( 'woocommerce_cart_needs_shipping', '__return_false' );
        } else {
            $fields['billing']['billing_shipping_toggle'] = [
                'type'     => 'text', 
                'label'    => '',
                'required' => false,
                'class'    => ['form-row-wide'],
                'priority' => 45,
            ];
            
            foreach ( $address_fields as $index => $field_key ) {
                if ( isset( $fields['billing'][$field_key] ) ) {
                    $fields['billing'][$field_key]['required'] = false; 
                    $fields['billing'][$field_key]['class'][]  = 'mu-hidden'; 
                    $fields['billing'][$field_key]['class'][]  = 'mu-physical-address-field'; 
                    $fields['billing'][$field_key]['priority'] = 90 + $index;
                }
            }
        }
        return $fields;
    }
    add_filter( 'woocommerce_checkout_fields', 'muyunicos_optimize_checkout_fields', 9999 );
}

if ( ! function_exists( 'muyunicos_render_html_fragments' ) ) {
    function muyunicos_render_html_fragments( $field, $key, $args, $value ) {
        if ( $key === 'billing_contact_header' ) {
            return '<div class="form-row form-row-wide" id="muyunicos_header_row" style="margin-bottom:0;"><div class="mu-contact-header">Te contactamos por:</div><div id="mu-email-exists-notice"></div></div>';
        }
        if ( $key === 'billing_shipping_toggle' ) {
            return '<div class="form-row form-row-wide" id="muyunicos_toggle_row"><div class="mu-shipping-toggle-wrapper"><label style="cursor:pointer;"><input type="checkbox" id="muyunicos-toggle-shipping" name="muyunicos_shipping_toggle" value="1"> <b>Ingresar datos para envío</b> (Opcional)</label></div></div>';
        }
        return $field;
    }
    add_filter( 'woocommerce_form_field', 'muyunicos_render_html_fragments', 10, 4 );
}

if ( ! function_exists( 'muyunicos_sanitize_posted_data' ) ) {
    function muyunicos_sanitize_posted_data( $data ) {
        if ( ! empty( $data['billing_full_name'] ) ) {
            $parts = explode( ' ', trim( $data['billing_full_name'] ), 2 );
            $data['billing_first_name'] = $parts[0];
            $data['billing_last_name']  = $parts[1] ?? '.'; 
        }
        if ( ! empty( $data['billing_phone'] ) ) {
            $digits = preg_replace('/\D/', '', $data['billing_phone']);
            if ( strlen( $digits ) <= 6 ) $data['billing_phone'] = '';
        }
        return $data;
    }
    add_filter( 'woocommerce_checkout_posted_data', 'muyunicos_sanitize_posted_data' );
}

if ( ! function_exists( 'muyunicos_validate_checkout' ) ) {
    function muyunicos_validate_checkout() {
        if ( empty( $_POST['billing_full_name'] ) ) {
            wc_add_notice( __( 'Por favor, completa tu Nombre y Apellido.' ), 'error' );
        }
        if ( ! empty( $_POST['billing_phone'] ) ) {
            if ( isset($_POST['muyunicos_wa_valid']) && $_POST['muyunicos_wa_valid'] === '0' ) {
                 wc_add_notice( __( 'El número de WhatsApp parece incompleto o inválido.' ), 'error' );
            }
        }
        if ( isset( $_POST['muyunicos_shipping_toggle'] ) && $_POST['muyunicos_shipping_toggle'] == '1' ) {
            if ( empty( $_POST['billing_address_1'] ) ) wc_add_notice( __( 'La <strong>Dirección</strong> es necesaria para el envío.' ), 'error' );
            if ( empty( $_POST['billing_city'] ) ) wc_add_notice( __( 'La <strong>Ciudad</strong> es necesaria.' ), 'error' );
            if ( empty( $_POST['billing_postcode'] ) ) wc_add_notice( __( 'El <strong>Código Postal</strong> es necesario.' ), 'error' );
            if ( empty( $_POST['billing_state'] ) && WC()->countries->get_states( $_POST['billing_country'] ) ) {
                 wc_add_notice( __( 'La <strong>Provincia/Estado</strong> es necesaria.' ), 'error' );
            }
        }
    }
    add_action( 'woocommerce_checkout_process', 'muyunicos_validate_checkout' );
}

if ( ! function_exists( 'muyunicos_ajax_check_email_optimized' ) ) {
    function muyunicos_ajax_check_email_optimized() {
        check_ajax_referer( 'check-email-nonce', 'security' );
        $email = isset($_POST['email']) ? sanitize_email( $_POST['email'] ) : '';
        if ( ! empty($email) && email_exists( $email ) ) {
            wp_send_json( [ 'exists' => true ] );
        } else {
            wp_send_json( [ 'exists' => false ] );
        }
    }
    add_action( 'wc_ajax_mu_check_email', 'muyunicos_ajax_check_email_optimized' );
}

function mu_order_received_custom_title( $title, $id ) {
    if ( is_order_received_page() && get_the_ID() === $id && in_the_loop() ) {
        return '¡Pedido Recibido! 🎉';
    }
    return $title;
}
add_filter( 'the_title', 'mu_order_received_custom_title', 10, 2 );

// --- MIGRADO (Snippets varios) ---
if ( ! function_exists( 'mu_googlesitekit_canonical_home_url' ) ) {
    function mu_googlesitekit_canonical_home_url( $url ) { return 'https://muyunicos.com'; }
}
add_filter( 'googlesitekit_canonical_home_url', 'mu_googlesitekit_canonical_home_url' );

function mu_dcms_share_shortcode( $atts ) {
    $icon_share = function_exists( 'mu_get_icon' ) ? mu_get_icon( 'share' ) : '';
    return sprintf( '<button class="dcms-share-btn" type="button" title="Compartir" aria-label="Compartir">%s</button>', $icon_share );
}
add_shortcode( 'dcms_share', 'mu_dcms_share_shortcode' );

if ( ! function_exists( 'woo_add_multiple_products_to_cart' ) ) {
    function woo_add_multiple_products_to_cart() {
        if ( empty( $_GET['add-multiple'] ) || ! function_exists( 'WC' ) ) return;
        if ( null === WC()->cart && function_exists( 'wc_load_cart' ) ) wc_load_cart();
        if ( null === WC()->cart ) return;

        $product_ids = explode( ',', sanitize_text_field( wp_unslash( $_GET['add-multiple'] ) ) );
        $productos_agregados = false;
        foreach ( $product_ids as $product_id ) {
            $product_id = absint( $product_id );
            if ( $product_id > 0 && WC()->cart->add_to_cart( $product_id ) ) {
                $productos_agregados = true;
            }
        }
        if ( $productos_agregados ) {
            wp_safe_redirect( wc_get_cart_url() );
            exit;
        }
    }
}
add_action( 'wp_loaded', 'woo_add_multiple_products_to_cart' );

if ( ! function_exists( 'bacs_buffer_start' ) ) { function bacs_buffer_start() { ob_start(); } }
if ( ! function_exists( 'bacs_buffer_end' ) ) { function bacs_buffer_end( $order_id ) { $out = ob_get_clean(); echo $order_id ? str_replace( 'NUMERODEPEDIDO', $order_id, $out ) : $out; } }
add_action( 'woocommerce_thankyou_bacs', 'bacs_buffer_start', 1 );
add_action( 'woocommerce_thankyou_bacs', 'bacs_buffer_end', 100, 1 );

if ( ! function_exists( 'bacs_email_buffer_start' ) ) { function bacs_email_buffer_start( $o, $s, $pt, $e ) { if ( 'bacs' === $o->get_payment_method() && ! $pt ) ob_start(); } }
if ( ! function_exists( 'bacs_email_buffer_end' ) ) { function bacs_email_buffer_end( $o, $s, $pt, $e ) { if ( 'bacs' === $o->get_payment_method() && ! $pt ) echo str_replace( 'NUMERODEPEDIDO', $o->get_id(), ob_get_clean() ); } }
add_action( 'woocommerce_email_before_order_table', 'bacs_email_buffer_start', 1, 4 );
add_action( 'woocommerce_email_before_order_table', 'bacs_email_buffer_end', 100, 4 );

if ( ! function_exists( 'muyunicos_move_category_description' ) ) {
    function muyunicos_move_category_description() {
        if ( is_product_category() ) {
            remove_action( 'woocommerce_archive_description', 'woocommerce_taxonomy_archive_description', 10 );
            add_action( 'woocommerce_after_shop_loop', 'woocommerce_taxonomy_archive_description', 5 );
        }
    }
}
add_action( 'wp', 'muyunicos_move_category_description' );

// --- SISTEMA DE RESTRICCIÓN DE CONTENIDO DIGITAL v2.2 ---
if ( ! class_exists( 'MUYU_Digital_Restriction_System' ) ) {
    class MUYU_Digital_Restriction_System {
        private static $instance = null;
        private $cache = [];
        
        const OPTION_PRODUCT_IDS     = 'muyu_digital_product_ids';
        const OPTION_CATEGORY_IDS    = 'muyu_digital_category_ids';
        const OPTION_TAG_IDS         = 'muyu_digital_tag_ids';
        const OPTION_REDIRECT_MAP    = 'muyu_phys_to_dig_map';
        const OPTION_LAST_UPDATE     = 'muyu_digital_list_updated';
        const TRANSIENT_REBUILD      = 'muyu_rebuild_scheduled';
        const PHYSICAL_FORMAT_ID     = 112; 
        const DIGITAL_FORMAT_ID      = 111; 
        
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
            add_action( 'init', [ $this, 'ensure_indexes_exist' ], 5 );
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
            $host = $this->get_clean_host();
            $main_domain = function_exists('muyu_get_main_domain') ? muyu_get_main_domain() : 'muyunicos.com';
            return ( $this->cache['is_restricted'] = ( $main_domain !== $host ) );
        }
        
        public function get_user_country_code() {
            if ( isset( $this->cache['country_code'] ) ) return $this->cache['country_code'];
            if ( function_exists('muyu_get_current_country_from_subdomain') ) {
                $code = muyu_get_current_country_from_subdomain();
            } else {
                $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
                $parts = explode('.', $host);
                if ( count($parts) >= 3 ) {
                    $subdomain = strtolower($parts[0]);
                    $subdomain_map = [ 'mexico' => 'MX', 'br' => 'BR', 'co' => 'CO', 'ec' => 'EC', 'cl' => 'CL', 'pe' => 'PE', 'ar' => 'AR', 'us' => 'US' ];
                    $code = $subdomain_map[$subdomain] ?? (strlen($subdomain) === 2 ? strtoupper($subdomain) : 'AR');
                } else {
                    $code = 'AR';
                }
            }
            return ( $this->cache['country_code'] = $code );
        }
        
        private function get_clean_host() {
            if ( isset( $this->cache['clean_host'] ) ) return $this->cache['clean_host'];
            $host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
            return ( $this->cache['clean_host'] = str_replace( 'www.', '', trim($host, ':80') ) );
        }
        
        public function rebuild_digital_indexes() {
            global $wpdb;
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
            return array_filter( array_unique( array_map( 'intval', $wpdb->get_col( $sql ) ) ) );
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
            $category_ids = []; $tag_ids = [];
            foreach ( $terms as $term ) {
                if ( 'product_cat' === $term->taxonomy ) $category_ids[] = (int) $term->term_id;
                elseif ( 'product_tag' === $term->taxonomy ) $tag_ids[] = (int) $term->term_id;
            }
            return [ array_unique( $category_ids ), array_unique( $tag_ids ) ];
        }
        
        private function expand_category_hierarchy( $category_ids ) {
            $expanded = $category_ids;
            foreach ( $category_ids as $cat_id ) {
                if ( $ancestors = get_ancestors( $cat_id, 'product_cat', 'taxonomy' ) ) {
                    $expanded = array_merge( $expanded, $ancestors );
                }
            }
            return array_unique( array_map( 'intval', $expanded ) );
        }
        
        private function build_redirect_map( $digital_product_ids ) {
            global $wpdb;
            if ( empty( $digital_product_ids ) ) return [];
            $ids_string = implode( ',', $digital_product_ids );
            $digital_products = $wpdb->get_results( "SELECT ID, post_name FROM {$wpdb->posts} WHERE ID IN ($ids_string)" );
            $redirect_map = [];
            foreach ( $digital_products as $product ) {
                if ( false !== strpos( $product->post_name, '-imprimible' ) ) {
                    $base_slug = str_replace( '-imprimible', '', $product->post_name );
                    $physical_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'product' AND post_status = 'publish' LIMIT 1", $base_slug ) );
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
            if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'Permisos insuficientes' );
            $count = $this->rebuild_digital_indexes();
            wp_send_json_success( sprintf( 'Índice reconstruido correctamente. Total productos digitales: %d', $count ) );
        }
        
        public function schedule_rebuild( $product_id ) {
            if ( get_transient( self::TRANSIENT_REBUILD ) ) return;
            set_transient( self::TRANSIENT_REBUILD, true, 120 );
            add_action( 'shutdown', [ $this, 'rebuild_digital_indexes' ] );
        }
        
        public function ensure_indexes_exist() {
            if ( false === get_option( self::OPTION_PRODUCT_IDS ) ) {
                if ( get_transient( 'muyu_rebuild_lock' ) ) return;
                set_transient( 'muyu_rebuild_lock', true, 300 );
                $this->rebuild_digital_indexes();
            }
        }
        
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
            
            if ( ! $is_shop_query || ! $this->is_restricted_user() ) return;
            
            $digital_ids = get_option( self::OPTION_PRODUCT_IDS, [] );
            $query->set( 'post__in', ! empty( $digital_ids ) ? $digital_ids : [ 0 ] );
        }
        
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
            
            if ( $should_redirect ) $this->execute_redirect( $target_url );
        }
        
        private function handle_category_redirect() {
            $queried_object = get_queried_object();
            $digital_cats = get_option( self::OPTION_CATEGORY_IDS, [] );
            
            if ( ! $queried_object || in_array( $queried_object->term_id, $digital_cats, true ) ) return [ false, '' ];
            
            $parent_id = $queried_object->parent;
            while ( $parent_id ) {
                if ( in_array( $parent_id, $digital_cats, true ) ) return [ true, get_term_link( $parent_id, 'product_cat' ) ];
                $term = get_term( $parent_id, 'product_cat' );
                $parent_id = ( $term && ! is_wp_error( $term ) ) ? $term->parent : 0;
            }
            return [ true, '' ];
        }
        
        private function handle_tag_redirect() {
            $queried_object = get_queried_object();
            $digital_tags = get_option( self::OPTION_TAG_IDS, [] );
            if ( ! $queried_object || in_array( $queried_object->term_id, $digital_tags, true ) ) return [ false, '' ];
            return [ true, '' ];
        }
        
        private function handle_product_redirect() {
            global $post;
            $digital_ids = get_option( self::OPTION_PRODUCT_IDS, [] );
            
            if ( ! $post || in_array( $post->ID, $digital_ids, true ) ) return [ false, '' ];
            
            $redirect_map = get_option( self::OPTION_REDIRECT_MAP, [] );
            if ( isset( $redirect_map[ $post->ID ] ) ) return [ true, get_permalink( $redirect_map[ $post->ID ] ) ];
            
            return [ true, $this->find_digital_category_for_product( $post->ID ) ];
        }
        
        private function find_digital_category_for_product( $product_id ) {
            $digital_cats = get_option( self::OPTION_CATEGORY_IDS, [] );
            $product_cats = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'ids' ] );
            
            if ( empty( $product_cats ) || is_wp_error( $product_cats ) ) return '';
            
            foreach ( $product_cats as $cat_id ) {
                if ( in_array( $cat_id, $digital_cats, true ) ) return get_term_link( $cat_id, 'product_cat' );
            }
            
            foreach ( $product_cats as $cat_id ) {
                $ancestors = get_ancestors( $cat_id, 'product_cat', 'taxonomy' );
                foreach ( $ancestors as $ancestor_id ) {
                    if ( in_array( $ancestor_id, $digital_cats, true ) ) return get_term_link( $ancestor_id, 'product_cat' );
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
                if ( $prefix = muyu_country_language_prefix( $this->get_user_country_code() ) ) {
                    $target_url = insertar_prefijo_idioma( $target_url, $prefix );
                }
            }
            
            $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
            if ( untrailingslashit($target_url) === untrailingslashit($current_url) ) return;
            
            wp_safe_redirect( $target_url, 302 );
            exit;
        }
        
        public function init_frontend_filters() {
            add_filter( 'get_terms_args', [ $this, 'filter_category_terms' ], 10, 2 );
            add_filter( 'wp_get_nav_menu_items', [ $this, 'filter_menu_items' ], 10, 3 );
        }
        
        public function filter_category_terms( $args, $taxonomies ) {
            if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX && is_user_logged_in() ) ) return $args;
            if ( ! in_array( 'product_cat', (array) $taxonomies, true ) || ! $this->is_restricted_user() ) return $args;
            
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
            if ( is_admin() || ! $this->is_restricted_user() ) return $items;
            
            $digital_cat_ids = get_option( self::OPTION_CATEGORY_IDS, [] );
            return array_filter( $items, function( $item ) use ( $digital_cat_ids ) {
                if ( isset( $item->object ) && 'product_cat' === $item->object ) {
                    return in_array( (int) $item->object_id, $digital_cat_ids, true );
                }
                return true;
            });
        }
        
        public function hide_physical_variation( $visible, $variation_id, $product_id, $variation ) {
            if ( ! $visible || ! $this->is_restricted_user() ) return $visible;
            $attributes = $variation->get_attributes();
            $physical_slug = $this->get_physical_term_slug();
            
            if ( $physical_slug && isset( $attributes['pa_formato'] ) && $attributes['pa_formato'] === $physical_slug ) {
                return false;
            }
            return $visible;
        }
        
        public function clean_variation_dropdown( $args ) {
            if ( ! $this->is_restricted_user() || ! isset( $args['attribute'] ) || 'pa_formato' !== $args['attribute'] ) return $args;
            if ( empty( $args['options'] ) ) return $args;
            if ( ! $physical_slug = $this->get_physical_term_slug() ) return $args;
            
            foreach ( $args['options'] as $key => $option ) {
                if ( ( is_object( $option ) && isset( $option->term_id ) && $option->term_id == self::PHYSICAL_FORMAT_ID ) || ( is_string( $option ) && $option === $physical_slug ) ) {
                    unset( $args['options'][ $key ] );
                }
            }
            return $args;
        }
        
        public function filter_variation_prices( $prices_array, $product, $for_display ) {
            if ( ! $this->is_restricted_user() || empty( $prices_array['price'] ) ) return $prices_array;
            if ( ! $physical_slug = $this->get_physical_term_slug() ) return $prices_array;
            
            foreach ( $prices_array['price'] as $variation_id => $amount ) {
                $format_slug = get_post_meta( $variation_id, 'attribute_pa_formato', true );
                if ( $format_slug === $physical_slug ) {
                    unset( $prices_array['price'][ $variation_id ], $prices_array['regular_price'][ $variation_id ], $prices_array['sale_price'][ $variation_id ] );
                }
            }
            return $prices_array;
        }
        
        private function get_physical_term_slug() {
            if ( isset($this->cache['physical_term_slug']) ) return $this->cache['physical_term_slug'];
            $term = get_term( self::PHYSICAL_FORMAT_ID, 'pa_formato' );
            return ( $this->cache['physical_term_slug'] = ( $term && ! is_wp_error( $term ) ) ? $term->slug : null );
        }
        
        public function set_format_default( $defaults, $product ) {
            if ( $this->is_restricted_user() ) $term_id = self::DIGITAL_FORMAT_ID;
            elseif ( 'AR' === $this->get_user_country_code() ) $term_id = self::PHYSICAL_FORMAT_ID;
            else return $defaults;
            
            $term = get_term( $term_id, 'pa_formato' );
            if ( $term && ! is_wp_error( $term ) ) $defaults['pa_formato'] = $term->slug;
            return $defaults;
        }
        
        public function autoselect_format_variation() {
            global $product;
            if ( ! $product || ! $product->is_type( 'variable' ) ) return;
            
            if ( $this->is_restricted_user() ) {
                $target_term_id = self::DIGITAL_FORMAT_ID;
                $hide_row = true; 
            } elseif ( 'AR' === $this->get_user_country_code() ) {
                $target_term_id = self::PHYSICAL_FORMAT_ID;
                $hide_row = false; 
            } else {
                return;
            }
            
            $attributes = $product->get_variation_attributes();
            if ( ! isset( $attributes['pa_formato'] ) ) return;
            
            $target_term = get_term( $target_term_id, 'pa_formato' );
            if ( ! $target_term || is_wp_error( $target_term ) ) return;
            if ( ! in_array( $target_term->slug, $attributes['pa_formato'], true ) ) return;
            
            $target_slug = esc_js( $target_term->slug );
            $hide_row_js = $hide_row ? 'true' : 'false';
            
            $script = "
                var hide_row = {$hide_row_js};
                var \$form = jQuery('form.variations_form');
                if ( \$form.length ) {
                    \$form.on('wc_variation_form', function() {
                        setTimeout(autoSelectFormatVariation, 100);
                    });
                    setTimeout(autoSelectFormatVariation, 150);
                    
                    function autoSelectFormatVariation() {
                        var \$select = \$form.find('#pa_formato');
                        if ( ! \$select.length ) \$select = \$form.find('select[name=\"attribute_pa_formato\"]');
                        if ( ! \$select.length ) return;
                        
                        if ( \$select.val() === '{$target_slug}' ) {
                            if(hide_row) hideRowAndTable(\$select, \$form);
                            return;
                        }
                        
                        \$select.val('{$target_slug}').trigger('change');
                        \$form.trigger('check_variations');
                        if(hide_row) hideRowAndTable(\$select, \$form);
                    }
                    
                    function hideRowAndTable(\$select, \$form) {
                        var \$row = \$select.closest('tr');
                        \$row.hide();
                        if ( \$form.find('table.variations tr:visible').length === 0 ) {
                            \$form.find('.variations').fadeOut(200);
                        }
                    }
                }
            ";
            
            wc_enqueue_js( $script );
            
            if ( $hide_row ) {
                echo '<style>form.variations_form .variations, form.variations_form tr { transition: opacity 0.2s ease-out; }</style>';
            }
        }
        
        public function add_rebuild_button() {
            global $typenow;
            if ( 'product' !== $typenow ) return;
            
            $nonce = wp_create_nonce( 'muyu-rebuild-nonce' );
            ?>
            <script>
            jQuery(document).ready(function($) {
                $('.page-title-action').last().after(
                    '<button id="muyu-rebuild" class="page-title-action mu-btn-rebuild" style="margin-left:10px" data-nonce="<?php echo esc_js( $nonce ); ?>">⚡ Reindexar Digitales</button>'
                );
                $('#muyu-rebuild').on('click', function(e) {
                    e.preventDefault();
                    var $btn = $(this);
                    var originalText = $btn.text();
                    $btn.prop('disabled', true).text('⏳ Procesando...');
                    $.post(ajaxurl, {
                        action: 'muyu_rebuild_digital_list',
                        nonce: $btn.data('nonce')
                    }, function(response) {
                        if ( response.success ) {
                            alert('✅ ' + response.data);
                            location.reload();
                        } else {
                            alert('❌ Error: ' + (response.data || 'Desconocido'));
                            $btn.prop('disabled', false).text(originalText);
                        }
                    }).fail(function() {
                        alert('❌ Error de conexión con el servidor');
                        $btn.prop('disabled', false).text(originalText);
                    });
                });
            });
            </script>
            <style>
                #muyu-rebuild { transition: all 0.2s ease; }
                #muyu-rebuild:hover { background: #2271b1; border-color: #2271b1; color: #fff; }
                #muyu-rebuild:disabled { opacity: 0.6; cursor: not-allowed; }
            </style>
            <?php
        }
    }
}

if ( ! function_exists( 'muyu_digital_restriction_init' ) ) {
    function muyu_digital_restriction_init() {
        return MUYU_Digital_Restriction_System::get_instance();
    }
}
add_action( 'plugins_loaded', 'muyu_digital_restriction_init', 5 );

if ( ! function_exists( 'muyu_is_restricted_user' ) ) {
    function muyu_is_restricted_user() { return muyu_digital_restriction_init()->is_restricted_user(); }
}

if ( ! function_exists( 'muyu_get_user_country_code' ) ) {
    function muyu_get_user_country_code() { return muyu_digital_restriction_init()->get_user_country_code(); }
}

if ( ! function_exists( 'muyu_rebuild_digital_indexes_optimized' ) ) {
    function muyu_rebuild_digital_indexes_optimized() { return muyu_digital_restriction_init()->rebuild_digital_indexes(); }
}
/* ============================================
   UX - VINCULACIÓN PRODUCTOS FÍSICOS / DIGITALES
   Versión: 1.0.0 (Migrado desde Code Snippets)
   Muestra caja de navegación cruzada Físico <-> Digital.
   Usa meta cache (_mu_sibling_id / _mu_sibling_checked) para
   evitar queries SQL repetidas (LiteSpeed-friendly).
   CSS migrado a css/product.css.
   ============================================ */

add_action( 'woocommerce_single_product_summary', 'mu_render_linked_product', 25 );

if ( ! function_exists( 'mu_render_linked_product' ) ) {
    function mu_render_linked_product() {
        global $product, $wpdb;

        if ( ! is_product() || ! is_object( $product ) ) return;

        $product_id = $product->get_id();

        // --- FASE 1: META CACHE ---
        $sibling_id = get_post_meta( $product_id, '_mu_sibling_id', true );
        $is_checked = get_post_meta( $product_id, '_mu_sibling_checked', true );

        // IDs de configuración
        $cat_fisico     = 19;
        $cat_imprimible = 62;
        $prod_pers_imp  = 10708;
        $prod_pers_fis  = 10279;

        if ( ! $is_checked ) {
            $slug         = $product->get_slug();
            $is_printable = ( substr( $slug, -11 ) === '-imprimible' );
            $target_slug  = $is_printable ? substr( $slug, 0, -11 ) : $slug . '-imprimible';

            $found_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type = 'product' AND post_status = 'publish' LIMIT 1",
                $target_slug
            ) );

            if ( $found_id ) {
                update_post_meta( $product_id, '_mu_sibling_id', $found_id );
                $sibling_id = $found_id;
            }

            update_post_meta( $product_id, '_mu_sibling_checked', 'yes' );
        }

        if ( ! $sibling_id ) return;

        // --- FASE 2: LÓGICA DE VISUALIZACIÓN ---
        $current_slug         = $product->get_slug();
        $is_current_printable = ( substr( $current_slug, -11 ) === '-imprimible' );

        // Usa el helper CORE (reemplaza detección manual de host)
        $is_argentina = ( muyu_get_current_country_from_subdomain() === 'AR' );

        $show_cross_link = false;
        $msg_intro       = '';
        $msg_cta         = '';
        $linked_url      = get_permalink( $sibling_id );

        if ( $is_current_printable ) {
            if ( $is_argentina ) {
                $show_cross_link = true;
                $msg_intro       = '¡Atención! Este es un producto digital, pero también te lo podemos ofrecer listo para usar:';
                $msg_cta         = 'Tocá acá para acceder a la versión física impresa';
            }
            $url_catalogo = get_term_link( $cat_imprimible, 'product_cat' );
            $url_diseno   = get_permalink( $prod_pers_imp );
            $text_diseno  = 'armá tu diseño';
        } else {
            $show_cross_link = true;
            $msg_intro       = 'Si necesitás la versión digital en PDF de este producto:';
            $msg_cta         = 'Tocá acá para acceder a la versión descargable';
            $url_catalogo    = get_term_link( $cat_fisico, 'product_cat' );
            $url_diseno      = get_permalink( $prod_pers_fis );
            $text_diseno     = 'diseñalo';
        }

        if ( is_wp_error( $url_catalogo ) ) $url_catalogo = '#';

        $is_customizable = in_array( $product_id, array( $prod_pers_imp, $prod_pers_fis ) );

        // --- FASE 3: RENDERIZADO ---
        ?>
        <div class="mu-linked-box">
            <?php if ( $show_cross_link ) : ?>
                <p class="mu-cross-p">
                    <?php echo esc_html( $msg_intro ); ?>
                    <a href="<?php echo esc_url( $linked_url ); ?>" class="mu-cross-a">
                        <?php echo esc_html( $msg_cta ); ?>
                    </a>
                </p>
            <?php endif; ?>

            <p class="mu-cat-p">
                <a href="<?php echo esc_url( $url_catalogo ); ?>">Ver catálogo completo de diseños</a>
                <?php if ( ! $is_customizable ) : ?>
                    o <?php echo esc_html( $text_diseno ); ?>
                    <a href="<?php echo esc_url( $url_diseno ); ?>">tocando acá</a>.
                <?php endif; ?>
            </p>
        </div>
        <?php
    }
}
