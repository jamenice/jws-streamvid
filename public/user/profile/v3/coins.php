<?php
/**
 * Profile page V3: Coins. Balance with Top up (the existing coin modal),
 * two figures for this month, then the statement or the unlocked episodes.
 *
 * The tabs are links (?coins=unlocks), so they work without the drama
 * script; Top up still needs it and loads it through Jws_Drama_Templates.
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jws_coin_user = get_current_user_id();

if ( ! $jws_coin_user || ! class_exists( 'Jws_Drama_Wallet' ) ) {
	return;
}

if ( class_exists( 'Jws_Drama_Templates' ) ) {
	Jws_Drama_Templates::assets();
}

global $wpdb;

$jws_coin_table   = Jws_Drama_Install::table_wallet_log();
$jws_coin_month   = gmdate( 'Y-m-01 00:00:00', current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- the ledger stores site time.
$jws_coin_spent   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE( SUM( -delta ), 0 ) FROM {$jws_coin_table} WHERE user_id = %d AND type = %s AND created_at >= %s", $jws_coin_user, 'spend', $jws_coin_month ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$jws_coin_count   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$jws_coin_table} WHERE user_id = %d AND type = %s", $jws_coin_user, 'spend' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$jws_coin_tab     = isset( $_GET['coins'] ) && 'unlocks' === sanitize_key( wp_unslash( $_GET['coins'] ) ) ? 'unlocks' : 'history'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$jws_coin_page    = Jws_Streamvid_Profile::get_url( 'drama-coins' );
$jws_coin_format  = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
?>
<div class="jws-coin">

	<div class="jws-coin__top">
		<section class="jws-coin__balance">
			<span class="jws-coin__icon" aria-hidden="true"><?php echo Jws_Drama_Settings::coin_icon_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from the drama settings, escaped there. ?></span>
			<span class="jws-coin__balance-text">
				<span class="jws-coin__label"><?php esc_html_e( 'Your balance', 'jws_streamvid' ); ?></span>
				<span class="jws-coin__value h4 sv-coin-balance-number"><?php echo esc_html( number_format_i18n( Jws_Drama_Wallet::balance( $jws_coin_user ) ) ); ?></span>
			</span>
			<button type="button" class="button-default" data-jws-coin-modal>
				<i class="jws-icon-plus" aria-hidden="true"></i>
				<?php esc_html_e( 'Top up', 'jws_streamvid' ); ?>
			</button>
		</section>
		<section class="jws-coin__stat">
			<span class="jws-coin__label"><?php esc_html_e( 'Spent this month', 'jws_streamvid' ); ?></span>
			<span class="jws-coin__stat-value h5">
				<?php
				/* translators: %s: number of coins */
				echo esc_html( sprintf( _n( '%s coin', '%s coins', $jws_coin_spent, 'jws_streamvid' ), number_format_i18n( $jws_coin_spent ) ) );
				?>
			</span>
		</section>
		<section class="jws-coin__stat">
			<span class="jws-coin__label"><?php esc_html_e( 'Episodes unlocked', 'jws_streamvid' ); ?></span>
			<span class="jws-coin__stat-value h5"><?php echo esc_html( number_format_i18n( $jws_coin_count ) ); ?></span>
		</section>
	</div>

	<nav class="jws-pv3__tabs" aria-label="<?php esc_attr_e( 'Coin activity', 'jws_streamvid' ); ?>">
		<a class="jws-pv3__tab<?php echo 'history' === $jws_coin_tab ? ' is-active' : ''; ?>" href="<?php echo esc_url( $jws_coin_page ); ?>"<?php echo 'history' === $jws_coin_tab ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Transactions', 'jws_streamvid' ); ?></a>
		<a class="jws-pv3__tab<?php echo 'unlocks' === $jws_coin_tab ? ' is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'coins', 'unlocks', $jws_coin_page ) ); ?>"<?php echo 'unlocks' === $jws_coin_tab ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Episodes Unlocked', 'jws_streamvid' ); ?></a>
	</nav>

	<?php if ( 'history' === $jws_coin_tab ) : ?>
		<?php $jws_coin_rows = Jws_Drama_Wallet::history( $jws_coin_user, 30 ); ?>
		<?php if ( $jws_coin_rows ) : ?>
			<ul class="jws-coin__list">
				<?php foreach ( $jws_coin_rows as $jws_coin_row ) : ?>
					<?php $jws_coin_ref = 'spend' === $jws_coin_row->type && $jws_coin_row->ref_id && get_post( $jws_coin_row->ref_id ) ? (int) $jws_coin_row->ref_id : 0; ?>
					<li class="jws-coin__row">
						<span class="jws-coin__what">
							<span class="jws-coin__detail h6">
								<?php if ( $jws_coin_ref ) : ?>
									<a href="<?php echo esc_url( get_permalink( $jws_coin_ref ) ); ?>"><?php echo esc_html( get_the_title( $jws_coin_ref ) ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $jws_coin_row->note ? $jws_coin_row->note : ucfirst( $jws_coin_row->type ) ); ?>
								<?php endif; ?>
							</span>
							<span class="jws-coin__when"><?php echo esc_html( date_i18n( $jws_coin_format, strtotime( $jws_coin_row->created_at ) ) ); ?></span>
						</span>
						<span class="jws-coin__amount">
							<span class="h6 jws-coin__delta<?php echo $jws_coin_row->delta < 0 ? ' is-out' : ' is-in'; ?>"><?php echo esc_html( ( $jws_coin_row->delta > 0 ? '+' : '' ) . number_format_i18n( $jws_coin_row->delta ) ); ?></span>
							<span class="jws-coin__after">
								<?php
								/* translators: %s: coin balance after this line */
								echo esc_html( sprintf( __( 'Balance %s', 'jws_streamvid' ), number_format_i18n( $jws_coin_row->balance_after ) ) );
								?>
							</span>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="jws-coin__empty"><?php esc_html_e( 'No coin activity yet.', 'jws_streamvid' ); ?></p>
		<?php endif; ?>
	<?php else : ?>
		<?php $jws_coin_rows = Jws_Drama_Wallet::unlock_history( $jws_coin_user, 30 ); ?>
		<?php if ( $jws_coin_rows ) : ?>
			<ul class="jws-coin__grid">
				<?php foreach ( $jws_coin_rows as $jws_coin_row ) : ?>
					<?php
					$jws_coin_ep    = (int) $jws_coin_row->ref_id;
					$jws_coin_drama = $jws_coin_ep ? (int) Jws_Drama_Wallet::drama_id_of( $jws_coin_ep ) : 0;
					if ( ! $jws_coin_ep || ! get_post( $jws_coin_ep ) ) {
						continue;
					}
					$jws_coin_img = $jws_coin_drama ? get_the_post_thumbnail_url( $jws_coin_drama, 'medium' ) : '';
					?>
					<li>
						<a class="jws-lib__card" href="<?php echo esc_url( get_permalink( $jws_coin_ep ) ); ?>">
							<span class="jws-lib__art jws-coin__poster">
								<?php if ( $jws_coin_img ) : ?>
									<img src="<?php echo esc_url( $jws_coin_img ); ?>" alt="" loading="lazy" decoding="async">
								<?php endif; ?>
							</span>
							<h6 class="jws-lib__title fs-small"><?php echo esc_html( $jws_coin_drama ? get_the_title( $jws_coin_drama ) : get_the_title( $jws_coin_ep ) ); ?></h6>
							<span class="jws-lib__meta fs-small">
								<?php
								/* translators: 1: episode number, 2: coins spent */
								echo esc_html( sprintf( __( 'Episode %1$d · %2$s coins', 'jws_streamvid' ), (int) Jws_Drama_Wallet::episode_number( $jws_coin_ep ), number_format_i18n( abs( $jws_coin_row->delta ) ) ) );
								?>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="jws-coin__empty"><?php esc_html_e( 'No episodes unlocked yet.', 'jws_streamvid' ); ?></p>
		<?php endif; ?>
	<?php endif; ?>
</div>
