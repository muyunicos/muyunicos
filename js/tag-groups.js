/**
 * Muy Únicos - Navegación de Grupos de Etiquetas
 *
 * Maneja la navegación de los cards de grupos de etiquetas,
 * integrándose con el sistema de navigation chips existente.
 *
 * @package GeneratePress_Child
 */

(function($) {
    'use strict';

    if (typeof $ === 'undefined' || !$.fn) {
        return;
    }

    $(document).ready(function() {
        initTagGroupsNavigation();
    });

    /**
     * Inicializa la navegación de grupos de etiquetas
     */
    function initTagGroupsNavigation() {
        // Manejar click en cards de grupos
        $(document.body).on('click', '.mu-tag-group-link', function(e) {
            const $link = $(this);
            const tagSlug = $link.closest('.mu-tag-group-card').data('tag-slug');

            if (!tagSlug) {
                return;
            }

            // Integrar con sistema de navigation chips
            if (typeof muNavChips !== 'undefined' && muNavChips.setTagActive) {
                sessionStorage.setItem('mu_selected_tag', tagSlug);
            }
        });

        // Restaurar estado de etiqueta seleccionada
        if (sessionStorage.getItem('mu_selected_tag')) {
            const selectedTag = sessionStorage.getItem('mu_selected_tag');
            sessionStorage.removeItem('mu_selected_tag');

            if (typeof muNavChips !== 'undefined' && muNavChips.highlightTag) {
                muNavChips.highlightTag(selectedTag);
            }
        }
    }

})(jQuery);