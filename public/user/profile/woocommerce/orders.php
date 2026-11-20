<?php
/**
 * Dashboard > Shopping > Orders (shop/orders/{page}) and one order
 * (shop/view-order/{id}).
 *
 * Built on WooCommerce's account functions with its hooks kept (before and
 * after the list, extra columns, order actions, woocommerce_view_order),
 * laid out like the profile's other lists: one card per order, the status
 * as a chip, Pay as the main button.
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jws_ord_profile = jws_streamvid()->get()->profile;
$jws_ord_user    = get_current_user_id();
$jws_ord_request = trailingslashit( (string) get_query_var( 'dashboard' ) );
$jws_ord_list    = $jws_ord_profile->get_endpoint( $jws_ord_user, 'dashboard', 'shop/orders' );

if ( ! function_exists( 'jws_shop_order_chip' ) ) {
	/**
	 * Status chip, the same chips as Purchased and Rented.
	 *
	 * @param WC_Order $order Order.
	 */
	function jws_shop_order_chip( $order ) {
		$status = $order->get_status();
		$tones  = array(
			'completed'      => 'owned',
			'failed'         => 'expired',
			'cancelled'      => 'expired',
			'refunded'       => 'muted',
			'checkout-draft' => 'muted',
		);
		$tone = isset( $tones[ $status ] ) ? $tones[ $status ] : 'pending';

		printf(
			'<span class="jws-buy__chip jws-buy__chip--%1$s">%2$s</span>',
			esc_attr( $tone ),
			esc_html( wc_get_order_status_name( $status ) )
		);
	}
}

if ( ! function_exists( 'jws_shop_order_actions' ) ) {
	/**
	 * WooCommerce's order actions as theme buttons; Pay is the main one.
	 *
	 * @param WC_Order $order Order.
	 * @param array    $skip  Action keys to leave out.
	 */
	function jws_shop_order_actions( $order, $skip = array() ) {
		$actions = array_diff_key( wc_get_account_orders_actions( $order ), array_flip( $skip ) );

		if ( ! $actions ) {
			return;
		}

		echo '<div class="jws-shop__actions">';
		foreach ( $actions as $key => $action ) {
			printf(
				'<a href="%1$s" class="%2$s jws-btn-sm jws-shop__action--%3$s"%4$s>%5$s</a>',
				esc_url( $action['url'] ),
				'pay' === $key ? 'button-default' : 'button-custom',
				esc_attr( sanitize_html_class( $key ) ),
				empty( $action['aria-label'] ) ? '' : ' aria-label="' . esc_attr( $action['aria-label'] ) . '"',
				esc_html( $action['name'] )
			);
		}
		echo '</div>';
	}
}

/* ------------------------------------------------------------------ *
 * One order
 * ------------------------------------------------------------------ */

