<?php
/**
 * Profile page V3: the account's playlists, one tab per playlist type.
 *
 * Playlists belong to the account, not to a viewer profile. Creating and
 * editing use the existing playlist modal and AJAX (#create-playlist, and
 * the single playlist screen at ?playlist=ID).
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jws_pl_class    = jws_streamvid()->get()->playlist;
$jws_pl_author   = (int) get_queried_object_id();
$jws_pl_types    = $jws_pl_class->playlist_type();
$jws_pl_posttype = $jws_pl_class->playlist_post_type();
$jws_pl_status   = $jws_pl_class->get_statuses();
$jws_pl_page     = Jws_Streamvid_Profile::get_url( 'playlist' );
$jws_pl_lists    = array();

foreach ( array_keys( $jws_pl_types ) as $jws_pl_tax ) {
	if ( ! taxonomy_exists( $jws_pl_tax ) ) {
		continue;
	}
	$jws_pl_terms = get_terms(
		array(
			'taxonomy'   => $jws_pl_tax,
			'hide_empty' => false,
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => 'user',
					'value' => $jws_pl_author,
				),
			),
		)
	);
	$jws_pl_lists[ $jws_pl_tax ] = is_wp_error( $jws_pl_terms ) ? array() : $jws_pl_terms;
}

$jws_pl_tab = isset( $_GET['ptype'] ) ? sanitize_key( wp_unslash( $_GET['ptype'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( ! isset( $jws_pl_lists[ $jws_pl_tab ] ) ) {
	$jws_pl_tab = (string) key( $jws_pl_lists );
}
$jws_pl_terms = isset( $jws_pl_lists[ $jws_pl_tab ] ) ? $jws_pl_lists[ $jws_pl_tab ] : array();
?>
<div class="jws-pl">

	<div class="jws-lib__head">
		<p class="jws-lib__sub"><?php esc_html_e( 'Group titles into lists you can keep private or share.', 'jws_streamvid' ); ?></p>
		<a href="#" class="button-default" data-modal-jws="#create-playlist">
			<i class="jws-icon-plus" aria-hidden="true"></i>
			<?php esc_html_e( 'Create Playlist', 'jws_streamvid' ); ?>
		</a>
	</div>

	<nav class="jws-pv3__tabs" aria-label="<?php esc_attr_e( 'Playlist type', 'jws_streamvid' ); ?>">
		<?php foreach ( $jws_pl_lists as $jws_pl_tax => $jws_pl_items ) : ?>
			<a class="jws-pv3__tab<?php echo $jws_pl_tab === $jws_pl_tax ? ' is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'ptype', $jws_pl_tax, $jws_pl_page ) ); ?>"<?php echo $jws_pl_tab === $jws_pl_tax ? ' aria-current="page"' : ''; ?>>
				<?php
				/* translators: 1: playlist type, 2: number of playlists */
				echo esc_html( sprintf( __( '%1$s (%2$d)', 'jws_streamvid' ), $jws_pl_types[ $jws_pl_tax ], count( $jws_pl_items ) ) );
				?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php if ( $jws_pl_terms ) : ?>
		<ul class="jws-pl__grid">
			<?php foreach ( $jws_pl_terms as $jws_pl_term ) : ?>
				<?php
				$jws_pl_status_key = get_term_meta( $jws_pl_term->term_id, 'status', true );
				$jws_pl_status_key = isset( $jws_pl_status[ $jws_pl_status_key ] ) ? $jws_pl_status_key : 'public';
				$jws_pl_image      = (int) get_term_meta( $jws_pl_term->term_id, 'playlist_image', true );
				$jws_pl_play       = $jws_pl_class->playlist_url_all( $jws_pl_term->term_id, $jws_pl_tab, $jws_pl_posttype[ $jws_pl_tab ] );
				$jws_pl_edit       = add_query_arg(
					array(
						'playlist'      => $jws_pl_term->term_id,
						'playlist_type' => $jws_pl_tab,
					),
					$jws_pl_page
				);

				// No cover chosen: use the newest title in the playlist.
				if ( ! $jws_pl_image && $jws_pl_term->count ) {
					$jws_pl_first = get_posts(
						array(
							'post_type'      => $jws_pl_posttype[ $jws_pl_tab ],
							'posts_per_page' => 1,
							'fields'         => 'ids',
							'no_found_rows'  => true,
							'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
								array(
									'taxonomy' => $jws_pl_tab,
									'terms'    => $jws_pl_term->term_id,
								),
							),
						)
					);
					$jws_pl_image = $jws_pl_first ? (int) get_post_thumbnail_id( $jws_pl_first[0] ) : 0;
				}
				$jws_pl_src = $jws_pl_image ? wp_get_attachment_image_url( $jws_pl_image, 'medium_large' ) : '';
				?>
				<li class="jws-pl__item">
					<a class="jws-lib__card" href="<?php echo esc_url( $jws_pl_play ); ?>">
						<span class="jws-lib__art jws-pl__art">
							<?php if ( $jws_pl_src ) : ?>
								<img src="<?php echo esc_url( $jws_pl_src ); ?>" alt="" loading="lazy" decoding="async">
							<?php endif; ?>
							<span class="jws-pl__count">
								<i class="jws-icon-queue" aria-hidden="true"></i>
								<?php echo esc_html( number_format_i18n( $jws_pl_term->count ) ); ?>
							</span>
							<span class="jws-pl__play"><i class="jws-icon-play-circle" aria-hidden="true"></i><?php esc_html_e( 'Play All', 'jws_streamvid' ); ?></span>
						</span>
						<h6 class="jws-lib__title"><?php echo esc_html( $jws_pl_term->name ); ?></h6>
					</a>
					<span class="jws-pl__meta">
						<span class="jws-pl__privacy">
							<i class="<?php echo 'private' === $jws_pl_status_key ? 'jws-icon-lock-key-fill' : 'jws-icon-globe-hemisphere-west-fill'; ?>" aria-hidden="true"></i>
							<?php echo esc_html( $jws_pl_status[ $jws_pl_status_key ] ); ?>
						</span>
						<a class="jws-pl__edit" href="<?php echo esc_url( $jws_pl_edit ); ?>">
							<i class="jws-icon-pencil-line" aria-hidden="true"></i>
							<?php esc_html_e( 'Edit', 'jws_streamvid' ); ?>
							<span class="screen-reader-text"><?php echo esc_html( $jws_pl_term->name ); ?></span>
						</a>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<div class="jws-lib__empty">
			<span class="jws-lib__empty-icon" aria-hidden="true"><i class="jws-icon-queue"></i></span>
			<h5><?php esc_html_e( 'No playlists yet', 'jws_streamvid' ); ?></h5>
			<p><?php esc_html_e( 'Create a playlist, then add titles to it from any title page.', 'jws_streamvid' ); ?></p>
			<a href="#" class="button-default" data-modal-jws="#create-playlist"><?php esc_html_e( 'Create Playlist', 'jws_streamvid' ); ?></a>
		</div>
	<?php endif; ?>
</div>
