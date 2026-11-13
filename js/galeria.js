/**
 * Muy Unicos — Galeria compartida del tema (spec 019)
 *
 * UN solo componente de galeria para todo el sitio. Tres piezas:
 *   - visor   : modal accesible (reusa `.mu-modal-overlay` / `.mu-modal-container`, D2)
 *   - (la TIRA v1 se retiro en la spec 020, T013: su ambito es `MU.coleccion`)
 *   - coleccion: la galeria embebida (ambito 1) reemplaza a la vieja v1 (tira/tarjeta)
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
 * Version: 1.11.0
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

    /** Valores distintos de un campo, en orden de aparicion (sin vacios ni repetidos). */
    function valoresFiltro(items, campo) {
        var out = [];
        var vistos = {};
        (items || []).forEach(function (it) {
            var v = it ? it[campo] : undefined;
            if (v === undefined || v === null || v === '') { return; }
            var k = String(v);
            if (vistos[k]) { return; }
            vistos[k] = 1;
            out.push(k);
        });
        return out;
    }

    /** Items que pasan el filtro. 'todos' (o sin campo) devuelve la lista entera. */
    function filtrar(items, campo, valor) {
        var lista = Array.isArray(items) ? items : [];
        if (!campo || valor === undefined || valor === null || String(valor) === 'todos') {
            return lista;
        }
        var v = String(valor);
        return lista.filter(function (it) {
            return !!it && String(it[campo]) === v;
        });
    }

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

    /**
     * Lienzo representativo de una lista de imagenes (contrato `galeria.md`, C1.1).
     *
     * El visor de escritorio NO adivina su tamano: mide el item PROMEDIO y lo fija una
     * vez por apertura. Que la caja cambie de tamano en cada foto es justo lo que se
     * evita (salta la UI y descoloca las flechas).
     *
     * Como se combina: `aspecto` = media de `w/h`; `area` = media de `w*h`. El
     * rectangulo representativo es `h = sqrt(area / aspecto)`, `w = aspecto * h`: la
     * media GEOMETRICA respeta tamano y forma (3x4 + 4x3 dan un cuadrado, el promedio
     * real). Sin medidas se asume el clasico 4:3.
     *
     * Se acota a lo DISPONIBLE con `contain` (nunca deforma) y a los minimos de
     * escritorio. Mismo input -> mismo output: no depende de la foto que se este viendo.
     *
     * @param {Array} items items del visor; usan `w`/`h` en px si los declaran
     * @param {{ancho:number, alto:number, minimoAncho:number, minimoAlto:number}} disponible
     * @returns {{w:number, h:number, aspecto:number, medidas:number, acotado:boolean}}
     */
    function lienzo(items, disponible) {
        var d = disponible || {};
        var maxW = Math.max(1, parseInt(d.ancho, 10) || 0);
        var maxH = Math.max(1, parseInt(d.alto, 10) || 0);
        var minW = Math.max(0, parseInt(d.minimoAncho, 10) || 0);
        var minH = Math.max(0, parseInt(d.minimoAlto, 10) || 0);

        var sumaAspecto = 0;
        var sumaArea = 0;
        var medidas = 0;
        (items || []).forEach(function (it) {
            var w = parseFloat(it && it.w);
            var h = parseFloat(it && it.h);
            if (!(w > 0) || !(h > 0)) { return; }
            sumaAspecto += w / h;
            sumaArea += w * h;
            medidas += 1;
        });

        var aspecto = (medidas > 0) ? (sumaAspecto / medidas) : (4 / 3);
        var aW;
        var aH;
        if (medidas > 0) {
            aH = Math.sqrt(sumaArea / medidas / aspecto);
            aW = aspecto * aH;
        } else {
            aW = maxW;
            // Sin medidas: 4:3 y el ancho NO se toma a ciegas. Si el alto manda (ventana
            // apaisada o baja) el lienzo sale de `maxH * aspecto`; `contener()` recortaba
            // a lo mismo, pero explicito ata el resultado a la VENTANA (C1.1, V4).
            aW = Math.min(aW, maxH * aspecto);
            aH = aW / aspecto;
        }

        var acotado = false;
        var contener = function () {
            if (maxW > 0 && aW > maxW) { aH = aH * (maxW / aW); aW = maxW; acotado = true; }
            if (maxH > 0 && aH > maxH) { aW = aW * (maxH / aH); aH = maxH; acotado = true; }
        };
        contener();
        // Minimos de escritorio: se respetan salvo que no quepan en lo disponible.
        if (minH > 0 && aH < minH) { aW = aW * (minH / aH); aH = minH; }
        if (minW > 0 && aW < minW) { aH = aH * (minW / aW); aW = minW; }
        contener();

        // `llenar` (modo `completo`): el lienzo CRECE hasta tocar lo disponible
        // conservando el aspecto promedio, en vez de quedarse en el tamano promedio.
        if (d.llenar && isFinite(aW) && isFinite(aH) && aW > 0 && aH > 0) {
            var k = Math.min((maxW > 0 ? maxW / aW : Infinity), (maxH > 0 ? maxH / aH : Infinity));
            if (isFinite(k) && k > 1) {
                aW = aW * k;
                aH = aH * k;
                acotado = false;
                contener();
            }
        }

        return {
            w: Math.max(1, Math.round(aW)),
            h: Math.max(1, Math.round(aH)),
            aspecto: aspecto,
            medidas: medidas,
            acotado: acotado
        };
    }

