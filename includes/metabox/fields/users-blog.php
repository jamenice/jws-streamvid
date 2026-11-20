<?php

/**
 * User profile fields and blog post-format fields — the Jws_Metabox version
 * of themes/streamvid/inc/admin/acf_metabox/post_type/{user,blog}.php.
 *
 * Names and field keys are copied from those files so both write identical meta.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/metabox
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

add_action( 'init', 'jws_metabox_register_users_blog', 20 );

function jws_metabox_register_users_blog() {
	Jws_Metabox::register_user(
		'user_profile',
		array(
			'title'      => __( 'Viewer profile', 'jws_streamvid' ),
			'acf_groups' => array( 'user_metabox' ),
			'fields'     => array(
				array(
					'type'          => 'date',
					'name'          => 'jws_date_of_birth',
					'key'           => 'jws_date_of_birth',
					'label'         => __( 'Date of birth', 'jws_streamvid' ),
					'return_format' => 'd/m/Y',
					// The front-end account page saves dd-mm-yyyy; keep one format for both.
					'store_format'  => 'd-m-Y',
					'width'         => 50,
				),
				array(
					'type'    => 'select',
					'name'    => 'jws_gender',
					'key'     => 'jws_gender',
					'label'   => __( 'Gender', 'jws_streamvid' ),
					'choices' => array(
						''       => __( '— Not set —', 'jws_streamvid' ),
						'male'   => __( 'Male', 'jws_streamvid' ),
						'female' => __( 'Female', 'jws_streamvid' ),
						'other'  => __( 'Other', 'jws_streamvid' ),
					),
					'width'   => 50,
				),
				array( 'type' => 'text', 'name' => 'jws_postcode', 'key' => 'jws_postcode', 'label' => __( 'Postcode', 'jws_streamvid' ), 'width' => 50 ),
				array( 'type' => 'text', 'name' => 'user_phone', 'key' => 'user_phone', 'label' => __( 'Phone', 'jws_streamvid' ), 'width' => 50 ),
			),
		)
	);

	Jws_Metabox::register(
		'post_format_fields',
		array(
			'title'      => __( 'Post Format', 'jws_streamvid' ),
			'post_types' => array( 'post' ),
			'acf_groups' => array( 'blog_audio_metabox', 'blog_gallery_metabox', 'blog_link_metabox', 'blog_quote_metabox', 'blog_video_metabox' ),
			'fields'     => array(
				array(
					'type'   => 'html',
					'name'   => 'format_empty',
					'render' => function () {
						echo '<p class="jws-mb__desc jws-mb__format-empty" hidden>' . esc_html__( 'No extra fields for this format. Audio, Gallery, Link, Quote and Video have their own.', 'jws_streamvid' ) . '</p>';
					},
				),
				array( 'type' => 'text', 'name' => 'blog_audio_url', 'key' => 'blog_audio_url', 'label' => __( 'Audio URL', 'jws_streamvid' ), 'placeholder' => 'https://…/track.mp3', 'formats' => array( 'audio' ) ),
				array( 'type' => 'gallery', 'name' => 'image_gallery_list', 'key' => 'image_gallery_list', 'label' => __( 'Gallery', 'jws_streamvid' ), 'library' => 'image', 'return_format' => 'array', 'formats' => array( 'gallery' ) ),
				array( 'type' => 'text', 'name' => 'blog_name_link', 'key' => 'blog_name_link', 'label' => __( 'Link text', 'jws_streamvid' ), 'width' => 40, 'formats' => array( 'link' ) ),
				array( 'type' => 'text', 'name' => 'blog_url_link', 'key' => 'blog_url_link', 'label' => __( 'Link URL', 'jws_streamvid' ), 'placeholder' => 'https://', 'width' => 60, 'formats' => array( 'link' ) ),
				array( 'type' => 'text', 'name' => 'blog_name_quote', 'key' => 'blog_name_quote', 'label' => __( 'Quote author', 'jws_streamvid' ), 'formats' => array( 'quote' ) ),
				array( 'type' => 'text', 'name' => 'blog_video', 'key' => 'blog_video', 'label' => __( 'Video URL', 'jws_streamvid' ), 'placeholder' => 'https://youtube.com/…', 'formats' => array( 'video' ) ),
			),
		)
	);
}
