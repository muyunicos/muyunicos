<?php
/**
 * Muy Únicos — Sistema de Precio Flexible v4.0
 *
 * Permite que productos específicos tengan un precio definido
 * por el cliente en el carrito (donaciones, aportes, etc.).
 *
 * Lógica:  validación + captura + aplicación + AJAX handler.
 * Assets:  js/flexible-price.js + sección en css/cart.css.
 * Carga:   condicional is_cart() || is_checkout().
 *
 * @package GeneratePress_Child
 * @since   1.9.7
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================
// 0. CONFIGURACIÓN — IDs de productos con precio flexible
// ============================================================

if ( ! function_exists( 'mu_get_flexible_product_ids' ) ) {
    /**
     * Retorna un mapa hash de IDs para lookup O(1).
     * Actualizar este array al agregar/quitar productos.
     *
     * @return array<int, true>
     */
    function mu_get_flexible_product_ids() {
        return array( 1 => true, 2 => true );
    }
}

if ( ! function_exists( 'mu_is_flexible_product' ) ) {
    /**
     * @param int $product_id
     * @return bool
     */
    function mu_is_flexible_product( $product_id ) {
        $ids = mu_get_flexible_product_ids();
        return isset( $ids[ (int) $product_id ] );
    }
}

// ============================================================
// 1. LIMPIEZA DE URL  — evita que ?add-to-cart quede visible
// ============================================================

if ( ! function_exists( 'mu_flexible_price_clean_url' ) ) {
    function mu_flexible_price_clean_url() {
        if ( isset( $_GET['add-to-cart'] ) && mu_is_flexible_product( intval( $_GET['add-to-cart'] ) ) ) {
            wp_safe_redirect( remove_query_arg( array( 'add-to-cart', 'precio', 'quantity' ) ) );
            exit;
        }
    }
    add_action( 'template_redirect', 'mu_flexible_price_clean_url', 20 );
}

// ============================================================
// 2. VALIDACIÓN — precio negativo + instancia única en carrito
// ============================================================

if ( ! function_exists( 'mu_flexible_price_validate' ) ) {
    /**
     * @param bool $passed
     * @param int  $product_id
     * @return bool
     */
    function mu_flexible_price_validate( $passed, $product_id ) {
        if ( ! mu_is_flexible_product( $product_id ) ) {
            return $passed;
        }

        // A. Rechazar precio negativo
        if ( isset( $_GET['precio'] ) && floatval( $_GET['precio'] ) < 0 ) {
            wc_add_notice( '⚠️ El monto no puede ser negativo.', 'error' );
            return false;
        }

        // B. Garantizar instancia única: eliminar entradas previas del mismo producto
        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( (int) $cart_item['product_id'] === (int) $product_id ) {
                WC()->cart->remove_cart_item( $cart_item_key );
            }
        }

        return $passed;
    }
    add_filter( 'woocommerce_add_to_cart_validation', 'mu_flexible_price_validate', 10, 2 );
}

// ============================================================
// 3. CAPTURAR DATOS — precio personalizado + clave única
// ============================================================

if ( ! function_exists( 'mu_flexible_price_add_cart_item_data' ) ) {
    /**
     * @param array $cart_item_data
     * @param int   $product_id
     * @return array
     */
    function mu_flexible_price_add_cart_item_data( $cart_item_data, $product_id ) {
        if ( ! mu_is_flexible_product( $product_id ) ) {
            return $cart_item_data;
        }

        $custom_price = 0;

        if ( isset( $_GET['precio'] ) ) {
            $custom_price = wc_format_decimal( $_GET['precio'] );
        } elseif ( isset( $_POST['custom_price'] ) ) {
            $custom_price = wc_format_decimal( $_POST['custom_price'] );
        }

        $cart_item_data['custom_price'] = $custom_price;
        // uniqid() evita colisiones de clave de carrito para el mismo producto
        $cart_item_data['unique_key']   = uniqid( 'mu_', true );

        return $cart_item_data;
    }
    add_filter( 'woocommerce_add_cart_item_data', 'mu_flexible_price_add_cart_item_data', 10, 2 );
}

// ============================================================
// 4. APLICAR PRECIO — sobreescribir precio antes de totales
// ============================================================

if ( ! function_exists( 'mu_flexible_price_apply' ) ) {
    function mu_flexible_price_apply( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

        foreach ( $cart->get_cart() as $cart_item ) {
            if ( isset( $cart_item['custom_price'] ) ) {
                $cart_item['data']->set_price( floatval( $cart_item['custom_price'] ) );
            }
        }
    }
    add_action( 'woocommerce_before_calculate_totals', 'mu_flexible_price_apply' );
}

// ============================================================
// 5. GUARDAR EN LA ORDEN — metadatos para reportes y display
// ============================================================

if ( ! function_exists( 'mu_flexible_price_save_order_meta' ) ) {
    /**
     * @param \WC_Order_Item_Product $item
     * @param string                $cart_item_key
     * @param array                 $values
     */
    function mu_flexible_price_save_order_meta( $item, $cart_item_key, $values ) {
        if ( isset( $values['custom_price'] ) ) {
            // Valor raw para reportes / filtros de admin
            $item->add_meta_data( '_custom_price', $values['custom_price'], true );
            // Valor formateado para visualización en email y pedido
            $item->add_meta_data( 'Precio Acordado', wc_price( $values['custom_price'] ), true );
        }
    }
    add_action( 'woocommerce_checkout_create_order_line_item', 'mu_flexible_price_save_order_meta', 10, 3 );
}

// ============================================================
// 6. BLOQUEO EN CHECKOUT — impide completar sin monto definido
// ============================================================

