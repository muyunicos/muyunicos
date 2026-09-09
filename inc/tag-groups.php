<?php
/**
 * Muy Únicos - Sistema de Agrupamiento por Etiquetas
 *
 * Sistema que agrupa productos por etiquetas en categorías con muchos productos.
 * Muestra cards de 4 imágenes que navegan a etiquetas específicas.
 *
 * @package GeneratePress_Child
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================
// CONFIGURACIÓN DE GRUPOS (DINÁMICA)
// ============================================

if ( ! function_exists( 'mu_get_tag_groups_config' ) ) {
    /**
     * Obtiene la configuración de grupos desde wp_options.
     *
     * @return array Configuración: [ 'cat_slug' => [ 'tag_slug', ... ], ... ]
     */
    function mu_get_tag_groups_config() {
        $config = get_option( 'mu_tag_groups_config', [] );

        if ( ! is_array( $config ) ) {
            $config = [];
        }

        return $config;
    }
}

if ( ! function_exists( 'mu_get_tag_groups_index' ) ) {
    /**
     * Obtiene el índice de grupos de etiquetas con fallback resiliente.
     *
     * Memcached puede fallar silenciosamente (evicción o límite de tamaño).
     * Si el transient no existe, lee la option permanente escrita por
     * mu_build_tag_groups_index() en navigation-chips.php.
     *
     * @return array|false Índice de grupos o false si no existe.
     */
    function mu_get_tag_groups_index() {
        $index = get_transient( 'mu_tag_groups_index' );

        if ( false === $index ) {
            $index = get_option( '_mu_navchips_permanent_mu_tag_groups_index', false );
        }

        return $index;
    }
}

if ( ! function_exists( 'mu_save_tag_groups_config' ) ) {
    /**
     * Guarda la configuración de grupos y programa el rebuild asíncrono.
     *
     * El rebuild se delega a WP Cron (+5s) para no bloquear la request
     * del admin. Si el rebuild falla y devuelve un índice vacío, el guard
     * de mu_build_tag_groups_index() conserva el índice anterior (ver
     * inc/navigation-chips.php).
     *
     * @param array $config Nueva configuración.
     */
    function mu_save_tag_groups_config( $config ) {
        update_option( 'mu_tag_groups_config', $config );

        // Rebuild asíncrono del índice de grupos (+5s)
        if ( ! wp_next_scheduled( 'mu_tag_groups_rebuild_hook' ) ) {
            wp_schedule_single_event( time() + 5, 'mu_tag_groups_rebuild_hook' );
        }

        // También reconstruir el índice de navegación (+5s) para que los
        // chips reflejen la nueva configuración de grupos.
        if ( ! wp_next_scheduled( 'mu_navchips_rebuild_index_hook' ) ) {
            wp_schedule_single_event( time() + 5, 'mu_navchips_rebuild_index_hook' );
        }
    }
}

// Hook de cron para rebuild asíncrono del índice de grupos
if ( ! function_exists( 'mu_tag_groups_async_rebuild' ) ) {
    /**
     * Ejecuta el rebuild del índice de grupos en segundo plano (System Cron).
     */
    function mu_tag_groups_async_rebuild() {
        if ( function_exists( 'mu_build_tag_groups_index' ) ) {
            mu_build_tag_groups_index();
        }
    }
}
add_action( 'mu_tag_groups_rebuild_hook', 'mu_tag_groups_async_rebuild' );

// ============================================
// FUNCIONES HELPER DE WOOCOMMERCE
// ============================================
// Nota: mu_is_woocommerce_active() está definida en functions.php (canónica).
// No duplicar aquí — ver MIGRATION-GUIDE.md.

if ( ! function_exists( 'mu_is_product_category' ) ) {
    /**
     * Wrapper seguro para is_product_category().
     * Usa la función global mu_wc_is_product_category() si está disponible.
     *
     * @return bool True si es página de categoría de producto, false en caso contrario.
     */
    function mu_is_product_category() {
        if ( function_exists( 'mu_wc_is_product_category' ) ) {
            return mu_wc_is_product_category();
        }
        if ( ! function_exists( 'is_product_category' ) ) {
            return false;
        }
        return is_product_category();
    }
}

if ( ! function_exists( 'mu_is_product_tag' ) ) {
    /**
     * Wrapper seguro para is_product_tag().
     * Usa la función global mu_wc_is_product_tag() si está disponible.
     *
     * @return bool True si es página de etiqueta de producto, false en caso contrario.
     */
    function mu_is_product_tag() {
        if ( function_exists( 'mu_wc_is_product_tag' ) ) {
            return mu_wc_is_product_tag();
        }
        if ( ! function_exists( 'is_product_tag' ) ) {
            return false;
        }
        return is_product_tag();
    }
}

