<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * Xendit, over its current payments stack (docs.xendit.co).
 *
 * Every sale is a Payment Session in PAYMENT_LINK mode: Xendit's hosted page
 * with every channel the account has switched on (cards, e-wallets, virtual
 * accounts, QR). Back on the site, the session is read and then the payment
 * it produced — Xendit's own rule is that a completed session means checkout
 * finished, and only a SUCCEEDED payment means the money moved.
 *
 * A renewing plan is sold one billing period at a time for now. Xendit's
 * subscription sessions exist, but when their first cycle is charged is not
 * documented, and a plan that grants access before anything is charged is
 * not something to ship untested.
 */
class Jws_Payment_Xendit extends Jws_Payment_Gateway {

	const API = 'https://api.xendit.co/';

	/** The Payments API version the v3 payment endpoints are read with. */
	const PAYMENTS_VERSION = '2024-11-11';

	/* ---------------------------------------------------------------------- */
	/* Declarations                                                            */
	/* ---------------------------------------------------------------------- */

	public static function id() {
		return 'xendit';
	}

	public static function label() {
		return 'Xendit';
	}

	public static function field_keys() {
		return array( 'secret_key', 'country', 'callback_token' );
	}

	public static function fields() {
		return array(
			'secret_key'     => array( esc_html__( 'Secret API key', 'jws_streamvid' ), 'password', 'xnd_development_…' ),
			'country'        => array( esc_html__( 'Business country (ID, PH, VN, TH, SG or MY)', 'jws_streamvid' ), 'text', 'ID' ),
			'callback_token' => array( esc_html__( 'Webhook verification token', 'jws_streamvid' ), 'password', '' ),
		);
	}

	/** Payments are confirmed on return without it; refunds need it. */
	public static function optional_fields() {
		return array( 'callback_token' );
	}

	public static function method_note() {
		return esc_html__( "Sends the buyer to Xendit's payment page: cards, e-wallets, virtual accounts and QR in Indonesia, the Philippines, Vietnam, Thailand, Singapore and Malaysia. A membership bought this way is paid one term at a time and does not renew itself.", 'jws_streamvid' );
	}

	public static function webhook_help() {
		return esc_html__( 'In Xendit under Settings > Webhooks, paste this URL for Payment Session Completed, Payment Status and Refund, and copy the verification token shown there above. Xendit only returns buyers to https addresses and does not deliver to localhost.', 'jws_streamvid' );
	}

	public static function webhook_events() {
		return array( 'payment_session.completed', 'payment.capture', 'refund.succeeded' );
	}

	public static function renews() {
		return false;
	}

	public static function currencies() {
		return array( 'IDR', 'PHP', 'VND', 'THB', 'SGD', 'MYR', 'USD' );
	}

	public static function key_mode( array $config ) {
		return 0 === strpos( (string) ( $config['secret_key'] ?? '' ), 'xnd_development_' ) ? 'test' : '';
	}

