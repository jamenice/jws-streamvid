<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * Mercado Pago, over its REST API (mercadopago.com/developers).
 *
 * One-off sales go through Checkout Pro: a Preference the buyer pays on
 * Mercado Pago's page. A renewing plan is a Subscription (preapproval) created
 * as `pending`, which the buyer authorises on Mercado Pago's page by adding a
 * payment method; Mercado Pago bills it from then on.
 *
 * Nothing that grants access is read from a return URL or a notification
 * body. Both only say which object to look at; the object itself is fetched
 * from the API with the site's own token.
 */
class Jws_Payment_Mercadopago extends Jws_Payment_Gateway {

	const API = 'https://api.mercadopago.com/';

	/* ---------------------------------------------------------------------- */
	/* Declarations                                                            */
	/* ---------------------------------------------------------------------- */

	public static function id() {
		return 'mercadopago';
	}

	public static function label() {
		return 'Mercado Pago';
	}

	public static function field_keys() {
		return array( 'access_token', 'webhook_secret' );
	}

	public static function fields() {
		return array(
			'access_token'   => array( esc_html__( 'Access token', 'jws_streamvid' ), 'password', 'APP_USR-…' ),
			'webhook_secret' => array( esc_html__( 'Webhook secret', 'jws_streamvid' ), 'password', '' ),
		);
	}

	public static function optional_fields() {
		return array( 'webhook_secret' );
	}

	public static function method_note() {
		return esc_html__( "Sends the buyer to Mercado Pago: cards, account money and local methods in Argentina, Brazil, Chile, Colombia, Mexico, Peru and Uruguay. For a membership the buyer has to use the same email on Mercado Pago as on this site.", 'jws_streamvid' );
	}

	public static function webhook_help() {
		return esc_html__( 'Add this URL in Mercado Pago under Your integrations > Webhooks, tick the events below, then paste the secret it shows above. Mercado Pago only notifies public https addresses, and test payments send no notifications.', 'jws_streamvid' );
	}

	public static function webhook_events() {
		return array( 'payment', 'subscription_preapproval', 'subscription_authorized_payment' );
	}

	/** The local currencies of the countries Mercado Pago operates in, and USD where a site accepts it. */
	public static function currencies() {
		return array( 'ARS', 'BRL', 'CLP', 'COP', 'MXN', 'PEN', 'UYU', 'USD' );
	}

	/**
	 * What a renewing plan has to look like for Mercado Pago to bill it.
	 *
	 * Subscriptions are only "every N days" or "every N months", and the docs
	 * describe no way to charge a different first amount or to stop after a
	 * set number of payments without a plan, so plans with an intro price or
	 * a billing limit are not offered rather than billed wrongly.
	 */
	public static function supports( array $item ) {

		$base = parent::supports( $item );

		if ( is_wp_error( $base ) || empty( $item['recurring'] ) ) {
			return $base;
		}

		if ( ! self::frequency( $item['period'], $item['cycle'] ) ) {
			return new WP_Error( 'jws_payment_mercadopago_period', esc_html__( 'Mercado Pago cannot bill this often.', 'jws_streamvid' ) );
		}

		if ( (float) $item['amount'] !== (float) $item['renew'] ) {
			return new WP_Error( 'jws_payment_mercadopago_intro', esc_html__( 'Mercado Pago cannot charge a different first payment.', 'jws_streamvid' ) );
		}

		if ( ! empty( $item['meta']['billing_limit'] ) ) {
			return new WP_Error( 'jws_payment_mercadopago_limit', esc_html__( 'Mercado Pago cannot stop a plan after a set number of payments.', 'jws_streamvid' ) );
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
			array( 'Authorization' => 'Bearer ' . (string) $config['access_token'] ),
			$body
		);
	}

	/** Mercado Pago errors carry `message`, and sometimes a `cause` list. */
	protected static function error_message( $decoded ) {

		if ( ! is_array( $decoded ) ) {
			return '';
		}

		if ( ! empty( $decoded['cause'][0]['description'] ) ) {
			return (string) $decoded['cause'][0]['description'];
		}

		return (string) ( $decoded['message'] ?? '' );
	}

