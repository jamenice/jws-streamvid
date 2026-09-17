<?php


function save_comment_rating( $comment_id ) {
    $rating = isset( $_POST['comment_rating'] ) ? intval( $_POST['comment_rating'] ) : 0;

    if ( $rating > 0 ) {
        add_comment_meta( $comment_id, 'rating', min( 5, $rating ), true );
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

    if ( 'comment_rating' !== $column ) {
        return;
    }

    echo esc_html( get_comment_meta( $comment_id, 'rating', true ) );
}
add_filter('manage_comments_custom_column', 'show_comment_rating_column', 10, 2);


/**
 * Average star rating of a post.
 *
 * Templates call this on every card and detail page, so it answers from the
 * active review system only — the legacy path adds up the ratings in one
 * query instead of reading meta comment by comment — and remembers the answer
 * for the rest of the request.
 *
 * @param int $id Post id.
 * @return float|int|false Average, or false when nothing is rated.
 */
function jws_ci_comment_rating_get_average_ratings( $id ) {

	$id = (int) $id;

	if ( ! $id ) {
		return false;
	}

	static $cache = array();

	if ( array_key_exists( $id, $cache ) ) {
		return $cache[ $id ];
	}

	if ( jws_theme_get_option( 'enable_new_comment_system' ) && class_exists( 'Jws_Review_Comment' ) ) {
		$new_reviews  = Jws_Review_Comment::get_average_rating( $id );
		$cache[ $id ] = isset( $new_reviews['average'] ) ? $new_reviews['average'] : 0;

		return $cache[ $id ];
	}

	global $wpdb;

	$average = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT AVG( cm.meta_value + 0 )
			 FROM {$wpdb->commentmeta} cm
			 INNER JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id
			 WHERE cm.meta_key = 'rating'
			   AND cm.meta_value <> ''
			   AND c.comment_post_ID = %d
			   AND c.comment_approved = '1'",
			$id
		)
	);

	$cache[ $id ] = ( null === $average ) ? false : round( (float) $average, 1 );

	return $cache[ $id ];
}

if(!function_exists('jws_save_post_all')) {
    
    /** Give new videos, movies and tv shows their view/like counters. */
    function jws_save_post_all($post_id) {

        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }

        if ( ! in_array( get_post_type( $post_id ), array( 'videos', 'movies', 'tv_shows' ), true ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        foreach ( array( 'views', 'likes' ) as $counter ) {
            if ( '' === (string) get_post_meta( $post_id, $counter, true ) ) {
                update_post_meta( $post_id, $counter, 0 );
            }
        }
    }
    add_action('save_post','jws_save_post_all');  
}

/**
 * Stamp every episode of a tv show with its show, season and position when the
 * show is saved through ACF. The meta box system runs the same routine on its
 * own save, so both keep tv_show_id in step (including dropping the stamp from
 * episodes that left the show).
 */
add_action('acf/save_post', function ($post_id) {

        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;
        if (get_post_type($post_id) !== 'tv_shows') return;

        if ( function_exists( 'jws_metabox_tv_shows_sync_episodes' ) ) {
            jws_metabox_tv_shows_sync_episodes( $post_id );
            return;
        }

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

}, 20); 


// filter
function my_posts_where( $where, $query ) {

    $meta_query = $query->get('meta_query');
    if (empty($meta_query)) {
        return $where;
    }

    // Cheap check first: these repeater keys all end with "_$" in the meta query.
    if ( false === strpos( $where, '_$' ) ) {
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