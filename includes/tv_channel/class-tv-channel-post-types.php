<?php

/**
 * The tv_channel post type and its category taxonomy.
 *
 * Channels get their own post type rather than riding on videos: a channel is
 * a stream plus a schedule, not a file, and keeping it apart leaves the
 * existing video archives, filters and history untouched.
 *
 * URLs: /live-tv/ lists the channels, /live-tv/{channel}/ plays one and
 * /live-tv-category/{term}/ is the list narrowed to a category. The first two
 * follow the `tv_channel_slug` plugin option when it is set.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/tv_channel
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Tv_Channel_Post_Types {

	public static function slug() {

		$slug = function_exists( 'jws_streamvid_options' ) ? jws_streamvid_options( 'tv_channel_slug' ) : '';

		return ! empty( $slug ) ? sanitize_title( $slug ) : 'live-tv';
	}

	public static function register() {

		register_post_type(
			Jws_Tv_Channel::POST_TYPE,
			array(
				'label'               => esc_html__( 'Live TV', 'jws_streamvid' ),
				'labels'              => array(
					'name'               => esc_html__( 'Live TV', 'jws_streamvid' ),
					'singular_name'      => esc_html__( 'Channel', 'jws_streamvid' ),
					'menu_name'          => esc_html__( 'Live TV', 'jws_streamvid' ),
					'all_items'          => esc_html__( 'All Channels', 'jws_streamvid' ),
					'add_new'            => esc_html__( 'Add Channel', 'jws_streamvid' ),
					'add_new_item'       => esc_html__( 'Add New Channel', 'jws_streamvid' ),
					'edit_item'          => esc_html__( 'Edit Channel', 'jws_streamvid' ),
					'new_item'           => esc_html__( 'New Channel', 'jws_streamvid' ),
					'view_item'          => esc_html__( 'View Channel', 'jws_streamvid' ),
					'search_items'       => esc_html__( 'Search Channels', 'jws_streamvid' ),
					'not_found'          => esc_html__( 'No channels found', 'jws_streamvid' ),
					'not_found_in_trash' => esc_html__( 'No channels found in Trash', 'jws_streamvid' ),
					'featured_image'     => esc_html__( 'Channel cover', 'jws_streamvid' ),
					'set_featured_image' => esc_html__( 'Set channel cover', 'jws_streamvid' ),
				),
				'description'         => esc_html__( 'Live channels with a stream and a program guide.', 'jws_streamvid' ),
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
					'slug'       => self::slug(),
					'with_front' => false,
				),
				'query_var'           => true,
				'menu_position'       => 7,
				'menu_icon'           => 'dashicons-desktop',
				'supports'            => array( 'title', 'editor', 'thumbnail', 'author' ),
			)
		);

		register_taxonomy(
			Jws_Tv_Channel::TAX,
			array( Jws_Tv_Channel::POST_TYPE ),
			array(
				'labels'            => array(
					'name'          => esc_html__( 'Channel Categories', 'jws_streamvid' ),
					'singular_name' => esc_html__( 'Channel Category', 'jws_streamvid' ),
					'menu_name'     => esc_html__( 'Categories', 'jws_streamvid' ),
					'all_items'     => esc_html__( 'All Categories', 'jws_streamvid' ),
					'edit_item'     => esc_html__( 'Edit Category', 'jws_streamvid' ),
					'add_new_item'  => esc_html__( 'Add New Category', 'jws_streamvid' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => false,
				'show_in_nav_menus' => true,
				'rewrite'           => array(
					'slug'       => self::slug() . '-category',
					'with_front' => false,
				),
			)
		);
	}
}
