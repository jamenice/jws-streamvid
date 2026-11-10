<?php

/**
 * Front end of viewer profiles: the "Who's watching?" modal, the forms
 * behind it, and the profile part of the header account menu.
 *
 * Every screen is shown in one modal (public/assets/js/profiles-modal.js);
 * there are no pages. Each screen still has a URL, /whos-watching/ by
 * default, which the modal loads with the header "X-Jws-Profiles: 1":
 *
 *     /whos-watching/            choose a profile (?manage=1 to edit them)
 *     /whos-watching/pin/{id}/   enter a profile's PIN
 *     /whos-watching/add/        add a profile
 *     /whos-watching/edit/{id}/  edit a profile, its PIN, or delete it
 *
 * Such a request gets JSON: the screen's markup ({html}) or where to go
 * next ({go}). Opening one of these URLs directly goes back to the page it
 * came from (?back=, else the home page) and opens the modal there.
 *
 * Every action is a form POST to the screen it is on, checked in the order
 * nonce, logged-in user, sanitize, then handed to Jws_Profiles, which owns
 * all validation and the SQL. A success answers with where to go next; an
 * error renders the same screen again with the message and the values
 * typed.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Profiles_Front {

	const QUERY_VAR = 'jws_profiles';

	const ID_VAR = 'jws_profile_id';

	const NONCE = 'jws_profiles';

	/** Bump when the rewrite rules change so they are rebuilt once (3: Profiles tab endpoint). */
	const REWRITE_VERSION = '3';

	const OPTION_REWRITE = 'jws_profiles_rewrite';

	const SCREENS = array( 'choose', 'pin', 'add', 'edit' );

	/** Errors from the form posted in this request: field => message ('' for the form). */
	private static $errors = array();

	/** Values from the form posted in this request, for refilling it. */
	private static $posted = array();

	/** Set by the gate: the "Who's watching?" URL the modal opens on this page. */
	private static $gate_url = '';

	/* ------------------------------------------------------------------ *
	 * URLs and routing
	 * ------------------------------------------------------------------ */

	public static function slug() {

		/**
		 * Filters the URL slug of the "Who's watching?" screens.
		 *
		 * @param string $slug Default whos-watching.
		 */
		$slug = sanitize_title( (string) apply_filters( 'jws_profiles_slug', 'whos-watching' ) );

		return '' !== $slug ? $slug : 'whos-watching';
	}

	/**
	 * @param string $screen     choose, pin, add or edit.
	 * @param int    $profile_id For pin and edit.
	 * @param array  $args       Extra query args, such as back or manage.
	 */
	public static function url( $screen = 'choose', $profile_id = 0, array $args = array() ) {

		$profile_id = (int) $profile_id;

		if ( get_option( 'permalink_structure' ) ) {
			$path = self::slug() . '/';
			if ( 'add' === $screen ) {
				$path .= 'add/';
			} elseif ( in_array( $screen, array( 'pin', 'edit' ), true ) ) {
				$path .= $screen . '/' . $profile_id . '/';
			}
			$url = home_url( '/' . $path );
		} else {
			$url = add_query_arg( array( self::QUERY_VAR => $screen ), home_url( '/' ) );
			if ( $profile_id ) {
				$url = add_query_arg( self::ID_VAR, $profile_id, $url );
			}
		}

		return $args ? add_query_arg( array_map( 'rawurlencode', $args ), $url ) : $url;
	}

	public static function add_rewrites() {

		$slug = preg_quote( self::slug(), '#' );

		add_rewrite_rule( '^' . $slug . '/?$', 'index.php?' . self::QUERY_VAR . '=choose', 'top' );
		add_rewrite_rule( '^' . $slug . '/add/?$', 'index.php?' . self::QUERY_VAR . '=add', 'top' );
		add_rewrite_rule( '^' . $slug . '/(pin|edit)/([0-9]+)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]&' . self::ID_VAR . '=$matches[2]', 'top' );
	}

	public static function query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		$vars[] = self::ID_VAR;
		return $vars;
	}

	/** Rebuilds the stored rewrite rules once when these rules or their slug change. */
	public static function maybe_flush() {

		$want = self::REWRITE_VERSION . '|' . self::slug();

		if ( get_option( self::OPTION_REWRITE ) === $want ) {
			return;
		}

		update_option( self::OPTION_REWRITE, $want, true );
		flush_rewrite_rules( false );
	}

	/** @return string The profile screen this request is for, or ''. */
	public static function screen() {

		$screen = (string) get_query_var( self::QUERY_VAR );

		return in_array( $screen, self::SCREENS, true ) ? $screen : '';
	}

	/** Where to go once a profile is picked: ?back=, when it is on this site. */
	public static function back_url() {

		$back = isset( $_REQUEST['back'] ) ? esc_url_raw( wp_unslash( $_REQUEST['back'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// wp_validate_redirect() returns '' for '', not the fallback.
		return '' === $back ? home_url( '/' ) : wp_validate_redirect( $back, home_url( '/' ) );
	}

	/** Whether this request comes from the modal (header "X-Jws-Profiles: 1"). */
	public static function is_modal_request() {

		$asked = isset( $_SERVER['HTTP_X_JWS_PROFILES'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_JWS_PROFILES'] ) ) : '';

		return '1' === $asked;
	}

	/** Sends the browser to $url: as {go} to the modal, as a redirect otherwise. Ends the request. */
	private static function go( $url ) {

		if ( self::is_modal_request() ) {
			wp_send_json( array( 'go' => wp_validate_redirect( $url, home_url( '/' ) ) ) );
		}

		wp_safe_redirect( $url );
		exit;
	}

	/** The screen's markup as JSON, for the modal. Ends the request. */
	private static function send_screen( $screen ) {

		ob_start();
		echo '<div class="jws-pf jws-pf--' . esc_attr( $screen ) . '"><div class="jws-pf__wrap">';
		jws_streamvid_load_template( 'user/profiles/' . $screen . '.php', false );
		echo '</div></div>';

		wp_send_json(
			array(
				'screen' => $screen,
				'html'   => ob_get_clean(),
			)
		);
	}

	/** This screen's URL without ?back=, for the modal to open after a direct visit. */
	private static function screen_url( $screen ) {

		$args = array();

		foreach ( array( 'manage', 'notice' ) as $key ) {
			if ( isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$args[ $key ] = sanitize_key( wp_unslash( $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}

		return self::url( $screen, self::requested_id(), $args );
	}

	/** "Who's watching?" URL the modal opens by itself on this page (the gate), or ''. */
	public static function gate_url() {
		return self::$gate_url;
	}

	/**
	 * Answers the modal's screen requests, or, on a page where the viewer has
	 * not picked a profile yet, has the modal open there by itself.
	 */
	public static function route() {

		$screen = self::screen();

		if ( ! $screen ) {
			self::maybe_gate();
			return;
		}

		nocache_headers();

		// The screens are not posts, so WordPress has marked the request a 404.
		status_header( 200 );

		if ( ! Jws_Profiles::enabled() ) {
			self::go( home_url( '/' ) );
		}

		if ( ! is_user_logged_in() ) {
			self::go( wp_login_url( self::url() ) );
		}

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			self::handle_post( $screen );
		}

		$user_id = get_current_user_id();

		if ( in_array( $screen, array( 'pin', 'edit' ), true ) && ! Jws_Profiles::get( $user_id, self::requested_id() ) ) {
			self::go( self::url() );
		}

		if ( in_array( $screen, array( 'add', 'edit' ), true ) && ! self::can_manage_account() ) {
			self::go( self::url( 'choose', 0, array( 'notice' => self::current_is_kid() ? 'kids' : 'main_only' ) ) );
		}

		if ( 'add' === $screen && ! Jws_Profiles::can_create( $user_id ) && empty( self::$errors ) ) {
			self::go( self::url( 'choose', 0, array( 'manage' => 1, 'notice' => 'limit' ) ) );
		}

		if ( self::is_modal_request() ) {
			self::send_screen( $screen );
		}

		// No pages: a direct visit goes back where it came from and the modal opens this screen there.
		self::go( self::back_url() . '#jws-profiles=' . rawurlencode( wp_make_link_relative( self::screen_url( $screen ) ) ) );
	}

	/**
	 * Accounts with two or more profiles (or every account, Theme Options)
	 * pick one before browsing, in the modal over the page. By default only
	 * pages where the profile changes what is shown ask: home, search,
	 * titles and their archives. With "Ask right after sign-in" on, any page
	 * asks until a profile is picked. Cart, checkout and payment pages never
	 * do, so a payment or its return is not interrupted.
	 */
	private static function maybe_gate() {

		if ( ! Jws_Profiles::enabled() || ! is_user_logged_in() || ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}
		if ( wp_doing_ajax() || is_feed() || is_embed() || is_robots() || is_preview() || isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( self::is_payment_page() || ! ( self::is_browse_page() || Jws_Profiles_Settings::gate_on_login() ) ) {
			return;
		}

		$user_id = get_current_user_id();

		// Theme Options can ask after every sign-in; by default only accounts with 2+ profiles are asked.
		if ( Jws_Profiles::has_chosen( $user_id ) || ( Jws_Profiles::count( $user_id ) < 2 && ! Jws_Profiles_Settings::gate_always() ) ) {
			return;
		}

		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$path = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$here = ( is_ssl() ? 'https://' : 'http://' ) . $host . $path;

		// The page loads as usual; the modal opens over it and stays until a profile is picked.
		self::$gate_url = self::url( 'choose', 0, array( 'back' => $here ) );
	}

	/** Cart, checkout and payment pages (WooCommerce, Paid Memberships Pro): never interrupted by the modal. */
	private static function is_payment_page() {

		$payment = false;

		if ( function_exists( 'is_checkout' ) && ( is_checkout() || is_cart() || is_wc_endpoint_url( 'order-received' ) || is_wc_endpoint_url( 'order-pay' ) ) ) {
			$payment = true;
		}

		if ( ! $payment && ! empty( $GLOBALS['pmpro_pages'] ) && is_array( $GLOBALS['pmpro_pages'] ) ) {
			$pages = array_filter( array_map( 'absint', array_intersect_key( $GLOBALS['pmpro_pages'], array_flip( array( 'checkout', 'confirmation', 'billing', 'cancel', 'invoice' ) ) ) ) );
			$payment = $pages && is_page( array_values( $pages ) );
		}

		/**
		 * Filters whether this page is part of paying, where "Who's watching?" never opens.
		 *
		 * @param bool $payment True on cart, checkout and payment pages.
		 */
		return (bool) apply_filters( 'jws_profiles_is_payment_page', $payment );
	}

	private static function is_browse_page() {

		$types = array_merge(
			Jws_Profile_Filter::RATED_TYPES,
			array_keys( Jws_Profile_Filter::CHILD_TYPES ),
			Jws_Profile_Filter::KIDS_HIDDEN_TYPES
		);

		$taxonomies = array();
		foreach ( $types as $type ) {
			$taxonomies = array_merge( $taxonomies, get_object_taxonomies( $type ) );
		}

		$browse = is_front_page() || is_home() || is_search() || is_singular( $types ) || is_post_type_archive( $types )
			|| ( $taxonomies && is_tax( array_unique( $taxonomies ) ) );

		/**
		 * Filters whether this page asks "Who's watching?" first.
		 *
		 * @param bool $browse True on home, search, titles and their archives.
		 */
		return (bool) apply_filters( 'jws_profiles_is_browse_page', $browse );
	}

	/* ------------------------------------------------------------------ *
	 * Forms
	 * ------------------------------------------------------------------ */

	private static function handle_post( $screen ) {

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified first thing below.
		if ( ! isset( $_POST['_jws_profiles_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_jws_profiles_nonce'] ) ), self::NONCE ) ) {
			self::$errors[''] = __( 'This form has expired. Reload the page and try again.', 'jws_streamvid' );
			return;
		}

		$user_id    = get_current_user_id();
		$action     = isset( $_POST['jws_profiles_action'] ) ? sanitize_key( wp_unslash( $_POST['jws_profiles_action'] ) ) : '';
		$profile_id = isset( $_POST['profile_id'] ) ? absint( wp_unslash( $_POST['profile_id'] ) ) : 0;

		/*
		 * Only a profile that may manage the account changes profiles: never a
		 * Kids profile, and only the main one when Theme Options says so.
		 * Resetting a PIN from the PIN screen ("Forgot PIN?") stays open
		 * because it needs the account password, which is the real gate.
		 */
		$manages = in_array( $action, array( 'save', 'delete', 'keep', 'unlock' ), true ) || ( 'pin' === $action && 'pin' !== $screen );

		if ( $manages && ! self::can_manage_account() ) {
			self::$errors[''] = self::current_is_kid()
				? __( 'Switch to an adult profile to manage profiles.', 'jws_streamvid' )
				: __( 'Switch to the main profile to manage profiles.', 'jws_streamvid' );
			return;
		}

		// A profile with Profile Lock is changed or deleted only after its PIN (or the account password).
		if ( ( $profile_id && in_array( $action, array( 'save', 'delete' ), true ) ) || ( 'pin' === $action && 'edit' === $screen ) ) {
			if ( self::needs_unlock( $user_id, Jws_Profiles::get( $user_id, $profile_id ) ) ) {
				self::$errors[''] = __( 'Enter this profile\'s PIN first.', 'jws_streamvid' );
				return;
			}
		}

		switch ( $action ) {

			case 'switch':
				$pin    = isset( $_POST['pin'] ) ? preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['pin'] ) ) ) : null;
				$result = Jws_Profiles::switch_to( $user_id, $profile_id, $pin );

				if ( is_wp_error( $result ) ) {
					// Switching from a menu without a PIN field: ask for the PIN on its own screen.
					if ( 'profile_pin_required' === $result->get_error_code() && 'pin' !== $screen ) {
						self::go( self::url( 'pin', $profile_id, array( 'back' => self::back_url() ) ) );
					}
					self::$errors[''] = $result->get_error_message();
					return;
				}

				// A Kids profile starts on the Kids Home Page (Theme Options), not where the adult was.
				self::go( $result->is_kid ? Jws_Profiles_Settings::kids_home_url() : self::back_url() );

			case 'save':
				$data = array(
					'name'          => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
					'is_kid'        => ! empty( $_POST['is_kid'] ),
					'autoplay_next' => ! empty( $_POST['autoplay_next'] ),
				);
				if ( isset( $_POST['avatar'] ) ) {
					$data['avatar'] = sanitize_text_field( wp_unslash( $_POST['avatar'] ) );
				}
				if ( isset( $_POST['max_maturity'] ) ) {
					$data['max_maturity'] = absint( wp_unslash( $_POST['max_maturity'] ) );
				}

				self::$posted = $data;

				// Profile Lock is part of this form: check it first, so a wrong password saves nothing.
				$lock = self::lock_change( $user_id, $profile_id );

				if ( is_wp_error( $lock ) ) {
					self::$errors['pin'] = $lock->get_error_message();
					return;
				}

				$result = $profile_id
					? Jws_Profiles::update( $user_id, $profile_id, $data )
					: Jws_Profiles::create( $user_id, $data );

				if ( is_wp_error( $result ) ) {
					self::$errors[ self::error_field( $result ) ] = $result->get_error_message();
					return;
				}

				if ( null !== $lock ) {
					$locked = Jws_Profiles::set_pin( $user_id, $result->id, $lock['pin'], $lock['password'] );

					if ( is_wp_error( $locked ) ) {
						// The profile is saved; show the lock error on its edit screen.
						self::go( self::url( 'edit', $result->id, array( 'notice' => 'pin_failed' ) ) );
					}
				}

				self::go( self::url( 'choose', 0, array( 'manage' => 1, 'notice' => 'saved' ) ) );

			case 'unlock':
				$pin      = isset( $_POST['pin'] ) ? preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['pin'] ) ) ) : '';
				$password = isset( $_POST['account_password'] ) ? (string) wp_unslash( $_POST['account_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passwords are compared, never stored or printed.

				// The account password, or the profile's PIN (which counts wrong tries and locks out like switching does).
				$result = '' !== $password
					? Jws_Profiles::check_account_password( $user_id, $password )
					: Jws_Profiles::verify_pin( $user_id, $profile_id, $pin );

				if ( is_wp_error( $result ) ) {
					self::$errors['unlock'] = $result->get_error_message();
					return;
				}

				self::unlock( $user_id, $profile_id );
				self::go( self::url( 'edit', $profile_id ) );

			case 'delete':
				$result = Jws_Profiles::delete( $user_id, $profile_id );

				if ( is_wp_error( $result ) ) {
					self::$errors[''] = $result->get_error_message();
					return;
				}

				self::go( self::url( 'choose', 0, array( 'manage' => 1, 'notice' => 'deleted' ) ) );

			case 'pin':
				$remove   = ! empty( $_POST['remove_pin'] );
				$pin      = $remove ? '' : ( isset( $_POST['new_pin'] ) ? sanitize_text_field( wp_unslash( $_POST['new_pin'] ) ) : '' );
				$password = isset( $_POST['account_password'] ) ? (string) wp_unslash( $_POST['account_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passwords are compared, never stored or printed.

				if ( ! $remove && '' === $pin ) {
					self::$errors['pin'] = __( 'The PIN must be 4 digits.', 'jws_streamvid' );
					return;
				}

				$result = Jws_Profiles::set_pin( $user_id, $profile_id, $pin, $password );

				if ( is_wp_error( $result ) ) {
					self::$errors['pin'] = $result->get_error_message();
					return;
				}

				$notice = $remove ? 'pin_removed' : 'pin_saved';

				// Reset from the PIN screen: carry on to that screen; otherwise back to the profile's settings.
				if ( 'pin' === $screen ) {
					self::go( $remove
						? self::url( 'choose', 0, array( 'notice' => $notice, 'back' => self::back_url() ) )
						: self::url( 'pin', $profile_id, array( 'notice' => $notice, 'back' => self::back_url() ) ) );
				}

				self::go( self::url( 'edit', $profile_id, array( 'notice' => $notice ) ) );

			case 'keep':
				$keep = isset( $_POST['keep'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['keep'] ) ) : array();
				Jws_Profiles::set_active( $user_id, $keep );

				self::go( self::url( 'choose', 0, array( 'manage' => 1, 'notice' => 'saved' ) ) );
		}
		// phpcs:enable

		self::$errors[''] = __( 'Something went wrong. Try again.', 'jws_streamvid' );
	}

	/**
	 * The Profile Lock part of the add / edit form (sent with lock_form=1):
	 * what to do with the PIN once the profile is saved.
	 *
	 * @return array|null|WP_Error array( pin, password ): pin '' turns the lock
	 *                             off; null leaves it as it is; WP_Error when
	 *                             the PIN or the account password is wrong.
	 */
	private static function lock_change( $user_id, $profile_id ) {

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- handle_post() verified the nonce.
		if ( empty( $_POST['lock_form'] ) ) {
			return null;
		}

		$wanted   = ! empty( $_POST['lock_pin'] );
		$pin      = isset( $_POST['new_pin'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['new_pin'] ) ) ) : '';
		$password = isset( $_POST['account_password'] ) ? (string) wp_unslash( $_POST['account_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passwords are compared, never stored or printed.
		// phpcs:enable

		$profile = $profile_id ? Jws_Profiles::get( $user_id, $profile_id ) : null;
		$has_pin = $profile && $profile->has_pin;

		self::$posted['lock_pin'] = $wanted;

		if ( ! $wanted ) {
			if ( ! $has_pin ) {
				return null;
			}
			$pin = '';
		} elseif ( '' === $pin ) {
			if ( $has_pin ) {
				return null;
			}
			return new WP_Error( 'profile_pin_format', __( 'Enter a 4-digit PIN, or turn Profile Lock off.', 'jws_streamvid' ) );
		} elseif ( ! preg_match( '/^\d{4}$/', $pin ) ) {
			return new WP_Error( 'profile_pin_format', __( 'The PIN must be 4 digits.', 'jws_streamvid' ) );
		}

		$checked = Jws_Profiles::check_account_password( $user_id, $password );

		if ( is_wp_error( $checked ) ) {
			return $checked;
		}

		return array(
			'pin'      => $pin,
			'password' => $password,
		);
	}

	/** Which form field an error from Jws_Profiles belongs to. */
	private static function error_field( WP_Error $error ) {

		$map = array(
			'profile_name'       => 'name',
			'profile_name_taken' => 'name',
			'profile_avatar'     => 'avatar',
			'profile_maturity'   => 'max_maturity',
		);

		return isset( $map[ $error->get_error_code() ] ) ? $map[ $error->get_error_code() ] : '';
	}

	/* ------------------------------------------------------------------ *
	 * Template helpers
	 * ------------------------------------------------------------------ */

	public static function requested_id() {
		return absint( get_query_var( self::ID_VAR ) );
	}

	/** @return string Error for a field ('' = the whole form), or ''. */
	public static function error( $field = '' ) {
		return isset( self::$errors[ $field ] ) ? self::$errors[ $field ] : '';
	}

	/** A value from the form just posted, or $default when nothing was posted. */
	public static function posted( $key, $default ) {
		return array_key_exists( $key, self::$posted ) ? self::$posted[ $key ] : $default;
	}

	/** @return string Message for ?notice=, or ''. */
	public static function notice() {

		$notice = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$messages = array(
			'saved'       => __( 'Profile saved.', 'jws_streamvid' ),
			'deleted'     => __( 'Profile deleted.', 'jws_streamvid' ),
			'pin_saved'   => __( 'Profile Lock is on.', 'jws_streamvid' ),
			'pin_removed' => __( 'Profile Lock is off.', 'jws_streamvid' ),
			'pin_failed'  => __( 'The profile is saved, but Profile Lock did not change. Try again.', 'jws_streamvid' ),
			'limit'       => __( 'Your plan has no room for another profile.', 'jws_streamvid' ),
			'kids'        => __( 'Switch to an adult profile to manage profiles.', 'jws_streamvid' ),
			'main_only'   => __( 'Switch to the main profile to manage profiles.', 'jws_streamvid' ),
		);

		return isset( $messages[ $notice ] ) ? $messages[ $notice ] : '';
	}

	/** The hidden fields every profile form needs. */
	public static function form_fields( $action, $profile_id = 0 ) {

		wp_nonce_field( self::NONCE, '_jws_profiles_nonce' );
		printf( '<input type="hidden" name="jws_profiles_action" value="%s">', esc_attr( $action ) );

		if ( $profile_id ) {
			printf( '<input type="hidden" name="profile_id" value="%d">', (int) $profile_id );
		}
	}

	/** <img> for a profile's avatar. */
	public static function avatar_img( $profile, $size = 64, $class = 'jws-pf-avatar' ) {

		$url = $profile ? Jws_Profiles::avatar_url( $profile->avatar ) : '';

		if ( ! $url ) {
			return get_avatar( get_current_user_id(), $size, '', '', array( 'class' => $class ) );
		}

		return sprintf(
			'<img class="%1$s" src="%2$s" alt="" width="%3$d" height="%3$d" loading="lazy" decoding="async">',
			esc_attr( $class ),
			esc_url( $url ),
			(int) $size
		);
	}

	/** Display name of a maturity level. */
	public static function maturity_label( $age ) {

		$labels = array(
			0  => __( 'G only', 'jws_streamvid' ),
			7  => __( 'PG and below', 'jws_streamvid' ),
			13 => __( 'PG-13 and below', 'jws_streamvid' ),
			17 => __( 'R and below', 'jws_streamvid' ),
			18 => __( 'All maturity ratings', 'jws_streamvid' ),
		);

		return isset( $labels[ (int) $age ] ) ? $labels[ (int) $age ] : $labels[18];
	}

	/** Profile page tabs that change the account: Settings, Devices, Subscription and Profiles. */
	const ACCOUNT_TABS = array( 'dashboard', 'profile', 'devices_manager', 'membership', 'subscriptions', 'profiles' );

	/** How long a locked profile stays open for editing after its PIN. */
	const UNLOCK_TTL = 15 * MINUTE_IN_SECONDS;

	/**
	 * Whether the profile in use may manage the account (its settings,
	 * devices, plan and profiles): never a Kids profile; with Theme Options
	 * "Only the main profile manages the account", only the main profile.
	 */
	public static function can_manage_account() {

		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( ! Jws_Profiles::enabled() ) {
			return true;
		}

		$profile = Jws_Profiles::current_profile();

		if ( $profile && $profile->is_kid ) {
			return false;
		}

		return ! Jws_Profiles_Settings::account_main_only() || ! $profile || $profile->is_main;
	}

	/**
	 * Whether editing or deleting $profile first needs its PIN: it has
	 * Profile Lock, it is not the profile in use, and it was not unlocked in
	 * this sign-in session during the last UNLOCK_TTL.
	 */
	public static function needs_unlock( $user_id, $profile ) {

		if ( ! $profile || ! $profile->has_pin ) {
			return false;
		}

		$current = Jws_Profiles::current_profile( $user_id );

		if ( $current && (int) $current->id === (int) $profile->id ) {
			return false;
		}

		$token = wp_get_session_token();

		return '' === $token || get_transient( self::unlock_key( $user_id, $profile->id ) ) !== wp_hash( $token );
	}

	private static function unlock( $user_id, $profile_id ) {
		set_transient( self::unlock_key( $user_id, $profile_id ), wp_hash( wp_get_session_token() ), self::UNLOCK_TTL );
	}

	private static function unlock_key( $user_id, $profile_id ) {
		return 'jws_pf_unlock_' . (int) $user_id . '_' . (int) $profile_id;
	}

	/**
	 * With "Only the main profile manages the account": another profile is
	 * sent from the account tabs of the profile page (and the membership
	 * billing and cancel pages) to History. Kids mode covers Kids profiles.
	 */
	public static function guard_account() {

		if ( ! is_user_logged_in() || self::screen() || self::current_is_kid() || self::can_manage_account() ) {
			return;
		}

		global $wp_query;

		$blocked = false;

		if ( is_author() && (int) get_queried_object_id() === get_current_user_id() ) {
			foreach ( array( 'dashboard', 'membership', 'profiles' ) as $tab ) {
				if ( isset( $wp_query->query_vars[ $tab ] ) ) {
					$blocked = true;
				}
			}
		} elseif ( is_page() ) {
			$pages   = array_filter( array( (int) get_option( 'pmpro_billing_page_id' ), (int) get_option( 'pmpro_cancel_page_id' ) ) );
			$blocked = $pages && is_page( $pages );
		}

		if ( $blocked ) {
			$history = jws_streamvid()->get()->profile->get_endpoint( get_current_user_id(), 'history', '' );
			wp_safe_redirect( add_query_arg( 'jws_lib', 'main_only', $history ) );
			exit;
		}
	}

	public static function current_is_kid() {
		$profile = Jws_Profiles::current_profile();
		return $profile && $profile->is_kid;
	}

	/* ------------------------------------------------------------------ *
	 * Page output
	 * ------------------------------------------------------------------ */

	public static function body_class( $classes ) {

		if ( self::current_is_kid() ) {
			$classes[] = 'jws-kids-mode';
		}

		return $classes;
	}

	public static function enqueue() {

		$v3 = is_author() && function_exists( 'jws_theme_get_option' ) && 'v3' === jws_theme_get_option( 'profile_layout' );

		// profiles.css: the header menu, Kids bar and "Who's watching?" modal, on every page for logged-in users.
		// profile-page.css: the profile page, added below. The V3 profile page loads both for everyone.
		if ( ! is_user_logged_in() && ! $v3 ) {
			return;
		}

		$css = JWS_STREAMVID_PATH_PUBLIC . '/assets/css/profiles.css';

		wp_enqueue_style(
			'jws-profiles',
			JWS_STREAMVID_URL_PUBLIC_ASSETS . '/css/profiles.css',
			array(),
			file_exists( $css ) ? (string) filemtime( $css ) : JWS_STREAMVID_VERSION
		);

		// The profile page and its tabs, on profile pages only (the Profiles tab is on every Profile Version).
		if ( is_author() ) {
			$page_css = JWS_STREAMVID_PATH_PUBLIC . '/assets/css/profile-page.css';

			wp_enqueue_style(
				'jws-profile-page',
				JWS_STREAMVID_URL_PUBLIC_ASSETS . '/css/profile-page.css',
				array( 'jws-profiles' ),
				file_exists( $page_css ) ? (string) filemtime( $page_css ) : JWS_STREAMVID_VERSION
			);
		}

		// Header account menu on phones: placed under the avatar, also with a sticky header.
		if ( is_user_logged_in() ) {
			$menu = JWS_STREAMVID_PATH_PUBLIC . '/assets/js/account-menu.js';

			wp_enqueue_script(
				'jws-account-menu',
				JWS_STREAMVID_URL_PUBLIC_ASSETS . '/js/account-menu.js',
				array(),
				file_exists( $menu ) ? (string) filemtime( $menu ) : JWS_STREAMVID_VERSION,
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
		}

		// The "Who's watching?" modal: wherever a signed-in member can switch or manage profiles.
		if ( is_user_logged_in() && Jws_Profiles::enabled() ) {
			$modal = JWS_STREAMVID_PATH_PUBLIC . '/assets/js/profiles-modal.js';

			wp_enqueue_script(
				'jws-profiles-modal',
				JWS_STREAMVID_URL_PUBLIC_ASSETS . '/js/profiles-modal.js',
				array(),
				file_exists( $modal ) ? (string) filemtime( $modal ) : JWS_STREAMVID_VERSION,
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);

			wp_localize_script(
				'jws-profiles-modal',
				'jwsProfilesModal',
				self::modal_config( self::gate_url() )
			);
		}

		if ( $v3 ) {
			$js = JWS_STREAMVID_PATH_PUBLIC . '/assets/js/profiles.js';

			wp_enqueue_script(
				'jws-profiles',
				JWS_STREAMVID_URL_PUBLIC_ASSETS . '/js/profiles.js',
				array(),
				file_exists( $js ) ? (string) filemtime( $js ) : JWS_STREAMVID_VERSION,
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);

			// The sidebar is a .jws_sticky_move like the theme's own sidebars; the theme loads its Sticky-kit on shop pages only.
			$sticky = get_template_directory() . '/assets/js/sticky_content.js';

			if ( file_exists( $sticky ) ) {
				wp_enqueue_script( 'stick-content', get_template_directory_uri() . '/assets/js/sticky_content.js', array( 'jquery' ), (string) filemtime( $sticky ), true );
			}
		}
	}

	/**
	 * Profile V3 tabs that profiles.js loads in place send "X-Jws-Pv3: 1".
	 * Such a request gets only the profile block, the page title and any file
	 * a tab enqueued while it rendered; no theme header, footer or page-wide
	 * assets. Runs just before the profile page itself (the_main, priority
	 * 20), so every guard and form handler before it has already run.
	 */
	public static function fragment() {

		$asked = isset( $_SERVER['HTTP_X_JWS_PV3'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_JWS_PV3'] ) ) : '';

		if ( '1' !== $asked || ! is_author() || ! class_exists( 'Jws_Profile_Library' ) || ! Jws_Profile_Library::is_v3() ) {
			return;
		}

		// The same URL also serves the full page, so a page cache must never keep this answer.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		header( 'Vary: X-Jws-Pv3', false );
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );

		// Register what a normal page registers (dropping anything a callback echoes), then keep only what the tab adds while it renders.
		ob_start();
		do_action( 'wp_enqueue_scripts' );
		ob_end_clean();

		$styles  = wp_styles()->queue;
		$scripts = wp_scripts()->queue;

		ob_start();
		jws_streamvid_load_template( 'user/profile/v3/layout.php', false );
		$block = ob_get_clean();

		echo '<title>' . esc_html( wp_get_document_title() ) . "</title>\n";

		$late_styles = array_diff( wp_styles()->queue, $styles );
		if ( $late_styles ) {
			wp_print_styles( $late_styles );
		}

		echo $block; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template output, escaped in the templates.

		$late_scripts = array_diff( wp_scripts()->queue, $scripts );
		if ( $late_scripts ) {
			wp_print_scripts( $late_scripts );
		}

		exit;
	}

	/** Settings for profiles-modal.js (window.jwsProfilesModal). */
	private static function modal_config( $gate = '' ) {

		return array(
			'screen'   => self::url(),
			'queryVar' => get_option( 'permalink_structure' ) ? '' : self::QUERY_VAR,
			'gate'     => $gate,
			'i18n'     => array(
				'dialog' => __( 'Profiles', 'jws_streamvid' ),
				'close'  => __( 'Close', 'jws_streamvid' ),
				'error'  => __( 'Something went wrong. Try again.', 'jws_streamvid' ),
			),
		);
	}

	/**
	 * A sign-in from the theme's form (jws_login_ajax), with Theme Options
	 * "Ask right after sign-in" on: when this account is asked
	 * "Who's watching?", the answer carries `profiles`, the screen URL
	 * coming back to the page the form goes to. The page then opens the
	 * modal at once (jwsOpenProfiles, below) and goes there once a profile
	 * is picked, instead of loading that page first and asking on it.
	 *
	 * @param array   $data code, message, redirect.
	 * @param WP_User $user Who signed in.
	 */
	public static function login_response( $data, $user ) {

		// Off: the sign-in goes straight on; home, search and title pages ask later.
		if ( ! is_array( $data ) || ! $user instanceof WP_User || ! Jws_Profiles::enabled() || ! Jws_Profiles_Settings::gate_on_login() ) {
			return $data;
		}

		if ( Jws_Profiles::count( $user->ID ) < 2 && ! Jws_Profiles_Settings::gate_always() ) {
			return $data;
		}

		$target = ! empty( $data['redirect'] ) ? (string) $data['redirect'] : (string) wp_get_referer();
		$target = '' !== $target ? wp_validate_redirect( $target, home_url( '/' ) ) : home_url( '/' );

		// A sign-in on the way to paying goes straight back to it.
		if ( self::is_payment_url( $target ) ) {
			return $data;
		}

		// The theme adds redirect=true when it goes to `redirect` itself.
		$back = ! empty( $data['redirect'] ) ? add_query_arg( 'redirect', 'true', $target ) : $target;

		$data['profiles'] = self::url( 'choose', 0, array( 'back' => $back ) );

		return $data;
	}

	/** Whether $url is a cart, checkout or payment page (the URL form of is_payment_page()). */
	private static function is_payment_url( $url ) {

		$ids = array();

		if ( function_exists( 'wc_get_page_id' ) ) {
			$ids[] = wc_get_page_id( 'checkout' );
			$ids[] = wc_get_page_id( 'cart' );
		}
		if ( ! empty( $GLOBALS['pmpro_pages'] ) && is_array( $GLOBALS['pmpro_pages'] ) ) {
			$ids = array_merge( $ids, array_values( array_intersect_key( $GLOBALS['pmpro_pages'], array_flip( array( 'checkout', 'confirmation', 'billing', 'cancel', 'invoice' ) ) ) ) );
		}

		$path    = trailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		$payment = false;

		foreach ( array_filter( array_map( 'intval', $ids ), function ( $id ) { return $id > 0; } ) as $id ) {
			$page = trailingslashit( (string) wp_parse_url( (string) get_permalink( $id ), PHP_URL_PATH ) );

			// The page itself or one of its endpoints (checkout/order-received/123/).
			if ( '/' !== $page && 0 === strpos( $path, $page ) ) {
				$payment = true;
				break;
			}
		}

		/**
		 * Filters whether a sign-in going to $url skips "Who's watching?" (cart, checkout, payment).
		 *
		 * @param bool   $payment True for cart, checkout and payment pages.
		 * @param string $url     Where the sign-in goes.
		 */
		return (bool) apply_filters( 'jws_profiles_is_payment_url', $payment, $url );
	}

	/**
	 * For visitors not signed in yet: window.jwsOpenProfiles( url, fallback ),
	 * which the theme's sign-in form calls with `profiles` (login_response()).
	 * It loads the modal's styles and script only then, and opens it as the
	 * gate; fallback (go to the page as before) runs if they cannot load.
	 */
	public static function login_loader() {

		if ( is_user_logged_in() || ! Jws_Profiles::enabled() ) {
			return;
		}

		$css  = JWS_STREAMVID_PATH_PUBLIC . '/assets/css/profiles.css';
		$js   = JWS_STREAMVID_PATH_PUBLIC . '/assets/js/profiles-modal.js';
		$data = array(
			'css'    => add_query_arg( 'ver', file_exists( $css ) ? filemtime( $css ) : JWS_STREAMVID_VERSION, JWS_STREAMVID_URL_PUBLIC_ASSETS . '/css/profiles.css' ),
			'js'     => add_query_arg( 'ver', file_exists( $js ) ? filemtime( $js ) : JWS_STREAMVID_VERSION, JWS_STREAMVID_URL_PUBLIC_ASSETS . '/js/profiles-modal.js' ),
			'config' => self::modal_config(),
		);

		$script = 'window.jwsOpenProfiles = function ( url, fallback ) {
	var a = ' . wp_json_encode( $data ) . ', done = false;
	var giveUp = function () { if ( ! done ) { done = true; fallback(); } };
	var timer = setTimeout( giveUp, 10000 );
	var run = function () {
		window.jwsProfilesModal = Object.assign( {}, a.config, { gate: url, fallback: giveUp } );
		var s = document.createElement( "script" );
		s.src = a.js;
		s.onload = function () { clearTimeout( timer ); };
		s.onerror = function () { clearTimeout( timer ); giveUp(); };
		document.body.appendChild( s );
	};
	var l = document.createElement( "link" );
	l.rel = "stylesheet";
	l.href = a.css;
	l.onload = run;
	l.onerror = run;
	document.head.appendChild( l );
};';

		wp_print_inline_script_tag( $script, array( 'id' => 'jws-profiles-login' ) );
	}

	/** Adds the Profiles tab to the profile page menu; the Profile Menu sorter can move or hide it. */
	public static function profile_tab( $items ) {

		if ( ! Jws_Profiles::enabled() ) {
			return $items;
		}

		$items['profiles'] = array(
			'title'    => esc_html__( 'Profiles', 'jws_streamvid' ),
			'icon'     => '',
			'callback' => function () {
				jws_streamvid_load_template( 'user/profile/profiles.php' );
			},
			'priority' => 45,
		);

		return $items;
	}
}

add_action( 'init', array( 'Jws_Profiles_Front', 'add_rewrites' ) );
add_filter( 'query_vars', array( 'Jws_Profiles_Front', 'query_vars' ) );
add_action( 'wp_loaded', array( 'Jws_Profiles_Front', 'maybe_flush' ) );
add_action( 'template_redirect', array( 'Jws_Profiles_Front', 'route' ), 1 );
add_action( 'template_redirect', array( 'Jws_Profiles_Front', 'fragment' ), 19 );
add_filter( 'body_class', array( 'Jws_Profiles_Front', 'body_class' ) );
add_action( 'wp_enqueue_scripts', array( 'Jws_Profiles_Front', 'enqueue' ), 20 );
add_filter( 'streamvid_profile_default_menu_items', array( 'Jws_Profiles_Front', 'profile_tab' ) );

if ( ! function_exists( 'jws_streamvid_account_menu' ) ) {
	/**
	 * Prints the header account menu: the profile in use, switching to
	 * another profile, then the account links. The theme's Account widget
	 * calls this when it exists.
	 */
	function jws_streamvid_account_menu() {
		jws_streamvid_load_template( 'user/profiles/account-menu.php', false );
	}
}

if ( ! function_exists( 'jws_streamvid_profile_avatar' ) ) {
	/** <img> of the profile in use, for the header account button. */
	function jws_streamvid_profile_avatar( $size = 36 ) {
		if ( ! Jws_Profiles::enabled() ) {
			return get_avatar( get_current_user_id(), $size, '', '', array( 'class' => 'img-thumbnail avatar' ) );
		}
		return Jws_Profiles_Front::avatar_img( Jws_Profiles::current_profile(), $size, 'avatar jws-pf-avatar' );
	}
}
add_filter( 'jws_login_response', array( 'Jws_Profiles_Front', 'login_response' ), 10, 2 );
add_action( 'wp_footer', array( 'Jws_Profiles_Front', 'login_loader' ), 30 );
add_action( 'template_redirect', array( 'Jws_Profiles_Front', 'guard_account' ), 7 );
