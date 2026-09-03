<?php

/**
 * Everything Stripe tells us after the shopper has left our hands.
 *
 * The webhook is the source of truth, not the redirect back: a shopper can type
 * the return URL themselves, and a shopper who closes the tab never visits it at
 * all. The return handler exists only to spare the honest majority a wait — it
 * asks Stripe whether that one session is paid and fulfils it early. Both paths
 * end in Jws_Drama_Orders::mark_paid(), which can only fire once.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/drama
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Drama_Stripe_Events {

	const NAMESPACE_REST = 'jws-drama/v1';

	/** How far out of step a webhook's timestamp may be, in seconds. */
	const TOLERANCE = 300;

	public function register() {

		add_action( 'rest_api_init', array( $this, 'register_route' ) );
		add_action( 'template_redirect', array( $this, 'handle_return' ) );
	}

	public function register_route() {

		register_rest_route(
			self::NAMESPACE_REST,
			'/webhook/stripe',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'receive' ),
				/* Stripe is not a WordPress user. The signature is the auth. */
				'permission_callback' => '__return_true',
			)
		);
	}

	/* ---------------------------------------------------------------------- */
	/* Signature                                                               */
	/* ---------------------------------------------------------------------- */

	/**
	 * Checks that a payload really came from Stripe.
	 *
	 * The header is `t=<timestamp>,v1=<hmac>,...`; the signed string is the
	 * timestamp, a dot, and the raw body. Comparison is hash_equals, not ==,
	 * because a byte-at-a-time comparison leaks how much of a forged signature
	 * was right.
	 *
	 * The timestamp check is what stops a captured webhook being replayed later
	 * to credit the same coins again.
	 */
	public static function verify( $payload, $header, $secret ) {

		if ( ! $payload || ! $header || ! $secret ) {
			return false;
		}

		$timestamp = 0;
		$signatures = array();

		foreach ( explode( ',', $header ) as $part ) {

			$pair = explode( '=', trim( $part ), 2 );

			if ( 2 !== count( $pair ) ) {
				continue;
			}

			if ( 't' === $pair[0] ) {
				$timestamp = (int) $pair[1];
			} elseif ( 'v1' === $pair[0] ) {
				$signatures[] = $pair[1];
			}
		}

		if ( ! $timestamp || ! $signatures ) {
			return false;
		}

		if ( abs( time() - $timestamp ) > self::TOLERANCE ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );

		foreach ( $signatures as $signature ) {
			if ( hash_equals( $expected, $signature ) ) {
				return true;
			}
		}

		return false;
	}

	/* ---------------------------------------------------------------------- */
	/* Receiving                                                               */
	/* ---------------------------------------------------------------------- */

	public function receive( WP_REST_Request $request ) {

		$config = Jws_Drama_Stripe::config();

		if ( empty( $config['webhook_secret'] ) ) {
			/* 500, not 400: this is our misconfiguration, and Stripe should keep
			   retrying until an admin fixes it rather than give up. */
			return new WP_REST_Response( array( 'error' => 'no_webhook_secret' ), 500 );
		}

		$payload = $request->get_body();

		if ( ! self::verify( $payload, $request->get_header( 'stripe_signature' ), $config['webhook_secret'] ) ) {
			return new WP_REST_Response( array( 'error' => 'bad_signature' ), 400 );
		}

		$event = json_decode( $payload, true );

		if ( ! is_array( $event ) || empty( $event['type'] ) ) {
			return new WP_REST_Response( array( 'error' => 'bad_payload' ), 400 );
		}

		self::handle( $event );

		/* Always 200 once the signature checked out. An event we do not act on
		   is not a failure, and answering anything else makes Stripe retry it
		   for days. */
		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	public static function handle( array $event ) {

		$object = isset( $event['data']['object'] ) ? $event['data']['object'] : array();

		switch ( $event['type'] ) {

			case 'checkout.session.completed':
				self::session_completed( $object );
				break;

			case 'payment_intent.succeeded':
				self::intent_succeeded( $object );
				break;

			case 'payment_intent.payment_failed':
				self::intent_failed( $object );
				break;

			case 'invoice.paid':
				self::invoice_paid( $object );
				break;

			case 'customer.subscription.updated':
			case 'customer.subscription.deleted':
				self::subscription_changed( $object );
				break;

			case 'charge.refunded':
				self::charge_refunded( $object );
				break;
		}

		/**
		 * Every verified Stripe event, for anything else that wants one.
		 *
		 * @param array $event
		 */
		do_action( 'streamvid/drama/stripe_event', $event );
	}

	/* ---------------------------------------------------------------------- */
	/* Events                                                                  */
	/* ---------------------------------------------------------------------- */

	private static function order_of( array $object ) {

		$order_id = 0;

		if ( ! empty( $object['metadata']['order_id'] ) ) {
			$order_id = (int) $object['metadata']['order_id'];
		} elseif ( ! empty( $object['client_reference_id'] ) ) {
			$order_id = (int) $object['client_reference_id'];
		}

		if ( $order_id ) {
			return Jws_Drama_Orders::find( $order_id );
		}

		return empty( $object['id'] ) ? null : Jws_Drama_Orders::find_by_ref( 'stripe', $object['id'] );
	}

	private static function session_completed( array $session ) {

		$order = self::order_of( $session );

		if ( ! $order ) {
			return;
		}

		/* A subscription session is only really done once its first invoice is
		   paid; `payment_status` covers both modes. */
		if ( isset( $session['payment_status'] ) && 'paid' !== $session['payment_status'] ) {
			return;
		}

		Jws_Drama_Orders::mark_paid( $order->id );

		if ( 'vip' === $order->kind && ! empty( $session['subscription'] ) ) {
			self::sync_subscription( $session['subscription'], (int) $order->user_id, $order->fingerprint, $order->label );
		}
	}

	private static function intent_succeeded( array $intent ) {

		$order = self::order_of( $intent );

		if ( $order ) {
			Jws_Drama_Orders::mark_paid( $order->id );
		}
	}

	private static function intent_failed( array $intent ) {

		$order = self::order_of( $intent );

		if ( $order ) {
			Jws_Drama_Orders::mark_failed( $order->id );
		}
	}

	/**
	 * A renewal, and the first charge of a wallet-bought subscription.
	 *
	 * This is the event that keeps VIP alive month after month: the sign-up is
	 * one payment, every one after it arrives only here.
	 */
	private static function invoice_paid( array $invoice ) {

		if ( empty( $invoice['subscription'] ) ) {
			return;
		}

		$user_id = 0;
		$order   = null;

		if ( ! empty( $invoice['payment_intent'] ) ) {
			$order = Jws_Drama_Orders::find_by_ref( 'stripe', $invoice['payment_intent'] );
		}

		if ( $order ) {
			Jws_Drama_Orders::mark_paid( $order->id );
			$user_id = (int) $order->user_id;
		}

		self::sync_subscription(
			$invoice['subscription'],
			$user_id,
			$order ? $order->fingerprint : '',
			$order ? $order->label : ''
		);
	}

	private static function subscription_changed( array $subscription ) {

		$status = 'canceled';

		if ( in_array( $subscription['status'], array( 'active', 'trialing' ), true ) ) {
			$status = Jws_Drama_Subscriptions::STATUS_ACTIVE;
		} elseif ( in_array( $subscription['status'], array( 'past_due', 'unpaid', 'incomplete' ), true ) ) {
			$status = Jws_Drama_Subscriptions::STATUS_PAST_DUE;
		}

		Jws_Drama_Subscriptions::set_status(
			'stripe',
			$subscription['id'],
			$status,
			self::local_time( $subscription['current_period_end'] ?? 0 )
		);
	}

	private static function charge_refunded( array $charge ) {

		if ( empty( $charge['payment_intent'] ) ) {
			return;
		}

		$order = Jws_Drama_Orders::find_by_ref( 'stripe', $charge['payment_intent'] );

		if ( $order ) {
			Jws_Drama_Orders::mark_refunded( $order->id );
		}
	}

	/**
	 * Pulls one subscription's current state and writes it down.
	 *
	 * Fetched rather than trusted from the event body, because the invoice that
	 * triggers this carries only the subscription's id — and the period end is
	 * the single field access depends on.
	 */
	private static function sync_subscription( $subscription_id, $user_id, $fingerprint, $label ) {

		$subscription = Jws_Drama_Stripe::request( 'GET', 'subscriptions/' . $subscription_id );

		if ( is_wp_error( $subscription ) ) {
			return;
		}

		$user_id     = $user_id ? $user_id : (int) ( $subscription['metadata']['user_id'] ?? 0 );
		$fingerprint = $fingerprint ? $fingerprint : (string) ( $subscription['metadata']['fingerprint'] ?? '' );

		if ( ! $user_id ) {
			return;
		}

		$status = in_array( $subscription['status'], array( 'active', 'trialing' ), true )
			? Jws_Drama_Subscriptions::STATUS_ACTIVE
			: ( in_array( $subscription['status'], array( 'past_due', 'unpaid', 'incomplete' ), true )
				? Jws_Drama_Subscriptions::STATUS_PAST_DUE
				: Jws_Drama_Subscriptions::STATUS_CANCELED );

		Jws_Drama_Subscriptions::upsert(
			array(
				'user_id'            => $user_id,
				'fingerprint'        => $fingerprint,
				'label'              => $label ? $label : esc_html__( 'VIP', 'jws_streamvid' ),
				'gateway'            => 'stripe',
				'gateway_sub_id'     => $subscription['id'],
				'status'             => $status,
				'current_period_end' => self::local_time( $subscription['current_period_end'] ?? 0 ),
			)
		);
	}

	/** Stripe speaks unix time; the table is compared against site time. */
	private static function local_time( $timestamp ) {

		$timestamp = (int) $timestamp;

		return $timestamp ? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $timestamp ) ) : null;
	}

	/* ---------------------------------------------------------------------- */
	/* Coming back from Stripe                                                 */
	/* ---------------------------------------------------------------------- */

	/**
	 * Lands the shopper back where they were, and fulfils early if it can.
	 *
	 * Shared by both gateways: PayPal needs it more than Stripe does, because a
	 * PayPal order is only approved when the payer returns and the money does
	 * not move until something captures it.
	 *
	 * The order id in the URL is only used to find where to send them; whether
	 * anything is credited comes from asking Stripe about the session, never
	 * from the URL itself.
	 */
	public function handle_return() {

		if ( empty( $_GET[ Jws_Drama_Stripe::RETURN_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$order_id = absint( $_GET[ Jws_Drama_Stripe::RETURN_VAR ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order    = Jws_Drama_Orders::find( $order_id );

		if ( ! $order || (int) $order->user_id !== get_current_user_id() ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		$cancelled = ! empty( $_GET['cancelled'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		/* Both gateways come back through this one handler; which one settles
		   the order is decided by the order, not by the query string. */
		if ( ! $cancelled && 'paypal' === $order->gateway ) {
			Jws_Drama_Paypal_Events::settle( $order );
		}

		if ( ! $cancelled && 'stripe' === $order->gateway && $order->gateway_ref && 0 === strpos( $order->gateway_ref, 'cs_' ) ) {

			$session = Jws_Drama_Stripe::request( 'GET', 'checkout/sessions/' . $order->gateway_ref );

			if ( ! is_wp_error( $session ) && isset( $session['payment_status'] ) && 'paid' === $session['payment_status'] ) {

				Jws_Drama_Orders::mark_paid( $order->id );

				if ( 'vip' === $order->kind && ! empty( $session['subscription'] ) ) {
					self::sync_subscription( $session['subscription'], (int) $order->user_id, $order->fingerprint, $order->label );
				}
			}
		}

		$destination = $order->episode_id && get_post( $order->episode_id )
			? get_permalink( $order->episode_id )
			: Jws_Streamvid_Profile::get_url( 'drama-coins' );

		wp_safe_redirect(
			add_query_arg(
				array( 'jws_drama_paid' => $cancelled ? 'cancelled' : (int) $order->id ),
				$destination
			)
		);
		exit;
	}
}
