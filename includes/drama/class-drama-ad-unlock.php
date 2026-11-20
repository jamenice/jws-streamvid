<?php

/**
 * Ad-for-access: one viewing of a locked episode in exchange for an ad — a
 * video break watched to the end, or a visit to an advertiser's link.
 *
 * Nothing is written down. The grant lives for the length of the request that
 * redeems it — long enough to render the stage with a player in it — and the
 * page keeps that markup for as long as the viewer stays on it. Reload and the
 * episode is behind the paywall again, which is what keeps this from competing
 * with the coin unlock, the one that is a purchase and is recorded.
 *
 * The ticket is what stops the claim being replayed: it is issued by the
 * server, single-use, bound to the episode and the viewer, and refused until
 * enough time has passed for the click to have been worth anything. That is the
 * most a web page can honestly prove about an ad — a click cannot be verified
 * from here, so the numbers that matter are the daily cap and the wait.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/drama
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Drama_Ad_Unlock {

	/** How long an unredeemed ticket stays valid. */
	const TICKET_TTL = 900;

	/**
	 * Episodes this request may render unlocked.
	 *
	 * Request-scoped on purpose: it is the whole of the grant's storage.
	 *
	 * @var array<int,bool>
	 */
	private static $granted = array();

	public function register() {
		/* Jws_Drama_Wallet::access() runs this filter on the locked branch
		   only, so a grant can never dress up a free, VIP or bought episode as
		   something an ad paid for. */
		add_filter( 'streamvid/drama/access', array( __CLASS__, 'apply_grant' ), 10, 2 );
	}

	/* ---------------------------------------------------------------------- */
	/* Settings                                                                */
	/* ---------------------------------------------------------------------- */

	/** Off unless it is switched on and the ad it would show is configured. */
	public static function enabled() {

		if ( ! class_exists( 'Jws_Drama_Settings' ) || ! Jws_Drama_Settings::get( 'ad_unlock' ) ) {
			return false;
		}

		return 'video' === self::mode() ? '' !== self::vast_tag() : '' !== self::link();
	}

	/** 'video' for a VAST break played in the stage, 'link' for a visit. */
	public static function mode() {

		$mode = class_exists( 'Jws_Drama_Settings' )
			? (string) Jws_Drama_Settings::get( 'ad_unlock_mode', 'link' )
			: 'link';

		return 'video' === $mode ? 'video' : 'link';
	}

	public static function link() {
		return class_exists( 'Jws_Drama_Settings' )
			? trim( (string) Jws_Drama_Settings::get( 'ad_unlock_url', '' ) )
			: '';
	}

	/**
	 * The VAST/VMAP tag the rewarded break is requested from.
	 *
	 * An Advertising post first, resolved the way the movie player resolves its
	 * own tags (Jws_Streamvid_Advertising::check_tag_url): a post pointed at an
	 * external ad server answers with the URL it was given, and every other one
	 * answers with its own permalink, which is where this plugin serves the XML
	 * it built. The pasted URL underneath is the way out for a site with no
	 * Advertising posts at all.
	 */
	public static function vast_tag() {

		if ( ! class_exists( 'Jws_Drama_Settings' ) ) {
			return '';
		}

		$post_id = (int) Jws_Drama_Settings::get( 'ad_unlock_tag', 0 );

		if ( $post_id && 'publish' === get_post_status( $post_id ) ) {

			if ( 'vast' === get_post_meta( $post_id, 'ads_server', true ) ) {
				$url = trim( (string) get_post_meta( $post_id, 'ads_vast_url', true ) );

				if ( '' !== $url ) {
					return $url;
				}
			}

			return (string) get_permalink( $post_id );
		}

		return trim( (string) Jws_Drama_Settings::get( 'ad_unlock_vast_url', '' ) );
	}

	/**
	 * How much of a video ad has to be watched: 'complete', 'midpoint' or
	 * 'start'.
	 *
	 * A creative that declares a skipoffset makes IMA draw a Skip button, and
	 * nothing on this side can take it away — the offer belongs to the ad, not
	 * to the player. So either the tag serves an ad with no skipoffset, or the
	 * reward has to be worth less than the whole thing; this is that choice.
	 */
	public static function reward_at() {

		$at = class_exists( 'Jws_Drama_Settings' )
			? (string) Jws_Drama_Settings::get( 'ad_unlock_reward', 'complete' )
			: 'complete';

		return in_array( $at, array( 'complete', 'midpoint', 'start' ), true ) ? $at : 'complete';
	}

	/** Seconds the link mode counts down before the episode can be claimed. */
	public static function seconds() {

		$seconds = class_exists( 'Jws_Drama_Settings' )
			? (int) Jws_Drama_Settings::get( 'ad_unlock_seconds', 15 )
			: 15;

		return max( 3, min( 120, $seconds ) );
	}

	/**
	 * The wait a claim is really held to.
	 *
	 * In video mode the wait is the ad's own length, which the server has no
	 * way of knowing — IMA reports the break to the browser and to nobody else.
	 * What is left to enforce is a floor: low enough for the shortest bumper,
	 * high enough that a claim cannot chase its own ticket.
	 */
	public static function min_seconds() {
		return 'video' === self::mode() ? 4 : self::seconds();
	}

	/** Claims allowed per viewer per day; 0 for no cap. */
	public static function daily_cap() {
		return class_exists( 'Jws_Drama_Settings' )
			? max( 0, (int) Jws_Drama_Settings::get( 'ad_unlock_daily', 0 ) )
			: 0;
	}

	/* ---------------------------------------------------------------------- */
	/* The grant                                                               */
	/* ---------------------------------------------------------------------- */

	/**
	 * Opens one episode for the rest of this request.
	 *
	 * @param int $episode_id
	 */
	public static function grant( $episode_id ) {
		self::$granted[ (int) $episode_id ] = true;
	}

	/**
	 * @param array $access The access decision so far.
	 * @param int   $episode_id
	 * @return array
	 */
	public static function apply_grant( $access, $episode_id ) {

		if ( empty( self::$granted[ (int) $episode_id ] ) ) {
			return $access;
		}

		$access['can_watch'] = true;
		$access['reason']    = 'ad';

		return $access;
	}

	/* ---------------------------------------------------------------------- */
	/* Tickets                                                                 */
	/* ---------------------------------------------------------------------- */

	/**
	 * Who is asking, for a feature that is open to signed-out viewers too.
	 *
	 * A logged-in viewer is their user id. Everyone else is a hash of the
	 * address and browser they arrived with — not an identity, just something
	 * stable enough to hang a daily count on and to stop one visitor redeeming
	 * a ticket issued to another.
	 */
	private static function viewer() {

		$user_id = get_current_user_id();

		if ( $user_id ) {
			return 'u' . $user_id;
		}

		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		return 'g' . substr( wp_hash( $ip . '|' . $agent ), 0, 16 );
	}

	private static function ticket_key( $token ) {
		return 'jws_drama_ad_' . md5( (string) $token );
	}

	private static function count_key() {
		return 'jws_drama_adn_' . self::viewer() . '_' . gmdate( 'Ymd' );
	}

	/** How many more episodes this viewer may open today. */
	public static function remaining() {

		$cap = self::daily_cap();

		if ( ! $cap ) {
			return PHP_INT_MAX;
		}

		return max( 0, $cap - (int) get_transient( self::count_key() ) );
	}

	/**
	 * Hands out a ticket for one episode.
	 *
	 * @param int $episode_id
	 * @return array { @type bool $success, @type string $reason, @type string $token }
	 */
	public static function start( $episode_id ) {

		$episode_id = (int) $episode_id;

		if ( ! self::enabled() ) {
			return array( 'success' => false, 'reason' => 'disabled', 'token' => '' );
		}

		if ( ! $episode_id || Jws_Drama_Post_Types::EPISODE !== get_post_type( $episode_id ) ) {
			return array( 'success' => false, 'reason' => 'invalid', 'token' => '' );
		}

		/* Already watchable — free, VIP, or bought. There is nothing here for an
		   ad to pay for, and issuing a ticket would spend the viewer's daily
		   allowance on an episode they could already open. */
		if ( Jws_Drama_Wallet::can_watch( $episode_id ) ) {
			return array( 'success' => false, 'reason' => 'not_locked', 'token' => '' );
		}

		if ( ! self::remaining() ) {
			return array( 'success' => false, 'reason' => 'daily_limit', 'token' => '' );
		}

		$token = wp_generate_password( 32, false, false );

		set_transient(
			self::ticket_key( $token ),
			array(
				'episode' => $episode_id,
				'viewer'  => self::viewer(),
				'issued'  => time(),
			),
			self::TICKET_TTL
		);

		return array( 'success' => true, 'reason' => 'ok', 'token' => $token );
	}

	/**
	 * Redeems a ticket. On success the episode is open for the rest of this
	 * request and the caller can render its stage.
	 *
	 * @param int    $episode_id
	 * @param string $token
	 * @return array { @type bool $success, @type string $reason }
	 */
	public static function claim( $episode_id, $token ) {

		$episode_id = (int) $episode_id;

		if ( ! self::enabled() ) {
			return array( 'success' => false, 'reason' => 'disabled' );
		}

		$ticket = $token ? get_transient( self::ticket_key( $token ) ) : false;

		if ( ! is_array( $ticket ) ) {
			return array( 'success' => false, 'reason' => 'expired' );
		}

		/* Single use, and spent whatever the outcome below: a ticket that has
		   been looked at once must never be worth a second look, or a claim
		   refused for being too early could simply be retried in a loop until
		   the clock caught up with it. */
		delete_transient( self::ticket_key( $token ) );

		if ( (int) $ticket['episode'] !== $episode_id || $ticket['viewer'] !== self::viewer() ) {
			return array( 'success' => false, 'reason' => 'mismatch' );
		}

		if ( ( time() - (int) $ticket['issued'] ) < self::min_seconds() ) {
			return array( 'success' => false, 'reason' => 'too_soon' );
		}

		if ( ! self::remaining() ) {
			return array( 'success' => false, 'reason' => 'daily_limit' );
		}

		self::count();
		self::grant( $episode_id );

		/* Nothing to reconcile and no row to write, so this is the only trace
		   an integration can hang anything off. */
		do_action( 'streamvid/drama/episode_ad_unlocked', $episode_id, get_current_user_id() );

		return array( 'success' => true, 'reason' => 'ok' );
	}

	/** Counts one claim against today's cap. */
	private static function count() {

		if ( ! self::daily_cap() ) {
			return;
		}

		$key = self::count_key();

		/* Two days rather than one: the count is keyed by UTC date and the
		   transient only has to outlive the day it belongs to. */
		set_transient( $key, (int) get_transient( $key ) + 1, 2 * DAY_IN_SECONDS );
	}
}
