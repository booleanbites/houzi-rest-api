<?php

//-----------------------------Lightspeed exclude URLs-------------------------------------
// By default all POST URLs aren't cached
add_action('litespeed_init', function () {

    //these URLs need to be excluded from lightspeed caches
    $exclude_url_list = array(
        "message_threads",
        "delete_message_thread",
        "start_message_thread",
        "thread_messages",
        "send_message"
    );
    foreach ($exclude_url_list as $exclude_url) {
        if (strpos($_SERVER['REQUEST_URI'], $exclude_url) !== FALSE) {
            do_action('litespeed_control_set_nocache', 'no-cache for rest api');
        }
    }

    //add these URLs to cache if required (even POSTs)
    $include_url_list = array(
        "sample-url",
    );
    foreach ($include_url_list as $include_url) {
        if (strpos($_SERVER['REQUEST_URI'], $exclude_url) !== FALSE) {
            do_action('litespeed_control_set_cacheable', 'cache for rest api');
        }
    }
});

add_action('rest_api_init', function () {
    register_rest_route('houzez-mobile-api/v1', '/message_threads', array(
        'methods' => 'GET',
        'callback' => 'getMessageThreads',
        'permission_callback' => '__return_true'
    )
    );
});

add_action('rest_api_init', function () {
    register_rest_route('houzez-mobile-api/v1', '/delete_message_thread',
        array(
            'methods' => 'POST',
            'callback' => 'deleteMessageThread',
            'permission_callback' => '__return_true'
        )
    );
});

add_action('rest_api_init', function () {
    register_rest_route('houzez-mobile-api/v1', '/start_message_thread',
        array(
            'methods' => 'POST',
            'callback' => 'startMessageThread',
            'permission_callback' => '__return_true'
        )
    );
});

add_action('rest_api_init', function () {
    register_rest_route( 'houzez-mobile-api/v1', '/thread_messages',
        array(
            'methods' => 'GET',
            'callback' => 'getThreadMessages',
            'permission_callback' => '__return_true'
        )
    );
});

add_action('rest_api_init', function () {
    register_rest_route('houzez-mobile-api/v1', '/send_message',
        array(
            'methods' => 'POST',
            'callback' => 'sendMessage',
            'permission_callback' => '__return_true'
        )
    );
});

