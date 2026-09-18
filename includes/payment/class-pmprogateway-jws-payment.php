<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/*
 * Nothing to define until Paid Memberships Pro has declared the base class.
 * The loader hooks this file onto `plugins_loaded`, but PMPro can be inactive
 * altogether, in which case there is no gateway for it to ask about anyway.
 */
if ( ! class_exists( 'PMProGateway' ) ) {
	return;
}

/**
 * The PMPro gateway behind orders this checkout took.
 *
 * PMPro records every order and subscription against a gateway name, and then
 * looks for a class called `PMProGateway_<name>` whenever it needs to ask that
 * gateway a question. Orders from this checkout are stamped `jws_payment`
 * (Jws_Payment_Fulfillment::PMPRO_GATEWAY), so without this class PMPro has a
 * gateway it cannot talk to: every subscription it creates from one of our
 * orders fails its first sync with "Could not find gateway class.", the error
 * is written to subscription meta, and the admin screen shows it from then on.
 *
 * There is no API to call here. Stripe and PayPal are already talked to by
 * Jws_Payment_Stripe and Jws_Payment_Paypal, and what they say is written to
 * the ledger's own subscription table by the webhook handlers. That table is
 * therefore what this class answers from — it is this checkout's record of
 * what the gateway last said, and re-asking Stripe from here would only be a
 * second, slower way to learn the same thing.
 */
class PMProGateway_jws_payment extends PMProGateway {

	/**
	 * @param string|null $gateway The gateway name PMPro built this object for.
	 */
	public function __construct( $gateway = null ) {
		$this->gateway = $gateway;
	}

	/**
	 * What PMPro's own screens may offer for these subscriptions.
	 *
	 * Billing details cannot be changed from PMPro's account page: the card on
	 * file belongs to the Stripe or PayPal customer this checkout created, and
	 * the member updates it through the checkout's own flow.
	 */
	public static function supports( $feature ) {

		$supports = array(
			'subscription_sync'      => true,
			'payment_method_updates' => false,
			'recurring_trials'       => false,
		);

		if ( empty( $supports[ $feature ] ) ) {
			return false;
		}

		return $supports[ $feature ];
	}

	/**
	 * Brings one PMPro subscription back in line with the ledger.
	 *
	 * @param PMPro_Subscription $subscription
	 * @return string|null Error message, or null when the sync succeeded.
	 */
	public function update_subscription_info( $subscription ) {

		if ( ! class_exists( 'Jws_Payment_Subscriptions' ) ) {
			return esc_html__( 'The StreamVid payment system is not available.', 'jws_streamvid' );
		}

		$row = Jws_Payment_Subscriptions::find_by_ref_any_gateway( $subscription->get_subscription_transaction_id() );

		if ( ! $row ) {
			return esc_html__( 'No subscription with this transaction ID was found in the StreamVid ledger.', 'jws_streamvid' );
		}

		/* Start date and the orders behind it are PMPro's own business, and
		   the base class already works them out from the order table. Ours is
		   the gateway half, so it is applied second and wins.

		   Skipped for a subscription with no next payment date, because the
		   base class runs that through strtotime() without checking and PHP 8
		   fills the log with a deprecation notice for every cancelled plan
		   synced. Nothing is lost by it: the only other thing the base class
		   would do is recalculate a next payment date the ledger is about to
		   replace anyway. */
		if ( $subscription->get_next_payment_date() ) {
			parent::update_subscription_info( $subscription );
		}

		$update = array();

		/* The ledger stores what is charged on renewal but not how often —
		   the interval was read off the PMPro level when the plan was sold,
		   and that is still where it lives. */
		$level = function_exists( 'pmpro_getLevel' ) ? pmpro_getLevel( $subscription->get_membership_level_id() ) : null;

		if ( ! empty( $level->cycle_number ) && ! empty( $level->cycle_period ) ) {
			$update['cycle_number'] = (int) $level->cycle_number;
			$update['cycle_period'] = ucfirst( strtolower( (string) $level->cycle_period ) );
		}

		if ( (float) $row->amount > 0 ) {
			$update['billing_amount'] = (float) $row->amount;
		}

		if ( in_array( $row->status, array( Jws_Payment_Subscriptions::STATUS_ACTIVE, Jws_Payment_Subscriptions::STATUS_PAST_DUE ), true ) ) {
			$update['status'] = 'active';

			if ( ! empty( $row->current_period_end ) ) {
				$update['next_payment_date'] = $row->current_period_end;
			}
		} else {
			$update['status'] = 'cancelled';

			/* A cancelled plan is normally paid up to the end of the term it
			   already bought, so that — not the moment of cancellation — is
			   when it ends. PMPro clears the next payment date itself when it
			   saves a cancelled subscription. */
			$update['enddate'] = ! empty( $row->current_period_end ) ? $row->current_period_end : current_time( 'mysql' );
		}

		$subscription->set( $update );

		return null;
	}

	/**
	 * Stops billing when the subscription is cancelled from PMPro's side.
	 *
	 * Without this, PMPro would inherit the base class's stub, which reports
	 * success without telling any gateway anything — the level would end and
	 * the card would go on being charged for it.
	 *
	 * @param PMPro_Subscription $subscription
	 * @return bool Whether the gateway accepted the cancellation.
	 */
	public function cancel_subscription( $subscription ) {

		if ( ! class_exists( 'Jws_Payment_Subscriptions' ) ) {
			return false;
		}

		$row = Jws_Payment_Subscriptions::find_by_ref_any_gateway( $subscription->get_subscription_transaction_id() );

		if ( ! $row ) {
			return false;
		}

		return Jws_Payment_Subscriptions::cancel_at_gateway( $row );
	}
}
