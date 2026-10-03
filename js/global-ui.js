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
     * WhatsApp Button - Arrastrable por el usuario
     *
     * POR QUÉ EXISTE (decisión del mantenedor, 2026-10-02):
     * El botón tapaba la barra inferior del navegador y molestaba. Se
     * probó agregarle una etiqueta de texto y ocupaba demasiado ancho, así
     * que quedó solo el ícono y el usuario puede moverlo a donde quiera,
     * como las burbujas de chat de Facebook.
     *
     * PERSISTENCIA (decisión explícita del mantenedor):
     * Se guarda en localStorage, en el navegador del usuario. NUNCA en el
     * servidor: no hay base de datos, ni AJAX, ni dato personal. Es una
     * preferencia de interfaz, igual que `wplng_switcher_closed` que ya se
     * guarda en este mismo archivo.
     *
     * MODELO DE DATOS: dos números, no píxeles.
     *   { side: 0|1, vertical: 0-100 }
     *     side     0 = izquierda, 1 = derecha
     *     vertical 0 = abajo del todo (posición original), 100 = arriba
     * Por ser porcentajes, la posición se recalcula sola al rotar el
     * teléfono o cambiar de ancho de pantalla. Guardar píxeles dejaría el
     * botón fuera de pantalla en cuanto cambiara el viewport.
     *
     * ZONA SEGURA: el botón nunca puede salir de los márgenes definidos en
     * MU_MARGIN, así que no queda medio cortado ni encima del carrito.
     */
    function initDraggableWhatsapp() {
        var btn = document.querySelector('.boton-whatsapp');
        if (!btn) return;

        var STORE_KEY = 'mu_whatsapp_pos';
        var DRAG_THRESHOLD = 8;   // px: por debajo de esto es un toque, no arrastre
        var MU_MARGIN = { top: 82, right: 25, bottom: 82, left: 25 };

        /** Lee la posición guardada. Defaults: abajo a la derecha. */
        function readPosition() {
            var def = { side: 1, vertical: 0 };
            try {
                var raw = window.localStorage.getItem(STORE_KEY);
                if (!raw) return def;
                var parsed = JSON.parse(raw);
                if (!parsed || typeof parsed !== 'object') return def;
                return {
                    side: parsed.side === 0 ? 0 : 1,
                    vertical: Math.max(0, Math.min(100, Number(parsed.vertical) || 0))
                };
            } catch (e) {
                // localStorage puede estar bloqueado (modo privado).
                // Es una mejora opcional: si falla, el botón funciona igual.
                return def;
            }
        }

        function savePosition(side, vertical) {
            try {
                window.localStorage.setItem(STORE_KEY, JSON.stringify({
                    side: side,
                    vertical: Math.round(vertical)
                }));
            } catch (e) {
                // Sin persistencia no se rompe nada: sigue siendo arrastrable.
            }
        }

        /** Convierte el estado (side, vertical) a píxeles y lo aplica. */
        function applyPosition(pos) {
            var vw = window.innerWidth;
            var vh = window.innerHeight;
            var w = btn.offsetWidth || 50;
            var h = btn.offsetHeight || 50;

            // Rango vertical utilizable, ya descontando la zona segura.
            var usableTop = MU_MARGIN.top;
            var usableBottom = vh - MU_MARGIN.bottom - h;
            var range = Math.max(0, usableBottom - usableTop);

            // CONVERSIÓN DEL MODELO (no invertir sin cambiar endDrag):
            //   vertical 0   -> pegado a la zona segura INFERIOR (abajo)
            //   vertical 100 -> pegado a la zona segura SUPERIOR (arriba)
            // Se resta desde usableBottom porque `top` se mide desde arriba:
            // con vertical=0 el botón queda abajo del todo, que es la
            // posición original y el valor por defecto.
            var top = usableBottom - (range * (pos.vertical / 100));
            var left = pos.side === 0 ? MU_MARGIN.left : vw - MU_MARGIN.right - w;

            btn.style.left = Math.round(left) + 'px';
            btn.style.top = Math.round(top) + 'px';
            btn.style.right = 'auto';
            btn.style.bottom = 'auto';
        }

        // Restaurar la posición guardada
        applyPosition(readPosition());

        // --- Estado del gesto ---
        var isDragging = false;
        var didMove = false;
        var startX = 0, startY = 0;
        var startLeft = 0, startTop = 0;

        btn.addEventListener('pointerdown', function(e) {
            // Solo botón principal, para no secuestrar el clic contextual.
            if (e.button !== undefined && e.button !== 0) return;

            isDragging = true;
            didMove = false;
            startX = e.clientX;
            startY = e.clientY;
            startLeft = btn.offsetLeft;
            startTop = btn.offsetTop;

            // Capturar el puntero garantiza que sigan llegando eventos
            // aunque el dedo se salga del botón a mitad del arrastre.
            if (btn.setPointerCapture && e.pointerId !== undefined) {
                try { btn.setPointerCapture(e.pointerId); } catch (err) { /* no crítico */ }
            }
        });

        btn.addEventListener('pointermove', function(e) {
            if (!isDragging) return;

            var dx = e.clientX - startX;
            var dy = e.clientY - startY;

            // Umbral: hasta acá es un toque tembloroso, no intención de
            // mover. Recién después se considera arrastre.
            if (!didMove) {
                if (Math.abs(dx) < DRAG_THRESHOLD && Math.abs(dy) < DRAG_THRESHOLD) {
                    return;
                }
                didMove = true;
                btn.classList.add('is-dragging');
            }

            e.preventDefault();

            // Traducir en vez de recalcular: la caja sigue al dedo 1:1 y
            // solo al soltar se convierte a porcentaje y se acota a la zona
            // segura. Así el control nunca puede quedar fuera de pantalla
            // durante el movimiento.
            btn.style.left = Math.round(startLeft + dx) + 'px';
            btn.style.top = Math.round(startTop + dy) + 'px';
        });

        function endDrag(e) {
            if (!isDragging) return;
            isDragging = false;

            if (!didMove) {
                return;  // fue un toque: el click sigue su curso normal
            }

            btn.classList.remove('is-dragging');

            var vw = window.innerWidth;
            var vh = window.innerHeight;
            var w = btn.offsetWidth || 50;
            var h = btn.offsetHeight || 50;

            // Leer dónde quedó y traducirla a (side, vertical).
            var finalLeft = parseFloat(btn.style.left) || 0;
            var finalTop = parseFloat(btn.style.top) || 0;

            // Lado: según de qué mitad quedó más cerca el centro.
            var centerX = finalLeft + w / 2;
            var side = centerX < vw / 2 ? 0 : 1;

            // Vertical: PÍXELES -> PORCENTAJE. Inverso de applyPosition.
            //   finalTop = usableBottom -> vertical 0   (abajo)
            //   finalTop = usableTop    -> vertical 100 (arriba)
            // Si esta fórmula y la de applyPosition no comparten el mismo
            // criterio, el botón aparece en un lugar al moverlo y en otro
            // al recargar la página.
            var usableTop = MU_MARGIN.top;
            var usableBottom = vh - MU_MARGIN.bottom - h;
            var range = Math.max(1, usableBottom - usableTop);
            var vertical = ((usableBottom - finalTop) / range) * 100;
            vertical = Math.max(0, Math.min(100, vertical));

            savePosition(side, vertical);
            applyPosition({ side: side, vertical: vertical });

            // Si el gesto terminó en arrastre, suprimir el click para no
            // abrir WhatsApp al solo querer mover el botón.
            if (e && e.preventDefault) e.preventDefault();
        }

        btn.addEventListener('pointerup', endDrag);
        btn.addEventListener('pointercancel', endDrag);

        // El clic en móvil dispara click después de pointerup. Si hubo
        // arrastre, hay que evitar que además abra WhatsApp.
        btn.addEventListener('click', function(e) {
            if (didMove) {
                e.preventDefault();
                didMove = false;
            }
        }, true);

        // Recalcular al cambiar el tamaño de la ventana o rotar el
        // dispositivo: el estado guardado es porcentual, así que se
        // reubica solo sin intervención del usuario.
        var resizeTimer = null;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                applyPosition(readPosition());
            }, 150);
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
        initDraggableWhatsapp();
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