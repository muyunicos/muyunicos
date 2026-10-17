# 012 — Vigencia anual del banner (repetir cada año)

**Estado**: implementado (2026-10-17). Rama `feat/vigencia-anual-banner`.

## Problema

Las promociones del Hero son **estacionales y se repiten todos los años**: la campaña escolar
cierra en junio, la de Navidad va del 20/12 al 10/01, la de Reyes del 01/01 al 06/01. Hoy la
ventana guarda **una fecha con año** (`DDMMAAAA`) y el banner deja de verse cuando ese año termina:
todos los años hay que volver a entrar al panel, recordar qué banners vencían y reescribir las
fechas — con la ventana anual del calendario comercial ya conocida desde el principio.

El costo de olvidarse es asimétrico: la promo **desaparece del sitio sin error** y nadie se entera
hasta que alguien mira la home (el transient y el filtro fallan en silencio, por diseño).

Con la caché de páginas medida en **24 h** (`X-LiteSpeed-Cache-Control: public,max-age=86400`,
2026-10-17), el borde de una ventana anual llega **sin que nadie guarde**, así que la purga que hoy
sólo ocurre al guardar no se dispara: el HTML viejo se sirve hasta 24 h después del cambio.

## Cambios

| # | Cambio | Archivos |
|---|---|---|
| 1 | **`anual` por ámbito** (general y `fechas[PAIS]['anual']`): con el flag, se ignoran los años guardados y **sólo cuentan día y mes**. Se persiste sólo cuando difiere de la general (misma convención de sobrescritura dispersa que textos y fechas): la clave ausente = hereda. | `inc/hero-banners.php` |
| 2 | **`mu_hero_banner_mmdd()`**: extrae `MMDD` de una fecha válida (o `''`). | `inc/hero-banners.php` |
| 3 | **`mu_hero_banner_anual_efectivo()`**: resuelve el tri-estado (propio / heredado de la general). | `inc/hero-banners.php` |
| 4 | **`mu_hero_banner_ventana_anual()`**: instancia que corresponde a un instante dado; resuelve el **cruce de fin de año** (20/12 → 10/01) y el *clamp* de 29/02 en años no bisiestos. Devuelve la instancia activa o, si ya pasó, la próxima. | `inc/hero-banners.php` |
| 5 | **`mu_hero_banner_ventana_instantes()`** gana `$now` (opcional, retrocompatible) y delega en la anual. `mu_hero_banner_is_active_for_pais()` le pasa su `$now`, así el TTL y la evaluación usan el mismo instante. | `inc/hero-banners.php` |
| 6 | **`mu_hero_banner_proximo_borde()`** queda anual-aware vía (5): cuando la instancia del año ya cerró, el próximo borde es el **inicio del año siguiente** (antes devolvía 0 → el banner entraba hasta 12 h tarde y no se reprogramaba nada). | `inc/hero-banners.php` |
| 7 | **Aviso `anuales_incompletas`** en `mu_hero_banner_avisos()`: con el flag tildado y una sola fecha no hay límite real (se muestra siempre) → la fila lo avisa en vez de fallar en silencio. | `inc/hero-banners.php` |
| 8 | **UI**: un checkbox «Se repite cada año» por ámbito dentro del panel de fechas, con el valor **efectivo** tildado; el hint del panel (M6) muestra la instancia calculada con **año real** y aclara que se repite. El punto de la píldora cuenta el flag como ventana propia. | `inc/hero-banners.php`, `js/admin-hero-banners.js`, `css/admin-hero-banners.css` |
| 9 | **Purga de la home en el borde** (`mu_hero_banner_programar_purga_borde()` + `mu_hero_banner_purge_borde_ejecutar()`): un **evento único** de WordPress en el próximo borde, que purga la home de cada host y se reprograma. Purga **condicionada a un borde real** (nunca periódica ni incondicional) y se cierra el pendiente de `MIGRATION-GUIDE.md` §8. | `inc/hero-banners.php` |
| 10 | **Arnés propio** (PHP + JS) de ventana anual, más el ajuste de la aserción exacta de avisos en el arnés de 010. | `specs/012-vigencia-anual/harness/`, `specs/010-…/harness/arnes-fechas-por-pais.php` |

### Decisiones