// ============================================
// AJAX: TOGGLE DE AGRUPAMIENTO DESDE TAG PAGE
// ============================================

if ( ! function_exists( 'mu_ajax_toggle_tag_group' ) ) {
    /**
     * AJAX handler para activar/desactivar el agrupamiento de un tag.
     * Solo para administradores.
     */
    function mu_ajax_toggle_tag_group() {
        // Verificar nonce y permisos
        if ( ! check_ajax_referer( 'mu_tag_group_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'Nonce inválido.' ] );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => 'No tienes permisos.' ] );
        }

        // Rate limiting: máximo 30 solicitudes por minuto para admin
        if ( ! function_exists( 'mu_ajax_rate_limit_check' ) || ! mu_ajax_rate_limit_check( 'mu_toggle_tag_group', 30, 60 ) ) {
            wp_send_json_error( [ 'message' => 'Demasiadas solicitudes. Por favor, esperá un momento.' ] );
        }

        $tag_slug  = isset( $_POST['tag_slug'] ) ? sanitize_title( $_POST['tag_slug'] ) : '';
        $cat_slug  = isset( $_POST['cat_slug'] ) ? sanitize_title( $_POST['cat_slug'] ) : '';
        $action    = isset( $_POST['toggle_action'] ) ? sanitize_text_field( $_POST['toggle_action'] ) : '';

        if ( empty( $tag_slug ) || empty( $cat_slug ) || ! in_array( $action, [ 'add', 'remove' ], true ) ) {
            wp_send_json_error( [ 'message' => 'Parámetros inválidos.' ] );
        }

        // Verificar que el tag y la categoría existan
        $tag = get_term_by( 'slug', $tag_slug, 'product_tag' );
        $cat = get_term_by( 'slug', $cat_slug, 'product_cat' );

        if ( ! $tag || ! $cat ) {
            wp_send_json_error( [ 'message' => 'Tag o categoría no encontrados.' ] );
        }

        $config = mu_get_tag_groups_config();

        if ( 'add' === $action ) {
            // Agregar tag al grupo de la categoría
            if ( ! isset( $config[ $cat_slug ] ) ) {
                $config[ $cat_slug ] = [];
            }
            if ( ! in_array( $tag_slug, $config[ $cat_slug ], true ) ) {
                $config[ $cat_slug ][] = $tag_slug;
            }
        } else {
            // Remover tag del grupo de la categoría
            if ( isset( $config[ $cat_slug ] ) ) {
                $config[ $cat_slug ] = array_values( array_diff( $config[ $cat_slug ], [ $tag_slug ] ) );
                if ( empty( $config[ $cat_slug ] ) ) {
                    unset( $config[ $cat_slug ] );
                }
            }
        }

        mu_save_tag_groups_config( $config );

        wp_send_json_success( [
            'message' => 'Grupo actualizado correctamente.',
            'config'  => $config,
        ] );
    }
    add_action( 'wp_ajax_mu_toggle_tag_group', 'mu_ajax_toggle_tag_group' );
}

if ( ! function_exists( 'mu_render_tag_group_admin_switch' ) ) {
    /**
     * Renderiza el switch "Agrupar" en páginas de tag o categoría con filtro de tag (solo admin).
     * Se muestra arriba a la izquierda del listado de productos.
     */
    function mu_render_tag_group_admin_switch() {
        // Solo para administradores
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $tag_slug = '';
        $cat_slug = '';

        // CASO 1: Página de categoría con filtro de tag (ej: /stickers/?product_tag=los-simpsons)
        if ( mu_is_product_category() && isset( $_GET['product_tag'] ) ) {
            $current_tags = array_filter( explode( ' ', str_replace( '+', ' ', wp_unslash( $_GET['product_tag'] ) ) ) );
            $tag_slug = sanitize_title( reset( $current_tags ) );
            $cat_obj  = get_queried_object();
            if ( $cat_obj && isset( $cat_obj->slug ) ) {
                $cat_slug = $cat_obj->slug;
            }
        }

        // CASO 2: Página de tag pura (ej: /etiqueta/los-simpsons/)
        if ( empty( $tag_slug ) && mu_is_product_tag() ) {
            $current_tag = get_queried_object();
            if ( $current_tag && isset( $current_tag->slug ) ) {
                $tag_slug = $current_tag->slug;

                // Detectar la categoría desde los parámetros de URL o desde productos
                if ( isset( $_GET['product_cat'] ) ) {
                    $cat_slug = sanitize_title( $_GET['product_cat'] );
                } else {
                    $products = get_posts( [
                        'post_type'      => 'product',
                        'post_status'    => 'publish',
                        'posts_per_page' => 1,
                        'fields'         => 'ids',
                        'tax_query'      => [
                            [
                                'taxonomy' => 'product_tag',
                                'field'    => 'slug',
                                'terms'    => $tag_slug,
                            ],
                        ],
                    ] );
                    if ( ! empty( $products ) ) {
                        $terms = wp_get_post_terms( $products[0], 'product_cat' );
                        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                            $cat_slug = $terms[0]->slug;
                        }
                    }
                }
            }
        }

        // Si no tenemos tag o categoría, salir
        if ( empty( $tag_slug ) || empty( $cat_slug ) ) {
            return;
        }

        // Verificar si el tag ya está agrupado en esta categoría
        $config   = mu_get_tag_groups_config();
        $is_grouped = isset( $config[ $cat_slug ] ) && in_array( $tag_slug, $config[ $cat_slug ], true );

        // Obtener nombre de la categoría para mostrar
        $cat_term = get_term_by( 'slug', $cat_slug, 'product_cat' );
        $cat_name = $cat_term ? $cat_term->name : $cat_slug;

        $nonce = wp_create_nonce( 'mu_tag_group_nonce' );
        ?>
        <div class="mu-tag-group-admin-switch" data-tag-slug="<?php echo esc_attr( $tag_slug ); ?>" data-cat-slug="<?php echo esc_attr( $cat_slug ); ?>" data-cat-name="<?php echo esc_attr( $cat_name ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
            <label class="mu-tag-group-toggle">
                <input type="checkbox" class="mu-tag-group-checkbox" <?php checked( $is_grouped ); ?>>
                <span class="mu-tag-group-slider"></span>
                <span class="mu-tag-group-label">Agrupar</span>
            </label>
            <span class="mu-tag-group-status <?php echo $is_grouped ? 'is-grouped' : ''; ?>">
                <?php echo $is_grouped ? '✓ Agrupado en "' . esc_html( $cat_name ) . '"' : 'No agrupado'; ?>
            </span>
        </div>
        <?php
    }
    add_action( 'woocommerce_before_shop_loop', 'mu_render_tag_group_admin_switch', 5 );
}

