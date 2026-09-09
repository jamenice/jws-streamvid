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
			 * Gateway objects already provisioned for recurring plans, as
			 * slot => array( stripe_price, stripe_coupon, paypal_product,
			 * paypal_plan ). Keyed by a hash of the money rather than by the
			 * PMPro level id: repricing a level makes a new Price, so everyone
			 * already subscribed keeps paying what they agreed to. Written by
			 * the gateway layer, never by this form.
			 */
			'gateway_refs'  => array(),
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
		foreach ( array( 'stripe', 'paypal' ) as $gateway ) {
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

		if ( $set ) {
			return strtoupper( $set );
		}

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
			),
			'apple_pay'  => array(
				'label'   => esc_html__( 'Apple Pay', 'jws_streamvid' ),
				'note'    => esc_html__( "The browser's own payment sheet. Safari only, and only on a device that has a card set up.", 'jws_streamvid' ),
				'gateway' => 'stripe',
				'flow'    => 'wallet',
			),
			'google_pay' => array(
				'label'   => esc_html__( 'Google Pay', 'jws_streamvid' ),
				'note'    => esc_html__( "The browser's own payment sheet. Chrome, on a device that has a card set up.", 'jws_streamvid' ),
				'gateway' => 'stripe',
				'flow'    => 'wallet',
			),
			'paypal'     => array(
				'label'   => esc_html__( 'PayPal', 'jws_streamvid' ),
				'note'    => esc_html__( 'Sends the buyer to PayPal and back.', 'jws_streamvid' ),
				'gateway' => 'paypal',
				'flow'    => 'redirect',
			),
			'quick_pay'  => array(
				'label'   => esc_html__( 'Stripe', 'jws_streamvid' ),
				'note'    => esc_html__( "Sends the buyer to Stripe's own checkout page instead of taking the card here.", 'jws_streamvid' ),
				'gateway' => 'stripe',
				'flow'    => 'redirect',
			),
		);
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

		$out['currency'] = isset( $raw['currency'] )
			? strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', $raw['currency'] ), 0, 3 ) )
			: '';

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
		if ( $out['stripe']['secret'] !== $stored['stripe']['secret'] || $out['paypal']['client_id'] !== $stored['paypal']['client_id'] ) {
			$out['gateway_refs'] = array();
		}

		return $out;
	}

	/* ---------------------------------------------------------------------- */
	/* Rendering                                                               */
	/* ---------------------------------------------------------------------- */

	/** The tabs, in the order someone setting this up would work through them. */
	private static function tabs() {

		return array(
			'general'  => esc_html__( 'General', 'jws_streamvid' ),
			'methods'  => esc_html__( 'Payment methods', 'jws_streamvid' ),
			'stripe'   => 'Stripe',
			'paypal'   => 'PayPal',
			'orders'   => esc_html__( 'Orders', 'jws_streamvid' ),
		);
	}

	/**
	 * A gateway's name as its own brand writes it.
	 *
	 * ucfirst() on the slug gets "Paypal", which is wrong everywhere it is
	 * shown and looks like a typo to anyone who has seen a PayPal invoice.
	 */
	public static function gateway_label( $gateway ) {

		$names = array(
			'stripe' => 'Stripe',
			'paypal' => 'PayPal',
		);

		return isset( $names[ $gateway ] ) ? $names[ $gateway ] : ucfirst( $gateway );
	}

	/** Loads this screen's own styles and script, and nowhere else. */
	public function enqueue_assets( $hook_suffix ) {

		if ( false === strpos( (string) $hook_suffix, self::PAGE ) ) {
			return;
		}

		$base    = plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . 'admin/';
		$version = defined( 'JWS_STREAMVID_VERSION' ) ? JWS_STREAMVID_VERSION : '1.0.0';

		wp_enqueue_style( 'jws-payment-admin', $base . 'css/jws-payment-admin.css', array(), $version );
		wp_enqueue_script( 'jws-payment-admin', $base . 'js/jws-payment-admin.js', array(), $version, true );

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
		$active = 'general';

		if ( isset( $_POST['active_tab'] ) ) {
			$active = sanitize_key( wp_unslash( $_POST['active_tab'] ) );
		} elseif ( isset( $_GET['active_tab'] ) ) {
			$active = sanitize_key( wp_unslash( $_GET['active_tab'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification

		$active = isset( self::tabs()[ $active ] ) ? $active : 'general';
		?>
		<div class="wrap jws-pay" data-active-tab="<?php echo esc_attr( $active ); ?>">

			<h1><?php echo esc_html__( 'Payment System', 'jws_streamvid' ); ?></h1>

			<p class="jws-pay-lede">
				<?php echo esc_html__( 'One checkout for membership, buy/rent and coins, taking payment with its own Stripe and PayPal keys. Switching it on replaces the WooCommerce cart and the Paid Memberships Pro checkout page — nothing else moves: PMPro keeps owning membership levels and access, buy/rent keeps unlocking the same way, coins keep landing in the same wallet.', 'jws_streamvid' ); ?>
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
				<?php $this->panel_gateway( 'stripe', $s ); ?>
				<?php $this->panel_gateway( 'paypal', $s ); ?>

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
						<input type="text" id="currency" name="currency" value="<?php echo esc_attr( $s['currency'] ); ?>" class="small-text code" maxlength="3" placeholder="<?php echo esc_attr( self::currency() ); ?>" />
						<p class="description">
							<?php
							printf(
								/* translators: %s: currency code the store already uses. */
								esc_html__( 'Leave empty to follow the store, which is %s right now. Set it only if this checkout should bill in something else.', 'jws_streamvid' ),
								'<code>' . esc_html( self::currency() ) . '</code>'
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
					?>
					<label class="jws-pay-method <?php echo $ticked && $ready ? 'is-live' : ''; ?>">
						<input type="checkbox" name="methods[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $ticked ); ?> />
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
	 * One gateway's credentials.
	 *
	 * Stripe and PayPal ask for different things but in the same order — turn
	 * it on, pick the mode, paste the keys, wire the webhook — so they share a
	 * renderer rather than drifting into two shapes of the same form.
	 */
	private function panel_gateway( $gateway, $s ) {

		$config = $s[ $gateway ];
		$ready  = self::gateway_ready( $gateway );
		$live   = 'live' === $config['mode'];

		$fields = 'stripe' === $gateway
			? array(
				'publishable'    => array( esc_html__( 'Publishable key', 'jws_streamvid' ), 'text', 'pk_test_…' ),
				'secret'         => array( esc_html__( 'Secret key', 'jws_streamvid' ), 'password', 'sk_test_…' ),
				'webhook_secret' => array( esc_html__( 'Webhook signing secret', 'jws_streamvid' ), 'password', 'whsec_…' ),
			)
			: array(
				'client_id'  => array( esc_html__( 'Client ID', 'jws_streamvid' ), 'text', '' ),
				'secret'     => array( esc_html__( 'Secret', 'jws_streamvid' ), 'password', '' ),
				'webhook_id' => array( esc_html__( 'Webhook ID', 'jws_streamvid' ), 'text', '' ),
			);

		$modes = 'stripe' === $gateway
			? array( 'test' => esc_html__( 'Test', 'jws_streamvid' ), 'live' => esc_html__( 'Live', 'jws_streamvid' ) )
			: array( 'sandbox' => esc_html__( 'Sandbox', 'jws_streamvid' ), 'live' => esc_html__( 'Live', 'jws_streamvid' ) );

		$events = 'stripe' === $gateway
			? 'checkout.session.completed, payment_intent.succeeded, invoice.paid, customer.subscription.updated, customer.subscription.deleted, charge.refunded'
			: 'PAYMENT.CAPTURE.COMPLETED, PAYMENT.CAPTURE.REFUNDED, BILLING.SUBSCRIPTION.ACTIVATED, BILLING.SUBSCRIPTION.CANCELLED, PAYMENT.SALE.COMPLETED';

		$webhook = rest_url( 'jws-payment/v1/webhook/' . $gateway );
		?>
		<div class="jws-pay-panel" data-panel="<?php echo esc_attr( $gateway ); ?>">

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
						<p class="description">
							<?php
							echo 'stripe' === $gateway
								? esc_html__( 'Add this endpoint in Stripe, then paste its signing secret above.', 'jws_streamvid' )
								: esc_html__( 'Add this endpoint in PayPal, then paste the ID PayPal gives it above.', 'jws_streamvid' );
							?>
						</p>
						<details class="jws-pay-details">
							<summary><?php echo esc_html__( 'Events to subscribe to', 'jws_streamvid' ); ?></summary>
							<ul class="jws-pay-events">
								<?php foreach ( explode( ', ', $events ) as $event ) : ?>
									<li><code><?php echo esc_html( $event ); ?></code></li>
								<?php endforeach; ?>
							</ul>
						</details>
					</td>
				</tr>
			</table>
		</div>
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
				<span class="description">
					<?php
					printf(
						/* translators: 1: orders table name, 2: subscriptions table name. */
						esc_html__( 'Tables: %1$s and %2$s.', 'jws_streamvid' ),
						'<code>' . esc_html( Jws_Payment_Ledger::table() ) . '</code>',
						'<code>' . esc_html( Jws_Payment_Ledger::table_subscriptions() ) . '</code>'
					);
					?>
					<button type="submit" class="button-link"><?php echo esc_html__( 'Repair tables', 'jws_streamvid' ); ?></button>
				</span>
			</form>
		</div>
		<?php
	}
}
