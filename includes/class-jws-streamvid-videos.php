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
 * This class defines all code for videos
 *
 * @since      1.0.0
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 * @author     Jws Theme <jwstheme@gmail.com>
 */
class Jws_Streamvid_Videos {
    
    private $video_type;
   
    public function filter_videos($args) { 
        
        global $wp;
        $currentURL = home_url( $wp->request );
        
  
        $cat_parts = explode('/', $wp->request);
        
        $args = wp_parse_args( $args, array(
            'label' => false,
            'year' => false,
            'category' => false,
            'post_type' => 'tv_shows'
        ) );
        extract( $args );

        $option = array(
            'title' => esc_html__('Title','jws_streamvid'),
            'date' => esc_html__('Date','jws_streamvid'),
        );
        
        
        if($post_type != 'person') {
            
           $option['likes'] = esc_html__('Likes','jws_streamvid');
           $option['views'] = esc_html__('Views','jws_streamvid');
   
        }
        
        
        $option = apply_filters( 'streamvid_filter_sortby_field', $option);
       

        echo '<form method="get" action="'.esc_url($currentURL).'" class="post-select-filter jws-scrollbar-x fs-small">';
 
        ob_start(); 
        
        
        $taxonomy = $post_type.'_cat';

        $genrest_new = jws_theme_get_option( 'enable_new_genre_system' );

        if($genrest_new) {
            if($post_type == 'movies' || $post_type == 'tv_shows') {
                $taxonomy = 'genres';
            }
        }

        /* A post type with no `{type}_cat` of its own says so here rather than
           being special-cased in this method — short drama uses genres only. */
        $taxonomy = apply_filters( 'streamvid/filter/taxonomy', $taxonomy, $post_type );
       $category_slug = isset($_GET[$taxonomy]) ? sanitize_text_field($_GET[$taxonomy]) : '';
	
          if($category) {
                 
                 global $wpdb;
                 $term_ids_with_posts = $wpdb->get_col( $wpdb->prepare(
                    "SELECT DISTINCT tt.term_id
                     FROM {$wpdb->term_taxonomy} tt
                     INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                     INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
                     WHERE tt.taxonomy = %s
                     AND p.post_type = %s
                     AND p.post_status = 'publish'",
                    $taxonomy, $post_type
                 ) );
                 $categories = ! empty( $term_ids_with_posts )
                    ? get_terms( array(
                        'taxonomy'   => $taxonomy,
                        'include'    => $term_ids_with_posts,
                        'hide_empty' => false,
                        'orderby'    => 'name',
                    ) )
                    : array();
                
              
                 echo '<div class="fild-item">';
                    echo $label ? '<label class="fs-small cl-heading">'.esc_html__('Category','jws_streamvid').'</label>' : '';
                    echo "<select class='cat_change' data-taxonomy='" . esc_attr($taxonomy) . "'>";
                    echo '<option value="" data-url="'.get_post_type_archive_link($post_type).'">'.esc_html__('Category','jws_streamvid').'</option>';
                    foreach($categories as $value) {
                        printf(
                            '<option value="%s" %s data-url="%s">%s</option>',
                            esc_attr( $value->slug ),
                            isset($category_slug) && $category_slug == $value->slug ? 'selected=selected' : '',
                            get_post_type_archive_link($post_type),
                            $value->name
                        );
                    }
                    echo '</select>';
                
                echo '</div>';
                
            } 
        
            if($year) {
                
              global $wpdb;

              $years = $wpdb->get_results( $wpdb->prepare( "
                SELECT DISTINCT  pm.meta_value FROM {$wpdb->postmeta} pm
                LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE pm.meta_key = '%s' 
                AND p.post_type = '%s'
              ", "videos_years" , $post_type
               ) );
            
                if(!empty($years)) {
                    sort($years);       
                    echo '<div class="fild-item">';
                        echo $label ? '<label class="fs-small cl-heading">'.esc_html__('Year','jws_streamvid').'</label>' : '';
                        echo '<select class="years" name="years">';
                        echo '<option value="">'.esc_html__('Year','jws_streamvid').'</option>';
                        foreach($years as $value) {
                            if(empty($value->meta_value)) continue;
                            printf(
                                '<option value="%s" %s>%s</option>',
                                 esc_attr( $value->meta_value ),
                                isset($_GET['years']) && $_GET['years'] == $value->meta_value ? 'selected=selected' : '',
                                $value->meta_value
                            );
                            
                            
                        }
                        echo '</select>';
                    echo '</div>';
                    
                }

            }
    
        echo '<div class="fild-item">';
            echo $label ? '<label class="fs-small cl-heading">'.esc_html__('Sort by','jws_streamvid').'</label>' : '';
            echo '<select class="sortby" name="sortby">';
            echo '<option value="">'.esc_html__('Sort by','jws_streamvid').'</option>';
            foreach($option as $value => $name) {
                
                printf(
                    '<option value="%s" %s>%s</option>',
                     esc_attr( $value ),
                    isset($_GET['sortby']) && $_GET['sortby'] == $value ? 'selected=selected' : '',
                    $name
                );
                
                
          }
            echo '</select>';
        echo '</div>';
        $content = ob_get_clean();
        
        echo apply_filters( 'streamvid_filter_archive_field', $content);
        echo jws_query_string_form_fields( null, array('sortby','years',$taxonomy), '', true ); 
        echo '</form>';
        
    }
    
