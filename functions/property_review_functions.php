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
    register_rest_route('wp/v2', '/get-reviews', array(
        'methods' => 'GET',
        'callback' => 'getReviewsAPI',
        'permission_callback' => '__return_true', // Make it public, or add your own permission logic
        'args' => array(
            'entity_type' => array(
                'required' => false,
                'default' => 'agent',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function ($param) {
                    return in_array($param, ['agent', 'agency', 'property', 'author']);
                }
            ),
            'entity_id' => array(
                'required' => false,
                'default' => 0,
                'sanitize_callback' => 'absint'
            ),
            'per_page' => array(
                'required' => false,
                'default' => 10,
                'sanitize_callback' => 'absint'
            ),
            'page' => array(
                'required' => false,
                'default' => 1,
                'sanitize_callback' => 'absint'
            ),
            'orderby' => array(
                'required' => false,
                'default' => 'date',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function ($param) {
                    return in_array($param, ['date', 'rating']);
                }
            ),
            'order' => array(
                'required' => false,
                'default' => 'DESC',
                'sanitize_callback' => function ($param) {
                    return strtoupper($param);
                },
                'validate_callback' => function ($param) {
                    return in_array(strtoupper($param), ['ASC', 'DESC']);
                }
            )
        )
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
function getReviewsAPI($request) {
    try {
        // Get parameters from request with defaults
        $params = $request->get_params();
        
        $entity_type = isset($params['entity_type']) ? sanitize_text_field($params['entity_type']) : 'agent';
        $entity_id = isset($params['entity_id']) ? intval($params['entity_id']) : 0;
        $per_page = isset($params['per_page']) ? min(absint($params['per_page']), 100) : 10; // Limit to 100 max
        $page = isset($params['page']) ? max(absint($params['page']), 1) : 1;
        $orderby = isset($params['orderby']) && in_array($params['orderby'], ['date', 'rating']) ? $params['orderby'] : 'date';
        $order = isset($params['order']) && in_array(strtoupper($params['order']), ['ASC', 'DESC']) ? strtoupper($params['order']) : 'DESC';

        // Map entity types to meta keys
        $meta_map = array(
            'agent'    => 'review_agent_id',
            'agency'   => 'review_agency_id',
            'property' => 'review_property_id',
            'author'   => 'review_author_id'
        );

        if (!isset($meta_map[$entity_type])) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Invalid entity type specified'
            ), 400);
        }

        $meta_key = $meta_map[$entity_type];
        
        // Build query arguments
        $args = array(
            'post_type'      => 'houzez_reviews',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'post_status'    => 'publish',
            'meta_query'     => array()
        );
        
        // Add entity type filter - fix the meta key mapping
        $args['meta_query'][] = array(
            'key'   => 'review_post_type',
            'value' => $entity_type // Changed from 'houzez_' . $entity_type to just $entity_type
        );
        
        // Add specific entity ID filter if provided
        if ($entity_id > 0) {
            $args['meta_query'][] = array(
                'key'   => $meta_key,
                'value' => $entity_id
            );
            $args['meta_query']['relation'] = 'AND';
        }
        
        // Handle ordering
        if ($orderby === 'rating') {
            $args['meta_key'] = 'review_stars';
            $args['orderby'] = 'meta_value_num';
        }
        $args['order'] = $order;
        
        // Execute query with error handling
        $reviews_query = new WP_Query($args);
        
        if (is_wp_error($reviews_query)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Database query failed'
            ), 500);
        }

        $reviews = array();
        
        if ($reviews_query->have_posts()) {
            while ($reviews_query->have_posts()) {
                $reviews_query->the_post();
                $review_id = get_the_ID();
                $post = get_post($review_id);
                
                if (!$post) continue;
                
                $author_id = $post->post_author;
                $user = $author_id ? get_userdata($author_id) : null;
                
                // Get all meta values safely
                $meta = array();
                $meta_keys = [
                    'review_post_type', 
                    'review_stars', 
                    'review_by', 
                    'review_to', 
                    $meta_key
                ];
                
                foreach ($meta_keys as $key) {
                    $meta_value = get_post_meta($review_id, $key, false);
                    if (!empty($meta_value)) {
                        $meta[$key] = $meta_value;
                    }
                }
                
                // Get thumbnail/featured image
                $thumbnail_id = get_post_thumbnail_id($review_id);
                $thumbnail_url = '';
                if ($thumbnail_id) {
                    $thumbnail_url = wp_get_attachment_image_url($thumbnail_id, 'full');
                } else {
                    // Default avatar/thumbnail fallback
                    $thumbnail_url = get_template_directory_uri() . '/img/profile-avatar.png';
                }
                
                // Get user avatar if no post thumbnail
                if (empty($thumbnail_url) && $user) {
                    $avatar_url = get_avatar_url($author_id, array('size' => 96));
                    if ($avatar_url) {
                        $thumbnail_url = $avatar_url;
                    }
                }
                
                // Build the review object matching the expected format
                $reviews[] = array(
                    'id' => $review_id,
                    'date' => mysql2date('c', $post->post_date, false),
                    'date_gmt' => mysql2date('c', $post->post_date_gmt, false),
                    'guid' => array(
                        'rendered' => get_permalink($review_id)
                    ),
                    'modified' => mysql2date('c', $post->post_modified, false),
                    'modified_gmt' => mysql2date('c', $post->post_modified_gmt, false),
                    'slug' => $post->post_name,
                    'status' => $post->post_status,
                    'type' => $post->post_type,
                    'link' => get_permalink($review_id),
                    'title' => array(
                        'rendered' => get_the_title($review_id)
                    ),
                    'content' => array(
                        'rendered' => apply_filters('the_content', $post->post_content),
                        'protected' => false
                    ),
                    'author' => $author_id,
                    'parent' => $post->post_parent,
                    'template' => '',
                    'thumbnail' => $thumbnail_url,
                    'meta' => $meta,
                    'username' => $user ? $user->user_login : '',
                    'user_display_name' => $user ? $user->display_name : '',
                    '_links' => array(
                        'self' => array(
                            array(
                                'href' => rest_url('wp/v2/houzez_reviews/' . $review_id),
                                'targetHints' => array(
                                    'allow' => array('GET')
                                )
                            )
                        ),
                        'collection' => array(
                            array(
                                'href' => rest_url('wp/v2/houzez_reviews')
                            )
                        ),
                        'about' => array(
                            array(
                                'href' => rest_url('wp/v2/types/houzez_reviews')
                            )
                        ),
                        'author' => array(
                            array(
                                'embeddable' => true,
                                'href' => rest_url('wp/v2/users/' . $author_id)
                            )
                        ),
                        'version-history' => array(
                            array(
                                'count' => 0,
                                'href' => rest_url('wp/v2/houzez_reviews/' . $review_id . '/revisions')
                            )
                        ),
                        'wp:attachment' => array(
                            array(
                                'href' => rest_url('wp/v2/media?parent=' . $review_id)
                            )
                        ),
                        'curies' => array(
                            array(
                                'name' => 'wp',
                                'href' => 'https://api.w.org/{rel}',
                                'templated' => true
                            )
                        )
                    )
                );
            }
            wp_reset_postdata();
        }
        
        $response = new WP_REST_Response($reviews, 200);
        $response->header('X-WP-Total', $reviews_query->found_posts);
        $response->header('X-WP-TotalPages', $reviews_query->max_num_pages);
        return $response;

    } catch (Exception $e) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Internal server error: ' . $e->getMessage()
        ), 500);
    }
}