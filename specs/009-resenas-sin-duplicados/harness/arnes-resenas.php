<?php
/**
 * Arnés de deduplicación de reseñas: ejecuta el módulo REAL
 * inc/reviews-identity.php con lo mínimo de WordPress simulado.
 */
define( 'ABSPATH', __DIR__ . '/' );

function wp_strip_all_tags( $t ) { return strip_tags( (string) $t ); }
function remove_accents( $t ) {
    $map = [ 'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N' ];
    return strtr( (string) $t, $map );
}

$GLOBALS['ok'] = 0;
$GLOBALS['fail'] = 0;

function same( $label, $expected, $actual ) {
    if ( $expected === $actual ) { $GLOBALS['ok']++; echo "OK   {$label}\n"; }
    else { $GLOBALS['fail']++; echo "FAIL {$label}  esperado=" . var_export( $expected, true ) . " obtenido=" . var_export( $actual, true ) . "\n"; }
}
function check( $label, $cond ) { same( $label, true, (bool) $cond ); }

require __DIR__ . '/../../../inc/reviews-identity.php';

echo "--- 1. Mismo autor ---\n";
$j  = [ 'author_name' => 'Jonatan' ];
$js = [ 'author_name' => 'Jonatan S.' ];
$ja = [ 'author_name' => 'Jonatán' ];
$am = [ 'author_name' => 'Ana María' ];
$an = [ 'author_name' => 'Ana' ];
$ma = [ 'author_name' => 'María' ];
$mo = [ 'author_name' => 'Mario' ];

check( '"Jonatan" = "Jonatan S." (Google agrega el apellido)', mu_review_same_author( $j, $js ) );
check( '"Jonatán" = "jonatan" (acentos y mayúsculas)', mu_review_same_author( $ja, $j ) );
check( '"Ana" ≠ "Ana María": con menos de 4 letras no se compara por prefijo', ! mu_review_same_author( $an, $am ) );
check( '"María" = "María José" (prefijo con 4+ letras)', mu_review_same_author( $ma, [ 'author_name' => 'María José' ] ) );
check( '"Ana" ≠ "Ana" de otro campo vacío', ! mu_review_same_author( $an, [ 'author_name' => '' ] ) );
check( '"María" ≠ "Mario"', ! mu_review_same_author( $ma, $mo ) );
same( 'Nombre vacío no rompe', false, mu_review_same_author( [ 'author_name' => '' ], $j ) );

echo "\n--- 2. Huella de contenido ---\n";
$t1 = [ 'author_name' => 'Jonatan', 'text' => '¡Excelente atención, muy rápido!' ];
$t2 = [ 'author_name' => 'jonatan', 'text' => 'Excelente atencion muy rapido' ];
$t3 = [ 'author_name' => 'Jonatan', 'text' => 'Otro comentario distinto' ];
check( 'Mismo texto con acentos/puntuación → misma huella', mu_review_identity_key( $t1 ) === mu_review_identity_key( $t2 ) );
check( 'Texto distinto → huella distinta', mu_review_identity_key( $t1 ) !== mu_review_identity_key( $t3 ) );

echo "\n--- 3. Importación: duplicados y marcas ---\n";
$google = [
    [ 'author_name' => 'Jonatan S.', 'rating' => 5, 'time' => 1788393600, 'text' => 'Excelente', 'profile_photo_url' => 'g.jpg', 'author_url' => 'https://g/1' ],
    [ 'author_name' => 'Beto', 'rating' => 4, 'time' => 1788393600, 'text' => 'Bueno' ],
];

$r = mu_reviews_import_merge( [], $google );
same( 'Solo entran las de 5 estrellas', 1, $r['added'] );
same( 'La base queda con 1', 1, count( $r['db'] ) );
same( 'No hay marcas al importar sobre base vacía', 0, count( $r['flagged'] ) );

$r2 = mu_reviews_import_merge( $r['db'], $google );
same( 'Reimportar el mismo lote no agrega nada', 0, $r2['added'] );
same( 'Y cuenta la que ya estaba', 1, $r2['existed'] );

// Reseña cargada a mano (fecha = día a las 00:00) vs la de Google (timestamp real).
$manual = [
    [ 'author_name' => 'Jonatan', 'rating' => 5, 'time' => 1788364800, 'text' => 'Excelente',
      'purchase_photo_url' => '/foto.jpg', 'text_pt' => 'Excelente', 'text_pt_hash' => 'abc', 'hidden' => true ],
];
$r3 = mu_reviews_import_merge( $manual, $google );
same( 'Se agrega la de Google (no se detecta por fecha)', 1, $r3['added'] );
same( 'La copia vieja queda marcada', true, $r3['flagged'][0]['index'] === 0 );
same( 'La marcada conserva su contenido', 'Jonatan', $r3['db'][0]['author_name'] );
check( 'La vieja quedó con posible_duplicado', mu_review_is_flagged_duplicate( $r3['db'][0] ) );
same( 'La de Google se queda con la foto de compra', '/foto.jpg', $r3['db'][1]['purchase_photo_url'] );
same( 'Y con la traducción guardada', 'Excelente', $r3['db'][1]['text_pt'] );
same( 'Con su marca de origen', 'abc', $r3['db'][1]['text_pt_hash'] );
check( 'Pero NO hereda el hidden', empty( $r3['db'][1]['hidden'] ) );

