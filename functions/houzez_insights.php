<?php
/**
 * Houzez Property Insights API
 * 
 * @package Houzi
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
    
    register_rest_route('houzez-insights/v1', '/user-properties', [
        'methods' => 'GET',
        'callback' => 'houzez_get_user_properties',
        'permission_callback' => 'is_user_logged_in'
    ]);
    
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

    
register_rest_route('houzez-insights/v1', '/user-insights', [
    'methods' => 'GET',
    'callback' => 'houzez_get_user_insights',
    'permission_callback' => 'is_user_logged_in',
    'args' => [
        'time_period' => [
            'default' => 'lastday',
            'validate_callback' => function($param) {
                $allowed = ['lastday', 'lastweek', 'lastmonth', 'lasthalfyear', 'lastyear'];
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

/// Depricated But Still available for backward compatibility 

/**
 * Get properties accessible to current user
 */
// function houzez_get_user_properties() {
//     try {
//         $user_id = get_current_user_id();
//         $user = get_userdata($user_id);
//         $properties = [];
        
//         if (current_user_can('edit_others_posts')) {
//             $args = [
//                 'post_type' => 'property',
//                 'posts_per_page' => -1,
//                 'post_status' => 'publish',
//                 'fields' => 'ids'
//             ];
//             $property_ids = get_posts($args);
//         } 
//         elseif (in_array('houzez_agency', (array)$user->roles)) {
//             $args_own = [
//                 'post_type' => 'property',
//                 'author' => $user_id,
//                 'posts_per_page' => -1,
//                 'post_status' => 'publish',
//                 'fields' => 'ids'
//             ];
            
//             $agent_ids = houzez_get_agency_agents($user_id);
//             $args_agents = [
//                 'post_type' => 'property',
//                 'author__in' => $agent_ids,
//                 'posts_per_page' => -1,
//                 'post_status' => 'publish',
//                 'fields' => 'ids'
//             ];
            
//             $own_ids = get_posts($args_own);
//             $agent_ids = $agent_ids ? get_posts($args_agents) : [];
//             $property_ids = array_unique(array_merge($own_ids, $agent_ids));
//         }
//         elseif (in_array('houzez_agent', (array)$user->roles)) {
//             $agency_id = get_user_meta($user_id, 'fave_agent_agency', true);
            
//             if ($agency_id) {
//                 $args_agency = [
//                     'post_type' => 'property',
//                     'author' => $agency_id,
//                     'posts_per_page' => -1,
//                     'post_status' => 'publish',
//                     'fields' => 'ids'
//                 ];
                
//                 $agent_ids = houzez_get_agency_agents($agency_id);
//                 $args_agents = [
//                     'post_type' => 'property',
//                     'author__in' => $agent_ids,
//                     'posts_per_page' => -1,
//                     'post_status' => 'publish',
//                     'fields' => 'ids'
//                 ];
                
//                 $agency_ids = get_posts($args_agency);
//                 $agent_ids = $agent_ids ? get_posts($args_agents) : [];
//                 $property_ids = array_unique(array_merge($agency_ids, $agent_ids));
//             } else {
//                 $args = [
//                     'post_type' => 'property',
//                     'author' => $user_id,
//                     'posts_per_page' => -1,
//                     'post_status' => 'publish',
//                     'fields' => 'ids'
//                 ];
//                 $property_ids = get_posts($args);
//             }
//         }
//         else {
//             $args = [
//                 'post_type' => 'property',
//                 'author' => $user_id,
//                 'posts_per_page' => -1,
//                 'post_status' => 'publish',
//                 'fields' => 'ids'
//             ];
//             $property_ids = get_posts($args);
//         }
        
//         foreach ($property_ids as $pid) {
//             $properties[] = [
//                 'id' => $pid,
//                 'title' => get_the_title($pid) ?: 'Property #' . $pid,
//                 'url' => get_permalink($pid),
//                 'status' => get_post_status($pid),
//                 'last_updated' => get_the_modified_date('', $pid),
//                 'thumbnail' => get_the_post_thumbnail_url($pid, 'thumbnail') ?: ''
//             ];
//         }
        
