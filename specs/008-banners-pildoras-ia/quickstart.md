# Quickstart 008 — Píldoras de idioma y traducción con IA en banners

## A. Verificación automática (local, reproducible)

```powershell
# Sintaxis
php -l inc/hero-banners.php
php -l inc/ui.php
node --check js/admin-hero-banners.js
node --check js/reviews-admin-modal.js
node --check js/testimonials.js

# Arnés PHP del módulo real de banners (WordPress simulado, sin red)
Copy-Item 'inc\hero-banners.php' "$env:TEMP\mu007\hero-banners.php" -Force
php "$env:TEMP\mu007\arnes-banners.php"     # esperado: 30 OK / 0 FAIL

# Arneses del editor de reseñas (componente compartido + arreglo de la marca)
Copy-Item 'js\testimonials.js' "$env:TEMP\mu006\testimonials.js" -Force
Copy-Item 'js\reviews-admin-modal.js' "$env:TEMP\mu006\modal.js" -Force
node "$env:TEMP\mu006\test-ring.js"      # 37 OK / 0 FAIL
node "$env:TEMP\mu006\test-ui.js"        # 21 OK / 0 FAIL
node "$env:TEMP\mu006\test-oldmarkup.js" #  3 OK / 0 FAIL

# Invariantes: no quedan restos del checkbox/dropdown viejo
Select-String -Path js\*.js,css\*.css,css\components\*.css,inc\*.php -Pattern `
  'mu-hero-banner-idioma-btn|mu-hero-banner-idioma-item|mu-hero-banner-idioma-dropdown|traducir-checkbox|traducir-toggle|traducir-fields'
# esperado: sin resultados

# Alcance
git diff --name-only    # nada bajo Tema-GeneratePress/
```

Qué cubre el arnés de banners: estado derivado (`traducir_pt/en`), front que ignora la
casilla, sobrescritura dispersa (lo igual al español no se guarda), y la marca de origen en
sus ocho escenarios — incluida la regresión corregida (el español cambia, la traducción no
→ la marca se conserva y el aviso aparece) y que mover una URL no invalida la traducción.

## B. Verificación manual (mantenedor)

**Antes de empezar**: purgar la caché de la home (el marcado del panel y del hero cambian).

1. **Panel** (WC → Marketing → Hero Banners): cada banner muestra `ES | PT | EN`.
   - Hacer clic en PT: se abre el bloque de portugués. Guardar y recargar: la pestaña
     recordada debe ser PT.
   - El **punto** debe aparecer en los idiomas que ya tenían contenido guardado, sin tocar
     nada. (Ese es el listado de banners afectados por la decisión "automático total".)
   - Ya no hay casilla "Traducir" ni dropdown de idioma.
2. **Placeholders**: en un banner sin traducción, el bloque PT muestra el español en gris
   dentro de los campos vacíos, pero los campos **están vacíos** (comprobar con el inspector
   o borrando y viendo que el punto no se enciende).
3. **Traducir con IA**: con PT activo, pulsar "Traducir con IA". Debe completar título,
   descripción, eyebrow, CTA, CTA secundario y badge; los **botones/URL e imagen quedan
   igual**; el punto se enciende; el estado dice cuántos campos tradujo. Pulsar de nuevo:
   debe decir que ya estaban en ese idioma (sin consumo nuevo, por caché).
4. **Guardar** y mirar el front: en `br.muyunicos.com/pt/` (purgar ese host primero) el
   banner debe verse traducido campo por campo, con los mismos enlaces; en español, igual
   que siempre. Repetir en `/en/`.
5. **Aviso de desactualizada**: cambiar el título en español del banner (sin tocar el PT),
   guardar y volver a abrir: el bloque PT debe avisar que el español cambió desde la
   traducción. Volver a traducir con IA (o editar el PT) y guardar: el aviso desaparece.
6. **Caso declarado**: un banner que tenía contenido PT/EN con la casilla vieja apagada
   ahora se muestra traducido en el front sin re-guardarlo (decisión del mantenedor).
7. **Reseñas** (regresión del arreglo 7): editar una reseña cambiando **solo** el comentario
   en español, guardar y reabrir: debe avisar que la traducción quedó desactualizada.
8. **Fallos declarados** (006 §B.4): sin clave o con la clave rechazada, el botón informa el
   motivo y no escribe nada; navegar el sitio público no consume solicitudes.

## C. Reversión

`git revert` del commit. En la base, las claves `traducir_pt/en` quedan con el valor derivado
último guardado (coherente con el front viejo: si hay contenido, la casilla está marcada), y
`traducir_XX_hash` se ignora. Sin migraciones ni limpieza pendiente.
