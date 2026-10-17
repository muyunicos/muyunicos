# Arneses del editor y de la sección de reseñas

## Qué es un arnés

Es un script de **Node** que carga el archivo JS **real** del tema (`js/testimonials.js`,
`js/reviews-admin-modal.js`) y lo maneja contra un **DOM simulado hecho a mano** (objetos que
imitan `document`, `window`, `fetch`, los temporizadores y los elementos con `value`,
`classList`, `addEventListener`). Después comprueba el resultado con aserciones y devuelve
una lista de OK/FAIL.

Sirve para probar, **sin navegador**, todo lo que no necesita pintar píxeles: qué HTML se
genera, qué clase se agrega, qué mensaje se muestra, qué temporizador se cancela.
No usa jsdom ni Puppeteer ni ninguna dependencia: se corre con `node` y listo.

Ejemplo mínimo (el patrón exacto que usan estos archivos):

```js
const fs = require('fs');
const path = require('path');
const src = fs.readFileSync(path.join(__dirname, '..', '..', '..', 'js', 'testimonials.js'), 'utf8');

let ok = 0, fail = 0;
function check(label, cond) { cond ? (ok++, console.log('OK   ' + label)) : (fail++, console.log('FAIL ' + label)); }

// ... acá se arma el DOM falso y se disparan eventos ...
global.document = { getElementById: () => null, addEventListener: () => {} };
eval(src);

check('carga sin excepciones', true);
console.log(ok + ' OK / ' + fail + ' FAIL');
process.exit(fail ? 1 : 0);
```

## Cómo correrlos (desde la raíz del repositorio, de a uno)

| Comando | Qué prueba | Esperado |
|---|---|---|
| `node specs\007-tarjetas-reloj-resenas\harness\editor-resenas.js` | Editor de reseñas: abrir/editar, traducción, variantes, ojo comparador, errores | 36 OK |
| `node specs\007-tarjetas-reloj-resenas\harness\editor-marcado-viejo.js` | El editor con **HTML cacheado viejo** (sin los elementos nuevos) | 3 OK |
| `node specs\007-tarjetas-reloj-resenas\harness\seccion-resenas.js` | Sección: tarjetas, reloj del botón, pausas | 37 OK |

Los equivalentes de PHP (mismo criterio: módulo real + WordPress simulado) viven en
`specs\006-ai-translate-core\harness\`, `specs\008-banners-pildoras-ia\harness\` y
`specs\009-resenas-sin-duplicados\harness\`.

## Dos cuidados

- **Correrlos de a uno**: usan temporizadores reales; dos a la vez se pisan (un `36 OK / 1 FAIL`
es falso). Pendiente: hacerlos deterministas con temporizadores falsos.
- Estos archivos **no se despliegan**: viven bajo `specs/` y no los carga el tema.