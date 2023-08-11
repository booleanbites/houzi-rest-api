<?php
/**
 * Functions to handle property search related apis, save searches.
 *
 *
 * @package Houzez Mobile Api
 * @since Houzi 1.0
 * @author Adil Soomro
 */
add_filter( 'rest_property_query', function( $args, $request ){
    //featured property
    if ( $request->get_param( 'fave_featured' ) ) {
        $args['meta_key']   = 'fave_featured';
        $args['meta_value'] = $request->get_param( 'fave_featured' );
    }
    if ( $request->get_param( 'fave_virtual_tour' ) ) {
        $args['meta_key']   = 'fave_virtual_tour';
        $args['meta_value'] = $request->get_param( 'fave_virtual_tour' );
    }
    if ( $request->get_param( 'fave_agents' ) ) {
        // $args['meta_key']   = 'fave_agents';
        // $args['meta_value'] = $request->get_param( 'fave_agents' );
        $agent_id = $request->get_param( 'fave_agents' );
        $args['meta_query']  = array(
            'relation' => 'AND',
            array(
                'key' => 'fave_agents',
                'value' => $agent_id,
                'compare' => '='
            ),
            array(
                'key' => 'fave_agent_display_option',
                'value' => 'agent_info',
                'compare' => '='
            )
        );
    }
    if ( $request->get_param( 'fave_property_agency' ) ) {
        // $args['meta_key']   = 'fave_property_agency';
        // $args['meta_value'] = $request->get_param( 'fave_property_agency' );
        
        $agency_id = $request->get_param( 'fave_property_agency' );
        $agents_array = array();
        $agency_agents_ids = Houzez_Query::loop_agency_agents_ids($agency_id);
        
        
        if( !empty($agency_agents_ids) ) {
            $agents_array = array(
                'key' => 'fave_agents',
                'value' => $agency_agents_ids,
                'compare' => 'IN',
            );
        }
        
        $args['meta_query'] = array(
            'relation' => 'OR',
            $agents_array,
            array(
                'relation' => 'AND',
                array(
                    'key'     => 'fave_property_agency',
                    'value'   => $agency_id,
                    'compare' => '='
                ),
                array(
                    'key'     => 'fave_agent_display_option',
                    'value'   => 'agency_info',
                    'compare' => '='
                )
            ),
        );
        
    }

    if ( $request->get_param( 'property_city' ) ) {
        $args['taxonomy_name']   = 'property_city';
        $args['term'] = $request->get_param( 'property_city' );
    }
    if ( $request->get_param( 'property_type' ) ) {
        $args['taxonomy_name']   = 'property_type';
        $args['term'] = $request->get_param( 'property_type' );
    }
    
  return $args;
}, 10, 2 );

//-----------------------------Lightspeed exclude URLs-------------------------------------
// By default all POST URLs aren't cached
add_action( 'litespeed_init', function () {
    
    //these URLs need to be excluded from lightspeed caches
    $exclude_url_list = array(
        "favorite-properties",
        "saved-searches"
    );
    foreach ($exclude_url_list as $exclude_url) {
        if (strpos($_SERVER['REQUEST_URI'], $exclude_url) !== FALSE) {
            do_action( 'litespeed_control_set_nocache', 'no-cache for rest api' );
        }
    }
    //add these URLs to cache if required (even POSTs)
    $include_url_list = array(
        "sample-url",
        "search-test"
    );
    foreach ($include_url_list as $include_url) {
        if (strpos($_SERVER['REQUEST_URI'], $include_url) !== FALSE) {
            do_action( 'litespeed_control_set_cacheable', 'cache for rest api' );
        }
    }
});


// houzez-mobile-api/v1/search-properties
add_action( 'rest_api_init', function () {
  register_rest_route( 'houzez-mobile-api/v1', '/search-properties', array(
    'methods' => 'POST',
    'callback' => 'searchProperties',
  ));

  register_rest_route( 'houzez-mobile-api/v1', '/search-test', array(
    'methods' => 'POST',
    'callback' => 'searchPropertiesTest',
  ));

  register_rest_route( 'houzez-mobile-api/v1', '/get-property-detail', array(
    'methods' => 'GET',
    'callback' => 'getPropertDetail',
  ));

  register_rest_route( 'houzez-mobile-api/v1', '/similar-properties', array(
    'methods' => 'GET',
    'callback' => 'getSimilarProperties',
  ));

  register_rest_route( 'houzez-mobile-api/v1', '/favorite-properties', array(
    'methods' => 'GET',
    'callback' => 'getFavoriteProperties',
  ));

  register_rest_route( 'houzez-mobile-api/v1', '/save-search', array(
    'methods' => 'POST',
    'callback' => 'saveSearch',
  ));
  register_rest_route( 'houzez-mobile-api/v1', '/saved-searches', array(
    'methods' => 'GET',
    'callback' => 'listSavedSearches',
  ));
  register_rest_route( 'houzez-mobile-api/v1', '/view-saved-search', array(
    'methods' => 'POST',
    'callback' => 'viewSavedSearch',
  ));
  register_rest_route( 'houzez-mobile-api/v1', '/delete-saved-search', array(
    'methods' => 'POST',
    'callback' => 'deleteSearch',
  ));

});


function getPropertDetail(){
    $property_post_id   =   (isset($_GET['property_id']))?$_GET['property_id']:0;
    $prop               =   get_post( $property_post_id );

    if( isset($prop->ID) ){
        echo json_encode( array( 'getProperties' => true,  'property' => propertyNode( $prop ), 'original' => $prop ) );
    }else{
        echo json_encode( array( 'getProperties' => false ));
    }

    exit;
}

/* ******************************************************************************************************** */
    /*
    Main Search for API
    */
/* ******************************************************************************************************** */
function searchProperties() {
    
    $query_args = setupSearchQuery();
    
    queryPropertiesAndSendJSON($query_args);
}
function searchPropertiesTest() {
    
    $query_args = setupSearchQueryForTesting();
    
    queryPropertiesAndSendJSON($query_args);
}
function queryPropertiesAndSendJSON($query_actual) {

    $keyword_filters = isset( $_POST['keyword_filters'] ) ? ( $_POST['keyword_filters'] ) : '';

    if( !empty( $keyword_filters ) ) {
        
        $jsonProcessed = stripslashes($keyword_filters);

        $decodedJson = json_decode($jsonProcessed, true);

        $keyword_filters_Obj = $decodedJson["keyword_filters"];

        $search_queries = $keyword_filters_Obj;

        add_filter('posts_where', function($where) use ($search_queries) {
            global $wp_query;
            return custom_title_content_filter($where, $wp_query, $search_queries);
        }, 10, 2);

        $query_args = new WP_Query( $query_actual );

        // Remove the filter after the query
        remove_filter('posts_where', function($where) use ($search_queries) {
            global $wp_query;
            return custom_title_content_filter($where, $wp_query, $search_queries);
        }, 10);
    } else {
        $query_args = new WP_Query( $query_actual );
    }

    $properties = array();
    $found_posts = $query_args->found_posts;
    while( $query_args->have_posts() ):
        $query_args->the_post();
        $property = $query_args->post;
        array_push($properties, propertyNode($property) );
        
    endwhile;

    wp_reset_postdata();
    wp_send_json( array( 'success' => true ,'count' => $found_posts , 'result' => $properties), 200);
    //wp_send_json( array( 'success' => true, 'query' => $query_actual), 200);
}

function custom_title_content_filter($where, $query, $search_queries) {
    global $wpdb;

    // Add your custom filtering conditions here
    if (!empty($search_queries)) {
        $where .= " AND (";
        $first = true;
        foreach ($search_queries as $search_query_data) {
            $relation = isset($search_query_data['query_type']) ? strtoupper($search_query_data['query_type']) : 'OR';
            $keywords = isset($search_query_data['value']) ? explode(",",$search_query_data['value']) : array();

            if (!empty($keywords)) {
                if (!$first) {
                    // $where .= " $relation ";
                    $where .= " AND ";
                }
                $where .= "(";
                $innerFirst = true;
                foreach ($keywords as $search_query) {
                    if (!$innerFirst) {
                        // $where .= " OR ";
                        $where .= " $relation ";
                    }
                    $where .= "{$wpdb->posts}.post_title LIKE '%$search_query%' OR {$wpdb->posts}.post_content LIKE '%$search_query%'";
                    $innerFirst = false;
                }
                $where .= ")";
                $first = false;
            }
        }
        $where .= ")";
    }

    return $where;
}

