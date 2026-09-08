<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * Stripe, spoken directly over its REST API.
 *
 * No SDK: the plugin ships without a vendor tree, and everything needed here
 * is a handful of endpoints and one HMAC. wp_remote_post() carries it, and
 * Stripe's form-encoded bracket syntax is exactly what http_build_query()
 * emits.
 *
 * Three of the checkout's four buttons come through this class: the on-page
 * card form and the Apple/Google Pay sheets confirm a PaymentIntent without
 * leaving the site, and the hosted-page button hands the buyer to Stripe. All
 * three end at Jws_Payment_Orders::mark_paid().
 */
class Jws_Payment_Stripe {

	const API = 'https://api.stripe.com/v1/';

	/** Stripe's own API version, pinned so a platform upgrade cannot surprise us. */
	const API_VERSION = '2024-06-20';

	const META_CUSTOMER = 'jws_payment_stripe_customer';

	/** Query var the buyer comes back on. */
	const RETURN_VAR = 'jws_payment_return';

	public static function config() {
		return Jws_Payment_Settings::get( 'stripe' );
	}

	public static function ready() {
		return Jws_Payment_Settings::gateway_ready( 'stripe' );
	}

	public static function mode() {
		$config = self::config();
		return 'live' === $config['mode'] ? 'live' : 'test';
	}

	/* ---------------------------------------------------------------------- */
	/* Transport                                                               */
	/* ---------------------------------------------------------------------- */

