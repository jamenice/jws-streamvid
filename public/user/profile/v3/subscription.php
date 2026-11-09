<?php
/**
 * Profile page V3: Subscription. Current plan with renewal, Change plan and
 * Cancel membership; how many profiles and devices the plan uses; billing;
 * membership invoices with a PDF download.
 *
 * Built on Paid Memberships Pro (3.x: PMPro_Subscription for the renewal
 * date and cost). Cancelling goes to PMPro's own cancel page, which asks
 * for confirmation.
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$jws_sub_user   = get_current_user_id();
$jws_sub_level  = pmpro_getMembershipLevelForUser( $jws_sub_user );
$jws_sub_upsell = function_exists( 'jws_theme_get_option' ) ? (int) jws_theme_get_option( 'select-update-level' ) : 0;
$jws_sub_plans  = $jws_sub_upsell ? get_permalink( $jws_sub_upsell ) : pmpro_url( 'levels' );
$jws_sub_date   = get_option( 'date_format' );
$jws_sub_cost   = '';
$jws_sub_next   = 0;

// A free level (some sites give one to every new member) reads like no plan: upgrade, nothing to cancel.
$jws_sub_free = $jws_sub_level && function_exists( 'pmpro_isLevelFree' ) && pmpro_isLevelFree( $jws_sub_level );

if ( $jws_sub_level ) {
	if ( class_exists( 'PMPro_Subscription' ) ) {
		$jws_sub_subs = PMPro_Subscription::get_subscriptions_for_user( $jws_sub_user, $jws_sub_level->id );
		if ( $jws_sub_subs ) {
			$jws_sub_cost = wp_strip_all_tags( $jws_sub_subs[0]->get_cost_text() );
			$jws_sub_next = (int) $jws_sub_subs[0]->get_next_payment_date( 'timestamp', true );
		}
	}
	if ( '' === $jws_sub_cost ) {
		$jws_sub_cost = wp_strip_all_tags( pmpro_getLevelCost( $jws_sub_level, true, true ) );
	}
}

// What the plan allows next to what the account uses.
$jws_sub_usage = array(
	array(
		'label' => __( 'Profiles', 'jws_streamvid' ),
		'used'  => Jws_Profiles::count( $jws_sub_user ),
		'limit' => Jws_Profiles::get_limit( $jws_sub_user ),
	),
);
if ( function_exists( 'jws_get_device_limit_for_user' ) && function_exists( 'jws_theme_get_option' ) && '1' === (string) jws_theme_get_option( 'diveces_management', '0' ) ) {
	$jws_sub_devices = get_user_meta( $jws_sub_user, 'jws_user_devices', true );
	$jws_sub_usage[] = array(
		'label' => __( 'Devices', 'jws_streamvid' ),
		'used'  => is_array( $jws_sub_devices ) ? count( $jws_sub_devices ) : 0,
		'limit' => (int) jws_get_device_limit_for_user( $jws_sub_user ),
	);
}

// The card on the latest paid order, for the billing line.
$jws_sub_orders = $wpdb->get_results( $wpdb->prepare(
	"SELECT id, code, membership_id, total, status, timestamp, cardtype, accountnumber FROM {$wpdb->pmpro_membership_orders}
	 WHERE user_id = %d AND status NOT IN ('error', 'token', 'review') ORDER BY timestamp DESC, id DESC LIMIT 12",
	$jws_sub_user
) );
$jws_sub_card = '';
foreach ( $jws_sub_orders as $jws_sub_order ) {
	if ( $jws_sub_order->accountnumber ) {
		/* translators: 1: card brand, 2: last four digits */
		$jws_sub_card = trim( sprintf( __( 'Paid with %1$s ending %2$s', 'jws_streamvid' ), $jws_sub_order->cardtype, substr( $jws_sub_order->accountnumber, -4 ) ) );
		break;
	}
}

