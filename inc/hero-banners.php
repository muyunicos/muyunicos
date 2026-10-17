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

            // [015] Se recuerda PARA CUÁNDO se esperaba el evento: es lo que le permite a la
            // revisión diaria distinguir «todavía no corrió el cron» de «se perdió».
            update_option( 'mu_hero_banner_borde_esperado', $borde, false );
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

        // [015] El borde esperado viejo ya no vale: si no se limpia, la revisión diaria lo
        // tomaría como «borde atrasado» y purgaría de más.
        update_option( 'mu_hero_banner_borde_esperado', 0, false );

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

        // [015] El modo queda registrado: 'url' (purgas la home de cada host) o 'all'
        // (respaldo: purga todo el sitio). Se muestra en el panel, así el mantenedor sabe si
        // está purgando de más sin tener que leer logs.
        $por_url = function_exists( 'has_action' ) && has_action( 'litespeed_purge_url' );
        $modo    = $por_url ? 'url' : 'all';

        if ( $por_url ) {
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

        // [015] Estado visible en el panel (patrón «Última importación automática» de reseñas).
        update_option( 'mu_hero_banner_ultima_purga', [
            'ts'    => time(),
            'hosts' => $purgados,
            'modo'  => $modo,
        ], false );

        mu_hero_banner_log_borde( sprintf(
            '[MU-HERO-BORDE] purga de la home por borde de fechas host(s)=%d modo=%s',
            $purgados,
            $modo
        ) );

        error_log( sprintf(
            '[MU-HERO-BORDE] purga de la home por borde de fechas host(s)=%d modo=%s',
            $purgados,
            $modo
        ) );

        if ( $programar_siguiente ) {
            mu_hero_banner_programar_purga_borde();
        }
    }
}

