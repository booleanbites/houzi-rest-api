<?php

class UserNotification {

    // Custom post type slug
    const POST_TYPE = 'houzi_notification';

    // Constructor to initialize hooks
    public function __construct() {
        add_action('init', [$this, 'register_custom_post_type']);

        add_action( 'rest_api_init', function () {
			register_rest_route( 'houzez-mobile-api/v1', '/all-notifications', array(
				'methods' => 'POST',
				'callback' => array( $this, 'get_user_notifications'),
				'permission_callback' => '__return_true'
			));

            register_rest_route( 'houzez-mobile-api/v1', '/check-notifications', array(
				'methods' => 'POST',
				'callback' => array( $this, 'check_for_new_notifications'),
				'permission_callback' => '__return_true'
			));
		});

        add_action('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'set_custom_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'custom_column'], 10, 2);
    }
    public function get_user_notifications() {
        if (! is_user_logged_in() ) {
            $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
            wp_send_json($ajax_response, 403);
            return; 
        }
        $posts_per_page   =  20;
        $paged = 1;
    
        if( isset( $_GET['per_page'] ) ) {
            $posts_per_page = $_GET['per_page'];
        }
        if( isset( $_GET['page'] ) ) {
            $paged = $_GET['page'];
        }

        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;

        $notifications = $this->get_notifications_for_email($user_email, $paged, $posts_per_page);
        $this->save_last_checked_notification_time_for_current_user();
        wp_send_json(
            array(
                'success' => true ,
                'result' => $notifications['notifications'],
                'total' => $notifications['total'],
            ),
            200
        );

	}
    public function save_last_checked_notification_time_for_current_user() {
        if (! is_user_logged_in() ) {
            $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
            wp_send_json($ajax_response, 403);
            return; 
        }
        $user_id = get_current_user_id();
        $current_time = current_time('timestamp');
        update_user_meta( $user_id, 'last_checked_notification_time', $current_time );
    }
    public function check_for_new_notifications() {
        $notif_data = $this->get_user_new_notifications();
        $array = [
            'success' => true ,
        ];

        // Merge arrays
        $merged_array = array_merge($array, $notif_data);
        wp_send_json(
            $merged_array,
            200
        );
    }
    public function get_user_new_notifications() {
        if (! is_user_logged_in() ) {
            $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
            wp_send_json($ajax_response, 403);
            return; 
        }
        $user_id = get_current_user_id();
        $last_checked_time = get_user_meta( $user_id, 'last_checked_notification_time', true );

        // Check if last_checked_time exists
        if ( ! $last_checked_time ) {
            // Consider all posts as new if no last checked time exists (default behavior)
            wp_send_json(
                array(
                    'success' => true ,
                    'has_notification' => true,
                    'last_checked_time' => $last_checked_time,
                ),
                200
            );
        }
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
        // Use WP_Query to get notifications for the user
        $args = [
            'post_type' => self::POST_TYPE,
            'meta_key' => 'user_email',
            'meta_value' => $user_email,
            'date_query' => [
                [
                    'after' => date('Y-m-d H:i:s', $last_checked_time),
                    'inclusive' => false,
                ],
            ],
        ];
        
        $query = new WP_Query( $args );
        $have_posts = $query->have_posts();
        $post_count = $query->found_posts;
        return array(
            'has_notification' => $have_posts,
            'num_notification' => $post_count,
            'last_checked_notification' => gmdate('Y-m-d H:i:s', $last_checked_time)
        );
    }
    function time_ago( $timestamp ) {
        $time_diff = current_time('timestamp') - $timestamp;
        
        if ( $time_diff < 1 ) { // Handle case where time difference is less than a second
            return 'a moment ago';
        }
        
        $seconds = round($time_diff % 60);
        $minutes = round($time_diff / 60 % 60);
        $hours = round($time_diff / 3600 % 24);
        $days = round($time_diff / 86400 % 7);
        $weeks = round($time_diff / 604800 % 52);
        $years = floor($time_diff / 31536000); // Use floor for whole years
        
        $text = '';
        if ( $years > 0 ) {
            $text .= sprintf( _n( '%s year', '%s years', $years ), $years ) . ' ';
        }
        if ( $weeks > 0 ) {
            $text .= sprintf( _n( '%s week', '%s weeks', $weeks ), $weeks ) . ' ';
        }
        if ( $days > 0 ) {
            $text .= sprintf( _n( '%s day', '%s days', $days ), $days ) . ' ';
        }
        if ( $hours > 0 ) {
            $text .= sprintf( _n( '%s hour', '%s hours', $hours ), $hours ) . ' ';
        }
        if ( $minutes > 0 ) {
            $text .= sprintf( _n( '%s minute', '%s minutes', $minutes ), $minutes ) . ' ';
        }
        if ( $seconds > 0 ) {
            $text .= sprintf( _n( '%s second', '%s seconds', $seconds ), $seconds ) . ' ';
        }
        
        $text = trim( $text ); // Remove trailing spaces
        return $text . ' ago';
    }  
    // Register the custom post type
    public function register_custom_post_type() {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Notifications'),
                'singular_name' => __('Notification'),
            ],
            'public' => false,
            'has_archive' => false,
            'rewrite' => false,
            'supports' => ['title', 'editor'],
            'show_ui' => true,
            'menu_icon' => 'dashicons-bell',
        ]);
    }

    // Create a notification for a user
    public function create_notification($user_email, $title, $description, $type, $extra_data = []) {
        $notification_id = wp_insert_post([
            'post_title' => $title,
            'post_content' => $description,
            'post_status' => 'publish',
            'post_type' => self::POST_TYPE,
            'meta_input' => [
                'user_email' => $user_email,
                'notification_type' => $type,
                'extra_data' => maybe_serialize($extra_data),
            ],
        ]);

        return $notification_id;
    }

    // Update a notification
    public function update_notification($notification_id, $title, $description, $type, $extra_data = []) {
        $updated = wp_update_post([
            'ID' => $notification_id,
            'post_title' => $title,
            'post_content' => $description,
            'meta_input' => [
                'notification_type' => $type,
                'extra_data' => maybe_serialize($extra_data),
            ],
        ]);

        return $updated;
    }

    // Delete a notification
    public function delete_notification($notification_id) {
        $deleted = wp_delete_post($notification_id, true);
        return $deleted;
    }

    // Set custom columns for the post type list table
    public function set_custom_columns($columns) {
        $columns['title'] = __('Title');
        $columns['description'] = __('Description');
        $columns['notification_type'] = __('Type');
        $columns['user_email'] = __('User Email');
        $columns['date'] = __('Date');
        return $columns;
    }

    // Render custom columns content
    public function custom_column($column, $post_id) {
        switch ($column) {
            case 'description':
                echo esc_html(get_post_field('post_content', $post_id));
                break;
            case 'notification_type':
                echo esc_html(get_post_meta($post_id, 'notification_type', true));
                break;
            case 'user_email':
                echo esc_html(get_post_meta($post_id, 'user_email', true));
                break;
        }
    }

    // Get notifications for a user with pagination
    public function get_notifications_for_email($user_email, $paged = 1, $posts_per_page = 20) {
        $args = [
            'post_type' => self::POST_TYPE,
            'meta_query' => [
                [
                    'key' => 'user_email',
                    'value' => $user_email,
                    'compare' => '='
                ]
            ],
            'paged' => $paged,
            'posts_per_page' => $posts_per_page,
        ];

        $query = new WP_Query($args);

        $notifications = [];
        foreach ($query->posts as $post) {
            $notifications[] = [
                'ID' => $post->ID,
                'title' => $post->post_title,
                'description' => $post->post_content,
                'type' => get_post_meta($post->ID, 'notification_type', true),
                'extra_data' => maybe_unserialize(get_post_meta($post->ID, 'extra_data', true)),
                'user_email' => get_post_meta($post->ID, 'user_email', true),
                'date' => $post->post_date,
            ];
        }

        return [
            'notifications' => $notifications,
            'total' => $query->found_posts,
            'max_num_pages' => $query->max_num_pages
        ];
    }
    // Get notifications with pagination
    public function get_notifications($paged = 1, $posts_per_page = 10) {
        $args = [
            'post_type' => self::POST_TYPE,
            'paged' => $paged,
            'posts_per_page' => $posts_per_page,
        ];

        $query = new WP_Query($args);

        $notifications = [];
        foreach ($query->posts as $post) {
            $notifications[] = [
                'ID' => $post->ID,
                'title' => $post->post_title,
                'description' => $post->post_content,
                'type' => get_post_meta($post->ID, 'notification_type', true),
                'extra_data' => maybe_unserialize(get_post_meta($post->ID, 'extra_data', true)),
                'user_email' => get_post_meta($post->ID, 'user_email', true),
                'date' => $post->post_date,
            ];
        }

        return [
            'notifications' => $notifications,
            'total' => $query->found_posts,
            'max_num_pages' => $query->max_num_pages
        ];
    }
}
// Initialize the UserNotification class
$user_notification = new UserNotification();