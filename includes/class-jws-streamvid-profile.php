<?php

/**
 * Fired during plugin activation
 *
 * @link       https://jwsuperthemes.com
 * @since      1.0.0
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code for profile
 *
 * @since      1.0.0
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 * @author     Jws Theme <jwstheme@gmail.com>
 */
class Jws_Streamvid_Profile {
    
     public function _profile_is_owner(){

      
        $user_id = get_current_user_id();
        $current_id = get_queried_object_id();
        
        if(is_user_logged_in() && get_current_user_id() == get_queried_object_id()) {  
            
            return true;
            
        } 
        
   
        return false;
    } 
    
    public function redirect_url_default(){  
    
        $is_logged_in = is_user_logged_in();
        
        if( !$is_logged_in ){
            return '';
        }

        // Set account page
        $account_page = get_option( 'pmpro_account_page_id' );

        if( $account_page && is_page( $account_page ) ){
            wp_redirect( $this->get_endpoint( get_current_user_id(), 'membership' , '' )   );
        } 

        // Set billing page
        $billind_page = get_option( 'pmpro_billing_page_id' );

        if( $billind_page && is_page( $billind_page ) ){
            wp_redirect( $this->get_endpoint( get_current_user_id(), 'membership' , 'billing' ));
        }

        // Set invoices page
        $invoice_page = get_option( 'pmpro_invoice_page_id' ); 

        if( $invoice_page && is_page( $invoice_page ) ){
           wp_redirect( $this->get_endpoint( get_current_user_id(), 'membership' , 'invoices' ));
        } 
        
    }
    
