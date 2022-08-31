<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://booleanbites.com
 * @since      1.0.0
 *
 * @package    Houzi_Rest_Api
 * @subpackage Houzi_Rest_Api/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Houzi_Rest_Api
 * @subpackage Houzi_Rest_Api/includes
 * @author     BooleanBites Ltd. <houzi@booleanbites.com>
 */
class Houzi_Rest_Api_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'houzi-rest-api',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
