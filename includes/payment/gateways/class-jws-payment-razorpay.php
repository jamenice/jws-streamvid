<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * Razorpay, over its REST API (razorpay.com/docs/api).
 *
 * Every sale is a Payment Link: Razorpay's hosted page, which sends the buyer
 * back to the checkout once they have paid.
 *
 * A renewing plan is sold one billing period at a time, like the WooCommerce
 * path. Razorpay's own Subscriptions are authorised on a hosted page that
 * does not send the buyer back to the site, and the add-on that would charge
 * an intro price is deprecated, so a renewing Razorpay plan would leave the
 * buyer stranded on Razorpay with nothing granted until a webhook arrived —
 * and never, on a site Razorpay cannot reach.
 */
class Jws_Payment_Razorpay extends Jws_Payment_Gateway {

	const API = 'https://api.razorpay.com/v1/';

	/* ---------------------------------------------------------------------- */
	/* Declarations                                                            */
	/* ---------------------------------------------------------------------- */

	public static function id() {
		return 'razorpay';
	}

	public static function label() {
		return 'Razorpay';
	}

	public static function field_keys() {
		return array( 'key_id', 'key_secret', 'webhook_secret' );
	}

	public static function fields() {
		return array(
			'key_id'         => array( esc_html__( 'Key ID', 'jws_streamvid' ), 'text', 'rzp_test_…' ),
			'key_secret'     => array( esc_html__( 'Key secret', 'jws_streamvid' ), 'password', '' ),
			'webhook_secret' => array( esc_html__( 'Webhook secret', 'jws_streamvid' ), 'password', '' ),
		);
	}

	/** Payments are confirmed on return without it; refunds need it. */
	public static function optional_fields() {
		return array( 'webhook_secret' );
	}

	public static function method_note() {
		return esc_html__( "Sends the buyer to a Razorpay payment page: cards, UPI, netbanking and wallets. A membership bought this way is paid one term at a time and does not renew itself.", 'jws_streamvid' );
	}

	public static function webhook_help() {
		return esc_html__( 'Add this URL in Razorpay under Account & Settings > Webhooks, type any secret there and paste the same secret above. Razorpay does not deliver to localhost.', 'jws_streamvid' );
	}

	public static function webhook_events() {
		return array( 'payment_link.paid', 'refund.processed' );
	}

	public static function renews() {
		return false;
	}

	public static function key_mode( array $config ) {

		$key = (string) ( $config['key_id'] ?? '' );

		if ( 0 === strpos( $key, 'rzp_live_' ) ) {
			return 'live';
		}

		return 0 === strpos( $key, 'rzp_test_' ) ? 'test' : '';
	}

	/* ---------------------------------------------------------------------- */
	/* Transport                                                               */
	/* ---------------------------------------------------------------------- */

