<?php
/**
 * Module: Hero Banners Manager
 * Description: Plugin admin para gestionar los banners del Hero de la portada.
 *              Submenu bajo WooCommerce → Marketing (toplevel_page_woocommerce-marketing).
 *              Storage: wp_options 'mu_hero_banners' (array de promos).
 *              Cache: transient 'mu_hero_banners_active' (TTL 12h, invalidado al guardar).
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ==========================================
// 1. CONSTANTES & HELPERS
// ==========================================

if ( ! defined( 'MU_HERO_BANNERS_OPTION' ) ) {
    define( 'MU_HERO_BANNERS_OPTION', 'mu_hero_banners' );
}
if ( ! defined( 'MU_HERO_BANNERS_TRANSIENT' ) ) {
    define( 'MU_HERO_BANNERS_TRANSIENT', 'mu_hero_banners_active' );
}
if ( ! defined( 'MU_HERO_BANNERS_SCREEN' ) ) {
    // Slug del submenu — el screen ID resultante será 'marketing_page_mu-hero-banners'.
    define( 'MU_HERO_BANNERS_SCREEN', 'mu-hero-banners' );
}

if ( ! function_exists( 'mu_hero_banners_default_seed' ) ) {
    /**
     * Datos semilla — coinciden con la lista hardcodeada anterior.
     * Solo se usan si la opción todavía no existe en DB.
     */
    function mu_hero_banners_default_seed() {
        return [
            [
                'id'                    => 'vuelta-al-cole',
                'inicio'                => '01012024',
                'fin'                   => '01032027',
                'imagen'                => '/wp-content/uploads/2026/02/fondo0126.webp',
                'eyebrow'               => 'Vuelta a Clases 2026',
                'titulo'                => 'Etiquetas escolares <span class="mu-highlight">únicas</span>',
                'descripcion'           => 'Personalizadas a mano. Más de 150 diseños diferentes para que nada se pierda.',
                'cta_texto'             => 'Ver Diseños',
                'cta_url'               => '/tienda/escolares/',
                'cta_secundario_texto'  => 'Guía de uso',
                'cta_secundario_url'    => '/guia-etiquetas-personalizadas/',
                'show_free_badge'       => true,
                'free_badge_text'       => '<strong>¡20% OFF!</strong><span>cupón: COLE26</span>',
                'free_badge_url'        => '',
            ],
            [
                'id'                    => 'san-valentin',
                'inicio'                => '01022026',
                'fin'                   => '20022026',
                'imagen'                => '/wp-content/uploads/2026/02/sanvalentin.webp',
                'eyebrow'               => 'San Valentín 14/02',
                'titulo'                => 'Stickers para <span class="mu-highlight">enamorarse</span>',
                'descripcion'           => 'Visitá nuestra selección de etiquetas imprimibles para celebrar el amor.',
                'cta_texto'             => 'Ver Diseños',
                'cta_url'               => '/tienda/eventos/',
                'cta_secundario_texto'  => '',
                'cta_secundario_url'    => '',
                'show_free_badge'       => false,
                'free_badge_text'       => '',
                'free_badge_url'        => '',
            ],
        ];
    }
}

if ( ! function_exists( 'mu_hero_banners_sanitize_one' ) ) {
    /**
     * Sanitiza un único banner. Acepta titulo/free_badge_text con HTML limitado.
     */
    function mu_hero_banners_sanitize_one( $b ) {
        $b = is_array( $b ) ? $b : [];
        return [
            'id'                    => sanitize_title( $b['id'] ?? '' ),
            'inicio'                => preg_match( '/^\d{8}$/', $b['inicio'] ?? '' ) ? $b['inicio'] : '',
            'fin'                   => preg_match( '/^\d{8}$/', $b['fin'] ?? '' )    ? $b['fin']    : '',
            'imagen'                => esc_url_raw( $b['imagen'] ?? '' ),
            'eyebrow'               => sanitize_text_field( $b['eyebrow'] ?? '' ),
            'titulo'                => wp_kses_post( $b['titulo'] ?? '' ),
            'descripcion'           => sanitize_textarea_field( $b['descripcion'] ?? '' ),
            'cta_texto'             => sanitize_text_field( $b['cta_texto'] ?? '' ),
            'cta_url'               => esc_url_raw( $b['cta_url'] ?? '' ),
            'cta_secundario_texto'  => sanitize_text_field( $b['cta_secundario_texto'] ?? '' ),
            'cta_secundario_url'    => esc_url_raw( $b['cta_secundario_url'] ?? '' ),
            'show_free_badge'       => ! empty( $b['show_free_badge'] ),
            'free_badge_text'       => wp_kses_post( $b['free_badge_text'] ?? '' ),
            'free_badge_url'        => esc_url_raw( $b['free_badge_url'] ?? '' ),
        ];
    }
}

