<?php

if(isset($_POST['post_id']) && isset($_POST['post_type']) && isset($_POST['type'])) {
    $post_id = absint($_POST['post_id']);
    $post_type = sanitize_text_field($_POST['post_type']);
    $type = sanitize_text_field($_POST['type']);
    $user_id = get_current_user_id();
    if(!$user_id) {
        wp_send_json_success('errrorr');
    }

    $liked = get_post_meta($post_id, 'likes', true);

    $status = 'bad';
    $liked_number = $liked ?: '0';
    $is_liked = Jws_Favorites::is_liked($user_id, $post_id, $post_type);

    if($type == 'dislike' && $is_liked) {
        $liked_number--;
        update_post_meta($post_id, 'likes', $liked_number);
        Jws_Favorites::remove($user_id, $post_id, $post_type);
        $message = sprintf(
                __('Unliked <strong>%s</strong>.','jws_streamvid'),
                get_the_title( $post_id )
        );
    }

    if($user_id && !$is_liked && $type == 'like') {
        Jws_Favorites::add($user_id, $post_id, $post_type);
        $liked_number++;
        update_post_meta($post_id, 'likes', $liked_number);
        $status = 'good';
        $message =  sprintf(
                __('Liked <strong>%s</strong>.','jws_streamvid'),
                get_the_title( $post_id )
        );
    }

    $result = [
        'status' => $status,
        'count'  => $liked_number,
        'message' => $message
    ];

    wp_send_json_success($result);
}