if ( ! function_exists( 'mu_hero_banner_log_borde' ) ) {
    /**
     * Rastro del borde en archivo plano (feature 015).
     *
     * Por qué no alcanza `error_log()`: el cron del hosting ejecuta wp-cron.php por CLI y
     * descarta stdout+stderr (`> /dev/null 2>&1`), y en CLI `error_log()` escribe a stderr →
     * se pierde salvo que `WP_DEBUG_LOG` (o el `error_log` del php.ini) apunten a un archivo.
     * Se escribe un archivo propio en el mismo directorio que usan los contadores de
     * mu_bot404_record_hit() (`wp-content/uploads/mu-logs/`), con LOCK_EX.
     *
     * @param string $mensaje Línea a registrar.
     */
    function mu_hero_banner_log_borde( $mensaje ) {
        if ( ! function_exists( 'wp_upload_dir' ) ) {
            return;
        }

        $upload = wp_upload_dir();
        $base   = (string) ( $upload['basedir'] ?? '' );

        if ( '' === $base ) {
            return;
        }

        $dir = trailingslashit( $base ) . 'mu-logs';

        if ( ! is_dir( $dir ) && function_exists( 'wp_mkdir_p' ) ) {
            wp_mkdir_p( $dir );
        }

        @file_put_contents(
            $dir . '/hero-borde.log',
            gmdate( 'Y-m-d H:i:s' ) . ' ' . $mensaje . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
}

if ( ! function_exists( 'mu_hero_banner_revisar_bordes' ) ) {
    /**
     * Red de seguridad diaria del borde de fecha (feature 015).
     *
     * El evento único cubre el caso normal: el cron del hosting corre wp-cron.php cada 5
     * minutos, así que la purga ocurre ≤5 min después del borde (aceptable con caché de
     * páginas de 24 h). Esta revisión cubre lo que ese evento NO puede: que se haya perdido
     * (un guardado que limpió la agenda, una importación de la base, el cron caído) o que los
     * datos hayan cambiado sin agendar.
     *
     * Es IDEMPOTENTE y CONDICIONAL:
     *   1. si el borde que se esperaba ya pasó y NO hay evento pendiente para él, purga una vez;
     *   2. rearma el evento único si falta o si apunta a un borde que ya no es el próximo.
     * En el caso normal (el evento corrió y reprogramó) no hace absolutamente nada.
     *
     * @param bool $programar_siguiente false = no reprogramar (lo usa el arnés).
     */
    function mu_hero_banner_revisar_bordes( $programar_siguiente = true ) {
        $ahora    = time();
        $esperado = (int) get_option( 'mu_hero_banner_borde_esperado', 0 );
        $agendado = function_exists( 'wp_next_scheduled' ) ? (int) wp_next_scheduled( 'mu_hero_banner_purge_borde' ) : 0;

        // 1) Borde esperado en el pasado y sin evento pendiente para él: se perdió. (Si el
        //    evento todavía está agendado para ese instante, sólo es que el cron no llegó
        //    —hasta 5 minutos— y no hay nada que hacer.)
        if ( $esperado > 0 && $esperado <= $ahora && $agendado !== $esperado ) {
            mu_hero_banner_purge_borde_ejecutar( false );
            mu_hero_banner_log_borde( 'revisión diaria: borde atrasado; se purgó como red de seguridad' );
        }

        if ( ! $programar_siguiente ) {
            return;
        }

        // 2) Rearmar el evento único si falta o si quedó en un borde viejo.
        $proximo = mu_hero_banner_proximo_borde_global();

        if ( $proximo > $ahora && $agendado !== $proximo ) {
            if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
                wp_clear_scheduled_hook( 'mu_hero_banner_purge_borde' );
            }

            mu_hero_banner_programar_purga_borde();
            mu_hero_banner_log_borde( sprintf( 'revisión diaria: evento rearmado para %s', gmdate( 'Y-m-d H:i:s', $proximo ) ) );
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

// [015] Red de seguridad: revisión DIARIA del borde (auto-sanación + rearme del evento único).
// Se agenda una sola vez: wp_next_scheduled lee la opción 'cron' (autoloaded), así que el
// guard es barato. Con el cron del hosting (wp-cron.php cada 5 min) la revisión corre a diario
// y, en el caso normal, no hace absolutamente nada.
add_action( 'mu_hero_banner_revisar_bordes_hook', 'mu_hero_banner_revisar_bordes' );
add_action( 'init', function () {
    if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( 'mu_hero_banner_revisar_bordes_hook' ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'mu_hero_banner_revisar_bordes_hook' );
    }
} );

// ==========================================
// 1.b RENDER DEL SLIDE — FUENTE ÚNICA (FRONT + PANEL)
// ==========================================

if ( ! function_exists( 'mu_hero_badge_defaults' ) ) {
    /**
     * Defaults del badge promo (los únicos del sistema).
     *
     * Fuente única: los usa el render del slide y el lienzo del panel (que los expone al
     * JS para replicar lo que se vería con el badge vacío).
     *
     * @return array{url:string,text:string}
     */
    function mu_hero_badge_defaults() {
        return [
            'url'  => '/tienda/digital/?min_price=0&max_price=0',
            'text' => '<strong>¡Gratis!</strong><span>Envíos digitales</span>',
        ];
    }
}

if ( ! function_exists( 'mu_hero_banner_excepciones' ) ) {
    /**
     * Países con ventana PROPIA (excepciones a las fechas generales).
     *
     * El saneado guarda 'fechas[PAIS]' SÓLO cuando difiere de la general (sobrescritura
     * dispersa), así que acá alcanza con leer las claves: es un espejo del guardado, no una
     * regla nueva. Se descartan los códigos que ya no son países válidos y las entradas
     * vacías (datos viejos), y se devuelven en el orden canónico de los países.
     *
     * Existe para que el panel muestre Global + EXCEPCIONES en lugar de una tabla con todos
     * los ámbitos: con 11 países y la misma ventana para todos, la tabla era puro ruido.
     *
     * Función pura: la verifica el arnés de la feature 013.
     *
     * @param array $promo Banner.
     * @return string[] Códigos con ventana propia.
     */
    function mu_hero_banner_excepciones( $promo ) {
        $promo  = is_array( $promo ) ? $promo : [];
        $fechas = ( isset( $promo['fechas'] ) && is_array( $promo['fechas'] ) ) ? $promo['fechas'] : [];
        $validos = function_exists( 'muyu_get_countries_data' ) ? array_keys( muyu_get_countries_data() ) : [];
        $out     = [];

        foreach ( $fechas as $code => $v ) {
            $code = (string) $code;

            if ( ! is_array( $v ) ) {
                continue;
            }

            if ( $validos && ! in_array( $code, $validos, true ) ) {
                continue;
            }

            // Entrada sin ningún dato propio: no es una excepción (dato viejo o corrupto).
            $ini = (string) ( $v['inicio'] ?? '' );
            $fin = (string) ( $v['fin'] ?? '' );
            $anu = (string) ( $v['anual'] ?? '' );

            if ( '' === $ini && '' === $fin && '' === $anu ) {
                continue;
            }

            $out[] = $code;
        }

        // Orden canónico (el de muyu_get_countries_data), para que la UI sea estable.
        if ( $validos ) {
            $out = array_values( array_intersect( $validos, $out ) );
        }

        return $out;
    }
}

if ( ! function_exists( 'mu_hero_banner_pais_es_excepcion' ) ) {
    /**
     * ¿Ese país tiene decisión propia (ventana distinta de la general)?
     *
     * Función pura: la verifica el arnés de la feature 013.
     *
     * @param array  $promo Banner.
     * @param string $pais  Código ISO.
     * @return bool
     */
    function mu_hero_banner_pais_es_excepcion( $promo, $pais ) {
        return in_array( (string) $pais, mu_hero_banner_excepciones( $promo ), true );
    }
}

if ( ! function_exists( 'mu_hero_banner_estado' ) ) {
    /**
     * Estado de un banner para el resumen de la fila: ¿se ve ahora, dónde y cuándo cambia?
     *
     * Junta en una frase lo que hoy sólo se descubre abriendo cada panel de fecha. Función
     * pura (recibe el instante): la verifica el arnés de la feature 013.
     *
     * @param array    $promo Banner.
     * @param int|null $now   Timestamp de referencia (null = time()).
     * @return array{clase:string,texto:string} Clase CSS (is-activo|is-programado|is-inactivo)
     *                                          y frase para el resumen.
     */
    function mu_hero_banner_estado( $promo, $now = null ) {
        $now    = ( null === $now ) ? time() : (int) $now;
        $promo  = is_array( $promo ) ? $promo : [];
        $paises = isset( $promo['paises'] ) && is_array( $promo['paises'] ) ? array_values( $promo['paises'] ) : [ 'AR' ];

        // [014] Sin ningún país tildado el banner no se ve en NINGÚN lado. Es un dato, no una
        // fecha, y hasta ahora caía en "No se ve en ningún país" (que suena a ventana vencida
        // y manda a revisar fechas). Merece decirse con todas las letras.
        if ( empty( $paises ) ) {
            return [
                'clase' => 'is-sin-paises',
                'texto' => __( 'No está tildado en ningún país: no se verá en el sitio', 'mu' ),
            ];
        }

        // Primer país del banner: da el huso para escribir la fecha del próximo cambio.
        $tz = mu_hero_banner_pais_tz( (string) ( $paises[0] ?? 'AR' ) );

        $activos = [];
        $proximo = 0;

        foreach ( $paises as $code ) {
            if ( mu_hero_banner_is_active_for_pais( $promo, (string) $code, $now ) ) {
                $activos[] = (string) $code;
            }

            $borde = mu_hero_banner_proximo_borde( $promo, (string) $code, $now );

            if ( $borde > 0 && ( 0 === $proximo || $borde < $proximo ) ) {
                $proximo = $borde;
            }
        }

        $cuando = $proximo > 0
            ? ( new DateTimeImmutable( '@' . $proximo ) )->setTimezone( $tz )->format( 'd/m/Y H:i' )
            : '';

        if ( ! empty( $activos ) ) {
            $texto = sprintf(
                /* translators: %s: países separados por coma. */
                __( 'Se ve en %s', 'mu' ),
                implode( ', ', $activos )
            );

            $fuera = array_values( array_diff( $paises, $activos ) );
            if ( $fuera ) {
                $texto .= sprintf(
                    /* translators: %s: países separados por coma. */
                    __( ' · fuera de ventana: %s', 'mu' ),
                    implode( ', ', $fuera )
                );
            }

            if ( '' !== $cuando ) {
                $texto .= sprintf(
                    /* translators: %s: fecha y hora del próximo cambio. */
                    __( ' · próximo cambio: %s', 'mu' ),
                    $cuando
                );
            }

            return [ 'clase' => 'is-activo', 'texto' => $texto ];
        }

        if ( '' !== $cuando ) {
            return [
                'clase' => 'is-programado',
                'texto' => sprintf(
                    /* translators: %s: fecha y hora en que entra. */
                    __( 'Entra el %s', 'mu' ),
                    $cuando
                ),
            ];
        }

        // Sin países o sin ventana futura: no hay nada que esperar (el "siempre" nunca
        // llega acá porque, sin ventana, el banner está activo).
        return [ 'clase' => 'is-inactivo', 'texto' => __( 'No se ve en ningún país', 'mu' ) ];
    }
}

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
     * @param array  $args  index (int, sale en data-slide-index), first (bool: clase
     *                      active, loading eager y fetchpriority high) y mu (bool: marca
     *                      los nodos con data-mu para el lienzo del editor).
     * @return string HTML del slide.
     */
    function mu_hero_slide_render( $promo, $lang, $args = [] ) {
        $promo = is_array( $promo ) ? $promo : [];
        $args  = wp_parse_args( $args, [ 'index' => 0, 'first' => true, 'mu' => false ] );

        $idx   = (int) $args['index'];
        $first = ! empty( $args['first'] );

        // [Lienzo del editor] Con 'mu' los nodos salen marcados (data-mu="campo") para que
        // el JS del panel los mantenga sincronizados con el formulario, y se emiten
        // SIEMPRE el eyebrow, el CTA secundario y el badge (aunque hoy no se muestren):
        // si no, no habría dónde editar su texto. El JS replica las condiciones del front
        // (oculta lo que no se mostraría). El front NUNCA pide 'mu'.
        $mu = ! empty( $args['mu'] );

        $dm = function ( $campo ) use ( $mu ) {
            // $campo es un literal del código, no un dato de usuario: no se escapa.
            return $mu ? ' data-mu="' . $campo . '"' : '';
        };

        // Campo del banner en el idioma pedido: el traducido cuando existe y el español
        // en cualquier otro caso (sobrescritura dispersa, ver mu_hero_banner_field()).
        $field = function ( $base ) use ( $promo, $lang ) {
            return function_exists( 'mu_hero_banner_field' )
                ? mu_hero_banner_field( $promo, $base, $lang )
                : (string) ( $promo[ $base ] ?? '' );
        };

        // --- DEFAULTS PARA BADGE (fuente única: mu_hero_badge_defaults) ---
        $badge_defaults = mu_hero_badge_defaults();

        $titulo       = $field( 'titulo' );
        // Con 'mu' el badge sale visible aunque la casilla esté apagada: en el lienzo hay
        // que poder editar su texto (el JS lo oculta si la casilla no está tildada).
        $show_badge   = $mu || ( ! empty( $promo['show_free_badge'] ) && true === $promo['show_free_badge'] );
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
                     decoding="async"<?php echo $dm( 'imagen' ); ?>>
            </div>

            <div class="mu-hero-promo-content">
                <?php if ( '' !== $eyebrow || $mu ) : ?>
                    <div class="mu-hero-promo-eyebrow"<?php echo $dm( 'eyebrow' ); ?>>
                        <?php echo esc_html( $eyebrow ); ?>
                    </div>
                <?php endif; ?>

                <h1 class="mu-hero-promo-title"<?php echo $dm( 'titulo' ); ?>><?php echo wp_kses_post( $titulo ); ?></h1>
                <p class="mu-hero-promo-desc"<?php echo $dm( 'descripcion' ); ?>><?php echo esc_html( $field( 'descripcion' ) ); ?></p>

                <div class="mu-hero-promo-actions">
                    <a href="<?php echo esc_url( $cta_url ); ?>" class="mu-btn mu-btn-primary"<?php echo $dm( 'cta' ); ?>>
                        <span<?php echo $dm( 'cta_texto' ); ?>><?php echo esc_html( $field( 'cta_texto' ) ); ?></span>
                        <?php echo mu_get_icon( 'arrow' ); ?>
                    </a>

                    <?php if ( ( '' !== $cta_sec_text && '' !== $cta_sec_url ) || $mu ) : ?>
                    <a href="<?php echo esc_url( $cta_sec_url ); ?>" class="mu-btn mu-btn-outline"<?php echo $dm( 'cta_secundario' ); ?>>
                        <?php echo $mu ? '<span data-mu="cta_secundario_texto">' : ''; ?><?php echo esc_html( $cta_sec_text ); ?><?php echo $mu ? '</span>' : ''; ?>
                    </a>
                    <?php endif; ?>

                    <?php if ( $show_badge ) : ?>
                    <a href="<?php echo esc_url( $badge_url ); ?>" class="mu-hero-promo-free-badge"<?php echo $dm( 'badge' ); ?>>
                        <div class="mu-hero-promo-free-text"<?php echo $dm( 'badge_text' ); ?>><?php echo wp_kses_post( $badge_text ); ?></div>
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

        // [013] El MISMO componente CSS del front: así el lienzo del editor muestra el
        // banner con sus estilos reales (y admin-hero-banners.css lo ajusta a la columna).
        $hero_css = $dir . '/css/components/hero-promo.css';
        $hero_ver = file_exists( $hero_css ) ? (string) filemtime( $hero_css ) : $ver;
        wp_enqueue_style( 'mu-hero-promo', $uri . '/css/components/hero-promo.css', [], $hero_ver );

        // La tipografía del front la aporta el tema padre (handle 'generate-google-fonts').
        // Se reusa ese handle en vez de inventar una URL: si el padre no lo registró en el
        // admin, wp_enqueue_style() no hace nada y el lienzo degrada a la fuente de
        // reemplazo (deuda declarada en MIGRATION-GUIDE §8).
        wp_enqueue_style( 'generate-google-fonts' );

        wp_enqueue_style( 'mu-ai-translate-inline', $uri . '/css/components/ai-translate-inline.css', [], $pills_ver );
        wp_enqueue_style( 'mu-admin-hero-banners', $uri . '/css/admin-hero-banners.css', [], $css_ver );
        wp_enqueue_script( 'mu-admin-hero-banners-js', $uri . '/js/admin-hero-banners.js', [ 'jquery' ], $js_ver, true );

        if ( wp_script_is( 'mu-admin-hero-banners-js', 'enqueued' ) ) {
            wp_localize_script( 'mu-admin-hero-banners-js', 'muHeroBannersData', [
                'mediaTitle'  => __( 'Seleccionar imagen del banner', 'mu' ),
                'mediaButton' => __( 'Usar esta imagen', 'mu' ),
                'removed'     => __( 'Eliminado (se descarta al guardar) · clic para deshacer', 'mu' ),
                'remove'      => __( 'Eliminar', 'mu' ),
                // Servicio de traducción con IA (botón "Traducir con IA").
                'aiUrl'       => admin_url( 'admin-ajax.php' ),
                'aiNonce'     => wp_create_nonce( 'mu_ai_nonce' ),
                // Avisos del panel de fechas (formato y rango invertido).
                'fechasMalas'   => __( 'Inicio es posterior al fin: el banner no se verá en este país.', 'mu' ),
                'fechasFormato' => __( 'Usá el formato DDMMAAAA (8 dígitos) en Inicio y Fin.', 'mu' ),
                // [012] Aviso de la repetición anual incompleta (una sola fecha).
                'fechasAnualIncompleta' => __( 'Con «Se repite cada año» hacen falta las dos fechas: con una sola, el banner se muestra siempre.', 'mu' ),
                // [013] Lienzo del editor: defaults del badge (los del front) para que la
                // vista previa muestre lo mismo cuando los campos están vacíos.
                'badgeDefaults' => mu_hero_badge_defaults(),
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

            <?php
            // [015] Estado de la purga por borde: la prueba de que el cron corrió, sin leer logs.
            // En CLI (cron del hosting) error_log() va a stderr y el `2>&1 >/dev/null` lo
            // descarta, así que este renglón es la verificación de verdad. El detalle completo
            // queda en wp-content/uploads/mu-logs/hero-borde.log.
            $purga    = get_option( 'mu_hero_banner_ultima_purga', [] );
            $esperado = (int) get_option( 'mu_hero_banner_borde_esperado', 0 );
            $agendado = ( function_exists( 'wp_next_scheduled' ) ) ? (int) wp_next_scheduled( 'mu_hero_banner_purge_borde' ) : 0;
            $atrasado = ( $esperado > 0 && $esperado <= time() && ! $agendado );
            ?>
            <p class="description mu-hero-banners-purga<?php echo $atrasado ? ' is-alerta' : ''; ?>">
                <?php
                if ( is_array( $purga ) && ! empty( $purga['ts'] ) ) {
                    printf(
                        /* translators: 1: fecha y hora de la última purga, 2: cantidad de hosts, 3: modo de purga. */
                        esc_html__( 'Última purga por borde de fecha: %1$s · %2$d host(s) · %3$s', 'mu' ),
                        esc_html( ( new DateTimeImmutable( '@' . (int) $purga['ts'] ) )->setTimezone( wp_timezone() )->format( 'd/m/Y H:i' ) ),
                        (int) ( $purga['hosts'] ?? 0 ),
                        'all' === ( $purga['modo'] ?? '' )
                            ? esc_html__( 'purga TOTAL (el plugin no expone la purga por URL)', 'mu' )
                            : esc_html__( 'por URL, host por host', 'mu' )
                    );
                } else {
                    esc_html_e( 'Todavía no hubo purgas por borde de fecha.', 'mu' );
                }

                // [016] El evento agendado: la prueba de que el cron está armado, sin esperar
                // a que pase un borde ni leer logs.
                if ( $agendado > 0 ) {
                    printf(
                        /* translators: %s: fecha y hora del próximo borde de fecha. */
                        esc_html__( ' · Próximo borde: %s', 'mu' ),
                        esc_html( ( new DateTimeImmutable( '@' . $agendado ) )->setTimezone( wp_timezone() )->format( 'd/m/Y H:i' ) )
                    );
                } else {
                    echo ' ' . esc_html__( '· Sin borde agendado (ninguna ventana de fecha tiene fin).', 'mu' );
                }

                if ( $atrasado ) {
                    echo ' ' . esc_html__( '⚠ Hay un borde pasado sin purgar: revisá el cron del hosting (MIGRATION-GUIDE §2).', 'mu' );
                }
                ?>
            </p>

            <?php if ( $updated ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Banners actualizados.', 'mu' ); ?></p></div>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field( 'mu_hero_banners_save', 'mu_hero_banners_nonce' ); ?>

                <div id="mu-hero-banners-list" class="mu-hero-banners-list">
                    <?php foreach ( $banners as $i => $b ) : ?>
                        <?php mu_hero_banners_render_row( $i, $b, count( $banners ) > 1 ); ?>
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
    function mu_hero_banners_render_row( $index, $b, $colapsada = false ) {
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

        // Datos de país e idioma activo: los necesita el resumen (chips) y el cuerpo.
        $countries_data   = function_exists( 'muyu_get_countries_data' ) ? muyu_get_countries_data() : [];
        $idioma_activo_lc = $b['idioma_activo'];              // es | en | pt (píldora activa)

        $estado_banner = mu_hero_banner_estado( $b );
        $titulo_resumen = $b['titulo'] ? wp_kses_post( $b['titulo'] ) : esc_html__( 'Nuevo banner', 'mu' );
        $mini_resumen   = (string) ( $b['imagen'] ?? '' );
        ?>
        <div class="mu-hero-banner-row<?php echo $colapsada ? ' is-folded' : ''; ?>" data-index="<?php echo esc_attr( $index ); ?>">
            <?php
            // Resumen siempre visible (features 013/014): estado, miniatura, países tildados
            // y acciones. El resto (contenido, vigencia, lienzo) vive en __body, que se pliega
            // —y nace PLEGADO cuando hay más de un banner, para que la lista se pueda leer—.
            ?>
            <div class="mu-hero-banner-row__summary">
                <button type="button" class="mu-hero-banner-row__fold" data-role="plegar" aria-expanded="<?php echo $colapsada ? 'false' : 'true'; ?>" title="<?php esc_attr_e( 'Plegar o desplegar', 'mu' ); ?>"><span aria-hidden="true">▾</span></button>

                <?php if ( '' !== $mini_resumen ) : ?>
                    <img class="mu-hero-banner-row__mini" src="<?php echo esc_url( $mini_resumen ); ?>" alt="">
                <?php endif; ?>

                <h2 class="mu-hero-banner-row__title"><?php echo $titulo_resumen; ?></h2>
                <span class="mu-hero-banner-row__estado <?php echo esc_attr( $estado_banner['clase'] ); ?>" role="status"><?php echo esc_html( $estado_banner['texto'] ); ?></span>

                <?php
                // Chips de país (feature 013): el checkbox ES el chip, así que activar un país
                // y elegir el ámbito de sus fechas son el MISMO control. El punto (data-has-fechas)
                // marca los países con ventana propia, como antes.
                ?>
                <?php
                // Chips de país (014): SÓLO los tildados, que son los que importan de un
                // vistazo. Los que quedan libres viven en el selector "+N" (mismo checkbox,
                // así el POST no cambia: cada país aparece una sola vez, en un lugar o en el
                // otro). El punto (data-has-fechas) marca los que tienen ventana propia.
                $chip_markup = function ( $code, $c, $activo ) use ( $b, $name ) {
                    $ini  = (string) ( $b['fechas'][ $code ]['inicio'] ?? '' );
                    $fin  = (string) ( $b['fechas'][ $code ]['fin'] ?? '' );
                    $anu  = (string) ( $b['fechas'][ $code ]['anual'] ?? '' );
                    $pro  = ( '' !== $ini || '' !== $fin || '' !== $anu );
                    $titu = $c['name'] . ( $pro ? __( ' · tiene ventana propia', 'mu' ) : '' );
                    ?>
                    <label class="mu-hero-banner-chip<?php echo $activo ? ' is-active' : ''; ?>"
                           data-pais="<?php echo esc_attr( $code ); ?>"
                           data-has-fechas="<?php echo $pro ? '1' : '0'; ?>"
                           title="<?php echo esc_attr( $titu ); ?>">
                        <input type="checkbox" name="<?php echo esc_attr( $name ); ?>[paises][]" value="<?php echo esc_attr( $code ); ?>"<?php checked( $activo ); ?>>
                        <span><?php echo esc_html( $code ); ?></span>
                    </label>
                    <?php
                };

                $chips_on   = [];
                $chips_off  = [];
                foreach ( $countries_data as $code => $c ) {
                    if ( in_array( $code, $b['paises'], true ) ) {
                        $chips_on[ $code ] = $c;
                    } else {
                        $chips_off[ $code ] = $c;
                    }
                }
                ?>
                <span class="mu-hero-banner-chips" role="group" aria-label="<?php esc_attr_e( 'Países donde se muestra el banner', 'mu' ); ?>">
                    <?php foreach ( $chips_on as $code => $c ) { $chip_markup( $code, $c, true ); } ?>
                    <details class="mu-hero-banner-chips-mas"<?php echo $chips_off ? '' : ' hidden'; ?>>
                        <summary title="<?php esc_attr_e( 'Agregar países', 'mu' ); ?>">+<span data-role="chips-libres"><?php echo (int) count( $chips_off ); ?></span></summary>
                        <div class="mu-hero-banner-chips-lista">
                            <?php foreach ( $chips_off as $code => $c ) { $chip_markup( $code, $c, false ); } ?>
                        </div>
                    </details>
                </span>

                <span class="mu-hero-banner-row__acciones">
                    <button type="button" class="button-link mu-hero-banner-row__accion" data-role="duplicar"><?php esc_html_e( 'Duplicar', 'mu' ); ?></button>
                    <details class="mu-hero-banner-row__menu">
                        <summary title="<?php esc_attr_e( 'Más acciones', 'mu' ); ?>">⋯</summary>
                        <div class="mu-hero-banner-row__menu-lista">
                            <button type="button" class="button-link" data-role="subir">▲ <?php esc_html_e( 'Subir', 'mu' ); ?></button>
                            <button type="button" class="button-link" data-role="bajar">▼ <?php esc_html_e( 'Bajar', 'mu' ); ?></button>
                            <button type="button" class="button-link-delete mu-hero-banner-row__remove"><?php esc_html_e( 'Eliminar', 'mu' ); ?></button>
                        </div>
                    </details>
                </span>
                <input type="hidden" name="<?php echo esc_attr( $name ); ?>[_delete]" value="0" class="mu-hero-banner-row__delete-flag">
            </div>

            <div class="mu-hero-banner-row__body">
            <div class="mu-hero-banner-row__head">
                <?php
                // Países e idioma activo ya vienen del resumen: acá queda sólo la barra de
                // herramientas de fila (píldoras de idioma, IA, variantes). Los países se
                // eligen con los chips del resumen (feature 013): el dropdown «Países» y
                // las píldoras de ámbito se retiraron.
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
                // Lienzo del editor (feature 013): el banner REAL, renderizado con la MISMA
                // pieza que el front (mu_hero_slide_render) y sincronizado con el formulario
                // por el JS (data-mu). No guarda nada: es vista.
                $anchos = [
                    '1280' => __( 'Escritorio', 'mu' ),
                    '390'  => __( 'Móvil', 'mu' ),
                ];
                ?>
                <div class="mu-hero-banner-canvas-block mu-hero-banner-row__full">
                    <div class="mu-hero-banner-canvas-head">
                        <strong><?php esc_html_e( 'Vista previa editable', 'mu' ); ?></strong>
                        <span class="mu-hero-banner-canvas-widths" role="group" aria-label="<?php esc_attr_e( 'Ancho de la vista previa', 'mu' ); ?>">
                            <?php foreach ( $anchos as $ancho => $etiqueta ) : ?>
                                <button type="button"
                                        class="mu-hero-banner-canvas-w<?php echo '1280' === (string) $ancho ? ' is-active' : ''; ?>"
                                        data-ancho="<?php echo esc_attr( $ancho ); ?>"><?php echo esc_html( $etiqueta ); ?></button>
                            <?php endforeach; ?>
                        </span>
                        <span class="mu-lang-hint"><?php esc_html_e( 'Se actualiza mientras escribís y sigue el idioma activo. Clic en el banner para ir a su campo.', 'mu' ); ?></span>
                    </div>
                    <div class="mu-hero-banner-canvas" data-mu-canvas data-ancho="1280" data-idioma="<?php echo esc_attr( $idioma_activo_lc ); ?>">
                        <div class="mu-hero-banner-canvas-inner">
                            <div class="mu-hero-promo-slider">
                                <?php
                                echo mu_hero_slide_render( $b, $idioma_activo_lc, [
                                    'index' => 0,
                                    'first' => true,
                                    'mu'    => true,
                                ] );   // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML escapado dentro del renderer.
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

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

                // [014] Agrupación y ORDEN del contenido: los campos se muestran agrupados por
                // lo que SON y en el orden en que aparecen en el banner (eyebrow → título →
                // descripción → botones → imagen), no en el orden en que se guardan. Antes los
                // 10 campos salían en una lista plana y con el mismo peso.
                $grupos_campos = [
                    __( 'Texto', 'mu' )   => [ 'eyebrow', 'titulo', 'descripcion' ],
                    __( 'Botones', 'mu' ) => [ 'cta_texto', 'cta_url', 'cta_secundario_texto', 'cta_secundario_url', 'free_badge_text', 'free_badge_url' ],
                    __( 'Imagen', 'mu' )  => [ 'imagen' ],
                ];

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
                    <?php foreach ( $grupos_campos as $titulo_grupo => $campos_grupo ) : ?>
                        <p class="mu-hero-banner-group-title"><?php echo esc_html( $titulo_grupo ); ?></p>
                        <div class="mu-hero-banner-fields-grid">
                            <?php foreach ( $campos_grupo as $field ) { $render_lang_field( $field, '' ); } ?>
                        </div>
                    <?php endforeach; ?>
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
                    <?php foreach ( $grupos_campos as $titulo_grupo => $campos_grupo ) : ?>
                        <p class="mu-hero-banner-group-title"><?php echo esc_html( $titulo_grupo ); ?></p>
                        <div class="mu-hero-banner-fields-grid">
                            <?php foreach ( $campos_grupo as $field ) { $render_lang_field( $field, '_' . $lang ); } ?>
                        </div>
                    <?php endforeach; ?>
                    <p class="mu-lang-hint">
                        <?php esc_html_e( 'Lo que dejes vacío (o igual al español) muestra el español en el sitio.', 'mu' ); ?>
                    </p>
                </div>
                <?php endforeach; ?>

                <!-- Bloque global: no cambia por idioma -->
                <div class="mu-hero-banner-globals-block">
                    <?php
                    // Identidad de la fila (feature 013): el ID y la casilla del badge. Las
                    // FECHAS se mudaron a la tabla de vigencia, que muestra todos los ámbitos
                    // a la vez (antes había una píldora = pestaña por país).
                    ?>
                    <p class="mu-hero-banner-globals-title">
                        <strong><?php esc_html_e( 'Ajustes de la fila (iguales en todos los idiomas)', 'mu' ); ?></strong>
                        <span class="mu-lang-hint"><?php esc_html_e( 'Identidad y badge. El TEXTO del badge se edita por idioma, en el grupo «Botones».', 'mu' ); ?></span>
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
                        <!-- Las fechas generales viven en la tabla de vigencia (feature 013) -->
                        <p>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( $name ); ?>[show_free_badge]" value="1" <?php checked( ! empty( $b['show_free_badge'] ) ); ?>>
                                <strong><?php esc_html_e( 'Mostrar badge promo', 'mu' ); ?></strong>
                            </label>
                        </p>

                    <?php
                    // VIGENCIA (feature 013): todos los ámbitos a la vez, en una tabla. Antes
                    // había que elegir una píldora por país para ver cada ventana; ahora se ven
                    // juntas y la columna «Se ve» dice qué resulta con lo guardado, en el huso
                    // del ámbito (la pregunta «¿medianoche de dónde?», respondida a la vista).
                    $render_fila_vigencia = function ( $ambito ) use ( $render_fecha, $b, $name, $countries_data ) {
                        $es_global = ( '' === $ambito );
                        $c         = $es_global ? [ 'name' => '' ] : ( $countries_data[ $ambito ] ?? [ 'name' => '' ] );
                        $tildado   = $es_global || in_array( $ambito, $b['paises'], true );

                        $campo_ini = $es_global ? $name . '[inicio]' : $name . '[fechas][' . $ambito . '][inicio]';
                        $campo_fin = $es_global ? $name . '[fin]' : $name . '[fechas][' . $ambito . '][fin]';
                        $campo_anu = $es_global ? $name . '[anual]' : $name . '[fechas][' . $ambito . '][anual]';

                        $ini = $es_global ? (string) $b['inicio'] : (string) ( $b['fechas'][ $ambito ]['inicio'] ?? '' );
                        $fin = $es_global ? (string) $b['fin'] : (string) ( $b['fechas'][ $ambito ]['fin'] ?? '' );

                        // Las generales quedan como placeholder de cada país: se VE la herencia.
                        $ph_ini = ( '' !== (string) $b['inicio'] ) ? (string) $b['inicio'] : '01012026';
                        $ph_fin = ( '' !== (string) $b['fin'] ) ? (string) $b['fin'] : '31122026';

                        $tz        = mu_hero_banner_pais_tz( $ambito );
                        $ventana_e = mu_hero_banner_ventana_instantes( $b, $ambito );
                        $nota      = mu_hero_banner_anual_efectivo( $b, $ambito ) ? ' · ' . __( 'cada año', 'mu' ) : '';

                        // [014] ¿Este ámbito tiene ventana propia? Las que no, se ocultan: la
                        // tabla muestra Global + excepciones (con "ver todos" se ven igual).
                        $es_exc = ! $es_global && mu_hero_banner_pais_es_excepcion( $b, $ambito );
                        $clase  = 'mu-hero-banner-fechas-block'
                            . ( $tildado ? '' : ' is-inactivo' )
                            . ( $es_global ? '' : ( $es_exc ? ' is-excepcion' : ' is-hereda' ) );
                        ?>
                        <tr class="<?php echo esc_attr( $clase ); ?>"
                            data-pais="<?php echo esc_attr( $ambito ); ?>"
                            data-tildado="<?php echo $tildado ? '1' : '0'; ?>"
                            data-es-excepcion="<?php echo $es_exc ? '1' : '0'; ?>">
                            <th scope="row">
                                <?php if ( $es_global ) : ?>
                                    <strong><?php esc_html_e( 'Global', 'mu' ); ?></strong>
                                <?php else : ?>
                                    <strong><?php echo esc_html( $ambito ); ?></strong>
                                    <span class="mu-lang-hint"><?php echo esc_html( $c['name'] ); ?></span>
                                <?php endif; ?>
                            </th>
                            <td><?php $render_fecha( 'inicio', __( 'Inicio', 'mu' ), $campo_ini, $ini, $ph_ini ); ?></td>
                            <td><?php $render_fecha( 'fin', __( 'Fin', 'mu' ), $campo_fin, $fin, $ph_fin ); ?></td>
                            <td>
                                <label class="mu-hero-banner-anual-field">
                                    <input type="hidden" name="<?php echo esc_attr( $campo_anu ); ?>" value="0">
                                    <input type="checkbox" name="<?php echo esc_attr( $campo_anu ); ?>" value="1" data-role="anual" <?php checked( mu_hero_banner_anual_efectivo( $b, $ambito ) ); ?>>
                                    <span class="screen-reader-text"><?php esc_html_e( 'Se repite cada año', 'mu' ); ?></span>
                                </label>
                            </td>
                            <td class="mu-hero-banner-vigencia-ver">
                                <?php
                                if ( null === $ventana_e ) {
                                    esc_html_e( 'Siempre', 'mu' );
                                } else {
                                    echo esc_html( sprintf(
                                        '%1$s → %2$s (%3$s)%4$s',
                                        ( new DateTimeImmutable( '@' . $ventana_e[0] ) )->setTimezone( $tz )->format( 'd/m/Y' ),
                                        ( new DateTimeImmutable( '@' . $ventana_e[1] ) )->setTimezone( $tz )->format( 'd/m/Y' ),
                                        // [014] En Global el "huso" es el del sitio, que suele estar
                                        // configurado por offset: "-03:00" no dice nada.
                                        $es_global ? __( 'huso del sitio', 'mu' ) : $tz->getName(),
                                        $nota
                                    ) );
                                }
                                ?>
                                <span class="mu-lang-hint mu-hero-banner-fechas-warning" data-role="fechas-warning" hidden></span>
                            </td>
                            <td>
                                <button type="button" class="mu-hero-banner-vigencia-limpiar" data-role="limpiar-ventana" title="<?php esc_attr_e( 'Borrar las fechas de este ámbito', 'mu' ); ?>">&times;</button>
                            </td>
                        </tr>
                        <?php
                    };
                    ?>

                    <div class="mu-hero-banner-vigencia mu-hero-banner-row__full">
                        <p class="mu-hero-banner-globals-title">
                            <strong><?php esc_html_e( 'Vigencia (fechas DDMMAAAA)', 'mu' ); ?></strong>
                            <span class="mu-lang-hint"><?php esc_html_e( 'Medianoche LOCAL de cada país. Lo que dejés vacío (o igual a Global) hereda de Global; con los dos campos vacíos el banner se muestra siempre en ese ámbito.', 'mu' ); ?></span>
                        </p>
                        <div class="mu-hero-banner-tabla-scroll">
                        <table class="mu-hero-banner-tabla">
                            <thead>
                                <tr>
                                    <th scope="col"><?php esc_html_e( 'Ámbito', 'mu' ); ?></th>
                                    <th scope="col"><?php esc_html_e( 'Inicio', 'mu' ); ?></th>
                                    <th scope="col"><?php esc_html_e( 'Fin', 'mu' ); ?></th>
                                    <th scope="col"><?php esc_html_e( 'Cada año', 'mu' ); ?></th>
                                    <th scope="col"><?php esc_html_e( 'Se ve', 'mu' ); ?></th>
                                    <th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Limpiar', 'mu' ); ?></span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $render_fila_vigencia( '' );

                                $n_exc = count( mu_hero_banner_excepciones( $b ) );
                                ?>
                                <tr class="mu-hero-banner-vigencia-acciones">
                                    <td colspan="6">
                                        <span class="mu-lang-hint">
                                            <?php
                                            printf(
                                                /* translators: 1: países que heredan, 2: total de países. */
                                                esc_html__( 'La ventana general la usan %1$d de %2$d países.', 'mu' ),
                                                count( $countries_data ) - $n_exc,
                                                count( $countries_data )
                                            );
                                            ?>
                                        </span>
                                        <span class="mu-hero-banner-vigencia-controles">
                                            <label class="mu-lang-hint">
                                                <input type="checkbox" data-role="ver-todos">
                                                <?php esc_html_e( 'ver todos los países', 'mu' ); ?>
                                            </label>
                                            <details class="mu-hero-banner-excepcion-add">
                                                <summary><?php esc_html_e( '+ excepción por país', 'mu' ); ?></summary>
                                                <div class="mu-hero-banner-chips-lista">
                                                    <?php
                                                    foreach ( $countries_data as $code => $c ) :
                                                        if ( mu_hero_banner_pais_es_excepcion( $b, $code ) ) {
                                                            continue;   // ya tiene ventana propia
                                                        }
                                                        ?>
                                                        <button type="button" class="mu-hero-banner-chip" data-role="nueva-excepcion" data-pais="<?php echo esc_attr( $code ); ?>" title="<?php echo esc_attr( $c['name'] ); ?>"><?php echo esc_html( $code ); ?></button>
                                                    <?php endforeach; ?>
                                                </div>
                                            </details>
                                        </span>
                                    </td>
                                </tr>
                                <?php
                                foreach ( array_keys( $countries_data ) as $code ) {
                                    $render_fila_vigencia( (string) $code );
                                }
                                ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                    </div>
                </div>
            </div>
            </div><!-- /.mu-hero-banner-row__body -->
        </div>
        <?php
    }
}
