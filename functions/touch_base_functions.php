<?php


add_action( 'rest_api_init', function () {
  register_rest_route( 'houzez-mobile-api/v1', '/touch-base', array(
    'methods' => 'GET',
    'callback' => 'getMetaData',
  ));
  register_rest_route( 'houzez-mobile-api/v1', '/get-terms', array(
    'methods' => 'GET',
    'callback' => 'getTerms',
  ));
  register_rest_route( 'houzez-mobile-api/v1', '/houzi-setup-status', array(
    'methods' => 'POST',
    'callback' => 'houziSetupStatus',
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
  $terms = $_GET['term'];
  if( is_array( $terms )) {
    foreach ($terms as $term):
      add_term_to_response($response, $term);
    endforeach;
  } else {
    add_term_to_response($response, $terms);
  }
  


  wp_send_json($response, 200);
}
function getMetaData() {
    
    $response = array();
    
    $response['success'] = true;
    $response['version'] = HOUZI_REST_API_VERSION;
    $response['houzez_ver'] = HOUZEZ_THEME_VERSION;
    $response['payment_enabled'] = houzez_option( 'enable_paid_submission', 'no' );
    $response['default_currency'] = houzez_get_currency();
    $response['currency_position'] = houzez_option( 'currency_position', '$' );
    $response['thousands_separator'] = houzez_option( 'thousands_separator', ',' );
    $response['decimal_point_separator'] = houzez_option( 'decimal_point_separator', '.' );
    $response['num_decimals'] = houzez_option( 'decimals', '0' );
    $response['add-prop-gdpr-enabled'] = houzez_option('add-prop-gdpr-enabled');
    
    $response['register_first_name'] = houzez_option('register_first_name', 0);
    $response['register_last_name'] = houzez_option('register_last_name', 0);
    $response['register_mobile'] = houzez_option('register_mobile', 0);
    $response['enable_password'] = houzez_option('enable_password', 0);
    
    $response['measurement_unit_global']  = houzez_option('measurement_unit_global');
    
    $prop_size_prefix = houzez_option('measurement_unit');
    $response['measurement_unit_global']  = $prop_size_prefix;
    if( $prop_size_prefix == 'sqft' ) {
      $response['measurement_unit_text'] = houzez_option('measurement_unit_sqft_text');
    } elseif( $prop_size_prefix == 'sq_meter' ) {
      $response['measurement_unit_text'] = houzez_option('measurement_unit_square_meter_text');
    }

    $options = get_option( 'houzi_rest_api_options' ); // Array of All Options
    $response['featured_product_id'] = get_option( 'iap_product_id_1' );
    $response['per_listing_product_id'] = get_option( 'iap_product_id_2' );
    $houzi_config = html_entity_decode( $options['mobile_app_config']);
    $response['mobile_app_config'] = json_decode($houzi_config, true, JSON_UNESCAPED_SLASHES);

    $radius_unit = houzez_option('radius_unit') ?? null;
    if (isset($radius_unit)) {
      $response['radius_unit']  = houzez_option('radius_unit');
    }

    $options = get_option( 'houzi_rest_api_options' ); // Array of All Options

    $houzi_config_array = $options['mobile_app_config'] ?? null;
    if (isset($houzi_config_array)) {
      $houzi_config = html_entity_decode( $options['mobile_app_config']);
      $response['mobile_app_config'] = json_decode($houzi_config, true, JSON_UNESCAPED_SLASHES);
    }
    
    $houzi_config_dev_array = $options['mobile_app_config_dev'] ?? null;
    if (isset($houzi_config_dev_array)) {
        $houzi_config_dev = html_entity_decode( $options['mobile_app_config_dev']);
        $response['mobile_app_config_dev'] = json_decode($houzi_config_dev, true, JSON_UNESCAPED_SLASHES);
    }

    add_term_to_response($response, 'property_country');
    add_term_to_response($response, 'property_state');
    add_term_to_response($response, 'property_city');
    //add_term_to_response($response, 'property_area');

    add_term_to_response($response, 'property_type');
    add_term_to_response($response, 'property_label');
    add_term_to_response($response, 'property_status');
    add_term_to_response($response, 'property_feature');
    
    $houzi_eleven = get_option( 'houzi_eleven' );
    $eleven_text = get_option( 'houzi_eleven_text' );
  
    $response['licensed'] = !empty($houzi_eleven) && !empty($eleven_text);
    $response['property_reviews'] = houzez_option( 'property_reviews' );
    $response['property_area'] = [];
    $response['schedule_time_slots'] = houzez_option('schedule_time_slots');
      
    add_custom_fields_to_response($response);
    add_roles_to_response($response);
    
    $response['enquiry_type'] = hcrm_get_option('enquiry_type', 'hcrm_enquiry_settings', esc_html__('Purchase, Rent, Sell, Miss, Evaluation, Mortgage', 'houzez'));
    $response['lead_prefix'] = hcrm_get_option('prefix', 'hcrm_lead_settings', esc_html__('Mr, Mrs, Ms, Miss, Dr, Prof, Mr & Mrs', 'houzez'));
    $response['lead_source'] = hcrm_get_option('source', 'hcrm_lead_settings', esc_html__('Website, Newspaper, Friend, Google, Facebook', 'houzez'));
    $response['deal_status'] = hcrm_get_option('status', 'hcrm_deals_settings', esc_html__('New Lead, Meeting Scheduled, Qualified, Proposal Sent, Called, Negotiation, Email Sent', 'houzez'));
    $response['deal_next_action'] = hcrm_get_option('next_action', 'hcrm_deals_settings', esc_html__('Qualification, Demo, Call, Send a Proposal, Send an Email, Follow Up, Meeting', 'houzez'));
    wp_send_json($response, 200);
    //echo json_encode($response);
}
function add_custom_fields_to_response(&$response){
  $fields_array = Houzez_Fields_Builder::get_form_fields();
  $custom_fields = array();
  if( !empty($fields_array) ) {
    foreach ($fields_array as $field) {
      $field_type = $field->type;
      
      $field_title = $field->label;
      $field_placeholder = $field->placeholder;

      $field->label = houzez_wpml_translate_single_string($field_title);
      $field->placeholder = houzez_wpml_translate_single_string($field_placeholder);

      if($field_type == 'select' || $field_type == 'multiselect') { 
        $options = unserialize($field->fvalues);
        $options_array = array();
        if(!empty($options)) {
        	foreach ($options as $key => $val) {
				    $select_options = houzez_wpml_translate_single_string($val);
				    $options_array[$key] = $select_options;
        	}
        }
        $field->fvalues = $options_array;
      } elseif( $field_type == 'checkbox_list' || $field_type == 'radio' ) {
        $options = unserialize($field->fvalues);
        $options    = explode( ',', $options );
        $options    = array_filter( array_map( 'trim', $options ) );
        $field->fvalues = $options;
      }
    
      array_push($custom_fields,$field);
    }
  }
  
  $response['custom_fields'] = $custom_fields;
}
function add_term_to_response(&$response, $key){
    if (!taxonomy_exists($key)) {
      $response[$key] = [];
      return;
    }
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
        $term_img_array = wp_get_attachment_image_src( $taxonomy_img_id, 'full' );
        $term_img = !empty($term_img_array) ? $term_img_array[0] : "";
        $term_img_thumb_array = wp_get_attachment_image_src($taxonomy_img_id, 'thumbnail', true );
        $term_img_thumb = !empty($term_img_thumb_array) ? $term_img_thumb_array[0] : "";
        $term->thumbnail = $term_img_thumb;
        $term->full = $term_img;
      }
      if ($key == 'property_area') {
        $term_meta = get_option( "_houzez_property_area_$term->term_id");
        if( $term_meta ) {
          $term->fave_parent_term =  $term_meta['parent_city'];
        }
      }
      if ($key == 'property_city') {
        $term_meta = get_option( "_houzez_property_city_$term->term_id");
        if( $term_meta ) {
          $term->fave_parent_term = $term_meta['parent_state'];
        }
      }
      if ($key == 'property_state') {
        $term_meta = get_option( "_houzez_property_state_$term->term_id");
        if( $term_meta ) {
          $term->fave_parent_term = $term_meta['parent_country'];
        }
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
  $response['all_user_roles'] = ["houzez_agent", "houzez_agency", "houzez_owner", "houzez_buyer", "houzez_seller", "houzez_manager"];
  
}

function houziSetupStatus() {
    
  $response = array();
  
  $response['success'] = true;
  $response['version'] = HOUZI_REST_API_VERSION;
  $response['houzez_ver'] = HOUZEZ_THEME_VERSION;

  $houzi_eleven = get_option( 'houzi_eleven' );
  $eleven_text = get_option( 'houzi_eleven_text' );
  $response['licensed'] = !empty($houzi_eleven) && !empty($eleven_text);
  $response['jwt_auth_active'] = class_exists('Jwt_Auth_Public');
  $response['jwt_auth_key'] = defined('JWT_AUTH_SECRET_KEY');

  wp_send_json($response, 200);
}

