<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * Schema for the unified payment system, and the read side of its order table.
 *
 * Two tables:
 *
 *  - {prefix}jws_payment_orders — one row per purchase. Orders this checkout
 *    took itself start at `pending` and are moved to `completed` by
 *    Jws_Payment_Orders; orders taken elsewhere (a WooCommerce sale while the
 *    system is switched off, a PMPro renewal) are mirrored in as `completed`
 *    by Jws_Payment_Sync. Either way this is the one place to read "what did
 *    this user buy, from anywhere".
 *
 *  - {prefix}jws_payment_subscriptions — one row per recurring plan sold,
 *    whichever gateway is billing it. The renewal webhook writes
 *    `current_period_end`; PMPro still owns the level itself.
 */
class Jws_Payment_Ledger {

	const DB_VERSION        = '1.4.0';
	const OPTION_DB_VERSION = 'jws_payment_ledger_db_version';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'jws_payment_orders';
	}

	public static function table_subscriptions() {
		global $wpdb;
		return $wpdb->prefix . 'jws_payment_subscriptions';
	}

	/**
	 * The tables this system owns, and what each one is for.
	 *
	 * Keyed by table name so a caller can report on them without having to
	 * know which method produced which name.
	 */
	public static function tables() {

		return array(
			self::table()               => esc_html__( 'Every purchase, from any checkout — one row per order, from pending to paid.', 'jws_streamvid' ),
			self::table_subscriptions() => esc_html__( 'Recurring plans being billed, whichever gateway is billing them.', 'jws_streamvid' ),
		);
	}

	/**
	 * What actually exists in the database right now.
	 *
	 * Reported rather than assumed: the installer runs on admin_init and skips
	 * itself once the stored version matches, so a table dropped by hand — or
	 * a version bumped without the upgrade ever running — would otherwise be
	 * invisible until something failed to write.
	 *
	 * @return array One entry per table: name, purpose, exists, rows, columns.
	 */
	public static function table_report() {

		global $wpdb;

		$report = array();

		foreach ( self::tables() as $table => $purpose ) {

			$exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

			$report[] = array(
				'name'    => $table,
				'purpose' => $purpose,
				'exists'  => $exists,
				'rows'    => $exists ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) : 0,
				'columns' => $exists ? (array) $wpdb->get_results( "SHOW COLUMNS FROM {$table}" ) : array(),
			);
		}

		return $report;
	}

	/** Runs on every admin request but does nothing once the version matches. */
	public static function maybe_install() {

		if ( get_option( self::OPTION_DB_VERSION ) === self::DB_VERSION ) {
			return;
		}

		self::install();
	}

	public static function install() {

		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$orders  = self::table();
		$subs    = self::table_subscriptions();

		/*
		 * (source, source_ref) is unique so a WC order that fires "processing"
		 * then "completed", or a webhook that retries, updates the same row
		 * instead of doubling it. Orders this checkout creates use a random
		 * token for source_ref, which is unique by construction.
		 *
		 * `gateway_ref` is what the gateway calls the thing it made for this
		 * order — a Checkout Session, a PaymentIntent, a PayPal order id. It is
		 * how a webhook that only knows the gateway's own id finds the row.
		 *
		 * `order_number` is indexed but not UNIQUE. dbDelta adds a column and
		 * its index as two separate statements and checks neither for failure,
		 * so a UNIQUE index declared here would be rejected on any existing
		 * table — every row starts at '' — and silently never created.
		 * Uniqueness is guaranteed where the number is minted instead, by
		 * Jws_Payment_Orders::generate_order_number(), which re-rolls on the
		 * astronomically unlikely collision.
		 */
		$sql_orders = "CREATE TABLE {$orders} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			order_number varchar(32) NOT NULL DEFAULT '',
			type varchar(20) NOT NULL DEFAULT '',
			source varchar(20) NOT NULL DEFAULT '',
			source_ref varchar(64) NOT NULL DEFAULT '',
			item_id bigint(20) unsigned NOT NULL DEFAULT 0,
			item_label varchar(191) NOT NULL DEFAULT '',
			amount decimal(12,2) NOT NULL DEFAULT 0.00,
			currency varchar(3) NOT NULL DEFAULT '',
			gateway varchar(20) NOT NULL DEFAULT '',
			gateway_ref varchar(191) NOT NULL DEFAULT '',
			method varchar(20) NOT NULL DEFAULT '',
			subscription_id bigint(20) unsigned NOT NULL DEFAULT 0,
			meta longtext NULL,
			status varchar(20) NOT NULL DEFAULT 'completed',
			created_at datetime NOT NULL,
			paid_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_ref (source, source_ref),
			KEY user_id (user_id),
			KEY type (type),
			KEY order_number (order_number),
			KEY gateway_ref (gateway, gateway_ref)
		) {$charset};";

		/*
		 * Unique on (gateway, gateway_ref) so the renewal event three weeks
		 * from now updates the row the sign-up created rather than stacking a
		 * second subscription on the same person.
		 */
		$sql_subs = "CREATE TABLE {$subs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			type varchar(20) NOT NULL DEFAULT 'membership',
			item_id bigint(20) unsigned NOT NULL DEFAULT 0,
			label varchar(191) NOT NULL DEFAULT '',
			fingerprint varchar(32) NOT NULL DEFAULT '',
			gateway varchar(20) NOT NULL DEFAULT '',
			gateway_ref varchar(191) NOT NULL DEFAULT '',
			amount decimal(12,2) NOT NULL DEFAULT 0.00,
			currency varchar(3) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'active',
			current_period_end datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY gateway_ref (gateway, gateway_ref),
			KEY user_id (user_id)
		) {$charset};";

		dbDelta( $sql_orders );
		dbDelta( $sql_subs );

		self::backfill_order_numbers();
		self::tag_invoice_references();

		update_option( self::OPTION_DB_VERSION, self::DB_VERSION );
	}

	/**
	 * Gives every row written before this column existed an order number.
	 *
	 * Done one row at a time rather than in a single UPDATE because each number
	 * has to be minted and checked for collisions individually — and because
	 * the tables this runs against hold tens of rows, not millions.
	 */
	private static function backfill_order_numbers() {

		global $wpdb;

		if ( ! class_exists( 'Jws_Payment_Orders' ) ) {
			return;
		}

		$table = self::table();

		$ids = $wpdb->get_col( "SELECT id FROM {$table} WHERE order_number = '' OR order_number IS NULL" );

		foreach ( $ids as $id ) {
			$wpdb->update(
				$table,
				array( 'order_number' => Jws_Payment_Orders::generate_order_number() ),
				array( 'id' => (int) $id ),
				array( '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Rewrites purchase references to the customer-facing order number.
	 *
	 * Two generations of value are found on older entitlements. The oldest is a
	 * bare ledger id, which the Download-invoice button hands to wc_get_order():
	 * ledger ids and WooCommerce order ids are separate small integer sequences,
	 * so a bare one either finds nothing or — worse — finds a different
	 * customer's order and renders their invoice. The second is `jws-<id>`,
	 * which was safe but is not the number the customer sees on their receipt.
	 *
	 * An entry is only rewritten when a ledger order with that id exists for
	 * that same user and that same title, which a coincidental collision with a
	 * WooCommerce id cannot satisfy. That condition is now the WHERE clause of a
	 * single indexed UPDATE per order; it used to mean reading and rewriting the
	 * whole of each customer's serialized purchase list.
	 */
	private static function tag_invoice_references() {

		global $wpdb;

		if ( ! class_exists( 'Jws_Payment_Invoice' ) || ! class_exists( 'Jws_PPV_Access' ) ) {
			return;
		}

		$table = self::table();

		$orders = $wpdb->get_results(
			$wpdb->prepare( "SELECT id, user_id, item_id, type, order_number FROM {$table} WHERE source = %s", 'jws' )
		);

		foreach ( (array) $orders as $order ) {

			if ( ! in_array( $order->type, array( 'buy', 'live', 'rent' ), true ) || ! $order->order_number ) {
				continue;
			}

			$entitlement = 'rent' === $order->type ? Jws_PPV_Access::TYPE_RENT : Jws_PPV_Access::TYPE_BUY;

			Jws_PPV_Access::retag_order_number(
				(int) $order->user_id,
				(int) $order->item_id,
				$entitlement,
				(string) $order->order_number,
				(int) $order->id,
				array(
					(string) $order->id,
					Jws_Payment_Invoice::LEGACY_PREFIX . (int) $order->id,
				)
			);
		}
	}

	/* ---------------------------------------------------------------------- */
	/* Writing (mirrored orders)                                               */
	/* ---------------------------------------------------------------------- */

	/**
	 * Writes or updates one row for a purchase taken by a *native* checkout —
	 * PMPro's own, the WooCommerce cart, the drama module's direct
	 * Stripe/PayPal flow. Called only from Jws_Payment_Sync, and only while
	 * Jws_Payment_Settings::is_enabled() is true.
	 *
	 * This is not how an order this plugin's own checkout takes gets written —
	 * that lifecycle (pending → completed, webhooks racing a return handler)
	 * belongs to Jws_Payment_Orders::create()/mark_paid(). A native checkout
	 * has already taken the money and granted access by the time its own hook
	 * fires, so there is nothing to race and nothing left to grant: this only
	 * mirrors the fact of the sale into the shared ledger, as a single
	 * `completed` row, idempotent on (source, source_ref) so a hook that fires
	 * twice for the same order — a WooCommerce status flapping between
	 * `processing` and `completed`, a webhook retried — updates that row
	 * rather than duplicating it.
	 *
	 * @param array $args user_id, type, source, source_ref, item_id,
	 *                    item_label, amount, currency, gateway, status.
	 * @return int Row id, or 0 if the row has neither a user nor a source
	 *             reference to key on.
	 */
	public static function record( array $args ) {

		global $wpdb;

		$table = self::table();

		$row = array(
			'user_id'    => (int) $args['user_id'],
			'type'       => substr( (string) $args['type'], 0, 20 ),
			'source'     => substr( (string) $args['source'], 0, 20 ),
			'source_ref' => substr( (string) $args['source_ref'], 0, 64 ),
			'item_id'    => isset( $args['item_id'] ) ? (int) $args['item_id'] : 0,
			'item_label' => substr( (string) ( $args['item_label'] ?? '' ), 0, 191 ),
			'amount'     => number_format( (float) ( $args['amount'] ?? 0 ), 2, '.', '' ),
			'currency'   => strtoupper( substr( (string) ( $args['currency'] ?? '' ), 0, 3 ) ),
			'gateway'    => substr( (string) ( $args['gateway'] ?? '' ), 0, 20 ),
			'status'     => substr( (string) ( $args['status'] ?? 'completed' ), 0, 20 ),
		);

		if ( ! $row['user_id'] || '' === $row['source'] || '' === $row['source_ref'] ) {
			return 0;
		}

		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE source = %s AND source_ref = %s LIMIT 1",
				$row['source'],
				$row['source_ref']
			)
		);

		if ( $existing_id ) {
			$wpdb->update( $table, $row, array( 'id' => (int) $existing_id ) );
			return (int) $existing_id;
		}

		$row['created_at'] = current_time( 'mysql' );
		$row['paid_at']    = current_time( 'mysql' );

		/* order_number exists on every row regardless of who wrote it — the
		   admin Orders list shows and searches by it uniformly, and a
		   mirrored purchase has no other checkout to have already minted one
		   for it. */
		$row['order_number'] = class_exists( 'Jws_Payment_Orders' )
			? Jws_Payment_Orders::generate_order_number()
			: '';

		$wpdb->insert( $table, $row );

		return (int) $wpdb->insert_id;
	}

	/* ---------------------------------------------------------------------- */
	/* Browsing                                                                */
	/* ---------------------------------------------------------------------- */

	/** Columns a caller may order by, so `orderby` can never reach the SQL raw. */
	private static function sortable() {
		return array( 'id', 'created_at', 'paid_at', 'amount', 'type', 'status', 'user_id' );
	}

	/**
	 * Builds the WHERE for a filtered order query.
	 *
	 * Returned rather than applied so count() and query() ask exactly the same
	 * question — a list whose pager disagrees with its rows is worse than no
	 * pager at all.
	 *
	 * @return array [ sql, params ] ready for $wpdb->prepare().
	 */
	private static function where( array $args ) {

		global $wpdb;

		$where  = array( '1=1' );
		$params = array();

		foreach ( array( 'status', 'type', 'gateway', 'source' ) as $column ) {
			if ( '' !== (string) $args[ $column ] ) {
				$where[]  = "{$column} = %s";
				$params[] = $args[ $column ];
			}
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where[]  = 'user_id = %d';
			$params[] = (int) $args['user_id'];
		}

		if ( '' !== (string) $args['search'] ) {

			$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';

			/*
			 * Whatever the admin has in front of them is what they will paste:
			 * a title, a Stripe id from the dashboard, the order number off a
			 * support ticket, or the buyer's email.
			 */
			$where[] = '( order_number LIKE %s OR item_label LIKE %s OR gateway_ref LIKE %s OR source_ref LIKE %s OR id = %d
				OR user_id IN ( SELECT ID FROM ' . $wpdb->users . ' WHERE user_login LIKE %s OR user_email LIKE %s OR display_name LIKE %s ) )';

			array_push( $params, $like, $like, $like, $like, (int) $args['search'], $like, $like, $like );
		}

		return array( implode( ' AND ', $where ), $params );
	}

	private static function defaults_for_query() {

		return array(
			'status'   => '',
			'type'     => '',
			'gateway'  => '',
			'source'   => '',
			'user_id'  => 0,
			'search'   => '',
			'orderby'  => 'id',
			'order'    => 'DESC',
			'per_page' => 20,
			'paged'    => 1,
		);
	}

	/**
	 * One page of orders, filtered.
	 *
	 * Pending and failed rows are included: an order that never left `pending`
	 * is a checkout somebody abandoned, and being able to see those is half of
	 * why this screen exists.
	 */
	public static function query( array $args = array() ) {

		global $wpdb;

		$args = wp_parse_args( $args, self::defaults_for_query() );

		list( $where, $params ) = self::where( $args );

		$orderby = in_array( $args['orderby'], self::sortable(), true ) ? $args['orderby'] : 'id';
		$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$per_page = max( 1, (int) $args['per_page'] );
		$offset   = max( 0, ( (int) $args['paged'] - 1 ) * $per_page );

		$sql = "SELECT * FROM " . self::table() . " WHERE {$where} ORDER BY {$orderby} {$order}, id DESC LIMIT %d OFFSET %d";

		array_push( $params, $per_page, $offset );

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/** How many rows that same filter matches, for the pager. */
	public static function count( array $args = array() ) {

		global $wpdb;

		$args = wp_parse_args( $args, self::defaults_for_query() );

		list( $where, $params ) = self::where( $args );

		$sql = 'SELECT COUNT(*) FROM ' . self::table() . " WHERE {$where}";

		return (int) ( $params
			? $wpdb->get_var( $wpdb->prepare( $sql, $params ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			: $wpdb->get_var( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/** Row counts per status, for the "All | Completed | Pending" links. */
	public static function status_counts() {

		global $wpdb;

		$rows = $wpdb->get_results( 'SELECT status, COUNT(*) AS total FROM ' . self::table() . ' GROUP BY status' );

		$counts = array( 'all' => 0 );

		foreach ( (array) $rows as $row ) {
			$counts[ $row->status ] = (int) $row->total;
			$counts['all']         += (int) $row->total;
		}

		return $counts;
	}

	/**
	 * The distinct values one column actually holds.
	 *
	 * The filter dropdowns are built from this rather than from a fixed list,
	 * so they only ever offer something that will return rows.
	 */
	public static function distinct( $column ) {

		global $wpdb;

		if ( ! in_array( $column, array( 'type', 'gateway', 'source', 'status' ), true ) ) {
			return array();
		}

		return $wpdb->get_col( "SELECT DISTINCT {$column} FROM " . self::table() . " WHERE {$column} <> '' ORDER BY {$column} ASC" );
	}

	/** One user's completed purchases across every source, newest first. */
	public static function for_user( $user_id, $limit = 50 ) {

		global $wpdb;

		$table = self::table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND status = 'completed' ORDER BY created_at DESC, id DESC LIMIT %d",
				(int) $user_id,
				(int) $limit
			)
		);
	}
}

add_action( 'admin_init', array( 'Jws_Payment_Ledger', 'maybe_install' ) );
