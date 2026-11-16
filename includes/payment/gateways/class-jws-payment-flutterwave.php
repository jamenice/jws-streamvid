<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * Flutterwave, over its v3 REST API (developer.flutterwave.com).
 *
 * v3 rather than v4: only v3 has the hosted checkout (Flutterwave Standard)
 * and payment plans; v4 collects cards by sending encrypted card fields from
 * the merchant's own server, which would put the site in PCI scope.
 *
 * One-off sales are a Standard payment on Flutterwave's page. A renewing plan
 * is the same payment with `payment_plan` set, which subscribes the card;
 * the first charge may differ from the plan's amount ("dynamic amounts"), so
 * an intro price works. Flutterwave then bills the card itself.
 */
class Jws_Payment_Flutterwave extends Jws_Payment_Gateway {

	const API = 'https://api.flutterwave.com/v3/';

	/* ---------------------------------------------------------------------- */
	/* Declarations                                                            */
	/* ---------------------------------------------------------------------- */

	public static function id() {
		return 'flutterwave';
	}

	public static function label() {
		return 'Flutterwave';
	}

	public static function field_keys() {
		return array( 'secret_key', 'secret_hash' );
	}

	public static function fields() {
		return array(
			'secret_key'  => array( esc_html__( 'Secret key', 'jws_streamvid' ), 'password', 'FLWSECK_TEST-…' ),
			'secret_hash' => array( esc_html__( 'Webhook secret hash', 'jws_streamvid' ), 'password', '' ),
		);
	}

	/** Payments are confirmed on return without it; renewals and cancellations need it. */
	public static function optional_fields() {
		return array( 'secret_hash' );
	}

	public static function method_note() {
		return esc_html__( "Sends the buyer to Flutterwave's checkout: cards, bank transfer, USSD and mobile money across Africa. Renewing plans are charged to the card used for the first payment.", 'jws_streamvid' );
	}

	public static function webhook_help() {
		return esc_html__( 'Add this URL in your Flutterwave dashboard under Settings > Webhooks, type a secret hash there and paste the same value above. Flutterwave does not deliver to localhost.', 'jws_streamvid' );
	}

	public static function webhook_events() {
		return array( 'charge.completed', 'subscription.cancelled' );
	}

	/** Test keys carry _TEST; the live prefix is not documented. */
	public static function key_mode( array $config ) {
		return false !== strpos( (string) ( $config['secret_key'] ?? '' ), '_TEST' ) ? 'test' : '';
	}

	/** A renewing plan has to be one of Flutterwave's plan intervals. */
	public static function supports( array $item ) {

		$base = parent::supports( $item );

		if ( is_wp_error( $base ) || empty( $item['recurring'] ) ) {
			return $base;
		}

		if ( ! self::interval( $item['period'], $item['cycle'] ) ) {
			return new WP_Error( 'jws_payment_flutterwave_interval', esc_html__( 'Flutterwave cannot bill at this interval.', 'jws_streamvid' ) );
		}

		return true;
	}

	/* ---------------------------------------------------------------------- */
	/* Transport                                                               */
	/* ---------------------------------------------------------------------- */

	/**
	 * One call, unwrapped. Flutterwave answers { status, message, data };
	 * `status` other than "success" is a refusal even with a 200.
	 */
	protected static function request( $method, $path, $body = null ) {

		$config = static::config();

		$decoded = static::http(
			$method,
			self::API . ltrim( $path, '/' ),
			array( 'Authorization' => 'Bearer ' . (string) $config['secret_key'] ),
			$body
		);

		if ( is_wp_error( $decoded ) ) {
			return $decoded;
		}

		if ( 'success' !== ( $decoded['status'] ?? '' ) ) {
			return new WP_Error( 'jws_payment_flutterwave_refused', self::error_message( $decoded ) ?: esc_html__( 'Flutterwave refused the request.', 'jws_streamvid' ), $decoded );
		}

		return is_array( $decoded['data'] ?? null ) ? $decoded['data'] : array();
	}

	protected static function error_message( $decoded ) {
		return is_array( $decoded ) ? (string) ( $decoded['message'] ?? '' ) : '';
	}