// ============================================
// FUNCIONES DE AYUDA
// ============================================

if ( ! function_exists( 'mu_get_tag_groups_for_category' ) ) {
    /**
     * Obtiene los grupos de etiquetas configurados para una categoría
     *
     * @param int $cat_id ID de la categoría
     * @return array Array de slugs de etiquetas
     */
    function mu_get_tag_groups_for_category( $cat_id ) {
        $config = mu_get_tag_groups_config();
        $groups = [];

        // Obtener slug de la categoría actual
        $current_cat = get_term( $cat_id, 'product_cat' );
        if ( ! $current_cat || is_wp_error( $current_cat ) ) {
            return $groups;
        }

        $current_slug = $current_cat->slug;

        // Verificar si la categoría tiene grupos configurados
        if ( isset( $config[ $current_slug ] ) ) {
            $groups = $config[ $current_slug ];
        }

        return $groups;
    }
}

if ( ! function_exists( 'mu_get_random_products_for_tag' ) ) {
    /**
     * Obtiene productos aleatorios para una etiqueta específica
     *
     * @param string $tag_slug Slug de la etiqueta
     * @param int $limit Cantidad de productos a obtener
     * @return array Array de IDs de productos
     */
    function mu_get_random_products_for_tag( $tag_slug, $limit = 4 ) {
        $cache_key = 'mu_tag_group_' . md5( $tag_slug . '_' . $limit );
        $cached = get_transient( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        $tag = get_term_by( 'slug', $tag_slug, 'product_tag' );
        if ( ! $tag || is_wp_error( $tag ) ) {
            return [];
        }

        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'rand',
            'tax_query'      => [
                [
                    'taxonomy' => 'product_tag',
                    'field'    => 'term_id',
                    'terms'    => $tag->term_id,
                ],
            ],
            'fields'         => 'ids',
        ];

        // Si estamos en una categoría específica, filtrar por esa categoría también
        if ( mu_is_product_category() ) {
            $queried_object = get_queried_object();
            if ( $queried_object && isset( $queried_object->term_id ) ) {
                $args['tax_query'][] = [
                    'taxonomy'         => 'product_cat',
                    'field'            => 'term_id',
                    'terms'            => $queried_object->term_id,
                    'include_children' => false,
                ];
            }
        }

        $query = new WP_Query( $args );
        $product_ids = $query->posts;

        // Cachear por 1 hora
        set_transient( $cache_key, $product_ids, HOUR_IN_SECONDS );

        return $product_ids;
    }
}

