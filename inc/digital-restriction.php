<?php
/**
 * Muy Únicos - Digital Restriction System
 * 
 * Sistema de restricción de contenido digital v2.4 (Refactorizado)
 * Propósito: Restringir productos físicos en subdominios, mostrando solo 
 * productos digitales. Optimizado para rendimiento y compatibilidad.
 * 
 * @package GeneratePress_Child
 * @since 2.4.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'MUYU_PHYSICAL_FORMAT_ID' ) ) {
    define( 'MUYU_PHYSICAL_FORMAT_ID', 112 );
}
if ( ! defined( 'MUYU_DIGITAL_FORMAT_ID' ) ) {
    define( 'MUYU_DIGITAL_FORMAT_ID', 111 );
}

if ( ! function_exists( 'muyu_is_restricted_user' ) ) {
    /**
     * Verifica si el usuario actual está restringido a contenido digital
     */
    function muyu_is_restricted_user() {
        static $is_restricted = null;
        if ( $is_restricted !== null ) {
            return $is_restricted;
        }
        
        // Admins no están restringidos, excepto si están probando AJAX frontend (opcional, pero seguro mantener false)
        if ( current_user_can( 'manage_woocommerce' ) || ( is_admin() && ! wp_doing_ajax() ) ) {
            $is_restricted = false;
            return false;
        }
        
        $main_domain = function_exists( 'muyu_get_main_domain' ) ? muyu_get_main_domain() : 'muyunicos.com';
        $host = preg_replace( '/:\d+$/', '', trim( $_SERVER['HTTP_HOST'] ?? '' ) );
        $host = str_replace( 'www.', '', $host );
        
        // Solo restringir si NO es el dominio principal
        $is_restricted = ( $main_domain !== $host );
        return $is_restricted;
    }
}

if ( ! function_exists( 'muyu_get_user_country_code' ) ) {
    /**
     * Obtiene el código de país del usuario
     */
    function muyu_get_user_country_code() {
        if ( function_exists( 'muyu_get_current_country_from_subdomain' ) ) {
            return muyu_get_current_country_from_subdomain();
        }
        return 'AR';
    }
}

// ----------------------------------------------------------------------
// GESTIÓN DE ÍNDICES
// ----------------------------------------------------------------------

if ( ! function_exists( 'muyu_get_digital_product_ids' ) ) {
    function muyu_get_digital_product_ids() {
        global $wpdb;
        $sql = "
            SELECT DISTINCT p.ID as product_id
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product' 
            AND p.post_status = 'publish'
            AND pm.meta_key IN ('_virtual', '_downloadable')
            AND pm.meta_value = 'yes'
            
            UNION
            
            SELECT DISTINCT p.post_parent as product_id
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product_variation' 
            AND p.post_status = 'publish'
            AND pm.meta_key IN ('_virtual', '_downloadable')
            AND pm.meta_value = 'yes'
            AND p.post_parent > 0
        ";
        $ids = $wpdb->get_col( $sql );
        return array_filter( array_unique( array_map( 'intval', $ids ) ) );
    }
}

if ( ! function_exists( 'muyu_get_product_terms' ) ) {
    function muyu_get_product_terms( $product_ids ) {
        global $wpdb;
        $ids_string = implode( ',', array_map( 'intval', $product_ids ) );
        
        if ( empty( $ids_string ) ) return [ [], [] ];

        $sql = "
            SELECT DISTINCT t.term_id, tt.taxonomy 
            FROM {$wpdb->terms} t
            INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            WHERE tr.object_id IN ($ids_string)
            AND tt.taxonomy IN ('product_cat', 'product_tag')
        ";
        $terms = $wpdb->get_results( $sql );
        $category_ids = [];
        $tag_ids = [];
        foreach ( $terms as $term ) {
            if ( 'product_cat' === $term->taxonomy ) {
                $category_ids[] = (int) $term->term_id;
            } elseif ( 'product_tag' === $term->taxonomy ) {
                $tag_ids[] = (int) $term->term_id;
            }
        }
        return [ array_unique( $category_ids ), array_unique( $tag_ids ) ];
    }
}

if ( ! function_exists( 'muyu_expand_category_hierarchy' ) ) {
    function muyu_expand_category_hierarchy( $category_ids ) {
        $expanded = $category_ids;
        foreach ( $category_ids as $cat_id ) {
            $ancestors = get_ancestors( $cat_id, 'product_cat', 'taxonomy' );
            if ( ! empty( $ancestors ) ) {
                $expanded = array_merge( $expanded, $ancestors );
            }
        }
        return array_unique( array_map( 'intval', $expanded ) );
    }
}

