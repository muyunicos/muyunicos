# 02 — Convenciones y comandos (Muyunicos)

## Estilo
- PHP + JS + CSS; mensajes/UI en español, sin tildes en código puro.
- No agregar comentarios salvo pedido explícito.
- Editar con herramienta de edición (diff exacto).

## Comandos (desde la raíz del tema)
```powershell
Get-ChildItem inc -Filter *.php | ForEach-Object { php -l $_.FullName }
php -l functions.php
node --check js/<archivo>.js
node tests/galeria.test.js
node tests/galeria.browser.js
node tests/visor.browser.js
```

## Reglas de oro
- No commitear/pushear/desplegar sin pedido explícito (push dispara webhook del tema).
- No tocar DB/credenciales/servicios externos sin aprobación.
- Un dueño por dato; reutilizar antes de agregar.