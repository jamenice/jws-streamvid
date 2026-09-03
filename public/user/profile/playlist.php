<?php
if( ! defined('ABSPATH' ) ){
    exit;
}

if(isset($_GET['playlist']) && !empty($_GET['playlist'])) {
    
    jws_streamvid_load_template("playlist/playlist-single.php", false , array('id' => $_GET['playlist'])); 
    
} else {
    
    jws_streamvid_load_template("playlist/playlist-page.php", false);
    
}

