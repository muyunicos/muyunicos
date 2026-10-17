<?php
/**
 * Module: Reviews Identity & Duplicates
 * Description: Identidad de reseñas para evitar duplicados entre la carga manual y
 *              la importación desde Google. Funciones PURAS (sin WordPress salvo
 *              remove_accents/wp_strip_all_tags), para poder verificarlas con un
 *              arnés: ver specs/009-resenas-sin-duplicados/.
 *
 *              Por qué existe: la importación comparaba `time` exacto (timestamp
 *              con hora) contra el `time` de una reseña cargada a mano, que es un
 *              día (00:00) o el momento de crearla → nunca coincidía y el duplicado
 *              era seguro.
 *
 * Depende de: nada (functions.php lo carga antes de ui.php)
 * @package GeneratePress_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'mu_review_normalize' ) ) {
    /**
     * Normaliza un texto para comparar reseñas: sin marcado, sin acentos, en
     * minúsculas, sin puntuación y con espacios colapsados.
     *
     * @param string $text
     * @return string
     */
    function mu_review_normalize( $text ) {
        $text = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( (string) $text ) : strip_tags( (string) $text );
        $text = function_exists( 'remove_accents' ) ? remove_accents( $text ) : $text;
        $text = strtolower( (string) $text );
        $text = preg_replace( '/[^a-z0-9]+/u', ' ', $text );

        return trim( preg_replace( '/\s+/', ' ', (string) $text ) );
    }
}

if ( ! function_exists( 'mu_review_author_key' ) ) {
    /**
     * Clave de autor: el nombre normalizado.
     *
     * @param array $review
     * @return string
     */
    function mu_review_author_key( $review ) {
        return mu_review_normalize( is_array( $review ) ? ( $review['author_name'] ?? '' ) : '' );
    }
}

if ( ! function_exists( 'mu_review_same_author' ) ) {
    /**
     * ¿Es la misma persona?
     *
     * Google suele devolver "Jonatan S." donde a mano se escribió "Jonatan", así
     * que además de la igualdad normalizada se acepta que uno sea el comienzo del
     * otro (mínimo 4 caracteres, para no confundir nombres cortos).
     *
     * @param array $a
     * @param array $b
     * @return bool
     */
    function mu_review_same_author( $a, $b ) {
        $ka = mu_review_author_key( $a );
        $kb = mu_review_author_key( $b );

        if ( '' === $ka || '' === $kb ) {
            return false;
        }

        if ( $ka === $kb ) {
            return true;
        }

        $min = 4;

        if ( strlen( $ka ) >= $min && 0 === strpos( $kb, $ka ) ) {
            return true;
        }

        return ( strlen( $kb ) >= $min && 0 === strpos( $ka, $kb ) );
    }
}

if ( ! function_exists( 'mu_review_identity_key' ) ) {
    /**
     * Huella de contenido: autor + los primeros 80 caracteres del comentario.
     * Se calcula al vuelo (no se guarda en la base: sin migración).
     *
     * @param array $review
     * @return string
     */
    function mu_review_identity_key( $review ) {
        $text = mu_review_normalize( is_array( $review ) ? ( $review['text'] ?? '' ) : '' );

        if ( strlen( $text ) > 80 ) {
            $text = substr( $text, 0, 80 );
        }

        return md5( mu_review_author_key( $review ) . '|' . $text );
    }
}

if ( ! function_exists( 'mu_review_is_flagged_duplicate' ) ) {
    /**
     * ¿Esa reseña quedó marcada como posible duplicado por una importación?
     *
     * @param array $review
     * @return bool
     */
    function mu_review_is_flagged_duplicate( $review ) {
        return is_array( $review ) && ! empty( $review['posible_duplicado'] );
    }
}

if ( ! function_exists( 'mu_reviews_find_same_author' ) ) {
    /**
     * Índices de las reseñas de la base que parecen ser de la misma persona.
     *
     * Lo usa el guardado manual para avisar antes de crear un duplicado.
     *
     * @param array $db
     * @param array $review Reseña nueva.
     * @return int[]
     */
    function mu_reviews_find_same_author( $db, $review ) {
        $hits = [];

        foreach ( (array) $db as $index => $stored ) {
            if ( mu_review_same_author( $review, $stored ) ) {
                $hits[] = $index;
            }
        }

        return $hits;
    }
}

