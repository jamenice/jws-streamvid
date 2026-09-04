<?php
/**
 * "Coins" tab in the account area: balance, a way in to the shelf, and the
 * statement.
 *
 * The shelf itself lives in purchase-modal.php, which the locked player opens
 * too — one template for both, rather than two that drift.
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$user_id = get_current_user_id();

if ( ! $user_id ) {
	return;
}

$balance = Jws_Drama_Wallet::balance( $user_id );
$history = Jws_Drama_Wallet::history( $user_id, 30 );
$unlocks = Jws_Drama_Wallet::unlock_history( $user_id, 30 );
?>
<div class="sv-coin-wrap">

	<div class="sv-coin-balance">
		<span class="sv-coin-balance-label"><?php echo esc_html__( 'Your balance', 'jws_streamvid' ); ?></span>
		<span class="sv-coin-balance-value">
			<?php echo Jws_Drama_Settings::coin_icon_html(); ?><span class="sv-coin-balance-number"><?php echo esc_html( number_format_i18n( $balance ) ); ?></span>
		</span>
		<?php
		/*
		 * The shelf itself is not printed here any more. Top up opens the same
		 * panel the locked player opens, so the prices, the bonus maths and the
		 * payment buttons exist in one template instead of two that drift.
		 */
		?>
		<button type="button" class="sv-coin-topup button-default" data-jws-coin-modal>
			<span><?php echo esc_html__( 'Top up', 'jws_streamvid' ); ?></span>
		</button>
	</div>

	<div class="sv-coin-tabs" role="tablist">
		<button type="button" class="sv-coin-tab active" data-tab="history" role="tab" aria-selected="true">
			<?php echo esc_html__( 'Transaction History', 'jws_streamvid' ); ?>
		</button>
		<button type="button" class="sv-coin-tab" data-tab="unlocks" role="tab" aria-selected="false">
			<?php echo esc_html__( 'Episodes Unlocked', 'jws_streamvid' ); ?>
		</button>
	</div>

	<div class="sv-coin-tab-panel" data-tab-panel="history">
		<?php if ( $history ) : ?>
			<table class="sv-coin-table">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'When', 'jws_streamvid' ); ?></th>
						<th><?php echo esc_html__( 'Detail', 'jws_streamvid' ); ?></th>
						<th><?php echo esc_html__( 'Change', 'jws_streamvid' ); ?></th>
						<th><?php echo esc_html__( 'Balance', 'jws_streamvid' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $history as $row ) : ?>
					<tr>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $row->created_at ) ) ); ?></td>
						<td>
							<?php
							/* A spend always references the episode it opened. */
							if ( 'spend' === $row->type && $row->ref_id && get_post( $row->ref_id ) ) {
								printf(
									'<a href="%s">%s</a>',
									esc_url( get_permalink( $row->ref_id ) ),
									esc_html( get_the_title( $row->ref_id ) )
								);
							} else {
								echo esc_html( $row->note ? $row->note : $row->type );
							}
							?>
						</td>
						<td class="<?php echo $row->delta < 0 ? 'is-out' : 'is-in'; ?>">
							<?php echo esc_html( ( $row->delta > 0 ? '+' : '' ) . number_format_i18n( $row->delta ) ); ?>
						</td>
						<td><?php echo esc_html( number_format_i18n( $row->balance_after ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p class="sv-coin-empty"><?php echo esc_html__( 'Nothing here yet.', 'jws_streamvid' ); ?></p>
		<?php endif; ?>
	</div>

	<div class="sv-coin-tab-panel" data-tab-panel="unlocks" hidden>
		<?php if ( $unlocks ) : ?>
			<table class="sv-coin-table">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Episode Name', 'jws_streamvid' ); ?></th>
						<th><?php echo esc_html__( 'Episode', 'jws_streamvid' ); ?></th>
						<th><?php echo esc_html__( 'Unlock time', 'jws_streamvid' ); ?></th>
						<th><?php echo esc_html__( 'Coins', 'jws_streamvid' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $unlocks as $row ) : ?>
					<?php
					$episode_id = (int) $row->ref_id;
					$drama_id   = $episode_id ? Jws_Drama_Wallet::drama_id_of( $episode_id ) : 0;
					?>
					<tr>
						<td>
							<?php if ( $drama_id && get_post( $drama_id ) ) : ?>
								<a href="<?php echo esc_url( get_permalink( $drama_id ) ); ?>"><?php echo esc_html( get_the_title( $drama_id ) ); ?></a>
							<?php else : ?>
								&#8212;
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $episode_id && get_post( $episode_id ) ) : ?>
								<a href="<?php echo esc_url( get_permalink( $episode_id ) ); ?>">
									<?php
									printf(
										/* translators: %d: episode number */
										esc_html__( 'Episode %d', 'jws_streamvid' ),
										(int) Jws_Drama_Wallet::episode_number( $episode_id )
									);
									?>
								</a>
							<?php else : ?>
								&#8212;
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $row->created_at ) ) ); ?></td>
						<td class="is-out"><?php echo esc_html( number_format_i18n( abs( $row->delta ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p class="sv-coin-empty"><?php echo esc_html__( 'No episodes unlocked yet.', 'jws_streamvid' ); ?></p>
		<?php endif; ?>
	</div>
</div>
