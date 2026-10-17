/**
 * Muy Únicos — Admin Hero Banners
 * Carga: marketing_page_mu-hero-banners
 * - Add/remove rows (template clone)
 * - WP Media picker para imagen
 * - Selector de países (dropdown + píldoras)
 * - Píldoras de idioma ES | PT | EN (mismo componente que el editor de reseñas):
 *   eligen qué bloque se edita. El punto marca los idiomas con contenido propio y
 *   sale automático del contenido — ya no hay checkbox "Traducir".
 * - Botón "Traducir con IA" (ámbito hero_banner): completa los campos del idioma
 *   activo desde el español y NUNCA guarda (se revisa y se guarda a mano).
 *
 * Los listeners usan delegación sobre #mu-hero-banners-list para que también
 * funcionen en las filas creadas con "+ Agregar banner" (clon del template).
 */
(function ($) {
    'use strict';

    var IDIOMAS = ['es', 'en', 'pt'];

    /**
     * Idioma activo de una fila: la píldora marcada (o el input oculto).
     */
    function idiomaActivo($row) {
        var lang = String($row.find('.mu-lang-pill.is-active').attr('data-lang') || '').toLowerCase();

        if (IDIOMAS.indexOf(lang) === -1) {
            lang = String($row.find('.mu-hero-banner-idioma-valor').val() || 'es').toLowerCase();
        }

        return IDIOMAS.indexOf(lang) === -1 ? 'es' : lang;
    }

    /**
     * Muestra el bloque del idioma elegido y sincroniza píldoras, input oculto y
     * botón de IA. Cada .mu-hero-banner-lang-block trae su propio juego de campos:
     * 'es' es el idioma base y 'pt' / 'en' son las traducciones (réplica).
     */
    function setLang($row, idioma) {
        var lang = String(idioma || 'es').toLowerCase();
        if (IDIOMAS.indexOf(lang) === -1) { lang = 'es'; }

        $row.find('.mu-hero-banner-lang-block').each(function () {
            var $block = $(this);
            $block.css('display', $block.attr('data-lang') === lang ? 'block' : 'none');
        });

        $row.find('.mu-lang-pill').each(function () {
            var $pill  = $(this);
            var activa = $pill.attr('data-lang') === lang;
            $pill.toggleClass('is-active', activa);
            $pill.attr('aria-pressed', activa ? 'true' : 'false');
        });

        // El idioma abierto se recuerda al guardar (solo la pestaña del editor).
        $row.find('.mu-hero-banner-idioma-valor').val(lang);

        // La IA traduce del español a otro idioma: en ES no aplica.
        $row.find('.mu-hero-banner-ai-btn').prop('disabled', lang === 'es');
    }

    /**
     * Punto de la píldora [automático]: ese idioma guarda algún valor propio, es
     * decir distinto del español (lo idéntico al español no se persiste).
     */
    function actualizarPunto($row, lang) {
        if (lang === 'es') return;

        var $pills = $row.find('.mu-lang-pill[data-lang="' + lang + '"]');
        if (!$pills.length) return;

        var $origen = $row.find('.mu-hero-banner-lang-block[data-lang="es"]');
        var tiene   = false;

        $row.find('.mu-hero-banner-lang-block[data-lang="' + lang + '"] [data-field]').each(function () {
            var $campo = $(this);
            var $base  = $origen.find('[data-field="' + String($campo.attr('data-field') || '') + '"]');
            if (!$base.length) return;

            if (String($campo.val() || '') !== String($base.val() || '')) {
                tiene = true;
                return false; // corta el each: alcanza con un campo propio
            }
        });

        $pills.attr('data-has-translation', tiene ? '1' : '0');
    }

    /** Punto de los dos idiomas traducibles (dependen del español y de sí mismos). */
    function actualizarPuntos($row) {
        ['pt', 'en'].forEach(function (lang) { actualizarPunto($row, lang); });
    }

    /** Valor de un campo sin marcado (el placeholder no interpreta HTML). */
    function textoPlano(valor) {
        return $('<div></div>').html(String(valor || '')).text().trim();
    }

    /**
     * El español se muestra como placeholder en los campos vacíos de PT/EN: es
     * ayuda visual y no se envía, así el punto sigue marcando solo lo que tiene
     * valor propio. (Antes el checkbox copiaba los valores al marcarlo, lo que
     * hacía imposible distinguir una traducción real de una copia.)
     */
    function aplicarPlaceholders($row) {
        var $origen = $row.find('.mu-hero-banner-lang-block[data-lang="es"]');

        ['pt', 'en'].forEach(function (lang) {
            $row.find('.mu-hero-banner-lang-block[data-lang="' + lang + '"] [data-field]').each(function () {
                var $campo = $(this);
                var $base  = $origen.find('[data-field="' + String($campo.attr('data-field') || '') + '"]');
                if (!$base.length) return;

                var texto = textoPlano($base.val());
                if (texto.length > 90) { texto = texto.slice(0, 90) + '…'; }

                $campo.attr('placeholder', texto);
            });
        });
    }

    /**
     * Estado completo de una fila: píldora activa, puntos y placeholders.
     */
    function refrescarFila($row) {
        setLang($row, idiomaActivo($row));
        aplicarPlaceholders($row);
        actualizarPuntos($row);
    }

    /** Aviso del botón de IA (verde si salió bien). */
    function setAiStatus($estado, texto, ok) {
        if (!$estado || !$estado.length) return;

        $estado.text(texto || '');
        $estado.toggleClass('is-ok', !!ok);
        $estado.toggleClass('is-bad', !ok && !!texto);
    }

    /**
     * Posiciona un dropdown justo debajo de su botón, dentro del head de la fila.
     */
    function posicionarDropdown($btn, $dd) {
        var offset = $btn.position();
        if (!offset) return;
        $dd.css({
            left: Math.round(offset.left) + 'px',
            top: Math.round(offset.top + $btn.outerHeight(true)) + 'px'
        });
    }

    function ready() {
        var $list = $('#mu-hero-banners-list');
        var $tpl  = $('#mu-hero-banner-template');
        if (!$list.length || !$tpl.length) return;

        var nextIndex = $list.find('.mu-hero-banner-row').length;

        // Agregar fila
        $('#mu-hero-banners-add').on('click', function () {
            var html = $tpl.html().replace(/__INDEX__/g, String(nextIndex++));
            var $row = $(html);
            $list.append($row);

            // Normalizar la fila nueva (idioma base ES por defecto)
            refrescarFila($row);
        });

        // Eliminar fila (marca _delete=1 y oculta visualmente; backend descarta)
        $list.on('click', '.mu-hero-banner-row__remove', function () {
            var $row = $(this).closest('.mu-hero-banner-row');
            var $flag = $row.find('.mu-hero-banner-row__delete-flag');
            $flag.val('1');
            $row.addClass('is-deleted');
            $row.find('input, textarea, select').not($flag).prop('disabled', true);
            $(this).text(window.muHeroBannersData && window.muHeroBannersData.removed ? window.muHeroBannersData.removed : 'Eliminado (se descarta al guardar)');
            $(this).prop('disabled', true);
        });

        // WP Media picker
        $list.on('click', '.mu-hero-banner-row__image-pick', function (e) {
            e.preventDefault();
            if (typeof wp === 'undefined' || !wp.media) return;

            // Acotado al bloque de imagen de ESTE idioma: la fila tiene uno por idioma.
            var $wrap = $(this).closest('.mu-hero-banner-row__image');
            var $url  = $wrap.find('.mu-hero-banner-row__image-url');
            var $img  = $wrap.find('.mu-hero-banner-row__image-preview');

            var frame = wp.media({
                title: (window.muHeroBannersData && window.muHeroBannersData.mediaTitle) || 'Seleccionar imagen',
                button: { text: (window.muHeroBannersData && window.muHeroBannersData.mediaButton) || 'Usar esta imagen' },
                multiple: false
            });

            frame.on('select', function () {
                var att = frame.state().get('selection').first().toJSON();
                // Guardamos URL relativa al sitio cuando es posible (más portable).
                var url = att.url || '';
                try {
                    var loc = window.location;
                    var origin = loc.protocol + '//' + loc.host;
                    if (url.indexOf(origin) === 0) {
                        url = url.substring(origin.length);
                    }
                } catch (err) { /* noop */ }

                $url.val(url);
                $img.attr('src', url).show();
            });

            frame.open();
        });

        // ---------------------------------------------------------------
        // Selector de países
        // ---------------------------------------------------------------
        $list.on('click', '.mu-hero-banner-paises-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var $row = $(this).closest('.mu-hero-banner-row');
            var $dd  = $row.find('.mu-hero-banner-paises-dropdown');
            var visible = $dd.is(':visible');

            $list.find('.mu-hero-banner-paises-dropdown').hide();
            if (!visible) {
                posicionarDropdown($(this), $dd);
                $dd.show();
            }
        });

        // Refrescar las píldoras al marcar/desmarcar un país
        $list.on('change', '.mu-hero-banner-paises-dropdown input[type="checkbox"]', function () {
            var $row   = $(this).closest('.mu-hero-banner-row');
            var $pills = $row.find('.mu-hero-banner-paises-pills');
            $pills.empty();
            $row.find('.mu-hero-banner-paises-dropdown input[type="checkbox"]:checked').each(function () {
                $('<span class="mu-hero-banner-pill"></span>').text($(this).val()).appendTo($pills);
            });
        });

        // Evitar que el clic dentro del dropdown de países lo cierre
        $list.on('click', '.mu-hero-banner-paises-dropdown', function (e) {
            e.stopPropagation();
        });

        // ---------------------------------------------------------------
        // Píldoras de idioma: eligen qué bloque se edita (ES | PT | EN)
        // ---------------------------------------------------------------
        $list.on('click', '.mu-lang-pill', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var $pill = $(this);
            var lang  = String($pill.attr('data-lang') || '').toLowerCase();
            if (IDIOMAS.indexOf(lang) === -1) return;

            setLang($pill.closest('.mu-hero-banner-row'), lang);
        });

        // Al escribir: refrescar el punto del idioma (¿tiene valor propio?). Si se
        // tocó el español, se actualizan además placeholders y puntos de PT/EN,
        // porque heredan de él.
        $list.on('input change', '.mu-hero-banner-row [data-field]', function () {
            var $campo = $(this);
            var $row   = $campo.closest('.mu-hero-banner-row');
            var lang   = String($campo.closest('.mu-hero-banner-lang-block').attr('data-lang') || 'es').toLowerCase();

            if (lang === 'es') {
                aplicarPlaceholders($row);
                actualizarPuntos($row);
                return;
            }

            actualizarPunto($row, lang);
        });

        // ---------------------------------------------------------------
        // "Traducir con IA": del español al idioma activo. No guarda.
        // ---------------------------------------------------------------
        $list.on('click', '.mu-hero-banner-ai-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var $btn    = $(this);
            var $row    = $btn.closest('.mu-hero-banner-row');
            var $estado = $row.find('[data-role="ai-status"]');
            var lang    = idiomaActivo($row);

            if (lang === 'es') { return; }

            // Solo los campos que el ámbito declara (textos; URLs e imagen no se traducen).
            var $origen = $row.find('.mu-hero-banner-lang-block[data-lang="es"]');
            var payload = {};

            String($btn.attr('data-fields') || '').split(',').forEach(function (field) {
                field = String(field || '').trim();
                if (!field) return;

                var valor = String($origen.find('[data-field="' + field + '"]').val() || '');
                if (valor !== '') { payload[field] = valor; }
            });

            var campos = Object.keys(payload);
            if (!campos.length) {
                setAiStatus($estado, 'Escribí primero los textos en español.', false);
                return;
            }

            var data = new FormData();
            data.append('action', 'mu_ai_translate');
            data.append('nonce', (window.muHeroBannersData && window.muHeroBannersData.aiNonce) || '');
            data.append('ambito', 'hero_banner');
            data.append('lang', lang);
            data.append('fields', JSON.stringify(payload));

            $btn.prop('disabled', true);
            setAiStatus($estado, 'Traduciendo…', false);

            var url = (window.muHeroBannersData && window.muHeroBannersData.aiUrl) || '';

            fetch(url, { method: 'POST', body: data, credentials: 'same-origin' })
                .then(function (response) { return response.json(); })
                .then(function (res) {
                    $btn.prop('disabled', false);

                    var d = (res && res.data) ? res.data : {};

                    if (!res || !res.success) {
                        setAiStatus($estado, d.error || 'No se pudo traducir.', false);
                        return;
                    }

                    var items      = d.items || {};
                    var traducidos = 0;

                    campos.forEach(function (field) {
                        var item = items[field];
                        if (!item || item.unchanged || !item.translation) return; // ya estaba en ese idioma

                        $row.find('.mu-hero-banner-lang-block[data-lang="' + lang + '"] [data-field="' + field + '"]')
                            .val(item.translation);
                        traducidos++;
                    });

                    refrescarFila($row);

                    if (!traducidos) {
                        setAiStatus($estado, 'Los textos ya estaban en ese idioma.', false);
                        return;
                    }

                    setAiStatus(
                        $estado,
                        'Listo: ' + traducidos + ' campo' + (1 === traducidos ? '' : 's') + ' traducido' + (1 === traducidos ? '' : 's') + '. Revisá y guardá.',
                        true
                    );
                })
                .catch(function () {
                    $btn.prop('disabled', false);
                    setAiStatus($estado, 'No se pudo traducir. Revisá la conexión e intentá de nuevo.', false);
                });
        });

        // ---------------------------------------------------------------
        // Cerrar dropdowns al hacer clic fuera
        // ---------------------------------------------------------------
        $(document).on('click', function () {
            $list.find('.mu-hero-banner-paises-dropdown').hide();
        });

        // Estado inicial de cada fila ya renderizada
        $list.find('.mu-hero-banner-row').each(function () {
            refrescarFila($(this));
        });
    }

    $(ready);
})(jQuery);