	/**
	 * A billing period as a Flutterwave plan interval.
	 *
	 * Only the named intervals the docs list; "every N days" also exists but
	 * its other units are not spelled out, so it is not guessed at.
	 *
	 * @return string '' when there is no such interval.
	 */
	private static function interval( $period, $cycle ) {

		$map = array(
			'day:1'   => 'daily',
			'week:1'  => 'weekly',
			'month:1' => 'monthly',
			'month:3' => 'quarterly',
			'month:6' => 'bi-annually',
			'year:1'  => 'yearly',
		);

		return $map[ strtolower( (string) $period ) . ':' . max( 1, (int) $cycle ) ] ?? '';
	}

	/** Our reference: unique per order, so a retry cannot be mistaken for another sale. */
	private static function reference( $order ) {
		return 'jws-' . (int) $order->id . '-' . substr( preg_replace( '/[^A-Za-z0-9]/', '', (string) $order->source_ref ), 0, 12 );
	}

	/** An amount in major units, with the currency's own precision. */
	private static function major( $amount, $currency ) {
		return static::is_zero_decimal( $currency ) ? (int) round( (float) $amount ) : round( (float) $amount, 2 );
	}

	/* ---------------------------------------------------------------------- */
	/* Plans                                                                   */
	/* ---------------------------------------------------------------------- */

	private static function slot( $fingerprint ) {
		return Jws_Payment_Settings::gateway_slot( 'flutterwave', static::mode(), $fingerprint );
	}

	/**
	 * The payment plan behind a renewing item, made on its first sale and
	 * kept, keyed by the money so repricing a level makes a new plan.
	 *
	 * @return int|WP_Error
	 */
	private static function plan_id( $order, array $item ) {

		$refs = Jws_Payment_Settings::refs( self::slot( $item['fingerprint'] ) );

		if ( ! empty( $refs['flutterwave_plan'] ) ) {
			return (int) $refs['flutterwave_plan'];
		}

		$body = array(
			'name'     => substr( (string) $order->item_label, 0, 100 ),
			'amount'   => self::major( $item['renew'], $order->currency ),
			'interval' => self::interval( $item['period'], $item['cycle'] ),
			'currency' => strtoupper( (string) $order->currency ),
		);

		/* `duration` is the number of charges; left out, the plan runs until
		   cancelled. PMPro's billing limit counts the charges after the first,
		   and the first one is a charge on the plan too. */
		if ( ! empty( $item['meta']['billing_limit'] ) ) {
			$body['duration'] = (int) $item['meta']['billing_limit'] + 1;
		}

		$created = self::request( 'POST', 'payment-plans', $body );

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		if ( empty( $created['id'] ) ) {
			return new WP_Error( 'jws_payment_flutterwave_plan', esc_html__( 'Flutterwave did not create the plan.', 'jws_streamvid' ) );
		}

		Jws_Payment_Settings::remember( self::slot( $item['fingerprint'] ), 'flutterwave_plan', (int) $created['id'] );

		return (int) $created['id'];
	}

	/* ---------------------------------------------------------------------- */
	/* Buying                                                                  */
	/* ---------------------------------------------------------------------- */

	public static function start( $order, array $item ) {

		$user = get_userdata( (int) $order->user_id );

		if ( ! $user || ! is_email( $user->user_email ) ) {
			return new WP_Error( 'jws_payment_flutterwave_email', esc_html__( 'Flutterwave needs an email address on your account.', 'jws_streamvid' ) );
		}

		$reference = self::reference( $order );

		$body = array(
			'tx_ref'         => $reference,
			/* The first charge: for a plan this may differ from the plan's
			   own amount, which is how an intro price is charged. */
			'amount'         => self::major( $order->amount, $order->currency ),
			'currency'       => strtoupper( (string) $order->currency ),
			'redirect_url'   => static::return_url( $order ),
			'customer'       => array(
				'email' => $user->user_email,
				'name'  => $user->display_name,
			),
			'meta'           => array( 'order_id' => (int) $order->id ),
			'customizations' => array(
				'title'       => get_bloginfo( 'name' ),
				'description' => substr( (string) $order->item_label, 0, 200 ),
			),
		);

		if ( ! empty( $item['recurring'] ) ) {

			$plan = self::plan_id( $order, $item );

			if ( is_wp_error( $plan ) ) {
				return $plan;
			}

			$body['payment_plan'] = $plan;
		}

		$created = self::request( 'POST', 'payments', $body );

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		if ( empty( $created['link'] ) ) {
			return new WP_Error( 'jws_payment_flutterwave_link', esc_html__( 'Flutterwave did not say where to send you.', 'jws_streamvid' ) );
		}

		Jws_Payment_Orders::attach_ref( $order->id, $reference );

		return (string) $created['link'];
	}

