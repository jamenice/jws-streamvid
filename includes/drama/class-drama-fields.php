<?php

/**
 * ACF field groups for short drama.
 *
 * Declared in code the same way the theme declares its movie and episode
 * groups, so the fields travel with the plugin instead of living only in a
 * database export.
 *
 * The episode's video fields deliberately reuse the meta keys the rest of the
 * site already uses — `videos_type`, `videos_file`, `videos_url`,
 * `quality_lists`, `sub_titles`. That is what lets the encode / Bunny /
 * Cloudflare pipeline and the existing player template read a drama episode
 * without a single special case.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/drama
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Drama_Fields {

	const GROUP_DRAMA   = 'jws_drama_metabox';
	const GROUP_EPISODE = 'jws_drama_ep_metabox';

	public function register() {

		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		$this->register_drama_group();
		$this->register_episode_group();
	}

	/* ---------------------------------------------------------------------- */
	/* Drama (the series)                                                      */
	/* ---------------------------------------------------------------------- */

	private function register_drama_group() {

		$g = self::GROUP_DRAMA;
		$k = 'field_drama_';

		acf_add_local_field_group(
			array(
				'key'      => $g,
				'title'    => 'Drama Setting',
				'fields'   => array(),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => Jws_Drama_Post_Types::DRAMA,
						),
					),
				),
			)
		);

		acf_add_local_field( array(
			'key'    => $k . 'tab_info',
			'label'  => 'Drama Info',
			'name'   => 'drama_tab_info',
			'type'   => 'tab',
			'parent' => $g,
		) );

		/* Short drama is shot vertically; the landscape thumbnail the rest of
		   the site uses is the wrong crop for a drama card. */
		acf_add_local_field( array(
			'key'          => $k . 'poster',
			'label'        => 'Vertical Poster (9:16)',
			'name'         => 'drama_poster',
			'type'         => 'image',
			'return_format' => 'array',
			'preview_size' => 'medium',
			'instructions' => 'Portrait artwork used on drama cards and the player. Falls back to the featured image when empty.',
			'parent'       => $g,
		) );

		acf_add_local_field( array(
			'key'     => $k . 'status',
			'label'   => 'Status',
			'name'    => 'drama_status',
			'type'    => 'select',
			'choices' => array(
				'ongoing'   => 'Ongoing',
				'completed' => 'Completed',
			),
			'default_value' => 'ongoing',
			'parent'  => $g,
		) );

		acf_add_local_field( array(
			'key'          => $k . 'total_ep',
			'label'        => 'Total Episodes (planned)',
			'name'         => 'drama_total_ep',
			'type'         => 'number',
			'min'          => 0,
			'instructions' => 'What the finished series will run to. Display only — the real count comes from the episodes themselves.',
			'parent'       => $g,
		) );

		acf_add_local_field( array(
			'key'          => $k . 'trailer',
			'label'        => 'Trailer Url',
			'name'         => 'drama_trailer',
			'type'         => 'textarea',
			'rows'         => 2,
			'instructions' => 'Optional. mp4 / YouTube url used for the preview reel.',
			'parent'       => $g,
		) );

		acf_add_local_field( array(
			'key'    => $k . 'tab_coin',
			'label'  => 'Unlocking',
			'name'   => 'drama_tab_coin',
			'type'   => 'tab',
			'parent' => $g,
		) );

		acf_add_local_field( array(
			'key'          => $k . 'free_ep',
			'label'        => 'Free Episodes',
			'name'         => 'drama_free_ep',
			'type'         => 'number',
			'min'          => 0,
			'instructions' => 'How many opening episodes anyone can watch. Leave empty to use the site default from Jws Settings → Drama Coins.',
			'parent'       => $g,
		) );

		acf_add_local_field( array(
			'key'          => $k . 'coin_per_ep',
			'label'        => 'Coins per Episode',
			'name'         => 'drama_coin_per_ep',
			'type'         => 'number',
			'min'          => 0,
			'instructions' => 'Cost to unlock one episode past the free ones. Leave empty to use the site default from Jws Settings → Drama Coins.',
			'parent'       => $g,
		) );
	}

	/* ---------------------------------------------------------------------- */
	/* Episode                                                                 */
	/* ---------------------------------------------------------------------- */

	private function register_episode_group() {

		$g = self::GROUP_EPISODE;
		$k = 'field_drama_ep_';

		acf_add_local_field_group(
			array(
				'key'      => $g,
				'title'    => 'Episode Setting',
				'fields'   => array(),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => Jws_Drama_Post_Types::EPISODE,
						),
					),
				),
			)
		);

		acf_add_local_field( array(
			'key'    => $k . 'tab_info',
			'label'  => 'Episode',
			'name'   => 'drama_ep_tab_info',
			'type'   => 'tab',
			'parent' => $g,
		) );

		acf_add_local_field( array(
			'key'           => $k . 'drama_id',
			'label'         => 'Belongs to Drama',
			'name'          => 'drama_id',
			'type'          => 'post_object',
			'post_type'     => array( Jws_Drama_Post_Types::DRAMA ),
			'return_format' => 'id',
			'ui'            => 1,
			'required'      => 1,
			'parent'        => $g,
		) );

		acf_add_local_field( array(
			'key'          => $k . 'number',
			'label'        => 'Episode Number',
			'name'         => 'drama_ep_number',
			'type'         => 'number',
			'min'          => 1,
			'required'     => 1,
			'instructions' => 'Also drives the running order.',
			'parent'       => $g,
		) );

		acf_add_local_field( array(
			'key'          => $k . 'free',
			'label'        => 'Always Free',
			'name'         => 'drama_ep_free',
			'type'         => 'true_false',
			'ui'           => 1,
			'instructions' => 'Opens this episode to everyone regardless of where it falls in the series.',
			'parent'       => $g,
		) );

		acf_add_local_field( array(
			'key'    => $k . 'duration',
			'label'  => 'Duration',
			'name'   => 'videos_time',
			'type'   => 'text',
			'placeholder' => '00:02:10',
			'parent' => $g,
		) );

		acf_add_local_field( array(
			'key'    => $k . 'tab_video',
			'label'  => 'Video',
			'name'   => 'drama_ep_tab_video',
			'type'   => 'tab',
			'parent' => $g,
		) );

		/* Same names as the movie / tv episode groups on purpose — see the file
		   header. Changing any of these breaks the shared player pipeline. */
		acf_add_local_field( array(
			'key'     => $k . 'videos_type',
			'label'   => 'Videos Type',
			'name'    => 'videos_type',
			'type'    => 'select',
			'choices' => array(
				'file'         => 'File',
				'url'          => 'Url',
				'many_quality' => 'Many Quality',
			),
			'default_value' => 'url',
			'parent'  => $g,
		) );

		acf_add_local_field( array(
			'key'               => $k . 'videos_file',
			'label'             => 'Videos File',
			'name'              => 'videos_file',
			'type'              => 'file',
			'mime_types'        => 'mp4, mov, avi',
			'conditional_logic' => array(
				array(
					array( 'field' => $k . 'videos_type', 'operator' => '==', 'value' => 'file' ),
				),
			),
			'parent'            => $g,
		) );

		acf_add_local_field( array(
			'key'               => $k . 'videos_url',
			'label'             => 'Videos Url',
			'name'              => 'videos_url',
			'type'              => 'textarea',
			'rows'              => 2,
			'instructions'      => 'mp4 or m3u8 url, a YouTube url, iframe html, or a shortcode.',
			'conditional_logic' => array(
				array(
					array( 'field' => $k . 'videos_type', 'operator' => '==', 'value' => 'url' ),
				),
			),
			'parent'            => $g,
		) );

		acf_add_local_field( array(
			'key'               => $k . 'quality_lists',
			'label'             => 'Quality Lists',
			'name'              => 'quality_lists',
			'type'              => 'repeater',
			'layout'            => 'table',
			'button_label'      => 'Add Quality',
			'conditional_logic' => array(
				array(
					array( 'field' => $k . 'videos_type', 'operator' => '==', 'value' => 'many_quality' ),
				),
			),
			'parent'            => $g,
		) );

		acf_add_local_field( array(
			'key'         => $k . 'quality_label',
			'label'       => 'Label',
			'name'        => 'label',
			'type'        => 'text',
			'placeholder' => '720p',
			'parent'      => $k . 'quality_lists',
		) );

		acf_add_local_field( array(
			'key'    => $k . 'quality_url',
			'label'  => 'Url',
			'name'   => 'quality_url',
			'type'   => 'text',
			'parent' => $k . 'quality_lists',
		) );

		acf_add_local_field( array(
			'key'          => $k . 'sub_titles',
			'label'        => 'Subtitles',
			'name'         => 'sub_titles',
			'type'         => 'repeater',
			'layout'       => 'table',
			'button_label' => 'Add Subtitle',
			'parent'       => $g,
		) );

		acf_add_local_field( array(
			'key'         => $k . 'sub_language',
			'label'       => 'Language',
			'name'        => 'language',
			'type'        => 'text',
			'placeholder' => 'English',
			'parent'      => $k . 'sub_titles',
		) );

		acf_add_local_field( array(
			'key'        => $k . 'sub_vtt_file',
			'label'      => 'VTT File',
			'name'       => 'vtt_file',
			'type'       => 'file',
			'return_format' => 'array',
			'parent'     => $k . 'sub_titles',
		) );

		acf_add_local_field( array(
			'key'    => $k . 'sub_vtt_url',
			'label'  => 'VTT Url',
			'name'   => 'vtt_url',
			'type'   => 'text',
			'parent' => $k . 'sub_titles',
		) );
	}
}