	/** Chilean pesos take no decimals; the other currencies take two. */
	private static function price( $amount, $currency ) {

		return 'CLP' === strtoupper( (string) $currency )
			? (int) round( (float) $amount )
			: round( (float) $amount, 2 );
	}

	/**
	 * A billing period as Mercado Pago's frequency.
	 *
	 * Only days and months exist. A week is sent as seven days and a year as
	 * twelve months — the docs promise weekly and yearly plans without saying
	 * how they are written, and these are the plain equivalents.
	 *
	 * @return array|null array( frequency, frequency_type )
	 */
	private static function frequency( $period, $cycle ) {

		$cycle = max( 1, (int) $cycle );

		switch ( strtolower( (string) $period ) ) {
			case 'day':
				return array( $cycle, 'days' );
			case 'week':
				return array( 7 * $cycle, 'days' );
			case 'month':
				return array( $cycle, 'months' );
			case 'year':
				return array( 12 * $cycle, 'months' );
		}

		return null;
	}

	/**
	 * The reference Mercado Pago carries for an order: letters, digits, `-`
	 * and `_`, at most 64. The token prefix makes it more than an id.
	 */
	private static function reference( $order ) {
		return 'jws-' . (int) $order->id . '-' . substr( preg_replace( '/[^A-Za-z0-9]/', '', (string) $order->source_ref ), 0, 12 );
	}

	/** The order a reference names, if the reference is still exactly that order's. */
	private static function order_for_reference( $reference ) {

		if ( ! preg_match( '/^jws-(\d+)-[A-Za-z0-9]+$/', (string) $reference, $match ) ) {
			return null;
		}

		$order = static::find_order( (int) $match[1] );

		return $order && self::reference( $order ) === $reference ? $order : null;
	}

	/** An https URL, or '' — Mercado Pago silently drops anything else. */
	private static function https_only( $url ) {
		return 0 === strpos( (string) $url, 'https://' ) ? (string) $url : '';
	}

	/* ---------------------------------------------------------------------- */
	/* Buying                                                                  */
	/* ---------------------------------------------------------------------- */

	public static function start( $order, array $item ) {

		return empty( $item['recurring'] )
			? self::start_payment( $order )
			: self::start_subscription( $order, $item );
	}

	/** A Checkout Pro preference for a one-off sale. */
	private static function start_payment( $order ) {

		$body = array(
			'items'              => array(
				array(
					'id'          => (string) (int) $order->item_id,
					'title'       => substr( (string) $order->item_label, 0, 256 ),
					'quantity'    => 1,
					'currency_id' => strtoupper( (string) $order->currency ),
					'unit_price'  => self::price( $order->amount, $order->currency ),
				),
			),
			'external_reference' => self::reference( $order ),
			'metadata'           => array( 'order_id' => (int) $order->id ),
		);

		$back = self::https_only( static::return_url( $order ) );

		if ( $back ) {
			$body['back_urls']   = array(
				'success' => $back,
				'pending' => $back,
				'failure' => self::https_only( static::return_url( $order, true ) ),
			);
			$body['auto_return'] = 'approved';
		}

		$notify = self::https_only( static::reachable_webhook_url() );

		if ( $notify && strlen( $notify ) <= 248 ) {
			$body['notification_url'] = $notify;
		}

		$created = self::request( 'POST', 'checkout/preferences', $body );

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		if ( empty( $created['id'] ) || empty( $created['init_point'] ) ) {
			return new WP_Error( 'jws_payment_mercadopago_link', esc_html__( 'Mercado Pago did not say where to send you.', 'jws_streamvid' ) );
		}

		Jws_Payment_Orders::attach_ref( $order->id, $created['id'] );

		return (string) $created['init_point'];
	}