// ============================================
// QUERY MODIFICATION PARA EXCLUIR PRODUCTOS DEL GRUPO
// ============================================

if ( ! function_exists( 'mu_tag_groups_exclude_group_products' ) ) {
    /**
     * Excluye productos del grupo del query principal (excepto el representativo)
     */
    function mu_tag_groups_exclude_group_products( $query ) {
        // Bandera estática para evitar ejecución recursiva
        static $mu_tag_groups_excluding = false;
        
        // Evitar ejecución recursiva
        if ( $mu_tag_groups_excluding ) {
            return;
        }
        
        // NO ejecutar en admin o admin-ajax.php
        if ( is_admin() || ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], 'admin-ajax.php' ) !== false ) ) {
            return;
        }

        // Solo en páginas de categoría de producto
        if ( ! mu_is_product_category() ) {
            return;
        }

        // NO en páginas de tag
        if ( mu_is_product_tag() ) {
            return;
        }

        // NO aplicar exclusión cuando hay filtros de tag en la URL (?product_tag=)
        if ( isset( $_GET['product_tag'] ) ) {
            return;
        }

        // Verificar si es un query de productos (main query o query de productos)
        if ( ! $query->is_main_query() && ! $query->is_tax( 'product_cat' ) ) {
            return;
        }

        $queried_object = get_queried_object();
        if ( ! $queried_object || ! isset( $queried_object->term_id ) ) {
            return;
        }

        $current_cat = get_term( $queried_object->term_id, 'product_cat' );
        if ( ! $current_cat || is_wp_error( $current_cat ) ) {
            return;
        }

        $config = mu_get_tag_groups_config();
        $current_slug = $current_cat->slug;

        if ( ! isset( $config[ $current_slug ] ) ) {
            return;
        }

        // Obtener índice de grupos pre-calculado (con fallback resiliente)
        $groups_index = mu_get_tag_groups_index();
        if ( false === $groups_index ) {
            return;
        }

        // Obtener IDs de productos a excluir (todos los del grupo excepto el representativo)
        $product_ids_to_exclude = [];

        foreach ( $config[ $current_slug ] as $tag_slug ) {
            $key = $current_slug . ':' . $tag_slug;
            if ( ! isset( $groups_index[ $key ] ) ) {
                continue;
            }

            $index_data = $groups_index[ $key ];
            // Formato: "representative_pid:±count:id1,id2,...,idN"
            $parts = explode( ':', $index_data );
            if ( count( $parts ) < 3 ) {
                continue;
            }

            $representative_pid = (int) $parts[0];
            $display_sign       = $parts[1]; // "+N" o "-N"
            $display_count      = (int) ltrim( $display_sign, '+-' );
            $all_ids_str        = $parts[2];

            if ( empty( $all_ids_str ) ) {
                continue;
            }

            $all_group_ids = array_map( 'intval', explode( ',', $all_ids_str ) );

            // Excluir todos excepto el representativo
            foreach ( $all_group_ids as $pid ) {
                if ( $pid !== $representative_pid ) {
                    $product_ids_to_exclude[] = $pid;
                }
            }
        }

        if ( ! empty( $product_ids_to_exclude ) ) {
            $query->set( 'post__not_in', $product_ids_to_exclude );
        }
    }
    add_action( 'pre_get_posts', 'mu_tag_groups_exclude_group_products', 999 );
}

// ============================================
// HOOKS PARA MODIFICAR PRODUCTOS REPRESENTATIVOS EN LOOP
// ============================================

