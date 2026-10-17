<?php
/**
 * Arnés de la vigencia anual del hero banner (feature 012).
 *
 * Ejecuta el módulo REAL (inc/hero-banners.php) con WordPress simulado: sin WordPress,
 * sin base de datos y sin red. Cubre la ventana anual (cruce de fin de año, 29/02, DST),
 * la herencia del flag, el saneado, los avisos, el marcado de la fila y la purga en el
 * borde (evento único del cron).
 *
 * Se corre desde la raíz del repositorio:
 *   php specs\012-vigencia-anual\harness\arnes-anual.php
 */
define( 'ABSPATH', __DIR__ . '/' );

/* El arnés arma sus instantes con husos EXPLÍCITOS: el huso por defecto de PHP no debe
   influir en ningún resultado (a diferencia del arnés de 010, que usa mktime). */
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
function is_admin() { return false; }
function add_action( $hook, $cb = null ) { $GLOBALS['mu_hooks'][ $hook ][] = is_string( $cb ) ? $cb : 'closure'; }
function do_action( $hook ) {
    $args = func_get_args();
    $GLOBALS['mu_actions'][] = $hook;
    $GLOBALS['mu_action_args'][ $hook ][] = $args;
}
function has_action( $hook ) { return ! empty( $GLOBALS['mu_hooks'][ $hook ] ); }
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
/** [015] Necesario para las opciones del borde (borde esperado, última purga). */
function update_option( $k, $v, $autoload = null ) { $GLOBALS['mu_option'][ $k ] = $v; return true; }
function get_transient( $k ) {
    return array_key_exists( $k, (array) ( $GLOBALS['mu_transient'] ?? [] ) ) ? $GLOBALS['mu_transient'][ $k ] : false;
}
function set_transient( $k, $v, $t = 0 ) {
    $GLOBALS['mu_transient'][ $k ]     = $v;
    $GLOBALS['mu_transient_ttl'][ $k ] = (int) $t;
    return true;
}
function delete_transient( $k ) { unset( $GLOBALS['mu_transient'][ $k ], $GLOBALS['mu_transient_ttl'][ $k ] ); return true; }
function muyu_get_current_country_from_subdomain() { return $GLOBALS['mu_pais'] ?? 'AR'; }
function wp_timezone() { return new DateTimeZone( 'America/Argentina/Buenos_Aires' ); }

/* Cron simulado: un evento por hook (basta para lo que verifica este arnés). */
function wp_next_scheduled( $hook ) { return $GLOBALS['mu_cron'][ $hook ] ?? false; }
function wp_schedule_single_event( $ts, $hook, $args = [] ) {
    if ( isset( $GLOBALS['mu_cron'][ $hook ] ) ) { return false; }
    $GLOBALS['mu_cron'][ $hook ] = (int) $ts;
    return true;
}
function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['mu_cron'][ $hook ] ); return true; }

function muyu_get_countries_data() {
    // 'ZZ' tiene el huso mal escrito a propósito (prueba del respaldo).
    return [
        'AR' => [ 'name' => 'Argentina', 'host' => 'muyunicos.com',        'tz' => 'America/Argentina/Buenos_Aires' ],
        'BR' => [ 'name' => 'Brasil',    'host' => 'br.muyunicos.com',     'tz' => 'America/Sao_Paulo' ],
        'MX' => [ 'name' => 'México',    'host' => 'mexico.muyunicos.com', 'tz' => 'America/Mexico_City' ],
        'ES' => [ 'name' => 'España',    'host' => 'es.muyunicos.com',     'tz' => 'Europe/Madrid' ],
        'CL' => [ 'name' => 'Chile',     'host' => 'cl.muyunicos.com',     'tz' => 'America/Santiago' ],
        'US' => [ 'name' => 'EE.UU.',    'host' => 'us.muyunicos.com',     'tz' => 'America/New_York' ],
        'ZZ' => [ 'name' => 'Zona rara', 'host' => '',                     'tz' => 'No/Existe' ],
    ];
}

/* El rastro [MU-HERO-BORDE] va a un archivo, no a la salida del arnés. */
$MU_LOG = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mu-hero-borde-harness.log';
@unlink( $MU_LOG );
ini_set( 'error_log', $MU_LOG );

