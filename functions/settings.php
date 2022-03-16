<?php

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
			'dashicons-rest-api', // icon_url
			80 // position
		);
	}

	public function houzez_mobile_api_create_admin_page() {
		$this->houzez_mobile_api_options = get_option( 'houzez_mobile_api_options' ); ?>

		<div class="wrap">
			<h2>Houzez Mobile Api</h2>
			<p>Extended Houzez Rest Api for mobile apps</p>
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

		add_settings_field(
			'fix_property_type_in_translation_0', // id
			'Fix property type in translation', // title
			array( $this, 'fix_property_type_in_translation_0_callback' ), // callback
			'houzez-mobile-api-admin', // page
			'houzez_mobile_api_setting_section' // section
		);
	}

	public function houzez_mobile_api_sanitize($input) {
		$sanitary_values = array();
		if ( isset( $input['fix_property_type_in_translation_0'] ) ) {
			$sanitary_values['fix_property_type_in_translation_0'] = $input['fix_property_type_in_translation_0'];
		}

		return $sanitary_values;
	}

	public function houzez_mobile_api_section_info() {
		
	}

	public function fix_property_type_in_translation_0_callback() {
		printf(
			'<input type="checkbox" name="houzez_mobile_api_options[fix_property_type_in_translation_0]" id="fix_property_type_in_translation_0" value="fix_property_type_in_translation_0" %s> <label for="fix_property_type_in_translation_0">When WPML plugin is active, it changes the rest api route for property. Check this option to fix this issue.</label>',
			( isset( $this->houzez_mobile_api_options['fix_property_type_in_translation_0'] ) && $this->houzez_mobile_api_options['fix_property_type_in_translation_0'] === 'fix_property_type_in_translation_0' ) ? 'checked' : ''
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


