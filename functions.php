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
    
    // CSS Condicional por Página
    if (is_front_page()) {
        wp_enqueue_style(
            'mu-home', 
            $theme_uri . '/css/pages/home.css', 
            array('mu-base'), 
            $theme_version
        );
    }
    
    if (is_shop() || is_product_category() || is_product_tag()) {
        wp_enqueue_style(
            'mu-shop', 
            $theme_uri . '/css/pages/shop.css', 
            array('mu-base'), 
            $theme_version
        );
    }
    
    if (is_product()) {
        wp_enqueue_style(
            'mu-product', 
            $theme_uri . '/css/pages/product.css', 
            array('mu-base'), 
            $theme_version
        );
    }
    
    if (is_cart()) {
        wp_enqueue_style(
            'mu-cart', 
            $theme_uri . '/css/pages/cart.css', 
            array('mu-base'), 
            $theme_version
        );
    }
    
    if (is_checkout()) {
        wp_enqueue_style(
            'mu-checkout', 
            $theme_uri . '/css/pages/checkout.css', 
            array('mu-base'), 
            $theme_version
        );
    }
    
    // JavaScript Modular
    wp_enqueue_script(
        'mu-header-js',
        $theme_uri . '/assets/js/header.js',
        array(),
        $theme_version,
        true // Cargar en footer con defer implícito
    );
    
    wp_enqueue_script(
        'mu-footer-js',
        $theme_uri . '/assets/js/footer.js',
        array(),
        $theme_version,
        true
    );
}
add_action('wp_enqueue_scripts', 'mu_enqueue_assets', 20);

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
            'instagram' => '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12,4.622c2.403,0,2.688,0.009,3.637,0.052c0.877,0.04,1.354,0.187,1.671,0.31c0.42,0.163,0.72,0.358,1.035,0.673 c0.315,0.315,0.51,0.615,0.673,1.035c0.123,0.317,0.27,0.794,0.31,1.671c0.043,0.949,0.052,1.234,0.052,3.637 s-0.009,2.688-0.052,3.637c-0.04,0.877-0.187,1.354-0.31,1.671c-0.163,0.42-0.358,0.72-0.673,1.035 c-0.315,0.315-0.615,0.51-1.035,0.673c-0.317,0.123-0.794,0.27-1.671,0.31c-0.949,0.043-1.233,0.052-3.637,0.052 s-2.688-0.009-3.637-0.052c-0.877-0.04-1.354-0.187-1.671-0.31c-0.42-0.163-0.72-0.358-1.035-0.673 c-0.315-0.315-0.51-0.615-0.673-1.035c-0.123-0.317-0.27-0.794-0.31-1.671C4.631,14.688,4.622,14.403,4.622,12 s0.009-2.688,0.052-3.637c0.04-0.877,0.187-1.354,0.31-1.671c0.163-0.42,0.358-0.72,0.673-1.035 c0.315-0.315,0.615-0.51,1.035-0.673c0.317-0.123,0.794-0.27,1.671-0.31C9.312,4.631,9.597,4.622,12,4.622 M12,3 C9.556,3,9.249,3.01,8.289,3.054C7.331,3.098,6.677,3.25,6.105,3.472C5.513,3.702,5.011,4.01,4.511,4.511 c-0.5,0.5-0.808,1.002-1.038,1.594C3.25,6.677,3.098,7.331,3.054,8.289C3.01,9.249,3,9.556,3,12c0,2.444,0.01,2.751,0.054,3.711 c0.044,0.958,0.196,1.612,0.418,2.185c0.23,0.592,0.538,1.094,1.038,1.594c0.5,0.5,1.002,0.808,1.594,1.038 c0.572,0.222,1.227,0.375,2.185,0.418C9.249,20.99,9.556,21,12,21s2.751-0.01,3.711-0.054c0.958-0.044,1.612-0.196,2.185-0.418 c0.592-0.23,1.094-0.538,1.594-1.038c0.5-0.5,0.808-1.002,1.038-1.594c0.222-0.572,0.375-1.227,0.418-2.185 C20.99,14.751,21,14.444,21,12s-0.01-2.751-0.054-3.711c-0.044-0.958-0.196-1.612-0.418-2.185c-0.23-0.592-0.538-1.094-1.038-1.594 c-0.5-0.5-1.002-0.808-1.594-1.038c-0.572-0.222-1.227-0.375-2.185-0.418C14.751,3.01,14.444,3,12,3L12,3z M12,7.378 c-2.552,0-4.622,2.069-4.622,4.622S9.448,16.622,12,16.622s4.622-2.069,4.622-4.622S14.552,7.378,12,7.378z M12,15 c-1.657,0-3-1.343-3-3s1.343-3,3-3s3,1.343,3,3S13.657,15,12,15z M16.804,6.116c-0.596,0-1.08,0.484-1.08,1.08 s0.484,1.08,1.08,1.08c0.596,0,1.08-0.484,1.08-1.08S17.401,6.116,16.804,6.116z"></path></svg>',
            'facebook'  => '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 2C6.5 2 2 6.5 2 12c0 5 3.7 9.1 8.4 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.3v7C18.3 21.1 22 17 22 12c0-5.5-4.5-10-10-10z"></path></svg>',
            'pinterest' => '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12.289,2C6.617,2,3.606,5.648,3.606,9.622c0,1.846,1.025,4.146,2.666,4.878c0.25,0.111,0.381,0.063,0.439-0.169 c0.044-0.175,0.267-1.029,0.365-1.428c0.032-0.128,0.017-0.237-0.091-0.362C6.445,11.911,6.01,10.75,6.01,9.668 c0-2.777,2.194-5.464,5.933-5.464c3.23,0,5.49,2.108,5.49,5.122c0,3.407-1.794,5.768-4.13,5.768c-1.291,0-2.257-1.021-1.948-2.277 c0.372-1.495,1.089-3.112,1.089-4.191c0-0.967-0.542-1.775-1.663-1.775c-1.319,0-2.379,1.309-2.379,3.059 c0,1.115,0.394,1.869,0.394,1.869s-1.302,5.279-1.54,6.261c-0.405,1.666,0.053,4.368,0.094,4.604 c0.021,0.126,0.167,0.169,0.25,0.063c0.129-0.165,1.699-2.419,2.142-4.051c0.158-0.59,0.817-2.995,0.817-2.995 c0.43,0.784,1.681,1.446,3.013,1.446c3.963,0,6.822-3.494,6.822-7.833C20.394,5.112,16.849,2,12.289,2"></path></svg>',
            'tiktok'    => '<svg width="24" height="24" viewBox="0 0 32 32" aria-hidden="true" fill="currentColor"><path d="M16.708 0.027c1.745-0.027 3.48-0.011 5.213-0.027 0.105 2.041 0.839 4.12 2.333 5.563 1.491 1.479 3.6 2.156 5.652 2.385v5.369c-1.923-0.063-3.855-0.463-5.6-1.291-0.76-0.344-1.468-0.787-2.161-1.24-0.009 3.896 0.016 7.787-0.025 11.667-0.104 1.864-0.719 3.719-1.803 5.255-1.744 2.557-4.771 4.224-7.88 4.276-1.907 0.109-3.812-0.411-5.437-1.369-2.693-1.588-4.588-4.495-4.864-7.615-0.032-0.667-0.043-1.333-0.016-1.984 0.24-2.537 1.495-4.964 3.443-6.615 2.208-1.923 5.301-2.839 8.197-2.297 0.027 1.975-0.052 3.948-0.052 5.923-1.323-0.428-2.869-0.308-4.025 0.495-0.844 0.547-1.485 1.385-1.819 2.333-0.276 0.676-0.197 1.427-0.181 2.145 0.317 2.188 2.421 4.027 4.667 3.828 1.489-0.016 2.916-0.88 3.692-2.145 0.251-0.443 0.532-0.896 0.547-1.417 0.131-2.385 0.079-4.76 0.095-7.145 0.011-5.375-0.016-10.735 0.025-16.093z"></path></svg>',
            'youtube'   => '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M21.8,8.001c0,0-0.195-1.378-0.795-1.985c-0.76-0.797-1.613-0.801-2.004-0.847c-2.799-0.202-6.997-0.202-6.997-0.202 h-0.009c0,0-4.198,0-6.997,0.202C4.608,5.216,3.756,5.22,2.995,6.016C2.395,6.623,2.2,8.001,2.2,8.001S2,9.62,2,11.238v1.517 c0,1.618,0.2,3.237,0.2,3.237s0.195,1.378,0.795,1.985c0.761,0.797,1.76,0.771,2.205,0.855c1.6,0.153,6.8,0.201,6.8,0.201 s4.203-0.006,7.001-0.209c0.391-0.047,1.243-0.051,2.004-0.847c0.6-0.607,0.795-1.985,0.795-1.985s0.2-1.618,0.2-3.237v-1.517 C22,9.62,21.8,8.001,21.8,8.001z M9.935,14.594l-0.001-5.62l5.404,2.82L9.935,14.594z"></path></svg>'
        ];
        return $icons[$name] ?? '';
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
