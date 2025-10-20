<?php
/**
 * Functions to handle user verification related APIs.
 *
 *
 * @package Houzi Mobile Api
 * @since Houzi 1.0
 * @author BooleanBites
 */

//-----------------------------Lightspeed exclude URLs-------------------------------------
// By default all POST URLs aren't cached
add_action('litespeed_init', function () {

    //these URLs need to be excluded from lightspeed caches
    $exclude_url_list = array(
        "submit_verification",
        "submit_additional_info",
        "process_verification",
        "secure_document",
        "verification_status",
        "verification_history"
    );
    foreach ($exclude_url_list as $exclude_url) {
        if (strpos($_SERVER['REQUEST_URI'], $exclude_url) !== FALSE) {
            do_action('litespeed_control_set_nocache', 'no-cache for rest api');
        }
    }
});

// Register all API routes at once
add_action('rest_api_init', function () {
    
    register_rest_route('houzez-mobile-api/v1', '/document-types', array(
        'methods' => 'GET',
        'callback' => 'getDocumentTypes',
        'permission_callback' => '__return_true'
    ));
    
    register_rest_route('houzez-mobile-api/v1', '/verification-status', array(
        'methods' => 'GET',
        'callback' => 'getVerificationStatus',
        'permission_callback' => '__return_true'
    ));
	
    
    register_rest_route('houzez-mobile-api/v1', '/submit-verification', array(
        'methods' => 'POST',
        'callback' => 'submitVerificationRequest',
        'permission_callback' => '__return_true'
    ));
    
    register_rest_route('houzez-mobile-api/v1', '/submit-additional-info', array(
        'methods' => 'POST',
        'callback' => 'submitAdditionalInfo',
        'permission_callback' => '__return_true'
    ));
    
    register_rest_route('houzez-mobile-api/v1', '/verification-history', array(
        'methods' => 'GET',
        'callback' => 'getVerificationHistory',
        'permission_callback' => '__return_true'
    ));
    
    // Verification Requests (Admin only)
    register_rest_route('houzez-mobile-api/v1', '/verification-requests', array(
        'methods' => 'GET',
        'callback' => 'getVerificationRequests',
        'permission_callback' => '__return_true'
    ));
    
    // Process Verification (Admin only)
    register_rest_route('houzez-mobile-api/v1', '/process-verification', array(
        'methods' => 'POST',
        'callback' => 'processVerificationRequest',
        'permission_callback' => '__return_true'
    ));
    
    
});

/**
 * Submit verification request
 */
function submitVerificationRequest($request) {
    
    if (!is_user_logged_in()) {
        return new WP_REST_Response(array('success' => false, 'message' => 'You must be logged in to submit a verification request'), 403);
    }

    $_POST = $request->get_params();
    $_FILES = $request->get_file_params();
    try {
        do_action("wp_ajax_houzez_submit_verification");
		
        return new WP_REST_Response(array(
            'success' => true, 
            'message' => 'Your verification request has been submitted successfully.',
        ), 200);
        
    } catch (Exception $e) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Error submitting verification: ' . $e->getMessage()), 400);
    }
}

/**
 * Submit additional information
 */
function submitAdditionalInfo($request) {
    
   
 if (!is_user_logged_in()) {
        return new WP_REST_Response(array('success' => false, 'message' => 'You must be logged in to submit an Additional Info'), 403);
    }

    $_POST = $request->get_params();
    $_FILES = $request->get_file_params();
    try {
        do_action("wp_ajax_houzez_submit_additional_info");
		
        return new WP_REST_Response(array(
            'success' => true, 
            'message' => 'Your additional verification request has been submitted successfully.',
        ), 200);
        
    } catch (Exception $e) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Error submitting additional info: ' . $e->getMessage()), 400);
    }
}





/**
 * Process verification request 
 */
function processVerificationRequest($request) {
    
    if (!current_user_can('manage_options')) {
        return new WP_REST_Response(array('success' => false, 'message' => 'You do not have permission to perform this action'), 403);
    }

    $original_post = $_POST;
    
    try {
        $_POST = $request->get_params();
        

        do_action("wp_ajax_houzez_process_verification");
        
        $_POST = $original_post;
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Verification request processed successfully'
        ), 200);

    } catch (Exception $e) {
        $_POST = $original_post;
        return new WP_REST_Response(array('success' => false, 'message' => 'Error processing request: ' . $e->getMessage()), 500);
    }
}

/**
 * Get verification status for current user
 */
function getVerificationStatus($request) {
    
    if (!is_user_logged_in()) {
        return new WP_REST_Response(array('success' => false, 'message' => 'You must be logged in'), 403);
    }

    $user_id = get_current_user_id();
	
	$verification_class = new Houzez_User_Verification();
    $verification_status_for_user = $verification_class->get_verification_status($user_id);
    $user_verification_data = $verification_class->get_verification_data($user_id);
	$is_user_verified = $verification_class->is_user_verified($user_id);
	
	$data = array("status" => $verification_status_for_user, "is_verified" => $is_user_verified, "verification_data" => $user_verification_data);

    return new WP_REST_Response(array(
        'success' => true,
        'data' => $data
    ), 200);
}

/**
 * Get verification history for user
 */
function getVerificationHistory($request) {
    
    if (!is_user_logged_in()) {
        return new WP_REST_Response(array('success' => false, 'message' => 'You must be logged in'), 403);
    }

	// If user_id is provided and current user is admin, allow getting history for any user
    //  $target_user_id = $user_id;
    //     if (isset($params['user_id']) && current_user_can('manage_options')) {
    //         $target_user_id = intval($params['user_id']);
    //     }
	
    $user_id = get_current_user_id();
    $params = $request->get_params();
   

    $verification_class = new Houzez_User_Verification();
    $history = $verification_class->get_verification_history($user_id);

    return new WP_REST_Response(array(
        'success' => true,
        'data' => $history
    ), 200);
}

/**
 * Get verification requests (admin only)
 */

function getVerificationRequests($request) {
    
    if (!current_user_can('manage_options')) {
        return new WP_REST_Response(array('success' => false, 'message' => 'You do not have permission to perform this action'), 403);
    }
	
	 if (!current_user_can('manage_options')) {
        return new WP_REST_Response(array('success' => false, 'message' => 'You do not have permission to perform this action'), 403);
    }

    $params = $request->get_params();
    $status_param = isset($params['status']) ? sanitize_text_field($params['status']) : 'all';
    
    $status = ($status_param === 'all') ? '' : $status_param;
    
    $verification_class = new Houzez_User_Verification();
    $data = $verification_class->get_verification_requests($status);
    
    return new WP_REST_Response(array(
        'success' => true,
        'data' => $data
    ), 200);

}



/**
 * Get available document types
 */
function getDocumentTypes() {
    
    $verification_class = new Houzez_User_Verification();
    $document_types = $verification_class->get_document_type('');

    return new WP_REST_Response(array(
        'success' => true,
        'data' => $document_types
    ), 200);
}
