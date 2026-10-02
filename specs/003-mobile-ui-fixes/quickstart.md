# Quickstart — Validación de `003-mobile-ui-fixes`

**Feature**: `003-mobile-ui-fixes` | **Fecha**: 2026-10-02 | **Plan**: [plan.md](./plan.md)

## Antes de empezar: qué se puede verificar y qué no

Este proyecto **no tiene suite de pruebas, ni integración continua, ni staging**.
Producción es el único entorno. La consecuencia práctica es que esta guía tiene dos
mitades y conviene no mezclarlas:

| Tipo | Qué es | Cuándo |
|---|---|---|
| **Automatizable** | Sintaxis PHP, presencia de clases, cabeceras de caché, ausencia de colores literales, archivos que no deben cambiar | Antes de commit, en local |
| **Manual, obligatorio** | Un dedo en una pantalla angosta, contraste real, área táctil, compra completa | Antes de dar la feature por terminada |

**La mitad manual no es opcional.** El principio IX exige que ninguna funcionalidad
se dé por terminada sin verificación reproducible, y este es el único mecanismo
disponible. Si la mitad manual no se ejecuta, la feature **no está verificada**, y
eso se declara explícitamente en el pull request; nunca se presenta como
verificada.

---

## Fase A — Verificaciones automatizables

Ejecutar desde la raíz del repositorio.

### A.1 — Sintaxis PHP

Los dos archivos PHP que se tocan son `inc/ui.php` (un atributo) y `functions.php`
(un enqueue).

```bash
php -l inc/ui.php
php -l functions.php
```

**Esperado**: `No syntax errors detected` en ambos.

> Nota de versión: el `php` local es **8.5.9** y producción es **8.5.4**
> (`research.md` §5). `php -l` verifica sintaxis, no comportamiento en el servidor.

### A.2 — Sin colores literales donde existe variable

```bash
grep -nE '#[0-9a-fA-F]{3,6}' css/components/global-ui.css
```

**Esperado**: los dos literales que existían (`#25d366` y `#339db7`) **ya no**
aparecen en el bloque del botón de contacto.

Comprobación complementaria de que las variables nuevas están declaradas:

```bash
grep -n 'mu-wa' style.css
```

**Esperado**: aparecen con su comentario justificativo.

### A.3 — Sin animación por estilo en línea

```bash
grep -rn 'style\.transform\|style\.opacity' js/ inc/
```

**Esperado**: sin resultados. El principio VIII lo prohíbe de forma expresa.

### A.4 — El tema base no se toca

```bash
git diff --name-only main...HEAD
```

**Esperado**: solo archivos bajo el tema hijo. **Si aparece algo bajo
`Tema-GeneratePress/` o cualquier plugin, el gate falla** (principio X y FR-033).

Lista esperada de archivos modificados:

```text
css/components/header.css
css/components/global-ui.css
css/account.css          (nuevo)
js/global-ui.js
functions.php
inc/ui.php
style.css
MIGRATION-GUIDE.md
```

### A.5 — La hoja de cuenta se carga solo en la sección

```bash
curl -s https://ec.muyunicos.com/tienda/ | grep -c 'account.css'
```

**Esperado**: `0`. La hoja no aparece fuera de la sección de cuenta.

```bash
curl -s https://ec.muyunicos.com/mi-cuenta/ | grep -c 'account.css'
```

**Esperado**: `1` o más. Aparece en la sección.

### A.6 — La sección de cuenta sigue sin caché pública

```bash
curl -sI https://ec.muyunicos.com/mi-cuenta/ | grep -iE 'cache-control|x-litespeed'
```

**Esperado**: `no-cache, no-store` y `X-LiteSpeed-Cache-Control: no-cache`. Si
aparece una cabecera de caché pública, el principio IV está violado y la feature
**no se despliega**.

### A.7 — Las URLs y el idioma no cambian

```bash
curl -sI https://ec.muyunicos.com/mi-cuenta/ | grep -i 'canonical\|hreflang'
curl -sI https://br.muyunicos.com/mi-cuenta/ | grep -i 'canonical\|hreflang'
```

**Esperado**: los `canonical` y `hreflang` son idénticos a los de antes del cambio.
Si difieren, se rompió el principio V.

### A.8 — El marcado de la lista de países no cambió

```bash
curl -s https://ec.muyunicos.com/ | grep -o 'aria-haspopup="true"\|aria-expanded="false"\|role="button"'
```

**Esperado**: los tres aparecen. Son parte del contrato C-1.6 y no deben tocarse
---

## Fase B — Verificación manual obligatoria

Requiere: un teléfono real con navegador, una sesión iniciada en la tienda, un
medidor de contraste (o el eyedropper de una app de diseño) y el subdominio de
Ecuador abierto.

### B.1 — Selector de país (P1, el defecto reportado)

En `https://ec.muyunicos.com/`, en el teléfono:

1. Toca la bandera. **La lista de países aparece.** Un toque, sin mantener el dedo.
2. Repite el paso 1 diez veces. **Las diez veces abre.** Este es el criterio que
   fallaba antes.
3. Toca un país distinto. **Navega al subdominio de ese país**, en la misma página.
4. Toca fuera de la lista. **Se cierra.**
5. Abre el menú hamburguesa, después la lista de países, y al revés. **Ninguno tapa
   al otro** y los dos se pueden usar.
