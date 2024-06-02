<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://booleanbites.com
 * @since      1.1.5
 *
 * @package    Houzi_Rest_Api
 * @subpackage Houzi_Rest_Api/admin/partials
 * @author Adil Soomro
 * Feb 17, 2023
 */
class RestApiElevenSettings {
    
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

		add_action('wp_ajax_houzi_lets_eleven', array( $this, 'lets_go_eleven'));
		add_action('wp_ajax_houzi_lets_twelve', array( $this, 'lets_go_twelve'));
		
		add_action( 'rest_api_init', function () {
			register_rest_route( 'houzez-mobile-api/v1', '/eleven-config', array(
				'methods' => 'POST',
				'callback' => array( $this, 'lets_eleven_config'),
				'permission_callback' => '__return_true'
			));
		});

		add_action( 'rest_api_init', function () {
			register_rest_route( 'houzez-mobile-api/v1', '/create-eleven-nonce', array(
				'methods' => 'POST',
				'callback' => array( $this, 'create_eleven_nonce'),
				'permission_callback' => '__return_true'
			));
		});
	}

	private function is_elevened() {
		$houzi_eleven = get_option( 'houzi_eleven' );
		$eleven_text = get_option( 'houzi_eleven_text' );
		return !empty($houzi_eleven) && !empty($eleven_text);
	}
	public function eleven_settings() {
		$houzi_eleven = get_option( 'houzi_eleven' );
		$eleven_text = get_option( 'houzi_eleven_text' );
		?>

		<p><?php esc_html_e('Enter purchase code to verify your purchase', 'houzi'); ?></p>

		<form id="admin-houzi-form" class="admin-houzi-form">
			
			<?php echo wp_nonce_field( 'eleven_nonce', 'eleven_nonce_field' ,true, false ); ?>

			<div class="form-field">
				<?php if( $houzi_eleven == 'elevened' ) { ?>

					<label><?php esc_html_e('Purchase Code', 'houzi'); ?> *</label>
					<?php if( ! empty( $eleven_text ) ) { ?>
					<input id="item_eleven_field" autocomplete="off" readonly class="regular-text" type="text" placeholder="Enter item purchase code." value="<?php echo esc_attr($eleven_text); ?>">
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
					<div>
						<p>
							Activate your plugin by entering your purchase code. Activation allows you to continuous upgrade, seamlsess api integration and support.
							You can consult <a target="_blank" href="https://help.market.envato.com/hc/en-us/articles/202822600-Where-Is-My-Purchase-Code-"> this article</a> to learn how to get item purchase code.  
						</p>
						
					</div>
				<?php
				} ?>
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
	public function lets_eleven_config() {
		$this->lets_go_eleven();
	}
	public function lets_go_eleven() {

		$item_eleven_text = sanitize_text_field( $_POST['item_eleven_text'] );
		$nonce = $_POST['nonce'];

        if (!wp_verify_nonce( $nonce, 'eleven_nonce') ) {
            wp_send_json(array(
                'success' => false,
                'msg' => esc_html__('Invalid Nonce!', 'houzi')
            ));
            
        }

		if ( ! $item_eleven_text ) {
            wp_send_json(array(
                'success' => false,
                'msg' => esc_html__('Please enter an item purchase code.', 'houzi')
            ));
            
        }
		$tree = $_POST['tree'];
		$debug = $_POST['debug'] ?? "";

        $error = new WP_Error();
        
		$header_map            = array();
		$header            = array();
		$header['User-Agent'] = 'Purchase code verification';
		
		$my_item_id = 39753350;
        $apiurl  = "https://api.envato.com/v3/market/author/sale?code=" . esc_html( $item_eleven_text );
		$envato_token = 'DTEBcnRdUOmvIUkQLCi6YK6C1m20NTwn';

		if (!empty($tree) && $tree == 'pine') {
			$my_item_id = 17022701;
			$apiurl  = "https://sandbox.bailey.sh/v3/market/author/sale?code=" . esc_html( $item_eleven_text );
        	$envato_token = 'cFAKETOKENabcREPLACEMExyzcdefghj';
		}

		$header['Authorization'] = "Bearer " . $envato_token;
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
					
					wp_send_json(array(
						'success' => true,
						'msg' => esc_html__('Thanks for verifying your purchase!', 'houzi')
					));
					
				}
            } else {
				if ($debug == 'true') {
					wp_send_json(array(
						'success' => false,
						'msg' => esc_html__('Invalid purchase code, please provide valid purchase code!', 'houzi'),
						'debug' => $request
					));
				} else {
                wp_send_json(array(
	                'success' => false,
					'msg' => esc_html__('Invalid purchase code, please provide valid purchase code!', 'houzi')
	            ));
				}
	            
            }


        } else {

            wp_send_json(array(
                'success' => false,
                'msg' => esc_html__('There is problem with API connection, try again.', 'houzi')
            ));
            
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
	public function create_eleven_nonce($request) {

		if(!isset( $_POST['nonce_name']) || empty($_POST['nonce_name']) ) {
			$ajax_response = array( 'success' => false, 'reason' => 'Please provide nonce_name' );
			wp_send_json($ajax_response, 403);
			return;
		}

		$nonce_name = $_POST['nonce_name'];
		$nonce = wp_create_nonce($nonce_name);
		$ajax_response = array( 'success' => true, 'nonce' => $nonce );
		wp_send_json($ajax_response, 200);
	}
}