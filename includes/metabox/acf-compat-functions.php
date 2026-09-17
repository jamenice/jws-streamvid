<?php

/**
 * ACF template functions, defined only when ACF is not active.
 * See Jws_Acf_Compat.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/metabox
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! function_exists( 'get_field' ) ) {
	function get_field( $selector, $post_id = false, $format_value = true, $escape_html = false ) {
		return Jws_Acf_Compat::get_field( $selector, $post_id, $format_value );
	}
}

if ( ! function_exists( 'update_field' ) ) {
	function update_field( $selector, $value, $post_id = false ) {
		return Jws_Acf_Compat::update_field( $selector, $value, $post_id );
	}
}
