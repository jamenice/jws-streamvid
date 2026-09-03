<?php

/**
 * Chooses which player engine renders the front-end video player.
 *
 * Two engines ship side by side:
 *
 *  - "legacy" (default) — Video.js 7.20.3 plus the bundled jws_player.js skin,
 *    videojs-ima, chromecast, YouTube/Vimeo techs. This is the original player
 *    and nothing about it changes while this engine is selected.
 *  - "v10" — Video.js 10 (@videojs/html web components). Opt-in.
 *
 * Picked in the theme: Theme Options > Video Global > Player Settings >
 * Player Engine. Reverting is that select, not a code change.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Streamvid_Player_Engine {

	/** Redux field id in the theme's Video Global section. */
	const THEME_OPTION = 'video_player_engine';

	/**
	 * Where the engine used to be stored, before it moved into the theme.
	 * Read once as a fallback so a site that had already switched to v10 does
	 * not silently drop back to the old player; drop this, and the read below,
	 * once every site has saved its theme options at least once.
	 */
	const LEGACY_OPTION = 'jws_streamvid_player_engine';

	const ENGINE_LEGACY = 'legacy';
	const ENGINE_V10    = 'v10';

	/** Resolved once per request so markup and enqueues never disagree. */
	private static $resolved = null;

	/**
	 * Which engine is active for this request.
	 *
	 * Order of precedence:
	 *   1. ?jws_player=v10|legacy   (preview, editors only — never for visitors)
	 *   2. JWS_STREAMVID_PLAYER_ENGINE constant (wp-config.php)
	 *   3. Theme Options > Video Global > Player Engine
	 *   4. legacy
	 *
	 * @return string
	 */
	public static function current() {

		if ( null !== self::$resolved ) {
			return self::$resolved;
		}

		$engine = self::ENGINE_LEGACY;

		$saved = get_option( self::LEGACY_OPTION, '' );
		if ( self::is_valid( $saved ) ) {
			$engine = $saved;
		}

		if ( function_exists( 'jws_theme_get_option' ) ) {
			$theme = jws_theme_get_option( self::THEME_OPTION );

			if ( self::is_valid( $theme ) ) {
				$engine = $theme;
			}
		}

		if ( defined( 'JWS_STREAMVID_PLAYER_ENGINE' ) && self::is_valid( JWS_STREAMVID_PLAYER_ENGINE ) ) {
			$engine = JWS_STREAMVID_PLAYER_ENGINE;
		}

		// Preview switch, so the new player can be checked on a live site without
		// flipping it on for everyone. Gated on a capability so page caches keyed
		// on the URL can't serve a previewed player to visitors.
		if ( isset( $_GET['jws_player'] ) && current_user_can( 'edit_posts' ) ) {
			$preview = sanitize_key( wp_unslash( $_GET['jws_player'] ) );
			if ( self::is_valid( $preview ) ) {
				$engine = $preview;
			}
		}

		self::$resolved = apply_filters( 'streamvid/player/engine', $engine );

		if ( ! self::is_valid( self::$resolved ) ) {
			self::$resolved = self::ENGINE_LEGACY;
		}

		return self::$resolved;
	}

	public static function is_v10() {
		return self::ENGINE_V10 === self::current();
	}

	public static function is_valid( $engine ) {
		return in_array( $engine, array( self::ENGINE_LEGACY, self::ENGINE_V10 ), true );
	}

	/**
	 * Base URL the v10 ES modules are loaded from, with a trailing slash.
	 *
	 * Always the bundle shipped with the plugin. The entry files import
	 * content-hashed siblings, so the whole cdn/ directory has to be mirrored
	 * into public/assets/videojs10/ — see README-PLAYER-V10.md. A partial copy
	 * still serves 200 for the entry and then fails at import time.
	 */
	public static function v10_base_url() {
		return trailingslashit( JWS_STREAMVID_URL_PUBLIC_ASSETS . '/videojs10' );
	}

	public static function has_self_hosted_bundle() {
		return file_exists( trailingslashit( JWS_STREAMVID_PATH_PUBLIC ) . 'assets/videojs10/video.js' );
	}
}
