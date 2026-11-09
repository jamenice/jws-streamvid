<?php

/**
 * Kids mode: what changes around the content while a Kids profile is in use.
 *
 * Jws_Profile_Filter decides which titles a profile sees. This class keeps
 * a Kids profile out of everything else that is not for children, on the
 * server rather than by hiding buttons:
 *
 * - account, membership, checkout and upload pages redirect home;
 * - on the profile page only History, Watchlist and Favorites open;
 * - comments and reviews are closed, WooCommerce will not add to the cart;
 * - a bar at the top says which Kids profile is watching, with Exit Kids,
 *   and explains a redirect away from a title or page (?jws_blocked=).
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Kids_Mode {

	/** Profile page tabs a Kids profile can open. */
	const PROFILE_TABS = array( 'history', 'watchlist', 'favorites' );

	public static function is_active() {
		$restriction = Jws_Profile_Filter::restriction();
		return $restriction && $restriction['kids'];
	}

	/**
	 * Redirects a Kids profile away from pages that are not for it. Runs
	 * after Jws_Profile_Filter::guard_request(), which handles titles.
	 */
	public static function guard_request() {

		if ( ! self::is_active() || Jws_Profiles_Front::screen() ) {
			return;
		}

		if ( is_author() ) {
			$author = (int) get_queried_object_id();

			if ( get_current_user_id() !== $author ) {
				self::redirect( home_url( '/' ) );
			}
			if ( ! self::on_allowed_profile_tab() ) {
				self::redirect( jws_streamvid()->get()->profile->get_endpoint( $author, 'history', '' ), false );
			}
			return;
		}

		if ( self::is_account_page() ) {
			self::redirect( home_url( '/' ) );
		}
	}

	/** Closes comments and reviews while a Kids profile is in use. */
	public static function comments_open( $open ) {
		return self::is_active() ? false : $open;
	}

	/** Refuses WooCommerce add-to-cart (buying a ticket, a title, a product) for a Kids profile. */
	public static function add_to_cart( $passed ) {

		if ( ! self::is_active() ) {
			return $passed;
		}

		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( __( 'Buying is not available on a Kids profile.', 'jws_streamvid' ), 'error' );
		}

		return false;
	}

	/** Prints the Kids bar, and for any restricted profile the "not available" notice. */
	public static function print_bar() {

		if ( Jws_Profiles_Front::screen() || ! Jws_Profile_Filter::restriction() ) {
			return;
		}

		jws_streamvid_load_template( 'user/profiles/kids-bar.php', false );
	}

	/** @return string Message for ?jws_blocked=, or ''. */
	public static function blocked_message() {

		$blocked = isset( $_GET['jws_blocked'] ) ? sanitize_key( wp_unslash( $_GET['jws_blocked'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' === $blocked ) {
			return '';
		}

		$profile = Jws_Profiles::current_profile();
		$name    = $profile ? $profile->name : '';

		if ( 'page' === $blocked ) {
			return __( 'That page is not available on a Kids profile.', 'jws_streamvid' );
		}

		/* translators: %s: profile name */
		return sprintf( __( 'That title is not available on %s\'s profile.', 'jws_streamvid' ), $name );
	}

	/** Short text for the ratings a profile shows, such as "G and PG". */
	public static function ratings_text( $max ) {

		$texts = array(
			0  => __( 'Showing titles rated G', 'jws_streamvid' ),
			7  => __( 'Showing titles rated G and PG', 'jws_streamvid' ),
			13 => __( 'Showing titles rated PG-13 and below', 'jws_streamvid' ),
			17 => __( 'Showing titles rated R and below', 'jws_streamvid' ),
		);

		return isset( $texts[ (int) $max ] ) ? $texts[ (int) $max ] : '';
	}

	/* ------------------------------------------------------------------ *
	 * Internals
	 * ------------------------------------------------------------------ */

	private static function redirect( $url, $with_notice = true ) {
		wp_safe_redirect( $with_notice ? add_query_arg( 'jws_blocked', 'page', $url ) : $url );
		exit;
	}

	private static function on_allowed_profile_tab() {

		global $wp_query;

		foreach ( self::PROFILE_TABS as $tab ) {
			if ( isset( $wp_query->query_vars[ $tab ] ) ) {
				return true;
			}
		}

		return false;
	}

	/** Membership, checkout, cart, WooCommerce account, upload and go-live pages. */
	private static function is_account_page() {

		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
			return true;
		}

		if ( ! is_page() ) {
			return false;
		}

		$page_ids = array();

		foreach ( array( 'checkout', 'levels', 'billing', 'cancel', 'account', 'invoice', 'confirmation' ) as $pmpro_page ) {
			$page_ids[] = (int) get_option( 'pmpro_' . $pmpro_page . '_page_id' );
		}

		if ( function_exists( 'jws_theme_get_option' ) ) {
			$page_ids[] = (int) jws_theme_get_option( 'select-update-level' );
		}

		$streamvid = jws_streamvid()->get();

		foreach ( array( $streamvid->videos->get_upload_page_url(), $streamvid->live_videos->get_create_page_url() ) as $url ) {
			if ( $url ) {
				$page_ids[] = (int) url_to_postid( $url );
			}
		}

		/**
		 * Filters the page ids a Kids profile is sent away from.
		 *
		 * @param int[] $page_ids Membership, upgrade, upload and go-live pages.
		 */
		$page_ids = array_filter( (array) apply_filters( 'jws_kids_blocked_page_ids', $page_ids ) );

		return $page_ids && is_page( $page_ids );
	}
}

add_action( 'template_redirect', array( 'Jws_Kids_Mode', 'guard_request' ), 6 );
add_filter( 'comments_open', array( 'Jws_Kids_Mode', 'comments_open' ), 20 );
add_filter( 'woocommerce_add_to_cart_validation', array( 'Jws_Kids_Mode', 'add_to_cart' ), 20 );
add_action( 'wp_body_open', array( 'Jws_Kids_Mode', 'print_bar' ), 5 );
