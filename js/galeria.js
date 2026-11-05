/**
 * Muy Unicos — Galeria compartida del tema (spec 019)
 *
 * UN solo componente de galeria para todo el sitio. Tres piezas:
 *   - visor   : modal accesible (reusa `.mu-modal-overlay` / `.mu-modal-container`, D2)
 *   - tira    : miniaturas con carrusel/snap o grilla (el aspecto de css/product.css, D6)
 *   - tarjeta : pieza de catalogo con badge, precio, estrella y acciones
 *
 * Mas dos adaptadores que leen el DOM existente, sin pedir datos (D4/D10):
 *   - desdeWoo(raiz)      : `.woocommerce-product-gallery` (data-large_image)
 *   - desdeEnlaces(raiz)  : cualquier `a[href]` de imagen o `img`
 * Y un modo declarativo (D3): `[data-mu-galeria]` + `autoInit()`.
 *
 * Reglas que NO se rompen:
 *   - No pide datos, no conoce endpoints ni nonces (D10).
 *   - No usa `innerHTML` (todo el texto va por `textContent`): lo exige la puerta.
 *   - No crea un sistema de modal paralelo: el modal es `.mu-modal-*` (D2).
 *   - Consume los tokens del tema (`--primario`, `--mu-radius-*`), nunca los redefine.
 *
 * API: window.MUGaleria (ver specs/019-galeria-compartida/contracts/galeria.md).
 * Version: 1.0.0
 */