	/* ---------------------------------------------------------------------- */
	/* Settling                                                                */
	/* ---------------------------------------------------------------------- */

	/**
	 * Verifies the payment by our own reference. The transaction id
	 * Flutterwave adds to the return URL is not used: the order is looked up
	 * by its token and the answer comes from the API.
	 */
	public static function settle( $order ) {

		if ( Jws_Payment_Orders::STATUS_COMPLETED === $order->status ) {
			return true;
		}

		if ( ! $order->gateway_ref ) {
			return false;
		}

		$transaction = self::request( 'GET', 'transactions/verify_by_reference?' . http_build_query( array( 'tx_ref' => (string) $order->gateway_ref ) ) );

		if ( is_wp_error( $transaction ) ) {
			return false;
		}

		return self::settle_transaction( $order, $transaction );
	}

	/**
	 * Fulfils an order from a verified transaction: successful, our own
	 * reference, the order's currency, and at least the order's amount —
	 * the checks Flutterwave's own docs ask for.
	 */
	private static function settle_transaction( $order, array $transaction ) {

		if ( 'failed' === ( $transaction['status'] ?? '' ) ) {
			Jws_Payment_Orders::mark_failed( $order->id );
			return false;
		}

		if ( 'successful' !== ( $transaction['status'] ?? '' )
			|| (string) ( $transaction['tx_ref'] ?? '' ) !== (string) $order->gateway_ref
			|| strtoupper( (string) ( $transaction['currency'] ?? '' ) ) !== strtoupper( (string) $order->currency )
			|| (float) ( $transaction['amount'] ?? 0 ) < (float) self::major( $order->amount, $order->currency ) ) {
			return false;
		}

		if ( ! static::subscription_decided( $order ) ) {

			$ran = static::once(
				'flutterwave_sub_' . (int) $order->id,
				static function () use ( $order, $transaction ) {
					$fresh = Jws_Payment_Orders::find( $order->id );

					if ( $fresh && ! static::subscription_decided( $fresh ) ) {
						self::link_subscription( $fresh, $transaction );
					}
				}
			);

			$order = $ran
				? Jws_Payment_Orders::find( $order->id )
				: static::wait_for( $order, array( static::class, 'subscription_decided' ) );
		}

		Jws_Payment_Orders::mark_paid( $order->id );

		return true;
	}

	/**
	 * Finds the subscription Flutterwave made for a plan payment and records
	 * it. Without it there is nothing to cancel, so the purchase falls back
	 * to one paid term rather than access with no end.
	 */
	private static function link_subscription( $order, array $transaction ) {

		$found = empty( $transaction['id'] )
			? null
			: self::request( 'GET', 'subscriptions?' . http_build_query( array( 'transaction_id' => (int) $transaction['id'] ) ) );

		$subscription = is_array( $found ) && isset( $found[0]['id'] ) ? $found[0] : null;

		if ( ! $subscription ) {
			static::settle_as_one_term( $order );
			return;
		}

		$meta = Jws_Payment_Orders::meta( $order );

		static::record_subscription(
			$order,
			(string) $subscription['id'],
			Jws_Payment_Subscriptions::STATUS_ACTIVE,
			static::to_gmt( strtotime( '+' . max( 1, (int) ( $meta['cycle'] ?? 1 ) ) . ' ' . ( $meta['period'] ?? 'month' ) ) )
		);
	}

