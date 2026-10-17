<?php
/**
 * Arnés de fechas por país en hero banners (feature 010).
 *
 * Ejecuta el módulo REAL (inc/hero-banners.php) con WordPress simulado: sin
 * WordPress, sin base de datos y sin red. Cubre la ventana efectiva por país
 * (propia o heredada de la general), los bordes del día y el saneado de
 * 'fechas[PAIS]' al guardar.
 *
 * Se corre desde la raíz del repositorio:
 *   php specs\010-fechas-banner-por-pais\harness\arnes-fechas-por-pais.php
 */
define( 'ABSPATH', __DIR__ . '/' );

/* Constantes de tiempo de WordPress (las usa el TTL del transient). */
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
function esc_attr( $t ) { return (string) $t; }
function esc_html( $t ) { return (string) $t; }
function esc_url( $t ) { return (string) $t; }
function esc_textarea( $t ) { return (string) $t; }
function esc_html_e( $t ) { echo $t; }
function esc_attr_e( $t ) { echo $t; }
function checked( $a, $b = true, $echo = true ) {
    $r = ( (string) $a === (string) $b ) ? 'checked="checked"' : '';
    if ( $echo ) { echo $r; }
    return $r;
}
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function get_option( $k, $def = null ) {
    return array_key_exists( $k, (array) ( $GLOBALS['mu_option'] ?? [] ) ) ? $GLOBALS['mu_option'][ $k ] : $def;
}
function get_transient( $k ) {
    return array_key_exists( $k, (array) ( $GLOBALS['mu_transient'] ?? [] ) ) ? $GLOBALS['mu_transient'][ $k ] : false;
}
function set_transient( $k, $v, $t = 0 ) {
    $GLOBALS['mu_transient'][ $k ]     = $v;
    $GLOBALS['mu_transient_ttl'][ $k ] = (int) $t;   // el arnés verifica el TTL pedido
    return true;
}
function delete_transient( $k ) { unset( $GLOBALS['mu_transient'][ $k ], $GLOBALS['mu_transient_ttl'][ $k ] ); return true; }
function muyu_get_current_country_from_subdomain() { return $GLOBALS['mu_pais'] ?? 'AR'; }
function wp_timezone() { return new DateTimeZone( 'America/Argentina/Buenos_Aires' ); }
function muyu_get_countries_data() {
    // 'ZZ' es un país con el huso mal escrito a propósito (prueba del respaldo).
    return [
        'AR' => [ 'name' => 'Argentina', 'tz' => 'America/Argentina/Buenos_Aires' ],
        'BR' => [ 'name' => 'Brasil',    'tz' => 'America/Sao_Paulo' ],
        'MX' => [ 'name' => 'México',    'tz' => 'America/Mexico_City' ],
        'ES' => [ 'name' => 'España',    'tz' => 'Europe/Madrid' ],
        'CL' => [ 'name' => 'Chile',     'tz' => 'America/Santiago' ],
        'ZZ' => [ 'name' => 'Zona rara', 'tz' => 'No/Existe' ],
    ];
}

require __DIR__ . '/../../../inc/hero-banners.php';

/* Ventanas de referencia (misma zona horaria que el módulo). */
$t_ene = mktime( 12, 0, 0, 1, 15, 2026 );   // 15/01/2026
$t_jun = mktime( 12, 0, 0, 6, 15, 2026 );   // 15/06/2026
$t_dic = mktime( 12, 0, 0, 12, 15, 2026 );  // 15/12/2026

