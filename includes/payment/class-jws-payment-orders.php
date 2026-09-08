<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * The lifecycle of an order this checkout took itself.
 *
 * A row is written before the buyer is sent anywhere, and fulfilled when the
 * gateway says the money arrived — never on the redirect back, which a buyer
 * can fake by editing a URL and which never happens at all if they close the
 * tab. Both the webhook and the return handler end here, and only one of them
 * can win.
 */
class Jws_Payment_Orders {

	const SOURCE = 'jws';

	const STATUS_PENDING   = 'pending';
	const STATUS_COMPLETED = 'completed';
	const STATUS_FAILED    = 'failed';
	const STATUS_REFUNDED  = 'refunded';

	/**
	 * Characters an order number is built from.
	 *
	 * No 0/O and no 1/I/L: an order number exists to be read off a screen and
	 * typed into a support form, or said out loud down a phone line, and those
	 * pairs are where that goes wrong.
	 */
	const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

	/**
	 * A fresh order number.
	 *
	 * Random rather than sequential, so the number on a receipt says nothing
	 * about how many orders the site has taken, and so one customer's number
	 * cannot be nudged by one to find the next customer's.
	 *
	 * Uniqueness is enforced here rather than by a UNIQUE index — see the note
	 * in Jws_Payment_Ledger::install() for why the index cannot be added
	 * safely. Eight characters from a 32-symbol alphabet is a trillion
	 * combinations; the loop is for the case that cannot be reasoned away.
	 */
	public static function generate_order_number() {

		global $wpdb;

		$table  = Jws_Payment_Ledger::table();
		$prefix = (string) apply_filters( 'jws_payment_order_number_prefix', 'SV' );

		for ( $attempt = 0; $attempt < 10; $attempt++ ) {

			$body = '';

			for ( $i = 0; $i < 8; $i++ ) {
				$body .= self::ALPHABET[ wp_rand( 0, strlen( self::ALPHABET ) - 1 ) ];
			}

			/* Grouped in fours: the same reason card numbers and licence keys
			   are — an unbroken run of eight is read back wrong. */
			$number = $prefix . '-' . substr( $body, 0, 4 ) . '-' . substr( $body, 4, 4 );

			$taken = $wpdb->get_var(
				$wpdb->prepare( "SELECT id FROM {$table} WHERE order_number = %s LIMIT 1", $number )
			);

			if ( ! $taken ) {
				return $number;
			}
		}

		/* Ten collisions in a row is not chance, it is a broken random source.
		   Falling back on something guaranteed unique beats returning ''. */
		return $prefix . '-' . strtoupper( substr( md5( uniqid( '', true ) ), 0, 9 ) );
	}

