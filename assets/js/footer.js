/**
 * Footer - Interacciones JavaScript
 * Migrado desde snippet "Footer"
 */

(function() {
    'use strict';
    
    /**
     * Inicializa los accordions del footer
     * En desktop (> 900px): todos abiertos por defecto
     * En móvil (<= 900px): controlados por el usuario
     */
    function initFooterAccordions() {
        // Solo aplicar auto-apertura en desktop
        if (window.innerWidth > 900) {
            const accordions = document.querySelectorAll('details.mu-accordion');
            accordions.forEach(function(accordion) {
                accordion.setAttribute('open', '');
            });
        }
    }

    // Inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFooterAccordions);
    } else {
        initFooterAccordions();
    }
})();
