<?php


add_action( 'rest_api_init', function () {
  register_rest_route( 'houzez-mobile-api/v1', '/touch-base', array(
    'methods' => 'GET',
    'callback' => 'getMetaData',
  ));

});


function getMetaData(){
    
    $response = array();
    
    $response['success'] = true;
    $response['version'] = 1;
    $response['default_currency'] = houzez_get_currency();

    add_term_to_response($response, 'property_country');
    add_term_to_response($response, 'property_state');
    add_term_to_response($response, 'property_city');
    add_term_to_response($response, 'property_area');

    add_term_to_response($response, 'property_type');
    add_term_to_response($response, 'property_label');
    add_term_to_response($response, 'property_status');
    add_term_to_response($response, 'property_feature');

    
    echo json_encode($response);
}
function add_term_to_response(&$response, $key){
    
    $property_term = get_terms( array(
        'taxonomy'   => $key,
        'hide_empty' => false,
    ) );
    $response[$key] = $property_term;
}

