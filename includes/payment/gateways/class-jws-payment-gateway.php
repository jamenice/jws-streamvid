<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * What every redirect gateway added after Stripe and PayPal has in common.
 *
 * Razorpay, Paystack, Mollie and Mercado Pago all work the same way from the
 * checkout's side: create something at the gateway, send the buyer to the
 * gateway's own page, take them back to the checkout, then ask the gateway
 * whether the money moved. Webhooks say the same thing later, and again for
 * every renewal. This class holds the parts of that which do not depend on
 * whose API is on the other end, so each gateway is only its own API calls.
 *
 * Everything is static, like Jws_Payment_Stripe and Jws_Payment_Paypal: the
 * gateways keep no state of their own beyond their settings, and `static::`
 * lets the shared helpers read the subclass that called them.
 */
abstract class Jws_Payment_Gateway {

	/* ---------------------------------------------------------------------- */
	/* What each gateway declares                                              */
	/* ---------------------------------------------------------------------- */

	/** Settings key, the order row's `gateway` value and the webhook path. */
	abstract public static function id();

	/** The gateway's name as its own brand writes it. */
	abstract public static function label();

	/**
	 * The credential keys this gateway stores, without labels.
	 *
	 * Separate from fields() because settings are read as early as
	 * `plugins_loaded`, before translations may load, and the labels are
	 * translated. Every key is required unless optional_fields() names it.
	 *
	 * @return string[]
	 */
	abstract public static function field_keys();

	/**
	 * The credential fields as the Gateways tab shows them.
	 *
	 * @return array key => array( label, input type, placeholder ), one per
	 *               field_keys() entry.
	 */
	abstract public static function fields();

	/** Field keys the gateway works without (a webhook secret, usually). */
	public static function optional_fields() {
		return array();
	}

	/** The line under this gateway's row on the Payment methods tab. */
	abstract public static function method_note();

	/**
	 * The gateway's logo in public/assets/images/payment, drawn on a 40 x 26
	 * white badge like the others there. A missing file shows no logo rather
	 * than a broken image (see Jws_Payment_Settings::method_image_url()).
	 */
	public static function logo() {
		return static::id() . '.svg';
	}

	/** What to do with the webhook URL, shown under it on the Gateways tab. */
	abstract public static function webhook_help();

	/** The events to subscribe to, when the gateway asks for a list. */
	public static function webhook_events() {
		return array();
	}

	/**
	 * Whether this gateway bills a renewing plan again by itself.
	 *
	 * A gateway that answers false is sold a renewing plan the way the
	 * WooCommerce path is: one paid billing period, with an end date, bought
	 * again to carry on. The checkout hides its auto-renew lines for it.
	 */
	public static function renews() {
		return true;
	}

	/**
	 * The currencies this gateway can charge, upper case.
	 *
	 * Empty means the gateway itself decides at the moment of the charge,
	 * and its own error is shown if it refuses.
	 */
	public static function currencies() {
		return array();
	}

	/**
	 * Whether this gateway can sell one item, or why it cannot.
	 *
	 * Asked before the method is offered, so a buyer is never shown a button
	 * that can only fail. The base checks the currency; gateways add their
	 * own limits (an intro price, a billing period they cannot express).
	 *
	 * @return true|WP_Error
	 */
	public static function supports( array $item ) {

		/* Sold as one paid term, a renewing plan needs a period to end on. */
		if ( ! static::renews() && ! empty( $item['recurring'] ) && ! Jws_Payment_Woocommerce::term_for( $item ) ) {
			return new WP_Error( 'jws_payment_term', esc_html__( 'This plan has no billing period to sell one term of.', 'jws_streamvid' ) );
		}

		$list = static::currencies();

		if ( $list && ! in_array( strtoupper( (string) $item['currency'] ), $list, true ) ) {
			return new WP_Error(
				'jws_payment_currency',
				sprintf(
					/* translators: 1: gateway name, 2: currency code. */
					esc_html__( '%1$s cannot charge in %2$s.', 'jws_streamvid' ),
					static::label(),
					strtoupper( (string) $item['currency'] )
				)
			);
		}

		return true;
	}

