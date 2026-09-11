<?php

/**
 * Post types and taxonomies for short drama.
 *
 * A drama is a series of very short vertical episodes. It gets its own pair of
 * post types rather than riding on tv_shows/episodes so that the existing movie
 * and series archives, filters and queries stay exactly as they were.
 *
 * Episodes link to their drama through a plain `drama_id` meta value, not
 * through a repeater the way tv_shows does — a drama runs to 80+ episodes and
 * the repeater pattern needs a LIKE scan of every series to answer "which show
 * owns this episode".
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/drama
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Drama_Post_Types {

	const DRAMA   = 'drama';
	const EPISODE = 'drama_ep';

	const TAX_TAG = 'drama_tag';

	public function register() {

		$this->register_drama();
		$this->register_episode();
		$this->register_taxonomies();
	}

	/**
	 * Vocabularies the rest of the site already curates. Sharing them means a
	 * drama tagged "Romance" surfaces next to films tagged the same, instead of
	 * forcing a parallel term tree.
	 *
	 * Hooked later than register(): genres, countries and ages are registered by
	 * Jws_Streamvid_Post on `init` at the same priority, and this module's hooks
	 * are added first, so at priority 10 none of them exist yet.
	 */
	public function attach_shared_taxonomies() {

		foreach ( array( 'genres', 'countries', 'ages' ) as $shared ) {
			if ( taxonomy_exists( $shared ) ) {
				register_taxonomy_for_object_type( $shared, self::DRAMA );
			}
		}
	}

	/**
	 * Sits Drama Short directly above Movies in the admin sidebar.
	 *
	 * Movies, TV Shows and Videos register without a menu_position, so they
	 * fall in after Comments wherever registration order puts them — no fixed
	 * number can land next to them reliably, hence moving it by slug instead.
	 */
	public function menu_order( $order ) {

		$drama  = 'edit.php?post_type=' . self::DRAMA;
		$movies = 'edit.php?post_type=movies';

		$from = array_search( $drama, $order, true );

		if ( false === $from || ! in_array( $movies, $order, true ) ) {
			return $order;
		}

		array_splice( $order, $from, 1 );
		array_splice( $order, array_search( $movies, $order, true ), 0, array( $drama ) );

		return $order;
	}

	private function register_drama() {

		$slug = function_exists( 'jws_streamvid_options' ) ? jws_streamvid_options( 'drama_slug' ) : '';

		register_post_type(
			self::DRAMA,
			array(
				'label'               => esc_html__( 'Drama', 'jws_streamvid' ),
				'labels'              => array(
					'name'          => esc_html__( 'Drama Short', 'jws_streamvid' ),
					'singular_name' => esc_html__( 'Drama', 'jws_streamvid' ),
					'add_new_item'  => esc_html__( 'Add New Drama', 'jws_streamvid' ),
					'edit_item'     => esc_html__( 'Edit Drama', 'jws_streamvid' ),
					'all_items'     => esc_html__( 'All Drama', 'jws_streamvid' ),
					'search_items'  => esc_html__( 'Search Drama', 'jws_streamvid' ),
				),
				'public'              => true,
				'publicly_queryable'  => true,
				'show_ui'             => true,
				'show_in_rest'        => false,
				'has_archive'         => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => true,
				'exclude_from_search' => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'hierarchical'        => false,
				'rewrite'             => array(
					'slug'       => ! empty( $slug ) ? $slug : 'drama',
					'with_front' => true,
				),
				'query_var'           => true,
				'menu_position'       => 6,
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'comments', 'author' ),
				'menu_icon'           => 'dashicons-smartphone',
			)
		);
	}

	private function register_episode() {

		$slug = function_exists( 'jws_streamvid_options' ) ? jws_streamvid_options( 'drama_ep_slug' ) : '';

		register_post_type(
			self::EPISODE,
			array(
				'label'               => esc_html__( 'Drama Episode', 'jws_streamvid' ),
				'labels'              => array(
					'name'          => esc_html__( 'Episodes', 'jws_streamvid' ),
					'singular_name' => esc_html__( 'Episode', 'jws_streamvid' ),
					'add_new_item'  => esc_html__( 'Add New Episode', 'jws_streamvid' ),
					'edit_item'     => esc_html__( 'Edit Episode', 'jws_streamvid' ),
					'all_items'     => esc_html__( 'Episodes', 'jws_streamvid' ),
					'search_items'  => esc_html__( 'Search Episodes', 'jws_streamvid' ),
				),
				'public'              => true,
				'publicly_queryable'  => true,
				'show_ui'             => true,
				'show_in_rest'        => false,
				'has_archive'         => false,
				/* Lives under the Drama menu — an episode is never browsed on
				   its own in the admin, always in the context of its series. */
				'show_in_menu'        => 'edit.php?post_type=' . self::DRAMA,
				'show_in_nav_menus'   => false,
				'exclude_from_search' => true,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'hierarchical'        => false,
				'rewrite'             => array(
					'slug'       => ! empty( $slug ) ? $slug : 'drama-ep',
					'with_front' => true,
				),
				'query_var'           => true,
				'supports'            => array( 'title', 'thumbnail', 'editor', 'page-attributes' ),
			)
		);
	}

	private function register_taxonomies() {

		/*
		 * No `drama_cat` on purpose: drama is categorised with the site-wide
		 * `genres` vocabulary attached in attach_shared_taxonomies(), so a
		 * parallel term tree would only split the same editorial idea in two.
		 */
		register_taxonomy(
			self::TAX_TAG,
			array( self::DRAMA ),
			array(
				'labels'            => array(
					'name'          => esc_html__( 'Drama Tags', 'jws_streamvid' ),
					'singular_name' => esc_html__( 'Drama Tag', 'jws_streamvid' ),
					'all_items'     => esc_html__( 'All Tags', 'jws_streamvid' ),
					'edit_item'     => esc_html__( 'Edit Tag', 'jws_streamvid' ),
					'add_new_item'  => esc_html__( 'Add New Tag', 'jws_streamvid' ),
				),
				'public'            => true,
				'hierarchical'      => false,
				'show_ui'           => true,
				'show_admin_column' => false,
				'show_in_rest'      => false,
				'rewrite'           => array( 'slug' => 'drama-tag' ),
			)
		);
	}

	/* ---------------------------------------------------------------------- */
	/* Queries                                                                 */
	/* ---------------------------------------------------------------------- */

	/**
	 * Episodes of a drama in running order.
	 *
	 * @param int    $drama_id
	 * @param string $fields 'ids' or 'all'
	 * @return int[]|WP_Post[]
	 */
	public static function episodes_of( $drama_id, $fields = 'ids' ) {

		if ( ! $drama_id ) {
			return array();
		}

		$query = new WP_Query(
			array(
				'post_type'              => self::EPISODE,
				'post_status'            => array( 'publish', 'draft', 'pending', 'future' ),
				'posts_per_page'         => -1,
				'orderby'                => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
				'fields'                 => 'ids' === $fields ? 'ids' : '',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'     => 'drama_id',
						'value'   => (int) $drama_id,
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		return $query->posts;
	}

	public static function episode_count( $drama_id, $published_only = true ) {

		global $wpdb;

		$status = $published_only ? "AND p.post_status = 'publish'" : "AND p.post_status != 'trash'";

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(1)
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'drama_id'
				 WHERE p.post_type = %s {$status} AND m.meta_value = %d",
				self::EPISODE,
				(int) $drama_id
			)
		);
	}

	/** Next episode in running order, or 0 at the end of the series. */
	public static function next_episode( $episode_id ) {

		$drama_id = (int) get_post_meta( $episode_id, 'drama_id', true );

		if ( ! $drama_id ) {
			return 0;
		}

		$ids   = self::episodes_of( $drama_id );
		$index = array_search( (int) $episode_id, array_map( 'intval', $ids ), true );

		if ( false === $index || ! isset( $ids[ $index + 1 ] ) ) {
			return 0;
		}

		return (int) $ids[ $index + 1 ];
	}

	/* ---------------------------------------------------------------------- */
	/* Keeping order and numbering in step                                     */
	/* ---------------------------------------------------------------------- */

	/**
	 * menu_order is what every ordered query sorts on, and drama_ep_number is
	 * what editors type. Mirroring one into the other on save keeps sorting
	 * correct without a second meta lookup per row.
	 */
	public function sync_episode_order( $post_id, $post, $update ) {

		if ( self::EPISODE !== $post->post_type || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$number = (int) get_post_meta( $post_id, 'drama_ep_number', true );

		if ( $number > 0 && (int) $post->menu_order !== $number ) {
			// Direct write: wp_update_post() here would re-enter save_post.
			global $wpdb;
			$wpdb->update( $wpdb->posts, array( 'menu_order' => $number ), array( 'ID' => $post_id ), array( '%d' ), array( '%d' ) );
			clean_post_cache( $post_id );
		}
	}
}
