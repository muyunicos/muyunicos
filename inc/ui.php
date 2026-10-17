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
 * - Shortcode de compartir (refactorizado)
 * - Canonical URL para Google Site Kit
 * - Mover descripción de categoría al final del loop
 * - Mostrar "¡Gratis!" en productos con precio $0
 * - Desactivar Imagen Destacada en cabecera
 * - Shortcode Testimonios / Reseñas Google [mu_testimonios_section]
 * - Shortcodes Home: [mu_bestsellers_section] + [mu_popcat_section]
 * - Shortcode Hero Promos Dinámicas [mu_hero_section]
 *
 * @package GeneratePress_Child
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================
// WPLINGUA — BODY CLASS
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
        if ( ! function_exists( 'WC' ) ) return;

        $cart_count       = ( null !== WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0;
        $is_logged_in     = is_user_logged_in();
        $my_account_url   = wc_get_page_permalink( 'myaccount' );
        $edit_account_url = wc_get_account_endpoint_url( 'edit-account' );
        $downloads_url    = wc_get_account_endpoint_url( 'downloads' );
        $logout_url       = wp_logout_url( home_url() );
        $account_label    = $is_logged_in ? 'Mi cuenta' : 'Ingresar';
        ?>
        <div class="mu-header-icons">
            <a class="mu-header-icon mu-icon-help" href="<?php echo esc_url( home_url( '/terminos/' ) ); ?>" title="Ayuda">
                <span class="mu-icon-wrapper"><?php echo mu_get_icon( 'help' ); ?></span>
                <span class="mu-icon-label"></span>
            </a>
            <a class="mu-header-icon mu-icon-search" href="#" role="button" aria-label="Buscar" data-gpmodal-trigger="gp-search">
                <span class="mu-icon-wrapper"><?php echo mu_get_icon( 'search' ); ?></span>
                <span class="mu-icon-label">Buscar</span>
            </a>
            <div class="mu-account-dropdown-wrap">
                <a class="mu-header-icon mu-icon-account mu-open-auth-modal" href="<?php echo esc_url( $my_account_url ); ?>" title="<?php echo esc_attr( $account_label ); ?>">
                    <span class="mu-icon-wrapper"><?php echo mu_get_icon( 'account' ); ?></span>
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
            <a class="mu-header-icon mu-icon-cart" href="<?php echo esc_url( wc_get_cart_url() ); ?>" title="Carrito">
                <span class="mu-icon-wrapper">
                    <?php echo mu_get_icon( 'cart' ); ?>
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
        if ( ! function_exists( 'WC' ) || null === WC()->cart ) return $fragments;
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
           class="boton-whatsapp" target="_blank" rel="noopener noreferrer"
           aria-label="Escribir por WhatsApp">
            <img src="https://muyunicos.com/wp-content/uploads/2025/10/whatsapp.webp" alt="Contacto por WhatsApp">
        </a>
        <?php
    }
    add_action( 'wp_footer', 'mu_boton_flotante_whatsapp' );
}

// ============================================
// FORMULARIO DE BÚSQL CUSTOM
// ============================================

