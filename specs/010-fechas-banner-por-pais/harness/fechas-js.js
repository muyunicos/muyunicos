/**
 * Arnés del JS del admin de hero banners — fechas por país (feature 010).
 *
 * Carga js/admin-hero-banners.js con un jQuery falso y comprueba las funciones puras
 * que deciden el aviso de rango invertido: el parseo de DDMMAAAA y la comparación.
 * El manejo de jQuery (píldoras de país, puntos, placeholders) sigue siendo
 * verificación manual (deuda declarada en MIGRATION-GUIDE §8).
 *
 * Se corre desde la raíz del repositorio:
 *   node specs\010-fechas-banner-por-pais\harness\fechas-js.js
 */
const fs = require('fs');
const path = require('path');

const SRC = fs.readFileSync(path.join(__dirname, '..', '..', '..', 'js', 'admin-hero-banners.js'), 'utf8');

let ok = 0, fail = 0;
function check(label, cond, extra) {
    if (cond) { ok++; console.log('OK   ' + label); }
    else { fail++; console.log('FAIL ' + label + (extra === undefined ? '' : '  [' + extra + ']')); }
}
function same(label, esperado, obtenido) {
    check(label, JSON.stringify(esperado) === JSON.stringify(obtenido),
        'esperado=' + JSON.stringify(esperado) + ' obtenido=' + JSON.stringify(obtenido));
}

/* --- navegador simulado --- */
global.window = global;
global.document = { addEventListener() {}, getElementById() { return null; }, querySelectorAll() { return []; } };

/** jQuery falso: devuelve un objeto encadenable y NO ejecuta los callbacks de ready. */
function fakeJQuery() {
    const stub = {
        length: 0,
        on() { return stub; }, off() { return stub; }, find() { return stub; },
        each() { return stub; }, hide() { return stub; }, show() { return stub; },
        is() { return false; }, toggle() { return stub; }, toggleClass() { return stub; },
        val() { return stub; }, attr() { return stub; }, prop() { return stub; },
        css() { return stub; }, text() { return stub; }, html() { return stub; }
    };
    return stub;
}
global.jQuery = fakeJQuery;

eval(SRC);

const MU = global.muHeroBanners;

console.log('--- 1. Exposición para el arnés ---');
check('expone parseDdmmaaaa, rangoInvertido y fechaMalFormada',
    !!MU && 'function' === typeof MU.parseDdmmaaaa && 'function' === typeof MU.rangoInvertido
    && 'function' === typeof MU.fechaMalFormada);

if (!MU || 'function' !== typeof MU.parseDdmmaaaa) {
    console.log('\n== 0 OK / 1 FAIL ==');
    process.exit(1);
}

console.log('\n--- 2. Parseo de DDMMAAAA ---');
same('fecha completa', 20260601, MU.parseDdmmaaaa('01062026'));
same('con espacios alrededor', 20260601, MU.parseDdmmaaaa(' 01062026 '));
same('vacío', null, MU.parseDdmmaaaa(''));
same('null no rompe', null, MU.parseDdmmaaaa(null));
same('siete dígitos', null, MU.parseDdmmaaaa('1062026'));
same('formato ISO', null, MU.parseDdmmaaaa('2026-06-01'));
same('con letras', null, MU.parseDdmmaaaa('0106202X'));

console.log('\n--- 3. Rango invertido (aviso) ---');
check('rango normal', false === MU.rangoInvertido('01012026', '31122026'));
check('rango invertido', true === MU.rangoInvertido('31122026', '01012026'));
check('mismo día', false === MU.rangoInvertido('01062026', '01062026'));
check('inicio vacío: sin aviso', false === MU.rangoInvertido('', '31122026'));
check('fin vacío: sin aviso', false === MU.rangoInvertido('01012026', ''));
check('formato inválido: sin aviso', false === MU.rangoInvertido('x', '31122026'));

console.log('\n--- 4. Formato y fecha real (aviso del panel) ---');
same('vacío no está mal formado', false, MU.fechaMalFormada(''));
same('ocho dígitos está bien', false, MU.fechaMalFormada('01062026'));
same('con guiones está mal formado', true, MU.fechaMalFormada('2026-06-01'));
same('letras están mal formadas', true, MU.fechaMalFormada('abcdefgh'));
same('siete dígitos está mal formado', true, MU.fechaMalFormada('1062026'));
same('null no rompe', false, MU.fechaMalFormada(null));
same('31 de febrero no existe', true, MU.fechaMalFormada('31022026'));
same('29 de febrero en bisiesto está bien', false, MU.fechaMalFormada('29022024'));
same('29 de febrero en no bisiesto está mal', true, MU.fechaMalFormada('29022026'));
same('mes 13 no existe', true, MU.fechaMalFormada('31132026'));
same('día 99 no existe', true, MU.fechaMalFormada('99012026'));
same('día 00 no existe', true, MU.fechaMalFormada('00012026'));
same('30 de abril existe', false, MU.fechaMalFormada('30042026'));
same('31 de abril no existe', true, MU.fechaMalFormada('31042026'));

console.log('\n== ' + ok + ' OK / ' + fail + ' FAIL ==');
process.exit(fail > 0 ? 1 : 0);
