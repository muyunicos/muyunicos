<?php
/**
 * Muy Únicos — Pantalla "Traducciones IA" (WC → Marketing)
 *
 * Configuración y estado del servicio de traducción (inc/ai-translate.php). Esta
 * pantalla NO traduce contenido: la traducción se pide desde el botón del editor de
 * cada ámbito (reseñas y hero banners) y la guarda el mantenedor.
 *
 * Feature 006. Carga después de inc/ai-translate.php (usa su API).
 *
 * @package GeneratePress_Child
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'MU_AI_SCREEN' ) ) define( 'MU_AI_SCREEN', 'mu-ai-translate' );

if ( is_admin() ) {

    /**
     * Submenú bajo WC → Marketing, igual que Hero Banners.
     */
    add_action( 'admin_menu', function () {
        add_submenu_page(
            'woocommerce-marketing',
            __( 'Traducciones IA', 'mu' ),
            __( 'Traducciones IA', 'mu' ),
            'manage_woocommerce',
            MU_AI_SCREEN,
            'mu_ai_render_admin_page'
        );
    }, 61 );

    /**
     * Assets solo en esta pantalla, con versión por filemtime.
     */
    add_action( 'admin_enqueue_scripts', function ( $hook ) {
        if ( false === strpos( (string) $hook, MU_AI_SCREEN ) ) {
            return;
        }

        $uri = get_stylesheet_directory_uri();
        $dir = get_stylesheet_directory();
        $ver = wp_get_theme()->get( 'Version' );

        $css_file = $dir . '/css/admin-ai-translate.css';
        $js_file  = $dir . '/js/admin-ai-translate.js';
        $css_ver  = file_exists( $css_file ) ? (string) filemtime( $css_file ) : $ver;
        $js_ver   = file_exists( $js_file ) ? (string) filemtime( $js_file ) : $ver;

        wp_enqueue_style( 'mu-admin-ai-translate', $uri . '/css/admin-ai-translate.css', [], $css_ver );
        wp_enqueue_script( 'mu-admin-ai-translate-js', $uri . '/js/admin-ai-translate.js', [ 'jquery' ], $js_ver, true );

        if ( wp_script_is( 'mu-admin-ai-translate-js', 'enqueued' ) ) {
            wp_localize_script( 'mu-admin-ai-translate-js', 'muAiAdmin', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'mu_ai_nonce' ),
                'testing' => __( 'Probando…', 'mu' ),
                'ok'      => __( 'Conexión correcta con el modelo', 'mu' ),
                'error'   => __( 'No se pudo conectar. Revisá la clave y la salida del servidor.', 'mu' ),
            ] );
        }
    } );

    add_action( 'load-marketing_page_' . MU_AI_SCREEN, 'mu_ai_handle_save' );
}

