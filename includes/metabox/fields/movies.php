<?php

/**
 * Movie meta boxes — the Jws_Metabox version of the theme's ACF groups
 * `movies_metabox` and `movies_meta_right`
 * (themes/streamvid/inc/admin/acf_metabox/post_type/movies.php).
 *
 * Names and field keys are copied from that file so both write identical meta.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/metabox
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

add_action( 'init', 'jws_metabox_register_movies', 20 );

function jws_metabox_register_movies() {
	$k = 'field_mov_';

	$taxonomies = array(
		jws_mb_field_taxonomy( 'movie_category', $k . 'movie_category', __( 'Movie Category', 'jws_streamvid' ), 'movies_cat' ),
		jws_mb_field_taxonomy( 'movie_genres', $k . 'movie_genres', __( 'Genres', 'jws_streamvid' ), 'genres' ),
		jws_mb_field_taxonomy( 'movie_topics', $k . 'movie_topics', __( 'Topics', 'jws_streamvid' ), 'topics' ),
		jws_mb_field_taxonomy( 'movie_countries', $k . 'movie_countries', __( 'Countries', 'jws_streamvid' ), 'countries' ),
		jws_mb_field_taxonomy( 'movie_ages', $k . 'movie_ages', __( 'Ages', 'jws_streamvid' ), 'ages', false ),
		jws_mb_field_taxonomy( 'movie_tags', $k . 'movie_tags', __( 'Movie Tags', 'jws_streamvid' ), 'movies_tag' ),
		jws_mb_field_taxonomy( 'movie_playlist', $k . 'movie_playlist', __( 'Movie Playlist', 'jws_streamvid' ), 'movies_playlist' ),
	);

	$text = function ( $name, $label, $width = 33, $placeholder = '' ) use ( $k ) {
		return array( 'type' => 'text', 'name' => $name, 'key' => $k . $name, 'label' => $label, 'width' => $width, 'placeholder' => $placeholder );
	};

	Jws_Metabox::register(
		'movies_main',
		array(
			'title'      => __( 'Movie Settings', 'jws_streamvid' ),
			'post_types' => array( 'movies' ),
			'acf_groups' => array( 'movies_metabox' ),
			'hide_boxes' => jws_mb_taxonomy_boxes( array( 'movies_cat', 'genres', 'topics', 'countries', 'ages', 'movies_tag', 'movies_playlist', JWS_CONTENT_BADGE_TAX ) ),
			'fields'     => array_merge(
				array(
					jws_mb_tab( __( 'Video', 'jws_streamvid' ), 'dashicons-video-alt3' ),
					jws_mb_field_video_type( $k ),
					array(
						'type'  => 'toggle',
						'name'  => 'is_affiliate',
						'key'   => $k . 'is_affiliate',
						'label' => __( 'Affiliate link', 'jws_streamvid' ),
						'desc'  => __( 'Show a play button that opens the video URL in a new tab instead of playing it.', 'jws_streamvid' ),
						'width' => 67,
					),
				),
				jws_mb_fields_video_files( $k ),
				array(
					jws_mb_field_subtitles( $k ),

					jws_mb_tab( __( 'Info', 'jws_streamvid' ), 'dashicons-info-outline' ),
					$text( 'videos_years', __( 'Year', 'jws_streamvid' ), 25, '2024' ),
					$text( 'videos_time', __( 'Duration', 'jws_streamvid' ), 25, '2h 10m' ),
					$text( 'videos_badge', __( 'Badge', 'jws_streamvid' ), 25, 'HD' ),
					array( 'type' => 'number', 'name' => 'videos_age', 'key' => $k . 'videos_age', 'label' => __( 'Age', 'jws_streamvid' ), 'width' => 25, 'attrs' => array( 'min' => '0', 'step' => '1' ), 'placeholder' => '13' ),
					$text( 'videos_language', __( 'Language', 'jws_streamvid' ), 25, 'English' ),
					$text( 'videos_tmdb', __( 'TMDB ID', 'jws_streamvid' ), 25 ),
					$text( 'videos_vote', __( 'TMDB Rating', 'jws_streamvid' ), 25 ),
					$text( 'videos_imdb_id', __( 'IMDb ID', 'jws_streamvid' ), 25, 'tt0000000' ),
					$text( 'videos_imdb', __( 'IMDb Rating', 'jws_streamvid' ), 25 ),
					jws_mb_field_badges( $k ),

					jws_mb_tab( __( 'Trailer', 'jws_streamvid' ), 'dashicons-format-video' ),
				),
				jws_mb_fields_trailer( $k ),
				array( jws_mb_tab( __( 'Cast & Crew', 'jws_streamvid' ), 'dashicons-groups' ) ),
				jws_mb_fields_cast_crew( $k ),
				array(
					jws_mb_tab( __( 'Sources', 'jws_streamvid' ), 'dashicons-networking' ),
					jws_mb_field_sources( $k ),

					jws_mb_tab( __( 'Download', 'jws_streamvid' ), 'dashicons-download' ),
				),
				jws_mb_fields_download( $k ),
				array( jws_mb_tab( __( 'Monetization', 'jws_streamvid' ), 'dashicons-money-alt' ) ),
				jws_mb_fields_ppv( $k ),
				array(
					jws_mb_field_ads( $k ),

					jws_mb_tab( __( 'Related & Media', 'jws_streamvid' ), 'dashicons-images-alt2' ),
					array(
						'type'      => 'posts',
						'name'      => 'recommended',
						'key'       => $k . 'recommended',
						'label'     => __( 'Recommended', 'jws_streamvid' ),
						'desc'      => __( 'Movies and TV shows suggested with this movie (up to 20).', 'jws_streamvid' ),
						'post_type' => array( 'movies', 'tv_shows' ),
						'multiple'  => true,
						'max'       => 20,
					),
					jws_mb_field_featured_clips( $k ),
					array(
						'type'          => 'gallery',
						'name'          => 'videos_gallery',
						'key'           => $k . 'videos_gallery',
						'label'         => __( 'Gallery', 'jws_streamvid' ),
						'return_format' => 'id',
						'max'           => 50,
					),

					jws_mb_tab( __( 'Taxonomies', 'jws_streamvid' ), 'dashicons-tag' ),
				),
				$taxonomies,
				array(
					jws_mb_tab( __( 'Preview', 'jws_streamvid' ), 'dashicons-visibility' ),
					jws_mb_field_preview(),
				)
			),
		)
	);

	Jws_Metabox::register(
		'movies_side',
		array(
			'title'      => __( 'Movie Images', 'jws_streamvid' ),
			'post_types' => array( 'movies' ),
			'context'    => 'side',
			'priority'   => 'low',
			'acf_groups' => array( 'movies_meta_right' ),
			'fields'     => jws_mb_fields_side_images( $k ),
		)
	);
}
