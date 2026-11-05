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