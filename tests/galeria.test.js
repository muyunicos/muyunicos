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
check('expone version', gal.version === '1.0.0', 'version=' + gal.version);
['visor', 'cerrar', 'estaAbierto', 'tira', 'tarjeta', 'desdeWoo', 'desdeEnlaces',
    'autoInit', 'arrastrar', 'navegarWoo'].forEach((fn) => {
    check('expone ' + fn, typeof gal[fn] === 'function');
});
['acotar', 'preCargar', 'orden', 'formatearPrecio'].forEach((fn) => {
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
check('el contrato nuevo existe',
    fs.existsSync(path.join(RAIZ, 'specs/019-galeria-compartida/contracts/coleccion.md')));

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

/* ==================== Resultado ==================== */
console.log('');
if (fallos > 0) {
    console.log('GALERIA FALLA (' + fallos + '/' + hechos + ' checks)');
    process.exit(1);
}
console.log('GALERIA OK (' + hechos + ' checks)');