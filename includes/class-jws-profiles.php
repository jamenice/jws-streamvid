<?php

/**
 * Viewer profiles: several people sharing one account, each with their own
 * history, watchlist and favorites, plus Kids profiles limited by age rating.
 *
 * Profiles live in {@see Jws_Streamvid_Tables::table_profiles()}. Every
 * account has exactly one main profile, created the first time the account's
 * profiles are read. The main profile stores its favorites, watchlist and
 * history under profile_id 0 rather than under its row id (see data_key()),
 * so rows saved before profiles existed, and rows the mobile app API writes
 * without a profile cookie, belong to the main profile.
 *
 * The profile a browser is using is kept in a signed cookie tied to the
 * current login session, so logging out, or the session expiring, returns
 * the browser to the main profile.
 *
 * Membership plans can limit how many profiles an account may use. An
 * account over its limit after a downgrade keeps every profile and its data;
 * the profiles past the limit are locked until the plan allows them again.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Profiles {

	/** Cookie holding this browser's signed choice of profile. */
	const COOKIE = 'jws_profile';

	/** Upper bound on the cookie; the login session ending clears the choice sooner. */
	const COOKIE_TTL = 30 * DAY_IN_SECONDS;

	const NAME_MAX = 50;

	const PIN_ATTEMPTS = 5;

	const PIN_LOCK_SECONDS = 15 * MINUTE_IN_SECONDS;

	/** Ages matched against `jws_maturity` term meta on `ages`: G, PG, PG-13, R, NC-17. */
	const MATURITY_LEVELS = array( 0, 7, 13, 17, 18 );

	const MATURITY_ALL = 18;

	const MATURITY_KIDS = 7;

	/** Profiles per account when no membership level sets a limit. */
	const DEFAULT_LIMIT = 5;

	/** Hard ceiling whatever an option or filter says. */
	const MAX_LIMIT = 10;

	const OPTION_DEFAULT_LIMIT = 'jws_profile_limit_default';

	/** Prefix for the per-level limit, the same pattern as the theme's `device_limit_{level_id}`. */
	const OPTION_LEVEL_LIMIT = 'profile_limit_';

	/** User meta: profile ids the account holder keeps usable when over the limit, in order. */
	const META_ACTIVE = 'jws_active_profiles';

	/** Image files ship as public/assets/images/avatars/{slug}.jpg. */
	const BUILTIN_AVATARS = array(
		'fox', 'cat', 'bear', 'panda', 'rabbit', 'owl', 'frog', 'penguin',
		'robot', 'alien', 'astronaut', 'ghost', 'dino', 'monster', 'ninja', 'wizard',
	);

	/** Per-request cache: user id => normalized profiles. */
	private static $profiles = array();

	/** Per-request cache: user id => data key of the profile in use. */
	private static $current = array();

	/* ------------------------------------------------------------------ *
	 * Reading
	 * ------------------------------------------------------------------ */

	/**
	 * Every profile on the account, main profile first, then in the order the
	 * account holder arranged them.
	 *
	 * @return object[] See normalize().
	 */
	public static function get_all( $user_id ) {

		$user_id = (int) $user_id;

		if ( $user_id <= 0 ) {
			return array();
		}
		if ( isset( self::$profiles[ $user_id ] ) ) {
			return self::$profiles[ $user_id ];
		}

		$rows = self::query_rows( $user_id );

		if ( ! $rows || null === $rows[0]->main_user_id ) {
			self::create_main( $user_id );
			$rows = self::query_rows( $user_id );
		}

		self::$profiles[ $user_id ] = array_map( array( __CLASS__, 'normalize' ), $rows );

		return self::$profiles[ $user_id ];
	}

	/** @return object|null The profile, only if it belongs to this account. */
	public static function get( $user_id, $profile_id ) {

		$profile_id = (int) $profile_id;

		foreach ( self::get_all( $user_id ) as $profile ) {
			if ( $profile->id === $profile_id ) {
				return $profile;
			}
		}

		return null;
	}

	/** @return object|null */
	public static function get_main( $user_id ) {

		$profiles = self::get_all( $user_id );

		return ( $profiles && $profiles[0]->is_main ) ? $profiles[0] : null;
	}

	/**
	 * The profile_id that this profile's favorites, watchlist and history
	 * rows are stored under: 0 for the main profile, the row id otherwise.
	 */
	public static function data_key( $profile ) {
		return $profile ? (int) $profile->data_key : 0;
	}

	public static function count( $user_id ) {
		return count( self::get_all( $user_id ) );
	}

	/* ------------------------------------------------------------------ *
	 * The profile in use
	 * ------------------------------------------------------------------ */

	/**
	 * Data key of the profile this request is using for $user_id.
	 *
	 * Only the logged-in user's own choice counts: for any other user id
	 * (an admin screen, a public profile page) this is that account's main
	 * profile, 0. So is a request with no valid profile cookie, which is
	 * what the mobile app API sends.
	 */
	public static function current_id( $user_id = 0 ) {

		$current_user = get_current_user_id();
		$user_id      = $user_id ? (int) $user_id : $current_user;

		if ( $user_id <= 0 || $user_id !== $current_user ) {
			return 0;
		}
		if ( isset( self::$current[ $user_id ] ) ) {
			return self::$current[ $user_id ];
		}

		$key        = 0;
		$profile_id = self::read_cookie( $user_id );

		if ( $profile_id ) {
			$profile = self::get( $user_id, $profile_id );
			if ( $profile && ! self::is_locked( $user_id, $profile->id ) ) {
				$key = $profile->data_key;
			}
		}

		/**
		 * Filters the data key of the profile in use, for clients that pick a
		 * profile some other way than the cookie.
		 *
		 * @param int $key     0 for the main profile, otherwise a profile row id.
		 * @param int $user_id Account the request is for.
		 */
		self::$current[ $user_id ] = (int) apply_filters( 'jws_profiles_current_id', $key, $user_id );

		return self::$current[ $user_id ];
	}

	/**
	 * Whether this browser picked a profile during the current login session,
	 * and that profile can still be used. "Who's watching?" asks until it has.
	 */
	public static function has_chosen( $user_id = 0 ) {

		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		if ( $user_id <= 0 || get_current_user_id() !== $user_id ) {
			return false;
		}

		$profile_id = self::read_cookie( $user_id );

		return $profile_id && self::get( $user_id, $profile_id ) && ! self::is_locked( $user_id, $profile_id );
	}

	/** @return object|null The profile in use, or null when no user is logged in. */
	public static function current_profile( $user_id = 0 ) {

		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		if ( $user_id <= 0 ) {
			return null;
		}

		$key = self::current_id( $user_id );

		foreach ( self::get_all( $user_id ) as $profile ) {
			if ( $profile->data_key === $key ) {
				return $profile;
			}
		}

		return self::get_main( $user_id );
	}

	/**
	 * What the favorites, watchlist and history classes store a row under:
	 * the profile they were given, or the profile in use when given null.
	 */
	public static function resolve( $user_id, $profile_id = null ) {
		return null === $profile_id ? self::current_id( $user_id ) : max( 0, (int) $profile_id );
	}

	/**
	 * Switches this browser to a profile of the logged-in account.
	 *
	 * @param string|null $pin Required when the profile has a PIN.
	 * @return object|WP_Error The profile switched to.
	 */
	public static function switch_to( $user_id, $profile_id, $pin = null ) {

		$user_id = (int) $user_id;

		if ( $user_id <= 0 || get_current_user_id() !== $user_id ) {
			return new WP_Error( 'profile_forbidden', __( 'You can only switch profiles on your own account.', 'jws_streamvid' ) );
		}

		$profile = self::get( $user_id, $profile_id );

		if ( ! $profile ) {
			return self::not_found();
		}
		if ( self::is_locked( $user_id, $profile->id ) ) {
			return new WP_Error( 'profile_locked', __( 'This profile is not available on your current plan.', 'jws_streamvid' ) );
		}
		if ( $profile->has_pin ) {
			// No PIN typed is a question, not a wrong answer: it does not use up an attempt.
			if ( null === $pin || '' === (string) $pin ) {
				return new WP_Error( 'profile_pin_required', __( 'Enter the PIN for this profile.', 'jws_streamvid' ) );
			}
			$checked = self::verify_pin( $user_id, $profile->id, (string) $pin );
			if ( is_wp_error( $checked ) ) {
				return $checked;
			}
		}

		self::write_cookie( $user_id, $profile->id );
		self::$current[ $user_id ] = $profile->data_key;

		/**
		 * Fires after the browser switched to another profile.
		 *
		 * @param int    $user_id Account.
		 * @param object $profile Profile now in use.
		 */
		do_action( 'jws_profiles_switched', $user_id, $profile );

		return $profile;
	}

	/* ------------------------------------------------------------------ *
	 * Limits
	 * ------------------------------------------------------------------ */

	/**
	 * How many profiles the account may use.
	 *
	 * When one or more of the user's membership levels sets a limit, the
	 * highest of those applies, even if it is lower than the default: that is
	 * what lets a cheaper plan offer fewer profiles. Otherwise the default
	 * applies.
	 */
	public static function get_limit( $user_id ) {

		$limit = (int) get_option( self::OPTION_DEFAULT_LIMIT, self::DEFAULT_LIMIT );

		if ( function_exists( 'pmpro_getMembershipLevelsForUser' ) ) {

			$level_limit = 0;

			foreach ( (array) pmpro_getMembershipLevelsForUser( $user_id ) as $level ) {
				$value = get_option( self::OPTION_LEVEL_LIMIT . (int) $level->id );
				if ( is_numeric( $value ) && (int) $value > 0 ) {
					$level_limit = max( $level_limit, (int) $value );
				}
			}

			if ( $level_limit > 0 ) {
				$limit = $level_limit;
			}
		}

		/**
		 * Filters how many profiles an account may use.
		 *
		 * @param int $limit   Profiles allowed, main profile included.
		 * @param int $user_id Account.
		 */
		$limit = (int) apply_filters( 'jws_profiles_limit', $limit, $user_id );

		return max( 1, min( self::MAX_LIMIT, $limit ) );
	}

	public static function can_create( $user_id ) {
		return self::count( $user_id ) < self::get_limit( $user_id );
	}

	/**
	 * Row ids of the profiles the account can use right now. Under the limit
	 * that is every profile. Over it: the main profile, then the ones the
	 * account holder chose to keep (META_ACTIVE), then the rest in display
	 * order, cut at the limit.
	 *
	 * @return int[]
	 */
	public static function active_ids( $user_id ) {

		$profiles = self::get_all( $user_id );
		$ids      = array_map( 'intval', wp_list_pluck( $profiles, 'id' ) );
		$limit    = self::get_limit( $user_id );

		if ( count( $ids ) <= $limit ) {
			return $ids;
		}

		$order = array();

		foreach ( $profiles as $profile ) {
			if ( $profile->is_main ) {
				$order[] = $profile->id;
			}
		}

		$chosen = array_map( 'intval', (array) get_user_meta( $user_id, self::META_ACTIVE, true ) );

		foreach ( array_merge( $chosen, $ids ) as $id ) {
			if ( in_array( $id, $ids, true ) && ! in_array( $id, $order, true ) ) {
				$order[] = $id;
			}
		}

		return array_slice( $order, 0, $limit );
	}

	public static function is_locked( $user_id, $profile_id ) {
		return ! in_array( (int) $profile_id, self::active_ids( $user_id ), true );
	}

	/**
	 * Stores which profiles to keep usable when the account is over its
	 * limit. Ids that are not this account's profiles are dropped.
	 *
	 * @param int[] $profile_ids In order of preference.
	 */
	public static function set_active( $user_id, array $profile_ids ) {

		$own = array_map( 'intval', wp_list_pluck( self::get_all( $user_id ), 'id' ) );
		$ids = array_values( array_intersect( array_unique( array_map( 'intval', $profile_ids ) ), $own ) );

		update_user_meta( $user_id, self::META_ACTIVE, $ids );
		self::flush( $user_id );
	}

	/* ------------------------------------------------------------------ *
	 * Writing
	 * ------------------------------------------------------------------ */

	/**
	 * @param array $data name (required), avatar, is_kid, max_maturity, autoplay_next.
	 *                    Values are expected unslashed.
	 * @return object|WP_Error The new profile.
	 */
	public static function create( $user_id, array $data ) {

		global $wpdb;

		$user_id  = (int) $user_id;
		$profiles = self::get_all( $user_id );

		if ( ! $profiles ) {
			return self::not_found();
		}

		$limit = self::get_limit( $user_id );

		if ( count( $profiles ) >= $limit ) {
			return new WP_Error(
				'profile_limit',
				sprintf(
					/* translators: %d: number of profiles the plan allows */
					_n( 'Your plan allows %d profile.', 'Your plan allows %d profiles.', $limit, 'jws_streamvid' ),
					$limit
				)
			);
		}

		$data['name'] = isset( $data['name'] ) ? $data['name'] : '';
		$clean        = self::validate( $user_id, $data, null );

		if ( is_wp_error( $clean ) ) {
			return $clean;
		}

		$is_kid = ! empty( $clean['is_kid'] );
		$now    = current_time( 'mysql' );
		$row    = array_merge(
			array(
				'avatar'        => self::pick_unused_avatar( $profiles ),
				'is_kid'        => 0,
				'max_maturity'  => $is_kid ? self::kids_max_maturity() : self::MATURITY_ALL,
				'autoplay_next' => 1,
			),
			$clean,
			array(
				'user_id'    => $user_id,
				'sort_order' => 1 + max( wp_list_pluck( $profiles, 'sort_order' ) ),
				'created_at' => $now,
				'updated_at' => $now,
			)
		);

		$ok = $wpdb->insert( Jws_Streamvid_Tables::table_profiles(), $row, self::formats( $row ) );

		if ( ! $ok ) {
			return self::db_error();
		}

		self::flush( $user_id );

		return self::get( $user_id, $wpdb->insert_id );
	}

	/**
	 * @param array $data Any of name, avatar, is_kid, max_maturity, autoplay_next, sort_order.
	 * @return object|WP_Error The updated profile.
	 */
	public static function update( $user_id, $profile_id, array $data ) {

		global $wpdb;

		$user_id = (int) $user_id;
		$profile = self::get( $user_id, $profile_id );

		if ( ! $profile ) {
			return self::not_found();
		}

		$clean = self::validate( $user_id, $data, $profile );

		if ( is_wp_error( $clean ) ) {
			return $clean;
		}
		if ( ! $clean ) {
			return $profile;
		}

		$clean['updated_at'] = current_time( 'mysql' );

		$ok = $wpdb->update(
			Jws_Streamvid_Tables::table_profiles(),
			$clean,
			array(
				'id'      => $profile->id,
				'user_id' => $user_id,
			),
			self::formats( $clean ),
			array( '%d', '%d' )
		);

		if ( false === $ok ) {
			return self::db_error();
		}

		self::flush( $user_id );

		return self::get( $user_id, $profile->id );
	}

	/**
	 * Deletes a profile with its favorites, watchlist and history. The main
	 * profile cannot be deleted.
	 *
	 * @return true|WP_Error
	 */
	public static function delete( $user_id, $profile_id ) {

		global $wpdb;

		$user_id = (int) $user_id;
		$profile = self::get( $user_id, $profile_id );

		if ( ! $profile ) {
			return self::not_found();
		}
		if ( $profile->is_main ) {
			return new WP_Error( 'profile_main', __( 'The main profile cannot be deleted.', 'jws_streamvid' ) );
		}

		$tables = array(
			Jws_Streamvid_Tables::table_favorites(),
			Jws_Streamvid_Tables::table_watchlist(),
			Jws_Streamvid_Tables::table_history(),
		);

		foreach ( $tables as $table ) {
			$wpdb->delete(
				$table,
				array(
					'user_id'    => $user_id,
					'profile_id' => $profile->data_key,
				),
				array( '%d', '%d' )
			);
		}

		$ok = $wpdb->delete(
			Jws_Streamvid_Tables::table_profiles(),
			array(
				'id'      => $profile->id,
				'user_id' => $user_id,
			),
			array( '%d', '%d' )
		);

		if ( ! $ok ) {
			return self::db_error();
		}

		delete_transient( self::attempts_key( $user_id, $profile->id ) );

		$chosen = (array) get_user_meta( $user_id, self::META_ACTIVE, true );
		if ( in_array( $profile->id, array_map( 'intval', $chosen ), true ) ) {
			update_user_meta( $user_id, self::META_ACTIVE, array_values( array_diff( array_map( 'intval', $chosen ), array( $profile->id ) ) ) );
		}

		if ( get_current_user_id() === $user_id && self::read_cookie( $user_id ) === $profile->id ) {
			self::clear_cookie();
		}

		self::flush( $user_id );

		return true;
	}

	/* ------------------------------------------------------------------ *
	 * Profile Lock (PIN)
	 * ------------------------------------------------------------------ */

	/**
	 * Sets, changes or removes (empty $pin) a profile's 4-digit PIN. Needs
	 * the account password, so someone who only has a Kids profile in front
	 * of them cannot unlock the others.
	 *
	 * @return true|WP_Error
	 */
	public static function set_pin( $user_id, $profile_id, $pin, $password ) {

		global $wpdb;

		$user_id = (int) $user_id;
		$profile = self::get( $user_id, $profile_id );

		if ( ! $profile ) {
			return self::not_found();
		}

		$checked = self::check_account_password( $user_id, $password );

		if ( is_wp_error( $checked ) ) {
			return $checked;
		}

		$pin = (string) $pin;

		if ( '' !== $pin && ! preg_match( '/^\d{4}$/', $pin ) ) {
			return new WP_Error( 'profile_pin_format', __( 'The PIN must be 4 digits.', 'jws_streamvid' ) );
		}

		$ok = $wpdb->update(
			Jws_Streamvid_Tables::table_profiles(),
			array(
				'pin_hash'   => '' === $pin ? '' : wp_hash_password( $pin ),
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'id'      => $profile->id,
				'user_id' => $user_id,
			),
			array( '%s', '%s' ),
			array( '%d', '%d' )
		);

		if ( false === $ok ) {
			return self::db_error();
		}

		delete_transient( self::attempts_key( $user_id, $profile->id ) );
		self::flush( $user_id );

		return true;
	}

	/**
	 * Checks a PIN. After PIN_ATTEMPTS wrong tries the profile refuses every
	 * PIN, right or wrong, for PIN_LOCK_SECONDS.
	 *
	 * @return true|WP_Error profile_pin_wrong (data: attempts_left) or
	 *                       profile_pin_locked (data: retry_after seconds).
	 */
	public static function verify_pin( $user_id, $profile_id, $pin ) {

		$user_id = (int) $user_id;
		$profile = self::get( $user_id, $profile_id );

		if ( ! $profile ) {
			return self::not_found();
		}
		if ( ! $profile->has_pin ) {
			return true;
		}

		$key   = self::attempts_key( $user_id, $profile->id );
		$state = get_transient( $key );
		$now   = time();

		if ( is_array( $state ) && ! empty( $state['until'] ) && $state['until'] > $now ) {
			return self::pin_locked( $state['until'] - $now );
		}

		if ( wp_check_password( (string) $pin, self::pin_hash( $user_id, $profile->id ) ) ) {
			delete_transient( $key );
			return true;
		}

		$count = ( is_array( $state ) && isset( $state['count'] ) ? (int) $state['count'] : 0 ) + 1;

		if ( $count >= self::PIN_ATTEMPTS ) {
			set_transient( $key, array( 'count' => $count, 'until' => $now + self::PIN_LOCK_SECONDS ), self::PIN_LOCK_SECONDS );
			return self::pin_locked( self::PIN_LOCK_SECONDS );
		}

		set_transient( $key, array( 'count' => $count, 'until' => 0 ), self::PIN_LOCK_SECONDS );

		$left = self::PIN_ATTEMPTS - $count;

		return new WP_Error(
			'profile_pin_wrong',
			sprintf(
				/* translators: %d: PIN attempts left before the profile locks */
				_n( 'Incorrect PIN. %d attempt left.', 'Incorrect PIN. %d attempts left.', $left, 'jws_streamvid' ),
				$left
			),
			array( 'attempts_left' => $left )
		);
	}

	/* ------------------------------------------------------------------ *
	 * Avatars and maturity
	 * ------------------------------------------------------------------ */

	/**
	 * Avatars a profile can pick, as stored values: `builtin:{slug}` for the
	 * images shipped with the plugin, `att:{attachment_id}` for images an
	 * admin adds. Account holders cannot upload their own.
	 *
	 * @return string[]
	 */
	public static function avatars() {

		$builtin = array();

		foreach ( self::BUILTIN_AVATARS as $slug ) {
			$builtin[] = 'builtin:' . $slug;
		}

		/**
		 * Filters the avatars profiles can pick from.
		 *
		 * @param string[] $avatars Stored values, `builtin:{slug}` or `att:{attachment_id}`.
		 */
		return array_values( array_filter( (array) apply_filters( 'jws_profiles_avatars', $builtin ), 'is_string' ) );
	}

	/**
	 * The avatar picker's sections. Only avatars still offered by avatars()
	 * are listed; a group left empty is dropped.
	 *
	 * @return array[] key => array( 'label' => string, 'avatars' => string[] ).
	 */
	public static function avatar_groups() {

		$offered = self::avatars();
		$groups  = array(
			'animals'    => array(
				'label'   => __( 'Animals', 'jws_streamvid' ),
				'avatars' => array( 'fox', 'cat', 'bear', 'panda', 'rabbit', 'owl', 'frog', 'penguin' ),
			),
			'characters' => array(
				'label'   => __( 'Characters', 'jws_streamvid' ),
				'avatars' => array( 'robot', 'alien', 'astronaut', 'ghost', 'dino', 'monster', 'ninja', 'wizard' ),
			),
		);

		$listed = array();

		foreach ( $groups as $key => $group ) {
			$values = array();
			foreach ( $group['avatars'] as $slug ) {
				$values[] = 'builtin:' . $slug;
			}
			$groups[ $key ]['avatars'] = array_values( array_intersect( $values, $offered ) );
			$listed                    = array_merge( $listed, $values );
		}

		// Anything else offered (images an admin added) gets a section of its own.
		$more = array_values( array_diff( $offered, $listed ) );

		if ( $more ) {
			$groups['more'] = array(
				'label'   => __( 'More', 'jws_streamvid' ),
				'avatars' => $more,
			);
		}

		/**
		 * Filters the avatar picker's sections.
		 *
		 * @param array[] $groups key => array( 'label' => string, 'avatars' => string[] ).
		 */
		$groups = (array) apply_filters( 'jws_profiles_avatar_groups', $groups );

		return array_filter( $groups, function ( $group ) {
			return ! empty( $group['avatars'] );
		} );
	}

	/** @return string Image URL for a stored avatar value, or '' when it has none. */
	public static function avatar_url( $avatar ) {

		$url = '';

		if ( 0 === strpos( (string) $avatar, 'builtin:' ) ) {
			$url = JWS_STREAMVID_URL_PUBLIC_ASSETS . '/images/avatars/' . sanitize_key( substr( $avatar, 8 ) ) . '.jpg';
		} elseif ( 0 === strpos( (string) $avatar, 'att:' ) ) {
			$url = (string) wp_get_attachment_image_url( absint( substr( $avatar, 4 ) ), 'thumbnail' );
		}

		/**
		 * Filters the image URL of a profile avatar.
		 *
		 * @param string $url    Image URL, '' when unknown.
		 * @param string $avatar Stored value.
		 */
		return (string) apply_filters( 'jws_profiles_avatar_url', $url, $avatar );
	}

	/** An avatar nobody on the account uses yet, to preselect for a new profile. */
	public static function suggest_avatar( $user_id ) {
		return self::pick_unused_avatar( self::get_all( $user_id ) );
	}

	/** Highest maturity a Kids profile may be set to. */
	public static function kids_max_maturity() {

		/**
		 * Filters the highest maturity, in years, a Kids profile may watch.
		 *
		 * @param int $age One of Jws_Profiles::MATURITY_LEVELS.
		 */
		$age = (int) apply_filters( 'jws_profiles_kids_max_maturity', self::MATURITY_KIDS );

		return in_array( $age, self::MATURITY_LEVELS, true ) ? $age : self::MATURITY_KIDS;
	}

	/* ------------------------------------------------------------------ *
	 * Hooks
	 * ------------------------------------------------------------------ */

	public static function clear_cookie() {

		self::send_cookie( '', time() - YEAR_IN_SECONDS );
		unset( $_COOKIE[ self::COOKIE ] );
		self::$current = array();

		/** This action is documented in includes/class-jws-profiles.php */
		do_action( 'jws_profiles_switched', get_current_user_id(), null );
	}

	/** Removes the account's profiles with the account. */
	public static function on_deleted_user( $user_id ) {

		global $wpdb;

		$wpdb->delete( Jws_Streamvid_Tables::table_profiles(), array( 'user_id' => (int) $user_id ), array( '%d' ) );
		self::flush( $user_id );
	}

	/* ------------------------------------------------------------------ *
	 * Internals
	 * ------------------------------------------------------------------ */

	private static function query_rows( $user_id ) {

		global $wpdb;

		$table = Jws_Streamvid_Tables::table_profiles();

		// Main profile first: its main_user_id is the only non-NULL one.
		return (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE user_id = %d ORDER BY main_user_id IS NULL, sort_order ASC, id ASC",
			$user_id
		) );
	}

	/**
	 * Creates the main profile from the account's display name. INSERT IGNORE
	 * plus the unique key on main_user_id make a second, concurrent call a
	 * no-op instead of a second main profile.
	 */
	private static function create_main( $user_id ) {

		global $wpdb;

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$name = trim( sanitize_text_field( $user->display_name ) );
		$name = '' !== $name ? $name : $user->user_login;
		$name = mb_substr( $name, 0, self::NAME_MAX );

		$avatars = self::avatars();
		$table   = Jws_Streamvid_Tables::table_profiles();
		$now     = current_time( 'mysql' );

		$wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$table} (user_id, main_user_id, name, avatar, max_maturity, sort_order, created_at, updated_at)
			 VALUES (%d, %d, %s, %s, %d, 0, %s, %s)",
			$user_id,
			$user_id,
			$name,
			$avatars ? $avatars[0] : '',
			self::MATURITY_ALL,
			$now,
			$now
		) );
	}

	private static function normalize( $row ) {

		$is_main = null !== $row->main_user_id;

		return (object) array(
			'id'            => (int) $row->id,
			'user_id'       => (int) $row->user_id,
			'data_key'      => $is_main ? 0 : (int) $row->id,
			'name'          => (string) $row->name,
			'avatar'        => (string) $row->avatar,
			'is_main'       => $is_main,
			'is_kid'        => (bool) $row->is_kid,
			'max_maturity'  => (int) $row->max_maturity,
			'has_pin'       => '' !== (string) $row->pin_hash,
			'autoplay_next' => (bool) $row->autoplay_next,
			'sort_order'    => (int) $row->sort_order,
		);
	}

	/**
	 * Sanitizes and checks the fields being written. On update, only fields
	 * present in $data are returned.
	 *
	 * @param object|null $profile The profile being updated, null on create.
	 * @return array|WP_Error Column => value.
	 */
	private static function validate( $user_id, array $data, $profile ) {

		$clean = array();

		if ( array_key_exists( 'name', $data ) ) {

			$name = trim( sanitize_text_field( (string) $data['name'] ) );

			if ( '' === $name ) {
				return new WP_Error( 'profile_name', __( 'Enter a profile name.', 'jws_streamvid' ) );
			}
			if ( mb_strlen( $name ) > self::NAME_MAX ) {
				return new WP_Error(
					'profile_name',
					sprintf(
						/* translators: %d: maximum number of characters */
						__( 'Use %d characters or fewer for the profile name.', 'jws_streamvid' ),
						self::NAME_MAX
					)
				);
			}

			foreach ( self::get_all( $user_id ) as $other ) {
				if ( ( ! $profile || $other->id !== $profile->id ) && 0 === strcasecmp( $other->name, $name ) ) {
					return new WP_Error( 'profile_name_taken', __( 'Another profile on this account already uses that name.', 'jws_streamvid' ) );
				}
			}

			$clean['name'] = $name;
		}

		if ( array_key_exists( 'avatar', $data ) ) {

			$avatar = (string) $data['avatar'];

			// A profile keeps an avatar the admin has since switched off.
			$keeping = $profile && $profile->avatar === $avatar;

			if ( ! $keeping && ! in_array( $avatar, self::avatars(), true ) ) {
				return new WP_Error( 'profile_avatar', __( 'Choose one of the avatars shown.', 'jws_streamvid' ) );
			}

			$clean['avatar'] = $avatar;
		}

		$is_kid = $profile ? $profile->is_kid : false;

		if ( array_key_exists( 'is_kid', $data ) ) {

			$is_kid = (bool) $data['is_kid'];

			if ( $is_kid && $profile && $profile->is_main ) {
				return new WP_Error( 'profile_main', __( 'The main profile cannot be a Kids profile.', 'jws_streamvid' ) );
			}

			$clean['is_kid'] = $is_kid ? 1 : 0;
		}

		if ( array_key_exists( 'max_maturity', $data ) ) {

			$age = (int) $data['max_maturity'];

			if ( ! in_array( $age, self::MATURITY_LEVELS, true ) ) {
				return new WP_Error( 'profile_maturity', __( 'Choose one of the maturity ratings shown.', 'jws_streamvid' ) );
			}

			$clean['max_maturity'] = $age;
		}

		if ( $is_kid ) {
			// A Kids profile never goes above the Kids ceiling, whichever field changed.
			$age = isset( $clean['max_maturity'] ) ? $clean['max_maturity'] : ( $profile && $profile->is_kid ? $profile->max_maturity : self::kids_max_maturity() );
			$age = min( $age, self::kids_max_maturity() );

			if ( ! $profile || $age !== $profile->max_maturity ) {
				$clean['max_maturity'] = $age;
			}
		} elseif ( $profile && $profile->is_kid && isset( $clean['is_kid'] ) && ! isset( $clean['max_maturity'] ) ) {
			// Turning Kids off without picking a rating opens every rating again.
			$clean['max_maturity'] = self::MATURITY_ALL;
		}

		if ( array_key_exists( 'autoplay_next', $data ) ) {
			$clean['autoplay_next'] = empty( $data['autoplay_next'] ) ? 0 : 1;
		}

		if ( array_key_exists( 'sort_order', $data ) ) {
			$clean['sort_order'] = min( 65535, absint( $data['sort_order'] ) );
		}

		return $clean;
	}

	private static function formats( array $row ) {

		$map = array(
			'user_id'       => '%d',
			'main_user_id'  => '%d',
			'name'          => '%s',
			'avatar'        => '%s',
			'is_kid'        => '%d',
			'max_maturity'  => '%d',
			'pin_hash'      => '%s',
			'autoplay_next' => '%d',
			'sort_order'    => '%d',
			'created_at'    => '%s',
			'updated_at'    => '%s',
		);

		$formats = array();

		foreach ( array_keys( $row ) as $column ) {
			$formats[] = isset( $map[ $column ] ) ? $map[ $column ] : '%s';
		}

		return $formats;
	}

	/** New profiles get an avatar nobody on the account uses yet, when one is left. */
	private static function pick_unused_avatar( array $profiles ) {

		$all  = self::avatars();
		$free = array_values( array_diff( $all, wp_list_pluck( $profiles, 'avatar' ) ) );
		$pool = $free ? $free : $all;

		return $pool ? $pool[ array_rand( $pool ) ] : '';
	}

	private static function pin_hash( $user_id, $profile_id ) {

		global $wpdb;

		$table = Jws_Streamvid_Tables::table_profiles();

		return (string) $wpdb->get_var( $wpdb->prepare(
			"SELECT pin_hash FROM {$table} WHERE id = %d AND user_id = %d",
			$profile_id,
			$user_id
		) );
	}

	private static function attempts_key( $user_id, $profile_id ) {
		return 'jws_pin_tries_' . (int) $user_id . '_' . (int) $profile_id;
	}

	private static function check_account_password( $user_id, $password ) {

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return self::not_found();
		}

		// Accounts created from a phone number or a social sign-in have a random password nobody knows.
		if ( function_exists( 'jws_streamvid_password_unknown' ) && jws_streamvid_password_unknown( $user_id ) ) {
			return new WP_Error( 'profile_password_unset', __( 'Set an account password in Settings first, then use it here.', 'jws_streamvid' ) );
		}

		if ( ! wp_check_password( (string) $password, $user->user_pass, $user->ID ) ) {
			return new WP_Error( 'profile_password', __( 'The account password is incorrect.', 'jws_streamvid' ) );
		}

		return true;
	}

	private static function pin_locked( $seconds ) {

		$minutes = max( 1, (int) ceil( $seconds / MINUTE_IN_SECONDS ) );

		return new WP_Error(
			'profile_pin_locked',
			sprintf(
				/* translators: %d: minutes until the PIN can be tried again */
				_n( 'Too many attempts. Try again in %d minute.', 'Too many attempts. Try again in %d minutes.', $minutes, 'jws_streamvid' ),
				$minutes
			),
			array( 'retry_after' => (int) $seconds )
		);
	}

	private static function not_found() {
		return new WP_Error( 'profile_not_found', __( 'That profile does not exist on this account.', 'jws_streamvid' ) );
	}

	private static function db_error() {
		return new WP_Error( 'profile_db', __( 'The profile could not be saved. Try again.', 'jws_streamvid' ) );
	}

	private static function flush( $user_id ) {
		unset( self::$profiles[ (int) $user_id ], self::$current[ (int) $user_id ] );
	}

	/* ------------------------------------------------------------------ *
	 * Cookie
	 * ------------------------------------------------------------------ */

	/**
	 * Signs user id, profile id and expiry together with the login session
	 * token, so the cookie cannot be edited to another profile id, copied to
	 * another account, or reused after logging out.
	 */
	private static function sign( $user_id, $profile_id, $expires ) {
		return hash_hmac( 'sha256', $user_id . '|' . $profile_id . '|' . $expires . '|' . wp_get_session_token(), wp_salt( 'auth' ) );
	}

	private static function write_cookie( $user_id, $profile_id ) {

		$user_id    = (int) $user_id;
		$profile_id = (int) $profile_id;
		$expires    = time() + self::COOKIE_TTL;
		$value      = $user_id . '|' . $profile_id . '|' . $expires . '|' . self::sign( $user_id, $profile_id, $expires );

		self::send_cookie( $value, $expires );
		$_COOKIE[ self::COOKIE ] = $value;
	}

	/** @return int Profile row id from a valid cookie for this user, else 0. */
	private static function read_cookie( $user_id ) {

		if ( empty( $_COOKIE[ self::COOKIE ] ) || ! is_string( $_COOKIE[ self::COOKIE ] ) ) {
			return 0;
		}

		$parts = explode( '|', sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) );

		if ( 4 !== count( $parts ) ) {
			return 0;
		}

		$uid     = (int) $parts[0];
		$pid     = (int) $parts[1];
		$expires = (int) $parts[2];

		if ( $uid !== (int) $user_id || $pid <= 0 || $expires < time() ) {
			return 0;
		}

		return hash_equals( self::sign( $uid, $pid, $expires ), $parts[3] ) ? $pid : 0;
	}

	private static function send_cookie( $value, $expires ) {

		if ( headers_sent() ) {
			return;
		}

		setcookie(
			self::COOKIE,
			$value,
			array(
				'expires'  => $expires,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}
}

add_action( 'wp_logout', array( 'Jws_Profiles', 'clear_cookie' ) );
add_action( 'deleted_user', array( 'Jws_Profiles', 'on_deleted_user' ) );