function setupSearchQuery() {
    $meta_query = array();
    $tax_query = array();
    $date_query = array();
    $allowed_html = array();
    $keyword_array =  '';

    //$dummy_array = array();

    $custom_fields_values = isset($_POST['custom_fields_values']) ? $_POST['custom_fields_values'] : '';
    
    // if(!empty($custom_fields_values)) {
    //     foreach ($custom_fields_values as $key => $value) {
    //         $dummy_array[$key] = $value;
    //     }
    // }

    $map_only = isset( $_POST[ 'map_only' ] ) && 'on' == $_POST[ 'map_only' ];
    $initial_city = isset($_POST['initial_city']) ? $_POST['initial_city'] : '';
    $features = isset($_POST['features']) ? $_POST['features'] : '';
    $keyword = isset($_POST['keyword']) ? $_POST['keyword'] : '';
    $country = isset($_POST['country']) ? ($_POST['country']) : '';
    $state = isset($_POST['state']) ? ($_POST['state']) : '';
    $featured = isset($_POST['featured']) ? ($_POST['featured']) : '';
    $virtual_tour = isset( $_POST['virtual_tour'] ) ? boolval( $_POST['virtual_tour'] ) : false;
    $location = isset($_POST['location']) ? ($_POST['location']) : '';
    $area = isset($_POST['area']) ? ($_POST['area']) : '';
    $status = isset($_POST['status']) ? ($_POST['status']) : '';
    $type = isset($_POST['type']) ? ($_POST['type']) : '';
    $label = isset($_POST['label']) ? ($_POST['label']) : '';
    $property_id = isset($_POST['property_id']) ? ($_POST['property_id']) : '';
    $bedrooms = isset($_POST['bedrooms']) ? ($_POST['bedrooms']) : '';
    $rooms = isset($_POST['rooms']) ? ($_POST['rooms']) : '';
    $bathrooms = isset($_POST['bathrooms']) ? ($_POST['bathrooms']) : '';
    $beds_baths_criteria = isset($_POST['beds_baths_criteria']) ? ($_POST['beds_baths_criteria']) : '';
    $min_price = isset($_POST['min_price']) ? ($_POST['min_price']) : (isset($_POST['min-price']) ? ($_POST['min-price']) : '');
    $max_price = isset($_POST['max_price']) ? ($_POST['max_price']) : (isset($_POST['max-price']) ? ($_POST['max-price']) : '');
    $currency = isset($_POST['currency']) ? ($_POST['currency']) : '';
	$min_area = isset($_POST['min_area']) ? ($_POST['min_area']) : (isset($_POST['min-area']) ? ($_POST['min-area']) : '');
    $max_area = isset($_POST['max_area']) ? ($_POST['max_area']) : (isset($_POST['max-area']) ? ($_POST['max-area']) : '');
    $publish_date = isset($_POST['publish_date']) ? ($_POST['publish_date']) : '';

    $search_location = isset( $_POST[ 'search_location' ] ) ? esc_attr( $_POST[ 'search_location' ] ) : false;
    $use_radius = isset( $_POST[ 'use_radius' ] ) && 'on' == $_POST[ 'use_radius' ];
    $search_lat = isset($_POST['search_lat']) ? (float) $_POST['search_lat'] : false;
    $search_long = isset($_POST['search_long']) ? (float) $_POST['search_long'] : false;
    $search_radius = isset($_POST['search_radius']) ? (int) $_POST['search_radius'] : false;

    $agency_id = isset($_POST['fave_property_agency']) ? ($_POST['fave_property_agency']) : '';
    $agent_id = isset($_POST['fave_agents']) ? ($_POST['fave_agents']) : '';

    $meta_key_filters = isset( $_POST['meta_key_filters'] ) ? ( $_POST['meta_key_filters'] ) : '';

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///  Query Types variables
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    $country_query_type = isset($_POST['country_query_type']) ? ($_POST['country_query_type']) : '';
    $state_query_type = isset($_POST['state_query_type']) ? ($_POST['state_query_type']) : '';
    $area_query_type = isset($_POST['area_query_type']) ? ($_POST['area_query_type']) : '';
    $status_query_type = isset($_POST['status_query_type']) ? ($_POST['status_query_type']) : '';
    $type_query_type = isset($_POST['type_query_type']) ? ($_POST['type_query_type']) : '';
    $label_query_type = isset($_POST['label_query_type']) ? ($_POST['label_query_type']) : '';
    $features_query_type = isset($_POST['features_query_type']) ? $_POST['features_query_type'] : '';
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////


    $page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
    $per_page = isset($_POST['per_page']) ? (int) $_POST['per_page'] : 20;

    $prop_locations = array();
    houzez_get_terms_array( 'property_city', $prop_locations );

    $keyword_field = houzez_option('keyword_field');
    $beds_baths_search = houzez_option('beds_baths_search');
    $property_id_prefix = houzez_option('property_id_prefix');

    $property_id = str_replace($property_id_prefix, "", $property_id);

    $search_criteria = 'IN';
    if (!empty($beds_baths_criteria)) {
        $search_criteria = $beds_baths_criteria;
    } else {
        $search_criteria = '=';
        if( $beds_baths_search == 'greater') {
            $search_criteria = '>=';
        } else if( $beds_baths_search == 'like' ) {
            $search_criteria = 'LIKE';
        } else if( $beds_baths_search == 'equal' ) {
            $search_criteria = '=';
        }
    }

    $query_args = array(
        'post_type' => 'property',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    );

    $query_args = apply_filters('houzez_radius_filter', $query_args, $search_lat, $search_long, $search_radius, $use_radius, $search_location );

    $keyword = stripcslashes($keyword);

    if ( $keyword != '') {

        if( $keyword_field == 'prop_address' ) {
            $meta_keywork = wp_kses($keyword, $allowed_html);
            $address_array = array(
                'key' => 'fave_property_map_address',
                'value' => $meta_keywork,
                'type' => 'CHAR',
                'compare' => 'LIKE',
            );

            $street_array = array(
                'key' => 'fave_property_address',
                'value' => $meta_keywork,
                'type' => 'CHAR',
                'compare' => 'LIKE',
            );

            $zip_array = array(
                'key' => 'fave_property_zip',
                'value' => $meta_keywork,
                'type' => 'CHAR',
                'compare' => '=',
            );
            $propid_array = array(
                'key' => 'fave_property_id',
                'value' => str_replace($property_id_prefix, "", $meta_keywork),
                'type' => 'CHAR',
                'compare' => '=',
            );

            $keyword_array = array(
                'relation' => 'OR',
                $address_array,
                $street_array,
                $propid_array,
                $zip_array
            );
        } else if( $keyword_field == 'prop_city_state_county' ) {
            $taxlocation[] = sanitize_title (  esc_html( wp_kses($keyword, $allowed_html) ) );

            $_tax_query = Array();
            $_tax_query['relation'] = 'OR';

            $_tax_query[] = array(
                'taxonomy'     => 'property_area',
                'field'        => 'slug',
                'terms'        => $taxlocation
            );

            $_tax_query[] = array(
                'taxonomy'     => 'property_city',
                'field'        => 'slug',
                'terms'        => $taxlocation
            );

            $_tax_query[] = array(
                'taxonomy'      => 'property_state',
                'field'         => 'slug',
                'terms'         => $taxlocation
            );
            $tax_query[] = $_tax_query;

        } else {
            $keyword = trim( $keyword );
            if ( ! empty( $keyword ) ) {
                $query_args['s'] = $keyword;
            }
        }
    }

    //Date Query
    if( !empty($publish_date) ) {
        $publish_date = explode('/', $publish_date);
        $query_args['date_query'] = array(
            array(
                'year' => $publish_date[2],
                'compare'   => '>=',
            ),
            array(
                'month' => $publish_date[1],
                'compare'   => '>=',
            ),
            array(
                'day' => $publish_date[0],
                'compare'   => '>=',
            )
        );
    }


    //Custom Fields
    if(class_exists('Houzez_Fields_Builder') && !empty($custom_fields_values)) {
        $fields_array = Houzez_Fields_Builder::get_form_fields();
        if(!empty($fields_array)):
            
            foreach ( $fields_array as $value ):
                $field_title = $value->label;
                $field_name = $value->field_id;
                $is_search = $value->is_search;
                $field_type = $value->type;

                if( $is_search == 'yes' ) {
                    if(!empty($custom_fields_values[$field_name])) {
                        $compare = 'LIKE';
                        if( $field_type == 'checkbox_list' || $field_type == 'multiselect' ) {
                            $compare = 'IN';
                        }

                        $meta_query[] = array(
                            'key' => 'fave_'.$field_name,
                            'value' => $custom_fields_values[$field_name],
                            'type' => 'CHAR',
                            'compare' => '=',
                            'compare' => $compare,
                        );
                    }
                }
            endforeach; endif;
    }

    if(!empty($currency)) {
        $meta_query[] = array(
            'key' => 'fave_currency',
            'value' => $currency,
            'type' => 'CHAR',
            'compare' => '=',
        );
    }
    if(!empty($featured)) {
        $meta_query[] = array(
            'key' => 'fave_featured',
            'value' => $featured,
            'type' => 'CHAR',
            'compare' => '=',
        );
    }

    if(!empty($virtual_tour) && $virtual_tour == true) {
        $meta_query[] = array(
            array(
                'key' => 'fave_virtual_tour',
                'compare' => 'EXISTS' // Check if the meta key exists
            ),
            array(
                'key' => 'fave_virtual_tour',
                'value' => '',
                'compare' => '!=' // Check if the meta value is not empty
            )
        );
    }

    // Meta Queries
    if(!empty($map_only) && $map_only == true) {
        $meta_query[] = array(
            'key' => 'fave_property_map_address',
            'compare' => 'EXISTS',
        );
    }

    // Property ID
    if( !empty( $property_id )  ) {
        $meta_query[] = array(
            'key' => 'fave_property_id',
            'value'   => $property_id,
            'type'    => 'char',
            'compare' => '=',
        );
    }

    if( !empty($location) && $location != 'all' ) {
        $tax_query[] = array(
            'taxonomy' => 'property_city',
            'field' => 'slug',
            'terms' => $location
        );

    } else {
        if( $location == 'all' ) {
            /*$tax_query[] = array(
                'taxonomy' => 'property_city',
                'field' => 'slug',
                'terms' => $prop_locations
            );*/
        } else {
            if (!empty($initial_city)) {
                $tax_query[] = array(
                    'taxonomy' => 'property_city',
                    'field' => 'slug',
                    'terms' => $initial_city
                );
            }
        }
    }

    if( !empty($area) ) {

        if( !empty($area_query_type) ) {
            
            $area_array = array(
                'taxonomy' => 'property_area',
                'field' => 'slug',
                'terms' => $area
            );

            $tax_query[] = array(
                'relation' => $area_query_type,
                $area_array
            );

        } else {

            $tax_query[] = array(
                'taxonomy' => 'property_area',
                'field' => 'slug',
                'terms' => $area
            );
        }
    }
    if( !empty($state) ) {

        if( !empty($state_query_type) ) {

            $state_array = array(
                'taxonomy'      => 'property_state',
                'field'         => 'slug',
                'terms'         => $state
            );
            
            $tax_query[] = array(
                'relation' => $state_query_type,
                $state_array
            );
        
        } else {

            $tax_query[] = array(
                'taxonomy'      => 'property_state',
                'field'         => 'slug',
                'terms'         => $state
            );
        }
    }

    if( !empty( $country ) ) {

        if( !empty($country_query_type) ) {

            $country_array = array(
                'key' => 'fave_property_country',
                'value'   => $country,
                'type'    => 'CHAR',
                'compare' => '=',
            );

            $meta_query[] = array(
                'relation' => $country_query_type,
                $country_array
            );

        } else {

            $meta_query[] = array(
                'key' => 'fave_property_country',
                'value'   => $country,
                'type'    => 'CHAR',
                'compare' => '=',
            );
        }
    }

    if( !empty($status) ) {

        if( !empty($status_query_type) ) {
        
            $status_array = array(
                'taxonomy' => 'property_status',
                'field' => 'slug',
                'terms' => $status
            );

            $tax_query[] = array(
                'relation' => $status_query_type,
                $status_array
            );

        } else {

            $tax_query[] = array(
                'taxonomy' => 'property_status',
                'field' => 'slug',
                'terms' => $status
            );
        }

        
    }
    if( !empty($type) ) {

        if( !empty($type_query_type) ) {

            $type_array = array(
                'taxonomy' => 'property_type',
                'field' => 'slug',
                'terms' => $type
            );

            $tax_query[] = array(
                'relation' => $type_query_type,
                $type_array
            );
        
        } else {

            $tax_query[] = array(
                'taxonomy' => 'property_type',
                'field' => 'slug',
                'terms' => $type
            );            
        }

        
    }

    if ( !empty($label) ) {

        if( !empty($label_query_type) ) {

            $label_array = array(
                'taxonomy' => 'property_label',
                'field' => 'slug',
                'terms' => $label
            );

            $tax_query[] = array(
                'relation' => $label_query_type,
                $label_array
            );

        } else {

            $tax_query[] = array(
                'taxonomy' => 'property_label',
                'field' => 'slug',
                'terms' => $label
            );
        }
    }

    if( !empty( $features ) ) {

        if( !empty($features_query_type) ) {

            foreach ($features as $feature):
        
                $features_array = array(
                    'taxonomy' => 'property_feature',
                    'field' => 'slug',
                    'terms' => $feature
                );

                $tax_query[] = array(
                    'relation' => $features_query_type,
                    $features_array
                );

            endforeach;

        } else {

            foreach ($features as $feature):

                $tax_query[] = array(
                    'taxonomy' => 'property_feature',
                    'field' => 'slug',
                    'terms' => $feature
                );

            endforeach;

        }

        
    }

    // bedrooms logic
    if( !empty( $bedrooms ) && $bedrooms != 'any'  ) {
        $bedrooms = sanitize_text_field($bedrooms);
        $meta_query[] = array(
            'key' => 'fave_property_bedrooms',
            'value'   => $bedrooms,
            'type'    => 'CHAR',
            'compare' => $search_criteria,
        );
    }

    // bedrooms logic
    if( !empty( $rooms ) && $rooms != 'any'  ) {
        $rooms = sanitize_text_field($rooms);
        $meta_query[] = array(
            'key' => 'fave_property_rooms',
            'value'   => $rooms,
            'type'    => 'CHAR',
            'compare' => $search_criteria,
        );
    }

    // bathrooms logic
    if( !empty( $bathrooms ) && $bathrooms != 'any'  ) {
        $bathrooms = sanitize_text_field($bathrooms);
        $meta_query[] = array(
            'key' => 'fave_property_bathrooms',
            'value'   => $bathrooms,
            'type'    => 'CHAR',
            'compare' => $search_criteria,
        );
    }

    // min and max price logic
    if( !empty( $min_price ) && $min_price != 'any' && !empty( $max_price ) && $max_price != 'any' ) {
        $min_price = doubleval( houzez_clean( $min_price ) );
        $max_price = doubleval( houzez_clean( $max_price ) );

        if( $min_price > 0 && $max_price > $min_price ) {
            $meta_query[] = array(
                'key' => 'fave_property_price',
                'value' => array($min_price, $max_price),
                'type' => 'NUMERIC',
                'compare' => 'BETWEEN',
            );
        }
    } else if( !empty( $min_price ) && $min_price != 'any'  ) {
        $min_price = doubleval( houzez_clean( $min_price ) );
        if( $min_price > 0 ) {
            $meta_query[] = array(
                'key' => 'fave_property_price',
                'value' => $min_price,
                'type' => 'NUMERIC',
                'compare' => '>=',
            );
        }
    } else if( !empty( $max_price ) && $max_price != 'any'  ) {
        $max_price = doubleval( houzez_clean( $max_price ) );
        if( $max_price > 0 ) {
            $meta_query[] = array(
                'key' => 'fave_property_price',
                'value' => $max_price,
                'type' => 'NUMERIC',
                'compare' => '<=',
            );
        }
    }

    // min and max area logic
    if( !empty( $min_area ) && !empty( $max_area ) ) {
        $min_area = intval( $min_area );
        $max_area = intval( $max_area );

        if( $min_area > 0 && $max_area > $min_area ) {
            $meta_query[] = array(
                'key' => 'fave_property_size',
                'value' => array($min_area, $max_area),
                'type' => 'NUMERIC',
                'compare' => 'BETWEEN',
            );
        }

    } else if( !empty( $max_area ) ) {
        $max_area = intval( $max_area );
        if( $max_area > 0 ) {
            $meta_query[] = array(
                'key' => 'fave_property_size',
                'value' => $max_area,
                'type' => 'NUMERIC',
                'compare' => '<=',
            );
        }
    } else if( !empty( $min_area ) ) {
        $min_area = intval( $min_area );
        if( $min_area > 0 ) {
            $meta_query[] = array(
                'key' => 'fave_property_size',
                'value' => $min_area,
                'type' => 'NUMERIC',
                'compare' => '>=',
            );
        }
    }

    # meta key filters logic
    if( !empty( $meta_key_filters ) ) {

        // print "jsonObj Type: " . gettype($meta_key_filters) . "\n";
        // print "content of meta_key_filters:\n";
        // print_r($meta_key_filters);
        // print "\n";

        //strip slashes for json sent in POST variable.
        $jsonProcessed = stripslashes($meta_key_filters);
        // print_r($jsonProcessed);
        // print "\n";

        $decodedJson = json_decode($jsonProcessed, true);
        // print_r($decodedObj);
        // print "\n";

        // print "decodedJson Type: " . gettype($decodedJson) . "\n";

        // print "Decoded jsonObj: " . $decodedJson . "\n";

        $metaKeyFiltersObj = $decodedJson["meta_key_filters"];

        // print "metaKeyFiltersObj: " . $metaKeyFiltersObj . "\n";
        // print "\n";


        foreach ( $metaKeyFiltersObj as $obj ) {
            // print "apiKey: " . $obj["apiKey"] . "\n";
            // print "value: " . $obj["value"] . "\n";
            // print "pickerType: " . $obj["pickerType"] . "\n";
            // print "\n";

            if ( !empty( $obj ) ) {
                
                $apiKey = isset( $obj["apiKey"] ) ? $obj["apiKey"] : '';
                $value = isset( $obj["value"] ) ? $obj["value"] : '';
                $pickerType = isset( $obj["pickerType"] ) ? $obj["pickerType"] : '';
                $minRange = isset( $obj["min_range_value"] ) ? $obj["min_range_value"] : '';
                $maxRange = isset( $obj["max_range_value"] ) ? $obj["max_range_value"] : '';

                if ( !empty( $apiKey ) && gettype( $apiKey ) == string ) {
                    # string_picker & dropdown logic
                    if ( ( $pickerType == 'string_picker' || $pickerType == 'dropdown' ) && !empty( $value ) && gettype( $value ) == string && $value != 'any' ) {
        
                        $apiKey = sanitize_text_field($apiKey);
                        $value = sanitize_text_field($value);
        
                        $meta_query[] = array(
                            'key' => $apiKey,
                            'value'   => $value,
                            'type'    => 'CHAR',
                            'compare' => 'IN',
                        );
                    }
        
                    # range_picker logic
                    if ( $pickerType == 'range_picker' ) {
                        if ( !empty( $minRange ) && $minRange != 'any' && !empty( $maxRange ) && $maxRange != 'any' ) {
                            $minRange = doubleval( houzez_clean( $minRange ) );
                            $maxRange = doubleval( houzez_clean( $maxRange ) );
            
                            if( $minRange > 0 && $maxRange > $minRange ) {
                                $meta_query[] = array(
                                    'key' => $apiKey,
                                    'value' => array($minRange, $maxRange),
                                    'type' => 'NUMERIC',
                                    'compare' => 'BETWEEN',
                                );
                            }
                        } else if( !empty( $minRange ) && $minRange != 'any'  ) {
                            $minRange = doubleval( houzez_clean( $minRange ) );
                            if( $minRange > 0 ) {
                                $meta_query[] = array(
                                    'key' => $apiKey,
                                    'value' => $minRange,
                                    'type' => 'NUMERIC',
                                    'compare' => '>=',
                                );
                            }
                        } else if( !empty( $maxRange ) && $maxRange != 'any'  ) {
                            $max_price = doubleval( houzez_clean( $maxRange ) );
                            if( $maxRange > 0 ) {
                                $meta_query[] = array(
                                    'key' => $apiKey,
                                    'value' => $maxRange,
                                    'type' => 'NUMERIC',
                                    'compare' => '<=',
                                );
                            }
                        }
                    }
                    

                    # text_field logic
                    if ( $pickerType == 'text_field' && !empty( $value ) && gettype( $value ) == string && $value != 'any' ) {
        
                        $apiKey = sanitize_text_field($apiKey);
                        $value = sanitize_text_field($value);

                        switch ($apiKey) {
                            case "fave_property_zip": {
                                $meta_keywork = wp_kses($value, $allowed_html);
                                $zip_array = array(
                                    'key' => 'fave_property_zip',
                                    'value' => $meta_keywork,
                                    'type' => 'CHAR',
                                    'compare' => '=',
                                );

                                $keyword_array = array(
                                    'relation' => 'OR',
                                    $zip_array
                                );
                                break;
                            }
                              
                            case "fave_property_id": {
                                $meta_keywork = wp_kses($value, $allowed_html);
                                $propid_array = array(
                                    'key' => 'fave_property_id',
                                    'value' => str_replace($property_id_prefix, "", $meta_keywork),
                                    'type' => 'CHAR',
                                    'compare' => '=',
                                );

                                $keyword_array = array(
                                    'relation' => 'OR',
                                    $propid_array
                                );
                                break;
                            }

                            case "fave_property_bedrooms": {
                                $meta_query[] = array(
                                    'key' => $apiKey,
                                    'value'   => $value,
                                    'type'    => 'CHAR',
                                    'compare' => 'IN',
                                );
                                break;
                            }

                            case "fave_property_bathrooms": {
                                $meta_query[] = array(
                                    'key' => $apiKey,
                                    'value'   => $value,
                                    'type'    => 'CHAR',
                                    'compare' => 'IN',
                                );
                                break;
                            }

                            case "fave_property_garage": {
                                $meta_query[] = array(
                                    'key' => $apiKey,
                                    'value'   => $value,
                                    'type'    => 'CHAR',
                                    'compare' => 'LIKE',
                                );
                                break;
                            }

                            case "fave_property_year": {
                                $meta_query[] = array(
                                    'key' => $apiKey,
                                    'value'   => $value,
                                    'type'    => 'CHAR',
                                    'compare' => 'LIKE',
                                );
                                break;
                            }

                            case "fave_property_price": {
                                $fave_price = intval( $value );
                                if( $fave_price > 0 ) {
                                    $meta_query[] = array(
                                        'key' => 'fave_property_price',
                                        'value' => $fave_price,
                                        'type' => 'NUMERIC',
                                        'compare' => '<=',
                                    );
                                }
                                break;
                            }

                            case "fave_property_size": {
                                $fave_size = intval( $value );
                                if( $fave_size > 0 ) {
                                    $meta_query[] = array(
                                        'key' => 'fave_property_size',
                                        'value' => $fave_size,
                                        'type' => 'NUMERIC',
                                        'compare' => '<=',
                                    );
                                }
                                break;
                            }
                            
                            default: {
                                $meta_query[] = array(
                                    'key' => $apiKey,
                                    'value'   => $value,
                                    'type'    => 'CHAR',
                                    'compare' => 'LIKE',
                                );
                            }
                          }
                    }
                }
            }
        }
    }

     // agent id logic
     if( !empty( $agent_id )) {
        
        $meta_query[] = array(
            'key' => 'fave_agents',
            'value' => $agent_id,
            'compare' => '='
        );
        $meta_query[] = array(
            'key' => 'fave_agent_display_option',
            'value' => 'agent_info',
            'compare' => '='
        );
    
    }

    $meta_count = count($meta_query);

    if( $meta_count > 0 || !empty($keyword_array)) {
        $query_args['meta_query'] = array(
            'relation' => 'AND',
            $keyword_array,
            array(
                'relation' => 'AND',
                $meta_query
            ),
        );
    }

    // agency id logic
    if( !empty( $agency_id )) {
        $agents_array = array();
        $agency_agents_ids = Houzez_Query::loop_agency_agents_ids($agency_id);
        
        
        if( !empty($agency_agents_ids) ) {
            $agents_array = array(
                'key' => 'fave_agents',
                'value' => $agency_agents_ids,
                'compare' => 'IN',
            );
        }
        //append array to meta query
        $query_args['meta_query'][] = array(
            'relation' => 'OR',
            $agents_array,
            array(
                'relation' => 'AND',
                array(
                    'key'     => 'fave_property_agency',
                    'value'   => $agency_id,
                    'compare' => '='
                ),
                array(
                    'key'     => 'fave_agent_display_option',
                    'value'   => 'agency_info',
                    'compare' => '='
                )
            ),
        );
    }
    

    $tax_count = count($tax_query);


    $tax_query['relation'] = 'AND';


    if( $tax_count > 0 ) {
        $query_args['tax_query']  = $tax_query;
    }
    
    $query_args['paged'] = $page;

    if( $per_page > 0 ) {
        $query_args['posts_per_page']  = $per_page;
    }
    return $query_args;
}

