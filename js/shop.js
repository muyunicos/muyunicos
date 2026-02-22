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
    
    $(document).ready(function() {
        var $form = $('form.variations_form');
        var $data = $('#mu-format-autoselect-data');
        
        if ( ! $form.length || ! $data.length ) {
            return;
        }
        
        var targetSlug = $data.data('target-slug');
        var hideRow = $data.data('hide-row') === true;
        
        $form.on('wc_variation_form', function() {
            setTimeout(autoSelectFormatVariation, 100);
        });
        
        // Ejecutar inmediatamente por si el evento ya pasó
        setTimeout(autoSelectFormatVariation, 150);
        
        function autoSelectFormatVariation() {
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
            $form.trigger('check_variations');
            
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
    });
})(jQuery);
