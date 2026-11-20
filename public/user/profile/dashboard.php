<?php
/**
 * Profile page: Dashboard. The account at a glance for its owner.
 *
 * Loaded for the Dashboard and Membership parent endpoints
 * (/author/{user}/dashboard/ and /author/{user}/membership/) on every
 * profile layout. Shows the plan, how much of the library the account
 * holds, the titles to pick up again and links into the other tabs.
 * Everything optional (Paid Memberships Pro, viewer profiles, device
 * management) is skipped when its plugin or option is off.
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! jws_streamvid()->get()->profile->_profile_is_owner() ) {
	return;
}

$jws_dash_user    = wp_get_current_user();
$jws_dash_id      = (int) $jws_dash_user->ID;
$jws_dash_profile = jws_streamvid()->get()->profile;
$jws_dash_tabs    = $jws_dash_profile->pre_get_menu_items();
$jws_dash_date    = get_option( 'date_format' );
$jws_dash_library = class_exists( 'Jws_Profile_Library' );
$jws_dash_name    = $jws_dash_user->display_name;
// Profile Version 1 and 2 already show the avatar, name, plan and Upgrade button in the profile header above the tab.
$jws_dash_v3      = $jws_dash_library && Jws_Profile_Library::is_v3();

if ( class_exists( 'Jws_Profiles' ) ) {
	$jws_dash_current = Jws_Profiles::current_profile( $jws_dash_id );
	if ( $jws_dash_current && ! empty( $jws_dash_current->name ) ) {
		$jws_dash_name = $jws_dash_current->name;
	}
}

/* ------------------------------------------------------------------ *
 * Library figures and the titles to continue
 * ------------------------------------------------------------------ */

$jws_dash_counts   = array( 'history' => 0, 'watchlist' => 0, 'favorites' => 0 );
$jws_dash_continue = array();

if ( $jws_dash_library ) {
	foreach ( array_keys( $jws_dash_counts ) as $jws_dash_tab ) {
		$jws_dash_items                = Jws_Profile_Library::items( $jws_dash_tab, $jws_dash_id );
		$jws_dash_counts[ $jws_dash_tab ] = count( $jws_dash_items );

		if ( 'history' === $jws_dash_tab ) {
			foreach ( $jws_dash_items as $jws_dash_item ) {
				$jws_dash_card = Jws_Profile_Library::card( $jws_dash_item );
				if ( null === $jws_dash_card['progress'] || $jws_dash_card['progress'] >= 95 ) {
					continue;
				}
				$jws_dash_continue[] = $jws_dash_card;
				// The row shows 5.5 cards on desktop and scrolls sideways, so give it more than one screen.
				if ( count( $jws_dash_continue ) >= 12 ) {
					break;
				}
			}
		}
	}
} else {
	if ( class_exists( 'Jws_History' ) ) {
		$jws_dash_counts['history'] = count( (array) Jws_History::get_all( $jws_dash_id ) );
	}
	if ( class_exists( 'Jws_Watchlist' ) ) {
		$jws_dash_counts['watchlist'] = count( (array) Jws_Watchlist::get_ids( $jws_dash_id ) );
	}
	if ( class_exists( 'Jws_Favorites' ) && function_exists( 'jws_profile_tab_post_types' ) ) {
		$jws_dash_counts['favorites'] = count( (array) Jws_Favorites::get_items( $jws_dash_id, jws_profile_tab_post_types() ) );
	}
}

$jws_dash_stats = array(
	'history'   => array( __( 'In history', 'jws_streamvid' ), 'jws-icon-eye' ),
	'watchlist' => array( __( 'In watchlist', 'jws_streamvid' ), 'jws-icon-bookmark-simple' ),
	'favorites' => array( __( 'Favorites', 'jws_streamvid' ), 'jws-icon-heart' ),
);

/* ------------------------------------------------------------------ *
 * Plan (Paid Memberships Pro) and what the account uses
 * ------------------------------------------------------------------ */

