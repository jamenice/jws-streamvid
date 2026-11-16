<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * Paystack, over its REST API (paystack.com/docs/api).
 *
 * Every sale starts as a Transaction the buyer pays on Paystack's hosted
 * page. A renewing plan then becomes a Subscription created from the card
 * that first payment left behind, starting one billing period later — the
 * same shape as the Mollie path. That keeps the subscription id in hand the
 * moment the buyer returns, and lets the first payment be an intro price,
 * which Paystack's own plan checkout cannot do (a plan overrides the amount).
 */
class Jws_Payment_Paystack extends Jws_Payment_Gateway {

	const API = 'https://api.paystack.co/';

	/** How far a signed webhook's timestamp may be from now, in seconds. */
	const TOLERANCE = 300;

	/* ---------------------------------------------------------------------- */
	/* Declarations                                                            */
	/* ---------------------------------------------------------------------- */

	public static function id() {
		return 'paystack';
	}

	public static function label() {
		return 'Paystack';
	}

	public static function field_keys() {
		return array( 'secret_key', 'webhook_secret' );
	}

	public static function fields() {
		return array(
			'secret_key'     => array( esc_html__( 'Secret key', 'jws_streamvid' ), 'password', 'sk_test_…' ),
			'webhook_secret' => array( esc_html__( 'Webhook signing secret', 'jws_streamvid' ), 'password', 'whsec_…' ),
		);
	}

	/** Without it the older signature, made with the secret key, is checked instead. */
	public static function optional_fields() {
		return array( 'webhook_secret' );
	}

	public static function method_note() {
		return esc_html__( 'Sends the buyer to Paystack: cards, bank transfer, USSD and mobile money in Nigeria, Ghana, South Africa, Kenya and Côte d\'Ivoire. Renewing plans are charged to the card used for the first payment.', 'jws_streamvid' );
	}

	public static function webhook_help() {
		return esc_html__( 'Add this URL in your Paystack dashboard as the webhook URL, and paste the webhook signing secret from Developers > API configuration above. Paystack does not deliver to localhost.', 'jws_streamvid' );
	}

	public static function webhook_events() {
		return array( 'charge.success', 'invoice.update', 'invoice.payment_failed', 'subscription.not_renew', 'subscription.disable', 'refund.processed' );
	}

	/** Paystack's currency table (paystack.com/docs/api). */
	public static function currencies() {
		return array( 'NGN', 'USD', 'GHS', 'ZAR', 'KES', 'XOF' );
	}

	public static function key_mode( array $config ) {

		$key = (string) ( $config['secret_key'] ?? '' );

		if ( 0 === strpos( $key, 'sk_live_' ) ) {
			return 'live';
		}

		return 0 === strpos( $key, 'sk_test_' ) ? 'test' : '';
	}

	/**
	 * A renewing plan has to match one of Paystack's fixed intervals, and
	 * cannot stop after a set number of payments here.
	 */
	public static function supports( array $item ) {

		$base = parent::supports( $item );

		if ( is_wp_error( $base ) || empty( $item['recurring'] ) ) {
			return $base;
		}

		if ( ! self::interval( $item['period'], $item['cycle'] ) ) {
			return new WP_Error( 'jws_payment_paystack_interval', esc_html__( 'Paystack cannot bill at this interval.', 'jws_streamvid' ) );
		}

		if ( ! empty( $item['meta']['billing_limit'] ) ) {
			return new WP_Error( 'jws_payment_paystack_limit', esc_html__( 'Paystack plans here cannot stop after a set number of payments.', 'jws_streamvid' ) );
		}

		return true;
	}

	/* ---------------------------------------------------------------------- */
	/* Transport                                                               */
	/* ---------------------------------------------------------------------- */

	/**
	 * One call to Paystack, unwrapped.
	 *
	 * Paystack answers { status, message, data }. `status` false with a 200
	 * is still a refusal, so it is turned into an error here and callers
	 * only ever see `data`.
	 *
	 * @return array|WP_Error
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

		if ( empty( $decoded['status'] ) ) {
			return new WP_Error( 'jws_payment_paystack_refused', self::error_message( $decoded ) ?: esc_html__( 'Paystack refused the request.', 'jws_streamvid' ), $decoded );
		}

		return is_array( $decoded['data'] ?? null ) ? $decoded['data'] : array();
	}

	protected static function error_message( $decoded ) {
		return is_array( $decoded ) ? (string) ( $decoded['message'] ?? '' ) : '';
	}

	/**
	 * An amount in subunits. Paystack multiplies every currency by 100 —
	 * XOF too, although it has no subunit; the fraction is ignored.
	 */
	private static function subunits( $amount ) {
		return (int) round( (float) $amount * 100 );
	}

