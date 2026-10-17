<?php
/**
 * Module: Hero Banners Manager
 * Description: Plugin admin para gestionar los banners del Hero de la portada.
 *              Submenu bajo WooCommerce → Marketing (toplevel_page_woocommerce-marketing).
 *              Storage: wp_options 'mu_hero_banners' (array de promos).
 *              Fechas: 'inicio'/'fin' son las GENERALES; cada país puede tener su
 *              propia ventana en 'fechas[PAIS]' (campo vacío o igual a la general =
 *              hereda). Ver mu_hero_banner_pais_fechas().
 *              Vigencia anual: con 'anual' (general) o 'fechas[PAIS][anual]' se ignoran
 *              los años guardados (sólo cuentan día y mes) y la ventana puede cruzar el
 *              fin de año (20/12 → 10/01). Ver mu_hero_banner_ventana_anual().
 *              Render: mu_hero_slide_render() es la FUENTE ÚNICA del HTML del slide (la
 *              comparten el front y el lienzo del panel — feature 013).
 *              Cache: transient por país 'mu_hero_banners_active_{PAIS}' (TTL 12h,
 *              acortado al próximo borde de fecha), invalidado al guardar vía
 *              mu_hero_banners_purge_cache(). La home de los hosts se purga al guardar y
 *              en cada borde de fecha (mu_hero_banner_programar_purga_borde()).
 * Version: 1.3.0
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

        // Fechas generales (DDMMAAAA): las heredan los países sin ventana propia.
        // [M3] Se valida la fecha REAL (checkdate), no sólo 8 dígitos: '99012026' o
        // '31022026' se descartan en lugar de resolverse por rollover a otro día.
        $inicio_global = mu_hero_banner_fecha_valida( $b['inicio'] ?? '' ) ? (string) $b['inicio'] : '';
        $fin_global    = mu_hero_banner_fecha_valida( $b['fin'] ?? '' )    ? (string) $b['fin']    : '';

        // [Sobrescritura dispersa] Ventana propia por país: se guarda sólo el campo que
        // difiere de la general del MISMO campo (lo igual se persiste vacío = hereda).
        // Misma convención que las traducciones, así que el estado ("tiene fechas
        // propias") se deduce del contenido y nunca se desincroniza de la regla de
        // guardado.
        //
        // [M7] Se conservan las fechas de TODOS los países válidos, estén tildados o no:
        // el front filtra primero por 'paises', así que no se muestran, y destildar y
        // volver a tildar un país no pierde la ventana cargada.
        $fechas     = [];
        $raw_fechas = isset( $b['fechas'] ) && is_array( $b['fechas'] ) ? $b['fechas'] : [];

        // [012] Vigencia anual: '1'/'0' explícitos en la general (raíz de la herencia) y
        // por país sólo cuando DIFIERE de la general (ausente = hereda), la misma
        // convención que inicio/fin (ver mu_hero_banner_anual_efectivo()).
        $anual_global = ! empty( $b['anual'] ) ? '1' : '0';

        foreach ( $valid_paises as $code ) {
            $own = isset( $raw_fechas[ $code ] ) && is_array( $raw_fechas[ $code ] ) ? $raw_fechas[ $code ] : [];

            $ini = mu_hero_banner_fecha_valida( $own['inicio'] ?? '' ) ? (string) $own['inicio'] : '';
            $fin = mu_hero_banner_fecha_valida( $own['fin'] ?? '' )    ? (string) $own['fin']    : '';

            $anual_pais = array_key_exists( 'anual', $own )
                ? ( ! empty( $own['anual'] ) ? '1' : '0' )
                : '';   // ausente = sin decisión propia (hereda); no se guarda

            if ( $ini === $inicio_global ) { $ini = ''; }
            if ( $fin === $fin_global )    { $fin = ''; }
            if ( $anual_pais === $anual_global ) { $anual_pais = ''; }

            if ( '' === $ini && '' === $fin && '' === $anual_pais ) { continue; }

            $fechas[ $code ] = [ 'inicio' => $ini, 'fin' => $fin ];

            if ( '' !== $anual_pais ) {
                $fechas[ $code ]['anual'] = $anual_pais;
            }
        }

        return array_merge(
            [
                'id'              => sanitize_title( $b['id'] ?? '' ),
                'inicio'          => $inicio_global,
                'fin'             => $fin_global,
                'anual'           => $anual_global,
                'show_free_badge' => ! empty( $b['show_free_badge'] ),
                'paises'          => $paises,
                'fechas'          => $fechas,
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

if ( ! function_exists( 'mu_hero_banner_fecha_valida' ) ) {
    /**
     * ¿El valor es una fecha DDMMAAAA REAL?
     *
     * El saneado sólo exigía 8 dígitos, y DateTime::createFromFormat() no rechaza días
     * ni meses fuera de rango: '99012026' se interpretaba como 2026-04-09 y '31132026'
     * como 2027-01-31 (el guard `! $dt_start` del código anterior era inefectivo). Con
     * checkdate() un 31/02 o un día 99 se descartan.
     *
     * Función pura: la verifica el arnés de la feature 010.
     *
     * @param mixed $ddmmaaaa Valor a validar.
     * @return bool
     */
    function mu_hero_banner_fecha_valida( $ddmmaaaa ) {
        if ( ! preg_match( '/^(\d{2})(\d{2})(\d{4})$/', (string) $ddmmaaaa, $m ) ) {
            return false;
        }

        return checkdate( (int) $m[2], (int) $m[1], (int) $m[3] );   // mes, día, año
    }
}

if ( ! function_exists( 'mu_hero_banner_pais_tz' ) ) {
    /**
     * Huso horario del país de destino (el subdominio), no el del servidor.
     *
     * Antes, "01/06" se resolvía con el huso por defecto de PHP: el mismo borde caía a
     * horas distintas en cada subdominio (Madrid y Buenos Aires están a 5 h en junio) y
     * en Brasil podía caer el 31/05. Fuente: la clave 'tz' de muyu_get_countries_data();
     * si falta o está mal escrita se cae a wp_timezone() (el huso del sitio), nunca al
     * huso por defecto de PHP.
     *
     * @param string $pais Código ISO del país.
     * @return DateTimeZone
     */
    function mu_hero_banner_pais_tz( $pais ) {
        $countries = function_exists( 'muyu_get_countries_data' ) ? muyu_get_countries_data() : [];
        $tz        = (string) ( $countries[ (string) $pais ]['tz'] ?? '' );

        if ( '' !== $tz ) {
            try {
                return new DateTimeZone( $tz );
            } catch ( Exception $e ) {
                // Huso mal escrito en la configuración del país: se usa el del sitio.
            }
        }

        return function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
    }
}

