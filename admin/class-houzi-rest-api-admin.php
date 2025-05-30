<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://booleanbites.com
 * @since      1.0.0
 *
 * @package    Houzi_Rest_Api
 * @subpackage Houzi_Rest_Api/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Houzi_Rest_Api
 * @subpackage Houzi_Rest_Api/admin
 * @author     BooleanBites Ltd. <houzi@booleanbites.com>
 */
class Houzi_Rest_Api_Admin
{

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct($plugin_name, $version)
	{

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles()
	{

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Houzi_Rest_Api_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Houzi_Rest_Api_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */
		wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/houzi-rest-api-admin.css', array(), $this->version, 'all');

		// wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/houzi-rest-api-admin.css', array(), rand(111,9999), 'all', 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts()
	{

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Houzi_Rest_Api_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Houzi_Rest_Api_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/houzi-rest-api-admin.js', array('jquery'), $this->version, false);
		// wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/houzi-rest-api-admin.js', array( 'jquery' ), rand(111,9999), false );
		wp_localize_script(
			$this->plugin_name,
			'houzi_admin_vars',
			array(
				'ajaxurl' => admin_url('admin-ajax.php'),
				'paid_status' => esc_html__('Paid', 'houzez'),
				'activate_now' => esc_html__('Activate Now', 'houzez'),
				'activating' => esc_html__('Activating...', 'houzez'),
				'activated' => esc_html__('Activated!', 'houzez'),
				'install_now' => esc_html__('Install Now', 'houzez'),
				'installing' => esc_html__('Installing...', 'houzez'),
				'installed' => esc_html__('Installed!', 'houzez'),
				'active' => esc_html__('Active', 'houzez'),
				'failed' => esc_html__('Failed!', 'houzez'),
			)
		);


	}
	public function load_admin_settings()	{
		require_once( HOUZI_REST_API_PLUGIN_PATH . 'admin/class-rest-api-settings.php');
		require_once( HOUZI_REST_API_PLUGIN_PATH . 'admin/partials/houzi-rest-api-admin-eleven.php');
		require_once( HOUZI_REST_API_PLUGIN_PATH . 'admin/partials/houzi-rest-api-admin-tab-settings.php');
		require_once( HOUZI_REST_API_PLUGIN_PATH . 'admin/partials/houzi-rest-api-admin-tab-iap-ids.php');
		require_once( HOUZI_REST_API_PLUGIN_PATH . 'admin/partials/houzi-rest-api-admin-notify.php');
		require_once( HOUZI_REST_API_PLUGIN_PATH . 'admin/partials/houzi-rest-api-admin-contact-settings.php');
		$settings = new RestApiSettings($this->plugin_name,$this->version);
	}

}