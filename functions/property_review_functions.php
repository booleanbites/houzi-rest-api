<?php
/**
 * Extends api for review. Exposes api to add review via rest api.
 *
 *
 * @package Houzi Mobile Api
 * @since Houzi 1.0
 * @author Adil Soomro
 */

add_filter('rest_houzez_reviews_query', function ($args, $request) {
    //featured property
    if ($request->get_param('review_property_id')) {
        $args['meta_key'] = 'review_property_id';
        $args['meta_value'] = $request->get_param('review_property_id');
    }
    if ($request->get_param('review_agent_id')) {
        $args['meta_key'] = 'review_agent_id';
        $args['meta_value'] = $request->get_param('review_agent_id');
    }
    if ($request->get_param('review_agency_id')) {
        $args['meta_key'] = 'review_agency_id';
        $args['meta_value'] = $request->get_param('review_agency_id');
    }
    if ($request->get_param('review_author_id')) {
        $args['meta_key'] = 'review_author_id';
        $args['meta_value'] = $request->get_param('review_author_id');
    }

    return $args;
}, 10, 2);

add_action('rest_api_init', function () {

    register_rest_route('houzez-mobile-api/v1', '/add-review', array(
        'methods' => 'POST',
        'callback' => 'addReview',
        'permission_callback' => '__return_true'
    ));
    register_rest_route('houzez-mobile-api/v1', '/report-content', array(
        'methods' => 'POST',
        'callback' => 'reportContent',
        'permission_callback' => '__return_true'
    ));
    /// 
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
            ),
            // NEW: Status parameter for filtering review status
            'status' => array(
                'required' => false,
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function ($param) {
                    $allowed = ['publish', 'pending', 'review_rejected', ''];
                    return in_array($param, $allowed);
                },
                'description' => 'Filter reviews by status (publish, pending, review_rejected)'
            )
        )
    ));


    register_rest_route('houzez-reviews/v1', '/approve/(?P<id>\d+)', [
        'methods' => 'POST',
        'callback' => 'houzez_rest_approve_review',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ]);

    // Reject review endpoint
    register_rest_route('houzez-reviews/v1', '/reject/(?P<id>\d+)', [
        'methods' => 'POST',
        'callback' => 'houzez_rest_reject_review',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ]);

    register_rest_route('houzez-reviews/v1', '/trash/(?P<id>\d+)', [
        'methods' => 'POST',
        'callback' => 'houzez_rest_trash_review',
        'permission_callback' => function () {
            return current_user_can('delete_posts');
        }
    ]);






    // Admin Actions Log API
    register_rest_route('houzez-mobile-api/v1', '/admin-actions', [
        'methods' => 'GET',
        'callback' => 'houzez_get_admin_actions_log',
        'permission_callback' => function () {
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
        'permission_callback' => function () {
            return current_user_can('manage_options');
        },
    ]);

});





add_filter('rest_prepare_houzez_reviews', 'prepareReviewsData', 10, 3);

function prepareReviewsData($response, $post, $request)
{
    $response->data['thumbnail'] = houzez_get_profile_pic();
    $response->data['meta'] = get_post_meta(get_the_ID());

    $user = get_user_by('id', get_the_author_meta('ID'));

    $response->data['username'] = $user->user_login;
    $response->data['user_display_name'] = $user->display_name;

    // $response->data['review_likes'] = get_post_meta(get_the_ID(), 'review_likes', true); 
    // $response->data['review_dislikes'] = get_post_meta(get_the_ID(), 'review_dislikes', true);
    //$response->data['review_stars'] = houzez_get_stars(get_post_meta(get_the_ID(), 'review_stars', true), false);
    return $response;
}

