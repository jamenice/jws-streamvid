<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * The order log, as a browsable table.
 *
 * Built on WP_List_Table so the sorting, paging, search box and status links
 * behave exactly like every other list in the dashboard — an admin checking a
 * payment should not have to learn a new table.
 *
 * Read-only on purpose. Nothing here edits an order: an order is a record of
 * money that moved, and the way to reverse one is a refund at the gateway,
 * which comes back through the webhook and updates the row itself.
 */
class Jws_Payment_Orders_Table extends WP_List_Table {

	/** The filter the admin is looking through, resolved once from the request. */
	private $filters = array();

	public function __construct() {

		parent::__construct(
			array(
				'singular' => 'jws_payment_order',
				'plural'   => 'jws_payment_orders',
				'ajax'     => false,
			)
		);
	}

	/* ---------------------------------------------------------------------- */
	/* Request                                                                 */
	/* ---------------------------------------------------------------------- */

	/**
	 * What the admin asked to see.
	 *
	 * Everything is sanitised here and nowhere else, so the query layer only
	 * ever receives values this method vouched for.
	 */
	private function read_request() {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filtering of an admin list.
		$this->filters = array(
			'status'  => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'type'    => isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '',
			'gateway' => isset( $_GET['gateway'] ) ? sanitize_key( wp_unslash( $_GET['gateway'] ) ) : '',
			'source'  => isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : '',
			'search'  => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'orderby' => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'id',
			'order'   => isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		/* "all" is how the status links spell "no filter". */
		if ( 'all' === $this->filters['status'] ) {
			$this->filters['status'] = '';
		}

		return $this->filters;
	}

	/* ---------------------------------------------------------------------- */
	/* Shape                                                                   */
	/* ---------------------------------------------------------------------- */

	public function get_columns() {

		return array(
			'id'      => esc_html__( 'Order', 'jws_streamvid' ),
			'created_at' => esc_html__( 'Date', 'jws_streamvid' ),
			'user_id' => esc_html__( 'Buyer', 'jws_streamvid' ),
			'type'    => esc_html__( 'Type', 'jws_streamvid' ),
			'item'    => esc_html__( 'Item', 'jws_streamvid' ),
			'amount'  => esc_html__( 'Amount', 'jws_streamvid' ),
			'gateway' => esc_html__( 'Paid with', 'jws_streamvid' ),
			'status'  => esc_html__( 'Status', 'jws_streamvid' ),
		);
	}

	public function get_sortable_columns() {

		return array(
			'id'         => array( 'id', true ),
			'created_at' => array( 'created_at', false ),
			'amount'     => array( 'amount', false ),
			'type'       => array( 'type', false ),
			'status'     => array( 'status', false ),
		);
	}

	/** The "All (12) | Completed (9) | Pending (3)" line above the table. */
	protected function get_views() {

		$counts  = Jws_Payment_Ledger::status_counts();
		$current = $this->filters['status'];

		$labels = array(
			'all'       => esc_html__( 'All', 'jws_streamvid' ),
			'completed' => esc_html__( 'Completed', 'jws_streamvid' ),
			'pending'   => esc_html__( 'Pending', 'jws_streamvid' ),
			'failed'    => esc_html__( 'Failed', 'jws_streamvid' ),
			'refunded'  => esc_html__( 'Refunded', 'jws_streamvid' ),
		);

		$views = array();

		foreach ( $labels as $status => $label ) {

			$count = isset( $counts[ $status ] ) ? (int) $counts[ $status ] : 0;

			/* A status nothing has ever been in is noise, but "All" stays even
			   at zero so the link back from an empty filter still exists. */
			if ( ! $count && 'all' !== $status ) {
				continue;
			}

			$url = self::base_url( array( 'status' => 'all' === $status ? false : $status ) );

			$is_current = 'all' === $status ? '' === $current : $current === $status;

			$views[ $status ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
				esc_url( $url ),
				$is_current ? ' class="current" aria-current="page"' : '',
				esc_html( $label ),
				esc_html( number_format_i18n( $count ) )
			);
		}

		return $views;
	}

	/**
	 * A link back to this screen, keeping the tab and dropping paging.
	 *
	 * Changing a filter always returns to page one — staying on page 7 of a
	 * result set that now has two rows shows an empty table.
	 */
	public static function base_url( array $args = array() ) {

		$keep = array( 'page' => Jws_Payment_Settings::PAGE, 'active_tab' => 'orders' );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		foreach ( array( 'status', 'type', 'gateway', 'source', 's', 'orderby', 'order' ) as $key ) {
			if ( isset( $_GET[ $key ] ) && '' !== $_GET[ $key ] ) {
				$keep[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		foreach ( $args as $key => $value ) {
			if ( false === $value ) {
				unset( $keep[ $key ] );
			} else {
				$keep[ $key ] = $value;
			}
		}

		return add_query_arg( $keep, admin_url( 'admin.php' ) );
	}

	/** The type/gateway/source dropdowns, above the table. */
	protected function extra_tablenav( $which ) {

		if ( 'top' !== $which ) {
			return;
		}

		$dropdowns = array(
			'type'    => esc_html__( 'All types', 'jws_streamvid' ),
			'gateway' => esc_html__( 'All gateways', 'jws_streamvid' ),
			'source'  => esc_html__( 'All sources', 'jws_streamvid' ),
		);
		?>
		<div class="alignleft actions">
			<?php foreach ( $dropdowns as $key => $any ) : ?>
				<?php $values = Jws_Payment_Ledger::distinct( $key ); ?>
				<?php if ( count( $values ) > 1 ) : ?>
					<label class="screen-reader-text" for="filter-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $any ); ?></label>
					<select name="<?php echo esc_attr( $key ); ?>" id="filter-<?php echo esc_attr( $key ); ?>">
						<option value=""><?php echo esc_html( $any ); ?></option>
						<?php foreach ( $values as $value ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $this->filters[ $key ] ); ?>>
								<?php echo esc_html( self::label_for( $key, $value ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
			<?php endforeach; ?>

			<?php submit_button( esc_html__( 'Filter', 'jws_streamvid' ), '', 'filter_action', false ); ?>

			<?php if ( array_filter( array( $this->filters['type'], $this->filters['gateway'], $this->filters['source'], $this->filters['search'], $this->filters['status'] ) ) ) : ?>
				<a class="button-link" href="<?php echo esc_url( add_query_arg( array( 'page' => Jws_Payment_Settings::PAGE, 'active_tab' => 'orders' ), admin_url( 'admin.php' ) ) ); ?>">
					<?php echo esc_html__( 'Clear filters', 'jws_streamvid' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Human wording for the values stored in the enum-ish columns. */
	private static function label_for( $column, $value ) {

		$labels = array(
			'type'    => array(
				'membership' => esc_html__( 'Membership', 'jws_streamvid' ),
				'buy'        => esc_html__( 'Buy', 'jws_streamvid' ),
				'rent'       => esc_html__( 'Rent', 'jws_streamvid' ),
				'live'       => esc_html__( 'Live', 'jws_streamvid' ),
				'coins'      => esc_html__( 'Coins', 'jws_streamvid' ),
				'coin'       => esc_html__( 'Coins', 'jws_streamvid' ),
			),
			'gateway' => array(
				'stripe'      => 'Stripe',
				'paypal'      => 'PayPal',
				'woocommerce' => 'WooCommerce',
			),
			'source'  => array(
				'jws'         => esc_html__( 'This checkout', 'jws_streamvid' ),
				'woocommerce' => 'WooCommerce',
				'pmpro'       => 'Paid Memberships Pro',
				'drama'       => esc_html__( 'Drama module', 'jws_streamvid' ),
			),
			'status'  => array(
				'completed' => esc_html__( 'Completed', 'jws_streamvid' ),
				'pending'   => esc_html__( 'Pending', 'jws_streamvid' ),
				'failed'    => esc_html__( 'Failed', 'jws_streamvid' ),
				'refunded'  => esc_html__( 'Refunded', 'jws_streamvid' ),
			),
		);

		return isset( $labels[ $column ][ $value ] ) ? $labels[ $column ][ $value ] : $value;
	}

	/* ---------------------------------------------------------------------- */
	/* Data                                                                    */
	/* ---------------------------------------------------------------------- */

	public function prepare_items() {

		$filters  = $this->read_request();
		$per_page = $this->get_items_per_page( 'jws_payment_orders_per_page', 20 );
		$paged    = $this->get_pagenum();

		$total = Jws_Payment_Ledger::count( $filters );

		$this->items = Jws_Payment_Ledger::query(
			array_merge( $filters, array( 'per_page' => $per_page, 'paged' => $paged ) )
		);

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'item' );
	}

	public function no_items() {

		echo esc_html__( 'No orders match this filter.', 'jws_streamvid' );
	}

	/* ---------------------------------------------------------------------- */
	/* Columns                                                                 */
	/* ---------------------------------------------------------------------- */

	/**
	 * The order number, plus what it is tied to at the gateway.
	 *
	 * The number, not the row id: it is what the customer sees on their receipt
	 * and their invoice, so it is the only string a support conversation will
	 * ever be about. The gateway reference under it is what an admin pastes
	 * into the Stripe or PayPal dashboard to see the other half of the payment.
	 */
	public function column_id( $order ) {

		$actions = array();

		$dashboard = self::gateway_dashboard_url( $order );

		if ( $dashboard ) {
			$actions['gateway'] = sprintf(
				'<a href="%s" target="_blank" rel="noopener">%s</a>',
				esc_url( $dashboard ),
				sprintf(
					/* translators: %s: gateway name. */
					esc_html__( 'View on %s', 'jws_streamvid' ),
					esc_html( Jws_Payment_Settings::gateway_label( $order->gateway ) )
				)
			);
		}

		if ( $order->gateway_ref ) {
			$actions['ref'] = '<code>' . esc_html( $order->gateway_ref ) . '</code>';
		}

		return sprintf( '<strong>%s</strong>', esc_html( $order->order_number ) ) . $this->row_actions( $actions, true );
	}

	/**
	 * Where this payment lives in the gateway's own dashboard.
	 *
	 * Only for references whose shape we recognise — guessing a URL from an
	 * unknown id would send the admin to a 404 and make them doubt the record.
	 */
	private static function gateway_dashboard_url( $order ) {

		$ref = (string) $order->gateway_ref;

		if ( ! $ref ) {
			return '';
		}

		if ( 'stripe' === $order->gateway ) {

			$config = Jws_Payment_Settings::get( 'stripe' );
			$base   = 'https://dashboard.stripe.com/' . ( 'live' === $config['mode'] ? '' : 'test/' );

			if ( 0 === strpos( $ref, 'pi_' ) ) {
				return $base . 'payments/' . $ref;
			}

			if ( 0 === strpos( $ref, 'sub_' ) ) {
				return $base . 'subscriptions/' . $ref;
			}

			if ( 0 === strpos( $ref, 'in_' ) ) {
				return $base . 'invoices/' . $ref;
			}
		}

		return '';
	}

	public function column_created_at( $order ) {

		$created = mysql2date( 'Y/m/d H:i', $order->created_at );

		if ( empty( $order->paid_at ) || $order->paid_at === $order->created_at ) {
			return esc_html( $created );
		}

		return esc_html( $created ) . '<br /><span class="description">' . sprintf(
			/* translators: %s: date and time the order was paid. */
			esc_html__( 'paid %s', 'jws_streamvid' ),
			esc_html( mysql2date( 'H:i', $order->paid_at ) )
		) . '</span>';
	}

	public function column_user_id( $order ) {

		$user = get_userdata( $order->user_id );

		if ( ! $user ) {
			return sprintf( '<span class="description">#%d</span>', (int) $order->user_id );
		}

		return sprintf(
			'<a href="%s">%s</a><br /><span class="description">%s</span>',
			esc_url( get_edit_user_link( $user->ID ) ),
			esc_html( $user->user_login ),
			esc_html( $user->user_email )
		);
	}

	public function column_type( $order ) {

		return esc_html( self::label_for( 'type', $order->type ) );
	}

	public function column_item( $order ) {

		$label = $order->item_label ? $order->item_label : '—';

		/* Buy, rent and live are all posts; a membership id is a PMPro level
		   and a coin package index is neither, so only the post types link. */
		if ( in_array( $order->type, array( 'buy', 'rent', 'live' ), true ) && get_post( $order->item_id ) ) {
			return sprintf(
				'<a href="%s" target="_blank" rel="noopener">%s</a>',
				esc_url( get_permalink( $order->item_id ) ),
				esc_html( $label )
			);
		}

		return esc_html( $label );
	}

	public function column_amount( $order ) {

		return esc_html( Jws_Payment_Items::format_price( $order->amount, $order->currency ) );
	}

	public function column_gateway( $order ) {

		$gateway = $order->gateway ? Jws_Payment_Settings::gateway_label( $order->gateway ) : '—';
		$source  = self::label_for( 'source', $order->source );

		return esc_html( $gateway ) . '<br /><span class="description">' . esc_html( $source ) . '</span>';
	}

	public function column_status( $order ) {

		$classes = array(
			'completed' => 'is-on',
			'pending'   => 'is-warn',
			'failed'    => 'is-bad',
			'refunded'  => 'is-bad',
		);

		return sprintf(
			'<span class="jws-pay-badge %s">%s</span>',
			esc_attr( isset( $classes[ $order->status ] ) ? $classes[ $order->status ] : '' ),
			esc_html( self::label_for( 'status', $order->status ) )
		);
	}

	public function column_default( $order, $column_name ) {

		return isset( $order->$column_name ) ? esc_html( $order->$column_name ) : '';
	}
}