	/**
	 * Creates the payment or subscription at the gateway.
	 *
	 * @return string|WP_Error The gateway page to send the buyer to.
	 */
	abstract public static function start( $order, array $item );

	/**
	 * Asks the gateway whether an order is paid, and fulfils it if so.
	 *
	 * Called when the buyer comes back. Never trusts anything in the URL: the
	 * order is looked up by its own token, and the answer comes from the API.
	 *
	 * @return bool Whether the order is paid.
	 */
	abstract public static function settle( $order );

	/**
	 * Stops the gateway billing a subscription.
	 *
	 * @return mixed|WP_Error
	 */
	abstract public static function cancel_subscription( $ref );

	/** Handles one webhook delivery. */
	abstract public static function receive( WP_REST_Request $request );

	/**
	 * Pulls a readable message out of an error response.
	 *
	 * @param mixed $decoded The decoded body.
	 * @return string Empty when the body says nothing useful.
	 */
	abstract protected static function error_message( $decoded );

	/* ---------------------------------------------------------------------- */
	/* Settings                                                                */
	/* ---------------------------------------------------------------------- */

	/** The stored shape of this gateway's settings. */
	public static function defaults() {

		$out = array(
			'enabled' => 0,
			'mode'    => 'test',
		);

		foreach ( static::field_keys() as $key ) {
			$out[ $key ] = '';
		}

		return $out;
	}

	/**
	 * Cleans what the Gateways tab posted for this gateway.
	 *
	 * @param mixed $raw This gateway's part of the form, already unslashed by
	 *                   Jws_Payment_Settings::handle_post().
	 */
	public static function sanitize( $raw ) {

		$raw = is_array( $raw ) ? $raw : array();

		$out = array(
			'enabled' => ! empty( $raw['enabled'] ) ? 1 : 0,
			'mode'    => isset( $raw['mode'] ) && 'live' === $raw['mode'] ? 'live' : 'test',
		);

		foreach ( static::field_keys() as $key ) {
			$out[ $key ] = isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? trim( sanitize_text_field( (string) $raw[ $key ] ) ) : '';
		}

		return $out;
	}

	/** Whether every required credential is filled in. */
	public static function has_credentials( array $config ) {

		foreach ( static::field_keys() as $key ) {

			if ( in_array( $key, static::optional_fields(), true ) ) {
				continue;
			}

			if ( empty( $config[ $key ] ) ) {
				return false;
			}
		}

		return true;
	}

	public static function config() {
		return Jws_Payment_Settings::get( static::id() );
	}

	public static function ready() {
		return Jws_Payment_Settings::gateway_ready( static::id() );
	}

	public static function mode() {

		$config = static::config();

		return 'live' === ( $config['mode'] ?? '' ) ? 'live' : 'test';
	}

	/* ---------------------------------------------------------------------- */
	/* URLs                                                                    */
	/* ---------------------------------------------------------------------- */

	/** Where this gateway's webhooks are received. */
	public static function webhook_url() {
		return rest_url( Jws_Payment_Events::NAMESPACE_REST . '/webhook/' . static::id() );
	}

	/**
	 * Where the gateway sends the buyer back to.
	 *
	 * The same return the PayPal path uses: Jws_Payment_Events::handle_return()
	 * reads the order token, settles through the order's own gateway (never
	 * the one named in the URL) and lands the buyer on the receipt.
	 */
	public static function return_url( $order, $cancelled = false ) {

		$args = array(
			Jws_Payment_Stripe::RETURN_VAR => (string) $order->source_ref,
			'gw'                           => static::id(),
		);

		if ( $cancelled ) {
			$args['cancelled'] = 1;
		}

		/* App mode has to survive the trip out to the gateway and back. */
		if ( Jws_Payment_Checkout::order_is_app( $order ) ) {
			$args[ Jws_Payment_Checkout::APP_VAR ] = '1';
		}

		return add_query_arg( $args, Jws_Payment_Checkout::page_url() );
	}

