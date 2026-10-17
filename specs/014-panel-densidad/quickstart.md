# Quickstart 014 — Densidad del panel de banners

## A. Verificación automática (local, reproducible)

```powershell
# Sintaxis
php -l inc\hero-banners.php
node --check js\admin-hero-banners.js

# Arnés del panel (013, donde vive el marcado y la lógica del editor)
php specs\013-panel-hero-lienzo\harness\arnes-render-hero.php   # esperado: 110 OK / 0 FAIL
node specs\013-panel-hero-lienzo\harness\lienzo-js.js           # esperado: 17 OK / 0 FAIL

# No-regresión
php specs\010-fechas-banner-por-pais\harness\arnes-fechas-por-pais.php # 99 OK
node specs\010-fechas-banner-por-pais\harness\fechas-js.js             # 28 OK
php specs\012-vigencia-anual\harness\arnes-anual.php                   # 87 OK
node specs\012-vigencia-anual\harness\anual-js.js                      # 18 OK
node specs\011-variantes-banners-ia\harness\variantes-js.js            # 18 OK
php specs\008-banners-pildoras-ia\harness\arnes-banners.php            # 30 OK
node specs\008-banners-pildoras-ia\harness\banners-js.js               # 17 OK
php specs\006-ai-translate-core\harness\arnes.php                      # 54 OK

# Invariantes de la densidad
Select-String -Path inc\hero-banners.php -Pattern 'is-hereda|is-excepcion|chips-mas'
# esperado: la tabla marca excepción/herencia y el selector +N

Select-String -Path js\admin-hero-banners.js -Pattern 'mostrarExcepcion|nueva-excepcion|ver-todos'
# esperado: los tres handlers de la vigencia por excepción

git diff --name-only   # nada bajo Tema-GeneratePress/
```

Qué cubre el arnés (secciones nuevas de 013):

- **Excepciones**: sin excepciones, una, varias en orden canónico, sólo con el flag anual, país
  inexistente, entrada vacía, `fechas` corrupto y banner no-array.
- **Estado**: los cuatro casos, con `is-sin-paises` diferenciado de `is-inactivo`.
- **Marcado**: la fila trae el resumen, el cuerpo plegable, el menú ⋯, los chips de los tildados con
  el selector `+N`, la fila de Global que **nunca se oculta**, cada fila de país con su
  `data-es-excepcion`, el contador de excepciones y el botón de limpiar.

## B. Verificación manual (mantenedor)

Con 2 o más banners, purgando la caché del host antes de mirar:

1. **Plegado**: al entrar, cada fila se ve como una línea (título · estado · países · Duplicar · ⋯).
   El ▾ abre el cuerpo; con un solo banner la fila nace abierta.
2. **Chips**: sólo aparecen los países tildados. Abrir `+N`: tildar un país mueve su chip al
   resumen y el contador baja; destildar uno del resumen lo devuelve al selector.
3. **Vigencia**: la tabla muestra **Global** + las excepciones, y arriba el contador *«la ventana
   general la usan N de M países»*. Con «ver todos los países» aparecen las 11 filas (las que
   heredan, atenuadas).
4. **Crear excepción**: `+ excepción por país` → elegir un país → su fila aparece con el foco en
   Inicio → cargar fechas → guardar → al recargar, el país sigue como excepción y su chip muestra
   el punto.
5. **Volver a la herencia**: el `×` de esa fila vacía las fechas y la fila desaparece de la tabla
   (el país vuelve a heredar). Guardar y comprobar que el front lo trata como heredado.
6. **Sin países**: destildar todos los chips → el estado del resumen queda **en rojo** con
   *«No está tildado en ningún país: no se verá en el sitio»* (y en el front, efectivamente no se ve).
7. **Menú ⋯**: Subir/Bajar mueven la fila (guardar y mirar el orden de los slides); Eliminar pide
   confirmación por el mismo botón («clic para deshacer»).
8. **No-regresión**: un banner de un solo país sin excepciones se comporta como antes; guardar sin
   cambios no purga la caché (Hash Gate).

## C. Reversión

`git revert` del commit: el panel vuelve a la tabla completa y a los 10 chips; el saneado y los
datos **no cambian** (la tabla completa seguía existiendo detrás de «ver todos»), así que no hay
migración ni limpieza pendiente.
