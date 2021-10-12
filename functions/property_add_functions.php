<?php

// houzez-mobile-api/v1/search-properties
add_action( 'rest_api_init', function () {
    
    register_rest_route( 'houzez-mobile-api/v1', '/add-property', array(
      'methods' => 'POST',
      'callback' => 'addProperty',
    ));
  
    register_rest_route( 'houzez-mobile-api/v1', '/update-property', array(
      'methods' => 'POST',
      'callback' => 'addProperty',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/upload-property-image', array(
        'methods' => 'POST',
        'callback' => 'uploadPropertyImage',
      ));
  
  });

function addProperty(){
    if(! isset( $_POST['user_id'] ) ) {
        $ajax_response = array( 'success' => false, 'reason' => 'Please provide user_id' );
        wp_send_json($ajax_response, 400);
        return;
    }
    $user_id  = $_POST['user_id'];
    $user = get_user_by( 'id', $user_id ); 
    if( $user ) {
        wp_set_current_user( $user_id, $user->user_login );
        wp_set_auth_cookie( $user_id );
        do_action( 'wp_login', $user->user_login, $user );
    }
    $new_property['post_status']    = 'publish';
    $new_property                   = apply_filters( 'houzez_submit_listing', $new_property );
    houzez_update_property_from_draft( $new_property ); 
    wp_send_json(['prop_id' => $new_property ],200);
}

//upload an image that can be used to add in a property.
function uploadPropertyImage(){
    
    if(!isset( $_FILES['property_upload_file']) ) {
        $ajax_response = array( 'success' => false, 'reason' => 'Please provide property_upload_file' );
        
        wp_send_json($ajax_response, 400);
        return;
    }
    if(! isset( $_POST['user_id'] ) ) {
        $ajax_response = array( 'success' => false, 'reason' => 'Please provide user_id' );
        wp_send_json($ajax_response, 400);
        return;
    }
    $user = get_user_by( 'id', $user_id ); 
    if( $user ) {
        wp_set_current_user( $user_id, $user->user_login );
        wp_set_auth_cookie( $user_id );
        do_action( 'wp_login', $user->user_login, $user );
    }

    //create nonce
    $nonce = wp_create_nonce('verify_gallery_nonce');
    $_REQUEST['verify_nonce'] = $nonce;
    require_once(ABSPATH . "wp-admin" . '/includes/image.php');
    houzez_property_img_upload();
}


