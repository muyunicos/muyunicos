<?php
/**
 * Muy Únicos — Servicio de traducción con IA en contexto de tienda
 *
 * Feature 006 (specs/006-ai-translate-core). Contrato: contracts/ai-translate.md.
 *
 * QUÉ HACE
 * Traduce SOLO lo que el tema guarda en base de datos (reseñas y hero banners) al
 * portugués e inglés que el sitio ya publica en /pt/ y /en/, con el contexto de la
 * tienda (rol, tono, glosario, exclusiones) y devolviendo la propuesta al editor:
 * este módulo NO publica ni escribe contenido — el guardado de cada ámbito es el que
 * ya existe y el que revisa una persona (FR-010, R9).
 *
 * INVARIANTES (verificables con el quickstart)
 *  - Único envío saliente de escritura del tema: vive acá y en ningún otro archivo.
 *  - Nunca se ejecuta en la navegación de un visitante: solo desde el panel
 *    (wp-admin) o desde una tarea programada futura (FR-005).
 *  - La clave vive en la configuración del servidor (constante) o en su propia opción de
 *    WordPress (sin autoload, pegada desde la pantalla): nunca en el contenido, en el
 *    repositorio ni en el HTML. La constante tiene prioridad si está definida (FR-001).
 *  - Nunca se envían datos de clientes: filtro previo por patrón (FR-012).
 *  - Caché y contador en wp_options con prefijo _mu_* (sobreviven al vaciado del
 *    object cache — constitución VI). Sin transients para datos durables.
 *  - Con el servicio sin configurar, caído o sin cuota, el sitio sigue funcionando
 *    igual: se muestra el texto en español (FR-018).
 *
 * @package GeneratePress_Child
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================
// CONSTANTES
// ============================================

if ( ! defined( 'MU_AI_SETTINGS_OPTION' ) ) define( 'MU_AI_SETTINGS_OPTION', '_mu_ai_settings' );
if ( ! defined( 'MU_AI_KEY_OPTION' ) )      define( 'MU_AI_KEY_OPTION', '_mu_ai_key' );
if ( ! defined( 'MU_AI_CACHE_OPTION' ) )    define( 'MU_AI_CACHE_OPTION', '_mu_ai_cache' );
if ( ! defined( 'MU_AI_USAGE_OPTION' ) )    define( 'MU_AI_USAGE_OPTION', '_mu_ai_usage' );
if ( ! defined( 'MU_AI_ENDPOINT' ) )        define( 'MU_AI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta' );
if ( ! defined( 'MU_AI_TIMEOUT' ) )         define( 'MU_AI_TIMEOUT', 20 );
if ( ! defined( 'MU_AI_CACHE_MAX' ) )       define( 'MU_AI_CACHE_MAX', 500 );
if ( ! defined( 'MU_AI_CACHE_TTL' ) )       define( 'MU_AI_CACHE_TTL', 180 * DAY_IN_SECONDS );
if ( ! defined( 'MU_AI_LANGS' ) )           define( 'MU_AI_LANGS', [ 'pt', 'en' ] );
if ( ! defined( 'MU_AI_DEFAULT_MODEL' ) )   define( 'MU_AI_DEFAULT_MODEL', 'gemini-flash-lite-latest' );

// ============================================
// CLAVE Y ACCESO
// ============================================

if ( ! function_exists( 'mu_ai_api_key' ) ) {
    /**
     * Clave del servicio, desde la configuración del servidor o desde la base.
     *
     * Prioridad: la constante de `wp-config.php` gana si está definida (permite mover
     * la clave fuera de la base sin tocar código); si no, se usa la que el mantenedor
     * pegó en la pantalla (FR-001: configuración del servidor **o de WordPress**).
     *
     * Nunca se devuelve al navegador (la pantalla muestra solo una versión enmascarada)
     * y nunca se registra en logs.
     *
     * @return string Clave o '' si no hay ninguna.
     */
    function mu_ai_api_key() {
        if ( defined( 'MU_GEMINI_API_KEY' ) && '' !== trim( (string) MU_GEMINI_API_KEY ) ) {
            return trim( (string) MU_GEMINI_API_KEY );
        }

        return trim( (string) get_option( MU_AI_KEY_OPTION, '' ) );
    }
}

if ( ! function_exists( 'mu_ai_key_source' ) ) {
    /**
     * De dónde sale la clave en uso: 'constant', 'option' o '' (sin configurar).
     *
     * @return string
     */
    function mu_ai_key_source() {
        if ( defined( 'MU_GEMINI_API_KEY' ) && '' !== trim( (string) MU_GEMINI_API_KEY ) ) {
            return 'constant';
        }

        return ( '' !== trim( (string) get_option( MU_AI_KEY_OPTION, '' ) ) ) ? 'option' : '';
    }
}

if ( ! function_exists( 'mu_ai_save_key' ) ) {
    /**
     * Guarda (o borra) la clave pegada en la pantalla.
     *
     * Se guarda en su propia opción, sin autoload: no se carga en la navegación de
     * visitantes y se borra con una sola acción (rollback). No se guarda en la opción de
     * ajustes para que nunca viaje dentro de arrays que la pantalla imprime.
     *
     * @param string $key Clave nueva, o '' para borrarla.
     * @return bool
     */
    function mu_ai_save_key( $key ) {
        $key = trim( sanitize_text_field( (string) $key ) );

        if ( '' === $key ) {
            delete_option( MU_AI_KEY_OPTION );
            return true;
        }

        return update_option( MU_AI_KEY_OPTION, $key, false );
    }
}

if ( ! function_exists( 'mu_ai_key_masked' ) ) {
    /**
     * Versión enmascarada de la clave, solo para mostrar en pantalla.
     *
     * @return string Ej: 'AQ.A…c60w', o '' si no hay clave.
     */
    function mu_ai_key_masked() {
        $key = mu_ai_api_key();

        if ( '' === $key ) {
            return '';
        }

        if ( strlen( $key ) <= 10 ) {
            return str_repeat( '•', strlen( $key ) );
        }

        return substr( $key, 0, 4 ) . '…' . substr( $key, -4 );
    }
}

if ( ! function_exists( 'mu_ai_can_manage' ) ) {
    /**
     * Gate del servicio: mismo criterio que el resto del panel de la tienda.
     *
     * @return bool
     */
    function mu_ai_can_manage() {
        return current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' );
    }
}

if ( ! function_exists( 'mu_ai_is_configured' ) ) {
    /**
     * ¿Hay clave y el servicio está habilitado? No hace red ni consulta pesada.
     *
     * @return bool
     */
    function mu_ai_is_configured() {
        $settings = mu_ai_settings();
        return ( '' !== mu_ai_api_key() ) && ! empty( $settings['enabled'] );
    }
}

// ============================================
// CONFIGURACIÓN
// ============================================