echo "--- 1. Ventana efectiva por país (herencia campo por campo) ---\n";
$banner = [
    'inicio' => '01012026',
    'fin'    => '31122026',
    'fechas' => [ 'BR' => [ 'inicio' => '01062026', 'fin' => '' ] ],
];
same( 'País sin ventana propia hereda la general (inicio)', '01012026', mu_hero_banner_pais_fechas( $banner, 'AR' )['inicio'] );
same( 'País sin ventana propia hereda la general (fin)', '31122026', mu_hero_banner_pais_fechas( $banner, 'AR' )['fin'] );
same( 'BR usa su inicio propio', '01062026', mu_hero_banner_pais_fechas( $banner, 'BR' )['inicio'] );
same( 'BR hereda el fin general (campo por campo)', '31122026', mu_hero_banner_pais_fechas( $banner, 'BR' )['fin'] );
same( 'Banner sin fechas: ventana vacía', [ '', '' ], array_values( mu_hero_banner_pais_fechas( [ 'id' => 'x' ], 'AR' ) ) );
same( 'Banner no-array no rompe', [ '', '' ], array_values( mu_hero_banner_pais_fechas( 'x', 'AR' ) ) );
same( "'fechas' corrupto (string) no rompe", [ '', '' ], array_values( mu_hero_banner_pais_fechas( [ 'fechas' => 'x' ], 'AR' ) ) );

echo "\n--- 2. Activo/inactivo por país (herencia y bordes del día) ---\n";
check( 'AR dentro de la ventana general', true === mu_hero_banner_is_active_for_pais( $banner, 'AR', $t_jun ) );
check( 'BR fuera de su ventana propia aunque la general lo cubra', false === mu_hero_banner_is_active_for_pais( $banner, 'BR', $t_ene ) );
check( 'BR dentro de su ventana propia', true === mu_hero_banner_is_active_for_pais( $banner, 'BR', $t_jun ) );
check( 'Con el fin heredado, BR sigue visible fuera de su ventana parcial', true === mu_hero_banner_is_active_for_pais( $banner, 'BR', $t_dic ) );

// Ventana completa por país: sirve para medir los bordes del día.
$br_completo = [
    'inicio' => '01012026',
    'fin'    => '31122026',
    'fechas' => [ 'BR' => [ 'inicio' => '01062026', 'fin' => '30062026' ] ],
];
$br_ini = mktime( 0, 0, 0, 6, 1, 2026 );
$br_fin = mktime( 23, 59, 59, 6, 30, 2026 );
check( 'Inicio inclusive: el primer segundo del día ya se ve', true === mu_hero_banner_is_active_for_pais( $br_completo, 'BR', $br_ini ) );
check( 'Un segundo antes del inicio todavía no', false === mu_hero_banner_is_active_for_pais( $br_completo, 'BR', $br_ini - 1 ) );
check( 'Fin inclusive: el último segundo del día todavía se ve', true === mu_hero_banner_is_active_for_pais( $br_completo, 'BR', $br_fin ) );
check( 'Un segundo después del fin ya no', false === mu_hero_banner_is_active_for_pais( $br_completo, 'BR', $br_fin + 1 ) );
check( 'AR sigue activo cuando la ventana de BR ya cerró', true === mu_hero_banner_is_active_for_pais( $br_completo, 'AR', $t_dic ) );

check( 'Sin fechas el banner se muestra siempre', true === mu_hero_banner_is_active_for_pais( [ 'id' => 'x' ], 'AR', $t_ene ) );
check( 'Con una sola fecha (sin fin) se muestra siempre', true === mu_hero_banner_is_active_for_pais( [ 'inicio' => '01012026' ], 'AR', $t_dic ) );
check( 'Rango invertido no se muestra nunca', false === mu_hero_banner_is_active_for_pais( [ 'inicio' => '31122026', 'fin' => '01012026' ], 'AR', $t_jun ) );
check(
    'Día fuera de rango guardado: ahora se trata como sin límite (M3)',
    true === mu_hero_banner_is_active_for_pais( [ 'inicio' => '99012026', 'fin' => '31122026' ], 'AR', $t_ene )
);
check( 'Banner legacy (sin clave fechas) mantiene el comportamiento previo', true === mu_hero_banner_is_active_for_pais( [ 'inicio' => '01012026', 'fin' => '31122026' ], 'AR', $t_jun ) );

