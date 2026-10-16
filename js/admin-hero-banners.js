/**
 * Muy Únicos — Admin Hero Banners
 * Carga: marketing_page_mu-hero-banners
 * - Add/remove rows (template clone)
 * - WP Media picker para imagen
 * - Selector de países (dropdown + píldoras)
 * - Selector de idioma (dropdown ES/EN/PT) con réplica de campos por idioma
 *   y checkbox "Traducir"
 *
 * Los listeners usan delegación sobre #mu-hero-banners-list para que también
 * funcionen en las filas creadas con "+ Agregar banner" (clon del template).
 */
(function ($) {
    'use strict';

    var IDIOMAS = ['es', 'en', 'pt'];

    /**
     * Muestra el bloque del idioma activo y oculta los demás.
     * Cada .mu-hero-banner-lang-block trae su propio juego de campos:
     * 'es' es el idioma base y 'pt' / 'en' son las traducciones (réplica).
     */
    function actualizarTraducir($row, idioma) {
        var lang = String(idioma || '').toLowerCase();
        $row.find('.mu-hero-banner-lang-block').each(function () {
            var $block = $(this);
            $block.css('display', $block.attr('data-lang') === lang ? 'block' : 'none');
        });
    }

    /**
     * Sincroniza las píldoras de idiomas traducidos con los checkboxes.
     */
    function actualizarPillsIdiomas($row) {
        $row.find('.mu-hero-banner-idiomas-pills .mu-hero-banner-pill--lang').each(function () {
            var $pill   = $(this);
            var lang    = $pill.attr('data-lang');
            var activo  = $row.find('.mu-hero-banner-traducir-checkbox[data-lang="' + lang + '"]').is(':checked');
            $pill.css('display', activo ? '' : 'none');
        });
    }

    /**
     * Muestra u oculta el juego de campos de una traducción y su aviso.
     */
    function actualizarCamposTraduccion($row, lang, activo) {
        $row.find('.mu-hero-banner-traducir-' + lang).css('display', activo ? 'grid' : 'none');
        $row.find('.mu-hero-banner-traducir-wrapper[data-lang="' + lang + '"] .mu-hero-banner-traducir-hint')
            .css('display', activo ? 'none' : '');
    }

    /**
     * Copia los valores de español a los campos vacíos del idioma indicado.
     * Es sólo ayuda visual: al guardar, los valores idénticos al español no se
     * persisten (sobrescritura dispersa en mu_hero_banners_sanitize_one()).
     */
    function precargarDesdeEs($row, lang) {
        var $destino = $row.find('.mu-hero-banner-traducir-wrapper[data-lang="' + lang + '"]');
        var $origen  = $row.find('.mu-hero-banner-lang-block[data-lang="es"]');
        if (!$destino.length || !$origen.length) return;

        $destino.find('[data-field]').each(function () {
            var $input = $(this);
            if ($input.val() !== '') return;

            var field = $input.attr('data-field');
            var $src  = $origen.find('[data-field="' + field + '"]');
            if (!$src.length) return;

            var valor = $src.val();
            $input.val(valor);

            // Refrescar la previsualización de la imagen si corresponde.
            var $preview = $input.closest('.mu-hero-banner-row__image').find('.mu-hero-banner-row__image-preview');
            if ($preview.length) {
                $preview.attr('src', valor).css('display', valor ? '' : 'none');
            }
        });
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
            actualizarTraducir($row, $row.find('.mu-hero-banner-idioma-btn').attr('data-idioma'));
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
        // Selector de idioma
        // ---------------------------------------------------------------
        $list.on('click', '.mu-hero-banner-idioma-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var $row = $(this).closest('.mu-hero-banner-row');
            var $dd  = $row.find('.mu-hero-banner-idioma-dropdown');
            var visible = $dd.is(':visible');

            $list.find('.mu-hero-banner-idioma-dropdown').hide();
            if (!visible) {
                posicionarDropdown($(this), $dd);
                $dd.show();
            }
        });

        $list.on('click', '.mu-hero-banner-idioma-item', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var $item  = $(this);
            var idioma = String($item.attr('data-idioma') || '').toLowerCase();
            if (IDIOMAS.indexOf(idioma) === -1) return;

            var $row = $item.closest('.mu-hero-banner-row');
            var $btn = $row.find('.mu-hero-banner-idioma-btn');
            var label = idioma.toUpperCase();

            $btn.attr('data-idioma', label);
            $btn.find('.mu-hero-banner-idioma-code').text(label);
            $row.find('.mu-hero-banner-idioma-valor').val(idioma);

            // El ítem elegido se oculta; el resto queda disponible
            $row.find('.mu-hero-banner-idioma-item').show();
            $item.hide();

            $row.find('.mu-hero-banner-idioma-dropdown').hide();

            actualizarTraducir($row, idioma);
        });

        // Evitar que el clic dentro del dropdown de idioma lo cierre
        $list.on('click', '.mu-hero-banner-idioma-dropdown', function (e) {
            e.stopPropagation();
        });

        // ---------------------------------------------------------------
        // Checkbox "Traducir"
        // ---------------------------------------------------------------
        $list.on('change', '.mu-hero-banner-traducir-checkbox', function () {
            var $cb    = $(this);
            var lang   = String($cb.attr('data-lang') || '').toLowerCase();
            var $row   = $cb.closest('.mu-hero-banner-row');
            var activo = $cb.is(':checked');

            if (activo) {
                precargarDesdeEs($row, lang);
            }
            actualizarCamposTraduccion($row, lang, activo);
            actualizarPillsIdiomas($row);
        });

        // ---------------------------------------------------------------
        // Cerrar dropdowns al hacer clic fuera
        // ---------------------------------------------------------------
        $(document).on('click', function () {
            $list.find('.mu-hero-banner-idioma-dropdown, .mu-hero-banner-paises-dropdown').hide();
        });

        // Estado inicial de cada fila ya renderizada
        $list.find('.mu-hero-banner-row').each(function () {
            var $row = $(this);
            actualizarTraducir($row, $row.find('.mu-hero-banner-idioma-btn').attr('data-idioma'));

            // Idiomas activados: completar con el texto de español los campos
            // vacíos (ayuda visual; al guardar no se persisten por ser idénticos).
            $row.find('.mu-hero-banner-traducir-checkbox:checked').each(function () {
                var lang = String($(this).attr('data-lang') || '').toLowerCase();
                precargarDesdeEs($row, lang);
                actualizarCamposTraduccion($row, lang, true);
            });

            actualizarPillsIdiomas($row);
        });
    }

    $(ready);
})(jQuery);
