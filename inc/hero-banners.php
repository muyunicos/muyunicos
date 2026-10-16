<?php
/**
 * Module: Hero Banners Manager
 * Description: Plugin admin para gestionar los banners del Hero de la portada.
 *              Submenu bajo WooCommerce → Marketing (toplevel_page_woocommerce-marketing).
 *              Storage: wp_options 'mu_hero_banners' (array de promos).
 *              Cache: transient por país 'mu_hero_banners_active_{PAIS}' (TTL 12h),
 *              invalidado al guardar vía mu_hero_banners_purge_cache().
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
                'paises'                => [ 'AR' ],
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
                'paises'                => [ 'AR' ],
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

        // Países permitidos: solo claves válidas de muyu_get_countries_data()
        $valid_paises = array_keys( function_exists( 'muyu_get_countries_data' ) ? muyu_get_countries_data() : [] );
        $raw_paises   = isset( $b['paises'] ) && is_array( $b['paises'] ) ? $b['paises'] : [];
        $paises       = array_values( array_intersect( $raw_paises, $valid_paises ) );

        // Idioma activo: solo controla la pestaña abierta en el admin (ES por defecto).
        // El estado real de idiomas del banner vive en traducir_en / traducir_pt.
        $idioma_activo = strtolower( (string) ( $b['idioma_activo'] ?? '' ) );
        if ( ! in_array( $idioma_activo, [ 'es', 'en', 'pt' ], true ) ) {
            $idioma_activo = 'es';
        }

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
            'paises'                => $paises,
            'idioma_activo'         => $idioma_activo,
            'traducir_pt'           => ! empty( $b['traducir_pt'] ),
            'traducir_en'           => ! empty( $b['traducir_en'] ),
            // Campos traducidos (portugués)
            'titulo_pt'             => wp_kses_post( $b['titulo_pt'] ?? '' ),
            'descripcion_pt'        => sanitize_textarea_field( $b['descripcion_pt'] ?? '' ),
            'cta_texto_pt'          => sanitize_text_field( $b['cta_texto_pt'] ?? '' ),
            'cta_secundario_texto_pt'=> sanitize_text_field( $b['cta_secundario_texto_pt'] ?? '' ),
            'free_badge_text_pt'    => wp_kses_post( $b['free_badge_text_pt'] ?? '' ),
            // Campos traducidos (inglés)
            'titulo_en'             => wp_kses_post( $b['titulo_en'] ?? '' ),
            'descripcion_en'        => sanitize_textarea_field( $b['descripcion_en'] ?? '' ),
            'cta_texto_en'          => sanitize_text_field( $b['cta_texto_en'] ?? '' ),
            'cta_secundario_texto_en'=> sanitize_text_field( $b['cta_secundario_texto_en'] ?? '' ),
            'free_badge_text_en'    => wp_kses_post( $b['free_badge_text_en'] ?? '' ),
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

if ( ! function_exists( 'mu_hero_banners_transient_key' ) ) {
    /**
     * Clave de transient por país.
     *
     * El contenido cacheado DEPENDE del país (el filtro por `paises` se resuelve
     * en mu_get_hero_banners()), y el object cache de LiteSpeed (LSMCD) es
     * compartido entre todos los subdominios. Con una clave única global, el
     * primer país que poblaba el transient contaminaba a todos los demás.
     *
     * El idioma NO forma parte de la clave: los banners se cachean completos
     * (con todas sus traducciones) y el idioma se resuelve al renderizar
     * mu_hero_section(), no al filtrar.
     *
     * @param string $pais Código ISO del país (AR, MX, BR...).
     * @return string Clave de transient.
     */
    function mu_hero_banners_transient_key( $pais ) {
        return MU_HERO_BANNERS_TRANSIENT . '_' . strtoupper( (string) $pais );
    }
}

if ( ! function_exists( 'mu_hero_banners_purge_cache' ) ) {
    /**
     * Borra los transients de todas las variantes por país (y la clave legacy).
     */
    function mu_hero_banners_purge_cache() {
        // Clave legacy (anterior al soporte multi-país).
        delete_transient( MU_HERO_BANNERS_TRANSIENT );

        $paises = [ 'AR' ];
        if ( function_exists( 'muyu_get_countries_data' ) ) {
            $paises = array_merge( $paises, array_keys( muyu_get_countries_data() ) );
        }

        foreach ( array_unique( $paises ) as $code ) {
            delete_transient( mu_hero_banners_transient_key( $code ) );
        }
    }
}

