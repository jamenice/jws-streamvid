<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * Turning a paid order into the thing that was bought.
 *
 * This is the only part of the unified system that changes anyone's access,
 * and every grant it makes is deliberately identical to the one the old path
 * made: a membership is still a Paid Memberships Pro level, a purchase and a
 * rental still go through Jws_PPV_Access — the same call the WooCommerce path
 * makes — and coins still go through the drama wallet. Nothing downstream —
 * the player, the access checks, the account pages — can tell which checkout
 * paid for it.
 *
 * Called only from Jws_Payment_Orders::mark_paid(), which guarantees it runs
 * exactly once per order.
 */
class Jws_Payment_Fulfillment {

	/** How PMPro records an order this checkout took. */
	const PMPRO_GATEWAY = 'jws_payment';

	/**
	 * The subscription being granted right now, if any.
	 *
	 * pmpro_changeMembershipLevel() cancels the old level before inserting the
	 * new one, and each of those steps fires the hook that stops billing for
	 * levels the member no longer holds. Renewing the same level by hand would
	 * therefore cancel, at the gateway, the subscription this very payment just
	 * created — so the one being granted is named here and skipped there.
	 * Every other subscription the member has is still checked, which is what
	 * makes an upgrade stop billing the plan it replaced.
	 *
	 * @var int
	 */
	public static $granting_subscription_id = 0;

	public static function grant( $order ) {

		switch ( $order->type ) {

			case 'membership':
				self::grant_membership( $order );
				break;

			case 'coins':
				self::grant_coins( $order );
				break;

			case 'rent':
				self::grant_rental( $order );
				break;

			case 'buy':
			case 'live':
				self::grant_purchase( $order );
				break;
		}
	}

	/**
	 * Undoes a grant, as far as it can honestly be undone.
	 *
	 * A refunded membership ends; refunded coins are taken back down to zero
	 * but never below, because they may already have been spent on episodes
	 * that were watched and clawing a wallet negative would leave someone
	 * unable to buy their way out of it. A refunded purchase or rental loses
	 * the title.
	 */
	public static function revoke( $order ) {

		$user_id = (int) $order->user_id;

		switch ( $order->type ) {

			case 'membership':
				if ( function_exists( 'pmpro_cancelMembershipLevel' ) ) {
					pmpro_cancelMembershipLevel( (int) $order->item_id, $user_id, 'refunded' );
				}
				break;

			case 'coins':
				$meta  = Jws_Payment_Orders::meta( $order );
				$coins = isset( $meta['coins'] ) ? (int) $meta['coins'] : 0;

				if ( $coins > 0 && class_exists( 'Jws_Drama_Wallet' ) ) {
					$take = min( $coins, Jws_Drama_Wallet::balance( $user_id ) );

					if ( $take > 0 ) {
						Jws_Drama_Wallet::debit( $user_id, $take, 'refund', (int) $order->id, sprintf( 'Refund #%d', (int) $order->id ) );
					}
				}
				break;

			/* Scoped to this order, so refunding an old rental does not take
			   away a newer one of the same title — see Jws_PPV_Access::revoke(). */
			case 'rent':
				Jws_PPV_Access::revoke( $user_id, (int) $order->item_id, Jws_PPV_Access::TYPE_RENT, (int) $order->id, (string) $order->order_number );
				break;

			case 'buy':
			case 'live':
				Jws_PPV_Access::revoke( $user_id, (int) $order->item_id, Jws_PPV_Access::TYPE_BUY, (int) $order->id, (string) $order->order_number );
				break;
		}
	}

	/* ---------------------------------------------------------------------- */
	/* Membership                                                              */
	/* ---------------------------------------------------------------------- */