function addReview()
{

    if (!is_user_logged_in()) {
        $ajax_response = array('success' => false, 'reason' => 'Please provide user auth.');
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

function reportContent() {
    if (!is_user_logged_in()) {
        $ajax_response = array('success' => false, 'reason' => 'Please provide user auth.');
        wp_send_json($ajax_response, 403);
        return;
    }

    if (!create_nonce_or_throw_error('report-security', 'report-nonce')) {
        return;
    }

    $nonce = $_POST['report-security'];
    if (!wp_verify_nonce($nonce, 'report-nonce')) {
        $ajax_response = array('success' => false, 'reason' => esc_html__('Security check failed!', 'houzi'));
        wp_send_json($ajax_response, 403);
        return;
    }

    global $current_user;
    wp_get_current_user();
    $userID = get_current_user_id();
    $contactName = empty($_POST['name']) ? $current_user->display_name : $_POST['name'];

    $content_type = $_POST['content_type'];
    $content_id = $_POST['content_id'];

    $contentLink = get_post_permalink($content_id);
    $contentTitle = get_the_title($content_id);

    $content_post = get_post($content_id);
    $contentDescription = $content_post->post_content;

    $author_id = get_post_field('post_author', $content_id);
    $content_author = get_the_author_meta('nickname', $author_id);

    $message = empty($_POST['message']) ? "" : $_POST['message'];
    $reason = empty($_POST['reason']) ? "" : $_POST['reason'];
    $email = empty($_POST['email']) ? "" : $_POST['email'];

    $email_subject = "$contactName reported about a $content_type";
    $email_body = "<p><b>Reporter ID:</b> $userID</p>";
    $email_body .= "<p><b>Reporter Name:</b> $contactName</p>";
    if (!empty($email)) {
        $email_body .= "<p><b>Reporter Email:</b> $email</p>";
    }
    $email_body .= "<p><b>$content_type ID:</b> $content_id</p>";
    $email_body .= "<p><b>$content_type title:</b> $contentTitle</p>";
    $email_body .= "<p><b>$content_type content:</b> $contentDescription</p>";
    $email_body .= "<p><b>$content_type author:</b> $content_author</p>";
    $email_body .= "<p><b>Permalink:</b> $contentLink</p>";
    if (!empty($reason)) {
        $email_body .= "<p><b>Reported reason:</b> $reason</p>";
    }
    if (!empty($message)) {
        $email_body .= "<p><b>Message:</b> $message</p>";
    }

    $notification_title = sprintf(
        __('New %s Report', 'houzi'),
        ucfirst($content_type)
    );
    $notification_message = sprintf(
        __('%s reported %s: %s', 'houzi'),
        $contactName,
        $content_type,
        $contentTitle
    );
    if (!empty($reason)) {
        $notification_message .= "\n" . __('Reason', 'houzi') . ": " . $reason;
    }

    $admins = get_users(array(
        'role' => 'administrator',
        'fields' => array('user_email', 'display_name')
    ));

    $admin_emails = array();
    foreach ($admins as $admin) {
        $admin_emails[] = $admin->user_email;
    }

    $headers = array('Content-Type: text/html; charset=UTF-8');
    if (!empty($admin_emails)) {
        wp_mail($admin_emails, $email_subject, $email_body, $headers);
    }

    $extra_data = array(
        'content_type' => $content_type,
        'content_id' => $content_id,
        'content_title' => $contentTitle,
        'content_link' => $contentLink,
        'reporter_id' => $userID,
        'reporter_name' => $contactName,
        'reporter_email' => $email,
        'report_reason' => $reason,
        'report_message' => $message
    );



    foreach ($admins as $admin) {
        $push_notifArgs = array(
            "title" => $notification_title,
            "message" => $notification_message,
            "type" => 'review_report',
            "to" => $admin->user_email,
        );

        do_action('houzez_send_notification', $push_notifArgs);
    }

    $ajax_response = array('success' => true, 'message' => esc_html__('Thank you for reporting, our support will review your report.', 'houzi'));
    wp_send_json($ajax_response, 200);
}


////
function getReviewsAPI($request)
{
    try {
        $entity_type = $request->get_param('entity_type');
        $entity_id = $request->get_param('entity_id');
        $per_page = $request->get_param('per_page');
        $page = $request->get_param('page');
        $orderby = $request->get_param('orderby');
        $order = $request->get_param('order');
        $search = $request->get_param('search');
        $status_param = $request->get_param('status'); 

        $post_status = ['publish']; 
        if (current_user_can('edit_posts')) {
            $post_status = ['publish', 'pending', 'review_rejected'];
        }

        if (!empty($status_param)) {
            if (in_array($status_param, ['pending', 'review_rejected']) && !current_user_can('edit_posts')) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'Insufficient permissions to view this status'
                ), 403);
            }

            if (in_array($status_param, $post_status)) {
                $post_status = [$status_param];
            }
        }

        $query_args = array(
            'post_type' => 'houzez_reviews',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'post_status' => $post_status,
            'orderby' => $orderby === 'rating' ? 'meta_value_num' : 'date',
            'order' => $order
        );

        $meta_query = array();

        if ($entity_id > 0 && !empty($entity_type)) {
            $meta_query[] = array(
                'key' => 'review_' . $entity_type . '_id',
                'value' => $entity_id,
                'compare' => '='
            );
        }

        if (!empty($entity_type)) {
            $meta_query[] = array(
                'key' => 'review_post_type',
                'value' => $entity_type,
                'compare' => '='
            );
        }

        if (!empty($meta_query)) {
            if (count($meta_query) > 1) {
                $meta_query['relation'] = 'AND';
            }
            $query_args['meta_query'] = $meta_query;
        }

        if ($orderby === 'rating') {
            $query_args['meta_key'] = 'review_stars';
        }

        if (!empty($search)) {
            $user_search_args = array(
                'search' => '*' . $search . '*',
                'search_columns' => array('user_login', 'display_name'),
                'fields' => 'ID'
            );
            $matching_users = get_users($user_search_args);
            $query_args['author__in'] = !empty($matching_users) ? $matching_users : [0];
        }

        $reviews_query = new WP_Query($query_args);
        $reviews = array();

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

                if (!$post)
                    continue;

                $author_id = $post->post_author;
                $user = $author_id ? get_userdata($author_id) : null;

                $avatar_url = '';
                if ($user) {
                    $custom_avatar = get_user_meta($author_id, 'houzez_author_custom_picture', true);
                    $wp_avatar = get_avatar_url($author_id, [
                        'size' => 150,
                        'default' => 'identicon'
                    ]);
                    $theme_avatar = function_exists('houzez_get_profile_pic') 
                        ? houzez_get_profile_pic() 
                        : '';
                    $avatar_url = !empty($custom_avatar) 
                        ? $custom_avatar 
                        : ($wp_avatar ? $wp_avatar : $theme_avatar);
                }

                $meta = array();
                $meta_keys = [
                    'review_post_type',
                    'review_stars',
                    'review_by',
                    'review_to',
                    'review_agent_id',
                    'review_agency_id',
                    'review_property_id',
                    'review_author_id'
                ];

                foreach ($meta_keys as $key) {
                    $meta_value = get_post_meta($review_id, $key, true);
                    $meta[$key] = $meta_value;
                }

                $thumbnail_url = '';
                if ($thumbnail_id = get_post_thumbnail_id($review_id)) {
                    $thumbnail_url = wp_get_attachment_image_url($thumbnail_id, 'full');
                }

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
                    'user_display_name' => $user ? $user->display_name : '',
                    'thumbnail' => $avatar_url 
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


function houzez_rest_trash_review(WP_REST_Request $request)
{
    $review_id = $request->get_param('id');

    $review = get_post($review_id);
    if (!$review || $review->post_type !== 'houzez_reviews') {
        return new WP_Error('invalid_review', 'Invalid review ID', ['status' => 404]);
    }

    $user = wp_get_current_user();
    $allowed_roles = ['administrator', 'editor', 'houzez_manager'];

    if (!array_intersect($allowed_roles, (array) $user->roles)) {
        return new WP_Error('permission_denied', 'You do not have permission to trash reviews', ['status' => 403]);
    }

    if ($review->post_status === 'trash') {
        return new WP_Error('already_trashed', 'Review is already in trash', ['status' => 400]);
    }

    $result = wp_trash_post($review_id);

    if (!$result) {
        return new WP_Error('trash_failed', 'Failed to move review to trash', ['status' => 500]);
    }

    return [
        'success' => true,
        'message' => 'Review moved to trash',
        'new_status' => 'trash'
    ];
}



function houzez_rest_approve_review(WP_REST_Request $request)
{
    $review_id = $request->get_param('id');

    $review = get_post($review_id);
    if (!$review || $review->post_type !== 'houzez_reviews') {
        return new WP_Error('invalid_review', 'Invalid review ID', ['status' => 404]);
    }

    $user = wp_get_current_user();
    $allowed_roles = ['administrator', 'editor', 'houzez_manager'];

    if (!array_intersect($allowed_roles, (array) $user->roles)) {
        return new WP_Error('permission_denied', 'You do not have permission to approve reviews', ['status' => 403]);
    }

    if (!in_array($review->post_status, ['pending', 'review_rejected'])) {
        return new WP_Error('invalid_status', 'Review cannot be approved from current status', ['status' => 400]);
    }

    $args = [
        'ID' => $review_id,
        'post_status' => 'publish'
    ];

    $result = wp_update_post($args, true);

    if (is_wp_error($result)) {
        return $result;
    }

    if (function_exists('houzez_admin_review_meta_on_save')) {
        houzez_admin_review_meta_on_save($review_id);
    }

    return [
        'success' => true,
        'message' => 'Review approved',
        'new_status' => 'publish'
    ];
}

/**
 * Reject review via REST API
 */
function houzez_rest_reject_review(WP_REST_Request $request)
{
    $review_id = $request->get_param('id');

    $review = get_post($review_id);
    if (!$review || $review->post_type !== 'houzez_reviews') {
        return new WP_Error('invalid_review', 'Invalid review ID', ['status' => 404]);
    }

    $user = wp_get_current_user();
    $allowed_roles = ['administrator', 'editor', 'houzez_manager'];

    if (!array_intersect($allowed_roles, (array) $user->roles)) {
        return new WP_Error('permission_denied', 'You do not have permission to reject reviews', ['status' => 403]);
    }

    if (!in_array($review->post_status, ['pending', 'publish'])) {
        return new WP_Error('invalid_status', 'Review cannot be rejected from current status', ['status' => 400]);
    }

    $args = [
        'ID' => $review_id,
        'post_status' => 'review_rejected'
    ];

    $result = wp_update_post($args, true);

    if (is_wp_error($result)) {
        return $result;
    }

    if (function_exists('houzez_admin_review_meta_on_save')) {
        houzez_admin_review_meta_on_save($review_id);
    }

    return [
        'success' => true,
        'message' => 'Review rejected',
        'new_status' => 'review_rejected'
    ];
}




// Admin Actions Log API handler
function houzez_get_admin_actions_log(WP_REST_Request $request)
{
    $page = $request->get_param('page');
    $per_page = $request->get_param('per_page');
    $log = get_option('houzez_review_admin_actions', []);

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
function houzez_get_review_settings()
{
    return new WP_REST_Response([
        'success' => true,
        'settings' => [
            'reviews_enabled' => true, // Hardcoded as true since reviews are functional
            'new_ratings_approved_by_admin' => (bool) houzez_option('property_reviews_approved_by_admin'),
            'update_review_approved' => (bool) houzez_option('update_review_approved')
        ]
    ], 200);
}

