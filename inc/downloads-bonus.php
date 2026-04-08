<?php
/**
 * Module: Downloads Bonus & Guides
 * Description: Inyección dinámica de archivo "Líneas de Corte" + Guía de Uso para productos Cat. 18.
 * Version: 1.2.0
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
     *
     * FIX v1.1.3: limit cambiado de -1 a 50 (últimas 50 órdenes DESC) para evitar
     * cargar arrays de cientos de IDs en RAM con tráfico concurrente.
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
            'limit'       => 50,
            'orderby'     => 'date',
            'order'       => 'DESC',
            'return'      => 'ids',
        ] );

        foreach ( $orders as $order_id ) {
            if ( function_exists( 'mu_order_has_virtual_manual_item' ) && mu_order_has_virtual_manual_item( $order_id ) ) {
                $has_virtual_manual = true;
                break;
            }
        }

        update_user_meta( $user_id, '_mu_has_virtual_manual', $has_virtual_manual ? 'yes' : 'no' );
        return $has_virtual_manual;
    }
}

if ( ! function_exists( 'mu_clear_virtual_manual_cache' ) ) {
    /**
     * Limpia el caché del usuario cuando el pedido cambia a un estado activo.
     */
    function mu_clear_virtual_manual_cache( $order_id, $from, $to, $order ) {
        if ( in_array( $to, [ 'processing', 'completed', 'production' ] ) ) {
            $user_id = $order->get_customer_id();
            if ( $user_id ) {
                delete_user_meta( $user_id, '_mu_has_virtual_manual' );
                delete_transient( 'mu_cat18_files_' . $user_id );
            }
        }
    }
    add_action( 'woocommerce_order_status_changed', 'mu_clear_virtual_manual_cache', 10, 4 );
}

if ( ! function_exists( 'mu_user_has_cat_18_download' ) ) {
    /**
     * Evalúa si dentro de las descargas proporcionadas existe un producto de Categoría 18.
     */
    function mu_user_has_cat_18_download( $downloads ) {
        if ( empty( $downloads ) || ! is_array( $downloads ) ) return false;

        foreach ( $downloads as $dl ) {
            $product_id = isset( $dl['product_id'] ) ? $dl['product_id'] : 0;
            if ( ! $product_id ) continue;

            $product = wc_get_product( $product_id );
            if ( $product ) {
                $parent_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
                if ( has_term( 18, 'product_cat', $parent_id ) ) {
                    return true;
                }
            }
        }
        return false;
    }
}