if ( ! function_exists( 'mu_ai_settings_defaults' ) ) {
    /**
     * Valores de fábrica: modelo, límites y perfiles de contexto de la tienda.
     *
     * El glosario y el tono son editables desde la pantalla (FR-007). Cambiar un
     * glosario invalida la caché anterior, porque su versión entra en la clave.
     *
     * @return array
     */
    function mu_ai_settings_defaults() {
        return [
            'enabled'  => true,
            'model'    => MU_AI_DEFAULT_MODEL,
            'limits'   => [
                'per_action' => 5,      // textos por acción del botón
                'per_minute' => 10,     // solicitudes por minuto (ventana propia)
                'per_day'    => 120,    // techo diario propio, además del del proveedor
            ],
            'contexts' => [
                'reviews'     => [
                    'role'        => 'Traducís la opinión pública de un cliente de una tienda online argentina de stickers y papelería personalizada.',
                    'tone'        => 'Cercano y natural, como habla un cliente real en Brasil o Estados Unidos. Sin lenguaje publicitario: un poco de entusiasmo, creíble, nunca exagerado. Si el texto es largo, poné lo más interesante primero. Se permite recortar redundancia para productos digitales; está prohibido inventar datos.',
                    'glossary'    => "stickers → adesivos (pt) / stickers (en)\netiquetas imprimibles → etiquetas imprimibles (pt) / printable labels (en)\nMuy Únicos (marca: no traducir)",
                    'exclude'     => 'No traduzcas nombres propios ni la marca. No cambies el sentido, el sarcasmo ni los elogios. No agregues datos que no estén (fechas, productos, nombres).',
                    // Una persona por línea: cada variante se escribe como una de ellas.
                    'personas'    => "El Escritor Rápido\nLa Mamá Práctica\nEl Gente de Campo\nEl Sofisticado\nEl Cliente Escéptico",
                    'variants'    => 5,
                    'temperature' => 0.9,
                    'max_chars'   => 700,
                    'allow_html'  => false,
                ],
                'hero_banner' => [
                    'role'        => 'Traducís textos de portada de una tienda online argentina de stickers y papelería personalizada.',
                    'tone'        => 'Breve, claro y vendedor, sin exagerar. Conservá las mayúsculas de marca.',
                    'glossary'    => "envío digital → envio digital (pt) / digital shipping (en)\n¡Gratis! → Grátis! (pt) / Free! (en)",
                    'exclude'     => 'Conservá el marcado <span class="mu-highlight">…</span> tal cual. No traduzcas URLs, rutas ni nombres de archivo. No marques unchanged en botones, etiquetas ni títulos cortos: traducilos siempre, aunque el texto sea un nombre de producto (los nombres también se traducen).',
                    'personas'    => '',
                    'variants'    => 1,
                    'temperature' => 0.2,
                    'max_chars'   => 1200,
                    'allow_html'  => true,
                ],
            ],
        ];
    }
}

if ( ! function_exists( 'mu_ai_settings' ) ) {
    /**
     * Configuración efectiva: lo guardado fusionado con los valores de fábrica.
     *
     * La fusión permite sumar ámbitos nuevos sin romper una configuración existente.
     * Se memoriza por request; `$flush = true` la limpia (lo usa el guardado).
     *
     * @param bool $flush Fuerza la relectura.
     * @return array
     */
    function mu_ai_settings( $flush = false ) {
        static $settings = null;

        if ( $flush ) {
            $settings = null;
        }

        if ( null !== $settings ) {
            return $settings;
        }

        $defaults = mu_ai_settings_defaults();
        $stored   = get_option( MU_AI_SETTINGS_OPTION, [] );
        $stored   = is_array( $stored ) ? $stored : [];

        $settings = wp_parse_args( $stored, $defaults );

        $contexts = [];
        foreach ( $defaults['contexts'] as $ambito => $default_context ) {
            $stored_context      = ( isset( $stored['contexts'][ $ambito ] ) && is_array( $stored['contexts'][ $ambito ] ) )
                ? $stored['contexts'][ $ambito ]
                : [];
            $contexts[ $ambito ] = wp_parse_args( $stored_context, $default_context );
        }
        $settings['contexts'] = $contexts;

        $settings['limits'] = wp_parse_args(
            ( isset( $stored['limits'] ) && is_array( $stored['limits'] ) ) ? $stored['limits'] : [],
            $defaults['limits']
        );

        return $settings;
    }
}

if ( ! function_exists( 'mu_ai_save_settings' ) ) {
    /**
     * Guarda la configuración con validación. La clave NO se guarda nunca acá: vive
     * en la configuración del servidor (FR-001).
     *
     * @param array $patch Cambios a persistir (enabled, model, limits, contexts).
     * @return bool
     */
    function mu_ai_save_settings( $patch ) {
        $current  = mu_ai_settings();
        $defaults = mu_ai_settings_defaults();

        $model = isset( $patch['model'] ) ? sanitize_text_field( (string) $patch['model'] ) : $current['model'];
        if ( '' === $model || ! preg_match( '/^[a-zA-Z0-9._-]{3,60}$/', $model ) ) {
            $model = $defaults['model'];
        }

        $limits = $current['limits'];
        if ( isset( $patch['limits'] ) && is_array( $patch['limits'] ) ) {
            foreach ( [ 'per_action', 'per_minute', 'per_day' ] as $key ) {
                if ( isset( $patch['limits'][ $key ] ) ) {
                    $limits[ $key ] = max( 1, min( 1000, intval( $patch['limits'][ $key ] ) ) );
                }
            }
        }

        $contexts = $current['contexts'];
        if ( isset( $patch['contexts'] ) && is_array( $patch['contexts'] ) ) {
            foreach ( $patch['contexts'] as $ambito => $values ) {
                if ( ! isset( $defaults['contexts'][ $ambito ] ) || ! is_array( $values ) ) {
                    continue;
                }
                foreach ( [ 'role', 'tone', 'glossary', 'exclude', 'personas' ] as $field ) {
                    if ( isset( $values[ $field ] ) ) {
                        $contexts[ $ambito ][ $field ] = sanitize_textarea_field( (string) $values[ $field ] );
                    }
                }
                if ( isset( $values['max_chars'] ) ) {
                    $contexts[ $ambito ]['max_chars'] = max( 60, min( 5000, intval( $values['max_chars'] ) ) );
                }
                if ( isset( $values['variants'] ) ) {
                    $contexts[ $ambito ]['variants'] = max( 1, min( 5, intval( $values['variants'] ) ) );
                }
                if ( isset( $values['temperature'] ) ) {
                    $contexts[ $ambito ]['temperature'] = max( 0, min( 1.5, (float) $values['temperature'] ) );
                }
            }
        }

        $saved = update_option(
            MU_AI_SETTINGS_OPTION,
            [
                'enabled'  => isset( $patch['enabled'] ) ? ! empty( $patch['enabled'] ) : ! empty( $current['enabled'] ),
                'model'    => $model,
                'limits'   => $limits,
                'contexts' => $contexts,
            ],
            false
        );

        mu_ai_settings( true ); // la pantalla muestra lo recién guardado

        return $saved;
    }
}

