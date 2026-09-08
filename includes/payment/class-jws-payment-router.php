<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * Sends the three existing checkouts to the unified one.
 *
 * Nothing here is a rewrite of the flows it intercepts — the buy panel, the
 * coin shelf and the membership cards all keep their own markup, their own
 * prices and their own buttons. Only the last step changes: instead of filling
 * a WooCommerce cart or opening the PMPro checkout, each one now writes what
 * was picked into the unified cart and points the browser at this plugin's
 * checkout page.
 *
 * Every hook here is registered only while the system is switched on, so
 * turning it off leaves the site behaving exactly as it did before.
 */
class Jws_Payment_Router {

	public function register() {

		if ( ! Jws_Payment_Settings::is_enabled() ) {
			return;
		}

		/*
		 * Priority 1, ahead of the theme's own handler at 10. Both of these
		 * answer with wp_send_json_*(), which exits — so the handler being
		 * replaced never runs, and it does not have to be unhooked from a
		 * class whose instance we do not hold.
		 */
		add_action( 'wp_ajax_jws_add_ticket_to_cart', array( $this, 'buy_ticket' ), 1 );
		add_action( 'wp_ajax_jws_drama_add_package', array( $this, 'coin_package' ), 1 );

		/*
		 * The theme's buy button posts first and then navigates to whatever
		 * `jws_script.checkout_url` held at page load — a value baked in long
		 * before anyone clicked. Overriding it is what actually moves that
		 * flow; intercepting the AJAX alone would leave the browser going to
		 * the WooCommerce checkout with an empty cart.
		 *
		 * PHP_INT_MAX because the theme registers `jws-main` from its own
		 * wp_enqueue_scripts callback at priority 100. Anything at or before
		 * that runs while the handle does not exist yet, and the inline script
		 * is dropped without a word.
		 */
		add_action( 'wp_enqueue_scripts', array( $this, 'override_checkout_url' ), PHP_INT_MAX );

		add_action( 'template_redirect', array( $this, 'divert_pmpro_checkout' ), 5 );

		/* The drama buy panel renders plan cards as links straight to the PMPro
		   checkout; pointing them here saves a redirect. */
		add_filter( 'jws_drama_plan_checkout_url', array( $this, 'plan_url' ), 10, 2 );
	}

	/* ---------------------------------------------------------------------- */
	/* Buy / rent                                                              */
	/* ---------------------------------------------------------------------- */

	/**
	 * Replaces the theme's "add ticket to WooCommerce cart" endpoint.
	 *
	 * The response keeps the shape the theme's script expects — a success with
	 * a message — because that script is in the plugin's own asset bundle and
	 * navigates to `jws_script.checkout_url` on its own.
	 */
	public function buy_ticket() {

		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'buy';

		$errors = new WP_Error();

		if ( ! check_ajax_referer( 'buy_ticket_' . $id . $type, 'nonce', false ) ) {
			$errors->add( 'secure_error', esc_html__( 'Security checking', 'jws_streamvid' ) );
			wp_send_json_error( $errors );
		}

		if ( ! is_user_logged_in() ) {
			$errors->add( 'not_logged_in', esc_html__( 'You must be logged in to buy or rent videos', 'jws_streamvid' ) );
			wp_send_json_error( $errors );
		}

		$item = Jws_Payment_Items::resolve( $type, $id );

		if ( is_wp_error( $item ) ) {
			$errors->add( 'error_item', $item->get_error_message() );
			wp_send_json_error( $errors );
		}

		Jws_Payment_Checkout::set_cart( get_current_user_id(), $type, $id, get_permalink( $id ) );

		wp_send_json_success(
			array(
				'message'   => esc_html__( 'Redirecting to checkout page', 'jws_streamvid' ),
				'cart_page' => Jws_Payment_Checkout::page_url(),
			)
		);
	}

