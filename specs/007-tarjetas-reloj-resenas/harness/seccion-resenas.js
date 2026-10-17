/**
 * Arnés de la sección de reseñas: tarjetas, reloj del botón y pausas.
 * Carga js/testimonials.js con un DOM simulado (sin navegador).
 */
const fs = require('fs');
const path = require('path');

// Temporizadores deterministas: el código bajo prueba se ejecuta ya, sin esperas reales.
// Así los arneses se pueden correr en paralelo sin falsos fallos (antes se pisaban).
// Reloj determinista: los temporizadores del código bajo prueba se ENCOLAN y se vacían
// cuando el arnés espera (wait()), nunca solos. Así no hay falsos fallos ni al correrlos
// en paralelo, y las aserciones «antes del retardo» siguen siendo válidas.
const realSetTimeout = setTimeout;
const muTimers = [];
global.setTimeout = ( fn ) => { muTimers.push( fn ); return muTimers.length; };
const flushTimers = () => { let guard = 0; while (muTimers.length && guard++ < 50) { muTimers.splice(0).forEach( fn => fn() ); } };
const SRC = fs.readFileSync(path.join(__dirname, '..', '..', '..', 'js', 'testimonials.js'), 'utf8');

let ok = 0, fail = 0;
function check(label, cond, extra) {
    if (cond) { ok++; console.log('OK   ' + label); }
    else { fail++; console.log('FAIL ' + label + (extra === undefined ? '' : '  [' + extra + ']')); }
}
const wait = () => new Promise( r => realSetTimeout( () => { flushTimers(); r(); }, 0 ) );

function mkStyle(log) {
    const s = {};
    ['animation', 'display', 'transform', 'opacity'].forEach(function (p) {
        let cur;
        Object.defineProperty(s, p, {
            get() { return cur; },
            set(v) { cur = v; if (log) { log.push(p + '=' + v); } }
        });
    });
    return s;
}

function loadModule(opts) {
    opts = opts || {};
    const log = [];
    const els = {};

    function mkEl(id) {
        const el = {
            id: id,
            style: mkStyle(log),
            classList: {
                _s: {},
                add(c) { this._s[c] = 1; },
                remove(c) { delete this._s[c]; },
                contains(c) { return !!this._s[c]; }
            },
            _l: {},
            _htmlWrites: 0,
            offsetWidth: 44,
            addEventListener(t, fn) { (this._l[t] = this._l[t] || []).push(fn); },
            fire(t, ev) {
                (this._l[t] || []).forEach(fn => fn.call(this, ev || {
                    preventDefault() {}, stopPropagation() {}, target: this
                }));
            },
            querySelector() { return null; }
        };
        Object.defineProperty(el, 'innerHTML', {
            get() { return el._html || ''; },
            set(v) { el._html = v; el._htmlWrites++; }
        });
        return el;
    }

    const container = mkEl('mu-reviews-container');
    const btn = mkEl('mu-btn-rotate');
    const arc = mkEl('mu-refresh-ring-arc');
    // opts.noRing simula una página con marcado viejo (botón sin anillo-reloj)
    btn.querySelector = sel => (opts.noRing ? null : (sel === '.mu-refresh-ring-arc' ? arc : null));
    // opts.waapi simula un navegador con Web Animations (getAnimations)
    const anims = [];
    if (opts.waapi) {
        anims.push({ currentTime: 4000 });
        arc.getAnimations = () => anims;
    }

    const ioCallbacks = [];
    const scheduled = [];
    const doc = {
        hidden: false,
        _l: {},
        addEventListener(t, fn) { (this._l[t] = this._l[t] || []).push(fn); },
        fire(t, ev) { (this._l[t] || []).forEach(fn => fn(ev || {})); }
    };

    const reviews = opts.empty ? [] : [
        { author_name: 'Ana', rating: 5, text: 'Excelente atención y envío rápido', time: 1788393600, purchase_photo_url: 'a.jpg' },
        { author_name: 'Beto', rating: 5, text: 'Muy recomendable, volvería a comprar', time: 1788393600 },
        { author_name: 'Ceci', rating: 4, text: 'Buen producto', time: 1788393600, purchase_photo_url: 'c.jpg' },
        { author_name: 'Dani', rating: 5, text: 'Llegó perfecto', time: 1788393600 },
        { author_name: 'Eva', rating: 5, text: 'Todo bien', time: 1788393600 }
    ];

    global.window = global;
    global.document = {
        hidden: false,
        _l: doc._l,
        addEventListener: doc.addEventListener.bind(doc),
        fire: doc.fire.bind(doc),
        getElementById: id => (id === 'mu-reviews-container' ? container : (id === 'mu-btn-rotate' ? btn : null))
    };
    global.muTestimonials = {
        reviews: reviews, lang: 'es', isAdmin: false, i18n: {},
        ajaxUrl: '/ajax', nonce: 'n'
    };
    global.matchMedia = () => ({ matches: !!opts.reduce });
    global.getComputedStyle = () => ({ getPropertyValue: () => '10s' });
    global.IntersectionObserver = function (cb) { ioCallbacks.push(cb); this.observe = function () {}; };
    global.console.warn = function () {};
    global.setInterval = function (fn, ms) { const h = { fn: fn, ms: ms, cleared: false }; scheduled.push(h); return h; };
    global.clearInterval = function (h) { if (h) { h.cleared = true; } };

    eval(SRC);
    doc.fire('DOMContentLoaded');

    return { container, btn, arc, anims, ioCallbacks, scheduled, log, doc: global.document };
}

