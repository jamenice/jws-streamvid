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
 * This class defines all code for post type
 *
 * @since      1.0.0
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 * @author     Jws Theme <jwstheme@gmail.com>
 */
class Jws_Streamvid_Post {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public function custom_taxonomy_link( $termlink, $term, $taxonomy ) {
		// List of taxonomies to customize
		$custom_taxonomies = array(
			'genres'    => 'genres',
			'countries' => 'countries',
			'ages'      => 'ages',
			'topics'      => 'topics'
		);

		// Map taxonomy to query parameter
		$taxonomy_map = array(
			'genres'    => 'genres',
			'countries' => 'countries',
			'ages'      => 'ages',
			'topics'    => 'topics'
		);

		if ( in_array( $taxonomy, $custom_taxonomies )  ) {
			$genrest_new = jws_theme_get_option( 'enable_new_genre_system' );	
			$archive_page_id  = jws_theme_get_option('archive_global_page');
			if ( !empty($archive_page_id) ) {
				$archive_page_url = get_permalink( $archive_page_id );
			} elseif ( is_singular('tv_shows') || is_post_type_archive('tv_shows') || is_tax('tv_shows_cat') || is_tax('tv_shows_tag') ) {
				$archive_page_url = get_post_type_archive_link('tv_shows');
			} elseif ( is_singular('drama') || is_singular('drama_ep') || is_post_type_archive('drama') ) {
				$archive_page_url = get_post_type_archive_link('drama');
			} else {
				$archive_page_url = get_post_type_archive_link('movies');
			}
			$param_key           = isset( $taxonomy_map[ $taxonomy ] ) ? $taxonomy_map[ $taxonomy ] : $taxonomy;

			if ( $archive_page_url && $genrest_new ) {
				$termlink = add_query_arg( $param_key, $term->slug, $archive_page_url );
			}
		}

		return $termlink;
	}
	public static function create_custom_posttype() {
	   
        $labels = array(
			'name' 									=> esc_html__( 'Movies', 'jws_streamvid' ),
			'singular_name' 						=> esc_html__( 'Movie', 'jws_streamvid' )	
		);
        
        $movies_slug = jws_streamvid_options('movies_slug');
        

		$args = array(
			'label' 								=> esc_html__( 'Movies', 'jws_streamvid' ),
			'labels' 								=> $labels,
			'description' 							=> '',
			'public' 								=> true,
			'publicly_queryable' 					=> true,
			'show_ui' 								=> true,
			'show_in_rest' 							=> false,
			'rest_base' 							=> '',
			'rest_controller_class' 				=> 'WP_REST_Posts_Controller',
			'has_archive' 							=> true,
			'show_in_menu' 							=> true,
			'show_in_nav_menus' 					=> true,
			'delete_with_user' 						=> false,
			'exclude_from_search' 					=> false,
			'capability_type' 						=> 'post',
			'map_meta_cap' 							=> true,
			'hierarchical' 							=> false,
			'rewrite' 								=> array( 
				'slug'			=>	!empty($movies_slug) ? $movies_slug : 'movie', 
				'with_front'	=>	true 
			),
			'query_var' 							=> true,
			'supports' 								=>  array( 
				'title', 
				'editor', 
				'thumbnail', 
				'excerpt', 
				'trackbacks', 
				'custom-fields', 
				'comments', 
				'author' 
			),
            'menu_icon' => 'dashicons-video-alt3', 
		);
        register_post_type( 'movies', $args );
        
        $labels = array(
			'name'					=> _x( 'Movies Categories', 'Taxonomy plural name', 'zahar' ),
			'singular_name'			=> _x( 'Movies Category', 'Taxonomy singular name', 'zahar' ),
			'search_items'			=> esc_html__( 'Search Categories', 'zahar' ),
			'popular_items'			=> esc_html__( 'Popular Movies Categories', 'zahar' ),
			'all_items'				=> esc_html__( 'All Movies Categories', 'zahar' ),
			'parent_item'			=> esc_html__( 'Parent Category', 'zahar' ),
			'parent_item_colon'		=> esc_html__( 'Parent Category', 'zahar' ),
			'edit_item'				=> esc_html__( 'Edit Category', 'zahar' ),
			'update_item'			=> esc_html__( 'Update Category', 'zahar' ),
			'add_new_item'			=> esc_html__( 'Add New Category', 'zahar' ),
			'new_item_name'			=> esc_html__( 'New Category', 'zahar' ),
			'add_or_remove_items'	=> esc_html__( 'Add or remove Categories', 'zahar' ),
			'choose_from_most_used'	=> esc_html__( 'Choose from most used text-domain', 'zahar' ),
			'menu_name'				=> esc_html__( 'Category', 'zahar' ),
		);
	    $movies_cat_slug = jws_streamvid_options('movies_cat_slug');
       
		$args = array(
			'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => !empty($movies_cat_slug) ? $movies_cat_slug : 'movies_cat' , 'with_front' =>	true  ),
		);
        

        
       register_taxonomy( 'movies_cat', array( 'movies' ), $args  );


		$labels = array(
			'name'					=> _x( 'Genres', 'Taxonomy plural name', 'zahar' ),
			'singular_name'			=> _x( 'Genre', 'Taxonomy singular name', 'zahar' ),
			'search_items'			=> esc_html__( 'Search Genres', 'zahar' ),
			'popular_items'			=> esc_html__( 'Popular Genres', 'zahar' ),
			'all_items'				=> esc_html__( 'All Genres', 'zahar' ),
			'parent_item'			=> esc_html__( 'Parent Genre', 'zahar' ),
			'parent_item_colon'		=> esc_html__( 'Parent Genre', 'zahar' ),
			'edit_item'				=> esc_html__( 'Edit Genre', 'zahar' ),
			'update_item'			=> esc_html__( 'Update Genre', 'zahar' ),
			'add_new_item'			=> esc_html__( 'Add New Genre', 'zahar' ),
			'new_item_name'			=> esc_html__( 'New Genre', 'zahar' ),
			'add_or_remove_items'	=> esc_html__( 'Add or remove Genres', 'zahar' ),
			'choose_from_most_used'	=> esc_html__( 'Choose from most used text-domain', 'zahar' ),
			'menu_name'				=> esc_html__( 'Genres', 'zahar' ),
		);
	
		$args = array(
			'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'genres' , 'with_front' =>	true  ),
		);
        


       register_taxonomy( 'genres', array( 'movies','tv_shows' ), $args  ); 


	   $labels = array(
			'name'                  => _x( 'Topics', 'Taxonomy plural name', 'zahar' ),
			'singular_name'         => _x( 'Topic', 'Taxonomy singular name', 'zahar' ),
			'search_items'          => esc_html__( 'Search Topics', 'zahar' ),
			'popular_items'         => esc_html__( 'Popular Topics', 'zahar' ),
			'all_items'             => esc_html__( 'All Topics', 'zahar' ),
			'parent_item'           => esc_html__( 'Parent Topic', 'zahar' ),
			'parent_item_colon'     => esc_html__( 'Parent Topic', 'zahar' ),
			'edit_item'             => esc_html__( 'Edit Topic', 'zahar' ),
			'update_item'           => esc_html__( 'Update Topic', 'zahar' ),
			'add_new_item'          => esc_html__( 'Add New Topic', 'zahar' ),
			'new_item_name'         => esc_html__( 'New Topic', 'zahar' ),
			'add_or_remove_items'   => esc_html__( 'Add or remove Topics', 'zahar' ),
			'choose_from_most_used' => esc_html__( 'Choose from most used topics', 'zahar' ),
			'menu_name'             => esc_html__( 'Topics', 'zahar' ), 
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'topics', 'with_front' => true ),
		);

