<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * Square, over its REST API (developer.squareup.com).
 *
 * Every sale is a Checkout API payment link in quick_pay form: a name, a
 * price and a location, no catalog item needed. The buyer pays on Square's
 * page and is sent back; the order Square made for the link is then read
 * back to find its payment.
 *
 * A renewing plan is sold one billing period at a time, like the WooCommerce
 * path. Square's own subscription checkout needs a plan variation created in
 * the catalog first, cannot charge an intro price, and leaves nothing on the
 * subscription that points back to the order it was bought with — and Square
 * only operates where Stripe already does.
 */
class Jws_Payment_Square extends Jws_Payment_Gateway {

	const LIVE_API    = 'https://connect.squareup.com/v2/';
	const SANDBOX_API = 'https://connect.squareupsandbox.com/v2/';

	/* ---------------------------------------------------------------------- */
	/* Declarations                                                            */
	/* ---------------------------------------------------------------------- */

	public static function id() {
		return 'square';
	}

	public static function label() {
		return 'Square';
	}

	public static function field_keys() {
		return array( 'access_token', 'location_id', 'signature_key' );
	}

	public static function fields() {
		return array(
			'access_token'  => array( esc_html__( 'Access token', 'jws_streamvid' ), 'password', '' ),
			'location_id'   => array( esc_html__( 'Location ID', 'jws_streamvid' ), 'text', 'L…' ),
			'signature_key' => array( esc_html__( 'Webhook signature key', 'jws_streamvid' ), 'password', '' ),
		);
	}

	/** Payments are confirmed on return without it; refunds need it. */
	public static function optional_fields() {
		return array( 'signature_key' );
	}

	public static function method_note() {
		return esc_html__( "Sends the buyer to a Square payment page. Square works in the United States, Canada, the United Kingdom, Ireland, France, Spain, Australia and Japan, in the currency of your Square location. A membership bought this way is paid one term at a time and does not renew itself.", 'jws_streamvid' );
	}

	public static function webhook_help() {
		return esc_html__( 'Add this URL in the Square Developer Console under your app > Webhooks > Subscriptions, then paste its Signature Key above. Square only delivers to public https addresses.', 'jws_streamvid' );
	}

	public static function webhook_events() {
		return array( 'payment.updated', 'refund.updated' );
	}

	public static function renews() {
		return false;
	}

	/** The currencies of the countries Square processes payments in. */
	public static function currencies() {
		return array( 'USD', 'CAD', 'GBP', 'EUR', 'AUD', 'JPY' );
	}

	/**
	 * Square charges only in the location's own currency, so the location is
	 * asked (and the answer kept for a day) before the method is offered.
	 */
	public static function supports( array $item ) {

		$base = parent::supports( $item );

		if ( is_wp_error( $base ) ) {
			return $base;
		}

		$currency = self::location_currency();

		if ( $currency && strtoupper( (string) $item['currency'] ) !== $currency ) {
			return new WP_Error(
				'jws_payment_square_currency',
				sprintf(
					/* translators: %s: currency code of the Square location. */
					esc_html__( 'This Square location charges in %s only.', 'jws_streamvid' ),
					$currency
				)
			);
		}

		return true;
	}

	/* ---------------------------------------------------------------------- */
	/* Transport                                                               */
	/* ---------------------------------------------------------------------- */

	protected static function request( $method, $path, $body = null ) {

		$config = static::config();

		return static::http(
			$method,
			( 'live' === static::mode() ? self::LIVE_API : self::SANDBOX_API ) . ltrim( $path, '/' ),
			array( 'Authorization' => 'Bearer ' . (string) $config['access_token'] ),
			$body
		);
	}

	/** Square errors are { errors: [ { category, code, detail } ] }. */
	protected static function error_message( $decoded ) {
		return is_array( $decoded ) ? (string) ( $decoded['errors'][0]['detail'] ?? '' ) : '';
	}

	/**
	 * The location's currency, or '' when it cannot be read.
	 *
	 * Cached per location and mode; an unreadable location is not cached, so
	 * a fixed token is picked up on the next page load.
	 */
	private static function location_currency() {

		$config   = static::config();
		$location = (string) ( $config['location_id'] ?? '' );

		if ( '' === $location || ! static::ready() ) {
			return '';
		}

		$key    = 'jws_payment_square_cur_' . md5( static::mode() . $location );
		$cached = get_transient( $key );

		if ( $cached ) {
			return (string) $cached;
		}

		$found    = self::request( 'GET', 'locations/' . rawurlencode( $location ) );
		$currency = is_wp_error( $found ) ? '' : strtoupper( (string) ( $found['location']['currency'] ?? '' ) );

		if ( $currency ) {
			set_transient( $key, $currency, DAY_IN_SECONDS );
		}

		return $currency;
	}

	/** A Money object: the smallest unit, in the order's currency. */
	private static function money( $amount, $currency ) {
		return array(
			'amount'   => static::minor_units( $amount, $currency ),
			'currency' => strtoupper( (string) $currency ),
		);
	}

	/* ---------------------------------------------------------------------- */
	/* Buying                                                                  */
	/* ---------------------------------------------------------------------- */

