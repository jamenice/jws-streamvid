<?php

/**
 * VIP subscriptions, whichever gateway sold them.
 *
 * One table for Stripe and PayPal both, because the access check runs on every
 * locked episode and must never have to ask an API which one is paying. The
 * webhooks write `current_period_end`; everything else only reads it.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/drama
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Drama_Subscriptions {

	const STATUS_ACTIVE   = 'active';
	const STATUS_PAST_DUE = 'past_due';
	const STATUS_CANCELED = 'canceled';

	/**
	 * Per-request answers to is_active(), cleared by every write.
	 *
	 * A webhook writes a subscription and then something downstream asks
	 * whether that person has access; a cache that outlived the write would
	 * answer with what was true a few lines earlier.
	 */
	private static $active = array();

	private static function flush() {
		self::$active = array();
	}

	/**
	 * Writes what a gateway just told us about one subscription.
	 *
	 * Keyed on (gateway, gateway_sub_id), so the renewal event three weeks from
	 * now updates the same row the sign-up created rather than stacking a
	 * second membership on the same person.
	 */
	public static function upsert( array $args ) {

		global $wpdb;

		$table   = Jws_Drama_Install::table_subscription();
		$gateway = substr( (string) $args['gateway'], 0, 20 );
		$sub_id  = substr( (string) $args['gateway_sub_id'], 0, 191 );
		$now     = current_time( 'mysql' );

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE gateway = %s AND gateway_sub_id = %s LIMIT 1",
				$gateway,
				$sub_id
			)
		);

		$row = array(
			'user_id'            => (int) $args['user_id'],
			'fingerprint'        => substr( (string) $args['fingerprint'], 0, 32 ),
			'label'              => substr( (string) $args['label'], 0, 191 ),
			'gateway'            => $gateway,
			'gateway_sub_id'     => $sub_id,
			'status'             => substr( (string) $args['status'], 0, 20 ),
			'current_period_end' => $args['current_period_end'] ? $args['current_period_end'] : null,
			'updated_at'         => $now,
		);

		self::flush();

		if ( $existing ) {
			$wpdb->update( $table, $row, array( 'id' => (int) $existing ) );

			return (int) $existing;
		}

		$row['created_at'] = $now;

		$wpdb->insert( $table, $row );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Updates one subscription, without needing to know who owns it.
	 *
	 * @return bool Whether a row for it exists — not whether anything changed.
	 *              $wpdb->update() reports zero when the values already match,
	 *              and a caller reading that as "no such subscription" would go
	 *              on to create a second one.
	 */
	public static function set_status( $gateway, $sub_id, $status, $period_end = null ) {

		global $wpdb;

		$table = Jws_Drama_Install::table_subscription();

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE gateway = %s AND gateway_sub_id = %s LIMIT 1",
				$gateway,
				$sub_id
			)
		);

		if ( ! $exists ) {
			return false;
		}

		$data = array( 'status' => substr( (string) $status, 0, 20 ), 'updated_at' => current_time( 'mysql' ) );

		if ( $period_end ) {
			$data['current_period_end'] = $period_end;
		}

		self::flush();

		$wpdb->update( $table, $data, array( 'id' => (int) $exists ) );

		return true;
	}

	/**
	 * Whether this person is inside a paid period right now.
	 *
	 * `current_period_end` in the future is the test, not the status alone: a
	 * cancelled subscription is normally paid up to the end of its term, and
	 * taking access away the moment someone clicks cancel would be selling them
	 * a month and giving them a day.
	 */
	public static function is_active( $user_id ) {

		global $wpdb;

		$user_id = (int) $user_id;

		if ( $user_id <= 0 ) {
			return false;
		}

		if ( isset( self::$active[ $user_id ] ) ) {
			return self::$active[ $user_id ];
		}

		$table = Jws_Drama_Install::table_subscription();

		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table}
				 WHERE user_id = %d
				   AND status IN ( %s, %s )
				   AND current_period_end IS NOT NULL
				   AND current_period_end > %s
				 LIMIT 1",
				$user_id,
				self::STATUS_ACTIVE,
				self::STATUS_CANCELED,
				current_time( 'mysql' )
			)
		);

		self::$active[ $user_id ] = (bool) $found;

		return self::$active[ $user_id ];
	}

	/** Everything on this person's account, newest first. */
	public static function for_user( $user_id ) {

		global $wpdb;

		$table = Jws_Drama_Install::table_subscription();

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC", (int) $user_id )
		);
	}

	public static function find( $id ) {

		global $wpdb;

		$table = Jws_Drama_Install::table_subscription();

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) );
	}

	/**
	 * Removes one subscription row outright — for an admin correcting a
	 * mistaken grant, not for a gateway cancellation (that goes through
	 * set_status(), which keeps the row as a record of what happened).
	 */
	public static function delete( $id ) {

		global $wpdb;

		self::flush();

		return (bool) $wpdb->delete( Jws_Drama_Install::table_subscription(), array( 'id' => (int) $id ), array( '%d' ) );
	}
}
