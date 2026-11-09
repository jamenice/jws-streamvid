<?php
if( ! defined('ABSPATH' ) ){
    exit;
}
if(!jws_streamvid()->get()->profile->_profile_is_owner()) return false;

// Profile Version 3: plan, usage, billing and invoices on one screen.
if ( class_exists( 'Jws_Profile_Library' ) && Jws_Profile_Library::is_v3() && function_exists( 'pmpro_getMembershipLevelForUser' ) ) {
    jws_streamvid_load_template( 'user/profile/v3/subscription.php', false );
    return;
}

get_template_part( 'paid-memberships-pro/shortcodes/pmpro_account' );

echo my_pmpro_shortcode_account( array(
            'sections'  =>  'membership'
) );

if(in_array(jws_theme_get_option('profile_layout'), array('v2', 'v3'), true)) {

    jws_streamvid_load_template( 'user/profile/invoices.php' );

}