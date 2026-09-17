<?php

/**
 * Episode meta boxes — the Jws_Metabox version of the theme's ACF group
 * `episodes_metabox` (themes/streamvid/inc/admin/acf_metabox/post_type/episodes.php),
 * plus a side box that shows which TV show seasons hold the episode and can
 * add it to (or drop it from) a season without leaving the page.
 *
 * Names and field keys are copied from the ACF file so both write identical meta.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/metabox
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

add_action( 'init', 'jws_metabox_register_episodes', 20 );
add_action( 'wp_ajax_jws_mb_show_seasons', 'jws_metabox_ajax_show_seasons' );

function jws_metabox_register_episodes() {
	$k = 'field_ep_';

	Jws_Metabox::register(
		'episodes_main',
		array(
			'title'      => __( 'Episode Settings', 'jws_streamvid' ),
			'post_types' => array( 'episodes' ),
			'acf_groups' => array( 'episodes_metabox' ),
			'fields'     => array_merge(
				array(
					jws_mb_tab( __( 'Video', 'jws_streamvid' ), 'dashicons-video-alt3' ),
					jws_mb_field_video_type( $k ),
					array( 'type' => 'text', 'name' => 'episodes_number', 'key' => $k . 'episodes_number', 'label' => __( 'Episode Number', 'jws_streamvid' ), 'width' => 22, 'placeholder' => '1' ),
					array( 'type' => 'text', 'name' => 'videos_time', 'key' => $k . 'videos_time', 'label' => __( 'Duration', 'jws_streamvid' ), 'width' => 22, 'placeholder' => '45m' ),
					array( 'type' => 'text', 'name' => 'videos_tmdb', 'key' => $k . 'videos_tmdb', 'label' => __( 'TMDB ID', 'jws_streamvid' ), 'width' => 22 ),
				),
				jws_mb_fields_video_files( $k ),
				array(
					jws_mb_field_subtitles( $k ),

					jws_mb_tab( __( 'Sources', 'jws_streamvid' ), 'dashicons-networking' ),
					jws_mb_field_sources( $k ),

					jws_mb_tab( __( 'Download', 'jws_streamvid' ), 'dashicons-download' ),
				),
				jws_mb_fields_download( $k ),
				array(
					jws_mb_tab( __( 'Preview', 'jws_streamvid' ), 'dashicons-visibility' ),
					jws_mb_field_preview(),
				)
			),
		)
	);

	Jws_Metabox::register(
		'episodes_show',
		array(
			'title'      => __( 'TV Show', 'jws_streamvid' ),
			'post_types' => array( 'episodes' ),
			'context'    => 'side',
			'priority'   => 'high',
			'on_save'    => 'jws_metabox_episode_assign_save',
			'fields'     => array(
				array( 'type' => 'html', 'name' => 'placements', 'render' => 'jws_metabox_render_episode_placements' ),
				array(
					'type'        => 'posts',
					'name'        => 'assign_show',
					'label'       => __( 'Add to a season', 'jws_streamvid' ),
					'post_type'   => array( 'tv_shows' ),
					'placeholder' => __( 'Search TV show…', 'jws_streamvid' ),
					'virtual'     => true,
				),
				array( 'type' => 'html', 'name' => 'assign_season', 'render' => 'jws_metabox_render_assign_season' ),
			),
		)
	);
}

/* -------------------------------------------------------------------------- */
/* Season placements                                                          */
/* -------------------------------------------------------------------------- */

/**
 * Every TV show season holding an episode.
 *
 * @return array[] { show: int, season: int (0-based), position: int (1-based) }
 */
function jws_metabox_episode_placements( $episode_id ) {
	global $wpdb;
	$episode_id = (int) $episode_id;
	if ( ! $episode_id ) {
		return array();
	}
	// Relationship arrays are stored as strings by ACF / the meta box, as ints by some importers.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT pm.post_id, pm.meta_key, pm.meta_value FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'tv_shows' AND p.post_status NOT IN ('trash','auto-draft')
			 WHERE pm.meta_key LIKE %s AND ( pm.meta_value LIKE %s OR pm.meta_value LIKE %s )
			 ORDER BY pm.post_id, pm.meta_key",
			$wpdb->esc_like( 'tv_shows_seasons_' ) . '%' . $wpdb->esc_like( '_episodes' ),
			'%' . $wpdb->esc_like( '"' . $episode_id . '"' ) . '%',
			'%' . $wpdb->esc_like( 'i:' . $episode_id . ';' ) . '%'
		)
	);

	$out = array();
	foreach ( $rows as $row ) {
		if ( ! preg_match( '/^tv_shows_seasons_(\d+)_episodes$/', $row->meta_key, $m ) ) {
			continue;
		}
		$season = (int) $m[1];
		if ( $season >= (int) get_post_meta( $row->post_id, 'tv_shows_seasons', true ) ) {
			continue; // Left over from a deleted row.
		}
		$ids = array_map( 'intval', (array) maybe_unserialize( $row->meta_value ) );
		$pos = array_search( $episode_id, $ids, true );
		if ( false !== $pos ) {
			$out[] = array(
				'show'     => (int) $row->post_id,
				'season'   => $season,
				'position' => $pos + 1,
			);
		}
	}
	return $out;
}

