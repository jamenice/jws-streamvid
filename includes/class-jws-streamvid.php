<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://jwsuperthemes.com
 * @since      1.0.0
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0 
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 * @author     Jws Theme <jwstheme@gmail.com>
 */
class Jws_Streamvid {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Jws_Streamvid_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;
    protected $plugin_action;    
	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'JWS_STREAMVID_VERSION' ) ) {
			$this->version = JWS_STREAMVID_VERSION;
		} else {
			$this->version = '1.0.0';
		}
        
        
        $this->plugin_action = new stdClass();
		$this->plugin_name = 'jws-streamvid';
        
		$this->load_dependencies();
		$this->set_locale();
        $this->define_admin_hooks();
	
        $this->define_post_hooks();
        $this->define_encode_hooks();
        $this->define_media_hooks();
        $this->define_user_hooks();
        $this->define_playlist_hooks();
        $this->define_videos_hooks();
        $this->define_dashboard_hooks();
        $this->define_live_videos_hooks();
        $this->define_advertising_hooks();
		$this->define_public_hooks();
	
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Jws_Streamvid_Loader. Orchestrates the hooks of the plugin.
	 * - Jws_Streamvid_i18n. Defines internationalization functionality.
	 * - Jws_Streamvid_Admin. Defines all hooks for the admin area.
	 * - Jws_Streamvid_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jws-streamvid-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jws-streamvid-i18n.php';
        
         /**
		 * The class responsible for create custom post type.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jws-streamvid-post.php';
         /**
		 * The class responsible for create custom post type.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jws-streamvid-media.php';
        
		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-jws-streamvid-admin.php';
       
		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-jws-streamvid-public.php';
        
        /**
		 * The class responsible for function global.
		 */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/function_template.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/function_ajax.php';
        /**
		 * change default wp.
		 */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/function_change_default_wp.php';
        
        /**
		 * The class responsible for encode media.
		 */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jws-streamvid-encode.php';
        
        /**
		 * The class responsible for encode profile.
		 */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jws-streamvid-profile.php';
        
        
        /**
		 * The class responsible for advertising.
		*/
        include_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jws-streamvid-advertising.php';
        
        
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jws-streamvid-playlist.php';
        
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jws-streamvid-videos.php';
        
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jws-streamvid-dashboard.php';
        
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jws-streamvid-livevideo.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jws-streamvid-youtube-import.php';

        /**
         * Custom tables + data access for favorites, watchlist and history
         * (replaces the old `{post_type}_liked` / `post_watchlist` /
         * `video_progress_data` usermeta arrays).
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jws-streamvid-tables.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jws-favorites.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jws-watchlist.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jws-history.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jws-ppv-access.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jws-streamvid-migration.php';

        /**
         * The unified payment system: one checkout for membership, buy/rent
         * and coins, taking payment with its own Stripe and PayPal
         * credentials. Everything front-facing is gated on the master switch
         * in Jws_Payment_Settings, so with it off the site keeps using the
         * PMPro checkout and the WooCommerce cart exactly as before.
         */
        $payment_dir = plugin_dir_path( dirname( __FILE__ ) ) . 'includes/payment/';

        require_once $payment_dir . 'class-jws-payment-settings.php';
        require_once $payment_dir . 'class-jws-payment-ledger.php';
        require_once $payment_dir . 'class-jws-payment-items.php';
        require_once $payment_dir . 'class-jws-payment-orders.php';
        require_once $payment_dir . 'class-jws-payment-subscriptions.php';
        require_once $payment_dir . 'class-jws-payment-fulfillment.php';
        require_once $payment_dir . 'class-jws-payment-stripe.php';
        require_once $payment_dir . 'class-jws-payment-paypal.php';
        require_once $payment_dir . 'class-jws-payment-events.php';
        require_once $payment_dir . 'class-jws-payment-checkout.php';
        require_once $payment_dir . 'class-jws-payment-invoice.php';
        require_once $payment_dir . 'class-jws-payment-router.php';
        require_once $payment_dir . 'class-jws-payment-sync.php';

        $payment_checkout = new Jws_Payment_Checkout();
        $payment_checkout->register();

        $payment_events = new Jws_Payment_Events();
        $payment_events->register();

        $payment_router = new Jws_Payment_Router();
        $payment_router->register();

        $payment_invoice = new Jws_Payment_Invoice();
        $payment_invoice->register();

        include_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jws-streamvid-install.php';
		$this->loader = new Jws_Streamvid_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Jws_Streamvid_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Jws_Streamvid_i18n();

		$this->loader->add_action( 'init', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Jws_Streamvid_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

        // YouTube Import feature
        new Jws_Streamvid_Youtube_Import();

        // Unified payment system settings
        new Jws_Payment_Settings();

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {


		$this->plugin_action->plugin_public = new Jws_Streamvid_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $this->plugin_action->plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $this->plugin_action->plugin_public, 'enqueue_scripts');
		$this->loader->add_action( 'wp_head', $this->plugin_action->plugin_public, 'inject_devtools_script' );

        $this->loader->add_action( 
			'streamvid/movies/player', 
			$this->plugin_action->plugin_public, 
			'movies_player'
		);	

        $this->loader->add_action( 
			'wp_ajax_like_post', 
			$this->plugin_action->plugin_public, 
			'likes'
		);
        
        $this->loader->add_action( 
			'wp_ajax_watchlist_post', 
			$this->plugin_action->plugin_public, 
			'watchlist'
		);
        
        $this->loader->add_action( 
			'wp_ajax_history_post', 
			$this->plugin_action->plugin_public, 
			'history'
		);
        
        $this->loader->add_action( 
			'wp_ajax_history_delete', 
			$this->plugin_action->plugin_public, 
			'history_delete'
		);
        

        
        $this->loader->add_action( 
			'wp_ajax_download_post', 
			$this->plugin_action->plugin_public, 
			'download'
		);
        
        $this->loader->add_action( 
			'wp_ajax_nopriv_download_post', 
			$this->plugin_action->plugin_public, 
			'download'
		);
        
        $this->loader->add_action( 
			'wp_ajax_share_content', 
			$this->plugin_action->plugin_public, 
			'share_content'
		);
        
        $this->loader->add_action( 
			'wp_ajax_nopriv_share_content', 
			$this->plugin_action->plugin_public, 
			'share_content'
		);
        
  
        $this->loader->add_action( 
			'wp_footer', 
			$this->plugin_action->plugin_public, 
			'global_modal'
		);
        
        
        $this->loader->add_filter( 
			'search_template', 
			$this->plugin_action->plugin_public, 
			'search_page'
		);
  
        
        
	}
    
    
    /**
	 * Register all of the hooks related to the post type functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_post_hooks() {

		$this->plugin_action->plugin_post = new Jws_Streamvid_Post();
        $this->loader->add_action( 
			'init', 
			$this->plugin_action->plugin_post, 
			'create_custom_posttype'
		);	

		$this->loader->add_filter(
			'term_link',
			$this->plugin_action->plugin_post,
			'custom_taxonomy_link',
			10,
			3
		);

        $this->loader->add_filter(
			'manage_videos_posts_columns',
			$this->plugin_action->plugin_post,  
			'add_filter_column_videos'
		);
        
        $this->loader->add_filter(
			'manage_videos_posts_custom_column',
			$this->plugin_action->plugin_post,  
			'show_filter_column_videos' , 10, 2
		);
        
        $this->loader->add_filter(
			'manage_movies_posts_columns',
			$this->plugin_action->plugin_post,  
			'add_filter_column_movies'
		);
        
        $this->loader->add_filter(
			'manage_movies_posts_custom_column',
			$this->plugin_action->plugin_post,  
			'show_filter_column_movies' , 10, 2
		);
        
        $this->loader->add_filter(
			'manage_tv_shows_posts_columns',
			$this->plugin_action->plugin_post,  
			'add_filter_column_tv_shows'
		);
        
        $this->loader->add_filter(
			'manage_tv_shows_posts_custom_column',
			$this->plugin_action->plugin_post,  
			'show_filter_column_tv_shows' , 10, 2
		);
        
        $this->loader->add_filter(
			'manage_episodes_posts_columns',
			$this->plugin_action->plugin_post,  
			'add_filter_column_episodes'
		);
        
        $this->loader->add_filter(
			'manage_episodes_posts_custom_column',
			$this->plugin_action->plugin_post,  
			'show_filter_column_episodes' , 10, 2
		);
        
        $this->loader->add_filter(
			'manage_person_posts_columns',
			$this->plugin_action->plugin_post,  
			'add_filter_column_person'
		);
        
        $this->loader->add_filter(
			'manage_person_posts_custom_column',
			$this->plugin_action->plugin_post,  
			'show_filter_column_person' , 10, 2
		);
	}
    
    /**
	 * Register all of the hooks related to the media functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_media_hooks() {

		$this->plugin_action->plugin_media = new Jws_Streamvid_Media();
       	
        $this->loader->add_filter( 
			'manage_media_columns', 
			$this->plugin_action->plugin_media, 
			'add_encode_column_media'
		);
        
        $this->loader->add_action( 
			'manage_media_custom_column', 
			$this->plugin_action->plugin_media, 
			'add_encode_column_media_content'
		);
 
        $this->loader->add_action( 
			'wp_ajax_encode_media_video', 
			$this->plugin_action->plugin_media, 
			'media_encode_ajax'
		);
        
        $this->loader->add_action( 
			'wp_ajax_bunny_media_video', 
			$this->plugin_action->plugin_media, 
			'media_bunny_ajax'
		);
        
        $this->loader->add_action( 
			'wp_ajax_bunny_media_video_delete', 
			$this->plugin_action->plugin_media, 
			'media_bunny_delete_ajax'
		);
        
        
        $this->loader->add_action( 
			'wp_ajax_cloudflare_upload_media_video', 
			$this->plugin_action->plugin_media, 
			'upload_video_cloudflare_stream'
		);
        
        $this->loader->add_action( 
			'wp_ajax_cloudflare_delete_media_video', 
			$this->plugin_action->plugin_media, 
			'delete_video_cloudflare_stream'
		);
        
        
        $this->loader->add_action(
			'delete_attachment',
			$this->plugin_action->plugin_media,
			'delete_attachment'	,
			10,
			2
		);
        
        $this->loader->add_action( 
			'streamvid_post_videos_media', 
			$this->plugin_action->plugin_media, 
			'post_videos_media'
		);
        


	}
    
     /**
	 * Register all of the hooks related to the user functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_user_hooks() { 
	   
       $this->plugin_action->profile = new Jws_Streamvid_Profile();
	   
	   $this->loader->add_action( 
			'template_redirect', 
			$this->plugin_action->profile, 
			'the_main',
			20
		);
        
        $this->loader->add_action( 
			'streamvid/profile/header', 
			$this->plugin_action->profile,  
			'the_header'
		);
        
        $this->loader->add_action( 
			'streamvid/profile/header/menu', 
			$this->plugin_action->profile,  
			'the_menu'
		);
        
        
        $this->loader->add_action( 
			'streamvid/profile/main', 
			$this->plugin_action->profile,  
			'the_content'
		);
        
        $this->loader->add_action( 
			'init', 
			$this->plugin_action->profile,  
			'add_endpoints', 
			100
		);
        
        $this->loader->add_action( 
			'wp', 
			$this->plugin_action->profile,  
			'redirect_url_default'
		);
        
        
     
    }
    
    
    
    /**
	 * Register all of the hooks playlist to the videos functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
    private function define_videos_hooks() { 
	   
       $this->plugin_action->videos = new Jws_Streamvid_Videos();
       $this->loader->add_action( 
			'streamvid/videos/form', 
			$this->plugin_action->videos, 
			'form_upload'
	   );
       
       $this->loader->add_action( 
			'streamvid/videos/filter', 
			$this->plugin_action->videos, 
			'filter_videos'
	   );
   
       $this->loader->add_action( 
			'wp_ajax_upload_video', 
			$this->plugin_action->videos, 
			'upload_video'
	   );
       
       $this->loader->add_action( 
			'wp_ajax_video_editor', 
			$this->plugin_action->videos, 
			'video_editor'
	   );
       
       $this->loader->add_action( 
			'wp_ajax_delete_videos', 
			$this->plugin_action->videos, 
			'delete_videos'
	   );
       
       $this->loader->add_action( 
			'wp_ajax_add_media_videos', 
			$this->plugin_action->videos, 
			'add_media_videos'
	   );
       
  
       
    }   
       
    /**
	 * Register all of the hooks playlist to the playlist functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
    private function define_playlist_hooks() { 
	   
       $this->plugin_action->playlist = new Jws_Streamvid_Playlist();
       
       $this->loader->add_action( 
			'streamvid/playlist/form', 
			$this->plugin_action->playlist, 
			'form_create_edit'
	   );

       $this->loader->add_action( 
			'wp_ajax_create_playlist', 
			$this->plugin_action->playlist, 
			'create_edit_playlist'
	   );
       
       $this->loader->add_action( 
			'wp_ajax_delete_playlist', 
			$this->plugin_action->playlist, 
			'delete_playlist'
	   );
       
       $this->loader->add_action( 
			'wp_ajax_search_item_playlist', 
			$this->plugin_action->playlist, 
			'search_item_playlist'
	   );
       
       $this->loader->add_action( 
			'wp_ajax_set_item_playlist', 
			$this->plugin_action->playlist, 
			'set_item_playlist'
	   );
       
       $this->loader->add_action( 
			'wp_ajax_set_image_playlist_from_post', 
			$this->plugin_action->playlist, 
			'set_image_playlist_from_post'
	   );
       
       
       $this->loader->add_action( 
			'wp_ajax_get_save_to_playlist', 
			$this->plugin_action->playlist, 
			'get_save_to_playlist'
	   );

	   $this->loader->add_action( 
			'wp_ajax_get_save_to_playlist_single', 
			$this->plugin_action->playlist, 
			'get_save_to_playlist_single'
	   );
  
  
	}
    
    
    /**
	 * Register all of the hooks playlist to the playlist functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	*/
    private function define_dashboard_hooks() { 
	   
       $this->plugin_action->dashboard = new Jws_Streamvid_Dashboard();
       $this->loader->add_action( 
			'wp_ajax_edit_profile', 
			$this->plugin_action->dashboard, 
			'save_profile'
	   );
       $this->loader->add_action( 
			'wp_ajax_edit_personal', 
			$this->plugin_action->dashboard, 
			'save_personal'
	   );
    }
    
    
    /**
	 * Register all of the hooks playlist to the playlist functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	*/
    private function define_live_videos_hooks() { 
	  
       $this->plugin_action->live_videos = new Jws_Streamvid_Live_Videos();
       
       $this->loader->add_action( 
			'wp_ajax_upload_video_live', 
			$this->plugin_action->live_videos, 
			'start_stream'
	   );
       $this->loader->add_action( 
			'wp_ajax_check_live_stream_status', 
			$this->plugin_action->live_videos, 
			'ajax_check_live_status'
	   );
       $this->loader->add_action( 
			'wp_ajax_delete_live_data', 
			$this->plugin_action->live_videos, 
			'delete_live_data'
	   );
       $this->loader->add_action( 
			'wp_ajax_start_live_data_admin', 
			$this->plugin_action->live_videos, 
			'start_live_in_admin'
	   );

    }
    
    
    /**
	 * Register all of the hooks related to the encode functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_encode_hooks() {
		$this->plugin_action->plugin_encode = new Jws_Streamvid_Encode();
	}
    
    /**
	 * Register all of the hooks related to the encode functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_advertising_hooks() {
		$this->plugin_action->plugin_advertising = new Jws_Streamvid_Advertising();
        $this->loader->add_action( 
			'template_redirect', 
			$this->plugin_action->plugin_advertising, 
			'template_redirect'
	   );
       
       $this->loader->add_filter( 
			'streamvid/player/setup', 
			$this->plugin_action->plugin_advertising, 
			'query_vmap',
            10,
			2
	   );


	}
    

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}
    
    /**
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get() {
		return $this->plugin_action;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Jws_Streamvid_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