	/** A pending subscription the buyer authorises on Mercado Pago. */
	private static function start_subscription( $order, array $item ) {

		$user      = get_userdata( (int) $order->user_id );
		$frequency = self::frequency( $item['period'], $item['cycle'] );

		if ( ! $user || ! $frequency ) {
			return new WP_Error( 'jws_payment_mercadopago_subscription', esc_html__( 'This plan cannot be billed through Mercado Pago.', 'jws_streamvid' ) );
		}

		$created = self::request(
			'POST',
			'preapproval',
			array(
				'reason'             => substr( (string) $order->item_label, 0, 256 ),
				'external_reference' => self::reference( $order ),
				/* Mercado Pago rejects the payment if the buyer signs in with a
				   different email, which the method note warns about. */
				'payer_email'        => $user->user_email,
				'back_url'           => static::return_url( $order ),
				'status'             => 'pending',
				'auto_recurring'     => array(
					'frequency'          => $frequency[0],
					'frequency_type'     => $frequency[1],
					'transaction_amount' => self::price( $item['renew'], $order->currency ),
					'currency_id'        => strtoupper( (string) $order->currency ),
				),
			)
		);

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		if ( empty( $created['id'] ) || empty( $created['init_point'] ) ) {
			return new WP_Error( 'jws_payment_mercadopago_link', esc_html__( 'Mercado Pago did not say where to send you.', 'jws_streamvid' ) );
		}

		Jws_Payment_Orders::attach_ref( $order->id, $created['id'] );

		return (string) $created['init_point'];
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

		$meta = Jws_Payment_Orders::meta( $order );

		return empty( $meta['recurring'] )
			? self::settle_payment( $order )
			: self::settle_subscription( $order );
	}

	/**
	 * Finds the payment made for an order's preference.
	 *
	 * The preference itself has no status, and the buyer may have tried more
	 * than once, so the payments are searched by the order's reference and
	 * any approved one settles it.
	 */
	private static function settle_payment( $order ) {

		$found = self::request(
			'GET',
			'v1/payments/search?' . http_build_query(
				array(
					'external_reference' => self::reference( $order ),
					'sort'               => 'date_created',
					'criteria'           => 'desc',
				)
			)
		);

		if ( is_wp_error( $found ) ) {
			return false;
		}

		foreach ( (array) ( $found['results'] ?? array() ) as $payment ) {
			if ( 'approved' === ( $payment['status'] ?? '' ) ) {
				Jws_Payment_Orders::mark_paid( $order->id );
				return true;
			}
		}

		return false;
	}

	/**
	 * Reads a subscription back. `authorized` means the buyer added a payment
	 * method and Mercado Pago will bill it, which is when access starts — as
	 * with a PayPal subscription that is ACTIVE.
	 */
	private static function settle_subscription( $order ) {

		$subscription = self::request( 'GET', 'preapproval/' . rawurlencode( (string) $order->gateway_ref ) );

		if ( is_wp_error( $subscription ) || 'authorized' !== ( $subscription['status'] ?? '' ) ) {
			return false;
		}

		/* The row first, so fulfilment sees the order linked to it. upsert()
		   is keyed on the preapproval id, so the return and the webhook can
		   both get here without writing two. */
		static::record_subscription(
			$order,
			(string) $subscription['id'],
			Jws_Payment_Subscriptions::STATUS_ACTIVE,
			static::to_gmt( $subscription['next_payment_date'] ?? '' )
		);

		Jws_Payment_Orders::mark_paid( $order->id );

		return true;
	}

	/* ---------------------------------------------------------------------- */
	/* Webhooks                                                                */
	/* ---------------------------------------------------------------------- */