(function (global) {
    'use strict';

    /** Vecinos que se precargan a cada lado (sin peticiones extra: ya estan en cache). */
    var VECINOS = 1;
    /** Distancia minima del swipe tactil, en px. */
    var SWIPE_MIN = 40;
    /** Clase de tono: claro (tienda) | oscuro (visor fullscreen que usa el plugin). */
    var TONOS = { claro: 'mu-gal-visor--claro', oscuro: 'mu-gal-visor--oscuro' };

    /* ==================== puros (sin DOM, testea Node) ==================== */

    /** Posicion valida dentro de la lista. */
    function acotar(indice, total) {
        var i = parseInt(indice, 10) || 0;
        var n = parseInt(total, 10) || 0;
        if (i < 0) { i = 0; }
        if (n > 0 && i > n - 1) { i = n - 1; }
        return i;
    }

    /** Indices vecinos a precargar: los `cuantos` de cada lado, sin salirse. */
    function preCargar(items, indice, cuantos) {
        var out = [];
        var n = (items || []).length;
        var k = Math.max(0, parseInt(cuantos, 10) || 0);
        for (var d = 1; d <= k; d++) {
            if (indice - d >= 0) { out.push(indice - d); }
            if (indice + d < n) { out.push(indice + d); }
        }
        return out;
    }

    /**
     * Orden de la tira (misma regla que la tira unificada del cotizador):
     * favorito primero; despues `orden` manual (0 = sin ordenar); despues lo mas
     * nuevo; y `key` desempata para que el orden sea ESTABLE entre repintados.
     */
    function orden(a, b) {
        a = a || {}; b = b || {};
        var fa = a.favorito ? 1 : 0;
        var fb = b.favorito ? 1 : 0;
        if (fa !== fb) { return fb - fa; }
        var oa = isFinite(a.orden) ? Number(a.orden) : 0;
        var ob = isFinite(b.orden) ? Number(b.orden) : 0;
        if (oa !== ob) { return oa - ob; }
        var ca = isFinite(a.creado) ? Number(a.creado) : 0;
        var cb = isFinite(b.creado) ? Number(b.creado) : 0;
        if (ca !== cb) { return cb - ca; }
        return String(a.key || '').localeCompare(String(b.key || ''));
    }

    /** Precio sin decimales, formato es-AR (con respaldo si el entorno no lo tiene). */
    function formatearPrecio(n) {
        var v = Number(n);
        if (!isFinite(v)) { return ''; }
        try {
            return v.toLocaleString('es-AR', { maximumFractionDigits: 0 });
        } catch (e) {
            return String(Math.round(v));
        }
    }

    /* ==================== helpers de DOM ==================== */

    /** Crea un elemento con clase y texto (nunca HTML: solo `textContent`). */
    function crear(doc, tag, clase, texto) {
        var nodo = doc.createElement(tag);
        if (clase) { nodo.className = clase; }
        if (texto !== undefined && texto !== null) { nodo.textContent = String(texto); }
        return nodo;
    }

    /** Vacia un nodo sin `innerHTML`. */
    function vaciar(nodo) {
        while (nodo && nodo.firstChild) { nodo.removeChild(nodo.firstChild); }
    }

    /** Quita una clase de un `className` de forma segura. */
    function sinClase(nodo, clase) {
        var re = new RegExp('\\b' + clase + '\\b', 'g');
        nodo.className = nodo.className.replace(re, '').replace(/\s+/g, ' ').replace(/^ | $/g, '');
    }

    /* ==================== visor (modal del tema) ==================== */

    var estado = {
        raiz: null, items: [], pos: 0, opciones: {}, onKey: null,
        overflowPrevio: '', focoPrevio: null
    };

    /** Hay un visor abierto. */
    function estaAbierto() {
        return !!(estado.raiz && estado.raiz.className.indexOf('is-visible') !== -1);
    }

    /** Bloquea y restaura el scroll del documento mientras el modal esta abierto. */
    function bloquearScroll(doc, si) {
        if (!doc || !doc.body) { return; }
        if (si) {
            estado.overflowPrevio = doc.body.style.overflow;
            doc.body.style.overflow = 'hidden';
            if (doc.body.className.indexOf('is-scroll-locked') === -1) {
                doc.body.className += (doc.body.className ? ' ' : '') + 'is-scroll-locked';
            }
        } else {
            doc.body.style.overflow = estado.overflowPrevio || '';
            sinClase(doc.body, 'is-scroll-locked');
        }
    }

    /** Bloque de metadatos (titulo, descripcion, badge, precio). */
    function pintarMeta(doc, meta) {
        var caja = crear(doc, 'div', 'mu-gal-meta-caja');
        if (meta.badge) { caja.appendChild(crear(doc, 'span', 'mu-gal-badge', meta.badge)); }
        if (meta.titulo) { caja.appendChild(crear(doc, 'p', 'mu-gal-meta-titulo', meta.titulo)); }
        if (meta.descripcion) {
            caja.appendChild(crear(doc, 'p', 'mu-gal-meta-desc', meta.descripcion));
        }
        if (meta.precio && meta.precio.valor !== undefined) {
            var fila = crear(doc, 'p', 'mu-gal-precio');
            if (meta.precio.anterior !== undefined && meta.precio.anterior !== null) {
                fila.appendChild(crear(doc, 'span', 'mu-gal-precio-antes',
                    '$' + formatearPrecio(meta.precio.anterior)));
            }
            fila.appendChild(crear(doc, 'strong', 'mu-gal-precio-ya',
                '$' + formatearPrecio(meta.precio.valor)));
            if (meta.precio.aviso) {
                fila.appendChild(crear(doc, 'span', 'mu-gal-precio-aviso', 'actualizado'));
            }
            caja.appendChild(fila);
        }
        return caja;
    }

    /** Repinta contador, imagen, flechas, meta y precarga de la posicion actual. */
    function pintarVisor() {
        var s = estado;
        if (!s.raiz) { return; }
        var total = s.items.length;
        var item = s.items[s.pos] || {};
        var o = s.opciones || {};

        var cont = s.raiz.querySelector('.mu-gal-contador');
        if (cont) { cont.textContent = (s.pos + 1) + ' / ' + total; }

        var img = s.raiz.querySelector('.mu-gal-img');
        if (img) {
            var url = String(item.url || item.thumb || '');
            if (img.getAttribute('src') !== url) {
                img.className = 'mu-gal-img';
                img.setAttribute('src', url);
            }
            img.setAttribute('alt', String(item.etiqueta || ''));
        }

        var prev = s.raiz.querySelector('.mu-gal-prev');
        var next = s.raiz.querySelector('.mu-gal-next');
        if (prev) { prev.hidden = total <= 1; }
        if (next) { next.hidden = total <= 1; }

        // Meta: la del item manda; si no trae, la del visor (contrato C1).
        var caja = s.raiz.querySelector('.mu-gal-meta');
        if (caja) {
            vaciar(caja);
            var meta = item.meta || o.meta || null;
            if (meta) { caja.appendChild(pintarMeta(s.raiz.ownerDocument, meta)); }
            caja.hidden = !meta;
        }

        // Precarga de vecinos: la MISMA url que la miniatura, cero peticiones extra.
        if (typeof global.Image === 'function') {
            preCargar(s.items, s.pos, VECINOS).forEach(function (i) {
                var vecino = s.items[i] || {};
                var im = new global.Image();
                im.src = String(vecino.url || vecino.thumb || '');
            });
        }

        if (typeof o.alCambiar === 'function') { o.alCambiar(s.pos); }
    }

    /** Navega (circular) y repinta. */
    function mover(delta) {
        var total = estado.items.length;
        if (total <= 1) { return; }
        estado.pos = (estado.pos + delta + total) % total;
        pintarVisor();
    }

    /** Cierra el visor: restaura el scroll, el foco y retira el nodo. */
    function cerrar() {
        var s = estado;
        var doc = global.document;
        if (!s.raiz) { return false; }
        sinClase(s.raiz, 'is-visible');
        s.raiz.setAttribute('aria-hidden', 'true');
        if (s.onKey && doc) { doc.removeEventListener('keydown', s.onKey); }
        s.onKey = null;
        bloquearScroll(doc, false);
        // Se retira del documento: un solo modal vivo, nunca dos apilados.
        var raiz = s.raiz;
        setTimeout(function () {
            if (raiz.parentNode) { raiz.parentNode.removeChild(raiz); }
        }, 250);
        s.raiz = null;
        s.items = [];
        s.pos = 0;
        if (s.focoPrevio && typeof s.focoPrevio.focus === 'function') {
            try { s.focoPrevio.focus(); } catch (e) { /* el foco previo ya no existe */ }
        }
        if (typeof s.opciones.alCerrar === 'function') { s.opciones.alCerrar(); }
        s.opciones = {};
        return true;
    }

    /** Mantiene el foco dentro del modal (Tab / Shift+Tab). */
    function atraparFoco(e, raiz) {
        var focos = raiz.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        var lista = [];
        for (var i = 0; i < focos.length; i++) {
            if (!focos[i].hidden && !focos[i].disabled) { lista.push(focos[i]); }
        }
        if (!lista.length) { return; }
        var doc = global.document;
        var primero = lista[0];
        var ultimo = lista[lista.length - 1];
        if (e.shiftKey && (doc.activeElement === primero || doc.activeElement === raiz)) {
            e.preventDefault();
            ultimo.focus();
        } else if (!e.shiftKey && doc.activeElement === ultimo) {
            e.preventDefault();
            primero.focus();
        }
    }

    /**
     * Abre el visor (contrato C1).
     * @returns {boolean} true si abrio.
     */
    function visor(opciones) {
        var doc = global.document;
        var o = opciones || {};
        var items = Array.isArray(o.items) ? o.items : [];
        if (!doc || !doc.body || !items.length) { return false; }
        if (estado.raiz) { cerrar(); }

        var tono = TONOS[o.tono] ? o.tono : 'claro';
        var overlay = crear(doc, 'div', 'mu-modal-overlay mu-gal-visor ' + TONOS[tono]);
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', String(o.titulo || 'Galeria de imagenes'));

        var caja = crear(doc, 'div', 'mu-modal-container mu-gal-caja');

        var cab = crear(doc, 'div', 'mu-gal-cabecera');
        cab.appendChild(crear(doc, 'p', 'mu-gal-titulo', o.titulo || 'Galeria'));
        cab.appendChild(crear(doc, 'p', 'mu-gal-contador', ''));
        var botonCerrar = crear(doc, 'button', 'mu-modal-close mu-gal-cerrar', '\u00d7');
        botonCerrar.type = 'button';
        botonCerrar.setAttribute('aria-label', 'Cerrar');
        cab.appendChild(botonCerrar);
        caja.appendChild(cab);

        if (Array.isArray(o.segmentos) && o.segmentos.length) {
            var segs = crear(doc, 'div', 'mu-gal-segs');
            o.segmentos.forEach(function (seg) {
                var b = crear(doc, 'button', 'mu-gal-seg', seg.etiqueta);
                b.type = 'button';
                b.setAttribute('aria-pressed', seg.activo ? 'true' : 'false');
                b.addEventListener('click', function () {
                    if (typeof seg.onElegir === 'function') { seg.onElegir(); }
                });
                segs.appendChild(b);
            });
            caja.appendChild(segs);
        }

        var cuerpo = crear(doc, 'div', 'mu-gal-cuerpo');
        var prev = crear(doc, 'button', 'mu-gal-nav mu-gal-prev', '\u2039');
        prev.type = 'button';
        prev.setAttribute('aria-label', 'Anterior');
        var next = crear(doc, 'button', 'mu-gal-nav mu-gal-next', '\u203a');
        next.type = 'button';
        next.setAttribute('aria-label', 'Siguiente');
        var marco = crear(doc, 'div', 'mu-gal-marco');
        var img = crear(doc, 'img', 'mu-gal-img');
        img.setAttribute('decoding', 'async');
        img.setAttribute('alt', '');
        img.addEventListener('load', function () {
            img.className = 'mu-gal-img mu-gal-img--cargada';
        });
        marco.appendChild(img);
        cuerpo.appendChild(prev);
        cuerpo.appendChild(marco);
        cuerpo.appendChild(next);
        caja.appendChild(cuerpo);

        var cajaMeta = crear(doc, 'div', 'mu-gal-meta');
        cajaMeta.hidden = true;
        caja.appendChild(cajaMeta);

        var acciones = crear(doc, 'div', 'mu-gal-acciones');
        (Array.isArray(o.acciones) ? o.acciones : []).forEach(function (ac) {
            var b = crear(doc, 'button', 'mu-gal-accion ' + (ac.clase || ''), ac.etiqueta || '');
            b.type = 'button';
            b.addEventListener('click', function () {
                if (typeof ac.onAccion === 'function') { ac.onAccion(estado.pos); }
            });
            acciones.appendChild(b);
        });
        if (acciones.firstChild) { caja.appendChild(acciones); }

        overlay.appendChild(caja);
        doc.body.appendChild(overlay);

        estado.raiz = overlay;
        estado.items = items;
        estado.pos = acotar(o.indice, items.length);
        estado.opciones = o;
        estado.focoPrevio = doc.activeElement;

        botonCerrar.addEventListener('click', cerrar);
        prev.addEventListener('click', function () { mover(-1); });
        next.addEventListener('click', function () { mover(1); });
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) { cerrar(); }
        });

        var tocaX = null;
        overlay.addEventListener('touchstart', function (e) {
            if (e.touches && e.touches.length === 1) { tocaX = e.touches[0].clientX; }
        }, { passive: true });
        overlay.addEventListener('touchend', function (e) {
            if (tocaX === null || !e.changedTouches || !e.changedTouches.length) { return; }
            var dx = e.changedTouches[0].clientX - tocaX;
            tocaX = null;
            if (Math.abs(dx) > SWIPE_MIN) { mover(dx < 0 ? 1 : -1); }
        }, { passive: true });

        estado.onKey = function (e) {
            if (e.key === 'Escape') { e.preventDefault(); cerrar(); return; }
            if (e.key === 'ArrowLeft') { e.preventDefault(); mover(-1); return; }
            if (e.key === 'ArrowRight') { e.preventDefault(); mover(1); return; }
            if (e.key === 'Tab') { atraparFoco(e, overlay); }
        };
        doc.addEventListener('keydown', estado.onKey);

        bloquearScroll(doc, true);
        pintarVisor();
        try { botonCerrar.focus(); } catch (eFoco) { /* sin foco programatico */ }
        // La transicion del tema corre al agregar `is-visible` en el frame siguiente.
        setTimeout(function () {
            if (estado.raiz === overlay) {
                overlay.className += ' is-visible';
                overlay.setAttribute('aria-hidden', 'false');
            }
        }, 16);
        return true;
    }

    /* ==================== tira (miniaturas) ==================== */

    /**
     * Dibuja una tira de miniaturas en `nodo` (contrato C2). Idempotente: limpia y
     * repinta. `scroll: true` = carrusel con snap (el criterio de css/product.css).
     * @returns {number} cuantas dibujo.
     */
    function tira(nodo, opciones) {
        var doc = global.document;
        var o = opciones || {};
        var items = Array.isArray(o.items) ? o.items : [];
        if (!doc || !nodo) { return 0; }
        vaciar(nodo);
        if (!items.length) { return 0; }
        var tope = parseInt(o.tope, 10);
        if (!(tope > 0) || tope > items.length) { tope = items.length; }
        var visibles = items.slice(0, tope);
        var activo = parseInt(o.activo, 10) || 0;

        var cont = crear(doc, 'div', 'mu-gal-tira' + (o.scroll ? ' mu-gal-tira--scroll' : ''));
        visibles.forEach(function (item, i) {
            var clase = 'mu-gal-mini';
            if (o.forma === 'circulo') { clase += ' mu-gal-mini--circulo'; }
            if (i === activo) { clase += ' mu-gal-mini--activa'; }
            var b = crear(doc, 'button', clase);
            b.type = 'button';
            b.setAttribute('aria-label', String(item.etiqueta || ('Imagen ' + (i + 1))));
            var img = crear(doc, 'img');
            img.setAttribute('loading', 'lazy');
            img.setAttribute('alt', '');
            img.setAttribute('src', String(item.thumb || item.url || ''));
            b.appendChild(img);
            b.addEventListener('click', function () {
                if (typeof o.onAbrir === 'function') { o.onAbrir(i); }
            });
            cont.appendChild(b);
        });
        if (o.mas && items.length > visibles.length) {
            var mas = crear(doc, 'button', 'mu-gal-mini mu-gal-mas', '+' + items.length);
            mas.type = 'button';
            mas.title = 'Ver todas las imagenes';
            mas.setAttribute('aria-label', 'Ver todas las imagenes (' + items.length + ')');
            mas.addEventListener('click', function () {
                if (typeof o.onAbrir === 'function') { o.onAbrir(visibles.length); }
            });
            cont.appendChild(mas);
        }
        nodo.appendChild(cont);
        return visibles.length;
    }

    /**
     * Drag-to-scroll reutilizable (contrato C2b): la UNICA implementacion del gesto
     * en el tema. La usa la ficha de producto sobre el slider de WooCommerce, que
     * antes tenia su propia copia en js/product.js (spec 019, D7).
     *
     * @returns {boolean} true si engancho (false si ya estaba o no hay nodo).
     */
    function arrastrar(nodo, opciones) {
        var doc = global.document;
        if (!doc || !nodo || !nodo.addEventListener) { return false; }
        if (nodo.getAttribute('data-mu-gal-arrastre') === '1') { return false; }
        nodo.setAttribute('data-mu-gal-arrastre', '1');

        var activo = false;
        var arrastro = false;
        var inicioX = 0;
        var inicioScroll = 0;
        var snapPrevio = '';

        nodo.addEventListener('mousedown', function (e) {
            if (nodo.scrollWidth <= nodo.clientWidth) { return; }
            if (e.button !== undefined && e.button !== 0) { return; }
            activo = true;
            arrastro = false;
            inicioX = e.pageX - nodo.offsetLeft;
            inicioScroll = nodo.scrollLeft;
            snapPrevio = nodo.style.scrollSnapType || '';
            nodo.style.scrollSnapType = 'none';
            nodo.style.cursor = 'grabbing';
        });

        nodo.addEventListener('mousemove', function (e) {
            if (!activo) { return; }
            e.preventDefault();
            var x = e.pageX - nodo.offsetLeft;
            var recorrido = (x - inicioX) * 2;
            if (Math.abs(recorrido) > 5) { arrastro = true; }
            nodo.scrollLeft = inicioScroll - recorrido;
        });

        function soltar() {
            if (!activo) { return; }
            activo = false;
            nodo.style.cursor = '';
            nodo.style.scrollSnapType = snapPrevio;
            if (!arrastro) { return; }
            // Un arrastre no debe disparar el click del hijo (cambio de foto, enlaces).
            var hijos = nodo.querySelectorAll('a, img, button');
            var i;
            for (i = 0; i < hijos.length; i++) { hijos[i].style.pointerEvents = 'none'; }
            setTimeout(function () {
                for (var j = 0; j < hijos.length; j++) { hijos[j].style.pointerEvents = ''; }
            }, 100);
        }

        nodo.addEventListener('mouseup', soltar);
        nodo.addEventListener('mouseleave', soltar);
        return true;
    }

    /** Indice de la foto visible en el slider de WooCommerce (o 0). */
    function indiceActivo(galeria) {
        var thumbs = galeria.querySelectorAll('.flex-control-thumbs img');
        for (var i = 0; i < thumbs.length; i++) {
            if (thumbs[i].className.indexOf('flex-active') !== -1) { return i; }
        }
        var slides = galeria.querySelectorAll('.woocommerce-product-gallery__image');
        for (var j = 0; j < slides.length; j++) {
            if (slides[j].className.indexOf('flex-active-slide') !== -1) { return j; }
        }
        return 0;
    }

    /**
     * Galeria de la ficha de WooCommerce (contrato C5/D8): intercepta el trigger de
     * Photoswipe y abre el visor con las fotos REALES del producto.
     *
     * Se engancha en fase de CAPTURA porque Woo escucha en el documento. Si no hay
     * items, no intercepta: Woo conserva su lightbox (degradacion limpia).
     *
     * @returns {number} 1 si engancho, 0 si no hay galeria de Woo en la pagina.
     */
    function navegarWoo(raiz) {
        var doc = global.document;
        var cont = raiz || doc;
        if (!doc || !cont || !cont.querySelectorAll) { return 0; }
        if (doc.body && doc.body.getAttribute('data-mu-gal-woo') === '1') { return 0; }
        if (!cont.querySelectorAll('.woocommerce-product-gallery').length) { return 0; }
        if (doc.body) { doc.body.setAttribute('data-mu-gal-woo', '1'); }

        doc.addEventListener('click', function (e) {
            var t = e.target;
            var trigger = (t && t.closest) ? t.closest('.woocommerce-product-gallery__trigger') : null;
            if (!trigger) { return; }
            var galeria = trigger.closest('.woocommerce-product-gallery') || cont;
            var items = desdeWoo(galeria);
            if (!items.length) { return; }
            e.preventDefault();
            e.stopPropagation();
            if (e.stopImmediatePropagation) { e.stopImmediatePropagation(); }
            visor({
                items: items,
                indice: indiceActivo(galeria),
                titulo: 'Galeria del producto',
                tono: 'claro'
            });
        }, true);
        return 1;
    }

    /* ==================== tarjeta (catalogo) ==================== */

    /**
     * Devuelve un `article.mu-gal-tarjeta` (contrato C3). NO lo inserta: lo inserta el
     * llamador. Sin `onBorrar` no hay boton de borrar (solo las ideas propias).
     */
    function tarjeta(item, opciones) {
        var doc = global.document;
        var o = opciones || {};
        item = item || {};
        if (!doc) { return null; }

        var art = crear(doc, 'article', 'mu-gal-tarjeta');
        var cajaImg = crear(doc, 'div', 'mu-gal-tarjeta-img');
        var img = crear(doc, 'img');
        img.setAttribute('loading', 'lazy');
        img.setAttribute('alt', String(item.titulo || ''));
        img.setAttribute('src', String(item.thumb || item.url || ''));
        cajaImg.appendChild(img);

        if (item.badge) {
            var tipo = String(item.badgeTipo || 'tienda').toLowerCase();
            cajaImg.appendChild(crear(doc, 'span', 'mu-gal-badge mu-gal-badge--' + tipo, item.badge));
        }
        if (typeof o.onEstrella === 'function') {
            var est = crear(doc, 'button', 'mu-gal-estrella', item.favorito ? '\u2605' : '\u2606');
            est.type = 'button';
            est.setAttribute('aria-pressed', item.favorito ? 'true' : 'false');
            est.setAttribute('aria-label', item.favorito ? 'Quitar de favoritos' : 'Marcar como favorito');
            est.addEventListener('click', function () {
                if (typeof o.onEstrella === 'function') { o.onEstrella(!item.favorito); }
            });
            cajaImg.appendChild(est);
        }
        art.appendChild(cajaImg);

        var cuerpo = crear(doc, 'div', 'mu-gal-tarjeta-cuerpo');
        if (item.origen) { cuerpo.appendChild(crear(doc, 'span', 'mu-gal-origen', item.origen)); }
        if (item.titulo) { cuerpo.appendChild(crear(doc, 'p', 'mu-gal-tarjeta-titulo', item.titulo)); }
        if (item.descripcion) {
            cuerpo.appendChild(crear(doc, 'p', 'mu-gal-tarjeta-desc', item.descripcion));
        }
        if (item.precio && item.precio.valor !== undefined) {
            var fila = crear(doc, 'p', 'mu-gal-precio');
            fila.appendChild(crear(doc, 'strong', 'mu-gal-precio-ya',
                '$' + formatearPrecio(item.precio.valor) + ' c/u'));
            if (item.precio.aviso) {
                fila.appendChild(crear(doc, 'span', 'mu-gal-precio-aviso', 'actualizado'));
            }
            cuerpo.appendChild(fila);
        }

        var acciones = crear(doc, 'div', 'mu-gal-tarjeta-acciones');
        if (typeof o.onUsar === 'function') {
            var usar = crear(doc, 'button', 'mu-gal-btn mu-gal-btn--usar', o.textoUsar || 'Usar');
            usar.type = 'button';
            usar.addEventListener('click', function () {
                if (typeof o.onUsar === 'function') { o.onUsar(); }
            });
            acciones.appendChild(usar);
        }
        if (typeof o.onBorrar === 'function') {
            var borrar = crear(doc, 'button', 'mu-gal-btn mu-gal-btn--borrar', o.textoBorrar || 'Borrar');
            borrar.type = 'button';
            borrar.setAttribute('aria-label', 'Borrar');
            borrar.addEventListener('click', function () {
                if (typeof o.onBorrar === 'function') { o.onBorrar(); }
            });
            acciones.appendChild(borrar);
        }
        if (acciones.firstChild) { cuerpo.appendChild(acciones); }
        art.appendChild(cuerpo);
        return art;
    }

    /* ==================== adaptadores (leen el DOM, no piden datos) ==================== */

    /**
     * Items desde la galeria de WooCommerce (contrato C4/FR-009): nunca hace una
     * peticion. Prefiere los slides (`data-large_image`, la foto grande real) y cae
     * a las miniaturas si el tema no los imprime.
     */
    function desdeWoo(raiz) {
        var doc = global.document;
        var cont = raiz || (doc && doc.querySelector('.woocommerce-product-gallery'));
        var out = [];
        if (!cont) { return out; }
        var slides = cont.querySelectorAll('.woocommerce-product-gallery__image img');
        for (var i = 0; i < slides.length; i++) {
            var s = slides[i];
            var url = s.getAttribute('data-large_image') || s.getAttribute('data-src')
                || s.getAttribute('src') || '';
            if (!url) { continue; }
            out.push({
                url: url,
                thumb: s.getAttribute('data-thumb') || s.getAttribute('src') || url,
                etiqueta: s.getAttribute('alt') || ''
            });
        }
        if (out.length) { return out; }
        var thumbs = cont.querySelectorAll('.flex-control-thumbs img');
        for (var j = 0; j < thumbs.length; j++) {
            var t = thumbs[j];
            var u = t.getAttribute('data-large_image') || t.getAttribute('src') || '';
            if (!u) { continue; }
            out.push({ url: u, thumb: t.getAttribute('src') || u, etiqueta: t.getAttribute('alt') || '' });
        }
        return out;
    }

    /**
     * Items desde el DOM de `raiz` (contrato C4/FR-010): enlaces de imagen primero y,
     * si no hay, las propias imagenes. Sirve para cuenta, banners, opciones y
     * contenido, sin JS a medida por caso.
     */
    function desdeEnlaces(raiz) {
        var doc = global.document;
        var cont = raiz || doc;
        var out = [];
        if (!cont) { return out; }
        var enlaces = cont.querySelectorAll('a[href]');
        for (var i = 0; i < enlaces.length; i++) {
            var a = enlaces[i];
            var href = a.getAttribute('href') || '';
            if (!/\.(png|jpe?g|webp|gif|avif|svg)([?#]|$)/i.test(href)) { continue; }
            var img = a.querySelector('img');
            out.push({
                url: href,
                thumb: (img && (img.getAttribute('src') || img.getAttribute('data-src'))) || href,
                etiqueta: (img && img.getAttribute('alt')) || a.getAttribute('title') || ''
            });
        }
        if (out.length) { return out; }
        var imgs = cont.querySelectorAll('img');
        for (var j = 0; j < imgs.length; j++) {
            var im = imgs[j];
            var src = im.getAttribute('data-large_image') || im.getAttribute('data-src')
                || im.getAttribute('src') || '';
            if (!src) { continue; }
            out.push({ url: src, thumb: im.getAttribute('src') || src, etiqueta: im.getAttribute('alt') || '' });
        }
        return out;
    }

    /* ==================== modo declarativo (D3) ==================== */

    /** Items de un contenedor `[data-mu-galeria]`: JSON propio o los del DOM. */
    function itemsDe(nodo) {
        var crudo = nodo.getAttribute('data-mu-galeria-items');
        if (crudo) {
            try {
                var lista = JSON.parse(crudo);
                if (Array.isArray(lista) && lista.length) { return lista; }
            } catch (e) { /* JSON invalido: cae a desdeEnlaces (contrato C4) */ }
        }
        return desdeEnlaces(nodo);
    }

    /** Abre el visor con lo que declara el contenedor. */
    function abrirDesdeNodo(nodo, indice) {
        var items = itemsDe(nodo);
        if (!items.length) { return false; }
        var i = (indice === undefined || indice === null)
            ? acotar(nodo.getAttribute('data-mu-galeria-indice'), items.length)
            : acotar(indice, items.length);
        return visor({
            items: items,
            indice: i,
            titulo: nodo.getAttribute('data-mu-galeria-titulo') || 'Galeria',
            tono: nodo.getAttribute('data-mu-galeria-tono') || 'claro'
        });
    }

    /**
     * Inicializa cada `[data-mu-galeria]` de `raiz` (delegado: un listener por
     * contenedor, nunca uno por elemento). Idempotente.
     * @returns {number} contenedores inicializados en esta pasada.
     */
    function autoInit(raiz) {
        var doc = global.document;
        var cont = raiz || doc;
        if (!doc || !cont || !cont.querySelectorAll) { return 0; }
        var nodos = cont.querySelectorAll('[data-mu-galeria]');
        var n = 0;
        for (var i = 0; i < nodos.length; i++) {
            var nodo = nodos[i];
            if (nodo.getAttribute('data-mu-galeria-listo') === '1') { continue; }
            nodo.setAttribute('data-mu-galeria-listo', '1');
            (function (actual) {
                actual.addEventListener('click', function (e) {
                    var t = e.target;
                    var disparador = (t && t.closest) ? t.closest('[data-mu-galeria-abrir]') : null;
                    if (!disparador) {
                        var dentro = (t && t.closest) ? t.closest('a[href], img') : null;
                        if (!dentro || !actual.contains(dentro)) { return; }
                    }
                    if (abrirDesdeNodo(actual)) { e.preventDefault(); }
                });
            }(nodo));
            n++;
        }
        return n;
    }

    /* ==================== export ==================== */

    var api = {
        visor: visor,
        cerrar: cerrar,
        estaAbierto: estaAbierto,
        tira: tira,
        tarjeta: tarjeta,
        desdeWoo: desdeWoo,
        desdeEnlaces: desdeEnlaces,
        autoInit: autoInit,
        arrastrar: arrastrar,
        navegarWoo: navegarWoo,
        puro: { acotar: acotar, preCargar: preCargar, orden: orden, formatearPrecio: formatearPrecio },
        version: '1.0.0'
    };

    global.MUGaleria = api;
    if (typeof module !== 'undefined' && module.exports) { module.exports = api; }

    // Declarativo: con el DOM listo se auto-inicializa (D3). Los consumidores pueden
    // llamar `autoInit(raiz)` otra vez cuando inyecten HTML nuevo.
    if (global.document) {
        if (global.document.readyState === 'loading') {
            global.document.addEventListener('DOMContentLoaded', function () {
                autoInit();
                navegarWoo();
            });
        } else {
            autoInit();
            navegarWoo();
        }
    }
})(typeof window !== 'undefined' ? window : globalThis);