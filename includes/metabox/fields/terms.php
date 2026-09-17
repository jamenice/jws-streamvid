<?php

/**
 * Term meta boxes — the Jws_Metabox version of the theme's taxonomy ACF
 * groups (age_settings, genres_settings, tv_shows_taxonomy, topic_settings
 * and the four *_playlist groups).
 *
 * Names and field keys are copied from the theme so both write identical
 * term meta. Saving a topic fires the same ACF hooks the theme's topic sync
 * listens on (see Jws_Metabox::save_term()).
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/metabox
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

add_action( 'init', 'jws_metabox_register_terms', 20 );

function jws_metabox_register_terms() {
	$look = function ( $k, $image_name ) {
		return array(
			array( 'type' => 'color', 'name' => 'color_background', 'key' => $k . 'color_background', 'label' => __( 'Background color', 'jws_streamvid' ), 'width' => 50 ),
			array( 'type' => 'color', 'name' => 'color_background2', 'key' => $k . 'color_background2', 'label' => __( 'Background color 2', 'jws_streamvid' ), 'desc' => __( 'Second gradient stop.', 'jws_streamvid' ), 'width' => 50 ),
			array( 'type' => 'image', 'name' => $image_name, 'key' => $k . $image_name, 'label' => __( 'Thumbnail', 'jws_streamvid' ), 'return_format' => 'array' ),
		);
	};

	Jws_Metabox::register_term(
		'ages_terms',
		array(
			'title'      => __( 'Age rating', 'jws_streamvid' ),
			'taxonomies' => array( 'ages' ),
			'acf_groups' => array( 'age_settings' ),
			'fields'     => array(
				array( 'type' => 'text', 'name' => 'ages_badge', 'key' => 'field_age_ages_badge', 'label' => __( 'Badge', 'jws_streamvid' ), 'placeholder' => '13+', 'desc' => __( 'Short label shown on posters.', 'jws_streamvid' ) ),
			),
		)
	);

	Jws_Metabox::register_term(
		'genres_terms',
		array(
			'title'      => __( 'Genre display', 'jws_streamvid' ),
			'taxonomies' => array( 'genres' ),
			'acf_groups' => array( 'genres_settings' ),
			'fields'     => $look( 'field_genres_', 'genres_image' ),
		)
	);

	Jws_Metabox::register_term(
		'tv_shows_cat_terms',
		array(
			'title'      => __( 'Category display', 'jws_streamvid' ),
			'taxonomies' => array( 'tv_shows_cat' ),
			'acf_groups' => array( 'tv_shows_taxonomy' ),
			'fields'     => $look( 'field_tvs_', 'tv_shows_cat_image' ),
		)
	);

	Jws_Metabox::register_term(
		'topics_terms',
		array(
			'title'      => __( 'Topic', 'jws_streamvid' ),
			'taxonomies' => array( 'topics' ),
			'acf_groups' => array( 'topic_settings' ),
			'fields'     => array_merge(
				array(
					array(
						'type'      => 'posts',
						'name'      => 'topic_featured_content',
						'key'       => 'field_topic_featured_content',
						'label'     => __( 'Movies & TV shows', 'jws_streamvid' ),
						'desc'      => __( 'Selected titles are assigned to this topic when you save; removed ones lose it.', 'jws_streamvid' ),
						'post_type' => array( 'movies', 'tv_shows' ),
						'multiple'  => true,
					),
				),
				$look( 'field_topic_', 'topics_image' )
			),
		)
	);

	$playlists = array(
		'movies_playlist'   => array( 'field_mov_', 'movies_playlist' ),
		'episodes_playlist' => array( 'field_ep_', 'episodes_playlist' ),
		'tv_shows_playlist' => array( 'field_tvs_', 'tv_shows_playlist' ),
		'videos_playlist'   => array( 'field_vd_', 'video_playlist' ),
	);
	foreach ( $playlists as $taxonomy => $def ) {
		list( $k, $group ) = $def;
		Jws_Metabox::register_term(
			$taxonomy . '_terms',
			array(
				'title'      => __( 'Playlist', 'jws_streamvid' ),
				'taxonomies' => array( $taxonomy ),
				'acf_groups' => array( $group ),
				'fields'     => array(
					array(
						'type'    => 'select',
						'name'    => 'status',
						'key'     => $k . 'status',
						'label'   => __( 'Visibility', 'jws_streamvid' ),
						'choices' => array( 'public' => __( 'Public', 'jws_streamvid' ), 'private' => __( 'Private', 'jws_streamvid' ) ),
						'default' => 'public',
						'width'   => 40,
					),
					array( 'type' => 'user', 'name' => 'user', 'key' => $k . 'user', 'label' => __( 'Owner', 'jws_streamvid' ), 'return_format' => 'array', 'width' => 60 ),
					array( 'type' => 'image', 'name' => 'playlist_image', 'key' => $k . 'playlist_image', 'label' => __( 'Cover image', 'jws_streamvid' ), 'return_format' => 'array' ),
				),
			)
		);
	}
}