		register_taxonomy( 'topics', array( 'movies', 'tv_shows' ), $args );


	   $labels = array(
			'name'					=> _x( 'Country', 'Taxonomy plural name', 'zahar' ),
			'singular_name'			=> _x( 'Country', 'Taxonomy singular name', 'zahar' ),
			'search_items'			=> esc_html__( 'Search Countries', 'zahar' ),
			'popular_items'			=> esc_html__( 'Popular Countries', 'zahar' ),
			'all_items'				=> esc_html__( 'All Countries', 'zahar' ),
			'parent_item'			=> esc_html__( 'Parent Country', 'zahar' ),
			'parent_item_colon'		=> esc_html__( 'Parent Country', 'zahar' ),
			'edit_item'				=> esc_html__( 'Edit Country', 'zahar' ),
			'update_item'			=> esc_html__( 'Update Country', 'zahar' ),
			'add_new_item'			=> esc_html__( 'Add New Country', 'zahar' ),
			'new_item_name'			=> esc_html__( 'New Country', 'zahar' ),
			'add_or_remove_items'	=> esc_html__( 'Add or remove Countries', 'zahar' ),
			'choose_from_most_used'	=> esc_html__( 'Choose from most used text-domain', 'zahar' ), 
			'menu_name'				=> esc_html__( 'Countries', 'zahar' ), 
		);
	
		$args = array(
			'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'countries' , 'with_front' =>	true  ),
		);
		
		register_taxonomy( 'countries', array( 'movies','tv_shows' ), $args  );

		$labels = array(
			'name'					=> _x( 'Age', 'Taxonomy plural name', 'zahar' ),
			'singular_name'			=> _x( 'Age', 'Taxonomy singular name', 'zahar' ),
			'search_items'			=> esc_html__( 'Search Ages', 'zahar' ),
			'popular_items'			=> esc_html__( 'Popular Ages', 'zahar' ),
			'all_items'				=> esc_html__( 'All Ages', 'zahar' ),
			'parent_item'			=> esc_html__( 'Parent Age', 'zahar' ),
			'parent_item_colon'		=> esc_html__( 'Parent Age', 'zahar' ),
			'edit_item'				=> esc_html__( 'Edit Age', 'zahar' ),
			'update_item'			=> esc_html__( 'Update Age', 'zahar' ),
			'add_new_item'			=> esc_html__( 'Add New Age', 'zahar' ),
			'new_item_name'			=> esc_html__( 'New Age', 'zahar' ),
			'add_or_remove_items'	=> esc_html__( 'Add or remove Ages', 'zahar' ),
			'choose_from_most_used'	=> esc_html__( 'Choose from most used text-domain', 'zahar' ),
			'menu_name'				=> esc_html__( 'Ages', 'zahar' ), 
		);
	
		$args = array(
			'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'ages' , 'with_front' =>	true  ),
		);
        


       register_taxonomy( 'ages', array( 'movies','tv_shows' ), $args  );



       $labels = array(
            'name' => esc_html__( 'Tags', 'zahar' ),
            'singular_name' => esc_html__( 'Tag',  'zahar'  ),
            'search_items' =>  esc_html__( 'Search Tags' , 'zahar' ),
            'popular_items' => esc_html__( 'Popular Tags' , 'zahar' ),
            'all_items' => esc_html__( 'All Tags' , 'zahar' ),
            'parent_item' => null,
            'parent_item_colon' => null,
            'edit_item' => esc_html__( 'Edit Tag' , 'zahar' ), 
            'update_item' => esc_html__( 'Update Tag' , 'zahar' ),
            'add_new_item' => esc_html__( 'Add New Tag' , 'zahar' ),
            'new_item_name' => esc_html__( 'New Tag Name' , 'zahar' ),
            'separate_items_with_commas' => esc_html__( 'Separate tags with commas' , 'zahar' ),
            'add_or_remove_items' => esc_html__( 'Add or remove tags' , 'zahar' ),
            'choose_from_most_used' => esc_html__( 'Choose from the most used tags' , 'zahar' ),
            'menu_name' => esc_html__( 'Tags','zahar'),
        ); 
    
