<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://jwsuperthemes.com
 * @since      1.0.0
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/public
 * @author     Jws Theme <jwstheme@gmail.com>
 */
class Jws_Streamvid_Public {

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
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

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
         $is_playlist_tax = is_tax('movies_playlist') || is_tax('videos_playlist') || is_tax('episodes_playlist');
         $is_drama = is_singular( array( 'drama', 'drama_ep' ) );
        

        wp_enqueue_style( $this->plugin_name, JWS_STREAMVID_URL_PUBLIC_ASSETS . '/css/jws-streamvid-public.css', array(), $this->version, 'all' );

        if($is_drama || $is_playlist_tax || is_singular( array( 'movies') ) || is_singular( array( 'episodes') ) || is_singular( array( 'videos') )) {

        if ( class_exists( 'Jws_Streamvid_Player_Engine' ) && Jws_Streamvid_Player_Engine::is_v10() ) {

            /* Video.js 10 skin ships its own CSS with the ES module bundle; this
               sheet only sizes the player and maps the theme colours onto it. */
            wp_enqueue_style( 'jws-player-v10', JWS_STREAMVID_URL_PUBLIC_ASSETS . '/css/jws_player_v10.css', array(), $this->version, 'all' );

        } else {

        wp_enqueue_style( 'jws-player', JWS_STREAMVID_URL_PUBLIC_ASSETS . '/css/jws_player.css', array(), $this->version, 'all' );

        wp_enqueue_style( 'videojs-ima', JWS_STREAMVID_URL_PUBLIC_ASSETS . '/css/videojs.ima.css', array(), $this->version, 'all' );

        wp_enqueue_style( 'videojs-chromcast', 'https://cdn.jsdelivr.net/npm/@silvermine/videojs-chromecast@1.5.0/dist/silvermine-videojs-chromecast.min.css', array(), $this->version, 'all' );

        wp_enqueue_style( 'videojs-airplay', 'https://cdn.jsdelivr.net/npm/videojs-airplay@1.1.1/dist/videojs.airplay.min.css', array(), $this->version, 'all' );

        }

        /* Shared by both engines: the episode panel is built by single_global.js
           and lands in .videos_player either way. Enqueued after jws_player.css
           so the legacy cascade is exactly what it was when these rules still
           lived at the end of that file. */
        wp_enqueue_style( 'jws-ep-panel', JWS_STREAMVID_URL_PUBLIC_ASSETS . '/css/jws_ep_panel.css', array(), $this->version, 'all' );

        }

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

        if(!function_exists('jws_theme_get_option')) { 
            return;
        }
        
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
         
         
        
        $video_chromcast = function_exists('jws_theme_get_option') && jws_theme_get_option('video_chromcast') ? true : false;
        $video_player_ads = function_exists('jws_theme_get_option') && jws_theme_get_option('video_player_ads') ? true : false;
        $video_seek_button = function_exists('jws_theme_get_option') && jws_theme_get_option('video_seek_button') ? true : false;
        $is_playlist_tax = is_tax('movies_playlist') || is_tax('videos_playlist') || is_tax('episodes_playlist');
        wp_register_script( 'jws-youtube-api', '//www.youtube.com/iframe_api', array( 'jquery' ), $this->version, true );


        wp_enqueue_script( $this->plugin_name, JWS_STREAMVID_URL_PUBLIC_ASSETS . '/js/jws-streamvid-public.js', array( 'jquery' ), $this->version, true );
        
        wp_enqueue_script( 'jws-tool', JWS_STREAMVID_URL_PUBLIC_ASSETS . '/js/tool/tool.js', array( 'jquery' ), $this->version, true );
      
        
        if(is_archive()) { 
    
             wp_enqueue_script( 'jws-archive-global', JWS_STREAMVID_URL_PUBLIC_ASSETS . '/js/pages/archive_global.js', array( 'jquery' ), $this->version, true );

             $queried_post_type = get_query_var( 'post_type' );

             if ( is_post_type_archive('tv_shows') || is_tax('tv_shows_cat') || is_tax('tv_shows_tag') ) {
                 $archive_post_type = 'tv_shows';
             } elseif ( is_post_type_archive('videos') || is_tax('videos_cat') || is_tax('videos_tag') ) {
                 $archive_post_type = 'videos';
             } elseif ( is_post_type_archive('person') || is_tax('person_cat') ) {
                 $archive_post_type = 'person';
             } elseif ( is_post_type_archive('drama') || is_tax('drama_tag') || ( is_tax( array( 'genres', 'countries', 'ages' ) ) && 'drama' === $queried_post_type ) ) {
                 $archive_post_type = 'drama';
             } elseif ( is_post_type_archive('movies') || is_tax('movies_cat') || is_tax('movies_tag') || is_tax('genres') ) {
                 $archive_post_type = 'movies';
             } else {
                 $archive_post_type = ( $queried_post_type && is_string( $queried_post_type ) ) ? $queried_post_type : 'movies';
             }
             wp_localize_script( 'jws-archive-global', 'jwsArchiveFilter', [
                 'ajaxurl'   => admin_url('admin-ajax.php'),
                 'post_type' => $archive_post_type,
             ] );
            
        }
        
