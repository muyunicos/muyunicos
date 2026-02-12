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
    
    // JavaScript del Header
    wp_enqueue_script(
        'mu-header-js',
        $theme_uri . '/assets/js/header.js',
        array(),
        $theme_version,
        true // Cargar en footer con defer implícito
    );
}
add_action('wp_enqueue_scripts', 'mu_enqueue_assets', 20);

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
