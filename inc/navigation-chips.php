<?php
/**
 * Muy Únicos - Chips de Navegación v8 (Índice Compacto)
 *
 * Sistema ultra-optimizado con índice compacto de productos.
 * Calcula conteos dinámicos y etiquetas compartidas sin consultas SQL repetidas.
 *
 * Responsable de:
 * - Desactivar breadcrumbs nativos de WooCommerce / GP.
 * - Renderizar breadcrumb global basado en chips.
 * - Construir y analizar índice compacto de productos (vía WP Cron).
 * - Renderizar chips de categorías y etiquetas con conteos.
 * - Herramientas de administración para regenerar índice.
 *
 * Carga: global, pero toda la lógica pesada se ejecuta sólo en
 * contexto WooCommerce de catálogo (shop / category / tag / product).
 *
 * @package GeneratePress_Child
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// =========================================================================
// 0. CONFIGURACIÓN E INICIALIZACIÓN (BREADCRUMBS)
// =========================================================================

if ( ! function_exists( 'mu_navchips_remove_wc_breadcrumbs' ) ) {
    function mu_navchips_remove_wc_breadcrumbs() {
        if ( ! function_exists( 'is_woocommerce' ) || ! is_woocommerce() ) {
            return;
        }

        remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
        add_filter( 'woocommerce_show_breadcrumb', '__return_false' );
    }
    add_action( 'wp', 'mu_navchips_remove_wc_breadcrumbs' );
}

// =========================================================================
// 1. DISPARADORES Y SCHEDULE (CRON JOBS)
// =========================================================================

if ( ! function_exists( 'mu_navchips_schedule_index_rebuild' ) ) {
    function mu_navchips_schedule_index_rebuild( $post_id = null ) {
        if ( $post_id && get_post_type( $post_id ) !== 'product' ) {
            return;
        }

        if ( ! wp_next_scheduled( 'mu_navchips_rebuild_index_hook' ) ) {
            wp_schedule_single_event( time() + 30, 'mu_navchips_rebuild_index_hook' );
        }
    }

    add_action( 'save_post_product',       'mu_navchips_schedule_index_rebuild', 10, 3 );
    add_action( 'edited_product_cat',      'mu_navchips_schedule_index_rebuild' );
    add_action( 'edited_product_tag',      'mu_navchips_schedule_index_rebuild' );
    add_action( 'delete_post',             'mu_navchips_schedule_index_rebuild' );
    add_action( 'created_product_cat',     'mu_navchips_schedule_index_rebuild' );
    add_action( 'delete_product_cat',      'mu_navchips_schedule_index_rebuild' );
    add_action( 'woocommerce_new_product', 'mu_navchips_schedule_index_rebuild' );
}

add_action( 'mu_navchips_rebuild_index_hook', 'mu_navchips_build_product_index' );

// =========================================================================
// 2. GENERACIÓN DEL ÍNDICE COMPACTO (BACKEND)
// =========================================================================

if ( ! function_exists( 'mu_navchips_build_product_index' ) ) {
    /**
     * Genera el índice compacto de productos.
     * Formato: "pid:cats:tags|pid:cats:tags|..."
     * Ejemplo: "12:8,3:12,1,4|13:8:5,6".
     */
    function mu_navchips_build_product_index() {
        global $wpdb;

        $start_time = microtime( true );

        $sql = "
            SELECT
                p.ID as product_id,
                GROUP_CONCAT(
                    CASE
                        WHEN tt.taxonomy = 'product_cat' THEN tt.term_id
                        ELSE NULL
                    END
                ) as cat_ids,
                GROUP_CONCAT(
                    CASE
                        WHEN tt.taxonomy = 'product_tag' THEN tt.term_id
                        ELSE NULL
                    END
                ) as tag_ids
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->term_relationships} tr ON (p.ID = tr.object_id)
            LEFT JOIN {$wpdb->term_taxonomy} tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id)
            WHERE p.post_type = 'product'
              AND p.post_status = 'publish'
            GROUP BY p.ID
            HAVING cat_ids IS NOT NULL OR tag_ids IS NOT NULL
        ";

        $results         = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $index_parts     = [];
        $total_products  = 0;

        foreach ( $results as $row ) {
            $cats = $row->cat_ids ? array_filter( explode( ',', $row->cat_ids ) ) : [];
            $tags = $row->tag_ids ? array_filter( explode( ',', $row->tag_ids ) ) : [];

            $cats_str = ! empty( $cats ) ? implode( ',', array_unique( $cats ) ) : '';
            $tags_str = ! empty( $tags ) ? implode( ',', array_unique( $tags ) ) : '';

            $index_parts[] = $row->product_id . ':' . $cats_str . ':' . $tags_str;
            $total_products++;
        }

        $compact_index = implode( '|', $index_parts );

        $metadata = [
            'total_products'     => $total_products,
            'index_size_bytes'   => strlen( $compact_index ),
            'generated_at'       => current_time( 'mysql' ),
            'generation_time_ms' => round( ( microtime( true ) - $start_time ) * 1000, 2 ),
        ];

        set_transient( 'mu_navchips_product_index',  $compact_index, 30 * DAY_IN_SECONDS );
        set_transient( 'mu_navchips_index_metadata', $metadata,      30 * DAY_IN_SECONDS );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                sprintf(
                    'MU NavChips Index rebuilt: %d products, %s KB, %s ms',
                    $total_products,
                    number_format( strlen( $compact_index ) / 1024, 2 ),
                    $metadata['generation_time_ms']
                )
            );
        }
    }
}