        wp_register_script( 'jws-single-global', JWS_STREAMVID_URL_PUBLIC_ASSETS . '/js/pages/single_global.js', array( 'jquery' ), $this->version, true );
            
        if($is_playlist_tax || is_singular( array( 'movies') ) || is_singular( array( 'episodes') ) || is_singular( array( 'videos') ) || is_singular( array( 'tv_shows')) || is_singular( array( 'person'))) { 
            
             wp_enqueue_script( 'jws-single-global');
            
            
        }
      
        /* Short drama renders the same Video.js 10 markup from its own template,
           so it needs the same player scripts. */
        $is_drama = is_singular( array( 'drama', 'drama_ep' ) );

        if($is_drama || $is_playlist_tax || is_singular( array( 'movies') ) || is_singular( array( 'episodes') ) || is_singular( array( 'videos') ) || is_singular( array( 'tv_shows')) || is_singular( array( 'person'))) {
            
            
            if ( class_exists( 'Jws_Streamvid_Player_Engine' ) && Jws_Streamvid_Player_Engine::is_v10() ) {

                /* Video.js 10 engine.
                   videojs.min.js is deliberately NOT enqueued — single_global.js
                   and single_video.js both gate their player init on
                   `typeof videojs === 'function'`, so leaving the global undefined
                   is what keeps the two engines from fighting over the same element.
                   The v10 runtime itself is an ES module tree; jws_player_v10.js
                   pulls in only the media adapter each source actually needs. */
                /* videojs-ima is a Video.js 7 plugin with no v10 port, so the v10
                   engine drives the IMA SDK directly — jws_player_v10.js talks to
                   google.ima itself. Only the SDK is needed here. */
                if ( $video_player_ads ) {
                    wp_enqueue_script( 'googleapis-imasdk', '//imasdk.googleapis.com/js/sdkloader/ima3.js', array(), $this->version, true );
                }

                wp_enqueue_script( 'jws-player-v10', JWS_STREAMVID_URL_PUBLIC_ASSETS . '/js/player/jws_player_v10.js', array( 'jquery' ), $this->version, true );

                wp_localize_script( 'jws-player-v10', 'jwsPlayerV10', array(
                    'base'        => Jws_Streamvid_Player_Engine::v10_base_url(),
                    'locale'      => strtolower( str_replace( '_', '-', get_locale() ) ),
                    'ajax_url'    => admin_url( 'admin-ajax.php' ),
                ) );

            } else {

            wp_enqueue_script( 'videojs', JWS_STREAMVID_URL_PUBLIC_ASSETS . '/js/vendor/videojs.min.js', array( 'jquery' ), $this->version, true );

            wp_enqueue_script( 'jws-player', JWS_STREAMVID_URL_PUBLIC_ASSETS . '/js/player/jws_player.js', array( 'videojs' ), $this->version, true );

            wp_enqueue_script( 'videojs-youtube', JWS_STREAMVID_URL_PUBLIC_ASSETS . '/js/vendor/Youtube.min.js', array( 'videojs' ), $this->version, true );

            wp_enqueue_script( 'videojs-vimeo', JWS_STREAMVID_URL_PUBLIC_ASSETS . '/js/vendor/Vimeo.min.js', array( 'videojs' ), $this->version, true );
            
  
            if($video_player_ads) {
               
                wp_enqueue_script( 'videojs-ima', JWS_STREAMVID_URL_PUBLIC_ASSETS . '/js/vendor/videojs.ima.min.js', array( 'videojs' ), $this->version, true );
            
                wp_enqueue_script( 'videojs-contrib-ads', JWS_STREAMVID_URL_PUBLIC_ASSETS . '/js/vendor/videojs-contrib-ads.js', array( 'videojs' ), $this->version, true );
                
                wp_enqueue_script( 'googleapis-imasdk', '//imasdk.googleapis.com/js/sdkloader/ima3.js', array( 'videojs' ), $this->version, true ); 
                    
            }

            /* Chromcast */

            if($video_chromcast) {
                
                wp_enqueue_script( 'videojs-chromecast', JWS_STREAMVID_URL_PUBLIC_ASSETS . '/js/vendor/videojs-chromecast.min.js', array( 'videojs' ), $this->version, true );
                wp_enqueue_script( 'cast_sender', JWS_STREAMVID_URL_PUBLIC_ASSETS . '/js/vendor/videojs.cast_sender.js?loadCastFramework=1', array( 'videojs' ), $this->version, false );
                wp_enqueue_script( 'cast_fenny', JWS_STREAMVID_URL_PUBLIC_ASSETS . '/js/vendor/videojs.cast.min.js', array( 'videojs' ), $this->version, true ); 
                wp_enqueue_script( 'videojs-airplay', 'https://cdn.jsdelivr.net/npm/videojs-airplay@1.1.1/dist/videojs.airplay.min.js', array( 'videojs' ), $this->version, true ); 
                
            }
            
            if($video_seek_button) {
                
            }
 
           
            
            wp_enqueue_script( 'videojs-hotkeys', JWS_STREAMVID_URL_PUBLIC_ASSETS . '/js/vendor/videojs.hotkeys.min.js', array( 'videojs' ), $this->version, true );

            }

        }
        
