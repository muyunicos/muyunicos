# Plan — Spec 021

**Estado**: implementación de código (2026-11-21); config y deploy pendientes. Spec: `spec.md`. Tareas: `tasks.md`. Contrato:
`contracts/checkout-envio.md`.

## Por qué existe

El checkout del tema oculta la dirección detrás del check "Ingresar datos para envío
(Opcional)", pero Woo sigue calculando envío. Como la zona "Nacional" matchea por
provincia y la sesión solo tiene país, no hay tarifas y el pedido se bloquea. Además
Woo renderiza una segunda casilla nativa ("¿Enviar a una dirección diferente?") y
Uber/Retiro se ofrecen en todo el país (decisión aceptada 2026-11-21). El plan
resuelve la config de zonas (prerrequisito manual) y deja el check como único
interruptor de obligatoriedad.

## Orden de ejecución

| Fase | Alcance | Puertas |
|---|---|---|
| **F0** | reproducir en el lab: zona por provincia vs país, producto físico, check on/off | probe Playwright del checkout |
| **F1** | config Woo (manual): Nacional → país AR + 4 métodos; TDF zone sin métodos; calculador off; Retiro default | carrito/checkout como invitado |
| **F2** | tema: una sola dirección, check como interruptor, nota de pedido, hint | `php -l`, `node --check` |
| **F3** | verificación: lab con físico/digital, Retiro default, puerta del plugin | `ficha-pedido.browser.js` |
| **F4** | docs: contrato, `MIGRATION-GUIDE.md` §3/§8, versión del tema | `git diff --name-only` |
| **F5** | deploy manual del mantenedor (no lo hace el agente) | prueba manual en producción |

## F0 — Reproducir

1. En el lab (`C:\wp-lab`), la zona "Lab (AR)" hoy es `country: AR`; por eso las
   tarifas aparecen. Cambiarla temporalmente a `state: AR-B` (Buenos Aires) para
   reproducir el fallo: sin provincia en la sesión no hay métodos.
2. Agregar un producto **físico** de prueba (los del lab son virtuales). Con la zona
   por estado y el check desmarcado, el checkout debe mostrar "No hay opciones de
   envío" y bloquear; es el estado actual de producción.
3. Confirmar el comportamiento del check: desmarcado los campos están ocultos y sin
   `validate-required`; marcado se muestran y Woo los exige.

## F1 — Config Woo (prerrequisito manual)

**F1a (obligatorio)**: zona "Nacional" → Regiones: quitar las 23 provincias y agregar
**Argentina** (país). Las tarifas son fijas e iguales; con el país AR que setea
`inc/geo.php` los métodos aparecen sin provincia ni calculador.

**F1b (decisión 2026-11-21, opción B)**: mantener los **4 métodos** (Retiro, Uber,
Andreani sucursal y domicilio) en la zona Nacional, con **Retiro primero**
(`method_order` 1) para que Woo lo elija por defecto. **No** crear zona "Mar del
Plata": Uber/Retiro se ofrecen en todo el país (el local confirma por WhatsApp).
Crear la zona **"Tierra del Fuego"** (estado AR-V) **sin métodos**; la zona Nacional
(país AR) cubre al resto y a quien no elige provincia. La exclusión de TDF es
**best-effort**: si el cliente no selecciona TDF, aplica la zona país y el local
cancela/coordina. "Resto del mundo" sin métodos (físicos AR-only). Desactivar el
calculador del carrito (`woocommerce_enable_shipping_calc = no`).
`shipping_cost_requires_address` puede quedar mientras el Retiro (local pickup) esté
activo; `shipping_hide_rates_when_free` puede quedar (no hay free_shipping).

**Verificación**: invitado en AR + producto físico → 4 métodos en carrito y checkout
con **Retiro seleccionado por defecto**, sin usar "Calcular envío". Con TDF
seleccionado no hay métodos; sin provincia, la zona país aplica (best-effort).

## F2 — Tema

### `inc/checkout.php`

1. **Una sola dirección**: filtro `woocommerce_cart_needs_shipping_address` →
   `false` en el checkout clásico (página o `wc-ajax=update_order_review|checkout`).
   Elimina la sección nativa "¿Enviar a una dirección diferente?" y el envío del
   pedido pasa a facturación. **No** se toca `woocommerce_cart_needs_shipping`: el
   envío sigue activo y los métodos se muestran.
2. **Guard de bloques**: si la página de checkout usa `woocommerce/checkout` (bloque),
   el filtro no se aplica; el tema no pinta sus campos ahí.