echo "\n--- 3. Saneado al guardar ('fechas[PAIS]') ---\n";
$clean = mu_hero_banners_sanitize_one( [
    'id'     => 'Cole 26',
    'titulo' => 'Hola',
    'inicio' => '01012026',
    'fin'    => '31122026',
    'paises' => [ 'AR', 'BR', 'XX' ],
    'fechas' => [
        'AR' => [ 'inicio' => '01012026', 'fin' => '31122026' ], // idéntico a la general
        'BR' => [ 'inicio' => '01062026', 'fin' => '' ],          // ventana propia parcial
        'MX' => [ 'inicio' => '01012026', 'fin' => '01012026' ],  // país válido pero no tildado
        'XX' => [ 'inicio' => '01062026', 'fin' => '30062026' ],  // país inexistente
    ],
] );
same( 'País idéntico a la general no se guarda (hereda)', false, isset( $clean['fechas']['AR'] ) );
same( 'País con ventana propia sí se guarda', '01062026', $clean['fechas']['BR']['inicio'] );
same( 'El campo heredado se guarda vacío', '', $clean['fechas']['BR']['fin'] );
same( 'País válido no tildado conserva su ventana (M7)', '01012026', $clean['fechas']['MX']['fin'] ?? '' );
same( 'País inválido se descarta', false, isset( $clean['fechas']['XX'] ) );
same( 'La fecha general sigue saneándose', '01012026', $clean['inicio'] );

$sin_generales = mu_hero_banners_sanitize_one( [
    'titulo' => 'x',
    'paises' => [ 'BR' ],
    'fechas' => [ 'BR' => [ 'inicio' => '01062026', 'fin' => '30062026' ] ],
] );
same( 'Sin fechas generales la ventana del país queda completa', [ 'inicio' => '01062026', 'fin' => '30062026' ], $sin_generales['fechas']['BR'] );

$formato_malo = mu_hero_banners_sanitize_one( [
    'titulo' => 'x',
    'paises' => [ 'AR' ],
    'fechas' => [ 'AR' => [ 'inicio' => '2026-06-01', 'fin' => 'abc' ] ],
] );
same( 'Formato distinto de DDMMAAAA no se guarda', false, isset( $formato_malo['fechas']['AR'] ) );
same( "'fechas' no-array se ignora", [], mu_hero_banners_sanitize_one( [ 'titulo' => 'x', 'paises' => [ 'AR' ], 'fechas' => 'x' ] )['fechas'] );
same( 'Sin clave fechas el saneado devuelve el mapa vacío', [], mu_hero_banners_sanitize_one( [ 'titulo' => 'x', 'paises' => [ 'AR' ] ] )['fechas'] );

echo "\n--- 4. Invariante de caché ---\n";
same( 'El transient sigue siendo por país (y normaliza mayúsculas)', 'mu_hero_banners_active_AR', mu_hero_banners_transient_key( 'ar' ) );

echo "\n--- 5. Marcado del panel (fila renderizada de verdad) ---\n";
$row_b = [
    'id'     => 'demo',
    'titulo' => 'Promo',
    'inicio' => '01012026',
    'fin'    => '31122026',
    'paises' => [ 'AR', 'BR' ],
    'fechas' => [ 'BR' => [ 'inicio' => '01062026', 'fin' => '' ] ],
];
ob_start();
mu_hero_banners_render_row( 0, $row_b );
$html = ob_get_clean();

