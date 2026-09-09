/**
 * Muy Únicos - Funcionalidad de Tienda y Producto (Shop/Single Product)
 * * Incluye:
 * - Infinite Scroll Ligero (WooCommerce + GP Optimized)
 * - Carrusel Híbrido Global (Grilla Desktop / Drag Mobile)
 */

(function() {
    'use strict';

    if ( typeof document === 'undefined' ) {
        return;
    }

    // Ejecución Principal
    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

    function init() {
        initInfiniteScroll();
        initHybridCarousel();
    }

    // ============================================
    // 1. INFINITE SCROLL LIGERO — AUTO PROGRESS
    // ============================================
    function initInfiniteScroll() {
        // SELECTORES (Ajustados para GeneratePress + Woo)
        const selectors = {
            container: 'ul.products',
            item: 'li.product',
            pagination: '.woocommerce-pagination',
            nextLink: '.woocommerce-pagination a.next',
            prevLink: '.woocommerce-pagination a.prev'
        };

        const container = document.querySelector(selectors.container);
        const pagination = document.querySelector(selectors.pagination);
        
        if (!container || !pagination) return;

        let nextLink = pagination.querySelector('a.next');
        if (!nextLink) return;

        // Ocultar paginación original
        pagination.style.display = 'none';

        // Botón "Cargar resultados previos" (page/2+)
        const prevLink = pagination.querySelector('a.prev');
        if (prevLink) {
            const prevBtn = document.createElement('div');
            prevBtn.className = 'mu-prev-results-wrapper';
            prevBtn.innerHTML = '<button class="mu-load-prev-btn">Cargar resultados previos</button>';
            container.parentElement.insertBefore(prevBtn, container);
            
            prevBtn.querySelector('.mu-load-prev-btn').addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = prevLink.href;
            });
        }

        // Crear centinela + botón de progreso
        const sentinelWrapper = document.createElement('div');
        sentinelWrapper.className = 'mu-scroll-sentinel-wrapper';
        
        const sentinel = document.createElement('div');
        sentinel.className = 'mu-scroll-sentinel';
        sentinel.innerHTML = '<div class="mu-spinner"></div>';
        
        const loadMoreBtn = document.createElement('button');
        loadMoreBtn.className = 'mu-load-more-btn';
        loadMoreBtn.setAttribute('aria-label', 'Cargar más resultados');
        // Barra de progreso interna
        const progressFill = document.createElement('span');
        progressFill.className = 'mu-progress-fill';
        loadMoreBtn.appendChild(progressFill);

        sentinelWrapper.appendChild(sentinel);
        sentinelWrapper.appendChild(loadMoreBtn);
        container.parentNode.insertBefore(sentinelWrapper, container.nextSibling);

        let isLoading = false;
        let isArming = false;
        let progressTimer = null;

        // --- FUNCIÓN: Iniciar armado (progreso de 2s) ---
        function startArming() {
            if (isLoading || !nextLink || isArming) return;

            isArming = true;
            loadMoreBtn.classList.remove('is-loading');
            loadMoreBtn.classList.remove('is-ready');
            loadMoreBtn.classList.add('is-arming');
            // Resetear el fill antes de animar
            progressFill.style.transition = 'none';
            progressFill.style.width = '0%';
            // Forzar reflow para que el reset sea visible
            void loadMoreBtn.offsetWidth;
            // Iniciar transición de 2s
            progressFill.style.transition = 'width 2s linear';
            progressFill.style.width = '100%';

            // Timer: cuando se complete, cargar automáticamente
            progressTimer = setTimeout(function() {
                if (isArming) {
                    cancelArming();
                    loadNextPage();
                }
            }, 1000);
        }

        // --- FUNCIÓN: Cancelar armado ---
        function cancelArming() {
            if (progressTimer) {
                clearTimeout(progressTimer);
                progressTimer = null;
            }
            isArming = false;
            loadMoreBtn.classList.remove('is-arming');
            loadMoreBtn.classList.add('is-ready');
            progressFill.style.transition = 'none';
            progressFill.style.width = '0%';
        }

        // --- FUNCIÓN: Cargar siguiente página ---
        async function loadNextPage() {
            isLoading = true;
            loadMoreBtn.classList.remove('is-arming');
            loadMoreBtn.classList.remove('is-ready');
            loadMoreBtn.classList.add('is-loading');
            
            const url = nextLink.href;

            try {
                const response = await fetch(url);
                const text = await response.text();
                
                const parser = new DOMParser();
                const doc = parser.parseFromString(text, 'text/html');
                
                const newProducts = doc.querySelectorAll(selectors.container + ' ' + selectors.item);
                
                if (newProducts.length > 0) {
                    newProducts.forEach(function(product) {
                        const img = product.querySelector('img');
                        const wrapper = img ? img.parentElement : null;

                        if (img && wrapper) {
                            if (img.complete) {
                                img.style.opacity = '1';
                            } else {
                                img.style.opacity = '0';
                                img.style.transition = 'opacity 0.6s ease-in-out';
                                wrapper.classList.add('mu-img-wrapper-loading');
                                
                                const revealImg = function() {
                                    img.style.opacity = '1';
                                    wrapper.classList.remove('mu-img-wrapper-loading');
                                };
                                
                                img.addEventListener('load', revealImg);
                                img.addEventListener('error', revealImg);
                            }
                        }
                        container.appendChild(product);
                    });

                    const newNextLink = doc.querySelector(selectors.nextLink);
                    if (newNextLink) {
                        nextLink = newNextLink;
                        window.history.replaceState(null, '', url);
                        
                        sentinelWrapper.classList.remove('loading');
                        loadMoreBtn.classList.remove('is-loading');
                        loadMoreBtn.classList.add('is-ready');
                        
                        // Si el centinela sigue en pantalla, reiniciar armado automático
                        const rect = sentinelWrapper.getBoundingClientRect();
                        if (rect.top <= (window.innerHeight || document.documentElement.clientHeight) + 200) {
                            startArming();
                        }
                    } else {
                        // Fin de catálogo
                        nextLink = null;
                        sentinelWrapper.remove();
                        observer.disconnect();
                    }
                    
                    // Disparar evento post-load para WooCommerce y otros scripts
                    document.body.dispatchEvent(new CustomEvent('post-load'));
                }

            } catch (error) {
                console.error('Error Infinite Scroll:', error);
                pagination.style.display = 'block';
                sentinelWrapper.remove();
            }

            isLoading = false;
            sentinelWrapper.classList.remove('loading');
            loadMoreBtn.classList.remove('is-loading');
            // Si quedan más páginas, mantener botón listo; si no, eliminar wrapper
            if (nextLink) {
                loadMoreBtn.classList.add('is-ready');
            } else if (sentinelWrapper.parentNode) {
                sentinelWrapper.remove();
                observer.disconnect();
            }
        }

        // --- INTERSECTION OBSERVER ---
        const observer = new IntersectionObserver(function(entries) {
            if (entries[0].isIntersecting && !isLoading && nextLink) {
                startArming();
            } else if (!entries[0].isIntersecting && isArming) {
                // Si el usuario scrolleó hacia arriba y el centinela ya no está visible, cancelar
                cancelArming();
            }
        }, {
            rootMargin: '200px'
        });

        observer.observe(sentinelWrapper);

        // --- CLICK EN BOTÓN: carga inmediata ---
        loadMoreBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (isArming) {
                cancelArming();
                loadNextPage();
            } else if (!isLoading && nextLink) {
                loadNextPage();
            }
        });

        // Prevenir que mousedown/touchstart en el botón se propague al documento y cancele el armado
        loadMoreBtn.addEventListener('mousedown', function(e) {
            e.stopPropagation();
        });
        loadMoreBtn.addEventListener('touchstart', function(e) {
            e.stopPropagation();
        }, { passive: true });

        // --- CLICK/TOUCH FUERA DEL BOTÓN: cancelar armado ---
        function handleOutsideInteraction(e) {
            if (!isArming) return;
            // Verificar si el click fue dentro del botón
            if (loadMoreBtn.contains(e.target)) return;
            cancelArming();
        }

        document.addEventListener('mousedown', handleOutsideInteraction);
        document.addEventListener('touchstart', handleOutsideInteraction, { passive: true });
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

})();