// ============================================
// ÁMBITOS, CONTEXTO Y MARCAS
// ============================================

if ( ! function_exists( 'mu_ai_ambitos' ) ) {
    /**
     * Registro declarativo de ámbitos (R11): sumar uno nuevo es declararlo acá.
     *
     * `fields` son los campos traducibles del ámbito; `allow_html` decide si el
     * destino admite marcado (los títulos de banner conservan sus resaltados).
     *
     * @return array<string,array>
     */
    function mu_ai_ambitos() {
        return [
            'reviews'     => [
                'label'  => 'Reseñas',
                'fields' => [ 'text' ],   // solo el comentario (decisión Q3)
            ],
            'hero_banner' => [
                'label'  => 'Hero banners',
                'fields' => [ 'titulo', 'descripcion', 'eyebrow', 'cta_texto', 'cta_secundario_texto', 'free_badge_text' ],
            ],
        ];
    }
}

if ( ! function_exists( 'mu_ai_ambito' ) ) {
    /**
     * Definición de un ámbito declarado, o [] si no existe.
     *
     * @param string $ambito
     * @return array
     */
    function mu_ai_ambito( $ambito ) {
        $all = mu_ai_ambitos();
        return isset( $all[ $ambito ] ) ? $all[ $ambito ] : [];
    }
}

if ( ! function_exists( 'mu_ai_context_profile' ) ) {
    /**
     * Perfil de contexto efectivo del ámbito (rol, tono, glosario, exclusiones).
     *
     * @param string $ambito
     * @return array
     */
    function mu_ai_context_profile( $ambito ) {
        $settings = mu_ai_settings();
        return isset( $settings['contexts'][ $ambito ] ) ? $settings['contexts'][ $ambito ] : [];
    }
}

if ( ! function_exists( 'mu_ai_glossary_version' ) ) {
    /**
     * Versión del contexto: si cambia el glosario o el tono, la caché anterior deja
     * de reutilizarse (la clave la incluye).
     *
     * @param string $ambito
     * @return string
     */
    function mu_ai_glossary_version( $ambito ) {
        $profile = mu_ai_context_profile( $ambito );

        // Firma de TODAS las instrucciones: si se edita cualquiera (rol, tono,
        // glosario, exclusiones, personas, variantes o temperatura), la caché
        // anterior deja de reutilizarse. Antes sólo miraba glosario y tono, así que
        // cambiar el rol o las reglas seguía devolviendo traducciones viejas.
        $firma = '';

        foreach ( [ 'role', 'tone', 'glossary', 'exclude', 'personas', 'variants', 'temperature' ] as $campo ) {
            $firma .= '|' . (string) ( $profile[ $campo ] ?? '' );
        }

        return substr( md5( $firma ), 0, 8 );
    }
}

if ( ! function_exists( 'mu_ai_personas' ) ) {
    /**
     * Personas del ámbito, una por línea (cada variante se escribe como una de ellas).
     *
     * @param string $ambito
     * @return string[]
     */
    function mu_ai_personas( $ambito ) {
        $profile = mu_ai_context_profile( $ambito );
        $out     = [];

        foreach ( preg_split( '/\r\n|\r|\n/', (string) ( $profile['personas'] ?? '' ) ) as $linea ) {
            $linea = trim( (string) $linea );

            if ( '' !== $linea ) {
                $out[] = $linea;
            }
        }

        return $out;
    }
}

if ( ! function_exists( 'mu_ai_variants' ) ) {
    /**
     * Cuántas variantes pide una acción manual en este ámbito.
     *
     * Sin personas no hay variantes (una única traducción estándar: es la ruta
     * automática). Con personas, se acota a la cantidad de personas disponibles,
     * porque cada variante usa una.
     *
     * @param string $ambito
     * @return int 1..5
     */
    function mu_ai_variants( $ambito ) {
        $profile  = mu_ai_context_profile( $ambito );
        $personas = mu_ai_personas( $ambito );

        if ( ! $personas ) {
            return 1;
        }

        return max( 1, min( count( $personas ), min( 5, (int) ( $profile['variants'] ?? 1 ) ) ) );
    }
}

if ( ! function_exists( 'mu_ai_hash' ) ) {
    /**
     * Marca de origen de una traducción: hash del texto en español que la produjo.
     *
     * @param string $text
     * @return string
     */
    function mu_ai_hash( $text ) {
        return substr( md5( trim( wp_strip_all_tags( (string) $text ) ) ), 0, 12 );
    }
}

if ( ! function_exists( 'mu_ai_is_stale' ) ) {
    /**
     * ¿La traducción guardada quedó desactualizada respecto del español actual?
     * Sin traducción o sin marca, no se considera desactualizada (FR-013).
     *
     * @param string $source_es   Texto actual en español.
     * @param string $traduccion  Traducción guardada.
     * @param string $hash        Marca de origen guardada.
     * @return bool
     */
    function mu_ai_is_stale( $source_es, $traduccion, $hash ) {
        if ( '' === trim( (string) $traduccion ) || '' === trim( (string) $hash ) ) {
            return false;
        }
        return $hash !== mu_ai_hash( $source_es );
    }
}

if ( ! function_exists( 'mu_ai_sanitize_for_field' ) ) {
    /**
     * Limpia el texto antes de enviarlo y también lo que devuelve el proveedor.
     *
     * Con marcado permitido se conservan los resaltados del banner; sin marcado se
     * descarta todo. El modelo es entrada no confiable (R3).
     *
     * @param string $text
     * @param string $ambito
     * @return string
     */
    function mu_ai_sanitize_for_field( $text, $ambito ) {
        $profile    = mu_ai_context_profile( $ambito );
        $allow_html = ! empty( $profile['allow_html'] );
        $text       = (string) $text;

        if ( $allow_html ) {
            $allowed = [
                'span'   => [ 'class' => true ],
                'strong' => [],
                'em'     => [],
                'b'      => [],
                'i'      => [],
                'br'     => [],
            ];
            return trim( (string) wp_kses( $text, $allowed ) );
        }

        return trim( (string) wp_strip_all_tags( $text ) );
    }
}

