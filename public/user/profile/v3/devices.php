<?php
/**
 * Profile page V3: devices signed in to the account, the plan's device
 * limit, and signing devices out (Jws_Profile_Library::handle_devices_post).
 *
 * The list is the theme's device manager data (user meta jws_user_devices);
 * this browser is the one whose jws_device_id cookie matches.
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jws_dev_user    = get_current_user_id();
$jws_dev_all     = get_user_meta( $jws_dev_user, 'jws_user_devices', true );
$jws_dev_all     = is_array( $jws_dev_all ) ? $jws_dev_all : array();
$jws_dev_cookie  = isset( $_COOKIE['jws_device_id'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['jws_device_id'] ) ) : '';
$jws_dev_limit   = function_exists( 'jws_get_device_limit_for_user' ) ? (int) jws_get_device_limit_for_user( $jws_dev_user ) : 0;
$jws_dev_notice  = Jws_Profile_Library::notice();
$jws_dev_now     = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- the theme stores last_active this way.
$jws_dev_this    = null;
$jws_dev_others  = array();

foreach ( $jws_dev_all as $jws_dev_item ) {
	if ( ! isset( $jws_dev_item['device_id'] ) ) {
		continue;
	}
	if ( '' !== $jws_dev_cookie && (string) $jws_dev_item['device_id'] === $jws_dev_cookie ) {
		$jws_dev_this = $jws_dev_item;
	} else {
		$jws_dev_others[] = $jws_dev_item;
	}
}

usort( $jws_dev_others, function ( $a, $b ) {
	return (int) ( isset( $b['last_active'] ) ? $b['last_active'] : 0 ) <=> (int) ( isset( $a['last_active'] ) ? $a['last_active'] : 0 );
} );

$jws_dev_name = function ( $device ) {
	if ( ! empty( $device['browser'] ) && ! empty( $device['os'] ) && 'Unknown Browser' !== $device['browser'] ) {
		/* translators: 1: browser or app, 2: operating system */
		return sprintf( __( '%1$s on %2$s', 'jws_streamvid' ), $device['browser'], $device['os'] );
	}
	return ! empty( $device['device_name'] ) ? $device['device_name'] : __( 'Unknown device', 'jws_streamvid' );
};

$jws_dev_seen = function ( $device ) use ( $jws_dev_now ) {
	$last = isset( $device['last_active'] ) ? (int) $device['last_active'] : 0;
	if ( ! $last ) {
		return '';
	}
	if ( $jws_dev_now - $last < 5 * MINUTE_IN_SECONDS ) {
		return __( 'Active now', 'jws_streamvid' );
	}
	/* translators: %s: time since, such as "3 hours" */
	return sprintf( __( 'Last active %s ago', 'jws_streamvid' ), human_time_diff( $last, $jws_dev_now ) );
};

$jws_dev_icon = function ( $device ) {
	$icon = isset( $device['icon'] ) ? (string) $device['icon'] : '';
	return preg_match( '/^jws-icon-[a-z0-9-]+$/', $icon ) ? $icon : 'jws-icon-devices';
};
?>
<div class="jws-dev">
	<p class="jws-lib__sub"><?php esc_html_e( 'Devices signed in to this account. Sign out any device you don\'t recognise, then change your password.', 'jws_streamvid' ); ?></p>

	<?php if ( $jws_dev_notice ) : ?>
		<p class="jws-pf-msg" role="status"><?php echo esc_html( $jws_dev_notice ); ?></p>
	<?php endif; ?>

	<?php if ( $jws_dev_limit ) : ?>
		<?php $jws_dev_used = count( $jws_dev_all ); ?>
		<section class="jws-dev__limit">
			<span class="jws-sub__meter-row">
				<strong><?php esc_html_e( 'Device limit', 'jws_streamvid' ); ?></strong>
				<span>
					<?php
					/* translators: 1: devices signed in, 2: devices allowed */
					echo esc_html( sprintf( __( '%1$d of %2$d devices', 'jws_streamvid' ), $jws_dev_used, $jws_dev_limit ) );
					?>
				</span>
			</span>
			<span class="jws-pf-meter"><span class="jws-pf-meter__fill" style="width: <?php echo (int) min( 100, round( 100 * $jws_dev_used / max( 1, $jws_dev_limit ) ) ); ?>%"></span></span>
			<span class="jws-dev__hint"><?php esc_html_e( 'The limit comes from your membership plan. New sign-ins are blocked when it is full.', 'jws_streamvid' ); ?></span>
		</section>
	<?php endif; ?>

	<?php if ( $jws_dev_this ) : ?>
		<section class="jws-dev__section">
			<h2 class="jws-pf-section__title"><?php esc_html_e( 'This device', 'jws_streamvid' ); ?></h2>
			<div class="jws-dev__row jws-dev__row--this">
				<span class="jws-dev__icon"><i class="<?php echo esc_attr( $jws_dev_icon( $jws_dev_this ) ); ?>" aria-hidden="true"></i></span>
				<span class="jws-dev__text">
					<span class="jws-dev__name"><?php echo esc_html( $jws_dev_name( $jws_dev_this ) ); ?></span>
					<span class="jws-dev__seen"><?php echo esc_html( $jws_dev_seen( $jws_dev_this ) ); ?></span>
				</span>
				<span class="jws-dev__badge"><?php esc_html_e( 'This device', 'jws_streamvid' ); ?></span>
			</div>
		</section>
	<?php endif; ?>

	<section class="jws-dev__section">
		<h2 class="jws-pf-section__title"><?php esc_html_e( 'Other devices', 'jws_streamvid' ); ?></h2>
		<?php if ( $jws_dev_others ) : ?>
			<form method="post" class="jws-dev__form">
				<?php wp_nonce_field( 'jws_devices', '_jws_devices_nonce' ); ?>
				<ul class="jws-coin__list">
					<?php foreach ( $jws_dev_others as $jws_dev_item ) : ?>
						<li class="jws-dev__row">
							<span class="jws-dev__icon"><i class="<?php echo esc_attr( $jws_dev_icon( $jws_dev_item ) ); ?>" aria-hidden="true"></i></span>
							<span class="jws-dev__text">
								<span class="jws-dev__name"><?php echo esc_html( $jws_dev_name( $jws_dev_item ) ); ?></span>
								<span class="jws-dev__seen"><?php echo esc_html( $jws_dev_seen( $jws_dev_item ) ); ?></span>
							</span>
							<button type="submit" name="sign_out" value="<?php echo esc_attr( $jws_dev_item['device_id'] ); ?>" class="jws-pf-btn jws-pf-btn--ghost jws-dev__out">
								<?php esc_html_e( 'Sign out', 'jws_streamvid' ); ?>
								<span class="screen-reader-text"><?php echo esc_html( $jws_dev_name( $jws_dev_item ) ); ?></span>
							</button>
						</li>
					<?php endforeach; ?>
				</ul>
				<button type="submit" name="sign_out_others" value="1" class="jws-pf-btn jws-pf-btn--danger-ghost jws-dev__all"><?php esc_html_e( 'Sign out of all other devices', 'jws_streamvid' ); ?></button>
			</form>
		<?php else : ?>
			<p class="jws-coin__empty" role="status"><?php esc_html_e( 'No other devices are signed in.', 'jws_streamvid' ); ?></p>
		<?php endif; ?>
	</section>
</div>