	/**
	 * Gives someone a PMPro level, the way PMPro's own checkout would.
	 *
	 * The level row is built from the level's real terms rather than from a
	 * bare id, so PMPro's account pages show the correct billing text, and so
	 * `enddate` can say what this particular sale actually bought:
	 *
	 *  - a recurring plan never expires on its own. The gateway is billing it,
	 *    and the subscription's own cancellation is what ends it — an enddate
	 *    would take access away mid-term the first time a renewal was a day
	 *    late.
	 *  - a one-off level expires exactly when the level says it should.
	 */
	private static function grant_membership( $order ) {

		if ( ! function_exists( 'pmpro_changeMembershipLevel' ) || ! function_exists( 'pmpro_getLevel' ) ) {
			return;
		}

		$user_id  = (int) $order->user_id;
		$level_id = (int) $order->item_id;
		$level    = pmpro_getLevel( $level_id );

		if ( ! $level ) {
			return;
		}

		$meta      = Jws_Payment_Orders::meta( $order );
		$recurring = ! empty( $meta['recurring'] );
		$now       = current_time( 'timestamp' );

		$enddate = '0000-00-00 00:00:00';

		if ( ! $recurring && (int) $level->expiration_number > 0 ) {
			$enddate = gmdate(
				'Y-m-d H:i:s',
				strtotime( '+' . (int) $level->expiration_number . ' ' . $level->expiration_period, $now )
			);
		}

		self::$granting_subscription_id = (int) $order->subscription_id;

		pmpro_changeMembershipLevel(
			array(
				'user_id'         => $user_id,
				'membership_id'   => $level_id,
				'code_id'         => 0,
				'initial_payment' => $level->initial_payment,
				'billing_amount'  => $recurring ? $level->billing_amount : 0,
				'cycle_number'    => $recurring ? (int) $level->cycle_number : 0,
				'cycle_period'    => $recurring ? $level->cycle_period : '',
				'billing_limit'   => $recurring ? (int) $level->billing_limit : 0,
				'trial_amount'    => 0,
				'trial_limit'     => 0,
				'startdate'       => gmdate( 'Y-m-d H:i:s', $now ),
				'enddate'         => $enddate,
			),
			$user_id
		);

		self::$granting_subscription_id = 0;

		self::retire_superseded_subscriptions( $order );

		self::record_pmpro_order( $order, $level );
	}

	/**
	 * Stops any earlier subscription that was paying for this same level.
	 *
	 * Buying a level you are already subscribed to is a renewal as far as the
	 * member is concerned, but at the gateway it is a second billing agreement
	 * — and PMPro sees one level either way, so nothing else would ever notice
	 * the member being charged twice a month for it.
	 *
	 * Subscriptions for *other* levels are left alone: a site whose level group
	 * allows several at once really is selling several, and the member holds
	 * and pays for each.
	 */
	private static function retire_superseded_subscriptions( $order ) {

		foreach ( Jws_Payment_Subscriptions::billing_for_user( (int) $order->user_id ) as $subscription ) {

			if ( 'membership' !== $subscription->type || (int) $subscription->item_id !== (int) $order->item_id ) {
				continue;
			}

			if ( (int) $subscription->id === (int) $order->subscription_id ) {
				continue;
			}

			Jws_Payment_Subscriptions::cancel_at_gateway( $subscription );
		}
	}

	/**
	 * Writes the payment into PMPro's own order table.
	 *
	 * Without this the member is charged and given the level but has no
	 * invoice: PMPro's "Membership Account" and "Invoices" pages read that
	 * table and nothing else, and a member who cannot see what they paid will
	 * open a support ticket about it.
	 */
	private static function record_pmpro_order( $order, $level ) {

		if ( ! class_exists( 'MemberOrder' ) ) {
			return;
		}

		$settings = Jws_Payment_Settings::all();
		$gateway  = (string) $order->gateway;
		$mode     = 'stripe' === $gateway
			? ( 'live' === $settings['stripe']['mode'] ? 'live' : 'sandbox' )
			: ( 'live' === $settings['paypal']['mode'] ? 'live' : 'sandbox' );

		$pmpro_order = new MemberOrder();

		$pmpro_order->user_id                     = (int) $order->user_id;
		$pmpro_order->membership_id               = (int) $level->id;
		$pmpro_order->InitialPayment              = (float) $order->amount;
		$pmpro_order->PaymentAmount               = (float) $order->amount;
		$pmpro_order->subtotal                    = (float) $order->amount;
		$pmpro_order->tax                         = 0;
		$pmpro_order->total                       = (float) $order->amount;
		$pmpro_order->status                      = 'success';
		$pmpro_order->gateway                     = self::PMPRO_GATEWAY;
		$pmpro_order->gateway_environment         = $mode;
		$pmpro_order->payment_type                = Jws_Payment_Settings::gateway_label( $gateway );
		$pmpro_order->payment_transaction_id      = (string) $order->gateway_ref;
		$pmpro_order->subscription_transaction_id = '';

		if ( (int) $order->subscription_id ) {
			$subscription = Jws_Payment_Subscriptions::find( (int) $order->subscription_id );

			if ( $subscription ) {
				$pmpro_order->subscription_transaction_id = (string) $subscription->gateway_ref;
			}
		}

		$pmpro_order->saveOrder();
	}

