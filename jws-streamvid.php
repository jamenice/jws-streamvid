<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://jwsuperthemes.com
 * @since             8.7.0
 * @package           Jws_Streamvid
 *
 * @wordpress-plugin
 * Plugin Name:       Jws Streamvid
 * Plugin URI:        https://streamvid.jwsuperthemes.com
 * Description:       Jws Streamvid is a key plugin for the StreamVid theme, providing essential functions and core features that power video streaming, user interaction, and theme-specific functionality. It ensures the StreamVid theme operates smoothly with all required integrations and custom features.
 * Version:           8.7.0
 * Author:            JWSThemes team
 * Author URI:        https://jwsuperthemes.com 
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       jws_streamvid
 * Domain Path:       /languages
 * Requires at least: 4.9
 * Requires PHP: 5.4.4
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 8.7.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'JWS_STREAMVID_VERSION', '8.7.0' );
define( 'JWS_STREAMVID_PATH', trailingslashit( plugin_dir_path( __FILE__ ) ) . 'public' );
define( 'JWS_STREAMVID_PATH_PUBLIC', trailingslashit( plugin_dir_path( __FILE__ ) ) . 'public' );
define( 'JWS_STREAMVID_URL_PUBLIC', trailingslashit( plugin_dir_url( __FILE__ ) ) . 'public' );
define( 'JWS_STREAMVID_URL_PUBLIC_ASSETS', trailingslashit( plugin_dir_url( __FILE__ ) ) . 'public/assets' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-jws-streamvid-activator.php
 */
function activate_jws_streamvid() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-jws-streamvid-activator.php';
	Jws_Streamvid_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-jws-streamvid-deactivator.php
 */
function deactivate_jws_streamvid() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-jws-streamvid-deactivator.php';
	Jws_Streamvid_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_jws_streamvid' );
register_deactivation_hook( __FILE__, 'deactivate_jws_streamvid' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-jws-streamvid.php';

/**
 * Front-end player engine switch: Video.js 7 (legacy) or Video.js 10.
 * Loaded before the public hooks run so enqueues and markup agree.
 * Chosen in Theme Options > Video Global > Player Engine.
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-jws-streamvid-player-engine.php';

/**
 * Short drama: its own post types, coin wallet and admin screens.
 * Everything it needs lives in includes/drama/.
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/drama/class-drama.php';
Jws_Drama::boot();


include_once( 'check_update.php' );

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function jws_streamvid() {
    $GLOBALS['jws_streamvid'] = new Jws_Streamvid();
	return $GLOBALS['jws_streamvid'];
}
jws_streamvid()->run();