// Repositorio REAL de iconos: el lienzo del editor (feature 013) renderiza el CTA con
// mu_get_icon(), que de otro modo no existe en el WordPress simulado.
require __DIR__ . '/../../../inc/icons.php';

require __DIR__ . '/../../../inc/hero-banners.php';

$GLOBALS['mu_hooks']   = $GLOBALS['mu_hooks'] ?? [];
$GLOBALS['mu_actions'] = $GLOBALS['mu_actions'] ?? [];

/** Instante de una fecha/hora local en un huso explícito. */
function ts( $iso, $tz ) {
    return ( new DateTimeImmutable( $iso, new DateTimeZone( $tz ) ) )->getTimestamp();
}

$AR = 'America/Argentina/Buenos_Aires';

echo "--- 1. Día y mes (MMDD) ---\n";
same( 'Fecha completa', '1220', mu_hero_banner_mmdd( '20122026' ) );
same( 'Con espacios alrededor no sirve (se sanea antes)', '', mu_hero_banner_mmdd( ' 20122026 ' ) );
same( 'Fecha inexistente', '', mu_hero_banner_mmdd( '31022026' ) );
same( '29/02 de año bisiesto', '0229', mu_hero_banner_mmdd( '29022024' ) );
same( '29/02 de año no bisiesto no existe', '', mu_hero_banner_mmdd( '29022026' ) );
same( 'Vacío', '', mu_hero_banner_mmdd( '' ) );
same( 'null no rompe', '', mu_hero_banner_mmdd( null ) );

echo "\n--- 2. Ventana anual (cruce de fin de año, 29/02, DST) ---\n";
// Navidad: 20/12 → 10/01, el caso que cruza el fin de año.
$navidad = [ 'id' => 'navidad', 'anual' => '1', 'inicio' => '20122026', 'fin' => '10012027', 'paises' => [ 'AR' ] ];

check( 'Diciembre: dentro de la ventana', true === mu_hero_banner_is_active_for_pais( $navidad, 'AR', ts( '2026-12-25 12:00:00', $AR ) ) );
check( 'Enero del año siguiente: sigue dentro (cruzó el fin de año)', true === mu_hero_banner_is_active_for_pais( $navidad, 'AR', ts( '2027-01-05 12:00:00', $AR ) ) );
check( 'Marzo: fuera', false === mu_hero_banner_is_active_for_pais( $navidad, 'AR', ts( '2027-03-05 12:00:00', $AR ) ) );
check( 'Agosto del mismo año: fuera', false === mu_hero_banner_is_active_for_pais( $navidad, 'AR', ts( '2026-08-05 12:00:00', $AR ) ) );

check( 'Primer segundo del inicio inclusive', true === mu_hero_banner_is_active_for_pais( $navidad, 'AR', ts( '2026-12-20 00:00:00', $AR ) ) );
check( 'Un segundo antes del inicio, no', false === mu_hero_banner_is_active_for_pais( $navidad, 'AR', ts( '2026-12-20 00:00:00', $AR ) - 1 ) );
check( 'Último segundo del fin inclusive', true === mu_hero_banner_is_active_for_pais( $navidad, 'AR', ts( '2027-01-10 23:59:59', $AR ) ) );
check( 'Un segundo después del fin, no', false === mu_hero_banner_is_active_for_pais( $navidad, 'AR', ts( '2027-01-11 00:00:00', $AR ) ) );

same(
    'La instancia vigente en enero es la que empezó en diciembre',
    [ ts( '2026-12-20 00:00:00', $AR ), ts( '2027-01-11 00:00:00', $AR ) - 1 ],
    mu_hero_banner_ventana_instantes( $navidad, 'AR', ts( '2027-01-05 12:00:00', $AR ) )
);

// El año guardado es irrelevante con el flag activo.
$navidad_vieja = $navidad;
$navidad_vieja['inicio'] = '20122015';
$navidad_vieja['fin']    = '10012016';
same(
    'El año guardado no cambia la ventana',
    mu_hero_banner_ventana_instantes( $navidad, 'AR', ts( '2027-01-05 12:00:00', $AR ) ),
    mu_hero_banner_ventana_instantes( $navidad_vieja, 'AR', ts( '2027-01-05 12:00:00', $AR ) )
);