function setupSearchQueryForTesting() {
    $meta_query = array();
    $tax_query = array();
    $date_query = array();
    $allowed_html = array();
    $keyword_array =  '';
    // $meta_key_filters = array();

    //$dummy_array = array();

    $custom_fields_values = isset($_POST['custom_fields_values']) ? $_POST['custom_fields_values'] : '';
    
    // if(!empty($custom_fields_values)) {
    //     foreach ($custom_fields_values as $key => $value) {
    //         $dummy_array[$key] = $value;
    //     }
    // }

    $map_only = isset( $_POST[ 'map_only' ] ) && 'on' == $_POST[ 'map_only' ];
    $initial_city = isset($_POST['initial_city']) ? $_POST['initial_city'] : '';
    $features = isset($_POST['features']) ? $_POST['features'] : '';
    $keyword = isset($_POST['keyword']) ? $_POST['keyword'] : '';
    $country = isset($_POST['country']) ? ($_POST['country']) : '';
    $state = isset($_POST['state']) ? ($_POST['state']) : '';
    $featured = isset($_POST['featured']) ? ($_POST['featured']) : '';
    $virtual_tour = isset( $_POST['virtual_tour'] ) ? boolval( $_POST['virtual_tour'] ) : false;
    $location = isset($_POST['location']) ? ($_POST['location']) : '';
    $area = isset($_POST['area']) ? ($_POST['area']) : '';
    $status = isset($_POST['status']) ? ($_POST['status']) : '';
    $type = isset($_POST['type']) ? ($_POST['type']) : '';
    $label = isset($_POST['label']) ? ($_POST['label']) : '';
    $property_id = isset($_POST['property_id']) ? ($_POST['property_id']) : '';
    $bedrooms = isset($_POST['bedrooms']) ? ($_POST['bedrooms']) : '';
    $rooms = isset($_POST['rooms']) ? ($_POST['rooms']) : '';
    $bathrooms = isset($_POST['bathrooms']) ? ($_POST['bathrooms']) : '';
    $beds_baths_criteria = isset($_POST['beds_baths_criteria']) ? ($_POST['beds_baths_criteria']) : '';
    $min_price = isset($_POST['min_price']) ? ($_POST['min_price']) : (isset($_POST['min-price']) ? ($_POST['min-price']) : '');
    $max_price = isset($_POST['max_price']) ? ($_POST['max_price']) : (isset($_POST['max-price']) ? ($_POST['max-price']) : '');
    $currency = isset($_POST['currency']) ? ($_POST['currency']) : '';
	$min_area = isset($_POST['min_area']) ? ($_POST['min_area']) : (isset($_POST['min-area']) ? ($_POST['min-area']) : '');
    $max_area = isset($_POST['max_area']) ? ($_POST['max_area']) : (isset($_POST['max-area']) ? ($_POST['max-area']) : '');
    $publish_date = isset($_POST['publish_date']) ? ($_POST['publish_date']) : '';

    $search_location = isset( $_POST[ 'search_location' ] ) ? esc_attr( $_POST[ 'search_location' ] ) : false;
    $use_radius = isset( $_POST[ 'use_radius' ] ) && 'on' == $_POST[ 'use_radius' ];
    $search_lat = isset($_POST['search_lat']) ? (float) $_POST['search_lat'] : false;
    $search_long = isset($_POST['search_long']) ? (float) $_POST['search_long'] : false;
    $search_radius = isset($_POST['search_radius']) ? (int) $_POST['search_radius'] : false;

    $agency_id = isset($_POST['fave_property_agency']) ? ($_POST['fave_property_agency']) : '';
    $agent_id = isset($_POST['fave_agents']) ? ($_POST['fave_agents']) : '';

    $meta_key_filters = isset( $_POST['meta_key_filters'] ) ? ( $_POST['meta_key_filters'] ) : '';
    
    $page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
    $per_page = isset($_POST['per_page']) ? (int) $_POST['per_page'] : 20;

    $prop_locations = array();
    houzez_get_terms_array( 'property_city', $prop_locations );

    $keyword_field = houzez_option('keyword_field');
    $beds_baths_search = houzez_option('beds_baths_search');
    $property_id_prefix = houzez_option('property_id_prefix');

    $property_id = str_replace($property_id_prefix, "", $property_id);

    $search_criteria = 'IN';
    if (!empty($beds_baths_criteria)) {
        $search_criteria = $beds_baths_criteria;
    } else {
        $search_criteria = '=';
        if( $beds_baths_search == 'greater') {
            $search_criteria = '>=';
        } else if( $beds_baths_search == 'like' ) {
            $search_criteria = 'LIKE';
        } else if( $beds_baths_search == 'equal' ) {
            $search_criteria = '=';
        }
    }

    $query_args = array(
        'post_type' => 'property',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    );

    $query_args = apply_filters('houzez_radius_filter', $query_args, $search_lat, $search_long, $search_radius, $use_radius, $search_location );

    $keyword = stripcslashes($keyword);

    if ( $keyword != '') {

        if( $keyword_field == 'prop_address' ) {
            $meta_keywork = wp_kses($keyword, $allowed_html);
            $address_array = array(
                'key' => 'fave_property_map_address',
                'value' => $meta_keywork,
                'type' => 'CHAR',
                'compare' => 'LIKE',
            );

            $street_array = array(
                'key' => 'fave_property_address',
                'value' => $meta_keywork,
                'type' => 'CHAR',
                'compare' => 'LIKE',
            );

            $zip_array = array(
                'key' => 'fave_property_zip',
                'value' => $meta_keywork,
                'type' => 'CHAR',
                'compare' => '=',
            );
            $propid_array = array(
                'key' => 'fave_property_id',
                'value' => str_replace($property_id_prefix, "", $meta_keywork),
                'type' => 'CHAR',
                'compare' => '=',
            );

            $keyword_array = array(
                'relation' => 'OR',
                $address_array,
                $street_array,
                $propid_array,
                $zip_array
            );
        } else if( $keyword_field == 'prop_city_state_county' ) {
            $taxlocation[] = sanitize_title (  esc_html( wp_kses($keyword, $allowed_html) ) );

            $_tax_query = Array();
            $_tax_query['relation'] = 'OR';

            $_tax_query[] = array(
                'taxonomy'     => 'property_area',
                'field'        => 'slug',
                'terms'        => $taxlocation
            );

            $_tax_query[] = array(
                'taxonomy'     => 'property_city',
                'field'        => 'slug',
                'terms'        => $taxlocation
            );

            $_tax_query[] = array(
                'taxonomy'      => 'property_state',
                'field'         => 'slug',
                'terms'         => $taxlocation
            );
            $tax_query[] = $_tax_query;

        } else {
            $keyword = trim( $keyword );
            if ( ! empty( $keyword ) ) {
                $query_args['s'] = $keyword;
            }
        }
    }

    //Date Query
    if( !empty($publish_date) ) {
        $publish_date = explode('/', $publish_date);
        $query_args['date_query'] = array(
            array(
                'year' => $publish_date[2],
                'compare'   => '>=',
            ),
            array(
                'month' => $publish_date[1],
                'compare'   => '>=',
            ),
            array(
                'day' => $publish_date[0],
                'compare'   => '>=',
            )
        );
    }


    //Custom Fields
    if(class_exists('Houzez_Fields_Builder') && !empty($custom_fields_values)) {
        $fields_array = Houzez_Fields_Builder::get_form_fields();
        if(!empty($fields_array)):
            
            foreach ( $fields_array as $value ):
                $field_title = $value->label;
                $field_name = $value->field_id;
                $is_search = $value->is_search;
                $field_type = $value->type;

                if( $is_search == 'yes' ) {
                    if(!empty($custom_fields_values[$field_name])) {
                        $compare = 'LIKE';
                        if( $field_type == 'checkbox_list' || $field_type == 'multiselect' ) {
                            $compare = 'IN';
                        }

                        $meta_query[] = array(
                            'key' => 'fave_'.$field_name,
                            'value' => $custom_fields_values[$field_name],
                            'type' => 'CHAR',
                            'compare' => '=',
                            'compare' => $compare,
                        );
                    }
                }
            endforeach; endif;
    }

    if(!empty($currency)) {
        $meta_query[] = array(
            'key' => 'fave_currency',
            'value' => $currency,
            'type' => 'CHAR',
            'compare' => '=',
        );
    }
    if(!empty($featured)) {
        $meta_query[] = array(
            'key' => 'fave_featured',
            'value' => $featured,
            'type' => 'CHAR',
            'compare' => '=',
        );
    }

    if(!empty($virtual_tour) && $virtual_tour == true) {
        $meta_query[] = array(
            array(
                'key' => 'fave_virtual_tour',
                'compare' => 'EXISTS' // Check if the meta key exists
            ),
            array(
                'key' => 'fave_virtual_tour',
                'value' => '',
                'compare' => '!=' // Check if the meta value is not empty
            )
        );
    }

    // Meta Queries
    if(!empty($map_only) && $map_only == true) {
        $meta_query[] = array(
            'key' => 'fave_property_map_address',
            'compare' => 'EXISTS',
        );
    }

    // Property ID
    if( !empty( $property_id )  ) {
        $meta_query[] = array(
            'key' => 'fave_property_id',
            'value'   => $property_id,
            'type'    => 'char',
            'compare' => '=',
        );
    }

    if( !empty($location) && $location != 'all' ) {
        $tax_query[] = array(
            'taxonomy' => 'property_city',
            'field' => 'slug',
            'terms' => $location
        );

    } else {
        if( $location == 'all' ) {
            /*$tax_query[] = array(
                'taxonomy' => 'property_city',
                'field' => 'slug',
                'terms' => $prop_locations
            );*/
        } else {
            if (!empty($initial_city)) {
                $tax_query[] = array(
                    'taxonomy' => 'property_city',
                    'field' => 'slug',
                    'terms' => $initial_city
                );
            }
        }
    }

    if( !empty($area) ) {
        $tax_query[] = array(
            'taxonomy' => 'property_area',
            'field' => 'slug',
            'terms' => $area
        );
    }
    if( !empty($state) ) {
        $tax_query[] = array(
            'taxonomy'      => 'property_state',
            'field'         => 'slug',
            'terms'         => $state
        );
    }

    if( !empty( $country ) ) {
        $meta_query[] = array(
            'key' => 'fave_property_country',
            'value'   => $country,
            'type'    => 'CHAR',
            'compare' => '=',
        );
    }

    if( !empty($status) ) {
        $tax_query[] = array(
            'taxonomy' => 'property_status',
            'field' => 'slug',
            'terms' => $status
        );
    }
    if( !empty($type) ) {
        $tax_query[] = array(
            'taxonomy' => 'property_type',
            'field' => 'slug',
            'terms' => $type
        );
    }

    if ( !empty($label) ) {
        $tax_query[] = array(
            'taxonomy' => 'property_label',
            'field' => 'slug',
            'terms' => $label
        );
    }

    if( !empty( $features ) ) {

        foreach ($features as $feature):
            $tax_query[] = array(
                'taxonomy' => 'property_feature',
                'field' => 'slug',
                'terms' => $feature
            );
        endforeach;
    }

    // bedrooms logic
    if( !empty( $bedrooms ) && $bedrooms != 'any'  ) {
        $bedrooms = sanitize_text_field($bedrooms);
        $meta_query[] = array(
            'key' => 'fave_property_bedrooms',
            'value'   => $bedrooms,
            'type'    => 'CHAR',
            'compare' => $search_criteria,
        );
    }

    // bedrooms logic
    if( !empty( $rooms ) && $rooms != 'any'  ) {
        $rooms = sanitize_text_field($rooms);
        $meta_query[] = array(
            'key' => 'fave_property_rooms',
            'value'   => $rooms,
            'type'    => 'CHAR',
            'compare' => $search_criteria,
        );
    }

    // bathrooms logic
    if( !empty( $bathrooms ) && $bathrooms != 'any'  ) {
        $bathrooms = sanitize_text_field($bathrooms);
        $meta_query[] = array(
            'key' => 'fave_property_bathrooms',
            'value'   => $bathrooms,
            'type'    => 'CHAR',
            'compare' => $search_criteria,
        );
    }

    // min and max price logic
    if( !empty( $min_price ) && $min_price != 'any' && !empty( $max_price ) && $max_price != 'any' ) {
        $min_price = doubleval( houzez_clean( $min_price ) );
        $max_price = doubleval( houzez_clean( $max_price ) );

        if( $min_price > 0 && $max_price > $min_price ) {
            $meta_query[] = array(
                'key' => 'fave_property_price',
                'value' => array($min_price, $max_price),
                'type' => 'NUMERIC',
                'compare' => 'BETWEEN',
            );
        }
    } else if( !empty( $min_price ) && $min_price != 'any'  ) {
        $min_price = doubleval( houzez_clean( $min_price ) );
        if( $min_price > 0 ) {
            $meta_query[] = array(
                'key' => 'fave_property_price',
                'value' => $min_price,
                'type' => 'NUMERIC',
                'compare' => '>=',
            );
        }
    } else if( !empty( $max_price ) && $max_price != 'any'  ) {
        $max_price = doubleval( houzez_clean( $max_price ) );
        if( $max_price > 0 ) {
            $meta_query[] = array(
                'key' => 'fave_property_price',
                'value' => $max_price,
                'type' => 'NUMERIC',
                'compare' => '<=',
            );
        }
    }

    // min and max area logic
    if( !empty( $min_area ) && !empty( $max_area ) ) {
        $min_area = intval( $min_area );
        $max_area = intval( $max_area );

        if( $min_area > 0 && $max_area > $min_area ) {
            $meta_query[] = array(
                'key' => 'fave_property_size',
                'value' => array($min_area, $max_area),
                'type' => 'NUMERIC',
                'compare' => 'BETWEEN',
            );
        }

    } else if( !empty( $max_area ) ) {
        $max_area = intval( $max_area );
        if( $max_area > 0 ) {
            $meta_query[] = array(
                'key' => 'fave_property_size',
                'value' => $max_area,
                'type' => 'NUMERIC',
                'compare' => '<=',
            );
        }
    } else if( !empty( $min_area ) ) {
        $min_area = intval( $min_area );
        if( $min_area > 0 ) {
            $meta_query[] = array(
                'key' => 'fave_property_size',
                'value' => $min_area,
                'type' => 'NUMERIC',
                'compare' => '>=',
            );
        }
    }

    # meta key filters logic
    if( !empty( $meta_key_filters ) ) {

        // print "jsonObj Type: " . gettype($meta_key_filters) . "\n";
        // print "content of meta_key_filters:\n";
        // print_r($meta_key_filters);
        // print "\n";

        //strip slashes for json sent in POST variable.
        $jsonProcessed = stripslashes($meta_key_filters);
        // print_r($jsonProcessed);
        // print "\n";

        $decodedJson = json_decode($jsonProcessed, true);
        // print_r($decodedObj);
        // print "\n";

        // print "decodedJson Type: " . gettype($decodedJson) . "\n";

        // print "Decoded jsonObj: " . $decodedJson . "\n";

        $metaKeyFiltersObj = $decodedJson["meta_key_filters"];

        // print "metaKeyFiltersObj: " . $metaKeyFiltersObj . "\n";
        // print "\n";


        foreach ( $metaKeyFiltersObj as $obj ) {
            // print "apiKey: " . $obj["apiKey"] . "\n";
            // print "value: " . $obj["value"] . "\n";
            // print "pickerType: " . $obj["pickerType"] . "\n";
            // print "\n";

            if ( !empty( $obj ) ) {
                
                $apiKey = isset( $obj["apiKey"] ) ? $obj["apiKey"] : '';
                $value = isset( $obj["value"] ) ? $obj["value"] : '';
                $pickerType = isset( $obj["pickerType"] ) ? $obj["pickerType"] : '';
                $minRange = isset( $obj["min_range_value"] ) ? $obj["min_range_value"] : '';
                $maxRange = isset( $obj["max_range_value"] ) ? $obj["max_range_value"] : '';

                if ( !empty( $apiKey ) && gettype( $apiKey ) == string ) {
                    # string_picker & dropdown logic
                    if ( ( $pickerType == 'string_picker' || $pickerType == 'dropdown' ) && !empty( $value ) && gettype( $value ) == string && $value != 'any' ) {
        
                        $apiKey = sanitize_text_field($apiKey);
                        $value = sanitize_text_field($value);
        
                        $meta_query[] = array(
                            'key' => $apiKey,
                            'value'   => $value,
                            'type'    => 'CHAR',
                            'compare' => 'IN',
                        );
                    }
        
                    # range_picker logic
                    if ( $pickerType == 'range_picker' ) {
                        if ( !empty( $minRange ) && $minRange != 'any' && !empty( $maxRange ) && $maxRange != 'any' ) {
                            $minRange = doubleval( houzez_clean( $minRange ) );
                            $maxRange = doubleval( houzez_clean( $maxRange ) );
            
                            if( $minRange > 0 && $maxRange > $minRange ) {
                                $meta_query[] = array(
                                    'key' => $apiKey,
                                    'value' => array($minRange, $maxRange),
                                    'type' => 'NUMERIC',
                                    'compare' => 'BETWEEN',
                                );
                            }
                        } else if( !empty( $minRange ) && $minRange != 'any'  ) {
                            $minRange = doubleval( houzez_clean( $minRange ) );
                            if( $minRange > 0 ) {
                                $meta_query[] = array(
                                    'key' => $apiKey,
                                    'value' => $minRange,
                                    'type' => 'NUMERIC',
                                    'compare' => '>=',
                                );
                            }
                        } else if( !empty( $maxRange ) && $maxRange != 'any'  ) {
                            $max_price = doubleval( houzez_clean( $maxRange ) );
                            if( $maxRange > 0 ) {
                                $meta_query[] = array(
                                    'key' => $apiKey,
                                    'value' => $maxRange,
                                    'type' => 'NUMERIC',
                                    'compare' => '<=',
                                );
                            }
                        }
                    }
                    

                    # text_field logic
                    if ( $pickerType == 'text_field' && !empty( $value ) && gettype( $value ) == string && $value != 'any' ) {
        
                        $apiKey = sanitize_text_field($apiKey);
                        $value = sanitize_text_field($value);

                        switch ($apiKey) {
                            case "fave_property_zip": {
                                $meta_keywork = wp_kses($value, $allowed_html);
                                $zip_array = array(
                                    'key' => 'fave_property_zip',
                                    'value' => $meta_keywork,
                                    'type' => 'CHAR',
                                    'compare' => '=',
                                );

                                $keyword_array = array(
                                    'relation' => 'OR',
                                    $zip_array
                                );
                                break;
                            }
                              
                            case "fave_property_id": {
                                $meta_keywork = wp_kses($value, $allowed_html);
                                $propid_array = array(
                                    'key' => 'fave_property_id',
                                    'value' => str_replace($property_id_prefix, "", $meta_keywork),
                                    'type' => 'CHAR',
                                    'compare' => '=',
                                );

                                $keyword_array = array(
                                    'relation' => 'OR',
                                    $propid_array
                                );
                                break;
                            }

                            case "fave_property_bedrooms": {
                                $meta_query[] = array(
                                    'key' => $apiKey,
                                    'value'   => $value,
                                    'type'    => 'CHAR',
                                    'compare' => 'IN',
                                );
                                break;
                            }

                            case "fave_property_bathrooms": {
                                $meta_query[] = array(
                                    'key' => $apiKey,
                                    'value'   => $value,
                                    'type'    => 'CHAR',
                                    'compare' => 'IN',
                                );
                                break;
                            }

                            case "fave_property_garage": {
                                $meta_query[] = array(
                                    'key' => $apiKey,
                                    'value'   => $value,
                                    'type'    => 'CHAR',
                                    'compare' => 'LIKE',
                                );
                                break;
                            }

                            case "fave_property_year": {
                                $meta_query[] = array(
                                    'key' => $apiKey,
                                    'value'   => $value,
                                    'type'    => 'CHAR',
                                    'compare' => 'LIKE',
                                );
                                break;
                            }

                            case "fave_property_price": {
                                $fave_price = intval( $value );
                                if( $fave_price > 0 ) {
                                    $meta_query[] = array(
                                        'key' => 'fave_property_price',
                                        'value' => $fave_price,
                                        'type' => 'NUMERIC',
                                        'compare' => '<=',
                                    );
                                }
                                break;
                            }

                            case "fave_property_size": {
                                $fave_size = intval( $value );
                                if( $fave_size > 0 ) {
                                    $meta_query[] = array(
                                        'key' => 'fave_property_size',
                                        'value' => $fave_size,
                                        'type' => 'NUMERIC',
                                        'compare' => '<=',
                                    );
                                }
                                break;
                            }
                            
                            default: {
                                $meta_query[] = array(
                                    'key' => $apiKey,
                                    'value'   => $value,
                                    'type'    => 'CHAR',
                                    'compare' => 'LIKE',
                                );
                            }
                          }
                    }
                }
            }
        }
    }

     // agent id logic
     if( !empty( $agent_id )) {
        
        $meta_query[] = array(
            'key' => 'fave_agents',
            'value' => $agent_id,
            'compare' => '='
        );
        $meta_query[] = array(
            'key' => 'fave_agent_display_option',
            'value' => 'agent_info',
            'compare' => '='
        );
    
    }

    $meta_count = count($meta_query);

    if( $meta_count > 0 || !empty($keyword_array)) {
        $query_args['meta_query'] = array(
            'relation' => 'AND',
            $keyword_array,
            array(
                'relation' => 'AND',
                $meta_query
            ),
        );
    }

    // agency id logic
    if( !empty( $agency_id )) {
        $agents_array = array();
        $agency_agents_ids = Houzez_Query::loop_agency_agents_ids($agency_id);
        
        
        if( !empty($agency_agents_ids) ) {
            $agents_array = array(
                'key' => 'fave_agents',
                'value' => $agency_agents_ids,
                'compare' => 'IN',
            );
        }
        //append array to meta query
        $query_args['meta_query'][] = array(
            'relation' => 'OR',
            $agents_array,
            array(
                'relation' => 'AND',
                array(
                    'key'     => 'fave_property_agency',
                    'value'   => $agency_id,
                    'compare' => '='
                ),
                array(
                    'key'     => 'fave_agent_display_option',
                    'value'   => 'agency_info',
                    'compare' => '='
                )
            ),
        );
    }
    

    $tax_count = count($tax_query);


    $tax_query['relation'] = 'AND';


    if( $tax_count > 0 ) {
        $query_args['tax_query']  = $tax_query;
    }
    
    $query_args['paged'] = $page;

    if( $per_page > 0 ) {
        $query_args['posts_per_page']  = $per_page;
    }
    return $query_args;
}
function getSimilarProperties() {
    $property_id = $_GET['property_id'];
    if( !isset( $_GET['property_id']) || empty($property_id)) {
        $ajax_response = array( 'success' => false, 'reason' => 'Please provide property_id' );
        wp_send_json($ajax_response, 400);
        return;
    }
    
    $show_similer = houzez_option( 'houzez_similer_properties' );
    $similer_criteria = houzez_option( 'houzez_similer_properties_type', array( 'property_type', 'property_city' ) );
    $similer_count = houzez_option( 'houzez_similer_properties_count' );



    $properties_args = array(
        'post_type'           => 'property',
        'posts_per_page'      => intval( $similer_count ),
        'post__not_in'        => array( $property_id ),
        'post_parent__not_in' => array( $property_id ),
        'post_status' => 'publish'
    );

    if ( ! empty( $similer_criteria ) && is_array( $similer_criteria ) ) {

        $similar_taxonomies_count = count( $similer_criteria );
        $tax_query                = array();

        for ( $i = 0; $i < $similar_taxonomies_count; $i ++ ) {
            
            $similar_terms = get_the_terms( get_the_ID(), $similer_criteria[ $i ] );
            if ( ! empty( $similar_terms ) && is_array( $similar_terms ) ) {
                $terms_array = array();
                foreach ( $similar_terms as $property_term ) {
                    $terms_array[] = $property_term->term_id;
                }
                $tax_query[] = array(
                    'taxonomy' => $similer_criteria[ $i ],
                    'field'    => 'id',
                    'terms'    => $terms_array,
                );
            }
        }

        $tax_count = count( $tax_query );  
        if ( $tax_count > 1 ) {
            $tax_query['relation'] = 'AND'; 
        }
        if ( $tax_count > 0 ) {
            $properties_args['tax_query'] = $tax_query; 
        }

    }

    $sort_by = houzez_option( 'similar_order', 'd_date' );
    if ( $sort_by == 'a_price' ) {
        $properties_args['orderby'] = 'meta_value_num';
        $properties_args['meta_key'] = 'fave_property_price';
        $properties_args['order'] = 'ASC';
    } else if ( $sort_by == 'd_price' ) {
        $properties_args['orderby'] = 'meta_value_num';
        $properties_args['meta_key'] = 'fave_property_price';
        $properties_args['order'] = 'DESC';
    } else if ( $sort_by == 'a_date' ) {
        $properties_args['orderby'] = 'date';
        $properties_args['order'] = 'ASC';
    } else if ( $sort_by == 'd_date' ) {
        $properties_args['orderby'] = 'date';
        $properties_args['order'] = 'DESC';
    } else if ( $sort_by == 'featured_first' ) {
        $properties_args['orderby'] = 'meta_value date';
        $properties_args['meta_key'] = 'fave_featured';
    } else if ( $sort_by == 'random' ) {
        $properties_args['orderby'] = 'rand date';
    }

    $wp_query = new WP_Query($properties_args);
    $properties = array();
    
    if ($wp_query->have_posts()) :
        while ($wp_query->have_posts()) : $wp_query->the_post();
        $property = $wp_query->post;
        array_push($properties, propertyNode($property) );

        endwhile;
    endif;
    wp_reset_query();
    wp_reset_postdata();

    
    wp_send_json( array( 'success' => true, 'count' => count($properties) , 'result' => $properties), 200);
        
    
    
}

