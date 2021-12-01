<?php


add_action( 'rest_api_init', function () {
  register_rest_route( 'houzez-mobile-api/v1', '/touch-base', array(
    'methods' => 'GET',
    'callback' => 'getMetaData',
  ));
});

add_action( 'rest_api_init', function () {
  register_rest_route( 'houzez-mobile-api/v1', '/get-terms', array(
    'methods' => 'GET',
    'callback' => 'getTerms',
  ));
});
function getTerms() {
  if( !isset( $_GET['term'])) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide term in GET' );
    wp_send_json($ajax_response, 400);
    return;
  }
  $response = array();
  
  $response['success'] = true;
  $term = $_GET['term'];

  add_term_to_response($response, $term);

  wp_send_json($response, 200);
}
function getMetaData() {
    
    $response = array();
    
    $response['success'] = true;
    $response['version'] = 1;
    $response['default_currency'] = houzez_get_currency();

    add_term_to_response($response, 'property_country');
    add_term_to_response($response, 'property_state');
    add_term_to_response($response, 'property_city');
    //add_term_to_response($response, 'property_area');

    add_term_to_response($response, 'property_type');
    add_term_to_response($response, 'property_label');
    add_term_to_response($response, 'property_status');
    add_term_to_response($response, 'property_feature');

    $response['property_area'] = [];
    $response['schedule_time_slots'] = houzez_option('schedule_time_slots');
    $response['property_item_designs'] = array(
      'home_item'   => 'design_2',
      'result_item' => 'design_1',
      'related_item' => 'design_1',
      'agent_item' => 'design_1',
    );

    add_roles_to_response($response);
    
    $response['enquiry_type'] = hcrm_get_option('enquiry_type', 'hcrm_enquiry_settings', esc_html__('Purchase, Rent, Sell, Miss, Evaluation, Mortgage', 'houzez'));
    wp_send_json($response, 200);
    //echo json_encode($response);
}
function add_term_to_response(&$response, $key){
    
    $property_term = get_terms( array(
        'taxonomy'   => $key,
        'hide_empty' => false,
    ) );
    foreach($property_term as $term) {
      $taxonomy_img_id = get_term_meta( $term->term_id, 'fave_taxonomy_img', true );
      if(empty($taxonomy_img_id)) {
        $taxonomy_img_id = get_term_meta( $term->term_id, 'fave_feature_img_icon', true );
      }
      if(!empty($taxonomy_img_id)) {
        $term_img = wp_get_attachment_image_src( $taxonomy_img_id, 'full' )[0];
        $term_img_thumb = wp_get_attachment_image_src($taxonomy_img_id, 'thumbnail', true )[0];
        $term->thumbnail = $term_img_thumb;
        $term->full = $term_img;
      }
    }
    $response[$key] = $property_term;
}

function add_roles_to_response(&$response){

  $show_hide_roles = houzez_option('show_hide_roles');
  $roles = array();

  if( $show_hide_roles['agent'] != 1 ) {
    array_push($roles, array( 'value' => 'houzez_agent', 'option' => houzez_option('agent_role') ) );
  }
  if( $show_hide_roles['agency'] != 1 ) {
    array_push($roles, array( 'value' => 'houzez_agency', 'option' => houzez_option('agency_role') ) );
  }
  if( $show_hide_roles['owner'] != 1 ) {
    array_push($roles, array( 'value' => 'houzez_owner', 'option' => houzez_option('owner_role') ) );
  }
  if( $show_hide_roles['buyer'] != 1 ) {
    array_push($roles, array( 'value' => 'houzez_buyer', 'option' => houzez_option('buyer_role') ) );
  }
  if( $show_hide_roles['seller'] != 1 ) {
    array_push($roles, array( 'value' => 'houzez_seller', 'option' => houzez_option('seller_role') ) );
  }
  if( $show_hide_roles['manager'] != 1 ) {
    array_push($roles, array( 'value' => 'houzez_manager', 'option' => houzez_option('manager_role') ) );
  }


  $response['user_roles'] = $roles;
  
}

