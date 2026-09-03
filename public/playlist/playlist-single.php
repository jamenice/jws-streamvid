<?php 

wp_enqueue_script( 'stick-content', JWS_URI_PATH. '/assets/js/sticky_content.js', array(), '', true );

$args = wp_parse_args( $_GET, array(
    'playlist'          =>  '',
    'playlist_type'          =>  '',
    'post_type' => 'videos'
) );

extract( $args );

$id = (int) $playlist;

$term = get_term( $id, $playlist_type );

if(!empty($playlist_type)) {
  $post_type = jws_streamvid()->get()->playlist->playlist_post_type();
  $post_type = $post_type[$playlist_type];  
}


 
if(isset($term)) :

$status = get_term_meta( $id , 'status', true);
$thumbnail = get_term_meta( $id, 'playlist_image', true);

$count = $term->count;
$query = jws_streamvid()->get()->playlist->playlist_query($id,$playlist_type,$post_type); 
$playlist_all = jws_streamvid()->get()->playlist->playlist_url_all($id,$playlist_type,$post_type); 

?>

<div class="row playlist-single">

    <div class="col-xl-3 col-lg-3">
        <div class="jws_sticky_move">    
            <div class="playlist-background ratio_16x9">
                <a href="<?php echo esc_url($playlist_all); ?>">      
                    <?php 
                        $image = jws_image_advanced(array('attach_id' => $thumbnail, 'thumb_size' => 'full'));
                        echo !empty($image) ? $image : '';
                   
                      printf( '<div class="content-hover"><div><i class="jws-icon-play-circle"></i>%s</div></div>',
                      esc_html__('Play All','jws_streamvid')); 
                   ?> 
               </a>  
            </div>
        </div>        
    </div>
    <div class="col-xl-9 col-lg-9">
        <div class="playlist-header">
            <h5><?php echo esc_html( $term->name ); ?></h5>
            <?php if(jws_streamvid()->get()->profile->_profile_is_owner()) : ?>
            <div class="playlist-control jws-dropdown-ui">
                <button class="dr-button"><i class="jws-icon-dots-three-outline-vertical"></i></button>
                <ul class="dropdown-menu fw-700">
                    <li><a href="#" modal="#add-item-playlist" data-term-id="<?php echo esc_attr($id); ?>"><i class="jws-icon-plus"></i><?php echo esc_html__('Add Videos','jws_streamvid'); ?></a></li>
                    <li><a href="#" data-term-id="<?php echo esc_attr($id); ?>" data-modal-jws="#edit-playlist"><i class="jws-icon-pencil-line" ></i><?php echo esc_html__('Edit','jws_streamvid'); ?></a></li>
                    <li><a href="#" data-modal-jws="#delete-playlist"><i class="jws-icon-trash"></i><?php echo esc_html__('Delete','jws_streamvid'); ?></a></li>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        <div class="playlist-meta">
            <?php 
                $icon_class = $status == 'private' ? 'jws-icon-lock-key-fill' : 'jws-icon-globe-hemisphere-west-fill';
                echo '<span><i class="'.esc_attr($icon_class).'"></i>'.esc_html($status).'</span>';
                printf( _n( '<span>%s video</span>', '<span>%s videos</span>', $count , 'jws_streamvid' ), number_format_i18n( $count ) );
            ?>
        </div>
      
        
        <?php 
        
   
            $posts = $query;

          
                ?>
                <div class="jws-videos-advanced-element">
                  <div class="row videos-advanced-content layout4">
                    <?php
                    if( $posts ){
        				foreach ( $posts as $post ){
        
        					setup_postdata( $post );
                            ?>
                            <div class="jws-videos-advanced-item col-xl-12 col-lg-12 col-12">
                                <div class="playlist-item">   
                                    <?php 
                                    
                                        get_template_part( 'template-parts/content/videos/layout/layout4' , '' , array('image_size'=>'150x90','playlist'=> $term->term_id,'post_type'=> $post_type,'playlist_type' => $playlist_type) );
                                        
                                        if( jws_streamvid()->get()->profile->_profile_is_owner() ){
        							       
                                             Jws_Streamvid_Playlist::the_playlist_control(array('post_id' => get_the_ID(),'term_id' => $term->term_id , 'post_type' => $post_type , 'playlist_type' => $playlist_type));
        							    }
                                        
                                     ?>
                                </div>                   
                            </div>
                            <?php
                        }
                        
                        wp_reset_postdata();
                        
                        }else {
                            printf(
            					'<p class="not-found">%s</p>',
            					esc_html__( 'The playlist has no videos.', 'jws_streamvid' )
            				);
                        } 
                    ?>
                   
                  </div>
        
                  
                 
                </div>
                <?php 
                        
                     if(jws_streamvid()->get()->profile->_profile_is_owner())   
                     printf(
        					'<button class="add-to-playlist add-border" data-term-id="%s" modal="#add-item-playlist"><i class="jws-icon-plus"></i>%s</button>',
        				    esc_attr( $term->term_id ),
                        	esc_html__( 'Add videos', 'jws_streamvid' ) 
        				);
                    
                ?>
                <?php
             
                   
        
        
        ?>
        
        
        </div>
    </div>

<?php endif; ?>