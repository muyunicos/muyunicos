# Quickstart 015 — Verificación de CSS + cron de bordes

## A. Verificación automática (local, reproducible)

```powershell
# Sintaxis
php -l inc\hero-banners.php
node --check js\admin-hero-banners.js

# Arnés de CSS (feature 015): comentarios y llaves de TODOS los .css del tema + reglas clave
node specs\015-css-verificacion-y-cron-bordes\harness\css-check.js   # esperado: 68 OK / 0 FAIL

# Arnés del borde (012, donde vive el cron): incluye la revisión diaria
php specs\012-vigencia-anual\harness\arnes-anual.php                 # esperado: 102 OK / 0 FAIL
node specs\012-vigencia-anual\harness\anual-js.js                    # esperado: 18 OK / 0 FAIL

# No-regresión del panel
php specs\013-panel-hero-lienzo\harness\arnes-render-hero.php        # 118 OK
node specs\013-panel-hero-lienzo\harness\lienzo-js.js                # 17 OK
php specs\010-fechas-banner-por-pais\harness\arnes-fechas-por-pais.php # 99 OK
node specs\010-fechas-banner-por-pais\harness\fechas-js.js           # 28 OK
node specs\011-variantes-banners-ia\harness\variantes-js.js          # 18 OK
php specs\008-banners-pildoras-ia\harness\arnes-banners.php          # 30 OK
node specs\008-banners-pildoras-ia\harness\banners-js.js             # 17 OK
php specs\006-ai-translate-core\harness\arnes.php                    # 54 OK

git diff --name-only   # nada bajo Tema-GeneratePress/
```

Qué cubre el arnés de CSS (y por qué existe): nació de un bug real — una edición dejó huérfana
la segunda línea de un comentario, el navegador **descartó la regla siguiente**
(`.is-hereda { display: none }`) y el panel dejó de ocultar los países que heredan, así que
«ver todos los países» parecía no funcionar. Ningún arnés de PHP/JS podía verlo. Ahora:

- **casos sintéticos** del detector (válido, cierre huérfano —con la línea—, comentario sin
  cerrar, llaves de más/de menos, llaves dentro de comentarios);
- **los 24 archivos .css** del tema: comentarios y llaves balanceados;
- **reglas clave del panel** presentes (`.is-hereda { display: none`, `.is-ver-todos`,
  `@container`, la fila plegada, el estado `is-sin-paises`, el lienzo pegajoso) y que
  `home.css` ya no tenga reglas del hero.

**Se validó en orden**: con el bug presente el arnés **falla** señalando
`css/admin-hero-banners.css` y la línea exacta; después del arreglo pasa. Si algún día se
vuelve a romper un comentario, este arnés lo dice en segundos.

Y el arnés de 012 cubre el cron: el evento se recuerda (`mu_hero_banner_borde_esperado`), el
caso normal **no** purga, un borde perdido purga **una vez** y **rearma**, el evento viejo se
rearma sin purgar, y el modo (`url`/`all`) queda registrado.

## B. Verificación manual (mantenedor)

**Antes de empezar**: purgar la caché del host (el panel cambia).

1. **El bug arreglado**: desplegar un banner con **más de un país tildado** y sin excepciones →
   en la tabla de vigencia se ven **Global + las excepciones** (los países que heredan **no**
   aparecen). Tildar **«ver todos los países»** → aparecen las 11 filas (las que heredan,
   atenuadas). Destildarlo → vuelven a ocultarse.
2. **Estado del cron en el panel**: arriba de la lista debe verse
   *«Última purga por borde de fecha: … · N host(s) · por URL, host por host»* (o
   «Todavía no hubo purgas por borde de fecha» si nunca corrió). Si el modo dijera
   **«purga TOTAL»**, el plugin no está exponiendo `litespeed_purge_url` y conviene revisarlo.
3. **Eventos agendados**: `wp_next_scheduled('mu_hero_banner_purge_borde')` (el borde) y
   `wp_next_scheduled('mu_hero_banner_revisar_bordes_hook')` (la revisión diaria) deben devolver
   timestamps, no `false`.
4. **Prueba del borde sin esperar un mes**: cargar un banner con la ventana cerrando **hoy**
   (fin = hoy) y guardar → el evento queda para la medianoche del país; al día siguiente el
   panel debe mostrar la última purga actualizada **sin que nadie guarde nada**.
5. **Rastro**: revisar `wp-content/uploads/mu-logs/hero-borde.log` (una línea por borde, con
   fecha UTC). Si no existe el directorio, es que todavía no corrió ningún borde.
6. **Auto-sanación** (opcional, para valer la pena): borrar a mano el evento único
   (`wp_clear_scheduled_hook`) y esperar la revisión diaria → debe rearmarlo y aparecer la línea
   *«revisión diaria: evento rearmado …»* en el archivo plano.

## C. Reversión

`git revert` por commit: el arreglo de CSS (`d6db174`) y el cron son independientes. Las opciones
nuevas (`mu_hero_banner_borde_esperado`, `mu_hero_banner_ultima_purga`) quedan huérfanas e
inertes; el archivo plano de rastro, también. Sin migración ni limpieza pendiente.
