<?php
if( ! defined('ABSPATH' ) ){
    exit;
}

jws_streamvid_load_template("playlist/page-content/content.php", false , array('taxonomy' => 'videos_playlist'));

jws_streamvid_load_template("playlist/page-content/content.php", false , array('taxonomy' => 'movies_playlist'));

jws_streamvid_load_template("playlist/page-content/content.php", false , array('taxonomy' => 'episodes_playlist'));

 