function propertyNode($property){
    
    $post_id = $property->ID;

    $property->thumbnail    = get_the_post_thumbnail_url( $post_id, 'houzez-property-thumb-image' );
    
    $property->property_meta    = get_post_meta($post_id);
    
    $property_type_array = getCurrentLanguageTermsOnly($post_id, 'property_type');
    
    $property->property_type  = implode(",",$property_type_array);    
    
    $property->property_features = getCurrentLanguageTermsOnly($post_id, 'property_feature');
    //$property->property_features = wp_get_post_terms(  $post_id,   ['property_feature'], array( 'fields' => 'names') );
    
    appendPostAttr($property);

    $priceHTML = houzez_listing_price_v1($post_id);
    $property->htmlPrice = $priceHTML;
    $property->price = strip_tags($priceHTML);
    $property->priceSimple = houzez_listing_price_map_pins();


    $should_add_agent_agency_info = isset($_REQUEST['agent_agency_info']) ? $_REQUEST['agent_agency_info'] : '';
    
    if (!empty($should_add_agent_agency_info) && $should_add_agent_agency_info == 'yes') {
        $property->property_meta['agent_agency_info'] = property_agency_agent_info();
    }
    return $property;
}

function getFavoriteProperties() {
    
    if (! is_user_logged_in() ) {
        $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
        wp_send_json($ajax_response, 403);
        return; 
    }
    $per_page = -1;
    $pagination = isset($_GET['cpage']);
    
    $page = isset($_GET['cpage']) ? (int) $_GET['cpage'] : 1; 
    $per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 10;

    $postOffset = ($page-1) * $per_page; //subtracting 1 from page, because api is sending 1 as first page.
    


    
    $userID  = get_current_user_id();
    $fav_ids = 'houzez_favorites-'.$userID;
    $fav_ids = get_option( $fav_ids );
    if( empty( $fav_ids ) ) { 
        $ajax_response = array( 'success' => false, 'reason' => esc_html__("You don't have any favorite listings yet!", 'houzez') );
         wp_send_json($ajax_response, 404);
    } else {
        $args = array('post_type' => 'property',
                'post__in' => $fav_ids,
                'numberposts' => -1
                );
        if ($pagination) {
            
            $args = array('post_type' => 'property',
                    'post__in' => $fav_ids,
                    'offset' => $postOffset,
                    'posts_per_page' => $per_page
                    );
        }  
        $myposts = get_posts($args);
        
        $properties = array();

        foreach ($myposts as $post) : setup_postdata($post);
            array_push($properties, propertyNode($post) );
        endforeach;
        wp_reset_postdata();

        $ajax_response = array( 'success' => true, 'result' => $myposts );
         wp_send_json($ajax_response, 200);
    }
}

