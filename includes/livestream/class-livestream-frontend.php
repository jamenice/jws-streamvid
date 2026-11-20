<?php

/**
 * The two faces of a LiveKit live video:
 *
 *   studio()  the host's Live Studio — go live from the webcam, or get the
 *             OBS server and stream key (theme: videos/single/host_stream.php)
 *   viewer()  everyone else — join, watch, chat (videos/single/client_stream.php)
 *
 * The element ids assets/js/livekit.js drives are listed in that file.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/livestream
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Livestream_Frontend {

	public static function hook() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
	}

	private static function template( $file, array $vars = array() ) {
		extract( $vars ); // phpcs:ignore WordPress.PHP.DontExtract
		include plugin_dir_path( __FILE__ ) . 'templates/' . $file;
	}

	/**
	 * Only on a LiveKit live video: the LiveKit client (bundled, ~390 KB)
	 * never loads anywhere else.
	 */
	public static function enqueue() {

		if ( ! is_singular( 'videos' ) ) {
			return;
		}

		$post_id = get_queried_object_id();

		if ( ! Jws_Livestream::is_stream_post( $post_id ) ) {
			return;
		}

		$url  = plugin_dir_url( __FILE__ ) . 'assets/';
		$path = plugin_dir_path( __FILE__ ) . 'assets/';

		wp_enqueue_style( 'jws-livestream', $url . 'css/live.css', array(), filemtime( $path . 'css/live.css' ) );

		if ( ! Jws_Livestream::is_live_post( $post_id ) ) {
			return;
		}

		wp_enqueue_script( 'livekit-client', $url . 'vendor/livekit-client.umd.min.js', array(), '2.9.3', true );

		foreach ( array(
			'jws-livestream'        => 'livekit.js',
			'jws-livestream-chat'   => 'chat.js',
			'jws-livestream-studio' => 'studio.js',
		) as $handle => $file ) {
			wp_enqueue_script( $handle, $url . 'js/' . $file, array( 'jquery', 'livekit-client' ), filemtime( $path . 'js/' . $file ), true );
		}

		$you = Jws_Livestream::user_profile( get_current_user_id() );

		wp_localize_script( 'jws-livestream', 'jwsLivestream', array(
			'ajax_url'    => admin_url( 'admin-ajax.php' ),
			'nonce'       => is_user_logged_in() ? wp_create_nonce( Jws_Livestream::NONCE ) : '',
			'livekit_url' => Jws_Livestream::url(),
			'you_id'      => get_current_user_id(),
			'you_name'    => $you['user_name'],
			'you_avatar'  => $you['user_avatar'],
			'post_id'     => $post_id,
			'host_id'     => (int) get_post_field( 'post_author', $post_id ),
			'i18n'        => array(
				'waiting_title'     => __( 'Waiting for the host', 'jws_streamvid' ),
				'waiting_text'      => __( 'You are in. The stream starts here as soon as the host goes live.', 'jws_streamvid' ),
				'no_host'           => __( 'The host has not started streaming yet. Try again in a moment.', 'jws_streamvid' ),
				'join_error'        => __( 'Could not connect to the live stream. Please try again.', 'jws_streamvid' ),
				'live_ended'        => __( 'This live stream has ended.', 'jws_streamvid' ),
				'host_away_title'   => __( 'The host will be right back', 'jws_streamvid' ),
				'host_away_text'    => __( 'The stream continues here when the host returns.', 'jws_streamvid' ),
				'reconnecting'      => __( 'Reconnecting', 'jws_streamvid' ),
				'reconnecting_text' => __( 'Your connection dropped. Hang on.', 'jws_streamvid' ),
				'disconnected'      => __( 'You were disconnected. Click Watch live to rejoin.', 'jws_streamvid' ),
				'tap_unmute'        => __( 'Tap to unmute', 'jws_streamvid' ),
				'camera_denied'     => __( 'Camera or microphone access was denied. Allow it in your browser settings.', 'jws_streamvid' ),
				'start_error'       => __( 'Could not start the live stream.', 'jws_streamvid' ),
				'stop_confirm'      => __( 'End the live stream? Viewers are told it has ended, and it cannot be restarted.', 'jws_streamvid' ),
				'host_reconnecting' => __( 'Connection lost. Reconnecting.', 'jws_streamvid' ),
				'host_reconnected'  => __( 'Reconnected. You are live again.', 'jws_streamvid' ),
				'host_disconnected' => __( 'You were disconnected from the live stream. Click Go live with webcam to resume.', 'jws_streamvid' ),
				'camera_off_label'  => __( 'Turn camera off', 'jws_streamvid' ),
				'camera_on_label'   => __( 'Turn camera on', 'jws_streamvid' ),
				'mic_off_label'     => __( 'Mute microphone', 'jws_streamvid' ),
				'mic_on_label'      => __( 'Unmute microphone', 'jws_streamvid' ),
				'emoji'             => __( 'Emoji', 'jws_streamvid' ),
				'reset_key'         => __( 'Reset stream key', 'jws_streamvid' ),
				'login_to_chat'     => __( 'Log in to chat', 'jws_streamvid' ),
				'key_ready'         => __( 'Stream key ready. Paste it into OBS.', 'jws_streamvid' ),
				'anonymous'         => __( 'Guest', 'jws_streamvid' ),
			),
		) );
	}

	/* ---------------------------------------------------------------------- */
	/* Viewer                                                                  */
	/* ---------------------------------------------------------------------- */

	public static function viewer( $post_id = 0 ) {

		$post_id = $post_id ? (int) $post_id : get_the_ID();

		if ( Jws_Livestream::has_ended( $post_id ) ) {
			self::template( 'ended.php', compact( 'post_id' ) );
			return;
		}

		if ( ! Jws_Livestream::can_watch( $post_id ) ) {
			self::blocked( $post_id );
			return;
		}

		if ( ! Jws_Livestream::has_started( $post_id ) ) {
			self::template( 'countdown.php', array(
				'post_id'   => $post_id,
				'timestamp' => Jws_Livestream::start_timestamp( $post_id ),
				'title'     => __( 'This live stream has not started yet.', 'jws_streamvid' ),
			) );
			return;
		}

		$author_id = (int) get_post_field( 'post_author', $post_id );

		self::template( 'viewer.php', compact( 'post_id', 'author_id' ) );
	}

	/**
	 * The theme's own locked-video box (membership levels, buy / rent), the
	 * same one the regular player shows.
	 */
	private static function blocked( $post_id ) {

		$image = get_the_post_thumbnail_url( $post_id, 'full' );

		if ( function_exists( 'jws_streamvid_show_blocked_message' ) ) {

			$level_names = array();

			if ( function_exists( 'pmpro_has_membership_access' ) ) {
				$levels      = pmpro_has_membership_access( $post_id, null, true );
				$level_names = isset( $levels[2] ) ? $levels[2] : array();
			}

			echo '<div class="live-player live-player--blocked">';
			jws_streamvid_show_blocked_message( $post_id, $image, $level_names );
			echo '</div>';

			return;
		}

		self::template( 'ended.php', array(
			'post_id' => $post_id,
			'message' => __( 'You do not have access to this live stream.', 'jws_streamvid' ),
		) );
	}

	/* ---------------------------------------------------------------------- */
	/* Host                                                                    */
	/* ---------------------------------------------------------------------- */

	public static function studio( $post_id = 0 ) {

		$post_id = $post_id ? (int) $post_id : get_the_ID();

		if ( Jws_Livestream::has_ended( $post_id ) ) {
			self::template( 'ended.php', array(
				'post_id' => $post_id,
				'message' => __( 'This live stream has ended. Create a new one to go live again.', 'jws_streamvid' ),
			) );
			return;
		}

		$started = Jws_Livestream::has_started( $post_id );

		self::template( 'studio.php', array(
			'post_id'    => $post_id,
			'room_name'  => Jws_Livestream::room_name( $post_id ),
			'stream_key' => get_post_meta( $post_id, 'stream_key', true ),
			'rtmp_url'   => get_post_meta( $post_id, 'rtmp_url', true ),
			'started'    => $started,
			'timestamp'  => $started ? 0 : Jws_Livestream::start_timestamp( $post_id ),
			'viewer_url' => add_query_arg( 'viewer', '1', get_permalink( $post_id ) ),
			'configured' => Jws_Livestream::is_configured(),
		) );
	}

	/** Start date in the site's timezone, e.g. "Thursday, Oct 29, 8:00 PM". */
	public static function format_start( $timestamp ) {
		return wp_date( 'l, M j, g:i A T', $timestamp );
	}
}
