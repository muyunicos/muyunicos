# Contract — Checkout y envío (`spec 021`)

Este contrato fija el comportamiento que implementan `inc/checkout.php`, `js/checkout.js`
y la configuración de zonas. Si el código y este documento difieren, manda el
documento y se corrige el código.

## C1 — El check existente es el único interruptor

| Propiedad | Valor |
|---|---|
| Campo del tema | `billing_shipping_toggle` (fieldset `billing`, tipo `text`) |
| HTML | `#mu-toggle-shipping`, `name="mu_shipping_toggle"`, `value="1"` |
| Texto | `Ingresar datos para envío (Opcional)` (se conserva) |
| Estado inicial | desmarcado |
| Alcance | **solo** la obligatoriedad/visibilidad de los campos de dirección |

Reglas:

1. **Desmarcado**: los campos `billing_address_1`, `billing_address_2`,
   `billing_city`, `billing_postcode` y `billing_state` están ocultos
   (`mu-hidden`), con `required=false` en PHP y **sin** `validate-required` en el
   DOM. Ninguna capa (tema, `address-i18n`, sección nativa, tarifa) puede exigirlos.
2. **Marcado**: los campos se muestran y se validan como hoy: dirección, ciudad, CP
   y provincia (Argentina). La dirección se guarda como facturación **y** envío.
3. El check **no** controla la visibilidad de los métodos de envío. Los métodos
   dependen de la zona y del país/provincia/CP, no del check.
4. El check **no** se renombra; se agrega un hint debajo:
   "Si lo marcás, te pedimos la dirección; si no, coordinamos el envío por WhatsApp.".
5. **Orden y etiquetas**: Nombre → País → "Te contactamos por:" → E-Mail → WhatsApp
   (opcional) → check → Dirección → Apartamento → Población → CP → Provincia. El
   orden se fija en `js/checkout.js` después de `address-i18n`; el teléfono conserva
   "WhatsApp" y el `#wa-status-msg`; el teléfono es opcional.

## C2 — Zonas y métodos

| Zona | Región | Métodos |
|---|---|---|
| Nacional | **Argentina (país)** | Retiro, Uber, Andreani sucursal/domicilio (Retiro primero/default) |
| Tierra del Fuego | **Tierra del Fuego (AR-V)** | ninguno |
| Resto del mundo | — | ninguno |

- La zona Nacional **debe** matchear con solo el país AR (lo setea `inc/geo.php`).
- **Todos los métodos en Argentina; sin split por CP** (decisión 2026-11-21): no se
  crea la zona "Mar del Plata"; Uber/Retiro se ofrecen en todo el país (el local
  confirma por WhatsApp).
- **TDF best-effort**: la zona TDF no tiene métodos; si el cliente no selecciona TDF,
  la zona país aplica y el local cancela/coordina. No se exige provincia.
- **Retiro primero** (`method_order` 1) para que Woo lo elija por defecto.
- El físico es AR-only; los digitales no usan zonas. "Resto del mundo" sin métodos es
  correcto (`MIGRATION-GUIDE.md` §3: "Físicos (Solo AR)").

## C3 — Filtros y contexto

| Hook | Valor | Contexto |
|---|---|---|
| `woocommerce_cart_needs_shipping_address` | `false` | checkout clásico (página o `wc-ajax=update_order_review|checkout`), sin bloques |
| `woocommerce_cart_needs_shipping` | **sin cambios** | el envío sigue activo; los métodos se muestran |
| `woocommerce_checkout_fields` | se mantiene el actual | físicos: campos ocultos + `required=false`; digitales: se unsetean |

Guard de bloques: si `has_block('woocommerce/checkout', wc_get_page_id('checkout'))`,
no se aplica el filtro de `needs_shipping_address` (el tema no pinta sus campos ahí).

Con `needs_shipping_address=false`, Woo no renderiza "¿Enviar a una dirección
diferente?", el fieldset `shipping` se saltea y `get_posted_address_data()` devuelve
la facturación para el envío: el pedido guarda envío = facturación.

## C4 — Nota de pedido