$jws_dash_pmpro  = function_exists( 'pmpro_getMembershipLevelForUser' );
$jws_dash_level  = $jws_dash_pmpro ? pmpro_getMembershipLevelForUser( $jws_dash_id ) : null;
$jws_dash_free   = $jws_dash_level && function_exists( 'pmpro_isLevelFree' ) && pmpro_isLevelFree( $jws_dash_level );
$jws_dash_cost   = '';
$jws_dash_renew  = '';
$jws_dash_upsell = function_exists( 'jws_theme_get_option' ) ? (int) jws_theme_get_option( 'select-update-level' ) : 0;
$jws_dash_plans  = $jws_dash_upsell ? get_permalink( $jws_dash_upsell ) : ( function_exists( 'pmpro_url' ) ? pmpro_url( 'levels' ) : '' );

if ( $jws_dash_level && ! $jws_dash_free ) {
	$jws_dash_next = 0;

	if ( class_exists( 'PMPro_Subscription' ) ) {
		$jws_dash_subs = PMPro_Subscription::get_subscriptions_for_user( $jws_dash_id, $jws_dash_level->id );
		if ( $jws_dash_subs ) {
			$jws_dash_cost = wp_strip_all_tags( $jws_dash_subs[0]->get_cost_text() );
			$jws_dash_next = (int) $jws_dash_subs[0]->get_next_payment_date( 'timestamp', true );
		}
	}
	if ( '' === $jws_dash_cost && function_exists( 'pmpro_getLevelCost' ) ) {
		$jws_dash_cost = wp_strip_all_tags( pmpro_getLevelCost( $jws_dash_level, true, true ) );
	}
	if ( $jws_dash_next ) {
		/* translators: %s: date */
		$jws_dash_renew = sprintf( __( 'Renews on %s', 'jws_streamvid' ), date_i18n( $jws_dash_date, $jws_dash_next ) );
	} elseif ( ! empty( $jws_dash_level->enddate ) ) {
		/* translators: %s: date */
		$jws_dash_renew = sprintf( __( 'Ends on %s', 'jws_streamvid' ), date_i18n( $jws_dash_date, (int) $jws_dash_level->enddate ) );
	}
}

$jws_dash_usage = array();

if ( class_exists( 'Jws_Profiles' ) && isset( $jws_dash_tabs['profiles'] ) ) {
	$jws_dash_usage[] = array(
		'label' => __( 'Profiles', 'jws_streamvid' ),
		'used'  => Jws_Profiles::count( $jws_dash_id ),
		'limit' => Jws_Profiles::get_limit( $jws_dash_id ),
		'url'   => $jws_dash_profile->get_url( 'profiles' ),
	);
}
if ( isset( $jws_dash_tabs['dashboard']['submenu']['devices_manager'] ) ) {
	$jws_dash_devices = get_user_meta( $jws_dash_id, 'jws_user_devices', true );
	$jws_dash_usage[] = array(
		'label' => __( 'Devices', 'jws_streamvid' ),
		'used'  => is_array( $jws_dash_devices ) ? count( $jws_dash_devices ) : 0,
		'limit' => function_exists( 'jws_get_device_limit_for_user' ) ? (int) jws_get_device_limit_for_user( $jws_dash_id ) : 0,
		'url'   => $jws_dash_profile->get_url( 'devices_manager', 'dashboard' ),
	);
}

/* ------------------------------------------------------------------ *
 * Links into the other tabs, only those registered for this site,
 * keyed like Theme Options > Dashboard Account Links
 * ------------------------------------------------------------------ */

$jws_dash_links = array();

