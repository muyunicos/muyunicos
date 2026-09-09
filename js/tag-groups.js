/**
 * Muy Únicos - Navegación de Grupos de Etiquetas + Admin Switch v2.0 (Performance Optimized)
 *
 * Maneja la navegación de los cards de grupos de etiquetas,
 * integrándose con el sistema de navigation chips existente.
 * También maneja el toggle "Agrupar" para administradores en páginas de tag.
 *
 * Optimizaciones de rendimiento:
 * - Rate limiting para llamadas AJAX admin
 * - Session-based caching para estados de switches
 * - Prevenir solicitudes duplicadas
 *
 * @package GeneratePress_Child
 */

(function($) {
    'use strict';

    if (typeof $ === 'undefined' || !$.fn) {
        return;
    }

    // Rate limiting configuration
    const RATE_LIMIT_DELAY = 1000; // 1 segundo entre solicitudes
    const CACHE_DURATION = 30 * 60 * 1000; // 30 minutos de caché
    let lastRequestTime = 0;
    let activeRequest = null;

    $(document).ready(function() {
        initTagGroupsNavigation();
        initTagGroupAdminSwitch();
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

    /**
     * Verifica rate limiting antes de permitir solicitud AJAX
     */
    function checkRateLimit() {
        const now = Date.now();
        if (now - lastRequestTime < RATE_LIMIT_DELAY) {
            return false;
        }
        return true;
    }

    /**
     * Obtiene estado cacheado del switch
     */
    function getCachedSwitchState(tagSlug, catSlug) {
        try {
            const cacheKey = `mu_tag_group_${tagSlug}_${catSlug}`;
            const cached = sessionStorage.getItem(cacheKey);
            if (cached) {
                const data = JSON.parse(cached);
                const now = Date.now();
                if (now - data.timestamp < CACHE_DURATION) {
                    return data.state;
                } else {
                    sessionStorage.removeItem(cacheKey);
                }
            }
        } catch (e) {
            console.warn('Error accessing sessionStorage:', e);
        }
        return null;
    }

    /**
     * Guarda estado del switch en caché
     */
    function cacheSwitchState(tagSlug, catSlug, state) {
        try {
            const cacheKey = `mu_tag_group_${tagSlug}_${catSlug}`;
            const data = {
                state: state,
                timestamp: Date.now()
            };
            sessionStorage.setItem(cacheKey, JSON.stringify(data));
        } catch (e) {
            console.warn('Error saving to sessionStorage:', e);
        }
    }

    /**
     * Inicializa el switch "Agrupar" para administradores con rate limiting
     */
    function initTagGroupAdminSwitch() {
        const $switch = $('.mu-tag-group-admin-switch');
        if (!$switch.length) {
            return;
        }

        const $checkbox = $switch.find('.mu-tag-group-checkbox');
        const $status = $switch.find('.mu-tag-group-status');
        const catName = $switch.data('cat-name');

        $checkbox.on('change', function() {
            const isChecked = $(this).prop('checked');
            const tagSlug = $switch.data('tag-slug');
            const catSlug = $switch.data('cat-slug');
            const nonce = $switch.data('nonce');

            if (!tagSlug || !catSlug) {
                return;
            }

            // Verificar rate limiting
            if (!checkRateLimit()) {
                // Revertir checkbox y mostrar mensaje de rate limit
                $checkbox.prop('checked', !isChecked);
                showError($switch, 'Esperá un momento antes de hacer otro cambio');
                return;
            }

            // Verificar si hay solicitud activa
            if (activeRequest) {
                $checkbox.prop('checked', !isChecked);
                showError($switch, 'Procesando solicitud anterior');
                return;
            }

            // Mostrar loading
            $switch.addClass('is-loading');
            $switch.removeClass('has-error');
            $switch.find('.mu-tag-group-error-msg').remove();

            const action = isChecked ? 'add' : 'remove';

            // Crear promise para solicitud activa
            activeRequest = $.ajax({
                url: typeof muTagGroupsAdmin !== 'undefined' && muTagGroupsAdmin.ajaxUrl
                    ? muTagGroupsAdmin.ajaxUrl
                    : window.location.origin + '/wp-admin/admin-ajax.php',
                method: 'POST',
                data: {
                    action: 'mu_toggle_tag_group',
                    tag_slug: tagSlug,
                    cat_slug: catSlug,
                    toggle_action: action,
                    nonce: typeof muTagGroupsAdmin !== 'undefined' && muTagGroupsAdmin.nonce
                        ? muTagGroupsAdmin.nonce
                        : nonce
                },
                success: function(response) {
                    lastRequestTime = Date.now();

                    if (response.success) {
                        // Actualizar estado visual
                        if (isChecked) {
                            $status.text('✓ Agrupado en "' + catName + '"');
                            $status.addClass('is-grouped');
                        } else {
                            $status.text('No agrupado');
                            $status.removeClass('is-grouped');
                        }

                        // Actualizar caché
                        cacheSwitchState(tagSlug, catSlug, isChecked);
                    } else {
                        // Revertir checkbox
                        $checkbox.prop('checked', !isChecked);
                        showError($switch, response.data && response.data.message ? response.data.message : 'Error al actualizar');
                    }
                },
                error: function() {
                    lastRequestTime = Date.now();
                    // Revertir checkbox
                    $checkbox.prop('checked', !isChecked);
                    showError($switch, 'Error de conexión');
                },
                complete: function() {
                    $switch.removeClass('is-loading');
                    activeRequest = null;
                }
            });
        });
    }

    /**
     * Muestra un mensaje de error en el switch
     *
     * @param {jQuery} $switch Elemento del switch
     * @param {string} message Mensaje de error
     */
    function showError($switch, message) {
        $switch.addClass('has-error');
        const $errorMsg = $('<span class="mu-tag-group-error-msg"></span>').text(message);
        $switch.append($errorMsg);

        // Auto-remover después de 5 segundos
        setTimeout(function() {
            $errorMsg.fadeOut(300, function() {
                $(this).remove();
                $switch.removeClass('has-error');
            });
        }, 5000);
    }

})(jQuery);