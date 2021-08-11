<?php

add_filter( 'rest_houzez_agent_query', function( $args, $request ){
  //featured property
  if ( $request->get_param( 'fave_agent_agencies' ) ) {
      $args['meta_key']   = 'fave_agent_agencies';
      $args['meta_value'] = $request->get_param( 'fave_agent_agencies' );
  }
return $args;
}, 10, 2 );


add_filter('rest_prepare_houzez_agent', 'prepareAgentData', 10, 3);

function prepareAgentData($response, $post, $request)
{
  $params = $request->get_params();
  $agent_id_from_url = $params["id"];
  $should_append_extra_data = !empty( $agent_id_from_url);
  $imgID = $response->data['agent_meta']['_thumbnail_id'][0];
  $response->data['thumbnail'] = wp_get_attachment_url($imgID);

  /*if($should_append_extra_data) {
    //
    $agent_properties = Houzez_Query::loop_agent_properties();
    
    $properties = array();
    while( $agent_properties->have_posts() ):
        $agent_properties->the_post();
        $property = $agent_properties->post;
        array_push($properties, propertyNode($property));
    endwhile;
    $response->data['properties'] = $properties;
    wp_reset_postdata();  
  }*/
  return $response;
}

add_filter('rest_prepare_houzez_agency', 'prepareAgencyData', 10, 3);

function prepareAgencyData($response, $post, $request)
{
  // $params = $request->get_params();
  // $agency_id_from_url = $params["id"];
  // $should_append_extra_data = !empty( $agency_id_from_url);

  $imgID = $response->data['agency_meta']['_thumbnail_id'][0];
  
  $response->data['thumbnail'] = wp_get_attachment_url($imgID);

  return $response;
}

//--------------- use fulllink--------------------------

// https://wordpress.stackexchange.com/questions/296440/filter-out-results-from-rest-api
// https://developer.wordpress.org/reference/hooks/rest_prepare_this-post_type/
// https://wordpress.stackexchange.com/questions/202362/unset-data-in-custom-post-type-wordpress-api-wp-

