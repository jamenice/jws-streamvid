<?php
/**
 * Profile page V3: videos and live streams the account uploaded.
 *
 * The owner sees every status with Upload Video / Go live, three figures, a
 * filter and sort, and an Edit button on each card that opens the existing
 * #edit-videos modal (deleting is in that modal). Visitors see the published
 * videos only.
 *
 * Live streams are videos with live_status meta (yes / 1 while live, ended
 * after); views come from the views meta.
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jws_vid_author = absint( get_queried_object_id() );
$jws_vid_owner  = jws_streamvid()->get()->profile->_profile_is_owner();
$jws_vid_filter = isset( $_GET['vtype'] ) ? sanitize_key( wp_unslash( $_GET['vtype'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$jws_vid_filter = in_array( $jws_vid_filter, array( 'all', 'uploads', 'live' ), true ) ? $jws_vid_filter : 'all';
$jws_vid_sort   = isset( $_GET['sortby'] ) ? sanitize_key( wp_unslash( $_GET['sortby'] ) ) : 'date'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$jws_vid_sort   = in_array( $jws_vid_sort, array( 'date', 'views', 'likes', 'title' ), true ) ? $jws_vid_sort : 'date';
$jws_vid_page   = Jws_Streamvid_Profile::get_url( 'video' );

$jws_vid_args = array(
	'post_type'      => 'videos',
	'author'         => $jws_vid_author,
	'post_status'    => $jws_vid_owner ? array( 'publish', 'pending', 'draft', 'private', 'future' ) : 'publish',
	'posts_per_page' => 100,
	'no_found_rows'  => true,
);

if ( 'live' === $jws_vid_filter ) {
	$jws_vid_args['meta_query'] = array( array( 'key' => 'live_status', 'compare' => 'EXISTS' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
} elseif ( 'uploads' === $jws_vid_filter ) {
	$jws_vid_args['meta_query'] = array( array( 'key' => 'live_status', 'compare' => 'NOT EXISTS' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
}

if ( 'title' === $jws_vid_sort ) {
	$jws_vid_args['orderby'] = 'title';
	$jws_vid_args['order']   = 'ASC';
} elseif ( in_array( $jws_vid_sort, array( 'views', 'likes' ), true ) ) {
	$jws_vid_args['orderby']  = 'meta_value_num';
	$jws_vid_args['meta_key'] = $jws_vid_sort; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
}

$jws_vid_query = new WP_Query( $jws_vid_args );

// Figures for the owner: every video by this author, whatever the filter.
$jws_vid_stats = array();
if ( $jws_vid_owner ) {
	$jws_vid_all = get_posts(
		array(
			'post_type'      => 'videos',
			'author'         => $jws_vid_author,
			'post_status'    => array( 'publish', 'pending', 'draft', 'private', 'future' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	$jws_vid_live  = 0;
	$jws_vid_views = 0;
	foreach ( $jws_vid_all as $jws_vid_id ) {
		$jws_vid_live  += '' !== (string) get_post_meta( $jws_vid_id, 'live_status', true ) ? 1 : 0;
		$jws_vid_views += (int) get_post_meta( $jws_vid_id, 'views', true );
	}
	$jws_vid_stats = array(
		array( __( 'Videos', 'jws_streamvid' ), count( $jws_vid_all ) - $jws_vid_live ),
		array( __( 'Live streams', 'jws_streamvid' ), $jws_vid_live ),
		array( __( 'Total views', 'jws_streamvid' ), $jws_vid_views ),
	);
}

$jws_vid_status = array(
	'publish' => array( 'owned', __( 'Published', 'jws_streamvid' ) ),
	'pending' => array( 'pending', __( 'Pending review', 'jws_streamvid' ) ),
	'draft'   => array( 'pending', __( 'Draft', 'jws_streamvid' ) ),
	'private' => array( 'pending', __( 'Private', 'jws_streamvid' ) ),
	'future'  => array( 'pending', __( 'Scheduled', 'jws_streamvid' ) ),
);
?>
<div class="jws-vid">

	<?php if ( $jws_vid_owner ) : ?>
		<div class="jws-lib__head">
			<p class="jws-lib__sub"><?php esc_html_e( 'Videos and live streams you uploaded to this account.', 'jws_streamvid' ); ?></p>
			<div class="jws-lib__actions">
				<?php if ( jws_theme_get_option( 'video_live' ) ) : ?>
					<?php $jws_vid_live_url = jws_streamvid()->get()->live_videos->get_create_page_url(); ?>
					<a class="button-custom" href="<?php echo esc_url( $jws_vid_live_url ? $jws_vid_live_url : '#' ); ?>"<?php echo $jws_vid_live_url ? '' : ' data-modal-jws="#upload-videos-live"'; ?>>
						<i class="jws-icon-video-camera" aria-hidden="true"></i>
						<?php esc_html_e( 'Go live', 'jws_streamvid' ); ?>
					</a>
				<?php endif; ?>
				<?php if ( jws_theme_get_option( 'video_upload' ) ) : ?>
					<a class="button-default" <?php echo jws_streamvid()->get()->videos->upload_trigger_attrs(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in upload_trigger_attrs(). ?>>
						<i class="jws-icon-upload" aria-hidden="true"></i>
						<?php esc_html_e( 'Upload Video', 'jws_streamvid' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>

		<div class="jws-vid__stats">
			<?php foreach ( $jws_vid_stats as $jws_vid_stat ) : ?>
				<div class="jws-coin__stat">
					<span class="jws-coin__label"><?php echo esc_html( $jws_vid_stat[0] ); ?></span>
					<span class="jws-coin__stat-value h5"><?php echo esc_html( number_format_i18n( $jws_vid_stat[1] ) ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="jws-vid__tools">
		<nav class="jws-pv3__tabs" aria-label="<?php esc_attr_e( 'Filter', 'jws_streamvid' ); ?>">
			<?php
			$jws_vid_chips = array(
				'all'     => __( 'All', 'jws_streamvid' ),
				'uploads' => __( 'Uploads', 'jws_streamvid' ),
				'live'    => __( 'Live streams', 'jws_streamvid' ),
			);
			foreach ( $jws_vid_chips as $jws_vid_key => $jws_vid_label ) :
				$jws_vid_href = add_query_arg( array( 'vtype' => 'all' === $jws_vid_key ? false : $jws_vid_key, 'sortby' => 'date' === $jws_vid_sort ? false : $jws_vid_sort ), $jws_vid_page );
				?>
				<a class="jws-pv3__tab<?php echo $jws_vid_filter === $jws_vid_key ? ' is-active' : ''; ?>" href="<?php echo esc_url( $jws_vid_href ); ?>"<?php echo $jws_vid_filter === $jws_vid_key ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $jws_vid_label ); ?></a>
			<?php endforeach; ?>
		</nav>

		<form method="get" action="<?php echo esc_url( $jws_vid_page ); ?>" class="jws-vid__sort">
			<?php if ( 'all' !== $jws_vid_filter ) : ?>
				<input type="hidden" name="vtype" value="<?php echo esc_attr( $jws_vid_filter ); ?>">
			<?php endif; ?>
			<label for="jws-vid-sort"><?php esc_html_e( 'Sort by', 'jws_streamvid' ); ?></label>
			<select id="jws-vid-sort" name="sortby" class="jws-pf-input jws-vid__select" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()">
				<option value="date" <?php selected( $jws_vid_sort, 'date' ); ?>><?php esc_html_e( 'Newest', 'jws_streamvid' ); ?></option>
				<option value="views" <?php selected( $jws_vid_sort, 'views' ); ?>><?php esc_html_e( 'Most viewed', 'jws_streamvid' ); ?></option>
				<option value="likes" <?php selected( $jws_vid_sort, 'likes' ); ?>><?php esc_html_e( 'Most liked', 'jws_streamvid' ); ?></option>
				<option value="title" <?php selected( $jws_vid_sort, 'title' ); ?>><?php esc_html_e( 'Title', 'jws_streamvid' ); ?></option>
			</select>
			<noscript><button type="submit" class="button-custom"><?php esc_html_e( 'Sort', 'jws_streamvid' ); ?></button></noscript>
		</form>
	</div>

	<?php if ( $jws_vid_query->have_posts() ) : ?>
		<ul class="jws-lib__grid jws-vid__grid">
			<?php
			while ( $jws_vid_query->have_posts() ) :
				$jws_vid_query->the_post();
				$jws_vid_id     = get_the_ID();
				$jws_vid_livest = (string) get_post_meta( $jws_vid_id, 'live_status', true );
				$jws_vid_length = (string) get_post_meta( $jws_vid_id, 'videos_time', true );
				$jws_vid_views  = (int) get_post_meta( $jws_vid_id, 'views', true );
				$jws_vid_chip   = isset( $jws_vid_status[ get_post_status() ] ) ? $jws_vid_status[ get_post_status() ] : $jws_vid_status['publish'];
				$jws_vid_thumb  = get_the_post_thumbnail_url( $jws_vid_id, 'medium_large' );
				?>
				<li class="jws-lib__item">
					<a class="jws-lib__card" href="<?php the_permalink(); ?>">
						<span class="jws-lib__art">
							<?php if ( $jws_vid_thumb ) : ?>
								<img src="<?php echo esc_url( $jws_vid_thumb ); ?>" alt="" loading="lazy" decoding="async">
							<?php endif; ?>
							<?php if ( in_array( $jws_vid_livest, array( 'yes', '1' ), true ) ) : ?>
								<span class="jws-vid__live fs-small"><?php esc_html_e( 'Live', 'jws_streamvid' ); ?></span>
							<?php endif; ?>
							<?php if ( $jws_vid_length ) : ?>
								<span class="jws-vid__length fs-small"><i class="jws-icon-play-circle" aria-hidden="true"></i><?php echo esc_html( $jws_vid_length ); ?></span>
							<?php endif; ?>
						</span>
						<h6 class="jws-lib__title fs-small"><?php the_title(); ?></h6>
					</a>
					<span class="jws-vid__meta">
						<span class="jws-lib__meta fs-small">
							<?php
							/* translators: 1: number of views, 2: time since, such as "3 days" */
							echo esc_html( sprintf( _n( '%1$s view · %2$s ago', '%1$s views · %2$s ago', $jws_vid_views, 'jws_streamvid' ), number_format_i18n( $jws_vid_views ), human_time_diff( get_post_time( 'U', true ), time() ) ) );
							?>
						</span>
						<?php if ( $jws_vid_owner ) : ?>
							<span class="jws-buy__chip jws-buy__chip--<?php echo esc_attr( $jws_vid_chip[0] ); ?>"><?php echo esc_html( $jws_vid_chip[1] ); ?></span>
							<a href="#" class="jws-vid__edit edit-video" data-modal-jws="#edit-videos" data-id="<?php echo (int) $jws_vid_id; ?>">
								<i class="jws-icon-pencil-line" aria-hidden="true"></i>
								<?php esc_html_e( 'Edit', 'jws_streamvid' ); ?>
								<span class="screen-reader-text"><?php the_title(); ?></span>
							</a>
						<?php endif; ?>
					</span>
				</li>
			<?php endwhile; ?>
		</ul>
		<?php wp_reset_postdata(); ?>
	<?php else : ?>
		<div class="jws-lib__empty">
			<span class="jws-lib__empty-icon" aria-hidden="true"><i class="jws-icon-monitor-play"></i></span>
			<h5><?php esc_html_e( 'No videos yet', 'jws_streamvid' ); ?></h5>
			<?php if ( $jws_vid_owner && jws_theme_get_option( 'video_upload' ) ) : ?>
				<p><?php esc_html_e( 'Videos you upload and live streams you run show up here.', 'jws_streamvid' ); ?></p>
				<a class="button-default" <?php echo jws_streamvid()->get()->videos->upload_trigger_attrs(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in upload_trigger_attrs(). ?>><?php esc_html_e( 'Upload Video', 'jws_streamvid' ); ?></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
