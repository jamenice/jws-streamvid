<?php
/**
 * The buy panel: VIP plans on top, coin packages under them, payment at the
 * foot.
 *
 * One template for both places it opens from — the lock panel on the watch
 * screen and the Coins tab in the account area — so the shelf, the prices and
 * the wording cannot drift apart between them. What changes is the header:
 * with an episode it shows what that episode costs, without one it is just the
 * balance.
 *
 * @var int $episode_id  Optional. The episode the viewer was trying to open.
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$episode_id = isset( $args['episode_id'] ) ? (int) $args['episode_id'] : 0;
$balance    = Jws_Drama_Wallet::balance();
$price      = 0;

if ( $episode_id ) {
	$access = Jws_Drama_Wallet::access( $episode_id );
	$price  = empty( $access['can_watch'] ) ? (int) $access['price'] : 0;
}

/*
 * VIP comes from Paid Memberships Pro and coins from WooCommerce, so this
 * panel no longer takes money itself: a plan card links to the PMPro checkout,
 * a package card goes through the cart. The module's own Stripe/PayPal layer
 * is still in the codebase for the subscriptions it already sold, but nothing
 * here offers it any more, which is why the gateway buttons are gone.
 */
$plans    = Jws_Drama_Settings::pmpro_plans();
$packages = Jws_Drama_Settings::packages();

/* The account page already is the shelf, so pointing at it from there would be
   a link to the current page. */
$more_url = ( class_exists( 'Jws_Streamvid_Profile' ) && ! is_author() )
	? Jws_Streamvid_Profile::get_url( 'drama-coins' )
	: '';
