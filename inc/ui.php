<?php
/**
 * Muy Únicos - Componentes de UI
 *
 * Responsable de:
 * - Hero de tienda con typing animation.
 * - Sección de testimonios / reviews de Google.
 * - Botón flotante de WhatsApp.
 * - Banner de cookies.
 * - Sección de features / propuestas de valor.
 * - Estilos y scripts de UI globales.
 *
 * @package GeneratePress_Child
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// =========================================================================
// 1. ENQUEUE GLOBAL UI ASSETS
// =========================================================================

if ( ! function_exists( 'mu_ui_enqueue_assets' ) ) {
    function mu_ui_enqueue_assets() {
        wp_enqueue_style(
            'mu-ui',
            get_stylesheet_directory_uri() . '/css/components/global-ui.css',
            [],
            '1.3.4'
        );
        wp_enqueue_script(
            'mu-global-ui',
            get_stylesheet_directory_uri() . '/js/global-ui.js',
            [],
            '1.3.0',
            true
        );
    }
    add_action( 'wp_enqueue_scripts', 'mu_ui_enqueue_assets' );
}

// =========================================================================
// 2. HERO DE TIENDA
// =========================================================================

if ( ! function_exists( 'mu_shop_hero_enqueue' ) ) {
    function mu_shop_hero_enqueue() {
        if ( ! is_shop() && ! is_product_category() && ! is_product_tag() ) {
            return;
        }
        wp_enqueue_style(
            'mu-hero',
            get_stylesheet_directory_uri() . '/css/components/hero.css',
            [],
            '1.0.4'
        );
        wp_enqueue_script(
            'mu-hero',
            get_stylesheet_directory_uri() . '/js/hero.js',
            [],
            '1.0.1',
            true
        );
    }
    add_action( 'wp_enqueue_scripts', 'mu_shop_hero_enqueue' );
}

if ( ! function_exists( 'mu_shop_hero' ) ) {
    function mu_shop_hero() {
        if ( ! is_shop() && ! is_product_category() && ! is_product_tag() ) {
            return;
        }

        $current_term = null;
        $description  = '';

        if ( is_product_category() || is_product_tag() ) {
            $current_term = get_queried_object();
            $description  = term_description();
        }

        $icon_search = mu_get_icon( 'search' );
        ?>
        <div class="mu-hero" id="mu-shop-hero">
            <h1 class="mu-hero__title">
                <?php if ( $current_term ) : ?>
                    <span class="mu-hero__title-text"><?php echo esc_html( $current_term->name ); ?></span>
                <?php else : ?>
                    <span class="mu-hero__title-text" data-typing-target>Stickers</span>
                <?php endif; ?>
            </h1>
            <?php if ( $description ) : ?>
            <p class="mu-hero__description"><?php echo wp_kses_post( $description ); ?></p>
            <?php endif; ?>
            <div class="mu-hero__search-wrapper">
                <form class="mu-hero__search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
                    <span class="mu-hero__search-icon"><?php echo $icon_search; // phpcs:ignore ?></span>
                    <input
                        class="mu-hero__search-input"
                        type="search"
                        name="s"
                        placeholder="Buscar productos…"
                        value="<?php echo esc_attr( get_search_query() ); ?>"
                        aria-label="Buscar en la tienda"
                    />
                    <input type="hidden" name="post_type" value="product" />
                </form>
            </div>
        </div>
        <?php
    }
    add_action( 'woocommerce_before_main_content', 'mu_shop_hero', 5 );
}

// =========================================================================
// 3. SECCIÓN DE TESTIMONIOS
// =========================================================================

if ( ! function_exists( 'mu_testimonios_enqueue' ) ) {
    function mu_testimonios_enqueue() {
        if ( ! is_front_page() && ! is_page( 'tienda' ) && ! is_shop() ) {
            return;
        }
        wp_enqueue_style( 'mu-testimonials', get_stylesheet_directory_uri() . '/css/components/testimonials.css', [], '1.0.2' );
        wp_enqueue_script( 'mu-testimonials', get_stylesheet_directory_uri() . '/js/testimonials.js', [], '1.0.0', true );
    }
    add_action( 'wp_enqueue_scripts', 'mu_testimonios_enqueue' );
}

if ( ! function_exists( 'mu_testimonios_section' ) ) {
    function mu_testimonios_section() {
        $api_key        = defined( 'MU_GOOGLE_PLACES_API_KEY' ) ? MU_GOOGLE_PLACES_API_KEY : '';
        $place_id       = 'ChIJ18LlLQPchJURqIDwiZM7t_E';
        $db_option_name = 'mu_reviews_master_db';
        $msg_update     = '';

        if ( $api_key && current_user_can( 'administrator' ) && isset( $_GET['force_reviews'] ) ) {
            $url      = add_query_arg(
                [ 'placeid' => $place_id, 'fields' => 'reviews', 'key' => $api_key, 'language' => 'es' ],
                'https://maps.googleapis.com/maps/api/place/details/json'
            );
            $response = wp_remote_get( $url, [ 'timeout' => 10 ] );

            if ( ! is_wp_error( $response ) ) {
                $data       = json_decode( wp_remote_retrieve_body( $response ), true );
                $new_batch  = $data['result']['reviews'] ?? [];
                $current_db = get_option( $db_option_name, [] );
                $added      = 0;

                foreach ( $new_batch as $item ) {
                    if ( intval( $item['rating'] ) < 5 ) continue;
                    $exists = false;
                    foreach ( $current_db as $stored ) {
                        if ( $stored['time'] == $item['time'] && $stored['author_name'] === $item['author_name'] ) { $exists = true; break; }
                    }
                    if ( ! $exists ) { $current_db[] = $item; $added++; }
                }

                if ( $added > 0 ) {
                    // Cap the stored reviews to the latest 200 to prevent unbounded growth in wp_options.
                    $current_db = array_slice( $current_db, -200 );
                    update_option( $db_option_name, $current_db );
                    $msg_update = sprintf(
                        '<div style="background:#d4edda;color:#155724;padding:10px;text-align:center;border-radius:12px;margin-bottom:20px;font-size:0.9rem;">✅ Se agregaron %d reseñas nuevas.</div>',
                        $added
                    );
                }
            }
        }

        wp_localize_script( 'mu-testimonials', 'muTestimonials', [ 'reviews' => get_option( $db_option_name, [] ) ] );

        ob_start();
        echo wp_kses_post( $msg_update );
        ?>
        <section class="mu-testimonials mu-section">
            <div class="mu-container">
                <div class="mu-testimonials__header">
                    <h2 class="mu-testimonials__title">Lo que dicen nuestros clientes</h2>
                    <div class="mu-testimonials__rating">
                        <div class="mu-testimonials__stars">★★★★★</div>
                        <span class="mu-testimonials__score">5.0</span>
                        <a href="https://g.co/kgs/8bLJBt8" target="_blank" rel="noopener noreferrer" class="mu-testimonials__gmaps-link">
                            <?php echo mu_get_icon( 'google' ); // phpcs:ignore ?>
                            Ver en Google
                        </a>
                        <?php if ( current_user_can( 'administrator' ) ) : ?>
                        <a href="?force_reviews=1" style="color:inherit;">↻ Admin: Actualizar desde Google</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mu-testimonials__track-wrapper">
                    <div class="mu-testimonials__track" id="mu-testimonials-track"></div>
                </div>
                <div class="mu-testimonials__controls">
                    <button class="mu-testimonials__btn mu-testimonials__btn--prev" aria-label="Anterior"><?php echo mu_get_icon( 'arrow-left' ); // phpcs:ignore ?></button>
                    <button class="mu-testimonials__btn mu-testimonials__btn--next" aria-label="Siguiente"><?php echo mu_get_icon( 'arrow-right' ); // phpcs:ignore ?></button>
                </div>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }
}

// =========================================================================
// 4. WHATSAPP FLOTANTE
// =========================================================================

if ( ! function_exists( 'mu_whatsapp_button' ) ) {
    function mu_whatsapp_button() {
        if ( is_checkout() || is_cart() ) {
            return;
        }

        $phone   = defined( 'MU_WHATSAPP_NUMBER' ) ? MU_WHATSAPP_NUMBER : '5492235551234';
        $message = urlencode( '¡Hola! Tengo una consulta sobre sus productos.' );
        $url     = 'https://wa.me/' . $phone . '?text=' . $message;

        echo '<a
            href="' . esc_url( $url ) . '"
            class="mu-whatsapp-float"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Contactar por WhatsApp"
        >' . mu_get_icon( 'whatsapp' ) . '</a>'; // phpcs:ignore
    }
    add_action( 'wp_footer', 'mu_whatsapp_button' );
}

// =========================================================================
// 5. BANNER DE COOKIES
// =========================================================================

if ( ! function_exists( 'mu_cookie_banner' ) ) {
    function mu_cookie_banner() {
        ?>
        <div id="mu-cookie-banner" class="mu-cookie-banner" role="dialog" aria-label="Aviso de cookies" style="display:none;">
            <p>Usamos cookies para mejorar tu experiencia. Al continuar navegando, aceptás su uso.</p>
            <div class="mu-cookie-banner__actions">
                <button id="mu-cookie-accept" class="mu-btn mu-btn--primary">Aceptar</button>
                <a href="/politica-de-privacidad/" class="mu-cookie-banner__link">Más info</a>
            </div>
        </div>
        <?php
    }
    add_action( 'wp_footer', 'mu_cookie_banner' );
}

// =========================================================================
// 6. SECCIÓN DE FEATURES
// =========================================================================

if ( ! function_exists( 'mu_features_section' ) ) {
    function mu_features_section() {
        ?>
        <section class="mu-features mu-section">
            <div class="mu-container">
                <ul class="mu-features__list">
                    <li class="mu-features__item">
                        <?php echo mu_get_icon( 'truck' ); // phpcs:ignore ?>
                        <span>Envíos a todo el país</span>
                    </li>
                    <li class="mu-features__item">
                        <?php echo mu_get_icon( 'shield' ); // phpcs:ignore ?>
                        <span>Compra segura</span>
                    </li>
                    <li class="mu-features__item">
                        <?php echo mu_get_icon( 'heart' ); // phpcs:ignore ?>
                        <span>Hecho con amor</span>
                    </li>
                    <li class="mu-features__item">
                        <?php echo mu_get_icon( 'star' ); // phpcs:ignore ?>
                        <span>Calidad premium</span>
                    </li>
                </ul>
            </div>
        </section>
        <?php
    }
}

// =========================================================================
// 7. SHORTCODES
// =========================================================================

if ( ! function_exists( 'mu_register_ui_shortcodes' ) ) {
    function mu_register_ui_shortcodes() {
        add_shortcode( 'mu_testimonios', 'mu_testimonios_section' );
        add_shortcode( 'mu_features', 'mu_features_section' );
    }
    add_action( 'init', 'mu_register_ui_shortcodes' );
}

// =========================================================================
// 8. OPEN GRAPH / SEO META TAGS
// =========================================================================

if ( ! function_exists( 'mu_og_meta_tags' ) ) {
    function mu_og_meta_tags() {
        if ( ! is_singular() ) {
            return;
        }

        global $post;

        $title       = get_the_title();
        $description = has_excerpt() ? get_the_excerpt() : wp_trim_words( get_the_content(), 20 );
        $image       = get_the_post_thumbnail_url( null, 'large' );
        $url         = get_permalink();

        if ( ! $image ) {
            $image = get_stylesheet_directory_uri() . '/assets/og-default.jpg';
        }

        echo '<meta property="og:type" content="' . ( is_product() ? 'product' : 'article' ) . '" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr( $description ) . '" />' . "\n";
        echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
    }
    add_action( 'wp_head', 'mu_og_meta_tags' );
}

// =========================================================================
// 9. ADMIN STYLES
// =========================================================================

if ( ! function_exists( 'mu_admin_styles' ) ) {
    function mu_admin_styles() {
        echo '<style>
            .mu-admin-badge {
                display: inline-block;
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .mu-admin-badge--digital { background: #e8f4fd; color: #1a6fa8; }
            .mu-admin-badge--physical { background: #fef3cd; color: #856404; }
        </style>';
    }
    add_action( 'admin_head', 'mu_admin_styles' );
}

// =========================================================================
// 10. STRUCTURED DATA (JSON-LD)
// =========================================================================

if ( ! function_exists( 'mu_structured_data' ) ) {
    function mu_structured_data() {
        if ( ! is_product() ) {
            return;
        }

        global $product;
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return;
        }

        $data = [
            '@context'    => 'https://schema.org/',
            '@type'       => 'Product',
            'name'        => get_the_title(),
            'image'       => [ get_the_post_thumbnail_url( null, 'full' ) ],
            'description' => wp_strip_all_tags( $product->get_description() ),
            'sku'         => $product->get_sku(),
            'offers'      => [
                '@type'         => 'Offer',
                'url'           => get_permalink(),
                'priceCurrency' => get_woocommerce_currency(),
                'price'         => $product->get_price(),
                'availability'  => $product->is_in_stock()
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
            ],
        ];

        echo '<script type="application/ld+json">' . wp_json_encode( $data ) . '</script>' . "\n";
    }
    add_action( 'wp_head', 'mu_structured_data' );
}
