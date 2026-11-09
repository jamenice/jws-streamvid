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
	<div class="jws-pv3 jws-pv3--public">
		<div class="jws-pv3__creator">
			<?php echo get_avatar( $jws_v3_author, 72, '', '', array( 'class' => 'jws-pv3__creator-avatar' ) ); ?>
			<h1 class="jws-pv3__title"><?php echo esc_html( get_the_author_meta( 'display_name', $jws_v3_author ) ); ?></h1>
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
	'history'         => 'M3 12a9 9 0 1 0 18 0a9 9 0 1 0-18 0M12 7v5l3 2',
	'watchlist'       => 'M6 4h12v17l-6-4-6 4z',
	'favorites'       => 'M12 20s-7-4.5-7-10a4 4 0 0 1 7-2.5A4 4 0 0 1 19 10c0 5.5-7 10-7 10z',
	'playlist'        => 'M4 6h12M4 12h12M4 18h8M17 15v6l4.5-3z',
	'pay_per_view'    => 'M5 8h14l-1 12H6zM9 8V6a3 3 0 0 1 6 0v2',
	'rented'          => 'M5 8h14l-1 12H6zM9 8V6a3 3 0 0 1 6 0v2',
	'drama-coins'     => 'M5 7a7 3 0 1 0 14 0a7 3 0 1 0-14 0M5 7v5c0 1.7 3.1 3 7 3s7-1.3 7-3V7M5 12v5c0 1.7 3.1 3 7 3s7-1.3 7-3v-5',
	'video'           => 'M5 5h10a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2zM17 10l4-2v8l-4-2',
	'profiles'        => 'M5.5 8a3.5 3.5 0 1 0 7 0a3.5 3.5 0 1 0-7 0M2.5 20c.8-3.5 3.4-5.5 6.5-5.5s5.7 2 6.5 5.5M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5c2 .6 3.2 2.5 3.6 5.5',
	'subscriptions'   => 'M3 8l4 4 5-7 5 7 4-4-2 11H5z',
	'devices_manager' => 'M3.5 4h11A1.5 1.5 0 0 1 16 5.5v8a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 2 13.5v-8A1.5 1.5 0 0 1 3.5 4zM6 19h6M18 9h3a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1h-3a1 1 0 0 1-1-1v-9a1 1 0 0 1 1-1z',
	'profile'         => 'M9 12a3 3 0 1 0 6 0a3 3 0 1 0-6 0M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1',
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
		'icon'  => isset( $jws_v3_icons[ $jws_v3_id ] ) ? $jws_v3_icons[ $jws_v3_id ] : 'M8 12a4 4 0 1 0 8 0a4 4 0 1 0-8 0',
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
<div class="jws-pv3">

	<div class="jws-pv3__bar">
		<h1 class="jws-pv3__title"><?php echo esc_html( $jws_v3_title ); ?></h1>
		<button type="button" class="jws-pv3__sections" aria-controls="jws-pv3-side" aria-expanded="false" hidden>
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="4" width="6.5" height="6.5" rx="1.5"></rect><rect x="13.5" y="4" width="6.5" height="6.5" rx="1.5"></rect><rect x="4" y="13.5" width="6.5" height="6.5" rx="1.5"></rect><rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1.5"></rect></svg>
			<?php esc_html_e( 'Sections', 'jws_streamvid' ); ?>
		</button>
	</div>

	<aside id="jws-pv3-side" class="jws-pv3__side" aria-label="<?php esc_attr_e( 'Account', 'jws_streamvid' ); ?>">
		<div class="jws-pv3__sheet-head">
			<span class="jws-pv3__sheet-title"><?php esc_html_e( 'My account', 'jws_streamvid' ); ?></span>
			<button type="button" class="jws-pv3__close">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"></path></svg>
				<span class="screen-reader-text"><?php esc_html_e( 'Close', 'jws_streamvid' ); ?></span>
			</button>
		</div>

		<div class="jws-pv3__card">
			<div class="jws-pv3__me">
				<?php echo get_avatar( $jws_v3_user->ID, 52, '', '', array( 'class' => 'jws-pv3__me-avatar' ) ); ?>
				<span class="jws-pv3__me-text">
					<span class="jws-pv3__me-name"><?php echo esc_html( $jws_v3_user->display_name ); ?></span>
					<span class="jws-pv3__me-email"><?php echo esc_html( $jws_v3_email ); ?></span>
				</span>
			</div>

			<?php if ( ! $jws_v3_kids ) : ?>
				<div class="jws-pv3__plan">
					<span class="jws-pv3__plan-text">
						<span class="jws-pv3__plan-label"><?php esc_html_e( 'Current plan', 'jws_streamvid' ); ?></span>
						<span class="jws-pv3__plan-name"><?php echo esc_html( $jws_v3_level ? $jws_v3_level->name : __( 'Free Plan', 'jws_streamvid' ) ); ?></span>
					</span>
					<?php if ( $jws_v3_upgrade ) : ?>
						<a class="jws-pf-btn jws-pv3__upgrade" href="<?php echo esc_url( get_permalink( $jws_v3_upgrade ) ); ?>"><?php esc_html_e( 'Upgrade', 'jws_streamvid' ); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $jws_v3_viewing ) : ?>
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
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?php echo esc_attr( $jws_v3_item['icon'] ); ?>"></path></svg>
							<span><?php echo esc_html( $jws_v3_item['label'] ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</nav>

		<?php if ( ! $jws_v3_kids ) : ?>
			<a class="jws-pv3__link jws-pv3__logout" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 4h4v16h-4"></path><path d="M10 8l-4 4 4 4M6 12h10"></path></svg>
				<span><?php esc_html_e( 'Log out', 'jws_streamvid' ); ?></span>
			</a>
		<?php endif; ?>
	</aside>
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
