/**
 * Carrito JS - Muy Únicos
 * Migrado desde snippet "UX Carrito Moderno V6.3 (Fix Loop & Ajax Stability)"
 * Usa SVG dinámico y formato modular.
 */
(function($) {
    'use strict';

    const debounceTime = 800;
    let updateTimer;

    // ================================================
    // FUNCIONES CORE
    // ================================================

    function initCartEnhancements() {
        restructureCartItems();
        handleCouponToggle();
        smartHideSubtotal();
    }

    function restructureCartItems() {
        $('.shop_table.cart tbody tr.cart_item').each(function () {
            const $row = $(this);
            if ($row.hasClass('mu-optimized')) return;

            const $qtyCell      = $row.find('.product-quantity');
            const $subtotalCell = $row.find('.product-subtotal');
            const $qtyInput     = $qtyCell.find('input.qty');

            // A. Mover subtotal arriba
            if (!$row.find('.mu-moved-subtotal').length) {
                const subtotalHtml = $subtotalCell.html();
                if (subtotalHtml) {
                    $qtyCell.prepend('<div class="mu-moved-subtotal">' + subtotalHtml + '</div>');
                }
            }

            // B. Crear controles de cantidad
            if (!$qtyCell.find('.mu-actions-wrapper').length) {
                const $controls = $('<div class="mu-actions-wrapper"></div>');
                const max = parseFloat($qtyInput.attr('max'));
                const isLimitedToOne = !isNaN(max) && max === 1;

                if ($qtyInput.length > 0) {
                    const $wooQtyDiv = $qtyCell.find('.quantity');
                    if (isLimitedToOne) {
                        $wooQtyDiv.hide();
                    } else {
                        if (!$wooQtyDiv.find('.mu-qty-btn').length) {
                            $qtyInput.before('<button type="button" class="mu-qty-btn minus">&minus;</button>');
                            $qtyInput.after('<button type="button" class="mu-qty-btn plus">&plus;</button>');
                        }
                        $controls.append($wooQtyDiv);
                    }
                }

                // C. SVG Dinámico desde PHP para ícono eliminar
                if (typeof muCartVars !== 'undefined' && muCartVars.closeIcon) {
                    const $deleteIcon = $(muCartVars.closeIcon).addClass('mu-red-cart-icon');
                    // Asegurar aria-label y tooltip para accesibilidad
                    $deleteIcon.attr('aria-label', 'Eliminar producto').attr('title', 'Eliminar producto');
                    $controls.append($deleteIcon);
                }

                $qtyCell.append($controls);
            }

            $row.addClass('mu-optimized');
        });
    }

    function handleCouponToggle() {
        const $coupon = $('.coupon');
        if ($coupon.length && !$coupon.find('.mu-toggle-coupon').length) {
            $coupon.children().wrapAll('<div class="mu-coupon-fields"></div>');
            $coupon.prepend('<button type="button" class="mu-toggle-coupon">¿Tenés un código de descuento?</button>');
        }
    }

    function smartHideSubtotal() {
        const $subtotal = $('.cart-subtotal');
        const $total    = $('.order-total');
        if ($subtotal.length && $total.length) {
            const same = $subtotal.find('.amount').text().trim() === $total.find('.amount').text().trim();
            same ? $subtotal.hide() : $subtotal.show();
        }
    }

    // ================================================
    // EVENT HANDLERS
    // ================================================

    function bindEvents() {
        // Toggle cupón
        $(document).on('click', '.mu-toggle-coupon', function (e) {
            e.preventDefault();
            $(this).next('.mu-coupon-fields').slideToggle(200).css('display', 'flex');
        });

        // Botones +/- con debounce (fix loop infinito)
        $(document).on('click', '.mu-qty-btn', function (e) {
            e.preventDefault();
            if ($('.woocommerce-cart-form').hasClass('processing')) return;

            const $btn      = $(this);
            const $input    = $btn.siblings('input.qty');
            const currentVal = parseFloat($input.val() || 0);
            const step       = parseFloat($input.attr('step') || 1);
            const min        = parseFloat($input.attr('min') || 0);
            const max        = parseFloat($input.attr('max'));
            let newVal       = currentVal;

            if ($btn.hasClass('plus')) {
                newVal = (!isNaN(max) && currentVal >= max) ? max : currentVal + step;
            } else {
                newVal = (currentVal > min && currentVal > 0) ? currentVal - step : (min > 0 ? min : 1);
            }

            if (newVal !== currentVal) {
                $input.val(newVal).trigger('change');
                clearTimeout(updateTimer);
                updateTimer = setTimeout(function () {
                    const $updateBtn = $('button[name="update_cart"]').first();
                    if ($updateBtn.length) {
                        $updateBtn.prop('disabled', false).attr('disabled', false).trigger('click');
                    }
                }, debounceTime);
            }
        });

        // Eliminar item
        $(document).on('click', '.mu-red-cart-icon', function () {
            const $row = $(this).closest('tr');
            const $nativeRemove = $row.find('.product-remove a.remove');
            if ($nativeRemove.length) {
                $row.css('opacity', '0.5');
                $nativeRemove[0].click();
            }
        });

        // Re-init tras AJAX de WC
        $(document.body).on('updated_wc_div', function () {
            $('.shop_table.cart tbody tr.cart_item').removeClass('mu-optimized');
            initCartEnhancements();
        });
    }

    // ================================================
    // INICIALIZACIÓN
    // ================================================
    
    function init() {
        if ($('body').hasClass('woocommerce-cart')) {
            initCartEnhancements();
            bindEvents();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})(jQuery);
