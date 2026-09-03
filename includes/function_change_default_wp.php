<?php


function save_comment_rating( $comment_id ) {
    $rating = isset( $_POST['comment_rating'] ) ? intval( $_POST['comment_rating'] ) : '';
    if(!empty($rating)) {
       add_comment_meta( $comment_id, 'rating', $rating, true ); 
    }
}
add_action( 'comment_post', 'save_comment_rating' );

add_filter( 'preprocess_comment', 'ci_comment_rating_require_rating' );
function ci_comment_rating_require_rating( $commentdata ) {
	if ( ! is_admin() && ( isset( $_POST['comment_rating'] ) && 0 === intval( $_POST['comment_rating'] ) ) )
	wp_die( __( 'Error: You did not add a rating. Hit the Back button on your Web browser and resubmit your comment with a rating.' ) );
	return $commentdata;
}


// Add raing to admin
function add_comment_rating_column($columns) {
    $columns['comment_rating'] = __('Rating', 'textdomain');
    return $columns;
}
add_filter('manage_edit-comments_columns', 'add_comment_rating_column');

// Display number rating
function show_comment_rating_column($column, $comment_id) { 
    $comment = get_comment($comment_id);
    $post_type = get_post_type($comment->comment_post_ID);
    
    switch ($column) {
        case 'comment_rating':
            $rating = get_comment_meta($comment_id, 'rating', true);
            echo esc_html( $rating );
            break;
    }
    
   
 
}
add_filter('manage_comments_custom_column', 'show_comment_rating_column', 10, 2);

add_action( 'restrict_manage_comments', 'add_post_type_filter_dropdown' );

function add_post_type_filter_dropdown() {
    global $wpdb;

    if ( isset( $_GET['post_type_filter'] ) && $_GET['post_type_filter'] != '' ) {
        $post_type_filter = $_GET['post_type_filter'];
    } else {
        $post_type_filter = -1;
    }

    ?>
    <select name="post_type_filter" id="post_type_filter">
        <option value="-1"><?php esc_html_e( 'All Post Types', 'textdomain' ); ?></option>
    
    </select>
    <?php
}


function jws_ci_comment_rating_get_average_ratings( $id ) {
	$comments = get_approved_comments( $id );

    if(jws_theme_get_option('enable_new_comment_system')) {
        $new_reviews = Jws_Review_Comment::get_average_rating($id);
        $comment_global = isset($new_reviews['average']) ? $new_reviews['average'] : 0;
        return $comment_global;
    }
  

	if ( $comments ) {
		$i = 0;
		$total = 0;
		foreach( $comments as $comment ){
			$rate = get_comment_meta( $comment->comment_ID, 'rating', true );
			if( isset( $rate ) && '' !== $rate ) {
				$i++;
				$total += $rate;
			}
		}

		if ( 0 === $i ) {
			return false;
		} else {
			return round( $total / $i, 1 );
		}
	} else {
		return false;
	}
}

if(!function_exists('jws_save_post_all')) {
    
    function jws_save_post_all($post_id) {
        
        if( ! current_user_can( 'edit_post', $post_id ) ){
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        
        if ( get_post_type( $post_id ) == 'videos' ||  get_post_type( $post_id ) == 'movies' ||  get_post_type( $post_id ) == 'tv_shows' ) {
            
            $liked = get_post_meta($post_id, 'likes', true);
            $views = get_post_meta($post_id, 'views', true);
            
            if (empty($views)) {
                update_post_meta( $post_id, 'views', 0 );
            } 
            if (empty($liked)) {
                update_post_meta( $post_id, 'likes', 0 );
            } 

  
        }
   
    }
    add_action('save_post','jws_save_post_all');  
}

add_action('acf/save_post', function ($post_id) {

        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;
        if (get_post_type($post_id) !== 'tv_shows') return;
      
        $seasons = get_field('tv_shows_seasons', $post_id);
     
        if (empty($seasons) || !is_array($seasons)) return;

        foreach ($seasons as $season_index => $season) {

            if (empty($season['episodes']) || !is_array($season['episodes'])) continue;

            foreach ($season['episodes'] as $ep_index => $episode_id) {

                if (get_post_type($episode_id) !== 'episodes') continue;
                update_post_meta($episode_id, 'tv_show_id', (int) $post_id);
                update_post_meta($episode_id, 'season_number', $season_index + 1);
                update_post_meta($episode_id, 'episode_number', $ep_index + 1);
            }
        }

         
    //}


}, 20); 


// filter
function my_posts_where( $where, $query ) {

   
    $meta_query = $query->get('meta_query');
    if (empty($meta_query)) {
        return $where;
    }

  
    $replacements = [
        "meta_key = 'cast_$"            => "meta_key LIKE 'cast_%",
        "meta_key = 'crew_$"            => "meta_key LIKE 'crew_%",
        "meta_key = 'tv_shows_seasons_$" => "meta_key LIKE 'tv_shows_seasons_%",
        "meta_key = 'vmap_movies_$"      => "meta_key LIKE 'vmap_movies_%",
        "meta_key = 'vmap_tv_shows_$"    => "meta_key LIKE 'vmap_tv_shows_%",
    ];

    foreach ($replacements as $find => $replace) {
        if (strpos($where, $find) !== false) {
            $where = str_replace($find, $replace, $where);
        }
    }

    return $where;
}
add_filter( 'posts_where', 'my_posts_where', 10, 2 ); 