# 015 — Verificación de CSS + cron de bordes

**Estado**: implementada y desplegada (2026-10-17). **Rama**: `fix/css-verificacion-y-cron-bordes`.

## Tramo A — El bug de «ver todos los países» y la verificación que faltaba

### Problema (reportado por el mantenedor)

En el panel, «ver todos los países» parecía no hacer nada: **los 11 países se veían siempre**.

### Causa raíz (encontrada leyendo el CSS)

`css/admin-hero-banners.css:498` tenía una **línea huérfana**: el cierre `*/` de un comentario
cuya apertura `/*` se había perdido en una edición mía anterior (usé como ancla la **primera línea
de un comentario de dos**, y quedó la segunda suelta):

```css
497: }                                                                          ← cierre del @media
498:    Global siempre; cada país sólo si difiere de la general (…). */         ← ✗ sin apertura
499: .mu-hero-banner-fechas-block.is-hereda { display: none; }                  ← el navegador la descarta
```

El parser, al encontrar ese texto basura, asocia el bloque siguiente a un selector inválido y
**descarta la regla**: `.is-hereda` nunca se aplicaba → nada se ocultaba → «ver todos» no cambiaba
nada (su propia regla sí se aplicaba, pero no había nada oculto que mostrar).

### Por qué no lo cazó ningún arnés

Hay `php -l`, `node --check` y diez arneses, pero **cero verificación de CSS**. El arnés del panel
comprueba que la clase `is-hereda` **esté en el HTML** (estaba), no que el CSS la aplique.

### Cambios

| # | Cambio | Archivos |
|---|---|---|
| 1 | La línea huérfana pasa a ser un comentario válido de una línea; `.is-hereda { display: none }` y `.is-ver-todos … { display: table-row }` vuelven a aplicarse | `css/admin-hero-banners.css` |
| 2 | **Arnés de CSS** (`harness/css-check.js`, node sin dependencias): comentarios balanceados (detecta el cierre sin apertura y dice **en qué línea**), llaves balanceadas por archivo ignorando comentarios, y las reglas clave del panel (incluidas las dos de este bug) | `specs/015-…/harness/css-check.js` |

### Criterios de aceptación (cumplidos)

1. El arnés **falla con el bug presente**, señalando `css/admin-hero-banners.css` y la línea 498, y
   **pasa después del arreglo** (68 OK). Se validó en ese orden.
2. «ver todos los países» alterna de verdad: oculto = Global + excepciones; tildado = todos.
3. Los 24 CSS del tema pasan el chequeo de comentarios y llaves (el barrido manual había dado **un
   solo positivo real**: esta línea; el resto eran cierres legítimos de comentarios multilínea).
4. Los 8 casos sintéticos del detector (válido, cierre huérfano, sin cerrar, llaves de más/de
   menos, llaves dentro de comentarios) pasan.

## Tramo B — Cron de bordes: implementado

| # | Pieza | Detalle |
|---|---|---|
| 1 | **Borde recordado** | Al programar el evento único se guarda `mu_hero_banner_borde_esperado`. Es lo que permite distinguir «el cron todavía no llegó» (≤5 min) de «el evento se perdió» |
| 2 | **Auto-sanación diaria** | `mu_hero_banner_revisar_bordes()` (evento `mu_hero_banner_revisar_bordes_hook`, agendado con guard en `init`): si el borde esperado ya pasó **y** no hay evento pendiente para él → purga **una vez**; después **rearma** el evento si falta o quedó en un borde viejo. En el caso normal **no hace nada** (cero purgas de más) |
| 3 | **Estado visible** | `mu_hero_banner_ultima_purga` (ts, hosts, modo) + un renglón arriba de la lista: *«Última purga por borde de fecha: … · N host(s) · por URL»*, y en rojo si hay un borde pasado sin purgar |
| 4 | **Rastro a prueba del `>/dev/null`** | `mu_hero_banner_log_borde()` escribe en `wp-content/uploads/mu-logs/hero-borde.log` (mismo directorio que los contadores de bot404), **además** de `error_log()`. Medido: en CLI `error_log()` va a stderr y el cron del hosting lo descarta |
| 5 | **Modo declarado** | `modo: url|all` en la opción y en el panel: si el plugin no expone `litespeed_purge_url`, se ve que se está purgando todo el sitio |
| 6 | **Arnés** | 15 aserciones nuevas en el arnés de 012 (sección 11): caso normal sin purga, borde perdido que purga y rearma, evento viejo que se rearma sin purgar, los dos modos de purga y los enganches |
| 7 | **Guide** | §2 con el comando real del cron, el retraso de hasta 5 min y dónde vive el rastro; §8 con el estado |

### Decisiones

- **No se toca la configuración del hosting**: `*/5` es la forma correcta para `DISABLE_WP_CRON` y
  alcanza (24 h de caché de páginas contra 5 min de retraso).
- **La revisión diaria no adelanta la purga**: el mecanismo fino sigue siendo el evento único; la
  revisión es red de seguridad y rearme. Por eso es idempotente y no purga sin un borde atrasado.
- **El respaldo se mantiene** (purgar de más antes que servir un hero vencido), pero declarado:
  el modo queda a la vista en el panel en lugar de esconderse en un log.

