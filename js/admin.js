/**
 * Muy Únicos - Scripts de Administración
 */
jQuery(document).ready(function($) {
    'use strict';
    
    // Botón de reindexado de productos digitales
    var $rebuildBtn = $('#muyu-rebuild');
    
    if ( $rebuildBtn.length ) {
        $rebuildBtn.on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var originalText = $btn.text();
            
            $btn.prop('disabled', true).text('⏳ Procesando...');
            
            $.post(ajaxurl, {
                action: 'muyu_rebuild_digital_list',
                nonce: $btn.data('nonce')
            }, function(response) {
                if ( response.success ) {
                    alert('✅ ' + response.data);
                    location.reload();
                } else {
                    alert('❌ Error: ' + (response.data || 'Desconocido'));
                    $btn.prop('disabled', false).text(originalText);
                }
            }).fail(function() {
                alert('❌ Error de conexión con el servidor');
                $btn.prop('disabled', false).text(originalText);
            });
        });
    }
});