if ( ! function_exists( 'mu_get_hero_banners_raw' ) ) {
    /**
     * Lee la lista bruta desde wp_options. Si la opción no existe, devuelve la semilla
     * (sin escribir nada — el primer Save desde el admin lo persistirá).
     */
    function mu_get_hero_banners_raw() {
        $stored = get_option( MU_HERO_BANNERS_OPTION, null );
        if ( ! is_array( $stored ) ) {
            return mu_hero_banners_default_seed();
        }
        return $stored;
    }
}

if ( ! function_exists( 'mu_get_hero_banners' ) ) {
    /**
     * Devuelve los banners activos según fecha (DateTime::createFromFormat 'dmY').
     * Cacheado en transient (12h). Se invalida en update_option(MU_HERO_BANNERS_OPTION).
     */
    function mu_get_hero_banners() {
        $cached = get_transient( MU_HERO_BANNERS_TRANSIENT );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $all    = mu_get_hero_banners_raw();
        $active = [];
        $now    = time();

        foreach ( $all as $p ) {
            if ( empty( $p['inicio'] ) || empty( $p['fin'] ) ) {
                $active[] = $p;
                continue;
            }
            $dt_start = DateTime::createFromFormat( 'dmY', $p['inicio'] );
            $dt_end   = DateTime::createFromFormat( 'dmY', $p['fin'] );
            if ( ! $dt_start || ! $dt_end ) {
                continue;
            }
            $start = $dt_start->setTime( 0, 0, 0 )->getTimestamp();
            $end   = $dt_end->setTime( 23, 59, 59 )->getTimestamp();
            if ( $now >= $start && $now <= $end ) {
                $active[] = $p;
            }
        }

        set_transient( MU_HERO_BANNERS_TRANSIENT, $active, 12 * HOUR_IN_SECONDS );
        return $active;
    }
}

// Invalidación de cache cuando la opción cambia.
add_action( 'update_option_' . MU_HERO_BANNERS_OPTION, function() {
    delete_transient( MU_HERO_BANNERS_TRANSIENT );
} );
add_action( 'add_option_' . MU_HERO_BANNERS_OPTION, function() {
    delete_transient( MU_HERO_BANNERS_TRANSIENT );
} );

// ==========================================
// 2. ADMIN — SUBMENU BAJO WC MARKETING
// ==========================================

if ( is_admin() ) {

    /**
     * Registra la submenu bajo WC → Marketing.
     * Parent slug 'woocommerce-marketing' produce id 'marketing_page_mu-hero-banners'.
     */
    add_action( 'admin_menu', function() {
        add_submenu_page(
            'woocommerce-marketing',
            __( 'Hero Banners', 'mu' ),
            __( 'Hero Banners', 'mu' ),
            'manage_woocommerce',
            MU_HERO_BANNERS_SCREEN,
            'mu_hero_banners_render_admin_page'
        );
    }, 60 );

    /**
     * Carga assets solo en la pantalla del plugin.
     */
    add_action( 'admin_enqueue_scripts', function( $hook ) {
        // Hook esperado: 'marketing_page_mu-hero-banners'.
        if ( false === strpos( (string) $hook, MU_HERO_BANNERS_SCREEN ) ) {
            return;
        }

        wp_enqueue_media();

        $uri = get_stylesheet_directory_uri();
        $ver = wp_get_theme()->get( 'Version' );

        wp_enqueue_style( 'mu-admin-hero-banners', $uri . '/css/admin-hero-banners.css', [], $ver );
        wp_enqueue_script( 'mu-admin-hero-banners-js', $uri . '/js/admin-hero-banners.js', [ 'jquery' ], $ver, true );

        wp_localize_script( 'mu-admin-hero-banners-js', 'muHeroBannersData', [
            'mediaTitle'  => __( 'Seleccionar imagen del banner', 'mu' ),
            'mediaButton' => __( 'Usar esta imagen', 'mu' ),
        ] );
    } );

    /**
     * Maneja POST del formulario antes de renderizar.
     */
    add_action( 'load-marketing_page_' . MU_HERO_BANNERS_SCREEN, 'mu_hero_banners_handle_save' );
}

if ( ! function_exists( 'mu_hero_banners_handle_save' ) ) {
    function mu_hero_banners_handle_save() {
        if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        check_admin_referer( 'mu_hero_banners_save', 'mu_hero_banners_nonce' );

        $raw     = isset( $_POST['mu_banner'] ) && is_array( $_POST['mu_banner'] ) ? wp_unslash( $_POST['mu_banner'] ) : [];
        $cleaned = [];

        foreach ( $raw as $b ) {
            // Saltar filas marcadas como eliminadas o totalmente vacías.
            if ( ! empty( $b['_delete'] ) ) {
                continue;
            }
            if ( empty( $b['titulo'] ) && empty( $b['imagen'] ) && empty( $b['cta_url'] ) ) {
                continue;
            }
            $cleaned[] = mu_hero_banners_sanitize_one( $b );
        }

        update_option( MU_HERO_BANNERS_OPTION, $cleaned, false );

        // Redirect PRG para evitar resubmit.
        wp_safe_redirect( add_query_arg( [
            'page'    => MU_HERO_BANNERS_SCREEN,
            'updated' => '1',
        ], admin_url( 'admin.php' ) ) );
        exit;
    }
}

