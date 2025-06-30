<?php
/**
 * Houzez Property Insights API
 * 
 * @package Houzi Mobile API
 * @author BooleanBites
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function() {
    // 1. System test endpoint
    register_rest_route('houzez-insights/v1', '/system-check', [
        'methods' => 'GET',
        'callback' => 'houzez_insights_system_check',
        'permission_callback' => '__return_true'
    ]);
    
    // 2. User properties endpoint
    register_rest_route('houzez-insights/v1', '/user-properties', [
        'methods' => 'GET',
        'callback' => 'houzez_get_user_properties',
        'permission_callback' => 'is_user_logged_in'
    ]);
    
    // 3. Property insights endpoint
    register_rest_route('houzez-insights/v1', '/property-insights/(?P<id>\d+)', [
        'methods'  => 'GET',
        'callback' => 'houzez_get_real_property_insights',
        'permission_callback' => 'houzez_validate_property_access',
        'args' => [
            'id' => [
                'validate_callback' => function($param) {
                    return is_numeric($param);
                },
                'required' => true
            ],
            'time_period' => [
                'default' => 'lastmonth',
                'validate_callback' => function($param) {
                    $allowed = ['lastday', 'lastweek', 'lastmonth', 'lastyear'];
                    return in_array($param, $allowed);
                }
            ],
            'data_type' => [
                'default' => 'overview',
                'validate_callback' => function($param) {
                    $allowed = ['overview', 'views', 'traffic', 'geo', 'devices'];
                    return in_array($param, $allowed);
                }
            ]
        ]
    ]);

    
    // 4. User insights endpoint
    register_rest_route('houzez-insights/v1', '/user-insights', [
    'methods' => 'GET',
    'callback' => 'houzez_get_user_insights',
    'permission_callback' => 'is_user_logged_in',
    'args' => [
        'time_period' => [
            'default' => 'lastmonth',
            'validate_callback' => function($param) {
                $allowed = ['lastday', 'lastweek', 'lastmonth', 'lastyear'];
                return in_array($param, $allowed);
            }
        ],
        'property_id' => [
            'validate_callback' => function($param) {
                return empty($param) || is_numeric($param);
            },
            'sanitize_callback' => 'absint'
        ]
    ]
]);

});

/**
 * System diagnostic check
 */
function houzez_insights_system_check() {
    return [
        'success' => true,
        'system' => [
            'php_version' => phpversion(),
            'wordpress_version' => get_bloginfo('version'),
            'rest_api_enabled' => true,
            'permalinks' => get_option('permalink_structure') ?: 'Plain (needs configuration)',
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
            'api_version' => '1.0.3',
            'timestamp' => current_time('mysql')
        ],
        'endpoints' => [
            '/user-properties',
            '/property-insights/{id}',
            '/user-insights'
        ],
        'instructions' => 'Flush permalinks if any endpoint returns 404'
    ];
}

/**
 * Get properties accessible to current user
 */
