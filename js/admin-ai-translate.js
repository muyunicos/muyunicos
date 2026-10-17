/**
 * Muy Únicos — Admin Traducciones IA
 * Carga: marketing_page_mu-ai-translate
 *
 * Única responsabilidad: el botón "Probar conexión" de la pantalla del servicio.
 * Cualquier otra acción (guardar ajustes) es un POST normal del formulario.
 *
 * @package GeneratePress_Child
 */
(function ($) {
    'use strict';

    $(function () {
        var $btn    = $('#mu-ai-test');
        var $result = $('#mu-ai-test-result');

        if (!$btn.length || typeof muAiAdmin === 'undefined') {
            return;
        }

        $btn.on('click', function (e) {
            e.preventDefault();

            $btn.prop('disabled', true);
            $result.removeClass('is-ok is-bad').text(muAiAdmin.testing);

            $.post(muAiAdmin.ajaxUrl, {
                action: 'mu_ai_test',
                nonce: muAiAdmin.nonce
            })
                .done(function (response) {
                    if (response && response.success) {
                        var d = response.data;
                        $result.addClass('is-ok').text(
                            muAiAdmin.ok + ' ' + d.model + ' · ' + d.latency_ms + ' ms'
                        );
                    } else {
                        var msg = (response && response.data && response.data.error) ? response.data.error : muAiAdmin.error;
                        $result.addClass('is-bad').text(msg);
                    }
                })
                .fail(function () {
                    $result.addClass('is-bad').text(muAiAdmin.error);
                })
                .always(function () {
                    $btn.prop('disabled', false);
                });
        });
    });
})(jQuery);
