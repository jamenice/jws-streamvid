<?php

/**
 * Selling coins, and showing a viewer what they have.
 *
 * Coins are sold as ordinary WooCommerce products: any product carrying a
 * `_jws_drama_coins` amount credits that many coins when its order is paid.
 * Reusing Woo means tax, currency, coupons, refunds and every payment gateway
 * already work, and the wallet only has to care about the moment money lands.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/drama
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Drama_Coins {

	/** Coins granted per unit of this product. */
	const PRODUCT_META = '_jws_drama_coins';

	/** Marks an order as already credited, so a status flap cannot pay twice. */
	const ORDER_META = '_jws_drama_coins_credited';

	/**
	 * Coins a single cart line is worth.
	 *
	 * A package sells through one carrier product with the row's own price and
	 * coin amount on the cart item, the way pay-per-view sells every rental
	 * through one ticket product — so the shelf grows by a row in the settings
	 * rather than by another product in the catalogue. The same key is copied
	 * onto the order item at checkout, which is what order_coins() reads back.
	 */
	const ITEM_COINS = '_jws_drama_item_coins';

	/** The package's price, kept beside the coins for the totals filter. */
	const ITEM_PRICE = '_jws_drama_item_price';

	public function register() {

		/*
		 * Registered unconditionally, NOT behind class_exists( 'WooCommerce' ).
		 * This module boots while jws-streamvid is being loaded, and plugins load
		 * alphabetically — woocommerce comes after, so the class does not exist
		 * yet and every one of these hooks would silently never be attached.
		 * An action WooCommerce never fires costs nothing.
		 */
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'product_field' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_field' ) );

		/*
		 * Both statuses, because a virtual product usually lands on "processing"
		 * and never reaches "completed" unless something moves it there. The
		 * order meta guard in credit_order() is what keeps that from crediting
		 * twice.
		 */
		add_action( 'woocommerce_order_status_processing', array( $this, 'credit_order' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'credit_order' ) );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'debit_order' ) );

		/* Top-up shelf → cart → checkout. Same shape as the theme's
		   pay-per-view flow, so a coin package and a rental behave alike. */
		add_action( 'wp_ajax_jws_drama_add_package', array( $this, 'ajax_add_package' ) );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_package_price' ) );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'store_item_coins' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'show_item_coins' ), 10, 2 );

		/*
		 * A coin top-up has no shipping and nothing to invoice an address to
		 * — the billing form is just friction between "picked a package" and
		 * "paid". checkout_style() hides it; simplify_checkout_fields() is
		 * what actually makes that legal, by dropping the "required" flag so
		 * Woo's own server-side validation does not refuse the order over a
		 * field the buyer was never shown.
		 */
		add_filter( 'woocommerce_checkout_fields', array( $this, 'simplify_checkout_fields' ) );
		add_action( 'wp_head', array( $this, 'checkout_style' ) );

		// Wallet tab in the account area, sortable from Theme Options > Profile.
		add_filter( 'streamvid_profile_default_menu_items', array( $this, 'profile_menu' ) );
	}

	/* ---------------------------------------------------------------------- */
	/* Buying a package                                                        */
	/* ---------------------------------------------------------------------- */

	/**
	 * Puts one coin package in the cart and hands back the checkout URL.
	 *
	 * The package is looked up server-side from its index and every number —
	 * coins and price both — is read from the settings, never from the
	 * request: the browser only says which row was clicked, so a tampered
	 * form cannot buy 10,000 coins for a penny.
	 */
	public function ajax_add_package() {

		check_ajax_referer( 'jws_drama_unlock', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'reason' => 'not_logged_in', 'message' => esc_html__( 'Please sign in first.', 'jws_streamvid' ) ), 401 );
		}

		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error( array( 'message' => esc_html__( 'The shop is unavailable right now.', 'jws_streamvid' ) ) );
		}

		$index    = isset( $_POST['package'] ) ? absint( $_POST['package'] ) : -1;
		$package  = null;

		foreach ( Jws_Drama_Settings::packages() as $row ) {
			if ( (int) $row['index'] === $index ) {
				$package = $row;
				break;
			}
		}

		if ( ! $package ) {
			wp_send_json_error( array( 'message' => esc_html__( 'That package is no longer on sale.', 'jws_streamvid' ) ) );
		}

		$product_id = (int) Jws_Drama_Settings::get( 'coin_product', 0 );

		if ( ! $product_id || ! wc_get_product( $product_id ) ) {
			wp_send_json_error(
				array(
					'message' => current_user_can( 'manage_options' )
						? esc_html__( 'No checkout product is set. Pick one under Jws Settings → Drama Coins → Coin Packages.', 'jws_streamvid' )
						: esc_html__( 'Coins cannot be bought right now.', 'jws_streamvid' ),
				)
			);
		}

		/*
		 * One top-up at a time, and nothing else riding along to checkout
		 * with it: a coin purchase started from the drama watch screen
		 * shouldn't also charge whatever was already sitting in the cart
		 * from browsing the shop earlier.
		 */
		WC()->cart->empty_cart();

		$added = WC()->cart->add_to_cart(
			$product_id,
			1,
			0,
			array(),
			array(
				self::ITEM_COINS => (int) $package['total'],
				self::ITEM_PRICE => (float) $package['price'],
				'jws_drama_package' => (int) $package['index'],
			)
		);

		if ( ! $added ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Could not start the checkout. Please try again.', 'jws_streamvid' ) ) );
		}

		wp_send_json_success(
			array(
				'redirect' => wc_get_checkout_url(),
				'coins'    => (int) $package['total'],
			)
		);
	}

	/**
	 * True only when every line in the cart is a coin package — never for a
	 * cart that mixes a top-up with a real shop purchase, which still needs
	 * an actual billing address to ship or invoice.
	 */
	public static function cart_is_coin_only() {

		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $item ) {
			if ( ! isset( $item[ self::ITEM_COINS ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Every billing field but email becomes optional on a coin-only cart —
	 * email is kept, since it's the one thing an order still needs and a
	 * logged-in buyer's account already supplies it, so the field is never
	 * actually blank even hidden.
	 */
	public function simplify_checkout_fields( $fields ) {

		if ( empty( $fields['billing'] ) || ! self::cart_is_coin_only() ) {
			return $fields;
		}

		foreach ( $fields['billing'] as $key => $field ) {
			if ( 'billing_email' !== $key ) {
				$fields['billing'][ $key ]['required'] = false;
			}
		}

		return $fields;
	}

	/**
	 * Hides the billing form on a coin-only checkout — simplify_checkout_fields()
	 * above is what makes that safe, by dropping "required" from every field
	 * this leaves unreachable.
	 */
	public function checkout_style() {

		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || ! self::cart_is_coin_only() ) {
			return;
		}
		?>
		<style>
			#customer_details { display: none; }
			.woocommerce-checkout .col-lg-60.col-xs-12:has(#customer_details) + .col-lg-40.col-xs-12 {
				-webkit-box-flex: 0;
				-ms-flex: 0 0 100%;
				flex: 0 0 100%;
				max-width: 100%;
			}
		</style>
		<?php
	}

	/**
	 * The package's price is the line's price.
	 *
	 * The carrier product's own price is whatever the admin happened to save
	 * on it and is never charged — every package rides the same product, so
	 * the row it came from is the only honest source.
	 */
	public function apply_package_price( $cart ) {

		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $item ) {

			if ( ! isset( $item[ self::ITEM_COINS ], $item[ self::ITEM_PRICE ] ) || empty( $item['data'] ) ) {
				continue;
			}

			$item['data']->set_price( (float) $item[ self::ITEM_PRICE ] );
			$item['data']->set_name(
				sprintf(
					/* translators: %s: coin amount */
					esc_html__( '%s drama coins', 'jws_streamvid' ),
					number_format_i18n( (int) $item[ self::ITEM_COINS ] )
				)
			);
		}
	}

	/** Carries the coin amount from the cart onto the order it becomes. */
	public function store_item_coins( $item, $cart_item_key, $values ) {

		if ( isset( $values[ self::ITEM_COINS ] ) ) {
			$item->update_meta_data( self::ITEM_COINS, (int) $values[ self::ITEM_COINS ] );
		}
	}

	/** What the buyer is getting, spelled out in the cart and at checkout. */
	public function show_item_coins( $data, $cart_item ) {

		if ( isset( $cart_item[ self::ITEM_COINS ] ) ) {
			$data[] = array(
				'key'   => esc_html__( 'Coins', 'jws_streamvid' ),
				'value' => number_format_i18n( (int) $cart_item[ self::ITEM_COINS ] ),
			);
		}

		return $data;
	}

	/* ---------------------------------------------------------------------- */
	/* WooCommerce product                                                     */
	/* ---------------------------------------------------------------------- */

	public function product_field() {

		woocommerce_wp_text_input(
			array(
				'id'                => self::PRODUCT_META,
				'label'             => esc_html__( 'Drama coins', 'jws_streamvid' ),
				'description'       => esc_html__( 'Coins credited to the buyer per item. Leave empty for a normal product.', 'jws_streamvid' ),
				'desc_tip'          => true,
				'type'              => 'number',
				'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
			)
		);
	}

	public function save_product_field( $product_id ) {

		// Woo verifies its own nonce before firing this action.
		$coins = isset( $_POST[ self::PRODUCT_META ] ) ? absint( $_POST[ self::PRODUCT_META ] ) : 0;

		if ( $coins > 0 ) {
			update_post_meta( $product_id, self::PRODUCT_META, $coins );
		} else {
			delete_post_meta( $product_id, self::PRODUCT_META );
		}
	}

	/** Coins a whole order is worth, quantities included. */
	public static function order_coins( $order ) {

		$total = 0;

		foreach ( $order->get_items() as $item ) {

			/*
			 * A package written onto the line at checkout wins over the
			 * product's own amount: every package shares one carrier product,
			 * so the product meta cannot tell them apart — and a plain coin
			 * product still has none of this, which is why the fallback stays.
			 */
			$line_coins = (int) $item->get_meta( self::ITEM_COINS );

			if ( $line_coins > 0 ) {
				$total += $line_coins * (int) $item->get_quantity();
				continue;
			}

			$product_id = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
			$coins      = (int) get_post_meta( $product_id, self::PRODUCT_META, true );

			if ( ! $coins && $item->get_variation_id() ) {
				// A variation usually inherits the amount from its parent.
				$coins = (int) get_post_meta( $item->get_product_id(), self::PRODUCT_META, true );
			}

			if ( $coins > 0 ) {
				$total += $coins * (int) $item->get_quantity();
			}
		}

		return $total;
	}

	public function credit_order( $order_id ) {

		$order = wc_get_order( $order_id );

		if ( ! $order || $order->get_meta( self::ORDER_META ) ) {
			return;
		}

		$user_id = (int) $order->get_user_id();
		$coins   = self::order_coins( $order );

		if ( ! $user_id || $coins <= 0 ) {
			return;
		}

		Jws_Drama_Wallet::credit(
			$user_id,
			$coins,
			'topup',
			$order_id,
			sprintf( 'Order #%s', $order->get_order_number() )
		);

		/* Written before anything else can re-enter: processing -> completed
		   fires this twice on a normal virtual sale. */
		$order->update_meta_data( self::ORDER_META, $coins );
		$order->save();

		$order->add_order_note(
			sprintf(
				/* translators: %d: coin amount */
				esc_html__( 'Credited %d drama coins.', 'jws_streamvid' ),
				$coins
			)
		);
	}

	/**
	 * Takes the coins back when an order is refunded — down to zero, never into
	 * the negative, because they may already have been spent.
	 */
	public function debit_order( $order_id ) {

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$coins   = (int) $order->get_meta( self::ORDER_META );
		$user_id = (int) $order->get_user_id();

		if ( ! $user_id || $coins <= 0 ) {
			return;
		}

		$take = min( $coins, Jws_Drama_Wallet::balance( $user_id ) );

		if ( $take > 0 ) {
			Jws_Drama_Wallet::debit(
				$user_id,
				$take,
				'refund',
				$order_id,
				sprintf( 'Refund order #%s', $order->get_order_number() )
			);
		}

		$order->delete_meta_data( self::ORDER_META );
		$order->save();
	}

	/**
	 * The top-up shelf.
	 *
	 * Theme Options > Drama Short > "Select products for coin packages" decides
	 * both which products appear and in what order. With nothing picked it falls
	 * back to every product carrying a coin amount, smallest first, so the shelf
	 * is never empty just because the option has not been touched.
	 *
	 * A selected product with no coin amount is dropped rather than sold: it
	 * would take the buyer's money and credit nothing.
	 */
	public static function packages() {

		if ( ! class_exists( 'WooCommerce' ) ) {
			return array();
		}

		$chosen = function_exists( 'jws_theme_get_option' ) ? jws_theme_get_option( 'drama_coin_products' ) : array();
		$chosen = is_array( $chosen ) ? array_filter( array_map( 'absint', $chosen ) ) : array();

		if ( $chosen ) {

			$posts = get_posts(
				array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'post__in'       => $chosen,
					'orderby'        => 'post__in',
					'posts_per_page' => count( $chosen ),
				)
			);

			return array_values(
				array_filter(
					$posts,
					function ( $post ) {
						return (int) get_post_meta( $post->ID, self::PRODUCT_META, true ) > 0;
					}
				)
			);
		}

		return get_posts(
			array(
				'post_type'      => 'product',
				'posts_per_page' => 20,
				'post_status'    => 'publish',
				'meta_key'       => self::PRODUCT_META,
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
				'meta_query'     => array(
					array(
						'key'     => self::PRODUCT_META,
						'value'   => 0,
						'compare' => '>',
						'type'    => 'NUMERIC',
					),
				),
			)
		);
	}

	/* ---------------------------------------------------------------------- */
	/* Account area                                                            */
	/* ---------------------------------------------------------------------- */

	public function profile_menu( $items ) { 

		$items['drama-coins'] = array(
			'title'    => esc_html__( 'Coins', 'jws_streamvid' ),
			'icon'     => '',
			'callback' => function () {
				jws_streamvid_load_template( '../includes/drama/templates/parts/wallet.php', false );
			},
			'priority' => 35,
		);

		return $items;
	}
}