    public function get_menu_menu_header_items(){  
 
       $items = array();
       
       $items[ 'favorites' ] 	= array(
			'title'			=>	esc_html__( 'Favorites', 'jws_streamvid' ),
			'icon'			=>	'jws-icon-heart',
			'callback'		=>	function(){
				jws_streamvid_load_template( 'user/profile/favorites.php' );
			},
			'priority'		=>	0
	   );
       
       $items[ 'history' ] 	= array(
			'title'			=>	esc_html__( 'History', 'jws_streamvid' ),
			'icon'			=>	'jws-icon-eye',
			'callback'		=>	function(){
				jws_streamvid_load_template( 'user/profile/history.php' );
			},
			'priority'		=>	5
	   );
      
       if(function_exists( 'pmpro_page_meta' ) ){ 
           $items[ 'subscriptions' ] 	= array(
    			'title'			=>	esc_html__( 'Subscription', 'jws_streamvid' ), 
    			'icon'			=>	'jws-icon-crown-simple',
    			'callback'		=>	function(){
    				jws_streamvid_load_template( 'user/profile/subscriptions.php' );
    			},
                'parent'    =>  'membership', 
    			'priority'		=>	10
    	   );
       }
       if(jws_theme_get_option('video_upload')) {
            $items[ 'upload' ] 	= array(
    			'title'			=>	esc_html__( 'Upload', 'jws_streamvid' ),
    			'icon'			=>	'jws-icon-upload',
    			'callback'		=>	'',
                'popup'         =>  '#upload-videos',
    			'priority'		=>	20
    	   );

            // Upload Video Mode "Page" (Theme Options > Videos Upload): link to the page instead.
            $upload_page_url = jws_streamvid()->get()->videos->get_upload_page_url();

            if($upload_page_url) {
                $items[ 'upload' ]['popup'] = '';
                $items[ 'upload' ]['url']   = $upload_page_url;
            }
       }
       if(jws_theme_get_option('video_live')) {
            $items[ 'live' ] 	= array(
    			'title'			=>	esc_html__( 'Go live', 'jws_streamvid' ),
    			'icon'			=>	'jws-icon-video-camera',
    			'callback'		=>	'',
                'popup'         =>  '#upload-videos-live',
    			'priority'		=>	30
    	   );

            // Go Live Mode "Page" (Theme Options > Videos Upload): link to the page instead.
            $live_page_url = jws_streamvid()->get()->live_videos->get_create_page_url();

            if($live_page_url) {
                $items[ 'live' ]['popup'] = '';
                $items[ 'live' ]['url']   = $live_page_url;
            }
       }

       // Only link the wallet when its profile tab is registered, otherwise the endpoint does not exist.
       $profile_items = $this->get_menu_items();
       if(isset($profile_items['drama-coins'])) {
            $items[ 'drama-coins' ] 	= array(
    			'title'			=>	esc_html__( 'Coins', 'jws_streamvid' ),
    			'icon'			=>	'jws-icon-coins',
    			'callback'		=>	'',
    			'priority'		=>	35
    	   );
       }

       if(function_exists( 'wc_get_page_permalink' )) {
            $items[ 'shop' ] 	= array(
    			'title'			=>	esc_html__( 'Shop', 'jws_streamvid' ),
    			'icon'			=>	'jws-icon-handbag',
    			'callback'		=>	'',
                'url'       =>  wc_get_page_permalink( 'shop' ),
    			'priority'		=>	38
    	   );
       }

       // WooCommerce account pages of the profile (Dashboard > Shopping), not the store's Shop page.
       $user_id = get_current_user_id();
       if( function_exists( 'WC' ) && $user_id ){
            $shop_pages = array(
                'orders'       => array( esc_html__( 'Orders', 'jws_streamvid' ), 'jws-icon-list-bullets' ),
                'downloads'    => array( esc_html__( 'Downloads', 'jws_streamvid' ), 'jws-icon-arrow-line-down' ),
                'edit-address' => array( esc_html__( 'Addresses', 'jws_streamvid' ), 'jws-icon-map-pin' ),
            );
            $priority = 39;
            foreach ( $shop_pages as $key => $page ) {
                $items[ $key ] = array(
                    'title'    => $page[0],
                    'icon'     => $page[1],
                    'callback' => '',
                    // ?dashboard=shop/orders without pretty permalinks, as shop.php expects.
                    'url'      => $this->get_endpoint( $user_id, 'dashboard', 'shop/' . $key ),
                    'priority' => $priority,
                );
                $priority += 0.1;
            }
       }

       $items[ 'author' ] 	= array(
			'title'			=>	esc_html__( 'My Profile', 'jws_streamvid' ),
			'icon'			=>	'jws-icon-user-circle',
			'callback'		=>	'',
            'url'       =>  get_author_posts_url( get_current_user_id() ),
			'priority'		=>	40
	   );
       $items[ 'profile' ] 	= array(
			'title'			=>	esc_html__( 'Settings', 'jws_streamvid' ),
			'icon'			=>	'jws-icon-gear',
			'callback'		=>	'',
            'parent'    =>  'dashboard', 
			'priority'		=>	50
	   );
       $items[ 'logout' ] 	= array(
			'title'			=>	esc_html__( 'Logout', 'jws_streamvid' ),
			'icon'			=>	'jws-icon-sign-out',
			'callback'		=>	'',
            'url'       =>  esc_url( wp_logout_url( home_url( '/' ) ) ),
			'priority'		=>	60
	   );

       $this->uasort( $items );

       /*
        * Theme Options > Profile > Account Dropdown Menu sets the order and hides
        * items. Items missing from both lists (added after the last save) still show.
        */
       $header_menu_list = function_exists('jws_theme_get_option') ? jws_theme_get_option('profile_header_menu_list', []) : [];
       $header_menu_list = is_array($header_menu_list) ? $header_menu_list : json_decode( $header_menu_list, true );

       $enabled  = ( isset($header_menu_list['enabled'])  && is_array($header_menu_list['enabled'])  ) ? $header_menu_list['enabled']  : array();
       $disabled = ( isset($header_menu_list['disabled']) && is_array($header_menu_list['disabled']) ) ? $header_menu_list['disabled'] : array();
       unset( $enabled['placebo'], $disabled['placebo'] );

       /*
        * Added to the sorter after sites had saved it. Theme Options lists them
        * under Disabled until someone moves them, so they stay hidden until then.
        */
       $hidden_until_enabled = array( 'orders', 'downloads', 'edit-address' );

       // Not saved yet: match the sorter default, which hides Upload, Go live, Shop and the WooCommerce pages.
       if ( empty( $enabled ) && empty( $disabled ) ) {
            $disabled = array_fill_keys( array_merge( array( 'upload', 'live', 'shop' ), $hidden_until_enabled ), 1 );
       }

       $sorted = array();
       foreach ($enabled as $key => $label) {
            if (isset($items[$key])) {
                $sorted[$key] = $items[$key];
            }
       }
       foreach ($items as $key => $item) {
            if ( ! isset($sorted[$key]) && ! isset($disabled[$key]) && ! in_array( $key, $hidden_until_enabled, true ) ) {
                $sorted[$key] = $item;
            }
       }
       $items = $sorted;

       return apply_filters( 'streamvid_profire_menu_header_items', $items );
        
    }
    
    
    public function get_menu_membership_items(){ 
       if( ! function_exists( 'pmpro_page_meta' ) ){
            return array();
       }
       
       $items[ 'membership' ] 	= array(
			'title'			=>	esc_html__( 'Membership', 'jws_streamvid' ),
			'icon'			=>	'',
			'callback'		=>	function(){
				jws_streamvid_load_template( 'user/profile/dashboard.php' );
			},
			'priority'		=>	0,
            'submenu'	=>	array(
				'subscriptions'	=>	array(
					'title'		=>	esc_html__( 'Your Subscriptions', 'jws_streamvid' ),
					'callback'	=>	function(){
        				jws_streamvid_load_template( 'user/profile/subscriptions.php' );
        			},
					'priority'	=>	10,
                    'parent'    =>  'membership'     
				),
                'billing'	=>	array(
					'title'		=>	esc_html__( 'Billing', 'jws_streamvid' ),
					'callback'	=>	function(){
        				jws_streamvid_load_template( 'user/profile/billing.php' );
        			},
					'priority'	=>	20,
                    'parent'    =>  'membership'   
				),
                'invoices'	=>	array(
					'title'		=>	esc_html__( 'Invoices', 'jws_streamvid' ),
					'callback'	=>	function(){
        				jws_streamvid_load_template( 'user/profile/invoices.php' );
        			},
					'priority'	=>	30,
                    'parent'    =>  'membership'   
				)
           )     
	   );
       
       return apply_filters( 'streamvid_profire_menu_membership_items', $items );
    
        
        
    }
    