if ( ! function_exists( 'mu_ai_looks_personal' ) ) {
    /**
     * Filtro de privacidad: los datos de un cliente nunca salen del sitio (FR-012).
     *
     * Detecta correo, teléfono (9 dígitos o más, con o sin separadores), número de
     * pedido, y palabras que anuncian datos personales.
     *
     * @param string $text
     * @return bool
     */
    function mu_ai_looks_personal( $text ) {
        $text = (string) $text;

        if ( '' === trim( $text ) ) {
            return false;
        }

        if ( preg_match( '/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $text ) ) {
            return true; // correo
        }

        if ( preg_match( '/\b(?:pedido|orden|order|compra|ticket)\s*(?:n[°ºo.]*|#|:)?\s*\d{3,}/iu', $text ) ) {
            return true; // número de pedido
        }

        if ( preg_match( '/\b(?:whatsapp|tel[eé]fono|celular|direcci[oó]n|dni|cpf|cuit|tarjeta)\b/iu', $text ) ) {
            return true; // el texto habla de datos personales
        }

        if ( preg_match( '/\b(?:descarga|download)\b[^\n]{0,40}(?:https?:\/\/|\/[a-z0-9\-]+\/)/iu', $text ) ) {
            return true; // enlace de descarga
        }

        // Teléfonos: cualquier candidato numérico con 9 dígitos o más.
        if ( preg_match_all( '/\d[\d\s.\-()]{6,}/', $text, $matches ) ) {
            foreach ( $matches[0] as $candidate ) {
                if ( strlen( preg_replace( '/\D/', '', $candidate ) ) >= 9 ) {
                    return true;
                }
            }
        }

        return false;
    }
}

// ============================================
// CACHÉ DE TRADUCCIONES (data-model §2)
// ============================================

if ( ! function_exists( 'mu_ai_cache_key' ) ) {
    /**
     * Clave de caché: texto + idioma + ámbito + versión del glosario + modelo.
     * Cambiar el glosario o el modelo deja de reutilizar lo anterior.
     *
     * @param string $text
     * @param string $lang
     * @param string $ambito
     * @return string
     */
    function mu_ai_cache_key( $text, $lang, $ambito ) {
        $settings = mu_ai_settings();
        $normal   = trim( wp_strip_all_tags( (string) $text ) );

        return md5( $normal . '|' . $lang . '|' . $ambito . '|' . mu_ai_glossary_version( $ambito ) . '|' . (string) $settings['model'] );
    }
}

if ( ! function_exists( 'mu_ai_cache_all' ) ) {
    /**
     * Caché completa (array en wp_options: sobrevive al vaciado del object cache).
     *
     * @return array
     */
    function mu_ai_cache_all() {
        $cache = get_option( MU_AI_CACHE_OPTION, [] );
        return is_array( $cache ) ? $cache : [];
    }
}

if ( ! function_exists( 'mu_ai_cache_get' ) ) {
    /**
     * Traducción guardada para una clave, o '' si no está o venció.
     *
     * @param string $key
     * @return string
     */
    function mu_ai_cache_get( $key ) {
        $cache = mu_ai_cache_all();

        if ( ! isset( $cache[ $key ] ) || ! is_array( $cache[ $key ] ) ) {
            return '';
        }

        $entry = $cache[ $key ];

        if ( empty( $entry['at'] ) || ( time() - (int) $entry['at'] ) > MU_AI_CACHE_TTL ) {
            return '';
        }

        return (string) ( $entry['t'] ?? '' );
    }
}

if ( ! function_exists( 'mu_ai_cache_set' ) ) {
    /**
     * Guarda una traducción y poda la caché.
     *
     * @param string $key
     * @param string $translation
     * @param string $lang
     * @param string $ambito
     * @return void
     */
    function mu_ai_cache_set( $key, $translation, $lang, $ambito ) {
        $cache = mu_ai_cache_all();

        $cache[ $key ] = [
            't'      => (string) $translation,
            'lang'   => (string) $lang,
            'ambito' => (string) $ambito,
            'at'     => time(),
            'model'  => (string) mu_ai_settings()['model'],
        ];

        update_option( MU_AI_CACHE_OPTION, mu_ai_cache_prune( $cache ), false );
    }
}

if ( ! function_exists( 'mu_ai_cache_prune' ) ) {
    /**
     * Poda por antigüedad (180 días) y por tamaño (500 entradas: descarta las viejas).
     *
     * @param array $cache
     * @return array
     */
    function mu_ai_cache_prune( $cache ) {
        $now = time();

        foreach ( $cache as $key => $entry ) {
            if ( ! is_array( $entry ) || empty( $entry['at'] ) || ( $now - (int) $entry['at'] ) > MU_AI_CACHE_TTL ) {
                unset( $cache[ $key ] );
            }
        }

        if ( count( $cache ) > MU_AI_CACHE_MAX ) {
            uasort(
                $cache,
                function ( $a, $b ) {
                    return ( (int) ( $a['at'] ?? 0 ) ) <=> ( (int) ( $b['at'] ?? 0 ) );
                }
            );
            $cache = array_slice( $cache, -MU_AI_CACHE_MAX, null, true );
        }

        return $cache;
    }
}

// ============================================
// CONSUMO Y LÍMITES (data-model §3)
// ============================================

if ( ! function_exists( 'mu_ai_usage' ) ) {
    /**
     * Consumo normalizado del día y del mes. No escribe: solo presenta.
     *
     * @return array
     */
    function mu_ai_usage() {
        $usage = get_option( MU_AI_USAGE_OPTION, [] );
        $usage = is_array( $usage ) ? $usage : [];

        $usage += [
            'day'            => '',
            'day_requests'   => 0,
            'day_chars'      => 0,
            'day_variants'   => 0,
            'month'          => '',
            'month_requests' => 0,
            'month_chars'    => 0,
            'month_variants' => 0,
            'last_model'     => '',
            'last_error'     => [],
        ];

        $today = current_time( 'Y-m-d' );
        $month = current_time( 'Y-m' );

        if ( $usage['day'] !== $today ) {
            $usage['day']           = $today;
            $usage['day_requests']  = 0;
            $usage['day_chars']     = 0;
            $usage['day_variants']  = 0;
        }

        if ( $usage['month'] !== $month ) {
            $usage['month']          = $month;
            $usage['month_requests'] = 0;
            $usage['month_chars']    = 0;
            $usage['month_variants'] = 0;
        }

        return $usage;
    }
}

if ( ! function_exists( 'mu_ai_usage_bump' ) ) {
    /**
     * Suma UNA solicitud efectivamente enviada y persiste el contador.
     *
     * `$variants` registra cuántas versiones se pidieron en esa solicitud: una tanda
     * manual de 5 variantes es **una** solicitud, pero gasta bastante más salida que
     * una traducción estándar. Sin este dato el panel diría que salió barata.
     *
     * @param int $chars    Caracteres enviados (solo para el registro).
     * @param int $variants Variantes pedidas en esa solicitud (1 = estándar).
     * @return array Contador actualizado.
     */
    function mu_ai_usage_bump( $chars, $variants = 1 ) {
        $usage    = mu_ai_usage();
        $chars    = max( 0, (int) $chars );
        $variants = max( 1, (int) $variants );

        $usage['day_requests']   = (int) $usage['day_requests'] + 1;
        $usage['day_chars']      = (int) $usage['day_chars'] + $chars;
        $usage['day_variants']   = (int) $usage['day_variants'] + $variants;
        $usage['month_requests'] = (int) $usage['month_requests'] + 1;
        $usage['month_chars']    = (int) $usage['month_chars'] + $chars;
        $usage['month_variants'] = (int) $usage['month_variants'] + $variants;

        update_option( MU_AI_USAGE_OPTION, $usage, false );

        return $usage;
    }
}

if ( ! function_exists( 'mu_ai_usage_reset' ) ) {
    /**
     * Reinicio explícito del contador (acción de la pantalla).
     *
     * @return array
     */
    function mu_ai_usage_reset() {
        $usage = mu_ai_usage();

        $usage['day_requests']   = 0;
        $usage['day_chars']      = 0;
        $usage['day_variants']   = 0;
        $usage['month_requests'] = 0;
        $usage['month_chars']    = 0;
        $usage['month_variants'] = 0;

        update_option( MU_AI_USAGE_OPTION, $usage, false );

        return $usage;
    }
}

if ( ! function_exists( 'mu_ai_note_model' ) ) {
    /**
     * Guarda el modelo que el proveedor informa haber usado.
     *
     * El identificador configurado suele ser un alias ("…-latest") que el proveedor
     * resuelve a una versión concreta; sin registrarlo, el mantenedor no puede saber
     * con qué modelo se tradujo de verdad.
     *
     * @param string $version Identificador informado por el proveedor.
     * @return void
     */
    function mu_ai_note_model( $version ) {
        $version = sanitize_text_field( (string) $version );

        if ( '' === $version ) {
            return;
        }

        $usage = mu_ai_usage();

        if ( ( $usage['last_model'] ?? '' ) === $version ) {
            return; // sin cambios: no se escribe de nuevo
        }

        $usage['last_model'] = $version;

        update_option( MU_AI_USAGE_OPTION, $usage, false );
    }
}

if ( ! function_exists( 'mu_ai_set_last_error' ) ) {
    /**
     * Registra el último error legible. Nunca guarda el texto enviado (principio III).
     *
     * @param string $code
     * @param string $msg
     * @return void
     */
    function mu_ai_set_last_error( $code, $msg ) {
        $usage = mu_ai_usage();

        $usage['last_error'] = [
            'code' => (string) $code,
            'msg'  => (string) $msg,
            'at'   => time(),
        ];

        update_option( MU_AI_USAGE_OPTION, $usage, false );
    }
}

if ( ! function_exists( 'mu_ai_rate_check' ) ) {
    /**
     * Ventana de límite GLOBAL (no por visitante): la cuota es del proyecto, no de la
     * IP. Mismo patrón que el rate-limit de AJAX del tema.
     *
     * La ventana es efímera (un minuto): si el object cache la pierde, el peor caso es
     * una solicitud de más. El contador durable (día y mes) sí vive en wp_options.
     *
     * @param int $max    Solicitudes permitidas dentro de la ventana.
     * @param int $window Segundos de la ventana.
     * @return bool true si se puede continuar.
     */
    function mu_ai_rate_check( $max, $window = MINUTE_IN_SECONDS ) {
        $key = 'mu_ai_rate_' . md5( (int) $max . '_' . (int) $window );
        $now = time();

        if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
            $data = wp_cache_get( $key, 'mu_ai' );

            if ( false === $data || ! is_array( $data ) || ( $now - (int) $data['start'] ) >= $window ) {
                wp_cache_set( $key, [ 'count' => 1, 'start' => $now ], 'mu_ai', $window );
                return true;
            }

            if ( (int) $data['count'] >= $max ) {
                return false;
            }

            $data['count'] = (int) $data['count'] + 1;
            wp_cache_set( $key, $data, 'mu_ai', $window );
            return true;
        }

        $data = get_transient( $key );

        if ( false === $data || ! is_array( $data ) || ( $now - (int) $data['start'] ) >= $window ) {
            set_transient( $key, [ 'count' => 1, 'start' => $now ], $window );
            return true;
        }

        if ( (int) $data['count'] >= $max ) {
            return false;
        }

        $data['count'] = (int) $data['count'] + 1;
        set_transient( $key, $data, $window );

        return true;
    }
}

