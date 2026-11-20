<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * Unified payment system — settings screen.
 *
 * The master switch here decides which checkout the site runs on. Off, the
 * three original paths are untouched: membership through the Paid Memberships
 * Pro checkout, buy/rent and coins through the WooCommerce cart. On, all three
 * are routed to this plugin's own checkout page, which takes the money with
 * its own Stripe and PayPal credentials — the ones entered below, deliberately
 * separate from the keys the Drama Coins screen holds.
 *
 * What each purchase *grants* never moves: PMPro still owns membership levels
 * and access, buy/rent still writes the same usermeta, coins still land in the
 * drama wallet. Only the page that charges the card changes.
 */
class Jws_Payment_Settings {

	const OPTION = 'jws_payment_settings';
	const NONCE  = 'jws_payment_settings_save';
	const PAGE   = 'jws_payment_system';

	/** The Database tab's own form, which saves nothing and so needs its own. */
	const REPAIR_NONCE = 'jws_payment_repair_tables';

	/** Filled by all() so a request reads the option once. */
	private static $cache = null;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Priority 20: "Jws Settings" is registered by the theme, whose
	 * admin_menu callback runs after every plugin's at the default
	 * priority — registering earlier would attach this submenu to a
	 * parent slug that does not exist yet and it would silently vanish.
	 */
	public function register_submenu() {
		add_submenu_page(
			'jws_settings',
			esc_html__( 'Payment System', 'jws_streamvid' ),
			esc_html__( 'Payment System', 'jws_streamvid' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/* ---------------------------------------------------------------------- */
	/* Storage                                                                 */
	/* ---------------------------------------------------------------------- */

	public static function defaults() {

		return array(
			'enabled'       => 0,

			/* Empty means "whatever the store already uses" — resolved by
			   currency() so a site that never opens this box keeps billing in
			   the currency PMPro and WooCommerce are already set to. */
			'currency'      => '',

			/* The page holding [jws_checkout]. Created on demand the first
			   time the system is switched on. */
			'checkout_page' => 0,

			/*
			 * Which buttons the checkout offers. `card` is the on-page Stripe
			 * Elements form; `quick_pay` is Stripe's own hosted page;
			 * apple_pay/google_pay are the browser's payment sheet, driven by
			 * the same Stripe account. They are listed separately because a
			 * shopper picks between them and a site may want one without the
			 * others.
			 */
			'methods'       => array( 'card', 'apple_pay', 'google_pay', 'paypal' ),

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

			/*
			 * No credentials, because there are none to hold: this hands the
			 * payment to the store's own checkout and whatever gateways are
			 * configured there. Only a switch, so a site running WooCommerce
			 * for something else entirely does not silently grow a second
			 * checkout route it never asked for.
			 */
			'woocommerce'   => array(
				'enabled' => 0,
			),

			/*
			 * Gateway objects already provisioned for recurring plans, as
			 * slot => array( stripe_price, stripe_coupon, paypal_product,
			 * paypal_plan ). Keyed by a hash of the money rather than by the
			 * PMPro level id: repricing a level makes a new Price, so everyone
			 * already subscribed keeps paying what they agreed to. Written by
			 * the gateway layer, never by this form.
			 */
			'gateway_refs'  => array(),
		) + self::extra_gateway_defaults();
	}

	/**
	 * Settings for the gateways in Jws_Payment_Gateways, each in its own key
	 * like `stripe` and `paypal`.
	 */
	private static function extra_gateway_defaults() {

		$out = array();

		if ( class_exists( 'Jws_Payment_Gateways' ) ) {
			foreach ( Jws_Payment_Gateways::all() as $id => $class ) {
				$out[ $id ] = $class::defaults();
			}
		}

		return $out;
	}

	/** Every gateway key holding settings of its own, old and new. */
	private static function gateway_keys() {

		return array_merge(
			array( 'stripe', 'paypal', 'woocommerce' ),
			class_exists( 'Jws_Payment_Gateways' ) ? array_keys( Jws_Payment_Gateways::all() ) : array()
		);
	}

	public static function all() {

		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();

		$out = array_merge( self::defaults(), $saved );

		/* array_merge is shallow, so a stored `stripe` missing a key added in
		   a later version would come back short of it. */
		foreach ( self::gateway_keys() as $gateway ) {
			$out[ $gateway ] = array_merge(
				self::defaults()[ $gateway ],
				isset( $saved[ $gateway ] ) && is_array( $saved[ $gateway ] ) ? $saved[ $gateway ] : array()
			);
		}

		self::$cache = $out;

		return $out;
	}

	public static function get( $key, $fallback = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	public static function save( array $settings ) {
		self::$cache = null;
		update_option( self::OPTION, $settings );
	}

	/** Writes one key without disturbing the rest. */
	public static function set( $key, $value ) {
		$all         = self::all();
		$all[ $key ] = $value;
		self::save( $all );
	}

	/** Whether the unified checkout is the one that takes the money. */
	public static function is_enabled() {
		return ! empty( self::get( 'enabled', 0 ) );
	}

	/**
	 * What everything is priced in.
	 *
	 * Left blank on the settings screen this follows the store, so the amount
	 * a shopper is charged never silently disagrees with the price they were
	 * shown on a PMPro level or a WooCommerce product.
	 */
	public static function currency() {

		$set = (string) self::get( 'currency', '' );

		return $set ? strtoupper( $set ) : self::store_currency();
	}

	/**
	 * The currency PMPro, or failing that WooCommerce, already charges in.
	 *
	 * What the checkout bills in when this screen leaves Currency on "Follow
	 * the store", and what that choice names, whatever is saved here.
	 */
	public static function store_currency() {

		/*
		 * The option, not pmpro_get_currency() — that returns the currency's
		 * formatting rules (symbol, separators, decimals), not its code.
		 */
		$pmpro = get_option( 'pmpro_currency' );

		if ( $pmpro && is_string( $pmpro ) ) {
			return strtoupper( $pmpro );
		}

		if ( function_exists( 'get_woocommerce_currency' ) ) {
			return strtoupper( get_woocommerce_currency() );
		}

		return 'USD';
	}

	/**
	 * Every currency the Currency select offers, code => name.
	 *
	 * WooCommerce's list when it is installed (the longest, and translated),
	 * otherwise PMPro's, otherwise the few most stores bill in. Keyed and
	 * sorted by code, which is how the select's options start, so typing a
	 * code in the closed select jumps straight to it.
	 */
	private static function currency_choices() {

		$out = array();

		if ( function_exists( 'get_woocommerce_currencies' ) ) {
			$out = get_woocommerce_currencies();
		} else {
			global $pmpro_currencies;

			if ( is_array( $pmpro_currencies ) ) {
				foreach ( $pmpro_currencies as $code => $currency ) {
					$out[ $code ] = is_array( $currency ) && isset( $currency['name'] ) ? $currency['name'] : (string) $currency;
				}
			}
		}

		if ( ! $out ) {
			$out = array(
				'USD' => esc_html__( 'United States (US) dollar', 'jws_streamvid' ),
				'EUR' => esc_html__( 'Euro', 'jws_streamvid' ),
				'GBP' => esc_html__( 'Pound sterling', 'jws_streamvid' ),
			);
		}

		$clean = array();

		foreach ( $out as $code => $name ) {
			$code = strtoupper( (string) $code );

			/* Only real three-letter codes: the same rule the saved value has
			   to pass in sanitize(). */
			if ( preg_match( '/^[A-Z]{3}$/', $code ) ) {
				/* WooCommerce writes some names with entities ("&#x111;"). */
				$clean[ $code ] = html_entity_decode( wp_strip_all_tags( (string) $name ), ENT_QUOTES, 'UTF-8' );
			}
		}

		ksort( $clean );

		return $clean;
	}

	/* ---------------------------------------------------------------------- */
	/* Payment methods                                                         */
	/* ---------------------------------------------------------------------- */

	/** Every method the checkout knows how to run, switched on or not. */
	public static function method_catalog() {

		return array(
			'card'       => array(
				'label'   => esc_html__( 'Credit or debit card', 'jws_streamvid' ),
				'note'    => esc_html__( 'Card form on your own checkout page (Stripe Elements).', 'jws_streamvid' ),
				'gateway' => 'stripe',
				'flow'    => 'elements',
				/* No single brand stands for "any card": a plain glyph instead. */
				'image'   => '',
			),
			'apple_pay'  => array(
				'label'   => esc_html__( 'Apple Pay', 'jws_streamvid' ),
				'note'    => esc_html__( "The browser's own payment sheet. Safari only, and only on a device that has a card set up.", 'jws_streamvid' ),
				'gateway' => 'stripe',
				'flow'    => 'wallet',
				'image'   => 'apple.png',
			),
			'google_pay' => array(
				'label'   => esc_html__( 'Google Pay', 'jws_streamvid' ),
				'note'    => esc_html__( "The browser's own payment sheet. Chrome, on a device that has a card set up.", 'jws_streamvid' ),
				'gateway' => 'stripe',
				'flow'    => 'wallet',
				'image'   => 'google.png',
			),
			'paypal'     => array(
				'label'   => esc_html__( 'PayPal', 'jws_streamvid' ),
				'note'    => esc_html__( 'Sends the buyer to PayPal and back.', 'jws_streamvid' ),
				'gateway' => 'paypal',
				'flow'    => 'redirect',
				'image'   => 'paypal.svg',
			),
			'quick_pay'  => array(
				'label'   => esc_html__( 'Stripe', 'jws_streamvid' ),
				'note'    => esc_html__( "Sends the buyer to Stripe's own checkout page instead of taking the card here.", 'jws_streamvid' ),
				'gateway' => 'stripe',
				'flow'    => 'redirect',
				'image'   => 'stripe.svg',
			),
			'woocommerce' => array(
				'label'   => esc_html__( 'WooCommerce', 'jws_streamvid' ),
				'note'    => esc_html__( "Hands the payment to the store's own checkout, so the buyer can pay with anything WooCommerce is already set up to take. A membership bought this way is paid one term at a time and does not renew itself.", 'jws_streamvid' ),
				'gateway' => 'woocommerce',
				'flow'    => 'redirect',
				'image'   => 'woocommerce.png',
			),
		) + self::extra_gateway_methods();
	}

	/**
	 * The full URL of a method's logo, or '' when it has none.
	 *
	 * One answer for the checkout's method rows and the Payment methods tab.
	 * A logo named but missing on disk counts as none, so a gateway added
	 * without artwork falls back to the card glyph instead of a broken image.
	 */
	public static function method_image_url( $key ) {

		$catalog = self::method_catalog();
		$file    = isset( $catalog[ $key ]['image'] ) ? (string) $catalog[ $key ]['image'] : '';

		if ( '' === $file || ! file_exists( JWS_STREAMVID_PATH_PUBLIC . '/assets/images/payment/' . $file ) ) {
			return '';
		}

		return JWS_STREAMVID_URL_PUBLIC_ASSETS . '/images/payment/' . $file;
	}

	/**
	 * One checkout row per gateway in Jws_Payment_Gateways, keyed by the
	 * gateway id: each of them is a single hosted page with its own methods
	 * behind it, so there is nothing finer to choose between here.
	 */
	private static function extra_gateway_methods() {

		$out = array();

		if ( class_exists( 'Jws_Payment_Gateways' ) ) {
			foreach ( Jws_Payment_Gateways::all() as $id => $class ) {
				$out[ $id ] = array(
					'label'   => $class::label(),
					'note'    => $class::method_note(),
					'gateway' => $id,
					'flow'    => 'redirect',
					'image'   => $class::logo(),
				);
			}
		}

		return $out;
	}

	/**
	 * The methods the checkout can actually offer right now.
	 *
	 * Switched on by the admin *and* backed by a gateway that is switched on
	 * and holds a secret — a Stripe button with no Stripe key is a button that
	 * can only fail. Whether the shopper's browser can do Apple or Google Pay
	 * is a question only the browser can answer, so that filter happens there.
	 */
	public static function available_methods() {

		$enabled = (array) self::get( 'methods', array() );
		$out     = array();

		foreach ( self::method_catalog() as $key => $method ) {

			if ( ! in_array( $key, $enabled, true ) ) {
				continue;
			}

			if ( ! self::gateway_ready( $method['gateway'] ) ) {
				continue;
			}

			$out[ $key ] = $method;
		}

		return $out;
	}

	/** Whether one gateway is switched on and has the credentials it needs. */
	public static function gateway_ready( $gateway ) {

		$config = self::get( $gateway );

		if ( empty( $config['enabled'] ) ) {
			return false;
		}

		/*
		 * WooCommerce holds no credentials of ours — it is the store's own
		 * checkout, with the store's own gateways behind it. "Ready" here
		 * means the plugin is actually active, because with it deactivated
		 * the button would lead to a page that no longer exists.
		 */
		if ( 'woocommerce' === $gateway ) {
			return class_exists( 'Jws_Payment_Woocommerce' ) && Jws_Payment_Woocommerce::is_available();
		}

		$class = class_exists( 'Jws_Payment_Gateways' ) ? Jws_Payment_Gateways::get( $gateway ) : null;

		if ( $class ) {
			return $class::has_credentials( (array) $config );
		}

		return 'stripe' === $gateway
			? ! empty( $config['secret'] ) && ! empty( $config['publishable'] )
			: ! empty( $config['client_id'] ) && ! empty( $config['secret'] );
	}

	/* ---------------------------------------------------------------------- */
	/* Provisioned gateway objects                                             */
	/* ---------------------------------------------------------------------- */

	/**
	 * Where one gateway object is remembered.
	 *
	 * The mode is part of the key so switching an account from test to live
	 * cannot hand a live charge the id of a test Price.
	 */
	public static function gateway_slot( $gateway, $mode, $fingerprint ) {
		return $gateway . ':' . $mode . ':' . $fingerprint;
	}

	public static function refs( $slot ) {

		$all = (array) self::get( 'gateway_refs', array() );

		return isset( $all[ $slot ] ) && is_array( $all[ $slot ] ) ? $all[ $slot ] : array();
	}

	public static function remember( $slot, $key, $value ) {

		$all = (array) self::get( 'gateway_refs', array() );

		if ( ! isset( $all[ $slot ] ) || ! is_array( $all[ $slot ] ) ) {
			$all[ $slot ] = array();
		}

		$all[ $slot ][ $key ] = $value;

		self::set( 'gateway_refs', $all );
	}

	/** Drops a slot so the next sale provisions the object again. */
	public static function forget( $slot ) {

		$all = (array) self::get( 'gateway_refs', array() );

		unset( $all[ $slot ] );

		self::set( 'gateway_refs', $all );
	}

	/* ---------------------------------------------------------------------- */
	/* Saving                                                                  */
	/* ---------------------------------------------------------------------- */

	private function handle_post() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return null;
		}

		if ( isset( $_POST['jws_payment_repair_nonce'] ) ) {
			return $this->handle_repair();
		}

		if ( ! isset( $_POST['jws_payment_settings_nonce'] ) ) {
			return null;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jws_payment_settings_nonce'] ) ), self::NONCE ) ) {
			return array( 'type' => 'error', 'message' => esc_html__( 'Security check failed. Nothing was saved.', 'jws_streamvid' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'type' => 'error', 'message' => esc_html__( 'You cannot change these settings.', 'jws_streamvid' ) );
		}

		$settings = self::sanitize( wp_unslash( $_POST ) );

		self::save( $settings );

		/* Switching on with nowhere to send buyers is the one misconfiguration
		   that breaks every checkout at once, so the page is made here. */
		if ( ! empty( $settings['enabled'] ) ) {
			Jws_Payment_Checkout::ensure_page();
		}

		return array( 'type' => 'success', 'message' => esc_html__( 'Settings saved.', 'jws_streamvid' ) );
	}

	/**
	 * Re-runs the schema installer from the Database tab.
	 *
	 * dbDelta only adds what is missing, so this is safe to press at any time
	 * — but it is a write, so it goes through a nonce like any other.
	 */
	private function handle_repair() {

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jws_payment_repair_nonce'] ) ), self::REPAIR_NONCE ) ) {
			return array( 'type' => 'error', 'message' => esc_html__( 'Security check failed. Nothing was changed.', 'jws_streamvid' ) );
		}

		Jws_Payment_Ledger::install();

		$missing = wp_list_filter( Jws_Payment_Ledger::table_report(), array( 'exists' => false ) );

		return $missing
			? array( 'type' => 'error', 'message' => esc_html__( 'Some tables could not be created. Check that the database user may create tables.', 'jws_streamvid' ) )
			: array( 'type' => 'success', 'message' => esc_html__( 'Tables are up to date.', 'jws_streamvid' ) );
	}

	private static function sanitize( $raw ) {

		$stored = self::all();
		$out    = self::defaults();

		$out['enabled'] = ! empty( $raw['enabled'] ) ? 1 : 0;

		/* One of the select's options or nothing ("Follow the store"). A code
		   saved before the select existed is kept, and anything else posted
		   leaves the saved value as it was rather than inventing a currency
		   out of whatever three letters were left. */
		$posted = isset( $raw['currency'] ) ? strtoupper( sanitize_text_field( (string) $raw['currency'] ) ) : '';
		$kept   = strtoupper( (string) $stored['currency'] );

		$out['currency'] = '' === $posted || $posted === $kept || isset( self::currency_choices()[ $posted ] )
			? $posted
			: $kept;

		$out['checkout_page'] = isset( $raw['checkout_page'] ) ? absint( $raw['checkout_page'] ) : (int) $stored['checkout_page'];

		$allowed         = array_keys( self::method_catalog() );
		$out['methods']  = isset( $raw['methods'] ) && is_array( $raw['methods'] )
			? array_values( array_intersect( $allowed, array_map( 'sanitize_key', $raw['methods'] ) ) )
			: array();

		$stripe = isset( $raw['stripe'] ) && is_array( $raw['stripe'] ) ? $raw['stripe'] : array();

		$out['stripe'] = array(
			'enabled'        => ! empty( $stripe['enabled'] ) ? 1 : 0,
			'mode'           => isset( $stripe['mode'] ) && 'live' === $stripe['mode'] ? 'live' : 'test',
			'publishable'    => isset( $stripe['publishable'] ) ? sanitize_text_field( $stripe['publishable'] ) : '',
			'secret'         => isset( $stripe['secret'] ) ? sanitize_text_field( $stripe['secret'] ) : '',
			'webhook_secret' => isset( $stripe['webhook_secret'] ) ? sanitize_text_field( $stripe['webhook_secret'] ) : '',
			'country'        => isset( $stripe['country'] ) ? strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', $stripe['country'] ), 0, 2 ) ) : 'US',
		);

		$paypal = isset( $raw['paypal'] ) && is_array( $raw['paypal'] ) ? $raw['paypal'] : array();

		$out['paypal'] = array(
			'enabled'    => ! empty( $paypal['enabled'] ) ? 1 : 0,
			'mode'       => isset( $paypal['mode'] ) && 'live' === $paypal['mode'] ? 'live' : 'sandbox',
			'client_id'  => isset( $paypal['client_id'] ) ? sanitize_text_field( $paypal['client_id'] ) : '',
			'secret'     => isset( $paypal['secret'] ) ? sanitize_text_field( $paypal['secret'] ) : '',
			'webhook_id' => isset( $paypal['webhook_id'] ) ? sanitize_text_field( $paypal['webhook_id'] ) : '',
		);

		/*
		 * Not on the form, and it must survive a save: these are ids of real
		 * objects on Stripe and PayPal that people are already being charged
		 * against. Dropping them would silently provision duplicates.
		 */
		$out['gateway_refs'] = isset( $stored['gateway_refs'] ) && is_array( $stored['gateway_refs'] ) ? $stored['gateway_refs'] : array();

		/*
		 * A key changing means the ids cached against the old account point at
		 * objects this account cannot see, so the cache goes with it.
		 */
		$out['woocommerce'] = array(
			'enabled' => ! empty( $raw['woocommerce']['enabled'] ) ? 1 : 0,
		);

		if ( $out['stripe']['secret'] !== $stored['stripe']['secret'] || $out['paypal']['client_id'] !== $stored['paypal']['client_id'] ) {
			$out['gateway_refs'] = array();
		}

		foreach ( Jws_Payment_Gateways::all() as $id => $class ) {

			$out[ $id ] = $class::sanitize( $raw[ $id ] ?? array() );

			/*
			 * Plans cached against one account mean nothing to the next, so a
			 * changed credential drops this gateway's own slots — only its
			 * own, keyed "id:mode:fingerprint" by gateway_slot(), so pasting
			 * a new Razorpay key does not make Stripe provision every Price
			 * again.
			 */
			foreach ( $class::field_keys() as $field ) {

				if ( (string) ( $out[ $id ][ $field ] ?? '' ) === (string) ( $stored[ $id ][ $field ] ?? '' ) ) {
					continue;
				}

				foreach ( array_keys( $out['gateway_refs'] ) as $slot ) {
					if ( 0 === strpos( (string) $slot, $id . ':' ) ) {
						unset( $out['gateway_refs'][ $slot ] );
					}
				}

				break;
			}
		}

		return $out;
	}

	/* ---------------------------------------------------------------------- */
	/* Rendering                                                               */
	/* ---------------------------------------------------------------------- */

	/** The tabs, in the order someone setting this up would work through them. */
	private static function tabs() {

		return array(
			'general'     => esc_html__( 'General', 'jws_streamvid' ),
			'methods'     => esc_html__( 'Payment methods', 'jws_streamvid' ),
			/* Stripe and PayPal share one tab: each is the same short form
			   (switch, mode, keys, webhook), and two tabs for them made the
			   admin hunt for which one held the setting. */
			'gateways'    => esc_html__( 'Gateways', 'jws_streamvid' ),
			'woocommerce' => 'WooCommerce',
			'orders'      => esc_html__( 'Orders', 'jws_streamvid' ),
		);
	}

	/**
	 * A gateway's name as its own brand writes it.
	 *
	 * ucfirst() on the slug gets "Paypal", which is wrong everywhere it is
	 * shown and looks like a typo to anyone who has seen a PayPal invoice.
	 */
	/** Every gateway with a section on the Gateways tab, in tab order. */
	private static function settings_gateways() {
		return array_merge( array( 'stripe', 'paypal' ), array_keys( Jws_Payment_Gateways::all() ) );
	}

	/**
	 * A gateway's state for its tab: ready, switched on without its keys, or
	 * off.
	 *
	 * @return array array( css state, words for screen readers ).
	 */
	private static function gateway_state( $gateway, $s ) {

		if ( self::gateway_ready( $gateway ) ) {
			return array( 'on', esc_html__( 'Ready', 'jws_streamvid' ) );
		}

		if ( ! empty( $s[ $gateway ]['enabled'] ) ) {
			return array( 'warn', esc_html__( 'Keys missing', 'jws_streamvid' ) );
		}

		return array( 'off', esc_html__( 'Off', 'jws_streamvid' ) );
	}

	public static function gateway_label( $gateway ) {

		$names = array(
			'stripe'      => 'Stripe',
			'paypal'      => 'PayPal',
			'woocommerce' => 'WooCommerce',
		);

		if ( isset( $names[ $gateway ] ) ) {
			return $names[ $gateway ];
		}

		$class = class_exists( 'Jws_Payment_Gateways' ) ? Jws_Payment_Gateways::get( $gateway ) : null;

		return $class ? $class::label() : ucfirst( (string) $gateway );
	}

	/** Loads this screen's own styles and script, and nowhere else. */
	public function enqueue_assets( $hook_suffix ) {

		if ( false === strpos( (string) $hook_suffix, self::PAGE ) ) {
			return;
		}

		$base    = plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . 'admin/';
		$dir     = dirname( dirname( dirname( __FILE__ ) ) ) . '/admin/';
		$version = defined( 'JWS_STREAMVID_VERSION' ) ? JWS_STREAMVID_VERSION : '1.0.0';

		/* The file's own time, so a changed screen is not served from the
		   browser cache until the next version bump. */
		$css_ver = file_exists( $dir . 'css/jws-payment-admin.css' ) ? (string) filemtime( $dir . 'css/jws-payment-admin.css' ) : $version;
		$js_ver  = file_exists( $dir . 'js/jws-payment-admin.js' ) ? (string) filemtime( $dir . 'js/jws-payment-admin.js' ) : $version;

		wp_enqueue_style( 'jws-payment-admin', $base . 'css/jws-payment-admin.css', array(), $css_ver );
		wp_enqueue_script( 'jws-payment-admin', $base . 'js/jws-payment-admin.js', array(), $js_ver, true );

		wp_localize_script(
			'jws-payment-admin',
			'jws_payment_admin',
			array(
				'copied' => esc_html__( 'Copied', 'jws_streamvid' ),
				'copy'   => esc_html__( 'Copy', 'jws_streamvid' ),
				'show'   => esc_html__( 'Show', 'jws_streamvid' ),
				'hide'   => esc_html__( 'Hide', 'jws_streamvid' ),
			)
		);
	}

	public function render_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notice = $this->handle_post();
		$s      = self::all();

		/*
		 * POST for a form that was just submitted, GET for the order list's own
		 * filter and paging links — both have to come back to the tab the admin
		 * was on, or every filter change would bounce them to General.
		 */
		// phpcs:disable WordPress.Security.NonceVerification -- display only; the tab is not a setting.
		$active         = 'general';
		$active_gateway = '';

		if ( isset( $_POST['active_tab'] ) ) {
			$active = sanitize_key( wp_unslash( $_POST['active_tab'] ) );
		} elseif ( isset( $_GET['active_tab'] ) ) {
			$active = sanitize_key( wp_unslash( $_GET['active_tab'] ) );
		}

		if ( isset( $_POST['active_gateway'] ) ) {
			$active_gateway = sanitize_key( wp_unslash( $_POST['active_gateway'] ) );
		} elseif ( isset( $_GET['active_gateway'] ) ) {
			$active_gateway = sanitize_key( wp_unslash( $_GET['active_gateway'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification

		$gateway_ids = self::settings_gateways();

		/* A gateway's own name as the tab — Stripe and PayPal had a tab each
		   before — opens the Gateways tab on that gateway. */
		if ( in_array( $active, $gateway_ids, true ) ) {
			$active_gateway = $active;
			$active         = 'gateways';
		}

		$active         = isset( self::tabs()[ $active ] ) ? $active : 'general';
		$active_gateway = in_array( $active_gateway, $gateway_ids, true ) ? $active_gateway : $gateway_ids[0];
		?>
		<div class="wrap jws-pay" data-active-tab="<?php echo esc_attr( $active ); ?>">

			<h1><?php echo esc_html__( 'Payment System', 'jws_streamvid' ); ?></h1>

			<p class="jws-pay-lede">
				<?php echo esc_html__( 'One checkout for membership, buy/rent and coins, taking payment with its own gateway accounts: Stripe, PayPal, Razorpay, Paystack, Mollie, Mercado Pago, Square, Flutterwave, Xendit and Authorize.net. Switching it on replaces the WooCommerce cart and the Paid Memberships Pro checkout page — nothing else moves: PMPro keeps owning membership levels and access, buy/rent keeps unlocking the same way, coins keep landing in the same wallet.', 'jws_streamvid' ); ?>
			</p>

			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
			<?php endif; ?>

			<?php $this->render_summary( $s ); ?>
			<?php $this->render_warnings( $s ); ?>

			<nav class="nav-tab-wrapper jws-pay-tabs">
				<?php foreach ( self::tabs() as $key => $label ) : ?>
					<a href="#<?php echo esc_attr( $key ); ?>" class="nav-tab" data-tab="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<form method="post" class="jws-pay-form">
				<?php wp_nonce_field( self::NONCE, 'jws_payment_settings_nonce' ); ?>
				<input type="hidden" name="active_tab" value="<?php echo esc_attr( $active ); ?>" />

				<?php $this->panel_general( $s ); ?>
				<?php $this->panel_methods( $s ); ?>
				<div class="jws-pay-panel" data-panel="gateways">
					<p class="description jws-pay-panel-lede">
						<?php echo esc_html__( 'The accounts that take the money. Switch on each gateway you use and paste its keys; the methods it powers are picked on the Payment methods tab.', 'jws_streamvid' ); ?>
					</p>

					<?php /* One tab per gateway. The dot is the gateway's state at a
					         glance, so an admin can see which one still needs keys
					         without opening each. */ ?>
					<div class="jws-pay-subtabs" role="tablist" aria-label="<?php echo esc_attr__( 'Gateways', 'jws_streamvid' ); ?>">
						<?php foreach ( $gateway_ids as $gateway_id ) : ?>
							<?php
							$is_current = $gateway_id === $active_gateway;
							$state      = self::gateway_state( $gateway_id, $s );
							?>
							<button type="button" role="tab"
								id="jws-pay-subtab-<?php echo esc_attr( $gateway_id ); ?>"
								class="jws-pay-subtab<?php echo $is_current ? ' is-active' : ''; ?>"
								data-gateway="<?php echo esc_attr( $gateway_id ); ?>"
								aria-controls="jws-pay-gateway-<?php echo esc_attr( $gateway_id ); ?>"
								aria-selected="<?php echo $is_current ? 'true' : 'false'; ?>"
								tabindex="<?php echo $is_current ? '0' : '-1'; ?>">
								<span class="jws-pay-subtab-dot is-<?php echo esc_attr( $state[0] ); ?>" aria-hidden="true"></span>
								<?php echo esc_html( self::gateway_label( $gateway_id ) ); ?>
								<span class="screen-reader-text">(<?php echo esc_html( $state[1] ); ?>)</span>
							</button>
						<?php endforeach; ?>
					</div>
					<input type="hidden" name="active_gateway" value="<?php echo esc_attr( $active_gateway ); ?>" />

					<?php foreach ( $gateway_ids as $gateway_id ) : ?>
						<?php $this->panel_gateway( $gateway_id, $s, $gateway_id === $active_gateway ); ?>
					<?php endforeach; ?>
				</div>
				<?php $this->panel_woocommerce( $s ); ?>

				<p class="submit jws-pay-submit">
					<button type="submit" class="button button-primary button-hero"><?php echo esc_html__( 'Save Settings', 'jws_streamvid' ); ?></button>
				</p>
			</form>

			<?php $this->panel_orders(); ?>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------- */
	/* Summary + warnings                                                      */
	/* ---------------------------------------------------------------------- */

	/**
	 * The four things worth knowing before reading anything else.
	 *
	 * Whether the system is actually taking money, where its checkout lives,
	 * how many ways someone can pay, and in what currency — the answers that
	 * otherwise have to be pieced together from four separate tabs.
	 */
	private function render_summary( $s ) {

		$on       = ! empty( $s['enabled'] );
		$methods  = self::available_methods();
		$page     = (int) $s['checkout_page'];
		$page_ok  = $page && get_post( $page );
		?>
		<div class="jws-pay-summary">

			<div class="jws-pay-card <?php echo $on ? 'is-on' : 'is-off'; ?>">
				<span class="jws-pay-card-label"><?php echo esc_html__( 'Unified checkout', 'jws_streamvid' ); ?></span>
				<strong class="jws-pay-card-value"><?php echo $on ? esc_html__( 'On', 'jws_streamvid' ) : esc_html__( 'Off', 'jws_streamvid' ); ?></strong>
				<span class="jws-pay-card-note">
					<?php echo $on
						? esc_html__( 'Membership, buy/rent and coins all go through it.', 'jws_streamvid' )
						: esc_html__( 'PMPro checkout and the WooCommerce cart are still in charge.', 'jws_streamvid' ); ?>
				</span>
			</div>

			<div class="jws-pay-card <?php echo $methods ? 'is-on' : 'is-warn'; ?>">
				<span class="jws-pay-card-label"><?php echo esc_html__( 'Ways to pay', 'jws_streamvid' ); ?></span>
				<strong class="jws-pay-card-value"><?php echo (int) count( $methods ); ?></strong>
				<span class="jws-pay-card-note">
					<?php
					echo $methods
						? esc_html( implode( ', ', wp_list_pluck( $methods, 'label' ) ) )
						: esc_html__( 'Nobody can buy anything yet.', 'jws_streamvid' );
					?>
				</span>
			</div>

			<div class="jws-pay-card <?php echo $page_ok ? 'is-on' : 'is-warn'; ?>">
				<span class="jws-pay-card-label"><?php echo esc_html__( 'Checkout page', 'jws_streamvid' ); ?></span>
				<strong class="jws-pay-card-value">
					<?php if ( $page_ok ) : ?>
						<a href="<?php echo esc_url( get_permalink( $page ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_the_title( $page ) ); ?></a>
					<?php else : ?>
						<?php echo esc_html__( 'Not created', 'jws_streamvid' ); ?>
					<?php endif; ?>
				</strong>
				<span class="jws-pay-card-note">
					<?php echo $page_ok
						? esc_html( wp_parse_url( get_permalink( $page ), PHP_URL_PATH ) )
						: esc_html__( 'Save with the system enabled and it is made for you.', 'jws_streamvid' ); ?>
				</span>
			</div>

			<div class="jws-pay-card">
				<span class="jws-pay-card-label"><?php echo esc_html__( 'Currency', 'jws_streamvid' ); ?></span>
				<strong class="jws-pay-card-value"><?php echo esc_html( self::currency() ); ?></strong>
				<span class="jws-pay-card-note">
					<?php echo empty( $s['currency'] )
						? esc_html__( 'Following the store.', 'jws_streamvid' )
						: esc_html__( 'Set on this screen.', 'jws_streamvid' ); ?>
				</span>
			</div>
		</div>
		<?php
	}

	/** The misconfigurations that would break a live checkout, said plainly. */
	private function render_warnings( $s ) {

		if ( empty( $s['enabled'] ) ) {
			return;
		}

		$problems = array();

		if ( ! self::available_methods() ) {
			$problems[] = esc_html__( 'No payment method is usable — tick at least one method and fill in the keys for the gateway behind it. Until then nobody can buy anything.', 'jws_streamvid' );
		}

		if ( ! $s['checkout_page'] || ! get_post( $s['checkout_page'] ) ) {
			$problems[] = esc_html__( 'There is no checkout page yet. Save this screen once and it will be created.', 'jws_streamvid' );
		}

		if ( ! empty( $s['stripe']['enabled'] ) && empty( $s['stripe']['webhook_secret'] ) ) {
			$problems[] = esc_html__( 'Stripe has no webhook secret. Payments still complete when the buyer lands back on the site, but a closed tab or a renewal will not be recorded.', 'jws_streamvid' );
		}

		if ( ! empty( $s['paypal']['enabled'] ) && empty( $s['paypal']['webhook_id'] ) ) {
			$problems[] = esc_html__( 'PayPal has no webhook ID. One-off payments still capture on return, but subscription renewals will not be recorded.', 'jws_streamvid' );
		}

		foreach ( Jws_Payment_Gateways::all() as $id => $class ) {

			if ( empty( $s[ $id ]['enabled'] ) ) {
				continue;
			}

			if ( ! $class::has_credentials( (array) $s[ $id ] ) ) {
				$problems[] = sprintf(
					/* translators: %s: gateway name. */
					esc_html__( '%s is switched on but its keys are missing, so the checkout does not offer it.', 'jws_streamvid' ),
					$class::label()
				);
				continue;
			}

			$key_mode = $class::key_mode( (array) $s[ $id ] );

			if ( $key_mode && $key_mode !== $s[ $id ]['mode'] ) {
				$problems[] = sprintf(
					/* translators: 1: gateway name, 2: Test or Live, as the key says, 3: Test or Live, as the Mode setting says. */
					esc_html__( '%1$s has %2$s keys but its Mode is set to %3$s. The keys decide which one is charged, so check that they are the ones you meant.', 'jws_streamvid' ),
					$class::label(),
					'live' === $key_mode ? esc_html__( 'Live', 'jws_streamvid' ) : esc_html__( 'Test', 'jws_streamvid' ),
					'live' === $s[ $id ]['mode'] ? esc_html__( 'Live', 'jws_streamvid' ) : esc_html__( 'Test', 'jws_streamvid' )
				);
			}
		}

		foreach ( Jws_Payment_Ledger::table_report() as $table ) {
			if ( ! $table['exists'] ) {
				$problems[] = sprintf(
					/* translators: %s: database table name. */
					esc_html__( 'The table %s is missing. Nothing can be recorded until it is created — use "Repair tables" on the Database tab.', 'jws_streamvid' ),
					$table['name']
				);
			}
		}

		if ( ! $problems ) {
			return;
		}
		?>
		<div class="notice notice-warning jws-pay-problems">
			<p><strong><?php echo esc_html__( 'Needs attention', 'jws_streamvid' ); ?></strong></p>
			<ul>
				<?php foreach ( $problems as $problem ) : ?>
					<li><?php echo esc_html( $problem ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------- */
	/* Panels                                                                  */
	/* ---------------------------------------------------------------------- */

	private function panel_general( $s ) {

		$page = (int) $s['checkout_page'];
		?>
		<div class="jws-pay-panel" data-panel="general">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php echo esc_html__( 'Unified checkout', 'jws_streamvid' ); ?></th>
					<td>
						<label class="jws-pay-switch">
							<input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?> />
							<span><?php echo esc_html__( 'Enable the unified payment system', 'jws_streamvid' ); ?></span>
						</label>
						<p class="description">
							<?php echo esc_html__( 'On: membership, buy/rent and coin purchases all go through this checkout. Off: each keeps working exactly as it does today.', 'jws_streamvid' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="currency"><?php echo esc_html__( 'Currency', 'jws_streamvid' ); ?></label></th>
					<td>
						<?php
						$saved   = strtoupper( (string) $s['currency'] );
						$choices = self::currency_choices();

						/* A code saved before this was a select, and missing
						   from the list, stays selectable rather than being
						   swapped for the first option on the next save. */
						if ( $saved && ! isset( $choices[ $saved ] ) ) {
							$choices = array( $saved => $saved ) + $choices;
						}
						?>
						<select id="currency" name="currency" class="regular-text">
							<option value="" <?php selected( '', $saved ); ?>>
								<?php
								printf(
									/* translators: %s: currency code the store already uses. */
									esc_html__( 'Follow the store (%s)', 'jws_streamvid' ),
									esc_html( self::store_currency() )
								);
								?>
							</option>
							<?php foreach ( $choices as $code => $name ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $code, $saved ); ?>><?php echo esc_html( $code === $name ? $code : $code . ' - ' . $name ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php
							printf(
								/* translators: %s: currency code the store already uses. */
								esc_html__( 'Follow the store bills in the store currency, which is %s right now. Choose another currency only if this checkout should bill in something else.', 'jws_streamvid' ),
								'<code>' . esc_html( self::store_currency() ) . '</code>'
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Checkout page', 'jws_streamvid' ); ?></th>
					<td>
						<?php if ( $page && get_post( $page ) ) : ?>
							<a href="<?php echo esc_url( get_permalink( $page ) ); ?>" target="_blank" rel="noopener" class="button"><?php echo esc_html__( 'View', 'jws_streamvid' ); ?></a>
							<a href="<?php echo esc_url( get_edit_post_link( $page ) ); ?>" class="button"><?php echo esc_html__( 'Edit', 'jws_streamvid' ); ?></a>
							<code class="jws-pay-inline-code"><?php echo esc_html( get_permalink( $page ) ); ?></code>
						<?php else : ?>
							<em><?php echo esc_html__( 'Created automatically when you save with the system enabled.', 'jws_streamvid' ); ?></em>
						<?php endif; ?>
						<input type="hidden" name="checkout_page" value="<?php echo (int) $page; ?>" />
						<p class="description"><?php echo esc_html__( 'A normal page holding the [jws_checkout] shortcode. Style it like any other page.', 'jws_streamvid' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * The payment-method shelf.
	 *
	 * Each row says whether it will actually appear at checkout, because a
	 * method ticked here whose gateway has no keys is simply dropped — and
	 * silently, which is a hard thing to debug from the front end.
	 */
	private function panel_methods( $s ) {
		?>
		<div class="jws-pay-panel" data-panel="methods">
			<p class="description jws-pay-panel-lede">
				<?php echo esc_html__( 'What the checkout offers the buyer. A method only appears if the gateway behind it is switched on and has its keys.', 'jws_streamvid' ); ?>
			</p>

			<div class="jws-pay-methods">
				<?php foreach ( self::method_catalog() as $key => $method ) : ?>
					<?php
					$ticked = in_array( $key, (array) $s['methods'], true );
					$ready  = self::gateway_ready( $method['gateway'] );
					$logo   = self::method_image_url( $key );
					?>
					<label class="jws-pay-method <?php echo $ticked && $ready ? 'is-live' : ''; ?>">
						<input type="checkbox" name="methods[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $ticked ); ?> />
						<?php /* Decorative: the name is printed right next to it. */ ?>
						<span class="jws-pay-method-logo" aria-hidden="true">
							<?php if ( $logo ) : ?>
								<img src="<?php echo esc_url( $logo ); ?>" alt="" width="60" height="39" loading="lazy" />
							<?php else : ?>
								<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
									<rect x="2" y="5" width="20" height="14" rx="2" />
									<line x1="2" y1="10" x2="22" y2="10" />
								</svg>
							<?php endif; ?>
						</span>
						<span class="jws-pay-method-body">
							<span class="jws-pay-method-head">
								<strong><?php echo esc_html( $method['label'] ); ?></strong>
								<?php if ( $ticked && ! $ready ) : ?>
									<span class="jws-pay-badge is-warn">
										<?php
										printf(
											/* translators: %s: gateway name. */
											esc_html__( '%s not configured', 'jws_streamvid' ),
											esc_html( self::gateway_label( $method['gateway'] ) )
										);
										?>
									</span>
								<?php elseif ( $ticked ) : ?>
									<span class="jws-pay-badge is-on"><?php echo esc_html__( 'Live', 'jws_streamvid' ); ?></span>
								<?php endif; ?>
							</span>
							<span class="jws-pay-method-note"><?php echo esc_html( $method['note'] ); ?></span>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * The WooCommerce hand-off.
	 *
	 * Its own renderer rather than a third call to panel_gateway(), because
	 * there is nothing here that panel_gateway() renders: no mode, no keys, no
	 * webhook. The store already holds all of that, and whichever gateways are
	 * configured there are the ones this offers. A single switch is the whole
	 * setting.
	 */
	private function panel_woocommerce( $s ) {

		$config = isset( $s['woocommerce'] ) ? $s['woocommerce'] : array( 'enabled' => 0 );
		$active = class_exists( 'Jws_Payment_Woocommerce' ) && Jws_Payment_Woocommerce::is_available();
		?>
		<div class="jws-pay-panel" data-panel="woocommerce">

			<div class="jws-pay-gateway-head">
				<h2>WooCommerce</h2>
				<?php if ( $active && ! empty( $config['enabled'] ) ) : ?>
					<span class="jws-pay-badge is-on"><?php echo esc_html__( 'Ready', 'jws_streamvid' ); ?></span>
				<?php elseif ( ! empty( $config['enabled'] ) ) : ?>
					<span class="jws-pay-badge is-warn"><?php echo esc_html__( 'WooCommerce not active', 'jws_streamvid' ); ?></span>
				<?php else : ?>
					<span class="jws-pay-badge"><?php echo esc_html__( 'Off', 'jws_streamvid' ); ?></span>
				<?php endif; ?>
			</div>

			<p class="description jws-pay-panel-lede">
				<?php echo esc_html__( "Sends the buyer to the store's own checkout to pay, then brings them back here once WooCommerce says the order is paid. Useful for the payment methods this plugin has no gateway of its own for — bank transfer, cash on delivery, a local card processor — since whatever the store already takes, this takes.", 'jws_streamvid' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php echo esc_html__( 'Enable', 'jws_streamvid' ); ?></th>
					<td>
						<label class="jws-pay-switch">
							<input type="checkbox" name="woocommerce[enabled]" value="1" <?php checked( ! empty( $config['enabled'] ) ); ?> />
							<span><?php echo esc_html__( 'Take payments through the WooCommerce checkout', 'jws_streamvid' ); ?></span>
						</label>
						<?php if ( ! $active ) : ?>
							<p class="description"><?php echo esc_html__( 'WooCommerce is not active on this site, so there is no checkout to hand the payment to. The setting is kept either way, and takes effect as soon as WooCommerce is back.', 'jws_streamvid' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'What it sells', 'jws_streamvid' ); ?></th>
					<td>
						<p class="description">
							<?php echo esc_html__( 'One hidden virtual product, created the first time it is needed and priced from the order being paid. It never appears in the shop, and nothing needs to be set up in WooCommerce for it.', 'jws_streamvid' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Renewals', 'jws_streamvid' ); ?></th>
					<td>
						<p class="description">
							<?php echo esc_html__( 'A WooCommerce order is a single charge and nothing here can renew it — there is no WooCommerce Subscriptions on this site, and bank transfer or cash on delivery could not renew anything even if there were.', 'jws_streamvid' ); ?>
						</p>
						<p class="description">
							<?php
							/* Named from the registry, so a gateway added later
							   lands in this sentence without anyone editing it. */
							$renewing = array( 'Stripe', 'PayPal' );

							foreach ( Jws_Payment_Gateways::all() as $class ) {
								if ( $class::renews() ) {
									$renewing[] = $class::label();
								}
							}

							printf(
								/* translators: %s: list of gateway names, such as "Stripe, PayPal, and Mollie". */
								esc_html__( 'So a membership bought this way is sold as one paid term: the member gets one billing cycle, the membership then expires, and they buy it again to carry on. These gateways renew on their own: %s.', 'jws_streamvid' ),
								esc_html( wp_sprintf_l( '%l', $renewing ) )
							);
							?>
						</p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * One gateway's credentials, as a section of the Gateways tab.
	 *
	 * Stripe and PayPal ask for different things but in the same order — turn
	 * it on, pick the mode, paste the keys, wire the webhook — so they share a
	 * renderer rather than drifting into two shapes of the same form.
	 */
	private function panel_gateway( $gateway, $s, $is_current = true ) {

		$config = $s[ $gateway ];
		$ready  = self::gateway_ready( $gateway );
		$live   = 'live' === $config['mode'];
		$extra  = Jws_Payment_Gateways::get( $gateway );

		if ( $extra ) {
			/* The gateways built on Jws_Payment_Gateway describe their own
			   form, so this renderer has nothing to know about any of them. */
			$fields = $extra::fields();
			$modes  = array( 'test' => esc_html__( 'Test', 'jws_streamvid' ), 'live' => esc_html__( 'Live', 'jws_streamvid' ) );
			$events = implode( ', ', $extra::webhook_events() );
			$help   = $extra::webhook_help();
		} elseif ( 'stripe' === $gateway ) {
			$fields = array(
				'publishable'    => array( esc_html__( 'Publishable key', 'jws_streamvid' ), 'text', 'pk_test_…' ),
				'secret'         => array( esc_html__( 'Secret key', 'jws_streamvid' ), 'password', 'sk_test_…' ),
				'webhook_secret' => array( esc_html__( 'Webhook signing secret', 'jws_streamvid' ), 'password', 'whsec_…' ),
			);
			$modes  = array( 'test' => esc_html__( 'Test', 'jws_streamvid' ), 'live' => esc_html__( 'Live', 'jws_streamvid' ) );
			$events = 'checkout.session.completed, payment_intent.succeeded, invoice.paid, customer.subscription.updated, customer.subscription.deleted, charge.refunded';
			$help   = esc_html__( 'Add this endpoint in Stripe, then paste its signing secret above.', 'jws_streamvid' );
		} else {
			$fields = array(
				'client_id'  => array( esc_html__( 'Client ID', 'jws_streamvid' ), 'text', '' ),
				'secret'     => array( esc_html__( 'Secret', 'jws_streamvid' ), 'password', '' ),
				'webhook_id' => array( esc_html__( 'Webhook ID', 'jws_streamvid' ), 'text', '' ),
			);
			$modes  = array( 'sandbox' => esc_html__( 'Sandbox', 'jws_streamvid' ), 'live' => esc_html__( 'Live', 'jws_streamvid' ) );
			$events = 'PAYMENT.CAPTURE.COMPLETED, PAYMENT.CAPTURE.REFUNDED, BILLING.SUBSCRIPTION.ACTIVATED, BILLING.SUBSCRIPTION.CANCELLED, PAYMENT.SALE.COMPLETED';
			$help   = esc_html__( 'Add this endpoint in PayPal, then paste the ID PayPal gives it above.', 'jws_streamvid' );
		}

		$webhook = rest_url( 'jws-payment/v1/webhook/' . $gateway );
		?>
		<section class="jws-pay-gateway<?php echo $is_current ? ' is-active' : ''; ?>" id="jws-pay-gateway-<?php echo esc_attr( $gateway ); ?>" role="tabpanel" aria-labelledby="jws-pay-subtab-<?php echo esc_attr( $gateway ); ?>">

			<div class="jws-pay-gateway-head">
				<h2><?php echo esc_html( self::gateway_label( $gateway ) ); ?></h2>
				<?php if ( $ready ) : ?>
					<span class="jws-pay-badge is-on"><?php echo esc_html__( 'Ready', 'jws_streamvid' ); ?></span>
					<span class="jws-pay-badge <?php echo $live ? 'is-live-mode' : ''; ?>"><?php echo esc_html( $modes[ $config['mode'] ] ); ?></span>
				<?php elseif ( ! empty( $config['enabled'] ) ) : ?>
					<span class="jws-pay-badge is-warn"><?php echo esc_html__( 'Keys missing', 'jws_streamvid' ); ?></span>
				<?php else : ?>
					<span class="jws-pay-badge"><?php echo esc_html__( 'Off', 'jws_streamvid' ); ?></span>
				<?php endif; ?>
			</div>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php echo esc_html__( 'Enable', 'jws_streamvid' ); ?></th>
					<td>
						<label class="jws-pay-switch">
							<input type="checkbox" name="<?php echo esc_attr( $gateway ); ?>[enabled]" value="1" <?php checked( ! empty( $config['enabled'] ) ); ?> />
							<span>
								<?php
								printf(
									/* translators: %s: gateway name. */
									esc_html__( 'Take payments with %s', 'jws_streamvid' ),
									esc_html( self::gateway_label( $gateway ) )
								);
								?>
							</span>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Mode', 'jws_streamvid' ); ?></th>
					<td>
						<select name="<?php echo esc_attr( $gateway ); ?>[mode]">
							<?php foreach ( $modes as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $config['mode'] ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php if ( $live ) : ?>
							<p class="description jws-pay-live-warning"><?php echo esc_html__( 'Live mode — real cards, real money.', 'jws_streamvid' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>

				<?php foreach ( $fields as $key => $field ) : ?>
					<?php list( $label, $type, $placeholder ) = $field; ?>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( $gateway . '_' . $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td>
							<input
								type="<?php echo esc_attr( $type ); ?>"
								id="<?php echo esc_attr( $gateway . '_' . $key ); ?>"
								name="<?php echo esc_attr( $gateway ); ?>[<?php echo esc_attr( $key ); ?>]"
								value="<?php echo esc_attr( $config[ $key ] ); ?>"
								class="regular-text code"
								placeholder="<?php echo esc_attr( $placeholder ); ?>"
								<?php echo 'password' === $type ? 'autocomplete="new-password"' : ''; ?> />
							<?php if ( 'password' === $type ) : ?>
								<button type="button" class="button jws-pay-reveal" aria-label="<?php echo esc_attr__( 'Show or hide this value', 'jws_streamvid' ); ?>"><?php echo esc_html__( 'Show', 'jws_streamvid' ); ?></button>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>

				<?php if ( 'stripe' === $gateway ) : ?>
					<tr>
						<th scope="row"><label for="stripe_country"><?php echo esc_html__( 'Account country', 'jws_streamvid' ); ?></label></th>
						<td>
							<input type="text" id="stripe_country" name="stripe[country]" value="<?php echo esc_attr( $config['country'] ); ?>" class="small-text code" maxlength="2" />
							<p class="description"><?php echo esc_html__( 'Two-letter country of the Stripe account. Apple Pay and Google Pay will not open without it.', 'jws_streamvid' ); ?></p>
						</td>
					</tr>
				<?php endif; ?>

				<tr>
					<th scope="row"><?php echo esc_html__( 'Webhook', 'jws_streamvid' ); ?></th>
					<td>
						<div class="jws-pay-copy">
							<code><?php echo esc_html( $webhook ); ?></code>
							<button type="button" class="button jws-pay-copy-button" data-copy="<?php echo esc_attr( $webhook ); ?>"><?php echo esc_html__( 'Copy', 'jws_streamvid' ); ?></button>
						</div>
						<p class="description"><?php echo esc_html( $help ); ?></p>
						<?php if ( '' !== $events ) : ?>
							<details class="jws-pay-details">
								<summary><?php echo esc_html__( 'Events to subscribe to', 'jws_streamvid' ); ?></summary>
								<ul class="jws-pay-events">
									<?php foreach ( explode( ', ', $events ) as $event ) : ?>
										<li><code><?php echo esc_html( $event ); ?></code></li>
									<?php endforeach; ?>
								</ul>
							</details>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</section>
		<?php
	}

	/**
	 * The order log.
	 *
	 * Every purchase this system knows about, from any checkout — the ones it
	 * took itself and the ones Jws_Payment_Sync mirrored in from WooCommerce
	 * and PMPro. Read-only: an order records money that moved, and the way to
	 * reverse one is a refund at the gateway, which comes back through the
	 * webhook and updates the row itself.
	 *
	 * Its own GET form, outside the settings form: filtering is a navigation,
	 * not a save, and nesting the two would post the credentials every time
	 * someone changed a dropdown.
	 */
	private function panel_orders() {

		require_once __DIR__ . '/class-jws-payment-orders-table.php';

		$table = new Jws_Payment_Orders_Table();
		$table->prepare_items();

		$missing = wp_list_filter( Jws_Payment_Ledger::table_report(), array( 'exists' => false ) );
		?>
		<div class="jws-pay-panel jws-pay-orders" data-panel="orders">


			<?php if ( $missing ) : ?>
				<div class="notice notice-error inline">
					<p>
						<?php echo esc_html__( 'A payment table is missing, so this list cannot be trusted.', 'jws_streamvid' ); ?>
						<button type="submit" form="jws-pay-repair-form" class="button button-small"><?php echo esc_html__( 'Repair tables', 'jws_streamvid' ); ?></button>
					</p>
				</div>
			<?php endif; ?>

			<?php $table->views(); ?>

			<form method="get" class="jws-pay-orders-form">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />
				<input type="hidden" name="active_tab" value="orders" />
				<?php $table->search_box( esc_html__( 'Search orders', 'jws_streamvid' ), 'jws-payment-orders' ); ?>
				<?php $table->display(); ?>
			</form>

			<form method="post" id="jws-pay-repair-form" class="jws-pay-repair">
				<?php wp_nonce_field( self::REPAIR_NONCE, 'jws_payment_repair_nonce' ); ?>
				<input type="hidden" name="active_tab" value="orders" />
				<button type="submit" class="button-link"><?php echo esc_html__( 'Repair tables', 'jws_streamvid' ); ?></button>
			</form>
		</div>
		<?php
	}
}
