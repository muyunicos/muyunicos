# Quickstart 018 — Densidad del panel y estado de la IA

## A. Verificación automática (local, reproducible)

```powershell
node --check js\admin-hero-banners.js
php -l inc\hero-banners.php

node specs\015-css-verificacion-y-cron-bordes\harness\css-check.js   # 86 OK (85 + 1)
node specs\011-variantes-banners-ia\harness\variantes-js.js           # 28 OK (18 + 10)
php specs\013-panel-hero-lienzo\harness\arnes-render-hero.php         # 130 OK
php specs\012-vigencia-anual\harness\arnes-anual.php                  # 102 OK
php specs\010-fechas-banner-por-pais\harness\arnes-fechas-por-pais.php # 99 OK
php specs\008-banners-pildoras-ia\harness\arnes-banners.php           # 30 OK
php specs\006-ai-translate-core\harness\arnes.php                     # 54 OK
node specs\007-tarjetas-reloj-resenas\harness\editor-resenas.js       # 41 OK
node specs\013-panel-hero-lienzo\harness\lienzo-js.js                 # 17 OK
node specs\012-vigencia-anual\harness\anual-js.js                     # 18 OK
node specs\010-fechas-banner-por-pais\harness\fechas-js.js            # 38 OK
node specs\008-banners-pildoras-ia\harness\banners-js.js              # 17 OK

git diff --name-only   # nada bajo Tema-GeneratePress/
```

Dos invariantes que quedan vigiladas: (1) el lienzo es **flexible** — el arnés falla si alguien
vuelve a la tercera columna vacía; (2) **mirar un idioma no borra las opciones de los otros**
(`tandaDeIdioma()` es pura y tiene 10 aserciones, incluido el caso del bug).

## B. Verificación manual (mantenedor)

1. **La preview más grande**: en pantalla ancha el lienzo ocupa el sobrante (a 1440px ~0,64 de
   escala en lugar de 0,31) y no queda espacio muerto a su derecha. Probá también «Escritorio» /
   «Móvil».
2. **El estado de la IA**: traducí a PT y comprobá que **ya no queda un mensaje verde** en la fila;
   el resultado se lee al pasar el mouse por «Traducir». Pedí una traducción con un campo vacío o
   un idioma «ya está en PT» ⇒ ahí **sí** aparece el aviso, en rojo y corto.
3. **Las opciones sobreviven** (lo reportado): generá variantes en PT (botones 1·2·3), aplicá la 2,
   pasá a **ES** a mirar el original y volvé a **PT** ⇒ los botones siguen ahí y el **2** es el
   marcado. Después andá a **EN**: tenía las suyas (o ninguna), no las de PT.
4. Nada de esto cambia el guardado: los textos de los tres idiomas siguen viajando siempre.

## C. Reversión

`git revert` del commit. No hay migración ni estado nuevo en la base: los cambios son de CSS/JS del
panel (las tandas viven en memoria de la página, no se guardan).
