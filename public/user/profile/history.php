<?php
if( ! defined('ABSPATH' ) ){
    exit;
}

$user_id = get_current_user_id();
$video_progress_data = Jws_History::get_all($user_id);
$valid_post_types = ['movies', 'tv_shows', 'episodes', 'videos', 'drama'];


$current_filter = isset($_GET['history_filter']) ? sanitize_text_field($_GET['history_filter']) : 'movies';
?>
<div class="profile-history">
    <h5 class="profile-title"><?php echo esc_html__('My History','jws_streamvid'); ?></h5>
   <div class="history-tabs jws-scrollbar-x">
    <?php foreach($valid_post_types as $type): ?>
        <a href="#" data-type="<?php echo esc_attr($type); ?>" class="history-tab<?php if($current_filter==$type) echo ' active'; ?>">
            <?php echo esc_html( jws_profile_tab_label( $type ) ); ?>
        </a>
    <?php endforeach; ?>
</div>
    <button class="select-all"><?php echo esc_html__('Select all','jws_streamvid'); ?></button>
    <div class="row post_content profile-grid" id="history-list">
        <?php include __DIR__ . '/history-list.php'; ?>
    </div>
    <div class="load-more-wrapper">
        <button id="history-load-more" class="button-default"><span><?php echo esc_html__('Load More','jws_streamvid'); ?></span></button>
    </div>
</div>



<script>
jQuery(document).ready(function($){
    var paged = 1;
    var currentType = '<?php echo esc_js($current_filter); ?>';

    function loadHistory(type, pagedNum, append = false) {
        var $list = $('#history-list');
        
         if(!append) {
             $list.addClass('loading');
             $list.parent().addClass('jws-animated-post');
        }
        
        if(!$list.find('.loader').length) {   
            $list.append('<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>');
        }
        $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
            action: 'jws_load_history',
            history_filter: type,
            paged: pagedNum
        }, function(res){
            if(append) {
                $list.append(res.html);
            } else {
                $list.html(res.html);
            }
            var iter = 0;
            var $items = $list.find('.jws-post-item:not(.jws-animated)');
            $items.removeClass('jws-animated');
            var intervalID = setInterval(function () {
                $items.eq(iter).addClass('jws-animated');
                iter++;
                if(iter >= $items.length) clearInterval(intervalID);
            }, 10);
            $list.removeClass('loading');
       
            if(res.has_more){
                $('#history-load-more').removeClass('hidden');
            } else {
                $('#history-load-more').addClass('hidden');
            }
            $('#history-load-more').removeClass('loading');
        }, 'json');
    }

    $('.history-tab').on('click', function(e){
        e.preventDefault();
        var type = $(this).data('type');
        $('.history-tab').removeClass('active');
        $(this).addClass('active');
        paged = 1;
        currentType = type;
        loadHistory(type, paged, false);
    });

    $('#history-load-more').on('click', function(){
        $(this).addClass('loading jws-animated-products');
        if(!$(this).find('.loader').length) {   
            $(this).append('<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>');
        }
        paged++;
        loadHistory(currentType, paged, true);
    });

    $(function(){
        var $list = $('#history-list');
        var $items = $list.find('.jws-post-item');
        if($items.length >= 12){
             $('#history-load-more').removeClass('hidden');
        } else {
            $('#history-load-more').addClass('hidden');
        }
    });


});
</script>