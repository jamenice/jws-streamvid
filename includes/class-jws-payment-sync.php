<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * Mirrors completed purchases from the three existing checkout paths
 * (PMPro membership, WooCommerce buy/rent/coins, drama's direct
 * Stripe/PayPal flow) into the unified ledger — nothing more.
 *
 * It never changes what any of those three do: no level is granted,
 * no video is unlocked, no coin is credited from here. Each still owns its
 * own fulfillment exactly as before; this class only writes a matching row
 * to Jws_Payment_Ledger so there is one shared place to see all of them.
 *
 * Every handler bails out immediately unless
 * Jws_Payment_Settings::is_enabled() is true, so turning the switch off
 * leaves the site behaving exactly as it does today.
 */
class Jws_Payment_Sync {

	public function __construct() {
		add_action( 'pmpro_after_checkout', array( $this, 'on_pmpro_checkout' ), 10, 2 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'on_woocommerce_order' ) );
		add_action( 'woocommerce_order_status_processing', array( $this, 'on_woocommerce_order' ) );
		add_action( 'streamvid/drama/order_paid', array( $this, 'on_drama_order_paid' ) );
	}

	/** Membership purchase/renewal through Paid Memberships Pro. */
	public function on_pmpro_checkout( $user_id, $order ) {

		if ( ! Jws_Payment_Settings::is_enabled() || empty( $order ) || empty( $order->id ) ) {
			return;
		}

		$level = function_exists( 'pmpro_getLevel' ) ? pmpro_getLevel( $order->membership_id ) : null;

		Jws_Payment_Ledger::record(
			array(
				'user_id'    => (int) $user_id,
				'type'       => 'membership',
				'source'     => 'pmpro',
				'source_ref' => (string) $order->id,
				'item_id'    => (int) $order->membership_id,
				'item_label' => ! empty( $level->name ) ? $level->name : '',
				'amount'     => $order->total,
				'currency'   => function_exists( 'pmpro_get_currency' ) ? pmpro_get_currency() : '',
				'gateway'    => (string) $order->gateway,
				'status'     => 'completed',
			)
		);
	}

	/**
	 * Buy/rent (the theme's `buy_ticket` carrier product) and drama coin
	 * top-ups (the drama module's coin carrier product) both go through a
	 * WooCommerce order — one order can carry either or both.
	 */
	public function on_woocommerce_order( $order_id ) {

		if ( ! Jws_Payment_Settings::is_enabled() || ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$user_id = $order->get_user_id();

		if ( ! $user_id ) {
			return;
		}

		$currency = $order->get_currency();
		$gateway  = $order->get_payment_method();

		foreach ( $order->get_items( 'line_item' ) as $order_item_id => $order_item ) {

			$product = wc_get_product( $order_item->get_product_id() );

			if ( ! $product || ! $product->is_type( 'buy_ticket' ) ) {
				continue;
			}

			$ticket_id = wc_get_order_item_meta( $order_item_id, 'ticket_id', true );

			if ( ! $ticket_id ) {
				continue;
			}

			$ticket_type = wc_get_order_item_meta( $order_item_id, 'ticket_type', true );

			Jws_Payment_Ledger::record(
				array(
					'user_id'    => $user_id,
					'type'       => 'rent' === $ticket_type ? 'rent' : 'buy',
					'source'     => 'woocommerce',
					'source_ref' => $order_id . ':' . $order_item_id,
					'item_id'    => (int) $ticket_id,
					'item_label' => get_the_title( $ticket_id ),
					'amount'     => $order_item->get_total(),
					'currency'   => $currency,
					'gateway'    => $gateway,
					'status'     => 'completed',
				)
			);
		}

		if ( class_exists( 'Jws_Drama_Coins' ) ) {

			$coins = Jws_Drama_Coins::order_coins( $order );

			if ( $coins > 0 ) {
				Jws_Payment_Ledger::record(
					array(
						'user_id'    => $user_id,
						'type'       => 'coin',
						'source'     => 'woocommerce',
						'source_ref' => $order_id . ':coins',
						'item_id'    => 0,
						/* translators: %d: number of coins purchased. */
						'item_label' => sprintf( _n( '%d coin', '%d coins', $coins, 'jws_streamvid' ), $coins ),
						'amount'     => $order->get_total(),
						'currency'   => $currency,
						'gateway'    => $gateway,
						'status'     => 'completed',
					)
				);
			}
		}
	}

	/** VIP or coins bought through the drama module's own Stripe/PayPal checkout. */
	public function on_drama_order_paid( $order ) {

		if ( ! Jws_Payment_Settings::is_enabled() || empty( $order ) || empty( $order->id ) ) {
			return;
		}

		Jws_Payment_Ledger::record(
			array(
				'user_id'    => (int) $order->user_id,
				'type'       => 'vip' === $order->kind ? 'membership' : 'coin',
				'source'     => 'drama',
				'source_ref' => (string) $order->id,
				'item_id'    => 0,
				'item_label' => (string) $order->label,
				'amount'     => $order->amount,
				'currency'   => $order->currency,
				'gateway'    => (string) $order->gateway,
				'status'     => 'completed',
			)
		);
	}
}

new Jws_Payment_Sync();
