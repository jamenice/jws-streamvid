<?php

/**
 * PayPal, over its REST API.
 *
 * The shape of the two products is not the same as Stripe's, and pretending
 * otherwise is what makes these integrations rot:
 *
 *  - A one-off sale is an Order that is created, approved by the payer on
 *    PayPal, and then captured by us. The money does not move until we capture.
 *  - A subscription cannot be priced inline at all. PayPal needs a Product and
 *    a Plan to exist first, so both are built on the first sale and cached the
 *    same way Stripe's Price is. The cheaper first period is a TRIAL billing
 *    cycle, not a discount.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/drama
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Drama_Paypal {

	const TOKEN_TRANSIENT = 'jws_drama_paypal_token';

	public static function config() {
		return Jws_Drama_Settings::get( 'paypal' );
	}

	public static function ready() {

		$config = self::config();

		return ! empty( $config['enabled'] ) && ! empty( $config['client_id'] ) && ! empty( $config['secret'] );
	}

	public static function mode() {

		$config = self::config();

		return 'live' === $config['mode'] ? 'live' : 'sandbox';
	}

	private static function base() {
		return 'live' === self::mode() ? 'https://api-m.paypal.com/' : 'https://api-m.sandbox.paypal.com/';
	}

	/* ---------------------------------------------------------------------- */
	/* Transport                                                               */
	/* ---------------------------------------------------------------------- */

	/**
	 * An OAuth token, cached until shortly before PayPal stops accepting it.
	 *
	 * Tokens last hours, so fetching one per request would be a round trip
	 * bought for nothing. The transient expires a minute early to avoid using
	 * one that dies mid-flight.
	 */
	public static function token( $force = false ) {

		$config = self::config();

		if ( empty( $config['client_id'] ) || empty( $config['secret'] ) ) {
			return new WP_Error( 'jws_drama_paypal_keys', esc_html__( 'PayPal is not configured.', 'jws_streamvid' ) );
		}

		$key = self::TOKEN_TRANSIENT . '_' . self::mode();

		if ( ! $force ) {

			$cached = get_transient( $key );

			if ( $cached ) {
				return $cached;
			}
		}

		$response = wp_remote_post(
			self::base() . 'v1/oauth2/token',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $config['client_id'] . ':' . $config['secret'] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => 'grant_type=client_credentials',
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			return new WP_Error(
				'jws_drama_paypal_auth',
				isset( $body['error_description'] ) ? $body['error_description'] : esc_html__( 'PayPal refused the credentials.', 'jws_streamvid' )
			);
		}

		set_transient( $key, $body['access_token'], max( 60, (int) $body['expires_in'] - 60 ) );

		return $body['access_token'];
	}

	/**
	 * One call to PayPal.
	 *
	 * @param bool $retrying Internal. A cached token can be revoked or invalidated
	 *                       server-side before it expires; one 401 is worth a
	 *                       fresh token and a second try rather than a failed sale.
	 */
	public static function request( $method, $path, $body = null, $headers = array(), $retrying = false ) {

		$token = self::token( $retrying );

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$args = array(
			'method'  => strtoupper( $method ),
			'timeout' => 30,
			'headers' => array_merge(
				array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				$headers
			),
		);

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( self::base() . ltrim( $path, '/' ), $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 401 === $code && ! $retrying ) {
			delete_transient( self::TOKEN_TRANSIENT . '_' . self::mode() );

			return self::request( $method, $path, $body, $headers, true );
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 300 ) {

			$message = esc_html__( 'PayPal refused the request.', 'jws_streamvid' );

			if ( ! empty( $decoded['details'][0]['description'] ) ) {
				$message = $decoded['details'][0]['description'];
			} elseif ( ! empty( $decoded['message'] ) ) {
				$message = $decoded['message'];
			}

			return new WP_Error( 'jws_drama_paypal_' . ( $decoded['name'] ?? 'error' ), $message, $decoded );
		}

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * An amount as PayPal wants it: a string, with the right number of decimals.
	 *
	 * Sending "4.99" for a currency that has no minor unit is rejected outright,
	 * which is a better failure than Stripe's — but only if we format it right.
	 */
	public static function amount( $value, $currency ) {

		$zero_decimal = array( 'HUF', 'JPY', 'TWD' );

		return in_array( strtoupper( $currency ), $zero_decimal, true )
			? (string) (int) round( (float) $value )
			: number_format( (float) $value, 2, '.', '' );
	}

	/** Finds the link PayPal wants the payer sent to. */
	private static function link( array $object, array $rels ) {

		foreach ( (array) ( $object['links'] ?? array() ) as $link ) {
			if ( isset( $link['rel'] ) && in_array( $link['rel'], $rels, true ) ) {
				return $link['href'];
			}
		}

		return '';
	}

	/* ---------------------------------------------------------------------- */
	/* Provisioned objects                                                     */
	/* ---------------------------------------------------------------------- */

	private static function slot( $fingerprint ) {
		return Jws_Drama_Settings::gateway_slot( 'paypal', self::mode(), $fingerprint );
	}

	/**
	 * The billing Plan behind a VIP plan, built on first sale.
	 *
	 * PayPal insists a Plan belongs to a Product, so both are made and cached.
	 * The intro price is a TRIAL cycle of exactly one period followed by the
	 * regular one — PayPal's model happens to describe "first week cheaper"
	 * directly, where Stripe needed a coupon bolted on.
	 */
	public static function plan_id( array $plan ) {

		$refs = Jws_Drama_Settings::refs( self::slot( $plan['fingerprint'] ) );

		if ( ! empty( $refs['paypal_plan'] ) ) {
			return $refs['paypal_plan'];
		}

		$currency = Jws_Drama_Settings::get( 'currency', 'USD' );
		$product  = $refs['paypal_product'] ?? '';

		if ( ! $product ) {

			$created = self::request(
				'POST',
				'v1/catalogs/products',
				array(
					'name'        => $plan['name'],
					'type'        => 'SERVICE',
					'category'    => 'ONLINE_SERVICES',
					'description' => sprintf(
						/* translators: %s: site name */
						esc_html__( 'VIP membership on %s', 'jws_streamvid' ),
						get_bloginfo( 'name' )
					),
				),
				array( 'PayPal-Request-Id' => 'jws-drama-product-' . $plan['fingerprint'] )
			);

			if ( is_wp_error( $created ) ) {
				return $created;
			}

			$product = $created['id'];

			Jws_Drama_Settings::remember( self::slot( $plan['fingerprint'] ), 'paypal_product', $product );
		}

		$frequency = array(
			'interval_unit'  => strtoupper( $plan['period'] ),
			'interval_count' => (int) $plan['cycle'],
		);

		$cycles   = array();
		$sequence = 1;

		if ( ! empty( $plan['has_intro'] ) ) {

			$cycles[] = array(
				'frequency'      => $frequency,
				'tenure_type'    => 'TRIAL',
				'sequence'       => $sequence,
				'total_cycles'   => 1,
				'pricing_scheme' => array(
					'fixed_price' => array(
						'value'         => self::amount( $plan['intro'], $currency ),
						'currency_code' => strtoupper( $currency ),
					),
				),
			);

			$sequence++;
		}

		$cycles[] = array(
			'frequency'      => $frequency,
			'tenure_type'    => 'REGULAR',
			'sequence'       => $sequence,
			/* 0 is "until cancelled", which is what auto-renew means. */
			'total_cycles'   => 0,
			'pricing_scheme' => array(
				'fixed_price' => array(
					'value'         => self::amount( $plan['price'], $currency ),
					'currency_code' => strtoupper( $currency ),
				),
			),
		);

		$created = self::request(
			'POST',
			'v1/billing/plans',
			array(
				'product_id'          => $product,
				'name'                => $plan['name'],
				'billing_cycles'      => $cycles,
				'payment_preferences' => array(
					'auto_bill_outstanding'     => true,
					'setup_fee_failure_action'  => 'CANCEL',
					'payment_failure_threshold' => 2,
				),
			),
			array( 'PayPal-Request-Id' => 'jws-drama-plan-' . $plan['fingerprint'] )
		);

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		Jws_Drama_Settings::remember( self::slot( $plan['fingerprint'] ), 'paypal_plan', $created['id'] );

		return $created['id'];
	}

	/* ---------------------------------------------------------------------- */
	/* Buying                                                                  */
	/* ---------------------------------------------------------------------- */

	public static function return_url( $order_id, $cancelled = false ) {

		$args = array( Jws_Drama_Stripe::RETURN_VAR => (int) $order_id, 'gw' => 'paypal' );

		if ( $cancelled ) {
			$args['cancelled'] = 1;
		}

		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * A one-off sale: coins.
	 *
	 * @return string|WP_Error Where to send the payer.
	 */
	public static function create_order( $order ) {

		$currency = strtoupper( $order->currency );

		$created = self::request(
			'POST',
			'v2/checkout/orders',
			array(
				'intent'         => 'CAPTURE',
				'purchase_units' => array(
					array(
						'custom_id'   => (string) $order->id,
						'description' => substr( $order->label, 0, 127 ),
						'amount'      => array(
							'currency_code' => $currency,
							'value'         => self::amount( $order->amount, $currency ),
						),
					),
				),
				'payment_source' => array(
					'paypal' => array(
						'experience_context' => array(
							'user_action' => 'PAY_NOW',
							'return_url'  => self::return_url( $order->id ),
							'cancel_url'  => self::return_url( $order->id, true ),
						),
					),
				),
			),
			array( 'PayPal-Request-Id' => 'jws-drama-order-' . $order->id )
		);

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		Jws_Drama_Orders::attach_ref( $order->id, $created['id'] );

		$url = self::link( $created, array( 'payer-action', 'approve' ) );

		return $url ? $url : new WP_Error( 'jws_drama_paypal_link', esc_html__( 'PayPal did not say where to send you.', 'jws_streamvid' ) );
	}

	/**
	 * Takes the money for an approved order.
	 *
	 * Nothing has been charged until this runs, which is why the return handler
	 * calls it rather than waiting for a webhook: an approved-but-uncaptured
	 * order is a shopper who thinks they have paid and has not.
	 */
	public static function capture( $paypal_order_id ) {

		return self::request(
			'POST',
			'v2/checkout/orders/' . rawurlencode( $paypal_order_id ) . '/capture',
			new stdClass(),
			array( 'PayPal-Request-Id' => 'jws-drama-capture-' . $paypal_order_id )
		);
	}

	/**
	 * A subscription: VIP.
	 *
	 * @return string|WP_Error Where to send the payer.
	 */
	public static function create_subscription( $order, array $plan, $retrying = false ) {

		$plan_id = self::plan_id( $plan );

		if ( is_wp_error( $plan_id ) ) {
			return $plan_id;
		}

		$created = self::request(
			'POST',
			'v1/billing/subscriptions',
			array(
				'plan_id'             => $plan_id,
				'custom_id'           => (string) $order->id,
				'application_context' => array(
					'user_action' => 'SUBSCRIBE_NOW',
					'return_url'  => self::return_url( $order->id ),
					'cancel_url'  => self::return_url( $order->id, true ),
				),
			),
			array( 'PayPal-Request-Id' => 'jws-drama-sub-' . $order->id )
		);

		/* A cached plan id that no longer resolves is one forget away from
		   working again; see the same guard on the Stripe side. */
		if ( is_wp_error( $created ) && ! $retrying && false !== strpos( $created->get_error_code(), 'RESOURCE_NOT_FOUND' ) ) {

			Jws_Drama_Settings::forget( self::slot( $plan['fingerprint'] ) );

			return self::create_subscription( $order, $plan, true );
		}

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		Jws_Drama_Orders::attach_ref( $order->id, $created['id'] );

		$url = self::link( $created, array( 'approve' ) );

		return $url ? $url : new WP_Error( 'jws_drama_paypal_link', esc_html__( 'PayPal did not say where to send you.', 'jws_streamvid' ) );
	}
}
