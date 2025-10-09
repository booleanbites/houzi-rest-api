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
        'permission_callback' => 'isUserLoggedIn'
    ));
    
    // Submit Verification
    register_rest_route('houzez-mobile-api/v1', '/submit-verification', array(
        'methods' => 'POST',
        'callback' => 'submitVerificationRequest',
        'permission_callback' => 'isUserLoggedIn'
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
        'permission_callback' => 'isUserLoggedIn'
    ));
    
    // Verification Requests (Admin only)
    register_rest_route('houzez-mobile-api/v1', '/verification-requests', array(
        'methods' => 'GET',
        'callback' => 'getVerificationRequests',
        'permission_callback' => 'isUserAdmin'
    ));
    
    // Process Verification (Admin only)
    register_rest_route('houzez-mobile-api/v1', '/process-verification', array(
        'methods' => 'POST',
        'callback' => 'processVerificationRequest',
        'permission_callback' => 'isUserAdmin'
    ));
    
    // Secure Document
    register_rest_route('houzez-mobile-api/v1', '/secure-document', array(
        'methods' => 'GET',
        'callback' => 'getSecureDocument',
        'permission_callback' => 'isUserLoggedIn'
    ));
});

/**
 * Submit verification request
 */
function submitVerificationRequest($request) {
    
    if (!is_user_logged_in()) {
        return new WP_REST_Response(array('success' => false, 'message' => 'You must be logged in to submit a verification request'), 403);
    }

    $user_id = get_current_user_id();
    $params = $request->get_params();
    $files = $request->get_file_params();

    // Check if user verification is enabled
    $verification_enabled = fave_option('enable_user_verification', 0);
    if (!$verification_enabled) {
        return new WP_REST_Response(array('success' => false, 'message' => 'User verification is not enabled'), 400);
    }

    // Check if user already has a pending or approved verification
    $current_status = get_user_meta($user_id, 'houzez_verification_status', true);
    if ($current_status === 'pending') {
        return new WP_REST_Response(array('success' => false, 'message' => 'You already have a pending verification request'), 400);
    } elseif ($current_status === 'approved') {
        return new WP_REST_Response(array('success' => false, 'message' => 'Your account is already verified'), 400);
    }

    // Validate required fields
    $full_name = isset($params['full_name']) ? sanitize_text_field($params['full_name']) : '';
    $document_type = isset($params['document_type']) ? sanitize_text_field($params['document_type']) : '';

    if (empty($full_name) || empty($document_type)) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Please fill in all required fields'), 400);
    }

    // Handle file upload
    if (empty($files['verification_document'])) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Please upload a document'), 400);
    }

    // Initialize verification class
    $verification_class = new Houzez_User_Verification();
    
    // Process front side file upload
    $uploaded_file = $files['verification_document'];
    $allowed_types = array('pdf', 'jpg', 'jpeg', 'png');
    $file_extension = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_types)) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Only PDF, JPG, and PNG files are allowed'), 400);
    }

    // Use the secure file upload method from the class
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

    $verification_data = array(
        'full_name' => $full_name,
        'document_type' => $document_type,
        'document_path' => $movefile['file'],
        'document_url' => $movefile['url'],
        'document_type_mime' => $movefile['type'],
        'status' => 'pending',
        'submitted_on' => current_time('mysql')
    );

    // Handle back side upload if required
    if ($requires_back) {
        if (empty($files['verification_document_back'])) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Please upload the back side of your document'), 400);
        }

        $uploaded_back_file = $files['verification_document_back'];
        $back_file_extension = strtolower(pathinfo($uploaded_back_file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($back_file_extension, $allowed_types)) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Only PDF, JPG, and PNG files are allowed for back side'), 400);
        }

        $movefile_back = $verification_class->handle_secure_file_upload($uploaded_back_file, $user_id);
        
        if (is_wp_error($movefile_back)) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Error uploading back side: ' . $movefile_back->get_error_message()), 400);
        }

        $verification_data['document_back_path'] = $movefile_back['file'];
        $verification_data['document_back_url'] = $movefile_back['url'];
        $verification_data['document_back_type_mime'] = $movefile_back['type'];
    }

    // Save verification data
    update_user_meta($user_id, 'houzez_verification_data', $verification_data);
    update_user_meta($user_id, 'houzez_verification_status', 'pending');

    // Add to verification history
    $verification_class->add_to_verification_history($user_id, 'pending', 'verification_submitted');
    
    // Update agent/agency post with pending verification status
    $verification_class->update_agent_verification_status($user_id, 0);
    
    // Send email notification to admin
    $verification_class->send_admin_notification($user_id, $verification_data);

    return new WP_REST_Response(array(
        'success' => true, 
        'message' => 'Your verification request has been submitted successfully. We will review your documents and get back to you soon.',
        'data' => array(
            'status' => 'pending',
            'submitted_on' => $verification_data['submitted_on']
        )
    ), 200);
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

    $params = $request->get_params();
    $user_id = isset($params['user_id']) ? intval($params['user_id']) : 0;
    $action = isset($params['action_type']) ? sanitize_text_field($params['action_type']) : '';
    $rejection_reason = isset($params['rejection_reason']) ? sanitize_text_field($params['rejection_reason']) : '';
    $additional_info = isset($params['additional_info']) ? sanitize_textarea_field($params['additional_info']) : '';

    if (empty($user_id) || empty($action)) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Invalid request'), 400);
    }

    $verification_data = get_user_meta($user_id, 'houzez_verification_data', true);
    if (empty($verification_data)) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Verification request not found'), 400);
    }

    $verification_class = new Houzez_User_Verification();

    switch ($action) {
        case 'approve':
            $verification_data['status'] = 'approved';
            $verification_data['processed_on'] = current_time('mysql');
            
            update_user_meta($user_id, 'houzez_verification_data', $verification_data);
            update_user_meta($user_id, 'houzez_verification_status', 'approved');
            
            $verification_class->add_to_verification_history($user_id, 'approved', 'verification_approved');
            $verification_class->update_agent_verification_status($user_id, 1);
            $verification_class->send_user_notification($user_id, 'approved');
            
            return new WP_REST_Response(array('success' => true, 'message' => 'Verification request approved successfully'), 200);

        case 'reject':
            $verification_data['status'] = 'rejected';
            $verification_data['processed_on'] = current_time('mysql');
            $verification_data['rejection_reason'] = $rejection_reason;
            
            update_user_meta($user_id, 'houzez_verification_data', $verification_data);
            update_user_meta($user_id, 'houzez_verification_status', 'rejected');
            $verification_class->update_agent_verification_status($user_id, 0);
            
            if (!empty($rejection_reason)) {
                $verification_class->add_to_verification_history($user_id, 'rejected', 'custom_rejection', array($rejection_reason));
            } else {
                $verification_class->add_to_verification_history($user_id, 'rejected', 'verification_rejected');
            }
            
            $verification_class->send_user_notification($user_id, 'rejected', $rejection_reason);
            
            return new WP_REST_Response(array('success' => true, 'message' => 'Verification request rejected successfully'), 200);

        case 'reset':
            $verification_data['status'] = '';
            $verification_data['processed_on'] = current_time('mysql');
            
            update_user_meta($user_id, 'houzez_verification_data', $verification_data);
            update_user_meta($user_id, 'houzez_verification_status', '');
            $verification_class->add_to_verification_history($user_id, '', 'verification_reset');
            
            return new WP_REST_Response(array('success' => true, 'message' => 'Verification status reset successfully'), 200);

        case 'revoke':
            $verification_data['status'] = 'rejected';
            $verification_data['processed_on'] = current_time('mysql');
            $verification_data['rejection_reason'] = 'Verification approval revoked by admin';
            
            update_user_meta($user_id, 'houzez_verification_data', $verification_data);
            update_user_meta($user_id, 'houzez_verification_status', 'rejected');
            $verification_class->update_agent_verification_status($user_id, 0);
            $verification_class->add_to_verification_history($user_id, 'rejected', 'verification_revoked');
            $verification_class->send_user_notification($user_id, 'rejected', 'Your verification approval has been revoked.');
            
            return new WP_REST_Response(array('success' => true, 'message' => 'Verification approval revoked successfully'), 200);

        case 'request_info':
            $verification_data['status'] = 'additional_info_required';
            $verification_data['processed_on'] = current_time('mysql');
            $verification_data['additional_info_request'] = $additional_info;
            
            update_user_meta($user_id, 'houzez_verification_data', $verification_data);
            update_user_meta($user_id, 'houzez_verification_status', 'additional_info_required');
            $verification_class->update_agent_verification_status($user_id, 0);
            
            if (!empty($additional_info)) {
                $verification_class->add_to_verification_history($user_id, 'additional_info_required', 'custom_additional_info', array($additional_info));
            } else {
                $verification_class->add_to_verification_history($user_id, 'additional_info_required', 'additional_info_requested');
            }
            
            $verification_class->send_user_notification($user_id, 'additional_info_required', $additional_info);
            
            return new WP_REST_Response(array('success' => true, 'message' => 'Additional information request sent successfully'), 200);

        default:
            return new WP_REST_Response(array('success' => false, 'message' => 'Invalid action'), 400);
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
        'status' => $verification_status ?: 'none',
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
 * Get verification requests (admin only)
 */
function getVerificationRequests($request) {
    
    if (!current_user_can('manage_options')) {
        return new WP_REST_Response(array('success' => false, 'message' => 'You do not have permission to perform this action'), 403);
    }

    $params = $request->get_params();
    $status = isset($params['status']) ? sanitize_text_field($params['status']) : '';
    $page = isset($params['page']) ? intval($params['page']) : 1;
    $per_page = isset($params['per_page']) ? intval($params['per_page']) : 20;

    $verification_class = new Houzez_User_Verification();
    
    // Get all users with verification data
    global $wpdb;
    $meta_key = 'houzez_verification_status';
    
    $query = "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s";
    $query_params = array($meta_key);
    
    if (!empty($status) && $status !== 'all') {
        $query .= " AND meta_value = %s";
        $query_params[] = $status;
    }
    
    $offset = ($page - 1) * $per_page;
    $query .= " LIMIT %d OFFSET %d";
    $query_params[] = $per_page;
    $query_params[] = $offset;

    $user_ids = $wpdb->get_col($wpdb->prepare($query, $query_params));
    
    $requests = array();
    foreach ($user_ids as $user_id) {
        $user = get_userdata($user_id);
        if ($user) {
            $verification_data = get_user_meta($user_id, 'houzez_verification_data', true);
            if (!empty($verification_data)) {
                $user_info = array(
                    'user_id' => $user_id,
                    'display_name' => $user->display_name,
                    'user_email' => $user->user_email,
                    'user_registered' => $user->user_registered
                );

                // Create safe verification data without file paths
                $safe_verification_data = array(
                    'full_name' => isset($verification_data['full_name']) ? $verification_data['full_name'] : '',
                    'document_type' => isset($verification_data['document_type']) ? $verification_data['document_type'] : '',
                    'document_url' => isset($verification_data['document_url']) ? $verification_data['document_url'] : '',
                    'document_back_url' => isset($verification_data['document_back_url']) ? $verification_data['document_back_url'] : '',
                    'submitted_on' => isset($verification_data['submitted_on']) ? $verification_data['submitted_on'] : '',
                    'status' => isset($verification_data['status']) ? $verification_data['status'] : ''
                );

                $requests[] = array(
                    'user' => $user_info,
                    'verification_data' => $safe_verification_data
                );
            }
        }
    }

    // Get total count for pagination
    $count_query = "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s";
    $count_params = array($meta_key);
    
    if (!empty($status) && $status !== 'all') {
        $count_query .= " AND meta_value = %s";
        $count_params[] = $status;
    }
    
    $total_count = $wpdb->get_var($wpdb->prepare($count_query, $count_params));
    $total_pages = ceil($total_count / $per_page);

    return new WP_REST_Response(array(
        'success' => true,
        'data' => array(
            'requests' => $requests,
            'pagination' => array(
                'current_page' => $page,
                'per_page' => $per_page,
                'total_count' => $total_count,
                'total_pages' => $total_pages
            )
        )
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