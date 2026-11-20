<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * Mollie, over its v2 REST API (docs.mollie.com).
 *
 * One-off sales are a Payment the buyer completes on Mollie's hosted page.
 * A renewing plan is a "first" Payment on that same page, which also gives
 * Mollie a mandate to charge the buyer again, followed by a Subscription that
 * starts one billing period later. The first payment is the plan's price for
 * the first period, so an intro price needs nothing special.
 *
 * Mollie's classic webhook posts only an id and carries no signature. That is
 * by design: nothing is read from the request except the id, and the payment
 * is fetched back from Mollie with the site's own key before anything changes.
 */
class Jws_Payment_Mollie extends Jws_Payment_Gateway {

	const API = 'https://api.mollie.com/v2/';

	/** Per-user Mollie customer id, keyed by a hash of the API key. */
	const CUSTOMER_META = '_jws_payment_mollie_customer';

	/* ---------------------------------------------------------------------- */
	/* Declarations                                                            */
	/* ---------------------------------------------------------------------- */

	public static function id() {
		return 'mollie';
	}

	public static function label() {
		return 'Mollie';
	}

	public static function field_keys() {
		return array( 'api_key' );
	}

	public static function fields() {
		return array(
			'api_key' => array( esc_html__( 'API key', 'jws_streamvid' ), 'password', 'test_…' ),
		);
	}

	public static function method_note() {
		return esc_html__( "Sends the buyer to Mollie's checkout: cards, iDEAL, Bancontact, SEPA and the other methods switched on in your Mollie account. Renewing plans charge cards, SEPA Direct Debit or PayPal.", 'jws_streamvid' );
	}

	public static function webhook_help() {
		return esc_html__( 'Nothing to set up in Mollie: every payment tells Mollie this address itself. Mollie cannot reach a site on localhost, so there payments are confirmed when the buyer comes back, and renewals are not recorded.', 'jws_streamvid' );
	}

