# 010 — Hero banners: fechas por país

**Estado**: implementado (2026-10-17). Rama `feat/fechas-banner-por-pais`.

## Problema

El banner del Hero tenía **una sola ventana de fechas** (`inicio`/`fin` globales) para todos
los países tildados. La promoción real no es la misma en cada zona: una campaña escolar cierra
en febrero en Argentina y en junio en Brasil, y hasta ahora la única salida era duplicar el
banner completo por país —con su propio juego de textos y traducciones— o dejar la ventana
ancha para todos (mostrando la promo cuando ya no aplica en un país).

Además, las píldoras de país vivían en la cabecera de la fila como **etiquetas decorativas**:
mostraban los países activos pero no permitían editar nada por país, y el JS las volvía a
construir a mano cada vez que se tildaba un checkbox (marcado duplicado en PHP y JS).

## Cambios

| # | Cambio | Archivos |
|---|---|---|
| 1 | **`fechas[PAIS]`**: cada país puede tener su propia ventana `inicio`/`fin`. Campo vacío (o igual a la general del mismo campo) **hereda** la fecha general — misma convención de sobrescritura dispersa que las traducciones. Banners existentes: comportamiento idéntico, sin migración. | `inc/hero-banners.php` |
| 2 | **Función pura `mu_hero_banner_pais_fechas()`**: resuelve la ventana efectiva de un país (propia o heredada, campo por campo). | `inc/hero-banners.php` |
| 3 | **Función pura `mu_hero_banner_is_active_for_pais()`**: extrae el filtro de fecha del front (sin fechas = siempre; ventana inclusiva; rango invertido = nunca). `mu_get_hero_banners()` la usa. | `inc/hero-banners.php` |
| 4 | **Saneado**: `fechas` se valida con `\d{8}`, se limita a países tildados y guarda vacío lo idéntico a la general (entrada descartada si ambos campos quedan vacíos). | `inc/hero-banners.php` |
| 5 | **Píldoras de país en el bloque de ajustes generales** (mismo estilo `.mu-hero-banner-paises-pills`): una píldora `Global` + una por país tildado. Eligen **de qué país se editan las fechas** (píldora = pestaña, como las de idioma) y el **punto** marca los países con ventana propia. Se retiran las píldoras decorativas de la cabecera. | `inc/hero-banners.php`, `js/admin-hero-banners.js`, `css/admin-hero-banners.css` |
| 6 | **Paneles de fecha por país**: un par Inicio/Fin por país (`mu_banner[i][fechas][AR][inicio]`), oculto hasta que se elija su píldora. El valor general se usa como **placeholder** y hay aviso de **rango invertido**. | `inc/hero-banners.php`, `js/admin-hero-banners.js` |
| 7 | **Sin HTML generado en JS**: los paneles y las píldoras se renderizan completos en PHP; el JS sólo alterna visibilidad y estado. | `inc/hero-banners.php`, `js/admin-hero-banners.js` |
| 8 | **Arnés de la feature** (módulo real + WordPress simulado) y arnés JS de las funciones puras nuevas. | `specs/010-fechas-banner-por-pais/harness/` |

### Decisiones

- **Global + por país, con herencia por campo** (elegido por el mantenedor): la píldora `Global`
  conserva las fechas que ya existían y sigue mandando en los países que no sobrescriben. Un país
  puede definir sólo el inicio (cierra cuando la general cierra) o sólo el fin.
- **Lo idéntico a la general no se persiste**: la BD guarda sólo la diferencia, así que el estado
  “este país tiene ventana propia” se deduce del contenido y nunca se desincroniza de la regla de
  guardado (igual que el punto de los idiomas).
- **La píldora activa arranca en `Global`**: no se guarda un “país activo” en la BD. Editar las
  fechas generales es lo que aplica a la mayoría de los casos.
- **Sin dimensiones nuevas en la caché**: el transient ya era por país
  (`mu_hero_banners_active_{PAIS}`) y el filtro de fecha se resuelve al poblarlo, así que la
  separación entre subdominios se mantiene sin tocar la clave ni la purga.
- **Rango invertido**: `inicio > fin` no se muestra nunca (regla heredada del filtro anterior). El
  panel lo avisa al escribir en lugar de aceptarlo en silencio.
- **Un país destildado pierde su ventana al guardar** (declarado): `fechas` sólo conserva países
  tildados. Volver a tildarlo no revive las fechas.
- **Sin `pattern` en los campos de fecha**: los paneles se ocultan al alternar píldoras, y un campo
  oculto con formato inválido **bloquea el envío** con un “not focusable” que el usuario no puede
  ver ni corregir. El formato lo avisa el propio panel (`fechasFormato`) y el saneado del servidor
  sigue descartando lo inválido, como antes. Se conserva `maxlength="8"` e `inputmode="numeric"`
  como guía, que no bloquean nada.

## Criterios de aceptación

1. Un banner con `fechas[BR]` se muestra en `br.muyunicos.com` sólo dentro de su ventana, y en los
   demás subdominios según la general, **sin cambiar la clave del transient**.
2. Un banner sin `fechas` (todo lo guardado hoy) se comporta exactamente igual que antes.
3. En el panel, el bloque de ajustes generales muestra las píldoras de país tildado + `Global`; al
   hacer clic en una píldora se abren las fechas de ese país y sólo esas.
4. Al escribir una fecha propia, el punto de la píldora del país se enciende; al borrarla (o
   dejarla igual a la general), se apaga.
