# Implementation Plan: Correcciones y mejoras de la interfaz en el móvil

**Branch**: `fix/mobile-ui` | **Date**: 2026-10-02 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/003-mobile-ui-fixes/spec.md`

## Summary

Cuatro correcciones de interfaz en el móvil, agrupadas porque comparten los
mismos archivos y el mismo punto de la pantalla angosta: el selector de país que no
abre al toque, el submenú de Mi Cuenta en blanco sobre blanco, el botón de WhatsApp
sin ningún estado de interacción, y las pantallas de la sección de cuenta sin
adaptación al diseño del sitio.

Enfoque técnico, por componente:

1. **Selector de país** — se separa la interacción de escritorio (hover) de la de
   táctil (toque), hoy mezcladas en un mismo manejador. Se reemplaza el estado
   visual basado en un atributo de estilo en línea por una clase, y se elimina el
   posicionamiento absoluto que compite con el menú hamburguesa.
2. **Submenú de Mi Cuenta** — corrección de contraste y de área táctil, alineado al
   mismo tratamiento que los submenús nativos ya corregidos. Sin JavaScript nuevo.
3. **Botón de contacto** — se le da nombre accesible y estados de reposo, foco y
   pulsación, con la invitación visual resuelta en CSS para que no dependa de que el
   puntero fino exista. Los colores literales pasan a variables.
4. **Pantallas de cuenta** — hoja de estilos nueva, cargada solo en páginas de la
   sección de cuenta, que hoy no tienen ninguna adaptación propia.

No cambia el comportamiento en escritorio, salvo el botón de contacto, que gana
estados que no tenía. Ningún cambio toca PHP de negocio, precios, pagos ni URLs.

## Technical Context

**Language/Version**: PHP 8.5.4 (confirmado contra `X-Powered-By` en producción el
2026-10-02), JavaScript ES5 sin framework, CSS con custom properties, Markdown

**Primary Dependencies**: WordPress, WooCommerce, GeneratePress 3.6.1 (tema parent,
solo lectura), WPLingua, LiteSpeed Cache 7.9. Sin dependencias nuevas.

**Storage**: No aplica. Ningún componente lee ni escribe datos. El único estado que
se agrega es efímero y vive en el DOM (clase de estado abierto y etiqueta de
producto), no en base de datos ni en sesión.

**Testing**: `php -l` para sintaxis PHP. No hay suite automatizada, ni CI, ni
composer, ni package.json, y la constitución IX prohíbe añadirlos. La verificación
de comportamiento es manual: un teléfono real por cada criterio, contra producción.

**Target Platform**: Navegador móvil (iOS Safari y Chrome Android) y escritorio, en
Linux tras el CDN de Hostinger. 10 subdominios de país. Producción es el único
entorno; no hay staging.

**Project Type**: Tema hijo de WordPress con arquitectura monolítica modular
(`functions.php` reducido a enqueue + cargador, lógica en `inc/`, assets en `css/`
y `js/`).

**Performance Goals**: Cero consultas SQL nuevas. Cero bytes nuevos en páginas fuera
de la sección de cuenta. La animación del botón de contacto no debe provocar
re-layout ni bloquear el hilo principal. El peso de las hojas de estilos existentes
se mantiene: se corrigen, no se duplican.

**Constraints**:
- La sección de cuenta **nunca** se sirve desde caché pública (principio IV). Ya
  funciona: `curl -sI` devuelve `Cache-Control: no-cache, no-store` y
  `X-LiteSpeed-Cache-Control: no-cache`. Este trabajo no lo altera.
- El comportamiento de escritorio de los cuatro componentes es correcto y se
  preserva (FR-017, FR-032).
- Los destinos de la lista de países se construyen en PHP y no se tocan (FR-006,
  FR-007). Cambiar de país debe seguir respetando subdominio y prefijo de idioma.
- La franja del encabezado en pantalla angosta es un espacio compartido: bandera,
  botón de menú e íconos. Cualquier corrección de apilado se verifica en conjunto.
- No se puede verificar nada automáticamente: la comprobación final la hace una
  persona con un teléfono, en el subdominio de Ecuador, antes de cerrar.

**Scale/Scope**: 3 archivos existentes a modificar (`css/components/header.css`,
`css/components/global-ui.css`, `js/global-ui.js`), 1 a crear (`css/account.css`),
2 a consultar (`inc/ui.php`, `inc/geo.php`, sin cambios), 1 enqueue a añadir en
`functions.php`, 34 requisitos funcionales y 15 criterios de éxito.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

| Principio | Gate | Estado | Evidencia |
|---|---|---|---|
| I. Modularidad | Lógica en `inc/`, CSS/JS en archivos | CUMPLE | No se agrega PHP de negocio. Un ajuste de UI menor a 50 líneas por componente va a los archivos existentes; la hoja de cuenta es un archivo propio porque cubre una sección completa y se carga condicionalmente. No se infla `functions.php`: solo una línea de enqueue |
| II. Carga condicional | Sin CSS/JS inline; condicionales explícitos | CUMPLE | El único enqueue nuevo es `mu_wc_is_account_page()`, un condicional explícito de contexto. `wp_add_inline_style/script` no se usa en ningún punto |
| III. Código seguro | Guardas, escapes | CUMPLE | No se agrega PHP. Si el enqueue toca `functions.php`, no declara funciones. Los atributos del botón de contacto se escapan en PHP con `esc_attr()` |
| IV. Corrección comercial | Servidor es la autoridad | CUMPLE | No toca precios, descuentos, disponibilidad ni pasarelas. El bypass de caché de la sección de cuenta no se modifica |
| V. SEO e i18n | canonical y hreflang invariantes | CUMPLE | No se crean rutas ni se modifica `seo-hreflang.php`. Los `href` de la lista de países se generan en `inc/geo.php` y quedan intactos. Ningún texto de las pantallas de cuenta se modifica (FR-034) |
| VI. Rendimiento y caché | Caché verificada | CUMPLE | Cero consultas nuevas. Los estilos nuevos solo se cargan en la sección de cuenta. Las páginas cacheadas siguen serviéndose desde caché: el cambio es de bytes de CSS, no de flujo de render. `quickstart.md` incluye la verificación de caché |
| VII. Observabilidad | Traza, sin PII | CUMPLE | No se agregan logs. El trabajo es de presentación y no toca datos de comprador |
| VIII. Sistema de diseño | Variables, BEM, foco visible, GPU, sin SVG inline | CUMPLE | Es la restricción que más pesa: los dos colores literales del botón de contacto (`#25d366`, `#339db7`) desaparecen. La animación se resuelve con clases CSS, nunca con `style.transform` inline. Todo elemento interactivo gana `:focus-visible`. Los override sobre GeneratePress llevan su comentario de motivo |
| IX. Verificación | Verificación reproducible | ⚠️ JUSTIFICADO | Ver abajo |
| X. Perímetro y plugins | El tema no reimplementa lo del plugin | CUMPLE | El marcado de la lista de países y de los formularios de cuenta lo genera WooCommerce; el tema solo lo adapta visualmente desde el hijo. No se edita el parent ni ningún plugin |