if ( ! function_exists( 'muyu_build_redirect_map' ) ) {
    function muyu_build_redirect_map( $digital_product_ids ) {
        global $wpdb;
        if ( empty( $digital_product_ids ) ) return [];
        
        $ids_string = implode( ',', array_map( 'intval', $digital_product_ids ) );
        $sql = "SELECT ID, post_name FROM {$wpdb->posts} WHERE ID IN ($ids_string)";
        $digital_products = $wpdb->get_results( $sql );
        $redirect_map = [];
        
        foreach ( $digital_products as $product ) {
            if ( false !== strpos( $product->post_name, '-imprimible' ) ) {
                $base_slug = str_replace( '-imprimible', '', $product->post_name );
                $physical_id = $wpdb->get_var( 
                    $wpdb->prepare(
                        "SELECT ID FROM {$wpdb->posts} 
                        WHERE post_name = %s 
                        AND post_type = 'product' 
                        AND post_status = 'publish' 
                        LIMIT 1",
                        $base_slug
                    )
                );
                
                if ( $physical_id && ! in_array( (int) $physical_id, $digital_product_ids, true ) ) {
                    $redirect_map[ (int) $physical_id ] = (int) $product->ID;
                }
            }
        }
        return $redirect_map;
    }
}

if ( ! function_exists( 'muyu_rebuild_digital_indexes_optimized' ) ) {
    /**
     * Reconstruye los índices y opciones de redirección digital
     */
    function muyu_rebuild_digital_indexes_optimized() {
        $digital_product_ids = muyu_get_digital_product_ids();
        
        if ( empty( $digital_product_ids ) ) {
            update_option( 'muyu_digital_product_ids', [], false );
            update_option( 'muyu_digital_category_ids', [], false );
            update_option( 'muyu_digital_tag_ids', [], false );
            update_option( 'muyu_phys_to_dig_map', [], false );
            update_option( 'muyu_digital_list_updated', current_time( 'mysql' ), false );
            return 0;
        }
        
        list( $category_ids, $tag_ids ) = muyu_get_product_terms( $digital_product_ids );
        $category_ids = muyu_expand_category_hierarchy( $category_ids );
        $redirect_map = muyu_build_redirect_map( $digital_product_ids );
        
        update_option( 'muyu_digital_product_ids', $digital_product_ids, false );
        update_option( 'muyu_digital_category_ids', $category_ids, false );
        update_option( 'muyu_digital_tag_ids', $tag_ids, false );
        update_option( 'muyu_phys_to_dig_map', $redirect_map, false );
        update_option( 'muyu_digital_list_updated', current_time( 'mysql' ), false );
        
        delete_transient( 'muyu_rebuild_scheduled' );
        
        return count( $digital_product_ids );
    }
}

// ----------------------------------------------------------------------
// HOOKS: AJAX, CRON & ADMIN INITIALIZATION
// ----------------------------------------------------------------------

