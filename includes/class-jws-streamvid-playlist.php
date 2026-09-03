<?php
/**
 * Fired during plugin activation
 *
 * @link       https://jwsuperthemes.com
 * @since      1.0.0
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code for playlist
 *
 * @since      1.0.0
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 * @author     Jws Theme <jwstheme@gmail.com>
 */
class Jws_Streamvid_Playlist {
    
    public function playlist_query($id,$playlist_type,$post_type){ 
        
     $post_ids = get_term_meta($id, 'playlist_order', true);  
     $args =  array(
            'post_type'         =>  $post_type,
            'post_status'       =>  array( 'publish' ),
            'posts_per_page'    =>  -1,
            'post__in'  => $post_ids, 
            'orderby'   => 'post__in', 
            'order'             =>  'ASC',
            'tax_query'         =>  array( 
                array(
                    'taxonomy'  =>  $playlist_type,
                    'field'     =>  'term_id',
                    'terms'     =>  $id
                )
            )
      );
      
     $posts = get_posts( $args );
     return $posts;
        
    }

    public function playlist_url_single($post_id, $playlist, $playlist_type, $post_type) { 
        $query = $this->playlist_query($playlist, $playlist_type, $post_type);
        $taxonomy_url = get_term_link($playlist, $playlist_type);
    
        if (is_wp_error($taxonomy_url)) {
            return '';
        }
    
        $url = add_query_arg(
            array(
                'video_id' => $post_id
            ),
            $taxonomy_url
        );
        return $url;
    }

    public function playlist_url_all($id, $playlist_type, $post_type) { 
        $query = $this->playlist_query($id, $playlist_type, $post_type);
        $first_post_id = isset($query[0]->ID) ? $query[0]->ID : '';
        $taxonomy_url = get_term_link($id, $playlist_type);
    
        if (is_wp_error($taxonomy_url)) {
            return '';
        }
    
        $url = add_query_arg(
            array(
                'video_id' => $first_post_id
            ),
            $taxonomy_url
        );
        return $url;
    }
    
    
    public function playlist_title_section() {  
        
        $array = array(
         
         'videos_playlist' => esc_html__('Videos Playlists' , 'jws_streamvid'),
         'movies_playlist' => esc_html__('Movies Playlists' , 'jws_streamvid'),
         'episodes_playlist' => esc_html__('Tv Shows Playlists' , 'jws_streamvid')
         
        ); 
        
        
        return apply_filters( 'streamvid/playlist/title_section',  $array );
     
    }
    
    
    public function delete_playlist(){ 
   
        
        $args = wp_parse_args( $_POST, array(
            'redirect_url'  =>  '',
            'playlist_type' => ''
        ) );

        extract( $args );
    
         $errors = new WP_Error();   
         if( ! $this->_is_owner( $term_id ) ){
            $errors->add(
                'no_permission',
                esc_html__( 'You do not have permission to add post to this collection', 'jws_streamvid' )
            );
        }
        
        if( $errors->get_error_code() ){
             wp_send_json_error( $errors );
        } 
        
        $message = esc_html__( 'Playlist deleted successfully', 'jws_streamvid' );

        $term = wp_delete_term( $term_id,$playlist_type);
        
        wp_send_json_success( compact('term' , 'redirect_url' ,  'message' ) );
        
    }
    