// ============================================
// TRANSPORTE AL PROVEEDOR (contrato C-2)
// ============================================

if ( ! function_exists( 'mu_ai_build_payload' ) ) {
    /**
     * Cuerpo de la solicitud: instrucción de sistema (contexto de tienda) + el lote de
     * textos numerados. El proveedor recibe SOLO esto: nunca datos del sitio.
     *
     * @param array  $items  [ [ 'key' => 'text', 'text' => '…' ] ]
     * @param string $lang
     * @param string $ambito
     * @return array
     */
    function mu_ai_build_payload( $items, $lang, $ambito, $variants = 1, $personas = [] ) {
        $profile = mu_ai_context_profile( $ambito );
        $label   = (string) ( mu_ai_ambito( $ambito )['label'] ?? $ambito );
        $idioma  = ( 'pt' === $lang ) ? 'portugués de Brasil (pt-BR)' : 'inglés (en-US)';

        $personas = is_array( $personas ) ? array_values( array_filter( array_map( 'trim', $personas ) ) ) : [];
        $multiple = ( $variants > 1 && $personas );

        $system = 'Traducís al ' . $idioma . " para una tienda online.\n"
            . 'Ámbito: ' . $label . ".\n"
            . 'Rol: ' . (string) ( $profile['role'] ?? '' ) . "\n"
            . 'Tono: ' . (string) ( $profile['tone'] ?? '' ) . "\n"
            . "Glosario (respetalo):\n" . (string) ( $profile['glossary'] ?? '' ) . "\n"
            . 'Reglas: ' . (string) ( $profile['exclude'] ?? '' ) . "\n";

        if ( $multiple ) {
            $system .= "Devolvé " . count( $personas ) . " versiones del mismo texto, cada una escrita como una persona distinta, en este orden:\n";

            foreach ( $personas as $i => $persona ) {
                $system .= ( $i + 1 ) . '. ' . $persona . "\n";
            }

            $system .= "Cada versión debe sonar como esa persona (vocabulario, ritmo, largo) sin inventar datos ni cambiar el sentido del original. No mezcles personas entre versiones.\n";
        }

        $system .= "No agregues comentarios, notas ni marcas de traducción.\n";

        if ( $multiple ) {
            $system .= 'Devolvé JSON válido con esta forma: {"items":[{"key":"…","variants":["versión 1","versión 2"],"unchanged":false}]}';
        } else {
            $system .= 'Devolvé JSON válido con esta forma: {"items":[{"key":"…","translation":"…","unchanged":false}]}';
        }

        $system .= "\nMarcá unchanged=true solo si el texto ya está escrito en el idioma destino. Si dudás, traducí: es preferible una versión para revisar antes que dejar el texto sin traducir.";

        $lote = [];
        foreach ( $items as $item ) {
            $lote[] = [ 'key' => (string) $item['key'], 'text' => (string) $item['text'] ];
        }

        return [
            'systemInstruction' => [ 'parts' => [ [ 'text' => $system ] ] ],
            'contents'          => [
                [ 'role' => 'user', 'parts' => [ [ 'text' => (string) wp_json_encode( $lote ) ] ] ],
            ],
            // Modo JSON: la forma se declara en la instrucción y se valida en C-1. No se
            // envía responseSchema porque el enum de tipos varía entre versiones de la
            // API; la validación local es la autoridad.
            'generationConfig'  => [
                'temperature'      => (float) ( $profile['temperature'] ?? 0.2 ),
                'responseMimeType' => 'application/json',
            ],
        ];
    }
}

