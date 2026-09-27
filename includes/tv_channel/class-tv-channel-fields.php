<?php

/**
 * The channel edit screen: stream, channel details and the program guide.
 *
 * Declared with Jws_Metabox, the box system every other post type on the site
 * uses, so values are stored ACF-style and get_field() reads them too — the
 * schedule repeater lands as `tv_schedule` (row count) plus
 * `tv_schedule_{i}_{field}`, which is what Jws_Tv_Channel_Schedule reads.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/tv_channel
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Tv_Channel_Fields {

	public static function register() {

		if ( ! class_exists( 'Jws_Metabox' ) ) {
			return;
		}

		$k = 'field_tv_channel_';

		$tab = function ( $label, $icon ) {
			return array( 'type' => 'tab', 'label' => $label, 'icon' => $icon );
		};

		Jws_Metabox::register(
			'tv_channel_main',
			array(
				'title'      => __( 'Channel Settings', 'jws_streamvid' ),
				'post_types' => array( Jws_Tv_Channel::POST_TYPE ),
				'fields'     => array(

					$tab( __( 'Stream', 'jws_streamvid' ), 'dashicons-video-alt3' ),
					array(
						'type'        => 'textarea',
						'name'        => 'tv_stream_url',
						'key'         => $k . 'stream_url',
						'label'       => __( 'Stream', 'jws_streamvid' ),
						'desc'        => __( 'An HLS playlist (.m3u8), a YouTube live link, or an embed code. Played by the site player, so membership rules and the player logo apply.', 'jws_streamvid' ),
						'placeholder' => 'https://…/live/playlist.m3u8',
						'rows'        => 3,
					),

					$tab( __( 'Channel', 'jws_streamvid' ), 'dashicons-desktop' ),
					array(
						'type'  => 'number',
						'name'  => 'tv_channel_number',
						'key'   => $k . 'number',
						'label' => __( 'Channel number', 'jws_streamvid' ),
						'desc'  => __( 'Sets the order in lists and in the TV guide.', 'jws_streamvid' ),
						'attrs' => array( 'min' => '0', 'step' => '1' ),
						'width' => 33,
					),
					array(
						'type'    => 'select',
						'name'    => 'tv_channel_quality',
						'key'     => $k . 'quality',
						'label'   => __( 'Quality', 'jws_streamvid' ),
						'choices' => array(
							''    => __( '—', 'jws_streamvid' ),
							'SD'  => 'SD',
							'HD'  => 'HD',
							'FHD' => 'Full HD',
							'4K'  => '4K',
						),
						'default' => 'HD',
						'width'   => 33,
					),
					array(
						'type'        => 'text',
						'name'        => 'tv_channel_language',
						'key'         => $k . 'language',
						'label'       => __( 'Language', 'jws_streamvid' ),
						'placeholder' => 'English',
						'width'       => 34,
					),
					array(
						'type'          => 'image',
						'name'          => 'tv_channel_logo',
						'key'           => $k . 'logo',
						'label'         => __( 'Logo', 'jws_streamvid' ),
						'desc'          => __( 'A light logo on a transparent background reads best — it sits on dark tiles.', 'jws_streamvid' ),
						'return_format' => 'id',
						'width'         => 50,
					),
					array(
						'type'  => 'toggle',
						'name'  => 'tv_channel_featured',
						'key'   => $k . 'featured',
						'label' => __( 'Feature on the Live TV page', 'jws_streamvid' ),
						'desc'  => __( 'Shown large above the channel list. Without one, the first channel on air is.', 'jws_streamvid' ),
						'width' => 50,
					),

					$tab( __( 'TV Guide', 'jws_streamvid' ), 'dashicons-calendar-alt' ),
					array(
						'type'       => 'repeater',
						'name'       => Jws_Tv_Channel_Schedule::META,
						'key'        => $k . 'schedule',
						'label'      => __( 'Programs', 'jws_streamvid' ),
						'desc'       => sprintf(
							/* translators: %s: timezone name */
							__( 'Times are in the site timezone (%s). Leave "Ends" empty to run until the next program. A row with a date only airs that day and replaces the weekly programs it overlaps.', 'jws_streamvid' ),
							wp_timezone_string()
						),
						'add_label'  => __( 'Add program', 'jws_streamvid' ),
						'row_title'  => 'title',
						/* A guide runs to dozens of rows: closed by default, each
						   bar still showing when it airs. */
						'row_meta'   => array( array( 'start', 'end' ), 'date|days' ),
						'collapsed'  => true,
						'toggle_all' => true,
						'max'        => 700,
						'sub_fields' => array(
							array(
								'type'        => 'text',
								'name'        => 'start',
								'key'         => $k . 'sch_start',
								'label'       => __( 'Starts', 'jws_streamvid' ),
								'placeholder' => '20:00',
								'attrs'       => array( 'pattern' => '[0-2]?[0-9][:.][0-5][0-9]', 'inputmode' => 'numeric' ),
								'width'       => 15,
							),
							array(
								'type'        => 'text',
								'name'        => 'end',
								'key'         => $k . 'sch_end',
								'label'       => __( 'Ends', 'jws_streamvid' ),
								'placeholder' => '21:00',
								'attrs'       => array( 'pattern' => '[0-2]?[0-9][:.][0-5][0-9]', 'inputmode' => 'numeric' ),
								'width'       => 15,
							),
							array(
								'type'    => 'select',
								'name'    => 'days',
								'key'     => $k . 'sch_days',
								'label'   => __( 'Airs', 'jws_streamvid' ),
								'choices' => Jws_Tv_Channel_Schedule::day_choices(),
								'default' => 'daily',
								'width'   => 35,
							),
							array(
								'type'  => 'date',
								'name'  => 'date',
								'key'   => $k . 'sch_date',
								'label' => __( 'Only on', 'jws_streamvid' ),
								'width' => 35,
							),
							array(
								'type'  => 'text',
								'name'  => 'title',
								'key'   => $k . 'sch_title',
								'label' => __( 'Title', 'jws_streamvid' ),
								'width' => 65,
							),
							array(
								'type'          => 'image',
								'name'          => 'image',
								'key'           => $k . 'sch_image',
								'label'         => __( 'Image', 'jws_streamvid' ),
								'return_format' => 'id',
								'width'         => 35,
							),
							array(
								'type'  => 'textarea',
								'name'  => 'desc',
								'key'   => $k . 'sch_desc',
								'label' => __( 'Description', 'jws_streamvid' ),
								'rows'  => 2,
							),
						),
					),
				),
			)
		);
	}
}
