<?php
/**
 * Arnés de la extracción del render del hero (feature 013, Fase 0).
 *
 * Compara el HTML que emite mu_hero_slide_render() —ahora fuente única del front y del
 * futuro lienzo del panel— contra el fixture capturado de PRODUCCIÓN **antes** de la
 * extracción. Si el front cambia una clase, un atributo, su orden o el escape, falla.
 *
 * Qué compara y con qué rigor:
 *   · El slide completo, NORMALIZADO (se colapsan los espacios en blanco: la indentación
 *     de un bucle inline no es reproducible dentro de una función y el navegador la
 *     ignora). Etiquetas, atributos, su orden y los textos deben coincidir.
 *   · Los nodos con contenido (h1, p, span del CTA) y los atributos (src, alt, href),
 *     BYTE A BYTE.
 *   · El eyebrow y el texto del CTA secundario, normalizados (el original los emite con
 *     espacios alrededor).
 *
 * Se corre desde la raíz del repositorio:
 *   php specs\013-panel-hero-lienzo\harness\arnes-render-hero.php
 */
define( 'ABSPATH', __DIR__ . '/' );
date_default_timezone_set( 'UTC' );

if ( ! defined( 'HOUR_IN_SECONDS' ) ) { define( 'HOUR_IN_SECONDS', 3600 ); }
if ( ! defined( 'DAY_IN_SECONDS' ) ) { define( 'DAY_IN_SECONDS', 86400 ); }

$GLOBALS['mu_ok']   = 0;
$GLOBALS['mu_fail'] = 0;

function check( $label, $cond, $extra = '' ) {
    if ( $cond ) {
        $GLOBALS['mu_ok']++;
        echo "OK   {$label}\n";
    } else {
        $GLOBALS['mu_fail']++;
        echo "FAIL {$label}" . ( '' !== $extra ? "  [{$extra}]" : '' ) . "\n";
    }
}

function same( $label, $expected, $actual ) {
    check(
        $label,
        $expected === $actual,
        'esperado=' . var_export( $expected, true ) . ' obtenido=' . var_export( $actual, true )
    );
}

/* --- WordPress simulado --------------------------------------------------- */
/* Los escapes ANOTAN su uso: así el arnés puede exigir que el título siga pasando por
   wp_kses_post y la descripción por esc_html (regresión clásica de una extracción). */
$GLOBALS['mu_esc_calls'] = [];

function is_admin() { return false; }
function add_action() {}
function __( $t ) { return $t; }
function esc_html__( $t ) { return $t; }
function esc_attr__( $t ) { return $t; }
function sanitize_title( $t ) { return trim( strtolower( preg_replace( '/[^a-z0-9]+/i', '-', (string) $t ) ), '-' ); }
function sanitize_text_field( $t ) { return trim( (string) $t ); }
function sanitize_textarea_field( $t ) { return trim( (string) $t ); }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function get_option( $k, $def = null ) { return $def; }
function get_transient( $k ) { return false; }
function set_transient( $k, $v, $t = 0 ) { return true; }
function delete_transient( $k ) { return true; }
function muyu_get_current_country_from_subdomain() { return 'AR'; }
function muyu_get_countries_data() {
    return [
        'AR' => [ 'name' => 'Argentina', 'host' => 'muyunicos.com',    'tz' => 'America/Argentina/Buenos_Aires' ],
        'BR' => [ 'name' => 'Brasil',    'host' => 'br.muyunicos.com', 'tz' => 'America/Sao_Paulo' ],
    ];
}
function wp_timezone() { return new DateTimeZone( 'America/Argentina/Buenos_Aires' ); }
function wp_next_scheduled( $h ) { return false; }
function wp_schedule_single_event( $t, $h, $a = [] ) { return true; }
function wp_clear_scheduled_hook( $h ) { return true; }
function has_action( $h ) { return false; }
function do_action( $h ) {}