	/**
	 * One call to Stripe.
	 *
	 * @return array|WP_Error Decoded body, or the error Stripe described.
	 */
	public static function request( $method, $path, array $body = array() ) {

		$config = self::config();

		if ( empty( $config['secret'] ) ) {
			return new WP_Error( 'jws_payment_stripe_key', esc_html__( 'Stripe is not configured.', 'jws_streamvid' ) );
		}

		$args = array(
			'method'  => strtoupper( $method ),
			'timeout' => 30,
			'headers' => array(
				'Authorization'  => 'Basic ' . base64_encode( $config['secret'] . ':' ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
				'Stripe-Version' => self::API_VERSION,
				'Content-Type'   => 'application/x-www-form-urlencoded',
			),
		);

		$url = self::API . ltrim( $path, '/' );

		if ( 'GET' === $args['method'] ) {
			$url = $body ? add_query_arg( $body, $url ) : $url;
		} else {
			/*
			 * Idempotency-Key is what makes a retried create safe. Without it a
			 * timeout that actually succeeded, retried, charges twice.
			 */
			if ( isset( $body['_idempotency_key'] ) ) {
				$args['headers']['Idempotency-Key'] = $body['_idempotency_key'];
				unset( $body['_idempotency_key'] );
			}

			$args['body'] = http_build_query( $body, '', '&', PHP_QUERY_RFC3986 );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $decoded['error'] ) ) {
			return new WP_Error(
				'jws_payment_stripe_' . ( $decoded['error']['type'] ?? 'error' ),
				isset( $decoded['error']['message'] ) ? $decoded['error']['message'] : esc_html__( 'Stripe refused the request.', 'jws_streamvid' ),
				$decoded['error']
			);
		}

		if ( wp_remote_retrieve_response_code( $response ) >= 300 ) {
			return new WP_Error( 'jws_payment_stripe_http', esc_html__( 'Stripe could not be reached.', 'jws_streamvid' ) );
		}

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * A price in the unit Stripe charges in.
	 *
	 * Most currencies are billed in cents; a handful have no minor unit at all,
	 * and sending 500000 for ₫5,000 would overcharge a Vietnamese buyer by a
	 * hundred times.
	 */
	public static function minor_units( $amount, $currency ) {

		$zero_decimal = array( 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' );

		return in_array( strtoupper( $currency ), $zero_decimal, true )
			? (int) round( (float) $amount )
			: (int) round( (float) $amount * 100 );
	}

	/* ---------------------------------------------------------------------- */
	/* Customers                                                               */
	/* ---------------------------------------------------------------------- */

	/**
	 * The Stripe customer for a WordPress user, made on first need.
	 *
	 * Subscriptions cannot exist without one, and reusing it means a returning
	 * buyer's saved cards and their whole billing history stay on one record.
	 */
	public static function customer_id( $user_id ) {

		$key    = self::META_CUSTOMER . '_' . self::mode();
		$stored = get_user_meta( $user_id, $key, true );

		if ( $stored ) {
			return $stored;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return new WP_Error( 'jws_payment_no_user', esc_html__( 'Unknown user.', 'jws_streamvid' ) );
		}

		$customer = self::request(
			'POST',
			'customers',
			array(
				'email'                   => $user->user_email,
				'name'                    => $user->display_name,
				'metadata[wp_user_id]'    => $user_id,
				'metadata[wp_user_login]' => $user->user_login,
				'_idempotency_key'        => 'jws-payment-customer-' . self::mode() . '-' . $user_id,
			)
		);

		if ( is_wp_error( $customer ) ) {
			return $customer;
		}

		update_user_meta( $user_id, $key, $customer['id'] );

		return $customer['id'];
	}

	/* ---------------------------------------------------------------------- */
	/* Provisioned objects                                                     */
	/* ---------------------------------------------------------------------- */

	private static function slot( $fingerprint ) {
		return Jws_Payment_Settings::gateway_slot( 'stripe', self::mode(), $fingerprint );
	}

	private static function refs( $fingerprint ) {
		return Jws_Payment_Settings::refs( self::slot( $fingerprint ) );
	}

	private static function remember( $fingerprint, $key, $value ) {
		Jws_Payment_Settings::remember( self::slot( $fingerprint ), $key, $value );
	}

	private static function forget( $fingerprint ) {
		Jws_Payment_Settings::forget( self::slot( $fingerprint ) );
	}

	/** Stripe's way of saying the id we cached is not there any more. */
	private static function is_missing_object( $error ) {
		return is_wp_error( $error ) && 0 === strpos( (string) $error->get_error_message(), 'No such ' );
	}

	/**
	 * The recurring Price behind a plan, created the first time it is sold.
	 *
	 * This is why the settings screen has no "Stripe price id" box. The
	 * fingerprint covers the money and nothing else, so renaming a PMPro level
	 * reuses the Price that is already charging people, and repricing it makes
	 * a new one — which is exactly right, because everyone already subscribed
	 * keeps paying what they agreed to.
	 *
	 * @return string|WP_Error
	 */
	public static function price_for_item( array $item ) {

		$refs = self::refs( $item['fingerprint'] );

		if ( ! empty( $refs['stripe_price'] ) ) {
			return $refs['stripe_price'];
		}

		$currency = $item['currency'];

		$price = self::request(
			'POST',
			'prices',
			array(
				'currency'                  => strtolower( $currency ),
				'unit_amount'               => self::minor_units( $item['renew'], $currency ),
				'recurring[interval]'       => $item['period'],
				'recurring[interval_count]' => (int) $item['cycle'],
				'product_data[name]'        => $item['label'],
				'metadata[fingerprint]'     => $item['fingerprint'],
				'_idempotency_key'          => 'jws-payment-price-' . $item['fingerprint'],
			)
		);

		if ( is_wp_error( $price ) ) {
			return $price;
		}

		self::remember( $item['fingerprint'], 'stripe_price', $price['id'] );

		return $price['id'];
	}

	/**
	 * The one-off discount that makes the first cycle cheaper.
	 *
	 * PMPro levels routinely charge less up front than they renew at, and
	 * Stripe has no "first period costs less" field on a Price. A trial would
	 * be the other option, but a trial is free — it cannot charge $5.99.
	 *
	 * @return string|WP_Error Coupon id, or '' when the plan has no intro.
	 */
	public static function coupon_for_item( array $item ) {

		$currency = $item['currency'];
		$off      = self::minor_units( $item['renew'], $currency ) - self::minor_units( $item['amount'], $currency );

		if ( $off <= 0 ) {
			return '';
		}

		$refs = self::refs( $item['fingerprint'] );

		if ( ! empty( $refs['stripe_coupon'] ) ) {
			return $refs['stripe_coupon'];
		}

		$coupon = self::request(
			'POST',
			'coupons',
			array(
				'amount_off'            => $off,
				'currency'              => strtolower( $currency ),
				'duration'              => 'once',
				'name'                  => substr( $item['label'] . ' intro', 0, 40 ),
				'metadata[fingerprint]' => $item['fingerprint'],
				'_idempotency_key'      => 'jws-payment-coupon-' . $item['fingerprint'],
			)
		);

		if ( is_wp_error( $coupon ) ) {
			return $coupon;
		}

		self::remember( $item['fingerprint'], 'stripe_coupon', $coupon['id'] );

		return $coupon['id'];
	}

	/* ---------------------------------------------------------------------- */
	/* Buying                                                                  */
	/* ---------------------------------------------------------------------- */

	/** Where Stripe sends the buyer back to. */
	public static function return_url( $order, $cancelled = false ) {

		$args = array(
			self::RETURN_VAR => (string) $order->source_ref,
			'session_id'     => '{CHECKOUT_SESSION_ID}',
		);

		if ( $cancelled ) {
			$args['cancelled'] = 1;
		}

		/* App mode rides along, or the buyer comes back out of the WebView's
		   chrome-free view into a full website inside the app. Folded into the
		   args rather than appended afterwards: a second add_query_arg() over a
		   finished URL re-encodes what is already in it, and Stripe only
		   substitutes {CHECKOUT_SESSION_ID} when it is left literal. */
		if ( Jws_Payment_Checkout::order_is_app( $order ) ) {
			$args[ Jws_Payment_Checkout::APP_VAR ] = '1';
		}

		return add_query_arg( $args, Jws_Payment_Checkout::page_url() );
	}

	/**
	 * The hosted-page button: Stripe's own Checkout, one line, one price.
	 *
	 * price_data is built inline for one-off sales rather than from a stored
	 * Price, so a coin package or a rental is priced entirely from this site
	 * and nothing has to be kept in step on Stripe's side. A recurring plan
	 * cannot work that way — a subscription needs a real Price — which is what
	 * price_for_item() provisions.
	 *
	 * @return string|WP_Error The URL to send the buyer to.
	 */
	public static function checkout_session( $order, array $item, $retrying = false ) {

		$currency = strtoupper( $order->currency );
		$customer = self::customer_id( (int) $order->user_id );

		if ( is_wp_error( $customer ) ) {
			return $customer;
		}

		$body = array(
			'customer'            => $customer,
			'client_reference_id' => (int) $order->id,
			'success_url'         => self::return_url( $order ),
			'cancel_url'          => self::return_url( $order, true ),
			'metadata[order_id]'  => (int) $order->id,
			'metadata[user_id]'   => (int) $order->user_id,
			'_idempotency_key'    => 'jws-payment-session-' . $order->id,
		);

		if ( ! empty( $item['recurring'] ) ) {

			$price = self::price_for_item( $item );

			if ( is_wp_error( $price ) ) {
				return $price;
			}

			$coupon = self::coupon_for_item( $item );

			if ( is_wp_error( $coupon ) ) {
				return $coupon;
			}

			$body['mode']                                     = 'subscription';
			$body['line_items[0][price]']                     = $price;
			$body['line_items[0][quantity]']                  = 1;
			$body['subscription_data[metadata][order_id]']     = (int) $order->id;
			$body['subscription_data[metadata][user_id]']      = (int) $order->user_id;
			$body['subscription_data[metadata][fingerprint]']  = $item['fingerprint'];

			if ( $coupon ) {
				$body['discounts[0][coupon]'] = $coupon;
			}
		} else {

			$body['mode']                                          = 'payment';
			$body['line_items[0][quantity]']                       = 1;
			$body['line_items[0][price_data][currency]']           = strtolower( $currency );
			$body['line_items[0][price_data][unit_amount]']        = self::minor_units( $order->amount, $currency );
			$body['line_items[0][price_data][product_data][name]'] = $order->item_label;
			$body['payment_intent_data[metadata][order_id]']       = (int) $order->id;
		}

		$session = self::request( 'POST', 'checkout/sessions', $body );

		/* A cached Price that no longer resolves is one forget away from
		   working again. */
		if ( self::is_missing_object( $session ) && ! empty( $item['recurring'] ) && ! $retrying ) {
			self::forget( $item['fingerprint'] );

			return self::checkout_session( $order, $item, true );
		}

		if ( is_wp_error( $session ) ) {
			return $session;
		}

		Jws_Payment_Orders::attach_ref( $order->id, $session['id'] );

		return $session['url'];
	}

	/**
	 * The on-page card form and the Apple/Google Pay sheets: pay without
	 * leaving the site.
	 *
	 * Both need something to confirm against, so this hands back a client
	 * secret rather than a URL. A one-off sale is a plain PaymentIntent; a
	 * recurring plan is a subscription created `default_incomplete`, which
	 * produces a first invoice whose PaymentIntent confirms exactly the same
	 * way — so the browser only ever deals with one kind of object.
	 *
	 * @return array|WP_Error client_secret, and the subscription id if any.
	 */
	public static function intent( $order, array $item, $retrying = false ) {

		$currency = strtoupper( $order->currency );
		$customer = self::customer_id( (int) $order->user_id );

		if ( is_wp_error( $customer ) ) {
			return $customer;
		}

		if ( ! empty( $item['recurring'] ) ) {

			$price = self::price_for_item( $item );

			if ( is_wp_error( $price ) ) {
				return $price;
			}

			$coupon = self::coupon_for_item( $item );

			if ( is_wp_error( $coupon ) ) {
				return $coupon;
			}

			$body = array(
				'customer'              => $customer,
				'items[0][price]'       => $price,
				'payment_behavior'      => 'default_incomplete',
				'payment_settings[save_default_payment_method]' => 'on_subscription',
				'expand[0]'             => 'latest_invoice.payment_intent',
				'metadata[order_id]'    => (int) $order->id,
				'metadata[user_id]'     => (int) $order->user_id,
				'metadata[fingerprint]' => $item['fingerprint'],
				'_idempotency_key'      => 'jws-payment-sub-' . $order->id,
			);

			if ( $coupon ) {
				$body['discounts[0][coupon]'] = $coupon;
			}

			$subscription = self::request( 'POST', 'subscriptions', $body );

			if ( self::is_missing_object( $subscription ) && ! $retrying ) {
				self::forget( $item['fingerprint'] );

				return self::intent( $order, $item, true );
			}

			if ( is_wp_error( $subscription ) ) {
				return $subscription;
			}

			$payment_intent = $subscription['latest_invoice']['payment_intent'] ?? array();

			if ( empty( $payment_intent['client_secret'] ) ) {
				return new WP_Error( 'jws_payment_stripe_intent', esc_html__( 'Stripe did not return anything to confirm.', 'jws_streamvid' ) );
			}

			/*
			 * The subscription is recorded now, not when the webhook lands: if
			 * the buyer's card is charged and the webhook is slow, the
			 * cancellation link on their account page must already work.
			 */
			$subscription_id = Jws_Payment_Subscriptions::upsert(
				array(
					'user_id'     => (int) $order->user_id,
					'type'        => (string) $order->type,
					'item_id'     => (int) $order->item_id,
					'label'       => (string) $order->item_label,
					'fingerprint' => $item['fingerprint'],
					'gateway'     => 'stripe',
					'gateway_ref' => $subscription['id'],
					'amount'      => $item['renew'],
					'currency'    => $currency,
					'status'      => Jws_Payment_Subscriptions::STATUS_ACTIVE,
					'current_period_end' => self::period_end( $subscription ),
				)
			);

			Jws_Payment_Orders::attach_subscription( $order->id, $subscription_id );
			Jws_Payment_Orders::attach_ref( $order->id, $payment_intent['id'] );

			return array(
				'client_secret' => $payment_intent['client_secret'],
				'subscription'  => $subscription['id'],
			);
		}

		$payment_intent = self::request(
			'POST',
			'payment_intents',
			array(
				'amount'                             => self::minor_units( $order->amount, $currency ),
				'currency'                           => strtolower( $currency ),
				'customer'                           => $customer,
				'description'                        => $order->item_label,
				'metadata[order_id]'                 => (int) $order->id,
				'metadata[user_id]'                  => (int) $order->user_id,
				'automatic_payment_methods[enabled]' => 'true',
				'_idempotency_key'                   => 'jws-payment-intent-' . $order->id,
			)
		);

		if ( is_wp_error( $payment_intent ) ) {
			return $payment_intent;
		}

		Jws_Payment_Orders::attach_ref( $order->id, $payment_intent['id'] );

		return array( 'client_secret' => $payment_intent['client_secret'] );
	}

	/* ---------------------------------------------------------------------- */
	/* Settling                                                                */
	/* ---------------------------------------------------------------------- */

	/**
	 * Asks Stripe whether one order is actually paid, and fulfils it if so.
	 *
	 * Used by the return handler and by the browser right after it confirms a
	 * card, so the buyer is not left staring at "pending" while a webhook makes
	 * its way over. It never trusts the request — it asks Stripe about the
	 * object the order is tied to — and it ends at mark_paid(), which can only
	 * fire once whichever path gets there first.
	 *
	 * @return bool Whether the order is paid (by this call or already).
	 */
	public static function settle( $order ) {

		if ( Jws_Payment_Orders::STATUS_COMPLETED === $order->status ) {
			return true;
		}

		$ref = (string) $order->gateway_ref;

		if ( ! $ref ) {
			return false;
		}

		if ( 0 === strpos( $ref, 'cs_' ) ) {

			$session = self::request( 'GET', 'checkout/sessions/' . $ref, array( 'expand[0]' => 'subscription' ) );

			if ( is_wp_error( $session ) || empty( $session['payment_status'] ) || 'paid' !== $session['payment_status'] ) {
				return false;
			}

			if ( ! empty( $session['subscription'] ) ) {
				self::sync_subscription( $session['subscription'], $order );
			}

			Jws_Payment_Orders::mark_paid( $order->id );

			return true;
		}

		if ( 0 === strpos( $ref, 'pi_' ) ) {

			$payment_intent = self::request( 'GET', 'payment_intents/' . $ref );

			if ( is_wp_error( $payment_intent ) || empty( $payment_intent['status'] ) || 'succeeded' !== $payment_intent['status'] ) {
				return false;
			}

			Jws_Payment_Orders::mark_paid( $order->id );

			return true;
		}

		return false;
	}

	/**
	 * Records what Stripe says about a subscription against one order.
	 *
	 * @param array|string $subscription The expanded object, or just its id.
	 */
	public static function sync_subscription( $subscription, $order ) {

		if ( is_string( $subscription ) ) {
			$subscription = self::request( 'GET', 'subscriptions/' . $subscription );
		}

		if ( is_wp_error( $subscription ) || empty( $subscription['id'] ) ) {
			return 0;
		}

		$status = in_array( $subscription['status'], array( 'active', 'trialing' ), true )
			? Jws_Payment_Subscriptions::STATUS_ACTIVE
			: ( 'past_due' === $subscription['status'] ? Jws_Payment_Subscriptions::STATUS_PAST_DUE : Jws_Payment_Subscriptions::STATUS_CANCELED );

		$subscription_id = Jws_Payment_Subscriptions::upsert(
			array(
				'user_id'            => (int) $order->user_id,
				'type'               => (string) $order->type,
				'item_id'            => (int) $order->item_id,
				'label'              => (string) $order->item_label,
				'fingerprint'        => (string) ( $subscription['metadata']['fingerprint'] ?? '' ),
				'gateway'            => 'stripe',
				'gateway_ref'        => $subscription['id'],
				'amount'             => isset( $subscription['items']['data'][0]['price']['unit_amount'] )
					? self::from_minor_units( $subscription['items']['data'][0]['price']['unit_amount'], $order->currency )
					: $order->amount,
				'currency'           => (string) $order->currency,
				'status'             => $status,
				'current_period_end' => self::period_end( $subscription ),
			)
		);

		if ( $subscription_id ) {
			Jws_Payment_Orders::attach_subscription( $order->id, $subscription_id );
		}

		return $subscription_id;
	}

	/**
	 * When the paid period of a subscription runs out, as a MySQL datetime.
	 *
	 * Stripe moved this from the subscription onto its line items in a later API
	 * version, so both places are read rather than trusting the one this plugin
	 * happens to pin.
	 *
	 * @return string|null
	 */
	public static function period_end( array $subscription ) {

		$end = ! empty( $subscription['current_period_end'] )
			? (int) $subscription['current_period_end']
			: (int) ( $subscription['items']['data'][0]['current_period_end'] ?? 0 );

		return $end ? gmdate( 'Y-m-d H:i:s', $end ) : null;
	}

	/** The inverse of minor_units(), for reading an amount back off Stripe. */
	public static function from_minor_units( $amount, $currency ) {

		$zero_decimal = array( 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' );

		return in_array( strtoupper( $currency ), $zero_decimal, true )
			? (float) $amount
			: (float) $amount / 100;
	}

	/**
	 * Stops billing a subscription at the end of nothing — immediately.
	 *
	 * PMPro has already ended the level by the time this runs, so leaving the
	 * period to run out would charge for a month the member cannot use.
	 */
	public static function cancel_subscription( $subscription_id ) {

		$result = self::request( 'DELETE', 'subscriptions/' . rawurlencode( $subscription_id ) );

		/* Already gone is the outcome we wanted. */
		if ( self::is_missing_object( $result ) ) {
			return array();
		}

		return $result;
	}
}