        $args = array(
            'hierarchical' => false,
            'labels' => $labels,
            'show_ui' => true,
            'update_count_callback' => '_update_post_term_count',
            'query_var' => true,
            'rewrite' => array( 'slug' => 'movies_tag' ),
        );
        
        register_taxonomy( 'movies_tag', array( 'movies' ), $args  );
        
        $labels = array(
			'name'					=> _x( 'Playlist', 'Taxonomy plural name', 'zahar' ),
			'singular_name'			=> _x( 'Playlist', 'Taxonomy singular name', 'zahar' ),
			'search_items'			=> esc_html__( 'Search Playlist', 'zahar' ),
			'popular_items'			=> esc_html__( 'Popular Movies Playlist', 'zahar' ),
			'all_items'				=> esc_html__( 'All Movies Playlist', 'zahar' ),
			'parent_item'			=> esc_html__( 'Parent Playlist', 'zahar' ),
			'parent_item_colon'		=> esc_html__( 'Parent Playlist', 'zahar' ),
			'edit_item'				=> esc_html__( 'Edit Playlist', 'zahar' ),
			'update_item'			=> esc_html__( 'Update Playlist', 'zahar' ),
			'add_new_item'			=> esc_html__( 'Add New Playlist', 'zahar' ),
			'new_item_name'			=> esc_html__( 'New Playlist', 'zahar' ),
			'add_or_remove_items'	=> esc_html__( 'Add or remove Playlist', 'zahar' ),
			'choose_from_most_used'	=> esc_html__( 'Choose from most used text-domain', 'zahar' ),
			'menu_name'				=> esc_html__( 'Playlist', 'zahar' ),
		);
	
		$args = array(
			'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'movies_playlist' ),
		);
        

        
       register_taxonomy( 'movies_playlist', array( 'movies' ), $args  );
        
        
        
        /* ------------------------------ */
       
       
       
       
        $labels = array(
			'name' 									=> esc_html__( 'Episodes', 'jws_streamvid' ),
			'singular_name' 						=> esc_html__( 'Episodes', 'jws_streamvid' )	
		);
        
        $episodes_slug = jws_streamvid_options('episodes_slug');

		$args = array(
			'label' 								=> esc_html__( 'Episodes', 'jws_streamvid' ),
			'labels' 								=> $labels,
			'description' 							=> '',
			'public' 								=> true,
			'publicly_queryable' 					=> true,
			'show_ui' 								=> true,
			'show_in_rest' 							=> false,
			'rest_base' 							=> '',
			'has_archive' 							=> true,
			'show_in_menu'		  => 'edit.php?post_type=tv_shows',
			'show_in_nav_menus' 					=> true,
			'delete_with_user' 						=> false,
			'exclude_from_search' 					=> false,
			'capability_type' 						=> 'post',
			'map_meta_cap' 							=> true,
			'hierarchical' 							=> false,
			'rewrite' 								=> array( 
				'slug'			=> !empty($episodes_slug) ? $episodes_slug : 'episodes', 
				'with_front'	=>	true 
			),
			'query_var' 							=> true,
			'supports' 								=>  array( 
				'title', 
				'editor', 
				'thumbnail', 
				'excerpt',  
			),
			'menu_icon'								=>	'dashicons-video-alt3'
		);
        register_post_type( 'episodes', $args );
        
        
        $labels = array(
			'name'					=> _x( 'Playlist', 'Taxonomy plural name', 'zahar' ),
			'singular_name'			=> _x( 'Playlist', 'Taxonomy singular name', 'zahar' ),
			'search_items'			=> esc_html__( 'Search Playlist', 'zahar' ),
			'popular_items'			=> esc_html__( 'Popular Playlist', 'zahar' ),
			'all_items'				=> esc_html__( 'All Playlist', 'zahar' ),
			'parent_item'			=> esc_html__( 'Parent Playlist', 'zahar' ),
			'parent_item_colon'		=> esc_html__( 'Parent Playlist', 'zahar' ),
			'edit_item'				=> esc_html__( 'Edit Playlist', 'zahar' ),
			'update_item'			=> esc_html__( 'Update Playlist', 'zahar' ),
			'add_new_item'			=> esc_html__( 'Add New Playlist', 'zahar' ),
			'new_item_name'			=> esc_html__( 'New Playlist', 'zahar' ),
			'add_or_remove_items'	=> esc_html__( 'Add or remove Playlist', 'zahar' ),
			'choose_from_most_used'	=> esc_html__( 'Choose from most used text-domain', 'zahar' ),
			'menu_name'				=> esc_html__( 'Playlist', 'zahar' ),
		);
	
		$args = array(
			'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'episodes_playlist' ),
		);
        

        
       register_taxonomy( 'episodes_playlist', array( 'episodes' ), $args  );
        
        
        $labels = array(
			'name' 									=> esc_html__( 'Tv Shows', 'jws_streamvid' ),
			'singular_name' 						=> esc_html__( 'Tv Shows', 'jws_streamvid' )	
		);
        
        $tv_shows_slug = jws_streamvid_options('tv_shows_slug');

		$args = array(
			'label' 								=> esc_html__( 'Tv Shows', 'jws_streamvid' ),
			'labels' 								=> $labels,
			'description' 							=> '',
			'public' 								=> true,
			'publicly_queryable' 					=> true,
			'show_ui' 								=> true,
			'show_in_rest' 							=> false,
			'rest_base' 							=> '',
			'rest_controller_class' 				=> 'WP_REST_Posts_Controller',
			'has_archive' 							=> true,
			'show_in_menu' 							=> true,
			'show_in_nav_menus' 					=> true,
			'delete_with_user' 						=> false,
			'exclude_from_search' 					=> false,
			'capability_type' 						=> 'post',
			'map_meta_cap' 							=> true,
			'hierarchical' 							=> false,
			'rewrite' 								=> array( 
				'slug'			=>	!empty($tv_shows_slug) ? $tv_shows_slug : 'tv_shows', 
				'with_front'	=>	true 
			),
			'query_var' 							=> true,
			'supports' 								=>  array( 
				'title', 
				'editor', 
				'thumbnail', 
				'excerpt', 
				'trackbacks', 
				'custom-fields', 
				'comments', 
				'author' 
			),
			'menu_icon'			=>	'dashicons-video-alt3'
		);
        register_post_type( 'tv_shows', $args );
        