/**
     * Cota y ventana de la grilla (contrato `coleccion.md`, C9).
     *
     * El chip existe SOLO si la vista esta ACOTADA. La cota sale de tres fuentes, en
     * este orden: `filas` (manda), `alto` declarado, o un marco que RECORTA de verdad
     * (su contenido no entra). Sin ninguna, la grilla crece con el contenido: se pintan
     * TODAS las filas y no hay chip (`navegable:'auto'` equivale a `true`).
     *
     * `alto` es la ventana TOTAL del bloque (pie incluido), asi que el pie se resta
     * siempre: por eso el marco nunca desborda el alto declarado.
     *
     * `filas` es una cota DECLARADA y basta por si sola: su presupuesto es
     * `filas * altoCelda + gaps` de MARCO (+ el pie del bloque) y NO se toma del marco
     * medido, que es justo lo que esta misma galeria acaba de recortar. Tomarlo del
     * marco clava la vista en una fila (`alto:0, filas:3, marco:246` daba `porAlto 1`).
     * Con `alto` declarado ademas, gana el menor de los dos (el bloque manda).
     *
     * @param {{alto:number, filas:number, altoCelda:number, gap:number, pie:number,
     *          marco:number, acota:boolean}} o
     * @returns {{acota:boolean, porAlto:number, altoMarco:number, motivo:string}}
     */
    function ventana(o) {
        o = o || {};
        var alto = Math.max(0, parseInt(o.alto, 10) || 0);
        var filas = Math.max(0, parseInt(o.filas, 10) || 0);
        var celda = Math.max(1, parseInt(o.altoCelda, 10) || 1);
        var gap = Math.max(0, parseInt(o.gap, 10) || 0);
        var pie = Math.max(0, parseInt(o.pie, 10) || 0);
        var marco = Math.max(0, parseInt(o.marco, 10) || 0);
        var recorta = !!o.acota;

        // Alto de MARCO que declaran las `filas` (sin pie): es una cota absoluta.
        var altoFilas = (filas > 0) ? (filas * celda + (filas - 1) * gap) : 0;
        // Presupuesto TOTAL del bloque: el declarado por `alto`, el que piden las
        // `filas` (+ su pie), o el marco SI recorta de verdad. El marco medido NUNCA
        // decide cuando hay cota declarada.
        var base = (alto > 0) ? alto
            : ((filas > 0) ? (altoFilas + pie) : (recorta ? marco : 0));
        var disp = base;
        if (disp > 0) { disp = Math.max(0, disp - pie); }

        // Filas de celdas que entran (0 = sin presupuesto: se ven todas).
        var porAlto = (disp > 0) ? Math.max(1, Math.floor((disp + gap) / (celda + gap))) : 0;
        var altoMarco = disp;
        if (filas > 0) {
            porAlto = (porAlto > 0) ? Math.min(filas, porAlto) : filas;
            // `altoFilas` ya se calculo arriba desde la cota declarada.
            altoMarco = (altoMarco > 0) ? Math.min(altoMarco, altoFilas) : altoFilas;
        }
        var motivo = (filas > 0) ? 'filas' : ((alto > 0) ? 'alto' : (recorta ? 'marco' : 'libre'));
        return { acota: (porAlto > 0), porAlto: porAlto, altoMarco: altoMarco, motivo: motivo };
    }
    /**
     * `navegable` (alias historico `deslizable`): 'auto' | true | false.
     *
     * - 'auto'  -> arranca FIJA (entra lo que cabe) y el chip SUMA una pantalla mas;
     *              en cuanto lo revelado no cabe, pasa a navegable sola.
     * - true    -> revela todo (hasta el `tope`) y es navegable de entrada.
     * - false   -> nunca navega: el chip abre el visor, no acumula.
     *
     * @param {Object} o opciones del consumidor
     * @returns {boolean|string} 'auto' | true | false
     */
    function navegableDe(o) {
        var v = (o.navegable !== undefined && o.navegable !== null)
            ? o.navegable : o.deslizable;
        if (v === true || v === 'si' || v === 'true') { return true; }
        if (v === false || v === 'no' || v === 'false') { return false; }
        return 'auto';
    }

    /**
     * Carga acumulativa del chip (contrato `coleccion.md`, C1bis).
     *
     * El chip SUMA una pantalla de celdas: lo revelado NUNCA se oculta. La vista fija
     * entra en `capacidad` (descontando los huecos, que van primero, y la celda del
     * propio chip). `navega` es DERIVADO: en cuanto lo revelado no cabe en la vista,
     * la galeria pasa a navegable. `agotado` = se alcanzo el `tope` y AUN quedan
     * imagenes: ahi el chip deja de sumar y abre el visor.
     *
     * El PASO de una tanda es `caben` (la pantalla completa de celdas), no lo que queda
     * despues de descontar huecos y chip: `caben` es multiplo del numero de columnas, asi
     * que cada tanda conserva el residuo y el chip cierra fila (`celdas % columnas === 0`).
     * Un chip "+1" no aporta (ocupa la celda del item que oculta): si el item entra en el
     * `tope`, se muestra y el chip desaparece.
     *
     * El `tope` llega ya materializado en FILAS COMPLETAS por `topeEnFilas`, asi que las
     * celdas pintadas siguen siendo multiplos del numero de columnas.
     *
     * El `tope` es de **CELDAS**, igual que en `celdas` (C1): el chip
     * (`mu-gal-celda--chip`) y los huecos (`mu-gal-celda--vacia`) que se ven ocupan
     * lugar dentro de el. Con `tope 4`, 2 huecos y 5 imagenes se ve `1 + 2 + chip = 4`.
     *
     * El estado que se conserva es `tandas` (cuantas pantallas pidio el usuario), NO un
     * numero absoluto: asi, si la vista cambia de capacidad (resize, filas, alto, o la
     * medicion real de la celda), lo revelado se re-ajusta solo a la vista nueva.
     *
     * @param {{items:number, tope:number, caben:number, huecos:number,
     *          tandas:number}} o
     * @returns {{capacidad:number, tandas:number, revelados:number, visibles:number,
     *            ocultas:number, paso:number, siguiente:number, navega:boolean,
     *            agotado:boolean, topeCeldas:number, topeItems:number,
     *            hayChip:boolean}}
     */
    function carga(o) {
        o = o || {};
        var n = Math.max(0, parseInt(o.items, 10) || 0);
        var tope = Math.max(0, parseInt(o.tope, 10) || 0);
        var cabenCtx = Math.max(1, parseInt(o.caben, 10) || 1);
        var huecos = Math.max(0, parseInt(o.huecos, 10) || 0);

        // Celdas de IMAGEN que quedan si el chip no existiera (los huecos van primero).
        var capItemsSinChip = Math.max(1, cabenCtx - huecos);
        var topeItemsSinChip = (tope > 0) ? Math.max(0, tope - huecos) : Infinity;

        // El chip existe si quedaran imagenes ocultas; y ocupa una celda de ambos.
        var hayChip = (n > Math.min(capItemsSinChip, topeItemsSinChip));
        var capacidad = Math.max(1, capItemsSinChip - (hayChip ? 1 : 0));
        var topeItems = (tope > 0)
            ? Math.max(0, topeItemsSinChip - (hayChip ? 1 : 0))
            : Infinity;

        var tandas = Math.max(0, parseInt(o.tandas, 10) || 0);
        var minimo = Math.min(capacidad, topeItems, n);
        // El paso de una tanda es una PANTALLA de celdas (`caben`), NO lo que queda
        // despues de descontar huecos y chip: `caben` es multiplo del numero de
        // columnas, asi que cada tanda conserva el residuo y el chip cierra fila.
        var paso = cabenCtx;
        var visibles = Math.min(n, topeItems, Math.max(minimo, capacidad + paso * tandas));
        var ocultas = Math.max(0, n - visibles);
        // Un chip "+1" no aporta: reserva la MISMA celda que el item que oculta. Se
        // muestra el item (solo puede pasar con tandas > 0, o sea que ya hay scroll)
        // salvo que el `tope` de celdas no lo deje entrar; ahi el chip si hace falta.
        if (ocultas === 1 && (visibles + 1) <= topeItems) {
            visibles += 1;
            ocultas = 0;
        }

        return {
            capacidad: capacidad, tandas: tandas,
            revelados: visibles, visibles: visibles,
            ocultas: ocultas, paso: paso,
            siguiente: Math.min(topeItems, visibles + paso),
            navega: (visibles > capacidad),
            agotado: (visibles >= Math.min(topeItems, n) && ocultas > 0),
            topeCeldas: tope, topeItems: topeItems, hayChip: (ocultas > 0)
        };
    }

    /**
     * Materializa un `tope` de CELDAS en FILAS COMPLETAS (contrato `coleccion.md`, C1ter).
     *
     * Un tope que no es multiplo del numero de columnas deja celdas sueltas: el chip cae a
     * media fila. Como `caben` en grilla ya es `porFila * porAlto` (multiplo), redondear el
     * tope hacia abajo garantiza `celdas % porFila === 0` en TODAS las tandas. Si el tope no
     * alcanza para una fila (`tope < porFila`) se conserva tal cual: redondear hacia arriba
     * lo excederia y el `tope` es un MAXIMO.
     *
     * @param {number} tope    celdas declaradas (0 = sin tope)
     * @param {number} porFila columnas de la grilla (0 = sin dato)
     * @returns {number} tope efectivo en celdas
     */
    function topeEnFilas(tope, porFila) {
        var t = Math.max(0, parseInt(tope, 10) || 0);
        var c = Math.max(0, parseInt(porFila, 10) || 0);
        if (t === 0 || c <= 0 || t < c) { return t; }
        return Math.floor(t / c) * c;
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

    /**
     * Agrega un parametro de cache a una url: es lo unico que hace util el boton
     * "Reintentar" del visor, porque el navegador cachea el fallo de la misma url.
     */
    function conCache(url) {
        var u = String(url || '');
        if (!u) { return u; }
        return u + (u.indexOf('?') === -1 ? '?' : '&') + 'r=' + Date.now();
    }

/**
     * Iconos de accion (V2, T042): misma familia que el chevron y el tachito, todos con
     * `createElementNS` porque el componente no usa `innerHTML`. NO es un set del tema:
     * son los cuatro nombres que el visor necesita, y el texto del boton sigue mandando
     * (el icono es decorativo: `aria-hidden`).
     */
    var ICONOS = {
        usar: 'M20 6L9 17l-5-5',
        descargar: 'M12 4v10M7 9l5 5 5-5M5 20h14',
        corazon: 'M12 20s-7-4.6-7-9.6A4.4 4.4 0 0112 7a4.4 4.4 0 017 3.4c0 5-7 9.6-7 9.6z',
        basura: 'M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14'
    };

    /**
     * Icono de accion por nombre conocido; `null` si el nombre no existe, y entonces el
     * boton queda solo con su etiqueta (un nombre desconocido no rompe la accion).
     */
    function crearIcono(doc, nombre) {
        if (!Object.prototype.hasOwnProperty.call(ICONOS, nombre)) { return null; }
        var ns = 'http://www.w3.org/2000/svg';
        var svg = doc.createElementNS(ns, 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '2');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        svg.setAttribute('width', '18');
        svg.setAttribute('height', '18');
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('focusable', 'false');
        var camino = doc.createElementNS(ns, 'path');
        camino.setAttribute('d', ICONOS[nombre]);
        svg.appendChild(camino);
        return svg;
    }

    /**
     * Mide el tamano de cada item para el lienzo promedio (contrato C1.1).
     *
     * Si el item declara `w`/`h` se usan tal cual. Si no, se busca en el DOM una `<img>`
     * que apunte a la MISMA url y se lee `naturalWidth/naturalHeight`: la galeria de Woo
     * ya tiene esas imagenes pintadas, asi que medir no cuesta una descarga.
     * Devuelve una lista paralela a `items` (nunca muta los items del consumidor).
     */
    function mismaUrl(srcs, url) {
        var limpio = function (u) { return String(u || '').split('#')[0].split('?')[0]; };
        var buscada = limpio(url);
        if (!buscada) { return false; }
        for (var i = 0; i < srcs.length; i++) {
            var s = limpio(srcs[i]);
            if (!s) { continue; }
            if (s === buscada) { return true; }
            // Woo agrega sufijos de recorte (`-300x300`) a la miniatura del MISMO archivo.
            if (s.indexOf(buscada) === 0 || buscada.indexOf(s) === 0) { return true; }
        }
        return false;
    }

    function medirItems(items) {
        var doc = global.document;
        var imgs = (doc && doc.querySelectorAll) ? doc.querySelectorAll('img') : [];
        return (items || []).map(function (it) {
            var w = parseFloat(it && it.w);
            var h = parseFloat(it && it.h);
            if ((w > 0) && (h > 0)) { return { w: w, h: h }; }
            var url = String((it && (it.url || it.thumb)) || '');
            if (!url || !imgs.length) { return null; }
            for (var i = 0; i < imgs.length; i++) {
                var im = imgs[i];
                var srcs = [im.getAttribute('src'), im.getAttribute('data-large_image'),
                    im.getAttribute('data-src'), im.currentSrc, im.getAttribute('data-thumb')];
                if (!mismaUrl(srcs, url)) { continue; }
                if (im.naturalWidth > 0 && im.naturalHeight > 0) {
                    return { w: im.naturalWidth, h: im.naturalHeight };
                }
            }
            return null;
        });
    }

    /* ==================== visor (modal del tema) ==================== */

    /**
     * Tema visual del contexto (spec 020, D2): `opts.tema` -> `closest('[data-mu-tema]')`
     * -> `base`. Es **solo presentacion**: el tema vive en `galeria-temas.css` y no
     * agrega opciones ni datos (D1/D4).
     *
     * @param {Element|null} el elemento desde el que se hereda (contenedor o activo)
     * @param {Object} [opts] opciones del consumidor (atajo `tema`)
     * @returns {string} nombre del tema, nunca vacio
     */
    function temaDe(el, opts) {
        if (opts && opts.tema) { return String(opts.tema); }
        if (el && typeof el.closest === 'function') {
            var c = el.closest('[data-mu-tema]');
            if (c) { return String(c.getAttribute('data-mu-tema') || 'base'); }
        }
        return 'base';
    }

    /** Topes del modo `responsive` (contrato `galeria.md`, C1.1). */
    var CAJA_TOPE = 880;
    var CAJA_TOPE_AMPLIO = 1400;
    /** Cromo lateral de la caja: 2 flechas de 44 px + gaps + paddings del cuerpo. */
    var CROMO_LATERAL = 140;
    /** Suelo del alto del lienzo: por debajo el marco no se lee (C1.1, V4). */
    var ALTO_MINIMO = 120;

    /** Ventana adaptativa (`<= 600px`): manda el `@media` a pantalla completa. */
    function esAdaptativo() {
        var doc = global.document;
        var w = global.innerWidth
            || (doc && doc.documentElement ? doc.documentElement.clientWidth : 0) || 0;
        return !(w > 600);
    }

    /**
     * Fija el LIENZO del visor de escritorio desde el item PROMEDIO y lo deja fijo
     * (`--mu-gal-lienzo-w/h` en la caja, contrato C1.1, modo `responsive`).
     *
     * Se llama al montar, al cambiar los items y al redimensionar. NUNCA al navegar.
     * Antes la caja era `fit-content`: el cuerpo media su min-content, el marco
     * desbordaba y las flechas se salian de la visualizacion (franja 811-820 px).
     *
     * @returns {{w:number, h:number}|null} lienzo aplicado (null en modo adaptativo)
     */
    function aplicarLienzo() {
        var r = estado.raiz;
        if (!r) { return null; }
        var doc = global.document;
        var caja = r.querySelector('.mu-gal-caja');
        if (!caja) { return null; }

        sinClase(caja, 'mu-gal-caja--fija');
        caja.style.removeProperty('--mu-gal-lienzo-w');
        caja.style.removeProperty('--mu-gal-lienzo-h');
        estado.lienzo = null;
        if (esAdaptativo() || !doc) { return null; }

        var o = estado.opciones || {};
        var amplio = (o.ancho === 'completo');
        var vw = global.innerWidth || doc.documentElement.clientWidth || 0;
        var vh = global.innerHeight || doc.documentElement.clientHeight || 0;
        var topeAncho = Math.min(vw * (amplio ? 0.96 : 0.92),
            amplio ? CAJA_TOPE_AMPLIO : CAJA_TOPE);
        // La caja ENTERA tiene que caber en 92vh (su `max-height`): el alto del lienzo es la
        // ventana menos el cromo VERTICAL medido (C1.1, V4). Antes era `vh * 0.62` a ojo:
        // en ventanas de 768 px la caja desbordaba, el marco empujaba la banda y la meta
        // fuera de la caja y el `overflow: hidden` las cortaba.
        var altoLienzo = Math.min(Math.max(ALTO_MINIMO, (vh * 0.92) - cromoVertical(caja)), 720);
        var l = lienzo(estado.medidas, {
            ancho: topeAncho - CROMO_LATERAL,
            alto: altoLienzo,
            minimoAncho: 320,
            minimoAlto: Math.min(220, altoLienzo),
            llenar: amplio
        });
        caja.style.setProperty('--mu-gal-lienzo-w', l.w + 'px');
        caja.style.setProperty('--mu-gal-lienzo-h', l.h + 'px');
        if (caja.className.indexOf('mu-gal-caja--fija') === -1) {
            caja.className += ' mu-gal-caja--fija';
        }
        estado.lienzo = l;
        return l;
    }

    /**
     * Monta (o RE-monta) las pestañas informativas: nunca filtran (C1.2). Se llama en el
     * montaje y desde `actualizar()`, asi que borra las previas antes de pintar.
     */
    function montarSegmentos(doc, caja, o) {
        var previo = caja.querySelector('.mu-gal-segs');
        if (previo) { previo.parentNode.removeChild(previo); }
        if (!Array.isArray(o.segmentos) || !o.segmentos.length) { return; }
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
        // Van entre la cabecera y el cuerpo, tanto al montar como al re-montar.
        var cuerpo = caja.querySelector('.mu-gal-cuerpo');
        if (cuerpo) { caja.insertBefore(segs, cuerpo); } else { caja.appendChild(segs); }
    }

    /** Monta (o RE-monta) los botones de accion al pie de la caja (V2, T042/T044). */
    function montarAcciones(doc, caja, o) {
        var previo = caja.querySelector('.mu-gal-acciones');
        if (previo) { previo.parentNode.removeChild(previo); }
        var lista = (Array.isArray(o.acciones) ? o.acciones : []);
        if (!lista.length) { return; }
        var acciones = crear(doc, 'div', 'mu-gal-acciones');
        lista.forEach(function (ac) {
            var b = crear(doc, 'button', 'mu-gal-accion ' + (ac.clase || ''));
            var iconoAc = ac.icono ? crearIcono(doc, String(ac.icono)) : null;
            if (iconoAc) { b.appendChild(iconoAc); }
            // El texto manda: el icono es decorativo. Sin etiqueta el boton queda solo con
            // el icono, y entonces el nombre accesible NO puede faltar (`aria-label`).
            if (ac.etiqueta) {
                b.appendChild(doc.createTextNode(String(ac.etiqueta)));
            } else {
                b.setAttribute('aria-label', String(ac.aria || ac.icono || 'Accion'));
            }
            b.type = 'button';
            b.addEventListener('click', function () {
                if (typeof ac.onAccion === 'function') { ac.onAccion(estado.pos); }
            });
            acciones.appendChild(b);
        });
        caja.appendChild(acciones);
    }

    /**
     * Cromo VERTICAL de la caja: todo lo que NO es el marco (contrato C1.1, V4).
     *
     * Suma lo que ocupa alto FUERA del marco: cabecera, segmentos, padding vertical
     * del cuerpo, el pie (banda + meta) con su alto NATURAL, acciones, gaps entre
     * hijos y bordes. El pie entra a proposito: si el lienzo se quedara con todo el
     * espacio, en ventanas de 768 px el scroll medía 0 px y la banda y la meta
     * desaparecian (medido).
     *
     * Se mide por PARTES (`caja - marco` dependeria del lienzo vigente) para que el
     * resultado sea el mismo input -> mismo output y no entre en bucle.
     *
     * @param {Element} caja contenedor `.mu-gal-caja`
     * @returns {number} px a restar de la ventana para el alto del lienzo (0 sin DOM)
     */
    function cromoVertical(caja) {
        var doc = global.document;
        if (!caja || !doc || typeof global.getComputedStyle !== 'function') { return 0; }
        var alto = 0;
        var suma = function (sel) {
            var el = caja.querySelector(sel);
            if (!el) { return; }
            // Un elemento FUERA de flujo no ocupa alto en el cromo: un tema puede sacar
            // las acciones con `position:absolute` (spec 020, tema `cotizador`).
            var csEl = global.getComputedStyle ? global.getComputedStyle(el) : null;
            if (csEl && csEl.position === 'absolute') { return; }
            // `offsetHeight` y no `getBoundingClientRect`: la animacion de apertura del modal
            // escala la caja (0.97 -> 1) y un rect medido a mitad mentiria (chrome corto).
            alto += el.offsetHeight || 0;
        };
        suma('.mu-gal-cabecera');
        suma('.mu-gal-segs');
        suma('.mu-gal-acciones');
        var scroll = caja.querySelector('.mu-gal-scroll');
        // Alto NATURAL del pie (`scrollHeight`): no depende de lo comprimido que este.
        if (scroll) { alto += scroll.scrollHeight; }
        var cuerpo = caja.querySelector('.mu-gal-cuerpo');
        var cs = cuerpo ? global.getComputedStyle(cuerpo) : null;
        if (cs) {
            alto += (parseFloat(cs.paddingTop) || 0) + (parseFloat(cs.paddingBottom) || 0);
        }
        var cc = global.getComputedStyle(caja);
        if (cc) {
            var gap = parseFloat(cc.rowGap);
            if (!(gap >= 0)) { gap = 0; }
            // Hijos flex de la caja: cabecera, segmentos, cuerpo, scroll y acciones.
            alto += gap * Math.max(0, caja.children.length - 1);
            alto += (parseFloat(cc.borderTopWidth) || 0) + (parseFloat(cc.borderBottomWidth) || 0);
        }
        return Math.round(alto);
    }

    /** Campos de filtro pedidos (`o.filtros`: string o lista). Vacio = sin pills. */
    function camposFiltro(o) {
        var f = (o && o.filtros) ? o.filtros : null;
        if (!f) { return []; }
        return Array.isArray(f) ? f.slice() : [f];
    }

    /** Estado inicial de filtros (`filtroActivo`: string o `{campo: valor}`). */
    function normalizarFiltro(o) {
        var out = {};
        var fa = (o && o.filtroActivo) ? o.filtroActivo : null;
        var campos = camposFiltro(o);
        if (fa && typeof fa === 'object') {
            for (var k in fa) { if (fa.hasOwnProperty(k)) { out[k] = String(fa[k]); } }
        } else if (fa && campos.length === 1) {
            out[campos[0]] = String(fa);
        }
        return out;
    }

    /** Lista del visor con los filtros aplicados (no muta la del consumidor). */
    function vistaFiltrada() {
        var lista = estado.todos || [];
        camposFiltro(estado.opciones || {}).forEach(function (campo) {
            var valor = (estado.filtro || {})[campo];
            if (valor && String(valor) !== 'todos') {
                lista = filtrar(lista, campo, valor);
            }
        });
        return lista;
    }

    /**
     * Pills de FILTRO arriba del cuerpo (spec 020 D6, contrato `visor-filtros.md`).
     * A diferencia de `segmentos`, que solo avisan (C1.2), estos RECORTAN la lista.
     */
    function montarFiltros(doc, caja, o) {
        var previo = caja.querySelector('.mu-gal-filtros');
        if (previo && previo.parentNode) { previo.parentNode.removeChild(previo); }
        var campos = camposFiltro(o);
        if (!campos.length) { return; }
        var cajaF = crear(doc, 'div', 'mu-gal-filtros');
        var montados = 0;
        campos.forEach(function (campo) {
            var valores = valoresFiltro(estado.todos || [], campo);
            if (valores.length < 2) { return; }
            var fila = crear(doc, 'div', 'mu-gal-filtros-fila');
            fila.setAttribute('data-mu-gal-filtro', campo);
            ['todos'].concat(valores).forEach(function (v) {
                var b = crear(doc, 'button', 'mu-gal-seg', (v === 'todos') ? 'Todos' : v);
                b.type = 'button';
                b.setAttribute('data-mu-gal-valor', String(v));
                var activo = String((estado.filtro || {})[campo] || 'todos') === String(v);
                b.setAttribute('aria-pressed', activo ? 'true' : 'false');
                b.addEventListener('click', function () { aplicarFiltroVisor(campo, v); });
                fila.appendChild(b);
            });
            cajaF.appendChild(fila);
            montados += 1;
        });
        if (!montados) { return; }
        var cuerpo = caja.querySelector('.mu-gal-cuerpo');
        if (cuerpo) { caja.insertBefore(cajaF, cuerpo); } else { caja.appendChild(cajaF); }
    }

    /** (Re)monta la banda de miniaturas con la lista dada (se usa al filtrar). */
    function montarListado(doc, scroll, items, o, activo) {
        var previa = scroll.querySelector('.mu-gal-visor-lista');
        if (previa && previa.parentNode) { previa.parentNode.removeChild(previa); }
        if (o.listado !== 'abajo' || items.length <= 1) { return; }
        var lista = crear(doc, 'div', 'mu-gal-visor-lista');
        if (scroll.firstChild) { scroll.insertBefore(lista, scroll.firstChild); }
        else { scroll.appendChild(lista); }
        coleccion(lista, {
            items: items.map(function (it) {
                return { url: it.thumb || it.url, etiqueta: it.etiqueta || '' };
            }),
            layout: 'fila',
            preset: 'mini',
            tamano: 'chico',
            navegable: true,
            activo: acotar(activo, items.length),
            pie: false,
            tope: 0,
            onAbrir: function (i) { ir(i); }
        });
    }

    /**
     * Aplica un filtro del visor: recalcula la vista, acota el indice, re-monta la banda
     * y los pills y repinta. `onFiltrar(campo, valor, res)` avisa al consumidor.
     * @returns {boolean} true si cambio el filtro
     */
    function aplicarFiltroVisor(campo, valor) {
        var s = estado;
        if (!s.raiz) { return false; }
        s.filtro = s.filtro || {};
        if (String(s.filtro[campo] || 'todos') === String(valor)) { return false; }
        s.filtro[campo] = String(valor);
        var doc = global.document;
        var caja = s.raiz.querySelector('.mu-gal-caja');
        var scroll = s.raiz.querySelector('.mu-gal-scroll');
        var vista = vistaFiltrada();
        s.items = vista;
        s.medidas = medirItems(vista);
        s.pos = 0;
        if (doc && caja) { montarFiltros(doc, caja, s.opciones || {}); }
        if (doc && scroll) { montarListado(doc, scroll, vista, s.opciones || {}, 0); }
        aplicarLienzo();
        pintarVisor();
        var o = s.opciones || {};
        if (typeof o.onFiltrar === 'function') {
            try { o.onFiltrar(campo, String(valor), { items: vista.length, indice: 0 }); }
            catch (eFiltro) { /* manda el consumidor */ }
        }
        return true;
    }

    var estado = {
        raiz: null, items: [], pos: 0, opciones: {}, onKey: null,
        overflowPrevio: '', focoPrevio: null, onResize: null, medidas: [], lienzo: null,
        todos: [], filtro: {}
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

    /** Tonos de badge validos (los dos ultimos los usa la biblioteca del plugin). */
    function tonoBadge(tono) {
        var t = String(tono || '').toLowerCase();
        var ok = ['tienda', 'nuevo', 'oferta', 'peligro', 'guardada', 'comprada'];
        return (ok.indexOf(t) === -1) ? 'tienda' : t;
    }

    /**
     * Normaliza la meta a UNA forma (V2, T043), aceptando la forma vieja para no romper a
     * quien ya llama asi:
     *   badge: '31% OFF' | {texto, tono}   + badgeTipo    -> {texto, tono}
     *   precio: {valor, anterior, aviso}                 -> precios: [{etiqueta, valor, anterior, aviso}]
     * Devuelve `null` si no hay nada que pintar.
     */
    function normalizarMeta(meta) {
        if (!meta || typeof meta !== 'object') { return null; }
        var out = { badge: null, titulo: '', descripcion: '', precios: [], nota: '' };

        var b = meta.badge;
        if (b && typeof b === 'object') {
            out.badge = { texto: String(b.texto || ''), tono: tonoBadge(b.tono) };
        } else if (b) {
            out.badge = { texto: String(b), tono: tonoBadge(meta.badgeTipo) };
        }
        if (out.badge && !out.badge.texto) { out.badge = null; }

        if (meta.titulo) { out.titulo = String(meta.titulo); }
        if (meta.descripcion) { out.descripcion = String(meta.descripcion); }
        if (meta.nota) { out.nota = String(meta.nota); }

        var lista = Array.isArray(meta.precios) ? meta.precios
            : ((meta.precio && typeof meta.precio === 'object') ? [meta.precio] : []);
        lista.forEach(function (p) {
            if (!p || p.valor === undefined || p.valor === null) { return; }
            out.precios.push({
                etiqueta: p.etiqueta ? String(p.etiqueta) : '',
                valor: p.valor,
                anterior: (p.anterior === undefined) ? null : p.anterior,
                aviso: p.aviso ? String(p.aviso) : ''
            });
        });
        return out;
    }

    /** Bloque de metadatos (badge tonal, titulo, descripcion, precios y nota). */
    function pintarMeta(doc, meta) {
        var caja = crear(doc, 'div', 'mu-gal-meta-caja');
        var m = normalizarMeta(meta);
        if (!m) { return caja; }
        if (m.badge) {
            caja.appendChild(crear(doc, 'span',
                'mu-gal-badge mu-gal-badge--' + m.badge.tono, m.badge.texto));
        }
        if (m.titulo) { caja.appendChild(crear(doc, 'p', 'mu-gal-meta-titulo', m.titulo)); }
        if (m.descripcion) {
            caja.appendChild(crear(doc, 'p', 'mu-gal-meta-desc', m.descripcion));
        }
        m.precios.forEach(function (p) {
            var fila = crear(doc, 'p', 'mu-gal-precio');
            if (p.etiqueta) {
                fila.appendChild(crear(doc, 'span', 'mu-gal-precio-etiqueta', p.etiqueta));
            }
            if (p.anterior !== null) {
                fila.appendChild(crear(doc, 'span', 'mu-gal-precio-antes',
                    '$' + formatearPrecio(p.anterior)));
            }
            fila.appendChild(crear(doc, 'strong', 'mu-gal-precio-ya',
                '$' + formatearPrecio(p.valor)));
            if (p.aviso) {
                fila.appendChild(crear(doc, 'span', 'mu-gal-precio-aviso', 'actualizado'));
            }
            caja.appendChild(fila);
        });
        if (m.nota) { caja.appendChild(crear(doc, 'p', 'mu-gal-nota', m.nota)); }
        return caja;
    }

    /**
     * Con las flechas al costado, la fila reserva su ancho: sin esto las flechas
     * ABSOLUTAS de `mu-gal-caja-linea` tapaban la primera y la ultima miniatura.
     * Los 36 px de la flecha + el `left: 0` se compensan aca, no en la celda.
     */
    function marcarFlechasEnLinea(caja) {
        if (!caja) { return; }
        if (caja.className.indexOf('mu-gal-caja-linea--flechas') === -1) {
            caja.className += ' mu-gal-caja-linea--flechas';
        }
    }

    /** Repinta contador, imagen, flechas, meta y precarga de la posicion actual. */
    function pintarVisor() {
        var s = estado;
        if (!s.raiz) { return; }
        var total = s.items.length;
        var item = s.items[s.pos] || {};
        var o = s.opciones || {};

        var cont = s.raiz.querySelector('.mu-gal-contador');
        if (cont) {
            // `contador: 'auto'` (default) lo oculta cuando no aporta: con un solo item
            // el "1 / 1" es ruido, no informacion. `true` lo fuerza y `false` lo apaga.
            var modoCont = (o.contador === undefined) ? 'auto' : o.contador;
            var muestraCont = (modoCont === true) || (modoCont === 'auto' && total > 1);
            cont.hidden = !muestraCont;
            cont.textContent = muestraCont ? ((s.pos + 1) + ' / ' + total) : '';
        }

        var img = s.raiz.querySelector('.mu-gal-img');
        if (img) {
            var url = String(item.url || item.thumb || '');
            if (img.getAttribute('src') !== url) {
                // Cambiar de imagen limpia el estado de error de la anterior: el `load`
                // o el `error` del `src` nuevo vuelven a decidirlo.
                var cajaErr = s.raiz.querySelector('.mu-gal-error');
                if (cajaErr) { cajaErr.hidden = true; }
                var marcoErr = s.raiz.querySelector('.mu-gal-marco');
                if (marcoErr) { sinClase(marcoErr, 'mu-gal-marco--error'); }
                img.className = 'mu-gal-img';
                img.setAttribute('src', url);
            }
            img.setAttribute('alt', String(item.etiqueta || ''));

        // El damero solo aporta si la imagen PUEDE tener transparencia: con una foto
        // opaca eran bandas grises alrededor. El item lo declara con `transparente`.
        var marcoV = s.raiz.querySelector('.mu-gal-marco');
        if (marcoV) {
            if (item.transparente) {
                if (marcoV.className.indexOf('mu-gal-marco--damero') === -1) {
                    marcoV.className += ' mu-gal-marco--damero';
                }
            } else {
                sinClase(marcoV, 'mu-gal-marco--damero');
            }
        }
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
            caja.hidden = !caja.firstChild;
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

        // Sincronizacion dos vias con el listado inferior (F2).
        marcarLista(s.pos);
    }

    /** Navega (circular) y repinta. */
    function mover(delta) {
        if (estado.items.length <= 1) { return; }
        ir(estado.pos + delta);
    }

    /**
     * Va a una posicion concreta (contrato C1: `ir(i)`). Repinta el visor y
     * sincroniza el listado inferior (activo + `scrollIntoView` nearest).
     * @returns {boolean} true si movio.
     */
    function ir(i) {
        var total = estado.items.length;
        if (!total) { return false; }
        var n = parseInt(i, 10);
        if (isNaN(n)) { return false; }
        estado.pos = ((n % total) + total) % total;
        pintarVisor();
        return true;
    }

    /** Marca la celda activa del listado inferior y la acerca sin saltar la pagina. */
    function marcarLista(pos) {
        var raiz = estado.raiz;
        if (!raiz) { return; }
        var lista = raiz.querySelector('.mu-gal-visor-lista');
        if (!lista) { return; }
        var previa = lista.querySelector('.mu-gal-celda--activa');
        if (previa) { sinClase(previa, 'mu-gal-celda--activa'); previa.removeAttribute('aria-current'); }
        var celda = lista.querySelector('[data-mu-gal-mini="' + pos + '"]');
        if (!celda) { return; }
        celda.className += ' mu-gal-celda--activa';
        // El estado va tambien en `aria-current`: el anillo es solo color.
        celda.setAttribute('aria-current', 'true');
        // Un repintado por resize reusa las opciones guardadas: sin esto volveria
        // a marcar la celda del montaje y se perderia la sincronizacion.
        var est = colecciones ? colecciones.get(lista) : null;
        if (est && est.opciones) { est.opciones.activo = pos; }
        if (typeof celda.scrollIntoView === 'function') {
            try {
                celda.scrollIntoView({ block: 'nearest', inline: 'nearest' });
            } catch (eScroll) { celda.scrollIntoView(); }
        }
    }

    /** Cierra el visor: restaura el scroll, el foco y retira el nodo. */
    function cerrar() {
        var s = estado;
        var doc = global.document;
        if (!s.raiz) { return false; }
        sinClase(s.raiz, 'is-visible');
        s.raiz.setAttribute('aria-hidden', 'true');
        if (s.onKey && doc) { doc.removeEventListener('keydown', s.onKey); }
        if (s.onResize && global.removeEventListener) {
            global.removeEventListener('resize', s.onResize);
        }
        s.onResize = null;
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
        s.medidas = [];
        s.lienzo = null;
        if (s.focoPrevio && typeof s.focoPrevio.focus === 'function') {
            try { s.focoPrevio.focus(); } catch (e) { /* el foco previo ya no existe */ }
        }
        if (typeof s.opciones.alCerrar === 'function') { s.opciones.alCerrar(); }
        s.opciones = {};
        return true;
    }

    /**
     * Actualiza el visor ABIERTO sin cerrarlo ni recrear el DOM (V2, T044): `items`,
     * `indice`, `titulo`, `meta`, `contador`, `ancho`, `segmentos` y `acciones`.
     * OJO: `listado` (la banda inferior) NO se re-monta; si cambia, cerrar y volver a abrir.
     * @returns {boolean} true si habia un visor abierto y se actualizo.
     */
    function actualizar(parcial) {
        var s = estado;
        var doc = global.document;
        if (!doc || !s.raiz) { return false; }
        var caja = s.raiz.querySelector('.mu-gal-caja');
        if (!caja) { return false; }
        var p = parcial || {};
        var o = s.opciones || {};

        if (Array.isArray(p.items)) {
            // El consumidor manda la lista COMPLETA: los filtros se re-aplican encima.
            s.todos = p.items.slice();
            s.items = vistaFiltrada();
            s.medidas = medirItems(s.items);
        }
        if (!s.items.length) { return cerrar(); }

        if (p.titulo !== undefined) {
            o.titulo = String(p.titulo || '');
            var texto = o.titulo || 'Galeria';
            var titulo = s.raiz.querySelector('.mu-gal-titulo');
            if (titulo) { titulo.textContent = texto; }
            s.raiz.setAttribute('aria-label', texto);
        }
        if (p.meta !== undefined) { o.meta = p.meta; }
        if (p.contador !== undefined) { o.contador = p.contador; }
        if (p.ancho !== undefined) { o.ancho = p.ancho; }
        if (p.segmentos !== undefined) {
            o.segmentos = p.segmentos;
            montarSegmentos(doc, caja, o);
        }
        if (p.acciones !== undefined) {
            o.acciones = p.acciones;
            montarAcciones(doc, caja, o);
        }

        s.opciones = o;
        // `ancho` y un cambio de `items` mueven el lienzo: se re-mide una vez.
        aplicarLienzo();
        s.pos = acotar((p.indice !== undefined) ? p.indice : s.pos, s.items.length);
        pintarVisor();
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
        // El visor hereda el tema del contexto que lo abrio (el boton de la miniatura
        // esta DENTRO del contenedor con `data-mu-tema`), asi tira y visor comparten
        // aspecto (spec 020, D3). `opts.tema` manda cuando se abre programaticamente.
        overlay.setAttribute('data-mu-tema', temaDe(doc.activeElement || null, o));

        var caja = crear(doc, 'div', 'mu-modal-container mu-gal-caja');
        // `ancho`: 'auto' (default) ajusta la caja al contenido en escritorio;
        // 'completo' la lleva casi a pantalla; un numero fija `max-width` (legado).
        // El lienzo se aplica al FINAL del montaje: necesita `estado.medidas`.

        var cab = crear(doc, 'div', 'mu-gal-cabecera');
        cab.appendChild(crear(doc, 'p', 'mu-gal-titulo', o.titulo || 'Galeria'));
        cab.appendChild(crear(doc, 'p', 'mu-gal-contador', ''));
        var botonCerrar = crear(doc, 'button', 'mu-gal-cerrar', '\u00d7');
        botonCerrar.type = 'button';
        botonCerrar.setAttribute('aria-label', 'Cerrar');
        cab.appendChild(botonCerrar);
        caja.appendChild(cab);

        montarSegmentos(doc, caja, o);

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
        // Estado de error DENTRO del marco (nunca en `estado`): se limpia solo cuando el
        // `src` nuevo carga, asi sobrevive a los repintados sin logica extra.
        var cajaError = crear(doc, 'div', 'mu-gal-error');
        cajaError.hidden = true;
        var textoError = crear(doc, 'p', 'mu-gal-error-texto', 'No se pudo cargar la imagen');
        var botonError = crear(doc, 'button', 'mu-gal-error-reintentar', 'Reintentar');
        botonError.type = 'button';
        cajaError.appendChild(textoError);
        cajaError.appendChild(botonError);
        img.addEventListener('load', function () {
            img.className = 'mu-gal-img mu-gal-img--cargada';
            sinClase(marco, 'mu-gal-marco--error');
            cajaError.hidden = true;
        });
        img.addEventListener('error', function () {
            // El `src` vacio no es un fallo (es el marco antes del primer pintado).
            if (!img.getAttribute('src')) { return; }
            img.className = 'mu-gal-img mu-gal-img--roto';
            if (marco.className.indexOf('mu-gal-marco--error') === -1) {
                marco.className += ' mu-gal-marco--error';
            }
            cajaError.hidden = false;
        });
        botonError.addEventListener('click', function () {
            var actual = img.getAttribute('src') || '';
            if (!actual) { return; }
            cajaError.hidden = true;
            img.className = 'mu-gal-img';
            img.setAttribute('src', conCache(actual));
        });
        marco.appendChild(img);
        marco.appendChild(cajaError);
        cuerpo.appendChild(prev);
        cuerpo.appendChild(marco);
        cuerpo.appendChild(next);
        caja.appendChild(cuerpo);
        // Cuerpo fijo (la imagen no se desplaza); banda y meta scrollean. Antes la
        // caja recortaba (`overflow: hidden`) y la meta larga se perdia abajo.
        var scroll = crear(doc, 'div', 'mu-gal-scroll');
        caja.appendChild(scroll);

        // Listado inferior (F2): la MISMA coleccion en preset mini, sincronizada
        // dos vias (click en la mini -> `ir`; `ir` -> celda activa + scroll).
        if (o.listado === 'abajo' && items.length > 1) {
            var lista = crear(doc, 'div', 'mu-gal-visor-lista');
            // La celda marcada es la que se esta VIENDO (F2): el listado se sincroniza
            // con `ir`; la "Elegida" del ambito 1 es otra cosa y no se mezcla aca.
            var posElegida = acotar(o.indice, items.length);
            scroll.appendChild(lista);
            coleccion(lista, {
                items: items.map(function (it) {
                    return { url: it.thumb || it.url, etiqueta: it.etiqueta || '' };
                }),
                layout: 'fila',
                preset: 'mini',
                tamano: 'chico',
                // El listado muestra TODO de una (`navegable: true`): asi la celda
                // activa siempre existe y `marcarLista` puede acercarla.
                navegable: true,
                activo: posElegida,
                pie: false,
                tope: 0,
                onAbrir: function (i) { ir(i); }
            });
        }

        var cajaMeta = crear(doc, 'div', 'mu-gal-meta');
        cajaMeta.hidden = true;
        scroll.appendChild(cajaMeta);

        montarAcciones(doc, caja, o);

        overlay.appendChild(caja);
        doc.body.appendChild(overlay);

        estado.raiz = overlay;
        // Filtros del visor (spec 020 D6): el estado guarda la lista COMPLETA y la vista
        // filtrada; la banda y la navegacion trabajan con la vista.
        estado.todos = items;
        estado.opciones = o;
        estado.filtro = normalizarFiltro(o);
        var vistaInicial = vistaFiltrada();
        estado.items = vistaInicial;
        estado.medidas = medirItems(vistaInicial);
        estado.pos = acotar(o.indice, vistaInicial.length);
        if (vistaInicial.length !== items.length) {
            montarListado(doc, scroll, vistaInicial, o, estado.pos);
        }
        montarFiltros(doc, caja, o);
        // Antes del frame visible: la caja ya tiene que tener su lienzo puesto.
        aplicarLienzo();
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
        // El ancho decide las flechas: se recalcula al redimensionar (rotar el telefono).
        // El lienzo depende de la ventana: se recalcula al redimensionar (rotar el telefono).
        estado.onResize = function () { aplicarLienzo(); };
        if (global.addEventListener) { global.addEventListener('resize', estado.onResize); }

        bloquearScroll(doc, true);
        pintarVisor();
        // El foco va en el frame que agrega `is-visible`. Con el overlay aun invisible
        // (`visibility: hidden` en el modal base del tema) el navegador IGNORA
        // `.focus()`: el visor abria sin foco y `Tab` se escapaba del modal.
        // Medido en tests/visor.browser.js (V0, spec 019).
        // La transicion del tema corre al agregar `is-visible` en el frame siguiente.
        setTimeout(function () {
            if (estado.raiz !== overlay) { return; }
            overlay.className += ' is-visible';
            aplicarLienzo();
            overlay.setAttribute('aria-hidden', 'false');
            // `transition: visibility` mantiene el valor computado en `hidden` un
            // rato, y el navegador IGNORA `.focus()` en silencio: abriendo por click
            // el modal quedaba sin foco y `Tab` se escapaba (medido en
            // tests/visor.browser.js). Se reintenta hasta que entre, sin robarlo si
            // el foco ya esta dentro del modal.
            var intentos = 0;
            var enfocar = function () {
                if (estado.raiz !== overlay) { return; }
                if (overlay.contains(doc.activeElement)) { return; }
                try { botonCerrar.focus(); } catch (eFoco) { /* sin foco programatico */ }
                intentos += 1;
                if (intentos < 8) { setTimeout(enfocar, 50); }
            };
            enfocar();
        }, 16);
        return true;
    }

    /* (v1 retirada en la spec 020, T013/F6: la TIRA de miniaturas v1 ya no existe; el
       ambito 1 es `MU.coleccion`.) */

    /* (v1 retirada: `pintarTira`/`observarTira` ya no existen.) */

    /* (v1 retirada: `tira` ya no existe; el ambito 1 es `MU.coleccion`.) */

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

    /* (v1 retirada en la spec 020, T013/F6: `MU.tarjeta` la reemplaza `MU.coleccion` con
       `preset:'tarjeta'`; el catalogo se hace con la celda v2.) */

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
        if (i === activo) {
            celda.className += ' mu-gal-celda--activa';
            // El estado no depende solo del color: el anillo es visual, esto es semantica.
            celda.setAttribute('aria-current', 'true');
        }

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
        // `estado` (contrato C10): el texto del ITEM si lo trae; si no, "Elegida" para
        // la celda activa. Antes solo existia "Elegida" y dependia de un `activo` que
        // el consumidor casi nunca pasaba, asi que no se veia nunca.
        var textoEstado = (item.estado === undefined || item.estado === null)
            ? '' : String(item.estado);
        if (slots.estado && (textoEstado || i === activo)) {
            asegurarCuerpo().appendChild(crear(doc, 'p', 'mu-gal-item-estado',
                textoEstado || 'Elegida'));
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

    /**
     * Chip "+X" como CELDA de la linea. `info.accion` decide que hace:
     *   'revelar' -> SUMA una pantalla de celdas (lo hace el propio componente)
     *   'abrir'   -> abre el visor en el primer oculto
     *   'cargar'  -> abre el cargador
     * `o.onChip` externo tiene prioridad (compatibilidad).
     */
    function construirChip(doc, info, o, tam) {
        var cuantos = Math.max(0, parseInt(info.cuantos, 10) || 0);
        var desde = Math.max(0, parseInt(info.desde, 10) || 0);
        var b = crear(doc, 'button', 'mu-gal-celda mu-gal-celda--chip', '+' + cuantos);
        b.type = 'button';
        b.style.setProperty('--mu-gal-tam', tam.ancho + 'px');
        b.setAttribute('data-mu-gal-chip', String(cuantos));
        b.setAttribute('data-mu-gal-chip-accion', String(info.accion || ''));
        b.setAttribute('aria-label', (info.accion === 'revelar')
            ? ('Ver ' + cuantos + ' mas: muestra ' + (info.muestra || cuantos))
            : ('Ver las ' + cuantos + ' restantes en el visor'));
        b.addEventListener('click', function () {
            if (typeof o.onChip === 'function') { o.onChip(cuantos, desde, info); return; }
            if (info.accion === 'cargar' && typeof o.onRanura === 'function') {
                o.onRanura(desde, 0);
                return;
            }
            if (info.accion === 'revelar' && typeof info.alRevelar === 'function') {
                info.alRevelar();
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
        marcarFlechasEnLinea(caja);
        caja.appendChild(prev);
        caja.appendChild(next);
        pintar();
        return { pintar: pintar };
    }

    /**
     * Galeria embebida (ambito 1). Contrato `coleccion.md`.
     * La vista arranca FIJA (lo que cabe) y el chip SUMA tandas; `navega` es derivado.
     *
     * @returns {{visiblesItems:number, huecos:number, chip:number, modo:string,
     *            navega:boolean, desliza:boolean, scrollY:boolean, revelados:number,
     *            tandas:number, capacidad:number, paso:number, agotado:boolean}}
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

        // FILTROS (contrato C10). Recortan los items ANTES de la aritmetica de celdas,
        // asi el chip, el pie y `min`/`capacidad` hablan siempre de lo que se ve.
        // Solo se ofrece un campo si su SLOT esta visible: filtrar por `etiqueta`
        // cuando la etiqueta no se pinta no le dice nada al comprador.
        var itemsBase = items;
        var estPrev = colecciones ? colecciones.get(nodo) : null;
        var filtrosEstado = (estPrev && estPrev.filtros) ? estPrev.filtros : {};
        var camposFiltro = [];
        var pedidosFiltro = o.filtros ? (Array.isArray(o.filtros) ? o.filtros : [o.filtros]) : [];
        pedidosFiltro.forEach(function (c) {
            var campo = String(c || '');
            if (campo && slots[campo] && camposFiltro.indexOf(campo) === -1) {
                camposFiltro.push(campo);
            }
        });
        camposFiltro.forEach(function (campo) {
            var valores = valoresFiltro(itemsBase, campo);
            var v = filtrosEstado[campo];
            if (v === undefined && o.filtroActivo && String(o.filtroActivo) !== 'todos') {
                v = String(o.filtroActivo);
            }
            if (v === undefined || v === null) { v = 'todos'; }
            // Con un solo valor no hay nada que filtrar (y el control no se pinta).
            if (valores.length < 2 || (String(v) !== 'todos' && valores.indexOf(String(v)) === -1)) {
                v = 'todos';
            }
            filtrosEstado[campo] = String(v);
            items = filtrar(items, campo, filtrosEstado[campo]);
        });

        // Cuantas celdas entran en la VISTA: manda el CONTENEDOR, nunca el viewport.
        var ancho = nodo.clientWidth || 0;
        var cabenLinea = caben(ancho, tam.ancho, gap);
        // Celdas por FILA: las columnas de la grilla o la linea completa. El `tope` es de
        // celdas, asi que se materializa en filas completas (`topeEnFilas`) ANTES de
        // repartirlo: un tope a media fila dejaba el chip en una celda suelta (C1ter).
        var porFila = (layout === 'grilla' && columnas > 0) ? columnas : cabenLinea;
        tope = topeEnFilas(tope, porFila);
        var navegable = navegableDe(o);
        var altoVista = Math.max(0, parseInt(o.alto, 10) || 0);

        // Huecos: obligatorios e invitacion. Van SIEMPRE primero y ocupan celda.
        var obligatorios = Math.max(0, min - items.length);
        var cargable = (min > 0 || capacidad > 0);
        var invitacion = (cargable && items.length >= min
            && (capacidad === 0 || capacidad > items.length)) ? 1 : 0;
        var huecos = obligatorios + invitacion;

        // Tandas pedidas por el chip: se conservan mientras no cambien las opciones.
        var estPrevio = colecciones ? colecciones.get(nodo) : null;
        var firma = [items.length, tope, min, capacidad, layout, filas, columnas,
            altoVista, String(navegable)].join('|');
        var tandas = (estPrevio && estPrevio.firma === firma) ? (estPrevio.tandas || 0) : 0;

        // El PIE se calcula y se PINTA antes de medir: su alto es parte de la ventana. Con
        // `alto` declarado el marco mide el alto MENOS el pie; si no, desborda el bloque.
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
        // Todo lo que se MIDE va ANTES de vaciar: el marco, su recorte y la celda previa.
        var lineaPrevia = nodo.querySelector('.mu-gal-linea');
        var scrollPrevio = lineaPrevia ? (lineaPrevia.scrollLeft || 0) : 0;
        var celdaPrevia = nodo.querySelector('.mu-gal-celda[data-mu-gal-mini]');
        var marcoH = nodo.clientHeight || 0;
        // El marco ACOTA solo si su contenido NO entra (nunca si crece con el).
        recorta = (marcoH > 0) && (nodo.scrollHeight > marcoH + 1);

        vaciar(nodo);
/* Filtros visibles (C10): pills hasta 7 valores; con mas, un `<select>`.
           "Todos" va primero y el estado del filtro vive en el CONTENEDOR (para que
           el repintado por resize no lo pierda). */
        function aplicarFiltro(campo, valor) {
            var est = colecciones ? colecciones.get(nodo) : null;
            if (!est) { return null; }
            est.filtros = est.filtros || {};
            if (String(est.filtros[campo]) === String(valor)) { return null; }
            est.filtros[campo] = String(valor);
            var nuevo = coleccion(nodo, o);
            if (typeof o.onFiltrar === 'function') {
                try { o.onFiltrar(campo, String(valor), nuevo); } catch (eFiltro) { /* manda el consumidor */ }
            }
            return nuevo;
        }

        camposFiltro.forEach(function (campo) {
            var valoresVisibles = valoresFiltro(itemsBase, campo);
            if (valoresVisibles.length < 2) { return; }
            var fila = crear(doc, 'div', 'mu-gal-filtros');
            fila.setAttribute('data-mu-gal-filtro', campo);
            if (valoresVisibles.length > 7) {
                var sel = crear(doc, 'select', 'mu-gal-filtro-select');
                sel.setAttribute('aria-label', 'Filtrar por ' + campo);
                ['todos'].concat(valoresVisibles).forEach(function (v) {
                    var op = crear(doc, 'option', '', (v === 'todos') ? 'Todos' : v);
                    op.value = v;
                    sel.appendChild(op);
                });
                sel.value = filtrosEstado[campo];
                sel.addEventListener('change', function () { aplicarFiltro(campo, sel.value); });
                fila.appendChild(sel);
            } else {
                ['todos'].concat(valoresVisibles).forEach(function (v) {
                    var b = crear(doc, 'button', 'mu-gal-filtro', (v === 'todos') ? 'Todos' : v);
                    b.type = 'button';
                    b.setAttribute('data-mu-gal-valor', v);
                    b.setAttribute('aria-pressed',
                        (String(filtrosEstado[campo]) === v) ? 'true' : 'false');
                    b.addEventListener('click', function () { aplicarFiltro(campo, v); });
                    fila.appendChild(b);
                });
            }
            nodo.appendChild(fila);
        });
        nodo.setAttribute('data-mu-gal-layout', layout);
        // Tema visual del contexto (spec 020, D2): se respeta el que declare el
        // consumidor y, si no hay, se resuelve desde el ancestro mas cercano.
        if (!nodo.getAttribute('data-mu-tema')) {
            nodo.setAttribute('data-mu-tema', temaDe(nodo, o));
        }
        var pieAlto = 0;
        if (pieTxt) {
            var p = crear(doc, 'p', 'mu-gal-pie', pieTxt);
            p.setAttribute('role', 'status');
            nodo.appendChild(p);
            pieAlto = p.offsetHeight || 0;     // reflow: el alto REAL del pie ya pintado
        }

        // Cota y ventana de la vista FIJA: 0 = sin presupuesto (entran todas, sin chip).
        var cabenVista = 0;
        var altoCelda = tam.alto;
        var midioCelda = false;
        var recorta = false;
        var vent = null;
        if (layout === 'grilla') {
            // Columnas por ANCHO; las filas, por ALTO (`filas` manda sobre `alto`).
            // `porFila` ya se resolvio arriba (lo comparte el redondeo del tope).
            // Alto de la celda: se MIDE la del pintado anterior (exacto); si no hay,
            // se estima con el cuerpo que dibujan los slots (conservador).
            var extras = 0;
            if (slots.titulo) { extras += 18; }
            if (slots.descripcion) { extras += 16; }
            if (slots.precio) { extras += 16; }
            if (slots.acciones) { extras += 30; }
            if (slots.estado) { extras += 14; }
            altoCelda = tam.alto + (extras > 0 ? (extras + 12) : 0);
            var muestra = celdaPrevia;
            if (muestra && muestra.offsetHeight > 0) {
                altoCelda = muestra.offsetHeight;
                midioCelda = true;
            }
            vent = ventana({
                alto: altoVista, filas: filas, altoCelda: altoCelda, gap: gap,
                pie: pieAlto, marco: marcoH,
                // El recorte ya visto se conserva: sin eso el marco mediria su PROPIO
                // recorte (pintar todas -> desborda -> "no acota" -> pintar todas).
                // SOLO el recorte REAL: `alto` y `filas` son cotas DECLARADAS y viajan
                // por su propio parametro (pasarlas aca hacia que el marco recortado se
                // usara como presupuesto y la vista quedara clavada en una fila).
                acota: (recorta || !!(estPrevio && estPrevio.recorta))
            });
            if (vent.acota) { cabenVista = porFila * vent.porAlto; }
        } else {
            // La fila SIEMPRE acota por el ancho: una linea, el resto detras del chip.
            cabenVista = cabenLinea;
        }

        // Carga ACUMULATIVA: `navegable: true` revela todo de una; el resto arranca fijo.
        var t = carga({
            items: items.length, tope: tope, huecos: huecos,
            caben: (cabenVista > 0) ? cabenVista : (items.length + huecos),
            tandas: (navegable === true) ? 1e9 : tandas
        });
        tandas = t.tandas;
        var navega = t.navega;               // DERIVADO: lo revelado ya no cabe en la vista
        var scrollY = (layout === 'grilla' && navega);

        // Los huecos ya se contaron dentro de `carga` (descuentan la vista fija).

        var modoClase = (layout === 'grilla') ? 'grilla' : (navega ? 'linea' : 'fila');
        var linea = crear(doc, 'div', 'mu-gal-linea mu-gal-linea--' + modoClase);
        linea.style.setProperty('--mu-gal-gap', gap + 'px');
        /* La medida va en la LINEA (no solo en la celda): la grilla calcula sus
           columnas con `minmax(var(--mu-gal-tam))` y sin esto usaba 112px siempre. */
        linea.style.setProperty('--mu-gal-tam', tam.ancho + 'px');
        if (scrollY) { linea.className += ' mu-gal-linea--scroll-y'; }
        if (layout === 'grilla' && filas > 0) {
            linea.setAttribute('data-filas', String(filas));
            linea.style.setProperty('--filas', String(filas));
        }
        if (layout === 'grilla' && columnas > 0) {
            linea.setAttribute('data-columnas', String(columnas));
            linea.style.setProperty('--columnas', String(columnas));
        }

        /* Los huecos van PRIMERO: el primer tab-stop es "cargar" (accesibilidad).
           El indice es el de la CARGA (items.length + h) y solo salen en la tanda 0. */
        for (var h = 0; h < huecos; h++) {
            linea.appendChild(construirHueco(doc, items.length + h, min || capacidad, o, tam));
        }
        var visibles = items.slice(0, t.visibles);
        visibles.forEach(function (it, i) {
            linea.appendChild(construirCelda(doc, it, i, o, activo, slots, tam));
        });

        // Chip: SUMA una pantalla de celdas; agotado el tope, abre el visor (C1bis).
        if (t.ocultas > 0) {
            var accionChip = o.chipAccion;
            if (!accionChip || accionChip === 'auto') {
                // `navegable: false` no acumula: el chip lleva al visor.
                accionChip = (navegable === false) ? 'abrir' : (t.agotado ? 'abrir' : 'revelar');
            } else if (accionChip === 'paginar') {
                accionChip = 'revelar';               // 'paginar' era el nombre viejo
            }
            if (accionChip === 'revelar' && t.agotado) { accionChip = 'abrir'; }
            linea.appendChild(construirChip(doc, {
                cuantos: t.ocultas,
                desde: t.visibles,
                accion: accionChip,
                muestra: Math.min(t.paso, t.ocultas),
                alPaginar: revelarSiguiente,
                alRevelar: revelarSiguiente
            }, o, tam));
        }

        /**
         * Suma una pantalla de celdas (estado interno). Lo ya revelado NO se oculta:
         * la vista se desplaza hasta la PRIMERA celda nueva.
         */
        function revelarSiguiente() {
            var est = colecciones ? colecciones.get(nodo) : null;
            if (!est) { return null; }
            var previos = t.visibles;
            est.tandas = t.tandas + 1;
            est.firma = firma;
            var res = coleccion(nodo, est.opciones);
            var celdas = nodo.querySelectorAll('[data-mu-gal-mini]');
            var destino = celdas[previos] || null;
            if (destino && typeof destino.scrollIntoView === 'function') {
                try { destino.scrollIntoView({ block: 'nearest', inline: 'nearest' }); }
                catch (eScroll) { /* sin scroll */ }
            }
            var chipNuevo = nodo.querySelector('[data-mu-gal-chip]');
            if (chipNuevo) { try { chipNuevo.focus(); } catch (eFoco) { /* sin foco */ } }
            return res;
        }

        if (navega) {
            var caja = crear(doc, 'div', 'mu-gal-caja-linea');
            caja.appendChild(linea);
            nodo.appendChild(caja);
            flechasDeLinea(caja, linea);
        } else {
            nodo.appendChild(linea);
        }
        linea.scrollLeft = scrollPrevio;
        // PISO DE LA RAIZ (contrato `coleccion.md`, C11): si el consumidor mete esta raiz
        // en un contenedor flex y le pone `overflow: hidden` (la banda del visor), su
        // minimo automatico pasa a 0 y las celdas se recortan hasta desaparecer (medido:
        // mini de 74 -> 0 px). Se publica el alto de la celda MAS ALTA (el contenido
        // manda, no el preset: hay celdas con titulo, precio o acciones) + el pie + los
        // paddings, y el CSS lo aplica como `min-height` de la raiz.
        // `scrollHeight` entra en la cuenta: con la raiz ya aplastada el rect miente y el
        // alto NATURAL del contenido no.
        var altoCeldaMax = 0;
        var celdasPiso = linea.querySelectorAll('[data-mu-gal-mini]');
        for (var ip = 0; ip < celdasPiso.length; ip++) {
            var celdaPiso = celdasPiso[ip];
            var altoPiso = Math.max(celdaPiso.offsetHeight || 0, celdaPiso.scrollHeight || 0);
            if (altoPiso > altoCeldaMax) { altoCeldaMax = altoPiso; }
        }
        if (altoCeldaMax <= 0) { altoCeldaMax = altoCelda; }   // sin celdas: el preset
        var altoMin = altoCeldaMax + (pieAlto || 0);
        var csLinea = global.getComputedStyle ? global.getComputedStyle(linea) : null;
        var csNodo = global.getComputedStyle ? global.getComputedStyle(nodo) : null;
        if (csLinea) {
            altoMin += (parseFloat(csLinea.paddingTop) || 0)
                + (parseFloat(csLinea.paddingBottom) || 0);
        }
        if (csNodo) {
            altoMin += (parseFloat(csNodo.paddingTop) || 0)
                + (parseFloat(csNodo.paddingBottom) || 0)
                + (parseFloat(csNodo.borderTopWidth) || 0)
                + (parseFloat(csNodo.borderBottomWidth) || 0);
        }
        // Los filtros (C10) son parte del contenido de la raiz: tambien cuentan.
        var filasFiltro = nodo.querySelectorAll('.mu-gal-filtros');
        for (var ifi = 0; ifi < filasFiltro.length; ifi++) {
            altoMin += filasFiltro[ifi].offsetHeight || 0;
        }
        nodo.style.setProperty('--mu-gal-alto-celda', Math.ceil(altoCeldaMax) + 'px');
        nodo.style.setProperty('--mu-gal-alto-min', Math.ceil(altoMin) + 'px');
        // Alto REAL de la fila y marco definitivo (los ajusta `ajustarAltoFila`).
        var altoFila = altoCelda;
        var marcoFinal = vent ? vent.altoMarco : 0;

        /**
         * Fija el alto de la FILA al de la celda REAL y recalcula el marco con la misma
         * funcion pura (`ventana`): la cota deja de depender de la estimacion por slot,
         * que se queda corta con el cuerpo real (el `precio` mide 28 px, no 16). El
         * numero se mide aca porque la linea ya esta insertada en el DOM.
         */
        function ajustarAltoFila() {
            if (!vent || !vent.acota) { return 0; }
            var medida = linea.querySelector('.mu-gal-celda[data-mu-gal-mini]');
            // El alto REAL manda (la estimacion por slot puede quedarse corta o sobrar);
            // se redondea hacia ARRIBA para que el grid nunca quede medio pixel corto.
            var altoReal = 0;
            if (medida) {
                altoReal = (typeof medida.getBoundingClientRect === 'function')
                    ? Math.ceil(medida.getBoundingClientRect().height || 0)
                    : (medida.offsetHeight || 0);
            }
            if (altoReal > 0) { altoFila = altoReal; }
            if (altoFila <= 0) { return 0; }
            var real = ventana({
                alto: altoVista, filas: filas, altoCelda: altoFila, gap: gap,
                pie: pieAlto, marco: marcoH, acota: vent.acota
            });
            linea.setAttribute('data-cota', vent.motivo);
            linea.style.setProperty('--fila-alto', altoFila + 'px');
            if (real.altoMarco > 0) {
                linea.style.maxHeight = real.altoMarco + 'px';
                linea.style.overflowY = 'auto';
            }
            return real.altoMarco;
        }

        // Ventana fija: el marco mide lo que manda la cota (nunca mas que el bloque).
        if (vent && vent.acota && vent.altoMarco > 0) {
            marcoFinal = ajustarAltoFila() || marcoFinal;
            if (marcoFinal > 0) { linea.style.maxHeight = marcoFinal + 'px'; }
            // La fuente del tema cambia el alto del cuerpo (~10 px): re-medir al asentar.
            observarFuentes(function () { coleccion(nodo, o); });
            linea.style.overflowY = 'auto';
        }
        // Estado publicado para CSS/QA: `navegable` cuando lo revelado no cabe, y la
        // FUENTE de la cota (`ancho` en fila; `filas`/`alto`/`marco`/`libre` en grilla).
        nodo.setAttribute('data-mu-gal-estado', navega ? 'navegable' : 'fija');
        nodo.setAttribute('data-mu-gal-ancho', String(ancho));
        nodo.setAttribute('data-mu-gal-cota', vent ? vent.motivo : 'ancho');
        // Cuantos items pasaron el filtro (QA y consumidores): `data-mu-gal-items`.
        nodo.setAttribute('data-mu-gal-items', String(items.length));

        if (colecciones) {
            colecciones.set(nodo, {
                opciones: o, items: items, ancho: ancho, filtros: filtrosEstado,
                tandas: tandas, firma: firma, recorta: recorta
            });
            observarColeccion(nodo);
        }

        // Auto-ajuste (UNA vez por evidencia): el primer pintado no puede saber si el
        // marco recorta (el nodo estaba vacio) ni el alto real de la celda. Se repinta
        // si falto alguno de esos datos; la marca evita el bucle.
        var marcaAjuste = firma + '|' + (recorta ? '1' : '0');
        var necesitaAjuste = false;
        if (layout === 'grilla' && nodo.clientHeight > 0) {
            // El marco SI recorta pero pintamos sin cota: hay que re-medir y recortar.
            if (!vent.acota && nodo.scrollHeight > nodo.clientHeight + 1) { necesitaAjuste = true; }
            // Ya acota, pero el alto de celda se estimo: el real cambia las filas.
            if (vent.acota && !midioCelda) { necesitaAjuste = true; }
        }
        if (necesitaAjuste && (!estPrevio || estPrevio.ajustado !== marcaAjuste)
            && typeof global.requestAnimationFrame === 'function') {
            var estAjuste = colecciones ? colecciones.get(nodo) : null;
            if (estAjuste) { estAjuste.ajustado = marcaAjuste; }
            global.requestAnimationFrame(function () { coleccion(nodo, o); });
        }
        var res = {
            visiblesItems: t.visibles, huecos: huecos, chip: t.ocultas,
            altoCelda: Math.ceil(altoCeldaMax), altoMin: Math.ceil(altoMin),
            modo: modoClase, navega: navega, desliza: navega, scrollY: scrollY,
            revelados: t.revelados, tandas: tandas, capacidad: t.capacidad, paso: t.paso,
            agotado: t.agotado,
            acota: (vent ? vent.acota : true), cota: (vent ? vent.motivo : 'ancho'),
            altoMarco: marcoFinal
        };
        // El componente puede repintarse solo (resize, auto-ajuste de la grilla): el
        // consumidor se entera por `onPintado` y no por el valor de retorno.
        if (typeof o.onPintado === 'function') {
            try { o.onPintado(res); } catch (ePintado) { /* el consumidor manda */ }
        }
        return res;
    }

    /**
     * Re-mide UNA vez cuando asienta la fuente del tema: el alto del cuerpo cambia y la
     * firma de la coleccion NO (nadie repintaria, y el marco quedaria corto). La marca
     * vive en la propia funcion para no agregar estado al modulo.
     */
    function observarFuentes(repintar) {
        var doc = global.document;
        if (observarFuentes.hecho || !doc || !doc.fonts
            || typeof doc.fonts.ready === 'undefined') { return; }
        if (doc.fonts.status !== 'loading') { return; }
        observarFuentes.hecho = true;
        try {
            doc.fonts.ready.then(function () { repintar(); });
        } catch (eFuentes) { /* el ajuste del momento ya dejo la cota exacta */ }
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
        // Dedupe por ARCHIVO (spec 020, C11 del visor): Woo repite la imagen principal en
        // cada slide y agrega sufijos de recorte (`-100x100`), asi que el visor contaba 10
        // con 5 miniaturas y la banda repetia fotos. Se compara la URL base, sin sufijo.
        var vistos = {};
        var agregar = function (url, thumb, etiqueta) {
            var base = String(url || '').split('#')[0].split('?')[0];
            if (!base) { return; }
            var clave = base.replace(/-\d+x\d+(\.(png|jpe?g|webp|gif))?$/i, '$1');
            if (vistos[clave]) { return; }
            vistos[clave] = 1;
            out.push({ url: url, thumb: thumb || url, etiqueta: etiqueta || '' });
        };
        var slides = cont.querySelectorAll('.woocommerce-product-gallery__image img');
        for (var i = 0; i < slides.length; i++) {
            var s = slides[i];
            var urlS = s.getAttribute('data-large_image') || s.getAttribute('data-src') || s.getAttribute('src') || '';
            if (!urlS) { continue; }
            agregar(urlS, s.getAttribute('data-src') || s.getAttribute('src') || urlS, s.getAttribute('alt'));
        }
        var thumbs = cont.querySelectorAll('.flex-control-thumbs img');
        for (var j = 0; j < thumbs.length; j++) {
            var t = thumbs[j];
            var urlT = t.getAttribute('data-large_image') || t.getAttribute('src') || '';
            if (!urlT) { continue; }
            agregar(urlT, t.getAttribute('src') || urlT, t.getAttribute('alt'));
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
        desdeWoo: desdeWoo,
        desdeEnlaces: desdeEnlaces,
        autoInit: autoInit,
        arrastrar: arrastrar,
        navegarWoo: navegarWoo,
        coleccion: coleccion,
        puro: { acotar: acotar, preCargar: preCargar, orden: orden, lienzo: lienzo,
            formatearPrecio: formatearPrecio, normalizarMeta: normalizarMeta, tonoBadge: tonoBadge,
            filtrar: filtrar, valoresFiltro: valoresFiltro,
            celdas: celdas, pie: pie, caben: caben,
        carga: carga, topeEnFilas: topeEnFilas, navegableDe: navegableDe, ventana: ventana },
        version: '1.11.0'
    };

    // `MU.visor.actualizar(parcial)` (V2, T044): se cuelga de la MISMA funcion `visor`, asi
    // `MU.visor(...)` sigue siendo la apertura y no hay un segundo nombre del mismo ambito.
    visor.actualizar = actualizar;
    global.MUGaleria = api;

    // Dos ambitos con nombre propio (contrato `coleccion.md`, C0):
    //   MU.coleccion = galeria embebida;  MU.visor = modal de ampliacion.
    global.MU = {
        coleccion: coleccion,
        visor: visor,
        ir: ir,
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