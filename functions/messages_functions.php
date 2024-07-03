<?php

//-----------------------------Lightspeed exclude URLs-------------------------------------
// By default all POST URLs aren't cached
add_action('litespeed_init', function () {

    //these URLs need to be excluded from lightspeed caches
    $exclude_url_list = array(
        "message_threads",
        "delete_message_thread",
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
    $user_status = 'Offline';
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
            }

            $thread_author = $thread->sender_id;
            $thread_author_first_name = get_the_author_meta('first_name', $thread_author);
            $thread_author_last_name = get_the_author_meta('last_name', $thread_author);
            $thread_author_display_name = get_the_author_meta('display_name', $thread_author);
            if (!empty($thread_author_first_name) && !empty($thread_author_last_name)) {
                $thread_author_display_name = $thread_author_first_name . ' ' . $thread_author_last_name;
            }

            $user_custom_picture = get_the_author_meta('fave_author_custom_picture', $thread_author);

            if (empty($user_custom_picture)) {
                $user_custom_picture = get_template_directory_uri() . '/img/profile-avatar.png';
            }

            if (houzez_is_user_online($thread_author)) {
                $user_status = 'Online';
            }

            $temp_thread["property_id"] = $thread->property_id;
            $temp_thread["property_title"] = get_post_field('post_title', $thread->property_id);
            $temp_thread["thread_author_id"] = $thread_author;
            $temp_thread["thread_author_first_name"] = $thread_author_first_name;
            $temp_thread["thread_author_last_name"] = $thread_author_last_name;
            $temp_thread["thread_author_display_name"] = $thread_author_display_name;
            $temp_thread["user_custom_picture"] = $user_custom_picture;
            $temp_thread["user_status"] = $user_status;
            $temp_thread["time"] = $thread->time;
            $temp_thread["sender_id"] = $thread->sender_id;
            $temp_thread["receiver_id"] = $thread->receiver_id;
            $temp_thread["seen"] = $thread->seen;
            $temp_thread["receiver_delete"] = $thread->receiver_delete;
            $temp_thread["sender_delete"] = $thread->sender_delete;

            $filtered_threads[] = $temp_thread;
        }
    }

    wp_send_json(
        array(
            'success' => true,
            'results' => $filtered_threads,
        ), 200);
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

        $ajax_response = array('success' => true, 'msg' => 'Thread deleted successfully!' );
        wp_send_json( $ajax_response, 200 );

    }  else {
        $ajax_response = array('success' => false, 'reason' => 'Please provide the required data correctly!');
        wp_send_json($ajax_response, 422);
        return;
    }
}