    public function create_edit_playlist(){
        $errors = new WP_Error();
        
        $args = wp_parse_args( $_POST, array(
            'post_id'   =>  0,
            'term_id'   =>  0,
            'user_id'   =>  get_current_user_id()     
        ) );

      
       if( empty($args['name'] ) ){
            $errors->add(
                'empty_name',
                esc_html__( 'Name is required', 'jws_streamvid' )
            );            
        }
        
     
        if( $errors->get_error_code() ){
            wp_send_json_error( $errors); 
        }
        
        
        if( $args['term_id'] ){  
          $term = $this->edit_term( $args , $errors );    
        }else {
          $term = $this->add_term( $args , $errors );  
        }
        
  
    
        if( is_wp_error( $term ) ){
            wp_send_json_error( $term );
        }
        
        $message = esc_html__( 'Playlist has been created successfully', 'jws_streamvid' );
        
        wp_send_json_success( compact( 'term', 'message') );
      
	}
    
    
     private function edit_term( $args , $errors ){
      
        $args = wp_parse_args( $args, array(
            'user'          =>  wp_get_current_user(),
            'name'          =>  '',
            'status'        =>  '',
            'parent'        =>  0,
            'description'   =>  '',
            'type'          =>  'playlist',
            'playlist_type'    => ''
        ) );

        extract( $args );
        
        $user = get_userdata( $user_id );
        
        if( ! $user ){
            $errors->add(
                'user_id_not_found',
                esc_html__( 'User ID was not found', 'jws_streamvid' )
            );
        }
        
        if( ! $this->_is_owner( $term_id ) ){
            $errors->add(
                'no_permission',
                esc_html__( 'You do not have permission to add post to this collection', 'jws_streamvid' )
            );
        }
        
        if( $errors->get_error_code() ){
            return $errors;
        } 
  
        $term = wp_update_term( $term_id, $playlist_type , compact( 'name', 'description' ) );
  
        if( ! is_wp_error( $term )  ){

            if( $type ){
                update_term_meta( $term_id, 'type', $type );
            }

            update_term_meta( $term_id, 'status', $status );
            
            if( isset( $_FILES[ 'playlist_image' ]['error'] ) && $_FILES[ 'playlist_image' ]['error'] == 0 ){ 
                 $file = $_FILES[ 'playlist_image' ];
                 
                  if( $file['error'] != 0 ){
                    $errors->add(
                        'file_broken',
                        esc_html__( 'File was not found or broken', 'jws_streamvid' )
                    );            
                }
                 
                 $type = array_key_exists( 'type' , $file ) ? $file['type'] : '';
                 if ( 0 !== strpos( $type, 'image/' ) ) {
                    $errors->add( 
                        'file_not_accepted', 
                        esc_html__( 'File format is not accepted.', 'jws_streamvid' )
                    );
                }
                $max_size = jws_get_max_upload_image_size();
                    
                if( $file['size'] > $max_size ){
                    $errors->add( 
                        'file_size_not_allowed',
                        sprintf(
                            esc_html__( 'File size has to be smaller than %s', 'jws_streamvid' ),
                            size_format( $max_size )
                        )
                    );                    
                }
              
                if( $errors->get_error_code() ){
                    return $errors;
                } 
                      
                $attachment_id = media_handle_upload( 'playlist_image', 0, array( '' ), array('test_form' => FALSE) );
          
                if( ! is_wp_error( $attachment_id ) ){
                    update_term_meta( $term_id, 'playlist_image', $attachment_id );
                }
        
                return $attachment_id; 
            }
 

        }
      
        return $term;
    }
    
    
      private function add_term( $args , $errors ){
     
        $args = wp_parse_args( $args, array(
            'user'          =>  wp_get_current_user(),
            'name'          =>  '',
            'status'        =>  '',
            'parent'        =>  0,
            'description'   =>  '',
            'type'          =>  'playlist',
            'playlist_type'    => ''
        ) );

        extract( $args );
  

        $term = wp_insert_term( $name, $playlist_type , compact( 'description', 'parent' ) );
 
        if( is_wp_error( $term ) ){
            return $term;
        }
   
        if( is_array( $term ) ){
            update_term_meta( $term['term_id'], 'user', $user->ID );

            if( $type ){
                update_term_meta( $term['term_id'], 'type', $type );
            }

            update_term_meta( $term['term_id'], 'status', $status );
            
            if( isset( $_FILES[ 'playlist_image' ]['error'] ) && $_FILES[ 'playlist_image' ]['error'] == 0 ){ 
                 $file = $_FILES[ 'playlist_image' ];
                 
                  if( $file['error'] != 0 ){
                    $errors->add(
                        'file_broken',
                        esc_html__( 'File was not found or broken', 'jws_streamvid' )
                    );            
                }
                 
                 $type = array_key_exists( 'type' , $file ) ? $file['type'] : '';
                 if ( 0 !== strpos( $type, 'image/' ) ) {
                    $errors->add( 
                        'file_not_accepted', 
                        esc_html__( 'File format is not accepted.', 'jws_streamvid' )
                    );
                }
                $max_size = jws_get_max_upload_image_size();
                    
                if( $file['size'] > $max_size ){
                    $errors->add( 
                        'file_size_not_allowed',
                        sprintf(
                            esc_html__( 'File size has to be smaller than %s', 'jws_streamvid' ),
                            size_format( $max_size )
                        )
                    );                    
                }
              
                if( $errors->get_error_code() ){
                    return $errors;
                } 
                      
                $attachment_id = media_handle_upload( 'playlist_image', 0, array( '' ), array('test_form' => FALSE) );
          
                if( ! is_wp_error( $attachment_id ) ){
                    update_term_meta( $term['term_id'], 'playlist_image', $attachment_id );
                }
        
                return $attachment_id; 
            }
 

        }
      
        return $term;
    }
    
    
    public function _is_owner( $term_id = 0, $user_id = 0 ){

        $user_id = (int)$user_id;

        if( ! $user_id ){
            $user_id = get_current_user_id();
        }

        if( ! $user_id ){
            return false;
        }

        $term_user_id = $this->_get_term_user( $term_id );

        if( $term_user_id && $term_user_id == $user_id ){
            return true;
        }

        return false;
    } 
    
