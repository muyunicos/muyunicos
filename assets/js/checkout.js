/**
 * Checkout JS - Muy Únicos
 * Migrado desde snippet "Checkout Híbrido Optimizado"
 * Vars PHP recibidas via wp_localize_script como `muCheckout`
 */
jQuery(document).ready(function ($) {
    'use strict';

    // --- CONFIG (PHP → JS via wp_localize_script) ---
    const isLoggedIn = muCheckout.isLoggedIn;
    const ajaxUrl    = muCheckout.ajaxUrl;
    const ajaxNonce  = muCheckout.nonce;

    // --- INYECCIONES DOM INICIALES ---
    if ($('#wa-status-msg').length === 0) {
        $('label[for="billing_phone"]').append('<span id="wa-status-msg"></span>');
    }
    if ($('#muyunicos_wa_valid').length === 0) {
        $('form.checkout').append('<input type="hidden" name="muyunicos_wa_valid" id="muyunicos_wa_valid" value="1">');
    }

    // --- REFERENCIAS ---
    const $phoneInput   = $('#billing_phone');
    const $countryInput = $('#billing_country');
    const $phoneWrapper = $('#billing_phone_field');
    const $statusMsg    = $('#wa-status-msg');
    let valTimer;

    // ================================================
    // LÓGICA WHATSAPP
    // ================================================

    function validarWhatsApp() {
        // libphonenumber cargado como dependencia WP, retry por seguridad
        if (typeof libphonenumber === 'undefined') {
            setTimeout(validarWhatsApp, 500);
            return;
        }

        const rawVal      = $phoneInput.val();
        const countryCode = $countryInput.val();
        const cleanDigits = rawVal.replace(/\D/g, '');

        if (rawVal.trim().length === 0) {
            $phoneWrapper.removeClass('hide-optional');
            setVisualState('reset');
            $('#muyunicos_wa_valid').val('1');
            return;
        }

        $phoneWrapper.addClass('hide-optional');

        if (cleanDigits.length < 6) {
            setVisualState('reset');
            $('#muyunicos_wa_valid').val('0');
            return;
        }

        try {
            const pn = libphonenumber.parsePhoneNumber(rawVal, countryCode);
            if (pn && pn.isValid()) {
                setVisualState('valid', '✓ ' + pn.formatInternational());
                $('#muyunicos_wa_valid').val('1');
            } else {
                setVisualState('error', 'Revisá el número');
                $('#muyunicos_wa_valid').val('0');
            }
        } catch (e) {
            setVisualState('error', 'Revisá el número');
            $('#muyunicos_wa_valid').val('0');
        }
    }

    function setVisualState(state, text = '') {
        $phoneInput.parent().removeClass('muyunicos-field-valid muyunicos-field-error');
        if (state === 'valid') {
            $phoneInput.parent().addClass('muyunicos-field-valid');
            $statusMsg.html('<span class="wa-ok">' + text + '</span>');
        } else if (state === 'error') {
            $phoneInput.parent().addClass('muyunicos-field-error');
            $statusMsg.html('<span class="wa-err">' + text + '</span>');
        } else {
            $statusMsg.text('');
        }
    }

    function autoPrefix() {
        if (typeof libphonenumber === 'undefined') return;
        if ($phoneInput.val() === '') {
            try {
                const code = libphonenumber.getCountryCallingCode($countryInput.val());
                $phoneInput.val('+' + code + ' ');
            } catch (e) {}
        }
    }

    $phoneInput.on('input keyup', function () {
        clearTimeout(valTimer);
        valTimer = setTimeout(validarWhatsApp, 800);
    });
    $phoneInput.on('blur', validarWhatsApp);
    $countryInput.on('change', function () {
        autoPrefix();
        setTimeout(validarWhatsApp, 100);
    });
    $(window).on('load', function () {
        setTimeout(function () { autoPrefix(); validarWhatsApp(); }, 1000);
    });

    // ================================================
    // LÓGICA NOMBRE: sincroniza campos nativos ocultos
    // ================================================
    $('#billing_full_name').on('input change', function () {
        const val   = $(this).val().trim();
        const space = val.indexOf(' ');
        if (space !== -1) {
            $('#billing_first_name').val(val.substring(0, space));
            $('#billing_last_name').val(val.substring(space + 1));
        } else {
            $('#billing_first_name').val(val);
            $('#billing_last_name').val('.');
        }
    });

    // ================================================
    // TOGGLE FÍSICO: mostrar/ocultar campos dirección
    // ================================================
    const $addrFields = $('.muyunicos-physical-address-field');

    $('#muyunicos-toggle-shipping').on('change', function () {
        if ($(this).is(':checked')) {
            $addrFields.removeClass('mu-hidden').hide().slideDown();
        } else {
            $addrFields.slideUp(function () { $(this).addClass('mu-hidden'); });
        }
    });
    // Restaurar estado en recarga con error de validación
    if ($('#muyunicos-toggle-shipping').is(':checked')) {
        $addrFields.removeClass('mu-hidden');
    }

    // ================================================
    // AJAX EMAIL — solo para guests
    // ================================================
    if (!isLoggedIn) {
        let emailTimer;

        $('#billing_email').on('keyup change', function () {
            const email  = $(this).val();
            const $wrap  = $(this).parent();

            clearTimeout(emailTimer);

            if (/^.+@.+\..+$/.test(email)) {
                $wrap.addClass('muyunicos-field-valid');

                emailTimer = setTimeout(function () {
                    $.post(ajaxUrl, {
                        action:   'muyunicos_check_email',
                        email:    email,
                        security: ajaxNonce
                    }, function (res) {
                        if (res.exists) {
                            $('#muyunicos-email-exists-notice')
                                .html('👋 Ya tenés cuenta. <a href="#" class="mu-open-modal">Iniciá sesión</a>.')
                                .slideDown();
                            $('.muyunicos-verified-badge').show();
                        } else {
                            $('#muyunicos-email-exists-notice').slideUp();
                            $('.muyunicos-verified-badge').show();
                        }
                    });
                }, 1000);
            } else {
                $wrap.removeClass('muyunicos-field-valid');
                $('.muyunicos-verified-badge').hide();
                $('#muyunicos-email-exists-notice').slideUp();
            }
        });
    }

    // Aceptar términos automáticamente
    $('input[name="terms"]').prop('checked', true);
});