	/**
	 * One Mercado Pago notification.
	 *
	 * When a secret is set and the request is signed, the x-signature header
	 * is checked: HMAC-SHA256 under the secret of
	 * "id:{data.id};request-id:{x-request-id};ts:{ts};", with data.id taken
	 * from the query string and lower-cased. A bad signature is refused.
	 * Unsigned notifications (the docs do not say every route signs them) are
	 * still safe to act on, because all they can do is make this site fetch
	 * an object from Mercado Pago with its own token.
	 */
	public static function receive( WP_REST_Request $request ) {

		if ( ! static::ready() ) {
			return static::ok();
		}

		$body = json_decode( (string) $request->get_body(), true );
		$body = is_array( $body ) ? $body : array();

		/* PHP turns the dot in `data.id` into an underscore. */
		$id   = (string) ( $request->get_param( 'data_id' ) ?: ( $body['data']['id'] ?? $request->get_param( 'id' ) ) );
		$type = (string) ( $request->get_param( 'type' ) ?: ( $body['type'] ?? $request->get_param( 'topic' ) ) );

		if ( ! self::signature_ok( $request, $id ) ) {
			return new WP_REST_Response( array( 'error' => 'bad_signature' ), 401 );
		}

		if ( ! preg_match( '/^[A-Za-z0-9_-]+$/', $id ) ) {
			return static::ok();
		}

		switch ( $type ) {

			case 'payment':
				self::on_payment( $id );
				break;

			case 'subscription_preapproval':
				self::on_subscription( $id );
				break;

			case 'subscription_authorized_payment':
				/* Busy with the same subscription in another request: a
				   non-2xx answer makes Mercado Pago deliver it again later,
				   rather than the charge going unrecorded. */
				if ( ! self::on_authorized_payment( $id ) ) {
					return new WP_REST_Response( array( 'error' => 'busy' ), 503 );
				}
				break;
		}

		return static::ok();
	}

	/** True when unsigned or correctly signed; false only for a bad signature. */
	private static function signature_ok( WP_REST_Request $request, $data_id ) {

		$config = static::config();
		$secret = (string) ( $config['webhook_secret'] ?? '' );
		$header = (string) $request->get_header( 'x_signature' );

		if ( '' === $secret || '' === $header ) {
			return true;
		}

		$parts = array();

		foreach ( explode( ',', $header ) as $pair ) {
			$kv = array_map( 'trim', explode( '=', $pair, 2 ) );

			if ( 2 === count( $kv ) ) {
				$parts[ $kv[0] ] = $kv[1];
			}
		}

		if ( empty( $parts['ts'] ) || empty( $parts['v1'] ) ) {
			return false;
		}

		$manifest = '';

		if ( '' !== (string) $data_id ) {
			$manifest .= 'id:' . strtolower( (string) $data_id ) . ';';
		}

		$request_id = (string) $request->get_header( 'x_request_id' );

		if ( '' !== $request_id ) {
			$manifest .= 'request-id:' . $request_id . ';';
		}

		$manifest .= 'ts:' . $parts['ts'] . ';';

		return hash_equals( hash_hmac( 'sha256', $manifest, $secret ), $parts['v1'] );
	}

	/** A one-off payment changed: paid, refunded or charged back. */
	private static function on_payment( $payment_id ) {

		$payment = self::request( 'GET', 'v1/payments/' . rawurlencode( $payment_id ) );

		if ( is_wp_error( $payment ) ) {
			return;
		}

		$order = self::order_for_reference( $payment['external_reference'] ?? '' );

		/* Subscription charges also arrive as payments; those are handled as
		   authorized payments, against the subscription. */
		if ( ! $order ) {
			return;
		}

		$meta = Jws_Payment_Orders::meta( $order );

		if ( ! empty( $meta['recurring'] ) ) {
			return;
		}

		$status = (string) ( $payment['status'] ?? '' );

		if ( 'approved' === $status ) {
			Jws_Payment_Orders::mark_paid( $order->id );
		} elseif ( in_array( $status, array( 'refunded', 'charged_back' ), true ) ) {
			Jws_Payment_Orders::mark_refunded( $order->id );
		}
	}