if ( ! function_exists( 'mu_review_link_key' ) ) {
    /**
     * Clave de enlace: identifica al reseñador por la URL de su perfil de Google Maps.
     *
     * En la API de Places el campo `author_url` es el PERFIL del reseñador
     * (…/maps/contrib/<ID>/reviews), no el enlace a la reseña; para un mismo lugar
     * equivale a la misma reseña y no cambia cuando Google reescribe el nombre.
     *
     * Solo se acepta si parece un perfil de contribuciones: un enlace genérico (del
     * lugar o de la reseña) lo compartirían todas las reseñas y usarlo como
     * identidad borraría contenido por error. Devuelve '' si no sirve.
     *
     * @param array $review
     * @return string
     */
    function mu_review_link_key( $review ) {
        if ( ! is_array( $review ) ) {
            return '';
        }

        $url = '';

        foreach ( [ 'author_url', 'profile_url' ] as $field ) {
            if ( ! empty( $review[ $field ] ) ) {
                $url = (string) $review[ $field ];
                break;
            }
        }

        if ( '' === $url ) {
            return '';
        }

        $parts = parse_url( $url );

        if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
            return '';
        }

        // Se usa el ID del perfil: ignora /reviews, query y fragmento.
        if ( ! preg_match( '#maps/contrib/([0-9a-z_\-]+)#i', (string) ( $parts['path'] ?? '' ), $match ) ) {
            return '';
        }

        $host = strtolower( preg_replace( '/^www\./', '', (string) $parts['host'] ) );

        return $host . '/maps/contrib/' . strtolower( $match[1] );
    }
}

if ( ! function_exists( 'mu_review_absorb' ) ) {
    /**
     * La reseña de Google se queda con lo que la manual tenía y Google no trae: foto
     * de compra y traducciones (con su marca de origen, para que el aviso de
     * "traducción desactualizada" siga funcionando). Nunca copia rating ni fecha:
     * manda Google.
     *
     * @param array $item
     * @param array $old
     * @param bool  $with_hidden Hereda `hidden` (solo cuando la manual se elimina).
     * @return array
     */
    function mu_review_absorb( $item, $old, $with_hidden = false ) {
        $old = is_array( $old ) ? $old : [];

        if ( empty( $item['purchase_photo_url'] ) && ! empty( $old['purchase_photo_url'] ) ) {
            $item['purchase_photo_url'] = $old['purchase_photo_url'];
        }

        foreach ( [ 'pt', 'en' ] as $lang ) {
            $key = 'text_' . $lang;

            if ( empty( $item[ $key ] ) && ! empty( $old[ $key ] ) ) {
                $item[ $key ]           = $old[ $key ];
                $item[ $key . '_hash' ] = (string) ( $old[ $key . '_hash' ] ?? '' );
            }
        }

        if ( $with_hidden && ! empty( $old['hidden'] ) ) {
            $item['hidden'] = true;
        }

        return $item;
    }
}