if ( ! function_exists( 'mu_custom_search_form_logic' ) ) {
    function mu_custom_search_form_logic( $form ) {
        $unique_id = uniqid( 'search-form-' );
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
                    <div class="mu-footer-col mu-col-links">
                        <details class="mu-accordion">
                            <summary class="mu-footer-title">
                                Te ayudamos <span class="gp-icon mu-arrow-icon"><?php echo mu_get_icon( 'arrow' ); ?></span>
                            </summary>
                            <div class="mu-accordion-content">
                                <ul class="mu-footer-links">
                                    <li><a href="/mi-cuenta/">Mi Cuenta</a></li>
                                    <li><a href="/mi-cuenta/downloads/">Mis Descargas</a></li>
                                    <?php
                                    /**
                                     * "Información de Envíos" describe la logística de Mar del
                                     * Plata (zonas, costos, retiro): sólo aplica a Argentina.
                                     * El enlace sale del mismo registro que la ruta, para que no
                                     * puedan desincronizarse (inc/country-scope.php — guía §6).
                                     */
                                    $mu_envios = function_exists( 'muyu_get_country_scope_route' )
                                        ? muyu_get_country_scope_route( 'envios' )
                                        : null;
                                    if ( null === $mu_envios || muyu_is_country( $mu_envios['countries'] ) ) :
                                        ?>
                                        <li><a href="/envios/"><?php echo esc_html( $mu_envios['label'] ?? 'Información de Envíos' ); ?></a></li>
                                    <?php endif; ?>
                                    <li><a href="/privacy-policy/">Políticas</a></li>
                                    <li><a href="/reembolso_devoluciones/" class="mu-regret-btn">Botón de arrepentimiento</a></li>
                                </ul>
                            </div>
                        </details>
                    </div>
                    <div class="mu-footer-col mu-col-pay">
                        <h3 class="mu-footer-title">Pagá seguro</h3>
                        <div class="mu-payment-icons">
                            <img decoding="async" src="https://muyunicos.com/wp-content/uploads/2026/01/medios.png" alt="Medios de Pago" width="200">
                        </div>
                        <div class="mu-secure-badge">
                            <a href="http://qr.afip.gob.ar/?qr=6MzPqG4S-KxbKdfR2TTWIQ,," target="_blank" rel="noopener noreferrer" aria-label="Verificar los datos de facturación en AFIP (se abre en una pestaña nueva)"><img src="https://www.afip.gob.ar/images/f960/DATAWEB.jpg" alt="Botón de verificación de datos de facturación ante AFIP" loading="lazy"></a>
                            <?php echo function_exists( 'mu_get_icon' ) ? mu_get_icon( 'lock' ) : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>'; ?> Compra 100% Protegida
                        </div>
                    </div>
                    <div class="mu-footer-col mu-col-search">
                        <h3 class="mu-footer-title">¿Buscás algo?</h3>
                        <div class="mu-footer-search">
                            <?php
                            if ( function_exists( 'get_product_search_form' ) ) {
                                get_product_search_form();
                            } else { ?>
                                <form role="search" method="get" class="woocommerce-product-search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
                                    <input type="search" class="search-field" placeholder="Buscar productos..." value="<?php echo esc_attr( get_search_query() ); ?>" name="s" />
                                    <button type="submit">Buscar</button>
                                    <input type="hidden" name="post_type" value="product" />
                                </form>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
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
        $icon_check = function_exists( 'mu_get_icon' ) ? mu_get_icon( 'check' ) : '';
        return sprintf(
            '<button class="dcms-share-btn mu-share-btn" type="button" title="Compartir" aria-label="Compartir">
                <span class="dcms-share-icon dcms-share-icon--share">%s</span>
                <span class="dcms-share-icon dcms-share-icon--check">%s</span>
            </button>',
            $icon_share,
            $icon_check
        );
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

// ============================================
// MOVER DESCRIPCIÓN DE CATEGORÍA
// ============================================

if ( ! function_exists( 'mu_move_category_description' ) ) {
    function mu_move_category_description() {
        if ( function_exists( 'mu_wc_is_product_category' ) && mu_wc_is_product_category() ) {
            remove_action( 'woocommerce_archive_description', 'woocommerce_taxonomy_archive_description', 10 );
            add_action( 'woocommerce_after_shop_loop', 'woocommerce_taxonomy_archive_description', 5 );
        }
    }
    add_action( 'wp', 'mu_move_category_description' );
}

// ============================================
// MOSTRAR "¡GRATIS!" EN PRODUCTOS $0 (OFERTA)
// ============================================

if ( ! function_exists( 'mu_opt_mostrar_gratis_si_precio_cero' ) ) {
    function mu_opt_mostrar_gratis_si_precio_cero( $price, $product ) {
        if ( is_admin() && ! wp_doing_ajax() ) return $price;
        if ( ! $product->is_on_sale() ) return $price;
        if ( (float) $product->get_sale_price() !== 0.0 ) return $price;

        $regular_price_html = wc_price( $product->get_regular_price() );
        $free_text = __( '¡Gratis!', 'woocommerce' );
        return sprintf( '<del aria-hidden="true">%s</del> <ins>%s</ins>', $regular_price_html, $free_text );
    }
    add_filter( 'woocommerce_get_price_html', 'mu_opt_mostrar_gratis_si_precio_cero', 100, 2 );
}

// ============================================
// SISTEMA REUTILIZABLE DE PERSONALIZACIÓN DE PRODUCTOS
// ============================================

if ( ! defined( 'MU_PRODUCT_CUSTOMIZATIONS' ) ) {
    define( 'MU_PRODUCT_CUSTOMIZATIONS', [
        27859 => [
            'price_text' => 'Armá tu presupuesto',
            'button_text' => 'Cotizar',
            'button_action' => 'product_page',
        ],
    ] );
}

if ( ! function_exists( 'mu_custom_product_pricing' ) ) {
    function mu_custom_product_pricing( $price, $product ) {
        if ( is_admin() && ! wp_doing_ajax() ) return $price;

        // Sistema existente: precios $0 en oferta → ¡Gratis!
        if ( $product->is_on_sale() && (float) $product->get_sale_price() === 0.0 ) {
            $regular_price_html = wc_price( $product->get_regular_price() );
            $free_text = __( '¡Gratis!', 'woocommerce' );
            return sprintf( '<del aria-hidden="true">%s</del> <ins>%s</ins>', $regular_price_html, $free_text );
        }

        // Nuevas personalizaciones por ID
        $customizations = MU_PRODUCT_CUSTOMIZATIONS;
        $product_id = $product->get_id();

        if ( isset( $customizations[ $product_id ]['price_text'] ) ) {
            return '<span class="price">' . $customizations[ $product_id ]['price_text'] . '</span>';
        }

        return $price;
    }
    add_filter( 'woocommerce_get_price_html', 'mu_custom_product_pricing', 15, 2 );
}

if ( ! function_exists( 'mu_custom_product_button' ) ) {
    function mu_custom_product_button( $link, $product ) {
        $customizations = MU_PRODUCT_CUSTOMIZATIONS;
        $product_id = $product->get_id();

        if ( ! isset( $customizations[ $product_id ] ) ) {
            return $link;
        }

        $config = $customizations[ $product_id ];

        // Reemplazar texto del botón
        if ( isset( $config['button_text'] ) ) {
            $link = str_replace( 'Comprar', $config['button_text'], $link );
        }

        // Cambiar acción del botón
        if ( isset( $config['button_action'] ) && $config['button_action'] === 'product_page' ) {
            $product_url = get_permalink( $product_id );
            $link = preg_replace( '/href="[^"]*"/', 'href="' . $product_url . '"', $link );
            $link = str_replace( 'ajax_add_to_cart', '', $link );
            $link = str_replace( 'add_to_cart_button', '', $link );
        }

        return $link;
    }
    add_filter( 'woocommerce_loop_add_to_cart_link', 'mu_custom_product_button', 10, 2 );
}

// ============================================
// DESACTIVAR IMAGEN DESTACADA (PERFORMANCE)
// ============================================

if ( ! function_exists( 'mu_desactivar_imagen_destacada_html' ) ) {
    function mu_desactivar_imagen_destacada_html() {
        if ( is_admin() ) return;
        remove_action( 'generate_after_header', 'generate_featured_page_header_area', 10 );
        remove_action( 'generate_before_content', 'generate_featured_page_header_area', 10 );
    }
    add_action( 'wp', 'mu_desactivar_imagen_destacada_html' );
}

// ============================================
// SHORTCODE TESTIMONIOS / RESEÑAS GOOGLE
// Uso: [mu_testimonios_section]
// API Key: define('MU_GOOGLE_PLACES_API_KEY','...') en wp-config.php
// ============================================

if ( ! function_exists( 'mu_can_manage_reviews' ) ) {
    /**
     * Gate único de la gestión de reseñas (editor del front + handlers AJAX).
     *
     * Antes era current_user_can('administrator'), un chequeo de ROL: quedaban
     * afuera los shop managers que sí administran Hero Banners. Se usa la misma
     * capability que el resto del panel (manage_woocommerce) con fallback a
     * manage_options.
     *
     * @return bool
     */
    function mu_can_manage_reviews() {
        return current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' );
    }
}

if ( ! function_exists( 'mu_testimonios_i18n' ) ) {
    /**
     * Cadenas fijas de la sección por idioma.
     *
     * 'es' es la base: si una clave falta en el idioma actual se usa la de
     * español (mismo fallback que las traducciones de cada reseña). El idioma
     * sale de muyu_get_current_language() (geo.php), nunca del navegador.
     *
     * @return array<string,string> Mapa clave => texto del idioma actual.
     */
    function mu_testimonios_i18n() {
        $strings = [
            'es' => [
                'sectionTitle'     => 'Clientes Felices',
                'ctaReview'        => 'Déjanos tu reseña',
                'loading'          => 'Cargando opiniones…',
                'rotateTitle'      => 'Ver otras opiniones',
                'purchasePhotoAlt' => 'Foto de la compra',
                'justNow'          => 'hace un momento',
            ],
            'pt' => [
                'sectionTitle'     => 'Clientes Satisfeitos',
                'ctaReview'        => 'Deixe sua avaliação',
                'loading'          => 'Carregando avaliações…',
                'rotateTitle'      => 'Ver outras avaliações',
                'purchasePhotoAlt' => 'Foto da compra',
                'justNow'          => 'há instantes',
            ],
            'en' => [
                'sectionTitle'     => 'Happy Customers',
                'ctaReview'        => 'Leave us a review',
                'loading'          => 'Loading reviews…',
                'rotateTitle'      => 'See other reviews',
                'purchasePhotoAlt' => 'Purchase photo',
                'justNow'          => 'just now',
            ],
        ];

        $lang = function_exists( 'muyu_get_current_language' ) ? muyu_get_current_language() : 'es';

        return isset( $strings[ $lang ] ) ? $strings[ $lang ] : $strings['es'];
    }
}

if ( ! function_exists( 'mu_testimonios_enqueue' ) ) {
    function mu_testimonios_enqueue() {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'mu_testimonios_section' ) ) return;

        $ver = wp_get_theme()->get( 'Version' );
        $dir = get_stylesheet_directory();
        $uri = get_stylesheet_directory_uri();

        // Cache-busting por filemtime: la Version del tema es fija, así que un
        // JS viejo podía convivir con el modal nuevo (inline, siempre fresco).
        // Mismo criterio que inc/hero-banners.php.
        $css_file = $dir . '/css/testimonials.css';
        $js_file  = $dir . '/js/testimonials.js';
        $css_ver  = file_exists( $css_file ) ? (string) filemtime( $css_file ) : $ver;
        $js_ver   = file_exists( $js_file ) ? (string) filemtime( $js_file ) : $ver;

        wp_enqueue_style( 'mu-testimonials', $uri . '/css/testimonials.css', [], $css_ver );
        wp_enqueue_script( 'mu-testimonials', $uri . '/js/testimonials.js', [], $js_ver, true );

        // Componente compartido (píldoras ES|PT|EN + botón "Traducir con IA") que
        // también usa el editor de hero banners: una sola definición para los dos.
        $pills_file = $dir . '/css/components/ai-translate-inline.css';
        $pills_ver  = file_exists( $pills_file ) ? (string) filemtime( $pills_file ) : $ver;

        wp_enqueue_style( 'mu-ai-translate-inline', $uri . '/css/components/ai-translate-inline.css', [], $pills_ver );

        // Editor de reseñas: archivo físico (constitución II: nada de JS inline en el
        // HTML de salida), solo para quien puede gestionar reseñas y con dependencia
        // explícita de mu-testimonials, que es quien define muTestimonials.
        if ( mu_can_manage_reviews() ) {
            $modal_file = $dir . '/js/reviews-admin-modal.js';
            $modal_ver  = file_exists( $modal_file ) ? (string) filemtime( $modal_file ) : $ver;

            wp_enqueue_script( 'mu-reviews-admin-modal', $uri . '/js/reviews-admin-modal.js', [ 'mu-testimonials' ], $modal_ver, true );
        }
        
        // Localize script en el enqueue, no en el shortcode
        if ( wp_script_is( 'mu-testimonials', 'enqueued' ) ) {
            $db_option_name = 'mu_reviews_master_db';
            wp_localize_script( 'mu-testimonials', 'muTestimonials', [
                'reviews' => get_option( $db_option_name, [] ),
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'mu_review_nonce' ),
                'isAdmin' => mu_can_manage_reviews(),
                // Nonce del servicio de traducción con IA (botón "Traducir" del editor).
                'aiNonce' => wp_create_nonce( 'mu_ai_nonce' ),
                // Idioma de la URL actual + cadenas de la sección (el front elige
                // la traducción del comentario con fallback a español).
                'lang'    => function_exists( 'muyu_get_current_language' ) ? muyu_get_current_language() : 'es',
                'i18n'    => mu_testimonios_i18n(),
            ] );
        }
    }
    add_action( 'wp_enqueue_scripts', 'mu_testimonios_enqueue' );
}

