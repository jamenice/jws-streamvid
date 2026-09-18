<?php

/**
 * TV show meta boxes — the Jws_Metabox version of the theme's ACF groups
 * `tv_shows_metabox` and `tv_shows_meta_right`
 * (themes/streamvid/inc/admin/acf_metabox/post_type/tv_shows.php).
 *
 * Names and field keys are copied from that file so both write identical meta.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/metabox
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

add_action( 'init', 'jws_metabox_register_tv_shows', 20 );

function jws_metabox_register_tv_shows() {
	$k = 'field_tvs_';

	$taxonomies = array(
		jws_mb_field_taxonomy( 'tvshow_category', $k . 'category', __( 'TV Show Category', 'jws_streamvid' ), 'tv_shows_cat' ),
		jws_mb_field_taxonomy( 'tvshow_genres', $k . 'genres', __( 'Genres', 'jws_streamvid' ), 'genres' ),
		jws_mb_field_taxonomy( 'tvshow_topics', $k . 'topics', __( 'Topics', 'jws_streamvid' ), 'topics' ),
		jws_mb_field_taxonomy( 'tvshow_countries', $k . 'countries', __( 'Countries', 'jws_streamvid' ), 'countries' ),
		jws_mb_field_taxonomy( 'tvshow_ages', $k . 'ages', __( 'Ages', 'jws_streamvid' ), 'ages', false ),
		jws_mb_field_taxonomy( 'tvshow_tags', $k . 'tags', __( 'TV Show Tags', 'jws_streamvid' ), 'tv_shows_tag' ),
	);

	Jws_Metabox::register(
		'tv_shows_main',
		array(
			'title'      => __( 'TV Show Settings', 'jws_streamvid' ),
			'post_types' => array( 'tv_shows' ),
			'acf_groups' => array( 'tv_shows_metabox' ),
			'hide_boxes' => jws_mb_taxonomy_boxes( array( 'genres', 'topics', 'countries', 'ages', 'tv_shows_cat', 'tv_shows_tag', JWS_CONTENT_BADGE_TAX ) ),
			'on_save'    => 'jws_metabox_tv_shows_sync_episodes',
			'fields'     => array_merge(
				array(
					jws_mb_tab( __( 'Seasons', 'jws_streamvid' ), 'dashicons-playlist-video' ),
					array(
						'type'       => 'repeater',
						'layout'     => 'seasons',
						'name'       => 'tv_shows_seasons',
						'key'        => $k . 'tv_shows_seasons',
						'sub_fields' => array(
							array( 'type' => 'image', 'name' => 'season_thumbnail', 'key' => $k . 'season_thumbnail', 'return_format' => 'id' ),
							array( 'type' => 'text', 'name' => 'season_name', 'key' => $k . 'season_name' ),
							array( 'type' => 'posts', 'name' => 'episodes', 'key' => $k . 'episodes', 'post_type' => array( 'episodes' ), 'multiple' => true ),
						),
					),

					jws_mb_tab( __( 'Info', 'jws_streamvid' ), 'dashicons-info-outline' ),
					array( 'type' => 'text', 'name' => 'videos_years', 'key' => $k . 'videos_years', 'label' => __( 'Years', 'jws_streamvid' ), 'width' => 33, 'placeholder' => '2024' ),
					array( 'type' => 'text', 'name' => 'videos_badge', 'key' => $k . 'videos_badge', 'label' => __( 'Badge', 'jws_streamvid' ), 'width' => 33, 'placeholder' => 'HD' ),
					array( 'type' => 'text', 'name' => 'videos_tmdb', 'key' => $k . 'videos_tmdb', 'label' => __( 'TMDB ID', 'jws_streamvid' ), 'width' => 33 ),
					array( 'type' => 'text', 'name' => 'videos_vote', 'key' => $k . 'videos_vote', 'label' => __( 'TMDB Rating', 'jws_streamvid' ), 'width' => 33 ),
					array( 'type' => 'text', 'name' => 'videos_imdb_id', 'key' => $k . 'videos_imdb_id', 'label' => __( 'IMDb ID', 'jws_streamvid' ), 'width' => 33 ),
					array( 'type' => 'text', 'name' => 'videos_imdb', 'key' => $k . 'videos_imdb', 'label' => __( 'IMDb Rating', 'jws_streamvid' ), 'width' => 33 ),
					jws_mb_field_badges( $k ),
					jws_mb_field_featured_clips( $k ),

					jws_mb_tab( __( 'Trailer', 'jws_streamvid' ), 'dashicons-video-alt3' ),
				),
				jws_mb_fields_trailer( $k ),
				array( jws_mb_tab( __( 'Cast & Crew', 'jws_streamvid' ), 'dashicons-groups' ) ),
				jws_mb_fields_cast_crew( $k ),
				array( jws_mb_tab( __( 'Monetization', 'jws_streamvid' ), 'dashicons-money-alt' ) ),
				jws_mb_fields_ppv( $k ),
				array(
					jws_mb_field_ads( $k ),
					jws_mb_tab( __( 'Taxonomies', 'jws_streamvid' ), 'dashicons-tag' ),
				),
				$taxonomies
			),
		)
	);

	Jws_Metabox::register(
		'tv_shows_side',
		array(
			'title'      => __( 'TV Show Images', 'jws_streamvid' ),
			'post_types' => array( 'tv_shows' ),
			'context'    => 'side',
			'priority'   => 'low',
			'acf_groups' => array( 'tv_shows_meta_right' ),
			'fields'     => jws_mb_fields_side_images( $k ),
		)
	);
}

/**
 * Same job as the `acf/save_post` hook in function_change_default_wp.php
 * (which does not fire when ACF is not rendering the form): stamp each
 * episode with its show, season and position. Episodes dropped from the show
 * are unlinked so the API's meta-based fallback does not keep listing them.
 */
function jws_metabox_tv_shows_sync_episodes( $post_id ) {
	$seasons = Jws_Metabox::get_field( 'tv_shows_seasons', $post_id );
	$kept    = array();

	foreach ( (array) $seasons as $season_index => $season ) {
		foreach ( array_values( (array) $season['episodes'] ) as $ep_index => $episode_id ) {
			if ( 'episodes' !== get_post_type( $episode_id ) ) {
				continue;
			}
			update_post_meta( $episode_id, 'tv_show_id', (int) $post_id );
			update_post_meta( $episode_id, 'season_number', $season_index + 1 );
			update_post_meta( $episode_id, 'episode_number', $ep_index + 1 );
			$kept[] = (int) $episode_id;
		}
	}

	$linked = get_posts(
		array(
			'post_type'      => 'episodes',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => 'tv_show_id',
			'meta_value'     => (int) $post_id,
		)
	);
	foreach ( array_diff( $linked, $kept ) as $orphan ) {
		delete_post_meta( $orphan, 'tv_show_id' );
		delete_post_meta( $orphan, 'season_number' );
		delete_post_meta( $orphan, 'episode_number' );
	}
}