        $labels = array(
			'name'					=> _x( 'Tv Shows Categories', 'Taxonomy plural name', 'zahar' ),
			'singular_name'			=> _x( 'Tv Shows Category', 'Taxonomy singular name', 'zahar' ),
			'search_items'			=> esc_html__( 'Search Categories', 'zahar' ),
			'popular_items'			=> esc_html__( 'Popular Tv Shows Categories', 'zahar' ),
			'all_items'				=> esc_html__( 'All Tv Shows Categories', 'zahar' ),
			'parent_item'			=> esc_html__( 'Parent Category', 'zahar' ),
			'parent_item_colon'		=> esc_html__( 'Parent Category', 'zahar' ),
			'edit_item'				=> esc_html__( 'Edit Category', 'zahar' ),
			'update_item'			=> esc_html__( 'Update Category', 'zahar' ),
			'add_new_item'			=> esc_html__( 'Add New Category', 'zahar' ),
			'new_item_name'			=> esc_html__( 'New Category', 'zahar' ),
			'add_or_remove_items'	=> esc_html__( 'Add or remove Categories', 'zahar' ),
			'choose_from_most_used'	=> esc_html__( 'Choose from most used text-domain', 'zahar' ),
			'menu_name'				=> esc_html__( 'Category', 'zahar' ),
		);
	    $tv_shows_cat_slug = jws_streamvid_options('tv_shows_cat_slug');
		$args = array(
			'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => !empty($tv_shows_cat_slug) ? $tv_shows_cat_slug : 'tv_shows_cat' ),
		);

       register_taxonomy( 'tv_shows_cat', array( 'tv_shows' ), $args  );
       
       
       $labels = array(
            'name' => esc_html__( 'Tags', 'zahar' ),
            'singular_name' => esc_html__( 'Tag',  'zahar'  ),
            'search_items' =>  esc_html__( 'Search Tags' , 'zahar' ),
            'popular_items' => esc_html__( 'Popular Tags' , 'zahar' ),
            'all_items' => esc_html__( 'All Tags' , 'zahar' ),
            'parent_item' => null,
            'parent_item_colon' => null,
            'edit_item' => esc_html__( 'Edit Tag' , 'zahar' ), 
            'update_item' => esc_html__( 'Update Tag' , 'zahar' ),
            'add_new_item' => esc_html__( 'Add New Tag' , 'zahar' ),
            'new_item_name' => esc_html__( 'New Tag Name' , 'zahar' ),
            'separate_items_with_commas' => esc_html__( 'Separate tags with commas' , 'zahar' ),
            'add_or_remove_items' => esc_html__( 'Add or remove tags' , 'zahar' ),
            'choose_from_most_used' => esc_html__( 'Choose from the most used tags' , 'zahar' ),
            'menu_name' => esc_html__( 'Tags','zahar'),
        ); 
    
        $args = array(
            'hierarchical' => false,
            'labels' => $labels,
            'show_ui' => true,
            'update_count_callback' => '_update_post_term_count',
            'query_var' => true,
            'rewrite' => array( 'slug' => 'tv_shows_tag' ),
        );
        
        register_taxonomy( 'tv_shows_tag', array( 'tv_shows' ), $args  );
       
       
       
       $labels = array(
			'name' 									=> esc_html__( 'Videos', 'jws_streamvid' ),
			'singular_name' 						=> esc_html__( 'Videos', 'jws_streamvid' )	
		);
        
        $videos_slug = jws_streamvid_options('videos_slug');

		$args = array(
			'label' 								=> esc_html__( 'Videos', 'jws_streamvid' ),
			'labels' 								=> $labels,
			'description' 							=> '',
			'public' 								=> true,
			'publicly_queryable' 					=> true,
			'show_ui' 								=> true,
			'show_in_rest' 							=> false,
			'rest_base' 							=> '',
			'rest_controller_class' 				=> 'WP_REST_Posts_Controller',
			'has_archive' 							=> true,
			'show_in_menu' 							=> true,
			'show_in_nav_menus' 					=> true,
			'delete_with_user' 						=> false,
			'exclude_from_search' 					=> false,
			'capability_type' 						=> 'post',
			'map_meta_cap' 							=> true,
			'hierarchical' 							=> false,
			'rewrite' 								=> array( 
				'slug'			=>	!empty($videos_slug) ? $videos_slug : 'videos', 
				'with_front'	=>	true 
			),
			'query_var' 							=> true,
			'supports' 								=>  array( 
				'title', 
				'editor', 
				'thumbnail', 
				'excerpt', 
				'trackbacks', 
				'custom-fields', 
				'comments', 
				'author' 
			),
			'menu_icon'								=>	'dashicons-video-alt3'
		);
        register_post_type( 'videos', $args );
        
