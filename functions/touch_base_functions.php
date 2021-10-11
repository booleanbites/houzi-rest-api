<?php


add_action( 'rest_api_init', function () {
  register_rest_route( 'houzez-mobile-api/v1', '/touch-base', array(
    'methods' => 'GET',
    'callback' => 'getMetaData',
  ));

});


function getMetaData() {
    
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

    
    $response['schedule_time_slots'] = houzez_option('schedule_time_slots');
    $response['property_item_designs'] = array(
      'home_item'   => 'design_2',
      'result_item' => 'design_1',
      'related_item' => 'design_1',
      'agent_item' => 'design_1',
  );

    echo json_encode($response);
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

