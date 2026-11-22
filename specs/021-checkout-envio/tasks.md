# Tasks — Spec 021

**Estado**: propuesta (2026-11-21). Spec: `spec.md`; plan: `plan.md`; contrato:
`contracts/checkout-envio.md`.

Convención: `[ ]` pendiente, `[X]` hecho. Las tareas marcadas **(mantenedor)** se
hacen en el admin de Woo y no las ejecuta el agente.

## F0 — Reproducción (lab)

- [ ] T001 Abrir el lab (`C:\wp-lab`), confirmar que la zona "Lab (AR)" es
      `country: AR` (por eso hoy las tarifas aparecen) y cambiarla temporalmente a
      `state: AR-B`. No dejar este cambio al final: la config definitiva es T004.
- [ ] T002 Agregar un producto **físico** de prueba (los del lab son virtuales).
      Con la zona por estado y el check desmarcado, confirmar el fallo: el checkout
      muestra "No hay opciones de envío" y no deja cerrar el pedido.
- [ ] T003 Confirmar el contrato del check en el DOM: `#mu-toggle-shipping`,
      `name="mu_shipping_toggle"`; desmarcado = campos con `mu-hidden` y sin
      `validate-required`; marcado = visibles y con `validate-required`. Es la base
      de T008/T011.

## F1 — Config Woo (mantenedor)

- [ ] T004 **(mantenedor)** Zona "Nacional" → Regiones: quitar las 23 provincias y
      agregar **Argentina** (país). Las tarifas son fijas e iguales; el país AR ya lo
      setea `inc/geo.php`. Sin esta tarea los métodos siguen sin aparecer.
- [ ] T005 **(mantenedor)** Mantener los **4 métodos** (Retiro, Uber, Andreani
      sucursal y domicilio) en la zona Nacional con **Retiro primero**
      (`method_order` 1) para que Woo lo elija por defecto. **No** crear zona "Mar
      del Plata": Uber/Retiro se ofrecen en todo el país (decisión 2026-11-21).
      **Crear zona "Tierra del Fuego" (estado AR-V) sin métodos**; la zona Nacional
      (país AR) cubre al resto y a quien no elige provincia. La exclusión de TDF es
      **best-effort**: si el cliente no selecciona TDF, aplica la zona país y el local
      cancela/coordina. "Resto del mundo" sin métodos (físicos AR-only). Desactivar el
      calculador (`woocommerce_enable_shipping_calc = no`).
      `shipping_cost_requires_address` puede quedar mientras el Retiro (local pickup)
      esté activo; `shipping_hide_rates_when_free` puede quedar (no hay free_shipping).
- [ ] T006 **(mantenedor)** Verificar como invitado en AR con un producto físico:
      carrito y checkout muestran los 4 métodos con **Retiro seleccionado por
      defecto**, sin usar "Calcular envío"; sin dependencia del CP; **TDF con estado
      no muestra métodos; TDF sin provincia aplica la zona país (best-effort)**;
      check off/on; digital.

## F2 — Tema

- [X] T007 `inc/checkout.php`: filtrar `woocommerce_cart_needs_shipping_address` a
      `false` en el checkout clásico (página o `wc-ajax=update_order_review|checkout`),
      con guard para el checkout por bloques (`has_block('woocommerce/checkout')`).
      Elimina la sección nativa "¿Enviar a una dirección diferente?"; el envío del
      pedido pasa a facturación. **No** tocar `woocommerce_cart_needs_shipping`.
- [X] T008 `inc/checkout.php`: conservar el check existente
      `billing_shipping_toggle` y el HTML `#mu-toggle-shipping` /
      `name="mu_shipping_toggle"` / value `1` con el texto "Ingresar datos para envío
      (Opcional)". Agregar el hint "Si lo marcás, te pedimos la dirección; si no,
      coordinamos el envío por WhatsApp.". El check es el **único** interruptor de
      obligatoriedad.
- [X] T009 `inc/checkout.php`: mantener `mu_validate_checkout()` leyendo
      `$_POST['mu_shipping_toggle']`; desmarcado no valida ningún campo de dirección;
      marcado exige dirección, ciudad, CP y provincia como hoy. Mantener
      `required=false` + `mu-hidden` en los campos de dirección para físicos.
