<?php
/**
 * Muy Únicos - Optimización de Checkout
 * 
 * Migración consolidada del snippet "Checkout Híbrido Optimizado".
 * Incluye:
 * - Campos de checkout optimizados (Mobile-First)
 * - Validación y sanitización robusta
 * - AJAX check email (Guest)
 * - Lógica condicional físico/digital
 * - Gestión de contraseñas WooCommerce
 * - Checkout Login Gate (Invitado / Login / Social)
 * 
 * @package GeneratePress_Child
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================
// CONFIGURACIÓN GENERAL
// ============================================

add_filter( 'woocommerce_enable_checkout_login_reminder', '__return_false' );
add_filter( 'woocommerce_checkout_registration_enabled', '__return_true' );
add_filter( 'woocommerce_checkout_registration_required', '__return_false' );
add_filter( 'woocommerce_create_account_default_checked', '__return_true' );
add_filter( 'woocommerce_terms_is_checked_default', '__return_true' );

// Elimina el formulario de login nativo de WooCommerce (evita duplicidad con el Gate)
remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10 );

if ( ! function_exists( 'mu_get_terms_and_conditions_checkbox_text' ) ) {
    /**
     * Personaliza el texto de términos y condiciones.
     */
    function mu_get_terms_and_conditions_checkbox_text( $text ) {
        return 'He leído y acepto los <a href="/terminos/" target="_blank">términos y condiciones</a> de la web.';
    }
}
add_filter( 'woocommerce_get_terms_and_conditions_checkbox_text', 'mu_get_terms_and_conditions_checkbox_text' );

// ============================================
// HELPER FUNCTIONS
// ============================================

if ( ! function_exists( 'mu_has_physical_products' ) ) {
    /**
     * Verifica si el carrito contiene productos físicos.
     * USO DE STATIC: Evita recorrer el array del carrito múltiples veces en una misma carga.
     *
     * @return bool
     */
    function mu_has_physical_products() {
        static $has_physical = null;
        if ( $has_physical !== null ) return $has_physical;

        $has_physical = false;
        if ( WC()->cart ) {
            foreach ( WC()->cart->get_cart() as $cart_item ) {
                if ( ! $cart_item['data']->is_virtual() && ! $cart_item['data']->is_downloadable() ) {
                    $has_physical = true;
                    break;
                }
            }
        }
        return $has_physical;
    }
}

// ============================================
// OPTIMIZACIÓN DE CAMPOS
// ============================================