	/* ---------------------------------------------------------------------- */
	/* Coins                                                                   */
	/* ---------------------------------------------------------------------- */

	private static function grant_coins( $order ) {

		if ( ! class_exists( 'Jws_Drama_Wallet' ) ) {
			return;
		}

		$meta  = Jws_Payment_Orders::meta( $order );
		$coins = isset( $meta['coins'] ) ? (int) $meta['coins'] : 0;

		if ( $coins <= 0 ) {
			return;
		}

		Jws_Drama_Wallet::credit(
			(int) $order->user_id,
			$coins,
			'topup',
			(int) $order->id,
			sprintf( '%s (#%d)', $order->item_label, (int) $order->id )
		);
	}

	/* ---------------------------------------------------------------------- */
	/* Buy / rent                                                              */
	/* ---------------------------------------------------------------------- */

	/**
	 * A rental, with its clock not yet running.
	 *
	 * Overwrites any earlier rental of the same title on purpose — that is
	 * what renting again means, and it is what the WooCommerce path did.
	 */
	private static function grant_rental( $order ) {

		$user_id  = (int) $order->user_id;
		$video_id = (int) $order->item_id;
		$meta     = Jws_Payment_Orders::meta( $order );

		Jws_PPV_Access::grant_rent( $user_id, $video_id, array(
			'order_id' => (int) $order->id,
			/* The order number, not a bare id: the Download-invoice button
			   feeds this straight to wc_get_order(), and a ledger id collides
			   with an unrelated WooCommerce order more often than not. */
			'order_number' => Jws_Payment_Invoice::reference( $order ),
			'price'        => (float) $order->amount,
			'currency'     => (string) $order->currency,
			'delay_days'   => isset( $meta['delay'] ) ? (int) $meta['delay'] : Jws_PPV_Access::default_delay_days(),
			'rent_days'    => isset( $meta['days'] ) ? (int) $meta['days'] : Jws_PPV_Access::default_rent_days( $video_id ),
		) );
	}

	/**
	 * A permanent purchase, plus whatever the uploader earned from it.
	 *
	 * The earnings half only applies to titles that have a `creator`, exactly
	 * as before — a studio title bought from the catalogue pays nobody a
	 * revenue share.
	 */
	private static function grant_purchase( $order ) {

		$user_id  = (int) $order->user_id;
		$video_id = (int) $order->item_id;

		/* Already owned is a no-op inside grant_buy(), which keeps the original
		   purchase date rather than moving one from a year ago. */
		Jws_PPV_Access::grant_buy( $user_id, $video_id, array(
			'order_id' => (int) $order->id,
			/* See grant_rental(): the invoice button reads this value. */
			'order_number' => Jws_Payment_Invoice::reference( $order ),
			'price'        => (float) $order->amount,
			'currency'     => (string) $order->currency,
		) );

		self::pay_creator( $order, $video_id, $user_id );
	}

	/** The uploader's cut of a sale, at the rate set in Theme Options. */
	private static function pay_creator( $order, $video_id, $buyer_id ) {

		$creator = get_post_meta( $video_id, 'creator', true );
		$status  = 'live' === $order->type ? 'live' : ( $creator ? 'paid' : '' );

		if ( ! $status ) {
			return;
		}

		$rate = function_exists( 'jws_theme_get_option' ) ? jws_theme_get_option( 'live_conversion_rate' ) : 100;
		$rate = is_numeric( $rate ) ? (float) $rate : 100;

		$author_id = (int) get_post_field( 'post_author', $video_id );

		if ( ! $author_id ) {
			return;
		}

		$current = get_user_meta( $author_id, 'jws_earnings', true );
		$current = is_numeric( $current ) ? (float) $current : 0;
		$earned  = (float) $order->amount * $rate / 100;

		update_user_meta( $author_id, 'jws_earnings', $current + $earned );

		if ( function_exists( 'jws_save_money_earned_transition' ) ) {
			jws_save_money_earned_transition(
				$author_id,
				$earned,
				$status,
				array( 'user_buy' => $buyer_id, 'video_id' => $video_id )
			);
		}
	}
}
