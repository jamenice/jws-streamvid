<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * Everything the gateways tell us after the buyer has left our hands.
 *
 * The webhook is the source of truth, not the redirect back: a buyer can type
 * the return URL themselves, and one who closes the tab never visits it at
 * all. The return handler exists only to spare the honest majority a wait — it
 * asks the gateway whether that one order is paid and fulfils it early. Both
 * paths end in Jws_Payment_Orders::mark_paid(), which can only fire once.
 *
 * Renewals have no return path at all; they only ever arrive here.
 */
class Jws_Payment_Events {

	const NAMESPACE_REST = 'jws-payment/v1';

	/** How far out of step a Stripe webhook's timestamp may be, in seconds. */
	const TOLERANCE = 300;

	public function register() {

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'template_redirect', array( $this, 'handle_return' ) );

		/*
		 * A member cancelling through PMPro has no idea a gateway is still
		 * holding a billing agreement in their name. Ending the level without
		 * ending the subscription would charge them next month for access the
		 * site has already taken away.
		 */
		add_action( 'pmpro_after_change_membership_level', array( $this, 'on_level_change' ), 10, 2 );
	}

	public function register_routes() {

		foreach ( array( 'stripe', 'paypal' ) as $gateway ) {
			register_rest_route(
				self::NAMESPACE_REST,
				'/webhook/' . $gateway,
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'receive_' . $gateway ),
					/* A gateway is not a WordPress user. The signature is the auth. */
					'permission_callback' => '__return_true',
				)
			);
		}
	}

	/* ---------------------------------------------------------------------- */
	/* Stripe                                                                  */
	/* ---------------------------------------------------------------------- */

	/**
	 * Checks that a payload really came from Stripe.
	 *
	 * The header is `t=<timestamp>,v1=<hmac>,...`; the signed string is the
	 * timestamp, a dot, and the raw body. Comparison is hash_equals, not ==,
	 * because a byte-at-a-time comparison leaks how much of a forged signature
	 * was right. The timestamp check is what stops a captured webhook being
	 * replayed later to grant the same membership again.
	 */
	public static function verify_stripe( $payload, $header, $secret ) {

		if ( ! $payload || ! $header || ! $secret ) {
			return false;
		}

		$timestamp  = 0;
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

	public function receive_stripe( WP_REST_Request $request ) {

		$config = Jws_Payment_Stripe::config();

		if ( empty( $config['webhook_secret'] ) ) {
			/* 500, not 400: this is our misconfiguration, and Stripe should
			   keep retrying until an admin fixes it rather than give up. */
			return new WP_REST_Response( array( 'error' => 'no_webhook_secret' ), 500 );
		}

		$payload = $request->get_body();

		if ( ! self::verify_stripe( $payload, $request->get_header( 'stripe_signature' ), $config['webhook_secret'] ) ) {
			return new WP_REST_Response( array( 'error' => 'bad_signature' ), 400 );
		}

		$event = json_decode( $payload, true );

		if ( ! is_array( $event ) || empty( $event['type'] ) ) {
			return new WP_REST_Response( array( 'error' => 'bad_payload' ), 400 );
		}

		$object = $event['data']['object'] ?? array();

		switch ( $event['type'] ) {

			case 'checkout.session.completed':
				$this->stripe_session_completed( $object );
				break;

			case 'payment_intent.succeeded':
				$this->stripe_intent_succeeded( $object );
				break;

			case 'invoice.paid':
				$this->stripe_invoice_paid( $object );
				break;

			case 'customer.subscription.updated':
			case 'customer.subscription.deleted':
				$this->stripe_subscription_changed( $object );
				break;

			case 'charge.refunded':
				$this->stripe_charge_refunded( $object );
				break;
		}

		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	private function stripe_session_completed( array $session ) {

		$order = $this->stripe_order_for( $session, $session['id'] ?? '' );

		if ( ! $order ) {
			return;
		}

		if ( ! empty( $session['subscription'] ) ) {
			Jws_Payment_Stripe::sync_subscription( $session['subscription'], $order );
		}

		/* Re-read: sync_subscription() may have attached a subscription id the
		   fulfilment needs to put on the PMPro invoice. */
		Jws_Payment_Orders::mark_paid( $order->id );
	}

	private function stripe_intent_succeeded( array $intent ) {

		$order = $this->stripe_order_for( $intent, $intent['id'] ?? '' );

		if ( $order ) {
			Jws_Payment_Orders::mark_paid( $order->id );
		}
	}

	/**
	 * A subscription invoice was paid.
	 *
	 * The first one is the sign-up, already handled by the session or intent
	 * event that carries the order. Every one after it is a renewal, which has
	 * no order of its own until this makes one — the member should see a
	 * receipt for each month they were charged, not just the first.
	 */
	private function stripe_invoice_paid( array $invoice ) {

		/*
		 * Stripe delivers webhooks in the API version configured on the
		 * endpoint, not the one this plugin pins for its own calls, so both
		 * shapes have to be read: `subscription` up to 2024, and
		 * `parent.subscription_details.subscription` after.
		 */
		$sub_ref = $invoice['subscription'] ?? ( $invoice['parent']['subscription_details']['subscription'] ?? '' );

		if ( ! $sub_ref || ! is_string( $sub_ref ) ) {
			return;
		}

		$subscription = Jws_Payment_Subscriptions::find_by_ref( 'stripe', $sub_ref );

		if ( ! $subscription ) {
			return;
		}

		if ( ! empty( $invoice['period_end'] ) ) {
			Jws_Payment_Subscriptions::set_status(
				'stripe',
				$sub_ref,
				Jws_Payment_Subscriptions::STATUS_ACTIVE,
				gmdate( 'Y-m-d H:i:s', (int) $invoice['period_end'] )
			);
		}

		if ( 'subscription_create' === ( $invoice['billing_reason'] ?? '' ) ) {
			return;
		}

		Jws_Payment_Orders::record_renewal(
			$subscription,
			(string) ( $invoice['id'] ?? '' ),
			Jws_Payment_Stripe::from_minor_units( $invoice['amount_paid'] ?? 0, $subscription->currency )
		);
	}

	private function stripe_subscription_changed( array $subscription ) {

		$ref = $subscription['id'] ?? '';

		if ( ! $ref ) {
			return;
		}

		$record = Jws_Payment_Subscriptions::find_by_ref( 'stripe', $ref );

		if ( ! $record ) {
			return;
		}

		$status = in_array( $subscription['status'] ?? '', array( 'active', 'trialing' ), true )
			? Jws_Payment_Subscriptions::STATUS_ACTIVE
			: ( 'past_due' === ( $subscription['status'] ?? '' ) ? Jws_Payment_Subscriptions::STATUS_PAST_DUE : Jws_Payment_Subscriptions::STATUS_CANCELED );

		$period_end = self::stripe_period_end( $subscription );

		Jws_Payment_Subscriptions::set_status(
			'stripe',
			$ref,
			$status,
			$period_end ? gmdate( 'Y-m-d H:i:s', $period_end ) : null
		);

		if ( Jws_Payment_Subscriptions::STATUS_CANCELED === $status ) {
			$this->end_membership( $record );
		}
	}

	/**
	 * When the paid period of a Stripe subscription runs out.
	 *
	 * Stripe moved this from the subscription onto its line items in a later
	 * API version, and the webhook arrives in whichever version the endpoint is
	 * set to, so both places are read.
	 *
	 * @return int Unix timestamp, or 0.
	 */
	public static function stripe_period_end( array $subscription ) {

		if ( ! empty( $subscription['current_period_end'] ) ) {
			return (int) $subscription['current_period_end'];
		}

		return (int) ( $subscription['items']['data'][0]['current_period_end'] ?? 0 );
	}

	private function stripe_charge_refunded( array $charge ) {

		$order = Jws_Payment_Orders::find_by_ref( 'stripe', $charge['payment_intent'] ?? '' );

		if ( $order ) {
			Jws_Payment_Orders::mark_refunded( $order->id );
		}
	}

	/**
	 * The order one Stripe object belongs to.
	 *
	 * The metadata is tried first because it survives the object being read
	 * back in a different shape; the gateway ref is the fallback for objects
	 * Stripe creates on our behalf, such as a subscription's first invoice.
	 */
	private function stripe_order_for( array $object, $ref ) {

		$order_id = (int) ( $object['metadata']['order_id'] ?? 0 );

		if ( $order_id ) {
			$order = Jws_Payment_Orders::find( $order_id );

			if ( $order ) {
				return $order;
			}
		}

		return Jws_Payment_Orders::find_by_ref( 'stripe', $ref );
	}

	/* ---------------------------------------------------------------------- */
	/* PayPal                                                                  */
	/* ---------------------------------------------------------------------- */

	/**
	 * Asks PayPal whether a payload really came from PayPal.
	 *
	 * Unlike Stripe there is no shared secret to HMAC against — the signature
	 * is over a certificate chain — so verification is a round trip to PayPal
	 * with the headers it sent.
	 */
	public static function verify_paypal( WP_REST_Request $request, $payload ) {

		$config = Jws_Payment_Paypal::config();

		if ( empty( $config['webhook_id'] ) ) {
			return false;
		}

		$event = json_decode( $payload, true );

		if ( ! is_array( $event ) ) {
			return false;
		}

		$result = Jws_Payment_Paypal::request(
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

		return ! is_wp_error( $result ) && 'SUCCESS' === ( $result['verification_status'] ?? '' );
	}

	public function receive_paypal( WP_REST_Request $request ) {

		$payload = $request->get_body();

		if ( ! self::verify_paypal( $request, $payload ) ) {
			return new WP_REST_Response( array( 'error' => 'bad_signature' ), 400 );
		}

		$event = json_decode( $payload, true );

		if ( ! is_array( $event ) || empty( $event['event_type'] ) ) {
			return new WP_REST_Response( array( 'error' => 'bad_payload' ), 400 );
		}

		$object = $event['resource'] ?? array();

		switch ( $event['event_type'] ) {

			case 'PAYMENT.CAPTURE.COMPLETED':
				$this->paypal_capture_completed( $object );
				break;

			case 'PAYMENT.CAPTURE.REFUNDED':
			case 'PAYMENT.CAPTURE.REVERSED':
				$this->paypal_capture_refunded( $object );
				break;

			case 'BILLING.SUBSCRIPTION.ACTIVATED':
				$this->paypal_subscription_activated( $object );
				break;

			case 'BILLING.SUBSCRIPTION.CANCELLED':
			case 'BILLING.SUBSCRIPTION.EXPIRED':
			case 'BILLING.SUBSCRIPTION.SUSPENDED':
				$this->paypal_subscription_ended( $event['event_type'], $object );
				break;

			case 'PAYMENT.SALE.COMPLETED':
				$this->paypal_sale_completed( $object );
				break;
		}

		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	private function paypal_capture_completed( array $capture ) {

		$order = $this->paypal_order_for( $capture );

		if ( $order ) {
			Jws_Payment_Orders::mark_paid( $order->id );
		}
	}

	private function paypal_capture_refunded( array $capture ) {

		$order = $this->paypal_order_for( $capture );

		if ( $order ) {
			Jws_Payment_Orders::mark_refunded( $order->id );
		}
	}

	private function paypal_subscription_activated( array $subscription ) {

		$order_id = (int) ( $subscription['custom_id'] ?? 0 );
		$order    = $order_id ? Jws_Payment_Orders::find( $order_id ) : Jws_Payment_Orders::find_by_ref( 'paypal', $subscription['id'] ?? '' );

		if ( ! $order ) {
			return;
		}

		Jws_Payment_Paypal::sync_subscription( $subscription, $order );
		Jws_Payment_Orders::mark_paid( $order->id );
	}

	private function paypal_subscription_ended( $event_type, array $subscription ) {

		$ref = $subscription['id'] ?? '';

		if ( ! $ref ) {
			return;
		}

		$record = Jws_Payment_Subscriptions::find_by_ref( 'paypal', $ref );

		if ( ! $record ) {
			return;
		}

		$suspended = 'BILLING.SUBSCRIPTION.SUSPENDED' === $event_type;

		Jws_Payment_Subscriptions::set_status(
			'paypal',
			$ref,
			$suspended ? Jws_Payment_Subscriptions::STATUS_PAST_DUE : Jws_Payment_Subscriptions::STATUS_CANCELED
		);

		/* A suspended subscription is one PayPal intends to retry, so the
		   member keeps their level until it is actually cancelled. */
		if ( ! $suspended ) {
			$this->end_membership( $record );
		}
	}

	/**
	 * A recurring PayPal payment landed.
	 *
	 * `billing_agreement_id` is how a sale says which subscription it belongs
	 * to. The first one arrives alongside the activation event that already
	 * created the sign-up order, so record_renewal() keying on the sale id is
	 * what keeps it from being written twice.
	 */
	private function paypal_sale_completed( array $sale ) {

		$ref = $sale['billing_agreement_id'] ?? '';

		if ( ! $ref ) {
			return;
		}

		$subscription = Jws_Payment_Subscriptions::find_by_ref( 'paypal', $ref );

		if ( ! $subscription ) {
			return;
		}

		/* PayPal puts the next billing date on the subscription, not on the
		   sale, so the period end has to be read back rather than derived. */
		$live = Jws_Payment_Paypal::request( 'GET', 'v1/billing/subscriptions/' . rawurlencode( $ref ) );
		$next = is_wp_error( $live ) ? '' : ( $live['billing_info']['next_billing_time'] ?? '' );

		Jws_Payment_Subscriptions::set_status(
			'paypal',
			$ref,
			Jws_Payment_Subscriptions::STATUS_ACTIVE,
			$next ? gmdate( 'Y-m-d H:i:s', strtotime( $next ) ) : null
		);

		Jws_Payment_Orders::record_renewal(
			$subscription,
			(string) ( $sale['id'] ?? '' ),
			(float) ( $sale['amount']['total'] ?? $subscription->amount )
		);
	}

	/** The order one PayPal capture belongs to. */
	private function paypal_order_for( array $capture ) {

		$order_id = (int) ( $capture['custom_id'] ?? 0 );

		if ( $order_id ) {
			$order = Jws_Payment_Orders::find( $order_id );

			if ( $order ) {
				return $order;
			}
		}

		/* A capture's own id is not what the order was tied to — that was the
		   PayPal order id, which the capture links back to. */
		$links = (array) ( $capture['links'] ?? array() );

		foreach ( $links as $link ) {
			if ( isset( $link['rel'] ) && 'up' === $link['rel'] && preg_match( '#/checkout/orders/([^/?]+)#', $link['href'], $match ) ) {
				return Jws_Payment_Orders::find_by_ref( 'paypal', $match[1] );
			}
		}

		return null;
	}

	/* ---------------------------------------------------------------------- */
	/* Membership lifecycle                                                    */
	/* ---------------------------------------------------------------------- */

	/** Ends the PMPro level a dead subscription was paying for. */
	private function end_membership( $subscription ) {

		if ( 'membership' !== $subscription->type || ! function_exists( 'pmpro_cancelMembershipLevel' ) ) {
			return;
		}

		if ( ! function_exists( 'pmpro_hasMembershipLevel' ) || ! pmpro_hasMembershipLevel( (int) $subscription->item_id, (int) $subscription->user_id ) ) {
			return;
		}

		pmpro_cancelMembershipLevel( (int) $subscription->item_id, (int) $subscription->user_id, 'inactive' );
	}

	/**
	 * Stops billing when a member loses a level any other way.
	 *
	 * Covers the member cancelling on PMPro's own page and an admin removing
	 * the level by hand: PMPro knows nothing about the billing agreement, so
	 * without this the card keeps being charged for access that is gone.
	 *
	 * Levels the member still holds are left alone — this fires on every
	 * change, including the one that *grants* a level.
	 *
	 * @param int $level_id The level changed to, or 0 when all were cancelled.
	 * @param int $user_id
	 */
	public function on_level_change( $level_id, $user_id ) {

		$subscriptions = Jws_Payment_Subscriptions::billing_for_user( $user_id );

		if ( ! $subscriptions ) {
			return;
		}

		foreach ( $subscriptions as $subscription ) {

			if ( 'membership' !== $subscription->type ) {
				continue;
			}

			/* The one this very payment is in the middle of granting: PMPro
			   removes the old level before adding the new one, so mid-grant it
			   looks exactly like a subscription with no level behind it. */
			if ( (int) $subscription->id === Jws_Payment_Fulfillment::$granting_subscription_id ) {
				continue;
			}

			if ( function_exists( 'pmpro_hasMembershipLevel' ) && pmpro_hasMembershipLevel( (int) $subscription->item_id, $user_id ) ) {
				continue;
			}

			Jws_Payment_Subscriptions::cancel_at_gateway( $subscription );
		}
	}

	/* ---------------------------------------------------------------------- */
	/* Coming back from a gateway                                              */
	/* ---------------------------------------------------------------------- */

	/**
	 * Lands the buyer back on the checkout, and fulfils early if it can.
	 *
	 * PayPal needs this more than Stripe does, because a PayPal order is only
	 * approved when the payer returns and the money does not move until
	 * something captures it.
	 *
	 * The token in the URL only says which order to look at; whether anything
	 * is granted comes from asking the gateway, never from the URL.
	 */
	public function handle_return() {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET[ Jws_Payment_Stripe::RETURN_VAR ] ) ) {
			return;
		}

		$token = sanitize_text_field( wp_unslash( $_GET[ Jws_Payment_Stripe::RETURN_VAR ] ) );
		$order = Jws_Payment_Checkout::find_by_token( $token );

		if ( ! $order || (int) $order->user_id !== get_current_user_id() ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		$cancelled = ! empty( $_GET['cancelled'] );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $cancelled ) {
			Jws_Payment_Orders::mark_failed( $order->id );
		} else {
			/* Which gateway settles it is decided by the order, not by the
			   query string, which the buyer controls. */
			if ( 'paypal' === $order->gateway ) {
				Jws_Payment_Paypal::settle( $order );
			} else {
				Jws_Payment_Stripe::settle( $order );
			}
		}

		wp_safe_redirect(
			Jws_Payment_Checkout::with_app(
				add_query_arg(
					array( 'jws_payment' => $cancelled ? 'cancelled' : 'done', 'order' => $token ),
					Jws_Payment_Checkout::page_url()
				),
				Jws_Payment_Checkout::order_is_app( $order )
			)
		);
		exit;
	}
}