	protected static function request( $method, $path, $body = null ) {

		$config = static::config();

		return static::http(
			$method,
			self::API . ltrim( $path, '/' ),
			array(
				'Authorization' => 'Basic ' . base64_encode( $config['key_id'] . ':' . $config['key_secret'] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic auth.
			),
			$body
		);
	}

	/** Razorpay errors are { error: { code, description } }. */
	protected static function error_message( $decoded ) {
		return is_array( $decoded ) ? (string) ( $decoded['error']['description'] ?? '' ) : '';
	}

	/**
	 * An amount in Razorpay's smallest unit.
	 *
	 * Razorpay's own exponent table: most currencies have two decimals, a
	 * few have none, and six have three — where Razorpay also asks for the
	 * last digit to be 0, which an amount with two decimals already is.
	 */
	private static function minor( $amount, $currency ) {

		$currency = strtoupper( (string) $currency );
		$zero     = array( 'BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' );
		$three    = array( 'BHD', 'IQD', 'JOD', 'KWD', 'OMR', 'TND' );

		if ( in_array( $currency, $zero, true ) ) {
			return (int) round( (float) $amount );
		}

		if ( in_array( $currency, $three, true ) ) {
			return (int) round( (float) $amount * 100 ) * 10;
		}

		return (int) round( (float) $amount * 100 );
	}

	/* ---------------------------------------------------------------------- */
	/* Buying                                                                  */
	/* ---------------------------------------------------------------------- */

	public static function start( $order, array $item ) {

		$user = get_userdata( (int) $order->user_id );

		$body = array(
			'amount'          => self::minor( $order->amount, $order->currency ),
			'currency'        => strtoupper( (string) $order->currency ),
			/* Unique per link and at most 40 characters. */
			'reference_id'    => substr( 'jws-' . (int) $order->id . '-' . substr( (string) $order->source_ref, 0, 8 ), 0, 40 ),
			'description'     => substr( (string) $order->item_label, 0, 2048 ),
			'notes'           => array( 'order_id' => (string) (int) $order->id ),
			'callback_url'    => static::return_url( $order ),
			'callback_method' => 'get',
		);

		if ( $user ) {
			$body['customer'] = array(
				'name'  => $user->display_name,
				'email' => $user->user_email,
			);
		}

		$created = self::request( 'POST', 'payment_links', $body );

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		if ( empty( $created['id'] ) || empty( $created['short_url'] ) ) {
			return new WP_Error( 'jws_payment_razorpay_link', esc_html__( 'Razorpay did not say where to send you.', 'jws_streamvid' ) );
		}

		Jws_Payment_Orders::attach_ref( $order->id, $created['id'] );

		return (string) $created['short_url'];
	}

	/* ---------------------------------------------------------------------- */
	/* Settling                                                                */
	/* ---------------------------------------------------------------------- */

	/**
	 * Reads the Payment Link back. The signature Razorpay adds to the return
	 * URL is not used: the order of its fields is documented but not how they
	 * are joined, and the link's own status from the API is the better proof.
	 */
	public static function settle( $order ) {

		if ( Jws_Payment_Orders::STATUS_COMPLETED === $order->status ) {
			return true;
		}

		if ( ! $order->gateway_ref ) {
			return false;
		}

		$link = self::request( 'GET', 'payment_links/' . rawurlencode( (string) $order->gateway_ref ) );

		if ( is_wp_error( $link ) ) {
			return false;
		}

		$status = (string) ( $link['status'] ?? '' );

		if ( in_array( $status, array( 'cancelled', 'expired' ), true ) ) {
			Jws_Payment_Orders::mark_failed( $order->id );
			return false;
		}

		if ( 'paid' !== $status ) {
			return false;
		}

		Jws_Payment_Orders::mark_paid( $order->id );

		return true;
	}

	/* ---------------------------------------------------------------------- */
	/* Webhooks                                                                */
	/* ---------------------------------------------------------------------- */

	/**
	 * One Razorpay webhook.
	 *
	 * X-Razorpay-Signature is the HMAC-SHA256 of the raw body under the
	 * webhook secret, so the body is checked before it is decoded.
	 */
	public static function receive( WP_REST_Request $request ) {

		$config = static::config();
		$secret = (string) ( $config['webhook_secret'] ?? '' );
		$body   = (string) $request->get_body();
		$sent   = (string) $request->get_header( 'x_razorpay_signature' );

		if ( '' === $secret || '' === $sent || ! hash_equals( hash_hmac( 'sha256', $body, $secret ), $sent ) ) {
			return static::rejected();
		}

		$event = json_decode( $body, true );

		if ( ! is_array( $event ) || empty( $event['event'] ) ) {
			return static::rejected( 'bad_payload' );
		}

		switch ( $event['event'] ) {

			case 'payment_link.paid':
				$link  = (array) ( $event['payload']['payment_link']['entity'] ?? array() );
				$order = static::find_order( (int) ( $link['notes']['order_id'] ?? 0 ), (string) ( $link['id'] ?? '' ) );

				if ( $order && (string) $order->gateway_ref === (string) ( $link['id'] ?? '' ) ) {
					static::settle( $order );
				}
				break;

			case 'refund.processed':
				$payment = (array) ( $event['payload']['payment']['entity'] ?? array() );
				$order   = static::find_order( (int) ( $payment['notes']['order_id'] ?? 0 ) );

				/* Only a full refund takes back what was granted. */
				if ( $order && (int) ( $payment['amount'] ?? 0 ) > 0 && (int) ( $payment['amount_refunded'] ?? 0 ) >= (int) $payment['amount'] ) {
					Jws_Payment_Orders::mark_refunded( $order->id );
				}
				break;
		}

		return static::ok();
	}

	/* ---------------------------------------------------------------------- */
	/* Cancelling                                                              */
	/* ---------------------------------------------------------------------- */

	/** Nothing to cancel: every Razorpay sale here is a single payment. */
	public static function cancel_subscription( $ref ) {
		return new WP_Error( 'jws_payment_razorpay_no_subscriptions', esc_html__( 'Razorpay payments here do not renew, so there is no subscription to cancel.', 'jws_streamvid' ) );
	}
}