        $labels = array(
			'name'					=> _x( 'Videos Categories', 'Taxonomy plural name', 'zahar' ),
			'singular_name'			=> _x( 'Videos Category', 'Taxonomy singular name', 'zahar' ),
			'search_items'			=> esc_html__( 'Search Categories', 'zahar' ),
			'popular_items'			=> esc_html__( 'Popular Videos Categories', 'zahar' ),
			'all_items'				=> esc_html__( 'All Videos Categories', 'zahar' ),
			'parent_item'			=> esc_html__( 'Parent Category', 'zahar' ),
			'parent_item_colon'		=> esc_html__( 'Parent Category', 'zahar' ),
			'edit_item'				=> esc_html__( 'Edit Category', 'zahar' ),
			'update_item'			=> esc_html__( 'Update Category', 'zahar' ),
			'add_new_item'			=> esc_html__( 'Add New Category', 'zahar' ),
			'new_item_name'			=> esc_html__( 'New Category', 'zahar' ),
			'add_or_remove_items'	=> esc_html__( 'Add or remove Categories', 'zahar' ),
			'choose_from_most_used'	=> esc_html__( 'Choose from most used text-domain', 'zahar' ),
			'menu_name'				=> esc_html__( 'Category', 'zahar' ),
		);
	    $videos_cat_slug = jws_streamvid_options('videos_cat_slug');
		$args = array(
			'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => !empty($videos_cat_slug) ? $videos_cat_slug : 'videos_cat' ),
		);
        

        
       register_taxonomy( 'videos_cat', array( 'videos' ), $args  );
       
       
       $labels = array(
			'name'					=> _x( 'Playlist', 'Taxonomy plural name', 'zahar' ),
			'singular_name'			=> _x( 'Playlist', 'Taxonomy singular name', 'zahar' ),
			'search_items'			=> esc_html__( 'Search Playlist', 'zahar' ),
			'popular_items'			=> esc_html__( 'Popular Videos Playlist', 'zahar' ),
			'all_items'				=> esc_html__( 'All Videos Playlist', 'zahar' ),
			'parent_item'			=> esc_html__( 'Parent Playlist', 'zahar' ),
			'parent_item_colon'		=> esc_html__( 'Parent Playlist', 'zahar' ),
			'edit_item'				=> esc_html__( 'Edit Playlist', 'zahar' ),
			'update_item'			=> esc_html__( 'Update Playlist', 'zahar' ),
			'add_new_item'			=> esc_html__( 'Add New Playlist', 'zahar' ),
			'new_item_name'			=> esc_html__( 'New Playlist', 'zahar' ),
			'add_or_remove_items'	=> esc_html__( 'Add or remove Playlist', 'zahar' ),
			'choose_from_most_used'	=> esc_html__( 'Choose from most used text-domain', 'zahar' ),
			'menu_name'				=> esc_html__( 'Playlist', 'zahar' ),
		);
	
		$args = array(
			'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'videos_playlist' ),
		);
        

        
       register_taxonomy( 'videos_playlist', array( 'videos' ), $args  );
       
       $labels = array(
            'name' => esc_html__( 'Tags', 'zahar' ),
            'singular_name' => esc_html__( 'Tag',  'zahar'  ),
            'search_items' =>  esc_html__( 'Search Tags' , 'zahar' ),
            'popular_items' => esc_html__( 'Popular Tags' , 'zahar' ),
            'all_items' => esc_html__( 'All Tags' , 'zahar' ),
            'parent_item' => null,
            'parent_item_colon' => null,
            'edit_item' => esc_html__( 'Edit Tag' , 'zahar' ), 
            'update_item' => esc_html__( 'Update Tag' , 'zahar' ),
            'add_new_item' => esc_html__( 'Add New Tag' , 'zahar' ),
            'new_item_name' => esc_html__( 'New Tag Name' , 'zahar' ),
            'separate_items_with_commas' => esc_html__( 'Separate tags with commas' , 'zahar' ),
            'add_or_remove_items' => esc_html__( 'Add or remove tags' , 'zahar' ),
            'choose_from_most_used' => esc_html__( 'Choose from the most used tags' , 'zahar' ),
            'menu_name' => esc_html__( 'Tags','zahar'),
        ); 
    
        $args = array(
            'hierarchical' => false,
            'labels' => $labels,
            'show_ui' => true,
            'update_count_callback' => '_update_post_term_count',
            'query_var' => true,
            'rewrite' => array( 'slug' => 'videos_tag' ),
        );
        
        register_taxonomy( 'videos_tag', array( 'videos' ), $args  );
       
       
        $labels = array(
			'name' 									=> esc_html__( 'Person', 'jws_streamvid' ),
			'singular_name' 						=> esc_html__( 'Person', 'jws_streamvid' )	
		);
        
        $person_slug = jws_streamvid_options('person_slug');

		$args = array(
			'label' 								=> esc_html__( 'Person', 'jws_streamvid' ),
			'labels' 								=> $labels,
			'description' 							=> '',
			'public' 								=> true,
			'publicly_queryable' 					=> true,
			'show_ui' 								=> true,
			'show_in_rest' 							=> false,
			'rest_base' 							=> '',
			'rest_controller_class' 				=> 'WP_REST_Posts_Controller',
			'has_archive' 							=> true,
			'show_in_menu' 							=> true,
			'show_in_nav_menus' 					=> true,
			'delete_with_user' 						=> false,
			'exclude_from_search' 					=> false,
			'capability_type' 						=> 'post',
			'map_meta_cap' 							=> true,
			'hierarchical' 							=> false,
			'rewrite' 								=> array( 
				'slug'			=>	!empty($person_slug) ? $person_slug :  'person', 
				'with_front'	=>	true 
			),
			'query_var' 							=> true,
			'supports' 								=>  array( 
				'title', 
				'editor', 
				'thumbnail', 
				'excerpt', 
				'trackbacks', 
				'custom-fields', 
				'comments', 
				'author' 
			),
			'menu_icon'								=>	'dashicons-admin-users'
		);
        register_post_type( 'person', $args );
       
