<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * The redirect gateways built on Jws_Payment_Gateway, by id.
 *
 * Stripe and PayPal predate the base class and keep their own classes; every
 * place that used to ask "Stripe or PayPal?" now asks this registry about
 * anything else, so adding a gateway is one class and one line here.
 */
class Jws_Payment_Gateways {

	/**
	 * Every gateway built on Jws_Payment_Gateway: id => class name, in the
	 * order they appear on the Gateways tab. The class lives in
	 * class-jws-payment-{id}.php next to this file. This is the one list to
	 * add a gateway to.
	 */
	const CLASSES = array(
		'razorpay'    => 'Jws_Payment_Razorpay',
		'paystack'    => 'Jws_Payment_Paystack',
		'mollie'      => 'Jws_Payment_Mollie',
		'mercadopago' => 'Jws_Payment_Mercadopago',
		'square'      => 'Jws_Payment_Square',
		'flutterwave' => 'Jws_Payment_Flutterwave',
		'xendit'      => 'Jws_Payment_Xendit',
		'authorizenet' => 'Jws_Payment_Authorizenet',
	);

	/** Query var and transient prefix of the form-POST hand-off page. */
	const POST_VAR    = 'jws_payment_post';
	const POST_PREFIX = 'jws_payment_post_';

	/** Hooks what the gateways share on the front end. */
	public static function register() {
		add_action( 'template_redirect', array( __CLASS__, 'post_redirect' ), 1 );
	}

	/**
	 * The page that posts a form to a gateway's hosted page and nothing else.
	 *
	 * Opened from Jws_Payment_Gateway::post_redirect_url(). Used once: the
	 * transient is deleted as soon as the form is printed, so a reloaded or
	 * shared link cannot replay the gateway token.
	 */
	public static function post_redirect() {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- an unguessable, single-use key checked against the signed-in user.
		$key = isset( $_GET[ self::POST_VAR ] ) ? preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_GET[ self::POST_VAR ] ) ) : '';

		if ( '' === $key ) {
			return;
		}

		$data = get_transient( self::POST_PREFIX . $key );

		if ( ! is_array( $data ) || ! is_user_logged_in() || (int) $data['user'] !== get_current_user_id() ) {
			wp_safe_redirect( Jws_Payment_Checkout::page_url() );
			exit;
		}

		delete_transient( self::POST_PREFIX . $key );

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		?>
		<!doctype html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>" />
			<meta name="viewport" content="width=device-width, initial-scale=1" />
			<meta name="robots" content="noindex" />
			<title><?php echo esc_html__( 'Taking you to the payment page…', 'jws_streamvid' ); ?></title>
		</head>
		<body>
			<form id="jws-payment-post" method="post" action="<?php echo esc_url( $data['action'] ); ?>">
				<?php foreach ( (array) $data['fields'] as $name => $value ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" />
				<?php endforeach; ?>
				<noscript><button type="submit"><?php echo esc_html__( 'Continue to payment', 'jws_streamvid' ); ?></button></noscript>
			</form>
			<p><?php echo esc_html__( 'Taking you to the payment page…', 'jws_streamvid' ); ?></p>
			<script>document.getElementById('jws-payment-post').submit();</script>
		</body>
		</html>
		<?php
		exit;
	}

	/**
	 * Loads every gateway class whose file is present.
	 *
	 * Called once by the plugin loader, after the base class and before
	 * anything that asks which gateways exist.
	 */
	public static function load() {

		foreach ( array_keys( self::CLASSES ) as $id ) {

			$file = __DIR__ . '/class-jws-payment-' . $id . '.php';

			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	}

	/**
	 * id => class name of the gateways actually loaded.
	 *
	 * @return array<string, string>
	 */
	public static function all() {

		return array_filter(
			self::CLASSES,
			static function ( $class ) {
				return class_exists( $class ) && is_subclass_of( $class, 'Jws_Payment_Gateway' );
			}
		);
	}

	/** Whether a gateway id is one of these. */
	public static function has( $id ) {
		return isset( self::all()[ (string) $id ] );
	}

	/**
	 * The class behind a gateway id.
	 *
	 * @return string|null Class name, or null for Stripe, PayPal, WooCommerce
	 *                     and anything unknown.
	 */
	public static function get( $id ) {

		$all = self::all();

		return isset( $all[ (string) $id ] ) ? $all[ (string) $id ] : null;
	}
}
