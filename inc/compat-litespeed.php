<?php
/**
 * Muy Únicos — Compatibilidad LiteSpeed Cache
 *
 * Problema: gla-gtag-events.js (Google Listings & Ads) depende de window.wp.hooks
 * (wp-hooks handle). Cuando LiteSpeed aplica "Load JS Delayed", ejecuta los scripts
 * en orden asíncrono sin respetar el árbol de dependencias de wp_register_script.
 * En visitantes (sin admin bar), wp-hooks no se carga antes que gtag-events.js,
 * resultando en: "Uncaught TypeError: Cannot read properties of undefined (reading 'hooks')"
 *
 * Solución: excluir los handles problemáticos del JS Delay de LiteSpeed vía filtro PHP,
 * forzando su carga en el orden normal del navegador.
 *
 * NO toca la config del plugin LiteSpeed (que se resetea con actualizaciones).
 *
 * @package GeneratePress_Child
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Excluye scripts críticos con dependencias de @wordpress/* del JS Delay de LiteSpeed.
 *
 * LiteSpeed Cache lee la opción 'litespeed.conf.optm-js_exc' pero también expone
 * el filtro 'litespeed_optimize_js_excludes' para exclusiones programáticas.
 * Usamos el filtro para mayor robustez (no depende de la configuración guardada).
 *
 * @param array $excludes Lista actual de patrones de exclusión.
 * @return array Lista ampliada.
 */
if ( ! function_exists( 'mu_litespeed_js_delay_excludes' ) ) {
    function mu_litespeed_js_delay_excludes( $excludes ) {
        /*
         * Excluir por fragmento de URL (LiteSpeed hace strpos contra la src del script).
         * - gtag-events.js        : GLA — necesita window.wp.hooks (wp-hooks)
         * - 101.js                : chunk interno de GLA que acompaña a gtag-events.js
         *
         * No excluir jquery.min.js ni wp-hooks completo ya que LiteSpeed los
         * gestiona bien cuando no tienen scripts dependientes siendo retrasados.
         */
        $mu_excludes = [
            'google-listings-and-ads/js/build/gtag-events.js',
            'google-listings-and-ads/js/build/101.js',
            'generatepress-child/js/jetpack-search.js',
            'generatepress-child/js/modal-auth.js',
        ];

        return array_merge( (array) $excludes, $mu_excludes );
    }
    add_filter( 'litespeed_optimize_js_excludes', 'mu_litespeed_js_delay_excludes' );
}

/**
 * Fuerza a LiteSpeed Cache a crear cachés separadas por subdominio.
 *
 * Evita Cache Collisions (contaminación cruzada de errores 404 de productos
 * físicos en otros países) y protege las etiquetas SEO Hreflang específicas
 * de cada país.
 *
 * Usa el filtro 'litespeed_vary' para agregar una regla de variación basada
 * en $_SERVER['HTTP_HOST']. Este es el método directo según la documentación
 * de LiteSpeed y no requiere cookies adicionales.
 *
 * Documentación: https://docs.litespeedtech.com/lscache/lscwp/api/
 *
 * @param array $vary Reglas de variación actuales.
 * @return array Reglas de variación ampliadas.
 */
if ( ! function_exists( 'mu_litespeed_vary_by_subdomain' ) ) {
    function mu_litespeed_vary_by_subdomain( $vary ) {
        if ( isset( $_SERVER['HTTP_HOST'] ) ) {
            // Limpiamos el host y lo agregamos como regla de variación
            $host = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
            $vary['mu_host'] = $host;
        }
        return $vary;
    }
    add_filter( 'litespeed_vary', 'mu_litespeed_vary_by_subdomain' );
}

/**
 * Evita que LiteSpeed Cache almacene páginas 404.
 *
 * Problema: fallos transitorios de infraestructura (max_user_connections,
 * "Commands out of sync", fatal de WCPBC) pueden hacer que una URL válida
 * (ej: /outlet/ en el dominio principal) genere un 404 momentáneo. Si
 * LiteSpeed cachea ese 404, lo sirve a todos los visitantes durante horas
 * aunque la URL ya vuelva a resolver correctamente.
 *
 * Al marcar las respuestas 404 como no cacheables, cada visita se resuelve
 * en PHP y el sitio se auto-recupera cuando la infraestructura se normaliza.
 */
