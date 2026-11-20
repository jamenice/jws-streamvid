<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * PayPal, spoken directly over its REST API.
 *
 * Two shapes of sale: a one-off Order that is created, approved by the payer
 * and then captured, and a Subscription against a Billing Plan that PayPal
 * bills on its own from then on. Both send the payer away and back, and both
 * end at Jws_Payment_Orders::mark_paid().
 */
class Jws_Payment_Paypal {

	const LIVE_API    = 'https://api-m.paypal.com/';
	const SANDBOX_API = 'https://api-m.sandbox.paypal.com/';

	const TOKEN_TRANSIENT = 'jws_payment_paypal_token';

	public static function config() {
		return Jws_Payment_Settings::get( 'paypal' );
	}

	public static function ready() {
		return Jws_Payment_Settings::gateway_ready( 'paypal' );
	}

	public static function mode() {
		$config = self::config();
		return 'live' === $config['mode'] ? 'live' : 'sandbox';
	}

	private static function api() {
		return 'live' === self::mode() ? self::LIVE_API : self::SANDBOX_API;
	}

	/* ---------------------------------------------------------------------- */
	/* Transport                                                               */
	/* ---------------------------------------------------------------------- */

	/**
	 * An OAuth access token, cached until shortly before PayPal expires it.
	 *
	 * The transient key carries the mode so flipping sandbox to live cannot
	 * hand a live call a sandbox token — which fails in a way that reads like
	 * bad credentials and wastes an afternoon.
	 */
	public static function token( $force = false ) {

		$config = self::config();

		if ( empty( $config['client_id'] ) || empty( $config['secret'] ) ) {
			return new WP_Error( 'jws_payment_paypal_keys', esc_html__( 'PayPal is not configured.', 'jws_streamvid' ) );
		}

		$key = self::TOKEN_TRANSIENT . '_' . self::mode();

		if ( ! $force ) {
			$cached = get_transient( $key );

			if ( $cached ) {
				return $cached;
			}
		}

		$response = wp_remote_post(
			self::api() . 'v1/oauth2/token',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $config['client_id'] . ':' . $config['secret'] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array( 'grant_type' => 'client_credentials' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $decoded['access_token'] ) ) {
			return new WP_Error( 'jws_payment_paypal_token', esc_html__( 'PayPal would not issue a token. Check the client ID and secret.', 'jws_streamvid' ) );
		}

		/* A minute short of PayPal's own expiry, so a token never dies
		   mid-request. */
		$ttl = max( 60, (int) ( $decoded['expires_in'] ?? 3600 ) - 60 );

		set_transient( $key, $decoded['access_token'], $ttl );

		return $decoded['access_token'];
	}

