# Plan 010 — Fechas por país en Hero Banners

**Feature**: `specs/010-fechas-banner-por-pais/spec.md` · **Rama**: `feat/fechas-banner-por-pais`

## Enfoque

El módulo ya resolvía país y fecha en el mismo lugar (`mu_get_hero_banners()`), así que el cambio
no agrega dimensiones nuevas: se **baja a datos** la ventana por país y se **extrae a funciones
puras** la resolución, para poder verificarla sin WordPress ni base de datos (única forma de
verificar en este proyecto, que no tiene suite ni staging).

1. **Datos**: `fechas[PAIS] => [ inicio, fin ]` dentro del mismo banner de `wp_options`
   `mu_hero_banners`. Herencia campo por campo hacia `inicio`/`fin` (generales). Sin migración:
   los banners guardados no tienen la clave y heredan todo.
2. **Lógica pura**: `mu_hero_banner_pais_fechas()` (ventana efectiva) y
   `mu_hero_banner_is_active_for_pais()` (evaluación con timestamp inyectable). Reemplazan el
   bloque inline del filtro; el front no cambia su contrato (`mu_get_hero_banners()` sigue
   devolviendo los banners activos del país).
3. **Caché**: intacta por diseño — la clave del transient ya era por país y el filtro se resuelve
   antes de poblarlo. El Hash Gate ya compara el array serializado, así que `fechas` participa sin
   código extra y una edición de fechas purga como cualquier otra.
4. **Admin**: las píldoras de país se mudan al bloque de ajustes generales y funcionan como
   pestañas (`data-pais`, `is-active`, `data-has-fechas`). Los paneles y las píldoras se renderizan
   completos en PHP (10 países); el JS sólo alterna visibilidad y puntos. Es el mismo patrón que ya
   usan los bloques de idioma, y elimina el marcado duplicado entre PHP y JS que había antes.

## Archivos

| Archivo | Cambio |
|---|---|
| `inc/hero-banners.php` | Docblock 1.1.0; saneado de `fechas`; funciones puras de fecha/ventana (validación `checkdate`, huso del país, instantes, próximo borde, avisos); filtro del front; TTL adaptativo del transient; píldoras + paneles por país en el bloque de ajustes; avisos en la fila; defaults de fila; textos de ayuda; cadenas localizadas. |
| `inc/geo.php` | Clave `tz` por país en `muyu_get_countries_data()` (huso del destino, reutilizable por cualquier módulo que resuelva fechas locales). |
| `inc/ai-translate.php` | `mu_ai_ambito_per_action()`: el tope de textos por acción pasa a ser del ámbito (un banner tiene 6 campos y el tope global de 5 lo bloqueaba); `per_action` en los defaults y en el saneado de ajustes. |
| `inc/ai-translate-admin.php` | Campo «Textos por acción» por ámbito (0 = automático = cantidad de campos) y el tope global retirado de la pantalla. |
| `js/admin-hero-banners.js` | Helpers de fechas (`.parseDdmmaaaa`, `rangoInvertido`, `fechaMalFormada` con bisiesto, `setPaisFechas`, puntos, sincronización de píldoras, placeholders, avisos); variantes de traducción (`variantesDeFila`, `mensajeVariante`, `pintarVariantes`, `aplicarVariante`); handlers; `refrescarFila()` extendido; exposición de las funciones puras. |
| `css/admin-hero-banners.css` | Píldora interactiva (`is-selectable`, `is-active`, punto `data-has-fechas`), grilla de fechas, avisos (panel y fila), título en flex, mobile. |
| `specs/010-fechas-banner-por-pais/` | `spec.md`, `plan.md`, `tasks.md`, `quickstart.md`, `harness/arnes-fechas-por-pais.php` (96 OK), `harness/fechas-js.js` (28 OK). |
| `specs/011-variantes-banners-ia/` | Feature de las variantes (personas) en el editor: `spec.md`, `tasks.md`, `quickstart.md`, `harness/variantes-js.js` (18 OK). |
| `specs/006-ai-translate-core/` | Arnés extendido (54 OK) y quickstart con el nuevo conteo. |
| `MIGRATION-GUIDE.md` | §2 (TTL de la caché de páginas: medir), §4 (árbol: `geo.php`, `hero-banners.php`), §6 (servicio de IA: tope por ámbito), §8 (deuda de verificación manual y decisión abierta de la purga en el borde). |

## Decisiones técnicas

- **Herencia campo por campo** (no por par): permite “cierra cuando cierra la general” con un solo
  dato, y es coherente con la sobrescritura dispersa de las traducciones.
- **Guardar vacío lo idéntico a la general**: evita que mover la fecha general deje a los países
  con una copia congelada, y hace que el punto de la píldora sea derivable del contenido.
- **Paneles ocultos, no `disabled`**: los inputs de países no tildados igual se envían y el saneado
  los descarta; no hace falta JS para habilitarlos ni hay riesgo de perder valores al alternar
  píldoras.
- **Sin `datepicker`**: `pattern="\d{8}"` como el resto del módulo; menos dependencias y ningún
  asset nuevo que encolar.
- **Aviso de rango invertido en el panel**: el front ya ignoraba un rango invertido; el aviso evita
  el diagnóstico tardío (“cargué fechas y no se ve”).
- **Sin `pattern` en los campos de fecha**: como los paneles se ocultan al cambiar de píldora, la
  validación nativa del navegador se vuelve una trampa (un campo oculto inválido bloquea el envío y
  no se puede enfocar). El aviso pasa a ser del panel (formato + rango) y el saneado del servidor
  sigue siendo la garantía. `maxlength="8"` e `inputmode="numeric"` se mantienen como guía.

## Riesgos y mitigación

| Riesgo | Mitigación |
|---|---|
| Un banner antiguo se comporta distinto | Toda la resolución cae a `inicio`/`fin` cuando no hay `fechas`; el arnés cubre el caso legacy. |
| Purga de caché excesiva al guardar | Hash Gate existente: sin diferencia real no hay `update_option` ni purga. |
| El panel queda inusable con 10 países | Sólo se ven las píldoras de países tildados (habitualmente 1-3) y un panel por vez. |
| Regresión en el JS del panel | `node --check`, arnés de funciones puras, arnés 008 (17 OK) y verificación manual en el quickstart. |

## Reversión

`git revert` del commit de la feature. En la base, las claves `fechas` quedan guardadas pero el
código anterior las ignora (el front vuelve a usar `inicio`/`fin` globales y el panel viejo no las
muestra). Sin migraciones ni limpieza pendiente. Purga de caché: la del guardado siguiente, o
`litespeed_purge_all` manual si se revierte el front.
