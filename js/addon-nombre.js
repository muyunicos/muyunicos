/**
 * Addon Nombre v3.0 — Frontend Logic
 *
 * - muTransformName(): Transformación de mayúsculas/título
 * - Validación al añadir al carrito (producto)
 * - Editor inline AJAX en carrito
 *
 * Datos vía wp_localize_script: muNombreData { ajaxUrl, nonce }
 * Carga condicional: is_product() (categorías 18/62/19) || is_cart()
 */
(function () {
    'use strict';

    // ========================================================================
    // 1. Transformación de nombre (Producto)
    // ========================================================================

    window.muTransformName = function (mode) {
        var input = document.getElementById('nombre_personalizado');
        if (!input || !input.value) return;

        var text = input.value;
        var exceptions = ['de', 'del', 'la', 'las', 'el', 'los', 'y', 'e', 'o', 'san', 'santa', 'von', 'van'];

        if (mode === 'upper') {
            input.value = text.toUpperCase();
        } else if (mode === 'title') {
            input.value = text.toLowerCase().split(' ').map(function (word, index) {
                if (index === 0) return word.charAt(0).toUpperCase() + word.slice(1);
                if (exceptions.indexOf(word) !== -1) return word;
                return word.charAt(0).toUpperCase() + word.slice(1);
            }).join(' ');
        }

        input.focus();
    };

    // ========================================================================
    // 2. Inicialización al cargar DOM
    // ========================================================================

    document.addEventListener('DOMContentLoaded', function () {
        var $ = jQuery;

        // --- Validación al añadir al carrito (solo en producto) ---
        $('body').on('click', '.single_add_to_cart_button', function (e) {
            var input = $('#nombre_personalizado');
            if (input.length === 0 || input.is(':hidden') || input.is(':disabled')) return;

            var nombre = input.val().trim();
            if (nombre.length < 2) {
                e.preventDefault();
                $('#mu-nombre-error').html('Escribe un nombre para este pedido de etiquetas.').slideDown();
                input.addClass('mu-input-error');
                $('html, body').animate({ scrollTop: input.offset().top - 150 }, 500);
                return false;
            }
        });

        $('#nombre_personalizado').on('input', function () {
            $(this).removeClass('mu-input-error');
            $('#mu-nombre-error').hide();
        });

        // --- Editor inline en carrito (solo si muNombreData existe) ---
        if (typeof muNombreData === 'undefined') return;

        var ajaxUrl = muNombreData.ajaxUrl;
        var nonce   = muNombreData.nonce;

        $(document).on('click', '.mu-name-trigger-edit', function (e) {
            e.preventDefault();
            var wrap = $(this).closest('.mu-name-wrapper-hook');
            wrap.find('.mu-name-view-mode').hide();
            wrap.find('.mu-name-edit-mode').css('display', 'flex');
            wrap.find('.mu-name-input').focus();
        });

        $(document).on('click', '.mu-name-cancel-btn', function (e) {
            e.preventDefault();
            var wrap = $(this).closest('.mu-name-wrapper-hook');
            wrap.find('.mu-name-edit-mode').hide();
            wrap.find('.mu-name-view-mode').fadeIn();
        });

        $(document).on('click', '.mu-btn-save', function (e) {
            e.preventDefault();
            var btn  = $(this);
            var wrap = btn.closest('.mu-name-wrapper-hook');
            var val  = wrap.find('.mu-name-input').val().trim();
            var msg  = wrap.find('.mu-name-status-msg');

            if (val.length < 2) return;

            btn.prop('disabled', true);
            msg.text('Guardando...');

            $.post(ajaxUrl, {
                action:        'update_cart_custom_name',
                security:      nonce,
                cart_item_key: wrap.data('key'),
                custom_name:   val
            }, function (res) {
                if (res.success) {
                    location.reload();
                } else {
                    btn.prop('disabled', false);
                    msg.text(res.data.message);
                }
            });
        });
    });
})();