$jws_dash_links['profile'] = array(
	'title' => __( 'Settings', 'jws_streamvid' ),
	'text'  => __( 'Name, email, phone and password.', 'jws_streamvid' ),
	'icon'  => 'jws-icon-gear',
	'url'   => $jws_dash_profile->get_url( 'profile', 'dashboard' ),
);
if ( isset( $jws_dash_tabs['profiles'] ) ) {
	$jws_dash_links['profiles'] = array(
		'title' => __( 'Profiles', 'jws_streamvid' ),
		'text'  => __( 'Who watches on this account.', 'jws_streamvid' ),
		'icon'  => 'jws-icon-user-circle',
		'url'   => $jws_dash_profile->get_url( 'profiles' ),
	);
}
if ( isset( $jws_dash_tabs['dashboard']['submenu']['devices_manager'] ) ) {
	$jws_dash_links['devices_manager'] = array(
		// Same name as the menu tab (Version 3 calls it Devices).
		'title' => __( 'Devices', 'jws_streamvid' ),
		'text'  => __( 'Where this account is signed in.', 'jws_streamvid' ),
		'icon'  => 'jws-icon-devices',
		'url'   => $jws_dash_profile->get_url( 'devices_manager', 'dashboard' ),
	);
}
if ( isset( $jws_dash_tabs['membership'] ) ) {
	$jws_dash_links['billing'] = array(
		'title' => __( 'Billing', 'jws_streamvid' ),
		'text'  => __( 'Payment method and billing address.', 'jws_streamvid' ),
		'icon'  => 'jws-icon-wallet',
		'url'   => $jws_dash_profile->get_url( 'billing', 'membership' ),
	);
	$jws_dash_links['invoices'] = array(
		'title' => __( 'Invoices', 'jws_streamvid' ),
		'text'  => __( 'Every membership payment.', 'jws_streamvid' ),
		'icon'  => 'jws-icon-list',
		'url'   => $jws_dash_profile->get_url( 'invoices', 'membership' ),
	);
}
if ( isset( $jws_dash_tabs['playlist'] ) ) {
	$jws_dash_links['playlist'] = array(
		'title' => __( 'Playlists', 'jws_streamvid' ),
		'text'  => __( 'Titles grouped your way.', 'jws_streamvid' ),
		'icon'  => 'jws-icon-queue',
		'url'   => $jws_dash_profile->get_url( 'playlist' ),
	);
}
if ( isset( $jws_dash_tabs['video'] ) ) {
	$jws_dash_links['video'] = array(
		'title' => __( 'My Videos', 'jws_streamvid' ),
		'text'  => __( 'Videos you uploaded.', 'jws_streamvid' ),
		'icon'  => 'jws-icon-video-camera',
		'url'   => $jws_dash_profile->get_url( 'video' ),
	);
}
if ( isset( $jws_dash_tabs['dashboard']['submenu']['shop'] ) ) {
	$jws_dash_links['shop'] = array(
		'title' => __( 'Shopping', 'jws_streamvid' ),
		'text'  => __( 'Orders, downloads and addresses.', 'jws_streamvid' ),
		'icon'  => 'jws-icon-handbag',
		'url'   => $jws_dash_profile->get_url( 'shop', 'dashboard' ),
	);
}

/*
 * Theme Options > Profile > Dashboard Account Links: order and hidden cards.
 * Never saved: the order above. Cards added after the last save (in neither
 * list) still show, after the sorted ones, like the Profile Menu Sorter.
 */
$jws_dash_sort = function_exists( 'jws_theme_get_option' ) ? jws_theme_get_option( 'dashboard_links_list', array() ) : array();
$jws_dash_sort = is_array( $jws_dash_sort ) ? $jws_dash_sort : json_decode( (string) $jws_dash_sort, true );
$jws_dash_on   = ( isset( $jws_dash_sort['enabled'] ) && is_array( $jws_dash_sort['enabled'] ) ) ? $jws_dash_sort['enabled'] : array();
$jws_dash_off  = ( isset( $jws_dash_sort['disabled'] ) && is_array( $jws_dash_sort['disabled'] ) ) ? $jws_dash_sort['disabled'] : array();
unset( $jws_dash_on['placebo'], $jws_dash_off['placebo'] );

