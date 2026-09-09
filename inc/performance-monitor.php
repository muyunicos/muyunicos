<?php
/**
 * Muy Únicos — Performance Monitoring
 *
 * Sistema básico de monitoreo de rendimiento:
 * - Logging de funciones críticas (geolocalización, AJAX)
 * - Métricas de cache hit rates
 * - Admin dashboard widget con estadísticas
 * - Integración con WordPress debug log
 *
 * @package GeneratePress_Child
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================
// PERFORMANCE LOGGING CONFIGURATION
// ============================================

if ( ! function_exists( 'mu_perf_is_enabled' ) ) {
    /**
     * Verifica si el monitoreo de rendimiento está habilitado
     * Solo habilitado para admin o si WP_DEBUG está activo
     *
     * @return bool True si el monitoreo está habilitado
     */
    function mu_perf_is_enabled() {
        return false; // current_user_can( 'manage_options' ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG );
    }
}

if ( ! function_exists( 'mu_perf_log' ) ) {
    /**
     * Escribe mensaje de performance en debug log
     *
     * @param string $message Mensaje a loggear
     * @param array $context Datos adicionales del contexto
     */
    function mu_perf_log( $message, $context = [] ) {
        if ( ! mu_perf_is_enabled() ) {
            return;
        }

        $timestamp = current_time( 'mysql' );
        $context_str = ! empty( $context ) ? ' ' . json_encode( $context ) : '';
        
        error_log( "[MU-PERF] {$timestamp} - {$message}{$context_str}" );
    }
}

// ============================================
// GEOLOCATION CACHE MONITORING
// ============================================

if ( ! function_exists( 'mu_monitor_geolocation_cache' ) ) {
    /**
     * Monitorea el uso de caché de geolocalización
     * Rastrea hits, misses y tiempos de respuesta.
     *
     * Retorna por referencia para que los contadores persistan entre llamadas.
     *
     * @return array Estadísticas de caché
     */
    function &mu_monitor_geolocation_cache() {
        static $cache_stats = [
            'hits' => 0,
            'misses' => 0,
            'cookie_hits' => 0,
            'transient_hits' => 0,
            'db_hits' => 0,
        ];

        return $cache_stats;
    }

    /**
     * Registra un hit de caché de geolocalización.
     * Solo actualiza stats en memoria (para el widget del dashboard).
     * NO escribe al debug log — evita saturar el servidor con 200+ líneas/segundo.
     *
     * @param string $source Fuente del caché ('domain', 'cookie', 'transient', 'db')
     */
    function mu_log_geolocation_cache_hit( $source ) {
        if ( ! mu_perf_is_enabled() ) {
            return;
        }

        $stats = &mu_monitor_geolocation_cache();
        $stats['hits']++;
        
        switch ( $source ) {
            case 'domain':
                // Los hits por dominio son los más comunes — no tienen contador propio
                break;
            case 'cookie':
                $stats['cookie_hits']++;
                break;
            case 'transient':
                $stats['transient_hits']++;
                break;
            case 'db':
                $stats['db_hits']++;
                break;
        }
    }

    /**
     * Registra un miss de caché de geolocalización.
     * Log con rate limiting: máximo 1 línea por minuto para no saturar el log.
     */
    function mu_log_geolocation_cache_miss() {
        if ( ! mu_perf_is_enabled() ) {
            return;
        }

        $stats = &mu_monitor_geolocation_cache();
        $stats['misses']++;

        // Rate limiting: máximo 1 log de miss por minuto
        static $last_miss_log = 0;
        if ( ( time() - $last_miss_log ) < MINUTE_IN_SECONDS ) {
            return;
        }
        $last_miss_log = time();

        mu_perf_log( 'Geolocation cache miss', [ 'stats' => $stats ] );
    }
}

// ============================================
// AJAX PERFORMANCE MONITORING
// ============================================

