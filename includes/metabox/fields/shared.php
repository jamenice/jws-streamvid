<?php

/**
 * Field groups shared by several post types (movies, episodes, tv shows,
 * videos, drama). Each builder takes the ACF key prefix of its post type
 * (`field_mov_`, `field_ep_`, …) so the keys stay identical to the theme's
 * ACF definitions.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/metabox
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

function jws_mb_tab( $label, $icon ) {
	return array( 'type' => 'tab', 'label' => $label, 'icon' => $icon );
}

/** Core meta box ids of taxonomies edited in a Taxonomies tab instead. */
function jws_mb_taxonomy_boxes( $taxonomies ) {
	$ids = array();
	foreach ( $taxonomies as $tax ) {
		$ids[] = $tax . 'div';
		$ids[] = 'tagsdiv-' . $tax;
	}
	return $ids;
}

/**
 * Override field keys where a post type strays from the `{prefix}{name}`
 * pattern. $map is 'name' => key or 'parent/child' => key.
 */
function jws_mb_rekey( $fields, $map, $parent = '' ) {
	foreach ( $fields as $i => $field ) {
		if ( empty( $field['name'] ) ) {
			continue;
		}
		$path = '' === $parent ? $field['name'] : $parent . '/' . $field['name'];
		if ( isset( $map[ $path ] ) ) {
			$fields[ $i ]['key'] = $map[ $path ];
		}
		if ( ! empty( $field['sub_fields'] ) ) {
			$fields[ $i ]['sub_fields'] = jws_mb_rekey( $field['sub_fields'], $map, $path );
		}
	}
	return $fields;
}

function jws_mb_field_taxonomy( $name, $key, $label, $taxonomy, $multiple = true ) {
	return array(
		'type'     => 'taxonomy',
		'name'     => $name,
		'key'      => $key,
		'label'    => $label,
		'taxonomy' => $taxonomy,
		'multiple' => $multiple,
		'width'    => 50,
	);
}

/* -------------------------------------------------------------------------- */
/* Main video                                                                 */
/* -------------------------------------------------------------------------- */

function jws_mb_field_video_type( $k, $width = 33, $qualities = true ) {
	$choices = array(
		'file' => __( 'Media file', 'jws_streamvid' ),
		'url'  => __( 'URL / embed', 'jws_streamvid' ),
	);
	if ( $qualities ) {
		$choices['many_quality'] = __( 'Multiple qualities', 'jws_streamvid' );
	}
	return array(
		'type'    => 'select',
		'name'    => 'videos_type',
		'key'     => $k . 'videos_type',
		'label'   => __( 'Video Source', 'jws_streamvid' ),
		'choices' => $choices,
		'width'   => $width,
	);
}

/** File / URL / quality list, each shown for its source type. */
function jws_mb_fields_video_files( $k, $qualities = true ) {
	$fields = array(
		array(
			'type'          => 'file',
			'name'          => 'videos_file',
			'key'           => $k . 'videos_file',
			'label'         => __( 'Video File', 'jws_streamvid' ),
			'library'       => 'video',
			'return_format' => 'array',
			'conditions'    => array( 'field' => 'videos_type', 'value' => array( 'file', '' ) ),
		),
		array(
			'type'       => 'textarea',
			'name'       => 'videos_url',
			'key'        => $k . 'videos_url',
			'label'      => __( 'Video URL', 'jws_streamvid' ),
			'desc'       => __( 'mp4 / m3u8 / YouTube url, iframe html, or a shortcode from another plugin.', 'jws_streamvid' ),
			'conditions' => array( 'field' => 'videos_type', 'value' => 'url' ),
		),
		array(
			'type'       => 'repeater',
			'name'       => 'quality_lists',
			'key'        => $k . 'quality_lists',
			'label'      => __( 'Qualities', 'jws_streamvid' ),
			'add_label'  => __( 'Add quality', 'jws_streamvid' ),
			'row_title'  => 'label',
			'max'        => 200,
			'conditions' => array( 'field' => 'videos_type', 'value' => 'many_quality' ),
			'sub_fields' => array(
				array( 'type' => 'text', 'name' => 'label', 'key' => $k . 'label', 'label' => __( 'Label', 'jws_streamvid' ), 'placeholder' => '720P', 'width' => 25 ),
				array( 'type' => 'text', 'name' => 'quality_url', 'key' => $k . 'quality_url', 'label' => __( 'URL', 'jws_streamvid' ), 'width' => 75 ),
			),
		),
	);
	return $qualities ? $fields : array_slice( $fields, 0, 2 );
}

