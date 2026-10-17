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
const wait = () => new Promise( r => realSetTimeout( () => { flushTimers(); r(); }, 0 ) );
const src = fs.readFileSync(path.join(__dirname, '..', '..', '..', 'js', 'reviews-admin-modal.js'), 'utf8');
let ok = 0, fail = 0;
function check( l, c, x ) { if (c) { ok++; console.log('OK   ' + l); } else { fail++; console.log('FAIL ' + l + '  ' + (x===undefined?'':x)); } }
const els = {};
function mk( id ) { return els[id] = { id, value:'', checked:false, disabled:false, textContent:'', innerHTML:'', className:'', placeholder:'', style:{}, _l:{}, files:[], addEventListener(t,fn){ (this._l[t]=this._l[t]||[]).push(fn); }, fire(t,ev){ (this._l[t]||[]).forEach(fn => fn.call(this, ev||{preventDefault(){},stopPropagation(){},target:this})); } }; }
// Marcado VIEJO: sin loading, sin boton de traduccion, sin caja de error, sin textareas PT/EN
['mu-review-modal','mu-review-form','mu-review-index','mu-review-name','mu-review-rating','mu-review-text','mu-review-date','mu-review-profile-url','mu-review-profile-photo','mu-review-purchase-photo','mu-review-purchase-photo-url','mu-purchase-photo-preview','mu-review-hidden','mu-delete-review-btn','mu-review-modal-title'].forEach(mk);
els['mu-review-form'].reset = function(){ ['mu-review-index','mu-review-name','mu-review-rating','mu-review-text','mu-review-date'].forEach(k => { if (els[k]) els[k].value=''; }); };
global.document = { getElementById: id => els[id] || null, querySelectorAll: () => [], querySelector: () => null, addEventListener(){} };
global.window = global; global.console.warn = () => {};
let getReview = { success:true, data:{ review:{ author_name:'Viejo marcado', rating:5, text:'Texto cacheado', time:1788393600, author_url:'', profile_url:'', profile_photo_url:'', purchase_photo_url:'', hidden:false } } };
global.fetch = () => Promise.resolve({ json: () => Promise.resolve(getReview) });
global.muTestimonials = { reviews: [], nonce:'n', aiNonce:'a', ajaxUrl:'/ajax', isAdmin:'1', lang:'es', i18n:{} };
try {
    eval(src);
    check('carga sin excepcion con marcado viejo', true);
} catch (e) {
    check('carga sin excepcion con marcado viejo', false, e.message);
}
(async () => {
    try {
        window.muReviewModal.openEdit(3);
        await wait();
        check('editor funciona con marcado viejo (nombre)', 'Viejo marcado' === els['mu-review-name'].value, els['mu-review-name'].value);
        check('editor funciona con marcado viejo (texto)', 'Texto cacheado' === els['mu-review-text'].value, els['mu-review-text'].value);
    } catch (e) {
        check('editor funciona con marcado viejo', false, e.message);
    }
    console.log('\n== ' + ok + ' OK / ' + fail + ' FAIL ==');
    process.exit(fail > 0 ? 1 : 0);
})();
