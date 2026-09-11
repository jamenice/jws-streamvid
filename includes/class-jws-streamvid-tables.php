<?php

/**
 * Custom tables for favorites, watchlist, watch history and pay-per-view access.
 *
 * These replaced per-user usermeta arrays (`{post_type}_liked`, `post_watchlist`,
 * `video_progress_data`, `jws_purchased_videos`, `jws_rented_videos`) that had to
 * be loaded, modified and saved whole on every toggle. One row per (user, post)
 * lets a toggle be a single indexed SQL statement instead of a read-modify-write
 * of a growing array, and removes the lost-update risk of two requests
 * overwriting each other's array.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Streamvid_Tables {

	/** Bump when a table changes so the installer re-runs on existing sites. */
	const DB_VERSION = '1.1.0';

	const OPTION_DB_VERSION = 'jws_streamvid_data_db_version';

	public static function table_favorites() {
		global $wpdb;
		return $wpdb->prefix . 'jws_favorites';
	}

	public static function table_watchlist() {
		global $wpdb;
		return $wpdb->prefix . 'jws_watchlist';
	}

	public static function table_history() {
		global $wpdb;
		return $wpdb->prefix . 'jws_history';
	}

	public static function table_ppv_access() {
		global $wpdb;
		return $wpdb->prefix . 'jws_ppv_access';
	}

	/**
	 * Runs on every request but does nothing once the stored version matches.
	 * Hooked on `init` (not `admin_init`) because these tables are also read
	 * and written by the REST API that the mobile app calls, which never
	 * loads wp-admin.
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

		$charset    = $wpdb->get_charset_collate();
		$favorites  = self::table_favorites();
		$watchlist  = self::table_watchlist();
		$history    = self::table_history();
		$ppv        = self::table_ppv_access();

		/*
		 * Unique on (user_id, post_id, post_type) — a post_type is included
		 * because the same post_id could theoretically collide across post
		 * types, and it lets INSERT IGNORE / ON DUPLICATE KEY do the toggle
		 * and the migration import idempotently.
		 */
		$sql_favorites = "CREATE TABLE {$favorites} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			post_id bigint(20) unsigned NOT NULL,
			post_type varchar(20) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_post_type (user_id, post_id, post_type),
			KEY user_type (user_id, post_type)
		) {$charset};";

		$sql_watchlist = "CREATE TABLE {$watchlist} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			post_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_post (user_id, post_id),
			KEY user_id (user_id)
		) {$charset};";

		/*
		 * `episode_id` mirrors the old meta's `episodes` field: when an
		 * episode is watched, the parent TV show also gets a row so it shows
		 * up in "continue watching" — episode_id records which episode that
		 * TV-show row points to. `watched_at` is refreshed on every write and
		 * is what "most recent" ordering sorts on, replacing the old
		 * unset()-then-reassign trick used to move an array entry to the end.
		 */
		$sql_history = "CREATE TABLE {$history} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			post_id bigint(20) unsigned NOT NULL,
			progress int(10) unsigned NOT NULL DEFAULT 0,
			duration int(10) unsigned NOT NULL DEFAULT 0,
			episode_id bigint(20) unsigned NOT NULL DEFAULT 0,
			watched_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_post (user_id, post_id),
			KEY user_watched (user_id, watched_at)
		) {$charset};";

		/*
		 * One row per thing someone bought, replacing the `jws_purchased_videos`
		 * and `jws_rented_videos` usermeta arrays. See class-jws-ppv-access.php
		 * for the access rules these columns encode; the ones worth explaining
		 * here are the nullable dates and the single unique key.
		 *
		 * A rental is sold as "start watching within `delay_days`, then you have
		 * `rent_days`", which means its expiry genuinely does not exist until it
		 * starts. The old array said so with `expire => 'never'`, a string in a
		 * date field that no SQL comparison could read, so "which rentals lapse
		 * today" was not a question the database could answer. Here an unstarted
		 * rental is `starts_at IS NULL` and a started one has both dates, so
		 * `KEY expiring` serves both that question and the cron that acts on it.
		 * A purchase keeps `expires_at` NULL for good — it never lapses.
		 *
		 * UNIQUE (user_id, post_id, type) is what makes every write a single
		 * atomic statement — INSERT … ON DUPLICATE KEY UPDATE for a grant,
		 * a conditional UPDATE for starting the clock — instead of the
		 * read-modify-write of a whole array that could lose a concurrent
		 * purchase. It also holds the buy/rent semantics the old code had: one
		 * live entitlement of each kind per title, so renting again restarts the
		 * rental rather than stacking a second one. Buying and renting the same
		 * title stay separate rows, as they were separate arrays.
		 *
		 * The money columns are a snapshot, not a pointer: `price` is what this
		 * customer actually paid, which the admin repricing the title must not
		 * rewrite. `order_id`/`order_number` reference jws_payment_orders, which
		 * remains the record of the transaction — this table is only the access
		 * it granted.
		 */
		$sql_ppv = "CREATE TABLE {$ppv} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			post_id bigint(20) unsigned NOT NULL,
			type varchar(10) NOT NULL,
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			order_number varchar(32) NOT NULL DEFAULT '',
			price decimal(12,2) NOT NULL DEFAULT 0.00,
			currency varchar(3) NOT NULL DEFAULT '',
			delay_days smallint(5) unsigned NOT NULL DEFAULT 0,
			rent_days smallint(5) unsigned NOT NULL DEFAULT 0,
			purchased_at datetime NOT NULL,
			starts_at datetime NULL DEFAULT NULL,
			expires_at datetime NULL DEFAULT NULL,
			status varchar(10) NOT NULL DEFAULT 'active',
			PRIMARY KEY  (id),
			UNIQUE KEY user_post_type (user_id, post_id, type),
			KEY post_type (post_id, type),
			KEY expiring (expires_at),
			KEY user_recent (user_id, purchased_at)
		) {$charset};";

		dbDelta( $sql_favorites );
		dbDelta( $sql_watchlist );
		dbDelta( $sql_history );
		dbDelta( $sql_ppv );

		update_option( self::OPTION_DB_VERSION, self::DB_VERSION );
	}
}

add_action( 'init', array( 'Jws_Streamvid_Tables', 'maybe_install' ), 5 );
