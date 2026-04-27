<?php
/**
 * Muy Únicos — Plantilla Coming Soon v1.0.0
 *
 * Servida por inc/coming-soon.php cuando el modo Coming Soon
 * de Hostinger está activo y el visitante no es administrador.
 *
 * @package GeneratePress_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blog_name    = get_bloginfo( 'name' );
$blog_tagline = get_bloginfo( 'description' );
$logo_url     = function_exists( 'get_custom_logo' ) ? wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'medium' ) : '';
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $blog_name ); ?> — Muy pronto</title>
	<?php wp_head(); ?>
</head>
<body class="mu-cs-body">
<?php wp_body_open(); ?>

<main class="mu-cs" role="main" aria-labelledby="mu-cs-title">

	<div class="mu-cs__card">

		<?php if ( $logo_url ) : ?>
		<div class="mu-cs__logo" aria-hidden="true">
			<img
				src="<?php echo esc_url( $logo_url ); ?>"
				alt="<?php echo esc_attr( $blog_name ); ?>"
				width="160"
				height="60"
				loading="eager"
				fetchpriority="high"
			>
		</div>
		<?php else : ?>
		<div class="mu-cs__logo mu-cs__logo--text" aria-hidden="true">
			<?php echo esc_html( $blog_name ); ?>
		</div>
		<?php endif; ?>

		<h1 id="mu-cs-title" class="mu-cs__title">
			<?php esc_html_e( '¡Muy pronto!', 'generatepress-child' ); ?>
		</h1>

		<p class="mu-cs__subtitle">
			<?php
			if ( $blog_tagline ) {
				echo esc_html( $blog_tagline );
			} else {
				esc_html_e( 'Estamos preparando algo increíble para vos. Volvé en breve.', 'generatepress-child' );
			}
			?>
		</p>

		<div class="mu-cs__divider" aria-hidden="true"></div>

		<p class="mu-cs__contact">
			<?php esc_html_e( '¿Consultas?', 'generatepress-child' ); ?>
			<a
				href="https://wa.me/<?php echo esc_attr( get_option( 'woocommerce_store_phone', '' ) ); ?>"
				class="mu-cs__link"
				target="_blank"
				rel="noopener noreferrer"
				aria-label="<?php esc_attr_e( 'Contactar por WhatsApp', 'generatepress-child' ); ?>"
			>
				<?php esc_html_e( 'Escribinos por WhatsApp', 'generatepress-child' ); ?>
			</a>
		</p>

	</div>

	<div class="mu-cs__shapes" aria-hidden="true">
		<span class="mu-cs__shape mu-cs__shape--1"></span>
		<span class="mu-cs__shape mu-cs__shape--2"></span>
		<span class="mu-cs__shape mu-cs__shape--3"></span>
	</div>

</main>

<?php wp_footer(); ?>
</body>
</html>
