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
        // --- Probador por ámbito: una solicitud real y muestra las variantes ---
        $('.mu-ai-probar').on('click', function (e) {
            e.preventDefault();

            var $btn   = $(this);
            var ambito = String($btn.attr('data-ambito') || '');
            var $texto = $('.mu-ai-probar-texto[data-ambito="' + ambito + '"]');
            var $lang  = $('.mu-ai-probar-lang[data-ambito="' + ambito + '"]');
            var $res   = $('.mu-ai-probar-resultado[data-ambito="' + ambito + '"]');
            var $vars  = $('.mu-ai-probar-variantes[data-ambito="' + ambito + '"]');
            var source = String($texto.val() || '').trim();
            var lang   = String($lang.val() || 'pt');

            $vars.empty();

            if (!ambito || !source) {
                $res.removeClass('is-ok').addClass('is-bad').text('Escribí un texto de ejemplo.');
                return;
            }

            $btn.prop('disabled', true);
            $res.removeClass('is-ok is-bad').text('Probando…');

            $.post(muAiAdmin.ajaxUrl, {
                action: 'mu_ai_translate',
                nonce: muAiAdmin.nonce,
                ambito: ambito,
                lang: lang,
                variants: 5,
                fields: JSON.stringify({ texto: source })
            })
                .done(function (response) {
                    $btn.prop('disabled', false);

                    if (!response || !response.success) {
                        var err = (response && response.data && response.data.error) ? response.data.error : 'No se pudo probar.';
                        $res.removeClass('is-ok').addClass('is-bad').text(err);
                        return;
                    }

                    var item     = (response.data.items && response.data.items.texto) ? response.data.items.texto : null;
                    var personas = response.data.personas || [];
                    var lista    = (item && item.variants && item.variants.length) ? item.variants : (item && item.translation ? [item.translation] : []);

                    if (!lista.length) {
                        $res.removeClass('is-ok').addClass('is-bad').text(
                            (item && item.unchanged) ? 'El servicio considera que ese texto ya está en el idioma.' : 'Sin respuesta utilizable.'
                        );
                        return;
                    }

                    $res.removeClass('is-bad').addClass('is-ok').text(
                        lista.length + (1 === lista.length ? ' versión' : ' versiones') + ' en ' + lang.toUpperCase()
                    );

                    lista.forEach(function (texto, i) {
                        $vars.append(
                            $('<p class="mu-ai-probar-var">')
                                .append($('<strong>').text((personas[i] ? personas[i] : 'Versión ' + (i + 1)) + ': '))
                                .append($('<span>').text(texto))
                        );
                    });
                })
                .fail(function () {
                    $btn.prop('disabled', false);
                    $res.removeClass('is-ok').addClass('is-bad').text('No se pudo probar. Revisá la conexión.');
                });
        });    });
})(jQuery);
