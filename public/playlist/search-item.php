<?php
if( ! defined( 'ABSPATH' ) ){
    exit;
}
$args = wp_parse_args( $args, array(
    'term_id' => 0,
    'post_type' => 'videos',
    'playlist_type' => 'videos_playlist'
) );

extract( $args );

if(isset($_GET['playlist_type'])) {
    $post_type = jws_streamvid()->get()->playlist->playlist_post_type();
    $playlist_type = $_GET['playlist_type'];
    $post_type = $post_type[$playlist_type];
}





?>
<div id="add-item-playlist" class="mfp-hide">

    <div class="form-head">
        <h5 class="search-title"><?php echo esc_html__('Search Videos','jws_streamvid');  ?></h5>
    </div>

<div class="form-body">

    <form class="form">
        <p class="field-item search-field">
            <input name="search" type="text" class="form-control" placeholder="<?php echo esc_attr__('Search...','jws_streamvid'); ?>">
            <button class="save-modal" type="submit"><i class="jws-icon-magnifying-glass"></i></button> 
        </p>                     
        <input type="hidden" name="term_id" value="0">
        <input type="hidden" name="action" value="search_item_playlist">
        <input type="hidden" name="post_type" value="<?php echo esc_attr($post_type); ?>">
        <input type="hidden" name="playlist_type" value="<?php echo esc_attr($playlist_type); ?>">
    </form>
    
    <div class="search-items jws-scrollbar" data-current-id="<?php echo esc_attr($term_id); ?>">
        <div class="jws-videos-advanced-element">
            <div class="row videos-advanced-content layout4">
            </div>
        </div>
    </div>
       
</div>
</div>