// Temporada escolar: 01/06 → 30/06, sin cruzar el año.
$escolar = [ 'id' => 'escolar', 'anual' => '1', 'inicio' => '01062026', 'fin' => '30062026', 'paises' => [ 'AR' ] ];
check( 'Junio de 2027: activo aunque la fecha guardada sea de 2026', true === mu_hero_banner_is_active_for_pais( $escolar, 'AR', ts( '2027-06-15 12:00:00', $AR ) ) );
check( 'Febrero de 2027: todavía no', false === mu_hero_banner_is_active_for_pais( $escolar, 'AR', ts( '2027-02-15 12:00:00', $AR ) ) );
check( 'Agosto de 2027: ya pasó (la instancia fue en junio)', false === mu_hero_banner_is_active_for_pais( $escolar, 'AR', ts( '2027-08-15 12:00:00', $AR ) ) );
same(
    'Con el ciclo cerrado, la ventana devuelta es la del año siguiente',
    [ ts( '2028-06-01 00:00:00', $AR ), ts( '2028-07-01 00:00:00', $AR ) - 1 ],
    mu_hero_banner_ventana_instantes( $escolar, 'AR', ts( '2027-08-15 12:00:00', $AR ) )
);

// 29/02: se guarda desde un año bisiesto real (checkdate lo exige) y se repite.
$bisiesto = [ 'id' => 'bisiesto', 'anual' => '1', 'inicio' => '29022024', 'fin' => '01032024', 'paises' => [ 'AR' ] ];
same(
    'En un año no bisiesto (2027) el 29/02 se acota al 28/02',
    ts( '2027-02-28 00:00:00', $AR ),
    mu_hero_banner_ventana_instantes( $bisiesto, 'AR', ts( '2027-02-28 12:00:00', $AR ) )[0] ?? 0
);
check( 'Y ese 28/02 el banner está activo', true === mu_hero_banner_is_active_for_pais( $bisiesto, 'AR', ts( '2027-02-28 12:00:00', $AR ) ) );
check( 'El 27/02 todavía no', false === mu_hero_banner_is_active_for_pais( $bisiesto, 'AR', ts( '2027-02-27 12:00:00', $AR ) ) );
same(
    'En un año bisiesto (2028) el 29/02 sí existe',
    ts( '2028-02-29 00:00:00', $AR ),
    mu_hero_banner_ventana_instantes( $bisiesto, 'AR', ts( '2028-02-29 12:00:00', $AR ) )[0] ?? 0
);

// Cambio de horario de verano en Chile: el 06/09/2026 el día local tiene 23 h.
$chile     = [ 'id' => 'chile', 'anual' => '1', 'inicio' => '05092026', 'fin' => '07092026', 'paises' => [ 'CL' ] ];
$ventana_cl = mu_hero_banner_ventana_instantes( $chile, 'CL', ts( '2026-09-06 12:00:00', 'America/Santiago' ) );
same( 'La ventana anual de Chile dura 71 h (DST-safe)', 71 * 3600, ( $ventana_cl[1] ?? 0 ) - ( $ventana_cl[0] ?? 0 ) + 1 );

$chile_fija = $chile;
unset( $chile_fija['anual'] );
same(
    'La ventana fija del mismo año coincide (sin regresión de 010)',
    $ventana_cl,
    mu_hero_banner_ventana_instantes( $chile_fija, 'CL', ts( '2026-09-06 12:00:00', 'America/Santiago' ) )
);

same( 'Anual sin fechas: sin límite', null, mu_hero_banner_ventana_instantes( [ 'anual' => '1' ], 'AR', ts( '2027-01-05 12:00:00', $AR ) ) );
same( 'Anual con una sola fecha: sin límite (el panel lo avisa)', null, mu_hero_banner_ventana_instantes( [ 'anual' => '1', 'inicio' => '01062026' ], 'AR', ts( '2027-01-05 12:00:00', $AR ) ) );

