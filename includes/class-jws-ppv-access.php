<?php

/**
 * Pay-per-view entitlements: who may watch what they bought or rented.
 *
 * Backed by {@see Jws_Streamvid_Tables::table_ppv_access()}, replacing the
 * `jws_purchased_videos` and `jws_rented_videos` usermeta arrays.
 *
 * Everything that used to read those arrays now asks this class instead, which
 * matters more than the storage change did. The rental rule — a delay window in
 * which to start watching, then a fixed number of days once you do — used to be
 * re-implemented at each of the three places that needed it (the theme's access
 * check, the app's post-access endpoint, the app's rental list), and the three
 * had drifted apart: the web materialised a lapsed delay window and then denied
 * that same request, while the app had no way to materialise one at all and so
 * quietly dropped a rental the moment its delay window closed, before a single
 * paid day had been used. One implementation cannot drift from itself.
 *
 * The rule, stated once:
 *
 *   - A purchase never lapses.
 *   - A rental not yet started may be watched for as long as its delay window
 *     is open. Starting it — the player reporting playback, via start_rent() —
 *     sets the clock running for `rent_days`.
 *   - A rental whose delay window closes without being started starts by
 *     itself, at the moment the window closed, and runs `rent_days` from
 *     there. This is the rental the customer paid for either way; leaving it
 *     unstarted forever would hand out unlimited access to anyone who never
 *     pressed play, and expiring it outright would take away days nobody used.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_PPV_Access {

	const TYPE_BUY  = 'buy';
	const TYPE_RENT = 'rent';

	const STATUS_ACTIVE   = 'active';
	const STATUS_REFUNDED = 'refunded';

	/** The arrays this table replaced. Still read, once, per user. */
	const META_BUY  = 'jws_purchased_videos';
	const META_RENT = 'jws_rented_videos';

	/**
	 * Set on a user once their legacy arrays have been imported.
	 *
	 * This flag is why a refund sticks. The old arrays are deliberately left in
	 * place as a backup, so without a marker saying "this user is done" the
	 * lazy import would keep finding a refunded purchase still listed there and
	 * keep handing the access back.
	 */
	const META_MIGRATED = '_jws_ppv_migrated';

	/** Fallbacks for rentals sold before the title carried its own terms. */
	const DEFAULT_DELAY_DAYS = 3;
	const DEFAULT_RENT_DAYS  = 2;

	/**
	 * All of one user's rows, keyed "{post_id}:{type}", per request.
	 *
	 * A title page checks access for every card on it, so the reads come in
	 * bursts for one user. Loading that user's rows once keeps those bursts at
	 * the single query the usermeta array used to cost, while the writes stay
	 * row-level and atomic. Every write flushes the user it touched.
	 *
	 * @var array<int, array<string, object>>
	 */
	private static $cache = array();

	/* ---------------------------------------------------------------------- */
	/* Reading                                                                 */
	/* ---------------------------------------------------------------------- */

	public static function has_buy( $user_id, $post_id ) {
		return null !== self::find( $user_id, $post_id, self::TYPE_BUY );
	}

	/**
	 * How a rental stands right now, decided without writing anything.
	 *
	 * This is the rental rule itself, in one pure function, so that the access
	 * gate, the account page and the app's list cannot disagree about the same
	 * rental — which is exactly what went wrong when each of them carried its
	 * own copy of this arithmetic.
	 *
	 * @param object $row A rental row.
	 * @return array {
	 *     @type string $status     pending (unplayed, delay window open),
	 *                             active, or expired.
	 *     @type string $expires_at When it lapses, '' while still pending.
	 *     @type bool   $active     Whether it may be watched right now.
	 * }
	 */
	public static function rent_state( $row ) {

		$now = self::now();

		if ( null === $row->starts_at ) {

			$window_closes = self::ts( $row->purchased_at ) + ( (int) $row->delay_days * DAY_IN_SECONDS );

			/* Bought but not yet played, and still free to start whenever. */
			if ( $window_closes > $now ) {
				return array( 'status' => 'pending', 'expires_at' => '', 'active' => true );
			}

			/* The window closed unused, so the rental has effectively been
			   running since it closed. has_rent() is what commits that. */
			$expires = $window_closes + ( (int) $row->rent_days * DAY_IN_SECONDS );

			return array(
				'status'     => $expires > $now ? 'active' : 'expired',
				'expires_at' => self::at( $expires ),
				'active'     => $expires > $now,
			);
		}

		$expires = self::ts( $row->expires_at );

		return array(
			'status'     => $expires > $now ? 'active' : 'expired',
			'expires_at' => (string) $row->expires_at,
			'active'     => $expires > $now,
		);
	}

	/**
	 * Whether a rental may be watched right now, committing the start its dates
	 * already imply.
	 *
	 * This is the gate that grants playback, so it is the one that writes: once
	 * a lapsed delay window has been turned into real dates, the expiry stops
	 * being recomputed on every read and becomes something the reminder query
	 * and any cleanup cron can actually see.
	 */
	public static function has_rent( $user_id, $post_id ) {

		$row = self::find( $user_id, $post_id, self::TYPE_RENT );

		if ( ! $row ) {
			return false;
		}

		$state = self::rent_state( $row );

		if ( null === $row->starts_at && 'pending' !== $state['status'] ) {
			self::start(
				$user_id,
				$post_id,
				self::ts( $state['expires_at'] ) - ( (int) $row->rent_days * DAY_IN_SECONDS )
			);
		}

		return $state['active'];
	}

	/** Either kind of access to a title, which is what a player gate asks. */
	public static function has_access( $user_id, $post_id ) {
		return self::has_buy( $user_id, $post_id ) || self::has_rent( $user_id, $post_id );
	}

	/**
	 * A rental's expiry in the shape the old array used: a mysql datetime once
	 * the clock is running, the literal 'never' while it is not, '' when there
	 * is no rental. Kept for the profile table and the player's countdown,
	 * which both already branch on 'never'.
	 */
	public static function rent_expire( $user_id, $post_id ) {

		$row = self::find( $user_id, $post_id, self::TYPE_RENT );

		if ( ! $row ) {
			return '';
		}

		$state = self::rent_state( $row );

		return 'pending' === $state['status'] ? 'never' : $state['expires_at'];
	}

	/**
	 * One entitlement row, or null. Reads come from the per-request cache.
	 *
	 * @return object|null
	 */
	public static function find( $user_id, $post_id, $type ) {

		$rows = self::rows( $user_id );
		$key  = (int) $post_id . ':' . $type;

		return isset( $rows[ $key ] ) ? $rows[ $key ] : null;
	}

	/**
	 * A user's entitlements of one kind, newest purchase first.
	 *
	 * Ordering, limiting and counting happen in SQL — the old code read every
	 * entitlement a customer had ever bought out of a serialized blob and then
	 * sorted it in PHP, which is also why the account pages and the app's lists
	 * could not be paginated at all.
	 *
	 * @param array $args limit, offset.
	 * @return object[]
	 */
	public static function list_for_user( $user_id, $type, $args = array() ) {

		global $wpdb;

		$user_id = (int) $user_id;

		if ( $user_id <= 0 ) {
			return array();
		}

		self::ensure_user_migrated( $user_id );

		$args = wp_parse_args( $args, array( 'limit' => 0, 'offset' => 0 ) );

		$table = self::table();
		$sql   = $wpdb->prepare(
			"SELECT * FROM {$table}
			 WHERE user_id = %d AND type = %s AND status = %s
			 ORDER BY purchased_at DESC, id DESC",
			$user_id,
			$type,
			self::STATUS_ACTIVE
		);

		if ( (int) $args['limit'] > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', (int) $args['limit'], max( 0, (int) $args['offset'] ) );
		}

		return $wpdb->get_results( $sql );
	}

	public static function count_for_user( $user_id, $type ) {

		global $wpdb;

		$user_id = (int) $user_id;

		if ( $user_id <= 0 ) {
			return 0;
		}

		self::ensure_user_migrated( $user_id );

		$table = self::table();

		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND type = %s AND status = %s",
			$user_id,
			$type,
			self::STATUS_ACTIVE
		) );
	}

	/**
	 * Everyone currently entitled to a title.
	 *
	 * The question the old storage could not answer at all: a serialized array
	 * per user has no index that runs from a post back to its buyers, so "who
	 * bought this" meant unserializing every row of usermeta. Reports, per-title
	 * sales figures and "your rental lapses tomorrow" notifications all needed
	 * it.
	 *
	 * @return object[]
	 */
	public static function list_for_post( $post_id, $type = '', $args = array() ) {

		global $wpdb;

		$args  = wp_parse_args( $args, array( 'limit' => 0, 'offset' => 0 ) );
		$table = self::table();

		$where  = $wpdb->prepare( 'post_id = %d AND status = %s', (int) $post_id, self::STATUS_ACTIVE );
		$where .= $type ? $wpdb->prepare( ' AND type = %s', $type ) : '';

		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY purchased_at DESC, id DESC";

		if ( (int) $args['limit'] > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', (int) $args['limit'], max( 0, (int) $args['offset'] ) );
		}

		return $wpdb->get_results( $sql );
	}

	/**
	 * Rentals whose clock is running out inside the next `$hours`.
	 *
	 * Nothing calls this yet — it is the point of `KEY expiring`, and the one
	 * query a reminder notification needs.
	 *
	 * @return object[]
	 */
	public static function expiring_within( $hours = 24 ) {

		global $wpdb;

		$table = self::table();
		$now   = self::now();

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table}
			 WHERE type = %s AND status = %s
			   AND expires_at IS NOT NULL
			   AND expires_at > %s AND expires_at <= %s
			 ORDER BY expires_at ASC",
			self::TYPE_RENT,
			self::STATUS_ACTIVE,
			self::at( $now ),
			self::at( $now + ( (int) $hours * HOUR_IN_SECONDS ) )
		) );
	}

	/* ---------------------------------------------------------------------- */
	/* Writing                                                                 */
	/* ---------------------------------------------------------------------- */

	/**
	 * Records a permanent purchase.
	 *
	 * Buying something already owned leaves the original row alone, including
	 * its purchase date and invoice reference — re-running fulfillment must not
	 * restamp a purchase made a year ago. A row that was refunded, though, is
	 * revived by the new sale and takes the new sale's details.
	 *
	 * @param array $args order_id, order_number, price, currency, purchased_at.
	 */
	public static function grant_buy( $user_id, $post_id, $args = array() ) {

		global $wpdb;

		$user_id = (int) $user_id;
		$post_id = (int) $post_id;

		if ( $user_id <= 0 || $post_id <= 0 ) {
			return false;
		}

		self::ensure_user_migrated( $user_id );

		$args = wp_parse_args( $args, array(
			'order_id'     => 0,
			'order_number' => '',
			'price'        => 0,
			'currency'     => '',
			'purchased_at' => self::now_mysql(),
		) );

		$table = self::table();

		/*
		 * IF(status = 'active', …) is what keeps an existing purchase untouched
		 * while still reviving a refunded one, in a single statement — the two
		 * cases cannot be told apart before the insert is attempted, and
		 * checking first would leave a window for a second sale to slip in.
		 */
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$table}
				(user_id, post_id, type, order_id, order_number, price, currency, purchased_at, starts_at, expires_at, status)
			 VALUES (%d, %d, %s, %d, %s, %f, %s, %s, NULL, NULL, %s)
			 ON DUPLICATE KEY UPDATE
				order_id     = IF(status = 'active', order_id, VALUES(order_id)),
				order_number = IF(status = 'active', order_number, VALUES(order_number)),
				price        = IF(status = 'active', price, VALUES(price)),
				currency     = IF(status = 'active', currency, VALUES(currency)),
				purchased_at = IF(status = 'active', purchased_at, VALUES(purchased_at)),
				status       = VALUES(status)",
			$user_id,
			$post_id,
			self::TYPE_BUY,
			(int) $args['order_id'],
			(string) $args['order_number'],
			(float) $args['price'],
			(string) $args['currency'],
			(string) $args['purchased_at'],
			self::STATUS_ACTIVE
		) );

		self::flush( $user_id );

		return true;
	}

	/**
	 * Records a rental, with its clock not yet running.
	 *
	 * Renting something again restarts it from scratch — the delay window
	 * reopens and `starts_at` goes back to NULL — which is what renting again
	 * means and what overwriting the array entry used to do.
	 *
	 * @param array $args order_id, order_number, price, currency, delay_days,
	 *                    rent_days, purchased_at.
	 */
	public static function grant_rent( $user_id, $post_id, $args = array() ) {

		global $wpdb;

		$user_id = (int) $user_id;
		$post_id = (int) $post_id;

		if ( $user_id <= 0 || $post_id <= 0 ) {
			return false;
		}

		self::ensure_user_migrated( $user_id );

		$args = wp_parse_args( $args, array(
			'order_id'     => 0,
			'order_number' => '',
			'price'        => 0,
			'currency'     => '',
			'delay_days'   => self::default_delay_days(),
			'rent_days'    => self::default_rent_days( $post_id ),
			'purchased_at' => self::now_mysql(),
		) );

		$table = self::table();

		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$table}
				(user_id, post_id, type, order_id, order_number, price, currency, delay_days, rent_days, purchased_at, starts_at, expires_at, status)
			 VALUES (%d, %d, %s, %d, %s, %f, %s, %d, %d, %s, NULL, NULL, %s)
			 ON DUPLICATE KEY UPDATE
				order_id     = VALUES(order_id),
				order_number = VALUES(order_number),
				price        = VALUES(price),
				currency     = VALUES(currency),
				delay_days   = VALUES(delay_days),
				rent_days    = VALUES(rent_days),
				purchased_at = VALUES(purchased_at),
				starts_at    = NULL,
				expires_at   = NULL,
				status       = VALUES(status)",
			$user_id,
			$post_id,
			self::TYPE_RENT,
			(int) $args['order_id'],
			(string) $args['order_number'],
			(float) $args['price'],
			(string) $args['currency'],
			max( 0, (int) $args['delay_days'] ),
			max( 0, (int) $args['rent_days'] ),
			(string) $args['purchased_at'],
			self::STATUS_ACTIVE
		) );

		self::flush( $user_id );

		return true;
	}

	/**
	 * Starts a rental's clock because playback has begun.
	 *
	 * Returns false when there is nothing to start — no rental, or one already
	 * running — so a player that reports every play does not keep pushing the
	 * expiry date forward.
	 */
	public static function start_rent( $user_id, $post_id ) {

		$row = self::find( $user_id, $post_id, self::TYPE_RENT );

		if ( ! $row || null !== $row->starts_at ) {
			return false;
		}

		return null !== self::start( $user_id, $post_id, self::now() );
	}

	/**
	 * Marks an entitlement refunded, which is how access is taken away.
	 *
	 * The row stays, so a refund is still visible next to the sale it reversed
	 * and a title's refund rate remains a query rather than an archaeology
	 * project. Reads filter on `status`, so a refunded row grants nothing.
	 *
	 * Pass the order being refunded, and only the entitlement that order
	 * granted is revoked. That matters because a gateway's refund can arrive
	 * days after the sale: someone rents a film, rents it again next week, and
	 * then the first rental is refunded — the row now belongs to the second,
	 * paid-for rental, and taking it away would refund one payment and confiscate
	 * the other. The old array could not tell the two apart; the row can.
	 * Without an order, whatever is there is revoked.
	 *
	 * @param int    $order_id     Ledger id of the order being refunded.
	 * @param string $order_number Its customer-facing number.
	 */
	public static function revoke( $user_id, $post_id, $type, $order_id = 0, $order_number = '' ) {

		global $wpdb;

		$user_id = (int) $user_id;

		if ( $user_id <= 0 ) {
			return false;
		}

		self::ensure_user_migrated( $user_id );

		$table = self::table();
		$where = $wpdb->prepare(
			'user_id = %d AND post_id = %d AND type = %s',
			$user_id,
			(int) $post_id,
			$type
		);

		if ( (int) $order_id > 0 || '' !== (string) $order_number ) {

			/*
			 * Every way this order may have been written down: its ledger id
			 * (what fulfillment stores now), its number (what migrated rows
			 * carry), and the two older forms the ledger's retag pass also
			 * recognises — `jws-<id>` and the bare id.
			 */
			$references = array_filter( array(
				(string) $order_number,
				(int) $order_id > 0 ? Jws_Payment_Invoice::LEGACY_PREFIX . (int) $order_id : '',
				(int) $order_id > 0 ? (string) (int) $order_id : '',
			), 'strlen' );

			$placeholders = implode( ',', array_fill( 0, count( $references ), '%s' ) );

			$where .= $wpdb->prepare(
				" AND ( order_id = %d OR order_number IN ({$placeholders}) )",
				array_merge( array( (int) $order_id ), array_values( $references ) )
			);
		}

		$updated = $wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET status = %s WHERE {$where}",
			self::STATUS_REFUNDED
		) );

		self::flush( $user_id );

		return (bool) $updated;
	}

	/**
	 * Repoints an entitlement at the customer-facing order number.
	 *
	 * Used by the ledger's one-off pass over references written before order
	 * numbers existed. `$recognised` lists the values that pass may replace, so
	 * a number that happens to match an unrelated WooCommerce order is left
	 * alone.
	 *
	 * @return bool Whether a row was changed.
	 */
	public static function retag_order_number( $user_id, $post_id, $type, $order_number, $order_id, array $recognised ) {

		global $wpdb;

		if ( empty( $recognised ) ) {
			return false;
		}

		/* The row has to exist before it can be retagged, and on a site whose
		   bulk migration has not run yet it only exists once this user has been
		   imported. */
		self::ensure_user_migrated( $user_id );

		$table        = self::table();
		$placeholders = implode( ',', array_fill( 0, count( $recognised ), '%s' ) );

		$params = array_merge(
			array( (string) $order_number, (int) $order_id, (int) $user_id, (int) $post_id, (string) $type ),
			array_map( 'strval', $recognised )
		);

		$updated = $wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET order_number = %s, order_id = %d
			 WHERE user_id = %d AND post_id = %d AND type = %s
			   AND order_number IN ({$placeholders})",
			$params
		) );

		if ( $updated ) {
			self::flush( $user_id );
		}

		return (bool) $updated;
	}

	/* ---------------------------------------------------------------------- */
	/* Migration from the usermeta arrays                                      */
	/* ---------------------------------------------------------------------- */

	/**
	 * Imports one user's legacy arrays the first time anything asks about them.
	 *
	 * The bulk migration on the Sync Data Tables page is the proper way to move
	 * a site over; this is the safety net for the ones it has not reached yet —
	 * a big site where the admin has not run it, or has not finished. It costs
	 * one flag read per user per request, against usermeta WordPress has already
	 * loaded, and one write ever.
	 */
	public static function ensure_user_migrated( $user_id ) {

		$user_id = (int) $user_id;

		if ( $user_id <= 0 || get_user_meta( $user_id, self::META_MIGRATED, true ) ) {
			return;
		}

		/* Only marked done once the rows have actually landed. The import is a
		   single INSERT, so it either all lands or none of it does — and if it
		   failed (the table missing, the database away), marking the user done
		   anyway would strand every title they paid for in the old meta, where
		   nothing reads it any more. Left unmarked, the next request retries. */
		if ( false !== self::import_user( $user_id ) ) {
			update_user_meta( $user_id, self::META_MIGRATED, 1 );
		}
	}

	/**
	 * Copies every legacy array into the table.
	 *
	 * Safe to run repeatedly: INSERT IGNORE against the unique key means a row
	 * already present is left exactly as it is, so a rental someone has since
	 * started is not rewound. The old usermeta is never touched, and stays as a
	 * backup until the admin clears it.
	 *
	 * @return array { migrated: int, users: int, failed: int }
	 */
	public static function migrate_from_meta( $batch = 200 ) {

		global $wpdb;

		$result = array( 'migrated' => 0, 'users' => 0, 'failed' => 0 );

		$user_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key IN (%s, %s)",
			self::META_BUY,
			self::META_RENT
		) );

		foreach ( array_chunk( array_map( 'intval', $user_ids ), (int) $batch ) as $chunk ) {

			foreach ( $chunk as $user_id ) {
				$imported = self::import_user( $user_id );
				$result['users']++;

				/* See ensure_user_migrated(): never mark done what did not land. */
				if ( false === $imported ) {
					$result['failed']++;
					continue;
				}

				$result['migrated'] += $imported;
				update_user_meta( $user_id, self::META_MIGRATED, 1 );
			}

			/* Each chunk of users pulled their meta into the cache; a site with
			   tens of thousands of them would otherwise run out of memory
			   before it ran out of users. */
			if ( function_exists( 'wp_cache_flush_group' ) ) {
				wp_cache_flush_group( 'user_meta' );
			}
		}

		return $result;
	}

	/**
	 * Imports one user's two arrays.
	 *
	 * @return int|false Rows inserted, or false if the insert failed.
	 */
	private static function import_user( $user_id ) {

		global $wpdb;

		$user_id = (int) $user_id;
		$table   = self::table();
		$values  = array();

		$bought = get_user_meta( $user_id, self::META_BUY, true );

		if ( is_array( $bought ) ) {
			foreach ( $bought as $post_id => $entry ) {

				$post_id = (int) $post_id;

				if ( $post_id <= 0 || ! is_array( $entry ) ) {
					continue;
				}

				$values[] = $wpdb->prepare(
					'(%d,%d,%s,%d,%s,%f,%s,%d,%d,%s,NULL,NULL,%s)',
					$user_id,
					$post_id,
					self::TYPE_BUY,
					0,
					isset( $entry['order_id'] ) ? (string) $entry['order_id'] : '',
					isset( $entry['price'] ) ? (float) $entry['price'] : 0,
					'',
					0,
					0,
					isset( $entry['time'] ) ? (string) $entry['time'] : self::now_mysql(),
					self::STATUS_ACTIVE
				);
			}
		}

		$rented = get_user_meta( $user_id, self::META_RENT, true );

		if ( is_array( $rented ) ) {
			foreach ( $rented as $post_id => $entry ) {

				$post_id = (int) $post_id;

				if ( $post_id <= 0 || ! is_array( $entry ) ) {
					continue;
				}

				$purchased = isset( $entry['time'] ) ? (string) $entry['time'] : self::now_mysql();
				$rent_days = isset( $entry['day_rent'] ) ? (int) $entry['day_rent'] : 0;
				$expire    = isset( $entry['expire'] ) ? (string) $entry['expire'] : '';

				/*
				 * 'never' — and the empty string some older entries carry —
				 * meant "not started yet", which is `starts_at IS NULL` here.
				 *
				 * For a rental that had started, only the expiry was ever
				 * stored. Both places that wrote one computed it as start plus
				 * `day_rent`, so subtracting `day_rent` back off recovers the
				 * start exactly rather than guessing at it.
				 */
				$starts_at  = 'NULL';
				$expires_at = 'NULL';

				if ( '' !== $expire && 'never' !== $expire ) {
					$expires_ts = self::ts( $expire );

					if ( $expires_ts > 0 ) {
						$expires_at = "'" . self::at( $expires_ts ) . "'";
						$starts_at  = "'" . self::at( $expires_ts - ( $rent_days * DAY_IN_SECONDS ) ) . "'";
					}
				}

				$values[] = $wpdb->prepare(
					"(%d,%d,%s,%d,%s,%f,%s,%d,%d,%s,{$starts_at},{$expires_at},%s)",
					$user_id,
					$post_id,
					self::TYPE_RENT,
					0,
					isset( $entry['order_id'] ) ? (string) $entry['order_id'] : '',
					isset( $entry['price'] ) ? (float) $entry['price'] : 0,
					'',
					isset( $entry['delay'] ) ? (int) $entry['delay'] : 0,
					$rent_days,
					$purchased,
					self::STATUS_ACTIVE
				);
			}
		}

		if ( empty( $values ) ) {
			return 0;
		}

		$inserted = $wpdb->query(
			"INSERT IGNORE INTO {$table}
				(user_id, post_id, type, order_id, order_number, price, currency, delay_days, rent_days, purchased_at, starts_at, expires_at, status)
			 VALUES " . implode( ',', $values )
		);

		self::flush( $user_id );

		return false === $inserted ? false : (int) $inserted;
	}

	/* ---------------------------------------------------------------------- */
	/* Internals                                                               */
	/* ---------------------------------------------------------------------- */

	private static function table() {
		return Jws_Streamvid_Tables::table_ppv_access();
	}

	/**
	 * One user's active entitlements, loaded once per request.
	 *
	 * @return array<string, object>
	 */
	private static function rows( $user_id ) {

		global $wpdb;

		$user_id = (int) $user_id;

		if ( $user_id <= 0 ) {
			return array();
		}

		if ( isset( self::$cache[ $user_id ] ) ) {
			return self::$cache[ $user_id ];
		}

		self::ensure_user_migrated( $user_id );

		$table = self::table();

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE user_id = %d AND status = %s",
			$user_id,
			self::STATUS_ACTIVE
		) );

		$keyed = array();

		foreach ( (array) $rows as $row ) {
			$keyed[ (int) $row->post_id . ':' . $row->type ] = $row;
		}

		self::$cache[ $user_id ] = $keyed;

		return $keyed;
	}

	private static function flush( $user_id ) {
		unset( self::$cache[ (int) $user_id ] );
	}

	/**
	 * Sets a rental's clock, but only if nothing else already has.
	 *
	 * `WHERE starts_at IS NULL` is the whole point: the player's start report
	 * and an access check that finds the delay window closed can arrive at the
	 * same instant, and whichever lands first is the one that counts. The row
	 * is read back afterwards so the caller sees the value that won rather than
	 * the one it tried to write.
	 *
	 * @return object|null The rental as it now stands.
	 */
	private static function start( $user_id, $post_id, $start_ts ) {

		global $wpdb;

		$row = self::find( $user_id, $post_id, self::TYPE_RENT );

		if ( ! $row ) {
			return null;
		}

		$table = self::table();

		$wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET starts_at = %s, expires_at = %s
			 WHERE id = %d AND starts_at IS NULL",
			self::at( $start_ts ),
			self::at( $start_ts + ( (int) $row->rent_days * DAY_IN_SECONDS ) ),
			(int) $row->id
		) );

		self::flush( $user_id );

		return self::find( $user_id, $post_id, self::TYPE_RENT );
	}

	/** How long a customer has to start watching, site-wide. */
	public static function default_delay_days() {

		$delay = function_exists( 'jws_theme_get_option' ) ? jws_theme_get_option( 'rent_delay' ) : 0;

		return is_numeric( $delay ) && (int) $delay > 0 ? (int) $delay : self::DEFAULT_DELAY_DAYS;
	}

	/** How long a rental of this title lasts once started. */
	public static function default_rent_days( $post_id ) {

		$days = get_post_meta( (int) $post_id, 'rent_day', true );

		if ( is_numeric( $days ) && (int) $days > 0 ) {
			return (int) $days;
		}

		$days = function_exists( 'jws_theme_get_option' ) ? jws_theme_get_option( 'rent_days', self::DEFAULT_RENT_DAYS ) : 0;

		return is_numeric( $days ) && (int) $days > 0 ? (int) $days : self::DEFAULT_RENT_DAYS;
	}

	/*
	 * Time is kept on the site's clock throughout, the same one the old arrays
	 * were written on: current_time() for "now", and strtotime() to read a
	 * stored value back. Mixing in real UTC would shift every rental by the
	 * site's offset the first time one was compared against the other.
	 */

	private static function now() {
		return (int) current_time( 'timestamp' );
	}

	private static function now_mysql() {
		return current_time( 'mysql' );
	}

	private static function at( $timestamp ) {
		return gmdate( 'Y-m-d H:i:s', (int) $timestamp );
	}

	private static function ts( $datetime ) {
		return $datetime ? (int) strtotime( (string) $datetime ) : 0;
	}
}