if ( ! function_exists( 'mu_hero_banner_pais_fechas' ) ) {
    /**
     * Ventana de fechas efectiva de un país para un banner.
     *
     * [Sobrescritura dispersa] El país puede definir su propia ventana en
     * 'fechas[PAIS]'; el campo que esté vacío (o igual a la general, que es cuando no
     * se persiste) hereda la fecha general. Si no hay ninguna de las dos, el banner no
     * tiene límite de fecha y se muestra siempre en ese país.
     *
     * Función pura (sin WordPress): la verifica el arnés de la feature 010.
     *
     * @param array  $promo Banner.
     * @param string $pais  Código ISO del país (AR, BR...).
     * @return array{inicio:string,fin:string}
     */
    function mu_hero_banner_pais_fechas( $promo, $pais ) {
        $promo = is_array( $promo ) ? $promo : [];
        $code  = (string) $pais;

        $own = ( isset( $promo['fechas'] ) && is_array( $promo['fechas'] ) && isset( $promo['fechas'][ $code ] ) && is_array( $promo['fechas'][ $code ] ) )
            ? $promo['fechas'][ $code ]
            : [];

        $inicio = (string) ( $own['inicio'] ?? '' );
        $fin    = (string) ( $own['fin'] ?? '' );

        if ( '' === $inicio ) { $inicio = (string) ( $promo['inicio'] ?? '' ); }
        if ( '' === $fin )    { $fin    = (string) ( $promo['fin'] ?? '' ); }

        return [ 'inicio' => $inicio, 'fin' => $fin ];
    }
}

if ( ! function_exists( 'mu_hero_banner_mmdd' ) ) {
    /**
     * Día y mes ('MMDD') de una fecha guardada, o '' si la fecha no es válida.
     *
     * Base de la vigencia anual (feature 012): con el flag 'anual' los años guardados
     * se ignoran y sólo cuentan día y mes. Función pura: la verifica el arnés de 012.
     *
     * @param mixed $ddmmaaaa Fecha DDMMAAAA.
     * @return string 'MMDD' o ''.
     */
    function mu_hero_banner_mmdd( $ddmmaaaa ) {
        if ( ! mu_hero_banner_fecha_valida( $ddmmaaaa ) ) {
            return '';
        }

        $ddmmaaaa = (string) $ddmmaaaa;

        return substr( $ddmmaaaa, 2, 2 ) . substr( $ddmmaaaa, 0, 2 );
    }
}

if ( ! function_exists( 'mu_hero_banner_anual_efectivo' ) ) {
    /**
     * ¿La ventana de este ámbito se repite todos los años?
     *
     * [Sobrescritura dispersa] El país puede declarar su propio 'anual' en
     * 'fechas[PAIS]'; como sólo se persiste cuando DIFIERE de la general, la ausencia
     * de la clave significa "hereda el flag general". Función pura: la verifica el
     * arnés de la feature 012.
     *
     * @param array  $promo Banner.
     * @param string $pais  Código ISO del país ('' = fechas generales).
     * @return bool
     */
    function mu_hero_banner_anual_efectivo( $promo, $pais ) {
        $promo = is_array( $promo ) ? $promo : [];
        $code  = (string) $pais;

        $own = [];
        if ( isset( $promo['fechas'] ) && is_array( $promo['fechas'] )
            && isset( $promo['fechas'][ $code ] ) && is_array( $promo['fechas'][ $code ] ) ) {
            $own = $promo['fechas'][ $code ];
        }

        if ( array_key_exists( 'anual', $own ) ) {
            return ! empty( $own['anual'] );
        }

        return ! empty( $promo['anual'] );
    }
}

if ( ! function_exists( 'mu_hero_banner_instante_dia' ) ) {
    /**
     * Instante de un día/mes ('MMDD') en un año y huso dados.
     *
     * El día se acota al último del mes cuando no existe en ese año: `checkdate()` ya
     * impide guardar `29022026`, así que el 29/02 guardado siempre tiene un año bisiesto
     * real, pero al repetir la ventana cada año el 29 puede no existir. Se cae al 28 en
     * lugar de prometer un día que no llega (o de correrse a marzo).
     *
     * Función pura: la verifica el arnés de la feature 012.
     *
     * @param string       $mmdd          'MMDD'.
     * @param int          $anio          Año de la instancia.
     * @param DateTimeZone $tz            Huso del país de destino.
     * @param bool         $fin_inclusive true = último segundo del día (fin del banner).
     * @return int|null Timestamp, o null si el MMDD no sirve.
     */
    function mu_hero_banner_instante_dia( $mmdd, $anio, $tz, $fin_inclusive = false ) {
        $mmdd = (string) $mmdd;

        if ( 4 !== strlen( $mmdd ) ) {
            return null;
        }

        $mes = (int) substr( $mmdd, 0, 2 );
        $dia = (int) substr( $mmdd, 2, 2 );

        if ( $mes < 1 || $mes > 12 || $dia < 1 ) {
            return null;
        }

        $primero = new DateTimeImmutable( sprintf( '%04d-%02d-01 00:00:00', (int) $anio, $mes ), $tz );
        $ultimo  = (int) $primero->modify( 'last day of this month' )->format( 'j' );
        $dia     = min( $dia, $ultimo );

        $dt = $primero->setDate( (int) $anio, $mes, $dia )->setTime( 0, 0, 0 );

        if ( $fin_inclusive ) {
            // Fin inclusivo = "inicio del día siguiente − 1 s" (DST-safe, ver M2).
            return $dt->modify( '+1 day' )->setTime( 0, 0, 0 )->getTimestamp() - 1;
        }

        return $dt->getTimestamp();
    }
}

