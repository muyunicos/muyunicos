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

const panelCss = fs.readFileSync(path.join(RAIZ, 'css', 'admin-hero-banners.css'), 'utf8');
const heroCss  = fs.readFileSync(path.join(RAIZ, 'css', 'components', 'hero-promo.css'), 'utf8');
const homeCss  = fs.readFileSync(path.join(RAIZ, 'css', 'home.css'), 'utf8');
const styleCss = fs.readFileSync(path.join(RAIZ, 'style.css'), 'utf8');   // [016] tokens mu-base

console.log('\n--- 3b. Fidelidad del lienzo (016): el componente del front se ve igual en el panel ---');

/** Cuerpo de la PRIMERA regla cuyo selector sea exactamente el pedido. */
function bloqueDe(css, selector) {
    const i = css.indexOf(selector + ' {');
    if (i === -1) { return ''; }
    const j = css.indexOf('\n}', i);
    return j === -1 ? '' : css.slice(i, j);
}

const varsUsadas   = Array.from(new Set(Array.from(heroCss.matchAll(/var\((--[a-z0-9-]+)/g), (m) => m[1])));
const varsDeclaradas = new Set([
    ...Array.from(heroCss.matchAll(/(--[a-z0-9-]+)\s*:/g), (m) => m[1]),
    ...Array.from(bloqueDe(panelCss, '.mu-hero-banner-canvas').matchAll(/(--[a-z0-9-]+)\s*:/g), (m) => m[1]),
    ...Array.from(styleCss.matchAll(/(--[a-z0-9-]+)\s*:/g), (m) => m[1])
]);
const varsHuerfanas = varsUsadas.filter((v) => !varsDeclaradas.has(v));

check(
    'Todas las variables que usa el componente están declaradas (componente, lienzo o style.css)' +
        (varsHuerfanas.length ? ' → faltan: ' + varsHuerfanas.join(', ') : ''),
    varsHuerfanas.length === 0
);

const slider = bloqueDe(heroCss, '.mu-hero-promo-slider');
check(
    'Las variables locales del hero viven en el slider (raíz que el lienzo SÍ incluye, no .mu-hero-promo)',
    ['--mu-hero-h-desktop', '--mu-hero-transition', '--mu-hero-eyebrow-bg'].every((v) => slider.includes(v + ':'))
);
check(
    'El slider declara también su altura mínima con esa variable (si no, el lienzo queda sin ella)',
    slider.includes('min-height: var(--mu-hero-h-desktop')
);

const mediasComponente = (heroCss.match(/@media \(max-width: 968px\)/g) || []).length;
check('El componente tiene su bloque móvil por @media (ventana): ' + mediasComponente, mediasComponente === 1);
check('El lienzo replica el móvil con @container', panelCss.includes('@container (max-width: 968px)'));
check(
    'Y agrega el ESPEJO de escritorio por @container (si la ventana del admin es angosta, la preview no se vuelve móvil)',
    panelCss.includes('@container (min-width: 969px)')
);

['.mu-btn', '.mu-btn-primary', '.mu-btn-outline'].forEach((sel) => {
    check('El lienzo tiene ' + sel, panelCss.includes('.mu-hero-banner-canvas ' + sel + ' {'));
});
['.mu-btn-primary:hover', '.mu-btn-outline:hover'].forEach((sel) => {
    check('El lienzo tiene ' + sel + ' (los CTA reaccionan como en la home)', panelCss.includes('.mu-hero-banner-canvas ' + sel));
});

check(
    'La caja del bloque de idioma aplica a los TRES idiomas (no sólo PT/EN)',
    /\.mu-hero-banner-lang-block\s*\{[^}]*border:/.test(panelCss)
        && !panelCss.includes('.mu-hero-banner-lang-block[data-lang="pt"],\n.mu-hero-banner-lang-block[data-lang="en"] {')
);

console.log('\n--- 3c. Un solo bloque de idioma (017) ---');
check(
    'Las variantes se ocultan con `hidden`, no con display (un display:block a secas anularía el atributo)',
    panelCss.includes('.mu-hero-banner-variante:not([hidden]) { display: block; }')
        && !/\.mu-hero-banner-variante\s*\{[^}]*display:/.test(panelCss)
);
check(
    'La línea del grupo es flex: rótulo a la izquierda, toolbar a la derecha',
    /\.mu-hero-banner-group-title\s*\{[^}]*justify-content: space-between/.test(panelCss)
);
check(
    'El toolbar ya NO es una barra con borde propio (vive en la línea del grupo)',
    !/\.mu-hero-banner-row__head\s*\{[^}]*border-bottom/.test(panelCss)
        && /\.mu-hero-banner-row__head\s*\{[^}]*text-transform: none/.test(panelCss)
);

console.log('\n--- 4. Reglas clave del panel (invariantes de 013/014) ---');

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
check(
    'El lienzo va al LADO del bloque de idioma, con filas explícitas y sin filas infinitas (016)' +
        ' (nada de grid-row «span 30»: era el hueco de abajo)',
    panelCss.includes('@media (min-width: 1100px)')
        && panelCss.includes('grid-template-columns: minmax(320px, 420px) minmax(400px, 1fr)')
        && !panelCss.includes('grid-row: 1 / span 30')
        && !panelCss.includes('position: sticky')
);
check(
    'Y el sobrante va al LIENZO, no a una celda vacía (018: en pantalla ancha se perdían ~540px)',
    !panelCss.includes('minmax(320px, 420px) minmax(320px, 400px) minmax(0, 1fr)')
);
check(
    'La fila de ajustes/vigencia ocupa el ancho completo (es la que necesita ≥720px)',
    panelCss.includes('.mu-hero-banner-row__grid > .mu-hero-banner-globals-block {')
        && panelCss.includes('grid-column: 1 / -1')
);
check(
    'La tabla de vigencia se desplaza en horizontal si el panel es angosto',
    panelCss.includes('.mu-hero-banner-tabla-scroll { overflow-x: auto; }')
);
check('El componente del hero sigue siendo el del front (su bloque móvil)', heroCss.includes('@media (max-width: 968px)'));
check('Y conserva la animación del badge', heroCss.includes('@keyframes mu-hero-float'));
check('home.css ya NO tiene reglas del hero (se movieron al componente)', 0 === (homeCss.match(/\.mu-hero-promo/g) || []).length);

console.log('\n== ' + ok + ' OK / ' + fail + ' FAIL ==');
process.exit(fail > 0 ? 1 : 0);