same( 'Un solo contenedor de píldoras de país (el de ajustes generales)', 1, substr_count( $html, 'mu-hero-banner-paises-pills' ) );
check( 'La píldora Global nace activa', (bool) preg_match( '/class="[^"]*mu-hero-banner-pill is-selectable is-active"[^>]*data-pais=""/', $html ) );
check( 'La píldora del país tildado se muestra', (bool) preg_match( '/data-pais="AR"[^>]*>AR</', $html ) );
check( 'La píldora del país NO tildado nace oculta', (bool) preg_match( '/data-pais="MX"[^>]*display:none[^>]*>MX</', $html ) );
check( 'El país con ventana propia sale con el punto encendido', (bool) preg_match( '/data-pais="BR"[^>]*data-has-fechas="1"/', $html ) );
check( 'El país sin ventana propia no lleva punto', (bool) preg_match( '/data-pais="AR"[^>]*data-has-fechas="0"/', $html ) );
same( 'El inicio general conserva el nombre de campo de siempre', 1, substr_count( $html, 'name="mu_banner[0][inicio]"' ) );
same( 'El fin general conserva su nombre de campo', 1, substr_count( $html, 'name="mu_banner[0][fin]"' ) );
same( 'Cada país tiene su panel con nombre propio', 1, substr_count( $html, 'name="mu_banner[0][fechas][BR][inicio]"' ) );
same( 'El panel existe también para países sin tildar', 1, substr_count( $html, 'name="mu_banner[0][fechas][MX][inicio]"' ) );
same( 'Sólo el panel general nace visible (los demás ocultos)', 1, substr_count( $html, '<p class="mu-hero-banner-fechas-block mu-hero-banner-row__full" data-pais=""' ) );
check( 'El panel dice el huso del país (M6)', false !== strpos( $html, 'America/Sao_Paulo' ) );
check( 'Y el de todos los países del sitio', false !== strpos( $html, 'Europe/Madrid' ) && false !== strpos( $html, 'America/Santiago' ) );
same( 'Una fila sana no muestra avisos', 0, substr_count( $html, 'mu-hero-banner-row__alert' ) );
same( 'Hay un panel de fechas por cada país del sitio', 7, substr_count( $html, 'mu-hero-banner-fechas-block mu-hero-banner-row__full' ) );
same( 'Los campos de fecha NO usan pattern (un campo oculto inválido bloquearía el envío)', 0, substr_count( $html, 'pattern=' ) );
same( 'Los campos de fecha se limitan a 8 caracteres', 14, substr_count( $html, 'maxlength="8"' ) );
same( 'La cabecera tiene el contenedor de variantes de IA (feature 011)', 1, substr_count( $html, 'data-role="ai-variants"' ) );

echo "\n--- 6. Integración: el filtro del front respeta la ventana de cada país ---\n";
// Fechas relativas a hoy: hace 30/20 días (cerrada) y ayer/mañana (abierta). Así el caso
// es determinista sin depender del calendario.
$hoy     = time();
$abierta = [ 'inicio' => date( 'dmY', $hoy - 86400 ), 'fin' => date( 'dmY', $hoy + 86400 ) ];
$cerrada = [ 'inicio' => date( 'dmY', $hoy - ( 30 * 86400 ) ), 'fin' => date( 'dmY', $hoy - ( 20 * 86400 ) ) ];

$GLOBALS['mu_transient'] = [];
$GLOBALS['mu_option'][ MU_HERO_BANNERS_OPTION ] = [
    // 1. General abierta para los dos países.
    [ 'id' => 'general', 'titulo' => 'G', 'inicio' => $abierta['inicio'], 'fin' => $abierta['fin'], 'paises' => [ 'AR', 'BR' ] ],
    // 2. La general está abierta, pero su ventana propia en BR ya cerró.
    [ 'id' => 'br-cerrada', 'titulo' => 'B1', 'inicio' => $abierta['inicio'], 'fin' => $abierta['fin'], 'paises' => [ 'BR' ], 'fechas' => [ 'BR' => $cerrada ] ],
    // 3. La general está cerrada, pero su ventana propia en BR está abierta.
    [ 'id' => 'br-abierta', 'titulo' => 'B2', 'inicio' => $cerrada['inicio'], 'fin' => $cerrada['fin'], 'paises' => [ 'BR' ], 'fechas' => [ 'BR' => $abierta ] ],
];

