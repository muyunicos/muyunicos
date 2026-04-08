/**
 * Muy Únicos — Ficha de Producto JS
 * Drag-to-scroll en miniaturas (igual al Sistema Global de Carruseles)
 * Carga condicional: is_product()
 * Dependencia: wc-single-product
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

        // 1. INICIAR ARRASTRE
        slider.addEventListener('mousedown', function (e) {
            // Solo activar si hay overflow real
            if (slider.scrollWidth <= slider.clientWidth) return;

            isDown = true;
            isDragging = false;
            slider.style.cursor = 'grabbing';
            startX = e.pageX - slider.offsetLeft;
            scrollLeft = slider.scrollLeft;

            // Desactivar snap temporalmente para arrastre fluido
            slider.style.scrollSnapType = 'none';
        });

        // 2. DETENER ARRASTRE
        function stopDragging() {
            if (!isDown) return;
            isDown = false;
            slider.style.cursor = 'grab';

            // Reactivar snap magnético al soltar
            slider.style.scrollSnapType = 'x mandatory';

            // Si hubo arrastre real, bloquear el click en imágenes brevemente
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

        // 3. MOVIENDO EL MOUSE
        slider.addEventListener('mousemove', function (e) {
            if (!isDown) return;
            e.preventDefault();
            var x = e.pageX - slider.offsetLeft;
            var walk = (x - startX) * 2; // Multiplicador de velocidad

            // Umbral para distinguir arrastre de click tembloroso
            if (Math.abs(walk) > 5) {
                isDragging = true;
            }

            slider.scrollLeft = scrollLeft - walk;
        });
    });
}());