    public function upload_video() { 
        jws_return_data_demo();
        $args = wp_parse_args( $_POST, array(
            'description'  =>  '',
            'name'  =>  '',
            'videos_type'  =>  'file',
            'videos_cat'  =>  '',
            'videos_tag'  =>  '',
            'videos_playlist' => '',
            'attachment_id'  => 0,
            'videos_url' => '',
            'video_thumbnail' => '',
            'post_id' => ''
        ) );

        extract( $args );
        
        $errors = new WP_Error(); 
      
        $images_thumbail = $this->add_videos_thmbnail($errors);

        
       /** if( ! current_user_can( 'publish_posts' ) ){
			$errors->add( 
				'no_permission', 
				esc_html__( 'Sorry, You do not have permission to upload videos, please contact administrator for further assistance.', 'jws_streamvid' ) 
			);
		}
      **/  
        if( empty($name) ){
			$errors->add( 
				'name_empty', 
				esc_html__( 'Video title is required.', 'jws_streamvid' ) 
			);
		}
        
        
        
    
        if( $errors->get_error_code() ){
             wp_send_json_error( $errors );
        } 
        
        
        
        if( is_wp_error( $images_thumbail ) ){

			wp_send_json_error( $images_thumbail );
           
		}
        
        if( is_wp_error( $attachment_id ) ){

			wp_send_json_error( $attachment_id );
           
		}
        
        $post =  array(
			'post_title'	=>	$name,
			'post_type'		=>	'videos',
			'post_status'	=>	'publish'
		);
        
        if(empty($post_id)) {
           $post_id = wp_insert_post( $post , true ); 
        }
        

		if( is_wp_error( $post_id ) ){
		  
		  wp_send_json_error( $post_id );
          
		}
        update_post_meta( $post_id , 'videos_type'  , $videos_type );
        
        if($videos_type ==  'file') {
            
          update_post_meta( $post_id , 'videos_file'  , $attachment_id );  
          
        }elseif($videos_type == 'url' && !empty($videos_url)) {
            
          update_post_meta( $post_id , 'videos_url'  , $videos_url ); 
           
        }
        
        if($images_thumbail == 'video_thumbnail') {
            
            $attachment_id = media_handle_upload( $images_thumbail , 0, array( '' ), array('test_form' => FALSE) );
      
            if( ! is_wp_error( $attachment_id ) ){
                set_post_thumbnail( $post_id, $attachment_id );
            }

        }
        
   
        if(!empty($description)) {
            
           wp_update_post( array(  'ID' => $post_id, 'post_content' => $description ) ); 
           
        }

        
        if(is_array( $videos_playlist )) {
            
           wp_set_post_terms( $post_id, $videos_playlist, 'videos_playlist');
 
        }
        
        wp_set_post_terms( $post_id, $videos_cat, 'videos_cat');

        wp_set_post_terms( $post_id, $videos_tag, 'videos_tag' );
 
        
        $liked = get_post_meta($post_id, 'likes', true);
        $views = get_post_meta($post_id, 'views', true);
        
        if (empty($views)) {
            update_post_meta( $post_id, 'views', 0 );
        } 
        if (empty($liked)) {
            update_post_meta( $post_id, 'likes', 0 );
        }
        
     
        
        
        $message = esc_html__( 'Video has been created successfully.', 'jws_streamvid' );
        $output = get_the_permalink($post_id);
        wp_send_json_success(compact( 'output','message' ));    
    
    }
  