        if(is_singular( array( 'movies') )) {
          
            wp_enqueue_script( 'jws-single-movies', JWS_STREAMVID_URL_PUBLIC_ASSETS . '/js/pages/single_movies.js', array( 'jquery' ), $this->version, true );
          
        }
        
        wp_register_script( 'jws-single-tv-shows', JWS_STREAMVID_URL_PUBLIC_ASSETS . '/js/pages/single_tv_shows.js', array( 'jquery' ), $this->version, true );
          
        if(is_singular( array( 'tv_shows','episodes') )) {
          wp_enqueue_script( 'jws-single-tv-shows');
        }
        
        
        if(is_singular( array( 'person') )) {
          
            wp_enqueue_script( 'jws-single-person', JWS_STREAMVID_URL_PUBLIC_ASSETS . '/js/pages/single_person.js', array( 'jquery' ), $this->version, true );
          
        }
        
        if(is_author()) {
          
            wp_enqueue_script( 'jws-profile', JWS_STREAMVID_URL_PUBLIC_ASSETS . '/js/pages/profile.js', array( 'jquery' ), $this->version, true );
           
          
        }
        
        if( is_user_logged_in() ){
            
            wp_enqueue_script( 'jws-upload-videos', JWS_STREAMVID_URL_PUBLIC_ASSETS . '/js/pages/upload_videos.js', array( 'jquery' ), $this->version, true );
       
        } 
        $max_size = jws_get_max_upload_image_size();
       
        $fr_varjs = array( 
         
            'max_file_size'	=>	sprintf(
                '%s %smb.',
                esc_html( 'Support *.png, *.jpeg, *.gif, *.jpg. Maximun upload file size:' , 'jws_streamvid' ),
                $max_size
            ),
            'next_episodes' => esc_html__( 'Moving on to the next episode' , 'jws_streamvid' ),
            'security_text' => jws_theme_get_option('block_devtool_text','You are currently using DevTools. Please disable it to continue watching the video.' ),
            'block_devtool' => jws_theme_get_option('block_devtool') ? 'yes' : 'no',
            'history_text' => esc_html__( 'You have watched up to' , 'jws_streamvid' ),
            'watch_again' => esc_html__( 'Watch again' , 'jws_streamvid' ),
            'continue_watching' => esc_html__( 'Continue watching' , 'jws_streamvid' ),
            /* Shown beside the countdown on the v10 ad control bar. */
            'ad_label' => esc_html__( 'Ad' , 'jws_streamvid' )

        );      
    