if ( ! function_exists( 'mu_hero_banner_ventana_anual' ) ) {
    /**
     * Instantes de la instancia ANUAL de la ventana que corresponde a un instante dado.
     *
     * Con la vigencia anual se ignoran los años guardados: sólo cuentan día y mes. La
     * ventana puede CRUZAR el fin de año (20/12 → 10/01), así que se arman las
     * instancias de los tres años alrededor (anterior, actual y siguiente) y se elige la
     * que contiene $now. Si ninguna lo contiene se devuelve la FUTURA más cercana con
     * activo = false: es lo que necesita mu_hero_banner_proximo_borde() para saber
     * cuándo vuelve a entrar (sin esto, un banner anual apagado hoy nunca se encendería
     * solo).
     *
     * Función pura: la verifica el arnés de la feature 012.
     *
     * @param string       $inicio DDMMAAAA de inicio.
     * @param string       $fin    DDMMAAAA de fin (inclusive).
     * @param DateTimeZone $tz     Huso del país de destino.
     * @param int          $now    Timestamp de referencia.
     * @return array{0:int,1:int,2:bool}|null [ inicio, fin_inclusive, activo ] o null.
     */
    function mu_hero_banner_ventana_anual( $inicio, $fin, $tz, $now ) {
        $mmdd_ini = mu_hero_banner_mmdd( $inicio );
        $mmdd_fin = mu_hero_banner_mmdd( $fin );

        if ( '' === $mmdd_ini || '' === $mmdd_fin ) {
            return null;
        }

        $now  = (int) $now;
        $anio = (int) ( new DateTimeImmutable( '@' . $now ) )->setTimezone( $tz )->format( 'Y' );

        // 'inicio' posterior a 'fin' = la ventana cruza el fin de año (MMDD comparados
        // como texto: '1220' > '0110'). Entonces el fin cae en el año siguiente.
        $cruza = ( $mmdd_ini > $mmdd_fin );

        $instancias = [];

        foreach ( [ $anio - 1, $anio, $anio + 1 ] as $y ) {
            $start = mu_hero_banner_instante_dia( $mmdd_ini, $y, $tz );
            $end   = mu_hero_banner_instante_dia( $mmdd_fin, $cruza ? $y + 1 : $y, $tz, true );

            if ( null === $start || null === $end ) {
                continue;
            }

            $instancias[] = [ $start, $end ];
        }

        // La instancia que contiene el instante pedido (inclusive en los dos bordes).
        foreach ( $instancias as $i ) {
            if ( $now >= $i[0] && $now <= $i[1] ) {
                return [ $i[0], $i[1], true ];
            }
        }

        // Ninguna: la próxima futura (así proximo_borde() apunta al año que viene).
        $futuras = array_values( array_filter( $instancias, function ( $i ) use ( $now ) {
            return $i[0] > $now;
        } ) );

        usort( $futuras, function ( $a, $b ) {
            return $a[0] <=> $b[0];
        } );

        if ( $futuras ) {
            return [ $futuras[0][0], $futuras[0][1], false ];
        }

        if ( $instancias ) {
            return [ $instancias[0][0], $instancias[0][1], false ];
        }

        return null;
    }
}

if ( ! function_exists( 'mu_hero_banner_ventana_instantes' ) ) {
    /**
     * Instantes de la ventana efectiva de un país, resueltos en SU huso horario.
     *
     * @param array    $promo Banner.
     * @param string   $pais  Código ISO del país.
     * @param int|null $now   Timestamp de referencia (null = time()). Con vigencia anual
     *                       decide QUÉ instancia del ciclo se devuelve.
     * @return array{0:int,1:int}|null [ inicio, fin_inclusive ] o null si no hay límite
     *                                 (sin fechas, incompletas o inválidas).
     */
    function mu_hero_banner_ventana_instantes( $promo, $pais, $now = null ) {
        $fechas = mu_hero_banner_pais_fechas( $promo, $pais );

        // Sin las dos fechas el banner no tiene límite; con una fecha inválida guardada
        // (dato viejo) tampoco: se prefiere mostrar de más y marcarlo en el panel antes
        // que ocultar una promo viva por un error de tipeo.
        if ( '' === $fechas['inicio'] || '' === $fechas['fin'] ) {
            return null;
        }
        if ( ! mu_hero_banner_fecha_valida( $fechas['inicio'] ) || ! mu_hero_banner_fecha_valida( $fechas['fin'] ) ) {
            return null;
        }

        $tz  = mu_hero_banner_pais_tz( $pais );
        $now = ( null === $now ) ? time() : (int) $now;

        // [012] Vigencia anual: se ignoran los años guardados y la instancia la resuelve
        // mu_hero_banner_ventana_anual(). Si el instante pedido ya quedó fuera del ciclo,
        // devuelve la próxima instancia (arranca después de $now, así is_active da false
        // y proximo_borde apunta al año que viene).
        if ( mu_hero_banner_anual_efectivo( $promo, $pais ) ) {
            $anual = mu_hero_banner_ventana_anual( $fechas['inicio'], $fechas['fin'], $tz, $now );

            return ( null === $anual ) ? null : [ $anual[0], $anual[1] ];
        }

        $dt_ini = DateTime::createFromFormat( 'dmY', $fechas['inicio'], $tz );
        $dt_fin = DateTime::createFromFormat( 'dmY', $fechas['fin'], $tz );

        if ( ! $dt_ini || ! $dt_fin ) {
            return null;
        }

        $start = $dt_ini->setTime( 0, 0, 0 )->getTimestamp();

        // Fin inclusivo = "inicio del día siguiente − 1 s". No se usa setTime(23,59,59)
        // porque con un huso propio esa hora de reloj puede no existir (cambios de
        // horario de verano) o no ser el cierre real del día.
        $end = $dt_fin->modify( '+1 day' )->setTime( 0, 0, 0 )->getTimestamp() - 1;

        return [ $start, $end ];
    }
}

