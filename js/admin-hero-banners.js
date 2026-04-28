/**
 * Muy Únicos — Admin Hero Banners
 * Carga: marketing_page_mu-hero-banners
 * - Add/remove rows (template clone)
 * - WP Media picker para imagen
 */
(function ($) {
    'use strict';

    function ready() {
        var $list  = $('#mu-hero-banners-list');
        var $tpl   = $('#mu-hero-banner-template');
        if (!$list.length || !$tpl.length) return;

        var nextIndex = $list.find('.mu-hero-banner-row').length;

        // Agregar fila
        $('#mu-hero-banners-add').on('click', function () {
            var html = $tpl.html().replace(/__INDEX__/g, String(nextIndex++));
            var $row = $(html);
            $list.append($row);
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

            var $row = $(this).closest('.mu-hero-banner-row');
            var $url = $row.find('.mu-hero-banner-row__image-url');
            var $img = $row.find('.mu-hero-banner-row__image-preview');

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
    }

    $(ready);
})(jQuery);
