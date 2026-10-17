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
const src = fs.readFileSync(path.join(__dirname, '..', '..', '..', 'js', 'reviews-admin-modal.js'), 'utf8');
let ok = 0, fail = 0;
function check( label, cond, extra ) {
    if ( cond ) { ok++; console.log( 'OK   ' + label ); }
    else { fail++; console.log( 'FAIL ' + label + '  ' + ( extra === undefined ? '' : extra ) ); }
}
const els = {};
function mk( id ) { return els[id] = { id, value:'', checked:false, disabled:false, hidden:false, textContent:'', innerHTML:'', className:'', placeholder:'', style:{}, _l:{}, files:[], children:[], classList:{ _s:{}, add(c){this._s[c]=1;}, remove(c){delete this._s[c];}, toggle(c,on){ if(on===undefined){ on=!this._s[c]; } if(on){this._s[c]=1;}else{delete this._s[c];} return on; }, contains(c){ return !!this._s[c]; } }, setAttribute(a,v){ this._attrs=this._attrs||{}; this._attrs[a]=v; }, getAttribute(a){ return (this._attrs||{})[a] === undefined ? null : this._attrs[a]; }, appendChild(el){ this.children.push(el); return el; }, querySelectorAll(){ return []; }, addEventListener(t,fn){ (this._l[t]=this._l[t]||[]).push(fn); }, fire(t,ev){ (this._l[t]||[]).forEach(fn => fn.call(this, ev || { preventDefault(){}, stopPropagation(){}, target:this })); } }; }
['mu-review-modal','mu-review-form','mu-review-index','mu-review-name','mu-review-rating','mu-review-text','mu-review-text-pt','mu-review-text-en','mu-review-date','mu-review-profile-url','mu-review-profile-photo','mu-review-purchase-photo','mu-review-purchase-photo-url','mu-purchase-photo-preview','mu-review-hidden','mu-delete-review-btn','mu-review-modal-title','mu-review-modal-error','mu-review-modal-error-text','mu-review-modal-loading','mu-review-translate','mu-review-translate-status','mu-review-variants','mu-review-original','mu-review-original-toggle'].forEach(mk);
// El marcado real nace con el ojo y el comparador ocultos (atributo hidden).
els['mu-review-original-toggle'].hidden = true;
els['mu-review-original'].hidden = true;
els['mu-review-form'].reset = function(){ ['mu-review-index','mu-review-name','mu-review-rating','mu-review-text','mu-review-text-pt','mu-review-text-en','mu-review-date','mu-review-profile-url','mu-review-profile-photo','mu-review-purchase-photo-url'].forEach(k => { if (els[k]) els[k].value=''; }); if (els['mu-review-hidden']) els['mu-review-hidden'].checked=false; };
const pills = ['es','pt','en'].map(lang => ({ lang, has:'', disabled:false, getAttribute(a){ return a==='data-lang'?this.lang:null; }, setAttribute(a,v){ if(a==='data-has-translation') this.has=v; }, classList:{ toggle(c,on){ this.state=on; } }, addEventListener(t,fn){ (this._l=this._l||{})[t]=fn; }, fire(t){ if(this._l&&this._l[t]) this._l[t].call(this); } }));
const blocks = ['es','pt','en'].map(lang => ({ lang, getAttribute(a){ return a==='data-lang'?this.lang:null; }, style:{} }));
global.document = { getElementById: id => els[id] || null, querySelectorAll: sel => sel.indexOf('lang-pill')!==-1 ? pills : (sel.indexOf('lang-block')!==-1 ? blocks : []), querySelector: sel => { const m = sel.match(/data-lang="(\w+)"/); return m ? pills.find(p => p.lang===m[1]) : null; }, createElement: () => ({ _l:{}, _attrs:{}, textContent:'', className:'', type:'', addEventListener(t,fn){ (this._l[t]=this._l[t]||[]).push(fn); }, fire(t){ (this._l[t]||[]).forEach(fn => fn.call(this, { preventDefault(){}, stopPropagation(){}, target:this })); }, classList:{ _s:{}, add(c){this._s[c]=1;}, remove(c){delete this._s[c];}, toggle(c,on){ if(on===undefined){ on=!this._s[c]; } if(on){this._s[c]=1;}else{delete this._s[c];} }, contains(c){ return !!this._s[c]; } }, setAttribute(a,v){ this._attrs[a]=v; }, getAttribute(a){ return this._attrs[a] || null; } }), addEventListener(){} };
global.window = global;
global.console.warn = () => {};
const calls = [];
let getReview = { success:true, data:{} };
let translateRes = { success:true, data:{ items:{} } };
global.fetch = function( url, opts ) {
    const fd = opts && opts.body;
    const action = (fd && fd.get) ? fd.get('action') : '';
    calls.push(action);
    const res = (action === 'mu_get_review') ? getReview : (action === 'mu_ai_translate' ? translateRes : { success:false, data:{} });
    return Promise.resolve({ json: () => Promise.resolve(res) });
};
global.muTestimonials = { reviews: [], nonce:'n', aiNonce:'a', ajaxUrl:'/ajax', isAdmin:'1', lang:'es', i18n:{} };
eval(src);
const wait = () => new Promise( r => realSetTimeout( () => { flushTimers(); r(); }, 0 ) );
(async () => {
    getReview = { success:true, data:{ index:3, review:{ author_name:'Cliente del servidor', rating:4, text:'Texto del servidor', time:1788393600, author_url:'g.com/p', profile_url:'', profile_photo_url:'f.jpg', purchase_photo_url:'', hidden:false, text_pt:'Texto do servidor', text_en:'' }, stale:['pt'] } };
    window.muReviewModal.openEdit(3);
    await wait();
    check('A pide la reseña al servidor', calls.indexOf('mu_get_review') !== -1, JSON.stringify(calls));
    check('A nombre desde el servidor', 'Cliente del servidor' === els['mu-review-name'].value, els['mu-review-name'].value);
    check('A comentario desde el servidor', 'Texto del servidor' === els['mu-review-text'].value, els['mu-review-text'].value);
    check('A traduccion PT cargada', 'Texto do servidor' === els['mu-review-text-pt'].value, els['mu-review-text-pt'].value);
    check('A fecha cargada', '' !== els['mu-review-date'].value, els['mu-review-date'].value);
    check('A aviso de desactualizada', els['mu-review-translate-status'].textContent.indexOf('desactualizada') !== -1, els['mu-review-translate-status'].textContent);
    check('A sin aviso de carga', 'none' === els['mu-review-modal-loading'].style.display, els['mu-review-modal-loading'].style.display);
    check('A formulario visible', 'block' === els['mu-review-form'].style.display);
    check('A boton eliminar visible', 'inline-block' === els['mu-delete-review-btn'].style.display);

    getReview = { success:false, data:{ code:'not_found', error:'La resena ya no esta en el listado.' } };
    window.muReviewModal.openEdit(99);
    await wait();
    check('B error visible', 'block' === els['mu-review-modal-error'].style.display);
    check('B mensaje con el motivo', els['mu-review-modal-error-text'].textContent.indexOf('ya no esta') !== -1, els['mu-review-modal-error-text'].textContent);
    check('B formulario oculto', 'none' === els['mu-review-form'].style.display);

    window.muReviewModal.openEdit(3);
    await wait();
    pills.find(p => p.lang==='pt').fire('click');
    check('C boton habilitado en PT', false === els['mu-review-translate'].disabled);
    els['mu-review-text'].value = 'Texto en espanol para traducir';
    translateRes = { success:true, data:{ items:{ text:{ translation:'Texto em portugues', unchanged:false, cached:false } } } };
    els['mu-review-translate'].fire('click');
    await wait();
    check('C llama a mu_ai_translate', calls.indexOf('mu_ai_translate') !== -1, JSON.stringify(calls));
    check('C campo PT completado', 'Texto em portugues' === els['mu-review-text-pt'].value, els['mu-review-text-pt'].value);
    check('C estado en verde', els['mu-review-translate-status'].className.indexOf('is-ok') !== -1, els['mu-review-translate-status'].className);
    check('C punto en la pill PT', '1' === pills.find(p => p.lang==='pt').has);

    pills.find(p => p.lang==='es').fire('click');
    check('D boton deshabilitado en ES', true === els['mu-review-translate'].disabled);

    pills.find(p => p.lang==='en').fire('click');
    translateRes = { success:true, data:{ items:{ text:{ translation:'', unchanged:true } } } };
    els['mu-review-text-en'].value = '';
    els['mu-review-translate'].fire('click');
    await wait();
    check('E avisa que ya esta en el idioma', els['mu-review-translate-status'].textContent.indexOf('ese idioma') !== -1, els['mu-review-translate-status'].textContent);
    check('E no escribe nada', '' === els['mu-review-text-en'].value);

    translateRes = { success:false, data:{ code:'quota', error:'El servicio alcanzo su limite de uso.' } };
    els['mu-review-translate'].fire('click');
    await wait();
    check('F muestra el error del servicio', els['mu-review-translate-status'].textContent.indexOf('limite de uso') !== -1, els['mu-review-translate-status'].textContent);

    // G) Comparador del original: el ojo abre y cierra el panel.
    // (Caso que faltaba: el bug estaba justo acá — el primer clic no abría nada.)
    getReview = { success:true, data:{ index:3, review:{ author_name:'Cliente del servidor', rating:4, text:'Original en espanol', time:1788393600, author_url:'', profile_url:'', profile_photo_url:'', purchase_photo_url:'', hidden:false, text_pt:'', text_en:'' }, stale:[] } };
    window.muReviewModal.openEdit(3);
    await wait();

    check('G con ES activo el ojo esta oculto', true === els['mu-review-original-toggle'].hidden);
    check('G el panel arranca oculto', true === els['mu-review-original'].hidden);

    pills.find(p => p.lang==='pt').fire('click');
    check('G en PT el ojo aparece', false === els['mu-review-original-toggle'].hidden);

    els['mu-review-original-toggle'].fire('click');
    check('G primer clic: el panel se ABRE', false === els['mu-review-original'].hidden, 'hidden=' + els['mu-review-original'].hidden);
    check('G muestra el original en espanol', 'Original en espanol' === els['mu-review-original'].textContent, els['mu-review-original'].textContent);
    check('G aria-expanded pasa a true', 'true' === els['mu-review-original-toggle'].getAttribute('aria-expanded'));
    check('G el ojo queda marcado', els['mu-review-original-toggle'].classList.contains('is-active'));

    els['mu-review-original-toggle'].fire('click');
    check('G segundo clic: el panel se CIERRA', true === els['mu-review-original'].hidden);
    check('G aria-expanded vuelve a false', 'false' === els['mu-review-original-toggle'].getAttribute('aria-expanded'));

    els['mu-review-original-toggle'].fire('click');
    pills.find(p => p.lang==='en').fire('click');
    check('G al cambiar de idioma el panel se cierra solo', true === els['mu-review-original'].hidden);

    pills.find(p => p.lang==='pt').fire('click');
    els['mu-review-text'].value = '';
    els['mu-review-original-toggle'].fire('click');
    check('G avisa cuando no hay texto original', els['mu-review-original'].textContent.indexOf('sin texto') !== -1, els['mu-review-original'].textContent);

    // H) Variantes: se pintan los numeros, cambian el campo y NO hay aviso de estado.
    els['mu-review-text'].value = 'Texto original para variantes';
    els['mu-review-translate-status'].textContent = 'mensaje previo que debe desaparecer';
    translateRes = { success:true, data:{ items:{ text:{ translation:'v1', variants:['v1','v2','v3','v4','v5'], unchanged:false, cached:false } }, personas:['Uno','Dos','Tres','Cuatro','Cinco'] } };
    els['mu-review-translate'].fire('click');
    await wait();

    check('H se pintan los numeros de variante', els['mu-review-variants'].children.length >= 5, String(els['mu-review-variants'].children.length));
    check('H sin aviso de estado (menos ruido)', '' === els['mu-review-translate-status'].textContent, els['mu-review-translate-status'].textContent);
    check('H el campo queda con la primera', 'v1' === els['mu-review-text-pt'].value, els['mu-review-text-pt'].value);

    els['mu-review-variants'].children[2].fire('click');
    check('H el numero 3 aplica su version', 'v3' === els['mu-review-text-pt'].value, els['mu-review-text-pt'].value);

    console.log('\n== ' + ok + ' OK / ' + fail + ' FAIL ==');
    process.exit(fail > 0 ? 1 : 0);
})();