        $labels = array(
			'name'					=> _x( 'Person Categories', 'Taxonomy plural name', 'zahar' ),
			'singular_name'			=> _x( 'Person Category', 'Taxonomy singular name', 'zahar' ),
			'search_items'			=> esc_html__( 'Search Categories', 'zahar' ),
			'popular_items'			=> esc_html__( 'Popular Person Categories', 'zahar' ),
			'all_items'				=> esc_html__( 'All Person Categories', 'zahar' ),
			'parent_item'			=> esc_html__( 'Parent Category', 'zahar' ),
			'parent_item_colon'		=> esc_html__( 'Parent Category', 'zahar' ),
			'edit_item'				=> esc_html__( 'Edit Category', 'zahar' ),
			'update_item'			=> esc_html__( 'Update Category', 'zahar' ),
			'add_new_item'			=> esc_html__( 'Add New Category', 'zahar' ),
			'new_item_name'			=> esc_html__( 'New Category', 'zahar' ),
			'add_or_remove_items'	=> esc_html__( 'Add or remove Categories', 'zahar' ),
			'choose_from_most_used'	=> esc_html__( 'Choose from most used text-domain', 'zahar' ),
			'menu_name'				=> esc_html__( 'Category', 'zahar' ),
		);
	    $person_cat_slug = jws_streamvid_options('person_cat_slug');
		$args = array(
			'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => !empty($person_cat_slug) ? $person_cat_slug :  'person_cat' ),
		);
        

        
       register_taxonomy( 'person_cat', array( 'person' ), $args  );
       
       $labels = array(
                'name' => _x('Advertising', 'Post Type General Name', 'jws_streamvid'),
                'singular_name' => _x('Advertising', 'Post Type Singular Name', 'jws_streamvid'),
                'menu_name' => __('Advertising', 'jws_streamvid'),
                'parent_item_colon' => __('Parent discussion', 'jws_streamvid'),
                'all_items' => __('Advertising', 'jws_streamvid'),
                'view_item' => __('View discussions', 'jws_streamvid'),
                'add_new_item' => __('Add new question', 'jws_streamvid'),
                'add_new' => __('Add new', 'jws_streamvid'),
                'edit_item' => __('Edit discussion', 'jws_streamvid'),
                'update_item' => __('Update discussion', 'jws_streamvid'),
                'search_items' => __('Search discussion', 'jws_streamvid'),
                'not_found' => __('Not found', 'jws_streamvid'),
                'not_found_in_trash' => __('Not found in the bin', 'jws_streamvid'),
         );

         // Set other options for Custom Post Type

         $args = array(
                'label' => __('Advertising', 'jws_streamvid'),
                'description' => __('Advertising', 'jws_streamvid'),
                'labels' => $labels,
                // Features this CPT supports in Post Editor
                'supports' => array(
                    'title',
                    //'editor',
                    //'author',
                ),
                'hierarchical' => false,
                'public' => false,
                'show_ui' => true,
                'show_in_menu'	 => true,
                'show_in_nav_menus' => false,
                'show_in_admin_bar' => false,
                'menu_position' => 9,
                'can_export' => false,
                'has_archive' => false,
                'exclude_from_search' => true,
                'menu_icon' => 'dashicons-clipboard',
                'query_var' => false,
                'publicly_queryable'  => true,
                'capability_type'     => 'page',
        );
        
        register_post_type( 'advertising', $args );
        
        $labels = array(
                'name' => _x('Ads VMAP', 'Post Type General Name', 'jws_streamvid'),
                'singular_name' => _x('Ads VMAP', 'Post Type Singular Name', 'jws_streamvid'),
                'menu_name' => __('Ads VMAP', 'jws_streamvid'),
                'parent_item_colon' => __('Parent discussion', 'jws_streamvid'),
                'all_items' => __('Ads VMAP', 'jws_streamvid'),
                'view_item' => __('View discussions', 'jws_streamvid'),
                'add_new_item' => __('Add new question', 'jws_streamvid'),
                'add_new' => __('Add new', 'jws_streamvid'),
                'edit_item' => __('Edit discussion', 'jws_streamvid'),
                'update_item' => __('Update discussion', 'jws_streamvid'),
                'search_items' => __('Search discussion', 'jws_streamvid'),
                'not_found' => __('Not found', 'jws_streamvid'),
                'not_found_in_trash' => __('Not found in the bin', 'jws_streamvid'),
         );

         // Set other options for Custom Post Type

         $args = array(
                'label' => __('Ads VMAP', 'jws_streamvid'),
                'description' => __('Ads VMAP', 'jws_streamvid'),
                'labels' => $labels,
                // Features this CPT supports in Post Editor
                'supports' => array(
                    'title',
                    //'editor',
                    //'author',
                ),
                'hierarchical' => false,
                'public' => false,
                'show_ui' => true,
                'show_in_menu'	 => 'edit.php?post_type=advertising',
                'show_in_nav_menus' => false,
                'show_in_admin_bar' => false,
                'menu_position' => 9,
                'can_export' => false,
                'has_archive' => false,
                'exclude_from_search' => true,
                'menu_icon' => 'dashicons-clipboard',
                'query_var' => false,
                'publicly_queryable'  => true,
                'capability_type'     => 'page',
        );
        
        register_post_type( 'adsvmap', $args );
        
	}
    
    public function add_filter_column_videos($defaults) {

        $defaults['featured_image'] = esc_html__('Featured Image','seatevent');
        
        return $defaults;
        
    }
    
    public function show_filter_column_videos($column_name, $post_id) {

        if ($column_name == 'featured_image') {
            echo get_the_post_thumbnail($post_id, 'thumbnail'); 
			
        }

    }
    
    public function add_filter_column_movies($defaults) {

        $defaults['featured_image'] = esc_html__('Featured Image','seatevent');
        
        return $defaults;
        
    }
    
    public function show_filter_column_movies($column_name, $post_id) {

        if ($column_name == 'featured_image') {
            $image = jws_poster_banner_image($post_id, 'thumbnail');
            echo !empty($image) ? $image : '';  
        }

    }
    
    public function add_filter_column_tv_shows($defaults) {

        $defaults['featured_image'] = esc_html__('Featured Image','seatevent');
        
        return $defaults;
        
    }
    
    public function show_filter_column_tv_shows($column_name, $post_id) {

        if ($column_name == 'featured_image') {
            $image = jws_poster_banner_image($post_id, 'thumbnail');
            echo !empty($image) ? $image : '';  
        }

    }
    
    public function add_filter_column_episodes($defaults) {

        $defaults['featured_image'] = esc_html__('Featured Image','seatevent');
        
        return $defaults;
        
    }
    
    public function show_filter_column_episodes($column_name, $post_id) {

        if ($column_name == 'featured_image') {
            $image = jws_poster_banner_image($post_id, 'thumbnail');
            echo !empty($image) ? $image : '';  
        }

    }
    
    public function add_filter_column_person($defaults) {

        $defaults['featured_image'] = esc_html__('Featured Image','seatevent');
        
        return $defaults;
        
    }
    
    public function show_filter_column_person($column_name, $post_id) {

        if ($column_name == 'featured_image') {
            $image = jws_poster_banner_image($post_id, 'thumbnail');
            echo !empty($image) ? $image : '';  
        }

    }
}


