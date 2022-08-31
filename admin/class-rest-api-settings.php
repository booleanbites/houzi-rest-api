<?php
/**
 * Houzi preferences and api settings
 *
 *
 * @package Houzi Rest Api
 * @since Houzi 1.1.3
 * @author Adil Soomro
 */
class RestApiSettings {
    private $houzi_rest_api_options;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'houzi_rest_api_add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'houzi_rest_api_page_init' ) );
	}

	public function houzi_rest_api_add_plugin_page() {
		add_menu_page(
			'Houzi Rest Api', // page_title
			'Houzi Api', // menu_title
			'manage_options', // capability
			'houzi-rest-api', // menu_slug
			array( $this, 'houzi_rest_api_create_admin_page' ), // function
			HOUZI_IMAGE.'houzi-logo.svg', // icon_url
			80 // position
		);
	}

	public function houzi_rest_api_create_admin_page() {
		$this->houzi_rest_api_options = get_option( 'houzi_rest_api_options' ); ?>

		<div class="wrap">
			<h2>Houzi Rest Api</h2>
			<p>Extended Rest Api for mobile apps.
			<br/>Developed for <a href="https://houzi.booleanbites.com">Houzi real estate app</a> by <a href="https://houzi.booleanbites.com">BooleanBites.com</a></p>
			<?php settings_errors(); ?>

			<form method="post" action="options.php">
				<?php
					settings_fields( 'houzi_rest_api_option_group' );
					do_settings_sections( 'houzi-rest-api-admin' );
					submit_button();
				?>
			</form>
		</div>
	<?php }

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
		/*add_settings_field(
			'onesingnal_app_id_0', // id
			'OneSingnal APP ID', // title
			array( $this, 'onesingnal_app_id_0_callback' ), // callback
			'houzi-rest-api-admin', // page
			'houzi_rest_api_setting_section' // section
		);*/
		add_settings_field(
			'fix_property_type_in_translation_0', // id
			'Fix translated property slug', // title
			array( $this, 'fix_property_type_in_translation_0_callback' ), // callback
			'houzi-rest-api-admin', // page
			'houzi_rest_api_setting_section' // section
		);
		add_settings_field(
			'mobile_app_config', // id
			'App Config', // title
			array( $this, 'mobile_app_config_callback' ), // callback
			'houzi-rest-api-admin', // page
			'houzi_rest_api_setting_section' // section
		);
	}

	public function houzi_rest_api_sanitize($input) {
		$sanitary_values = array();
		if ( isset( $input['fix_property_type_in_translation_0'] ) ) {
			$sanitary_values['fix_property_type_in_translation_0'] = $input['fix_property_type_in_translation_0'];
		}
		if ( isset( $input['onesingnal_app_id_0'] ) ) {
			$sanitary_values['onesingnal_app_id_0'] = sanitize_text_field( $input['onesingnal_app_id_0'] );
		}
		if ( isset( $input['mobile_app_config'] ) ) {
			$sanitary_values['mobile_app_config'] = esc_textarea( $input['mobile_app_config'] );
		}
		

		return $sanitary_values;
	}

	public function houzi_rest_api_section_info() {
		
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
	

}

/**
 * Instantiate the Class only when admin
 *
 * @since     1.1.3
 * @global    object
 */
if ( is_admin() )
$settings = new RestApiSettings();