if ( ! function_exists( 'mu_monitor_ajax_performance' ) ) {
    /**
     * Monitorea el rendimiento de llamadas AJAX
     * Rastrea tiempos de respuesta y frecuencia
     */
    function mu_monitor_ajax_performance( $action, $start_time, $success = true ) {
        if ( ! mu_perf_is_enabled() ) {
            return;
        }

        $execution_time = microtime( true ) - $start_time;
        
        mu_perf_log( 'AJAX performance', [
            'action' => $action,
            'execution_time' => round( $execution_time * 1000, 2 ), // ms
            'success' => $success,
            'memory' => memory_get_peak_usage( true )
        ] );

        // Alerta si el tiempo de ejecución es excesivo
        if ( $execution_time > 2.0 ) {
            mu_perf_log( 'SLOW AJAX detected', [
                'action' => $action,
                'execution_time' => round( $execution_time * 1000, 2 ) . 'ms'
            ] );
        }
    }
}

// ============================================
// RATE LIMITING MONITORING
// ============================================

if ( ! function_exists( 'mu_monitor_rate_limit' ) ) {
    /**
     * Monitorea cuando se activa el rate limiting
     * Útil para detectar posibles ataques o abusos
     */
    function mu_monitor_rate_limit( $action, $ip ) {
        if ( ! mu_perf_is_enabled() ) {
            return;
        }

        mu_perf_log( 'Rate limit triggered', [
            'action' => $action,
            'ip' => $ip,
            'time' => current_time( 'mysql' )
        ] );
    }
}

// ============================================
// ADMIN DASHBOARD WIDGET
// ============================================

if ( ! function_exists( 'mu_perf_dashboard_widget' ) ) {
    /**
     * Agrega widget de performance al dashboard de admin
     */
    function mu_perf_dashboard_widget() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        wp_add_dashboard_widget(
            'mu_performance_widget',
            'Muy Únicos - Performance Monitor',
            'mu_perf_render_dashboard_widget'
        );
    }
    add_action( 'wp_dashboard_setup', 'mu_perf_dashboard_widget' );
}

if ( ! function_exists( 'mu_bot404_get_stats' ) ) {
    /**
     * Lee los contadores de bot-404 fast-exit desde el archivo plano.
     *
     * Los contadores los escribe mu_bot404_record_hit() (inc/compat-litespeed.php)
     * en wp-content/uploads/mu-logs/bot404-stats.json — cero consultas SQL.
     * [Fix object-cache] Se migró de Memcached a archivo plano porque el
     * object cache de LiteSpeed persiste en shutdown, que el exit del
     * fast-exit salta (los contadores se reseteaban en cada petición).
     *
     * @return array{daily:int,total:int}
     */
    function mu_bot404_get_stats(): array {
        if ( ! function_exists( 'mu_bot404_stats_file' ) ) {
            return [ 'daily' => 0, 'total' => 0 ];
        }

        $file = mu_bot404_stats_file();
        if ( ! is_readable( $file ) ) {
            return [ 'daily' => 0, 'total' => 0 ];
        }

        $decoded = json_decode( (string) file_get_contents( $file ), true );
        if ( ! is_array( $decoded ) ) {
            return [ 'daily' => 0, 'total' => 0 ];
        }

        // Reset del contador diario si el archivo quedó de un día anterior.
        $today = gmdate( 'Ymd' );
        $daily = ( isset( $decoded['day'] ) && (string) $decoded['day'] === $today )
            ? (int) $decoded['daily']
            : 0;

        return [
            'daily' => $daily,
            'total' => (int) ( $decoded['total'] ?? 0 ),
        ];
    }
}

