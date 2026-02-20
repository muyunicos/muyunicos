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

/* ============================================
   SISTEMA DE ENQUEUE MODULAR
   Carga CSS y JS de forma optimizada y condicional
   ============================================ */

function mu_enqueue_assets() {
    $theme_version = wp_get_theme()->get('Version');
    $theme_uri = get_stylesheet_directory_uri();
    
    // CSS Base (siempre)
    wp_enqueue_style(
        'mu-base', 
        get_stylesheet_uri(), 
        array(), 
        $theme_version
    );
    
    // CSS Componentes Globales
    wp_enqueue_style(
        'mu-header', 
        $theme_uri . '/css/components/header.css', 
        array('mu-base'), 
        $theme_version
    );
    
    wp_enqueue_style(
        'mu-footer', 
        $theme_uri . '/css/components/footer.css', 
        array('mu-base'), 
        $theme_version
    );

    // Share button (global; liviano)
    wp_enqueue_style(
        'mu-share',
        $theme_uri . '/css/components/share-button.css',
        array('mu-base'),
        $theme_version
    );
    
    // Modal de Autenticación (solo si no está logueado)
    if (!is_user_logged_in()) {
        wp_enqueue_style(
            'mu-modal-auth', 
            $theme_uri . '/css/components/modal-auth.css', 
            array('mu-base'), 
            $theme_version
        );
        
        wp_enqueue_script(
            'mu-modal-auth-js',
            $theme_uri . '/js/modal-auth.js',
            array(),
            $theme_version,
            true // Footer
        );
    }
    
    // CSS Condicional por Página
    if (is_front_page()) {
        wp_enqueue_style(
            'mu-home', 
            $theme_uri . '/css/home.css', 
            array('mu-base'), 
            $theme_version
        );
    }
    
    if (is_shop() || is_product_category() || is_product_tag()) {
        wp_enqueue_style(
            'mu-shop', 
            $theme_uri . '/css/shop.css', 
            array('mu-base'), 
            $theme_version
        );
    }
    
    if (is_product()) {
        wp_enqueue_style(
            'mu-product', 
            $theme_uri . '/css/product.css', 
            array('mu-base'), 
            $theme_version
        );
    }
    
        // --- CARRITO ---
    if ( is_cart() ) {
        wp_enqueue_style(
            'mu-cart',
            $theme_uri . '/css/cart.css',
            array('mu-base'),
            $theme_version
        );
        wp_enqueue_script(
            'mu-cart-js',
            $theme_uri . '/js/cart.js',
            array('jquery'),
            $theme_version,
            true
        );
        
        // Localize SVG icon para JS
        wp_localize_script( 'mu-cart-js', 'muCartVars', array(
            'closeIcon' => function_exists('mu_get_icon') ? mu_get_icon('close') : ''
        ) );
    }

    // --- CHECKOUT (excluir thank-you page) ---
    if ( is_checkout() && ! is_order_received_page() ) {
        wp_enqueue_style(
            'mu-checkout',
            $theme_uri . '/css/checkout.css',
            array('mu-base'),
            $theme_version
        );

        // Librería externa: libphonenumber (CDN, footer)
        wp_register_script(
            'libphonenumber-js',
            'https://unpkg.com/libphonenumber-js@1.10.49/bundle/libphonenumber-js.min.js',
            array(),
            '1.10.49',
            true
        );

        wp_enqueue_script(
            'mu-checkout-js',
            $theme_uri . '/js/checkout.js',
            array( 'jquery', 'libphonenumber-js' ),
            $theme_version,
            true
        );

        // Puente PHP → JS (reemplaza las vars inline del snippet)
        wp_localize_script( 'mu-checkout-js', 'muCheckout', array(
            'isLoggedIn' => is_user_logged_in(),
            'ajaxUrl'    => WC_AJAX::get_endpoint('mu_check_email'), // Usamos WC_AJAX en vez de admin-ajax
            'nonce'      => wp_create_nonce( 'check-email-nonce' ),
        ) );
    }
	
    // JavaScript Modular
    wp_enqueue_script(
        'mu-header-js',
        $theme_uri . '/js/header.js',
        array(),
        $theme_version,
        true
    );
    
    wp_enqueue_script(
        'mu-footer-js',
        $theme_uri . '/js/footer.js',
        array(),
        $theme_version,
        true
    );
    
    // JavaScript UI helpers (Country selector + WPLingua toggle)
    wp_enqueue_script(
        'mu-ui-scripts',
        $theme_uri . '/js/mu-ui-scripts.js',
        array(),
        $theme_version,
        true
    );
}
add_action('wp_enqueue_scripts', 'mu_enqueue_assets', 20);