	/* ---------------------------------------------------------------------- */
	/* Webhooks                                                                */
	/* ---------------------------------------------------------------------- */

	/**
	 * One Flutterwave webhook. v3 signs nothing: the `verif-hash` header
	 * carries the secret hash set in the dashboard, compared in constant time,
	 * and every payment is verified again through the API before it counts.
	 */
	public static function receive( WP_REST_Request $request ) {

		$config = static::config();
		$secret = (string) ( $config['secret_hash'] ?? '' );
		$sent   = (string) $request->get_header( 'verif_hash' );

		if ( '' === $secret || '' === $sent || ! hash_equals( $secret, $sent ) || ! static::ready() ) {
			return static::rejected();
		}

		$event = json_decode( (string) $request->get_body(), true );
		$data  = is_array( $event ) ? (array) ( $event['data'] ?? array() ) : array();

		switch ( is_array( $event ) ? (string) ( $event['event'] ?? '' ) : '' ) {

			case 'charge.completed':
				if ( ! empty( $data['id'] ) ) {
					self::on_charge( (int) $data['id'] );
				}
				break;

			case 'subscription.cancelled':
				self::on_cancelled( $data );
				break;
		}

		return static::ok();
	}

	/**
	 * A charge finished: a sign-up or one-off payment of ours, or a renewal
	 * Flutterwave made on a plan.
	 */
	private static function on_charge( $transaction_id ) {

		$transaction = self::request( 'GET', 'transactions/' . $transaction_id . '/verify' );

		if ( is_wp_error( $transaction ) ) {
			return;
		}

		$order = static::find_order( 0, (string) ( $transaction['tx_ref'] ?? '' ) );

		if ( $order ) {
			self::settle_transaction( $order, $transaction );
			return;
		}

		/* Not one of our references: a renewal, if it is a successful charge
		   on a plan and the buyer's subscription to it is on record. The
		   plan field's name on a verified transaction is not documented, so
		   both spellings are read. */
		$plan  = (int) ( $transaction['payment_plan'] ?? $transaction['plan'] ?? 0 );
		$email = (string) ( $transaction['customer']['email'] ?? '' );

		if ( 'successful' !== ( $transaction['status'] ?? '' ) || ! $plan || ! $email ) {
			return;
		}

		$record = self::subscription_record( $plan, $email );

		if ( $record ) {
			Jws_Payment_Orders::record_renewal( $record, 'fw-' . $transaction_id, (float) ( $transaction['amount'] ?? $record->amount ) );
		}
	}

	/** A subscription Flutterwave cancelled — by the buyer, or after three failed charges. */
	private static function on_cancelled( array $data ) {

		$record = self::subscription_record( (int) ( $data['plan']['id'] ?? 0 ), (string) ( $data['customer']['email'] ?? '' ) );

		if ( $record ) {
			Jws_Payment_Subscriptions::set_status( 'flutterwave', $record->gateway_ref, Jws_Payment_Subscriptions::STATUS_CANCELED );
			static::end_membership( $record );
		}
	}

	/**
	 * Our record of a buyer's subscription to a plan, found through
	 * Flutterwave's own list (the webhook names a plan and an email, not a
	 * subscription id).
	 */
	private static function subscription_record( $plan_id, $email ) {

		if ( ! $plan_id || ! is_email( $email ) ) {
			return null;
		}

		$found = self::request( 'GET', 'subscriptions?' . http_build_query( array( 'plan' => $plan_id, 'email' => $email ) ) );

		foreach ( is_array( $found ) ? $found : array() as $subscription ) {

			$record = Jws_Payment_Subscriptions::find_by_ref( 'flutterwave', (string) ( $subscription['id'] ?? '' ) );

			if ( $record ) {
				return $record;
			}
		}

		return null;
	}

	/* ---------------------------------------------------------------------- */
	/* Cancelling                                                              */
	/* ---------------------------------------------------------------------- */

	public static function cancel_subscription( $ref ) {
		return self::request( 'PUT', 'subscriptions/' . rawurlencode( (string) $ref ) . '/cancel' );
	}
}