    public function _get_term_user( $term_id = 0 ){
        return (int)get_term_meta( $term_id, 'user', true );
    }
    
    public function search_item_playlist(){
        
        $args = wp_parse_args( $_POST , array(
            'search'    =>  '',
            'term_id'   =>  0,
            'post_type' => 'videos',
            'playlist_type' => 'videos_playlist'
        ) );

        extract( $args );
        
        $query_args = array(
            'post_type'         =>  $post_type,
            'post_status'       =>  'publish',
            'posts_per_page'    =>  50,
            'orderby'           =>  'date',
            'order'             =>  'DESC',
            's'                 =>  $search,
            'tax_query'         =>  array(),
        );
   
        $posts =  get_posts( apply_filters( 'streamvid/add_to_playlist', $query_args, $args ) );

        if( empty( $search ) ){
            wp_send_json_error( new WP_Error(
                'keywords_not_found',
                esc_html__( 'Keywords were not found', 'jws_streamvid' )
            ) );
        }

        if( empty( $term_id ) ){
            wp_send_json_error( new WP_Error(
                'term_not_found',
                esc_html__( 'Playlist was not found', 'jws_streamvid' )
            ) );
        }
        $image_size_global = jws_theme_get_option('videos_imagesize'); 
        
        if( $posts ){

            ob_start();
                for ( $i = 0; $i < count( $posts ) ; $i++ ) { 
       
                    global $post;
                    $post = $posts[$i];
                    setup_postdata( $post );
                    
                    
                    $post_id = get_the_ID();
                    $has_term = has_term( $term_id,$playlist_type, $post_id );
                    echo '<div class="jws-videos-advanced-item col-xl-12 col-lg-12 col-12">';
                    
                        get_template_part( 'template-parts/content/videos/layout/layout4' , '' , array('image_size'=>$image_size_global) );
                     
                         printf(
        					'<button class="set-item-playlist %s" data-term-id="%s" data-post-id="%s" data-post-type="%s" data-playlist-type="%s"></button>',
                            $has_term ? 'checked' : '',
        				    $term_id,
                            $post_id,
                            $post_type,
                            $playlist_type
        				);
                        
                        
                    
                    echo '</div>';
    
                    wp_reset_postdata(); 
                }
              

            $results = ob_get_clean();
          
        }else{
            $results = sprintf(
                '<p class="not-found col-12">%s</p>',
                esc_html__( 'Nothing matched your search terms', 'jws_streamvid' )
            );
        }

       wp_send_json_success( $results );
        
        
    }
    
    
    public function set_item_playlist(){ 
        
        $args = wp_parse_args( $_POST, array(
            'post_id'   =>  0,
            'term_id'   =>  0,
            'post_type' => 'videos',
            'playlist_type' => 'videos_playlist'
        ) );
        
        extract( $args );

        $post_id = (int)$post_id;
        $term_id = (int)$term_id;
  
        $errors = new WP_Error();

        if( ! $this->_is_owner( $term_id ) ){
            $errors->add(
                'no_permission',
                esc_html__( 'You do not have permission to add post to this collection', 'jws_streamvid' )
            );
        }
        
        $errors = apply_filters( 'streamvid/playlist/set_post', $errors , $args );
        
        
     
        $meta_key = 'playlist_order';
        
        $playlist_order = get_term_meta($term_id, $meta_key, true);
        
        $playlist_order = !empty($playlist_order) ? $playlist_order : array();

        if( has_term( $term_id,$playlist_type, $post_id ) ){
            $results = $this->remove_post( $post_id, $term_id , $playlist_type );
            
            if (isset($playlist_order['pl_'.$post_id])) {
           
                unset($playlist_order['pl_'.$post_id]);
                update_term_meta($term_id, $meta_key, $playlist_order); 
            }

        }else{
            $results = $this->add_post( $post_id, $term_id , $playlist_type );
             $playlist_order['pl_'.$post_id] = $post_id;
             update_term_meta($term_id, $meta_key, $playlist_order); 
        }
        
        if( is_wp_error( $results ) ){
            wp_send_json_error( $results );
        }
        
        $term = get_term( $term_id, $playlist_type);
        
        $post_ids = get_term_meta($term_id, 'playlist_order', true);  
        
        $args = array(
            'post_type'         =>  $post_type,
            'post_status'       =>  array( 'publish' ),
            'posts_per_page'    =>  -1,
            'post__in'  => $post_ids, 
            'orderby'   => 'post__in', 
            'order'             =>  'ASC',
            'tax_query'         =>  array(
                array(
                    'taxonomy'  =>  $term->taxonomy,
                    'field'     =>  'term_id',
                    'terms'     =>  $term_id
                )
            )
        );
        $image_size_global = jws_theme_get_option('videos_imagesize');  
        $posts = get_posts( $args );
        ob_start();
        for ( $i = 0; $i < count( $posts ) ; $i++ ) { 
   
                global $post;
                $post = $posts[$i];
                setup_postdata( $post );
        
               ?>
                <div class="jws-videos-advanced-item col-xl-12 col-lg-12 col-12">
                <div class="playlist-item">      
                <?php get_template_part( 'template-parts/content/videos/layout/layout4' , '' , array('image_size'=>'150x90','playlist'=> $term_id,'post_type'=> $post_type,'playlist_type' => $playlist_type) ); 
                if( is_user_logged_in() ){
        	       
                     $this->the_playlist_control(array('post_id' => get_the_ID(),'term_id' => $term_id , 'post_type' => $post_type , 'playlist_type' => $playlist_type ));
        	    }
                ?>
                </div>                  
                </div>
                <?php
                     
        }
        wp_reset_postdata();
        
        $output = ob_get_clean();
        
        if(has_term( $term_id,$playlist_type, $post_id )){
            $message = sprintf(
                esc_html__( 'Saved to %s', 'jws_streamvid' ),
                $term->name
            );
            $type = 'save';
        }else{
            $message = sprintf(
                esc_html__( 'Removed from %s', 'jws_streamvid' ),
                $term->name
            ); 
            $type = 'remove';           
        }

        wp_send_json_success( compact( 'output', 'message' , 'type' ) );
     
    }
    
