/**
 * Flexible Price Widget — Muy Únicos v4.0
 *
 * Maneja la UI de edición inline de precio en carrito/checkout.
 * Datos PHP → JS via wp_localize_script (muFlexiblePrice).
 *
 * Carga: condicional is_cart() || is_checkout()
 * Deps:  jquery
 */
(function ($) {
    'use strict';

    var cfg = (typeof muFlexiblePrice !== 'undefined') ? muFlexiblePrice : {
        ajaxUrl: '',
        nonce: '',
        i18n: { saving: 'Guardando...', invalidAmt: 'Ingresá un monto válido mayor a cero.' }
    };

    // ── Abrir edición ────────────────────────────────────────────────
    $(document).on('click', '.mu-cp-link', function (e) {
        e.preventDefault();
        var $wrapper = $(this).closest('.mu-cp-wrapper');
        $wrapper.find('.mu-cp-view').addClass('mu-hidden');
        $wrapper.find('.mu-cp-edit').removeClass('mu-hidden').find('.mu-cp-input').focus();
    });

    // ── Guardar precio via AJAX ──────────────────────────────────────
    $(document).on('click', '.mu-btn-save', function (e) {
        e.preventDefault();

        var $btn     = $(this);
        var $wrapper = $btn.closest('.mu-cp-wrapper');
        var $input   = $wrapper.find('.mu-cp-input');
        var $msg     = $wrapper.find('.mu-cp-msg');
        var val      = parseFloat($input.val());

        // Validación client-side
        if ( isNaN(val) || val <= 0 ) {
            $input.css('border-color', 'var(--error, #d9534f)');
            $msg.css('color', 'var(--error, #d9534f)').text(cfg.i18n.invalidAmt);
            return;
        }

        $input.css('border-color', '');
        $btn.prop('disabled', true).css('opacity', 0.6);
        $msg.css('color', 'var(--texto-light, #777)').text(cfg.i18n.saving);

        $.post(
            cfg.ajaxUrl,
            {
                action:        'mu_update_custom_price',
                security:      cfg.nonce,
                cart_item_key: $wrapper.data('key'),
                custom_price:  val
            },
            function (response) {
                if ( response.success ) {
                    window.location.reload();
                } else {
                    var errMsg = (response.data && response.data.message)
                        ? response.data.message
                        : 'Error al guardar.';
                    $msg.css('color', 'var(--error, #d9534f)').text(errMsg);
                    $btn.prop('disabled', false).css('opacity', 1);
                }
            }
        );
    });

    // ── Enter dispara guardar ────────────────────────────────────────
    $(document).on('keypress', '.mu-cp-input', function (e) {
        if ( e.which === 13 ) {
            e.preventDefault();
            $(this).siblings('.mu-btn-save').trigger('click');
        }
    });

})(jQuery);
