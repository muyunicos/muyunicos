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

        var slider = document.querySelector('.woocommerce-product-gallery .flex-control-thumbs');
        if (slider) { gal.arrastrar(slider); }

        // La lupa de WooCommerce abre el visor compartido con las fotos reales.
        gal.navegarWoo();
    });
}());