(async function () {
    console.log('--- Escenario 1: marcado y reloj normal ---');
    const h = loadModule({});
    const c = h.container, b = h.btn, arc = h.arc;

    check('renderiza 3 tarjetas al cargar',
        c._htmlWrites === 1 && (c.innerHTML.match(/mu-review-card/g) || []).length === 3,
        'escrituras=' + c._htmlWrites);
    check('el HTML generado NO incluye el botón de rotación (ya es estático)',
        !/mu-nav-btn/.test(c.innerHTML) && !/mu-btn-rotate/.test(c.innerHTML));
    check('el comentario va dentro de .mu-review-body', /mu-review-body/.test(c.innerHTML));
    check('la foto de compra es miniatura .mu-purchase-photo (sin estilos inline)',
        /mu-purchase-photo/.test(c.innerHTML) && !/max-width:100%/.test(c.innerHTML));
    check('la fecha usa .mu-review-time (sin estilos inline)',
        /mu-review-time/.test(c.innerHTML) && !/margin-top:auto/.test(c.innerHTML));
    check('el recorte por palabras quedó como red de seguridad (45)',
        !/truncate\(reviewText\(review\), 20\)/.test(SRC) && /WORD_LIMIT/.test(SRC));
    check('sin reduced motion no se programa temporizador', h.scheduled.length === 0,
        'timers=' + h.scheduled.length);

    const w1 = c._htmlWrites;
    arc.fire('animationiteration');
    check('al completar una vuelta el icono gira (is-spinning)', b.classList.contains('is-spinning'));
    await wait();
    check('y la vuelta carga otras reseñas', c._htmlWrites === w1 + 1, 'escrituras=' + c._htmlWrites);
    check('el giro y la atenuación se limpian al terminar',
        !b.classList.contains('is-spinning') && !c.classList.contains('is-loading'));

    const w2 = c._htmlWrites;
    b.fire('mouseenter');
    check('cursor sobre el botón: el reloj se pausa (is-paused)', b.classList.contains('is-paused'));
    arc.fire('animationiteration');
    await wait();
    check('con el cursor sobre el botón NO carga más reseñas', c._htmlWrites === w2,
        'escrituras=' + c._htmlWrites);
    b.fire('mouseleave');
    check('al salir el cursor el reloj sigue (sin is-paused)', !b.classList.contains('is-paused'));
    arc.fire('animationiteration');
    await wait();
    check('y vuelve a cargar reseñas', c._htmlWrites === w2 + 1, 'escrituras=' + c._htmlWrites);

    const w3 = c._htmlWrites;
    c.fire('mouseenter');
    check('cursor sobre una reseña: el reloj se pausa', b.classList.contains('is-paused'));
    arc.fire('animationiteration');
    await wait();
    check('posado sobre una reseña NO carga más reseñas', c._htmlWrites === w3,
        'escrituras=' + c._htmlWrites);
    c.fire('mouseleave');
    arc.fire('animationiteration');
    await wait();
    check('al dejar de posarse, vuelve a cargar', c._htmlWrites === w3 + 1, 'escrituras=' + c._htmlWrites);

    const w4 = c._htmlWrites;
    const noneBeforeClick = h.log.filter(l => l === 'animation=none').length;
    b.fire('click');
    arc.fire('animationiteration');   // vuelta rezagada: la que estaba por terminar
    await wait();
    check('el clic carga otras reseñas en el acto', c._htmlWrites === w4 + 1, 'escrituras=' + c._htmlWrites);
    check('la vuelta rezagada del clic no duplica la carga', c._htmlWrites === w4 + 1,
        'escrituras=' + c._htmlWrites);
    check('el clic reinicia el contador (respaldo por estilos: animation:none)',
        h.log.filter(l => l === 'animation=none').length > noneBeforeClick);

    const noneBefore = h.log.filter(l => l === 'animation=none').length;
    h.doc.hidden = true;
    h.doc.fire('visibilitychange');
    check('pestaña en segundo plano: el reloj se pausa', b.classList.contains('is-paused'));
    h.doc.hidden = false;
    h.doc.fire('visibilitychange');
    check('al volver: reloj reiniciado y en marcha',
        !b.classList.contains('is-paused')
        && h.log.filter(l => l === 'animation=none').length > noneBefore);

    h.ioCallbacks.forEach(cb => cb([{ isIntersecting: false }]));
    check('sección fuera de pantalla: en pausa', b.classList.contains('is-paused'));
    h.ioCallbacks.forEach(cb => cb([{ isIntersecting: true }]));
    check('sección a la vista: en marcha', !b.classList.contains('is-paused'));


    console.log('--- Escenario 2: prefers-reduced-motion ---');
    const h2 = loadModule({ reduce: true });
    check('sin animación no se engancha animationiteration',
        h2.arc._l['animationiteration'] === undefined);
    check('se programa un temporizador con la duración del CSS (10s)',
        h2.scheduled.length === 1 && h2.scheduled[0].ms === 10000 && h2.scheduled[0].cleared === false,
        JSON.stringify(h2.scheduled.map(s => s.ms)));
    const r1 = h2.container._htmlWrites;
    h2.scheduled[0].fn();
    await wait();
    check('el temporizador rota las reseñas igual', h2.container._htmlWrites === r1 + 1);
    h2.btn.fire('mouseenter');
    check('en pausa el temporizador se cancela', h2.scheduled[0].cleared === true);
    h2.btn.fire('mouseleave');
    check('al salir el cursor el temporizador vuelve',
        h2.scheduled.length === 2 && h2.scheduled[1].cleared === false);

    console.log('--- Escenario 3: marcado viejo (botón sin anillo) ---');
    const h4 = loadModule({ noRing: true });
    check('sin anillo no se engancha animationiteration',
        h4.arc._l['animationiteration'] === undefined);
    check('sin anillo el ciclo lo lleva el temporizador (10s)',
        h4.scheduled.length === 1 && h4.scheduled[0].ms === 10000,
        JSON.stringify(h4.scheduled.map(s => s.ms)));
    const q1 = h4.container._htmlWrites;
    h4.btn.fire('click');
    await wait();
    check('el clic sigue rotando sin anillo', h4.container._htmlWrites === q1 + 1);
    h4.btn.fire('mouseenter');
    check('sin anillo, en pausa se cancela el temporizador',
        h4.scheduled[0].cleared === true);

    console.log('--- Escenario 4: sin reseñas ---');
    const h3 = loadModule({ empty: true });
    check('sin reseñas el botón de rotación no se muestra',
        h3.btn.style.display === 'none', 'display=' + h3.btn.style.display);

    console.log('--- Escenario 5: reinicio del contador con Web Animations ---');
    const h5 = loadModule({ waapi: true });
    check('al arrancar, el contador del anillo vuelve a cero (currentTime = 0)',
        h5.anims[0].currentTime === 0, 'currentTime=' + h5.anims[0].currentTime);
    check('el reinicio usa Web Animations y no toca estilos',
        !h5.log.some(l => l === 'animation=none'));

    h5.anims[0].currentTime = 7000;
    h5.btn.fire('click');
    await wait();
    check('el clic pone el contador en cero', h5.anims[0].currentTime === 0,
        'currentTime=' + h5.anims[0].currentTime);

    console.log('\n== ' + ok + ' OK / ' + fail + ' FAIL ==');
    process.exit(fail > 0 ? 1 : 0);

}());

