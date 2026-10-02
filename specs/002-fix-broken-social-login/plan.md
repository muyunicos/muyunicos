# Implementation Plan: Restaurar el login social y eliminar los SVG inline

**Branch**: `main` | **Date**: 2026-10-02 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/002-fix-broken-social-login/spec.md`

## Summary

Cuatro enlaces de login social apuntan a `wp-login.php`, que WPS Hide Login
devuelve 404. El tema construye la URL a mano en lugar de dejar que WordPress
resuelva la ruta de login, que el plugin modifica por sí solo.

El fix introduce un único punto de construcción de la URL de login social en el
tema, que resuelve la ruta mediante la API de WordPress y preserva el subdominio
y la ruta de origen. En el mismo trabajo se mueven al repositorio central de
iconos los cuatro iconos que el modal de autenticación tiene pegados en el HTML,
porque están en las líneas contiguas a las que hay que tocar.

Enfoque técnico: una función helper del tema que reemplaza cuatro construcciones
de URL duplicadas, más una limpieza de iconos en el mismo archivo. Sin cambios de
comportamiento más allá de restaurar el login.

## Technical Context

**Language/Version**: PHP 8.5.4 (confirmado contra `X-Powered-By` en producción
el 2026-10-02), WordPress con WooCommerce, JavaScript sin framework, Markdown

**Primary Dependencies**: WordPress, WooCommerce, Nextend Social Login 3.1.26
(Google y Facebook), WPS Hide Login 1.9.19 (slug configurado: `/login/`),
GeneratePress 3.6.1 (parent)

**Storage**: No aplica. El fix no lee ni escribe datos; solo construye URLs y
resuelve iconos ya versionados.

**Testing**: `php -l` para sintaxis. No hay suite automatizada, ni CI, ni staging.
La verificación del flujo de autenticación es manual y requiere completar un
permisos real de un proveedor externo.

**Target Platform**: Linux en Hostinger, 17 subdominios de país. Producción es el
único entorno.

**Project Type**: WordPress child theme con arquitectura monolítica modular

**Performance Goals**: El fix no debe añadir consultas ni modificar el HTML servido
más allá del `href` de los cuatro enlaces. La URL de login se resuelve una vez
por renderizado, no por botón.

**Constraints**: El formato de la URL de redirección que el proveedor de login
social ya acepta **no puede cambiar**. El proveedor recibe como destino la misma
ruta de login con el parámetro del proveedor dentro; si ese formato cambia, el
proveedor puede rechazarlo como no válido. El fix reproduce el formato vigente.

**Scope/Scope**: 2 archivos PHP a modificar (`inc/auth-modal.php`,
`inc/checkout.php`), 1 archivo a consultar (`inc/icons.php`), 4 URLs que
reconstruir, 4 iconos a mover al repositorio.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

| Principio | Gate | Estado | Evidencia |
|---|---|---|---|
| I. Modularidad | Función en `inc/`, nombre que describa su función | CUMPLE | El helper vive en `inc/auth-modal.php`, que ya es el dueño de la autenticación del frontend. No se crea un módulo nuevo para 20 líneas |
| II. Carga condicional | Sin inline CSS/JS | CUMPLE | El cambio no toca enqueues ni assets |
| III. Código seguro | Guardas, escapes, nonces | CUMPLE | El helper va con `if ( ! function_exists() )`; las URLs siguen con `esc_url()`; no se introducen inputs nuevos |
| IV. Corrección comercial | Servidor es la autoridad | CUMPLE | El login es previas; no toca precios ni checkout de pago |
| V. SEO e i18n | canonical y hreflang invariantes | CUMPLE | No se toca `seo-hreflang.php`. La redirección preserva host y prefijo de idioma, lo que protege el invariante |
| VI. Rendimiento y caché | Caché verificada | CUMPLE | Las páginas con el modal se cachean; el `href` cambia pero el HTML sigue siendo estable. quickstart.md incluye la verificación |
| VII. Observabilidad | Traza, sin PII | CUMPLE | No se agregan logs con datos personales. La URL de redirección ya existía |
| VIII. Sistema de diseño | Iconos del repositorio, sin SVG inline | **SE CUMPLE** | Es el objetivo explícito de US2: mover los 4 SVG del modal a `inc/icons.php` |
| IX. Verificación | Verificación reproducible | ⚠️ JUSTIFICADO | Ver abajo |
| X. Perímetro y plugins | El tema no reimplementa lo del plugin | **SE CUMPLE** | El fix hace lo contrario de violarlo: delega la ruta al plugin en vez de hardcodearla |

**Resultado del gate: PASS con 1 violación justificada.**

**Re-evaluación post-diseño (Phase 1): PASS.** El diseño no introduce tensión
adicional con ningún principio.

## Project Structure

### Documentation (this feature)

```text
specs/002-fix-broken-social-login/
├── plan.md              # Este archivo
├── spec.md              # Especificación (ya existe, 200 líneas)
├── research.md          # Phase 0
├── data-model.md        # Phase 1
├── quickstart.md        # Phase 1
├── contracts/           # OMITIDO — ver nota
├── checklists/
│   └── requirements.md  # 16/16 items
└── tasks.md             # Phase 2 (/speckit-tasks)
```

**Nota sobre `contracts/`**: se omite. El fix no expone interfaces nuevas al
mundo exterior: no hay API pública, ni endpoints REST propios, ni CLI. El
"contrato" es el formato de la URL que el proveedor de login social acepta, y
está documentado en `research.md` con evidencia de producción.

### Source Code (repository root)

```text
generatepress-child/
├── functions.php                    # mu_load_module() — sin cambios
├── inc/
│   ├── auth-modal.php               # SE MODIFICA: helper + 2 URLs + 4 iconos
│   ├── checkout.php                 # SE MODIFICA: 2 URLs
│   └── icons.php                    # SE CONSULTA: agregar 3 iconos faltantes
└── MIGRATION-GUIDE.md               # SE ACTUALIZA: registrar el incidente
```

**Structure Decision**: estructura existente, sin reorganización. El helper no
crea un módulo nuevo porque `auth-modal.php` ya es el dueño de la autenticación
del frontend y el checkout ya consume esos enlaces. Un módulo `social-login.php`
de 20 líneas sería fragmentación sin benefit, en contra del principio I.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Principio IX: el flujo de autenticación no se puede verificar automáticamente | Completar un permiso real de Google o Facebook requiere la interacción de una persona, y no hay suite de pruebas ni staging | Se evaluó escribir un test que verifique la URL con `curl`. Se descartó porque solo comprobaría que la ruta responde, no que el proveedor complete el flujo: el bug actual también "responde" en la ruta correcta cuando se construye bien |
