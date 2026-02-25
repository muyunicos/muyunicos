<?php
/**
 * Module: Orders - File Manager
 * Description: Gestión eficiente de archivos adjuntos a items de pedido (Admin/Frontend).
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ==========================================
// 1. CONFIGURACIÓN & HELPERS
// ==========================================

if ( ! defined( 'MUYUNICOS_TEMPLATE_DIR' ) ) {
    $upload_dir = wp_upload_dir();
    define( 'MUYUNICOS_TEMPLATE_DIR', $upload_dir['basedir'] . '/pdf-templates/' );
}

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

// ==========================================
// 2. LÓGICA DE ADMINISTRACIÓN (BACKEND)
// ==========================================

if ( is_admin() ) {

    /**
     * Carga scripts de WP Media y Assets del Módulo.
     */
    add_action( 'admin_enqueue_scripts', function() {
        if ( muyunicos_is_order_edit_screen() ) {
            wp_enqueue_media();

            $uri = get_stylesheet_directory_uri();
            $ver = wp_get_theme()->get( 'Version' );

            wp_enqueue_style( 'mu-admin-order-files', $uri . '/css/admin-order-files.css', [], $ver );
            wp_enqueue_script( 'mu-admin-order-files-js', $uri . '/js/admin-order-files.js', [ 'jquery' ], $ver, true );

            wp_localize_script( 'mu-admin-order-files-js', 'muOrderFilesData', [
                'nonce'   => wp_create_nonce( 'muyunicos_pdf_actions' ),
                'ajaxurl' => admin_url( 'admin-ajax.php' )
            ]);
        }
    });

    /**
     * Cabecera de tabla en items.
     */
    add_action( 'woocommerce_admin_order_item_headers', function() {
        echo '<th class="item_pdf sortable" style="width: 200px; text-align: left;">Archivos Cliente</th>';
    });

    /**
     * Contenido de la celda en items.
     */
    add_action( 'woocommerce_admin_order_item_values', 'muyunicos_render_admin_controls', 10, 3 );

    function muyunicos_render_admin_controls( $product, $item, $item_id ) {
        // Optimización: Obtener meta una sola vez
        $archivos = $item->get_meta( '_urls_files', true );
        $has_files = ! empty( $archivos );
        
        // Si no es virtual y no tiene archivos, mostrar guion para reducir ruido visual
        if ( ( $product && ! $product->is_virtual() ) && ! $has_files ) {
            echo '<td><small class="description">-</small></td>';
            return;
        }

        // Normalizar array
        if ( ! is_array( $archivos ) ) $archivos = [];

        // Chequeo ligero de existencia de template (evita file_exists si no hay SKU)
        $has_template = ( $product && $product->get_sku() ) ? file_exists( MUYUNICOS_TEMPLATE_DIR . $product->get_sku() . '.pdf' ) : false;
        
        ?>
        <td class="item_pdf item_pdf--unified">
            <div class="pdf-controls-wrapper" id="pdf-controls-wrapper-<?php echo esc_attr($item_id); ?>" style="position:relative;">
                
                <?php if ( $has_template ): ?>
                <div class="generate-controls" style="margin-bottom: 5px;">
                    <button type="button" class="button button-small generar-pdf-ajax-btn" data-item-id="<?php echo esc_attr($item_id); ?>">
                        <span class="dashicons dashicons-media-document" style="font-size:14px;line-height:1.8;"></span> Generar PDF
                    </button>
                    <div class="feedback-msg feedback-gen-<?php echo esc_attr($item_id); ?>" style="display:none; font-size: 10px; margin-top:2px;"></div>
                </div>
                <?php endif; ?>

                <div class="upload-controls">
                    <div class="muyunicos-dropzone" data-item-id="<?php echo esc_attr($item_id); ?>">
                        <input type="file" class="muyunicos-file-input" style="display:none;">
                        <div style="display:flex; gap:5px; align-items:center;">
                            <button type="button" class="button button-small select-file-btn" title="Biblioteca">
                                <span class="dashicons dashicons-admin-media" style="font-size:14px;line-height:1.8;"></span>
                            </button>
                            <span style="font-size: 10px; color: #666; cursor:pointer;" class="trigger-upload">Subir / Arrastrar</span>
                        </div>
                    </div>
                    <div class="feedback-msg feedback-up-<?php echo esc_attr($item_id); ?>" style="display:none; font-size: 10px; color:green;"></div>
                </div>

                <div class="manage-controls" style="margin-top: 5px;">
                    <button type="button" class="button button-small administar-btn" 
                            style="width: 100%;"
                            data-item-id="<?php echo esc_attr($item_id); ?>" 
                            data-archivos="<?php echo esc_attr( json_encode( $archivos ) ); ?>" 
                            <?php disabled( empty( $archivos ) ); ?>>
                        Archivos (<span class="file-count"><?php echo count( $archivos ); ?></span>)
                    </button>
                </div>
            </div>
        </td>
        <?php
    }

    /**
     * Inyecta el Modal HTML en el footer de admin (solo si estamos en edición de pedido)
     */
    add_action( 'admin_footer', 'muyunicos_print_admin_modal' );
    
    function muyunicos_print_admin_modal() {
        if ( ! muyunicos_is_order_edit_screen() ) return;
        ?>
        <div id="mu-file-manager-modal" class="mu-modal">
            <div class="mu-modal-box">
                <span class="mu-close">&times;</span>
                <h3 style="margin-top:0;">Archivos del Cliente</h3>
                <ul class="mu-file-list"></ul>
                <p class="mu-empty" style="display:none; text-align:center; color:#777; margin-top:15px;">Sin archivos.</p>
            </div>
        </div>
        <?php
    }
}