/** Escape con anotación: el arnés verifica que cada campo use la función de siempre. */
function mu_esc( $fn, $valor ) {
    $GLOBALS['mu_esc_calls'][ $fn ] = ( $GLOBALS['mu_esc_calls'][ $fn ] ?? 0 ) + 1;

    // Con los marcadores activos el valor sale envuelto: así el arnés comprueba QUÉ
    // función escapó cada campo (título → wp_kses_post, descripción → esc_html…).
    if ( ! empty( $GLOBALS['mu_esc_marcar'] ) ) {
        return '{{' . $fn . '}}' . (string) $valor . '{{/' . $fn . '}}';
    }

    return (string) $valor;
}
function esc_html( $t ) { return mu_esc( 'esc_html', $t ); }
function esc_attr( $t ) { return mu_esc( 'esc_attr', $t ); }
function esc_url( $t ) { return mu_esc( 'esc_url', $t ); }
function esc_url_raw( $t ) { return (string) $t; }
function esc_textarea( $t ) { return (string) $t; }
function wp_kses_post( $t ) { return mu_esc( 'wp_kses_post', $t ); }
function esc_html_e( $t ) { echo $t; }
function esc_attr_e( $t ) { echo $t; }
function checked( $a, $b = true, $echo = true ) { return ''; }

/** Instante de una fecha/hora local en un huso EXPLÍCITO (el huso por defecto no influye). */
function ts( $iso, $tz ) {
    return ( new DateTimeImmutable( $iso, new DateTimeZone( $tz ) ) )->getTimestamp();
}

$AR = 'America/Argentina/Buenos_Aires';

// El repositorio REAL de iconos (array puro): el SVG del CTA debe ser el de producción.
require __DIR__ . '/../../../inc/icons.php';

// El módulo real: de acá salen mu_hero_slide_render() y mu_hero_banner_field().
require __DIR__ . '/../../../inc/hero-banners.php';

/* --- Normalización, DOM y helpers ----------------------------------------- */
function mu_norm( $html ) {
    $html = str_replace( [ "\r\n", "\r" ], "\n", (string) $html );
    $html = preg_replace( '/>\s+</', '><', $html );   // espacios ENTRE etiquetas
    $html = preg_replace( '/\s+/', ' ', $html );      // corridas de espacios
    return trim( (string) $html );
}

/** DOMDocument de un fragmento HTML (UTF-8 explícito). */
function mu_dom( $html ) {
    $doc = new DOMDocument();
    libxml_use_internal_errors( true );
    $doc->loadHTML( '<?xml encoding="utf-8" ?>' . (string) $html );
    libxml_clear_errors();

    return $doc;
}

/** Primer nodo etiqueta+clase (clase vacía = por etiqueta). */
function mu_nodo( DOMDocument $doc, $tag, $clase = '' ) {
    $xpath = new DOMXPath( $doc );
    $query = '' === $clase
        ? "//{$tag}"
        : "//{$tag}[contains(concat(' ', normalize-space(@class), ' '), ' {$clase} ')]";
    $nodos = $xpath->query( $query );

    return ( $nodos && $nodos->length ) ? $nodos->item( 0 ) : null;
}

/** HTML completo de un nodo. */
function mu_html_nodo( $nodo ) {
    if ( ! $nodo instanceof DOMNode ) {
        return '';
    }

    return (string) $nodo->ownerDocument->saveHTML( $nodo );
}

/** Contenido interno de un nodo (para eyebrow y textos de CTA). */
function mu_inner( $nodo ) {
    if ( ! $nodo instanceof DOMNode ) {
        return '';
    }

    $out = '';
    foreach ( $nodo->childNodes as $hijo ) {
        $out .= $nodo->ownerDocument->saveHTML( $hijo );
    }

    return $out;
}

/** Valor de un atributo de un nodo. */
function mu_attr( $nodo, $attr ) {
    return ( $nodo instanceof DOMElement ) ? (string) $nodo->getAttribute( $attr ) : '';
}

