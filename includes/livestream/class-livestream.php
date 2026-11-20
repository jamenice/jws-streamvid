<?php

/**
 * Live streaming with LiveKit: the host's Live Studio (webcam or OBS), the
 * viewer, and live chat.
 *
 * Self-contained like the drama and Live TV modules — tokens, the OBS
 * ingress, chat storage, the webhook and the front end all live under
 * includes/livestream/ and are hooked up from boot(). The theme only decides
 * which view a live video gets (single-videos.php → host_stream / client_stream).
 *
 * LiveKit is the only live service; its keys are Theme Options > Videos >
 * Livestream. A stream is a `videos` post with live_status "yes" (beeble's
 * flag, kept so the two products share data); one that has finished carries
 * live_status "ended".
 *
 * No composer vendor: the LiveKit SDK was only used to sign JWTs, and a
 * bundled Guzzle clashes with other plugins that ship their own. Tokens are
 * Jws_Livestream_Token; the server API is plain wp_remote_post().
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/livestream
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Livestream {

	/** Nonce action for every host and chat request. */
	const NONCE = 'jws_livestream';

	public static function boot() {

		$dir = plugin_dir_path( __FILE__ );

		require_once $dir . 'class-livestream-token.php';
		require_once $dir . 'class-livestream-api.php';
		require_once $dir . 'class-livestream-chat.php';
		require_once $dir . 'class-livestream-webhook.php';
		require_once $dir . 'class-livestream-frontend.php';

		Jws_Livestream_Api::hook();
		Jws_Livestream_Chat::hook();
		Jws_Livestream_Webhook::hook();
		Jws_Livestream_Frontend::hook();
	}

	/* ---------------------------------------------------------------------- */
	/* Settings                                                                */
	/* ---------------------------------------------------------------------- */

	/**
	 * Theme options are read on use, never at boot: the plugin loads before
	 * the theme defines jws_theme_get_option().
	 */
	public static function option( $key, $default = '' ) {
		return function_exists( 'jws_theme_get_option' ) ? jws_theme_get_option( $key, $default ) : $default;
	}

	/** The wss:// URL browsers connect to. */
	public static function url() {
		return trim( (string) self::option( 'livekit_url' ) );
	}

	/** The same server for API calls: wss:// → https://, ws:// → http://. */
	public static function http_url() {
		return untrailingslashit( preg_replace( '#^ws(s?)://#i', 'http$1://', self::url() ) );
	}

	public static function api_key() {
		return trim( (string) self::option( 'livekit_api_key' ) );
	}

	public static function api_secret() {
		return trim( (string) self::option( 'livekit_api_secret' ) );
	}

	public static function is_configured() {
		return self::url() && self::api_key() && self::api_secret();
	}

	/* ---------------------------------------------------------------------- */
	/* Live posts                                                              */
	/* ---------------------------------------------------------------------- */

	/** Every LiveKit room is live_room_{post_id}. */
	public static function room_name( $post_id ) {
		return 'live_room_' . (int) $post_id;
	}

	/** Post id behind a room name, or 0 when the name is not one of ours. */
	public static function room_post_id( $room_name ) {
		return preg_match( '/^live_room_(\d+)$/', (string) $room_name, $matches ) ? (int) $matches[1] : 0;
	}

	/** LiveKit identity of the host — the post author. */
	public static function host_identity( $post_id ) {
		return 'user_' . (int) get_post_field( 'post_author', $post_id );
	}

	/** A LiveKit live video that has not ended. */
	public static function is_live_post( $post_id ) {
		return 'videos' === get_post_type( $post_id ) && in_array( get_post_meta( $post_id, 'live_status', true ), array( 'yes', '1' ), true );
	}

	/** A LiveKit live video, on air or ended. */
	public static function is_stream_post( $post_id ) {
		return self::is_live_post( $post_id ) || self::has_ended( $post_id );
	}

	public static function has_ended( $post_id ) {
		return 'videos' === get_post_type( $post_id ) && 'ended' === get_post_meta( $post_id, 'live_status', true );
	}

	public static function is_host( $post_id ) {
		$user_id = get_current_user_id();
		return $user_id && (int) get_post_field( 'post_author', $post_id ) === $user_id;
	}

	/**
	 * Scheduled start as a Unix timestamp, or 0 if there is none.
	 * live_start_date is wall-clock time in the site's timezone.
	 */
	public static function start_timestamp( $post_id ) {
		$datetime = get_post_meta( $post_id, 'live_start_date', true );
		$start    = $datetime ? date_create( $datetime, wp_timezone() ) : false;
		return $start ? $start->getTimestamp() : 0;
	}

	/** No schedule, or its time has come. Compared as timestamps. */
	public static function has_started( $post_id ) {
		$start = self::start_timestamp( $post_id );
		return ! $start || $start <= time();
	}

	/**
	 * Who may watch: the host, and anyone the video's own access rules let
	 * in — membership levels, buy or rent (jws_user_has_video_access()).
	 * The viewer token goes through this too, so it cannot be fetched by
	 * someone the page itself turns away.
	 */
	public static function can_watch( $post_id ) {

		if ( self::is_host( $post_id ) ) {
			return true;
		}

		return function_exists( 'jws_user_has_video_access' ) ? (bool) jws_user_has_video_access( $post_id ) : true;
	}

	/**
	 * Name and avatar sent with a LiveKit identity, so chat and the
	 * participants list read them from the server-signed token rather than
	 * from whatever a client puts in its messages.
	 */
	public static function user_profile( $user_id ) {

		$user_id = (int) $user_id;
		$user    = $user_id ? get_userdata( $user_id ) : false;
		$avatar  = '';

		if ( $user_id ) {
			// The avatar uploaded in the profile dashboard, then Gravatar.
			$attachment_id = (int) get_user_meta( $user_id, 'mm_sua_attachment_id', true );
			$avatar        = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '';
		}

		if ( ! $avatar ) {
			$avatar = get_avatar_url( $user_id, array( 'size' => 64 ) );
		}

		return array(
			'user_id'     => $user_id,
			'user_name'   => ( $user && $user->display_name ) ? $user->display_name : __( 'Guest', 'jws_streamvid' ),
			'user_avatar' => $avatar ? $avatar : '',
		);
	}

	/**
	 * The stream is over: viewers stop being sent here, and the OBS key's
	 * ingress slot goes back (LiveKit plans cap how many exist at once).
	 * Called by the host's End stream button and by the webhook.
	 */
	public static function finish( $post_id ) {

		if ( ! self::is_live_post( $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, 'live_status', 'ended' );
		delete_post_meta( $post_id, '_livekit_host_present' );
		wp_clear_scheduled_hook( Jws_Livestream_Webhook::CRON_HOOK, array( (int) $post_id ) );

		$ingress_id = get_post_meta( $post_id, '_livekit_ingress_id', true );

		if ( $ingress_id && Jws_Livestream_Api::delete_ingress( $ingress_id ) ) {
			delete_post_meta( $post_id, '_livekit_ingress_id' );
			delete_post_meta( $post_id, 'stream_key' );
			delete_post_meta( $post_id, 'rtmp_url' );
		}

		do_action( 'jws_livestream_finished', (int) $post_id );
	}
}