if ( ! function_exists( 'mu_reviews_blocked_keys' ) ) {
    /**
     * Claves de las reseñas eliminadas a mano, para que la importación no las
     * reviva (Google devuelve siempre las mismas reseñas).
     *
     * @return array
     */
    function mu_reviews_blocked_keys() {
        $keys = get_option( 'mu_reviews_blocked', [] );

        return is_array( $keys ) ? $keys : [];
    }
}

if ( ! function_exists( 'mu_reviews_block_reimport' ) ) {
    /**
     * Recuerda una reseña eliminada a mano (huella y enlace de perfil).
     *
     * @param array $review
     */
    function mu_reviews_block_reimport( $review ) {
        $keys = mu_reviews_blocked_keys();

        foreach ( [ mu_review_link_key( $review ), mu_review_identity_key( $review ) ] as $key ) {
            if ( '' !== (string) $key ) {
                $keys[ (string) $key ] = time();
            }
        }

        // Se conservan las últimas 100 marcas (la base guarda hasta 200 reseñas).
        arsort( $keys );

        update_option( 'mu_reviews_blocked', array_slice( $keys, 0, 100, true ), false );
    }
}

if ( ! function_exists( 'mu_reviews_import_from_google' ) ) {
    /**
     * Importa las reseñas del lugar desde Google Places y aplica el merge.
     *
     * Única ruta de import: la usan el enlace manual (?force_reviews=1) y la tarea
     * programada semanal. Devuelve el resultado del merge, o null si no se pudo
     * consultar (sin clave, error de red o respuesta vacía).
     *
     * @return array|null
     */
    function mu_reviews_import_from_google() {
        $api_key  = defined( 'MU_GOOGLE_PLACES_API_KEY' ) ? MU_GOOGLE_PLACES_API_KEY : '';
        $place_id = 'ChIJ18LlLQPchJURqIDwiZM7t_E';

        if ( ! $api_key ) {
            return null;
        }

        $url = add_query_arg(
            [ 'placeid' => $place_id, 'fields' => 'reviews', 'key' => $api_key, 'language' => 'es' ],
            'https://maps.googleapis.com/maps/api/place/details/json'
        );

        $response = wp_remote_get( $url, [ 'timeout' => 10 ] );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $data  = json_decode( wp_remote_retrieve_body( $response ), true );
        $batch = $data['result']['reviews'] ?? [];

        if ( ! is_array( $batch ) || ! $batch ) {
            return null;
        }

        $option = 'mu_reviews_master_db';
        $merge  = mu_reviews_import_merge( get_option( $option, [] ), $batch, 200, mu_reviews_blocked_keys() );

        if ( $merge['added'] > 0 || $merge['deleted'] > 0 ) {
            update_option( $option, $merge['db'] );

            // El set de reseñas viaja embebido en el HTML cacheado: sin purga el
            // front sigue sirviendo el JSON viejo. Solo tras un cambio real.
            do_action( 'litespeed_purge_all' );
        }

        // Rastro para el panel: así se ve que el cron corrió y qué hizo.
        update_option( 'mu_reviews_last_import', [
            'time'    => time(),
            'added'   => (int) $merge['added'],
            'existed' => (int) $merge['existed'],
            'deleted' => (int) $merge['deleted'],
            'skipped' => (int) $merge['skipped'],
        ], false );

        return $merge;
    }
}

