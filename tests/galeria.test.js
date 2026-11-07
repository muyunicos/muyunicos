/**
 * Puerta de la galeria compartida del tema (spec 019).
 *
 * Corre en Node, sin navegador: ejercita los puros exportados y verifica por TEXTO
 * las reglas que no se pueden romper (contrato C7):
 *   - no pedir datos (ni fetch, ni endpoints, ni nonces)
 *   - no usar `innerHTML` con nada
 *   - las clases propias van con prefijo `mu-gal-`
 *   - el modal es el del tema (`.mu-modal-*`), no uno nuevo
 *   - el tema no aporta datos: inc/galeria.php solo registra/encola
 *
 * Uso: node tests/galeria.test.js      (esperado: "GALERIA OK (N checks)")
 */
'use strict';

const fs = require('fs');
const path = require('path');

const RAIZ = path.join(__dirname, '..');
const lee = (rel) => fs.readFileSync(path.join(RAIZ, rel), 'utf8');

const gal = require(path.join(RAIZ, 'js/galeria.js'));
const js = lee('js/galeria.js');
const css = lee('css/components/galeria.css');
const php = lee('inc/galeria.php');
const prod = lee('js/product.js');
const func = lee('functions.php');
const ui = lee('js/global-ui.js');
const baseCss = lee('css/components/global-ui.css');
const shop = lee('css/shop.css');

// Codigo SIN comentarios: las reglas de "prohibido" se evaluan sobre el codigo real
// (los docblocks explican las reglas y no deben hacer fallar la puerta).
const jsCodigo = js.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '');
const cssCodigo = css.replace(/\/\*[\s\S]*?\*\//g, '');
const phpCodigo = php.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '');

let hechos = 0;
let fallos = 0;

function check(nombre, cond, detalle) {
    hechos++;
    if (cond) {
        console.log('  OK   ' + nombre);
    } else {
        fallos++;
        console.log('  FALLA ' + nombre + (detalle ? ' -> ' + detalle : ''));
    }
}

/* ==================== 1. API publica ==================== */
console.log('== API ==');
// `version` es la version SEMANTICA del API y no participa del cache-bust (eso es
// el `Version:` del tema, D12): se verifica que exista y sea comparable, no un
// numero concreto, para no acoplar tres fuentes que se bumbean por motivos distintos.
check('expone version', typeof gal.version === 'string' && /^\d+\.\d+\.\d+$/.test(gal.version), 'version=' + gal.version);
check('expone visor.actualizar (V2, T044)', typeof gal.visor.actualizar === 'function');
['visor', 'cerrar', 'estaAbierto', 'tira', 'tarjeta', 'desdeWoo', 'desdeEnlaces',
    'autoInit', 'arrastrar', 'navegarWoo'].forEach((fn) => {
    check('expone ' + fn, typeof gal[fn] === 'function');
});
['acotar', 'preCargar', 'orden', 'formatearPrecio', 'filtrar', 'valoresFiltro', 'celdas',
    'pie', 'caben', 'carga', 'navegableDe', 'ventana'].forEach((fn) => {
    check('expone puro.' + fn, typeof gal.puro[fn] === 'function');
});

/* ==================== 2. Puros ==================== */
console.log('== Puros ==');
check('acotar negativo -> 0', gal.puro.acotar(-3, 5) === 0);
check('acotar fuera de rango -> ultimo', gal.puro.acotar(9, 3) === 2);
check('acotar sin total no rompe', gal.puro.acotar(2, 0) === 2);
check('acotar texto -> 0', gal.puro.acotar('x', 4) === 0);

check('preCargar vecinos', gal.puro.preCargar([1, 2, 3, 4], 1, 1).join(',') === '0,2');
check('preCargar respeta bordes', gal.puro.preCargar([1], 0, 2).join(',') === '');
check('preCargar k=0 vacio', gal.puro.preCargar([1, 2, 3], 1, 0).length === 0);

check('orden: favorito primero',
    gal.puro.orden({ favorito: true, key: 'b' }, { favorito: false, key: 'a' }) < 0);
check('orden: orden manual manda',
    gal.puro.orden({ orden: 1, key: 'a' }, { orden: 2, key: 'b' }) < 0);
check('orden: lo mas nuevo primero',
    gal.puro.orden({ creado: 100, key: 'a' }, { creado: 50, key: 'b' }) < 0);
check('orden: key desempata estable',
    gal.puro.orden({ key: 'a' }, { key: 'b' }) < 0);

check('formatearPrecio miles', gal.puro.formatearPrecio(1234.6) === '1.235',
    gal.puro.formatearPrecio(1234.6));
check('formatearPrecio invalido', gal.puro.formatearPrecio('x') === '');

/* --- Filtros (contrato C10) --- */
const ITEMS_F = [
    { etiqueta: 'Oferta' }, { etiqueta: 'Nuevo' }, { etiqueta: 'Oferta' },
    { etiqueta: '' }, { etiqueta: 'Pack' }, {}
];
check('valoresFiltro: distintos en orden de aparicion',
    gal.puro.valoresFiltro(ITEMS_F, 'etiqueta').join('|') === 'Oferta|Nuevo|Pack',
    gal.puro.valoresFiltro(ITEMS_F, 'etiqueta').join('|'));
