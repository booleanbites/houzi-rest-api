<?php

/**
 * The plugin bootstrap file
 *
 *
 * @link              https://booleanbites.com
 * @since             1.0.0
 * @package           Houzi_Rest_Api
 *
 * @wordpress-plugin
 * Plugin Name:       Houzi Rest Api
 * Plugin URI:        https://houzi.booleanbites.com
 * Description:       Enhance Rest Api for Houzi mobile apps.
 * Version:           1.2.0
 * Author:            BooleanBites Ltd.
 * Author URI:        https://booleanbites.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       houzi-rest-api
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'HOUZI_REST_API_VERSION', '1.2.0' );
define( 'HOUZI_REST_API_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'HOUZI_IMAGE', plugins_url('/images/', __FILE__) );
define( 'SHOW_EXPERIMENTAL_FEATUERS', false );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-houzi-rest-api-activator.php
 */
function activate_houzi_rest_api() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-houzi-rest-api-activator.php';
	Houzi_Rest_Api_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-houzi-rest-api-deactivator.php
 */
function deactivate_houzi_rest_api() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-houzi-rest-api-deactivator.php';
	Houzi_Rest_Api_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_houzi_rest_api' );
register_deactivation_hook( __FILE__, 'deactivate_houzi_rest_api' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-houzi-rest-api.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_houzi_rest_api() {

	$plugin = new Houzi_Rest_Api();
	$plugin->run();

}
run_houzi_rest_api();
