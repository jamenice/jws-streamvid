<?php

/**
 * Everything PayPal tells us after the payer has left our hands.
 *
 * Unlike Stripe, PayPal signs a webhook by asking PayPal: there is no local
 * HMAC, so verification is itself an API call against the webhook id from the
 * settings. That call is not optional — without it anyone who knows the URL can
 * post an event that credits coins.
 *
 * Fulfilment goes through Jws_Drama_Orders::mark_paid(), the same one-shot claim
 * Stripe uses, so a retried event cannot pay twice.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/drama
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Drama_Paypal_Events {

	public function register() {

		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	public function register_route() {

		register_rest_route(
			Jws_Drama_Stripe_Events::NAMESPACE_REST,
			'/webhook/paypal',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'receive' ),
				/* PayPal is not a WordPress user. The verification call is the auth. */
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Asks PayPal whether it really sent this.
	 *
	 * Every field below comes from the request's own headers, which is why the
	 * webhook id from the settings has to be part of it — it is the only piece
	 * a forger does not control.
	 */
	public static function verify( WP_REST_Request $request, $payload ) {

		$config = Jws_Drama_Paypal::config();

		if ( empty( $config['webhook_id'] ) ) {
			return false;
		}

		$event = json_decode( $payload, true );

		if ( ! is_array( $event ) ) {
			return false;
		}

		$result = Jws_Drama_Paypal::request(
			'POST',
			'v1/notifications/verify-webhook-signature',
			array(
				'auth_algo'         => $request->get_header( 'paypal_auth_algo' ),
				'cert_url'          => $request->get_header( 'paypal_cert_url' ),
				'transmission_id'   => $request->get_header( 'paypal_transmission_id' ),
				'transmission_sig'  => $request->get_header( 'paypal_transmission_sig' ),
				'transmission_time' => $request->get_header( 'paypal_transmission_time' ),
				'webhook_id'        => $config['webhook_id'],
				'webhook_event'     => $event,
			)
		);

		if ( is_wp_error( $result ) ) {
			return false;
		}

		return isset( $result['verification_status'] ) && 'SUCCESS' === $result['verification_status'];
	}

	public function receive( WP_REST_Request $request ) {

		$config = Jws_Drama_Paypal::config();

		if ( empty( $config['webhook_id'] ) ) {
			/* Ours to fix, so ask PayPal to keep trying rather than give up. */
			return new WP_REST_Response( array( 'error' => 'no_webhook_id' ), 500 );
		}

		$payload = $request->get_body();

		if ( ! self::verify( $request, $payload ) ) {
			return new WP_REST_Response( array( 'error' => 'bad_signature' ), 400 );
		}

		$event = json_decode( $payload, true );

		if ( empty( $event['event_type'] ) ) {
			return new WP_REST_Response( array( 'error' => 'bad_payload' ), 400 );
		}

		self::handle( $event );

		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	public static function handle( array $event ) {

		$object = isset( $event['resource'] ) ? $event['resource'] : array();

		switch ( $event['event_type'] ) {

			case 'CHECKOUT.ORDER.APPROVED':
				self::order_approved( $object );
				break;

			case 'PAYMENT.CAPTURE.COMPLETED':
				self::capture_completed( $object );
				break;

			case 'PAYMENT.CAPTURE.DENIED':
				self::capture_denied( $object );
				break;

			case 'PAYMENT.CAPTURE.REFUNDED':
			case 'PAYMENT.CAPTURE.REVERSED':
				self::capture_refunded( $object );
				break;

			case 'BILLING.SUBSCRIPTION.ACTIVATED':
			case 'BILLING.SUBSCRIPTION.UPDATED':
				self::subscription_state( $object, Jws_Drama_Subscriptions::STATUS_ACTIVE );
				break;

			case 'BILLING.SUBSCRIPTION.SUSPENDED':
			case 'BILLING.SUBSCRIPTION.PAYMENT.FAILED':
				self::subscription_state( $object, Jws_Drama_Subscriptions::STATUS_PAST_DUE );
				break;

			case 'BILLING.SUBSCRIPTION.CANCELLED':
			case 'BILLING.SUBSCRIPTION.EXPIRED':
				self::subscription_state( $object, Jws_Drama_Subscriptions::STATUS_CANCELED );
				break;

			case 'PAYMENT.SALE.COMPLETED':
				self::renewal( $object );
				break;
		}

		/**
		 * Every verified PayPal event, for anything else that wants one.
		 *
		 * @param array $event
		 */
		do_action( 'streamvid/drama/paypal_event', $event );
	}

	/* ---------------------------------------------------------------------- */
	/* Events                                                                  */
	/* ---------------------------------------------------------------------- */

	private static function order_of( array $object ) {

		$order_id = 0;

		if ( ! empty( $object['custom_id'] ) ) {
			$order_id = (int) $object['custom_id'];
		} elseif ( ! empty( $object['purchase_units'][0]['custom_id'] ) ) {
			$order_id = (int) $object['purchase_units'][0]['custom_id'];
		}

		if ( $order_id ) {
			return Jws_Drama_Orders::find( $order_id );
		}

		return empty( $object['id'] ) ? null : Jws_Drama_Orders::find_by_ref( 'paypal', $object['id'] );
	}

	/**
	 * Approved is not paid.
	 *
	 * PayPal holds the money until someone captures it, so this is where that
	 * happens for a shopper who never came back to the site. The capture then
	 * arrives as its own event, which is what actually credits.
	 */
	private static function order_approved( array $object ) {

		$order = self::order_of( $object );

		if ( ! $order || Jws_Drama_Orders::STATUS_PENDING !== $order->status || empty( $object['id'] ) ) {
			return;
		}

		Jws_Drama_Paypal::capture( $object['id'] );
	}

	private static function capture_completed( array $capture ) {

		$order = self::order_of( $capture );

		if ( $order ) {
			Jws_Drama_Orders::mark_paid( $order->id );
		}
	}

	private static function capture_denied( array $capture ) {

		$order = self::order_of( $capture );

		if ( $order ) {
			Jws_Drama_Orders::mark_failed( $order->id );
		}
	}

	private static function capture_refunded( array $capture ) {

		$order = self::order_of( $capture );

		if ( $order ) {
			Jws_Drama_Orders::mark_refunded( $order->id );
		}
	}

	/**
	 * A subscription changing hands or lapsing.
	 *
	 * `billing_info.next_billing_time` is PayPal's answer to Stripe's
	 * current_period_end: it is how far the member has already paid to.
	 */
	private static function subscription_state( array $subscription, $status ) {

		if ( empty( $subscription['id'] ) ) {
			return;
		}

		$order = self::order_of( $subscription );
		$until = self::local_time( $subscription['billing_info']['next_billing_time'] ?? '' );

		$existing = Jws_Drama_Subscriptions::set_status( 'paypal', $subscription['id'], $status, $until );

		if ( $existing || ! $order ) {
			return;
		}

		/* First we have heard of it: the sign-up. */
		Jws_Drama_Subscriptions::upsert(
			array(
				'user_id'            => (int) $order->user_id,
				'fingerprint'        => $order->fingerprint,
				'label'              => $order->label,
				'gateway'            => 'paypal',
				'gateway_sub_id'     => $subscription['id'],
				'status'             => $status,
				'current_period_end' => $until,
			)
		);

		Jws_Drama_Orders::mark_paid( $order->id );
	}

	/**
	 * A renewal payment.
	 *
	 * Carries the subscription id but not its dates, so the subscription is
	 * re-read to find how far the member is now paid up to.
	 */
	private static function renewal( array $sale ) {

		if ( empty( $sale['billing_agreement_id'] ) ) {
			return;
		}

		$subscription = Jws_Drama_Paypal::request( 'GET', 'v1/billing/subscriptions/' . rawurlencode( $sale['billing_agreement_id'] ) );

		if ( is_wp_error( $subscription ) ) {
			return;
		}

		self::subscription_state( $subscription, Jws_Drama_Subscriptions::STATUS_ACTIVE );
	}

	/** PayPal speaks ISO 8601 in UTC; the table is compared against site time. */
	private static function local_time( $iso ) {

		if ( ! $iso ) {
			return null;
		}

		$timestamp = strtotime( $iso );

		return $timestamp ? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $timestamp ) ) : null;
	}

	/* ---------------------------------------------------------------------- */
	/* Coming back from PayPal                                                 */
	/* ---------------------------------------------------------------------- */

	/**
	 * Captures the money the payer just approved.
	 *
	 * Called from the shared return handler. Whether anything is credited comes
	 * from what PayPal says about the capture, never from the URL.
	 */
	public static function settle( $order ) {

		if ( ! $order->gateway_ref ) {
			return;
		}

		if ( 'vip' === $order->kind ) {

			$subscription = Jws_Drama_Paypal::request( 'GET', 'v1/billing/subscriptions/' . rawurlencode( $order->gateway_ref ) );

			if ( ! is_wp_error( $subscription ) && in_array( $subscription['status'] ?? '', array( 'ACTIVE', 'APPROVED' ), true ) ) {
				self::subscription_state( $subscription, Jws_Drama_Subscriptions::STATUS_ACTIVE );
			}

			return;
		}

		$captured = Jws_Drama_Paypal::capture( $order->gateway_ref );

		if ( is_wp_error( $captured ) ) {
			return;
		}

		if ( isset( $captured['status'] ) && 'COMPLETED' === $captured['status'] ) {
			Jws_Drama_Orders::mark_paid( $order->id );
		}
	}
}
