/**
 * Muy Únicos - Funcionalidad de Tienda y Producto (Shop/Single Product)
 * * Incluye:
 * - Auto-selección inteligente de variación
 * - Infinite Scroll Ligero (WooCommerce + GP Optimized)
 * - Carrusel Híbrido Global (Grilla Desktop / Drag Mobile)
 */

(function($) {
    'use strict';
    
    if ( 'undefined' === typeof $ || ! $.fn ) {
        return;
    }
    
    // ============================================
    // 1. AUTO-SELECCIÓN DE VARIACIÓN
    // ============================================
    
    function initAutoSelectFormat() {
        // ---------------------------------------------------------
        // GUARD DE SEGURIDAD (Caché vs Sesión)
        // Detecta si el HTML sirvió un form simple para un producto variable
        // ---------------------------------------------------------
        var $data = $('#mu-format-autoselect-data');
        var $cartForm = $('form.cart');

        if ( $data.length && $cartForm.length && ! $cartForm.hasClass('variations_form') ) {
            // Interceptar el botón comprar del form simple incorrecto
            $cartForm.on('submit', function(e) {
                if ( ! $(this).find('input[name="variation_id"]').length ) {
                    e.preventDefault();
                    var $btn = $(this).find('button[type="submit"]');
                    if ($btn.length) {
                        $btn.css({'opacity': '0.7', 'pointer-events': 'none'}).text('Procesando...');
                    }
                    window.location.reload(); // Recarga silenciosa con sesión activa
                }
            });
            return;
        }

        var $form = $('form.variations_form');

        if ( ! $form.length || ! $data.length ) {
            return;
        }
        
        var targetSlug = $data.data('target-slug');
        var hideRow = $data.data('hide-row') === true || $data.attr('data-hide-row') === 'true';
        var userChanged = false; // Flag para detectar intención manual del usuario

        // Inyectar CSS para ocultar el enlace "Limpiar" y evitar FOUC
        if ( ! $('#mu-hide-reset-css').length ) {
            $('<style id="mu-hide-reset-css">.variations_form .reset_variations { display: none !important; visibility: hidden !important; pointer-events: none; }</style>').appendTo('head');
        }

        // Detectar si el usuario cambia el selector manualmente
        $form.on('change', 'select[name="attribute_pa_formato"], select[name="attribute_formato"]', function(e) {
            if ( e.originalEvent ) { // Si existe originalEvent, fue una interacción humana
                userChanged = true;
            }
        });

        var enforceSelection = function() {
            var $select = $form.find('select[name="attribute_pa_formato"], select[name="attribute_formato"]').first();
            
            if ( ! $select.length ) {
                return;
            }

            if ( hideRow ) {
                hideRowAndTable($select, $form);
            }
            
            // Si el usuario ya eligió manualmente y no estamos forzando ocultamiento, respetamos su decisión
            if ( userChanged && ! hideRow ) {
                return;
            }
            
            // Si el select ya tiene el valor correcto, no hacemos NADA. 
            // FIX: No usar .trigger('check_variations') aquí, ya que eso relanza woocommerce_update_variation_values y crea un infinite loop visual ("titila").
            if ( $select.val() === targetSlug ) {
                return;
            }
            
            // Autoseleccionar si la opción existe en el DOM
            if ( $select.find('option[value="' + targetSlug + '"]').length ) {
                $select.val(targetSlug).trigger('change');
            }
        };

        function hideRowAndTable($select, $form) {
            var $row = $select.closest('tr');
            $row.hide();
            
            // Contar si hay otras variaciones visibles (excluyendo la oculta)
            var visibleRows = 0;
            $form.find('table.variations tbody tr').each(function() {
                if ( $(this).css('display') !== 'none' ) {
                    visibleRows++;
                }
            });
            
            if ( visibleRows === 0 ) {
                $form.find('.variations').hide();
            }
        }

        // Eventos de sincronización con WooCommerce
        $form.on('wc_variation_form woocommerce_update_variation_values', function() {
            setTimeout(enforceSelection, 50); // Pequeño buffer para que WC termine de manipular el DOM
        });

        // Resetear intenciones si el form se vacía por completo
        $form.on('reset_data', function() {
            userChanged = false;
            setTimeout(enforceSelection, 50);
        });

        // Ejecución inmediata
        enforceSelection();
    }

    $(document).ready(function() {
        initAutoSelectFormat();
        
        // Inicializar funcionalidades UI
        if (typeof initInfiniteScroll === 'function') initInfiniteScroll();
        if (typeof initHybridCarousel === 'function') initHybridCarousel();
    });

    // ============================================
    // 2. INFINITE SCROLL LIGERO
    // ============================================
    function initInfiniteScroll() {
        // SELECTORES (Ajustados para GeneratePress + Woo)
        const selectors = {
            container: 'ul.products',        // Contenedor de la grilla
            item: 'li.product',              // Items individuales
            pagination: '.woocommerce-pagination', // Paginación nativa
            nextLink: '.woocommerce-pagination a.next' // Botón siguiente
        };

        const container = document.querySelector(selectors.container);
        const pagination = document.querySelector(selectors.pagination);
        
        // Si no hay contenedor o paginación, no hacemos nada
        if (!container || !pagination) return;

        let nextLink = pagination.querySelector('a.next');
        if (!nextLink) return;

        // 1. Ocultar paginación original (pero mantenerla en DOM por si acaso)
        pagination.style.display = 'none';

        // 2. Crear el "Centinela" (Elemento invisible que detecta cuando llegamos al fondo)
        const sentinelWrapper = document.createElement('div');
        sentinelWrapper.className = 'mu-scroll-sentinel-wrapper';
        
        const sentinel = document.createElement('div');
        sentinel.className = 'mu-scroll-sentinel';
        sentinel.innerHTML = '<div class="mu-spinner"></div>';
        
        const loadMoreBtn = document.createElement('button');
        loadMoreBtn.className = 'mu-load-more-btn';
        loadMoreBtn.innerText = 'Cargar más resultados';
        loadMoreBtn.style.display = 'none'; // Inicialmente oculto

        sentinelWrapper.appendChild(sentinel);
        sentinelWrapper.appendChild(loadMoreBtn);
        container.parentNode.insertBefore(sentinelWrapper, container.nextSibling);

        let isLoading = false;
        let autoLoadCount = 0; // Contador de cargas automáticas

        // 3. Configurar IntersectionObserver (API nativa eficiente)
        const observer = new IntersectionObserver((entries) => {
            // Solo carga automáticamente la primera vez (autoLoadCount === 0)
            if (entries[0].isIntersecting && !isLoading && nextLink && autoLoadCount === 0) {
                loadNextPage();
            } 
            // Si entra en rango, ya cargó la primera vez y no está cargando: mostramos botón
            else if (entries[0].isIntersecting && !isLoading && nextLink && autoLoadCount > 0) {
                 loadMoreBtn.style.display = 'block';
                 sentinel.style.display = 'none';
            }
        }, {
            rootMargin: '200px' // Cargar 200px antes de llegar al final
        });

        observer.observe(sentinelWrapper);
        
        // Evento click para el botón "Cargar más"
        loadMoreBtn.addEventListener('click', (e) => {
            e.preventDefault();
            loadMoreBtn.style.display = 'none';
            sentinel.style.display = 'flex'; // Volver a mostrar el spinner
            if (!isLoading && nextLink) {
                loadNextPage();
            }
        });

        // 4. Función de Carga
        async function loadNextPage() {
            isLoading = true;
            sentinelWrapper.classList.add('loading');
            loadMoreBtn.style.display = 'none';
            sentinel.style.display = 'flex';
            
            const url = nextLink.href;

            try {
                // Fetch de la siguiente página (Aprovecha LiteSpeed Cache)
                const response = await fetch(url);
                const text = await response.text();
                
                // Parsear HTML
                const parser = new DOMParser();
                const doc = parser.parseFromString(text, 'text/html');
                
                // Extraer productos
                const newProducts = doc.querySelectorAll(selectors.container + ' ' + selectors.item);
                
                if (newProducts.length > 0) {
                    // Añadir productos al contenedor actual
                    newProducts.forEach(product => {
                        // Lógica de Imagen (Placeholder + FadeIn)
                        const img = product.querySelector('img');
                        if (img) {
                            // Preparamos la imagen invisible
                            img.style.opacity = '0';
                            img.style.transition = 'opacity 0.6s ease-in-out';
                            
                            // Añadimos clase de carga al contenedor (para el placeholder dots)
                            // GeneratePress suele poner la imagen dentro de un <a> o un wrapper
                            const wrapper = img.parentElement; 
                            wrapper.classList.add('mu-img-wrapper-loading');

                            // Función para revelar imagen
                            const revealImg = () => {
                                img.style.opacity = '1';
                                wrapper.classList.remove('mu-img-wrapper-loading');
                            };

                            if (img.complete) {
                                revealImg();
                            } else {
                                img.addEventListener('load', revealImg);
                                img.addEventListener('error', revealImg); // Fallback si falla
                            }
                        }

                        container.appendChild(product);
                    });

                    // Actualizar enlace "Siguiente"
                    const newNextLink = doc.querySelector(selectors.nextLink);
                    if (newNextLink) {
                        nextLink = newNextLink;
                        // Actualizar URL del navegador (SEO friendly)
                        window.history.replaceState(null, '', url);
                        
                        // Incrementamos el contador de cargas automáticas exitosas
                        autoLoadCount++;
                        
                        // Ocultar spinner. El Observer volverá a disparar el botón si está en pantalla
                        sentinelWrapper.classList.remove('loading');
                        
                        // Si ya está en pantalla, mostramos el botón directo (para evitar delay)
                        const rect = sentinelWrapper.getBoundingClientRect();
                        if (rect.top <= (window.innerHeight || document.documentElement.clientHeight) + 200) {
                            loadMoreBtn.style.display = 'block';
                            sentinel.style.display = 'none';
                        }

                    } else {
                        // Fin de catálogo
                        nextLink = null;
                        sentinelWrapper.remove();
                        observer.disconnect();
                    }
                    
                    // Disparar evento para que otros plugins sepan que hay nuevos productos
                    $(document.body).trigger('post-load');
                }

            } catch (error) {
                console.error('Error Infinite Scroll:', error);
                // Si falla, mostramos la paginación normal como fallback
                pagination.style.display = 'block';
                sentinelWrapper.remove();
            }

            isLoading = false;
            sentinelWrapper.classList.remove('loading');
        }
    }

    // ============================================
    // 3. CARRUSEL HÍBRIDO (Drag/Grid)
    // ============================================
    function initHybridCarousel() {
        const carousels = document.querySelectorAll('.mu-carousel-wrapper');
        if (!carousels.length) return;

        carousels.forEach(wrapper => {
            const track = wrapper.querySelector('.mu-carousel-track');
            const prevBtn = wrapper.querySelector('.prev');
            const nextBtn = wrapper.querySelector('.next');
            
            if (!track) return;

            // 1. FLECHAS DE NAVEGACIÓN
            const moveTrack = (direction) => {
                // Buscamos el primer item para saber su ancho real actual
                const item = track.querySelector('.mu-carousel-item');
                if(!item) return;
                
                const itemWidth = item.offsetWidth;
                const gap = 20; // Valor aproximado del CSS
                const scrollAmount = (direction === 'left') 
                    ? -(itemWidth + gap) 
                    : (itemWidth + gap);
                
                track.scrollBy({ left: scrollAmount, behavior: 'smooth' });
            };

            if(prevBtn) prevBtn.addEventListener('click', (e) => {
                e.preventDefault(); moveTrack('left');
            });

            if(nextBtn) nextBtn.addEventListener('click', (e) => {
                e.preventDefault(); moveTrack('right');
            });

            // 2. ARRASTRE (DRAG & DROP)
            let isDown = false;
            let startX;
            let scrollLeft;
            let isDragging = false; 

            track.addEventListener('mousedown', (e) => {
                // Protección: Solo drag si es < 968px (Modo Carrusel)
                if (window.innerWidth > 968) return;

                isDown = true;
                isDragging = false;
                track.style.cursor = 'grabbing';
                startX = e.pageX - track.offsetLeft;
                scrollLeft = track.scrollLeft;
                track.style.scrollSnapType = 'none'; // Desactivar snap para fluidez
            });

            track.addEventListener('mouseleave', () => {
                isDown = false;
                track.style.cursor = 'default';
                // Reactivar snap si estamos en modo móvil
                if (window.innerWidth <= 968) track.style.scrollSnapType = 'x mandatory';
            });

            track.addEventListener('mouseup', () => {
                isDown = false;
                track.style.cursor = 'default';
                if (window.innerWidth <= 968) track.style.scrollSnapType = 'x mandatory';
                
                // Si arrastró, evitar click accidental en enlaces
                if (isDragging) {
                    const links = track.querySelectorAll('a');
                    links.forEach(l => l.style.pointerEvents = 'none');
                    setTimeout(() => links.forEach(l => l.style.pointerEvents = 'auto'), 100);
                }
            });

            track.addEventListener('mousemove', (e) => {
                if (!isDown) return;
                e.preventDefault();
                const x = e.pageX - track.offsetLeft;
                const walk = (x - startX) * 2; // Velocidad del arrastre
                
                if (Math.abs(walk) > 5) {
                    isDragging = true;
                    // Quitar efecto hover visual mientras se arrastra
                    const items = track.querySelectorAll('.can-hover');
                    items.forEach(c => c.classList.remove('can-hover'));
                }
                track.scrollLeft = scrollLeft - walk;
            });
            
            // Restaurar efectos hover al soltar
            track.addEventListener('mouseup', () => {
                 const items = track.querySelectorAll('.mu-carousel-item');
                 items.forEach(c => c.classList.add('can-hover'));
            });
        });
    }

})(jQuery);