/* --- Fixture y datos de producción ---------------------------------------- */
$ruta_fixture = __DIR__ . '/fixtures/hero-slide-produccion-ar.html';

if ( ! file_exists( $ruta_fixture ) ) {
    echo "FAIL No se encontró el fixture de producción (harness/fixtures/).\n== 0 OK / 1 FAIL ==\n";
    exit( 1 );
}

$doc_fixture = mu_dom( file_get_contents( $ruta_fixture ) );
$slide_fix   = mu_nodo( $doc_fixture, 'div', 'mu-hero-promo-slide' );

// Los MISMOS datos del banner que producción sirvió al capturar el fixture
// (transcritos del propio fixture: título, descripción, eyebrow, imagen, CTAs, badge).
$promo_ar = [
    'id'                   => 'cotizador',
    'titulo'               => 'Calculá el precio de tu pedido',
    'descripcion'          => 'Conocé nuestros precios de etiquetas adhesivas para tu emprendimiento o evento.',
    'eyebrow'              => 'Cotizador Online',
    'imagen'               => '/wp-content/uploads/2026/07/cotizador2.webp',
    'cta_texto'            => 'Ver más',
    'cta_url'              => '/emprendimientos/',
    'cta_secundario_texto' => 'Cotizar',
    'cta_secundario_url'   => '/cotizador/',
    'show_free_badge'      => false,
];

$render     = mu_hero_slide_render( $promo_ar, 'es', [ 'index' => 0, 'first' => true ] );
$doc_render = mu_dom( $render );

echo "--- 1. Igualdad con el fixture de producción (front intacto) ---\n";
same(
    'El slide renderizado coincide con producción (normalizado, ambos lados como DOM)',
    mu_norm( mu_html_nodo( $slide_fix ) ),
    mu_norm( mu_html_nodo( mu_nodo( $doc_render, 'div', 'mu-hero-promo-slide' ) ) )
);
// El icono se compara además sobre el HTML CRUDO del renderer (sin pasar por libxml,
// que baja a minúsculas los nombres de atributo: viewBox → viewbox).
same(
    'El SVG del CTA sale con su viewBox tal cual (HTML crudo)',
    1,
    substr_count( $render, '<svg class="mu-icon-svg muy-svg" viewBox="0 0 24 24">' )
);

foreach ( [ [ 'h1', 'mu-hero-promo-title' ], [ 'p', 'mu-hero-promo-desc' ] ] as $par ) {
    same(
        "Nodo <{$par[0]} class=\"{$par[1]}\"> byte a byte",
        mu_html_nodo( mu_nodo( $doc_fixture, $par[0], $par[1] ) ),
        mu_html_nodo( mu_nodo( $doc_render, $par[0], $par[1] ) )
    );
}

same(
    'src de la imagen byte a byte',
    mu_attr( mu_nodo( $doc_fixture, 'img' ), 'src' ),
    mu_attr( mu_nodo( $doc_render, 'img' ), 'src' )
);
same(
    'alt de la imagen byte a byte (strip_tags del título)',
    mu_attr( mu_nodo( $doc_fixture, 'img' ), 'alt' ),
    mu_attr( mu_nodo( $doc_render, 'img' ), 'alt' )
);
same(
    'Atributos de la imagen (width/height/loading/fetchpriority/decoding)',
    mu_norm( mu_html_nodo( mu_nodo( $doc_fixture, 'img' ) ) ),
    mu_norm( mu_html_nodo( mu_nodo( $doc_render, 'img' ) ) )
);

$cta_fix = mu_nodo( $doc_fixture, 'a', 'mu-btn-primary' );
$cta_new = mu_nodo( $doc_render, 'a', 'mu-btn-primary' );
same( 'href del CTA principal byte a byte', mu_attr( $cta_fix, 'href' ), mu_attr( $cta_new, 'href' ) );
same( 'Texto del CTA principal byte a byte', mu_inner( mu_nodo( $doc_fixture, 'span' ) ), mu_inner( mu_nodo( $doc_render, 'span' ) ) );
same(
    'El icono del CTA es el mismo SVG',
    1,
    substr_count( mu_norm( $render ), '<svg class="mu-icon-svg muy-svg" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg>' )
);

