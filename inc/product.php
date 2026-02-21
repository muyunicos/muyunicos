<?php
/**
 * Muy Únicos - Lógica UX de Productos
 * 
 * Incluye:
 * - Vinculación Físico <-> Digital (mu_render_linked_product)
 * - Meta cache para performance
 * 
 * @package GeneratePress_Child
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================
// VINCULACIÓN PRODUCTOS FÍSICOS / DIGITALES
// ============================================

if ( ! function_exists( 'mu_render_linked_product' ) ) {
    /**
     * Muestra caja de navegación cruzada Físico <-> Digital
     * Usa meta cache (_mu_sibling_id / _mu_sibling_checked) para evitar queries repetidas
     */
    function mu_render_linked_product() {
        global $product, $wpdb;

        if ( ! is_product() || ! is_object( $product ) ) return;

        $product_id = $product->get_id();

        // FASE 1: META CACHE
        $sibling_id = get_post_meta( $product_id, '_mu_sibling_id', true );
        $is_checked = get_post_meta( $product_id, '_mu_sibling_checked', true );

        // IDs de configuración
        $cat_fisico = 19;
        $cat_imprimible = 62;
        $prod_pers_imp = 10708;
        $prod_pers_fis = 10279;

        if ( ! $is_checked ) {
            $slug = $product->get_slug();
            $is_printable = ( substr( $slug, -11 ) === '-imprimible' );
            $target_slug = $is_printable ? substr( $slug, 0, -11 ) : $slug . '-imprimible';

            $found_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type = 'product' AND post_status = 'publish' LIMIT 1",
                $target_slug
            ) );

            if ( $found_id ) {
                update_post_meta( $product_id, '_mu_sibling_id', $found_id );
                $sibling_id = $found_id;
            }

            update_post_meta( $product_id, '_mu_sibling_checked', 'yes' );
        }

        if ( ! $sibling_id ) return;

        // FASE 2: LÓGICA DE VISUALIZACIÓN
        $current_slug = $product->get_slug();
        $is_current_printable = ( substr( $current_slug, -11 ) === '-imprimible' );

        // Usa el helper CORE
        $is_argentina = ( muyu_get_current_country_from_subdomain() === 'AR' );

        $show_cross_link = false;
        $msg_intro = '';
        $msg_cta = '';
        $linked_url = get_permalink( $sibling_id );

        if ( $is_current_printable ) {
            if ( $is_argentina ) {
                $show_cross_link = true;
                $msg_intro = '¡Atención! Este es un producto digital, pero también te lo podemos ofrecer listo para usar:';
                $msg_cta = 'Tocá acá para acceder a la versión física impresa';
            }
            $url_catalogo = get_term_link( $cat_imprimible, 'product_cat' );
            $url_diseno = get_permalink( $prod_pers_imp );
            $text_diseno = 'armá tu diseño';
        } else {
            $show_cross_link = true;
            $msg_intro = 'Si necesitás la versión digital en PDF de este producto:';
            $msg_cta = 'Tocá acá para acceder a la versión descargable';
            $url_catalogo = get_term_link( $cat_fisico, 'product_cat' );
            $url_diseno = get_permalink( $prod_pers_fis );
            $text_diseno = 'diseñalo';
        }

        if ( is_wp_error( $url_catalogo ) ) $url_catalogo = '#';

        $is_customizable = in_array( $product_id, array( $prod_pers_imp, $prod_pers_fis ) );

        // FASE 3: RENDERIZADO
        ?>
        <div class="mu-linked-box">
            <?php if ( $show_cross_link ) : ?>
                <p class="mu-cross-p">
                    <?php echo esc_html( $msg_intro ); ?>
                    <a href="<?php echo esc_url( $linked_url ); ?>" class="mu-cross-a">
                        <?php echo esc_html( $msg_cta ); ?>
                    </a>
                </p>
            <?php endif; ?>

            <p class="mu-cat-p">
                <a href="<?php echo esc_url( $url_catalogo ); ?>">Ver catálogo completo de diseños</a>
                <?php if ( ! $is_customizable ) : ?>
                    o <?php echo esc_html( $text_diseno ); ?>
                    <a href="<?php echo esc_url( $url_diseno ); ?>">tocando acá</a>.
                <?php endif; ?>
            </p>
        </div>
        <?php
    }
}
add_action( 'woocommerce_single_product_summary', 'mu_render_linked_product', 25 );

// ============================================
// MOVER DESCRIPCIÓN DE CATEGORÍA
// ============================================

if ( ! function_exists( 'muyunicos_move_category_description' ) ) {
    function muyunicos_move_category_description() {
        if ( is_product_category() ) {
            remove_action( 'woocommerce_archive_description', 'woocommerce_taxonomy_archive_description', 10 );
            add_action( 'woocommerce_after_shop_loop', 'woocommerce_taxonomy_archive_description', 5 );
        }
    }
}
add_action( 'wp', 'muyunicos_move_category_description' );
