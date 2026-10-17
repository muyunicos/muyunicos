# 008 — Hero banners: píldoras de idioma automáticas + traducción con IA

**Estado**: implementado (2026-10-16). Cierra T018/T019 de `specs/006-ai-translate-core`.

## Problema

El editor de banners (WC → Marketing → Hero Banners) tenía tres piezas para lo mismo:

1. Un **dropdown de idioma** (`mu-hero-banner-idioma-btn` + lista) para elegir la pestaña,
   más **píldoras decorativas** que solo mostraban los idiomas "marcados".
2. Un **checkbox "Traducir" por idioma** (`traducir_pt` / `traducir_en`) que (a) revelaba la
   grilla de campos del idioma y (b) **copiaba el español** a los campos vacíos al marcarlo.
3. El front decidía con esa casilla (`inc/ui.php`, helper `$mu_hero_field`).

Dos defectos de fondo: la casilla era redundante (al guardar, un valor idéntico al español
se persiste vacío, así que "hay traducción" ya era deducible del contenido) y copiar el
español al marcar hacía imposible distinguir una traducción real de una copia. Además, el
banner no tenía el botón "Traducir con IA" que sí tenía el editor de reseñas (T018 pendiente
de 006), y la marca de origen de reseñas **nunca** marcaba "desactualizada" porque se
recalculaba en cada guardado.

## Cambios

| # | Cambio | Archivos |
|---|---|---|
| 1 | **Componente compartido**: las píldoras y el botón de IA se extraen a un parcial neutro (`.mu-lang-pill`, `.mu-ai-inline-btn`, `.mu-ai-inline-status`, `.mu-lang-hint`), encolado en el front y en el admin. El editor de reseñas pasa a usar esas clases. | `css/components/ai-translate-inline.css` (nuevo), `css/testimonials.css`, `inc/ui.php`, `js/reviews-admin-modal.js` |
| 2 | **Píldoras ES | PT | EN en cada banner** (reemplazan dropdown + píldoras decorativas + checkbox). Eligen qué bloque se edita y recuerdan la pestaña al guardar. | `inc/hero-banners.php`, `js/admin-hero-banners.js` |
| 3 | **Estado automático**: el punto amarillo sale del contenido. `traducir_pt/en` se guardan **derivados** (algún campo con valor propio) y el front usa el valor del idioma si existe (`mu_hero_banner_field()`), sin depender de la casilla. | `inc/hero-banners.php`, `inc/ui.php` |
| 4 | **Placeholders en vez de copiar**: el español aparece como placeholder (texto plano, sin marcado) en los campos vacíos de PT/EN, así el punto no miente. | `js/admin-hero-banners.js` |
| 5 | **Botón "Traducir con IA"** por banner (ámbito `hero_banner`): traduce del español al idioma activo los textos que declara el servicio, completa los campos, enciende el punto y **nunca guarda**. | `inc/hero-banners.php`, `js/admin-hero-banners.js` |
| 6 | **Marca de origen correcta** (`traducir_XX_hash`) + aviso de traducción desactualizada en el bloque del idioma. | `inc/hero-banners.php` |
| 7 | **Mismo arreglo en reseñas**: la marca de origen se conserva si la traducción no cambió en el guardado (antes se recalculaba siempre y el aviso nunca aparecía). | `inc/ui.php` |

### Decisiones

- **Automático total** (decisión del mantenedor): el front muestra el valor traducido si
  existe, sin mirar la casilla. Consecuencia asumida: un banner con contenido PT/EN guardado
  y la casilla apagada **empieza a mostrarse traducido** al desplegar, sin re-guardar.
  Es coherente: el valor solo existe si difiere del español.
- **Qué significa el punto**: ese idioma sobrescribe al menos un campo respecto del español.
  Es exactamente la regla de guardado, así que no puede desincronizarse.
- **La IA traduce solo los 6 campos de texto** del ámbito (`titulo`, `descripcion`,
  `eyebrow`, `cta_texto`, `cta_secundario_texto`, `free_badge_text`). **Las URLs y la imagen
  quedan manuales**, y editar una URL no invalida una traducción (la marca se calcula sobre
  los textos).
- **La marca de origen se recalcula solo si la traducción cambió**; si no, se conserva. Es
  la única forma de que "el español cambió después de traducir" sea detectable.
- Sin clave de IA configurada, el botón informa el motivo y **nada se escribe** (FR-002 de 006).

## Criterios de aceptación

1. Cada banner muestra tres píldoras `ES | PT | EN`; al hacer clic se abre ese bloque y se
   recuerda al guardar.
2. El punto aparece **sin intervención manual** en los idiomas con contenido propio
   (con el contenido que ya estaba guardado, apenas se abre el panel).
3. Ya no existe ninguna casilla "Traducir" ni dropdown de idioma.
4. Los campos vacíos muestran el español como placeholder, sin copiarlo al valor.
5. "Traducir con IA" completa los 6 textos del idioma activo, avisa cuántos campos tradujo
   (o que ya estaban en ese idioma) y no guarda; en ES está deshabilitado.
6. Si el español cambia después de una traducción, el bloque PT/EN avisa que conviene
   revisarla; si se vuelve a traducir (o se edita la traducción), el aviso desaparece.
7. El front en `/pt/` y `/en/` muestra el valor traducido campo por campo, y el español
   donde no hay valor propio.
8. Reseñas: el aviso de "traducción desactualizada" ahora sí aparece cuando el comentario en
   español cambia y la traducción queda igual.
9. Nada bajo `Tema-GeneratePress/`; sin JS inline nuevo; un solo envío externo (el servicio).

## Fuera de alcance

- Traducción en lote (US4 de 006) y traducción de productos/categorías/correos/SEO.
- Traducir URLs o imágenes (siguen manuales; el ámbito declara solo textos).
- Reemplazar las píldoras de países del panel (son otro componente: `.mu-hero-banner-pill`).
