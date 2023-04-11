<?php
/**
 * Extends api for property post type, it appends many data that's not available in default wp-rest-api.
 *
 *
 * @package Houzez Mobile Api
 * @since Houzi 1.0
 * @author Adil Soomro
 */

add_image_size( 'custom-size', 200, 200 );
// The filter callback function.
function houzez_property_rest_base_name( $string) {
  $options = get_option( 'houzi_rest_api_options' ); // Array of All Options
  
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
  $property_id_from_url = $params["id"] ?? "";
  $isediting = $params["editing"] ?? "";
  

  $should_append_extra_data = !empty( $property_id_from_url);

  //append extra data only when there's an id provided.
  if($should_append_extra_data == true) {
    hm_postImages($response);
    hm_postFeature($response);
    hm_postAddress($response);
    hm_postAttachments($response);
    
    
    $response->data['is_fav'] = !empty( $property_id_from_url) ? isFavoriteProperty($property_id_from_url) : false;
    $response->data['property_meta']['agent_info'] = houzez20_property_contact_form();
  } else {
    $response->data['thumbnail']   = get_the_post_thumbnail_url( get_the_ID(), 'houzez-property-thumb-image' );
  }
  hm_postAttr($response);
  
  $property_meta = $response->data['property_meta'];
  
  $additional_features = $property_meta["additional_features"];
  $floor_plans = $property_meta["floor_plans"];
  $fave_multi_units = $property_meta["fave_multi_units"] ?? null;

  unset($response->data['property_meta']['additional_features']);
  unset($response->data['property_meta']['floor_plans']);
  unset($response->data['property_meta']['fave_multi_units']);
  
  $response->data['property_meta']['additional_features'] = unserialize($additional_features[0]);
  $response->data['property_meta']['floor_plans'] = unserialize($floor_plans[0]);
  
  $response->data['property_meta']['fave_multi_units'] = $fave_multi_units ? unserialize($fave_multi_units[0]) : false;
  

  if(empty($isediting)) {
    unset($response->data['property_meta']['fave_property_images']);
  }
  unset($response->data['property_meta']['houzez_views_by_date']);
  unset($response->data['property_meta']['_vc_post_settings']);
  
  $should_add_agent_agency_info = $params["agent_agency_info"] ?? "";
  if (!empty($should_add_agent_agency_info) && $should_add_agent_agency_info == 'yes') {
    $response->data['property_meta']['agent_agency_info'] = property_agency_agent_info();
  }
  
  


  return $response;
}

function hm_postImages(&$response)
{
  foreach ($response->data['property_meta']['fave_property_images'] as $imgID) :
    $response->data['property_images'][] = wp_get_attachment_url($imgID);
    $response->data['property_images_thumb'][] = wp_get_attachment_image_src($imgID, 'thumbnail', true )[0];
  endforeach;
}

function hm_postAttachments(&$response)
{
  foreach ($response->data['property_meta']['fave_attachments'] as $attachment_id) :
    // $response->data['attachments'][] = wp_get_attachment_url($attachment_id);
    $attachment_metadata = wp_get_attachment_metadata($attachment_id);
    $file_name = basename ( get_attached_file( $attachment_id ) );
    $file_url = wp_get_attachment_url($attachment_id);
    $file_size = size_format( filesize( get_attached_file( $attachment_id ) ), 2 );
    $response->data['attachments'][] = array(
      'url' => $file_url,
      'name' => $file_name,
      'size' => $file_size
  );
  endforeach;

}

function hm_postFeature(&$response)
{
  // $response->data['property_features'] = get_the_terms( $response->data['id'], 'property_feature' );

  // $response->data['property_features'] = wp_get_post_terms(
  //   $response->data['id'],
  //   ['property_feature'],
  //   array('fields' => 'names')
  // );

  $response->data['property_features'] = getCurrentLanguageTermsOnly($response->data['id'], 'property_feature');
}


