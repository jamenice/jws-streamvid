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
 * This class defines all code for live stream.
 *
 * @since      1.0.0
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 * @author     Jws Theme <jwstheme@gmail.com>
 */
class Jws_Streamvid_Live_Videos {
    
        
     protected $api_url  =   'https://api.cloudflare.com/client/v4/accounts/ACCOUNT_ID/stream';
     protected $api_token   =   '';   
     /**
     *
     * Class contructor
     * 
     * @since 1.0.0
     */
    public function __construct( $args = array() ){
        

    
  
    }    
        
    
     protected function call_api( $url, $args = array() ){
        $cl_access_key 	= jws_theme_get_option('cloudflare_key');
        $args = array_merge( $args, array(
            'headers'   =>  array(
                'Authorization'     =>  'Bearer ' . $cl_access_key,
                'Content-Type'      =>  'application/json'
            )
        ) );
        
    

        $response = wp_remote_request( $url, $args );

        if( is_wp_error( $response ) ){
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if( is_array( $body ) && array_key_exists( 'success', $body ) && ! $body['success'] ){
            return new WP_Error(
                'error',
                json_encode( $body['errors'] )
            );
        }

        return is_array( $body ) && array_key_exists( 'result' , $body ) ? $body['result'] : $body;
    }
    
    /**
     *
     * Update Video
     * 
     * @param  string $uid
     * @return call_api()
     *
     * @since 1.0.0
     * 
     */
   public function start_live_stream( $args = array() ){
        $args = wp_parse_args( $args, array(
            'uid'                   =>  '',
            'name'                  =>  '',
            'description'           =>  '',
            'author'                =>  '',
            'mode'                  =>  'automatic',
            'timeoutSeconds'        =>  10,
            'allowedOrigins'        =>  array(),
            'requireSignedURLs'     =>  false
        ) );

        extract( $args );
        
        $cloudflare_id 	= jws_theme_get_option('cloudflare_id');
        $api_url   =   str_replace( 'ACCOUNT_ID' , $cloudflare_id , $this->api_url );
            
        $body = $meta = $recording = array();

        if( ! empty( $name ) ){
            $meta['name'] = $name;
        }

        if( ! empty( $description ) ){
            $meta['description'] = $description;
        }

        if( ! empty( $mode ) ){
            $recording['mode'] = $mode;
        }

        if( ! empty( $timeoutSeconds ) ){
            $recording['timeoutSeconds'] = $timeoutSeconds;
        }

        if( ! empty( $requireSignedURLs ) ){
            $recording['requireSignedURLs'] = $requireSignedURLs;
        }

        if( $meta ){
            $body['meta'] = $meta;
        }

        if( $recording ){
            $body['recording'] = $recording;
        }

        $api_url = $api_url . "/live_inputs";

        if( $uid ){
            $api_url = trailingslashit( $api_url ) . $uid;
        }

        $response = $this->call_api( $api_url, array(
            'method'    =>  'POST',
            'body'      =>  json_encode( $body )
        ) );

        if( is_wp_error( $response ) ){
            return $response;
        }

        return $response;
    }

     public function get_live_stream_data( $uid = ''  ){
        $cloudflare_id 	= jws_theme_get_option('cloudflare_id');
        $api_url   =   str_replace( 'ACCOUNT_ID' , $cloudflare_id , $this->api_url );    
        return $this->call_api( $api_url . "/live_inputs/" . $uid . '/videos', array(
            'method'    =>  'GET'
        ) );
     }

      public function poli_get_live_stream_status( $uid = ''  ){
      
        $url = sprintf( 'https://videodelivery.net/%s/lifecycle', $uid );

        $response = wp_remote_get( $url );
  
        if( is_wp_error( $response ) ){
            return $response;
        }

        return json_decode( wp_remote_retrieve_body( $response ), true );
     }
     
     
     public function get_live_stream_url( $uid = ''  ){
        
        
            $live_stream_data =  jws_streamvid()->get()->live_videos->get_live_stream_data($uid);
            if( is_wp_error( $live_stream_data ) ){
                return false;
            }
            if(isset($live_stream_data[0]['playback']['hls'])) {
                return $live_stream_data[0]['playback']['hls'];
            }
      
        
        
     
     }
     
     
     

    public function ajax_check_live_status() { 
        
        $uid = $_POST['id'];
     
        $errors = new WP_Error(); 
        if(isset($uid) && !empty($uid)) {
        $status_default = 'disconnected';
        $live_stream_data =  $this->poli_get_live_stream_status($uid);
        
        if(isset($live_stream_data['status'])) {
            
            $status = $live_stream_data['status'];
            
   
            if($status == 'ready') {
           
                  $message = esc_html__( 'Stream has started.', 'jws_streamvid' );
            }
            
            if($status == 'disconnected') {
           
                  $message = esc_html__( 'Stream has not started yet.', 'jws_streamvid' );

            }
            
            if($status == 'initializing') {
           
                
                 $message = esc_html__( 'Stream is starting soon.', 'jws_streamvid' );
                
                 
            }
         
           wp_send_json_success(compact( 'status','message','live_stream_data'));
            

          } else {
            
            
            
            $errors->add(
                    'no_live',
                    esc_html__( 'Not found', 'jws_streamvid' )
            );
            
            
                  
                  
          }
          
          
          if( $errors->get_error_code() ){
            
            
             wp_send_json_error( $errors );
             
             
          } 
          
         
        }

    
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
                    'value' => get_queried_object_id()
                ]
            );
        } 
         
        $result = get_terms($query);

        return $result;
        
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

            }
    
   }
   
    public function start_stream() { 
        jws_return_data_demo();
        $args = wp_parse_args( $_POST, array(
            'description'  =>  '',
            'name'  =>  '',
            'videos_cat'  =>  '',
            'videos_tag'  =>  '',
            'video_thumbnail' => ''
        ) );

        extract( $args );
       
        $errors = new WP_Error(); 
        
        
        $images_thumbail = $this->add_videos_thmbnail($errors);

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
			'post_status'	=>	'publish',
		);
  
        $api_create = $this->start_live_stream();
        
        if( is_wp_error( $api_create ) ){

            wp_send_json_error($api_create);
        }

        
        $post_id = wp_insert_post( $post , true );

        update_post_meta( $post_id , 'live_data'  , $api_create ); 


		if( is_wp_error( $post_id ) ){
		  
		  wp_send_json_error( $post_id );
          
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
        
        if(is_array( $videos_cat )) {
            
           wp_set_post_terms( $post_id, $videos_cat, 'videos_cat');
 
        }
  
        if(is_array( $videos_tag )) {
            
           wp_set_post_terms( $post_id, $videos_tag, 'videos_tag' );
 
        }
        
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

   public function delete_live_data(){  
        $live_data = get_post_meta( $_POST['id'] , 'live_data', true ); 
        $cloudflare_id 	= jws_theme_get_option('cloudflare_id');
        $api_url   =   str_replace( 'ACCOUNT_ID' , $cloudflare_id , $this->api_url );    

       
       $response = $this->call_api( $api_url . "/live_inputs/" . $live_data['uid'] . '', array(
           'method'    =>  'DELETE'
       ) );
      
      if (is_wp_error($response)) {
           wp_send_json_error( $response ); 
      }
      $videos = $this->get_live_stream_data($live_data['uid']);
  
    
      if (!is_wp_error($videos) && !empty($videos)) {
         
         
         foreach($videos as $video) {
            
             $videoId = $video['uid'];
              $response = $this->call_api( $api_url . "/" . $videoId . '', array(
                    'method'    =>  'DELETE'
               ) );
       
            
         }
         
         
      } 
      if (!is_wp_error($response)) {
           delete_post_meta($_POST['id'] ,'live_data');
           wp_send_json_success($response);
      }
     
   }  
    
    
    public function start_live_in_admin(){  
        
      
        $api_create = $this->start_live_stream();
        
        if( is_wp_error( $api_create ) ){
             wp_send_json_error( $api_create ); 
        }
        
       
        
        $post_id = $_POST['id'];

        update_post_meta( $post_id , 'live_data'  , $api_create ); 
        
        wp_send_json_success($api_create);
        
    } 
    
public function form_upload() {
     
        $max_size = jws_get_max_upload_image_size();
   
       ?>
        
        <div id="upload-videos-live" class="mfp-hide">
        
        
         <div class="form-head">
        
            <h5 class="title">
                <?php 
                  
                  echo esc_html__('Live Streaming','jws_streamvid'); 
                    
                ?>
            </h5>
      
            <p><?php echo esc_html__('Start create your own streaming.','jws_streamvid'); ?></p>
           
            
        </div>
         <div class="form-body">
        
            <form class="form-videos">
        
            <p class="field-item">
            
                <label for="name"><?php echo esc_html__('Title','jws_streamvid'); ?> *</label>
                <input type="text" id="name" name="name"  value=""/>
            
            </p>
            

            <div class="field-item">
                <label for="videos-live-thumbnail"><?php echo esc_html__('Thumbnail','jws_streamvid'); ?></label>
                <?php
                    printf(
                        '<div class="note-max-size">'.esc_html__( 'Support *.png, *.jpeg, *.gif, *.jpg. Maximun upload file size: ' , 'jws_streamvid' ).'%smb.</div>',
                        number_format_i18n( ceil( $max_size/1048576 ) )
                    );
                 ?>
                <div class="file-thumbnail"></div>
                <input name="video_thumbnail" type="file" accept=".jpg,.jpeg,.png,.gif,.bmp,.tiff" id="videos-live-thumbnail" class="video-thumbnail">
            </div>
            
            
            <p class="field-item">
            
                <label for="description"><?php echo esc_html__('Description','jws_streamvid'); ?></label>
                <textarea  id="description" name="description"  value=""/></textarea>
            
            </p>
            
            <?php 
            $tags = $this->get_taxonomy('videos_tag');
            if(!empty($tags)) : ?>
            <p class="field-item">
                <label><?php echo esc_html__('Tags','jws_streamvid'); ?></label>
               
                <select multiple="" class="" name="videos_tag[]" tabindex="-1" aria-hidden="true">
                    <?php 
                    
                    foreach($tags as $tag) {
                        echo '<option value="'.esc_attr($tag->slug).'">'.esc_html($tag->name).'</option>';
                    }
                    
                    ?>
                </select>
                <span class="tag-note"><?php echo esc_html__('Use comma to separate tags','jws_streamvid'); ?></span>
               
            </p>
            <?php endif; ?>
            <?php 
            
            $categorys = $this->get_taxonomy('videos_cat');
            if(!empty($tags)) : ?>
           
            <div class="field-item">
                <label><?php echo esc_html__('Select Categories','jws_streamvid'); ?></label>
                <ul class="multiple-checkbox">
                <?php 
                    
                foreach($categorys as $category) {
                    $cat_id = "catlive_$category->term_id";
                    
                    printf(
                        '<li class="cat-item">
                          <input id="%1$s" type="checkbox" name="videos_cat[]" value="%2$s">  
                          <label for="%1$s">%3$s</label>  
                        </li>',
                        esc_attr( $cat_id ),
                        esc_attr( $category->term_id ),
                        esc_html( $category->name )
                    );
     
                }
                
                ?>
                </ul>
            </div>
            
             <?php endif; ?>

             <div class="form-button">
              <input name="action" type="hidden" value="upload_video_live"  />  
              <button class="btn-main button-default" type="submit"><?php echo esc_html__('Create Stream','jws_streamvid'); ?></button>
                
            </div>
            </form>
         </div>   
        
        
        
        </div>
        
        
       <?php
        
    }  
    
    public function stream_frontend() {     
        
        $id = get_the_ID();
        $live_data = get_post_meta( $id , 'live_data', true );
        
        if( !jws_streamvid()->get()->videos->_is_owner($id)){ 
            
            return;
            
        }
        
        
        if(empty($live_data)) {
            return;
        }
        
        ?>
        
        <div class="stream-video-container">
        
        <div id="stream-app" class="stream-item">
        
            <div class="stream-heading">
                
                <div class="icon">
                
                    <i class="jws-icon-video-camera"></i>
                
                </div>
                <div class="text">
                    
                    <span>
                        <?php echo esc_html__('RTMP Encoder/ Broadcaster','jws_streamvid'); ?>
                    </span>
                    <h5>
                        <?php echo esc_html__('Go Live with external streaming app','jws_streamvid'); ?>
                    </h5>
                
                </div>
            
            </div>
            
            <div class="stream-field">
                <?php
                    if(isset($live_data['rtmps']['url'])) {
                        echo '<h6>'.esc_html__('Server:','jws_streamvid').'</h6>';
                        echo '<div class="fs-small"><input type="text" value="'.esc_attr($live_data['rtmps']['url']).'"></input></div>';
                        
                    }
                ?>
            </div>
            
            <div class="stream-field">
                <?php
                    if(isset($live_data['rtmps']['streamKey'])) {
                        echo '<h6>'.esc_html__('Stream Key:','jws_streamvid').'</h6>';
                        echo '<div class="fs-small"><input type="text" value="'.esc_attr($live_data['rtmps']['streamKey']).'"></input></div>';
                        
                    }
                ?>
            </div>

        
        </div>

        </div>
        
        
        <?php
        
        
    } 
}