    public function get_menu_dashboard_items(){ 

       
       $items[ 'dashboard' ] 	= array(
			'title'			=>	esc_html__( 'Dashboard', 'jws_streamvid' ),
			'icon'			=>	'',
			'callback'		=>	function(){
				jws_streamvid_load_template( 'user/profile/dashboard.php' );
			},
			'priority'		=>	10,
            'submenu'	=>	array(
				'profile'	=>	array(
					'title'		=>	esc_html__( 'My Profile', 'jws_streamvid' ),
					'icon'		=>	'',
					'callback'		=>	function(){
        				jws_streamvid_load_template( 'user/profile/profile.php' );
        			},
        			'priority'	=>	20,
                    'parent'    =>  'dashboard'
				),
                
           )     
	   );

       if (function_exists('jws_theme_get_option') && jws_theme_get_option('diveces_management', '0') == '1') {
            $items[ 'dashboard' ]['submenu']['devices_manager']	=	array(
					'title'		=>	esc_html__( 'Devices Manager', 'jws_streamvid' ),
					'icon'		=>	'',
					'callback'		=>	function(){
        				jws_streamvid_load_template( 'user/profile/devices_manager.php' );
        			},
        			'priority'	=>	25,
                    'parent'    =>  'dashboard'
            );
       }
       
 
		if( function_exists( 'WC' )  ){
			$items[ 'dashboard' ]['submenu']['shop'] = array(
				'title'		=>	esc_html__( 'Shopping', 'streamtube-core' ),
				'icon'		=>	'',
				'callback'	=>	function(){
				    jws_streamvid_load_template( 'user/profile/woocommerce/shop.php' );
				},
                'priority'	=>	30,
				'parent'	=>	'dashboard',
				'submenu'	=>	array(
					'orders'	=>	array(
						'title'		=>	esc_html__( 'Orders', 'streamtube-core' ),
						'priority'	=>	10
					),
					'downloads'	=>	array(
						'title'		=>	esc_html__( 'Downloads', 'streamtube-core' ),
						'priority'	=>	20
					),
					'edit-address'		=>	array(
						'title'		=>	esc_html__( 'Addresses', 'streamtube-core' ),
						'priority'	=>	30
					)
				),
				'cap'		=>	'read',
			
			);			
		}
        
       return apply_filters( 'streamvid_profire_menu_woocommerce_items', $items );
  
        
        
    }

