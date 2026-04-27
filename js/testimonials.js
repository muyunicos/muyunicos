/**
 * Muy Únicos — Testimonios / Reseñas Google
 * Carga: shortcode mu_testimonios_section
 * Datos PHP→JS: muTestimonials.reviews (wp_localize_script)
 *
 * @package GeneratePress_Child
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        var data = (typeof muTestimonials !== 'undefined') ? muTestimonials : {};
        var allReviews = data.reviews || [];
        var container  = document.getElementById('mu-reviews-container');

        if (!container) return;

        /* --- Icono SVG de recarga (inline, no contiene datos sensibles) --- */
        var refreshIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.3"/></svg>';

        /**
         * Fisher-Yates shuffle — distribución uniforme real.
         */
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
            return fisherYatesShuffle(allReviews).slice(0, count);
        }

        function truncate(str, maxWords) {
            var words = str.split(' ');
            if (words.length <= maxWords) return str;
            return words.slice(0, maxWords).join(' ') + '…';
        }

        function getStars(rating) {
            return '★'.repeat(Math.floor(rating));
        }

        function renderReviews() {
            if (!allReviews.length) {
                container.innerHTML = '<p style="text-align:center;width:100%;color:#666;">Cargando opiniones…</p>';
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

                var satelliteBtn = isLast
                    ? '<button class="mu-refresh-satellite" id="mu-btn-rotate" type="button" title="Ver otras opiniones" aria-label="Ver otras opiniones">' + refreshIcon + '</button>'
                    : '';

                html +=
                    '<div class="mu-review-wrapper mu-review-fade" style="animation-delay:' + delay + 'ms">' +
                        '<a href="' + review.author_url + '" target="_blank" rel="noopener noreferrer" class="mu-review-card" title="Ver en Google">' +
                            '<div class="mu-review-meta">' +
                                '<img src="' + photoUrl + '" alt="Foto de ' + review.author_name + '" width="45" height="45" loading="lazy" onerror="this.src=\'' + defaultAvatar + '\';">' +
                                '<div>' +
                                    '<div class="mu-author-name">' + review.author_name + '</div>' +
                                    '<div class="mu-stars" aria-label="' + review.rating + ' estrellas">' + getStars(review.rating) + '</div>' +
                                '</div>' +
                            '</div>' +
                            '<p class="mu-review-text">“' + truncate(review.text, 20) + '”</p>' +
                            '<small style="margin-top:auto;color:#999;font-size:0.75rem;padding-top:10px;display:block;">' + review.relative_time_description + '</small>' +
                        '</a>' +
                        satelliteBtn +
                    '</div>';
            }

            container.innerHTML = html;

            /* Re-bindear botón dinámico */
            var btn = document.getElementById('mu-btn-rotate');
            if (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    /* Disparar animación CSS via clase, sin pisar transform inline */
                    btn.classList.add('is-spinning');
                    container.style.opacity = '0.5';

                    setTimeout(function () {
                        btn.classList.remove('is-spinning');
                        container.style.opacity = '1';
                        renderReviews();
                    }, 400);
                });
            }
        }

        renderReviews();
    });
}());
