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
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 * @author     Jws Theme <jwstheme@gmail.com>
 */
class Jws_Streamvid_Install {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function install() {
         add_action( 'init', array( __CLASS__, 'check_version' ), 5 );         
	}
    
    
    /**
     * Check MasVideos version and run the updater is required.
     *
     * This check is done on all requests and runs if the versions do not match.
     */
    public static function check_version() {
      
        if ( ! defined( 'IFRAME_REQUEST' ) ) {
            self::create_roles();  
        }
    }
    
     /**
     * Create roles and capabilities.
     */
    public static function create_roles() {
        global $wp_roles;

        if ( ! class_exists( 'WP_Roles' ) ) {
            return;
        }

        if ( ! isset( $wp_roles ) ) {
            $wp_roles = new WP_Roles(); // @codingStandardsIgnoreLine
        }

        $capabilities = self::get_core_capabilities();

        foreach ( $capabilities as $cap_group ) {
            foreach ( $cap_group as $cap ) {
                $wp_roles->add_cap( 'administrator', $cap );
            }
        }

        $video_contributor_capabilities = apply_filters( 'masvideos_video_contributor_capabilities', array(
            'edit_video',
            'read_video',
            'delete_video',
            'edit_videos',
            'delete_videos',
            'manage_video_terms',
            'assign_video_terms',
            'upload_files',
        ) );

        foreach ( $video_contributor_capabilities as $cap ) {
            $wp_roles->add_cap( 'contributor', $cap );
        }
    }

    /**
     * Get capabilities for MasVideos - these are assigned to admin/shop manager during installation or reset.
     *
     * @return array
     */
    private static function get_core_capabilities() {
        $capabilities = array();

        $capabilities['core'] = array(
            'manage_masvideos',
        );

        $capability_types = array( 'episodes', 'tv_shows', 'videos', 'movies', 'persons' );

        foreach ( $capability_types as $capability_type ) {

            $capabilities[ $capability_type ] = array(
                // Post type.
                "edit_{$capability_type}",
                "read_{$capability_type}",
                "delete_{$capability_type}",
                "edit_{$capability_type}s",
                "edit_others_{$capability_type}s",
                "publish_{$capability_type}s",
                "read_private_{$capability_type}s",
                "delete_{$capability_type}s",
                "delete_private_{$capability_type}s",
                "delete_published_{$capability_type}s",
                "delete_others_{$capability_type}s",
                "edit_private_{$capability_type}s",
                "edit_published_{$capability_type}s",

                // Terms.
                "manage_{$capability_type}_terms",
                "edit_{$capability_type}_terms",
                "delete_{$capability_type}_terms",
                "assign_{$capability_type}_terms",
            );
        }

        return $capabilities;
    }

}
Jws_Streamvid_Install::install();