if ( ! function_exists( 'mu_hero_banner_is_active_for_pais' ) ) {
    /**
     * ¿El banner está dentro de su ventana de fechas para ese país?
     *
     * Sin ventana (sin fechas, incompletas o inválidas) se muestra siempre. Con
     * ventana, es inclusiva: desde el 00:00 del inicio (hora del país) hasta el último
     * segundo del fin. Un rango invertido (inicio posterior al fin) no se muestra nunca.
     *
     * Función pura (recibe el timestamp de referencia): la verifica el arnés de la
     * feature 010.
     *
     * @param array    $promo Banner.
     * @param string   $pais  Código ISO del país.
     * @param int|null $now   Timestamp de referencia (null = time()).
     * @return bool
     */
    function mu_hero_banner_is_active_for_pais( $promo, $pais, $now = null ) {
        $now = ( null === $now ) ? time() : (int) $now;

        // La ventana se resuelve con el MISMO instante de referencia: con vigencia anual
        // la instancia del ciclo depende de él (ver mu_hero_banner_ventana_anual()).
        $ventana = mu_hero_banner_ventana_instantes( $promo, $pais, $now );

        if ( null === $ventana ) {
            return true;
        }

        return ( $now >= $ventana[0] && $now <= $ventana[1] );
    }
}

if ( ! function_exists( 'mu_hero_banner_proximo_borde' ) ) {
    /**
     * Próximo instante en que cambia el escenario de este banner para ese país.
     *
     * Sirve para que el transient del país expire justo cuando el hero cambia (entrada
     * o salida de la ventana) en lugar de esperar las 12 h fijas.
     *
     * Función pura: la verifica el arnés de la feature 010.
     *
     * @param array    $promo Banner.
     * @param string   $pais  Código ISO del país.
     * @param int|null $now   Timestamp de referencia (null = time()).
     * @return int Instante del próximo cambio, o 0 si no hay ninguno.
     */
    function mu_hero_banner_proximo_borde( $promo, $pais, $now = null ) {
        $now = ( null === $now ) ? time() : (int) $now;

        // La ventana se resuelve con el MISMO instante de referencia: con una ventana
        // anual, la instancia devuelta (y por lo tanto sus bordes) depende de él.
        $ventana = mu_hero_banner_ventana_instantes( $promo, $pais, $now );

        if ( null === $ventana ) {
            return 0;
        }

        // El fin es inclusivo: el banner sale del aire un segundo después.
        $futuros = array_filter( [ $ventana[0], $ventana[1] + 1 ], function ( $borde ) use ( $now ) {
            return $borde > $now;
        } );

        return $futuros ? min( $futuros ) : 0;
    }
}

if ( ! function_exists( 'mu_hero_banner_avisos' ) ) {
    /**
     * Avisos de fecha de un banner: datos inválidos guardados y rangos invertidos.
     *
     * Existe para que el problema se vea EN LA LISTA, sin abrir el panel: una fecha
     * inválida se ignora (el banner se muestra siempre) y un rango invertido hace que
     * ese país no lo vea nunca. Las dos cosas se descubrían recién mirando el sitio.
     *
     * Función pura: la verifica el arnés de la feature 010.
     *
     * @param array $promo Banner.
     * @return array{invalidas:string[],invertidas:string[],anuales_incompletas:string[]}
     *              Ámbitos afectados; '' = fechas generales, o el código del país.
     */
    function mu_hero_banner_avisos( $promo ) {
        $promo  = is_array( $promo ) ? $promo : [];
        $out    = [ 'invalidas' => [], 'invertidas' => [], 'anuales_incompletas' => [] ];
        $ambitos = [ '' => [
            'inicio' => (string) ( $promo['inicio'] ?? '' ),
            'fin'    => (string) ( $promo['fin'] ?? '' ),
            'anual'  => mu_hero_banner_anual_efectivo( $promo, '' ),
        ] ];

        foreach ( (array) ( $promo['fechas'] ?? [] ) as $code => $fechas ) {
            if ( ! is_array( $fechas ) ) {
                continue;
            }

            $ambitos[ (string) $code ] = [
                'inicio' => (string) ( $fechas['inicio'] ?? '' ),
                'fin'    => (string) ( $fechas['fin'] ?? '' ),
                'anual'  => mu_hero_banner_anual_efectivo( $promo, (string) $code ),
            ];
        }

        foreach ( $ambitos as $ambito => $v ) {
            if ( ( '' !== $v['inicio'] && ! mu_hero_banner_fecha_valida( $v['inicio'] ) )
                || ( '' !== $v['fin'] && ! mu_hero_banner_fecha_valida( $v['fin'] ) ) ) {
                $out['invalidas'][] = (string) $ambito;
                continue;
            }

            // [012] Anual con una sola fecha: no hay ciclo que repetir (se muestra siempre,
            // igual que sin el flag) → se avisa en vez de fallar en silencio.
            if ( $v['anual'] && ( '' === $v['inicio'] || '' === $v['fin'] ) ) {
                $out['anuales_incompletas'][] = (string) $ambito;
                continue;
            }

            if ( '' === $v['inicio'] || '' === $v['fin'] ) {
                continue;
            }

            // Orden de calendario sin husos: AAAA-MM-DD comparado como texto.
            $orden = function ( $ddmmaaaa ) {
                return substr( $ddmmaaaa, 4, 4 ) . substr( $ddmmaaaa, 2, 2 ) . substr( $ddmmaaaa, 0, 2 );
            };

            // [012] Con vigencia anual, un inicio posterior al fin NO es un rango invertido:
            // es la ventana que cruza el fin de año (20/12 → 10/01), el caso más común.
            if ( ! $v['anual'] && $orden( $v['inicio'] ) > $orden( $v['fin'] ) ) {
                $out['invertidas'][] = (string) $ambito;
            }
        }

        return $out;
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

        $all     = mu_get_hero_banners_raw();
        $active  = [];
        $now     = time();
        $proximo = 0;   // próximo instante en que cambia el escenario de este país

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

            // [M5-A] Borde de este banner para este país (entra o sale del aire). Se
            // mira TODOS los banners del país, incluidos los que hoy no se muestran:
            // el inicio de uno inactivo también cambia el hero.
            $borde = mu_hero_banner_proximo_borde( $p, $pais_actual, $now );
            if ( $borde > 0 && ( 0 === $proximo || $borde < $proximo ) ) {
                $proximo = $borde;
            }

            // Fecha: ventana EFECTIVA del país (propia o heredada) en su huso horario;
            // funciones puras y testeables (arnés de la feature 010).
            if ( mu_hero_banner_is_active_for_pais( $p, $pais_actual, $now ) ) {
                $active[] = $p;
            }
        }

        // TTL: 12 h, pero se acorta hasta el próximo borde para que el hero cambie cuando
        // corresponde y no hasta 12 h después. Piso de 60 s para no martillar la caché
        // cuando el borde es inminente.
        $ttl = 12 * HOUR_IN_SECONDS;
        if ( $proximo > 0 ) {
            $ttl = max( 60, min( $ttl, $proximo - $now + 1 ) );
        }

        set_transient( $transient_key, $active, $ttl );
        return $active;
    }
}

