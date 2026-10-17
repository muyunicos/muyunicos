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
        // El estado de cada idioma se DERIVA del contenido (ver traducir_pt / traducir_en
        // más abajo): no hay casilla que lo habilite.
        $idioma_activo = strtolower( (string) ( $b['idioma_activo'] ?? '' ) );
        if ( ! in_array( $idioma_activo, [ 'es', 'en', 'pt' ], true ) ) {
            $idioma_activo = 'es';
        }

        // Campos que existen por idioma (base ES + sufijos _pt / _en).
        // El valor es la función de sanitización que corresponde a cada uno.
        $lang_fields = [
            'titulo'                 => 'wp_kses_post',
            'descripcion'            => 'sanitize_textarea_field',
            'eyebrow'                => 'sanitize_text_field',
            'imagen'                 => 'esc_url_raw',
            'cta_texto'              => 'sanitize_text_field',
            'cta_url'                => 'esc_url_raw',
            'cta_secundario_texto'   => 'sanitize_text_field',
            'cta_secundario_url'     => 'esc_url_raw',
            'free_badge_text'        => 'wp_kses_post',
            'free_badge_url'         => 'esc_url_raw',
        ];

        // Valores base (español).
        $lang_values = [];
        foreach ( $lang_fields as $field => $sanitize_cb ) {
            $lang_values[ $field ] = $sanitize_cb( $b[ $field ] ?? '' );
        }

        // Traducciones con [sobrescritura dispersa]: si el valor del idioma es
        // idéntico al de español, se guarda vacío. El front, al encontrar el
        // campo vacío, usa el de español (ver mu_hero_section()). Así la BD no
        // guarda copias redundantes y editar el español propaga a los idiomas
        // que nunca sobrescribieron ese campo.
        foreach ( [ 'pt', 'en' ] as $lang ) {
            foreach ( $lang_fields as $field => $sanitize_cb ) {
                $value = $sanitize_cb( $b[ $field . '_' . $lang ] ?? '' );
                $lang_values[ $field . '_' . $lang ] = ( $value === $lang_values[ $field ] ) ? '' : $value;
            }
        }

        return array_merge(
            [
                'id'              => sanitize_title( $b['id'] ?? '' ),
                'inicio'          => preg_match( '/^\d{8}$/', $b['inicio'] ?? '' ) ? $b['inicio'] : '',
                'fin'             => preg_match( '/^\d{8}$/', $b['fin'] ?? '' )    ? $b['fin']    : '',
                'show_free_badge' => ! empty( $b['show_free_badge'] ),
                'paises'          => $paises,
                'idioma_activo'   => $idioma_activo,
                // [Automático] Un idioma "tiene traducción" cuando guarda algún valor
                // propio: como lo idéntico al español se guarda vacío, el contenido
                // alcanza para saberlo. Se conserva por compatibilidad (la base
                // describe su propio estado), pero el front ya no decide con esto:
                // usa mu_hero_banner_field().
                'traducir_pt'     => mu_hero_banner_lang_has_translation( $lang_values, 'pt' ),
                'traducir_en'     => mu_hero_banner_lang_has_translation( $lang_values, 'en' ),
            ],
            $lang_values
        );
    }
}

if ( ! function_exists( 'mu_hero_banner_lang_all_fields' ) ) {
    /**
     * Los 10 campos que existen por idioma (base ES + sufijos _pt / _en).
     *
     * @return string[]
     */
    function mu_hero_banner_lang_all_fields() {
        return [
            'titulo',
            'descripcion',
            'eyebrow',
            'imagen',
            'cta_texto',
            'cta_url',
            'cta_secundario_texto',
            'cta_secundario_url',
            'free_badge_text',
            'free_badge_url',
        ];
    }
}

if ( ! function_exists( 'mu_hero_banner_lang_fields' ) ) {
    /**
     * Campos que la IA traduce en un banner. Los declara el servicio (ámbito
     * hero_banner); si el módulo no está cargado queda la misma lista, para que el
     * resto del banner no dependa de él.
     *
     * @return string[]
     */
    function mu_hero_banner_lang_fields() {
        if ( function_exists( 'mu_ai_ambito' ) ) {
            $def = mu_ai_ambito( 'hero_banner' );
            if ( ! empty( $def['fields'] ) && is_array( $def['fields'] ) ) {
                return array_values( $def['fields'] );
            }
        }

        return [ 'titulo', 'descripcion', 'eyebrow', 'cta_texto', 'cta_secundario_texto', 'free_badge_text' ];
    }
}