//         return [
//             'success' => true,
//             'user_id' => $user_id,
//             'count' => count($properties),
//             'properties' => $properties
//         ];
        
//     } catch (Exception $e) {
//         return new WP_REST_Response([
//             'success' => false,
//             'error' => $e->getMessage()
//         ], 500);
//     }
// }

function houzez_get_user_properties(WP_REST_Request $request) {
    try {
        $page     = max(1, intval($request->get_param('page')) ?: 1);
        $per_page = min(100, intval($request->get_param('per_page')) ?: 20); 
        // limit per_page to avoid abuse, default 20

        $user_id = get_current_user_id();
        $user    = get_userdata($user_id);
        $properties = [];

        // Base args
        $base_args = [
            'post_type'      => 'property',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'post_status'    => 'publish',
            'fields'         => 'ids'
        ];

        // --- ROLE LOGIC ---
        if (current_user_can('edit_others_posts')) {
            $args = $base_args;
        } 
        elseif (in_array('houzez_agency', (array)$user->roles)) {
            $agent_ids = houzez_get_agency_agents($user_id);

            $args = $base_args;
            $args['author__in'] = array_merge([$user_id], $agent_ids);
        }
        elseif (in_array('houzez_agent', (array)$user->roles)) {
            $agency_id = get_user_meta($user_id, 'fave_agent_agency', true);

            if ($agency_id) {
                $agent_ids = houzez_get_agency_agents($agency_id);

                $args = $base_args;
                $args['author__in'] = array_merge([$agency_id], $agent_ids);
            } else {
                $args = $base_args;
                $args['author'] = $user_id;
            }
        }
        else {
            $args = $base_args;
            $args['author'] = $user_id;
        }

        // Query
        $query = new WP_Query($args);

        foreach ($query->posts as $pid) {
            $properties[] = [
                'id'           => $pid,
                'title'        => get_the_title($pid) ?: 'Property #' . $pid,
                'url'          => get_permalink($pid),
                'status'       => get_post_status($pid),
                'last_updated' => get_the_modified_date('', $pid),
                'thumbnail'    => get_the_post_thumbnail_url($pid, 'thumbnail') ?: ''
            ];
        }

        return [
            'success'     => true,
            'user_id'     => $user_id,
            'count'       => $query->found_posts,   // total items
            'total_pages' => $query->max_num_pages, // total pages
            'page'        => $page,
            'per_page'    => $per_page,
            'properties'  => $properties
        ];

    } catch (Exception $e) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => $e->getMessage()
        ], 500);
    }
}


if ( ! function_exists( 'houzez_get_agency_agents' ) ) {
    function houzez_get_agency_agents($agency_id) {
        // Ensure WordPress environment is fully loaded
        if ( ! function_exists( 'get_users' ) ) {
            return [];
        }
        
        $agents = get_users([
            'meta_key' => 'fave_agent_agency',
            'meta_value' => $agency_id,
            'fields' => 'ids',
            'meta_compare' => '=', // Explicit comparison for clarity
        ]);
        
        return is_array($agents) ? $agents : [];
    }
}

/**
 * Validate property access
 */
