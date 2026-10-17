# Tasks: Verificación de CSS + cron de bordes (015)

**Input**: `specs/015-css-verificacion-y-cron-bordes/spec.md`

## Format: `[ID] [P?] Description`

- `[X]` = ejecutada; `[ ]` = pendiente

---

## Tramo A · Bug de «ver todos» + arnés de CSS

- [X] T001 `css/admin-hero-banners.css`: convertir la línea huérfana 498 en un comentario válido (el `*/` sin `/*` descartaba la regla `.is-hereda { display: none }`)
- [X] T002 `specs/015-…/harness/css-check.js`: arnés de CSS (comentarios balanceados con línea, llaves balanceadas, reglas clave del panel y 8 casos sintéticos del detector)
- [X] T003 Validar el arnés **en orden**: con el bug falla señalando la línea 498; después del arreglo pasa (68 OK)
- [X] T004 Barrido de los 24 CSS del tema (un solo positivo real)
- [ ] T005 Sumar el comando del arnés a la Fase A de los quickstarts de 013/014/015
- [X] T006 Commit `fix(admin-css)` + deploy del arreglo por separado (es un bug visible)

## Tramo B · Cron de bordes

- [X] T010 `mu_hero_banner_revisar_bordes()`: evento diario que purga **sólo si el borde esperado quedó atrás y no hay evento pendiente** y **rearma** el evento único si falta o está viejo
- [X] T011 Enganche del evento diario con guard (`wp_next_scheduled`) en `init` + `mu_hero_banner_borde_esperado` recordado al programar y limpiado al reprogramar
- [X] T012 `mu_hero_banner_ultima_purga` (opción: `ts`, `hosts`, `modo`) y el renglón de estado en el panel (rojo si hay un borde pasado sin purgar)
- [X] T013 Rastro a **archivo plano** (`wp-content/uploads/mu-logs/hero-borde.log`, patrón `mu_bot404_record_hit()`) además de `error_log`
- [X] T014 `modo: url|all` explícito, con el respaldo visible en el panel
- [X] T015 Arnés: 15 aserciones nuevas en 012 (sección 11) — el arnés queda en **102 OK**
- [X] T016 `MIGRATION-GUIDE.md` §2 (comando real del cron `*/5` y el `>/dev/null`) y §8 (estado de 015)
- [ ] T017 Fase B manual: los países que heredan no se ven y «ver todos» los muestra; los dos eventos agendados; «Última purga por borde» actualizándose sin leer logs