	/** The order a customer-facing number belongs to. */
	public static function find_by_number( $number ) {

		global $wpdb;

		if ( ! $number ) {
			return null;
		}

		$table = Jws_Payment_Ledger::table();

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE order_number = %s LIMIT 1", (string) $number )
		);
	}

	/**
	 * Records an intent to buy, before anyone is sent anywhere.
	 *
	 * The row carries a copy of what was bought rather than a pointer to it, so
	 * a webhook can arrive before this request has even returned and still find
	 * everything it needs to fulfil.
	 *
	 * @param array $args user_id, type, item_id, item_label, amount, currency,
	 *                    gateway, method, meta.
	 * @return int Order id, or 0.
	 */
	public static function create( array $args ) {

		global $wpdb;

		$user_id = (int) $args['user_id'];

		if ( ! $user_id ) {
			return 0;
		}

		$row = array(
			'user_id'         => $user_id,
			'order_number'    => self::generate_order_number(),
			'type'            => substr( (string) $args['type'], 0, 20 ),
			'source'          => self::SOURCE,
			/* Random rather than the row id, which does not exist yet, and
			   unguessable so a return URL cannot be walked. */
			'source_ref'      => wp_generate_password( 32, false ),
			'item_id'         => isset( $args['item_id'] ) ? (int) $args['item_id'] : 0,
			'item_label'      => substr( (string) ( $args['item_label'] ?? '' ), 0, 191 ),
			'amount'          => number_format( (float) ( $args['amount'] ?? 0 ), 2, '.', '' ),
			'currency'        => strtoupper( substr( (string) ( $args['currency'] ?? '' ), 0, 3 ) ),
			'gateway'         => substr( (string) ( $args['gateway'] ?? '' ), 0, 20 ),
			'gateway_ref'     => '',
			'method'          => substr( (string) ( $args['method'] ?? '' ), 0, 20 ),
			'subscription_id' => 0,
			'meta'            => wp_json_encode( isset( $args['meta'] ) ? (array) $args['meta'] : array() ),
			'status'          => self::STATUS_PENDING,
			'created_at'      => current_time( 'mysql' ),
		);

		$ok = $wpdb->insert( Jws_Payment_Ledger::table(), $row );

		return $ok ? (int) $wpdb->insert_id : 0;
	}

	public static function find( $order_id ) {

		global $wpdb;

		$table = Jws_Payment_Ledger::table();

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $order_id ) );
	}

	public static function find_by_ref( $gateway, $ref ) {

		global $wpdb;

		if ( ! $ref ) {
			return null;
		}

		$table = Jws_Payment_Ledger::table();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE source = %s AND gateway = %s AND gateway_ref = %s ORDER BY id DESC LIMIT 1",
				self::SOURCE,
				$gateway,
				$ref
			)
		);
	}

	/**
	 * Ties the order to the object the gateway just made for it.
	 *
	 * Written straight after the session or intent is created, so a webhook
	 * that arrives before the buyer has even finished paying still finds
	 * something to fulfil.
	 */
	public static function attach_ref( $order_id, $ref ) {

		global $wpdb;

		return (bool) $wpdb->update(
			Jws_Payment_Ledger::table(),
			array( 'gateway_ref' => substr( (string) $ref, 0, 191 ) ),
			array( 'id' => (int) $order_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	public static function attach_subscription( $order_id, $subscription_id ) {

		global $wpdb;

		return (bool) $wpdb->update(
			Jws_Payment_Ledger::table(),
			array( 'subscription_id' => (int) $subscription_id ),
			array( 'id' => (int) $order_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/** What was bought, as the array that was handed to create(). */
	public static function meta( $order ) {

		if ( empty( $order->meta ) ) {
			return array();
		}

		$decoded = json_decode( $order->meta, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Fulfils an order exactly once.
	 *
	 * The claim is the UPDATE itself: only a row still sitting at `pending` can
	 * be moved to `completed`, and only the caller whose UPDATE actually
	 * changed a row goes on to grant anything. Two webhooks racing — Stripe
	 * sends the same payment as more than one event, and retries each until it
	 * gets a 2xx — cannot both win that, and neither can a webhook racing the
	 * buyer's own return to the site.
	 *
	 * @return bool True if this call is the one that fulfilled it.
	 */
	public static function mark_paid( $order_id ) {

		global $wpdb;

		$claimed = $wpdb->update(
			Jws_Payment_Ledger::table(),
			array( 'status' => self::STATUS_COMPLETED, 'paid_at' => current_time( 'mysql' ) ),
			array( 'id' => (int) $order_id, 'status' => self::STATUS_PENDING ),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);

		if ( ! $claimed ) {
			return false;
		}

		$order = self::find( $order_id );

		if ( ! $order ) {
			return false;
		}

		Jws_Payment_Fulfillment::grant( $order );

		/**
		 * Fires once, after an order is paid and whatever it bought has been
		 * granted.
		 *
		 * @param object $order
		 */
		do_action( 'jws/payment/order_paid', $order );

		return true;
	}

	public static function mark_failed( $order_id ) {

		global $wpdb;

		return (bool) $wpdb->update(
			Jws_Payment_Ledger::table(),
			array( 'status' => self::STATUS_FAILED ),
			array( 'id' => (int) $order_id, 'status' => self::STATUS_PENDING ),
			array( '%s' ),
			array( '%d', '%s' )
		);
	}

	/**
	 * Reverses what an order granted, as far as it can be reversed.
	 *
	 * Only a completed order can be refunded, and only once — the same claim
	 * pattern as mark_paid(), because PayPal and Stripe both retry refund
	 * events.
	 */
	public static function mark_refunded( $order_id ) {

		global $wpdb;

		$claimed = $wpdb->update(
			Jws_Payment_Ledger::table(),
			array( 'status' => self::STATUS_REFUNDED ),
			array( 'id' => (int) $order_id, 'status' => self::STATUS_COMPLETED ),
			array( '%s' ),
			array( '%d', '%s' )
		);

		if ( ! $claimed ) {
			return false;
		}

		$order = self::find( $order_id );

		if ( ! $order ) {
			return false;
		}

		Jws_Payment_Fulfillment::revoke( $order );

		do_action( 'jws/payment/order_refunded', $order );

		return true;
	}

	/**
	 * Writes a renewal as its own completed order.
	 *
	 * A subscription that bills for a year is twelve payments, and a buyer
	 * looking at their receipts should see twelve rows. The first payment is
	 * the sign-up order; every one after it lands here, already paid, keyed on
	 * the gateway's own id for that invoice so a retried webhook updates the
	 * same row.
	 *
	 * @return int Row id, or 0.
	 */
	public static function record_renewal( $subscription, $gateway_ref, $amount ) {

		global $wpdb;

		$table = Jws_Payment_Ledger::table();
		$ref   = substr( (string) $gateway_ref, 0, 64 );

		if ( ! $ref ) {
			return 0;
		}

		$existing = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE source = %s AND source_ref = %s LIMIT 1", self::SOURCE, $ref )
		);

		if ( $existing ) {
			return (int) $existing;
		}

		$now = current_time( 'mysql' );

		$wpdb->insert(
			$table,
			array(
				'user_id'         => (int) $subscription->user_id,
				'order_number'    => self::generate_order_number(),
				'type'            => (string) $subscription->type,
				'source'          => self::SOURCE,
				'source_ref'      => $ref,
				'item_id'         => (int) $subscription->item_id,
				'item_label'      => substr( (string) $subscription->label, 0, 191 ),
				'amount'          => number_format( (float) $amount, 2, '.', '' ),
				'currency'        => (string) $subscription->currency,
				'gateway'         => (string) $subscription->gateway,
				'gateway_ref'     => substr( (string) $subscription->gateway_ref, 0, 191 ),
				'method'          => '',
				'subscription_id' => (int) $subscription->id,
				'meta'            => wp_json_encode( array( 'renewal' => true, 'level_id' => (int) $subscription->item_id ) ),
				'status'          => self::STATUS_COMPLETED,
				'created_at'      => $now,
				'paid_at'         => $now,
			)
		);

		return (int) $wpdb->insert_id;
	}
}