        if(is_singular( array( 'episodes') ) ) {
            $fr_varjs['is_episodes'] = true;
            $fr_varjs['episodes_tv_shows'] = jws_episodes_check_type(get_the_ID());
            $fr_varjs['episodes_list'] = array();
            $tv_shows = jws_episodes_check_type(get_the_ID());
            $tv_shows_seasons = get_field('tv_shows_seasons',$tv_shows);
            $seasion = jws_episodes_check_season( array('id_tv' => $tv_shows) );
            $auto_episodes = jws_theme_get_option('next_episodes') ? true : false;
            $fr_varjs['show_ep_list_btn'] = jws_theme_get_option('show_ep_list_btn') ? true : false;
            $seasion = $seasion - 1;

            // Build seasons_data for in-player season select
            $fr_varjs['episodes_current_season'] = $seasion;
            $fr_varjs['seasons_data'] = array();
            if (!empty($tv_shows_seasons)) {
                foreach ($tv_shows_seasons as $s_index => $season_row) {
                    if (empty($season_row['episodes'])) continue;
                    $season_label = !empty($season_row['season_name'])
                        ? $season_row['season_name']
                        : sprintf(__('Season %d', 'jws_streamvid'), $s_index + 1);
                    $season_eps = array();
                    foreach ($season_row['episodes'] as $ep_id) {
                        $ep_link  = get_the_permalink($ep_id);
                        $ep_thumb = function_exists('jws_poster_banner_url') ? jws_poster_banner_url($ep_id, 'thumbnail') : '';
                        $season_eps[] = array(
                            'id'    => $ep_id,
                            'link'  => $ep_link,
                            'title' => get_the_title($ep_id),
                            'thumb' => $ep_thumb,
                        );
                    }
                    $fr_varjs['seasons_data'][] = array(
                        'label'    => $season_label,
                        'episodes' => $season_eps,
                    );
                }
            }
            if(isset($_GET['playlist'])) {
                
                $term = get_term( $_GET['playlist'] , 'episodes_playlist' );
                $post_ids = get_term_meta($_GET['playlist'], 'playlist_order', true);
                $args =  array(
                    'post_type'         =>  'episodes',
                    'post_status'       =>  array( 'publish' ),
                    'posts_per_page'    =>  -1,
                    'post__in'  => $post_ids, 
                    'orderby'   => 'post__in', 
                    'order'             =>  'ASC',
                     'fields'          => 'ids', // Only get post IDs
                    'tax_query'         =>  array(
                        array(
                            'taxonomy'  =>  $term->taxonomy,
                            'field'     =>  'term_id',
                            'terms'     =>  $_GET['playlist']
                        )
                    )
                ) ; 
                
                $episodes = get_posts( $args );
                
                foreach($episodes as $episodes_value) { 
                    
                     $link = get_the_permalink($episodes_value);
                     
                     if(!empty($_GET['playlist'])) { $link = add_query_arg( 'playlist', $_GET['playlist'] , $link ); } 
                     
                     $ep_thumb = function_exists('jws_poster_banner_url') ? jws_poster_banner_url($episodes_value, 'thumbnail') : '';
                     $fr_varjs['episodes_list'][] = array(
                            
                        'id'    => $episodes_value,
                        'link'  => $link,
                        'title' => get_the_title($episodes_value),
                        'thumb' => $ep_thumb,
                      
                      );
                    
                }
                
            }  else {
                
                   if(isset($tv_shows_seasons[$seasion]['episodes']) && !empty($tv_shows_seasons[$seasion]['episodes']) && $auto_episodes) : 
            
                       $episodes = $tv_shows_seasons[$seasion]['episodes'];
                 
                       foreach($episodes as $episodes_value) { 
                          $link = get_the_permalink($episodes_value);
                          $ep_thumb = function_exists('jws_poster_banner_url') ? jws_poster_banner_url($episodes_value, 'thumbnail') : '';
                          $fr_varjs['episodes_list'][] = array(
                            
                            'id'    => $episodes_value,
                            'link'  => $link,
                            'title' => get_the_title($episodes_value),
                            'thumb' => $ep_thumb,
                          
                          );
                       }
                    
                    
                    endif;

            }


        } elseif ( is_singular( array( 'drama', 'drama_ep' ) ) && class_exists( 'Jws_Drama_Wallet' ) ) {

            /*
             * Same idea as the tv_shows branch above, cut down to what
             * saveVideoProgress() in jws_player_v10.js actually needs: the
             * parent id, so watching an episode also credits the series for
             * "Continue Watching" the way a tv_shows episode already does.
             *
             * Both post types, because the episode plays on either URL:
             * single-drama.php opens the drama on whichever episode the viewer
             * is up to, so /drama/the-ceo/ is where most watching happens. When
             * only drama_ep was covered, that page wrote a history row for the
             * episode and none for the series, and the drama never reached
             * "Continue Watching" at all.
             */
            $fr_varjs['is_drama_episode'] = true;
            $fr_varjs['episodes_drama']   = is_singular( 'drama' )
                ? get_the_ID()
                : Jws_Drama_Wallet::drama_id_of( get_the_ID() );
        }

