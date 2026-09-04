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
 * This class defines all code for media
 *
 * @since      1.0.0
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 * @author     Jws Theme <jwstheme@gmail.com>
 */
class Jws_Streamvid_Media {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
  
    public static function add_encode_column_media($defaults) { 
        
        $advence_videos = jws_theme_get_option('video_advenced');
        
        if($advence_videos == 'encode') {
           $defaults['media_encode'] = esc_html__( 'Media Encode', 'seatevent' ); 
        }
        if($advence_videos == 'bunny') {
           $defaults['media_bunny'] = esc_html__( 'Media Bunny', 'seatevent' );
        }
        if($advence_videos == 'cloudflare') {
           $defaults['media_cloudflare'] = esc_html__( 'Media Cloudflare', 'seatevent' );
        }

        return $defaults;
    }
    
    public  function add_encode_column_media_content($column_name) {
            
            $info = pathinfo( get_attached_file( get_the_ID() ));
         
            switch ( $column_name )
            {
                case 'media_encode' :
                    
                    
                    $status = get_post_meta(get_the_ID() , 'encode_status' , true);
                    $file = get_post_meta(get_the_ID() , 'encode_url' , true);

                    if($info['extension'] == 'mp4') {
                        if($status == 'encoded') {
                            $text = esc_html__( 'Decode', 'seatevent' );
                        }elseif($status == 'encoding') {
                            $text = esc_html__( 'Encoding', 'seatevent' );
                        }else {
                            $text = esc_html__( 'Encode', 'seatevent' );
                        }
                        if($status == 'encoded') {
                          echo '<div class="bag_encoded">'.$status.'</div>';  
                        } 
                        echo '<a class="button jws-media-encode '.$status.'" href="#" data-id="'.get_the_ID().'">'.$text.'</a>';
                        
                        
                    }
                   
               break;
                
                
               case 'media_cloudflare' :
                    
                 if($info['extension'] == 'mp4') {  
                      $cloudflare_id = get_post_meta(get_the_ID() , 'cloudflare_id' , true);   
                     if(!empty($cloudflare_id)) {
                        echo '<div>'.esc_html__( 'Synced cloudflare', 'seatevent' ).'</div>';
                        echo '<a class="button jws-remove-cloudflare" href="#" data-id="'.get_the_ID().'">'.esc_html__( 'Remove Cloudflare', 'seatevent' ).'</a>';
                     } else {
                        echo '<a class="button jws-media-cloudflare" href="#" data-id="'.get_the_ID().'">'.esc_html__( 'Cloudflare sync', 'seatevent' ).'</a>';
                     }
                     
                    
                 }     
             
               break;
                
               case 'media_bunny' :
               
               
                 $bunny_id = get_post_meta(get_the_ID() , 'bunny_id' , true);
                 
                 if($info['extension'] == 'mp4') {  
                    
                     if(!empty($bunny_id)) {
                        echo '<div>'.esc_html__( 'Synced bunny', 'seatevent' ).'</div>';
                        echo '<a class="button jws-remove-bunny" href="#" data-id="'.get_the_ID().'">'.esc_html__( 'Remove Bunny', 'seatevent' ).'</a>';
                     } else {
                        echo '<a class="button jws-media-bunny" href="#" data-id="'.get_the_ID().'">'.esc_html__( 'Bunny sync', 'seatevent' ).'</a>';
                     }
                     
                    
                 }  
                 
                break;
        
                default :
                break;
            }
    }
    
    public function media_encode_ajax(){ 
        $ffmpeg_path = jws_theme_get_option('ffmpeg_path','/usr/bin/');
        if(isset($_POST['id'])) { 
            
            $encoder = new Jws_Streamvid_Encode( 
    			get_attached_file( $_POST['id'] ),
    			$ffmpeg_path
    		);
            
            if($_POST['status'] == 'encoding') { 
                  $percentage = $encoder->get_encoded_percentage();
                  $status = '';
                  if($percentage >= 100) { 
                      update_post_meta($_POST['id'], 'encode_status', 'encoded');
                  };
                  $result_check = array(
                       'percentage' => 	$percentage ,
                       'status' => $status,
                     
                  );  
                  wp_send_json_success( $result_check );
                  return false;
            }
            
            if($_POST['status'] == 'encoded') {

                $encoder->delete_file_folder();
                delete_post_meta($_POST['id'] ,'encode_url');
                delete_post_meta($_POST['id'] ,'encode_status');
                
            }else {
                
                $log = 'no encode';
                
                $results = $encoder->generate_video_hls();
                
                 
                $percentage = $encoder->get_encoded_percentage();
            
                
                if(  $encoder->get_result_code() == 0 ){
        			/**
        			 *
        			 * Fires after video being encoded
        			 * 
        			 */
        		    $this->media_encode_status($_POST['id'],$results,$percentage);
                }   
            }

            
            $result_check = array(
               'content' => 	get_attached_file( $_POST['id'] ),
               'status' => $_POST,
               'log' => $log
            );  
            
        }else {
            $result_check = 'error';
        }
        
        wp_send_json_success( $result_check );
        
    }
    
