/**
 * Arnés del JS del lienzo del editor de banners (feature 013, Fases 2-3).
 *
 * Carga js/admin-hero-banners.js con un jQuery falso y comprueba las funciones puras que
 * sostiene el lienzo: el encaje (escala) en la columna. El resto del lienzo es manejo de
 * jQuery (patch por nodos, clic-para-foco, selector de ancho) y sigue siendo verificación
 * manual, la misma deuda declarada que 008/010/011/012.
 *
 * Se corre desde la raíz del repositorio:
 *   node specs\013-panel-hero-lienzo\harness\lienzo-js.js
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

function fakeJQuery(sel) {
    // `$('<div></div>')`: implementa html()/text() para poder probar textoPlanoDeNodo().
    if (typeof sel === 'string' && sel.charAt(0) === '<') {
        let inner = '';
        const nodo = {
            html(v) { if (v === undefined) { return inner; } inner = String(v); return nodo; },
            text() {
                return inner
                    .replace(/<[^>]*>/g, '')
                    .replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
            }
        };
        return nodo;
    }

    const stub = {
        length: 0,
        on() { return stub; }, off() { return stub; }, find() { return stub; },
        each() { return stub; }, hide() { return stub; }, show() { return stub; },
        is() { return false; }, toggle() { return stub; }, toggleClass() { return stub; },
        val() { return stub; }, attr() { return stub; }, prop() { return stub; },
        css() { return stub; }, text() { return stub; }, html() { return stub; },
        animate() { return stub; }, closest() { return stub; }, trigger() { return stub; },
        offset() { return { top: 0 }; }, width() { return 0; }, removeClass() { return stub; },
        addClass() { return stub; }
    };
    return stub;
}
global.jQuery = fakeJQuery;
global.$ = fakeJQuery;

eval(SRC);

const MU = global.muHeroBanners;

console.log('--- 1. Exposición para el arnés ---');
check('expone escalaCanvas junto a las funciones de 008/010/011/012',
    !!MU && 'function' === typeof MU.escalaCanvas && 'function' === typeof MU.anualIncompleta
    && 'function' === typeof MU.rangoInvertido && 'function' === typeof MU.parseDdmmaaaa
    && 'function' === typeof MU.fechaMalFormada && 'function' === typeof MU.clasificarCampos
    && 'function' === typeof MU.variantesDeFila);

if (!MU || 'function' !== typeof MU.escalaCanvas) {
    console.log('\n== 0 OK / 1 FAIL ==');
    process.exit(1);
}

console.log('\n--- 2. Encaje del lienzo en la columna ---');
same('columna más angosta que el banner (1000 de 1280)', 0.78125, MU.escalaCanvas(1000, 1280));
same('columna igual al banner', 1, MU.escalaCanvas(1280, 1280));
same('columna más ancha: NUNCA agranda', 1, MU.escalaCanvas(2400, 1280));
same('modo móvil en la misma columna', 1, MU.escalaCanvas(1000, 390));
same('ancho inválido no rompe (usa 1)', 1, MU.escalaCanvas(1000, 0));
same('disponible inválido no rompe (usa 1)', 1, MU.escalaCanvas(0, 1280));
same('entradas como texto se convierten', 0.5, MU.escalaCanvas('640', '1280'));
same('nunca baja del piso de legibilidad', 0.05, MU.escalaCanvas(10, 1280));

console.log('\n--- 3. Texto plano de un nodo editable (N2) ---');
same('texto simple', 'Cotizador Online', MU.textoPlanoDeNodo('Cotizador Online'));
same('los <br> vuelven a ser saltos de línea', 'uno\ndos', MU.textoPlanoDeNodo('uno<br>dos'));
same('<br/> también', 'uno\ndos', MU.textoPlanoDeNodo('uno<br/>dos'));
same('los <div> de Chrome son saltos', 'uno\ndos', MU.textoPlanoDeNodo('uno<div>dos</div>'));
same('el salto final no se arrastra', 'uno', MU.textoPlanoDeNodo('uno<br>'));
same('cierra las entidades (el HTML no se cuela al textarea)', 'a <b> & c', MU.textoPlanoDeNodo('a &lt;b&gt; &amp; c'));
same('vacío', '', MU.textoPlanoDeNodo(''));
same('null no rompe', '', MU.textoPlanoDeNodo(null));

console.log('\n== ' + ok + ' OK / ' + fail + ' FAIL ==');
process.exit(fail > 0 ? 1 : 0);