echo "\n--- 3. Próximo borde y TTL del transient ---\n";
same(
    'Dentro de la ventana, el próximo borde es el fin (inclusive + 1 s)',
    ts( '2027-01-10 23:59:59', $AR ) + 1,
    mu_hero_banner_proximo_borde( $navidad, 'AR', ts( '2027-01-05 12:00:00', $AR ) )
);
same(
    'Con el ciclo cerrado, el próximo borde es el inicio del año siguiente',
    ts( '2027-12-20 00:00:00', $AR ),
    mu_hero_banner_proximo_borde( $navidad, 'AR', ts( '2027-03-05 12:00:00', $AR ) )
);
same(
    'Ventana anual sin cruce, ciclo cerrado: borde = inicio del año siguiente',
    ts( '2028-06-01 00:00:00', $AR ),
    mu_hero_banner_proximo_borde( $escolar, 'AR', ts( '2027-08-15 12:00:00', $AR ) )
);
same( 'Sin ventana no hay borde', 0, mu_hero_banner_proximo_borde( [ 'anual' => '1' ], 'AR', ts( '2027-03-05 12:00:00', $AR ) ) );

// TTL: un banner anual activo hoy tiene su próximo borde en el día siguiente.
$GLOBALS['mu_transient']     = [];
$GLOBALS['mu_transient_ttl'] = [];
$GLOBALS['mu_pais']          = 'AR';

$hoy_anual = [
    'id'     => 'corto-anual',
    'titulo' => 'C',
    'anual'  => '1',
    'paises' => [ 'AR' ],
    'inicio' => date( 'dmY', time() - 86400 ),
    'fin'    => date( 'dmY', time() + 86400 ),
];
$GLOBALS['mu_option'][ MU_HERO_BANNERS_OPTION ] = [ $hoy_anual ];

$antes = time();
mu_get_hero_banners();
$ttl    = $GLOBALS['mu_transient_ttl']['mu_hero_banners_active_AR'] ?? 0;
$borde  = mu_hero_banner_proximo_borde( $hoy_anual, 'AR', $antes );
$espera = max( 60, min( 12 * 3600, $borde - $antes + 1 ) );
check(
    'El TTL se acorta al próximo borde anual (±5 s) y nunca pasa de 12 h',
    abs( $ttl - $espera ) <= 5 && $ttl <= 12 * 3600,
    'ttl=' . $ttl . ' esperado≈' . $espera
);

echo "\n--- 4. Herencia del flag y saneado ---\n";
check( 'Sin flag: puntual', false === mu_hero_banner_anual_efectivo( [ 'id' => 'x' ], 'AR' ) );
check( 'General anual: el país sin declaración hereda', true === mu_hero_banner_anual_efectivo( [ 'anual' => '1' ], 'AR' ) );
check( 'General anual + país puntual: manda el país', false === mu_hero_banner_anual_efectivo( [ 'anual' => '1', 'fechas' => [ 'BR' => [ 'anual' => '0' ] ] ], 'BR' ) );
check( 'General puntual + país anual: manda el país', true === mu_hero_banner_anual_efectivo( [ 'anual' => '0', 'fechas' => [ 'BR' => [ 'anual' => '1' ] ] ], 'BR' ) );
check( 'General anual + país que declara 1: anual', true === mu_hero_banner_anual_efectivo( [ 'anual' => '1', 'fechas' => [ 'BR' => [ 'anual' => '1' ] ] ], 'BR' ) );
check( 'Otro país sigue heredando la general', true === mu_hero_banner_anual_efectivo( [ 'anual' => '1', 'fechas' => [ 'BR' => [ 'anual' => '0' ] ] ], 'MX' ) );
check( "'fechas' corrupto no rompe", false === mu_hero_banner_anual_efectivo( [ 'fechas' => 'x' ], 'AR' ) );

