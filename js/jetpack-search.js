/**
 * MUYUNICOS - Jetpack Search URL Rewriter
 * Corrige los enlaces de los resultados de Jetpack Instant Search
 * para que apunten al subdominio actual del usuario (multi-país).
 * Version: 1.1.0
 */

(function() {
    'use strict';

    var data = window.muJetpackSearchData || null;
    if (!data || !data.mainDomain) {
        return;
    }

    var mainDomain = data.mainDomain;
    var currentHost = data.currentHost;
    var languagePrefix = data.languagePrefix || '';
    var hasLanguagePrefix = languagePrefix.length > 0;
    // wp_localize_script convierte booleanos PHP: true → '1', false → ''
    // (compatibilidad: también aceptamos false real de wp_add_inline_script)
    var showPrice = data.showPrice !== false && data.showPrice !== '' && data.showPrice !== '0';

    /**
     * Construye la URL correcta para el subdominio actual.
     * Si la URL ya apunta al host correcto, devuelve la URL sin cambios.
     *
     * @param {string} url URL original del resultado.
     * @return {string} URL corregida.
     */
    function rewriteUrl(url) {
        if (!url || url.indexOf('//') === -1) {
            return url;
        }

        // Extraer host y path de la URL (protocol-relative o absoluta)
        var match = url.match(/^(?:https?:)?\/\/([^\/]+)(\/.*)?$/);
        if (!match) {
            return url;
        }

        var host = match[1].replace(/^www\./, '');
        var path = match[2] || '/';

        // Si el host ya es el actual, no tocar
        if (host === currentHost) {
            return url;
        }

        // Solo reescribir si el host es un subdominio del dominio principal
        // (ej: mexico.muyunicos.com, co.muyunicos.com, muyunicos.com)
        if (host !== mainDomain && host.indexOf('.' + mainDomain) === -1) {
            return url;
        }

        // Agregar prefijo de idioma si es BR/US y no está presente
        if (hasLanguagePrefix && path.indexOf(languagePrefix) !== 0) {
            path = languagePrefix + path;
        }

        return '//' + currentHost + path;
    }

    /**
     * Oculta los precios del overlay fuera de Argentina.
     *
     * El índice de Jetpack Search guarda price_html en ARS (precio base) y
     * WCPBC no los convierte en el overlay. Esta es la defensa JS por si
     * Jetpack renderiza precios por alguna vía no cubierta por el filtro PHP
     * `enableProductPrice`.
     */
    function hidePrices(root) {
        if (showPrice) {
            return;
        }

        var priceNodes = root.querySelectorAll('.jetpack-instant-search__product-price');
        for (var i = 0; i < priceNodes.length; i++) {
            priceNodes[i].style.display = 'none';
        }
    }

    /**
     * Reescribe los links de los resultados de búsqueda y oculta precios
     * si el país actual no es Argentina.
     */
    function rewriteResultLinks(root) {
        var selectors = [
            '.jetpack-instant-search__search-result-title-link',
            '.jetpack-instant-search__search-result-product-img-link',
            '.jetpack-instant-search__search-result-link'
        ];

        var links = root.querySelectorAll(selectors.join(','));
        for (var i = 0; i < links.length; i++) {
            var link = links[i];
            var href = link.getAttribute('href');
            var newHref = rewriteUrl(href);

            if (newHref !== href) {
                link.setAttribute('href', newHref);
            }
        }

        hidePrices(root);
    }

    /**
     * Inicializa el MutationObserver para corregir los links
     * cuando Jetpack renderiza (o re-renderiza) los resultados.
     */
    function init() {
        // Corregir links ya renderizados en el DOM
        rewriteResultLinks(document);

        // Observar cambios en el DOM (los resultados se renderizan dinámicamente)
        var observer = new MutationObserver(function(mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var mutation = mutations[i];
                if (mutation.type === 'childList' && mutation.addedNodes.length) {
                    for (var j = 0; j < mutation.addedNodes.length; j++) {
                        var node = mutation.addedNodes[j];
                        if (node.nodeType === 1) { // Element node
                            rewriteResultLinks(node);
                        }
                    }
                }
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();