check('valoresFiltro: sin datos -> vacio', gal.puro.valoresFiltro(ITEMS_F, 'estado').length === 0);
check('valoresFiltro: lista vacia no rompe', gal.puro.valoresFiltro(null, 'etiqueta').length === 0);
check('filtrar: todos devuelve la lista entera', gal.puro.filtrar(ITEMS_F, 'etiqueta', 'todos').length === 6);
check('filtrar: sin campo devuelve la lista entera', gal.puro.filtrar(ITEMS_F, '', 'Oferta').length === 6);
check('filtrar: por valor deja los que coinciden',
    gal.puro.filtrar(ITEMS_F, 'etiqueta', 'Oferta').length === 2);
check('filtrar: valor inexistente -> vacio', gal.puro.filtrar(ITEMS_F, 'etiqueta', 'Nada').length === 0);
check('filtrar: compara por texto (numero vs string)',
    gal.puro.filtrar([{ estado: 3 }, { estado: '3' }], 'estado', '3').length === 2);
check('filtrar: no muta la lista original', ITEMS_F.length === 6);

/* ==================== 3. Reglas del JavaScript (C7) ==================== */
console.log('== Reglas del JS ==');
check('sin innerHTML', !/innerHTML|insertAdjacentHTML|outerHTML/.test(jsCodigo));
check('usa textContent', /textContent/.test(jsCodigo));
check('sin fetch ni XHR', !/fetch\(|XMLHttpRequest/.test(jsCodigo));
check('sin endpoints ni nonces', !/wp-json|admin-ajax|admin-post|_wpnonce/i.test(jsCodigo));
check('modal del tema (mu-modal-overlay)', /mu-modal-overlay/.test(js));
check('modal del tema (mu-modal-container)', /mu-modal-container/.test(js));
check('estado por clase is-visible', /is-visible/.test(js));
check('modo declarativo data-mu-galeria', /data-mu-galeria/.test(js));
check('adaptador de WooCommerce', /woocommerce-product-gallery/.test(js));
check('trigger de WooCommerce interceptado', /woocommerce-product-gallery__trigger/.test(js));
check('interceptor en fase de captura', /\}, true\);/.test(js));
check('bloqueo de scroll con clase', /is-scroll-locked/.test(js));

/* ==================== 4. Reglas del CSS ==================== */
console.log('== Reglas del CSS ==');
const clases = (css.match(/\.mu-gal-[a-z0-9-]+/g) || []);
check('define clases mu-gal-*', clases.length >= 20, 'n=' + clases.length);
check('usa el modal base del tema', /\.mu-modal-overlay\.mu-gal-visor/.test(css) &&
    /\.mu-modal-container\.mu-gal-caja/.test(css));
check('no redefine los tokens del tema',
    !/--primario\s*:/.test(css) && !/--secundario\s*:/.test(css) && !/--mu-radius\s*:/.test(css));
check('hereda el criterio de la tira de producto', /scroll-snap-type: x mandatory/.test(css));
check('tiene reduced motion', /prefers-reduced-motion/.test(css));
check('mas de 300 lineas de estilo', css.split('\n').length > 300);

/* ==================== 4b. Tira v1.1 (enmienda 2026-11-05) ==================== */
console.log('== Tira v1.1 ==');
check('modo auto|linea|grilla', /modoEfectivo/.test(js));
check('auto decide por ancho del CONTENEDOR',
    /cabenEnLinea/.test(js) && /nodo\.clientWidth/.test(js));
check('ya no depende del viewport',
    !/matchMedia/.test(js) && !/@media \(max-width: 768px\)/.test(css) && !/tira--scroll/.test(css));
check('repinta al cambiar el ancho', /ResizeObserver/.test(js));
check('conserva el scroll al repintar', /scrollPrevio/.test(js));
check('conserva el foco al repintar', /focoPrevio/.test(js));
check('min dibuja ranuras a llenar',
    /construirRanura/.test(js) && /mu-gal-mini--vacia/.test(js));
check('la ranura avisa con onRanura',
    /onRanura/.test(js) && /data-mu-gal-ranura/.test(js));
check('contador del minimo', /mu-gal-cuenta/.test(js) && /' de ' \+ min/.test(js));
check('tachito en la tira', /mu-gal-mini-borrar/.test(js) && /o\.onBorrar/.test(js));
check('confirmacion configurable', /o\.confirmar/.test(js));
check('la miniatura NO es un boton (sin boton anidado)',
    /crear\(doc, 'div', 'mu-gal-mini'\)/.test(js) && /mu-gal-mini-abrir/.test(js));
check('"+N" son las que FALTAN',
    /'\+' \+ faltan/.test(js) && !/'\+' \+ items\.length/.test(js));
check('activo por defecto: ninguna marcada', /\(o\.activo === undefined\) \? -1/.test(js));
check('grilla con tope de filas',
    /'--filas'/.test(js) && /data-filas/.test(js) && /grid-auto-rows/.test(css));
check('filas recorta y lo que sobra va al "+N"',
    /porFila \* filas/.test(js) && /\(o\.mas \|\| recortado\)/.test(js));
check('el chip "+N" sale fuera de la grilla recortada',
    /chipFuera/.test(js) && /mu-gal-mas--fuera/.test(css));
