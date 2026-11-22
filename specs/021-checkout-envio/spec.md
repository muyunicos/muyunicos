# Spec 021 — Checkout y envío: métodos consistentes y dirección opcional

**Estado**: **implementada en código** (2026-11-21); la config de zonas y el deploy quedan pendientes del mantenedor. Nace de la incidencia de ventas perdidas por
envío y de la configuración de zonas que pasó el mantenedor. Detalle técnico en
`plan.md`, tareas en `tasks.md` y contrato en `contracts/checkout-envio.md`.

## 1. Contexto y problema

La tienda vende digitales (globales, sin envío) y físicos (AR, con envío por zonas).
El checkout del tema oculta la dirección detrás de un check opcional, pero Woo sigue
calculando envío: sin provincia no matchea la zona y el pedido se bloquea.

**Requisito explícito del mantenedor (2026-11-21)**: se conserva el check existente
"Ingresar datos para envío (Opcional)" y **solo exige los datos de dirección si el
cliente lo marca**; desmarcado no se exige ningún dato de dirección en ninguna capa.

1. **Zona "Nacional" atada a 23 provincias.** Las tarifas son fijas e iguales para
   todas las provincias; la provincia solo sirve para matchear la zona.
2. **`inc/geo.php` (555-557) setea solo el país** (`billing_country` y
   `shipping_country` = AR), nunca la provincia. En una sesión nueva
   `shipping_state` está vacío.
3. **Sin provincia no hay zona.** `WC_Shipping_Zones::get_zone_matching_package()`
   usa `destination[state]`; sin estado no matchea "Nacional", cae en "Resto del
   mundo" (sin métodos) y no hay tarifas.
4. **El carrito es inconsistente.** Si la sesión ya tiene provincia (calculador
   usado antes, cuenta logueada, checkout previo) aparecen Retiro/Uber/Andreani; si
   no, "Calcular envío" / "Ingresá tu dirección". El log de Analytics que reportó el
   mantenedor es el `form_start` del calculador (`calc_shipping_city`, destino
   `/es/carrito/` por wpLingua): no es un error.
5. **El checkout bloquea la venta.** `inc/checkout.php` (183-210) oculta
   dirección/ciudad/CP/provincia tras el check y las deja `required=false`; con el
   check desmarcado la provincia queda vacía, no hay zona, no hay métodos y
   `WC_Checkout::validate_checkout()` frena con "No se ha seleccionado ningún método
   de envío"; el resumen muestra "No hay opciones de envío / asegurate de que tu
   dirección esté completa". **Ahí se pierden las ventas.**
6. **Sección nativa duplicada.** `WC()->cart->needs_shipping_address()` es true;
   `form-shipping.php` renderiza "¿Enviar a una dirección diferente?" (otra casilla).
   Si se marca, los campos nativos `shipping_*` pasan a obligatorios. Si producción
   tiene `ship_to_destination=shipping`, además viene pre-marcada.
7. **Uber/Retiro en la zona Nacional.** Se ofrecen a las 23 provincias; **decisión
   2026-11-21: se mantiene así** (el local confirma la logística por WhatsApp). No se
   corrige en esta spec.
8. **Validación del tema más estricta que la zona.** Al marcar el check exige
   dirección, ciudad, CP y provincia (270-283), aunque la zona (país AR) no necesita
   CP; el check es el que decide pedir la dirección completa.
9. **`address-i18n.js` re-marca `validate-required` y reordena.** Hoy Woo valida
   `.validate-required:visible`, así que los campos ocultos no bloquean; pero es
   frágil y, al hacerse visibles con el check, Woo exige la dirección completa por
   encima del `required=false` del PHP. Además reordena los campos: el teléfono pasa
   a prioridad 100 y la fila del check (sin `data-priority`) cae al final.
10. **La etiqueta es ambigua.** "Ingresar datos para envío (Opcional)" no aclara que
    controla la dirección, no la visibilidad de los métodos.
11. **Sin dirección no hay aviso.** El pedido queda sin dirección y el admin no tiene
    una nota de "coordinar".
12. **"Resto del mundo" sin métodos.** Correcto: los físicos son **AR-only**
    (`MIGRATION-GUIDE.md` §3: "Físicos (Solo AR)") y los digitales no necesitan envío.
    No se agrega fallback.
