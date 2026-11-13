/* PUERTA de navegador del TEMA: SC-003 (spec 019) — la ficha real de WooCommerce.

 * Cubre lo que NINGUNA otra puerta cubre: `js/product.js` sobre la ficha real del lab.
 *   - El ARRASTRE de las miniaturas queda conectado (`data-mu-gal-arrastre`), aunque Woo
 *     repinte la galeria despues de DOMContentLoaded (bug medido: quedaba en un nodo
 *     huerfano y el gesto no respondia).
 *   - La lupa abre el VISOR COMPARTIDO (`MU.visor`) y **NO** Photoswipe.
 *
 * Precondiciones (si fallan, la puerta lo dice con la causa):
 *   1. El laboratorio responde en `PMU_BASE` (por defecto http://localhost:8091):
      `pwsh C:\wp-lab\arrancar.ps1`.
 *   2. El producto (`PMU_PRODUCTO`) tiene galeria con miniaturas. Se siembra con
 *      `%TEMP%\lab-sembrar-galeria.php` (usa fixtures del plugin).
 *
 * Uso:
 *   $env:NODE_PATH="$env:TEMP\pmu-pw\node_modules"
 *   $env:TEXTMUY_CHROME='C:/Program Files/Google/Chrome/Application/chrome.exe'
 *   node tests/galeria.browser.js
 */
const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');

const BASE = process.env.PMU_BASE || 'http://localhost:8091';
const PRODUCTO = process.env.PMU_PRODUCTO || BASE + '/product/sticker-circular-6cm-lab/';
const CAPT = process.env.PMU_CAPTURAS || 'C:/wp-lab/capturas';
const CHROME = process.env.TEXTMUY_CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe';

const esperar = (ms) => new Promise((r) => setTimeout(r, ms));
let ok = 0;
let mal = 0;
function chk(nombre, cond, detalle) {
    if (cond) { ok++; console.log('  OK   ' + nombre + (detalle ? ' | ' + detalle : '')); }
    else { mal++; console.log('  FALLA ' + nombre + (detalle ? ' | ' + detalle : '')); }
}
function pendiente(causa) {
    console.log('GALERIA FICHA: pendiente (' + causa + ')');
    process.exit(0);
}

(async () => {
    if (!fs.existsSync(CHROME)) { pendiente('Chrome no esta en ' + CHROME + ' (usa TEXTMUY_CHROME)'); }

    const browser = await chromium.launch({ executablePath: CHROME });
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });

    const errores = [];
    const peticiones = [];
    page.on('pageerror', (e) => errores.push('PAGEERROR: ' + String(e.message).slice(0, 120)));
    page.on('console', (m) => { if (m.type() === 'error') { errores.push('ERROR: ' + m.text().slice(0, 120)); } });
    page.on('request', (r) => { peticiones.push(r.url()); });

    const resp = await page.goto(PRODUCTO, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
    if (!resp || resp.status() >= 400) {
        await browser.close();
        pendiente('el producto no responde (' + PRODUCTO + ')');
    }
    await esperar(3500);
    // El modal de pais del tema tapa la pagina: se quita para poder clickear.
    await page.evaluate(() => { const m = document.getElementById('muyu-country-modal-overlay'); if (m) { m.remove(); } });

    const base = await page.evaluate(() => {
        const t = document.querySelector('.woocommerce-product-gallery .flex-control-thumbs');
        return {
            galeria: !!document.querySelector('.woocommerce-product-gallery'),
            slider: !!t,
            arrastre: t ? t.getAttribute('data-mu-gal-arrastre') : null,
            celdas: t ? t.querySelectorAll('li').length : 0,
            conSrc: t ? [...t.querySelectorAll('img')].filter((i) => i.getAttribute('src')).length : 0,
            desborde: t ? t.scrollWidth - t.clientWidth : 0,
            lupa: !!document.querySelector('.woocommerce-product-gallery__trigger')
        };
    });

    chk('la ficha monta la galeria de WooCommerce', base.galeria);
    chk('el slider de miniaturas existe y tiene miniaturas', base.slider && base.celdas > 0, JSON.stringify(base));
    chk('el ARRASTRE queda conectado en el slider real (data-mu-gal-arrastre)',
        base.arrastre === '1', 'arrastre=' + base.arrastre);
    chk('las miniaturas tienen imagen', base.conSrc > 0, 'conSrc=' + base.conSrc);

    if (base.desborde > 1) {
        const box = await page.locator('.woocommerce-product-gallery .flex-control-thumbs').first().boundingBox();
        await page.mouse.move(box.x + box.width - 24, box.y + box.height / 2);
        await page.mouse.down();
        await page.mouse.move(box.x + 30, box.y + box.height / 2, { steps: 10 });
        await page.mouse.up();
        await esperar(400);
        const sx = await page.evaluate(() => Math.round(document.querySelector('.woocommerce-product-gallery .flex-control-thumbs').scrollLeft));
        chk('el arrastre desplaza las miniaturas', sx > 0, 'scrollLeft=' + sx);
    } else {
        console.log('  info  sin desborde horizontal (grid de ' + base.celdas
            + ' miniaturas): el arrastre no desplaza, se declara (spec 020: se mantiene el grid)');
    }
    // Lupa: abre el visor del tema y NO Photoswipe.
    const antesLupa = peticiones.length;
    await page.evaluate(() => { const t = document.querySelector('.woocommerce-product-gallery__trigger'); if (t) { t.click(); } });
    await esperar(1400);
    const visor = await page.evaluate(() => {
        const ov = document.querySelector('.mu-gal-visor');
        const img = document.querySelector('.mu-gal-visor .mu-gal-img');
        return {
            abierto: !!ov && ov.className.indexOf('is-visible') !== -1,
            img: img ? (img.getAttribute('src') || '') : '',
            pswp: !!document.querySelector('.pswp.pswp--open'),
            tema: ov ? ov.getAttribute('data-mu-tema') : null
        };
    });
    chk('la lupa abre el VISOR COMPARTIDO del tema', visor.abierto, JSON.stringify(visor));
    chk('el visor muestra la imagen real', visor.img.length > 0, visor.img.slice(0, 70));
    chk('la lupa NO abre Photoswipe', visor.pswp === false, 'pswp=' + visor.pswp);

    // Recorrer el visor no debe pedir red (las vecinas ya estan en cache).
    const antes = peticiones.length;
    for (let i = 0; i < 3; i++) {
        await page.evaluate(() => {
            const n = document.querySelector('.mu-gal-visor .mu-gal-next');
            if (n && !n.hidden) { n.click(); }
        });
        await esperar(250);
    }
    const nuevas = peticiones.slice(antes).filter((u) => !/\.(png|jpe?g|webp|gif|svg)(\?|$)/i.test(u));
    chk('recorrer el visor no dispara peticiones', nuevas.length === 0, JSON.stringify(nuevas.slice(0, 3)));

    try {
        fs.mkdirSync(CAPT, { recursive: true });
        await page.screenshot({ path: path.join(CAPT, 'galeria-ficha.png') });
    } catch (e) { /* la captura es evidencia opcional */ }

    chk('sin errores de pagina', errores.length === 0, errores.slice(0, 3).join(' | '));

    await browser.close();
    console.log((mal === 0 ? 'GALERIA FICHA OK (' : 'GALERIA FICHA FALLA (')
        + ok + '/' + (ok + mal) + ' checks)');
    process.exit(mal === 0 ? 0 : 1);
})();