    public function add_post( $post_id = 0, $term_id = 0 , $playlist_type = '' ){
        return wp_set_post_terms( $post_id, $term_id, $playlist_type, true );
    }

    public function remove_post( $post_id = 0, $term_id = 0 , $playlist_type = '' ){
        return wp_remove_object_terms( $post_id, $term_id, $playlist_type );
    }
    
    public function get_statuses(){
        return array(
            'public'        =>  esc_html__( 'Public', 'jws_streamvid' ),
            'private'       =>  esc_html__( 'Private', 'jws_streamvid' )
        );
    }
    
    public function playlist_post_type() {  
        
        $array = array(
         
         'videos_playlist' => 'videos',
         'movies_playlist' => 'movies',
         'episodes_playlist' => 'episodes'
         
        );
        
        
        return $array;
        
    }
    
    public function playlist_type() {  
        
        $array = array(
         
         'videos_playlist' => esc_html__('Videos' , 'jws_streamvid'),
         'movies_playlist' => esc_html__('Movies' , 'jws_streamvid'),
         'episodes_playlist' => esc_html__('Tv Shows' , 'jws_streamvid')
         
        );
        
        
        return apply_filters( 'streamvid/playlist/playlist_type',  $array );
     
    }
    
    public function form_create_edit($args){
        
       $args = wp_parse_args( $args, array(
            'type'   =>  'create',
            'term_id' => 0,
            'playlist_type' => isset($_GET['playlist_type']) ? $_GET['playlist_type'] : ''
       ) );
       
       extract( $args );
       
       $thumbnail = get_term_meta( $term_id , 'playlist_image', true); 
       $status = get_term_meta( $term_id , 'status', true);
       $term = get_term( $term_id, $playlist_type ); 
       $max_size = jws_get_max_upload_image_size();
       
       ?>
        
        <div id="<?php echo $type; ?>-playlist" class="mfp-hide">

        <div class="form-head">
        
            <h5 class="title">
                <?php 
                    if($type == 'create') {
                        echo esc_html__('Create new Playlist','jws_streamvid');
                    } else {
                        echo esc_html__('Edit Playlist','jws_streamvid'); 
                    }  
                ?>
            </h5>
            
            <?php if($type == 'create') : ?>
            
            <p><?php echo esc_html__('Please fill in all information bellow to create new playlist.','jws_streamvid'); ?></p>
            <?php endif; ?>
            
        </div>
        
        <div class="form-body">
        
            <form class="form form-set-playlist">
            <?php if($type == 'create') { ?>
            <p class="field-item">
            
                <label for="playlist_type"><?php echo esc_html__('Type Playlist','jws_streamvid'); ?></label>
                <select name="playlist_type" id="playlist_type">
                  
                     <?php foreach ( $this->playlist_type() as $key => $value ) {
                    
                        printf(
                            '<option value="%s">%s</option>',
                            esc_attr( $key ),
                            esc_html( $value )
                        );
    
                    }?>
                  
                </select>
            
            </p>
            <?php } else {
                
                if(isset($_GET['playlist_type'])) {
                    
                    ?> <input type="hidden" name="playlist_type" value="<?php echo esc_attr($_GET['playlist_type']); ?>"> <?php
                    
                }
                
            } ?>  
            <p class="field-item">
            
                <label for="playlist-name"><?php echo esc_html__('Name','jws_streamvid'); ?> *</label>
                <input type="text" id="playlist-name" name="name"  value="<?php echo isset($term->name) ? esc_attr( $term->name ) : ''; ?>"/>
            
            </p>
            
            <p class="field-item">
            
                <label><?php echo esc_html__('Description','jws_streamvid'); ?></label>
                <textarea name="description"><?php echo isset($term->description) ? esc_textarea( $term->description ) : ''; ?></textarea>
            
            </p>
            
            <p class="field-item">
            
                <label for="status"><?php echo esc_html__('Privacy','jws_streamvid'); ?></label>
                <select name="status" id="status">
                  
                     <?php foreach ( $this->get_statuses() as $key => $value ) {
                    
                        printf(
                            '<option value="%s" %s>%s</option>',
                            esc_attr( $key ),
                            $status == $key ? 'selected="selected"' : '',
                            esc_html( $value )
                        );
    
                    }?>
                  
                </select>
            
            </p>
            
            <div class="field-item">
                <label class="has-des" for="playlist_image"><?php echo esc_html__('Playlist Thumbnail','jws_streamvid'); ?></label>
                  <?php
                    printf(
                        '<div class="note-max-size">'.esc_html__( 'Support *.png, *.jpeg, *.gif, *.jpg. Maximun upload file size: ' , 'jws_streamvid' ).'%smb.</div>',
                        number_format_i18n( ceil( $max_size/1048576 ) )
                    );
                 ?>
                <div class="file-thumbnail">
                     <?php 
                       if($thumbnail) {
                            $image = jws_image_advanced(array('attach_id' => $thumbnail, 'thumb_size' => 'full'));
                            echo !empty($image) ? $image : '';
                       }  
                    ?>
                </div>
                <input id="playlist_image" type="file" name="playlist_image" accept=".jpg,.jpeg,.png,.gif,.bmp,.tiff">
            </div>
                                   
            <input type="hidden" name="term_id" value="<?php echo esc_attr( $term_id ); ?>">
            <input type="hidden" name="post_id" value="0">
            <input type="hidden" name="action" value="create_playlist"> 
             <div class="form-button">
                <a class="cancel-modal button-custom" href="#"><?php echo esc_html__('Cancel','jws_streamvid'); ?></a>
                
                <button class="save-modal btn-main button-default" type="submit"><?php echo esc_html__('Save','jws_streamvid'); ?></button>
                
            </div>
            </form>
           
        </div>
        </div>
       
       <?php
        
    }
    
    
    public function set_image_playlist_from_post(){ 
       
        $args = wp_parse_args( $_POST, array(
            'post_id'   =>  0,
            'term_id'   =>  0
        ) );
        
        extract( $args );

        $post_id = (int)$post_id;
        $term_id = (int)$term_id;

        $errors = new WP_Error();

        if( ! $this->_is_owner( $term_id ) ){
            $errors->add(
                'no_permission',
                esc_html__( 'You do not have permission to add post to this collection', 'jws_streamvid' )
            );
        }

        if( ! has_post_thumbnail( $post_id ) ){
            $errors->add(
                'no_thumbnail',
                esc_html__( 'Thumbnail Image was not found', 'jws_streamvid' )
            );
        }
       
        $errors = apply_filters( 'streamvid/playlist/playlist/from/post', $errors , $args );
        
      
        
        if( $errors->get_error_code() ){
             wp_send_json_error( $errors );
        } 
        
        $thumbnail_id   = get_post_thumbnail_id( $post_id );
        $thumbnail_url  = wp_get_attachment_image_url( $thumbnail_id, 'full' );
        $results = update_term_meta( $term_id, 'playlist_image', $thumbnail_id );
        
         
         if( ! $results ){
            wp_send_json_error(
                new WP_Error(
                    'error',
                    esc_html__( 'It seems you tried to set up a same thumbnail image, please choose another one', 'jws_streamvid' )
                )
            );
        }
        
        if( is_wp_error( $results )){
            wp_send_json_error( $results );
        }
       
     
        $message = esc_html__( 'Thumbnail Image has been set up successfully', 'jws_streamvid' );
   
        wp_send_json_success( compact( 'thumbnail_url','message' ) );
        
    }
    
    
    
    
    public static  function the_playlist_control($args) {
        $args = wp_parse_args( $args, array(
            'post_id'   => 0,
            'term_id'   => 0,   
            'post_type' => 'videos',
            'playlist_type' => ''    
       ) );
       
       extract( $args );
       ?>
       
            <div class="playlist-control jws-dropdown-ui">
                <button class="dr-button"><i class="jws-icon-dots-three-outline-vertical"></i></button>
                <ul class="dropdown-menu">
                    <li><a href="#" data-term-id="<?php echo esc_attr($term_id); ?>" data-post-id="<?php echo esc_attr($post_id); ?>" data-post-type="<?php echo esc_attr($post_type); ?>" data-playlist-type="<?php echo esc_attr($playlist_type); ?>" class="set-item-playlist"><i class="jws-icon-trash"></i><?php echo esc_html__('Remove','jws_streamvid'); ?></a></li>
                    <li><a href="#" data-term-id="<?php echo esc_attr($term_id); ?>" data-post-id="<?php echo esc_attr($post_id); ?>" class="set-thumbnail"><i class="jws-icon-image"></i><?php echo esc_html__('Set as thumbnail','jws_streamvid'); ?></a></li>
                    <li><a href="#" data-modal-jws="#save-to-playlist" data-post-id="<?php echo esc_attr($post_id); ?>" data-post-type="<?php echo esc_attr($post_type); ?>" data-playlist-type="<?php echo esc_attr($playlist_type); ?>"><i class="jws-icon-queue"></i><?php echo esc_html__('Save to an other playlist','jws_streamvid'); ?></a></li>
                </ul>
            </div>
       
       <?php
        
    }

 
public function get_save_to_playlist_single() {
  
  
    $args = wp_parse_args( $_POST, array(
        'post_id'   =>  0,
        'playlist_type' => 'videos_playlist',
        'post_type' => 'videos'
    ) );
    
    extract( $args );

    $post_id = (int)$post_id;

    $errors = new WP_Error();

   
  
    
  $terms = get_terms( array(
            'taxonomy' => $playlist_type,
            'hide_empty' => false,
            'meta_query' => array(
                [
                    'key' => 'user',
                    'value' => get_current_user_id()
                ]
            ),
        
    ) );

    
    ob_start();    
    
    if(!empty($terms)) {
            foreach($terms as $term) {
                $has_term = has_term( $term->term_id, $playlist_type, $post_id );
                $checked = $has_term ? 'checked' : '';
                ?>
                <div class="playlist-item">
                    <div class="dropdown-checkbox">
                        <input class="form-check-input" 
                               id="playlist-<?php echo esc_attr($term->term_id); ?>" 
                               type="checkbox" 
                               value="<?php echo esc_attr($term->term_id); ?>"
                               <?php echo $checked; ?>>
                        <label class="form-check-label" for="playlist-<?php echo esc_attr($term->term_id); ?>">
                            <?php echo esc_html($term->name); ?>
                        </label>
                    </div>
            </div>
                <?php
            }
  
    } else {
        echo '<div class="playlist-item"><div class="playlist-loading">' . esc_html__('No playlists found','streamvid') . '</div></div>';
    }
    $results = ob_get_clean();
    
    if( is_wp_error( $results )){
        wp_send_json_error( $results );
    }
    
    
    wp_send_json_success( $results );
    
}
    
