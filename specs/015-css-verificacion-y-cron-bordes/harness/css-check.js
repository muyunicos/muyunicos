/**
 * Arnés de CSS (feature 015).
 *
 * Nace de un bug real: una edición dejó huérfana la segunda línea de un comentario
 * (`   Global siempre; … */` sin su `/*`), el navegador descartó la regla siguiente
 * (`.is-hereda { display: none }`) y el panel dejó de ocultar los países que heredan.
 * Ningún arnés de PHP/JS podía verlo: el marcado estaba bien, el CSS no se ejecuta en el
 * arnés. Este archivo cubre ese hueco.
 *
 * Verifica, sobre TODOS los .css del tema:
 *   1. comentarios balanceados (un `*​/` sin apertura → el bug de arriba);
 *   2. llaves balanceadas por archivo;
 *   3. las reglas clave del panel presentes (invariantes de las features 013/014).
 *
 * Se corre desde la raíz del repositorio:
 *   node specs\015-css-verificacion-y-cron-bordes\harness\css-check.js
 */
const fs = require('fs');
const path = require('path');

const RAIZ = path.join(__dirname, '..', '..', '..');

let ok = 0, fail = 0;
function check(label, cond, extra) {
    if (cond) { ok++; console.log('OK   ' + label); }
    else { fail++; console.log('FAIL ' + label + (extra === undefined ? '' : '  [' + extra + ']')); }
}

/** Archivos .css del tema (css/ y css/components/). */
function archivosCss() {
    const out = [];
    const recorrer = (dir) => {
        for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
            const p = path.join(dir, e.name);
            if (e.isDirectory()) { recorrer(p); }
            else if (e.name.toLowerCase().endsWith('.css')) { out.push(p); }
        }
    };
    recorrer(path.join(RAIZ, 'css'));
    return out;
}

/**
 * Comentarios balanceados. Función PURA sobre el texto: devuelve { ok, linea, motivo }.
 * Detecta el cierre sin apertura (el bug real) y la apertura sin cierre.
 */
function comentariosBalanceados(css) {
    let abiertos = 0, linea = 1;

    for (let i = 0; i < css.length; i++) {
        const c = css[i];

        if (c === '\n') { linea++; continue; }

        if (c === '/' && css[i + 1] === '*') { abiertos++; i++; continue; }

        if (c === '*' && css[i + 1] === '/') {
            if (abiertos === 0) { return { ok: false, linea, motivo: 'cierre sin apertura' }; }
            abiertos--; i++; continue;
        }
    }

    return abiertos === 0 ? { ok: true, linea: 0, motivo: '' } : { ok: false, linea: 0, motivo: 'comentario sin cerrar' };
}

/** Llaves balanceadas, ignorando lo que está dentro de comentarios. Función PURA. */
function llavesBalanceadas(css) {
    let abiertos = 0, i = 0;

    while (i < css.length) {
        if (css[i] === '/' && css[i + 1] === '*') {
            const fin = css.indexOf('*/', i + 2);
            i = (fin === -1) ? css.length : fin + 2;
            continue;
        }

        if (css[i] === '{') { abiertos++; }
        if (css[i] === '}') { abiertos--; if (abiertos < 0) { return false; } }

        i++;
    }

    return abiertos === 0;
}

console.log('--- 1. El detector funciona (casos sintéticos) ---');
check('CSS válido pasa', comentariosBalanceados('/* hola */\n.a { color: red; }\n/* chau */').ok);
check('Un cierre huérfano se detecta (el bug real)', !comentariosBalanceados('/* abre */\n.a{}\n   texto suelto */\n.b { display: none; }').ok);
check('Y dice en qué línea', 3 === comentariosBalanceados('/* abre */\n.a{}\n   texto suelto */\n.b { display: none; }').linea);
check('Un comentario sin cerrar se detecta', !comentariosBalanceados('/* abre\n.a{}').ok);
check('Llaves balanceadas: válido', llavesBalanceadas('.a { color: red; }'));
check('Llaves balanceadas: falta un cierre', !llavesBalanceadas('.a { color: red;'));
check('Llaves balanceadas: sobra un cierre', !llavesBalanceadas('.a { color: red; }}'));
check('Las llaves dentro de comentarios no cuentan', llavesBalanceadas('/* { } */ .a { color: red; }'));

console.log('\n--- 2. Todos los CSS del tema ---');
const todos = archivosCss();
check('Se revisaron los CSS del tema (' + todos.length + ' archivos)', todos.length >= 20);

todos.forEach((f) => {
    const rel = path.relative(RAIZ, f).replace(/\\/g, '/');
    const css = fs.readFileSync(f, 'utf8');
    const c   = comentariosBalanceados(css);

    check(
        rel + ': comentarios balanceados' + (c.ok ? '' : ' → ' + c.motivo + (c.linea ? ' en la línea ' + c.linea : '')),
        c.ok
    );
    check(rel + ': llaves balanceadas', llavesBalanceadas(css));
});

console.log('\n--- 3. Reglas clave del panel (invariantes de 013/014) ---');
const panelCss = fs.readFileSync(path.join(RAIZ, 'css', 'admin-hero-banners.css'), 'utf8');
const heroCss  = fs.readFileSync(path.join(RAIZ, 'css', 'components', 'hero-promo.css'), 'utf8');
const homeCss  = fs.readFileSync(path.join(RAIZ, 'css', 'home.css'), 'utf8');

check(
    'Los países que heredan NO se ven (el bug de «ver todos» era esta regla)',
    panelCss.includes('.mu-hero-banner-fechas-block.is-hereda { display: none; }')
);
check(
    '«ver todos los países» los vuelve a mostrar',
    panelCss.includes('.mu-hero-banner-tabla.is-ver-todos .mu-hero-banner-fechas-block.is-hereda { display: table-row; }')
);
check('La fila plegada oculta su cuerpo', panelCss.includes('.mu-hero-banner-row.is-folded .mu-hero-banner-row__body { display: none; }'));
check('El estado «sin países» tiene su estilo', panelCss.includes('.mu-hero-banner-row__estado.is-sin-paises'));
check('El modo móvil del lienzo responde al ancho del LIENZO', panelCss.includes('@container (max-width: 968px)'));
check('El lienzo es pegajoso en dos columnas', panelCss.includes('@media (min-width: 1100px)') && panelCss.includes('position: sticky'));
check('El componente del hero sigue siendo el del front (su bloque móvil)', heroCss.includes('@media (max-width: 968px)'));
check('Y conserva la animación del badge', heroCss.includes('@keyframes mu-hero-float'));
check('home.css ya NO tiene reglas del hero (se movieron al componente)', 0 === (homeCss.match(/\.mu-hero-promo/g) || []).length);

console.log('\n== ' + ok + ' OK / ' + fail + ' FAIL ==');
process.exit(fail > 0 ? 1 : 0);

