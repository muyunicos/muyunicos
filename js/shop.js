/**
 * Muy Únicos - Funcionalidad de Tienda y Producto (Shop/Single Product)
 * 
 * Incluye:
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
    // Delegar evento en el document para soportar inicializaciones AJAX (ej: Quick Views)
    $(document).on('wc_variation_form', 'form.variations_form', function() {
        var $form = $(this);
        var $data = $('#mu-format-autoselect-data');
        
        if ( ! $data.length ) {
            return;
        }
        
        var targetSlug = $data.data('target-slug');
        var hideRow = $data.data('hide-row') === true;
        
        // Ejecutar con un pequeño delay para asegurar que WC renderizó los options
        setTimeout(function() {
            autoSelectFormatVariation($form, targetSlug, hideRow);
        }, 100);
    });

    // Ejecutar fallback en page load por si el evento ya pasó
    $(document).ready(function() {
        var $form = $('form.variations_form');
        if ( $form.length ) {
            var $data = $('#mu-format-autoselect-data');
            if ( $data.length ) {
                setTimeout(function() {
                    autoSelectFormatVariation($form, $data.data('target-slug'), $data.data('hide-row') === true);
                }, 150);
            }
        }
        
        // Inicializar funcionalidades UI
        initInfiniteScroll();
        initHybridCarousel();
    });
    
    function autoSelectFormatVariation($form, targetSlug, hideRow) {
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
        // $form.trigger('check_variations'); // Evitar loops si WC ya está chequeando
        
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
        let scrollTimeout;

        // 3. Configurar IntersectionObserver (API nativa eficiente)
        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && !isLoading && nextLink && loadMoreBtn.style.display === 'none') {
                loadNextPage();
            } else if (!entries[0].isIntersecting) {
                 // Si el usuario sale del rango de intersección, reiniciamos el estado del botón a visible 
                 // después de un tiempo prudencial si es que quedó pausado
                 clearTimeout(scrollTimeout);
                 scrollTimeout = setTimeout(() => {
                     if (nextLink && !isLoading) {
                         loadMoreBtn.style.display = 'block';
                         sentinel.style.display = 'none';
                     }
                 }, 500); // Muestra el botón si el usuario se alejó y vuelve a acercarse.
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
                        
                        // Si cargó exitosamente, forzamos la aparición del botón
                        // para que el usuario tenga que interactuar en la próxima carga
                        loadMoreBtn.style.display = 'block';
                        sentinel.style.display = 'none';
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