function isFavoriteProperty($prop_id) {
    if (!is_user_logged_in() ) {
        return false; 
    }
    $userID  = get_current_user_id();
    $fav_ids = 'houzez_favorites-'.$userID;
    $fav_ids = get_option( $fav_ids );
    if( empty( $fav_ids ) ) {
        return false;
    }
    return in_array($prop_id, $fav_ids);
}

function saveSearch() {
    if (! is_user_logged_in() ) {
        $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
        wp_send_json($ajax_response, 403);
        return; 
    }

    $query_args_orig = setupSearchQuery();
    $search_args = base64_encode( serialize( $query_args_orig ));
    
    
    // $nonce = wp_create_nonce('houzez-save-search-nounce');
    // $_REQUEST['houzez_save_search_ajax'] = $nonce;

    if (!create_nonce_or_throw_error('houzez_save_search_ajax', 'houzez-save-search-nounce')) {
        return;
    }

    $_REQUEST['search_args'] = $search_args;
    $_REQUEST['search_URI'] = http_build_query($_POST);

    do_action('wp_ajax_houzez_save_search');
    // wp_send_json(['success' => true, 
                    // 'query' => $query_args_orig
                    // ],200);
}

function listSavedSearches() {
    do_action( 'litespeed_control_set_nocache', 'nocache due to logged in' );
    
    if (! is_user_logged_in() ) {
        $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
        wp_send_json($ajax_response, 403);
        return; 
    }
    global $wpdb, $houzez_local;

    $userID = get_current_user_id();

    $table_name = $wpdb->prefix . 'houzez_search';
    $results    = $wpdb->get_results( 'SELECT * FROM ' . $table_name . ' WHERE auther_id = '.$userID.' ORDER BY id DESC', OBJECT );

    $saved_searches = array();

    foreach ( $results as $houzez_search_data ) :

        $search_args = $houzez_search_data->query;
        $search_args_decoded = unserialize( base64_decode( $search_args ) );
        $search_as_text = decodeParamsUtil($search_args_decoded);
        $houzez_search_data->query = $search_as_text;
        array_push($saved_searches, $houzez_search_data );
    endforeach;

    wp_send_json(['sucess' => true,
    'results' => $saved_searches],200);
}

