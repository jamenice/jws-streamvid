<?php

/**
 * AJAX for the drama watch screen.
 *
 * Switching episode swaps the stage instead of reloading: a 42-episode series is
 * meant to be binged, and a full navigation between two-minute episodes throws
 * away the header, the panel and the whole module tree every time.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/drama
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Drama_Ajax {

	public function register() {

		foreach ( array( 'wp_ajax_', 'wp_ajax_nopriv_' ) as $prefix ) {
			add_action( $prefix . 'jws_drama_episode', array( $this, 'episode' ) );

			/* Both halves have a nopriv twin: an ad is watched by whoever is in
			   front of the screen, and this one is open to viewers who have not
			   signed in — there is no account for it to touch. */
			add_action( $prefix . 'jws_drama_ad_start', array( $this, 'ad_start' ) );
			add_action( $prefix . 'jws_drama_ad_claim', array( $this, 'ad_claim' ) );
		}

		/* Spending coins is not a read — it needs a session and a nonce, so
		   there is no nopriv twin here on purpose. */
		add_action( 'wp_ajax_jws_drama_unlock', array( $this, 'unlock' ) );

		/* The buy panel quotes a live balance, so it is rendered per request
		   rather than baked into the page. */
		add_action( 'wp_ajax_jws_drama_purchase_panel', array( $this, 'purchase_panel' ) );
		add_action( 'wp_ajax_jws_drama_checkout', array( $this, 'checkout' ) );
	}

	/**
	 * Renders the buy panel for whoever asked.
	 *
	 * The same markup serves the watch screen and the Coins tab; passing an
	 * episode only changes the price line in its header.
	 */
	public function purchase_panel() {

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please sign in first.', 'jws_streamvid' ) ), 401 );
		}

		check_ajax_referer( 'jws_drama_unlock', 'nonce' );

		$episode_id = isset( $_POST['episode_id'] ) ? absint( $_POST['episode_id'] ) : 0;

		if ( $episode_id && Jws_Drama_Post_Types::EPISODE !== get_post_type( $episode_id ) ) {
			$episode_id = 0;
		}

		ob_start();
		jws_streamvid_load_template(
			'../includes/drama/templates/parts/purchase-modal.php',
			false,
			array( 'episode_id' => $episode_id )
		);

		wp_send_json_success(
			array(
				'html'    => ob_get_clean(),
				'balance' => Jws_Drama_Wallet::balance(),
			)
		);
	}

	/**
	 * Turns a chosen package or plan into a payment.
	 *
	 * Takes the method the shopper picked — apple_pay, google_pay, paypal or
	 * quick_pay — and resolves which gateway is behind it, since three of the
	 * four are Stripe wearing different hats.
	 *
	 * Answers with either somewhere to go (Quick Pay's hosted page) or a client
	 * secret (the Apple Pay / Google Pay sheet, which never leaves the site).
	 * Prices are read back from the settings, never from the request, so a
	 * tampered form cannot buy 10,000 coins for the price of 300.
	 */
	public function checkout() {

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please sign in first.', 'jws_streamvid' ) ), 401 );
		}

		check_ajax_referer( 'jws_drama_unlock', 'nonce' );

		$method_key = isset( $_POST['method'] ) ? sanitize_key( wp_unslash( $_POST['method'] ) ) : '';
		$kind       = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		$index      = isset( $_POST['item'] ) ? absint( $_POST['item'] ) : -1;

		/*
		 * Resolved against what the site is actually offering, not against the
		 * full catalogue: this is the same list the panel was rendered from, so
		 * a button the admin switched off cannot be replayed from a stale tab.
		 */
		$methods = Jws_Drama_Settings::available_methods();

		if ( ! isset( $methods[ $method_key ] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'That payment method is not available.', 'jws_streamvid' ) ), 400 );
		}

		$method  = $methods[ $method_key ];
		$gateway = $method['gateway'];
		$config  = Jws_Drama_Settings::get( $gateway );

		/* Look the item up by index against the same reader the panel rendered
		   from, so a tampered index cannot invent a price. */
		$items = 'plan' === $kind ? Jws_Drama_Settings::plans() : Jws_Drama_Settings::packages();
		$item  = null;

		foreach ( $items as $candidate ) {
			if ( $candidate['index'] === $index ) {
				$item = $candidate;
				break;
			}
		}

		if ( ! $item ) {
			wp_send_json_error( array( 'message' => esc_html__( 'That option is no longer on sale.', 'jws_streamvid' ) ), 400 );
		}

		$episode_id = isset( $_POST['episode_id'] ) ? absint( $_POST['episode_id'] ) : 0;

		if ( $episode_id && Jws_Drama_Post_Types::EPISODE !== get_post_type( $episode_id ) ) {
			$episode_id = 0;
		}

		/*
		 * The order is written before Stripe is called, and carries a copy of
		 * what was bought rather than a pointer to it. A webhook can then arrive
		 * before this request has even returned and still find a row to fulfil.
		 */
		$is_plan  = 'plan' === $kind;
		$currency = Jws_Drama_Settings::get( 'currency', 'USD' );

		$order_id = Jws_Drama_Orders::create(
			array(
				'user_id'     => get_current_user_id(),
				'kind'        => $is_plan ? 'vip' : 'coins',
				'label'       => $is_plan
					? $item['name']
					: sprintf(
						/* translators: %s: coin amount */
						esc_html__( '%s coins', 'jws_streamvid' ),
						number_format_i18n( $item['total'] )
					),
				'coins'       => $is_plan ? 0 : $item['total'],
				'amount'      => $is_plan ? $item['intro'] : $item['price'],
				'currency'    => $currency,
				'gateway'     => $gateway,
				'method'      => $method_key,
				'fingerprint' => $item['fingerprint'],
				'episode_id'  => $episode_id,
			)
		);

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Could not start the purchase.', 'jws_streamvid' ) ), 500 );
		}

		$order = Jws_Drama_Orders::find( $order_id );

		if ( 'paypal' === $gateway ) {

			$url = $is_plan
				? Jws_Drama_Paypal::create_subscription( $order, $item )
				: Jws_Drama_Paypal::create_order( $order );

			if ( is_wp_error( $url ) ) {
				Jws_Drama_Orders::mark_failed( $order_id );
				wp_send_json_error( array( 'message' => $url->get_error_message() ), 200 );
			}

			wp_send_json_success( array( 'flow' => 'redirect', 'orderId' => $order_id, 'redirect' => $url ) );
		}

		if ( 'wallet' === $method['flow'] ) {

			$intent = Jws_Drama_Stripe::wallet_intent( $order, $item, $is_plan ? 'vip' : 'coins' );

			if ( is_wp_error( $intent ) ) {
				Jws_Drama_Orders::mark_failed( $order_id );
				wp_send_json_error( array( 'message' => $intent->get_error_message() ), 200 );
			}

			wp_send_json_success(
				array(
					'flow'         => 'wallet',
					'orderId'      => $order_id,
					'clientSecret' => $intent['client_secret'],
					'amount'       => Jws_Drama_Stripe::minor_units( $order->amount, $currency ),
					'currency'     => strtolower( $currency ),
					'label'        => $order->label,
				)
			);
		}

		$url = Jws_Drama_Stripe::checkout_session( $order, $item, $is_plan ? 'vip' : 'coins' );

		if ( is_wp_error( $url ) ) {
			Jws_Drama_Orders::mark_failed( $order_id );
			wp_send_json_error( array( 'message' => $url->get_error_message() ), 200 );
		}

		wp_send_json_success( array( 'flow' => 'redirect', 'orderId' => $order_id, 'redirect' => $url ) );
	}

	/**
	 * Spends coins on one episode and hands back the now-playable stage.
	 *
	 * The charge itself lives in Jws_Drama_Wallet::unlock(), which is what makes
	 * the price, the membership bypass and the double-charge guard identical
	 * whether the request came from here, the app or anywhere else.
	 */
	public function unlock() {

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please sign in first.', 'jws_streamvid' ) ), 401 );
		}

		check_ajax_referer( 'jws_drama_unlock', 'nonce' );

		$episode_id = isset( $_POST['episode_id'] ) ? absint( $_POST['episode_id'] ) : 0;

		if ( ! $episode_id || Jws_Drama_Post_Types::EPISODE !== get_post_type( $episode_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unknown episode.', 'jws_streamvid' ) ), 400 );
		}

		$result   = Jws_Drama_Wallet::unlock( $episode_id );
		$drama_id = Jws_Drama_Wallet::drama_id_of( $episode_id );

		if ( empty( $result['success'] ) ) {

			$messages = array(
				'insufficient_coins' => esc_html__( 'Not enough coins.', 'jws_streamvid' ),
				'not_logged_in'      => esc_html__( 'Please sign in first.', 'jws_streamvid' ),
				'invalid'            => esc_html__( 'This episode cannot be unlocked.', 'jws_streamvid' ),
			);

			wp_send_json_error(
				array(
					'reason'  => $result['reason'],
					'balance' => $result['balance'],
					'message' => isset( $messages[ $result['reason'] ] ) ? $messages[ $result['reason'] ] : esc_html__( 'Could not unlock.', 'jws_streamvid' ),
				),
				200
			);
		}

		global $post;

		$post = get_post( $episode_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		setup_postdata( $post );

		ob_start();
		jws_streamvid_load_template(
			'../includes/drama/templates/parts/stage.php',
			false,
			array( 'drama_id' => $drama_id, 'episode_id' => $episode_id )
		);
		$stage = ob_get_clean();

		wp_reset_postdata();

		wp_send_json_success(
			array(
				'stage'     => $stage,
				'episodeId' => $episode_id,
				'balance'   => $result['balance'],
				'message'   => esc_html__( 'Episode unlocked.', 'jws_streamvid' ),
			)
		);
	}

	/**
	 * Returns everything the page needs to show a different episode: the stage
	 * markup, and the few strings outside it that mention the episode number.
	 *
	 * Read-only and identical for every visitor of the same episode, so there is
	 * no nonce — one would only break under page caching. What matters is that
	 * the id is validated and that the locked/unlocked decision is taken here on
	 * the server, exactly as it is on a normal page load.
	 */
	public function episode() {

		$episode_id = isset( $_POST['episode_id'] ) ? absint( $_POST['episode_id'] ) : 0;

		if ( ! $episode_id || Jws_Drama_Post_Types::EPISODE !== get_post_type( $episode_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unknown episode.', 'jws_streamvid' ) ), 400 );
		}

		if ( 'publish' !== get_post_status( $episode_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Episode is not available.', 'jws_streamvid' ) ), 404 );
		}

		$drama_id = Jws_Drama_Wallet::drama_id_of( $episode_id );

		if ( ! $drama_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Episode is not linked to a drama.', 'jws_streamvid' ) ), 400 );
		}

		/* The watch screen warms the episode either side of the one playing, and
		   says so here. Same markup either way — what it changes is that a stage
		   nobody has opened does not become the drama's resume point. */
		$prefetch = ! empty( $_POST['prefetch'] );

		wp_send_json_success( $this->episode_payload( $episode_id, $drama_id, $prefetch ) );
	}

	/**
	 * Everything the watch screen needs to be showing one episode: the stage
	 * markup and the few strings outside it that name the episode.
	 *
	 * Shared by the episode switch and by the ad claim below, so a stage opened
	 * by an ad arrives in exactly the shape the switch already knows how to
	 * apply — and so an access decision taken between the two can never be
	 * described two different ways.
	 *
	 * @param int  $episode_id
	 * @param int  $drama_id
	 * @param bool $prefetch Whether this is a neighbour being warmed rather than
	 *   an episode the viewer opened.
	 * @return array
	 */
	private function episode_payload( $episode_id, $drama_id, $prefetch = false ) {

		$number = Jws_Drama_Wallet::episode_number( $episode_id );

		/*
		 * The template parts read the loop, not just their arguments — the player
		 * pulls ACF fields and the excerpt off the current post. Set the loop up
		 * the way a real request would.
		 */
		global $post;

		$post = get_post( $episode_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		setup_postdata( $post );

		ob_start();
		jws_streamvid_load_template(
			'../includes/drama/templates/parts/stage.php',
			false,
			array( 'drama_id' => $drama_id, 'episode_id' => $episode_id, 'prefetch' => $prefetch )
		);
		$stage = ob_get_clean();

		wp_reset_postdata();

		$access = Jws_Drama_Wallet::access( $episode_id );

		return array(
			'stage'     => $stage,
			'episodeId' => $episode_id,
			'dramaId'   => $drama_id,
			'number'    => $number,
			'permalink' => get_permalink( $episode_id ),
			/* translators: %d: episode number */
			'crumb'     => sprintf( __( 'Episode %d', 'jws_streamvid' ), $number ),
			'title'     => html_entity_decode( get_the_title( $drama_id ), ENT_QUOTES, 'UTF-8' ) . ' – ' . sprintf( __( 'Episode %d', 'jws_streamvid' ), $number ),
			'locked'    => empty( $access['can_watch'] ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Ad unlock                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Issues the ticket that a visit to the advertiser's link is redeemed with.
	 *
	 * Answers with the wait it will be held to, so the button can count the
	 * same seconds the server is going to check.
	 */
	public function ad_start() {

		check_ajax_referer( 'jws_drama_ad', 'nonce' );

		$episode_id = isset( $_POST['episode_id'] ) ? absint( $_POST['episode_id'] ) : 0;
		$result     = Jws_Drama_Ad_Unlock::start( $episode_id );

		if ( empty( $result['success'] ) ) {
			wp_send_json_error(
				array(
					'reason'  => $result['reason'],
					'message' => self::ad_message( $result['reason'] ),
				),
				200
			);
		}

		wp_send_json_success(
			array(
				'token'   => $result['token'],
				'seconds' => Jws_Drama_Ad_Unlock::seconds(),
			)
		);
	}

	/**
	 * Redeems the ticket and hands back the episode, playable.
	 *
	 * The grant lasts as long as this request, which is exactly long enough to
	 * render the stage below — after that the only copy of it is the markup the
	 * page is holding.
	 */
	public function ad_claim() {

		check_ajax_referer( 'jws_drama_ad', 'nonce' );

		$episode_id = isset( $_POST['episode_id'] ) ? absint( $_POST['episode_id'] ) : 0;
		$token      = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';

		if ( ! $episode_id || Jws_Drama_Post_Types::EPISODE !== get_post_type( $episode_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unknown episode.', 'jws_streamvid' ) ), 400 );
		}

		$drama_id = Jws_Drama_Wallet::drama_id_of( $episode_id );

		if ( ! $drama_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'This episode cannot be unlocked.', 'jws_streamvid' ) ), 400 );
		}

		$result = Jws_Drama_Ad_Unlock::claim( $episode_id, $token );

		if ( empty( $result['success'] ) ) {
			wp_send_json_error(
				array(
					'reason'  => $result['reason'],
					'message' => self::ad_message( $result['reason'] ),
				),
				200
			);
		}

		$payload            = $this->episode_payload( $episode_id, $drama_id );
		$payload['message'] = esc_html__( 'Episode unlocked for this visit.', 'jws_streamvid' );

		wp_send_json_success( $payload );
	}

	/** Why an ad unlock did not happen, in words the viewer can act on. */
	private static function ad_message( $reason ) {

		$messages = array(
			'disabled'    => esc_html__( 'This is not available right now.', 'jws_streamvid' ),
			'invalid'     => esc_html__( 'Unknown episode.', 'jws_streamvid' ),
			'not_locked'  => esc_html__( 'This episode is already open.', 'jws_streamvid' ),
			'daily_limit' => esc_html__( "That is all the episodes ads can open today. Come back tomorrow, or unlock this one with coins.", 'jws_streamvid' ),
			'expired'     => esc_html__( 'That took too long — please try again.', 'jws_streamvid' ),
			'mismatch'    => esc_html__( 'That took too long — please try again.', 'jws_streamvid' ),
			'too_soon'    => esc_html__( 'Please give the ad a moment longer.', 'jws_streamvid' ),
		);

		return isset( $messages[ $reason ] )
			? $messages[ $reason ]
			: esc_html__( 'Could not unlock. Please try again.', 'jws_streamvid' );
	}
}
