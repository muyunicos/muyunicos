<?php
/**
 * Module: Orders - Workflow & Statuses
 * Description: Estado "En Producción", lógica de emails inteligentes, y mejoras de UI en Admin de Pedidos.
 * Version: 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ==========================================
// 1. HELPERS
// ==========================================

if ( ! function_exists( 'muyunicos_is_order_edit_screen' ) ) {
    /**
     * Detecta si estamos en la pantalla de edición de pedidos (Compatible Legacy + HPOS).
     */
    function muyunicos_is_order_edit_screen() {
        if ( ! is_admin() ) return false;
        $screen = get_current_screen();
        if ( ! $screen ) return false;
        
        // IDs: 'shop_order' (Legacy Post Type) o 'woocommerce_page_wc-orders' (HPOS)
        return in_array( $screen->id, [ 'shop_order', 'woocommerce_page_wc-orders' ], true );
    }
}

if ( ! function_exists( 'mu_order_has_virtual_manual_item' ) ) {
    /**
     * Helper: Detecta si un pedido tiene al menos un ítem Virtual NO Descargable (requiere trabajo manual).
     * 
     * @param int|WC_Order $order
     * @return bool
     */
    function mu_order_has_virtual_manual_item( $order ) {
        if ( is_numeric( $order ) ) {
            $order = wc_get_order( $order );
        }
        if ( ! $order ) {
            return false;
        }

        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( ! $product ) continue;

            // Si es virtual pero NO es descargable -> requiere trabajo manual
            if ( $product->is_virtual() && ! $product->is_downloadable() ) {
                return true;
            }
        }
        return false;
    }
}

// ==========================================
// 2. ESTADO "EN PRODUCCIÓN"
// ==========================================

// Registrar Estado
add_action( 'init', 'muyunicos_register_production_status' );
function muyunicos_register_production_status() {
    register_post_status( 'wc-production', array(
        'label'                     => _x( 'En Producción', 'Order status', 'woocommerce' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'En Producción (%s)', 'En Producción (%s)', 'woocommerce' )
    ) );
}

// Agregar a lista visual
add_filter( 'wc_order_statuses', 'muyunicos_add_production_to_statuses' );
function muyunicos_add_production_to_statuses( $order_statuses ) {
    $new_order_statuses = array();
    foreach ( $order_statuses as $key => $status ) {
        $new_order_statuses[ $key ] = $status;
        if ( 'wc-processing' === $key ) {
            $new_order_statuses['wc-production'] = _x( 'En Producción', 'Order status', 'woocommerce' );
        }
    }
    return $new_order_statuses;
}

// Lógica de pago y reportes
add_filter( 'woocommerce_order_is_paid_statuses', 'muyunicos_production_is_paid' );
function muyunicos_production_is_paid( $statuses ) {
    $statuses[] = 'production';
    return $statuses;
}

add_filter( 'woocommerce_reports_order_statuses', 'muyunicos_production_in_reports' );
function muyunicos_production_in_reports( $statuses ) {
    $statuses[] = 'production';
    return $statuses;
}

// Acciones en Lote (Legacy)
add_filter( 'bulk_actions-edit-shop_order', 'muyunicos_bulk_action_production' );
function muyunicos_bulk_action_production( $bulk_actions ) {
    $bulk_actions['mark_production'] = __( 'Cambiar a En Producción', 'woocommerce' );
    return $bulk_actions;
}

// ==========================================
// 3. LOGICA VIRTUAL NO DESCARGABLE
// ==========================================

/**
 * Forzar 'Procesando' en productos Virtuales NO Descargables
 * Evita autocompletado en pasarelas.
 */
add_filter( 'woocommerce_payment_complete_order_status', 'mu_forzar_procesando_virtual_no_descargable', 20, 3 );

function mu_forzar_procesando_virtual_no_descargable( $status, $order_id, $order = null ) {
    // Usamos el helper refactorizado
    if ( mu_order_has_virtual_manual_item( $order ?: $order_id ) ) {
        return 'processing';
    }

    return $status;
}

/**
 * Indicador visual en Listado de Pedidos (Admin)
 * Agrega clase CSS a la fila del pedido si contiene ítems virtuales manuales.
 */
