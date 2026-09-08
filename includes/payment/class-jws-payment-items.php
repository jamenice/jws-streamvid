<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * What is being bought, and what it costs.
 *
 * Everything the checkout charges comes through resolve(): the browser only
 * ever says *which* thing was clicked, and every number — price, coin amount,
 * rental length, billing cycle — is read back here from the same place the
 * front end priced it from. A tampered form can therefore pick a different
 * item, which is harmless, but never a different price.
 *
 * The four kinds map onto the three checkouts being replaced:
 *
 *   membership          → a Paid Memberships Pro level
 *   buy / rent / live   → a video post, priced from its own meta
 *   coins               → a package row from the Drama Coins settings
 */
class Jws_Payment_Items {

	/** The item types the checkout knows how to sell. */
	public static function types() {
		return array( 'membership', 'buy', 'rent', 'live', 'coins' );
	}

	/**
	 * Everything the checkout needs about one purchasable thing.
	 *
	 * @param string $type One of types().
	 * @param int    $id   Level id, post id, or package index.
	 * @return array|WP_Error
	 */
	public static function resolve( $type, $id ) {

		$type = sanitize_key( $type );
		$id   = (int) $id;

		if ( ! in_array( $type, self::types(), true ) ) {
			return new WP_Error( 'jws_payment_type', esc_html__( 'That is not something you can buy here.', 'jws_streamvid' ) );
		}

		$item = 'membership' === $type
			? self::resolve_membership( $id )
			: ( 'coins' === $type ? self::resolve_coins( $id ) : self::resolve_video( $type, $id ) );

		if ( is_wp_error( $item ) ) {
			return $item;
		}

		if ( (float) $item['amount'] <= 0 ) {
			return new WP_Error( 'jws_payment_price', esc_html__( 'This item has no price set, so it cannot be sold.', 'jws_streamvid' ) );
		}

		/*
		 * The fingerprint covers the money and nothing else, so that a Stripe
		 * Price or a PayPal Plan provisioned for it can be reused by any other
		 * plan billing the same way — and, more importantly, so that repricing
		 * a level provisions a new one rather than quietly repricing everybody
		 * already subscribed.
		 */
		$item['fingerprint'] = md5(
			implode(
				'|',
				array(
					$item['currency'],
					$item['type'],
					number_format( (float) $item['amount'], 2, '.', '' ),
					number_format( (float) $item['renew'], 2, '.', '' ),
					$item['recurring'] ? '1' : '0',
					$item['period'],
					(int) $item['cycle'],
				)
			)
		);

		return $item;
	}

	/** The shape every resolver fills in, so callers never test for a key. */
	private static function base( $type, $id ) {

		return array(
			'type'      => $type,
			'item_id'   => (int) $id,
			'label'     => '',
			/* What is charged today. */
			'amount'    => 0.0,
			/* What is charged on every renewal after that; equal to `amount`
			   unless the plan has an intro price. */
			'renew'     => 0.0,
			'currency'  => Jws_Payment_Settings::currency(),
			'recurring' => false,
			'period'    => 'month',
			'cycle'     => 1,
			'meta'      => array(),
		);
	}

	/* ---------------------------------------------------------------------- */
	/* Membership                                                              */
	/* ---------------------------------------------------------------------- */

	/**
	 * A PMPro level, read straight off PMPro's own table.
	 *
	 * PMPro stays the owner of the level and of access; all this does is
	 * describe the money so the gateway can take it.
	 */
	private static function resolve_membership( $level_id ) {

		if ( ! function_exists( 'pmpro_getLevel' ) ) {
			return new WP_Error( 'jws_payment_no_pmpro', esc_html__( 'Memberships are unavailable right now.', 'jws_streamvid' ) );
		}

		$level = pmpro_getLevel( $level_id );

		if ( ! $level || empty( $level->id ) ) {
			return new WP_Error( 'jws_payment_level', esc_html__( 'That membership level does not exist.', 'jws_streamvid' ) );
		}

		$item = self::base( 'membership', $level->id );

		$initial   = (float) $level->initial_payment;
		$recurring = (float) $level->billing_amount;
		$cycle     = (int) $level->cycle_number;
		$period    = strtolower( (string) $level->cycle_period );

		$item['label']     = (string) $level->name;
		$item['recurring'] = $recurring > 0 && $cycle > 0 && in_array( $period, array( 'day', 'week', 'month', 'year' ), true );

		if ( $item['recurring'] ) {
			$item['amount'] = $initial;
			$item['renew']  = $recurring;
			$item['period'] = $period;
			$item['cycle']  = $cycle;
		} else {
			/* A level that bills nothing on renewal has only the one price —
			   and a level whose initial payment is zero but which does bill
			   later is charged its billing amount up front instead of asking
			   the gateway to take nothing. */
			$item['amount'] = $initial > 0 ? $initial : $recurring;
			$item['renew']  = $item['amount'];
		}

		$item['meta'] = array(
			'level_id'          => (int) $level->id,
			'billing_limit'     => (int) $level->billing_limit,
			'expiration_number' => (int) $level->expiration_number,
			'expiration_period' => (string) $level->expiration_period,
		);

		return $item;
	}

	/* ---------------------------------------------------------------------- */
	/* Buy / rent / live                                                       */
	/* ---------------------------------------------------------------------- */

