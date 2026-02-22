<?php
/**
 * Muy Únicos - Funcionalidad del Carrito
 * 
 * Incluye:
 * - Add multiple products to cart
 * - BACS buffers (reemplazo de NUMERODEPEDIDO)
 * 
 * @package GeneratePress_Child
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================
// ADD MULTIPLE PRODUCTS TO CART
// ============================================

if ( ! function_exists( 'woo_add_multiple_products_to_cart' ) ) {
    /**
     * Permite agregar múltiples productos al carrito vía URL
     * Ejemplo: ?add-multiple=123,456,789
     */
    function woo_add_multiple_products_to_cart() {
        if ( empty( $_GET['add-multiple'] ) || ! function_exists( 'WC' ) ) return;
        
        if ( null === WC()->cart && function_exists( 'wc_load_cart' ) ) {
            wc_load_cart();
        }
        
        if ( null === WC()->cart ) return;

        $product_ids = explode( ',', sanitize_text_field( wp_unslash( $_GET['add-multiple'] ) ) );
        $productos_agregados = false;
        
        foreach ( $product_ids as $product_id ) {
            $product_id = absint( $product_id );
            if ( $product_id > 0 && WC()->cart->add_to_cart( $product_id ) ) {
                $productos_agregados = true;
            }
        }
        
        if ( $productos_agregados ) {
            wp_safe_redirect( wc_get_cart_url() );
            exit;
        }
    }
    add_action( 'wp_loaded', 'woo_add_multiple_products_to_cart' );
}

// ============================================
// BACS BUFFERS (Thank You Page)
// ============================================

if ( ! function_exists( 'bacs_buffer_start' ) ) {
    function bacs_buffer_start() {
        ob_start();
    }
    add_action( 'woocommerce_thankyou_bacs', 'bacs_buffer_start', 1 );
}

if ( ! function_exists( 'bacs_buffer_end' ) ) {
    function bacs_buffer_end( $order_id ) {
        $out = ob_get_clean();
        echo $order_id ? str_replace( 'NUMERODEPEDIDO', $order_id, $out ) : $out;
    }
    add_action( 'woocommerce_thankyou_bacs', 'bacs_buffer_end', 100, 1 );
}

// ============================================
// BACS BUFFERS (Email)
// ============================================

if ( ! function_exists( 'bacs_email_buffer_start' ) ) {
    function bacs_email_buffer_start( $o, $s, $pt, $e ) {
        if ( 'bacs' === $o->get_payment_method() && ! $pt ) {
            ob_start();
        }
    }
    add_action( 'woocommerce_email_before_order_table', 'bacs_email_buffer_start', 1, 4 );
}

if ( ! function_exists( 'bacs_email_buffer_end' ) ) {
    function bacs_email_buffer_end( $o, $s, $pt, $e ) {
        if ( 'bacs' === $o->get_payment_method() && ! $pt ) {
            echo str_replace( 'NUMERODEPEDIDO', $o->get_id(), ob_get_clean() );
        }
    }
    add_action( 'woocommerce_email_before_order_table', 'bacs_email_buffer_end', 100, 4 );
}
