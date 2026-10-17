# Quickstart 010 — Fechas por país en Hero Banners

## A. Verificación automática (local, reproducible)

```powershell
# Sintaxis
php -l inc/hero-banners.php
node --check js/admin-hero-banners.js

# Arnés propio (módulo real con WordPress simulado, sin red)
php specs\010-fechas-banner-por-pais\harness\arnes-fechas-por-pais.php   # esperado: 96 OK / 0 FAIL
node specs\010-fechas-banner-por-pais\harness\fechas-js.js              # esperado: 28 OK / 0 FAIL

# No-regresión de las features que comparten el módulo y el JS
php specs\008-banners-pildoras-ia\harness\arnes-banners.php             # esperado: 30 OK / 0 FAIL
node specs\008-banners-pildoras-ia\harness\banners-js.js                # esperado: 17 OK / 0 FAIL
node specs\011-variantes-banners-ia\harness\variantes-js.js             # esperado: 18 OK / 0 FAIL
php specs\006-ai-translate-core\harness\arnes.php                       # esperado: 54 OK / 0 FAIL

# Invariantes: las píldoras de país se renderizan una sola vez (en PHP) y no se
# construyen más desde el JS
Select-String -Path js\*.js -Pattern "mu-hero-banner-pill"
# esperado: sólo selectores (.mu-hero-banner-pill, [data-pais=...]); sin $('<span class="mu-hero-banner-pill">')

Select-String -Path inc\hero-banners.php -Pattern 'mu-hero-banner-fechas-block'
# esperado: 2 apariciones en el marcado (panel general + panel por país)

# Alcance
git diff --name-only   # nada bajo Tema-GeneratePress/
```

Qué cubre el arnés PHP: herencia campo por campo de la ventana general, país sin ventana,
`fechas` corrupto, bordes del día (inicio inclusive, fin del día inclusive, un segundo después),
rango invertido, banner legacy, saneado (país inexistente, formato distinto de DDMMAAAA, fecha
inexistente como 31/02, vacío cuando es igual a la general, y que las fechas de un país válido no
tildado ya **no** se pierdan), la clave del transient por país, el **marcado real de la fila del
panel** (píldoras por país, panel por país, nombres de campo, ausencia de `pattern`, contenedor de
variantes), el **huso horario del país** (misma fecha, instantes distintos por subdominio; cambio
de horario de verano en Chile), el **TTL adaptativo** del transient, los **avisos de la fila**
(fechas inválidas y rangos invertidos) y una **prueba de integración** de `mu_get_hero_banners()`
con fechas relativas a hoy: la ventana propia puede excluir un banner que la general mostraría y
mostrar uno que la general ya cerró.

El arnés JS cubre las funciones puras: parseo de DDMMAAAA, rango invertido, fecha real (bisiesto
incluido) y las variantes de traducción (feature 011, arnés propio).

## B. Verificación manual (mantenedor)

**Antes de empezar**: purgar la caché de los hosts que se van a mirar (el marcado del panel y del
hero cambian). El transient del país ya caduca en el próximo borde de fecha, pero la **caché de
páginas** de LiteSpeed (HTML) puede seguir sirviendo la home vieja: por eso se purga el host antes
de medir.

1. **Panel** (WC → Marketing → Hero Banners): el **resumen** de la fila trae los **chips** de los
   países tildados y, al desplegarla, **Contenido** agrupado y la **tabla de vigencia** con
   `Global` + las excepciones.
   - `+ excepción por país`: elegir un país → su fila aparece con el foco en **Inicio**.
   - Los campos de ese país deben mostrar la fecha general como **placeholder** (en gris) y el
     valor vacío (comprobar con el inspector).
2. **Punto**: escribir una fecha propia en la fila de un país → el punto aparece en su **chip** del resumen.
   Borrarla, o dejarla igual a la general → el punto desaparece. Mover la fecha **general** debe
   recalcular los puntos y los placeholders de todos los países.
3. **Fecha imposible**: escribir `31/02/2026` o `99012026` → aviso del panel (“Usá el formato
   DDMMAAAA…”); y un inicio posterior al fin → aviso de rango invertido. Guardar y mirar la
   **lista**: la fila avisa *“BR: hay una fecha inválida guardada… / Fechas generales: el inicio es
   posterior al fin…”* sin abrir el panel.
4. **Huso del país**: en la columna **«Se ve»** de cada fila debe verse la ventana efectiva con su huso
   (`dd/mm/aaaa → dd/mm/aaaa (America/Sao_Paulo)`, y en la fila de Global «huso del sitio»). Con el mismo `01/06`, Madrid y Buenos
   Aires deben entrar/salir en momentos distintos (5 h de diferencia en junio).
5. **Países**: destildar un chip del resumen → el país pasa al selector `+N` y su fila queda atenuada, pero **conserva** su ventana: volver a tildarlo la recupera. (La tabla completa se ve con «ver todos los países».)
6. **Guardar** y comprobar el front: con un país dentro de su ventana propia, el banner se ve en ese
   subdominio; con el país fuera de su ventana (fecha pasada), no se ve; en un subdominio sin ventana
   propia sigue la general. Repetir en `/pt/` y `/en/` (el idioma no cambia la ventana).
7. **Variantes de traducción** (feature 011): con personas cargadas en el ámbito, «Traducir» debe
   ofrecer los botones `1·2·3…` al lado del botón; ver `specs/011-variantes-banners-ia/quickstart.md`
   Fase B.
8. **No-regresión**: un banner sin fechas por país se comporta igual que antes. Guardar sin cambios
   no debe purgar la caché (Hash Gate).

## C. Reversión

`git revert` del commit de la feature. En la base, `fechas` queda guardada pero el código anterior
la ignora: el front vuelve a usar las fechas generales y el panel viejo no la muestra. Sin
migraciones ni limpieza pendiente. Si la caché quedó poblada con el comportamiento nuevo, purgar el
host correspondiente (el transient caduca solo en el próximo borde de fecha o en 12 h).
