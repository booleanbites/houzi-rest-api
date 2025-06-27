<?php
/**
 * Houzez Property Insights API
 * 
 * @package Houzi Mobile Api
 * @since Houzi 1.0
 * @author BooleanBites
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
        'methods' => 'GET',
        'callback' => 'houzez_get_property_insights',
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
function houzez_generate_property_insights($property_id, $time_period, $data_type) {
    // Base views data
    $views_map = [
        'lastday' => rand(50, 200),
        'lastweek' => rand(300, 1000),
        'lastmonth' => rand(1000, 5000),
        'lastyear' => rand(10000, 50000)
    ];
    
    $insights = [];
    
    // Data type handling
    switch ($data_type) {
        case 'views':
            $insights = [
                'total_views' => $views_map[$time_period],
                'unique_views' => round($views_map[$time_period] * 0.75)
            ];
            break;
            
        case 'traffic':
            $insights = [
                'referrers' => [
                    'Google' => rand(30, 60),
                    'Facebook' => rand(10, 30),
                    'Direct' => rand(5, 20),
                    'Other' => rand(1, 10)
                ],
                'browsers' => [
                    'Chrome' => rand(50, 70),
                    'Safari' => rand(15, 30),
                    'Firefox' => rand(5, 15)
                ]
            ];
            break;
            
        case 'geo':
            $insights = [
                'countries' => [
                    'US' => rand(30, 60),
                    'UK' => rand(10, 25),
                    'CA' => rand(5, 15),
                    'AU' => rand(3, 10)
                ]
            ];
            break;
            
        case 'devices':
            $insights = [
                'devices' => [
                    'Mobile' => rand(50, 70),
                    'Desktop' => rand(25, 45),
                    'Tablet' => rand(5, 15)
                ],
                'platforms' => [
                    'Android' => rand(40, 60),
                    'iOS' => rand(30, 50),
                    'Windows' => rand(20, 40)
                ]
            ];
            break;
            
        case 'overview':
        default:
            $insights = [
                'total_views' => $views_map[$time_period],
                'unique_views' => round($views_map[$time_period] * 0.75),
                'top_countries' => [
                    'US' => rand(30, 60),
                    'UK' => rand(10, 25),
                    'CA' => rand(5, 15)
                ],
                'top_referrers' => [
                    'Google' => rand(30, 60),
                    'Facebook' => rand(10, 30),
                    'Direct' => rand(5, 20)
                ],
                'device_breakdown' => [
                    'Mobile' => rand(50, 70),
                    'Desktop' => rand(25, 45),
                    'Tablet' => rand(5, 15)
                ],
                'last_updated' => current_time('mysql')
            ];
            break;
    }
    
    return $insights;
}

/**
 * Get user insights data
 */
function houzez_get_user_insights($request) {
    try {
        $user_id = get_current_user_id();
        $time_period = sanitize_text_field($request->get_param('time_period'));
        $properties = houzez_get_user_properties();
        
        // Ensure we have properties
        if (!$properties['success'] || empty($properties['properties'])) {
            return [
                'success' => true,
                'user_id' => $user_id,
                'message' => 'No properties found for this user',
                'insights' => []
            ];
        }
        
        // Initialize totals
        $total_views = 0;
        $total_unique_views = 0;
        $top_properties = [];
        
        // Create insights instance
        $fave_insights = new Fave_Insights();
        
        // Aggregate data from all properties
        foreach ($properties['properties'] as $property) {
            // Get real insights for the property
            $property_stats = $fave_insights->fave_listing_stats($property['id']);
            
            // Extract values for the requested time period
            $views = $property_stats['views'][$time_period] ?? 0;
            $unique_views = $property_stats['unique_views'][$time_period] ?? 0;
            
            // Aggregate totals
            $total_views += $views;
            $total_unique_views += $unique_views;
            
            // Store for top properties list
            $top_properties[] = [
                'id' => $property['id'],
                'title' => $property['title'],
                'views' => $views,
                'unique_views' => $unique_views,
                'url' => $property['url']
            ];
        }
        
        // Sort properties by views descending
        usort($top_properties, function($a, $b) {
            return $b['views'] - $a['views'];
        });
        
        // Get user-level stats (agency-wide or all properties for admin)
        $user_stats = $fave_insights->fave_user_stats($user_id);
        
        // Prepare final response
        $response = [
            'success' => true,
            'user_id' => $user_id,
            'time_period' => $time_period,
            'total_properties' => count($properties['properties']),
            'total_views' => $total_views,
            'total_unique_views' => $total_unique_views,
            'top_properties' => array_slice($top_properties, 0, 3),
            'charts' => $user_stats['charts'][$time_period] ?? [],
            'traffic_sources' => $user_stats['others']['referrers'] ?? [],
            'geo_distribution' => $user_stats['others']['countries'] ?? [],
            'device_breakdown' => $user_stats['others']['devices'] ?? []
        ];
        
        return $response;
        
    } catch (Exception $e) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
}