	public static function start( $order, array $item ) {

		$config = static::config();

		$created = self::request(
			'POST',
			'online-checkout/payment-links',
			array(
				/* One link per order, so a retried request cannot make two. */
				'idempotency_key'  => substr( 'jws-' . (int) $order->id . '-' . (string) $order->source_ref, 0, 192 ),
				'quick_pay'        => array(
					'name'        => substr( (string) $order->item_label, 0, 255 ),
					'price_money' => self::money( $order->amount, $order->currency ),
					'location_id' => (string) $config['location_id'],
				),
				'checkout_options' => array(
					'redirect_url'  => static::return_url( $order ),
					/* A tip would make the total differ from the price. */
					'allow_tipping' => false,
				),
				'payment_note'     => substr( sprintf( '%s %s', get_bloginfo( 'name' ), $order->order_number ), 0, 500 ),
			)
		);

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		$link = (array) ( $created['payment_link'] ?? array() );

		if ( empty( $link['url'] ) || empty( $link['order_id'] ) ) {
			return new WP_Error( 'jws_payment_square_link', esc_html__( 'Square did not say where to send you.', 'jws_streamvid' ) );
		}

		/* Square's order id: what its payment and refund events point at. */
		Jws_Payment_Orders::attach_ref( $order->id, $link['order_id'] );

		return (string) $link['url'];
	}

	/* ---------------------------------------------------------------------- */
	/* Settling                                                                */
	/* ---------------------------------------------------------------------- */

	/**
	 * Reads Square's order back and looks at its payment.
	 *
	 * Square's own docs warn that the redirect does not mean the payment
	 * completed, and that a payment-link order stays OPEN, so the order state
	 * proves nothing: only a payment with status COMPLETED, for this amount,
	 * does.
	 */
	public static function settle( $order ) {

		if ( Jws_Payment_Orders::STATUS_COMPLETED === $order->status ) {
			return true;
		}

		if ( ! $order->gateway_ref ) {
			return false;
		}

		$found = self::request( 'GET', 'orders/' . rawurlencode( (string) $order->gateway_ref ) );

		if ( is_wp_error( $found ) ) {
			return false;
		}

		foreach ( (array) ( $found['order']['tenders'] ?? array() ) as $tender ) {

			$payment_id = (string) ( $tender['payment_id'] ?? $tender['id'] ?? '' );

			if ( '' === $payment_id ) {
				continue;
			}

			$payment = self::request( 'GET', 'payments/' . rawurlencode( $payment_id ) );

			if ( ! is_wp_error( $payment ) && self::payment_settles( $order, (array) ( $payment['payment'] ?? array() ) ) ) {
				Jws_Payment_Orders::mark_paid( $order->id );
				return true;
			}
		}

		return false;
	}

	/** Whether a Square payment is the completed payment for this order. */
	private static function payment_settles( $order, array $payment ) {

		$expected = self::money( $order->amount, $order->currency );

		return 'COMPLETED' === ( $payment['status'] ?? '' )
			&& (string) ( $payment['order_id'] ?? '' ) === (string) $order->gateway_ref
			&& (int) ( $payment['amount_money']['amount'] ?? -1 ) === $expected['amount']
			&& strtoupper( (string) ( $payment['amount_money']['currency'] ?? '' ) ) === $expected['currency'];
	}

	/* ---------------------------------------------------------------------- */
	/* Webhooks                                                                */
	/* ---------------------------------------------------------------------- */

	/**
	 * One Square webhook.
	 *
	 * x-square-hmacsha256-signature is the base64 HMAC-SHA256 of the
	 * notification URL followed by the raw body, under the signature key.
	 * The URL is this site's own webhook URL — the one the admin pasted into
	 * Square — rather than one rebuilt from the request, which a proxy can
	 * change.
	 */
	public static function receive( WP_REST_Request $request ) {

		$config = static::config();
		$key    = (string) ( $config['signature_key'] ?? '' );
		$body   = (string) $request->get_body();
		$sent   = (string) $request->get_header( 'x_square_hmacsha256_signature' );

		if ( '' === $key || '' === $sent || ! static::ready() ) {
			return static::rejected();
		}

		$expected = base64_encode( hash_hmac( 'sha256', static::webhook_url() . $body, $key, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Square's signature encoding.

		if ( ! hash_equals( $expected, $sent ) ) {
			return static::rejected();
		}

		$event  = json_decode( $body, true );
		$object = is_array( $event ) ? (array) ( $event['data']['object'] ?? array() ) : array();

		switch ( is_array( $event ) ? (string) ( $event['type'] ?? '' ) : '' ) {

			case 'payment.updated':
			case 'payment.created':
				$payment = (array) ( $object['payment'] ?? array() );
				$order   = static::find_order( 0, (string) ( $payment['order_id'] ?? '' ) );

				if ( $order && self::payment_settles( $order, $payment ) ) {
					Jws_Payment_Orders::mark_paid( $order->id );
				}
				break;

			case 'refund.updated':
			case 'refund.created':
				$refund = (array) ( $object['refund'] ?? array() );
				$order  = static::find_order( 0, (string) ( $refund['order_id'] ?? '' ) );

				/* Only a completed refund of the whole price takes back what
				   was granted. */
				if ( $order
					&& 'COMPLETED' === ( $refund['status'] ?? '' )
					&& (int) ( $refund['amount_money']['amount'] ?? 0 ) >= static::minor_units( $order->amount, $order->currency ) ) {
					Jws_Payment_Orders::mark_refunded( $order->id );
				}
				break;
		}

		return static::ok();
	}

	/* ---------------------------------------------------------------------- */
	/* Cancelling                                                              */
	/* ---------------------------------------------------------------------- */

	/** Nothing to cancel: every Square sale here is a single payment. */
	public static function cancel_subscription( $ref ) {
		return new WP_Error( 'jws_payment_square_no_subscriptions', esc_html__( 'Square payments here do not renew, so there is no subscription to cancel.', 'jws_streamvid' ) );
	}
}
