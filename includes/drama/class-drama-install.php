<?php

/**
 * Database tables for the short-drama coin wallet.
 *
 * Two tables, both write-mostly:
 *
 *  - {prefix}svt_drama_wallet_log — every coin movement, append only. The
 *    running balance lives in usermeta for cheap reads, but this is what it is
 *    reconciled against, so a topup that half-failed can always be traced.
 *  - {prefix}svt_drama_unlock — which episodes a user has paid for. Unique on
 *    (user_id, episode_id) so a double-submit can never charge twice.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/drama
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Drama_Install {

	/** Bump when a table changes so the installer re-runs on existing sites. */
	const DB_VERSION = '1.1.1';

	const OPTION_DB_VERSION = 'jws_drama_db_version';

	public static function table_wallet_log() {
		global $wpdb;
		return $wpdb->prefix . 'svt_drama_wallet_log';
	}

	public static function table_unlock() {
		global $wpdb;
		return $wpdb->prefix . 'svt_drama_unlock';
	}

	public static function table_order() {
		global $wpdb;
		return $wpdb->prefix . 'svt_drama_order';
	}

	public static function table_subscription() {
		global $wpdb;
		return $wpdb->prefix . 'svt_drama_subscription';
	}

	/**
	 * Runs on every admin request but does nothing once the stored version
	 * matches. The plugin is already active on live sites, so the activation
	 * hook alone would never fire for them.
	 */
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
		$log     = self::table_wallet_log();
		$unlock  = self::table_unlock();

		/*
		 * `delta` is signed: topups are positive, spends negative. Storing the
		 * balance after each row means a statement can be rendered without
		 * replaying the whole table.
		 */
		$sql_log = "CREATE TABLE {$log} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			delta bigint(20) NOT NULL,
			balance_after bigint(20) NOT NULL,
			type varchar(20) NOT NULL DEFAULT 'admin',
			ref_id bigint(20) unsigned NOT NULL DEFAULT 0,
			note varchar(191) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY user_created (user_id, created_at),
			KEY type (type)
		) {$charset};";

		$sql_unlock = "CREATE TABLE {$unlock} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			episode_id bigint(20) unsigned NOT NULL,
			drama_id bigint(20) unsigned NOT NULL DEFAULT 0,
			coins bigint(20) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_episode (user_id, episode_id),
			KEY user_drama (user_id, drama_id)
		) {$charset};";

		$order = self::table_order();
		$sub   = self::table_subscription();

		/*
		 * An order carries a snapshot, not a pointer: `coins`, `amount` and
		 * `label` are copied at the moment of purchase. The admin can reprice a
		 * package while a payment is in flight, and the buyer must still get
		 * what the panel showed them.
		 *
		 * The unique key on (gateway, gateway_ref) stops two orders ever claiming
		 * the same Stripe object. `gateway_ref` must therefore be NULL until
		 * there is one, not the empty string: MySQL lets a unique index hold any
		 * number of NULLs but only one '', so a second shopper reaching checkout
		 * before the first had a session id would be turned away.
		 */
		$sql_order = "CREATE TABLE {$order} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			kind varchar(10) NOT NULL DEFAULT 'coins',
			label varchar(191) NOT NULL DEFAULT '',
			coins bigint(20) NOT NULL DEFAULT 0,
			amount decimal(12,2) NOT NULL DEFAULT 0.00,
			currency char(3) NOT NULL DEFAULT 'USD',
			gateway varchar(20) NOT NULL DEFAULT '',
			method varchar(20) NOT NULL DEFAULT '',
			gateway_ref varchar(191) DEFAULT NULL,
			fingerprint varchar(32) NOT NULL DEFAULT '',
			episode_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL,
			paid_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY gateway_ref (gateway, gateway_ref),
			KEY user_status (user_id, status),
			KEY created (created_at)
		) {$charset};";

		/*
		 * One row per subscription, whichever gateway sold it, because the
		 * access check must not have to ask two APIs which one is paying. It is
		 * written only by webhooks; `current_period_end` is what
		 * Jws_Drama_Wallet::membership_unlocks_all() reads.
		 */
		$sql_sub = "CREATE TABLE {$sub} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			fingerprint varchar(32) NOT NULL DEFAULT '',
			label varchar(191) NOT NULL DEFAULT '',
			gateway varchar(20) NOT NULL DEFAULT '',
			gateway_sub_id varchar(191) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'active',
			current_period_end datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY gateway_sub (gateway, gateway_sub_id),
			KEY user_active (user_id, status, current_period_end)
		) {$charset};";

		dbDelta( $sql_log );
		dbDelta( $sql_unlock );
		dbDelta( $sql_order );
		dbDelta( $sql_sub );

		/*
		 * dbDelta compares column types but will not change a column from NOT
		 * NULL to nullable, so it silently leaves `gateway_ref` as it found it.
		 * Stating it outright is the only way this actually lands on a table
		 * created by an earlier version. Both statements are safe to re-run.
		 */
		$wpdb->query( "ALTER TABLE {$order} MODIFY gateway_ref varchar(191) DEFAULT NULL" );
		$wpdb->query( "UPDATE {$order} SET gateway_ref = NULL WHERE gateway_ref = ''" );

		/*
		 * The drama post types are new, so their permalinks do not exist in the
		 * stored rewrite rules yet and every drama URL would 404. Safe to call
		 * here: install() only runs when the version changed, not per request.
		 */
		flush_rewrite_rules( false );

		update_option( self::OPTION_DB_VERSION, self::DB_VERSION );
	}
}