$anual_clean = mu_hero_banners_sanitize_one( [
    'id'     => 'demo',
    'titulo' => 'Hola',
    'anual'  => '1',
    'inicio' => '20122026',
    'fin'    => '10012027',
    'paises' => [ 'AR', 'BR', 'MX' ],
    'fechas' => [
        'BR' => [ 'anual' => '0' ],                                                // sólo el flag, sin fechas propias
        'MX' => [ 'anual' => '1', 'inicio' => '01032026', 'fin' => '31032026' ],   // flag heredado + fechas propias
    ],
] );
same( 'El flag general se guarda explícito', '1', $anual_clean['anual'] );
same( 'País con decisión propia del flag: se persiste', '0', $anual_clean['fechas']['BR']['anual'] ?? '' );
check( 'Y la entrada del país no se pierde por no tener fechas', isset( $anual_clean['fechas']['BR'] ) );
same( 'País que solo hereda el flag: la clave no se persiste', false, array_key_exists( 'anual', $anual_clean['fechas']['MX'] ?? [] ) );
same( 'Sus fechas propias sí', '01032026', $anual_clean['fechas']['MX']['inicio'] ?? '' );
same( 'País sin nada propio no se guarda', false, isset( $anual_clean['fechas']['AR'] ) );

// Compatibilidad con la convención de 010: sin desviación del flag la entrada queda igual.
$sin_anual = mu_hero_banners_sanitize_one( [
    'titulo' => 'x',
    'paises' => [ 'BR' ],
    'fechas' => [ 'BR' => [ 'inicio' => '01062026', 'fin' => '30062026' ] ],
] );
same( 'Sin flag anual la entrada por país queda como antes', [ 'inicio' => '01062026', 'fin' => '30062026' ], $sin_anual['fechas']['BR'] );
same( 'Y el flag general queda en 0', '0', $sin_anual['anual'] );

echo "\n--- 5. Avisos ---\n";
same(
    'Banner sano: sin avisos',
    [ 'invalidas' => [], 'invertidas' => [], 'anuales_incompletas' => [] ],
    mu_hero_banner_avisos( [ 'id' => 'ok', 'inicio' => '01012026', 'fin' => '31122026' ] )
);
same(
    'Anual con una sola fecha en las generales',
    [ '' ],
    mu_hero_banner_avisos( [ 'anual' => '1', 'inicio' => '01062026', 'fin' => '' ] )['anuales_incompletas']
);
same(
    'Anual con una sola fecha en un país',
    [ 'BR' ],
    mu_hero_banner_avisos( [ 'fechas' => [ 'BR' => [ 'anual' => '1', 'inicio' => '01062026', 'fin' => '' ] ] ] )['anuales_incompletas']
);
same(
    'Con el flag, cruzar el fin de año NO es un rango invertido',
    [],
    mu_hero_banner_avisos( [ 'anual' => '1', 'inicio' => '20122026', 'fin' => '10012027' ] )['invertidas']
);
same(
    'Sin el flag, el mismo rango sí se avisa como invertido',
    [ '' ],
    mu_hero_banner_avisos( [ 'inicio' => '31122026', 'fin' => '01012026' ] )['invertidas']
);
same(
    'Una fecha inválida sigue mandando sobre el flag',
    [ 'BR' ],
    mu_hero_banner_avisos( [ 'fechas' => [ 'BR' => [ 'anual' => '1', 'inicio' => '99012026', 'fin' => '' ] ] ] )['invalidas']
);

echo "\n--- 6. Marcado del panel (fila renderizada de verdad) ---\n";
$row_anual = [
    'id'     => 'navidad',
    'titulo' => 'Navidad',
    'anual'  => '1',
    'inicio' => '20122026',
    'fin'    => '10012027',
    'paises' => [ 'AR', 'BR' ],
    'fechas' => [ 'BR' => [ 'anual' => '0' ] ],
];
ob_start();
mu_hero_banners_render_row( 0, $row_anual );
$html = ob_get_clean();

