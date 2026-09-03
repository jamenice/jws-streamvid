<?php

/**
 * Stripe, spoken directly over its REST API.
 *
 * No SDK: the plugin ships without a vendor tree, and everything needed here is
 * four endpoints and one HMAC. wp_remote_post() carries it, and Stripe's
 * form-encoded bracket syntax is exactly what http_build_query() emits.
 *
 * Three of the four buttons in the buy panel come through this class. Quick Pay
 * hands the shopper to Stripe's own hosted page; Apple Pay and Google Pay stay
 * on the site and confirm a PaymentIntent from the browser's payment sheet.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/drama
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Drama_Stripe {

	const API = 'https://api.stripe.com/v1/';

	/** Stripe's own API version, pinned so a platform upgrade cannot surprise us. */
	const API_VERSION = '2024-06-20';

	const META_CUSTOMER = 'svt_drama_stripe_customer';

	/** Query var the shopper comes back on. */
	const RETURN_VAR = 'jws_drama_return';

	public static function config() {
		return Jws_Drama_Settings::get( 'stripe' );
	}

	public static function ready() {

		$config = self::config();

		return ! empty( $config['enabled'] ) && ! empty( $config['secret'] );
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
			return new WP_Error( 'jws_drama_stripe_key', esc_html__( 'Stripe is not configured.', 'jws_streamvid' ) );
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
				'jws_drama_stripe_' . ( $decoded['error']['type'] ?? 'error' ),
				isset( $decoded['error']['message'] ) ? $decoded['error']['message'] : esc_html__( 'Stripe refused the request.', 'jws_streamvid' ),
				$decoded['error']
			);
		}

		if ( wp_remote_retrieve_response_code( $response ) >= 300 ) {
			return new WP_Error( 'jws_drama_stripe_http', esc_html__( 'Stripe could not be reached.', 'jws_streamvid' ) );
		}

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * A price in the unit Stripe charges in.
	 *
	 * Most currencies are billed in cents; a handful have no minor unit at all,
	 * and sending 500000 for ₫5,000 would overcharge a Vietnamese shopper by a
	 * hundred times.
	 */
	public static function minor_units( $amount, $currency ) {

		$zero_decimal = array( 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' );

		$currency = strtoupper( $currency );

		return in_array( $currency, $zero_decimal, true )
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

		$stored = get_user_meta( $user_id, self::META_CUSTOMER, true );

		if ( $stored ) {
			return $stored;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return new WP_Error( 'jws_drama_no_user', esc_html__( 'Unknown user.', 'jws_streamvid' ) );
		}

		$customer = self::request(
			'POST',
			'customers',
			array(
				'email'                   => $user->user_email,
				'name'                    => $user->display_name,
				'metadata[wp_user_id]'    => $user_id,
				'metadata[wp_user_login]' => $user->user_login,
				'_idempotency_key'        => 'jws-drama-customer-' . $user_id,
			)
		);

		if ( is_wp_error( $customer ) ) {
			return $customer;
		}

		update_user_meta( $user_id, self::META_CUSTOMER, $customer['id'] );

		return $customer['id'];
	}

	/* ---------------------------------------------------------------------- */
	/* Provisioned objects                                                     */
	/* ---------------------------------------------------------------------- */

	/** This account's slot for one fingerprint. See Jws_Drama_Settings. */
	private static function slot( $fingerprint ) {

		$config = self::config();

		return Jws_Drama_Settings::gateway_slot( 'stripe', 'live' === $config['mode'] ? 'live' : 'test', $fingerprint );
	}

	private static function refs( $fingerprint ) {
		return Jws_Drama_Settings::refs( self::slot( $fingerprint ) );
	}

	private static function remember( $fingerprint, $key, $value ) {
		Jws_Drama_Settings::remember( self::slot( $fingerprint ), $key, $value );
	}

	private static function forget( $fingerprint ) {
		Jws_Drama_Settings::forget( self::slot( $fingerprint ) );
	}

	/** Stripe's way of saying the id we cached is not there any more. */
	private static function is_missing_object( $error ) {

		return is_wp_error( $error ) && 0 === strpos( (string) $error->get_error_message(), 'No such ' );
	}

	/**
	 * The recurring Price behind a VIP plan, created the first time it is sold.
	 *
	 * This is why the admin screen has no "Stripe price id" box. The fingerprint
	 * covers the money and nothing else, so renaming a plan reuses the Price
	 * that is already charging people and repricing it makes a new one — which
	 * is exactly right, because everyone already subscribed keeps paying what
	 * they agreed to.
	 */
	public static function price_for_plan( array $plan ) {

		$refs = self::refs( $plan['fingerprint'] );

		if ( ! empty( $refs['stripe_price'] ) ) {
			return $refs['stripe_price'];
		}

		$currency = Jws_Drama_Settings::get( 'currency', 'USD' );

		$price = self::request(
			'POST',
			'prices',
			array(
				'currency'                  => strtolower( $currency ),
				'unit_amount'               => self::minor_units( $plan['price'], $currency ),
				'recurring[interval]'       => $plan['period'],
				'recurring[interval_count]' => (int) $plan['cycle'],
				'product_data[name]'        => $plan['name'],
				'metadata[fingerprint]'     => $plan['fingerprint'],
				'_idempotency_key'          => 'jws-drama-price-' . $plan['fingerprint'],
			)
		);

		if ( is_wp_error( $price ) ) {
			return $price;
		}

		self::remember( $plan['fingerprint'], 'stripe_price', $price['id'] );

		return $price['id'];
	}

	/**
	 * The one-off discount that makes the first cycle cheaper.
	 *
	 * Stripe has no "first period costs less" field on a Price, so the intro
	 * offer is a coupon with duration `once`, applied at checkout. A trial would
	 * be the other option, but a trial is free — it cannot charge $5.99.
	 */
	public static function coupon_for_plan( array $plan ) {

		if ( empty( $plan['has_intro'] ) ) {
			return '';
		}

		$refs = self::refs( $plan['fingerprint'] );

		if ( ! empty( $refs['stripe_coupon'] ) ) {
			return $refs['stripe_coupon'];
		}

		$currency = Jws_Drama_Settings::get( 'currency', 'USD' );
		$off      = self::minor_units( $plan['price'], $currency ) - self::minor_units( $plan['intro'], $currency );

		if ( $off <= 0 ) {
			return '';
		}

		$coupon = self::request(
			'POST',
			'coupons',
			array(
				'amount_off'            => $off,
				'currency'              => strtolower( $currency ),
				'duration'              => 'once',
				'name'                  => sprintf( '%s intro', $plan['name'] ),
				'metadata[fingerprint]' => $plan['fingerprint'],
				'_idempotency_key'      => 'jws-drama-coupon-' . $plan['fingerprint'],
			)
		);

		if ( is_wp_error( $coupon ) ) {
			return $coupon;
		}

		self::remember( $plan['fingerprint'], 'stripe_coupon', $coupon['id'] );

		return $coupon['id'];
	}

	/* ---------------------------------------------------------------------- */
	/* Buying                                                                  */
	/* ---------------------------------------------------------------------- */

	/** Where Stripe sends the shopper back to. */
	public static function return_url( $order_id ) {

		return add_query_arg(
			array(
				self::RETURN_VAR => (int) $order_id,
				'session_id'     => '{CHECKOUT_SESSION_ID}',
			),
			home_url( '/' )
		);
	}

	/**
	 * Quick Pay: a hosted Checkout page, one product, one price.
	 *
	 * price_data is built inline rather than from a stored Price, so a coin
	 * package is priced entirely from the admin screen and nothing has to be
	 * kept in step on Stripe's side.
	 *
	 * @return string|WP_Error The URL to send the shopper to.
	 */
	public static function checkout_session( $order, array $item, $kind, $retrying = false ) {

		$currency = strtoupper( $order->currency );
		$customer = self::customer_id( (int) $order->user_id );

		if ( is_wp_error( $customer ) ) {
			return $customer;
		}

		$body = array(
			'customer'                    => $customer,
			'client_reference_id'         => (int) $order->id,
			'success_url'                 => self::return_url( $order->id ),
			'cancel_url'                  => self::return_url( $order->id ) . '&cancelled=1',
			'metadata[order_id]'          => (int) $order->id,
			'metadata[user_id]'           => (int) $order->user_id,
			'_idempotency_key'            => 'jws-drama-session-' . $order->id,
		);

		if ( 'vip' === $kind ) {

			$price = self::price_for_plan( $item );

			if ( is_wp_error( $price ) ) {
				return $price;
			}

			$coupon = self::coupon_for_plan( $item );

			if ( is_wp_error( $coupon ) ) {
				return $coupon;
			}

			$body['mode']                                 = 'subscription';
			$body['line_items[0][price]']                 = $price;
			$body['line_items[0][quantity]']              = 1;
			$body['subscription_data[metadata][order_id]'] = (int) $order->id;
			$body['subscription_data[metadata][user_id]']  = (int) $order->user_id;
			$body['subscription_data[metadata][fingerprint]'] = $item['fingerprint'];

			if ( $coupon ) {
				$body['discounts[0][coupon]'] = $coupon;
			}
		} else {

			$body['mode']                                        = 'payment';
			$body['line_items[0][quantity]']                     = 1;
			$body['line_items[0][price_data][currency]']         = strtolower( $currency );
			$body['line_items[0][price_data][unit_amount]']      = self::minor_units( $order->amount, $currency );
			$body['line_items[0][price_data][product_data][name]'] = $order->label;
			$body['payment_intent_data[metadata][order_id]']     = (int) $order->id;
		}

		$session = self::request( 'POST', 'checkout/sessions', $body );

		if ( self::is_missing_object( $session ) && 'vip' === $kind && ! $retrying ) {
			self::forget( $item['fingerprint'] );

			return self::checkout_session( $order, $item, $kind, true );
		}

		if ( is_wp_error( $session ) ) {
			return $session;
		}

		Jws_Drama_Orders::attach_ref( $order->id, $session['id'] );

		return $session['url'];
	}

	/**
	 * Apple Pay and Google Pay: pay without leaving the page.
	 *
	 * The browser's sheet needs something to confirm against, so this hands
	 * back a client secret rather than a URL. Coins are a plain PaymentIntent;
	 * a VIP plan is a subscription created `default_incomplete`, which produces
	 * a first invoice whose PaymentIntent the sheet can confirm exactly the
	 * same way.
	 *
	 * @return array|WP_Error client_secret and what kind of confirm it needs.
	 */
	public static function wallet_intent( $order, array $item, $kind, $retrying = false ) {

		$currency = strtoupper( $order->currency );
		$customer = self::customer_id( (int) $order->user_id );

		if ( is_wp_error( $customer ) ) {
			return $customer;
		}

		if ( 'vip' === $kind ) {

			$price = self::price_for_plan( $item );

			if ( is_wp_error( $price ) ) {
				return $price;
			}

			$coupon = self::coupon_for_plan( $item );

			if ( is_wp_error( $coupon ) ) {
				return $coupon;
			}

			$body = array(
				'customer'                          => $customer,
				'items[0][price]'                   => $price,
				'payment_behavior'                  => 'default_incomplete',
				'payment_settings[save_default_payment_method]' => 'on_subscription',
				'expand[0]'                         => 'latest_invoice.payment_intent',
				'metadata[order_id]'                => (int) $order->id,
				'metadata[user_id]'                 => (int) $order->user_id,
				'metadata[fingerprint]'             => $item['fingerprint'],
				'_idempotency_key'                  => 'jws-drama-sub-' . $order->id,
			);

			if ( $coupon ) {
				$body['discounts[0][coupon]'] = $coupon;
			}

			$subscription = self::request( 'POST', 'subscriptions', $body );

			if ( self::is_missing_object( $subscription ) && ! $retrying ) {
				self::forget( $item['fingerprint'] );

				return self::wallet_intent( $order, $item, $kind, true );
			}

			if ( is_wp_error( $subscription ) ) {
				return $subscription;
			}

			$intent = $subscription['latest_invoice']['payment_intent'] ?? array();

			if ( empty( $intent['client_secret'] ) ) {
				return new WP_Error( 'jws_drama_stripe_intent', esc_html__( 'Stripe did not return anything to confirm.', 'jws_streamvid' ) );
			}

			Jws_Drama_Orders::attach_ref( $order->id, $intent['id'] );

			return array( 'client_secret' => $intent['client_secret'], 'subscription' => $subscription['id'] );
		}

		$intent = self::request(
			'POST',
			'payment_intents',
			array(
				'amount'                     => self::minor_units( $order->amount, $currency ),
				'currency'                   => strtolower( $currency ),
				'customer'                   => $customer,
				'description'                => $order->label,
				'metadata[order_id]'         => (int) $order->id,
				'metadata[user_id]'          => (int) $order->user_id,
				'automatic_payment_methods[enabled]' => 'true',
				'_idempotency_key'           => 'jws-drama-intent-' . $order->id,
			)
		);

		if ( is_wp_error( $intent ) ) {
			return $intent;
		}

		Jws_Drama_Orders::attach_ref( $order->id, $intent['id'] );

		return array( 'client_secret' => $intent['client_secret'] );
	}
}
