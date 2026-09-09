<?php
/**
 * Muy Únicos - Restricción de Carrito por País
 *
 * Detecta productos físicos en el carrito cuando el usuario está en un
 * subdominio restringido (solo digitales) y muestra un aviso con opción
 * de removerlos manualmente. Bloquea el checkout hasta que se resuelva.
 *
 * Incluye:
 * - Detección de productos físicos en carrito
 * - Aviso localizado (es/pt/en) en carrito y checkout
 * - Bloqueo del checkout (woocommerce_checkout_process)
 * - Endpoint AJAX para remover productos físicos
 * - Enqueue condicional de JS/CSS
 *
 * @package GeneratePress_Child
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================
// HELPERS
// ============================================

if ( ! function_exists( 'mu_cart_has_physical_products' ) ) {
    /**
     * Verifica si el carrito contiene productos físicos.
     * Independiente de mu_has_physical_products() de checkout.php
     * para no depender del orden de carga de módulos.
     *
     * @return bool
     */
    function mu_cart_has_physical_products() {
        static $has_physical = null;
        if ( $has_physical !== null ) return $has_physical;

        $has_physical = false;
        if ( function_exists( 'WC' ) && WC()->cart ) {
            foreach ( WC()->cart->get_cart() as $cart_item ) {
                $product = $cart_item['data'] ?? null;
                if ( $product && ! $product->is_virtual() && ! $product->is_downloadable() ) {
                    $has_physical = true;
                    break;
                }
            }
        }
        return $has_physical;
    }
}

if ( ! function_exists( 'mu_cart_restriction_is_active' ) ) {
    /**
     * Determina si la restricción de carrito aplica:
     * usuario en subdominio restringido + carrito con productos físicos.
     *
     * @return bool
     */
    function mu_cart_restriction_is_active() {
        if ( is_admin() && ! wp_doing_ajax() ) return false;
        if ( ! function_exists( 'muyu_is_restricted_user' ) ) return false;
        if ( ! muyu_is_restricted_user() ) return false;
        return mu_cart_has_physical_products();
    }
}

if ( ! function_exists( 'mu_cart_restriction_get_country_name' ) ) {
    /**
     * Obtiene el nombre del país actual (para el mensaje localizado).
     *
     * @return string Nombre del país
     */
    function mu_cart_restriction_get_country_name() {
        if ( function_exists( 'muyu_get_current_country_from_subdomain' ) && function_exists( 'muyu_get_countries_data' ) ) {
            $code = muyu_get_current_country_from_subdomain();
            $countries = muyu_get_countries_data();
            if ( isset( $countries[ $code ] ) ) {
                return $countries[ $code ]['name'];
            }
        }
        return '';
    }
}

if ( ! function_exists( 'mu_cart_restriction_get_messages' ) ) {
    /**
     * Textos localizados del aviso según el idioma del país actual.
     *
     * @return array{title: string, message: string, remove: string, back: string}
     */
    function mu_cart_restriction_get_messages() {
        $country_name = mu_cart_restriction_get_country_name();

        $texts = [
            'pt' => [
                'title'   => 'Produtos não disponíveis',
                'message' => sprintf(
                    'Alguns produtos do seu carrinho (os físicos) não estão disponíveis em %s. Aqui vendemos apenas produtos digitais. Remova-os para continuar ou volte para a loja da Argentina.',
                    $country_name
                ),
                'remove'  => 'Remover produtos não disponíveis',
                'back'    => 'Voltar para a loja da Argentina',
            ],
            'en' => [
                'title'   => 'Products not available',
                'message' => sprintf(
                    'Some products in your cart (the physical ones) are not available in %s. We only sell digital products here. Remove them to continue or go back to the Argentina store.',
                    $country_name
                ),
                'remove'  => 'Remove unavailable products',
                'back'    => 'Go back to the Argentina store',
            ],
            'es' => [
                'title'   => 'Productos no disponibles',
                'message' => sprintf(
                    'Algunos productos de tu carrito no están disponibles en %s.',
                    $country_name
                ),
                'remove'  => 'Remover productos no disponibles',
                'back'    => 'Ir a la tienda de Argentina',
            ],
        ];

        $lang = 'es';
        if ( function_exists( 'muyu_get_current_country_from_subdomain' ) && function_exists( 'muyu_get_countries_data' ) ) {
            $code = muyu_get_current_country_from_subdomain();
            $countries = muyu_get_countries_data();
            $lang = $countries[ $code ]['lang'] ?? 'es';
        }

        return $texts[ $lang ] ?? $texts['es'];
    }
}

// ============================================
// AVISO EN CARRITO Y CHECKOUT
// ============================================