if ( preg_match( '#view-order/(\d+)/#', $jws_ord_request, $jws_ord_match ) ) :

	$jws_ord_order = wc_get_order( absint( $jws_ord_match[1] ) );
	?>
	<a class="jws-shop__back fs-small" href="<?php echo esc_url( $jws_ord_list ); ?>">
		<i class="jws-icon-caret-left" aria-hidden="true"></i>
		<?php esc_html_e( 'All orders', 'jws_streamvid' ); ?>
	</a>
	<?php

	if ( ! $jws_ord_order || (int) $jws_ord_order->get_customer_id() !== $jws_ord_user ) :
		?>
		<div class="woocommerce-error" role="alert"><?php esc_html_e( 'Sorry, you can not view this order.', 'jws_streamvid' ); ?></div>
		<?php
		return;
	endif;

	$jws_ord_notes = $jws_ord_order->get_customer_order_notes();
	?>
	<div class="jws-shop__order-head">
		<div class="jws-shop__order-text">
			<div class="jws-shop__order-title">
				<h5 class="jws-shop__heading">
					<?php
					/* translators: %s: order number */
					echo esc_html( sprintf( __( 'Order #%s', 'jws_streamvid' ), $jws_ord_order->get_order_number() ) );
					?>
				</h5>
				<?php jws_shop_order_chip( $jws_ord_order ); ?>
			</div>
			<p class="jws-shop__muted">
				<?php
				/* translators: %s: date */
				echo esc_html( sprintf( __( 'Placed on %s', 'jws_streamvid' ), wc_format_datetime( $jws_ord_order->get_date_created() ) ) );
				?>
			</p>
		</div>
		<?php jws_shop_order_actions( $jws_ord_order, array( 'view' ) ); ?>
	</div>

	<?php if ( $jws_ord_notes ) : ?>
		<section class="jws-shop__card">
			<h6 class="jws-shop__card-title"><?php esc_html_e( 'Order updates', 'jws_streamvid' ); ?></h6>
			<ol class="jws-shop__notes">
				<?php foreach ( $jws_ord_notes as $jws_ord_note ) : ?>
					<li class="jws-shop__note">
						<time class="jws-shop__muted fs-small" datetime="<?php echo esc_attr( mysql2date( 'c', $jws_ord_note->comment_date ) ); ?>">
							<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $jws_ord_note->comment_date ) ) ); ?>
						</time>
						<div class="jws-shop__note-text"><?php echo wp_kses_post( wpautop( wptexturize( $jws_ord_note->comment_content ) ) ); ?></div>
					</li>
				<?php endforeach; ?>
			</ol>
		</section>
	<?php endif; ?>

	<?php
	/*
	 * Order details and the billing / shipping address (WooCommerce's own
	 * tables). Their <h2> titles become the card titles used here (<h6>,
	 * under the h5 order title), which also takes them out of the theme's
	 * own WooCommerce title sizes, so the stylesheet sets no font size.
	 */
	ob_start();
	do_action( 'woocommerce_view_order', $jws_ord_order->get_id() );
	echo preg_replace( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce's escaped template output, only the title tag and class changed.
		'#<h2\s[^>]*class="(?:woocommerce-order-details__title|woocommerce-column__title)[^"]*"[^>]*>(.*?)</h2>#s',
		'<h6 class="jws-shop__card-title">$1</h6>',
		ob_get_clean()
	);

	return;
endif;

/* ------------------------------------------------------------------ *
 * Order list
 * ------------------------------------------------------------------ */

$jws_ord_page   = preg_match( '#orders/(\d+)/#', $jws_ord_request, $jws_ord_match ) ? max( 1, absint( $jws_ord_match[1] ) ) : 1;
$jws_ord_result = wc_get_orders(
	apply_filters(
		'woocommerce_my_account_my_orders_query',
		array(
			'customer' => $jws_ord_user,
			'page'     => $jws_ord_page,
			'paginate' => true,
		)
	)
);
$jws_ord_has    = 0 < $jws_ord_result->total;
$jws_ord_core   = array( 'order-number', 'order-date', 'order-status', 'order-total', 'order-actions' );

do_action( 'woocommerce_before_account_orders', $jws_ord_has );

