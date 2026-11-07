/* Verificacion en navegador REAL del visor (spec 019, ambito 2 `MU.visor`).
   Uso: node tests/visor.browser.js   (Playwright vive en %TEMP%\pmu-pw)

   Dos clases de salida:
   - chk(): puerta. Si falla, el proceso sale en 1 (regresion real).
   - aviso(): HALLAZGO del veredicto (hueco conocido o decision pendiente). NO
     rompe la puerta: se lista al final para llenar `visor-evaluacion.md`.
   Los datos del demo son SVG data-URI: cero red, cero dependencias externas. */
const path = require('path');
const { chromium } = require(path.join(process.env.TEMP, 'pmu-pw', 'node_modules', 'playwright'));

const DEMO = 'file:///C:/Users/Jonatan/Documents/GitHub/muyunicos/tests/galeria-demo.html';
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';

let ok = 0;
let mal = 0;
const avisos = [];

function chk(nombre, cond, detalle) {
    if (cond) { ok++; console.log('  OK    ' + nombre); }
    else { mal++; console.log('  FALLA ' + nombre + (detalle ? ' :: ' + detalle : '')); }
}

function aviso(texto) {
    avisos.push(texto);
    console.log('  AVISO ' + texto);
}

/** Todo lo medible del visor abierto (una sola ida al navegador). */
async function leer(page) {
    return page.evaluate(() => {
        const raiz = document.querySelector('.mu-gal-visor');
        if (!raiz) { return { abierto: false }; }
        const caja = raiz.querySelector('.mu-gal-caja');
        const scroll = raiz.querySelector('.mu-gal-scroll');
        const marco = raiz.querySelector('.mu-gal-marco');
        const cont = raiz.querySelector('.mu-gal-contador');
        const img = raiz.querySelector('.mu-gal-img');
        const meta = raiz.querySelector('.mu-gal-meta');
        const ultimo = (meta && meta.lastElementChild)
            ? meta.lastElementChild.getBoundingClientRect() : null;
        const cajaR = caja.getBoundingClientRect();
        const lista = raiz.querySelector('.mu-gal-visor-lista');
        const celdas = lista ? Array.from(lista.querySelectorAll('[data-mu-gal-mini]')) : [];
        const activa = lista ? lista.querySelector('.mu-gal-celda--activa') : null;
        const cerrar = raiz.querySelector('.mu-gal-cerrar');
        const cerrarR = cerrar ? cerrar.getBoundingClientRect() : null;
        return {
            abierto: true,
            clases: raiz.className,
            rol: raiz.getAttribute('role'),
            ariaModal: raiz.getAttribute('aria-modal'),
            ariaHidden: raiz.getAttribute('aria-hidden'),
            titulo: (raiz.querySelector('.mu-gal-titulo') || {}).textContent || '',
            contador: cont ? cont.textContent : '',
            contadorHidden: cont ? !!cont.hidden : true,
            marcoDamero: marco ? marco.className.indexOf('mu-gal-marco--damero') !== -1 : false,
            cajaBorder: caja ? getComputedStyle(caja).borderTopWidth : '',
            scrollOverflow: scroll ? getComputedStyle(scroll).overflowY : '',
            prevHidden: !!(raiz.querySelector('.mu-gal-prev') || {}).hidden,
            nextHidden: !!(raiz.querySelector('.mu-gal-next') || {}).hidden,
            metaVisible: meta ? !meta.hidden : false,
            badge: (raiz.querySelector('.mu-gal-badge') || {}).textContent || '',
            precioYa: (raiz.querySelector('.mu-gal-precio-ya') || {}).textContent || '',
            precioAntes: (raiz.querySelector('.mu-gal-precio-antes') || {}).textContent || '',
            imgNatural: img ? img.naturalWidth : -1,
            imgAlto: img ? Math.round(img.getBoundingClientRect().height) : 0,
            imgAncho: img ? Math.round(img.getBoundingClientRect().width) : 0,
            cuerpoJusto: /mu-gal-cuerpo--justo/.test((raiz.querySelector('.mu-gal-cuerpo') || {}).className || ''),
            flechasAlCostado: (function () {
                const p = raiz.querySelector('.mu-gal-prev');
                const m = raiz.querySelector('.mu-gal-marco');
                if (!p || !m || p.hidden) { return false; }
                return p.getBoundingClientRect().right <= m.getBoundingClientRect().left + 1;
            }()),
            flechasSuperpuestas: (function () {
                const p = raiz.querySelector('.mu-gal-prev');
                const m = raiz.querySelector('.mu-gal-marco');
                if (!p || !m || p.hidden) { return false; }
                const pr = p.getBoundingClientRect();
                const mr = m.getBoundingClientRect();
                return pr.left >= mr.left - 1 && pr.right <= mr.right + 1;
            }()),
            cajaClientH: caja.clientHeight,
            cajaAncho: Math.round(cajaR.width),
            cajaClases: caja.className,
            cajaScrollH: caja.scrollHeight,
            scrollClientH: scroll ? scroll.clientHeight : 0,
            scrollH: scroll ? scroll.scrollHeight : 0,
            metaAbajo: ultimo ? Math.round(ultimo.bottom) : null,
            cajaAbajo: Math.round(cajaR.bottom),
            recorta: (ultimo && scroll) ? (ultimo.bottom > scroll.getBoundingClientRect().bottom + 1) : false,
            celdas: celdas.length,
            activa: activa ? celdas.indexOf(activa) : -1,
            listaScrollX: lista ? (lista.scrollWidth - lista.clientWidth) : 0,
            segs: raiz.querySelectorAll('.mu-gal-seg').length,
            segActivo: (raiz.querySelector('.mu-gal-seg[aria-pressed="true"]') || {}).textContent || '',
            acciones: raiz.querySelectorAll('.mu-gal-accion').length,
            hayAvisoError: raiz.querySelectorAll('[data-mu-gal-error], .mu-gal-error').length,
            errorVisible: (function () {
                const e = raiz.querySelector('.mu-gal-error');
                if (!e) { return false; }
                return !e.hidden && getComputedStyle(e).display !== 'none';
            }()),
            errorTexto: (raiz.querySelector('.mu-gal-error-texto') || {}).textContent || '',
            hayReintento: raiz.querySelectorAll('.mu-gal-error-reintentar').length,
            imgSrc: img ? (img.getAttribute('src') || '') : '',
            imgRoto: img ? /mu-gal-img--roto/.test(img.className) : false,
            cerrarVisible: cerrarR
                ? (cerrarR.width > 0 && cerrarR.height > 0 && cerrarR.bottom <= window.innerHeight + 1)
                : false,
            dentro: raiz.contains(document.activeElement),
            activoId: (document.activeElement || {}).id || '',
            activoClase: (document.activeElement || {}).className || '',
            bloqueado: document.body.className.indexOf('is-scroll-locked') !== -1
        };
    });
}

