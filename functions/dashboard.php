<?php

// houzez-mobile-api/v1/search-properties
add_action( 'rest_api_init', function () {
    
    register_rest_route( 'houzez-mobile-api/v1', '/activities', array(
      'methods' => 'GET',
      'callback' => 'allActivities',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/enquiries', array(
      'methods' => 'GET',
      'callback' => 'allEnquiries',
    ));
  
  });

  function doFakeLogin() {
    if( !isset( $_GET['user_id']) && !isset( $_POST['user_id']) ) {
      $ajax_response = array( 'success' => false, 'reason' => 'Please provide user_id' );
      wp_send_json($ajax_response, 400);
      return;
    }
    $user_id  = $_GET['user_id'];
    if ( empty($user_id) ) {
      $user_id  = $_POST['user_id'];
    }
    if ( empty($user_id) ) {
      $ajax_response = array( 'success' => false, 'reason' => 'Please provide user_id in POST or GET' );
      wp_send_json($ajax_response, 400);
      return;
    }
  
    $user = get_user_by( 'id', $user_id ); 
    if( $user ) {
        wp_set_current_user( $user_id, $user->user_login );
        wp_set_auth_cookie( $user_id );
        do_action( 'wp_login', $user->user_login, $user );
    }
  }

function allActivities(){
    
    doFakeLogin();
    
    $activities = Houzez_Activities::get_activities();
    $leads_count = Houzez_Leads::get_leads_stats();
    $resultsold = $activities["data"]["results"];
    $results = array();

    
    
    foreach( $activities['data']['results'] as $activity ) {
      $meta = maybe_unserialize($activity->meta);
      $type = isset($meta['type']) ? $meta['type'] : '';
      $subtype = isset($meta['subtype']) ? $meta['subtype'] : '';

      if($type == 'lead') {
        $permalink_id = isset($meta['listing_id']) ? $meta['listing_id'] : '';
        if(!empty($permalink_id)) {
          $meta['title'] = get_the_title($permalink_id);
          
        }
      } else if($type == 'lead_agent') {
  
        $permalink_id = isset($meta['agent_id']) ? $meta['agent_id'] : '';
        $agent_type = isset($meta['agent_type']) ? $meta['agent_type'] : '';
  
        if(!empty($permalink_id)) {     
          if($agent_type == "author_info") {
            $meta['title'] = get_the_author_meta( 'display_name', $permalink_id );
          } else {
            $meta['title'] = get_the_title($permalink_id);
          }
        }
      } else if($type == 'lead_contact') {
  
        $permalink_id = isset($meta['lead_page_id']) ? $meta['lead_page_id'] : '';
        
        if(!empty($permalink_id)) {
          $meta['title'] = get_the_title($permalink_id);
        }
      }
      
      $activity->meta = $meta;
      array_push($results, $activity);
    }
    $activities["data"]["results"] = $results;

    

    $activities["data"]["stats"] = $leads_count['leads_count'];
    
    wp_send_json($activities["data"],200);
}


function allEnquiries(){
  doFakeLogin();
  
  $all_enquires = Houzez_Enquiry::get_enquires();

  $results = array();
  foreach( $all_enquires['data']['results'] as $enquiry ) {
    $meta = maybe_unserialize($enquiry->enquiry_meta);
    $lead = Houzez_Leads::get_lead($enquiry->lead_id);
    $matched_query = matched_listings($enquiry->enquiry_meta);

    $enquiry->enquiry_meta = $meta;
    $enquiry->display_name = $lead->display_name;
    $enquiry->lead = $lead;
    $enquiry->matched = $matched_query->posts;

    array_push($results, $enquiry);
  }
  $all_enquires["data"]["results"] = $results;
  
  wp_send_json($all_enquires["data"],200);
}

