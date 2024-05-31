<?php
/**
 * Functions to add delete update favorite properties.
 *
 *
 * @package Houzez Mobile Api
 * @since Houzi 1.0
 * @author Adil Soomro
 */

//-----------------------------Lightspeed exclude URLs-------------------------------------
// By default all POST URLs aren't cached
add_action( 'litespeed_init', function() {

    //these URLs need to be excluded from lightspeed caches
    $exclude_url_list = array(
        "delete-property",
        "is-fav-property",
        "my-properties",
        "print-pdf-property"
    );
    foreach ($exclude_url_list as $exclude_url) {
        if (strpos($_SERVER['REQUEST_URI'], $exclude_url) !== FALSE) {
            do_action( 'litespeed_control_set_nocache', 'no-cache for rest api' );
        }
    }

    //add these URLs to cache if required (even POSTs)
    $include_url_list = array(
        "sample-url",
    );
    foreach ($include_url_list as $include_url) {
        if (strpos($_SERVER['REQUEST_URI'], $exclude_url) !== FALSE) {
            do_action( 'litespeed_control_set_cacheable', 'cache for rest api' );
        }
    }

});

// houzez-mobile-api/v1/search-properties
add_action( 'rest_api_init', function () {
    
    // register_rest_route( 'houzez-mobile-api/v1', '/add-property', array(
    //   'methods' => 'POST',
    //   'callback' => 'addProperty',
    // ));

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

    // register_rest_route( 'houzez-mobile-api/v1', '/upload-property-image', array(
    //     'methods' => 'POST',
    //     'callback' => 'uploadPropertyImage',
    //   ));
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

    register_rest_route( 'houzez-mobile-api/v1', '/my-properties', array(
        'methods' => 'GET',
        'callback' => 'getMyProperties',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/property', array(
        'methods' => 'GET',
        'callback' => 'getProperty',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/property-by-permalink', array(
        'methods' => 'GET',
        'callback' => 'getPropertyByPermalink',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/print-pdf-property', array(
        'methods' => 'GET',
        'callback' => 'printPdfProperty',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/property-by-meta-key', array(
        'methods' => 'GET',
        'callback' => 'getPropertyByMetaKey',
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
    
    // $floor_plans_post = $_POST['floor_plans'];
    // if( ! empty( $floor_plans_post ) ) {
    //     $_POST['floor_plans'] = serialize($floor_plans_post);
    // }
    // $floor_plans_post = $_POST['additional_features'];
    // if( ! empty( $floor_plans_post ) ) {
    //     $_POST['additional_features'] = serialize($floor_plans_post);
    // }
    // $floor_plans_post = $_POST['fave_multi_units'];
    // if( ! empty( $floor_plans_post ) ) {
    //     $_POST['fave_multi_units'] = serialize($floor_plans_post);
    // }

    // $nonce = wp_create_nonce('add_property_nonce');
    // $_REQUEST['verify_add_prop_nonce'] = $nonce;

    if (!create_nonce_or_throw_error('verify_add_prop_nonce', 'add_property_nonce')) {
        return;
    }
    $nonce = $_POST['verify_add_prop_nonce'];
    if ( ! wp_verify_nonce( $nonce, 'add_property_nonce' ) ) {
        $ajax_response = array( 'success' => false , 'reason' => esc_html__( 'Security nonce check failed!', 'houzi' ) );
        wp_send_json($ajax_response, 403);
        return;
    }

    $new_property = apply_filters( 'houzez_submit_listing', $new_property );
    houzez_update_property_from_draft( $new_property );

    //if fave_multi_units_ids was set, update property meta data.
    if( isset( $_POST['fave_multi_units_ids'] ) ) {
        update_post_meta( $new_property, 'fave_multi_units_ids', sanitize_text_field( $_POST['fave_multi_units_ids'] ) );
    }
    $response_editing = 'false';
    if( isset( $_POST['prop_id'] ) && !empty( $_POST['prop_id'] ) ){
        //purge light-speed cache for this property post type.
        do_action( 'litespeed_purge_post', $_POST['prop_id'] );
        $response_editing = 'true';
    }

    $admin_email = get_option('admin_email');
    $property_id = $new_property;
    $email_type = '';

    global $current_user;
    wp_get_current_user();
    $userEmail = $current_user->user_email;

    if ($response_editing == 'true') {
        $email_type = 'admin_update_listing';
    } else {
        $email_type = 'admin_free_submission_listing';
    }

    $args = array(
        'listing_title' => get_the_title($property_id),
        'listing_id' => $property_id,
        'listing_url' => get_permalink($property_id)
    );

    houzez_email_type($admin_email, $email_type, $args);
    
    wp_send_json(['prop_id' => $new_property, 'purged' => $response_editing ],200);
}

function deleteProperty() {
    do_action( 'litespeed_control_set_nocache', 'nocache due to logged in' );
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

        //purge light-speed cache for this property post type.
        do_action( 'litespeed_purge_post', $propID );

        $ajax_response = array( 'success' => true , 'mesg' => esc_html__( 'Property Deleted', 'houzez' ) );
        wp_send_json( $ajax_response );
        
    } else {
        $ajax_response = array( 'success' => false , 'reason' => esc_html__( 'Permission denied', 'houzez' ) );
        wp_send_json( $ajax_response );
        
    }
}

//upload an image that can be used to add in a property.
function uploadPropertyImageWithAuth(){
    
    if(!isset( $_FILES['property_upload_file']) ) {
        $ajax_response = array( 'success' => false, 'reason' => 'Please provide property_upload_file' );
        
        wp_send_json($ajax_response, 400);
        return;
    }

    //create nonce
    // $nonce = wp_create_nonce('verify_gallery_nonce');
    // $_REQUEST['verify_nonce'] = $nonce;

    if (!create_nonce_or_throw_error('verify_nonce', 'verify_gallery_nonce')) {
        return;
      }

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
    //purge light-speed cache for this property post type.
    do_action( 'litespeed_purge_post', $_POST['prop_id'] );

    // $nonce = wp_create_nonce('verify_gallery_nonce');
    // $_POST['removeNonce'] = $nonce;

    if (!create_nonce_or_throw_error('removeNonce', 'verify_gallery_nonce')) {
        return;
    }
    
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

function printPdfProperty() {
    if ( !isset( $_GET['propid'] ) ) {
        $ajax_response = array( 'success' => false , 'reason' => esc_html__( 'No Property ID found', 'houzez' ) );
        wp_send_json($ajax_response,400);
        return;
    }

    $_POST['propid'] = $_GET["propid"];

    // do_action( 'wp_ajax_nopriv_houzez_create_print');
    do_action( 'wp_ajax_houzez_create_print');
}

function getMyProperties() {
    if (! is_user_logged_in() ) {
        $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
        wp_send_json($ajax_response, 403);
        return; 
    }

    $userID         = get_current_user_id();
    $qry_status = 'any';

    if( isset( $_GET['status'] ) && !empty( $_GET['status'] )) {
        $qry_status = $_GET['status'];
    }

    $sortby = '';
    if( isset( $_GET['sortby'] ) ) {
        $sortby = $_GET['sortby'];
    }
    $no_of_prop   =  5;
    $paged = 1;

    if( isset( $_GET['per_page'] ) ) {
        $no_of_prop = $_GET['per_page'];
    }
    if( isset( $_GET['page'] ) ) {
        $paged = $_GET['page'];
    }

    if ( get_query_var( 'paged' ) ) {
        $paged = get_query_var( 'paged' );
    } elseif ( get_query_var( 'page' ) ) { // if is static front page
        $paged = get_query_var( 'page' );
    }
    
    $args = array(
        'post_type'        =>  'property',
        'author'           =>  $userID,
        'paged'             => $paged,
        'posts_per_page'    => $no_of_prop,
        'post_status'      =>  array( $qry_status ),
        'suppress_filters' => false
    );
    if( isset ( $_GET['keyword'] ) ) {
        $keyword = trim( $_GET['keyword'] );
        if ( ! empty( $keyword ) ) {
            $args['s'] = $keyword;
        }
    }
    $args = houzez_prop_sort ( $args );
    queryPropertiesAndSendJSON($args);
}
function getPropertyByPermalink($request) {
    if ( !isset( $_GET['perm']) || empty( $_GET['perm']) ) {
        $ajax_response = array( 'success' => false , 'reason' => esc_html__( 'No permalink found', 'houzez' ) );
        wp_send_json($ajax_response,400);
        return;
    }
    $permalink     = $_GET['perm'];
    $propertyId = url_to_postid( $permalink );
    queryPropertyById($propertyId);

}
function getProperty($request) {
    if ( !isset( $_GET['id']) || empty( $_GET['id']) ) {
        $ajax_response = array( 'success' => false , 'reason' => esc_html__( 'No Property ID found', 'houzez' ) );
        wp_send_json($ajax_response,400);
        return;
    }
    if (isset( $_GET['editing']) && !empty( $_GET['editing'] && $_GET['editing'] == "true") && ! is_user_logged_in() ) {
        $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
        wp_send_json($ajax_response, 403);
        return; 
    }

	$propertyId     = $_GET['id'];
    queryPropertyById($propertyId);
}
function queryPropertyById($propertyId) {   
    $args = array(
        'post_type'        =>  'property',
        'p'               =>  $propertyId,    
        'posts_per_page'   =>  1,
        'suppress_filters' =>  false
    );

    sendPropertyJson($args);
    
    // $query_args = query_posts( $args );
    // $properties = array();
    
    
    //global $post;
    // while( have_posts() ):
    //     the_post();
    //     //$property = $query_args->post;
    //     $property = get_post();
    //     //$post = $property;
    //     setup_postdata($property);
    //     do_action( 'template_redirect' );

    //     //$property = $query_args->post;
    //     $post_id = $property->ID;
        
    //     $property->is_fav = isFavoriteProperty($post_id);
    //     $property->link = get_permalink();
    //     $property_meta = get_post_meta($post_id);
        
        
    //     $property_meta['agent_info'] = houzez20_property_contact_form();

    //     $additional_features = $property_meta["additional_features"] ?? null;
    //     $floor_plans = $property_meta["floor_plans"] ?? null;
    //     $fave_multi_units = $property_meta["fave_multi_units"] ?? null;

    //     unset($property_meta['additional_features']);
    //     unset($property_meta['floor_plans']);    
    //     unset($property_meta['fave_multi_units']);
        
  
    //     $property_meta['additional_features'] = $additional_features ? unserialize($additional_features[0]) : [];
    //     $property_meta['floor_plans'] = $floor_plans ?  unserialize($floor_plans[0]) : [];
    //     $property_meta['fave_multi_units'] = $fave_multi_units ? unserialize($fave_multi_units[0]) : [];
        

    //     $property->property_meta    = $property_meta;

    //     appendPostImages($property);
    //     appendPostFeature($property);
    //     appendPostAddress($property);
    //     appendPostAttr($property);
    //     appendPostAttachments($property);

    //     array_push($properties, $property );
    //     //break;
    // endwhile;
    // wp_reset_query();
    // wp_send_json($properties[0] , 200);
}

function sendPropertyJson($args) {

    $query_args = query_posts( $args );
    $properties = array();
    
    //global $post;
    while( have_posts() ):
        the_post();
        //$property = $query_args->post;
        $property = get_post();
        //$post = $property;
        setup_postdata($property);
        do_action( 'template_redirect' );

        //$property = $query_args->post;
        $post_id = $property->ID;
        
        $property->is_fav = isFavoriteProperty($post_id);
        $property->link = get_permalink();
        $property_meta = get_post_meta($post_id);
        
        
        $property_meta['agent_info'] = houzez20_property_contact_form();

        $additional_features = $property_meta["additional_features"] ?? null;
        $floor_plans = $property_meta["floor_plans"] ?? null;
        $fave_multi_units = $property_meta["fave_multi_units"] ?? null;

        unset($property_meta['additional_features']);
        unset($property_meta['floor_plans']);    
        unset($property_meta['fave_multi_units']);
        
  
        $property_meta['additional_features'] = $additional_features ? unserialize($additional_features[0]) : [];
        $property_meta['floor_plans'] = $floor_plans ?  unserialize($floor_plans[0]) : [];
        $property_meta['fave_multi_units'] = $fave_multi_units ? unserialize($fave_multi_units[0]) : [];
        

        $property->property_meta    = $property_meta;

        appendPostImages($property);
        appendPostFeature($property);
        appendPostAddress($property);
        appendPostAttr($property);
        appendPostAttachments($property);

        array_push($properties, $property );
        //break;
    endwhile;
    wp_reset_query();

    if (!empty($properties)) {
        wp_send_json($properties[0] , 200);
    } else {
        wp_send_json($properties , 200);
    }
}

function appendPostImages(&$property)
{
    $property->property_images = array();
    $property->property_images_thumb = array();
    $property_images_array = !empty($property->property_meta['fave_property_images']) ? $property->property_meta['fave_property_images'] : [];
	if ($property_images_array == null || empty($property_images_array)) return;
	
  foreach ($property_images_array as $imgID) :
    $property->property_images[] = wp_get_attachment_url($imgID);
    $property->property_images_thumb[] = wp_get_attachment_image_src($imgID, 'thumbnail', true )[0];
  endforeach;
}
function appendPostAttachments(&$property)
{
    $property->attachments = array();
    
    $property_attachment_array = !empty($property->property_meta['fave_attachments']) ? $property->property_meta['fave_attachments'] : [];
	if ($property_attachment_array == null || empty($property_attachment_array)) return;
    
    foreach ($property_attachment_array as $attachment_id) {
        $attachment_metadata = wp_get_attachment_metadata($attachment_id);
        $file_name = basename ( get_attached_file( $attachment_id ) );
        $file_url = wp_get_attachment_url($attachment_id);
        $file_size = size_format( filesize( get_attached_file( $attachment_id ) ), 2 );
        $property->attachments[] = array(
            'url' => $file_url,
            'name' => $file_name,
            'size' => $file_size
        );
    }
}
function appendPostFeature(&$property)
{
  // $response->data['property_features'] = get_the_terms( $response->data['id'], 'property_feature' );

//   $property->property_features = wp_get_post_terms( $property->ID,
//     ['property_feature'],
//     array('fields' => 'names')
//   );
    $property->property_features = getCurrentLanguageTermsOnly($property->ID, 'property_feature');
}

function appendPostAddress(&$response)
{
    $address_taxonomies = array();
    if (taxonomy_exists( 'property_country' )) {
        array_push($address_taxonomies, 'property_country' );
    }
    if (taxonomy_exists( 'property_state' )) {
        array_push($address_taxonomies, 'property_state' );
    }
    if (taxonomy_exists( 'property_city' )) {
        array_push($address_taxonomies, 'property_city' );
    }
    if (taxonomy_exists( 'property_area' )) {
        array_push($address_taxonomies, 'property_area' );
    }
    $address_array = wp_get_post_terms(
        $response->ID,
        $address_taxonomies
    );
    $property_address = array();
    foreach ($address_array as $address) :
        $property_address[$address->taxonomy] = $address->name;
    endforeach;
    $response->property_address = $property_address;
}
function appendPostAttr(&$response)
{
  $property_attr = wp_get_post_terms(
    $response->ID,
    ['property_type', 'property_status', 'property_label']

  );
  $current_lang = apply_filters( 'wpml_current_language', "en" );
  $property_attributes = array();
  $property_attributes_all = array();
  foreach ($property_attr as $attribute) :
    $localizez_term_id = apply_filters( 'wpml_object_id', $attribute->term_id, $attribute->taxonomy, FALSE, $current_lang );
    $term = get_term( $localizez_term_id );
    if (empty($property_attributes[$attribute->taxonomy])) {
      $property_attributes[$attribute->taxonomy] = $term->name;
    }
    $property_attributes_all[$attribute->taxonomy][] = $term->name;
  endforeach;
  $response->property_attr = $property_attributes;
  $response->property_type_text = !empty($property_attributes_all["property_type"]) ? $property_attributes_all["property_type"] : []; 
  $response->property_status_text = !empty($property_attributes_all["property_status"]) ? $property_attributes_all["property_status"] : [];
  $response->property_label_text = !empty($property_attributes_all["property_label"]) ? $property_attributes_all["property_label"] : [];
}
function getCurrentLanguageTermsOnly($postId, $term_name) {
    $current_lang = apply_filters( 'wpml_current_language', "en" );

    $property_feature_terms = wp_get_post_terms(
        $postId,
        [$term_name]
    );
    $property_attributes = array();
    if (! empty($property_feature_terms)){
        foreach ($property_feature_terms as $feature) :
        $localizez_term_id = apply_filters( 'wpml_object_id', $feature->term_id, $feature->taxonomy, FALSE, $current_lang );
        $term = get_term( $localizez_term_id );
        if (!in_array($term->name,$property_attributes)) {
            $property_attributes[] = $term->name;
        }
        endforeach;
    }
    return $property_attributes;
}

function getPropertyByMetaKey($request) {
    if ( !isset( $_GET['meta_key']) || empty( $_GET['meta_key']) ) {
        $ajax_response = array( 'success' => false , 'reason' => esc_html__( 'No Meta Key found', 'houzez' ) );
        wp_send_json($ajax_response,400);
        return;
    }

    if ( !isset( $_GET['meta_value']) || empty( $_GET['meta_value']) ) {
        $ajax_response = array( 'success' => false , 'reason' => esc_html__( 'No Meta Value found', 'houzez' ) );
        wp_send_json($ajax_response,400);
        return;
    }

    $meta_key     = $_GET['meta_key'];
    $meta_value     = $_GET['meta_value'];
    

    $args = array(
        'post_type'        =>  'property',
        'meta_key'         =>  $meta_key,    
        'meta_value'       =>  $meta_value,
        'posts_per_page'   =>  1,
        'suppress_filters' =>  false
    );

    sendPropertyJson($args);
}

//-----------------------------Add custom print property endpoint-------------------------------------


// Custom endpoint for the 'print-property' query variable to the WordPress rewrite rules.
// This allows us to handle custom actions based on this endpoint.
function houzez_add_custom_endpoint() {
    add_rewrite_endpoint( 'print-property-pdf', EP_ROOT );
}
add_action( 'init', 'houzez_add_custom_endpoint' );

// ---------------
// Function to handle the custom 'print-property-pdf' endpoint.
function houzez_handle_custom_endpoint() {
    // Check if the 'propid' parameter is set in the query string and is not empty.
    if ( isset( $_GET['propid'] ) && ! empty( $_GET['propid'] ) ) {
        // Houzez function to print the property.
		houzi_create_print ();
        // Note: The following 'exit;' line is commented out, which means WordPress 
		// will continue processing after this function.
        // If you uncomment the 'exit;' line, it will stop further WordPress processing 
		// after this function, which may be necessary depending on your specific use case.
        exit; // Stop further WordPress processing
    }
}
add_action( 'template_redirect', 'houzez_handle_custom_endpoint' );


function houzi_create_print () {
	if(!isset($_GET['propid'])|| !is_numeric($_GET['propid'])){
		exit();
	}
	global $hide_fields;
	$hide_fields = houzez_option('hide_detail_prop_fields');
	$property_id = intval($_GET['propid']);

	print  '<html><head><link href="'.get_stylesheet_uri().'" rel="stylesheet" type="text/css" />';
	print  '<html><head><link href="'.HOUZEZ_CSS_DIR_URI.'bootstrap.min.css" rel="stylesheet" type="text/css" />';
	print  '<html><head><link href="'.HOUZEZ_CSS_DIR_URI.'main.css" rel="stylesheet" type="text/css" />';
	print  '<html><head><link href="'.HOUZEZ_CSS_DIR_URI.'icons.css" rel="stylesheet" type="text/css" />';
	print  '<html><head><link href="'.HOUZEZ_CSS_DIR_URI.'font-awesome.min.css" rel="stylesheet" type="text/css" />';

	if( is_rtl() ) {
		print '<link href="'.HOUZEZ_CSS_DIR_URI.'/rtl.css" rel="stylesheet" type="text/css" />';
		print '<link href="'.HOUZEZ_CSS_DIR_URI.'/bootstrap-rtl.min.css" rel="stylesheet" type="text/css" />';
	}
	print '</head>';
	print  '<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script><script>$(window).load(function(){ print(); });</script>';
	print  '<body class="print-page">';

	$print_logo = houzez_option( 'print_page_logo', false, 'url' );

	$print_agent = houzez_option('print_agent');
	$print_description = houzez_option('print_description');
	$print_details = houzez_option('print_details');
	$print_details_additional = houzez_option('print_details_additional');
	$print_features = houzez_option('print_features');
	$print_floorplans = houzez_option('print_floorplans');
	$print_gallery = houzez_option('print_gallery');
	$print_gr_code = houzez_option('print_gr_code');

	$args = array(
		'post_type' => 'property',
		'p' => $property_id,
	);

	$the_query = new WP_Query($args);

	if($the_query->have_posts()): 
		while($the_query->have_posts()): $the_query->the_post(); 
			global $property_features, $energy_class;
			$image_id     = get_post_thumbnail_id( get_the_ID() );
			$full_img     = wp_get_attachment_image_src($image_id, 'full');
			$full_img     = isset($full_img [0]) ? $full_img [0] : '';
			$property_features     = wp_get_post_terms( get_the_ID(), 'property_feature', array("fields" => "all"));
			$energy_class = houzez_get_listing_data('energy_class');
			$floor_plans  = get_post_meta( get_the_ID(), 'floor_plans', true );
			$prop_images  = get_post_meta( get_the_ID(), 'fave_property_images', false );
			$agent_array = houzez20_property_contact_form();

	?>

		<div class="print-main-wrap">
			<div class="print-wrap">
				<header class="print-header">
					<div class="print-logo-wrap">
						<div class="logo">
							<a href="#">
								<img src="<?php echo esc_url($print_logo); ?>" alt="logo">
							</a>
						</div><!-- .logo -->
						<div class="primary-text"><?php bloginfo( 'description' ); ?></div>
					</div><!-- print-logo-wrap -->
					
					<div class="print-title-wrap">
						<div class="d-flex align-items-center">
							<div class="flex-grow-1">
								<?php get_template_part('property-details/partials/title'); ?>      
								<?php get_template_part('property-details/partials/item-address'); ?>
							</div>          
							<?php get_template_part('property-details/partials/item-price'); ?>
						</div><!-- d-flex -->
					</div><!-- print-title-wrap -->
					
					<?php if( !empty($full_img) ) { ?>
					<div class="print-banner-wrap">
						<?php if($print_gr_code != 0) { ?>
						<div class="qr-code">
							<img class="img-fluid" src="https://chart.googleapis.com/chart?chs=105x104&cht=qr&chl=<?php echo esc_url( get_permalink($property_id) ); ?>&choe=UTF-8" title="<?php echo esc_attr(get_the_title()); ?>" />
						</div>
						<?php } ?>
						<img class="img-fluid" src="<?php echo esc_url( $full_img ); ?>" alt="<?php echo esc_attr(get_the_title()); ?>">
					</div><!-- print-banner-wrap -->
					<?php } ?>
					
					<?php 
					if( $print_agent != 0 && !empty($agent_array)) { ?>
					<div class="print-agent-info-wrap">
						
						<h2 class="print-title"><?php echo esc_html__('Contact Information', 'houzez'); ?></h2>
						
						<?php 
						if( isset( $agent_array['agent_info'] ) ) {
							foreach( $agent_array['agent_info'] as $agent_info ) {  ?>
							   
								<div class="agent-details">
									<div class="d-flex align-items-center">
										<?php if(!empty($agent_info['picture'])) { ?>
										<div class="agent-image">
											<img class="rounded" src="<?php echo esc_url($agent_info['picture']); ?>" alt="<?php echo esc_attr($agent_info['agent_name']); ?>" width="80" height="80">
										</div>
										<?php } ?>

										<ul class="list-unstyled m-0 ml-3 mr-3">
											<li class="agent-name">
												<i class="houzez-icon icon-single-neutral mr-1"></i> <?php echo esc_attr($agent_info['agent_name']); ?>
											</li>
											<li class="agent-phone-wrap clearfix">
												<?php if(!empty($agent_info['agent_phone'])) { ?>
												<i class="houzez-icon icon-phone mr-1"></i> <strong><?php echo esc_attr($agent_info['agent_phone']); ?></strong>
												<?php } ?>

												<?php if(!empty($agent_info['agent_mobile'])) { ?>
												<i class="houzez-icon icon-mobile-phone mr-1"></i> <strong><?php echo esc_attr($agent_info['agent_mobile']); ?></strong>
												<?php } ?>
											</li>

											<?php if(!empty($agent_info['agent_email'])) { ?>
											<li><i class="houzez-icon icon-envelope mr-1"></i> <strong><?php echo esc_attr($agent_info['agent_email']); ?></strong></li>
											<?php } ?>
										</ul>
									</div><!-- d-flex -->
								</div><!-- agent-details -->
								<br/>
							<?php
							}
						}
						?>
					</div><!-- print-agent-info-wrap -->
					<?php } ?>

				</header>  

				
				<section class="print-content">
					
					<?php 
					if( $print_description != 0 ) { ?>

						<div class="print-section">
							<h2 class="print-title"><?php echo esc_html__('Description', 'houzez'); ?></h2>
							<?php the_content(); ?>       
						</div>

					<?php } ?>

					<?php 
					if( $print_details != 0 ) { ?>

						<div class="print-section">
							<h2 class="print-title"><?php echo esc_html__('Detail', 'houzez'); ?></h2>
							<div class="block-content-wrap">
								<?php get_template_part('property-details/partials/details'); ?> 
							</div><!-- block-content-wrap -->
						</div>

					<?php } ?>

					<?php 
					if( $print_features != 0 && !empty($property_features)) { ?>

						<div class="print-section">
							<h2 class="print-title"><?php echo esc_html__('Features', 'houzez'); ?></h2>
							<div class="block-content-wrap">
								<?php get_template_part('property-details/partials/features'); ?>  
							</div><!-- block-content-wrap -->
						</div>

					<?php } ?>

					<?php
					if( houzez_option('print_energy_class') != 0 && !empty($energy_class) ) { ?>
						<div class="print-section">
							<h2 class="print-title"><?php echo esc_html__('Energy Efficiency', 'houzez'); ?></h2>
							<div class="block-content-wrap">
								<?php get_template_part('property-details/partials/energy-class'); ?> 
							</div><!-- block-content-wrap -->
						</div><!-- print-section -->
					<?php } ?>

					<?php 
					if( !empty( $floor_plans ) && $print_floorplans != 0 ) { ?>

						<div class="print-section">
							<h2 class="print-title"><?php echo esc_html__('Floor Plans', 'houzez'); ?></h2>
							
							<?php 
							foreach( $floor_plans as $plan ):
								$price_postfix = '';
								if( !empty( $plan['fave_plan_price_postfix'] ) ) {
									$price_postfix = ' / '.$plan['fave_plan_price_postfix'];
								}
								$filetype = wp_check_filetype($plan['fave_plan_image']);
							?>
							<div class="floor-plan-wrap">
								<div class="floor-plan-top">
									<div class="d-flex align-items-center">
										<div class="accordion-title flex-grow-1">
											<?php echo esc_attr( $plan['fave_plan_title'] ); ?>
										</div><!-- accordion-title -->
										<ul class="floor-information list-unstyled list-inline m-0">
											<?php if( !empty( $plan['fave_plan_size'] ) ) { ?>
												<li class="list-inline-item">
													<?php esc_html_e( 'Size', 'houzez' ); ?>: 
													<strong> <?php echo esc_attr( $plan['fave_plan_size'] ); ?></strong>
												</li>
											<?php } ?>

											<?php if( !empty( $plan['fave_plan_rooms'] ) ) { ?>
												<li class="list-inline-item">
													<i class="houzez-icon icon-hotel-double-bed-1 mr-1"></i>
													<strong><?php echo esc_attr( $plan['fave_plan_rooms'] ); ?></strong>
												</li>
											<?php } ?>

											<?php if( !empty( $plan['fave_plan_bathrooms'] ) ) { ?>
												<li class="list-inline-item">
													<i class="houzez-icon icon-bathroom-shower-1 mr-1"></i>
													<strong><?php echo esc_attr( $plan['fave_plan_bathrooms'] ); ?></strong>
												</li>
											<?php } ?>

											<?php if( !empty( $plan['fave_plan_price'] ) ) { ?>
												<li class="list-inline-item">
													<?php esc_html_e( 'Price', 'houzez' ); ?>: 
													<strong><?php echo houzez_get_property_price( $plan['fave_plan_price'] ).$price_postfix; ?></strong>
												</li>
											<?php } ?>
										</ul>
									</div><!-- d-flex -->
								</div><!-- floor-plan-top -->
								
								<?php 
								if( !empty( $plan['fave_plan_image'] ) ) { ?>
				
									<?php if($filetype['ext'] != 'pdf' ) {?>
									<a href="<?php echo esc_url( $plan['fave_plan_image'] ); ?>" target="_blank">
										<img class="img-fluid" src="<?php echo esc_url( $plan['fave_plan_image'] ); ?>" alt="image">
									</a>
									<?php } ?>
									
								<?php } ?>

								<?php
								if( !empty( $plan['fave_plan_description'] ) ) { ?>
								<div class="floor-plan-description mt-3">
									<p>
										<?php echo wp_kses_post( $plan['fave_plan_description'] ); ?>
									</p>
								</div><!-- floor-plan-description -->
								<?php } ?>

							</div><!-- floor-plan-wrap -->
							<?php endforeach; ?>

						</div>
					<?php } ?>


					<?php 
					if( !empty( $prop_images ) && $print_gallery != 0 ) { ?>
					<div class="print-section">
						<h2 class="print-title"><?php esc_html_e('Property images', 'houzez'); ?></h2>
						<?php 
						foreach( $prop_images as $img_id ): 
							$image_url = houzez_get_image_by_id($img_id, 'full');
							?>
							<div class="print-gallery-image"> 
							<img src="<?php echo $image_url[0]; ?>" class="img-fluid mb-3">
							</div>
						<?php endforeach; ?>
					</div>
					<?php } ?>


				</section>
			</div><!-- print-wrap -->
		</div><!-- print-main-wrap -->

	<?php
		endwhile;
	endif;

	?>

 <?php
	print '</body></html>';
	wp_die();
}