	/**
	 * A billing period as one of Paystack's fixed plan intervals.
	 *
	 * @return string '' when Paystack has no such interval.
	 */
	private static function interval( $period, $cycle ) {

		$map = array(
			'day:1'   => 'daily',
			'week:1'  => 'weekly',
			'month:1' => 'monthly',
			'month:3' => 'quarterly',
			'month:6' => 'biannually',
			'year:1'  => 'annually',
		);

		$key = strtolower( (string) $period ) . ':' . max( 1, (int) $cycle );

		return $map[ $key ] ?? '';
	}

	/** Our reference for an order: letters, digits and `-`, unique per order. */
	private static function reference( $order ) {
		return 'jws-' . (int) $order->id . '-' . substr( preg_replace( '/[^A-Za-z0-9]/', '', (string) $order->source_ref ), 0, 12 );
	}

	/* ---------------------------------------------------------------------- */
	/* Plans                                                                   */
	/* ---------------------------------------------------------------------- */

	private static function slot( $fingerprint ) {
		return Jws_Payment_Settings::gateway_slot( 'paystack', static::mode(), $fingerprint );
	}

	/**
	 * The plan behind a renewing item, made on its first sale and kept.
	 *
	 * Keyed by the money (the item's fingerprint), so repricing a level makes
	 * a new plan and nobody already subscribed is repriced with it.
	 *
	 * @return string|WP_Error The plan code.
	 */
	private static function plan_code( $order, array $meta ) {

		$fingerprint = (string) ( $meta['fingerprint'] ?? '' );
		$refs        = Jws_Payment_Settings::refs( self::slot( $fingerprint ) );

		if ( ! empty( $refs['paystack_plan'] ) ) {
			return $refs['paystack_plan'];
		}

		$created = self::request(
			'POST',
			'plan',
			array(
				'name'     => substr( (string) $order->item_label, 0, 100 ),
				'amount'   => self::subunits( $meta['renew'] ?? $order->amount ),
				'interval' => self::interval( $meta['period'] ?? 'month', $meta['cycle'] ?? 1 ),
				'currency' => strtoupper( (string) $order->currency ),
			)
		);

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		if ( empty( $created['plan_code'] ) ) {
			return new WP_Error( 'jws_payment_paystack_plan', esc_html__( 'Paystack did not create the plan.', 'jws_streamvid' ) );
		}

		Jws_Payment_Settings::remember( self::slot( $fingerprint ), 'paystack_plan', (string) $created['plan_code'] );

		return (string) $created['plan_code'];
	}

	/* ---------------------------------------------------------------------- */
	/* Buying                                                                  */
	/* ---------------------------------------------------------------------- */

