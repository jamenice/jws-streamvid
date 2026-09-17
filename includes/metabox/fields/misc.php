<?php

/**
 * Meta boxes for the smaller post types — person, page, advertising, ads
 * VMAP, product and questions — replacing the theme's ACF groups in
 * themes/streamvid/inc/admin/acf_metabox/post_type/{person,page,advertising,
 * adsvmap,products,questions}.php.
 *
 * Names and field keys are copied from those files so both write identical meta.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/metabox
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

add_action( 'init', 'jws_metabox_register_misc', 20 );

function jws_metabox_register_misc() {
	jws_metabox_register_person();
	jws_metabox_register_page();
	jws_metabox_register_ads();
	jws_metabox_register_product();
	jws_metabox_register_questions();
}

function jws_metabox_register_person() {
	$k = 'field_person_';
	Jws_Metabox::register(
		'person_main',
		array(
			'title'      => __( 'Person Details', 'jws_streamvid' ),
			'post_types' => array( 'person' ),
			'acf_groups' => array( 'person_metabox' ),
			'fields'     => array(
				array( 'type' => 'text', 'name' => 'know', 'key' => $k . 'know', 'label' => __( 'Known for', 'jws_streamvid' ), 'placeholder' => __( 'Acting', 'jws_streamvid' ), 'width' => 33 ),
				array(
					'type'    => 'select',
					'name'    => 'gender',
					'key'     => $k . 'gender',
					'label'   => __( 'Gender', 'jws_streamvid' ),
					'choices' => array(
						''       => __( '— Not set —', 'jws_streamvid' ),
						'male'   => __( 'Male', 'jws_streamvid' ),
						'female' => __( 'Female', 'jws_streamvid' ),
						'other'  => __( 'Other', 'jws_streamvid' ),
					),
					'width'   => 33,
				),
				array( 'type' => 'date', 'name' => 'birthday', 'key' => $k . 'birthday', 'label' => __( 'Birthday', 'jws_streamvid' ), 'return_format' => 'd/m/Y', 'width' => 33 ),
				array( 'type' => 'text', 'name' => 'address', 'key' => $k . 'address', 'label' => __( 'Place of Birth', 'jws_streamvid' ), 'width' => 50 ),
				array( 'type' => 'text', 'name' => 'know_as', 'key' => $k . 'know_as', 'label' => __( 'Also Known As', 'jws_streamvid' ), 'width' => 50 ),
				array( 'type' => 'text', 'name' => 'videos_tmdb', 'key' => $k . 'videos_tmdb', 'label' => __( 'TMDB ID', 'jws_streamvid' ), 'width' => 33 ),
			),
		)
	);
}

function jws_metabox_register_page() {
	$k      = 'field_page_';
	$toggle = function ( $name, $label, $desc = '' ) use ( $k ) {
		return array( 'type' => 'toggle', 'name' => $name, 'key' => $k . $name, 'label' => $label, 'desc' => $desc, 'width' => 33 );
	};
	Jws_Metabox::register(
		'page_main',
		array(
			'title'      => __( 'Page Settings', 'jws_streamvid' ),
			'post_types' => array( 'page' ),
			'acf_groups' => array( 'page_metabox' ),
			'fields'     => array(
				jws_mb_tab( __( 'Header & Footer', 'jws_streamvid' ), 'dashicons-align-wide' ),
				array( 'type' => 'posts', 'name' => 'page_select_header', 'key' => $k . 'page_select_header', 'label' => __( 'Header template', 'jws_streamvid' ), 'desc' => __( 'Empty = the header from Theme Options.', 'jws_streamvid' ), 'post_type' => array( 'hf_template' ), 'width' => 50 ),
				array( 'type' => 'posts', 'name' => 'page_select_footer', 'key' => $k . 'page_select_footer', 'label' => __( 'Footer template', 'jws_streamvid' ), 'desc' => __( 'Empty = the footer from Theme Options.', 'jws_streamvid' ), 'post_type' => array( 'hf_template' ), 'width' => 50 ),
				array(
					'type'    => 'select',
					'name'    => 'page_header_absolute',
					'key'     => $k . 'page_header_absolute',
					'label'   => __( 'Header over content (absolute)', 'jws_streamvid' ),
					'choices' => array( '' => __( 'Theme default', 'jws_streamvid' ), 'on' => __( 'Yes', 'jws_streamvid' ), 'off' => __( 'No', 'jws_streamvid' ) ),
					'width'   => 33,
				),
				$toggle( 'turn_off_header', __( 'Turn off header', 'jws_streamvid' ) ),
				$toggle( 'turn_off_footer', __( 'Turn off footer', 'jws_streamvid' ) ),
				$toggle( 'turn_on_header_sidebar', __( 'Header sidebar', 'jws_streamvid' ), __( 'Use the vertical sidebar header on this page.', 'jws_streamvid' ) ),

				jws_mb_tab( __( 'Layout', 'jws_streamvid' ), 'dashicons-layout' ),
				$toggle( 'title_bar_checkbox', __( 'Disable title bar', 'jws_streamvid' ) ),
				$toggle( 'tool_bar_checkbox', __( 'Turn off mobile toolbar', 'jws_streamvid' ) ),
			),
		)
	);
}

function jws_metabox_register_ads() {
	$k = 'field_adsvmap_';

	$slot = function ( $name, $tag, $label, $desc, $with_time = false ) use ( $k ) {
		$subs = array(
			array( 'type' => 'posts', 'name' => $tag, 'key' => $k . $tag, 'label' => __( 'Ad', 'jws_streamvid' ), 'post_type' => array( 'advertising' ), 'width' => $with_time ? 65 : 100 ),
		);
		if ( $with_time ) {
			$subs[] = array( 'type' => 'text', 'name' => 'time_offset', 'key' => $k . 'time_offset', 'label' => __( 'Time offset', 'jws_streamvid' ), 'placeholder' => '00:05:10', 'width' => 35 );
		}
		return array(
			'type'       => 'repeater',
			'name'       => $name,
			'key'        => $k . $name,
			'label'      => $label,
			'desc'       => $desc,
			'add_label'  => __( 'Add ad', 'jws_streamvid' ),
			'row_title'  => $with_time ? 'time_offset' : '',
			'max'        => 100,
			'sub_fields' => $subs,
		);
	};

	Jws_Metabox::register(
		'adsvmap_main',
		array(
			'title'      => __( 'Ad Schedule (VMAP)', 'jws_streamvid' ),
			'post_types' => array( 'adsvmap' ),
			'acf_groups' => array( 'adsvmap_metabox' ),
			'fields'     => array(
				jws_mb_tab( __( 'Pre-roll', 'jws_streamvid' ), 'dashicons-controls-skipback' ),
				$slot( 'preroll', 'ads_tag', __( 'Before the video', 'jws_streamvid' ), __( 'Played in order before playback starts.', 'jws_streamvid' ) ),
				jws_mb_tab( __( 'Mid-roll', 'jws_streamvid' ), 'dashicons-controls-pause' ),
				$slot( 'midroll', 'ads_tag_mid', __( 'During the video', 'jws_streamvid' ), __( 'Each ad plays when playback reaches its time offset (hh:mm:ss).', 'jws_streamvid' ), true ),
				jws_mb_tab( __( 'Post-roll', 'jws_streamvid' ), 'dashicons-controls-skipforward' ),
				$slot( 'postroll', 'ads_tag_end', __( 'After the video', 'jws_streamvid' ), __( 'Played in order when the video ends.', 'jws_streamvid' ) ),
			),
		)
	);

	$k      = 'field_ads_';
	$self   = array( 'field' => 'ads_server', 'value' => 'self_ads' );
	$linear = array( $self, array( 'field' => 'ads_type', 'value' => 'linear' ) );

	Jws_Metabox::register(
		'advertising_main',
		array(
			'title'      => __( 'Advertising Settings', 'jws_streamvid' ),
			'post_types' => array( 'advertising' ),
			'acf_groups' => array( 'advertising_metabox' ),
			'fields'     => array(
				array(
					'type'    => 'select',
					'name'    => 'ads_server',
					'key'     => $k . 'ads_server',
					'label'   => __( 'Ad server', 'jws_streamvid' ),
					'choices' => array( 'self_ads' => __( 'Self-hosted ad', 'jws_streamvid' ), 'vast' => __( 'VAST ad server', 'jws_streamvid' ) ),
					'width'   => 50,
				),

				array( 'type' => 'heading', 'name' => 'self_heading', 'label' => __( 'Self-hosted ad', 'jws_streamvid' ), 'conditions' => $self ),
				array(
					'type'       => 'select',
					'name'       => 'ads_type',
					'key'        => $k . 'ads_type',
					'label'      => __( 'Ad type', 'jws_streamvid' ),
					'choices'    => array( 'linear' => __( 'Linear (video)', 'jws_streamvid' ), 'non_linear' => __( 'Non-linear (banner)', 'jws_streamvid' ) ),
					'width'      => 33,
					'conditions' => $self,
				),
				array( 'type' => 'text', 'name' => 'ads_target_url', 'key' => $k . 'ads_target_url', 'label' => __( 'Target URL', 'jws_streamvid' ), 'placeholder' => 'https://', 'width' => 67, 'conditions' => $self ),
				array(
					'type'       => 'group',
					'name'       => 'ads_banner',
					'key'        => $k . 'ads_banner',
					'label'      => __( 'Banner', 'jws_streamvid' ),
					'conditions' => array( $self, array( 'field' => 'ads_type', 'value' => 'non_linear' ) ),
					'sub_fields' => array(
						array( 'type' => 'image', 'name' => 'ads_banner_image', 'key' => $k . 'ads_banner_image', 'label' => __( 'Image', 'jws_streamvid' ), 'return_format' => 'id', 'width' => 60 ),
						array(
							'type'    => 'select',
							'name'    => 'ads_banner_position',
							'key'     => $k . 'ads_banner_position',
							'label'   => __( 'Position', 'jws_streamvid' ),
							'choices' => array( 'middle' => __( 'Middle', 'jws_streamvid' ), 'bottom' => __( 'Bottom', 'jws_streamvid' ) ),
							'width'   => 40,
						),
					),
				),
				array(
					'type'       => 'select',
					'name'       => 'ads_video_source',
					'key'        => $k . 'ads_video_source',
					'label'      => __( 'Video source', 'jws_streamvid' ),
					'choices'    => array( 'file' => __( 'Upload file', 'jws_streamvid' ), 'url' => __( 'URL', 'jws_streamvid' ) ),
					'default'    => 'file',
					'width'      => 33,
					'conditions' => $linear,
				),
				array( 'type' => 'text', 'name' => 'ads_duration', 'key' => $k . 'ads_duration', 'label' => __( 'Duration', 'jws_streamvid' ), 'placeholder' => '00:00:10', 'width' => 33, 'conditions' => $linear ),
				array( 'type' => 'text', 'name' => 'ads_skippable', 'key' => $k . 'ads_skippable', 'label' => __( 'Skippable after', 'jws_streamvid' ), 'placeholder' => '00:00:05', 'desc' => __( 'Empty = cannot be skipped.', 'jws_streamvid' ), 'width' => 33, 'conditions' => $linear ),
				array( 'type' => 'file', 'name' => 'ads_video', 'key' => $k . 'ads_video', 'label' => __( 'Ad video (mp4)', 'jws_streamvid' ), 'library' => 'video', 'return_format' => 'array', 'conditions' => array_merge( $linear, array( array( 'field' => 'ads_video_source', 'value' => 'file' ) ) ) ),
				array( 'type' => 'url', 'name' => 'ads_video_url', 'key' => $k . 'ads_video_url', 'label' => __( 'Ad video URL', 'jws_streamvid' ), 'placeholder' => 'https://…/ad.mp4', 'desc' => __( 'Direct link to the mp4 ad video.', 'jws_streamvid' ), 'conditions' => array_merge( $linear, array( array( 'field' => 'ads_video_source', 'value' => 'url' ) ) ) ),

				array( 'type' => 'heading', 'name' => 'vast_heading', 'label' => __( 'VAST ad server', 'jws_streamvid' ), 'conditions' => array( 'field' => 'ads_server', 'value' => 'vast' ) ),
				array( 'type' => 'textarea', 'name' => 'ads_vast_url', 'key' => $k . 'ads_vast_url', 'label' => __( 'VAST tag URL', 'jws_streamvid' ), 'rows' => 2, 'conditions' => array( 'field' => 'ads_server', 'value' => 'vast' ) ),
			),
		)
	);
}

function jws_metabox_register_product() {
	$k = 'field_product_';
	Jws_Metabox::register(
		'product_main',
		array(
			'title'      => __( 'Product Layout', 'jws_streamvid' ),
			'post_types' => array( 'product' ),
			'priority'   => 'low',
			'acf_groups' => array( 'product_metabox' ),
			'fields'     => array(
				array( 'type' => 'select', 'name' => 'shop_single_layout', 'key' => $k . 'shop_single_layout', 'label' => __( 'Layout', 'jws_streamvid' ), 'choices' => array( '' => __( 'Theme default', 'jws_streamvid' ), 'default' => __( 'Default', 'jws_streamvid' ) ), 'width' => 33 ),
				array(
					'type'    => 'select',
					'name'    => 'shop_single_thumbnail_position',
					'key'     => $k . 'shop_single_thumbnail_position',
					'label'   => __( 'Thumbnail position', 'jws_streamvid' ),
					'choices' => array( '' => __( 'Theme default', 'jws_streamvid' ), 'left' => __( 'Left', 'jws_streamvid' ), 'right' => __( 'Right', 'jws_streamvid' ), 'bottom' => __( 'Bottom', 'jws_streamvid' ), 'bottom2' => __( 'Bottom (4 items)', 'jws_streamvid' ) ),
					'width'   => 33,
				),
				array( 'type' => 'select', 'name' => 'shop_single_video_type', 'key' => $k . 'shop_single_video_type', 'label' => __( 'Video type', 'jws_streamvid' ), 'choices' => array( '' => __( 'Theme default', 'jws_streamvid' ), 'popup' => __( 'Popup', 'jws_streamvid' ), 'inner' => __( 'Inner', 'jws_streamvid' ) ), 'width' => 33 ),
				array( 'type' => 'text', 'name' => 'product_video', 'key' => $k . 'product_video', 'label' => __( 'Product video', 'jws_streamvid' ), 'placeholder' => 'https://youtube.com/…', 'desc' => __( 'YouTube, Vimeo or mp4 link.', 'jws_streamvid' ) ),
			),
		)
	);
}

function jws_metabox_register_questions() {
	$k = 'field_questions_';
	Jws_Metabox::register(
		'questions_main',
		array(
			'title'      => __( 'Question', 'jws_streamvid' ),
			'post_types' => array( 'questions' ),
			'acf_groups' => array( 'questions_metabox' ),
			'fields'     => array(
				array( 'type' => 'text', 'name' => 'product_name', 'key' => $k . 'product_name', 'label' => __( 'Name', 'jws_streamvid' ), 'width' => 50 ),
				array( 'type' => 'text', 'name' => 'product_email', 'key' => $k . 'product_email', 'label' => __( 'Email', 'jws_streamvid' ), 'width' => 50 ),
				array( 'type' => 'textarea', 'name' => 'answer_content', 'key' => $k . 'answer_content', 'label' => __( 'Answer', 'jws_streamvid' ), 'rows' => 5 ),
			),
		)
	);
}