if ( ! function_exists( 'mu_tag_groups_is_representative_product' ) ) {
    /**
     * Verifica si un producto es representativo de un grupo
     * NO usa variable global, hace la verificación directamente
     *
     * @param int $product_id ID del producto
     * @return array|null Información del grupo o null si no es representativo
     */
    function mu_tag_groups_is_representative_product( $product_id ) {
        // NO modificar en vista de tag
        if ( mu_is_product_tag() ) {
            return null;
        }

        // NO modificar cuando hay filtros de tag en la URL (?product_tag=)
        if ( isset( $_GET['product_tag'] ) ) {
            return null;
        }

        if ( ! mu_is_product_category() ) {
            return null;
        }

        $queried_object = get_queried_object();
        if ( ! $queried_object || ! isset( $queried_object->term_id ) ) {
            return null;
        }

        $current_cat = get_term( $queried_object->term_id, 'product_cat' );
        if ( ! $current_cat || is_wp_error( $current_cat ) ) {
            return null;
        }

        $config = mu_get_tag_groups_config();
        $current_slug = $current_cat->slug;

        if ( ! isset( $config[ $current_slug ] ) ) {
            return null;
        }

        // Obtener índice de grupos pre-calculado (con fallback resiliente)
        $groups_index = mu_get_tag_groups_index();
        if ( false === $groups_index ) {
            return null;
        }

        // Verificar si este producto es representativo de algún grupo
        foreach ( $config[ $current_slug ] as $tag_slug ) {
            $key = $current_slug . ':' . $tag_slug;
            if ( isset( $groups_index[ $key ] ) ) {
                $index_data = $groups_index[ $key ];
                // Formato: "representative_pid:±N:id1,id2,...,idN"
                $parts = explode( ':', $index_data );
                if ( count( $parts ) < 3 ) {
                    continue;
                }
                $rep_pid = (int) $parts[0];
                
                if ( $rep_pid === $product_id ) {
                    $display_sign  = $parts[1];
                    $all_ids_str   = $parts[2];
                    $all_ids       = ! empty( $all_ids_str ) ? array_map( 'intval', explode( ',', $all_ids_str ) ) : [];
                    
                    return [
                        'tag_slug'     => $tag_slug,
                        'display_sign' => $display_sign,
                        'all_ids'      => $all_ids,
                        'index_data'   => $index_data,
                    ];
                }
            }
        }

        return null;
    }
}

if ( ! function_exists( 'mu_tag_groups_check_representative_product' ) ) {
    /**
     * Verifica si el producto actual es representativo y almacena la información del grupo
     * Solo funciona en categorías, NO en tags
     */
    function mu_tag_groups_check_representative_product() {
        global $product;
        static $mu_current_tag_group_info = null;

        // NO modificar en vista de tag
        if ( mu_is_product_tag() ) {
            $mu_current_tag_group_info = null;
            return;
        }

        // NO modificar cuando hay filtros de tag en la URL (?product_tag=)
        if ( isset( $_GET['product_tag'] ) ) {
            $mu_current_tag_group_info = null;
            return;
        }

        if ( ! mu_is_product_category() || ! $product ) {
            $mu_current_tag_group_info = null;
            return;
        }

        $queried_object = get_queried_object();
        if ( ! $queried_object || ! isset( $queried_object->term_id ) ) {
            $mu_current_tag_group_info = null;
            return;
        }

        $current_cat = get_term( $queried_object->term_id, 'product_cat' );
        if ( ! $current_cat || is_wp_error( $current_cat ) ) {
            $mu_current_tag_group_info = null;
            return;
        }

        $config = mu_get_tag_groups_config();
        $current_slug = $current_cat->slug;

        if ( ! isset( $config[ $current_slug ] ) ) {
            $mu_current_tag_group_info = null;
            return;
        }

        // Obtener índice de grupos pre-calculado (con fallback resiliente)
        $groups_index = mu_get_tag_groups_index();
        if ( false === $groups_index ) {
            $mu_current_tag_group_info = null;
            return;
        }

        // Verificar si este producto es representativo de algún grupo
        $product_id = $product->get_id();
        $representative_info = null;

        foreach ( $config[ $current_slug ] as $tag_slug ) {
            $key = $current_slug . ':' . $tag_slug;
            if ( ! isset( $groups_index[ $key ] ) ) {
                continue;
            }

            $index_data = $groups_index[ $key ];
            // Formato: "representative_pid:±N:id1,id2,...,idN"
            $parts = explode( ':', $index_data );
            if ( count( $parts ) < 3 ) {
                continue;
            }

            if ( (int) $parts[0] === $product_id ) {
                $representative_info = [
                    'tag_slug'   => $tag_slug,
                    'index_data' => $index_data,
                ];
                break;
            }
        }

        $mu_current_tag_group_info = $representative_info;
    }
    add_action( 'woocommerce_before_shop_loop_item', 'mu_tag_groups_check_representative_product', 1 );
}

