<?php 

if(isset($_POST['download_url'])) {
  
    $video_url = $_POST['download_url']; 
 
    
    $content = !empty($video_url) ? $video_url : 'no_file';
     
    $result = [
        'status' => $video_url,
        'content'  => $content,
    ];
    
    wp_send_json_success($result);
}