function jws_metabox_season_label( $show_id, $season ) {
	$name = get_post_meta( $show_id, 'tv_shows_seasons_' . $season . '_season_name', true );
	/* translators: %d: season number */
	return '' !== (string) $name ? $name : sprintf( __( 'Season %d', 'jws_streamvid' ), $season + 1 );
}

function jws_metabox_render_episode_placements( $post, $field, $input ) {
	$placements = jws_metabox_episode_placements( $post->ID );

	echo '<div class="jws-mb__placements">';
	if ( ! $placements ) {
		echo '<p class="jws-mb__placements-empty">' . esc_html__( 'This episode is not in any TV show yet.', 'jws_streamvid' ) . '</p>';
	}
	foreach ( $placements as $pl ) {
		$show  = get_post( $pl['show'] );
		$thumb = get_the_post_thumbnail_url( $show, 'thumbnail' );
		printf(
			'<div class="jws-mb__placement">%s<div class="jws-mb__placement-info"><a href="%s" target="_blank">%s</a><small>%s · %s</small></div>
			<label class="jws-mb__placement-remove" title="%s"><input type="checkbox" name="%s" value="%s"><span class="dashicons dashicons-no-alt"></span></label></div>',
			$thumb ? '<img src="' . esc_url( $thumb ) . '" alt="">' : '<span class="jws-mb__placement-noimg dashicons dashicons-format-video"></span>',
			esc_url( get_edit_post_link( $show->ID ) ),
			esc_html( get_the_title( $show ) ),
			esc_html( jws_metabox_season_label( $pl['show'], $pl['season'] ) ),
			/* translators: %d: position in the season */
			esc_html( sprintf( __( 'Episode %d', 'jws_streamvid' ), $pl['position'] ) ),
			esc_attr__( 'Remove from this season (on update)', 'jws_streamvid' ),
			esc_attr( Jws_Metabox::INPUT . '[episodes_show][remove][]' ),
			esc_attr( $pl['show'] . ':' . $pl['season'] )
		);
	}
	echo '</div>';
}

function jws_metabox_render_assign_season( $post, $field, $input ) {
	$base = Jws_Metabox::INPUT . '[episodes_show]';
	?>
	<div class="jws-mb__assign" hidden>
		<select name="<?php echo esc_attr( $base . '[assign_season]' ); ?>" class="jws-mb__input jws-mb__assign-season">
			<option value=""><?php esc_html_e( 'Choose season…', 'jws_streamvid' ); ?></option>
		</select>
		<input type="text" class="jws-mb__input jws-mb__assign-name" name="<?php echo esc_attr( $base . '[assign_season_name]' ); ?>" placeholder="<?php esc_attr_e( 'New season name', 'jws_streamvid' ); ?>" hidden>
		<p class="jws-mb__desc"><?php esc_html_e( 'The episode is added at the end of the season when you update.', 'jws_streamvid' ); ?></p>
	</div>
	<?php
}

/** Seasons of a show for the side box select. */
function jws_metabox_ajax_show_seasons() {
	check_ajax_referer( Jws_Metabox::AJAX_NONCE, 'nonce' );
	$show = isset( $_POST['show'] ) ? absint( $_POST['show'] ) : 0;
	if ( ! $show || 'tv_shows' !== get_post_type( $show ) || ! current_user_can( 'edit_post', $show ) ) {
		wp_send_json_error( null, 403 );
	}
	$episode = isset( $_POST['episode'] ) ? absint( $_POST['episode'] ) : 0;
	$seasons = array();
	foreach ( (array) Jws_Metabox::get_field( 'tv_shows_seasons', $show ) as $i => $row ) {
		$ids       = array_map( 'intval', (array) $row['episodes'] );
		$seasons[] = array(
			'index'    => $i,
			'label'    => jws_metabox_season_label( $show, $i ),
			'count'    => count( array_filter( $ids ) ),
			'contains' => $episode && in_array( $episode, $ids, true ),
		);
	}
	wp_send_json_success(
		array(
			'seasons' => $seasons,
			/* translators: %d: season number */
			'newName' => sprintf( __( 'Season %d', 'jws_streamvid' ), count( $seasons ) + 1 ),
		)
	);
}

