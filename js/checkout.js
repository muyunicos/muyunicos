/**
 * Checkout JS - Muy Únicos
 * Migrado desde snippet "Checkout Híbrido Optimizado"
 * Vars PHP recibidas via wp_localize_script como `muCheckout`
 *
 * Incluye: Checkout Login Gate
 * Carga: is_checkout() && ! is_order_received_page()
 */
jQuery(document).ready(function ($) {
    'use strict';

    // --- CONFIG (PHP → JS via wp_localize_script) ---
    const isLoggedIn  = muCheckout.isLoggedIn;
    const ajaxUrl     = muCheckout.ajaxUrl;
    const ajaxNonce   = muCheckout.nonce;
    const myAccountUrl = muCheckout.myAccountUrl;

    // --- INYECCIONES DOM INICIALES ---
    if ($('#wa-status-msg').length === 0) {
        $('label[for="billing_phone"]').append('<span id="wa-status-msg"></span>');
    }
    if ($('#mu_wa_valid').length === 0) {
        $('form.checkout').append('<input type="hidden" name="mu_wa_valid" id="mu_wa_valid" value="1">');
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
            $('#mu_wa_valid').val('1');
            return;
        }
        $phoneWrapper.addClass('hide-optional');
        if (cleanDigits.length < 6) {
            setVisualState('reset');
            $('#mu_wa_valid').val('0');
            return;
        }
        try {
            const pn = libphonenumber.parsePhoneNumber(rawVal, countryCode);
            if (pn && pn.isValid()) {
                setVisualState('valid', '\u2713 ' + pn.formatInternational());
                $('#mu_wa_valid').val('1');
            } else {
                setVisualState('error', 'Revis\u00e1 el n\u00famero');
                $('#mu_wa_valid').val('0');
            }
        } catch (e) {
            setVisualState('error', 'Revis\u00e1 el n\u00famero');
            $('#mu_wa_valid').val('0');
        }
    }

    function setVisualState(state, text) {
        text = text || '';
        $phoneInput.parent().removeClass('mu-field-valid mu-field-error');
        if (state === 'valid') {
            $phoneInput.parent().addClass('mu-field-valid');
            $statusMsg.html('<span class="wa-ok">' + text + '</span>');
        } else if (state === 'error') {
            $phoneInput.parent().addClass('mu-field-error');
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
    const $addrFields = $('.mu-physical-address-field');

    $('#mu-toggle-shipping').on('change', function () {
        if ($(this).is(':checked')) {
            $addrFields.removeClass('mu-hidden').hide().slideDown();
        } else {
            $addrFields.slideUp(function () { $(this).addClass('mu-hidden'); });
        }
    });
    if ($('#mu-toggle-shipping').is(':checked')) {
        $addrFields.removeClass('mu-hidden');
    }

    // ================================================
    // AJAX EMAIL — solo para guests
    // ================================================
    if (!isLoggedIn) {
        let emailTimer;
        $('#billing_email').on('keyup change', function () {
            const email = $(this).val();
            const $wrap = $(this).parent();
            clearTimeout(emailTimer);
            if (/^.+@.+\..+$/.test(email)) {
                $wrap.addClass('mu-field-valid');
                emailTimer = setTimeout(function () {
                    $.post(ajaxUrl, {
                        action:   'mu_check_email',
                        email:    email,
                        security: ajaxNonce
                    }, function (res) {
                        if (res.exists) {
                            $('#mu-email-exists-notice')
                                .html('\uD83D\uDC4B Ya ten\u00e9s cuenta. <a href="#" class="mu-open-modal">Inici\u00e1 sesi\u00f3n</a>.')
                                .slideDown();
                            $('.mu-verified-badge').show();
                        } else {
                            $('#mu-email-exists-notice').slideUp();
                            $('.mu-verified-badge').show();
                        }
                    });
                }, 1000);
            } else {
                $wrap.removeClass('mu-field-valid');
                $('.mu-verified-badge').hide();
                $('#mu-email-exists-notice').slideUp();
            }
        });
    }

    // Aceptar términos automáticamente
    $('input[name="terms"]').prop('checked', true);

    // ================================================
    // CHECKOUT LOGIN GATE
    // ================================================
    (function () {
        'use strict';

        const guestBtn  = document.getElementById('mu-continue-guest-btn');
        const loginBtn  = document.getElementById('mu-checkout-open-modal');
        const notice    = document.getElementById('mu-checkout-notice');

        if (!notice) return; // Gate no presente (usuario logueado o page order-received)

        var activateGuestMode = function () {
            document.body.classList.add('mu-guest-checkout');
            notice.classList.add('mu-hidden');
            try { sessionStorage.setItem('mu_guest_active', 'true'); } catch (e) {}
            setTimeout(function () {
                var billing = document.querySelector('.woocommerce-billing-fields');
                if (billing) {
                    billing.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    var firstInput = billing.querySelector('input:not([type="hidden"])');
                    if (firstInput) firstInput.focus();
                }
            }, 300);
        };

        if (guestBtn) {
            guestBtn.addEventListener('click', function (e) {
                e.preventDefault();
                activateGuestMode();
            });
        }

        if (loginBtn) {
            loginBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var trigger = document.querySelector('.mu-open-auth-modal, .login-btn, [href="#login"]');
                if (trigger && trigger !== loginBtn) {
                    trigger.click();
                } else {
                    window.location.href = myAccountUrl;
                }
            });
        }

        // Restaurar estado de sesión previa
        try {
            if (sessionStorage.getItem('mu_guest_active') === 'true') {
                document.body.classList.add('mu-guest-checkout');
                notice.classList.add('mu-hidden');
            }
        } catch (e) {}
    }());

});
