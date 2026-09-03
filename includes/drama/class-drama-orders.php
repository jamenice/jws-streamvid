<?php

/**
 * Orders: what someone bought, and the one place it is turned into coins.
 *
 * Every gateway funnels through here. A row is written before the shopper
 * leaves for the payment page, and fulfilled when the gateway says the money
 * arrived — never on the redirect back, which a shopper can fake by editing a
 * URL and which never happens at all if they close the tab.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/drama
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Drama_Orders {

	const STATUS_PENDING  = 'pending';
	const STATUS_PAID     = 'paid';
	const STATUS_FAILED   = 'failed';
	const STATUS_REFUNDED = 'refunded';

	/**
	 * Records an intent to buy, before anyone is sent anywhere.
	 *
	 * @param array $args user_id, kind, label, coins, amount, currency,
	 *                    gateway, method, fingerprint, episode_id.
	 * @return int Order id, or 0.
	 */
	public static function create( array $args ) {

		global $wpdb;

		$row = array(
			'user_id'     => (int) $args['user_id'],
			'kind'        => 'vip' === $args['kind'] ? 'vip' : 'coins',
			'label'       => substr( (string) $args['label'], 0, 191 ),
			'coins'       => isset( $args['coins'] ) ? (int) $args['coins'] : 0,
			'amount'      => number_format( (float) $args['amount'], 2, '.', '' ),
			'currency'    => strtoupper( substr( (string) $args['currency'], 0, 3 ) ),
			'gateway'     => substr( (string) $args['gateway'], 0, 20 ),
			'method'      => substr( (string) $args['method'], 0, 20 ),
			/* NULL, not '': see the unique key on this column. */
			'gateway_ref' => null,
			'fingerprint' => substr( (string) $args['fingerprint'], 0, 32 ),
			'episode_id'  => isset( $args['episode_id'] ) ? (int) $args['episode_id'] : 0,
			'status'      => self::STATUS_PENDING,
			'created_at'  => current_time( 'mysql' ),
		);

		$ok = $wpdb->insert( Jws_Drama_Install::table_order(), $row );

		return $ok ? (int) $wpdb->insert_id : 0;
	}

	public static function find( $order_id ) {

		global $wpdb;

		$table = Jws_Drama_Install::table_order();

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $order_id ) );
	}

	public static function find_by_ref( $gateway, $ref ) {

		global $wpdb;

		$table = Jws_Drama_Install::table_order();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE gateway = %s AND gateway_ref = %s LIMIT 1",
				$gateway,
				$ref
			)
		);
	}

	/**
	 * Ties the order to the object the gateway just made for it.
	 *
	 * Written straight after the session or intent is created, so a webhook
	 * that arrives before the shopper has even finished paying still finds
	 * something to fulfil.
	 */
	public static function attach_ref( $order_id, $ref ) {

		global $wpdb;

		return (bool) $wpdb->update(
			Jws_Drama_Install::table_order(),
			array( 'gateway_ref' => substr( (string) $ref, 0, 191 ) ),
			array( 'id' => (int) $order_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Fulfils an order exactly once.
	 *
	 * The claim is the UPDATE itself: only a row still sitting at `pending` can
	 * be moved to `paid`, and only the caller whose UPDATE actually changed a
	 * row goes on to credit anything. Two webhooks racing — Stripe sends the
	 * same payment as more than one event, and retries each until it gets a
	 * 2xx — cannot both win that.
	 *
	 * @return bool True if this call is the one that fulfilled it.
	 */
	public static function mark_paid( $order_id ) {

		global $wpdb;

		$claimed = $wpdb->update(
			Jws_Drama_Install::table_order(),
			array( 'status' => self::STATUS_PAID, 'paid_at' => current_time( 'mysql' ) ),
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

		if ( 'coins' === $order->kind && (int) $order->coins > 0 ) {
			Jws_Drama_Wallet::credit(
				(int) $order->user_id,
				(int) $order->coins,
				'topup',
				(int) $order->id,
				sprintf( '%s (#%d)', $order->label, $order->id )
			);
		}

		/**
		 * Fires once, after an order is paid and any coins are credited.
		 *
		 * @param object $order
		 */
		do_action( 'streamvid/drama/order_paid', $order );

		return true;
	}

	public static function mark_failed( $order_id ) {

		global $wpdb;

		return (bool) $wpdb->update(
			Jws_Drama_Install::table_order(),
			array( 'status' => self::STATUS_FAILED ),
			array( 'id' => (int) $order_id, 'status' => self::STATUS_PENDING ),
			array( '%s' ),
			array( '%d', '%s' )
		);
	}

	/**
	 * Takes coins back on a refund — down to zero, never below.
	 *
	 * They may already have been spent on episodes the viewer has watched;
	 * clawing a wallet into the negative would leave them unable to buy their
	 * way out of it.
	 */
	public static function mark_refunded( $order_id ) {

		global $wpdb;

		$claimed = $wpdb->update(
			Jws_Drama_Install::table_order(),
			array( 'status' => self::STATUS_REFUNDED ),
			array( 'id' => (int) $order_id, 'status' => self::STATUS_PAID ),
			array( '%s' ),
			array( '%d', '%s' )
		);

		if ( ! $claimed ) {
			return false;
		}

		$order = self::find( $order_id );

		if ( $order && 'coins' === $order->kind && (int) $order->coins > 0 ) {

			$take = min( (int) $order->coins, Jws_Drama_Wallet::balance( (int) $order->user_id ) );

			if ( $take > 0 ) {
				Jws_Drama_Wallet::debit(
					(int) $order->user_id,
					$take,
					'refund',
					(int) $order->id,
					sprintf( 'Refund #%d', $order->id )
				);
			}
		}

		return true;
	}

	/** Recent orders for one person, for the statement on the Coins tab. */
	public static function history( $user_id, $limit = 30, $offset = 0 ) {

		global $wpdb;

		$table = Jws_Drama_Install::table_order();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT %d OFFSET %d",
				(int) $user_id,
				(int) $limit,
				(int) $offset
			)
		);
	}
}
