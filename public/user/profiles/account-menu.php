<?php
/**
 * Header account menu: the profile in use, switching profiles, then the
 * account links grouped as Library, Create and Account.
 *
 * The links and their order come from the profile class's header items
 * (Theme Options > Profile > Account Dropdown Menu); the order applies
 * inside each group. A Kids profile gets switching, its own lists and
 * Exit Kids only.
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jws_am_user     = get_current_user_id();
$jws_am_current  = Jws_Profiles::current_profile( $jws_am_user );
$jws_am_kid      = $jws_am_current && $jws_am_current->is_kid;
$jws_am_manage   = Jws_Profiles_Front::can_manage_account();
$jws_am_active   = Jws_Profiles::active_ids( $jws_am_user );
$jws_am_profile  = jws_streamvid()->get()->profile;
$jws_am_items    = $jws_am_profile->get_menu_menu_header_items();
$jws_am_host     = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
$jws_am_path     = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
$jws_am_here     = ( is_ssl() ? 'https://' : 'http://' ) . $jws_am_host . $jws_am_path;
$jws_am_others   = array();
$jws_am_on       = Jws_Profiles::enabled();

foreach ( $jws_am_on ? Jws_Profiles::get_all( $jws_am_user ) : array() as $jws_am_p ) {
	if ( ( ! $jws_am_current || $jws_am_p->id !== $jws_am_current->id ) && in_array( $jws_am_p->id, $jws_am_active, true ) ) {
		$jws_am_others[] = $jws_am_p;
	}
}

// Plan and coins under the name.
$jws_am_meta = array();
if ( ! $jws_am_kid && function_exists( 'pmpro_getMembershipLevelForUser' ) ) {
	$jws_am_level  = pmpro_getMembershipLevelForUser( $jws_am_user );
	$jws_am_meta[] = $jws_am_level ? $jws_am_level->name : __( 'Free Plan', 'jws_streamvid' );
}
if ( ! $jws_am_kid && isset( $jws_am_items['drama-coins'] ) && class_exists( 'Jws_Drama_Wallet' ) ) {
	$jws_am_coins  = Jws_Drama_Wallet::balance( $jws_am_user );
	/* translators: %s: number of coins */
	$jws_am_meta[] = sprintf( _n( '%s coin', '%s coins', $jws_am_coins, 'jws_streamvid' ), number_format_i18n( $jws_am_coins ) );
}

$jws_am_url = function ( $id, $menu ) use ( $jws_am_profile, $jws_am_user ) {
	if ( ! empty( $menu['url'] ) ) {
		return $menu['url'];
	}
	return empty( $menu['parent'] )
		? $jws_am_profile->get_endpoint( $jws_am_user, $id, '' )
		: $jws_am_profile->get_endpoint( $jws_am_user, $menu['parent'], $id );
};

// Library / Create / Account, each keeping the sorter's order.
$jws_am_groups = array(
	'library' => array( 'label' => __( 'Library', 'jws_streamvid' ), 'items' => array() ),
	'create'  => array( 'label' => __( 'Create', 'jws_streamvid' ), 'items' => array() ),
	'account' => array( 'label' => __( 'Account', 'jws_streamvid' ), 'items' => array() ),
);
$jws_am_group_of = array(
	'favorites' => 'library',
	'history'   => 'library',
	'upload'    => 'create',
	'live'      => 'create',
);
$jws_am_logout = isset( $jws_am_items['logout'] ) ? $jws_am_items['logout'] : null;

