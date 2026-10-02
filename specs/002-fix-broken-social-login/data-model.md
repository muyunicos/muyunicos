# Phase 1 Data Model: Restaurar el login social y eliminar los SVG inline

**Feature**: 002-fix-broken-social-login | **Date**: 2026-10-02

Este feature no introduce almacenamiento. El "modelo" son las entidades de
comportamiento que el fix debe respetar: qué forma tiene una URL válida de login
social y qué reglas debe cumplir un icono reutilizable.

---

## 1. URL de login social

Enlace de autenticación con un proveedor externo. Es la entidad central del fix.

| Atributo | Tipo | Regla |
|---|---|---|
| Proveedor | `google` \| `facebook` | Solo los dos configurados en el panel del plugin |
| Ruta de login | string | Resuelta por la API de WordPress, nunca escrita a mano |
| Destino de redirección | URL absoluta | Debe preservar host y ruta de origen |
| Formato | fijo | `RUTA?loginSocial=PROVEEDOR&redirect=DESTINO`, en ese orden |

### Reglas de validación

- **Ruta**: no puede contener el nombre del archivo de login por defecto de
  WordPress escrito a mano. Se resuelve en runtime.
- **Formato**: el orden de los parámetros es parte del contrato con el proveedor.
  Cambiarlo cambia la URL de redirección OAuth y el proveedor puede rechazarla.
- **Host**: debe ser el de la petición actual. Un usuario que entra desde un
  subdominio de otro país debe volver a ese subdominio.
- **Escapado**: la URL completa se escapa al imprimir. Los componentes
  individuales también se escapan al construirse.

### Estados

```
              ┌─────────────────┐
              │  URL construida  │
              └────────┬────────┘
                       │
        ┌──────────────┼──────────────┐
        ▼              ▼              ▼
  ruta vigente    ruta por        ruta no
  (/login/)        defecto         encontrada
  (plugin activo) (plugin off)    (error)
        │              │              │
        ▼              ▼              ▼
   proveedor        proveedor     fallback
   acepta          acepta         a la URL
                                         del modal
```

**Por qué no hay estado de error real**: si la ruta no se puede resolver, el
comportamiento correcto es no renderizar el enlace social, no renderizar uno
roto. El modal tiene su propio formulario de usuario y contraseña como camino
alternativo.

---

## 2. Destino de redirección

| Contexto | Origen del destino | Al volver |
|---|---|---|
| Modal de autenticación | Página desde la que se abrió el modal | Esa misma página, con su subdominio y su prefijo de idioma |
| Checkout | La página de checkout de WooCommerce | El checkout, para continuar la compra |

**Regla**: el destino es un parámetro del punto de construcción, no un valor fijo.
Los dos contextos coexisten en el mismo renderizado en algunos casos, y un
destino fijo rompería uno de los dos.

---

## 3. Icono reutilizable

SVG versionado en el repositorio central de iconos del tema.

| Atributo | Regla |
|---|---|
| Nombre | Identificador único en el repositorio |
| Markup | SVG completo, con sus dimensiones y su viewBox |
| Accesibilidad | `aria-hidden="true"` si es decorativo; etiqueta visible si acompaña texto |

### Reglas

- Un icono que aparece en más de un lugar vive **una sola vez** en el repositorio.
- Si no hay equivalente, **se agrega**; no se reemplaza por uno parecido.
- La apariencia se conserva exactamente. El cambio es de origen, no de diseño.
- Los fallbacks dentro de una comprobación de existencia de la función son
  deliberados y están exentos.

---

## 4. Proveedor de login social

Servicio externo de autenticación.

| Atributo | Valor |
|---|---|
| Identificador | `google` o `facebook` |
| Nombre visible | Texto del botón |
| Formato de URL aceptado | `RUTA?loginSocial=ID&redirect=DESTINO` |
| Tamaño de ventana emergente | Configurado en el marcado del botón (no se modifica) |

**Regla de contrato**: el formato de la URL es la parte más frágil del sistema.
No se toca sin evidencia de que el proveedor acepta el formato nuevo.

---

## Relaciones

```text
Proveedor de login social ── define ──> Formato de URL (contrato)
                                          │
Punto de construcción ── produce ──────────┘
       │
       ├── usa ──> Ruta resuelta por la API de WordPress
       ├── usa ──> Destino de redirección (modal | checkout)
       └── usa ──> Identificador del proveedor
                                          │
Destino de redirección ── preserva ──> Host de la petición
                                  └── Ruta y prefijo de idioma

Modal de autenticación ── consume ──> Punto de construcción
Checkout ───────────────── consume ──> Punto de construcción

Icono reutilizable ── alimenta ──> Modal de autenticación
                                 └── Checkout
```

**Invariante crítica**: los cuatro enlaces de los dos contextos deben salir del
mismo punto de construcción. Si uno se reconstruye a mano, el bug vuelve.

---

## Validación del modelo

| Regla | Cómo se verifica |
|---|---|
| Cero URL con la ruta de login escrita a mano | Búsqueda de texto en el código del tema |
| Los 4 enlaces salen del mismo punto de construcción | Búsqueda del nombre del helper: debe aparecer 4 veces como consumidor y 1 como definición |
| El host de la URL es el de la petición | Abrir la home en un subdominio y leer el `href` del botón |
| Cero SVG en el HTML del modal | Búsqueda de texto |
| Los iconos se ven iguales | Comparación visual antes y después |
| El formato de la URL no cambió | Comparar la URL generada contra la que hoy funciona en producción |
