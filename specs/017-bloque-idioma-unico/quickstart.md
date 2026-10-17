# Quickstart 017 — Un solo bloque de idioma

## A. Verificación automática (local, reproducible)

```powershell
php -l inc\hero-banners.php; php -l inc\ui.php
node --check js\admin-hero-banners.js; node --check js\reviews-admin-modal.js

# El cambio grande (bloque único + toolbar en la línea del primer grupo)
php specs\013-panel-hero-lienzo\harness\arnes-render-hero.php   # 130 OK (118 + 12)
node specs\015-css-verificacion-y-cron-bordes\harness\css-check.js  # 85 OK (82 + 3)

# El modal de reseñas
node specs\007-tarjetas-reloj-resenas\harness\editor-resenas.js # 41 OK (+5)

# No-regresión
php specs\012-vigencia-anual\harness\arnes-anual.php            # 102 OK
node specs\012-vigencia-anual\harness\anual-js.js               # 18 OK
php specs\010-fechas-banner-por-pais\harness\arnes-fechas-por-pais.php # 99 OK
node specs\010-fechas-banner-por-pais\harness\fechas-js.js      # 38 OK
node specs\013-panel-hero-lienzo\harness\lienzo-js.js           # 17 OK
node specs\011-variantes-banners-ia\harness\variantes-js.js     # 18 OK
php specs\008-banners-pildoras-ia\harness\arnes-banners.php     # 30 OK
node specs\008-banners-pildoras-ia\harness\banners-js.js        # 17 OK
php specs\006-ai-translate-core\harness\arnes.php               # 54 OK

git diff --name-only   # nada bajo Tema-GeneratePress/
```

Las aserciones que más importan de esta feature:
1. **ningún campo `disabled`** (un `disabled` no viaja en el POST: se perderían traducciones);
2. los **`name`** de las tres lenguas siguen ahí (`[titulo]`, `[titulo_pt]`, `[titulo_en]`);
3. las variantes se ocultan con **`hidden`** y la regla de visible es `:not([hidden])`;
4. el **toolbar está dentro** de la línea del primer grupo y **el bloque no se oculta** nunca;
5. en el modal de reseñas, el **`required`** del español acompaña la visibilidad.

## B. Verificación manual (mantenedor)

1. **Panel de banners**: el rótulo «Texto» con la botonera a su derecha (ES | PT | EN · variantes ·
   Traducir). Alterná ES → PT → EN: la botonera **no se mueve ni desaparece** y los campos cambian
   de idioma en el mismo lugar.
2. **La prueba del `disabled`** (la más importante): escribí algo distinto en ES, en PT y en EN,
   guardá **estando en PT**, recargá y verificá que **las tres** versiones siguen ahí. Repetí
   guardando estando en EN.
3. Los avisos por idioma aparecen sólo en su idioma: el de «traducción desactualizada» y el
   recordatorio «lo que dejes vacío…».
4. **Modal de reseñas** (desde el front, con tu sesión de administrador): agregá una reseña, cambiá
   de idioma, guardá estando en PT y reabrí: el texto de cada idioma tiene que estar.
5. La **vista previa** sigue el idioma activo y el lienzo muestra lo del idioma elegido (el respaldo
   al español cuando el campo está vacío).

## C. Reversión

`git revert` del commit de implementación. No hay migración de datos: los `name` y las columnas no
se tocaron, así que el contenido guardado sigue siendo el mismo.
