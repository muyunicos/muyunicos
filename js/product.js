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
