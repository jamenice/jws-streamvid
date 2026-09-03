<?php
if( ! defined('ABSPATH' ) ){
    exit;
}

$user_id = absint(get_queried_object_id());
wp_enqueue_script('jws-youtube-api');

$valid_post_types = ['movies', 'tv_shows', 'videos'];
$current_filter = isset($_GET['favorites_filter']) ? sanitize_text_field($_GET['favorites_filter']) : 'movies';
?>
<div class="jws-movies_advanced-element profile-favorites favorites-page">
    <h5 class="profile-title"><?php echo esc_html__('My Favorites','jws_streamvid'); ?></h5>
    <div class="history-tabs jws-scrollbar-x">
        <?php foreach($valid_post_types as $type): ?>
            <a href="#" data-type="<?php echo esc_attr($type); ?>" class="favorites-tab<?php if($current_filter==$type) echo ' active'; ?>">
                <?php echo esc_html(ucwords(str_replace('_',' ', $type))); ?>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="row movies-advanced-content layout6 profile-grid post_content" id="favorites-list">
        <?php include __DIR__ . '/favorites-list.php'; ?>
    </div>
    <div class="load-more-wrapper">
        <button id="favorites-load-more" class="button-default" style="display:none;margin:20px auto 0;">
            <span class="btn-text"><?php echo esc_html__('Load More','jws_streamvid'); ?></span>
        </button>
    </div>
</div>

<script>
jQuery(document).ready(function($){
    var paged = 1;
    var currentType = '<?php echo esc_js($current_filter); ?>';

    function loadFavorites(type, pagedNum, append = false) {
        var $list = $('#favorites-list');
        if(!append) {
            $list.addClass('loading');
            $list.parent().addClass('jws-animated-post');
        }
        
        if(!$list.find('.loader').length) {
            $list.append('<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>');
        }
        
        $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
            action: 'jws_load_favorites',
            favorites_filter: type,
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
                $('#favorites-load-more').removeClass('hidden');
            } else {
                $('#favorites-load-more').addClass('hidden');
            }
        }, 'json');
    }

    $('.favorites-tab').on('click', function(e){
        e.preventDefault();
        var type = $(this).data('type');
        $('.favorites-tab').removeClass('active');
        $(this).addClass('active');
        paged = 1;
        currentType = type;
        loadFavorites(type, paged, false);
    });

    $(function(){
        var $list = $('#favorites-list');
        var $items = $list.find('.jws-post-item');
        if($items.length >= 12){
            $('#favorites-load-more').removeClass('hidden');
        } else {
            $('#favorites-load-more').addClass('hidden');
        }
    });

    $('#favorites-load-more').on('click', function(e){
        e.preventDefault();
        paged++;
        loadFavorites(currentType, paged, true);
    });
});
</script>