/**
 * Apply the side box: drop the episode from ticked seasons, add it to the
 * chosen one, then re-stamp tv_show_id / season_number / episode_number.
 */
function jws_metabox_episode_assign_save( $episode_id, $box, $data ) {
	if ( 'episodes' !== get_post_type( $episode_id ) ) {
		return;
	}
	$changed = array();

	$field = null;
	foreach ( Jws_Metabox::fields_for( 'tv_shows' ) as $f ) {
		if ( 'tv_shows_seasons' === $f['name'] ) {
			$field = $f;
		}
	}
	if ( ! $field ) {
		return;
	}

	$edit = function ( $show_id, $callback ) use ( $field, &$changed ) {
		if ( 'tv_shows' !== get_post_type( $show_id ) || ! current_user_can( 'edit_post', $show_id ) ) {
			return;
		}
		$rows = (array) Jws_Metabox::get_value( $show_id, $field );
		$rows = $callback( $rows );
		if ( null === $rows ) {
			return;
		}
		Jws_Metabox::save_field( $show_id, $field, $rows );
		$changed[ $show_id ] = true;
	};

	foreach ( isset( $data['remove'] ) ? (array) $data['remove'] : array() as $pair ) {
		if ( ! preg_match( '/^(\d+):(\d+)$/', (string) $pair, $m ) ) {
			continue;
		}
		$season = (int) $m[2];
		$edit(
			(int) $m[1],
			function ( $rows ) use ( $season, $episode_id ) {
				if ( ! isset( $rows[ $season ] ) ) {
					return null;
				}
				$rows[ $season ]['episodes'] = array_values(
					array_filter(
						array_map( 'intval', (array) $rows[ $season ]['episodes'] ),
						function ( $id ) use ( $episode_id ) {
							return $id !== (int) $episode_id;
						}
					)
				);
				return $rows;
			}
		);
	}

	$show   = isset( $data['assign_show'] ) ? absint( is_array( $data['assign_show'] ) ? reset( $data['assign_show'] ) : $data['assign_show'] ) : 0;
	$target = isset( $data['assign_season'] ) ? (string) $data['assign_season'] : '';
	if ( $show && '' !== $target ) {
		$name = isset( $data['assign_season_name'] ) ? sanitize_text_field( $data['assign_season_name'] ) : '';
		$edit(
			$show,
			function ( $rows ) use ( $target, $name, $episode_id ) {
				if ( 'new' === $target ) {
					$rows[] = array(
						'season_thumbnail' => '',
						/* translators: %d: season number */
						'season_name'      => '' !== $name ? $name : sprintf( __( 'Season %d', 'jws_streamvid' ), count( $rows ) + 1 ),
						'episodes'         => array( $episode_id ),
					);
					return $rows;
				}
				$season = (int) $target;
				if ( ! isset( $rows[ $season ] ) ) {
					return null;
				}
				$ids = array_map( 'intval', (array) $rows[ $season ]['episodes'] );
				if ( in_array( (int) $episode_id, $ids, true ) ) {
					return null;
				}
				$ids[]                       = (int) $episode_id;
				$rows[ $season ]['episodes'] = $ids;
				return $rows;
			}
		);
	}

	if ( ! $changed ) {
		return;
	}
	foreach ( array_keys( $changed ) as $show_id ) {
		jws_metabox_tv_shows_sync_episodes( $show_id );
	}

	// A show sync may have unlinked this episode although another show still lists it.
	$placements = jws_metabox_episode_placements( $episode_id );
	if ( $placements ) {
		$first = $placements[0];
		update_post_meta( $episode_id, 'tv_show_id', $first['show'] );
		update_post_meta( $episode_id, 'season_number', $first['season'] + 1 );
		update_post_meta( $episode_id, 'episode_number', $first['position'] );
	} else {
		delete_post_meta( $episode_id, 'tv_show_id' );
		delete_post_meta( $episode_id, 'season_number' );
		delete_post_meta( $episode_id, 'episode_number' );
	}
}