if ( ! function_exists( 'mu_get_hero_banners' ) ) {
    /**
     * Devuelve los banners activos según país y fecha (DateTime::createFromFormat 'dmY').
     *
     * Cacheado en transient por país (12h): la clave incluye el país porque el
     * resultado depende de él (ver mu_hero_banners_transient_key()). Se invalida
     * con mu_hero_banners_purge_cache() en update_option/add_option.
     */
    function mu_get_hero_banners() {
        // País actual detectado por subdominio (cero DB, caché static por request).
        // Se detecta ANTES de leer el transient: la clave depende de él.
        $pais_actual = function_exists( 'muyu_get_current_country_from_subdomain' )
            ? muyu_get_current_country_from_subdomain()
            : 'AR';

        $transient_key = mu_hero_banners_transient_key( $pais_actual );

        $cached = get_transient( $transient_key );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $all    = mu_get_hero_banners_raw();
        $active = [];
        $now    = time();

        foreach ( $all as $p ) {
            // Filtro por país: si el banner define países, el país actual debe estar incluido.
            // Vacío = no se muestra en ningún país.
            $paises = isset( $p['paises'] ) && is_array( $p['paises'] ) ? $p['paises'] : [ 'AR' ];
            if ( ! in_array( $pais_actual, $paises, true ) ) {
                continue;
            }

            // Filtro por idioma: el idioma NO excluye el banner. El banner se
            // muestra siempre que el país coincida; si existe traducción para el
            // idioma actual se usa esa versión (ver mu_hero_section), y si no,
            // se muestra la versión en español como fallback.

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

        set_transient( $transient_key, $active, 12 * HOUR_IN_SECONDS );
        return $active;
    }
}

// Invalidación de cache cuando la opción cambia (todas las variantes por país).
add_action( 'update_option_' . MU_HERO_BANNERS_OPTION, 'mu_hero_banners_purge_cache' );
add_action( 'add_option_' . MU_HERO_BANNERS_OPTION, 'mu_hero_banners_purge_cache' );

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
        $dir = get_stylesheet_directory();
        $ver = wp_get_theme()->get( 'Version' );

        // Cache-busting por filemtime: evita que el navegador sirva una versión
        // vieja del CSS/JS del módulo tras desplegar cambios.
        $css_file = $dir . '/css/admin-hero-banners.css';
        $js_file  = $dir . '/js/admin-hero-banners.js';
        $css_ver  = file_exists( $css_file ) ? (string) filemtime( $css_file ) : $ver;
        $js_ver   = file_exists( $js_file ) ? (string) filemtime( $js_file ) : $ver;

        wp_enqueue_style( 'mu-admin-hero-banners', $uri . '/css/admin-hero-banners.css', [], $css_ver );
        wp_enqueue_script( 'mu-admin-hero-banners-js', $uri . '/js/admin-hero-banners.js', [ 'jquery' ], $js_ver, true );

        if ( wp_script_is( 'mu-admin-hero-banners-js', 'enqueued' ) ) {
            wp_localize_script( 'mu-admin-hero-banners-js', 'muHeroBannersData', [
                'mediaTitle'  => __( 'Seleccionar imagen del banner', 'mu' ),
                'mediaButton' => __( 'Usar esta imagen', 'mu' ),
                'removed'     => __( 'Eliminado (se descarta al guardar)', 'mu' ),
            ] );
        }
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

        // [Hash Gate] Comparar lo nuevo contra lo almacenado con maybe_serialize()
        // y actuar solo si cambió realmente. Evita invalidar el object cache y
        // purgar la caché de página en guardados sin cambios reales.
        $before = get_option( MU_HERO_BANNERS_OPTION, null );

        if ( maybe_serialize( $before ) === maybe_serialize( $cleaned ) ) {
            // Sin cambios: no se toca la opción, ni el cache, ni la caché de página.
            wp_safe_redirect( add_query_arg( [
                'page'    => MU_HERO_BANNERS_SCREEN,
                'updated' => '1',
            ], admin_url( 'admin.php' ) ) );
            exit;
        }

        update_option( MU_HERO_BANNERS_OPTION, $cleaned, false );
        // update_option() dispara el hook 'update_option_' . MU_HERO_BANNERS_OPTION,
        // que llama a mu_hero_banners_purge_cache(): los transients por país
        // quedan invalidados sin necesidad de una purga explícita adicional.

        // Purga condicional de la caché de página: el Hero se renderiza en la home
        // de todos los subdominios, así que un cambio real en los banners exige
        // regenerarla. Condicionada por el Hash Gate, en línea con la norma
        // vigente (prohibido litespeed_purge_all() incondicional).
        do_action( 'litespeed_purge_all' );

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
                <?php esc_html_e( 'Gestioná los banners del slider principal de la home. Las fechas usan formato DDMMAAAA: si dejás ambas vacías, el banner se muestra siempre. Cada banner se muestra sólo en los países tildados. En los sitios en otro idioma se usa la traducción del banner si está marcada y cargada; si no, se muestra la versión en español.', 'mu' ); ?>
            </p>

            <?php if ( $updated ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Banners actualizados.', 'mu' ); ?></p></div>
            <?php endif; ?>

            <form method="post" action="">
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
            'paises'                => [ 'AR' ],
            'idioma_activo'         => 'es',
            'traducir_pt'           => false,
            'traducir_en'           => false,
            'titulo_pt'             => '',
            'descripcion_pt'        => '',
            'cta_texto_pt'          => '',
            'cta_secundario_texto_pt'=> '',
            'free_badge_text_pt'    => '',
            'titulo_en'             => '',
            'descripcion_en'        => '',
            'cta_texto_en'          => '',
            'cta_secundario_texto_en'=> '',
            'free_badge_text_en'    => '',
        ] );
        $name = 'mu_banner[' . esc_attr( $index ) . ']';
        ?>
        <div class="mu-hero-banner-row" data-index="<?php echo esc_attr( $index ); ?>">
            <div class="mu-hero-banner-row__head">
                <h2 class="mu-hero-banner-row__title">
                    <?php echo $b['titulo'] ? wp_kses_post( $b['titulo'] ) : esc_html__( 'Nuevo banner', 'mu' ); ?>
                </h2>

                <?php
                $countries_data   = function_exists( 'muyu_get_countries_data' ) ? muyu_get_countries_data() : [];
                $idioma_activo_lc = $b['idioma_activo'];              // es | en | pt (pestaña del editor)
                $idioma_activo    = strtoupper( $idioma_activo_lc );  // ES | EN | PT (etiqueta visible)
                ?>

                <!-- Selector de países -->
                <button type="button" class="button mu-hero-banner-paises-btn"><?php esc_html_e( 'Países', 'mu' ); ?></button>
                <div class="mu-hero-banner-paises-dropdown">
                    <?php foreach ( $countries_data as $code => $c ) : ?>
                    <label style="display:block; font-size:12px; padding:2px 8px; cursor:pointer;">
                        <input type="checkbox" name="<?php echo esc_attr( $name ); ?>[paises][]" value="<?php echo esc_attr( $code ); ?>"<?php checked( in_array( $code, $b['paises'], true ) ); ?>>
                        <?php echo esc_html( $c['name'] ); ?> (<?php echo esc_html( $code ); ?>)
                    </label>
                    <?php endforeach; ?>
                </div>
                <span class="mu-hero-banner-paises-pills">
                    <?php foreach ( $b['paises'] as $code ) : if ( isset( $countries_data[ $code ] ) ) : ?>
                        <span class="mu-hero-banner-pill"><?php echo esc_html( $code ); ?></span>
                    <?php endif; endforeach; ?>
                </span>

                <!-- Selector de idioma (pestaña activa del editor) -->
                <button type="button" class="button mu-hero-banner-idioma-btn" data-idioma="<?php echo esc_attr( $idioma_activo ); ?>">
                    [<?php echo esc_html( $idioma_activo ); ?>▼]
                </button>
                <input type="hidden" name="<?php echo esc_attr( $name ); ?>[idioma_activo]" value="<?php echo esc_attr( $idioma_activo_lc ); ?>" class="mu-hero-banner-idioma-valor">
                <div class="mu-hero-banner-idioma-dropdown">
                    <?php foreach ( [ 'es' => 'ES', 'en' => 'EN', 'pt' => 'PT' ] as $code => $label ) : ?>
                    <button type="button" class="mu-hero-banner-idioma-item" data-idioma="<?php echo esc_attr( $code ); ?>"<?php echo $idioma_activo_lc === $code ? ' style="display:none;"' : ''; ?>>
                        [<?php echo esc_html( $label ); ?>▼]
                    </button>
                    <?php endforeach; ?>
                </div>

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

                <!-- Traducciones (ES es el idioma base: solo EN y PT tienen bloque) -->
                <?php foreach ( [ 'pt' => 'Portugués', 'en' => 'Inglés' ] as $lang => $lang_name ) : ?>
                <?php
                $traducir_on = ! empty( $b[ 'traducir_' . $lang ] );
                $es_pestania = ( $idioma_activo_lc === $lang );
                ?>
                <div class="mu-hero-banner-traducir-wrapper" data-lang="<?php echo esc_attr( $lang ); ?>" style="display:<?php echo $es_pestania ? 'block' : 'none'; ?>;">
                    <p>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr( $name ); ?>[traducir_<?php echo esc_attr( $lang ); ?>]" value="1"<?php checked( $traducir_on ); ?> class="mu-hero-banner-traducir-checkbox" data-lang="<?php echo esc_attr( $lang ); ?>">
                            <?php echo esc_html( sprintf( __( 'Traducir a %s', 'mu' ), $lang_name ) ); ?>
                        </label>
                    </p>
                    <div class="mu-hero-banner-traducir-fields mu-hero-banner-traducir-<?php echo esc_attr( $lang ); ?>" style="display:<?php echo $traducir_on ? 'block' : 'none'; ?>; border:1px solid #ddd; padding:10px; margin-top:8px; background:#f9f9f9;">
                        <p>
                            <label><strong><?php echo esc_html( sprintf( __( 'Título — %s', 'mu' ), $lang_name ) ); ?></strong></label>
                            <input type="text" name="<?php echo esc_attr( $name ); ?>[titulo_<?php echo esc_attr( $lang ); ?>]" value="<?php echo esc_attr( $b[ 'titulo_' . $lang ] ?? '' ); ?>" class="large-text">
                        </p>
                        <p>
                            <label><strong><?php echo esc_html( sprintf( __( 'Descripción — %s', 'mu' ), $lang_name ) ); ?></strong></label>
                            <textarea name="<?php echo esc_attr( $name ); ?>[descripcion_<?php echo esc_attr( $lang ); ?>]" rows="2" class="large-text"><?php echo esc_textarea( $b[ 'descripcion_' . $lang ] ?? '' ); ?></textarea>
                        </p>
                        <p>
                            <label><strong><?php echo esc_html( sprintf( __( 'CTA principal — texto — %s', 'mu' ), $lang_name ) ); ?></strong></label>
                            <input type="text" name="<?php echo esc_attr( $name ); ?>[cta_texto_<?php echo esc_attr( $lang ); ?>]" value="<?php echo esc_attr( $b[ 'cta_texto_' . $lang ] ?? '' ); ?>" class="regular-text">
                        </p>
                        <p>
                            <label><strong><?php echo esc_html( sprintf( __( 'CTA secundario — texto — %s', 'mu' ), $lang_name ) ); ?></strong></label>
                            <input type="text" name="<?php echo esc_attr( $name ); ?>[cta_secundario_texto_<?php echo esc_attr( $lang ); ?>]" value="<?php echo esc_attr( $b[ 'cta_secundario_texto_' . $lang ] ?? '' ); ?>" class="regular-text">
                        </p>
                        <p>
                            <label><strong><?php echo esc_html( sprintf( __( 'Badge — texto — %s', 'mu' ), $lang_name ) ); ?></strong></label>
                            <input type="text" name="<?php echo esc_attr( $name ); ?>[free_badge_text_<?php echo esc_attr( $lang ); ?>]" value="<?php echo esc_attr( $b[ 'free_badge_text_' . $lang ] ?? '' ); ?>" class="large-text">
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}