function jws_mb_field_subtitles( $k ) {
	return array(
		'type'       => 'repeater',
		'name'       => 'sub_titles',
		'key'        => $k . 'sub_titles',
		'label'      => __( 'Subtitles', 'jws_streamvid' ),
		'add_label'  => __( 'Add subtitle', 'jws_streamvid' ),
		'row_title'  => 'language',
		'max'        => 200,
		'sub_fields' => array(
			array( 'type' => 'text', 'name' => 'language', 'key' => $k . 'language', 'label' => __( 'Language', 'jws_streamvid' ), 'placeholder' => 'en', 'width' => 20 ),
			array( 'type' => 'file', 'name' => 'vtt_file', 'key' => $k . 'vtt_file', 'label' => __( 'VTT File', 'jws_streamvid' ), 'library' => '', 'return_format' => 'array', 'width' => 35 ),
			array( 'type' => 'text', 'name' => 'vtt_url', 'key' => $k . 'vtt_url', 'label' => __( 'or VTT URL', 'jws_streamvid' ), 'placeholder' => 'https://…/en.vtt', 'width' => 45 ),
		),
	);
}

function jws_mb_field_sources( $k ) {
	return array(
		'type'       => 'repeater',
		'name'       => 'sources',
		'key'        => $k . 'sources',
		'label'      => __( 'Extra sources', 'jws_streamvid' ),
		'desc'       => __( 'Alternative servers shown in the player’s source switcher (max 5).', 'jws_streamvid' ),
		'add_label'  => __( 'Add source', 'jws_streamvid' ),
		'row_title'  => 'player',
		'max'        => 5,
		'sub_fields' => array(
			array( 'type' => 'toggle', 'name' => 'main', 'key' => $k . 'main', 'label' => __( 'Use main source', 'jws_streamvid' ), 'width' => 20 ),
			array( 'type' => 'text', 'name' => 'player', 'key' => $k . 'player', 'label' => __( 'Player / server', 'jws_streamvid' ), 'width' => 30 ),
			array( 'type' => 'text', 'name' => 'quality', 'key' => $k . 'quality', 'label' => __( 'Quality', 'jws_streamvid' ), 'width' => 15 ),
			array( 'type' => 'text', 'name' => 'language', 'key' => $k . 'language', 'label' => __( 'Language', 'jws_streamvid' ), 'width' => 15 ),
			array( 'type' => 'text', 'name' => 'date', 'key' => $k . 'date', 'label' => __( 'Date', 'jws_streamvid' ), 'width' => 20 ),
			array( 'type' => 'textarea', 'name' => 'url', 'key' => $k . 'url', 'label' => __( 'Source URL / embed', 'jws_streamvid' ), 'rows' => 2, 'conditions' => array( 'field' => 'main', 'value' => '0' ) ),
		),
	);
}

function jws_mb_fields_download( $k ) {
	return array(
		array( 'type' => 'toggle', 'name' => 'download', 'key' => $k . 'download', 'label' => __( 'Enable download', 'jws_streamvid' ) ),
		array(
			'type'       => 'repeater',
			'name'       => 'download_list',
			'key'        => $k . 'download_list',
			'label'      => __( 'Download files', 'jws_streamvid' ),
			'add_label'  => __( 'Add file', 'jws_streamvid' ),
			'row_title'  => 'download_name',
			'max'        => 200,
			'conditions' => array( 'field' => 'download', 'value' => '1' ),
			'sub_fields' => array(
				array( 'type' => 'text', 'name' => 'download_name', 'key' => $k . 'download_name', 'label' => __( 'File name', 'jws_streamvid' ), 'placeholder' => '1080p', 'width' => 30 ),
				array( 'type' => 'text', 'name' => 'download_url', 'key' => $k . 'download_url', 'label' => __( 'Download URL', 'jws_streamvid' ), 'desc' => __( 'An mp4 from the media library or an external url.', 'jws_streamvid' ), 'width' => 70 ),
			),
		),
	);
}

function jws_mb_field_preview() {
	return array(
		'type'   => 'html',
		'name'   => 'videos_preview',
		'desc'   => __( 'Shows the saved settings — update the post, then reload to preview changes.', 'jws_streamvid' ),
		'render' => 'jws_metabox_render_video_preview',
	);
}

/* -------------------------------------------------------------------------- */
/* Trailer, people, money, images                                             */
/* -------------------------------------------------------------------------- */

