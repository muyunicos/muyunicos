# Implementation Plan: Blindar la URL de login social y eliminar los SVG inline

**Branch**: `main` | **Date**: 2026-10-02 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/002-fix-broken-social-login/spec.md`

**Revisión**: v2. La v1 partía de una premisa que resultó falsa; ver
`research.md` §1.

## Summary

El login social **funciona hoy** y nunca estuvo roto: WPS Hide Login hookea el
filtro `site_url` de WordPress, de modo que `site_url( '/wp-login.php' )` devuelve
la ruta vigente. La v1 de esta feature proponía "arreglar" cuatro enlaces que ya
funcionaban.

Queda trabajo por hacer, en dos partes. La primera es **blindar** la construcción
de la URL para que no dependa de que el plugin reescriba: un punto único que
resuelve la ruta por API. El comportamiento es idéntico al actual; lo que cambia
es que si el plugin se desactiva o cambia, los enlaces no se rompen en silencio.
La segunda es la limpieza de los cuatro iconos que el modal tiene pegados en el
HTML, en las líneas contiguas a las que hay que tocar igual.

Durante la verificación apareció un segundo bloqueo, de otra naturaleza: el WAF
del edge bloqueaba el callback de OAuth con 403. Se resolvió fuera de código,
ajustando el nivel de seguridad de la CDN de Alto a Medio, y queda documentado
en `research.md` §6.

Enfoque técnico: una función helper que reemplaza cuatro construcciones de URL
duplicadas, más una limpieza de iconos. Sin cambios de comportamiento observable:
el login funciona antes y después.

## Technical Context

**Language/Version**: PHP 8.5.4 (confirmado contra `X-Powered-By` el
2026-10-02), WordPress con WooCommerce, JavaScript sin framework, Markdown

**Primary Dependencies**: WordPress, WooCommerce, Nextend Social Login 3.1.26
(Google y Facebook), WPS Hide Login 1.9.19 (slug `/login/`), GeneratePress 3.6.1

**Storage**: No aplica. El fix no lee ni escribe datos.

**Testing**: `php -l` para sintaxis. No hay suite automatizada, ni CI, ni staging.
La verificación del flujo de autenticación es manual y requiere completar un
permisos real de un proveedor externo.

**Target Platform**: Linux en Hostinger, 17 subdominios de país. Producción es el
único entorno.

**Project Type**: WordPress child theme con arquitectura monolítica modular

**Performance Goals**: El fix no debe añadir consultas ni modificar el HTML
servido más allá del `href` de los cuatro enlaces. La URL de login se resuelve
una vez por renderizado, no por botón.

**Constraints**: El formato de la URL de redirección que el proveedor de login
social ya acepta **no puede cambiar**. El proveedor recibe como destino la misma
ruta de login con el parámetro del proveedor dentro; si ese formato cambia, el
proveedor puede rechazarlo como no válido.

**Scope/Scope**: 2 archivos PHP a modificar (`inc/auth-modal.php`,
`inc/checkout.php`), 1 a consultar (`inc/icons.php`), 4 URLs a reconstruir, 4
iconos a mover.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

| Principio | Gate | Estado | Evidencia |
|---|---|---|---|
| I. Modularidad | Función en `inc/`, nombre que la describa | CUMPLE | El helper vive en `inc/auth-modal.php`, ya dueño de la autenticación del frontend. Un módulo de una función sería fragmentación |
| II. Carga condicional | Sin inline CSS/JS | CUMPLE | No toca enqueues ni assets |
| III. Código seguro | Guardas, escapes | CUMPLE | El helper va con `if ( ! function_exists() )`; las URLs siguen con `esc_url()` |
| IV. Corrección comercial | Servidor es la autoridad | CUMPLE | El login es previas; no toca precios ni pago |
| V. SEO e i18n | canonical y hreflang invariantes | CUMPLE | No toca `seo-hreflang.php`. La redirección preserva host y prefijo de idioma |
| VI. Rendimiento y caché | Caché verificada | CUMPLE | Las páginas con el modal siguen cacheando; quickstart.md lo verifica |
| VII. Observabilidad | Traza, sin PII | CUMPLE | No se agregan logs con datos personales |
| VIII. Sistema de diseño | Iconos del repositorio, sin SVG inline | **SE CUMPLE** | Es el objetivo explícito de US2 |
| IX. Verificación | Verificación reproducible | ⚠️ JUSTIFICADO | Ver abajo |
| X. Perímetro y plugins | El tema no reimplementa lo del plugin | **SE CUMPLE** | El fix delega la resolución de la ruta al plugin en vez de hardcodearla |

**Resultado del gate: PASS con 1 violación justificada.**

**Re-evaluación post-diseño (Phase 1): PASS.**

## Project Structure

### Documentation (this feature)

```text
specs/002-fix-broken-social-login/
├── plan.md              # Este archivo (v2)
├── spec.md              # Especificación
├── research.md          # Phase 0, con la corrección de premisa
├── data-model.md        # Phase 1
├── quickstart.md        # Phase 1
├── contracts/           # OMITIDO — ver nota
├── checklists/
│   └── requirements.md
└── tasks.md             # Phase 2
```

**Nota sobre `contracts/`**: se omite. El fix no expone interfaces nuevas: no
hay API pública, ni endpoints REST propios, ni CLI. El contrato relevante es el
formato de la URL que el proveedor acepta, documentado en `research.md` §3.

### Source Code (repository root)

```text
generatepress-child/
├── functions.php                    # mu_load_module() — sin cambios
├── inc/
│   ├── auth-modal.php               # SE MODIFICA: helper + 4 URLs + 4 iconos
│   ├── checkout.php                 # SE MODIFICA: 2 URLs
│   └── icons.php                    # SE CONSULTA: agregar chevron y google
└── MIGRATION-GUIDE.md               # SE ACTUALIZA: incidente + WAF
```

**Structure Decision**: estructura existente. El helper no crea un módulo nuevo
porque `auth-modal.php` ya es el dueño de la autenticación del frontend.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Principio IX: el flujo de autenticación no se verifica automáticamente | Completar un permiso real de Google o Facebook requiere la interacción de una persona, y no hay suite de pruebas ni staging | Se evaluó un test con `curl` que verifique la URL. Se descartó porque solo comprobaría que la ruta responde, no que el proveedor complete el flujo |