	public function get_menu_items(){
    $items = array();

    $default_items = array(
        /*
         * The account overview. Same slug as the Dashboard parent endpoint
         * (get_menu_dashboard_items), so /author/{user}/dashboard/ opens it
         * and its sub pages (dashboard/profile, ...) keep working.
         */
        'dashboard' => array(
            'title' => esc_html__( 'Dashboard', 'jws_streamvid' ),
            'icon'  => '',
            'callback' => function(){ jws_streamvid_load_template( 'user/profile/dashboard.php' ); },
            'priority' => -10
        ),
        'favorites' => array(
            'title' => esc_html__( 'Favorites', 'jws_streamvid' ),
            'icon'  => '',
            'callback' => function(){ jws_streamvid_load_template( 'user/profile/favorites.php' ); },
            'priority' => 0
        ),
        'history' => array(
            'title' => esc_html__( 'History', 'jws_streamvid' ),
            'icon'  => '',
            'callback' => function(){ jws_streamvid_load_template( 'user/profile/history.php' ); },
            'priority' => 5
        ),
        'playlist' => array(
            'title' => esc_html__( 'Playlist', 'jws_streamvid' ),
            'icon'  => '',
            'callback' => function(){ jws_streamvid_load_template( 'user/profile/playlist.php' ); },
            'priority' => 10
        ),
        'watchlist' => array(
            'title' => esc_html__( 'Watchlist', 'jws_streamvid' ),
            'icon'  => '',
            'callback' => function(){ jws_streamvid_load_template( 'user/profile/watchlist.php' ); },
            'priority' => 20
        ),
        'video' => array(
            'title' => esc_html__( 'Videos', 'jws_streamvid' ),
            'icon'  => '',
            'callback' => function(){ jws_streamvid_load_template( 'user/profile/videos.php' ); },
            'priority' => 30
        ),
    );

    if( function_exists( 'WC' )  ){
        $default_items[ 'pay_per_view' ] 	= array(
            'title'			=>	esc_html__( 'Pay Per View', 'jws_beyondtv' ),
            'icon'			=>	'',
            'callback'		=>	function(){
                jws_streamvid_load_template( 'user/profile/pay-per-view.php' );
            },
            'priority'		=>	40
        );  
        $default_items[ 'rented' ] 	= array(
            'title'			=>	esc_html__( 'Rented Videos', 'jws_beyondtv' ),
            'icon'			=>	'',
            'callback'		=>	function(){
                jws_streamvid_load_template( 'user/profile/video_rented.php' );
            },
            'priority'		=>	40
        );

        /*
         * WooCommerce account pages (Orders, Downloads, Addresses). They stay at
         * dashboard/shop/..., where shop.php reads the sub page from, so the
         * tab links there instead of getting an endpoint of its own.
         */
        $default_items[ 'shop' ] = array(
            'title'    => esc_html__( 'Shopping', 'jws_streamvid' ),
            'icon'     => '',
            'callback' => function(){ jws_streamvid_load_template( 'user/profile/woocommerce/shop.php' ); },
            'parent'   => 'dashboard',
            'priority' => 45
        );
    }


    /*
     * Modules register their own tab here so it goes through the Profile Menu
     * Sorter like every built-in one (see Jws_Drama_Coins::profile_menu).
     */
    $default_items = apply_filters( 'streamvid_profile_default_menu_items', $default_items );

    $profile_menu_list = function_exists('jws_theme_get_option') ? jws_theme_get_option('profile_menu_list', []) : [];
	$profile_menu_list = is_array($profile_menu_list) ? $profile_menu_list : json_decode( $profile_menu_list, true );

    $enabled  = ( isset($profile_menu_list['enabled'])  && is_array($profile_menu_list['enabled'])  ) ? $profile_menu_list['enabled']  : array();
    $disabled = ( isset($profile_menu_list['disabled']) && is_array($profile_menu_list['disabled']) ) ? $profile_menu_list['disabled'] : array();

    if ( ! empty( $enabled ) ) {
        foreach ($enabled as $key => $label) {
            if (isset($default_items[$key])) {
                $items[$key] = $default_items[$key];
            }
        }

        /*
         * Tabs added after the sorter was last saved are in neither list, so
         * show them instead of dropping them silently - otherwise a new tab
         * stays invisible until someone re-saves the theme options.
         */
        foreach ($default_items as $key => $item) {
            if ( ! isset($items[$key]) && ! isset($enabled[$key]) && ! isset($disabled[$key]) ) {
                $items[$key] = $item;
            }
        }
    } else {

        $items = $default_items;
    }

    return apply_filters( 'streamvid_profire_menu_items', $items );
}
    