3. **Check existente**: se conserva `billing_shipping_toggle` y el HTML
   `#mu-toggle-shipping` / `name="mu_shipping_toggle"` / value `1` con el texto
   "Ingresar datos para envío (Opcional)". Se agrega un hint: "Si lo marcás, te
   pedimos la dirección; si no, coordinamos el envío por WhatsApp.".
4. **Validación**: `mu_validate_checkout()` sigue leyendo
   `$_POST['mu_shipping_toggle']`; desmarcado no valida ningún campo de dirección;
   marcado exige dirección, ciudad, CP y provincia como hoy.
5. **Campos**: para físicos se mantiene `required=false` y `mu-hidden` en
   `billing_address_1/2`, `billing_city`, `billing_postcode`, `billing_state`; no se
   unsetean (el check los muestra).
6. **Nota de pedido**: en `woocommerce_checkout_order_processed`, si el check está
   desmarcado y el pedido tiene método de envío, agregar la nota privada "Dirección
   de envío a coordinar: el cliente no la ingresó (check desmarcado).". Si no hay
   método de envío (digital), no se agrega.

### `js/checkout.js`

1. `sincronizarDireccion()`: si el check está **desmarcado**, quitar
   `validate-required`, `woocommerce-invalid`, `woocommerce-invalid-required-field`
   y el asterisco `.required` de `.mu-physical-address-field`. Si está **marcado**,
   no tocar: `address-i18n`/la validación del tema ya los exigen.
2. Ejecutarla en `ready` (con `setTimeout(0)`, después de Woo), en
   `country_to_state_changing`, en `updated_checkout` y en el `change` del check.
   Si el check se marca y las clases se habían quitado, re-disparar
   `$(document.body).trigger('country_to_state_changing', [...])` para que
   `address-i18n` las reponga.
3. **No** disparar `update_checkout` por el check: los métodos no dependen de la
   dirección. Los campos de dirección ya disparan el update de Woo (keyup, 1 s).

### `css/checkout.css`

Estilo del hint del check (texto chico, gris) y, si hace falta, del aviso de
"dirección a coordinar" en el checkout. No se oculta la sección nativa por CSS: la
quita el filtro.

### `style.css`

Bump de `Version:` (el tema lo usa como `?ver=` en `functions.php`) para cache-bust.

## F3 — Verificación

1. Lab: producto físico, check desmarcado → 4 métodos, pedido completado, sin
   dirección, nota en el admin. Check marcado → campos visibles/obligatorios,
   dirección = envío; los 4 métodos siguen visibles sin depender del CP. TDF con
   estado → sin métodos; TDF sin provincia → zona país (best-effort). Digital → sin
   envío/dirección.
2. Puerta externa del plugin: `ficha-pedido.browser.js` (ficha → carrito → pedido →
   PDF).
3. Producción: prueba manual del mantenedor después del deploy y de la config.

## F4 — Docs

`contracts/checkout-envio.md`; `MIGRATION-GUIDE.md` §3 (zonas/logística) y §8
(deuda); `style.css` `Version:` en la implementación.

## F5 — Deploy

Manual del mantenedor (constitución del tema). El agente no despliega ni sube
archivos a mano.

## Riesgos y mitigaciones

- **La config de zonas no es código**: sin F1a los métodos siguen sin aparecer.
  Mitigación: F1a es prerrequisito y se verifica antes de F2.
- **Uber/Retiro en todo el país**: decisión aceptada (2026-11-21); el local confirma
  la logística por WhatsApp. Mitigación: ninguna; se documenta.
- **TDF best-effort**: si el cliente no selecciona TDF, la zona país aplica y el
  local cancela/coordina (opción B). Mitigación: documentado; exigir provincia queda
  fuera de B.
- **`shipping_cost_requires_address`**: puede quedar mientras el Retiro (local
  pickup) esté activo; si se desactiva, desmarcarlo. **`shipping_hide_rates_when_free`**:
  no hay free_shipping; si se agrega, desmarcarlo.
- **`needs_shipping_address=false`**: el pedido guarda el envío = facturación. Es lo
  que el tema ya pretende; se prueba el pedido y el admin.
- **`address-i18n`**: con el check marcado exige los campos visibles; con el
  desmarcado el JS limpia las clases. Se prueba en el lab con el check on/off.
- **Sin suite/CI**: verificación manual en producción; el lab del plugin es la red
  local. El deploy es manual del mantenedor.
- **Rollback**: volver la zona a las 23 provincias, quitar el filtro
  `needs_shipping_address`, revertir el JS/hint y purgar caché.

## Comandos de verificación

```powershell
php -l inc\checkout.php
node --check js\checkout.js
git diff --name-only   # nada bajo Tema-GeneratePress/
```

La puerta del plugin se corre desde el repo `personalizador-pdf` con el lab
(`C:\wp-lab`); no es una suite del tema.