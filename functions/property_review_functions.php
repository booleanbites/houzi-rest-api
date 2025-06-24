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
    'permission_callback' => '__return_true',
    'args' => array(
        'entity_type' => array(
            'required' => false,
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'validate_callback' => function ($param) {
                return empty($param) || in_array($param, ['agent', 'agency', 'property', 'author']);
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
        ),
        'search' => array(
            'required' => false,
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'description' => 'Search reviews by reviewer name'
        )
    )
));

 add_action('rest_api_init', function () {
    register_rest_route('houzez-mobile-api/v1', '/review-approval', [
        'methods' => 'POST',
        'callback' => 'houzez_handle_review_approval_api',
        'permission_callback' => function() {
            return current_user_can('edit_others_posts'); // Only allow users with edit permissions
        },
        'args' => [
            'review_id' => [
                'required' => true,
                'validate_callback' => 'is_numeric',
                'sanitize_callback' => 'absint',
            ],
            'action' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return in_array($param, ['approve', 'reject', 'delete']);
                },
            ],
        ],
    ]);
});

    
    // Admin Actions Log API
    register_rest_route('houzez-mobile-api/v1', '/admin-actions', [
        'methods' => 'GET',
        'callback' => 'houzez_get_admin_actions_log',
        'permission_callback' => function() {
            return current_user_can('edit_others_posts');
        },
        'args' => [
            'page' => [
                'default' => 1,
                'validate_callback' => 'is_numeric',
                'sanitize_callback' => 'absint',
            ],
            'per_page' => [
                'default' => 20,
                'validate_callback' => 'is_numeric',
                'sanitize_callback' => 'absint',
            ],
        ],
    ]);
    
    // Review Settings API
    register_rest_route('houzez-mobile-api/v1', '/review-settings', [
        'methods' => 'GET',
        'callback' => 'houzez_get_review_settings',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
    ]);






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

////
function getReviewsAPI($request) {
    try {
        // Get parameters
        $entity_type = $request->get_param('entity_type');
        $entity_id = $request->get_param('entity_id');
        $per_page = $request->get_param('per_page');
        $page = $request->get_param('page');
        $orderby = $request->get_param('orderby');
        $order = $request->get_param('order');
        $search = $request->get_param('search');

        // Determine allowed statuses
        $post_status = ['publish'];
        if (current_user_can('edit_posts')) {
            $post_status = array_merge($post_status, ['pending', 'review_rejected']);
        }

        // Build query args
        $query_args = array(
            'post_type' => 'houzez_reviews',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'post_status' => $post_status,
            'orderby' => $orderby === 'rating' ? 'meta_value_num' : 'date',
            'order' => $order
        );

        // Entity ID filter
        if ($entity_id > 0 && !empty($entity_type)) {
            $meta_key = 'review_' . $entity_type . '_id';
            $query_args['meta_query'] = array(
                array(
                    'key' => $meta_key,
                    'value' => $entity_id,
                    'compare' => '='
                )
            );
        }

        // Entity type filter
        if (!empty($entity_type) && $entity_id == 0) {
            $query_args['meta_query'] = array(
                array(
                    'key' => 'review_post_type',
                    'value' => $entity_type,
                    'compare' => '='
                )
            );
        }

        // Order by rating
        if ($orderby === 'rating') {
            $query_args['meta_key'] = 'review_stars';
        }

        // Search by reviewer
        if (!empty($search)) {
            $user_search_args = array(
                'search' => '*' . $search . '*',
                'search_columns' => array('user_login', 'display_name'),
                'fields' => 'ID'
            );
            $matching_users = get_users($user_search_args);
            $query_args['author__in'] = !empty($matching_users) ? $matching_users : [0];
        }

        // Execute query
        $reviews_query = new WP_Query($query_args);
        $reviews = array();
        
        // Status mapping
        $status_mapping = [
            'publish' => 'Approved',
            'pending' => 'Pending',
            'review_rejected' => 'Rejected'
        ];

        if ($reviews_query->have_posts()) {
            while ($reviews_query->have_posts()) {
                $reviews_query->the_post();
                $review_id = get_the_ID();
                $post = get_post($review_id);

                if (!$post) continue;

                $author_id = $post->post_author;
                $user = $author_id ? get_userdata($author_id) : null;

                // Get meta values
                $meta = array();
                $meta_keys = [
                    'review_post_type', 'review_stars', 'review_by', 'review_to',
                    'review_agent_id', 'review_agency_id', 'review_property_id', 'review_author_id'
                ];
                
                foreach ($meta_keys as $key) {
                    $meta_value = get_post_meta($review_id, $key, false);
                    if (!empty($meta_value)) {
                        $meta[$key] = $meta_value;
                    }
                }

                // Get thumbnail
                $thumbnail_url = '';
                if ($thumbnail_id = get_post_thumbnail_id($review_id)) {
                    $thumbnail_url = wp_get_attachment_image_url($thumbnail_id, 'full');
                }

                // Build review object
                $review_status = $post->post_status;
                $review = array(
                    'id' => $review_id,
                    'date' => mysql2date('Y-m-d\TH:i:s', $post->post_date, false),
                    'date_gmt' => mysql2date('Y-m-d\TH:i:s', $post->post_date_gmt, false),
                    'guid' => array('rendered' => get_permalink($review_id)),
                    'modified' => mysql2date('Y-m-d\TH:i:s', $post->post_modified, false),
                    'modified_gmt' => mysql2date('Y-m-d\TH:i:s', $post->post_modified_gmt, false),
                    'slug' => $post->post_name,
                    'status' => $review_status,
                    'status_label' => $status_mapping[$review_status] ?? $review_status,
                    'type' => $post->post_type,
                    'link' => get_permalink($review_id),
                    'title' => array('rendered' => get_the_title($review_id)),
                    'content' => array(
                        'rendered' => apply_filters('the_content', $post->post_content),
                        'protected' => false
                    ),
                    'author' => (int) $author_id,
                    'parent' => (int) $post->post_parent,
                    'thumbnail' => $thumbnail_url,
                    'meta' => $meta,
                    'username' => $user ? $user->user_login : '',
                    'user_display_name' => $user ? $user->display_name : ''
                );

                $reviews[] = $review;
            }
            wp_reset_postdata();
        }

        $response = new WP_REST_Response($reviews, 200);
        $response->header('X-WP-Total', $reviews_query->found_posts);
        $response->header('X-WP-TotalPages', $reviews_query->max_num_pages);
        return $response;

    } catch (Exception $e) {
        error_log('Reviews API Error: ' . $e->getMessage());
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Internal server error: ' . $e->getMessage()
        ), 500);
    }
}


