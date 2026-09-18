<?php

/**
 * Short drama meta boxes — the Jws_Metabox version of the ACF groups in
 * includes/drama/class-drama-fields.php (`jws_drama_metabox`,
 * `jws_drama_metabox_side`, `jws_drama_ep_metabox`).
 *
 * The drama screen also gets an Episodes tab: the episodes linked through
 * `drama_id`, in running order, which can be reordered (renumbering
 * `drama_ep_number`), flagged free, unlinked, attached or bulk-created
 * without leaving the page.
 *
 * Names and field keys are copied from class-drama-fields.php so both write
 * identical meta.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/metabox
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

add_action( 'init', 'jws_metabox_register_drama', 20 );
add_action( 'wp_ajax_jws_mb_drama_create', 'jws_metabox_ajax_drama_create' );
add_action( 'admin_enqueue_scripts', 'jws_metabox_drama_player_assets', 20 );

function jws_metabox_drama_types() {
	return class_exists( 'Jws_Drama_Post_Types' )
		? array( Jws_Drama_Post_Types::DRAMA, Jws_Drama_Post_Types::EPISODE )
		: array( 'drama', 'drama_ep' );
}

function jws_metabox_register_drama() {
	list( $drama, $episode ) = jws_metabox_drama_types();
	$k                       = 'field_drama_';
	$ke                      = 'field_drama_ep_';

	$default_free  = class_exists( 'Jws_Drama_Wallet' ) ? Jws_Drama_Wallet::free_episodes( 0 ) : '';
	$default_price = class_exists( 'Jws_Drama_Wallet' ) ? Jws_Drama_Wallet::coin_price( 0 ) : '';
	$tag_tax       = class_exists( 'Jws_Drama_Post_Types' ) ? Jws_Drama_Post_Types::TAX_TAG : 'drama_tag';

	Jws_Metabox::register(
		'drama_main',
		array(
			'title'      => __( 'Drama Settings', 'jws_streamvid' ),
			'post_types' => array( $drama ),
			'acf_groups' => array( 'jws_drama_metabox' ),
			'hide_boxes' => jws_mb_taxonomy_boxes( array( 'genres', 'countries', 'ages', $tag_tax, JWS_CONTENT_BADGE_TAX ) ),
			'on_save'    => 'jws_metabox_drama_save_episodes',
			'fields'     => array(
				jws_mb_tab( __( 'Episodes', 'jws_streamvid' ), 'dashicons-playlist-video' ),
				array( 'type' => 'html', 'name' => 'drama_episodes', 'render' => 'jws_metabox_render_drama_episodes' ),
				array(
					'type'        => 'posts',
					'name'        => 'attach_episodes',
					'label'       => __( 'Attach existing episodes', 'jws_streamvid' ),
					'desc'        => __( 'Added after the last episode when you update.', 'jws_streamvid' ),
					'placeholder' => __( 'Search drama episodes…', 'jws_streamvid' ),
					'post_type'   => array( $episode ),
					'multiple'    => true,
					'virtual'     => true,
				),

				jws_mb_tab( __( 'Info', 'jws_streamvid' ), 'dashicons-info-outline' ),
				array(
					'type'    => 'select',
					'name'    => 'drama_status',
					'key'     => $k . 'status',
					'label'   => __( 'Status', 'jws_streamvid' ),
					'choices' => array( 'ongoing' => __( 'Ongoing', 'jws_streamvid' ), 'completed' => __( 'Completed', 'jws_streamvid' ) ),
					'default' => 'ongoing',
					'width'   => 50,
				),
				array(
					'type'  => 'number',
					'name'  => 'drama_total_ep',
					'key'   => $k . 'total_ep',
					'label' => __( 'Total Episodes (planned)', 'jws_streamvid' ),
					'desc'  => __( 'What the finished series will run to. Display only — the real count comes from the episodes themselves.', 'jws_streamvid' ),
					'attrs' => array( 'min' => '0', 'step' => '1' ),
					'width' => 50,
				),
				array(
					'type'  => 'textarea',
					'name'  => 'drama_trailer',
					'key'   => $k . 'trailer',
					'label' => __( 'Trailer Url', 'jws_streamvid' ),
					'desc'  => __( 'Optional. mp4 / YouTube url used for the preview reel.', 'jws_streamvid' ),
					'rows'  => 2,
				),
				jws_mb_field_badges( $k ),

				jws_mb_tab( __( 'Unlocking', 'jws_streamvid' ), 'dashicons-lock' ),
				array(
					'type'        => 'number',
					'name'        => 'drama_free_ep',
					'key'         => $k . 'free_ep',
					'label'       => __( 'Free Episodes', 'jws_streamvid' ),
					/* translators: %s: site default */
					'desc'        => sprintf( __( 'How many opening episodes anyone can watch. Empty = site default (%s).', 'jws_streamvid' ), $default_free ),
					'placeholder' => (string) $default_free,
					'attrs'       => array( 'min' => '0', 'step' => '1' ),
					'width'       => 50,
				),
				array(
					'type'        => 'number',
					'name'        => 'drama_coin_per_ep',
					'key'         => $k . 'coin_per_ep',
					'label'       => __( 'Coins per Episode', 'jws_streamvid' ),
					/* translators: %s: site default */
					'desc'        => sprintf( __( 'Cost to unlock one episode past the free ones. Empty = site default (%s).', 'jws_streamvid' ), $default_price ),
					'placeholder' => (string) $default_price,
					'attrs'       => array( 'min' => '0', 'step' => '1' ),
					'width'       => 50,
				),

				jws_mb_tab( __( 'Taxonomies', 'jws_streamvid' ), 'dashicons-tag' ),
				jws_mb_field_taxonomy( 'drama_genres', $k . 'genres', __( 'Genres', 'jws_streamvid' ), 'genres' ),
				jws_mb_field_taxonomy( 'drama_countries', $k . 'countries', __( 'Countries', 'jws_streamvid' ), 'countries' ),
				jws_mb_field_taxonomy( 'drama_ages', $k . 'ages', __( 'Ages', 'jws_streamvid' ), 'ages', false ),
				jws_mb_field_taxonomy( 'drama_tags', $k . 'tags', __( 'Drama Tags', 'jws_streamvid' ), $tag_tax ),
			),
		)
	);

	Jws_Metabox::register(
		'drama_side',
		array(
			'title'      => __( 'Drama Images', 'jws_streamvid' ),
			'post_types' => array( $drama ),
			'context'    => 'side',
			'priority'   => 'low',
			'acf_groups' => array( 'jws_drama_metabox_side' ),
			'fields'     => array(
				array( 'type' => 'image', 'name' => 'featured_image_two', 'key' => $k . 'side_featured_image_two', 'label' => __( 'Cover image', 'jws_streamvid' ), 'return_format' => 'id' ),
			),
		)
	);

	$type_field            = jws_mb_field_video_type( $ke );
	$type_field['default'] = 'url';

	$video = jws_mb_rekey(
		array_merge( array( $type_field ), jws_mb_fields_video_files( $ke ), array( jws_mb_field_subtitles( $ke ) ) ),
		array(
			'quality_lists/label'  => $ke . 'quality_label',
			'sub_titles/language'  => $ke . 'sub_language',
			'sub_titles/vtt_file'  => $ke . 'sub_vtt_file',
			'sub_titles/vtt_url'   => $ke . 'sub_vtt_url',
		)
	);
	$video[0]['width'] = 100;

	Jws_Metabox::register(
		'drama_ep_main',
		array(
			'title'      => __( 'Episode Settings', 'jws_streamvid' ),
			'post_types' => array( $episode ),
			'acf_groups' => array( 'jws_drama_ep_metabox' ),
			'on_save'    => 'jws_metabox_drama_ep_autonumber',
			'fields'     => array_merge(
				array(
					jws_mb_tab( __( 'Episode', 'jws_streamvid' ), 'dashicons-format-video' ),
					array(
						'type'        => 'posts',
						'name'        => 'drama_id',
						'key'         => $ke . 'drama_id',
						'label'       => __( 'Belongs to Drama', 'jws_streamvid' ),
						'placeholder' => __( 'Search drama…', 'jws_streamvid' ),
						'post_type'   => array( $drama ),
						'width'       => 50,
					),
					array(
						'type'        => 'number',
						'name'        => 'drama_ep_number',
						'key'         => $ke . 'number',
						'label'       => __( 'Episode Number', 'jws_streamvid' ),
						'desc'        => __( 'Drives the running order. Leave empty to add it after the last episode.', 'jws_streamvid' ),
						'attrs'       => array( 'min' => '1', 'step' => '1' ),
						'width'       => 25,
					),
					array( 'type' => 'text', 'name' => 'videos_time', 'key' => $ke . 'duration', 'label' => __( 'Duration', 'jws_streamvid' ), 'placeholder' => '00:02:10', 'width' => 25 ),
					array(
						'type'  => 'toggle',
						'name'  => 'drama_ep_free',
						'key'   => $ke . 'free',
						'label' => __( 'Always Free', 'jws_streamvid' ),
						'desc'  => __( 'Opens this episode to everyone regardless of where it falls in the series.', 'jws_streamvid' ),
					),

					jws_mb_tab( __( 'Video', 'jws_streamvid' ), 'dashicons-video-alt3' ),
				),
				$video,
				array(
					jws_mb_tab( __( 'Preview', 'jws_streamvid' ), 'dashicons-visibility' ),
					jws_mb_field_preview(),
				)
			),
		)
	);
}