5. Los campos de país vacíos muestran la fecha general como placeholder y se envían vacíos.
6. Un formato distinto de DDMMAAAA o un inicio posterior al fin ⇒ aviso en el panel; lo inválido se
   descarta al guardar y un rango invertido no se muestra nunca en el front.
7. Destildar un país oculta su píldora y su panel: no se edita una ventana que no se va a guardar.
8. El guardado sigue pasando por el Hash Gate (sin cambios reales no hay purga) y la caché de
   página se purga sólo si `maybe_serialize()` detectó diferencia.
9. Nada bajo `Tema-GeneratePress/`; sin CSS/JS inline nuevo.

## Fuera de alcance

- Fechas por **idioma** (la ventana sigue siendo la misma para `/pt/`, `/en/` y el idioma base).
- Ventanas recurrentes, horas del día o un calendario con `datepicker` (el formato sigue siendo
  DDMMAAAA).
- Reemplazo del botón/dropdown **Países** de la cabecera: sigue siendo la selección de países
  activos del banner.
- Migración/limpieza de `fechas` de países destildados entre despliegues: se descartan en el
  siguiente guardado.

## Limitaciones declaradas

- **Transient vs. borde de fecha — RESUELTO en la misma feature**: el TTL del transient se acorta
  hasta el próximo borde (`mu_hero_banner_proximo_borde()`), así que caduca justo cuando el hero
  cambia en lugar de esperar 12 h. Lo que puede demorar es la **caché de páginas** de LiteSpeed
  (HTML por subdominio), cuyo TTL **no está medido**; la Fase B del quickstart purga el host antes
  de medir y `MIGRATION-GUIDE.md` §8 lo deja como decisión abierta (cron de purga en el borde).
- **Rollover de PHP — RESUELTO**: `mu_hero_banner_fecha_valida()` valida con `checkdate()`, así que
  `99012026`, `31022026` o un mes 13 se descartan al guardar. Un valor inválido ya guardado se
  trata como “sin límite” (el banner se sigue viendo, no se oculta una promo viva por un typo) y la
  fila del panel lo avisa.
- **Verificación del panel**: el manejo de jQuery (píldoras, paneles, puntos) no es automatizable
  en este proyecto; se verifican las funciones puras con arnés y el resto a mano (deuda declarada
  en `MIGRATION-GUIDE.md` §8).

## Mejoras posteriores (2026-10-17, misma feature)

| # | Mejora | Detalle |
|---|---|---|
| M1 | **Huso horario del país de destino** | `muyu_get_countries_data()` declara `tz` por país; `mu_hero_banner_pais_tz()` resuelve la ventana en ese huso (respaldo: `wp_timezone()`, nunca el huso por defecto de PHP). Antes, el mismo `01/06` caía a horas distintas por subdominio (Madrid y Buenos Aires están a 5 h en junio) y en Brasil podía caer el 31/05. |
| M2 | **Fin del día a prueba de DST** | El fin inclusive se calcula como “inicio del día siguiente − 1 s” en el huso del país, no con `setTime(23,59,59)`: hay husos donde esa hora de reloj no existe (el 06/09/2026 en Chile el día tiene 23 h). |
| M3 | **Validación real de fechas** | `mu_hero_banner_fecha_valida()` (regex + `checkdate()`) en el saneado y en el front; el JS replica la validación (incluido el bisiesto) para avisar en el panel. |
| M4 | **Avisos en la lista** | `mu_hero_banner_avisos()` marca en la fila (sin abrir el panel) las fechas inválidas guardadas y los rangos invertidos, con el ámbito afectado (`Fechas generales`, `BR`, …). |
| M5-A | **TTL adaptativo** | `mu_hero_banner_proximo_borde()` da el próximo instante en que cambia el escenario del país (entrada o salida); el transient se acorta hasta ahí (piso 60 s, techo 12 h). |
| M6 | **Huso a la vista** | Cada panel de país muestra la ventana efectiva con lo guardado en el huso del país (`Con lo guardado se ve del 01/06/2026 00:00 al 30/06/2026 23:59, en el huso del país (America/Sao_Paulo)`), y el panel general aclara que las fechas son medianoche LOCAL. |
| M7 | **No se pierden fechas al destildar** | El saneado conserva las ventanas de todos los países válidos (el front filtra primero por `paises`), así que destildar y volver a tildar no borra lo cargado. |

### Criterios de aceptación de las mejoras

1. Con `inicio` = `01062026` en AR y ES, la ventana de Madrid empieza 5 h antes que la de Buenos
   Aires y cada una se mide en su propio huso (arnés, sección 8).
2. Una ventana que cruza un cambio de horario dura lo que dura ese calendario local (71 h en el
   caso de Chile 05–07/09/2026), y el último segundo del fin sigue visible (arnés, sección 8).
3. Guardar `31022026` o `99012026` no persiste la fecha; un valor inválido legacy no oculta el
   banner y la fila lo avisa (arnés, secciones 7 y 10).
4. El transient de un país sin bordes futuros dura 12 h; con un borde más cercano, se acorta a ese
   borde (arnés, sección 9).
5. Cada panel de país muestra su huso y la ventana efectiva (arnés, sección 5).
6. La caché sigue siendo por país: la clave no cambió (arnés, sección 4).
7. El tope de textos por acción es del ámbito (un banner tiene 6 campos y ya no queda bloqueado):
   ver `specs/006-ai-translate-core/harness/arnes.php`, sección 11, y §6 del guide.

