/**
 * Header - Interacciones JavaScript
 * Migrado desde snippet "Header"
 */

(function() {
    'use strict';
    
    /**
     * Inicializa la funcionalidad del dropdown de Mi Cuenta en móvil
     */
    function initAccountDropdown() {
        const accountWraps = document.querySelectorAll('.mu-account-dropdown-wrap');

        accountWraps.forEach(function(wrap) {
            const trigger = wrap.querySelector('.mu-open-auth-modal');
            const hasSubMenu = wrap.querySelector('.mu-sub-menu');

            if (trigger) {
                trigger.addEventListener('click', function(e) {
                    // Solo actuar en móvil
                    if (window.innerWidth <= 768) {
                        // Solo prevenir clic si existe submenú
                        if (hasSubMenu) {
                            e.preventDefault();
                            e.stopPropagation();

                            // Cerrar otros dropdowns
                            accountWraps.forEach(function(w) {
                                if (w !== wrap) {
                                    w.classList.remove('active');
                                }
                            });

                            // Toggle de la clase active
                            wrap.classList.toggle('active');
                        }
                        // Si no hay submenú, dejar que el enlace funcione normalmente
                    }
                });
            }
        });

        // Cerrar al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                accountWraps.forEach(function(wrap) {
                    if (!wrap.contains(e.target)) {
                        wrap.classList.remove('active');
                    }
                });
            }
        });

        // Limpiar al redimensionar a desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                accountWraps.forEach(function(wrap) {
                    wrap.classList.remove('active');
                });
            }
        });
    }

    // Inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAccountDropdown);
    } else {
        initAccountDropdown();
    }
})();