if ( ! function_exists( 'mu_hero_banners_render_admin_page' ) ) {
    function mu_hero_banners_render_admin_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'No tenés permisos para acceder a esta página.', 'mu' ) );
        }

        $banners = mu_get_hero_banners_raw();
        $updated = isset( $_GET['updated'] ) && '1' === $_GET['updated'];
        ?>
        <div class="wrap mu-hero-banners-wrap">
            <h1><?php esc_html_e( 'Hero Banners — Portada', 'mu' ); ?></h1>
            <p class="description">
                <?php esc_html_e( 'Gestioná los banners del slider principal de la home. Las fechas usan formato DDMMAAAA. Si dejás fechas vacías, el banner se muestra siempre.', 'mu' ); ?>
            </p>

            <?php if ( $updated ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Banners actualizados.', 'mu' ); ?></p></div>
            <?php endif; ?>

            <form method="post" action="" class="mu-hero-banners-form">
                <?php wp_nonce_field( 'mu_hero_banners_save', 'mu_hero_banners_nonce' ); ?>

                <div id="mu-hero-banners-list" class="mu-hero-banners-list">
                    <?php foreach ( $banners as $i => $b ) : ?>
                        <?php mu_hero_banners_render_row( $i, $b ); ?>
                    <?php endforeach; ?>
                </div>

                <p>
                    <button type="button" class="button button-secondary" id="mu-hero-banners-add">
                        + <?php esc_html_e( 'Agregar banner', 'mu' ); ?>
                    </button>
                </p>

                <?php submit_button( __( 'Guardar cambios', 'mu' ) ); ?>
            </form>

            <template id="mu-hero-banner-template"><?php mu_hero_banners_render_row( '__INDEX__', [] ); ?></template>
        </div>
        <?php
    }
}

