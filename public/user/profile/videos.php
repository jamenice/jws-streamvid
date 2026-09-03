<?php
if( ! defined('ABSPATH' ) ){
    exit;
}
$user_id = absint(get_queried_object_id());
$is_owner = jws_streamvid_check_owner();
$setting =  array(
    'image_size'    =>  jws_theme_get_option('videos_imagesize')
); 

if(jws_streamvid()->get()->profile->_profile_is_owner()) {
    $setting['edit'] = true;
}
 
  
 
$args = array(
    'post_type' => array('videos'),
    'author' => $user_id,
    'posts_per_page' => 100,
);

if(isset($_GET['sortby'])) {
    
    
    if($_GET['sortby'] == 'date') {
        $args['orderby'] = 'date';
    }
    if($_GET['sortby'] == 'title') {
        $args['orderby'] = 'title';
        $args['order'] = 'ASC';
    }
    if($_GET['sortby'] == 'likes') {
        $args['orderby'] = 'meta_value_num';
        $args['meta_key'] = 'likes';
    }
    if($_GET['sortby'] == 'views') {
        $args['orderby'] = 'meta_value_num';
        $args['meta_key'] = 'views';

    }
    
    
}

$querys = new WP_Query($args);    
    ?> 
    <div class="post_content jws-videos-advanced-element">
        <?php 
          
          if ( $querys->have_posts() ) :
          do_action('streamvid/videos/filter' , array('label'=>true));
          ?>
          <div class="row videos-advanced-content layout1 profile-grid jws-posts-grid"> 
                <?php
                while ( $querys->have_posts() ) {
                    $querys->the_post();

                    echo '<div class="jws-post-item col-xl-2 col-lg-3 col-md-6 col-12">';
                        get_template_part( 'template-parts/content/videos/layout/layout1' , '' , $setting );
                    echo '</div>';
                }
         ?>
        </div>
        <?php 
        
          else: 
          
          if($is_owner) {
             $upload_here = jws_theme_get_option('video_upload') ? __('Start upload your own video ','jws_streamvid').'<a href="#" data-modal-jws="#upload-videos">'.esc_html__('here.','jws_streamvid').'</a>' : '';
          }else {
            $upload_here = '';
          }
		 
		  printf(
            '%s %s',
            __('There isn\'t any video.','jws_streamvid'),
            $upload_here
          );
          wp_reset_postdata();       
          endif;          
        
        
        ?>
    </div>    
    <?php     
