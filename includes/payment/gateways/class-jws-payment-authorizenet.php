<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * Authorize.net, over its JSON API (developer.authorize.net).
 *
 * Every sale goes through Accept Hosted: the server asks for a one-time token,
 * and the buyer's browser posts it to Authorize.net's payment form (through
 * Jws_Payment_Gateways::post_redirect(), because the checkout script can only
 * navigate). The way back carries no transaction data at all, so settling
 * finds the transaction by our invoice number.
 *
 * A renewing plan is the hosted payment for the first period, then an ARB
 * subscription created from a customer profile made out of that payment,
 * starting one billing period later — the same shape as the Mollie and
 * Paystack paths, so an intro price works.
 *
 * The API is JSON translated to XML: element order matters, and every body
 * here is written in the order the API reference lists.
 */
class Jws_Payment_Authorizenet extends Jws_Payment_Gateway {

	const LIVE_API     = 'https://api.authorize.net/xml/v1/request.api';
	const SANDBOX_API  = 'https://apitest.authorize.net/xml/v1/request.api';
	const LIVE_FORM    = 'https://accept.authorize.net/payment/payment';
	const SANDBOX_FORM = 'https://test.authorize.net/payment/payment';

	/** Transaction statuses that mean the card was charged. */
	const PAID_STATUSES = array( 'capturedPendingSettlement', 'settledSuccessfully' );

	/* ---------------------------------------------------------------------- */
	/* Declarations                                                            */
	/* ---------------------------------------------------------------------- */

	public static function id() {
		return 'authorizenet';
	}

	public static function label() {
		return 'Authorize.net';
	}

	public static function field_keys() {
		return array( 'api_login_id', 'transaction_key', 'signature_key' );
	}

	public static function fields() {
		return array(
			'api_login_id'    => array( esc_html__( 'API Login ID', 'jws_streamvid' ), 'text', '' ),
			'transaction_key' => array( esc_html__( 'Transaction Key', 'jws_streamvid' ), 'password', '' ),
			'signature_key'   => array( esc_html__( 'Signature Key', 'jws_streamvid' ), 'password', '' ),
		);
	}

	/** Payments are confirmed on return without it; renewals and refunds need it. */
	public static function optional_fields() {
		return array( 'signature_key' );
	}

	public static function method_note() {
		return esc_html__( "Sends the buyer to Authorize.net's payment form. Charges in your Authorize.net account's currency. Renewing plans are charged to the card used for the first payment.", 'jws_streamvid' );
	}

	/** The wordmark only exists as a raster on authorize.net. */
	public static function logo() {
		return 'authorizenet.png';
	}

	public static function webhook_help() {
		return esc_html__( 'Add this URL in the Authorize.net Merchant Interface under Account > Webhooks, and paste the Signature Key from Account > Settings > Security Settings > API Credentials and Keys above. Authorize.net does not deliver to localhost.', 'jws_streamvid' );
	}

	public static function webhook_events() {
		return array(
			'net.authorize.payment.authcapture.created',
			'net.authorize.payment.refund.created',
			'net.authorize.customer.subscription.cancelled',
			'net.authorize.customer.subscription.terminated',
			'net.authorize.customer.subscription.expired',
			'net.authorize.customer.subscription.suspended',
		);
	}

	/**
	 * The account charges in one currency, so the order's has to be it, and
	 * a renewing plan has to fit ARB's interval: 7 to 365 days, or 1 to 12
	 * months.
	 */
	public static function supports( array $item ) {

		$base = parent::supports( $item );

		if ( is_wp_error( $base ) ) {
			return $base;
		}

		$currencies = self::account_currencies();

		if ( $currencies && ! in_array( strtoupper( (string) $item['currency'] ), $currencies, true ) ) {
			return new WP_Error( 'jws_payment_authorizenet_currency', esc_html__( 'This Authorize.net account does not charge in this currency.', 'jws_streamvid' ) );
		}

		if ( ! empty( $item['recurring'] ) && ! self::interval( $item['period'], $item['cycle'] ) ) {
			return new WP_Error( 'jws_payment_authorizenet_interval', esc_html__( 'Authorize.net cannot bill at this interval.', 'jws_streamvid' ) );
		}

		return true;
	}

	/* ---------------------------------------------------------------------- */
	/* Transport                                                               */
	/* ---------------------------------------------------------------------- */