$sec_fix = mu_nodo( $doc_fixture, 'a', 'mu-btn-outline' );
$sec_new = mu_nodo( $doc_render, 'a', 'mu-btn-outline' );
same( 'href del CTA secundario byte a byte', mu_attr( $sec_fix, 'href' ), mu_attr( $sec_new, 'href' ) );
same( 'Texto del CTA secundario (normalizado)', mu_norm( mu_inner( $sec_fix ) ), mu_norm( mu_inner( $sec_new ) ) );
same(
    'Contenido del eyebrow (normalizado)',
    mu_norm( mu_html_nodo( mu_nodo( $doc_fixture, 'div', 'mu-hero-promo-eyebrow' ) ) ),
    mu_norm( mu_html_nodo( mu_nodo( $doc_render, 'div', 'mu-hero-promo-eyebrow' ) ) )
);
same( 'Producción no tenía badge y el render tampoco lo emite', 0, substr_count( $render, 'mu-hero-promo-free-badge' ) );

echo "\n--- 2. Quién escapa cada campo (marcadores) ---\n";
$GLOBALS['mu_esc_marcar'] = true;
$marcado                  = mu_hero_slide_render( $promo_ar, 'es', [ 'index' => 0, 'first' => true ] );
$GLOBALS['mu_esc_marcar'] = false;

check(
    'El título pasa por wp_kses_post (HTML permitido)',
    false !== strpos( $marcado, '<h1 class="mu-hero-promo-title">{{wp_kses_post}}Calculá el precio de tu pedido{{/wp_kses_post}}</h1>' )
);
check(
    'La descripción pasa por esc_html',
    false !== strpos( $marcado, '<p class="mu-hero-promo-desc">{{esc_html}}Conocé nuestros precios de etiquetas adhesivas para tu emprendimiento o evento.{{/esc_html}}</p>' )
);
check( 'El eyebrow pasa por esc_html', false !== strpos( $marcado, '{{esc_html}}Cotizador Online{{/esc_html}}' ) );
check( 'El alt pasa por esc_attr', false !== strpos( $marcado, 'alt="{{esc_attr}}Calculá el precio de tu pedido{{/esc_attr}}"' ) );
check( 'El src pasa por esc_url', false !== strpos( $marcado, 'src="{{esc_url}}/wp-content/uploads/2026/07/cotizador2.webp{{/esc_url}}"' ) );
check( 'El href del CTA principal pasa por esc_url', false !== strpos( $marcado, 'href="{{esc_url}}/emprendimientos/{{/esc_url}}"' ) );
check( 'El texto del CTA principal pasa por esc_html', false !== strpos( $marcado, '<span>{{esc_html}}Ver más{{/esc_html}}</span>' ) );
check( 'El texto del CTA secundario pasa por esc_html', false !== strpos( $marcado, '{{esc_html}}Cotizar{{/esc_html}}' ) );
same( 'El texto del badge no se escapa si no se muestra', 0, substr_count( $marcado, '<strong>¡Gratis!</strong>' ) );

echo "\n--- 3. first, badge e idioma ---\n";
$no_primero = mu_hero_slide_render( $promo_ar, 'es', [ 'index' => 3, 'first' => false ] );
same( 'Sin first no lleva la clase active', 0, substr_count( $no_primero, ' active' ) );
check( 'Sin first la imagen carga en lazy', false !== strpos( $no_primero, 'loading="lazy"' ) );
same( 'Sin first no hay fetchpriority', 0, substr_count( $no_primero, 'fetchpriority' ) );
same( 'El índice sale en data-slide-index', 1, substr_count( $no_primero, 'data-slide-index="3"' ) );

