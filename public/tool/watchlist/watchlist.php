<?php

    $errors = new WP_Error();

    if(!isset($_POST['post_id'])) {

        $errors->add(
                'video_empty',
                esc_html__( 'No video selected yet.', 'jws_streamvid' )
        );

    }

    if( $errors->get_error_code() ){
             wp_send_json_error( $errors );
    }

    $type = sanitize_text_field($_POST['type']);

    $user_id = get_current_user_id();

    $status = 'bad';

    // Bulk delete from the profile's watchlist tab sends post_id as an array of checked ids.
    if($user_id && $type == 'watchlist_many') {
        $post_ids = array_filter(array_map('absint', (array) $_POST['post_id']));
        foreach($post_ids as $post_id) {
            Jws_Watchlist::remove($user_id, $post_id);
        }
        $status = 'good';
        $message = esc_html__('Removed from watchlist.','jws_streamvid');
    } else {
        $post_id = absint($_POST['post_id']);
        $is_watchlisted = Jws_Watchlist::is_watchlisted($user_id, $post_id);

        if($type == 'watchlisted' && $is_watchlisted) {
            Jws_Watchlist::remove($user_id, $post_id);
            $message = esc_html__('Removed from watchlist.','jws_streamvid');
        }

        if($user_id && !$is_watchlisted && $type == 'watchlist') {
            Jws_Watchlist::add($user_id, $post_id);
            $status = 'good';
            $message =  sprintf(
                __('Added <strong>%s</strong> to watchlist.','jws_streamvid'),
                get_the_title( $post_id )
            );
        }
    }

    $result = [
        'status' => $status,
        'message' => $message
    ];

    wp_send_json_success($result);
