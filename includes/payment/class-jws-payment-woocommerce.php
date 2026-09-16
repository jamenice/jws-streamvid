<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * Hands the payment off to WooCommerce, and takes the answer back.
 *
 * The one method here that is not a gateway of its own: Stripe and PayPal are
 * asked for money directly, whereas this borrows whatever the store is already
 * set up to take — bank transfer, cash on delivery, a regional gateway with no
 * API this plugin would ever speak. The buyer goes to the WooCommerce checkout,
 * pays there, and WooCommerce tells us it happened.
 *
 * The ledger row is written before the buyer leaves (ajax_start() does it for
 * every method), so nothing is stashed in a transient the way a guest-facing
 * booking form has to: the row already holds the price, the currency and what
 * was bought, and its `source_ref` token is the only thing that travels. A WC
 * order carries that token in its own meta, which is what lets a status change
 * arriving minutes or days later — a bank transfer marked paid by hand — find
 * the row it settles.
 *
 * What the store sells for this is one hidden virtual product, created on
 * demand and priced at whatever the ledger row says at calculate-totals time.
 * The alternative — a real product per film, per coin pack, per membership —
 * would mean a catalogue kept in sync with three other systems forever.
 */
class Jws_Payment_Woocommerce {

	/** The query var carrying our order token to the WooCommerce checkout. */
	const REF_VAR = 'jws_wc_ref';

	/** Where the WC order remembers which ledger row it is paying. */
	const ORDER_META = '_jws_payment_ref';

	/** The option holding the id of the hidden placeholder product. */
	const PRODUCT_OPTION = '_jws_payment_wc_product';

	/** Cart item key holding the token, so WC persists it in its own session. */
	const CART_KEY = 'jws_payment_ref';

	/**
	 * The admin-ajax action the paid checkout lands on inside the modal.
	 *
	 * WooCommerce's own return URL would render a shop receipt inside a 700px
	 * iframe, with the buyer's real receipt stuck behind it. This lands there
	 * instead and tells the page that opened the modal to close it and move
	 * on — see bridge().
	 */
	const BRIDGE = 'jws_payment_wc_bridge';

	/* ---------------------------------------------------------------------- */
	/* Availability                                                            */
	/* ---------------------------------------------------------------------- */

	/** Whether WooCommerce is actually here to hand anything off to. */
	public static function is_available() {
		return class_exists( 'WooCommerce' ) && function_exists( 'WC' );
	}

	/**
	 * Whether this method can take one particular purchase.
	 *
	 * A plain WooCommerce order is a single charge — there is no WooCommerce
	 * Subscriptions on this site, and bank transfer or cash on delivery could
	 * not renew anything even if there were. So a plan that renews is not sold
	 * here as a plan that renews: it is sold as one paid term, which is what
	 * term_for() works out and grant_membership() then puts an end date on.
	 *
	 * The only thing refused is a recurring item whose billing cycle cannot be
	 * read. Without it there is no way to say how long the buyer has paid for,
	 * and the alternative — handing PMPro a membership with no end date — is
	 * one payment in exchange for permanent access.
	 */
	public static function supports( array $item ) {

		if ( empty( $item['recurring'] ) ) {
			return true;
		}

		return (bool) self::term_for( $item );
	}

	/**
	 * How long one WooCommerce payment buys, for a plan that normally renews.
	 *
	 * One billing cycle: the buyer pays this month's price, so they get this
	 * month. When it runs out the membership lapses and they buy it again —
	 * the manual version of what Stripe and PayPal do on their own.
	 *
	 * @return array|null array( cycle, period ), or null if the plan does not
	 *                    say what its cycle is.
	 */
	public static function term_for( array $item ) {

		$cycle  = isset( $item['cycle'] ) ? (int) $item['cycle'] : 0;
		$period = isset( $item['period'] ) ? strtolower( (string) $item['period'] ) : '';

		if ( $cycle < 1 || ! in_array( $period, array( 'day', 'week', 'month', 'year' ), true ) ) {
			return null;
		}

		return array( 'cycle' => $cycle, 'period' => $period );
	}