$con_badge = $promo_ar;
$con_badge['show_free_badge'] = true;
$badge = mu_hero_slide_render( $con_badge, 'es' );
check( 'Badge activo sin datos propios: usa la URL por defecto', false !== strpos( $badge, 'href="/tienda/digital/?min_price=0&max_price=0"' ) );
check( 'Y el texto por defecto del badge', false !== strpos( $badge, '<strong>¡Gratis!</strong><span>Envíos digitales</span>' ) );

$badge_propio = $con_badge;
$badge_propio['free_badge_text'] = '<strong>Envío gratis</strong>';
$badge_propio['free_badge_url']  = '/envios/';
$bd = mu_hero_slide_render( $badge_propio, 'es' );
check( 'Con datos propios el badge los usa', false !== strpos( $bd, 'href="/envios/"' ) && false !== strpos( $bd, '<strong>Envío gratis</strong>' ) );

$pt = $promo_ar;
$pt['titulo_pt'] = 'Calcule o preço do seu pedido';
same( 'En pt usa la traducción propia', 1, substr_count( mu_hero_slide_render( $pt, 'pt' ), '<h1 class="mu-hero-promo-title">Calcule o preço do seu pedido</h1>' ) );
same( 'En es sigue usando el español', 1, substr_count( mu_hero_slide_render( $pt, 'es' ), '<h1 class="mu-hero-promo-title">Calculá el precio de tu pedido</h1>' ) );
same( 'Un idioma sin traducción cae al español', 1, substr_count( mu_hero_slide_render( $promo_ar, 'en' ), '<h1 class="mu-hero-promo-title">Calculá el precio de tu pedido</h1>' ) );
same( 'Y el alt de la imagen sigue al título del idioma activo', 1, substr_count( mu_hero_slide_render( $pt, 'pt' ), 'alt="Calcule o preço do seu pedido"' ) );

same( 'Banner no-array no rompe', 1, substr_count( mu_hero_slide_render( 'x', 'es' ), 'mu-hero-promo-slide' ) );
same( 'Sin eyebrow no se emite el bloque', 0, substr_count( mu_hero_slide_render( [ 'titulo' => 'x' ], 'es' ), 'mu-hero-promo-eyebrow' ) );
same(
    'Sin CTA secundario completo no se emite',
    0,
    substr_count( mu_hero_slide_render( [ 'titulo' => 'x', 'cta_secundario_texto' => 'Cotizar' ], 'es' ), 'mu-btn-outline' )
);

echo "\n--- 4. Modo lienzo (mu): marcas y nodos siempre presentes ---\n";
$lienzo = mu_hero_slide_render( [ 'titulo' => 'Calculá', 'descripcion' => 'D', 'cta_texto' => 'Ver' ], 'es', [ 'mu' => true ] );

foreach ( [ 'imagen', 'eyebrow', 'titulo', 'descripcion', 'cta', 'cta_texto', 'cta_secundario', 'cta_secundario_texto', 'badge', 'badge_text' ] as $marca ) {
    same( "Marca data-mu=\"{$marca}\" presente una vez", 1, substr_count( $lienzo, 'data-mu="' . $marca . '"' ) );
}
same( 'Con mu, el eyebrow existe aunque esté vacío', 1, substr_count( $lienzo, 'data-mu="eyebrow"' ) );
same( 'Con mu, el CTA secundario existe aunque esté vacío', 1, substr_count( $lienzo, 'data-mu="cta_secundario"' ) );
same( 'Con mu, el badge existe aunque la casilla esté apagada', 1, substr_count( $lienzo, 'data-mu="badge"' ) );
same( 'Con mu, el texto del badge sale con los defaults', 1, substr_count( $lienzo, '<strong>¡Gratis!</strong><span>Envíos digitales</span>' ) );
same( 'El HTML del front NO lleva marcas data-mu', 0, substr_count( $render, 'data-mu=' ) );
same( 'Sin mu, el eyebrow vacío sigue sin emitirse', 0, substr_count( mu_hero_slide_render( [ 'titulo' => 'x' ], 'es' ), 'mu-hero-promo-eyebrow' ) );

