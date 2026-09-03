<?php
if( ! defined('ABSPATH' ) ){
    exit;
}
if(!jws_streamvid()->get()->profile->_profile_is_owner()) return false;
	if( 0 < $levels_page_id = get_option( 'pmpro_levels_page_id' ) ){

		if( get_post_meta( $levels_page_id, '_elementor_edit_mode', true ) && class_exists( '\Elementor\Plugin' ) ){
            $pluginElementor = \Elementor\Plugin::instance();
            echo $pluginElementor->frontend->get_builder_content( $levels_page_id );
       
		}
		else{
			echo do_shortcode( get_post( $levels_page_id )->post_content ); 
		}

	}else{
	
	}