if ( ! function_exists( 'mu_reviews_import_merge' ) ) {
    /**
     * Importa un lote de reseñas de Google sobre la base actual (deduplicado).
     *
     * Reglas, en orden:
     *   1. misma reseña exacta (`time` + `author_name`) → se omite, sin marcas;
     *   2. MISMO ENLACE de perfil (con /maps/contrib/)   → la de Google se conserva,
     *      absorbe traducciones/foto/`hidden` de la manual y la manual SE ELIMINA
     *      (el enlace es identidad: decisión del mantenedor). No borra nada si el
     *      enlace está repetido en 3+ reseñas (señal de link genérico): ahí cae al
     *      punto 3;
     *   3. mismo autor (normalizado) o texto idéntico   → se agrega la de Google y
     *      la vieja queda marcada (`posible_duplicado`), sin borrar;
     *   4. lo que está en la lista de exclusiones       → no se reimporta;
     *   5. el resto                                     → se agrega normal.
     *
     * @param array $db      Reseñas almacenadas.
     * @param array $batch   Reseñas del proveedor.
     * @param int   $max     Tope de reseñas guardadas (0 = sin tope).
     * @param array $blocked Claves (enlace/huella) que no deben reimportarse.
     * @return array{db:array,added:int,existed:int,flagged:array,absorbed:array,deleted:int,skipped:int}
     */
    function mu_reviews_import_merge( $db, $batch, $max = 200, $blocked = [] ) {
        $db       = is_array( $db ) ? $db : [];
        $batch    = is_array( $batch ) ? $batch : [];
        $blocked  = is_array( $blocked ) ? $blocked : [];
        $added    = 0;
        $existed  = 0;
        $flagged  = [];
        $absorbed = [];
        $deleted  = 0;
        $skipped  = 0;

        foreach ( $batch as $item ) {
            if ( intval( $item['rating'] ?? 0 ) < 5 ) {
                continue;
            }

            // Lo que se borró a mano no se revive: el import semanal volvería a
            // traerlo, porque Google devuelve siempre las mismas reseñas.
            $item_link = mu_review_link_key( $item );

            if ( $blocked ) {
                if ( ( '' !== $item_link && isset( $blocked[ $item_link ] ) )
                    || isset( $blocked[ mu_review_identity_key( $item ) ] ) ) {
                    $skipped++;
                    continue;
                }
            }

            // ¿El enlace sirve como identidad? Repetido en 3+ reseñas = sospechoso.
            $link_usable = false;

            if ( '' !== $item_link ) {
                $con_mismo_link = 0;

                foreach ( $db as $row ) {
                    if ( $item_link === mu_review_link_key( $row ) ) {
                        $con_mismo_link++;
                    }
                }

                $link_usable = ( $con_mismo_link < 3 );
            }

            $is_same    = false;
            $same_link  = [];
            $superseded = [];

            foreach ( $db as $index => $stored ) {
                if ( ! is_array( $stored ) ) {
                    continue;
                }

                if ( isset( $stored['time'], $stored['author_name'] )
                    && $stored['time'] == ( $item['time'] ?? null )
                    && $stored['author_name'] === ( $item['author_name'] ?? null ) ) {
                    $is_same = true;
                    break;
                }

                if ( $link_usable && $item_link === mu_review_link_key( $stored ) ) {
                    $same_link[] = $index;
                    continue; // el enlace manda: no se evalúa por nombre
                }

                if ( mu_review_same_author( $item, $stored )
                    || mu_review_identity_key( $item ) === mu_review_identity_key( $stored ) ) {
                    $superseded[] = $index;
                }
            }

            if ( $is_same ) {
                $existed++;
                continue;
            }

            // [Enlace] Misma reseña: la de Google manda y la manual se elimina.
            foreach ( $same_link as $index ) {
                $old = is_array( $db[ $index ] ) ? $db[ $index ] : [];

                $item = mu_review_absorb( $item, $old, true );

                $absorbed[] = [
                    'index' => $index,
                    'name'  => (string) ( $old['author_name'] ?? '' ),
                ];

                $db[ $index ] = null; // se descarta al final
                $deleted++;
            }

            // [Sin enlace confiable] Mismo autor o texto idéntico: se agrega la de
            // Google y la vieja queda marcada (no se borra: lo decide el mantenedor).
            foreach ( $superseded as $index ) {
                $old = is_array( $db[ $index ] ) ? $db[ $index ] : [];

                $item = mu_review_absorb( $item, $old, false );

                $db[ $index ]['posible_duplicado'] = true;

                $flagged[] = [
                    'index' => $index,
                    'name'  => (string) ( $old['author_name'] ?? '' ),
                ];
            }

            $db[] = $item;
            $added++;
        }

        // Fuera las absorbidas (quedaron en null al marcarlas para eliminar).
        if ( $deleted > 0 ) {
            $db = array_values( array_filter( $db, function ( $row ) {
                return null !== $row;
            } ) );
        }

        if ( $added > 0 && $max > 0 && count( $db ) > $max ) {
            $db = array_slice( $db, -$max );
        }

        return [
            'db'       => $db,
            'added'    => $added,
            'existed'  => $existed,
            'flagged'  => $flagged,
            'absorbed' => $absorbed,
            'deleted'  => $deleted,
            'skipped'  => $skipped,
        ];
    }
}

