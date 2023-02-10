<?php
/**
 * Extends api for crm dashboard.
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
      "activities",
      "leads",
      "lead-details",
      "lead-saved-searches",
      "lead-listing-viewed",
      "lead-notes",
      "delete-lead",
      "enquiries",
      "all-enquiries",
      "enquiry-matched-listing",
      "enquiry-notes",
      "deals",
      "delete-deal",
      "delete-crm-enquiry",
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
    
    register_rest_route( 'houzez-mobile-api/v1', '/activities', array(
      'methods' => 'GET',
      'callback' => 'allActivities',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/leads', array(
      'methods' => 'GET',
      'callback' => 'allLeads',
    ));
    register_rest_route( 'houzez-mobile-api/v1', '/add-lead', array(
      'methods' => 'POST',
      'callback' => 'addLead',
    ));
    register_rest_route( 'houzez-mobile-api/v1', '/delete-lead', array(
      'methods' => 'GET',
      'callback' => 'deleteLead',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/lead-details', array(
      'methods' => 'GET',
      'callback' => 'leadDetails',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/lead-listing-viewed', array(
      'methods' => 'GET',
      'callback' => 'leadListingViewed',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/lead-saved-searches', array(
      'methods' => 'GET',
      'callback' => 'leadSavedSearches',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/lead-notes', array(
      'methods' => 'GET',
      'callback' => 'leadNotes',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/enquiries', array(
      'methods' => 'GET',
      'callback' => 'getEnquiries',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/all-enquiries', array(
      'methods' => 'GET',
      'callback' => 'allEnquiries',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/enquiry-matched-listing', array(
      'methods' => 'GET',
      'callback' => 'enquiryMatchedListing',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/send-matched-listing-email', array(
      'methods' => 'POST',
      'callback' => 'sendMatchedListingEmail',
    ));

    
    register_rest_route( 'houzez-mobile-api/v1', '/enquiry-notes', array(
      'methods' => 'GET',
      'callback' => 'enquiryNotes',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/deals', array(
      'methods' => 'GET',
      'callback' => 'allDeals',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/add-deal', array(
      'methods' => 'POST',
      'callback' => 'addDeal',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/update-deal-data', array(
      'methods' => 'POST',
      'callback' => 'updateDealData',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/delete-deal', array(
      'methods' => 'GET',
      'callback' => 'deleteDeal',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/add-crm-enquiry', array(
      'methods' => 'POST',
      'callback' => 'addCRMEnquiry',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/add-property-request', array(
      'methods' => 'POST',
      'callback' => 'addPropertyRequest',
    ));

    register_rest_route( 'houzez-mobile-api/v1', '/delete-crm-enquiry', array(
      'methods' => 'GET',
      'callback' => 'deleteCRMEnquiry',
    ));
    
    register_rest_route( 'houzez-mobile-api/v1', '/add-note', array(
      'methods' => 'POST',
      'callback' => 'addNote',
    ));
    register_rest_route( 'houzez-mobile-api/v1', '/delete-note', array(
      'methods' => 'POST',
      'callback' => 'deleteNote',
    ));
    
  });

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
  
        if($type == 'lead' || $type == 'review') {
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
  
  foreach( $all_leads['data']['results'] as $lead ) {
    $enquiry_to = $lead->enquiry_to;
    $enquiry_user_type = $lead->enquiry_user_type;
    $lead->agent_info = houzezcrm_get_assigned_agent( $enquiry_to, $enquiry_user_type );  
  }

  wp_send_json($all_leads["data"],200);
}

function leadDetails() {
  //disable lightspeed caching
  do_action( 'litespeed_control_set_nocache', 'nocache due to logged in' );
  if (! is_user_logged_in() ) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
    wp_send_json($ajax_response, 403);
    return; 
  }
  //lead-id must be provided
  if(!isset($_GET["lead-id"]) || empty($_GET["lead-id"])) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide lead-id.' );
    wp_send_json($ajax_response, 400);
    return; 
  }
  

  $lead = Houzez_Leads::get_lead($_GET["lead-id"]);
    
  if (!empty($lead)) {
    $all_enquires = Houzez_Enquiry::get_enquires();
    //$lead->enquiries = $all_enquires;
    $results = array();
    foreach( $all_enquires['data']['results'] as $enquiry ) {
      $meta = maybe_unserialize($enquiry->enquiry_meta);
      $enquiry->enquiry_meta = $meta;
      array_push($results, $enquiry);
    }
    $lead->enquiries = $results;
    $enquiry_to = $lead->enquiry_to;
	  $enquiry_user_type = $lead->enquiry_user_type;
	  $lead->agent_info = houzezcrm_get_assigned_agent( $enquiry_to, $enquiry_user_type );
  }

  wp_send_json($lead,200);

}

function leadListingViewed() {
  //disable lightspeed caching
  do_action( 'litespeed_control_set_nocache', 'nocache due to logged in' );
  if (! is_user_logged_in() ) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
    wp_send_json($ajax_response, 403);
    return; 
  }
  //lead-id must be provided
  if(!isset($_GET["lead-id"]) || empty($_GET["lead-id"])) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide lead-id.' );
    wp_send_json($ajax_response, 400);
    return; 
  }
  $viewed = Houzez_Leads::get_lead_viewed_listings();
  $listings = array();
  foreach( $viewed['data']['results'] as $listing ) {
    $listing_id = $listing->listing_id; 
    $listing->title = get_the_title($listing_id);
    $listing->thumbnail = get_the_post_thumbnail_url($listing_id, 'thumbnail');
    $listing->address = get_post_meta($listing_id, 'fave_property_map_address', true);
    $listing->permalink = get_permalink($listing_id);
    //array_push($listings, $listing);
  }
  

  //$viewed['data']['results'] = $listings;
  wp_send_json($viewed,200);

}

function leadSavedSearches() {
  //disable lightspeed caching
  do_action( 'litespeed_control_set_nocache', 'nocache due to logged in' );
  if (! is_user_logged_in() ) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
    wp_send_json($ajax_response, 403);
    return; 
  }
  //lead-id must be provided
  if(!isset($_GET["lead-id"]) || empty($_GET["lead-id"])) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide lead-id.' );
    wp_send_json($ajax_response, 400);
    return; 
  }
  $searches = Houzez_Leads::get_lead_saved_searches();
  wp_send_json($searches["data"],200);

}
function leadNotes() {
  //disable lightspeed caching
  do_action( 'litespeed_control_set_nocache', 'nocache due to logged in' );
  if (! is_user_logged_in() ) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
    wp_send_json($ajax_response, 403);
    return; 
  }
  //lead-id must be provided
  if(!isset($_GET["lead-id"]) || empty($_GET["lead-id"])) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide lead-id.' );
    wp_send_json($ajax_response, 400);
    return; 
  }
  $belong_to = isset($_GET['lead-id']) ? $_GET['lead-id'] : '';
  $notes = Houzez_CRM_Notes::get_notes($belong_to, 'lead');
  wp_send_json($notes,200);

}


function getEnquiries(){
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
    
    $enquiry->enquiry_meta = $meta;
    $enquiry->display_name = $lead->display_name;
    $enquiry->lead = $lead;
    $enquiry->matched = [];

    array_push($results, $enquiry);
  }
  $all_enquires["data"]["results"] = $results;
  
  wp_send_json($all_enquires["data"],200);
}
function enquiryMatchedListing() {
  //disable lightspeed caching
  do_action( 'litespeed_control_set_nocache', 'nocache due to logged in' );
  if (! is_user_logged_in() ) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
    wp_send_json($ajax_response, 403);
    return; 
  }
  //lead-id must be provided
  if(!isset($_GET["enquiry-id"]) || empty($_GET["enquiry-id"])) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide enquiry-id.' );
    wp_send_json($ajax_response, 400);
    return; 
  }
  
  $enquiry_id =  $_GET["enquiry-id"];
  
  $enquiry = Houzez_Enquiry::get_enquiry($enquiry_id);

  $prop_page = (isset($_GET["prop_page"]) && !empty($_GET["enquiry-id"])) ? $_GET["prop_page"] : "1";
  set_query_var('paged', $prop_page);

  
  if( !empty($enquiry) ) {
    $meta = maybe_unserialize($enquiry->enquiry_meta);
    $lead = Houzez_Leads::get_lead($enquiry->lead_id);
    $matched_query = matched_listings($enquiry->enquiry_meta);

    if($matched_query->have_posts()):
      while ($matched_query->have_posts()): $matched_query->the_post();
        $property = $matched_query->post; 
        $post_id = $property->ID;
        $property_meta = get_post_meta($post_id);
        $property->property_meta = $property_meta;
      endwhile;
    endif;

    $enquiry->enquiry_meta = $meta;
    $enquiry->display_name = $lead->display_name;
    $enquiry->lead = $lead;
    $enquiry->matched = $matched_query->posts;

    
  }
  
  
  wp_send_json($enquiry,200);

}
function enquiryNotes() {
  //disable lightspeed caching
  do_action( 'litespeed_control_set_nocache', 'nocache due to logged in' );
  if (! is_user_logged_in() ) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
    wp_send_json($ajax_response, 403);
    return; 
  }
  //lead-id must be provided
  if(!isset($_GET["enquiry-id"]) || empty($_GET["enquiry-id"])) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide enquiry-id.' );
    wp_send_json($ajax_response, 400);
    return; 
  }
  $belong_to = isset($_GET['enquiry-id']) ? $_GET['enquiry-id'] : '';
  $notes = Houzez_CRM_Notes::get_notes($belong_to, 'enquiry');
  wp_send_json($notes,200);

}
function sendMatchedListingEmail() {
  if (! is_user_logged_in() ) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
    wp_send_json($ajax_response, 403);
    return; 
  }
  
  if(!isset($_POST["ids"]) || empty($_POST["ids"])) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide selected property ids.' );
    wp_send_json($ajax_response, 400);
    return; 
  }
  if(!isset($_POST["email_to"]) || empty($_POST["email_to"])) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide email_to.' );
    wp_send_json($ajax_response, 400);
    return; 
  }
  
  do_action("wp_ajax_houzez_match_listing_email");
  
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

function addLead() {
  //calls Houzez_Lead->add_lead();
  do_action("wp_ajax_houzez_crm_add_lead");
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
function addPropertyRequest() {

  $_POST['is_estimation'] = 'yes';
  $_POST['email_to'] = get_option( 'admin_email' );

  global $houzez_options;
  $houzez_options['enable_reCaptcha'] = 0;

  do_action("wp_ajax_houzez_ele_inquiry_form");
  
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

function addNote() {
  if (! is_user_logged_in() ) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
    wp_send_json($ajax_response, 403);
    return; 
  }
  $nonce = wp_create_nonce('note_add_nonce');
  $_REQUEST['security'] = $nonce;
  if(!isset($_POST["note"]) || empty($_POST["note"])) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide note.' );
    wp_send_json($ajax_response, 400);
    return; 
  }
  if(!isset($_POST["note_type"]) || empty($_POST["note_type"])) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide note type.' );
    wp_send_json($ajax_response, 400);
    return; 
  }
  if(!isset($_POST["belong_to"]) || empty($_POST["belong_to"])) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide note parent.' );
    wp_send_json($ajax_response, 400);
    return; 
  }
  do_action("wp_ajax_houzez_crm_add_note");
  
}
function deleteNote() {

  if (! is_user_logged_in() ) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
    wp_send_json($ajax_response, 403);
    return; 
  }
  if(!isset($_POST["note_id"]) || empty($_POST["note_id"])) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide note parent.' );
    wp_send_json($ajax_response, 400);
    return; 
  }
  //needs enquiry id in var ids
  do_action("wp_ajax_houzez_delete_note");
}

function updateDealData() {
  if (! is_user_logged_in() ) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
    wp_send_json($ajax_response, 403);
    return; 
  }
  if(!isset($_POST["deal_id"]) || empty($_POST["deal_id"])) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide deal_id.' );
    wp_send_json($ajax_response, 400);
    return; 
  }

  if(!isset($_POST["purpose"]) || empty($_POST["purpose"])) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide purpose.' );
    wp_send_json($ajax_response, 400);
    return; 
  }
  if(!isset($_POST["deal_data"]) || empty($_POST["deal_data"])) {
    $ajax_response = array( 'success' => false, 'reason' => 'Please provide deal_data.' );
    wp_send_json($ajax_response, 400);
    return; 
  }
  
  //purposes: crm_set_deal_status, crm_set_deal_next_action, crm_set_action_due, crm_set_last_contact_date
  $purpose = $_POST["purpose"];
  
  do_action("wp_ajax_".$purpose);
  
  
}

