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
    
    // Document Types
    register_rest_route('houzez-mobile-api/v1', '/document-types', array(
        'methods' => 'GET',
        'callback' => 'getDocumentTypes',
        'permission_callback' => '__return_true'
    ));
    
    // Verification Status
    register_rest_route('houzez-mobile-api/v1', '/verification-status', array(
        'methods' => 'GET',
        'callback' => 'getVerificationStatus',
        'permission_callback' => '__return_true'
    ));
	register_rest_route('houzez-mobile-api/v1', '/test-verification', array(
    'methods' => 'POST',
    'callback' => 'testVerification',
    'permission_callback' => '__return_true'
));
    
    // Submit Verification
    register_rest_route('houzez-mobile-api/v1', '/submit-verification', array(
        'methods' => 'POST',
        'callback' => 'submitVerificationRequest',
        'permission_callback' => '__return_true'
    ));
    
    // Submit Additional Info
    register_rest_route('houzez-mobile-api/v1', '/submit-additional-info', array(
        'methods' => 'POST',
        'callback' => 'submitAdditionalInfo',
        'permission_callback' => 'isUserLoggedIn'
    ));
    
    // Verification History
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
    
    // Secure Document
    register_rest_route('houzez-mobile-api/v1', '/secure-document', array(
        'methods' => 'GET',
        'callback' => 'getSecureDocument',
        'permission_callback' => 'isUserLoggedIn'
    ));
});
function testVerification($request) {
    return new WP_REST_Response(array(
        'success' => true, 
        'message' => 'Basic API is working',
        'user_id' => get_current_user_id()
    ), 200);
}

/**
 * Submit verification request by calling Houzez method directly
 */
function submitVerificationRequest($request) {
    
    if (!is_user_logged_in()) {
        return new WP_REST_Response(array('success' => false, 'message' => 'You must be logged in to submit a verification request'), 403);
    }

    // Set up the necessary POST data to mimic the AJAX request
    $_POST = $request->get_params();
    $_FILES = $request->get_file_params();
    $_POST['security'] = wp_create_nonce('houzez_verification_nonce');
    
    // Initialize the verification class
    $verification_class = new Houzez_User_Verification();
    
    // Call the submit_verification_request method directly
    try {
        $verification_class->submit_verification_request();
        
        // If we reach here, the method executed successfully
        return new WP_REST_Response(array(
            'success' => true, 
            'message' => 'Your verification request has been submitted successfully.',
        ), 200);
        
    } catch (Exception $e) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Error submitting verification: ' . $e->getMessage()), 500);
    }
}

/**
 * Submit additional information
 */
function submitAdditionalInfo($request) {
    
    if (!is_user_logged_in()) {
        return new WP_REST_Response(array('success' => false, 'message' => 'You must be logged in to submit additional information'), 403);
    }

    $user_id = get_current_user_id();
    $params = $request->get_params();
    $files = $request->get_file_params();

    // Check if user has a verification in additional_info_required status
    $current_status = get_user_meta($user_id, 'houzez_verification_status', true);
    if ($current_status !== 'additional_info_required') {
        return new WP_REST_Response(array('success' => false, 'message' => 'You do not have an active request for additional information'), 400);
    }

    // Get existing verification data
    $verification_data = get_user_meta($user_id, 'houzez_verification_data', true);
    if (empty($verification_data)) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Verification data not found'), 400);
    }

    // Validate required fields
    $document_type = isset($params['document_type']) ? sanitize_text_field($params['document_type']) : '';
    $additional_notes = isset($params['additional_notes']) ? sanitize_textarea_field($params['additional_notes']) : '';

    if (empty($document_type)) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Please select a document type'), 400);
    }

    // Handle file upload
    if (empty($files['additional_document'])) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Please upload a document'), 400);
    }

    // Initialize verification class
    $verification_class = new Houzez_User_Verification();
    
    // Process front side file upload
    $uploaded_file = $files['additional_document'];
    $allowed_types = array('pdf', 'jpg', 'jpeg', 'png');
    $file_extension = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_types)) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Only PDF, JPG, and PNG files are allowed'), 400);
    }

    $movefile = $verification_class->handle_secure_file_upload($uploaded_file, $user_id);

    if (is_wp_error($movefile)) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Error uploading document: ' . $movefile->get_error_message()), 400);
    }

    // Check if document requires back side
    $document_types = $verification_class->get_document_type('');
    $requires_back = false;
    
    if (isset($document_types[$document_type]) && $document_types[$document_type]['requires_back']) {
        $requires_back = true;
    }

    // Update verification data
    $verification_data['additional_document_type'] = $document_type;
    $verification_data['additional_document_path'] = $movefile['file'];
    $verification_data['additional_document_url'] = $movefile['url'];
    $verification_data['additional_document_type_mime'] = $movefile['type'];
    $verification_data['additional_notes'] = $additional_notes;

    // Handle back side upload if required
    if ($requires_back) {
        if (empty($files['additional_document_back'])) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Please upload the back side of your document'), 400);
        }

        $uploaded_back_file = $files['additional_document_back'];
        $back_file_extension = strtolower(pathinfo($uploaded_back_file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($back_file_extension, $allowed_types)) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Only PDF, JPG, and PNG files are allowed for back side'), 400);
        }

        $movefile_back = $verification_class->handle_secure_file_upload($uploaded_back_file, $user_id);
        
        if (is_wp_error($movefile_back)) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Error uploading back side: ' . $movefile_back->get_error_message()), 400);
        }

        $verification_data['additional_document_back_path'] = $movefile_back['file'];
        $verification_data['additional_document_back_url'] = $movefile_back['url'];
        $verification_data['additional_document_back_type_mime'] = $movefile_back['type'];
    }

    // Change status back to pending
    $verification_data['status'] = 'pending';
    $verification_data['additional_info_submitted_on'] = current_time('mysql');
    
    update_user_meta($user_id, 'houzez_verification_data', $verification_data);
    update_user_meta($user_id, 'houzez_verification_status', 'pending');

    // Add to verification history
    $verification_class->add_to_verification_history($user_id, 'pending', 'additional_info_submitted');
    
    // Update agent/agency post with pending verification status
    $verification_class->update_agent_verification_status($user_id, 0);
    
    // Send email notification to admin
    $verification_class->send_admin_additional_info_notification($user_id, $verification_data);

    return new WP_REST_Response(array(
        'success' => true, 
        'message' => 'Your additional information has been submitted successfully. We will review your documents and get back to you soon.',
        'data' => array(
            'status' => 'pending',
            'additional_info_submitted_on' => $verification_data['additional_info_submitted_on']
        )
    ), 200);
}

