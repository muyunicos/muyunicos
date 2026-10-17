/**
 * Arnés del JS del admin de hero banners — vigencia anual (feature 012).
 *
 * Carga js/admin-hero-banners.js con un jQuery falso y comprueba las funciones puras que
 * deciden los avisos del panel con el flag anual: cruzar el fin de año NO es un rango
 * invertido, y una sola fecha con el flag sí es un problema que hay que avisar.
 * El manejo de jQuery (casillas, puntos, placeholders) sigue siendo verificación manual
 * (deuda declarada en MIGRATION-GUIDE §8).
 *
 * Se corre desde la raíz del repositorio:
 *   node specs\012-vigencia-anual\harness\anual-js.js
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
check('expone rangoInvertido y anualIncompleta',
    !!MU && 'function' === typeof MU.rangoInvertido && 'function' === typeof MU.anualIncompleta);

if (!MU || 'function' !== typeof MU.anualIncompleta) {
    console.log('\n== 0 OK / 1 FAIL ==');
    process.exit(1);
}

console.log('\n--- 2. Rango invertido con repetición anual ---');
check('sin flag, rango normal', false === MU.rangoInvertido('01012026', '31122026'));
check('sin flag, rango invertido (sigue avisando)', true === MU.rangoInvertido('31122026', '01012026'));
check('con flag, cruzar el fin de año NO se avisa', false === MU.rangoInvertido('20122026', '10012027', true));
check('con flag, un rango invertido real tampoco se avisa (cruza el año)', false === MU.rangoInvertido('31122026', '01012026', true));
check('con flag y fechas vacías no hay aviso', false === MU.rangoInvertido('', '', true));
check('flag explícitamente falso se comporta como sin flag', true === MU.rangoInvertido('31122026', '01012026', false));

console.log('\n--- 3. Repetición anual incompleta (una sola fecha) ---');
same('sin flag, una sola fecha no se reporta', false, MU.anualIncompleta('01062026', '', false));
same('con flag, sólo inicio', true, MU.anualIncompleta('01062026', '', true));
same('con flag, sólo fin', true, MU.anualIncompleta('', '30062026', true));
same('con flag y las dos fechas', false, MU.anualIncompleta('01062026', '30062026', true));
same('con flag y ninguna fecha', false, MU.anualIncompleta('', '', true));
same('con flag, espacios alrededor no cuentan como fecha', true, MU.anualIncompleta('   ', '30062026', true));
same('null no rompe', true, MU.anualIncompleta(null, '30062026', true));

console.log('\n--- 4. Compatibilidad con el arnés de 010 (llamadas de 2 argumentos) ---');
check('rangoInvertido sin tercer argumento: rango normal', false === MU.rangoInvertido('01012026', '31122026'));
check('rangoInvertido sin tercer argumento: invertido', true === MU.rangoInvertido('31122026', '01012026'));
same('fechaMalFormada sigue expuesta y correcta', true, MU.fechaMalFormada('31022026'));
same('parseDdmmaaaa sigue expuesta y correcta', 20260601, MU.parseDdmmaaaa('01062026'));

console.log('\n== ' + ok + ' OK / ' + fail + ' FAIL ==');
process.exit(fail > 0 ? 1 : 0);