	public static function start( $order, array $item ) {

		$user = get_userdata( (int) $order->user_id );

		if ( ! $user || ! is_email( $user->user_email ) ) {
			return new WP_Error( 'jws_payment_paystack_email', esc_html__( 'Paystack needs an email address on your account.', 'jws_streamvid' ) );
		}

		$reference = self::reference( $order );

		$body = array(
			'email'        => $user->user_email,
			/* Documented as a string of subunits. */
			'amount'       => (string) self::subunits( $order->amount ),
			'currency'     => strtoupper( (string) $order->currency ),
			'reference'    => $reference,
			'callback_url' => static::return_url( $order ),
			/* Documented as stringified JSON. cancel_action is where Paystack
			   sends a buyer who cancels on its page. */
			'metadata'     => wp_json_encode(
				array(
					'order_id'      => (int) $order->id,
					'cancel_action' => static::return_url( $order, true ),
				)
			),
		);

		/* A subscription is charged to a saved card, so the first payment has
		   to be made with one. */
		if ( ! empty( $item['recurring'] ) ) {
			$body['channels'] = array( 'card' );
		}

		$created = self::request( 'POST', 'transaction/initialize', $body );

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		if ( empty( $created['authorization_url'] ) ) {
			return new WP_Error( 'jws_payment_paystack_link', esc_html__( 'Paystack did not say where to send you.', 'jws_streamvid' ) );
		}

		Jws_Payment_Orders::attach_ref( $order->id, $reference );

		return (string) $created['authorization_url'];
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

		$transaction = self::request( 'GET', 'transaction/verify/' . rawurlencode( (string) $order->gateway_ref ) );

		if ( is_wp_error( $transaction ) ) {
			return false;
		}

		$status = (string) ( $transaction['status'] ?? '' );

		if ( in_array( $status, array( 'failed', 'reversed' ), true ) ) {
			Jws_Payment_Orders::mark_failed( $order->id );
			return false;
		}

		/* "success" alone is not enough: the amount and currency must be what
		   this order asked for. */
		if ( 'success' !== $status
			|| (int) ( $transaction['amount'] ?? 0 ) !== self::subunits( $order->amount )
			|| strtoupper( (string) ( $transaction['currency'] ?? '' ) ) !== strtoupper( (string) $order->currency ) ) {
			return false;
		}

		if ( ! static::subscription_decided( $order ) ) {

			$ran = static::once(
				'paystack_sub_' . (int) $order->id,
				static function () use ( $order, $transaction ) {
					$fresh = Jws_Payment_Orders::find( $order->id );

					if ( $fresh && ! static::subscription_decided( $fresh ) ) {
						self::start_subscription( $fresh, $transaction );
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
	 * Subscribes the buyer's card to the plan, starting one period from now.
	 * Anything missing or refused leaves the purchase as one paid term.
	 */
	private static function start_subscription( $order, array $transaction ) {

		$meta          = Jws_Payment_Orders::meta( $order );
		$customer      = (string) ( $transaction['customer']['customer_code'] ?? '' );
		$authorization = (string) ( $transaction['authorization']['authorization_code'] ?? '' );
		$reusable      = ! empty( $transaction['authorization']['reusable'] );

		if ( ! $customer || ! $authorization || ! $reusable ) {
			static::settle_as_one_term( $order );
			return;
		}

		$plan = self::plan_code( $order, $meta );

		if ( is_wp_error( $plan ) ) {
			static::settle_as_one_term( $order );
			return;
		}

		$start = strtotime( '+' . max( 1, (int) ( $meta['cycle'] ?? 1 ) ) . ' ' . ( $meta['period'] ?? 'month' ) );

		$created = self::request(
			'POST',
			'subscription',
			array(
				'customer'      => $customer,
				'plan'          => $plan,
				'authorization' => $authorization,
				'start_date'    => gmdate( 'c', $start ),
			)
		);

		/* A cached plan the account no longer has: make it again, once. */
		if ( is_wp_error( $created ) && false !== stripos( $created->get_error_message(), 'plan' ) ) {

			Jws_Payment_Settings::forget( self::slot( (string) ( $meta['fingerprint'] ?? '' ) ) );

			$plan = self::plan_code( $order, $meta );

			$created = is_wp_error( $plan ) ? $plan : self::request(
				'POST',
				'subscription',
				array(
					'customer'      => $customer,
					'plan'          => $plan,
					'authorization' => $authorization,
					'start_date'    => gmdate( 'c', $start ),
				)
			);
		}

		if ( is_wp_error( $created ) || empty( $created['subscription_code'] ) ) {
			static::settle_as_one_term( $order );
			return;
		}

		static::record_subscription(
			$order,
			(string) $created['subscription_code'],
			Jws_Payment_Subscriptions::STATUS_ACTIVE,
			static::to_gmt( $created['next_payment_date'] ?? $start )
		);
	}

	/* ---------------------------------------------------------------------- */
	/* Webhooks                                                                */
	/* ---------------------------------------------------------------------- */

	/**
	 * One Paystack webhook, checked before it is decoded.
	 *
	 * Paystack signs two ways. The current one is `Paystack-Signature:
	 * t=…,v1=whk_…:<hex>`, an HMAC-SHA512 of "t.body" under the webhook
	 * signing secret, with a timestamp that must be recent. The older one is
	 * `x-paystack-signature`, an HMAC-SHA512 of the body under the secret
	 * key. Either passing is enough; neither, and nothing is done.
	 */
	public static function receive( WP_REST_Request $request ) {

		$body = (string) $request->get_body();

		if ( ! static::ready() || ! self::signature_ok( $request, $body ) ) {
			return static::rejected();
		}

		$event = json_decode( $body, true );

		if ( ! is_array( $event ) || empty( $event['event'] ) ) {
			return static::rejected( 'bad_payload' );
		}

		$data = (array) ( $event['data'] ?? array() );

		switch ( $event['event'] ) {

			case 'charge.success':
				/* The first payment. Renewals are taken from invoice.update,
				   which names the subscription they belong to. */
				$order = static::find_order( 0, (string) ( $data['reference'] ?? '' ) );

				if ( $order ) {
					static::settle( $order );
				}
				break;

			case 'invoice.update':
				self::on_invoice( $data );
				break;

			case 'invoice.payment_failed':
				$code = (string) ( $data['subscription']['subscription_code'] ?? '' );

				/* Paystack does not retry a failed renewal; the member keeps
				   the level until the subscription is actually disabled. */
				if ( $code ) {
					Jws_Payment_Subscriptions::set_status( 'paystack', $code, Jws_Payment_Subscriptions::STATUS_PAST_DUE );
				}
				break;

			case 'subscription.not_renew':
				/* Cancelled, but paid up to the next payment date, when
				   subscription.disable follows and the level ends. */
				if ( ! empty( $data['subscription_code'] ) ) {
					Jws_Payment_Subscriptions::set_status( 'paystack', (string) $data['subscription_code'], Jws_Payment_Subscriptions::STATUS_CANCELED );
				}
				break;

			case 'subscription.disable':
				$record = Jws_Payment_Subscriptions::find_by_ref( 'paystack', (string) ( $data['subscription_code'] ?? '' ) );

				if ( $record ) {
					Jws_Payment_Subscriptions::set_status( 'paystack', $record->gateway_ref, Jws_Payment_Subscriptions::STATUS_CANCELED );
					static::end_membership( $record );
				}
				break;

			case 'refund.processed':
				$order = static::find_order( 0, (string) ( $data['transaction_reference'] ?? '' ) );

				/* Only a full refund takes back what was granted. */
				if ( $order && (int) ( $data['amount'] ?? 0 ) >= self::subunits( $order->amount ) ) {
					Jws_Payment_Orders::mark_refunded( $order->id );
				}
				break;
		}

		return static::ok();
	}

	private static function signature_ok( WP_REST_Request $request, $body ) {

		$config = static::config();

		$current = (string) $request->get_header( 'paystack_signature' );
		$secret  = (string) ( $config['webhook_secret'] ?? '' );

		if ( '' !== $current && '' !== $secret ) {

			$timestamp  = '';
			$signatures = array();

			foreach ( explode( ',', $current ) as $pair ) {
				$kv = array_map( 'trim', explode( '=', $pair, 2 ) );

				if ( 2 !== count( $kv ) ) {
					continue;
				}

				if ( 't' === $kv[0] ) {
					$timestamp = $kv[1];
				} elseif ( 'v1' === $kv[0] ) {
					/* "whk_<id>:<hex>" — the hex is after the colon. */
					$signatures[] = false !== strpos( $kv[1], ':' ) ? substr( $kv[1], strrpos( $kv[1], ':' ) + 1 ) : $kv[1];
				}
			}

			if ( ctype_digit( $timestamp ) && abs( time() - (int) $timestamp ) <= self::TOLERANCE ) {

				$expected = hash_hmac( 'sha512', $timestamp . '.' . $body, $secret );

				foreach ( $signatures as $signature ) {
					if ( hash_equals( $expected, strtolower( $signature ) ) ) {
						return true;
					}
				}
			}
		}

		$legacy = (string) $request->get_header( 'x_paystack_signature' );

		return '' !== $legacy
			&& hash_equals( hash_hmac( 'sha512', $body, (string) $config['secret_key'] ), strtolower( $legacy ) );
	}

	/** A subscription invoice reached its final status. */
	private static function on_invoice( array $invoice ) {

		$code   = (string) ( $invoice['subscription']['subscription_code'] ?? '' );
		$record = $code ? Jws_Payment_Subscriptions::find_by_ref( 'paystack', $code ) : null;

		$paid = ! empty( $invoice['paid'] ) || 'success' === ( $invoice['status'] ?? '' );

		if ( ! $record || ! $paid ) {
			return;
		}

		$reference = (string) ( $invoice['transaction']['reference'] ?? '' );

		if ( '' === $reference ) {
			return;
		}

		$live = self::request( 'GET', 'subscription/' . rawurlencode( $code ) );

		Jws_Payment_Subscriptions::set_status(
			'paystack',
			$code,
			Jws_Payment_Subscriptions::STATUS_ACTIVE,
			is_wp_error( $live ) ? null : static::to_gmt( $live['next_payment_date'] ?? '' )
		);

		Jws_Payment_Orders::record_renewal(
			$record,
			'ps-' . $reference,
			isset( $invoice['amount'] ) ? (float) $invoice['amount'] / 100 : (float) $record->amount
		);
	}

	/* ---------------------------------------------------------------------- */
	/* Cancelling                                                              */
	/* ---------------------------------------------------------------------- */

	/** Disabling needs the subscription's email token, read back first. */
	public static function cancel_subscription( $ref ) {

		$subscription = self::request( 'GET', 'subscription/' . rawurlencode( (string) $ref ) );

		if ( is_wp_error( $subscription ) ) {
			return $subscription;
		}

		if ( empty( $subscription['email_token'] ) ) {
			return new WP_Error( 'jws_payment_paystack_token', esc_html__( 'Paystack did not return the token needed to cancel this subscription.', 'jws_streamvid' ) );
		}

		return self::request(
			'POST',
			'subscription/disable',
			array(
				'code'  => (string) $ref,
				'token' => (string) $subscription['email_token'],
			)
		);
	}
}
