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

    /* ==================== coleccion v2: aritmetica de celdas (pura) ==================== */

    /**
     * Cuantas celdas se pintan y cuanto resume el chip (contrato `coleccion.md`, C1).
     *
     * Reglas cerradas con el mantenedor (2026-11-05):
     *   - Las IMAGENES van primero; los huecos rellenan lo que quede.
     *   - El chip cuenta SOLO items ocultos (los huecos los cuenta el `pie`).
     *   - Si no caben todos los items, el chip RESERVA una celda (`tope - 1`).
     *   - Los huecos obligatorios (`min - n`) compiten por celda; la invitacion solo
     *     rellena si sobra.
     *
     * @param {{cargadas:number, min:number, capacidad:number, tope:number}} opciones
     * @returns {{visiblesItems:number, huecos:number, chip:number, reservaChip:boolean,
     *            obligatorios:number, invitacion:number, celdas:number}}
     */
    function celdas(opciones) {
        var o = opciones || {};
        var n = Math.max(0, parseInt(o.cargadas, 10) || 0);
        var min = Math.max(0, parseInt(o.min, 10) || 0);
        var capacidad = Math.max(0, parseInt(o.capacidad, 10) || 0);
        var tope = Math.max(0, parseInt(o.tope, 10) || 0);   // 0 = todos

        // Huecos: los obligatorios mandan; la invitacion solo existe en un campo
        // cargable (con `min` o `capacidad`) y mientras no se llego al maximo.
        var obligatorios = Math.max(0, min - n);
        var cargable = (min > 0 || capacidad > 0);
        var invitacion = (cargable && n >= min && (capacidad === 0 || capacidad > n)) ? 1 : 0;

        // El chip: solo si NO caben todos los items. Reserva una celda.
        var reservaChip = (tope > 0 && n > tope);
        var celdasItems;
        if (tope === 0) {
            celdasItems = n;
        } else if (reservaChip) {
            celdasItems = Math.max(0, tope - 1);
        } else {
            celdasItems = Math.min(tope, n);
        }
        var visiblesItems = Math.min(n, celdasItems);
        var chip = reservaChip ? (n - visiblesItems) : 0;

        // Lo que queda libre va a huecos (obligatorios primero).
        var libres = (tope === 0)
            ? (obligatorios + invitacion)
            : Math.max(0, tope - visiblesItems - (reservaChip ? 1 : 0));
        var huecos = Math.min(obligatorios + invitacion, libres);

        return {
            visiblesItems: visiblesItems,
            huecos: huecos,
            chip: chip,
            reservaChip: reservaChip,
            obligatorios: obligatorios,
            invitacion: invitacion,
            celdas: visiblesItems + huecos + (reservaChip ? 1 : 0)
        };
    }

    /**
     * Texto del pie (contrato C2). Es lo que reemplaza al chip para los huecos:
     * "Carga 4 imagenes" en vez de un "+4" que no lleva a ninguna imagen.
     *
     * @param {{cargadas:number, min:number, capacidad:number, sustantivo:string,
     *          sustantivoSingular:string}} opciones
     * @returns {string} texto, o '' si el campo no tiene minimo ni maximo.
     */
    function pie(opciones) {
        var o = opciones || {};
        var n = Math.max(0, parseInt(o.cargadas, 10) || 0);
        var min = Math.max(0, parseInt(o.min, 10) || 0);
        var capacidad = Math.max(0, parseInt(o.capacidad, 10) || 0);
        var que = String(o.sustantivo || 'imagenes');

        if (min > 0 && n < min) {
            var faltan = min - n;
            var uno = String(o.sustantivoSingular || que);
            return 'Carga ' + faltan + ' ' + (faltan === 1 ? uno : que);
        }
        if (capacidad > 0 && n < capacidad) {
            var puede = capacidad - n;
            return 'Puedes agregar ' + puede + ' mas';
        }
        if (capacidad > 0 && n >= capacidad) {
            return 'Maximo alcanzado';
        }
        return '';
    }

    /**
     * Cuantas celdas entran en un ancho dado (contrato C3, fila no deslizable).
     * Con 100 px de espacio, celdas de 30 y sin gap -> 3 (2 items + el chip).
     *
     * @param {number} ancho  ancho disponible del contenedor, en px
     * @param {number} celda  ancho de celda (incluye margin/padding), en px
     * @param {number} gap    separacion entre celdas, en px
     * @returns {number} celdas que entran (minimo 1)
     */
    function caben(ancho, celda, gap) {
        var a = Math.max(0, parseInt(ancho, 10) || 0);
        var c = Math.max(1, parseInt(celda, 10) || 1);
        var g = Math.max(0, parseInt(gap, 10) || 0);
        return Math.max(1, Math.floor((a + g) / (c + g)));
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

    /**
     * Chevron del sistema `.mu-nav-btn` del tema (misma forma que `inc/ui.php`).
     * Se crea con `createElementNS`: el componente no usa `innerHTML`.
     */
    function crearChevron(doc, hacia) {
        var ns = 'http://www.w3.org/2000/svg';
        var svg = doc.createElementNS(ns, 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '2');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        svg.setAttribute('width', '24');
        svg.setAttribute('height', '24');
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('focusable', 'false');
        var linea = doc.createElementNS(ns, 'polyline');
        linea.setAttribute('points', hacia === 'prev' ? '15 18 9 12 15 6' : '9 18 15 12 9 6');
        svg.appendChild(linea);
        return svg;
    }

    /** Icono de tachito (misma familia que el chevron: SVG sin innerHTML). */
    function crearTacho(doc) {
        var ns = 'http://www.w3.org/2000/svg';
        var svg = doc.createElementNS(ns, 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '2');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        svg.setAttribute('width', '24');
        svg.setAttribute('height', '24');
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('focusable', 'false');
        var tapa = doc.createElementNS(ns, 'path');
        tapa.setAttribute('d', 'M3 6h18');
        var asa = doc.createElementNS(ns, 'path');
        asa.setAttribute('d', 'M8 6V4h8v2');
        var caja = doc.createElementNS(ns, 'path');
        caja.setAttribute('d', 'M6 6l1 14h10l1-14');
        svg.appendChild(tapa);
        svg.appendChild(asa);
        svg.appendChild(caja);
        return svg;
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
        // Flechas: reusa el sistema `.mu-nav-btn` del tema (D18) con su SVG.
        var prev = crear(doc, 'button', 'mu-nav-btn prev mu-gal-prev');
        prev.type = 'button';
        prev.setAttribute('aria-label', 'Anterior');
        prev.appendChild(crearChevron(doc, 'prev'));
        var next = crear(doc, 'button', 'mu-nav-btn next mu-gal-next');
        next.type = 'button';
        next.setAttribute('aria-label', 'Siguiente');
        next.appendChild(crearChevron(doc, 'next'));
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

    /** Ancho de cada miniatura y separacion (espejo del CSS). */
    var ANCHO_MINI = 70;
    var HUECO_MINI = 6;
    /** A partir de este ancho de CONTENEDOR se prefiere grilla antes que carrusel. */
    var ANCHO_GRILLA = 480;
    /** Estado por contenedor (items/opciones) para repintar al cambiar el ancho. */
    var tiras = (typeof WeakMap === 'function') ? new WeakMap() : null;
    var observadorTiras = null;

    /** Entran `cuantas` miniaturas en una sola linea del contenedor? */
    function cabenEnLinea(nodo, cuantas, anchoMini, hueco) {
        var ancho = nodo.clientWidth || 0;
        if (!ancho) { return true; }   // sin layout (oculto): no degradar a carrusel
        return (cuantas * anchoMini + (cuantas - 1) * hueco) <= ancho;
    }

    /**
     * Modo efectivo de la tira (contrato C2 v1.1). `auto` mira el ANCHO DEL
     * CONTENEDOR (no el viewport, que era el error del criterio anterior):
     *   - todo cabe en una linea -> linea
     *   - no cabe y el contenedor es ancho -> grilla
     *   - no cabe y el contenedor es angosto -> linea con scroll (nunca inaccesible)
     */
    function modoEfectivo(nodo, total, o) {
        var modo = (o.modo === 'grilla' || o.modo === 'linea') ? o.modo : 'auto';
        if (modo !== 'auto') { return modo; }
        if (cabenEnLinea(nodo, total, ANCHO_MINI, HUECO_MINI)) { return 'linea'; }
        return ((nodo.clientWidth || 0) >= ANCHO_GRILLA) ? 'grilla' : 'linea';
    }

    /**
     * Una miniatura (contrato C2 v1.1): CONTENEDOR con dos botones, nunca un boton
     * con un boton dentro (HTML invalido).
     */
    function construirMini(doc, item, i, o, activo) {
        var caja = crear(doc, 'div', 'mu-gal-mini');
        caja.setAttribute('data-mu-gal-mini', String(i));
        if (o.forma === 'circulo') { caja.className += ' mu-gal-mini--circulo'; }
        if (i === activo) { caja.className += ' mu-gal-mini--activa'; }

        var abrir = crear(doc, 'button', 'mu-gal-mini-abrir');
        abrir.type = 'button';
        abrir.setAttribute('aria-label', String(item.etiqueta || ('Imagen ' + (i + 1))));
        var img = crear(doc, 'img');
        img.setAttribute('loading', 'lazy');
        img.setAttribute('alt', '');
        img.setAttribute('src', String(item.thumb || item.url || ''));
        abrir.appendChild(img);
        abrir.addEventListener('click', function () {
            if (typeof o.onAbrir === 'function') { o.onAbrir(i); }
        });
        caja.appendChild(abrir);

        if (o.borrar && typeof o.onBorrar === 'function') {
            var borrar = crear(doc, 'button', 'mu-gal-mini-borrar', '\u00d7');
            borrar.type = 'button';
            borrar.setAttribute('aria-label',
                'Quitar ' + (item.etiqueta || ('imagen ' + (i + 1))));
            borrar.addEventListener('click', function (e) {
                if (e.stopPropagation) { e.stopPropagation(); }
                var seguir = true;
                if (typeof o.confirmar === 'function') {
                    seguir = !!o.confirmar(i, item);
                } else if (o.confirmar !== false && typeof global.confirm === 'function') {
                    seguir = global.confirm('Quitar esta imagen?');
                }
                if (seguir) { o.onBorrar(i, item); }
            });
            caja.appendChild(borrar);
        }
        return caja;
    }

    /** Una ranura a llenar (campo con minimo de imagenes). */
    function construirRanura(doc, i, total, o) {
        var b = crear(doc, 'button', 'mu-gal-mini mu-gal-mini--vacia');
        b.type = 'button';
        b.setAttribute('data-mu-gal-ranura', String(i));
        b.setAttribute('aria-label', 'Agregar imagen ' + (i + 1) + ' de ' + total);
        b.appendChild(crear(doc, 'span', 'mu-gal-mini-mas', '+'));
        b.addEventListener('click', function () {
            if (typeof o.onRanura === 'function') { o.onRanura(i); }
        });
        return b;
    }

    /**
     * Dibuja (o repinta) la tira. Conserva el scroll y el foco del contenedor al
     * repintar (resize), para que el repintado no "salte" al inicio.
     */
    function pintarTira(nodo, o, items) {
        var doc = global.document;
        var tope = parseInt(o.tope, 10);
        if (!(tope > 0) || tope > items.length) { tope = items.length; }
        var visibles = items.slice(0, tope);
        var filas = parseInt(o.filas, 10) || 0;

        // Con `filas` la grilla recorta: el tope efectivo es lo que cabe (filas x
        // columnas del contenedor) y el resto se resume en el "+N" (FR-017).
        var recortado = false;
        if (o.modo === 'grilla' && filas > 0 && nodo.clientWidth) {
            var porFila = Math.max(1, Math.floor((nodo.clientWidth + HUECO_MINI) / (ANCHO_MINI + HUECO_MINI)));
            var caben = porFila * filas;
            if (caben < visibles.length) {
                visibles = visibles.slice(0, caben);
                recortado = true;
            }
        }

        var min = Math.max(0, parseInt(o.min, 10) || 0);
        var ranuras = Math.max(0, min - items.length);
        var activo = (o.activo === undefined) ? -1 : (parseInt(o.activo, 10) || 0);
        var total = visibles.length + ranuras;
        var modo = modoEfectivo(nodo, total, o);

        var scrollPrevio = nodo.scrollLeft || 0;
        var focoPrevio = null;
        if (doc.activeElement && nodo.contains && nodo.contains(doc.activeElement)
            && doc.activeElement.closest) {
            var cajaFoco = doc.activeElement.closest('[data-mu-gal-mini]');
            if (cajaFoco) { focoPrevio = cajaFoco.getAttribute('data-mu-gal-mini'); }
        }

        vaciar(nodo);
        nodo.setAttribute('data-mu-gal-modo', modo);

        if (o.contador && min > 0) {
            nodo.appendChild(crear(doc, 'p', 'mu-gal-cuenta',
                Math.min(items.length, min) + ' de ' + min));
        }

        var cont = crear(doc, 'div', 'mu-gal-tira mu-gal-tira--' + modo);
        if (modo === 'grilla' && filas > 0) {
            cont.setAttribute('data-filas', String(filas));
            cont.style.setProperty('--filas', String(filas));
        }

        visibles.forEach(function (item, i) {
            cont.appendChild(construirMini(doc, item, i, o, activo));
        });

        // "+N" = las que FALTAN (antes decia el total, que se leia mal).
        var faltan = items.length - visibles.length;
        var chipFuera = null;
        // Con `filas` el "+N" es AUTOMATICO: si recortamos, hay que avisar (FR-017).
        if ((o.mas || recortado) && faltan > 0) {
            var mas = crear(doc, 'button', 'mu-gal-mini mu-gal-mas', '+' + faltan);
            mas.type = 'button';
            mas.title = 'Ver ' + faltan + ' imagenes mas';
            mas.setAttribute('aria-label',
                'Ver ' + faltan + ' imagenes mas (' + items.length + ' en total)');
            mas.addEventListener('click', function () {
                if (typeof o.onMas === 'function') {
                    o.onMas(visibles.length);
                } else if (typeof o.onAbrir === 'function') {
                    o.onAbrir(visibles.length);
                }
            });
            if (recortado) { chipFuera = mas; } else { cont.appendChild(mas); }
        }

        for (var r = 0; r < ranuras; r++) {
            cont.appendChild(construirRanura(doc, visibles.length + r, min, o));
        }

        nodo.appendChild(cont);
        // Si la grilla recorto, el chip va FUERA del contenedor recortado o no se ve.
        if (chipFuera) {
            chipFuera.className += ' mu-gal-mas--fuera';
            nodo.appendChild(chipFuera);
        }
        nodo.scrollLeft = scrollPrevio;
        if (focoPrevio !== null) {
            var destino = cont.querySelector('[data-mu-gal-mini="' + focoPrevio + '"] .mu-gal-mini-abrir');
            if (destino) { try { destino.focus(); } catch (eFoco2) { /* sin foco */ } }
        }
        return { modo: modo, total: total, dibujadas: total, faltan: faltan };
    }

    /** Repinta al cambiar el ANCHO del contenedor (solo en modo `auto`). */
    function observarTira(nodo) {
        if (!tiras || !nodo) { return; }
        var est = tiras.get(nodo);
        if (!est) { return; }
        if (est.opciones.modo === 'linea' || est.opciones.modo === 'grilla') { return; }
        if (typeof global.ResizeObserver !== 'function') { return; }
        if (!observadorTiras) {
            observadorTiras = new global.ResizeObserver(function (entradas) {
                for (var i = 0; i < entradas.length; i++) {
                    var destino = entradas[i].target;
                    var s = tiras.get(destino);
                    if (!s) { continue; }
                    if (s.opciones.modo === 'linea' || s.opciones.modo === 'grilla') { continue; }
                    if (modoEfectivo(destino, s.total, s.opciones) === s.modo) { continue; }
                    var r = pintarTira(destino, s.opciones, s.items);
                    s.modo = r.modo;
                    s.total = r.total;
                }
            });
        }
        observadorTiras.observe(nodo);
    }

    /**
     * Dibuja una tira de miniaturas en `nodo` (contrato C2 v1.1). Idempotente: limpia
     * y repinta. `modo: 'auto'` decide por el ANCHO DEL CONTENEDOR; `min` dibuja
     * ranuras a llenar; `borrar` agrega el tachito con confirmacion.
     * @returns {number} cuantas dibujo (fotos reales + ranuras).
     */
    function tira(nodo, opciones) {
        var doc = global.document;
        if (!doc || !nodo) { return 0; }
        var o = opciones || {};
        var items = Array.isArray(o.items) ? o.items : [];
        var res = pintarTira(nodo, o, items);
        if (tiras) {
            tiras.set(nodo, { items: items, opciones: o, modo: res.modo, total: res.total });
        }
        observarTira(nodo);
        return res.dibujadas;
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

    /* ==================== coleccion v2 (ambito 1) ==================== */

    /** Lado de la celda por preset de tamano (px). */
    var TAMANOS = { chico: 72, medio: 112, grande: 160 };

    /** Presets de slots (contrato `coleccion.md`, C5). */
    var PRESETS = {
        mini: { imagen: 1, eliminar: 1 },
        tarjeta: { imagen: 1, etiqueta: 1, favorito: 1, titulo: 1, descripcion: 1, precio: 1, acciones: 1 },
        producto: { imagen: 1, etiqueta: 1, titulo: 1, precio: 1 },
        opcion: { imagen: 1, titulo: 1, estado: 1 }
    };

    /** `tamano` en px: preset, numero o `{ancho, alto}`. */
    function medidas(tamano) {
        if (tamano && typeof tamano === 'object') {
            var a = Math.max(24, parseInt(tamano.ancho, 10) || TAMANOS.medio);
            var h = Math.max(24, parseInt(tamano.alto, 10) || a);
            return { ancho: a, alto: h };
        }
        var lado = TAMANOS[String(tamano)] || TAMANOS.medio;
        return { ancho: lado, alto: lado };
    }

    /** Slots efectivos: preset + `slots` (objeto de booleanos o lista de nombres). */
    function resolverSlots(o) {
        var preset = PRESETS[String(o.preset)] || PRESETS.mini;
        var base = {};
        var k;
        for (k in preset) { if (preset.hasOwnProperty(k)) { base[k] = preset[k]; } }

        var s = o.slots;
        if (Array.isArray(s)) {
            var lista = {};
            for (var i = 0; i < s.length; i++) { lista[String(s[i])] = 1; }
            lista.imagen = 1;                       // la imagen nunca se quita
            return lista;
        }
        if (s && typeof s === 'object') {
            for (k in s) { if (s.hasOwnProperty(k)) { base[k] = s[k] ? 1 : 0; } }
        }
        base.imagen = 1;
        return base;
    }

    /** Una celda con el item (contrato C6). Nunca un boton dentro de otro. */
    function construirCelda(doc, item, i, o, activo, slots, tam) {
        var celda = crear(doc, 'div', 'mu-gal-celda');
        celda.setAttribute('data-mu-gal-mini', String(i));
        celda.style.setProperty('--mu-gal-tam', tam.ancho + 'px');
        if (i === activo) { celda.className += ' mu-gal-celda--activa'; }

        var caja = crear(doc, 'div', 'mu-gal-item');
        caja.style.setProperty('--mu-gal-alto', tam.alto + 'px');

        var abrir = crear(doc, 'button', 'mu-gal-item-abrir');
        abrir.type = 'button';
        abrir.setAttribute('aria-label', String(item.etiqueta || item.titulo || ('Imagen ' + (i + 1))));
        var img = crear(doc, 'img');
        img.setAttribute('loading', 'lazy');
        img.setAttribute('alt', '');
        img.setAttribute('src', String(item.thumb || item.url || ''));
        abrir.appendChild(img);
        abrir.addEventListener('click', function () {
            if (typeof o.onAbrir === 'function') { o.onAbrir(i, item); }
        });
        caja.appendChild(abrir);

        if (slots.etiqueta && item.etiqueta) {
            caja.appendChild(crear(doc, 'span', 'mu-gal-item-etiqueta', item.etiqueta));
        }

        if (slots.favorito) {
            var est = crear(doc, 'button', 'mu-gal-item-favorito', item.favorito ? '\u2605' : '\u2606');
            est.type = 'button';
            est.setAttribute('aria-pressed', item.favorito ? 'true' : 'false');
            est.setAttribute('aria-label', item.favorito ? 'Quitar de favoritos' : 'Marcar como favorito');
            est.addEventListener('click', function (e) {
                if (e.stopPropagation) { e.stopPropagation(); }
                if (typeof o.onFavorito === 'function') { o.onFavorito(i, !item.favorito, item); }
            });
            caja.appendChild(est);
        }

        if (slots.eliminar && typeof o.onEliminar === 'function') {
            var del = crear(doc, 'button', 'mu-gal-item-eliminar');
            del.type = 'button';
            del.setAttribute('aria-label', 'Quitar ' + (item.titulo || ('imagen ' + (i + 1))));
            del.appendChild(crearTacho(doc));
            del.addEventListener('click', function (e) {
                if (e.stopPropagation) { e.stopPropagation(); }
                var seguir = true;
                if (typeof o.confirmar === 'function') {
                    seguir = !!o.confirmar(i, item);
                } else if (o.confirmar !== false && typeof global.confirm === 'function') {
                    seguir = global.confirm('Quitar esta imagen?');
                }
                if (seguir) { o.onEliminar(i, item); }
            });
            caja.appendChild(del);
        }

        var cuerpo = null;
        function asegurarCuerpo() {
            if (!cuerpo) {
                cuerpo = crear(doc, 'div', 'mu-gal-item-cuerpo');
                caja.appendChild(cuerpo);
            }
            return cuerpo;
        }

        if (slots.titulo && item.titulo) {
            asegurarCuerpo().appendChild(crear(doc, 'p', 'mu-gal-item-titulo', item.titulo));
        }
        if (slots.descripcion && item.descripcion) {
            asegurarCuerpo().appendChild(crear(doc, 'p', 'mu-gal-item-desc', item.descripcion));
        }
        if (slots.precio && item.precio && item.precio.valor !== undefined) {
            var fila = crear(doc, 'p', 'mu-gal-item-precio');
            if (item.precio.anterior !== undefined && item.precio.anterior !== null) {
                fila.appendChild(crear(doc, 'span', 'mu-gal-item-antes',
                    '$' + formatearPrecio(item.precio.anterior)));
            }
            fila.appendChild(crear(doc, 'strong', 'mu-gal-item-ya',
                '$' + formatearPrecio(item.precio.valor)));
            if (item.precio.promo) {
                fila.appendChild(crear(doc, 'span', 'mu-gal-item-promo', item.precio.promo));
            }
            if (item.precio.aviso) {
                fila.appendChild(crear(doc, 'span', 'mu-gal-item-aviso', 'actualizado'));
            }
            asegurarCuerpo().appendChild(fila);
        }
        if (slots.estado && i === activo) {
            asegurarCuerpo().appendChild(crear(doc, 'p', 'mu-gal-item-estado', 'Elegida'));
        }
        if (slots.acciones && Array.isArray(item.acciones)) {
            var acc = crear(doc, 'div', 'mu-gal-item-acciones');
            item.acciones.forEach(function (a) {
                var b = crear(doc, 'button', 'mu-gal-item-btn', a.etiqueta || '');
                b.type = 'button';
                b.addEventListener('click', function (e) {
                    if (e.stopPropagation) { e.stopPropagation(); }
                    if (typeof a.onAccion === 'function') { a.onAccion(i, item); }
                });
                acc.appendChild(b);
            });
            if (acc.firstChild) { asegurarCuerpo().appendChild(acc); }
        }

        celda.appendChild(caja);
        return celda;
    }

    /** Hueco a llenar (obligatorio o invitacion). */
    function construirHueco(doc, i, total, o, tam) {
        var b = crear(doc, 'button', 'mu-gal-celda mu-gal-celda--vacia', '+');
        b.type = 'button';
        b.style.setProperty('--mu-gal-tam', tam.ancho + 'px');
        b.setAttribute('data-mu-gal-ranura', String(i));
        b.setAttribute('aria-label',
            'Agregar ' + (o.sustantivoSingular || 'imagen') + ' ' + (i + 1) + (total ? (' de ' + total) : ''));
        b.addEventListener('click', function () {
            if (typeof o.onRanura === 'function') { o.onRanura(i, total); }
        });
        return b;
    }

    /** Chip "+X" como CELDA de la linea (nunca flota fuera). */
    function construirChip(doc, cuantos, desde, o, tam) {
        var b = crear(doc, 'button', 'mu-gal-celda mu-gal-celda--chip', '+' + cuantos);
        b.type = 'button';
        b.style.setProperty('--mu-gal-tam', tam.ancho + 'px');
        b.setAttribute('data-mu-gal-chip', String(cuantos));
        b.setAttribute('aria-label', 'Ver ' + cuantos + ' mas');
        b.addEventListener('click', function () {
            if (typeof o.onChip === 'function') { o.onChip(cuantos, desde); return; }
            if (o.chipAccion === 'cargar' && typeof o.onRanura === 'function') {
                o.onRanura(desde, 0);
                return;
            }
            if (typeof o.onAbrir === 'function') { o.onAbrir(desde); }
        });
        return b;
    }

    /** Estado por contenedor, para repintar al cambiar el ancho. */
    var colecciones = (typeof WeakMap === 'function') ? new WeakMap() : null;
    var observadorColecciones = null;

    /** Flechas `.mu-nav-btn` de una linea deslizable: solo si desborda y en su extremo. */
    function flechasDeLinea(caja, linea) {
        var doc = global.document;
        var prev = crear(doc, 'button', 'mu-nav-btn prev mu-gal-prev');
        prev.type = 'button';
        prev.setAttribute('aria-label', 'Anterior');
        prev.appendChild(crearChevron(doc, 'prev'));
        var next = crear(doc, 'button', 'mu-nav-btn next mu-gal-next');
        next.type = 'button';
        next.setAttribute('aria-label', 'Siguiente');
        next.appendChild(crearChevron(doc, 'next'));

        function pintar() {
            var sobra = linea.scrollWidth - linea.clientWidth;
            var desborda = sobra > 1;
            prev.hidden = !(desborda && linea.scrollLeft > 1);
            next.hidden = !(desborda && linea.scrollLeft < sobra - 1);
        }

        prev.addEventListener('click', function () {
            linea.scrollBy({ left: -(linea.clientWidth * 0.8), behavior: 'smooth' });
        });
        next.addEventListener('click', function () {
            linea.scrollBy({ left: (linea.clientWidth * 0.8), behavior: 'smooth' });
        });
        linea.addEventListener('scroll', pintar, { passive: true });
        if (global.addEventListener) { global.addEventListener('resize', pintar); }
        caja.appendChild(prev);
        caja.appendChild(next);
        pintar();
        return { pintar: pintar };
    }

    /**
     * Galeria embebida (ambito 1). Contrato `coleccion.md`.
     * @returns {{visiblesItems:number, huecos:number, chip:number, modo:string, desliza:boolean}}
     */
    function coleccion(nodo, opciones) {
        var doc = global.document;
        if (!doc || !nodo) { return null; }
        var o = opciones || {};
        var items = Array.isArray(o.items) ? o.items : [];
        var tam = medidas(o.tamano);
        var gap = parseInt(o.gap, 10);
        if (!(gap >= 0)) { gap = 8; }
        var layout = (o.layout === 'grilla') ? 'grilla' : 'fila';
        var filas = Math.max(0, parseInt(o.filas, 10) || 0);
        var columnas = Math.max(0, parseInt(o.columnas, 10) || 0);
        var tope = Math.max(0, parseInt(o.tope, 10) || 0);
        var min = Math.max(0, parseInt(o.min, 10) || 0);
        var capacidad = Math.max(0, parseInt(o.capacidad, 10) || 0);
        var activo = (o.activo === undefined) ? -1 : (parseInt(o.activo, 10) || 0);
        var slots = resolverSlots(o);

        // Cuantas celdas entran: manda el CONTENEDOR, nunca el viewport.
        var ancho = nodo.clientWidth || 0;
        var cabenLinea = caben(ancho, tam.ancho, gap);
        var desliza = false;
        var topeEfectivo = tope;

        if (layout === 'fila') {
            if (o.deslizable === true) {
                desliza = true;
            } else if (o.deslizable === false) {
                desliza = false;
            } else {
                desliza = ((items.length + 1) > cabenLinea);   // 'auto'
            }
            if (!desliza) {
                topeEfectivo = (tope > 0) ? Math.min(tope, cabenLinea) : cabenLinea;
            }
        } else if (filas > 0) {
            var porFila = (columnas > 0) ? columnas : cabenLinea;
            var cabenGrilla = porFila * filas;
            topeEfectivo = (tope > 0) ? Math.min(tope, cabenGrilla) : cabenGrilla;
        }

        var res = celdas({ cargadas: items.length, min: min, capacidad: capacidad, tope: topeEfectivo });

        var pieTxt = '';
        if (o.pie !== false) {
            if (typeof o.pie === 'string' && o.pie !== 'auto') {
                pieTxt = o.pie;
            } else {
                pieTxt = pie({
                    cargadas: items.length, min: min, capacidad: capacidad,
                    sustantivo: o.sustantivo, sustantivoSingular: o.sustantivoSingular
                });
            }
        }

        var scrollPrevio = nodo.scrollLeft || 0;
        vaciar(nodo);
        nodo.setAttribute('data-mu-gal-layout', layout);

        if (pieTxt) {
            var p = crear(doc, 'p', 'mu-gal-pie', pieTxt);
            p.setAttribute('role', 'status');
            nodo.appendChild(p);
        }

        var modoClase = (layout === 'grilla') ? 'grilla' : (desliza ? 'linea' : 'fila');
        var linea = crear(doc, 'div', 'mu-gal-linea mu-gal-linea--' + modoClase);
        linea.style.setProperty('--mu-gal-gap', gap + 'px');
        if (layout === 'grilla' && filas > 0) {
            linea.setAttribute('data-filas', String(filas));
            linea.style.setProperty('--filas', String(filas));
        }
        if (layout === 'grilla' && columnas > 0) {
            linea.setAttribute('data-columnas', String(columnas));
            linea.style.setProperty('--columnas', String(columnas));
        }

        var visibles = items.slice(0, res.visiblesItems);
        visibles.forEach(function (it, i) {
            linea.appendChild(construirCelda(doc, it, i, o, activo, slots, tam));
        });
        for (var h = 0; h < res.huecos; h++) {
            linea.appendChild(construirHueco(doc, visibles.length + h, min || capacidad, o, tam));
        }
        if (res.chip > 0) {
            linea.appendChild(construirChip(doc, res.chip, res.visiblesItems, o, tam));
        }

        if (desliza) {
            var caja = crear(doc, 'div', 'mu-gal-caja-linea');
            caja.appendChild(linea);
            nodo.appendChild(caja);
            flechasDeLinea(caja, linea);
        } else {
            nodo.appendChild(linea);
        }
        nodo.scrollLeft = scrollPrevio;

        if (colecciones) {
            colecciones.set(nodo, { opciones: o, items: items, ancho: ancho });
            observarColeccion(nodo);
        }
        return {
            visiblesItems: res.visiblesItems, huecos: res.huecos, chip: res.chip,
            modo: modoClase, desliza: desliza
        };
    }

    /** Repinta al cambiar el ancho del contenedor (conserva el estado de scroll). */
    function observarColeccion(nodo) {
        if (!colecciones || typeof global.ResizeObserver !== 'function') { return; }
        var est = colecciones.get(nodo);
        if (!est) { return; }
        if (!observadorColecciones) {
            observadorColecciones = new global.ResizeObserver(function (entradas) {
                for (var i = 0; i < entradas.length; i++) {
                    var destino = entradas[i].target;
                    var s = colecciones.get(destino);
                    if (!s || destino.clientWidth === s.ancho) { continue; }
                    coleccion(destino, s.opciones);
                }
            });
        }
        observadorColecciones.observe(nodo);
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
        coleccion: coleccion,
        puro: { acotar: acotar, preCargar: preCargar, orden: orden,
            formatearPrecio: formatearPrecio, celdas: celdas, pie: pie, caben: caben },
        version: '1.0.0'
    };

    global.MUGaleria = api;

    // Dos ambitos con nombre propio (contrato `coleccion.md`, C0):
    //   MU.coleccion = galeria embebida;  MU.visor = modal de ampliacion.
    global.MU = {
        coleccion: coleccion,
        visor: visor,
        cerrar: cerrar,
        arrastrar: arrastrar,
        navegarWoo: navegarWoo,
        puro: api.puro,
        presets: PRESETS,
        tamanos: TAMANOS,
        version: api.version
    };
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