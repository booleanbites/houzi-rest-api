<?php
add_action( 'rest_api_init', function () {
    register_rest_route( 'houzez-mobile-api/v1', '/create-nonce', array(
    'methods' => 'POST',
    'callback' => 'create_nonce',
  ));
});
function create_nonce() {
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
function nonce_security_enabled() {
    $options = get_option( 'houzi_rest_api_options' ); // Array of All Options
    
    if ($options != null && isset($options['nonce_security_enabled']) && $options['nonce_security_enabled'] === 'nonce_security_enabled' ) {
      // rest_base property should always be properties.
      return true;
    }
    return false;
}
function create_nonce_or_throw_error($request_var, $nonce_var) {
    if (isset($_POST[$request_var]) && !empty($_POST[$request_var])) {
        return true;
    }
    if (nonce_security_enabled()) {
        if (!isset($_POST[$request_var]) || empty($_POST[$request_var])) {
            $ajax_response = array( 'success' => false, 'reason' => 'Security nonce not found, please update app.' );
            wp_send_json($ajax_response, 403);
            return false;
        }
    }
    
    //using the existing theme method.
    $nonce = wp_create_nonce($nonce_var);
    $_POST[$request_var] = $nonce;
    $_REQUEST[$request_var] = $nonce;
    return true;
}