    private function get_request_endpoint($var){
		global $wp_query;
 
		$menu_items = $this->pre_get_menu_items();
        
        if($var == 'dashboard') {
            
            $menu_items = $this->get_menu_dashboard_items(); 
        }
        
        if($var == 'membership') {
            
            $menu_items = $this->get_menu_membership_items();; 
        }
        $endpoint = isset($wp_query->query_vars[$var]) ? $wp_query->query_vars[$var] : '';
      
        
        if(!empty($endpoint)){
                return explode( '/' , $endpoint );
                
            
        } elseif(isset($wp_query->query_vars[$var])) {
        
             return $var; 
        }

		
	}
    
    public function add_endpoints(){
        
		// Tabs with a parent (Shopping: dashboard/shop) open through that parent's endpoint.
		$menu_items = array_keys( array_filter( $this->get_menu_items(), function( $item ){
			return empty( $item['parent'] );
		} ) );
        $menu_dashboard_items = array_keys($this->get_menu_dashboard_items());
        $menu_membership_items = array_keys($this->get_menu_membership_items());
       
        // "dashboard" is both a menu tab and the Dashboard parent endpoint; register it once.
        $menu_items = array_values( array_unique( array_merge($menu_items, $menu_dashboard_items , $menu_membership_items) ) );

		for ( $i=0; $i < count( $menu_items ); $i++) {
			add_rewrite_endpoint( $menu_items[$i], EP_AUTHORS );
		}

	}

    /**
     * Theme Options > Slugs > Author Slug replaces "author" in profile links
     * (/author/admin/favorites/). Set before any link or rule is built.
     */
    public function set_author_base(){
        global $wp_rewrite;

        $slug = sanitize_title( (string) jws_streamvid_options( 'author_slug' ) );

        if ( $slug && $wp_rewrite instanceof WP_Rewrite ) {
            $wp_rewrite->author_base = $slug;
        }
    }