    public function get_save_to_playlist() {
      
      
        $args = wp_parse_args( $_POST, array(
            'post_id'   =>  0,
            'playlist_type' => 'videos_playlist',
            'post_type' => 'videos'
        ) );
        
        extract( $args );

        $post_id = (int)$post_id;

        $errors = new WP_Error();

       
      
        
      $terms = get_terms( array(
                'taxonomy' => $playlist_type,
                'hide_empty' => false,
                'meta_query' => array(
                    [
                        'key' => 'user',
                        'value' => get_current_user_id()
                    ]
                ),
            
        ) );

        
        ob_start();    
        
        if(!empty($terms)) {
                $url_playlist = Jws_Streamvid_Profile::get_url('playlist');
                foreach($terms as $term) {
                    $status = get_term_meta( $term->term_id, 'status', true);
                    $thumbnail = get_term_meta( $term->term_id, 'playlist_image', true);
                    $url =  add_query_arg( 
                     array(  
                       'playlist' => $term->term_id,
                       'playlist_type' => $playlist_type 
                     ),
                     $url_playlist 
                    );
                    $count = $term->count;
                    jws_streamvid_load_template("playlist/content/playlist-item-modal.php", false , compact('term' , 'count' , 'thumbnail' , 'status' , 'url' , 'post_id' , 'playlist_type' , 'post_type' ));
                    ?>
                
                <?php }
      
        }
        $results = ob_get_clean(); 
        
        if( is_wp_error( $results )){
            wp_send_json_error( $results );
        }
        
        
        wp_send_json_success( $results );
        
    }
    
    
  
}

  