/**
 * Muy Únicos - Scripts de Administración
 *
 * Crea el botón de reindexado y gestiona su AJAX handler.
 * Nonce y label recibidos vía wp_localize_script (muyuAdminData).
 * Sin dependencia de jQuery — usa IIFE + DOMContentLoaded + fetch().
 */
(function () {
    'use strict';

    document.addEventListener( 'DOMContentLoaded', function () {
        if ( typeof muyuAdminData === 'undefined' ) return;

        // Inyectar botón de reindexado junto al último .page-title-action
        var buttons = document.querySelectorAll( '.page-title-action' );
        if ( ! buttons.length ) return;

        var btn         = document.createElement( 'button' );
        btn.id          = 'muyu-rebuild';
        btn.className   = 'page-title-action';
        btn.textContent = muyuAdminData.label;
        buttons[ buttons.length - 1 ].after( btn );

        btn.addEventListener( 'click', function ( e ) {
            e.preventDefault();
            var originalText = btn.textContent;

            btn.disabled    = true;
            btn.textContent = '⏳ Procesando...';

            var formData = new FormData();
            formData.append( 'nonce',  muyuAdminData.nonce );

            // Endpoint nativo de WooCommerce (URL completa desde PHP)
            var ajaxUrl = muyuAdminData.wc_ajax_url;

            fetch( ajaxUrl, { method: 'POST', body: formData } )
                .then( function ( res ) { return res.json(); } )
                .then( function ( response ) {
                    if ( response.success ) {
                        alert( '✅ ' + response.data );
                        location.reload();
                    } else {
                        alert( '❌ Error: ' + ( response.data || 'Desconocido' ) );
                        btn.disabled    = false;
                        btn.textContent = originalText;
                    }
                } )
                .catch( function () {
                    alert( '❌ Error de conexión con el servidor' );
                    btn.disabled    = false;
                    btn.textContent = originalText;
                } );
        } );
    } );
} )();
