<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to show settings area of the plugin
 *
 * @link       https://booleanbites.com
 * @since      1.1.5
 *
 * @package    Houzi_Rest_Api
 * @subpackage Houzi_Rest_Api/admin/partials
 * @author Adil Soomro
 * Feb 17, 2023
 */
class RestApiIAPProductIds {
    private $houzi_rest_api_options;

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.1.5
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.1.5
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.1.5
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function add_settings_page() {
        add_options_page(
            'IAP Product IDs',
            'IAP Product IDs',
            'manage_options',
            'iap_product_ids',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting( 'iap_product_ids_group', 'android_featured_product_id' );
        register_setting( 'iap_product_ids_group', 'android_per_listing_product_id' );
        register_setting( 'iap_product_ids_group', 'ios_featured_product_id' );
        register_setting( 'iap_product_ids_group', 'ios_per_listing_product_id' );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>IAP Product IDs</h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'iap_product_ids_group' ); ?>
                <?php do_settings_sections( 'iap_product_ids_group' ); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Make featured product id (Google PlayStore)</th>
                        <td><input type="text" name="android_featured_product_id" value="<?php echo esc_attr( get_option( 'android_featured_product_id' ) ); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Make featured product id (Apple AppStore)</th>
                        <td><input type="text" name="ios_featured_product_id" value="<?php echo esc_attr( get_option( 'ios_featured_product_id' ) ); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Per Listing product id (Google PlayStore)</th>
                        <td><input type="text" name="android_per_listing_product_id" value="<?php echo esc_attr( get_option( 'android_per_listing_product_id' ) ); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Per Listing product id (Apple AppStore)</th>
                        <td><input type="text" name="ios_per_listing_product_id" value="<?php echo esc_attr( get_option( 'ios_per_listing_product_id' ) ); ?>" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
	

}
