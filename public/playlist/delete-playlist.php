<?php
if( ! defined( 'ABSPATH' ) ){
    exit;
}
$args = wp_parse_args( $args, array(
    'term_id' => 0,
    'playlist_type' => 'videos_playlist'
) );


extract( $args );

if(isset($_GET['playlist_type'])) {
  $playlist_type = sanitize_key( $_GET['playlist_type'] );  
}

$url_playlist = Jws_Streamvid_Profile::get_url('playlist');
?>  

<div id="delete-playlist" class="mfp-hide">
    <div class="form-head">
            <h5 class="title">
                <i class="jws-icon-warning"></i>
                <?php 
                  
                  echo esc_html__('Confirm Delete','jws_streamvid'); 
                    
                ?>
            </h5> 
            
            <?php 
                printf(
                    '<p>%s</p>',
                    esc_html__('Are you sure you want to delete this playlist?','jws_streamvid')
                );   
             
            ?>
          
            <?php 
                printf(
                    '<p>'.esc_html__('After you press %s, this playlist will be deleted permanently.','jws_streamvid').'</p>',
                    '<strong>'.esc_html__('DELETE','jws_streamvid').'</strong>'
                );   
             
            ?>
         
    </div>
    <div class="form-body">
        <form class="form form-delete-playlist">       
            <input type="hidden" name="term_id" value="<?php echo esc_attr( $term_id ); ?>">
            <input type="hidden" name="redirect_url" value="<?php echo esc_url( $url_playlist ); ?>">
            <input type="hidden" name="playlist_type" value="<?php echo esc_attr( $playlist_type ); ?>">
            <input type="hidden" name="action" value="delete_playlist">
             <div class="form-button">
                <a class="cancel-modal button-custom" href="#"><?php echo esc_html__('Cancel','jws_streamvid'); ?></a>
                <button class="save-modal btn-main button-default" type="submit"><?php echo esc_html__('Delete','jws_streamvid'); ?></button>
            </div>
        </form>
    </div>
 </div>    