$GLOBALS['mu_pais'] = 'AR';
$en_ar              = mu_get_hero_banners();
same( 'AR sólo ve los banners de AR (con la ventana general)', [ 'general' ], array_column( $en_ar, 'id' ) );
check( 'El resultado quedó cacheado en el transient del país', array_key_exists( 'mu_hero_banners_active_AR', $GLOBALS['mu_transient'] ) );

$GLOBALS['mu_pais'] = 'BR';
$en_br              = mu_get_hero_banners();
same( 'BR no ve el banner cuya ventana propia ya cerró', 'br-cerrada' !== ( array_column( $en_br, 'id' )[1] ?? '' ), true );
same( 'BR sí ve el inclusivo por su ventana propia', [ 'general', 'br-abierta' ], array_column( $en_br, 'id' ) );

echo "\n--- 7. Validación real de fechas (M3) ---\n";
check( 'Fecha válida', true === mu_hero_banner_fecha_valida( '01012026' ) );
check( '29 de febrero en año bisiesto', true === mu_hero_banner_fecha_valida( '29022024' ) );
check( '31 de febrero no existe', false === mu_hero_banner_fecha_valida( '31022026' ) );
check( '29 de febrero en año no bisiesto', false === mu_hero_banner_fecha_valida( '29022026' ) );
check( 'Mes 13 no existe', false === mu_hero_banner_fecha_valida( '31132026' ) );
check( 'Día 99 no existe (antes hacía rollover)', false === mu_hero_banner_fecha_valida( '99012026' ) );
check( 'Día 00 no existe', false === mu_hero_banner_fecha_valida( '00012026' ) );
check( 'Otro formato', false === mu_hero_banner_fecha_valida( '2026-06-01' ) );
check( 'Vacío', false === mu_hero_banner_fecha_valida( '' ) );
check( 'Sin ventana completa no hay instantes', null === mu_hero_banner_ventana_instantes( [ 'inicio' => '01012026' ], 'AR' ) );

echo "\n--- 8. Huso horario del país (M1 y M2) ---\n";
same( 'BR usa el huso de Brasil', 'America/Sao_Paulo', mu_hero_banner_pais_tz( 'BR' )->getName() );
same( 'ES usa el huso de España', 'Europe/Madrid', mu_hero_banner_pais_tz( 'ES' )->getName() );
same( 'País desconocido cae al huso del sitio', 'America/Argentina/Buenos_Aires', mu_hero_banner_pais_tz( 'XX' )->getName() );
same( 'Huso mal escrito cae al huso del sitio', 'America/Argentina/Buenos_Aires', mu_hero_banner_pais_tz( 'ZZ' )->getName() );

$tz_madrid  = new DateTimeZone( 'Europe/Madrid' );
$tz_bsas    = new DateTimeZone( 'America/Argentina/Buenos_Aires' );
$es_ini     = ( new DateTimeImmutable( '2026-06-01 00:00:00', $tz_madrid ) )->getTimestamp();
$ar_ini     = ( new DateTimeImmutable( '2026-06-01 00:00:00', $tz_bsas ) )->getTimestamp();
$mismo_dato = [ 'inicio' => '01062026', 'fin' => '30062026', 'paises' => [ 'AR', 'ES' ] ];

check( 'Madrid ya ve el banner en SU medianoche', true === mu_hero_banner_is_active_for_pais( $mismo_dato, 'ES', $es_ini ) );
check( 'Madrid un segundo antes: todavía no', false === mu_hero_banner_is_active_for_pais( $mismo_dato, 'ES', $es_ini - 1 ) );
same( 'La misma fecha en Buenos Aires es otro instante (5 h después en junio)', 18000, $ar_ini - $es_ini );
check( 'A la medianoche de Madrid, Buenos Aires todavía no lo ve', false === mu_hero_banner_is_active_for_pais( $mismo_dato, 'AR', $es_ini ) );
check( 'Buenos Aires entra en su propia medianoche', true === mu_hero_banner_is_active_for_pais( $mismo_dato, 'AR', $ar_ini ) );