	/* ---------------------------------------------------------------------- */
	/* Hooks                                                                   */
	/* ---------------------------------------------------------------------- */

	/**
	 * Defers the real wiring to `plugins_loaded`.
	 *
	 * This class is constructed while the plugins are still being loaded, and
	 * `jws-streamvid` sorts before `woocommerce`, so at this moment the
	 * WooCommerce class does not exist yet and is_available() is false for a
	 * reason that stops being true a few milliseconds later. Asking the
	 * question here would answer “no” on every request and hook nothing, on a
	 * site where WooCommerce is running perfectly.
	 */
	public function register() {
		add_action( 'plugins_loaded', array( $this, 'register_hooks' ), 20 );
	}

	public function register_hooks() {

		if ( ! Jws_Payment_Settings::is_enabled() || ! self::is_available() ) {
			return;
		}

		/* Before WooCommerce's own template_redirect, which bounces an empty
		   cart back to the cart page before any filter of ours could speak. */
		add_action( 'template_redirect', array( $this, 'fill_cart' ), 1 );

		/* And the belt to that braces: if the product could not be added at
		   all, at least do not bounce the buyer to an empty cart page. */
		add_filter( 'woocommerce_checkout_redirect_empty_cart', array( $this, 'allow_empty_cart' ) );

		/* Our line is priced from the ledger, not from the product. */
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'price_line' ), 20 );
		add_filter( 'woocommerce_cart_item_name', array( $this, 'name_line' ), 10, 3 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'restore_line' ), 10, 2 );

		/* A hidden product with a placeholder price can otherwise be judged
		   unpurchasable, which fails the checkout with nothing to read. */
		add_filter( 'woocommerce_is_purchasable', array( $this, 'is_purchasable' ), 10, 2 );

		/* The token moves from the cart line onto the order, which is what
		   outlives the session. */
		add_action( 'woocommerce_checkout_create_order', array( $this, 'stamp_order' ), 10, 2 );

		/* The billing column is hidden in the modal, so nothing in it may be
		   required — see simplify_fields(). */
		add_filter( 'woocommerce_checkout_fields', array( $this, 'simplify_fields' ) );

		/*
		 * Every way a WooCommerce order becomes paid. They overlap by design —
		 * a gateway firing payment_complete also moves the status — and
		 * mark_paid() only settles a row still pending, so the second and third
		 * arrival do nothing.
		 */
		add_action( 'woocommerce_payment_complete', array( $this, 'settle' ) );
		add_action( 'woocommerce_order_status_processing', array( $this, 'settle' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'settle' ) );

		add_action( 'woocommerce_order_status_cancelled', array( $this, 'abandon' ) );
		add_action( 'woocommerce_order_status_failed', array( $this, 'abandon' ) );

		/* Back to our own receipt rather than WooCommerce's order-received. */
		add_filter( 'woocommerce_get_return_url', array( $this, 'return_url' ), 10, 2 );

		/* The checkout is shown inside a modal iframe on our own page, which
		   means it has to be allowed to be framed, and has to stop drawing the
		   site's header and footer around itself while it is. */
		add_filter( 'wp_headers', array( $this, 'allow_framing' ), 20 );
		add_action( 'wp_head', array( $this, 'frame_styles' ) );

		add_action( 'wp_ajax_' . self::BRIDGE, array( $this, 'bridge' ) );
		add_action( 'wp_ajax_nopriv_' . self::BRIDGE, array( $this, 'bridge' ) );

		/* The placeholder must never turn up in someone's ordinary shopping. */
		add_action( 'template_redirect', array( $this, 'clear_stale_line' ), 20 );
	}

	/* ---------------------------------------------------------------------- */
	/* Starting                                                                */
	/* ---------------------------------------------------------------------- */

	/**
	 * Where to send the buyer to pay this order through WooCommerce.
	 *
	 * Mirrors Jws_Payment_Stripe::checkout_session() and
	 * Jws_Payment_Paypal::create_order(): a URL, or a WP_Error that
	 * send_redirect_or_fail() turns into a message and a failed row.
	 *
	 * @param object $order Ledger row.
	 * @param array  $item  What is being bought.
	 * @return string|WP_Error
	 */
	public static function checkout_url( $order, array $item ) {

		if ( ! self::is_available() ) {
			return new WP_Error( 'jws_wc_missing', esc_html__( 'WooCommerce is not available right now.', 'jws_streamvid' ) );
		}

		if ( ! self::supports( $item ) ) {
			return new WP_Error(
				'jws_wc_recurring',
				esc_html__( 'This plan does not say how long one payment covers, so it cannot be paid through WooCommerce. Please choose another payment method.', 'jws_streamvid' )
			);
		}

		if ( ! self::product_id() ) {
			return new WP_Error( 'jws_wc_product', esc_html__( 'The store could not prepare this payment. Please choose another payment method.', 'jws_streamvid' ) );
		}

		/*
		 * WooCommerce charges in the store's currency and takes no instruction
		 * from us about it, so an order priced in anything else would be
		 * handed over as a bare number and charged in the wrong money — the
		 * buyer sees €9 on the checkout and £9 on their statement. Currencies
		 * agree on almost every site, because Jws_Payment_Settings::currency()
		 * falls back to the store's own when the box is left blank; this is
		 * for the site that filled it in with something else.
		 */
		if ( strtoupper( (string) $order->currency ) !== strtoupper( get_woocommerce_currency() ) ) {
			return new WP_Error(
				'jws_wc_currency',
				esc_html__( 'This purchase is priced in a different currency to the store, so it cannot be paid through WooCommerce. Please choose another payment method.', 'jws_streamvid' )
			);
		}

		$checkout = wc_get_checkout_url();

		if ( ! $checkout ) {
			return new WP_Error( 'jws_wc_no_checkout', esc_html__( 'The store has no checkout page set up.', 'jws_streamvid' ) );
		}

		/*
		 * app carried through by hand rather than by with_app()'s cookie
		 * reading: this runs in an admin-ajax request, where the WebView's
		 * cookie is present but the query var that set it is not, and the
		 * buyer is about to spend several page loads inside WooCommerce's own
		 * templates — every one of which would otherwise grow the site's
		 * header and footer back around it.
		 */
		return Jws_Payment_Checkout::with_app(
			add_query_arg( array( self::REF_VAR => $order->source_ref ), $checkout ),
			Jws_Payment_Checkout::order_is_app( $order )
		);
	}

	/* ---------------------------------------------------------------------- */
	/* The placeholder product                                                 */
	/* ---------------------------------------------------------------------- */

	/**
	 * The hidden product every hand-off is sold through, made on first use.
	 *
	 * Published rather than private, because a private product is not
	 * purchasable; hidden from the catalogue so it never appears in the shop,
	 * search or a related-products row; virtual so WooCommerce asks for no
	 * shipping; sold individually so a stale line cannot become two.
	 */
	public static function product_id() {

		$id      = (int) get_option( self::PRODUCT_OPTION, 0 );
		$product = $id ? wc_get_product( $id ) : null;

		if ( $product ) {

			/*
			 * Found, but not necessarily usable. wc_get_product() answers for a
			 * product in the trash exactly as it does for a live one, and a
			 * trashed product is dropped straight back out of the cart by
			 * WC_Cart::check_cart_item_validity() — which would leave the buyer
			 * staring at an empty checkout with nothing to explain it, and no
			 * amount of retrying would fix it.
			 *
			 * So anything that is not published is put back, rather than
			 * answered with. Restoring the same product keeps every past order
			 * pointing at the row it was actually sold through; making a second
			 * one would quietly strand them.
			 */
			if ( 'publish' !== $product->get_status() ) {
				$product->set_status( 'publish' );
				$product->set_catalog_visibility( 'hidden' );
				$product->save();
			}

			return $id;
		}

		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			return 0;
		}

		$product = new WC_Product_Simple();

		$product->set_name( esc_html__( 'Payment', 'jws_streamvid' ) );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->set_downloadable( false );

		/* Non-zero only so is_purchasable() sees a price at all; price_line()
		   replaces it with the real one on every cart calculation. */
		$product->set_price( 0.01 );
		$product->set_regular_price( 0.01 );
		$product->set_tax_status( 'none' );
		$product->set_sold_individually( true );

		$id = (int) $product->save();

		if ( $id ) {
			update_option( self::PRODUCT_OPTION, $id, false );
		}

		return $id;
	}

	/* ---------------------------------------------------------------------- */
	/* The cart                                                                */
	/* ---------------------------------------------------------------------- */

	/** The token this request is carrying, if it is one of ours. */
	private static function requested_ref() {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- an unguessable token, checked against the ledger below.
		return isset( $_GET[ self::REF_VAR ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::REF_VAR ] ) ) : '';
	}

	/**
	 * The token for the checkout in progress, URL or not.
	 *
	 * requested_ref() only answers on a request that carries the query var,
	 * which is the iframe's own page load and nothing else. WooCommerce submits
	 * the checkout form to `/?wc-ajax=checkout` — a different URL, with no
	 * query var on it — so anything that has to hold true while the order is
	 * being validated has to find the token somewhere that outlives the URL.
	 *
	 * The cart line is that somewhere: restore_line() puts the token back on it
	 * from WooCommerce's own session on every request, including that one.
	 */
	private static function active_ref() {

		$ref = self::requested_ref();

		if ( $ref ) {
			return $ref;
		}

		if ( ! self::is_available() || ! WC()->cart ) {
			return '';
		}

		foreach ( WC()->cart->get_cart() as $line ) {
			if ( ! empty( $line[ self::CART_KEY ] ) ) {
				return (string) $line[ self::CART_KEY ];
			}
		}

		return '';
	}

	/**
	 * The pending ledger row a token belongs to, if it is this buyer's.
	 *
	 * The token is unguessable, but it also travels in a URL that a buyer can
	 * bookmark, share or open again after paying — so ownership and status are
	 * both checked rather than assumed.
	 */
	private static function pending_order( $ref ) {

		if ( ! $ref ) {
			return null;
		}

		$order = Jws_Payment_Checkout::find_by_token( $ref );

		if ( ! $order || Jws_Payment_Orders::STATUS_PENDING !== $order->status ) {
			return null;
		}

		if ( (int) $order->user_id !== get_current_user_id() ) {
			return null;
		}

		return $order;
	}

	/**
	 * Puts the placeholder in the cart so the WooCommerce checkout has
	 * something to sell.
	 *
	 * Runs only on a checkout URL carrying our token, and empties the cart
	 * first: whatever else the buyer had in there is not what they came here to
	 * pay for, and WooCommerce would otherwise charge them for both.
	 */
	public function fill_cart() {

		if ( ! self::is_available() || ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}

		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}

		$ref = self::requested_ref();

		if ( ! $ref || ! WC()->cart ) {
			return;
		}

		$order = self::pending_order( $ref );

		if ( ! $order ) {
			return;
		}

		/* Already set up — a page refresh, or the checkout re-rendering over
		   AJAX after a payment method changed. */
		foreach ( WC()->cart->get_cart() as $line ) {
			if ( ! empty( $line[ self::CART_KEY ] ) && $line[ self::CART_KEY ] === $ref ) {
				return;
			}
		}

		$product_id = self::product_id();

		if ( ! $product_id ) {
			return;
		}

		WC()->cart->empty_cart( true );
		WC()->cart->add_to_cart( $product_id, 1, 0, array(), array( self::CART_KEY => $ref ) );
	}

	/** Never bounce one of our checkouts to the cart page for being empty. */
	public function allow_empty_cart( $redirect ) {
		return self::requested_ref() ? false : $redirect;
	}

	/**
	 * Prices the placeholder from the ledger row, every time totals are run.
	 *
	 * Read from the row rather than from anything the browser sent, so the
	 * amount charged is the amount the checkout wrote down when the buyer
	 * pressed the button.
	 */
	public function price_line( $cart ) {

		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		foreach ( $cart->get_cart() as $line ) {

			if ( empty( $line[ self::CART_KEY ] ) ) {
				continue;
			}

			$order = Jws_Payment_Checkout::find_by_token( $line[ self::CART_KEY ] );

			if ( $order ) {
				$line['data']->set_price( (float) $order->amount );
			}
		}
	}

	/** Shows what is actually being bought instead of "Payment". */
	public function name_line( $name, $line, $line_key ) {

		if ( empty( $line[ self::CART_KEY ] ) ) {
			return $name;
		}

		$order = Jws_Payment_Checkout::find_by_token( $line[ self::CART_KEY ] );

		return $order && $order->item_label ? esc_html( $order->item_label ) : $name;
	}

	/** Keeps the token on the line when WooCommerce rebuilds the cart. */
	public function restore_line( $line, $values ) {

		if ( ! empty( $values[ self::CART_KEY ] ) ) {
			$line[ self::CART_KEY ] = $values[ self::CART_KEY ];
		}

		return $line;
	}

	/**
	 * The placeholder is purchasable; nothing else is affected.
	 *
	 * Scoped to a published product on purpose. Vouching for it whatever its
	 * status would paper over a trashed placeholder just long enough for the
	 * cart to accept it and WooCommerce to throw it out again a moment later,
	 * which turns a clear failure into an unexplained empty checkout.
	 * product_id() is what repairs that; this only speaks for a live product
	 * whose one-cent placeholder price might otherwise be judged too low.
	 */
	public function is_purchasable( $purchasable, $product ) {

		$ours = (int) get_option( self::PRODUCT_OPTION, 0 );

		if ( ! $ours || (int) $product->get_id() !== $ours ) {
			return $purchasable;
		}

		return 'publish' === $product->get_status();
	}

	/**
	 * Takes the placeholder back out of the cart anywhere but our checkout.
	 *
	 * A buyer who abandons the WooCommerce checkout and goes shopping would
	 * otherwise find a mystery "Payment" line in their basket, priced at
	 * something they never chose.
	 */
	public function clear_stale_line() {

		if ( self::requested_ref() || ! self::is_available() || ! WC()->cart ) {
			return;
		}

		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}

		foreach ( WC()->cart->get_cart() as $key => $line ) {
			if ( ! empty( $line[ self::CART_KEY ] ) ) {
				WC()->cart->remove_cart_item( $key );
			}
		}
	}

	/* ---------------------------------------------------------------------- */
	/* The order                                                               */
	/* ---------------------------------------------------------------------- */

	/**
	 * Copies the token from the cart line onto the WooCommerce order.
	 *
	 * The cart lives in a session that ends; the order does not. Everything
	 * after this point — a gateway callback, an admin marking a bank transfer
	 * paid next week — reads the token from here.
	 */
	public function stamp_order( $wc_order, $data ) {

		$ref = '';

		if ( WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $line ) {
				if ( ! empty( $line[ self::CART_KEY ] ) ) {
					$ref = $line[ self::CART_KEY ];
					break;
				}
			}
		}

		if ( ! $ref ) {
			return;
		}

		$order = self::pending_order( $ref );

		if ( ! $order ) {
			return;
		}

		$wc_order->update_meta_data( self::ORDER_META, $ref );

		/*
		 * Only where the checkout left a blank. A buyer who does have a billing
		 * profile in the shop already — or who somehow typed something into a
		 * column this flow hides — keeps what they entered.
		 */
		$account = self::billing_from_account( $order->user_id );

		if ( $account ) {
			if ( ! $wc_order->get_billing_first_name() && $account['first_name'] ) {
				$wc_order->set_billing_first_name( $account['first_name'] );
			}

			if ( ! $wc_order->get_billing_last_name() && $account['last_name'] ) {
				$wc_order->set_billing_last_name( $account['last_name'] );
			}

			if ( ! $wc_order->get_billing_email() && $account['email'] ) {
				$wc_order->set_billing_email( $account['email'] );
			}
		}

		/* So an admin looking at the WooCommerce order can find the row, and
		   so a webhook that only knows the WC id can too. */
		Jws_Payment_Orders::attach_ref( $order->id, (string) $wc_order->get_id() );
	}

	/**
	 * Stops the checkout demanding a billing address nobody can see.
	 *
	 * `#customer_details` is hidden in the modal: the buyer is a signed-in
	 * member buying something virtual, so an address column is a form asking
	 * for a delivery address for a film. Hiding it in CSS alone would be the
	 * worst of both worlds, though — WooCommerce would still refuse the order
	 * over a first name that is required, empty, and invisible, and the buyer
	 * would be left with an error naming a field that is not on their screen.
	 *
	 * So nothing in there is required any more. What WooCommerce genuinely
	 * needs to produce an order — a name and an email to send the receipt to —
	 * is taken from the WordPress account instead, in stamp_order().
	 */
	public function simplify_fields( $fields ) {

		if ( is_admin() && ! wp_doing_ajax() ) {
			return $fields;
		}

		/*
		 * active_ref(), not requested_ref(): this filter has to say the same
		 * thing when the form is rendered and when it is validated, and those
		 * are two different URLs. Reading the query var alone made the fields
		 * optional on screen and required again on submit — so the buyer was
		 * told a street address was missing, with no street address field
		 * anywhere on the page to put one in.
		 */
		if ( ! self::active_ref() ) {
			return $fields;
		}

		if ( ! empty( $fields['billing'] ) ) {
			foreach ( $fields['billing'] as $key => $field ) {
				$fields['billing'][ $key ]['required'] = false;
			}
		}

		if ( ! empty( $fields['shipping'] ) ) {
			foreach ( $fields['shipping'] as $key => $field ) {
				$fields['shipping'][ $key ]['required'] = false;
			}
		}

		/* A note to the shop about an order the shop never sees. */
		unset( $fields['order']['order_comments'] );

		return $fields;
	}

	/**
	 * The buyer's name and email, from their WordPress account.
	 *
	 * WooCommerce fills these from the customer's billing profile, which on
	 * this site is usually empty — members sign up to watch films, not to be
	 * shipped anything, so nobody has ever filled in a billing address. That
	 * would leave the order, and the receipt email, addressed to nobody.
	 */
	private static function billing_from_account( $user_id ) {

		$user = get_userdata( (int) $user_id );

		if ( ! $user ) {
			return array();
		}

		$first = $user->first_name;
		$last  = $user->last_name;

		/* No real name on the account: the display name is the only thing
		   left that a person would recognise as themselves. */
		if ( ! $first && ! $last ) {
			$first = $user->display_name ? $user->display_name : $user->user_login;
		}

		return array(
			'first_name' => $first,
			'last_name'  => $last,
			'email'      => $user->user_email,
		);
	}

	/** The ledger row a WooCommerce order is paying, if it is one of ours. */
	private static function order_for( $wc_order_id ) {

		$wc_order = wc_get_order( $wc_order_id );

		if ( ! $wc_order ) {
			return null;
		}

		$ref = $wc_order->get_meta( self::ORDER_META );

		return $ref ? Jws_Payment_Checkout::find_by_token( $ref ) : null;
	}

	/**
	 * WooCommerce says it has been paid, so grant what was bought.
	 *
	 * mark_paid() is what fulfils the order and it only settles a row that is
	 * still pending, so the three hooks pointed here — and a status that flaps
	 * between processing and completed — settle it once between them.
	 */
	public function settle( $wc_order_id ) {

		$order = self::order_for( $wc_order_id );

		if ( ! $order ) {
			return;
		}

		Jws_Payment_Orders::mark_paid( $order->id );
	}

	/** Cancelled or failed in WooCommerce: the row should not stay pending. */
	public function abandon( $wc_order_id ) {

		$order = self::order_for( $wc_order_id );

		if ( ! $order || Jws_Payment_Orders::STATUS_PENDING !== $order->status ) {
			return;
		}

		Jws_Payment_Orders::mark_failed( $order->id );
	}

	/* ---------------------------------------------------------------------- */
	/* The modal frame                                                         */
	/* ---------------------------------------------------------------------- */

	/**
	 * Lets the WooCommerce checkout be framed, but only ours.
	 *
	 * Filtered rather than checked on the `wp` hook, because headers are
	 * already sent by the time that fires. The exception is scoped to a request
	 * carrying a live token: every other page on the site keeps whatever
	 * clickjacking protection it had.
	 *
	 * A security plugin that writes the header with header() instead of through
	 * this filter cannot be undone from here — if the modal comes up blank,
	 * that is the first thing to look at.
	 */
	public function allow_framing( $headers ) {

		if ( self::requested_ref() ) {
			unset( $headers['X-Frame-Options'] );
		}

		return $headers;
	}

	/**
	 * Strips the site's chrome off the checkout while it is in the modal.
	 *
	 * The selectors are the theme's own app-mode list (see css_inline.php),
	 * reused rather than re-derived so the two stay in step. What is not reused
	 * is `?app=1` itself, which would have been the one-line version of this:
	 * that sets a session cookie the whole site then reads, so an iframe asking
	 * for it would strip the header and footer off the parent window too, and
	 * keep doing it until the browser closed.
	 */
	public function frame_styles() {

		if ( ! self::requested_ref() || ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}

		?>
		<style id="jws-payment-wc-frame">
			.jws_header, .jws_footer, .backToTop, #svcChatWidget,
			#jws-rtl-toggle-gear, .jws-toolbar-wap, .jws-title-bar-wrap,
			.woocommerce-breadcrumb, .woocommerce-form-coupon-toggle,
			.checkout_coupon { display: none !important; }

			/* The billing column, and everything that hangs off it: the
			   "returning customer?" login prompt, the create-an-account box,
			   the express-checkout separator left behind once its buttons are
			   gone. The buyer is already signed in and buying something
			   virtual, so none of it has anything to ask them. Nothing in here
			   is required any more either — see simplify_fields(); hiding a
			   required field is how a checkout fails with an error pointing at
			   a box that is not on screen. */
			#customer_details,
			.woocommerce-form-login-toggle,
			.woocommerce-form-login,
			.woocommerce-account-fields,
			#wcpay-express-checkout-button-separator { display: none !important; }

			/* #order_review_heading reads "Your order" directly under a modal
			   header that already says what this is. */
			#order_review_heading { display: none !important; }

			/* No colours are set here on purpose. The checkout inside the
			   frame is the site's own theme, and the buyer should recognise
			   it — painting a light background under a dark theme's light
			   text is how this ends up unreadable. */
			.site-main, .entry-content, .page-content,
			.woocommerce, .woocommerce-page { padding: 0 !important; margin: 0 !important; }

			form.woocommerce-checkout, form#order_review { padding: 16px 14px 32px; margin: 0 auto; }

			/*
			 * The theme's own checkout template (woocommerce/checkout/
			 * form-checkout.php) splits the page into a 60% column holding
			 * #customer_details and a 40% one holding the order review and the
			 * payment box — it does not use WooCommerce's stock `col2-set` at
			 * all. Hiding the billing column alone would leave its 60% wrapper
			 * standing empty and the part that actually takes the money
			 * squeezed into the remaining 40% of a 720px modal, so the wrapper
			 * goes too and what is left takes the full width.
			 *
			 * Scoped to the checkout form: these grid classes are the theme's
			 * general-purpose ones and are used all over the site.
			 */
			form.woocommerce-checkout .col-lg-60 { display: none !important; }
			.ppcp-messages { display: none !important; }
			div[data-elementor-type="wp-page"] > .elementor-element {
				padding: 0 !important;
			}
			form.woocommerce-checkout .col-lg-40 {
				width: 100% !important;
				max-width: 100% !important;
				flex: 0 0 100% !important;
			}
		</style>
		<?php
	}

	/**
	 * The page WooCommerce returns to once the order is placed.
	 *
	 * It is loaded inside the modal iframe, so it cannot simply be the receipt:
	 * the receipt would render in a 700px box with the shop still around it.
	 * Instead it tells the opener to close the modal and go to the receipt
	 * itself — and, if it turns out not to be framed at all (a gateway that
	 * broke out to its own page, someone opening the link directly), it just
	 * goes there under its own steam.
	 */
	public function bridge() {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- an unguessable token, and this only reads.
		$ref   = isset( $_GET['ref'] ) ? sanitize_text_field( wp_unslash( $_GET['ref'] ) ) : '';
		$order = $ref ? Jws_Payment_Checkout::find_by_token( $ref ) : null;

		$target = $order
			? Jws_Payment_Checkout::with_app(
				add_query_arg(
					array( 'jws_payment' => 'done', 'order' => $ref ),
					Jws_Payment_Checkout::page_url()
				),
				Jws_Payment_Checkout::order_is_app( $order )
			)
			: home_url( '/' );

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );

		?>
		<!doctype html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>" />
			<meta name="viewport" content="width=device-width, initial-scale=1" />
			<title><?php echo esc_html__( 'Payment complete', 'jws_streamvid' ); ?></title>
			<style>
				body { margin: 0; height: 100vh; display: flex; align-items: center; justify-content: center;
				       font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
				       background: #fff; color: #111; gap: 10px; font-size: 15px; }
				svg { flex: 0 0 auto; }
			</style>
		</head>
		<body>
			<svg width="22" height="22" viewBox="0 0 22 22" fill="none" aria-hidden="true">
				<circle cx="11" cy="11" r="10" fill="#16a34a" />
				<path d="M6 11.5l3.5 3.5 6.5-7" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
			</svg>
			<span><?php echo esc_html__( 'Payment complete. One moment…', 'jws_streamvid' ); ?></span>

			<script>
				(function () {
					var target = <?php echo wp_json_encode( $target ); ?>;
					var origin = <?php echo wp_json_encode( home_url( '/' ) ); ?>;

					/*
					 * Framed: hand it to the opener, which owns the modal and
					 * the page behind it. Not framed: nobody is listening, so
					 * go there directly.
					 */
					if (window.parent && window.parent !== window) {
						window.parent.postMessage({ type: 'jws_payment_wc_paid', redirect: target }, origin);

						/* The opener normally moves the whole window within a
						   tick. If it does not — an older tab still open on a
						   different page, a listener that never bound — the
						   buyer is left reading "one moment" forever. */
						setTimeout(function () { window.location.replace(target); }, 4000);
						return;
					}

					window.location.replace(target);
				}());
			</script>
		</body>
		</html>
		<?php

		exit;
	}

	/**
	 * Sends the buyer to the bridge instead of WooCommerce's receipt.
	 *
	 * The order-received page is a shop's receipt — it talks about a product
	 * called "Payment" and says nothing about the film now unlocked or the
	 * coins now in the account. render_result() is the page that does, and it
	 * is the one every other payment method comes back to. It cannot be
	 * returned to directly, though, because this lands inside the modal
	 * iframe; bridge() is the thing that gets the buyer out of it first.
	 */
	public function return_url( $url, $wc_order ) {

		if ( ! $wc_order ) {
			return $url;
		}

		$ref = $wc_order->get_meta( self::ORDER_META );

		if ( ! $ref ) {
			return $url;
		}

		return add_query_arg(
			array( 'action' => self::BRIDGE, 'ref' => rawurlencode( $ref ) ),
			admin_url( 'admin-ajax.php' )
		);
	}
}