echo "\n--- 5. El lienzo dentro de la fila del panel ---\n";
ob_start();
mu_hero_banners_render_row( 0, [
    'id'     => 'cotizador',
    'titulo' => 'Calculá el precio de tu pedido',
    'inicio' => '01012026',
    'fin'    => '31122026',
    'paises' => [ 'AR' ],
] );
$fila = ob_get_clean();

same( 'La fila trae el lienzo', 1, substr_count( $fila, 'data-mu-canvas' ) );
same( 'Con el slide renderizado dentro', 1, substr_count( $fila, 'data-slide-index="0"' ) );
same( 'Y los 10 nodos marcados para el JS', 10, substr_count( $fila, 'data-mu="' ) );
same( 'El lienzo arranca en escritorio (1280)', 1, substr_count( $fila, 'data-mu-canvas data-ancho="1280"' ) );
same( 'Botón de escritorio (activo por defecto)', 1, substr_count( $fila, 'class="mu-hero-banner-canvas-w is-active"' ) );
same( 'Botón de móvil', 1, substr_count( $fila, 'class="mu-hero-banner-canvas-w"' ) );
check( 'Declara el idioma activo', false !== strpos( $fila, 'data-idioma="es"' ) );
check( 'El envoltorio es el slider del componente (para sus estilos)', false !== strpos( $fila, 'mu-hero-promo-slider' ) );
same( 'El lienzo es sólo vista: no agrega campos al POST', 0, substr_count( $fila, 'name="mu_banner[0][canvas' ) );
same( 'El CTA del lienzo usa el mismo SVG del front', 1, substr_count( $fila, '<svg class="mu-icon-svg muy-svg" viewBox="0 0 24 24">' ) );

echo "\n--- 6. Estado del banner en el resumen ---\n";
$junio = ts( '2026-06-15 12:00:00', $AR );

// Ventana abierta y cerrada a la vez (dos países): estado, fuera de ventana y próximo cambio.
$mixto = [
    'titulo' => 'Mixto',
    'paises' => [ 'AR', 'BR' ],
    'inicio' => '01062026',
    'fin'    => '30062026',
    'fechas' => [ 'BR' => [ 'inicio' => '01072026', 'fin' => '31072026' ] ],
];
$estado_mixto = mu_hero_banner_estado( $mixto, $junio );
same( 'Con un país dentro de su ventana el banner está activo', 'is-activo', $estado_mixto['clase'] );
check( 'El resumen nombra los países en los que se ve', false !== strpos( $estado_mixto['texto'], 'Se ve en AR' ) );
check( 'Y los que quedaron fuera de ventana', false !== strpos( $estado_mixto['texto'], 'fuera de ventana: BR' ) );
check( 'Y cuándo cambia (el próximo borde)', false !== strpos( $estado_mixto['texto'], 'próximo cambio: 01/07/2026 00:00' ) );

// Sin ninguna ventana: activo y sin bordes que anunciar.
$siempre = mu_hero_banner_estado( [ 'titulo' => 'Siempre', 'paises' => [ 'AR' ] ], $junio );
same( 'Sin ventana: activo', 'is-activo', $siempre['clase'] );
same( 'Y sin próximo cambio que anunciar', 'Se ve en AR', $siempre['texto'] );

// Ventana futura: programado.
$futuro = mu_hero_banner_estado( [ 'titulo' => 'Futuro', 'paises' => [ 'AR' ], 'inicio' => '01082026', 'fin' => '31082026' ], $junio );
same( 'Con la ventana por delante: programado', 'is-programado', $futuro['clase'] );
check( 'Y dice cuándo entra', false !== strpos( $futuro['texto'], 'Entra el 01/08/2026 00:00' ) );