if ( ! function_exists( 'mu_ai_provider_request' ) ) {
    /**
     * Única solicitud saliente del tema. Nunca lanza: siempre devuelve un estado.
     *
     * @param array  $items
     * @param string $lang
     * @param string $ambito
     * @return array [ 'ok' => bool, 'code' => string, 'error' => string, 'items' => array ]
     */
    function mu_ai_provider_request( $items, $lang, $ambito, $variants = 1, $personas = [] ) {
        $settings = mu_ai_settings();
        $chars    = 0;

        foreach ( $items as $item ) {
            $chars += strlen( (string) $item['text'] );
        }

        // Varias variantes en una sola respuesta tardan más: el tope se estira sólo
        // en esa ruta manual.
        $timeout = ( $variants > 1 ) ? max( MU_AI_TIMEOUT, 30 ) : MU_AI_TIMEOUT;

        $response = wp_remote_post(
            MU_AI_ENDPOINT . '/models/' . rawurlencode( (string) $settings['model'] ) . ':generateContent',
            [
                'timeout' => $timeout,
                'headers' => [
                    'Content-Type'   => 'application/json',
                    'x-goog-api-key' => mu_ai_api_key(),
                ],
                'body'    => (string) wp_json_encode( mu_ai_build_payload( $items, $lang, $ambito, $variants, $personas ) ),
            ]
        );

        // Se contabiliza desde que la solicitud sale, incluso si el proveedor falla.
        mu_ai_usage_bump( $chars, $variants );

        if ( is_wp_error( $response ) ) {
            return [
                'ok'    => false,
                'code'  => 'timeout',
                'error' => 'El servicio no respondió a tiempo. Probá de nuevo en un momento.',
                'items' => [],
            ];
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        $raw    = (string) wp_remote_retrieve_body( $response );

        if ( 429 === $status ) {
            return [
                'ok'    => false,
                'code'  => 'quota',
                'error' => 'El servicio alcanzó su límite de uso. Esperá un rato o revisá la cuota del plan.',
                'items' => [],
            ];
        }

        if ( 401 === $status || 403 === $status ) {
            return [
                'ok'    => false,
                'code'  => 'bad_key',
                'error' => 'La clave del servicio fue rechazada. Revisá la configuración.',
                'items' => [],
            ];
        }

        if ( $status < 200 || $status >= 300 ) {
            return [
                'ok'    => false,
                'code'  => 'bad_response',
                'error' => 'El servicio respondió con un error (' . $status . '). No se escribió nada.',
                'items' => [],
            ];
        }

        $data = json_decode( $raw, true );

        // Observabilidad: con qué modelo tradujo realmente el proveedor (el alias se
        // resuelve de su lado). Medido el 2026-10-16: …-lite-latest → gemini-3.5-flash-lite.
        mu_ai_note_model( isset( $data['modelVersion'] ) ? (string) $data['modelVersion'] : '' );

        $text = '';

        if ( isset( $data['candidates'][0]['content']['parts'] ) && is_array( $data['candidates'][0]['content']['parts'] ) ) {
            foreach ( $data['candidates'][0]['content']['parts'] as $part ) {
                $text .= (string) ( $part['text'] ?? '' );
            }
        }

        // El modelo puede envolver el JSON en una cerca de código: se limpia antes de leer.
        $json = json_decode( (string) preg_replace( '/^```[a-z]*|```$/m', '', trim( $text ) ), true );

        if ( ! is_array( $json ) || ! isset( $json['items'] ) || ! is_array( $json['items'] ) ) {
            return [
                'ok'    => false,
                'code'  => 'bad_response',
                'error' => 'La respuesta del servicio no tenía el formato esperado. No se escribió nada.',
                'items' => [],
            ];
        }

        $out = [];

        foreach ( $json['items'] as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $key = isset( $row['key'] ) ? (string) $row['key'] : '';
            if ( '' === $key ) {
                continue;
            }

            $translation = isset( $row['translation'] ) ? (string) $row['translation'] : '';
            $variantes   = [];

            // El modo manual devuelve una lista (una por persona) y el automático un
            // único `translation`: se aceptan las dos formas.
            if ( isset( $row['variants'] ) && is_array( $row['variants'] ) ) {
                foreach ( $row['variants'] as $variante ) {
                    if ( is_string( $variante ) && '' !== trim( $variante ) ) {
                        $variantes[] = (string) $variante;
                    }
                }
            }

            if ( ! $variantes && '' !== trim( $translation ) ) {
                $variantes[] = $translation;
            }

            if ( '' === trim( $translation ) && $variantes ) {
                $translation = $variantes[0];
            }

            $out[ $key ] = [
                'translation' => $translation,
                'variants'    => $variantes,
                'unchanged'   => ! empty( $row['unchanged'] ) || ( '' === trim( $translation ) ),
            ];
        }

        return [ 'ok' => true, 'code' => '', 'error' => '', 'items' => $out ];
    }
}

// ============================================
// API PÚBLICA PARA LOS ÁMBITOS (contrato C-1)
// ============================================

if ( ! function_exists( 'mu_ai_strlen' ) ) {
    /**
     * Largo del texto en caracteres, con respaldo si no hay mbstring.
     *
     * @param string $text
     * @return int
     */
    function mu_ai_strlen( $text ) {
        return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $text ) : strlen( (string) $text );
    }
}

if ( ! function_exists( 'mu_ai_cache_decode' ) ) {
    /**
     * Convierte una entrada de caché en la lista de variantes.
     *
     * Las entradas anteriores a las variantes guardaban texto plano: se devuelven
     * como lista de una, así la caché vieja sigue siendo válida.
     *
     * @param string $cached
     * @return string[]
     */
    function mu_ai_cache_decode( $cached ) {
        $cached = (string) $cached;

        if ( '' === trim( $cached ) ) {
            return [];
        }

        if ( '[' === substr( ltrim( $cached ), 0, 1 ) ) {
            $lista = json_decode( $cached, true );

            if ( is_array( $lista ) ) {
                $out = [];

                foreach ( $lista as $item ) {
                    if ( is_string( $item ) && '' !== trim( $item ) ) {
                        $out[] = $item;
                    }
                }

                return $out;
            }
        }

        return [ $cached ];
    }
}