	/**
	 * A URL on this site that posts a form to the gateway.
	 *
	 * For gateways whose hosted page has to be opened with a form POST (a
	 * token in the body) rather than a link: the checkout script can only
	 * navigate, so it is sent here and Jws_Payment_Gateways::post_redirect()
	 * submits the form. The fields wait in a short-lived transient, and only
	 * the buyer the order belongs to can open it.
	 *
	 * @param object $order  The order being paid.
	 * @param string $action The gateway URL the form posts to (https).
	 * @param array  $fields name => value, posted as hidden inputs.
	 * @return string|WP_Error
	 */
	protected static function post_redirect_url( $order, $action, array $fields ) {

		if ( 0 !== strpos( (string) $action, 'https://' ) ) {
			return new WP_Error( 'jws_payment_post_target', esc_html__( 'The payment page address is not secure.', 'jws_streamvid' ) );
		}

		$key = wp_generate_password( 32, false );

		set_transient(
			Jws_Payment_Gateways::POST_PREFIX . $key,
			array(
				'user'   => (int) $order->user_id,
				'action' => (string) $action,
				'fields' => array_map( 'strval', $fields ),
			),
			15 * MINUTE_IN_SECONDS
		);

		return Jws_Payment_Checkout::with_app(
			add_query_arg( Jws_Payment_Gateways::POST_VAR, $key, Jws_Payment_Checkout::page_url() ),
			Jws_Payment_Checkout::order_is_app( $order )
		);
	}

	/* ---------------------------------------------------------------------- */
	/* Transport                                                               */
	/* ---------------------------------------------------------------------- */