	/**
	 * One API call.
	 *
	 * Every method is a POST of { "<name>Request": { merchantAuthentication,
	 * … } } to the same URL. The JSON responses start with a byte order mark,
	 * which json_decode() refuses, so it is removed first. A 200 can still be
	 * a refusal: messages.resultCode is what says.
	 *
	 * @param string $name The request name without "Request".
	 * @param array  $body Its fields after merchantAuthentication, in order.
	 * @return array|WP_Error
	 */
	protected static function request( $name, array $body ) {

		$config = static::config();

		$payload = array(
			$name . 'Request' => array_merge(
				array(
					'merchantAuthentication' => array(
						'name'           => (string) $config['api_login_id'],
						'transactionKey' => (string) $config['transaction_key'],
					),
				),
				$body
			),
		);

		$response = wp_remote_post(
			'live' === static::mode() ? self::LIVE_API : self::SANDBOX_API,
			array(
				'timeout' => 30,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$raw     = preg_replace( '/^\xEF\xBB\xBF/', '', (string) wp_remote_retrieve_body( $response ) );
		$decoded = json_decode( $raw, true );

		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'jws_payment_authorizenet_http', esc_html__( 'Authorize.net could not be reached.', 'jws_streamvid' ) );
		}

		if ( 'Ok' !== ( $decoded['messages']['resultCode'] ?? '' ) ) {
			return new WP_Error( 'jws_payment_authorizenet_' . sanitize_key( (string) ( $decoded['messages']['message'][0]['code'] ?? 'error' ) ), self::error_message( $decoded ) ?: esc_html__( 'Authorize.net refused the request.', 'jws_streamvid' ), $decoded );
		}

		return $decoded;
	}

	protected static function error_message( $decoded ) {
		return is_array( $decoded ) ? (string) ( $decoded['messages']['message'][0]['text'] ?? '' ) : '';
	}

	/** An amount as Authorize.net wants it: a decimal string. */
	private static function amount( $value ) {
		return number_format( (float) $value, 2, '.', '' );
	}

	/** Our reference for an order: its order number, within the 20-character limit. */
	private static function invoice( $order ) {
		return substr( (string) $order->order_number, 0, 20 );
	}

	/**
	 * A billing period as ARB's interval.
	 *
	 * @return array|null array( length, unit ).
	 */
	private static function interval( $period, $cycle ) {

		$cycle = max( 1, (int) $cycle );

		switch ( strtolower( (string) $period ) ) {
			case 'day':
				return $cycle >= 7 && $cycle <= 365 ? array( $cycle, 'days' ) : null;
			case 'week':
				return 7 * $cycle <= 365 ? array( 7 * $cycle, 'days' ) : null;
			case 'month':
				return $cycle <= 12 ? array( $cycle, 'months' ) : null;
			case 'year':
				return 1 === $cycle ? array( 12, 'months' ) : null;
		}

		return null;
	}

