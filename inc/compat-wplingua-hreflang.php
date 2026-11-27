<?php
/**
 * Muy Únicos — Compatibilidad wpLingua: fuente única de hreflang (el tema)
 *
 * PROBLEMA (medido en producción, 2026): el <head> emitía DOS sets de hreflang:
 *   1. wpLingua: es/pt/en apuntando a subdirectorios del MISMO host
 *      (muyunicos.com/es/...), con URLs malformadas (https:/pt//pt/br...).
 *   2. El tema (inc/seo-hreflang.php): subdominios por país.
 * Google exige UNA sola implementación, con self-reference, recíproco y códigos
 * ISO 639-1 + ISO 3166-1 alpha-2. Dos sets contradictorios hacen que Google
 * desconfíe de todos (el hreflang debe ser único por URL).
 *
 * SOLUCIÓN: se retira el hreflang de wpLingua y queda el del tema como fuente
 * única. wpLingua registra su callback en 'wp_head' con prioridad 1:
 *
 *   add_action( 'wp_head', 'wplng_link_alternate_hreflang', 1 );   (wplingua.php)
 *
 * El callback vive en inc/hreflang.php y, además del hreflang, imprime el
 * <meta name="google" content="notranslate"> y el comentario del generador: al
 * retirarlo se retira todo el bloque (el SEO lo cubre el tema). Verificado contra
 * el fuente de wpLingua (julien-jacob/wplingua: inc/hreflang.php + wplingua.php).
 *
 * Se engancha en 'wp_head' prioridad 0 —antes que el callback de wpLingua en la 1—
 * para que la retirada ocurra justo antes de que se emita. remove_action() es un
 * no-op inofensivo si wpLingua no está activo o cambió el nombre del callback, así
 * que el tema funciona igual con o sin el plugin.
 *
 * @package GeneratePress_Child
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'mu_remove_wplingua_hreflang' ) ) {
    /**
     * Retira del hook 'wp_head' el callback que emite el hreflang de wpLingua.
     *
     * @return void
     */
    function mu_remove_wplingua_hreflang() {
        remove_action( 'wp_head', 'wplng_link_alternate_hreflang', 1 );
    }
    add_action( 'wp_head', 'mu_remove_wplingua_hreflang', 0 );
}