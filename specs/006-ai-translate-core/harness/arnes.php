<?php
/**
 * Arnés del servicio de traducción con IA (feature 006 + variantes por persona).
 *
 * Ejecuta el módulo REAL (inc/ai-translate.php) con WordPress simulado: sin WordPress,
 * sin base de datos, sin red y sin clave real. Las solicitudes salientes se capturan y
 * se responden con un JSON armado a mano.
 *
 * Vive versionado acá (antes estaba sólo en %TEMP% y se perdió con la limpieza del
 * temporal). Uso, desde la raíz del repositorio:
 *
 *   php specs/006-ai-translate-core/harness/arnes.php
 *
 * @package GeneratePress_Child
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'MONTH_IN_SECONDS', 2592000 );
define( 'MU_GEMINI_API_KEY', 'clave-de-prueba' );

$GLOBALS['mu_store'] = [];   // opciones simuladas
$GLOBALS['mu_trans'] = [];   // transients simulados
$GLOBALS['mu_posts'] = [];   // solicitudes salientes capturadas
$GLOBALS['mu_ok']    = 0;
$GLOBALS['mu_fail']  = 0;

function ok( $label, $cond, $extra = '' ) {
    if ( $cond ) {
        $GLOBALS['mu_ok']++;
        echo "OK   {$label}\n";
    } else {
        $GLOBALS['mu_fail']++;
        echo "FAIL {$label}" . ( '' !== $extra ? "  [{$extra}]" : '' ) . "\n";
    }
}

function same( $label, $esperado, $obtenido ) {
    ok( $label, $esperado === $obtenido, 'esperado=' . var_export( $esperado, true ) . ' obtenido=' . var_export( $obtenido, true ) );
}

/* --- WordPress simulado --------------------------------------------------- */
function add_action() {}
function add_filter() {}
function apply_filters( $t, $v ) { return $v; }
function __( $t ) { return $t; }
function _n( $s, $p, $n ) { return 1 === (int) $n ? $s : $p; }
function esc_html__( $t ) { return $t; }
function esc_attr__( $t ) { return $t; }
function sanitize_text_field( $t ) { return trim( (string) $t ); }
function sanitize_textarea_field( $t ) { return trim( (string) $t ); }
function wp_strip_all_tags( $t ) { return strip_tags( (string) $t ); }

/**
 * wp_kses simulado: conserva solo las etiquetas permitidas, con sus atributos
 * (quitando los `on*`). Alcanza para lo que el servicio valida: en el banner se
 * conserva `<span class="mu-highlight">` y se descarta cualquier otra etiqueta.
 */
