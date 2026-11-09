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
	const DB_VERSION = '1.2.0';

	/** The schema before viewer profiles; what rollback_profiles() returns to. */
	const DB_VERSION_BEFORE_PROFILES = '1.1.0';

	const OPTION_DB_VERSION = 'jws_streamvid_data_db_version';

	/**
	 * Index changes that 1.2.0 makes to the per-viewer tables, in one place so
	 * the upgrade and the rollback cannot drift apart.
	 *
	 * dbDelta() adds a missing column or index but never drops or rewrites an
	 * existing one, so the old unique keys (one row per user and post) would
	 * stay and block a second profile from saving the same title. The upgrade
	 * therefore drops them itself before dbDelta() runs.
	 */
	private static function profile_index_changes() {
		return array(
			self::table_favorites() => array(
				'old' => array(
					'user_post_type' => 'UNIQUE KEY user_post_type (user_id, post_id, post_type)',
					'user_type'      => 'KEY user_type (user_id, post_type)',
				),
				'new' => array(
					'user_profile_post_type' => 'UNIQUE KEY user_profile_post_type (user_id, profile_id, post_id, post_type)',
					'user_profile_type'      => 'KEY user_profile_type (user_id, profile_id, post_type)',
				),
			),
			self::table_watchlist() => array(
				'old' => array(
					'user_post' => 'UNIQUE KEY user_post (user_id, post_id)',
				),
				'new' => array(
					'user_profile_post' => 'UNIQUE KEY user_profile_post (user_id, profile_id, post_id)',
				),
			),
			self::table_history() => array(
				'old' => array(
					'user_post'    => 'UNIQUE KEY user_post (user_id, post_id)',
					'user_watched' => 'KEY user_watched (user_id, watched_at)',
				),
				'new' => array(
					'user_profile_post'    => 'UNIQUE KEY user_profile_post (user_id, profile_id, post_id)',
					'user_profile_watched' => 'KEY user_profile_watched (user_id, profile_id, watched_at)',
				),
			),
		);
	}

	public static function table_profiles() {
		global $wpdb;
		return $wpdb->prefix . 'jws_profiles';
	}

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
		$profiles   = self::table_profiles();

		/*
		 * Viewer profiles: several people sharing one account, Netflix style.
		 *
		 * Favorites, watchlist and history carry a `profile_id`. The main
		 * profile always stores its rows under profile_id 0, not under its own
		 * row id, so every row written before profiles existed already belongs
		 * to the main profile with no data migration, and the mobile app API
		 * (which sends no profile cookie) keeps reading and writing the main
		 * profile. See Jws_Profiles::data_key().
		 *
		 * `main_user_id` holds the user id on the main profile's row and NULL
		 * on every other row. MySQL allows many NULLs in a UNIQUE key, so the
		 * key enforces exactly one main profile per account even when two
		 * requests create it at the same moment.
		 *
		 * `max_maturity` is an age in years matched against the `jws_maturity`
		 * term meta on the `ages` taxonomy (G 0, PG 7, PG-13 13, R 17, NC-17 18).
		 */
		$sql_profiles = "CREATE TABLE {$profiles} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			main_user_id bigint(20) unsigned NULL DEFAULT NULL,
			name varchar(50) NOT NULL,
			avatar varchar(100) NOT NULL DEFAULT '',
			is_kid tinyint(1) unsigned NOT NULL DEFAULT 0,
			max_maturity tinyint(3) unsigned NOT NULL DEFAULT 18,
			pin_hash varchar(255) NOT NULL DEFAULT '',
			autoplay_next tinyint(1) unsigned NOT NULL DEFAULT 1,
			sort_order smallint(5) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY main_user (main_user_id),
			KEY user_order (user_id, sort_order)
		) {$charset};";

		/*
		 * Unique on (user_id, profile_id, post_id, post_type) — a post_type is
		 * included because the same post_id could theoretically collide across
		 * post types, and it lets INSERT IGNORE / ON DUPLICATE KEY do the
		 * toggle and the migration import idempotently.
		 */
		$sql_favorites = "CREATE TABLE {$favorites} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			profile_id bigint(20) unsigned NOT NULL DEFAULT 0,
			post_id bigint(20) unsigned NOT NULL,
			post_type varchar(20) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_profile_post_type (user_id, profile_id, post_id, post_type),
			KEY user_profile_type (user_id, profile_id, post_type)
		) {$charset};";

		$sql_watchlist = "CREATE TABLE {$watchlist} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			profile_id bigint(20) unsigned NOT NULL DEFAULT 0,
			post_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_profile_post (user_id, profile_id, post_id),
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
			profile_id bigint(20) unsigned NOT NULL DEFAULT 0,
			post_id bigint(20) unsigned NOT NULL,
			progress int(10) unsigned NOT NULL DEFAULT 0,
			duration int(10) unsigned NOT NULL DEFAULT 0,
			episode_id bigint(20) unsigned NOT NULL DEFAULT 0,
			watched_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_profile_post (user_id, profile_id, post_id),
			KEY user_profile_watched (user_id, profile_id, watched_at)
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

		/*
		 * Existing tables get their profile column and new keys first, so the
		 * dbDelta() calls below find them already matching. A failed ALTER
		 * leaves the stored version alone, and the next request tries again
		 * instead of running code that writes to a column that is not there.
		 */
		if ( ! self::upgrade_profile_columns() ) {
			return;
		}

		dbDelta( $sql_profiles );
		dbDelta( $sql_favorites );
		dbDelta( $sql_watchlist );
		dbDelta( $sql_history );
		dbDelta( $sql_ppv );

		update_option( self::OPTION_DB_VERSION, self::DB_VERSION );
	}

	/**
	 * Adds `profile_id` to the per-viewer tables that already exist and swaps
	 * their keys, one ALTER TABLE per table so each table changes in a single
	 * step. Rows already there get profile_id 0, the main profile, and stay
	 * unique because they were unique on (user_id, post_id) before.
	 *
	 * Tables that do not exist yet are skipped: dbDelta() creates them with
	 * the new schema. Running this twice changes nothing the second time.
	 *
	 * @return bool False when an ALTER failed (logged).
	 */
	private static function upgrade_profile_columns() {

		global $wpdb;

		foreach ( self::profile_index_changes() as $table => $keys ) {

			if ( ! self::table_exists( $table ) ) {
				continue;
			}

			$indexes = self::index_names( $table );
			$parts   = array();

			if ( ! self::column_exists( $table, 'profile_id' ) ) {
				$parts[] = 'ADD COLUMN profile_id bigint(20) unsigned NOT NULL DEFAULT 0 AFTER user_id';
			}
			foreach ( array_keys( $keys['old'] ) as $name ) {
				if ( in_array( $name, $indexes, true ) ) {
					$parts[] = "DROP INDEX {$name}";
				}
			}
			foreach ( $keys['new'] as $name => $definition ) {
				if ( ! in_array( $name, $indexes, true ) ) {
					$parts[] = "ADD {$definition}";
				}
			}

			if ( empty( $parts ) ) {
				continue;
			}

			// Table and index names come from this class, not from input.
			$ok = $wpdb->query( "ALTER TABLE {$table} " . implode( ', ', $parts ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			if ( false === $ok ) {
				error_log( sprintf( 'jws-streamvid: could not add viewer profiles to %s: %s', $table, $wpdb->last_error ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return false;
			}
		}

		return true;
	}

	/**
	 * Undoes the 1.2.0 viewer-profiles schema.
	 *
	 * Warning: deletes every favorite, watchlist and history row that belongs
	 * to a profile other than the main one, and every profile. Those rows
	 * cannot fit back under the old one-row-per-user-and-post keys. Back up
	 * the database first.
	 *
	 * Run it with the 1.2.0 code still active, then deploy the previous plugin
	 * version straight away; while 1.2.0 stays active, the next request would
	 * upgrade the tables again:
	 *
	 *     wp eval 'Jws_Streamvid_Tables::rollback_profiles();'
	 *
	 * @return bool False when an ALTER failed (logged).
	 */
	public static function rollback_profiles() {

		global $wpdb;

		foreach ( self::profile_index_changes() as $table => $keys ) {

			if ( ! self::table_exists( $table ) || ! self::column_exists( $table, 'profile_id' ) ) {
				continue;
			}

			$wpdb->query( "DELETE FROM {$table} WHERE profile_id <> 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$indexes = self::index_names( $table );
			$parts   = array();

			foreach ( array_keys( $keys['new'] ) as $name ) {
				if ( in_array( $name, $indexes, true ) ) {
					$parts[] = "DROP INDEX {$name}";
				}
			}
			foreach ( $keys['old'] as $name => $definition ) {
				if ( ! in_array( $name, $indexes, true ) ) {
					$parts[] = "ADD {$definition}";
				}
			}
			$parts[] = 'DROP COLUMN profile_id';

			$ok = $wpdb->query( "ALTER TABLE {$table} " . implode( ', ', $parts ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			if ( false === $ok ) {
				error_log( sprintf( 'jws-streamvid: could not remove viewer profiles from %s: %s', $table, $wpdb->last_error ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return false;
			}
		}

		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table_profiles() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		delete_metadata( 'user', 0, Jws_Profiles::META_ACTIVE, '', true );
		update_option( self::OPTION_DB_VERSION, self::DB_VERSION_BEFORE_PROFILES );

		return true;
	}

	private static function table_exists( $table ) {
		global $wpdb;
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	private static function column_exists( $table, $column ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private static function index_names( $table ) {
		global $wpdb;
		$rows = $wpdb->get_results( "SHOW INDEX FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array_values( array_unique( wp_list_pluck( (array) $rows, 'Key_name' ) ) );
	}
}

add_action( 'init', array( 'Jws_Streamvid_Tables', 'maybe_install' ), 5 );
