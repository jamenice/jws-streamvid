<?php

if( ! defined('ABSPATH' ) ){
    exit;
}

$args = wp_parse_args( $args, array(
    'taxonomy'   => 'videos_playlist',   
    'post_type' => 'videos'  
) );

extract( $args );

$terms = get_terms( array(
    'taxonomy' => $taxonomy,
    'hide_empty' => false,
    'meta_query' => array(
        [
            'key' => 'user',
            'value' => get_queried_object_id()
        ]
    ),
));


$url_playlist = Jws_Streamvid_Profile::get_url('playlist');
$heading = jws_streamvid()->get()->playlist->playlist_title_section();
$i = 0;
?>
<div class="profile-playlist">
   <h5><?php echo esc_html( $heading[$taxonomy] ); ?></h5>
   <?php if(!empty($terms) && !is_wp_error( $terms ) ) : ?>
      
      <div class="row profile-grid">
      
      <?php
        
        foreach($terms as $term) {
            $status = get_term_meta( $term->term_id, 'status', true);
            $thumbnail = get_term_meta( $term->term_id, 'playlist_image', true);
            $type2 = get_term_meta( $term->term_id, 'type', true);
            $url =  add_query_arg( 
             array(  
               'playlist' => $term->term_id,
               'playlist_type' => $taxonomy 
             ),
             $url_playlist 
            );
            
            if(!empty($taxonomy)) {
              $post_type = jws_streamvid()->get()->playlist->playlist_post_type();
              $post_type = $post_type[$taxonomy];  
            }
            
            
            $count = $term->count;
            
            $is_owner = jws_streamvid()->get()->playlist->_is_owner($term->term_id); 
            
          
            $playlist_all = jws_streamvid()->get()->playlist->playlist_url_all($term->term_id,$taxonomy,$post_type);
            $view_playlist = esc_html__('Play All','jws_streamvid');  
            
            
            if(!$is_owner && $status == 'private') continue;
            ?>
            <div class="jws-post-item col-xl-2 col-lg-4 col-md-6 col-12">
                <div class="playlist-inner">
                    <div class="playlist-thumbnail">
                        <a class="ratio_16x9" href="<?php echo esc_url($playlist_all); ?>">
                        <?php 
                            $image = jws_image_advanced(array('attach_id' => $thumbnail, 'thumb_size' => 'full'));
                            echo !empty($image) ? $image : '';
                        ?>
                    
                            <?php
                            
                                printf( ' <div class="content-display"><div><h6>%s</h6><i class="jws-icon-queue"></i></div></div>',
                                number_format_i18n( $count ) );
                        
                                printf( '<div class="content-hover"><div class="fs-small"><i class="jws-icon-play-circle"></i>%s</div></div>',
                                $view_playlist);
                                
                         ?>
                         
                       </a> 
                    </div>
                    <div class="playlist-content">
                        <?php 
                            
                            
                            $text = jws_streamvid()->get()->playlist->get_statuses(); 
                        
                        
                            $icon_class = $status == 'private' ? 'jws-icon-lock-key-fill' : 'jws-icon-globe-hemisphere-west-fill';
                      
                            echo "<h6><a href='" . esc_url($url) . "'>" . esc_html($term->name) . "</a></h6>";
                            
                            ?>
                            
                                <div class="playlist-meta"> 
                                
                                    <?php 
                                    
                                    
                                        echo '<span><i class="'.esc_attr($icon_class).'"></i>'.esc_html($text[$status]).'</span>';
                                        printf( _n( '<span>%s video</span>', '<span>%s videos</span>', $count , 'jws_streamvid' ), number_format_i18n( $count ) );
                                          echo '<span><a href="'.esc_url($url).'"><i class="jws-icon-pencil-line"></i>'.esc_html__('Edit','jws_streamvid').'</a></span>';
                                    
                                    ?>
                                
                                </div>

                    </div>
                    
                </div>
            </div>
        <?php $i++; }
      
      ?>
      
      </div> 
      
      <?php endif; if($i == 0) echo '<div class="playlist-inner">'.esc_html_e('No playlists.','jws_streamvid').'</div>'; ?>
  
    </div>
<?php  