   public function add_videos_thmbnail($errors) {  
    
            if( isset( $_FILES[ 'video_thumbnail' ]['error'] ) && $_FILES[ 'video_thumbnail' ]['error'] == 0 ){ 
                 $file = $_FILES[ 'video_thumbnail' ];
                 
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
                $max_size = (int)jws_get_max_upload_image_size();
                    
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
                } else {
                    return 'video_thumbnail';
                }

            } else {
                    return $_FILES[ 'video_thumbnail' ];
            }
    
   }
   
    public function add_media_videos() { 
     jws_return_data_demo();
        $errors = new WP_Error();
  
        // Check size
		$allow_size = (int)jws_get_max_upload_image_size();

		if( ! isset( $_FILES['videos_file'] ) || (int)$_FILES['videos_file']['error'] != 0 ){
			$errors->add( 
				'file_error', 
				esc_html__( 'File was not found or empty.', 'jws_streamvid' ) 
			);
		}

		if( $allow_size < (int)$_FILES['videos_file']['size'] ){
			$errors->add( 
				'file_size_not_allowed', 
				esc_html__( 'The upload file exceeds the maximum allow file size.', 'jws_streamvid' ) 
			);
		}

		$file_type = wp_check_filetype( $_FILES['videos_file']['name'] );

		if( ! $file_type ){
			$errors->add( 
				'file_type_not_allowed', 
				esc_html__( 'File Type is not allowed.', 'jws_streamvid' ) 
			);
		}
        
        $type = explode( '/' , $file_type['type'] );

		if( ! is_array( $type ) || count( $type ) != 2 || ! in_array( $type[0], array( 'video', 'audio' )) ){
			$errors->add( 
				'file_type_not_allowed', 
				esc_html__( 'File Type is not allowed.', 'jws_streamvid' ) 
			);
		}

		if( $type[0] == 'video' && ! in_array( strtolower( $file_type['ext'] ) , wp_get_video_extensions() ) ){
			$errors->add( 
				'video_format_not_allowed', 
				esc_html__( 'Video Format is not allowed.', 'jws_streamvid' ) 
			);
		}

		if( $type[0] == 'audio' && ! in_array( $file_type['ext'] , wp_get_audio_extensions() ) ){
			$errors->add( 
				'audio_format_not_allowed', 
				esc_html__( 'Audio Format is not allowed.', 'jws_streamvid' ) 
			);
		}
        
        if( $errors->get_error_code() ){
		   wp_send_json_error( $errors );
		}
        
  
        $attachment_id = media_handle_upload( 'videos_file', 0, array( '' ), array('test_form' => FALSE) );

		if( is_wp_error( $attachment_id ) ){

			wp_send_json_error( $attachment_id );
           
		}
          
        $message = esc_html__( 'Videos has been set up successfully', 'jws_streamvid' );
   
         wp_send_json_success(compact( 'attachment_id','message' ));
   }
   
   
   public function get_taxonomy($slug) { 

        
        $query = array(
            'taxonomy' => $slug, 
            'hide_empty' => false,
        );
        
        if($slug == 'videos_playlist') {
            $query['meta_query'] = array(
                [
                    'key' => 'user',
                    'value' => get_current_user_id()
                ]
            );
        } 
       
         
        $result = get_terms($query);

        return $result;
        
   } 
    
   public function form_upload($args) {
       $args = wp_parse_args( $args, array(
            'type'   =>  'create',
       ) );
      
       extract( $args ); 

       
       $id_popup = $type == 'edit' ? 'edit-videos' : 'upload-videos';
       
       ?>
        
       <div id="<?php echo esc_attr($id_popup); ?>" class="mfp-hide">
        
        
         <div class="form-head">
            
            <?php
            
                if($type == 'edit') {
                        
                        ?>
                            
                        <h5 class="title">
                            <?php 
                              
                              echo esc_html__('Edit Video','jws_streamvid'); 
                                
                            ?>
                        </h5>
                        
                        <?php
                        
                } else {
                    ?>
                    
                       <h5 class="title">
                            <?php 
                              
                              echo esc_html__('Submit Video','jws_streamvid'); 
                                
                            ?>
                        </h5>
                  
                        <p><?php echo esc_html__('Please fill in all information bellow to submit video.','jws_streamvid'); ?></p>
                       
                    
                    <?php
                }
                
             ?>
       
            
        </div>
        <div class="form-body">
            <?php if($type == 'create') $this->videos_form($args); ?>
        </div>   
        </div>
        
        
       <?php
        
    } 
    
     public function videos_form($args){ 
       $args = wp_parse_args( $args, array(
            'type'   =>  'create',
            'id'     =>  '',
       ) );
       extract( $args ); 

        $max_size = jws_get_max_upload_image_size();
        ?>
            
               
            <form class="form-videos">
        
            <p class="field-item">
            
                <label for="video-name"><?php echo esc_html__('Video Title','jws_streamvid'); ?> *</label>
                <input type="text" id="video-name" name="name"  value="<?php if(!empty($id)) echo get_the_title($id); ?>"/>
            
            </p>
            
            <p class="field-item">
            
                <label><?php echo esc_html__('Video Description','jws_streamvid'); ?></label>
                <textarea name="description"><?php if(!empty($id)) echo get_post_field('post_content', $id);  ?></textarea>
            
            </p>
             <p class="field-item">
                <?php
                
              
                    $video_upload_file = jws_theme_get_option('video_up_file');
                    $video_up_embed = jws_theme_get_option('video_up_embed');
                    
                    if($video_up_embed) $this->video_type = 'url';  
                    if($video_upload_file) $this->video_type = 'file';  
                    
                    if($type != 'create') $this->video_type = get_post_meta($id, 'videos_type',true);
                    
                   
                ?>    
                
                <label for="video-type"><?php echo esc_html__('Choose Video Submit Method','jws_streamvid'); ?></label>
                <select name="videos_type" id="video-type">
                    <?php 
                     
                   
                     foreach ( $this->get_videos_type() as $key => $value ) {

                        printf(
                            '<option value="%s" %s>%s</option>',
                            esc_attr( $key ),
                            $this->video_type == $key ? 'selected="selected"' : '',
                            $value
                        );
    
                    }?>
                  
                </select>
            
            </p>  
          
            <?php if($video_upload_file) : ?>
            <p class="field-item<?php if($this->video_type != 'file') echo ' hidden'; ?>">
                <label for="video-file"><?php echo esc_html__('Video File','jws_streamvid'); ?></label>
                <input id="video-file" name="videos_file" type="file" accept="video/*" data-video="<?php if(!empty($id)) echo get_post_meta($id, 'videos_file',true);  ?>">
            </p>
            <?php endif; ?>
            
               <?php if($video_up_embed) : ?>
            <p class="field-item<?php if($this->video_type != 'url') echo ' hidden'; ?>">
                <label for="video-url"><?php echo esc_html__('Video URL or Embed','jws_streamvid'); ?></label>
                <textarea  id="video-url" name="videos_url"><?php if(!empty($id)) echo get_post_meta($id, 'videos_url',true);  ?></textarea>
             </p>
             <?php endif; ?>
          
            <div class="field-item">
                <label for="videos-thumbnail"><?php echo esc_html__('Video Thumbnail','jws_streamvid'); ?></label>
                <?php
                    printf(
                        '<div class="note-max-size">'.esc_html__( 'Support *.png, *.jpeg, *.gif, *.jpg. Maximun upload file size: ' , 'jws_streamvid' ).'%smb.</div>',
                        number_format_i18n( ceil( $max_size/1048576 ) )
                    );
                 ?>
                <div class="file-thumbnail">
                     <?php 
                       $thumbnail = get_post_thumbnail_id($id);
                       if($thumbnail && !empty($id)) {
                            $image = jws_image_advanced(array('attach_id' => $thumbnail, 'thumb_size' => 'full'));
                            echo !empty($image) ? $image : '';
                       }  
                    ?>
                </div>
                <input name="video_thumbnail" type="file" accept=".jpg,.jpeg,.png,.gif,.bmp,.tiff" id="videos-thumbnail" class="video-thumbnail">
            </div>
            <?php 
            $tags_add = array();
            $tags = $this->get_taxonomy('videos_tag');
            if(!empty($id))  {

                $term_list = get_the_terms($id, 'videos_tag');
                
                foreach($term_list as $value) {
                  $tags_add[] = $value->slug;
                }
                
                
            }
        
            if(!empty($tags)) : ?>
            <p class="field-item">
                <label><?php echo esc_html__('Tags','jws_streamvid'); ?></label>
               
                <select multiple="" class="" name="videos_tag[]" tabindex="-1" aria-hidden="true">
                    <?php 
                    
                    foreach($tags as $tag) {
                        printf(
                            '<option value="%s" %s>%s</option>',
                            esc_attr( $tag->slug ),
                            in_array($tag->slug , $tags_add) ? 'selected="selected"' : '',
                            $tag->name
                        );
                    }
                    
                    ?>
                </select> 
            </p>
            <?php endif; ?>
            <?php 
            
            $categorys = $this->get_taxonomy('videos_cat');
            $cat_add = array();
            if(!empty($id))  {
              
                $term_list = get_the_terms($id, 'videos_cat');
                
                foreach($term_list as $value) {
                  $cat_add[] = $value->term_id;
                }
                
                
            }

            if(!empty($categorys)) : ?>
           
            <div class="field-item">
                <label><?php echo esc_html__('Select Categories','jws_streamvid'); ?></label>
                <ul class="multiple-checkbox">
                <?php 
                    
                foreach($categorys as $category) {
                    $cat_id = "cat_$category->term_id";
                    
                    printf(
                        '<li class="cat-item">
                          <input id="%1$s" type="checkbox" title="%3$s" name="videos_cat[]" value="%2$s" %4$s>  
                          <label for="%1$s" title="%3$s">%3$s</label>  
                        </li>',
                        $cat_id,
                        $category->term_id,
                        $category->name,
                        in_array($category->term_id , $cat_add) ? 'checked' : ''
                    );
     
                }
                
                ?>
                </ul>
            </div>
            
             <?php endif; ?>
             <?php 
            
            $playlists = $this->get_taxonomy('videos_playlist');
            
            $play_add = array();
            if(!empty($id))  {
              
                $term_list = get_the_terms($id, 'videos_playlist');
                
                foreach($term_list as $value) {
                  $play_add[] = $value->term_id;
                }
                
                
            }
          
            if(!empty($playlists)) : ?>
             <div class="field-item">
                <label><?php echo esc_html__('Select Playlist','jws_streamvid'); ?></label>
                <ul class="multiple-checkbox">
                <?php 
          
                foreach($playlists as $playlist) {
                    $cat_id = "$playlist->term_id";
                    
                    printf(
                        '<li class="cat-item">
                          <input id="%1$s" type="checkbox" title="%3$s" name="videos_playlist[]" value="%2$s">  
                          <label for="%1$s" title="%3$s">%3$s</label>  
                        </li>',
                        $cat_id,
                        $playlist->term_id,
                        $playlist->name,
                        in_array($playlist->term_id , $play_add) ? 'checked' : ''
                    );
     
                }
                
                ?>
                </ul>
            </div>
            <?php endif; ?> 
            <div class="form-button">
              <input name="action" type="hidden" value="upload_video"  />  
              <input name="post_id" type="hidden" value="<?php if(!empty($id)) echo esc_attr($id); ?>"  /> 
              <button class="button-default" type="submit"><span class="text"><?php echo esc_html__('Submit Video','jws_streamvid'); ?></span></button>
              <?php if($type == 'edit') { ?>
                  <a href="#" class="button-custom delete-videos"><span class="text"><?php echo esc_html__('Delete Video','jws_streamvid'); ?></span></a>
              <?php } ?>  
             </div>
            </form>
        
        
        <?php
        
        
     }
     
     public function get_videos_type(){
 
        $data = array();
        
        $video_upload_file = jws_theme_get_option('video_up_file');
        $video_up_embed = jws_theme_get_option('video_up_embed');
        
        if($video_upload_file) {
            $data['file']  =  esc_html__( 'Upload Video', 'jws_streamvid' );
        }
        if($video_up_embed) {
            $data['url']  =  esc_html__( 'Video URL or Embed', 'jws_streamvid' );
        }
        return $data;
    } 
    
    
    public function video_editor(){
    
       ob_start(); 
       
       $args = wp_parse_args( $args, array(
            'type'   =>  'edit',
            'id'     =>  $_POST['id'],
       ) );
       
       $this->videos_form($args);

       $results = ob_get_clean();
       
       if( is_wp_error( $results ) ){

			wp_send_json_error( $results );
           
	   }
       wp_send_json_success($results); 
        
    }
    
    
    public function delete_videos(){
      jws_return_data_demo();
     
      $args = wp_parse_args( $_POST, array(
      
            'id'     =>  $_POST['id'],
            
      ) );
      extract( $args ); 
  
      $errors = new WP_Error();   
      if( ! $this->_is_owner( $id ) ){
            $errors->add(
                'no_permission',
                esc_html__( 'You do not have permission to add post to this collection', 'jws_streamvid' )
            );
      }
        
      if( $errors->get_error_code() ){
             wp_send_json_error( $errors );
      }
  
      if($id) {
        
        $results = wp_delete_post($id);
        
      }  
        
      if( is_wp_error( $results ) ){

			wp_send_json_error( $results );
           
	  }
      wp_send_json_success($results); 
        
    }
    
     public function _is_owner( $post_id = 0, $user_id = 0 ){

        $user_id = (int)$user_id;

        if( ! $user_id ){
            $user_id = get_current_user_id();
        }

        if( ! $user_id ){
            return false;
        }

        $author_id = get_post_field( 'post_author', $post_id );

        if( $author_id && $author_id == $user_id ){
            return true;
        }

        return false;
    } 
   
}
    