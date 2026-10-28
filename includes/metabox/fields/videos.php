<?php

/**
 * Video meta box — the Jws_Metabox version of the theme's ACF group
 * `videos_metabox` (themes/streamvid/inc/admin/acf_metabox/post_type/videos.php).
 *
 * Names and field keys are copied from that file so both write identical meta.
 * The Live Stream tab only reports: LiveKit streams are created with the Go
 * live button and run from the video page (includes/livestream/).
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/metabox
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

add_action( 'init', 'jws_metabox_register_videos', 20 );

function jws_metabox_register_videos() {
	$k = 'field_vd_';

	$ratios = function_exists( 'jws_videos_ratio' ) ? jws_videos_ratio() : array( '21x9' => '21x9', '16x9' => '16x9', '4x3' => '4x3' );

	Jws_Metabox::register(
		'videos_main',
		array(
			'title'      => __( 'Video Settings', 'jws_streamvid' ),
			'post_types' => array( 'videos' ),
			'acf_groups' => array( 'videos_metabox' ),
			'fields'     => array_merge(
				array(
					jws_mb_tab( __( 'Video', 'jws_streamvid' ), 'dashicons-video-alt3' ),
					jws_mb_field_video_type( $k, 34, false ),
					array(
						'type'    => 'select',
						'name'    => 'video_ratio',
						'key'     => $k . 'video_ratio',
						'label'   => __( 'Aspect Ratio', 'jws_streamvid' ),
						'choices' => array( '' => __( 'Theme default', 'jws_streamvid' ) ) + $ratios,
						'width'   => 33,
					),
					array( 'type' => 'text', 'name' => 'videos_time', 'key' => $k . 'videos_time', 'label' => __( 'Duration', 'jws_streamvid' ), 'width' => 33, 'placeholder' => '12:30' ),
				),
				jws_mb_fields_video_files( $k, false ),
				array(
					jws_mb_tab( __( 'Live Stream', 'jws_streamvid' ), 'dashicons-video-alt2' ),
					array(
						'type'   => 'html',
						'name'   => 'live_data',
						'render' => 'jws_metabox_render_live_data',
					),

					jws_mb_tab( __( 'Ads', 'jws_streamvid' ), 'dashicons-megaphone' ),
					jws_mb_field_ads( $k ),

					jws_mb_tab( __( 'Preview', 'jws_streamvid' ), 'dashicons-visibility' ),
					jws_mb_field_preview(),
				)
			),
		)
	);
}

/**
 * The Live Stream tab: whether this video is a LiveKit live stream. Members
 * create streams with the Go live button; nothing here starts one.
 */
function jws_metabox_render_live_data( $post ) {

	echo '<div class="admin-livestream jws-mb__live">';

	if ( class_exists( 'Jws_Livestream' ) && Jws_Livestream::is_stream_post( $post->ID ) ) {

		$message = Jws_Livestream::has_ended( $post->ID )
			? __( 'This live stream has ended.', 'jws_streamvid' )
			: __( 'This is a live stream. Its author goes live from the video page, in Live Studio.', 'jws_streamvid' );

		echo '<p class="jws-mb__desc">' . esc_html( $message ) . '</p>';

		if ( 'auto-draft' !== $post->post_status ) {
			printf( '<p><a href="%s" class="button" target="_blank" rel="noopener">%s</a></p>', esc_url( get_permalink( $post ) ), esc_html__( 'View video page', 'jws_streamvid' ) );
		}

	} else {

		echo '<div class="jws-mb__live-empty"><span class="dashicons dashicons-video-alt2"></span>';
		echo '<p>' . esc_html__( 'This video is not a live stream. Members create live streams on the front end with the Go live button.', 'jws_streamvid' ) . '</p>';
		echo '</div>';

	}

	echo '</div>';
}
