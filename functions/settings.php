<?php
/**
 * Houzi preferences and api settings
 *
 * @link https://developer.wordpress.org/plugins/settings/custom-settings-page/
 *
 * @package Houzez Mobile Api
 * @since Houzi 1.2
 * @author Adil Soomro
 */
class MobileApiSettings {
    private $houzez_mobile_api_options;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'houzez_mobile_api_add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'houzez_mobile_api_page_init' ) );
	}

	public function houzez_mobile_api_add_plugin_page() {
		add_menu_page(
			'Houzez Mobile Api', // page_title
			'Houzez Api', // menu_title
			'manage_options', // capability
			'houzez-mobile-api', // menu_slug
			array( $this, 'houzez_mobile_api_create_admin_page' ), // function
			HOUZI_IMAGE.'houzi-logo.svg', // icon_url
			80 // position
		);
	}

	public function houzez_mobile_api_create_admin_page() {
		$this->houzez_mobile_api_options = get_option( 'houzez_mobile_api_options' ); ?>

		<div class="wrap">
			<h2>Houzez Mobile Api</h2>
			<p>Extended Houzez Rest Api for mobile apps.
			<br/>Developed for <a href="https://houzi.booleanbites.com">Houzi real estate app</a> by <a href="https://booleanbites.com">BooleanBites.com</a></p>
			<?php settings_errors(); ?>

			<form method="post" action="options.php">
				<?php
					settings_fields( 'houzez_mobile_api_option_group' );
					do_settings_sections( 'houzez-mobile-api-admin' );
					submit_button();
				?>
			</form>
		</div>
	<?php }

	public function houzez_mobile_api_page_init() {
		register_setting(
			'houzez_mobile_api_option_group', // option_group
			'houzez_mobile_api_options', // option_name
			array( $this, 'houzez_mobile_api_sanitize' ) // sanitize_callback
		);

		add_settings_section(
			'houzez_mobile_api_setting_section', // id
			'Settings', // title
			array( $this, 'houzez_mobile_api_section_info' ), // callback
			'houzez-mobile-api-admin' // page
		);
		/*add_settings_field(
			'onesingnal_app_id_0', // id
			'OneSingnal APP ID', // title
			array( $this, 'onesingnal_app_id_0_callback' ), // callback
			'houzez-mobile-api-admin', // page
			'houzez_mobile_api_setting_section' // section
		);*/
		add_settings_field(
			'fix_property_type_in_translation_0', // id
			'Fix translated property slug', // title
			array( $this, 'fix_property_type_in_translation_0_callback' ), // callback
			'houzez-mobile-api-admin', // page
			'houzez_mobile_api_setting_section' // section
		);
		add_settings_field(
			'mobile_app_config', // id
			'App Config', // title
			array( $this, 'mobile_app_config_callback' ), // callback
			'houzez-mobile-api-admin', // page
			'houzez_mobile_api_setting_section' // section
		);
	}

	public function houzez_mobile_api_sanitize($input) {
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

	public function houzez_mobile_api_section_info() {
		
	}

	public function fix_property_type_in_translation_0_callback() {
		printf(
			'<input type="checkbox" name="houzez_mobile_api_options[fix_property_type_in_translation_0]" id="fix_property_type_in_translation_0" value="fix_property_type_in_translation_0" %s> <label for="fix_property_type_in_translation_0"><br>If you have changed property slug via WPML plugin to another langugage, it also changes the rest api route for property.<br>Check this option to set the property slug to \'property\'.</label>',
			( isset( $this->houzez_mobile_api_options['fix_property_type_in_translation_0'] ) && $this->houzez_mobile_api_options['fix_property_type_in_translation_0'] === 'fix_property_type_in_translation_0' ) ? 'checked' : ''
		);
	}
	public function onesingnal_app_id_0_callback() {
		printf(
			'<input class="regular-text" type="text" name="houzez_mobile_api_options[onesingnal_app_id_0]" id="onesingnal_app_id_0" value="%s" placeholder="xxxxxxxx-xxx-xxxx-xxxx-xxxxxxxxxxxx">',
			isset( $this->houzez_mobile_api_options['onesingnal_app_id_0'] ) ? esc_attr( $this->houzez_mobile_api_options['onesingnal_app_id_0']) : ''
		);
	}
	public function mobile_app_config_callback() {
		printf(
			'<textarea class="large-text" rows="5" placeholder="JSON config from Houzi Config desktop app" name="houzez_mobile_api_options[mobile_app_config]" id="mobile_app_config">%s</textarea>',
			isset( $this->houzez_mobile_api_options['mobile_app_config'] ) ? esc_attr( $this->houzez_mobile_api_options['mobile_app_config']) : ''
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
$settings = new MobileApiSettings();


