<?php

add_filter( 'rest_property_query', function( $args, $request ){
    //featured property
    if ( $request->get_param( 'fave_featured' ) ) {
        $args['meta_key']   = 'fave_featured';
        $args['meta_value'] = $request->get_param( 'fave_featured' );
    }
    if ( $request->get_param( 'fave_agents' ) ) {
        $args['meta_key']   = 'fave_agents';
        $args['meta_value'] = $request->get_param( 'fave_agents' );
    }
    if ( $request->get_param( 'fave_property_agency' ) ) {
        $args['meta_key']   = 'fave_property_agency';
        $args['meta_value'] = $request->get_param( 'fave_property_agency' );
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


//-----------------------------------------------------------------------



// houzez-mobile-api/v1/search-properties
add_action( 'rest_api_init', function () {
  register_rest_route( 'houzez-mobile-api/v1', '/search-properties', array(
    'methods' => 'POST',
    'callback' => 'searchProperties',
  ));

  register_rest_route( 'houzez-mobile-api/v1', '/get-property-detail', array(
    'methods' => 'GET',
    'callback' => 'getPropertDetail',
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

function searchProperties(){
    $meta_query = array();
    $tax_query = array();
    $date_query = array();
    $allowed_html = array();
    $keyword_array =  '';

    $dummy_array = array();

    $custom_fields_values = isset($_POST['custom_fields_values']) ? $_POST['custom_fields_values'] : '';
    
    if(!empty($custom_fields_values)) {
        foreach ($custom_fields_values as $value) {
            $dummy_array[] = $value;
        }
    }


    $initial_city = isset($_POST['initial_city']) ? $_POST['initial_city'] : '';
    $features = isset($_POST['features']) ? $_POST['features'] : '';
    $keyword = isset($_POST['keyword']) ? $_POST['keyword'] : '';
    $country = isset($_POST['country']) ? ($_POST['country']) : '';
    $state = isset($_POST['state']) ? ($_POST['state']) : '';
    $location = isset($_POST['location']) ? ($_POST['location']) : '';
    $area = isset($_POST['area']) ? ($_POST['area']) : '';
    $status = isset($_POST['status']) ? ($_POST['status']) : '';
    $type = isset($_POST['type']) ? ($_POST['type']) : '';
    $label = isset($_POST['label']) ? ($_POST['label']) : '';
    $property_id = isset($_POST['property_id']) ? ($_POST['property_id']) : '';
    $bedrooms = isset($_POST['bedrooms']) ? ($_POST['bedrooms']) : '';
    $bathrooms = isset($_POST['bathrooms']) ? ($_POST['bathrooms']) : '';
    $min_price = isset($_POST['min_price']) ? ($_POST['min_price']) : '';
    $max_price = isset($_POST['max_price']) ? ($_POST['max_price']) : '';
    $currency = isset($_POST['currency']) ? ($_POST['currency']) : '';
    $min_area = isset($_POST['min_area']) ? ($_POST['min_area']) : '';
    $max_area = isset($_POST['max_area']) ? ($_POST['max_area']) : '';
    $publish_date = isset($_POST['publish_date']) ? ($_POST['publish_date']) : '';

    $search_location = isset( $_POST[ 'search_location' ] ) ? esc_attr( $_POST[ 'search_location' ] ) : false;
    $use_radius = isset( $_POST[ 'use_radius' ] ) && 'on' == $_POST[ 'use_radius' ];
    $search_lat = isset($_POST['search_lat']) ? (float) $_POST['search_lat'] : false;
    $search_long = isset($_POST['search_long']) ? (float) $_POST['search_long'] : false;
    $search_radius = isset($_POST['search_radius']) ? (int) $_POST['search_radius'] : false;
    
    $page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
    $per_page = isset($_POST['per_page']) ? (int) $_POST['per_page'] : 0;

    $prop_locations = array();
    houzez_get_terms_array( 'property_city', $prop_locations );

    $keyword_field = houzez_option('keyword_field');
    $beds_baths_search = houzez_option('beds_baths_search');
    $property_id_prefix = houzez_option('property_id_prefix');

    $property_id = str_replace($property_id_prefix, "", $property_id);

    $search_criteria = '=';
    if( $beds_baths_search == 'greater') {
        $search_criteria = '>=';
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
    if(class_exists('Houzez_Fields_Builder')) {
        $fields_array = Houzez_Fields_Builder::get_form_fields();
        if(!empty($fields_array)):
            
            foreach ( $fields_array as $key => $value ):
                $field_title = $value->label;
                $field_name = $value->field_id;
                $is_search = $value->is_search;

                if( $is_search == 'yes' ) {
                    if(!empty($dummy_array[$key])) {
                        $meta_query[] = array(
                            'key' => 'fave_'.$field_name,
                            'value' => $dummy_array[$key],
                            'type' => 'CHAR',
                            'compare' => '=',
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

    // Meta Queries
    $meta_query[] = array(
        'key' => 'fave_property_map_address',
        'compare' => 'EXISTS',
    );

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

    $tax_count = count($tax_query);


    $tax_query['relation'] = 'AND';


    if( $tax_count > 0 ) {
        $query_args['tax_query']  = $tax_query;
    }
    
    $query_args['paged'] = $page;

    if( $per_page > 0 ) {
        $query_args['posts_per_page']  = $per_page;
    }

    $query_args = new WP_Query( $query_args );


    $properties = array();
    

    while( $query_args->have_posts() ):
        $query_args->the_post();
        $property = $query_args->post;
        array_push($properties, propertyNode($property) );
        
    endwhile;

    wp_reset_postdata();

    if( count($properties) > 0 ) {
        echo json_encode( array( 'success' => true, 'count' => count($properties) , 'result' => $properties));
        exit();
    } else {
        echo json_encode( array( 'success' => false ) );
        exit();
    }
    die();
}

function propertyNode($property){

    $post_id = $property->ID;

    // $property_location = get_post_meta( $post_id,'fave_property_location',true);
    // $lat_lng = explode(',', $property_location);
    
    // $prop_featured = get_post_meta( $post_id, 'fave_featured', true );
    // $prop_type = wp_get_post_terms( $post_id, 'property_type', array("fields" => "ids") );

    

    //$prop = new stdClass();

    // $prop->id           = $post_id;
    // $prop->title        = get_the_title();
    // $prop->sanitizetitle = sanitize_title(get_the_title());
    // $prop->lat          = $lat_lng[0];
    // $prop->lng          = $lat_lng[1];
    // $prop->bedrooms     = get_post_meta( $post_id, 'fave_property_bedrooms', true );
    // $prop->bathrooms    = get_post_meta( $post_id, 'fave_property_bathrooms', true );
    // $prop->address      = get_post_meta( $post_id, 'fave_property_map_address', true );
    $property->thumbnail    = get_the_post_thumbnail_url( $post_id, 'houzez-property-thumb-image' );
    // $prop->url          = get_permalink();
    $property->property_meta    = get_post_meta($post_id);
    $property->property_type         = houzez_taxonomy_simple('property_type');

    // $prop->prop_images        = get_post_meta( $post_id, 'fave_property_images', false );
    // $prop->images_count = count( $prop->prop_images );

    // $prop->prop_meta = get_post_meta($post_id);


    
    $property->property_features = wp_get_post_terms(  $post_id,   ['property_feature'], array( 'fields' => 'names') );
    

    // $prop->property_images =  ;
    //$prop->property_images = [];
    // foreach(get_attached_media( '', $post_id ) as $row):
    //     $prop->property_images[] = $row->guid;
    // endforeach;

    // foreach(get_post_meta( $post_id, 'fave_property_images', false ) as $imgID):
    //     $prop->property_images[] = wp_get_attachment_url($imgID);
    // endforeach;

    

    $priceHTML = houzez_listing_price_v1($post_id);
    $property->htmlPrice = $priceHTML;
    $property->price = strip_tags($priceHTML);
    $property->priceSimple = houzez_listing_price_map_pins();

    return $property;
}