6. Gira el teléfono. **El panel no queda flotando** ni a medio camino.
7. En un iPhone, comprueba que el botón de menú hamburguesa sigue siendo pulsable
   en su posición habitual.

**Cubre**: FR-001 a FR-009, SC-001, SC-002, SC-013.

### B.2 — Submenú de Mi Cuenta (P2)

Con sesión iniciada, en el teléfono:

1. Toca el ícono de Mi Cuenta. **El submenú se abre y todo su texto se lee.**
2. Mide el contraste del texto contra su fondo. **Debe dar 4.5:1 o más.**
3. Compara visualmente con un submenú nativo del menú principal. **El mismo
   tratamiento**, no una excepción.
4. Toca cada enlace. **Cada uno responde al primer toque.** Ninguno exige repetir.
5. Toca fuera. **Se cierra.**
6. Sal de la sesión y toca el ícono. **Navega a Mi Cuenta** y no aparece ningún
   panel en blanco.
7. Gira el teléfono a horizontal. **El panel no queda flotando** a medio camino.

**Cubre**: FR-010 a FR-017, SC-003, SC-004, SC-005, SC-006.

### B.3 — Pantallas de cuenta (P3)

Con sesión iniciada, en el teléfono, en `https://ec.muyunicos.com/mi-cuenta/` y
`https://ec.muyunicos.com/mi-cuenta/edit-account/`:

1. Recorre Mi Cuenta. **Ningún elemento se sale de la pantalla** ni obliga a
   desplazar de lado a lado.
2. Recorre la navegación de la sección. **Los 6 accesos son alcanzables con el
   dedo** y legibles.
3. Abre Detalles de la cuenta. **El formulario se lee, se completa y se guarda.**
4. Edita el email y el teléfono, guarda, y comprueba que el cambio persiste al
   recargar.
5. Repite el recorrido con la pantalla en **320 px de ancho**. **Nada desborda.**
6. Repite en 375, 390, 414 y 430 px.
7. Sal de la sesión. **La pantalla de acceso tiene el mismo cuidado** que las
   pantallas de usuario autenticado.

**Cubre**: FR-026 a FR-031, SC-007, SC-010.

### B.4 — Botón de contacto (P4)

En el teléfono:

1. Mira el botón. **Dice qué es** y se ve pulsable, no es un círculo plano.
2. Acércate con el dedo y púlsalo. **Cambia de estado**, y al pulsarlo se ve que
   fue reconocido.
3. En 320 px de ancho, comprueba que **no tapa el carrito, ni ningún botón, ni la
   barra de direcciones**.
4. En un iPhone, comprueba que queda **por encima de la barra de direcciones**.
5. Activa "reducir movimiento" en los ajustes del sistema y recarga. **No hay
   animación continua ni repetida**; los estados de reposo y pulsación siguen
   funcionando.
6. Púlsalo. **Se abre WhatsApp con el mismo número y el mismo mensaje** de siempre.
7. Desplaza la página mientras aparece el pulso. **La página responde con
   fluidez.**

**Cubre**: FR-018 a FR-025, SC-008, SC-009.

### B.5 — Accesibilidad por teclado

Con un teclado físico o con un lector de pantalla:

1. Recorre la página con `Tab`. **Todos los controles tienen foco visible.**
2. En la bandera, pulsa `Enter`, `Espacio` y `Escape`. **Abre, alterna y cierra**, y
   el foco vuelve al control.
3. En el botón de WhatsApp, verifica que el lector **anuncia la función**, no el
   nombre del archivo de imagen.

**Cubre**: FR-005, FR-022, SC-006.

### B.6 — No-regresión en escritorio (obligatorio)

A **1024 px o más**:

1. La lista de países abre al pasar el puntero. **Correcto.**
2. El submenú de Mi Cuenta tiene fondo azul y texto blanco. **Sin cambios.**
3. El botón de contacto tiene sus estados nuevos. **Único cambio deliberado.**
4. El carrito, el buscador y el menú funcionan. **Sin cambios.**

**Cubre**: FR-017, FR-032, SC-014.

### B.7 — Compra completa de punta a punta (obligatorio)

En el teléfono, desde la página de producto hasta la confirmación del pedido:
agregar al carrito → carrito → checkout → pagar con el método de prueba → pantalla
de confirmación.

**Esperado**: el flujo completo funciona sin cambios.

**Cubre**: SC-015. Este paso se hace porque la sección de cuenta y el checkout
comparten contexto de sesión, y un error de estilos o de caché en uno puede aparecer
en el otro.

---

## Fase C — Cierre

Si todo lo anterior pasó, la feature está verificada y se puede cerrar el pull
request dejando registrado:

- La salida de `php -l` de los dos archivos PHP tocados.
- La salida de los `curl` de A.5, A.6 y A.7.
- El resultado de los pasos manuales B.1 a B.7, con los anchos probados.
- La actualización de `MIGRATION-GUIDE.md`: §4 si cambió el enqueue, §5 con las
  variables nuevas, §8 con la excepción de color y con la nota de que la
  verificación móvil sigue siendo manual.

**Si algún paso manual falla**: la feature no se da por terminada. Se corrige, se
vuelve a ejecutar **el paso que falló y los que dependen de él**, y se vuelve a
registrar.
(`inc/geo.php` no se modifica en esta feature).