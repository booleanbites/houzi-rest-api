<?php
/**
 * Extends api for review. Exposes api to add review via rest api.
 *
 *
 * @package Houzez Mobile Api
 * @since Houzi 1.0
 * @author Adil Soomro
 */

add_filter( 'rest_houzez_reviews_query', function( $args, $request ){
    //featured property
    if ( $request->get_param( 'review_property_id' ) ) {
        $args['meta_key']   = 'review_property_id';
        $args['meta_value'] = $request->get_param( 'review_property_id' );
    }
    if ( $request->get_param( 'review_agent_id' ) ) {
        $args['meta_key']   = 'review_agent_id';
        $args['meta_value'] = $request->get_param( 'review_agent_id' );
    }
    if ( $request->get_param( 'review_agency_id' ) ) {
        $args['meta_key']   = 'review_agency_id';
        $args['meta_value'] = $request->get_param( 'review_agency_id' );
    }
    if ( $request->get_param( 'review_author_id' ) ) {
        $args['meta_key']   = 'review_author_id';
        $args['meta_value'] = $request->get_param( 'review_author_id' );
    }
    
  return $args;
}, 10, 2 );

add_action( 'rest_api_init', function () {
    
    register_rest_route( 'houzez-mobile-api/v1', '/add-review', array(
      'methods' => 'POST',
      'callback' => 'addReview',
    ));
  });

add_filter('rest_prepare_houzez_reviews', 'prepareReviewsData', 10, 3);

function prepareReviewsData($response, $post, $request) {
    $response->data['thumbnail']   = houzez_get_profile_pic();
    $response->data['meta'] = get_post_meta(get_the_ID()); 

    $user = get_user_by('id', get_the_author_meta( 'ID' ));

    $response->data['username'] = $user->user_login;
    $response->data['user_display_name'] = $user->display_name;

    // $response->data['review_likes'] = get_post_meta(get_the_ID(), 'review_likes', true); 
    // $response->data['review_dislikes'] = get_post_meta(get_the_ID(), 'review_dislikes', true);
    //$response->data['review_stars'] = houzez_get_stars(get_post_meta(get_the_ID(), 'review_stars', true), false);
    return $response;
}

function addReview(){
    
    if (! is_user_logged_in() ) {
        $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
        wp_send_json($ajax_response, 403);
        return; 
    }

    //create nonce
    $nonce = wp_create_nonce('review-nonce');
    $_POST['review-security'] = $nonce;
    
    houzez_submit_review();
}