// =========================================================================
// 3. FUNCIONES DE ANÁLISIS DEL ÍNDICE
// =========================================================================

if ( ! function_exists( 'mu_navchips_parse_product_index' ) ) {
    /**
     * Parsea el índice compacto y devuelve estructura PHP.
     *
     * Si el transient expiró, programa un rebuild vía cron y devuelve
     * un array vacío para no bloquear la petición del visitante.
     *
     * @return array {
     *   @type array $products        Mapa pid => [ 'cats' => [], 'tags' => [] ].
     *   @type array $cat_to_products Mapa cat_id => [ product_ids ].
     *   @type array $tag_to_products Mapa tag_id => [ product_ids ].
     * }
     */
    function mu_navchips_parse_product_index() {
        static $parsed_cache = null;

        if ( null !== $parsed_cache ) {
            return $parsed_cache;
        }

        $index = get_transient( 'mu_navchips_product_index' );

        if ( false === $index ) {
            // [Fix #1] No reconstruir inline: programar cron y devolver vacío.
            // El próximo visitante (tras el siguiente cron, máx 10 min) verá los chips.
            if ( ! wp_next_scheduled( 'mu_navchips_rebuild_index_hook' ) ) {
                wp_schedule_single_event( time(), 'mu_navchips_rebuild_index_hook' );
            }

            $parsed_cache = [
                'products'        => [],
                'cat_to_products' => [],
                'tag_to_products' => [],
            ];

            return $parsed_cache;
        }

        if ( empty( $index ) ) {
            return [
                'products'        => [],
                'cat_to_products' => [],
                'tag_to_products' => [],
            ];
        }

        $products        = [];
        $cat_to_products = [];
        $tag_to_products = [];

        $entries = explode( '|', $index );

        foreach ( $entries as $entry ) {
            $parts = explode( ':', $entry );
            if ( 3 !== count( $parts ) ) {
                continue;
            }

            list( $pid, $cats_str, $tags_str ) = $parts;

            $pid  = (int) $pid;
            $cats = $cats_str ? array_map( 'intval', explode( ',', $cats_str ) ) : [];
            $tags = $tags_str ? array_map( 'intval', explode( ',', $tags_str ) ) : [];

            $products[ $pid ] = [
                'cats' => $cats,
                'tags' => $tags,
            ];

            foreach ( $cats as $cid ) {
                $cat_to_products[ $cid ][] = $pid;
            }

            foreach ( $tags as $tid ) {
                $tag_to_products[ $tid ][] = $pid;
            }
        }

        $parsed_cache = [
            'products'        => $products,
            'cat_to_products' => $cat_to_products,
            'tag_to_products' => $tag_to_products,
        ];

        return $parsed_cache;
    }
}