    /**
     * Rewrite rules are stored in the database, so rebuild them once when the
     * author slug changes. Runs on wp_loaded, after the post types and the
     * profile endpoints are registered, so the rebuilt rules include them.
     */
    public function maybe_flush_author_base(){
        global $wp_rewrite;

        if ( ! $wp_rewrite instanceof WP_Rewrite ) {
            return;
        }

        $current = $wp_rewrite->author_base;
        $applied = get_option( 'jws_streamvid_author_base', false );

        if ( $applied === $current ) {
            return;
        }

        update_option( 'jws_streamvid_author_base', $current, true );

        // First run on a site that still uses the default: nothing to rebuild.
        if ( false === $applied && 'author' === $current ) {
            return;
        }

        flush_rewrite_rules( false );
    }
    
    
    public function get_endpoint( $user_id = 0, $parent_endpoint = '' , $endpoint = '' ){

		if( ! $user_id ){
			return;
		}

		$url = get_author_posts_url( $user_id);
    
        if( ! get_option( 'permalink_structure' ) ){ 
			return add_query_arg( array(
				$parent_endpoint	=>	$endpoint
			), $url );
           
		}
	
		return trailingslashit( $url ) . $parent_endpoint . '/' . $endpoint;
	}
    
     public static function get_url( $endpoint = '', $parent = '' ){
    $current_user = get_current_user_id();
    $url = is_author() ? get_author_posts_url( get_queried_object_id() ) : get_author_posts_url($current_user);

    if( ! $endpoint ){
        return trailingslashit($url);
    }

    if( ! get_option( 'permalink_structure' ) ){
        if( ! $parent ){
            $url = add_query_arg( array(
                $endpoint 	=>	1
            ), $url );
        }
        else{
            $url = add_query_arg( array(
                $parent 	=>	$endpoint
            ), $url );				
        }
        return trailingslashit($url);
    }
    else{
        $path = $endpoint;
        if( $parent ){
            $path = $parent . '/' . $endpoint;	
        }
        $url = trailingslashit( $url ) . $path;
        return trailingslashit($url);
    }
}
    
    
    public function pre_get_menu_items(){
        
		$menu_items = $this->get_menu_items();
        $menu_dashboard_items = $this->get_menu_dashboard_items();
        $menu_membership_items = $this->get_menu_membership_items();
        $menu_items = array_merge($menu_items, $menu_dashboard_items , $menu_membership_items);
        

		return $menu_items;	
	}

    
    public function get_currents_menu(){

		$current = '';
       
		$menu_items = $this->pre_get_menu_items();
        $menu_layout = $this->the_menu_items_position();
        
        
        
          $endpoint_member = $this->get_request_endpoint('membership');
          $endpoint_dashboard = $this->get_request_endpoint('dashboard');
      
          if($endpoint_member == 'membership') {
            return array_keys($menu_layout)[0];
          }
          

          if($endpoint_dashboard == 'dashboard') {

            // /dashboard/ opens the Dashboard tab when the Profile Menu Sorter has it enabled.
            if ( isset( $menu_layout['dashboard'] ) ) {
                return 'dashboard';
            }

            return array_keys($menu_layout)[0];
            
          }
  
          
          if(isset($endpoint_member[0]) && !empty($endpoint_member[0])) {
            
            $menu_items = $endpoint_member;
             
             return $menu_items[0];
            
          }
               
  
          if(isset($endpoint_dashboard[0]) && !empty($endpoint_dashboard[0])) {
            
           
            $menu_items = $endpoint_dashboard;
  
             return $menu_items[0];
            
          }
     
        
 
		if( count( $menu_items ) == 0 ){
			return false;
		}

		foreach ( $menu_items as $menu_id => $menu ) {

			$menu = wp_parse_args( $menu, array(
				'cap'	=>	'read'
			) );

			if( ! user_can( get_queried_object_id(), $menu['cap'] ) ){
				unset( $menu_items[ $menu_id ] );
			}

			if( isset( $GLOBALS['wp_query']->query_vars[$menu_id] ) ){
				$current = $menu_id;
               
			}
		}

        $return = isset(array_keys( $menu_items )[0]) ? array_keys( $menu_items )[0] : '';

		return $current ? $current : $return;
	}
    
    
    protected function uasort( &$items ){
		uasort( $items, function( $item1, $item2 ){
			return $item1['priority'] <=> $item2['priority'];
		} );
	}
    
    
    
