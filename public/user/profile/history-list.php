<?php
if (!isset($video_progress_data) || !isset($current_filter)) return;
$valid_post_types = ['movies', 'tv_shows', 'episodes', 'videos'];
$paged = isset($_POST['paged']) ? intval($_POST['paged']) : 1;
$per_page = 12;
$found = false;
$count = 0;
$start = ($paged - 1) * $per_page;
$end = $start + $per_page;
$items = [];
if(!empty($video_progress_data)) { 
    $video_progress_data = array_reverse($video_progress_data, true);
    foreach($video_progress_data as $id => $history) { 
        $post_type = get_post_type($id); 
        if(!in_array($post_type, $valid_post_types)) continue;
        if($post_type !== $current_filter) continue;
        $items[] = [
            'id' => $id,
            'history' => $history
        ];
    }
    $total = count($items);
    $paged_items = array_slice($items, $start, $per_page);
    foreach($paged_items as $setting) {
        $found = true;
        echo '<div class="jws-post-item col-xl-2 col-lg-4 col-md-6 col-6">';
            get_template_part( 'template-parts/content/content-history' , '' , $setting ); 
        echo '</div>';
    }
    if(!$found) {
        echo '<div class="jws-post-item col-xl-2 col-lg-4 col-md-6 col-12">';
            echo esc_html__('Not Found','jws_streamvid');
        echo '</div>';
    }
    $has_more = ($end < $total);
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