if ( ! function_exists( 'mu_navchips_get_products_in_category_tree' ) ) {
    /**
     * Obtiene productos únicos en una categoría y todas sus subcategorías.
     *
     * @param int $cat_id ID de categoría.
     * @return array IDs de productos únicos.
     */
    function mu_navchips_get_products_in_category_tree( $cat_id ) {
        $index            = mu_navchips_parse_product_index();
        $cat_to_products  = $index['cat_to_products'];
        $cat_tree         = array_merge( [ $cat_id ], get_term_children( $cat_id, 'product_cat' ) ?: [] );
        $product_ids      = [];

        foreach ( $cat_tree as $cid ) {
            if ( isset( $cat_to_products[ $cid ] ) ) {
                $product_ids = array_merge( $product_ids, $cat_to_products[ $cid ] );
            }
        }

        return array_unique( $product_ids );
    }
}

if ( ! function_exists( 'mu_navchips_calculate_tag_stats' ) ) {
    /**
     * Calcula estadísticas de etiquetas para un contexto dado.
     *
     * @param array $product_ids     IDs de productos a analizar.
     * @param array $active_tag_ids  Tags actualmente seleccionados.
     * @return array Mapa tag_id => [ 'count' => N, 'shared_count' => M ].
     */
    function mu_navchips_calculate_tag_stats( $product_ids, $active_tag_ids = [] ) {
        if ( empty( $product_ids ) ) {
            return [];
        }

        $index    = mu_navchips_parse_product_index();
        $products = $index['products'];
        $tag_stats = [];

        if ( ! empty( $active_tag_ids ) ) {
            $products_with_all_active = [];

            foreach ( $product_ids as $pid ) {
                if ( ! isset( $products[ $pid ] ) ) {
                    continue;
                }

                $product_tags = $products[ $pid ]['tags'];

                if ( count( array_intersect( $active_tag_ids, $product_tags ) ) === count( $active_tag_ids ) ) {
                    $products_with_all_active[] = $pid;
                }
            }

            foreach ( $products_with_all_active as $pid ) {
                $product_tags = $products[ $pid ]['tags'];

                foreach ( $product_tags as $tid ) {
                    if ( ! isset( $tag_stats[ $tid ] ) ) {
                        $tag_stats[ $tid ] = [ 'count' => 0, 'shared_count' => 0 ];
                    }
                    $tag_stats[ $tid ]['shared_count']++;
                }
            }

            foreach ( $product_ids as $pid ) {
                if ( ! isset( $products[ $pid ] ) ) {
                    continue;
                }

                foreach ( $products[ $pid ]['tags'] as $tid ) {
                    if ( ! isset( $tag_stats[ $tid ] ) ) {
                        $tag_stats[ $tid ] = [ 'count' => 0, 'shared_count' => 0 ];
                    }
                    $tag_stats[ $tid ]['count']++;
                }
            }
        } else {
            foreach ( $product_ids as $pid ) {
                if ( ! isset( $products[ $pid ] ) ) {
                    continue;
                }

                foreach ( $products[ $pid ]['tags'] as $tid ) {
                    if ( ! isset( $tag_stats[ $tid ] ) ) {
                        $tag_stats[ $tid ] = [ 'count' => 0, 'shared_count' => 0 ];
                    }
                    $tag_stats[ $tid ]['count']++;
                }
            }
        }

        return $tag_stats;
    }
}

// =========================================================================
// 4. FUNCIONES HELPER & FRONTEND
// =========================================================================

if ( ! function_exists( 'mu_navchips_is_restricted_user' ) ) {
    function mu_navchips_is_restricted_user() {
        if ( current_user_can( 'manage_woocommerce' ) ) {
            return false;
        }

        if ( function_exists( 'WC' ) && WC()->customer ) {
            $country = WC()->customer->get_billing_country();
            if ( ! empty( $country ) ) {
                return ( 'AR' !== $country );
            }
        }

        return false;
    }
}

