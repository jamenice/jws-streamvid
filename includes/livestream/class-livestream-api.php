<?php

/**
 * AJAX endpoints for the Live Studio and the viewer, and the LiveKit server
 * API calls behind them.
 *
 *   jws_livekit_host_token    the host goes live from the webcam (nonce, author)
 *   jws_livekit_ingress       the host's OBS server + stream key (nonce, author)
 *   jws_livekit_end           the host ends the stream (nonce, author)
 *   jws_livekit_viewer_token  watch and chat; guests too, so no nonce — a
 *                             cached page would carry an expired one. It is
 *                             gated by Jws_Livestream::can_watch() instead.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/livestream
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Livestream_Api {

	public static function hook() {
		add_action( 'wp_ajax_jws_livekit_host_token', array( __CLASS__, 'host_token' ) );
		add_action( 'wp_ajax_jws_livekit_ingress', array( __CLASS__, 'create_ingress' ) );
		add_action( 'wp_ajax_jws_livekit_end', array( __CLASS__, 'end_stream' ) );
		add_action( 'wp_ajax_jws_livekit_viewer_token', array( __CLASS__, 'viewer_token' ) );
		add_action( 'wp_ajax_nopriv_jws_livekit_viewer_token', array( __CLASS__, 'viewer_token' ) );
	}

	private static function require_config() {
		if ( ! Jws_Livestream::is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'Live streaming is not configured.', 'jws_streamvid' ) ), 500 );
		}
	}

	/**
	 * The live video a host request is about. Nonce, then the current user
	 * must be its author, then the schedule must allow it. Ends the request
	 * otherwise.
	 */
	private static function require_host_post() {

		check_ajax_referer( Jws_Livestream::NONCE, 'nonce' );

		$post_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! $post_id || ! Jws_Livestream::is_live_post( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Live stream not found.', 'jws_streamvid' ) ), 404 );
		}

		if ( ! Jws_Livestream::is_host( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Only the host can manage this live stream.', 'jws_streamvid' ) ), 403 );
		}

		return $post_id;
	}

	private static function require_started( $post_id ) {
		if ( ! Jws_Livestream::has_started( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'This live stream is scheduled for later.', 'jws_streamvid' ) ), 403 );
		}
	}

	/** Publish, plus ingress admin for the OBS input. */
	private static function host_jwt( $post_id ) {

		$identity = 'user_' . get_current_user_id();

		return Jws_Livestream_Token::create(
			$identity,
			array(
				'roomJoin'     => true,
				'room'         => Jws_Livestream::room_name( $post_id ),
				'ingressAdmin' => true,
			),
			array(
				// Viewers match the host by identity to know the stream has ended.
				'name'     => $identity,
				'metadata' => wp_json_encode( Jws_Livestream::user_profile( get_current_user_id() ) ),
			)
		);
	}

	/** A token for managing ingresses outside any one room. */
	private static function ingress_admin_jwt() {
		return Jws_Livestream_Token::create( 'wp-ingress-admin', array( 'ingressAdmin' => true ) );
	}

	/**
	 * A Twirp call to the LiveKit server API. Returns [ HTTP status, decoded
	 * body ]; LiveKit reports errors as { code, msg }.
	 */
	public static function call( $method, array $body, $token ) {

		$response = wp_remote_post( Jws_Livestream::http_url() . '/twirp/livekit.' . $method, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( (object) $body ),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			return array( 0, array( 'msg' => $response->get_error_message() ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		return array( (int) wp_remote_retrieve_response_code( $response ), is_array( $data ) ? $data : array() );
	}

	/* ---------------------------------------------------------------------- */
	/* Host                                                                    */
	/* ---------------------------------------------------------------------- */

	public static function host_token() {

		self::require_config();

		$post_id = self::require_host_post();

		self::require_started( $post_id );

		wp_send_json_success( self::host_jwt( $post_id ) );
	}

	/**
	 * Creates the RTMP ingress the host points OBS at — Service "Custom…",
	 * Server + Stream key, which every encoder supports.
	 *
	 * With `regenerate`, the post's current ingress is deleted first: a leaked
	 * key, or one from a LiveKit project the site no longer uses.
	 */
	public static function create_ingress() {

		self::require_config();

		$post_id = self::require_host_post();

		self::require_started( $post_id );

		if ( get_post_meta( $post_id, 'stream_key', true ) ) {

			if ( empty( $_POST['regenerate'] ) ) {
				wp_send_json_error( array( 'message' => __( 'This live stream already has a stream key.', 'jws_streamvid' ) ) );
			}

			// A failed delete on an old project must not block the new key.
			self::delete_ingress( get_post_meta( $post_id, '_livekit_ingress_id', true ) );

			delete_post_meta( $post_id, '_livekit_ingress_id' );
			delete_post_meta( $post_id, 'stream_key' );
			delete_post_meta( $post_id, 'rtmp_url' );
		}

		$identity = 'user_' . get_current_user_id();
		$request  = array(
			'room_name'            => Jws_Livestream::room_name( $post_id ),
			'participant_identity' => $identity,
			'participant_name'     => $identity,
			'input_type'           => 0, // RTMP_INPUT
		);

		list( $status, $result ) = self::call( 'Ingress/CreateIngress', $request, self::host_jwt( $post_id ) );

		// Out of ingress slots: clear the stale ones and try once more.
		if ( isset( $result['code'] ) && 'resource_exhausted' === $result['code'] && self::prune_ingresses() ) {
			list( $status, $result ) = self::call( 'Ingress/CreateIngress', $request, self::host_jwt( $post_id ) );
		}

		if ( 200 !== $status || empty( $result['url'] ) || empty( $result['stream_key'] ) ) {

			$message = __( 'Could not create the stream key.', 'jws_streamvid' );

			if ( isset( $result['code'] ) && 'resource_exhausted' === $result['code'] ) {
				$message = __( 'Your LiveKit plan has no free stream keys left. End another live stream, or delete unused ingresses in the LiveKit dashboard.', 'jws_streamvid' );
			} elseif ( ! empty( $result['msg'] ) ) {
				$message .= ' ' . sanitize_text_field( $result['msg'] );
			}

			wp_send_json_error( array( 'message' => $message ) );
		}

		// esc_url_raw() drops rtmp(s):// unless the protocols are named.
		update_post_meta( $post_id, 'rtmp_url', esc_url_raw( $result['url'], array( 'rtmp', 'rtmps', 'https', 'http' ) ) );
		update_post_meta( $post_id, 'stream_key', sanitize_text_field( $result['stream_key'] ) );
		update_post_meta( $post_id, '_livekit_ingress_id', sanitize_text_field( $result['ingress_id'] ) );

		wp_send_json_success( array(
			'url'        => $result['url'],
			'stream_key' => $result['stream_key'],
		) );
	}

	/**
	 * The host's End stream button. The webhook ends a stream too, but only
	 * when one is set up in LiveKit; this does not depend on it.
	 */
	public static function end_stream() {

		$post_id = self::require_host_post();

		Jws_Livestream::finish( $post_id );

		wp_send_json_success( array( 'message' => __( 'The live stream has ended.', 'jws_streamvid' ) ) );
	}

	/* ---------------------------------------------------------------------- */
	/* Viewer                                                                  */
	/* ---------------------------------------------------------------------- */

	/**
	 * Watch, chat (data messages) and nothing else: no publishing, no room
	 * admin. Chat needs an account, so guests get canPublishData false.
	 */
	public static function viewer_token() {

		self::require_config();

		$post_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! $post_id || ! Jws_Livestream::is_live_post( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Live stream not found.', 'jws_streamvid' ) ), 404 );
		}

		if ( ! Jws_Livestream::can_watch( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have access to this live stream.', 'jws_streamvid' ) ), 403 );
		}

		if ( ! Jws_Livestream::has_started( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'This live stream has not started yet.', 'jws_streamvid' ) ), 403 );
		}

		$user_id  = get_current_user_id();
		$identity = $user_id ? 'viewer_' . $user_id : 'guest_' . wp_generate_password( 12, false );

		wp_send_json_success( Jws_Livestream_Token::create(
			$identity,
			array(
				'roomJoin'       => true,
				'room'           => Jws_Livestream::room_name( $post_id ),
				'canPublish'     => false,
				'canPublishData' => (bool) $user_id,
				'canSubscribe'   => true,
			),
			array( 'metadata' => wp_json_encode( Jws_Livestream::user_profile( $user_id ) ) )
		) );
	}

	/* ---------------------------------------------------------------------- */
	/* Ingresses                                                               */
	/* ---------------------------------------------------------------------- */

	public static function delete_ingress( $ingress_id ) {

		if ( ! $ingress_id || ! Jws_Livestream::is_configured() ) {
			return false;
		}

		list( $status ) = self::call( 'Ingress/DeleteIngress', array( 'ingress_id' => $ingress_id ), self::ingress_admin_jwt() );

		return 200 === $status;
	}

	/**
	 * Frees ingress slots: deletes the ones not streaming right now whose
	 * stream is no longer live, or whose post no longer uses that key.
	 */
	public static function prune_ingresses() {

		list( $status, $data ) = self::call( 'Ingress/ListIngress', array(), self::ingress_admin_jwt() );

		if ( 200 !== $status || empty( $data['items'] ) ) {
			return 0;
		}

		$deleted = 0;

		foreach ( $data['items'] as $item ) {

			$state = isset( $item['state']['status'] ) ? $item['state']['status'] : '';

			if ( in_array( $state, array( 'ENDPOINT_BUFFERING', 'ENDPOINT_PUBLISHING' ), true ) ) {
				continue;
			}

			$post_id = Jws_Livestream::room_post_id( isset( $item['room_name'] ) ? $item['room_name'] : '' );
			$in_use  = $post_id
				&& Jws_Livestream::is_live_post( $post_id )
				&& 'publish' === get_post_status( $post_id )
				&& get_post_meta( $post_id, 'stream_key', true ) === ( isset( $item['stream_key'] ) ? $item['stream_key'] : '' );

			if ( ! $in_use && self::delete_ingress( $item['ingress_id'] ) ) {
				$deleted++;
			}
		}

		return $deleted;
	}
}
