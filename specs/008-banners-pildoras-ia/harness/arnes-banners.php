<?php
/**
 * Arnés de hero banners: estado automático de idiomas + marca de origen.
 * Ejecuta el módulo REAL (inc/hero-banners.php copiado acá) con WordPress simulado:
 * sin WordPress, sin base de datos y sin red.
 */
define( 'ABSPATH', __DIR__ . '/' );

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
function is_admin() { return false; }
function add_action() {}
function __( $t ) { return $t; }
function esc_html__( $t ) { return $t; }
function esc_attr__( $t ) { return $t; }
function sanitize_title( $t ) { return trim( strtolower( preg_replace( '/[^a-z0-9]+/i', '-', (string) $t ) ), '-' ); }
function sanitize_text_field( $t ) { return trim( (string) $t ); }
function sanitize_textarea_field( $t ) { return trim( (string) $t ); }
function wp_kses_post( $t ) { return (string) $t; }
function esc_url_raw( $t ) { return (string) $t; }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function get_option( $k, $def = null ) { return $def; }
function muyu_get_countries_data() {
    return [ 'AR' => [ 'name' => 'Argentina' ], 'BR' => [ 'name' => 'Brasil' ], 'MX' => [ 'name' => 'México' ] ];
}

/* Marca de origen: misma regla que inc/ai-translate.php (su arnés vive en la feature 006). */
function mu_ai_hash( $text ) { return md5( (string) $text ); }
function mu_ai_is_stale( $source_es, $traduccion, $hash ) {
    if ( '' === trim( (string) $traduccion ) || '' === trim( (string) $hash ) ) { return false; }
    return $hash !== mu_ai_hash( $source_es );
}

require __DIR__ . '/../../../inc/hero-banners.php';

/* --- Utilidad: el español que alimenta la traducción --------------------- */
function fuente( $titulo = '', $desc = '', $eyebrow = '', $cta = '', $cta2 = '', $badge = '' ) {
    return implode( "\n", [ $titulo, $desc, $eyebrow, $cta, $cta2, $badge ] );
}

echo "--- 1. Estado del idioma (automático, sin casilla) ---\n";
same( 'PT sin contenido: no tiene traducción', false, mu_hero_banner_lang_has_translation( [ 'titulo_pt' => '' ], 'pt' ) );
same( 'PT con un solo campo propio: tiene traducción', true, mu_hero_banner_lang_has_translation( [ 'cta_texto_pt' => 'Ver' ], 'pt' ) );
same( 'EN con descripción propia: tiene traducción', true, mu_hero_banner_lang_has_translation( [ 'descripcion_en' => 'Hi' ], 'en' ) );
same( 'ES nunca cuenta como idioma traducido', false, mu_hero_banner_lang_has_translation( [ 'titulo' => 'x' ], 'es' ) );
same( 'Banner no-array no rompe', false, mu_hero_banner_lang_has_translation( 'x', 'pt' ) );

echo "\n--- 2. Front: qué valor se muestra ---\n";
$promo = [ 'titulo' => 'Hola', 'titulo_pt' => 'Olá', 'descripcion' => 'Base', 'descripcion_pt' => '' ];
same( 'PT con valor propio se usa', 'Olá', mu_hero_banner_field( $promo, 'titulo', 'pt' ) );
same( 'PT vacío cae al español', 'Base', mu_hero_banner_field( $promo, 'descripcion', 'pt' ) );
same( 'ES usa la base', 'Hola', mu_hero_banner_field( $promo, 'titulo', 'es' ) );
same( 'EN sin contenido cae al español', 'Hola', mu_hero_banner_field( $promo, 'titulo', 'en' ) );
same( 'Campo inexistente devuelve vacío (sin avisos)', '', mu_hero_banner_field( $promo, 'nada', 'pt' ) );
$vieja = [ 'titulo' => 'Hola', 'titulo_pt' => 'Olá', 'traducir_pt' => false ];
same(
    'Contenido PT con la casilla vieja apagada: ahora se muestra [decisión "automático total"]',
    'Olá',
    mu_hero_banner_field( $vieja, 'titulo', 'pt' )
);

echo "\n--- 3. Guardado: sobrescritura dispersa + estado derivado ---\n";
$clean = mu_hero_banners_sanitize_one( [
    'id'         => 'demo',
    'titulo'     => 'Hola',
    'titulo_pt'  => 'Olá',
    'titulo_en'  => 'Hola',
    'cta_url'    => '/tienda/',
    'cta_url_pt' => '/tienda/',
    'paises'     => [ 'AR', 'XX' ],
] );
same( 'PT propio se guarda', 'Olá', $clean['titulo_pt'] );
same( 'EN igual al español no se guarda', '', $clean['titulo_en'] );
same( 'URL igual al español no se guarda', '', $clean['cta_url_pt'] );
same( 'traducir_pt se deriva del contenido', true, $clean['traducir_pt'] );
same( 'traducir_en se deriva del contenido', false, $clean['traducir_en'] );
same( 'País inválido se descarta', [ 'AR' ], $clean['paises'] );

