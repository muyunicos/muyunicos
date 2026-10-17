# Plan 012 — Vigencia anual del banner

## Enfoque

La feature **no agrega ejes nuevos**: el flag `anual` vive en el mismo ámbito que las fechas
(general + `fechas[PAIS]`) y toda la resolución se hace en **funciones puras** con el instante
inyectado, igual que 010. El front no cambia su contrato (`mu_get_hero_banners()` sigue devolviendo
los banners activos del país) y la clave del transient sigue siendo por país.

1. **Datos**: `anual` (general, raíz) y `fechas[PAIS]['anual']` (sobrescritura **dispersa**: se
   guarda sólo si difiere de la general; ausente = hereda). Un checkbox común alcanza porque el
   formulario se renderiza con el valor **efectivo**.
2. **Lógica pura**: `mu_hero_banner_mmdd()`, `mu_hero_banner_anual_efectivo()`,
   `mu_hero_banner_ventana_anual()` (con cruce de fin de año y *clamp* de 29/02).
3. **Cascada**: `mu_hero_banner_ventana_instantes( $promo, $pais, $now = null )` delega en la anual
   cuando el flag está activo; `mu_hero_banner_is_active_for_pais()` y `mu_hero_banner_proximo_borde()`
   la consumen. El parámetro `$now` es **opcional** → los llamados existentes siguen valiendo.
4. **Purga en el borde**: `mu_hero_banner_programar_purga_borde()` programa **un** evento único en
   el próximo borde (guard `wp_next_scheduled`, patrón de `inc/navigation-chips.php`), y
   `mu_hero_banner_purge_borde_ejecutar()` purga la home de cada host del sitio y se reprograma.

## Contrato de las funciones nuevas

| Función | Entrada | Salida |
|---|---|---|
| `mu_hero_banner_mmdd( $ddmmaaaa )` | `'20122026'` | `'1220'` (o `''` si la fecha no es válida) |
| `mu_hero_banner_anual_efectivo( $promo, $pais )` | banner + país | `bool` (propio si existe, si no el general) |
| `mu_hero_banner_ventana_anual( $inicio, $fin, $tz, $now )` | fechas + huso + instante | `[ start, end, activo ]` o `null` |
| `mu_hero_banner_ventana_instantes( $promo, $pais, $now = null )` | banner + país (+ instante) | `[ start, end ]` o `null` (sin cambios de forma) |
| `mu_hero_banner_avisos( $promo )` | banner | `[ 'invalidas' => [], 'invertidas' => [], 'anuales_incompletas' => [] ]` |
| `mu_hero_banner_proximo_borde_global()` | — | instante del próximo borde de todo el sitio (o `0`) |

### Ventana anual: reglas

- `MMDD` comparados como **texto** (`'1220' > '0110'` ⇒ cruza el fin de año).
- Candidatas: la instancia que **empieza este año** y, si cruza, la que **empezó el año pasado**.
  Se devuelve la que contiene `$now` (activo = `true`); si ninguna lo contiene, la **futura más
  cercana** (activo = `false`), que es lo que necesita `proximo_borde()`.
- Inicio: `00:00` local del huso del país. Fin: **inicio del día siguiente − 1 s** (M2, DST-safe).
- Día acotado al último del mes cuando no existe en ese año (29/02 → 28/02).

## Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| El arnés 010 compara el array de avisos por igualdad exacta | Se agrega la clave nueva y se **actualiza esa aserción** (cambio declarado en la spec y en el guide §8) |
| `wp_schedule_single_event` perdido (cron de WP) | El handler reprograma; el transient por país sigue siendo la fuente de verdad del filtro |
| Purga demasiado agresiva | Sólo un evento por borde real; `litespeed_purge_url` por host y `litespeed_purge_all` como respaldo **en ese borde**, con rastro `[MU-HERO-BORDE]` |
| Regresión en la ventana fija | Los 96 asertos de 010 deben seguir verdes; el arnés 012 incluye un caso de ventana fija con el flag ausente |

## Reversión

`git revert` del commit: `anual` queda guardada pero el código anterior la ignora y el front vuelve
a la ventana fija con el año tipeado. Sin migraciones. Si el evento quedó programado, se ejecuta una
purga de home de más (inocua) y no se reprograma.
