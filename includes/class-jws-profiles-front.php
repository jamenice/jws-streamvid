<?php

/**
 * Front end of viewer profiles: the "Who's watching?" screens, the forms
 * behind them, and the profile part of the header account menu.
 *
 * Screens live under one URL, /whos-watching/ by default:
 *
 *     /whos-watching/            choose a profile (?manage=1 to edit them)
 *     /whos-watching/pin/{id}/   enter a profile's PIN
 *     /whos-watching/add/        add a profile
 *     /whos-watching/edit/{id}/  edit a profile, its PIN, or delete it
 *
 * Every action is a plain form POST to the screen it is on, checked in the
 * order nonce, logged-in user, sanitize, then handed to Jws_Profiles, which
 * owns all validation and the SQL. A success redirects (post/redirect/get);
 * an error renders the same screen again with the message and the values
 * typed. Nothing here needs JavaScript.
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

		return wp_validate_redirect( $back, home_url( '/' ) );
	}

	/**
	 * Runs the profile screens, or sends a viewer who has not picked a
	 * profile yet to "Who's watching?".
	 */
	public static function route() {

		$screen = self::screen();

		if ( ! $screen ) {
			self::maybe_gate();
			return;
		}

		nocache_headers();

		if ( ! Jws_Profiles::enabled() ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( self::url() ) );
			exit;
		}

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			self::handle_post( $screen );
		}

		$user_id = get_current_user_id();

		if ( in_array( $screen, array( 'pin', 'edit' ), true ) && ! Jws_Profiles::get( $user_id, self::requested_id() ) ) {
			wp_safe_redirect( self::url() );
			exit;
		}

		if ( in_array( $screen, array( 'add', 'edit' ), true ) && self::current_is_kid() ) {
			wp_safe_redirect( self::url( 'choose', 0, array( 'notice' => 'kids' ) ) );
			exit;
		}

		if ( 'add' === $screen && ! Jws_Profiles::can_create( $user_id ) && empty( self::$errors ) ) {
			wp_safe_redirect( self::url( 'choose', 0, array( 'manage' => 1, 'notice' => 'limit' ) ) );
			exit;
		}

		status_header( 200 );
	}

	/**
	 * Accounts with two or more profiles pick one before browsing. Only
	 * pages where the profile changes what is shown ask: home, search,
	 * titles and their archives. Account, checkout and payment pages never
	 * do, so a payment return or a login redirect is not interrupted.
	 */
	private static function maybe_gate() {

		if ( ! Jws_Profiles::enabled() || ! is_user_logged_in() || ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}
		if ( wp_doing_ajax() || is_feed() || is_embed() || is_robots() || is_preview() || isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! self::is_browse_page() ) {
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

		wp_safe_redirect( self::url( 'choose', 0, array( 'back' => $here ) ) );
		exit;
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
		 * A Kids profile cannot change profiles. Resetting a PIN stays open
		 * ("Forgot PIN?" on the PIN screen) because it needs the account
		 * password, which is the real gate.
		 */
		if ( in_array( $action, array( 'save', 'delete', 'keep' ), true ) && self::current_is_kid() ) {
			self::$errors[''] = __( 'Switch to an adult profile to manage profiles.', 'jws_streamvid' );
			return;
		}

		switch ( $action ) {

			case 'switch':
				$pin    = isset( $_POST['pin'] ) ? preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['pin'] ) ) ) : null;
				$result = Jws_Profiles::switch_to( $user_id, $profile_id, $pin );

				if ( is_wp_error( $result ) ) {
					// Switching from a menu without a PIN field: ask for the PIN on its own screen.
					if ( 'profile_pin_required' === $result->get_error_code() && 'pin' !== $screen ) {
						wp_safe_redirect( self::url( 'pin', $profile_id, array( 'back' => self::back_url() ) ) );
						exit;
					}
					self::$errors[''] = $result->get_error_message();
					return;
				}

				// A Kids profile starts on the Kids Home Page (Theme Options), not where the adult was.
				wp_safe_redirect( $result->is_kid ? Jws_Profiles_Settings::kids_home_url() : self::back_url() );
				exit;

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

				$result = $profile_id
					? Jws_Profiles::update( $user_id, $profile_id, $data )
					: Jws_Profiles::create( $user_id, $data );

				if ( is_wp_error( $result ) ) {
					self::$errors[ self::error_field( $result ) ] = $result->get_error_message();
					return;
				}

				wp_safe_redirect( self::url( 'choose', 0, array( 'manage' => 1, 'notice' => 'saved' ) ) );
				exit;

			case 'delete':
				$result = Jws_Profiles::delete( $user_id, $profile_id );

				if ( is_wp_error( $result ) ) {
					self::$errors[''] = $result->get_error_message();
					return;
				}

				wp_safe_redirect( self::url( 'choose', 0, array( 'manage' => 1, 'notice' => 'deleted' ) ) );
				exit;

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
					wp_safe_redirect( $remove
						? self::url( 'choose', 0, array( 'notice' => $notice, 'back' => self::back_url() ) )
						: self::url( 'pin', $profile_id, array( 'notice' => $notice, 'back' => self::back_url() ) ) );
					exit;
				}

				wp_safe_redirect( self::url( 'edit', $profile_id, array( 'notice' => $notice ) ) );
				exit;

			case 'keep':
				$keep = isset( $_POST['keep'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['keep'] ) ) : array();
				Jws_Profiles::set_active( $user_id, $keep );

				wp_safe_redirect( self::url( 'choose', 0, array( 'manage' => 1, 'notice' => 'saved' ) ) );
				exit;
		}
		// phpcs:enable

		self::$errors[''] = __( 'Something went wrong. Try again.', 'jws_streamvid' );
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
			'limit'       => __( 'Your plan has no room for another profile.', 'jws_streamvid' ),
			'kids'        => __( 'Switch to an adult profile to manage profiles.', 'jws_streamvid' ),
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

	public static function current_is_kid() {
		$profile = Jws_Profiles::current_profile();
		return $profile && $profile->is_kid;
	}

	/* ------------------------------------------------------------------ *
	 * Page output
	 * ------------------------------------------------------------------ */

	public static function template( $template ) {

		if ( ! self::screen() ) {
			return $template;
		}

		$file = trailingslashit( JWS_STREAMVID_PATH_PUBLIC ) . 'user/profiles/screen.php';

		return file_exists( $file ) ? $file : $template;
	}

	public static function document_title( $title ) {

		$titles = array(
			'choose' => __( "Who's watching?", 'jws_streamvid' ),
			'pin'    => __( 'Profile Lock', 'jws_streamvid' ),
			'add'    => __( 'Add Profile', 'jws_streamvid' ),
			'edit'   => __( 'Edit Profile', 'jws_streamvid' ),
		);
		$screen = self::screen();

		return $screen ? $titles[ $screen ] . ' – ' . get_bloginfo( 'name' ) : $title;
	}

	public static function body_class( $classes ) {

		if ( self::screen() ) {
			$classes[] = 'jws-profiles-screen';
		}
		if ( self::current_is_kid() ) {
			$classes[] = 'jws-kids-mode';
		}

		return $classes;
	}

	public static function enqueue() {

		$v3 = is_author() && function_exists( 'jws_theme_get_option' ) && 'v3' === jws_theme_get_option( 'profile_layout' );

		// The header menu needs the styles on every page for logged-in users; the V3 profile page for everyone.
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
		}
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

	/** Keeps search engines off the profile screens. */
	public static function robots( $robots ) {

		if ( self::screen() ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}

		return $robots;
	}
}

add_action( 'init', array( 'Jws_Profiles_Front', 'add_rewrites' ) );
add_filter( 'query_vars', array( 'Jws_Profiles_Front', 'query_vars' ) );
add_action( 'wp_loaded', array( 'Jws_Profiles_Front', 'maybe_flush' ) );
add_action( 'template_redirect', array( 'Jws_Profiles_Front', 'route' ), 1 );
add_filter( 'template_include', array( 'Jws_Profiles_Front', 'template' ), 99 );
add_filter( 'pre_get_document_title', array( 'Jws_Profiles_Front', 'document_title' ), 20 );
add_filter( 'body_class', array( 'Jws_Profiles_Front', 'body_class' ) );
add_action( 'wp_enqueue_scripts', array( 'Jws_Profiles_Front', 'enqueue' ), 20 );
add_filter( 'wp_robots', array( 'Jws_Profiles_Front', 'robots' ) );
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
