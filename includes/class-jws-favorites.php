<?php

/**
 * Data access for favorites/likes, backed by {@see Jws_Streamvid_Tables::table_favorites()}.
 *
 * Replaces the `{post_type}_liked` usermeta array. `get_ids()` returns ids
 * oldest-first, matching the old array's insertion order, so existing callers
 * that do `array_reverse()` to show newest-first keep working unchanged.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Favorites {

	public static function is_liked( $user_id, $post_id, $post_type ) {
		global $wpdb;
		$table = Jws_Streamvid_Tables::table_favorites();
		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT 1 FROM {$table} WHERE user_id = %d AND post_id = %d AND post_type = %s LIMIT 1",
			$user_id, $post_id, $post_type
		) );
	}

	public static function get_ids( $user_id, $post_type ) {
		global $wpdb;
		$table = Jws_Streamvid_Tables::table_favorites();
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT post_id FROM {$table} WHERE user_id = %d AND post_type = %s ORDER BY created_at ASC, id ASC",
			$user_id, $post_type
		) );
		return array_map( 'intval', $ids );
	}

	public static function add( $user_id, $post_id, $post_type ) {
		global $wpdb;
		$table = Jws_Streamvid_Tables::table_favorites();
		$wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$table} (user_id, post_id, post_type, created_at) VALUES (%d, %d, %s, %s)",
			$user_id, $post_id, $post_type, current_time( 'mysql' )
		) );
	}

	public static function remove( $user_id, $post_id, $post_type ) {
		global $wpdb;
		$table = Jws_Streamvid_Tables::table_favorites();
		$wpdb->delete( $table, array(
			'user_id'   => $user_id,
			'post_id'   => $post_id,
			'post_type' => $post_type,
		), array( '%d', '%d', '%s' ) );
	}

	/**
	 * Copies every `{post_type}_liked` usermeta row into the table. Safe to
	 * run more than once: the unique key on (user_id, post_id, post_type)
	 * makes each insert a no-op if that row already exists.
	 *
	 * @return array { migrated: int, skipped: int, users: int, errors: string[] }
	 */
	public static function migrate_from_meta( $batch = 500 ) {
		global $wpdb;
		$table = Jws_Streamvid_Tables::table_favorites();
		$post_types = array( 'movies', 'tv_shows', 'videos' );
		$result = array( 'migrated' => 0, 'skipped' => 0, 'users' => 0, 'errors' => array() );

		foreach ( $post_types as $post_type ) {
			$meta_key = $post_type . '_liked';
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
				$meta_key
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
					$values[] = $wpdb->prepare( '(%d,%d,%s,%s)', $row->user_id, $post_id, $post_type, $now );

					if ( count( $values ) >= $batch ) {
						$result['migrated'] += self::flush_batch( $table, $values );
						$values = array();
					}
				}
			}

			if ( ! empty( $values ) ) {
				$result['migrated'] += self::flush_batch( $table, $values );
			}
		}

		return $result;
	}

	private static function flush_batch( $table, array $values ) {
		global $wpdb;
		$sql = "INSERT IGNORE INTO {$table} (user_id, post_id, post_type, created_at) VALUES " . implode( ',', $values );
		$wpdb->query( $sql );
		return $wpdb->rows_affected;
	}
}