13. **Digital y bloques.** Los digitales no necesitan envío/dirección; el checkout
    por bloques no usa estos campos y no debe verse afectado.
14. **Tierra del Fuego.** El mantenedor quiere excluirla del envío; la zona
    "Nacional" por 22 provincias no matchea sin provincia. Se resuelve con la zona
    país AR + una zona TDF (estado AR-V) sin métodos; la exclusión es best-effort
    (si el cliente no elige TDF, aplica la zona país y el local cancela/coordina).
15. **Métodos de envío amontonados.** El `td` de "Envío" en `#order_review` no tiene
    estilos: los 4 métodos se ven en una lista comprimida en la columna angosta.
16. **Formulario no regionalizado.** "Población", "Código Postal / ZIP" y
    "Región / Provincia" son genéricos; en AR son Localidad, Código Postal y
    Provincia; para físicos el país es solo Argentina.
17. **País oculto con `type=hidden`.** Al no haber select de país, `address-i18n`
    usa el locale default y pisa los labels AR. Se resuelve con un select AR oculto
    por CSS.
18. **Select de país oculto.** Se ocultó para forzar AR, pero el select es necesario
    para el redirect multi-país; se vuelve a mostrar con los 10 países.
19. **El locale no alcanza.** Con el select visible, `address-i18n` no aplica el
    locale AR en este setup; los labels vuelven a Población/ZIP/Región. Se restaura el
    relabel JS como red final.

Evidencia: `inc/checkout.php` (183-215, 226-228, 260-286), `inc/geo.php` (505-599),
Woo `class-wc-cart.php` (1742-1749), `class-wc-shipping-zones.php` (395-408),
`class-wc-checkout.php` (786-789, 863-867, 1030-1057), `templates/cart/cart-shipping.php`
(60-67), `templates/checkout/form-shipping.php`, y la config de zonas que pasó el
mantenedor (Nacional: 23 provincias; Resto del mundo: sin métodos).

## Clarifications

### Session 2026-11-21

- Q: ¿La zona Nacional debe separar Uber/Retiro por código postal o mostrar todos los métodos en Argentina? → A: Todos los métodos en Argentina (con la excepción TDF de Q5), sin split por CP; no se crea la zona "Mar del Plata".
- Q: ¿Se exige dirección para "Andreani a domicilio"/"Uber" o se permite coordinar? → A: Se permite coordinar siempre; la dirección es opcional y se coordina por WhatsApp.
- Q: ¿El calculador del carrito debe pedir datos de envío para calcular? → A: No; en Argentina con producto físico se muestran los métodos con "Retiro en Muy Únicos" por defecto; el calculador no es requisito.
- Q: ¿Hace falta un método "A cotizar" para Resto del mundo? → A: No; los físicos son AR-only (`MIGRATION-GUIDE.md` §3) y los digitales no necesitan envío; la zona sin métodos es correcta.
- Q: ¿Cómo se excluye Tierra del Fuego si no se quiere exigir la provincia? → A: Zona "Tierra del Fuego" (estado AR-V) sin métodos; la zona Nacional (país AR) cubre al resto y a quien no elige provincia. Es exclusión best-effort: si el cliente no selecciona TDF, la zona país aplica y el local cancela/coordina. No se exige provincia.

## 2. Objetivos

- Que un invitado en AR vea **los mismos métodos** en carrito y checkout, sin
  provincia y sin usar el calculador.
- Que pueda **cerrar el pedido sin dirección** cuando el check está desmarcado,
  eligiendo Retiro/Andreani; el local coordina.
- Que el **check existente** sea el único interruptor: desmarcado = ningún dato de
  dirección obligatorio; marcado = los campos se muestran y se validan como hoy.
- Una sola casilla de dirección: sin la sección nativa duplicada.
- Los 4 métodos (Retiro, Uber, Andreani sucursal y domicilio) en Argentina (TDF
  best-effort), sin dependencia del CP; **Retiro por defecto**.
- Digitales y checkout por bloques intactos.

## 3. Decisiones

- **D1 — Zona "Nacional" = país Argentina, no 23 provincias.** Las 4 tarifas son
  fijas e iguales; la provincia no aporta al cálculo. El país AR ya lo setea
  `geo.php`: los métodos aparecen siempre, sin provincia y sin calculador.