if ( ! function_exists( 'mu_tag_groups_modify_product_link' ) ) {
    /**
     * Modifica el enlace del producto representativo
     * Solo funciona en categorías, NO en tags
     */
    function mu_tag_groups_modify_product_link( $link, $product ) {
        // Verificar si este producto es representativo usando la función helper
        $post_id = $product->get_id();
        $tag_group_info = mu_tag_groups_is_representative_product( $post_id );

        if ( ! $tag_group_info ) {
            return $link;
        }

        $queried_object = get_queried_object();
        if ( ! $queried_object || ! isset( $queried_object->term_id ) ) {
            return $link;
        }

        $current_cat = get_term( $queried_object->term_id, 'product_cat' );
        if ( ! $current_cat || is_wp_error( $current_cat ) ) {
            return $link;
        }

        $tag = get_term_by( 'slug', $tag_group_info['tag_slug'], 'product_tag' );
        if ( ! $tag || is_wp_error( $tag ) ) {
            return $link;
        }

        $tag_link = add_query_arg( 'product_tag', $tag->slug, get_term_link( $current_cat ) );
        if ( is_wp_error( $tag_link ) ) {
            return $link;
        }

        return $tag_link;
    }
    add_filter( 'woocommerce_loop_product_link', 'mu_tag_groups_modify_product_link', 10, 2 );
}

// (Filters woocommerce_loop_product_title and post_thumbnail_html
//  removed - they didn't execute due to plugin conflicts.
//  Using TEST1 action hooks + TEST9 post_class + CSS instead)

if ( ! function_exists( 'mu_tag_groups_hide_add_to_cart' ) ) {
    /**
     * Modifica el botón de añadir al carrito para productos representativos
     * Cambia el texto a "Ver Colección" y mantiene el enlace al tag
     * Solo funciona en categorías, NO en tags
     */
    function mu_tag_groups_hide_add_to_cart( $html, $product ) {
        // Verificar si este producto es representativo usando la función helper
        $post_id = $product->get_id();
        $tag_group_info = mu_tag_groups_is_representative_product( $post_id );

        if ( ! $tag_group_info ) {
            return $html;
        }

        // Obtener el enlace al tag
        $queried_object = get_queried_object();
        if ( ! $queried_object || ! isset( $queried_object->term_id ) ) {
            return $html;
        }

        $current_cat = get_term( $queried_object->term_id, 'product_cat' );
        if ( ! $current_cat || is_wp_error( $current_cat ) ) {
            return $html;
        }

        $tag = get_term_by( 'slug', $tag_group_info['tag_slug'], 'product_tag' );
        if ( ! $tag || is_wp_error( $tag ) ) {
            return $html;
        }

        $tag_link = add_query_arg( 'product_tag', $tag->slug, get_term_link( $current_cat ) );
        if ( is_wp_error( $tag_link ) ) {
            return $html;
        }
        
        // Reemplazar el botón con un enlace "Ver Colección"
        $new_html = sprintf(
            '<a href="%s" class="button add_to_cart_button mu-tag-group-btn" rel="nofollow">Ver Colección</a>',
            esc_url( $tag_link )
        );
        
        return $new_html;
    }
    add_filter( 'woocommerce_loop_add_to_cart_link', 'mu_tag_groups_hide_add_to_cart', 10, 2 );
}

// ============================================
// HELPER: RENDER IMAGE GRID FOR REPRESENTATIVE PRODUCTS
// ============================================

if ( ! function_exists( 'mu_render_tag_group_grid' ) ) {
    /**
     * Renderiza un grid de 4 imágenes para un tag group
     *
     * @param string $tag_slug Slug de la etiqueta
     * @return string HTML del grid de imágenes
     */
    function mu_render_tag_group_grid( $tag_slug ) {
        $product_ids = mu_get_random_products_for_tag( $tag_slug, 4 );

        if ( empty( $product_ids ) ) {
            return '';
        }

        $images_html = '<div class="mu-tag-group-grid mu-tag-group-grid-inline">';
        $image_count = 0;
        foreach ( $product_ids as $product_id ) {
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                continue;
            }
            $image_id = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : wc_placeholder_img_src();
    $images_html .= sprintf(
        '<img src="%s" alt="%s">',
        esc_url( $image_url ),
        esc_attr( $product->get_name() )
    );
            $image_count++;
        }

        // Rellenar con placeholders si faltan imágenes
        for ( $i = $image_count; $i < 4; $i++ ) {
    $images_html .= sprintf(
        '<img src="%s" alt="">',
        esc_url( wc_placeholder_img_src() )
    );
        }

        $images_html .= '</div>';
        return $images_html;
    }
}

