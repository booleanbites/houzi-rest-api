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
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		
		//add_action( 'admin_enqueue_scripts', array( $this, 'houzi_admin_js_hook' ) );
		add_action( 'admin_menu', array( $this, 'houzi_rest_api_add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'houzi_rest_api_page_init' ) );

		add_action('wp_ajax_houzi_lets_eleven', array( $this, 'lets_go_eleven'));
		add_action('wp_ajax_houzi_lets_twelve', array( $this, 'lets_go_twelve'));
	}
	
	function houzi_admin_css_hook( $hook ) {
		wp_enqueue_style( 'custom_wp_admin_css', plugin_dir_url( __FILE__ ) . 'css/houzi-rest-api-admin.css' );
	}
	function houzi_admin_js_hook () {
		wp_enqueue_script( "houzi_wp_admin_css", plugin_dir_url( __FILE__ ) . 'js/houzi-rest-api-admin.js' );
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
		$this->houzi_rest_api_options = get_option( 'houzi_rest_api_options' );
			?>

		<div class="wrap">
			<h2>Houzi Rest Api</h2>
			<p>Extended Rest Api for mobile apps.
			<br/>Developed for <a href="https://houzi.booleanbites.com">Houzi real estate app</a> by <a href="https://houzi.booleanbites.com">BooleanBites.com</a></p>
			<?php settings_errors();
			 $active_tab = 'settings';
			 if ( isset( $_GET['tab'] ) ) {
				$active_tab = $_GET['tab'];
			 }
			?>
			<h2 class="nav-tab-wrapper">
                <a href="?page=<?php echo $_GET['page']; ?>&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
                <a href="?page=<?php echo $_GET['page']; ?>&tab=p_code" class="nav-tab <?php echo $active_tab == 'p_code' ? 'nav-tab-active' : ''; ?>">Purchase Code</a>
            </h2>
			<?php 
			if ( $active_tab == 'settings' ) {
				$this->admin_settings();
			} elseif ( $active_tab == 'p_code' ) {
				$this->eleven_settings();
			}
			
			?>
		</div>
	<?php }

	public function eleven_settings() {
		$houzi_eleven = get_option( 'houzi_eleven' );
		$eleven_code = get_option( 'houzi_eleven_text' );
		?>

		<p><?php esc_html_e('Enter purchase code to verify your purchase', 'houzi'); ?></p>

		<form id="admin-houzi-form" class="admin-houzi-form">
			
			<?php echo wp_nonce_field( 'eleven_nonce', 'eleven_nonce_field' ,true, false ); ?>

			<div class="form-field">
				<?php if( $houzi_eleven == 'elevened' ) { ?>

					<label><?php esc_html_e('Purchase Code', 'houzi'); ?> *</label>
					<?php if( ! empty( $eleven_code ) ) { ?>
					<input id="item_eleven_field" autocomplete="off" readonly class="regular-text" type="text" placeholder="Enter item purchase code." value="<?php echo esc_attr($eleven_code); ?>">
					<?php } ?>
					<input type="hidden" name="action" value="houzi_lets_twelve">
					<p>
					<span class="pc-elevened">Verified</span>
					</p>
				<?php
				} else { ?>
					<label><?php esc_html_e('Purchase Code', 'houzi'); ?> *</label>
					<input id="item_eleven_field" autocomplete="off" class="regular-text" type="text" placeholder="Enter item purchase code.">
					<input type="hidden" name="action" value="houzi_lets_eleven">
				<?php
				} ?>
			</div>

			<div>
				<p>
					You can consult <a target="_blank" href="https://help.market.envato.com/hc/en-us/articles/202822600-Where-Is-My-Purchase-Code-"> this article</a> to learn how to get item purchase code.  
				</p>
			</div>

			<div class="submit">

				<?php if( $houzi_eleven == 'elevened' ) { ?>
					<button id="houzi-twelve-button" type="submit" class="button button-primary"><?php esc_html_e('Deactivate', 'houzi'); ?></button>
				<?php
				} else { ?>
					<button id="houzi-eleven-button" type="submit" class="button button-primary"><?php esc_html_e('Verify Purchase', 'houzi'); ?></button>
				<?php
				} ?>
			</div>

			<div class="form-field" id="form-messages"></div>
		</form>
					
		<?php
	}
	public function lets_go_eleven() {

		$item_eleven_text = sanitize_text_field( $_POST['item_eleven_text'] );

		$nonce = $_POST['nonce'];
        if (!wp_verify_nonce( $nonce, 'eleven_nonce') ) {
            echo json_encode(array(
                'success' => false,
                'msg' => esc_html__('Invalid Nonce!', 'houzi')
            ));
            wp_die();
        }

		if ( ! $item_eleven_text ) {
            echo json_encode(array(
                'success' => false,
                'msg' => esc_html__('Please enter an item purchase code.', 'houzi')
            ));
            wp_die();
        }

        $my_item_id = 15752549;
        $error = new WP_Error();

        $envato_token = 'n3UqTOU50S2rPm17mcPtGsh8nAv9fmU4';
		$header_map            = array();
		$header            = array();
		$header['User-Agent'] = 'Purchase code verification';

        // $apiurl  = "https://api.envato.com/v1/market/private/user/verify-purchase:" . esc_html( $item_eleven_text ) . ".json";
		// $header['Authorization'] = "Bearer " . $envato_token;

		$my_item_id = 17022701;
		$apiurl  = "https://sandbox.bailey.sh/v3/market/author/sale?code=" . esc_html( $item_eleven_text );
        
		$header_map['headers'] = $header;
        $request  = wp_safe_remote_request( $apiurl, $header_map );
		
        if ( ! is_wp_error( $request )) {
			$responseCode = wp_remote_retrieve_response_code($request);
			
			if ( $responseCode == 200 ) {
				$response_body = json_decode(wp_remote_retrieve_body($request), true);

				if ( isset( $response_body['item'] ) ) {
					$purchase_array = (array) $response_body['item']; 
				}

				if ( isset( $purchase_array ) && $my_item_id == $purchase_array['id'] ) {
					update_option( 'houzi_eleven', 'elevened' );
					update_option( 'houzi_eleven_text', sanitize_text_field( $item_eleven_text ) );
					
					echo json_encode(array(
						'success' => true,
						'msg' => esc_html__('Thanks for verifying your purchase!', 'houzi')
					));
					wp_die();
				}
            } else {
                echo json_encode(array(
	                'success' => false,
					'msg' => esc_html__('Invalid purchase code, please provide valid purchase code!', 'houzi')
	            ));
	            wp_die();
            }


        } else {

            echo json_encode(array(
                'success' => false,
                'msg' => esc_html__('There is problem with API connection, try again.', 'houzi')
            ));
            wp_die();
        }

	}
	public function lets_go_twelve() {
		$nonce = $_POST['nonce'];
        if (!wp_verify_nonce( $nonce, 'eleven_nonce') ) {
            echo json_encode(array(
                'success' => false,
                'msg' => esc_html__('Invalid Nonce!', 'houzi')
            ));
            wp_die();
        }

        update_option( 'houzi_eleven', 'none' );
        update_option( 'houzi_eleven_text', '' );

        echo json_encode(array(
            'success' => true,
            'msg' => esc_html__('Deactivated', 'houzi')
        ));
        wp_die();
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