add_action('admin_menu', 'jws_custom_episodes_playlist');

function jws_custom_episodes_playlist() {
    
     add_submenu_page(
        'edit.php?post_type=tv_shows',
        'Playlist',  
        'Playlist',     
        'manage_categories',   
        'edit-tags.php?taxonomy=episodes_playlist&post_type=tv_shows' 
    );
    
}

add_filter('the_title', function($title, $post_id) {
    
    if (is_admin() && get_post_type($post_id) === 'episodes') {
     
        $tv_show_id = jws_episodes_check_type($post_id);
        $season_index = get_post_meta($post_id, 'season_number', true);
        if ($tv_show_id) {
            $tv_show_title = get_the_title($tv_show_id);
            if(!empty($season_index)) {
                $title .= ' - ' . esc_html__('Season: ', 'streamvid') . ' ' . $season_index. ' - ';
            }
            if ($tv_show_title) {
                $title .= ' (' . $tv_show_title . ')';
            }
        }
    }
    return $title;
}, 10, 2); 

/**
* Filter episodes by TV Show in admin list
*/

// Add filter UI (search box + hidden input) above episodes list
add_action('restrict_manage_posts', 'jws_episodes_filter_by_tvshow');
function jws_episodes_filter_by_tvshow() {
global $typenow;
if ($typenow !== 'episodes') return;

$selected_id = isset($_GET['filter_tv_show_id']) ? intval($_GET['filter_tv_show_id']) : 0;
$selected_name = '';
if ($selected_id) {
$selected_name = get_the_title($selected_id);
}

$selected_season = isset($_GET['filter_season_number']) ? intval($_GET['filter_season_number']) : 0;

// Build season options: if a TV show is selected, get its seasons from meta; otherwise show a numeric range
$season_options = array();
if ($selected_id) {
    $seasons_data = get_post_meta($selected_id, 'tv_shows_seasons', true);
    if (!empty($seasons_data) && is_array($seasons_data)) {
        foreach ($seasons_data as $index => $season) {
            $season_options[] = $index + 1;
        }
    }
}
// Fallback: scan existing episode meta for this TV show
if (empty($season_options)) {
    global $wpdb;
    $where_tvshow = $selected_id ? $wpdb->prepare("AND pm2.meta_value = %d", $selected_id) : '';
    $rows = $wpdb->get_col(
        "SELECT DISTINCT CAST(pm.meta_value AS UNSIGNED) FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'episodes' AND p.post_status != 'trash'
         LEFT JOIN {$wpdb->postmeta} pm2 ON pm2.post_id = pm.post_id AND pm2.meta_key = 'tv_show_id'
         WHERE pm.meta_key = 'season_number' AND pm.meta_value != '' $where_tvshow
         ORDER BY 1 ASC"
    );
    $season_options = array_filter(array_map('intval', $rows));
}
?>
<style>
#jws-tvshow-filter-wrap { display:inline-flex; align-items:center; gap:4px; position:relative; }
#jws-tvshow-search-input { min-width:200px; }
#jws-tvshow-autocomplete { position:absolute; top:100%; left:0; z-index:9999; background:#fff; border:1px solid #ccc; min-width:220px; max-height:250px; overflow-y:auto; display:none; }
#jws-tvshow-autocomplete .jws-tvshow-item { padding:6px 10px; cursor:pointer; font-size:13px; }
#jws-tvshow-autocomplete .jws-tvshow-item:hover { background:#f0f0f0; }
</style>
<div id="jws-tvshow-filter-wrap">
<input type="text"
id="jws-tvshow-search-input"
placeholder="<?php esc_attr_e('Search TV Show...', 'jws_streamvid'); ?>"
value="<?php echo esc_attr($selected_name); ?>"
autocomplete="off" />
<input type="hidden" name="filter_tv_show_id" id="jws-tvshow-filter-id" value="<?php echo esc_attr($selected_id); ?>" />
<?php if ($selected_id) : ?>
<a href="#" id="jws-tvshow-clear" title="<?php esc_attr_e('Clear', 'jws_streamvid'); ?>">&#10005;</a>
<?php endif; ?>
<div id="jws-tvshow-autocomplete"></div>
</div>

<select name="filter_season_number" id="jws-season-filter">
    <option value="0"><?php esc_html_e('— All Seasons —', 'jws_streamvid'); ?></option>
    <?php if (!empty($season_options)) : ?>
        <?php foreach ($season_options as $s) : ?>
            <option value="<?php echo esc_attr($s); ?>" <?php selected($selected_season, $s); ?>>
                <?php printf(esc_html__('Season %d', 'jws_streamvid'), $s); ?>
            </option>
        <?php endforeach; ?>
    <?php else : ?>
        <?php for ($s = 1; $s <= 20; $s++) : ?>
            <option value="<?php echo esc_attr($s); ?>" <?php selected($selected_season, $s); ?>>
                <?php printf(esc_html__('Season %d', 'jws_streamvid'), $s); ?>
            </option>
        <?php endfor; ?>
    <?php endif; ?>
</select>

<script>
(function($){
$(function(){
var $input = $('#jws-tvshow-search-input');
var $hidden = $('#jws-tvshow-filter-id');
var $list = $('#jws-tvshow-autocomplete');
var $seasonSelect = $('#jws-season-filter');
var timer = null;

// When TV show changes, reload season options via AJAX
function reloadSeasons(tvShowId) {
    $seasonSelect.empty().append('<option value="0"><?php echo esc_js(__('— All Seasons —', 'jws_streamvid')); ?></option>');
    if (!tvShowId) {
        for (var s = 1; s <= 20; s++) {
            $seasonSelect.append('<option value="' + s + '"><?php echo esc_js(__('Season', 'jws_streamvid')); ?> ' + s + '</option>');
        }
        return;
    }
    $.ajax({
        url: ajaxurl,
        data: { action: 'jws_get_tvshow_seasons', tv_show_id: tvShowId, nonce: '<?php echo wp_create_nonce('jws_get_tvshow_seasons'); ?>' },
        success: function(res) {
            if (res.data && res.data.length) {
                $.each(res.data, function(i, season) {
                    $seasonSelect.append('<option value="' + season + '"><?php echo esc_js(__('Season', 'jws_streamvid')); ?> ' + season + '</option>');
                });
            }
        }
    });
}

$input.on('input', function(){
clearTimeout(timer);
var q = $(this).val().trim();
$hidden.val('');
if (q.length < 1) { $list.hide().empty(); return; }
timer = setTimeout(function(){
$.ajax({
url: ajaxurl,
data: { action: 'jws_search_tvshow', q: q, nonce: '<?php echo wp_create_nonce('jws_search_tvshow'); ?>' },
success: function(res){
$list.empty();
if (res.data && res.data.length) {
$.each(res.data, function(i, item){
$('<div class="jws-tvshow-item">').text(item.title).attr('data-id', item.id).appendTo($list);
});
$list.show();
} else {
$list.hide();
}
}
});
}, 300);
});

$list.on('click', '.jws-tvshow-item', function(){
var id = $(this).data('id');
$input.val($(this).text());
$hidden.val(id);
$list.hide().empty();
reloadSeasons(id);
});

$(document).on('click', function(e){
if (!$(e.target).closest('#jws-tvshow-filter-wrap').length) {
$list.hide();
}
});

$('#jws-tvshow-clear').on('click', function(e){
e.preventDefault();
$input.val('');
$hidden.val('');
reloadSeasons(0);
});
});
})(jQuery);
</script>
<?php
}

