<?php
/**
 * Muy Únicos — Google One Tap propio (compatible con Nextend Social Login).
 *
 * Reimplementa One Tap por fuera del plugin (función Pro en Nextend) sin
 * modificarlo: reutiliza su client_id de Google y su tabla `wp_social_users`,
 * de modo que la cuenta creada/vinculada por One Tap es la MISMA que usaría el
 * botón de Nextend (clave = `sub` de Google). El callback es JS (GIS) y el
 * credencial (JWT) se verifica del lado servidor contra las claves de Google.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const MU_ONETAP_AJAX = 'nmu_onetap_login';

/**
 * client_id de Google: el del proveedor Google de Nextend si está disponible.
 */
function mu_onetap_client_id() {
    $client_id = '';

    if ( class_exists( 'NextendSocialLogin', false ) && isset( NextendSocialLogin::$providers['google'] ) ) {
        $provider  = NextendSocialLogin::$providers['google'];
        $client_id = (string) $provider->settings->get( 'client_id' );
    }

    return $client_id;
}

/**
 * Proveedor Google de Nextend (para vincular/reconocer usuarios).
 */
function mu_onetap_provider() {
    if ( class_exists( 'NextendSocialLogin', false ) && isset( NextendSocialLogin::$providers['google'] ) ) {
        return NextendSocialLogin::$providers['google'];
    }

    return null;
}

/**
 * ¿One Tap activo? Sí para usuarios no logueados en el frontend, con client_id.
 */
function mu_onetap_activo() {
    if ( is_admin() || is_user_logged_in() ) {
        return false;
    }

    if ( '' === mu_onetap_client_id() ) {
        return false;
    }

    return (bool) apply_filters( 'mu_onetap_activo', true );
}

/**
 * Encola el script de Google Identity Services + el inicializador propio.
 */
function mu_onetap_assets() {
    if ( ! mu_onetap_activo() ) {
        return;
    }

    $ver = wp_get_theme()->get( 'Version' );
    $uri = get_stylesheet_directory_uri();

    wp_enqueue_script( 'mu-google-onetap', 'https://accounts.google.com/gsi/client', array(), null, true );
    wp_enqueue_script( 'mu-onetap', "$uri/js/one-tap.js", array( 'mu-google-onetap' ), $ver, true );

    wp_localize_script( 'mu-onetap', 'muOneTap', array(
        'clientId'  => mu_onetap_client_id(),
        'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
        'nonce'     => wp_create_nonce( 'mu_onetap' ),
        'redirect'  => mu_onetap_redirect_actual(),
    ) );
}
add_action( 'wp_enqueue_scripts', 'mu_onetap_assets', 998 );

/**
 * URL a la que volver tras el login (misma página, validada).
 */
function mu_onetap_redirect_actual() {
    $request = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
    $url     = home_url( $request );

    return wp_validate_redirect( $url, home_url( '/' ) );
}

/**
 * Callback AJAX: recibe el credencial (JWT), lo verifica yloguea/crea el usuario.
 */
function mu_onetap_login() {
    check_ajax_referer( 'mu_onetap', 'nonce' );

    if ( empty( $_POST['credential'] ) || ! is_string( $_POST['credential'] ) ) {
        wp_send_json_error( array( 'codigo' => 'sin_credencial' ), 400 );
    }

    $client_id = mu_onetap_client_id();
    if ( '' === $client_id ) {
        wp_send_json_error( array( 'codigo' => 'sin_cliente' ), 503 );
    }

    $datos = mu_onetap_verificar_jwt( $_POST['credential'], $client_id );
    if ( is_wp_error( $datos ) ) {
        wp_send_json_error( array( 'codigo' => $datos->get_error_code() ), 403 );
    }

    $resultado = mu_onetap_login_o_registrar( $datos );
    if ( is_wp_error( $resultado ) ) {
        wp_send_json_error( array( 'codigo' => $resultado->get_error_code() ), 403 );
    }

    $redirect = isset( $_POST['redirect'] ) ? wp_validate_redirect( wp_unslash( $_POST['redirect'] ), home_url( '/' ) ) : home_url( '/' );

    wp_send_json_success( array( 'redirect' => $redirect ) );
}
add_action( 'wp_ajax_' . MU_ONETAP_AJAX, 'mu_onetap_login' );
add_action( 'wp_ajax_nopriv_' . MU_ONETAP_AJAX, 'mu_onetap_login' );

/**
 * Reconoce al usuario por `sub` (tabla de Nextend), lo vincula si hace falta y
 * lologuea. Si no existe, lo crea (si el registro está permitido).
 */
