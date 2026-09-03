<?php
if( ! defined('ABSPATH' ) ){
    exit;
}
if(!jws_streamvid()->get()->profile->_profile_is_owner()) return false;

$devices = get_user_meta(get_current_user_id(), 'jws_user_devices', true);
echo do_shortcode('[jws_device_manager]');