	/**
	 * The currencies the account is set up for, or array() when unknown.
	 * Cached for a day per login and mode; a failed read is not cached.
	 */
	private static function account_currencies() {

		$config = static::config();

		if ( ! static::ready() ) {
			return array();
		}

		$key    = 'jws_payment_anet_cur_' . md5( static::mode() . $config['api_login_id'] );
		$cached = get_transient( $key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$details = self::request( 'getMerchantDetails', array() );

		if ( is_wp_error( $details ) ) {
			return array();
		}

		$currencies = array_values( array_filter( array_map( 'strtoupper', array_map( 'strval', (array) ( $details['currencies'] ?? array() ) ) ) ) );

		if ( $currencies ) {
			set_transient( $key, $currencies, DAY_IN_SECONDS );
		}

		return $currencies;
	}

	/* ---------------------------------------------------------------------- */
	/* Buying                                                                  */
	/* ---------------------------------------------------------------------- */

	public static function start( $order, array $item ) {

		$user = get_userdata( (int) $order->user_id );

		$transaction = array(
			'transactionType' => 'authCaptureTransaction',
			'amount'          => self::amount( $order->amount ),
			'order'           => array(
				'invoiceNumber' => self::invoice( $order ),
				'description'   => substr( (string) $order->item_label, 0, 255 ),
			),
		);

		if ( $user && is_email( $user->user_email ) ) {
			$transaction['customer'] = array( 'email' => $user->user_email );
		}

		$settings = array(
			array(
				'settingName'  => 'hostedPaymentReturnOptions',
				'settingValue' => wp_json_encode(
					array(
						'showReceipt'   => false,
						'url'           => static::return_url( $order ),
						'urlText'       => __( 'Continue', 'jws_streamvid' ),
						'cancelUrl'     => static::return_url( $order, true ),
						'cancelUrlText' => __( 'Cancel', 'jws_streamvid' ),
					)
				),
			),
			array(
				'settingName'  => 'hostedPaymentOrderOptions',
				'settingValue' => wp_json_encode( array( 'show' => true, 'merchantName' => get_bloginfo( 'name' ) ) ),
			),
		);

		/* A subscription is made from the payment's customer profile, whose
		   payment profile must carry a name — so the name is asked for. */
		if ( ! empty( $item['recurring'] ) ) {
			$settings[] = array(
				'settingName'  => 'hostedPaymentBillingAddressOptions',
				'settingValue' => wp_json_encode( array( 'show' => true, 'required' => true ) ),
			);
		}

		$created = self::request(
			'getHostedPaymentPage',
			array(
				'refId'                 => (string) (int) $order->id,
				'transactionRequest'    => $transaction,
				'hostedPaymentSettings' => array( 'setting' => $settings ),
			)
		);

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		if ( empty( $created['token'] ) ) {
			return new WP_Error( 'jws_payment_authorizenet_token', esc_html__( 'Authorize.net did not open a payment form.', 'jws_streamvid' ) );
		}

		Jws_Payment_Orders::attach_ref( $order->id, self::invoice( $order ) );

		return static::post_redirect_url(
			$order,
			'live' === static::mode() ? self::LIVE_FORM : self::SANDBOX_FORM,
			array( 'token' => (string) $created['token'] )
		);
	}

	/* ---------------------------------------------------------------------- */
	/* Settling                                                                */
	/* ---------------------------------------------------------------------- */

	/**
	 * Finds the order's transaction among the recent unsettled ones by our
	 * invoice number — the return carries nothing else — and settles from
	 * its details. A transaction already settled into a batch is no longer
	 * in that list; the authcapture webhook covers it.
	 */
	public static function settle( $order ) {

		if ( Jws_Payment_Orders::STATUS_COMPLETED === $order->status ) {
			return true;
		}

		$list = self::request(
			'getUnsettledTransactionList',
			array(
				'sorting' => array(
					'orderBy'         => 'submitTimeUTC',
					'orderDescending' => true,
				),
				'paging'  => array(
					'limit'  => 100,
					'offset' => 1,
				),
			)
		);

		if ( is_wp_error( $list ) ) {
			return false;
		}

		foreach ( (array) ( $list['transactions'] ?? array() ) as $row ) {

			if ( (string) ( $row['invoiceNumber'] ?? '' ) !== self::invoice( $order ) || empty( $row['transId'] ) ) {
				continue;
			}

			$details = self::transaction( (string) $row['transId'] );

			if ( $details && self::settle_transaction( $order, $details ) ) {
				return true;
			}
		}

		return false;
	}

	/** One transaction's details, or null. */
	private static function transaction( $trans_id ) {

		$found = self::request( 'getTransactionDetails', array( 'transId' => (string) $trans_id ) );

		return is_wp_error( $found ) ? null : (array) ( $found['transaction'] ?? array() );
	}

	/**
	 * Fulfils an order from a transaction: approved (responseCode 1),
	 * captured or settled, for this order's invoice and amount.
	 */
	private static function settle_transaction( $order, array $transaction ) {

		if ( (string) ( $transaction['order']['invoiceNumber'] ?? '' ) !== self::invoice( $order ) ) {
			return false;
		}

		if ( 1 !== (int) ( $transaction['responseCode'] ?? 0 )
			|| ! in_array( (string) ( $transaction['transactionStatus'] ?? '' ), self::PAID_STATUSES, true )
			|| self::amount( $transaction['authAmount'] ?? 0 ) !== self::amount( $order->amount ) ) {
			return false;
		}

		$trans_id = (string) ( $transaction['transId'] ?? '' );

		if ( ! static::subscription_decided( $order ) ) {

			$ran = static::once(
				'authorizenet_sub_' . (int) $order->id,
				static function () use ( $order, $trans_id ) {
					$fresh = Jws_Payment_Orders::find( $order->id );

					if ( $fresh && ! static::subscription_decided( $fresh ) ) {
						self::start_subscription( $fresh, $trans_id );
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
	 * Saves the paying card as a customer profile and subscribes it to ARB,
	 * starting one billing period from now. Anything refused leaves the
	 * purchase as one paid term.
	 */
	private static function start_subscription( $order, $trans_id ) {

		$meta     = Jws_Payment_Orders::meta( $order );
		$interval = self::interval( $meta['period'] ?? 'month', $meta['cycle'] ?? 1 );
		$user     = get_userdata( (int) $order->user_id );

		if ( ! $interval || '' === $trans_id ) {
			static::settle_as_one_term( $order );
			return;
		}

		$customer = array( 'merchantCustomerId' => substr( (string) (int) $order->user_id, 0, 20 ) );

		if ( $user && is_email( $user->user_email ) ) {
			$customer['email'] = $user->user_email;
		}

		$profile = self::request(
			'createCustomerProfileFromTransaction',
			array(
				'transId'  => $trans_id,
				'customer' => $customer,
			)
		);

		$profile_id = is_wp_error( $profile ) ? '' : (string) ( $profile['customerProfileId'] ?? '' );
		$payment_id = is_wp_error( $profile ) ? '' : (string) ( $profile['customerPaymentProfileIdList'][0] ?? '' );

		if ( '' === $profile_id || '' === $payment_id ) {
			static::settle_as_one_term( $order );
			return;
		}

		/* ARB reads start dates in Mountain Time; the first charge is one
		   billing period after the hosted payment that paid for this one. */
		$start = new DateTime( 'now', new DateTimeZone( 'America/Denver' ) );
		$start->modify( '+' . max( 1, (int) ( $meta['cycle'] ?? 1 ) ) . ' ' . ( $meta['period'] ?? 'month' ) );

		$created = self::request(
			'ARBCreateSubscription',
			array(
				'refId'        => (string) (int) $order->id,
				'subscription' => array(
					'name'            => substr( (string) $order->item_label, 0, 50 ),
					'paymentSchedule' => array(
						'interval'         => array(
							'length' => $interval[0],
							'unit'   => $interval[1],
						),
						'startDate'        => $start->format( 'Y-m-d' ),
						/* PMPro's billing limit counts the payments after the
						   first; 9999 is ARB's "no end". */
						'totalOccurrences' => ! empty( $meta['billing_limit'] ) ? (int) $meta['billing_limit'] : 9999,
					),
					'amount'          => self::amount( $meta['renew'] ?? $order->amount ),
					'order'           => array(
						'invoiceNumber' => self::invoice( $order ),
						'description'   => substr( (string) $order->item_label, 0, 255 ),
					),
					'profile'         => array(
						'customerProfileId'        => $profile_id,
						'customerPaymentProfileId' => $payment_id,
					),
				),
			)
		);

		if ( is_wp_error( $created ) || empty( $created['subscriptionId'] ) ) {
			static::settle_as_one_term( $order );
			return;
		}

		static::record_subscription(
			$order,
			(string) $created['subscriptionId'],
			Jws_Payment_Subscriptions::STATUS_ACTIVE,
			static::to_gmt( $start->getTimestamp() )
		);
	}

	/* ---------------------------------------------------------------------- */
	/* Webhooks                                                                */
	/* ---------------------------------------------------------------------- */

	/**
	 * One Authorize.net webhook.
	 *
	 * X-ANET-Signature is "sha512=" followed by the HMAC-SHA512 of the raw
	 * body under the Signature Key. The docs leave open whether the key is
	 * used as text or as the bytes its hex spells, and the hex case, so both
	 * readings are accepted and the case is ignored — each still needs the
	 * key. The transaction is then read back before anything is granted.
	 */
	public static function receive( WP_REST_Request $request ) {

		$config = static::config();
		$key    = (string) ( $config['signature_key'] ?? '' );
		$body   = (string) $request->get_body();
		$sent   = strtolower( (string) preg_replace( '/^sha512=/i', '', (string) $request->get_header( 'x_anet_signature' ) ) );

		if ( '' === $key || '' === $sent || ! static::ready() ) {
			return static::rejected();
		}

		$candidates = array( hash_hmac( 'sha512', $body, $key ) );

		if ( ctype_xdigit( $key ) && 0 === strlen( $key ) % 2 ) {
			$candidates[] = hash_hmac( 'sha512', $body, (string) hex2bin( $key ) );
		}

		$valid = false;

		foreach ( $candidates as $candidate ) {
			$valid = $valid || hash_equals( $candidate, $sent );
		}

		if ( ! $valid ) {
			return static::rejected();
		}

		$event   = json_decode( $body, true );
		$type    = is_array( $event ) ? (string) ( $event['eventType'] ?? '' ) : '';
		$payload = is_array( $event ) ? (array) ( $event['payload'] ?? array() ) : array();
		$id      = (string) ( $payload['id'] ?? '' );

		if ( '' === $id ) {
			return static::ok();
		}

		if ( 'net.authorize.payment.authcapture.created' === $type ) {
			self::on_charge( $id );
		} elseif ( 'net.authorize.payment.refund.created' === $type ) {
			self::on_refund( $id );
		} elseif ( 0 === strpos( $type, 'net.authorize.customer.subscription.' ) ) {
			self::on_subscription( substr( $type, strlen( 'net.authorize.customer.subscription.' ) ), $id );
		}

		return static::ok();
	}

	/** A charge: one of our hosted payments, or an ARB renewal. */
	private static function on_charge( $trans_id ) {

		$transaction = self::transaction( $trans_id );

		if ( ! $transaction ) {
			return;
		}

		$order = static::find_order( 0, (string) ( $transaction['order']['invoiceNumber'] ?? '' ) );

		/* The sign-up order, still waiting: settle it from this charge. */
		if ( $order && Jws_Payment_Orders::STATUS_PENDING === $order->status && empty( $transaction['subscription']['id'] ) ) {
			self::settle_transaction( $order, $transaction );
			return;
		}

		/* An ARB charge carries its subscription and its payment number. */
		$subscription_id = (string) ( $transaction['subscription']['id'] ?? '' );
		$record          = $subscription_id ? Jws_Payment_Subscriptions::find_by_ref( 'authorizenet', $subscription_id ) : null;

		if ( $record && 1 === (int) ( $transaction['responseCode'] ?? 0 ) ) {
			Jws_Payment_Subscriptions::set_status( 'authorizenet', $subscription_id, Jws_Payment_Subscriptions::STATUS_ACTIVE );
			Jws_Payment_Orders::record_renewal( $record, 'anet-' . $trans_id, (float) ( $transaction['authAmount'] ?? $record->amount ) );
		}
	}

	/** A refund: only one of the whole price takes back what was granted. */
	private static function on_refund( $refund_id ) {

		$refund = self::transaction( $refund_id );

		if ( ! $refund ) {
			return;
		}

		$invoice = (string) ( $refund['order']['invoiceNumber'] ?? '' );

		/* A refund may not repeat the invoice; the charge it refunds does. */
		if ( '' === $invoice && ! empty( $refund['refTransId'] ) ) {
			$charge  = self::transaction( (string) $refund['refTransId'] );
			$invoice = $charge ? (string) ( $charge['order']['invoiceNumber'] ?? '' ) : '';
		}

		$order = static::find_order( 0, $invoice );

		if ( $order && (float) ( $refund['authAmount'] ?? $refund['settleAmount'] ?? 0 ) >= (float) $order->amount ) {
			Jws_Payment_Orders::mark_refunded( $order->id );
		}
	}

	/** A subscription changed state at Authorize.net. */
	private static function on_subscription( $change, $subscription_id ) {

		$record = Jws_Payment_Subscriptions::find_by_ref( 'authorizenet', $subscription_id );

		if ( ! $record ) {
			return;
		}

		if ( 'suspended' === $change ) {
			Jws_Payment_Subscriptions::set_status( 'authorizenet', $subscription_id, Jws_Payment_Subscriptions::STATUS_PAST_DUE );
			return;
		}

		if ( in_array( $change, array( 'cancelled', 'terminated', 'expired' ), true ) ) {
			Jws_Payment_Subscriptions::set_status( 'authorizenet', $subscription_id, Jws_Payment_Subscriptions::STATUS_CANCELED );
			static::end_membership( $record );
		}
	}

	/* ---------------------------------------------------------------------- */
	/* Cancelling                                                              */
	/* ---------------------------------------------------------------------- */

	public static function cancel_subscription( $ref ) {
		return self::request(
			'ARBCancelSubscription',
			array(
				'refId'          => 'cancel',
				'subscriptionId' => (string) $ref,
			)
		);
	}
}