function mu_onetap_login_o_registrar( $datos ) {
    $provider = mu_onetap_provider();

    $user_id = null;
    if ( $provider ) {
        $user_id = $provider->getUserIDByProviderIdentifier( $datos['sub'] );
    }

    if ( $user_id && get_userdata( $user_id ) ) {
        mu_onetap_hacer_login( (int) $user_id, $provider );
        return true;
    }

    $email = strtolower( trim( $datos['email'] ) );
    $existente = email_exists( $email );

    if ( $existente ) {
        if ( ! $provider ) {
            return new WP_Error( 'sin_nextend', 'Nextend Social Login no está activo.' );
        }

        $provider->linkUserToProviderIdentifier( $existente, $datos['sub'], false );
        mu_onetap_hacer_login( (int) $existente, $provider );
        return true;
    }

    if ( ! apply_filters( 'nsl_is_register_allowed', true, $provider ) ) {
        return new WP_Error( 'registro_deshabilitado', 'El registro está deshabilitado.' );
    }

    $user_id = mu_onetap_crear_usuario( $datos );
    if ( is_wp_error( $user_id ) ) {
        return $user_id;
    }

    if ( $provider ) {
        $provider->linkUserToProviderIdentifier( $user_id, $datos['sub'], true );
    }

    do_action( 'nsl_register_new_user', $user_id, $provider );
    if ( $provider ) {
        do_action( 'nsl_google_register_new_user', $user_id, $provider );
    }

    mu_onetap_hacer_login( $user_id, $provider );

    return true;
}

/**
 * Crea el usuario WP a partir del perfil de Google.
 */
function mu_onetap_crear_usuario( $datos ) {
    $email = strtolower( trim( $datos['email'] ) );

    $base = sanitize_user( (string) $datos['given_name'], true );
    if ( '' === $base ) {
        $base = sanitize_user( explode( '@', $email )[0], true );
    }
    if ( '' === $base ) {
        $base = 'user';
    }

    $login   = $base;
    $sufijo  = 1;
    while ( username_exists( $login ) ) {
        $login = $base . $sufijo;
        $sufijo++;
    }

    $roles = class_exists( 'WooCommerce', false ) ? array( 'customer' ) : array( 'subscriber' );

    $user_id = wp_insert_user( array(
        'user_login'   => $login,
        'user_email'   => $email,
        'user_pass'    => wp_generate_password( 16, false ),
        'role'         => $roles[0],
        'display_name' => sanitize_text_field( (string) $datos['name'] ),
        'first_name'   => sanitize_text_field( (string) $datos['given_name'] ),
        'last_name'    => sanitize_text_field( (string) $datos['family_name'] ),
    ) );

    if ( is_wp_error( $user_id ) ) {
        return $user_id;
    }

    if ( class_exists( 'WooCommerce', false ) ) {
        update_user_meta( $user_id, 'billing_first_name', sanitize_text_field( (string) $datos['given_name'] ) );
        update_user_meta( $user_id, 'billing_last_name', sanitize_text_field( (string) $datos['family_name'] ) );
    }

    return (int) $user_id;
}

/**
 * Establece la sesión y dispara los hooks de login (compatibles con Nextend).
 */
function mu_onetap_hacer_login( $user_id, $provider ) {
    $user = new WP_User( $user_id );

    wp_set_current_user( $user_id );
    wp_set_auth_cookie( $user_id, true, is_ssl() );

    do_action( 'nsl_before_wp_login' );
    do_action( 'wp_login', $user->user_login, $user );
    if ( $provider ) {
        do_action( 'nsl_login', $user_id, $provider );
        do_action( 'nsl_google_login', $user_id, $provider, array() );
    }
}

/**
 * Verifica el JWT (ID token) de Google: firma RS256, iss, aud, exp, email_verified.
 *
 * @return array|WP_Error Perfil {sub,email,name,given_name,family_name} o error.
 */