function houzez_validate_property_access() {
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
    
    $fave_insights = new Fave_Insights();
    
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
    
    if (is_string($period_map[$time_period])) {
        return $stats['views'][$period_map[$time_period]] ?? 0;
    }
    
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
    
    if (is_string($period_map[$time_period])) {
        return $stats['unique_views'][$period_map[$time_period]] ?? 0;
    }
    
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
        
        $properties = houzez_get_user_properties($request);
        $is_single_property = !empty($property_id);
    
        /// Depricated Code
        ///
//         if ($is_single_property) {
//             // Validate requested property belongs to user
//             $property_exists = false;
//             foreach ($properties['properties'] as $property) {
//                 if ($property['id'] == $property_id) {
//                     $property_exists = true;
//                     break;
//                 }
//             }
            
//             if (!$property_exists) {
//                 return new WP_REST_Response([
//                     'success' => false,
//                     'error' => 'Invalid property ID or property not owned by user'
//                 ], 403);
//             }
//         }
//         
        if ($is_single_property) {
    if (!houzez_user_can_access_property($user_id, $property_id)) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'Invalid property ID or property not owned by user'
        ], 403);
    }
}
        
        $fave_insights = new Fave_Insights();
        
        if ($is_single_property) {
            $property_stats = $fave_insights->fave_listing_stats($property_id);
            
            $charts = $property_stats['charts'][$time_period] ?? [];
            $traffic_sources = $property_stats['others']['referrers'] ?? [];
            $top_countries = $property_stats['others']['countries'] ?? [];
            $devices = $property_stats['others']['devices'] ?? [];
            $platforms = $property_stats['others']['platforms'] ?? [];
            $browsers = $property_stats['others']['browsers'] ?? [];
            
            $views_data = $property_stats['views'] ?? [];
            $unique_views_data = $property_stats['unique_views'] ?? [];
            
            $total_properties = 1;
        } else {
            if (!$properties['success'] || empty($properties['properties'])) {
                return [
                    'success' => true,
                    'user_id' => $user_id,
                    'message' => 'No properties found for this user',
                    'insights' => []
                ];
            }
            
            // Get user-level aggregated stats
            $user_stats = $fave_insights->fave_user_stats($user_id);
            $charts = $user_stats['charts'][$time_period] ?? [];
            $traffic_sources = $user_stats['others']['referrers'] ?? [];
            $top_countries = $user_stats['others']['countries'] ?? [];
            $devices = $user_stats['others']['devices'] ?? [];
            $platforms = $user_stats['others']['platforms'] ?? [];
            $browsers = $user_stats['others']['browsers'] ?? [];
            
            $views_data = $user_stats['views'] ?? [];
            $unique_views_data = $user_stats['unique_views'] ?? [];
            
            $total_properties = count($properties['properties']);
        }
        
        // Calculate Views with percentages
        $views = [
            [
                'period' => 'Last 24 Hours',
                'count' => $views_data['lastday'] ?? 0,
                'percentage' => 0
            ],
            [
                'period' => 'Last 7 days',
                'count' => $views_data['lastweek'] ?? 0,
                'percentage' => 0
            ],
            [
                'period' => 'Last month',
                'count' => $views_data['lastmonth'] ?? 0,
                'percentage' => 0
            ]
        ];
        
        // Calculate percentages for views
        $total_views_sum = array_sum(array_column($views, 'count'));
        if ($total_views_sum > 0) {
            for ($i = 0; $i < count($views); $i++) {
                $views[$i]['percentage'] = round(($views[$i]['count'] / $total_views_sum) * 100, 2);
            }
        }
        
        // Calculate Unique Views with percentages
        $unique_views = [
            [
                'period' => 'Last 24 Hours',
                'count' => $unique_views_data['lastday'] ?? 0,
                'percentage' => 0
            ],
            [
                'period' => 'Last 7 days',
                'count' => $unique_views_data['lastweek'] ?? 0,
                'percentage' => 0
            ],
            [
                'period' => 'Last month',
                'count' => $unique_views_data['lastmonth'] ?? 0,
                'percentage' => 0
            ]
        ];
        
        // Calculate percentages for unique views
        $total_unique_views_sum = array_sum(array_column($unique_views, 'count'));
        if ($total_unique_views_sum > 0) {
            for ($i = 0; $i < count($unique_views); $i++) {
                $unique_views[$i]['percentage'] = round(($unique_views[$i]['count'] / $total_unique_views_sum) * 100, 2);
            }
        }
        
        $response = [
            'success' => true,
            'user_id' => $user_id,
            'time_period' => $time_period,
            'total_properties' => $total_properties,
            'views' => $views,
            'unique_views' => $unique_views,
            'charts' => $charts,
            'referrals' => $traffic_sources,
            'top_countries' => $top_countries,
            'devices' => $devices,
            'platforms' => $platforms,
            'browsers' => $browsers
        ];
        
        return $response;
        
    } catch (Exception $e) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
}


function houzez_user_can_access_property($user_id, $property_id) {
    // Check if user can edit others posts (admin)
    if (current_user_can('edit_others_posts')) {
        return true;
    }
    
    // Check if user is the author
    $post = get_post($property_id);
    if ($post && $post->post_author == $user_id) {
        return true;
    }
    
    
    return false;
}
