<?php
/**
 * Muy Únicos - Modal de Autenticación
 * 
 * Incluye:
 * - HTML del modal de login/registro
 * - Handlers WC-AJAX (login, register, reset password, check user)
 * 
 * Nota: el localize de muAuthData vive en functions.php (mu_enqueue_assets),
 * inmediatamente después del enqueue de mu-modal-auth-js, para garantizar
 * que la variable JS exista siempre.
 * 
 * @package GeneratePress_Child
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================
// HELPERS DE URL DE LOGIN SOCIAL
// ============================================

/**
 * Construye la URL de autenticacion de un proveedor social.
 *
 * El proveedor (Nextend Social Login) recibe como destino de redireccion la
 * propia URL de login con el parametro del proveedor dentro, y valida que
 * coincida con lo registrado en su panel. Por eso el formato de la URL es
 * fijo: ruta de login seguida de ?loginSocial=PROVEEDOR&redirect=DESTINO.
 * Cualquier otra forma (por ejemplo, agregar los parametros con una funcion
 * que reordene) cambia la URL de redireccion y el proveedor la rechaza.
 *
 * La ruta de login se resuelve por API de WordPress y no escribiendo el
 * nombre del archivo de login a mano. Un plugin que oculta la ruta de login
 * la modifica por filtro: si el tema la escribiera, dependeria de que ese
 * plugin este activo y hookeando, y el fallo seria silencioso.
 *
 * @param string $provider    Identificador del proveedor (google, facebook).
 * @param string $redirect_to URL a la que volver tras autenticarse.
 * @return string URL de login social, o cadena vacia si el proveedor no es valido.
 */
if ( ! function_exists( 'mu_social_login_url' ) ) {
    function mu_social_login_url( $provider, $redirect_to = '' ) {
        $allowed = array( 'google', 'facebook' );
        $provider = sanitize_key( $provider );

        if ( ! in_array( $provider, $allowed, true ) ) {
            return '';
        }

        // wp_login_url() devuelve la URL completa; nos quedamos con la ruta
        // para no fijar el host y conservar el subdominio de la peticion.
        $login_path = wp_login_url();
        $path_only  = wp_parse_url( $login_path, PHP_URL_PATH );
        $path_only  = $path_only ? $path_only : '/wp-login.php';

        $url = $path_only . '?loginSocial=' . rawurlencode( $provider );

        if ( $redirect_to ) {
            $url .= '&redirect=' . rawurlencode( $redirect_to );
        }

        return $url;
    }
}

// ============================================
// HTML DEL MODAL
// ============================================

function mu_auth_modal_html() {
    if ( is_user_logged_in() ) return;
    
    $current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    ?>
    <div id="mu-auth-modal" class="mu-modal-overlay" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="mu-modal-title">
    <div class="mu-modal-container">
        <div class="mu-modal-content">
                <button class="mu-modal-close" aria-label="Cerrar" type="button">
                    <?php echo mu_get_icon( 'close' ); ?>
                </button>

                <div class="mu-modal-header">
                    <h2 id="mu-modal-title">¡Te damos la bienvenida!</h2>
                    <p class="mu-modal-subtitle" id="mu-modal-subtitle">Ingresa a tu cuenta o creá una nueva</p>
                </div>

                <form id="mu-auth-form" class="mu-modal-body">
                    <!-- STEP 1: Identificación -->
                    <div id="mu-step-1" class="mu-form-step">
                        <div class="mu-form-group">
                            <label for="mu-user-input">Tu Email o usuario</label>
                            <input type="text" id="mu-user-input" name="user_login" class="mu-input" placeholder="tu@email.com" required autocomplete="username">
                        </div>
                        <button type="button" id="mu-continue-btn" class="mu-btn mu-btn-primary mu-btn-block">Continuar</button>
                    </div>

                    <!-- STEP 2: Login -->
                    <div id="mu-step-2-login" class="mu-form-step" style="display:none;">
                        <div class="mu-back-link">
                            <button type="button" id="mu-back-to-step1" class="mu-link-back">
                                <?php echo mu_get_icon( 'chevron-down' ); ?> Cambiar usuario
                            </button>
                        </div>
                        <p class="mu-welcome-text">Hola de nuevo, <strong id="mu-user-display"></strong></p>
                        <div class="mu-form-group">
                            <label for="mu-password-login">Tu contraseña</label>
                            <input type="password" id="mu-password-login" name="password" class="mu-input" placeholder="Tu contraseña" autocomplete="current-password">
                        </div>
                        <button type="submit" id="mu-login-btn" class="mu-btn mu-btn-primary mu-btn-block">Entrar</button>
                        <a href="#" id="mu-forgot-link" class="mu-forgot-link">¿Has olvidado tu contraseña?</a>
                    </div>

                    <!-- STEP 2: Registro -->
                    <div id="mu-step-2-register" class="mu-form-step" style="display:none;">
                        <div class="mu-back-link">
                            <button type="button" id="mu-back-to-step1-reg" class="mu-link-back">
                                <?php echo mu_get_icon( 'chevron-down' ); ?> Cambiar email
                            </button>
                        </div>
                        <p class="mu-welcome-new">🎉 Primera vez por aquí</p>
                        <div class="mu-form-group" id="mu-email-group" style="display:none;">
                            <label for="mu-email-register">Email</label>
                            <input type="email" id="mu-email-register" name="email" class="mu-input" placeholder="tu@email.com" autocomplete="email">
                        </div>
                        <div class="mu-form-group">
                            <label for="mu-password-register">Creá una contraseña</label>
                            <input type="password" id="mu-password-register" name="password" class="mu-input" placeholder="Mínimo 6 caracteres" autocomplete="new-password">
                        </div>
                        <button type="submit" id="mu-register-btn" class="mu-btn mu-btn-primary mu-btn-block">Crear cuenta</button>
                        <p class="mu-terms-text">Aceptás nuestros <a href="/terminos/" target="_blank">términos y condiciones</a></p>
                    </div>

                    <!-- STEP: Recupero -->
                    <div id="mu-step-forgot" class="mu-form-step" style="display:none;">
                         <div class="mu-back-link">
                            <button type="button" id="mu-back-to-login" class="mu-link-back">
                                <?php echo mu_get_icon( 'chevron-down' ); ?> Volver
                            </button>
                        </div>
                        <p class="mu-welcome-text" style="background:#f3f4f6;">Te enviaremos un enlace para crear una nueva clave.</p>
                        <div class="mu-form-group">
                            <label for="mu-forgot-email">Confirma tu email</label>
                            <input type="text" id="mu-forgot-email" class="mu-input" placeholder="tu@email.com">
                        </div>
                        <button type="button" id="mu-send-reset-btn" class="mu-btn mu-btn-primary mu-btn-block">Enviar enlace</button>
                    </div>
                    <div id="mu-auth-message" class="mu-auth-message" style="display:none;"></div>
                </form>

                <div id="mu-social-section">
                    <div class="mu-divider"><span>o ingresa directamente con</span></div>
                    <div class="mu-social-buttons">
                        <a href="<?php echo esc_url( mu_social_login_url( 'google', $current_url ) ); ?>" class="mu-btn-social mu-btn-google" data-plugin="nsl" data-action="connect" data-provider="google" data-popupwidth="600" data-popupheight="600">
                            <?php echo mu_get_icon( 'google' ); ?> Google
                        </a>
                        <a href="<?php echo esc_url( mu_social_login_url( 'facebook', $current_url ) ); ?>" class="mu-btn-social mu-btn-facebook" data-plugin="nsl" data-action="connect" data-provider="facebook" data-popupwidth="600" data-popupheight="679">
                            <?php echo mu_get_icon( 'facebook' ); ?> Facebook
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    if ( class_exists( 'NextendSocialLogin', false ) ) do_action( 'nsl_render_login_form' );
}
add_action( 'wp_footer', 'mu_auth_modal_html', 5 );