- [X] T010 `inc/checkout.php`: en `woocommerce_checkout_order_processed`, si el check
      está desmarcado y el pedido tiene método de envío, agregar la nota privada
      "Dirección de envío a coordinar: el cliente no la ingresó (check desmarcado).";
      si no hay método (digital), no agregar nada.
- [X] T011 `js/checkout.js`: `sincronizarDireccion()` que, con el check desmarcado,
      quite `validate-required`, `woocommerce-invalid`,
      `woocommerce-invalid-required-field` y `.required` de
      `.mu-physical-address-field`; con el check marcado no toca nada. Ejecutar en
      `ready` (setTimeout 0), `country_to_state_changing`, `updated_checkout` y
      `change` del check. Si se marca y se habían quitado, re-disparar
      `country_to_state_changing` para que `address-i18n` las reponga. No disparar
      `update_checkout` por el check.
- [X] T012 `css/checkout.css`: estilo del hint del check y, si hace falta, del aviso
      de dirección a coordinar.
- [X] T013 `style.css`: bump de `Version:` para cache-bust (`?ver=` del tema).
- [X] T014 Verificación estática: `php -l inc\checkout.php`, `node --check
      js\checkout.js`; `git diff --name-only` sin cambios bajo `Tema-GeneratePress/`.

## F3 — Verificación funcional

- [ ] T015 Lab, check **desmarcado**: producto físico → 4 métodos en carrito y
      checkout con **Retiro por defecto**; pedido completado sin dirección; método y
      costo guardados; nota en el admin. Sin "No hay opciones de envío". TDF sin
      provincia → zona país (best-effort). Mapea
      SC-001/SC-002/SC-004/SC-007/SC-010/SC-011.
- [ ] T016 Lab, check **marcado**: campos visibles y obligatorios; dirección =
      facturación y envío; los 4 métodos siguen visibles sin depender del CP; TDF con
      estado → sin métodos. Mapea SC-003/SC-009/SC-011.
- [ ] T017 Lab, producto **digital**: sin envío ni dirección, sin regresiones
      (SC-006).
- [ ] T018 Puerta externa del plugin: `ficha-pedido.browser.js` (ficha → carrito →
      pedido → PDF) sigue verde (SC-008). Se corre desde el repo `personalizador-pdf`
      con el lab.
- [ ] T019 **(mantenedor)** Prueba manual en producción después del deploy y de T004:
      invitado + físico + check off/on; los 4 métodos con Retiro default; TDF con
      estado no muestra métodos, TDF sin provincia aplica la zona país (best-effort);
      digital.

## F4 — Documentación

- [X] T020 `contracts/checkout-envio.md`: contrato del check, filtros, zonas, nota de
      pedido, mensajes y rollback.
- [X] T021 `MIGRATION-GUIDE.md` §3 (zonas/logística) y §8 (deuda): documentar la zona
      Nacional por país con los 4 métodos y Retiro por defecto, la zona TDF (estado
      AR-V) sin métodos y la exclusión best-effort, el calculador desactivado, el
      check como interruptor, la nota de dirección a coordinar y los settings
      `shipping_cost_requires_address`/`shipping_hide_rates_when_free`; dejar claro
      que los físicos son AR-only ("Físicos (Solo AR)").
- [X] T022 Actualizar el estado del `spec.md`/`tasks.md` al cerrar la implementación
      y registrar las decisiones Q1–Q5 del §7.

## F5 — Cierre

- [X] T023 Revisar `git status` y `git diff`: spec/plan/tasks/contracts + los archivos
      del tema; nada fuera del alcance.
- [X] T024 Commit en el repo del tema (rama `fix/` o `feat/` según la convención).
      **No** push sin aprobación explícita del mantenedor; el push dispara el deploy
      de Hostinger.
- [ ] T025 **(mantenedor)** Deploy y purga de caché; verificación posterior (T019).

## Trazabilidad rápida

| Criterio | Tareas |
|---|---|
| SC-001/SC-002 | T004, T005, T007, T009, T011, T015 |
| SC-003/SC-009 | T008, T009, T011, T016 |
| SC-004 | T007, T008 |
| SC-005 | T004, T005, T006, T016, T019 |
| SC-006 | T017 |
| SC-007 | T010, T015 |
| SC-008 | T018 |
| SC-010 | T005, T006, T015, T016 |
| SC-011 | T005, T006, T015, T016, T019 |