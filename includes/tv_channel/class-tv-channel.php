<?php

/**
 * Live TV module: linear channels with a stream and a program guide.
 *
 * Self-contained like the drama module — post type, fields, the schedule
 * engine and the admin columns all live under includes/tv_channel/ and are
 * hooked up from boot(). The theme draws the pages (archive-tv_channel.php,
 * single-tv_channel.php, the Live TV Elementor widget) and reads everything it
 * needs through the static helpers here and in Jws_Tv_Channel_Schedule, so the
 * REST API can later answer from the same place.
 *
 * Playback goes through the site's own player (`streamvid/movies/player` with
 * the channel's stream as `url`), which already understands HLS, YouTube and
 * iframe embeds and applies Paid Memberships Pro's "Require Membership" box.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/tv_channel
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Tv_Channel {

	const POST_TYPE = 'tv_channel';
	const TAX       = 'tv_channel_cat';

	/** Bumped when the rewrite slugs change, so rules are flushed once. */
	const REWRITE_VERSION = '1';

	public static function boot() {

		$dir = plugin_dir_path( __FILE__ );

		require_once $dir . 'class-tv-channel-post-types.php';
		require_once $dir . 'class-tv-channel-schedule.php';
		require_once $dir . 'class-tv-channel-fields.php';
		require_once $dir . 'class-tv-channel-admin.php';
		require_once $dir . 'class-tv-channel-demo.php';

		add_action( 'init', array( 'Jws_Tv_Channel_Post_Types', 'register' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrites' ), 99 );

		/* After Jws_Metabox has loaded its own field files (init, 20). */
		add_action( 'init', array( 'Jws_Tv_Channel_Fields', 'register' ), 21 );

		/* Channels are restricted the same way movies are: PMPro's box. */
		add_action( 'add_meta_boxes', array( __CLASS__, 'membership_box' ) );

		add_action( 'save_post_' . self::POST_TYPE, array( 'Jws_Tv_Channel_Schedule', 'flush' ) );

		if ( is_admin() ) {
			Jws_Tv_Channel_Admin::hook();
			Jws_Tv_Channel_Demo::hook();
		}
	}

	public static function maybe_flush_rewrites() {

		if ( get_option( 'jws_tv_channel_rewrite' ) === self::REWRITE_VERSION ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( 'jws_tv_channel_rewrite', self::REWRITE_VERSION );
	}

	public static function membership_box() {

		if ( function_exists( 'pmpro_page_meta' ) ) {
			add_meta_box( 'pmpro_page_meta', 'Require Membership', 'pmpro_page_meta', self::POST_TYPE, 'side', 'high' );
		}
	}

	/* ---------------------------------------------------------------------- */
	/* Reading channels                                                        */
	/* ---------------------------------------------------------------------- */

	/**
	 * Channel ids in guide order: channel number, then title.
	 *
	 * @param array $args {
	 *     @type int[]    $include  Only these ids, kept in this order.
	 *     @type string[] $category Term slugs of tv_channel_cat.
	 *     @type int      $limit    -1 for all.
	 *     @type int[]    $exclude
	 * }
	 * @return int[]
	 */
	public static function ids( $args = array() ) {

		$args = wp_parse_args(
			$args,
			array(
				'include'  => array(),
				'category' => array(),
				'limit'    => -1,
				'exclude'  => array(),
			)
		);

		$query = array(
			'post_type'              => self::POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => (int) $args['limit'],
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'post__not_in'           => array_map( 'absint', (array) $args['exclude'] ),
		);

		if ( $args['include'] ) {
			$query['post__in'] = array_map( 'absint', (array) $args['include'] );
			$query['orderby']  = 'post__in';
		} else {
			$query['orderby'] = 'title';
			$query['order']   = 'ASC';
			/* The limit applies after sorting by number, below. */
			$query['posts_per_page'] = -1;
		}

		if ( $args['category'] ) {
			$query['tax_query'] = array(
				array(
					'taxonomy' => self::TAX,
					'field'    => 'slug',
					'terms'    => (array) $args['category'],
				),
			);
		}

		$ids = array_map( 'intval', get_posts( $query ) );

		if ( $args['include'] ) {
			return $ids;
		}

		/*
		 * Channel number first; a channel without one lists after the numbered
		 * ones. Sorted here rather than by meta_value_num because the meta box
		 * stores an empty number as '' — which SQL would sort as 0, first.
		 */
		$numbers = array();
		foreach ( $ids as $id ) {
			$number          = get_post_meta( $id, 'tv_channel_number', true );
			$numbers[ $id ] = is_numeric( $number ) ? (float) $number : PHP_INT_MAX;
		}

		$order = array_flip( $ids );
		usort(
			$ids,
			function ( $a, $b ) use ( $numbers, $order ) {
				return $numbers[ $a ] <=> $numbers[ $b ] ?: $order[ $a ] <=> $order[ $b ];
			}
		);

		return (int) $args['limit'] > 0 ? array_slice( $ids, 0, (int) $args['limit'] ) : $ids;
	}

	/**
	 * Everything a template shows about one channel.
	 *
	 * @return array|null
	 */
	public static function get( $id ) {

		$post = get_post( $id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$logo_id  = (int) get_post_meta( $id, 'tv_channel_logo', true );
		$cover_id = (int) get_post_thumbnail_id( $id );
		$terms    = get_the_terms( $id, self::TAX );
		$terms    = $terms && ! is_wp_error( $terms ) ? array_values( $terms ) : array();

		return array(
			'id'       => (int) $id,
			'name'     => get_the_title( $id ),
			'url'      => get_permalink( $id ),
			'number'   => (string) get_post_meta( $id, 'tv_channel_number', true ),
			'logo'     => $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '',
			'cover'    => $cover_id ? wp_get_attachment_image_url( $cover_id, 'large' ) : '',
			'quality'  => (string) get_post_meta( $id, 'tv_channel_quality', true ),
			'language' => (string) get_post_meta( $id, 'tv_channel_language', true ),
			'stream'   => trim( (string) get_post_meta( $id, 'tv_stream_url', true ) ),
			'featured' => (bool) get_post_meta( $id, 'tv_channel_featured', true ),
			'terms'    => $terms,
			'category' => $terms ? $terms[0]->name : '',
			'premium'  => self::is_premium( $id ),
		);
	}

	/** True when the channel needs a membership level. */
	public static function is_premium( $id ) {

		if ( ! function_exists( 'pmpro_has_membership_access' ) ) {
			return false;
		}

		$levels = pmpro_has_membership_access( $id, null, true );

		return ! empty( $levels[1] );
	}

	/** The channel shown large at the top of the archive. */
	public static function featured_id( $ids ) {

		foreach ( $ids as $id ) {
			if ( get_post_meta( $id, 'tv_channel_featured', true ) ) {
				return (int) $id;
			}
		}

		foreach ( $ids as $id ) {
			if ( Jws_Tv_Channel_Schedule::now_next( $id )['now'] ) {
				return (int) $id;
			}
		}

		return $ids ? (int) $ids[0] : 0;
	}

	/**
	 * The channels either side of this one in guide order, wrapping around.
	 *
	 * @return int[] [ previous, next ]
	 */
	public static function neighbours( $id ) {

		$ids = self::ids();
		$at  = array_search( (int) $id, $ids, true );

		if ( false === $at || count( $ids ) < 2 ) {
			return array( 0, 0 );
		}

		$count = count( $ids );

		return array( $ids[ ( $at - 1 + $count ) % $count ], $ids[ ( $at + 1 ) % $count ] );
	}
}
