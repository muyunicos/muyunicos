# Quickstart 012 — Vigencia anual del banner

## A. Verificación automática (local, reproducible)

```powershell
# Sintaxis
php -l inc/hero-banners.php
node --check js/admin-hero-banners.js

# Arneses propios (módulo real con WordPress simulado, sin red)
php specs\012-vigencia-anual\harness\arnes-anual.php   # esperado: 87 OK / 0 FAIL
node specs\012-vigencia-anual\harness\anual-js.js      # esperado: 18 OK / 0 FAIL

# No-regresión (010 se ajusta en una sola aserción: la forma del array de avisos)
php specs\010-fechas-banner-por-pais\harness\arnes-fechas-por-pais.php  # esperado: 96 OK / 0 FAIL
node specs\010-fechas-banner-por-pais\harness\fechas-js.js             # esperado: 28 OK / 0 FAIL
node specs\011-variantes-banners-ia\harness\variantes-js.js            # esperado: 18 OK / 0 FAIL
php specs\008-banners-pildoras-ia\harness\arnes-banners.php            # esperado: 30 OK / 0 FAIL
node specs\008-banners-pildoras-ia\harness\banners-js.js               # esperado: 17 OK / 0 FAIL
php specs\006-ai-translate-core\harness\arnes.php                      # esperado: 54 OK / 0 FAIL

# Invariantes
Select-String -Path js\admin-hero-banners.js -Pattern 'data-role="anual"'
# esperado: 3 líneas (leer el estado, sincronizar los países y el handler del cambio)

Select-String -Path inc\hero-banners.php -Pattern 'mu_hero_banner_ventana_anual'
# esperado: 6 líneas (definición, la llamada desde ventana_instantes y las menciones del docblock)

Select-String -Path inc\hero-banners.php -Pattern 'wp_schedule_single_event'
# esperado: 2 líneas (la guarda function_exists y la única llamada: un evento por borde,
# nunca un barrido periódico)

# Alcance
git diff --name-only   # nada bajo Tema-GeneratePress/
```

Qué cubre el arnés PHP: `MMDD` de una fecha válida; ventana anual sin cruce y **cruzando el
fin de año** (activa en diciembre y en enero, no en marzo/agosto); irrelevancia del año
guardado; los dos bordes inclusive; la instancia vigente en enero es la que empezó en
diciembre; el ciclo cerrado devuelve la instancia del año siguiente (y `proximo_borde()`
apunta a ella); **29/02 acotado al 28/02** en años no bisiestos y respetado en bisiestos;
DST de Chile (71 h) con el flag y sin él (idéntico → sin regresión); el **tri-estado del
flag** (general sí/país no, general no/país sí, ausente = hereda); el saneado (la clave se
persiste sólo cuando difiere; un país cuyo único dato es el flag **no se pierde**; sin
desviación la entrada por país queda igual que en 010); los avisos (`anuales_incompletas`,
y que cruzar el año **no** se reporte como invertido); el **marcado real de la fila**
(campo oculto + casilla por ámbito, casilla efectiva, invariantes de 010 intactas); el
**TTL adaptativo** con una ventana anual; y el **cron**: el evento se programa en el
próximo borde, no se duplica, se recalcula al guardar, purga la home de cada host cuando
el hook por URL está disponible (y sólo entonces: si no, purga total **en ese borde**),
limpia los transients y deja el rastro `[MU-HERO-BORDE]`.

El arnés JS cubre las funciones puras de los avisos del panel: rango invertido con y sin
flag, y la repetición anual incompleta (una sola fecha). El manejo de jQuery sigue siendo
verificación manual (misma deuda que 008/010/011).

## B. Verificación manual (mantenedor)

**Antes de empezar**: purgar la caché de los hosts que se van a mirar (el marcado del panel
y del hero cambian). La caché de **páginas** de la home dura 24 h
(`X-LiteSpeed-Cache-Control: public,max-age=86400`, medido 2026-10-17), así que el cron del
borde es el que la adelanta; el transient del país caduca solo en el próximo borde.

1. **Panel** (WC → Marketing → Hero Banners): en el panel general debe verse la casilla
   «Se repite cada año» con su hint.
   - Tildarla: la casilla de los países que **no** tenían decisión propia debe tildarse
     sola (heredan). El panel de cada país debe decir *«… Se repite cada año: el año de las
     fechas se ignora.»*
   - Destildar la de un país: el **punto** de su píldora debe encenderse (tiene ventana
     propia: usa el flag contrario al global). Volver a tildarla → el punto se apaga.
2. **Front con un año pasado**: cargar un banner con `01/10/2020` → `31/10/2020` y la
   casilla tildada. Hoy (octubre) el banner **debe verse**: la ventana volvió este año.
   Sin la casilla, el mismo banner **no** debe verse. Repetir con `20/12/2020` →
   `10/01/2021`: hoy no se ve, pero el hint del panel debe mostrar la instancia con el año
   real (la que corresponde a hoy o la próxima).
3. **Promo de fin de año**: con `20/12` → `10/01` y la casilla tildada, la ventana debe
   **durar dos meses de calendario** (diciembre y los primeros 10 días de enero) y no
   reportarse como rango invertido.
4. **Aviso**: dejar sólo la fecha de inicio con la casilla tildada → la fila debe avisar
   *«… la repetición anual quedó con una sola fecha, así que el banner se muestra
   siempre; completá Inicio y Fin.»* (y el panel muestra el mismo aviso al pie).
5. **Cron del borde**: comprobar que el evento quedó agendado
   (`wp_next_scheduled('mu_hero_banner_purge_borde')` desde un plugin/CLI, o revisar
   `debug.log`). En el borde debe aparecer `[MU-HERO-BORDE] purga de la home por borde de
   fechas host(s)=…` y la home debe regenerarse con el hero nuevo, sin guardar nada a mano.
   Para probarlo sin esperar: guardar un banner anual cuya ventana cierre hoy.
6. **No-regresión**: un banner sin la casilla se comporta igual que antes (ventana fija del
   año guardado). Guardar sin cambios no debe purgar nada (Hash Gate) — atención: el primer
   guardado después del deploy **sí** purga una vez, porque la opción gana la clave `anual`.

## C. Reversión

`git revert` del commit de la feature. En la base, `anual` (y `fechas[PAIS][anual]`) quedan
guardadas pero el código anterior las ignora: el front vuelve a la ventana fija con el año
tipeado y el panel viejo no muestra la casilla. Si el evento `mu_hero_banner_purge_borde`
quedó agendado, se ejecutará una purga de home de más (inocua) y no se reprogramará. Sin
migraciones ni limpieza pendiente.