	/** A subscription changed: authorised, paused or cancelled. */
	private static function on_subscription( $preapproval_id ) {

		$subscription = self::request( 'GET', 'preapproval/' . rawurlencode( $preapproval_id ) );

		if ( is_wp_error( $subscription ) || empty( $subscription['id'] ) ) {
			return;
		}

		$status = (string) ( $subscription['status'] ?? '' );
		$record = Jws_Payment_Subscriptions::find_by_ref( 'mercadopago', (string) $subscription['id'] );

		if ( 'authorized' === $status && ! $record ) {
			$order = self::order_for_reference( $subscription['external_reference'] ?? '' );

			if ( $order && (string) $order->gateway_ref === (string) $subscription['id'] ) {
				self::settle_subscription( $order );
			}
			return;
		}

		if ( ! $record ) {
			return;
		}

		/* Both spellings: payments write "cancelled", subscriptions "canceled". */
		if ( in_array( $status, array( 'canceled', 'cancelled' ), true ) ) {
			Jws_Payment_Subscriptions::set_status( 'mercadopago', $record->gateway_ref, Jws_Payment_Subscriptions::STATUS_CANCELED );
			static::end_membership( $record );
		} elseif ( 'paused' === $status ) {
			Jws_Payment_Subscriptions::set_status( 'mercadopago', $record->gateway_ref, Jws_Payment_Subscriptions::STATUS_PAST_DUE );
		} elseif ( 'authorized' === $status ) {
			Jws_Payment_Subscriptions::set_status( 'mercadopago', $record->gateway_ref, Jws_Payment_Subscriptions::STATUS_ACTIVE, static::to_gmt( $subscription['next_payment_date'] ?? '' ) );
		}
	}

	/**
	 * One scheduled charge of a subscription.
	 *
	 * The first one pays for the period the sign-up order already granted,
	 * so it is noted on that order rather than written as a renewal; every
	 * later approved one is a renewal row of its own.
	 *
	 * @return bool False only when another request holds this subscription.
	 */
	private static function on_authorized_payment( $authorized_id ) {

		$charge = self::request( 'GET', 'authorized_payments/' . rawurlencode( $authorized_id ) );

		if ( is_wp_error( $charge ) || empty( $charge['preapproval_id'] ) ) {
			return true;
		}

		$record = Jws_Payment_Subscriptions::find_by_ref( 'mercadopago', (string) $charge['preapproval_id'] );

		if ( ! $record || 'approved' !== ( $charge['payment']['status'] ?? '' ) ) {
			return true;
		}

		$payment_id = (string) ( $charge['payment']['id'] ?? $authorized_id );

		$ran = static::once(
			'mercadopago_charge_' . (int) $record->id,
			static function () use ( $record, $charge, $payment_id ) {

				$signup = Jws_Payment_Orders::find_by_ref( 'mercadopago', (string) $record->gateway_ref );
				$meta   = $signup ? Jws_Payment_Orders::meta( $signup ) : array();

				if ( $signup && empty( $meta['first_charge'] ) ) {
					Jws_Payment_Orders::merge_meta( $signup->id, array( 'first_charge' => $payment_id ) );
				} elseif ( ! $signup || $payment_id !== (string) $meta['first_charge'] ) {
					Jws_Payment_Orders::record_renewal( $record, 'mp-' . $payment_id, (float) ( $charge['transaction_amount'] ?? $record->amount ) );
				}
			}
		);

		if ( ! $ran ) {
			return false;
		}

		$live = self::request( 'GET', 'preapproval/' . rawurlencode( (string) $record->gateway_ref ) );

		Jws_Payment_Subscriptions::set_status(
			'mercadopago',
			$record->gateway_ref,
			Jws_Payment_Subscriptions::STATUS_ACTIVE,
			is_wp_error( $live ) ? null : static::to_gmt( $live['next_payment_date'] ?? '' )
		);

		return true;
	}

	/* ---------------------------------------------------------------------- */
	/* Cancelling                                                              */
	/* ---------------------------------------------------------------------- */

	/** `canceled`, one l, and final: a cancelled subscription cannot resume. */
	public static function cancel_subscription( $ref ) {
		return self::request( 'PUT', 'preapproval/' . rawurlencode( (string) $ref ), array( 'status' => 'canceled' ) );
	}
}