/**
 * Process verification request (admin only)
 */
function processVerificationRequest($request) {
    
    if (!current_user_can('manage_options')) {
        return new WP_REST_Response(array('success' => false, 'message' => 'You do not have permission to perform this action'), 403);
    }

    $original_post = $_POST;
	
    
    try {
        // Set up the data for Houzez
        $_POST = $request->get_params();
// 		$_POST = $params;
//         $nonce = isset($_POST['security']) ? sanitize_text_field($_POST['security']) : '';
        // Add required security nonce
        if (!isset($_POST['security']) ) {
            $_POST['security'] = wp_create_nonce('houzez_admin_verification_nonce');
//             $_POST['security'] = sanitize_text_field($params['security']);
        }

        // Initialize verification class
        $verification_class = new Houzez_User_Verification();
        
        // Call the public method directly
        $verification_class->process_verification_request();
        
        // If we reach here, it was successful
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
    $params = $request->get_params();
    
    // If user_id is provided and current user is admin, allow getting status for any user
    $target_user_id = $user_id;
    if (isset($params['user_id']) && current_user_can('manage_options')) {
        $target_user_id = intval($params['user_id']);
    }

    $verification_status = get_user_meta($target_user_id, 'houzez_verification_status', true);
    $verification_data = get_user_meta($target_user_id, 'houzez_verification_data', true);

    $response_data = array(
        'status' => $verification_status ?: 'Not Verified',
        'is_verified' => ($verification_status === 'approved')
    );

    if (!empty($verification_data)) {
        // Don't expose file paths in the response
        $safe_verification_data = array(
            'full_name' => isset($verification_data['full_name']) ? $verification_data['full_name'] : '',
            'document_type' => isset($verification_data['document_type']) ? $verification_data['document_type'] : '',
            'document_url' => isset($verification_data['document_url']) ? $verification_data['document_url'] : '',
            'document_back_url' => isset($verification_data['document_back_url']) ? $verification_data['document_back_url'] : '',
            'submitted_on' => isset($verification_data['submitted_on']) ? $verification_data['submitted_on'] : '',
            'processed_on' => isset($verification_data['processed_on']) ? $verification_data['processed_on'] : '',
            'rejection_reason' => isset($verification_data['rejection_reason']) ? $verification_data['rejection_reason'] : '',
            'additional_info_request' => isset($verification_data['additional_info_request']) ? $verification_data['additional_info_request'] : ''
        );

        $response_data['verification_data'] = $safe_verification_data;
    }

    return new WP_REST_Response(array(
        'success' => true,
        'data' => $response_data
    ), 200);
}

/**
 * Get verification history for user
 */
function getVerificationHistory($request) {
    
    if (!is_user_logged_in()) {
        return new WP_REST_Response(array('success' => false, 'message' => 'You must be logged in'), 403);
    }

    $user_id = get_current_user_id();
    $params = $request->get_params();
    
    // If user_id is provided and current user is admin, allow getting history for any user
    $target_user_id = $user_id;
    if (isset($params['user_id']) && current_user_can('manage_options')) {
        $target_user_id = intval($params['user_id']);
    }

    $verification_class = new Houzez_User_Verification();
    $history = $verification_class->get_verification_history($target_user_id);

    return new WP_REST_Response(array(
        'success' => true,
        'data' => $history
    ), 200);
}

/**
 * Get verification requests with stats, counts, and pagination (admin only)
 */
function getVerificationRequests($request) {
    
    if (!current_user_can('manage_options')) {
        return new WP_REST_Response(array('success' => false, 'message' => 'You do not have permission to perform this action'), 403);
    }

    $params = $request->get_params();
    $status = isset($params['status']) ? sanitize_text_field($params['status']) : 'all';
    $page = isset($params['page']) ? intval($params['page']) : 1;
    $per_page = isset($params['per_page']) ? intval($params['per_page']) : 20;
    $search = isset($params['search']) ? sanitize_text_field($params['search']) : '';

    $verification_class = new Houzez_User_Verification();
    
    // Get all status counts
    $status_counts = array(
        'all' => 0,
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0,
        'additional_info_required' => 0,
        'none' => 0
    );
    
    global $wpdb;
    $meta_key = 'houzez_verification_status';
    
    // Get counts for each status
    $status_counts['all'] = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s",
        $meta_key
    ));
    
    // Get counts for specific statuses
    $specific_statuses = array('pending', 'approved', 'rejected', 'additional_info_required');
    foreach ($specific_statuses as $status_type) {
        $status_counts[$status_type] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s",
            $meta_key,
            $status_type
        ));
    }
    
    // Count users with no verification status
    $status_counts['none'] = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->users} u 
         WHERE NOT EXISTS (
             SELECT 1 FROM {$wpdb->usermeta} um 
             WHERE um.user_id = u.ID AND um.meta_key = 'houzez_verification_status'
         )"
    );

    // Build the main query for verification requests
    $query = "SELECT um.user_id FROM {$wpdb->usermeta} um";
    $query_params = array($meta_key);
    
    // Add user table join if searching
    if (!empty($search)) {
        $query .= " INNER JOIN {$wpdb->users} u ON um.user_id = u.ID";
    }
    
    $query .= " WHERE um.meta_key = %s";
    
    // Filter by status
    if ($status !== 'all') {
        $query .= " AND um.meta_value = %s";
        $query_params[] = $status;
    }
    
    // Add search condition
    if (!empty($search)) {
        $query .= " AND (u.display_name LIKE %s OR u.user_email LIKE %s)";
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $query_params[] = $search_term;
        $query_params[] = $search_term;
    }
    
    // Calculate offset and add pagination
    $offset = ($page - 1) * $per_page;
    $query .= " ORDER BY um.umeta_id DESC LIMIT %d OFFSET %d";
    $query_params[] = $per_page;
    $query_params[] = $offset;

    $user_ids = $wpdb->get_col($wpdb->prepare($query, $query_params));
    
    // Process the requests
    $requests = array();
    foreach ($user_ids as $user_id) {
        $user_data = getEnhancedUserData($user_id);
        $verification_data = get_user_meta($user_id, 'houzez_verification_data', true);
        
        if (!empty($verification_data) && $user_data) {
            // Get verification history
            $history = $verification_class->get_verification_history($user_id);
            
            // Create safe verification data
            $safe_verification_data = array(
                'full_name' => isset($verification_data['full_name']) ? $verification_data['full_name'] : '',
                'document_type' => isset($verification_data['document_type']) ? $verification_data['document_type'] : '',
                'document_url' => isset($verification_data['document_url']) ? $verification_data['document_url'] : '',
                'document_back_url' => isset($verification_data['document_back_url']) ? $verification_data['document_back_url'] : '',
                'submitted_on' => isset($verification_data['submitted_on']) ? $verification_data['submitted_on'] : '',
                'processed_on' => isset($verification_data['processed_on']) ? $verification_data['processed_on'] : '',
                'status' => isset($verification_data['status']) ? $verification_data['status'] : '',
                'rejection_reason' => isset($verification_data['rejection_reason']) ? $verification_data['rejection_reason'] : '',
                'additional_info_request' => isset($verification_data['additional_info_request']) ? $verification_data['additional_info_request'] : '',
                'additional_notes' => isset($verification_data['additional_notes']) ? $verification_data['additional_notes'] : '',
                'history' => $history
            );

            $requests[] = array(
                'user' => $user_data,
                'verification_data' => $safe_verification_data
            );
        }
    }

    // Get total count for the current filtered results
    $count_query = "SELECT COUNT(*) FROM {$wpdb->usermeta} um";
    $count_params = array($meta_key);
    
    if (!empty($search)) {
        $count_query .= " INNER JOIN {$wpdb->users} u ON um.user_id = u.ID";
    }
    
    $count_query .= " WHERE um.meta_key = %s";
    
    if ($status !== 'all') {
        $count_query .= " AND um.meta_value = %s";
        $count_params[] = $status;
    }
    
    if (!empty($search)) {
        $count_query .= " AND (u.display_name LIKE %s OR u.user_email LIKE %s)";
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $count_params[] = $search_term;
        $count_params[] = $search_term;
    }
    
    $total_count = $wpdb->get_var($wpdb->prepare($count_query, $count_params));
    $total_pages = ceil($total_count / $per_page);

    // Prepare the complete response
    $response_data = array(
        'stats' => array(
            'total_requests' => $status_counts['all'],
            'pending_review' => $status_counts['pending'],
            'approved' => $status_counts['approved'],
            'rejected' => $status_counts['rejected'],
            'additional_info_required' => $status_counts['additional_info_required'],
            'not_submitted' => $status_counts['none']
        ),
        'status_counts' => $status_counts,
        'requests' => $requests,
        'filters' => array(
            'current_status' => $status,
            'current_search' => $search
        ),
        'pagination' => array(
            'current_page' => $page,
            'per_page' => $per_page,
            'total_count' => $total_count,
            'total_pages' => $total_pages,
            'has_previous' => $page > 1,
            'has_next' => $page < $total_pages
        )
    );

    return new WP_REST_Response(array(
        'success' => true,
        'data' => $response_data
    ), 200);
}