/** The admin player scripts are only enqueued for movies/videos/episodes by default. */
function jws_metabox_drama_player_assets( $hook ) {
	global $post_type;
	list( , $episode ) = jws_metabox_drama_types();
	if ( 'post.php' !== $hook || $episode !== $post_type || ! Jws_Metabox::active_boxes( $episode ) || ! defined( 'JWS_STREAMVID_URL_PUBLIC_ASSETS' ) ) {
		return;
	}
	$base = JWS_STREAMVID_URL_PUBLIC_ASSETS;
	wp_enqueue_style( 'videojs', $base . '/css/videojs.css', array(), null );
	wp_enqueue_script( 'videojs', $base . '/js/vendor/videojs.min.js', array( 'jquery' ), null, true );
	wp_enqueue_script( 'videojs-http-streaming', $base . '/js/vendor/videojs-http-streaming.js', array( 'videojs' ), null, true );
	wp_enqueue_script( 'videojs-contrib-quality-levels', $base . '/js/vendor/videojs-contrib-quality-levels.min.js', array( 'videojs' ), null, true );
	wp_enqueue_script( 'videojs-hls-quality-selector', $base . '/js/vendor/videojs-hls-quality-selector.min.js', array( 'videojs' ), null, true );
}

/* -------------------------------------------------------------------------- */
/* Episodes tab                                                               */
/* -------------------------------------------------------------------------- */

