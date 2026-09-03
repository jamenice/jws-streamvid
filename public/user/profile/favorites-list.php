<?php
$per_page = 12;
$user_id = get_current_user_id();
if (!$user_id) {
    $user_id = absint(get_queried_object_id());
}

$paged = isset($_POST['paged']) ? intval($_POST['paged']) : 1;
$current_filter = isset($_POST['favorites_filter']) ? sanitize_text_field($_POST['favorites_filter']) : (isset($current_filter) ? $current_filter : 'movies');

$favorites_list = Jws_Favorites::get_ids($user_id, $current_filter);

$found = false;
$items = array();

if(!empty($favorites_list) && is_array($favorites_list)) {
    $favorites_list = array_reverse($favorites_list);
    foreach($favorites_list as $id) {
        $status = get_post_status($id);
        if(!$status || $status != 'publish') continue; 
        $items[] = $id;
    }
    
    $total = count($items);
    $start = ($paged - 1) * $per_page;
    $paged_items = array_slice($items, $start, $per_page);
    
    foreach($paged_items as $id) {
        $found = true;
        $setting = array('id' => $id, 'image_size' => jws_theme_get_option('tv_shows_imagesize'));
        echo '<div class="jws-post-item col-xl-2 col-lg-4 col-md-6 col-6">';
        get_template_part('template-parts/content/content-favorite', '', $setting);
        echo '</div>';
    }
    
    if(!$found) {
        echo '<div class="jws-post-item col-xl-2 col-lg-4 col-md-6 col-12">';
        echo esc_html__('Not Found','jws_streamvid');
        echo '</div>';
    }
    
    $has_more = ($start + $per_page < $total);
} else {
    echo '<div class="jws-post-item col-xl-2 col-lg-4 col-md-6 col-12">';
    echo esc_html__('Not Found','jws_streamvid');
    echo '</div>';
    $has_more = false;
}

if (defined('DOING_AJAX') && DOING_AJAX) {
    wp_send_json([
        'html' => ob_get_clean(),
        'has_more' => $has_more
    ]);
}