same( 'Casilla del flag general (campo oculto + casilla)', 2, substr_count( $html, 'name="mu_banner[0][anual]"' ) );
same( 'Casilla del flag por país (campo oculto + casilla)', 2, substr_count( $html, 'name="mu_banner[0][fechas][BR][anual]"' ) );
same( 'El estado apagado viaja igual aunque la casilla no se marque', 1, substr_count( $html, 'type="hidden" name="mu_banner[0][fechas][BR][anual]"' ) );
same( 'Una casilla por ámbito (Global + los 7 países)', 8, substr_count( $html, 'data-role="anual"' ) );
check( 'La casilla general nace tildada', (bool) preg_match( '/type="checkbox" name="mu_banner\[0\]\[anual\]"[^>]*checked/', $html ) );
check( 'La del país que lo apaga nace destildada', (bool) preg_match( '/type="checkbox" name="mu_banner\[0\]\[fechas\]\[BR\]\[anual\]"(?![^>]*checked)/', $html ) );
check( 'La del país que hereda nace tildada', (bool) preg_match( '/type="checkbox" name="mu_banner\[0\]\[fechas\]\[MX\]\[anual\]"[^>]*checked/', $html ) );
check( 'La fila explica la repetición', false !== strpos( $html, 'Se repite cada año' ) );
check( 'La fila del ámbito anual lo dice en la columna «Se ve» (feature 013)', false !== strpos( $html, '· cada año' ) );
same( 'Una fila sana no muestra avisos', 0, substr_count( $html, 'mu-hero-banner-row__alert' ) );
same( 'Sigue habiendo una fila de vigencia por ámbito (invariante de 010)', 8, substr_count( $html, '<tr class="mu-hero-banner-fechas-block' ) );
same( 'Los campos de fecha siguen limitados a 8 caracteres (invariante de 010)', 16, substr_count( $html, 'maxlength="8"' ) );
same( 'Ningún campo usa pattern (invariante de 010)', 0, substr_count( $html, 'pattern=' ) );

$row_incompleta = [
    'id'     => 'mala',
    'titulo' => 'Mala',
    'anual'  => '1',
    'inicio' => '20122026',
    'fin'    => '',
    'paises' => [ 'AR' ],
];
ob_start();
mu_hero_banners_render_row( 1, $row_incompleta );
$html_mala = ob_get_clean();
same( 'La fila con repetición incompleta avisa', 1, substr_count( $html_mala, 'mu-hero-banner-row__alert' ) );
check( 'Y el aviso dice qué completar', false !== strpos( $html_mala, 'la repetición anual quedó con una sola fecha' ) );

echo "\n--- 7. Purga de la home en el borde (evento único del cron) ---\n";
check(
    'El hook del borde está enganchado al ejecutor',
    in_array( 'mu_hero_banner_purge_borde_ejecutar', (array) ( $GLOBALS['mu_hooks']['mu_hero_banner_purge_borde'] ?? [] ), true )
);

$GLOBALS['mu_cron'] = [];
$GLOBALS['mu_option'][ MU_HERO_BANNERS_OPTION ] = [ $hoy_anual ];
mu_hero_banner_programar_purga_borde();
$programado = $GLOBALS['mu_cron']['mu_hero_banner_purge_borde'] ?? 0;
same( 'Se programa un evento único en el próximo borde del sitio', mu_hero_banner_proximo_borde_global(), $programado );

mu_hero_banner_programar_purga_borde();
same( 'Llamarlo de nuevo no mueve el evento', $programado, $GLOBALS['mu_cron']['mu_hero_banner_purge_borde'] ?? 0 );

$GLOBALS['mu_option'][ MU_HERO_BANNERS_OPTION ] = [
    [ 'id' => 'lejos', 'titulo' => 'L', 'anual' => '1', 'paises' => [ 'AR' ], 'inicio' => '01062026', 'fin' => '30062026' ],
];
mu_hero_banner_reprogramar_purga_borde();
check( 'Al guardar, el evento se recalcula (no queda el viejo)', ( $GLOBALS['mu_cron']['mu_hero_banner_purge_borde'] ?? 0 ) !== $programado );

// Ejecución sin el hook por URL: cae a la purga total (sólo en un borde real).
$GLOBALS['mu_actions'] = [];
$GLOBALS['mu_transient']['mu_hero_banners_active_AR'] = [ 'x' ];
mu_hero_banner_purge_borde_ejecutar( false );
check( 'Sin hook por URL se usa la purga total', in_array( 'litespeed_purge_all', (array) $GLOBALS['mu_actions'], true ) );
check( 'Y no se registró ninguna purga por URL', ! in_array( 'litespeed_purge_url', (array) $GLOBALS['mu_actions'], true ) );
check( 'Los transients por país quedan limpios', ! array_key_exists( 'mu_hero_banners_active_AR', (array) $GLOBALS['mu_transient'] ) );

