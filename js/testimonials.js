/**
 * Muy Únicos — Testimonios / Reseñas Google
 * Carga: shortcode mu_testimonios_section
 * Datos PHP→JS: muTestimonials (wp_localize_script)
 *   reviews  → listado de reseñas (option mu_reviews_master_db)
 *   lang     → idioma de la URL actual ('es' | 'pt' | 'en')
 *   i18n     → cadenas fijas de la sección ya resueltas por idioma
 *   isAdmin  → muestra los controles de edición
 *
 * Traducción por reseña: review.text_pt / review.text_en. Vacío = sin
 * traducción, se muestra el comentario en español (text).
 *
 * @package GeneratePress_Child
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        var data = (typeof muTestimonials !== 'undefined') ? muTestimonials : {};
        var allReviews = data.reviews || [];
        var container  = document.getElementById('mu-reviews-container');
        var i18n       = data.i18n || {};
        var lang       = data.lang || 'es';
        
        // Variables globales para todo el scope del módulo
        var isAdmin = typeof muTestimonials !== 'undefined' && muTestimonials.isAdmin;

        if (!container) return;

        /** Cadena de la sección en el idioma actual (fallback: español). */
        function t(key, fallback) {
            return i18n[key] || fallback;
        }

        /**
         * Comentario de la reseña en el idioma actual: se usa la traducción solo
         * si existe; si está vacía (o el idioma es español) va el original.
         */
        function reviewText(review) {
            if (lang !== 'es') {
                var translated = review['text_' + lang];
                if (translated) return translated;
            }
            return review.text || '';
        }

        /* --- Sistema de memoria para evitar duplicados --- */
        var shownReviews = []; // IDs de reseñas ya mostradas

        // Recorte por palabras del comentario: red de seguridad para textos
        // desmesurados. El recorte que se ve lo hace el CSS (-webkit-line-clamp),
        // que es lo que mantiene el alto parejo entre las tarjetas.
        var WORD_LIMIT = 45;

        /* --- Reloj del botón de rotación --------------------------------------
           La duración NO la lleva el JS: vive en el CSS (--mu-ring-duration del
           botón), el navegador anima el anillo y acá solo se escucha el final de
           cada vuelta. Pausar el anillo (clase .is-paused) pausa también la
           carga: el reloj y la carga no se pueden desincronizar.
           -------------------------------------------------------------------- */
        var rotateBtn    = document.getElementById('mu-btn-rotate');
        var ringArc      = rotateBtn ? rotateBtn.querySelector('.mu-refresh-ring-arc') : null;
        var rotatePaused = false;
        var rotateTimer  = null;   // Solo en el modo sin animación (reduced motion).
        var lastManualRotation = 0; // Para descartar una vuelta rezagada tras el clic.
        var RING_MS_FALLBACK = 10000;
        var reduceMotion = !!(window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

        function fisherYatesShuffle(arr) {
            var a = arr.slice();
            for (var i = a.length - 1; i > 0; i--) {
                var j = Math.floor(Math.random() * (i + 1));
                var tmp = a[i];
                a[i] = a[j];
                a[j] = tmp;
            }
            return a;
        }

        function getRandomReviews(count) {
            if (!allReviews.length) return [];
            
            // Filtrar reseñas ocultas y marcadas como posible duplicado (la
            // importación marca la copia vieja). El mantenedor ve todo, para poder
            // resolverlas desde el aviso de la sección.
            var visibleReviews = isAdmin 
                ? allReviews 
                : allReviews.filter(function(review) {
                    return !review.hidden && !review.posible_duplicado;
                });
            
            // Filtrar reseñas no mostradas
            var unseenReviews = visibleReviews.filter(function(review, index) {
                var originalIndex = allReviews.indexOf(review);
                return !shownReviews.includes(originalIndex);
            });
            
            // Si hay suficientes reseñas no vistas, usar esas
            if (unseenReviews.length >= count) {
                var shuffled = fisherYatesShuffle(unseenReviews);
                var selected = shuffled.slice(0, count);
                
                // Marcar como vistas
                selected.forEach(function(review) {
                    var index = allReviews.indexOf(review);
                    if (index !== -1 && !shownReviews.includes(index)) {
                        shownReviews.push(index);
                    }
                });
                
                return selected;
            }
            
            // Si no hay suficientes nuevas, usar vistas también y resetear memoria
            var shuffled = fisherYatesShuffle(visibleReviews);
            shownReviews = []; // Resetear memoria
            
            var selected = shuffled.slice(0, count);
            selected.forEach(function(review) {
                var index = allReviews.indexOf(review);
                if (index !== -1) {
                    shownReviews.push(index);
                }
            });
            
            return selected;
        }

        function truncate(str, maxWords) {
            var words = str.split(' ');
            if (words.length <= maxWords) return str;
            return words.slice(0, maxWords).join(' ') + '…';
        }

        function getStars(rating) {
            return '★'.repeat(Math.floor(rating));
        }

        /**
         * Tiempo relativo en el idioma de la página.
         *
         * Intl.RelativeTimeFormat cubre es/pt/en sin duplicar cadenas; si el
         * navegador no lo soporta se cae a la descripción relativa que ya trae
         * Google en el dato (español).
         */
        function getRelativeTime(review) {
            var timestamp = review.time;
            if (!timestamp) return '';

            var seconds = Math.round((Date.now() - new Date(timestamp * 1000).getTime()) / 1000);
            if (seconds < 60) return t('justNow', 'hace un momento');

            var locale = 'es' === lang ? 'es-AR' : ('pt' === lang ? 'pt-BR' : 'en-US');
            var units  = [
                ['year', 31536000],
                ['month', 2629800],
                ['week', 604800],
                ['day', 86400],
                ['hour', 3600],
                ['minute', 60]
            ];

            try {
                var formatter = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
                for (var i = 0; i < units.length; i++) {
                    var amount = Math.floor(seconds / units[i][1]);
                    if (amount >= 1) return formatter.format(-amount, units[i][0]);
                }
            } catch (e) {
                // Sin Intl: queda la descripción relativa de Google.
            }

            return review.relative_time_description || '';
        }

        /** Duración del ciclo en ms: se lee del CSS (única fuente de verdad). */
        function ringDurationMs() {
            if (!rotateBtn) return RING_MS_FALLBACK;

            var raw = window.getComputedStyle(rotateBtn).getPropertyValue('--mu-ring-duration').trim();
            var match = /^([\d.]+)(ms|s)$/.exec(raw);
            if (!match) return RING_MS_FALLBACK;

            var value = parseFloat(match[1]);
            return 'ms' === match[2] ? value : value * 1000;
        }

        /** Vuelve el anillo a cero: la vuelta empieza de nuevo (clic manual, o al
            volver de una pestaña en segundo plano). */
        function restartRing() {
            if (!ringArc) return;

            // Web Animations: pone el contador exactamente en cero, también con el
            // anillo en pausa (cursor encima). Es el camino de los navegadores
            // actuales y el que garantiza que el reloj vuelva a empezar.
            if (typeof ringArc.getAnimations === 'function') {
                var animations = ringArc.getAnimations();
                if (animations && animations.length) {
                    for (var i = 0; i < animations.length; i++) {
                        animations[i].currentTime = 0;
                    }
                    return;
                }
            }

            // Respaldo (navegadores sin getAnimations): quitar la animación, forzar
            // reflow y dejar que el CSS la vuelva a crear desde cero.
            ringArc.style.animation = 'none';
            void ringArc.offsetWidth;
            ringArc.style.animation = '';
        }

        /** Carga otras opiniones: gira el icono, atenúa la grilla y repinta. */
        function rotateReviews() {
            if (rotateBtn) { rotateBtn.classList.add('is-spinning'); }
            container.classList.add('is-loading');

            setTimeout(function () {
                renderReviews();
                container.classList.remove('is-loading');
                if (rotateBtn) { rotateBtn.classList.remove('is-spinning'); }
            }, 200);
        }

        /* --- Pausa / reanudación ---------------------------------------------
           El reloj se detiene (y no se cargan reseñas) mientras el cursor está
           sobre el botón o sobre alguna reseña, cuando la sección no se ve y
           cuando la pestaña queda en segundo plano. Al reanudar sigue donde iba.
           -------------------------------------------------------------------- */

        /** Sin anillo animado (reduced motion o marcado viejo) rota un temporizador. */
        function needsFallbackTimer() {
            return reduceMotion || !ringArc;
        }

        function startFallbackTimer() {
            if (!needsFallbackTimer() || rotateTimer || rotatePaused) return;
            rotateTimer = setInterval(rotateReviews, ringDurationMs());
        }

        function stopFallbackTimer() {
            if (!rotateTimer) return;
            clearInterval(rotateTimer);
            rotateTimer = null;
        }

        function pauseRotation() {
            if (rotatePaused) return;
            rotatePaused = true;
            if (rotateBtn) { rotateBtn.classList.add('is-paused'); }
            stopFallbackTimer();
        }

        function resumeRotation() {
            if (!rotatePaused) return;
            rotatePaused = false;
            if (rotateBtn) { rotateBtn.classList.remove('is-paused'); }
            startFallbackTimer();
        }

        function renderReviews() {
            if (!allReviews.length) {
                container.innerHTML = '<p style="text-align:center;width:100%;color:#666;">' + t('loading', 'Cargando opiniones…') + '</p>';
                if (rotateBtn) { rotateBtn.style.display = 'none'; } // sin reseñas no hay nada que rotar
                return;
            }

            if (rotateBtn) { rotateBtn.style.display = ''; }

            var selection = getRandomReviews(3);
            var html = '';
            var defaultAvatar = 'https://lh3.googleusercontent.com/a/default-user';

            for (var i = 0; i < selection.length; i++) {
                var review   = selection[i];
                var photoUrl = review.profile_photo_url || defaultAvatar;
                var delay    = i * 100;
                var reviewIndex = allReviews.indexOf(review);

                // Miniatura de la compra: va a la izquierda del comentario
                // (.mu-review-body, en css/testimonials.css).
                var purchasePhotoHtml = '';
                if (review.purchase_photo_url) {
                    purchasePhotoHtml = '<div class="mu-purchase-photo"><img src="' + review.purchase_photo_url + '" alt="' + t('purchasePhotoAlt', 'Foto de la compra') + '" loading="lazy"></div>';
                }

                // El índice viaja en data-* (no concatenado en el onclick) y lo
                // resuelve el listener delegado que se registra más abajo.
                var editBtn = isAdmin 
                    ? '<button type="button" class="mu-review-edit-btn" data-review-index="' + reviewIndex + '">Editar</button>'
                    : '';

                // Tiempo relativo en el idioma de la página (fallback: Google).
                var timeDisplay = getRelativeTime(review);

                // Etiqueta solo para el mantenedor: esta reseña quedó marcada al
                // importar otra con el mismo nombre (los visitantes no la ven).
                var dupBadge = (isAdmin && review.posible_duplicado)
                    ? '<span class="mu-review-dup-badge" title="Se importó otra reseña con el mismo nombre">posible duplicado</span>'
                    : '';

                html +=
                    '<div class="mu-review-wrapper mu-review-fade" style="animation-delay:' + delay + 'ms">' +
                        '<a href="' + (review.author_url || review.profile_url || '#') + '" target="_blank" rel="noopener noreferrer" class="mu-review-card" title="Ver en Google">' +
                            '<div class="mu-review-meta">' +
                                '<img src="' + photoUrl + '" alt="Foto de ' + review.author_name + '" width="45" height="45" loading="lazy" onerror="this.src=\'' + defaultAvatar + '\';">' +
                                '<div>' +
                                    '<div class="mu-author-name">' + review.author_name + editBtn + dupBadge + '</div>' +
                                    '<div class="mu-stars" aria-label="' + review.rating + ' estrellas">' + getStars(review.rating) + '</div>' +
                                '</div>' +
                            '</div>' +
                            '<div class="mu-review-body">' +
                                purchasePhotoHtml +
                                '<p class="mu-review-text">“' + truncate(reviewText(review), WORD_LIMIT) + '”</p>' +
                            '</div>' +
                            '<small class="mu-review-time">' + timeDisplay + '</small>' +
                        '</a>' +
                    '</div>';
            }

            // El botón de rotación no forma parte de este HTML: vive en el marcado
            // de la página (.mu-reviews-shell) y lo maneja el reloj, así el
            // repintado no lo destruye ni hay que re-enganchar el clic.
            container.innerHTML = html;
        }

        // El reloj se detiene cuando la sección no está a la vista (y sigue al volver)
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    resumeRotation();
                } else {
                    pauseRotation();
                }
            });
        });
        
        if (container) {
            observer.observe(container);
        }

        // Los botones de cada tarjeta se re-crean en cada render: el clic se
        // delega una sola vez sobre el contenedor (evita listeners duplicados).
        container.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('.mu-review-edit-btn') : null;
            if (!btn) return;

            e.preventDefault();
            e.stopPropagation();

            var index = parseInt(btn.getAttribute('data-review-index'), 10);
            if (!isNaN(index) && typeof window.openReviewModal === 'function') {
                window.openReviewModal(index);
            }
        });

        /* --- Reloj: enganche único (el botón ya no se recrea en cada render) --- */
        function initRotationClock() {
            if (!rotateBtn) return;

            restartRing();

            rotateBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                lastManualRotation = Date.now();
                rotateReviews();
                restartRing();   // con el clic, el contador vuelve a cero
            });

            // Con reduced motion el anillo no se dibuja (su animación no corre), así
            // que el ciclo lo lleva el temporizador y no se escucha el anillo: un
            // único origen por modo, sin vueltas contadas dos veces.
            if (ringArc && !reduceMotion) {
                // Cada vuelta completa del reloj carga otras reseñas.
                ringArc.addEventListener('animationiteration', function () {
                    if (rotatePaused) return;

                    // La vuelta que estaba por terminar cuando se hizo clic no debe
                    // sumar una carga extra (solo puede pasar si el reinicio del
                    // contador no prendió en este navegador).
                    if (Date.now() - lastManualRotation < 250) return;

                    rotateReviews();
                });
            }

            // Cursor sobre el botón o sobre alguna reseña: el reloj se detiene
            // (mouseenter/mouseleave no burbujean, así que no hay falsos positivos)
            rotateBtn.addEventListener('mouseenter', pauseRotation);
            rotateBtn.addEventListener('mouseleave', resumeRotation);
            container.addEventListener('mouseenter', pauseRotation);
            container.addEventListener('mouseleave', resumeRotation);

            // Pestaña en segundo plano: se pausa; al volver el reloj arranca de
            // cero (red de seguridad por si el navegador congeló la animación).
            document.addEventListener('visibilitychange', function () {
                if (document.hidden) {
                    pauseRotation();
                } else {
                    restartRing();
                    resumeRotation();
                }
            });

            startFallbackTimer();
        }

        initRotationClock();
        renderReviews();
    });
    
    // Funciones globales para el modal de reseñas
    window.openReviewModal = function(index) {
        if (typeof window.muReviewModal !== 'undefined') {
            window.muReviewModal.open(index);
        }
    };
    
    window.closeReviewModal = function() {
        if (typeof window.muReviewModal !== 'undefined') {
            window.muReviewModal.close();
        }
    };
}());