        $fr_varjs['video_continue_watching'] = jws_theme_get_option('video_continue_watching') ? 'yes' : 'no';
        $post_id = get_the_ID();
        $rent_enabled = get_post_meta($post_id, 'rent_enable', true ); 

        if($rent_enabled) {
            $user_videos = get_user_meta(get_current_user_id(), 'jws_rented_videos', true);
            $rent_expire = isset($user_videos[$post_id]['expire']) ? $user_videos[$post_id]['expire'] : '';
            $fr_varjs['is_rented_video'] = true;
            $fr_varjs['rent_expire'] = $rent_expire;
        }



		$fr_varjs = apply_filters( 'streamvid_frontend_localize', $fr_varjs );

		wp_localize_script( $this->plugin_name, 'streamvid_script', $fr_varjs );
        
        
        
        
        
	}
    
    public function movies_player($args) { 
        jws_streamvid_load_template("movies/player.php", false , $args);
    }
    public function likes() { 
        
        jws_streamvid_load_template("tool/like/likes.php", false);

    } 
    
    public function watchlist() { 
        
        jws_streamvid_load_template("tool/watchlist/watchlist.php", false);

    }
    
    public function download() { 
        
        jws_streamvid_load_template("tool/download/download.php", false);

    }
    
    /**
     * The share popup's contents, fetched by tool.js into the empty shell that
     * wp_footer prints. Public data about one published post, so no nonce and no
     * capability check — just a hard check that the id is a post anyone can read.
     */
    public function share_content() {

        $share_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

        if (!$share_id || 'publish' !== get_post_status($share_id)) {
            wp_send_json_error(['message' => esc_html__('Nothing to share.', 'jws_streamvid')], 404);
        }

        ob_start();
        jws_streamvid_load_template("tool/share/share-content.php", false, ['share_id' => $share_id]);

        wp_send_json_success(['html' => ob_get_clean()]);

    }
    
    public function history_delete() {  
        
      
        $errors = new WP_Error();   
      
        $history_delete = isset($_POST['post_id']) ?  $_POST['post_id'] : '';

        $user_id = get_current_user_id();

        if(!empty($history_delete)) {

            if(!is_array($history_delete)) {
                $history_delete = array($history_delete);
            }

            Jws_History::delete_items($user_id, array_map('absint', $history_delete));

           $message = esc_html__('Removed from history.','jws_streamvid');
            
        } else {
             $errors->add(
                'video_empty',
                esc_html__( 'No video selected yet.', 'jws_streamvid' )
            );
        }
        $result = [
            'message' => $message
        ];
   
        if( $errors->get_error_code() ){
             wp_send_json_error( $errors );
        } 
        
            
        wp_send_json_success($result);
  
    }
    
       public function history() { 
            
   
        $args = wp_parse_args( $_POST, array(
            'progress' => array(),
            'tv_shows' => '',
            'drama'    => ''
        ) );

        extract( $args );
        
        $errors = new WP_Error();   
       
        
        if( $errors->get_error_code() ){
             wp_send_json_error( $errors );
        } 
        
        if(is_user_logged_in()) {

            $user_id = get_current_user_id();

            if(!empty($progress)) {

                $id = absint($progress['id']);

                if(empty($progress['time'])) {
                   return;
                }

                Jws_History::set_item($user_id, $id, $progress['time'], $progress['endtime']);

                if(!empty($tv_shows)) {
                    $id_tv_show = absint($tv_shows);
                    Jws_History::set_item($user_id, $id_tv_show, $progress['time'], $progress['endtime'], $id);
                }

                if(!empty($drama)) {
                    $id_drama = absint($drama);
                    Jws_History::set_item($user_id, $id_drama, $progress['time'], $progress['endtime'], $id);
                }

            }

        }
       
       
        
    }
    
    
    public function search_page($search_template) { 
        
        if( is_search() && !isset($_GET['post_type'])){
			$search_template = plugin_dir_path( __FILE__ ) . 'page/search.php';
		}
        return $search_template;

    }

    
     public function global_modal() {
        global $wp_query; 
         /*
          * Not just is_single() any more: a share button now sits on every card
          * too, and they all open this one shell. It is a handful of empty tags
          * and stays empty until something is shared.
          */
         if(jws_streamvid_options('videos_share')){
		  jws_streamvid_load_template("tool/share/share.php", false);
		}
        
        
        if( is_user_logged_in() ){
            jws_streamvid_load_template("playlist/create-playlist.php", false);
            if(isset($_GET['playlist']) && !empty($_GET['playlist'])) { 
              jws_streamvid_load_template("playlist/edit-playlist.php", false);  
              jws_streamvid_load_template("playlist/delete-playlist.php" , false , array('term_id'=>$_GET['playlist']));
              jws_streamvid_load_template("playlist/other-playlist.php" , false , array('term_id'=>$_GET['playlist']));
              jws_streamvid_load_template("playlist/search-item.php", false ,  array('term_id'=>$_GET['playlist']));
            }
          
            do_action( 'streamvid/videos/form', array('type'=>'create') );
            do_action( 'streamvid/videos/form', array('type'=>'edit') );
            
            if(isset($wp_query->query_vars['dashboard'])) {
                jws_streamvid()->get()->dashboard->form_profile();
                jws_streamvid()->get()->dashboard->form_personal();     
            }
            
            jws_streamvid()->get()->live_videos->form_upload(); 
          
           
        }  
    }

    /**
     * Inject devtools-detect script into the footer.
     *
     * @since    1.0.0
     */
    public function inject_devtools_script() {

        if(jws_theme_get_option('block_devtool') && is_singular(array( 'movies','episodes','videos' ))) {
          

      ?><script>
		function wccp_pro_log_to_console_if_allowed(data = "")
        {
            var myName = "";
            
            if(wccp_pro_log_to_console_if_allowed.caller != null) myName = wccp_pro_log_to_console_if_allowed.caller.toString();
            
            myName = myName.substr('function '.length);
            
            myName = myName.substr(0, myName.indexOf('('));
            
            }
        </script> <?php


        $message = jws_theme_get_option( 'block_devtool_text', __( 'DevTools is not allowed on this site. Please close it to continue.', 'jws_streamvid' ) );
        ?>
        <script type="module">
        import devtools from '<?php echo esc_url( plugins_url( 'node_modules/devtools-detect/index.js', dirname( __FILE__ ) ) ); ?>';
            
     
        const devtoolsMessage = <?php echo wp_json_encode( $message ); ?>;
        const player = document.querySelector('.jws-player-global');
        

        function blockPage() {
            if (!player) {
                return;
            }
           wccp_pro_log_to_console_if_allowed("clear_body_at_all");
           player.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100vh;background:#0d0d0d;margin:0;font-family:sans-serif;"><div style="text-align:center;padding:40px 60px;background:#1a1a1a;border:1px solid #333;border-radius:12px;max-width:500px;"><svg xmlns=\'http://www.w3.org/2000/svg\' width=\'64\' height=\'64\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'#e74c3c\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'><circle cx=\'12\' cy=\'12\' r=\'10\'></circle><line x1=\'12\' y1=\'8\' x2=\'12\' y2=\'12\'></line><line x1=\'12\' y1=\'16\' x2=\'12.01\' y2=\'16\'></line></svg><h2 style=\'color:#e74c3c;margin:20px 0 12px;font-size:22px;\'>Access Restricted</h2><p style=\'color:#aaa;font-size:15px;line-height:1.6;margin:0;\'>' + devtoolsMessage + '</p></div></div>';
        }

        function restorePage() {
            if (!player) {
                return;
            }
            location.reload();
        }

        // Block immediately if devtools already open on page load
        if (devtools.isOpen) {
            blockPage();
        }

        // Listen for open/close events
        window.addEventListener('devtoolschange', event => {
          
            if (event.detail.isOpen) {
                blockPage();
            } else {
                restorePage();
            }
        });
        </script>
        <?php
    }

    }
}