// Ventana pasada (fija): no se ve ni va a verse.
$pasado = mu_hero_banner_estado( [ 'titulo' => 'Pasado', 'paises' => [ 'AR' ], 'inicio' => '01012026', 'fin' => '31012026' ], $junio );
same( 'Con la ventana vencida: inactivo', 'is-inactivo', $pasado['clase'] );
same( 'Sin países tildados ya no cae en "inactivo": tiene su propio estado (014)', 'is-sin-paises', mu_hero_banner_estado( [ 'titulo' => 'X', 'paises' => [] ], $junio )['clase'] );
check( 'Banner no-array no rompe', is_array( mu_hero_banner_estado( 'x', $junio ) ) );

echo "\n--- 7. Excepciones de vigencia y estado «sin países» (014) ---\n";
same( 'Sin excepciones: lista vacía', [], mu_hero_banner_excepciones( [ 'id' => 'x', 'paises' => [ 'AR' ] ] ) );
same( 'Una excepción', [ 'BR' ], mu_hero_banner_excepciones( [ 'fechas' => [ 'BR' => [ 'inicio' => '01062026', 'fin' => '' ] ] ] ) );
same( 'Varias, en el orden canónico de los países', [ 'AR', 'BR' ], mu_hero_banner_excepciones( [ 'fechas' => [ 'BR' => [ 'inicio' => '01062026' ], 'AR' => [ 'fin' => '30062026' ] ] ] ) );
same( 'Sólo el flag anual también es excepción', [ 'BR' ], mu_hero_banner_excepciones( [ 'fechas' => [ 'BR' => [ 'anual' => '1' ] ] ] ) );
same( 'País inexistente se ignora', [], mu_hero_banner_excepciones( [ 'fechas' => [ 'XX' => [ 'inicio' => '01062026' ] ] ] ) );
same( 'Entrada vacía no es excepción', [], mu_hero_banner_excepciones( [ 'fechas' => [ 'BR' => [ 'inicio' => '', 'fin' => '' ] ] ] ) );
same( "'fechas' corrupto no rompe", [], mu_hero_banner_excepciones( [ 'fechas' => 'x' ] ) );
check( 'Banner no-array no rompe', is_array( mu_hero_banner_excepciones( 'x' ) ) );
check( 'pais_es_excepcion(): sí', true === mu_hero_banner_pais_es_excepcion( [ 'fechas' => [ 'BR' => [ 'inicio' => '01062026' ] ] ], 'BR' ) );
check( 'pais_es_excepcion(): no', false === mu_hero_banner_pais_es_excepcion( [ 'fechas' => [ 'BR' => [ 'inicio' => '01062026' ] ] ], 'MX' ) );

$sin_paises = mu_hero_banner_estado( [ 'titulo' => 'X', 'paises' => [] ], $junio );
same( 'Sin países tildados: estado propio', 'is-sin-paises', $sin_paises['clase'] );
check( 'Y el texto explica el motivo', false !== strpos( $sin_paises['texto'], 'no se verá en el sitio' ) );
same(
    'Un país tildado + ventana vencida sigue siendo "inactivo" (no "sin países")',
    'is-inactivo',
    mu_hero_banner_estado( [ 'paises' => [ 'AR' ], 'inicio' => '01012026', 'fin' => '31012026' ], $junio )['clase']
);

echo "\n--- 8. El resumen en el marcado de la fila ---\n";
same( 'La fila trae el resumen', 1, substr_count( $fila, 'mu-hero-banner-row__summary' ) );
same( 'Y el cuerpo plegable', 1, substr_count( $fila, '<div class="mu-hero-banner-row__body"' ) );
same( 'Con el botón de plegar', 1, substr_count( $fila, 'data-role="plegar"' ) );
same( 'Y el estado calculado', 1, substr_count( $fila, 'mu-hero-banner-row__estado is-' ) );
same( 'Acción Duplicar', 1, substr_count( $fila, 'data-role="duplicar"' ) );
same( 'Acciones de orden', 2, substr_count( $fila, 'data-role="subir"' ) + substr_count( $fila, 'data-role="bajar"' ) );
same( 'El botón Eliminar vive en el resumen (no quedó duplicado)', 1, substr_count( $fila, 'mu-hero-banner-row__remove' ) );
same( 'Y hay un solo flag de borrado', 1, substr_count( $fila, 'mu-hero-banner-row__delete-flag' ) );