// Hook para Legacy (Post Type 'shop_order')
add_filter( 'post_class', 'mu_add_virtual_manual_indicator_class_legacy', 10, 3 );
function mu_add_virtual_manual_indicator_class_legacy( $classes, $class, $post_id ) {
    if ( ! is_admin() || get_post_type( $post_id ) !== 'shop_order' ) {
        return $classes;
    }
    
    // Verificamos si estamos en el loop principal de admin
    $screen = get_current_screen();
    if ( $screen && $screen->id === 'edit-shop_order' ) {
        if ( mu_order_has_virtual_manual_item( $post_id ) ) {
            $classes[] = 'mu-has-virtual-manual';
        }
    }
    return $classes;
}

// Hook para HPOS (High Performance Order Storage)
add_filter( 'woocommerce_shop_order_list_table_order_css_classes', 'mu_add_virtual_manual_indicator_class_hpos', 10, 2 );
function mu_add_virtual_manual_indicator_class_hpos( $classes, $order ) {
    if ( mu_order_has_virtual_manual_item( $order ) ) {
        $classes[] = 'mu-has-virtual-manual';
    }
    return $classes;
}


// ==========================================
// 4. NOTIFICACIÓN POR EMAIL INTELIGENTE
// ==========================================

add_action( 'woocommerce_order_status_changed', 'muyunicos_email_production_notification', 10, 4 );
function muyunicos_email_production_notification( $order_id, $from_status, $to_status, $order ) {
    if ( 'production' === $to_status ) {
        
        $has_physical = false;
        $has_digital  = false;

        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( $product ) {
                if ( $product->is_virtual() || $product->is_downloadable() ) {
                    $has_digital = true;
                } else {
                    $has_physical = true;
                }
            }
        }

        $msg_opening = __( 'Te confirmamos que ya hemos comenzado a trabajar en tu pedido. Tus personalizados se están preparando con mucha dedicación.', 'woocommerce' );

        if ( $has_physical && $has_digital ) {
            $msg_body = __( 'Te avisaremos cuando tu compra esté lista para envío o retiro. Además, recibirás un correo con los enlaces de descarga para tus archivos digitales.', 'woocommerce' );
        } elseif ( $has_digital ) {
            $msg_body = __( 'En cuanto terminemos el diseño y la preparación, recibirás un correo electrónico automático con los enlaces directos para descargar tus archivos.', 'woocommerce' );
        } else {
            $msg_body = __( 'En cuanto todo esté listo, recibirás una notificación de confirmación para el envío o para que pases a retirarlo, según la opción que elegiste al comprar.', 'woocommerce' );
        }

        $msg_closing = __( 'Si tienes alguna duda, puedes tocar el ícono de WhatsApp que encontrarás más abajo para hablar directamente con nosotros. ¡Gracias por elegir Muy Únicos!', 'woocommerce' );

        $mailer = WC()->mailer();
        $user_email = $order->get_billing_email();
        $first_name = $order->get_billing_first_name();
        
        $subject_txt = sprintf( __( 'Tu pedido #%s está en producción 🎨', 'woocommerce' ), $order->get_order_number() );
        $heading_txt = __( '¡Tu pedido está en marcha!', 'woocommerce' );
        
        $content = sprintf(
            '<p>%s %s,</p>\n            <p>%s</p>\n            <p>%s</p>\n            <hr style="border:0; border-top:1px solid #eee; margin: 20px 0;">\n            <p><small>%s</small></p>',
            __( 'Hola', 'woocommerce' ),
            esc_html( $first_name ),
            $msg_opening,
            $msg_body,
            $msg_closing
        );

        $message = $mailer->wrap_message( $heading_txt, $content );
        $mailer->send( $user_email, $subject_txt, $message );

        $order->add_order_note( __( 'Correo "En Producción" enviado (Tipo: ' . ($has_physical && $has_digital ? 'Mixto' : ($has_digital ? 'Digital' : 'Físico')) . ').', 'woocommerce' ) );
    }
}

// ==========================================
// 5. ASSETS ADMIN (CSS Badge + JS WhatsApp)
// ==========================================

add_action( 'admin_enqueue_scripts', function() {
    if ( muyunicos_is_order_edit_screen() ) {
        $uri = get_stylesheet_directory_uri();
        $ver = wp_get_theme()->get( 'Version' );

        wp_enqueue_style( 'mu-admin-orders', $uri . '/css/admin-orders.css', [], $ver );
        wp_enqueue_script( 'mu-admin-orders-js', $uri . '/js/admin-orders.js', [ 'jquery' ], $ver, true );

        // Pasar URL base de API a JS por si cambia en el futuro
        wp_localize_script( 'mu-admin-orders-js', 'muOrderWA', [
            'apiUrl' => 'https://api.whatsapp.com/send',
            'label'  => 'WhatsApp: '
        ]);
    }
});
