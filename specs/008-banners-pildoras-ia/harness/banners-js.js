/**
 * Arnés del JS del admin de hero banners.
 *
 * Carga js/admin-hero-banners.js con un jQuery falso y comprueba las funciones puras
 * que deciden el mensaje del botón «Traducir»: qué campos se aplican, cuáles el
 * servicio considera «sin cambios» y cuáles no llegaron. Es la lógica que falló con
 * el mensaje "ya estaban en ese idioma".
 *
 * Se corre desde la raíz del repositorio:
 *   node specs\008-banners-pildoras-ia\harness\banners-js.js
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
        val() { return stub; }, attr() { return stub; }, prop() { return stub; },
        css() { return stub; }, text() { return stub; }, html() { return stub; }
    };
    return stub;
}
global.jQuery = fakeJQuery;

eval(SRC);

const MU = global.muHeroBanners;

console.log('--- 1. Exposición para el arnés ---');
check('expone clasificarCampos y mensajeTraduccion',
    !!MU && 'function' === typeof MU.clasificarCampos && 'function' === typeof MU.mensajeTraduccion);

if (!MU) {
    console.log('\n== 0 OK / 1 FAIL ==');
    process.exit(1);
}

console.log('\n--- 2. Clasificación de la respuesta ---');
const campos = ['titulo', 'descripcion', 'eyebrow'];

let r = MU.clasificarCampos(campos, {
    titulo: { translation: 'Title' },
    descripcion: { translation: 'Description' },
    eyebrow: { translation: 'Eyebrow' }
});
same('los tres se aplican', 3, r.traducidos);
same('con sus textos', 'Title', r.aplicar.titulo);
same('sin iguales ni faltantes', [0, 0], [r.iguales.length, r.faltantes.length]);

r = MU.clasificarCampos(campos, {
    titulo: { translation: '', unchanged: true },
    descripcion: { translation: 'Description' },
    eyebrow: { translation: 'Eyebrow' }
});
same('el unchanged queda como igual', ['titulo'], r.iguales);
same('y solo se aplican los otros dos', 2, r.traducidos);

r = MU.clasificarCampos(campos, { titulo: { translation: 'Title' } });
same('lo que no vino queda como faltante', ['descripcion', 'eyebrow'], r.faltantes);
same('y no se confunde con igual', 0, r.iguales.length);

r = MU.clasificarCampos(campos, { titulo: { unchanged: true }, descripcion: { translation: '   ' }, eyebrow: { translation: 'Eyebrow' } });
same('unchanged con texto tambien es igual', ['titulo', 'descripcion'], r.iguales);

r = MU.clasificarCampos(campos, null);
same('items nulo no rompe: todos faltantes', 3, r.faltantes.length);

console.log('\n--- 3. Mensajes (el bug que se está arreglando) ---');
let m = MU.mensajeTraduccion({ aplicar: {}, traducidos: 3, iguales: [], faltantes: [] }, 'en');
check('tres campos: verde y en plural', true === m.ok && m.texto.indexOf('3 campos traducidos') !== -1, m.texto);

m = MU.mensajeTraduccion({ aplicar: {}, traducidos: 1, iguales: [], faltantes: [] }, 'en');
check('un campo: singular', m.texto.indexOf('1 campo traducido') !== -1, m.texto);

m = MU.mensajeTraduccion({ aplicar: {}, traducidos: 2, iguales: ['eyebrow'], faltantes: [] }, 'en');
check('con iguales: los nombra', m.texto.indexOf('Sin cambios según el servicio: eyebrow') !== -1, m.texto);

m = MU.mensajeTraduccion({ aplicar: {}, traducidos: 0, iguales: [], faltantes: ['titulo', 'cta_texto'] }, 'en');
check('sin traduccion y con faltantes: lo dice', true === m.ok ? false : m.texto.indexOf('no devolvió traducción para: titulo, cta_texto') !== -1, m.texto);

m = MU.mensajeTraduccion({ aplicar: {}, traducidos: 0, iguales: ['titulo'], faltantes: [] }, 'en');
check('todo sin cambios: dice el idioma y los campos', m.texto.indexOf('ya están en EN: titulo') !== -1, m.texto);
check('y NO repite el mensaje viejo (regresión)', m.texto.indexOf('ya estaban en ese idioma') === -1, m.texto);

m = MU.mensajeTraduccion({ aplicar: {}, traducidos: 0, iguales: ['titulo'], faltantes: [] }, '');
check('idioma vacío no rompe', 'string' === typeof m.texto && m.texto.length > 0);

console.log('\n== ' + ok + ' OK / ' + fail + ' FAIL ==');
process.exit(fail > 0 ? 1 : 0);