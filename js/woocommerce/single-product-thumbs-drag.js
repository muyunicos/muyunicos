/**
 * Muy Únicos — Drag-to-Scroll en miniaturas de galería (ficha de producto)
 *
 * Condición: is_product()
 * Dependencias: ninguna (Vanilla JS)
 * Patrón: IIFE + 'use strict' + DOMContentLoaded
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var slider = document.querySelector('.woocommerce-product-gallery .flex-control-thumbs');
        if (!slider) return;

        var isDown = false;
        var startX;
        var scrollLeft;
        var isDragging = false;

        // 1. Iniciar arrastre
        slider.addEventListener('mousedown', function (e) {
            if (slider.scrollWidth <= slider.clientWidth) return;

            isDown = true;
            isDragging = false;
            slider.style.cursor = 'grabbing';
            startX = e.pageX - slider.offsetLeft;
            scrollLeft = slider.scrollLeft;
            slider.style.scrollSnapType = 'none';
        });

        // 2. Detener arrastre
        function stopDragging() {
            if (!isDown) return;
            isDown = false;
            slider.style.cursor = 'grab';
            slider.style.scrollSnapType = 'x mandatory';

            if (isDragging) {
                var images = slider.querySelectorAll('img');
                images.forEach(function (img) {
                    img.style.pointerEvents = 'none';
                });
                setTimeout(function () {
                    images.forEach(function (img) {
                        img.style.pointerEvents = 'auto';
                    });
                }, 100);
            }
        }

        slider.addEventListener('mouseleave', stopDragging);
        slider.addEventListener('mouseup', stopDragging);

        // 3. Mover
        slider.addEventListener('mousemove', function (e) {
            if (!isDown) return;
            e.preventDefault();
            var x = e.pageX - slider.offsetLeft;
            var walk = (x - startX) * 2;

            if (Math.abs(walk) > 5) {
                isDragging = true;
            }

            slider.scrollLeft = scrollLeft - walk;
        });
    });
})();
