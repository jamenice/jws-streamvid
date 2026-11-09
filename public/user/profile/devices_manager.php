<?php
if( ! defined('ABSPATH' ) ){
    exit;
}
if(!jws_streamvid()->get()->profile->_profile_is_owner()) return false;

// Profile Version 3: this device, other devices and signing out, without the popup.
if ( class_exists( 'Jws_Profile_Library' ) && Jws_Profile_Library::is_v3() ) {
    jws_streamvid_load_template( 'user/profile/v3/devices.php', false );
    return;
}

$devices = get_user_meta(get_current_user_id(), 'jws_user_devices', true);
echo do_shortcode('[jws_device_manager]');