    public function media_encode_status($id,$file_path,$percentage){  
        
        $status = get_post_meta($id, 'encode_status');
    
        if(empty($status)) {
            update_post_meta($id, 'encode_status', 'encoding'); 
            update_post_meta($id, 'encode_url', $file_path); 
        }
        if($percentage >= 99) {
            update_post_meta($id, 'encode_status', 'encoded'); 
        }
        
        
    }
    
    
     public function delete_attachment($post_id, $post ){  
        if( wp_attachment_is( 'video',  $post_id ) ){
            
            $encoder = new Jws_Streamvid_Encode( 
    			get_attached_file( $post_id )
    		);
            
            $encoder->delete_file_folder();
            delete_post_meta($post_id ,'encode_url');
            delete_post_meta($post_id ,'encode_status');
            
            /* Remove bunny video */
            
            $this->media_bunny_delete($post_id);
           
        }
    }
    
    
    /* Code With Bunny Cdn */
    public function media_bunny_delete($id){  
            $logs = '';
            $bunny_id = get_post_meta($id , 'bunny_id' , true);
            delete_post_meta($id ,'bunny_id');
            $curl = curl_init();
            $library_id 	= jws_theme_get_option('library_id');
            $bn_access_key 	= jws_theme_get_option('bn_access_key');
            curl_setopt_array($curl, [
              CURLOPT_URL => "https://video.bunnycdn.com/library/$library_id/videos/$bunny_id",
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => "",
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 30,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => "DELETE",
              CURLOPT_HTTPHEADER => [
                "AccessKey:  $bn_access_key",
                "accept: application/json"
              ],
            ]);
            
            $response = curl_exec($curl);
            $err = curl_error($curl);
            
            curl_close($curl);
            
            if ($err) {
              $logs = "cURL Error #:" . $err;
            } else {
              $data = json_decode($response,true); 
              $logs = $data;
            } 
            return $logs;
        
    }
    
    
    public function media_bunny_delete_ajax(){ 
          
        if(isset($_POST['id'])) { 
            
             $logs = $this->media_bunny_delete($_POST['id']);
             $result_check = array(
               'status' => $_POST,
               'log' => $logs,
           
            );  
            wp_send_json_success( $result_check );
        }   
    }
      
    public function media_bunny_ajax(){ 
        $library_id 	= jws_theme_get_option('library_id');
        $bn_access_key 	= jws_theme_get_option('bn_access_key');    
        if(isset($_POST['id'])) { 
            $video_url = wp_get_attachment_url($_POST['id']);
            $curl = curl_init();
            
            curl_setopt_array($curl, [
              CURLOPT_URL => "https://video.bunnycdn.com/library/$library_id/videos/fetch",
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => "",
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 30,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => "POST",
              CURLOPT_POSTFIELDS => "{\"url\":\"$video_url\"}",
              CURLOPT_HTTPHEADER => [
                "AccessKey:  $bn_access_key",
                "accept: application/json",
                "content-type: application/*+json"
              ],
            ]);
            
            $response = curl_exec($curl);
            $err = curl_error($curl);
            
            curl_close($curl);
            
            if ($err) {
              $logs =  "cURL Error #:" . $err;
            } else {
              $data = json_decode($response,true); 
              if(isset($data['id'])) {
                update_post_meta($_POST['id'], 'bunny_id', $data['id']); 
              } 
              $logs = $data;
            }

            
            $result_check = array(
               'status' => $_POST,
               'log' => $logs,
           
            );  
            wp_send_json_success( $result_check );
        }   
    }
    