    public  function the_main() {
        if( is_author() ){
			 jws_streamvid_load_template("user/profile/main.php", false);
			exit;
		}    
    }
    
    
    
    public  function the_menu_items_position() { 
        
            $menu_items = $this->get_menu_items();

            // V2 and V3 list every tab in one menu (V3 groups them, see user/profile/v3/layout.php).
            if(in_array(jws_theme_get_option('profile_layout'), array('v2', 'v3'), true)) {
                $dashboard_items = $this->get_menu_dashboard_items();
                $membership_items = $this->get_menu_membership_items();
                
                if(isset($dashboard_items['dashboard']['submenu']['devices_manager'])) {
                    $menu_items['devices_manager'] = $dashboard_items['dashboard']['submenu']['devices_manager'];
                }
                if(isset($dashboard_items['dashboard']['submenu']['profile'])) {
                    $menu_items['profile'] = $dashboard_items['dashboard']['submenu']['profile'];
                }
                // Add subscriptions for v2 layout
                if(isset($membership_items['membership']['submenu']['subscriptions'])) {
                    $menu_items['subscriptions'] = $membership_items['membership']['submenu']['subscriptions'];
                }
                return $menu_items;
            }
            
            $endpoint_member = $this->get_request_endpoint('membership');
            $endpoint_dashboard = $this->get_request_endpoint('dashboard');
  
            /*
             * A bare /dashboard/ comes back as the string "dashboard" ("d" at [0]). With the
             * Dashboard tab enabled it stays in the main menu; sub pages still get the submenu.
             */
            $dashboard_tab = isset( $menu_items['dashboard'] ) && 'dashboard' === $endpoint_dashboard;

            if(isset($endpoint_dashboard[0]) && !empty($endpoint_dashboard[0]) && ! $dashboard_tab) {
                $menu_items = $this->get_menu_dashboard_items();
                $menu_items = $menu_items['dashboard']['submenu'];
            }
            
            if(isset($endpoint_member[0]) && !empty($endpoint_member[0])) {
                $menu_items = $this->get_menu_membership_items();
                $menu_items = $menu_items['membership']['submenu'];
            }
            
            return $menu_items;
        
    }
    
    
    
    /**
     * PMPro gives an administrator the levels picked in the admin bar ("View
     * as": no access / all levels) everywhere except its own Account, Billing,
     * Invoice ... pages, where it shows the real ones. The profile's
     * Membership pages (membership/billing, subscriptions, invoices) are those
     * pages here, so they get the real ones too; in "no access" view the
     * Billing page showed an empty Level.
     */
    public function pmpro_true_levels(){

        global $wp_query;

        if ( is_author() && isset( $wp_query->query_vars['membership'] ) ) {
            add_filter( 'pmpro_disable_admin_membership_access', '__return_true' );
        }
    }