// Con el hook por URL disponible: una purga por host, con la home de cada uno.
$GLOBALS['mu_hooks']['litespeed_purge_url'][] = 'litespeed-cache';
$GLOBALS['mu_actions'] = [];
$GLOBALS['mu_transient']['mu_hero_banners_active_BR'] = [ 'x' ];
mu_hero_banner_purge_borde_ejecutar( false );
same( 'Con el hook por URL se purga una vez por host', 6, count( array_keys( (array) $GLOBALS['mu_actions'], 'litespeed_purge_url', true ) ) );
check( 'Y no se purga todo', ! in_array( 'litespeed_purge_all', (array) $GLOBALS['mu_actions'], true ) );
$urls = array_column( (array) ( $GLOBALS['mu_action_args']['litespeed_purge_url'] ?? [] ), 1 );
check( 'Las URLs son la home de cada host del sitio', in_array( 'https://muyunicos.com/', $urls, true ) && in_array( 'https://br.muyunicos.com/', $urls, true ) );
check( 'El rastro [MU-HERO-BORDE] queda en el log', false !== strpos( (string) @file_get_contents( $MU_LOG ), '[MU-HERO-BORDE]' ) );

echo "\n--- 8. No-regresión de la ventana fija (feature 010) ---\n";
$fija = [ 'id' => 'fija', 'inicio' => '01062026', 'fin' => '30062026', 'paises' => [ 'AR' ] ];
same(
    'Ventana fija: mismos instantes que en 010',
    [ ts( '2026-06-01 00:00:00', $AR ), ts( '2026-07-01 00:00:00', $AR ) - 1 ],
    mu_hero_banner_ventana_instantes( $fija, 'AR', ts( '2026-06-15 12:00:00', $AR ) )
);
check( 'Junio de 2027: ya no (la ventana era de 2026)', false === mu_hero_banner_is_active_for_pais( $fija, 'AR', ts( '2027-06-15 12:00:00', $AR ) ) );
same( 'Y no deja borde futuro', 0, mu_hero_banner_proximo_borde( $fija, 'AR', ts( '2027-06-15 12:00:00', $AR ) ) );
same( 'Fecha inválida guardada: sin límite (M3)', null, mu_hero_banner_ventana_instantes( [ 'inicio' => '99012026', 'fin' => '31122026' ], 'AR', ts( '2026-06-15 12:00:00', $AR ) ) );
same( 'Sin fechas: sin límite', null, mu_hero_banner_ventana_instantes( [ 'id' => 'x' ], 'AR', ts( '2026-06-15 12:00:00', $AR ) ) );

// Integración con el filtro del front: la ventana anual vuelve todos los años.
$GLOBALS['mu_transient']     = [];
$GLOBALS['mu_transient_ttl'] = [];
$GLOBALS['mu_pais']          = 'AR';
$GLOBALS['mu_option'][ MU_HERO_BANNERS_OPTION ] = [
    [ 'id' => 'anual-hoy', 'titulo' => 'A', 'anual' => '1', 'paises' => [ 'AR' ], 'inicio' => date( 'dmY', time() - 86400 ), 'fin' => date( 'dmY', time() + 86400 ) ],
    [ 'id' => 'anual-lejos', 'titulo' => 'B', 'anual' => '1', 'paises' => [ 'AR' ], 'inicio' => date( 'dmY', time() + ( 200 * 86400 ) ), 'fin' => date( 'dmY', time() + ( 210 * 86400 ) ) ],
];
same( 'El front ve sólo el banner anual de hoy', [ 'anual-hoy' ], array_column( mu_get_hero_banners(), 'id' ) );

echo "\n--- 11. Red de seguridad diaria del borde (015) ---\n";

// Escenario: un banner anual cuya ventana cierra mañana ⇒ hay un borde FUTURO.
$GLOBALS['mu_transient']     = [];
$GLOBALS['mu_transient_ttl'] = [];
$GLOBALS['mu_option']        = [];
$GLOBALS['mu_cron']          = [];
$GLOBALS['mu_actions']       = [];
$GLOBALS['mu_pais']          = 'AR';

$GLOBALS['mu_option'][ MU_HERO_BANNERS_OPTION ] = [ [
    'id'     => 'borde',
    'titulo' => 'B',
    'anual'  => '1',
    'paises' => [ 'AR' ],
    'inicio' => date( 'dmY', time() - 86400 ),
    'fin'    => date( 'dmY', time() + 86400 ),
] ];