function getMessageThreads()
{

    if (!is_user_logged_in()) {
        $ajax_response = array('success' => false, 'reason' => 'Please provide user auth.');
        wp_send_json($ajax_response, 403);
        return;
    }

    global $wpdb, $current_user;

    $current_user_id = get_current_user_id();
    $table = $wpdb->prefix . 'houzez_threads';
    $sender_status = 'Offline';
    $receiver_status = 'Offline';
    $filtered_threads = [];
    $current_page = isset($_GET['page']) ? intval($_GET['page']) : 1; // Current page number, default to 1 if not set
    $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 10; // Number of threads per page, default to 10

    // Calculate the offset
    $offset = ($current_page - 1) * $per_page;

    $houzez_threads = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE sender_id = %d OR receiver_id = %d LIMIT %d OFFSET %d",
            $current_user_id,
            $current_user_id,
            $per_page,
            $offset
        )
    );

    $messages_table = $wpdb->prefix . 'houzez_thread_messages';

    foreach ($houzez_threads as $thread) {

        if (isset($thread) && !empty($thread)) {

            $temp_thread = [];

            $thread_id = $thread->id;
            if (isset($thread_id) && !empty($thread_id)) {
                $temp_thread["thread_id"] = $thread_id;
            }

            $houzez_messages = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$messages_table} WHERE thread_id = %d ORDER BY id DESC",
                    $thread_id
                )
            );

            if (isset($houzez_messages) && !empty($houzez_messages)) {
                $temp_thread["last_message"] = $houzez_messages[0]->message;
                $last_message_author_id = $houzez_messages[0]->created_by;

                if (isset($last_message_author_id) && !empty($last_message_author_id)) {
                    $temp_thread["last_message_author_id"] = $last_message_author_id;

                    $last_message_author_first_name = get_the_author_meta('first_name', $last_message_author_id);
                    $last_message_author_last_name = get_the_author_meta('last_name', $last_message_author_id);
                    $last_message_author_display_name = get_the_author_meta('display_name', $last_message_author_id);

                    if (isset($last_message_author_display_name) && !empty($last_message_author_display_name)) {
                        $temp_thread["last_message_author_display_name"] = $last_message_author_display_name;
                    } else {
                        if (!empty($last_message_author_first_name) && !empty($last_message_author_last_name)) {
                            $last_message_author_display_name = $last_message_author_first_name . ' ' . $last_message_author_last_name;
                            $temp_thread["last_message_author_display_name"] = $last_message_author_display_name;
                        } else {
                            $temp_thread["last_message_author_first_name"] = $last_message_author_first_name;
                            $temp_thread["last_message_author_last_name"] = $last_message_author_last_name;
                        }
                    }
                }
            }

            $sender_id = $thread->sender_id;
            $sender_first_name = get_the_author_meta('first_name', $sender_id);
            $sender_last_name = get_the_author_meta('last_name', $sender_id);
            $sender_display_name = get_the_author_meta('display_name', $sender_id);
            $sender_picture = get_the_author_meta('fave_author_custom_picture', $sender_id);

            if (empty($sender_picture)) {
                $sender_picture = get_template_directory_uri() . '/img/profile-avatar.png';
            }

            if (houzez_is_user_online($sender_id)) {
                $sender_status = 'Online';
            }

            $receiver_id = $thread->receiver_id;
            $receiver_first_name = get_the_author_meta('first_name', $receiver_id);
            $receiver_last_name = get_the_author_meta('last_name', $receiver_id);
            $receiver_display_name = get_the_author_meta('display_name', $receiver_id);
            $receiver_picture = get_the_author_meta('fave_author_custom_picture', $receiver_id);

            if (empty($receiver_custom_picture)) {
                $receiver_picture = get_template_directory_uri() . '/img/profile-avatar.png';
            }

            if (houzez_is_user_online($receiver_id)) {
                $receiver_status = 'Online';
            }

            $temp_thread["seen"] = $thread->seen;
            $temp_thread["time"] = $thread->time;

            $temp_thread["property_id"] = $thread->property_id;
            $temp_thread["property_title"] = get_post_field('post_title', $thread->property_id);

            $temp_thread["sender_id"] = $sender_id;

            if (isset($sender_display_name) && !empty($sender_display_name)) {
                $temp_thread["sender_display_name"] = $sender_display_name;
            } else {
                if (!empty($sender_first_name) && !empty($sender_last_name)) {
                    $sender_display_name = $sender_first_name . ' ' . $sender_last_name;
                    $temp_thread["sender_display_name"] = $sender_display_name;
                } else {
                    $temp_thread["sender_first_name"] = $sender_first_name;
                    $temp_thread["sender_last_name"] = $sender_last_name;
                }
            }

            $temp_thread["sender_picture"] = $sender_picture;
            $temp_thread["sender_status"] = $sender_status;

            $temp_thread["receiver_id"] = $receiver_id;
            if (isset($receiver_display_name) && !empty($receiver_display_name)) {
                $temp_thread["receiver_display_name"] = $receiver_display_name;
            } else {
                if (!empty($receiver_first_name) && !empty($receiver_last_name)) {
                    $receiver_display_name = $receiver_first_name . ' ' . $receiver_last_name;
                    $temp_thread["receiver_display_name"] = $receiver_display_name;
                } else {
                    $temp_thread["receiver_first_name"] = $receiver_first_name;
                    $temp_thread["receiver_last_name"] = $receiver_last_name;
                }
            }

            $temp_thread["receiver_picture"] = $receiver_picture;
            $temp_thread["receiver_status"] = $receiver_status;

            $temp_thread["sender_delete"] = $thread->sender_delete;
            $temp_thread["receiver_delete"] = $thread->receiver_delete;

            $filtered_threads[] = $temp_thread;
        }
    }

    $ajax_response = array('success' => true, 'results' => $filtered_threads);
    wp_send_json($ajax_response, 200);
}

function deleteMessageThread()
{

    if (!is_user_logged_in()) {
        $ajax_response = array('success' => false, 'reason' => 'Please provide user auth.');
        wp_send_json($ajax_response, 403);
        return;
    }

    global $wpdb, $current_user;
    wp_get_current_user();
    $userID = $current_user->ID;
    $column = '';

    $thread_id = $_POST['thread_id'];
    $sender_id = $_POST['sender_id'];
    $receiver_id = $_POST['receiver_id'];

    if (isset($thread_id) && !empty($thread_id) &&
        isset($sender_id) && !empty($sender_id) &&
        isset($receiver_id) && !empty($receiver_id) ) {

        if ($userID == $sender_id) {
            $column = 'sender_delete';
        } elseif ($userID == $receiver_id) {
            $column = 'receiver_delete';
        }


        if (!empty($column) && !empty($thread_id)) {
            $table = $wpdb->prefix . 'houzez_threads';
            $wpdb->update(
                $table,
                array($column => 1),
                array('id' => $thread_id),
                array('%d'),
                array('%d')
            );
        }

        $ajax_response = array('success' => true, 'message' => 'Thread deleted successfully!' );
        wp_send_json( $ajax_response, 200 );

    }  else {
        $ajax_response = array('success' => false, 'reason' => 'Some errors occurred! Please try again.');
        wp_send_json($ajax_response, 422);
        return;
    }
}

