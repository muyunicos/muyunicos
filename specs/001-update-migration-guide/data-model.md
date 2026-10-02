# Phase 1 Data Model: Actualizar MIGRATION-GUIDE.md

**Feature**: 001-update-migration-guide | **Date**: 2026-10-02

Este proyecto no tiene base de datos propia ni esquema persistente. El "modelo"
de esta feature son las **entidades de configuración y documentación** que el guide
debe representar. No hay migraciones porque no hay almacenamiento que migrar.

---

## 1. Capa de caché

Dos capas con función distinta. Confundirlas lleva a diagnóstico erróneo.

| Capa | Qué almacena | Responsable | Cómo se verifica |
|---|---|---|---|
| Object cache | Datos (transients, opciones, índices) | LiteSpeed Memcached (LSMCD) | Presencia de `wp_using_ext_object_cache()` |
| Caché de páginas | HTML por subdominio | LiteSpeed Cache | `X-Litespeed-Cache: hit` en la segunda request |

**Regla**: el object cache no guarda páginas, y la caché de páginas no guarda
objetos. Un error de purga o de TTL en una capa no se propaga a la otra.

---

## 2. Perímetro de red

Configuración que vive fuera del repositorio, en el panel de hosting.

| Atributo | Valor actual | Dónde se cambia |
|---|---|---|
| CDN activa | Hostinger (`hcdn`) | Panel de Hostinger |
| CDN de QUIC.cloud | Desactivada (integración activa) | LiteSpeed Cache > CDN |
| TLS | 1.3 | Panel de Hostinger |
| Bloqueo geográfico | Lista de permitidos por país | Panel de Hostinger |
| Preajuste de LiteSpeed | Caché por defecto | LiteSpeed Cache > Cache |
| JS Delay | Desactivado | LiteSpeed Cache > Optimización |

**Validación**: un valor de esta tabla que cambie no requiere enmienda de la
constitución, sí actualización del guide §2.

---

## 3. Plugin acoplado

Plugin con el que el tema interactúa por hook, filtro o API. Solo estos se
documentan en el inventario.

| Plugin | Punto de integración | Verificado en |
|---|---|---|
| Rank Math | `rank_math_description`, `rank_math_canonical` | `inc/seo-hreflang.php:146,173` |
| Jetpack Search | `jetpack_search_instant_search_options` (p10, p15) | `inc/jetpack-search-integration.php:93,132` |
| Hostinger Tools | `get_option( "hostinger_tools" )` | `inc/coming-soon.php:56` |
| wpLingua | Clases `.wplng-*` y body class `mu-wplng-hide` | `inc/ui.php:39`, `css/`, `js/global-ui.js` |
| WooCommerce Price Based on Country | `class_exists()` como guarda | `inc/geo.php:395,438` |
| Nextend Social Login | `shortcode_exists()` y URLs `?loginSocial=` | `inc/checkout.php:372`, `inc/auth-modal.php:106` |
| LiteSpeed Cache | `litespeed_vary`, `litespeed_purge_all` | `inc/compat-litespeed.php:66`, `inc/navigation-chips.php:280` |

**Regla de exclusión (FR-008)**: un plugin instalado sin acoplamiento de código
**no** entra en esta entidad. Va a §3 del guide como pasarela o servicio
configurado. PayPal y Mercado Pago son el caso actual.

---

## 4. Incidente

Evento que originó una mitigación. Nace de un hecho, no de una hipótesis.

| Atributo | Regla |
|---|---|
| Fecha | ISO `YYYY-MM-DD`. Obligatoria |
| Síntoma | Lo que se observó, literal |
| Causa raíz | Mecanismo, no correlación |
| Mitigación | Qué se hizo, y si está activa hoy |
| Comando de verificación | `curl` ejecutable que prueba el estado actual |
| Aprendizaje | Qué hacer distinto la próxima vez |