/**
 * Get enhanced user data with agent/agency information
 */
function getEnhancedUserData($user_id) {
    $user = get_userdata($user_id);
    if (!$user) {
        return null;
    }
    
    $user_data = array(
        'user_id' => $user_id,
        'display_name' => $user->display_name,
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'user_email' => $user->user_email,
        'user_registered' => $user->user_registered,
        'avatar_url' => get_avatar_url($user_id, array('size' => 100)),
        'roles' => $user->roles
    );
    
    // Get agent/agency information
    $agent_id = get_user_meta($user_id, 'fave_author_agent_id', true);
    $agency_id = get_user_meta($user_id, 'fave_author_agency_id', true);
    
    if ($agent_id) {
        $user_data['agent_info'] = array(
            'agent_id' => $agent_id,
            'agent_name' => get_the_title($agent_id),
            'agent_phone' => get_post_meta($agent_id, 'fave_agent_office_num', true),
            'agent_mobile' => get_post_meta($agent_id, 'fave_agent_mobile', true),
            'agent_photo' => get_post_meta($agent_id, 'fave_agent_custom_picture', true),
            'agent_website' => get_post_meta($agent_id, 'fave_agent_website', true)
        );
    }
    
    if ($agency_id) {
        $user_data['agency_info'] = array(
            'agency_id' => $agency_id,
            'agency_name' => get_the_title($agency_id),
            'agency_phone' => get_post_meta($agency_id, 'fave_agency_phone', true),
            'agency_logo' => get_post_meta($agency_id, 'fave_agency_logo', true),
            'agency_website' => get_post_meta($agency_id, 'fave_agency_website', true)
        );
    }
    
    return $user_data;
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

/**
 * Get secure document (file download)
 */
function getSecureDocument($request) {
    
    $params = $request->get_params();
    $user_id = isset($params['user_id']) ? intval($params['user_id']) : 0;
    $filename = isset($params['file']) ? sanitize_file_name($params['file']) : '';

    // Basic security check
    if (!is_user_logged_in()) {
        return new WP_REST_Response(array('success' => false, 'message' => 'You must be logged in to access this file'), 403);
    }

    // Admins and the file owner can access
    if (!current_user_can('manage_options') && get_current_user_id() != $user_id) {
        return new WP_REST_Response(array('success' => false, 'message' => 'You do not have permission to access this file'), 403);
    }

    $verification_class = new Houzez_User_Verification();
    
    // Use the secure document delivery method from the class
    // This will handle the file delivery directly
    $verification_class->deliver_secure_document();

    // If we reach here, something went wrong with file delivery
    return new WP_REST_Response(array('success' => false, 'message' => 'File not found'), 404);
}