if ( $jws_dash_on || $jws_dash_off ) {
	$jws_dash_sorted = array();

	foreach ( array_keys( $jws_dash_on ) as $jws_dash_key ) {
		if ( isset( $jws_dash_links[ $jws_dash_key ] ) ) {
			$jws_dash_sorted[ $jws_dash_key ] = $jws_dash_links[ $jws_dash_key ];
		}
	}

	foreach ( $jws_dash_links as $jws_dash_key => $jws_dash_link ) {
		if ( ! isset( $jws_dash_sorted[ $jws_dash_key ] ) && ! isset( $jws_dash_off[ $jws_dash_key ] ) ) {
			$jws_dash_sorted[ $jws_dash_key ] = $jws_dash_link;
		}
	}

	$jws_dash_links = $jws_dash_sorted;
}
?>
<div class="jws-dash">

	<section class="jws-dash__hero">
		<?php if ( $jws_dash_v3 ) : ?>
			<?php echo get_avatar( $jws_dash_id, 72, '', '', array( 'class' => 'jws-dash__avatar' ) ); ?>
		<?php endif; ?>
		<div class="jws-dash__hero-text">
			<?php // Version 3 already names the tab in its title bar. ?>
			<?php if ( ! $jws_dash_v3 ) : ?>
				<span class="jws-dash__eyebrow"><?php esc_html_e( 'Dashboard', 'jws_streamvid' ); ?></span>
			<?php endif; ?>
			<h5 class="jws-dash__title">
				<?php
				/* translators: %s: profile or display name */
				echo esc_html( sprintf( __( 'Welcome back, %s', 'jws_streamvid' ), $jws_dash_name ) );
				?>
			</h5>
			<?php
			// The plan name is in the profile header (Version 1, 2), the side card (Version 3) and the plan card below.
			$jws_dash_since = strtotime( $jws_dash_user->user_registered );
			if ( $jws_dash_since ) :
				?>
				<span class="jws-dash__sub">
					<?php
					/* translators: %s: date the account was created */
					echo esc_html( sprintf( __( 'Member since %s', 'jws_streamvid' ), date_i18n( $jws_dash_date, $jws_dash_since ) ) );
					?>
				</span>
			<?php endif; ?>
		</div>
		<div class="jws-dash__hero-actions">
			<?php // Plan buttons live on the plan card below, so the hero keeps one action. ?>
			<a class="button-custom" href="<?php echo esc_url( $jws_dash_profile->get_url( 'profile', 'dashboard' ) ); ?>">
				<i class="jws-icon-gear" aria-hidden="true"></i>
				<?php esc_html_e( 'Settings', 'jws_streamvid' ); ?>
			</a>
		</div>
	</section>

	<ul class="jws-dash__stats">
		<?php foreach ( $jws_dash_stats as $jws_dash_key => $jws_dash_stat ) : ?>
			<?php if ( ! isset( $jws_dash_tabs[ $jws_dash_key ] ) ) : continue; endif; ?>
			<li class="jws-dash__stat">
				<a class="jws-dash__stat-link" href="<?php echo esc_url( $jws_dash_profile->get_url( $jws_dash_key ) ); ?>">
					<i class="<?php echo esc_attr( $jws_dash_stat[1] ); ?>" aria-hidden="true"></i>
					<strong class="jws-dash__stat-value h4"><?php echo esc_html( number_format_i18n( $jws_dash_counts[ $jws_dash_key ] ) ); ?></strong>
					<span class="jws-dash__stat-label"><?php echo esc_html( $jws_dash_stat[0] ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>

	<?php if ( $jws_dash_pmpro ) : ?>
		<section class="jws-dash__plan">
			<div class="jws-dash__plan-head">
				<div class="jws-dash__plan-text">
					<span class="jws-dash__eyebrow"><?php esc_html_e( 'Current plan', 'jws_streamvid' ); ?></span>
					<span class="jws-dash__plan-name h6"><?php echo $jws_dash_level ? esc_html( $jws_dash_level->name ) : esc_html__( 'Free Plan', 'jws_streamvid' ); ?></span>
					<span class="jws-dash__plan-cost">
						<?php
						if ( $jws_dash_level && ! $jws_dash_free ) {
							echo esc_html( implode( ' · ', array_filter( array( $jws_dash_cost, $jws_dash_renew ), 'strlen' ) ) );
						} else {
							esc_html_e( 'Upgrade to watch premium titles, add more profiles and sign in on more devices.', 'jws_streamvid' );
						}
						?>
					</span>
				</div>
				<div class="jws-dash__plan-actions">
					<?php if ( $jws_dash_level && ! $jws_dash_free ) : ?>
						<a class="button-custom" href="<?php echo esc_url( $jws_dash_profile->get_url( 'subscriptions', 'membership' ) ); ?>"><?php esc_html_e( 'Your Subscriptions', 'jws_streamvid' ); ?></a>
					<?php elseif ( $jws_dash_plans ) : ?>
						<?php // Upgrading is the main action for a free account, so it gets the primary button. ?>
						<a class="button-default" href="<?php echo esc_url( $jws_dash_plans ); ?>">
							<i class="jws-icon-crown-simple" aria-hidden="true"></i>
							<?php esc_html_e( 'View all Membership Options', 'jws_streamvid' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( $jws_dash_usage ) : ?>
				<div class="jws-dash__usage">
					<?php foreach ( $jws_dash_usage as $jws_dash_meter ) : ?>
						<?php $jws_dash_pct = $jws_dash_meter['limit'] > 0 ? min( 100, round( 100 * $jws_dash_meter['used'] / $jws_dash_meter['limit'] ) ) : 0; ?>
						<a class="jws-dash__meter<?php echo ( $jws_dash_meter['limit'] > 0 && $jws_dash_meter['used'] >= $jws_dash_meter['limit'] ) ? ' is-full' : ''; ?>" href="<?php echo esc_url( $jws_dash_meter['url'] ); ?>">
							<span class="jws-dash__meter-row">
								<span><?php echo esc_html( $jws_dash_meter['label'] ); ?></span>
								<strong>
									<?php
									if ( $jws_dash_meter['limit'] > 0 ) {
										/* translators: 1: used, 2: allowed */
										echo esc_html( sprintf( __( '%1$d of %2$d', 'jws_streamvid' ), $jws_dash_meter['used'], $jws_dash_meter['limit'] ) );
									} else {
										echo esc_html( number_format_i18n( $jws_dash_meter['used'] ) );
									}
									?>
								</strong>
							</span>
							<span class="jws-pf-meter" aria-hidden="true"><span class="jws-pf-meter__fill" style="width: <?php echo (int) $jws_dash_pct; ?>%"></span></span>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<?php if ( $jws_dash_continue ) : ?>
		<section class="jws-dash__continue">
			<div class="jws-dash__head">
				<h6 class="jws-dash__head-title"><?php esc_html_e( 'Continue watching', 'jws_streamvid' ); ?></h6>
				<?php if ( isset( $jws_dash_tabs['history'] ) ) : ?>
					<a class="jws-dash__head-link" href="<?php echo esc_url( $jws_dash_profile->get_url( 'history' ) ); ?>"><?php esc_html_e( 'See all history', 'jws_streamvid' ); ?></a>
				<?php endif; ?>
			</div>
			<ul class="jws-lib__grid jws-dash__grid" data-jws-drag-scroll>
				<?php foreach ( $jws_dash_continue as $jws_dash_card ) : ?>
					<li class="jws-lib__item">
						<a class="jws-lib__card" href="<?php echo esc_url( $jws_dash_card['url'] ); ?>">
							<span class="jws-lib__art">
								<?php if ( $jws_dash_card['image'] ) : ?>
									<img src="<?php echo esc_url( $jws_dash_card['image'] ); ?>" alt="" loading="lazy" decoding="async">
								<?php endif; ?>
								<span class="jws-lib__type"><?php echo esc_html( $jws_dash_card['type'] ); ?></span>
								<span class="time-data"><span style="width: <?php echo (int) $jws_dash_card['progress']; ?>%"></span></span>
							</span>
							<h6 class="jws-lib__title fs-small"><?php echo esc_html( $jws_dash_card['title'] ); ?></h6>
							<?php if ( $jws_dash_card['meta'] ) : ?>
								<span class="jws-lib__meta fs-small"><?php echo esc_html( $jws_dash_card['meta'] ); ?></span>
							<?php endif; ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php // Every card switched off in Dashboard Account Links: no empty "Your account" heading. ?>
	<?php if ( $jws_dash_links ) : ?>
		<section class="jws-dash__links">
			<div class="jws-dash__head">
				<h6 class="jws-dash__head-title"><?php esc_html_e( 'Your account', 'jws_streamvid' ); ?></h6>
			</div>
			<ul class="jws-dash__link-grid">
				<?php foreach ( $jws_dash_links as $jws_dash_link ) : ?>
					<li class="jws-dash__link-item">
						<a class="jws-dash__link" href="<?php echo esc_url( $jws_dash_link['url'] ); ?>">
							<i class="<?php echo esc_attr( $jws_dash_link['icon'] ); ?>" aria-hidden="true"></i>
							<span class="jws-dash__link-text">
								<strong class="jws-dash__link-title"><?php echo esc_html( $jws_dash_link['title'] ); ?></strong>
								<span class="jws-dash__link-sub fs-small"><?php echo esc_html( $jws_dash_link['text'] ); ?></span>
							</span>
							<i class="jws-icon-caret-right jws-dash__link-arrow" aria-hidden="true"></i>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

</div>