/** Cada hash es una vista COMPLETA del demo: recargar el documento. */
async function ir(page, hash) {
    await page.goto('about:blank');
    await page.goto(DEMO + (hash || ''));
    await page.waitForTimeout(550);
}

(async () => {
    const browser = await chromium.launch({ executablePath: CHROME });
    const page = await browser.newPage({ viewport: { width: 1440, height: 960 } });
    const errores = [];
    page.on('pageerror', e => errores.push(String(e)));

    /* ---------------- 1. apertura y estructura ---------------- */
    console.log('== #visor: apertura, foco, contador, flechas y banda ==');
    await ir(page, '#visor');
    let r = await leer(page);
    chk('abre el dialogo (role=dialog, aria-modal, visible)', r.abierto === true
        && r.rol === 'dialog' && r.ariaModal === 'true' && r.ariaHidden === 'false',
        'rol=' + r.rol + ' modal=' + r.ariaModal + ' hidden=' + r.ariaHidden);
    chk('el foco entra en el modal (boton cerrar)', r.dentro === true && /cerrar/.test(r.activoClase),
        'activo=' + r.activoId + ' clase=' + r.activoClase);
    chk('bloquea el scroll del documento', r.bloqueado === true);
    chk('contador "1 / 9" y titulo "Galeria"', r.contador === '1 / 9' && r.titulo === 'Galeria',
        'contador="' + r.contador + '" titulo="' + r.titulo + '"');
    chk('flechas visibles con mas de un item', !r.prevHidden && !r.nextHidden);
    chk('el marco NO pinta damero con fotos opacas', r.marcoDamero === false);
    chk('en escritorio las flechas van AL COSTADO del marco',
        r.flechasAlCostado === true && r.cuerpoJusto === false,
        'costado=' + r.flechasAlCostado + ' justo=' + r.cuerpoJusto);
    chk('`ancho: auto` (default) no pasa de 880px', /mu-gal-caja--auto/.test(r.cajaClases)
        && r.cajaAncho <= 880, 'clases=' + r.cajaClases + ' ancho=' + r.cajaAncho);
    chk('la caja usa un borde fino (1px), sin el dorado de 3px', r.cajaBorder === '1px',
        'border=' + r.cajaBorder);
    chk('banda inferior con 9 miniaturas y la 1ra activa',
        r.celdas === 9 && r.activa === 0, 'celdas=' + r.celdas + ' activa=' + r.activa);

    /* ---------------- 2. tono ---------------- */
    console.log('== #visor-oscuro: tono del tema ==');
    await ir(page, '#visor-oscuro');
    r = await leer(page);
    chk('aplica mu-gal-visor--oscuro (y no --claro)',
        /mu-gal-visor--oscuro/.test(r.clases) && !/mu-gal-visor--claro/.test(r.clases),
        r.clases);

    /* ---------------- 3. un solo item ---------------- */
    console.log('== #visor-1: un solo item ==');
    await ir(page, '#visor-1');
    r = await leer(page);
    chk('oculta ambas flechas con un item', r.prevHidden && r.nextHidden);
    chk('sin contador (auto con 1 item) y sin banda', r.contador === '' && r.celdas === 0,
        'contador="' + r.contador + '" celdas=' + r.celdas);
    chk('con 1 item el contador se oculta (contador auto)', r.contador === '' && r.contadorHidden === true,
        'contador="' + r.contador + '" hidden=' + r.contadorHidden);

    /* ---------------- 3bis. damero, elegida y estado ---------------- */
    console.log('== #visor-transparente: el damero solo si el item lo declara ==');
    await ir(page, '#visor-transparente');
    r = await leer(page);
    chk('con `transparente: true` el marco pinta damero', r.marcoDamero === true);

    console.log('== #visor-elegida: la galeria marca la elegida (ambito 1) ==');
    await ir(page, '#estado');
    const est = await page.evaluate(() => {
        const celdas = Array.from(document.querySelectorAll('#pg-galeria .mu-gal-celda'));
        const conEstado = celdas
            .map((c, i) => {
                const e = c.querySelector('.mu-gal-item-estado');
                return e ? i + ':' + e.textContent : '';
            })
            .filter(Boolean);
        return { celdas: celdas.length, conEstado: conEstado };
    });
    chk('el preset `opcion` pinta "Elegida" solo en la celda elegida',
        est.conEstado.length === 1 && est.conEstado[0] === '1:Elegida',
        'celdas=' + est.celdas + ' conEstado=' + JSON.stringify(est.conEstado));

    console.log('== #visor-completo: ancho de caja a pantalla ==');
    await ir(page, '#visor-completo');
    r = await leer(page);
    chk('`ancho: completo` ensancha la caja mas alla de 880px',
        /mu-gal-caja--completo/.test(r.cajaClases) && r.cajaAncho > 880,
        'clases=' + r.cajaClases + ' ancho=' + r.cajaAncho);

    console.log('== #filtro-etiqueta: los filtros son de la galeria (C10) ==');
    await ir(page, '#filtro-etiqueta');
    const filtro = await page.evaluate(() => {
        const nodo = document.getElementById('pg-galeria');
        const pills = Array.from(nodo.querySelectorAll('.mu-gal-filtro'));
        return {
            campo: (nodo.querySelector('[data-mu-gal-filtro]') || {}).getAttribute
                ? nodo.querySelector('[data-mu-gal-filtro]').getAttribute('data-mu-gal-filtro') : '',
            etiquetas: pills.map(p => p.textContent),
            activa: (pills.find(p => p.getAttribute('aria-pressed') === 'true') || {}).textContent || '',
            items: Number(nodo.getAttribute('data-mu-gal-items')),
            celdas: nodo.querySelectorAll('.mu-gal-celda[data-mu-gal-mini]').length
        };
    });
    chk('pinta la fila de filtros del campo `etiqueta`',
        filtro.campo === 'etiqueta' && filtro.etiquetas[0] === 'Todos',
        JSON.stringify(filtro));
    chk('los valores salen de los datos (Oferta/Nuevo/Pack) y Todos esta activo',
        filtro.etiquetas.join('|') === 'Todos|Oferta|Nuevo|Pack' && filtro.activa === 'Todos',
        filtro.etiquetas.join('|') + ' activa=' + filtro.activa);

    // Click en "Oferta": recorta a un tercio y el chip/pie hablan de lo filtrado.
    await page.click('#pg-galeria .mu-gal-filtro[data-mu-gal-valor="Oferta"]');
    await page.waitForTimeout(250);
    const filtrado = await page.evaluate(() => {
        const nodo = document.getElementById('pg-galeria');
        return {
            items: Number(nodo.getAttribute('data-mu-gal-items')),
            celdas: nodo.querySelectorAll('.mu-gal-celda[data-mu-gal-mini]').length,
            activa: (nodo.querySelector('.mu-gal-filtro[aria-pressed="true"]') || {}).textContent || '',
            estados: Array.from(nodo.querySelectorAll('.mu-gal-item-estado')).map(e => e.textContent)
        };
    });
    chk('filtrar deja solo los items de la etiqueta elegida',
        filtrado.items === 3 && filtrado.celdas === 3,
        'items=' + filtrado.items + ' celdas=' + filtrado.celdas);
    chk('la pill elegida queda marcada', filtrado.activa === 'Oferta', 'activa=' + filtrado.activa);

    // Volver a "Todos" restaura la lista completa.
    await page.click('#pg-galeria .mu-gal-filtro[data-mu-gal-valor="todos"]');
    await page.waitForTimeout(250);
    const vuelto = await page.evaluate(() =>
        Number(document.getElementById('pg-galeria').getAttribute('data-mu-gal-items')));
    chk('"Todos" restaura los 9 items', vuelto === 9, 'items=' + vuelto);

    console.log('== #filtro-estado: filtro por estado (slot visible) ==');
    await ir(page, '#filtro-estado');
    const estFiltro = await page.evaluate(() => {
        const nodo = document.getElementById('pg-galeria');
        return {
            campo: (nodo.querySelector('[data-mu-gal-filtro]') || {}).getAttribute
                ? nodo.querySelector('[data-mu-gal-filtro]').getAttribute('data-mu-gal-filtro') : '',
            etiquetas: Array.from(nodo.querySelectorAll('.mu-gal-filtro')).map(p => p.textContent),
            estados: Array.from(nodo.querySelectorAll('.mu-gal-item-estado')).map(e => e.textContent)
        };
    });
    chk('el filtro por `estado` aparece con el slot estado visible',
        estFiltro.campo === 'estado' && estFiltro.etiquetas[0] === 'Todos',
        JSON.stringify(estFiltro.etiquetas));
    chk('el slot `estado` pinta el texto del item (no solo "Elegida")',
        estFiltro.estados.length > 0 && estFiltro.estados.indexOf('En stock') !== -1,
        JSON.stringify(estFiltro.estados));

    /* ---------------- 4. meta rica ---------------- */
    console.log('== #visor-meta-rica: badge + precio anterior/valor ==');
    await ir(page, '#visor-meta-rica');
    r = await leer(page);
    chk('muestra badge y precios', r.badge === '31% OFF'
        && r.precioYa === '$153' && r.precioAntes === '$180',
        'badge="' + r.badge + '" ya="' + r.precioYa + '" antes="' + r.precioAntes + '"');

/* ---------------- 5. meta larga: el recorte del pie ---------------- */
    console.log('== #visor-meta-larga: el caso del modal del cotizador ==');
    await ir(page, '#visor-meta-larga');
    r = await leer(page);
    chk('la meta larga entra en el DOM', r.metaVisible === true);
    chk('el contenedor del cuerpo/meta es scrolleable (overflow-y:auto)', r.scrollOverflow === 'auto',
        'overflow=' + r.scrollOverflow);
    const desborde = r.cajaScrollH - r.cajaClientH;
    console.log('  datos  caja clientH=' + r.cajaClientH + ' scrollH=' + r.cajaScrollH
        + ' desborde=' + desborde + ' ultimoNodo.bottom=' + r.metaAbajo
        + ' caja.bottom=' + r.cajaAbajo);
    if (r.recorta) {
        aviso('meta larga RECORTADA: el pie no scrollea (desborde ' + desborde
            + 'px, ultimo nodo ' + (r.metaAbajo - r.cajaAbajo) + 'px fuera de la caja)');
    } else {
        console.log('  ok     la meta larga entra completa (sin recorte)');
    }
    await page.screenshot({ path: path.join(process.env.TEMP, 'qa-visor-meta-larga.png') });

    /* ---------------- 6. meta por item ---------------- */
    console.log('== #visor-meta-item: la meta del item manda ==');
    await ir(page, '#visor-meta-item');
    r = await leer(page);
    chk('la 1ra no trae meta (sin badge)', r.badge === '' && r.metaVisible === false,
        'badge="' + r.badge + '" metaVisible=' + r.metaVisible);
    await page.evaluate(() => window.MU.ir(1));
    await page.waitForTimeout(250);
    r = await leer(page);
    chk('la 2da trae su propia meta', r.badge === 'Solo la 2da' && r.contador === '2 / 9',
        'badge="' + r.badge + '" contador="' + r.contador + '"');

    /* ---------------- 7. 40 items: la banda aguanta ---------------- */
    console.log('== #visor-40: banda inferior con 40 items ==');
    await ir(page, '#visor-40');
    r = await leer(page);
    chk('contador "1 / 40" y la banda pinta celdas', r.contador === '1 / 40' && r.celdas > 10,
        'contador="' + r.contador + '" celdas=' + r.celdas);
    const celda5 = await page.$('.mu-gal-visor-lista [data-mu-gal-mini="4"]');
    chk('la celda 5 existe en la banda', !!celda5);
    if (celda5) {
        await celda5.click();
        await page.waitForTimeout(350);
        r = await leer(page);
        chk('clic en la celda 5 -> contador "5 / 40" y activa=4',
            r.contador === '5 / 40' && r.activa === 4,
            'contador="' + r.contador + '" activa=' + r.activa);
    }

    /* ---------------- 8. imagen rota ---------------- */
    console.log('== #visor-roto: urls que no existen ==');
    await ir(page, '#visor-roto');
    r = await leer(page);
    chk('el visor abre igual con imagenes rotas', r.abierto === true && r.contador === '1 / 4',
        'contador="' + r.contador + '"');
    chk('la imagen no carga (naturalWidth 0)', r.imgNatural === 0, 'naturalWidth=' + r.imgNatural);
    chk('la imagen rota muestra mensaje, reintento y oculta el icono del navegador',
        r.errorVisible === true && r.hayReintento === 1 && r.imgRoto === true
            && r.errorTexto.length > 8,
        'visible=' + r.errorVisible + ' botones=' + r.hayReintento
            + ' roto=' + r.imgRoto + ' texto="' + r.errorTexto + '"');
    const srcRoto = r.imgSrc;
    const reintentar = await page.$('.mu-gal-error-reintentar');
    chk('el boton Reintentar esta en el marco y es clickeable', !!reintentar);
    if (reintentar) {
        await reintentar.click();
        await page.waitForTimeout(450);
        r = await leer(page);
        chk('Reintentar pide la imagen de nuevo (url con parametro de cache)',
            r.imgSrc !== srcRoto && /[?&]r=\d+/.test(r.imgSrc),
            'antes="' + srcRoto + '" despues="' + r.imgSrc + '"');
        chk('si vuelve a fallar, el mensaje sigue visible', r.errorVisible === true);
    }

    /* ---------------- 8b. el error no queda pegado al cambiar de imagen ---------------- */
    console.log('== #visor-roto-mixto: 2 rotas y 2 buenas ==');
    await ir(page, '#visor-roto-mixto');
    r = await leer(page);
    chk('el visor mixto arranca en la rota y muestra el error', r.errorVisible === true,
        'visible=' + r.errorVisible);
    await page.click('.mu-gal-next');
    await page.waitForTimeout(250);
    await page.click('.mu-gal-next');
    await page.waitForTimeout(500);
    r = await leer(page);
    chk('al pasar a una imagen buena el error se limpia solo',
        r.errorVisible === false && r.imgNatural > 0 && r.imgRoto === false,
        'visible=' + r.errorVisible + ' naturalWidth=' + r.imgNatural + ' roto=' + r.imgRoto);

/* ---------------- 9. sin listado y segmentos ---------------- */
    console.log('== #visor-sin-lista y #visor-segmentos ==');
    await ir(page, '#visor-sin-lista');
    r = await leer(page);
    chk('sin listado no hay banda', r.celdas === 0, 'celdas=' + r.celdas);
    await ir(page, '#visor-segmentos');
    r = await leer(page);
    chk('2 segmentos con el 1ro activo', r.segs === 2 && r.segActivo === 'Fotos',
        'segs=' + r.segs + ' activo="' + r.segActivo + '"');

    /* ---------------- 10. teclado, trampa de foco y cierre ---------------- */
    console.log('== abrir desde el boton: flechas, Tab y Escape ==');
    await ir(page, '');
    await page.click('#pg-visor');
    await page.waitForTimeout(400);
    r = await leer(page);
    chk('abre desde el boton de la galeria', r.abierto === true && r.contador === '1 / 9',
        'contador="' + r.contador + '"');
    await page.keyboard.press('ArrowRight');
    await page.waitForTimeout(250);
    r = await leer(page);
    chk('ArrowRight avanza a "2 / 9" y mueve la celda activa',
        r.contador === '2 / 9' && r.activa === 1, 'contador="' + r.contador + '" activa=' + r.activa);
    await page.keyboard.press('ArrowLeft');
    await page.waitForTimeout(250);
    r = await leer(page);
    chk('ArrowLeft vuelve a "1 / 9"', r.contador === '1 / 9', 'contador="' + r.contador + '"');
    let sinEscapar = true;
    let detalleEscape = '';
    const tabbables = await page.evaluate(() => Array.from(document.querySelectorAll(
        '.mu-gal-visor button, .mu-gal-visor [href], .mu-gal-visor [tabindex]'
    )).map(b => (b.className || b.tagName) + (b.hidden ? '(hidden)' : '')
        + (b.getAttribute('tabindex') !== null ? '[tabindex=' + b.getAttribute('tabindex') + ']' : '')));
    console.log('  tabbables ' + tabbables.length + ': ' + tabbables.join(' | '));
    for (let i = 0; i < 10; i++) {
        await page.keyboard.press('Tab');
        const dentro = await page.evaluate(() => {
            const raiz = document.querySelector('.mu-gal-visor');
            const a = document.activeElement || {};
            return {
                dentro: !!raiz && raiz.contains(a),
                who: (a.id || '') + '.' + (a.className || '') + '|' + (a.tagName || '')
            };
        });
        if (!dentro.dentro) {
            sinEscapar = false;
            detalleEscape = 'tab#' + (i + 1) + ' se fue a ' + dentro.who;
            break;
        }
    }
    chk('Tab 10 veces no escapa del modal', sinEscapar, detalleEscape);
    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);
    r = await leer(page);
    chk('Escape cierra y retira el nodo', r.abierto === false);
    const volvio = await page.evaluate(() => ({
        id: (document.activeElement || {}).id,
        bloqueado: document.body.className.indexOf('is-scroll-locked') !== -1
    }));
    chk('el foco vuelve al disparador (#pg-visor)', volvio.id === 'pg-visor', 'activo=' + volvio.id);
    chk('se restaura el scroll del documento', volvio.bloqueado === false);

    /* ---------------- 11. movil (390 px) ---------------- */
    console.log('== 390x844: el modal en un telefono ==');
    await page.setViewportSize({ width: 390, height: 844 });
    await ir(page, '#visor-meta-rica');
    r = await leer(page);
    chk('el modal abre y la imagen tiene alto util', r.abierto === true && r.imgAlto > 120,
        'imgAlto=' + r.imgAlto + ' imgAncho=' + r.imgAncho);
    chk('el boton cerrar queda dentro del viewport', r.cerrarVisible === true);
    chk('la imagen no desborda el ancho del viewport', r.imgAncho <= 390,
        'imgAncho=' + r.imgAncho);
    chk('en 390 las flechas se SUPERponen al marco (decision del componente)',
        r.cuerpoJusto === true && r.flechasSuperpuestas === true,
        'justo=' + r.cuerpoJusto + ' superpuestas=' + r.flechasSuperpuestas);
    chk('la imagen usa casi todo el ancho de la caja', (r.imgAncho / r.cajaAncho) > 0.82,
        'imgAncho=' + r.imgAncho + ' cajaAncho=' + r.cajaAncho);
    // La banda es un carrusel por diseno: que scrollee NO es un defecto, pero si es
    // una propiedad observable del componente en movil, asi que se afirma.
    chk('en 390 px la banda inferior es un carrusel y scrollea en horizontal',
        r.listaScrollX > 1, 'scroll horizontal=' + r.listaScrollX + 'px');
    // La caja es max-height 92vh + overflow:hidden: en movil es donde el pie se aprieta.
    await ir(page, '#visor-meta-larga');
    r = await leer(page);
    const desbordeMovil = r.cajaScrollH - r.cajaClientH;
    console.log('  datos  movil meta larga: clientH=' + r.cajaClientH
        + ' scrollH=' + r.cajaScrollH + ' desborde=' + desbordeMovil);
    chk('en movil el modal sigue usable (imagen con alto util)', r.imgAlto > 100,
        'imgAlto=' + r.imgAlto);
    chk('en movil la meta larga scrollea y nunca recorta',
        !r.recorta && r.scrollOverflow === 'auto',
        'recorta=' + r.recorta + ' overflow=' + r.scrollOverflow
            + ' scrollH=' + r.scrollH + ' clientH=' + r.scrollClientH);

    await page.screenshot({ path: path.join(process.env.TEMP, 'qa-visor-390.png') });
    await page.setViewportSize({ width: 1440, height: 960 });

    /* ---------------- 12. sin errores de JS ---------------- */
    chk('sin errores de JS en la pagina', errores.length === 0, errores.join(' | '));

    await browser.close();
    if (avisos.length) {
        console.log('== HALLAZGOS (no rompen la puerta: van a visor-evaluacion.md) ==');
        avisos.forEach(a => console.log('   - ' + a));
    }
    console.log((mal === 0 ? 'VISOR OK (' : 'VISOR FALLA (') + ok + '/' + (ok + mal)
        + ' checks, ' + avisos.length + ' hallazgos)');
    process.exit(mal === 0 ? 0 : 1);
})().catch(e => { console.log('ERROR ' + e.message); process.exit(2); });