// ============================================
// TEST9 + TEST1: ACTION HOOKS + post_class + CSS
// ============================================

// TEST9: Agregar clase CSS al <li> del producto representativo
if ( ! function_exists( 'mu_tag_groups_post_class' ) ) {
    function mu_tag_groups_post_class( $classes, $class, $post_id ) {
        if ( is_admin() ) return $classes;
        if ( ! mu_wc_is_product_category() ) return $classes;
        if ( mu_wc_is_product_tag() ) return $classes;
        if ( isset( $_GET['product_tag'] ) ) return $classes;

        $tag_group_info = mu_tag_groups_is_representative_product( $post_id );
        if ( $tag_group_info ) {
            $classes[] = 'mu-tag-group-representative';
            $classes[] = 'mu-tag-group-' . esc_attr( $tag_group_info['tag_slug'] );
        }
        return $classes;
    }
    add_filter( 'post_class', 'mu_tag_groups_post_class', 10, 3 );
}

// TEST1: Action hook para modificar título (priority 20, después del título original)
function mu_tag_groups_action_modify_title() {
    global $product;
    if ( ! $product ) return;

    $post_id = $product->get_id();
    $tag_group_info = mu_tag_groups_is_representative_product( $post_id );

    if ( ! $tag_group_info ) return;

    $tag = get_term_by( 'slug', $tag_group_info['tag_slug'], 'product_tag' );
    if ( ! $tag ) return;

    echo '<h2 class="woocommerce-loop-product__title mu-tag-group-replacement">' . esc_html( $tag->name ) . '</h2>';
}
add_action( 'woocommerce_before_shop_loop_item_title', 'mu_tag_groups_action_modify_title', 20 );

// TEST1: Action hook para modificar imagen (priority 5, antes de la imagen original)
function mu_tag_groups_action_modify_image() {
    global $product;
    if ( ! $product ) return;

    $post_id = $product->get_id();
    $tag_group_info = mu_tag_groups_is_representative_product( $post_id );

    if ( ! $tag_group_info ) return;

    $grid_html = mu_render_tag_group_grid( $tag_group_info['tag_slug'] );
    if ( ! empty( $grid_html ) ) {
        echo '<div class="mu-tag-group-image-wrapper">' . $grid_html . '</div>';
    }
}
add_action( 'woocommerce_before_shop_loop_item_title', 'mu_tag_groups_action_modify_image', 5 );

// Nota: mu_tag_groups_reset_global ya no es necesaria porque
// $mu_current_tag_group_info es static dentro de mu_tag_groups_check_representative_product()
// y se resetea automáticamente en cada llamada.

if ( ! function_exists( 'mu_render_tag_group_card_from_index' ) ) {
    /**
     * Renderiza un card de grupo desde el índice pre-calculado
     *
     * @param string $tag_slug Slug de la etiqueta
     * @param string $index_data String "representative_pid:±N:id1,id2,...,idN"
     * @return string HTML del card
     */
    function mu_render_tag_group_card_from_index( $tag_slug, $index_data ) {
        $parts = explode( ':', $index_data );
        if ( count( $parts ) < 3 ) {
            return '';
        }

        $representative_pid = (int) $parts[0];
        $display_sign       = $parts[1]; // "+N" o "-N"
        $display_count      = (int) ltrim( $display_sign, '+-' );
        $all_ids_str        = $parts[2];

        if ( empty( $all_ids_str ) ) {
            return '';
        }

        $all_ids = array_map( 'intval', explode( ',', $all_ids_str ) );

        // Determinar qué IDs mostrar según el signo
        if ( $display_sign === '-' ) {
            // El representativo NO está incluido, mostrar otros N productos
            $product_ids = array_values( array_diff( $all_ids, [ $representative_pid ] ) );
            $product_ids = array_slice( $product_ids, 0, $display_count );
        } else {
            // El representativo SÍ está incluido, mostrar primeros N
            $product_ids = array_slice( $all_ids, 0, $display_count );
        }

        if ( empty( $product_ids ) ) {
            return '';
        }

        $tag = get_term_by( 'slug', $tag_slug, 'product_tag' );
        if ( ! $tag || is_wp_error( $tag ) ) {
            return '';
        }

        $tag_link = get_term_link( $tag, 'product_tag' );
        if ( is_wp_error( $tag_link ) ) {
            return '';
        }

        // Obtener conteo de productos desde el índice (evita WP_Query)
        $product_count = count( $all_ids );

        // Generar grid de imágenes
        $images_html = '';
        foreach ( $product_ids as $product_id ) {
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                continue;
            }

            $image_id = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : wc_placeholder_img_src();

            $images_html .= sprintf(
                '<div class="mu-tag-group-image">
                    <img src="%s" alt="%s" loading="lazy">
                </div>',
                esc_url( $image_url ),
                esc_attr( $product->get_name() )
            );
        }

        // Si no tenemos suficientes imágenes, rellenar con placeholders
        while ( substr_count( $images_html, 'mu-tag-group-image' ) < 4 ) {
            $images_html .= sprintf(
                '<div class="mu-tag-group-image">
                    <img src="%s" alt="" loading="lazy">
                </div>',
                esc_url( wc_placeholder_img_src() )
            );
        }

        ob_start();
        ?>
        <li class="product mu-tag-group-wrapper">
            <div class="mu-tag-group-card" data-tag-slug="<?php echo esc_attr( $tag_slug ); ?>">
                <a href="<?php echo esc_url( $tag_link ); ?>" class="mu-tag-group-link">
                    <div class="mu-tag-group-grid">
                        <?php echo $images_html; ?>
                    </div>
                    <div class="mu-tag-group-overlay">
                        <h3 class="mu-tag-group-title"><?php echo esc_html( $tag->name ); ?></h3>
                        <span class="mu-tag-group-count"><?php echo esc_html( $product_count ); ?></span>
                    </div>
                </a>
            </div>
        </li>
        <?php
        return ob_get_clean();
    }
}