    /**
     * Dashboard > Shopping > Addresses saves through WooCommerce, which reads
     * the address type from the "edit-address" query var of its My Account
     * page. The profile page carries it in dashboard=shop/edit-address/{type},
     * so without this every Shipping form was handled as Billing and dropped.
     */
    public function shop_address_type(){

        global $wp;

        if ( ! is_author() || ! isset( $_POST['action'] ) || 'edit_address' !== $_POST['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce checks the nonce when it saves.
            return;
        }

        if ( preg_match( '#^shop/edit-address/(billing|shipping)\b#', (string) get_query_var( 'dashboard' ), $match ) ) {
            $wp->query_vars['edit-address'] = $match[1];
        }
    }

    /**
     * Every page the profile can open: the menu tabs plus the Dashboard and
     * Membership sub pages (dashboard/shop/orders, membership/billing, ...).
     * The Version 2 and 3 menus list only some of those, yet links to the
     * others (Dashboard cards, the account dropdown) still have to open them.
     */
    public function get_route_items() {

        $items      = $this->the_menu_items_position();
        $dashboard  = $this->get_menu_dashboard_items();
        $membership = $this->get_menu_membership_items();

        $sub_pages = array_merge(
            isset( $dashboard['dashboard']['submenu'] ) ? $dashboard['dashboard']['submenu'] : array(),
            isset( $membership['membership']['submenu'] ) ? $membership['membership']['submenu'] : array()
        );

        foreach ( $sub_pages as $id => $item ) {
            if ( ! isset( $items[ $id ] ) ) {
                $items[ $id ] = $item;
            }
        }

        return $items;
    }

    public  function the_content() {

       	$current = $this->get_currents_menu();

		$menu_items = $this->get_route_items();
        
        $is_owner = jws_streamvid_check_owner();
        
        if(!$is_owner && $current != 'video') {
            echo esc_html__('You do not have access.','jws_streamvid');
        }else {
           if( count( $menu_items ) == 0 
			|| ! array_key_exists( $current , $menu_items ) 
			|| ! array_key_exists( 'callback' , $menu_items[ $current ] ) 
			|| ! is_callable( $menu_items[ $current ]['callback'] ) ){
			// If no menu items found, we load videos template instead of empty space.
       
			return jws_streamvid_load_template( 'user/profile/favorites.php' );
		}
        
 
  
		return call_user_func( $menu_items[ $current ]['callback'] );	  
        }
 
		 
    }
    
    public  function the_header() {
            
           jws_streamvid_load_template("user/profile/header.php", false);
        
    }
    
    
   
    
    
    public function the_menu($header) {
    $current = $this->get_currents_menu();
    $menu_items = $this->the_menu_items_position();

    if($header) {
        $menu_items = $this->get_menu_menu_header_items();
    }

    $profile_menu_list = jws_theme_get_option('profile_menu_list', []);
    $profile_menu_list = is_array($profile_menu_list) ? $profile_menu_list : json_decode( $profile_menu_list, true );
    // The header items come already ordered by the Account Dropdown Menu sorter.
    if ( ! $header && (
        !isset($profile_menu_list['enabled']) ||
        !is_array($profile_menu_list['enabled']) ||
        count($profile_menu_list['enabled']) === 0
    ) ) {
        $this->uasort( $menu_items );
    }
    ?>
    <ul class="<?php echo esc_attr('nav jws-scrollbar jws-scrollbar-x');?>">
        <?php 
            $menu_li = '';
            foreach($menu_items as $id => $menu) {
                $menu = wp_parse_args( $menu, array(
                    'url'		=>	'',
                    'parent'	=>	'',
                    'private'	=>	false
                ) );
                   $menu_li .= sprintf(
                    '<li class="nav-item nav-%s %s">',
                    sanitize_html_class( $id ),
                    ($current == $id ? 'active' : '')
                );
                $menu_li .= sprintf(
                    '<a class="item-%s %s"  href="%s" %s>',
                    esc_attr($id),
                    $current == $id ? 'active' : '',
                    $menu['url'] ? esc_url( $menu['url'] ) : esc_url( $this->get_url( $id, $menu['parent'] ) ),
                    !empty($menu['popup']) ? "data-modal-jws='".esc_attr($menu['popup'])."'" : ''
                );
                if(!empty($menu['icon'])) {
                    $menu_li .= sprintf(
                        ' <i class="%s"></i>',
                        esc_attr( $menu['icon'] ),
                    );
                }
                $menu_li .= sprintf(
                    ' <span class="menu-text">%s</span>',
                    esc_html( $menu['title'] ),
                );
                $menu_li .= '</a>';
                $menu_li .= '</li>';
            }
            echo $menu_li;
        ?>
    </ul>
    <?php
}
  
}