	/**
	 * Points the theme's hard-wired checkout URL at this checkout.
	 *
	 * Added after the theme has localized `jws_script`, so it overwrites the
	 * WooCommerce URL already sitting in it rather than being overwritten by
	 * it. `jws-main` is the theme's handle for that data.
	 */
	public function override_checkout_url() {

		$script = sprintf(
			'if (window.jws_script) { window.jws_script.checkout_url = %s; }',
			wp_json_encode( Jws_Payment_Checkout::page_url() )
		);

		if ( wp_script_is( 'jws-main', 'registered' ) ) {
			wp_add_inline_script( 'jws-main', $script );

			return;
		}

		/*
		 * No such handle — the theme renamed it, or registers it somewhere this
		 * never runs before. wp_add_inline_script() would return false and drop
		 * the override without a word, which is exactly the failure that is
		 * impossible to notice: every button keeps working and quietly sends
		 * the buyer to the WooCommerce cart. Printing it at the end of the
		 * footer instead cannot be dropped, and the buy button reads the value
		 * when it is clicked, long after.
		 */
		add_action(
			'wp_footer',
			function () use ( $script ) {
				wp_print_inline_script_tag( $script );
			},
			PHP_INT_MAX
		);
	}

	/* ---------------------------------------------------------------------- */
	/* Coins                                                                   */
	/* ---------------------------------------------------------------------- */

	/**
	 * Replaces the drama module's "add coin package to WooCommerce cart".
	 *
	 * The package is still looked up server-side from its index, so the browser
	 * only ever says which row was clicked — a tampered form cannot buy 10,000
	 * coins for a penny any more than it could before.
	 */
	public function coin_package() {

		check_ajax_referer( 'jws_drama_unlock', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array( 'reason' => 'not_logged_in', 'message' => esc_html__( 'Please sign in first.', 'jws_streamvid' ) ),
				401
			);
		}

		$index = isset( $_POST['package'] ) ? absint( $_POST['package'] ) : -1;
		$item  = Jws_Payment_Items::resolve( 'coins', $index );

		if ( is_wp_error( $item ) ) {
			wp_send_json_error( array( 'message' => $item->get_error_message() ) );
		}

		Jws_Payment_Checkout::set_cart( get_current_user_id(), 'coins', $index, wp_get_referer() );

		wp_send_json_success(
			array(
				'redirect' => Jws_Payment_Checkout::page_url(),
				'coins'    => isset( $item['meta']['coins'] ) ? (int) $item['meta']['coins'] : 0,
			)
		);
	}

	/* ---------------------------------------------------------------------- */
	/* Membership                                                              */
	/* ---------------------------------------------------------------------- */

	/**
	 * Sends the PMPro checkout page here instead.
	 *
	 * Only the checkout page — PMPro's levels, account, billing, invoice and
	 * cancellation pages are left exactly as they are, because PMPro still owns
	 * memberships. And only for a level that costs something: a free level has
	 * no payment to take, so PMPro's own checkout remains the right place to
	 * sign someone up for it.
	 */
	public function divert_pmpro_checkout() {

		if ( ! function_exists( 'pmpro_is_checkout' ) || ! pmpro_is_checkout() ) {
			return;
		}

		/*
		 * Left to whatever already handles a signed-out visitor here — the
		 * drama module turns them back to the login screen with a return URL,
		 * and PMPro's own guest checkout is the fallback. Diverting first would
		 * replace that with a bare "please sign in" and lose the level they
		 * clicked.
		 */
		if ( ! is_user_logged_in() ) {
			return;
		}

		$level_id = $this->requested_level();

		if ( ! $level_id ) {
			return;
		}

		$item = Jws_Payment_Items::resolve( 'membership', $level_id );

		/* No price, or a level PMPro knows and we do not: leave it to PMPro. */
		if ( is_wp_error( $item ) ) {
			return;
		}

		$account = function_exists( 'pmpro_url' ) ? pmpro_url( 'account' ) : '';

		Jws_Payment_Checkout::set_cart( get_current_user_id(), 'membership', $level_id, $account );

		wp_safe_redirect( Jws_Payment_Checkout::url_for( 'membership', $level_id, $account ) );
		exit;
	}

	/**
	 * Which level the PMPro checkout was opened for.
	 *
	 * PMPro has accepted both spellings across its versions and the theme's own
	 * templates use `pmpro_level`, so both are read.
	 */
	private function requested_level() {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		foreach ( array( 'level', 'pmpro_level' ) as $key ) {
			if ( ! empty( $_REQUEST[ $key ] ) ) {
				return absint( $_REQUEST[ $key ] );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return 0;
	}

	/** Lets anything rendering a plan card link straight here. */
	public function plan_url( $url, $level_id ) {

		$item = Jws_Payment_Items::resolve( 'membership', $level_id );

		return is_wp_error( $item ) ? $url : Jws_Payment_Checkout::url_for( 'membership', $level_id );
	}
}