function hm_postAddress(&$response)
{
  $address_taxonomies = array();
  if (taxonomy_exists( 'property_country' )) {
      array_push($address_taxonomies, 'property_country' );
  }
  if (taxonomy_exists( 'property_state' )) {
      array_push($address_taxonomies, 'property_state' );
  }
  if (taxonomy_exists( 'property_city' )) {
      array_push($address_taxonomies, 'property_city' );
  }
  if (taxonomy_exists( 'property_area' )) {
      array_push($address_taxonomies, 'property_area' );
  }
  $address_array = wp_get_post_terms(
    $response->data['id'],
    $address_taxonomies
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
    ['property_type', 'property_status', 'property_label'],
  );
  $current_lang = apply_filters( 'wpml_current_language', "en" );
  $property_attributes = array();
  foreach ($property_attr as $attribute) :
    $localizez_term_id = apply_filters( 'wpml_object_id', $attribute->term_id, $attribute->taxonomy, FALSE, $current_lang );
    $term = get_term( $localizez_term_id );
    if (empty($property_attributes[$attribute->taxonomy])) {
      $property_attributes[$attribute->taxonomy] = $term->name;
    }
    $response->data[$attribute->taxonomy."_text"][] = $term->name;
  endforeach;
  $response->data['property_attr'] = $property_attributes;

}

if(!function_exists('property_agency_agent_info')) {
  function property_agency_agent_info($is_top = true, $luxury = false) {
      
      $prop_agent = $picture = $agent_id = '';
      
      $return_array = array();
      $listing_agent_info = array();

      $agent_display = houzez_get_listing_data('agent_display_option');
      $is_single_agent = true;

      if( $agent_display != 'none' ) {
          if( $agent_display == 'agent_info' ) {

              $agents_ids = houzez_get_listing_data('agents', false);

              $agents_ids = array_filter( $agents_ids, function($hz){
                  return ( $hz > 0 );
              });

              $agents_ids = array_unique( $agents_ids );

              if ( ! empty( $agents_ids ) ) {
                  $agents_count = count( $agents_ids );
                  if ( $agents_count > 1 ) :
                      $is_single_agent = false;
                  endif;
                  $listing_agent = '';
                  foreach ( $agents_ids as $agent ) {
                      if ( 0 < intval( $agent ) ) {

                          $agent_id = intval( $agent );
                          
                          $prop_agent = get_the_title( $agent_id );
                          $thumb_id = get_post_thumbnail_id( $agent_id );
                          $thumb_url_array = wp_get_attachment_image_src( $thumb_id, array(150,150), true );
                          $prop_agent_photo_url = $thumb_url_array[0];
                          
                          if( empty( $prop_agent_photo_url )) {
                              
                              $picture = HOUZEZ_IMAGE. 'profile-avatar.png';
                          } else {
                              
                              $picture = $prop_agent_photo_url;
                          }

                      }
                  }
              }

          } elseif( $agent_display == 'agency_info' ) {
              $agent_id = get_post_meta( get_the_ID(), 'fave_property_agency', true );

              $prop_agent = get_the_title( $agent_id );
              $thumb_id = get_post_thumbnail_id( $agent_id );
              $thumb_url_array = wp_get_attachment_image_src( $thumb_id, array(150,150), true );
              $prop_agent_photo_url = $thumb_url_array[0];
              

              if( empty( $prop_agent_photo_url )) {
                  $picture = HOUZEZ_IMAGE. 'profile-avatar.png';
              } else {
                  $picture = $prop_agent_photo_url;
              }
              
          } else {
              $prop_agent = get_the_author();
              $prop_agent_photo_url = get_the_author_meta( 'fave_author_custom_picture' );
              $agent_args = array();
              $agent_id   = get_the_author_meta( 'ID' );

              if( empty( $prop_agent_photo_url )) {
                  $picture = HOUZEZ_IMAGE. 'profile-avatar.png';
              } else {
                  $picture = $prop_agent_photo_url;
              }
          }
          $return_array['agent_name'] = $prop_agent;
          $return_array['picture'] = $picture;
          $return_array['agent_type'] = $agent_display;
          $return_array['agent_id'] = $agent_id;
      }

      return $return_array;
  } // End function
}


//--------------- use fulllink--------------------------

// https://wordpress.stackexchange.com/questions/296440/filter-out-results-from-rest-api
// https://developer.wordpress.org/reference/hooks/rest_prepare_this-post_type/
// https://wordpress.stackexchange.com/questions/202362/unset-data-in-custom-post-type-wordpress-api-wp-

