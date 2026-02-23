/**
 * Muy Únicos - Funcionalidad de Tienda y Producto (Shop/Single Product)
 * * Incluye:
 * - Infinite Scroll Ligero (WooCommerce + GP Optimized)
 * - Carrusel Híbrido Global (Grilla Desktop / Drag Mobile)
 */

(function($) {
    'use strict';
    
    if ( 'undefined' === typeof $ || ! $.fn ) {
        return;
    }
    
    // Ejecución Principal
    $(document).ready(function() {
        initInfiniteScroll();
        initHybridCarousel();
    });

    // ============================================
    // 1. INFINITE SCROLL LIGERO
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
                        const img = product.querySelector('img');
                        const wrapper = img ? img.parentElement : null;

                        if (img && wrapper) {
                            // Lógica de Imagen optimizada (Evita flash si ya está en cache)
                            if (img.complete) {
                                img.style.opacity = '1';
                            } else {
                                // Solo si NO está completa, preparamos la animación
                                img.style.opacity = '0';
                                img.style.transition = 'opacity 0.6s ease-in-out';
                                wrapper.classList.add('mu-img-wrapper-loading');
                                
                                const revealImg = () => {
                                    img.style.opacity = '1';
                                    wrapper.classList.remove('mu-img-wrapper-loading');
                                };
                                
                                img.addEventListener('load', revealImg);
                                img.addEventListener('error', revealImg);
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
    // 2. CARRUSEL HÍBRIDO (Drag/Grid)
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
                
                // Leemos el gap real del CSS en lugar de hardcodearlo
                const trackStyle = window.getComputedStyle(track);
                const gap = parseFloat(trackStyle.gap) || 20; 

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
