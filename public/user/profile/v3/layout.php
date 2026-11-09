<?php
/**
 * Profile page, Version 3: account card and grouped menu beside the tab.
 *
 * One menu markup serves every width; profiles.css lays it out as a 290px
 * sidebar (from 1025px), a scrolling bar under the account card (768-1024px)
 * or a "Sections" sheet (to 767px, opened by profiles.js; without JavaScript
 * the menu simply shows above the tab).
 *
 * The tabs and their order come from the profile class (Theme Options
 * Profile Menu sorter); the order applies inside each group.
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jws_v3_profile = jws_streamvid()->get()->profile;
$jws_v3_author  = (int) get_queried_object_id();
$jws_v3_owner   = jws_streamvid_check_owner();
$jws_v3_current = $jws_v3_profile->get_currents_menu();
$jws_v3_items   = $jws_v3_profile->the_menu_items_position();
$jws_v3_kids    = class_exists( 'Jws_Kids_Mode' ) && Jws_Kids_Mode::is_active();

// Visitors see the creator's public videos only.
if ( ! $jws_v3_owner ) :
	?>
	<div class="jws-pv3 jws-pv3--public" data-jws-base="<?php echo esc_url( Jws_Streamvid_Profile::get_url() ); ?>">
		<div class="jws-pv3__creator">
			<?php echo get_avatar( $jws_v3_author, 72, '', '', array( 'class' => 'jws-pv3__creator-avatar' ) ); ?>
			<h5 class="jws-pv3__title"><?php echo esc_html( get_the_author_meta( 'display_name', $jws_v3_author ) ); ?></h5>
		</div>
		<div class="jws-pv3__content">
			<?php do_action( 'streamvid/profile/main' ); ?>
		</div>
	</div>
	<?php
	return;
endif;

$jws_v3_groups = array(
	'library'   => array( 'label' => __( 'Library', 'jws_streamvid' ), 'items' => array() ),
	'purchases' => array( 'label' => __( 'Purchases', 'jws_streamvid' ), 'items' => array() ),
	'creator'   => array( 'label' => __( 'Creator', 'jws_streamvid' ), 'items' => array() ),
	'account'   => array( 'label' => __( 'Account', 'jws_streamvid' ), 'items' => array() ),
);
$jws_v3_group_of = array(
	'history'         => 'library',
	'watchlist'       => 'library',
	'favorites'       => 'library',
	'playlist'        => 'library',
	'pay_per_view'    => 'purchases',
	'rented'          => 'purchases',
	'drama-coins'     => 'purchases',
	'video'           => 'creator',
	'profiles'        => 'account',
	'subscriptions'   => 'account',
	'devices_manager' => 'account',
	'profile'         => 'account',
);
$jws_v3_titles = array(
	'playlist'        => __( 'Playlists', 'jws_streamvid' ),
	'pay_per_view'    => __( 'Purchased and Rented', 'jws_streamvid' ),
	'rented'          => __( 'Purchased and Rented', 'jws_streamvid' ),
	'drama-coins'     => __( 'Coins', 'jws_streamvid' ),
	'video'           => __( 'My Videos', 'jws_streamvid' ),
	'subscriptions'   => __( 'Subscription', 'jws_streamvid' ),
	'devices_manager' => __( 'Devices', 'jws_streamvid' ),
	'profile'         => __( 'Settings', 'jws_streamvid' ),
);
$jws_v3_icons = array(
	'history'         => 'jws-icon-eye',
	'watchlist'       => 'jws-icon-bookmark-simple',
	'favorites'       => 'jws-icon-heart',
	'playlist'        => 'jws-icon-queue',
	'pay_per_view'    => 'jws-icon-handbag',
	'rented'          => 'jws-icon-handbag',
	'drama-coins'     => 'jws-icon-coins',
	'video'           => 'jws-icon-monitor-play',
	'profiles'        => 'jws-icon-user-circle',
	'subscriptions'   => 'jws-icon-crown-simple',
	'devices_manager' => 'jws-icon-devices',
	'profile'         => 'jws-icon-gear',
);
$jws_v3_purchase_tabs = array_values( array_intersect( array( 'rented', 'pay_per_view' ), array_keys( $jws_v3_items ) ) );
$jws_v3_title         = '';

foreach ( $jws_v3_items as $jws_v3_id => $jws_v3_menu ) {

	if ( $jws_v3_kids && ! in_array( $jws_v3_id, Jws_Kids_Mode::PROFILE_TABS, true ) ) {
		continue;
	}

	$jws_v3_menu  = wp_parse_args( $jws_v3_menu, array( 'url' => '', 'parent' => '', 'title' => $jws_v3_id ) );
	$jws_v3_label = isset( $jws_v3_titles[ $jws_v3_id ] ) ? $jws_v3_titles[ $jws_v3_id ] : $jws_v3_menu['title'];
	$jws_v3_on    = $jws_v3_current === $jws_v3_id || ( in_array( $jws_v3_id, $jws_v3_purchase_tabs, true ) && in_array( $jws_v3_current, $jws_v3_purchase_tabs, true ) );

	if ( $jws_v3_current === $jws_v3_id || ( $jws_v3_on && '' === $jws_v3_title ) ) {
		$jws_v3_title = $jws_v3_label;
	}

	// Rented and Purchased share one menu entry; the tab bar above the content switches between them.
	if ( in_array( $jws_v3_id, $jws_v3_purchase_tabs, true ) && $jws_v3_purchase_tabs[0] !== $jws_v3_id ) {
		continue;
	}

	$jws_v3_group = isset( $jws_v3_group_of[ $jws_v3_id ] ) ? $jws_v3_group_of[ $jws_v3_id ] : 'account';

	$jws_v3_groups[ $jws_v3_group ]['items'][ $jws_v3_id ] = array(
		'label' => $jws_v3_label,
		'href'  => $jws_v3_menu['url'] ? $jws_v3_menu['url'] : $jws_v3_profile->get_url( $jws_v3_id, $jws_v3_menu['parent'] ),
		'icon'  => isset( $jws_v3_icons[ $jws_v3_id ] ) ? $jws_v3_icons[ $jws_v3_id ] : 'jws-icon-list',
		'on'    => $jws_v3_on,
	);
}

if ( '' === $jws_v3_title ) {
	$jws_v3_title = __( 'My account', 'jws_streamvid' );
}

$jws_v3_user     = wp_get_current_user();
$jws_v3_viewing  = Jws_Profiles::current_profile( $jws_v3_user->ID );
$jws_v3_level    = function_exists( 'pmpro_getMembershipLevelForUser' ) ? pmpro_getMembershipLevelForUser( $jws_v3_user->ID ) : null;
$jws_v3_upgrade  = function_exists( 'jws_theme_get_option' ) ? (int) jws_theme_get_option( 'select-update-level' ) : 0;
$jws_v3_email    = function_exists( 'jws_streamvid_email_is_placeholder' ) && jws_streamvid_email_is_placeholder( $jws_v3_user->ID )
	? __( 'Email not set', 'jws_streamvid' )
	: $jws_v3_user->user_email;
$jws_v3_host     = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
$jws_v3_path     = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
$jws_v3_here     = ( is_ssl() ? 'https://' : 'http://' ) . $jws_v3_host . $jws_v3_path;
?>
<div class="jws-pv3" data-jws-base="<?php echo esc_url( Jws_Streamvid_Profile::get_url() ); ?>">

	<div class="jws-pv3__bar">
		<h5 class="jws-pv3__title"><?php echo esc_html( $jws_v3_title ); ?></h5>
		<button type="button" class="jws-pv3__sections" aria-controls="jws-pv3-side" aria-expanded="false" hidden>
			<i class="jws-icon-squares-four" aria-hidden="true"></i>
			<?php esc_html_e( 'Sections', 'jws_streamvid' ); ?>
		</button>
	</div>

	<?php // The wrapper is the sticky parent: it spans the grid row, and the theme's Sticky-kit spacer stays inside it. ?>
	<div class="jws-pv3__side-wrap">
		<aside id="jws-pv3-side" class="jws-pv3__side jws_sticky_move" aria-label="<?php esc_attr_e( 'Account', 'jws_streamvid' ); ?>">
			<div class="jws-pv3__sheet-head">
				<span class="jws-pv3__sheet-title h5"><?php esc_html_e( 'My account', 'jws_streamvid' ); ?></span>
				<button type="button" class="jws-pv3__close">
					<i class="jws-icon-x" aria-hidden="true"></i>
					<span class="screen-reader-text"><?php esc_html_e( 'Close', 'jws_streamvid' ); ?></span>
				</button>
			</div>

			<div class="jws-pv3__card">
				<div class="jws-pv3__me">
					<?php echo get_avatar( $jws_v3_user->ID, 52, '', '', array( 'class' => 'jws-pv3__me-avatar' ) ); ?>
					<span class="jws-pv3__me-text">
						<span class="jws-pv3__me-name h6"><?php echo esc_html( $jws_v3_user->display_name ); ?></span>
						<span class="jws-pv3__me-email"><?php echo esc_html( $jws_v3_email ); ?></span>
					</span>
				</div>

				<?php if ( ! $jws_v3_kids ) : ?>
					<div class="jws-pv3__plan">
						<span class="jws-pv3__plan-text">
							<span class="jws-pv3__plan-label"><?php esc_html_e( 'Current plan', 'jws_streamvid' ); ?></span>
							<span class="jws-pv3__plan-name h6"><?php echo esc_html( $jws_v3_level ? $jws_v3_level->name : __( 'Free Plan', 'jws_streamvid' ) ); ?></span>
						</span>
						<?php if ( $jws_v3_upgrade ) : ?>
							<a class="button-default jws-btn-sm jws-pv3__upgrade" href="<?php echo esc_url( get_permalink( $jws_v3_upgrade ) ); ?>"><?php esc_html_e( 'Upgrade', 'jws_streamvid' ); ?></a>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( $jws_v3_viewing && Jws_Profiles::enabled() ) : ?>
					<div class="jws-pv3__viewing">
						<?php echo Jws_Profiles_Front::avatar_img( $jws_v3_viewing, 32, 'jws-pv3__viewing-avatar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in avatar_img(). ?>
						<span>
							<?php
							/* translators: %s: profile name */
							printf( esc_html__( 'Watching as %s', 'jws_streamvid' ), '<strong>' . esc_html( $jws_v3_viewing->name ) . '</strong>' );
							?>
						</span>
						<a href="<?php echo esc_url( Jws_Profiles_Front::url( 'choose', 0, array( 'back' => $jws_v3_here ) ) ); ?>"><?php esc_html_e( 'Switch', 'jws_streamvid' ); ?></a>
					</div>
				<?php endif; ?>
			</div>

			<nav class="jws-pv3__nav" aria-label="<?php esc_attr_e( 'Account sections', 'jws_streamvid' ); ?>">
				<?php foreach ( $jws_v3_groups as $jws_v3_group ) : ?>
					<?php if ( ! $jws_v3_group['items'] ) { continue; } ?>
					<div class="jws-pv3__group">
						<span class="jws-pv3__group-label"><?php echo esc_html( $jws_v3_group['label'] ); ?></span>
						<?php foreach ( $jws_v3_group['items'] as $jws_v3_id => $jws_v3_item ) : ?>
							<a class="jws-pv3__link<?php echo $jws_v3_item['on'] ? ' is-active' : ''; ?>" href="<?php echo esc_url( $jws_v3_item['href'] ); ?>"<?php echo $jws_v3_item['on'] ? ' aria-current="page"' : ''; ?>>
								<i class="<?php echo esc_attr( $jws_v3_item['icon'] ); ?>" aria-hidden="true"></i>
								<span><?php echo esc_html( $jws_v3_item['label'] ); ?></span>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			</nav>

			<?php if ( ! $jws_v3_kids ) : ?>
				<a class="jws-pv3__link jws-pv3__logout" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">
					<i class="jws-icon-sign-out" aria-hidden="true"></i>
					<span><?php esc_html_e( 'Log out', 'jws_streamvid' ); ?></span>
				</a>
			<?php endif; ?>
		</aside>
	</div>
	<div class="jws-pv3__backdrop" hidden></div>

	<div class="jws-pv3__content">
		<?php if ( count( $jws_v3_purchase_tabs ) > 1 && in_array( $jws_v3_current, $jws_v3_purchase_tabs, true ) ) : ?>
			<nav class="jws-pv3__tabs" aria-label="<?php esc_attr_e( 'Purchase type', 'jws_streamvid' ); ?>">
				<?php foreach ( $jws_v3_purchase_tabs as $jws_v3_tab ) : ?>
					<a class="jws-pv3__tab<?php echo $jws_v3_current === $jws_v3_tab ? ' is-active' : ''; ?>" href="<?php echo esc_url( $jws_v3_profile->get_url( $jws_v3_tab ) ); ?>"<?php echo $jws_v3_current === $jws_v3_tab ? ' aria-current="page"' : ''; ?>>
						<?php echo 'rented' === $jws_v3_tab ? esc_html__( 'Rented', 'jws_streamvid' ) : esc_html__( 'Purchased', 'jws_streamvid' ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>

		<?php do_action( 'streamvid/profile/main' ); ?>
	</div>
</div>