// Cambio de horario de verano en Chile (2026-09-06: el día tiene 23 h).
$tz_cl     = new DateTimeZone( 'America/Santiago' );
$banner_cl = [ 'inicio' => '05092026', 'fin' => '07092026', 'paises' => [ 'CL' ] ];
$cl_ini    = ( new DateTimeImmutable( '2026-09-05 00:00:00', $tz_cl ) )->getTimestamp();
$cl_fin    = ( new DateTimeImmutable( '2026-09-08 00:00:00', $tz_cl ) )->getTimestamp() - 1;
$ventana   = mu_hero_banner_ventana_instantes( $banner_cl, 'CL' );

same( 'El inicio del día del país es el esperado', $cl_ini, $ventana[0] );
same( 'El fin es el último segundo del día del país', $cl_fin, $ventana[1] );
same( 'La ventana dura 71 h, no 72: el día del salto tiene 23 h', 255600, $ventana[1] - $ventana[0] + 1 );
check( 'El último segundo del fin todavía se ve', true === mu_hero_banner_is_active_for_pais( $banner_cl, 'CL', $cl_fin ) );
check( 'Un segundo después ya no', false === mu_hero_banner_is_active_for_pais( $banner_cl, 'CL', $cl_fin + 1 ) );

echo "\n--- 9. Próximo borde y TTL del transient (M5-A) ---\n";
$junio = mktime( 12, 0, 0, 6, 15, 2026 );
same( 'Sin fechas no hay borde', 0, mu_hero_banner_proximo_borde( [ 'id' => 'x' ], 'AR', $junio ) );
same(
    'Banner activo: el próximo borde es su salida',
    ( new DateTimeImmutable( '2026-07-01 00:00:00', $tz_bsas ) )->getTimestamp(),
    mu_hero_banner_proximo_borde( [ 'inicio' => '01062026', 'fin' => '30062026' ], 'AR', $junio )
);
same(
    'Banner todavía inactivo: el próximo borde es su entrada',
    ( new DateTimeImmutable( '2026-06-01 00:00:00', $tz_bsas ) )->getTimestamp(),
    mu_hero_banner_proximo_borde( [ 'inicio' => '01062026', 'fin' => '30062026' ], 'AR', mktime( 12, 0, 0, 1, 15, 2026 ) )
);
same( 'Ventana ya terminada: sin bordes futuros', 0, mu_hero_banner_proximo_borde( [ 'inicio' => '01062026', 'fin' => '30062026' ], 'AR', mktime( 12, 0, 0, 12, 15, 2026 ) ) );
same(
    'El borde también se resuelve en el huso del país (Madrid sale 5 h antes)',
    18000,
    mu_hero_banner_proximo_borde( $mismo_dato, 'AR', $junio ) - mu_hero_banner_proximo_borde( $mismo_dato, 'ES', $junio )
);

// TTL del transient: 12 h de fábrica cuando no hay bordes…
$GLOBALS['mu_transient']     = [];
$GLOBALS['mu_transient_ttl'] = [];
$GLOBALS['mu_pais']          = 'AR';
$GLOBALS['mu_option'][ MU_HERO_BANNERS_OPTION ] = [ [ 'id' => 'siempre', 'titulo' => 'S', 'paises' => [ 'AR' ] ] ];
mu_get_hero_banners();
same( 'Sin bordes futuros el transient dura 12 h', 12 * 3600, $GLOBALS['mu_transient_ttl']['mu_hero_banners_active_AR'] ?? 0 );