/* ============================================
   CSS CONDICIONAL - WPLingua Switcher
   Oculta switcher en hosts no permitidos
   ============================================ */

add_action('wp_enqueue_scripts', function() {
    $allowed_hosts = ['us.muyunicos.com', 'br.muyunicos.com'];
    
    if (is_admin()) {
        return;
    }
    
    $host = $_SERVER['HTTP_HOST'] ?? '';
    
    if (!in_array($host, $allowed_hosts, true)) {
        // Adjuntar al handle de CSS base
        wp_add_inline_style(
            'mu-base',
            '.wplng-switcher { display: none !important; }'
        );
    }
}, 25);

/* ============================================
   MODAL AUTH - LOCALIZE SCRIPT
   Provee datos necesarios para WC-AJAX
   ============================================ */

add_action('wp_enqueue_scripts', 'mu_auth_localize_script', 25);
function mu_auth_localize_script() {
    if (!is_user_logged_in()) {
        // Localizar datos para el script del modal
        wp_localize_script('mu-modal-auth-js', 'muAuthData', array(
            'ajax_url' => WC_AJAX::get_endpoint('%%endpoint%%'),
            'nonce'    => wp_create_nonce('mu_auth_nonce'),
            'home_url' => home_url('/')
        ));
    }
}

/* ============================================
   REPOSITORIO DE ICONOS SVG
   Función central para todos los iconos del sistema
   Migrado desde snippets - Ver MIGRATION-GUIDE.md
   ============================================ */

if ( !function_exists( 'mu_get_icon' ) ) {
    /**
     * Obtiene el SVG de un icono específico
     * 
     * @param string $name Nombre del icono (arrow, instagram, facebook, etc.)
     * @return string HTML del SVG o string vacío si no existe
     */
    function mu_get_icon($name) {
        $icons = [
            'arrow'     => '<svg class="mu-icon-svg muy-svg" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg>',
            'search'    => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>',
            'close'     => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>',

            // UI helpers
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

/* ============================================
   MODAL AUTH - HTML OUTPUT
   Renderiza el modal en el footer
   ============================================ */

add_action('wp_footer', 'mu_auth_modal_html', 5);
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
    
    // Renderizar popup de login social si NextendSocialLogin está activo
    if (class_exists('NextendSocialLogin', false)) {
        do_action('nsl_render_login_form');
    }
}

/* ============================================
   MODAL AUTH - WC-AJAX HANDLERS
   Endpoints optimizados usando wc-ajax
   ============================================ */

// Registrar endpoints WC-AJAX (más rápido que wp_ajax)
add_action('wc_ajax_mu_check_user', 'mu_check_user_exists');
add_action('wc_ajax_mu_login_user', 'mu_handle_login');
add_action('wc_ajax_mu_register_user', 'mu_handle_register');
add_action('wc_ajax_mu_reset_password', 'mu_handle_reset_password');

/**
 * Check si el usuario existe
 */
function mu_check_user_exists() {
    check_ajax_referer('mu_auth_nonce', 'nonce');
    
    $input = sanitize_text_field($_POST['user_input']);
    $user = is_email($input) ? get_user_by('email', $input) : get_user_by('login', $input);
    
    if ($user) {
        wp_send_json_success(array(
            'exists' => true,
            'display_name' => $user->display_name
        ));
    } else {
        wp_send_json_success(array('exists' => false));
    }
}

/**
 * Manejar login
 */
function mu_handle_login() {
    check_ajax_referer('mu_auth_nonce', 'nonce');
    
    $creds = array(
        'user_login'    => sanitize_text_field($_POST['user_login']),
        'user_password' => $_POST['password'],
        'remember'      => true
    );
    
    $user = wp_signon($creds, is_ssl());
    
    if (is_wp_error($user)) {
        wp_send_json_error(array('message' => 'Contraseña incorrecta'));
    }
    
    wp_send_json_success();
}

/**
 * Manejar registro
 */
function mu_handle_register() {
    check_ajax_referer('mu_auth_nonce', 'nonce');
    
    $email    = sanitize_email($_POST['email']);
    $username = sanitize_user($_POST['username']);
    $password = $_POST['password'];
    
    if (email_exists($email)) {
        wp_send_json_error(array('message' => 'Email ya registrado'));
    }
    
    // Crear cliente WooCommerce
    $user_id = wc_create_new_customer($email, $username, $password);
    
    if (is_wp_error($user_id)) {
        wp_send_json_error(array('message' => $user_id->get_error_message()));
    }
    
    // Auto-login después del registro
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true, is_ssl());
    
    wp_send_json_success();
}

/**
 * Manejar recupero de contraseña
 */
function mu_handle_reset_password() {
    check_ajax_referer('mu_auth_nonce', 'nonce');
    
    $login = sanitize_text_field($_POST['user_login']);
    $user = is_email($login) ? get_user_by('email', $login) : get_user_by('login', $login);
    
    if (!$user) {
        wp_send_json_error(array('message' => 'No encontramos esa cuenta.'));
    }
    
    $key = get_password_reset_key($user);
    
    if (is_wp_error($key)) {
        wp_send_json_error(array('message' => 'Error del sistema.'));
    }
    
    // Usar WC Mailer de forma segura
    try {
        $mailer = WC()->mailer();
        $email = $mailer->get_emails()['WC_Email_Customer_Reset_Password'];
        
        if ($email) {
            $email->trigger($user->user_login, $key);
            wp_send_json_success(array(
                'message' => '¡Enviado! Ten en cuenta que puede demorar o marcarse como spam.'
            ));
        }
    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'Error al enviar correo.'));
    }
}