if ( ! function_exists( 'muyu_ajax_rebuild_indexes' ) ) {
    function muyu_ajax_rebuild_indexes() {
        check_ajax_referer( 'muyu-rebuild-nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'Permisos insuficientes' );
        
        $count = muyu_rebuild_digital_indexes_optimized();
        wp_send_json_success( sprintf( 'Índice reconstruido correctamente. Total productos digitales: %d', $count ) );
    }
}
add_action( 'wp_ajax_muyu_rebuild_digital_list', 'muyu_ajax_rebuild_indexes' );

if ( ! function_exists( 'muyu_schedule_rebuild' ) ) {
    function muyu_schedule_rebuild( $product_id ) {
        if ( ! get_transient( 'muyu_rebuild_scheduled' ) ) {
            set_transient( 'muyu_rebuild_scheduled', true, 120 );
        }
    }
}
add_action( 'woocommerce_update_product', 'muyu_schedule_rebuild', 10, 1 );

if ( ! function_exists( 'muyu_execute_scheduled_rebuild' ) ) {
    function muyu_execute_scheduled_rebuild() {
        if ( get_transient( 'muyu_rebuild_scheduled' ) ) {
            muyu_rebuild_digital_indexes_optimized();
        }
    }
}
add_action( 'shutdown', 'muyu_execute_scheduled_rebuild' );

if ( ! function_exists( 'muyu_ensure_indexes_exist' ) ) {
    function muyu_ensure_indexes_exist() {
        if ( false === get_option( 'muyu_digital_product_ids' ) ) {
            muyu_rebuild_digital_indexes_optimized();
        }
    }
}
add_action( 'admin_init', 'muyu_ensure_indexes_exist', 5 );

if ( ! function_exists( 'muyu_admin_rebuild_assets' ) ) {
    /**
     * Encola el botón de reindexado en wp-admin/edit.php?post_type=product
     */
    function muyu_admin_rebuild_assets( $hook ) {
        global $typenow;
        if ( 'edit.php' !== $hook || 'product' !== $typenow ) return;

        $theme_uri = get_stylesheet_directory_uri();
        $ver       = wp_get_theme()->get( 'Version' );

        wp_enqueue_style( 'mu-admin', $theme_uri . '/css/admin.css', [], $ver );
        wp_enqueue_script( 'mu-admin-js', $theme_uri . '/js/admin.js', [], $ver, true );
        wp_localize_script( 'mu-admin-js', 'muyuAdminData', [
            'nonce' => wp_create_nonce( 'muyu-rebuild-nonce' ),
            'label' => '⚡ Reindexar Digitales',
        ] );
    }
}
add_action( 'admin_enqueue_scripts', 'muyu_admin_rebuild_assets' );

// ----------------------------------------------------------------------
// HOOKS: FILTROS FRONTEND
// ----------------------------------------------------------------------

if ( ! function_exists( 'muyu_filter_product_queries' ) ) {
    function muyu_filter_product_queries( $query ) {
        if ( is_admin() || ! $query->is_main_query() ) return;
        if ( $query->is_product() || ( $query->is_singular() && 'product' === $query->get( 'post_type' ) ) ) return;
        
        $is_shop_query = (
            ( function_exists( 'is_shop' ) && is_shop() ) ||
            ( function_exists( 'is_product_category' ) && is_product_category() ) ||
            ( function_exists( 'is_product_tag' ) && is_product_tag() ) ||
            is_search() ||
            'product' === $query->get( 'post_type' )
        );
        
        if ( ! $is_shop_query ) return;
        
        if ( muyu_is_restricted_user() ) {
            $digital_ids = get_option( 'muyu_digital_product_ids', [] );
            // Aseguramos que sea array de enteros
            $digital_ids = array_map( 'intval', $digital_ids );
            $query->set( 'post__in', ! empty( $digital_ids ) ? $digital_ids : [ 0 ] );
        }
    }
}
add_action( 'pre_get_posts', 'muyu_filter_product_queries', 50 );

if ( ! function_exists( 'muyu_filter_category_terms' ) ) {
    function muyu_filter_category_terms( $args, $taxonomies ) {
        // Evitar ejecución en admin o JSON requests, pero permitir en fragmentos AJAX si es necesario
        if ( is_admin() || wp_is_json_request() ) return $args;
        if ( ! in_array( 'product_cat', (array) $taxonomies, true ) || ! muyu_is_restricted_user() ) return $args;
        
        $digital_cat_ids = get_option( 'muyu_digital_category_ids', [] );
        $digital_cat_ids = array_map( 'intval', $digital_cat_ids );
        
        if ( ! empty( $args['include'] ) ) {
            $current = array_map( 'intval', is_array( $args['include'] ) ? $args['include'] : explode( ',', $args['include'] ) );
            $args['include'] = array_intersect( $current, $digital_cat_ids );
        } else {
            $args['include'] = empty( $digital_cat_ids ) ? [ 0 ] : $digital_cat_ids;
        }
        
        return $args;
    }
}
add_filter( 'get_terms_args', 'muyu_filter_category_terms', 10, 2 );

if ( ! function_exists( 'muyu_filter_menu_items' ) ) {
    function muyu_filter_menu_items( $items, $menu, $args ) {
        if ( is_admin() || wp_is_json_request() || ! muyu_is_restricted_user() ) return $items;
        
        $digital_cat_ids = get_option( 'muyu_digital_category_ids', [] );
        $digital_cat_ids = array_map( 'intval', $digital_cat_ids );
        
        return array_filter( $items, function( $item ) use ( $digital_cat_ids ) {
            if ( isset( $item->object ) && 'product_cat' === $item->object ) {
                return in_array( (int) $item->object_id, $digital_cat_ids, true );
            }
            return true;
        });
    }
}
add_filter( 'wp_get_nav_menu_items', 'muyu_filter_menu_items', 10, 3 );

// ----------------------------------------------------------------------
// HOOKS: REDIRECCIONES DE CONTENIDO FÍSICO
// ----------------------------------------------------------------------

if ( ! function_exists( 'muyu_handle_redirects' ) ) {
    function muyu_handle_redirects() {
        if ( is_admin() || ! muyu_is_restricted_user() ) return;
        if ( ! is_product() && ! is_product_category() && ! is_product_tag() ) return;
        
        $target_url = '';
        $should_redirect = false;
        
        if ( is_product_category() ) {
            $queried_object = get_queried_object();
            $digital_cats = get_option( 'muyu_digital_category_ids', [] );
            $digital_cats = array_map( 'intval', $digital_cats );
            
            if ( $queried_object && ! in_array( (int) $queried_object->term_id, $digital_cats, true ) ) {
                $parent_id = $queried_object->parent;
                while ( $parent_id ) {
                    if ( in_array( (int) $parent_id, $digital_cats, true ) ) {
                        $should_redirect = true;
                        $target_url = get_term_link( $parent_id, 'product_cat' );
                        break;
                    }
                    $term = get_term( $parent_id, 'product_cat' );
                    $parent_id = ( $term && ! is_wp_error( $term ) ) ? $term->parent : 0;
                }
                if ( ! $should_redirect ) $should_redirect = true;
            }
        } elseif ( is_product_tag() ) {
            $queried_object = get_queried_object();
            $digital_tags = get_option( 'muyu_digital_tag_ids', [] );
            $digital_tags = array_map( 'intval', $digital_tags );
            
            if ( $queried_object && ! in_array( (int) $queried_object->term_id, $digital_tags, true ) ) {
                $should_redirect = true;
            }
        } elseif ( is_product() ) {
            global $post;
            $digital_ids = get_option( 'muyu_digital_product_ids', [] );
            $digital_ids = array_map( 'intval', $digital_ids );
            
            if ( $post && ! in_array( (int) $post->ID, $digital_ids, true ) ) {
                $should_redirect = true;
                $redirect_map = get_option( 'muyu_phys_to_dig_map', [] );
                
                if ( isset( $redirect_map[ $post->ID ] ) ) {
                    $target_url = get_permalink( $redirect_map[ $post->ID ] );
                } else {
                    $digital_cats = get_option( 'muyu_digital_category_ids', [] );
                    $digital_cats = array_map( 'intval', $digital_cats );
                    $product_cats = wp_get_post_terms( $post->ID, 'product_cat', [ 'fields' => 'ids' ] );
                    
                    if ( ! empty( $product_cats ) && ! is_wp_error( $product_cats ) ) {
                        foreach ( $product_cats as $cat_id ) {
                            if ( in_array( (int) $cat_id, $digital_cats, true ) ) {
                                $target_url = get_term_link( (int) $cat_id, 'product_cat' );
                                break;
                            }
                        }
                        if ( empty( $target_url ) ) {
                            foreach ( $product_cats as $cat_id ) {
                                $ancestors = get_ancestors( $cat_id, 'product_cat', 'taxonomy' );
                                foreach ( $ancestors as $ancestor_id ) {
                                    if ( in_array( (int) $ancestor_id, $digital_cats, true ) ) {
                                        $target_url = get_term_link( (int) $ancestor_id, 'product_cat' );
                                        break 2;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        if ( $should_redirect ) {
            global $post;
            if ( empty( $target_url ) || is_wp_error( $target_url ) ) {
                if ( is_product() && isset( $post->post_title ) ) {
                    $target_url = home_url( '/?s=' . urlencode( $post->post_title ) . '&post_type=product' );
                } else {
                    $target_url = wc_get_page_permalink( 'shop' );
                }
            }
            
            if ( function_exists( 'insertar_prefijo_idioma' ) && function_exists( 'muyu_country_language_prefix' ) ) {
                $prefix = muyu_country_language_prefix( muyu_get_user_country_code() );
                if ( $prefix ) {
                    $target_url = insertar_prefijo_idioma( $target_url, $prefix );
                }
            }
            
            wp_redirect( $target_url, 302 );
            exit;
        }
    }
}
add_action( 'template_redirect', 'muyu_handle_redirects', 20 );

// ----------------------------------------------------------------------
// HOOKS: VARIACIONES Y AUTO-SELECCIÓN
// ----------------------------------------------------------------------

if ( ! function_exists( 'muyu_hide_physical_variation' ) ) {
    function muyu_hide_physical_variation( $visible, $variation_id, $product_id, $variation ) {
        if ( ! $visible || ! muyu_is_restricted_user() ) return $visible;
        
        $attributes = $variation->get_attributes();
        $physical_term = get_term( MUYU_PHYSICAL_FORMAT_ID, 'pa_formato' );
        
        if ( $physical_term && ! is_wp_error( $physical_term ) ) {
            if ( isset( $attributes['pa_formato'] ) && $attributes['pa_formato'] === $physical_term->slug ) {
                return false;
            }
        }
        return $visible;
    }
}
add_filter( 'woocommerce_variation_is_visible', 'muyu_hide_physical_variation', 10, 4 );

if ( ! function_exists( 'muyu_clean_variation_dropdown' ) ) {
    function muyu_clean_variation_dropdown( $args ) {
        if ( ! muyu_is_restricted_user() || ! isset( $args['attribute'] ) || 'pa_formato' !== $args['attribute'] ) return $args;
        if ( empty( $args['options'] ) ) return $args;
        
        $physical_term = get_term( MUYU_PHYSICAL_FORMAT_ID, 'pa_formato' );
        if ( ! $physical_term || is_wp_error( $physical_term ) ) return $args;
        
        foreach ( $args['options'] as $key => $option ) {
            if ( ( is_object( $option ) && isset( $option->term_id ) && $option->term_id == MUYU_PHYSICAL_FORMAT_ID ) ||
                 ( is_string( $option ) && $option === $physical_term->slug ) ) {
                unset( $args['options'][ $key ] );
            }
        }
        return $args;
    }
}
add_filter( 'woocommerce_dropdown_variation_attribute_options_args', 'muyu_clean_variation_dropdown', 10, 1 );

if ( ! function_exists( 'muyu_filter_variation_prices' ) ) {
    function muyu_filter_variation_prices( $prices_array, $product, $for_display ) {
        if ( ! muyu_is_restricted_user() || empty( $prices_array['price'] ) ) return $prices_array;
        
        $physical_term = get_term( MUYU_PHYSICAL_FORMAT_ID, 'pa_formato' );
        if ( ! $physical_term || is_wp_error( $physical_term ) ) return $prices_array;
        
        foreach ( $prices_array['price'] as $variation_id => $amount ) {
            $format_slug = get_post_meta( $variation_id, 'attribute_pa_formato', true );
            if ( $format_slug === $physical_term->slug ) {
                unset( $prices_array['price'][ $variation_id ], $prices_array['regular_price'][ $variation_id ], $prices_array['sale_price'][ $variation_id ] );
            }
        }
        return $prices_array;
    }
}
add_filter( 'woocommerce_variation_prices', 'muyu_filter_variation_prices', 10, 3 );

if ( ! function_exists( 'muyu_set_format_default' ) ) {
    function muyu_set_format_default( $defaults, $product ) {
        $is_restricted = muyu_is_restricted_user();
        $country       = muyu_get_user_country_code();
        
        if ( $is_restricted ) {
            $term_id = MUYU_DIGITAL_FORMAT_ID;
        } elseif ( 'AR' === $country ) {
            $term_id = MUYU_PHYSICAL_FORMAT_ID;
        } else {
            return $defaults;
        }
        
        $term = get_term( $term_id, 'pa_formato' );
        if ( $term && ! is_wp_error( $term ) ) {
            $defaults['pa_formato'] = $term->slug;
        }
        return $defaults;
    }
}
add_filter( 'woocommerce_product_get_default_attributes', 'muyu_set_format_default', 20, 2 );

if ( ! function_exists( 'muyu_autoselect_format_variation' ) ) {
    function muyu_autoselect_format_variation() {
        global $product;
        if ( ! $product || ! $product->is_type( 'variable' ) ) return;

        $is_restricted  = muyu_is_restricted_user();
        $country        = muyu_get_user_country_code();

        if ( $is_restricted ) {
            $target_term_id = MUYU_DIGITAL_FORMAT_ID;
            $hide_row       = true;
        } elseif ( 'AR' === $country ) {
            $target_term_id = MUYU_PHYSICAL_FORMAT_ID;
            $hide_row       = false;
        } else {
            return;
        }

        $attributes = $product->get_variation_attributes();
        if ( ! isset( $attributes['pa_formato'] ) ) return;

        $target_term = get_term( $target_term_id, 'pa_formato' );
        if ( ! $target_term || is_wp_error( $target_term ) ) return;
        if ( ! in_array( $target_term->slug, $attributes['pa_formato'], true ) ) return;

        // Data bridge para js/shop.js
        printf(
            '<span id="mu-format-autoselect-data" style="display:none" data-target-slug="%s" data-hide-row="%s"></span>',
            esc_attr( $target_term->slug ),
            $hide_row ? 'true' : 'false'
        );
    }
}
add_action( 'woocommerce_before_add_to_cart_button', 'muyu_autoselect_format_variation', 5 );
