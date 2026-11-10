<?php

/**
 * Hides content a viewer profile may not watch.
 *
 * A profile is restricted when it is a Kids profile or its maturity is set
 * below "all ratings". Ratings come from the `ages` taxonomy: each term has
 * a maturity in years (term meta `jws_maturity`, set on the Age rating
 * screen, or worked out from the usual slugs g, pg, pg-13, r, nc-17).
 *
 * - Movies, TV shows and dramas rated above the profile's maturity are hidden.
 * - Kids profiles also hide those titles with no Age rating, plus every
 *   video upload, live stream and TV channel (KIDS_HIDDEN_TYPES).
 * - Episodes and drama episodes follow the show or drama they belong to.
 *
 * Lists are filtered in pre_get_posts by adding the hidden ids to
 * post__not_in, which also reaches get_posts() calls (they set
 * suppress_filters, so SQL filters such as posts_where would not run).
 * Opening a hidden title directly redirects to the home page, and the AJAX
 * actions that play a title refuse hidden ones.
 *
 * Accounts without a restricted profile in use, and the mobile app API
 * (which always reads the main profile), are not affected.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Profile_Filter {

	/** Post types rated with the `ages` taxonomy. */
	const RATED_TYPES = array( 'movies', 'tv_shows', 'drama' );

	/** Post types rated through their parent: type => meta key holding the parent id. */
	const CHILD_TYPES = array(
		'episodes' => 'tv_show_id',
		'drama_ep' => 'drama_id',
	);

	/** Never shown on a Kids profile: user uploads, live streams, live TV. */
	const KIDS_HIDDEN_TYPES = array( 'videos', 'tv_channel' );

	/** Term meta on `ages`: maturity in years, one of Jws_Profiles::MATURITY_LEVELS. */
	const TERM_META = 'jws_maturity';

	/** Maturity for the usual rating slugs when the term meta is not set. */
	const SLUG_MATURITY = array(
		'g'     => 0,
		'pg'    => 7,
		'pg-13' => 13,
		'r'     => 17,
		'nc-17' => 18,
	);

	/** Query var: set true on a WP_Query to leave it unfiltered. */
	const SKIP_VAR = 'jws_skip_profile_filter';

	/** Query var carrying post types to drop in SQL when the query did not name its types. */
	const HIDE_TYPES_VAR = 'jws_profile_hide_types';

	/** Per request: false, or array( 'kids' => bool, 'max' => int ). */
	private static $restriction = null;

	/** Per request: hidden post id => true. */
	private static $blocked = null;

	/* ------------------------------------------------------------------ *
	 * Answers
	 * ------------------------------------------------------------------ */

	/**
	 * The restriction of the profile in use.
	 *
	 * @return array|false array( 'kids' => bool, 'max' => int ), or false when nothing is hidden.
	 */
	public static function restriction() {

		if ( null !== self::$restriction ) {
			return self::$restriction;
		}

		self::$restriction = false;

		if ( is_user_logged_in() && ! self::is_builder_preview() && Jws_Profiles::enabled() ) {

			$profile = Jws_Profiles::current_profile();

			if ( $profile && ( $profile->is_kid || $profile->max_maturity < Jws_Profiles::MATURITY_ALL ) ) {
				self::$restriction = array(
					'kids' => $profile->is_kid,
					'max'  => $profile->max_maturity,
				);
			}
		}

		return self::$restriction;
	}

	/** Whether the profile in use may open this post. */
	public static function can_view( $post_id ) {

		$restriction = self::restriction();

		if ( ! $restriction ) {
			return true;
		}

		if ( $restriction['kids'] && in_array( get_post_type( $post_id ), self::kids_hidden_types(), true ) ) {
			return false;
		}

		return ! isset( self::blocked_map()[ (int) $post_id ] );
	}

	/** Maturity in years for an `ages` term; unknown ratings count as adults only. */
	public static function term_maturity( $term ) {

		$term = get_term( $term, 'ages' );

		if ( ! $term || is_wp_error( $term ) ) {
			return Jws_Profiles::MATURITY_ALL;
		}

		$meta = get_term_meta( $term->term_id, self::TERM_META, true );

		if ( '' !== $meta && null !== $meta && in_array( (int) $meta, Jws_Profiles::MATURITY_LEVELS, true ) ) {
			return (int) $meta;
		}

		$slug = strtolower( $term->slug );

		return isset( self::SLUG_MATURITY[ $slug ] ) ? self::SLUG_MATURITY[ $slug ] : Jws_Profiles::MATURITY_ALL;
	}

	/** @return string[] Post types a Kids profile never sees. */
	public static function kids_hidden_types() {

		/**
		 * Filters the post types a Kids profile never sees.
		 *
		 * @param string[] $types Post type names.
		 */
		return (array) apply_filters( 'jws_profile_kids_hidden_types', self::KIDS_HIDDEN_TYPES );
	}

	/* ------------------------------------------------------------------ *
	 * For code that picks titles without WP_Query (raw SQL, hand-picked
	 * lists), as filters so the theme works without this plugin:
	 *
	 *   apply_filters( 'jws_profile_visible_post_types', $types )
	 *   apply_filters( 'jws_profile_hidden_post_ids', array() )
	 *   apply_filters( 'jws_profile_visible_ids', $ids )
	 * ------------------------------------------------------------------ */

	/** $types without the ones the profile in use never sees (Kids: videos, live streams). */
	public static function visible_post_types( $types ) {

		$restriction = self::restriction();
		$types       = (array) $types;

		return $restriction && $restriction['kids'] ? array_values( array_diff( $types, self::kids_hidden_types() ) ) : $types;
	}

	/** IDs of the titles the profile in use may not see, added to $ids. */
	public static function hidden_post_ids( $ids ) {

		$hidden = array_keys( self::blocked_map() );

		return $hidden ? array_merge( (array) $ids, $hidden ) : (array) $ids;
	}

	/** $ids without the titles the profile in use may not see, in the same order. */
	public static function visible_ids( $ids ) {
		return array_values( array_filter( (array) $ids, array( __CLASS__, 'can_view' ) ) );
	}

	/* ------------------------------------------------------------------ *
	 * Hooks
	 * ------------------------------------------------------------------ */

	public static function filter_query( $query ) {

		if ( ( is_admin() && ! wp_doing_ajax() ) || $query->get( self::SKIP_VAR ) ) {
			return;
		}

		// A hidden title opened directly is redirected by guard_request(); filtering it here would 404 first.
		if ( $query->is_main_query() && $query->is_singular() ) {
			return;
		}

		$restriction = self::restriction();

		if ( ! $restriction ) {
			return;
		}

		if ( $restriction['kids'] && ! self::drop_hidden_types( $query ) ) {
			return;
		}

		if ( ! self::may_return_rated( $query ) ) {
			return;
		}

		$blocked = self::blocked_map();

		if ( ! $blocked ) {
			return;
		}

		/*
		 * WordPress ignores post__not_in when p or post__in is set, so a query
		 * for given ids gets the hidden ones removed from its list instead.
		 */
		$p = (int) $query->get( 'p' );

		if ( $p && isset( $blocked[ $p ] ) ) {
			self::match_nothing( $query );
			return;
		}

		$in = array_filter( array_map( 'intval', (array) $query->get( 'post__in' ) ) );

		if ( $in ) {
			$left = array_values( array_filter( $in, function ( $id ) use ( $blocked ) {
				return ! isset( $blocked[ $id ] );
			} ) );
			if ( ! $left ) {
				self::match_nothing( $query );
			} else {
				$query->set( 'post__in', $left );
			}
			return;
		}

		$not_in = array_map( 'intval', (array) $query->get( 'post__not_in' ) );

		$query->set( 'post__not_in', array_values( array_unique( array_merge( $not_in, array_keys( $blocked ) ) ) ) );
	}

	/** Drops Kids-hidden post types from queries that did not name their types (taxonomy archives). */
	public static function hide_types_where( $where, $query ) {

		$types = $query->get( self::HIDE_TYPES_VAR );

		if ( ! $types || ! is_array( $types ) ) {
			return $where;
		}

		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );

		return $where . $wpdb->prepare( " AND {$wpdb->posts}.post_type NOT IN ({$placeholders})", $types ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	}

	/** Sends a restricted profile that opens a hidden title back to the home page. */
	public static function guard_request() {

		$restriction = self::restriction();

		if ( ! $restriction ) {
			return;
		}

		$hidden = false;

		if ( is_singular() ) {
			$hidden = ! self::can_view( get_queried_object_id() );
		} elseif ( $restriction['kids'] ) {
			$types  = self::kids_hidden_types();
			$hidden = is_post_type_archive( $types ) || ( self::hidden_taxonomies() && is_tax( self::hidden_taxonomies() ) );
		}

		if ( $hidden ) {
			$home = $restriction['kids'] ? Jws_Profiles_Settings::kids_home_url() : home_url( '/' );
			wp_safe_redirect( add_query_arg( 'jws_blocked', 1, $home ) );
			exit;
		}
	}

	/**
	 * Refuses AJAX requests a restricted profile may not make: playing a
	 * hidden title, and on a Kids profile, buying, reviewing, chatting,
	 * going live and managing devices.
	 *
	 * Runs on admin_init, which admin-ajax.php fires before the action's
	 * handler. It only refuses; the handlers keep their own nonce and
	 * permission checks.
	 */
	public static function guard_ajax() {

		if ( ! wp_doing_ajax() || empty( $_REQUEST['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$action = sanitize_key( wp_unslash( $_REQUEST['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Account changes: never from a Kids profile, and only from the main one when Theme Options says so.
		if ( is_user_logged_in() && in_array( $action, self::account_actions(), true ) && ! Jws_Profiles_Front::can_manage_account() ) {
			self::deny(
				Jws_Profiles_Front::current_is_kid()
					? __( 'This is not available on a Kids profile.', 'jws_streamvid' )
					: __( 'Switch to the main profile to change the account.', 'jws_streamvid' )
			);
		}

		$restriction = self::restriction();

		if ( ! $restriction ) {
			return;
		}

		if ( $restriction['kids'] && in_array( $action, self::kids_blocked_actions(), true ) ) {
			self::deny( __( 'This is not available on a Kids profile.', 'jws_streamvid' ) );
		}

		$fields = self::ajax_post_fields();

		if ( isset( $fields[ $action ] ) && isset( $_REQUEST[ $fields[ $action ] ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			$post_id = absint( wp_unslash( $_REQUEST[ $fields[ $action ] ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( $post_id && ! self::can_view( $post_id ) ) {
				self::deny( __( 'That title is not available on this profile.', 'jws_streamvid' ) );
			}
		}
	}

	/** Forgets the per-request answers, after the profile in use changed. */
	public static function reset() {
		self::$restriction = null;
		self::$blocked     = null;
	}

	/* ------------------------------------------------------------------ *
	 * Internals
	 * ------------------------------------------------------------------ */

	/**
	 * Hidden post ids for the profile in use, as id => true.
	 *
	 * Kids: rated titles without an Age at or below the profile's maturity,
	 * and child posts whose parent is not such a title (including episodes
	 * with no parent). Other restricted profiles: rated titles with an Age
	 * above the maturity, and the children of those.
	 */
	private static function blocked_map() {

		if ( null !== self::$blocked ) {
			return self::$blocked;
		}

		self::$blocked = array();

		$restriction = self::restriction();

		if ( ! $restriction ) {
			return self::$blocked;
		}

		global $wpdb;

		list( $allowed_tt, $blocked_tt ) = self::split_terms( $restriction['max'] );

		/**
		 * Filters whether a Kids profile hides titles that have no Age rating.
		 * When false, Kids profiles hide only titles rated above their maturity.
		 *
		 * @param bool $hide Default true.
		 */
		$strict = $restriction['kids'] && (bool) apply_filters( 'jws_profiles_kids_hide_untagged', true );

		$types   = self::RATED_TYPES;
		$type_in = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$hidden  = array();

		if ( $strict ) {

			$sql = "SELECT p.ID FROM {$wpdb->posts} p WHERE p.post_type IN ({$type_in})";
			$arg = $types;

			if ( $allowed_tt ) {
				$tt_in = implode( ',', array_fill( 0, count( $allowed_tt ), '%d' ) );
				$sql  .= " AND NOT EXISTS ( SELECT 1 FROM {$wpdb->term_relationships} tr WHERE tr.object_id = p.ID AND tr.term_taxonomy_id IN ({$tt_in}) )";
				$arg   = array_merge( $arg, $allowed_tt );
			}

			$hidden = $wpdb->get_col( $wpdb->prepare( $sql, $arg ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		} elseif ( $blocked_tt ) {

			$tt_in  = implode( ',', array_fill( 0, count( $blocked_tt ), '%d' ) );
			$hidden = $wpdb->get_col( $wpdb->prepare(
				"SELECT DISTINCT tr.object_id FROM {$wpdb->term_relationships} tr
				 INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
				 WHERE tr.term_taxonomy_id IN ({$tt_in}) AND p.post_type IN ({$type_in})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( $blocked_tt, $types )
			) );
		}

		$hidden = array_map( 'intval', $hidden );

		foreach ( self::CHILD_TYPES as $child_type => $meta_key ) {
			$hidden = array_merge( $hidden, self::blocked_children( $child_type, $meta_key, $hidden, $strict ) );
		}

		self::$blocked = array_fill_keys( $hidden, true );

		return self::$blocked;
	}

	/**
	 * Kids: children whose parent is missing or hidden. Others: children of
	 * a hidden parent.
	 *
	 * @param int[] $hidden_parents Hidden rated post ids.
	 * @return int[]
	 */
	private static function blocked_children( $child_type, $meta_key, array $hidden_parents, $kids ) {

		global $wpdb;

		if ( $kids ) {
			$sql = "SELECT p.ID FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
				WHERE p.post_type = %s AND ( pm.meta_value IS NULL OR pm.meta_value = '' OR pm.meta_value = '0'";
			$arg = array( $meta_key, $child_type );

			if ( $hidden_parents ) {
				$sql .= ' OR CAST( pm.meta_value AS UNSIGNED ) IN (' . implode( ',', array_fill( 0, count( $hidden_parents ), '%d' ) ) . ')';
				$arg  = array_merge( $arg, $hidden_parents );
			}

			return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( $sql . ' )', $arg ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		if ( ! $hidden_parents ) {
			return array();
		}

		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
			 WHERE p.post_type = %s AND CAST( pm.meta_value AS UNSIGNED ) IN (" . implode( ',', array_fill( 0, count( $hidden_parents ), '%d' ) ) . ')', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			array_merge( array( $meta_key, $child_type ), $hidden_parents )
		) ) );
	}

	/** @return array array( allowed term_taxonomy_ids, blocked term_taxonomy_ids ) for a maturity. */
	private static function split_terms( $max ) {

		$terms = get_terms(
			array(
				'taxonomy'   => 'ages',
				'hide_empty' => false,
			)
		);

		$allowed = array();
		$blocked = array();

		if ( is_wp_error( $terms ) ) {
			return array( $allowed, $blocked );
		}

		foreach ( $terms as $term ) {
			if ( self::term_maturity( $term ) <= $max ) {
				$allowed[] = (int) $term->term_taxonomy_id;
			} else {
				$blocked[] = (int) $term->term_taxonomy_id;
			}
		}

		return array( $allowed, $blocked );
	}

	/**
	 * Takes Kids-hidden types out of the query's post types.
	 *
	 * @return bool False when nothing the query asks for is visible (the
	 *              query was set to match nothing).
	 */
	private static function drop_hidden_types( $query ) {

		$hidden = self::kids_hidden_types();
		$types  = $query->get( 'post_type' );

		if ( 'any' === $types ) {
			$types = array_values( get_post_types( array( 'exclude_from_search' => false ) ) );
		}
		if ( is_string( $types ) && '' !== $types ) {
			$types = array( $types );
		}

		if ( ! is_array( $types ) || ! $types ) {
			// No types named: WordPress works them out later, so drop them in SQL.
			$query->set( self::HIDE_TYPES_VAR, $hidden );
			return true;
		}

		$left = array_values( array_diff( $types, $hidden ) );

		if ( ! $left ) {
			self::match_nothing( $query );
			return false;
		}

		if ( count( $left ) !== count( $types ) ) {
			$query->set( 'post_type', $left );
		}

		return true;
	}

	/** Whether a query can return rated or child posts, so is worth filtering. */
	private static function may_return_rated( $query ) {

		$types = $query->get( 'post_type' );

		if ( '' === $types || 'any' === $types || null === $types || array() === $types ) {
			return true;
		}

		return (bool) array_intersect( (array) $types, array_merge( self::RATED_TYPES, array_keys( self::CHILD_TYPES ) ) );
	}

	private static function match_nothing( $query ) {
		$query->set( 'p', 0 );
		$query->set( 'post__in', array( 0 ) );
	}

	/** Taxonomies used only by Kids-hidden post types, whose archives a Kids profile cannot open. */
	private static function hidden_taxonomies() {

		$hidden = self::kids_hidden_types();
		$out    = array();

		foreach ( $hidden as $type ) {
			foreach ( get_object_taxonomies( $type ) as $taxonomy ) {
				$object = get_taxonomy( $taxonomy );
				if ( $object && ! array_diff( (array) $object->object_type, $hidden ) ) {
					$out[] = $taxonomy;
				}
			}
		}

		return array_values( array_unique( $out ) );
	}

	/** AJAX action => request field holding the post it plays or shows. */
	private static function ajax_post_fields() {

		/**
		 * Filters the AJAX actions that open one title, and the request field
		 * that carries its post id. Restricted profiles are refused hidden titles.
		 *
		 * @param array $fields action => field.
		 */
		return (array) apply_filters(
			'jws_profile_ajax_post_fields',
			array(
				'jws_ajax_episode_player'   => 'id',
				'jws_ajax_sources'          => 'id',
				'jws_load_season'           => 'id',
				'jws_videos_quickview'      => 'id',
				'jws_videos_live_quickview' => 'id',
				'jws_video_check'           => 'id',
				'jws_livekit_viewer_token'  => 'id',
				'jws_drama_purchase_panel'  => 'episode_id',
				'jws_drama_unlock'          => 'episode_id',
			)
		);
	}

	/** AJAX actions that change the account: its email, phone, password, details and devices. */
	private static function account_actions() {

		/**
		 * Filters the AJAX actions only a profile that manages the account may make.
		 *
		 * @param string[] $actions Action names, without the wp_ajax_ prefix.
		 */
		return (array) apply_filters(
			'jws_profile_account_actions',
			array(
				'edit_profile',
				'edit_personal',
				'jws_send_email_change_code',
				'jws_send_phone_change_code',
				'jws_remove_user_device',
				'jws_remove_all_other_devices',
			)
		);
	}

	/** AJAX actions a Kids profile cannot make at all. */
	private static function kids_blocked_actions() {

		/**
		 * Filters the AJAX actions refused on a Kids profile.
		 *
		 * @param string[] $actions Action names, without the wp_ajax_ prefix.
		 */
		return (array) apply_filters(
			'jws_profile_kids_blocked_actions',
			array(
				// Buying, renting and spending coins.
				'jws_payment_start',
				'jws_payment_settle',
				'jws_drama_checkout',
				'jws_drama_add_package',
				'jws_drama_purchase_panel',
				'jws_drama_unlock',
				'jws_ajax_add_to_cart',
				'jws_add_ticket_to_cart',
				// Reviews, questions and live chat.
				'jws_submit_review_comment',
				'jws_add_question',
				'jws_livekit_chat',
				// Going live.
				'jws_livekit_host_token',
				'jws_livekit_ingress',
				'jws_livekit_end',
				// Account devices.
				'jws_remove_user_device',
				'jws_remove_all_other_devices',
			)
		);
	}

	private static function deny( $message ) {
		wp_send_json_error(
			array(
				'code'    => 'jws_profile_restricted',
				'message' => $message,
			),
			403
		);
	}

	/** Page builder previews show everything, whichever profile the editor last used. */
	private static function is_builder_preview() {
		return isset( $_GET['elementor-preview'] ) || is_customize_preview(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
}

add_action( 'pre_get_posts', array( 'Jws_Profile_Filter', 'filter_query' ), 20 );
add_filter( 'posts_where', array( 'Jws_Profile_Filter', 'hide_types_where' ), 10, 2 );
add_action( 'template_redirect', array( 'Jws_Profile_Filter', 'guard_request' ), 5 );
add_action( 'admin_init', array( 'Jws_Profile_Filter', 'guard_ajax' ), 1 );
add_action( 'jws_profiles_switched', array( 'Jws_Profile_Filter', 'reset' ) );
add_filter( 'jws_profile_visible_post_types', array( 'Jws_Profile_Filter', 'visible_post_types' ) );
add_filter( 'jws_profile_hidden_post_ids', array( 'Jws_Profile_Filter', 'hidden_post_ids' ) );
add_filter( 'jws_profile_visible_ids', array( 'Jws_Profile_Filter', 'visible_ids' ) );
