<?php
/**
 * Profile page V3: History, Watchlist or Favorites of the profile in use.
 *
 * One form wraps the grid. Select (a checkbox styled as a button) shows a
 * tick on every card and the Remove bar; the count of ticked cards is a CSS
 * counter. Each card also has its own remove button (shown on hover and
 * focus on devices with a pointer). Handled by Jws_Profile_Library.
 *
 * @package Jws_Streamvid
 *
 * @var array $args { @type string $tab history, watchlist or favorites. }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jws_lib_tab     = isset( $args['tab'] ) && in_array( $args['tab'], Jws_Profile_Library::TABS, true ) ? $args['tab'] : 'history';
$jws_lib_user    = get_current_user_id();
$jws_lib_type    = Jws_Profile_Library::current_type();
$jws_lib_items   = Jws_Profile_Library::items( $jws_lib_tab, $jws_lib_user, $jws_lib_type );
$jws_lib_total   = count( $jws_lib_items );
$jws_lib_shown   = isset( $_GET['shown'] ) ? max( Jws_Profile_Library::PER_PAGE, absint( $_GET['shown'] ) ) : Jws_Profile_Library::PER_PAGE; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$jws_lib_items   = array_slice( $jws_lib_items, 0, $jws_lib_shown );
$jws_lib_profile = Jws_Profiles::current_profile( $jws_lib_user );
$jws_lib_name    = $jws_lib_profile ? $jws_lib_profile->name : wp_get_current_user()->display_name;
$jws_lib_notice  = Jws_Profile_Library::notice();
$jws_lib_page    = jws_streamvid()->get()->profile->get_url( $jws_lib_tab );

$jws_lib_groups = 'history' === $jws_lib_tab
	? Jws_Profile_Library::by_day( $jws_lib_items )
	: ( $jws_lib_items ? array( array( 'title' => '', 'items' => $jws_lib_items ) ) : array() );

$jws_lib_text = array(
	/* translators: %s: profile name */
	'history'   => array( __( 'What %s watched on this profile. Pick up where you left off.', 'jws_streamvid' ), __( 'No watch history yet', 'jws_streamvid' ), __( 'Titles you watch on this profile show up here, so you can continue from the same minute.', 'jws_streamvid' ) ),
	/* translators: %s: profile name */
	'watchlist' => array( __( 'Titles %s saved to watch later.', 'jws_streamvid' ), __( 'Your Watchlist is empty', 'jws_streamvid' ), __( 'Use the bookmark on any title to save it here for later.', 'jws_streamvid' ) ),
	/* translators: %s: profile name */
	'favorites' => array( __( 'Titles %s liked.', 'jws_streamvid' ), __( 'No favorites yet', 'jws_streamvid' ), __( 'Use the heart on any title to keep it here.', 'jws_streamvid' ) ),
);
$jws_lib_copy = $jws_lib_text[ $jws_lib_tab ];
?>
<div class="jws-lib jws-lib--<?php echo esc_attr( $jws_lib_tab ); ?>">

	<?php if ( $jws_lib_notice ) : ?>
		<p class="jws-pf-msg" role="status"><?php echo esc_html( $jws_lib_notice ); ?></p>
	<?php endif; ?>

	<form method="post" class="jws-lib__form">
		<?php wp_nonce_field( Jws_Profile_Library::NONCE, '_jws_library_nonce' ); ?>
		<input type="hidden" name="library_tab" value="<?php echo esc_attr( $jws_lib_tab ); ?>">
		<input type="checkbox" id="jws-lib-select" class="jws-lib__toggle" <?php disabled( ! $jws_lib_items ); ?>>

		<div class="jws-lib__head">
			<p class="jws-lib__sub"><?php echo esc_html( sprintf( $jws_lib_copy[0], $jws_lib_name ) ); ?></p>
			<?php if ( $jws_lib_items ) : ?>
				<div class="jws-lib__actions">
					<label for="jws-lib-select" class="button-custom jws-btn-sm jws-lib__select">
						<i class="jws-icon-check-square" aria-hidden="true"></i>
						<?php esc_html_e( 'Select', 'jws_streamvid' ); ?>
					</label>
					<?php if ( 'history' === $jws_lib_tab ) : ?>
						<details class="jws-lib__clear">
							<summary class="button-custom jws-btn-sm"><?php esc_html_e( 'Clear all history', 'jws_streamvid' ); ?></summary>
							<div class="jws-lib__clear-box">
								<p>
									<?php
									/* translators: %s: profile name */
									echo esc_html( sprintf( __( 'Clear everything %s watched on this profile? This cannot be undone.', 'jws_streamvid' ), $jws_lib_name ) );
									?>
								</p>
								<button type="submit" name="clear_all" value="1" class="button-default jws-btn-danger"><?php esc_html_e( 'Clear history', 'jws_streamvid' ); ?></button>
							</div>
						</details>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<nav class="jws-pv3__tabs" aria-label="<?php esc_attr_e( 'Filter by type', 'jws_streamvid' ); ?>">
			<a class="jws-pv3__tab<?php echo 'all' === $jws_lib_type ? ' is-active' : ''; ?>" href="<?php echo esc_url( $jws_lib_page ); ?>"<?php echo 'all' === $jws_lib_type ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'All', 'jws_streamvid' ); ?></a>
			<?php foreach ( jws_profile_tab_post_types() as $jws_lib_t ) : ?>
				<a class="jws-pv3__tab<?php echo $jws_lib_type === $jws_lib_t ? ' is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'type', $jws_lib_t, $jws_lib_page ) ); ?>"<?php echo $jws_lib_type === $jws_lib_t ? ' aria-current="page"' : ''; ?>><?php echo esc_html( jws_profile_tab_label( $jws_lib_t ) ); ?></a>
			<?php endforeach; ?>
		</nav>

		<?php if ( $jws_lib_groups ) : ?>
			<div class="jws-lib__groups">
				<?php foreach ( $jws_lib_groups as $jws_lib_group ) : ?>
					<section class="jws-lib__group">
						<?php if ( $jws_lib_group['title'] ) : ?>
							<h6 class="jws-lib__group-title"><?php echo esc_html( $jws_lib_group['title'] ); ?></h6>
						<?php endif; ?>
						<ul class="jws-lib__grid">
							<?php foreach ( $jws_lib_group['items'] as $jws_lib_item ) : ?>
								<?php $jws_lib_card = Jws_Profile_Library::card( $jws_lib_item ); ?>
								<li class="jws-lib__item">
									<a class="jws-lib__card" href="<?php echo esc_url( $jws_lib_card['url'] ); ?>">
										<span class="jws-lib__art">
											<?php if ( $jws_lib_card['image'] ) : ?>
												<img src="<?php echo esc_url( $jws_lib_card['image'] ); ?>" alt="" loading="lazy" decoding="async">
											<?php endif; ?>
											<span class="jws-lib__type"><?php echo esc_html( $jws_lib_card['type'] ); ?></span>
											<?php if ( null !== $jws_lib_card['progress'] ) : ?>
												<span class="time-data"><span style="width: <?php echo (int) $jws_lib_card['progress']; ?>%"></span></span>
											<?php endif; ?>
										</span>
										<h6 class="jws-lib__title fs-small"><?php echo esc_html( $jws_lib_card['title'] ); ?></h6>
										<?php if ( $jws_lib_card['meta'] ) : ?>
											<span class="jws-lib__meta fs-small"><?php echo esc_html( $jws_lib_card['meta'] ); ?></span>
										<?php endif; ?>
									</a>
									<label class="jws-lib__tick">
										<input type="checkbox" name="ids[]" value="<?php echo (int) $jws_lib_item['id']; ?>">
										<span class="screen-reader-text">
											<?php
											/* translators: %s: title */
											echo esc_html( sprintf( __( 'Select %s', 'jws_streamvid' ), $jws_lib_card['title'] ) );
											?>
										</span>
									</label>
									<button type="submit" name="remove_one" value="<?php echo (int) $jws_lib_item['id']; ?>" class="jws-lib__x">
										<i class="jws-icon-x" aria-hidden="true"></i>
										<span class="screen-reader-text">
											<?php
											/* translators: %s: title */
											echo esc_html( sprintf( __( 'Remove %s', 'jws_streamvid' ), $jws_lib_card['title'] ) );
											?>
										</span>
									</button>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endforeach; ?>
			</div>

			<?php if ( $jws_lib_total > $jws_lib_shown ) : ?>
				<div class="jws-lib__more">
					<a class="button-custom" href="<?php echo esc_url( add_query_arg( array( 'type' => 'all' === $jws_lib_type ? false : $jws_lib_type, 'shown' => $jws_lib_shown + Jws_Profile_Library::PER_PAGE ), $jws_lib_page ) ); ?>">
						<?php esc_html_e( 'Show more', 'jws_streamvid' ); ?>
					</a>
				</div>
			<?php endif; ?>

			<div class="jws-lib__bar" role="toolbar" aria-label="<?php esc_attr_e( 'Selection', 'jws_streamvid' ); ?>">
				<span class="jws-lib__count" aria-hidden="true">
					<?php
					/* translators: %s: number of ticked titles (filled in by CSS) */
					printf( esc_html__( '%s selected', 'jws_streamvid' ), '<span class="jws-lib__num"></span>' );
					?>
				</span>
				<label for="jws-lib-select" class="button-custom"><?php esc_html_e( 'Cancel', 'jws_streamvid' ); ?></label>
				<button type="submit" name="remove_selected" value="1" class="button-default jws-btn-danger">
					<i class="jws-icon-trash" aria-hidden="true"></i>
					<?php esc_html_e( 'Remove selected', 'jws_streamvid' ); ?>
				</button>
			</div>
		<?php else : ?>
			<div class="jws-lib__empty">
				<span class="jws-lib__empty-icon" aria-hidden="true">
					<i class="jws-icon-play-circle" aria-hidden="true"></i>
				</span>
				<h5><?php echo esc_html( $jws_lib_copy[1] ); ?></h5>
				<p><?php echo esc_html( $jws_lib_copy[2] ); ?></p>
				<a class="button-default" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Browse titles', 'jws_streamvid' ); ?></a>
			</div>
		<?php endif; ?>
	</form>
</div>