/* ============================================
   HEADER - ICONOS Y FUNCIONALIDAD
   HTML/PHP puro, CSS migrado a /css/components/header.css
   ============================================ */

add_action( 'generate_after_primary_menu', 'mu_header_icons' );
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
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </span>
            <span class="mu-icon-label"></span>
        </a>
        
        <a class="mu-header-icon mu-icon-search" href="#" role="button" aria-label="Buscar" data-gpmodal-trigger="gp-search">
            <span class="mu-icon-wrapper">
                <svg class="mu-icon-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="m21 21-4.35-4.35"/>
                </svg>
            </span>
            <span class="mu-icon-label">Buscar</span>
        </a>
        
        <div class="mu-account-dropdown-wrap">
            <a class="mu-header-icon mu-icon-account mu-open-auth-modal" href="<?php echo esc_url($my_account_url); ?>" title="<?php echo esc_attr($account_label); ?>">
                <span class="mu-icon-wrapper">
                    <svg class="mu-icon-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
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
                    <circle cx="9" cy="21" r="1"/>
                    <circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
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

/* ============================================
   WOOCOMMERCE - AJAX CART FRAGMENTS
   Actualiza badge del carrito sin recargar página
   ============================================ */

add_filter( 'woocommerce_add_to_cart_fragments', 'mu_update_cart_badge' );
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

/* ============================================
   BOTÓN FLOTANTE WHATSAPP
   HTML/PHP puro, CSS migrado a style.css
   ============================================ */

function mu_boton_flotante_whatsapp() {
    ?>
    <a href="https://api.whatsapp.com/send?phone=542235331311&amp;text=Hola!%20te%20escribo%20de%20la%20p%C3%A1gina%20muyunicos.com"
       class="boton-whatsapp"
       target="_blank"
       rel="noopener noreferrer">
        <img src="https://muyunicos.com/wp-content/uploads/2025/10/whatsapp.webp"
             alt="Contacto por WhatsApp">
    </a>
    <?php
}
add_action( 'wp_footer', 'mu_boton_flotante_whatsapp' );

/* ============================================
   FORMULARIO DE BÚSQUEDA DE PRODUCTOS
   HTML/PHP puro, CSS migrado a style.css
   ============================================ */

add_filter( 'get_product_search_form', 'mu_custom_search_form_logic' );
function mu_custom_search_form_logic( $form ) {

    $unique_id = uniqid( 'search-form-' );

    $icon_html = function_exists( 'mu_get_icon' )
        ? mu_get_icon( 'search' )
        : '<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>';

    $form = '<form role="search" method="get" class="woocommerce-product-search mu-product-search" action="' . esc_url( home_url( '/' ) ) . '"><label class="screen-reader-text" for="' . esc_attr( $unique_id ) . '">Buscar productos:</label><div class="mu-search-group"><input type="search" id="' . esc_attr( $unique_id ) . '" class="search-field" placeholder="Buscar en la tienda..." value="' . get_search_query() . '" name="s" /><button type="submit" class="mu-search-submit" aria-label="Buscar">' . $icon_html . '</button><input type="hidden" name="post_type" value="product" /></div></form>';

    return $form;
}

