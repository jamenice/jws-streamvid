<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * Recurring plans sold through this checkout, whichever gateway bills them.
 *
 * One table for Stripe and PayPal both, because everything downstream — the
 * renewal that extends a membership, the cancellation that ends it, the row on
 * the buyer's account page — needs the same answer and must never have to ask
 * an API which gateway is paying.
 *
 * Paid Memberships Pro still owns the level itself. This table only records
 * who is being billed for what, so a renewal can find the level to keep alive
 * and a cancellation can find the level to end.
 */
class Jws_Payment_Subscriptions {

	const STATUS_ACTIVE   = 'active';
	const STATUS_PAST_DUE = 'past_due';
	const STATUS_CANCELED = 'canceled';

	/**
	 * Writes what a gateway just told us about one subscription.
	 *
	 * Keyed on (gateway, gateway_ref), so the renewal event three weeks from
	 * now updates the row the sign-up created rather than stacking a second
	 * membership on the same person.
	 *
	 * @return int Row id, or 0.
	 */
	public static function upsert( array $args ) {

		global $wpdb;

		$table   = Jws_Payment_Ledger::table_subscriptions();
		$gateway = substr( (string) $args['gateway'], 0, 20 );
		$ref     = substr( (string) $args['gateway_ref'], 0, 191 );
		$now     = current_time( 'mysql' );

		if ( ! $gateway || ! $ref ) {
			return 0;
		}

		$existing = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE gateway = %s AND gateway_ref = %s LIMIT 1", $gateway, $ref )
		);

		$row = array(
			'user_id'            => (int) $args['user_id'],
			'type'               => substr( (string) ( $args['type'] ?? 'membership' ), 0, 20 ),
			'item_id'            => (int) ( $args['item_id'] ?? 0 ),
			'label'              => substr( (string) ( $args['label'] ?? '' ), 0, 191 ),
			'fingerprint'        => substr( (string) ( $args['fingerprint'] ?? '' ), 0, 32 ),
			'gateway'            => $gateway,
			'gateway_ref'        => $ref,
			'amount'             => number_format( (float) ( $args['amount'] ?? 0 ), 2, '.', '' ),
			'currency'           => strtoupper( substr( (string) ( $args['currency'] ?? '' ), 0, 3 ) ),
			'status'             => substr( (string) ( $args['status'] ?? self::STATUS_ACTIVE ), 0, 20 ),
			'current_period_end' => ! empty( $args['current_period_end'] ) ? $args['current_period_end'] : null,
			'updated_at'         => $now,
		);

		if ( $existing ) {
			$wpdb->update( $table, $row, array( 'id' => (int) $existing ) );

			return (int) $existing;
		}

		$row['created_at'] = $now;

		$wpdb->insert( $table, $row );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Updates one subscription without needing to know who owns it.
	 *
	 * @return bool Whether a row for it exists — not whether anything changed.
	 *              $wpdb->update() reports zero when the values already match,
	 *              and a caller reading that as "no such subscription" would go
	 *              on to create a second one.
	 */
	public static function set_status( $gateway, $ref, $status, $period_end = null ) {

		global $wpdb;

		$table = Jws_Payment_Ledger::table_subscriptions();

		$id = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE gateway = %s AND gateway_ref = %s LIMIT 1", $gateway, $ref )
		);

		if ( ! $id ) {
			return false;
		}

		$data = array( 'status' => substr( (string) $status, 0, 20 ), 'updated_at' => current_time( 'mysql' ) );

		if ( $period_end ) {
			$data['current_period_end'] = $period_end;
		}

		$wpdb->update( $table, $data, array( 'id' => (int) $id ) );

		return true;
	}

	public static function find( $id ) {

		global $wpdb;

		$table = Jws_Payment_Ledger::table_subscriptions();

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) );
	}

	public static function find_by_ref( $gateway, $ref ) {

		global $wpdb;

		if ( ! $ref ) {
			return null;
		}

		$table = Jws_Payment_Ledger::table_subscriptions();

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE gateway = %s AND gateway_ref = %s LIMIT 1", $gateway, $ref )
		);
	}

	/** Everything on this person's account, newest first. */
	public static function for_user( $user_id ) {

		global $wpdb;

		$table = Jws_Payment_Ledger::table_subscriptions();

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC", (int) $user_id )
		);
	}

	/**
	 * This person's live subscription for one membership level, if any.
	 *
	 * `canceled` counts as live while the paid period has not run out: someone
	 * who cancels is normally paid up to the end of their term, and taking the
	 * level away the moment they click cancel would be selling them a month and
	 * giving them a day.
	 */
	public static function active_for_level( $user_id, $level_id ) {

		global $wpdb;

		$table = Jws_Payment_Ledger::table_subscriptions();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE user_id = %d AND type = 'membership' AND item_id = %d
				   AND status IN ( %s, %s )
				 ORDER BY id DESC LIMIT 1",
				(int) $user_id,
				(int) $level_id,
				self::STATUS_ACTIVE,
				self::STATUS_PAST_DUE
			)
		);
	}

	/** Every subscription still being billed for this person. */
	public static function billing_for_user( $user_id ) {

		global $wpdb;

		$table = Jws_Payment_Ledger::table_subscriptions();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND status IN ( %s, %s ) ORDER BY id DESC",
				(int) $user_id,
				self::STATUS_ACTIVE,
				self::STATUS_PAST_DUE
			)
		);
	}

	/**
	 * Stops a subscription at the gateway, so nothing is charged again.
	 *
	 * Called when the buyer cancels their membership through PMPro — the level
	 * ending on our side means nothing to Stripe or PayPal, which will happily
	 * keep billing a card for access the site has already taken away.
	 *
	 * @return bool Whether the gateway accepted the cancellation.
	 */
	public static function cancel_at_gateway( $subscription ) {

		if ( empty( $subscription->gateway ) || empty( $subscription->gateway_ref ) ) {
			return false;
		}

		$cancelled = 'stripe' === $subscription->gateway
			? Jws_Payment_Stripe::cancel_subscription( $subscription->gateway_ref )
			: Jws_Payment_Paypal::cancel_subscription( $subscription->gateway_ref );

		/*
		 * Marked cancelled either way. A gateway that refuses because the
		 * subscription is already gone leaves us agreeing with it; one that
		 * refuses for any other reason has still had access withdrawn on this
		 * side, and leaving the row `active` would only make the account page
		 * claim a plan the member no longer has.
		 */
		self::set_status( $subscription->gateway, $subscription->gateway_ref, self::STATUS_CANCELED );

		return ! is_wp_error( $cancelled );
	}
}