    public function media_bunny_check_id(){  
      
            $curl = curl_init(); 
            $library_id 	= jws_theme_get_option('library_id');
            $bn_access_key 	= jws_theme_get_option('bn_access_key');
            curl_setopt_array($curl, [
              CURLOPT_URL => "https://video.bunnycdn.com/library/$library_id/videos/983e1883-d1ae-4b69-a996-eedf651d8dd5",
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => "",
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 30,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => "GET",
              CURLOPT_HTTPHEADER => [
                "AccessKey: $bn_access_key",
                "accept: application/json"
              ],
            ]);
            
            $response = curl_exec($curl);
          
            $err = curl_error($curl);
            
            curl_close($curl);
            
            if ($err) {
              echo "cURL Error #:" . $err;
            } else {
             
              $data = json_decode($response,true);
              if(isset($data['guid'])) {
              }
              
            }
                
     }
     
     
   // Cloudflare  Videos.
     
     
    public function upload_video_cloudflare_stream() {
        
        $account_id 	= jws_theme_get_option('cloudflare_id');
        $cl_access_key 	= jws_theme_get_option('cloudflare_key');
        
        $errors = new WP_Error(); 
        
        $video_url = wp_get_attachment_url($_POST['id']);
 
        $api_endpoint = "https://api.cloudflare.com/client/v4/accounts/$account_id/stream/copy/";
        $api_key = "Bearer $cl_access_key";
        $headers = [
            'Authorization' => $api_key,
            'Content-Type' => 'application/json',
        ];
        $body = [
            'url' => $video_url,
        ];
        $args = [
            'headers' => $headers,
            'body' => wp_json_encode($body),
        ];
    
 
        $response = wp_remote_post($api_endpoint, $args);

        if(wp_remote_retrieve_response_code($response) == 400) {
            $errors->add( 
				'cl_error', 
				esc_html__( 'Error please check config api key and account id.', 'jws_streamvid' ) 
			);
        }
        
        if( $errors->get_error_code() ){
             wp_send_json_error( $errors );
        } 
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            $video_id = $data['result']['uid'];
            update_post_meta($_POST['id'], 'cloudflare_id', $video_id); 
            wp_send_json_success($video_id);
        }
    
    } 
    
     public function delete_video_cloudflare_stream($id){  
            $account_id 	= jws_theme_get_option('cloudflare_id');
            $cl_access_key 	= jws_theme_get_option('cloudflare_key');
          
            $errors = new WP_Error(); 
            $cloudflare_id = get_post_meta($_POST['id'] , 'cloudflare_id' , true);
           
     
            $api_endpoint = "https://api.cloudflare.com/client/v4/accounts/$account_id/stream/$cloudflare_id";
            
            $api_key = "Bearer $cl_access_key";
            $headers = [
                'Authorization' => $api_key,
                'Content-Type' => 'application/json',
            ];
            $body = [
                'url' => $video_url,
            ];
            $args = [
                'method' => 'DELETE',
                'headers' => $headers,
                'body' => wp_json_encode($body),
            ];
        
     
            $response = wp_remote_post($api_endpoint, $args);

            if(wp_remote_retrieve_response_code($response) == 400) {
                $errors->add( 
    				'cl_error', 
    				esc_html__( 'Error please check config api key and account id.', 'jws_streamvid' ) 
    			);
            }
      
            
            if( $errors->get_error_code() ){
                 wp_send_json_error( $errors );
            } 
   
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                delete_post_meta($_POST['id'] ,'cloudflare_id');
                wp_send_json_success($data);
            }
        
    }
    
    public function post_videos_media($args){
        $args = wp_parse_args( $args, array(
            'image_size'   =>  'full',
            'post_id'   =>  get_the_ID(),
            'edit'      => false,
            'img_two' => false
        ) );

        extract( $args ); 

        $background_banner = get_post_meta( $post_id , 'featured_image_two', true );  
        $live = get_post_meta($post_id , 'live_data' , true);
        $pre = jws_premium_videos($post_id);
        $attach_id = get_post_thumbnail_id($post_id);

        $tmdb_enable_image_url = jws_theme_get_option('tmdb_enable_image_url',false);
        $tmdb_image_url = jws_theme_get_option( 'tmdb_image_url',' https://image.tmdb.org/t/p/original' );
        $poster_path = get_post_meta( $post_id , 'poster_path', true );
        $backdrop_path = get_post_meta( $post_id , 'backdrop_path', true );  

            
        if($img_two && !empty($background_banner)) {
          $attach_id = $background_banner;
        }
        $image = jws_image_advanced(array('attach_id' => $attach_id, 'thumb_size' => $image_size)); 
       
        if($tmdb_enable_image_url && !empty($poster_path)) {
            $image =  function_exists('jws_poster_banner_image') ? jws_poster_banner_image($post_id, $image_size) : '';
       }

        if($img_two && $tmdb_enable_image_url && !empty($backdrop_path) ) {
            $image = function_exists('jws_backdrop_banner_image') ? jws_backdrop_banner_image($post_id, $image_size) : '';
        }

        /*
         * Drama keeps its own vertical poster in the `drama_poster` field
         * rather than always relying on the featured image, so a drama post
         * with no featured image set falls through every branch above to
         * the theme's generic placeholder (WooCommerce's, on this site)
         * instead of its real artwork. Overridden last, unconditionally,
         * since none of the TMDB branches above ever apply to drama.
         */
        if ( 'drama' === get_post_type( $post_id ) && class_exists( 'Jws_Drama_Templates' ) ) {
            $drama_poster = Jws_Drama_Templates::poster_url( $post_id, $image_size );

            if ( $drama_poster ) {
                $image = '<img class="attachment-full" alt="" src="' . esc_url( $drama_poster ) . '">';
            }
        }

        echo !empty($image) ? $image : '';
        echo $pre;
        
        if(!empty($live)) printf( '<span class="live-bage fs-small cl-light">%s</span>', esc_html__('Live','jws_streamvid') );
    }
    

}