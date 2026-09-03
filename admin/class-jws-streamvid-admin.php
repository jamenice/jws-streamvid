<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://jwsuperthemes.com
 * @since      1.0.0
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/admin
 * @author     Jws Theme <jwstheme@gmail.com>
 */
class Jws_Streamvid_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
        global $pagenow, $post_type;
		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Jws_Streamvid_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Jws_Streamvid_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/jws-streamvid-admin.css', array(), $this->version, 'all' );
        
        if($pagenow === 'post.php' &&  ($post_type === 'videos' || $post_type === 'movies' || $post_type === 'episodes')) { 
            
            wp_enqueue_style( 'videojs', JWS_STREAMVID_URL_PUBLIC_ASSETS . '/css/videojs.css', array(), $this->version, 'all' );
            
        }
        
        

	}  

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
        global $pagenow, $post_type;
		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Jws_Streamvid_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Jws_Streamvid_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/jws-streamvid-admin.js', array( 'jquery' ), $this->version, false );
        
        wp_enqueue_script( 'jws_encode', plugin_dir_url( __FILE__ ) . 'js/encode.js', array( $this->plugin_name ), $this->version, false );

         if($pagenow === 'post.php' &&  ($post_type === 'videos' || $post_type === 'movies' || $post_type === 'episodes')) {
            
            wp_enqueue_script( 'videojs', JWS_STREAMVID_URL_PUBLIC_ASSETS . '/js/vendor/videojs.min.js', array( 'jquery' ), $this->version, false );
            
            wp_register_script( 'videojs-youtube', JWS_STREAMVID_URL_PUBLIC_ASSETS . '/js/vendor/Youtube.min.js', array( 'videojs' ), $this->version, false );
            
            wp_enqueue_script( 'videojs-http-streaming', JWS_STREAMVID_URL_PUBLIC_ASSETS. '/js/vendor/videojs-http-streaming.js', array( 'videojs' ), $this->version, false );
            
            wp_enqueue_script( 'videojs-contrib-quality-levels', JWS_STREAMVID_URL_PUBLIC_ASSETS . '/js/vendor/videojs-contrib-quality-levels.min.js', array( 'videojs' ), $this->version, false );
            
            wp_enqueue_script( 'videojs-hls-quality-selector', JWS_STREAMVID_URL_PUBLIC_ASSETS . '/js/vendor/videojs-hls-quality-selector.min.js', array( 'videojs' ), $this->version, false );
            
           
        }

	}

}
