<?php

/**
 * Front-end templates for short drama.
 *
 * The plugin ships the archive and single templates itself rather than adding
 * them to the theme, so the whole module stays in one folder. A theme can still
 * take any of them over by dropping a file of the same name in its root — the
 * theme copy always wins.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/drama
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Drama_Templates {

	/** Set by locate() so the enqueue below only fires on drama screens. */
	private $is_drama_screen = false;

	private function dir() {
		return plugin_dir_path( __FILE__ ) . 'templates/';
	}

	private function url() {
		return plugin_dir_url( __FILE__ );
	}

	/**
	 * Points WordPress at the plugin's template unless the theme has its own.
	 */
	public function locate( $template ) {

		$drama   = Jws_Drama_Post_Types::DRAMA;
		$episode = Jws_Drama_Post_Types::EPISODE;

		$file = '';

		if ( is_post_type_archive( $drama ) || is_tax( Jws_Drama_Post_Types::TAX_TAG ) || ( is_tax( array( 'genres', 'countries', 'ages' ) ) && $drama === get_query_var( 'post_type' ) ) ) {
			$file = 'archive-drama.php';
		} elseif ( is_singular( $drama ) ) {
			$file = 'single-drama.php';
		} elseif ( is_singular( $episode ) ) {
			$file = 'single-drama_ep.php';
		}

		if ( ! $file ) {
			return $template;
		}

		$this->is_drama_screen = true;

		$theme = locate_template( array( $file ) );

		if ( $theme ) {
			return $theme;
		}

		$plugin = $this->dir() . $file;

		return file_exists( $plugin ) ? $plugin : $template;
	}

	public function enqueue() {

		/*
		 * The account area is not a drama screen, but the Coins tab renders from
		 * this module and needs the same stylesheet — without this the wallet
		 * comes out unstyled.
		 */
		if ( ! $this->is_drama_screen && ! is_author() ) {
			return;
		}

		self::assets();

		/*
		 * The chat widget floats over the same corner as the stage controls
		 * and the unlock button on the watch screens — singular only, the
		 * archive has room for it.
		 */
		if ( is_singular( array( Jws_Drama_Post_Types::DRAMA, Jws_Drama_Post_Types::EPISODE ) ) ) {
			wp_add_inline_style( 'jws-drama', '#svcChatWidget{display:none !important;}' );
		}
	}

	/**
	 * Loads the module's stylesheet and script.
	 *
	 * Public and static because a drama card can now appear outside a drama
	 * screen — the theme's Layout 10 renders it from any page — and that
	 * template has no way back to the instance. wp_enqueue_* deduplicates by
	 * handle, so calling this again from a template body is free; WordPress
	 * prints what arrives after wp_head in the footer.
	 */
	public static function assets() {

		$version = defined( 'JWS_STREAMVID_VERSION' ) ? JWS_STREAMVID_VERSION : '1.0.0';
		$url     = plugin_dir_url( __FILE__ );

		wp_enqueue_style( 'jws-drama', $url . 'assets/drama.css', array(), $version );

		$ad_mode = class_exists( 'Jws_Drama_Ad_Unlock' ) && Jws_Drama_Ad_Unlock::enabled()
			? Jws_Drama_Ad_Unlock::mode()
			: '';

		/*
		 * The rewarded break talks to google.ima directly, the way
		 * jws_player_v10.js does — videojs-ima was never ported to v10. Same
		 * handle the movie player enqueues it under, so this costs nothing on a
		 * site that already runs ads and is what makes the break work on one
		 * that does not.
		 */
		if ( 'video' === $ad_mode ) {
			wp_enqueue_script( 'googleapis-imasdk', '//imasdk.googleapis.com/js/sdkloader/ima3.js', array(), $version, true );
		}

		wp_enqueue_script( 'jws-drama', $url . 'assets/drama.js', array( 'jquery' ), $version, true );

		wp_localize_script(
			'jws-drama',
			'jwsDrama',
			array(
				'ajax_url'    => admin_url( 'admin-ajax.php' ),
				'unlockNonce' => wp_create_nonce( 'jws_drama_unlock' ),
				/* Its own nonce because its two actions have nopriv twins: the
				   ad unlock is open to viewers who have not signed in. */
				'adNonce'     => wp_create_nonce( 'jws_drama_ad' ),
				'adMode'      => $ad_mode,
				'adTag'       => 'video' === $ad_mode ? Jws_Drama_Ad_Unlock::vast_tag() : '',
				'adReward'    => 'video' === $ad_mode ? Jws_Drama_Ad_Unlock::reward_at() : '',
				'loggedIn'    => is_user_logged_in(),
				'i18n'        => array(
					/* translators: %d: seconds left before the episode opens */
					'adWait'        => esc_html__( 'Unlocking in %ds', 'jws_streamvid' ),
					'adClaiming'    => esc_html__( 'Unlocking…', 'jws_streamvid' ),
					'adLoading'     => esc_html__( 'Loading the ad…', 'jws_streamvid' ),
					/* What was missing, in the terms this site actually asks for:
					   telling someone to watch to the end when half of it would
					   have done is a wrong answer, not a strict one. */
					'adNotFinished' => self::ad_reward_message(),
					'adNoAd'        => esc_html__( 'No ad available right now. Please try again.', 'jws_streamvid' ),
					/* The stage's own prev/next arrows, which drama.js rebuilds
					   while a placeholder stands in for an episode still being
					   fetched — the markup it copies them from is gone by then. */
					'prevEpisode' => esc_attr__( 'Previous episode', 'jws_streamvid' ),
					'nextEpisode' => esc_attr__( 'Next episode', 'jws_streamvid' ),
					'locked'  => esc_html__( 'This episode is locked.', 'jws_streamvid' ),
					'signIn'  => esc_html__( 'Please sign in first.', 'jws_streamvid' ),
					'failed'  => esc_html__( 'Could not unlock. Please try again.', 'jws_streamvid' ),
					'pickPayment' => esc_html__( 'Choose a payment method first.', 'jws_streamvid' ),
				),
			)
		);
	}

	/** What the viewer is told when a break did not earn the episode. */
	private static function ad_reward_message() {

		if ( ! class_exists( 'Jws_Drama_Ad_Unlock' ) ) {
			return esc_html__( 'Watch the ad to the end to open this episode.', 'jws_streamvid' );
		}

		switch ( Jws_Drama_Ad_Unlock::reward_at() ) {

			case 'midpoint':
				return esc_html__( 'Watch at least half of the ad to open this episode.', 'jws_streamvid' );

			case 'start':
				/* Nothing was skipped here — the ad never got as far as
				   playing, which is a different problem entirely. */
				return esc_html__( 'The ad did not play. Please try again.', 'jws_streamvid' );

			default:
				return esc_html__( 'Watch the ad to the end to open this episode.', 'jws_streamvid' );
		}
	}

	/* ---------------------------------------------------------------------- */
	/* Archive filter (AJAX)                                                   */
	/* ---------------------------------------------------------------------- */

	/**
	 * Lets the theme's archive filter run for drama.
	 *
	 * Without this the handler answers "Invalid post type" and the grid is never
	 * replaced — which also means the staggered `jws-animated` class the theme
	 * adds to the returned items never lands, so filtering looked like it did
	 * nothing at all.
	 */
	public function allow_in_filter( $types ) {

		$types[] = Jws_Drama_Post_Types::DRAMA;

		return $types;
	}

	/**
	 * Drama has no `drama_cat`; its categories are the site-wide genres, so the
	 * filter's category dropdown has to read that vocabulary instead of the
	 * `{post_type}_cat` it assumes.
	 */
	public function filter_taxonomy( $taxonomy, $post_type ) {

		return Jws_Drama_Post_Types::DRAMA === $post_type ? 'genres' : $taxonomy;
	}

	/**
	 * Renders a drama card into the filter response.
	 *
	 * The handler would otherwise reach for
	 * template-parts/content/drama/layout/… in the theme, which does not exist.
	 * The card is the theme's Layout 10 — the same one the archive and the
	 * movies_advanced widget render — so point the handler at it.
	 */
	public function filter_item_html( $html, $post_type, $args_item, $post_id ) {

		if ( Jws_Drama_Post_Types::DRAMA !== $post_type ) {
			return $html;
		}

		$item = array( 'post_id' => (int) $post_id );

		if ( ! empty( $args_item['image_size'] ) ) {
			$item['image_size'] = $args_item['image_size'];
		}

		ob_start();
		get_template_part( 'template-parts/content/movies/layout/layout10', '', $item );

		return ob_get_clean();
	}

	/* ---------------------------------------------------------------------- */
	/* Helpers the templates use                                               */
	/* ---------------------------------------------------------------------- */

	/** Genre-ish terms shown as links, e.g. the tag list on the watch screen. */
	public static function genre_terms( $drama_id, $limit = 3 ) {

		$terms_by_id = array();

		foreach ( array( 'genres', 'topics' ) as $taxonomy ) {

			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$terms = get_the_terms( $drama_id, $taxonomy );

			if ( $terms && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$terms_by_id[ $term->term_id ] = $term;
				}
			}

			if ( count( $terms_by_id ) >= $limit ) {
				break;
			}
		}

		return array_slice( array_values( $terms_by_id ), 0, $limit );
	}

	/** Genre-ish labels shown under a card title, e.g. "Royalty | Shifter". */
	public static function genre_names( $drama_id, $limit = 3 ) {

		return wp_list_pluck( self::genre_terms( $drama_id, $limit ), 'name' );
	}

	/**
	 * The episode a bare drama URL should open on: where this viewer left off,
	 * otherwise the first one.
	 */
	public static function entry_episode( $drama_id ) {

		$episodes = Jws_Drama_Post_Types::episodes_of( $drama_id );

		if ( empty( $episodes ) ) {
			return 0;
		}

		$user_id = get_current_user_id();

		if ( $user_id ) {
			$last = (int) get_user_meta( $user_id, 'jws_drama_last_ep_' . (int) $drama_id, true );

			if ( $last && in_array( $last, array_map( 'intval', $episodes ), true ) ) {
				return $last;
			}
		}

		return (int) $episodes[0];
	}

	/** Remembers where a signed-in viewer got to, for the next visit. */
	public static function remember_episode( $drama_id, $episode_id ) {

		$user_id = get_current_user_id();

		if ( $user_id && $drama_id && $episode_id ) {
			update_user_meta( $user_id, 'jws_drama_last_ep_' . (int) $drama_id, (int) $episode_id );
		}
	}
}