if ( ! function_exists( 'mu_ai_translate' ) ) {
    /**
     * Traduce un lote de textos y devuelve la propuesta al editor. NO escribe contenido.
     *
     * Garantías (contrato C-1): sin clave, deshabilitado, texto vacío, texto demasiado
     * largo, dato personal o límite alcanzado ⇒ `ok=false` y **sin** llamada de red.
     * Lo que ya está en caché no genera solicitud.
     *
     * @param array  $items  [ [ 'key' => 'text', 'text' => '…' ] ]
     * @param string $lang   'pt' | 'en'
     * @param string $ambito Ámbito declarado ('reviews', 'hero_banner', …)
     * @return array
     */
    function mu_ai_translate( $items, $lang, $ambito, $opts = [] ) {
        $opts     = is_array( $opts ) ? $opts : [];
        $personas = mu_ai_personas( $ambito );
        $variants = isset( $opts['variants'] ) ? max( 1, min( 5, (int) $opts['variants'] ) ) : mu_ai_variants( $ambito );
        $force    = ! empty( $opts['force'] );

        // Cada variante usa una persona: sin personas hay una única traducción
        // estándar (es la ruta automática, predecible y cacheable al 100%).
        $variants = $personas ? min( $variants, count( $personas ) ) : 1;
        $result = [
            'ok'        => false,
            'code'      => '',
            'error'     => '',
            'items'     => [],
            'missing'   => [],
            'requested' => 0,
            'usage'     => mu_ai_usage(),
        ];

        if ( ! mu_ai_is_configured() ) {
            $result['code']  = 'no_key';
            $result['error'] = 'El servicio de traducción no está configurado. Cargá la clave del servicio en el servidor.';
            return $result;
        }

        if ( ! mu_ai_ambito( $ambito ) ) {
            $result['code']  = 'bad_scope';
            $result['error'] = 'Ámbito de traducción desconocido.';
            return $result;
        }

        if ( ! in_array( $lang, MU_AI_LANGS, true ) ) {
            $result['code']  = 'bad_lang';
            $result['error'] = 'Idioma destino no soportado.';
            return $result;
        }

        $profile   = mu_ai_context_profile( $ambito );
        $max_chars = isset( $profile['max_chars'] ) ? (int) $profile['max_chars'] : 700;

        // 1) Normalizar y validar cada texto: nada sale del sitio sin pasar por acá.
        $clean_items = [];

        foreach ( $items as $item ) {
            $key = isset( $item['key'] ) ? (string) $item['key'] : '';

            if ( '' === $key ) {
                continue;
            }

            $text = mu_ai_sanitize_for_field( isset( $item['text'] ) ? $item['text'] : '', $ambito );

            if ( '' === trim( $text ) ) {
                continue; // texto vacío: no se envía ni se informa como error
            }

            if ( mu_ai_strlen( $text ) > $max_chars ) {
                $result['code']  = 'too_long';
                $result['error'] = sprintf( 'El texto de «%s» supera el largo máximo (%d caracteres).', $key, $max_chars );
                return $result;
            }

            if ( mu_ai_looks_personal( $text ) ) {
                $result['code']  = 'personal_data';
                $result['error'] = sprintf( 'El texto de «%s» parece contener un dato de cliente (correo, teléfono o número de pedido). No se envió nada.', $key );
                return $result; // el lote entero se rechaza (FR-012)
            }

            $clean_items[ $key ] = $text;
        }

        if ( empty( $clean_items ) ) {
            $result['code']  = 'empty';
            $result['error'] = 'No hay texto para traducir.';
            return $result;
        }

        // 2) Caché: lo ya traducido no genera solicitud (SC-002).
        $from_cache = [];
        $pending    = [];

        foreach ( $clean_items as $key => $text ) {
            $cache_key = mu_ai_cache_key( $text, $lang, $ambito );
            $cached    = $force ? '' : mu_ai_cache_get( $cache_key );
            $lista     = mu_ai_cache_decode( $cached );

            if ( $lista ) {
                $from_cache[ $key ] = [
                    'translation' => $lista[0],
                    'variants'    => $lista,
                    'unchanged'   => false,
                    'cached'      => true,
                ];
            } else {
                $pending[ $key ] = [ 'key' => $key, 'text' => $text, 'cache_key' => $cache_key ];
            }
        }

        if ( empty( $pending ) ) {
            $result['ok']       = true;
            $result['items']    = $from_cache;
            $result['variants'] = $variants;
            $result['personas'] = ( $variants > 1 ? $personas : [] );
            return $result;
        }

        // 3) Límites: por acción, por minuto y techo diario.
        $limits = mu_ai_settings()['limits'];

        if ( count( $pending ) > (int) $limits['per_action'] ) {
            $result['code']  = 'rate_limited';
            $result['error'] = sprintf( 'La acción tiene %d textos y el límite por acción es %d. Dividila en partes.', count( $pending ), (int) $limits['per_action'] );
            return $result;
        }

        if ( ! mu_ai_rate_check( (int) $limits['per_minute'] ) ) {
            $result['code']  = 'rate_limited';
            $result['error'] = 'Se alcanzó el límite de solicitudes por minuto. Esperá un momento y volvé a intentar.';
            return $result;
        }

        $usage = mu_ai_usage();

        if ( (int) $usage['day_requests'] >= (int) $limits['per_day'] ) {
            $result['code']  = 'quota';
            $result['error'] = sprintf( 'Se alcanzó el techo diario de traducciones (%d). Se reinicia mañana.', (int) $limits['per_day'] );
            return $result;
        }

        // 4) Una sola solicitud para todo el lote pendiente (R4).
        $batch   = array_values( $pending );
        $request = mu_ai_provider_request( $batch, $lang, $ambito, $variants, ( $variants > 1 ? $personas : [] ) );

        $result['requested'] = 1;
        $result['usage']     = mu_ai_usage();

        if ( empty( $request['ok'] ) ) {
            $result['code']  = (string) $request['code'];
            $result['error'] = (string) $request['error'];
            mu_ai_set_last_error( $result['code'], $result['error'] );
            return $result;
        }

        // 5) Resultado por clave, con saneo de lo que devolvió el proveedor.
        $out = $from_cache;

        foreach ( $batch as $item ) {
            $key = $item['key'];
            $row = isset( $request['items'][ $key ] ) ? $request['items'][ $key ] : null;

            if ( ! is_array( $row ) ) {
                $result['missing'][] = $key;
                continue;
            }

            // Se sanea cada variante y se descartan las que queden vacías.
            $variantes = [];

            foreach ( (array) ( $row['variants'] ?? [] ) as $variante ) {
                $limpia = mu_ai_sanitize_for_field( $variante, $ambito );

                if ( '' !== trim( $limpia ) ) {
                    $variantes[] = $limpia;
                }
            }

            $translation = mu_ai_sanitize_for_field( (string) ( $row['translation'] ?? '' ), $ambito );

            if ( ! $variantes && '' !== trim( $translation ) ) {
                $variantes[] = $translation;
            }

            if ( '' === trim( $translation ) && $variantes ) {
                $translation = $variantes[0];
            }

            $unchanged = ! empty( $row['unchanged'] ) || ( '' === trim( $translation ) );

            $out[ $key ] = [
                'translation' => $translation,
                'variants'    => $variantes,
                'unchanged'   => $unchanged,
                'cached'      => false,
            ];

            if ( ! $unchanged && $variantes ) {
                // Se cachea la tanda completa: el siguiente clic con la misma
                // persona es gratis (para otra tanda se manda force=1).
                mu_ai_cache_set(
                    $item['cache_key'],
                    ( count( $variantes ) > 1 ? (string) wp_json_encode( $variantes ) : $variantes[0] ),
                    $lang,
                    $ambito
                );
            }
        }

        if ( empty( $out ) ) {
            $result['code']  = 'bad_response';
            $result['error'] = 'El servicio no devolvió ninguna traducción utilizable. No se escribió nada.';
            return $result;
        }

        $result['ok']       = true;
        $result['items']    = $out;
        $result['variants'] = $variants;
        $result['personas'] = ( $variants > 1 ? $personas : [] );

        return $result;
    }
}

