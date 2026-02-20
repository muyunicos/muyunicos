/**
 * Header - Interacciones JavaScript
 * Migrado desde snippet "Header"
 * Actualizado para soportar menús nativos de GeneratePress
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

    /**
     * Inicializa los submenús nativos de GeneratePress
     * Añade funcionalidad hover/click que puede haber sido interrumpida por CSS custom
     */
    function initNativeSubmenus() {
        const menuItems = document.querySelectorAll('.main-navigation .menu-item-has-children');
        
        // En móvil (cuando el menú está colapsado)
        if (window.innerWidth <= 768) {
            menuItems.forEach(function(item) {
                const link = item.querySelector('a');
                const submenu = item.querySelector('.sub-menu');
                
                if (link && submenu) {
                    // Clonar el link para remover listeners anteriores
                    const newLink = link.cloneNode(true);
                    link.parentNode.replaceChild(newLink, link);
                    
                    newLink.addEventListener('click', function(e) {
                        // Solo en móvil cuando el menú está abierto
                        if (document.querySelector('.main-navigation.toggled')) {
                            e.preventDefault();
                            e.stopPropagation();
                            
                            // Toggle del item
                            item.classList.toggle('sfHover');
                            item.classList.toggle('toggled-on');
                        }
                    });
                }
            });
        }
    }

    /**
     * Re-inicializa funcionalidad después de que GeneratePress modifica el DOM
     */
    function reinitOnMenuToggle() {
        const menuToggle = document.querySelector('.menu-toggle');
        
        if (menuToggle) {
            menuToggle.addEventListener('click', function() {
                // Esperar a que GeneratePress termine de animar
                setTimeout(function() {
                    initNativeSubmenus();
                }, 100);
            });
        }
    }

    /**
     * Inicialización principal
     */
    function init() {
        initAccountDropdown();
        initNativeSubmenus();
        reinitOnMenuToggle();
        
        // Re-inicializar submenús en resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                initNativeSubmenus();
            }, 250);
        });
    }

    // Inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
