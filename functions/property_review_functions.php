<?php
/**
 * Extends api for review. Exposes api to add review via rest api.
 *
 *
 * @package Houzi Mobile Api
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
      'permission_callback' => '__return_true'
    ));
    register_rest_route( 'houzez-mobile-api/v1', '/report-content', array(
        'methods' => 'POST',
        'callback' => 'reportContent',
        'permission_callback' => '__return_true'
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
    // $nonce = wp_create_nonce('review-nonce');
    // $_POST['review-security'] = $nonce;
    

    if (!create_nonce_or_throw_error('review-security', 'review-nonce')) {
        return;
    }
    
    houzez_submit_review();
}

function reportContent(){
    
    if (! is_user_logged_in() ) {
        $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
        wp_send_json($ajax_response, 403);
        return; 
    }

    if (!create_nonce_or_throw_error('report-security', 'report-nonce')) {
        return;
    }

    $nonce = $_POST['report-security'];
    if ( ! wp_verify_nonce( $nonce, 'report-nonce' ) ) {
        $ajax_response = array( 'success' => false , 'reason' => esc_html__( 'Security check failed!', 'houzi' ) );
        wp_send_json($ajax_response, 403);
        return;
    }
    global $current_user; wp_get_current_user();
    $userID       = get_current_user_id();
    // $contactName = $current_user->display_name;
    $contactName = empty($_POST['name']) ? $current_user->display_name : $_POST['name'];

    $content_type = $_POST['content_type'];
    $content_id = $_POST['content_id'];

    
    $contentLink = get_post_permalink($content_id);
    $contentTitle = get_the_title($content_id);
    
    $content_post = get_post($content_id);
    $contentDescription = $content_post->post_content;

    $author_id = get_post_field ('post_author', $content_id);
    $content_author = get_the_author_meta( 'nickname' , $author_id ); 

    $message = empty($_POST['message']) ? "" : $_POST['message'];
    $reason  = empty($_POST['reason']) ? "" : $_POST['reason'];
    $email  = empty($_POST['email']) ? "" : $_POST['email'];

    $subject = "$contactName reported about a $content_type";
    $body = "<p><b>Reporter ID:</b> $userID</p>";
    $body .= "<p><b>Reporter Name:</b> $contactName</p>";
    if (!empty($email)) {
        $body .= "<p><b>Reporter Email:</b> $email</p>";
    }
    $body .= "<p><b>$content_type ID:</b> $content_id</p>";
    $body .= "<p><b>$content_type title:</b> $contentTitle</p>";
    $body .= "<p><b>$content_type content:</b> $contentDescription</p>";
    $body .= "<p><b>$content_type author:</b> $content_author</p>";
    $body .= "<p><b>Permalink:</b> $contentLink</p>";
    if (!empty($reason)) {
        $body .= "<p><b>Reported reason:</b> $reason</p>";
    }
    if (!empty($message)) {
        $body .= "<p><b>Message:</b> $message</p>";
    }
    

    $to = get_option( 'admin_email' );
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
    );

    if ( wp_mail( $to, $subject, $body, $headers ) ) {
        // $response['status'] = 200;
        // $response['message'] = 'Message sent successfully.';
        //$response['test'] = $body;
    }

    $notifArgs = array(
        "title" => $subject,
        "message" => $body,
        "type" => 'report',
        "to" => $to,
    );

    do_action('houzez_send_notification', $notifArgs);
    
    $ajax_response = array( 'success' => true , 'message' => esc_html__( 'Thank you for reporting, our support will review your report.', 'houzi' ) );
    wp_send_json($ajax_response, 200);
    
}