/**
 * Muy Únicos - Funcionalidad de Tienda y Producto (Shop/Single Product)
 * 
 * Auto-selección inteligente de variación:
 * Oculta o pre-selecciona variaciones basado en el formato y datos del servidor.
 */

(function($) {
    'use strict';
    
    if ( 'undefined' === typeof $ || ! $.fn ) {
        return;
    }
    
    // Delegar evento en el document para soportar inicializaciones AJAX (ej: Quick Views)
    $(document).on('wc_variation_form', 'form.variations_form', function() {
        var $form = $(this);
        var $data = $('#mu-format-autoselect-data');
        
        if ( ! $data.length ) {
            return;
        }
        
        var targetSlug = $data.data('target-slug');
        var hideRow = $data.data('hide-row') === true;
        
        // Ejecutar con un pequeño delay para asegurar que WC renderizó los options
        setTimeout(function() {
            autoSelectFormatVariation($form, targetSlug, hideRow);
        }, 100);
    });

    // Ejecutar fallback en page load por si el evento ya pasó
    $(document).ready(function() {
        var $form = $('form.variations_form');
        if ( $form.length ) {
            var $data = $('#mu-format-autoselect-data');
            if ( $data.length ) {
                setTimeout(function() {
                    autoSelectFormatVariation($form, $data.data('target-slug'), $data.data('hide-row') === true);
                }, 150);
            }
        }
    });
    
    function autoSelectFormatVariation($form, targetSlug, hideRow) {
        var $select = $form.find('#pa_formato');
        
        if ( ! $select.length ) {
            // Fallback por name
            $select = $form.find('select[name="attribute_pa_formato"]');
        }
        
        if ( ! $select.length ) {
            return;
        }
        
        // Si ya está seleccionado como queremos, ocultamos y salimos
        if ( $select.val() === targetSlug ) {
            if ( hideRow ) {
                hideRowAndTable($select, $form);
            }
            return;
        }
        
        // Seleccionar el valor objetivo
        $select.val(targetSlug);
        $select.trigger('change');
        // $form.trigger('check_variations'); // Evitar loops si WC ya está chequeando
        
        if ( hideRow ) {
            hideRowAndTable($select, $form);
        }
    }
    
    function hideRowAndTable($select, $form) {
        var $row = $select.closest('tr');
        $row.hide();
        
        if ( $form.find('table.variations tr:visible').length === 0 ) {
            $form.find('.variations').fadeOut(200);
        }
    }

})(jQuery);
