<?php

/**
 * Data access for the watchlist, backed by {@see Jws_Streamvid_Tables::table_watchlist()}.
 *
 * Replaces the `post_watchlist` usermeta array. `get_ids()` returns ids
 * oldest-first, matching the old array's insertion order, so existing callers
 * that do `array_reverse()` to show newest-first keep working unchanged.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Watchlist {

	public static function is_watchlisted( $user_id, $post_id ) {
		global $wpdb;
		$table = Jws_Streamvid_Tables::table_watchlist();
		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT 1 FROM {$table} WHERE user_id = %d AND post_id = %d LIMIT 1",
			$user_id, $post_id
		) );
	}

	public static function get_ids( $user_id ) {
		global $wpdb;
		$table = Jws_Streamvid_Tables::table_watchlist();
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT post_id FROM {$table} WHERE user_id = %d ORDER BY created_at ASC, id ASC",
			$user_id
		) );
		return array_map( 'intval', $ids );
	}

	public static function add( $user_id, $post_id ) {
		global $wpdb;
		$table = Jws_Streamvid_Tables::table_watchlist();
		$wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$table} (user_id, post_id, created_at) VALUES (%d, %d, %s)",
			$user_id, $post_id, current_time( 'mysql' )
		) );
	}

	public static function remove( $user_id, $post_id ) {
		global $wpdb;
		$table = Jws_Streamvid_Tables::table_watchlist();
		$wpdb->delete( $table, array(
			'user_id' => $user_id,
			'post_id' => $post_id,
		), array( '%d', '%d' ) );
	}

	/**
	 * Copies every `post_watchlist` usermeta row into the table. Safe to run
	 * more than once: the unique key on (user_id, post_id) makes each insert
	 * a no-op if that row already exists.
	 *
	 * @return array { migrated: int, users: int }
	 */
	public static function migrate_from_meta( $batch = 500 ) {
		global $wpdb;
		$table = Jws_Streamvid_Tables::table_watchlist();
		$result = array( 'migrated' => 0, 'users' => 0 );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
			'post_watchlist'
		) );

		$values = array();
		$now = current_time( 'mysql' );

		foreach ( $rows as $row ) {
			$ids = maybe_unserialize( $row->meta_value );
			if ( ! is_array( $ids ) || empty( $ids ) ) {
				continue;
			}
			$result['users']++;
			foreach ( $ids as $post_id ) {
				$post_id = (int) $post_id;
				if ( $post_id <= 0 ) {
					continue;
				}
				$values[] = $wpdb->prepare( '(%d,%d,%s)', $row->user_id, $post_id, $now );

				if ( count( $values ) >= $batch ) {
					$result['migrated'] += self::flush_batch( $table, $values );
					$values = array();
				}
			}
		}

		if ( ! empty( $values ) ) {
			$result['migrated'] += self::flush_batch( $table, $values );
		}

		return $result;
	}

	private static function flush_batch( $table, array $values ) {
		global $wpdb;
		$sql = "INSERT IGNORE INTO {$table} (user_id, post_id, created_at) VALUES " . implode( ',', $values );
		$wpdb->query( $sql );
		return $wpdb->rows_affected;
	}
}