/* ============================================
   MUYUNICOS - Selector de País en Header (Izquierda)
   Ubicación: Inside Header (Left)
   HTML/PHP puro, CSS y JS migrados a archivos separados
   ============================================ */

if ( ! function_exists( 'render_country_redirect_selector' ) ) {
    function render_country_redirect_selector() {
        if ( ! function_exists( 'WC' ) || ! WC()->customer ) {
            return '';
        }

        $countries_data        = muyu_get_countries_data();
        $current_country_code  = WC()->customer->get_billing_country() ?: 'AR';

        if ( ! isset( $countries_data[ $current_country_code ] ) ) {
            $current_country_code = 'AR';
        }

        $current_country_data = $countries_data[ $current_country_code ];
        $request_uri          = $_SERVER['REQUEST_URI'] ?? '/';
        $scheme               = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) ? 'https' : 'http';

        ob_start();
        ?>
        <div id="country-redirect-selector" class="country-redirect-container">
            <div class="country-selector-trigger"
                 title="Cambiar de País"
                 tabindex="0"
                 role="button"
                 aria-haspopup="true"
                 aria-expanded="false">
                <img src="https://flagcdn.com/w40/<?php echo esc_attr( $current_country_data['flag'] ); ?>.png"
                     alt="<?php echo esc_attr( $current_country_data['name'] ); ?>" />
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
                                <img src="https://flagcdn.com/w40/<?php echo esc_attr( $country['flag'] ); ?>.png"
                                     alt="<?php echo esc_attr( $country['name'] ); ?>" />
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

/**
 * Inyectar selector dentro del header GeneratePress
 */
function mu_inject_country_selector_header() {
    if ( ! function_exists( 'render_country_redirect_selector' ) ) {
        return;
    }
    ?>
    <div class="mu-header-country-item">
        <?php echo render_country_redirect_selector(); ?>
    </div>
    <?php
}
add_action( 'generate_header', 'mu_inject_country_selector_header', 1 );

/* ============================================
   FOOTER - ESTRUCTURA CUSTOM
   HTML/PHP puro, CSS migrado a /css/components/footer.css
   ============================================ */

add_action('generate_before_footer', 'muyunicos_custom_footer_structure');
function muyunicos_custom_footer_structure() {
    // Definición de redes sociales
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
                    <p style="opacity: 0.8; line-height: 1.6; margin-bottom: 15px;">
                        Diseños exclusivos y productos personalizados hechos con pasión en Mar del Plata.
                    </p>
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
                            Te ayudamos
                            <span class="gp-icon mu-arrow-icon">
                                <?php echo mu_get_icon('arrow'); ?>
                            </span>
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
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        Compra 100% Protegida
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

/* ============================================
   WC CHECKOUT - OPTIMIZACIONES
   Funciones de negocio (validaciones, UX, campos)
   ============================================ */

/* 1. CONFIGURACIÓN GENERAL (LIGHTWEIGHT) */
add_filter( 'woocommerce_enable_checkout_login_reminder', '__return_false' );
add_filter( 'woocommerce_checkout_registration_enabled', '__return_true' );
add_filter( 'woocommerce_checkout_registration_required', '__return_false' );
add_filter( 'woocommerce_create_account_default_checked', '__return_true' );
add_filter( 'woocommerce_terms_is_checked_default', '__return_true' );
add_filter( 'woocommerce_get_terms_and_conditions_checkbox_text', function($text) {
    return 'He leído y acepto los <a href="/terminos/" target="_blank">términos y condiciones</a> de la web.';
});

/* 2. HELPERS */
if ( ! function_exists( 'muyunicos_has_physical_products' ) ) {
    /**
     * Verifica si hay productos físicos en el carrito.
     * USO DE STATIC: Evita recorrer el array del carrito múltiples veces.
     */
    function muyunicos_has_physical_products() {
        static $has_physical = null;
        
        if ( $has_physical !== null ) {
            return $has_physical;
        }

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

/* 3. MANIPULACIÓN DE CAMPOS */
if ( ! function_exists( 'muyunicos_optimize_checkout_fields' ) ) {
    add_filter( 'woocommerce_checkout_fields', 'muyunicos_optimize_checkout_fields', 9999 );
    function muyunicos_optimize_checkout_fields( $fields ) {
        
        // 1. Unificar Nombre
        $fields['billing']['billing_full_name'] = array(
            'label'       => 'Nombre y Apellido',
            'placeholder' => 'Ej: Juan Pérez',
            'required'    => true,
            'class'       => array('form-row-wide', 'mu-smart-field'),
            'clear'       => true,
            'priority'    => 10, 
        );

        // 2. Ajustes de Prioridad y Clases
        if ( isset( $fields['billing']['billing_country'] ) ) {
            $fields['billing']['billing_country']['priority'] = 20;
            $fields['billing']['billing_country']['class']    = array('form-row-wide');
        }

        $fields['billing']['billing_contact_header'] = array(
            'type'     => 'text',
            'label'    => '', 
            'required' => false,
            'class'    => array('form-row-wide'),
            'priority' => 25,
        );

        $fields['billing']['billing_email']['priority'] = 30;
        $fields['billing']['billing_email']['class']    = array('form-row-wide', 'mu-contact-field');
        $fields['billing']['billing_email']['label']    = '<span class="mu-verified-badge" style="display:none;">✓</span> E-Mail';

        $fields['billing']['billing_phone']['priority']    = 40;
        $fields['billing']['billing_phone']['label']       = 'WhatsApp';
        $fields['billing']['billing_phone']['required']    = false; // Siempre opcional, JS decide
        $fields['billing']['billing_phone']['placeholder'] = 'Ej: 9 223 123 4567';
        $fields['billing']['billing_phone']['class']       = array('form-row-wide', 'mu-contact-field');

        // 3. Lógica Condicional Físico vs Digital
        $is_physical = muyunicos_has_physical_products();
        $address_fields = ['billing_address_1', 'billing_address_2', 'billing_city', 'billing_postcode', 'billing_state'];

        // Eliminamos Company siempre
        unset( $fields['billing']['billing_company'] );

        if ( ! $is_physical ) {
            // MODO DIGITAL: Limpieza total
            foreach ( $address_fields as $key ) unset( $fields['billing'][$key] );
            add_filter( 'woocommerce_cart_needs_shipping', '__return_false' );
        } else {
            // MODO FÍSICO: Toggle y ocultar campos por defecto
            $fields['billing']['billing_shipping_toggle'] = array(
                'type'     => 'text', 
                'label'    => '',
                'required' => false,
                'class'    => array('form-row-wide'),
                'priority' => 45,
            );
            
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
}

/* 4. RENDERIZADO VISUAL (VISTA) */
if ( ! function_exists( 'muyunicos_render_html_fragments' ) ) {
    add_filter( 'woocommerce_form_field', 'muyunicos_render_html_fragments', 10, 4 );
    function muyunicos_render_html_fragments( $field, $key, $args, $value ) {
        if ( $key === 'billing_contact_header' ) {
            return '<div class="form-row form-row-wide" id="muyunicos_header_row" style="margin-bottom:0;">\n                        <div class="mu-contact-header">Te contactamos por:</div>\n                        <div id="mu-email-exists-notice"></div>\n                    </div>';
        }
        
        if ( $key === 'billing_shipping_toggle' ) {
            return '<div class="form-row form-row-wide" id="muyunicos_toggle_row">\n                        <div class="mu-shipping-toggle-wrapper">\n                            <label style="cursor:pointer;">\n                                <input type="checkbox" id="muyunicos-toggle-shipping" name="muyunicos_shipping_toggle" value="1"> \n                                <b>Ingresar datos para envío</b> (Opcional)\n                            </label>\n                        </div>\n                    </div>';
        }

        return $field;
    }
}

/* 5. PROCESAMIENTO Y VALIDACIÓN (BACKEND) */
if ( ! function_exists( 'muyunicos_sanitize_posted_data' ) ) {
    add_filter( 'woocommerce_checkout_posted_data', 'muyunicos_sanitize_posted_data' );
    function muyunicos_sanitize_posted_data( $data ) {
        // Split Nombre Completo
        if ( ! empty( $data['billing_full_name'] ) ) {
            $parts = explode( ' ', trim( $data['billing_full_name'] ), 2 );
            $data['billing_first_name'] = $parts[0];
            $data['billing_last_name']  = isset( $parts[1] ) ? $parts[1] : '.'; 
        }

        // Limpieza de Teléfono: Si es muy corto, lo vaciamos para que pase como "vacío opcional"
        if ( ! empty( $data['billing_phone'] ) ) {
            $digits = preg_replace('/\\D/', '', $data['billing_phone']);
            if ( strlen( $digits ) <= 6 ) {
                $data['billing_phone'] = '';
            }
        }

        return $data;
    }
}

if ( ! function_exists( 'muyunicos_validate_checkout' ) ) {
    add_action( 'woocommerce_checkout_process', 'muyunicos_validate_checkout' );
    function muyunicos_validate_checkout() {
        // Validar Nombre
        if ( empty( $_POST['billing_full_name'] ) ) {
            wc_add_notice( __( 'Por favor, completa tu Nombre y Apellido.' ), 'error' );
        }
        
        // Validación WhatsApp (Confianza en JS)
        if ( ! empty( $_POST['billing_phone'] ) ) {
            if ( isset($_POST['muyunicos_wa_valid']) && $_POST['muyunicos_wa_valid'] === '0' ) {
                 wc_add_notice( __( 'El número de WhatsApp parece incompleto o inválido.' ), 'error' );
            }
        }

        // Validación Dirección (Solo si el toggle está activo)
        if ( isset( $_POST['muyunicos_shipping_toggle'] ) && $_POST['muyunicos_shipping_toggle'] == '1' ) {
            if ( empty( $_POST['billing_address_1'] ) ) wc_add_notice( __( 'La <strong>Dirección</strong> es necesaria para el envío.' ), 'error' );
            if ( empty( $_POST['billing_city'] ) ) wc_add_notice( __( 'La <strong>Ciudad</strong> es necesaria.' ), 'error' );
            if ( empty( $_POST['billing_postcode'] ) ) wc_add_notice( __( 'El <strong>Código Postal</strong> es necesario.' ), 'error' );
            
            if ( empty( $_POST['billing_state'] ) && WC()->countries->get_states( $_POST['billing_country'] ) ) {
                 wc_add_notice( __( 'La <strong>Provincia/Estado</strong> es necesaria.' ), 'error' );
            }
        }
    }
}

/* 6. AJAX HANDLER (Backend) */
if ( ! function_exists( 'muyunicos_ajax_check_email_optimized' ) ) {
    add_action( 'wc_ajax_mu_check_email', 'muyunicos_ajax_check_email_optimized' );
    function muyunicos_ajax_check_email_optimized() {
        // 1. Verificación de seguridad
        check_ajax_referer( 'check-email-nonce', 'security' );
        
        // 2. Sanitización
        $email = isset($_POST['email']) ? sanitize_email( $_POST['email'] ) : '';
        
        // 3. Respuesta rápida JSON
        if ( ! empty($email) && email_exists( $email ) ) {
            wp_send_json( array( 'exists' => true ) );
        } else {
            wp_send_json( array( 'exists' => false ) );
        }
    }
}

/* 7. EXTRAS UI */
add_filter( 'the_title', function( $title, $id ) {
    if ( is_order_received_page() && get_the_ID() === $id && in_the_loop() ) {
        return '¡Pedido Recibido! 🎉';
    }
    return $title;
}, 10, 2 );


/* ============================================
   MIGRADO (Snippets varios)
   Canonical Site Kit + Share + Add-multiple + BACS + Category description
   ============================================ */

// 1) Google Site Kit: canonical fijo de home
if ( ! function_exists( 'mu_googlesitekit_canonical_home_url' ) ) {
    function mu_googlesitekit_canonical_home_url( $url ) {
        return 'https://muyunicos.com';
    }
}
add_filter( 'googlesitekit_canonical_home_url', 'mu_googlesitekit_canonical_home_url' );

// 2) Botón compartir (shortcode: [dcms_share])
if ( ! function_exists( 'dcms_render_share_button' ) ) {
    /**
     * Retorna el HTML del botón.
     * @param string $custom_class Clase CSS extra opcional.
     * @param bool $echo Si debe imprimir o retornar.
     */
    function dcms_render_share_button( $custom_class = '', $echo = true ) {

        $classes = trim( 'mu-share-btn dcms-share-btn ' . $custom_class );

        $icon_share = function_exists( 'mu_get_icon' ) ? mu_get_icon( 'share' ) : '';
        $icon_check = function_exists( 'mu_get_icon' ) ? mu_get_icon( 'check' ) : '';

        $html = sprintf(
            '<button class="%s" type="button" title="Compartir" aria-label="Compartir">' .
            '<span class="dcms-share-icon dcms-share-icon--share" aria-hidden="true">%s</span>' .
            '<span class="dcms-share-icon dcms-share-icon--check" aria-hidden="true">%s</span>' .
            '</button>',
            esc_attr( $classes ),
            $icon_share,
            $icon_check
        );

        if ( $echo ) {
            echo $html;
        } else {
            return $html;
        }
    }
}

add_shortcode( 'dcms_share', function( $atts ) {
    return dcms_render_share_button( 'dcms-share-btn--shortcode', false );
});

// 3) WooCommerce: agregar múltiples productos al carrito por URL (?add-multiple=1,2,3)
add_action( 'wp_loaded', 'woo_add_multiple_products_to_cart' );
if ( ! function_exists( 'woo_add_multiple_products_to_cart' ) ) {
    function woo_add_multiple_products_to_cart() {
        if ( ! isset( $_GET['add-multiple'] ) || empty( $_GET['add-multiple'] ) ) {
            return;
        }

        if ( ! function_exists( 'WC' ) ) {
            return;
        }

        if ( null === WC()->cart && function_exists( 'wc_load_cart' ) ) {
            wc_load_cart();
        }

        if ( null === WC()->cart ) {
            return;
        }

        $product_ids = explode( ',', sanitize_text_field( wp_unslash( $_GET['add-multiple'] ) ) );
        $productos_agregados = false;

        foreach ( $product_ids as $product_id ) {
            $product_id = absint( $product_id );
            if ( $product_id > 0 ) {
                WC()->cart->add_to_cart( $product_id );
                $productos_agregados = true;
            }
        }

        if ( $productos_agregados ) {
            wp_safe_redirect( wc_get_cart_url() );
            exit;
        }
    }
}

// 4) Reemplazar NUMERODEPEDIDO por el ID real en Transferencia Bancaria (BACS)
if ( ! function_exists( 'bacs_buffer_start' ) ) {
    function bacs_buffer_start() {
        ob_start();
    }
}
if ( ! function_exists( 'bacs_buffer_end' ) ) {
    function bacs_buffer_end( $order_id ) {
        $output = ob_get_clean();
        if ( $order_id ) {
            echo str_replace( 'NUMERODEPEDIDO', $order_id, $output );
        } else {
            echo $output;
        }
    }
}
add_action( 'woocommerce_thankyou_bacs', 'bacs_buffer_start', 1 );
add_action( 'woocommerce_thankyou_bacs', 'bacs_buffer_end', 100, 1 );

if ( ! function_exists( 'bacs_email_buffer_start' ) ) {
    function bacs_email_buffer_start( $order, $sent_to_admin, $plain_text, $email ) {
        if ( 'bacs' === $order->get_payment_method() && ! $plain_text ) {
            ob_start();
        }
    }
}
if ( ! function_exists( 'bacs_email_buffer_end' ) ) {
    function bacs_email_buffer_end( $order, $sent_to_admin, $plain_text, $email ) {
        if ( 'bacs' === $order->get_payment_method() && ! $plain_text ) {
            $output = ob_get_clean();
            echo str_replace( 'NUMERODEPEDIDO', $order->get_id(), $output );
        }
    }
}
add_action( 'woocommerce_email_before_order_table', 'bacs_email_buffer_start', 1, 4 );
add_action( 'woocommerce_email_before_order_table', 'bacs_email_buffer_end', 100, 4 );

// 5) WooCommerce: mover descripción de categoría debajo del loop
add_action( 'wp', 'muyunicos_move_category_description' );
if ( ! function_exists( 'muyunicos_move_category_description' ) ) {
    function muyunicos_move_category_description() {
        if ( is_product_category() ) {
            remove_action(
                'woocommerce_archive_description',
                'woocommerce_taxonomy_archive_description',
                10
            );

            add_action(
                'woocommerce_after_shop_loop',
                'woocommerce_taxonomy_archive_description',
                5
            );
        }
    }
}
