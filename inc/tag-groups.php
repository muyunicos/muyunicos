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
// CONFIGURACIÓN DE GRUPOS
// ============================================

if ( ! defined( 'MU_TAG_GROUPS_CONFIG' ) ) {
    define( 'MU_TAG_GROUPS_CONFIG', [
        'stickers' => [
            'los-simpsons',
            'zorros-mdq',
            'disney',
            'diversidad',
            // Agregar más etiquetas según necesidad
        ],
        'juegosparawii' => [
            'controles',
            'accesorios',
            // Agregar más etiquetas según necesidad
        ],
        // Agregar más categorías según necesidad
    ] );
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
        $config = MU_TAG_GROUPS_CONFIG;
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
        if ( is_product_category() ) {
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

if ( ! function_exists( 'mu_render_tag_group_card' ) ) {
    /**
     * Renderiza el HTML de un card de grupo de etiquetas
     *
     * @param string $tag_slug Slug de la etiqueta
     * @return string HTML del card
     */
    function mu_render_tag_group_card( $tag_slug ) {
        $product_ids = mu_get_random_products_for_tag( $tag_slug, 4 );

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

        // Obtener conteo de productos para esta etiqueta
        $count_args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => [
                [
                    'taxonomy' => 'product_tag',
                    'field'    => 'term_id',
                    'terms'    => $tag->term_id,
                ],
            ],
        ];

        if ( is_product_category() ) {
            $queried_object = get_queried_object();
            if ( $queried_object && isset( $queried_object->term_id ) ) {
                $count_args['tax_query'][] = [
                    'taxonomy'         => 'product_cat',
                    'field'            => 'term_id',
                    'terms'            => $queried_object->term_id,
                    'include_children' => false,
                ];
            }
        }

        $count_query = new WP_Query( $count_args );
        $product_count = $count_query->found_posts;

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
        <?php
        return ob_get_clean();
    }
}

// ============================================
// QUERY MODIFICATION PARA EXCLUIR PRODUCTOS DEL GRUPO
// ============================================

// Bandera para evitar ejecución recursiva
global $mu_tag_groups_excluding;
$mu_tag_groups_excluding = false;

if ( ! function_exists( 'mu_tag_groups_exclude_group_products' ) ) {
    /**
     * Excluye productos del grupo del query principal (excepto el representativo)
     */
    function mu_tag_groups_exclude_group_products( $query ) {
        global $mu_tag_groups_excluding;
        
        // Evitar ejecución recursiva
        if ( $mu_tag_groups_excluding ) {
            return;
        }
        
        // NO ejecutar en admin o admin-ajax.php
        if ( is_admin() || strpos( $_SERVER['REQUEST_URI'], 'admin-ajax.php' ) !== false ) {
            return;
        }

        // Solo en páginas de categoría de producto, NO en tag
        if ( ! is_product_category() || is_product_tag() ) {
            return;
        }

        // NO aplicar exclusión cuando hay filtros de tag en la URL (?product_tag=)
        if ( isset( $_GET['product_tag'] ) ) {
            return;
        }

        // Verificar si es un query de productos (main query o query de productos)
        // Usar is_tax con product_cat para detectar queries de WooCommerce
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

        $config = MU_TAG_GROUPS_CONFIG;
        $current_slug = $current_cat->slug;

        if ( ! isset( $config[ $current_slug ] ) ) {
            return;
        }

        // Obtener índice de grupos pre-calculado
        $groups_index = get_transient( 'mu_tag_groups_index' );
        if ( false === $groups_index ) {
            return;
        }

        // Obtener IDs de productos a excluir (todos los del grupo excepto el representativo)
        $product_ids_to_exclude = [];
        $representative_ids = [];

        foreach ( $config[ $current_slug ] as $tag_slug ) {
            $key = $current_slug . ':' . $tag_slug;
            if ( isset( $groups_index[ $key ] ) ) {
                // Nuevo formato: solo el ID del representativo
                $representative_pid = (int) $groups_index[ $key ];
                
                // Agregar el representativo a la lista de permitidos
                $representative_ids[] = $representative_pid;
                
                // Obtener TODOS los productos del grupo para excluirlos (excepto el representativo)
                $tag = get_term_by( 'slug', $tag_slug, 'product_tag' );
                if ( $tag && ! is_wp_error( $tag ) ) {
                    // Activar bandera para evitar recursión
                    $mu_tag_groups_excluding = true;
                    
                    $args = [
                        'post_type' => 'product',
                        'post_status' => 'publish',
                        'posts_per_page' => -1,
                        'orderby' => 'date',
                        'order' => 'DESC',
                        'tax_query' => [
                            [
                                'taxonomy' => 'product_cat',
                                'field' => 'term_id',
                                'terms' => $queried_object->term_id,
                            ],
                            [
                                'taxonomy' => 'product_tag',
                                'field' => 'term_id',
                                'terms' => $tag->term_id,
                            ],
                        ],
                        'fields' => 'ids',
                    ];
                    
                    $group_query = new WP_Query( $args );
                    $all_group_products = $group_query->posts;
                    
                    // Desactivar bandera
                    $mu_tag_groups_excluding = false;
                    
                    // Excluir todos excepto el representativo
                    foreach ( $all_group_products as $pid ) {
                        if ( $pid !== $representative_pid ) {
                            $product_ids_to_exclude[] = $pid;
                        }
                    }
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
        if ( is_product_tag() ) {
            return null;
        }

        // NO modificar cuando hay filtros de tag en la URL (?product_tag=)
        if ( isset( $_GET['product_tag'] ) ) {
            return null;
        }

        if ( ! is_product_category() ) {
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

        $config = MU_TAG_GROUPS_CONFIG;
        $current_slug = $current_cat->slug;

        if ( ! isset( $config[ $current_slug ] ) ) {
            return null;
        }

        // Obtener índice de grupos pre-calculado
        $groups_index = get_transient( 'mu_tag_groups_index' );
        if ( false === $groups_index ) {
            return null;
        }

        // Verificar si este producto es representativo de algún grupo
        foreach ( $config[ $current_slug ] as $tag_slug ) {
            $key = $current_slug . ':' . $tag_slug;
            if ( isset( $groups_index[ $key ] ) ) {
                $index_data = $groups_index[ $key ];
                // Formato: "representative_pid:reserved1,reserved2,..."
                $parts = explode( ':', $index_data );
                $rep_pid = (int) $parts[0];
                
                if ( $rep_pid === $product_id ) {
                    $reserved_ids = [];
                    if ( isset( $parts[1] ) && ! empty( $parts[1] ) ) {
                        $reserved_ids = array_map( 'intval', explode( ',', $parts[1] ) );
                    }
                    return [
                        'tag_slug'     => $tag_slug,
                        'reserved_ids' => $reserved_ids,
                        'index_data'   => $index_data,
                    ];
                }
            }
        }

        return null;
    }
}

// Variable global para almacenar información del grupo actual (mantener para compatibilidad)
global $mu_current_tag_group_info;
$mu_current_tag_group_info = null;

if ( ! function_exists( 'mu_tag_groups_check_representative_product' ) ) {
    /**
     * Verifica si el producto actual es representativo y almacena la información del grupo
     * Solo funciona en categorías, NO en tags
     */
    function mu_tag_groups_check_representative_product() {
        global $product, $mu_current_tag_group_info;

        // NO modificar en vista de tag
        if ( is_product_tag() ) {
            $mu_current_tag_group_info = null;
            return;
        }

        // NO modificar cuando hay filtros de tag en la URL (?product_tag=)
        if ( isset( $_GET['product_tag'] ) ) {
            $mu_current_tag_group_info = null;
            return;
        }

        if ( ! is_product_category() || ! $product ) {
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

        $config = MU_TAG_GROUPS_CONFIG;
        $current_slug = $current_cat->slug;

        if ( ! isset( $config[ $current_slug ] ) ) {
            $mu_current_tag_group_info = null;
            return;
        }

        // Obtener índice de grupos pre-calculado
        $groups_index = get_transient( 'mu_tag_groups_index' );
        if ( false === $groups_index ) {
            $mu_current_tag_group_info = null;
            return;
        }

        // Verificar si este producto es representativo de algún grupo
        $product_id = $product->get_id();
        $representative_info = null;

        foreach ( $config[ $current_slug ] as $tag_slug ) {
            $key = $current_slug . ':' . $tag_slug;
            if ( isset( $groups_index[ $key ] ) ) {
                // Nuevo formato: solo el ID del representativo
                if ( (int) $groups_index[ $key ] === $product_id ) {
                    $representative_info = [
                        'tag_slug' => $tag_slug,
                        'index_data' => $groups_index[ $key ],
                    ];
                    break;
                }
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
        if ( ! is_product_category() ) return $classes;
        if ( is_product_tag() ) return $classes;
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

if ( ! function_exists( 'mu_tag_groups_reset_global' ) ) {
    /**
     * Limpia la variable global después de cada producto
     */
    function mu_tag_groups_reset_global() {
        global $mu_current_tag_group_info;
        $mu_current_tag_group_info = null;
    }
    add_action( 'woocommerce_after_shop_loop_item', 'mu_tag_groups_reset_global', 999 );
}

if ( ! function_exists( 'mu_render_tag_group_card_from_index' ) ) {
    /**
     * Renderiza un card de grupo desde el índice pre-calculado
     *
     * @param string $tag_slug Slug de la etiqueta
     * @param string $index_data String "representative_pid:pid1,pid2,pid3,pid4"
     * @return string HTML del card
     */
    function mu_render_tag_group_card_from_index( $tag_slug, $index_data ) {
        $parts = explode( ':', $index_data );
        if ( count( $parts ) < 2 ) {
            return '';
        }

        $representative_pid = (int) $parts[0]; // No usado en renderizado, solo para exclusión
        $image_pids_str = $parts[1];
        $product_ids = array_map( 'intval', explode( ',', $image_pids_str ) );

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

        // Obtener conteo de productos para esta etiqueta
        $count_args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => [
                [
                    'taxonomy' => 'product_tag',
                    'field'    => 'term_id',
                    'terms'    => $tag->term_id,
                ],
            ],
        ];

        if ( is_product_category() ) {
            $queried_object = get_queried_object();
            if ( $queried_object && isset( $queried_object->term_id ) ) {
                $count_args['tax_query'][] = [
                    'taxonomy'         => 'product_cat',
                    'field'            => 'term_id',
                    'terms'            => $queried_object->term_id,
                    'include_children' => false,
                ];
            }
        }

        $count_query = new WP_Query( $count_args );
        $product_count = $count_query->found_posts;

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

if ( ! function_exists( 'mu_tag_groups_enqueue_assets' ) ) {
    function mu_tag_groups_enqueue_assets() {
        // Solo cargar en páginas de categoría de producto
        if ( ! is_product_category() ) {
            return;
        }

        // Verificar si la categoría actual tiene grupos configurados
        $queried_object = get_queried_object();
        if ( ! $queried_object || ! isset( $queried_object->term_id ) ) {
            return;
        }

        $groups = mu_get_tag_groups_for_category( $queried_object->term_id );
        if ( empty( $groups ) ) {
            return;
        }

        $ver = wp_get_theme()->get( 'Version' );
        wp_enqueue_style( 'mu-tag-groups', get_stylesheet_directory_uri() . '/css/tag-groups.css', [], $ver );
        wp_enqueue_script( 'mu-tag-groups', get_stylesheet_directory_uri() . '/js/tag-groups.js', [], $ver, true );
    }
    add_action( 'wp_enqueue_scripts', 'mu_tag_groups_enqueue_assets' );
}