$borde_real = mu_hero_banner_proximo_borde_global();
check( 'El escenario tiene un borde futuro', $borde_real > time() );

// (a) Caso normal: el evento está agendado para ese borde y la opción lo recuerda.
mu_hero_banner_programar_purga_borde();
same( 'Al programar se recuerda el borde esperado', $borde_real, (int) ( $GLOBALS['mu_option']['mu_hero_banner_borde_esperado'] ?? 0 ) );

$GLOBALS['mu_actions'] = [];
mu_hero_banner_revisar_bordes();
same( 'Caso normal: la revisión NO purga', [], $GLOBALS['mu_actions'] );
same( 'Y deja el evento donde estaba', $borde_real, (int) ( $GLOBALS['mu_cron']['mu_hero_banner_purge_borde'] ?? 0 ) );

// (b) Borde perdido: se esperaba un borde que ya pasó y no hay evento pendiente.
$GLOBALS['mu_cron']    = [];
$GLOBALS['mu_actions'] = [];
$GLOBALS['mu_option']['mu_hero_banner_borde_esperado'] = time() - 3600;   // el evento se perdió

mu_hero_banner_revisar_bordes();
check( 'Borde perdido: la revisión purga como red de seguridad', $GLOBALS['mu_actions'] !== [] );
same( 'Y rearma el evento en el próximo borde', $borde_real, (int) ( $GLOBALS['mu_cron']['mu_hero_banner_purge_borde'] ?? 0 ) );
check( 'Y deja registrada la última purga (para el panel)', ! empty( $GLOBALS['mu_option']['mu_hero_banner_ultima_purga']['ts'] ) );
same( 'Con su modo (por URL, que es como corre en producción)', 'url', $GLOBALS['mu_option']['mu_hero_banner_ultima_purga']['modo'] ?? '' );
check( 'Y con la cantidad de hosts', (int) ( $GLOBALS['mu_option']['mu_hero_banner_ultima_purga']['hosts'] ?? 0 ) > 0 );

// (b2) Sin el hook de purga por URL, el modo registrado es el respaldo (purga total).
unset( $GLOBALS['mu_hooks']['litespeed_purge_url'] );
$GLOBALS['mu_cron']    = [];
$GLOBALS['mu_actions'] = [];
$GLOBALS['mu_option']['mu_hero_banner_borde_esperado'] = time() - 3600;

mu_hero_banner_revisar_bordes();
same( 'Sin purga por URL, el modo registrado es el respaldo', 'all', $GLOBALS['mu_option']['mu_hero_banner_ultima_purga']['modo'] ?? '' );
check( 'Y purga todo el sitio', in_array( 'litespeed_purge_all', (array) $GLOBALS['mu_actions'], true ) );

// (c) Evento pendiente para un borde viejo: se rearma, pero no se purga (no hay borde atrasado).
$GLOBALS['mu_cron']    = [ 'mu_hero_banner_purge_borde' => time() - 7200 ];
$GLOBALS['mu_actions'] = [];
$GLOBALS['mu_option']['mu_hero_banner_borde_esperado'] = time() + 100000;   // futuro

mu_hero_banner_revisar_bordes();
same( 'Con borde esperado futuro no purga', [], $GLOBALS['mu_actions'] );
same( 'Pero el evento viejo queda rearmado en el borde real', $borde_real, (int) ( $GLOBALS['mu_cron']['mu_hero_banner_purge_borde'] ?? 0 ) );

// (d) Enganches.
check(
    'El hook de la revisión diaria está enganchado',
    in_array( 'mu_hero_banner_revisar_bordes', (array) ( $GLOBALS['mu_hooks']['mu_hero_banner_revisar_bordes_hook'] ?? [] ), true )
);
check(
    'Y el init agenda el evento diario (con guard)',
    in_array( 'closure', (array) ( $GLOBALS['mu_hooks']['init'] ?? [] ), true )
);

echo "\n== {$GLOBALS['mu_ok']} OK / {$GLOBALS['mu_fail']} FAIL ==\n";
exit( $GLOBALS['mu_fail'] > 0 ? 1 : 0 );

