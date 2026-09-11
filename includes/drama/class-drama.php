<?php

/**
 * Short drama module.
 *
 * Self-contained: post types, ACF fields, the coin wallet and the admin screens
 * all live under includes/drama/ and hook themselves up from here. Nothing
 * outside this folder is touched, so the module can be switched off by not
 * calling Jws_Drama::boot().
 *
 * This first pass is data + admin only. The wallet exposes the whole access
 * rule through Jws_Drama_Wallet::access()/unlock(), which is what the web
 * templates, admin-ajax and the Flutter REST API should all call later rather
 * than re-deriving it.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/drama
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Drama {

	public static function boot() {

		$dir = plugin_dir_path( __FILE__ );

		require_once $dir . 'class-drama-install.php'; 
		require_once $dir . 'class-drama-post-types.php';
		require_once $dir . 'class-drama-settings.php';
		require_once $dir . 'class-drama-wallet.php';
		require_once $dir . 'class-drama-orders.php';
		require_once $dir . 'class-drama-subscriptions.php';
		require_once $dir . 'class-drama-stripe.php';
		require_once $dir . 'class-drama-stripe-events.php';
		require_once $dir . 'class-drama-paypal.php';
		require_once $dir . 'class-drama-paypal-events.php';
		require_once $dir . 'class-drama-fields.php';
		require_once $dir . 'class-drama-admin.php';
		require_once $dir . 'class-drama-templates.php';
		require_once $dir . 'class-drama-ajax.php';
		require_once $dir . 'class-drama-ad-unlock.php';
		require_once $dir . 'class-drama-coins.php';

		$settings   = new Jws_Drama_Settings();
		$post_types = new Jws_Drama_Post_Types();
		$fields     = new Jws_Drama_Fields();
		$admin      = new Jws_Drama_Admin();
		$templates  = new Jws_Drama_Templates();
		$ajax       = new Jws_Drama_Ajax();
		$coins      = new Jws_Drama_Coins();
		$ad_unlock  = new Jws_Drama_Ad_Unlock();
		$stripe     = new Jws_Drama_Stripe_Events();
		$paypal     = new Jws_Drama_Paypal_Events();

		$ajax->register();
		$coins->register();
		$ad_unlock->register();
		$stripe->register();
		$paypal->register();

		/* Master switch: the settings screen registered further down stays
		   reachable either way, but everything that makes the module show up
		   — post types, front-end templates, archive/widget hooks — is
		   skipped while it is switched off, so turning it off hides drama
		   everywhere on the site without touching stored content. */
		$enabled = Jws_Drama_Settings::is_enabled();

		if ( $enabled ) {

			/* Late so the theme's own template_include filters run first. */
			add_filter( 'template_include', array( $templates, 'locate' ), 99 );
			add_action( 'wp_enqueue_scripts', array( $templates, 'enqueue' ), 20 );

			/* The theme's archive filter runs over admin-ajax, so these have to be
			   registered on every request, not just front-end ones. */
			add_filter( 'jws_archive_filter_post_types', array( $templates, 'allow_in_filter' ) );
			add_filter( 'jws_archive_filter_item_html', array( $templates, 'filter_item_html' ), 10, 4 );
			add_filter( 'streamvid/filter/taxonomy', array( $templates, 'filter_taxonomy' ), 10, 2 );

			add_action( 'init', array( $post_types, 'register' ) );
			add_action( 'init', array( $post_types, 'attach_shared_taxonomies' ), 20 );
			add_action( 'save_post', array( $post_types, 'sync_episode_order' ), 20, 3 );

			/* After WooCommerce's own menu_order pass (priority 10). */
			add_filter( 'custom_menu_order', '__return_true' );
			add_filter( 'menu_order', array( $post_types, 'menu_order' ), 20 );

			add_action( 'acf/init', array( $fields, 'register' ) );

			/* VIP checkout is Paid Memberships Pro's own page; PMPro lets a
			   logged-out visitor straight onto it (guest checkout is its
			   default). This site sells no plan that way, so anyone who isn't
			   signed in is turned back at the door instead. */
			add_action( 'template_redirect', array( 'Jws_Drama_Wallet', 'block_logged_out_checkout' ) );
			add_action( 'wp_footer', array( 'Jws_Drama_Wallet', 'print_login_redirect_script' ) );
		}

		/* The plugin is already active on live sites, so the activation hook
		   would never fire for them. Checking a stored version on admin_init
		   costs one option read once it matches. */
		add_action( 'admin_init', array( 'Jws_Drama_Install', 'maybe_install' ) );
		add_action( 'admin_init', array( 'Jws_Drama_Settings', 'maybe_migrate' ) );

		if ( ! is_admin() ) {
			return;
		}

		/* Late: "Jws Settings" is registered by the theme, whose admin_menu
		   callback runs after every plugin's at the default priority. Always
		   on, disabled or not, so the toggle above stays reachable. */
		add_action( 'admin_menu', array( $settings, 'register_submenu' ), 20 );

		if ( ! $enabled ) {
			return;
		}

		add_action( 'admin_menu', array( $admin, 'register_pages' ) );
		add_action( 'admin_menu', array( $admin, 'remove_taxonomy_metaboxes' ) );

		/* No sub_save/sub_delete endpoints: VIP is Paid Memberships Pro's, so
		   a membership is granted and revoked on its own screens rather than
		   through a card here. Jws_Drama_Subscriptions still holds the rows
		   sold before the switch, which the access check keeps honouring. */

		add_filter( 'manage_' . Jws_Drama_Post_Types::DRAMA . '_posts_columns', array( $admin, 'drama_columns' ) );
		add_action( 'manage_' . Jws_Drama_Post_Types::DRAMA . '_posts_custom_column', array( $admin, 'drama_column' ), 10, 2 );

		add_filter( 'manage_' . Jws_Drama_Post_Types::EPISODE . '_posts_columns', array( $admin, 'episode_columns' ) );
		add_action( 'manage_' . Jws_Drama_Post_Types::EPISODE . '_posts_custom_column', array( $admin, 'episode_column' ), 10, 2 );
		add_filter( 'manage_edit-' . Jws_Drama_Post_Types::EPISODE . '_sortable_columns', array( $admin, 'episode_sortable_columns' ) );

		add_action( 'pre_get_posts', array( $admin, 'episode_default_order' ) );
		add_action( 'restrict_manage_posts', array( $admin, 'episode_filter_dropdown' ) );

		add_filter( 'manage_users_columns', array( $admin, 'user_columns' ) );
		add_filter( 'manage_users_custom_column', array( $admin, 'user_column' ), 10, 3 );
		add_action( 'show_user_profile', array( $admin, 'user_profile_fields' ) );
		add_action( 'edit_user_profile', array( $admin, 'user_profile_fields' ) );
		add_action( 'personal_options_update', array( $admin, 'save_user_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( $admin, 'save_user_profile_fields' ) );
	}
}