if ( ! function_exists( 'mu_ai_handle_save' ) ) {
    /**
     * Guarda los ajustes (o reinicia el contador) antes de renderizar la pantalla.
     *
     * @return void
     */
    function mu_ai_handle_save() {
        if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
            return;
        }

        if ( ! mu_ai_can_manage() ) {
            return;
        }

        check_admin_referer( 'mu_ai_save', 'mu_ai_nonce' );

        if ( isset( $_POST['mu_ai_reset_usage'] ) ) {
            mu_ai_usage_reset();
        } else {
            // La clave se toca solo si el mantenedor escribió una nueva o pidió borrarla
            // (el campo se envía vacío en cada guardado para no reescribir el valor).
            if ( isset( $_POST['mu_ai_delete_key'] ) ) {
                mu_ai_save_key( '' );
            } elseif ( ! empty( $_POST['api_key'] ) ) {
                mu_ai_save_key( (string) wp_unslash( $_POST['api_key'] ) );
            }

            mu_ai_save_settings( [
                'enabled'  => isset( $_POST['enabled'] ),
                'model'    => isset( $_POST['model'] ) ? (string) wp_unslash( $_POST['model'] ) : '',
                'limits'   => isset( $_POST['limits'] ) ? (array) wp_unslash( $_POST['limits'] ) : [],
                'contexts' => isset( $_POST['contexts'] ) ? (array) wp_unslash( $_POST['contexts'] ) : [],
            ] );
        }

        wp_safe_redirect( add_query_arg( [ 'page' => MU_AI_SCREEN, 'updated' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }
}

if ( ! function_exists( 'mu_ai_render_admin_page' ) ) {
    /**
     * Pantalla: estado, configuración, contextos, consumo y prueba de conexión.
     *
     * @return void
     */
    function mu_ai_render_admin_page() {
        if ( ! mu_ai_can_manage() ) {
            wp_die( esc_html__( 'No tenés permisos para acceder a esta página.', 'mu' ) );
        }

        $settings   = mu_ai_settings();
        $usage      = mu_ai_usage();
        $key_loaded = '' !== mu_ai_api_key();
        $key_source = mu_ai_key_source();
        $updated    = isset( $_GET['updated'] ) && '1' === $_GET['updated'];
        $last_error = is_array( $usage['last_error'] ) ? $usage['last_error'] : [];
        ?>
        <div class="wrap mu-ai-wrap">
            <h1><?php esc_html_e( 'Traducciones IA', 'mu' ); ?></h1>
            <p class="mu-ai-hint">
                <?php esc_html_e( 'Traduce en contexto de tienda el contenido que el tema guarda en base de datos (reseñas y hero banners). El servicio solo propone: el texto se guarda desde el editor de cada sección, después de revisarlo.', 'mu' ); ?>
            </p>

            <?php if ( $updated ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ajustes guardados.', 'mu' ); ?></p></div>
            <?php endif; ?>

            <div class="mu-ai-card">
                <h2><?php esc_html_e( 'Estado', 'mu' ); ?></h2>
                <p>
                    <span class="mu-ai-status <?php echo $key_loaded ? 'is-on' : 'is-off'; ?>">
                        <?php echo $key_loaded ? esc_html__( 'Clave presente', 'mu' ) : esc_html__( 'Sin clave', 'mu' ); ?>
                    </span>
                    <span class="mu-ai-status <?php echo ! empty( $settings['enabled'] ) ? 'is-on' : 'is-off'; ?>">
                        <?php echo ! empty( $settings['enabled'] ) ? esc_html__( 'Servicio activo', 'mu' ) : esc_html__( 'Servicio desactivado', 'mu' ); ?>
                    </span>
                </p>
                <?php if ( ! $key_loaded ) : ?>
                    <p class="mu-ai-hint">
                        <?php esc_html_e( 'Todavía no hay clave. Pegala en el campo «Clave del servicio» y guardá. Hasta entonces el sitio funciona igual y las secciones muestran el español.', 'mu' ); ?>
                    </p>
                <?php else : ?>
                    <p class="mu-ai-hint">
                        <?php
                        echo esc_html( sprintf(
                            'constant' === $key_source
                                ? __( 'Clave %s — definida en la configuración del servidor, tiene prioridad sobre la guardada en la base.', 'mu' )
                                : __( 'Clave %s — guardada en la base de datos (sin carga automática).', 'mu' ),
                            mu_ai_key_masked()
                        ) );
                        ?>
                    </p>
                <?php endif; ?>
                <p>
                    <button type="button" class="button button-secondary" id="mu-ai-test"><?php esc_html_e( 'Probar conexión', 'mu' ); ?></button>
                    <span class="mu-ai-result" id="mu-ai-test-result"></span>
                </p>
            </div>

            <form method="post" action="">
                <?php wp_nonce_field( 'mu_ai_save', 'mu_ai_nonce' ); ?>

                <div class="mu-ai-card">
                    <h2><?php esc_html_e( 'Ajustes', 'mu' ); ?></h2>
                    <div class="mu-ai-grid">
                        <p>
                            <label for="mu-ai-api-key"><strong><?php esc_html_e( 'Clave del servicio', 'mu' ); ?></strong></label><br>
                            <input type="password" class="regular-text" id="mu-ai-api-key" name="api_key" value="" autocomplete="off" spellcheck="false" placeholder="<?php echo $key_loaded ? esc_attr__( 'Pegá una nueva para reemplazarla', 'mu' ) : 'AQ.…'; ?>">
                            <span class="mu-ai-hint"><?php esc_html_e( 'Se guarda en la base de datos sin carga automática y nunca se muestra de nuevo. Dejá el campo vacío para no cambiarla.', 'mu' ); ?></span>
                            <?php if ( 'option' === $key_source ) : ?>
                                <label><input type="checkbox" name="mu_ai_delete_key" value="1"> <?php esc_html_e( 'Borrar la clave guardada', 'mu' ); ?></label>
                            <?php endif; ?>
                        </p>
                        <p>
                            <label><strong><?php esc_html_e( 'Servicio activo', 'mu' ); ?></strong></label><br>
                            <label><input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>> <?php esc_html_e( 'Permitir traducciones', 'mu' ); ?></label>
                        </p>
                        <p>
                            <label for="mu-ai-model"><strong><?php esc_html_e( 'Modelo', 'mu' ); ?></strong></label><br>
                            <input type="text" class="regular-text" id="mu-ai-model" name="model" value="<?php echo esc_attr( (string) $settings['model'] ); ?>">
                            <span class="mu-ai-hint"><?php esc_html_e( 'Usá «Probar conexión» para ver los modelos disponibles con esta clave.', 'mu' ); ?></span>
                        </p>
                        <?php
                        // El tope de textos por acción vive en cada ámbito (abajo): un
                        // banner trae 6 campos y una reseña 1. Acá quedan los límites del
                        // servicio.
                        $limites = [
                            'per_minute' => __( 'Solicitudes por minuto', 'mu' ),
                            'per_day'    => __( 'Solicitudes por día', 'mu' ),
                        ];
                        foreach ( $limites as $limit_key => $limit_label ) :
                            ?>
                        <p>
                            <label for="mu-ai-limit-<?php echo esc_attr( $limit_key ); ?>"><strong><?php echo esc_html( $limit_label ); ?></strong></label><br>
                            <input type="number" min="1" max="1000" id="mu-ai-limit-<?php echo esc_attr( $limit_key ); ?>" name="limits[<?php echo esc_attr( $limit_key ); ?>]" value="<?php echo esc_attr( (int) $settings['limits'][ $limit_key ] ); ?>">
                        </p>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php
                foreach ( mu_ai_ambitos() as $ambito => $def ) :
                    $ctx = mu_ai_context_profile( $ambito );
                    ?>
                <div class="mu-ai-card mu-ai-context">
                    <h2><?php echo esc_html( sprintf( __( 'Contexto: %s', 'mu' ), (string) $def['label'] ) ); ?></h2>
                    <?php
                    $campos = [
                        'role'     => __( 'Rol', 'mu' ),
                        'tone'     => __( 'Tono', 'mu' ),
                        'glossary' => __( 'Glosario', 'mu' ),
                        'exclude'  => __( 'Qué no hacer', 'mu' ),
                    ];
                    foreach ( $campos as $field => $label ) :
                        ?>
                        <h3><?php echo esc_html( $label ); ?></h3>
                        <textarea rows="3" name="contexts[<?php echo esc_attr( $ambito ); ?>][<?php echo esc_attr( $field ); ?>]"><?php echo esc_textarea( (string) ( $ctx[ $field ] ?? '' ) ); ?></textarea>
                    <?php endforeach; ?>

                    <h3><?php esc_html_e( 'Personas (una por línea)', 'mu' ); ?></h3>
                    <textarea rows="5" name="contexts[<?php echo esc_attr( $ambito ); ?>][personas]"><?php echo esc_textarea( (string) ( $ctx['personas'] ?? '' ) ); ?></textarea>
                    <p class="mu-ai-hint">
                        <?php esc_html_e( 'Cada variante de una traducción manual se escribe como una de estas personas: la elige el servicio, no la IA (así el estilo cambia de verdad y se puede repetir). Si lo dejás vacío, la acción manual devuelve una sola traducción estándar: es la ruta automática.', 'mu' ); ?>
                    </p>

                    <p>
                        <label for="mu-ai-variants-<?php echo esc_attr( $ambito ); ?>"><strong><?php esc_html_e( 'Variantes por traducción manual (1 a 5)', 'mu' ); ?></strong></label><br>
                        <input type="number" min="1" max="5" id="mu-ai-variants-<?php echo esc_attr( $ambito ); ?>" name="contexts[<?php echo esc_attr( $ambito ); ?>][variants]" value="<?php echo esc_attr( max( 1, min( 5, (int) ( $ctx['variants'] ?? 1 ) ) ) ); ?>">
                        <span class="mu-ai-hint"><?php esc_html_e( 'Cuántas versiones distintas (una por persona) devuelve el botón «Traducir». Se acota solo a la cantidad de personas cargadas.', 'mu' ); ?></span>
                    </p>

                    <p>
                        <label for="mu-ai-peraction-<?php echo esc_attr( $ambito ); ?>"><strong><?php esc_html_e( 'Textos por acción', 'mu' ); ?></strong></label><br>
                        <input type="number" min="0" max="50" id="mu-ai-peraction-<?php echo esc_attr( $ambito ); ?>" name="contexts[<?php echo esc_attr( $ambito ); ?>][per_action]" value="<?php echo esc_attr( (int) ( $ctx['per_action'] ?? 0 ) ); ?>">
                        <span class="mu-ai-hint">
                            <?php
                            printf(
                                /* translators: 1: cantidad de campos del ámbito, 2: tope efectivo. */
                                esc_html__( 'Cuántos textos puede traducir UNA acción del botón en este ámbito. El ámbito declara %1$d campo(s); con 0 o vacío se usa esa cantidad. Hoy el tope efectivo es %2$d.', 'mu' ),
                                count( (array) ( $def['fields'] ?? [] ) ),
                                mu_ai_ambito_per_action( $ambito )
                            );
                            ?>
                        </span>
                    </p>

                    <p>
                        <label for="mu-ai-temp-<?php echo esc_attr( $ambito ); ?>"><strong><?php esc_html_e( 'Temperatura', 'mu' ); ?></strong></label><br>
                        <input type="number" min="0" max="1.5" step="0.1" id="mu-ai-temp-<?php echo esc_attr( $ambito ); ?>" name="contexts[<?php echo esc_attr( $ambito ); ?>][temperature]" value="<?php echo esc_attr( (float) ( $ctx['temperature'] ?? 0.2 ) ); ?>">
                        <span class="mu-ai-hint"><?php esc_html_e( 'Más alta = más variedad de redacción; más baja = más fiel y repetible. Sugerido: 0.9 en reseñas (con personas) y 0.2 en textos de portada.', 'mu' ); ?></span>
                    </p>

                    <p>
                        <label for="mu-ai-max-<?php echo esc_attr( $ambito ); ?>"><strong><?php esc_html_e( 'Largo máximo (caracteres)', 'mu' ); ?></strong></label><br>
                        <input type="number" min="60" max="5000" id="mu-ai-max-<?php echo esc_attr( $ambito ); ?>" name="contexts[<?php echo esc_attr( $ambito ); ?>][max_chars]" value="<?php echo esc_attr( (int) ( $ctx['max_chars'] ?? 700 ) ); ?>">
                        <span class="mu-ai-hint"><?php esc_html_e( 'Cualquier cambio de estas instrucciones (rol, tono, glosario, personas, variantes o temperatura) invalida la caché de traducciones de ese ámbito: la próxima traducción se pide de nuevo.', 'mu' ); ?></span>
                    </p>
                    <?php
                    // Probador del ámbito: manda un texto de ejemplo con la configuración
                    // GUARDADA y muestra las variantes (una por persona). Cada prueba es una
                    // solicitud real y consume cuota del día.
                    ?>
                    <h3><?php esc_html_e( 'Probar este ámbito', 'mu' ); ?></h3>
                    <textarea rows="3" class="mu-ai-probar-texto" data-ambito="<?php echo esc_attr( $ambito ); ?>" placeholder="<?php esc_attr_e( 'Pegá un texto de ejemplo para traducir…', 'mu' ); ?>"></textarea>
                    <p>
                        <select class="mu-ai-probar-lang" data-ambito="<?php echo esc_attr( $ambito ); ?>">
                            <option value="pt">Portugués</option>
                            <option value="en">Inglés</option>
                        </select>
                        <button type="button" class="button mu-ai-probar" data-ambito="<?php echo esc_attr( $ambito ); ?>"><?php esc_html_e( 'Probar traducción', 'mu' ); ?></button>
                        <span class="mu-ai-result mu-ai-probar-resultado" data-ambito="<?php echo esc_attr( $ambito ); ?>"></span>
                    </p>
                    <div class="mu-ai-probar-variantes" data-ambito="<?php echo esc_attr( $ambito ); ?>"></div>
                    <p class="mu-ai-hint"><?php esc_html_e( 'Se prueba con la configuración guardada (guardá antes de probar).', 'mu' ); ?></p>
                </div>
                <?php endforeach; ?>

                <?php submit_button( __( 'Guardar ajustes', 'mu' ) ); ?>
            </form>

            <div class="mu-ai-card">
                <h2><?php esc_html_e( 'Consumo', 'mu' ); ?></h2>
                <div class="mu-ai-usage">
                    <span><?php echo esc_html( sprintf( __( 'Hoy (%1$s): %2$d solicitudes, %3$d caracteres enviados, %4$d variantes', 'mu' ), (string) $usage['day'], (int) $usage['day_requests'], (int) $usage['day_chars'], (int) ( $usage['day_variants'] ?? 0 ) ) ); ?></span>
                    <span><?php echo esc_html( sprintf( __( 'Mes (%1$s): %2$d solicitudes, %3$d variantes', 'mu' ), (string) $usage['month'], (int) $usage['month_requests'], (int) ( $usage['month_variants'] ?? 0 ) ) ); ?></span>
                    <?php if ( ! empty( $usage['last_model'] ) ) : ?>
                        <span><?php echo esc_html( sprintf( __( 'Modelo en uso: %s', 'mu' ), (string) $usage['last_model'] ) ); ?></span>
                    <?php endif; ?>
                </div>
                <?php if ( ! empty( $last_error['msg'] ) ) : ?>
                    <p class="mu-ai-error">
                        <?php echo esc_html( sprintf( __( 'Último error (%1$s): %2$s', 'mu' ), (string) ( $last_error['code'] ?? '' ), (string) $last_error['msg'] ) ); ?>
                    </p>
                <?php endif; ?>
                <form method="post" action="">
                    <?php wp_nonce_field( 'mu_ai_save', 'mu_ai_nonce' ); ?>
                    <input type="hidden" name="mu_ai_reset_usage" value="1">
                    <?php submit_button( __( 'Reiniciar contador', 'mu' ), 'secondary', 'mu-ai-reset', false ); ?>
                </form>
                <p class="mu-ai-hint">
                    <?php esc_html_e( 'El contador es propio del tema y sirve para no agotar la cuota del plan. Las traducciones ya hechas no se vuelven a pedir (caché).', 'mu' ); ?>
                </p>
            </div>
        </div>
        <?php
    }
}