if ( ! function_exists( 'mu_hero_banner_proximo_borde_global' ) ) {
    /**
     * Próximo instante en que cambia el hero en CUALQUIER país del sitio.
     *
     * Es el borde que importa para la caché de páginas: cuando llega, la home de algún
     * host deja de ser válida. Se miran todos los banners en todos los países donde se
     * muestran (el cron corre en un request cualquiera, no en el del visitante).
     *
     * @return int Timestamp del próximo borde, o 0 si no hay ninguno.
     */
    function mu_hero_banner_proximo_borde_global() {
        $all     = mu_get_hero_banners_raw();
        $now     = time();
        $validos = function_exists( 'muyu_get_countries_data' ) ? array_keys( muyu_get_countries_data() ) : [ 'AR' ];
        $proximo = 0;

        foreach ( $all as $p ) {
            $paises = isset( $p['paises'] ) && is_array( $p['paises'] ) ? $p['paises'] : [ 'AR' ];

            foreach ( $paises as $pais ) {
                if ( ! in_array( $pais, $validos, true ) ) {
                    continue;
                }

                $borde = mu_hero_banner_proximo_borde( $p, (string) $pais, $now );

                if ( $borde > 0 && ( 0 === $proximo || $borde < $proximo ) ) {
                    $proximo = $borde;
                }
            }
        }

        return $proximo;
    }
}

if ( ! function_exists( 'mu_hero_banner_programar_purga_borde' ) ) {
    /**
     * Programa UN evento único en el próximo borde de fecha.
     *
     * No reprograma si ya hay uno pendiente (guard `wp_next_scheduled`, mismo patrón que
     * inc/navigation-chips.php). Cuando los datos cambian se pasa por
     * mu_hero_banner_reprogramar_purga_borde(), que limpia el evento viejo antes.
     */
    function mu_hero_banner_programar_purga_borde() {
        if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_single_event' ) ) {
            return;
        }

        if ( wp_next_scheduled( 'mu_hero_banner_purge_borde' ) ) {
            return;
        }

        $borde = mu_hero_banner_proximo_borde_global();

        if ( $borde > time() ) {
            wp_schedule_single_event( $borde, 'mu_hero_banner_purge_borde' );
        }
    }
}

if ( ! function_exists( 'mu_hero_banner_reprogramar_purga_borde' ) ) {
    /**
     * Descarta el evento pendiente y lo vuelve a calcular con los datos nuevos.
     *
     * Se dispara al guardar: si el borde guardado era "el 01/06" y ahora es "mañana", el
     * evento viejo quedaría agendado en una fecha que ya no corresponde.
     */
    function mu_hero_banner_reprogramar_purga_borde() {
        if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
            wp_clear_scheduled_hook( 'mu_hero_banner_purge_borde' );
        }

        mu_hero_banner_programar_purga_borde();
    }
}

if ( ! function_exists( 'mu_hero_banner_purge_borde_ejecutar' ) ) {
    /**
     * Purga la home de los hosts del sitio al cruzar un borde de fecha.
     *
     * Purga por URL cuando el plugin expone el hook (`litespeed_purge_url`, que respeta el
     * aislamiento por host) y cae a `litespeed_purge_all` si no está registrado — sólo en
     * un borde real, nunca por request: el criterio del proyecto prohíbe las purgas
     * incondicionales. Deja rastro `[MU-HERO-BORDE]` para poder auditar el disparo (§7).
     * Al terminar reprograma el próximo borde.
     *
     * @param bool $programar_siguiente false = no reprogramar (lo usa el arnés).
     */
    function mu_hero_banner_purge_borde_ejecutar( $programar_siguiente = true ) {
        $countries = function_exists( 'muyu_get_countries_data' ) ? muyu_get_countries_data() : [];
        $purgados  = 0;

        if ( function_exists( 'has_action' ) && has_action( 'litespeed_purge_url' ) ) {
            foreach ( $countries as $c ) {
                $host = (string) ( $c['host'] ?? '' );

                if ( '' === $host ) {
                    continue;
                }

                do_action( 'litespeed_purge_url', 'https://' . $host . '/' );
                $purgados++;
            }
        } else {
            do_action( 'litespeed_purge_all' );
            $purgados = count( $countries );
        }

        // Los transients por país se invalidan también: el HTML se regenera con el
        // escenario nuevo (si el borde era de entrada, el transient no tenía el banner).
        mu_hero_banners_purge_cache();

        error_log( sprintf(
            '[MU-HERO-BORDE] purga de la home por borde de fechas host(s)=%d',
            $purgados
        ) );

        if ( $programar_siguiente ) {
            mu_hero_banner_programar_purga_borde();
        }
    }
}

// Invalidación de cache cuando la opción cambia (todas las variantes por país).
add_action( 'update_option_' . MU_HERO_BANNERS_OPTION, 'mu_hero_banners_purge_cache' );
add_action( 'add_option_' . MU_HERO_BANNERS_OPTION, 'mu_hero_banners_purge_cache' );

// [012] Purga de la home en el BORDE de fecha. Con una ventana anual el cambio llega sin
// que nadie guarde, y la caché de páginas de LiteSpeed dura 24 h (medido: max-age=86400),
// así que sin esto la home seguiría mostrando el hero viejo hasta un día después. La purga
// es condicionada por construcción (un evento único por borde real, nunca un barrido
// periódico): ver mu_hero_banner_purge_borde_ejecutar().
add_action( 'mu_hero_banner_purge_borde', 'mu_hero_banner_purge_borde_ejecutar' );
add_action( 'update_option_' . MU_HERO_BANNERS_OPTION, 'mu_hero_banner_reprogramar_purga_borde' );
add_action( 'add_option_' . MU_HERO_BANNERS_OPTION, 'mu_hero_banner_reprogramar_purga_borde' );