- **Sólo día y mes, herencia por ámbito** (elegido por el mantenedor): el flag vive en el mismo eje
  que las fechas (general + país), no global al banner, para que un país pueda repetir anualmente
  su propia ventana aunque la general sea puntual, y al revés.
- **Tri-estado por ausencia**: la clave `anual` de un país se guarda **sólo si difiere** de la
  general; ausente = hereda. Un checkbox común alcanza para esto porque el formulario se renderiza
  con el valor **efectivo** (tildado si hereda un sí), así que "no tocar nada" reenvía lo mismo y
  la sobrescritura se autocorrige cuando la general cambia.
- **Cruce de fin de año soportado**: 20/12 → 10/01 es el caso más común de promos anuales. Se
  evalúan dos instancias candidatas (la que empieza este año y la que empezó el anterior) y se elige
  la que contiene el instante pedido; si ninguna lo contiene, se devuelve la futura más cercana.
- **29/02 se acota al último día del mes** cuando el año de la instancia no es bisiesto (hoy ni se
  puede guardar `29022026`: `checkdate()` lo rechaza). Prometer un 29/02 que nunca llega es peor que
  caer el 28.
- **Una sola fecha con el flag tildado = sin límite** (igual que sin el flag) + aviso: se prefiere
  mostrar de más y avisar antes que ocultar una promo viva (criterio de M3/M4).
- **La purga en el borde es condicional por construcción**: un evento único en el instante del
  borde, no un barrido periódico. Cuando el hook por URL de LiteSpeed no está registrado se cae a
  `litespeed_purge_all` **en ese borde** (no en cada request) y se deja rastro `[MU-HERO-BORDE]`.
- **Sin migración**: los banners guardados no tienen la clave → heredan `false` → comportamiento
  idéntico al de la ventana fija de 010.

### Criterios de aceptación

1. Con `anual` tildado y `20/12`–`10/01`, el banner está activo en diciembre **y** en enero del año
   siguiente, y no en marzo (arnés, sección 2).
2. Con el flag, cambiar el **año** guardado no cambia la ventana: `20122026`–`10012027` y
   `20122031`–`10012032` dan los mismos instantes (arnés, sección 2).
3. Una ventana anual ya cerrada (01/06–30/06 en febrero) no se muestra y `proximo_borde()` devuelve
   el **1 de junio del año siguiente** (arnés, secciones 2 y 3).
4. `29/02` con el flag tildado cae al 28/02 en años no bisiestos y cae el 29/02 en bisiestos
   (arnés, sección 2).
5. El huso del país y el fin inclusivo DST-safe se conservan: 05/09–07/09 en Chile dura 71 h
   (arnés, sección 2).
6. Herencia del flag: general sí / país no, general no / país sí, y país que no declara nada
   (arnés, sección 4).
7. El saneado guarda la clave sólo cuando difiere y **no pierde** la ventana de un país cuyo único
   dato propio es el flag (arnés, sección 4).
8. Una fecha anual incompleta se reporta en `anuales_incompletas` y la fila la muestra (arnés,
   secciones 5 y 6).
9. El TTL del transient sigue acortándose al próximo borde, también con ventanas anuales (arnés,
   sección 3).
10. El evento de purga se programa en el próximo borde (y no antes) y, al ejecutarse, purga la home
    de los hosts del sitio y se reprograma (arnés, sección 7).
11. La ventana fija de 010 no cambia: los 96 asertos del arnés 010 siguen pasando (con el único
    ajuste declarado: la forma exacta del array de avisos).

### Verificación

- **Automática**: `specs/012-vigencia-anual/quickstart.md` Fase A (arnés PHP + arnés JS propios y
  la no-regresión de 006/008/010/011).
- **Manual (mantenedor)**: Fase B del quickstart, en el panel y con el front; sin ella la feature
  se declara NO VERIFICADA (`MIGRATION-GUIDE.md` §8).

### Limitaciones declaradas

- El `wp_schedule_single_event` depende del cron de WordPress (se dispara con tráfico): si el sitio
  quedara sin visitas justo en el borde, la purga de la home ocurriría en la primera visita
  posterior. El transient del país (fuente de verdad del filtro) sigue siendo correcto.
- La purga en producción se verifica a mano: `curl.exe -sI` no dice "cuándo se purgó", sólo el
  estado (`X-Litespeed-Cache` / `age`).