	/**
	 * One video, priced from the same post meta the buy panel reads.
	 *
	 * The three types are three prices on the same post, which is why they
	 * share a resolver: `buy` and `live` are permanent, `rent` expires.
	 */
	private static function resolve_video( $type, $post_id ) {

		$post = get_post( $post_id );

		if ( ! $post || 'publish' !== $post->post_status ) {
			return new WP_Error( 'jws_payment_video', esc_html__( 'That title is not available.', 'jws_streamvid' ) );
		}

		$item = self::base( $type, $post_id );

		$price_key = 'rent' === $type ? 'rent_price' : ( 'live' === $type ? 'videos_price' : 'buy_price' );
		$price     = get_post_meta( $post_id, $price_key, true );

		$item['amount'] = is_numeric( $price ) ? (float) $price : 0.0;
		$item['renew']  = $item['amount'];

		$labels = array(
			'buy'  => esc_html__( 'Buy: %s', 'jws_streamvid' ),
			'rent' => esc_html__( 'Rent: %s', 'jws_streamvid' ),
			'live' => esc_html__( 'Buy Live: %s', 'jws_streamvid' ),
		);

		$item['label'] = sprintf( $labels[ $type ], get_the_title( $post_id ) );

		if ( 'rent' === $type ) {

			$days = get_post_meta( $post_id, 'rent_day', true );

			$item['meta']['days']  = is_numeric( $days ) ? (int) $days : (int) self::theme_option( 'rent_days', 2 );
			$item['meta']['delay'] = (int) self::theme_option( 'rent_delay', 3 );
		}

		$item['meta']['thumbnail_id'] = (int) get_post_thumbnail_id( $post_id );

		return $item;
	}

	/* ---------------------------------------------------------------------- */
	/* Coins                                                                   */
	/* ---------------------------------------------------------------------- */

	/**
	 * One coin package, looked up by the index the shelf rendered it under.
	 *
	 * Both numbers that matter — the coins granted and the price — come from
	 * the settings row, never from the request, exactly as the WooCommerce path
	 * did before this checkout existed.
	 */
	private static function resolve_coins( $index ) {

		if ( ! class_exists( 'Jws_Drama_Settings' ) ) {
			return new WP_Error( 'jws_payment_no_drama', esc_html__( 'Coins are unavailable right now.', 'jws_streamvid' ) );
		}

		foreach ( Jws_Drama_Settings::packages() as $package ) {

			if ( (int) $package['index'] !== (int) $index ) {
				continue;
			}

			$item = self::base( 'coins', $index );

			$item['amount'] = (float) $package['price'];
			$item['renew']  = $item['amount'];
			$item['label']  = sprintf(
				/* translators: %s: coin amount */
				esc_html__( '%s coins', 'jws_streamvid' ),
				number_format_i18n( (int) $package['total'] )
			);

			$item['meta'] = array(
				'coins' => (int) $package['total'],
				'base'  => (int) $package['base'],
				'bonus' => (int) $package['bonus'],
			);

			/* Coins are priced in the drama module's own currency, which is
			   what the shelf quoted; charging a different one would show the
			   buyer one number and take another. */
			$item['currency'] = strtoupper( (string) Jws_Drama_Settings::get( 'currency', $item['currency'] ) );

			return $item;
		}

		return new WP_Error( 'jws_payment_package', esc_html__( 'That package is no longer on sale.', 'jws_streamvid' ) );
	}

	/* ---------------------------------------------------------------------- */
	/* Helpers                                                                 */
	/* ---------------------------------------------------------------------- */

	/** The theme's option reader, when the theme is the one that has it. */
	private static function theme_option( $key, $fallback ) {

		if ( ! function_exists( 'jws_theme_get_option' ) ) {
			return $fallback;
		}

		$value = jws_theme_get_option( $key, $fallback );

		return is_numeric( $value ) ? $value : $fallback;
	}

	/**
	 * A price the way the site writes prices elsewhere.
	 *
	 * WooCommerce's formatter is preferred because every price already on the
	 * buy panel came from it, and a checkout that renders "$9.99" beside a
	 * panel that said "9,99 $" reads like a different shop.
	 */
	public static function format_price( $amount, $currency = '' ) {

		$currency = $currency ? strtoupper( $currency ) : Jws_Payment_Settings::currency();

		if ( function_exists( 'wc_price' ) ) {
			/*
			 * Decoded, not just stripped: wc_price() writes currency symbols as
			 * entities ("&#036;"), and every caller escapes what it gets back —
			 * which would turn that into a literal "&#036;" on the page.
			 */
			return html_entity_decode(
				wp_strip_all_tags( wc_price( (float) $amount, array( 'currency' => $currency ) ) ),
				ENT_QUOTES,
				'UTF-8'
			);
		}

		return $currency . ' ' . number_format_i18n( (float) $amount, 2 );
	}

	/**
	 * "every month", "every 2 weeks" — how often a recurring item bills.
	 */
	public static function period_phrase( $period, $cycle ) {

		$cycle = max( 1, (int) $cycle );

		$singular = array(
			'day'   => esc_html__( 'day', 'jws_streamvid' ),
			'week'  => esc_html__( 'week', 'jws_streamvid' ),
			'month' => esc_html__( 'month', 'jws_streamvid' ),
			'year'  => esc_html__( 'year', 'jws_streamvid' ),
		);

		$plural = array(
			'day'   => esc_html__( '%d days', 'jws_streamvid' ),
			'week'  => esc_html__( '%d weeks', 'jws_streamvid' ),
			'month' => esc_html__( '%d months', 'jws_streamvid' ),
			'year'  => esc_html__( '%d years', 'jws_streamvid' ),
		);

		$period = isset( $singular[ $period ] ) ? $period : 'month';

		return 1 === $cycle ? $singular[ $period ] : sprintf( $plural[ $period ], $cycle );
	}
}
