<?php
/**
 * Muy Únicos — Plantilla Coming Soon v2.0.0 (Standalone)
 *
 * ⚠️  NO llamar wp_head() ni wp_footer() aquí.
 *    Esta plantilla se sirve con exit() en inc/coming-soon.php,
 *    antes de que WordPress complete su ciclo de carga.
 *    Todo el CSS y las fuentes van inlineados abajo para
 *    garantizar 0 recursos externos bloqueantes y mínima descarga.
 *
 * @package GeneratePress_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$logo_url   = 'https://muyunicos.com/wp-content/uploads/2025/10/muy-logo.webp';
$wa_number  = '542235331311';
$wa_message = rawurlencode( '¡Hola! Te escribo desde muyunicos.com' );
$wa_url     = 'https://wa.me/' . $wa_number . '?text=' . $wa_message;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Muy Únicos — Muy pronto</title>
<link rel="icon" href="https://muyunicos.com/wp-content/uploads/2025/10/cropped-android-chrome-512x512-1-100x100.jpg" sizes="32x32">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Inter:wght@400;600&display=swap" rel="stylesheet">
<style>
/* === RESET MÍNIMO === */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
body{
  min-height:100dvh;
  background:#fbf7f5;
  font-family:'Inter',sans-serif;
  color:#277292;
  display:flex;
  align-items:center;
  justify-content:center;
  padding:20px;
  overflow:hidden;
}

/* === SHAPES DE FONDO === */
.cs-bg{position:fixed;inset:0;pointer-events:none;z-index:0;overflow:hidden}
.cs-bg span{position:absolute;border-radius:9999px;}
.cs-bg .s1{width:clamp(140px,32vw,300px);height:clamp(140px,32vw,300px);background:#2B9FCF;opacity:.12;top:-60px;right:-70px}
.cs-bg .s2{width:clamp(90px,20vw,180px);height:clamp(90px,20vw,180px);background:#FFD77A;opacity:.22;bottom:-40px;left:-50px}
.cs-bg .s3{width:clamp(55px,10vw,110px);height:clamp(55px,10vw,110px);background:#2B9FCF;opacity:.08;bottom:15%;right:8%}

/* === TARJETA === */
.cs-card{
  position:relative;z-index:1;
  background:#fff;
  border-radius:16px;
  box-shadow:0 8px 32px rgba(0,0,0,.10);
  padding:clamp(28px,6vw,48px);
  max-width:460px;
  width:100%;
  text-align:center;
}

/* === LOGO === */
.cs-logo{margin-bottom:22px}
.cs-logo img{max-width:150px;height:auto;display:block;margin-inline:auto}

/* === TAGLINE IDIOMAS === */
.cs-langs{margin-bottom:6px;min-height:2.6em}
.cs-lang{
  display:none;
  font-family:'Fredoka One',cursive;
  font-size:clamp(1.6rem,4.5vw,2.2rem);
  color:#2B9FCF;
  line-height:1.2;
  font-weight:400;
}
.cs-lang.active{display:block}

/* === SUBTÍTULO === */
.cs-sub{
  font-size:clamp(.9rem,2.5vw,1rem);
  color:#6C6F7A;
  margin-bottom:24px;
  line-height:1.6;
  max-width:32ch;
  margin-inline:auto;
}

/* === DIVIDER === */
.cs-divider{
  width:40px;height:4px;
  background:#FFD77A;
  border-radius:9999px;
  margin:0 auto 24px;
}

/* === BOTÓN WHATSAPP === */
.cs-wa{
  display:inline-flex;
  align-items:center;
  gap:10px;
  background:#25D366;
  color:#fff;
  font-family:'Inter',sans-serif;
  font-weight:600;
  font-size:1rem;
  padding:.7em 1.6em;
  border-radius:9999px;
  text-decoration:none;
  transition:background 180ms ease, transform 180ms ease;
  box-shadow:0 4px 14px rgba(37,211,102,.35);
}
.cs-wa:hover,.cs-wa:focus-visible{
  background:#1ebe5d;
  transform:translateY(-2px);
  outline:2px solid #25D366;
  outline-offset:3px;
}
.cs-wa:active{transform:translateY(0)}
.cs-wa svg{flex-shrink:0}

/* === REDUCCIÓN DE MOVIMIENTO === */
@media(prefers-reduced-motion:reduce){
  .cs-wa{transition:none;transform:none!important}
}
</style>
</head>
<body>

<div class="cs-bg" aria-hidden="true">
  <span class="s1"></span>
  <span class="s2"></span>
  <span class="s3"></span>
</div>

<main class="cs-card" role="main">

  <div class="cs-logo">
    <img
      src="<?php echo esc_url( $logo_url ); ?>"
      alt="Muy Únicos"
      width="150" height="56"
      loading="eager" fetchpriority="high"
    >
  </div>

  <!-- Título rotativo multi-idioma -->
  <div class="cs-langs" aria-live="polite" aria-atomic="true">
    <p class="cs-lang active" lang="es">Estamos mejorando el sitio para vos</p>
    <p class="cs-lang" lang="en">We're improving the site for you</p>
    <p class="cs-lang" lang="pt">Estamos melhorando o site para você</p>
    <p class="cs-lang" lang="it">Stiamo migliorando il sito per te</p>
    <p class="cs-lang" lang="fr">Nous améliorons le site pour vous</p>
  </div>

  <p class="cs-sub">Muy Únicos · Diseños para todos los días</p>

  <div class="cs-divider" aria-hidden="true"></div>

  <a
    href="<?php echo esc_url( $wa_url ); ?>"
    class="cs-wa"
    target="_blank"
    rel="noopener noreferrer"
    aria-label="Contactar por WhatsApp"
  >
    <!-- WhatsApp icon inline SVG (no dep externa) -->
    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
    </svg>
    Consultá por WhatsApp
  </a>

</main>

<script>
(function(){'use strict';
  // Rotación de idiomas cada 3s — sin jQuery, sin frameworks
  var langs = document.querySelectorAll('.cs-lang');
  if (!langs.length) return;
  var i = 0;
  setInterval(function(){
    langs[i].classList.remove('active');
    i = (i + 1) % langs.length;
    langs[i].classList.add('active');
  }, 3000);
})();
</script>

</body>
</html>