// ==========================================
// 3. AJAX HANDLERS (Optimizados y Seguros)
// ==========================================

add_action( 'wp_ajax_mu_add_media_file', 'muyunicos_ajax_add_file' );
add_action( 'wp_ajax_mu_upload_file', 'muyunicos_ajax_upload_file' );
add_action( 'wp_ajax_mu_delete_file', 'muyunicos_ajax_delete_file' );
add_action( 'wp_ajax_mu_generate_pdf', 'muyunicos_ajax_generate_pdf' );

function mu_check_permissions() {
    check_ajax_referer( 'muyunicos_pdf_actions', 'security' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( [ 'message' => 'Sin permisos' ], 403 );
}

function muyunicos_ajax_add_file() {
    mu_check_permissions();
    $item_id = intval( $_POST['item_id'] );
    $file_url = esc_url_raw( $_POST['file_url'] );
    mu_update_item_files( $item_id, $file_url, 'add' );
}

function muyunicos_ajax_upload_file() {
    mu_check_permissions();
    if ( empty( $_FILES['file'] ) ) wp_send_json_error( [ 'message' => 'Sin archivo' ] );

    if ( ! function_exists( 'wp_handle_upload' ) ) require_once( ABSPATH . 'wp-admin/includes/file.php' );
    $uploaded = wp_handle_upload( $_FILES['file'], [ 'test_form' => false ] );

    if ( isset( $uploaded['error'] ) ) wp_send_json_error( [ 'message' => $uploaded['error'] ] );

    mu_update_item_files( intval( $_POST['item_id'] ), $uploaded['url'], 'add' );
}

function muyunicos_ajax_delete_file() {
    mu_check_permissions();
    mu_update_item_files( intval( $_POST['item_id'] ), esc_url_raw( $_POST['file_url'] ), 'remove' );
}

function muyunicos_ajax_generate_pdf() {
    mu_check_permissions();
    $item_id = intval( $_POST['item_id'] );
    $image_id = intval( $_POST['image_id'] );
    $item = WC_Order_Factory::get_order_item( $item_id );
    
    // Stub: Solo ejecuta si la clase existe (evita errores fatales)
    if ( class_exists( 'MuyUnicos_PDF_Generator' ) && $item ) {
        MuyUnicos_PDF_Generator::generate( $item->get_order(), $item, $image_id );
        // Recargar meta fresco
        $item->read_meta_data();
        $files = $item->get_meta( '_urls_files', true ) ?: [];
        wp_send_json_success( [ 'all_files' => $files ] );
    } else {
        wp_send_json_error( [ 'message' => 'Generador PDF no activo' ] );
    }
}

/**
 * Helper centralizado para actualizar meta
 */
function mu_update_item_files( $item_id, $file_url, $action ) {
    $item = WC_Order_Factory::get_order_item( $item_id );
    if ( ! $item ) wp_send_json_error( [ 'message' => 'Item inválido' ] );

    $files = $item->get_meta( '_urls_files', true );
    if ( ! is_array( $files ) ) $files = [];

    if ( $action === 'add' && ! in_array( $file_url, $files ) ) {
        $files[] = $file_url;
        $item->get_order()->add_order_note( "Archivo añadido a item {$item_id}: " . basename($file_url) );
    } elseif ( $action === 'remove' ) {
        $key = array_search( $file_url, $files );
        if ( $key !== false ) unset( $files[$key] );
        $files = array_values( $files ); // Reindexar
    }

    $item->update_meta_data( '_urls_files', $files );
    $item->save();
    wp_send_json_success( [ 'all_files' => $files ] );
}


// ==========================================
// 4. FRONTEND (MI CUENTA & EMAILS)
// ==========================================

/**
 * Emails: Solo se engancha en correos de Pedido Completado o Factura.
 */
add_action( 'woocommerce_email_order_details', function( $order, $sent_to_admin, $plain_text, $email ) {
    if ( $sent_to_admin || $plain_text || ! $email ) return;
    if ( ! in_array( $email->id, [ 'customer_completed_order', 'customer_invoice' ] ) ) return;

    $html_files = '';
    foreach ( $order->get_items() as $item ) {
        $files = $item->get_meta( '_urls_files', true );
        if ( ! empty( $files ) && is_array( $files ) ) {
            foreach ( $files as $url ) {
                $html_files .= sprintf(
                    '<li style="margin-bottom:5px;"><strong>%s:</strong> <a href="%s" download>Descargar</a></li>',
                    esc_html( $item->get_name() . ' (' . basename($url) . ')' ),
                    esc_url( $url )
                );
            }
        }
    }

    if ( $html_files ) {
        echo '<div style="margin: 20px 0; border:1px solid #eee; padding:15px; background:#fafafa;">';
        echo '<h3 style="margin-top:0;">Archivos Disponibles</h3><ul style="padding-left:0; list-style:none;">' . $html_files . '</ul>';
        
        // --- UX RESTAURADA V2.5 ---
        echo '<p style="margin-top: 15px;">';
        echo '<a href="' . esc_url( wc_get_page_permalink('myaccount') ) . 'downloads/" target="_blank" style="display: inline-block; padding: 10px 15px; background-color: #eee; color: #333; text-decoration: none; border-radius: 5px; font-size:12px;">Ver todas mis descargas</a>';
        echo '</p>';
        // -------------------------

        echo '</div>';
    }
}, 25, 4 );


/**
 * Mi Cuenta > Descargas
 * OPTIMIZACIÓN CRÍTICA: Limitado a los últimos 20 pedidos para no matar la RAM.
 */
add_action( 'woocommerce_account_downloads_endpoint', function() {
    $user_id = get_current_user_id();
    if ( ! $user_id ) return;

    // Optimización: Limitar consulta a 20 pedidos recientes
    $orders = wc_get_orders([
        'customer_id' => $user_id,
        'status' => ['completed', 'processing'],
        'limit' => 20, 
        'orderby' => 'date',
        'order' => 'DESC'
    ]);

    if ( ! $orders ) return;

    $display_rows = [];
    foreach ( $orders as $order ) {
        foreach ( $order->get_items() as $item ) {
            $files = $item->get_meta( '_urls_files', true );
            if ( ! empty( $files ) && is_array( $files ) ) {
                foreach ( $files as $url ) {
                    $display_rows[] = [
                        'date' => $order->get_date_created()->date_i18n('d/m/Y'),
                        'product' => $item->get_name(),
                        'file' => basename($url),
                        'url' => $url
                    ];
                }
            }
        }
    }

    if ( empty( $display_rows ) ) return;
    ?>
    <div class="mu-custom-downloads">
        <h3>Archivos Personalizados (Recientes)</h3>
        <table class="woocommerce-table shop_table shop_table_responsive">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Producto</th>
                    <th>Archivo</th>
                    <th>&nbsp;</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $display_rows as $row ) : ?>
                <tr>
                    <td data-title="Fecha"><?php echo esc_html($row['date']); ?></td>
                    <td data-title="Producto"><small><?php echo esc_html($row['product']); ?></small></td>
                    <td data-title="Archivo"><?php echo esc_html(mb_strimwidth($row['file'], 0, 20, '...')); ?></td>
                    <td><a href="<?php echo esc_url($row['url']); ?>" class="button" target="_blank" download>Descargar</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}, 5 );