function viewSavedSearch() {
    if (! is_user_logged_in() ) {
        $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
        wp_send_json($ajax_response, 403);
        return; 
    }
    if(! isset( $_POST['id'] ) ) {
        $ajax_response = array( 'success' => false, 'reason' => 'Please provide saved search id' );
        wp_send_json($ajax_response, 400);
        return;
    }
    global $wpdb, $houzez_local;

    $id = $_POST['id'];

    $table_name = $wpdb->prefix . 'houzez_search';
    $result    = $wpdb->get_row( 'SELECT * FROM ' . $table_name . ' WHERE id = '.$id.' ORDER BY id DESC', OBJECT );
    
    $search_args = $result->query;
    $query_args = unserialize( base64_decode( $search_args ) );

    // wp_send_json(['sucess' => true,
    // 'results' => $query_args], 200);

    queryPropertiesAndSendJSON($query_args);
}

function deleteSearch() {
    if (! is_user_logged_in() ) {
        $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
        wp_send_json($ajax_response, 403);
        return; 
    }
    if(! isset( $_POST['id'] ) ) {
        $ajax_response = array( 'success' => false, 'reason' => 'Please provide saved search id' );
        wp_send_json($ajax_response, 400);
        return;
    }
    $id = $_POST['id'];
    //houzez need id in property_id
    $_POST['property_id']  = $id;
    do_action('wp_ajax_houzez_delete_search');
}

