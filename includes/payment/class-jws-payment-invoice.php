<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * Invoices for orders this checkout took.
 *
 * The Download button on the pay-per-view and rentals tabs asks
 * `get_invoice_html_woo` for one order's invoice, and that handler only knows
 * how to read a WooCommerce order. A purchase made here has no WooCommerce
 * order at all — so its customer-facing order number is stored instead
 * (`SV-4K7M-9QF2`), this class claims anything that is not a bare integer, and
 * everything numeric still falls through to the original handler untouched.
 *
 * Being non-numeric is what makes that safe. A ledger id and a WooCommerce
 * order id are both small integers from independent sequences, so they collide
 * constantly: asking for ledger order 19 by its bare id would hand back
 * WooCommerce order 19 — a different customer's invoice, with their name and
 * address on it. An order number can never be mistaken for either.
 */
class Jws_Payment_Invoice {

	/** How ledger ids were referenced before order numbers existed. */
	const LEGACY_PREFIX = 'jws-';

	public function register() {

		/*
		 * Priority 1, ahead of the original handler at 10. This one answers
		 * with wp_send_json_*(), which exits, so an order number never reaches
		 * it; a bare id returns here and the original runs exactly as before.
		 */
		add_action( 'wp_ajax_get_invoice_html_woo', array( $this, 'ajax_invoice' ), 1 );
	}

	/**
	 * How an order is referenced from the purchase usermeta.
	 *
	 * The customer-facing order number, which is unique, unguessable and the
	 * same string printed on the receipt — so a shopper reading "SV-4K7M-9QF2"
	 * off their invoice is quoting something support can search for.
	 */
	public static function reference( $order ) {
		return (string) $order->order_number;
	}

	/**
	 * The order behind a reference, or null when it is not one of ours.
	 *
	 * A bare integer is left alone: that is a WooCommerce order id, and the
	 * original handler is the one that knows how to read it.
	 */
	public static function resolve( $reference ) {

		$reference = trim( (string) $reference );

		if ( '' === $reference || ctype_digit( $reference ) ) {
			return null;
		}

		/* Purchases recorded between the invoice fix and order numbers being
		   added carry `jws-<ledger id>`; still honoured so those receipts keep
		   downloading. */
		if ( 0 === strpos( $reference, self::LEGACY_PREFIX ) ) {
			return Jws_Payment_Orders::find( (int) substr( $reference, strlen( self::LEGACY_PREFIX ) ) );
		}

		return Jws_Payment_Orders::find_by_number( $reference );
	}

	/* ---------------------------------------------------------------------- */
	/* AJAX                                                                    */
	/* ---------------------------------------------------------------------- */