function jws_metabox_drama_episode_number( $episode_id ) {
	return class_exists( 'Jws_Drama_Wallet' )
		? Jws_Drama_Wallet::episode_number( $episode_id )
		: (int) get_post_meta( $episode_id, 'drama_ep_number', true );
}

function jws_metabox_drama_episode_ids( $drama_id ) {
	return class_exists( 'Jws_Drama_Post_Types' ) ? array_map( 'intval', Jws_Drama_Post_Types::episodes_of( $drama_id ) ) : array();
}

function jws_metabox_drama_episode_row( $episode ) {
	$episode = get_post( $episode );
	$type    = get_post_meta( $episode->ID, 'videos_type', true );
	$has_src = 'file' === $type
		? (bool) get_post_meta( $episode->ID, 'videos_file', true )
		: ( 'many_quality' === $type ? (bool) get_post_meta( $episode->ID, 'quality_lists', true ) : (bool) get_post_meta( $episode->ID, 'videos_url', true ) );
	$free    = (bool) get_post_meta( $episode->ID, 'drama_ep_free', true );
	$status  = get_post_status_object( $episode->post_status );
	$thumb   = get_the_post_thumbnail_url( $episode, 'thumbnail' );
	$base    = Jws_Metabox::INPUT . '[drama_main]';

	ob_start();
	?>
	<li class="jws-mb__episode jws-mb__dep" data-id="<?php echo (int) $episode->ID; ?>" data-number="<?php echo (int) jws_metabox_drama_episode_number( $episode->ID ); ?>">
		<input type="hidden" name="<?php echo esc_attr( $base . '[episodes_order][]' ); ?>" value="<?php echo (int) $episode->ID; ?>">
		<span class="jws-mb__handle dashicons dashicons-menu"></span>
		<span class="jws-mb__ep-no"><?php echo (int) jws_metabox_drama_episode_number( $episode->ID ); ?></span>
		<span class="jws-mb__ep-thumb"><?php echo $thumb ? '<img src="' . esc_url( $thumb ) . '" alt="" loading="lazy">' : '<span class="dashicons dashicons-format-video"></span>'; ?></span>
		<span class="jws-mb__ep-info">
			<a href="<?php echo esc_url( get_edit_post_link( $episode->ID ) ); ?>" target="_blank" class="jws-mb__ep-title"><?php echo esc_html( '' !== $episode->post_title ? $episode->post_title : __( '(no title)', 'jws_streamvid' ) ); ?></a>
			<small><?php echo esc_html( ( $type ? $type : '—' ) . ' · #' . $episode->ID ); ?></small>
		</span>
		<?php if ( 'publish' !== $episode->post_status ) : ?>
			<span class="jws-mb__status jws-mb__status--<?php echo esc_attr( $episode->post_status ); ?>"><?php echo esc_html( $status ? $status->label : $episode->post_status ); ?></span>
		<?php endif; ?>
		<?php if ( ! $has_src ) : ?>
			<span class="jws-mb__pill jws-mb__pill--missing" title="<?php esc_attr_e( 'No video source saved', 'jws_streamvid' ); ?>"><?php esc_html_e( 'No video', 'jws_streamvid' ); ?></span>
		<?php endif; ?>
		<span class="jws-mb__pill jws-mb__dep-access"></span>
		<label class="jws-mb__dep-free" title="<?php esc_attr_e( 'Always free', 'jws_streamvid' ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $base . '[episodes_free][' . $episode->ID . ']' ); ?>" value="0">
			<input type="checkbox" name="<?php echo esc_attr( $base . '[episodes_free][' . $episode->ID . ']' ); ?>" value="1" <?php checked( $free ); ?>>
			<span class="dashicons dashicons-unlock"></span>
		</label>
		<span class="jws-mb__ep-tools">
			<a href="<?php echo esc_url( get_edit_post_link( $episode->ID ) ); ?>" target="_blank" class="jws-mb__icon-btn" title="<?php esc_attr_e( 'Edit episode', 'jws_streamvid' ); ?>"><span class="dashicons dashicons-edit"></span></a>
			<label class="jws-mb__icon-btn jws-mb__dep-remove" title="<?php esc_attr_e( 'Unlink from this drama (on update)', 'jws_streamvid' ); ?>"><input type="checkbox" name="<?php echo esc_attr( $base . '[episodes_remove][]' ); ?>" value="<?php echo (int) $episode->ID; ?>"><span class="dashicons dashicons-no-alt"></span></label>
		</span>
	</li>
	<?php
	return ob_get_clean();
}