**Regla**: un incidente sin comando de verificación vigente es una hipótesis.
Si el comando dependía de un proveedor retirado, se reemplaza o se marca no
aplicable con la razón.

---

## 5. Deuda técnica

Trabajo pendiente conocido. No es un descuido: es una decisión registrada.

| Atributo | Regla |
|---|---|
| Descripción | Qué falta y por qué importa |
| Origen | FR de la spec, o incidente de §7 |
| Criterio de cierre | Cómo se sabe que está hecho |

**Regla**: una mitigación recomendada que no esté configurada **debe** figurar
aquí, no en las secciones normativas. Documentarla no es reconocer un fallo: es
impedir que un agente la tome por una capacidad disponible.

---

## 6. Código externo

Fragmento que el sitio ejecuta y que no reside en el repositorio versionado.
Satisface el Principio X (v2.1.0) de la constitución.

| Atributo | Regla |
|---|---|
| Propósito | Qué hace, en una frase. Sin publicar el código |
| Ubicación | Dónde vive (plugin y nombre del fragmento) |
| Fuente | A dónde ir si hay que cambiarlo |
| Riesgo de colisión | Si declara entidades con el prefijo `mu_` del tema |

**Fragmentos a inventariar**:

| Propósito | Riesgo |
|---|---|
| Oculta del contenido los shortcodes no registrados | Closure sin nombre, no desactivable de forma selectiva |
| Calculadora de Stickers: shortcode con enqueue y datos para el JS | Declara `mu_sticker_calculator_shortcode()` sin guarda. Colisiona con el tema si el nombre se repite |
| Ajuste de la configuración del plugin de traducción | Declara `define()` y funciones sin guardas |

**Relación con el principio X**: este inventario es lo que convierte el código
externo en una excepción permitida en lugar de una prohibición incumplida. El
contenido del código **no** se publica: documentarlo es hacerlo auditable, no
incorporarlo al repositorio.

---

## 7. Artefacto compilado

Archivo generado a partir de una fuente externa y servido por el tema.

| Atributo | Valor actual |
|---|---|
| Origen | Repositorio de la Calculadora de Stickers |
| Comandos | `npm run build:js` y `npm run build:css` |
| Destino | `assets/js/calculadora_stickers.js` y `assets/css/calculadora_stickers.css` en el tema |
| Endpoint de guardado | `assets/guardar_datos.php`, protegido con `manage_options` |

**Regla**: el bundle servido y su fuente pueden divergir si alguien sube el
bundle sin recompilar. El guide debe indicar cuál manda — en este caso, la
fuente.

---

## Relaciones

```text
Perímetro de red ───┬── Cache de páginas ──┐
                   └── Object cache ───────┤
                                          ├── Producto aislable por subdominio
Incidente ────────── explica ─────────────┘

Plugin acoplado ── integra ──> Módulo en inc/
Código externo ─── NO se integra ──> declaran su relación en el inventario
Artefacto compilado ── se genera desde ──> Repositorio externo
Deuda técnica ─── deriva de ──> Incidente o FR
```

**Invariante crítica**: Cache de páginas + Perímetro de red determinan qué
catálogo sirve cada subdominio. Si esa relación se rompe, aparecen productos
físicos en países restringidos. Es el único punto del sistema donde un error
tiene consecuencias comerciales directas.

---

## Validación del modelo

| Regla | Cómo se verifica |
|---|---|
| Todo plugin acoplado tiene punto de integración verificable | `grep` del hook en `inc/` |
| Ningún plugin sin acoplamiento aparece en el inventario | Mismo `grep`, resultado vacío |
| Todo incidente tiene comando de verificación vigente | Ejecutar el `curl` del §7 correspondiente |
| Todo código externo tiene propósito y ubicación | Lectura del inventario §2 |
| La caché responde `hit` en raíz y subdominio | `quickstart.md`, escenario 1 |
| Los catálogos difieren entre raíz y subdominio | `quickstart.md`, escenario 2 |