	/**
	 * One call to PayPal.
	 *
	 * @param bool $retrying Internal. A 401 on a cached token is retried once
	 *                       with a fresh one before it is called an error.
	 * @return array|WP_Error
	 */
	public static function request( $method, $path, $body = null, array $headers = array(), $retrying = false ) {

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
				),
				$headers
			),
		);

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( self::api() . ltrim( $path, '/' ), $args );

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

			return new WP_Error( 'jws_payment_paypal_' . ( $decoded['name'] ?? 'error' ), $message, $decoded );
		}

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * An amount as PayPal wants it: a string, with the right number of
	 * decimals. Sending "4.99" for a currency that has no minor unit is
	 * rejected outright.
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
		return Jws_Payment_Settings::gateway_slot( 'paypal', self::mode(), $fingerprint );
	}

	/**
	 * The Billing Plan behind a recurring item, built on first sale.
	 *
	 * PayPal insists a Plan belongs to a Product, so both are made and cached.
	 * An intro price becomes a TRIAL cycle of exactly one period followed by
	 * the regular one — PayPal's model happens to describe "first month
	 * cheaper" directly, where Stripe needed a coupon bolted on.
	 *
	 * @return string|WP_Error
	 */
	public static function plan_id( array $item ) {

		$refs = Jws_Payment_Settings::refs( self::slot( $item['fingerprint'] ) );

		if ( ! empty( $refs['paypal_plan'] ) ) {
			return $refs['paypal_plan'];
		}

		$currency = strtoupper( $item['currency'] );
		$product  = $refs['paypal_product'] ?? '';

		if ( ! $product ) {

			$created = self::request(
				'POST',
				'v1/catalogs/products',
				array(
					'name'        => $item['label'],
					'type'        => 'SERVICE',
					'category'    => 'ONLINE_SERVICES',
					'description' => sprintf(
						/* translators: %s: site name */
						esc_html__( 'Membership on %s', 'jws_streamvid' ),
						get_bloginfo( 'name' )
					),
				),
				array( 'PayPal-Request-Id' => 'jws-payment-product-' . $item['fingerprint'] )
			);

			if ( is_wp_error( $created ) ) {
				return $created;
			}

			$product = $created['id'];

			Jws_Payment_Settings::remember( self::slot( $item['fingerprint'] ), 'paypal_product', $product );
		}

		$frequency = array(
			'interval_unit'  => strtoupper( $item['period'] ),
			'interval_count' => (int) $item['cycle'],
		);

		$cycles   = array();
		$sequence = 1;

		if ( (float) $item['amount'] < (float) $item['renew'] ) {

			$cycles[] = array(
				'frequency'      => $frequency,
				'tenure_type'    => 'TRIAL',
				'sequence'       => $sequence,
				'total_cycles'   => 1,
				'pricing_scheme' => array(
					'fixed_price' => array(
						'value'         => self::amount( $item['amount'], $currency ),
						'currency_code' => $currency,
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
			'total_cycles'   => isset( $item['meta']['billing_limit'] ) ? (int) $item['meta']['billing_limit'] : 0,
			'pricing_scheme' => array(
				'fixed_price' => array(
					'value'         => self::amount( $item['renew'], $currency ),
					'currency_code' => $currency,
				),
			),
		);

		$created = self::request(
			'POST',
			'v1/billing/plans',
			array(
				'product_id'          => $product,
				'name'                => $item['label'],
				'billing_cycles'      => $cycles,
				'payment_preferences' => array(
					'auto_bill_outstanding'     => true,
					'setup_fee_failure_action'  => 'CANCEL',
					'payment_failure_threshold' => 2,
				),
			),
			array( 'PayPal-Request-Id' => 'jws-payment-plan-' . $item['fingerprint'] )
		);

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		Jws_Payment_Settings::remember( self::slot( $item['fingerprint'] ), 'paypal_plan', $created['id'] );

		return $created['id'];
	}

	/* ---------------------------------------------------------------------- */
	/* Buying                                                                  */
	/* ---------------------------------------------------------------------- */

	public static function return_url( $order, $cancelled = false ) {

		$args = array(
			Jws_Payment_Stripe::RETURN_VAR => (string) $order->source_ref,
			'gw'                           => 'paypal',
		);

		if ( $cancelled ) {
			$args['cancelled'] = 1;
		}

		/* See the note on the Stripe side: app mode has to survive the trip. */
		if ( Jws_Payment_Checkout::order_is_app( $order ) ) {
			$args[ Jws_Payment_Checkout::APP_VAR ] = '1';
		}

		return add_query_arg( $args, Jws_Payment_Checkout::page_url() );
	}

	/**
	 * A one-off sale: a rental, a purchase, a coin package.
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
						'description' => substr( (string) $order->item_label, 0, 127 ),
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
							'return_url'  => self::return_url( $order ),
							'cancel_url'  => self::return_url( $order, true ),
						),
					),
				),
			),
			array( 'PayPal-Request-Id' => 'jws-payment-order-' . $order->id )
		);

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		Jws_Payment_Orders::attach_ref( $order->id, $created['id'] );

		$url = self::link( $created, array( 'payer-action', 'approve' ) );

		return $url ? $url : new WP_Error( 'jws_payment_paypal_link', esc_html__( 'PayPal did not say where to send you.', 'jws_streamvid' ) );
	}

	/**
	 * Takes the money for an approved order.
	 *
	 * Nothing has been charged until this runs, which is why the return handler
	 * calls it rather than waiting for a webhook: an approved-but-uncaptured
	 * order is a buyer who thinks they have paid and has not.
	 */
	public static function capture( $paypal_order_id ) {

		return self::request(
			'POST',
			'v2/checkout/orders/' . rawurlencode( $paypal_order_id ) . '/capture',
			new stdClass(),
			array( 'PayPal-Request-Id' => 'jws-payment-capture-' . $paypal_order_id )
		);
	}

	/**
	 * A recurring sale: a membership level PayPal will keep billing.
	 *
	 * @return string|WP_Error Where to send the payer.
	 */
	public static function create_subscription( $order, array $item, $retrying = false ) {

		$plan_id = self::plan_id( $item );

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
					'return_url'  => self::return_url( $order ),
					'cancel_url'  => self::return_url( $order, true ),
				),
			),
			array( 'PayPal-Request-Id' => 'jws-payment-sub-' . $order->id )
		);

		/* A cached plan id that no longer resolves is one forget away from
		   working again; the Stripe side has the same guard. */
		if ( is_wp_error( $created ) && ! $retrying && false !== strpos( $created->get_error_code(), 'RESOURCE_NOT_FOUND' ) ) {

			Jws_Payment_Settings::forget( self::slot( $item['fingerprint'] ) );

			return self::create_subscription( $order, $item, true );
		}

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		Jws_Payment_Orders::attach_ref( $order->id, $created['id'] );

		$url = self::link( $created, array( 'approve' ) );

		return $url ? $url : new WP_Error( 'jws_payment_paypal_link', esc_html__( 'PayPal did not say where to send you.', 'jws_streamvid' ) );
	}

	/* ---------------------------------------------------------------------- */
	/* Settling                                                                */
	/* ---------------------------------------------------------------------- */

	/**
	 * Finishes a PayPal order the payer has just approved.
	 *
	 * A subscription needs no capture — PayPal bills it itself — but it does
	 * need reading back, both to check the payer actually approved it and to
	 * learn when the first period ends.
	 *
	 * @return bool Whether the order is paid.
	 */
	public static function settle( $order ) {

		if ( Jws_Payment_Orders::STATUS_COMPLETED === $order->status ) {
			return true;
		}

		$ref = (string) $order->gateway_ref;

		if ( ! $ref ) {
			return false;
		}

		$meta = Jws_Payment_Orders::meta( $order );

		if ( ! empty( $meta['recurring'] ) ) {

			$subscription = self::request( 'GET', 'v1/billing/subscriptions/' . rawurlencode( $ref ) );

			if ( is_wp_error( $subscription ) || empty( $subscription['status'] ) ) {
				return false;
			}

			if ( ! in_array( $subscription['status'], array( 'ACTIVE', 'APPROVED' ), true ) ) {
				return false;
			}

			self::sync_subscription( $subscription, $order );

			Jws_Payment_Orders::mark_paid( $order->id );

			return true;
		}

		$captured = self::capture( $ref );

		/*
		 * "Already captured" is not a failure — a webhook may have got here
		 * first — so the order status is what decides, and mark_paid() settles
		 * the race either way. PayPal reports it as an `issue` nested in the
		 * error body rather than in the top-level name.
		 */
		if ( is_wp_error( $captured ) && ! self::error_has_issue( $captured, 'ORDER_ALREADY_CAPTURED' ) ) {
			return false;
		}

		if ( ! is_wp_error( $captured ) && isset( $captured['status'] ) && 'COMPLETED' !== $captured['status'] ) {
			return false;
		}

		Jws_Payment_Orders::mark_paid( $order->id );

		return true;
	}

	/**
	 * Whether a PayPal error is the specific one named.
	 *
	 * The name at the top of an error body is usually a category
	 * ("UNPROCESSABLE_ENTITY"); what actually went wrong is the `issue` on one
	 * of its details.
	 */
	private static function error_has_issue( WP_Error $error, $issue ) {

		$data = $error->get_error_data();

		if ( ! is_array( $data ) ) {
			return false;
		}

		if ( isset( $data['name'] ) && $issue === $data['name'] ) {
			return true;
		}

		foreach ( (array) ( $data['details'] ?? array() ) as $detail ) {
			if ( isset( $detail['issue'] ) && $issue === $detail['issue'] ) {
				return true;
			}
		}

		return false;
	}

	/** Records what PayPal says about a subscription against one order. */
	public static function sync_subscription( $subscription, $order ) {

		if ( is_string( $subscription ) ) {
			$subscription = self::request( 'GET', 'v1/billing/subscriptions/' . rawurlencode( $subscription ) );
		}

		if ( is_wp_error( $subscription ) || empty( $subscription['id'] ) ) {
			return 0;
		}

		$map = array(
			'ACTIVE'    => Jws_Payment_Subscriptions::STATUS_ACTIVE,
			'APPROVED'  => Jws_Payment_Subscriptions::STATUS_ACTIVE,
			'SUSPENDED' => Jws_Payment_Subscriptions::STATUS_PAST_DUE,
			'CANCELLED' => Jws_Payment_Subscriptions::STATUS_CANCELED,
			'EXPIRED'   => Jws_Payment_Subscriptions::STATUS_CANCELED,
		);

		$next = $subscription['billing_info']['next_billing_time'] ?? '';

		$subscription_id = Jws_Payment_Subscriptions::upsert(
			array(
				'user_id'            => (int) $order->user_id,
				'type'               => (string) $order->type,
				'item_id'            => (int) $order->item_id,
				'label'              => (string) $order->item_label,
				'fingerprint'        => '',
				'gateway'            => 'paypal',
				'gateway_ref'        => $subscription['id'],
				'amount'             => $subscription['billing_info']['last_payment']['amount']['value'] ?? $order->amount,
				'currency'           => (string) $order->currency,
				'status'             => $map[ $subscription['status'] ] ?? Jws_Payment_Subscriptions::STATUS_ACTIVE,
				'current_period_end' => $next ? gmdate( 'Y-m-d H:i:s', strtotime( $next ) ) : null,
			)
		);

		if ( $subscription_id ) {
			Jws_Payment_Orders::attach_subscription( $order->id, $subscription_id );
		}

		return $subscription_id;
	}

	/** Stops PayPal billing a subscription. */
	public static function cancel_subscription( $subscription_id ) {

		return self::request(
			'POST',
			'v1/billing/subscriptions/' . rawurlencode( $subscription_id ) . '/cancel',
			array( 'reason' => 'Cancelled by the member.' )
		);
	}
}