if ( ! function_exists( 'mu_ai_test_connection' ) ) {
    /**
     * Prueba de conexión: valida la clave, lista los modelos disponibles y mide la
     * latencia. Usa un texto fijo del propio sistema: nunca contenido del sitio.
     *
     * @return array [ 'ok', 'model', 'models', 'latency_ms', 'error' ]
     */
    function mu_ai_test_connection() {
        $out = [
            'ok'         => false,
            'model'      => '',
            'models'     => [],
            'latency_ms' => 0,
            'error'      => '',
        ];

        if ( '' === mu_ai_api_key() ) {
            $out['error'] = 'No hay clave configurada. Definí la constante de la clave en la configuración del servidor.';
            return $out;
        }

        $out['model'] = (string) mu_ai_settings()['model'];

        $start = microtime( true );
        $list  = wp_remote_get(
            MU_AI_ENDPOINT . '/models',
            [
                'timeout' => MU_AI_TIMEOUT,
                'headers' => [ 'x-goog-api-key' => mu_ai_api_key() ],
            ]
        );
        $out['latency_ms'] = (int) round( ( microtime( true ) - $start ) * 1000 );

        if ( is_wp_error( $list ) ) {
            $out['error'] = 'El servicio no respondió. Revisá la salida del servidor.';
            return $out;
        }

        $status = (int) wp_remote_retrieve_response_code( $list );

        if ( 401 === $status || 403 === $status ) {
            $out['error'] = 'La clave fue rechazada por el servicio.';
            return $out;
        }

        if ( $status < 200 || $status >= 300 ) {
            $out['error'] = 'El servicio respondió con un error (' . $status . ').';
            return $out;
        }

        $data = json_decode( (string) wp_remote_retrieve_body( $list ), true );

        if ( isset( $data['models'] ) && is_array( $data['models'] ) ) {
            foreach ( $data['models'] as $model ) {
                $name = (string) ( $model['name'] ?? '' );
                if ( '' !== $name ) {
                    $out['models'][] = str_replace( 'models/', '', $name );
                }
            }
        }

        if ( ! empty( $out['models'] ) && ! in_array( $out['model'], $out['models'], true ) ) {
            $out['error'] = sprintf(
                'La clave funciona, pero el modelo configurado (%s) no figura entre los disponibles.',
                $out['model']
            );
            return $out;
        }

        $out['ok'] = true;

        return $out;
    }
}

// ============================================
// ACCIONES DEL ADMINISTRADOR (contrato C-3)
// ============================================

if ( ! function_exists( 'mu_ai_ajax_translate' ) ) {
    /**
     * Traduce los campos que envía el editor. No guarda nada: el guardado es del
     * mantenedor, por el camino que ya existe en cada ámbito (R9).
     *
     * @return void
     */
    function mu_ai_ajax_translate() {
        check_ajax_referer( 'mu_ai_nonce', 'nonce' );

        if ( ! mu_ai_can_manage() ) {
            wp_send_json_error( [ 'code' => 'forbidden', 'error' => 'No tenés permisos para traducir contenido.' ] );
        }

        $ambito = isset( $_POST['ambito'] ) ? sanitize_text_field( wp_unslash( $_POST['ambito'] ) ) : '';
        $lang   = isset( $_POST['lang'] ) ? sanitize_text_field( wp_unslash( $_POST['lang'] ) ) : '';
        $raw    = isset( $_POST['fields'] ) ? (string) wp_unslash( $_POST['fields'] ) : '';

        $fields = json_decode( $raw, true );

        if ( ! is_array( $fields ) || empty( $fields ) ) {
            wp_send_json_error( [ 'code' => 'empty', 'error' => 'No se recibieron campos para traducir.' ] );
        }

        $items = [];

        foreach ( $fields as $key => $text ) {
            if ( ! is_string( $text ) ) {
                continue;
            }
            $items[] = [ 'key' => sanitize_text_field( (string) $key ), 'text' => $text ];
        }

        // variants: la ruta manual pide una tanda (una versión por persona); sin el
        // dato se usa lo que declara el ámbito (1 = traducción estándar automática).
        $variants = isset( $_POST['variants'] ) ? max( 1, min( 5, intval( $_POST['variants'] ) ) ) : mu_ai_variants( $ambito );
        $force    = ! empty( $_POST['force'] );

        $result           = mu_ai_translate( $items, $lang, $ambito, [ 'variants' => $variants, 'force' => $force ] );
        $result['ambito'] = $ambito;
        $result['lang']   = $lang;

        if ( empty( $result['ok'] ) ) {
            wp_send_json_error( $result );
        }

        wp_send_json_success( $result );
    }
    add_action( 'wp_ajax_mu_ai_translate', 'mu_ai_ajax_translate' );
}

if ( ! function_exists( 'mu_ai_ajax_test' ) ) {
    /**
     * Prueba de conexión desde la pantalla del servicio.
     *
     * @return void
     */
    function mu_ai_ajax_test() {
        check_ajax_referer( 'mu_ai_nonce', 'nonce' );

        if ( ! mu_ai_can_manage() ) {
            wp_send_json_error( [ 'code' => 'forbidden', 'error' => 'No tenés permisos.' ] );
        }

        $result = mu_ai_test_connection();

        if ( empty( $result['ok'] ) ) {
            wp_send_json_error( $result );
        }

        wp_send_json_success( $result );
    }
    add_action( 'wp_ajax_mu_ai_test', 'mu_ai_ajax_test' );
}