if ( ! function_exists( 'mu_flexible_price_check_cart' ) ) {
    function mu_flexible_price_check_cart() {
        if ( ! is_cart() && ! is_checkout() ) return;

        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( ! mu_is_flexible_product( $cart_item['product_id'] ) ) continue;

            $price = isset( $cart_item['custom_price'] ) ? floatval( $cart_item['custom_price'] ) : 0;

            if ( $price <= 0 ) {
                wc_add_notice(
                    sprintf(
                        '⚠️ <strong>%s</strong> requiere definir un monto antes de continuar.',
                        esc_html( $cart_item['data']->get_name() )
                    ),
                    'error'
                );
            }
        }
    }
    add_action( 'woocommerce_check_cart_items', 'mu_flexible_price_check_cart' );
}

// ============================================================
// 7. INTERFAZ VISUAL EN CARRITO — widget edición de precio
// ============================================================

if ( ! function_exists( 'mu_flexible_price_cart_ui' ) ) {
    /**
     * Renderiza el widget de edición inline de precio en el carrito.
     *
     * @param array  $cart_item
     * @param string $cart_item_key
     */
    function mu_flexible_price_cart_ui( $cart_item, $cart_item_key ) {
        if ( ! isset( $cart_item['custom_price'] ) ) return;

        $product_id    = $cart_item['product_id'];
        $current_price = floatval( $cart_item['custom_price'] );
        $is_mandatory  = mu_is_flexible_product( $product_id );
        $edit_mode     = ( $current_price <= 0 );
        ?>
        <div class="mu-cp-wrapper" data-key="<?php echo esc_attr( $cart_item_key ); ?>">
            <div class="mu-cp-label">
                <?php echo $is_mandatory ? esc_html__( 'Monto:', 'generatepress-child' ) : esc_html__( 'Aporte:', 'generatepress-child' ); ?>
                <?php if ( $edit_mode ) : ?>
                    <span class="mu-cp-req"><?php esc_html_e( '(Requerido)', 'generatepress-child' ); ?></span>
                <?php endif; ?>
            </div>

            <div class="mu-cp-view<?php echo $edit_mode ? ' mu-hidden' : ''; ?>">
                <strong><?php echo wc_price( $current_price ); ?></strong>
                <button type="button" class="mu-cp-link">
                    <?php esc_html_e( '(Cambiar)', 'generatepress-child' ); ?>
                </button>
            </div>

            <div class="mu-cp-edit<?php echo $edit_mode ? '' : ' mu-hidden'; ?>">
                <input
                    type="number"
                    class="mu-cp-input"
                    value="<?php echo $current_price > 0 ? esc_attr( $current_price ) : ''; ?>"
                    step="any"
                    min="0.01"
                    placeholder="$"
                    aria-label="<?php esc_attr_e( 'Ingresar monto', 'generatepress-child' ); ?>"
                >
                <button
                    type="button"
                    class="mu-btn-save"
                    aria-label="<?php esc_attr_e( 'Confirmar monto', 'generatepress-child' ); ?>"
                >
                    <?php echo mu_get_icon( 'check' ); ?>
                </button>
            </div>

            <div class="mu-cp-msg" role="status" aria-live="polite"></div>
        </div>
        <?php
    }
    add_action( 'woocommerce_after_cart_item_name', 'mu_flexible_price_cart_ui', 10, 2 );
}

// ============================================================
// 8. AJAX HANDLER — actualización de precio desde el carrito
// ============================================================

if ( ! function_exists( 'mu_ajax_update_flexible_price' ) ) {
    function mu_ajax_update_flexible_price() {
        check_ajax_referer( 'mu-price-nonce', 'security' );

        $cart_item_key = sanitize_text_field( wp_unslash( $_POST['cart_item_key'] ?? '' ) );
        $custom_price  = wc_format_decimal( wp_unslash( $_POST['custom_price'] ?? 0 ) );

        if ( (float) $custom_price <= 0 ) {
            wp_send_json_error( array( 'message' => __( 'Valor inválido. Ingresá un monto mayor a cero.', 'generatepress-child' ) ) );
        }

        $cart = WC()->cart;

        if ( isset( $cart->cart_contents[ $cart_item_key ] ) ) {
            $cart->cart_contents[ $cart_item_key ]['custom_price'] = $custom_price;
            $cart->set_session();
            $cart->calculate_totals();
            wp_send_json_success();
        } else {
            wp_send_json_error( array( 'message' => __( 'Producto no encontrado en el carrito.', 'generatepress-child' ) ) );
        }
    }
    add_action( 'wp_ajax_mu_update_custom_price',        'mu_ajax_update_flexible_price' );
    add_action( 'wp_ajax_nopriv_mu_update_custom_price', 'mu_ajax_update_flexible_price' );
}

// ============================================================
// 9. ENQUEUE ASSETS — JS del widget (condicional carrito/checkout)
// ============================================================

if ( ! function_exists( 'mu_flexible_price_enqueue' ) ) {
    function mu_flexible_price_enqueue() {
        if ( ! is_cart() && ! is_checkout() ) return;

        wp_enqueue_script(
            'mu-flexible-price',
            get_stylesheet_directory_uri() . '/js/flexible-price.js',
            array( 'jquery' ),
            '4.0.0',
            true
        );

        wp_localize_script(
            'mu-flexible-price',
            'muFlexiblePrice',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'mu-price-nonce' ),
                'i18n'    => array(
                    'saving'      => __( 'Guardando...', 'generatepress-child' ),
                    'invalidAmt'  => __( 'Ingresá un monto válido mayor a cero.', 'generatepress-child' ),
                ),
            )
        );
    }
    add_action( 'wp_enqueue_scripts', 'mu_flexible_price_enqueue' );
}