if ( ! function_exists( 'mu_hero_banner_lang_has_translation' ) ) {
    /**
     * ¿Ese idioma guarda algún valor propio (distinto del español)?
     *
     * [Automático] Al guardarse vacío lo idéntico al español (sobrescritura
     * dispersa), tener contenido equivale a tener traducción real: no hace falta
     * ninguna casilla que lo habilite.
     *
     * @param array  $b    Banner (guardado o saneado).
     * @param string $lang Idioma a consultar (pt | en).
     * @return bool
     */
    function mu_hero_banner_lang_has_translation( $b, $lang ) {
        if ( 'es' === $lang || ! is_array( $b ) ) {
            return false;
        }

        foreach ( mu_hero_banner_lang_all_fields() as $field ) {
            if ( '' !== (string) ( $b[ $field . '_' . $lang ] ?? '' ) ) {
                return true;
            }
        }

        return false;
    }
}

if ( ! function_exists( 'mu_hero_banner_lang_values' ) ) {
    /**
     * Valores propios de un idioma, tal como se guardan (para detectar cambios).
     *
     * @param array  $b
     * @param string $lang
     * @return array<string,string>
     */
    function mu_hero_banner_lang_values( $b, $lang ) {
        $out = [];

        foreach ( mu_hero_banner_lang_all_fields() as $field ) {
            $out[ $field ] = (string) ( $b[ $field . '_' . $lang ] ?? '' );
        }

        return $out;
    }
}

if ( ! function_exists( 'mu_hero_banner_es_source' ) ) {
    /**
     * Español que sirve de origen a la traducción: solo los campos que traduce la
     * IA (título, descripción, eyebrow, CTA, CTA secundario y badge). Las URLs y la
     * imagen no se traducen, así que editarlas no invalida una traducción.
     *
     * @param array $b
     * @return string
     */
    function mu_hero_banner_es_source( $b ) {
        $parts = [];

        foreach ( mu_hero_banner_lang_fields() as $field ) {
            $parts[] = (string) ( $b[ $field ] ?? '' );
        }

        return implode( "\n", $parts );
    }
}

if ( ! function_exists( 'mu_hero_banner_lang_hash' ) ) {
    /**
     * Marca de origen de la traducción de un banner: hash del español que la produjo.
     *
     * @param array $b
     * @return string
     */
    function mu_hero_banner_lang_hash( $b ) {
        return function_exists( 'mu_ai_hash' ) ? mu_ai_hash( mu_hero_banner_es_source( $b ) ) : '';
    }
}

if ( ! function_exists( 'mu_hero_banner_lang_is_stale' ) ) {
    /**
     * ¿El español cambió desde que se guardó esa traducción?
     *
     * @param array  $b
     * @param string $lang
     * @return bool
     */
    function mu_hero_banner_lang_is_stale( $b, $lang ) {
        if ( 'es' === $lang || ! function_exists( 'mu_ai_is_stale' ) || ! is_array( $b ) ) {
            return false;
        }

        $hash = (string) ( $b[ 'traducir_' . $lang . '_hash' ] ?? '' );

        // Cualquier valor propio sirve como "traducción" para la comparación: lo que
        // se compara es el hash del español guardado contra la marca de origen.
        $sample = '';
        foreach ( mu_hero_banner_lang_all_fields() as $field ) {
            $value = (string) ( $b[ $field . '_' . $lang ] ?? '' );
            if ( '' !== $value ) {
                $sample = $value;
                break;
            }
        }

        return mu_ai_is_stale( mu_hero_banner_es_source( $b ), $sample, $hash );
    }
}

