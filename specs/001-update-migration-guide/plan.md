# Implementation Plan: Actualizar MIGRATION-GUIDE.md tras el cambio de CDN

**Branch**: `main` | **Date**: 2026-10-02 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/001-update-migration-guide/spec.md`

## Summary

`MIGRATION-GUIDE.md` describe una pila de infraestructura que ya no existe. El
proyecto migró de Cloudflare a la CDN de Hostinger, activó TLS 1.3, LiteSpeed
Memcached (LSMCD), bloqueo de tráfico por país en el edge y una integración de
QUIC.cloud con su CDN desactivada. Además, el preajuste de LiteSpeed cambió a
"Caché por defecto" el 2026-10-02, lo que desactivó el JS Delay y eliminó la
causa del incidente `wp.i18n is not defined`.

La constitución v2.1.0 ya es agnóstica del proveedor de CDN y apunta al guide §2
como fuente de verdad del perímetro de red. Hoy esa referencia apunta a
información que es falsa. Esta spec alinea el documento con la realidad
verificada, renombra el módulo de caché cuyo nombre ya no describe lo que hace,
e inventa el código que se ejecuta fuera del tema para que sea auditable.

Enfoque técnico: documentación como fuente de verdad, más un rename acotado de
un módulo PHP. Sin cambios de comportamiento en el sitio.

## Technical Context

**Language/Version**: PHP 8.3+ (el host reporta `X-Powered-By: PHP/8.5.4` en
respuesta; el guide declara 8.3.28 — discrepancia registrada en research.md),
JavaScript ES2018 sin framework, Markdown

**Primary Dependencies**: WordPress, WooCommerce 11.1.0, GeneratePress 3.6.1
(parent), LiteSpeed Cache 7.9.1, CDN de Hostinger, plugin Code Snippets 3.10.2

**Storage**: MariaDB vía WooCommerce con HPOS activo; object cache Memcached
(LSMCD) para datos; caché de páginas de LiteSpeed para HTML por subdominio

**Testing**: `php -l` para sintaxis. No hay suite de pruebas automatizadas, ni
integración continua, ni package.json, ni composer.json. La verificación es
manual y reproducible contra producción (Principio IX de la constitución).

**Target Platform**: Linux en Hostinger Plan Business, 2 núcleos, 3 GB RAM;
17 subdominios de país; sin entorno de staging — producción es el único entorno

**Project Type**: WordPress child theme con arquitectura monolítica modular;
documentación operativa como artefacto de primera clase

**Performance Goals**: la caché de páginas debe seguir respondiendo `hit` en raíz
y subdominios tras cada cambio; el catálogo de la raíz (con productos físicos) y
el de los subdominios (solo digitales) deben seguir sirviendo HTML distinto

**Constraints**: sin staging; despliegue manual por FTP; no combinar CSS/JS
(rompe la carga condicional por página); no activar JS Delay sin excluir antes
`wp-hooks`, `wp-i18n` y `a18n`; no tocar el filtro `litespeed_vary` sin medir
primero, porque romperlo reintroduce productos físicos cruzados entre países

**Scale/Scope**: 25 módulos PHP en `inc/`, 17 zonas de país, 33 plugins
conviviendo, 1 documento de 460 líneas que hay que actualizar en 8 puntos

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

| Principio | Gate | Estado | Evidencia |
|---|---|---|---|
| I. Modularidad | Todo módulo en `inc/`, nombre que describa su función | CUMPLE | El rename de `cloudflare-optimization.php` a un nombre agnóstico es precisamente el cumplimiento de este principio |
| II. Carga condicional | Sin inline CSS/JS, assets por página | CUMPLE | El rename no altera enqueues; el bypass de caché sigue emitting cabeceras estándar |
| III. Código seguro | Guardas, HPOS, nonces, escapes | CUMPLE | El rename conserva la guarda `if ( ! defined( 'ABSPATH' ) )` y la función queda con su `function_exists` |
| IV. Corrección comercial | Servidor es la autoridad | CUMPLE | No se toca lógica de precios ni checkout |
| V. SEO e i18n | canonical y hreflang invariantes | CUMPLE | No se toca `seo-hreflang.php` ni el enrutado por subdominio |
| VI. Rendimiento y caché | Caché verificada, purga condicionada | CUMPLE | quickstart.md incluye los `curl` que prueban caché e aislamiento |
| VII. Observabilidad | Traza `[MU-MODULO]`, sin PII | CUMPLE | Los incidentes se documentan con fecha, causa raíz y verificación |
| VIII. Sistema de diseño | Solo variables de `:root`, `mu_get_icon` | CUMPLE | No se toca CSS ni JS |
| IX. Verificación | Verificación reproducible antes de cerrar | ⚠️ JUSTIFICADO | Ver abajo |
| X. Perímetro y plugins | Código externo declarado | CUMPLE | El inventario de código externo satisface este principio |

**Resultado del gate: PASS con 1 violación justificada.**

**Re-evaluación post-diseño (Phase 1): PASS.** El diseño no introduce tensión
adicional con ningún principio. El inventario de código externo cubre el
requisito de declaración del principio X.

## Project Structure

### Documentation (this feature)

```text
specs/001-update-migration-guide/
├── plan.md              # Este archivo
├── spec.md              # Especificación (ya existe, 362 líneas)
├── research.md          # Phase 0: acoplamientos verificados + correcciones
├── data-model.md        # Phase 1: entidades de configuración
├── quickstart.md        # Phase 1: verificación reproducible
├── contracts/           # OMITIDO — ver nota abajo
├── checklists/
│   └── requirements.md  # 16/16 items
└── tasks.md             # Phase 2 (/speckit-tasks — no lo crea /speckit-plan)
```

**Nota sobre `contracts/`**: se omite deliberadamente. El proyecto no expone
interfaces externas: es un tema hijo de WordPress sin API pública, sin CLI y sin
endpoints REST propios (`register_rest_route` no aparece en el código). El
"contrato" de esta feature es el propio documento Markdown, y su validación son
los comandos de `quickstart.md`.

### Source Code (repository root)

```text
generatepress-child/
├── functions.php                    # Enqueue central + mu_load_module()
├── style.css                        # Tokens CSS en :root + cabecera del tema
├── inc/                             # 25 módulos PHP
│   ├── compat-litespeed.php         # Cache: vary, nocache_404, fast-exit bots
│   ├── cloudflare-optimization.php  # RENAME → nombre agnóstico (FR-009/010)
│   ├── seo-hreflang.php             # canonical + hreflang por subdominio
│   ├── jetpack-search-integration.php
│   ├── geo.php                      # Multi-país, WCPBC
│   ├── coming-soon.php              # Lee option de Hostinger Tools
│   └── ui.php                       # body_class para wpLingua
├── css/                             # Modular, con components/ transversal
├── js/                              # Modular, IIFE + 'use strict'
└── templates/                       # PHP standalone

MIGRATION-GUIDE.md                   # §2, §4 y §7 se actualizan
```

**Structure Decision**: estructura existente, sin reorganización. El rename
toca un archivo y su referencia en `functions.php`; el resto del trabajo es
documental. No se crean directorios nuevos.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Principio IX: la spec mezcla documentación y cambio de código en un solo commit | El rename de `cloudflare-optimization.php` es un archivo PHP en producción, y el guide debe reflejar el nombre nuevo; separarlos dejaría el guide describiendo un nombre inexistente | Se evaluó separar en dos commits. El mantenedor eligió commit único de forma consciente: el guide es documentación sin efecto en runtime, así que puede revertirse solo si el código falla |
| Renombrar un módulo ya desplegado | El nombre `cloudflare-optimization` describe un proveedor que no se usa; la función emite cabeceras HTTP estándar. Mantener el nombre contradice el Principio I y hace que un agente busque una integración inexistente | Se evaluó mantener el nombre y solo corregir el comentario. Se descartó porque el nombre es la señal que un agente lee primero para entender qué hace un módulo |