function wp_kses( $t, $permitidos ) {
    $tags = is_array( $permitidos ) ? array_keys( $permitidos ) : [];

    return (string) preg_replace_callback(
        '#<\s*(/?)\s*([a-z0-9]+)([^>]*)>#i',
        function ( $m ) use ( $tags ) {
            $tag = strtolower( $m[2] );

            if ( ! in_array( $tag, $tags, true ) ) {
                return '';
            }

            $attrs = preg_replace( '/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $m[3] );

            return '<' . $m[1] . $tag . $attrs . '>';
        },
        (string) $t
    );
}
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function get_option( $k, $def = false ) { return $GLOBALS['mu_store'][ $k ] ?? $def; }
function update_option( $k, $v, $auto = null ) { $GLOBALS['mu_store'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['mu_store'][ $k ] ); return true; }
function get_transient( $k ) { return $GLOBALS['mu_trans'][ $k ] ?? false; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['mu_trans'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['mu_trans'][ $k ] ); return true; }
function current_time( $formato ) { return date( $formato ); }
function wp_json_encode( $d ) { return json_encode( $d, JSON_UNESCAPED_UNICODE ); }
function wp_send_json_success() {}
function wp_send_json_error() {}
function check_ajax_referer() { return true; }
function current_user_can() { return true; }
function wp_create_nonce() { return 'nonce'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function is_wp_error( $x ) { return $x instanceof WP_Error; }

class WP_Error {
    public function get_error_message() { return 'error simulado'; }
}

function wp_remote_retrieve_response_code( $r ) { return (int) ( $r['code'] ?? 200 ); }
function wp_remote_retrieve_body( $r ) { return (string) ( $r['body'] ?? '' ); }

/**
 * Respuesta del proveedor simulada.
 *
 * Si $GLOBALS['mu_respuesta'] está seteada, se devuelve tal cual (para simular fallos).
 * Si no, se arma un JSON válido con la forma real: candidates[0].content.parts[0].text
 * lleva el JSON con los items (una `translation` o una lista de `variants`, según lo
 * que se haya pedido en la instrucción).
 */
function wp_remote_post( $url, $args = [] ) {
    $payload = json_decode( (string) ( $args['body'] ?? '' ), true );

    $GLOBALS['mu_posts'][] = [ 'url' => $url, 'payload' => $payload, 'timeout' => (int) ( $args['timeout'] ?? 0 ) ];

    if ( isset( $GLOBALS['mu_respuesta'] ) ) {
        return $GLOBALS['mu_respuesta'];
    }

    $system = (string) ( $payload['systemInstruction']['parts'][0]['text'] ?? '' );
    $lote   = json_decode( (string) ( $payload['contents'][0]['parts'][0]['text'] ?? '[]' ), true );
    $multi  = ( false !== strpos( $system, '"variants"' ) );
    $items  = [];

    foreach ( (array) $lote as $item ) {
        $items[] = $multi
            ? [ 'key' => $item['key'], 'variants' => [ 'v1', 'v2', 'v3', 'v4', 'v5' ] ]
            : [ 'key' => $item['key'], 'translation' => 'EN:' . $item['text'] ];
    }

    return [
        'code' => 200,
        'body' => wp_json_encode( [
            'modelVersion' => 'gemini-de-prueba',
            'candidates'   => [ [ 'content' => [ 'parts' => [ [ 'text' => wp_json_encode( [ 'items' => $items ] ) ] ] ] ] ],
        ] ),
    ];
}

/** Última solicitud capturada (o null). */
function ultima() {
    $total = count( $GLOBALS['mu_posts'] );
    return $total ? $GLOBALS['mu_posts'][ $total - 1 ] : null;
}

/** Instrucción de sistema de la última solicitud. */
function ultima_instruccion() {
    $u = ultima();
    return (string) ( $u['payload']['systemInstruction']['parts'][0]['text'] ?? '' );
}

/** Configuración del ámbito tal como la lee el módulo. */
function perfil( $ambito ) {
    return mu_ai_context_profile( $ambito );
}

require_once __DIR__ . '/../../../inc/ai-translate.php';

echo "--- 1. Perfil: personas y variantes ---\n";
$personas = mu_ai_personas( 'reviews' );
same( 'Reseñas: 5 personas', 5, count( $personas ) );
same( 'La primera es El Escritor Rápido', 'El Escritor Rápido', $personas[0] ?? '' );
same( 'Reseñas: variantes declaradas', 5, mu_ai_variants( 'reviews' ) );
same( 'Banner: sin personas → 1 variante (ruta estándar)', 1, mu_ai_variants( 'hero_banner' ) );
same( 'Banner: no tiene personas cargadas', [], mu_ai_personas( 'hero_banner' ) );

echo "\n--- 2. Modo estándar (banner): una traducción, sin personas ---\n";
$r = mu_ai_translate( [ [ 'key' => 'titulo', 'text' => 'Hola' ] ], 'en', 'hero_banner' );
ok(
    'Traduce el texto',
    ! empty( $r['ok'] ) && 'EN:Hola' === ( $r['items']['titulo']['translation'] ?? '' ),
    var_export( $r['items'] ?? [], true )
);
same( 'Una sola variante en la respuesta', [ 'EN:Hola' ], $r['items']['titulo']['variants'] ?? [] );
same( 'Sin personas en la respuesta', 0, count( $r['personas'] ?? [] ) );
ok( 'La instrucción no pide variantes', false === strpos( ultima_instruccion(), '"variants"' ) );
same( 'Temperatura del ámbito en el payload', 0.2, (float) ( ultima()['payload']['generationConfig']['temperature'] ?? 0 ) );
same( 'Tope de espera estándar (20s)', 20, ultima()['timeout'] );

echo "\n--- 3. Modo manual (reseñas): una versión por persona ---\n";
$r    = mu_ai_translate( [ [ 'key' => 'text', 'text' => 'Excelente atención' ] ], 'pt', 'reviews' );
$item = $r['items']['text'] ?? [];
same( 'Devuelve las 5 variantes', 5, count( $item['variants'] ?? [] ) );
same( 'translation es la primera variante', 'v1', $item['translation'] ?? '' );
same( 'Las personas viajan en la respuesta (para los botones)', 5, count( $r['personas'] ?? [] ) );

$instruccion = ultima_instruccion();
ok( 'El payload pide variantes', false !== strpos( $instruccion, '"variants"' ) );
ok( 'El payload lista la primera persona', false !== strpos( $instruccion, 'El Escritor Rápido' ) );
ok( 'El payload lista la última persona', false !== strpos( $instruccion, 'El Cliente Escéptico' ) );
same( 'Temperatura de reseñas (0.9)', 0.9, (float) ( ultima()['payload']['generationConfig']['temperature'] ?? 0 ) );
same( 'Tope de espera estirado para la tanda (30s)', 30, ultima()['timeout'] );

echo "\n--- 4. Caché: la tanda se guarda y force la saltea ---\n";
$solicitudes = count( $GLOBALS['mu_posts'] );

$r2 = mu_ai_translate( [ [ 'key' => 'text', 'text' => 'Excelente atención' ] ], 'pt', 'reviews' );
same( 'Segunda llamada: no hay solicitud nueva', $solicitudes, count( $GLOBALS['mu_posts'] ) );
ok( 'Y viene marcada como caché', ! empty( $r2['items']['text']['cached'] ) );
same( 'Con las mismas 5 variantes', 5, count( $r2['items']['text']['variants'] ?? [] ) );

$r3 = mu_ai_translate( [ [ 'key' => 'text', 'text' => 'Excelente atención' ] ], 'pt', 'reviews', [ 'force' => true ] );
same( 'force=1: hay una solicitud nueva', $solicitudes + 1, count( $GLOBALS['mu_posts'] ) );
ok( 'Y la respuesta no es de caché', empty( $r3['items']['text']['cached'] ) );

echo "\n--- 5. La firma de instrucciones invalida la caché ---\n";
$version  = mu_ai_glossary_version( 'reviews' );
$settings = mu_ai_settings();
$settings['contexts']['reviews']['temperature'] = 0.3;
update_option( MU_AI_SETTINGS_OPTION, $settings );
mu_ai_settings( true );

ok( 'Cambiar la temperatura cambia la versión de instrucciones', mu_ai_glossary_version( 'reviews' ) !== $version );
$antes = count( $GLOBALS['mu_posts'] );
mu_ai_translate( [ [ 'key' => 'text', 'text' => 'Excelente atención' ] ], 'pt', 'reviews' );
same( 'Y la traducción cacheada deja de reutilizarse', $antes + 1, count( $GLOBALS['mu_posts'] ) );

echo "\n--- 6. Consumo honesto (variantes) ---\n";
$uso = mu_ai_usage();
ok( 'Registra las solicitudes', (int) $uso['day_requests'] >= 3, var_export( $uso['day_requests'], true ) );
ok( 'Y las variantes generadas', (int) $uso['day_variants'] >= 10, var_export( $uso['day_variants'], true ) );
ok( 'El mes acompaña', (int) $uso['month_variants'] === (int) $uso['day_variants'] );

echo "\n--- 7. Guardas (sin gastar solicitudes) ---\n";
$antes = count( $GLOBALS['mu_posts'] );

$r = mu_ai_translate( [ [ 'key' => 'x', 'text' => 'Hola' ] ], 'en', 'no_existe' );
ok( 'Ámbito desconocido: no traduce', empty( $r['ok'] ) );
same( 'Y no hay solicitud', $antes, count( $GLOBALS['mu_posts'] ) );

$r = mu_ai_translate( [ [ 'key' => 'x', 'text' => 'Hola' ] ], 'fr', 'reviews' );
ok( 'Idioma no soportado: no traduce', empty( $r['ok'] ) );
same( 'Y no hay solicitud', $antes, count( $GLOBALS['mu_posts'] ) );

$r = mu_ai_translate( [ [ 'key' => 'text', 'text' => 'Escribime a juan@example.com' ] ], 'pt', 'reviews' );
ok( 'Dato personal: rechaza el lote', empty( $r['ok'] ) && 'personal_data' === ( $r['code'] ?? '' ), var_export( $r['code'] ?? '', true ) );
same( 'Y no hay solicitud', $antes, count( $GLOBALS['mu_posts'] ) );

echo "\n--- 8. Marcado y decodificación de caché ---\n";
ok( 'Banner: conserva el resaltado permitido', false !== strpos( mu_ai_sanitize_for_field( '<span class="mu-highlight">Hola</span> más', 'hero_banner' ), 'mu-highlight' ) );
ok( 'Banner: descarta lo no permitido', false === strpos( mu_ai_sanitize_for_field( '<script>alert(1)</script>Hola', 'hero_banner' ), 'script' ) );
ok( 'Reseñas: sin marcado (el comentario es texto)', false === strpos( mu_ai_sanitize_for_field( '<b>Hola</b>', 'reviews' ), '<b>' ) );
same( 'Caché antigua (texto plano) → lista de una', [ 'hola' ], mu_ai_cache_decode( 'hola' ) );
same( 'Caché de tanda (JSON) → lista completa', [ 'a', 'b' ], mu_ai_cache_decode( '["a","b"]' ) );
same( 'Caché vacía → lista vacía', [], mu_ai_cache_decode( '' ) );

echo "\n--- 9. unchanged (el texto ya estaba en el idioma) ---\n";
$GLOBALS['mu_respuesta'] = [
    'code' => 200,
    'body' => wp_json_encode( [
        'candidates' => [ [ 'content' => [ 'parts' => [ [ 'text' => wp_json_encode( [ 'items' => [ [ 'key' => 'text', 'translation' => '', 'unchanged' => true ] ] ] ) ] ] ] ] ],
    ] ),
];

$r = mu_ai_translate( [ [ 'key' => 'text', 'text' => 'Texto distinto para no tocar la caché' ] ], 'pt', 'reviews' );
unset( $GLOBALS['mu_respuesta'] );

ok( 'Se informa sin cambios', ! empty( $r['items']['text']['unchanged'] ) );

echo "\n--- 10. Diagnostico: unchanged, traducidos y faltantes ---\n";
$GLOBALS['mu_respuesta'] = [
    'code' => 200,
    'body' => wp_json_encode( [
        'candidates' => [ [ 'content' => [ 'parts' => [ [ 'text' => wp_json_encode( [ 'items' => [
            [ 'key' => 'titulo', 'translation' => '', 'unchanged' => true ],
            [ 'key' => 'descripcion', 'translation' => 'New description' ],
        ] ] ) ] ] ] ] ],
    ] ),
];

$r = mu_ai_translate(
    [
        [ 'key' => 'titulo', 'text' => 'Titulo que el modelo considera ya traducido' ],
        [ 'key' => 'descripcion', 'text' => 'Descripcion distinta para esta prueba' ],
        [ 'key' => 'eyebrow', 'text' => 'Eyebrow distinto para esta prueba' ],
    ],
    'en',
    'hero_banner'
);
unset( $GLOBALS['mu_respuesta'] );

ok( 'El campo unchanged se informa como tal', ! empty( $r['items']['titulo']['unchanged'] ) );
ok( 'El campo traducido llega con su texto', 'New description' === ( $r['items']['descripcion']['translation'] ?? '' ), var_export( $r['items']['descripcion'] ?? [], true ) );
ok( 'El campo que el modelo omitio queda en missing (no como unchanged)', in_array( 'eyebrow', (array) ( $r['missing'] ?? [] ), true ), var_export( $r['missing'] ?? [], true ) );
echo "\n== {$GLOBALS['mu_ok']} OK / {$GLOBALS['mu_fail']} FAIL ==\n";
exit( $GLOBALS['mu_fail'] > 0 ? 1 : 0 );


