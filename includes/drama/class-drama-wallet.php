<?php

/**
 * Coin wallet and episode unlocking for short drama.
 *
 * This is the data layer only — no REST, no AJAX, no markup. Everything that
 * later decides "can this person watch this episode" goes through
 * can_watch()/unlock() here, so the rule lives in exactly one place whether it
 * is asked by the web templates, admin-ajax or the Flutter API.
 *
 * Balance is kept in usermeta for cheap reads and mirrored into the wallet log,
 * which is the audit trail.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/drama
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Drama_Wallet {

	const META_BALANCE = 'svt_drama_coins';

	/** Mirrors the Theme Options checkbox for a site running the plugin alone. */
	const OPTION_UNLOCK_LEVELS = 'jws_drama_unlock_levels';

	/* ---------------------------------------------------------------------- */
	/* Balance                                                                 */
	/* ---------------------------------------------------------------------- */

	public static function balance( $user_id = 0 ) {

		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		if ( ! $user_id ) {
			return 0;
		}

		return max( 0, (int) get_user_meta( $user_id, self::META_BALANCE, true ) );
	}

	/**
	 * Adds coins and records why.
	 *
	 * @param int    $user_id
	 * @param int    $amount  Positive number of coins.
	 * @param string $type    topup | bonus | refund | admin
	 * @param int    $ref_id  Order id, episode id — whatever the type implies.
	 * @param string $note
	 * @return int|false New balance, or false when nothing was credited.
	 */
	public static function credit( $user_id, $amount, $type = 'topup', $ref_id = 0, $note = '' ) {

		$user_id = (int) $user_id;
		$amount  = (int) $amount;

		if ( $user_id <= 0 || $amount <= 0 ) {
			return false;
		}

		$balance = self::balance( $user_id ) + $amount;

		update_user_meta( $user_id, self::META_BALANCE, $balance );
		self::log( $user_id, $amount, $balance, $type, $ref_id, $note );

		do_action( 'streamvid/drama/coins_credited', $user_id, $amount, $balance, $type, $ref_id );

		return $balance;
	}

	/**
	 * Spends coins. Refuses rather than going negative.
	 *
	 * @return int|false New balance, or false when the balance was too low.
	 */
	public static function debit( $user_id, $amount, $type = 'spend', $ref_id = 0, $note = '' ) {

		$user_id = (int) $user_id;
		$amount  = (int) $amount;

		if ( $user_id <= 0 || $amount <= 0 ) {
			return false;
		}

		$balance = self::balance( $user_id );

		if ( $balance < $amount ) {
			return false;
		}

		$balance -= $amount;

		update_user_meta( $user_id, self::META_BALANCE, $balance );
		self::log( $user_id, -$amount, $balance, $type, $ref_id, $note );

		do_action( 'streamvid/drama/coins_debited', $user_id, $amount, $balance, $type, $ref_id );

		return $balance;
	}

	private static function log( $user_id, $delta, $balance_after, $type, $ref_id, $note ) {

		global $wpdb;

		$wpdb->insert(
			Jws_Drama_Install::table_wallet_log(),
			array(
				'user_id'       => (int) $user_id,
				'delta'         => (int) $delta,
				'balance_after' => (int) $balance_after,
				'type'          => substr( (string) $type, 0, 20 ),
				'ref_id'        => (int) $ref_id,
				'note'          => substr( (string) $note, 0, 191 ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%d', '%s', '%s' )
		);
	}

	/** Most recent wallet movements, newest first. */
	public static function history( $user_id, $limit = 50, $offset = 0 ) {

		global $wpdb;

		$table = Jws_Drama_Install::table_wallet_log();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT %d OFFSET %d",
				(int) $user_id,
				(int) $limit,
				(int) $offset
			)
		);
	}

	/**
	 * Every episode this user has spent coins to unlock, newest first.
	 *
	 * Same ledger as history(), just narrowed to 'spend' rows — the account
	 * area wants a dedicated "Episodes Unlocked" table alongside the general
	 * activity feed, and filtering client-side would cut it short whenever a
	 * top-up or refund pushed a spend out of the mixed feed's own limit.
	 */
	public static function unlock_history( $user_id, $limit = 50 ) {

		global $wpdb;

		$table = Jws_Drama_Install::table_wallet_log();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND type = %s ORDER BY id DESC LIMIT %d",
				(int) $user_id,
				'spend',
				(int) $limit
			)
		);
	}

	/* ---------------------------------------------------------------------- */
	/* Episode pricing                                                         */
	/* ---------------------------------------------------------------------- */

	/**
	 * Site-wide default for one of the coin settings.
	 *
	 * Theme Options > Drama Short first, so the two numbers live where every
	 * other archive setting does; the plain option stays as a fallback for a
	 * site running the plugin without this theme.
	 */
	private static function default_setting( $settings_key, $theme_key, $option_key, $fallback ) {

		/* Jws Settings > Drama Coins owns these now. The two lookups below are
		   what a site that has not opened that screen yet still answers with;
		   Jws_Drama_Settings::maybe_migrate() copies them over on first sight. */
		if ( class_exists( 'Jws_Drama_Settings' ) ) {
			$value = Jws_Drama_Settings::get( $settings_key );

			if ( is_numeric( $value ) ) {
				return $value;
			}
		}

		if ( function_exists( 'jws_theme_get_option' ) ) {
			$value = jws_theme_get_option( $theme_key );

			/*
			 * is_numeric, not an emptiness test: jws_theme_get_option() answers
			 * `false` for a field Redux has not written yet, and `false` survives
			 * an `!== ''` check only to cast to 0 — which would lock every
			 * episode behind a price of zero coins.
			 */
			if ( is_numeric( $value ) ) {
				return $value;
			}
		}

		return get_option( $option_key, $fallback );
	}

	/** How many opening episodes of a drama are free to everyone. */
	public static function free_episodes( $drama_id ) {

		$free = get_post_meta( (int) $drama_id, 'drama_free_ep', true );

		if ( '' === $free || null === $free ) {
			$free = self::default_setting( 'free_episodes', 'drama_default_free_ep', 'jws_drama_default_free_ep', 3 );
		}

		return (int) apply_filters( 'streamvid/drama/free_episodes', max( 0, (int) $free ), $drama_id );
	}

	/** Coin price of one locked episode of a drama. */
	public static function coin_price( $drama_id ) {

		$price = get_post_meta( (int) $drama_id, 'drama_coin_per_ep', true );

		if ( '' === $price || null === $price ) {
			$price = self::default_setting( 'coin_price', 'drama_default_coin_per_ep', 'jws_drama_default_coin_per_ep', 10 );
		}

		return (int) apply_filters( 'streamvid/drama/coin_price', max( 0, (int) $price ), $drama_id );
	}

	/* ---------------------------------------------------------------------- */
	/* Access                                                                  */
	/* ---------------------------------------------------------------------- */

	public static function drama_id_of( $episode_id ) {
		return (int) get_post_meta( (int) $episode_id, 'drama_id', true );
	}

	public static function episode_number( $episode_id ) {

		$number = (int) get_post_meta( (int) $episode_id, 'drama_ep_number', true );

		if ( $number > 0 ) {
			return $number;
		}

		// menu_order is kept in step on save; fall back to it if the meta is gone.
		$post = get_post( (int) $episode_id );

		return $post ? (int) $post->menu_order : 0;
	}

	public static function is_unlocked( $user_id, $episode_id ) {

		global $wpdb;

		$user_id = (int) $user_id;

		if ( $user_id <= 0 ) {
			return false;
		}

		$table = Jws_Drama_Install::table_unlock();

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE user_id = %d AND episode_id = %d LIMIT 1",
				$user_id,
				(int) $episode_id
			)
		);
	}

	/** Episode ids of a drama this user has already paid for. */
	public static function unlocked_episodes( $user_id, $drama_id ) {

		global $wpdb;

		$user_id = (int) $user_id;

		if ( $user_id <= 0 ) {
			return array();
		}

		$table = Jws_Drama_Install::table_unlock();

		return array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT episode_id FROM {$table} WHERE user_id = %d AND drama_id = %d",
					$user_id,
					(int) $drama_id
				)
			)
		);
	}

	/**
	 * Every PMPro level as id => label, for the Theme Options checkbox.
	 *
	 * Free levels are labelled as such: "any active level unlocks everything"
	 * is exactly the setting that hands a $0 plan the whole catalogue, so the
	 * price belongs next to the box being ticked.
	 */
	public static function membership_level_choices() {

		$choices = array();

		if ( ! function_exists( 'pmpro_getAllLevels' ) ) {
			return $choices;
		}

		foreach ( (array) pmpro_getAllLevels( true ) as $level ) {

			if ( empty( $level->id ) ) {
				continue;
			}

			$label = $level->name;

			if ( ! self::level_is_paid( $level ) ) {
				$label .= ' (' . esc_html__( 'free', 'jws_streamvid' ) . ')';
			}

			$choices[ (int) $level->id ] = $label;
		}

		return $choices;
	}

	private static function level_is_paid( $level ) {
		return ( (float) $level->initial_payment > 0 ) || ( (float) $level->billing_amount > 0 );
	}

	/**
	 * What the checkbox starts on: the paid levels, none of the free ones.
	 *
	 * Shaped the way Redux stores a multi-option checkbox — id => '1' | '0' —
	 * so the saved value and the default read back through the same code.
	 */
	public static function default_unlock_levels() {

		$default = array();

		if ( ! function_exists( 'pmpro_getAllLevels' ) ) {
			return $default;
		}

		foreach ( (array) pmpro_getAllLevels( true ) as $level ) {

			if ( empty( $level->id ) ) {
				continue;
			}

			$default[ (int) $level->id ] = self::level_is_paid( $level ) ? '1' : '0';
		}

		return $default;
	}

	/**
	 * Ids of the membership levels that skip the coin wall.
	 *
	 * Jws Settings > Drama Coins first, then the older Theme Options value and
	 * the plain option for a site running the plugin alone, then the default of
	 * paid levels only.
	 * An unticked box is still a saved array, so a site that wants *no* level
	 * to bypass gets that rather than falling back to the default.
	 */
	public static function unlock_all_levels() {

		$stored = null;

		if ( class_exists( 'Jws_Drama_Settings' ) ) {
			$value = Jws_Drama_Settings::get( 'pmpro_levels' );

			if ( is_array( $value ) ) {
				$stored = $value;
			}
		}

		if ( null === $stored && function_exists( 'jws_theme_get_option' ) ) {
			$value = jws_theme_get_option( 'drama_unlock_levels' );

			if ( is_array( $value ) ) {
				$stored = $value;
			}
		}

		if ( null === $stored ) {
			$value = get_option( self::OPTION_UNLOCK_LEVELS, null );

			if ( is_array( $value ) ) {
				$stored = $value;
			}
		}

		if ( null === $stored ) {
			$stored = self::default_unlock_levels();
		}

		/*
		 * Redux hands back a map of id => '1' | '0'; a plain list of ids is
		 * accepted too, since that is the shape anyone setting the option by
		 * hand would reach for.
		 */
		$levels = array_is_list( $stored )
			? array_map( 'intval', $stored )
			: array_map( 'intval', array_keys( array_filter( $stored ) ) );

		$levels = array_values( array_unique( array_filter( $levels ) ) );

		return (array) apply_filters( 'streamvid/drama/unlock_all_levels', $levels );
	}

	/** Active level ids for a user — PMPro can hand out more than one. */
	private static function user_level_ids( $user_id ) {

		$ids = array();

		if ( function_exists( 'pmpro_getMembershipLevelsForUser' ) ) {
			foreach ( (array) pmpro_getMembershipLevelsForUser( $user_id ) as $level ) {
				$id = isset( $level->ID ) ? $level->ID : ( isset( $level->id ) ? $level->id : 0 );

				if ( $id ) {
					$ids[] = (int) $id;
				}
			}
		} elseif ( function_exists( 'pmpro_getMembershipLevelForUser' ) ) {
			$level = pmpro_getMembershipLevelForUser( $user_id );

			if ( ! empty( $level->ID ) ) {
				$ids[] = (int) $level->ID;
			}
		}

		return $ids;
	}

	/**
	 * Whether an active membership lets this person skip the coin wall.
	 *
	 * Only the levels ticked in Jws Settings > Drama Coins count. This used to
	 * be "any active level", which quietly handed the free plan every locked
	 * episode and made the free-episode limit look broken.
	 */
	public static function membership_unlocks_all( $user_id ) {

		$user_id = (int) $user_id;
		$unlocks = false;

		if ( $user_id > 0 ) {

			/* A VIP plan sold by this module comes first: it is one indexed
			   read, and it is the thing people actually buy here. */
			$unlocks = Jws_Drama_Subscriptions::is_active( $user_id );

			if ( ! $unlocks ) {

				$allowed = self::unlock_all_levels();

				if ( $allowed ) {
					$unlocks = (bool) array_intersect( self::user_level_ids( $user_id ), $allowed );
				}
			}
		}

		return (bool) apply_filters( 'streamvid/drama/membership_unlocks_all', $unlocks, $user_id );
	}

	/**
	 * The single access decision for one episode.
	 *
	 * @return array {
	 *     @type bool   $can_watch
	 *     @type string $reason     free | membership | unlocked | locked | invalid
	 *     @type int    $price      Coins needed when locked.
	 *     @type int    $balance
	 *     @type int    $number     Episode number within its drama.
	 * }
	 */
	public static function access( $episode_id, $user_id = null ) {

		$episode_id = (int) $episode_id;
		$user_id    = null === $user_id ? get_current_user_id() : (int) $user_id;
		$drama_id   = self::drama_id_of( $episode_id );

		$out = array(
			'can_watch' => false,
			'reason'    => 'invalid',
			'price'     => 0,
			'balance'   => self::balance( $user_id ),
			'number'    => self::episode_number( $episode_id ),
			'drama_id'  => $drama_id,
		);

		if ( ! $drama_id || get_post_type( $episode_id ) !== Jws_Drama_Post_Types::EPISODE ) {
			return $out;
		}

		$out['price'] = self::coin_price( $drama_id );

		// An episode can be opened up individually regardless of where it sits.
		$forced_free = get_post_meta( $episode_id, 'drama_ep_free', true );

		if ( $forced_free || $out['number'] <= self::free_episodes( $drama_id ) ) {
			$out['can_watch'] = true;
			$out['reason']    = 'free';
			$out['price']     = 0;
			return $out;
		}

		if ( self::membership_unlocks_all( $user_id ) ) {
			$out['can_watch'] = true;
			$out['reason']    = 'membership';
			return $out;
		}

		if ( self::is_unlocked( $user_id, $episode_id ) ) {
			$out['can_watch'] = true;
			$out['reason']    = 'unlocked';
			return $out;
		}

		$out['reason'] = 'locked';

		return apply_filters( 'streamvid/drama/access', $out, $episode_id, $user_id );
	}

	public static function can_watch( $episode_id, $user_id = null ) {
		$access = self::access( $episode_id, $user_id );
		return ! empty( $access['can_watch'] );
	}

	/**
	 * Spends coins to open one episode, permanently.
	 *
	 * Safe to call twice: an episode already open returns success without
	 * charging, and the unique key on (user_id, episode_id) is the backstop if
	 * two requests race.
	 *
	 * @return array { @type bool $success, @type string $reason, @type int $balance }
	 */
	public static function unlock( $episode_id, $user_id = null ) {

		global $wpdb;

		$episode_id = (int) $episode_id;
		$user_id    = null === $user_id ? get_current_user_id() : (int) $user_id;

		if ( $user_id <= 0 ) {
			return array( 'success' => false, 'reason' => 'not_logged_in', 'balance' => 0 );
		}

		$access = self::access( $episode_id, $user_id );

		if ( 'invalid' === $access['reason'] ) {
			return array( 'success' => false, 'reason' => 'invalid', 'balance' => $access['balance'] );
		}

		if ( $access['can_watch'] ) {
			return array( 'success' => true, 'reason' => $access['reason'], 'balance' => $access['balance'] );
		}

		$price = (int) $access['price'];

		if ( self::balance( $user_id ) < $price ) {
			return array( 'success' => false, 'reason' => 'insufficient_coins', 'balance' => $access['balance'] );
		}

		/*
		 * Write the unlock row first. It carries the unique key, so if two
		 * requests arrive together exactly one of them gets past this point and
		 * only that one goes on to charge.
		 */
		$inserted = $wpdb->insert(
			Jws_Drama_Install::table_unlock(),
			array(
				'user_id'    => $user_id,
				'episode_id' => $episode_id,
				'drama_id'   => (int) $access['drama_id'],
				'coins'      => $price,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%s' )
		);

		if ( ! $inserted ) {
			// Lost the race — the other request unlocked it, so this is a success.
			return array( 'success' => true, 'reason' => 'unlocked', 'balance' => self::balance( $user_id ) );
		}

		$balance = self::debit(
			$user_id,
			$price,
			'spend',
			$episode_id,
			sprintf( 'Unlock episode #%d', $access['number'] )
		);

		if ( false === $balance ) {
			// Balance moved under us; drop the row again rather than give it away.
			$wpdb->delete(
				Jws_Drama_Install::table_unlock(),
				array( 'user_id' => $user_id, 'episode_id' => $episode_id ),
				array( '%d', '%d' )
			);

			return array( 'success' => false, 'reason' => 'insufficient_coins', 'balance' => self::balance( $user_id ) );
		}

		do_action( 'streamvid/drama/episode_unlocked', $user_id, $episode_id, $access['drama_id'], $price );

		return array( 'success' => true, 'reason' => 'unlocked', 'balance' => $balance );
	}
}
