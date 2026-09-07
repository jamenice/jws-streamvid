<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * Unified payment ledger.
 *
 * One row per purchase event, whichever of the three checkout paths it came
 * from (PMPro membership, WooCommerce buy/rent/coins, or the drama module's
 * direct Stripe/PayPal flow). This is the shared "what did this user buy,
 * from anywhere" record none of those three has on its own — it does not
 * replace or change how any of them grant access, it only mirrors their
 * completed purchases here so there is one place to read them all.
 *
 * Rows are written by Jws_Payment_Sync, and only while the unified system is
 * enabled (Jws_Payment_Settings::is_enabled()).
 */
class Jws_Payment_Ledger {

	const DB_VERSION        = '1.0.0';
	const OPTION_DB_VERSION = 'jws_payment_ledger_db_version';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'jws_payment_orders';
	}

	/** Runs on every admin request but does nothing once the version matches. */
	public static function maybe_install() {

		if ( get_option( self::OPTION_DB_VERSION ) === self::DB_VERSION ) {
			return;
		}

		self::install();
	}

	public static function install() {

		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$table   = self::table();

		/*
		 * (source, source_ref) is unique so a WC order that fires
		 * "processing" then "completed", or a webhook that retries, updates
		 * the same row instead of doubling it.
		 */
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			type varchar(20) NOT NULL DEFAULT '',
			source varchar(20) NOT NULL DEFAULT '',
			source_ref varchar(64) NOT NULL DEFAULT '',
			item_id bigint(20) unsigned NOT NULL DEFAULT 0,
			item_label varchar(191) NOT NULL DEFAULT '',
			amount decimal(12,2) NOT NULL DEFAULT 0.00,
			currency varchar(3) NOT NULL DEFAULT '',
			gateway varchar(20) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'completed',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_ref (source, source_ref),
			KEY user_id (user_id),
			KEY type (type)
		) {$charset};";

		dbDelta( $sql );

		update_option( self::OPTION_DB_VERSION, self::DB_VERSION );
	}

	/**
	 * Writes or updates one purchase row. Idempotent on (source, source_ref)
	 * so the same WooCommerce order or PMPro order recorded twice (a status
	 * hook firing more than once) updates in place instead of duplicating.
	 *
	 * @param array $args user_id, type, source, source_ref, item_id,
	 *                    item_label, amount, currency, gateway, status.
	 * @return int Row id, or 0 on failure.
	 */
	public static function record( array $args ) {

		global $wpdb;

		$table = self::table();

		$row = array(
			'user_id'    => (int) $args['user_id'],
			'type'       => substr( (string) $args['type'], 0, 20 ),
			'source'     => substr( (string) $args['source'], 0, 20 ),
			'source_ref' => substr( (string) $args['source_ref'], 0, 64 ),
			'item_id'    => isset( $args['item_id'] ) ? (int) $args['item_id'] : 0,
			'item_label' => substr( (string) ( $args['item_label'] ?? '' ), 0, 191 ),
			'amount'     => number_format( (float) ( $args['amount'] ?? 0 ), 2, '.', '' ),
			'currency'   => strtoupper( substr( (string) ( $args['currency'] ?? '' ), 0, 3 ) ),
			'gateway'    => substr( (string) ( $args['gateway'] ?? '' ), 0, 20 ),
			'status'     => substr( (string) ( $args['status'] ?? 'completed' ), 0, 20 ),
		);

		if ( ! $row['user_id'] || '' === $row['source'] || '' === $row['source_ref'] ) {
			return 0;
		}

		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE source = %s AND source_ref = %s LIMIT 1",
				$row['source'],
				$row['source_ref']
			)
		);

		if ( $existing_id ) {
			$wpdb->update( $table, $row, array( 'id' => (int) $existing_id ) );
			return (int) $existing_id;
		}

		$row['created_at'] = current_time( 'mysql' );

		$wpdb->insert( $table, $row );

		return (int) $wpdb->insert_id;
	}

	/** Most recent rows across all users, newest first — for the admin screen. */
	public static function recent( $limit = 50 ) {

		global $wpdb;

		$table = self::table();

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d", (int) $limit )
		);
	}

	/** One user's purchases across all three sources, newest first. */
	public static function for_user( $user_id, $limit = 50 ) {

		global $wpdb;

		$table = self::table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC, id DESC LIMIT %d",
				(int) $user_id,
				(int) $limit
			)
		);
	}
}

add_action( 'admin_init', array( 'Jws_Payment_Ledger', 'maybe_install' ) );
