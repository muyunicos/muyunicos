/**
 * Muy Únicos — Ficha de Producto JS
 * Conecta la galeria compartida del tema (spec 019): `MUGaleria.arrastrar` para las
 * miniaturas y `MUGaleria.navegarWoo` para la lupa. No tiene logica propia de galeria.
 * Carga condicional: is_product()
 * Dependencia: wc-single-product + mu-galeria
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        /**
         * La galeria de la ficha la pone `MUGaleria` (js/galeria.js): aca solo se
         * CONECTA lo que ya existe en el DOM de WooCommerce.
         *
         * El drag-to-scroll propio se retiro: ahora hay UNA implementacion del gesto
         * (`MUGaleria.arrastrar`), compartida con el resto de las tiras (spec 019, D7).
         * Sin el modulo, la ficha sigue funcionando con WooCommerce.
         */
        var gal = window.MUGaleria;
        if (!gal) { return; }

        // El arrastre se engancha en CADA slider que exista. Antes se engachaba sobre el
        // PRIMER `.flex-control-thumbs` una sola vez: si Woo/GeneratePress pintaba o
        // RE-pintaba la galeria despues, el gesto quedaba en un nodo huerfano (medido en
        // el laboratorio: el slider visible no tenia `data-mu-gal-arrastre`).
        // El motor es idempotente (`arrastrar` marca `data-mu-gal-arrastre`), asi que
        // llamarlo varias veces es barato.
        var enganchar = function () {
            var nodos = document.querySelectorAll('.woocommerce-product-gallery .flex-control-thumbs');
            for (var i = 0; i < nodos.length; i++) { gal.arrastrar(nodos[i]); }
        };

        // 1) Inmediato + reintentos acotados (3 s): cubre el pintado tardio de Woo.
        var intentos = 0;
        (function reintentar() {
            enganchar();
            intentos += 1;
            if (intentos < 20) { setTimeout(reintentar, 150); }
        }());

        /**
     * Flechas del slider de miniaturas (spec 020): reutiliza `.mu-nav-btn` del tema y
         * aparecen SOLO si el slider desborda en X. En el grid de Woo no hay scroll, asi
         * que se ocultan (no estorban).
         */
        var intentosFlechas = 0;
        function ponerFlechas() {
            var slider = document.querySelector('.woocommerce-product-gallery .flex-control-thumbs');
            if (!slider || !window.MUGaleria) {
                // El slider de Woo puede pintarse despues de DOMContentLoaded: reintento
                // acotado (3 s) como el enganche del arrastre.
                if (intentosFlechas < 20) { intentosFlechas += 1; setTimeout(ponerFlechas, 150); }
                return;
            }
            var caja = slider.parentNode;
            if (!caja || !caja.classList) { return; }
            var prev = caja.querySelector(':scope > .mu-gal-thumbs-prev');
            if (prev) { return; }                       // ya montadas
            var envoltura = document.createElement('div');
            envoltura.className = 'mu-gal-thumbs';
            caja.insertBefore(envoltura, slider);
            envoltura.appendChild(slider);

            var svg = function (dir) {
                var s = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                s.setAttribute('viewBox', '0 0 24 24');
                s.setAttribute('fill', 'none');
                s.setAttribute('stroke', 'currentColor');
                s.setAttribute('stroke-width', '2');
                s.setAttribute('width', '18');
                s.setAttribute('height', '18');
                s.setAttribute('aria-hidden', 'true');
                var p = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
                p.setAttribute('points', dir === 'prev' ? '15 18 9 12 15 6' : '9 18 15 12 9 6');
                s.appendChild(p);
                return s;
            };
            var mk = function (dir) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'mu-nav-btn ' + dir + ' mu-gal-thumbs-' + dir;
                b.setAttribute('aria-label', dir === 'prev' ? 'Anterior' : 'Siguiente');
                b.appendChild(svg(dir));
                return b;
            };
            var btnPrev = mk('prev');
            var btnNext = mk('next');
            envoltura.appendChild(btnPrev);
            envoltura.appendChild(btnNext);

            var paso = function () {
                var ancho = slider.querySelector('.mu-gal-celda, li');
                var w = ancho ? ancho.getBoundingClientRect().width + 8 : 80;
                slider.scrollBy({ left: w, behavior: 'smooth' });
            };
            btnPrev.addEventListener('click', function () { slider.scrollBy({ left: -9999, behavior: 'smooth' }); });
            btnNext.addEventListener('click', paso);
            var actualizar = function () {
                var desborda = slider.scrollWidth - slider.clientWidth > 4;
                var alInicio = slider.scrollLeft <= 2;
                var alFinal = slider.scrollLeft + slider.clientWidth >= slider.scrollWidth - 2;
                btnPrev.hidden = !desborda || alInicio;
                btnNext.hidden = !desborda || alFinal;
            };
            slider.addEventListener('scroll', actualizar, { passive: true });
            window.addEventListener('resize', actualizar);
            setTimeout(actualizar, 60);
        };
        ponerFlechas();

        // 2) Si la galeria se REEMPLAZA despues, el observer vuelve a enganchar (debounce).
        if (typeof MutationObserver === 'function') {
            var raizGal = document.querySelector('.woocommerce-product-gallery') || document.body;
            var pendiente = null;
            var obsGal = new MutationObserver(function () {
                if (pendiente) { return; }
                pendiente = setTimeout(function () { pendiente = null; enganchar(); }, 80);
            });
            obsGal.observe(raizGal, { childList: true, subtree: true });
        }
        // (el enganche vive arriba: inmediato + reintentos + observer)

        // La lupa de WooCommerce abre el visor compartido con las fotos reales.
        gal.navegarWoo();
    });
}());