if ( $jws_am_kid ) {
	foreach ( array( 'history', 'watchlist', 'favorites' ) as $jws_am_id ) {
		$jws_am_titles = array(
			'history'   => __( 'History', 'jws_streamvid' ),
			'watchlist' => __( 'Watchlist', 'jws_streamvid' ),
			'favorites' => __( 'Favorites', 'jws_streamvid' ),
		);
		$jws_am_groups['library']['items'][ $jws_am_id ] = array(
			'title' => $jws_am_titles[ $jws_am_id ],
			'icon'  => isset( $jws_am_items[ $jws_am_id ]['icon'] ) ? $jws_am_items[ $jws_am_id ]['icon'] : '',
			'href'  => $jws_am_profile->get_endpoint( $jws_am_user, $jws_am_id, '' ),
			'popup' => '',
		);
	}
} else {
	foreach ( $jws_am_items as $jws_am_id => $jws_am_menu ) {
		// The card at the top opens the profile page, so "My Profile" is not repeated.
		if ( in_array( $jws_am_id, array( 'author', 'logout' ), true ) ) {
			continue;
		}
		// "Only the main profile manages the account": no Settings or Subscription for the others.
		if ( ! $jws_am_manage && in_array( $jws_am_id, Jws_Profiles_Front::ACCOUNT_TABS, true ) ) {
			continue;
		}
		$jws_am_group = isset( $jws_am_group_of[ $jws_am_id ] ) ? $jws_am_group_of[ $jws_am_id ] : 'account';

		$jws_am_groups[ $jws_am_group ]['items'][ $jws_am_id ] = array(
			'title' => $jws_am_menu['title'],
			'icon'  => isset( $jws_am_menu['icon'] ) ? $jws_am_menu['icon'] : '',
			'href'  => $jws_am_url( $jws_am_id, $jws_am_menu ),
			'popup' => isset( $jws_am_menu['popup'] ) ? $jws_am_menu['popup'] : '',
		);
	}
}
?>
<div class="jws-am<?php echo $jws_am_kid ? ' jws-am--kids' : ''; ?>">

	<a class="jws-am__me" href="<?php echo esc_url( $jws_am_kid ? $jws_am_profile->get_endpoint( $jws_am_user, 'history', '' ) : get_author_posts_url( $jws_am_user ) ); ?>">
		<?php echo $jws_am_on ? Jws_Profiles_Front::avatar_img( $jws_am_current, 44, 'jws-am__avatar' ) : get_avatar( $jws_am_user, 44, '', '', array( 'class' => 'jws-am__avatar' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in avatar_img() / get_avatar(). ?>
		<span class="jws-am__me-text">
			<span class="jws-am__me-name h6">
				<?php echo esc_html( $jws_am_on && $jws_am_current ? $jws_am_current->name : wp_get_current_user()->display_name ); ?>
				<?php if ( $jws_am_kid ) : ?>
					<span class="jws-pf-kids-badge"><?php esc_html_e( 'Kids', 'jws_streamvid' ); ?></span>
				<?php endif; ?>
			</span>
			<?php if ( $jws_am_meta ) : ?>
				<span class="jws-am__me-meta"><?php echo esc_html( implode( ' · ', $jws_am_meta ) ); ?></span>
			<?php endif; ?>
		</span>
	</a>

	<?php if ( $jws_am_on && ( $jws_am_others || $jws_am_manage ) ) : ?>
		<div class="jws-am__profiles">
			<?php if ( $jws_am_others ) : ?>
				<ul class="jws-am__switch" aria-label="<?php esc_attr_e( 'Switch profile', 'jws_streamvid' ); ?>">
					<?php foreach ( $jws_am_others as $jws_am_p ) : ?>
						<li>
							<form method="post" action="<?php echo esc_url( Jws_Profiles_Front::url() ); ?>">
								<?php Jws_Profiles_Front::form_fields( 'switch', $jws_am_p->id ); ?>
								<input type="hidden" name="back" value="<?php echo esc_url( $jws_am_kid ? home_url( '/' ) : $jws_am_here ); ?>">
								<button type="submit" class="jws-am__switch-btn">
									<?php echo Jws_Profiles_Front::avatar_img( $jws_am_p, 36, 'jws-am__avatar jws-am__avatar--sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in avatar_img(). ?>
									<span class="jws-am__switch-name"><?php echo esc_html( $jws_am_p->name ); ?></span>
									<?php if ( $jws_am_p->has_pin ) : ?>
										<i class="jws-icon-lock-key-fill jws-am__lock" aria-hidden="true"></i>
										<span class="screen-reader-text"><?php esc_html_e( '(PIN required)', 'jws_streamvid' ); ?></span>
									<?php endif; ?>
								</button>
							</form>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( $jws_am_manage ) : ?>
				<a class="jws-am__item jws-am__item--manage" href="<?php echo esc_url( Jws_Profiles_Front::url( 'choose', 0, array( 'manage' => 1, 'back' => $jws_am_here ) ) ); ?>">
					<i class="jws-icon-pencil-line" aria-hidden="true"></i>
					<span><?php esc_html_e( 'Manage Profiles', 'jws_streamvid' ); ?></span>
				</a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php foreach ( $jws_am_groups as $jws_am_key => $jws_am_group ) : ?>
		<?php if ( ! $jws_am_group['items'] ) { continue; } ?>
		<div class="jws-am__group">
			<ul class="jws-am__list" aria-label="<?php echo esc_attr( $jws_am_group['label'] ); ?>">
				<?php foreach ( $jws_am_group['items'] as $jws_am_id => $jws_am_item ) : ?>
					<li>
						<a class="jws-am__item item-<?php echo esc_attr( $jws_am_id ); ?>" href="<?php echo esc_url( $jws_am_item['href'] ); ?>"<?php echo $jws_am_item['popup'] ? ' data-modal-jws="' . esc_attr( $jws_am_item['popup'] ) . '"' : ''; ?>>
							<?php if ( $jws_am_item['icon'] ) : ?>
								<i class="<?php echo esc_attr( $jws_am_item['icon'] ); ?>" aria-hidden="true"></i>
							<?php endif; ?>
							<span><?php echo esc_html( $jws_am_item['title'] ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endforeach; ?>

	<div class="jws-am__group jws-am__group--end">
		<?php if ( $jws_am_kid ) : ?>
			<a class="jws-am__item jws-am__item--exit" href="<?php echo esc_url( Jws_Profiles_Front::url( 'choose' ) ); ?>">
				<i class="jws-icon-sign-out" aria-hidden="true"></i>
				<span><?php esc_html_e( 'Exit Kids', 'jws_streamvid' ); ?></span>
			</a>
		<?php elseif ( $jws_am_logout ) : ?>
			<a class="jws-am__item" href="<?php echo esc_url( $jws_am_logout['url'] ); ?>">
				<?php if ( ! empty( $jws_am_logout['icon'] ) ) : ?>
					<i class="<?php echo esc_attr( $jws_am_logout['icon'] ); ?>" aria-hidden="true"></i>
				<?php endif; ?>
				<span><?php echo esc_html( $jws_am_logout['title'] ); ?></span>
			</a>
		<?php endif; ?>
	</div>
</div>
