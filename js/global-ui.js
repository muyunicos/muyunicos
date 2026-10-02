/**
 * MUYUNICOS - Global UI Scripts
 * Consolidated: Country Selector, WPLingua Toggle, Share Button, Hybrid Carousel
 * Version: 1.3.0
 */

(function() {
    'use strict';

    /**
     * Country Selector - Dropdown functionality
     *
     * SEPARACIÓN DE CONTEXTOS (research.md D-1):
     * El fallo reportado era que en el móvil un toque no abría la lista.
     * La causa: el mismo disparador tenía 'mouseenter' (que abre) y 'click'
     * (que alterna). En táctil un toque dispara el 'mouseenter' emulado
     * —que abre— y acto seguido el 'click', que lee "ya está visible" y
     * CIERRA. El mismo toque que abría lo cerraba.
     *
     * Se resuelve detectando el contexto de puntero con matchMedia y
     * registrando SOLO las escuchas que aplican a cada contexto:
     *   - Puntero fino (escritorio): hover abre y cierra. Un ratón real
     *     genera mouseenter y click como eventos distintos en el tiempo.
     *   - Sin puntero fino (móvil): solo click, que alterna. No se registra
     *     mouseenter, así que no puede depender de un evento emulado
     *     (cumple el caso de borde "sin eventos de puntero fino").
     *
     * ESTADO POR CLASE (D-2, contrato C-1.2 a C-1.4):
     * La visibilidad la gobierna la clase 'is-open' en el CONTENEDOR. Antes
     * el CSS la detectaba buscando texto dentro de un atributo style en
     * línea ([style*="display: block"]), un acoplamiento frágil que se
     * rompe con cualquier refactor. aria-expanded queda sincronizado.
     */
    function initCountrySelector() {
        var containers = document.querySelectorAll('.country-redirect-container');
        if(!containers.length) return;

        // Contexto de puntero del DISPOSITIVO (no de cada instancia).
        var finePointer = window.matchMedia
            ? window.matchMedia('(hover: hover) and (pointer: fine)')
            : { matches: true, addEventListener: function() {} };

        containers.forEach(function(container) {
            var trigger = container.querySelector('.country-selector-trigger');
            var dropdown = container.querySelector('.country-selector-dropdown');

            if (!trigger || !dropdown) return;

            var closeTimeout = null;

            function isOpen() {
                return container.classList.contains('is-open');
            }

            /** Abre este dropdown y cierra los demás. Contrato C-1.3. */
            function openDropdown() {
                if (closeTimeout) {
                    clearTimeout(closeTimeout);
                    closeTimeout = null;
                }

                document.querySelectorAll('.country-redirect-container').forEach(function(other) {
                    if (other !== container) {
                        other.classList.remove('is-open');
                        var otherTrigger = other.querySelector('.country-selector-trigger');
                        if (otherTrigger) otherTrigger.setAttribute('aria-expanded', 'false');
                    }
                });

                container.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');
            }

            /**
             * Cierre inmediato. Se separa de closeDropdown() porque el
             * retraso de gracia solo tiene sentido cuando hay un puntero
             * que viaja del disparador a la lista. En táctil, cerrar con
             * retraso deja el panel abierto 200ms tras el toque.
             */
            function hideDropdown() {
                if (closeTimeout) {
                    clearTimeout(closeTimeout);
                    closeTimeout = null;
                }
                container.classList.remove('is-open');
                trigger.setAttribute('aria-expanded', 'false');
            }

            /**
             * Cierre con 200ms de gracia. Solo escritorio (D-4): el tiempo
             * existe para que el cursor llegue del disparador a la lista
             * sin que se cierre en el camino.
             */
            function closeDropdown() {
                closeTimeout = setTimeout(function() {
                    closeTimeout = null;
                    container.classList.remove('is-open');
                    trigger.setAttribute('aria-expanded', 'false');
                }, 200);
            }
            
            function bindListeners() {
                if (finePointer.matches) {
                    /* --- ESCRITORIO: puntero fino --- */
                    trigger.addEventListener('mouseenter', function() {
                        openDropdown();
                    });

                    trigger.addEventListener('mouseleave', function() {
                        closeDropdown();
                    });

                    // Mantener abierto cuando el cursor está sobre el dropdown
                    dropdown.addEventListener('mouseenter', function() {
                        if (closeTimeout) {
                            clearTimeout(closeTimeout);
                            closeTimeout = null;
                        }
                    });

                    dropdown.addEventListener('mouseleave', function() {
                        closeDropdown();
                    });

                    // El clic también alterna en escritorio, para quien
                    // navega con teclado y llega al disparador con Enter,
                    // Espacio o clic.
                    trigger.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        if (isOpen()) {
                            hideDropdown();
                        } else {
                            openDropdown();
                        }
                    });
                } else {
                    /* --- MÓVIL: sin puntero fino ---
                       SOLO se registra 'click'. No se registra 'mouseenter',
                       que es justamente el evento que el navegador emula en
                       el primer toque y que hacía que el mismo toque
                       abriera y cerrara la lista a la vez. */
                    trigger.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        if (isOpen()) {
                            hideDropdown();
                        } else {
                            openDropdown();
                        }
                    });
                }
            }

            bindListeners();

            // Si el contexto de puntero cambia (p.ej. se conecta un ratón a
            // una tableta), se cierra el panel para no dejar un dropdown de
            // escritorio abierto sobre una pantalla táctil.
            if (finePointer.addEventListener) {
                finePointer.addEventListener('change', function() {
                    hideDropdown();
                });
            }

            // Cerrar al hacer click fuera
            document.addEventListener('click', function(e) {
                if (!trigger.contains(e.target) && !dropdown.contains(e.target)) {
                    hideDropdown();
                }
            });

            // Teclado: Enter y Espacio alternan, Escape cierra y devuelve
            // el foco al disparador. Contrato C-1.5. Se registra SIEMPRE,
            // en ambos contextos: no depende de que exista puntero fino.
            trigger.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
                    // En puntero fino el clic ya alterna; se evita el doble
                    // disparo que producirían clic + keydown.
                    if (finePointer.matches) {
                        e.preventDefault();
                        return;
                    }
                    e.preventDefault();
                    if (isOpen()) {
                        hideDropdown();
                    } else {
                        openDropdown();
                    }
                }
            });

            // Cerrar con tecla Escape
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && isOpen()) {
                    hideDropdown();
                    trigger.focus();
                }
            });

            // Cerrar ante cualquier cambio de ancho (rotación del
            // dispositivo). Sin esto, un panel abierto a 375px queda a
            // medio camino al pasar a horizontal o al cruzar el
            // breakpoint de escritorio.
            var resizeTimer = null;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(hideDropdown, 100);
            });
        });
    }

    /**
     * WPLingua Switcher - Toggle UI with close button and restore tab
     */
    function initWpLinguaSwitcherToggle() {
        const switcher = document.querySelector('.wplng-switcher.insert-bottom-center');
        if (!switcher) return;

        const switcherContent = switcher.querySelector('.switcher-content');
        const languages = switcherContent && switcherContent.querySelector('.wplng-languages');
        if (!switcherContent || !languages) return;

        // Remove previous buttons from navigation
        Array.from(languages.querySelectorAll('.wplng-close-btn')).forEach(btn => btn.remove());

        // Insert close button at the end of language list
        function createCloseButton() {
            if (languages.querySelector('.wplng-close-btn')) return;
            const closeBtn = document.createElement('button');
            closeBtn.className = 'wplng-close-btn';
            closeBtn.innerHTML = '✕';
            closeBtn.title = 'Cerrar selector de idioma';
            closeBtn.setAttribute('aria-label', 'Cerrar selector de idioma');
            closeBtn.tabIndex = 0;
            closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                closeSwitcher();
            });
            languages.appendChild(closeBtn);
        }

        // Restore tab/flap
        function createRestoreTab() {
            const existingTab = document.querySelector('.wplng-restore-tab');
            if (existingTab) existingTab.remove();
            const restoreTab = document.createElement('div');
            restoreTab.className = 'wplng-restore-tab';

            // World icon 🌐
            const globe = document.createElement('span');
            globe.textContent = '🌐';
            restoreTab.appendChild(globe);

            restoreTab.addEventListener('click', function(e){
                e.preventDefault();
                openSwitcher();
            });
            document.body.appendChild(restoreTab);
        }

        function closeSwitcher() {
            switcher.classList.add('wplng-collapsed');
            localStorage.setItem('wplng_switcher_closed', 'true');
            setTimeout(createRestoreTab, 150);
        }

        function openSwitcher() {
            switcher.classList.remove('wplng-collapsed');
            localStorage.setItem('wplng_switcher_closed', 'false');
            setTimeout(function(){
                createCloseButton();
            }, 1);
            const restoreTab = document.querySelector('.wplng-restore-tab');
            if (restoreTab) {
                restoreTab.style.opacity = '0';
                setTimeout(function(){ restoreTab.remove(); }, 260);
            }
        }

        // Persistence
        const isClosed = localStorage.getItem('wplng_switcher_closed') === 'true';
        if (isClosed) {
            switcher.classList.add('wplng-collapsed');
            createRestoreTab();
        } else {
            createCloseButton();
        }

        // Rebuild button if navigation/page reload/no render
        const observer = new MutationObserver(function() {
            if (!switcher.classList.contains('wplng-collapsed') && !languages.querySelector('.wplng-close-btn')) {
                createCloseButton();
            }
        });
        observer.observe(languages, {childList:true, subtree:false});
    }

    /**
     * Share button - Native Share API (mobile) + clipboard fallback (desktop)
     */
    function initShareButtons() {
        const shareBtns = document.querySelectorAll('.dcms-share-btn, .mu-share-btn');
        if (!shareBtns.length) return;

        shareBtns.forEach((btn) => {
            if (btn.dataset.muShareBound === '1') return;
            btn.dataset.muShareBound = '1';

            btn.addEventListener('click', async (e) => {
                e.preventDefault();

                const desc = document.querySelector('meta[name="description"]');
                const shareData = {
                    title: document.title,
                    text: (desc && desc.content) ? desc.content : document.title,
                    url: window.location.href
                };

                if (navigator.share) {
                    try {
                        await navigator.share(shareData);
                        return;
                    } catch (err) {
                        if (err && err.name !== 'AbortError') {
                            copyToClipboard(shareData.url, btn);
                        }
                        return;
                    }
                }

                copyToClipboard(shareData.url, btn);
            });
        });

        function copyToClipboard(text, btn) {
            if (!navigator.clipboard) {
                fallbackCopyTextToClipboard(text, btn);
                return;
            }

            navigator.clipboard.writeText(text).then(function() {
                showFeedback(btn);
            }, function() {
                fallbackCopyTextToClipboard(text, btn);
            });
        }

        function fallbackCopyTextToClipboard(text, btn) {
            var textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.setAttribute('readonly', '');
            textArea.style.position = 'fixed';
            textArea.style.left = '-9999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                showFeedback(btn);
            } catch (err) {
                // noop
            }
            document.body.removeChild(textArea);
        }

        function showFeedback(btn) {
            // Simplified logic: Just toggle the class. CSS handles the icon swap.
            if (btn.classList.contains('is-copied')) return;
            btn.classList.add('is-copied');

            // Tooltip logic
            const tooltip = document.createElement('span');
            tooltip.className = 'dcms-share-tooltip';
            tooltip.textContent = '¡Enlace copiado!';
            tooltip.setAttribute('role', 'status');
            tooltip.setAttribute('aria-live', 'polite');

            // Ensure parent positioning context for tooltip
            const parent = btn.parentNode;
            if (parent) {
                const style = window.getComputedStyle(parent);
                if (style.position === 'static') {
                    parent.style.position = 'relative';
                }
                parent.insertBefore(tooltip, btn.nextSibling);
            }

            setTimeout(() => {
                btn.classList.remove('is-copied');
                if (tooltip.parentNode) tooltip.parentNode.removeChild(tooltip);
            }, 2000);
        }
    }

    /**
     * Sistema Global de Carruseles Muy Únicos (Grilla Desktop / Carousel Móvil)
     */
    function initCarousels() {
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

    /**
     * Initialize all UI components
     */
    function init() {
        initCountrySelector();
        initWpLinguaSwitcherToggle();
        initShareButtons();
        initCarousels();
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Fallback timeout for WPLingua (might load late)
    setTimeout(initWpLinguaSwitcherToggle, 1000);

})();