function jws_mb_fields_trailer( $k ) {
	return array(
		array(
			'type'    => 'select',
			'name'    => 'videos_trailer_type',
			'key'     => $k . 'videos_trailer_type',
			'label'   => __( 'Trailer Type', 'jws_streamvid' ),
			'choices' => array( 'file' => __( 'Media file', 'jws_streamvid' ), 'url' => __( 'URL / embed', 'jws_streamvid' ) ),
			'width'   => 33,
		),
		array(
			'type'          => 'file',
			'name'          => 'videos_trailer_file',
			'key'           => $k . 'videos_trailer_file',
			'label'         => __( 'Trailer File', 'jws_streamvid' ),
			'library'       => 'video',
			'return_format' => 'array',
			'conditions'    => array( 'field' => 'videos_trailer_type', 'value' => array( 'file', '' ) ),
		),
		array(
			'type'       => 'textarea',
			'name'       => 'videos_trailer_url',
			'key'        => $k . 'videos_trailer_url',
			'label'      => __( 'Trailer Url', 'jws_streamvid' ),
			'desc'       => __( 'mp4 / YouTube url, iframe html or a shortcode.', 'jws_streamvid' ),
			'conditions' => array( 'field' => 'videos_trailer_type', 'value' => 'url' ),
		),
	);
}

function jws_mb_fields_cast_crew( $k ) {
	$person = function ( $key ) {
		return array( 'type' => 'posts', 'name' => 'person', 'key' => $key, 'label' => __( 'Person', 'jws_streamvid' ), 'post_type' => array( 'person' ), 'width' => 55 );
	};
	return array(
		array(
			'type'       => 'repeater',
			'name'       => 'cast',
			'key'        => $k . 'cast',
			'label'      => __( 'Cast', 'jws_streamvid' ),
			'add_label'  => __( 'Add cast member', 'jws_streamvid' ),
			'row_title'  => 'as',
			'max'        => 100,
			'width'      => 50,
			'sub_fields' => array(
				$person( $k . 'person' ),
				array( 'type' => 'text', 'name' => 'as', 'key' => $k . 'as', 'label' => __( 'As', 'jws_streamvid' ), 'width' => 45 ),
			),
		),
		array(
			'type'       => 'repeater',
			'name'       => 'crew',
			'key'        => $k . 'crew',
			'label'      => __( 'Crew', 'jws_streamvid' ),
			'add_label'  => __( 'Add crew member', 'jws_streamvid' ),
			'row_title'  => 'job',
			'max'        => 100,
			'width'      => 50,
			'sub_fields' => array(
				$person( $k . 'crew_person' ),
				array( 'type' => 'text', 'name' => 'job', 'key' => $k . 'job', 'label' => __( 'Job', 'jws_streamvid' ), 'width' => 45 ),
			),
		),
	);
}

/** Buy / rent (pay per view). */
function jws_mb_fields_ppv( $k ) {
	return array(
		array( 'type' => 'toggle', 'name' => 'buy_enable', 'key' => $k . 'buy_enable', 'label' => __( 'Enable Buy', 'jws_streamvid' ), 'width' => 50 ),
		array( 'type' => 'number', 'name' => 'buy_price', 'key' => $k . 'buy_price', 'label' => __( 'Buy Price', 'jws_streamvid' ), 'width' => 50, 'attrs' => array( 'step' => '0.01', 'min' => '0' ), 'conditions' => array( 'field' => 'buy_enable', 'value' => '1' ) ),
		array( 'type' => 'toggle', 'name' => 'rent_enable', 'key' => $k . 'rent_enable', 'label' => __( 'Enable Rent', 'jws_streamvid' ), 'width' => 34 ),
		array( 'type' => 'number', 'name' => 'rent_price', 'key' => $k . 'rent_price', 'label' => __( 'Rent Price', 'jws_streamvid' ), 'width' => 33, 'attrs' => array( 'step' => '0.01', 'min' => '0' ), 'conditions' => array( 'field' => 'rent_enable', 'value' => '1' ) ),
		array( 'type' => 'number', 'name' => 'rent_day', 'key' => $k . 'rent_day', 'label' => __( 'Days to watch', 'jws_streamvid' ), 'width' => 33, 'attrs' => array( 'step' => '1', 'min' => '0' ), 'conditions' => array( 'field' => 'rent_enable', 'value' => '1' ) ),
	);
}

/**
 * Poster label pills — "4K", "ENSUB", "NEW", "Trending"… — picked from the
 * `content_badge` taxonomy (see class-jws-content-badges.php), shared by
 * movies, tv_shows and drama so the vocabulary and each label's color are
 * edited in one place. Separate from the single `videos_badge` text field
 * (the content rating, "HD" / "TV-MA").
 */
function jws_mb_field_badges( $k ) {
	$field         = jws_mb_field_taxonomy( 'content_badges', $k . 'content_badges', __( 'Badges', 'jws_streamvid' ), JWS_CONTENT_BADGE_TAX );
	$field['desc'] = __( 'Shown on the poster. Manage the list and each badge\'s color under Content Badges.', 'jws_streamvid' );
	$field['width'] = 100;
	return $field;
}

function jws_mb_field_ads( $k ) {
	return array(
		'type'      => 'posts',
		'name'      => 'videos_ads_special',
		'key'       => $k . 'videos_ads_special',
		'label'     => __( 'Ads Special (VMAP)', 'jws_streamvid' ),
		'post_type' => array( 'adsvmap' ),
		'width'     => 50,
	);
}

