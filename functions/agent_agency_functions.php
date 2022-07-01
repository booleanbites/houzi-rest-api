<?php
/**
 * Extends api for agency and agents.
 *
 *
 * @package Houzez Mobile Api
 * @since Houzi 1.0
 * @author Adil Soomro
 */

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
//-----------------------------Lightspeed exclude URLs-------------------------------------
// By default all POST URLs aren't cached
add_action( 'litespeed_init', function () {
    
  //these URLs need to be excluded from lightspeed caches
  $exclude_url_list = array(
      "agency-all-agents",

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
      if (strpos($_SERVER['REQUEST_URI'], $include_url) !== FALSE) {
          do_action( 'litespeed_control_set_cacheable', 'cache for rest api' );
      }
  }
});
add_action( 'rest_api_init', function () {
  register_rest_route( 'houzez-mobile-api/v1', '/contact-realtor', array(
    'methods' => 'POST',
    'callback' => 'contactRealtor',
  ));
  register_rest_route( 'houzez-mobile-api/v1', '/schedule-tour', array(
    'methods' => 'POST',
    'callback' => 'scheduleATour',
  ));
  register_rest_route( 'houzez-mobile-api/v1', '/contact-property-agent', array(
    'methods' => 'POST',
    'callback' => 'contactPropertyRealtor',
  ));
  register_rest_route( 'houzez-mobile-api/v1', '/add-new-agent', array(
    'methods' => 'POST',
    'callback' => 'addAgent',
  ));
  register_rest_route( 'houzez-mobile-api/v1', '/edit-an-agent', array(
    'methods' => 'POST',
    'callback' => 'editAgent',
  ));
  register_rest_route( 'houzez-mobile-api/v1', '/agency-all-agents', array(
    'methods' => 'GET',
    'callback' => 'allAgencyAgents',
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
}
function addAgent($request){
  if (! is_user_logged_in() ) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
    wp_send_json($ajax_response, 403);
    return; 
  }
  $userID       = get_current_user_id();
  $user_role = houzez_user_role_by_user_id($userID);
  if ($user_role != 'houzez_agency') {
    $ajax_response = array( 'success' => false, 'reason' => 'User is not an agency.' );
    wp_send_json($ajax_response, 403);
    return; 
  }

  $agency_id = get_user_meta($userID, 'fave_author_agency_id', true );
  $agency_ids_cpt = get_post_meta($agency_id, 'fave_agency_cpt_agent', false );

  $_POST['agency_id'] = $userID;
  $_POST['agency_id_cpt'] = $agency_id;

  if( !empty($agency_ids_cpt)) {
    foreach( $agency_ids_cpt as $ag_id ):
    $_POST['agency_ids_cpt'][] =$ag_id;
    endforeach;
  } else { 
    $_POST['agency_ids_cpt[]'] ='';
  }

  $nonce = wp_create_nonce('houzez_agency_agent_ajax_nonce');
  $_REQUEST['houzez-security-agency-agent'] = $nonce;

  
  $_POST['action'] = "houzez_agency_agent";
  $results = array();
  
  $results["success"] = true;
  $results["data"] = $_POST;
  //wp_send_json($results,200);
  //using the existing theme method.
  do_action("wp_ajax_nopriv_houzez_agency_agent");//houzez_agency_agent();
}
function editAgent($request){
  if (! is_user_logged_in() ) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
    wp_send_json($ajax_response, 403);
    return; 
  }
  $userID       = get_current_user_id();
  $user_role = houzez_user_role_by_user_id($userID);
  if ($user_role != 'houzez_agency') {
    $ajax_response = array( 'success' => false, 'reason' => 'User is not an agency.' );
    wp_send_json($ajax_response, 403);
    return; 
  }
  $agency_user_id = $_POST['agency_user_id'];
  if(!isset($_POST['agency_user_id']) || empty($agency_user_id)) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide agency_user_id to edit.' );
    wp_send_json($ajax_response, 403);
    return; 
  }
  $wp_user_query = new WP_User_Query( array(
    array( 'role' => 'houzez_agent' ),
    'meta_key' => 'fave_agent_agency',
    'meta_value' => $userID
  ));
  $agents = $wp_user_query->get_results();
  $user_agents = [];
  foreach($agents as $agent){
    array_push($user_agents, $agent->ID);
  }
  if (!in_array($agency_user_id, $user_agents)){
    //echo 'You do not have access to this.';
    $ajax_response = array( 'success' => false, 'reason' => 'Agent user does not belong to agency.' );
    wp_send_json($ajax_response, 403);
    return;
  }
  $agency_user_agent_id = get_user_meta($agency_user_id, 'fave_author_agent_id', true );
  $_POST['agency_user_agent_id'] = $agency_user_agent_id;

  $agency_id = get_user_meta($userID, 'fave_author_agency_id', true );
  $agency_ids_cpt = get_post_meta($agency_id, 'fave_agency_cpt_agent', false );

  $_POST['agency_id'] = $userID;
  $_POST['agency_id_cpt'] = $agency_id;

  if( !empty($agency_ids_cpt)) {
    foreach( $agency_ids_cpt as $ag_id ):
    $_POST['agency_ids_cpt'][] =$ag_id;
    endforeach;
  } else { 
    $_POST['agency_ids_cpt[]'] ='';
  }

  $nonce = wp_create_nonce('houzez_agency_agent_ajax_nonce');
  $_REQUEST['houzez-security-agency-agent'] = $nonce;

  $_POST['action'] = "houzez_agency_agent_update";
  //using the existing theme method.
  do_action("wp_ajax_houzez_agency_agent_update");
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

function contactPropertyRealtor($request){
  // agent_id
  // target_email
  // mobile
  // name
  // email
  // message
  // user_type - buyer, tennant, agent, other

  //using the existing theme method.
  
  $nonce = wp_create_nonce('property_agent_contact_nonce');
  
  $_POST['property_agent_contact_security'] = $nonce;
  //$_POST['agent_type'] = 'agent_info';
  $_POST['privacy_policy'] =  '1';

  $enable_reCaptcha = houzez_option('enable_reCaptcha');
  
  global $houzez_options;
  $houzez_options['enable_reCaptcha'] = 0;

  houzez_property_agent_contact();
}
function allAgencyAgents($request) {
  //disable lightspeed caching
  do_action( 'litespeed_control_set_nocache', 'nocache due to logged in' );
  if(!isset( $_REQUEST['agency_id']) ) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide agency_id' );
    wp_send_json($ajax_response, 403);
    return;
  }
  $agency_user_id = isset($_GET['agency_id']) ? $_GET['agency_id'] : '';
  
  $wp_user_query = new WP_User_Query( array(
      array( 'role' => 'houzez_agent' ),
      'meta_key' => 'fave_agent_agency',
      'meta_value' => $agency_user_id
  ));
  $results = array();
  $agents = $wp_user_query->get_results();
  if( !empty($agents) ) {
    
    foreach ($agents as $agent) {
      $data = $agent->data;
      unset($data->user_pass);
      $data->agent_meta = get_user_meta( $agent->ID);
      array_push($results, $data);
    }
  }

  wp_send_json($results,200);
}