// ==========================================
// 1.b RENDER DEL SLIDE — FUENTE ÚNICA (FRONT + PANEL)
// ==========================================

if ( ! function_exists( 'mu_hero_slide_render' ) ) {
    /**
     * Render de UN slide del hero.
     *
     * FUENTE ÚNICA del marcado del banner: lo consume el front (mu_hero_section(), en
     * ui.php, que sólo aporta el wrapper, el slider y los dots) y el lienzo del panel
     * (feature 013), para que el editor muestre EXACTAMENTE el HTML del sitio.
     *
     * Vive acá y no en ui.php por el orden de carga de functions.php: hero-banners (379)
     * se carga ANTES que ui (381) y el panel renderiza la fila desde este módulo. El
     * módulo icons (360) va primero, así que mu_get_icon() está disponible.
     *
     * El marcado, las clases, el orden de los atributos y el escape son los que emitía
     * ui.php inline (features 001-012): lo verifica el arnés de 013 comparando contra el
     * fixture capturado de producción ANTES de esta extracción.
     *
     * @param array  $promo Banner (mismos campos que consume el front).
     * @param string $lang  Idioma ya resuelto ('es'|'en'|'pt').
     * @param array  $args  index (int, sale en data-slide-index) y first (bool: clase
     *                      active, loading eager y fetchpriority high).
     * @return string HTML del slide.
     */
    function mu_hero_slide_render( $promo, $lang, $args = [] ) {
        $promo = is_array( $promo ) ? $promo : [];
        $args  = wp_parse_args( $args, [ 'index' => 0, 'first' => true ] );

        $idx   = (int) $args['index'];
        $first = ! empty( $args['first'] );

        // Campo del banner en el idioma pedido: el traducido cuando existe y el español
        // en cualquier otro caso (sobrescritura dispersa, ver mu_hero_banner_field()).
        $field = function ( $base ) use ( $promo, $lang ) {
            return function_exists( 'mu_hero_banner_field' )
                ? mu_hero_banner_field( $promo, $base, $lang )
                : (string) ( $promo[ $base ] ?? '' );
        };

        // --- DEFAULTS PARA BADGE (los únicos del sistema) ---
        $badge_defaults = [
            'url'  => '/tienda/digital/?min_price=0&max_price=0',
            'text' => '<strong>¡Gratis!</strong><span>Envíos digitales</span>',
        ];

        $titulo       = $field( 'titulo' );
        $show_badge   = ! empty( $promo['show_free_badge'] ) && true === $promo['show_free_badge'];
        $imagen       = $field( 'imagen' );
        $eyebrow      = $field( 'eyebrow' );
        $cta_url      = $field( 'cta_url' );
        $cta_sec_text = $field( 'cta_secundario_texto' );
        $cta_sec_url  = $field( 'cta_secundario_url' );
        $badge_url    = $field( 'free_badge_url' );
        $badge_text   = $field( 'free_badge_text' );

        if ( '' === $badge_url ) {
            $badge_url = $badge_defaults['url'];
        }
        if ( '' === $badge_text ) {
            $badge_text = $badge_defaults['text'];
        }

        ob_start();
        ?>
        <div class="mu-hero-promo-slide<?php echo $first ? ' active' : ''; ?>"
             data-slide-index="<?php echo esc_attr( $idx ); ?>">

            <div class="mu-hero-promo-bg">
                <img src="<?php echo esc_url( $imagen ); ?>"
                     alt="<?php echo esc_attr( strip_tags( $titulo ) ); ?>"
                     width="1280" height="580"
                     loading="<?php echo $first ? 'eager' : 'lazy'; ?>"
                     <?php echo $first ? 'fetchpriority="high"' : ''; ?>
                     decoding="async">
            </div>

            <div class="mu-hero-promo-content">
                <?php if ( '' !== $eyebrow ) : ?>
                    <div class="mu-hero-promo-eyebrow">
                        <?php echo esc_html( $eyebrow ); ?>
                    </div>
                <?php endif; ?>

                <h1 class="mu-hero-promo-title"><?php echo wp_kses_post( $titulo ); ?></h1>
                <p class="mu-hero-promo-desc"><?php echo esc_html( $field( 'descripcion' ) ); ?></p>

                <div class="mu-hero-promo-actions">
                    <a href="<?php echo esc_url( $cta_url ); ?>" class="mu-btn mu-btn-primary">
                        <span><?php echo esc_html( $field( 'cta_texto' ) ); ?></span>
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
        <?php
        return (string) ob_get_clean();
    }
}

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
                // Avisos del panel de fechas (formato y rango invertido).
                'fechasMalas'   => __( 'Inicio es posterior al fin: el banner no se verá en este país.', 'mu' ),
                'fechasFormato' => __( 'Usá el formato DDMMAAAA (8 dígitos) en Inicio y Fin.', 'mu' ),
                // [012] Aviso de la repetición anual incompleta (una sola fecha).
                'fechasAnualIncompleta' => __( 'Con «Se repite cada año» hacen falta las dos fechas: con una sola, el banner se muestra siempre.', 'mu' ),
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
                <?php esc_html_e( 'Gestioná los banners del slider principal de la home. Las fechas usan formato DDMMAAAA: si dejás ambas vacías, el banner se muestra siempre. Cada banner se muestra sólo en los países tildados. Las fechas generales (píldora "Global") valen para los países sin ventana propia: elegí la píldora de un país para darle fechas distintas y el punto marcará que tiene ventana propia. En los sitios en otro idioma se usa la versión traducida de cada campo cuando está cargada; los campos vacíos (o iguales al español) muestran el español. El punto en la píldora de idioma marca los idiomas que ya tienen contenido propio. Con «Se repite cada año» en un panel de fechas, esa ventana vuelve todos los años: se ignoran los años guardados y sólo cuentan el día y el mes.', 'mu' ); ?>
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
            'fechas'          => [],
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
                <?php
                // Las píldoras de país se mudaron al bloque de ajustes generales, donde
                // además eligen DE QUÉ país se editan las fechas (píldora = pestaña).
                // Acá queda sólo la selección de países activos (checkbox del dropdown).
                ?>

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

                <?php
                // Variantes (una versión por persona): los botones numerados los llena el
                // JS cuando la respuesta del servicio trae más de una versión. Mismo
                // componente que el editor de reseñas.
                ?>
                <span class="mu-ai-variants" data-role="ai-variants" role="group" aria-label="<?php esc_attr_e( 'Variantes de la traducción', 'mu' ); ?>"></span>

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
                        <?php esc_html_e( 'Traducir', 'mu' ); ?>
                    </button>
                </span>

                <button type="button" class="button-link-delete mu-hero-banner-row__remove">
                    <?php esc_html_e( 'Eliminar', 'mu' ); ?>
                </button>
                <input type="hidden" name="<?php echo esc_attr( $name ); ?>[_delete]" value="0" class="mu-hero-banner-row__delete-flag">
            </div>

            <?php
            // [M4] Avisos de fecha visibles en la lista, sin abrir el panel: una fecha
            // inválida guardada se ignora (el banner se muestra siempre) y un rango
            // invertido hace que ese ámbito no lo vea nunca.
            $avisos = mu_hero_banner_avisos( $b );
            $rotular = function ( $ambito ) {
                return '' === $ambito ? __( 'Fechas generales', 'mu' ) : $ambito;
            };
            ?>
            <?php if ( $avisos['invalidas'] || $avisos['invertidas'] || $avisos['anuales_incompletas'] ) : ?>
            <p class="mu-hero-banner-row__alert" role="status">
                <?php if ( $avisos['invalidas'] ) : ?>
                    <span>
                        <?php
                        printf(
                            /* translators: %s: ámbito (país o fechas generales). */
                            esc_html__( '%s: hay una fecha inválida guardada; se ignora hasta corregirla.', 'mu' ),
                            esc_html( implode( ', ', array_map( $rotular, $avisos['invalidas'] ) ) )
                        );
                        ?>
                    </span>
                <?php endif; ?>
                <?php if ( $avisos['invertidas'] ) : ?>
                    <span>
                        <?php
                        printf(
                            /* translators: %s: ámbito (país o fechas generales). */
                            esc_html__( '%s: el inicio es posterior al fin; ese ámbito no ve el banner.', 'mu' ),
                            esc_html( implode( ', ', array_map( $rotular, $avisos['invertidas'] ) ) )
                        );
                        ?>
                    </span>
                <?php endif; ?>
                <?php if ( $avisos['anuales_incompletas'] ) : ?>
                    <span>
                        <?php
                        printf(
                            /* translators: %s: ámbito (país o fechas generales). */
                            esc_html__( '%s: la repetición anual quedó con una sola fecha, así que el banner se muestra siempre; completá Inicio y Fin.', 'mu' ),
                            esc_html( implode( ', ', array_map( $rotular, $avisos['anuales_incompletas'] ) ) )
                        );
                        ?>
                    </span>
                <?php endif; ?>
            </p>
            <?php endif; ?>

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
                    <?php
                    // Píldoras de país: eligen DE QUÉ país se editan las fechas (misma
                    // mecánica que las píldoras de idioma). "Global" son las fechas
                    // generales, que heredan los países sin ventana propia; el punto
                    // marca los países con fechas propias. Los países ACTIVOS se eligen
                    // con el botón "Países" de la cabecera: acá se ven los tildados.
                    ?>
                    <p class="mu-hero-banner-globals-title">
                        <strong><?php esc_html_e( 'Ajustes generales (iguales en todos los idiomas)', 'mu' ); ?></strong>
                        <span class="mu-hero-banner-paises-pills" role="group" aria-label="<?php esc_attr_e( 'País cuyas fechas se editan', 'mu' ); ?>">
                            <button type="button"
                                    class="mu-hero-banner-pill is-selectable is-active"
                                    data-pais=""
                                    data-has-fechas="0"
                                    title="<?php esc_attr_e( 'Fechas generales (las usan los países sin ventana propia)', 'mu' ); ?>"><?php esc_html_e( 'Global', 'mu' ); ?></button>
                            <?php
                            foreach ( $countries_data as $code => $c ) :
                                $pais_activo = in_array( $code, $b['paises'], true );
                                $ini_propio  = (string) ( $b['fechas'][ $code ]['inicio'] ?? '' );
                                $fin_propio  = (string) ( $b['fechas'][ $code ]['fin'] ?? '' );
                                ?>
                                <button type="button"
                                        class="mu-hero-banner-pill is-selectable"
                                        data-pais="<?php echo esc_attr( $code ); ?>"
                                        data-has-fechas="<?php echo ( '' !== $ini_propio || '' !== $fin_propio ) ? '1' : '0'; ?>"
                                        title="<?php echo esc_attr( $c['name'] ); ?>"
                                        <?php echo $pais_activo ? '' : 'style="display:none;"'; ?>><?php echo esc_html( $code ); ?></button>
                            <?php endforeach; ?>
                        </span>
                    </p>

                    <?php
                    // Campo de fecha (DDMMAAAA). El nombre completo se arma afuera: las
                    // generales usan [inicio]/[fin] y cada país [fechas][PAIS][inicio].
                    //
                    // SIN `pattern`: los paneles se ocultan al cambiar de píldora y un
                    // campo oculto con formato inválido bloquea el envío con un
                    // "not focusable" que no se ve. El formato lo avisa el JS (aviso del
                    // panel) y lo sanea el servidor, igual que antes.
                    $render_fecha = function ( $field, $label, $input, $valor, $placeholder ) {
                        ?>
                        <span class="mu-hero-banner-fechas-field">
                            <label><strong><?php echo esc_html( $label ); ?></strong></label>
                            <input type="text" name="<?php echo esc_attr( $input ); ?>" value="<?php echo esc_attr( $valor ); ?>" data-fecha="<?php echo esc_attr( $field ); ?>" maxlength="8" inputmode="numeric" placeholder="<?php echo esc_attr( $placeholder ); ?>" class="regular-text">
                        </span>
                        <?php
                    };
                    ?>
                    <div class="mu-hero-banner-fields-grid">
                        <p>
                            <label><strong><?php esc_html_e( 'ID (slug)', 'mu' ); ?></strong></label>
                            <input type="text" name="<?php echo esc_attr( $name ); ?>[id]" value="<?php echo esc_attr( $b['id'] ); ?>" class="regular-text">
                        </p>
                        <!-- Fechas generales: las heredan los países sin ventana propia -->
                        <p class="mu-hero-banner-fechas-block mu-hero-banner-row__full" data-pais="">
                            <span class="mu-hero-banner-fechas-grid">
                                <?php
                                $render_fecha( 'inicio', __( 'Inicio (DDMMAAAA)', 'mu' ), $name . '[inicio]', $b['inicio'], '01012026' );
                                $render_fecha( 'fin', __( 'Fin (DDMMAAAA)', 'mu' ), $name . '[fin]', $b['fin'], '31122026' );
                                ?>
                            </span>
                            <label class="mu-hero-banner-anual-field">
                                <input type="hidden" name="<?php echo esc_attr( $name ); ?>[anual]" value="0">
                                <input type="checkbox" name="<?php echo esc_attr( $name ); ?>[anual]" value="1" data-role="anual" <?php checked( mu_hero_banner_anual_efectivo( $b, '' ) ); ?>>
                                <strong><?php esc_html_e( 'Se repite cada año', 'mu' ); ?></strong>
                                <span class="mu-lang-hint"><?php esc_html_e( 'Con esto se ignoran los años de las fechas y sólo cuentan el día y el mes. Para promos que vuelven todos los años: 20/12 → 10/01 cruza el fin de año y sigue funcionando.', 'mu' ); ?></span>
                            </label>
                            <span class="mu-lang-hint"><?php esc_html_e( 'Estas fechas valen para los países sin ventana propia. Si dejás ambas vacías, el banner se muestra siempre en ellos.', 'mu' ); ?></span>
                            <span class="mu-lang-hint"><?php esc_html_e( 'Se interpretan como medianoche LOCAL de cada país: 01/06 es el 01/06 a las 00:00 en el huso de ese país, no en el del servidor.', 'mu' ); ?></span>
                            <span class="mu-lang-hint mu-hero-banner-fechas-warning" data-role="fechas-warning" hidden></span>
                        </p>
                        <p>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( $name ); ?>[show_free_badge]" value="1" <?php checked( ! empty( $b['show_free_badge'] ) ); ?>>
                                <strong><?php esc_html_e( 'Mostrar badge promo', 'mu' ); ?></strong>
                            </label>
                        </p>

                        <?php // Ventana propia de cada país (oculta hasta que se elija su píldora) ?>
                        <?php foreach ( $countries_data as $code => $c ) : ?>
                        <p class="mu-hero-banner-fechas-block mu-hero-banner-row__full" data-pais="<?php echo esc_attr( $code ); ?>" style="display:none;">
                            <span class="mu-hero-banner-fechas-grid">
                                <?php
                                $render_fecha( 'inicio', __( 'Inicio (DDMMAAAA)', 'mu' ), $name . '[fechas][' . $code . '][inicio]', $b['fechas'][ $code ]['inicio'] ?? '', $b['inicio'] );
                                $render_fecha( 'fin', __( 'Fin (DDMMAAAA)', 'mu' ), $name . '[fechas][' . $code . '][fin]', $b['fechas'][ $code ]['fin'] ?? '', $b['fin'] );
                                ?>
                            </span>
                            <label class="mu-hero-banner-anual-field">
                                <input type="hidden" name="<?php echo esc_attr( $name ); ?>[fechas][<?php echo esc_attr( $code ); ?>][anual]" value="0">
                                <input type="checkbox" name="<?php echo esc_attr( $name ); ?>[fechas][<?php echo esc_attr( $code ); ?>][anual]" value="1" data-role="anual" <?php checked( mu_hero_banner_anual_efectivo( $b, $code ) ); ?>>
                                <strong><?php esc_html_e( 'Se repite cada año', 'mu' ); ?></strong>
                                <span class="mu-lang-hint"><?php esc_html_e( 'Tildado en la fila de Global, heredar este país también repite; destildalo acá para que este país use la ventana una sola vez.', 'mu' ); ?></span>
                            </label>
                            <span class="mu-lang-hint">
                                <?php
                                printf(
                                    /* translators: %s: nombre del país. */
                                    esc_html__( '%s: lo que dejes vacío (o igual a las fechas generales) usa las fechas generales; si dejás ambas vacías, el banner se muestra siempre en este país.', 'mu' ),
                                    esc_html( $c['name'] )
                                );
                                ?>
                            </span>
                            <span class="mu-lang-hint">
                                <?php
                                // [M6] Ventana efectiva con lo GUARDADO, en el huso del país: la
                                // pregunta "¿medianoche de dónde?" queda respondida a la vista.
                                // [012] Con la repetición anual, el año que se muestra es el de la
                                // instancia que corresponde a hoy (o al próximo si ya pasó).
                                $tz_pais   = mu_hero_banner_pais_tz( $code );
                                $ventana_e = mu_hero_banner_ventana_instantes( $b, $code );
                                $nota      = mu_hero_banner_anual_efectivo( $b, $code )
                                    ? ' ' . __( 'Se repite cada año: el año de las fechas se ignora.', 'mu' )
                                    : '';

                                if ( null === $ventana_e ) {
                                    echo esc_html( sprintf(
                                        /* translators: %s: huso horario del país. */
                                        __( 'Sin límite de fecha con lo guardado: se muestra siempre (huso del país: %s).', 'mu' ),
                                        $tz_pais->getName()
                                    ) . $nota );
                                } else {
                                    echo esc_html( sprintf(
                                        /* translators: 1: inicio, 2: fin, 3: huso horario del país. */
                                        __( 'Con lo guardado se ve del %1$s al %2$s, en el huso del país (%3$s).', 'mu' ),
                                        ( new DateTimeImmutable( '@' . $ventana_e[0] ) )->setTimezone( $tz_pais )->format( 'd/m/Y H:i' ),
                                        ( new DateTimeImmutable( '@' . $ventana_e[1] ) )->setTimezone( $tz_pais )->format( 'd/m/Y H:i' ),
                                        $tz_pais->getName()
                                    ) . $nota );
                                }
                                ?>
                            </span>
                            <span class="mu-lang-hint mu-hero-banner-fechas-warning" data-role="fechas-warning" hidden></span>
                        </p>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