- **D2 — Todos los métodos en Argentina; sin split por CP (decisión 2026-11-21).**
  La zona "Nacional" queda en **país AR** con Retiro, Uber y Andreani (sucursal y
  domicilio). **No** se crea zona "Mar del Plata" ni se condiciona por CP. Uber y
  Retiro se ofrecen en todo el país; el local confirma la logística por WhatsApp.
  **Tierra del Fuego** queda en una zona propia (estado AR-V) **sin métodos**; la
  exclusión es **best-effort** (si el cliente no selecciona TDF, aplica la zona país
  y el local cancela/coordina). "Resto del mundo" sigue sin métodos: físicos AR-only.
- **D3 — Una sola dirección en el checkout.** La de facturación del tema;
  `woocommerce_cart_needs_shipping_address` → `false` en el checkout clásico:
  desaparece la sección nativa "¿Enviar a una dirección diferente?" y el envío del
  pedido pasa a ser la facturación.
- **D4 — Se conserva el check existente "Ingresar datos para envío (Opcional)"**
  (`#mu-toggle-shipping`, `name="mu_shipping_toggle"`, value `1`). Su estado es el
  **único** interruptor de obligatoriedad:
  - **Desmarcado**: ningún campo de dirección es obligatorio, en ninguna capa
    (validación del tema, locale de Woo, sección nativa, tarifa). Los campos siguen
    ocultos con `mu-hidden` y `required=false`.
  - **Marcado**: los campos de dirección se muestran y se validan como hoy
    (dirección, ciudad, CP y provincia), y se guardan como facturación y envío.
- **D5 — Sin dirección el pedido se completa.** Se guarda el método elegido y se
  agrega una **nota de pedido** "Dirección de envío a coordinar (el cliente no la
  ingresó)", visible en el admin. No se bloquea la venta.
- **D6 — Se permite coordinar siempre (decisión 2026-11-21).** Ningún método exige
  dirección: ni Andreani a domicilio ni Uber. La dirección completa solo se pide si
  el cliente marca el check; si no, se coordina por WhatsApp y queda la nota de
  pedido. No hay validación por método que bloquee.
- **D7 — El calculador del carrito no es requisito (decisión 2026-11-21).** Se
  desactiva `woocommerce_enable_shipping_calc` (o se oculta el calculador): en AR
  con físico los métodos se muestran solos y **"Retiro en Muy Únicos" queda por
  defecto** (primer método de la zona). El cliente puede cambiar el método.
  `woocommerce_shipping_cost_requires_address` **puede quedar marcado mientras el
  Retiro (local pickup) esté activo** (Woo muestra el envío por la excepción de local
  pickup); si se desactiva el Retiro, desmarcarlo.
  `woocommerce_shipping_hide_rates_when_free` puede quedar: no hay `free_shipping`;
  si se agrega, desmarcarlo.
- **D8 — Guard para checkout por bloques** (`has_block('woocommerce/checkout')`): el
  parche no interfiere. Digitales sin envío ni dirección.
- **D9 — La config de zonas es un prerrequisito manual del mantenedor.** El tema no
  toca la base; el spec documenta los pasos exactos.
- **D10 — Deploy manual del mantenedor** (constitución del tema). El agente no
  despliega ni sube archivos a mano.

## 4. Fuera de alcance

- Migrar a checkout por bloques.
- Envíos físicos fuera de AR.
- Cambiar tarifas, precios o pasarelas.
- El plugin Personalizador PDF (solo se corre su puerta de regresión).
- El deploy (manual del mantenedor).

## 5. Requisitos funcionales

- **FR-001**: la zona Nacional debe matchear con **país AR**, sin exigir provincia;
  la zona "Tierra del Fuego" (estado AR-V) no tiene métodos.
- **FR-002**: carrito y checkout muestran los **mismos métodos** para el mismo
  destino, sin usar el calculador.
- **FR-003**: el **check existente** es el único interruptor: desmarcado → ningún
  campo de dirección obligatorio; marcado → campos visibles y validados como hoy.
- **FR-004**: con el check desmarcado, el pedido se completa sin dirección; se guarda
  el método y el costo.
- **FR-005**: con el check marcado, la dirección se guarda como facturación y envío;
  los 4 métodos siguen visibles y no dependen del CP.