if ( ! function_exists( 'mu_perf_render_dashboard_widget' ) ) {
    /**
     * Renderiza el contenido del widget de performance
     */
    function mu_perf_render_dashboard_widget() {
        $geo_stats = mu_monitor_geolocation_cache();
        $total_requests = $geo_stats['hits'] + $geo_stats['misses'];
        $cache_hit_rate = $total_requests > 0 ? round( ( $geo_stats['hits'] / $total_requests ) * 100, 2 ) : 0;

        // Estadísticas de bot-404 fast-exit (anti-bots)
        $bot404 = function_exists( 'mu_bot404_get_stats' )
            ? mu_bot404_get_stats()
            : [ 'daily' => 0, 'total' => 0 ];

        // Estadísticas de rebuilds de índices
        $last_rebuild_schedule = get_option( 'muyu_digital_last_rebuild_schedule', 0 );
        $last_rebuild_time = $last_rebuild_schedule > 0 ? human_time_diff( $last_rebuild_schedule, current_time( 'timestamp' ) ) . ' ago' : 'Never';
        $cooldown_remaining = 0;
        if ( $last_rebuild_schedule > 0 ) {
            $cooldown_remaining = max( 0, HOUR_IN_SECONDS - ( current_time( 'timestamp' ) - $last_rebuild_schedule ) );
        }

        ?>
        <div class="mu-perf-widget">
            <h3>Digital Index Rebuild Stats</h3>
            <table class="wp-list-table widefat fixed striped">
                <tbody>
                    <tr>
                        <td><strong>Last Rebuild Schedule:</strong></td>
                        <td><?php echo esc_html( $last_rebuild_time ); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Cooldown Remaining:</strong></td>
                        <td><?php echo $cooldown_remaining > 0 ? round( $cooldown_remaining / 60, 1 ) . ' min' : 'Ready'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Rebuild Cooldown:</strong></td>
                        <td>1 hour</td>
                    </tr>
                </tbody>
            </table>

            <h3>Geolocation Cache Stats</h3>
            <table class="wp-list-table widefat fixed striped">
                <tbody>
                    <tr>
                        <td><strong>Total Requests:</strong></td>
                        <td><?php echo number_format( $total_requests ); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Cache Hit Rate:</strong></td>
                        <td><?php echo $cache_hit_rate; ?>%</td>
                    </tr>
                    <tr>
                        <td><strong>Cookie Hits:</strong></td>
                        <td><?php echo number_format( $geo_stats['cookie_hits'] ); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Transient Hits:</strong></td>
                        <td><?php echo number_format( $geo_stats['transient_hits'] ); ?></td>
                    </tr>
                    <tr>
                        <td><strong>DB Hits:</strong></td>
                        <td><?php echo number_format( $geo_stats['db_hits'] ); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Cache Misses:</strong></td>
                        <td><?php echo number_format( $geo_stats['misses'] ); ?></td>
                    </tr>
                </tbody>
            </table>

            <h3>Bot 404 Fast-Exit (Anti-Bots)</h3>
            <table class="wp-list-table widefat fixed striped">
                <tbody>
                    <tr>
                        <td><strong>Bloqueados hoy:</strong></td>
                        <td><?php echo number_format( $bot404['daily'] ); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Total acumulado:</strong></td>
                        <td><?php echo number_format( $bot404['total'] ); ?></td>
                    </tr>
                </tbody>
            </table>
            <p class="description">
                <em>Peticiones 404 de bots servidas con HTML mínimo sin tocar MySQL.
                Verificar con: curl -sI -A "GPTBot" https://us.muyunicos.com/pt/outlet</em>
            </p>

            <h3>System Info</h3>
            <table class="wp-list-table widefat fixed striped">
                <tbody>
                    <tr>
                        <td><strong>PHP Version:</strong></td>
                        <td><?php echo PHP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Memory Limit:</strong></td>
                        <td><?php echo ini_get( 'memory_limit' ); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Current Memory:</strong></td>
                        <td><?php echo size_format( memory_get_usage( true ) ); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Peak Memory:</strong></td>
                        <td><?php echo size_format( memory_get_peak_usage( true ) ); ?></td>
                    </tr>
                </tbody>
            </table>

            <p class="description">
                <em>Stats are for current session only. Check debug.log for detailed performance data.</em>
            </p>
        </div>
        <style>
            .mu-perf-widget h3 {
                margin-top: 15px;
                margin-bottom: 10px;
            }
            .mu-perf-widget table {
                margin-bottom: 15px;
            }
        </style>
        <?php
    }
}

// ============================================
// INTEGRATION WITH EXISTING FUNCTIONS
// ============================================

// Hook into geolocation functions for monitoring
add_action( 'muyu_geolocation_cache_hit', function( $source ) {
    mu_log_geolocation_cache_hit( $source );
}, 10, 1 );

add_action( 'muyu_geolocation_cache_miss', function() {
    mu_log_geolocation_cache_miss();
});

// Hook into rate limiting for monitoring
add_action( 'mu_rate_limit_triggered', function( $action, $ip ) {
    mu_monitor_rate_limit( $action, $ip );
}, 10, 2 );