function startMessageThread()
{

    if (!is_user_logged_in()) {
        $ajax_response = array('success' => false, 'reason' => 'Please provide user auth.');
        wp_send_json($ajax_response, 403);
        return;
    }

    $nonce = $_POST['start_thread_form_ajax'];

    if (!wp_verify_nonce($nonce, 'property_agent_contact_nonce')) {
        $ajax_response = array('success' => false, 'reason' => 'Unverified Nonce!');
        wp_send_json($ajax_response, 401);
        return;
    }

    if (isset($_POST['property_id']) && !empty($_POST['property_id']) && 
        isset($_POST['message']) && !empty($_POST['message'])) {

        $message = $_POST['message'];
        $thread_id = apply_filters('houzez_start_thread', $_POST);
        $message_id = apply_filters('houzez_thread_message', $thread_id, $message, array());

        if ($message_id) {
            $ajax_response = array('success' => true, 'message' => 'Message sent successfully!');
            wp_send_json($ajax_response, 200);
        }

    } else {
        $ajax_response = array('success' => false, 'reason' => 'Some errors occurred! Please try again.');
        wp_send_json($ajax_response, 422);
    }
}

function getThreadMessages()
{

    if (!is_user_logged_in()) {
        $ajax_response = array('success' => false, 'reason' => 'Please provide user auth.');
        wp_send_json($ajax_response, 403);
        return;
    }

    global $wpdb, $current_user;

    $current_user_id = get_current_user_id();

    $thread_id = $_REQUEST['thread_id'];

    if (isset($thread_id) && !empty($thread_id)) {

        $sender_id = $_GET['sender_id'];
        $receiver_id = $_GET['receiver_id'];
        $sender_status = 'Offline';
        $receiver_status = 'Offline';
        $current_page = isset($_GET['page']) ? intval($_GET['page']) : 1; // Current page number, default to 1 if not set
        $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 10; // Number of threads per page, default to 10

        // Calculate the offset
        $offset = ($current_page - 1) * $per_page;

        if (isset($_GET['seen']) && $_GET['seen'] == 1) {
            houzez_update_message_status($current_user_id, $thread_id);
        }

        $table = $wpdb->prefix . 'houzez_thread_messages';
        $houzez_messages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE thread_id = %d ORDER BY id DESC LIMIT %d OFFSET %d",
                $thread_id,
                $per_page,
                $offset
            )
        );

        if (isset($sender_id) && !empty($sender_id) && houzez_is_user_online($sender_id)) {
            $sender_status = 'Online';
        }

        if (isset($receiver_id) && !empty($receiver_id) && houzez_is_user_online($receiver_id)) {
            $receiver_status = 'Online';
        }

        $ajax_response = array(
            'success' => true,
            'sender_status' => $sender_status,
            'receiver_status' => $receiver_status,
            'results' => $houzez_messages
        );

        wp_send_json($ajax_response, 200);

    } else {
        $ajax_response = array('success' => false, 'reason' => 'Please provide the Thread id!');
        wp_send_json($ajax_response, 422);
        return;
    }
}

function sendMessage()
{

    if (!is_user_logged_in()) {
        $ajax_response = array('success' => false, 'reason' => 'Please provide user auth.');
        wp_send_json($ajax_response, 403);
        return;
    }

    $nonce = $_POST['start_thread_message_form_ajax'];

    if (!wp_verify_nonce($nonce, 'start-thread-message-form-nonce')) {
        $ajax_response = array('success' => false, 'reason' => 'Unverified Nonce!');
        wp_send_json($ajax_response, 401);
        return;
    }

    if (isset($_POST['thread_id']) && !empty($_POST['thread_id']) && isset($_POST['message']) && !empty($_POST['message'])) {

        $thread_id = $_POST['thread_id'];
        $message = $_POST['message'];

        $message_id = apply_filters('houzez_thread_message', $thread_id, $message, array());

        if ($message_id) {
            $ajax_response = array('success' => true, 'message' => 'The message has been sent');
            wp_send_json($ajax_response, 200);
        }

    } else {
        $ajax_response = array('success' => false, 'reason' => 'Some errors occurred! Please try again.');
        wp_send_json($ajax_response, 422);
    }

}