- **FR-006**: no se renderiza la sección nativa "¿Enviar a una dirección diferente?";
  hay **una sola casilla**.
- **FR-007**: los 4 métodos se muestran en Argentina; la zona TDF no tiene métodos
  (best-effort); "Resto del mundo" sin métodos (físicos AR-only).
- **FR-008**: digitales sin envío/dirección; sin regresiones.
- **FR-009**: si no hay dirección, el admin ve la nota "dirección a coordinar".
- **FR-010**: la validación del tema no exige dirección completa cuando el check está
  desmarcado; no hay bloqueos por `address-i18n`.
- **FR-011**: el flujo del plugin (ficha → carrito → pedido → PDF) sigue funcionando.
- **FR-012**: mensajes y etiquetas en español, claros; el check conserva su texto y
  se agrega un hint de qué hace.
- **FR-013**: guard para checkout por bloques.
- **FR-014**: en AR con físico, carrito y checkout muestran los métodos con "Retiro
  en Muy Únicos" por defecto; el calculador no pide datos para calcular.
- **FR-015**: la exclusión de Tierra del Fuego es **best-effort**: si el cliente
  selecciona TDF (con el check marcado o una dirección guardada), la zona TDF no
  tiene métodos; si no la selecciona, la zona país aplica y el local cancela o
  coordina. No se exige provincia.
- **FR-016**: el formulario mantiene el orden Nombre → País → "Te contactamos por:" →
  E-Mail → WhatsApp (opcional) → check → Dirección → Apartamento → Población → CP →
  Provincia; `address-i18n` no lo desordena; la etiqueta del teléfono es "WhatsApp" y
  el `#wa-status-msg` sigue visible.
- **FR-017**: en el resumen del pedido, los métodos de envío se muestran como tarjetas
  verticales (radio, título y precio; "Gratis" en los de costo 0), con la seleccionada
  resaltada y el bloque a ancho completo; mismo layout en mobile.
- **FR-018**: en AR, el formulario usa "Localidad", "Provincia", "Código Postal"
  (sin ZIP), "Dirección" con "Calle y número", "Piso / Depto" y "WhatsApp"; para
  físicos el país es solo Argentina y el campo de país queda fijo en AR.
- **FR-019**: en físicos, el país es un select con una sola opción AR, oculto por CSS
  pero presente en el DOM para que `address-i18n` aplique el locale AR; el envío sigue
  usando la facturación (una sola dirección).
- **FR-020**: en el checkout, el select de país muestra los 10 países del proyecto y
  conserva el redirect al subdominio al cambiar; el envío físico sigue limitado a AR
  por la zona, no por el select.
- **FR-021**: los labels AR de las direcciones se resuelven con el locale, los
  labels iniciales del PHP y `restaurarEtiquetasAR` (JS) como red final; el locale
  por sí solo no alcanza en este setup.

## 6. Criterios de aceptación

- **SC-001**: invitado en AR con físico → 4 métodos en carrito y checkout **sin**
  provincia ni calculador.
- **SC-002**: check desmarcado → pedido completado; sin "No hay opciones de envío";
  método y costo guardados; nota de dirección a coordinar.
- **SC-003**: check marcado → campos visibles/obligatorios; dirección = envío; los
  4 métodos siguen visibles sin depender del CP.
- **SC-004**: una sola casilla; sin sección nativa duplicada.
- **SC-005**: los 4 métodos se muestran en Argentina; si el cliente selecciona TDF,
  la zona TDF no ofrece métodos (best-effort); no hay dependencia del CP.
- **SC-006**: producto digital → sin envío ni dirección.
- **SC-007**: pedido sin dirección → nota visible en el admin.
- **SC-008**: la puerta externa del plugin `ficha-pedido.browser.js` sigue verde
  (53/53).
- **SC-009**: el check desmarcado no dispara `validate-required` ni
  `woocommerce-invalid` en los campos ocultos; el marcado sí los exige.
- **SC-010**: en AR con físico, "Retiro en Muy Únicos" queda seleccionado por
  defecto en carrito y checkout; el calculador no es requisito.
- **SC-011**: TDF best-effort: con la provincia TDF seleccionada no hay métodos; sin
  provincia, la zona país aplica y el local cancela/coordina; no se exige provincia.