function jws_metabox_render_drama_episodes( $post ) {
	$ids      = jws_metabox_drama_episode_ids( $post->ID );
	$free     = class_exists( 'Jws_Drama_Wallet' ) ? Jws_Drama_Wallet::free_episodes( $post->ID ) : 0;
	$price    = class_exists( 'Jws_Drama_Wallet' ) ? Jws_Drama_Wallet::coin_price( $post->ID ) : 0;
	$highest  = 0;
	$drafts   = 0;
	foreach ( $ids as $id ) {
		$highest = max( $highest, jws_metabox_drama_episode_number( $id ) );
		$drafts += 'publish' === get_post_status( $id ) ? 0 : 1;
	}
	_prime_post_caches( $ids, false, true );
	$base    = Jws_Metabox::INPUT . '[drama_main]';
	$manage  = class_exists( 'Jws_Drama_Admin' ) ? add_query_arg( array( 'post_type' => $post->post_type, 'page' => Jws_Drama_Admin::PAGE_EPISODES, 'drama_id' => $post->ID ), admin_url( 'edit.php' ) ) : '';
	list( , $episode_type ) = jws_metabox_drama_types();
	?>
	<div class="jws-mb__drama" data-free="<?php echo (int) $free; ?>" data-price="<?php echo (int) $price; ?>" data-post-type="<?php echo esc_attr( $episode_type ); ?>"
		data-i18n-free="<?php esc_attr_e( 'Free', 'jws_streamvid' ); ?>"
		data-i18n-coins="<?php /* translators: %d: coins */ esc_attr_e( '%d coins', 'jws_streamvid' ); ?>">
		<input type="hidden" class="jws-mb__dep-reordered" name="<?php echo esc_attr( $base . '[episodes_reordered]' ); ?>" value="0">

		<div class="jws-mb__seasons-toolbar">
			<div class="jws-mb__seasons-stats">
				<strong class="jws-mb__dep-total"><?php echo count( $ids ); ?></strong> <?php esc_html_e( 'episodes', 'jws_streamvid' ); ?>
				<?php if ( $drafts ) : ?>
					· <strong><?php echo (int) $drafts; ?></strong> <?php esc_html_e( 'not published', 'jws_streamvid' ); ?>
				<?php endif; ?>
				· <?php
				/* translators: 1: free episodes, 2: coin price */
				printf( esc_html__( 'first %1$d free, then %2$d coins', 'jws_streamvid' ), (int) $free, (int) $price );
				?>
			</div>
			<div class="jws-mb__seasons-actions">
				<?php if ( $manage ) : ?>
					<a class="button-link" href="<?php echo esc_url( $manage ); ?>"><?php esc_html_e( 'Manage Episodes page', 'jws_streamvid' ); ?></a>
				<?php endif; ?>
				<button type="button" class="button button-primary jws-mb__dep-create-toggle"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Create episodes', 'jws_streamvid' ); ?></button>
			</div>
		</div>

		<div class="jws-mb__dep-create" hidden>
			<label><?php esc_html_e( 'From', 'jws_streamvid' ); ?> <input type="number" min="1" class="jws-mb__dep-from" value="<?php echo (int) ( $highest + 1 ); ?>"></label>
			<label><?php esc_html_e( 'To', 'jws_streamvid' ); ?> <input type="number" min="1" class="jws-mb__dep-to" value="<?php echo (int) ( $highest + 10 ); ?>"></label>
			<label class="jws-mb__dep-pattern-wrap"><?php esc_html_e( 'Title', 'jws_streamvid' ); ?> <input type="text" class="jws-mb__dep-pattern" value="<?php echo esc_attr__( 'Episode %d', 'jws_streamvid' ); ?>"></label>
			<label><input type="checkbox" class="jws-mb__dep-publish"> <?php esc_html_e( 'Publish', 'jws_streamvid' ); ?></label>
			<button type="button" class="button button-primary jws-mb__dep-create-go"><?php esc_html_e( 'Create', 'jws_streamvid' ); ?></button>
			<span class="jws-mb__dep-msg"></span>
			<p class="jws-mb__desc"><?php esc_html_e( 'Created right away and linked to this drama. Numbers that already exist are skipped; %d in the title becomes the number.', 'jws_streamvid' ); ?></p>
		</div>

		<ol class="jws-mb__episodes jws-mb__dep-list">
			<?php
			foreach ( $ids as $id ) {
				echo jws_metabox_drama_episode_row( $id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
			}
			?>
		</ol>
		<div class="jws-mb__seasons-empty jws-mb__dep-empty"<?php echo $ids ? ' hidden' : ''; ?>>
			<span class="dashicons dashicons-playlist-video"></span>
			<p><?php esc_html_e( 'No episodes yet. Create them in bulk above or attach existing ones below.', 'jws_streamvid' ); ?></p>
		</div>
		<p class="jws-mb__desc jws-mb__dep-hint" hidden><?php esc_html_e( 'Order changed — episodes are renumbered 1, 2, 3… when you update.', 'jws_streamvid' ); ?></p>
	</div>
	<?php
}

/** Bulk create from the Episodes tab (same rules as the Manage Episodes page). */
function jws_metabox_ajax_drama_create() {
	check_ajax_referer( Jws_Metabox::AJAX_NONCE, 'nonce' );
	list( $drama_type, $episode_type ) = jws_metabox_drama_types();

	$drama_id = isset( $_POST['drama'] ) ? absint( $_POST['drama'] ) : 0;
	$pt       = get_post_type_object( $episode_type );
	if ( ! $drama_id || get_post_type( $drama_id ) !== $drama_type || ! $pt || ! current_user_can( 'edit_post', $drama_id ) || ! current_user_can( $pt->cap->create_posts ) ) {
		wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'jws_streamvid' ) ), 403 );
	}

	$from    = max( 1, isset( $_POST['from'] ) ? absint( $_POST['from'] ) : 1 );
	$to      = max( 1, isset( $_POST['to'] ) ? absint( $_POST['to'] ) : 1 );
	$pattern = isset( $_POST['pattern'] ) ? sanitize_text_field( wp_unslash( $_POST['pattern'] ) ) : '';
	$status  = ! empty( $_POST['publish'] ) && current_user_can( $pt->cap->publish_posts ) ? 'publish' : 'draft';

	if ( $to < $from ) {
		wp_send_json_error( array( 'message' => __( '"To" must not be smaller than "From".', 'jws_streamvid' ) ) );
	}
	if ( ( $to - $from ) >= 200 ) {
		wp_send_json_error( array( 'message' => __( 'That is more than 200 episodes at once. Do it in smaller batches.', 'jws_streamvid' ) ) );
	}
	if ( '' === $pattern ) {
		$pattern = __( 'Episode %d', 'jws_streamvid' );
	}
	if ( false === strpos( $pattern, '%d' ) ) {
		$pattern .= ' %d';
	}

	$taken = array();
	foreach ( jws_metabox_drama_episode_ids( $drama_id ) as $id ) {
		$taken[ jws_metabox_drama_episode_number( $id ) ] = true;
	}

	$rows    = array();
	$skipped = 0;
	for ( $n = $from; $n <= $to; $n++ ) {
		if ( isset( $taken[ $n ] ) ) {
			$skipped++;
			continue;
		}
		$id = wp_insert_post(
			array(
				'post_type'   => $episode_type,
				'post_title'  => str_replace( '%d', (string) $n, $pattern ),
				'post_status' => $status,
				'menu_order'  => $n,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			continue;
		}
		update_post_meta( $id, 'drama_id', $drama_id );
		update_post_meta( $id, '_drama_id', 'field_drama_ep_drama_id' );
		update_post_meta( $id, 'drama_ep_number', $n );
		update_post_meta( $id, '_drama_ep_number', 'field_drama_ep_number' );
		$rows[] = array( 'number' => $n, 'html' => jws_metabox_drama_episode_row( $id ) );
	}

	wp_send_json_success(
		array(
			'rows'    => $rows,
			/* translators: 1: created, 2: skipped */
			'message' => sprintf( __( 'Created %1$d, skipped %2$d existing.', 'jws_streamvid' ), count( $rows ), $skipped ),
		)
	);
}

