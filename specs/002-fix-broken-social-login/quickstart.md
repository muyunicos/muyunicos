# Quickstart: Verificar que el login social funciona

**Feature**: 002-fix-broken-social-login | **Fecha**: 2026-10-02

Estos comandos comprueban que el fix funciona. El escenario 1 se puede automatizar;
el 2 requiere una persona con una cuenta de Google o Facebook real.

Todos se ejecutan contra producción. No hay staging.

---

## Escenario 1 — Las URLs apuntan a la ruta vigente

**Prueba**: los cuatro enlaces de login social usan la ruta que existe, no la
que devuelve 404.

### 1a. La ruta de login responde

```bash
# Debe dar 200
curl -s -o /dev/null -w "%{http_code}\n" https://muyunicos.com/login/

# Debe dar 404 (esta es la ruta que el tema ya no debe usar)
curl -s -o /dev/null -w "%{http_code}\n" https://muyunicos.com/wp-login.php
```

**Esperado**: `200` y luego `404`. Si el primero da 404, el plugin que oculta la
URL está configurado con otro slug y hay que actualizar el supuesto de la spec.

### 1b. Los enlaces del modal usan la ruta vigente

```bash
# Extraer los href de login social del HTML servido
curl -s https://muyunicos.com/ | grep -o 'href="[^"]*loginSocial[^"]*"'
```

**Esperado**: dos enlaces con `https://muyunicos.com/login/?loginSocial=...`.

| Resultado | Significado |
|---|---|
| Aparecen con `/login/` | Correcto |
| Aparecen con `wp-login.php` | El fix no llegó a producción |
| No aparecen | El modal no se renderiza: revisar que el visitante no esté autenticado |

### 1c. Cero rutas escritas a mano en el código

```bash
grep -rn "wp-login.php" inc/ functions.php
```

**Esperado**: cero resultados.

### 1d. Los cuatro enlaces salen del mismo punto de construcción

```bash
# Debe encontrar 4 usos
grep -rc "mu_social_login_url" inc/auth-modal.php inc/checkout.php
```

**Esperado**: 4 en total entre los dos archivos. Si hay un número literal
`site_url(` o una ruta escrita a mano, hay un uso que se quedó atrás.

### 1e. El subdominio se preserva

```bash
curl -s https://us.muyunicos.com/ | grep -o 'href="https://us[^"]*loginSocial[^"]*"'
```

**Esperado**: enlaces con `us.muyunicos.com`, no con el dominio raíz.

| Resultado | Significado |
|---|---|
| `us.muyunicos.com/login/?...` | Correcto: el comprador vuelve a su zona |
| `muyunicos.com/login/?...` | El comprador perdería su moneda al volver |

### 1f. El proveedor acepta el formato

```bash
curl -s -o /dev/null -D - \
  "https://us.muyunicos.com/login/?loginSocial=google" | grep -i "^location:"
```

**Esperado**: una redirección al proveedor de autenticación.

| Resultado | Significado |
|---|---|
| 302 al proveedor | El formato es aceptado |
| 302 a la página de login | El proveedor no reconoció el parámetro: el formato cambió |
| 404 o error | La ruta está mal |

---

## Escenario 2 — El flujo completo funciona (manual)

**Prueba**: una persona real completa el login. Este escenario no se puede
automatizar: requiere interacción con un proveedor externo.

### Preparación

Tener a mano una cuenta de Google y una de Facebook. No usar una cuenta de
trabajo: el login crea ovincula un usuario en el sitio.

### Pasos

1. Abrir `https://us.muyunicos.com/` en una ventana de incógnito.
2. Pulsar el icono de usuario del header para abrir el modal de autenticación.
3. Pulsar **"Google"**.
   - **Esperado**: se abre la pantalla de permisos de Google en una ventana
     emergente, con el nombre y el correo de la cuenta.
   - **Falla si**: aparece un error 404, o se queda en blanco, o Google dice que
     la redirección no está autorizada.
4. Aceptar los permisos.
   - **Esperado**: se cierra la ventana y el modal indica que la sesión cambió.
5. Repetir con **"Facebook"**.
6. Ir al checkout y verificar que los botones sociales también funcionan desde ahí.
   - **Esperado**: vuelve al checkout autenticado, no a la home.

### Verificar el subdominio

7. Iniciar sesión en `https://mexico.muyunicos.com/`.
   - **Esperado**: los precios se muestran en la moneda de México.
   - **Falla si**: aparecen precios en pesos argentinos. Eso significa que el
     login devolvió al comprador al dominio raíz.

### Desloguearse

8. Cerrar sesión y comprobar que el sitio vuelve al estado de visitante.