echo "\n--- 8. Chips de país (el checkbox ES el chip) ---\n";
same( 'Un solo contenedor de chips', 1, substr_count( $fila, 'class="mu-hero-banner-chips"' ) );
same( 'Un chip por país del sitio', 2, substr_count( $fila, '<label class="mu-hero-banner-chip' ) );
same( 'El país tildado sale activo', 1, substr_count( $fila, 'class="mu-hero-banner-chip is-active"' ) );
check( 'El chip lleva el checkbox de países del POST', (bool) preg_match( '/class="mu-hero-banner-chip[^"]*"[^>]*>\s*<input type="checkbox" name="mu_banner\[0\]\[paises\]\[\]"/', $fila ) );
check( 'Y el nombre del país en el title', false !== strpos( $fila, 'title="Argentina"' ) );
same( 'El dropdown «Países» se retiró', 0, substr_count( $fila, 'mu-hero-banner-paises-dropdown' ) );
same( 'El botón «Países» se retiró', 0, substr_count( $fila, 'mu-hero-banner-paises-btn' ) );

echo "\n--- 9. Tabla de vigencia: todos los ámbitos a la vez ---\n";
same( 'Las píldoras de ámbito se retiraron', 0, substr_count( $fila, 'mu-hero-banner-paises-pills' ) );
same( 'Una fila por ámbito (Global + los 2 países del arnés)', 3, substr_count( $fila, '<tr class="mu-hero-banner-fechas-block' ) );
same( 'La fila de Global va primero', 1, substr_count( $fila, 'data-pais=""' ) );
same( 'Cada fila tiene su botón de limpiar', 3, substr_count( $fila, 'data-role="limpiar-ventana"' ) );
same( 'Cada fila declara si el país está tildado', 3, substr_count( $fila, 'data-tildado="' ) );
check( 'La columna «Se ve» muestra el huso del ámbito', false !== strpos( $fila, 'America/Argentina/Buenos_Aires' ) );
check( 'Y la columna tiene su aviso por fila', false !== strpos( $fila, 'mu-hero-banner-fechas-warning' ) );
same( 'Cada ámbito conserva sus 2 campos de fecha', 6, substr_count( $fila, 'data-fecha="' ) );

echo "\n--- 10. Densidad: grupos del contenido y huso del sitio (014) ---\n";
check( 'Los grupos están en el orden del banner (Texto → Botones → Imagen)',
    strpos( $fila, '>Texto<' ) < strpos( $fila, '>Botones<' ) && strpos( $fila, '>Botones<' ) < strpos( $fila, '>Imagen<' ) );
check( 'Dentro de «Texto» el eyebrow va antes que el título',
    strpos( $fila, 'data-field="eyebrow"' ) < strpos( $fila, 'data-field="titulo"' ) );
check( 'Y el título antes que la descripción',
    strpos( $fila, 'data-field="titulo"' ) < strpos( $fila, 'data-field="descripcion"' ) );
check( 'La imagen quedó en su propio grupo', false !== strpos( $fila, '>Imagen<' ) );
same( 'Tres grupos por idioma × 3 idiomas', 9, substr_count( $fila, 'mu-hero-banner-group-title' ) );
check( 'La fila de Global dice «huso del sitio» en vez de un offset', false !== strpos( $fila, 'huso del sitio' ) );
same( 'Y ya no muestra el offset crudo', 0, substr_count( $fila, '(-03:00)' ) );
check( 'Los ajustes de la fila apuntan al grupo Botones', false !== strpos( $fila, 'en el grupo «Botones»' ) );

echo "\n== {$GLOBALS['mu_ok']} OK / {$GLOBALS['mu_fail']} FAIL ==\n";
exit( $GLOBALS['mu_fail'] > 0 ? 1 : 0 );