if ( ! function_exists( 'mu_user_has_cat_18_custom_files' ) ) {
    /**
     * Evalúa si el usuario tiene pedidos con archivos personalizados pertenecientes a la Categoría 18.
     *
     * FIX v1.2.0: Migrado de static $cache (solo in-request) a transient con TTL de 12h.
     * Se invalida automáticamente en mu_clear_virtual_manual_cache() al cambiar estado del pedido.
     */
    function mu_user_has_cat_18_custom_files( $user_id ) {
        if ( ! $user_id ) return false;

        $transient_key = 'mu_cat18_files_' . $user_id;
        $cached        = get_transient( $transient_key );

        if ( $cached === 'yes' ) return true;
        if ( $cached === 'no' )  return false;

        $has_cat_18 = false;
        $orders     = wc_get_orders( [
            'customer_id' => $user_id,
            'status'      => [ 'completed', 'processing' ],
            'limit'       => 20,
        ] );

        foreach ( $orders as $order ) {
            foreach ( $order->get_items() as $item ) {
                $files = $item->get_meta( '_urls_files', true );
                if ( ! empty( $files ) && is_array( $files ) ) {
                    $product = $item->get_product();
                    if ( $product ) {
                        $parent_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
                        if ( has_term( 18, 'product_cat', $parent_id ) ) {
                            $has_cat_18 = true;
                            break 2;
                        }
                    }
                }
            }
        }

        set_transient( $transient_key, $has_cat_18 ? 'yes' : 'no', 12 * HOUR_IN_SECONDS );
        return $has_cat_18;
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
// 2 & 4. INYECCIÓN DE GUÍA Y BONUS EN MI CUENTA > DESCARGAS
// ==========================================

if ( ! function_exists( 'mu_inject_downloads_and_guides_table' ) ) {
    /**
     * Modifica el nombre de las descargas para agregar guía (Cat. 18) y agrega bono si aplica.
     *
     * FIX v1.2.0: Eliminado style="" inline del link de guía. La clase .mu-guide-link
     * ahora vive en css/account-downloads.css (cargado condicionalmente en esa página).
     */
    function mu_inject_downloads_and_guides_table( $downloads ) {
        $user_id = get_current_user_id();

        // 1. Inyectar links a la guía
        if ( ! empty( $downloads ) && is_array( $downloads ) ) {
            foreach ( $downloads as $key => $download ) {
                $product_id = isset( $download['product_id'] ) ? $download['product_id'] : 0;
                if ( ! $product_id ) continue;
                $product = wc_get_product( $product_id );

                if ( mu_product_is_cat_18_virtual( $product ) ) {
                    if ( strpos( $downloads[ $key ]['download_name'], '(📖 Ver Guía)' ) === false ) {
                        $guide_link = ' <a href="https://muyunicos.com/guia-etiquetas-personalizada/" target="_blank" class="mu-guide-link">(📖 Ver Guía)</a>';
                        $downloads[ $key ]['download_name'] .= $guide_link;
                    }
                }
            }
        }

        // 2. Inyectar archivo bonus
        if ( $user_id && ! isset( $downloads['mu_bonus_lineas_corte'] ) ) {
            $has_cat_18 = mu_user_has_cat_18_download( $downloads ) || mu_user_has_cat_18_custom_files( $user_id );

            if ( $has_cat_18 && mu_user_has_virtual_manual_purchases( $user_id ) ) {
                $downloads['mu_bonus_lineas_corte'] = [
                    'download_url'        => 'https://muyunicos.com/wp-content/uploads/2026/02/Lineas-de-Corte-Etiquetas-Escolares-Muy-Unicos.zip',
                    'download_id'         => 'mu_bonus_lineas_corte',
                    'product_id'          => 0,
                    'product_name'        => 'Líneas de Corte - Etiquetas Escolares (Bonus)',
                    'download_name'       => 'Líneas de Corte - Etiquetas Escolares',
                    'order_id'            => 0,
                    'order_key'           => '',
                    'downloads_remaining' => '',
                    'access_expires'      => '',
                    'file'                => [
                        'name' => 'Líneas de Corte - Etiquetas Escolares',
                        'file' => 'https://muyunicos.com/wp-content/uploads/2026/02/Lineas-de-Corte-Etiquetas-Escolares-Muy-Unicos.zip',
                    ],
                ];
            }
        }

        return $downloads;
    }
    add_filter( 'woocommerce_customer_get_downloadable_products', 'mu_inject_downloads_and_guides_table', 10, 1 );
}

// ==========================================
// 3. INYECCIÓN DE GUÍA EN EMAILS (INLINE OBLIGATORIO)
// ==========================================

if ( ! function_exists( 'mu_inject_guide_in_email_item_name' ) ) {
    /**
     * Agrega el link de la guía inline después del nombre del producto en emails.
     * Los estilos inline son obligatorios en emails HTML (compatibilidad con clientes de correo).
     */
    function mu_inject_guide_in_email_item_name( $item_name, $item, $is_visible ) {
        if ( ! doing_action( 'woocommerce_email_order_details' ) ) {
            return $item_name;
        }

        $product = $item->get_product();
        if ( mu_product_is_cat_18_virtual( $product ) ) {
            $item_name .= ' <a href="https://muyunicos.com/guia-etiquetas-personalizada/" target="_blank" style="font-size: 0.85em; color: #2B9FCF; text-decoration: none;">(📖 Ver Guía)</a>';
        }

        return $item_name;
    }
    add_filter( 'woocommerce_order_item_name', 'mu_inject_guide_in_email_item_name', 10, 3 );
}

// ==========================================
// 5. INYECCIÓN DE BONUS EN EMAILS DE PEDIDO (INLINE OBLIGATORIO)
// ==========================================

if ( ! function_exists( 'mu_inject_bonus_in_emails' ) ) {
    /**
     * Agrega el enlace del bonus en correos de pedido completado/facturado.
     * Los estilos inline son obligatorios en emails HTML (compatibilidad con clientes de correo).
     */
    function mu_inject_bonus_in_emails( $order, $sent_to_admin, $plain_text, $email ) {
        if ( $sent_to_admin || $plain_text || ! $email ) return;

        if ( ! in_array( $email->id, [ 'customer_completed_order', 'customer_invoice' ] ) ) return;

        $user_id = $order->get_customer_id();
        if ( ! $user_id ) return;

        if ( ! mu_user_has_virtual_manual_purchases( $user_id ) ) return;

        $all_downloads = wc_get_customer_available_downloads( $user_id );
        $has_cat_18    = mu_user_has_cat_18_download( $all_downloads ) || mu_user_has_cat_18_custom_files( $user_id );

        if ( $has_cat_18 ) {
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
    add_action( 'woocommerce_email_order_details', 'mu_inject_bonus_in_emails', 26, 4 );
}