if ( ! function_exists( 'mu_navchips_get_share_button_html' ) ) {
    function mu_navchips_get_share_button_html() {
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
}

// =========================================================================
// 5. BREADCRUMB GLOBAL (CHIPS)
// =========================================================================

if ( ! function_exists( 'mu_navchips_render_global_breadcrumb' ) ) {
    function mu_navchips_render_global_breadcrumb() {
        // [Fix #breadcrumb-1] Flag estático: garantiza render único sin importar
        // el orden de disparo entre woocommerce_before_main_content y generate_before_content.
        static $rendered = false;
        if ( $rendered ) {
            return;
        }

        if ( is_front_page() || is_cart() || is_checkout() ) {
            return;
        }

        $rendered = true;

        $home_url  = home_url();
        $is_woo    = function_exists( 'is_woocommerce' ) && is_woocommerce();
        $share_btn = mu_navchips_get_share_button_html();

        $icon_home    = '<span class="mu-navchips-icon-home">' . mu_get_icon( 'home' ) . '</span>';
        $icon_context = $is_woo
            ? '<span class="mu-navchips-icon-context mu-navchips-icon-context--shop">' . mu_get_icon( 'cart' ) . '</span>'
            : '<span class="mu-navchips-icon-context mu-navchips-icon-context--blog">' . mu_get_icon( 'book' ) . '</span>';

        $context_url        = $is_woo ? wc_get_page_permalink( 'shop' ) : ( get_post_type_archive_link( 'post' ) ?: $home_url );
        $current_tags_slugs = isset( $_GET['product_tag'] ) ? array_filter( explode( ' ', str_replace( '+', ' ', wp_unslash( $_GET['product_tag'] ) ) ) ) : [];
        $is_filtered_view   = ! empty( $current_tags_slugs );

        $ancestors_html    = '';
        $current_item_html = '';

        if ( $is_woo ) {
            if ( is_product() ) {
                global $post;
                $terms = get_the_terms( $post->ID, 'product_cat' );
                if ( $terms && ! is_wp_error( $terms ) ) {
                    $main_term = $terms[0];
                    $ancestors = array_reverse( get_ancestors( $main_term->term_id, 'product_cat' ) );
                    foreach ( $ancestors as $aid ) {
                        $term = get_term( $aid, 'product_cat' );
                        if ( $term ) {
                            $ancestors_html .= '<li class="mu-navchips-crumb"><a href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( $term->name ) . '</a></li>';
                        }
                    }
                    $ancestors_html .= '<li class="mu-navchips-crumb"><a href="' . esc_url( get_term_link( $main_term ) ) . '">' . esc_html( $main_term->name ) . '</a></li>';
                }
                $current_item_html = '<li class="mu-navchips-current"><span>' . get_the_title() . $share_btn . '</span></li>';
            } elseif ( is_product_category() ) {
                $obj       = get_queried_object();
                $ancestors = array_reverse( get_ancestors( $obj->term_id, 'product_cat' ) );
                foreach ( $ancestors as $aid ) {
                    $term = get_term( $aid, 'product_cat' );
                    if ( $term ) {
                        $ancestors_html .= '<li class="mu-navchips-crumb"><a href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( $term->name ) . '</a></li>';
                    }
                }

                if ( $is_filtered_view ) {
                    $ancestors_html .= '<li class="mu-navchips-crumb"><a href="' . esc_url( get_term_link( $obj ) ) . '">' . esc_html( $obj->name ) . '</a></li>';
                    $tag_names = [];
                    foreach ( $current_tags_slugs as $slug ) {
                        $t = get_term_by( 'slug', $slug, 'product_tag' );
                        if ( $t ) {
                            $tag_names[] = $t->name;
                        }
                    }
                    $display_name      = ! empty( $tag_names ) ? implode( ' + ', $tag_names ) : 'Filtros';
                    $current_item_html = '<li class="mu-navchips-current mu-navchips-current--tag"><span>' . esc_html( $display_name ) . $share_btn . '</span></li>';
                } else {
                    $current_item_html = '<li class="mu-navchips-current"><span>' . esc_html( $obj->name ) . $share_btn . '</span></li>';
                }
            } elseif ( is_product_tag() ) {
                $obj = get_queried_object();
                if ( count( $current_tags_slugs ) > 1 ) {
                    $current_item_html = '<li class="mu-navchips-current mu-navchips-current--tag"><span>' . esc_html( $obj->name . ' +' ) . $share_btn . '</span></li>';
                } else {
                    $current_item_html = '<li class="mu-navchips-current mu-navchips-current--tag"><span>' . esc_html( $obj->name ) . $share_btn . '</span></li>';
                }
            } elseif ( is_shop() ) {
                $current_item_html = '<li class="mu-navchips-current"><span>Tienda' . $share_btn . '</span></li>';
            }
        } else {
            $title = is_archive() ? get_the_archive_title() : get_the_title();
            if ( is_home() ) {
                $title = 'Blog';
            }
            $current_item_html = '<li class="mu-navchips-current"><span>' . esc_html( $title ) . $share_btn . '</span></li>';
        }

        echo '<nav class="mu-navchips-breadcrumb" aria-label="Breadcrumb"><ul>';
        echo '<li class="mu-navchips-icon-link"><a href="' . esc_url( $home_url ) . '" title="Inicio">' . $icon_home . '</a></li>';
        echo '<li class="mu-navchips-icon-link mu-navchips-icon-link--context"><a href="' . esc_url( $context_url ) . '">' . $icon_context . '</a></li>';
        echo $ancestors_html;
        echo $current_item_html;
        echo '</ul></nav>';
    }

    add_action( 'woocommerce_before_main_content', 'mu_navchips_render_global_breadcrumb', 20 );
    add_action( 'generate_before_content', 'mu_navchips_render_global_breadcrumb', 10 );
}

// =========================================================================
// 6. CHIPS DE NAVEGACIÓN (CATALOGO)
// =========================================================================

if ( ! function_exists( 'mu_navchips_render_navigation_chips' ) ) {
    function mu_navchips_render_navigation_chips() {
        if ( is_product() || is_cart() || is_checkout() ) {
            return;
        }

        $is_restricted = mu_navchips_is_restricted_user();
        $allowed_cats  = $is_restricted ? get_option( 'muyu_digital_category_ids', [] ) : [];
        $allowed_tags  = $is_restricted ? get_option( 'muyu_digital_tag_ids', [] ) : [];

        $current_cat_id     = is_product_category() ? get_queried_object_id() : 0;
        $current_tags_slugs = isset( $_GET['product_tag'] ) ? array_filter( explode( ' ', str_replace( '+', ' ', wp_unslash( $_GET['product_tag'] ) ) ) ) : [];

        $active_tag_ids = [];
        foreach ( $current_tags_slugs as $slug ) {
            $t = get_term_by( 'slug', $slug, 'product_tag' );
            if ( $t ) {
                $active_tag_ids[] = $t->term_id;
            }
        }

                // --- A. CATEGORÍAS ---
        $cats_to_show              = [];
        $categorias_a_omitir_slugs = [ 'sin-categorizar' ];

        $extra_slugs = $is_restricted ? [ 'digital' ] : [ 'digital', 'outlet' ];
        $extra_terms = [];
        foreach ( $extra_slugs as $slug ) {
            $t = get_term_by( 'slug', $slug, 'product_cat' );
            if ( $t && ( ! $is_restricted || in_array( $t->term_id, (array) $allowed_cats, true ) ) ) {
                $extra_terms[] = $t;
            }
        }

        // [Fix #2] hide_empty => true: evita cargar categorías sin productos en memoria.
        $cats_query = get_terms(
            [
                'taxonomy'   => 'product_cat',
                'hide_empty' => true,
                'orderby'    => 'count',
                'order'      => 'DESC',
            ]
        );

        foreach ( $cats_query as $cat ) {
            if ( $is_restricted && ! empty( $allowed_cats ) && ! in_array( $cat->term_id, (array) $allowed_cats, true ) ) {
                continue;
            }

            if ( in_array( $cat->slug, $categorias_a_omitir_slugs, true ) ) {
                continue;
            }

            // Verificar que la categoría tenga al menos un producto visible en catálogo.
            // Necesario porque hide_empty=true cuenta productos ocultos (exclude-from-catalog).
            // Solo aplicar el filtro digital si el usuario está restringido
            if ( $is_restricted && function_exists( 'muyu_digital_restriction_init' ) ) {
                $restriction = muyu_digital_restriction_init();
                if ( method_exists( $restriction, 'category_has_visible_digital_products' ) ) {
                    if ( ! $restriction->category_has_visible_digital_products( $cat->term_id ) ) {
                        continue;
                    }
                }
            }

            foreach ( $extra_terms as $ex ) {
                if ( $ex->term_id === $cat->term_id ) {
                    continue 2;
                }
            }

            if ( $current_cat_id ) {
                if ( (int) $cat->parent === (int) $current_cat_id ) {
                    $cats_to_show[] = $cat;
                }
            } else {
                if ( 0 === (int) $cat->parent ) {
                    $cats_to_show[] = $cat;
                }
            }
        }

        // --- B. TAGS (CON CONTEOS) ---
        $context_product_ids = [];

        if ( $current_cat_id > 0 ) {
            $context_product_ids = mu_navchips_get_products_in_category_tree( $current_cat_id );
        } else {
            $index               = mu_navchips_parse_product_index();
            $context_product_ids = array_keys( $index['products'] );
        }

        // Para usuarios restringidos, limitar los product_ids al set de digitales
        // para que los conteos de tags reflejen lo que el usuario puede ver.
        if ( $is_restricted ) {
            $digital_ids         = array_map( 'intval', (array) get_option( 'muyu_digital_product_ids', [] ) );
            $context_product_ids = array_intersect( $context_product_ids, $digital_ids );
        }

        $tag_stats = mu_navchips_calculate_tag_stats( $context_product_ids, $active_tag_ids );

        if ( $is_restricted && ! empty( $allowed_tags ) ) {
            $tag_stats = array_intersect_key( $tag_stats, array_flip( (array) $allowed_tags ) );
        }

        $sort_key = ! empty( $active_tag_ids ) ? 'shared_count' : 'count';
        uasort(
            $tag_stats,
            function( $a, $b ) use ( $sort_key ) {
                return $b[ $sort_key ] <=> $a[ $sort_key ];
            }
        );

        $tags_to_show  = [];
        $tags_a_omitir = [ 'descargable', 'adhesivos', 'planchas-de-stickers' ];
        $processed     = 0;
        $max_tags      = 30;

        // [Fix #3] Pre-cargar hasta $max_tags términos en una sola query al object cache.
        _prime_term_caches( array_keys( array_slice( $tag_stats, 0, $max_tags, true ) ) );

        foreach ( $tag_stats as $tag_id => $stats ) {
            if ( $processed >= $max_tags && ! in_array( $tag_id, $active_tag_ids, true ) ) {
                break;
            }

            $tag = get_term( $tag_id, 'product_tag' );
            if ( ! $tag || in_array( $tag->slug, $tags_a_omitir, true ) ) {
                continue;
            }

            $tag->product_count = $stats['count'];
            $tag->shared_count  = $stats['shared_count'];
            $tag->is_active     = in_array( $tag_id, $active_tag_ids, true );

            $tags_to_show[] = $tag;
            $processed++;
        }

        if ( empty( $cats_to_show ) && empty( $extra_terms ) && empty( $tags_to_show ) ) {
            return;
        }

        echo "<div class='mu-navchips-wrapper navigation-chips'>";

        // Categorías
        if ( ! empty( $cats_to_show ) || ! empty( $extra_terms ) ) {
            echo "<div class='mu-navchips-label'>Explorar categorías:</div>";
            echo "<ul class='mu-navchips-list mu-navchips-list--cats'>";

            $shop_url = wc_get_page_permalink( 'shop' );
            echo "<li class='mu-navchips-chip mu-navchips-chip--reset'><a href='" . esc_url( $shop_url ) . "'>Todas</a></li>";

            $max_cats = 4;
            $c        = 0;

            foreach ( $extra_terms as $term ) {
                if ( $term->term_id === $current_cat_id ) {
                    continue;
                }
                echo "<li class='mu-navchips-chip mu-navchips-chip--extra'><a href='" . esc_url( get_term_link( $term ) ) . "'>" . esc_html( $term->name ) . "</a></li>";
            }

            foreach ( $cats_to_show as $cat ) {
                if ( $cat->term_id === $current_cat_id ) {
                    continue;
                }

                $classes = 'mu-navchips-chip mu-navchips-chip--cat';
                $style   = '';

                if ( $c >= $max_cats ) {
                    $classes .= ' mu-navchips-chip--cat-hidden';
                    $style    = " style='display:none;'";
                }

                $html = "<li class='" . esc_attr( $classes ) . "'" . $style . "><a href='" . esc_url( get_term_link( $cat ) ) . "'>" . esc_html( $cat->name ) . "</a></li>";

                echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                $c++;
            }

            if ( $c > $max_cats ) {
                echo "<li class='mu-navchips-chip mu-navchips-chip--more'><button type='button' class='mu-navchips-more-btn' data-target='cats'>Más</button></li>";
            }

            echo '</ul>';
        }

        // Tags
        if ( ! empty( $tags_to_show ) ) {
            $label = ! empty( $active_tag_ids ) ? 'Etiquetas compartidas:' : 'Explorar temáticas:';

            echo "<div class='mu-navchips-label mu-navchips-label--tags'>" . esc_html( $label ) . '</div>';
            echo "<ul class='mu-navchips-list mu-navchips-list--tags'>";

            $base_url = $current_cat_id ? get_term_link( $current_cat_id, 'product_cat' ) : wc_get_page_permalink( 'shop' );

            if ( ! empty( $active_tag_ids ) ) {
                echo "<li class='mu-navchips-chip mu-navchips-chip--reset-tags'><a href='" . esc_url( $base_url ) . "'>Mostrar Todas</a></li>";
            }

            $max_visible = 8;
            $t_count     = 0;

            foreach ( $tags_to_show as $tag ) {
                $is_active = $tag->is_active;

                $display_count = ! empty( $active_tag_ids ) ? $tag->shared_count : $tag->product_count;
                $is_disabled   = ! empty( $active_tag_ids ) && ! $is_active && 0 === (int) $tag->shared_count;

                $new_slugs = $current_tags_slugs;
                if ( $is_active ) {
                    $new_slugs = array_diff( $new_slugs, [ $tag->slug ] );
                } else {
                    $new_slugs[] = $tag->slug;
                }

                $link = empty( $new_slugs ) ? $base_url : add_query_arg( 'product_tag', implode( '+', array_unique( $new_slugs ) ), $base_url );

                $classes    = 'mu-navchips-chip mu-navchips-chip--tag';
                $force_show = $is_active;

                if ( $is_active ) {
                    $classes .= ' mu-navchips-chip--active';
                }

                if ( $is_disabled ) {
                    $classes .= ' mu-navchips-chip--disabled';
                }

                if ( ! $force_show && $t_count >= $max_visible ) {
                    $classes .= ' mu-navchips-chip--tag-hidden';
                }

                $tag_label = esc_html( $tag->name ) . ' <span class="mu-navchips-count">' . (int) $display_count . '</span>';

                $html  = "<li class='" . esc_attr( $classes ) . "'" . ( strpos( $classes, 'mu-navchips-chip--tag-hidden' ) !== false ? " style='display:none;'" : '' ) . '>';
                if ( $is_disabled ) {
                    $html .= "<span class='mu-navchips-chip-link mu-navchips-chip-link--disabled'>" . $tag_label . '</span>';
                } else {
                    $html .= "<a class='mu-navchips-chip-link' href='" . esc_url( $link ) . "'>" . $tag_label . '</a>';
                }
                $html .= '</li>';

                echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

                $t_count++;
            }

            if ( count( $tags_to_show ) > $max_visible ) {
                echo "<li class='mu-navchips-chip mu-navchips-chip--more-tags'><button type='button' class='mu-navchips-more-btn' data-target='tags'>Más</button></li>";
            }

            echo '</ul>';
        }

        echo '</div>';
    }

    add_action( 'woocommerce_before_shop_loop', 'mu_navchips_render_navigation_chips', 15 );
}
