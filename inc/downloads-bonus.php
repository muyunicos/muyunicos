<?php
/**
 * Module: Downloads Bonus & Guides
 * Description: Inyección dinámica de archivo "Líneas de Corte" + Guía de Uso para productos Cat. 18.
 * Version: 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ==========================================
// 1. HELPERS & CACHING
// ==========================================

if ( ! function_exists( 'mu_user_has_virtual_manual_purchases' ) ) {
    /**
     * Evalúa si un usuario ha comprado alguna vez un producto Virtual NO Descargable.
     * Utiliza un caché persistente en usermeta para evitar queries pesadas en frontend.
     */
    function mu_user_has_virtual_manual_purchases( $user_id ) {
        if ( ! $user_id ) return false;

        $cached = get_user_meta( $user_id, '_mu_has_virtual_manual', true );
        if ( $cached === 'yes' ) return true;
        if ( $cached === 'no' ) return false;

        $has_virtual_manual = false;
        
        $orders = wc_get_orders( [
            'customer_id' => $user_id,
            'status'      => [ 'completed', 'processing', 'production' ],
            'limit'       => -1,
            'return'      => 'ids',
        ] );

        foreach ( $orders as $order_id ) {
            // Reutiliza el helper de inc/orders-workflow.php si está disponible
            if ( function_exists('mu_order_has_virtual_manual_item') && mu_order_has_virtual_manual_item( $order_id ) ) {
                $has_virtual_manual = true;
                break;
            }
        }

        update_user_meta( $user_id, '_mu_has_virtual_manual', $has_virtual_manual ? 'yes' : 'no' );
        return $has_virtual_manual;
    }
}

/**
 * Limpia el caché del usuario cuando el pedido cambia a un estado activo.
 * Esto asegura que la regla sea retroactiva y en tiempo real.
 */
add_action( 'woocommerce_order_status_changed', 'mu_clear_virtual_manual_cache', 10, 4 );
function mu_clear_virtual_manual_cache( $order_id, $from, $to, $order ) {
    if ( in_array( $to, [ 'processing', 'completed', 'production' ] ) ) {
        $user_id = $order->get_customer_id();
        if ( $user_id ) {
            delete_user_meta( $user_id, '_mu_has_virtual_manual' );
        }
    }
}

if ( ! function_exists( 'mu_user_has_cat_18_download' ) ) {
    /**
     * Evalúa si dentro de las descargas proporcionadas existe un producto de Categoría 18.
     */
    function mu_user_has_cat_18_download( $downloads ) {
        if ( empty( $downloads ) || ! is_array( $downloads ) ) return false;

        foreach ( $downloads as $dl ) {
            $product_id = isset( $dl['product_id'] ) ? $dl['product_id'] : 0;
            $product = wc_get_product( $product_id );
            
            if ( $product ) {
                // Soportar tanto producto simple como variación
                $parent_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
                if ( has_term( 18, 'product_cat', $parent_id ) ) {
                    return true;
                }
            }
        }
        return false;
    }
}

if ( ! function_exists( 'mu_product_is_cat_18_virtual' ) ) {
    /**
     * Verifica si un producto (o su padre si es variación) pertenece a la categoría 18 y es virtual.
     */
    function mu_product_is_cat_18_virtual( $product ) {
        if ( ! $product ) return false;
        
        $parent_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
        return $product->is_virtual() && has_term( 18, 'product_cat', $parent_id );
    }
}

// ==========================================
// 2. INYECCIÓN DE GUÍA EN MI CUENTA > DESCARGAS
// ==========================================

/**
 * Modifica el nombre de las descargas para agregar un link inline a la guía si es Cat. 18 + Virtual.
 */
add_filter( 'woocommerce_account_downloads', 'mu_inject_guide_in_downloads_table', 10, 1 );
function mu_inject_guide_in_downloads_table( $downloads ) {
    if ( empty( $downloads ) ) return $downloads;

    foreach ( $downloads as $key => $download ) {
        $product_id = isset( $download['product_id'] ) ? $download['product_id'] : 0;
        $product = wc_get_product( $product_id );
        
        if ( mu_product_is_cat_18_virtual( $product ) ) {
            $guide_link = ' <a href="https://muyunicos.com/guia-etiquetas-personalizada/" target="_blank" style="font-size: 0.9em; color: #2B9FCF; text-decoration: none;">(📖 Ver Guía)</a>';
            $downloads[$key]['download_name'] .= $guide_link;
        }
    }

    return $downloads;
}

// ==========================================
// 3. INYECCIÓN DE GUÍA EN EMAILS (INLINE SUTIL)
// ==========================================

/**
 * Agrega el link de la guía inline después del nombre del producto en emails.
 * Hook: woocommerce_order_item_name (filtro del nombre del producto en emails).
 */
