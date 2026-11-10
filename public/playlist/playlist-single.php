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

$count = $term->count;
$query = jws_streamvid()->get()->playlist->playlist_query($id,$playlist_type,$post_type); 
$playlist_all = jws_streamvid()->get()->playlist->playlist_url_all($id,$playlist_type,$post_type); 

?>

<div class="row playlist-single">

    <div class="col-xl-3 col-lg-3">
        <div class="jws_sticky_move">    
            <div class="playlist-background ratio_16x9">
                <?php echo jws_streamvid()->get()->playlist->cover_html( $id, $playlist_type, $post_type ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in cover_html(). ?>
            </div>
        </div>        
    </div>
    <div class="col-xl-9 col-lg-9">
        <div class="playlist-header">
            <?php $back_url = add_query_arg( 'ptype', sanitize_key( $playlist_type ), Jws_Streamvid_Profile::get_url( 'playlist' ) ); ?>
            <a class="playlist-back" href="<?php echo esc_url( $back_url ); ?>">
                <i class="jws-icon-arrow-left" aria-hidden="true"></i>
                <span class="screen-reader-text"><?php echo esc_html__( 'Back to playlists', 'jws_streamvid' ); ?></span>
            </a>
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
                echo jws_streamvid()->get()->playlist->count_html( $count ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- number only.
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

<?php
// The playlist's own popups come with the screen, so a profile tab that opens it in place (profiles.js) has them.
if ( is_user_logged_in() ) {
    Jws_Streamvid_Playlist::$single_modals = true;
    jws_streamvid_load_template( 'playlist/edit-playlist.php', false );
    jws_streamvid_load_template( 'playlist/delete-playlist.php', false, array( 'term_id' => $id ) );
    jws_streamvid_load_template( 'playlist/other-playlist.php', false, array( 'term_id' => $id ) );
    jws_streamvid_load_template( 'playlist/search-item.php', false, array( 'term_id' => $id ) );
}
?>

<?php endif; ?>