if ( ! function_exists( 'mu_cart_restriction_notice' ) ) {
    /**
     * Muestra el aviso de productos no disponibles en carrito y checkout.
     * Se ejecuta en woocommerce_before_cart y woocommerce_before_checkout_form.
     */
    function mu_cart_restriction_notice() {
        if ( ! mu_cart_restriction_is_active() ) return;

        $messages = mu_cart_restriction_get_messages();
        $country_name = mu_cart_restriction_get_country_name();
        $back_url = 'https://muyunicos.com/';

        // Preservar la URI actual al volver a Argentina
        if ( function_exists( 'muyu_country_language_prefix' ) && function_exists( 'muyu_clean_uri' ) ) {
            $request_uri = $_SERVER['REQUEST_URI'] ?? '/';
            $back_url = 'https://muyunicos.com' . muyu_clean_uri( '', $request_uri );
        }

        ?>
        <div class="mu-cart-restriction-notice" role="alert">
            <div class="mu-cart-restriction-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
            </div>
            <div class="mu-cart-restriction-content">
                <strong class="mu-cart-restriction-title"><?php echo esc_html( $messages['title'] ); ?></strong>
                <p class="mu-cart-restriction-message"><?php echo esc_html( $messages['message'] ); ?></p>
                <div class="mu-cart-restriction-actions">
                    <button type="button" class="mu-btn mu-btn-primary mu-cart-restriction-remove" data-nonce="<?php echo esc_attr( wp_create_nonce( 'mu-remove-physical' ) ); ?>">
                        <?php echo esc_html( $messages['remove'] ); ?>
                    </button>
                    <a href="<?php echo esc_url( $back_url ); ?>" class="mu-btn mu-btn-outline mu-cart-restriction-back" rel="nofollow">
                        <?php echo esc_html( $messages['back'] ); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
}
add_action( 'woocommerce_before_cart', 'mu_cart_restriction_notice', 5 );
add_action( 'woocommerce_before_checkout_form', 'mu_cart_restriction_notice', 5 );

// ============================================
// BLOQUEO DEL CHECKOUT
// ============================================

if ( ! function_exists( 'mu_cart_restriction_checkout_validation' ) ) {
    /**
     * Bloquea el checkout si hay productos físicos en subdominio restringido.
     * Se ejecuta en woocommerce_checkout_process.
     */
    function mu_cart_restriction_checkout_validation() {
        if ( ! mu_cart_restriction_is_active() ) return;

        $messages = mu_cart_restriction_get_messages();
        wc_add_notice( $messages['message'], 'error' );
    }
}
add_action( 'woocommerce_checkout_process', 'mu_cart_restriction_checkout_validation', 5 );

// ============================================
// AJAX: REMOVER PRODUCTOS FÍSICOS
// ============================================

if ( ! function_exists( 'mu_ajax_remove_physical_products' ) ) {
    /**
     * Endpoint AJAX para remover todos los productos físicos del carrito.
     * Usa WC_AJAX para que los fragmentos del carrito se refresquen.
     */
    function mu_ajax_remove_physical_products() {
        check_ajax_referer( 'mu-remove-physical', 'nonce' );

        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            wp_send_json_error( [ 'message' => 'Carrito no disponible' ] );
        }

        // Rate limiting básico
        if ( function_exists( 'mu_ajax_rate_limit_check' ) && ! mu_ajax_rate_limit_check( 'mu_remove_physical', 5, 60 ) ) {
            wp_send_json_error( [ 'message' => 'Demasiadas solicitudes. Intenta de nuevo en un momento.' ] );
        }

        $removed = 0;
        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            $product = $cart_item['data'] ?? null;
            if ( $product && ! $product->is_virtual() && ! $product->is_downloadable() ) {
                WC()->cart->remove_cart_item( $cart_item_key );
                $removed++;
            }
        }

        // El JS recarga la página tras éxito, no necesitamos fragmentos aquí.
        wp_send_json_success( [ 'removed' => $removed ] );
    }
}
add_action( 'wc_ajax_mu_remove_physical_products', 'mu_ajax_remove_physical_products' );
add_action( 'wp_ajax_mu_remove_physical_products', 'mu_ajax_remove_physical_products' );
add_action( 'wp_ajax_nopriv_mu_remove_physical_products', 'mu_ajax_remove_physical_products' );

// ============================================
// ENQUEUE CONDICIONAL
// ============================================

