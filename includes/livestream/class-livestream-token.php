<?php

/**
 * LiveKit access tokens: HS256 JWTs signed with the API secret.
 *
 * The claims are the ones the official server SDKs write — iss (API key),
 * sub / jti (identity), nbf, iat, exp, name, metadata and the `video` grant
 * (room, roomJoin, canPublish, canSubscribe, canPublishData, ingressAdmin…).
 * decode() checks a webhook's Authorization header the same way.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/livestream
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Livestream_Token {

	/** Four hours, the SDK default. Only joining needs a valid token. */
	const TTL = 14400;

	/** Clock skew allowed between this server and LiveKit. */
	const LEEWAY = 60;

	/**
	 * @param string $identity Participant identity; '' for a server token.
	 * @param array  $video    The video grant, e.g. [ 'roomJoin' => true, 'room' => 'live_room_5' ].
	 * @param array  $claims   name, metadata.
	 */
	public static function create( $identity, array $video, array $claims = array() ) {

		$now     = time();
		$payload = array();

		if ( '' !== (string) $identity ) {
			$payload['sub'] = (string) $identity;
			$payload['jti'] = (string) $identity;
		}

		$payload += array(
			'exp' => $now + self::TTL,
			'nbf' => $now,
			'iat' => $now,
			'iss' => Jws_Livestream::api_key(),
		);

		foreach ( array( 'name', 'metadata' ) as $claim ) {
			if ( isset( $claims[ $claim ] ) && '' !== $claims[ $claim ] ) {
				$payload[ $claim ] = (string) $claims[ $claim ];
			}
		}

		$payload['video'] = (object) $video;

		return self::encode( $payload, Jws_Livestream::api_secret() );
	}

	public static function encode( array $payload, $secret ) {

		$segments = array(
			self::base64url( wp_json_encode( array( 'alg' => 'HS256', 'typ' => 'JWT' ) ) ),
			self::base64url( wp_json_encode( $payload ) ),
		);

		$segments[] = self::base64url( hash_hmac( 'sha256', implode( '.', $segments ), $secret, true ) );

		return implode( '.', $segments );
	}

	/**
	 * The claims of a token signed with our API secret and issued for our API
	 * key, or false.
	 */
	public static function decode( $jwt ) {

		$secret = Jws_Livestream::api_secret();
		$parts  = explode( '.', trim( (string) $jwt ) );

		if ( ! $secret || 3 !== count( $parts ) ) {
			return false;
		}

		$header = json_decode( self::base64url_decode( $parts[0] ), true );

		if ( ! is_array( $header ) || ! isset( $header['alg'] ) || 'HS256' !== $header['alg'] ) {
			return false;
		}

		$signature = self::base64url( hash_hmac( 'sha256', $parts[0] . '.' . $parts[1], $secret, true ) );

		if ( ! hash_equals( $signature, $parts[2] ) ) {
			return false;
		}

		$claims = json_decode( self::base64url_decode( $parts[1] ), true );
		$now    = time();

		if ( ! is_array( $claims ) || ! isset( $claims['iss'] ) || Jws_Livestream::api_key() !== $claims['iss'] ) {
			return false;
		}

		if ( ( isset( $claims['exp'] ) && $claims['exp'] < $now - self::LEEWAY ) || ( isset( $claims['nbf'] ) && $claims['nbf'] > $now + self::LEEWAY ) ) {
			return false;
		}

		return $claims;
	}

	private static function base64url( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	private static function base64url_decode( $data ) {
		return (string) base64_decode( strtr( $data, '-_', '+/' ) );
	}
}