function jws_mb_field_featured_clips( $k ) {
	return array(
		'type'      => 'posts',
		'name'      => 'featured_clips',
		'key'       => $k . 'featured_clips',
		'label'     => __( 'Featured Clips', 'jws_streamvid' ),
		'post_type' => array( 'videos' ),
		'multiple'  => true,
	);
}

/** Side box: second featured image and title logo. */
function jws_mb_fields_side_images( $k ) {
	return array(
		array( 'type' => 'image', 'name' => 'featured_image_two', 'key' => $k . 'featured_image_two', 'label' => __( 'Featured image 2', 'jws_streamvid' ), 'return_format' => 'id' ),
		array( 'type' => 'image', 'name' => 'title_images', 'key' => $k . 'title_images', 'label' => __( 'Title Image', 'jws_streamvid' ), 'return_format' => 'id' ),
	);
}

/* -------------------------------------------------------------------------- */
/* Video preview                                                              */
/* -------------------------------------------------------------------------- */

/**
 * Admin preview: the post's own embed page in an iframe. The embed template
 * runs the real front-end player, so the box always matches what visitors get
 * instead of rebuilding the player — and every source type (mp4, hls, youtube,
 * iframe, shortcode, live, bunny, cloudflare) is handled in one place.
 *
 * The iframe ships with no `src`: that player starts on its own (and some
 * sources, YouTube included, start with sound), so merely opening the post to
 * edit a title used to blast audio at whoever was editing. The real URL sits in
 * `data-src` behind a poster and a play button, and metabox.js moves it over on
 * the first click — nothing loads, and no request reaches the source, until the
 * editor actually asks to watch.
 *
 * Shared by every post type with a video.
 */
function jws_metabox_render_video_preview( $post ) {
	if ( ! in_array( $post->post_status, array( 'publish', 'private' ), true ) ) {
		echo '<div class="jws-mb__preview-empty"><span class="dashicons dashicons-format-video"></span><p>' . esc_html__( 'Publish the post to preview the player.', 'jws_streamvid' ) . '</p></div>';
		return;
	}

	$ratio = get_post_meta( $post->ID, 'video_ratio', true );
	if ( ! $ratio && function_exists( 'jws_theme_get_option' ) ) {
		$ratio = jws_theme_get_option( 'video_ratio' );
	}
	$ratio = $ratio ? $ratio : '16x9';

	$src = add_query_arg( 'jws_mb_preview', '1', get_post_embed_url( $post->ID ) );

	// The post's own poster stands in for the player until it is asked for.
	$poster = get_the_post_thumbnail_url( $post->ID, 'large' );
	$style  = $poster ? sprintf( ' style="background-image:url(%s)"', esc_url( $poster ) ) : '';

	echo '<div class="jws-mb__preview">';
	printf(
		'<div class="jws-mb__preview-frame jws-mb__ratio-%1$s is-idle"%5$s>' .
			'<iframe data-src="%2$s" title="%3$s" allow="autoplay; fullscreen; encrypted-media; picture-in-picture" allowfullscreen></iframe>' .
			'<button type="button" class="jws-mb__preview-play" aria-label="%4$s"><span class="dashicons dashicons-controls-play"></span></button>' .
		'</div>',
		esc_attr( $ratio ),
		esc_url( $src ),
		esc_attr( get_the_title( $post ) ),
		esc_attr__( 'Load the player', 'jws_streamvid' ),
		$style // phpcs:ignore WordPress.Security.EscapeOutput -- built from esc_url() above.
	);
	printf(
		'<p class="jws-mb__desc"><a href="%1$s" target="_blank" rel="noopener">%2$s</a> <code>%1$s</code></p>',
		esc_url( $src ),
		esc_html__( 'Open in a new tab', 'jws_streamvid' )
	);
	echo '</div>';
}

/**
 * Post types with no embed-{post_type}.php of their own fall back to WordPress'
 * little link card, which is no use as a player preview — point those at the
 * theme's movies embed instead. Only for the preview iframe, so what the rest
 * of the web gets from oEmbed is unchanged.
 */
function jws_metabox_preview_embed_template( $template ) {
	if ( empty( $_GET['jws_mb_preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- read-only template switch, capability checked below.
		return $template;
	}
	$post = get_post();
	if ( ! $post || ! current_user_can( 'edit_post', $post->ID ) ) {
		return $template;
	}
	// A theme template was found (not WordPress' theme-compat card) — keep it.
	if ( $template && false === strpos( $template, '/theme-compat/' ) ) {
		return $template;
	}
	$fallback = locate_template( 'embed-movies.php' );
	return $fallback ? $fallback : $template;
}
add_filter( 'embed_template', 'jws_metabox_preview_embed_template', 20 );