	/** The country is part of every session, so it is cleaned to two letters. */
	public static function sanitize( $raw ) {

		$out            = parent::sanitize( $raw );
		$out['country'] = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) $out['country'] ), 0, 2 ) );

		return $out;
	}

	/* ---------------------------------------------------------------------- */
	/* Transport                                                               */
	/* ---------------------------------------------------------------------- */

	/** Basic auth with the secret key as the user name and no password. */
	protected static function request( $method, $path, $body = null, array $headers = array() ) {

		$config = static::config();

		return static::http(
			$method,
			self::API . ltrim( $path, '/' ),
			array_merge(
				array( 'Authorization' => 'Basic ' . base64_encode( (string) $config['secret_key'] . ':' ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic auth.
				$headers
			),
			$body
		);
	}

	/** Xendit errors are { error_code, message }. */
	protected static function error_message( $decoded ) {
		return is_array( $decoded ) ? (string) ( $decoded['message'] ?? '' ) : '';
	}

	/** Our reference: 1-64 characters, unique per order. */
	private static function reference( $order ) {
		return 'jws-' . (int) $order->id . '-' . substr( preg_replace( '/[^A-Za-z0-9]/', '', (string) $order->source_ref ), 0, 12 );
	}

	/** The order a reference names, if it is still exactly that order's. */
	private static function order_for_reference( $reference ) {

		if ( ! preg_match( '/^jws-(\d+)-[A-Za-z0-9]+$/', (string) $reference, $match ) ) {
			return null;
		}

		$order = static::find_order( (int) $match[1] );

		return $order && self::reference( $order ) === $reference ? $order : null;
	}

	/** An https URL, or '' — Xendit refuses anything else as a return URL. */
	private static function https_only( $url ) {
		return 0 === strpos( (string) $url, 'https://' ) ? (string) $url : '';
	}

	/* ---------------------------------------------------------------------- */
	/* Buying                                                                  */
	/* ---------------------------------------------------------------------- */

	public static function start( $order, array $item ) {

		$config = static::config();

		$body = array(
			'reference_id' => self::reference( $order ),
			'session_type' => 'PAY',
			'mode'         => 'PAYMENT_LINK',
			'currency'     => strtoupper( (string) $order->currency ),
			/* Major units, with the currency's own decimals (none for VND). */
			'amount'       => static::is_zero_decimal( $order->currency ) ? (int) round( (float) $order->amount ) : round( (float) $order->amount, 2 ),
			'country'      => (string) $config['country'],
			'description'  => substr( (string) $order->item_label, 0, 255 ),
			/* Within the tighter of Xendit's two documented metadata limits. */
			'metadata'     => array( 'order_id' => (string) (int) $order->id ),
		);

		$back = self::https_only( static::return_url( $order ) );

		if ( $back ) {
			$body['success_return_url'] = $back;
			$body['cancel_return_url']  = self::https_only( static::return_url( $order, true ) );
		}

		$created = self::request( 'POST', 'sessions', $body );

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		if ( empty( $created['payment_session_id'] ) || empty( $created['payment_link_url'] ) ) {
			return new WP_Error( 'jws_payment_xendit_link', esc_html__( 'Xendit did not say where to send you.', 'jws_streamvid' ) );
		}

		Jws_Payment_Orders::attach_ref( $order->id, $created['payment_session_id'] );

		return (string) $created['payment_link_url'];
	}

	/* ---------------------------------------------------------------------- */
	/* Settling                                                                */
	/* ---------------------------------------------------------------------- */

	public static function settle( $order ) {

		if ( Jws_Payment_Orders::STATUS_COMPLETED === $order->status ) {
			return true;
		}

		if ( ! $order->gateway_ref ) {
			return false;
		}

		$session = self::request( 'GET', 'sessions/' . rawurlencode( (string) $order->gateway_ref ) );

		if ( is_wp_error( $session ) ) {
			return false;
		}

		$status = (string) ( $session['status'] ?? '' );

		if ( in_array( $status, array( 'EXPIRED', 'CANCELED' ), true ) ) {
			Jws_Payment_Orders::mark_failed( $order->id );
			return false;
		}

		if ( 'COMPLETED' !== $status || empty( $session['payment_id'] ) || (string) ( $session['reference_id'] ?? '' ) !== self::reference( $order ) ) {
			return false;
		}

		if ( ! self::payment_succeeded( (string) $session['payment_id'] ) ) {
			return false;
		}

		Jws_Payment_Orders::mark_paid( $order->id );

		return true;
	}

	/** Whether a payment actually moved the money. */
	private static function payment_succeeded( $payment_id ) {

		$payment = self::request( 'GET', 'v3/payments/' . rawurlencode( $payment_id ), null, array( 'api-version' => self::PAYMENTS_VERSION ) );

		return ! is_wp_error( $payment ) && 'SUCCEEDED' === ( $payment['status'] ?? '' );
	}

	/* ---------------------------------------------------------------------- */
	/* Webhooks                                                                */
	/* ---------------------------------------------------------------------- */

	/**
	 * One Xendit webhook. The `x-callback-token` header must equal the
	 * account's verification token (a shared value, not a signature), and the
	 * session or payment is read back through the API before anything is
	 * granted.
	 */
	public static function receive( WP_REST_Request $request ) {

		$config = static::config();
		$token  = (string) ( $config['callback_token'] ?? '' );
		$sent   = (string) $request->get_header( 'x_callback_token' );

		if ( '' === $token || '' === $sent || ! hash_equals( $token, $sent ) || ! static::ready() ) {
			return static::rejected();
		}

		$event = json_decode( (string) $request->get_body(), true );
		$data  = is_array( $event ) ? (array) ( $event['data'] ?? array() ) : array();

		switch ( is_array( $event ) ? (string) ( $event['event'] ?? '' ) : '' ) {

			case 'payment_session.completed':
			case 'payment.capture':
				$order = self::order_for_reference( (string) ( $data['reference_id'] ?? '' ) );

				if ( $order ) {
					static::settle( $order );
				}
				break;

			case 'refund.succeeded':
				self::on_refund( $data );
				break;
		}

		return static::ok();
	}

	/**
	 * A refund went through. The refund names the payment; the payment names
	 * our reference. Only a refund of the whole price takes back what was
	 * granted.
	 */
	private static function on_refund( array $refund ) {

		if ( empty( $refund['payment_id'] ) ) {
			return;
		}

		$payment = self::request( 'GET', 'v3/payments/' . rawurlencode( (string) $refund['payment_id'] ), null, array( 'api-version' => self::PAYMENTS_VERSION ) );

		if ( is_wp_error( $payment ) ) {
			return;
		}

		$order  = self::order_for_reference( (string) ( $payment['reference_id'] ?? '' ) );
		$amount = (float) ( $refund['amount'] ?? $refund['refund_amount'] ?? 0 );

		if ( $order && $amount >= (float) $order->amount ) {
			Jws_Payment_Orders::mark_refunded( $order->id );
		}
	}

	/* ---------------------------------------------------------------------- */
	/* Cancelling                                                              */
	/* ---------------------------------------------------------------------- */

	/** Nothing to cancel: every Xendit sale here is a single payment. */
	public static function cancel_subscription( $ref ) {
		return new WP_Error( 'jws_payment_xendit_no_subscriptions', esc_html__( 'Xendit payments here do not renew, so there is no subscription to cancel.', 'jws_streamvid' ) );
	}
}
