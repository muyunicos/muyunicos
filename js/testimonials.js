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

        /* --- Icono SVG de recarga --- */
        var refreshIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.3"/></svg>';

        /* --- Sistema de memoria para evitar duplicados --- */
        var shownReviews = []; // IDs de reseñas ya mostradas
        var autoRefreshInterval = null;
        var AUTO_REFRESH_TIME = 120000; // 2 minutos (optimizado para reducir carga)

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
            
            // Filtrar reseñas ocultas (solo visible para admin)
            var visibleReviews = isAdmin 
                ? allReviews 
                : allReviews.filter(function(review) {
                    return !review.hidden;
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

        function startAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
            
            autoRefreshInterval = setInterval(function() {
                if (container && container.offsetParent !== null) {
                    container.style.opacity = '0.5';
                    setTimeout(function() {
                        container.style.opacity = '1';
                        renderReviews();
                    }, 400);
                }
            }, AUTO_REFRESH_TIME);
        }

        function stopAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
            }
        }

        function renderReviews() {
            if (!allReviews.length) {
                container.innerHTML = '<p style="text-align:center;width:100%;color:#666;">' + t('loading', 'Cargando opiniones…') + '</p>';
                return;
            }

            var selection = getRandomReviews(3);
            var html = '';
            var defaultAvatar = 'https://lh3.googleusercontent.com/a/default-user';

            for (var i = 0; i < selection.length; i++) {
                var review   = selection[i];
                var isLast   = (i === selection.length - 1);
                var photoUrl = review.profile_photo_url || defaultAvatar;
                var delay    = i * 100;
                var reviewIndex = allReviews.indexOf(review);

                var rotateLabel  = t('rotateTitle', 'Ver otras opiniones');
                var satelliteBtn = isLast
                    ? '<button class="mu-nav-btn refresh" id="mu-btn-rotate" type="button" title="' + rotateLabel + '" aria-label="' + rotateLabel + '">' + refreshIcon + '</button>'
                    : '';

                var purchasePhotoHtml = '';
                if (review.purchase_photo_url) {
                    purchasePhotoHtml = '<div class="mu-purchase-photo" style="margin-top:10px;"><img src="' + review.purchase_photo_url + '" alt="' + t('purchasePhotoAlt', 'Foto de la compra') + '" style="max-width:100%;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);"></div>';
                }

                // El índice viaja en data-* (no concatenado en el onclick) y lo
                // resuelve el listener delegado que se registra más abajo.
                var editBtn = isAdmin 
                    ? '<button type="button" class="mu-review-edit-btn" data-review-index="' + reviewIndex + '" style="font-size:11px;padding:6px 12px;margin-left:10px;background:#0073aa;color:white;border:none;border-radius:4px;cursor:pointer;box-shadow:0 2px 4px rgba(0,0,0,0.1);transition:background 0.2s;z-index:10;position:relative;">Editar</button>'
                    : '';

                // Tiempo relativo en el idioma de la página (fallback: Google).
                var timeDisplay = getRelativeTime(review);

                html +=
                    '<div class="mu-review-wrapper mu-review-fade" style="animation-delay:' + delay + 'ms">' +
                        '<a href="' + (review.author_url || review.profile_url || '#') + '" target="_blank" rel="noopener noreferrer" class="mu-review-card" title="Ver en Google">' +
                            '<div class="mu-review-meta">' +
                                '<img src="' + photoUrl + '" alt="Foto de ' + review.author_name + '" width="45" height="45" loading="lazy" onerror="this.src=\'' + defaultAvatar + '\';">' +
                                '<div>' +
                                    '<div class="mu-author-name">' + review.author_name + editBtn + '</div>' +
                                    '<div class="mu-stars" aria-label="' + review.rating + ' estrellas">' + getStars(review.rating) + '</div>' +
                                '</div>' +
                            '</div>' +
                            '<p class="mu-review-text">“' + truncate(reviewText(review), 20) + '”</p>' +
                            purchasePhotoHtml +
                            '<small style="margin-top:auto;color:#999;font-size:0.75rem;padding-top:10px;display:block;">' + timeDisplay + '</small>' +
                        '</a>' +
                        satelliteBtn +
                    '</div>';
            }

            container.innerHTML = html;

            var btn = document.getElementById('mu-btn-rotate');
            if (btn) {
                // Remover listeners anteriores para evitar duplicados
                var newBtn = btn.cloneNode(true);
                btn.parentNode.replaceChild(newBtn, btn);
                
                newBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    newBtn.style.transform = 'scale(0.95)';
                    newBtn.style.opacity = '0.8';
                    container.style.opacity = '0.5';

                    setTimeout(function () {
                        newBtn.style.transform = 'scale(1)';
                        newBtn.style.opacity = '1';
                        container.style.opacity = '1';
                        renderReviews();
                    }, 400);
                });
            }
            
            // Iniciar auto-refresh
            startAutoRefresh();
        }

        // Detener auto-refresh cuando el elemento no es visible
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    startAutoRefresh();
                } else {
                    stopAutoRefresh();
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