add_filter( 'woocommerce_order_item_name', 'mu_inject_guide_in_email_item_name', 10, 3 );
function mu_inject_guide_in_email_item_name( $item_name, $item, $is_visible ) {
    // Solo aplicar en emails (no en frontend)
    if ( ! is_email() && ! doing_action( 'woocommerce_email_order_details' ) ) {
        return $item_name;
    }

    $product = $item->get_product();
    if ( mu_product_is_cat_18_virtual( $product ) ) {
        $item_name .= ' <a href="https://muyunicos.com/guia-etiquetas-personalizada/" target="_blank" style="font-size: 0.85em; color: #2B9FCF; text-decoration: none;">(📖 Ver Guía)</a>';
    }

    return $item_name;
}

// ==========================================
// 4. INYECCIÓN DE BONUS EN MI CUENTA > DESCARGAS
// ==========================================

add_filter( 'woocommerce_customer_get_downloadable_products', 'mu_inject_bonus_download', 10, 1 );
function mu_inject_bonus_download( $downloads ) {
    $user_id = get_current_user_id();
    if ( ! $user_id ) return $downloads;

    // Prevención de inyección doble
    if ( isset( $downloads['mu_bonus_lineas_corte'] ) ) {
        return $downloads;
    }

    // El usuario debe cumplir AMBAS condiciones para recibir el archivo
    if ( mu_user_has_cat_18_download( $downloads ) && mu_user_has_virtual_manual_purchases( $user_id ) ) {
        $downloads['mu_bonus_lineas_corte'] = [
            'download_url' => 'https://muyunicos.com/wp-content/uploads/2026/02/Lineas-de-Corte-Etiquetas-Escolares-Muy-Unicos.zip',
            'download_id'  => 'mu_bonus_lineas_corte',
            'product_id'   => 0,
            'product_name' => 'Líneas de Corte - Etiquetas Escolares (Bonus)',
            'download_name'=> 'Líneas de Corte - Etiquetas Escolares',
            'order_id'     => 0,
            'order_key'    => '',
            'downloads_remaining' => '',
            'access_expires' => '',
            'file' => [
                'name' => 'Líneas de Corte - Etiquetas Escolares',
                'file' => 'https://muyunicos.com/wp-content/uploads/2026/02/Lineas-de-Corte-Etiquetas-Escolares-Muy-Unicos.zip'
            ]
        ];
    }

    return $downloads;
}

// ==========================================
// 5. INYECCIÓN DE BONUS EN EMAILS DE PEDIDO
// ==========================================

/**
 * Agregamos el enlace del bonus directamente en los correos de pedido completado/procesando.
 * Usamos prioridad 26 para que aparezca justo debajo de la inyección de "orders-files.php" (prioridad 25).
 */
add_action( 'woocommerce_email_order_details', 'mu_inject_bonus_in_emails', 26, 4 );
function mu_inject_bonus_in_emails( $order, $sent_to_admin, $plain_text, $email ) {
    if ( $sent_to_admin || $plain_text || ! $email ) return;
    
    // Solo mostramos el bonus si la orden fue recién completada o facturada
    if ( ! in_array( $email->id, [ 'customer_completed_order', 'customer_invoice' ] ) ) return;

    $user_id = $order->get_customer_id();
    if ( ! $user_id ) return;

    // Validación Condición A: Debe tener Virtual + Manual (puede ser de este u otro pedido)
    if ( ! mu_user_has_virtual_manual_purchases( $user_id ) ) return;

    // Validación Condición B: Debe tener un producto Categoría 18 (puede ser de este u otro pedido)
    $all_downloads = wc_get_customer_available_downloads( $user_id );
    if ( mu_user_has_cat_18_download( $all_downloads ) ) {
        
        $zip_url = 'https://muyunicos.com/wp-content/uploads/2026/02/Lineas-de-Corte-Etiquetas-Escolares-Muy-Unicos.zip';
        
        echo '<div style="margin: 20px 0; border: 1px solid #e5e5e5; padding: 15px; background: #fdfdfd; border-radius: 6px;">';
        echo '<h3 style="margin-top:0; color: #2B9FCF;">🎁 Archivo Adicional (Bonus)</h3>';
        echo '<p style="margin-bottom: 10px;">Como adquiriste etiquetas escolares y productos de diseño manual, te regalamos este recurso útil:</p>';
        echo '<ul style="padding-left:0; list-style:none; margin: 0;">';
        echo sprintf(
            '<li style="margin-bottom:0;"><strong>%s:</strong> <a href="%s" download style="color: #2B9FCF; text-decoration: underline;">Descargar ZIP</a></li>',
            esc_html( 'Líneas de Corte - Etiquetas Escolares' ),
            esc_url( $zip_url )
        );
        echo '</ul>';
        echo '</div>';
    }
}
