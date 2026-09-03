<?php

/**
 * Data access for watch history, backed by {@see Jws_Streamvid_Tables::table_history()}.
 *
 * Replaces the `video_progress_data` usermeta array
 * (`post_id => ['time'=>, 'endtime'=>, 'episodes'=>?, 'watched_at'=>?]`).
 * `get_all()`/`get_item()` return that exact shape so the ~20 templates that
 * read `$video_progress_data[$id]['time']` etc. only need their data source
 * swapped, not their display logic. `episode_id` on a row is the "episodes"
 * field: when an episode is watched, its parent TV show also gets a row so it
 * shows up in "continue watching", and that row's episode_id points back at
 * the episode — the episode's own row has no episode_id.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_History {

	/** Matches the old class-api-history.php MAX_HISTORY cap. */
	const MAX_HISTORY = 200;

	public static function get_all( $user_id ) {
		global $wpdb;
		$table = Jws_Streamvid_Tables::table_history();
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT post_id, progress, duration, episode_id, watched_at FROM {$table} WHERE user_id = %d ORDER BY watched_at ASC, id ASC",
			$user_id
		) );

		$out = array();
		foreach ( $rows as $row ) {
			$out[ (int) $row->post_id ] = self::row_to_item( $row );
		}
		return $out;
	}

	public static function get_item( $user_id, $post_id ) {
		global $wpdb;
		$table = Jws_Streamvid_Tables::table_history();
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT post_id, progress, duration, episode_id, watched_at FROM {$table} WHERE user_id = %d AND post_id = %d LIMIT 1",
			$user_id, $post_id
		) );
		return $row ? self::row_to_item( $row ) : null;
	}

	private static function row_to_item( $row ) {
		$item = array(
			'time'       => (int) $row->progress,
			'endtime'    => (int) $row->duration,
			'watched_at' => $row->watched_at,
		);
		if ( (int) $row->episode_id > 0 ) {
			$item['episodes'] = (int) $row->episode_id;
		}
		return $item;
	}

	public static function set_item( $user_id, $post_id, $progress, $duration, $episode_id = 0 ) {
		global $wpdb;
		$table = Jws_Streamvid_Tables::table_history();

		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$table} (user_id, post_id, progress, duration, episode_id, watched_at)
			 VALUES (%d, %d, %d, %d, %d, %s)
			 ON DUPLICATE KEY UPDATE progress = VALUES(progress), duration = VALUES(duration), episode_id = VALUES(episode_id), watched_at = VALUES(watched_at)",
			$user_id, $post_id, max( 0, (int) $progress ), max( 0, (int) $duration ), max( 0, (int) $episode_id ), current_time( 'mysql' )
		) );

		self::trim( $user_id );
	}

	public static function delete_items( $user_id, array $post_ids ) {
		global $wpdb;
		if ( empty( $post_ids ) ) {
			return;
		}
		$table = Jws_Streamvid_Tables::table_history();
		$post_ids = array_map( 'intval', $post_ids );
		$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table} WHERE user_id = %d AND post_id IN ({$placeholders})",
			array_merge( array( $user_id ), $post_ids )
		) );
	}

	public static function clear( $user_id ) {
		global $wpdb;
		$table = Jws_Streamvid_Tables::table_history();
		$wpdb->delete( $table, array( 'user_id' => $user_id ), array( '%d' ) );
	}

	/** Caps history per user at MAX_HISTORY rows, dropping the oldest. */
	private static function trim( $user_id ) {
		global $wpdb;
		$table = Jws_Streamvid_Tables::table_history();
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user_id ) );
		if ( $count <= self::MAX_HISTORY ) {
			return;
		}
		$excess = $count - self::MAX_HISTORY;
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table} WHERE user_id = %d ORDER BY watched_at ASC, id ASC LIMIT %d",
			$user_id, $excess
		) );
	}

	/**
	 * Copies every `video_progress_data` usermeta row into the table. Safe to
	 * run more than once: the unique key on (user_id, post_id) makes each
	 * insert a no-op if that row already exists.
	 *
	 * @return array { migrated: int, users: int }
	 */
	public static function migrate_from_meta( $batch = 500 ) {
		global $wpdb;
		$table = Jws_Streamvid_Tables::table_history();
		$result = array( 'migrated' => 0, 'users' => 0 );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
			'video_progress_data'
		) );

		$values = array();

		foreach ( $rows as $row ) {
			$items = maybe_unserialize( $row->meta_value );
			if ( ! is_array( $items ) || empty( $items ) ) {
				continue;
			}
			$result['users']++;
			foreach ( $items as $post_id => $meta ) {
				$post_id = (int) $post_id;
				if ( $post_id <= 0 || ! is_array( $meta ) ) {
					continue;
				}
				$progress   = isset( $meta['time'] ) ? max( 0, (int) $meta['time'] ) : 0;
				$duration   = isset( $meta['endtime'] ) ? max( 0, (int) $meta['endtime'] ) : 0;
				$episode_id = isset( $meta['episodes'] ) ? max( 0, (int) $meta['episodes'] ) : 0;
				$watched_at = ! empty( $meta['watched_at'] ) ? $meta['watched_at'] : current_time( 'mysql' );

				$values[] = $wpdb->prepare( '(%d,%d,%d,%d,%d,%s)', $row->user_id, $post_id, $progress, $duration, $episode_id, $watched_at );

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
		$sql = "INSERT IGNORE INTO {$table} (user_id, post_id, progress, duration, episode_id, watched_at) VALUES " . implode( ',', $values );
		$wpdb->query( $sql );
		return $wpdb->rows_affected;
	}
}