if ( ! function_exists( 'mu_hero_banners_render_row' ) ) {
    function mu_hero_banners_render_row( $index, $b ) {
        $b = wp_parse_args( $b, [
            'id'                    => '',
            'inicio'                => '',
            'fin'                   => '',
            'imagen'                => '',
            'eyebrow'               => '',
            'titulo'                => '',
            'descripcion'           => '',
            'cta_texto'             => '',
            'cta_url'               => '',
            'cta_secundario_texto'  => '',
            'cta_secundario_url'    => '',
            'show_free_badge'       => false,
            'free_badge_text'       => '',
            'free_badge_url'        => '',
        ] );
        $name = 'mu_banner[' . esc_attr( $index ) . ']';
        ?>
        <div class="mu-hero-banner-row" data-index="<?php echo esc_attr( $index ); ?>">
            <div class="mu-hero-banner-row__head">
                <h2 class="mu-hero-banner-row__title">
                    <?php echo $b['titulo'] ? wp_kses_post( $b['titulo'] ) : esc_html__( 'Nuevo banner', 'mu' ); ?>
                </h2>
                <button type="button" class="button-link-delete mu-hero-banner-row__remove">
                    <?php esc_html_e( 'Eliminar', 'mu' ); ?>
                </button>
                <input type="hidden" name="<?php echo esc_attr( $name ); ?>[_delete]" value="0" class="mu-hero-banner-row__delete-flag">
            </div>

            <div class="mu-hero-banner-row__grid">
                <p>
                    <label><strong><?php esc_html_e( 'ID (slug)', 'mu' ); ?></strong></label>
                    <input type="text" name="<?php echo esc_attr( $name ); ?>[id]" value="<?php echo esc_attr( $b['id'] ); ?>" class="regular-text">
                </p>
                <p>
                    <label><strong><?php esc_html_e( 'Eyebrow', 'mu' ); ?></strong></label>
                    <input type="text" name="<?php echo esc_attr( $name ); ?>[eyebrow]" value="<?php echo esc_attr( $b['eyebrow'] ); ?>" class="regular-text">
                </p>
                <p class="mu-hero-banner-row__full">
                    <label><strong><?php esc_html_e( 'Título (HTML permitido — <span class="mu-highlight">…</span>)', 'mu' ); ?></strong></label>
                    <input type="text" name="<?php echo esc_attr( $name ); ?>[titulo]" value="<?php echo esc_attr( $b['titulo'] ); ?>" class="large-text">
                </p>
                <p class="mu-hero-banner-row__full">
                    <label><strong><?php esc_html_e( 'Descripción', 'mu' ); ?></strong></label>
                    <textarea name="<?php echo esc_attr( $name ); ?>[descripcion]" rows="2" class="large-text"><?php echo esc_textarea( $b['descripcion'] ); ?></textarea>
                </p>

                <p class="mu-hero-banner-row__full mu-hero-banner-row__image">
                    <label><strong><?php esc_html_e( 'Imagen de fondo', 'mu' ); ?></strong></label>
                    <span class="mu-hero-banner-row__image-controls">
                        <input type="text" name="<?php echo esc_attr( $name ); ?>[imagen]" value="<?php echo esc_attr( $b['imagen'] ); ?>" class="large-text mu-hero-banner-row__image-url" placeholder="/wp-content/uploads/...">
                        <button type="button" class="button mu-hero-banner-row__image-pick"><?php esc_html_e( 'Elegir imagen', 'mu' ); ?></button>
                    </span>
                    <?php if ( ! empty( $b['imagen'] ) ) : ?>
                        <img src="<?php echo esc_url( $b['imagen'] ); ?>" alt="" class="mu-hero-banner-row__image-preview">
                    <?php else : ?>
                        <img src="" alt="" class="mu-hero-banner-row__image-preview" style="display:none;">
                    <?php endif; ?>
                </p>

                <p>
                    <label><strong><?php esc_html_e( 'Inicio (DDMMAAAA)', 'mu' ); ?></strong></label>
                    <input type="text" name="<?php echo esc_attr( $name ); ?>[inicio]" value="<?php echo esc_attr( $b['inicio'] ); ?>" pattern="\d{8}" placeholder="01012026" class="regular-text">
                </p>
                <p>
                    <label><strong><?php esc_html_e( 'Fin (DDMMAAAA)', 'mu' ); ?></strong></label>
                    <input type="text" name="<?php echo esc_attr( $name ); ?>[fin]" value="<?php echo esc_attr( $b['fin'] ); ?>" pattern="\d{8}" placeholder="31122026" class="regular-text">
                </p>

                <p>
                    <label><strong><?php esc_html_e( 'CTA principal — texto', 'mu' ); ?></strong></label>
                    <input type="text" name="<?php echo esc_attr( $name ); ?>[cta_texto]" value="<?php echo esc_attr( $b['cta_texto'] ); ?>" class="regular-text">
                </p>
                <p>
                    <label><strong><?php esc_html_e( 'CTA principal — URL o ruta relativa', 'mu' ); ?></strong></label>
                    <input type="text" name="<?php echo esc_attr( $name ); ?>[cta_url]" value="<?php echo esc_attr( $b['cta_url'] ); ?>" class="regular-text" placeholder="/tienda/escolares/">
                </p>

                <p>
                    <label><strong><?php esc_html_e( 'CTA secundario — texto', 'mu' ); ?></strong></label>
                    <input type="text" name="<?php echo esc_attr( $name ); ?>[cta_secundario_texto]" value="<?php echo esc_attr( $b['cta_secundario_texto'] ); ?>" class="regular-text">
                </p>
                <p>
                    <label><strong><?php esc_html_e( 'CTA secundario — URL o ruta relativa', 'mu' ); ?></strong></label>
                    <input type="text" name="<?php echo esc_attr( $name ); ?>[cta_secundario_url]" value="<?php echo esc_attr( $b['cta_secundario_url'] ); ?>" class="regular-text" placeholder="/guia-etiquetas-personalizadas/">
                </p>

                <p>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr( $name ); ?>[show_free_badge]" value="1" <?php checked( ! empty( $b['show_free_badge'] ) ); ?>>
                        <strong><?php esc_html_e( 'Mostrar badge promo', 'mu' ); ?></strong>
                    </label>
                </p>
                <p>
                    <label><strong><?php esc_html_e( 'Badge — URL o ruta relativa', 'mu' ); ?></strong></label>
                    <input type="text" name="<?php echo esc_attr( $name ); ?>[free_badge_url]" value="<?php echo esc_attr( $b['free_badge_url'] ); ?>" class="regular-text" placeholder="/tienda/escolares/">
                </p>
                <p class="mu-hero-banner-row__full">
                    <label><strong><?php esc_html_e( 'Badge — Texto (HTML permitido)', 'mu' ); ?></strong></label>
                    <input type="text" name="<?php echo esc_attr( $name ); ?>[free_badge_text]" value="<?php echo esc_attr( $b['free_badge_text'] ); ?>" class="large-text" placeholder='<strong>¡20% OFF!</strong><span>cupón: COLE26</span>'>
                </p>
            </div>
        </div>
        <?php
    }
}
