<?php
/**
 * Profile page V3: titles bought (type buy, Pay Per View tab) or rented
 * (type rent, Rented tab) on the account, as cards with their state.
 *
 * Purchases belong to the account, so every profile sees the same list; a
 * title the profile in use may not watch has no Watch button here.
 *
 * @package Jws_Streamvid
 *
 * @var array $args { @type string $type buy or rent. }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! jws_streamvid()->get()->profile->_profile_is_owner() || ! class_exists( 'Jws_PPV_Access' ) ) {
	return;
}

$jws_buy_type = isset( $args['type'] ) && 'rent' === $args['type'] ? Jws_PPV_Access::TYPE_RENT : Jws_PPV_Access::TYPE_BUY;
$jws_buy_rows = Jws_PPV_Access::list_for_user( get_current_user_id(), $jws_buy_type );

if ( $jws_buy_rows ) {
	_prime_post_caches( wp_list_pluck( $jws_buy_rows, 'post_id' ), false, true );
}

$jws_buy_date  = get_option( 'date_format' );
$jws_buy_money = function ( $row ) {
	if ( (float) $row->price <= 0 ) {
		return '';
	}
	if ( function_exists( 'wc_price' ) ) {
		return html_entity_decode( wp_strip_all_tags( wc_price( $row->price, $row->currency ? array( 'currency' => $row->currency ) : array() ) ), ENT_QUOTES, 'UTF-8' );
	}
	return trim( number_format_i18n( (float) $row->price, 2 ) . ' ' . $row->currency );
};

$jws_buy_items = array();

foreach ( $jws_buy_rows as $jws_buy_row ) {

	$jws_buy_id = (int) $jws_buy_row->post_id;

	if ( 'publish' !== get_post_status( $jws_buy_id ) ) {
		continue;
	}

	$jws_buy_when = date_i18n( $jws_buy_date, strtotime( $jws_buy_row->purchased_at ) );
	$jws_buy_paid = $jws_buy_money( $jws_buy_row );

	if ( Jws_PPV_Access::TYPE_RENT === $jws_buy_type ) {
		$jws_buy_state = Jws_PPV_Access::rent_state( $jws_buy_row );

		if ( 'pending' === $jws_buy_state['status'] ) {
			$jws_buy_chip  = array( 'pending', __( 'Not started', 'jws_streamvid' ) );
			$jws_buy_deadline = strtotime( $jws_buy_row->purchased_at ) + (int) $jws_buy_row->delay_days * DAY_IN_SECONDS;
			/* translators: 1: date the rental must start by, 2: number of days to finish */
			$jws_buy_note  = sprintf( _n( 'Start by %1$s. You then have %2$d day to finish.', 'Start by %1$s. You then have %2$d days to finish.', (int) $jws_buy_row->rent_days, 'jws_streamvid' ), date_i18n( $jws_buy_date, $jws_buy_deadline ), (int) $jws_buy_row->rent_days );
			$jws_buy_watch = __( 'Start watching', 'jws_streamvid' );
		} elseif ( 'active' === $jws_buy_state['status'] ) {
			$jws_buy_chip  = array( 'active', __( 'Active', 'jws_streamvid' ) );
			/* translators: %s: time left, such as "1 day" */
			// Same clock as Jws_PPV_Access::rent_state(): site-time strings against current_time( 'timestamp' ).
			$jws_buy_note  = sprintf( __( 'Expires in %s', 'jws_streamvid' ), human_time_diff( current_time( 'timestamp' ), strtotime( $jws_buy_state['expires_at'] ) ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			$jws_buy_watch = __( 'Continue', 'jws_streamvid' );
		} else {
			$jws_buy_chip  = array( 'expired', __( 'Expired', 'jws_streamvid' ) );
			/* translators: %s: date */
			$jws_buy_note  = sprintf( __( 'Rental ended on %s', 'jws_streamvid' ), date_i18n( $jws_buy_date, strtotime( $jws_buy_state['expires_at'] ) ) );
			$jws_buy_watch = '';
		}

		$jws_buy_line = $jws_buy_paid
			/* translators: 1: price, 2: date */
			? sprintf( __( 'Rented for %1$s on %2$s', 'jws_streamvid' ), $jws_buy_paid, $jws_buy_when )
			/* translators: %s: date */
			: sprintf( __( 'Rented on %s', 'jws_streamvid' ), $jws_buy_when );
	} else {
		$jws_buy_chip  = array( 'owned', __( 'Owned', 'jws_streamvid' ) );
		$jws_buy_note  = __( 'Yours to keep. Watch any time.', 'jws_streamvid' );
		$jws_buy_watch = __( 'Watch', 'jws_streamvid' );
		$jws_buy_line  = $jws_buy_paid
			/* translators: 1: price, 2: date */
			? sprintf( __( 'Bought for %1$s on %2$s', 'jws_streamvid' ), $jws_buy_paid, $jws_buy_when )
			/* translators: %s: date */
			: sprintf( __( 'Bought on %s', 'jws_streamvid' ), $jws_buy_when );
	}

	if ( $jws_buy_row->order_number ) {
		/* translators: %s: order number */
		$jws_buy_line .= ' · ' . sprintf( __( 'Order %s', 'jws_streamvid' ), $jws_buy_row->order_number );
	}

	$jws_buy_thumb = get_post_meta( $jws_buy_id, 'featured_image_two', true );
	$jws_buy_thumb = $jws_buy_thumb ? $jws_buy_thumb : get_post_thumbnail_id( $jws_buy_id );

	$jws_buy_items[] = array(
		'id'       => $jws_buy_id,
		'title'    => get_the_title( $jws_buy_id ),
		'url'      => get_permalink( $jws_buy_id ),
		'image'    => $jws_buy_thumb ? wp_get_attachment_image_url( $jws_buy_thumb, 'medium' ) : '',
		'chip'     => $jws_buy_chip,
		'note'     => $jws_buy_note,
		'line'     => $jws_buy_line,
		'watch'    => Jws_Profile_Filter::can_view( $jws_buy_id ) ? $jws_buy_watch : '',
		'blocked'  => ! Jws_Profile_Filter::can_view( $jws_buy_id ),
		'again'    => 'expired' === $jws_buy_chip[0],
		'order'    => (string) $jws_buy_row->order_number,
	);
}
?>
<div class="jws-buy">
	<p class="jws-lib__sub"><?php esc_html_e( 'Titles bought on this account. Every profile can watch them, except profiles whose rating is lower than the title.', 'jws_streamvid' ); ?></p>

	<?php if ( $jws_buy_items ) : ?>
		<ul class="jws-buy__list">
			<?php foreach ( $jws_buy_items as $jws_buy_item ) : ?>
				<li class="jws-buy__item">
					<a class="jws-buy__thumb" href="<?php echo esc_url( $jws_buy_item['url'] ); ?>" tabindex="-1" aria-hidden="true">
						<?php if ( $jws_buy_item['image'] ) : ?>
							<img src="<?php echo esc_url( $jws_buy_item['image'] ); ?>" alt="" loading="lazy" decoding="async">
						<?php endif; ?>
					</a>
					<div class="jws-buy__info">
						<div class="jws-buy__title-row">
							<a class="jws-buy__title h6" href="<?php echo esc_url( $jws_buy_item['url'] ); ?>"><?php echo esc_html( $jws_buy_item['title'] ); ?></a>
							<span class="jws-buy__chip jws-buy__chip--<?php echo esc_attr( $jws_buy_item['chip'][0] ); ?>"><?php echo esc_html( $jws_buy_item['chip'][1] ); ?></span>
						</div>
						<p class="jws-buy__note"><?php echo esc_html( $jws_buy_item['note'] ); ?></p>
						<p class="jws-buy__paid"><?php echo esc_html( $jws_buy_item['line'] ); ?></p>
						<?php if ( $jws_buy_item['blocked'] ) : ?>
							<p class="jws-buy__paid"><?php esc_html_e( 'Not available on this profile.', 'jws_streamvid' ); ?></p>
						<?php endif; ?>
					</div>
					<div class="jws-buy__actions">
						<?php if ( $jws_buy_item['watch'] ) : ?>
							<a class="button-default jws-btn-sm" href="<?php echo esc_url( $jws_buy_item['url'] ); ?>">
								<i class="jws-icon-play-fill" aria-hidden="true"></i>
								<?php echo esc_html( $jws_buy_item['watch'] ); ?>
							</a>
						<?php elseif ( $jws_buy_item['again'] && ! $jws_buy_item['blocked'] ) : ?>
							<a class="button-default jws-btn-sm" href="<?php echo esc_url( $jws_buy_item['url'] ); ?>"><?php esc_html_e( 'Rent again', 'jws_streamvid' ); ?></a>
						<?php endif; ?>
						<?php if ( $jws_buy_item['order'] ) : ?>
							<button type="button" class="button-custom jws-btn-sm download-invoice-woo" data-invoice="<?php echo esc_attr( $jws_buy_item['order'] ); ?>">
								<i class="jws-icon-arrow-line-down" aria-hidden="true"></i>
								<?php esc_html_e( 'Invoice', 'jws_streamvid' ); ?>
							</button>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php jws_streamvid_load_template( 'user/profile/parts/invoice-download.php', false ); ?>
	<?php else : ?>
		<div class="jws-lib__empty">
			<span class="jws-lib__empty-icon" aria-hidden="true"><i class="jws-icon-handbag"></i></span>
			<h5><?php echo Jws_PPV_Access::TYPE_RENT === $jws_buy_type ? esc_html__( 'No rentals yet', 'jws_streamvid' ) : esc_html__( 'No purchases yet', 'jws_streamvid' ); ?></h5>
			<p>
				<?php
				echo Jws_PPV_Access::TYPE_RENT === $jws_buy_type
					? esc_html__( 'Rent a title to watch it for a limited time. Rentals you start show their time left here.', 'jws_streamvid' )
					: esc_html__( 'Titles you buy stay on this account for good and show up here.', 'jws_streamvid' );
				?>
			</p>

		</div>
	<?php endif; ?>
</div>