echo "\n--- 4. Marca de origen: cuándo se recalcula y cuándo se conserva ---\n";
$guardado = [
    'id'              => 'demo',
    'titulo'          => 'Hola mundo',
    'titulo_pt'       => 'Olá mundo',
    'traducir_pt_hash' => mu_ai_hash( fuente( 'Hola mundo' ) ),
];

// (a) Nada cambió en la traducción: se conserva la marca.
$a = mu_hero_banner_with_origin_marks( $guardado, $guardado );
same( '(a) sin cambios en la traducción: marca conservada', $guardado['traducir_pt_hash'], $a['traducir_pt_hash'] );

// (b) El español cambió y la traducción quedó igual: la marca vieja se conserva
//     → el aviso de "desactualizada" APARECE (regresión que se corrigió).
$es_movido = $guardado;
$es_movido['titulo'] = 'Hola mundo nuevo';
$b = mu_hero_banner_with_origin_marks( $es_movido, $guardado );
same( '(b) español nuevo + traducción vieja: la marca NO se recalcula', $guardado['traducir_pt_hash'], $b['traducir_pt_hash'] );
check( '(b) entonces el aviso de desactualizada aparece', true === mu_hero_banner_lang_is_stale( $b, 'pt' ) );

// (c) La traducción cambió: se recalcula la marca y ya no está desactualizada.
$pt_movido = $es_movido;
$pt_movido['titulo_pt'] = 'Olá mundo novo';
$c = mu_hero_banner_with_origin_marks( $pt_movido, $guardado );
same( '(c) traducción nueva: la marca se recalcula', mu_hero_banner_lang_hash( $pt_movido ), $c['traducir_pt_hash'] );
check( '(c) y no queda desactualizada', false === mu_hero_banner_lang_is_stale( $c, 'pt' ) );

// (d) Se borra la traducción: la marca se limpia.
$sin_pt = $es_movido;
$sin_pt['titulo_pt'] = '';
$d = mu_hero_banner_with_origin_marks( $sin_pt, $guardado );
same( '(d) sin traducción no queda marca', '', $d['traducir_pt_hash'] );

// (e) Banner nuevo: la marca sale del español actual.
$e = mu_hero_banner_with_origin_marks( $pt_movido, null );
same( '(e) banner nuevo: marca del español actual', mu_hero_banner_lang_hash( $pt_movido ), $e['traducir_pt_hash'] );

// (f) Traducción manual (nunca pasó por la IA): sin marca y sin aviso.
check( '(f) traducción manual sin marca: no hay aviso', false === mu_hero_banner_lang_is_stale( [ 'titulo' => 'Hola', 'titulo_pt' => 'Olá' ], 'pt' ) );

// (g) Cambiar solo una URL no invalida la traducción de los textos.
$url_movida = $guardado;
$url_movida['cta_url'] = '/otra/';
$url_marks = mu_hero_banner_with_origin_marks( $url_movida, $guardado );
check( '(g) mover una URL no marca la traducción como desactualizada', false === mu_hero_banner_lang_is_stale( $url_marks, 'pt' ) );
same( '(g) y la marca sigue intacta', $guardado['traducir_pt_hash'], $url_marks['traducir_pt_hash'] );

// (h) Dos idiomas en el mismo banner: cada uno con su marca.
$dos = [
    'id' => 'demo', 'titulo' => 'Hola mundo', 'titulo_pt' => 'Olá mundo', 'titulo_en' => 'Hello world',
];
$h = mu_hero_banner_with_origin_marks( $dos, null );
check( '(h) PT y EN tienen marca', '' !== $h['traducir_pt_hash'] && '' !== $h['traducir_en_hash'] );
check( '(h) las marcas coinciden (mismo español de origen)', $h['traducir_pt_hash'] === $h['traducir_en_hash'] );
check( '(h) sin desactualización de entrada', false === mu_hero_banner_lang_is_stale( $h, 'pt' ) && false === mu_hero_banner_lang_is_stale( $h, 'en' ) );

echo "\n== {$GLOBALS['mu_ok']} OK / {$GLOBALS['mu_fail']} FAIL ==\n";
exit( $GLOBALS['mu_fail'] > 0 ? 1 : 0 );

