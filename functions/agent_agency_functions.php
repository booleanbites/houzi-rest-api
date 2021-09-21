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

add_action( 'rest_api_init', function () {
  register_rest_route( 'houzez-mobile-api/v1', '/contact-realtor', array(
    'methods' => 'POST',
    'callback' => 'contactRealtor',
  ));
  register_rest_route( 'houzez-mobile-api/v1', '/schedule-tour', array(
    'methods' => 'POST',
    'callback' => 'scheduleATour',
  ));
});

function contactRealtor($request){
  // agent_id
  // target_email
  // mobile
  // name
  // email
  // message
  // user_type - buyer, tennant, agent, other

  //using the existing theme method.
  
  $nonce = wp_create_nonce('contact_realtor_nonce');
  
  $_POST['contact_realtor_ajax'] = $nonce;
  //$_POST['agent_type'] = 'agent_info';
  $_POST['privacy_policy'] =  '1';

  $enable_reCaptcha = houzez_option('enable_reCaptcha');
  
  global $houzez_options;
  $houzez_options['enable_reCaptcha'] = 0;

  houzez_contact_realtor();
  
  /*
  for future use
  $result = array(
    'success' => true,
    'message' => "Message sent successfully.",
    'result' => $result,
    'captcha_before' => $enable_reCaptcha,
    'captcha_after' => $enable_reCaptcha2,
  );
  return new WP_REST_Response($result, 200);
  */
}

function scheduleATour($request){
  // listing_id
  // property_title
  // property_permalink

  // target_email
  
  // schedule_tour_type
  // schedule_date
  // schedule_time

  // name
  // phone
  // email
  // message

  //using the existing theme method.
  
  $nonce = wp_create_nonce('schedule-contact-form-nonce');
  

  $_POST['schedule_contact_form_ajax'] = $nonce;
  $_POST['is_listing_form'] = 'yes';
  $_POST['is_schedule_form'] = 'yes';
  $_POST['privacy_policy'] =  '1';

  $enable_reCaptcha = houzez_option('enable_reCaptcha');
  
  // global $houzez_options;
  // $houzez_options['enable_reCaptcha'] = 0;

  houzez_schedule_send_message();
  
  /*
  for future use
  $result = array(
    'success' => true,
    'message' => "Message sent successfully.",
    'result' => $result,
    'captcha_before' => $enable_reCaptcha,
    'captcha_after' => $enable_reCaptcha2,
  );
  return new WP_REST_Response($result, 200);
  */
}