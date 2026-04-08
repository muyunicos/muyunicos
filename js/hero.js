/**
 * Muy Únicos — Hero Slider
 * Carga: is_front_page() + has_shortcode('mu_hero_section')
 * Responsabilidades: autoplay, dots, swipe móvil, navegación circular.
 *
 * @package GeneratePress_Child
 */
(function () {
    'use strict';

    function initHeroSlider() {
        const wrapper  = document.querySelector('.mu-hero-promo-wrapper');
        const slider   = document.getElementById('muHeroSlider');
        if (!wrapper || !slider) return;

        const slides   = slider.querySelectorAll('.mu-hero-promo-slide');
        const dots     = wrapper.querySelectorAll('.mu-hero-promo-dot');
        const total    = slides.length;
        if (total < 2) return; // Un solo slide: sin lógica

        let current = 0;
        let timer;

        // Ir a un slide específico
        function goTo(index) {
            let next = index;
            if (next >= total) next = 0;
            if (next < 0)     next = total - 1;
            if (next === current) return;

            slides[current].classList.remove('active');
            if (dots[current]) dots[current].classList.remove('active');

            current = next;

            slides[current].classList.add('active');
            if (dots[current]) dots[current].classList.add('active');

            resetTimer();
        }

        function next() { goTo(current + 1); }
        function prev() { goTo(current - 1); }

        function resetTimer() {
            clearInterval(timer);
            timer = setInterval(next, 7000);
        }

        // Dots — data-index en lugar de onclick inline
        dots.forEach(function (dot, i) {
            dot.addEventListener('click', function () { goTo(i); });
        });

        // Swipe (touch)
        let touchStartX = 0;
        slider.addEventListener('touchstart', function (e) {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });

        slider.addEventListener('touchend', function (e) {
            const delta = e.changedTouches[0].screenX - touchStartX;
            if (delta < -50) next();
            if (delta >  50) prev();
        }, { passive: true });

        // Pausa al perder foco (accesibilidad)
        wrapper.addEventListener('mouseenter', function () { clearInterval(timer); });
        wrapper.addEventListener('mouseleave', resetTimer);

        // Arranque
        resetTimer();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initHeroSlider);
    } else {
        initHeroSlider();
    }

})();