// ============================================
// ENQUEUE DE ASSETS
// ============================================

if ( ! function_exists( 'mu_tag_groups_get_asset_version' ) ) {
    /**
     * Obtiene un versionado basado en filemtime para forzar refresco de caché.
     *
     * @param string $relative_path Ruta relativa al theme (ej: 'css/tag-groups.css').
     * @return string Versión con timestamp del archivo.
     */
    function mu_tag_groups_get_asset_version( $relative_path ) {
        $file = get_stylesheet_directory() . '/' . ltrim( $relative_path, '/' );
        if ( file_exists( $file ) ) {
            return (string) filemtime( $file );
        }
        return wp_get_theme()->get( 'Version' );
    }
}

if ( ! function_exists( 'mu_tag_groups_enqueue_assets' ) ) {
    function mu_tag_groups_enqueue_assets() {
        // Cargar en páginas de categoría de producto (comportamiento actual)
        if ( mu_wc_is_product_category() ) {
            $queried_object = get_queried_object();
            if ( $queried_object && isset( $queried_object->term_id ) ) {
                $groups = mu_get_tag_groups_for_category( $queried_object->term_id );
                if ( ! empty( $groups ) ) {
                    wp_enqueue_style( 'mu-tag-groups', get_stylesheet_directory_uri() . '/css/tag-groups.css', [], mu_tag_groups_get_asset_version( 'css/tag-groups.css' ) );
                    wp_enqueue_script( 'mu-tag-groups', get_stylesheet_directory_uri() . '/js/tag-groups.js', [], mu_tag_groups_get_asset_version( 'js/tag-groups.js' ), true );
                }
            }
        }

        // Cargar assets para el switch admin (en páginas de tag o categoría con filtro de tag)
        $is_admin_tag_view = ( mu_wc_is_product_tag() && current_user_can( 'manage_woocommerce' ) );
        $is_admin_cat_filtered_view = ( mu_wc_is_product_category() && isset( $_GET['product_tag'] ) && current_user_can( 'manage_woocommerce' ) );

        if ( $is_admin_tag_view || $is_admin_cat_filtered_view ) {
            wp_enqueue_style( 'mu-tag-groups', get_stylesheet_directory_uri() . '/css/tag-groups.css', [], mu_tag_groups_get_asset_version( 'css/tag-groups.css' ) );
            wp_enqueue_script( 'mu-tag-groups', get_stylesheet_directory_uri() . '/js/tag-groups.js', [], mu_tag_groups_get_asset_version( 'js/tag-groups.js' ), true );

            // Pasar datos al JS
            if ( wp_script_is( 'mu-tag-groups', 'enqueued' ) ) {
                wp_localize_script( 'mu-tag-groups', 'muTagGroupsAdmin', [
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                    'nonce'   => wp_create_nonce( 'mu_tag_group_nonce' ),
                ] );
            }
        }
    }
    add_action( 'wp_enqueue_scripts', 'mu_tag_groups_enqueue_assets' );
}
