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
		wp_enqueue_script( 'jws-drama', $url . 'assets/drama.js', array( 'jquery' ), $version, true );

		wp_localize_script(
			'jws-drama',
			'jwsDrama',
			array(
				'ajax_url'    => admin_url( 'admin-ajax.php' ),
				'unlockNonce' => wp_create_nonce( 'jws_drama_unlock' ),
				'loggedIn'    => is_user_logged_in(),
				'i18n'        => array(
					'locked'  => esc_html__( 'This episode is locked.', 'jws_streamvid' ),
					'signIn'  => esc_html__( 'Please sign in first.', 'jws_streamvid' ),
					'failed'  => esc_html__( 'Could not unlock. Please try again.', 'jws_streamvid' ),
					'pickPayment' => esc_html__( 'Choose a payment method first.', 'jws_streamvid' ),
				),
			)
		);
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

		ob_start();
		get_template_part(
			'template-parts/content/movies/layout/layout10',
			'',
			array( 'post_id' => (int) $post_id )
		);

		return ob_get_clean();
	}

	/* ---------------------------------------------------------------------- */
	/* Helpers the templates use                                               */
	/* ---------------------------------------------------------------------- */

	/**
	 * Portrait artwork for a drama, falling back to the featured image.
	 *
	 * Short drama is shot 9:16, so the landscape thumbnail the rest of the site
	 * uses is the wrong crop for a card.
	 */
	public static function poster_url( $drama_id, $size = 'large' ) {

		$poster = get_post_meta( $drama_id, 'drama_poster', true );

		if ( is_array( $poster ) && ! empty( $poster['ID'] ) ) {
			$src = wp_get_attachment_image_src( $poster['ID'], $size );
			if ( $src ) {
				return $src[0];
			}
		}

		if ( is_numeric( $poster ) && $poster ) {
			$src = wp_get_attachment_image_src( $poster, $size );
			if ( $src ) {
				return $src[0];
			}
		}

		if ( is_string( $poster ) && filter_var( $poster, FILTER_VALIDATE_URL ) ) {
			return $poster;
		}

		$thumb = get_the_post_thumbnail_url( $drama_id, $size );

		return $thumb ? $thumb : '';
	}

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