function jws_metabox_drama_set_number( $episode_id, $number ) {
	update_post_meta( $episode_id, 'drama_ep_number', (int) $number );
	update_post_meta( $episode_id, '_drama_ep_number', 'field_drama_ep_number' );
	global $wpdb;
	// Direct write, like Jws_Drama_Post_Types::sync_episode_order(): wp_update_post() would re-enter save_post.
	$wpdb->update( $wpdb->posts, array( 'menu_order' => (int) $number ), array( 'ID' => (int) $episode_id ), array( '%d' ), array( '%d' ) );
	clean_post_cache( $episode_id );
}

/** Apply the Episodes tab: unlink, free flags, renumbering, attach. */
function jws_metabox_drama_save_episodes( $drama_id, $box, $data ) {
	list( , $episode_type ) = jws_metabox_drama_types();

	$owned = function ( $id ) use ( $drama_id, $episode_type ) {
		return $id && get_post_type( $id ) === $episode_type
			&& (int) get_post_meta( $id, 'drama_id', true ) === (int) $drama_id
			&& current_user_can( 'edit_post', $id );
	};

	$removed = array_filter( array_map( 'absint', isset( $data['episodes_remove'] ) ? (array) $data['episodes_remove'] : array() ) );
	foreach ( $removed as $id ) {
		if ( $owned( $id ) ) {
			delete_post_meta( $id, 'drama_id' );
		}
	}

	foreach ( isset( $data['episodes_free'] ) ? (array) $data['episodes_free'] : array() as $id => $flag ) {
		$id   = absint( $id );
		$flag = empty( $flag ) ? 0 : 1;
		if ( $owned( $id ) && (int) get_post_meta( $id, 'drama_ep_free', true ) !== $flag ) {
			update_post_meta( $id, 'drama_ep_free', $flag );
			update_post_meta( $id, '_drama_ep_free', 'field_drama_ep_free' );
		}
	}

	if ( ! empty( $data['episodes_reordered'] ) ) {
		$n = 0;
		foreach ( array_map( 'absint', isset( $data['episodes_order'] ) ? (array) $data['episodes_order'] : array() ) as $id ) {
			if ( ! $owned( $id ) ) {
				continue;
			}
			$n++;
			if ( jws_metabox_drama_episode_number( $id ) !== $n ) {
				jws_metabox_drama_set_number( $id, $n );
			}
		}
	}

	$attach = array_filter( array_map( 'absint', isset( $data['attach_episodes'] ) ? (array) $data['attach_episodes'] : array() ) );
	if ( $attach ) {
		$highest = 0;
		foreach ( jws_metabox_drama_episode_ids( $drama_id ) as $id ) {
			$highest = max( $highest, jws_metabox_drama_episode_number( $id ) );
		}
		foreach ( array_unique( $attach ) as $id ) {
			if ( get_post_type( $id ) !== $episode_type || ! current_user_can( 'edit_post', $id ) || (int) get_post_meta( $id, 'drama_id', true ) === (int) $drama_id ) {
				continue;
			}
			update_post_meta( $id, 'drama_id', (int) $drama_id );
			update_post_meta( $id, '_drama_id', 'field_drama_ep_drama_id' );
			jws_metabox_drama_set_number( $id, ++$highest );
		}
	}
}

/** An episode saved with a drama but no number goes to the end of that drama. */
function jws_metabox_drama_ep_autonumber( $episode_id ) {
	$drama_id = (int) get_post_meta( $episode_id, 'drama_id', true );
	if ( ! $drama_id || (int) get_post_meta( $episode_id, 'drama_ep_number', true ) > 0 ) {
		return;
	}
	$highest = 0;
	foreach ( jws_metabox_drama_episode_ids( $drama_id ) as $id ) {
		if ( $id !== (int) $episode_id ) {
			$highest = max( $highest, jws_metabox_drama_episode_number( $id ) );
		}
	}
	jws_metabox_drama_set_number( $episode_id, $highest + 1 );
}