	public function ajax_invoice() {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only; ownership is what guards this.
		$reference = isset( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : '';

		if ( '' === $reference || ctype_digit( $reference ) ) {
			return; // A WooCommerce id — let the original handler have it.
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please sign in first.', 'jws_streamvid' ) ) );
		}

		$order = self::resolve( $reference );

		if ( ! $order || Jws_Payment_Orders::SOURCE !== $order->source ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Order not found', 'jws_streamvid' ) ) );
		}

		/*
		 * An invoice carries the buyer's name, address and phone number. Only
		 * the person who bought it, or someone who can already see every order
		 * in the dashboard, may read one.
		 */
		if ( (int) $order->user_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Order not found', 'jws_streamvid' ) ) );
		}

		if ( Jws_Payment_Orders::STATUS_COMPLETED !== $order->status ) {
			wp_send_json_error( array( 'message' => esc_html__( 'This order has not been paid.', 'jws_streamvid' ) ) );
		}

		wp_send_json_success( array( 'html' => self::render( $order ) ) );
	}

	/* ---------------------------------------------------------------------- */
	/* The document                                                            */
	/* ---------------------------------------------------------------------- */

	/**
	 * The invoice stylesheet.
	 *
	 * Shared with the WooCommerce invoice so the two documents are the same
	 * document with different numbers in it — a customer with one of each
	 * should not be able to tell which checkout took the money.
	 */
	public static function styles() {
		?>
		<style>
			.invoice-pdf-container {
				font-family: Arial, sans-serif;
				max-width: 700px;
				margin: 0 auto;
				background: #fff;
				color: #222;
				border: 1px solid #e5e5e5;
				padding: 32px 40px 40px 40px;
				border-radius: 12px;
			}
			.invoice-header {
				display: flex;
				align-items: center;
				margin-bottom: 32px;
			}
			.invoice-logo {
				margin-right: 32px;
				max-height: 70px;
			}
			.invoice-shop-info {
				font-size: 15px;
				line-height: 1.6;
			}
			.invoice-title {
				font-size: 32px;
				font-weight: bold;
				margin-bottom: 8px;
				color: #2d3e50;
			}
			.invoice-meta {
				margin-bottom: 24px;
				font-size: 15px;
			}
			.invoice-meta span {
				display: inline-block;
				min-width: 120px;
				font-weight: 500;
			}
			.invoice-section-title {
				font-size: 18px;
				font-weight: 600;
				margin: 24px 0 8px 0;
				color: #2d3e50;
			}
			.invoice-address, .invoice-customer {
				font-size: 15px;
				line-height: 1.6;
				margin-bottom: 8px;
			}
			.invoice-table {
				width: 100%;
				border-collapse: collapse;
				margin: 24px 0;
			}
			.invoice-table th, .invoice-table td {
				border: 1px solid #e5e5e5;
				padding: 10px 12px;
				text-align: left;
			}
			.invoice-table th {
				background: #f7f7f7;
				font-weight: 600;
			}
			.invoice-table tfoot td {
				font-weight: bold;
				background: #fafafa;
			}
			.invoice-summary {
				margin-top: 24px;
				font-size: 16px;
			}
			.invoice-footer {
				margin-top: 40px;
				font-size: 13px;
				color: #888;
				text-align: center;
			}
		</style>
		<?php
	}

	/** One ledger order as a printable invoice. */
	public static function render( $order ) {

		$logo_id  = get_theme_mod( 'custom_logo' );
		$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';

		$user  = get_userdata( $order->user_id );
		$price = Jws_Payment_Items::format_price( $order->amount, $order->currency );

		ob_start();
		self::styles();
		?>
		<div class="invoice-pdf-container">

			<div class="invoice-header">
				<?php if ( $logo_url ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" class="invoice-logo" alt="" />
				<?php endif; ?>
				<div class="invoice-shop-info">
					<div class="invoice-title"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></div>
					<?php $phone = get_option( 'woocommerce_store_phone' ); ?>
					<?php if ( $phone ) : ?>
						<div><?php echo esc_html__( 'Phone:', 'jws_streamvid' ) . ' ' . esc_html( $phone ); ?></div>
					<?php endif; ?>
					<?php $email = get_option( 'woocommerce_store_email', get_option( 'admin_email' ) ); ?>
					<?php if ( $email ) : ?>
						<div><?php echo esc_html__( 'Email:', 'jws_streamvid' ) . ' ' . esc_html( $email ); ?></div>
					<?php endif; ?>
				</div>
			</div>

			<div class="invoice-meta">
				<div><span><?php esc_html_e( 'Order #', 'jws_streamvid' ); ?></span> <?php echo esc_html( self::reference( $order ) ); ?></div>
				<div><span><?php esc_html_e( 'Date:', 'jws_streamvid' ); ?></span> <?php echo esc_html( mysql2date( 'Y-m-d H:i', $order->paid_at ? $order->paid_at : $order->created_at ) ); ?></div>
				<div><span><?php esc_html_e( 'Status:', 'jws_streamvid' ); ?></span> <?php esc_html_e( 'Paid', 'jws_streamvid' ); ?></div>
				<div><span><?php esc_html_e( 'Payment:', 'jws_streamvid' ); ?></span> <?php echo esc_html( self::payment_label( $order ) ); ?></div>
				<?php if ( $order->gateway_ref ) : ?>
					<div><span><?php esc_html_e( 'Reference:', 'jws_streamvid' ); ?></span> <?php echo esc_html( $order->gateway_ref ); ?></div>
				<?php endif; ?>
			</div>

			<div class="invoice-section-title"><?php esc_html_e( 'Pay to', 'jws_streamvid' ); ?></div>
			<div class="invoice-address"><?php echo wp_kses_post( self::seller_address() ); ?></div>

			<div class="invoice-section-title"><?php esc_html_e( 'Billed To', 'jws_streamvid' ); ?></div>
			<div class="invoice-address"><?php echo wp_kses_post( self::buyer_address( $user ) ); ?></div>

			<div class="invoice-section-title"><?php esc_html_e( 'Order Items', 'jws_streamvid' ); ?></div>
			<table class="invoice-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Product', 'jws_streamvid' ); ?></th>
						<th><?php esc_html_e( 'Quantity', 'jws_streamvid' ); ?></th>
						<th><?php esc_html_e( 'Price', 'jws_streamvid' ); ?></th>
						<th><?php esc_html_e( 'Total', 'jws_streamvid' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php echo esc_html( $order->item_label ); ?></td>
						<td>1</td>
						<td><?php echo esc_html( $price ); ?></td>
						<td><?php echo esc_html( $price ); ?></td>
					</tr>
				</tbody>
				<tfoot>
					<tr>
						<td colspan="3" style="text-align:right;"><?php esc_html_e( 'Subtotal', 'jws_streamvid' ); ?></td>
						<td><?php echo esc_html( $price ); ?></td>
					</tr>
					<tr>
						<td colspan="3" style="text-align:right;font-size:18px;"><?php esc_html_e( 'Total', 'jws_streamvid' ); ?></td>
						<td style="font-size:18px;"><?php echo esc_html( $price ); ?></td>
					</tr>
				</tfoot>
			</table>

			<div class="invoice-summary"><?php esc_html_e( 'Thank you for your purchase!', 'jws_streamvid' ); ?></div>
			<div class="invoice-footer"><?php esc_html_e( 'This invoice was generated automatically. If you have any questions, please contact our support.', 'jws_streamvid' ); ?></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/** How the buyer paid, in the same words the receipt page uses. */
	private static function payment_label( $order ) {

		$gateway = $order->gateway ? Jws_Payment_Settings::gateway_label( $order->gateway ) : '';
		$catalog = Jws_Payment_Settings::method_catalog();

		if ( isset( $catalog[ $order->method ] ) ) {
			$method = $catalog[ $order->method ]['label'];

			return $method === $gateway ? $gateway : $method . ' (' . $gateway . ')';
		}

		return $gateway ? $gateway : esc_html__( 'Card', 'jws_streamvid' );
	}

	/**
	 * Who the money went to.
	 *
	 * Read from the WooCommerce store settings first and the site admin's
	 * billing fields second — the same two places the WooCommerce invoice
	 * looks, so both documents name the same company.
	 */
	private static function seller_address() {

		$lines = array(
			get_user_meta( 1, 'billing_company', true ),
			get_option( 'woocommerce_store_address' ),
			get_option( 'woocommerce_store_city' ),
			get_option( 'woocommerce_store_postcode' ),
			get_user_meta( 1, 'billing_phone', true ),
			get_option( 'woocommerce_store_email', get_option( 'admin_email' ) ),
		);

		$lines = array_filter( array_map( 'trim', array_map( 'strval', $lines ) ) );

		return implode( '<br />', array_map( 'esc_html', $lines ) );
	}

	/**
	 * Who was billed.
	 *
	 * This checkout never asks for a billing address — it has nothing to ship
	 * and nothing to invoice to one — so the fields are shown only when the
	 * buyer happens to have them from a previous WooCommerce order, and the
	 * account name and email carry the document otherwise.
	 */
	private static function buyer_address( $user ) {

		if ( ! $user ) {
			return '';
		}

		$name = trim(
			get_user_meta( $user->ID, 'billing_first_name', true ) . ' ' .
			get_user_meta( $user->ID, 'billing_last_name', true )
		);

		$lines = array( esc_html( $name ? $name : $user->display_name ) );

		$optional = array(
			'Address' => get_user_meta( $user->ID, 'billing_address_1', true ),
			'Company' => get_user_meta( $user->ID, 'billing_company', true ),
			'Phone'   => get_user_meta( $user->ID, 'billing_phone', true ),
		);

		foreach ( $optional as $label => $value ) {
			if ( $value ) {
				$lines[] = esc_html( $label ) . ': ' . esc_html( $value );
			}
		}

		$billing_email = get_user_meta( $user->ID, 'billing_email', true );

		$lines[] = esc_html__( 'Email:', 'jws_streamvid' ) . ' ' . esc_html( $billing_email ? $billing_email : $user->user_email );

		return implode( '<br />', $lines );
	}
}
