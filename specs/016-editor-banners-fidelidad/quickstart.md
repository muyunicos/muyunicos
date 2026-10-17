# Quickstart 016 — Fidelidad del lienzo y densidad

## A. Verificación automática (local, reproducible)

```powershell
php -l inc\hero-banners.php
node --check js\admin-hero-banners.js

node specs\015-css-verificacion-y-cron-bordes\harness\css-check.js   # 82 OK (68 + 12 de fidelidad + layout)
node specs\010-fechas-banner-por-pais\harness\fechas-js.js           # 38 OK (28 + 10 de tieneContenidoPropio)
node specs\013-panel-hero-lienzo\harness\arnes-render-hero.php       # 118 OK
php specs\012-vigencia-anual\harness\arnes-anual.php                 # 102 OK
php specs\010-fechas-banner-por-pais\harness\arnes-fechas-por-pais.php # 99 OK
php specs\008-banners-pildoras-ia\harness\arnes-banners.php          # 30 OK
php specs\006-ai-translate-core\harness\arnes.php                    # 54 OK
node specs\013-panel-hero-lienzo\harness\lienzo-js.js                # 17 OK
node specs\012-vigencia-anual\harness\anual-js.js                    # 18 OK
node specs\011-variantes-banners-ia\harness\variantes-js.js          # 18 OK
node specs\008-banners-pildoras-ia\harness\banners-js.js             # 17 OK

git diff --name-only   # nada bajo Tema-GeneratePress/
```

Los chequeos de fidelidad del arnés de CSS se validaron **contra el CSS anterior** (`d6db174`):
con esa versión fallan (las 3 variables del hero no estaban en la raíz compartida, no existía el
espejo `@container` y los CTA no tenían estados); con el arreglo pasan.

## B. Verificación manual (mantenedor)

Purgar la caché del host antes de empezar (el panel no está cacheado, la home sí).

1. **Píldora de idioma**: en un banner **sin** traducciones, PPT y EN deben quedar **sin el punto**.
   Escribir un texto en PT que difiera del español → aparece el punto. Borrarlo (o dejarlo igual al
   español) → desaparece.
2. **Vista previa contra la home**: el eyebrow debe verse con su **fondo rosa**, el título con la
   tipografía display, y al pasar el mouse por los CTA deben cambiar (relleno/desplazamiento) igual
   que en `muyunicos.com`. Con la ventana del navegador **angosta** (< 968px) y el lienzo en
   «Escritorio», la preview tiene que seguir mostrando el diseño de escritorio.
3. **Layout**: en pantalla ancha, el bloque de idioma y el lienzo quedan **lado a lado**, y los
   ajustes con la tabla de vigencia **debajo, a todo el ancho**; **sin hueco vacío** al final de la
   fila. En pantalla angosta todo se apila y la tabla se desplaza en horizontal si hace falta.
4. **Caja del bloque de idioma**: ES, PT y EN se ven como el mismo grupo (borde y fondo claro).
5. **Cron**: leer en el panel *«Última purga por borde de fecha… · Próximo borde: …»*. Para ver una
   purga real sin esperar un mes: poner el **Fin** de un banner en **hoy** (DDMMAAAA), guardar, y
   al día siguiente el renglón debe mostrar la purga actualizada sin que nadie guarde nada.

## C. Reversión

`git revert` del commit de implementación (un solo commit para 016). No hay migración de datos: lo
único nuevo en el estado es la lectura de `wp_next_scheduled`, que no escribe nada.