**Resultado del gate: PASS con 1 violación justificada.**

### Violación justificada

| Violación | Por qué se necesita | Alternativa más simple rechazada porque |
|---|---|---|
| Principio IX: el comportamiento en móvil no se puede verificar automáticamente | Un toque en una pantalla angosta no se reproduce con `curl`. Los criterios de contraste y área táctil exigen un dispositivo real o un emulador | Se evaluó verificar con `curl` el HTML servido. Se descarta porque comprobaría que las clases existen en el marcado, no que un dedo abre la lista de países. Es la misma razón por la que en `specs/002` el flujo de login quedó declarado como no verificado automáticamente |

**Re-evaluación post-diseño (Phase 1): PASS.** El diseño no introduce ninguna
violación nueva: la hoja de cuenta se carga condicionalmente (II), los estados del
botón de contacto se resuelven con clases CSS (VIII) y no hay PHP de negocio nuevo
(I, III).

## Project Structure

### Documentation (this feature)

```text
specs/003-mobile-ui-fixes/
├── plan.md              # Este archivo
├── spec.md              # Especificación (de /speckit-specify)
├── research.md          # Phase 0: decisiones técnicas y alternativas
├── data-model.md        # Phase 1: entidades de estado y transiciones
├── quickstart.md        # Phase 1: guía de validación manual
├── contracts/
│   └── ui-states.md     # Phase 1: contrato de estados y atributos accesibles
├── checklists/
│   └── requirements.md  # Checklist de calidad de la spec
└── tasks.md             # Phase 2 (NO creado por /speckit-plan)
```

### Source Code (repository root)

```text
generatepress-child/
├── functions.php                        # SE MODIFICA: 1 enqueue condicional
├── css/
│   ├── components/
│   │   ├── header.css                   # SE MODIFICA: selector de país + mu-sub-menu
│   │   └── global-ui.css                # SE MODIFICA: botón de contacto
│   └── account.css                      # SE CREA: pantallas de la sección de cuenta
├── js/
│   └── global-ui.js                     # SE MODIFICA: interacción táctil del selector
├── inc/
│   ├── ui.php                           # SE CONSULTA: markup del botón y del submenú
│   └── geo.php                          # SE CONSULTA: markup de la lista de países
└── MIGRATION-GUIDE.md                   # SE ACTUALIZA: registro de deuda §8
```

**Structure Decision**: estructura existente, sin módulos nuevos. Los tres
componentes del encabezado viven ya en `css/components/header.css`,
`css/components/global-ui.css` y `js/global-ui.js`; ampliarlos es lo que exige el
principio I (ajustes de UI agrupados, no un archivo por detalle). La única pieza
nueva es `css/account.css`, que se justifica porque cubre una sección completa
del sitio —no un ajuste puntual— y porque el archivo que existe
(`css/account-downloads.css`) está acotado a un endpoint y se carga en un solo
momento. Meter la sección de cuenta completa dentro de un archivo llamado
"downloads" sería peor que el costo de uno nuevo.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Principio IX: verificación de comportamiento móvil solo manual | Ningún comando del repositorio reproduce un toque en pantalla angosta ni mide contraste renderizado | Se evaluó `curl` sobre el HTML servido: verifica que las clases estén presentes, no que un dedo abra la lista. La verificación real la hace una persona con un teléfono en el subdominio de Ecuador, y queda registrada en `quickstart.md` como paso obligatorio, no opcional |

