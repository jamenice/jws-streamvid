<?php

/**
 * Live chat history.
 *
 * Messages travel between viewers as LiveKit data messages; this keeps the
 * last ones so someone who joins late sees the conversation. One row per
 * message in {prefix}jws_live_chat — written with a single INSERT, so two
 * viewers writing at once never overwrite each other.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/livestream
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Livestream_Chat {

	/** Bump when the table changes; maybe_install() reruns dbDelta. */
	const DB_VERSION = '1';

	const MAX_LENGTH = 300;

	public static function hook() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ) );
		add_action( 'wp_ajax_jws_livekit_chat', array( __CLASS__, 'save' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'delete_for_post' ) );
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'jws_live_chat';
	}

	/** Past the first run this is one autoloaded option read. */
	public static function maybe_install() {

		if ( get_option( 'jws_livestream_db_version' ) === self::DB_VERSION ) {
			return;
		}

		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		dbDelta( "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			message text NOT NULL,
			created datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id,id)
		) {$charset};" );

		update_option( 'jws_livestream_db_version', self::DB_VERSION );
	}

	/** The latest messages of a stream, oldest first. */
	public static function get_messages( $post_id, $limit = 20 ) {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT user_id, message, created FROM ' . self::table() . ' WHERE post_id = %d ORDER BY id DESC LIMIT %d',
			$post_id,
			$limit
		) );

		return array_reverse( (array) $rows );
	}

	/** Saves a chat message. The author is always the current user. */
	public static function save() {

		check_ajax_referer( Jws_Livestream::NONCE, 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$message = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( ! $post_id || '' === $message || ! Jws_Livestream::is_live_post( $post_id ) || ! Jws_Livestream::can_watch( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Your message could not be sent.', 'jws_streamvid' ) ) );
		}

		self::throttle( 'chat', 5, 10 );

		global $wpdb;

		$wpdb->insert( self::table(), array(
			'post_id' => $post_id,
			'user_id' => get_current_user_id(),
			'message' => mb_substr( $message, 0, self::MAX_LENGTH ),
			'created' => current_time( 'mysql' ),
		), array( '%d', '%d', '%s', '%s' ) );

		wp_send_json_success();
	}

	/** A deleted video takes its chat with it. */
	public static function delete_for_post( $post_id ) {
		global $wpdb;

		if ( 'videos' === get_post_type( $post_id ) ) {
			$wpdb->delete( self::table(), array( 'post_id' => (int) $post_id ), array( '%d' ) );
		}
	}

	/**
	 * At most $max calls per $window seconds per user and action. Chat goes
	 * through admin-ajax, which boots all of WordPress.
	 */
	private static function throttle( $action, $max, $window ) {

		$key   = 'jws_live_' . $action . '_' . get_current_user_id();
		$state = get_transient( $key );

		if ( ! is_array( $state ) || $state['until'] < time() ) {
			$state = array( 'count' => 0, 'until' => time() + $window );
		}

		if ( ++$state['count'] > $max ) {
			wp_send_json_error( array( 'message' => __( 'You are sending messages too fast. Please slow down.', 'jws_streamvid' ) ), 429 );
		}

		set_transient( $key, $state, $window );
	}
}