// ============================================
// WC-AJAX HANDLERS
// ============================================

function mu_check_user_exists() {
    check_ajax_referer( 'mu_auth_nonce', 'nonce' );
    $input = sanitize_text_field( $_POST['user_input'] );
    $user = is_email( $input ) ? get_user_by( 'email', $input ) : get_user_by( 'login', $input );
    
    if ( $user ) {
        wp_send_json_success( [ 'exists' => true, 'display_name' => $user->display_name ] );
    } else {
        wp_send_json_success( [ 'exists' => false ] );
    }
}
add_action( 'wc_ajax_mu_check_user', 'mu_check_user_exists' );

function mu_handle_login() {
    check_ajax_referer( 'mu_auth_nonce', 'nonce' );
    
    $creds = [
        'user_login'    => sanitize_text_field( $_POST['user_login'] ),
        'user_password' => $_POST['password'],
        'remember'      => true
    ];
    
    $user = wp_signon( $creds, is_ssl() );
    
    if ( is_wp_error( $user ) ) {
        wp_send_json_error( [ 'message' => 'Contraseña incorrecta' ] );
    }
    
    wp_send_json_success();
}
add_action( 'wc_ajax_mu_login_user', 'mu_handle_login' );

function mu_handle_register() {
    check_ajax_referer( 'mu_auth_nonce', 'nonce' );
    
    $email    = sanitize_email( $_POST['email'] );
    $username = sanitize_user( $_POST['username'] );
    $password = $_POST['password'];
    
    if ( email_exists( $email ) ) {
        wp_send_json_error( [ 'message' => 'Email ya registrado' ] );
    }
    
    $user_id = wc_create_new_customer( $email, $username, $password );
    
    if ( is_wp_error( $user_id ) ) {
        wp_send_json_error( [ 'message' => $user_id->get_error_message() ] );
    }
    
    wp_set_current_user( $user_id );
    wp_set_auth_cookie( $user_id, true, is_ssl() );
    wp_send_json_success();
}
add_action( 'wc_ajax_mu_register_user', 'mu_handle_register' );

function mu_handle_reset_password() {
    check_ajax_referer( 'mu_auth_nonce', 'nonce' );
    
    $login = sanitize_text_field( $_POST['user_login'] );
    $user = is_email( $login ) ? get_user_by( 'email', $login ) : get_user_by( 'login', $login );
    
    if ( ! $user ) {
        wp_send_json_error( [ 'message' => 'No encontramos esa cuenta.' ] );
    }
    
    $key = get_password_reset_key( $user );
    
    if ( is_wp_error( $key ) ) {
        wp_send_json_error( [ 'message' => 'Error del sistema.' ] );
    }
    
    try {
        $mailer = WC()->mailer();
        $email = $mailer->get_emails()['WC_Email_Customer_Reset_Password'];
        
        if ( $email ) {
            $email->trigger( $user->user_login, $key );
            wp_send_json_success( [ 'message' => '¡Enviado! Ten en cuenta que puede demorar o marcarse como spam.' ] );
        }
    } catch ( Exception $e ) {
        wp_send_json_error( [ 'message' => 'Error al enviar correo.' ] );
    }
}
add_action( 'wc_ajax_mu_reset_password', 'mu_handle_reset_password' );
