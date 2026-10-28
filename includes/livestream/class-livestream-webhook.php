<?php

/**
 * LiveKit webhooks: POST /wp-json/jws-streamvid/v1/livekit
 *
 * Set up in LiveKit Cloud > Settings > Webhooks with the site's API key.
 * Optional — the host's End stream button ends a stream without it — but it
 * also ends streams whose host closed the tab or lost the connection for good.
 *
 * LiveKit signs every webhook: the Authorization header is a JWT signed with
 * the API secret, whose `sha256` claim is the hash of the raw body.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/livestream
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Livestream_Webhook {

	const REST_NAMESPACE = 'jws-streamvid/v1';
	const REST_ROUTE     = '/livekit';

	/** Fires two minutes after the host left, with the post id. */
	const CRON_HOOK = 'jws_livestream_host_gone';

	/** How long a host may be gone before the stream is ended. */
	const GRACE = 120;

	public static function hook() {
		add_action( 'rest_api_init', array( __CLASS__, 'register' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'finish_if_host_gone' ) );
	}

	public static function url() {
		return rest_url( self::REST_NAMESPACE . self::REST_ROUTE );
	}

	public static function register() {
		register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE, array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'handle' ),
			// Authenticity is the LiveKit signature, checked in handle().
			'permission_callback' => '__return_true',
		) );
	}

	private static function verify( WP_REST_Request $request ) {

		$auth   = preg_replace( '/^Bearer\s+/i', '', trim( (string) $request->get_header( 'authorization' ) ) );
		$claims = $auth ? Jws_Livestream_Token::decode( $auth ) : false;

		return $claims
			&& isset( $claims['sha256'] )
			&& hash_equals( (string) $claims['sha256'], base64_encode( hash( 'sha256', $request->get_body(), true ) ) );
	}

	public static function handle( WP_REST_Request $request ) {

		if ( ! self::verify( $request ) ) {
			return new WP_REST_Response( array( 'message' => 'Invalid signature' ), 401 );
		}

		$data  = $request->get_json_params();
		$event = isset( $data['event'] ) ? $data['event'] : '';
		$room  = isset( $data['room']['name'] ) ? $data['room']['name'] : '';

		$post_id = Jws_Livestream::room_post_id( $room );

		if ( ! $event || ! $post_id || ! Jws_Livestream::is_live_post( $post_id ) ) {
			return new WP_REST_Response( array( 'message' => 'Ignored' ), 200 );
		}

		$identity = isset( $data['participant']['identity'] ) ? $data['participant']['identity'] : '';
		$is_host  = $identity && Jws_Livestream::host_identity( $post_id ) === $identity;

		if ( 'participant_joined' === $event && $is_host ) {
			update_post_meta( $post_id, '_livekit_host_present', 1 );
			// Seen once: a room that closes before the host ever came is not an ended stream.
			update_post_meta( $post_id, '_livekit_host_seen', 1 );
			wp_clear_scheduled_hook( self::CRON_HOOK, array( $post_id ) );
		}

		/*
		 * The host leaving is not the end: a dropped connection or a reload
		 * rejoins within seconds. The stream ends only if the host is still
		 * gone after the grace period.
		 */
		if ( 'participant_left' === $event && $is_host ) {
			update_post_meta( $post_id, '_livekit_host_present', 0 );

			if ( ! wp_next_scheduled( self::CRON_HOOK, array( $post_id ) ) ) {
				wp_schedule_single_event( time() + self::GRACE, self::CRON_HOOK, array( $post_id ) );
			}
		}

		if ( 'room_finished' === $event && get_post_meta( $post_id, '_livekit_host_seen', true ) ) {
			Jws_Livestream::finish( $post_id );
		}

		return new WP_REST_Response( array( 'message' => 'Webhook received' ), 200 );
	}

	public static function finish_if_host_gone( $post_id ) {
		if ( ! (int) get_post_meta( $post_id, '_livekit_host_present', true ) ) {
			Jws_Livestream::finish( $post_id );
		}
	}
}