// …y se acorta hasta el próximo borde (tolerancia de 5 s por el tiempo transcurrido).
$GLOBALS['mu_transient']     = [];
$GLOBALS['mu_transient_ttl'] = [];
$corto  = [ 'id' => 'corto', 'titulo' => 'C', 'paises' => [ 'AR' ], 'inicio' => date( 'dmY' ), 'fin' => date( 'dmY', time() + 86400 ) ];
$antes  = time();
$GLOBALS['mu_option'][ MU_HERO_BANNERS_OPTION ] = [ $corto ];
mu_get_hero_banners();
$ttl    = $GLOBALS['mu_transient_ttl']['mu_hero_banners_active_AR'] ?? 0;
$borde  = mu_hero_banner_proximo_borde( $corto, 'AR', $antes );
$espera = max( 60, min( 12 * 3600, $borde - $antes + 1 ) );
check(
    'El TTL se acorta al próximo borde (±5 s) y nunca pasa de 12 h',
    abs( $ttl - $espera ) <= 5 && $ttl <= 12 * 3600,
    'ttl=' . $ttl . ' esperado≈' . $espera
);

echo "\n--- 10. Avisos de fecha en la lista (M4) ---\n";
$sin_problemas = [
    'id'     => 'ok',
    'inicio' => '01012026',
    'fin'    => '31122026',
    'fechas' => [ 'BR' => [ 'inicio' => '01062026', 'fin' => '' ] ],
];
same( 'Banner sano: sin avisos', [ 'invalidas' => [], 'invertidas' => [] ], mu_hero_banner_avisos( $sin_problemas ) );
same( 'Fecha general inválida', [ '' ], mu_hero_banner_avisos( [ 'inicio' => '31132026', 'fin' => '31122026' ] )['invalidas'] );
same( 'Fecha de país inválida', [ 'BR' ], mu_hero_banner_avisos( [ 'fechas' => [ 'BR' => [ 'inicio' => '2026-06-01', 'fin' => '' ] ] ] )['invalidas'] );
same( 'Rango invertido en un país', [ 'BR' ], mu_hero_banner_avisos( [ 'fechas' => [ 'BR' => [ 'inicio' => '30062026', 'fin' => '01062026' ] ] ] )['invertidas'] );
same( 'Rango invertido en las generales', [ '' ], mu_hero_banner_avisos( [ 'inicio' => '31122026', 'fin' => '01012026' ] )['invertidas'] );
same( 'Con una sola fecha no hay rango que invertir', [], mu_hero_banner_avisos( [ 'inicio' => '30062026', 'fin' => '' ] )['invertidas'] );

$solo_invalido = mu_hero_banner_avisos( [ 'fechas' => [ 'BR' => [ 'inicio' => '99012026', 'fin' => '01062026' ] ] ] );
same( 'Una fecha inválida sí se reporta como inválida', [ 'BR' ], $solo_invalido['invalidas'] );
same( 'Y no se cuenta además como invertida', [], $solo_invalido['invertidas'] );

$row_malo = [
    'id'     => 'malo',
    'titulo' => 'Malo',
    'inicio' => '31122026',
    'fin'    => '01012026',
    'paises' => [ 'BR' ],
    'fechas' => [ 'BR' => [ 'inicio' => '99012026', 'fin' => '' ] ],
];
ob_start();
mu_hero_banners_render_row( 1, $row_malo );
$html_malo = ob_get_clean();

same( 'La fila con problemas muestra un aviso', 1, substr_count( $html_malo, 'mu-hero-banner-row__alert' ) );
check( 'El aviso nombra las fechas generales invertidas', false !== strpos( $html_malo, 'Fechas generales: el inicio es posterior al fin' ) );
check( 'Y la fecha inválida de Brasil', false !== strpos( $html_malo, 'BR: hay una fecha inválida guardada' ) );

echo "\n== {$GLOBALS['mu_ok']} OK / {$GLOBALS['mu_fail']} FAIL ==\n";
exit( $GLOBALS['mu_fail'] > 0 ? 1 : 0 );
