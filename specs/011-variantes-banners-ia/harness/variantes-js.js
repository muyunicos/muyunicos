/**
 * Arnés de las variantes de traducción en el editor de banners (feature 011).
 *
 * Carga js/admin-hero-banners.js con un jQuery falso y comprueba las funciones puras que
 * deciden qué texto de la tanda se aplica a los campos del idioma activo y qué se le
 * informa al mantenedor. El manejo de jQuery (botones, tanda por fila) sigue siendo
 * verificación manual (deuda declarada en MIGRATION-GUIDE §8).
 *
 * Se corre desde la raíz del repositorio:
 *   node specs\011-variantes-banners-ia\harness\variantes-js.js
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
        css() { return stub; }, text() { return stub; }, html() { return stub; },
        data() { return null; }, empty() { return stub; }, append() { return stub; }
    };
    return stub;
}
global.jQuery = fakeJQuery;

eval(SRC);

const MU = global.muHeroBanners;

console.log('--- 1. Exposición para el arnés ---');
check('expone variantesDeFila y mensajeVariante',
    !!MU && 'function' === typeof MU.variantesDeFila && 'function' === typeof MU.mensajeVariante);

if (!MU || 'function' !== typeof MU.variantesDeFila) {
    console.log('\n== 0 OK / 1 FAIL ==');
    process.exit(1);
}

const campos = ['titulo', 'descripcion', 'eyebrow'];
const items  = {
    titulo:      { translation: 'T1', variants: ['T1', 'T2', 'T3'] },
    descripcion: { translation: 'D1', variants: ['D1', 'D2', 'D3'] },
    eyebrow:     { translation: 'E1', variants: ['E1', 'E2', 'E3'] }
};

console.log('\n--- 2. Qué texto se aplica (una versión por persona) ---');
let r = MU.variantesDeFila(items, 0, campos);
same('variante 1: los tres campos', 3, Object.keys(r.aplicar).length);
same('variante 1: los primeros textos', ['T1', 'D1', 'E1'], [r.aplicar.titulo, r.aplicar.descripcion, r.aplicar.eyebrow]);
same('sin faltantes', [], r.faltantes);

r = MU.variantesDeFila(items, 2, campos);
same('variante 3 se aplica a todos los campos', ['T3', 'D3', 'E3'], [r.aplicar.titulo, r.aplicar.descripcion, r.aplicar.eyebrow]);

console.log('\n--- 3. Campos sin esa versión (no se inventa nada) ---');
r = MU.variantesDeFila({
    titulo:      { variants: ['T1', 'T2'] },
    descripcion: { variants: ['D1'] },
    eyebrow:     { variants: ['E1', 'E2'] }
}, 1, campos);
same('el campo sin esa variante queda como faltante', ['descripcion'], r.faltantes);
check('y no se toca', !('descripcion' in r.aplicar));
same('los demás sí se aplican', ['T2', 'E2'], [r.aplicar.titulo, r.aplicar.eyebrow]);

console.log('\n--- 4. Ruta estándar (sin personas en el ámbito) ---');
r = MU.variantesDeFila({ titulo: { translation: 'T1', variants: ['T1'] } }, 1, campos);
same('sin segunda versión no hay nada que aplicar', {}, r.aplicar);
same('y se informan los campos', 3, r.faltantes.length);

console.log('\n--- 5. Casos borde ---');
same('items nulo no rompe', 0, Object.keys(MU.variantesDeFila(null, 0, campos).aplicar).length);
same('campos vacío no rompe', 0, Object.keys(MU.variantesDeFila(items, 0, []).aplicar).length);
same('variante fuera de rango: faltan todos', 3, MU.variantesDeFila(items, 9, campos).faltantes.length);
same('texto vacío cuenta como faltante', ['titulo'], MU.variantesDeFila({ titulo: { variants: ['T1', '   '] } }, 1, ['titulo']).faltantes);

console.log('\n--- 6. Mensajes del estado ---');
let m = MU.mensajeVariante(0, 'El Escritor Rápido', []);
check('variante 1 con persona: verde y con el nombre', true === m.ok && m.texto.indexOf('Variante 1 (El Escritor Rápido) aplicada') !== -1, m.texto);
check('y pide revisar antes de guardar', m.texto.indexOf('Revisá y guardá') !== -1, m.texto);

m = MU.mensajeVariante(2, '', []);
check('sin persona no rompe el mensaje', true === m.ok && m.texto.indexOf('Variante 3 aplicada') !== -1, m.texto);

m = MU.mensajeVariante(1, 'La Mamá Práctica', ['eyebrow', 'cta_texto']);
check('con faltantes: avisa y queda en no-ok', false === m.ok && m.texto.indexOf('Sin texto en: eyebrow, cta_texto.') !== -1, m.texto);

console.log('\n--- 7. Una tanda de variantes por idioma (018) ---');
check('expone tandaDeIdioma', !!MU && 'function' === typeof MU.tandaDeIdioma);

if (MU && 'function' === typeof MU.tandaDeIdioma) {
    const tandas = {
        pt: { lang: 'pt', items: { titulo: { variants: ['A', 'B'] } }, activa: 1 },
        en: { lang: 'en', items: {}, activa: 0 }
    };

    check('devuelve la tanda del idioma pedido', 'pt' === MU.tandaDeIdioma(tandas, 'pt').lang);
    same('y recuerda qué número quedó puesto', 1, MU.tandaDeIdioma(tandas, 'pt').activa);
    check('normaliza el idioma (PT ⇒ pt)', 'pt' === MU.tandaDeIdioma(tandas, 'PT').lang);
    same('un idioma sin tanda devuelve null', null, MU.tandaDeIdioma(tandas, 'es'));
    same('sin tandas devuelve null', null, MU.tandaDeIdioma(null, 'pt'));
    same('objeto vacío devuelve null', null, MU.tandaDeIdioma({}, 'pt'));
    // Lo que importa: mirar un idioma NO toca las opciones de los otros (era el bug: el cambio
    // de idioma las descartaba y al volver ya no estaban).
    check('consultar PT no borra las de EN', 'en' === MU.tandaDeIdioma(tandas, 'en').lang);
    check('y las dos siguen guardadas', 2 === Object.keys(tandas).length);
    check('la tanda de PT conserva sus variantes', 2 === MU.tandaDeIdioma(tandas, 'pt').items.titulo.variants.length);
}

console.log('\n== ' + ok + ' OK / ' + fail + ' FAIL ==');
process.exit(fail > 0 ? 1 : 0);