	/**
	 * Mollie's multicurrency list (docs.mollie.com/docs/multicurrency).
	 * Which methods take which currency is Mollie's call per account.
	 */
	public static function currencies() {
		return array( 'AED', 'AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'ISK', 'JPY', 'MXN', 'MYR', 'NOK', 'NZD', 'PHP', 'PLN', 'RON', 'RUB', 'SEK', 'SGD', 'THB', 'TWD', 'UAH', 'USD', 'ZAR' );
	}

	/** A key starting test_ or live_ decides the mode on its own. */
	public static function key_mode( array $config ) {

		$key = (string) ( $config['api_key'] ?? '' );

		if ( 0 === strpos( $key, 'live_' ) ) {
			return 'live';
		}

		return 0 === strpos( $key, 'test_' ) ? 'test' : '';
	}

	/**
	 * A renewing plan has to fit Mollie's interval: days, weeks or months,
	 * at most one year apart.
	 */
	public static function supports( array $item ) {

		$base = parent::supports( $item );

		if ( is_wp_error( $base ) ) {
			return $base;
		}

		if ( ! empty( $item['recurring'] ) && '' === self::interval( $item['period'], $item['cycle'] ) ) {
			return new WP_Error( 'jws_payment_mollie_interval', esc_html__( 'Mollie cannot bill this often or this rarely.', 'jws_streamvid' ) );
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
			self::API . ltrim( $path, '/' ),
			array( 'Authorization' => 'Bearer ' . (string) $config['api_key'] ),
			$body
		);
	}

	/** Mollie answers errors as application/problem+json: title and detail. */
	protected static function error_message( $decoded ) {

		if ( ! is_array( $decoded ) ) {
			return '';
		}

		return (string) ( $decoded['detail'] ?? $decoded['title'] ?? '' );
	}

	/**
	 * An amount as Mollie wants it: a string with exactly the currency's
	 * decimals. Mollie lists ISK and JPY with none and everything else with
	 * two — HUF and TWD included, unlike the zero-decimal list Stripe uses.
	 */
	private static function amount( $value, $currency ) {

		$currency = strtoupper( (string) $currency );

		return array(
			'currency' => $currency,
			'value'    => in_array( $currency, array( 'ISK', 'JPY' ), true )
				? (string) (int) round( (float) $value )
				: number_format( (float) $value, 2, '.', '' ),
		);
	}

	/**
	 * A billing period as Mollie's interval string ("1 month", "2 weeks").
	 *
	 * Mollie has no year unit and allows at most a year between charges, so
	 * a yearly plan is "12 months" and anything longer cannot be expressed.
	 *
	 * @return string '' when Mollie cannot bill at this period.
	 */
	private static function interval( $period, $cycle ) {

		$cycle  = max( 1, (int) $cycle );
		$period = strtolower( (string) $period );

		if ( 'year' === $period ) {
			$period = 'month';
			$cycle *= 12;
		}

		$limits = array(
			'day'   => 365,
			'week'  => 52,
			'month' => 12,
		);

		if ( ! isset( $limits[ $period ] ) || $cycle > $limits[ $period ] ) {
			return '';
		}

		return $cycle . ' ' . $period . ( 1 === $cycle ? '' : 's' );
	}

	/* ---------------------------------------------------------------------- */
	/* Customers                                                               */
	/* ---------------------------------------------------------------------- */

	/**
	 * The Mollie customer for one site user, made on their first renewing
	 * purchase. Remembered per API key, because a customer id from one
	 * Mollie account (or from test mode) does not exist in another.
	 *
	 * @return string|WP_Error
	 */
	private static function customer_id( $user_id, $force_new = false ) {

		$saved_id = self::saved_customer_id( $user_id );

		if ( ! $force_new && $saved_id ) {
			return $saved_id;
		}

		$saved = get_user_meta( (int) $user_id, self::CUSTOMER_META, true );
		$saved = is_array( $saved ) ? $saved : array();
		$slot  = self::customer_slot();

		$user = get_userdata( (int) $user_id );

		$created = self::request(
			'POST',
			'customers',
			array(
				'name'     => $user ? $user->display_name : '',
				'email'    => $user ? $user->user_email : '',
				'metadata' => array( 'user_id' => (int) $user_id ),
			)
		);

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		if ( empty( $created['id'] ) ) {
			return new WP_Error( 'jws_payment_mollie_customer', esc_html__( 'Mollie did not create a customer.', 'jws_streamvid' ) );
		}

		$saved[ $slot ] = (string) $created['id'];

		update_user_meta( (int) $user_id, self::CUSTOMER_META, $saved );

		return $saved[ $slot ];
	}

	/** The customer already made for this user under the current key, or ''. */
	private static function saved_customer_id( $user_id ) {

		$saved = get_user_meta( (int) $user_id, self::CUSTOMER_META, true );
		$slot  = self::customer_slot();

		return is_array( $saved ) && ! empty( $saved[ $slot ] ) ? (string) $saved[ $slot ] : '';
	}

	/** Customers are remembered per API key: ids do not cross accounts or modes. */
	private static function customer_slot() {

		$config = static::config();

		return substr( md5( (string) $config['api_key'] ), 0, 12 );
	}

	/* ---------------------------------------------------------------------- */
	/* Buying                                                                  */
	/* ---------------------------------------------------------------------- */

	public static function start( $order, array $item ) {

		$body = array(
			'amount'      => self::amount( $order->amount, $order->currency ),
			'description' => self::description( (string) $order->item_label ),
			'redirectUrl' => static::return_url( $order ),
			'cancelUrl'   => static::return_url( $order, true ),
			'metadata'    => array( 'order_id' => (int) $order->id ),
		);

		$webhook = static::reachable_webhook_url();

		if ( $webhook ) {
			$body['webhookUrl'] = $webhook;
		}

		if ( ! empty( $item['recurring'] ) ) {

			$customer = self::customer_id( $order->user_id );

			if ( is_wp_error( $customer ) ) {
				return $customer;
			}

			/* The payment that also leaves a mandate behind for the
			   subscription created once it is paid. */
			$body['customerId']   = $customer;
			$body['sequenceType'] = 'first';
		}

		$created = self::request( 'POST', 'payments', $body );

		/* A customer remembered from an account the key no longer belongs to
		   is the one failure a fresh customer fixes. */
		if ( is_wp_error( $created ) && isset( $body['customerId'] ) && false !== strpos( $created->get_error_code(), '_404' ) ) {

			$customer = self::customer_id( $order->user_id, true );

			if ( is_wp_error( $customer ) ) {
				return $customer;
			}

			$body['customerId'] = $customer;
			$created            = self::request( 'POST', 'payments', $body );
		}

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		if ( empty( $created['id'] ) || empty( $created['_links']['checkout']['href'] ) ) {
			return new WP_Error( 'jws_payment_mollie_link', esc_html__( 'Mollie did not say where to send you.', 'jws_streamvid' ) );
		}

		Jws_Payment_Orders::attach_ref( $order->id, $created['id'] );

		return (string) $created['_links']['checkout']['href'];
	}

	/** Mollie takes up to 255 characters and cuts anything longer. */
	private static function description( $text ) {
		return substr( '' !== $text ? $text : get_bloginfo( 'name' ), 0, 255 );
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

		$payment = self::request( 'GET', 'payments/' . rawurlencode( (string) $order->gateway_ref ) );

		if ( is_wp_error( $payment ) ) {
			return false;
		}

		return self::settle_payment( $order, $payment );
	}

	/**
	 * Fulfils an order from the payment Mollie returned for it.
	 *
	 * `paid` is the only status that means the money moved; `open` and
	 * `pending` may still become paid, and `canceled`, `expired` and
	 * `failed` are final.
	 */
	private static function settle_payment( $order, array $payment ) {

		$status = (string) ( $payment['status'] ?? '' );

		if ( in_array( $status, array( 'canceled', 'expired', 'failed' ), true ) ) {
			Jws_Payment_Orders::mark_failed( $order->id );
			return false;
		}

		if ( 'paid' !== $status ) {
			return false;
		}

		if ( ! static::subscription_decided( $order ) ) {

			$ran = static::once(
				'mollie_sub_' . (int) $order->id,
				static function () use ( $order, $payment ) {
					/* Re-checked inside the lock: the request that held it
					   last may have just finished. */
					$fresh = Jws_Payment_Orders::find( $order->id );

					if ( $fresh && ! static::subscription_decided( $fresh ) ) {
						self::start_subscription( $fresh, $payment );
					}
				}
			);

			/* Either way, fulfilment has to see whether the plan renews or
			   was made one paid term, so it waits for that to be settled. */
			$order = $ran
				? Jws_Payment_Orders::find( $order->id )
				: static::wait_for( $order, array( static::class, 'subscription_decided' ) );
		}

		Jws_Payment_Orders::mark_paid( $order->id );

		return true;
	}

	/**
	 * Sets up the subscription a paid first payment was for. Runs inside
	 * once(), so the return and the webhook cannot both create one.
	 */
	private static function start_subscription( $order, array $payment ) {

		$meta     = Jws_Payment_Orders::meta( $order );
		$interval = self::interval( $meta['period'] ?? 'month', $meta['cycle'] ?? 1 );
		$customer = (string) ( $payment['customerId'] ?? '' );

		if ( ! $interval || ! $customer ) {
			static::settle_as_one_term( $order );
			return;
		}

		/* The first payment covered the first period, so Mollie's first
		   charge is one period from today. */
		$start = strtotime( '+' . max( 1, (int) ( $meta['cycle'] ?? 1 ) ) . ' ' . ( $meta['period'] ?? 'month' ) );

		$body = array(
			'amount'      => self::amount( $meta['renew'] ?? $order->amount, $order->currency ),
			'interval'    => $interval,
			'startDate'   => gmdate( 'Y-m-d', $start ),
			/* Mollie wants this unique per customer; the order number is. */
			'description' => self::description( $order->item_label . ' (' . $order->order_number . ')' ),
			'metadata'    => array( 'order_id' => (int) $order->id ),
		);

		if ( ! empty( $payment['mandateId'] ) ) {
			$body['mandateId'] = (string) $payment['mandateId'];
		}

		/* PMPro's billing limit counts the payments after the first one,
		   which is exactly what a Mollie subscription that starts after the
		   first payment charges. 0 means until cancelled. */
		if ( ! empty( $meta['billing_limit'] ) ) {
			$body['times'] = (int) $meta['billing_limit'];
		}

		$webhook = static::reachable_webhook_url();

		if ( $webhook ) {
			$body['webhookUrl'] = $webhook;
		}

		$created = self::request( 'POST', 'customers/' . rawurlencode( $customer ) . '/subscriptions', $body );

		if ( is_wp_error( $created ) || empty( $created['id'] ) ) {
			static::settle_as_one_term( $order );
			return;
		}

		static::record_subscription(
			$order,
			(string) $created['id'],
			Jws_Payment_Subscriptions::STATUS_ACTIVE,
			static::to_gmt( $start )
		);
	}

	/* ---------------------------------------------------------------------- */
	/* Webhooks                                                                */
	/* ---------------------------------------------------------------------- */

	/**
	 * One classic Mollie webhook: `id=tr_…`, form-encoded, no signature.
	 *
	 * Always answers 200, also for ids this site does not know — that is
	 * what Mollie asks for, so it does not retry a delivery that can never
	 * succeed and so a probe learns nothing from the answer.
	 */
	public static function receive( WP_REST_Request $request ) {

		$id = (string) $request->get_param( 'id' );

		if ( ! preg_match( '/^tr_[A-Za-z0-9]+$/', $id ) || ! static::ready() ) {
			return static::ok();
		}

		$payment = self::request( 'GET', 'payments/' . rawurlencode( $id ) );

		if ( is_wp_error( $payment ) || empty( $payment['id'] ) ) {
			return static::ok();
		}

		if ( ! empty( $payment['subscriptionId'] ) ) {
			self::renewal( $payment );
			return static::ok();
		}

		$order = static::find_order( (int) ( $payment['metadata']['order_id'] ?? 0 ), $payment['id'] );

		/* The order must be the one this payment was created for, not just
		   any order an id in the metadata happens to name. */
		if ( ! $order || (string) $order->gateway_ref !== (string) $payment['id'] ) {
			return static::ok();
		}

		if ( self::is_refunded( $payment ) ) {
			Jws_Payment_Orders::mark_refunded( $order->id );
			return static::ok();
		}

		self::settle_payment( $order, $payment );

		return static::ok();
	}

	/** Whether a payment has been refunded or charged back in full. */
	private static function is_refunded( array $payment ) {

		$total = (float) ( $payment['amount']['value'] ?? 0 );

		if ( $total <= 0 ) {
			return false;
		}

		$back = (float) ( $payment['amountRefunded']['value'] ?? 0 ) + (float) ( $payment['amountChargedBack']['value'] ?? 0 );

		return $back >= $total;
	}

	/**
	 * A payment Mollie made for a subscription.
	 *
	 * Mollie sends no webhook when a subscription itself changes, so a failed
	 * renewal is also when the subscription is read back: after its last
	 * retry Mollie cancels it, and the membership has to end with it.
	 */
	private static function renewal( array $payment ) {

		$ref    = (string) $payment['subscriptionId'];
		$record = Jws_Payment_Subscriptions::find_by_ref( 'mollie', $ref );

		if ( ! $record ) {
			return;
		}

		$live = empty( $payment['customerId'] )
			? null
			: self::request( 'GET', 'customers/' . rawurlencode( (string) $payment['customerId'] ) . '/subscriptions/' . rawurlencode( $ref ) );

		$next = is_array( $live ) ? static::to_gmt( $live['nextPaymentDate'] ?? '' ) : null;

		if ( 'paid' === ( $payment['status'] ?? '' ) ) {

			Jws_Payment_Subscriptions::set_status( 'mollie', $ref, Jws_Payment_Subscriptions::STATUS_ACTIVE, $next );

			Jws_Payment_Orders::record_renewal( $record, (string) $payment['id'], (float) ( $payment['amount']['value'] ?? $record->amount ) );

			return;
		}

		if ( ! in_array( $payment['status'] ?? '', array( 'failed', 'canceled', 'expired' ), true ) ) {
			return;
		}

		$ended = is_array( $live ) && in_array( $live['status'] ?? '', array( 'canceled', 'completed' ), true );

		Jws_Payment_Subscriptions::set_status(
			'mollie',
			$ref,
			$ended ? Jws_Payment_Subscriptions::STATUS_CANCELED : Jws_Payment_Subscriptions::STATUS_PAST_DUE
		);

		if ( $ended ) {
			static::end_membership( $record );
		}
	}

	/* ---------------------------------------------------------------------- */
	/* Cancelling                                                              */
	/* ---------------------------------------------------------------------- */

	/**
	 * Cancelling needs the customer as well as the subscription, and only the
	 * subscription id is stored on the row, so the customer is found through
	 * the member it belongs to.
	 */
	public static function cancel_subscription( $ref ) {

		$record = Jws_Payment_Subscriptions::find_by_ref( 'mollie', (string) $ref );

		if ( ! $record ) {
			return new WP_Error( 'jws_payment_mollie_unknown', esc_html__( 'That subscription is not on record.', 'jws_streamvid' ) );
		}

		/* Read only: cancelling must never be what creates a customer. */
		$customer = self::saved_customer_id( $record->user_id );

		if ( ! $customer ) {
			return new WP_Error( 'jws_payment_mollie_customer', esc_html__( 'The Mollie customer for this subscription is not on record under the current API key.', 'jws_streamvid' ) );
		}

		return self::request( 'DELETE', 'customers/' . rawurlencode( $customer ) . '/subscriptions/' . rawurlencode( (string) $ref ) );
	}
}
