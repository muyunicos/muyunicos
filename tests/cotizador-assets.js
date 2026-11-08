/**
 * cotizador-assets - Puerta de los artefactos del COTIZADOR en el TEMA (spec 020, D8).
 *
 * Los 4 artefactos (bundle JS, CSS y los dos PHP) se PUBLICAN desde el repo hermano
 * `calculadora` con `npm run pub:tema`. Esta puerta verifica que lo del tema sea
 * EXACTAMENTE lo de la fuente (SHA256), para que nadie commitee un bundle viejo ni un
 * PHP que se olvido de publicar.
 *
 * En el servidor no existe el repo `calculadora`: la puerta se declara PENDIENTE y sale 0.
 *
 *   node tests/cotizador-assets.js  ->  COTIZADOR ASSETS OK (N checks)
 */
'use strict';

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const RAIZ = path.join(__dirname, '..');
const FUENTE = path.join(RAIZ, '..', 'calculadora', 'assets');
const PARES = [
    'js/calculadora_stickers.js',
    'css/calculadora_stickers.css',
    'datos_config_etag.php',
    'guardar_datos.php'
];

if (!fs.existsSync(FUENTE)) {
    console.log('COTIZADOR ASSETS: pendiente (no hay repo calculadora al lado)');
    process.exit(0);
}

const sha = (archivo) => crypto.createHash('sha256').update(fs.readFileSync(archivo)).digest('hex');

let ok = 0;
let fallos = 0;
function check(nombre, cond, detalle) {
    if (cond) { ok++; console.log('  OK   ' + nombre); }
    else { fallos++; console.log('  FALLA ' + nombre + (detalle ? ' :: ' + detalle : '')); }
}

PARES.forEach(function (rel) {
    const a = path.join(FUENTE, rel);
    const b = path.join(RAIZ, 'assets', rel);
    if (!fs.existsSync(a) || !fs.existsSync(b)) {
        check('existe ' + rel, false, !fs.existsSync(a) ? 'falta en calculadora' : 'falta en el tema');
        return;
    }
    const ha = sha(a);
    const hb = sha(b);
    check('el tema tiene la MISMA version de ' + rel, ha === hb,
        ha === hb ? '' : 'corre `npm run pub:tema` en calculadora');
});

// El shortcode versionado tiene que existir: sin el, el deploy deja la pagina vacia.
const sc = path.join(RAIZ, 'inc', 'cotizador.php');
check('existe inc/cotizador.php (shortcode versionado)', fs.existsSync(sc));
if (fs.existsSync(sc)) {
    const t = fs.readFileSync(sc, 'utf8');
    check('el shortcode encola el motor de galeria', /mu_galeria_encolar/.test(t));
    check('el contenedor declara el tema del cotizador', /data-mu-tema="cotizador"/.test(t));
    check('registra el shortcode [calculadora_stickers]', /add_shortcode\(\s*'calculadora_stickers'/.test(t));
}

console.log('');
if (fallos === 0) { console.log('COTIZADOR ASSETS OK (' + ok + ' checks)'); process.exit(0); }
console.log('COTIZADOR ASSETS FALLA (' + fallos + ' de ' + (ok + fallos) + ')');
process.exit(1);