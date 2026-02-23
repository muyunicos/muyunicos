/**
 * Admin Orders: WhatsApp Link Replacer
 */
(function($){
    'use strict';
    
    $(document).ready(function(){
        // Buscamos el enlace del teléfono en la columna de facturación (Legacy Admin)
        // Nota: HPOS tiene selectores diferentes, esto cubre Legacy principalmente.
        var $enlaceTelefono = $('.order_data_column a[href^="tel:"]');
        
        if( $enlaceTelefono.length ) {
            var telefono = $enlaceTelefono.attr('href').replace('tel:', '');
            // Limpieza básica
            var telefono_limpio = telefono.replace(/[^0-9]/g, '');
            
            // Obtener nombre del cliente (intento heurístico: buscar en el mismo contenedor)
            // En legacy: .address p -> "Nombre Apellido<br>"
            // Mejor opción: no poner nombre si es complicado extraerlo de forma fiable via JS.
            // Opcional: Obtener texto del DOM si es necesario.
            
            // Construir URL
            var whatsapp_url = muOrderWA.apiUrl + '?phone=' + telefono_limpio;
            
            // Cambiamos el destino del click a la API de WhatsApp
            $enlaceTelefono.attr('href', whatsapp_url);
            $enlaceTelefono.attr('target', '_blank');
            $enlaceTelefono.css({'color': '#25D366', 'font-weight': 'bold'});
            
            // Usamos el label localizado
            $enlaceTelefono.text( muOrderWA.label + $enlaceTelefono.text() );
        }
    });

})(jQuery);
