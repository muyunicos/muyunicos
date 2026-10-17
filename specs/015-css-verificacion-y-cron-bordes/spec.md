# 015 — Verificación de CSS + cron de bordes

**Estado**: Tramo A implementado y desplegado (2026-10-17); Tramo B pendiente.
**Rama**: `fix/css-verificacion-y-cron-bordes` · **Riesgo**: bajo (un arreglo de CSS y un cron; el front no se toca salvo la purga).

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

## Tramo B — Cron de bordes: auto-sanación y estado visible (pendiente)

Ver `tasks.md`. Resumen del diseño aprobado:

- **Evento único por borde** (ya existe, feature 012): con el cron del hosting (`*/5`, medido), se
  ejecuta **≤5 min** después del borde. Aceptable con caché de páginas de 24 h; se declara.
- **Auto-sanación diaria** (`mu_hero_banner_revisar_bordes`): purga **sólo si el borde cambió** y
  **rearma** el evento único si falta o quedó viejo. Idempotente.
- **Estado visible**: opción `mu_hero_banner_ultima_purga` + una fila en el panel
  (*«Última purga por borde: … · N hosts · por URL»*, en rojo si hay un borde pasado sin purgar).
- **Rastro a prueba de `>/dev/null`**: archivo plano (patrón `mu_bot404_record_hit()`), porque en
  CLI `error_log()` va a stderr y el cron del hosting lo descarta.
- **Respaldo declarado**: si no existe el hook de purga por URL, `litespeed_purge_all` queda
  registrado como `modo: all` y visible en el panel.
