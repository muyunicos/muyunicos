# Quickstart: Verificar que el guide describe la realidad

**Feature**: 001-update-migration-guide | **Fecha**: 2026-10-02

Estos comandos prueban que `MIGRATION-GUIDE.md` describe el sistema que está
corriendo. Si alguno falla, el guide está mal: eso es exactamente lo que esta
feature previene.

Todos se ejecutan contra producción. No hay staging.

## Requisito previo: persistir cookies

LiteSpeed separa por variante con la cookie `_lscache_vary`. **Un `curl` sin
cookies siempre da `miss`** y parece una caché rota cuando no lo es. Todos los
comandos de caché usan un cookie jar.

---

## Escenario 1 — La caché responde en raíz y subdominio

**Prueba**: el catálogo se cachea por subdominio.

```bash
# La primera request genera la variante (miss normal), la segunda debe dar hit
curl -sI -c /tmp/cj-root.txt https://muyunicos.com/tienda/ | grep -i "x-litespeed-cache:"
curl -sI -b /tmp/cj-root.txt -c /tmp/cj-root.txt https://muyunicos.com/tienda/ | grep -i "x-litespeed-cache:"

curl -sI -c /tmp/cj-us.txt https://us.muyunicos.com/tienda/ | grep -i "x-litespeed-cache:"
curl -sI -b /tmp/cj-us.txt -c /tmp/cj-us.txt https://us.muyunicos.com/tienda/ | grep -i "x-litespeed-cache:"
```

**Esperado**: `miss` en la primera request de cada host, `hit` en la segunda.

| Resultado | Significado |
|---|---|
| `hit` en la segunda | Correcto |
| `miss` en la segunda | La caché de páginas no está guardando. Revisar el preajuste de LiteSpeed |
| Sin header `X-Litespeed-Cache` | LiteSpeed no está interviniendo en esa URL |

---

## Escenario 2 — La raíz y los subdominios sirven catálogos distintos

**Prueba**: el aislamiento funciona. Es el escenario que tiene consecuencias
comerciales directas si falla: productos físicos apareciendo en países que no los
venden.

```bash
# La raíz (Argentina) SÍ tiene productos físicos
curl -s https://muyunicos.com/tienda/ | grep -oc "outlet\|wii\|consola\|sticker"

# Un subdominio restringido NO debe tenerlos
curl -s https://us.muyunicos.com/tienda/ | grep -oc "outlet\|wii\|consola\|sticker"
```

**Esperado**: la raíz devuelve un número mayor que cero; el subdominio devuelve
cero.

Verificación cruzada por hash, más concluyente que el conteo:

```bash
curl -s https://muyunicos.com/tienda/     | md5sum
curl -s https://us.muyunicos.com/tienda/ | md5sum
curl -s https://es.muyunicos.com/tienda/ | md5sum
```

**Esperado**: tres hashes distintos. Si dos coinciden, hay colisión de caché
entre subdominios.

| Resultado | Significado |
|---|---|
| Hashes distintos | Aislamiento correcto |
| Dos hashes iguales | **Colisión de caché.** Los productos físicos se sirven cruzados. Revisar `_lscache_vary` antes de tocar el filtro `litespeed_vary` |

---

## Escenario 3 — El proveedor de CDN retirado no aparece en el guide

**Prueba**: ninguna regla vigente depende de un proveedor que no se usa.

```bash
grep -n -i "cloudflare\|quic" MIGRATION-GUIDE.md
```

**Esperado**: la única mención válida es la de QUIC.cloud en la línea que
documenta que su CDN está desactivada. No debe aparecer `cloudflare` como regla
aplicable.

| Resultado | Significado |
|---|---|
| Sin Cloudflare; QUIC.cloud solo como estado | Correcto |
| Cloudflare en sección normativa | Quedó una regla del proveedor retirado |

---

## Escenario 4 — El error de JavaScript no reaparece

**Prueba**: el JS Delay no está activo y no rompió el orden de dependencias.

```bash
# Abrir https://muyunicos.com en el navegador y abrir la consola
```

**Esperado**: cero ocurrencias de:

- `Uncaught ReferenceError: wp is not defined`
- `Cannot read properties of undefined (reading 'hooks')`
- `wp.jpI18nLoader.state is not set`

Estos tres son el mismo fallo. Si aparece cualquiera, el JS Delay se reactivó
o se cambió el preajuste.

**En consola puede verse (y es normal)**:

- `JQMIGRATE: Migrate is installed` → informativo
- `'setTimeout' handler took NNms` → violación de rendimiento de Google Ads
- Peticiones a `google-analytics.com` y `doubleclick.net` → analítica

---

## Escenario 5 — Los bots se bloquean antes de llegar a PHP

**Prueba**: el edge filtra, y el fast-exit del tema queda como segunda línea.

```bash
# Bot contra una URL inexistente en un subdominio
curl -sI -A "GPTBot/1.0" https://us.muyunicos.com/pt/outlet

# Humano en la MISMA URL
curl -sI -A "Mozilla/5.0 (Windows NT 10.0; Win64; x64)" https://us.muyunicos.com/pt/outlet
```

**Esperado**: el bot recibe `429` y el humano `302` hacia el catálogo.

| Señal | Significado |
|---|---|
| Bot: `429` + `Server: hcdn` | El edge bloquea. WordPress no se ejecuta |
| Bot: `404` + `X-MU-Bot-404: fast-exit` | El fast-exit del tema está actuando. El edge dejó de filtrar |
| Humano con la misma respuesta que el bot | **Problema**: se está bloqueando a usuarios reales |

Esa última fila es el riesgo real de este escenario: un filtrado demasiado
agresivo en el edge dejaría fuera a clientes.

---

## Escenario 6 — El módulo renombrado carga sin error fatal

**Prueba**: el rename no rompió el cargador de módulos.

```bash
php -l inc/cdn-cache-bypass.php
grep -n "cdn-cache-bypass" functions.php
```

**Esperado**: sin errores de sintaxis, y una línea en `functions.php` con el
nombre nuevo.

Después de desplegar, abrir el carrito: si el módulo no cargara, PHP abortaría
con un error fatal por `mu_load_module()` y no encontraría el archivo.

---

## Checklist de cierre

| # | Escenario | Cubre |
|---|---|---|
| 1 | Caché responde en raíz y subdominio | SC-008 |
| 2 | Catálogos distintos por subdominio | SC-003, SC-011 |
| 3 | Proveedor retirado ausente del guide | SC-001, SC-002 |
| 4 | Sin errores de JavaScript | SC-005 |
| 5 | Bots bloqueados, humanos no | SC-005 |
| 6 | Módulo renombrado carga | SC-003 |

Todos los escenarios son de lectura. Ninguno modifica el sistema.

**Referencia**: las entidades que estos comandos validan están definidas en
[data-model.md](./data-model.md). Los criterios de aceptación completos están en
[spec.md](./spec.md) y las decisiones técnicas en [research.md](./research.md).
