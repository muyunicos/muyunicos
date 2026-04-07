<?php
/**
 * Productos Personalizados — Core v2.1
 *
 * Motor central del sistema de productos personalizados.
 * - Constantes de versión
 * - MU_UI_Helper (renderizado de secciones y filas)
 * - Backend automático (carrito → orden)
 * - Lógica frontend para variaciones (precio dinámico)
 *
 * Carga: Siempre (hooks solo se ejecutan en contextos apropiados)
 *
 * @package MuyUnicos
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'MU_CORE_VERSION' ) ) {
    define( 'MU_CORE_VERSION', '2.1' );
    define( 'MU_CORE_ACTIVE', true );
}

if ( ! function_exists( 'mu_core_is_active' ) ) {
    function mu_core_is_active() {
        return defined( 'MU_CORE_ACTIVE' ) && MU_CORE_ACTIVE === true;
    }
}

// ============================================================================
// 1. HELPER DE UI (PHP) — Para uso en Addons
// ============================================================================

if ( ! class_exists( 'MU_UI_Helper' ) ) {

    class MU_UI_Helper {

        public static function render_section( $id, $title, $state, $content_callback ) {
            $expandClass = ( $state === 'expanded' ) ? 'expanded' : ( ( $state === 'fixed' ) ? 'fixed' : 'collapsed' );
            ?>
            <div class="mu-o-section <?php echo esc_attr( $expandClass ); ?>" id="section-<?php echo esc_attr( $id ); ?>">
                <div class="mu-o-section-header" onclick="MU.toggleSection('<?php echo esc_js( $id ); ?>')">
                    <span><?php echo $title; ?></span>
                    <span class="mu-toggle-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"></path></svg></span>
                </div>
                <div class="mu-o-section-content"><?php call_user_func( $content_callback ); ?></div>
            </div>
            <?php
        }

        public static function render_row( $key, $label, $desc, $price_tag_id, $on_update_js, $extras_callback = null, $info_html = null ) {
            ?>
            <div class="mu-row" id="row-<?php echo esc_attr( $key ); ?>">
                <div class="mu-row-main">
                    <div class="mu-info">
                        <span class="mu-title"><?php echo $label; ?></span>
                        <span class="mu-desc"><?php echo $desc; ?></span>
                    </div>
                    <div class="mu-controls">
                        <div class="mu-qty-wrap">
                            <button type="button" class="mu-qty-btn" onclick="<?php echo esc_attr( $on_update_js ); ?>('<?php echo esc_js( $key ); ?>', -1)">-</button>
                            <input type="number" id="qty-<?php echo esc_attr( $key ); ?>" class="mu-qty-input" value="0" readonly>
                            <button type="button" class="mu-qty-btn" onclick="<?php echo esc_attr( $on_update_js ); ?>('<?php echo esc_js( $key ); ?>', 1)">+</button>
                        </div>
                        <span class="mu-price-tag" id="<?php echo esc_attr( $price_tag_id ); ?>"></span>
                    </div>
                </div>

                <?php if ( $extras_callback ) : ?>
                    <div class="mu-item-options">
                        <?php call_user_func( $extras_callback ); ?>
                    </div>
                <?php endif; ?>

                <?php if ( $info_html ) : ?>
                    <span class="mu-details-toggle" onclick="jQuery('#details-<?php echo esc_js( $key ); ?>').slideToggle(200)">Ver detalles</span>
                    <div class="mu-details-content" id="details-<?php echo esc_attr( $key ); ?>"><?php echo $info_html; ?></div>
                <?php endif; ?>
            </div>
            <?php
        }
    }

}

// ============================================================================
// 2. BACKEND AUTOMÁTICO (Hooks Genéricos)
// ============================================================================

// A. Guardar datos en el carrito
if ( ! function_exists( 'mu_core_add_cart_item_data' ) ) {
    add_filter( 'woocommerce_add_cart_item_data', 'mu_core_add_cart_item_data', 10, 2 );
    function mu_core_add_cart_item_data( $cart_item_data, $product_id ) {
        if ( isset( $_POST['mu_custom_data'] ) && ! empty( $_POST['mu_custom_data'] ) ) {
            $json = stripslashes( $_POST['mu_custom_data'] );
            $data = json_decode( $json, true );
            if ( $data ) {
                $cart_item_data['mu_core_info'] = $data;
                $cart_item_data['unique_key']   = md5( microtime() . $json );
            }
        }
        return $cart_item_data;
    }
}

// B. Sobrescribir precio en el carrito
if ( ! function_exists( 'mu_core_set_cart_price' ) ) {
    add_action( 'woocommerce_before_calculate_totals', 'mu_core_set_cart_price', 20, 1 );
    function mu_core_set_cart_price( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }
        foreach ( $cart->get_cart() as $cart_item ) {
            if ( isset( $cart_item['mu_core_info']['total_calculated'] ) ) {
                $cart_item['data']->set_price( floatval( $cart_item['mu_core_info']['total_calculated'] ) );
            }
        }
    }
}

// C. Mostrar detalles en Carrito (Display Lines)
if ( ! function_exists( 'mu_core_display_cart_meta' ) ) {
    add_filter( 'woocommerce_get_item_data', 'mu_core_display_cart_meta', 10, 2 );
    function mu_core_display_cart_meta( $item_data, $cart_item ) {
        if ( isset( $cart_item['mu_core_info']['display_lines'] ) ) {
            $lines = $cart_item['mu_core_info']['display_lines'];
            if ( is_array( $lines ) ) {
                $html_parts = [];
                foreach ( $lines as $line ) {
                    if ( is_string( $line ) ) {
                        $html_parts[] = $line;
                    } elseif ( isset( $line['value'] ) ) {
                        $html_parts[] = $line['value'];
                    }
                }
                if ( ! empty( $html_parts ) ) {
                    $item_data[] = [
                        'key'   => 'Detalle',
                        'value' => implode( '<br>', $html_parts ),
                    ];
                }
            }
        }
        return $item_data;
    }
}

// D. Guardar en el Pedido (Order Meta)
if ( ! function_exists( 'mu_core_save_order_meta' ) ) {
    add_action( 'woocommerce_checkout_create_order_line_item', 'mu_core_save_order_meta', 10, 4 );
    function mu_core_save_order_meta( $item, $key, $values, $order ) {
        if ( isset( $values['mu_core_info']['display_lines'] ) ) {
            $lines     = $values['mu_core_info']['display_lines'];
            $txt_parts = [];

            foreach ( $lines as $line ) {
                if ( is_string( $line ) ) {
                    $txt_parts[] = strip_tags( str_replace( [ '<br>', '<b>', '</b>' ], [ ' ', '', '' ], $line ) );
                } elseif ( isset( $line['value'] ) ) {
                    $txt_parts[] = strip_tags( str_replace( [ '<br>', '<b>', '</b>' ], [ ' ', '', '' ], $line['value'] ) );
                }
            }

            if ( ! empty( $txt_parts ) ) {
                $item->add_meta_data( 'Detalle', implode( "\n", $txt_parts ), true );
            }
        }
    }
}

// ============================================================================
// 3. CONFIRMACIÓN EN ADMIN
// ============================================================================

if ( ! function_exists( 'mu_core_admin_notice' ) ) {
    add_action( 'admin_notices', 'mu_core_admin_notice' );
    function mu_core_admin_notice() {
        $screen = get_current_screen();
        if ( $screen && strpos( $screen->id, 'code-snippets' ) !== false ) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>✅ Productos Personalizados Core v<?php echo esc_html( MU_CORE_VERSION ); ?> activo</strong><br>Sistema UI centralizado + Lógica Variaciones.</p>
            </div>
            <?php
        }
    }
}