if ( ! function_exists( 'mu_litespeed_nocache_404' ) ) {
    function mu_litespeed_nocache_404() {
        if ( is_404() ) {
            header( 'X-LiteSpeed-Cache-Control: no-cache' );
            header( 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0' );
            // [Optimización anti-bots] Indica a buscadores e indexadores de IA
            // que NO indexen las URLs 404. Reduce el rastreo futuro de rutas
            // inexistentes en subdominios restringidos.
            header( 'X-Robots-Tag: noindex, nofollow' );
        }
    }
    add_action( 'template_redirect', 'mu_litespeed_nocache_404', 5 );
}

/**
 * Detecta si la petición proviene de un bot/indexador/crawler.
 *
 * [Optimización anti-bots] Los rastreos masivos de IA (GPTBot, CCBot,
 * ClaudeBot, Bytespider, etc.) y SEO generan miles de peticiones a URLs
 * inexistentes que saturan PHP/MySQL. Esta detección es O(1): un solo
 * preg_match sobre el User-Agent, cacheado estáticamente por request.
 *
 * Usa wp_is_bot() del core cuando está disponible (WP 6.3+) y cae a un
 * regex propio con los crawlers más comunes en versiones anteriores.
 *
 * @return bool True si el User-Agent corresponde a un bot conocido.
 */
if ( ! function_exists( 'mu_is_bot' ) ) {
    function mu_is_bot(): bool {
        static $is_bot = null;

        if ( null !== $is_bot ) {
            return $is_bot;
        }

        if ( function_exists( 'wp_is_bot' ) ) {
            $is_bot = (bool) wp_is_bot();
        } else {
            $ua     = strtolower( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
            $is_bot = '' !== $ua && (bool) preg_match(
                '/bot|crawl|spider|slurp|gptbot|ccbot|claudebot|bytespider|'
                . 'googlebot|bingbot|yandexbot|ahrefs|semrush|mj12|dotbot|'
                . 'petalbot|applebot|facebookexternalhit|perplexitybot|'
                . 'amazonbot|python-requests|python-urllib|curl\/|wget|headless/i',
                $ua
            );
        }

        return $is_bot;
    }
}

/**
 * Ruta del archivo de estadísticas de bot-404 (archivo plano).
 *
 * [Fix object-cache] Los contadores vivían en Memcached, pero LiteSpeed
 * persiste su object cache durante el shutdown de WordPress — que el exit
 * del fast-exit salta. Evidencia en producción: cada petición bot arrancaba
 * de cero (daily=1 total=1 siempre) y el rate-limit nunca sostenía. Un
 * archivo plano con LOCK_EX sobrevive al exit, no toca MySQL y la escritura
 * es de ~100 bytes.
 *
 * @return string Ruta absoluta de wp-content/uploads/mu-logs/bot404-stats.json.
 */
if ( ! function_exists( 'mu_bot404_stats_file' ) ) {
    function mu_bot404_stats_file(): string {
        $upload = wp_upload_dir();
        $dir    = trailingslashit( $upload['basedir'] ) . 'mu-logs';

        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }

        return $dir . '/bot404-stats.json';
    }
}

/**
 * Registra un hit de bot-404 en archivo plano y decide si toca loguear.
 *
 * Contadores: daily (por día UTC) + total acumulado + timestamp del último
 * log para el rate-limit. Escritura con LOCK_EX (serializa escrituras
 * concurrentes de múltiples workers PHP). Best-effort: si la escritura
 * falla (permisos/disco), la telemetría se degrada pero el fast-exit
 * sigue funcionando.
 *
 * @return array{0:int,1:int,2:bool} [daily, total, should_log]
 */
if ( ! function_exists( 'mu_bot404_record_hit' ) ) {
    function mu_bot404_record_hit(): array {
        $file  = mu_bot404_stats_file();
        $today = gmdate( 'Ymd' );

        $stats = [
            'daily'    => 0,
            'total'    => 0,
            'day'      => $today,
            'last_log' => 0,
        ];

        if ( is_readable( $file ) ) {
            $decoded = json_decode( (string) file_get_contents( $file ), true );
            if ( is_array( $decoded ) ) {
                $stats = array_merge( $stats, $decoded );
            }
        }

        // Reset del contador diario al cambiar de día (UTC).
        if ( (string) $stats['day'] !== $today ) {
            $stats['daily'] = 0;
            $stats['day']   = $today;
        }

        $stats['daily']++;
        $stats['total']++;

        // Rate-limit: máximo 1 línea de log cada 5 minutos (global, todos
        // los workers comparten el timestamp vía el mismo archivo).
        $should_log = ( time() - (int) $stats['last_log'] ) >= 300;
        if ( $should_log ) {
            $stats['last_log'] = time();
        }

        @file_put_contents( $file, wp_json_encode( $stats ), LOCK_EX );

        return [ (int) $stats['daily'], (int) $stats['total'], $should_log ];
    }
}

/**
 * Salida instantánea de 404 para bots — evita render completo de WordPress.
 *
 * [Optimización anti-bots] Los bots/indexadores de IA rastrean miles de URLs
 * inexistentes (query strings basura, rutas viejas, combinaciones inválidas).
 * Sin esta mitigación, cada petición ejecuta WordPress completo: routing,
 * menús, widgets, render del tema y consultas SQL — saturando el servidor.
 *
 * Comportamiento:
 * - Corre en template_redirect prioridad 1 (ANTES que mu_litespeed_nocache_404
 *   en prioridad 5 y handle_redirects en prioridad 20).
 * - Si es bot + 404: responde HTML mínimo (~100 bytes) y hace exit inmediato.
 *   No se renderiza el tema ni se consultan menús/widgets.
 * - [Fix cache-poisoning] NO envía cabeceras de caché pública: la caché de
 *   LiteSpeed es compartida entre bots y humanos (el vary solo separa por
 *   subdominio, no por User-Agent). Si un bot pisaba primero una URL 404 y
 *   LiteSpeed cacheaba esa respuesta, un HUMANO que visitara la misma URL
 *   en un subdominio restringido recibiría el 404 cacheado en vez de la
 *   redirección 302 a su categoría digital (handle_404_category_redirect).
 *   Respuestas que dependen del User-Agent jamás deben marcarse cacheables.
 * - Envía X-Robots-Tag: noindex,nofollow para desincentivar re-rastreo.
 * - Cabecera X-MU-Bot-404: fast-exit para trazabilidad vía curl/DevTools.
 *
 * Los humanos NUNCA pasan por aquí: conservan el flujo completo
 * (no-cache + auto-recuperación de 404s transitorios vía digital-restriction).
 */
if ( ! function_exists( 'mu_litespeed_bot_404_fast_exit' ) ) {
    function mu_litespeed_bot_404_fast_exit() {
        if ( ! is_404() || ! mu_is_bot() ) {
            return;
        }

        status_header( 404 );
        nocache_headers(); // Evita caché intermedia del navegador y de LiteSpeed.
        header( 'X-Robots-Tag: noindex, nofollow' );

        // [Verificación] Cabecera de trazabilidad: con curl o DevTools
        // confirmás que esta respuesta vino del fast-exit y no del
        // 404 normal del tema. Costo cero.
        header( 'X-MU-Bot-404: fast-exit' );

        // [Verificación] Contadores en archivo plano (sobreviven al exit;
        // el object cache de LiteSpeed persiste en shutdown, que salteamos).
        list( $daily, $total, $should_log ) = mu_bot404_record_hit();

        // [Verificación] Log rate-limitado GLOBALMENTE: máximo 1 línea cada
        // 5 minutos aunque haya decenas de workers PHP (timestamp compartido
        // vía el mismo archivo de stats). UA truncado a 200 chars para poder
        // identificar el crawler completo (los bots de IA spoofean Chrome y
        // el identificador real vive al final del UA).
        if ( $should_log ) {
            error_log( sprintf(
                '[MU-BOT404] %s host=%s ua="%s" daily=%d total=%d',
                current_time( 'mysql' ),
                sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) ),
                substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 0, 200 ),
                $daily,
                $total
            ) );
        }

        echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
           . '<meta name="robots" content="noindex, nofollow">'
           . '<title>404 - Página no encontrada</title></head>'
           . '<body><h1>404</h1><p>Página no encontrada.</p></body></html>';
        exit;
    }
    add_action( 'template_redirect', 'mu_litespeed_bot_404_fast_exit', 1 );
}

