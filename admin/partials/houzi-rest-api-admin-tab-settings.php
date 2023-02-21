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
class RestApiAdminSettings {
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
		
		add_action( 'admin_init', array( $this, 'houzi_rest_api_page_init' ) );

		add_action('update_option_houzi_rest_api_options', function( $old_value, $value ) {
			do_action( 'litespeed_purge_all' );
	   	}, 10, 2);
		$this->houzi_rest_api_options = get_option( 'houzi_rest_api_options' );
	}
	
	public function admin_settings() {
		?>
		
		<form method="post" action="options.php">
				<?php
					settings_fields( 'houzi_rest_api_option_group' );
					do_settings_sections( 'houzi-rest-api-admin' );
					submit_button();
				?>
		</form>
			
		<?php
	}

	public function houzi_rest_api_page_init() {
		
		register_setting(
			'houzi_rest_api_option_group', // option_group
			'houzi_rest_api_options', // option_name
			array( $this, 'houzi_rest_api_sanitize' ) // sanitize_callback
		);
		add_settings_section(
			'houzi_rest_api_setting_section', // id
			'Settings', // title
			array( $this, 'houzi_rest_api_section_info' ), // callback
			'houzi-rest-api-admin' // page
		);
		add_settings_field(
			'fix_property_type_in_translation_0', // id
			'Fix translated property slug', // title
			array( $this, 'fix_property_type_in_translation_0_callback' ), // callback
			'houzi-rest-api-admin', // page
			'houzi_rest_api_setting_section' // section
		);
		add_settings_field(
			'nonce_security_disabled', // id
			'NONCE Security', // title
			array( $this, 'nonce_security_check_box_callback' ), // callback
			'houzi-rest-api-admin', // page
			'houzi_rest_api_setting_section' // section
		);
		add_settings_field(
			'app_secret_key', // id
			'App Secret', // title
			array( $this, 'app_secret_field_callback' ), // callback
			'houzi-rest-api-admin', // page
			'houzi_rest_api_setting_section' // section
		);
		if ( SHOW_EXPERIMENTAL_FEATUERS == true) {
			add_settings_field(
				'onesingnal_app_id_0', // id
				'OneSingnal APP ID', // title
				array( $this, 'onesingnal_app_id_0_callback' ), // callback
				'houzi-rest-api-admin', // page
				'houzi_rest_api_setting_section' // section
			);
		}
		add_settings_field(
			'mobile_app_config', // id
			'App Config', // title
			array( $this, 'mobile_app_config_callback' ), // callback
			'houzi-rest-api-admin', // page
			'houzi_rest_api_setting_section' // section
		);
		if (SHOW_EXPERIMENTAL_FEATUERS == true) {
			add_settings_field(
				'mobile_app_config_dev', // id
				'App Config (Dev)', // title
				array( $this, 'mobile_app_config_dev_callback' ), // callback
				'houzi-rest-api-admin', // page
				'houzi_rest_api_setting_section' // section
			);
		}
	}

	public function houzi_rest_api_sanitize($input) {
		$sanitary_values = array();
		if ( isset( $input['fix_property_type_in_translation_0'] ) ) {
			$sanitary_values['fix_property_type_in_translation_0'] = $input['fix_property_type_in_translation_0'];
		}
		if ( isset( $input['onesingnal_app_id_0'] ) ) {
			$sanitary_values['onesingnal_app_id_0'] = sanitize_text_field( $input['onesingnal_app_id_0'] );
		}
		if ( isset( $input['nonce_security_disabled'] ) ) {
			$sanitary_values['nonce_security_disabled'] = sanitize_text_field( $input['nonce_security_disabled'] );
		}
		if ( isset( $input['app_secret_key'] ) ) {
			$sanitary_values['app_secret_key'] = sanitize_text_field( $input['app_secret_key'] );
		}
		if ( isset( $input['mobile_app_config'] ) ) {
			$sanitary_values['mobile_app_config'] = esc_textarea( $input['mobile_app_config'] );
		}
		if ( isset( $input['mobile_app_config_dev'] ) ) {
			$sanitary_values['mobile_app_config_dev'] = esc_textarea( $input['mobile_app_config_dev'] );
		}

		return $sanitary_values;
	}

	public function houzi_rest_api_section_info() {
		
	}
	public function nonce_security_check_box_callback() {
		printf(
			'<input type="checkbox" name="houzi_rest_api_options[nonce_security_disabled]" id="nonce_security_disabled" value="nonce_security_disabled" %s>
			<label for="nonce_security_disabled">
				<br>Disable NONCE Security for POST apis plugin.<br>
			</label>',
			( isset( $this->houzi_rest_api_options['nonce_security_disabled'] ) && $this->houzi_rest_api_options['nonce_security_disabled'] === 'nonce_security_disabled' ) ? 'checked' : ''
		);
	}
	public function app_secret_field_callback() {
		printf(
			'<input class="regular-text" type="text" name="houzi_rest_api_options[app_secret_key]" id="app_secret_key" value="%s" placeholder="Enter a secret key">
			<label for="app_secret_key">
				<br>This will be matched with secret key sent from app.<br>So make sure to add this secret key in header hook in your app source.<br>
			</label>
			',
			isset( $this->houzi_rest_api_options['app_secret_key'] ) ? esc_attr( $this->houzi_rest_api_options['app_secret_key']) : ''
		);
	}
	public function fix_property_type_in_translation_0_callback() {
		printf(
			'<input type="checkbox" name="houzi_rest_api_options[fix_property_type_in_translation_0]" id="fix_property_type_in_translation_0" value="fix_property_type_in_translation_0" %s> <label for="fix_property_type_in_translation_0"><br>If you have changed property slug via WPML plugin to another langugage, it also changes the rest api route for property.<br>Check this option to set the property slug to \'property\'.</label>',
			( isset( $this->houzi_rest_api_options['fix_property_type_in_translation_0'] ) && $this->houzi_rest_api_options['fix_property_type_in_translation_0'] === 'fix_property_type_in_translation_0' ) ? 'checked' : ''
		);
	}
	public function onesingnal_app_id_0_callback() {
		printf(
			'<input class="regular-text" type="text" name="houzi_rest_api_options[onesingnal_app_id_0]" id="onesingnal_app_id_0" value="%s" placeholder="xxxxxxxx-xxx-xxxx-xxxx-xxxxxxxxxxxx">',
			isset( $this->houzi_rest_api_options['onesingnal_app_id_0'] ) ? esc_attr( $this->houzi_rest_api_options['onesingnal_app_id_0']) : ''
		);
	}
	public function mobile_app_config_callback() {
		printf(
			'<textarea class="large-text" rows="5" placeholder="JSON config from Houzi Config desktop app" name="houzi_rest_api_options[mobile_app_config]" id="mobile_app_config">%s</textarea>',
			isset( $this->houzi_rest_api_options['mobile_app_config'] ) ? esc_attr( $this->houzi_rest_api_options['mobile_app_config']) : ''
		);
	}
	public function mobile_app_config_dev_callback() {
		printf(
			'<textarea class="large-text" rows="15" placeholder="JSON config from Houzi Config desktop app" name="houzi_rest_api_options[mobile_app_config_dev]" id="mobile_app_config_dev">%s</textarea>',
			isset( $this->houzi_rest_api_options['mobile_app_config_dev'] ) ? esc_attr( $this->houzi_rest_api_options['mobile_app_config_dev']) : ''
		);
	}
	

}