if ( ! function_exists( 'mu_hero_banner_field' ) ) {
    /**
     * Valor de un campo del banner en el idioma pedido.
     *
     * [Automático] Si el idioma tiene un valor propio se usa ese; si no (campo
     * vacío o igual al español, que es cuando no se persiste), se usa el español.
     * Antes se exigía además la casilla traducir_XX.
     *
     * @param array  $promo Banner.
     * @param string $base  Nombre del campo sin sufijo (titulo, cta_url…).
     * @param string $lang  Idioma actual (es | pt | en).
     * @return string
     */
    function mu_hero_banner_field( $promo, $base, $lang ) {
        if ( 'es' !== $lang && is_array( $promo ) ) {
            $value = (string) ( $promo[ $base . '_' . $lang ] ?? '' );
            if ( '' !== $value ) {
                return $value;
            }
        }

        return (string) ( $promo[ $base ] ?? '' );
    }
}

if ( ! function_exists( 'mu_hero_banner_with_origin_marks' ) ) {
    /**
     * Marca de origen de cada traducción del banner (FR-013).
     *
     * Se recalcula SOLO si la traducción de ese idioma cambió respecto del banner
     * guardado; si no cambió, se conserva la marca anterior. Si se recalculara
     * siempre, quedaría idéntica al español recién guardado y el aviso de
     * "traducción desactualizada" no aparecería nunca (defecto que tenía el editor
     * de reseñas, corregido en la misma pasada).
     *
     * @param array      $clean    Banner ya saneado.
     * @param array|null $previous Banner guardado antes de este guardado (null si es nuevo).
     * @return array Banner con traducir_pt_hash / traducir_en_hash.
     */
    function mu_hero_banner_with_origin_marks( $clean, $previous ) {
        foreach ( [ 'pt', 'en' ] as $lang ) {
            $key = 'traducir_' . $lang . '_hash';

            if ( ! mu_hero_banner_lang_has_translation( $clean, $lang ) ) {
                $clean[ $key ] = '';
                continue;
            }

            if ( ! is_array( $previous ) ) {
                // Banner nuevo (o sin id): la traducción se está creando ahora.
                $clean[ $key ] = mu_hero_banner_lang_hash( $clean );
                continue;
            }

            $changed = ( mu_hero_banner_lang_values( $clean, $lang ) !== mu_hero_banner_lang_values( $previous, $lang ) );

            $clean[ $key ] = $changed
                ? mu_hero_banner_lang_hash( $clean )
                : (string) ( $previous[ $key ] ?? '' );
        }

        return $clean;
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

        // Componente compartido con el editor de reseñas (píldoras ES|PT|EN + botón
        // "Traducir con IA"): una sola definición para los dos editores.
        $pills_file = $dir . '/css/components/ai-translate-inline.css';
        $pills_ver  = file_exists( $pills_file ) ? (string) filemtime( $pills_file ) : $ver;

        wp_enqueue_style( 'mu-ai-translate-inline', $uri . '/css/components/ai-translate-inline.css', [], $pills_ver );
        wp_enqueue_style( 'mu-admin-hero-banners', $uri . '/css/admin-hero-banners.css', [], $css_ver );
        wp_enqueue_script( 'mu-admin-hero-banners-js', $uri . '/js/admin-hero-banners.js', [ 'jquery' ], $js_ver, true );

        if ( wp_script_is( 'mu-admin-hero-banners-js', 'enqueued' ) ) {
            wp_localize_script( 'mu-admin-hero-banners-js', 'muHeroBannersData', [
                'mediaTitle'  => __( 'Seleccionar imagen del banner', 'mu' ),
                'mediaButton' => __( 'Usar esta imagen', 'mu' ),
                'removed'     => __( 'Eliminado (se descarta al guardar)', 'mu' ),
                // Servicio de traducción con IA (botón "Traducir con IA").
                'aiUrl'       => admin_url( 'admin-ajax.php' ),
                'aiNonce'     => wp_create_nonce( 'mu_ai_nonce' ),
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

        // Lo almacenado se lee ANTES de limpiar: además del [Hash Gate] de más abajo,
        // sirve para saber si la traducción de un idioma cambió en este guardado
        // (marca de origen, ver el bucle).
        $before = get_option( MU_HERO_BANNERS_OPTION, null );

        // Banners guardados indexados por id (slug), para comparar traducciones.
        $before_by_id = [];
        foreach ( (array) $before as $old ) {
            if ( is_array( $old ) && '' !== (string) ( $old['id'] ?? '' ) ) {
                $before_by_id[ (string) $old['id'] ] = $old;
            }
        }

        foreach ( $raw as $b ) {
            // Saltar filas marcadas como eliminadas o totalmente vacías.
            if ( ! empty( $b['_delete'] ) ) {
                continue;
            }
            if ( empty( $b['titulo'] ) && empty( $b['imagen'] ) && empty( $b['cta_url'] ) ) {
                continue;
            }

            $clean = mu_hero_banners_sanitize_one( $b );

            // [Marca de origen] Con qué español se tradujo cada idioma, para poder
            // avisar "traducción desactualizada" cuando el banner base cambie.
            $previous = ( '' !== (string) $clean['id'] && isset( $before_by_id[ (string) $clean['id'] ] ) )
                ? $before_by_id[ (string) $clean['id'] ]
                : null;

            $cleaned[] = mu_hero_banner_with_origin_marks( $clean, $previous );
        }

        // [Hash Gate] Comparar lo nuevo contra lo almacenado con maybe_serialize()
        // y actuar solo si cambió realmente. Evita invalidar el object cache y
        // purgar la caché de página en guardados sin cambios reales.
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
                <?php esc_html_e( 'Gestioná los banners del slider principal de la home. Las fechas usan formato DDMMAAAA: si dejás ambas vacías, el banner se muestra siempre. Cada banner se muestra sólo en los países tildados. En los sitios en otro idioma se usa la versión traducida de cada campo cuando está cargada; los campos vacíos (o iguales al español) muestran el español. El punto en la píldora marca los idiomas que ya tienen contenido propio.', 'mu' ); ?>
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
            // Globales (no cambian por idioma)
            'id'              => '',
            'inicio'          => '',
            'fin'             => '',
            'show_free_badge' => false,
            'paises'          => [ 'AR' ],
            'idioma_activo'   => 'es',
            'traducir_pt'     => false,
            'traducir_en'     => false,
            // Campos base (español)
            'titulo'                 => '',
            'descripcion'            => '',
            'eyebrow'                => '',
            'imagen'                 => '',
            'cta_texto'              => '',
            'cta_url'                => '',
            'cta_secundario_texto'   => '',
            'cta_secundario_url'     => '',
            'free_badge_text'        => '',
            'free_badge_url'         => '',
            // Traducciones (portugués)
            'titulo_pt'              => '',
            'descripcion_pt'         => '',
            'eyebrow_pt'             => '',
            'imagen_pt'              => '',
            'cta_texto_pt'           => '',
            'cta_url_pt'             => '',
            'cta_secundario_texto_pt'=> '',
            'cta_secundario_url_pt'  => '',
            'free_badge_text_pt'     => '',
            'free_badge_url_pt'      => '',
            // Traducciones (inglés)
            'titulo_en'              => '',
            'descripcion_en'         => '',
            'eyebrow_en'             => '',
            'imagen_en'              => '',
            'cta_texto_en'           => '',
            'cta_url_en'             => '',
            'cta_secundario_texto_en'=> '',
            'cta_secundario_url_en'  => '',
            'free_badge_text_en'     => '',
            'free_badge_url_en'      => '',
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
                $idioma_activo_lc = $b['idioma_activo'];              // es | en | pt (píldora activa)
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
                <?php
                // Píldoras de idioma (mismo componente que el editor de reseñas):
                // eligen qué bloque se edita y el punto marca, automáticamente, los
                // idiomas con contenido propio. Ya no hay casilla "Traducir".
                ?>
                <span class="mu-lang-pills" role="group" aria-label="<?php esc_attr_e( 'Idioma del banner', 'mu' ); ?>">
                    <?php
                    foreach ( [ 'es' => 'Español', 'pt' => 'Portugués (Brasil)', 'en' => 'Inglés' ] as $code => $label ) :
                        $tiene = 'es' !== $code && mu_hero_banner_lang_has_translation( $b, $code );
                        ?>
                        <button type="button"
                                class="mu-lang-pill<?php echo $idioma_activo_lc === $code ? ' is-active' : ''; ?>"
                                data-lang="<?php echo esc_attr( $code ); ?>"
                                data-has-translation="<?php echo $tiene ? '1' : '0'; ?>"
                                title="<?php echo esc_attr( $label ); ?>"><?php echo esc_html( strtoupper( $code ) ); ?></button>
                    <?php endforeach; ?>
                </span>
                <input type="hidden" name="<?php echo esc_attr( $name ); ?>[idioma_activo]" value="<?php echo esc_attr( $idioma_activo_lc ); ?>" class="mu-hero-banner-idioma-valor">

                <?php // Traduce del español al idioma activo; nunca guarda. ?>
                <span class="mu-hero-banner-ai">
                    <span class="mu-ai-inline-status" data-role="ai-status"></span>
                    <button type="button"
                            class="mu-ai-inline-btn mu-hero-banner-ai-btn"
                            data-role="ai-btn"
                            data-fields="<?php echo esc_attr( implode( ',', mu_hero_banner_lang_fields() ) ); ?>"
                            data-source-lang="es"
                            title="<?php esc_attr_e( 'Traduce los textos del banner del español al idioma activo', 'mu' ); ?>"
                            <?php echo 'es' === $idioma_activo_lc ? 'disabled' : ''; ?>>
                        <?php esc_html_e( 'Traducir con IA', 'mu' ); ?>
                    </button>
                </span>

                <button type="button" class="button-link-delete mu-hero-banner-row__remove">
                    <?php esc_html_e( 'Eliminar', 'mu' ); ?>
                </button>
                <input type="hidden" name="<?php echo esc_attr( $name ); ?>[_delete]" value="0" class="mu-hero-banner-row__delete-flag">
            </div>

            <div class="mu-hero-banner-row__grid">
                <?php
                // Los 10 campos que existen por idioma (base ES + sufijos _pt / _en).
                // Se declaran una sola vez para que el bloque de español y las
                // réplicas de cada idioma sean idénticos.
                $lang_fields = [
                    'titulo'               => [ 'type' => 'text',     'label' => __( 'Título (HTML permitido — <span class="mu-highlight">…</span>)', 'mu' ) ],
                    'descripcion'          => [ 'type' => 'textarea', 'label' => __( 'Descripción', 'mu' ) ],
                    'eyebrow'              => [ 'type' => 'text',     'label' => __( 'Eyebrow', 'mu' ) ],
                    'imagen'               => [ 'type' => 'image',    'label' => __( 'Imagen de fondo', 'mu' ) ],
                    'cta_texto'            => [ 'type' => 'text',     'label' => __( 'CTA principal — texto', 'mu' ) ],
                    'cta_url'              => [ 'type' => 'text',     'label' => __( 'CTA principal — URL o ruta relativa', 'mu' ) ],
                    'cta_secundario_texto' => [ 'type' => 'text',     'label' => __( 'CTA secundario — texto', 'mu' ) ],
                    'cta_secundario_url'   => [ 'type' => 'text',     'label' => __( 'CTA secundario — URL o ruta relativa', 'mu' ) ],
                    'free_badge_text'      => [ 'type' => 'text',     'label' => __( 'Badge — Texto (HTML permitido)', 'mu' ) ],
                    'free_badge_url'       => [ 'type' => 'text',     'label' => __( 'Badge — URL o ruta relativa', 'mu' ) ],
                ];
                $full_fields = [ 'titulo', 'descripcion', 'eyebrow', 'imagen', 'free_badge_text', 'free_badge_url' ];

                // Renderiza un campo del bloque de idioma. El sufijo es '' para
                // español, '_pt' o '_en' para las traducciones.
                $render_lang_field = function ( $field, $suffix ) use ( $name, $b, $lang_fields, $full_fields ) {
                    $key   = $field . $suffix;
                    $value = isset( $b[ $key ] ) ? $b[ $key ] : '';
                    $input = $name . '[' . $key . ']';
                    $full  = in_array( $field, $full_fields, true ) ? ' mu-hero-banner-row__full' : '';
                    $type  = $lang_fields[ $field ]['type'];
                    $label = $lang_fields[ $field ]['label'];
                    $data  = ' data-field="' . esc_attr( $field ) . '"';

                    if ( 'image' === $type ) {
                        ?>
                        <p class="mu-hero-banner-row__full mu-hero-banner-row__image">
                            <label><strong><?php echo esc_html( $label ); ?></strong></label>
                            <span class="mu-hero-banner-row__image-controls">
                                <input type="text" name="<?php echo esc_attr( $input ); ?>" value="<?php echo esc_attr( $value ); ?>"<?php echo $data; ?> class="large-text mu-hero-banner-row__image-url" placeholder="/wp-content/uploads/...">
                                <button type="button" class="button mu-hero-banner-row__image-pick"><?php esc_html_e( 'Elegir imagen', 'mu' ); ?></button>
                            </span>
                            <img src="<?php echo esc_url( $value ); ?>" alt="" class="mu-hero-banner-row__image-preview"<?php echo $value ? '' : ' style="display:none;"'; ?>>
                        </p>
                        <?php
                        return;
                    }

                    if ( 'textarea' === $type ) {
                        ?>
                        <p class="<?php echo esc_attr( trim( $full ) ); ?>">
                            <label><strong><?php echo esc_html( $label ); ?></strong></label>
                            <textarea name="<?php echo esc_attr( $input ); ?>" rows="2"<?php echo $data; ?> class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
                        </p>
                        <?php
                        return;
                    }
                    ?>
                    <p class="<?php echo esc_attr( trim( $full ) ); ?>">
                        <label><strong><?php echo esc_html( $label ); ?></strong></label>
                        <input type="text" name="<?php echo esc_attr( $input ); ?>" value="<?php echo esc_attr( $value ); ?>"<?php echo $data; ?> class="large-text">
                    </p>
                    <?php
                };
                ?>

                <!-- Bloque de español (idioma base) -->
                <div class="mu-hero-banner-lang-block" data-lang="es" style="display:<?php echo 'es' === $idioma_activo_lc ? 'block' : 'none'; ?>;">
                    <div class="mu-hero-banner-fields-grid">
                        <?php foreach ( array_keys( $lang_fields ) as $field ) { $render_lang_field( $field, '' ); } ?>
                    </div>
                </div>

                <!-- Réplicas por idioma: siempre editables (no hay casilla que las habilite).
                     Un campo vacío o igual al español no se guarda, y el front muestra
                     el español: el punto de la píldora sale de ese contenido guardado. -->
                <?php foreach ( [ 'pt', 'en' ] as $lang ) : ?>
                <div class="mu-hero-banner-lang-block" data-lang="<?php echo esc_attr( $lang ); ?>" style="display:<?php echo $idioma_activo_lc === $lang ? 'block' : 'none'; ?>;">
                    <?php if ( mu_hero_banner_lang_is_stale( $b, $lang ) ) : ?>
                        <p class="mu-lang-hint mu-hero-banner-stale" role="status">
                            <?php esc_html_e( 'El texto en español cambió después de esta traducción: conviene revisarla (o volver a traducirla con IA).', 'mu' ); ?>
                        </p>
                    <?php endif; ?>
                    <div class="mu-hero-banner-fields-grid">
                        <?php foreach ( array_keys( $lang_fields ) as $field ) { $render_lang_field( $field, '_' . $lang ); } ?>
                    </div>
                    <p class="mu-lang-hint">
                        <?php esc_html_e( 'Lo que dejes vacío (o igual al español) muestra el español en el sitio.', 'mu' ); ?>
                    </p>
                </div>
                <?php endforeach; ?>

                <!-- Bloque global: no cambia por idioma -->
                <div class="mu-hero-banner-globals-block">
                    <p class="mu-hero-banner-globals-title"><strong><?php esc_html_e( 'Ajustes generales (iguales en todos los idiomas)', 'mu' ); ?></strong></p>
                    <div class="mu-hero-banner-fields-grid">
                        <p>
                            <label><strong><?php esc_html_e( 'ID (slug)', 'mu' ); ?></strong></label>
                            <input type="text" name="<?php echo esc_attr( $name ); ?>[id]" value="<?php echo esc_attr( $b['id'] ); ?>" class="regular-text">
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
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( $name ); ?>[show_free_badge]" value="1" <?php checked( ! empty( $b['show_free_badge'] ) ); ?>>
                                <strong><?php esc_html_e( 'Mostrar badge promo', 'mu' ); ?></strong>
                            </label>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
