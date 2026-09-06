<?php

/**
 * Every coin-system option, on one screen.
 *
 * The numbers that drive unlocking used to be spread across Theme Options
 * (Redux), a WooCommerce product list and a PMPro level picker — three places,
 * none of them owned by this module. They all live here now, in one option row,
 * so the module can be moved to a site running neither plugin.
 *
 * Storage is a single nested array rather than a row per setting: the whole
 * thing is read on almost every drama request, and one autoloaded option is one
 * lookup instead of a dozen.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/drama
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Drama_Settings {

	const OPTION = 'jws_drama_settings';

	/** Set once the old Redux/PMPro values have been carried over. */
	const OPTION_MIGRATED = 'jws_drama_settings_migrated';

	const PAGE  = 'jws-drama-coins';
	const NONCE = 'jws_drama_settings_save';

	/** Nonce action for the Demo Import tab's own form. */
	const DEMO_NONCE = 'jws_drama_demo_action';

	/** Marks a drama/episode created by the Demo Import tool, so it can be found and trashed again. */
	const DEMO_META = '_jws_drama_demo_seed';

	/** Filled by all() so a request reads the option once. */
	private static $cache = null;

	public static function defaults() {

		return array(
			/* Master switch: off hides the drama post types, front-end pages
			   and widgets everywhere on the site while this settings screen
			   stays reachable to turn it back on. */
			'enabled'       => 1,

			'free_episodes' => 3,
			'coin_price'    => 10,
			'currency'      => 'USD',

			/*
			 * VIP is sold by Paid Memberships Pro: the levels ticked here are
			 * both what the buy panel offers and what skips the coin wall, so
			 * the shelf and the access rule can never describe different plans.
			 */
			'pmpro_levels'  => array(),

			/*
			 * The WooCommerce product coin packages are bought through — one
			 * carrier for every package, the way pay-per-view sells every
			 * rental through a single ticket product. The row's own price and
			 * coin amount ride on the cart item, so adding a package never
			 * means creating another product.
			 */
			'coin_product'  => 0,

			/* Attachment ID for the coin glyph next to every coin amount
			   (wallet, prices, package cards). 0 keeps the CSS-drawn gold
			   circle that ships as the default. */
			'coin_icon'     => 0,

			'packages'      => array(),
			'plans'         => array(),

			/*
			 * Which buttons the modal offers. Three of the four are Stripe:
			 * quick_pay is its hosted Checkout page, apple_pay and google_pay
			 * are the browser's own sheet driven by the same account. They are
			 * listed separately because a shopper picks between them, and
			 * because a site may want the card page without the wallets.
			 */
			'methods'       => array( 'apple_pay', 'google_pay', 'paypal', 'quick_pay' ),

			/*
			 * Gateway objects this site has already provisioned, as
			 * fingerprint => array( stripe_price, stripe_coupon, paypal_plan ).
			 *
			 * A Stripe Price and a PayPal Plan are a function of a plan's
			 * economics, not of the row it is edited in, so they are keyed by a
			 * hash of those numbers rather than stored on the row: reordering
			 * or deleting a plan cannot then point a charge at the wrong money,
			 * and two plans priced the same reuse one object. Written by the
			 * gateway layer, never by the form.
			 */
			'gateway_refs'  => array(),

			'stripe'        => array(
				'enabled'        => 0,
				'mode'           => 'test',
				'publishable'    => '',
				'secret'         => '',
				'webhook_secret' => '',
				/* Two-letter country of the Stripe account. The wallet sheet
				   refuses to open without it. */
				'country'        => 'US',
			),

			'paypal'        => array(
				'enabled'    => 0,
				'mode'       => 'sandbox',
				'client_id'  => '',
				'secret'     => '',
				'webhook_id' => '',
			),
		);
	}

	/** The whole settings array, defaults filled in. */
	public static function all() {

		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$settings = array_merge( self::defaults(), $stored );

		// Nested groups merge one level down or a partial save would drop keys.
		foreach ( array( 'stripe', 'paypal' ) as $group ) {
			$settings[ $group ] = array_merge(
				self::defaults()[ $group ],
				is_array( $settings[ $group ] ) ? $settings[ $group ] : array()
			);
		}

		self::$cache = $settings;

		return $settings;
	}

	public static function get( $key, $fallback = null ) {

		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	/** Whether the module should register its post types and front end at all. */
	public static function is_enabled() {

		return ! empty( self::get( 'enabled', 1 ) );
	}

	public static function save( array $settings ) {

		self::$cache = null;

		update_option( self::OPTION, $settings );
	}

	/* ---------------------------------------------------------------------- */
	/* Coin icon                                                               */
	/* ---------------------------------------------------------------------- */

	/** Empty until an admin picks one under General → Coin icon. */
	public static function coin_icon_url() {

		$id = (int) self::get( 'coin_icon', 0 );

		if ( ! $id ) {
			return '';
		}

		$src = wp_get_attachment_image_src( $id, array( 40, 40 ) );

		return $src ? $src[0] : '';
	}

	/**
	 * The markup every `.sv-coin-ico` spot in the coin-wallet templates
	 * prints, so the fallback (CSS-drawn gold circle) and the admin's own
	 * image live in one place instead of four.
	 */
	public static function coin_icon_html() {

		$url = self::coin_icon_url();

		if ( ! $url ) {
			return '<span class="sv-coin-ico" aria-hidden="true"></span>';
		}

		return '<img class="sv-coin-ico sv-coin-ico--custom" src="' . esc_url( $url ) . '" alt="" aria-hidden="true" />';
	}

	/* ---------------------------------------------------------------------- */
	/* Reading the lists                                                       */
	/* ---------------------------------------------------------------------- */

	/**
	 * Coin packages a buyer can see, in the order the admin arranged them.
	 *
	 * Each row is normalised here rather than at every call site: `total` and
	 * `bonus_percent` are what the modal prints, and both are derived, so
	 * nothing stores a number that could disagree with the two it came from.
	 */
	public static function package_defaults() {
		return array( 'base' => 0, 'bonus' => 0, 'price' => '0.00', 'active' => 1 );
	}

	public static function plan_defaults() {
		return array(
			'name'          => '',
			'period'        => 'month',
			'cycle'         => 1,
			'price'         => '0.00',
			'intro'         => '',
			'features'      => '',
			'active'        => 1,
		);
	}

	public static function packages( $include_inactive = false ) {

		$out = array();

		foreach ( (array) self::get( 'packages', array() ) as $index => $row ) {

			$row = wp_parse_args( (array) $row, self::package_defaults() );

			if ( ! $include_inactive && empty( $row['active'] ) ) {
				continue;
			}

			$base  = max( 0, (int) $row['base'] );
			$bonus = max( 0, (int) $row['bonus'] );

			if ( $base <= 0 ) {
				continue;
			}

			$out[] = array(
				'index'         => (int) $index,
				'base'          => $base,
				'bonus'         => $bonus,
				'total'         => $base + $bonus,
				'bonus_percent' => (int) round( $bonus / $base * 100 ),
				'price'         => (float) $row['price'],
				'fingerprint'   => self::fingerprint( 'package', $row ),
				'active'        => ! empty( $row['active'] ),
			);
		}

		return $out;
	}

	/**
	 * A stable key for one priced thing.
	 *
	 * Everything a gateway needs to build its object goes in, and nothing else:
	 * renaming a plan or moving it up the list must not orphan the Price that is
	 * already charging people, while changing what it costs must.
	 */
	public static function fingerprint( $kind, array $row ) {

		$parts = 'plan' === $kind
			? array( $row['period'], (int) $row['cycle'], $row['price'], $row['intro'] )
			: array( (int) $row['base'], (int) $row['bonus'], $row['price'] );

		array_unshift( $parts, $kind, self::get( 'currency', 'USD' ) );

		return substr( md5( implode( '|', $parts ) ), 0, 16 );
	}

	/**
	 * VIP plans, with the intro-price discount worked out.
	 *
	 * `price` is what renews, `intro` what the first cycle costs. That pair is
	 * the whole of "$5.99 for the first week, then $12.99/week" and the "54%
	 * OFF" badge, so neither the sentence nor the badge is stored.
	 */
	public static function plans( $include_inactive = false ) {

		$out = array();

		foreach ( (array) self::get( 'plans', array() ) as $index => $row ) {

			$row = wp_parse_args( (array) $row, self::plan_defaults() );

			if ( ! $include_inactive && empty( $row['active'] ) ) {
				continue;
			}

			$price = (float) $row['price'];
			$intro = '' === $row['intro'] ? $price : (float) $row['intro'];

			if ( $price <= 0 ) {
				continue;
			}

			$out[] = array(
				'index'         => (int) $index,
				'name'          => (string) $row['name'],
				'period'        => (string) $row['period'],
				'cycle'         => max( 1, (int) $row['cycle'] ),
				'price'         => $price,
				'intro'         => $intro,
				'has_intro'     => $intro < $price,
				'off_percent'   => $intro < $price ? (int) round( ( 1 - $intro / $price ) * 100 ) : 0,
				'features'      => array_values( array_filter( array_map( 'trim', explode( "\n", (string) $row['features'] ) ) ) ),
				'fingerprint'   => self::fingerprint( 'plan', $row ),
				'active'        => ! empty( $row['active'] ),
			);
		}

		return $out;
	}

	/**
	 * The VIP shelf, straight off Paid Memberships Pro.
	 *
	 * Only the levels ticked under General are returned, because that tick is
	 * what actually unlocks the catalogue — offering a level that does not
	 * would sell access nobody gets. Shaped like plans() so the buy panel
	 * renders either source without knowing which it has.
	 *
	 * PMPro's own fields do the work the old rows stored by hand:
	 * `initial_payment` is the first charge and `billing_amount` what renews,
	 * so an intro offer is just the two disagreeing.
	 *
	 * @return array
	 */
	public static function pmpro_plans() {

		if ( ! function_exists( 'pmpro_getAllLevels' ) || ! function_exists( 'pmpro_url' ) ) {
			return array();
		}

		$allowed = array_map( 'intval', (array) self::get( 'pmpro_levels', array() ) );

		if ( ! $allowed ) {
			return array();
		}

		$out = array();

		foreach ( (array) pmpro_getAllLevels( true ) as $level ) {

			$id = isset( $level->id ) ? (int) $level->id : 0;

			if ( ! $id || ! in_array( $id, $allowed, true ) ) {
				continue;
			}

			$initial   = (float) $level->initial_payment;
			$recurring = (float) $level->billing_amount;

			/* A one-off level bills nothing on renewal, so its "renews at" is
			   the only price it has. */
			$price = $recurring > 0 ? $recurring : $initial;
			$intro = $initial;

			if ( $price <= 0 ) {
				continue;
			}

			$period = strtolower( (string) $level->cycle_period );
			$period = in_array( $period, array_keys( self::period_labels() ), true ) ? $period : 'month';

			$out[] = array(
				'index'       => $id,
				'level_id'    => $id,
				'name'        => (string) $level->name,
				'period'      => $period,
				'cycle'       => max( 1, (int) $level->cycle_number ),
				'price'       => $price,
				'intro'       => $intro,
				'has_intro'   => $intro < $price,
				'off_percent' => $intro < $price ? (int) round( ( 1 - $intro / $price ) * 100 ) : 0,
				/* PMPro's description is rich text meant for its own page; one
				   line per feature is all the card has room for. */
				'features'    => array_values( array_filter( array_map( 'trim', explode( "\n", wp_strip_all_tags( (string) $level->description ) ) ) ) ),
				'recurring'   => $recurring > 0,
				'url'         => pmpro_url( 'checkout', '?level=' . $id ),
				'active'      => true,
			);
		}

		return $out;
	}

	/**
	 * Prices from PMPro are in the store's own currency, not the code kept in
	 * these settings — it charges through its own gateway, so its formatter is
	 * the honest one to print with.
	 */
	public static function format_level_price( $amount ) {

		if ( function_exists( 'pmpro_formatPrice' ) ) {
			return pmpro_formatPrice( (float) $amount );
		}

		return self::format_price( $amount );
	}

	/**
	 * A coin package's price, as WooCommerce will charge it.
	 *
	 * The cart is what takes the money, so its currency and formatting are the
	 * ones the shelf has to quote — printing this module's own currency code
	 * beside a Woo checkout is how a shopper ends up seeing two prices.
	 */
	public static function format_cart_price( $amount ) {

		if ( function_exists( 'wc_price' ) ) {
			return wp_strip_all_tags( wc_price( (float) $amount ) );
		}

		return self::format_price( $amount );
	}

	/**
	 * A price as the buyer reads it.
	 *
	 * Deliberately small: the modal says "local currency for reference only",
	 * and a full locale-aware formatter would imply a conversion that never
	 * happens — the gateway always charges the configured currency.
	 */
	public static function format_price( $amount, $currency = null ) {

		$currency = $currency ? $currency : self::get( 'currency', 'USD' );

		$symbols = array(
			'USD' => '$',
			'EUR' => '€',
			'GBP' => '£',
			'VND' => '₫',
			'JPY' => '¥',
			'AUD' => 'A$',
			'CAD' => 'C$',
		);

		$amount = number_format( (float) $amount, 2, '.', ',' );

		return isset( $symbols[ $currency ] ) ? $symbols[ $currency ] . $amount : $currency . ' ' . $amount;
	}

	/** "week" or "2 months" — the tail of "then $12.99/…". */
	public static function period_phrase( $period, $cycle = 1 ) {

		$cycle  = max( 1, (int) $cycle );
		$labels = self::period_labels();
		$label  = isset( $labels[ $period ] ) ? strtolower( $labels[ $period ] ) : $period;

		return 1 === $cycle ? $label : $cycle . ' ' . $label . 's';
	}

	/**
	 * Every payment method, in the order the modal lists them.
	 *
	 * `gateway` is which set of keys pays for it; `flow` is what the click does,
	 * which is what the browser needs to know: a redirect leaves the page, a
	 * wallet opens the operating system's sheet over it.
	 */
	/* ---------------------------------------------------------------------- */
	/* Provisioned gateway objects                                             */
	/* ---------------------------------------------------------------------- */

	/**
	 * Where one gateway keeps what it has already built for one fingerprint.
	 *
	 * The mode is part of the key on purpose. A Stripe Price made with test
	 * keys does not exist to the live account, and a PayPal sandbox plan means
	 * nothing in production — a site that finishes testing and goes live must
	 * not hand either of them an id from the other side.
	 */
	/**
	 * Credentials the form must never render back.
	 *
	 * A password input still puts its value in the HTML, so anything here would
	 * sit in plain text in the page source, in the browser cache, and in any
	 * copy of that page anyone ever saves. They are write-only: blank means
	 * "leave it alone", and a new value replaces it.
	 */
	public static function secret_fields() {
		return array(
			'stripe' => array( 'secret', 'webhook_secret' ),
			'paypal' => array( 'secret' ),
		);
	}

	/** A credential shown as evidence it is set, not as something to copy. */
	public static function mask( $value ) {

		$value = (string) $value;

		if ( '' === $value ) {
			return '';
		}

		return str_repeat( '•', 8 ) . substr( $value, -4 );
	}

	public static function gateway_slot( $gateway, $mode, $fingerprint ) {
		return $gateway . ':' . $mode . ':' . $fingerprint;
	}

	public static function refs( $slot ) {

		$all = (array) self::get( 'gateway_refs', array() );

		return isset( $all[ $slot ] ) && is_array( $all[ $slot ] ) ? $all[ $slot ] : array();
	}

	public static function remember( $slot, $key, $value ) {

		$settings = self::all();

		if ( ! isset( $settings['gateway_refs'][ $slot ] ) || ! is_array( $settings['gateway_refs'][ $slot ] ) ) {
			$settings['gateway_refs'][ $slot ] = array();
		}

		$settings['gateway_refs'][ $slot ][ $key ] = $value;

		self::save( $settings );
	}

	/**
	 * Throws away what we thought a gateway had, so the next call rebuilds it.
	 *
	 * A cached id can stop resolving — deleted in a dashboard, or restored from
	 * a database dump taken against a different account. Left alone that is a
	 * plan nobody can ever buy again; forgetting it costs one extra create.
	 */
	public static function forget( $slot ) {

		$settings = self::all();

		unset( $settings['gateway_refs'][ $slot ] );

		self::save( $settings );
	}

	public static function method_catalog() {

		return array(
			'apple_pay'  => array(
				'label'   => esc_html__( 'Apple Pay', 'jws_streamvid' ),
				'gateway' => 'stripe',
				'flow'    => 'wallet',
			),
			'google_pay' => array(
				'label'   => esc_html__( 'Google Pay', 'jws_streamvid' ),
				'gateway' => 'stripe',
				'flow'    => 'wallet',
			),
			'paypal'     => array(
				'label'   => esc_html__( 'PayPal', 'jws_streamvid' ),
				'gateway' => 'paypal',
				'flow'    => 'redirect',
			),
			'quick_pay'  => array(
				'label'   => esc_html__( 'Quick Pay', 'jws_streamvid' ),
				'note'    => esc_html__( 'Card', 'jws_streamvid' ),
				'gateway' => 'stripe',
				'flow'    => 'redirect',
			),
		);
	}

	/**
	 * The methods this site can actually offer right now.
	 *
	 * Switched on by the admin *and* backed by a gateway that is switched on
	 * itself — a Stripe button with Stripe disabled is a button that can only
	 * fail. Whether the shopper's browser can do Apple or Google Pay is a
	 * question only the browser can answer, so that filter happens there.
	 */
	public static function available_methods() {

		$enabled = (array) self::get( 'methods', array() );
		$out     = array();

		foreach ( self::method_catalog() as $key => $method ) {

			if ( ! in_array( $key, $enabled, true ) ) {
				continue;
			}

			$gateway = self::get( $method['gateway'] );

			if ( empty( $gateway['enabled'] ) ) {
				continue;
			}

			$out[ $key ] = $method;
		}

		return $out;
	}

	public static function period_labels() {

		return array(
			'day'   => esc_html__( 'Day', 'jws_streamvid' ),
			'week'  => esc_html__( 'Week', 'jws_streamvid' ),
			'month' => esc_html__( 'Month', 'jws_streamvid' ),
			'year'  => esc_html__( 'Year', 'jws_streamvid' ),
		);
	}

	/* ---------------------------------------------------------------------- */
	/* Migration                                                               */
	/* ---------------------------------------------------------------------- */

	/**
	 * Carries the old Redux values over the first time this page is reached.
	 *
	 * Read straight out of the `jws_option` row rather than through
	 * jws_theme_get_option(): the theme fields are being removed in the same
	 * change, and Redux prunes keys it no longer knows about on the next save.
	 * A site that upgrades and saves Theme Options before ever opening this page
	 * would otherwise silently fall back to the shipped defaults.
	 */
	public static function maybe_migrate() {

		if ( get_option( self::OPTION_MIGRATED ) ) {
			return;
		}

		$theme    = get_option( 'jws_option', array() );
		$theme    = is_array( $theme ) ? $theme : array();
		$settings = self::all();

		if ( isset( $theme['drama_default_free_ep'] ) && is_numeric( $theme['drama_default_free_ep'] ) ) {
			$settings['free_episodes'] = (int) $theme['drama_default_free_ep'];
		}

		if ( isset( $theme['drama_default_coin_per_ep'] ) && is_numeric( $theme['drama_default_coin_per_ep'] ) ) {
			$settings['coin_price'] = (int) $theme['drama_default_coin_per_ep'];
		}

		if ( isset( $theme['drama_unlock_levels'] ) && is_array( $theme['drama_unlock_levels'] ) ) {
			$settings['pmpro_levels'] = array_map( 'intval', array_keys( array_filter( $theme['drama_unlock_levels'] ) ) );
		} elseif ( method_exists( 'Jws_Drama_Wallet', 'default_unlock_levels' ) ) {
			// Never touched the checkbox: keep what it was defaulting to.
			$settings['pmpro_levels'] = array_map( 'intval', array_keys( array_filter( Jws_Drama_Wallet::default_unlock_levels() ) ) );
		}

		/*
		 * Carry the WooCommerce coin products across as rows so the new shelf
		 * opens showing what the site actually sells, rather than empty. Base
		 * takes the whole coin amount: Woo only ever stored one number, so
		 * there is no bonus to recover, and splitting one is the admin's call.
		 */
		if ( ! $settings['packages'] && class_exists( 'Jws_Drama_Coins' ) && function_exists( 'wc_get_product' ) ) {

			foreach ( Jws_Drama_Coins::packages() as $product_post ) {

				$product = wc_get_product( $product_post->ID );
				$coins   = (int) get_post_meta( $product_post->ID, Jws_Drama_Coins::PRODUCT_META, true );

				if ( ! $product || $coins <= 0 ) {
					continue;
				}

				$settings['packages'][] = array(
					'base'   => $coins,
					'bonus'  => 0,
					'price'  => number_format( (float) $product->get_price(), 2, '.', '' ),
					'active' => 1,
				);
			}
		}

		$currency = get_option( 'woocommerce_currency', get_option( 'pmpro_currency', 'USD' ) );

		if ( $currency ) {
			$settings['currency'] = strtoupper( substr( (string) $currency, 0, 3 ) );
		}

		self::save( $settings );

		update_option( self::OPTION_MIGRATED, 1 );
	}

	/**
	 * The shelf from the design, ready to price up.
	 *
	 * Offered behind a button rather than shipped as a default: a default the
	 * admin deletes comes back on the next upgrade, and six rows of someone
	 * else's pricing is not a sane starting state for every site.
	 */
	public static function example_packages() {

		return array(
			array( 'base' => 300,   'bonus' => 0,    'price' => '2.99',  'active' => 1 ),
			array( 'base' => 500,   'bonus' => 75,   'price' => '4.99',  'active' => 1 ),
			array( 'base' => 1400,  'bonus' => 420,  'price' => '13.99', 'active' => 1 ),
			array( 'base' => 2500,  'bonus' => 1250, 'price' => '24.99', 'active' => 1 ),
			array( 'base' => 3600,  'bonus' => 2160, 'price' => '35.99', 'active' => 1 ),
			array( 'base' => 5000,  'bonus' => 5000, 'price' => '49.99', 'active' => 1 ),
		);
	}

	/*
	 * No example_plans() twin: VIP plans are PMPro levels now, and seeding
	 * example rows into a list nothing reads or edits would only leave a
	 * shelf of prices that never reach a buyer.
	 */

	/* ---------------------------------------------------------------------- */
	/* Admin screen                                                            */
	/* ---------------------------------------------------------------------- */

	/**
	 * Priority 20: the parent "Jws Settings" menu is registered by the theme,
	 * and a theme's admin_menu callback runs after every plugin's at the default
	 * priority — the submenu would be attached to a parent that does not exist
	 * yet and silently vanish.
	 */
	/** Set by register_submenu() to the exact hook add_submenu_page() returned. */
	private $hook_suffix = '';

	public function register_submenu() {

		$this->hook_suffix = add_submenu_page(
			'jws_settings',
			esc_html__( 'Drama Coins', 'jws_streamvid' ),
			esc_html__( 'Drama Coins', 'jws_streamvid' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);

		/*
		 * Media scripts have to be queued from admin_enqueue_scripts, not from
		 * inside render_page(): that callback runs after admin-header.php has
		 * already printed the <head> scripts, so wp.media would still be
		 * undefined when the Coin icon button's click handler is set up.
		 */
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_screen_assets' ) );
	}

	/** @param string $hook_suffix */
	public function enqueue_screen_assets( $hook_suffix ) {

		if ( $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_media();
	}

	/* ---------------------------------------------------------------------- */
	/* Saving                                                                  */
	/* ---------------------------------------------------------------------- */

	private function handle_post() {

		if ( ! isset( $_POST['jws_drama_settings_nonce'] ) ) {
			return null;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jws_drama_settings_nonce'] ) ), self::NONCE ) ) {
			return array( 'type' => 'error', 'message' => esc_html__( 'Security check failed. Nothing was saved.', 'jws_streamvid' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'type' => 'error', 'message' => esc_html__( 'You cannot change these settings.', 'jws_streamvid' ) );
		}

		$settings = $this->sanitize( wp_unslash( $_POST ) );

		/* The two "load the example shelf" buttons append rather than replace,
		   so a half-filled list is never wiped by a stray click. */
		if ( isset( $_POST['jws_drama_seed_packages'] ) ) {
			$settings['packages'] = array_merge( $settings['packages'], self::example_packages() );
		}

		self::save( $settings );

		return array( 'type' => 'success', 'message' => esc_html__( 'Settings saved.', 'jws_streamvid' ) );
	}

	/**
	 * Everything that lands in the option row goes through here.
	 *
	 * Rows are re-indexed on the way in: the repeater deletes by removing a
	 * <tr>, which leaves gaps in the POSTed indexes, and a gappy array stops
	 * being a list the moment it is JSON-encoded for the front end.
	 */
	private function sanitize( $raw ) {

		$out    = self::defaults();
		$stored = self::all();

		$stored_packages = isset( $stored['packages'] ) && is_array( $stored['packages'] ) ? $stored['packages'] : array();
		$stored_plans    = isset( $stored['plans'] ) && is_array( $stored['plans'] ) ? $stored['plans'] : array();

		$out['enabled'] = empty( $raw['enabled'] ) ? 0 : 1;

		$out['free_episodes'] = isset( $raw['free_episodes'] ) ? max( 0, (int) $raw['free_episodes'] ) : 0;
		$out['coin_price']    = isset( $raw['coin_price'] ) ? max( 0, (int) $raw['coin_price'] ) : 0;

		/*
		 * No form posts a currency any more — the field went when Woo and
		 * PMPro took over the checkouts. Falling back to what is stored
		 * rather than to the default keeps the retired gateway layer pointed
		 * at the same currency it already charged people in.
		 */
		$out['currency'] = isset( $raw['currency'] )
			? strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', $raw['currency'] ), 0, 3 ) )
			: ( isset( $stored['currency'] ) ? $stored['currency'] : 'USD' );

		/* Posted only by the Packages tab; every other tab keeps what is set. */
		$out['coin_product'] = isset( $raw['coin_product'] )
			? absint( $raw['coin_product'] )
			: ( isset( $stored['coin_product'] ) ? absint( $stored['coin_product'] ) : 0 );

		/* Posted only by the General tab. */
		$out['coin_icon'] = isset( $raw['coin_icon'] )
			? absint( $raw['coin_icon'] )
			: ( isset( $stored['coin_icon'] ) ? absint( $stored['coin_icon'] ) : 0 );

		/*
		 * Nothing posts the method list any more — the checkboxes went with
		 * the buy panel's gateway row, since there is no button left for them
		 * to switch on. The sentinel branch is kept so the stored value rides
		 * through every save untouched, which is what the retired Stripe and
		 * PayPal flows still read.
		 */
		$out['methods'] = empty( $raw['methods_present'] )
			? ( isset( $stored['methods'] ) ? (array) $stored['methods'] : array() )
			: array();

		if ( ! empty( $raw['methods_present'] ) && isset( $raw['methods'] ) && is_array( $raw['methods'] ) ) {
			$known          = array_keys( self::method_catalog() );
			$out['methods'] = array_values( array_intersect( $known, array_map( 'sanitize_key', $raw['methods'] ) ) );
		}

		/*
		 * A checkbox group with every box unticked posts nothing at all, which
		 * is byte-for-byte what a POST that never carried the section looks
		 * like — hence the sentinel, without which saving another tab would
		 * silently untick every PMPro level.
		 */
		$out['pmpro_levels'] = empty( $raw['pmpro_levels_present'] )
			? ( isset( $stored['pmpro_levels'] ) ? (array) $stored['pmpro_levels'] : array() )
			: array();

		if ( ! empty( $raw['pmpro_levels_present'] ) && isset( $raw['pmpro_levels'] ) && is_array( $raw['pmpro_levels'] ) ) {
			$out['pmpro_levels'] = array_values( array_unique( array_filter( array_map( 'intval', $raw['pmpro_levels'] ) ) ) );
		}

		/*
		 * Each list is only rewritten when its own section was really posted.
		 * The sentinel is what tells "the admin deleted every row" apart from
		 * "this POST never carried that section" — a request truncated by
		 * max_input_vars would otherwise wipe a shelf nobody touched.
		 */
		$out['packages'] = empty( $raw['packages_present'] ) ? $stored_packages : array();

		if ( ! empty( $raw['packages_present'] ) && isset( $raw['packages'] ) && is_array( $raw['packages'] ) ) {
			foreach ( $raw['packages'] as $row ) {

				$base = isset( $row['base'] ) ? max( 0, (int) $row['base'] ) : 0;

				// An empty row is how a repeater says "I added one and changed my mind".
				if ( $base <= 0 ) {
					continue;
				}

				$out['packages'][] = array(
					'base'   => $base,
					'bonus'  => isset( $row['bonus'] ) ? max( 0, (int) $row['bonus'] ) : 0,
					'price'  => $this->price( isset( $row['price'] ) ? $row['price'] : 0 ),
					'active' => empty( $row['active'] ) ? 0 : 1,
				);
			}
		}

		$out['plans'] = empty( $raw['plans_present'] ) ? $stored_plans : array();
		$periods      = array_keys( self::period_labels() );

		if ( ! empty( $raw['plans_present'] ) && isset( $raw['plans'] ) && is_array( $raw['plans'] ) ) {
			foreach ( $raw['plans'] as $row ) {

				$name = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';

				if ( '' === $name ) {
					continue;
				}

				$period = isset( $row['period'] ) && in_array( $row['period'], $periods, true ) ? $row['period'] : 'month';

				$out['plans'][] = array(
					'name'          => $name,
					'period'        => $period,
					'cycle'         => isset( $row['cycle'] ) ? max( 1, (int) $row['cycle'] ) : 1,
					'price'         => $this->price( isset( $row['price'] ) ? $row['price'] : 0 ),
					/* Blank, not zero: an empty intro means "no intro offer",
					   while 0.00 would be a legitimate free first cycle. */
					'intro'         => isset( $row['intro'] ) && '' !== trim( $row['intro'] ) ? $this->price( $row['intro'] ) : '',
					'features'      => isset( $row['features'] ) ? sanitize_textarea_field( $row['features'] ) : '',
					'active'        => empty( $row['active'] ) ? 0 : 1,
				);
			}
		}

		/*
		 * API keys fall back to what is stored, never to the blank defaults.
		 * A POST that did not carry the Payments section — truncated by
		 * max_input_vars, or a form posted from somewhere else — would
		 * otherwise erase live credentials on a plain Save, and there is
		 * nowhere to get them back from.
		 */
		foreach ( array( 'stripe', 'paypal' ) as $gateway ) {

			$was    = isset( $stored[ $gateway ] ) && is_array( $stored[ $gateway ] ) ? $stored[ $gateway ] : array();
			$posted = isset( $raw[ $gateway ] ) && is_array( $raw[ $gateway ] ) ? $raw[ $gateway ] : null;

			if ( null === $posted ) {
				$out[ $gateway ] = array_merge( $out[ $gateway ], $was );
				continue;
			}

			foreach ( $out[ $gateway ] as $key => $default ) {

				if ( 'enabled' === $key ) {
					$out[ $gateway ][ $key ] = empty( $posted[ $key ] ) ? 0 : 1;
					continue;
				}

				$secret = in_array( $key, self::secret_fields()[ $gateway ], true );
				$value  = isset( $posted[ $key ] ) ? sanitize_text_field( trim( $posted[ $key ] ) ) : null;

				/*
				 * A write-only field comes back blank on every load, so blank
				 * has to mean "unchanged" — otherwise simply saving the page
				 * would delete the key it is not allowed to show.
				 */
				if ( null === $value || ( $secret && '' === $value ) ) {
					$out[ $gateway ][ $key ] = isset( $was[ $key ] ) ? $was[ $key ] : $default;
				} else {
					$out[ $gateway ][ $key ] = $value;
				}
			}
		}

		/* Provisioned gateway objects are not on this form. Carrying them over
		   is what stops a plain Save from orphaning a live Stripe Price. */
		$out['gateway_refs'] = isset( $stored['gateway_refs'] ) && is_array( $stored['gateway_refs'] ) ? $stored['gateway_refs'] : array();

		$out['stripe']['country'] = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', $out['stripe']['country'] ), 0, 2 ) );
		$out['stripe']['country'] = $out['stripe']['country'] ? $out['stripe']['country'] : 'US';

		$out['stripe']['mode'] = 'live' === $out['stripe']['mode'] ? 'live' : 'test';
		$out['paypal']['mode'] = 'live' === $out['paypal']['mode'] ? 'live' : 'sandbox';

		return $out;
	}

	/** A price as a plain decimal string, so it round-trips through the form. */
	private function price( $value ) {

		$value = (float) str_replace( ',', '.', preg_replace( '/[^0-9.,\-]/', '', (string) $value ) );

		return number_format( max( 0, $value ), 2, '.', '' );
	}

	/* ---------------------------------------------------------------------- */
	/* Rendering                                                               */
	/* ---------------------------------------------------------------------- */

	public function render_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// May redirect (Post/Redirect/Get) and exit before any markup is echoed.
		$this->handle_member_post();
		$this->handle_demo_import_post();

		$notice = $this->handle_post();
		$s      = self::all();
		/*
		 * No VIP Plans or Payments tab: VIP is sold and priced by Paid
		 * Memberships Pro and coins by WooCommerce, so both are managed in
		 * those plugins' own screens. The General tab's level tick-boxes are
		 * the only decision this module still makes about VIP.
		 */
		$tabs = array(
			'general'     => esc_html__( 'General', 'jws_streamvid' ),
			'packages'    => esc_html__( 'Coin Packages', 'jws_streamvid' ),
			'members'     => esc_html__( 'Members & Transactions', 'jws_streamvid' ),
			'demo_import' => esc_html__( 'Demo Import', 'jws_streamvid' ),
		);
		?>
		<div class="wrap jws-drama-settings">
			<h1><?php echo esc_html__( 'Drama Coins', 'jws_streamvid' ); ?></h1>
			<p class="description" style="max-width:760px">
				<?php echo esc_html__( 'Everything the short-drama coin system runs on: what is free, what an episode costs, what is on sale and who takes the money.', 'jws_streamvid' ); ?>
			</p>

			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
			<?php endif; ?>

			<?php $this->render_member_notice(); ?>

			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $id => $label ) : ?>
					<a href="#<?php echo esc_attr( $id ); ?>" class="nav-tab" data-tab="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</h2>

			<form method="post">
				<?php wp_nonce_field( self::NONCE, 'jws_drama_settings_nonce' ); ?>

				<?php
				$this->render_general( $s );
				$this->render_packages( $s );
				?>

				<?php submit_button( esc_html__( 'Save Settings', 'jws_streamvid' ) ); ?>
			</form>

			<?php $this->render_members( $s ); ?>
			<?php $this->render_demo_import(); ?>
		</div>
		<?php
		$this->render_assets();
	}

	private function render_general( $s ) {
		?>
		<div class="jws-drama-tab" data-tab="general">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="enabled"><?php echo esc_html__( 'Drama system', 'jws_streamvid' ); ?></label></th>
					<td>
						<label>
							<input type="checkbox" id="enabled" name="enabled" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?> />
							<?php echo esc_html__( 'Enable the drama system', 'jws_streamvid' ); ?>
						</label>
						<p class="description"><?php echo esc_html__( 'Turn off to hide drama everywhere on the site — post types, pages, widgets and the app API — without losing any content. This settings screen stays reachable so it can be switched back on.', 'jws_streamvid' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="free_episodes"><?php echo esc_html__( 'Default free episodes', 'jws_streamvid' ); ?></label></th>
					<td>
						<input type="number" min="0" id="free_episodes" name="free_episodes" value="<?php echo (int) $s['free_episodes']; ?>" class="small-text" />
						<p class="description"><?php echo esc_html__( 'How many opening episodes anyone can watch, for a drama that does not set its own number.', 'jws_streamvid' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="coin_price"><?php echo esc_html__( 'Default coins per episode', 'jws_streamvid' ); ?></label></th>
					<td>
						<input type="number" min="0" id="coin_price" name="coin_price" value="<?php echo (int) $s['coin_price']; ?>" class="small-text" />
						<p class="description"><?php echo esc_html__( 'Cost to unlock one episode past the free ones, for a drama that does not set its own price.', 'jws_streamvid' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="coin_icon_select"><?php echo esc_html__( 'Coin icon', 'jws_streamvid' ); ?></label></th>
					<td>
						<div class="jws-drama-coin-icon">
							<input type="hidden" name="coin_icon" id="coin_icon" value="<?php echo (int) $s['coin_icon']; ?>" />
							<img id="coin_icon_preview" src="<?php echo esc_url( self::coin_icon_url() ); ?>" <?php echo self::coin_icon_url() ? '' : 'hidden'; ?> />
							<button type="button" class="button" id="coin_icon_select"><?php echo esc_html__( 'Select Image', 'jws_streamvid' ); ?></button>
							<button type="button" class="button-link jws-drama-coin-icon-remove" id="coin_icon_remove" <?php echo self::coin_icon_url() ? '' : 'hidden'; ?>><?php echo esc_html__( 'Remove', 'jws_streamvid' ); ?></button>
						</div>
						<p class="description"><?php echo esc_html__( 'Shown next to every coin amount — wallet balance, episode prices, package cards. Leave empty for the default gold circle.', 'jws_streamvid' ); ?></p>
					</td>
				</tr>
				<?php
				/*
				 * No currency field: coins are priced and charged by
				 * WooCommerce and VIP by PMPro, so each quotes its own store's
				 * currency. The stored code is only still read by the retired
				 * Stripe/PayPal layer, and a second currency box here could
				 * only ever disagree with the checkout the buyer lands on.
				 */
				?>

				<?php $levels = method_exists( 'Jws_Drama_Wallet', 'membership_level_choices' ) ? Jws_Drama_Wallet::membership_level_choices() : array(); ?>
				<?php if ( $levels ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html__( 'PMPro plans that unlock everything', 'jws_streamvid' ); ?></th>
						<td>
							<input type="hidden" name="pmpro_levels_present" value="1" />
							<?php foreach ( $levels as $id => $label ) : ?>
								<label style="display:block;margin-bottom:4px">
									<input type="checkbox" name="pmpro_levels[]" value="<?php echo (int) $id; ?>" <?php checked( in_array( (int) $id, array_map( 'intval', (array) $s['pmpro_levels'] ), true ) ); ?> />
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
							<p class="description">
								<?php echo esc_html__( 'Members on a ticked plan watch every episode without spending coins. Leave a free plan unticked, or it hands the whole catalogue away and the free-episode limit above never applies.', 'jws_streamvid' ); ?>
								<br /><em><?php echo esc_html__( 'A ticked plan is also what the buy panel sells as VIP — see the VIP Plans tab.', 'jws_streamvid' ); ?></em>
							</p>
						</td>
					</tr>
				<?php endif; ?>
			</table>
		</div>
		<?php
	}

	private function render_packages( $s ) {

		$rows = self::packages( true );
		?>
		<div class="jws-drama-tab" data-tab="packages">
			<p class="description" style="max-width:760px">
				<?php echo esc_html__( 'What the top-up shelf sells. "Base" is what the buyer pays for and "Bonus" is what they get on top — the big number and the +% badge in the modal are both worked out from those two, so there is nothing to keep in step by hand.', 'jws_streamvid' ); ?>
			</p>
			<p class="description" style="max-width:760px">
				<?php echo esc_html__( 'Packages are bought through WooCommerce: clicking one adds it to the cart and goes to checkout, and the coins land when the order is paid. Prices are in the shop\'s currency.', 'jws_streamvid' ); ?>
			</p>
			<?php if ( class_exists( 'WooCommerce' ) ) : ?>
				<?php
				/*
				 * One product carries every package, the way pay-per-view sells
				 * every rental through a single ticket product: the row's price
				 * and coin amount ride on the cart item, so a new package is a
				 * new row here and never another product to create.
				 */
				$coin_product = (int) ( isset( $s['coin_product'] ) ? $s['coin_product'] : 0 );
				$products     = get_posts(
					array(
						'post_type'      => 'product',
						'post_status'    => 'publish',
						'posts_per_page' => 100,
						'orderby'        => 'title',
						'order'          => 'ASC',
					)
				);
				?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="coin_product"><?php echo esc_html__( 'Checkout product', 'jws_streamvid' ); ?></label></th>
						<td>
							<select name="coin_product" id="coin_product">
								<option value="0"><?php echo esc_html__( '— Pick a product —', 'jws_streamvid' ); ?></option>
								<?php foreach ( $products as $product ) : ?>
									<option value="<?php echo (int) $product->ID; ?>" <?php selected( $coin_product, (int) $product->ID ); ?>>
										<?php echo esc_html( $product->post_title ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description" style="max-width:760px">
								<?php echo esc_html__( 'The WooCommerce product every coin package is bought through. Make it a simple, virtual product — its own price is ignored, since each package sets the price and the coins on the cart item.', 'jws_streamvid' ); ?>
							</p>
							<?php if ( ! $coin_product ) : ?>
								<p class="description" style="color:#b32d2e">
									<?php echo esc_html__( 'Until one is picked, the Top up shelf cannot check out.', 'jws_streamvid' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			<?php else : ?>
				<div class="notice notice-warning inline" style="margin:12px 0;max-width:760px">
					<p><?php echo esc_html__( 'WooCommerce is not active. Coin packages check out through it, so the Top up shelf stays unbuyable until it is.', 'jws_streamvid' ); ?></p>
				</div>
			<?php endif; ?>

			<input type="hidden" name="packages_present" value="1" />

			<table class="widefat striped jws-drama-repeater" data-repeater="packages">
				<thead>
					<tr>
						<th style="width:110px"><?php echo esc_html__( 'Base coins', 'jws_streamvid' ); ?></th>
						<th style="width:110px"><?php echo esc_html__( 'Bonus coins', 'jws_streamvid' ); ?></th>
						<th style="width:130px"><?php echo esc_html__( 'Buyer sees', 'jws_streamvid' ); ?></th>
						<th style="width:120px"><?php echo esc_html__( 'Price', 'jws_streamvid' ); ?></th>
						<th style="width:80px"><?php echo esc_html__( 'Active', 'jws_streamvid' ); ?></th>
						<th style="width:60px"></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $i => $row ) : ?>
						<tr>
							<td><input type="number" min="0" name="packages[<?php echo (int) $i; ?>][base]" value="<?php echo (int) $row['base']; ?>" class="small-text jws-pk-base" /></td>
							<td><input type="number" min="0" name="packages[<?php echo (int) $i; ?>][bonus]" value="<?php echo (int) $row['bonus']; ?>" class="small-text jws-pk-bonus" /></td>
							<td class="jws-pk-preview"><?php echo esc_html( number_format_i18n( $row['total'] ) ); ?><?php echo $row['bonus'] ? ' <span style="color:#b32d2e">+' . (int) $row['bonus_percent'] . '%</span>' : ''; ?></td>
							<td><input type="text" name="packages[<?php echo (int) $i; ?>][price]" value="<?php echo esc_attr( number_format( $row['price'], 2, '.', '' ) ); ?>" class="small-text" /></td>
							<td><input type="checkbox" name="packages[<?php echo (int) $i; ?>][active]" value="1" <?php checked( $row['active'] ); ?> /></td>
							<td><button type="button" class="button-link jws-drama-remove" style="color:#b32d2e"><?php echo esc_html__( 'Remove', 'jws_streamvid' ); ?></button></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p>
				<button type="button" class="button jws-drama-add" data-repeater="packages"><?php echo esc_html__( '+ Add package', 'jws_streamvid' ); ?></button>
				<button type="submit" name="jws_drama_seed_packages" value="1" class="button"><?php echo esc_html__( 'Load example shelf', 'jws_streamvid' ); ?></button>
			</p>

			<template data-template="packages">
				<tr>
					<td><input type="number" min="0" name="packages[__i__][base]" value="" class="small-text jws-pk-base" /></td>
					<td><input type="number" min="0" name="packages[__i__][bonus]" value="0" class="small-text jws-pk-bonus" /></td>
					<td class="jws-pk-preview">—</td>
					<td><input type="text" name="packages[__i__][price]" value="0.00" class="small-text" /></td>
					<td><input type="checkbox" name="packages[__i__][active]" value="1" checked /></td>
					<td><button type="button" class="button-link jws-drama-remove" style="color:#b32d2e"><?php echo esc_html__( 'Remove', 'jws_streamvid' ); ?></button></td>
				</tr>
			</template>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------- */
	/* Members & transactions                                                  */
	/* ---------------------------------------------------------------------- */

	private function member_url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
	}

	private function status_label( $status ) {

		$labels = array(
			'pending'  => esc_html__( 'Pending', 'jws_streamvid' ),
			'paid'     => esc_html__( 'Paid', 'jws_streamvid' ),
			'failed'   => esc_html__( 'Failed', 'jws_streamvid' ),
			'refunded' => esc_html__( 'Refunded', 'jws_streamvid' ),
			'active'   => esc_html__( 'Active', 'jws_streamvid' ),
			'past_due' => esc_html__( 'Past due', 'jws_streamvid' ),
			'canceled' => esc_html__( 'Canceled', 'jws_streamvid' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( $status );
	}

	/**
	 * Every write from the member detail screen — coin adjustment, and now a
	 * VIP subscription's own save/delete — funnels through here so they share
	 * one nonce and one Post/Redirect/Get: a straight render after the POST
	 * would resubmit the action on every refresh.
	 */
	private function handle_member_post() {

		if ( empty( $_POST['jws_drama_member_action'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action  = sanitize_key( wp_unslash( $_POST['jws_drama_member_action'] ) );
		$user_id = isset( $_POST['member_user_id'] ) ? absint( $_POST['member_user_id'] ) : 0;
		$nonce   = isset( $_POST['jws_drama_member_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['jws_drama_member_nonce'] ) ) : '';

		if ( ! $user_id || ! wp_verify_nonce( $nonce, 'jws_drama_member_action_' . $user_id ) ) {
			return;
		}

		$back = $this->member_url(
			array(
				'view_user'    => $user_id,
				'wallet_paged' => isset( $_POST['wallet_paged'] ) ? absint( $_POST['wallet_paged'] ) : 1,
				'order_paged'  => isset( $_POST['order_paged'] ) ? absint( $_POST['order_paged'] ) : 1,
			)
		);

		if ( 'adjust' === $action ) {
			$back = $this->handle_member_coin_adjust( $user_id, $back );
		}

		wp_safe_redirect( $back . '#members' );
		exit;
	}

	private function handle_member_coin_adjust( $user_id, $back ) {

		$amount = isset( $_POST['member_coin_amount'] ) ? max( 0, (int) $_POST['member_coin_amount'] ) : 0;

		if ( $amount <= 0 ) {
			return $back;
		}

		$subtract = isset( $_POST['member_coin_action'] ) && 'subtract' === $_POST['member_coin_action'];

		$note = isset( $_POST['member_coin_note'] ) ? sanitize_text_field( wp_unslash( $_POST['member_coin_note'] ) ) : '';
		$note = $note ? $note : sprintf( 'Adjusted by %s', wp_get_current_user()->user_login );

		$ok = $subtract
			? Jws_Drama_Wallet::debit( $user_id, $amount, 'admin', get_current_user_id(), $note )
			: Jws_Drama_Wallet::credit( $user_id, $amount, 'admin', get_current_user_id(), $note );

		return add_query_arg( 'member_msg', ( false === $ok ? 'adjust_failed' : 'adjusted' ), $back );
	}

	private function render_member_notice() {

		if ( empty( $_GET['member_msg'] ) ) {
			return;
		}

		$messages = array(
			'adjusted'      => array( 'success', esc_html__( 'Balance updated.', 'jws_streamvid' ) ),
			'adjust_failed' => array( 'error', esc_html__( 'Could not apply that change — a debit cannot take the balance below zero.', 'jws_streamvid' ) ),
		);

		$key = sanitize_key( wp_unslash( $_GET['member_msg'] ) );

		if ( ! isset( $messages[ $key ] ) ) {
			return;
		}

		list( $type, $text ) = $messages[ $key ];

		printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $type ), $text );
	}

	private function render_members( $s ) {
		?>
		<div class="jws-drama-tab" data-tab="members">
			<?php
			$view_user = isset( $_GET['view_user'] ) ? absint( $_GET['view_user'] ) : 0;

			if ( $view_user ) {
				$this->render_member_detail( $view_user );
			} else {
				$this->render_member_list( $s );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Every member with a wallet or a VIP plan, searchable and sortable.
	 *
	 * Balance and VIP status are fetched in bulk for the rows actually shown
	 * rather than per user — a page of 20 members costs three queries beside
	 * the user query itself, not sixty.
	 */
	private function render_member_list( $s ) {

		global $wpdb;

		$sub_table   = Jws_Drama_Install::table_subscription();
		$order_table = Jws_Drama_Install::table_order();

		$search   = isset( $_GET['member_s'] ) ? sanitize_text_field( wp_unslash( $_GET['member_s'] ) ) : '';
		$filter   = isset( $_GET['member_filter'] ) ? sanitize_key( wp_unslash( $_GET['member_filter'] ) ) : '';
		$sort     = isset( $_GET['member_sort'] ) ? sanitize_key( wp_unslash( $_GET['member_sort'] ) ) : 'registered';
		$paged    = isset( $_GET['member_paged'] ) ? max( 1, absint( $_GET['member_paged'] ) ) : 1;
		$per_page = 20;

		$total_users   = (int) count_users()['total_users'];
		$coins_out     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(CAST(meta_value AS SIGNED)) FROM {$wpdb->usermeta} WHERE meta_key = %s", Jws_Drama_Wallet::META_BALANCE ) );
		$vip_count     = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT user_id) FROM {$sub_table} WHERE status IN (%s,%s) AND current_period_end IS NOT NULL AND current_period_end > %s",
				Jws_Drama_Subscriptions::STATUS_ACTIVE,
				Jws_Drama_Subscriptions::STATUS_CANCELED,
				current_time( 'mysql' )
			)
		);
		$pending_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$order_table} WHERE status = %s", Jws_Drama_Orders::STATUS_PENDING ) );
		$revenue_rows  = $wpdb->get_results( $wpdb->prepare( "SELECT currency, SUM(amount) AS total FROM {$order_table} WHERE status = %s GROUP BY currency", Jws_Drama_Orders::STATUS_PAID ) );
		?>
		<p class="description" style="max-width:760px">
			<?php echo esc_html__( 'Every viewer with a wallet or a VIP plan, what they have spent, and the ledger behind each number.', 'jws_streamvid' ); ?>
		</p>

		<div class="jws-drama-stats">
			<div class="jws-drama-stat">
				<span class="jws-drama-stat-num"><?php echo esc_html( number_format_i18n( $total_users ) ); ?></span>
				<span class="jws-drama-stat-label"><?php echo esc_html__( 'Registered users', 'jws_streamvid' ); ?></span>
			</div>
			<div class="jws-drama-stat">
				<span class="jws-drama-stat-num"><?php echo esc_html( number_format_i18n( max( 0, $coins_out ) ) ); ?></span>
				<span class="jws-drama-stat-label"><?php echo esc_html__( 'Coins in wallets', 'jws_streamvid' ); ?></span>
			</div>
			<div class="jws-drama-stat">
				<span class="jws-drama-stat-num"><?php echo esc_html( number_format_i18n( $vip_count ) ); ?></span>
				<span class="jws-drama-stat-label"><?php echo esc_html__( 'Active VIP members', 'jws_streamvid' ); ?></span>
			</div>
			<div class="jws-drama-stat">
				<span class="jws-drama-stat-num">
					<?php
					if ( $revenue_rows ) {
						echo esc_html(
							implode(
								' + ',
								array_map(
									function ( $row ) {
										return self::format_price( $row->total, $row->currency );
									},
									$revenue_rows
								)
							)
						);
					} else {
						echo '—';
					}
					?>
				</span>
				<span class="jws-drama-stat-label"><?php echo esc_html__( 'Total revenue (paid)', 'jws_streamvid' ); ?></span>
			</div>
			<div class="jws-drama-stat">
				<span class="jws-drama-stat-num"><?php echo esc_html( number_format_i18n( $pending_count ) ); ?></span>
				<span class="jws-drama-stat-label"><?php echo esc_html__( 'Pending orders', 'jws_streamvid' ); ?></span>
			</div>
		</div>

		<form method="get" class="jws-drama-member-filters">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />
			<input type="search" name="member_s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Search name, username or email…', 'jws_streamvid' ); ?>" class="regular-text" />
			<select name="member_filter">
				<option value="" <?php selected( $filter, '' ); ?>><?php echo esc_html__( 'Everyone', 'jws_streamvid' ); ?></option>
				<option value="vip" <?php selected( $filter, 'vip' ); ?>><?php echo esc_html__( 'Active VIP', 'jws_streamvid' ); ?></option>
				<option value="balance" <?php selected( $filter, 'balance' ); ?>><?php echo esc_html__( 'Has coins', 'jws_streamvid' ); ?></option>
				<option value="zero" <?php selected( $filter, 'zero' ); ?>><?php echo esc_html__( 'Zero balance', 'jws_streamvid' ); ?></option>
			</select>
			<select name="member_sort">
				<option value="registered" <?php selected( $sort, 'registered' ); ?>><?php echo esc_html__( 'Newest first', 'jws_streamvid' ); ?></option>
				<option value="balance" <?php selected( $sort, 'balance' ); ?>><?php echo esc_html__( 'Highest balance', 'jws_streamvid' ); ?></option>
				<option value="name" <?php selected( $sort, 'name' ); ?>><?php echo esc_html__( 'Name (A–Z)', 'jws_streamvid' ); ?></option>
			</select>
			<?php submit_button( esc_html__( 'Filter', 'jws_streamvid' ), 'secondary', '', false ); ?>
			<?php if ( $search || $filter || 'registered' !== $sort ) : ?>
				<a class="button-link" style="margin-inline-start:8px" href="<?php echo esc_url( $this->member_url() . '#members' ); ?>"><?php echo esc_html__( 'Reset', 'jws_streamvid' ); ?></a>
			<?php endif; ?>
		</form>

		<?php
		$query_args = array(
			'number' => $per_page,
			'paged'  => $paged,
		);

		if ( '' !== $search ) {
			$query_args['search']         = '*' . $search . '*';
			$query_args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		if ( 'balance' === $sort ) {
			$query_args['orderby']  = 'meta_value_num';
			$query_args['meta_key'] = Jws_Drama_Wallet::META_BALANCE;
			$query_args['order']    = 'DESC';
		} elseif ( 'name' === $sort ) {
			$query_args['orderby'] = 'display_name';
			$query_args['order']   = 'ASC';
		} else {
			$query_args['orderby'] = 'registered';
			$query_args['order']   = 'DESC';
		}

		if ( 'balance' === $filter ) {
			$query_args['meta_query'] = array(
				array( 'key' => Jws_Drama_Wallet::META_BALANCE, 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ),
			);
		} elseif ( 'zero' === $filter ) {
			$query_args['meta_query'] = array(
				'relation' => 'OR',
				array( 'key' => Jws_Drama_Wallet::META_BALANCE, 'value' => 0, 'compare' => '=', 'type' => 'NUMERIC' ),
				array( 'key' => Jws_Drama_Wallet::META_BALANCE, 'compare' => 'NOT EXISTS' ),
			);
		} elseif ( 'vip' === $filter ) {

			$vip_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT user_id FROM {$sub_table} WHERE status IN (%s,%s) AND current_period_end IS NOT NULL AND current_period_end > %s",
					Jws_Drama_Subscriptions::STATUS_ACTIVE,
					Jws_Drama_Subscriptions::STATUS_CANCELED,
					current_time( 'mysql' )
				)
			);

			$query_args['include'] = $vip_ids ? array_map( 'intval', $vip_ids ) : array( 0 );
		}

		$user_query = new WP_User_Query( $query_args );
		$users      = $user_query->get_results();
		$total      = (int) $user_query->get_total();
		$user_ids   = wp_list_pluck( $users, 'ID' );

		if ( $user_ids ) {
			update_meta_cache( 'user', $user_ids );
		}

		$vip_map       = array();
		$spent_map     = array();

		if ( $user_ids ) {

			$placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );

			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT user_id, label, current_period_end FROM {$sub_table}
					 WHERE user_id IN ({$placeholders}) AND status IN (%s,%s) AND current_period_end IS NOT NULL AND current_period_end > %s
					 ORDER BY current_period_end DESC",
					array_merge( $user_ids, array( Jws_Drama_Subscriptions::STATUS_ACTIVE, Jws_Drama_Subscriptions::STATUS_CANCELED, current_time( 'mysql' ) ) )
				)
			);

			foreach ( $rows as $row ) {
				if ( ! isset( $vip_map[ $row->user_id ] ) ) {
					$vip_map[ $row->user_id ] = $row;
				}
			}

			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT user_id, SUM(amount) AS total FROM {$order_table} WHERE user_id IN ({$placeholders}) AND status = %s GROUP BY user_id",
					array_merge( $user_ids, array( Jws_Drama_Orders::STATUS_PAID ) )
				)
			);

			foreach ( $rows as $row ) {
				$spent_map[ $row->user_id ] = (float) $row->total;
			}
		}
		?>

		<div class="jws-drama-table-wrap">
			<table class="widefat striped jws-drama-member-table">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Member', 'jws_streamvid' ); ?></th>
						<th style="width:110px"><?php echo esc_html__( 'Balance', 'jws_streamvid' ); ?></th>
						<th style="width:190px"><?php echo esc_html__( 'VIP', 'jws_streamvid' ); ?></th>
						<th style="width:120px"><?php echo esc_html__( 'Total spent', 'jws_streamvid' ); ?></th>
						<th style="width:140px"><?php echo esc_html__( 'Joined', 'jws_streamvid' ); ?></th>
						<th style="width:100px"></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $users ) : ?>
						<tr><td colspan="6"><?php echo esc_html__( 'No members match that search.', 'jws_streamvid' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $users as $user ) : ?>
							<tr>
								<td>
									<?php echo get_avatar( $user->ID, 32, '', '', array( 'extra_attr' => 'style="border-radius:50%;vertical-align:middle;margin-inline-end:8px"' ) ); ?>
									<strong><?php echo esc_html( $user->display_name ); ?></strong><br />
									<span class="description"><?php echo esc_html( $user->user_email ); ?></span>
								</td>
								<td><strong><?php echo esc_html( number_format_i18n( Jws_Drama_Wallet::balance( $user->ID ) ) ); ?></strong></td>
								<td>
									<?php if ( isset( $vip_map[ $user->ID ] ) ) : ?>
										<span class="jws-drama-badge jws-drama-badge-vip"><?php echo esc_html( $vip_map[ $user->ID ]->label ? $vip_map[ $user->ID ]->label : esc_html__( 'VIP', 'jws_streamvid' ) ); ?></span>
										<br /><span class="description"><?php printf( esc_html__( 'until %s', 'jws_streamvid' ), esc_html( mysql2date( get_option( 'date_format' ), $vip_map[ $user->ID ]->current_period_end ) ) ); ?></span>
									<?php else : ?>
										<span class="description">—</span>
									<?php endif; ?>
								</td>
								<td><?php echo isset( $spent_map[ $user->ID ] ) ? esc_html( self::format_price( $spent_map[ $user->ID ] ) ) : '—'; ?></td>
								<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $user->user_registered ) ); ?></td>
								<td><a class="button button-small" href="<?php echo esc_url( $this->member_url( array( 'view_user' => $user->ID ) ) . '#members' ); ?>"><?php echo esc_html__( 'Manage', 'jws_streamvid' ); ?></a></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<?php
		$this->render_pagination(
			$paged,
			(int) ceil( $total / $per_page ),
			array(
				'member_s'      => $search,
				'member_filter' => $filter,
				'member_sort'   => $sort,
			),
			'member_paged'
		);
	}

	/** One member's wallet, VIP history and purchases, with a manual adjust form. */
	private function render_member_detail( $user_id ) {

		$user = get_userdata( $user_id );
		?>
		<p><a href="<?php echo esc_url( $this->member_url() . '#members' ); ?>">&larr; <?php echo esc_html__( 'Back to all members', 'jws_streamvid' ); ?></a></p>
		<?php
		if ( ! $user ) {
			echo '<p>' . esc_html__( 'That user no longer exists.', 'jws_streamvid' ) . '</p>';
			return;
		}

		$balance = Jws_Drama_Wallet::balance( $user_id );

		$wallet_paged = isset( $_GET['wallet_paged'] ) ? max( 1, absint( $_GET['wallet_paged'] ) ) : 1;
		$order_paged  = isset( $_GET['order_paged'] ) ? max( 1, absint( $_GET['order_paged'] ) ) : 1;
		$per_page     = 15;

		// One extra row fetched to know whether a "next" page exists, without a COUNT query.
		$wallet_rows = Jws_Drama_Wallet::history( $user_id, $per_page + 1, ( $wallet_paged - 1 ) * $per_page );
		$wallet_more = count( $wallet_rows ) > $per_page;
		$wallet_rows = array_slice( $wallet_rows, 0, $per_page );

		$order_rows = Jws_Drama_Orders::history( $user_id, $per_page + 1, ( $order_paged - 1 ) * $per_page );
		$order_more = count( $order_rows ) > $per_page;
		$order_rows = array_slice( $order_rows, 0, $per_page );
		?>

		<div class="jws-drama-member-header">
			<?php echo get_avatar( $user_id, 64, '', '', array( 'extra_attr' => 'style="border-radius:50%"' ) ); ?>
			<div>
				<h2 style="margin:0 0 4px"><?php echo esc_html( $user->display_name ); ?></h2>
				<p class="description" style="margin:0">
					<?php echo esc_html( $user->user_email ); ?> · <?php echo esc_html( $user->user_login ); ?> ·
					<?php printf( esc_html__( 'joined %s', 'jws_streamvid' ), esc_html( mysql2date( get_option( 'date_format' ), $user->user_registered ) ) ); ?>
					· <a href="<?php echo esc_url( get_edit_user_link( $user_id ) ); ?>"><?php echo esc_html__( 'Edit user profile', 'jws_streamvid' ); ?></a>
				</p>
			</div>
		</div>

		<div class="jws-drama-member-grid">
			<div class="card jws-drama-card">
				<h3 style="margin-top:0"><?php echo esc_html__( 'Coin wallet', 'jws_streamvid' ); ?></h3>
				<p class="jws-drama-balance"><?php echo esc_html( number_format_i18n( $balance ) ); ?> <span class="description"><?php echo esc_html__( 'coins', 'jws_streamvid' ); ?></span></p>

				<form method="post">
					<?php wp_nonce_field( 'jws_drama_member_action_' . $user_id, 'jws_drama_member_nonce' ); ?>
					<input type="hidden" name="jws_drama_member_action" value="adjust" />
					<input type="hidden" name="member_user_id" value="<?php echo (int) $user_id; ?>" />
					<input type="hidden" name="wallet_paged" value="<?php echo (int) $wallet_paged; ?>" />
					<input type="hidden" name="order_paged" value="<?php echo (int) $order_paged; ?>" />
					<p style="    display: flex;
    flex-wrap: wrap;
    gap: 10px;">
						<select name="member_coin_action">
							<option value="add"><?php echo esc_html__( '+ Add coins', 'jws_streamvid' ); ?></option>
							<option value="subtract"><?php echo esc_html__( '− Deduct coins', 'jws_streamvid' ); ?></option>
						</select>
						<input type="number" name="member_coin_amount" min="1" value="" placeholder="0" style="width:110px" />
						<input type="text" name="member_coin_note" value=""  placeholder="<?php echo esc_attr__( 'Reason (optional)', 'jws_streamvid' ); ?>" />
						<?php submit_button( esc_html__( 'Apply', 'jws_streamvid' ), 'secondary', '', false ); ?>
					</p>
					<p class="description"><?php echo esc_html__( 'Writes a ledger entry either way.', 'jws_streamvid' ); ?></p>
				</form>
			</div>

		</div>

		<h3><?php echo esc_html__( 'Wallet ledger', 'jws_streamvid' ); ?></h3>
		<div class="jws-drama-table-wrap">
			<table class="widefat striped">
				<thead><tr>
					<th><?php echo esc_html__( 'When', 'jws_streamvid' ); ?></th>
					<th><?php echo esc_html__( 'Change', 'jws_streamvid' ); ?></th>
					<th><?php echo esc_html__( 'Balance after', 'jws_streamvid' ); ?></th>
					<th><?php echo esc_html__( 'Type', 'jws_streamvid' ); ?></th>
					<th><?php echo esc_html__( 'Note', 'jws_streamvid' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( ! $wallet_rows ) : ?>
					<tr><td colspan="5"><?php echo esc_html__( 'No wallet activity yet.', 'jws_streamvid' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $wallet_rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row->created_at ); ?></td>
							<td style="color:<?php echo $row->delta < 0 ? '#b32d2e' : '#008a20'; ?>"><?php echo esc_html( ( $row->delta > 0 ? '+' : '' ) . (int) $row->delta ); ?></td>
							<td><?php echo (int) $row->balance_after; ?></td>
							<td><?php echo esc_html( $row->type ); ?></td>
							<td><?php echo esc_html( $row->note ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
		$this->render_simple_pager(
			$wallet_paged,
			$wallet_more,
			array(
				'view_user'   => $user_id,
				'order_paged' => $order_paged,
			),
			'wallet_paged'
		);
		?>

		<h3><?php echo esc_html__( 'Purchases', 'jws_streamvid' ); ?></h3>
		<div class="jws-drama-table-wrap">
			<table class="widefat striped">
				<thead><tr>
					<th><?php echo esc_html__( 'When', 'jws_streamvid' ); ?></th>
					<th><?php echo esc_html__( 'What', 'jws_streamvid' ); ?></th>
					<th><?php echo esc_html__( 'Amount', 'jws_streamvid' ); ?></th>
					<th><?php echo esc_html__( 'Via', 'jws_streamvid' ); ?></th>
					<th><?php echo esc_html__( 'Status', 'jws_streamvid' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( ! $order_rows ) : ?>
					<tr><td colspan="5"><?php echo esc_html__( 'No purchases yet.', 'jws_streamvid' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $order_rows as $order ) : ?>
						<tr>
							<td><?php echo esc_html( $order->created_at ); ?></td>
							<td><?php echo esc_html( $order->label ); ?><?php echo $order->coins ? ' <span class="description">(' . (int) $order->coins . ' coins)</span>' : ''; ?></td>
							<td><?php echo esc_html( self::format_price( $order->amount, $order->currency ) ); ?></td>
							<td><?php echo esc_html( ucfirst( $order->gateway ) . ( $order->method ? ' · ' . $order->method : '' ) ); ?></td>
							<td><span class="jws-drama-badge jws-drama-badge-<?php echo esc_attr( $order->status ); ?>"><?php echo esc_html( $this->status_label( $order->status ) ); ?></span></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
		$this->render_simple_pager(
			$order_paged,
			$order_more,
			array(
				'view_user'    => $user_id,
				'wallet_paged' => $wallet_paged,
			),
			'order_paged'
		);
	}

	/** Numbered pager for a query whose total is known. */
	private function render_pagination( $current, $total_pages, array $keep, $page_param ) {

		if ( $total_pages <= 1 ) {
			return;
		}
		?>
		<p class="jws-drama-pager">
			<?php if ( $current > 1 ) : ?>
				<a class="button" href="<?php echo esc_url( $this->member_url( array_merge( $keep, array( $page_param => $current - 1 ) ) ) . '#members' ); ?>">&larr; <?php echo esc_html__( 'Previous', 'jws_streamvid' ); ?></a>
			<?php endif; ?>
			<span class="description" style="margin:0 8px">
				<?php printf( esc_html__( 'Page %1$d of %2$d', 'jws_streamvid' ), (int) $current, (int) $total_pages ); ?>
			</span>
			<?php if ( $current < $total_pages ) : ?>
				<a class="button" href="<?php echo esc_url( $this->member_url( array_merge( $keep, array( $page_param => $current + 1 ) ) ) . '#members' ); ?>"><?php echo esc_html__( 'Next', 'jws_streamvid' ); ?> &rarr;</a>
			<?php endif; ?>
		</p>
		<?php
	}

	/** Prev/Next pager for a list whose total is not worth a COUNT query for. */
	private function render_simple_pager( $current, $has_more, array $keep, $page_param ) {

		if ( 1 === $current && ! $has_more ) {
			return;
		}
		?>
		<p class="jws-drama-pager">
			<?php if ( $current > 1 ) : ?>
				<a class="button" href="<?php echo esc_url( $this->member_url( array_merge( $keep, array( $page_param => $current - 1 ) ) ) . '#members' ); ?>">&larr; <?php echo esc_html__( 'Previous', 'jws_streamvid' ); ?></a>
			<?php endif; ?>
			<?php if ( $has_more ) : ?>
				<a class="button" href="<?php echo esc_url( $this->member_url( array_merge( $keep, array( $page_param => $current + 1 ) ) ) . '#members' ); ?>"><?php echo esc_html__( 'Next', 'jws_streamvid' ); ?> &rarr;</a>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Inline rather than enqueued: it is a hundred lines that exist only for
	 * this screen, and a separate file would need its own registration, version
	 * and cache-busting for no gain.
	 */
	/* ---------------------------------------------------------------------- */
	/* Demo Import                                                             */
	/* ---------------------------------------------------------------------- */

	/**
	 * Generic short-drama style titles used only to fill the catalogue with
	 * something readable while trying the coin flow out. Every other field —
	 * synopsis, poster — is placeholder content generated by the tool itself,
	 * never pulled from a third party at run time. Episodes get no video of
	 * their own; they play through the site-wide drama default url instead.
	 */
	/**
	 * A handful of generic trope labels common across the short-drama genre
	 * as a whole (not tied to any one show or catalogue), used to seed
	 * `drama_tag` when the site has none yet, so a freshly installed demo
	 * still has something to randomly tag dramas with.
	 */
	private static function demo_drama_tag_names() {

		return array(
			'Alpha & Luna', 'Werewolf', 'Vampire', 'Billionaire', 'Revenge',
			'Second Chance', 'Marriage of Convenience', 'Enemies to Lovers',
			'Fated Mates', 'Secret Baby', 'Amnesia', 'Mafia', 'Royalty',
			'Rejected Mate', 'Contract Marriage', 'Rags to Riches', 'Betrayal',
		);
	}

	/**
	 * Term ids for demo_drama_tag_names(), creating whichever of them do not
	 * exist yet. Existing `drama_tag` terms are used as-is — nothing is
	 * created if the taxonomy is already populated with its own vocabulary.
	 */
	private function ensure_demo_drama_tags() {

		if ( ! taxonomy_exists( 'drama_tag' ) ) {
			return array();
		}

		$existing = get_terms( array( 'taxonomy' => 'drama_tag', 'hide_empty' => false ) );
		$existing = ( ! is_wp_error( $existing ) && $existing ) ? wp_list_pluck( $existing, 'term_id' ) : array();

		if ( $existing ) {
			return $existing;
		}

		$ids = array();

		foreach ( self::demo_drama_tag_names() as $name ) {

			$term = term_exists( $name, 'drama_tag' );

			if ( ! $term ) {
				$term = wp_insert_term( $name, 'drama_tag' );
			}

			if ( ! is_wp_error( $term ) && isset( $term['term_id'] ) ) {
				$ids[] = (int) $term['term_id'];
			}
		}

		return $ids;
	}

	private static function demo_titles() {

		return array(
			'The Great and Powerful Genie', 'Abandoned Pawn, Unrivaled Dragon King', 'In Bed with My Brother-in-Law',
			'The Alpha Princess Is Gone for Good', 'Wasteland Sovereign', 'Mated to the Alpha and His Beta',
			"You've Been Replaced, First Love", 'Sold to the Warlord, Born for the Sky', 'Tempted by My Bad Boy Stepbrother',
			'After Her Seventh Heartbreak, I Took Mom Back to Heaven', "You Can't Stop My Super X-Ray Vision", 'I, The Contracted Djinn',
			"The Alpha's Forbidden Mate", "My Blood-Sucking Familiar Is My Husband's Lover", 'The Death Payout System: Escaping My Toxic Pack',
			"A Mother's Vengeance", 'From Puppet Bride to Alpha Queen', 'Bound By the Amnesiac Heir',
			"A Zombie Girl's Journey Home", 'Altarboy', "Mistaken as His Mate: The Luna's Regret",
			'Art of Falling in Love', 'Take Me Back to the Night We Met', "Keeping the Cowboy's Baby",
			'Dirty Work', 'Flunk: Season 1', 'Their Brother Lost in Space',
			'The Vampire Next Door', 'Cooking My Way Back to Love', 'The Reckoning Takes Flight',
			'Found A Homeless Billionaire Husband for Christmas', 'My X-Ray Vision Sees Right Through You', 'Miss You After Goodbye',
			'The Lost Quarterback Returns', 'I Accidentally Sexted My Enemy', "Step Aside, I'm the King of Capital",
			'You Are My Destiny', "Falling for My Ex's Mafia Dad", 'The Amber Trap',
			'The Atlantic Bride', "A Farm Girl's Reckoning", 'Zero to Alpha: Return of the Wolf King',
			'The Son Rises Alone', 'The Real Heiress Reclaims Her Place', "The Professor's Forbidden Dragon Prey",
			"The Silver Serpent's Bride", 'The Valkyrie Divorces the God of War', 'Full Court Legend',
			'Chained by Hades, the Underworld King', 'Married In A Heartbeat', 'Rejected Luna Is the Alpha Queen',
			'After the Sacred Whale Betrayed Me, I Contracted Poseidon', 'My Stolen Billionaire Life', 'Good with Her Hands',
			'Monster in His Eyes', 'Married a Gardener, Got a Prince', 'Fated To My Billionaire Call Boy',
			'Flash Vows', 'The Auctioned Mate', "Secretly Pregnant with the Billionaire's Daughter",
			'Crowned in His Claws', 'How to Kiss a Vampire', "Second Chance: The Tech Billionaire's Secret Family",
			'Forced to Marry My Ruined Ex: The Duke\'s Revenge', 'Pucked in the Friend Zone', "Daddy Help! Mommy's in Prison!",
			"When Love's Sorrow Plays Again", "Mommy's Little Savior", 'I Ditched My Ex and Had Five Babies with His Alpha Dad',
			'The Alpha King Sold Me to the War God', 'The Alpha and His Nanny Luna', "Fate of the Dragon's Bride",
			'After Cancer I Turn into A Badass', 'A Cinderella for Wolf King', 'Mommy, I Got You A Date',
			"The Godfather's Guardian Angel", "The Alpha and Beta's Shared Mate", 'Rent-A-Mom for the Billionaire Twins',
			'Waterboy: Second Down', 'Pucked and Pregnant', 'Waterboy',
			"The Senator's Son", 'Swept Away by My Janitor Husband', 'The Ultimate Fight for Love',
			'The Genius and the Bad Boy', 'Baby Daddy Goals', "Oops! I'm in Love with My Stepbrother",
			'Summer Situationship', "The Rockstar's Secret", 'The Fake Dating Spell',
			'A Spicy Text to My Nemesis', 'Once Love Is Lost, It Never Returns', "Fated to His Brother's Alpha",
			'Offside with the Hockey Star', 'True Heiress vs. Fake Queen Bee', 'Taming My Bullies 1-3',
			"Don't Miss Me When I'm Gone", 'Dear Brother, You Loved Me Too Late',
		);
	}

	private function handle_demo_import_post() {

		if ( empty( $_POST['jws_drama_demo_action'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$nonce = isset( $_POST['jws_drama_demo_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['jws_drama_demo_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::DEMO_NONCE ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['jws_drama_demo_action'] ) );
		$args   = array( 'page' => self::PAGE );

		if ( 'import' === $action ) {

			$count    = isset( $_POST['demo_count'] ) ? max( 1, min( count( self::demo_titles() ), (int) $_POST['demo_count'] ) ) : 10;
			$episodes = isset( $_POST['demo_episodes'] ) ? max( 1, min( 80, (int) $_POST['demo_episodes'] ) ) : 8;
			$publish  = ! empty( $_POST['demo_publish'] );

			$result = $this->import_demo_dramas( $count, $episodes, $publish );

			$args['demo_msg']     = 'imported';
			$args['demo_created'] = $result['dramas'];
			$args['demo_eps']     = $result['episodes'];
			$args['demo_skipped'] = $result['skipped'];

		} elseif ( 'delete' === $action ) {

			$deleted = $this->delete_demo_dramas();

			$args['demo_msg']     = 'deleted';
			$args['demo_deleted'] = $deleted;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) . '#demo_import' );
		exit;
	}

	/**
	 * Creates up to $count dramas (skipping any title already used by an
	 * existing drama post) with $episodes_per_drama placeholder episodes each.
	 */
	private function import_demo_dramas( $count, $episodes_per_drama, $publish ) {

		$status       = $publish ? 'publish' : 'draft';
		$created      = 0;
		$episodes_out = 0;
		$skipped      = 0;

		$genre_terms = get_terms( array( 'taxonomy' => 'genres', 'hide_empty' => false ) );
		$genre_terms = ( ! is_wp_error( $genre_terms ) && $genre_terms ) ? wp_list_pluck( $genre_terms, 'term_id' ) : array();

		$drama_tag_terms = $this->ensure_demo_drama_tags();

		foreach ( self::demo_titles() as $title ) {

			if ( $created >= $count ) {
				break;
			}

			if ( post_exists( $title, '', '', Jws_Drama_Post_Types::DRAMA ) ) {
				$skipped++;
				continue;
			}

			$drama_id = wp_insert_post(
				array(
					'post_type'    => Jws_Drama_Post_Types::DRAMA,
					'post_title'   => $title,
					'post_status'  => $status,
					'post_content' => sprintf(
						/* translators: %s: drama title */
						esc_html__( '%s is placeholder demo content added by the Demo Import tool. Replace this description, the poster and every episode with your own before going live.', 'jws_streamvid' ),
						$title
					),
					'post_excerpt' => esc_html__( 'Demo content — replace before going live.', 'jws_streamvid' ),
				),
				true
			);

			if ( is_wp_error( $drama_id ) ) {
				continue;
			}

			update_post_meta( $drama_id, self::DEMO_META, 1 );
			update_post_meta( $drama_id, 'drama_status', 'ongoing' );
			update_post_meta( $drama_id, 'drama_total_ep', $episodes_per_drama );

			update_post_meta( $drama_id, '_drama_status', 'field_drama_status' );
			update_post_meta( $drama_id, '_drama_total_ep', 'field_drama_total_ep' );

			if ( $genre_terms ) {
				$pick = array_rand( $genre_terms, min( 2, count( $genre_terms ) ) );
				wp_set_object_terms( $drama_id, array_map( 'intval', (array) array_intersect_key( $genre_terms, array_flip( (array) $pick ) ) ), 'genres' );
			}

			if ( $drama_tag_terms ) {
				$tag_count = min( wp_rand( 1, 2 ), count( $drama_tag_terms ) );
				$tag_pick  = array_rand( $drama_tag_terms, $tag_count );
				wp_set_object_terms( $drama_id, array_map( 'intval', (array) array_intersect_key( $drama_tag_terms, array_flip( (array) $tag_pick ) ) ), 'drama_tag' );
			}

			$free_episodes = (int) self::all()['free_episodes'];

			for ( $number = 1; $number <= $episodes_per_drama; $number++ ) {

				$episode_id = wp_insert_post(
					array(
						'post_type'   => Jws_Drama_Post_Types::EPISODE,
						/* translators: %d: episode number */
						'post_title'  => sprintf( esc_html__( 'Episode %d', 'jws_streamvid' ), $number ),
						'post_status' => $status,
						'menu_order'  => $number,
					),
					true
				);

				if ( is_wp_error( $episode_id ) ) {
					continue;
				}

				update_post_meta( $episode_id, self::DEMO_META, 1 );
				update_post_meta( $episode_id, 'drama_id', $drama_id );
				update_post_meta( $episode_id, 'drama_ep_number', $number );
				update_post_meta( $episode_id, 'drama_ep_free', $number <= $free_episodes ? 1 : 0 );
				update_post_meta( $episode_id, 'videos_time', sprintf( '00:0%d:%02d', wp_rand( 1, 3 ), wp_rand( 10, 59 ) ) );

				/*
				 * No videos_type / videos_url written on purpose: an episode
				 * left without its own video falls back to the site-wide
				 * "Drama Short Default Url" (Jws Settings → Video Options),
				 * so demo episodes play without this tool needing to point
				 * at a video of its own.
				 */
				update_post_meta( $episode_id, '_drama_id', 'field_drama_ep_drama_id' );
				update_post_meta( $episode_id, '_drama_ep_number', 'field_drama_ep_number' );
				update_post_meta( $episode_id, '_drama_ep_free', 'field_drama_ep_free' );
				update_post_meta( $episode_id, '_videos_time', 'field_drama_ep_duration' );

				$episodes_out++;
			}

			$created++;
		}

		return array( 'dramas' => $created, 'episodes' => $episodes_out, 'skipped' => $skipped );
	}

	/** Trashes (not force-deletes) every drama and episode the importer created, so it can be undone from the Trash. */
	private function delete_demo_dramas() {

		$ids = get_posts(
			array(
				'post_type'      => array( Jws_Drama_Post_Types::DRAMA, Jws_Drama_Post_Types::EPISODE ),
				'post_status'    => array( 'publish', 'draft', 'pending', 'trash' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => self::DEMO_META,
			)
		);

		foreach ( $ids as $id ) {
			wp_trash_post( $id );
		}

		return count( $ids );
	}

	private function render_demo_import() {

		$demo_count = count(
			get_posts(
				array(
					'post_type'      => Jws_Drama_Post_Types::DRAMA,
					'post_status'    => array( 'publish', 'draft', 'pending' ),
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'meta_key'       => self::DEMO_META,
				)
			)
		);
		?>
		<div class="jws-drama-tab" data-tab="demo_import">

			<?php if ( ! empty( $_GET['demo_msg'] ) ) : ?>
				<?php if ( 'imported' === $_GET['demo_msg'] ) : ?>
					<div class="notice notice-success is-dismissible"><p>
						<?php
						printf(
							/* translators: 1: dramas created, 2: episodes created, 3: titles skipped */
							esc_html__( 'Imported %1$d demo drama(s) with %2$d episode(s). %3$d title(s) already existed and were skipped.', 'jws_streamvid' ),
							isset( $_GET['demo_created'] ) ? absint( $_GET['demo_created'] ) : 0,
							isset( $_GET['demo_eps'] ) ? absint( $_GET['demo_eps'] ) : 0,
							isset( $_GET['demo_skipped'] ) ? absint( $_GET['demo_skipped'] ) : 0
						);
						?>
					</p></div>
				<?php elseif ( 'deleted' === $_GET['demo_msg'] ) : ?>
					<div class="notice notice-success is-dismissible"><p>
						<?php
						printf(
							/* translators: %d: posts trashed */
							esc_html__( 'Moved %d demo post(s) to Trash.', 'jws_streamvid' ),
							isset( $_GET['demo_deleted'] ) ? absint( $_GET['demo_deleted'] ) : 0
						);
						?>
					</p></div>
				<?php endif; ?>
			<?php endif; ?>

			<p class="description" style="max-width:760px">
				<?php echo esc_html__( 'Fills the catalogue with placeholder dramas so the coin wall, free-episode limit and buy panel can be tried out end to end. Title, poster and description are generated placeholder content — swap them for the real thing before the site goes live. Episodes get no video of their own; set "Drama Short Default Url" under Jws Settings → Video Options so they still play.', 'jws_streamvid' ); ?>
			</p>

			<form method="post" style="max-width:520px">
				<?php wp_nonce_field( self::DEMO_NONCE, 'jws_drama_demo_nonce' ); ?>
				<input type="hidden" name="jws_drama_demo_action" value="import" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="demo_count"><?php echo esc_html__( 'Number of dramas', 'jws_streamvid' ); ?></label></th>
						<td><input type="number" min="1" max="<?php echo (int) count( self::demo_titles() ); ?>" id="demo_count" name="demo_count" value="10" class="small-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="demo_episodes"><?php echo esc_html__( 'Episodes per drama', 'jws_streamvid' ); ?></label></th>
						<td><input type="number" min="1" max="80" id="demo_episodes" name="demo_episodes" value="8" class="small-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Publish immediately', 'jws_streamvid' ); ?></th>
						<td><label><input type="checkbox" name="demo_publish" value="1" checked="checked" /> <?php echo esc_html__( 'Publish instead of saving as draft', 'jws_streamvid' ); ?></label></td>
					</tr>
				</table>
				<?php submit_button( esc_html__( 'Import Demo Dramas', 'jws_streamvid' ), 'primary', '', false ); ?>
			</form>

			<p style="margin-top:24px">
				<?php
				printf(
					/* translators: %d: number of demo posts already on the site */
					esc_html__( '%d demo drama(s) currently on the site.', 'jws_streamvid' ),
					(int) $demo_count
				);
				?>
			</p>

			<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Move every demo drama and episode to Trash?', 'jws_streamvid' ) ); ?>');">
				<?php wp_nonce_field( self::DEMO_NONCE, 'jws_drama_demo_nonce' ); ?>
				<input type="hidden" name="jws_drama_demo_action" value="delete" />
				<?php submit_button( esc_html__( 'Move Demo Dramas to Trash', 'jws_streamvid' ), 'secondary', '', false ); ?>
			</form>
		</div>
		<?php
	}

	private function render_assets() {
		?>
		<style>
			.jws-drama-settings .jws-drama-tab { display: none; }
			.jws-drama-settings .jws-drama-tab.is-active { display: block; }
			.jws-drama-settings .jws-drama-repeater { margin-top: 12px; }
			.jws-drama-settings .jws-pk-preview { font-weight: 600; }
			.jws-drama-settings .nav-tab-wrapper { margin-bottom: 16px; }
			.jws-drama-settings .jws-drama-table-wrap { overflow-x: auto; }

			.jws-drama-settings .jws-drama-toggle {
				display: inline-flex;
				align-items: center;
				gap: 6px;
				white-space: nowrap;
			}

			.jws-drama-settings .jws-drama-stats {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
				gap: 12px;
				margin: 16px 0 20px;
			}
			.jws-drama-settings .jws-drama-stat {
				background: #fff;
				border: 1px solid #dcdcde;
				border-radius: 6px;
				padding: 14px 16px;
			}
			.jws-drama-settings .jws-drama-stat-num {
				display: block;
				font-size: 22px;
				font-weight: 600;
				line-height: 1.3;
			}
			.jws-drama-settings .jws-drama-stat-label {
				display: block;
				color: #646970;
				font-size: 12px;
				text-transform: uppercase;
				letter-spacing: .02em;
			}

			.jws-drama-settings .jws-drama-member-filters {
				display: flex;
				flex-wrap: wrap;
				align-items: center;
				gap: 8px;
				margin-bottom: 16px;
			}

			.jws-drama-settings .jws-drama-badge {
				display: inline-block;
				padding: 2px 8px;
				border-radius: 10px;
				font-size: 11px;
				font-weight: 600;
				text-transform: uppercase;
				letter-spacing: .02em;
				background: #f0f0f1;
				color: #50575e;
			}
			.jws-drama-settings .jws-drama-badge-vip,
			.jws-drama-settings .jws-drama-badge-paid,
			.jws-drama-settings .jws-drama-badge-active { background: #edfaef; color: #008a20; }
			.jws-drama-settings .jws-drama-badge-pending,
			.jws-drama-settings .jws-drama-badge-past_due { background: #fcf3e3; color: #996800; }
			.jws-drama-settings .jws-drama-badge-failed,
			.jws-drama-settings .jws-drama-badge-refunded,
			.jws-drama-settings .jws-drama-badge-canceled { background: #fbeaea; color: #b32d2e; }

			.jws-drama-settings .jws-drama-member-header {
				display: flex;
				align-items: center;
				gap: 14px;
				margin: 8px 0 20px;
			}
			.jws-drama-settings .jws-drama-member-grid {
				display: grid;
				grid-template-columns: minmax(240px, 320px) 1fr;
				gap: 16px;
				align-items: start;
				margin-bottom: 24px;
			}
			.jws-drama-settings .jws-drama-card { padding: 14px 16px; }
			.jws-drama-settings .jws-drama-balance { font-size: 26px; font-weight: 600; margin: 4px 0 14px; }
			.jws-drama-settings .jws-drama-pager { margin-top: 10px; }


			@media (max-width: 782px) {
				.jws-drama-settings .jws-drama-member-grid { grid-template-columns: 1fr; }
			}

			.jws-drama-settings .jws-drama-coin-icon {
				display: flex;
				align-items: center;
				gap: 10px;
			}
			.jws-drama-settings .jws-drama-coin-icon img {
				width: 40px;
				height: 40px;
				border-radius: 50%;
				object-fit: cover;
				border: 1px solid #dcdcde;
			}
		</style>
		<script>
		( function () {
			var wrap = document.querySelector( '.jws-drama-settings' );

			if ( ! wrap ) { return; }

			/* Tabs. currentTab is the source of truth for "which one is open" —
			   window.location.hash agrees with it after every click, but a page
			   that loaded with no hash at all (or an invalid one) never had a
			   reason to write one, so the variable is what the submit handler
			   below reads rather than re-deriving it from the URL. */
			var currentTab = null;

			function show( id ) {
				currentTab = id;
				wrap.querySelectorAll( '.nav-tab' ).forEach( function ( tab ) {
					tab.classList.toggle( 'nav-tab-active', tab.dataset.tab === id );
				} );
				wrap.querySelectorAll( '.jws-drama-tab' ).forEach( function ( panel ) {
					panel.classList.toggle( 'is-active', panel.dataset.tab === id );
				} );
			}

			/* A search, a pager click or the adjust-balance redirect all land back
			   here as a plain GET with no guarantee the #members fragment survived,
			   so the query string is checked too before falling back to the hash. */
			var first       = wrap.querySelector( '.nav-tab' );
			var params      = new URLSearchParams( window.location.search );
			var memberKeys  = [ 'view_user', 'member_s', 'member_filter', 'member_paged', 'member_sort', 'wallet_paged', 'order_paged' ];
			var isMembers   = memberKeys.some( function ( key ) { return params.has( key ) && '' !== params.get( key ); } );
			var start       = isMembers ? 'members' : ( window.location.hash || '' ).replace( '#', '' );

			show( wrap.querySelector( '.jws-drama-tab[data-tab="' + start + '"]' ) ? start : first.dataset.tab );

			wrap.addEventListener( 'click', function ( event ) {
				var tab = event.target.closest( '.nav-tab' );
				if ( tab ) {
					event.preventDefault();
					window.location.hash = tab.dataset.tab;
					show( tab.dataset.tab );
				}
			} );

			/*
			 * "Save Settings" posts to a form with no `action`, which every
			 * browser resolves to the current URL *without* its fragment — a
			 * plain reload keeps the tab you were on, but Save always bounced
			 * back to the first tab because the hash never survived the POST.
			 * Stamping the action with the current tab right before submit
			 * fixes that without turning the save into a redirect.
			 */
			var settingsForm = wrap.querySelector( 'form[method="post"]' );

			if ( settingsForm ) {
				settingsForm.addEventListener( 'submit', function () {
					var base = window.location.href.split( '#' )[ 0 ];
					settingsForm.setAttribute( 'action', base + '#' + ( currentTab || first.dataset.tab ) );
				} );
			}

			/* Repeater rows. New rows are numbered from a counter that starts
			   past the highest rendered index, so adding and removing in the
			   same session cannot collide; the server re-indexes on save. */
			var counters = {};

			wrap.querySelectorAll( '[data-repeater]' ).forEach( function ( list ) {
				counters[ list.dataset.repeater ] = list.querySelectorAll( '[name^="' + list.dataset.repeater + '["]' ).length + 100;
			} );

			wrap.addEventListener( 'click', function ( event ) {

				var add = event.target.closest( '.jws-drama-add' );

				if ( add ) {
					var key   = add.dataset.repeater;
					var tpl   = wrap.querySelector( 'template[data-template="' + key + '"]' );
					var list  = wrap.querySelector( '[data-repeater="' + key + '"]' );
					var host  = list.tagName === 'TABLE' ? list.querySelector( 'tbody' ) : list;
					var index = counters[ key ]++;

					host.insertAdjacentHTML( 'beforeend', tpl.innerHTML.replace( /__i__/g, index ) );

					return;
				}

				var remove = event.target.closest( '.jws-drama-remove' );

				if ( remove ) {
					var row = remove.closest( 'tr' );
					if ( row ) { row.remove(); }
				}
			} );

			/* Live "buyer sees" column, so the bonus maths is visible before
			   saving rather than after. */
			wrap.addEventListener( 'input', function ( event ) {

				if ( ! event.target.matches( '.jws-pk-base, .jws-pk-bonus' ) ) { return; }

				var row     = event.target.closest( 'tr' );
				var base    = parseInt( row.querySelector( '.jws-pk-base' ).value, 10 ) || 0;
				var bonus   = parseInt( row.querySelector( '.jws-pk-bonus' ).value, 10 ) || 0;
				var preview = row.querySelector( '.jws-pk-preview' );

				preview.innerHTML = base
					? ( base + bonus ).toLocaleString() + ( bonus ? ' <span style="color:#b32d2e">+' + Math.round( bonus / base * 100 ) + '%</span>' : '' )
					: '—';
			} );

			/*
			 * Coin icon: the standard core single-image picker.
			 *
			 * wp.media is defined by media-editor.js, which WordPress prints
			 * in the admin footer — after this inline script, which runs the
			 * moment the parser reaches it. Checking `wp.media` up here would
			 * always see it as undefined and silently skip binding the click
			 * handler; checking inside the handler instead means the only
			 * thing that has to have loaded by then is whatever the viewer
			 * did before clicking, which by definition already has.
			 */
			var coinIconInput   = wrap.querySelector( '#coin_icon' );
			var coinIconPreview = wrap.querySelector( '#coin_icon_preview' );
			var coinIconSelect  = wrap.querySelector( '#coin_icon_select' );
			var coinIconRemove  = wrap.querySelector( '#coin_icon_remove' );
			var coinIconFrame   = null;

			if ( coinIconSelect ) {

				coinIconSelect.addEventListener( 'click', function ( event ) {

					event.preventDefault();

					if ( ! window.wp || ! wp.media ) {
						return;
					}

					if ( ! coinIconFrame ) {
						coinIconFrame = wp.media( {
							title: coinIconSelect.textContent,
							multiple: false,
							library: { type: 'image' }
						} );

						coinIconFrame.on( 'select', function () {
							var attachment = coinIconFrame.state().get( 'selection' ).first().toJSON();
							var thumb      = ( attachment.sizes && ( attachment.sizes.thumbnail || attachment.sizes.full ) ) || attachment;

							coinIconInput.value = attachment.id;
							coinIconPreview.src = thumb.url;
							coinIconPreview.hidden = false;
							coinIconRemove.hidden = false;
						} );
					}

					coinIconFrame.open();
				} );

				if ( coinIconRemove ) {
					coinIconRemove.addEventListener( 'click', function ( event ) {
						event.preventDefault();
						coinIconInput.value = '0';
						coinIconPreview.hidden = true;
						coinIconRemove.hidden = true;
					} );
				}
			}

		}() );
		</script>
		<?php
	}
}
