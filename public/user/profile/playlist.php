<?php
if( ! defined('ABSPATH' ) ){
    exit;
}

if(isset($_GET['playlist']) && !empty($_GET['playlist'])) {

    jws_streamvid_load_template("playlist/playlist-single.php", false , array('id' => $_GET['playlist']));

} elseif ( class_exists( 'Jws_Profile_Library' ) && Jws_Profile_Library::is_v3() ) {

    // Profile Version 3: playlists as tabbed cards.
    jws_streamvid_load_template( 'user/profile/v3/playlists.php', false );

} else {
    
    jws_streamvid_load_template("playlist/playlist-page.php", false);
    
}