$jws_sub_status = array(
	'success'   => array( 'paid', __( 'Paid', 'jws_streamvid' ) ),
	'cancelled' => array( 'paid', __( 'Paid', 'jws_streamvid' ) ),
	''          => array( 'paid', __( 'Paid', 'jws_streamvid' ) ),
	'pending'   => array( 'pending', __( 'Pending', 'jws_streamvid' ) ),
	'refunded'  => array( 'refunded', __( 'Refunded', 'jws_streamvid' ) ),
);
?>
<div class="jws-sub">

	<?php if ( $jws_sub_level && ! $jws_sub_free ) : ?>
		<section class="jws-sub__plan">
			<div class="jws-sub__plan-head">
				<div class="jws-sub__plan-text">
					<span class="jws-sub__eyebrow"><?php esc_html_e( 'Current plan', 'jws_streamvid' ); ?></span>
					<span class="jws-sub__name h4">
						<?php echo esc_html( $jws_sub_level->name ); ?>
						<span class="jws-buy__chip jws-buy__chip--active"><?php esc_html_e( 'Active', 'jws_streamvid' ); ?></span>
					</span>
					<span class="jws-sub__cost">
						<?php
						echo esc_html( $jws_sub_cost );
						if ( $jws_sub_next ) {
							/* translators: %s: date */
							echo ' · ' . esc_html( sprintf( __( 'Renews on %s', 'jws_streamvid' ), date_i18n( $jws_sub_date, $jws_sub_next ) ) );
						} elseif ( ! empty( $jws_sub_level->enddate ) ) {
							/* translators: %s: date */
							echo ' · ' . esc_html( sprintf( __( 'Ends on %s', 'jws_streamvid' ), date_i18n( $jws_sub_date, (int) $jws_sub_level->enddate ) ) );
						}
						?>
					</span>
				</div>
				<div class="jws-sub__actions">
					<a class="button-default" href="<?php echo esc_url( $jws_sub_plans ); ?>"><?php esc_html_e( 'Change plan', 'jws_streamvid' ); ?></a>
					<a class="button-custom jws-btn-danger" href="<?php echo esc_url( pmpro_url( 'cancel', '?levelstocancel=' . (int) $jws_sub_level->id ) ); ?>"><?php esc_html_e( 'Cancel membership', 'jws_streamvid' ); ?></a>
				</div>
			</div>
	<?php else : ?>
		<section class="jws-sub__plan">
			<div class="jws-sub__plan-head">
				<div class="jws-sub__plan-text">
					<span class="jws-sub__eyebrow"><?php esc_html_e( 'Current plan', 'jws_streamvid' ); ?></span>
					<span class="jws-sub__name h4"><?php echo $jws_sub_level ? esc_html( $jws_sub_level->name ) : esc_html__( 'Free Plan', 'jws_streamvid' ); ?></span>
					<span class="jws-sub__cost"><?php esc_html_e( 'Upgrade to watch premium titles, add more profiles and sign in on more devices.', 'jws_streamvid' ); ?></span>
				</div>
				<div class="jws-sub__actions">
					<a class="button-default" href="<?php echo esc_url( $jws_sub_plans ); ?>">
						<i class="jws-icon-crown-simple" aria-hidden="true"></i>
						<?php esc_html_e( 'View all Membership Options', 'jws_streamvid' ); ?>
					</a>
				</div>
			</div>
	<?php endif; ?>

			<div class="jws-sub__usage">
				<?php foreach ( $jws_sub_usage as $jws_sub_meter ) : ?>
					<?php $jws_sub_pct = $jws_sub_meter['limit'] > 0 ? min( 100, round( 100 * $jws_sub_meter['used'] / $jws_sub_meter['limit'] ) ) : 0; ?>
					<div class="jws-sub__meter">
						<span class="jws-sub__meter-row">
							<span><?php echo esc_html( $jws_sub_meter['label'] ); ?></span>
							<strong>
								<?php
								/* translators: 1: used, 2: allowed */
								echo esc_html( sprintf( __( '%1$d of %2$d', 'jws_streamvid' ), $jws_sub_meter['used'], $jws_sub_meter['limit'] ) );
								?>
							</strong>
						</span>
						<span class="jws-pf-meter"><span class="jws-pf-meter__fill" style="width: <?php echo (int) $jws_sub_pct; ?>%"></span></span>
					</div>
				<?php endforeach; ?>
			</div>
		</section>

	<section class="jws-sub__billing">
		<i class="jws-icon-wallet" aria-hidden="true"></i>
		<span class="jws-sub__billing-text">
			<span class="jws-sub__billing-title h6"><?php esc_html_e( 'Billing', 'jws_streamvid' ); ?></span>
			<span class="jws-lib__sub"><?php echo esc_html( $jws_sub_card ? $jws_sub_card : __( 'No payment method on file', 'jws_streamvid' ) ); ?></span>
		</span>
		<?php if ( $jws_sub_level && pmpro_isLevelRecurring( $jws_sub_level ) ) : ?>
			<a class="jws-sub__billing-link" href="<?php echo esc_url( pmpro_url( 'billing' ) ); ?>"><?php esc_html_e( 'Update billing information', 'jws_streamvid' ); ?></a>
		<?php endif; ?>
	</section>

	<section class="jws-sub__invoices">
		<h5 class="jws-pf-section__title"><?php esc_html_e( 'Invoices', 'jws_streamvid' ); ?></h5>
		<?php if ( $jws_sub_orders ) : ?>
			<ul class="jws-coin__list">
				<?php foreach ( $jws_sub_orders as $jws_sub_order ) : ?>
					<?php
					$jws_sub_order_level = pmpro_getLevel( $jws_sub_order->membership_id );
					$jws_sub_chip        = isset( $jws_sub_status[ $jws_sub_order->status ] ) ? $jws_sub_status[ $jws_sub_order->status ] : array( 'pending', ucfirst( $jws_sub_order->status ) );
					?>
					<li class="jws-coin__row">
						<span class="jws-coin__what">
							<span class="jws-coin__detail h6"><?php echo esc_html( $jws_sub_order_level ? $jws_sub_order_level->name : $jws_sub_order->code ); ?></span>
							<span class="jws-coin__when">
								<?php echo esc_html( date_i18n( $jws_sub_date, strtotime( $jws_sub_order->timestamp ) ) ); ?>
								· <?php echo esc_html( wp_strip_all_tags( pmpro_formatPrice( $jws_sub_order->total ) ) ); ?>
							</span>
						</span>
						<span class="jws-buy__chip jws-buy__chip--<?php echo 'paid' === $jws_sub_chip[0] ? 'owned' : ( 'refunded' === $jws_sub_chip[0] ? 'expired' : 'pending' ); ?>"><?php echo esc_html( $jws_sub_chip[1] ); ?></span>
						<button type="button" class="button-custom jws-btn-sm jws-sub__pdf download-invoice-pmpro" data-invoice="<?php echo esc_attr( $jws_sub_order->code ); ?>">
							<i class="jws-icon-arrow-line-down" aria-hidden="true"></i>
							<?php esc_html_e( 'PDF', 'jws_streamvid' ); ?>
							<span class="screen-reader-text">
								<?php
								/* translators: %s: order code */
								echo esc_html( sprintf( __( 'invoice %s', 'jws_streamvid' ), $jws_sub_order->code ) );
								?>
							</span>
						</button>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php jws_streamvid_load_template( 'user/profile/parts/invoice-download-pmpro.php', false ); ?>
		<?php else : ?>
			<p class="jws-coin__empty"><?php esc_html_e( 'No invoices yet.', 'jws_streamvid' ); ?></p>
		<?php endif; ?>
	</section>
</div>
