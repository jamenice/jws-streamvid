<?php
/**
 * Admin login address (Settings > General > Admin login address).
 *
 * With an address set, for example "security-admin":
 *
 *  - the admin sign-in form is at /security-admin/ (wp-login.php, served there);
 *  - visitors who are not signed in get the 404 page at /wp-admin/ and at
 *    wp-login.php, which only still answers the few links WordPress sends out
 *    with a key or a nonce: log out, the password reset email, privacy request
 *    confirmations, and the password form of protected posts;
 *  - /login, /admin and /dashboard no longer redirect to the sign-in form;
 *  - public pages never show the address: sign-in and register links go to
 *    the theme's Sign In page, and the other links keep wp-login.php;
 *  - in the dashboard and on the address itself, links use the address.
 *
 * Signed-in users reach /wp-admin/ as before. admin-ajax.php and
 * admin-post.php stay open, because the front end runs on them.
 *
 * Locked out: sign in with the site's own sign-in form (the theme's popup or
 * Sign In page), or add define( 'JWS_HIDE_LOGIN_OFF', true ); to wp-config.php.
 *
 * Needs pretty permalinks (Settings > Permalinks, anything but Plain): the
 * address is a path, which the web server only hands to WordPress when
 * permalinks are on.
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Jws_Hide_Login {

	const OPTION = 'jws_login_slug';

	/** Addresses that WordPress, the theme or a common plugin already answer. */
	const RESERVED = array( 'wp-admin', 'wp-login', 'wp-login-php', 'wp-content', 'wp-includes', 'wp-json', 'admin', 'login', 'dashboard', 'feed', 'sign-in', 'lost-password' );

	/**
	 * wp-login.php actions that arrive from links sent out with a key or a
	 * nonce. They keep wp-login.php in the link (so emails do not carry the
	 * address) and wp-login.php still answers them.
	 */
	const OPEN_ACTIONS = array( 'logout', 'rp', 'resetpass', 'confirmaction', 'postpass' );

	/** This request is the address. */
	private static $is_login = false;

	/** This request is wp-login.php or wp-signup.php, reached directly. */
	private static $is_direct = false;

	public static function boot() {

		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );

		if ( ! self::slug() ) {
			return;
		}

		// The plugin file is read before plugins_loaded, so this runs for this request.
		add_action( 'plugins_loaded', array( __CLASS__, 'route' ), 1 );
		add_action( 'wp_loaded', array( __CLASS__, 'serve' ), 1 );

		add_filter( 'site_url', array( __CLASS__, 'filter_site_url' ), 10, 1 );
		add_filter( 'network_site_url', array( __CLASS__, 'filter_site_url' ), 10, 1 );
		add_filter( 'wp_redirect', array( __CLASS__, 'filter_redirect' ), 10, 1 );
		add_filter( 'login_url', array( __CLASS__, 'front_login_url' ), 20, 2 );
		add_filter( 'register_url', array( __CLASS__, 'front_register_url' ), 20 );
		add_filter( 'logout_redirect', array( __CLASS__, 'logout_redirect' ), 10, 2 );

		// /login, /admin and /dashboard would redirect straight to the address.
		remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );
	}

	/** The address without slashes, or '' when the feature is off. */
	public static function slug() {

		if ( defined( 'JWS_HIDE_LOGIN_OFF' ) && JWS_HIDE_LOGIN_OFF ) {
			return '';
		}

		return sanitize_title( (string) get_option( self::OPTION, '' ) );
	}

	public static function login_url_base() {
		return user_trailingslashit( home_url( '/' . self::slug() ) );
	}

	/* ---------------------------------------------------------------------- */
	/* Request routing                                                         */
	/* ---------------------------------------------------------------------- */

	/** The request path relative to the home URL, without slashes. */
	private static function request_path() {

		$path = (string) wp_parse_url( rawurldecode( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only compared, never printed.
		$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		$path = trim( $path, '/' );

		if ( '' !== $home && 0 === strpos( $path . '/', $home . '/' ) ) {
			$path = trim( substr( $path, strlen( $home ) ), '/' );
		}

		return $path;
	}

	/** On plugins_loaded: is this the address, or wp-login.php reached directly? */
	public static function route() {

		global $pagenow;

		$path = self::request_path();

		if ( self::slug() === $path ) {
			self::$is_login = true;
			$pagenow        = 'wp-login.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- core reads it to treat this as the login screen.
			return;
		}

		if ( ! is_admin() && in_array( basename( $path ), array( 'wp-login.php', 'wp-signup.php' ), true ) ) {
			self::$is_direct = true;
		}
	}

	/** The action of this request, as wp-login.php reads it. */
	private static function action() {
		// phpcs:ignore WordPress.Security.NonceVerification -- read-only: wp-login.php checks the nonce or key itself.
		return isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
	}

	/** wp-login.php reached directly, for one of the links it still answers. */
	private static function is_open_direct_request() {

		// phpcs:disable WordPress.Security.NonceVerification -- read-only: wp-login.php checks keys and nonces itself.
		if ( 'wp-login.php' !== basename( self::request_path() ) ) {
			return false; // wp-signup.php only ever leads to registration.
		}

		switch ( self::action() ) {
			case '':
			case 'login':
				// A sign-in form on one of the site's pages (Paid Memberships
				// Pro's Log In page posts here). Typed straight at wp-login.php,
				// it stays closed.
				return 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared only.
					&& isset( $_POST['log'], $_POST['pwd'] )
					&& self::from_own_page();
			case 'postpass':
				return isset( $_POST['post_password'] );
			case 'logout':
				return is_user_logged_in();
			case 'rp':
			case 'resetpass':
				return true; // An invalid key is sent on to "lostpassword", which is closed.
			case 'confirmaction':
				return isset( $_GET['request_id'], $_GET['confirm_key'] );
		}
		// phpcs:enable WordPress.Security.NonceVerification

		return false;
	}

	/** The request came from a page of this site that is not wp-login.php itself. */
	private static function from_own_page() {

		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( '' === $referer || false !== strpos( $referer, 'wp-login.php' ) ) {
			return false;
		}

		return wp_parse_url( $referer, PHP_URL_HOST ) === wp_parse_url( home_url(), PHP_URL_HOST );
	}

	/** On wp_loaded: answer the request routed above, or keep /wp-admin/ closed. */
	public static function serve() {

		global $pagenow;

		if ( self::$is_direct ) {
			if ( ! self::is_open_direct_request() ) {
				self::not_found();
			}
			// A failed sign-in from a page of the site goes back to that page,
			// not to wp-login.php's own screen (whose form is closed).
			add_action( 'wp_login_failed', array( __CLASS__, 'back_after_failed_login' ), 20 );
			return;
		}

		if ( is_admin() && ! is_user_logged_in() && ! wp_doing_ajax() && ! wp_doing_cron() && ! ( defined( 'WP_CLI' ) && WP_CLI ) && 'admin-post.php' !== $pagenow ) {
			wp_safe_redirect( home_url( '/404/' ) );
			exit;
		}

		if ( ! self::$is_login ) {
			return;
		}

		// Signed in already and nothing to do (no log out, no reset): the dashboard.
		// phpcs:ignore WordPress.Security.NonceVerification -- read-only.
		if ( is_user_logged_in() && '' === self::action() && empty( $_REQUEST['interim-login'] ) ) {
			wp_safe_redirect( admin_url() );
			exit;
		}

		// wp-login.php expects these in the global scope.
		global $error, $interim_login, $action, $user_login;

		require_once ABSPATH . 'wp-login.php';
		exit;
	}

	/**
	 * After Paid Memberships Pro's own handler (priority 10), which does the
	 * same for its Log In page when one is set. ?action=failed is what its
	 * [pmpro_login] form reads to show the error.
	 */
	public static function back_after_failed_login() {

		if ( ! self::from_own_page() ) {
			return;
		}

		$referer = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- checked by from_own_page().

		wp_safe_redirect( add_query_arg( 'action', 'failed', $referer ) );
		exit;
	}

	/** The theme's 404 page, through the normal template loader. */
	private static function not_found() {

		global $pagenow;

		$pagenow                = 'index.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$_SERVER['REQUEST_URI'] = user_trailingslashit( '/' . str_repeat( '-/', 10 ) );

		if ( ! defined( 'WP_USE_THEMES' ) ) {
			define( 'WP_USE_THEMES', true );
		}

		wp();
		status_header( 404 );
		nocache_headers();
		require_once ABSPATH . WPINC . '/template-loader.php';
		exit;
	}

	/* ---------------------------------------------------------------------- */
	/* Links                                                                   */
	/* ---------------------------------------------------------------------- */

	/** A wp-login.php URL that keeps wp-login.php (see OPEN_ACTIONS). */
	private static function is_open_url( $url ) {

		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
		parse_str( $query, $args );

		return isset( $args['action'] ) && in_array( $args['action'], self::OPEN_ACTIONS, true );
	}

	private static function to_address( $url ) {
		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
		return self::login_url_base() . ( '' !== $query ? '?' . $query : '' );
	}

	/**
	 * wp-login.php in a URL WordPress builds: the address, in the dashboard and
	 * on the address itself. Public pages keep wp-login.php (their sign-in
	 * and register links are changed below), so the address is never in their
	 * source.
	 */
	public static function filter_site_url( $url ) {

		if ( ! is_string( $url ) || false === strpos( $url, 'wp-login.php' ) || self::is_open_url( $url ) ) {
			return $url;
		}

		if ( ! is_admin() && ! self::$is_login ) {
			return $url;
		}

		return self::to_address( $url );
	}

	/** Redirects to wp-login.php from the dashboard or the address go to the address. */
	public static function filter_redirect( $location ) {

		if ( ! is_string( $location ) || false === strpos( $location, 'wp-login.php' ) || self::is_open_url( $location ) ) {
			return $location;
		}

		if ( ! is_admin() && ! self::$is_login ) {
			return $location;
		}

		return self::to_address( $location );
	}

	/**
	 * Sign-in links on public pages ("Sign in to watch", a comment form's
	 * "Log in") go to the theme's Sign In page, else the home page. In the
	 * dashboard (an expired session asks to sign in again there) and on the
	 * address itself, the address is kept.
	 */
	public static function front_login_url( $login_url, $redirect ) {

		if ( is_admin() || self::$is_login ) {
			return $login_url;
		}

		$page = self::sign_in_page_url();

		if ( ! $page ) {
			return home_url( '/' );
		}

		return $redirect ? add_query_arg( 'redirect_to', rawurlencode( $redirect ), $page ) : $page;
	}

	/** Register links on public pages: the Sign In page, opened on Register. */
	public static function front_register_url( $url ) {

		if ( is_admin() || self::$is_login ) {
			return $url;
		}

		$page = self::sign_in_page_url();

		return $page ? add_query_arg( 'action', 'register', $page ) : home_url( '/' );
	}

	/**
	 * After logging out with no page asked for, the home page: the default,
	 * wp-login.php?loggedout=true, is closed. From the address it stays there.
	 */
	public static function logout_redirect( $redirect_to, $requested ) {

		if ( '' !== (string) $requested || self::$is_login ) {
			return $redirect_to;
		}

		return home_url( '/' );
	}

	/** A published page on the theme's Sign In template, else the page "sign-in". */
	private static function sign_in_page_url() {

		static $url = null;

		if ( null !== $url ) {
			return $url;
		}

		$url = '';

		$ids = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 5,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => 'page-sign-in.php', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		foreach ( $ids as $id ) {
			// The Lost Password page can use the same template; it is not the sign-in form.
			if ( ! has_shortcode( (string) get_post_field( 'post_content', $id ), 'jws_lost_password_form' ) ) {
				$url = get_permalink( $id );
				break;
			}
		}

		if ( ! $url ) {
			$page = get_page_by_path( 'sign-in' );
			if ( $page && 'publish' === $page->post_status ) {
				$url = get_permalink( $page );
			}
		}

		return $url;
	}

	/* ---------------------------------------------------------------------- */
	/* Setting                                                                 */
	/* ---------------------------------------------------------------------- */

	public static function register_setting() {

		register_setting(
			'general',
			self::OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => '',
			)
		);

		add_settings_field(
			self::OPTION,
			'<label for="' . esc_attr( self::OPTION ) . '">' . esc_html__( 'Admin login address', 'jws_streamvid' ) . '</label>',
			array( __CLASS__, 'render_field' ),
			'general'
		);
	}

	public static function render_field() {

		$value = (string) get_option( self::OPTION, '' );
		?>
		<code><?php echo esc_html( trailingslashit( home_url() ) ); ?></code>
		<input type="text" class="regular-text code" id="<?php echo esc_attr( self::OPTION ); ?>" name="<?php echo esc_attr( self::OPTION ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="security-admin" style="width: 16em">
		<code>/</code>
		<p class="description">
			<?php esc_html_e( 'Moves the admin sign-in form to this address. Visitors who are not signed in then get the 404 page at wp-login.php and /wp-admin/. Signed-in users reach the dashboard as before. Leave empty to turn this off.', 'jws_streamvid' ); ?>
		</p>
		<?php if ( self::slug() ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: the admin sign-in address. */
					esc_html__( 'Sign in to the admin at %s. Bookmark it.', 'jws_streamvid' ),
					'<a href="' . esc_url( self::login_url_base() ) . '"><code>' . esc_html( self::login_url_base() ) . '</code></a>'
				);
				?>
			</p>
		<?php elseif ( '' !== $value ) : ?>
			<p class="description">
				<?php esc_html_e( 'Turned off in wp-config.php (JWS_HIDE_LOGIN_OFF).', 'jws_streamvid' ); ?>
			</p>
		<?php endif; ?>
		<p class="description">
			<?php esc_html_e( 'Locked out? Sign in with the site\'s own sign-in form, or add define( \'JWS_HIDE_LOGIN_OFF\', true ); to wp-config.php. Needs pretty permalinks.', 'jws_streamvid' ); ?>
		</p>
		<?php
	}

	/**
	 * Lowercase letters, numbers and dashes. An address that WordPress or a
	 * page already answers is refused, and the previous one is kept.
	 */
	public static function sanitize( $value ) {

		$old  = (string) get_option( self::OPTION, '' );
		$slug = sanitize_title( (string) $value );

		if ( '' === $slug ) {
			return '';
		}

		$taken = in_array( $slug, self::RESERVED, true ) || get_page_by_path( $slug ) || get_page_by_path( $slug, OBJECT, 'post' );

		if ( $taken ) {
			add_settings_error(
				self::OPTION,
				'jws_login_slug_taken',
				sprintf(
					/* translators: %s: the address that was entered. */
					esc_html__( 'The admin login address "%s" is already used by WordPress or a page. Choose another one.', 'jws_streamvid' ),
					$slug
				)
			);
			return $old;
		}

		return $slug;
	}
}
