/**
 * Flexible Price Widget — Muy Únicos v5.0 (Performance Optimized)
 *
 * Maneja la UI de edición inline de precio en carrito/checkout.
 * Datos PHP → JS via wp_localize_script (muFlexiblePrice).
 *
 * Optimizaciones de rendimiento:
 * - Debouncing de 500ms para prevenir llamadas AJAX excesivas
 * - Loading states para prevenir envíos duplicados
 * - Validación client-side mejorada
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

    // Debounce function para prevenir llamadas AJAX excesivas
    function debounce(func, wait) {
        var timeout;
        return function() {
            var context = this, args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                func.apply(context, args);
            }, wait);
        };
    }

    // Track active requests para prevenir duplicados
    var activeRequests = {};

    // ── Abrir edición ────────────────────────────────────────────────
    $(document).on('click', '.mu-cp-link', function (e) {
        e.preventDefault();
        var $wrapper = $(this).closest('.mu-cp-wrapper');
        $wrapper.find('.mu-cp-view').addClass('mu-hidden');
        $wrapper.find('.mu-cp-edit').removeClass('mu-hidden').find('.mu-cp-input').focus();
    });

    // ── Guardar precio via AJAX (con debouncing y loading states) ─────
    var savePrice = debounce(function($btn, $wrapper, $input, $msg, val) {
        var cartItemKey = $wrapper.data('key');

        // Prevenir solicitudes duplicadas para el mismo item
        if (activeRequests[cartItemKey]) {
            return; // Ya hay una solicitud activa para este item
        }

        // Validación client-side mejorada
        if (isNaN(val) || val <= 0 || !isFinite(val)) {
            $input.css('border-color', 'var(--error, #d9534f)');
            $msg.css('color', 'var(--error, #d9534f)').text(cfg.i18n.invalidAmt);
            return;
        }

        // Validar rango razonable (previene valores extremos)
        if (val > 1000000) {
            $input.css('border-color', 'var(--error, #d9534f)');
            $msg.css('color', 'var(--error, #d9534f)').text('El monto es demasiado alto.');
            return;
        }

        // Marcar como solicitud activa
        activeRequests[cartItemKey] = true;

        $input.css('border-color', '');
        $btn.prop('disabled', true).css('opacity', 0.6).addClass('mu-loading');
        $msg.css('color', 'var(--texto-light, #777)').text(cfg.i18n.saving);

        $.post(
            cfg.ajaxUrl,
            {
                action:        'mu_update_custom_price',
                security:      cfg.nonce,
                cart_item_key: cartItemKey,
                custom_price:  val
            },
            function (response) {
                // Limpiar solicitud activa
                delete activeRequests[cartItemKey];

                if (response.success) {
                    // Success: recargar página para mostrar precio actualizado
                    window.location.reload();
                } else {
                    var errMsg = (response.data && response.data.message)
                        ? response.data.message
                        : 'Error al guardar. Por favor, intentá nuevamente.';
                    $msg.css('color', 'var(--error, #d9534f)').text(errMsg);
                    $btn.prop('disabled', false).css('opacity', 1).removeClass('mu-loading');
                }
            }
        ).fail(function() {
            // Error de red
            delete activeRequests[cartItemKey];
            $msg.css('color', 'var(--error, #d9534f)').text('Error de conexión. Verificá tu internet.');
            $btn.prop('disabled', false).css('opacity', 1).removeClass('mu-loading');
        });
    }, 500); // 500ms debounce

    // Event listener para botón guardar
    $(document).on('click', '.mu-btn-save', function (e) {
        e.preventDefault();

        var $btn     = $(this);
        var $wrapper = $btn.closest('.mu-cp-wrapper');
        var $input   = $wrapper.find('.mu-cp-input');
        var $msg     = $wrapper.find('.mu-cp-msg');
        var val      = parseFloat($input.val());

        // Aplicar debouncing
        savePrice($btn, $wrapper, $input, $msg, val);
    });

    // ── Enter dispara guardar (con debouncing) ────────────────────────
    $(document).on('keypress', '.mu-cp-input', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            var $input = $(this);
            var $wrapper = $input.closest('.mu-cp-wrapper');
            var $btn = $wrapper.find('.mu-btn-save');
            var $msg = $wrapper.find('.mu-cp-msg');
            var val = parseFloat($input.val());

            // Aplicar debouncing
            savePrice($btn, $wrapper, $input, $msg, val);
        }
    });

    // ── Validación en tiempo real (visual feedback) ───────────────────
    $(document).on('input', '.mu-cp-input', function() {
        var $input = $(this);
        var val = parseFloat($input.val());

        // Remover estado de error cuando el usuario corrige
        if (!isNaN(val) && val > 0 && isFinite(val) && val <= 1000000) {
            $input.css('border-color', '');
        }
    });

})(jQuery);