// Otra persona con el mismo nombre pero sin relación: mismo criterio (se marca).
$otro = [ [ 'author_name' => 'Beto', 'rating' => 5, 'time' => 1788393600, 'text' => 'Otra cosa' ] ];
$r4 = mu_reviews_import_merge( $r['db'], $otro );
same( 'Autor distinto: se agrega sin marcas', 1, $r4['added'] );
same( 'Sin marcas nuevas', 0, count( $r4['flagged'] ) );

// Tope de reseñas.
$muchas = [];
for ( $i = 0; $i < 5; $i++ ) { $muchas[] = [ 'author_name' => "P{$i}", 'rating' => 5, 'time' => 1000 + $i, 'text' => "t{$i}" ]; }
$r5 = mu_reviews_import_merge( [], $muchas, 3 );
same( 'Respeta el tope de reseñas', 3, count( $r5['db'] ) );

echo "\n--- 4. Guardado manual: detección por nombre ---\n";
$hits = mu_reviews_find_same_author( [ [ 'author_name' => 'Jonatan S.', 'time' => 1 ] ], [ 'author_name' => 'Jonatan' ] );
same( 'Encuentra la reseña existente', [ 0 ], $hits );
same( 'Sin coincidencias devuelve vacío', [], mu_reviews_find_same_author( [ [ 'author_name' => 'Beto' ] ], [ 'author_name' => 'Ana' ] ) );

echo "\n--- 5. Llave de enlace (perfil del reseñador) ---\n";
same( 'Perfil de contribuciones → clave con el ID', 'google.com/maps/contrib/1234567890', mu_review_link_key( [ 'author_url' => 'https://www.google.com/maps/contrib/1234567890/reviews?hl=es' ] ) );
same( 'Enlace del lugar → sin clave (no sirve como identidad)', '', mu_review_link_key( [ 'author_url' => 'https://www.google.com/maps/place/Muy+Unicos' ] ) );
same( 'Enlace genérico de reseñas → sin clave', '', mu_review_link_key( [ 'author_url' => 'https://search.google.com/local/reviews?placeid=ChIJ18' ] ) );
same( 'Sin enlace → sin clave', '', mu_review_link_key( [] ) );
same( 'Usa profile_url si no hay author_url', 'google.com/maps/contrib/99', mu_review_link_key( [ 'profile_url' => 'https://google.com/maps/contrib/99' ] ) );

echo "\n--- 6. Mismo enlace: absorbe y elimina la manual ---\n";
$manual_link = [ [
    'author_name' => 'Jonatan', 'rating' => 5, 'time' => 1788364800, 'text' => 'Excelente',
    'author_url' => 'https://www.google.com/maps/contrib/4242/reviews', 'purchase_photo_url' => '/foto.jpg',
    'text_pt' => 'Excelente', 'text_pt_hash' => 'abc', 'hidden' => true,
] ];
$google_link = [ [
    'author_name' => 'Jonatan S.', 'rating' => 5, 'time' => 1788393600, 'text' => 'Excelente servicio',
    'author_url' => 'https://www.google.com/maps/contrib/4242/reviews', 'profile_photo_url' => 'g.jpg',
] ];

$r = mu_reviews_import_merge( $manual_link, $google_link );
same( 'Se agregó la de Google', 1, $r['added'] );
same( 'La manual se eliminó', 1, $r['deleted'] );
same( 'Queda una sola reseña', 1, count( $r['db'] ) );
same( 'La que queda es la de Google', 'Jonatan S.', $r['db'][0]['author_name'] );
same( 'Absorbió la foto de compra', '/foto.jpg', $r['db'][0]['purchase_photo_url'] );
same( 'Absorbió la traducción', 'Excelente', $r['db'][0]['text_pt'] );
same( 'Con su marca de origen', 'abc', $r['db'][0]['text_pt_hash'] );
check( 'Absorbió el hidden (es la misma reseña)', ! empty( $r['db'][0]['hidden'] ) );
same( 'No quedó marcada como duplicado', false, mu_review_is_flagged_duplicate( $r['db'][0] ) );
same( 'El registro guarda el nombre de la absorbida', 'Jonatan', $r['absorbed'][0]['name'] );

echo "\n--- 7. Guardas: link repetido y exclusiones ---\n";
$tres = [];
for ( $i = 0; $i < 3; $i++ ) {
    $tres[] = [ 'author_name' => "C{$i}", 'time' => 1000 + $i, 'text' => "t{$i}", 'author_url' => 'https://www.google.com/maps/contrib/777/reviews' ];
}
$uno = [ [ 'author_name' => 'Nuevo', 'rating' => 5, 'time' => 5000, 'text' => 'otra', 'author_url' => 'https://www.google.com/maps/contrib/777/reviews' ] ];

$r = mu_reviews_import_merge( $tres, $uno );
same( 'Link repetido en 3+: NO se borra nada', 0, $r['deleted'] );
same( 'Y la nueva se agrega igual', 1, $r['added'] );

$bloqueo = [ mu_review_link_key( $google_link[0] ) => time() ];
$r = mu_reviews_import_merge( $tres, $google_link, 200, $bloqueo );
same( 'Lo excluido por enlace no se reimporta', 0, $r['added'] );
same( 'Y se cuenta como excluida', 1, $r['skipped'] );

$r = mu_reviews_import_merge( $tres, $google_link, 200, [ mu_review_identity_key( $google_link[0] ) => time() ] );
same( 'La exclusión por huella también funciona', 0, $r['added'] );

echo "\n== {$GLOBALS['ok']} OK / {$GLOBALS['fail']} FAIL ==\n";
exit( $GLOBALS['fail'] > 0 ? 1 : 0 );