// Apply the filter to the WP_Query for episodes
add_action('pre_get_posts', 'jws_episodes_filter_by_tvshow_query');
function jws_episodes_filter_by_tvshow_query($query) {
if (!is_admin() || !$query->is_main_query()) return;
if ($query->get('post_type') !== 'episodes') return;

$tv_show_id    = isset($_GET['filter_tv_show_id'])     ? intval($_GET['filter_tv_show_id'])     : 0;
$season_number = isset($_GET['filter_season_number'])  ? intval($_GET['filter_season_number'])  : 0;

if (!$tv_show_id && !$season_number) return;

$meta_query = $query->get('meta_query') ?: array();

if ($tv_show_id) {
    $meta_query[] = array(
        'key'   => 'tv_show_id',
        'value' => $tv_show_id,
        'type'  => 'NUMERIC',
    );
}

if ($season_number) {
    $meta_query[] = array(
        'key'   => 'season_number',
        'value' => $season_number,
        'type'  => 'NUMERIC',
    );
}

$query->set('meta_query', $meta_query);
}

// AJAX: get seasons of a specific TV Show
add_action('wp_ajax_jws_get_tvshow_seasons', 'jws_ajax_get_tvshow_seasons');
function jws_ajax_get_tvshow_seasons() {
    check_ajax_referer('jws_get_tvshow_seasons', 'nonce');

    $tv_show_id = isset($_GET['tv_show_id']) ? intval($_GET['tv_show_id']) : 0;
    if (!$tv_show_id) {
        wp_send_json_success(array());
    }

    $seasons = array();

    // Try to get seasons from ACF field first
    $seasons_data = get_post_meta($tv_show_id, 'tv_shows_seasons', true);
    if (!empty($seasons_data) && is_array($seasons_data)) {
        foreach ($seasons_data as $index => $season) {
            $seasons[] = $index + 1;
        }
    }

    // Fallback: scan episode meta for this TV show
    if (empty($seasons)) {
        global $wpdb;
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT CAST(pm.meta_value AS UNSIGNED) FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'episodes' AND p.post_status != 'trash'
                 INNER JOIN {$wpdb->postmeta} pm2 ON pm2.post_id = pm.post_id AND pm2.meta_key = 'tv_show_id' AND pm2.meta_value = %d
                 WHERE pm.meta_key = 'season_number' AND pm.meta_value != ''
                 ORDER BY 1 ASC",
                $tv_show_id
            )
        );
        $seasons = array_values(array_filter(array_map('intval', $rows)));
    }

    wp_send_json_success($seasons);
}

// AJAX: search TV Shows by title
add_action('wp_ajax_jws_search_tvshow', 'jws_ajax_search_tvshow');
function jws_ajax_search_tvshow() {
check_ajax_referer('jws_search_tvshow', 'nonce');

$q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
if (empty($q)) {
wp_send_json_success(array());
}

$posts = get_posts(array(
'post_type' => 'tv_shows',
'post_status' => 'publish', 
's' => $q,
'posts_per_page' => 20,
'orderby' => 'title',
'order' => 'ASC',
));

$results = array();
foreach ($posts as $post) {
$results[] = array(
'id' => $post->ID,
'title' => $post->post_title,
);
}

wp_send_json_success($results);
}