?>
<div class="sv-buy" data-episode="<?php echo (int) $episode_id; ?>" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr__( 'Get coins', 'jws_streamvid' ); ?>">

	<div class="sv-buy-head">
		<div class="sv-buy-meta">
			<?php if ( $price ) : ?>
				<span><?php echo esc_html__( 'Price:', 'jws_streamvid' ); ?> <?php echo Jws_Drama_Settings::coin_icon_html(); ?><strong><?php echo esc_html( number_format_i18n( $price ) ); ?></strong></span>
				<span class="sv-buy-sep"></span>
			<?php endif; ?>
			<span><?php echo esc_html__( 'Balance:', 'jws_streamvid' ); ?> <?php echo Jws_Drama_Settings::coin_icon_html(); ?><strong class="sv-buy-balance"><?php echo esc_html( number_format_i18n( $balance ) ); ?></strong></span>
		</div>
		<button type="button" class="sv-buy-close" aria-label="<?php echo esc_attr__( 'Close', 'jws_streamvid' ); ?>"><i class="jws-icon-x"></i></button>
	</div>

	<div class="sv-buy-body jws-scrollbar">

		<?php if ( $plans ) : ?>
			<h4 class="sv-buy-title"><?php echo esc_html__( 'VIP Unlock all series for free', 'jws_streamvid' ); ?></h4>
			<p class="sv-buy-sub"><?php echo esc_html__( 'Auto renew. Cancel anytime.', 'jws_streamvid' ); ?></p>

			<div class="sv-buy-plans">
				<?php foreach ( $plans as $plan ) : ?>
					<?php $period = Jws_Drama_Settings::period_phrase( $plan['period'], $plan['cycle'] ); ?>
					<?php
					/*
					 * A link, not a button: PMPro owns the checkout, and its
					 * page is a normal navigation away from here.
					 */
					?>
					<a class="sv-buy-plan"
						href="<?php echo esc_url( $plan['url'] ); ?>"
						data-level="<?php echo (int) $plan['level_id']; ?>">
						<?php if ( $plan['off_percent'] ) : ?>
							<span class="sv-buy-off"><?php printf( esc_html__( '%d%% OFF', 'jws_streamvid' ), (int) $plan['off_percent'] ); ?></span>
						<?php endif; ?>

						<div class="sv-buy-plan-main">
							<span class="sv-buy-plan-name"><?php echo esc_html( $plan['name'] ); ?></span>
							<span class="sv-buy-plan-price">
								<?php echo esc_html( Jws_Drama_Settings::format_level_price( $plan['intro'] ) ); ?>
								<?php if ( $plan['has_intro'] ) : ?>
									<s><?php echo esc_html( Jws_Drama_Settings::format_level_price( $plan['price'] ) ); ?></s>
								<?php endif; ?>
							</span>
							<span class="sv-buy-plan-note">
								<?php
								if ( ! $plan['recurring'] ) {
									echo esc_html__( 'One-off payment. No renewal.', 'jws_streamvid' );
								} elseif ( $plan['has_intro'] ) {
									printf(
										/* translators: 1: intro price, 2: period, 3: renewal price */
										esc_html__( '%1$s for the first %2$s, then %3$s/%2$s. Cancel anytime.', 'jws_streamvid' ),
										esc_html( Jws_Drama_Settings::format_level_price( $plan['intro'] ) ),
										esc_html( $period ),
										esc_html( Jws_Drama_Settings::format_level_price( $plan['price'] ) )
									);
								} else {
									echo esc_html__( 'Auto-renew. Cancel anytime.', 'jws_streamvid' );
								}
								?>
							</span>
						</div>

						<?php if ( $plan['features'] ) : ?>
							<div class="sv-buy-plan-feats">
								<?php foreach ( $plan['features'] as $feature ) : ?>
									<span><i class="jws-icon-check"></i><?php echo esc_html( $feature ); ?></span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( $packages ) : ?>
			<h4 class="sv-buy-title"><?php echo esc_html__( 'Top up coins', 'jws_streamvid' ); ?></h4>

			<div class="sv-buy-packs">
				<?php foreach ( $packages as $package ) : ?>
					<div class="sv-buy-pack"
						data-package="<?php echo (int) $package['index'] ?>"
						data-label="<?php echo esc_attr( sprintf( __( '%s coins', 'jws_streamvid' ), number_format_i18n( $package['total'] ) ) ); ?>"
						role="button" tabindex="0">
						<?php if ( $package['bonus'] ) : ?>
							<span class="sv-buy-off"><?php printf( esc_html__( '+%d%%', 'jws_streamvid' ), (int) $package['bonus_percent'] ); ?></span>
						<?php endif; ?>

						<span class="sv-buy-pack-total"><?php echo Jws_Drama_Settings::coin_icon_html(); ?><?php echo esc_html( number_format_i18n( $package['total'] ) ); ?></span>

						<span class="sv-buy-pack-split">
							<?php
							/* translators: %s: coin amount */
							printf( esc_html__( 'Immediately: %s', 'jws_streamvid' ), esc_html( number_format_i18n( $package['base'] ) ) );
							?>
							<?php if ( $package['bonus'] ) : ?>
								<br />
								<?php
								/* translators: %s: bonus coin amount */
								printf( esc_html__( 'Free: %s', 'jws_streamvid' ), esc_html( number_format_i18n( $package['bonus'] ) ) );
								?>
							<?php endif; ?>
						</span>

						<span class="sv-buy-pack-price"><?php echo esc_html( Jws_Drama_Settings::format_cart_price( $package['price'] ) ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>

		<?php endif; ?>

		<?php if ( ! $plans && ! $packages ) : ?>
			<p class="sv-buy-empty">
				<?php echo esc_html__( 'Nothing is on sale yet.', 'jws_streamvid' ); ?>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<br /><em><?php echo esc_html__( 'Add coin packages in Jws Settings → Drama Coins, and tick the PMPro levels that unlock everything.', 'jws_streamvid' ); ?></em>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<?php if ( $plans || $packages ) : ?>
		
			<p class="sv-buy-error" hidden></p>
		<?php endif; ?>
	</div>
</div>
