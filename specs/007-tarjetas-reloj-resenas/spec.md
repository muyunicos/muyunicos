# 007 — Tarjetas parejas, miniatura a la izquierda y reloj de rotación

**Estado**: implementado (2026-10-16). Sin cambios de datos: es UI de la sección de reseñas.

## Problema

La sección de testimonios (`shortcode mu_testimonios_section`) mostraba tres síntomas:

1. **Tarjetas de altos dispares**: el comentario se recorta por palabras (20), así que
   cada tarjeta mide distinto y la grilla queda desprolija.
2. **La foto de la compra va abajo y a todo el ancho** (`max-width:100%` inline), lo que
   rompe el alto y empuja el comentario.
3. **El botón de rotación desaparecía al cargar otras reseñas**: lo generaba
   `js/testimonials.js` dentro del HTML que reescribe `renderReviews()`, colgado de la
   última tarjeta, y con un `cloneNode` para re-enganchar el clic. Además rotaba solo cada
   120 s sin ninguna señal visual para el visitante.

## Cambios

| # | Cambio | Archivo |
|---|---|---|
| 1 | Tarjetas de alto uniforme: `grid-auto-rows: 1fr` + `min-height` + recorte del comentario por líneas (`-webkit-line-clamp: 5`). El recorte por palabras del JS queda solo como red de seguridad (`WORD_LIMIT = 45`). | `css/testimonials.css`, `js/testimonials.js` |
| 2 | Foto de la compra como **miniatura de 64 px a la izquierda** del comentario (`.mu-review-body` + `.mu-purchase-photo`), sin estilos inline. | `css/testimonials.css`, `js/testimonials.js` |
| 3 | Botón de rotación **estático en el marcado** (`.mu-reviews-shell`, fuera de `#mu-reviews-container`): el repintado ya no lo destruye y el clic se engancha una sola vez. | `inc/ui.php`, `css/testimonials.css`, `js/testimonials.js` |
| 4 | **Reloj**: un anillo SVG (`.mu-refresh-ring`) completa el aro del botón en `--mu-ring-duration` (10 s). Al completar la vuelta gira el icono (`is-spinning`), carga otras reseñas y vuelve a empezar. | `inc/ui.php`, `css/testimonials.css`, `js/testimonials.js` |

### Decisiones

- **La duración vive en el CSS** (`--mu-ring-duration`), no en el JS: el JS lee la variable
  para el modo sin animación. Una sola fuente de verdad, sin cuentas duplicadas.
- **El ritmo lo marca el navegador**: el JS escucha `animationiteration` del anillo en lugar
  de llevar un temporizador. Pausar el anillo (`animation-play-state: paused` vía
  `.is-paused`) pausa el reloj **y** la carga a la vez: no se pueden desincronizar.
- **Ciclo de 10 s** (antes 120 s, sin señal visual).
- **El clic vuelve el contador a cero**: se reinicia con la Web Animations API
  (`getAnimations()` → `currentTime = 0`), que funciona también con el anillo en pausa (el
  cursor está sobre el botón cuando se hace clic); en navegadores sin esa API queda el
  respaldo de quitar y reponer la animación por estilos. Una vuelta rezagada que llegue en
  los 250 ms siguientes al clic se descarta, así el clic nunca carga reseñas dos veces.
- **Pausas**: cursor sobre el botón, cursor sobre alguna reseña (hover sobre la grilla, que
  es más robusto que listeners por tarjeta porque las tarjetas se recrean), sección fuera
  de pantalla (`IntersectionObserver`) y pestaña en segundo plano (`visibilitychange`, que
  además reinicia el reloj por si el navegador congeló la animación). Al reanudar continúa
  donde iba.
- **`prefers-reduced-motion`**: no se dibuja el anillo y el ciclo lo lleva un `setInterval`
  con la misma duración (leída del CSS). El reloj nunca es la única señal.
- **Sin reseñas**, el botón no se muestra.
- **Estilos al CSS**: los estilos inline que el JS inyectaba (foto, botón «Editar», fecha)
  pasaron al archivo de estilos, y solo gira el icono (`> svg`), no el anillo.

## Criterios de aceptación

1. Todas las tarjetas de la grilla tienen la misma altura, con comentarios cortos y largos.
2. Los comentarios largos terminan recortados (sin desbordar ni empujar la fecha).
3. Las reseñas con foto de compra muestran la miniatura a la izquierda del comentario.
4. El botón de rotación **nunca desaparece** al cargar otras reseñas (es el mismo nodo).
5. El aro del botón se completa en 10 s; al completarse gira el icono, aparecen otras
   reseñas y el reloj vuelve a empezar.
6. Con el cursor sobre el botón o sobre una reseña el reloj se detiene y **no** se cargan
   reseñas nuevas; al salir, continúa desde donde iba.
7. Fuera de pantalla o con la pestaña en segundo plano el reloj se detiene; al volver,
   arranca de cero.
8. El clic sigue rotando en el acto y reinicia el reloj.
9. Con `prefers-reduced-motion` no hay animación y las reseñas siguen rotando.
10. Nada bajo `Tema-GeneratePress/` (tema padre) modificado; sin JS inline nuevo en el
    HTML de salida.

## Fuera de alcance

- Cambiar el conjunto o el orden de las reseñas (`getRandomReviews` sigue mostrando 3 al azar
  sin repetir hasta agotar).
- Que la miniatura abra la foto en grande: la tarjeta entera es un enlace a Google y anidar
  otro `<a>` es HTML inválido (requeriría reestructurar la tarjeta).
- Los números finos (10 s, 5 líneas, 64 px, `min-height: 280px`) son calibración visual: se
  ajustan en `css/testimonials.css` sin tocar el JS.
