<?php

/**
 * Custom tables for favorites, watchlist and watch history.
 *
 * These replaced per-user usermeta arrays (`{post_type}_liked`, `post_watchlist`,
 * `video_progress_data`) that had to be loaded, modified and saved whole on every
 * toggle. One row per (user, post) lets a toggle be a single indexed SQL
 * statement instead of a read-modify-write of a growing array, and removes the
 * lost-update risk of two requests overwriting each other's array.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Streamvid_Tables {

	/** Bump when a table changes so the installer re-runs on existing sites. */
	const DB_VERSION = '1.0.0';

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

		dbDelta( $sql_favorites );
		dbDelta( $sql_watchlist );
		dbDelta( $sql_history );

		update_option( self::OPTION_DB_VERSION, self::DB_VERSION );
	}
}

add_action( 'init', array( 'Jws_Streamvid_Tables', 'maybe_install' ), 5 );