function houzez_handle_review_approval_api(WP_REST_Request $request) {
    $review_id = $request->get_param('review_id');
    $action = $request->get_param('action');
    $response = [];

    try {
        switch ($action) {
            case 'approve':
                $result = wp_update_post([
                    'ID' => $review_id,
                    'post_status' => 'publish'
                ]);
                
                if (is_wp_error($result)) {
                    throw new Exception($result->get_error_message());
                }
                
                // Trigger rating calculation
                houzez_admin_review_meta_on_save($review_id);
                $response = ['success' => true, 'message' => 'Review approved successfully'];
                break;

            case 'reject':
                $result = wp_update_post([
                    'ID' => $review_id,
                    'post_status' => 'review_rejected'
                ]);
                
                if (is_wp_error($result)) {
                    throw new Exception($result->get_error_message());
                }
                
                $response = ['success' => true, 'message' => 'Review rejected successfully'];
                break;

            case 'delete':
                $review = get_post($review_id);
                
                if (!$review || $review->post_type !== 'houzez_reviews') {
                    throw new Exception('Invalid review ID');
                }
                
                // Trigger rating adjustment before deletion
                houzez_adjust_listing_rating_on_delete($review_id);
                $result = wp_delete_post($review_id, true);
                
                if (!$result) {
                    throw new Exception('Failed to delete review');
                }
                
                $response = ['success' => true, 'message' => 'Review deleted successfully'];
                break;
        }
    } catch (Exception $e) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $e->getMessage()
        ], 400);
    }

    return new WP_REST_Response($response, 200);
}


// Admin Actions Log API handler
function houzez_get_admin_actions_log(WP_REST_Request $request) {
    $page = $request->get_param('page');
    $per_page = $request->get_param('per_page');
    $log = get_option('houzez_review_admin_actions', []);
    
    // Paginate results
    $total_items = count($log);
    $total_pages = ceil($total_items / $per_page);
    $offset = ($page - 1) * $per_page;
    $items = array_slice($log, $offset, $per_page);
    
    return new WP_REST_Response([
        'success' => true,
        'data' => $items,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $per_page,
            'total_items' => $total_items,
            'total_pages' => $total_pages
        ]
    ], 200);
}

// Review Settings API handler
function houzez_get_review_settings() {
    return new WP_REST_Response([
        'success' => true,
        'settings' => [
            'reviews_enabled' => true, // Hardcoded as true since reviews are functional
            'new_ratings_approved_by_admin' => (bool) houzez_option('property_reviews_approved_by_admin'),
            'update_review_approved' => (bool) houzez_option('update_review_approved')
        ]
    ], 200);
}