- **SC-012**: el formulario, cerrado y abierto, mantiene el orden Nombre → País →
  contacto → E-Mail → WhatsApp (opcional) → check → Dirección → Apartamento →
  Población → CP → Provincia; el teléfono conserva la etiqueta "WhatsApp" y su estado
  de validación.
- **SC-013**: en el checkout, los 4 métodos se ven como tarjetas legibles, la
  seleccionada (Retiro) resaltada, "Gratis" en el Retiro, los precios a la derecha y
  el resumen a ancho completo; en mobile se mantiene el mismo layout.
- **SC-014**: checkout AR físico muestra Localidad/Provincia/Código Postal, sin ZIP,
  con placeholders AR, país solo Argentina (campo fijo) y WhatsApp opcional; digital
  conserva los 10 países.
- **SC-015**: el checkout físico muestra Localidad/Provincia/Código Postal (sin ZIP) y
  una sola dirección; el select de país AR está en el DOM pero no se ve; el
  `address-i18n` no pisa los labels; el digital mantiene los 10 países.
- **SC-016**: el select de país está visible, lista los 10 países y al cambiarlo
  redirige al subdominio correspondiente; los labels del formulario quedan en AR
  (Dirección, Piso / Depto, Localidad, Código Postal, Provincia) y el envío físico
  sigue AR-only.
- **SC-017**: el formulario físico muestra Localidad/Provincia/Código Postal con el
  relabel JS final; el locale/PHP no alcanzan.

## 7. Supuestos y decisiones cerradas

- Las tarifas son fijas e iguales por provincia (confirmado por el mantenedor).
- El físico es AR-only (`MIGRATION-GUIDE.md` §3: "Físicos (Solo AR)"); los digitales
  son globales y no necesitan envío.
- El local coordina la dirección por WhatsApp si falta (la página `/envios/` ya lo
  propone).
- **Decisión Q1 (2026-11-21)**: todos los métodos en Argentina, sin split por CP; no
  se crea la zona "Mar del Plata"; la exclusión de TDF se rige por Q5.
- **Decisión Q2 (2026-11-21)**: se permite coordinar siempre; ningún método exige
  dirección.
- **Decisión Q3 (2026-11-21)**: el calculador del carrito no se usa para calcular; en
  AR con físico se muestran los métodos con "Retiro en Muy Únicos" por defecto.
- **Decisión Q4 (2026-11-21)**: no se agrega fallback para "Resto del mundo"; los
  físicos son AR-only y la zona sin métodos es correcta.
- **Decisión Q5 (2026-11-21, opción B)**: zona "Tierra del Fuego" (estado AR-V) sin
  métodos; la zona Nacional (país AR) cubre al resto y a quien no elige provincia.
  La exclusión de TDF es **best-effort**: si el cliente no selecciona TDF, la zona
  país aplica y el local cancela/coordina. No se exige provincia.
- **Decisión Q6 (2026-11-21)**: el formulario fija el orden Nombre → País →
  "Te contactamos por:" → E-Mail → WhatsApp (opcional) → check → Dirección →
  Apartamento → Población → CP → Provincia después de `address-i18n`; restaura la
  etiqueta "WhatsApp" y el `#wa-status-msg`; el teléfono sigue opcional.
- **Decisión Q7 (2026-11-21)**: los métodos de envío del resumen se muestran como
  tarjetas verticales; "Gratis" en los de costo 0; la seleccionada resaltada; el
  bloque a ancho completo; mismo layout en mobile.
- **Decisión Q8 (2026-11-21)**: regionalización AR; Localidad, Provincia, Código
  Postal, Dirección/Calle y número, Piso / Depto, WhatsApp; país fijo AR en físicos.
- **Decisión Q9 (2026-11-21)**: el país en físicos es un select con una sola opción
  AR, oculto por CSS, para que `address-i18n` aplique el locale; una sola dirección.
- **Decisión Q10 (2026-11-21)**: el select de país se muestra con los 10 países para
  el redirect; el envío físico se limita por la zona AR; los labels AR se reaplican
  por JS después de `address-i18n`.
- **Decisión Q11 (2026-11-21)**: se restaura el parche JS `restaurarEtiquetasAR`;
  el locale/PHP no aplican los labels AR en este setup y el parche es la red final.

