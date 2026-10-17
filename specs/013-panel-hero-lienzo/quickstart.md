# Quickstart 013 — Panel del banner: render compartido y lienzo

## A. Verificación automática (local, reproducible)

```powershell
# Sintaxis
php -l inc\hero-banners.php
php -l inc\ui.php
node --check js\admin-hero-banners.js

# Arnés de la Fase 0 (igualdad con el fixture de producción) y del lienzo
php specs\013-panel-hero-lienzo\harness\arnes-render-hero.php   # esperado: 63 OK / 0 FAIL
node specs\013-panel-hero-lienzo\harness\lienzo-js.js           # esperado: 9 OK / 0 FAIL

# No-regresión de todo lo demás
php specs\006-ai-translate-core\harness\arnes.php                      # 54 OK
php specs\008-banners-pildoras-ia\harness\arnes-banners.php            # 30 OK
node specs\008-banners-pildoras-ia\harness\banners-js.js               # 17 OK
php specs\010-fechas-banner-por-pais\harness\arnes-fechas-por-pais.php # 96 OK
node specs\010-fechas-banner-por-pais\harness\fechas-js.js             # 28 OK
node specs\011-variantes-banners-ia\harness\variantes-js.js            # 18 OK
php specs\012-vigencia-anual\harness\arnes-anual.php                   # 87 OK
node specs\012-vigencia-anual\harness\anual-js.js                      # 18 OK

# Invariantes de la Fase 0
Select-String -Path css\home.css -Pattern 'mu-hero-promo'
# esperado: 0 líneas (el hero ya no vive en home.css)

(Select-String -Path css\components\hero-promo.css -Pattern '\.mu-hero-promo' | Measure-Object).Count
# esperado: 34 selectores (más 1 @media y 1 @keyframes)

Select-String -Path inc\ui.php -Pattern 'mu_hero_slide_render'
# esperado: 3 líneas (la llamada del front y los dos comentarios que la explican)

# El lienzo dentro de la fila del panel
(Select-String -Path inc\hero-banners.php -Pattern 'data-mu-canvas' | Measure-Object).Count
# esperado: 1 (el contenedor del lienzo)

(Select-String -Path js\admin-hero-banners.js -Pattern 'data-mu=' | Measure-Object).Count
# esperado: 12 líneas que cubren los 10 nodos marcados (CTA secundario y badge se repintan
# en dos pasos cada uno: contenido/URL y visibilidad)

Select-String -Path css\admin-hero-banners.css -Pattern '@container'
# esperado: 1 (el modo móvil responde al ancho del LIENZO, no al de la ventana)

git diff --name-only   # nada bajo Tema-GeneratePress/
```

Qué cubre el arnés: el slide completo comparado con el fixture de producción (normalizado, ambos
lados serializados como DOM), **byte a byte** en `<h1>`, `<p>`, `src`, `alt`, `href` del CTA
principal y su texto (con el SVG del icono verificado sobre el HTML crudo), la comparación
normalizada del eyebrow y del CTA secundario, **qué función de escape usa cada campo** (marcadores:
`wp_kses_post` para el título, `esc_html` para descripción/eyebrow/CTAs, `esc_attr` para el `alt`,
`esc_url` para `src`/`href`), `first` (clase `active`, `loading`, `fetchpriority`), el badge (propio
y por defecto), la resolución de idioma (PT propio, ES por defecto) y los casos defensivos (banner
no-array, sin eyebrow, CTA secundario incompleto).

## B. Verificación manual (mantenedor)

**Fase 0 (front).** Se hace **después** del deploy y en los 3 hosts principales, porque lo que se
verifica es que el HTML de producción siga siendo el mismo:

```powershell
# 1) El componente se sirve (y home.css ya no trae las reglas del hero)
curl.exe -s https://muyunicos.com/ | Select-String 'hero-promo.css'      # 1 línea (el <link>)
curl.exe -s https://br.muyunicos.com/ | Select-String 'hero-promo.css'   # 1 línea

# 2) El hero de producción es el mismo que antes del deploy
$fx   = (Get-Content -Raw specs\013-panel-hero-lienzo\harness\fixtures\hero-slide-produccion-ar.html) -replace '\s+',' '
$post = [regex]::Match((curl.exe -s https://muyunicos.com/), '(?s)<section class="mu-hero-promo">.*?</section>').Value -replace '\s+',' '
"coincide con el fixture: $($fx.Trim() -eq $post.Trim())"
```

Si el resultado es `False` porque **cambió el contenido** de un banner (título, imagen, CTA), no es
un defecto de la extracción: comparar entonces etiquetas y atributos (estructura). Si cambia la
estructura (una clase, un atributo, su orden), **revertir**.

**Lienzo del editor (Fase 2).** En WC → Marketing → Hero Banners, con un banner cargado:

1. **Encaje**: el lienzo entra completo en la columna (escala al ancho disponible, nunca agranda) y
   mantiene la proporción. Probar el botón **Móvil**: el banner pasa al layout de 968px (imagen
   arriba, contenido centrado, badge en línea) porque el `@container` responde al ancho del
   lienzo, no al de la ventana del admin.
2. **En vivo**: escribir en título, descripción, eyebrow, CTAs o badge y ver el cambio en el lienzo
   **sin guardar**; cambiar de píldora ES\|PT\|EN y comprobar que el lienzo cambia de idioma (y que
   un campo vacío en PT/EN muestra el español, igual que el front).
3. **Condiciones del front**: destildar «Mostrar badge promo» → el badge desaparece del lienzo;
   vaciar el texto **o** la URL del CTA secundario → desaparece; vaciar el eyebrow → desaparece.
4. **Clic-para-foco (N1)**: clic en el título, un CTA, el badge o la imagen → el formulario se
   desplaza al campo, lo enfoca y lo resalta; **ningún clic navega** (los CTA son enlaces).
5. **Tipografía**: si el título se ve con la fuente de reemplazo en lugar de Fredoka One, es que el
   tema padre no registró `generate-google-fonts` en el admin (limitación declarada; no afecta al
   resto de la vista previa).

Mirar además, a ojo, el hero en escritorio y en móvil en `muyunicos.com` y en un subdominio: mismo
tamaño de tipografía, mismo fondo, mismos botones, mismos espacios. Es la única verificación del
`@media` y del `@keyframes` que el arnés no cubre.

**Fase 1 (jerarquía de la fila).** Pendiente: traerá su propia verificación manual (el manejo de
jQuery no es automatizable) en este mismo quickstart.

## C. Reversión

`git revert` del commit de la Fase 0: el marcado vuelve a `ui.php` inline y las reglas del hero a
`css/home.css`; se purga la caché del host (TTL de páginas medido: 24 h) y se repite la verificación
del punto B. Sin datos que migrar. Si se revierte la Fase 0, las Fases 1-3 no se empiezan (el lienzo
depende del renderer compartido).