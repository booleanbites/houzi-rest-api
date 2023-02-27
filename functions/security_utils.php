<?php
add_action( 'rest_api_init', function () {
    register_rest_route( 'houzez-mobile-api/v1', '/create-nonce', array(
    'methods' => 'POST',
    'callback' => 'create_nonce',
  ));
});
function create_nonce($request) {
    if(!isset( $_POST['nonce_name']) || empty($_POST['nonce_name']) ) {
        $ajax_response = array( 'success' => false, 'reason' => 'Please provide nonce_name' );
        wp_send_json($ajax_response, 403);
        return;
    }
    
    $app_secret = $request->get_header("app-secret");
    $saved_app_secret = get_saved_app_secret();
    
    if (empty($app_secret) && !empty($saved_app_secret)) {
        $ajax_response = array( 'success' => false, 'reason' => 'Please provide app secret key in headers. Set app secret in header hook in hooks_v2.dart' );
        wp_send_json($ajax_response, 403);
        return;
    }

    if ($app_secret != $saved_app_secret) {
        $ajax_response = array( 'success' => false, 'reason' => 'app secret mismatch. Please check if plugin and hook secret are same' );
        wp_send_json($ajax_response, 403);
        return;
    }

    $nonce_name = $_POST['nonce_name'];
    $nonce = wp_create_nonce($nonce_name);
    $ajax_response = array( 'success' => true, 'nonce' => $nonce );
    wp_send_json($ajax_response, 200);
}
function nonce_security_enabled() {
    $options = get_option( 'houzi_rest_api_options' ); // Array of All Options
    
    if ($options != null && isset($options['nonce_security_disabled']) && $options['nonce_security_disabled'] === 'nonce_security_disabled' ) {
      // rest_base property should always be properties.
      return false;
    }
    return true;
}
function get_saved_app_secret() {
    $options = get_option( 'houzi_rest_api_options' ); // Array of All Options
    
    if ($options != null && isset($options['app_secret_key']) ) {
      // rest_base property should always be properties.
      return $options['app_secret_key'];
    }
    return "";
}
function create_nonce_or_throw_error($request_var, $nonce_var) {
    if (isset($_REQUEST[$request_var]) && !empty($_REQUEST[$request_var])) {
        return true;
    }
    if (nonce_security_enabled()) {
    
        $ajax_response = array( 'success' => false, 'reason' => 'Security nonce not found, please update app.' );
        wp_send_json($ajax_response, 403);
        return false;
    
    }
    
    //using the existing theme method.
    $nonce = wp_create_nonce($nonce_var);
    $_POST[$request_var] = $nonce;
    $_REQUEST[$request_var] = $nonce;
    return true;
}