| Resultado | Significado |
|---|---|
| Flujo completo sin errores | El fix funciona |
| 404 al pulsar el botón | El enlace sigue apuntando a la ruta vieja |
| "Redirect URI mismatch" del proveedor | El formato de la URL cambió. Ver `research.md` §2 |
| Vuelve al dominio raíz | Se perdió la zona del comprador |

---

## Escenario 2b — El filtrado del edge no bloquea el retorno

**Prueba**: el WAF no corta el callback de OAuth. Este escenario existe porque
el login se rompio dos veces por causas distintas: una por la construccion de la
URL y otra por el filtrado del borde. Arreglar una no arregla la otra.

```bash
# Debe devolver 200. Con el nivel de seguridad Alto devolvia 403.
curl -sI "https://muyunicos.com/login/?loginSocial=google&state=t&code=TEST&scope=email+profile+https://www.googleapis.com/auth/userinfo.profile"
```

| Resultado | Significado |
|---|---|
| 200 | El edge no bloquea el callback |
| 403 | El WAF volvio a bloquear el retorno. El fix de URLs es correcto pero no alcanza |

**Por que este parametro**: la regla del WAF bloqueaba cualquier parametro que
contuviera la secuencia `.profile`, y el proveedor de Google siempre la envia en
el scope del retorno. Aislado parametro a parametro: `scope=userinfo.email` pasaba,
`scope=userinfo.profile` no.

---

## Escenario 3 — Los iconos se ven iguales

**Prueba**: el origen de los iconos cambió, su apariencia no.

1. Abrir el modal de autenticación antes y después del cambio.
2. Comparar: la X de cerrar, los chevrons de los campos, y los logos de Google y
   Facebook.
3. Comprobar que se ven **exactamente igual**.

```bash
# Cero SVG sueltos en el modal
grep -c "<svg" inc/auth-modal.php
```

**Esperado**: 0. Todos los iconos salen del repositorio central.

---

## Escenario 4 — Con el plugin de ocultación desactivado (manual)

**Prueba**: el login funciona también cuando la URL de login es la de por
defecto. Cumple el FR-006.

> ⚠️ Esta prueba se hace en el panel de Hostinger. Si algo sale mal, el login
> por usuario y contraseña queda accesible en la ruta estándar: es reversible
> reactivando el plugin.

1. Desactivar el plugin que oculta la URL de login.
2. Repetir el escenario 2 completo.
   - **Esperado**: funciona igual. El enlace debe apuntar ahora a la ruta estándar.
3. **Reactivar el plugin** antes de terminar.
4. Confirmar que el login vuelve a funcionar con el plugin activo.

| Resultado | Significado |
|---|---|
| Funciona con el plugin activo y desactivado | El fix cumple el FR-006 |
| Solo funciona con el plugin activo | La ruta quedó fijada en el código: el fix está incompleto |

---

## Escenario 5 — Sin regresiones en el checkout

**Prueba**: el resto del flujo de compra sigue intacto.

```bash
# El checkout responde y conserva su bypass de caché.
# La URL real es /finalizar-compra/ (verificado 2026-10-02):
# /checkout/ devuelve 404 porque WooCommerce la localize al español.
curl -s -D - -o /dev/null https://muyunicos.com/finalizar-compra/ \
  | grep -iE "^HTTP/|cache-control"

# Cero errores fatales
curl -s https://muyunicos.com/ | grep -ci "fatal error\|critical error"
```

**Esperado**: redirección hacia el checkout, con `Cache-Control: no-cache` (el
contenido dinámico nunca se cachea), y cero errores.

Además, a mano: completar una compra de prueba, porque el cambio toca el
checkout. Ese paso no lo puede hacer nadie más.

---

## Checklist de cierre

| # | Escenario | Cubre |
|---|---|---|
| 1 | URLs apuntan a la ruta vigente | SC-001, SC-003, SC-007 |
| 2 | Flujo completo con persona real | SC-001, SC-002 |
| 3 | Iconos idénticos | SC-004, SC-005 |
| 2b | El WAF no bloquea el retorno | SC-009 |
| 4 | Con el plugin desactivado | SC-006 |
| 5 | Sin regresiones en el checkout | SC-008 |

Los escenarios 2, 4 y la compra de prueba del 5 **no se pueden automatizar**:
requieren una persona. Los tres están marcados como manuales a propósito, no como
pendientes.

**Referencia**: las reglas que estos comandos validan están definidas en
[data-model.md](./data-model.md). El análisis técnico, en
[research.md](./research.md). Los criterios completos, en [spec.md](./spec.md).
