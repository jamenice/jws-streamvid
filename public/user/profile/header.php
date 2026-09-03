<?php $author_id = get_queried_object_id();
$is_owner = jws_streamvid_check_owner();
?>
<div class="profile-header">
    <div class="row row-eq-height header-top">
    <div class="user-profile">
        <div class="user-avatar">
            <?php  printf(
                '%s',
                get_avatar( $author_id, 96, null, null, array(
                    'class' =>  'img-thumbnail avatar'
                ) )
            );?>
        </div>
        <div class="user-info">
            <?php 
            
            printf(
                '<h5>%s</h5>',
                esc_html( get_the_author_meta( 'display_name' , $author_id) )
            );
            printf(
                '<p class="email">%s</p>',
                esc_html( get_the_author_meta('user_email', $author_id) )
            );
            if(is_user_logged_in() && function_exists('pmpro_getMembershipLevelForUser') && $is_owner) {
                $level = pmpro_getMembershipLevelForUser() ? pmpro_getMembershipLevelForUser()->name : '';
                printf(
                    '<p class="level-nemberships">%s</p>', 
                    esc_html( $level )
                ); 
            }
            ?>
        </div>
    </div>
    <?php 
    
    $update_page = jws_theme_get_option('select-update-level');
    if(jws_streamvid()->get()->profile->_profile_is_owner() && !empty($update_page)): ?>
    <div class="update-nember">
        <a href="<?php echo get_the_permalink($update_page); ?>" class="btn-main button-default">
            <?php echo esc_html__('Upgrade Premium','jws_streamvid'); ?>
            <img src="<?php echo JWS_STREAMVID_URL_PUBLIC_ASSETS . '/images/icon_premium.svg'; ?>" />
        </a>
    </div>
    <?php endif; ?>
    </div>
    <div class="row header-bottom">
    <div class="col-xl-8 col-lg-8 col-12 h-left">
         <?php if(jws_theme_get_option('profile_layout') != 'v2') { do_action("streamvid/profile/header/menu"); } ?>
    </div>
    <div class="col-xl-4 col-lg-4 col-12 h-right">
    <?php  
    if(jws_streamvid()->get()->profile->_profile_is_owner()) {
        if( isset( $GLOBALS['wp_query']->query_vars['playlist'] ) ){
            echo '<div class="playlist-button header-button"><button class="button-custom" data-modal-jws="#create-playlist">'.esc_html__('Create Playlist','jws_streamvid').'</button></div>';
        }
        elseif( isset( $GLOBALS['wp_query']->query_vars['watchlist']) || isset( $GLOBALS['wp_query']->query_vars['history'] )  ){
            if(jws_theme_get_option('profile_layout') != 'v2') {
            
            echo '<div class="watchlist-button header-button">';
            echo '<button class="button-custom watchlist-edit"><i class="jws-icon-pencil-line"></i>'.esc_html__('Edit','jws_streamvid').'</button>';
            echo '<button class="button-custom watchlist-cancel">'.esc_html__('Cancel','jws_streamvid').'</button>';
            echo '<button class="btn-main button-default watchlist-delete"><i class="jws-icon-trash"></i><span class="text">'.esc_html__('Delete','jws_streamvid').'</span></button>';
            echo '</div>';
                    
            }
        }
        elseif(isset( $GLOBALS['wp_query']->query_vars['video'] ) && jws_theme_get_option('video_upload')){
            echo '<div class="videos-button header-button">';    
            echo '<button class="button-custom" data-modal-jws="#upload-videos"><i class="jws-icon-upload"></i>'.esc_html__('Upload Video','jws_streamvid').'</button>';
            echo '</div>';
        }
    }    
    ?>
    </div>
  </div>
 
</div>