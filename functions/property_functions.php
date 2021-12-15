<?php

// houzez-mobile-api/v1/search-properties
add_action( 'rest_api_init', function () {
    
    register_rest_route( 'houzez-mobile-api/v1', '/add-property', array(
      'methods' => 'POST',
      'callback' => 'addProperty',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/save-property', array(
        'methods' => 'POST',
        'callback' => 'addPropertyWithAuth',
      ));
    
    register_rest_route( 'houzez-mobile-api/v1', '/delete-property', array(
        'methods' => 'GET',
        'callback' => 'deleteProperty',
      ));
  
    register_rest_route( 'houzez-mobile-api/v1', '/update-property', array(
      'methods' => 'POST',
      'callback' => 'addPropertyWithAuth',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/upload-property-image', array(
        'methods' => 'POST',
        'callback' => 'uploadPropertyImage',
      ));
    register_rest_route( 'houzez-mobile-api/v1', '/save-property-image', array(
        'methods' => 'POST',
        'callback' => 'uploadPropertyImageWithAuth',
    ));
    register_rest_route( 'houzez-mobile-api/v1', '/delete-property-image', array(
        'methods' => 'POST',
        'callback' => 'deleteImageForProperty',
    ));

    

    register_rest_route( 'houzez-mobile-api/v1', '/like-property', array(
        'methods' => 'POST',
        'callback' => 'likeProperty',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/is-fav-property', array(
        'methods' => 'GET',
        'callback' => 'isFavProperty',
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

function addPropertyWithAuth() {
    
    $new_property['post_status']    = 'publish';
    $new_property                   = apply_filters( 'houzez_submit_listing', $new_property );
    houzez_update_property_from_draft( $new_property ); 
    wp_send_json(['prop_id' => $new_property ],200);
}

function deleteProperty() {
    
    if ( !isset( $_REQUEST['prop_id'] ) ) {
        $ajax_response = array( 'success' => false , 'reason' => esc_html__( 'No Property ID found', 'houzez' ) );
        echo json_encode( $ajax_response );
        die;
    }

    $propID = $_REQUEST['prop_id'];
    $post_author = get_post_field( 'post_author', $propID );

    global $current_user;
    wp_get_current_user();
    $userID      =   $current_user->ID;

    if ( $post_author == $userID || current_user_can( 'delete_post', $propID )) {

        if( get_post_status($propID) != 'draft' ) {
            houzez_delete_property_attachments_frontend($propID);
        }
        wp_delete_post( $propID );
        $ajax_response = array( 'success' => true , 'mesg' => esc_html__( 'Property Deleted', 'houzez' ) );
        wp_send_json( $ajax_response );
        
    } else {
        $ajax_response = array( 'success' => false , 'reason' => esc_html__( 'Permission denied', 'houzez' ) );
        wp_send_json( $ajax_response );
        
    }
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
function uploadPropertyImageWithAuth(){
    
    if(!isset( $_FILES['property_upload_file']) ) {
        $ajax_response = array( 'success' => false, 'reason' => 'Please provide property_upload_file' );
        
        wp_send_json($ajax_response, 400);
        return;
    }

    //create nonce
    $nonce = wp_create_nonce('verify_gallery_nonce');
    $_REQUEST['verify_nonce'] = $nonce;
    require_once(ABSPATH . "wp-admin" . '/includes/image.php');
    houzez_property_img_upload();
}

function deleteImageForProperty() {
    if (! is_user_logged_in() ) {
        $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
        wp_send_json($ajax_response, 403);
        return; 
    }
    if(! isset( $_POST['thumb_id'] ) ) {
        $ajax_response = array( 'success' => false, 'reason' => 'Please provide thumb_id' );
        wp_send_json($ajax_response, 400);
        return;
    }
    if(! isset( $_POST['prop_id'] ) ) {
        $ajax_response = array( 'success' => false, 'reason' => 'Please provide prop_id' );
        wp_send_json($ajax_response, 400);
        return;
    }
    
    $nonce = wp_create_nonce('verify_gallery_nonce');
    $_POST['removeNonce'] = $nonce;
    
    do_action('wp_ajax_houzez_remove_property_thumbnail');
}

//like or unlike | favorite or un favorite a property.
function likeProperty() {
    
    if ( !isset( $_POST['listing_id'] ) ) {
        $ajax_response = array( 'success' => false , 'reason' => esc_html__( 'No Property ID found', 'houzez' ) );
        wp_send_json($ajax_response,400);
        return;
    }
    if (! is_user_logged_in() ) {
        $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
        wp_send_json($ajax_response, 403);
        return; 
    }
    do_action('wp_ajax_houzez_add_to_favorite');
}

function isFavProperty() {
    
    if ( !isset( $_REQUEST['listing_id'] ) ) {
        $ajax_response = array( 'success' => false , 'reason' => esc_html__( 'No Property ID (listing_id) found', 'houzez' ) );
        wp_send_json($ajax_response,400);
        return;
    }
    if (! is_user_logged_in() ) {
        $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
        wp_send_json($ajax_response, 403);
        return; 
    }

    $ajax_response = array( 'success' => true, 'is_fav' => isFavoriteProperty($_REQUEST['listing_id']) );
    wp_send_json($ajax_response, 200);

}