function mu_onetap_verificar_jwt( $jwt, $client_id ) {
    $partes = explode( '.', $jwt );
    if ( 3 !== count( $partes ) ) {
        return new WP_Error( 'jwt_formato', 'Token mal formado.' );
    }

    $header = json_decode( mu_onetap_b64url( $partes[0] ), true );
    if ( ! is_array( $header ) || empty( $header['kid'] ) ) {
        return new WP_Error( 'jwt_header', 'Cabecera inválida.' );
    }
    if ( ( $header['alg'] ?? '' ) !== 'RS256' ) {
        return new WP_Error( 'jwt_alg', 'Algoritmo no permitido.' );
    }

    $pem = mu_onetap_clave_publica( $header['kid'] );
    if ( is_wp_error( $pem ) ) {
        return $pem;
    }

    $firma = mu_onetap_b64url_bin( $partes[2] );
    $ok    = openssl_verify( $partes[0] . '.' . $partes[1], $firma, $pem, OPENSSL_ALGO_SHA256 );
    if ( 1 !== $ok ) {
        return new WP_Error( 'jwt_firma', 'Firma inválida.' );
    }

    $payload = json_decode( mu_onetap_b64url( $partes[1] ), true );
    if ( ! is_array( $payload ) ) {
        return new WP_Error( 'jwt_payload', 'Payload inválido.' );
    }

    $ahora = time();
    $iss   = $payload['iss'] ?? '';
    if ( 'accounts.google.com' !== $iss && 'https://accounts.google.com' !== $iss ) {
        return new WP_Error( 'jwt_iss', 'Emisor inválido.' );
    }
    if ( ( $payload['aud'] ?? '' ) !== $client_id ) {
        return new WP_Error( 'jwt_aud', 'Audiencia inválida.' );
    }
    if ( empty( $payload['exp'] ) || $payload['exp'] < $ahora - 60 ) {
        return new WP_Error( 'jwt_exp', 'Token expirado.' );
    }
    if ( empty( $payload['email'] ) || empty( $payload['email_verified'] ) || ! filter_var( $payload['email'], FILTER_VALIDATE_EMAIL ) ) {
        return new WP_Error( 'jwt_email', 'Email no verificado.' );
    }

    return array(
        'sub'         => (string) ( $payload['sub'] ?? '' ),
        'email'       => (string) $payload['email'],
        'name'        => (string) ( $payload['name'] ?? '' ),
        'given_name'  => (string) ( $payload['given_name'] ?? '' ),
        'family_name' => (string) ( $payload['family_name'] ?? '' ),
    );
}

/**
 * Obtiene (y cachea) la clave pública de Google para un `kid`.
 */
function mu_onetap_clave_publica( $kid ) {
    $jwk = get_transient( 'mu_onetap_jwks' );
    if ( ! is_array( $jwk ) ) {
        $resp = wp_safe_remote_get( 'https://www.googleapis.com/oauth2/v3/certs', array( 'timeout' => 8 ) );
        if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
            return new WP_Error( 'jwks_no_disponible', 'No se pudo obtener las claves de Google.' );
        }
        $body = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( ! is_array( $body ) || empty( $body['keys'] ) ) {
            return new WP_Error( 'jwks_invalido', 'Claves de Google inválidas.' );
        }
        $jwk = $body['keys'];
        set_transient( 'mu_onetap_jwks', $jwk, HOUR_IN_SECONDS );
    }

    foreach ( $jwk as $clave ) {
        if ( ( $clave['kid'] ?? '' ) === $kid ) {
            return mu_onetap_jwk_a_pem( $clave );
        }
    }

    return new WP_Error( 'kid_no_encontrado', 'Clave pública no encontrada.' );
}

/**
 * Convierte un JWK RSA (n,e) a PEM SubjectPublicKeyInfo.
 */
function mu_onetap_jwk_a_pem( $jwk ) {
    $modulus  = mu_onetap_b64url_bin( $jwk['n'] );
    $exponent = mu_onetap_b64url_bin( $jwk['e'] );

    $rsa_public = mu_onetap_der_secuencia( mu_onetap_der_entero( $modulus ) . mu_onetap_der_entero( $exponent ) );

    $oid = "\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01"; // 1.2.840.113549.1.1.1 (rsaEncryption)
    $alg  = mu_onetap_der_secuencia( "\x06\x09" . $oid . "\x05\x00" );
    $bit  = "\x03" . mu_onetap_der_largo( strlen( $rsa_public ) + 1 ) . "\x00" . $rsa_public;
    $spki = mu_onetap_der_secuencia( $alg . $bit );

    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $spki ), 64, "\n" ) . "-----END PUBLIC KEY-----\n";
}

function mu_onetap_der_largo( $len ) {
    if ( $len < 0x80 ) {
        return chr( $len );
    }
    $bytes = '';
    while ( $len > 0 ) {
        $bytes = chr( $len & 0xff ) . $bytes;
        $len >>= 8;
    }
    return chr( 0x80 | strlen( $bytes ) ) . $bytes;
}

function mu_onetap_der_entero( $bytes ) {
    $bytes = ltrim( $bytes, "\x00" );
    if ( '' === $bytes ) {
        $bytes = "\x00";
    }
    if ( ord( $bytes[0] ) > 0x7f ) {
        $bytes = "\x00" . $bytes;
    }
    return "\x02" . mu_onetap_der_largo( strlen( $bytes ) ) . $bytes;
}

function mu_onetap_der_secuencia( $contenido ) {
    return "\x30" . mu_onetap_der_largo( strlen( $contenido ) ) . $contenido;
}

function mu_onetap_b64url( $texto ) {
    $resto = strlen( $texto ) % 4;
    if ( $resto ) {
        $texto .= str_repeat( '=', 4 - $resto );
    }
    return base64_decode( strtr( $texto, '-_', '+/' ) );
}

function mu_onetap_b64url_bin( $texto ) {
    $bin = mu_onetap_b64url( $texto );
    return false === $bin ? '' : $bin;
}