	/**
	 * One call to the gateway's API.
	 *
	 * @param string            $method  GET, POST, PUT, PATCH, DELETE.
	 * @param string            $url     Full URL.
	 * @param array             $headers Auth and anything else the gateway wants.
	 * @param array|object|null $body    Sent as JSON unless $form.
	 * @param bool              $form    Send the body form-encoded instead.
	 * @return array|WP_Error The decoded body.
	 */
	protected static function http( $method, $url, array $headers, $body = null, $form = false ) {

		$args = array(
			'method'  => strtoupper( $method ),
			'timeout' => 30,
			'headers' => array_merge(
				array(
					'Accept'       => 'application/json',
					'Content-Type' => $form ? 'application/x-www-form-urlencoded' : 'application/json',
				),
				$headers
			),
		);

		if ( null !== $body ) {
			$args['body'] = $form ? $body : self::json( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 300 ) {

			$message = static::error_message( $decoded );

			return new WP_Error(
				'jws_payment_' . static::id() . '_' . $code,
				$message ? $message : sprintf(
					/* translators: %s: gateway name. */
					esc_html__( '%s refused the request.', 'jws_streamvid' ),
					static::label()
				),
				is_array( $decoded ) ? $decoded : array()
			);
		}

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * JSON with prices written the way they were meant.
	 *
	 * With PHP's serialize_precision at 17, which many hosts still set,
	 * json_encode() writes 39.99 as 39.990000000000002. -1 is the shortest
	 * form that reads back exactly, and is put back as it was afterwards.
	 */
	private static function json( $body ) {

		$previous = ini_get( 'serialize_precision' );

		ini_set( 'serialize_precision', '-1' ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- restored below.

		$json = wp_json_encode( $body );

		ini_set( 'serialize_precision', (string) $previous ); // phpcs:ignore WordPress.PHP.IniSet.Risky

		return $json;
	}

	/* ---------------------------------------------------------------------- */
	/* Shared bookkeeping                                                      */
	/* ---------------------------------------------------------------------- */

	/**
	 * A price in the gateway's smallest unit (cents, paise, kobo).
	 *
	 * The same zero-decimal list Stripe uses: for those currencies the major
	 * unit is the smallest one, and multiplying by 100 would overcharge the
	 * buyer a hundred times.
	 */
	public static function minor_units( $amount, $currency ) {
		return Jws_Payment_Stripe::minor_units( $amount, $currency );
	}

	/** Whether a currency has no minor unit. */
	public static function is_zero_decimal( $currency ) {
		return self::minor_units( 1, $currency ) === 1;
	}

	/** An amount as a decimal string with the currency's own precision. */
	public static function decimal( $amount, $currency ) {

		return self::is_zero_decimal( $currency )
			? (string) (int) round( (float) $amount )
			: number_format( (float) $amount, 2, '.', '' );
	}

	/**
	 * Writes the subscription row for one sign-up order and links the two.
	 *
	 * @param object      $order      The sign-up order.
	 * @param string      $ref        The gateway's subscription id.
	 * @param string      $status     One of Jws_Payment_Subscriptions::STATUS_*.
	 * @param string|null $period_end When the paid period ends, UTC (see to_gmt()), or null.
	 * @param float|null  $amount     What each renewal charges; the order's renewal price when null.
	 * @return int Subscription row id, or 0.
	 */
	protected static function record_subscription( $order, $ref, $status, $period_end = null, $amount = null ) {

		$meta = Jws_Payment_Orders::meta( $order );

		$subscription_id = Jws_Payment_Subscriptions::upsert(
			array(
				'user_id'            => (int) $order->user_id,
				'type'               => (string) $order->type,
				'item_id'            => (int) $order->item_id,
				'label'              => (string) $order->item_label,
				'fingerprint'        => (string) ( $meta['fingerprint'] ?? '' ),
				'gateway'            => static::id(),
				'gateway_ref'        => (string) $ref,
				'amount'             => null === $amount ? (float) ( $meta['renew'] ?? $order->amount ) : (float) $amount,
				'currency'           => (string) $order->currency,
				'status'             => $status,
				'current_period_end' => $period_end,
			)
		);

		if ( $subscription_id ) {
			Jws_Payment_Orders::attach_subscription( $order->id, $subscription_id );
		}

		return $subscription_id;
	}

	/**
	 * A gateway timestamp as the UTC string `current_period_end` holds.
	 *
	 * UTC because that is what the Stripe and PayPal paths write into the
	 * same column (Jws_Payment_Stripe::period_end()).
	 *
	 * @param int|string $value Unix seconds, or anything strtotime() reads.
	 * @return string|null
	 */
	protected static function to_gmt( $value ) {

		if ( '' === $value || null === $value ) {
			return null;
		}

		$timestamp = is_numeric( $value ) ? (int) $value : strtotime( (string) $value );

		return $timestamp ? gmdate( 'Y-m-d H:i:s', $timestamp ) : null;
	}

	/**
	 * The order a gateway object belongs to.
	 *
	 * Every gateway here is handed the order's id when the payment is created
	 * (in its own metadata or reference field) and stores its own id on the
	 * order through attach_ref(). Either is enough to find the order again;
	 * the id is tried first because it cannot be confused with a renewal.
	 *
	 * @param int    $order_id From the gateway object's metadata, or 0.
	 * @param string $ref      The gateway's id for the payment or subscription.
	 * @return object|null
	 */
	protected static function find_order( $order_id, $ref = '' ) {

		$order = $order_id ? Jws_Payment_Orders::find( (int) $order_id ) : null;

		if ( $order && static::id() === $order->gateway ) {
			return $order;
		}

		return $ref ? Jws_Payment_Orders::find_by_ref( static::id(), (string) $ref ) : null;
	}

	/**
	 * Records a renewing plan as one paid term instead.
	 *
	 * For when the first payment went through but the gateway would not set
	 * up the subscription that renews it. Left as it is, the order would grant
	 * a membership with no end date and nothing to ever charge for it again —
	 * permanent access for one payment. The term meta is the same pair the
	 * WooCommerce path writes, which grant_membership() turns into an end
	 * date one billing period away.
	 */
	protected static function settle_as_one_term( $order ) {

		$meta = Jws_Payment_Orders::meta( $order );

		Jws_Payment_Orders::merge_meta(
			$order->id,
			array(
				'recurring'   => false,
				'term_cycle'  => max( 1, (int) ( $meta['cycle'] ?? 1 ) ),
				'term_period' => (string) ( $meta['period'] ?? 'month' ),
			)
		);
	}

	/**
	 * Runs a step at most once at a time across requests.
	 *
	 * The buyer's return and the gateway's webhook routinely arrive together
	 * for the same payment. add_option() succeeds for exactly one of them,
	 * so it is the lock; a lock older than a minute is taken to belong to a
	 * request that died, and is taken over.
	 *
	 * @param string   $name What is being locked, unique per order.
	 * @param callable $step Runs only for the caller holding the lock.
	 * @return bool Whether this caller ran the step.
	 */
	protected static function once( $name, callable $step ) {

		$lock = 'jws_payment_lock_' . $name;

		if ( ! add_option( $lock, time(), '', 'no' ) ) {

			if ( time() - (int) get_option( $lock, 0 ) < MINUTE_IN_SECONDS ) {
				return false;
			}

			update_option( $lock, time(), false );
		}

		try {
			$step();
		} finally {
			delete_option( $lock );
		}

		return true;
	}

	/**
	 * Waits, briefly, for another request to finish a step for this order.
	 *
	 * For the caller that lost once(): fulfilling the order before the
	 * winner has decided between "renews" and "one paid term" could grant
	 * the wrong one.
	 *
	 * @param callable $done Given the re-read order; true once the step is over.
	 * @return object The order as it stands after waiting.
	 */
	protected static function wait_for( $order, callable $done ) {

		for ( $i = 0; $i < 10; $i++ ) {

			$fresh = Jws_Payment_Orders::find( $order->id );

			if ( ! $fresh || $done( $fresh ) ) {
				return $fresh ? $fresh : $order;
			}

			usleep( 500000 );
		}

		return Jws_Payment_Orders::find( $order->id ) ?: $order;
	}

	/** Takes away the level a subscription the gateway gave up on paid for. */
	protected static function end_membership( $record ) {

		if ( 'membership' !== $record->type || ! function_exists( 'pmpro_hasMembershipLevel' ) || ! function_exists( 'pmpro_cancelMembershipLevel' ) ) {
			return;
		}

		if ( pmpro_hasMembershipLevel( (int) $record->item_id, (int) $record->user_id ) ) {
			pmpro_cancelMembershipLevel( (int) $record->item_id, (int) $record->user_id, 'inactive' );
		}
	}

	/** Whether an order's renewing plan has been decided: linked, or made one term. */
	protected static function subscription_decided( $order ) {

		$meta = Jws_Payment_Orders::meta( $order );

		return (int) $order->subscription_id > 0 || empty( $meta['recurring'] );
	}

	/**
	 * The webhook URL to hand the gateway, or '' when it could never reach it.
	 *
	 * Some gateways refuse to create a payment at all when the notification
	 * URL is localhost. Leaving it out there keeps local testing possible:
	 * the payment is still confirmed when the buyer comes back.
	 */
	protected static function reachable_webhook_url() {

		$url  = static::webhook_url();
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

		if ( ! $host || 'localhost' === $host || preg_match( '/\.(localhost|local|test|invalid|example)$/', $host ) ) {
			return '';
		}

		if ( filter_var( $host, FILTER_VALIDATE_IP ) && ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return '';
		}

		return $url;
	}

	/**
	 * Which mode a credential belongs to, when its shape says so.
	 *
	 * Several gateways decide test or live by the key itself, so the Mode
	 * setting cannot switch them; this lets the settings screen warn when the
	 * two disagree.
	 *
	 * @return string 'test', 'live', or '' when the key does not say.
	 */
	public static function key_mode( array $config ) {
		return '';
	}

	/** A 200 for a webhook, so the gateway stops retrying it. */
	protected static function ok() {
		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	/** A webhook that did not pass verification. */
	protected static function rejected( $why = 'bad_signature' ) {
		return new WP_REST_Response( array( 'error' => $why ), 400 );
	}
}