if ( ! function_exists( 'mu_reviews_schedule_import' ) ) {
    /**
     * Programa la importación semanal de reseñas (WP-Cron).
     *
     * WP-Cron corre "cuando alguien visita" el sitio; con el tráfico de una tienda
     * alcanza. Para hora exacta hace falta un cron real del hosting pegándole a
     * wp-cron.php, que se configura fuera del tema.
     */
    function mu_reviews_schedule_import() {
        if ( ! wp_next_scheduled( 'mu_reviews_weekly_import' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', 'mu_reviews_weekly_import' );
        }
    }

    add_action( 'init', 'mu_reviews_schedule_import' );
    add_action( 'mu_reviews_weekly_import', 'mu_reviews_import_from_google' );
}

if ( ! function_exists( 'mu_reviews_import_notice' ) ) {
    /**
     * Aviso del import de reseñas: cuántas entraron, cuántas ya estaban y cuáles
     * quedaron marcadas como posible duplicado (con el nombre, para ubicarlas).
     *
     * @param array $merge Resultado de mu_reviews_import_merge().
     * @return string HTML del aviso ('' si no pasó nada relevante).
     */
    function mu_reviews_import_notice( $merge ) {
        $added    = (int) ( $merge['added'] ?? 0 );
        $existed  = (int) ( $merge['existed'] ?? 0 );
        $absorbed = (int) ( $merge['deleted'] ?? 0 );
        $skipped  = (int) ( $merge['skipped'] ?? 0 );
        $flagged  = (array) ( $merge['flagged'] ?? [] );

        if ( ! $added && ! $flagged && ! $absorbed && ! $skipped ) {
            return '';
        }

        $partes = [];

        if ( $added ) {
            $partes[] = sprintf( '%d agregada%s', $added, 1 === $added ? '' : 's' );
        }

        if ( $absorbed ) {
            $partes[] = sprintf( '%d manual absorbida y eliminada (enlace coincidente)', $absorbed );
        }

        if ( $existed ) {
            $partes[] = sprintf( '%d ya estaba%s', $existed, 1 === $existed ? '' : 'n' );
        }

        if ( $skipped ) {
            $partes[] = sprintf( '%d excluida%s por haberse borrado a mano', $skipped, 1 === $skipped ? '' : 's' );
        }

        $html = '<div style="background:#d4edda;color:#155724;padding:10px;text-align:center;border-radius:12px;margin-bottom:20px;font-size:0.9rem;">'
            . ( $added ? '✅ ' : 'ℹ ' ) . esc_html( implode( ' · ', $partes ) ) . '</div>';

        if ( $flagged ) {
            $nombres = [];

            foreach ( $flagged as $item ) {
                $nombre = trim( (string) ( $item['name'] ?? '' ) );
                if ( '' !== $nombre ) {
                    $nombres[] = $nombre;
                }
            }

            $detalle = $nombres ? ' (' . implode( ', ', $nombres ) . ')' : '';

            $html .= '<div style="background:#fdf6e3;color:#7a4b00;padding:10px;text-align:center;border-radius:12px;margin-bottom:20px;font-size:0.9rem;">'
                . '⚠ ' . esc_html( sprintf(
                    _n(
                        'Se importó %d reseña con el mismo nombre que una existente: la vieja quedó marcada como posible duplicado%s.',
                        'Se importaron %d reseñas con el mismo nombre que otras existentes: las viejas quedaron marcadas como posible duplicado%s.',
                        count( $flagged ),
                        'mu'
                    ),
                    count( $flagged ),
                    $detalle
                ) )
                . '</div>';
        }

        return $html;
    }
}

if ( ! function_exists( 'mu_testimonios_section' ) ) {
    function mu_testimonios_section() {
        $api_key        = defined( 'MU_GOOGLE_PLACES_API_KEY' ) ? MU_GOOGLE_PLACES_API_KEY : '';
        $place_id       = 'ChIJ18LlLQPchJURqIDwiZM7t_E';
        $db_option_name = 'mu_reviews_master_db';
        $max_reviews    = 200; // [Fix #4] Límite para evitar crecimiento ilimitado en wp_options.
        $msg_update     = '';

        if ( $api_key && mu_can_manage_reviews() && isset( $_GET['force_reviews'] ) ) {
            // Una sola ruta de import (la comparte la tarea programada semanal).
            $merge = mu_reviews_import_from_google();

            $msg_update = is_array( $merge ) ? mu_reviews_import_notice( $merge ) : '';
        }

        // Cadenas de la sección en el idioma de la URL (fallback: español).
        $i18n = mu_testimonios_i18n();

        ob_start();
        echo wp_kses_post( $msg_update );
        ?>
        <section class="mu-testimonials mu-section">
            <div class="mu-container">
                <h2 class="mu-section-title"><?php echo esc_html( $i18n['sectionTitle'] ); ?></h2>
                <?php
                // Resolución de posibles duplicados: solo para quien gestiona. La
                // importación agrega la reseña de Google (fuente de verdad) y marca
                // la copia vieja; acá se decide si se restaura o se elimina. Los
                // visitantes no ven las marcadas (js/testimonials.js).
                $mu_dups = [];

                if ( mu_can_manage_reviews() ) {
                    foreach ( (array) get_option( $db_option_name, [] ) as $mu_i => $mu_r ) {
                        if ( mu_review_is_flagged_duplicate( $mu_r ) ) {
                            $mu_dups[] = [ 'index' => $mu_i, 'review' => $mu_r ];
                        }
                    }
                }
                ?>
                <?php if ( $mu_dups ) : ?>
                    <div class="mu-dups" data-mu-dups>
                        <p class="mu-dups__title">
                            ⚠ <?php echo esc_html( sprintf( 'Posibles duplicados a revisar: %d', count( $mu_dups ) ) ); ?>
                        </p>
                        <ul class="mu-dups__list">
                            <?php foreach ( $mu_dups as $mu_dup ) : ?>
                                <li data-review-index="<?php echo esc_attr( $mu_dup['index'] ); ?>">
                                    <span class="mu-dups__info">
                                        <strong><?php echo esc_html( (string) ( $mu_dup['review']['author_name'] ?? '' ) ); ?></strong>
                                        <?php if ( ! empty( $mu_dup['review']['time'] ) ) : ?>
                                            <?php echo esc_html( ' · ' . date_i18n( 'd/m/Y', (int) $mu_dup['review']['time'] ) ); ?>
                                        <?php endif; ?>
                                        <?php echo esc_html( ' — ' . wp_html_excerpt( (string) ( $mu_dup['review']['text'] ?? '' ), 90, '…' ) ); ?>
                                    </span>
                                    <button type="button" class="button mu-dups__keep" data-accion="restaurar"><?php esc_html_e( 'No es duplicada', 'mu' ); ?></button>
                                    <button type="button" class="button mu-dups__delete" data-accion="eliminar"><?php esc_html_e( 'Eliminar', 'mu' ); ?></button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php
                // Botón de rotación en el marcado estático de la página (antes lo generaba
                // js/testimonials.js dentro del HTML que reescribe en cada rotación, y por
                // eso se destruía al cargar otras reseñas). El anillo que lo rodea es el
                // reloj: la duración vive en --mu-ring-duration (css/testimonials.css) y el
                // JS solo escucha el final de cada vuelta. Va fuera de
                // #mu-reviews-container justamente para que el repintado no lo toque.
                ?>
                <div class="mu-reviews-shell">
                    <div id="mu-reviews-container" class="mu-grid-reviews"></div>
                    <button class="mu-nav-btn refresh" id="mu-btn-rotate" type="button"
                            title="<?php echo esc_attr( $i18n['rotateTitle'] ); ?>"
                            aria-label="<?php echo esc_attr( $i18n['rotateTitle'] ); ?>">
                        <span class="mu-refresh-ring" aria-hidden="true">
                            <svg viewBox="0 0 44 44" focusable="false">
                                <circle class="mu-refresh-ring-track" cx="22" cy="22" r="20"></circle>
                                <circle class="mu-refresh-ring-arc" cx="22" cy="22" r="20"></circle>
                            </svg>
                        </span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.3"/></svg>
                    </button>
                </div>
                <div class="mu-bottom-actions">
                    <a href="https://search.google.com/local/writereview?placeid=<?php echo esc_attr( $place_id ); ?>"
                       target="_blank" rel="noopener noreferrer" class="mu-btn mu-btn-secondary">
                        <span class="mu-btn-icon">⭐</span> <?php echo esc_html( $i18n['ctaReview'] ); ?>
                    </a>
                </div>
                <?php if ( mu_can_manage_reviews() ) : ?>
                    <div style="text-align:center;margin-top:20px;font-size:12px;opacity:0.6;">
                        <a href="?force_reviews=1" style="color:inherit;">↻ Admin: Actualizar desde Google</a>
                        <span style="margin:0 8px;">|</span>
                        <a href="#" onclick="openReviewModal();return false;" style="color:inherit;">✎ Manual</a>
                    </div>
                    <?php
                    // Rastro de la importación automática (semanal): es la forma de
                    // ver desde el panel que el cron corrió y qué hizo.
                    $mu_last = get_option( 'mu_reviews_last_import', [] );

                    if ( is_array( $mu_last ) && ! empty( $mu_last['time'] ) ) :
                        ?>
                        <p style="text-align:center;margin-top:8px;font-size:12px;opacity:0.6;">
                            <?php
                            echo esc_html( sprintf(
                                'Última importación automática: %s · %d agregadas · %d absorbidas · %d ya estaban · %d excluidas',
                                date_i18n( 'd/m/Y H:i', (int) $mu_last['time'] ),
                                (int) ( $mu_last['added'] ?? 0 ),
                                (int) ( $mu_last['deleted'] ?? 0 ),
                                (int) ( $mu_last['existed'] ?? 0 ),
                                (int) ( $mu_last['skipped'] ?? 0 )
                            ) );
                            ?>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
        
        <!-- Modal para agregar/editar reseñas manualmente -->
        <?php
        // Un solo modal por página: si el shortcode aparece dos veces (contenido +
        // plantilla, por ejemplo) duplicar los id rompería el editor, porque
        // getElementById devuelve siempre el primero. El guardia lo evita de raíz.
        static $mu_review_modal_printed = false;
        ?>
        <?php if ( mu_can_manage_reviews() && ! $mu_review_modal_printed ) : $mu_review_modal_printed = true; ?>
        <div id="mu-review-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
            <div style="background:white;padding:30px;border-radius:12px;max-width:500px;width:90%;max-height:90vh;overflow-y:auto;">
                <div class="mu-review-modal-head">
                    <h3 id="mu-review-modal-title" style="margin:0;">Agregar Reseña</h3>
                    <span class="mu-lang-pills" role="group" aria-label="Idioma del comentario">
                        <button type="button" class="mu-lang-pill is-active" data-lang="es" title="Español">ES</button>
                        <button type="button" class="mu-lang-pill" data-lang="pt" title="Portugués (Brasil)">PT</button>
                        <button type="button" class="mu-lang-pill" data-lang="en" title="Inglés">EN</button>
                    </span>
                </div>
                <p id="mu-review-modal-loading" class="mu-review-modal-loading" style="display:none;"></p>
                <div id="mu-review-modal-error" class="mu-review-modal-error" role="alert" style="display:none;">
                    <p id="mu-review-modal-error-text" style="margin:0 0 10px;"></p>
                    <button type="button" onclick="window.muReviewModal.close()" style="padding:6px 14px;border:1px solid #ddd;border-radius:4px;cursor:pointer;">Cerrar</button>
                </div>
                <form id="mu-review-form">
                    <input type="hidden" id="mu-review-index" name="review_index" value="">
                    
                    <div style="margin-bottom:15px;">
                        <label style="display:block;margin-bottom:5px;font-weight:bold;">Nombre del cliente:</label>
                        <input type="text" id="mu-review-name" name="author_name" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;">
                    </div>
                    
                    <div style="margin-bottom:15px;">
                        <label style="display:block;margin-bottom:5px;font-weight:bold;">Rating (1-5):</label>
                        <select id="mu-review-rating" name="rating" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;">
                            <option value="5">⭐⭐⭐⭐⭐ (5)</option>
                            <option value="4">⭐⭐⭐⭐ (4)</option>
                            <option value="3">⭐⭐⭐ (3)</option>
                            <option value="2">⭐⭐ (2)</option>
                            <option value="1">⭐ (1)</option>
                        </select>
                    </div>
                    
                    <div style="margin-bottom:15px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
                            <label style="display:block;font-weight:bold;">Comentario:</label>
                            <span style="display:inline-flex;align-items:center;gap:8px;">
                                <span id="mu-review-translate-status" class="mu-ai-inline-status"></span>
                                <button type="button" id="mu-review-original-toggle" class="mu-ai-eye" hidden title="Ver el original en español">👁</button>
                                <?php // Botones numerados (1..5) que llena el JS cuando la traducción trae variantes. ?>
                                <span id="mu-review-variants" class="mu-ai-variants" role="group" aria-label="Variantes de la traducción"></span>
                                <button type="button" id="mu-review-translate" class="mu-ai-inline-btn" disabled title="Traduce el comentario en español al idioma activo">Traducir</button>
                            </span>
                        </div>

                        <div class="mu-review-lang-block" data-lang="es">
                            <textarea id="mu-review-text" name="text" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;min-height:100px;"></textarea>
                        </div>

                        <div class="mu-review-lang-block" data-lang="pt" style="display:none;">
                            <textarea id="mu-review-text-pt" name="text_pt" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;min-height:100px;"></textarea>
                        </div>

                        <div class="mu-review-lang-block" data-lang="en" style="display:none;">
                            <textarea id="mu-review-text-en" name="text_en" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;min-height:100px;"></textarea>
                        </div>
                    </div>

                    <?php // Comparador: el ojo del encabezado muestra u oculta el original en español. ?>
                    <div id="mu-review-original" class="mu-ai-original" hidden></div>

                    <div style="margin-bottom:15px;">
                        <label style="display:block;margin-bottom:5px;font-weight:bold;">Fecha (opcional):</label>
                        <input type="date" id="mu-review-date" name="time" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;">
                    </div>
                    
                    <div style="margin-bottom:15px;">
                        <label style="display:block;margin-bottom:5px;font-weight:bold;">URL del perfil de Google (opcional):</label>
                        <input type="url" id="mu-review-profile-url" name="profile_url" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;">
                    </div>
                    
                    <div style="margin-bottom:15px;">
                        <label style="display:block;margin-bottom:5px;font-weight:bold;">URL de la foto de perfil (opcional):</label>
                        <input type="url" id="mu-review-profile-photo" name="profile_photo_url" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;">
                    </div>
                    
                    <div style="margin-bottom:15px;">
                        <label style="display:block;margin-bottom:5px;font-weight:bold;">Foto de la compra (upload):</label>
                        <input type="file" id="mu-review-purchase-photo" name="purchase_photo" accept="image/*" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;">
                        <input type="hidden" id="mu-review-purchase-photo-url" name="purchase_photo_url" value="">
                        <div id="mu-purchase-photo-preview" style="margin-top:10px;"></div>
                    </div>
                    
                    <div style="margin-bottom:15px;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" id="mu-review-hidden" name="hidden" value="1" style="width:auto;">
                            <span style="font-weight:bold;">Ocultar reseña (no se mostrará)</span>
                        </label>
                    </div>
                    
                    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                        <button type="button" id="mu-delete-review-btn" onclick="window.muReviewModal.deleteReview()" style="padding:10px 20px;background:#dc3232;color:white;border:none;border-radius:4px;cursor:pointer;display:none;">Eliminar</button>
                        <button type="button" onclick="window.muReviewModal.close()" style="padding:10px 20px;border:1px solid #ddd;background:white;border-radius:4px;cursor:pointer;">Cancelar</button>
                        <button type="submit" style="padding:10px 20px;background:#0073aa;color:white;border:none;border-radius:4px;cursor:pointer;">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php endif; ?>
        
        <?php
        return ob_get_clean();
    }
    add_shortcode( 'mu_testimonios_section', 'mu_testimonios_section' );
}

// ============================================
// AJAX HANDLER PARA GUARDAR RESEÑAS
// ============================================

if ( ! function_exists( 'mu_ajax_save_review' ) ) {
    function mu_ajax_save_review() {
        check_ajax_referer( 'mu_review_nonce', 'nonce' );

        if ( ! mu_can_manage_reviews() ) {
            wp_send_json_error( 'No tienes permisos' );
        }

        $db_option_name = 'mu_reviews_master_db';
        $max_reviews    = 200;
        $current_db     = get_option( $db_option_name, [] );

        $review_index = isset( $_POST['review_index'] ) ? sanitize_text_field( wp_unslash( $_POST['review_index'] ) ) : '';
        $is_edit      = ( '' !== $review_index && isset( $current_db[ $review_index ] ) );

        // Índice que ya no existe (página cacheada, reseña borrada en otra
        // pestaña): error explícito. Antes caía en "nueva reseña" y duplicaba.
        if ( '' !== $review_index && ! $is_edit ) {
            wp_send_json_error( 'Reseña no encontrada. Recargá la página e intentá de nuevo.' );
        }

        // Base: la reseña existente. Conserva los campos que el formulario no
        // edita (relative_time_description, language, translated, etc.).
        $review = $is_edit ? (array) $current_db[ $review_index ] : [];

        $rating = intval( $_POST['rating'] ?? 0 );
        if ( $rating < 1 || $rating > 5 ) {
            $rating = 5;
        }

        $text = sanitize_textarea_field( wp_unslash( $_POST['text'] ?? '' ) );

        $review['author_name']       = sanitize_text_field( wp_unslash( $_POST['author_name'] ?? '' ) );
        $review['rating']            = $rating;
        $review['text']              = $text;
        $review['time']              = ! empty( $_POST['time'] ) ? strtotime( sanitize_text_field( wp_unslash( $_POST['time'] ) ) ) : time();
        $review['author_url']        = esc_url_raw( wp_unslash( $_POST['profile_url'] ?? '' ) );
        $review['profile_url']       = esc_url_raw( wp_unslash( $_POST['profile_url'] ?? '' ) );
        $review['profile_photo_url'] = esc_url_raw( wp_unslash( $_POST['profile_photo_url'] ?? '' ) );
        $review['hidden']            = isset( $_POST['hidden'] ) ? 1 : 0;

        // Traducciones del comentario ([sobrescritura dispersa]): se guarda ''
        // cuando el valor está vacío o es idéntico al español, así el front usa
        // el texto base y editar el español propaga a los idiomas sin traducción.
        foreach ( [ 'pt', 'en' ] as $lang ) {
            $value                     = sanitize_textarea_field( wp_unslash( $_POST[ 'text_' . $lang ] ?? '' ) );
            $review[ 'text_' . $lang ] = ( '' === $value || trim( $value ) === trim( $text ) ) ? '' : $value;
        }

        // Marca de origen por campo: guarda con qué español se tradujo, para poder
        // avisar "traducción desactualizada" cuando el comentario base cambia (FR-013).
        // Se recalcula SOLO si la traducción cambió en este guardado; si no cambió, se
        // conserva la marca anterior. Si se recalculara siempre, quedaría idéntica al
        // español recién guardado y el aviso no aparecería nunca.
        foreach ( [ 'pt', 'en' ] as $lang ) {
            $key      = 'text_' . $lang . '_hash';
            $has_text = ( '' !== (string) $review[ 'text_' . $lang ] );

            if ( ! $has_text ) {
                $review[ $key ] = '';
                continue;
            }

            if ( ! $is_edit ) {
                $review[ $key ] = function_exists( 'mu_ai_hash' ) ? mu_ai_hash( $text ) : '';
                continue;
            }

            $prev_text = (string) ( $current_db[ $review_index ][ 'text_' . $lang ] ?? '' );
            $changed   = ( $prev_text !== (string) $review[ 'text_' . $lang ] );

            $review[ $key ] = $changed
                ? ( function_exists( 'mu_ai_hash' ) ? mu_ai_hash( $text ) : '' )
                : (string) ( $current_db[ $review_index ][ $key ] ?? '' );
        }

        if ( ! isset( $review['purchase_photo_url'] ) ) {
            $review['purchase_photo_url'] = '';
        }

        // Manejar upload de foto de compra
        if ( isset( $_FILES['purchase_photo'] ) && isset( $_FILES['purchase_photo']['error'] ) && $_FILES['purchase_photo']['error'] === UPLOAD_ERR_OK ) {
            $upload = wp_handle_upload( $_FILES['purchase_photo'], [ 'test_form' => false ] );
            if ( ! isset( $upload['error'] ) ) {
                $review['purchase_photo_url'] = $upload['url'];
            }
        } elseif ( ! empty( $_POST['purchase_photo_url'] ) ) {
            $review['purchase_photo_url'] = esc_url_raw( wp_unslash( $_POST['purchase_photo_url'] ) );
        }

        if ( $is_edit ) {
            // Editar reseña existente
            $current_db[ $review_index ] = $review;
        } else {
            // [Duplicados] Al crear a mano, si ya hay una reseña del mismo autor no
            // se crea otra sin confirmación: el front no vuelve a verse duplicado y
            // tampoco hay que esperar a la próxima importación para descubrirlo.
            // El editor ofrece "Guardar igual" (es otra persona), que reenvía con
            // forzar_duplicado=1.
            if ( empty( $_POST['forzar_duplicado'] ) ) {
                $parecidas = mu_reviews_find_same_author( $current_db, $review );

                if ( $parecidas ) {
                    $primera = (array) $current_db[ $parecidas[0] ];
                    $cuando  = ! empty( $primera['time'] ) ? date_i18n( 'd/m/Y', (int) $primera['time'] ) : '';

                    wp_send_json_error( [
                        'code'    => 'duplicado_probable',
                        'index'   => $parecidas[0],
                        'message' => sprintf(
                            'Ya hay una reseña de %s%s. Si es la misma persona, editá la existente; si es otra con el mismo nombre, guardá igual.',
                            (string) ( $primera['author_name'] ?? '' ),
                            '' !== $cuando ? ' (' . $cuando . ')' : ''
                        ),
                    ] );
                }
            }

            // Nueva reseña
            $current_db[] = $review;
            // Mantener límite de reseñas
            $current_db = array_slice( $current_db, -$max_reviews );
        }

        update_option( $db_option_name, $current_db );

        // El set de reseñas viaja embebido en el HTML cacheado: sin purga, el
        // front sigue sirviendo el JSON viejo. Solo tras un cambio real
        // (Hash Gate, MIGRATION-GUIDE §7).
        do_action( 'litespeed_purge_all' );

        wp_send_json_success( 'Reseña guardada correctamente' );
    }
    add_action( 'wp_ajax_mu_save_review', 'mu_ajax_save_review' );
}

// ============================================
// AJAX HANDLER PARA ELIMINAR RESEÑAS
// ============================================

if ( ! function_exists( 'mu_ajax_delete_review' ) ) {
    function mu_ajax_delete_review() {
        check_ajax_referer( 'mu_review_nonce', 'nonce' );
        
        if ( ! mu_can_manage_reviews() ) {
            wp_send_json_error( 'No tienes permisos' );
        }
        
        $db_option_name = 'mu_reviews_master_db';
        $current_db = get_option( $db_option_name, [] );
        
        // Sin índice válido no se borra nada: antes el default 0 eliminaba la
        // primera reseña del listado.
        $review_index = isset( $_POST['review_index'] ) ? intval( wp_unslash( $_POST['review_index'] ) ) : -1;
        
        if ( isset( $current_db[ $review_index ] ) ) {
            // Lo borrado a mano no se reimporta: Google devuelve siempre las mismas
            // reseñas y la tarea semanal las traería de vuelta.
            mu_reviews_block_reimport( (array) $current_db[ $review_index ] );

            unset( $current_db[ $review_index ] );
            // Reindexar array para mantener continuidad
            $current_db = array_values( $current_db );
            update_option( $db_option_name, $current_db );

            // El set de reseñas viaja embebido en el HTML cacheado (Hash Gate).
            do_action( 'litespeed_purge_all' );

            wp_send_json_success( 'Reseña eliminada correctamente' );
        } else {
            wp_send_json_error( 'Reseña no encontrada' );
        }
    }
    add_action( 'wp_ajax_mu_delete_review', 'mu_ajax_delete_review' );
}

// ============================================
// AJAX HANDLER: TRAER UNA RESEÑA PARA EL EDITOR
// ============================================

if ( ! function_exists( 'mu_ajax_resolve_duplicate' ) ) {
    /**
     * Resuelve una reseña marcada como posible duplicado:
     *   restaurar → no era duplicada: se le saca la marca y vuelve a mostrarse
     *               (no toca `hidden`, que es una decisión aparte del mantenedor);
     *   eliminar  → se borra de la base.
     */
    function mu_ajax_resolve_duplicate() {
        check_ajax_referer( 'mu_review_nonce', 'nonce' );

        if ( ! mu_can_manage_reviews() ) {
            wp_send_json_error( 'Sin permisos.' );
        }

        $db_option_name = 'mu_reviews_master_db';
        $current_db     = get_option( $db_option_name, [] );
        $index          = isset( $_POST['review_index'] ) ? sanitize_text_field( wp_unslash( $_POST['review_index'] ) ) : '';
        $accion         = isset( $_POST['accion'] ) ? sanitize_text_field( wp_unslash( $_POST['accion'] ) ) : 'restaurar';

        if ( '' === $index || ! isset( $current_db[ $index ] ) ) {
            wp_send_json_error( 'Reseña no encontrada. Recargá la página e intentá de nuevo.' );
        }

        if ( 'eliminar' === $accion ) {
            array_splice( $current_db, (int) $index, 1 );
        } else {
            $current_db[ $index ]['posible_duplicado'] = false;
        }

        update_option( $db_option_name, array_values( $current_db ) );

        // El listado viaja en el HTML cacheado: sin purga el front sigue igual.
        do_action( 'litespeed_purge_all' );

        wp_send_json_success( 'ok' );
    }
    add_action( 'wp_ajax_mu_resolve_review_duplicate', 'mu_ajax_resolve_duplicate' );
}

if ( ! function_exists( 'mu_ajax_get_review' ) ) {
    /**
     * Devuelve una reseña de la base para el editor.
     *
     * El editor no puede depender del listado que quedó impreso en la página (puede
     * venir de caché o con índices viejos): esa dependencia era la causa del
     * formulario vacío al tocar «Editar». Acá la fuente de verdad es la base.
     *
     * @return void
     */
    function mu_ajax_get_review() {
        check_ajax_referer( 'mu_review_nonce', 'nonce' );

        if ( ! mu_can_manage_reviews() ) {
            wp_send_json_error( [ 'code' => 'forbidden', 'error' => 'No tenés permisos.' ] );
        }

        $index = isset( $_POST['review_index'] ) ? intval( wp_unslash( $_POST['review_index'] ) ) : -1;
        $db    = get_option( 'mu_reviews_master_db', [] );

        if ( ! isset( $db[ $index ] ) ) {
            wp_send_json_error( [ 'code' => 'not_found', 'error' => 'La reseña ya no está en el listado (¿se borró en otra pestaña?).' ] );
        }

        $review = (array) $db[ $index ];

        // Solo lo que el editor usa, con tipos explícitos.
        $out = [
            'author_name'        => (string) ( $review['author_name'] ?? '' ),
            'rating'             => (int) ( $review['rating'] ?? 5 ),
            'text'               => (string) ( $review['text'] ?? '' ),
            'time'               => (int) ( $review['time'] ?? 0 ),
            'author_url'         => (string) ( $review['author_url'] ?? '' ),
            'profile_url'        => (string) ( $review['profile_url'] ?? '' ),
            'profile_photo_url'  => (string) ( $review['profile_photo_url'] ?? '' ),
            'purchase_photo_url' => (string) ( $review['purchase_photo_url'] ?? '' ),
            'hidden'             => ! empty( $review['hidden'] ),
        ];

        $stale = [];

        foreach ( [ 'pt', 'en' ] as $lang ) {
            $out[ 'text_' . $lang ] = (string) ( $review[ 'text_' . $lang ] ?? '' );

            if ( function_exists( 'mu_ai_is_stale' ) && mu_ai_is_stale( $out['text'], $out[ 'text_' . $lang ], (string) ( $review[ 'text_' . $lang . '_hash' ] ?? '' ) ) ) {
                $stale[] = $lang;
            }
        }

        wp_send_json_success( [ 'index' => $index, 'review' => $out, 'stale' => $stale ] );
    }
    add_action( 'wp_ajax_mu_get_review', 'mu_ajax_get_review' );
}

// ============================================
// SHORTCODES HOME — ENQUEUE CONDICIONAL
// Carga css/home.css + js/hero.js solo en is_front_page().
// El carrusel de bestsellers/popcat ya está cubierto por js/global-ui.js.
// ============================================

if ( ! function_exists( 'mu_home_sections_enqueue' ) ) {
    function mu_home_sections_enqueue() {
        if ( ! is_front_page() ) return;
        wp_enqueue_style( 'mu-home', get_stylesheet_directory_uri() . '/css/home.css', [], '1.1.0' );
        wp_enqueue_script( 'mu-hero', get_stylesheet_directory_uri() . '/js/hero.js', [], '1.0.0', true );
    }
    add_action( 'wp_enqueue_scripts', 'mu_home_sections_enqueue' );
}

// ============================================
// SHORTCODE BESTSELLERS
// Uso: [mu_bestsellers_section]
// CSS: css/home.css | Carrusel JS: js/global-ui.js (initCarousels)
// ============================================

if ( ! function_exists( 'mu_bestsellers_section' ) ) {
    function mu_bestsellers_section() {
        // Obtener país actual para evitar cross-linking
        $current_country = function_exists( 'muyu_get_current_country_from_subdomain' ) 
            ? muyu_get_current_country_from_subdomain() 
            : 'AR';
        
        // Verificar si es usuario restringido (no Argentina) para filtrar productos digitales
        $is_restricted = function_exists( 'muyu_is_restricted_user' ) && muyu_is_restricted_user();
        
        // Cache key específico por país y restricción
        $cache_key = 'mu_bestsellers_html_' . $current_country . '_' . ( $is_restricted ? 'restricted' : 'all' );
        $cached    = get_transient( $cache_key );
        if ( $cached ) return $cached;

        $query_args = [
            'post_type'      => 'product',
            'posts_per_page' => 8,
            'meta_key'       => 'total_sales',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
            'post_status'    => 'publish',
            'tax_query'      => [ [
                'taxonomy' => 'product_visibility',
                'field'    => 'name',
                'terms'    => 'exclude-from-catalog',
                'operator' => 'NOT IN',
            ] ],
        ];
        
        // Si es usuario restringido, filtrar solo productos digitales
        if ( $is_restricted ) {
            $digital_ids = function_exists( 'muyu_get_digital_product_ids' ) 
                ? muyu_get_digital_product_ids() 
                : [];
            
            if ( ! empty( $digital_ids ) ) {
                $query_args['post__in'] = array_map( 'intval', $digital_ids );
            }
        }
        
        $query = new WP_Query( $query_args );

        ob_start();
        ?>
        <section class="mu-bestsellers mu-section">
            <div class="mu-container">
                <h2 class="mu-section-title">Nuestros Productos Más Vendidos</h2>
                <div class="mu-carousel-wrapper">
                    <button class="mu-nav-btn prev" aria-label="Anterior">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="24" height="24"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    </button>
                    <button class="mu-nav-btn next" aria-label="Siguiente">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="24" height="24"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </button>
                    <div class="mu-carousel-track">
                        <?php
                        if ( $query->have_posts() ) :
                            while ( $query->have_posts() ) : $query->the_post();
                                global $product;
                                $image_id  = $product->get_image_id();
                                $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src();
                                ?>
                                <div class="mu-carousel-item">
                                    <a href="<?php echo esc_url( get_permalink() ); ?>" class="mu-product-card can-hover">
                                        <?php if ( $product->is_on_sale() ) : ?>
                                            <span class="mu-product-badge">Oferta</span>
                                        <?php endif; ?>
                                        <img src="<?php echo esc_url( $image_url ); ?>" class="mu-product-image"
                                             alt="<?php echo esc_attr( get_the_title() ); ?>"
                                             width="300" height="300" loading="lazy">
                                        <h3 class="mu-product-title"><?php the_title(); ?></h3>
                                        <span class="mu-product-price"><?php echo $product->get_price_html(); ?></span>
                                        <span class="mu-product-btn">Ver Producto</span>
                                    </a>
                                </div>
                            <?php endwhile;
                        else : ?>
                            <p style="text-align:center;width:100%;">No hay productos destacados por el momento.</p>
                        <?php endif;
                        wp_reset_postdata();
                        ?>
                    </div>
                </div>
                <div class="mu-bestsellers-footer">
                    <a href="<?php echo esc_url( add_query_arg( 'orderby', 'popularity', wc_get_page_permalink( 'shop' ) ) ); ?>" class="mu-btn mu-btn-primary">Ver Todos</a>
                </div>
            </div>
        </section>
        <?php
        $html = ob_get_clean();
        set_transient( $cache_key, $html, 12 * HOUR_IN_SECONDS );
        
        // Invalidar caches antiguos para forzar refresh con nuevo sistema
        delete_transient( 'mu_bestsellers_html' );
        delete_transient( 'mu_bestsellers_html_AR_all' );
        delete_transient( 'mu_bestsellers_html_AR_restricted' );
        delete_transient( 'mu_bestsellers_html_MX_all' );
        delete_transient( 'mu_bestsellers_html_MX_restricted' );
        delete_transient( 'mu_bestsellers_html_CO_all' );
        delete_transient( 'mu_bestsellers_html_CO_restricted' );
        delete_transient( 'mu_bestsellers_html_ES_all' );
        delete_transient( 'mu_bestsellers_html_ES_restricted' );
        
        return $html;
    }
    add_shortcode( 'mu_bestsellers_section', 'mu_bestsellers_section' );
}

// ============================================
// SHORTCODE CATEGORÍAS POPULARES
// Uso: [mu_popcat_section]
// CSS: css/home.css | Carrusel JS: js/global-ui.js (initCarousels)
// ============================================

if ( ! function_exists( 'mu_popcat_section' ) ) {
    function mu_popcat_section() {
        // Verificar si es usuario restringido (no Argentina) para filtrar categorías
        $is_restricted = function_exists( 'muyu_is_restricted_user' ) && muyu_is_restricted_user();
        
        $all_categories = [
            [ 'href' => '/tienda/escolares/',     'img' => '/wp-content/uploads/2026/02/catescolares.webp', 'alt' => 'Etiquetas Escolares',  'title' => 'Etiquetas Escolares',  'desc' => 'Más de 150 diseños' ],
            [ 'href' => '/tienda/decoracion/',    'img' => '/wp-content/uploads/2026/02/catstickers.webp',  'alt' => 'Stickers Decorativos', 'title' => 'Stickers Decorativos', 'desc' => 'Planchas y packs' ],
            [ 'href' => '/tienda/emprendimientos/', 'img' => '/wp-content/uploads/2026/02/catetiquetas.webp', 'alt' => 'Emprendedores',       'title' => 'Emprendedores',        'desc' => 'Todo para tu marca' ],
            [ 'href' => '/tienda/outlet/',        'img' => '/wp-content/uploads/2026/02/catoutlet.webp',    'alt' => 'Outlet',               'title' => 'Outlet',               'desc' => 'Productos en oferta' ],
      ];
        
        // Categoría especial solo para países restringidos (no Argentina)
        $restricted_only_categories = [
            [
                'href' => 'https://br.muyunicos.com/eventos/', 
                'img' => '/wp-content/uploads/2026/02/catetiquetas.webp', 
                'alt' => 'Eventos', 
                'title' => 'Eventos', 
                'desc' => 'Personaliza tu evento'
            ]
        ];
        
        // Si es usuario restringido, filtrar solo categorías con productos digitales
        $categories = $all_categories;
        if ( $is_restricted ) {
            // Agregar categorías especiales para países restringidos
            $categories = array_merge( $categories, $restricted_only_categories );
            
            $digital_cat_ids = get_option( 'muyu_digital_category_ids', [] );
            
            if ( ! empty( $digital_cat_ids ) ) {
                $categories = array_filter( $categories, function( $cat ) use ( $digital_cat_ids ) {
                    // La categoría especial de eventos siempre se muestra
                    if ( isset( $cat['href'] ) && strpos( $cat['href'], 'eventos' ) !== false ) {
                        return true;
                    }
                    
                    // Obtener ID de categoría desde la URL
                    $cat_slug = basename( rtrim( $cat['href'], '/' ) );
                    $term = get_term_by( 'slug', $cat_slug, 'product_cat' );
                    return $term && in_array( $term->term_id, $digital_cat_ids );
                } );
            }
        }
        
        // Si no hay categorías después del filtrado, no mostrar la sección
        if ( empty( $categories ) ) {
            return '';
        }

        ob_start();
        ?>
        <section class="mu-section">
            <div class="mu-container">
                <h2 class="mu-section-title">Explora Nuestras Categorías</h2>
                <div class="mu-carousel-wrapper">
                    <button class="mu-nav-btn prev" aria-label="Anterior">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="24" height="24"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    </button>
                    <button class="mu-nav-btn next" aria-label="Siguiente">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="24" height="24"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </button>
                    <div class="mu-carousel-track">
                        <?php foreach ( $categories as $cat ) : ?>
                            <div class="mu-carousel-item">
                                <div class="mu-category-card can-hover">
                                    <a href="<?php echo esc_url( $cat['href'] ); ?>">
                                        <div class="mu-category-image">
                                            <img src="<?php echo esc_url( $cat['img'] ); ?>"
                                                 alt="<?php echo esc_attr( $cat['alt'] ); ?>" loading="lazy">
                                        </div>
                                        <div class="mu-category-info">
                                            <h3><?php echo esc_html( $cat['title'] ); ?></h3>
                                            <p><?php echo esc_html( $cat['desc'] ); ?></p>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }
    add_shortcode( 'mu_popcat_section', 'mu_popcat_section' );
}

// ============================================
// SHORTCODE HERO PROMOS DINÁMICAS
// Uso: [mu_hero_section]
// Datos: gestionados desde inc/hero-banners.php (admin: WC → Marketing → Hero Banners)
// CSS: css/home.css (sección Hero, encolado por mu_home_sections_enqueue)
// JS:  js/hero.js  (IIFE, autoplay 7s, swipe, dots — encolado por mu_home_sections_enqueue)
// El filtrado por fecha y el cache de transient los aplica mu_get_hero_banners().
// ============================================

if ( ! function_exists( 'mu_hero_section' ) ) {
    function mu_hero_section() {
        if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) return '';

        $active_promos = function_exists( 'mu_get_hero_banners' ) ? mu_get_hero_banners() : [];
        if ( empty( $active_promos ) ) return '';

        // Idioma actual para mostrar versión traducida del banner
        $idioma_actual = function_exists( 'muyu_get_current_language' ) ? muyu_get_current_language() : 'es';

        // Helper: campo del banner en el idioma actual. [Automático] Se usa el valor
        // traducido cuando existe —un valor solo se guarda si difiere del español
        // (sobrescritura dispersa)— y el español en cualquier otro caso. Ya no
        // depende de la casilla traducir_XX: ver mu_hero_banner_field().
        $mu_hero_field = function( $promo, $base ) use ( $idioma_actual ) {
            return function_exists( 'mu_hero_banner_field' )
                ? mu_hero_banner_field( $promo, $base, $idioma_actual )
                : ( isset( $promo[ $base ] ) ? $promo[ $base ] : '' );
        };

        // --- DEFAULTS PARA BADGE ---
        $badge_defaults = [
            'url'  => '/tienda/digital/?min_price=0&max_price=0',
            'text' => '<strong>¡Gratis!</strong><span>Envíos digitales</span>',
        ];

        $total = count( $active_promos );

        ob_start();
        ?>
        <section class="mu-hero-promo">
            <div class="mu-hero-promo-wrapper" data-hero-slides="<?php echo esc_attr( $total ); ?>">
                <div class="mu-hero-promo-slider" id="muHeroSlider">
                    <?php foreach ( $active_promos as $idx => $promo ) :
                        $is_first      = ( 0 === $idx );
                        $show_badge    = ! empty( $promo['show_free_badge'] ) && true === $promo['show_free_badge'];
                        $imagen        = $mu_hero_field( $promo, 'imagen' );
                        $eyebrow       = $mu_hero_field( $promo, 'eyebrow' );
                        $cta_url       = $mu_hero_field( $promo, 'cta_url' );
                        $cta_sec_text  = $mu_hero_field( $promo, 'cta_secundario_texto' );
                        $cta_sec_url   = $mu_hero_field( $promo, 'cta_secundario_url' );
                        $badge_url     = $mu_hero_field( $promo, 'free_badge_url' );
                        if ( '' === $badge_url ) {
                            $badge_url = $badge_defaults['url'];
                        }
                        $badge_text    = $mu_hero_field( $promo, 'free_badge_text' );
                        if ( '' === $badge_text ) {
                            $badge_text = $badge_defaults['text'];
                        }
                    ?>
                    <div class="mu-hero-promo-slide<?php echo $is_first ? ' active' : ''; ?>"
                         data-slide-index="<?php echo esc_attr( $idx ); ?>">

                        <div class="mu-hero-promo-bg">
                            <img src="<?php echo esc_url( $imagen ); ?>"
                                 alt="<?php echo esc_attr( strip_tags( $mu_hero_field( $promo, 'titulo' ) ) ); ?>"
                                 width="1280" height="580"
                                 loading="<?php echo $is_first ? 'eager' : 'lazy'; ?>"
                                 <?php echo $is_first ? 'fetchpriority="high"' : ''; ?>
                                 decoding="async">
                        </div>

                        <div class="mu-hero-promo-content">
                            <?php if ( '' !== $eyebrow ) : ?>
                                <div class="mu-hero-promo-eyebrow">
                                    <?php echo esc_html( $eyebrow ); ?>
                                </div>
                            <?php endif; ?>

                            <h1 class="mu-hero-promo-title"><?php echo wp_kses_post( $mu_hero_field( $promo, 'titulo' ) ); ?></h1>
                            <p class="mu-hero-promo-desc"><?php echo esc_html( $mu_hero_field( $promo, 'descripcion' ) ); ?></p>

                            <div class="mu-hero-promo-actions">
                                <a href="<?php echo esc_url( $cta_url ); ?>" class="mu-btn mu-btn-primary">
                                    <span><?php echo esc_html( $mu_hero_field( $promo, 'cta_texto' ) ); ?></span>
                                    <?php echo mu_get_icon( 'arrow' ); ?>
                                </a>

                                <?php if ( '' !== $cta_sec_text && '' !== $cta_sec_url ) : ?>
                                <a href="<?php echo esc_url( $cta_sec_url ); ?>" class="mu-btn mu-btn-outline">
                                    <?php echo esc_html( $cta_sec_text ); ?>
                                </a>
                                <?php endif; ?>

                                <?php if ( $show_badge ) : ?>
                                <a href="<?php echo esc_url( $badge_url ); ?>" class="mu-hero-promo-free-badge">
                                    <div class="mu-hero-promo-free-text"><?php echo wp_kses_post( $badge_text ); ?></div>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if ( $total > 1 ) : ?>
                <div class="mu-hero-promo-dots" role="tablist" aria-label="Promos">
                    <?php foreach ( $active_promos as $idx => $promo ) : ?>
                    <button class="mu-hero-promo-dot<?php echo 0 === $idx ? ' active' : ''; ?>"
                            role="tab"
                            aria-selected="<?php echo 0 === $idx ? 'true' : 'false'; ?>"
                            aria-label="Promo <?php echo $idx + 1; ?>"
                            data-hero-dot="<?php echo esc_attr( $idx ); ?>"></button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

            </div>
        </section>
        <?php
        return ob_get_clean();
    }
    add_shortcode( 'mu_hero_section', 'mu_hero_section' );
}

