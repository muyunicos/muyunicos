# Quickstart 007 — Tarjetas y reloj de rotación

## A. Verificación automática (reproducible en local)

```powershell
# Sintaxis
php -l inc/ui.php
node --check js/testimonials.js

# Arnés de la sección (DOM simulado, sin navegador): tarjetas, reloj y pausas
Copy-Item 'js\testimonials.js' "$env:TEMP\mu006\testimonials.js" -Force
node "$env:TEMP\mu006\test-ring.js"        # esperado: 37 OK / 0 FAIL

# El editor de reseñas, sin regresiones
node "$env:TEMP\mu006\test-ui.js"          # esperado: 21 OK / 0 FAIL
node "$env:TEMP\mu006\test-oldmarkup.js"   # esperado: 3 OK / 0 FAIL

# Alcance: el tema padre no se toca
git diff --name-only                       # nada bajo Tema-GeneratePress/
```

El arnés vive en `%TEMP%\mu006\` (temporal). Qué cubre: el HTML generado ya no trae el
botón, las tarjetas usan `.mu-review-body` / `.mu-purchase-photo` / `.mu-review-time` sin
estilos inline, la vuelta del anillo gira el icono y repinta, el cursor sobre el botón o
sobre la grilla detiene la carga, el clic rota y reinicia, pestaña oculta y fuera de
pantalla pausan, `prefers-reduced-motion` usa temporizador de 10 s y el marcado viejo
(botón sin anillo) sigue funcionando.

## B. Verificación manual (lo que el arnés no puede ver)

**Antes de empezar**: purgar la caché de la página que tiene la sección (el botón y el
anillo ahora viajan en el HTML de la página).

1. **Alturas**: recargar y mirar la grilla. Las tres tarjetas miden igual; forzar reseñas
   cortas y largas (recargando varias veces) y comprobar que el alto no cambia.
2. **Recorte**: los comentarios largos terminan en "…" sin desbordar; la fecha queda
   siempre al pie de la tarjeta.
3. **Miniatura**: las reseñas con foto de compra muestran la imagen chica (64 px,
   redondeada) a la izquierda del comentario; las que no tienen foto dejan el comentario a
   todo el ancho.
4. **Reloj**: mirar el aro del botón completarse. Contar: **10 segundos**. Al completarse,
   el icono gira, cambian las reseñas y el aro vuelve a empezar.
5. **Pausa sobre el botón**: dejar el cursor encima del botón. El reloj se congela y **no**
   cargan reseñas nuevas; al apartar el cursor, sigue desde donde estaba.
6. **Pausa sobre una reseña**: apoyar el cursor sobre una tarjeta (sin entrar al botón). El
   reloj se congela; los enlaces siguen funcionando. Al salir, continúa.
7. **Clic manual**: pulsar el botón. Rota en el acto y el contador vuelve a cero. Como el
   cursor está encima del botón el anillo queda en pausa (aro vacío) y arranca a correr al
   apartar el cursor; **no** debe verse una segunda carga inmediata.
8. **Fuera de pantalla / segunda pestaña**: bajar la sección fuera de vista y esperar: no
   debe rotar. Volver: arranca de cero. Igual si se cambia de pestaña y se vuelve.
9. **Móvil**: en pantalla chica el botón va centrado abajo, el aro se ve completo y el
   reloj corre (en táctil no hay pausa por cursor; el tap rota y reinicia).
10. **Reduced motion**: activar "reducir movimiento" en el sistema/DevTools
    (`prefers-reduced-motion: reduce`). No debe verse el aro y las reseñas deben rotar
    igual cada 10 s.
11. **Consola limpia**: sin errores ni advertencias de este módulo.

## C. Calibración (todo en `css/testimonials.css`)

| Qué | Dónde | Valor actual |
|---|---|---|
| Duración del reloj (y del ciclo) | `.mu-nav-btn.refresh { --mu-ring-duration }` | `10s` |
| Líneas visibles del comentario | `.mu-review-text { -webkit-line-clamp }` | `5` |
| Alto mínimo de tarjeta | `.mu-review-card { min-height }` | `280px` |
| Tamaño de la miniatura | `.mu-purchase-photo { flex / width / height }` | `64px` |

El JS no necesita cambios al tocar esos valores: lee `--mu-ring-duration` del CSS para el
modo sin animación.

## D. Reversión

`git revert` del commit, o a mano: quitar `.mu-reviews-shell` y el botón de `inc/ui.php`,
restaurar el bloque de `renderReviews()` que generaba el botón y borrar el bloque del reloj
de `js/testimonials.js`. Sin cambios de datos ni de opciones: nada que limpiar en la base.
