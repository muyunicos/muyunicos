<?php
/**
 * Muy Únicos - Modal de Autenticación
 * 
 * Incluye:
 * - HTML del modal de login/registro
 * - Localize script para AJAX
 * - Handlers WC-AJAX (login, register, reset password, check user)
 * 
 * @package GeneratePress_Child
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================
// LOCALIZE SCRIPT
// ============================================

function mu_auth_localize_script() {
    if ( ! is_user_logged_in() ) {
        wp_localize_script( 'mu-modal-auth-js', 'muAuthData', [
            'ajax_url' => WC_AJAX::get_endpoint( '%%endpoint%%' ),
            'nonce'    => wp_create_nonce( 'mu_auth_nonce' ),
            'home_url' => home_url( '/' )
        ] );
    }
}
add_action( 'wp_enqueue_scripts', 'mu_auth_localize_script', 25 );

// ============================================
// HTML DEL MODAL
// ============================================

function mu_auth_modal_html() {
    if ( is_user_logged_in() ) return;
    
    $current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    ?>
    <div id="mu-auth-modal" class="mu-auth-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="mu-modal-title">
        <div class="mu-modal-overlay" aria-hidden="true"></div>
        <div class="mu-modal-container">
            <div class="mu-modal-content">
                <button class="mu-modal-close" aria-label="Cerrar" type="button">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
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
                                <svg class="mu-icon-svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg> Cambiar usuario
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
                                <svg class="mu-icon-svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg> Cambiar email
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
                                <svg class="mu-icon-svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg> Volver
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
                        <a href="<?php echo esc_url( site_url( '/wp-login.php?loginSocial=google&redirect=' . urlencode( $current_url ) ) ); ?>" class="mu-btn-social mu-btn-google" data-plugin="nsl" data-action="connect" data-provider="google" data-popupwidth="600" data-popupheight="600">
                            <svg width="18" height="18" viewBox="0 0 18 18"><path d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.615z" fill="#4285F4"/><path d="M9.003 18c2.43 0 4.467-.806 5.956-2.18L12.05 13.56c-.806.54-1.836.86-3.047.86-2.344 0-4.328-1.584-5.036-3.711H.96v2.332C2.44 15.983 5.485 18 9.003 18z" fill="#34A853"/><path d="M3.964 10.712c-.18-.54-.282-1.117-.282-1.71 0-.593.102-1.17.282-1.71V4.96H.957C.347 6.175 0 7.55 0 9.002c0 1.452.348 2.827.957 4.042l3.007-2.332z" fill="#FBBC05"/><path d="M9.003 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.464.891 11.428 0 9.003 0 5.485 0 2.44 2.017.96 4.958L3.967 7.29c.708-2.127 2.692-3.71 5.036-3.71z" fill="#EA4335"/></svg> Google
                        </a>
                        <a href="<?php echo esc_url( site_url( '/wp-login.php?loginSocial=facebook&redirect=' . urlencode( $current_url ) ) ); ?>" class="mu-btn-social mu-btn-facebook" data-plugin="nsl" data-action="connect" data-provider="facebook" data-popupwidth="600" data-popupheight="679">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="#fff"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg> Facebook
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
