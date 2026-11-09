<?php
/**
 * Profile page tab: the account's viewer profiles, how many the plan
 * allows, and links to add or edit them on the Who's watching screens.
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! jws_streamvid()->get()->profile->_profile_is_owner() ) {
	return;
}

global $wpdb;

$jws_pt_user     = get_current_user_id();
$jws_pt_profiles = Jws_Profiles::get_all( $jws_pt_user );
$jws_pt_limit    = Jws_Profiles::get_limit( $jws_pt_user );
$jws_pt_active   = Jws_Profiles::active_ids( $jws_pt_user );
$jws_pt_count    = count( $jws_pt_profiles );
$jws_pt_used     = min( $jws_pt_count, $jws_pt_limit );
$jws_pt_table    = Jws_Streamvid_Tables::table_history();

// Last watched per profile, in one query.
$jws_pt_watched = array();
foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT profile_id, MAX(watched_at) AS last FROM {$jws_pt_table} WHERE user_id = %d GROUP BY profile_id", $jws_pt_user ) ) as $jws_pt_row ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$jws_pt_watched[ (int) $jws_pt_row->profile_id ] = $jws_pt_row->last;
}

$jws_pt_upgrade = function_exists( 'jws_theme_get_option' ) ? (int) jws_theme_get_option( 'select-update-level' ) : 0;
?>
<div class="jws-pf-tab">

	<div class="jws-pf-tab__head">
		<div class="jws-pf-usage">
			<p class="jws-pf-usage__text">
				<?php
				printf(
					/* translators: 1: profiles in use, 2: profiles the plan allows */
					esc_html__( '%1$d of %2$d profiles used on your plan', 'jws_streamvid' ),
					(int) $jws_pt_used,
					(int) $jws_pt_limit
				);
				?>
			</p>
			<span class="jws-pf-meter" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: used, 2: allowed */ __( '%1$d of %2$d profiles used', 'jws_streamvid' ), $jws_pt_used, $jws_pt_limit ) ); ?>">
				<span class="jws-pf-meter__fill" style="width: <?php echo esc_attr( round( 100 * $jws_pt_used / max( 1, $jws_pt_limit ) ) ); ?>%"></span>
			</span>
		</div>

		<?php if ( Jws_Profiles::can_create( $jws_pt_user ) ) : ?>
			<a class="button-default" href="<?php echo esc_url( Jws_Profiles_Front::url( 'add' ) ); ?>">
				<i class="jws-icon-plus" aria-hidden="true"></i>
				<?php esc_html_e( 'Add Profile', 'jws_streamvid' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<ul class="jws-pf-list">
		<?php foreach ( $jws_pt_profiles as $jws_pt_p ) : ?>
			<?php
			$jws_pt_locked = ! in_array( $jws_pt_p->id, $jws_pt_active, true );
			$jws_pt_last   = isset( $jws_pt_watched[ $jws_pt_p->data_key ] ) ? $jws_pt_watched[ $jws_pt_p->data_key ] : '';

			if ( $jws_pt_p->is_main ) {
				$jws_pt_type = array( 'main', __( 'Main', 'jws_streamvid' ) );
			} elseif ( $jws_pt_p->is_kid ) {
				$jws_pt_type = array( 'kids', __( 'Kids', 'jws_streamvid' ) );
			} else {
				$jws_pt_type = array( 'adult', __( 'Adult', 'jws_streamvid' ) );
			}
			?>
			<li class="jws-pf-list__item<?php echo $jws_pt_locked ? ' is-locked' : ''; ?>">
				<?php echo Jws_Profiles_Front::avatar_img( $jws_pt_p, 48, 'jws-pf-list__avatar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in avatar_img(). ?>

				<span class="jws-pf-list__who">
					<span class="jws-pf-list__name h6">
						<?php echo esc_html( $jws_pt_p->name ); ?>
						<span class="jws-pf-chip jws-pf-chip--<?php echo esc_attr( $jws_pt_type[0] ); ?>"><?php echo esc_html( $jws_pt_type[1] ); ?></span>
					</span>
					<span class="jws-pf-list__meta">
						<?php
						if ( $jws_pt_last ) {
							/* translators: %s: time since, such as "2 hours" */
							echo esc_html( sprintf( __( 'Last watched %s ago', 'jws_streamvid' ), human_time_diff( strtotime( $jws_pt_last ), current_time( 'timestamp' ) ) ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- watched_at is stored in site time.
						} else {
							esc_html_e( 'Not watched yet', 'jws_streamvid' );
						}
						?>
					</span>
				</span>

				<span class="jws-pf-list__rating"><?php echo esc_html( Jws_Profiles_Front::maturity_label( $jws_pt_p->max_maturity ) ); ?></span>

				<span class="jws-pf-list__lock">
					<?php if ( $jws_pt_locked ) : ?>
						<?php esc_html_e( 'Not on your plan', 'jws_streamvid' ); ?>
					<?php elseif ( $jws_pt_p->has_pin ) : ?>
						<i class="jws-icon-lock-key-fill" aria-hidden="true"></i>
						<?php esc_html_e( 'PIN on', 'jws_streamvid' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'No PIN', 'jws_streamvid' ); ?>
					<?php endif; ?>
				</span>

				<a class="button-custom jws-btn-sm jws-pf-list__edit" href="<?php echo esc_url( Jws_Profiles_Front::url( 'edit', $jws_pt_p->id ) ); ?>">
					<?php esc_html_e( 'Edit', 'jws_streamvid' ); ?>
					<span class="screen-reader-text"><?php echo esc_html( $jws_pt_p->name ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>

	<?php if ( $jws_pt_count > $jws_pt_limit ) : ?>
		<p class="jws-pf-msg jws-pf-msg--error">
			<?php
			printf(
				/* translators: %d: number of profiles the plan allows */
				esc_html( _n( 'Your plan allows %d profile, so the others are locked until you change plans.', 'Your plan allows %d profiles, so the others are locked until you change plans.', $jws_pt_limit, 'jws_streamvid' ) ),
				(int) $jws_pt_limit
			);
			?>
			<a href="<?php echo esc_url( Jws_Profiles_Front::url( 'choose', 0, array( 'manage' => 1 ) ) ); ?>"><?php esc_html_e( 'Choose which profiles to keep', 'jws_streamvid' ); ?></a>
		</p>
	<?php endif; ?>

	<div class="jws-pf-info">
		<i class="jws-icon-info-light" aria-hidden="true"></i>
		<p>
			<?php esc_html_e( 'Your membership, purchased and rented titles, and signed-in devices are shared by every profile on this account. Each profile has its own history, Watchlist and Favorites.', 'jws_streamvid' ); ?>
			<?php if ( $jws_pt_upgrade ) : ?>
				<a href="<?php echo esc_url( get_permalink( $jws_pt_upgrade ) ); ?>"><?php esc_html_e( 'Change your plan for more profiles.', 'jws_streamvid' ); ?></a>
			<?php endif; ?>
		</p>
	</div>
</div>