check('tachito siempre visible en tactil', /@media \(hover: none\)/.test(css));
check('linea con scroll propia (no por viewport)',
    /\.mu-gal-tira--linea/.test(css) && /scroll-snap-type: x mandatory/.test(css));

/* ==================== 4c. Flechas: sistema del tema (D18) ==================== */
console.log('== Flechas ==');
check('la galeria usa .mu-nav-btn del tema (no flechas propias)',
    /mu-nav-btn prev mu-gal-prev/.test(js) && !/mu-gal-nav/.test(js));
check('chevron con createElementNS (sin innerHTML)',
    /createElementNS\(ns, 'svg'\)/.test(js) && /polyline/.test(js));
check('el visor adapta el boton del tema a su flex',
    /\.mu-gal-cuerpo \.mu-nav-btn\.prev/.test(css));
check('hidden gana sobre el display del sistema',
    /\.mu-gal-cuerpo \.mu-nav-btn\[hidden\]/.test(css));
check('nada muestra las flechas sin is-visible',
    !/\.mu-nav-btn\s*\{\s*display:\s*flex/.test(shop) &&
    !/\.mu-nav-btn\.next\s*\{\s*display:\s*flex/.test(baseCss));
check('el sistema enciende con is-visible', /\.mu-nav-btn\.prev\.is-visible/.test(baseCss));
check('el extremo oculta la flecha (disabled)', /\.mu-nav-btn\[disabled\]/.test(baseCss));
check('el carrusel decide por desborde y extremo',
    /const desborda/.test(ui) && /const alInicio/.test(ui) && /const alFinal/.test(ui));

/* ==================== 4d. Coleccion v2: aritmetica (enmienda 2026-11-05) ==================== */
console.log('== Coleccion v2 ==');

function checkCeldas(nombre, opciones, items, huecos, chip) {
    const r = gal.puro.celdas(opciones);
    check(nombre,
        r.visiblesItems === items && r.huecos === huecos && r.chip === chip,
        'items=' + r.visiblesItems + ' huecos=' + r.huecos + ' chip=' + r.chip);
}

// Caso del mantenedor: min 4, capacidad 8, tope 3.
checkCeldas('min4/cap8/tope3 · 0 cargadas -> [+][+][+]',
    { cargadas: 0, min: 4, capacidad: 8, tope: 3 }, 0, 3, 0);
checkCeldas('min4/cap8/tope3 · 1 -> [O][+][+]',
    { cargadas: 1, min: 4, capacidad: 8, tope: 3 }, 1, 2, 0);
checkCeldas('min4/cap8/tope3 · 2 -> [O][O][+]',
    { cargadas: 2, min: 4, capacidad: 8, tope: 3 }, 2, 1, 0);
checkCeldas('min4/cap8/tope3 · 3 -> [O][O][O] (imagenes primero)',
    { cargadas: 3, min: 4, capacidad: 8, tope: 3 }, 3, 0, 0);
checkCeldas('min4/cap8/tope3 · 4 -> [O][O][+2]',
    { cargadas: 4, min: 4, capacidad: 8, tope: 3 }, 2, 0, 2);
checkCeldas('min4/cap8/tope3 · 7 -> [O][O][+5]',
    { cargadas: 7, min: 4, capacidad: 8, tope: 3 }, 2, 0, 5);
checkCeldas('min4/cap8/tope3 · 8 -> [O][O][+6] (el dibujo del mantenedor)',
    { cargadas: 8, min: 4, capacidad: 8, tope: 3 }, 2, 0, 6);

// Campo opcional (sin minimo): la invitacion rellena la celda libre.
checkCeldas('min0/cap8/tope3 · 2 -> [O][O][+] (invitacion)',
    { cargadas: 2, min: 0, capacidad: 8, tope: 3 }, 2, 1, 0);
checkCeldas('min0/cap8/tope3 · 3 -> [O][O][O]',
    { cargadas: 3, min: 0, capacidad: 8, tope: 3 }, 3, 0, 0);
checkCeldas('min0/cap8/tope3 · 4 -> [O][O][+2]',
    { cargadas: 4, min: 0, capacidad: 8, tope: 3 }, 2, 0, 2);

// Galeria sin campo (catalogo): sin min ni capacidad no hay huecos.
checkCeldas('catalogo (min0/cap0) · 3 -> [O][O][O]',
    { cargadas: 3, min: 0, capacidad: 0, tope: 3 }, 3, 0, 0);
checkCeldas('catalogo (min0/cap0) · 4 -> [O][O][+2]',
    { cargadas: 4, min: 0, capacidad: 0, tope: 3 }, 2, 0, 2);

// tope 0 = todos: nunca hay chip.
checkCeldas('tope 0 · 8 items -> todos, sin chip',
    { cargadas: 8, min: 4, capacidad: 8, tope: 0 }, 8, 0, 0);
checkCeldas('tope 0 · 8 items de cap 0 -> 8 + invitacion',
    { cargadas: 8, min: 4, capacidad: 0, tope: 0 }, 8, 1, 0);

// Fila no deslizable: el tope sale de medir. 100 de espacio / celdas de 30.
check('caben(100,30,0) = 3', gal.puro.caben(100, 30, 0) === 3, String(gal.puro.caben(100, 30, 0)));
check('caben nunca baja de 1', gal.puro.caben(5, 30, 0) === 1);
check('caben con gap', gal.puro.caben(100, 30, 5) === 3);
checkCeldas('100px/30px con 10 items -> 2 items + chip 8',
    { cargadas: 10, min: 0, capacidad: 0, tope: gal.puro.caben(100, 30, 0) }, 2, 0, 8);

// Pie (reemplaza el chip para los huecos).
check('pie sin cargas: "Carga 4 imagenes"',
    gal.puro.pie({ cargadas: 0, min: 4, capacidad: 8, sustantivo: 'imagenes' })
    === 'Carga 4 imagenes');
check('pie singular: "Carga 1 imagen"',
    gal.puro.pie({ cargadas: 3, min: 4, capacidad: 8,
        sustantivo: 'imagenes', sustantivoSingular: 'imagen' }) === 'Carga 1 imagen');
check('pie con lugar para mas',
    gal.puro.pie({ cargadas: 4, min: 4, capacidad: 8, sustantivo: 'imagenes' })
    === 'Puedes agregar 4 mas');
check('pie maximo alcanzado',
    gal.puro.pie({ cargadas: 8, min: 4, capacidad: 8, sustantivo: 'imagenes' })
    === 'Maximo alcanzado');
check('pie vacio sin min ni capacidad',
    gal.puro.pie({ cargadas: 2, min: 0, capacidad: 0 }) === '');

/* ==================== 4e. Coleccion v2: API y DOM ==================== */
console.log('== Coleccion v2 (API) ==');

const MU = globalThis.MU || {};
check('MU.coleccion (ambito 1)', typeof MU.coleccion === 'function');
check('MU.visor (ambito 2)', typeof MU.visor === 'function');
check('MUGaleria.coleccion (alias)', typeof gal.coleccion === 'function');
check('presets declarados', /var PRESETS = \{/.test(js) && /tarjeta:/.test(js));
check('slots pisan al preset', /function resolverSlots/.test(js));
check('la celda es un contenedor (sin boton anidado)',
    /crear\(doc, 'div', 'mu-gal-celda'\)/.test(js) && !/crear\(doc, 'button', 'mu-gal-celda'\)/.test(js));
check('el chip es una CELDA de la linea',
    /mu-gal-celda--chip/.test(css) && /'mu-gal-celda mu-gal-celda--chip'/.test(js));
check('el hueco es una celda', /mu-gal-celda--vacia/.test(css));
check('el pie existe y es status', /mu-gal-pie/.test(css) && /'mu-gal-pie'/.test(js) && /'status'/.test(js));
check('las flechas de la linea son del tema', /mu-gal-caja-linea \.mu-nav-btn/.test(css)
    && /function flechasDeLinea/.test(js));
check('el tachito es un icono (mismo estilo que la estrella)',
    /function crearTacho/.test(js) && /mu-gal-item-eliminar svg/.test(css));
const desdeColeccion = js.indexOf('function coleccion(');
check('los huecos van primero (primer tab-stop = cargar)',
    // Los huecos van primero (primer tab-stop = cargar): el indice es el de la CARGA.
    js.indexOf('h < huecos; h++', desdeColeccion) !== -1
    && js.indexOf('h < huecos; h++', desdeColeccion)
        < js.indexOf('var visibles = items.slice(0, t.visibles', desdeColeccion));
check('la medida va en la linea (grilla calcula sus columnas)',
    /linea\.style\.setProperty\('--mu-gal-tam'/.test(js));
check('la grilla no fija alto de fila (era el solapamiento)',
    !/grid-auto-rows: var\(--mu-gal-tam/.test(css));
check('la linea horizontal no captura el scroll vertical',
    /overflow-y: hidden/.test(css) && /overscroll-behavior-x: contain/.test(css));
check('la grilla calcula las filas con la VENTANA (C9)',
    /vent = ventana\(\{/.test(js) && /vent\.porAlto/.test(js) && /altoCelda/.test(js));
check('grilla navegable = scroll vertical DERIVADO del desborde',
    /mu-gal-linea--scroll-y/.test(css)
    && /var scrollY = \(layout === 'grilla' && navega\)/.test(js));
check('las columnas no se estiran: bloque centrado',
    /justify-content: center/.test(css) && /repeat\(auto-fill, var\(--mu-gal-tam/.test(css));
check('el contrato nuevo existe',
    fs.existsSync(path.join(RAIZ, 'specs/019-galeria-compartida/contracts/coleccion.md')));

/* ==================== 4e-bis. Cota y ventana (C9) ==================== */
console.log('== Cota y ventana ==');
const v0 = gal.puro.ventana({});
check('ventana · sin cota no acota',
    v0.acota === false && v0.porAlto === 0 && v0.altoMarco === 0, JSON.stringify(v0));
check('ventana · motivo libre', v0.motivo === 'libre', v0.motivo);
const v1 = gal.puro.ventana({ alto: 500, pie: 24, altoCelda: 200, gap: 8 });
check('ventana · alto declarado descuenta el pie', v1.altoMarco === 476, String(v1.altoMarco));
check('ventana · 476px = 2 filas de 200 + gap', v1.porAlto === 2, String(v1.porAlto));
check('ventana · motivo alto', v1.acota === true && v1.motivo === 'alto', v1.motivo);
const v2 = gal.puro.ventana({ filas: 3, altoCelda: 200, gap: 8 });
check('ventana · filas fija el alto del marco', v2.altoMarco === 616, String(v2.altoMarco));
check('ventana · filas acota aunque el marco crezca',
    v2.acota === true && v2.porAlto === 3, JSON.stringify(v2));
check('ventana · motivo filas', v2.motivo === 'filas', v2.motivo);
const v3 = gal.puro.ventana({ filas: 5, alto: 400, altoCelda: 200, gap: 8 });
check('ventana · filas manda sobre alto (gana el menor)',
    v3.porAlto === 1 && v3.altoMarco === 400, JSON.stringify(v3));
const v4 = gal.puro.ventana({ marco: 420, pie: 20, altoCelda: 200, gap: 8, acota: true });
check('ventana · marco que recorta acota',
    v4.acota === true && v4.porAlto === 1 && v4.altoMarco === 400, JSON.stringify(v4));
check('ventana · motivo marco', v4.motivo === 'marco', v4.motivo);
const v5 = gal.puro.ventana({ marco: 420, pie: 20, altoCelda: 200, gap: 8, acota: false });
check('ventana · marco que crece con el contenido NO acota',
    v5.acota === false && v5.altoMarco === 0, JSON.stringify(v5));
const v6 = gal.puro.ventana({ alto: 10, pie: 20 });
check('ventana · alto menor que el pie no acota', v6.acota === false);
const v7 = gal.puro.ventana({ filas: 2 });
check('ventana · filas sin altoCelda degrada sin romper',
    v7.porAlto === 2 && v7.altoMarco === 2, JSON.stringify(v7));
check('ventana · filas 1 = una celda exacta',
    gal.puro.ventana({ filas: 1, altoCelda: 160 }).altoMarco === 160);
check('ventana · no muta las opciones',
    typeof gal.puro.ventana({ filas: 'x', alto: null }).acota === 'boolean');

/* El marco MEDIDO es el recorte que esta misma galeria acaba de hacer (1 fila): si se
   usa como presupuesto, `filas` queda clavada en 1. `filas` es una cota DECLARADA. */
const v8 = gal.puro.ventana({ filas: 2, altoCelda: 246, gap: 8, marco: 246, acota: true });
check('ventana · filas 2 con marco de 1 fila NO se queda en 1 fila',
    v8.porAlto === 2 && v8.altoMarco === 500, JSON.stringify(v8));
const v9 = gal.puro.ventana({ filas: 3, altoCelda: 246, gap: 8, marco: 246, acota: true, pie: 15 });
check('ventana · filas 3 + pie: el pie no se come una fila',
    v9.porAlto === 3 && v9.altoMarco === 754, JSON.stringify(v9));
const v10 = gal.puro.ventana({ filas: 0, altoCelda: 246, gap: 8, marco: 246, acota: true });
check('ventana · sin filas el marco recortado sigue mandando (1 fila)',
    v10.porAlto === 1 && v10.altoMarco === 246 && v10.motivo === 'marco', JSON.stringify(v10));
const v11 = gal.puro.ventana({ filas: 2, alto: 800, altoCelda: 246, gap: 8 });
check('ventana · filas no infla el marco cuando el bloque sobra',
    v11.porAlto === 2 && v11.altoMarco === 500, JSON.stringify(v11));
const v12 = gal.puro.ventana({ filas: 4, alto: 300, altoCelda: 246, gap: 8 });
check('ventana · bloque acotado recorta las filas declaradas (gana el menor)',
    v12.porAlto === 1 && v12.altoMarco === 300, JSON.stringify(v12));
check('coleccion · `acota` es el recorte REAL, no las cotas declaradas',
    /acota: \(recorta \|\| !!\(estPrevio && estPrevio\.recorta\)\)/.test(js)
    && !/acota: \(altoVista > 0 \|\| filas > 0/.test(js));

/* ==================== 4f. Carga acumulativa del chip (C1bis) ==================== */
console.log('== Carga acumulativa del chip ==');

const c0 = gal.puro.carga({ items: 12, tope: 0, caben: 4 });
check('carga · arranca FIJA: 3 visibles (chip reserva celda) y 9 ocultas',
    c0.capacidad === 3 && c0.visibles === 3 && c0.ocultas === 9 && c0.navega === false,
    'visibles=' + c0.visibles + ' ocultas=' + c0.ocultas);
check('carga · la primera tanda suma una pantalla (3 -> 7)', c0.siguiente === 7);

const c1 = gal.puro.carga({ items: 12, tope: 0, caben: 4, tandas: 1 });
check('carga · al sumar una tanda, lo revelado YA no cabe: navega',
    c1.visibles === 7 && c1.ocultas === 5 && c1.navega === true);
check('carga · lo revelado NUNCA se oculta (7 de 12, no una ventana)',
    c1.visibles > c0.visibles);

const c2 = gal.puro.carga({ items: 12, tope: 0, caben: 4, tandas: 3 });
check('carga · revelado todo: sin chip y sin agotar',
    c2.visibles === 12 && c2.ocultas === 0 && c2.agotado === false && c2.navega === true);

const c3 = gal.puro.carga({ items: 12, tope: 6, caben: 4, tandas: 1e9 });
check('carga · el tope corta (es de CELDAS): 5 imagenes + chip = 6',
    c3.visibles === 5 && c3.ocultas === 7 && c3.agotado === true && c3.topeItems === 5,
    'visibles=' + c3.visibles + ' topeItems=' + c3.topeItems);
check('carga · las tandas se acotan al tope',
    gal.puro.carga({ items: 12, tope: 6, caben: 4, tandas: 99 }).visibles === 5);

// El `tope` cuenta TODAS las celdas visibles: chip (`--chip`) y huecos (`--vacia`).
const ct1 = gal.puro.carga({ items: 14, tope: 4, caben: 6 });
check('tope 4 · 14 imagenes -> 3 + chip = 4 celdas',
    ct1.visibles === 3 && ct1.topeItems === 3 && ct1.hayChip === true && ct1.ocultas === 11,
    'visibles=' + ct1.visibles + ' topeItems=' + ct1.topeItems);
const ct2 = gal.puro.carga({ items: 5, tope: 4, caben: 6, huecos: 2 });
check('tope 4 · 2 huecos + 5 imagenes -> 1 + 2 + chip = 4 celdas',
    ct2.visibles === 1 && ct2.topeItems === 1 && ct2.hayChip === true);
const ct3 = gal.puro.carga({ items: 2, tope: 4, caben: 6, huecos: 2 });
check('tope 4 · 2 huecos + 2 imagenes -> 4 celdas, sin chip',
    ct3.visibles === 2 && ct3.ocultas === 0 && ct3.hayChip === false);
const ct4 = gal.puro.carga({ items: 12, tope: 2, caben: 5 });
check('tope 2 · 12 imagenes -> 1 + chip = 2 celdas',
    ct4.visibles === 1 && ct4.topeItems === 1);
const ct5 = gal.puro.carga({ items: 12, tope: 1, caben: 5 });
check('tope 1 · solo el chip (0 imagenes), y abre el visor',
    ct5.visibles === 0 && ct5.topeItems === 0 && ct5.agotado === true);
check('tope 0 = sin tope de celdas', gal.puro.carga({ items: 12, caben: 4 }).topeItems === Infinity);

// El estado es RELATIVO a la capacidad: si la vista se achica, lo revelado se re-ajusta.
check('carga · las mismas tandas con otra capacidad se re-ajustan solas',
    gal.puro.carga({ items: 12, caben: 12, tandas: 0 }).visibles === 12
    && gal.puro.carga({ items: 12, caben: 5, tandas: 0 }).visibles === 4);

/* ---------- C1bis: el paso es una PANTALLA (y el chip cierra fila) ---------- */

const cp1 = gal.puro.carga({ items: 14, caben: 6, huecos: 1 });
check('paso · una tanda revela `caben` celdas, no `capacidad` (4 -> 10)',
    cp1.capacidad === 4 && cp1.paso === 6 && cp1.siguiente === 10,
    'capacidad=' + cp1.capacidad + ' paso=' + cp1.paso + ' siguiente=' + cp1.siguiente);

// El caso reportado: 3 columnas x 2 filas, 1 hueco y 14 imagenes. La vista fija deja
// 4 imagenes + chip; al tocar entran 6 mas (una pantalla) y el chip cierra la fila.
const cp2 = gal.puro.carga({ items: 14, caben: 6, huecos: 1, tandas: 1 });
check('paso · 1 hueco + 10 imagenes + chip = 12 celdas (el chip cierra fila)',
    cp2.visibles === 10 && cp2.ocultas === 4 && (1 + 10 + 1) % 3 === 0,
    'visibles=' + cp2.visibles + ' ocultas=' + cp2.ocultas);

let alineado = true, detalle = '';
[3, 4, 6].forEach(function (columnas) {
    const caben = columnas * 2;              // grilla de 2 filas
    [0, 1, 2, 3].forEach(function (huecos) {
        for (let k = 1; k <= 24; k++) {
            const r = gal.puro.carga({ items: 40, caben: caben, huecos: huecos, tandas: k });
            const celdas = huecos + r.visibles + (r.hayChip ? 1 : 0);
            if (r.hayChip && celdas % columnas !== 0) {
                alineado = false;
                detalle = 'col=' + columnas + ' huecos=' + huecos + ' tandas=' + k + ' celdas=' + celdas;
            }
        }
    });
});
check('paso · con chip, celdas % columnas === 0 en TODAS las tandas', alineado, detalle);

const u1 = gal.puro.carga({ items: 16, caben: 8, tandas: 1 });
check('sin "+1" · un solo oculto que cabe se muestra (16 de 16, sin chip)',
    u1.visibles === 16 && u1.ocultas === 0 && u1.hayChip === false,
    'visibles=' + u1.visibles + ' ocultas=' + u1.ocultas);
const u2 = gal.puro.carga({ items: 16, tope: 16, caben: 8, tandas: 1 });
check('con tope · el "+1" SI queda cuando el tope de celdas no lo deja entrar',
    u2.visibles === 15 && u2.ocultas === 1 && u2.hayChip === true && u2.agotado === true,
    'visibles=' + u2.visibles + ' topeItems=' + u2.topeItems);

check('topeEnFilas · materializa el tope en filas completas',
    gal.puro.topeEnFilas(9, 6) === 6 && gal.puro.topeEnFilas(10, 4) === 8
    && gal.puro.topeEnFilas(12, 6) === 12 && gal.puro.topeEnFilas(4, 9) === 4
    && gal.puro.topeEnFilas(0, 6) === 0 && gal.puro.topeEnFilas(5, 0) === 5,
    'g(9,6)=' + gal.puro.topeEnFilas(9, 6) + ' g(10,4)=' + gal.puro.topeEnFilas(10, 4));
check('coleccion · el tope entra a `carga` ya en filas completas',
    /tope = topeEnFilas\(tope, porFila\);/.test(js)
    && /var porFila = \(layout === 'grilla' && columnas > 0\) \? columnas : cabenLinea;/.test(js));

const c4 = gal.puro.carga({ items: 5, tope: 0, caben: 6 });
check('carga · si todo cabe no hay chip ni navegacion',
    c4.visibles === 5 && c4.ocultas === 0 && c4.navega === false && c4.capacidad === 6);

const c5 = gal.puro.carga({ items: 5, tope: 0, caben: 4, huecos: 2 });
check('carga · los huecos descuentan la vista fija (y el chip)',
    c5.capacidad === 1 && c5.visibles === 1);
const c6 = gal.puro.carga({ items: 2, tope: 0, caben: 4, huecos: 2 });
check('carga · con todo a la vista los huecos no fuerzan chip',
    c6.capacidad === 2 && c6.visibles === 2 && c6.ocultas === 0);

check('navegable · "auto" es el default y el alias historico funciona',
    gal.puro.navegableDe({}) === 'auto'
    && gal.puro.navegableDe({ deslizable: true }) === true
    && gal.puro.navegableDe({ navegable: false }) === false
    && gal.puro.navegableDe({ navegable: 'si' }) === true);
check('el chip revela una pantalla (no pagina) y el estado es derivado',
    /function revelarSiguiente/.test(js) && /alRevelar/.test(js)
    && !/function paginarSiguiente/.test(js) && !/function tanda/.test(js));
check('la galeria publica su estado navegable', /data-mu-gal-estado/.test(js));
check('`alto` es opcion y con 0 se mide el marco real',
    /var altoVista = Math\.max\(0, parseInt\(o\.alto, 10\) \|\| 0\)/.test(js)
    && /var marcoH = nodo\.clientHeight \|\| 0/.test(js));
check('el marco solo acota si su contenido NO entra',
    /recorta = \(marcoH > 0\) && \(nodo\.scrollHeight > marcoH \+ 1\)/.test(js));
check('el recorte ya visto se conserva entre pintados',
    /!!\(estPrevio && estPrevio\.recorta\)/.test(js)
    && /tandas: tandas, firma: firma, recorta: recorta/.test(js));
check('la ventana fija el alto del marco (ya sin el pie, sin desbordar)',
    /linea\.style\.maxHeight = marcoFinal \+ 'px'/.test(js)
    && /marcoFinal = ajustarAltoFila\(\) \|\| marcoFinal/.test(js));
check('sin cota no hay chip (caben = todos)',
    js.indexOf('(cabenVista > 0) ? cabenVista : (items.length + huecos)') !== -1);
check('la raiz reparte pie + marco (flex column)',
    /\[data-mu-gal-layout\] \{[^}]*flex-direction: column/.test(css));
check('la fuente de la cota se publica para QA',
    /data-mu-gal-cota/.test(js) && /vent \? vent\.motivo : 'ancho'/.test(js));
check('el consumidor se entera de los repintados propios (onPintado)',
    /typeof o\.onPintado === 'function'/.test(js) && /o\.onPintado\(res\)/.test(js));
check('el listado del visor revela todo (navegable:true, sin chip)',
    /navegable: true,/.test(js)
    && js.indexOf('navegable: true,') > js.indexOf("o.listado === 'abajo'"));

/* ==================== 4g. Visor con listado inferior (F2) ==================== */
console.log('== Visor con listado ==');

check('el visor monta la coleccion con listado abajo',
    js.indexOf("o.listado === 'abajo'") !== -1 && /mu-gal-visor-lista/.test(js)
    && js.indexOf("preset: 'mini'") !== -1);
check('dos vias: la mini llama ir y mover pasa por ir',
    /onAbrir: function \(i\) \{ ir\(i\); \}/.test(js) && /function ir\(i\)/.test(js)
    && js.indexOf('ir(estado.pos + delta)') !== -1);
check('ir sincroniza el listado (marcarLista)',
    js.indexOf('marcarLista(s.pos)') !== -1 && /function marcarLista/.test(js));
check('la activa sobrevive al repintado por resize',
    js.indexOf('est.opciones.activo = pos') !== -1);
check('MU.ir esta expuesto', /^\s{8}ir: ir,$/m.test(js));
check('el listado tiene CSS propio', /\.mu-gal-visor-lista/.test(css));

/* ==================== 5. Reglas del PHP ==================== */
console.log('== Reglas del PHP ==');
check('registra el estilo', /wp_register_style\(\s*'mu-galeria'/.test(php));
check('registra el script', /wp_register_script\(\s*'mu-galeria'/.test(php));
check('expone mu_galeria_encolar', /function mu_galeria_encolar/.test(php));
check('encolado idempotente', /static \$pedida/.test(php));
check('no imprime HTML', !/echo |printf\(|<\?=/.test(phpCodigo));
check('registra ANTES de mu_enqueue_assets (998)', /', 998 \)/.test(php));
check('encola la ficha despues (1001)', /', 1001 \)/.test(php));
check('solo en is_product', /is_product\(\)/.test(php));
check('filtro para apagarla', /mu_galeria_en_ficha/.test(php));
check('cubre el encolado tardio en el footer', /wp_print_styles/.test(php));

/* ==================== 6. Cableado ==================== */
console.log('== Cableado ==');
check('el modulo se carga en functions.php', /mu_load_module\( 'galeria' \)/.test(func));
check('mu-product-js depende de mu-galeria', /'wc-single-product', 'mu-galeria'/.test(func));
check('product.js ya no tiene drag propio', !/scrollSnapType = 'none'/.test(prod));
check('product.js usa el gesto unico', /MUGaleria\.arrastrar|gal\.arrastrar/.test(prod));
check('product.js conecta la lupa', /navegarWoo/.test(prod));
check('la spec existe', fs.existsSync(path.join(RAIZ, 'specs/019-galeria-compartida/spec.md')));
check('el contrato existe',
    fs.existsSync(path.join(RAIZ, 'specs/019-galeria-compartida/contracts/galeria.md')));

/* ==================== 7. Cota exacta (D24bis, C9) ==================== */
console.log('== Cota exacta ==');
check('la linea publica data-cota', /setAttribute\('data-cota'/.test(jsCodigo));
check('la linea publica --fila-alto', /setProperty\('--fila-alto'/.test(jsCodigo));
check('el alto de la celda es REAL (medido en el DOM)', /querySelector\('\.mu-gal-celda\[data-mu-gal-mini\]'\)/.test(jsCodigo)
    && /\.offsetHeight/.test(jsCodigo));
check('el marco se recalcula con la misma funcion pura', /var real = ventana\(\{/.test(jsCodigo));
check('la fuente del tema se re-mide UNA vez', /function observarFuentes\(/.test(jsCodigo)
    && /doc\.fonts\.ready\.then/.test(jsCodigo));
check('el res publicado trae el marco FINAL', /altoMarco: marcoFinal/.test(jsCodigo));
check('el CSS fija la fila SOLO con cota',
    /\.mu-gal-linea--grilla\[data-cota\]\s*\{[^}]*grid-auto-rows:\s*var\(--fila-alto/.test(cssCodigo));
check('sin cota las filas siguen automaticas',
    !/\.mu-gal-linea--grilla\s*\{[^}]*grid-auto-rows/.test(cssCodigo));
check('la celda obedece el alto de la fila',
    /\[data-cota\][^{]*>\s*\.mu-gal-celda\s*\{[^}]*max-height:\s*var\(--fila-alto/.test(cssCodigo));

/* ==================== Meta normalizada (spec 019 V2, T043) ==================== */
console.log('== Meta (normalizarMeta) ==');
const nm = gal.puro.normalizarMeta;
{
    const vieja = nm({ badge: '31% OFF', badgeTipo: 'oferta', precio: { valor: 100, anterior: 130 } });
    check('la forma vieja da badge con tono y un precio',
        vieja.badge.texto === '31% OFF' && vieja.badge.tono === 'oferta'
        && vieja.precios.length === 1 && vieja.precios[0].anterior === 130);
    check('el badge string sin badgeTipo cae al tono tienda',
        nm({ badge: 'Nuevo' }).badge.tono === 'tienda');
    const nueva = nm({
        badge: { texto: 'Nuevo', tono: 'nuevo' },
        precios: [{ etiqueta: 'Regular', valor: 1 }, { etiqueta: 'Mayor', valor: 2, anterior: 3 }],
        nota: 'x'
    });
    check('la forma nueva: badge objeto, dos precios con etiqueta y nota',
        nueva.badge.tono === 'nuevo' && nueva.precios.length === 2
        && nueva.precios[0].etiqueta === 'Regular' && nueva.precios[1].anterior === 3
        && nueva.nota === 'x');
    check('un precio sin valor se descarta',
        nm({ precios: [{ etiqueta: 'x' }, { valor: 5 }] }).precios.length === 1);
    check('una meta vacia o ausente devuelve null',
        nm(null) === null && nm(undefined) === null && nm({}) !== null);
    check('un tono desconocido cae al tono tienda',
        nm({ badge: { texto: 'x', tono: 'turquesa' } }).badge.tono === 'tienda');
    check('guardada y comprada se conservan (las usa la biblioteca del plugin)',
        nm({ badge: 'x', badgeTipo: 'guardada' }).badge.tono === 'guardada'
        && nm({ badge: 'x', badgeTipo: 'comprada' }).badge.tono === 'comprada');
}

/* ==================== Resultado ==================== */
console.log('');
if (fallos > 0) {
    console.log('GALERIA FALLA (' + fallos + '/' + hechos + ' checks)');
    process.exit(1);
}
console.log('GALERIA OK (' + hechos + ' checks)');