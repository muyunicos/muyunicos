<?php
/**
 * Muy Únicos - Componentes UI y UX
 *
 * Incluye:
 * - WPLingua body class (ocultar switcher en subdominios sin multilenguaje)
 * - Iconos del header (búsqueda, cuenta, carrito)
 * - Custom Footer
 * - Formulario de búsqueda customizado
 * - Botón flotante de WhatsApp
 * - Shortcode de compartir
 * - Canonical URL para Google Site Kit
 *
 * @package GeneratePress_Child
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================
// WPLINGUA — BODY CLASS
// Ocultar switcher en subdominios sin multilenguaje.
// El CSS de ocultación vive en css/components/global-ui.css
// (selector: body.mu-wplng-hide .wplng-switcher).
// ============================================

if ( ! function_exists( 'mu_wplng_body_class' ) ) {
    function mu_wplng_body_class( $classes ) {
        $allowed_hosts = [ 'us.muyunicos.com', 'br.muyunicos.com' ];
        $host = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '';
        if ( ! in_array( $host, $allowed_hosts, true ) ) {
            $classes[] = 'mu-wplng-hide';
        }
        return $classes;
    }
    add_filter( 'body_class', 'mu_wplng_body_class' );
}

// ============================================
// ICONOS DEL HEADER
// ============================================

if ( ! function_exists( 'mu_header_icons' ) ) {
    function mu_header_icons() {
        $cart_count       = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
        $is_logged_in     = is_user_logged_in();
        $my_account_url   = get_permalink( get_option( 'woocommerce_myaccount_page_id' ) );
        $edit_account_url = wc_get_account_endpoint_url( 'edit-account' );
        $downloads_url    = wc_get_account_endpoint_url( 'downloads' );
        $logout_url       = wp_logout_url( home_url() );
        $account_label    = $is_logged_in ? 'Mi cuenta' : 'Ingresar';
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
                <a class="mu-header-icon mu-icon-account mu-open-auth-modal" href="<?php echo esc_url( $my_account_url ); ?>" title="<?php echo esc_attr( $account_label ); ?>">
                    <span class="mu-icon-wrapper">
                        <svg class="mu-icon-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                        </svg>
                    </span>
                    <span class="mu-icon-label">
                        <?php echo esc_html( $account_label ); ?>
                        <?php if ( $is_logged_in ) : ?>
                             <span class="gp-icon icon-arrow"> <?php echo mu_get_icon( 'arrow' ); ?> </span>
                        <?php endif; ?>
                    </span>
                </a>
                <?php if ( $is_logged_in ) : ?>
                <ul class="mu-sub-menu">
                    <li><a href="<?php echo esc_url( $edit_account_url ); ?>">Detalles de la cuenta</a></li>
                    <li><a href="<?php echo esc_url( $downloads_url ); ?>">Mis Descargas</a></li>
                    <li class="mu-logout-item"><a href="<?php echo esc_url( $logout_url ); ?>">Salir</a></li>
                </ul>
                <?php endif; ?>
            </div>
            <a class="mu-header-icon mu-icon-cart" href="/carrito/" title="Carrito">
                <span class="mu-icon-wrapper">
                    <svg class="mu-icon-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                    </svg>
                    <span class="mu-cart-badge <?php echo ( $cart_count > 0 ) ? 'is-visible' : ''; ?>">
                        <?php echo esc_html( $cart_count ); ?>
                    </span>
                </span>
                <span class="mu-icon-label">Carrito</span>
            </a>
        </div>
        <?php
    }
    add_action( 'generate_after_primary_menu', 'mu_header_icons' );
}

if ( ! function_exists( 'mu_update_cart_badge' ) ) {
    function mu_update_cart_badge( $fragments ) {
        $cart_count = WC()->cart->get_cart_contents_count();
        ob_start();
        ?>
        <span class="mu-cart-badge <?php echo ( $cart_count > 0 ) ? 'is-visible' : ''; ?>">
            <?php echo esc_html( $cart_count ); ?>
        </span>
        <?php
        $fragments['.mu-cart-badge'] = ob_get_clean();
        return $fragments;
    }
    add_filter( 'woocommerce_add_to_cart_fragments', 'mu_update_cart_badge' );
}

// ============================================
// BOTÓN FLOTANTE WHATSAPP
// ============================================

if ( ! function_exists( 'mu_boton_flotante_whatsapp' ) ) {
    function mu_boton_flotante_whatsapp() {
        ?>
        <a href="https://api.whatsapp.com/send?phone=542235331311&amp;text=Hola!%20te%20escribo%20de%20la%20p%C3%A1gina%20muyunicos.com"
           class="boton-whatsapp" target="_blank" rel="noopener noreferrer">
            <img src="https://muyunicos.com/wp-content/uploads/2025/10/whatsapp.webp" alt="Contacto por WhatsApp">
        </a>
        <?php
    }
    add_action( 'wp_footer', 'mu_boton_flotante_whatsapp' );
}

// ============================================
// FORMULARIO DE BÚSQUEDA CUSTOM
// ============================================

if ( ! function_exists( 'mu_custom_search_form_logic' ) ) {
    function mu_custom_search_form_logic( $form ) {
        $unique_id = uniqid( 'search-form-' );
        // fix: esc_attr() on get_search_query() prevents XSS via crafted search queries
        $icon_html = function_exists( 'mu_get_icon' ) ? mu_get_icon( 'search' ) : '<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>';

        $form  = '<form role="search" method="get" class="woocommerce-product-search mu-product-search" action="' . esc_url( home_url( '/' ) ) . '">';
        $form .= '<label class="screen-reader-text" for="' . esc_attr( $unique_id ) . '">Buscar productos:</label>';
        $form .= '<div class="mu-search-group">';
        $form .= '<input type="search" id="' . esc_attr( $unique_id ) . '" class="search-field" placeholder="Buscar en la tienda..." value="' . esc_attr( get_search_query() ) . '" name="s" />';
        $form .= '<button type="submit" class="mu-search-submit" aria-label="Buscar">' . $icon_html . '</button>';
        $form .= '<input type="hidden" name="post_type" value="product" />';
        $form .= '</div></form>';

        return $form;
    }
    add_filter( 'get_product_search_form', 'mu_custom_search_form_logic' );
}

// ============================================
// CUSTOM FOOTER
// ============================================

if ( ! function_exists( 'muyunicos_custom_footer_structure' ) ) {
    function muyunicos_custom_footer_structure() {
        $social_networks = [
            [ 'name' => 'Instagram', 'url' => 'https://www.instagram.com/muyunicos', 'id' => 'instagram' ],
            [ 'name' => 'Facebook',  'url' => 'https://www.facebook.com/muyunicos',  'id' => 'facebook' ],
            [ 'name' => 'TikTok',    'url' => 'https://www.tiktok.com/@muyunicos',   'id' => 'tiktok' ],
            [ 'name' => 'YouTube',   'url' => 'https://www.youtube.com/@muyunicos',  'id' => 'youtube' ],
            [ 'name' => 'Pinterest', 'url' => 'https://www.pinterest.com/muyunicos', 'id' => 'pinterest' ],
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
                                Te ayudamos <span class="gp-icon mu-arrow-icon"><?php echo mu_get_icon( 'arrow' ); ?></span>
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
                            <?php
                            if ( function_exists( 'get_product_search_form' ) ) {
                                get_product_search_form();
                            } else {
                                ?>
                                <form role="search" method="get" class="woocommerce-product-search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
                                    <input type="search" class="search-field" placeholder="Buscar productos..." value="<?php echo esc_attr( get_search_query() ); ?>" name="s" />
                                    <button type="submit">Buscar</button>
                                    <input type="hidden" name="post_type" value="product" />
                                </form>
                                <?php
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Socket (Barra Inferior) -->
            <div class="mu-socket">
                <div class="mu-container mu-socket-inner">
                    <div class="mu-copyright">
                        &copy; 2022-<?php echo date( 'Y' ); ?> <strong>Muy Únicos</strong>. Mar del Plata.
                    </div>
                    <div class="mu-social-icons">
                        <?php foreach ( $social_networks as $net ) : ?>
                            <a href="<?php echo esc_url( $net['url'] ); ?>" class="mu-social-link" target="_blank" aria-label="<?php echo esc_attr( $net['name'] ); ?>">
                                <?php echo mu_get_icon( $net['id'] ); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </footer>
        <?php
    }
    add_action( 'generate_before_footer', 'muyunicos_custom_footer_structure' );
}

// ============================================
// SHORTCODE COMPARTIR
// ============================================

if ( ! function_exists( 'mu_dcms_share_shortcode' ) ) {
    function mu_dcms_share_shortcode( $atts ) {
        $icon_share = function_exists( 'mu_get_icon' ) ? mu_get_icon( 'share' ) : '';
        return sprintf( '<button class="dcms-share-btn" type="button" title="Compartir" aria-label="Compartir">%s</button>', $icon_share );
    }
    add_shortcode( 'dcms_share', 'mu_dcms_share_shortcode' );
}

// ============================================
// GOOGLE SITE KIT CANONICAL
// ============================================

if ( ! function_exists( 'mu_googlesitekit_canonical_home_url' ) ) {
    function mu_googlesitekit_canonical_home_url( $url ) {
        return 'https://muyunicos.com';
    }
    add_filter( 'googlesitekit_canonical_home_url', 'mu_googlesitekit_canonical_home_url' );
}