if ( ! function_exists( 'mu_optimize_checkout_fields' ) ) {
    function mu_optimize_checkout_fields( $fields ) {
        $fields['billing']['billing_full_name'] = [
            'label'       => 'Nombre y Apellido',
            'placeholder' => 'Ej: Juan Pérez',
            'required'    => true,
            'class'       => [ 'form-row-wide', 'mu-smart-field' ],
            'clear'       => true,
            'priority'    => 10,
        ];

        if ( isset( $fields['billing']['billing_country'] ) ) {
            $fields['billing']['billing_country']['priority'] = 20;
            $fields['billing']['billing_country']['class'] = [ 'form-row-wide' ];
        }

        $fields['billing']['billing_contact_header'] = [
            'type'     => 'text',
            'label'    => '',
            'required' => false,
            'class'    => [ 'form-row-wide' ],
            'priority' => 25,
        ];

        $fields['billing']['billing_email']['priority'] = 30;
        $fields['billing']['billing_email']['class'] = [ 'form-row-wide', 'mu-contact-field' ];
        $fields['billing']['billing_email']['label'] = '<span class="mu-verified-badge" style="display:none;">✓</span> E-Mail';

        if ( isset( $fields['billing']['billing_phone'] ) ) {
            $fields['billing']['billing_phone']['priority'] = 40;
            $fields['billing']['billing_phone']['label'] = 'WhatsApp';
            $fields['billing']['billing_phone']['required'] = false;
            $fields['billing']['billing_phone']['placeholder'] = 'Ej: 9 223 123 4567';
            $fields['billing']['billing_phone']['class'] = [ 'form-row-wide', 'mu-contact-field' ];
        }

        $is_physical = mu_has_physical_products();
        $address_fields = [ 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_postcode', 'billing_state' ];

        unset( $fields['billing']['billing_company'] );

        if ( ! $is_physical ) {
            foreach ( $address_fields as $key ) {
                unset( $fields['billing'][ $key ] );
            }
            add_filter( 'woocommerce_cart_needs_shipping', '__return_false' );
        } else {
            $fields['billing']['billing_shipping_toggle'] = [
                'type'     => 'text',
                'label'    => '',
                'required' => false,
                'class'    => [ 'form-row-wide' ],
                'priority' => 45,
            ];

            foreach ( $address_fields as $index => $field_key ) {
                if ( isset( $fields['billing'][ $field_key ] ) ) {
                    $fields['billing'][ $field_key ]['required'] = false;
                    $fields['billing'][ $field_key ]['class'][] = 'mu-hidden';
                    $fields['billing'][ $field_key ]['class'][] = 'mu-physical-address-field';
                    $fields['billing'][ $field_key ]['priority'] = 90 + $index;
                }
            }
        }

        return $fields;
    }
}
add_filter( 'woocommerce_checkout_fields', 'mu_optimize_checkout_fields', 9999 );

// ============================================
// RENDERIZADO DE FRAGMENTOS HTML
// ============================================

if ( ! function_exists( 'mu_render_html_fragments' ) ) {
    function mu_render_html_fragments( $field, $key, $args, $value ) {
        if ( $key === 'billing_contact_header' ) {
            return '<div class="form-row form-row-wide" id="mu_header_row" style="margin-bottom:0;"><div class="mu-contact-header">Te contactamos por:</div><div id="mu-email-exists-notice"></div></div>';
        }
        if ( $key === 'billing_shipping_toggle' ) {
            return '<div class="form-row form-row-wide" id="mu_toggle_row"><div class="mu-shipping-toggle-wrapper"><label style="cursor:pointer;"><input type="checkbox" id="mu-toggle-shipping" name="mu_shipping_toggle" value="1"> <b>Ingresar datos para envío</b> (Opcional)</label></div></div>';
        }
        return $field;
    }
}
add_filter( 'woocommerce_form_field', 'mu_render_html_fragments', 10, 4 );

// ============================================
// SANITIZACIÓN
// ============================================

if ( ! function_exists( 'mu_sanitize_posted_data' ) ) {
    function mu_sanitize_posted_data( $data ) {
        if ( ! empty( $data['billing_full_name'] ) ) {
            $parts = explode( ' ', trim( $data['billing_full_name'] ), 2 );
            $data['billing_first_name'] = $parts[0];
            $data['billing_last_name'] = $parts[1] ?? '.';
        }
        if ( ! empty( $data['billing_phone'] ) ) {
            $digits = preg_replace( '/\D/', '', $data['billing_phone'] );
            if ( strlen( $digits ) <= 6 ) {
                $data['billing_phone'] = '';
            }
        }
        return $data;
    }
}
add_filter( 'woocommerce_checkout_posted_data', 'mu_sanitize_posted_data' );

// ============================================
// VALIDACIÓN
// ============================================

if ( ! function_exists( 'mu_validate_checkout' ) ) {
    function mu_validate_checkout() {
        if ( empty( $_POST['billing_full_name'] ) ) {
            wc_add_notice( __( 'Por favor, completa tu Nombre y Apellido.' ), 'error' );
        }
        if ( ! empty( $_POST['billing_phone'] ) ) {
            if ( isset( $_POST['mu_wa_valid'] ) && $_POST['mu_wa_valid'] === '0' ) {
                wc_add_notice( __( 'El número de WhatsApp parece incompleto o inválido.' ), 'error' );
            }
        }
        if ( isset( $_POST['mu_shipping_toggle'] ) && $_POST['mu_shipping_toggle'] == '1' ) {
            if ( empty( $_POST['billing_address_1'] ) ) {
                wc_add_notice( __( 'La <strong>Dirección</strong> es necesaria para el envío.' ), 'error' );
            }
            if ( empty( $_POST['billing_city'] ) ) {
                wc_add_notice( __( 'La <strong>Ciudad</strong> es necesaria.' ), 'error' );
            }
            if ( empty( $_POST['billing_postcode'] ) ) {
                wc_add_notice( __( 'El <strong>Código Postal</strong> es necesario.' ), 'error' );
            }
            if ( empty( $_POST['billing_state'] ) && WC()->countries->get_states( $_POST['billing_country'] ) ) {
                wc_add_notice( __( 'La <strong>Provincia/Estado</strong> es necesaria.' ), 'error' );
            }
        }
    }
}
add_action( 'woocommerce_checkout_process', 'mu_validate_checkout' );

// ============================================
// AJAX CHECK EMAIL
// ============================================

if ( ! function_exists( 'mu_ajax_check_email_optimized' ) ) {
    function mu_ajax_check_email_optimized() {
        check_ajax_referer( 'check-email-nonce', 'security' );
        $email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
        if ( ! empty( $email ) && email_exists( $email ) ) {
            wp_send_json( [ 'exists' => true ] );
        } else {
            wp_send_json( [ 'exists' => false ] );
        }
    }
}
add_action( 'wc_ajax_mu_check_email', 'mu_ajax_check_email_optimized' );

// ============================================
// TÍTULO PÁGINA CONFIRMACIÓN
// ============================================

if ( ! function_exists( 'mu_order_received_custom_title' ) ) {
    function mu_order_received_custom_title( $title, $id ) {
        if ( is_order_received_page() && get_the_ID() === $id && in_the_loop() ) {
            return '¡Pedido Recibido! 🎉';
        }
        return $title;
    }
}
add_filter( 'the_title', 'mu_order_received_custom_title', 10, 2 );

// ============================================
// GESTIÓN DE CONTRASEÑAS WOOCOMMERCE
// ============================================

add_filter( 'woocommerce_min_password_strength', function( $strength ) {
    return 0;
} );

if ( ! function_exists( 'mu_dequeue_password_strength_meter' ) ) {
    function mu_dequeue_password_strength_meter() {
        if ( wp_script_is( 'wc-password-strength-meter', 'enqueued' ) ) {
            wp_dequeue_script( 'wc-password-strength-meter' );
        }
    }
}
add_action( 'wp_print_scripts', 'mu_dequeue_password_strength_meter', 100 );

// ============================================
// CHECKOUT LOGIN GATE — HTML
// Muestra el bloque de acceso (Invitado/Login/Social)
// solo a usuarios no logueados en el checkout.
// Carga: woocommerce_before_checkout_form, prioridad 5.
// ============================================

if ( ! function_exists( 'mu_checkout_login_notice' ) ) {
    function mu_checkout_login_notice() {
        if ( ! is_checkout() || is_user_logged_in() || is_wc_endpoint_url( 'order-received' ) ) {
            return;
        }
        $current_url = wc_get_checkout_url();
        ?>
        <div class="mu-checkout-login-block" id="mu-checkout-notice" role="dialog" aria-modal="true" aria-labelledby="mu-gate-title">
            <div class="mu-checkout-login-content">

                <div class="mu-checkout-icon">
                    <?php echo mu_get_icon( 'account' ); ?>
                </div>

                <h2 class="mu-checkout-title" id="mu-gate-title">¿Cómo quieres continuar?</h2>
                <p class="mu-checkout-subtitle">Compra más rápido iniciando sesión o continúa como invitado.</p>

                <div class="mu-checkout-actions">

                    <button type="button" class="mu-btn mu-btn-secondary" id="mu-continue-guest-btn">
                        Continuar como Invitado
                    </button>

                    <div class="mu-checkout-divider"><span>O ingresa con</span></div>

                    <button type="button" class="mu-btn mu-btn-outline" id="mu-checkout-open-modal">
                        Usuario y Contraseña
                    </button>

                    <?php if ( shortcode_exists( 'nextend_social_login' ) || class_exists( 'NextendSocialLogin' ) ) : ?>
                    <div class="mu-checkout-social">
                        <a href="<?php echo esc_url( site_url( '/wp-login.php?loginSocial=google&redirect=' . urlencode( $current_url ) ) ); ?>" class="mu-btn-social mu-btn-google" rel="nofollow">
                            <?php echo mu_get_icon( 'google' ); ?>
                            Google
                        </a>
                        <a href="<?php echo esc_url( site_url( '/wp-login.php?loginSocial=facebook&redirect=' . urlencode( $current_url ) ) ); ?>" class="mu-btn-social mu-btn-facebook" rel="nofollow">
                            <?php echo mu_get_icon( 'facebook' ); ?>
                            Facebook
                        </a>
                    </div>
                    <?php endif; ?>

                    <div class="mu-checkout-benefits">
                        <span class="mu-benefits-title">Beneficios de tu cuenta Muy Únicos:</span>
                        <ul class="mu-benefits-list">
                            <li>Seguimiento de pedidos y descargas</li>
                            <li>Acceso inmediato a tus imprimibles</li>
                            <li>Recibe descuentos especiales</li>
                        </ul>
                    </div>

                </div>
            </div>
        </div>
        <?php
    }
}
add_action( 'woocommerce_before_checkout_form', 'mu_checkout_login_notice', 5 );
// ============================================
// MEDIOS DE PAGO — RESTRICCIÓN POR PAÍS
// Oculta Transferencia Bancaria (bacs) si el
// país del comprador NO es Argentina (AR).
// ============================================

if ( ! function_exists( 'mu_filter_payment_gateways_by_country' ) ) {
    function mu_filter_payment_gateways_by_country( $gateways ) {
        // WC()->customer puede ser null fuera del contexto de cart/checkout
        if ( ! WC()->customer ) {
            return $gateways;
        }

        $country = WC()->customer->get_billing_country();

        // Si el país aún no está definido, fallback al país de envío
        if ( empty( $country ) ) {
            $country = WC()->customer->get_shipping_country();
        }

        // Ocultar BACS (Transferencia bancaria) para cualquier país que no sea AR
        if ( ! empty( $country ) && $country !== 'AR' ) {
            unset( $gateways['bacs'] );
        }

        return $gateways;
    }
}
add_filter( 'woocommerce_available_payment_gateways', 'mu_filter_payment_gateways_by_country' );