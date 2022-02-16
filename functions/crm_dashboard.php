<?php

// houzez-mobile-api/v1/search-properties
add_action( 'rest_api_init', function () {
    
    register_rest_route( 'houzez-mobile-api/v1', '/activities', array(
      'methods' => 'GET',
      'callback' => 'allActivities',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/leads', array(
      'methods' => 'GET',
      'callback' => 'allLeads',
    ));
    register_rest_route( 'houzez-mobile-api/v1', '/delete-lead', array(
      'methods' => 'GET',
      'callback' => 'deleteLead',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/enquiries', array(
      'methods' => 'GET',
      'callback' => 'allEnquiries',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/deals', array(
      'methods' => 'GET',
      'callback' => 'allDeals',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/add-deal', array(
      'methods' => 'POST',
      'callback' => 'addDeal',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/delete-deal', array(
      'methods' => 'GET',
      'callback' => 'deleteDeal',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/add-crm-enquiry', array(
      'methods' => 'POST',
      'callback' => 'addCRMEnquiry',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/delete-crm-enquiry', array(
      'methods' => 'GET',
      'callback' => 'deleteCRMEnquiry',
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
function allLeads() {
  do_action( 'litespeed_control_set_nocache', 'nocache due to logged in' );
    if (! is_user_logged_in() ) {
      $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
      wp_send_json($ajax_response, 403);
      return; 
    }
    //a fix for pagination
    if(isset($_GET["per_page"]) && !empty($_GET["per_page"])) {
      $_GET["records"] = $_GET["per_page"];
    }

  $all_leads = Houzez_leads::get_leads();
  wp_send_json($all_leads["data"],200);
}
function allActivities(){
  //disable lightspeed caching
    do_action( 'litespeed_control_set_nocache', 'nocache due to logged in' );
    if (! is_user_logged_in() ) {
      $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
      wp_send_json($ajax_response, 403);
      return; 
    }
    //a fix for pagination
    if(isset($_GET["per_page"]) && !empty($_GET["per_page"])) {
      $_GET["records"] = $_GET["per_page"];
    }

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

    $deals = array(
      'active_count' => Houzez_Deals::get_total_deals_by_group('active'),
      'won_count' => Houzez_Deals::get_total_deals_by_group('won'),
      'lost_count' => Houzez_Deals::get_total_deals_by_group('lost'),
    );
    $activities["data"]["deals"] = $deals;

    $activities["data"]["stats"] = $leads_count['leads_count'];
    
    wp_send_json($activities["data"],200);
}

function allEnquiries(){
  //disable lightspeed caching
  do_action( 'litespeed_control_set_nocache', 'nocache due to logged in' );
  if (! is_user_logged_in() ) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
    wp_send_json($ajax_response, 403);
    return; 
  }
  //a fix for pagination
  if(isset($_GET["per_page"]) && !empty($_GET["per_page"])) {
    $_GET["records"] = $_GET["per_page"];
  }
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

function allDeals() {
  //disable lightspeed caching
  do_action( 'litespeed_control_set_nocache', 'nocache due to logged in' );
  if (! is_user_logged_in() ) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
    wp_send_json($ajax_response, 403);
    return; 
  }
  //a fix for pagination
  if(isset($_GET["per_page"]) && !empty($_GET["per_page"])) {
    $_GET["records"] = $_GET["per_page"];
  }
  
  $deals = Houzez_Deals::get_deals();

  $results = array();
  foreach ($deals['data']['results'] as $deal_data) {
    $agent_id = $deal_data->agent_id;
    $deal_data->agent_name = get_the_title($agent_id); 
    $deal_data->lead = Houzez_Leads::get_lead($deal_data->lead_id);
    
    array_push($results, $deal_data);
  }
  $status_settings = hcrm_get_option('status', 'hcrm_deals_settings', esc_html__('New Lead, Meeting Scheduled, Qualified, Proposal Sent, Called, Negotiation, Email Sent', 'houzez'));
  $next_action_settings = hcrm_get_option('next_action', 'hcrm_deals_settings', esc_html__('Qualification, Demo, Call, Send a Proposal, Send an Email, Follow Up, Meeting', 'houzez'));

  $deals["data"]["status"] = $status_settings;
  $deals["data"]["actions"] = $next_action_settings;
  
  $deals["data"]["active_count"] = Houzez_Deals::get_total_deals_by_group('active');
  $deals["data"]["won_count"] = Houzez_Deals::get_total_deals_by_group('won');
  $deals["data"]["lost_count"] = Houzez_Deals::get_total_deals_by_group('lost');

  $deals["data"]["results"] = $results;
  
  wp_send_json($deals["data"],200);
}

function addDeal() {
  //calls Houzez_Deals->add_new_deal();
  do_action("wp_ajax_houzez_crm_add_deal");
}
function deleteDeal() {

  if (! is_user_logged_in() ) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
    wp_send_json($ajax_response, 403);
    return; 
  }
  $nonce = wp_create_nonce('delete_deal_nonce');
  $_REQUEST['security'] = $nonce;

  //needs enquiry id in var deal_id
  do_action("wp_ajax_houzez_delete_deal");
}


function deleteLead() {

  if (! is_user_logged_in() ) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
    wp_send_json($ajax_response, 403);
    return; 
  }
  if(!isset( $_REQUEST['lead_id']) ) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide lead_id' );
    wp_send_json($ajax_response, 403);
    return;
  }
  $nonce = wp_create_nonce('delete_lead_nonce');
  $_REQUEST['security'] = $nonce;


  //$_REQUEST['lead_id'] = $_POST['lead_id'];

  //needs lead id in var lead_id
  do_action("wp_ajax_houzez_delete_lead");
}

function addCRMEnquiry() {
  //calls Houzez_Deals->add_new_deal();
  do_action("wp_ajax_crm_add_new_enquiry");
}

function deleteCRMEnquiry() {

  if (! is_user_logged_in() ) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
    wp_send_json($ajax_response, 403);
    return; 
  }
  //needs enquiry id in var ids
  do_action("wp_ajax_houzez_delete_enquiry");
}