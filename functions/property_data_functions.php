<?php


add_image_size( 'custom-size', 200, 200 );
// The filter callback function.
function houzez_property_rest_base_name( $string) {
  $options = get_option( 'houzez_mobile_api_options' ); // Array of All Options
  
  if ($options != null && isset($options['fix_property_type_in_translation_0']) && $options['fix_property_type_in_translation_0'] === 'fix_property_type_in_translation_0' ) {
    // rest_base property should always be properties.
    return "properties";
  }
  return $string;
}
add_filter( 'houzez_property_rest_base', 'houzez_property_rest_base_name', 10, 1 );


add_filter('rest_prepare_property', 'preparePropertyData', 10, 3);

function preparePropertyData($response, $post, $request)
{

  //TODO: add context params to only modify this result when its coming from app context.
  $params = $request->get_params();
  $property_id_from_url = $params["id"];
  $isediting = $params["editing"];
  $should_append_extra_data = !empty( $property_id_from_url);

  //append extra data only when there's an id provided.
  if($should_append_extra_data == true) {
    hm_postImages($response);
    hm_postFeature($response);
    hm_postAddress($response);
    
    $response->data['is_fav'] = isFavoriteProperty($property_id_from_url);
    $response->data['property_meta']['agent_info'] = houzez20_property_contact_form();
  } else {
    $response->data['thumbnail']   = get_the_post_thumbnail_url( get_the_ID(), 'houzez-property-thumb-image' );
  }
  hm_postAttr($response);
  //$response->data['params'] = $params;
  $property_meta = $response->data['property_meta'];
  
  $additional_features = $property_meta["additional_features"];
  $floor_plans = $property_meta["floor_plans"];
  $fave_multi_units = $property_meta["fave_multi_units"];

  unset($response->data['property_meta']['additional_features']);
  unset($response->data['property_meta']['floor_plans']);
  unset($response->data['property_meta']['fave_multi_units']);
  
  $response->data['property_meta']['additional_features'] = unserialize($additional_features[0]);
  $response->data['property_meta']['floor_plans'] = unserialize($floor_plans[0]);
  $response->data['property_meta']['fave_multi_units'] = unserialize($fave_multi_units[0]);

  if(empty($isediting)) {
    unset($response->data['property_meta']['fave_property_images']);
  }
  unset($response->data['property_meta']['houzez_views_by_date']);
  unset($response->data['property_meta']['_vc_post_settings']);
  
  unset($response->data['property_feature']);
  unset($response->data['property_type']);
  unset($response->data['property_status']);
  unset($response->data['property_label']);
  unset($response->data['property_country']);
  unset($response->data['property_state']);
  unset($response->data['property_city']);

  unset($response->data['property_area']);
  // unset($response->data['guid']);
  
  // unset($response->data['modified_gmt']);
  // unset($response->data['menu_order']);
  //$response->data->remove_link($_linkKey);


  return $response;
}

function hm_postImages(&$response)
{
  foreach ($response->data['property_meta']['fave_property_images'] as $imgID) :
    $response->data['property_images'][] = wp_get_attachment_url($imgID);
    $response->data['property_images_thumb'][] = wp_get_attachment_image_src($imgID, 'thumbnail', true )[0];
  endforeach;
}

function hm_postFeature(&$response)
{
  // $response->data['property_features'] = get_the_terms( $response->data['id'], 'property_feature' );

  $response->data['property_features'] = wp_get_post_terms(
    $response->data['id'],
    ['property_feature'],
    array('fields' => 'names')
  );
}


function hm_postAddress(&$response)
{

  $address_array = wp_get_post_terms(
    $response->data['id'],
    ['property_country', 'property_state', 'property_city', 'property_area']
  );
  $property_address = array();
  foreach ($address_array as $address) :
    $property_address[$address->taxonomy] = $address->name;
  endforeach;
  $response->data['property_address'] = $property_address;
}

function hm_postAttr(&$response)
{
  $property_attr = wp_get_post_terms(
    $response->data['id'],
    ['property_type', 'property_status', 'property_label']

  );

  $property_attributes = array();
  foreach ($property_attr as $attribute) :
    $property_attributes[$attribute->taxonomy] = $attribute->name;
  endforeach;
  $response->data['property_attr'] = $property_attributes;
  
}


//--------------- use fulllink--------------------------

// https://wordpress.stackexchange.com/questions/296440/filter-out-results-from-rest-api
// https://developer.wordpress.org/reference/hooks/rest_prepare_this-post_type/
// https://wordpress.stackexchange.com/questions/202362/unset-data-in-custom-post-type-wordpress-api-wp-