if ( ! function_exists( 'mu_cart_restriction_enqueue' ) ) {
    /**
     * Encola JS/CSS solo cuando la restricción está activa
     * (subdominio restringido + carrito con físicos).
     */
    function mu_cart_restriction_enqueue() {
        if ( ! mu_cart_restriction_is_active() ) return;

        $ver = wp_get_theme()->get( 'Version' );
        $uri = get_stylesheet_directory_uri();

        // CSS: reutilizar cart.css si ya está cargado, si no agregar estilos inline
        if ( ! wp_style_is( 'mu-cart', 'enqueued' ) && ! wp_style_is( 'mu-checkout', 'enqueued' ) ) {
            wp_add_inline_style( 'mu-base', mu_cart_restriction_get_inline_css() );
        }

        // URL del endpoint AJAX (wc_ajax para que WC refresque fragmentos)
        $ajax_url = function_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( 'mu_remove_physical_products' ) : admin_url( 'admin-ajax.php' );

        // JS: agregar al script del carrito o checkout si existe, si no inline
        $target_script = wp_script_is( 'mu-cart-js', 'enqueued' ) ? 'mu-cart-js' : ( wp_script_is( 'mu-checkout-js', 'enqueued' ) ? 'mu-checkout-js' : '' );

        if ( $target_script ) {
            wp_add_inline_script( $target_script, mu_cart_restriction_get_inline_js( $ajax_url ), 'after' );
        } else {
            wp_add_inline_script( 'jquery', mu_cart_restriction_get_inline_js( $ajax_url ), 'after' );
        }
    }
    add_action( 'wp_enqueue_scripts', 'mu_cart_restriction_enqueue', 1000 );
}

if ( ! function_exists( 'mu_cart_restriction_get_inline_css' ) ) {
    /**
     * CSS inline para el aviso (usado cuando cart.css/checkout.css no están cargados).
     *
     * @return string
     */
    function mu_cart_restriction_get_inline_css() {
        return '
.mu-cart-restriction-notice {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    background: #fff8e6;
    border: 2px solid #f0ad4e;
    border-radius: 8px;
    padding: 16px 18px;
    margin: 0 0 20px;
    max-width: 1200px;
}
.mu-cart-restriction-icon {
    flex-shrink: 0;
    color: #f0ad4e;
    margin-top: 2px;
}
.mu-cart-restriction-content { flex: 1; }
.mu-cart-restriction-title {
    display: block;
    font-size: 1rem;
    color: #8a6d3b;
    margin-bottom: 4px;
}
.mu-cart-restriction-message {
    margin: 0 0 12px;
    color: #6d5a2e;
    font-size: 0.9rem;
    line-height: 1.5;
}
.mu-cart-restriction-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}
.mu-cart-restriction-actions .mu-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 18px;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    border: 2px solid transparent;
    transition: opacity 0.2s;
}
.mu-cart-restriction-actions .mu-btn:hover { opacity: 0.85; }
.mu-cart-restriction-actions .mu-btn-primary {
    background: #f0ad4e;
    color: #fff;
}
.mu-cart-restriction-actions .mu-btn-outline {
    background: transparent;
    color: #8a6d3b;
    border-color: #f0ad4e;
}
.mu-cart-restriction-remove.is-loading {
    opacity: 0.6;
    pointer-events: none;
}
@media (max-width: 480px) {
    .mu-cart-restriction-notice { flex-direction: column; }
    .mu-cart-restriction-actions { flex-direction: column; align-items: stretch; }
    .mu-cart-restriction-actions .mu-btn { width: 100%; }
}
';
    }
}

if ( ! function_exists( 'mu_cart_restriction_get_inline_js' ) ) {
    /**
     * JS inline para el botón de remover (usado cuando cart.js/checkout.js no están cargados).
     *
     * @return string
     */
    function mu_cart_restriction_get_inline_js( $ajax_url = '' ) {
        $ajax_url = esc_url_raw( $ajax_url ?: ( function_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( 'mu_remove_physical_products' ) : admin_url( 'admin-ajax.php' ) ) );
        return '
(function($) {
    "use strict";
    if (typeof $ !== "function") return;

    $(document).on("click", ".mu-cart-restriction-remove", function(e) {
        e.preventDefault();
        var $btn = $(this);
        if ($btn.hasClass("is-loading")) return;

        $btn.addClass("is-loading").prop("disabled", true);
        var nonce = $btn.data("nonce");

        $.ajax({
            url: "' . $ajax_url . '",
            type: "POST",
            data: {
                action: "mu_remove_physical_products",
                nonce: nonce
            },
            success: function(response) {
                if (response && response.success) {
                    // Recargar para refrescar carrito/checkout
                    window.location.reload();
                } else {
                    $btn.removeClass("is-loading").prop("disabled", false);
                    alert(response && response.data && response.data.message ? response.data.message : "Error al remover productos.");
                }
            },
            error: function() {
                $btn.removeClass("is-loading").prop("disabled", false);
                alert("Error de conexión. Intenta de nuevo.");
            }
        });
    });
})(jQuery);
';
    }
}