function houzez_get_user_properties() {
    try {
        $user_id = get_current_user_id();
        $properties = [];
        
        // Admins get all properties
        if (current_user_can('edit_others_posts')) {
            $args = [
                'post_type' => 'property',
                'posts_per_page' => 10,
                'post_status' => 'publish',
                'fields' => 'ids'
            ];
            $property_ids = get_posts($args);
        } 
        // Regular users get their own properties
        else {
            $args = [
                'post_type' => 'property',
                'author' => $user_id,
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'fields' => 'ids'
            ];
            $property_ids = get_posts($args);
        }
        
        foreach ($property_ids as $pid) {
            $properties[] = [
                'id' => $pid,
                'title' => get_the_title($pid) ?: 'Property #' . $pid,
                'url' => get_permalink($pid),
                'status' => get_post_status($pid),
                'last_updated' => get_the_modified_date('', $pid),
                'thumbnail' => get_the_post_thumbnail_url($pid, 'thumbnail') ?: ''
            ];
        }
        
        return [
            'success' => true,
            'user_id' => $user_id,
            'count' => count($properties),
            'properties' => $properties
        ];
        
    } catch (Exception $e) {
        return new WP_REST_Response([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
 * Validate property access
 */
function houzez_validate_property_access() {
    // Always allow if user can edit others posts
    if (current_user_can('edit_others_posts')) {
        return true;
    }
    
    // Require authentication
    if (!is_user_logged_in()) {
        return new WP_Error(
            'rest_not_logged_in',
            __('You are not currently logged in.'),
            ['status' => 401]
        );
    }
    
    return true;
}

/**
 * Get property insights data
 */
function houzez_get_property_insights($request) {
    try {
        $params = $request->get_params();
        $property_id = (int) $params['id'];
        $time_period = sanitize_text_field($params['time_period']);
        $data_type = sanitize_text_field($params['data_type']);
        
        // Verify property exists
        $post = get_post($property_id);
        if (!$post || $post->post_type !== 'property') {
            return new WP_Error(
                'invalid_property',
                __('Property not found or is not published'),
                ['status' => 404]
            );
        }
        
        // Prepare response structure
        $response = [
            'success' => true,
            'property' => [
                'id' => $property_id,
                'title' => $post->post_title,
                'status' => $post->post_status,
                'url' => get_permalink($property_id),
                'author_id' => $post->post_author
            ],
            'time_period' => $time_period,
            'data_type' => $data_type,
            'insights' => []
        ];
        
        // Generate insights data
        $response['insights'] = houzez_generate_property_insights($property_id, $time_period, $data_type);
        
        return $response;
        
    } catch (Exception $e) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Generate property insights data
 */
function houzez_get_real_property_insights($request) {
    $property_id = $request->get_param('id');
    $time_period = $request->get_param('time_period');
    $data_type = $request->get_param('data_type');
    
    // Initialize insights system
    $fave_insights = new Fave_Insights();
    
    // Get all available stats
    $stats = $fave_insights->fave_listing_stats($property_id);
    
    // Handle lastyear views differently (sum chart data)
    $lastyear_views = 0;
    $lastyear_unique_views = 0;
    if ($time_period === 'lastyear' && isset($stats['charts']['lastyear'])) {
        foreach ($stats['charts']['lastyear'] as $data_point) {
            $lastyear_views += $data_point['views'];
            $lastyear_unique_views += $data_point['unique_views'];
        }
    }
    
    // Process based on data type
    switch ($data_type) {
        case 'views':
            return [
                'total_views' => houzez_get_period_views($stats, $time_period, $lastyear_views),
                'unique_views' => houzez_get_period_unique_views($stats, $time_period, $lastyear_unique_views)
            ];
            
        case 'traffic':
            return [
                'referrers' => $stats['others']['referrers'] ?? [],
                'browsers' => $stats['others']['browsers'] ?? []
            ];
            
        case 'geo':
            return [
                'countries' => $stats['others']['countries'] ?? []
            ];
            
        case 'devices':
            return [
                'devices' => $stats['others']['devices'] ?? [],
                'platforms' => $stats['others']['platforms'] ?? []
            ];
            
        case 'overview':
        default:
            return [
                'total_views' => houzez_get_period_views($stats, $time_period, $lastyear_views),
                'unique_views' => houzez_get_period_unique_views($stats, $time_period, $lastyear_unique_views),
                'top_countries' => $stats['others']['countries'] ?? [],
                'top_referrers' => $stats['others']['referrers'] ?? [],
                'device_breakdown' => $stats['others']['devices'] ?? [],
                'last_updated' => current_time('mysql')
            ];
    }
}

/**
 * Helper to get views for a specific time period
 */
function houzez_get_period_views($stats, $time_period, $lastyear_value = 0) {
    $period_map = [
        'lastday' => 'lastday',
        'lastweek' => 'lastweek',
        'lastmonth' => 'lastmonth',
        'lastyear' => $lastyear_value
    ];
    
    // Handle normal periods
    if (is_string($period_map[$time_period])) {
        return $stats['views'][$period_map[$time_period]] ?? 0;
    }
    
    // Handle computed lastyear value
    return $period_map[$time_period];
}

/**
 * Helper to get unique views for a specific time period
 */
function houzez_get_period_unique_views($stats, $time_period, $lastyear_value = 0) {
    $period_map = [
        'lastday' => 'lastday',
        'lastweek' => 'lastweek',
        'lastmonth' => 'lastmonth',
        'lastyear' => $lastyear_value
    ];
    
    // Handle normal periods
    if (is_string($period_map[$time_period])) {
        return $stats['unique_views'][$period_map[$time_period]] ?? 0;
    }
    
    // Handle computed lastyear value
    return $period_map[$time_period];
}


/**
 * Get user insights data
 */
function houzez_get_user_insights($request) {
    try {
        $user_id = get_current_user_id();
        $time_period = sanitize_text_field($request->get_param('time_period'));
        $property_id = $request->get_param('property_id');
        
        // Get user properties and validate property ID if provided
        $properties = houzez_get_user_properties();
        $is_single_property = !empty($property_id);
        
        if ($is_single_property) {
            // Validate requested property belongs to user
            $property_exists = false;
            foreach ($properties['properties'] as $property) {
                if ($property['id'] == $property_id) {
                    $property_exists = true;
                    break;
                }
            }
            
            if (!$property_exists) {
                return new WP_REST_Response([
                    'success' => false,
                    'error' => 'Invalid property ID or property not owned by user'
                ], 403);
            }
        }
        
        // Initialize insights and stats
        $fave_insights = new Fave_Insights();
        $total_views = 0;
        $total_unique_views = 0;
        $top_properties = [];
        
        if ($is_single_property) {
            // Process single property
            $property_stats = $fave_insights->fave_listing_stats($property_id);
            
            $views = $property_stats['views'][$time_period] ?? 0;
            $unique_views = $property_stats['unique_views'][$time_period] ?? 0;
            
            $total_views = $views;
            $total_unique_views = $unique_views;
            
            $top_properties[] = [
                'id' => $property_id,
                'title' => get_the_title($property_id),
                'views' => $views,
                'unique_views' => $unique_views,
                'url' => get_permalink($property_id)
            ];
            
            // Get detailed stats for single property
            $charts = $property_stats['charts'][$time_period] ?? [];
            $traffic_sources = $property_stats['others']['referrers'] ?? [];
            $geo_distribution = $property_stats['others']['countries'] ?? [];
            $device_breakdown = $property_stats['others']['devices'] ?? [];
            
            $total_properties = 1;
        } else {
            // Process all properties
            if (!$properties['success'] || empty($properties['properties'])) {
                return [
                    'success' => true,
                    'user_id' => $user_id,
                    'message' => 'No properties found for this user',
                    'insights' => []
                ];
            }
            
            foreach ($properties['properties'] as $property) {
                $property_stats = $fave_insights->fave_listing_stats($property['id']);
                
                $views = $property_stats['views'][$time_period] ?? 0;
                $unique_views = $property_stats['unique_views'][$time_period] ?? 0;
                
                $total_views += $views;
                $total_unique_views += $unique_views;
                
                $top_properties[] = [
                    'id' => $property['id'],
                    'title' => $property['title'],
                    'views' => $views,
                    'unique_views' => $unique_views,
                    'url' => $property['url']
                ];
            }
            
            // Sort and get top 3 properties
            usort($top_properties, function($a, $b) {
                return $b['views'] - $a['views'];
            });
            $top_properties = array_slice($top_properties, 0, 3);
            
            // Get user-level aggregated stats
            $user_stats = $fave_insights->fave_user_stats($user_id);
            $charts = $user_stats['charts'][$time_period] ?? [];
            $traffic_sources = $user_stats['others']['referrers'] ?? [];
            $geo_distribution = $user_stats['others']['countries'] ?? [];
            $device_breakdown = $user_stats['others']['devices'] ?? [];
            
            $total_properties = count($properties['properties']);
        }
        
        // Prepare final response
        $response = [
            'success' => true,
            'user_id' => $user_id,
            'time_period' => $time_period,
            'total_properties' => $total_properties,
            'total_views' => $total_views,
            'total_unique_views' => $total_unique_views,
            'top_properties' => $top_properties,
            'charts' => $charts,
            'traffic_sources' => $traffic_sources,
            'geo_distribution' => $geo_distribution,
            'device_breakdown' => $device_breakdown
        ];
        
        return $response;
        
    } catch (Exception $e) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
}
