<?php
/**
 * Productos Personalizados — Addon Nombre v3.0
 *
 * Campo de nombre personalizado para etiquetas.
 * - Input en página de producto (categorías 18, 62, 19)
 * - Validación server-side
 * - Guardado en carrito y orden
 * - Editor inline en carrito (AJAX)
 *
 * Dependencia: inc/products-core.php (MU Core v2.1)
 * CSS: css/product-builder.css (§10, §11)
 * JS:  js/addon-nombre.js
 *
 * @package MuyUnicos
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Verificación de dependencias
if ( ! function_exists( 'mu_core_is_active' ) || ! mu_core_is_active() ) {
    return;
}

// ============================================================================
// 1. INPUT EN PÁGINA DE PRODUCTO
// ============================================================================

if ( ! function_exists( 'mu_nombre_agregar_campo' ) ) {
    add_action( 'woocommerce_before_add_to_cart_button', 'mu_nombre_agregar_campo', 15 );
    function mu_nombre_agregar_campo() {
        global $product;
        // Categorías ID 62 (Nombre), 19 (Etiquetas), 18 (Variables)
        if ( ! has_term( array( 18, 62, 19 ), 'product_cat', $product->get_id() ) ) {
            return;
        }

        $valor_actual = isset( $_POST['nombre_personalizado'] ) ? sanitize_text_field( $_POST['nombre_personalizado'] ) : '';
        ?>
        <!-- Usamos clases del Core (mu-o-section) para consistencia visual -->
        <div class="mu-o-section expanded" id="mu-nombre-container">
            <div class="mu-o-section-header">Nombre para las etiquetas</div>
            <div class="mu-o-section-content" style="display:block; padding:15px;">

                <label for="nombre_personalizado" style="font-weight: 600; margin-bottom: 8px; display: block;">
                    Escribí el nombre aquí: <span class="required" style="color:red">*</span>
                </label>

                <input type="text" id="nombre_personalizado" name="nombre_personalizado"
                       placeholder="Ej: Olivia Pinto" required
                       class="mu-text-input-nombre" maxlength="25"
                       value="<?php echo esc_attr( $valor_actual ); ?>" autocomplete="off">

                <div id="mu-nombre-error" class="mu-error-msg"></div>

                <div class="mu-case-controls">
                    <button type="button" class="mu-btn-case" onclick="muTransformName('title')" title="Primera Mayúscula">Aa</button>
                    <button type="button" class="mu-btn-case" onclick="muTransformName('upper')" title="MAYÚSCULAS">AA</button>
                </div>

                <div style="margin-top: 8px; font-size: 0.85em; color: var(--texto-light); line-height: 1.4;">
                    ℹ️ Incluiremos este nombre respetando mayúsculas y acentos tal cual lo escribas.
                </div>
            </div>
        </div>
        <?php
    }
}

// ============================================================================
// 2. GUARDAR DATOS Y MOSTRAR
// ============================================================================

// Guardar en sesión
if ( ! function_exists( 'mu_nombre_guardar_en_carrito' ) ) {
    add_filter( 'woocommerce_add_cart_item_data', 'mu_nombre_guardar_en_carrito', 10, 3 );
    function mu_nombre_guardar_en_carrito( $cart_item_data, $product_id, $variation_id ) {
        if ( isset( $_POST['nombre_personalizado'] ) && ! empty( $_POST['nombre_personalizado'] ) ) {
            $cart_item_data['nombre_personalizado'] = sanitize_text_field( $_POST['nombre_personalizado'] );
            $cart_item_data['unique_key_nombre']    = md5( microtime() . rand() . $cart_item_data['nombre_personalizado'] );
        }
        return $cart_item_data;
    }
}

// Mostrar en Carrito y Checkout
if ( ! function_exists( 'mu_nombre_mostrar_en_carrito' ) ) {
    add_filter( 'woocommerce_get_item_data', 'mu_nombre_mostrar_en_carrito', 10, 2 );
    function mu_nombre_mostrar_en_carrito( $item_data, $cart_item ) {
        if ( isset( $cart_item['nombre_personalizado'] ) ) {
            $item_data[] = array(
                'key'     => 'Nombre en etiquetas',
                'value'   => esc_html( $cart_item['nombre_personalizado'] ),
                'display' => '',
            );
        }
        return $item_data;
    }
}

// Guardar en el Pedido (Base de Datos)
if ( ! function_exists( 'mu_nombre_guardar_en_orden' ) ) {
    add_action( 'woocommerce_checkout_create_order_line_item', 'mu_nombre_guardar_en_orden', 10, 4 );
    function mu_nombre_guardar_en_orden( $item, $cart_item_key, $values, $order ) {
        if ( isset( $values['nombre_personalizado'] ) ) {
            $item->add_meta_data( 'Nombre', $values['nombre_personalizado'], true );
        }
    }
}

// Validación server-side
if ( ! function_exists( 'mu_nombre_validar_campo' ) ) {
    add_filter( 'woocommerce_add_to_cart_validation', 'mu_nombre_validar_campo', 20, 3 );
    function mu_nombre_validar_campo( $passed, $product_id, $quantity ) {
        if ( ! has_term( array( 18, 62, 19 ), 'product_cat', $product_id ) ) {
            return $passed;
        }

        $nombre = isset( $_POST['nombre_personalizado'] ) ? trim( $_POST['nombre_personalizado'] ) : '';

        if ( empty( $nombre ) || strlen( $nombre ) < 2 ) {
            wc_add_notice( '<strong>⚠️ Campo obligatorio:</strong> Por favor, escribe el nombre para tus etiquetas antes de añadir al carrito.', 'error' );
            return false;
        }

        return $passed;
    }
}

// ============================================================================
// 3. EDITOR EN LÍNEA EN EL CARRITO (HTML)
// ============================================================================

if ( ! function_exists( 'mu_nombre_cart_edit_html' ) ) {
    add_action( 'woocommerce_after_cart_item_name', 'mu_nombre_cart_edit_html', 10, 2 );
    function mu_nombre_cart_edit_html( $cart_item, $cart_item_key ) {
        if ( ! isset( $cart_item['nombre_personalizado'] ) ) {
            return;
        }
        $nombre_actual = esc_attr( $cart_item['nombre_personalizado'] );
        ?>
        <div class="mu-name-wrapper-hook" data-key="<?php echo esc_attr( $cart_item_key ); ?>">
            <div class="mu-name-view-mode">
                <button type="button" class="mu-name-trigger-edit" title="Corregir nombre">✎ Editar Nombre</button>
            </div>
            <div class="mu-name-edit-mode mu-name-force-hidden" style="display:none;">
                <input type="text" class="mu-name-input" value="<?php echo $nombre_actual; ?>" maxlength="25">
                <button type="button" class="mu-btn-save mu-icon-btn" title="Guardar">✓</button>
                <button type="button" class="mu-name-cancel-btn mu-icon-btn" title="Cancelar">✕</button>
            </div>
            <div class="mu-name-status-msg"></div>
        </div>
        <?php
    }
}

// ============================================================================
// 4. AJAX HANDLER
// ============================================================================

if ( ! function_exists( 'mu_update_cart_custom_name' ) ) {
    add_action( 'wp_ajax_update_cart_custom_name', 'mu_update_cart_custom_name' );
    add_action( 'wp_ajax_nopriv_update_cart_custom_name', 'mu_update_cart_custom_name' );
    function mu_update_cart_custom_name() {
        check_ajax_referer( 'update-cart-name', 'security' );

        $cart_item_key = sanitize_text_field( $_POST['cart_item_key'] );
        $new_name      = sanitize_text_field( $_POST['custom_name'] );

        if ( strlen( $new_name ) < 2 ) {
            wp_send_json_error( [ 'message' => 'Nombre muy corto' ] );
        }

        $cart = WC()->cart->get_cart();

        if ( isset( $cart[ $cart_item_key ] ) ) {
            $cart[ $cart_item_key ]['nombre_personalizado'] = $new_name;
            $cart[ $cart_item_key ]['unique_key_nombre']    = md5( microtime() . $new_name );

            WC()->cart->set_cart_contents( $cart );
            if ( isset( WC()->session ) ) {
                WC()->session->set( 'cart', $cart );
            }
            WC()->cart->calculate_totals();

            wp_send_json_success( [ 'new_name' => $new_name ] );
        } else {
            wp_send_json_error( [ 'message' => 'Producto no encontrado' ] );
        }
    }
}