if ( $jws_ord_has ) :
	?>
	<ul class="jws-shop__orders">
		<?php
		foreach ( $jws_ord_result->orders as $jws_ord_item ) :
			$jws_ord_order = wc_get_order( $jws_ord_item );
			if ( ! $jws_ord_order ) {
				continue;
			}
			$jws_ord_count = $jws_ord_order->get_item_count() - $jws_ord_order->get_item_count_refunded();
			$jws_ord_date  = $jws_ord_order->get_date_created();
			$jws_ord_view  = $jws_ord_profile->get_endpoint( $jws_ord_user, 'dashboard', 'shop/view-order/' . $jws_ord_order->get_id() );
			?>
			<li class="jws-shop__order jws-shop__card woocommerce-orders-table__row--status-<?php echo esc_attr( $jws_ord_order->get_status() ); ?>">
				<div class="jws-shop__order-text">
					<div class="jws-shop__order-title">
						<a class="jws-shop__order-number h6" href="<?php echo esc_url( $jws_ord_view ); ?>">
							<?php
							/* translators: %s: order number */
							echo esc_html( sprintf( __( 'Order #%s', 'jws_streamvid' ), $jws_ord_order->get_order_number() ) );
							?>
						</a>
						<?php jws_shop_order_chip( $jws_ord_order ); ?>
					</div>
					<p class="jws-shop__muted fs-small">
						<?php if ( $jws_ord_date ) : ?>
							<time datetime="<?php echo esc_attr( $jws_ord_date->date( 'c' ) ); ?>"><?php echo esc_html( wc_format_datetime( $jws_ord_date ) ); ?></time>
							&middot;
						<?php endif; ?>
						<?php
						/* translators: %s: number of items */
						echo esc_html( sprintf( _n( '%s item', '%s items', $jws_ord_count, 'jws_streamvid' ), number_format_i18n( $jws_ord_count ) ) );
						?>
					</p>
					<?php
					// Columns other plugins add to the WooCommerce orders table.
					foreach ( wc_get_account_orders_columns() as $jws_ord_col => $jws_ord_label ) :
						if ( in_array( $jws_ord_col, $jws_ord_core, true ) || ! has_action( 'woocommerce_my_account_my_orders_column_' . $jws_ord_col ) ) {
							continue;
						}
						?>
						<div class="jws-shop__order-extra fs-small">
							<span class="jws-shop__muted"><?php echo esc_html( $jws_ord_label ); ?>:</span>
							<?php do_action( 'woocommerce_my_account_my_orders_column_' . $jws_ord_col, $jws_ord_order ); ?>
						</div>
					<?php endforeach; ?>
				</div>
				<div class="jws-shop__order-side">
					<span class="jws-shop__total h6"><?php echo wp_kses_post( $jws_ord_order->get_formatted_order_total() ); ?></span>
					<?php jws_shop_order_actions( $jws_ord_order ); ?>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>

	<?php if ( 1 < $jws_ord_result->max_num_pages ) : ?>
		<nav class="jws-shop__pager" aria-label="<?php esc_attr_e( 'Order pages', 'jws_streamvid' ); ?>">
			<?php if ( 1 < $jws_ord_page ) : ?>
				<a class="button-custom jws-btn-sm" href="<?php echo esc_url( $jws_ord_profile->get_endpoint( $jws_ord_user, 'dashboard', 'shop/orders/' . ( $jws_ord_page - 1 ) ) ); ?>">
					<i class="jws-icon-caret-left" aria-hidden="true"></i>
					<?php esc_html_e( 'Previous', 'jws_streamvid' ); ?>
				</a>
			<?php endif; ?>
			<span class="jws-shop__muted fs-small">
				<?php
				/* translators: 1: current page, 2: number of pages */
				echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'jws_streamvid' ), $jws_ord_page, $jws_ord_result->max_num_pages ) );
				?>
			</span>
			<?php if ( $jws_ord_page < $jws_ord_result->max_num_pages ) : ?>
				<a class="button-custom jws-btn-sm" href="<?php echo esc_url( $jws_ord_profile->get_endpoint( $jws_ord_user, 'dashboard', 'shop/orders/' . ( $jws_ord_page + 1 ) ) ); ?>">
					<?php esc_html_e( 'Next', 'jws_streamvid' ); ?>
					<i class="jws-icon-caret-right" aria-hidden="true"></i>
				</a>
			<?php endif; ?>
		</nav>
	<?php endif; ?>

<?php else : ?>

	<div class="jws-lib__empty">
		<span class="jws-lib__empty-icon" aria-hidden="true"><i class="jws-icon-handbag"></i></span>
		<h5><?php esc_html_e( 'No orders yet', 'jws_streamvid' ); ?></h5>
		<p><?php esc_html_e( 'Orders you place in the store show up here with their status.', 'jws_streamvid' ); ?></p>
		<a class="button-default" href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>"><?php esc_html_e( 'Browse products', 'jws_streamvid' ); ?></a>
	</div>

	<?php
endif;

do_action( 'woocommerce_after_account_orders', $jws_ord_has );
