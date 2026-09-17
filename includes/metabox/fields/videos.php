<?php

/**
 * Video meta box — the Jws_Metabox version of the theme's ACF group
 * `videos_metabox` (themes/streamvid/inc/admin/acf_metabox/post_type/videos.php).
 *
 * Names and field keys are copied from that file so both write identical meta.
 * The live stream panel keeps the buttons admin/js/jws-streamvid-admin.js
 * already drives (.start-live-stream / .remove-live-stream).
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
 * Cloudflare live input details. Read-only: the data is written by the
 * start/remove live stream ajax actions, never by the form.
 */
function jws_metabox_render_live_data( $post ) {
	$live = get_post_meta( $post->ID, 'live_data', true );

	echo '<div class="admin-livestream jws-mb__live">';
	if ( 'auto-draft' === $post->post_status ) {
		echo '<p class="jws-mb__desc">' . esc_html__( 'Save the video once before starting a live stream.', 'jws_streamvid' ) . '</p></div>';
		return;
	}

	if ( empty( $live ) || ! is_array( $live ) ) {
		echo '<div class="jws-mb__live-empty"><span class="dashicons dashicons-video-alt2"></span>';
		echo '<p>' . esc_html__( 'No live input yet. Starting one creates a Cloudflare Stream live input for this video.', 'jws_streamvid' ) . '</p>';
		printf( '<a href="#" class="start-live-stream button button-primary button-large" data-id="%d">%s</a>', (int) $post->ID, esc_html__( 'Start Live Stream', 'jws_streamvid' ) );
		echo '</div></div>';
		return;
	}

	$rows = array(
		__( 'Uid', 'jws_streamvid' )        => isset( $live['uid'] ) ? $live['uid'] : '',
		__( 'Server Url', 'jws_streamvid' ) => isset( $live['rtmps']['url'] ) ? $live['rtmps']['url'] : '',
		__( 'Stream Key', 'jws_streamvid' ) => isset( $live['rtmps']['streamKey'] ) ? $live['rtmps']['streamKey'] : '',
	);
	echo '<div class="jws-mb__grid">';
	foreach ( $rows as $label => $value ) {
		if ( '' === (string) $value ) {
			continue;
		}
		echo '<div class="jws-mb__field field-item" style="--jws-mb-w:100%">';
		printf( '<label class="jws-mb__label">%s</label>', esc_html( $label ) );
		printf(
			'<div class="jws-mb__copy"><input type="text" class="jws-mb__input" readonly value="%s"><button type="button" class="button jws-mb__copy-btn" title="%s"><span class="dashicons dashicons-admin-page"></span></button></div>',
			esc_attr( $value ),
			esc_attr__( 'Copy', 'jws_streamvid' )
		);
		echo '</div>';
	}
	echo '</div>';
	printf(
		'<p class="field-item"><a href="#" class="remove-live-stream button button-link-delete" data-id="%d">%s</a></p>',
		(int) $post->ID,
		esc_html__( 'Remove Live Stream Data', 'jws_streamvid' )
	);
	echo '</div>';
}
