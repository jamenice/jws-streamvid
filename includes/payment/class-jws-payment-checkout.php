<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * The checkout itself: one page, one purchase, whatever kind it is.
 *
 * What is being bought lives in a per-user "cart" in usermeta rather than in
 * the URL, for the same reason WooCommerce keeps one: the theme's buy button
 * sends the browser to a checkout URL it was handed at page load, long before
 * anyone clicked anything, so the page has to be able to work out what it is
 * selling without being told in the link. A query string still works — the
 * membership links and the coin shelf use one — and simply writes the cart on
 * arrival.
 *
 * One item at a time, deliberately: all three flows being replaced sold one
 * thing at a time too, and a basket that can hold a rental and a membership
 * together is a basket that has to decide what a half-failed payment means.
 */
class Jws_Payment_Checkout {

	const CART_META  = '_jws_payment_cart';
	const SHORTCODE  = 'jws_checkout';
	const NONCE      = 'jws_payment_checkout';
	const PAGE_SLUG  = 'jws-checkout';

	public function register() {

		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );

		add_action( 'wp_ajax_jws_payment_start', array( $this, 'ajax_start' ) );
		add_action( 'wp_ajax_jws_payment_settle', array( $this, 'ajax_settle' ) );

		/* A checkout page under page caching would serve one buyer another
		   buyer's order summary. */
		add_action( 'template_redirect', array( $this, 'never_cache' ) );
	}

	/* ---------------------------------------------------------------------- */
	/* The page                                                                */
	/* ---------------------------------------------------------------------- */

	public static function page_id() {
		return (int) Jws_Payment_Settings::get( 'checkout_page', 0 );
	}

	public static function page_url() {

		$page_id = self::page_id();

		return $page_id && get_post( $page_id ) ? get_permalink( $page_id ) : home_url( '/' );
	}

	public static function is_checkout() {

		$page_id = self::page_id();

		return $page_id && is_page( $page_id );
	}

	/**
	 * A link that opens the checkout on one specific thing.
	 *
	 * Used by everything that knows what it is selling at render time — the
	 * membership buttons, the coin shelf — as opposed to the theme's buy
	 * button, which posts first and navigates to a fixed URL afterwards.
	 */
	public static function url_for( $type, $item_id, $return_to = '' ) {

		$args = array( 'jws_item' => sanitize_key( $type ) . ':' . (int) $item_id );

		if ( $return_to ) {
			$args['jws_back'] = rawurlencode( $return_to );
		}

		return add_query_arg( $args, self::page_url() );
	}

	/**
	 * Makes the checkout page if it is missing, and returns its id.
	 *
	 * Idempotent: a page already carrying the shortcode is adopted rather than
	 * duplicated, so switching the system off and on again does not litter the
	 * site with checkouts.
	 */
	public static function ensure_page() {

		$existing = self::page_id();

		if ( $existing && 'page' === get_post_type( $existing ) && 'trash' !== get_post_status( $existing ) ) {
			return $existing;
		}

		$found = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 1,
				'name'           => self::PAGE_SLUG,
				'fields'         => 'ids',
			)
		);

		$page_id = $found ? (int) $found[0] : 0;

		if ( ! $page_id ) {
			$page_id = wp_insert_post(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'post_title'     => esc_html__( 'Checkout', 'jws_streamvid' ),
					'post_name'      => self::PAGE_SLUG,
					'post_content'   => '[' . self::SHORTCODE . ']',
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
				)
			);
		}

		if ( is_wp_error( $page_id ) || ! $page_id ) {
			return 0;
		}

		Jws_Payment_Settings::set( 'checkout_page', (int) $page_id );

		return (int) $page_id;
	}

	/** Nothing on this page is the same for two people. */
	public function never_cache() {

		if ( ! self::is_checkout() ) {
			return;
		}

		nocache_headers();

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
	}

	/* ---------------------------------------------------------------------- */
	/* The cart                                                                */
	/* ---------------------------------------------------------------------- */

	/**
	 * Remembers what this person is about to buy.
	 *
	 * Only the type, the id and where to send them afterwards — never a price.
	 * Everything chargeable is resolved again from the settings and post meta
	 * at the moment the order is created.
	 */
	public static function set_cart( $user_id, $type, $item_id, $return_to = '' ) {

		update_user_meta(
			(int) $user_id,
			self::CART_META,
			array(
				'type'      => sanitize_key( $type ),
				'item'      => (int) $item_id,
				'return_to' => esc_url_raw( $return_to ),
				'added'     => time(),
			)
		);
	}

	public static function get_cart( $user_id = 0 ) {

		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		if ( ! $user_id ) {
			return null;
		}

		$cart = get_user_meta( $user_id, self::CART_META, true );

		if ( ! is_array( $cart ) || empty( $cart['type'] ) ) {
			return null;
		}

		return $cart;
	}

	public static function clear_cart( $user_id = 0 ) {

		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		if ( $user_id ) {
			delete_user_meta( $user_id, self::CART_META );
		}
	}

	/**
	 * Reads `?jws_item=type:id` into the cart.
	 *
	 * Nothing is trusted beyond the two ids: resolve() decides whether they
	 * name something that is actually for sale, and at what price.
	 */
	private function absorb_query_args() {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['jws_item'] ) || ! is_user_logged_in() ) {
			return;
		}

		$raw   = sanitize_text_field( wp_unslash( $_GET['jws_item'] ) );
		$parts = explode( ':', $raw, 2 );

		if ( 2 !== count( $parts ) ) {
			return;
		}

		$back = isset( $_GET['jws_back'] ) ? esc_url_raw( urldecode( wp_unslash( $_GET['jws_back'] ) ) ) : wp_get_referer();
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		self::set_cart( get_current_user_id(), $parts[0], (int) $parts[1], $back );
	}

	/** The order behind a return token, whoever is asking. */
	public static function find_by_token( $token ) {

		global $wpdb;

		$table = Jws_Payment_Ledger::table();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE source = %s AND source_ref = %s LIMIT 1",
				Jws_Payment_Orders::SOURCE,
				(string) $token
			)
		);
	}

	/**
	 * Where someone goes once they have paid.
	 *
	 * The page they came from if we know it, and otherwise the place the thing
	 * they bought actually lives — a rented film is no use to anyone sitting on
	 * a receipt page.
	 */
	public static function destination( $order ) {

		$meta = Jws_Payment_Orders::meta( $order );

		if ( ! empty( $meta['return_to'] ) ) {
			return $meta['return_to'];
		}

		switch ( $order->type ) {

			case 'membership':
				return function_exists( 'pmpro_url' ) ? pmpro_url( 'account' ) : home_url( '/' );

			case 'coins':
				return class_exists( 'Jws_Streamvid_Profile' )
					? Jws_Streamvid_Profile::get_url( 'drama-coins' )
					: home_url( '/' );

			default:
				return get_post( $order->item_id ) ? get_permalink( $order->item_id ) : home_url( '/' );
		}
	}

	/* ---------------------------------------------------------------------- */
	/* Assets                                                                  */
	/* ---------------------------------------------------------------------- */

	public function enqueue() {

		if ( ! self::is_checkout() || ! Jws_Payment_Settings::is_enabled() ) {
			return;
		}

		$base    = plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . 'public/assets/';
		$version = defined( 'JWS_STREAMVID_VERSION' ) ? JWS_STREAMVID_VERSION : '1.0.0';

		wp_enqueue_style( 'jws-payment', $base . 'css/jws-payment.css', array(), $version );

		$methods = Jws_Payment_Settings::available_methods();
		$stripe  = Jws_Payment_Settings::get( 'stripe' );

		$needs_stripe_js = false;

		foreach ( $methods as $method ) {
			if ( 'stripe' === $method['gateway'] && 'redirect' !== $method['flow'] ) {
				$needs_stripe_js = true;
			}
		}

		if ( $needs_stripe_js ) {
			wp_enqueue_script( 'jws-stripe-js', 'https://js.stripe.com/v3/', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		}

		wp_enqueue_script(
			'jws-payment',
			$base . 'js/jws-payment.js',
			$needs_stripe_js ? array( 'jquery', 'jws-stripe-js' ) : array( 'jquery' ),
			$version,
			true
		);

		wp_localize_script(
			'jws-payment',
			'jws_payment',
			array(
				'ajax_url'    => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( self::NONCE ),
				'publishable' => $needs_stripe_js ? $stripe['publishable'] : '',
				'country'     => $stripe['country'],
				'locale'      => strtolower( substr( get_locale(), 0, 2 ) ),
				'i18n'        => array(
					'generic'    => esc_html__( 'Something went wrong. Please try again.', 'jws_streamvid' ),
					'processing' => esc_html__( 'Processing…', 'jws_streamvid' ),
					'redirect'   => esc_html__( 'Taking you to the payment page…', 'jws_streamvid' ),
				),
			)
		);
	}

	/* ---------------------------------------------------------------------- */
	/* Rendering                                                               */
	/* ---------------------------------------------------------------------- */

	public function render() {

		if ( ! Jws_Payment_Settings::is_enabled() ) {
			return $this->notice( esc_html__( 'The checkout is not switched on.', 'jws_streamvid' ) );
		}

		if ( ! is_user_logged_in() ) {
			return $this->notice(
				esc_html__( 'Please sign in to complete your purchase.', 'jws_streamvid' ),
				wp_login_url( self::page_url() ),
				esc_html__( 'Sign in', 'jws_streamvid' )
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['jws_payment'] ) ) {
			return $this->render_result();
		}

		$this->absorb_query_args();

		$cart = self::get_cart();

		if ( ! $cart ) {
			return $this->notice( esc_html__( 'There is nothing to pay for yet.', 'jws_streamvid' ), home_url( '/' ), esc_html__( 'Browse', 'jws_streamvid' ) );
		}

		$item = Jws_Payment_Items::resolve( $cart['type'], $cart['item'] );

		if ( is_wp_error( $item ) ) {
			return $this->notice( $item->get_error_message(), home_url( '/' ), esc_html__( 'Browse', 'jws_streamvid' ) );
		}

		$methods = Jws_Payment_Settings::available_methods();

		if ( ! $methods ) {
			return $this->notice( esc_html__( 'No payment method is available right now. Please try again later.', 'jws_streamvid' ) );
		}

		ob_start();
		$this->render_checkout( $item, $methods );

		return ob_get_clean();
	}

	private function render_checkout( array $item, array $methods ) {

		$wallets = array_intersect( array( 'apple_pay', 'google_pay' ), array_keys( $methods ) );
		$has_card = isset( $methods['card'] );
		?>
		<div class="jws-checkout"
			data-type="<?php echo esc_attr( $item['type'] ); ?>"
			data-item="<?php echo (int) $item['item_id']; ?>"
			data-amount="<?php echo esc_attr( Jws_Payment_Stripe::minor_units( $item['amount'], $item['currency'] ) ); ?>"
			data-currency="<?php echo esc_attr( strtolower( $item['currency'] ) ); ?>"
			data-mode="<?php echo esc_attr( ! empty( $item['recurring'] ) ? 'subscription' : 'payment' ); ?>">

			<div class="jws-checkout-summary">
				<h5><?php echo esc_html__( 'Your order', 'jws_streamvid' ); ?></h5>

				<div class="jws-checkout-line">
					<?php if ( ! empty( $item['meta']['thumbnail_id'] ) ) : ?>
						<div class="jws-checkout-thumb"><?php echo wp_get_attachment_image( (int) $item['meta']['thumbnail_id'], 'thumbnail' ); ?></div>
					<?php endif; ?>

					<div class="jws-checkout-line-text">
						<span class="jws-checkout-item"><?php echo esc_html( $item['label'] ); ?></span>
						<?php if ( 'rent' === $item['type'] && ! empty( $item['meta']['days'] ) ) : ?>
							<span class="jws-checkout-note">
								<?php
								printf(
									/* translators: %d: number of days the rental lasts. */
									esc_html( _n( 'Watch for %d day once you start.', 'Watch for %d days once you start.', (int) $item['meta']['days'], 'jws_streamvid' ) ),
									(int) $item['meta']['days']
								);
								?>
							</span>
						<?php endif; ?>
					</div>

					<span class="jws-checkout-price"><?php echo esc_html( Jws_Payment_Items::format_price( $item['amount'], $item['currency'] ) ); ?></span>
				</div>

				<div class="jws-checkout-total">
					<span><?php echo esc_html__( 'Total today', 'jws_streamvid' ); ?></span>
					<strong><?php echo esc_html( Jws_Payment_Items::format_price( $item['amount'], $item['currency'] ) ); ?></strong>
				</div>

				<?php if ( ! empty( $item['recurring'] ) ) : ?>
					<p class="jws-checkout-terms">
						<?php
						$period = Jws_Payment_Items::period_phrase( $item['period'], $item['cycle'] );

						if ( (float) $item['amount'] < (float) $item['renew'] ) {
							printf(
								/* translators: 1: price charged today, 2: billing period, 3: price charged on every renewal. */
								esc_html__( '%1$s for the first %2$s, then %3$s every %2$s. Cancel any time from your account.', 'jws_streamvid' ),
								esc_html( Jws_Payment_Items::format_price( $item['amount'], $item['currency'] ) ),
								esc_html( $period ),
								esc_html( Jws_Payment_Items::format_price( $item['renew'], $item['currency'] ) )
							);
						} else {
							printf(
								/* translators: 1: renewal price, 2: billing period. */
								esc_html__( 'Renews at %1$s every %2$s. Cancel any time from your account.', 'jws_streamvid' ),
								esc_html( Jws_Payment_Items::format_price( $item['renew'], $item['currency'] ) ),
								esc_html( $period )
							);
						}
						?>
					</p>
				<?php endif; ?>
			</div>

			<div class="jws-checkout-pay">
				<h5><?php echo esc_html__( 'Payment', 'jws_streamvid' ); ?></h5>

				<?php if ( $wallets ) : ?>
					<?php /* Hidden until Stripe reports the browser can actually
					         offer one — an empty wallet slot reads as broken. */ ?>
					<div class="jws-checkout-wallets" hidden>
						<div id="jws-express-checkout"></div>
						<div class="jws-checkout-or"><span><?php echo esc_html__( 'or', 'jws_streamvid' ); ?></span></div>
					</div>
				<?php endif; ?>

				<?php if ( $has_card ) : ?>
					<div class="jws-checkout-card">
						<div id="jws-card-element"></div>
						<button type="button" class="jws-checkout-button button-default" data-method="card">
							<?php
							printf(
								/* translators: %s: amount to pay. */
								esc_html__( 'Pay %s', 'jws_streamvid' ),
								esc_html( Jws_Payment_Items::format_price( $item['amount'], $item['currency'] ) )
							);
							?>
						</button>
					</div>
				<?php endif; ?>

				<?php $redirects = array_filter( $methods, function ( $m ) { return 'redirect' === $m['flow']; } ); ?>

				<?php if ( $redirects ) : ?>
					<?php if ( $has_card || $wallets ) : ?>
						<div class="jws-checkout-or"><span><?php echo esc_html__( 'or', 'jws_streamvid' ); ?></span></div>
					<?php endif; ?>

					<div class="jws-checkout-alt">
						<?php foreach ( $redirects as $key => $method ) : ?>
							<button type="button" class="jws-checkout-button button-default jws-checkout-button--alt jws-checkout-button--<?php echo esc_attr( $key ); ?>" data-method="<?php echo esc_attr( $key ); ?>">
								<?php echo esc_html( $method['label'] ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<p class="jws-checkout-error" role="alert" hidden></p>
			</div>
		</div>
		<?php
	}

	/** The page someone lands on after paying, or after backing out. */
	private function render_result() {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$state = sanitize_key( wp_unslash( $_GET['jws_payment'] ) );
		$token = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$order = $token ? self::find_by_token( $token ) : null;

		if ( ! $order || (int) $order->user_id !== get_current_user_id() ) {
			return $this->notice( esc_html__( 'We could not find that order.', 'jws_streamvid' ), home_url( '/' ), esc_html__( 'Browse', 'jws_streamvid' ) );
		}

		if ( 'cancelled' === $state || Jws_Payment_Orders::STATUS_COMPLETED !== $order->status ) {
			return $this->notice(
				esc_html__( 'The payment was not completed, so nothing has been charged.', 'jws_streamvid' ),
				self::page_url(),
				esc_html__( 'Try again', 'jws_streamvid' )
			);
		}

		self::clear_cart();

		$outcome = self::outcome( $order );
		$user    = get_userdata( $order->user_id );
		$paid_at = $order->paid_at ? $order->paid_at : $order->created_at;

		ob_start();
		?>
		<div class="jws-checkout jws-checkout--done">
			<div class="jws-checkout-summary jws-receipt">

				<div class="jws-receipt-head">
					<span class="jws-receipt-check" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
					</span>
					<h5><?php echo esc_html__( 'Payment complete', 'jws_streamvid' ); ?></h5>
					<p class="jws-receipt-sub">
						<?php
						printf(
							/* translators: %s: amount paid, already formatted. */
							esc_html__( 'Thanks — your payment of %s went through.', 'jws_streamvid' ),
							'<strong>' . esc_html( Jws_Payment_Items::format_price( $order->amount, $order->currency ) ) . '</strong>'
						);
						?>
					</p>
				</div>

				<?php /* What the money actually bought, in the buyer's terms. */ ?>
				<div class="jws-receipt-granted">
					<strong><?php echo esc_html( $outcome['title'] ); ?></strong>
					<?php if ( $outcome['detail'] ) : ?>
						<span><?php echo esc_html( $outcome['detail'] ); ?></span>
					<?php endif; ?>
				</div>

				<dl class="jws-receipt-rows">

					<div class="jws-receipt-row">
						<dt><?php echo esc_html__( 'Order', 'jws_streamvid' ); ?></dt>
						<dd><?php echo esc_html( $order->order_number ); ?></dd>
					</div>

					<div class="jws-receipt-row">
						<dt><?php echo esc_html__( 'Date', 'jws_streamvid' ); ?></dt>
						<dd><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), strtotime( $paid_at ) ) ); ?></dd>
					</div>

					<div class="jws-receipt-row">
						<dt><?php echo esc_html__( 'Item', 'jws_streamvid' ); ?></dt>
						<dd><?php echo esc_html( $order->item_label ); ?></dd>
					</div>

					<div class="jws-receipt-row">
						<dt><?php echo esc_html__( 'Paid with', 'jws_streamvid' ); ?></dt>
						<dd><?php echo esc_html( self::method_label( $order ) ); ?></dd>
					</div>

					<?php if ( $user ) : ?>
						<div class="jws-receipt-row">
							<dt><?php echo esc_html__( 'Account', 'jws_streamvid' ); ?></dt>
							<dd><?php echo esc_html( $user->user_email ); ?></dd>
						</div>
					<?php endif; ?>

					<?php if ( $order->gateway_ref ) : ?>
						<?php /* The one string support will ask for; also what the
						         buyer can match against their card statement. */ ?>
						<div class="jws-receipt-row">
							<dt><?php echo esc_html__( 'Reference', 'jws_streamvid' ); ?></dt>
							<dd><code><?php echo esc_html( $order->gateway_ref ); ?></code></dd>
						</div>
					<?php endif; ?>
				</dl>

				<div class="jws-receipt-total">
					<span><?php echo esc_html__( 'Total paid', 'jws_streamvid' ); ?></span>
					<strong><?php echo esc_html( Jws_Payment_Items::format_price( $order->amount, $order->currency ) ); ?></strong>
				</div>

				<div class="jws-receipt-actions">
					<a class="jws-checkout-button button-default" href="<?php echo esc_url( self::destination( $order ) ); ?>">
						<?php echo esc_html( self::destination_label( $order ) ); ?>
					</a>
					<a class="jws-checkout-button button-custom jws-checkout-button--alt" href="<?php echo esc_url( home_url( '/' ) ); ?>">
						<?php echo esc_html__( 'Back to home', 'jws_streamvid' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * What the buyer actually got, said the way they would say it.
	 *
	 * "Payment complete" tells someone their card worked. It does not tell them
	 * their coins have landed, when their rental runs out, or when they will be
	 * charged again — which is what they came to this page to find out.
	 *
	 * @return array title and detail, both already plain text.
	 */
	private static function outcome( $order ) {

		$meta = Jws_Payment_Orders::meta( $order );

		switch ( $order->type ) {

			case 'coins':
				$coins   = isset( $meta['coins'] ) ? (int) $meta['coins'] : 0;
				$balance = class_exists( 'Jws_Drama_Wallet' ) ? Jws_Drama_Wallet::balance( $order->user_id ) : null;

				return array(
					'title'  => sprintf(
						/* translators: %s: number of coins. */
						esc_html__( '%s coins added to your wallet', 'jws_streamvid' ),
						number_format_i18n( $coins )
					),
					'detail' => null === $balance ? '' : sprintf(
						/* translators: %s: the wallet balance after this purchase. */
						esc_html__( 'Your balance is now %s coins.', 'jws_streamvid' ),
						number_format_i18n( $balance )
					),
				);

			case 'membership':
				return array(
					'title'  => sprintf(
						/* translators: %s: membership level name. */
						esc_html__( '%s is active', 'jws_streamvid' ),
						$order->item_label
					),
					'detail' => self::renewal_sentence( $order ),
				);

			case 'rent':
				$days = isset( $meta['days'] ) ? (int) $meta['days'] : 0;

				return array(
					'title'  => esc_html__( 'Your rental is ready to watch', 'jws_streamvid' ),
					'detail' => $days
						? sprintf(
							/* translators: %d: number of days the rental lasts. */
							esc_html( _n( 'You have %d day to finish it once you start watching.', 'You have %d days to finish it once you start watching.', $days, 'jws_streamvid' ) ),
							$days
						)
						: '',
				);

			default:
				return array(
					'title'  => esc_html__( 'Added to your library', 'jws_streamvid' ),
					'detail' => esc_html__( 'Yours to watch any time, as often as you like.', 'jws_streamvid' ),
				);
		}
	}

	/** When a subscription bills next, when there is a subscription to ask. */
	private static function renewal_sentence( $order ) {

		if ( ! (int) $order->subscription_id ) {
			return '';
		}

		$subscription = Jws_Payment_Subscriptions::find( (int) $order->subscription_id );

		if ( ! $subscription || empty( $subscription->current_period_end ) ) {
			return '';
		}

		return sprintf(
			/* translators: 1: renewal amount, 2: date of the next payment. */
			esc_html__( 'Renews at %1$s on %2$s. You can cancel any time from your account.', 'jws_streamvid' ),
			Jws_Payment_Items::format_price( $subscription->amount, $subscription->currency ),
			date_i18n( get_option( 'date_format' ), strtotime( $subscription->current_period_end ) )
		);
	}

	/** How the payment was taken — "Credit or debit card · Stripe". */
	private static function method_label( $order ) {

		$gateway = $order->gateway ? Jws_Payment_Settings::gateway_label( $order->gateway ) : '';
		$catalog = Jws_Payment_Settings::method_catalog();

		if ( isset( $catalog[ $order->method ] ) ) {
			$method = $catalog[ $order->method ]['label'];

			/* PayPal's method and gateway are the same word; saying it twice
			   reads like a mistake. */
			return $method === $gateway ? $gateway : $method . ' · ' . $gateway;
		}

		return $gateway ? $gateway : esc_html__( 'Card', 'jws_streamvid' );
	}

	/**
	 * What the button that leaves this page should say.
	 *
	 * "Continue" is a button that tells the buyer nothing about where it goes;
	 * every one of these knows exactly where it is sending them.
	 */
	private static function destination_label( $order ) {

		switch ( $order->type ) {

			case 'coins':
				return esc_html__( 'Go to my wallet', 'jws_streamvid' );

			case 'membership':
				return esc_html__( 'View my membership', 'jws_streamvid' );

			default:
				return esc_html__( 'Watch now', 'jws_streamvid' );
		}
	}

	private function notice( $message, $url = '', $label = '' ) {

		ob_start();
		?>
		<div class="jws-checkout jws-checkout--notice">
			<p><?php echo esc_html( $message ); ?></p>
			<?php if ( $url && $label ) : ?>
				<a class="jws-checkout-button button-default" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ---------------------------------------------------------------------- */
	/* AJAX                                                                    */
	/* ---------------------------------------------------------------------- */

	/**
	 * Turns the cart and a chosen method into a payment.
	 *
	 * Answers with either somewhere to go (the hosted pages) or a client secret
	 * (the card form and the wallet sheets, which never leave the site). Prices
	 * are read from resolve() and never from the request, so a tampered form
	 * cannot buy a $299 plan for a dollar.
	 */
	public function ajax_start() {

		$this->guard();

		$method_key = isset( $_POST['method'] ) ? sanitize_key( wp_unslash( $_POST['method'] ) ) : '';
		$methods    = Jws_Payment_Settings::available_methods();

		if ( ! isset( $methods[ $method_key ] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'That payment method is not available.', 'jws_streamvid' ) ), 400 );
		}

		$cart = self::get_cart();

		if ( ! $cart ) {
			wp_send_json_error( array( 'message' => esc_html__( 'There is nothing to pay for.', 'jws_streamvid' ) ), 400 );
		}

		$item = Jws_Payment_Items::resolve( $cart['type'], $cart['item'] );

		if ( is_wp_error( $item ) ) {
			wp_send_json_error( array( 'message' => $item->get_error_message() ), 400 );
		}

		$method  = $methods[ $method_key ];
		$gateway = $method['gateway'];

		/*
		 * The order is written before the gateway is called, and carries a copy
		 * of what was bought rather than a pointer to it, so a webhook can
		 * arrive before this request has even returned and still find a row to
		 * fulfil.
		 */
		$order_id = Jws_Payment_Orders::create(
			array(
				'user_id'    => get_current_user_id(),
				'type'       => $item['type'],
				'item_id'    => $item['item_id'],
				'item_label' => $item['label'],
				'amount'     => $item['amount'],
				'currency'   => $item['currency'],
				'gateway'    => $gateway,
				'method'     => $method_key,
				'meta'       => array_merge(
					$item['meta'],
					array(
						'recurring'   => ! empty( $item['recurring'] ),
						'renew'       => $item['renew'],
						'period'      => $item['period'],
						'cycle'       => $item['cycle'],
						'fingerprint' => $item['fingerprint'],
						'return_to'   => isset( $cart['return_to'] ) ? $cart['return_to'] : '',
					)
				),
			)
		);

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Could not start the purchase.', 'jws_streamvid' ) ), 500 );
		}

		$order = Jws_Payment_Orders::find( $order_id );

		if ( 'paypal' === $gateway ) {

			$url = ! empty( $item['recurring'] )
				? Jws_Payment_Paypal::create_subscription( $order, $item )
				: Jws_Payment_Paypal::create_order( $order );

			$this->send_redirect_or_fail( $url, $order_id );
		}

		if ( 'redirect' === $method['flow'] ) {
			$this->send_redirect_or_fail( Jws_Payment_Stripe::checkout_session( $order, $item ), $order_id );
		}

		$intent = Jws_Payment_Stripe::intent( $order, $item );

		if ( is_wp_error( $intent ) ) {
			Jws_Payment_Orders::mark_failed( $order_id );
			wp_send_json_error( array( 'message' => $intent->get_error_message() ), 200 );
		}

		wp_send_json_success(
			array(
				'flow'         => 'elements',
				'token'        => $order->source_ref,
				'clientSecret' => $intent['client_secret'],
				'returnUrl'    => add_query_arg(
					array( Jws_Payment_Stripe::RETURN_VAR => $order->source_ref ),
					self::page_url()
				),
			)
		);
	}

	/**
	 * Confirms an order the browser has just paid for.
	 *
	 * Stripe's own webhook is what really settles it, but on a site whose
	 * webhooks are slow — or not reachable at all, which is every local
	 * install — the buyer would otherwise be told nothing happened. This asks
	 * Stripe directly about that one order; mark_paid() makes it safe for both
	 * to arrive.
	 */
	public function ajax_settle() {

		$this->guard();

		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$order = $token ? self::find_by_token( $token ) : null;

		if ( ! $order || (int) $order->user_id !== get_current_user_id() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'We could not find that order.', 'jws_streamvid' ) ), 404 );
		}

		$paid = 'paypal' === $order->gateway
			? Jws_Payment_Paypal::settle( $order )
			: Jws_Payment_Stripe::settle( $order );

		if ( ! $paid ) {
			wp_send_json_error( array( 'message' => esc_html__( 'The payment has not gone through yet.', 'jws_streamvid' ) ), 200 );
		}

		self::clear_cart();

		wp_send_json_success(
			array(
				'redirect' => add_query_arg(
					array( 'jws_payment' => 'done', 'order' => $token ),
					self::page_url()
				),
			)
		);
	}

	/** Both endpoints need the same two things to be true. */
	private function guard() {

		if ( ! Jws_Payment_Settings::is_enabled() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'The checkout is not switched on.', 'jws_streamvid' ) ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please sign in first.', 'jws_streamvid' ) ), 401 );
		}

		check_ajax_referer( self::NONCE, 'nonce' );
	}

	/** Sends the buyer to a gateway, or gives up cleanly if it refused. */
	private function send_redirect_or_fail( $url, $order_id ) {

		if ( is_wp_error( $url ) ) {
			Jws_Payment_Orders::mark_failed( $order_id );
			wp_send_json_error( array( 'message' => $url->get_error_message() ), 200 );
		}

		wp_send_json_success( array( 'flow' => 'redirect', 'redirect' => $url ) );
	}
}