- Hook: `woocommerce_checkout_order_processed`.
- Condición: en el checkout clásico, `empty($_POST['mu_shipping_toggle'])`, el pedido
  tiene método de envío **y** no tiene dirección de envío (`get_shipping_address_1()`
  vacío).
- Acción: `$order->add_order_note('Dirección de envío a coordinar: el cliente no la
  ingresó (check desmarcado).')` (nota privada).
- Si no hay método de envío (digital), no se agrega.
- El método y el costo quedan guardados por Woo; la nota no reemplaza el dato.

## C5 — Mensajes y etiquetas

- El check conserva su texto; el hint es el de C1.4.
- Los 4 métodos se muestran en Argentina; no dependen del CP.
- TDF: con la provincia seleccionada no hay métodos; sin provincia, la zona país
  aplica (best-effort); no se exige provincia.
- Con el check desmarcado **no** debe aparecer "No hay opciones de envío" ni
  "asegurate de que tu dirección esté completa" para un carrito físico en AR.
- Si el cliente elige "Andreani a domicilio" o "Uber" sin dirección, se le puede
  mostrar un aviso (no bloqueante): "Coordinaremos la dirección por WhatsApp".
- No se muestran mensajes nuevos en digitales.

## C6 — Carrito

- El calculador se **desactiva** (`woocommerce_enable_shipping_calc = no`); no es
  requisito para ver los métodos.
- En AR con físico los métodos se muestran solos y **"Retiro en Muy Únicos" queda por
  defecto** (primer método de la zona); el cliente puede cambiarlo.
- `woocommerce_shipping_cost_requires_address` puede quedar mientras el Retiro (local
  pickup) esté activo; si se desactiva, desmarcarlo. `shipping_hide_rates_when_free`
  puede quedar (no hay free_shipping); si se agrega, desmarcarlo.
- El carrito y el checkout deben mostrar los mismos métodos para el mismo destino.
- **Resumen del pedido**: los métodos se muestran como tarjetas verticales en
  `#order_review` (radio, título, precio, "Gratis" en costo 0), con la seleccionada
  resaltada y el `td` de "Envío" a ancho completo; mismo layout en mobile.
- **Regionalización AR**: Localidad, Provincia, Código Postal (sin ZIP), Dirección
  (Calle y número), Piso / Depto, WhatsApp; país fijo AR en físicos.
- **País visible**: el select de país lista los 10 países y conserva el redirect al
  subdominio; el envío físico se limita por la zona AR.
- **Labels AR**: Localidad, Provincia, Código Postal, Dirección, Piso / Depto,
  WhatsApp; se resuelven con el locale AR y los labels iniciales del PHP, sin relabel
  JS.

## C7 — Regresión

- La puerta externa del plugin `ficha-pedido.browser.js` (ficha → carrito → pedido →
  PDF) debe seguir verde (53/53).
- Digitales: sin envío, sin dirección, sin nota.
- Checkout por bloques: sin cambios.

## C8 — Rollback

1. Zona Nacional: volver a las 23 provincias (purgar caché de zonas).
2. Zona TDF: eliminarla (o agregarle métodos) si se quiere habilitar.
3. Calculador: reactivar `woocommerce_enable_shipping_calc` si se quiere el calculador.
4. Tema: quitar el filtro `needs_shipping_address`, el sync de
   `js/checkout.js`, la nota y el hint; volver `Version:`.
5. Purga de caché (LiteSpeed/CDN) después de cada rollback.

## C9 — Evidencia de referencia

- `inc/checkout.php` (183-215, 226-228, 260-286).
- `inc/geo.php` (505-599).
- Woo `class-wc-checkout.php` (786-789, 863-867, 1030-1057),
  `class-wc-shipping-zones.php` (395-408),
  `class-wc-cart.php` (1742-1749),
  `templates/cart/cart-shipping.php` (60-67),
  `templates/checkout/form-shipping.php`.
- Config de zonas del mantenedor (2026-11-21): Nacional = 23 provincias; Resto del
  mundo sin métodos. **Decisión**: Nacional pasa a país AR con los 4 métodos, Retiro
  primero/default, sin split por CP.
- `MIGRATION-GUIDE.md` §3: "Físicos (Solo AR)"; los digitales no necesitan envío.