/**
 * Fast-path para bots en URLs con filtro de tags (?product_tag=...).
 *
 * [Optimización anti-bots] Los bots permutan los slugs de nuestros chips
 * generando URLs infinitas con ?product_tag=a+b+c. LiteSpeed no cachea
 * query strings por defecto, así que cada permutación ejecutaba WordPress
 * completo (WP_Query con joins N-vías + render del shop + chips) — la causa
 * de los timeouts de 20s+ y la saturación de workers PHP.
 *
 * Si es bot + hay ?product_tag= → 301 redirect a la misma URL SIN el query
 * string (~1ms, cero DB, cero render). El 301 además enseña al bot la URL
 * canónica, reduciendo el rastreo de permutaciones con el tiempo.
 *
 * Corre en template_redirect prioridad 1 (junto al fast-exit de 404).
 * Los humanos NUNCA pasan por aquí: conservan el filtro funcional.
 * Sin cabeceras de caché (misma razón que el fast-exit: la respuesta
 * depende del User-Agent y la caché de LiteSpeed es compartida).
 */
if ( ! function_exists( 'mu_litespeed_bot_tag_filter_redirect' ) ) {
    function mu_litespeed_bot_tag_filter_redirect() {
        if ( is_admin() || ! mu_is_bot() ) {
            return;
        }

        // Solo aplica a URLs de catálogo con el filtro de tags activo.
        if ( empty( $_GET['product_tag'] ) ) {
            return;
        }

        // URL actual sin el query string completo (los chips solo usan
        // product_tag; descartar todo el QS es seguro y canónico).
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        $path        = (string) strtok( $request_uri, '?' );
        if ( '' === $path || '/' === $path ) {
            return;
        }

        $scheme = is_ssl() ? 'https' : 'http';
        $host   = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) );
        $target = $scheme . '://' . $host . $path;

        header( 'X-Robots-Tag: noindex, nofollow' );
        header( 'X-MU-Bot-TagFilter: redirect-canonical' );

        wp_redirect( $target, 301 );
        exit;
    }
    add_action( 'template_redirect', 'mu_litespeed_bot_tag_filter_redirect', 1 );
}
