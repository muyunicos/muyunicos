/* ============================================
   COUNTRY MODAL - JavaScript Controller
   Versión: 2.0.0 (Migrado desde snippet)
   Maneja la lógica de interacción del modal
   ============================================ */

(function() {
    'use strict';
    
    // Variables del DOM
    var closeBtn = null;
    var stayBtn = null;
    var overlay = null;
    var currentDomain = '';
    
    /**
     * Inicializa el modal y sus event listeners
     */
    function init() {
        // Obtener referencias del DOM
        closeBtn = document.getElementById('muyu-country-close');
        stayBtn = document.getElementById('muyu-country-stay');
        overlay = document.getElementById('muyu-country-modal-overlay');
        
        // Validar que los elementos existen
        if (!overlay) {
            return; // Modal no presente en esta página
        }
        
        // Obtener dominio actual del atributo data
        currentDomain = overlay.getAttribute('data-current-domain') || '';
        
        // Event listener: Botón Cerrar
        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                hideModal();
            });
        }
        
        // Event listener: Botón "Quedarme aquí"
        if (stayBtn) {
            stayBtn.addEventListener('click', function(e) {
                e.preventDefault();
                savePreference();
                hideModal();
            });
        }
        
        // Event listener: Click fuera del modal (en el overlay)
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                hideModal();
            }
        });
        
        // Event listener: Tecla ESC para cerrar
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && overlay && overlay.style.display !== 'none') {
                hideModal();
            }
        });
        
        // Mostrar modal automáticamente
        showModal();
    }
    
    /**
     * Muestra el modal con animación
     */
    function showModal() {
        if (overlay) {
            overlay.style.display = 'flex';
            // Trigger reflow para animación CSS
            void overlay.offsetWidth;
            overlay.classList.add('is-visible');
        }
    }
    
    /**
     * Oculta el modal
     */
    function hideModal() {
        if (overlay) {
            overlay.classList.remove('is-visible');
            setTimeout(function() {
                overlay.style.display = 'none';
            }, 300); // Esperar animación CSS
        }
    }
    
    /**
     * Guarda la preferencia del usuario en una cookie
     * Cookie dura 1 año y aplica a todo el dominio principal
     */
    function savePreference() {
        if (!currentDomain) {
            return;
        }
        
        var domain = '.muyunicos.com';
        var d = new Date();
        d.setFullYear(d.getFullYear() + 1);
        
        var cookieString = 'muyu_stay_here=' + encodeURIComponent(currentDomain) +
                          ';path=/;domain=' + domain +
                          ';expires=' + d.toUTCString() +
                          ';SameSite=Lax';
        
        document.cookie = cookieString;
    }
    
    // Inicialización con DOMContentLoaded guard
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
})();