function decodeParamsUtil($search_args_decoded) {
    $meta_query     = array();

    if ( isset( $search_args_decoded['meta_query'] ) ) :

        foreach ( $search_args_decoded['meta_query'] as $key => $value ) :

            if ( is_array( $value ) ) :

                if ( isset( $value['key'] ) ) :

                    $meta_query[] = $value;

                else :

                    foreach ( $value as $key => $value ) :

                        if ( is_array( $value ) ) :

                            foreach ( $value as $key => $value ) :

                                if ( isset( $value['key'] ) ) :

                                    $meta_query[]     = $value;

                                endif;

                            endforeach;

                        endif;

                    endforeach;

                endif;

            endif;

        endforeach;

    endif;

    $search_as_text = '';

    if( isset( $search_args_decoded['s'] ) && !empty( $search_args_decoded['s'] ) ) {
        $search_as_text =  esc_html__('Keyword', 'houzez') . ': ' . esc_attr( $search_args_decoded['s'] ). ' / ';
    }

    if( isset( $search_args_decoded['tax_query'] ) ) {
        foreach ($search_args_decoded['tax_query'] as $key => $val):

            if (isset($val['taxonomy']) && isset($val['terms']) && $val['taxonomy'] == 'property_status') {
                $status = hz_saved_search_term($val['terms'], 'property_status');
                if (!empty($status)) {
                    $search_as_text =  $search_as_text . esc_html__('Status', 'houzez') . ': ' . esc_attr( $status ). ' / ';
                }
            }
            if (isset($val['taxonomy']) && isset($val['terms']) && $val['taxonomy'] == 'property_type') {
                $types = hz_saved_search_term($val['terms'], 'property_type');
                if (!empty($types)) {
                    $search_as_text =  $search_as_text  . esc_html__('Type', 'houzez') . ': ' . esc_attr( $types ). ' / ';
                }
            }
            if (isset($val['taxonomy']) && isset($val['terms']) && $val['taxonomy'] == 'property_city') {
                $cities = hz_saved_search_term($val['terms'], 'property_city');
                if (!empty($cities)) {
                    $search_as_text =  $search_as_text  . esc_html__('City', 'houzez') . ': ' . esc_attr( $cities ). ' / ';
                }
            }

            if (isset($val['taxonomy']) && isset($val['terms']) && $val['taxonomy'] == 'property_state') {
                $state = hz_saved_search_term($val['terms'], 'property_state');
                if (!empty($state)) {
                    $search_as_text =  $search_as_text  . esc_html__('State', 'houzez') . ': ' . esc_attr( $state ). ' / ';
                }
            }

            if (isset($val['taxonomy']) && isset($val['terms']) && $val['taxonomy'] == 'property_area') {
                $area = hz_saved_search_term($val['terms'], 'property_area');
                if (!empty($area)) {
                    $search_as_text =  $search_as_text  . esc_html__('Area', 'houzez') . ': ' . esc_attr( $area ). ' / ';
                }
            }

            if (isset($val['taxonomy']) && isset($val['terms']) && $val['taxonomy'] == 'property_label') {
                $label = hz_saved_search_term($val['terms'], 'property_label');
                if (!empty($label)) {
                    $search_as_text =  $search_as_text  . esc_html__('Label', 'houzez') . ': ' . esc_attr( $label ). ' / ';
                }
            }

        endforeach;
    }

    if( isset( $meta_query ) && sizeof( $meta_query ) !== 0 ) {
        foreach ( $meta_query as $key => $val ) :

            if (isset($val['key']) && $val['key'] == 'fave_property_bedrooms') {
                
                $search_as_text =  $search_as_text  . esc_html__('Bedrooms', 'houzez') . ': ' . esc_attr( $val['value'] ). ' / ';
            }

            if (isset($val['key']) && $val['key'] == 'fave_property_bathrooms') {
                
                $search_as_text =  $search_as_text  . esc_html__('Bathrooms', 'houzez') . ': ' . esc_attr( $val['value'] ). ' / ';
            }

            if (isset($val['key']) && $val['key'] == 'fave_property_price') {
                if ( isset( $val['value'] ) && is_array( $val['value'] ) ) :
                    $user_args['min-price'] = $val['value'][0];
                    $user_args['max-price'] = $val['value'][1];
                    $search_as_text =  $search_as_text  . esc_html__('Price', 'houzez') . ': ' . esc_attr( $val['value'][0] ).' - '.esc_attr( $val['value'][1]). ' / ';
                else :
                    $user_args['max-price'] = $val['value'];
                    $search_as_text =  $search_as_text  . esc_html__('Price', 'houzez') . ': ' . esc_attr( $val['value'] ).' / ';
                endif;
            }

            if (isset($val['key']) && $val['key'] == 'fave_property_size') {
                if ( isset( $val['value'] ) && is_array( $val['value'] ) ) :
                    $user_args['min-area'] = $val['value'][0];
                    $user_args['max-area'] = $val['value'][1];
                    $search_as_text =  $search_as_text  . esc_html__('Size', 'houzez') . ': ' . esc_attr( $val['value'][0] ).' - '.esc_attr( $val['value'][1]). ' / ';
                else :
                    $user_args['max-area'] = $val['value'];
                    $search_as_text =  $search_as_text  . esc_html__('Size', 'houzez') . ': ' . esc_attr( $val['value'] ).' / ';
                endif;
            }


        endforeach;
    }
    return $search_as_text;
}