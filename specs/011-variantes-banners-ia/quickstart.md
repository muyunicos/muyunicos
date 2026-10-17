# Quickstart 011 — Variantes de traducción (personas) en el editor de banners

## A. Verificación automática (local, reproducible)

```powershell
# Sintaxis
php -l inc/hero-banners.php
node --check js/admin-hero-banners.js

# Arnés de esta feature (funciones puras del JS)
node specs\011-variantes-banners-ia\harness\variantes-js.js    # esperado: 18 OK / 0 FAIL

# No-regresión de lo que comparte el mismo archivo
php specs\010-fechas-banner-por-pais\harness\arnes-fechas-por-pais.php   # esperado: 96 OK / 0 FAIL
node specs\010-fechas-banner-por-pais\harness\fechas-js.js               # esperado: 28 OK / 0 FAIL
php specs\008-banners-pildoras-ia\harness\arnes-banners.php              # esperado: 30 OK / 0 FAIL
node specs\008-banners-pildoras-ia\harness\banners-js.js                 # esperado: 17 OK / 0 FAIL
php specs\006-ai-translate-core\harness\arnes.php                       # esperado: 54 OK / 0 FAIL
```

Qué cubre el arnés: qué texto de la tanda se aplica a cada campo (una versión por persona),
que un campo sin esa versión **no** se inventa (queda en `faltantes`), la ruta estándar sin
personas, los casos borde (items nulo, campos vacíos, índice fuera de rango, texto vacío) y los
mensajes del estado.

## B. Verificación manual (mantenedor)

**Antes de empezar**: en `WC → Marketing → Traducciones IA`, en el ámbito **Hero banners**,
cargar las **Personas** (una por línea) y poner **Variantes** ≥ 2. Guardar: cambiar personas o
variantes invalida la caché de ese ámbito (la próxima traducción se pide de nuevo).

1. **Botones**: en un banner con textos en español, abrir el idioma PT (o EN) y pulsar
   «Traducir». Deben completarse los campos y aparecer los números `1·2·3…` a la izquierda del
   botón, con el nombre de la persona en el `title` (pasar el mouse).
2. **Elegir**: hacer clic en `2`. Los seis campos de texto deben cambiar a la segunda versión y
   el número `2` debe quedar marcado; el estado dice “Variante 2 (La Mamá Práctica) aplicada.
   Revisá y guardá.”; el punto del idioma se enciende.
3. **Guardar y ver el front**: guardar el banner y mirar `/pt/` (purgando la caché de ese host):
   debe verse la variante elegida, no la primera.
4. **Sin personas**: dejar el ámbito sin personas (o sin variantes > 1), guardar y volver a
   traducir: no deben aparecer botones y el mensaje es el de siempre.
5. **Cambio de idioma**: con la tanda de PT a la vista, pasar a EN: los botones desaparecen
   (no se mezclan versiones de idiomas distintos). Volver a traducir en EN: aparecen de nuevo.
6. **Segunda tanda**: pulsar «Traducir» otra vez con la misma tanda visible: debe pedir una tanda
   nueva (consume cuota) y volver a pintar botones. Con la misma tanda sin `force` el servicio
   responde de caché y no se gasta nada.
7. **Campo omitido**: si el estado avisa “Sin texto en: …”, ese campo quedó con la versión que ya
   tenía (no se inventó nada). Revisarlo a mano antes de guardar.

## C. Reversión

`git revert` del commit. No hay datos nuevos que limpiar: las variantes viven en la respuesta
del servicio y la única persistencia es el texto final que el mantenedor guarda. Si se revierte,
el editor vuelve a aplicar la primera versión y los botones desaparecen.