## 8. Riesgos y reversibilidad

- **Cambio de zona**: afecta tarifas; el país AR ya está seteado por geo. Rollback:
  volver a las 23 provincias y purgar caché.
- **Uber/Retiro en todo el país**: riesgo aceptado (decisión 2026-11-21); el local
  confirma la logística por WhatsApp. Rollback: no aplica.
- **TDF best-effort**: un cliente que no selecciona TDF puede pedir; el local
  cancela/coordina (decisión Q5). Rollback: exigir provincia (fuera de B) o eliminar
  la zona TDF.
- **`needs_shipping_address=false`**: el envío del pedido pasa a facturación; probar
  el pedido y el admin. Rollback: quitar el filtro.
- **`address-i18n`**: los campos visibles con el check marcado se exigen como hoy;
  con el check desmarcado el JS limpia `validate-required`. Rollback: quitar el sync.
- **`shipping_cost_requires_address`**: puede quedar mientras el Retiro (local
  pickup) esté activo; si se desactiva el Retiro, Woo oculta el envío sin dirección y
  hay que desmarcarlo.
- **`shipping_hide_rates_when_free`**: no hay `free_shipping`; si se agrega, oculta
  Uber/Andreani y hay que desmarcarlo.
- **Calculador desactivado**: el carrito no pide datos; si se vuelve a activar, no
  debe ser requisito para ver los métodos. Rollback: reactivar el setting.
- **Sin suite/CI**: verificación en el lab del plugin + prueba manual en producción
  (constitución).
- **Deploy**: manual del mantenedor; no se despliega desde el repo.

## 9. Prerrequisitos manuales (Woo)

1. **Nacional → país Argentina**: WooCommerce → Ajustes → Envío → zona "Nacional" →
   Regiones: quitar las 23/22 provincias, agregar **Argentina** → Guardar.
2. **Zona Tierra del Fuego**: crear una zona "Tierra del Fuego" con **estado
   Tierra del Fuego (AR-V)** y **sin métodos**. La zona Nacional (país AR) cubre al
   resto y a quien no elige provincia; TDF es **best-effort**: si el cliente no
   selecciona TDF, aplica la zona país y el local cancela/coordina.
3. **Mantener los 4 métodos** (Retiro, Uber, Andreani sucursal y domicilio) en la zona
   Nacional, con **Retiro primero** (`method_order` 1) para que Woo lo elija por
   defecto. No crear zona "Mar del Plata"; "Resto del mundo" sin métodos (físicos AR-only).
4. **Calculador del carrito**: WooCommerce → Ajustes → Envío → Opciones de envío →
   desactivar "Habilitar el calculador de envío en la página del carrito"
   (`woocommerce_enable_shipping_calc = no`).
5. **Ajustes de envío**: "Forzar el envío a la dirección de facturación"
   (`billing_only`) → sí. `shipping_cost_requires_address` puede quedar (la excepción
   de local pickup mantiene el envío visible); `shipping_hide_rates_when_free` puede
   quedar (no hay free_shipping). Si se desactiva el Retiro o se agrega envío gratis,
   desmarcar el setting correspondiente.
6. Probar como invitado con un producto físico: carrito y checkout deben mostrar los
   4 métodos con Retiro seleccionado, sin usar "Calcular envío". Con TDF seleccionado
   no debe haber métodos; sin provincia, la zona país aplica (best-effort). Probar el
   check off/on y un digital.

## 10. Verificación

- **Lab**: la zona del lab es `country: AR`; cambiarla temporalmente a
  `state: AR-B` reproduce el fallo; volver a país lo corrige. Agregar un producto
  físico; probar carrito/checkout con y sin dirección, con los 4 métodos y Retiro
  por defecto, TDF con estado (sin métodos) y sin estado (zona país), y un digital.
- **Puerta externa**: `ficha-pedido.browser.js` del repo del plugin (ficha →
  carrito → pedido → PDF).
- **Producción**: prueba manual del mantenedor después del deploy y de la config.

## 11. Documentación a actualizar

- `MIGRATION-GUIDE.md` §3 (zonas/logística) y §8 (deuda).
- `contracts/checkout-envio.md`.
- `style.css` `Version:` (cache-bust) en la implementación.