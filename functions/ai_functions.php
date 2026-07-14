<?php
/**
 * AI feature endpoints (BYOK, server-side).
 *
 * All AI calls happen here so the site owner's provider API key never ships in
 * the mobile app. Endpoints return structured data; generative output is always
 * a draft for a human to review (see the plan doc: ai-features-plugin-plan).
 *
 * @package Houzi Mobile Api
 * @since Houzi 1.5.0
 * @author Adil Soomro
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once HOUZI_REST_API_PLUGIN_PATH . 'includes/ai/providers/interface-ai-provider.php';
require_once HOUZI_REST_API_PLUGIN_PATH . 'includes/ai/providers/class-provider-openai.php';
require_once HOUZI_REST_API_PLUGIN_PATH . 'includes/ai/providers/class-provider-claude.php';
require_once HOUZI_REST_API_PLUGIN_PATH . 'includes/ai/providers/class-provider-gemini.php';
require_once HOUZI_REST_API_PLUGIN_PATH . 'includes/ai/class-ai-settings.php';
require_once HOUZI_REST_API_PLUGIN_PATH . 'includes/ai/class-ai-rate-limiter.php';
require_once HOUZI_REST_API_PLUGIN_PATH . 'includes/ai/class-ai-gateway.php';
require_once HOUZI_REST_API_PLUGIN_PATH . 'includes/ai/class-prompt-library.php';
require_once HOUZI_REST_API_PLUGIN_PATH . 'includes/ai/class-location-resolver.php';

add_action( 'rest_api_init', function () {
	register_rest_route( 'houzez-mobile-api/v1', '/ai-search', array(
		'methods'             => 'POST',
		'callback'            => 'houziAiSearch',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( 'houzez-mobile-api/v1', '/ai-describe', array(
		'methods'             => 'POST',
		'callback'            => 'houziAiDescribe',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( 'houzez-mobile-api/v1', '/ai-suggestions', array(
		'methods'             => 'POST',
		'callback'            => 'houziAiSuggestions',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( 'houzez-mobile-api/v1', '/ai-ask-listing', array(
		'methods'             => 'POST',
		'callback'            => 'houziAiAskListing',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( 'houzez-mobile-api/v1', '/ai-crm/lead-summary', array(
		'methods'             => 'POST',
		'callback'            => 'houziAiCrmLeadSummary',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( 'houzez-mobile-api/v1', '/ai-crm/rank-matches', array(
		'methods'             => 'POST',
		'callback'            => 'houziAiCrmRankMatches',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( 'houzez-mobile-api/v1', '/ai-crm/draft-email', array(
		'methods'             => 'POST',
		'callback'            => 'houziAiCrmDraftEmail',
		'permission_callback' => '__return_true',
	) );
} );

// The location term index must not go stale when the owner edits terms.
add_action( 'created_term', array( 'Houzi_AI_Location_Resolver', 'flush_index' ) );
add_action( 'edited_term', array( 'Houzi_AI_Location_Resolver', 'flush_index' ) );
add_action( 'delete_term', array( 'Houzi_AI_Location_Resolver', 'flush_index' ) );

/*
|--------------------------------------------------------------------------
| Shared helpers
|--------------------------------------------------------------------------
*/

/**
 * Uniform error response (plan §8 contract).
 */
function houzi_ai_send_error( $error_code, $message, $status, $extra = array() ) {
	$response = array_merge(
		array( 'success' => false, 'error_code' => $error_code, 'message' => $message ),
		$extra
	);
	wp_send_json( $response, $status );
}

/**
 * Common guard for every AI endpoint: feature toggle, key, app secret,
 * optional auth, rate limit. Sends the error response itself and returns
 * false when the request must not proceed.
 */
function houzi_ai_guard( $request, $feature, $require_login = false ) {
	do_action( 'litespeed_control_set_nocache', 'nocache for ai endpoints' );

	if ( ! Houzi_AI_Settings::is_enabled() || ! Houzi_AI_Settings::feature_enabled( $feature ) ) {
		houzi_ai_send_error( 'ai_disabled', 'AI is not enabled for this feature.', 403 );
		return false;
	}
	if ( '' === Houzi_AI_Settings::api_key() ) {
		houzi_ai_send_error( 'no_api_key', 'No AI API key is configured.', 403 );
		return false;
	}

	// Same app-secret handshake as create_nonce in security_utils.php.
	$saved_app_secret = function_exists( 'get_saved_app_secret' ) ? get_saved_app_secret() : '';
	if ( ! empty( $saved_app_secret ) ) {
		$app_secret = $request->get_header( 'app-secret' );
		if ( $app_secret != $saved_app_secret ) {
			houzi_ai_send_error( 'forbidden', 'App secret mismatch.', 403 );
			return false;
		}
	}

	if ( $require_login && ! is_user_logged_in() ) {
		houzi_ai_send_error( 'forbidden', 'Please provide user auth.', 403 );
		return false;
	}

	$rate = Houzi_AI_Rate_Limiter::check_and_increment();
	if ( true !== $rate ) {
		houzi_ai_send_error( 'rate_limited', 'Too many AI requests. Please try again later.', 429, $rate );
		return false;
	}

	return true;
}

/**
 * Bail out with the gateway's WP_Error mapped onto the uniform contract.
 */
function houzi_ai_send_gateway_error( $error ) {
	$code   = $error->get_error_code();
	$status = ( 'ai_timeout' === $code ) ? 504 : ( in_array( $code, array( 'ai_disabled', 'no_api_key' ), true ) ? 403 : 502 );
	houzi_ai_send_error( $code, $error->get_error_message(), $status );
}

/**
 * Conversation state: transient, 30-minute TTL, capped history.
 */
function houzi_ai_load_conversation( $conversation_id ) {
	if ( empty( $conversation_id ) || ! wp_is_uuid( $conversation_id ) ) {
		return array();
	}
	$messages = get_transient( 'houzi_ai_conv_' . $conversation_id );
	return is_array( $messages ) ? $messages : array();
}

function houzi_ai_save_conversation( $conversation_id, $messages ) {
	// Keep the last 10 turns; older context adds cost, not accuracy.
	$messages = array_slice( $messages, -10 );
	set_transient( 'houzi_ai_conv_' . $conversation_id, $messages, 30 * MINUTE_IN_SECONDS );
}

/**
 * Term slugs for small taxonomies used as tool-schema enums, cached 1 hour.
 * Very large vocabularies (>150 terms) skip the enum: the model returns free
 * values which are then validated against the full list anyway.
 */
function houzi_ai_taxonomy_slugs( $taxonomy ) {
	$cached = get_transient( 'houzi_ai_tax_enum_' . $taxonomy );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$slugs = array();
	$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true ) );
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$slugs[] = $term->slug;
		}
	}
	set_transient( 'houzi_ai_tax_enum_' . $taxonomy, $slugs, HOUR_IN_SECONDS );
	return $slugs;
}

/**
 * Whitelisted, AI-safe context for one property. Used for grounding
 * (ask-listing) and content generation (describe, CRM). Never include
 * private notes or the owner's contact meta here.
 */
function houzi_ai_property_context( $post_id, $include_description = false ) {
	$post = get_post( $post_id );
	if ( empty( $post ) || 'property' !== $post->post_type ) {
		return null;
	}

	$meta_keys = array(
		'bedrooms'      => 'fave_property_bedrooms',
		'bathrooms'     => 'fave_property_bathrooms',
		'size'          => 'fave_property_size',
		'size_unit'     => 'fave_property_size_prefix',
		'land'          => 'fave_property_land',
		'garage'        => 'fave_property_garage',
		'year_built'    => 'fave_property_year',
		'price'         => 'fave_property_price',
		'price_postfix' => 'fave_property_price_postfix',
		'address'       => 'fave_property_map_address',
	);

	$context = array(
		'id'    => $post->ID,
		'title' => get_the_title( $post ),
	);
	foreach ( $meta_keys as $label => $meta_key ) {
		$value = get_post_meta( $post->ID, $meta_key, true );
		if ( '' !== $value && null !== $value ) {
			$context[ $label ] = $value;
		}
	}

	foreach ( array( 'property_type' => 'type', 'property_status' => 'status', 'property_city' => 'city', 'property_area' => 'area', 'property_feature' => 'features', 'property_label' => 'label' ) as $taxonomy => $label ) {
		$terms = get_the_terms( $post->ID, $taxonomy );
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			$names             = wp_list_pluck( $terms, 'name' );
			$context[ $label ] = ( 'features' === $label ) ? array_values( $names ) : implode( ', ', $names );
		}
	}

	if ( $include_description ) {
		$description = wp_strip_all_tags( $post->post_content );
		if ( function_exists( 'mb_substr' ) ) {
			$description = mb_substr( $description, 0, 2500 );
		} else {
			$description = substr( $description, 0, 2500 );
		}
		$context['description'] = $description;
	}

	return $context;
}

/**
 * Multibyte-safe truncation with a graceful fallback when the mbstring
 * extension is unavailable.
 */
function houzi_ai_truncate( $text, $length ) {
	if ( function_exists( 'mb_substr' ) ) {
		return mb_substr( $text, 0, $length );
	}
	return substr( $text, 0, $length );
}

/**
 * Whitelist and bound the client-supplied property payload for /ai-describe.
 *
 * Only known listing fields survive, each value is length-capped, and array
 * fields are element-capped. Client data feeds a prompt billed to the owner's
 * BYOK key, so it must never be an open text channel (plan §6).
 */
function houzi_ai_sanitize_describe_property( $property ) {
	$allowed = array(
		'title', 'type', 'status', 'label', 'city', 'area', 'address',
		'beds', 'bedrooms', 'baths', 'bathrooms', 'size', 'size_unit',
		'land', 'garage', 'year_built', 'price', 'price_postfix', 'price_prefix',
	);

	$clean = array();
	foreach ( $allowed as $key ) {
		if ( ! isset( $property[ $key ] ) || is_array( $property[ $key ] ) ) {
			continue;
		}
		$value = sanitize_text_field( (string) $property[ $key ] );
		if ( '' !== $value ) {
			$clean[ $key ] = houzi_ai_truncate( $value, 200 );
		}
	}

	// features[] is the one legitimately-array field.
	if ( isset( $property['features'] ) && is_array( $property['features'] ) ) {
		$features = array();
		foreach ( array_slice( $property['features'], 0, 40 ) as $feature ) {
			if ( is_array( $feature ) ) {
				continue;
			}
			$feature = sanitize_text_field( (string) $feature );
			if ( '' !== $feature ) {
				$features[] = houzi_ai_truncate( $feature, 60 );
			}
		}
		if ( ! empty( $features ) ) {
			$clean['features'] = array_values( $features );
		}
	}

	return $clean;
}

/*
|--------------------------------------------------------------------------
| POST /ai-search
|--------------------------------------------------------------------------
*/
function houziAiSearch( $request ) {
	if ( ! houzi_ai_guard( $request, 'search' ) ) {
		return;
	}

	$query = isset( $_POST['query'] ) ? sanitize_text_field( $_POST['query'] ) : '';
	if ( '' === trim( $query ) ) {
		houzi_ai_send_error( 'invalid_request', 'Please provide query.', 400 );
		return;
	}
	if ( strlen( $query ) > 500 ) {
		houzi_ai_send_error( 'invalid_request', 'Query is too long (max 500 characters).', 400 );
		return;
	}

	$conversation_id = isset( $_POST['conversation_id'] ) ? sanitize_text_field( $_POST['conversation_id'] ) : '';
	$is_refinement   = wp_is_uuid( $conversation_id );

	// Cache identical first-turn queries for 5 minutes (plan §7).
	$cache_key = 'houzi_ai_search_' . md5( strtolower( trim( $query ) ) );
	if ( ! $is_refinement ) {
		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			$cached['conversation_id'] = wp_generate_uuid4();
			houzi_ai_save_conversation( $cached['conversation_id'], array(
				array( 'role' => 'user', 'content' => $query ),
				array( 'role' => 'assistant', 'content' => wp_json_encode( array( 'filters' => $cached['filters'] ) ) ),
			) );
			wp_send_json( $cached, 200 );
			return;
		}
	}

	// Small, stable taxonomies go into the tool schema as enums. Locations
	// deliberately do not (see Houzi_AI_Location_Resolver).
	$enum_or_string = function ( $taxonomy, $description ) {
		$slugs = houzi_ai_taxonomy_slugs( $taxonomy );
		$item  = array( 'type' => 'string' );
		if ( ! empty( $slugs ) && count( $slugs ) <= 150 ) {
			$item['enum'] = $slugs;
		}
		return array( 'type' => 'array', 'items' => $item, 'description' => $description );
	};

	$tool = array(
		'name'        => 'search_properties',
		'description' => 'Extract structured real-estate search filters from the user request.',
		'parameters'  => array(
			'type'       => 'object',
			'properties' => array(
				'type'        => $enum_or_string( 'property_type', 'Property type slugs.' ),
				'status'      => $enum_or_string( 'property_status', 'Listing status slugs (e.g. for-sale, for-rent).' ),
				'label'       => $enum_or_string( 'property_label', 'Listing label slugs.' ),
				'features'    => $enum_or_string( 'property_feature', 'Feature/amenity slugs.' ),
				'bedrooms'    => array( 'type' => 'integer', 'description' => 'Number of bedrooms requested.' ),
				'bathrooms'   => array( 'type' => 'integer', 'description' => 'Number of bathrooms requested.' ),
				'min_price'   => array( 'type' => 'number' ),
				'max_price'   => array( 'type' => 'number' ),
				'min_area'    => array( 'type' => 'number', 'description' => 'Minimum property size.' ),
				'max_area'    => array( 'type' => 'number', 'description' => 'Maximum property size.' ),
				'keyword'     => array( 'type' => 'string', 'description' => 'Style/quality words with no matching filter (e.g. modern, sea view).' ),
				'locations'   => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Place names mentioned (city, area, state or country). Full names, abbreviations expanded (NY -> New York). Only places the user mentioned.',
				),
				'explanation' => array( 'type' => 'string' ),
				'suggestions' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
		),
	);

	$messages   = houzi_ai_load_conversation( $conversation_id );
	$messages[] = array( 'role' => 'user', 'content' => $query );

	$system = Houzi_AI_Prompt_Library::get( 'search' );
	$result = Houzi_AI_Gateway::complete( 'search', $system, $messages, $tool );
	if ( is_wp_error( $result ) ) {
		houzi_ai_send_gateway_error( $result );
		return;
	}
	$args = $result['tool_args'];

	// --- Map tool args onto search-properties params (validated). ---
	$filters = array();

	foreach ( array( 'type' => 'property_type', 'status' => 'property_status', 'label' => 'property_label', 'features' => 'property_feature' ) as $key => $taxonomy ) {
		if ( ! empty( $args[ $key ] ) && is_array( $args[ $key ] ) ) {
			$valid = array_values( array_intersect( array_map( 'sanitize_title', $args[ $key ] ), houzi_ai_taxonomy_slugs( $taxonomy ) ) );
			if ( ! empty( $valid ) ) {
				$filters[ $key ] = $valid;
			}
		}
	}
	foreach ( array( 'bedrooms', 'bathrooms' ) as $key ) {
		if ( ! empty( $args[ $key ] ) && intval( $args[ $key ] ) > 0 ) {
			$filters[ $key ] = strval( intval( $args[ $key ] ) );
		}
	}
	foreach ( array( 'min_price', 'max_price', 'min_area', 'max_area' ) as $key ) {
		if ( isset( $args[ $key ] ) && is_numeric( $args[ $key ] ) && floatval( $args[ $key ] ) > 0 ) {
			$filters[ $key ] = strval( round( floatval( $args[ $key ] ) ) );
		}
	}
	if ( ! empty( $args['keyword'] ) && is_string( $args['keyword'] ) ) {
		$filters['keyword'] = sanitize_text_field( $args['keyword'] );
	}

	// --- Locations: resolve phrases to real term slugs (never guessed). ---
	$location = Houzi_AI_Location_Resolver::resolve( isset( $args['locations'] ) ? $args['locations'] : array() );
	foreach ( $location['resolved'] as $resolved ) {
		$filter_key = Houzi_AI_Location_Resolver::$filter_key_for_taxonomy[ $resolved['taxonomy'] ];
		if ( in_array( $filter_key, array( 'location', 'area' ), true ) ) {
			$filters[ $filter_key ]   = isset( $filters[ $filter_key ] ) ? $filters[ $filter_key ] : array();
			$filters[ $filter_key ][] = $resolved['slug'];
		} else {
			$filters[ $filter_key ] = $resolved['slug'];
		}
	}
	// Unresolved place names still help as keyword search.
	if ( ! empty( $location['unresolved'] ) && empty( $filters['keyword'] ) ) {
		$filters['keyword'] = implode( ' ', $location['unresolved'] );
	}

	$response = array(
		'success'     => true,
		'filters'     => $filters,
		'explanation' => isset( $args['explanation'] ) ? sanitize_text_field( $args['explanation'] ) : '',
		'location'    => $location,
		'suggestions' => ( isset( $args['suggestions'] ) && is_array( $args['suggestions'] ) )
			? array_slice( array_map( 'sanitize_text_field', $args['suggestions'] ), 0, 2 )
			: array(),
	);

	if ( ! $is_refinement ) {
		set_transient( $cache_key, $response, 5 * MINUTE_IN_SECONDS );
		$conversation_id = wp_generate_uuid4();
	}
	$response['conversation_id'] = $conversation_id;

	$messages[] = array( 'role' => 'assistant', 'content' => wp_json_encode( array( 'filters' => $filters ) ) );
	houzi_ai_save_conversation( $conversation_id, $messages );

	wp_send_json( $response, 200 );
}

/*
|--------------------------------------------------------------------------
| POST /ai-describe
|--------------------------------------------------------------------------
*/
function houziAiDescribe( $request ) {
	if ( ! houzi_ai_guard( $request, 'describe', true ) ) {
		return;
	}

	$listing_id = isset( $_POST['listing_id'] ) ? intval( $_POST['listing_id'] ) : 0;
	if ( $listing_id > 0 ) {
		// Trust server data over the client payload when the listing exists.
		$post = get_post( $listing_id );
		if ( empty( $post ) || 'property' !== $post->post_type ) {
			houzi_ai_send_error( 'invalid_request', 'Listing not found.', 404 );
			return;
		}
		if ( intval( $post->post_author ) !== get_current_user_id() && ! current_user_can( 'edit_post', $listing_id ) ) {
			houzi_ai_send_error( 'forbidden', 'You cannot edit this listing.', 403 );
			return;
		}
		$property = houzi_ai_property_context( $listing_id );
	} else {
		// Add-property flow: the listing doesn't exist yet, data comes from the
		// form. Still gate on listing-editing capability so a plain buyer account
		// cannot burn the owner's key as a free text generator (plan §2.2, §6).
		if ( ! current_user_can( 'edit_posts' ) ) {
			houzi_ai_send_error( 'forbidden', 'You cannot create listings.', 403 );
			return;
		}
		$property = isset( $_POST['property'] ) ? $_POST['property'] : null;
		if ( is_string( $property ) ) {
			$property = json_decode( stripslashes( $property ), true );
		}
		if ( empty( $property ) || ! is_array( $property ) ) {
			houzi_ai_send_error( 'invalid_request', 'Please provide listing_id or property data.', 400 );
			return;
		}
		// Whitelist to known listing fields and cap sizes: this is client data
		// going straight into a prompt on the owner's key, not an open channel.
		$property = houzi_ai_sanitize_describe_property( $property );
		if ( empty( $property ) ) {
			houzi_ai_send_error( 'invalid_request', 'No usable property data provided.', 400 );
			return;
		}
	}

	$tone   = isset( $_POST['tone'] ) ? sanitize_text_field( $_POST['tone'] ) : 'professional';
	$length = isset( $_POST['length'] ) ? sanitize_text_field( $_POST['length'] ) : 'medium';
	$tone   = in_array( $tone, array( 'professional', 'casual', 'luxury' ), true ) ? $tone : 'professional';
	$length = in_array( $length, array( 'short', 'medium', 'long' ), true ) ? $length : 'medium';
	$words  = array( 'short' => 80, 'medium' => 150, 'long' => 250 );

	$language = Houzi_AI_Settings::resolve_language( isset( $_POST['language'] ) ? $_POST['language'] : '' );

	$tool = array(
		'name'        => 'write_listing_description',
		'description' => 'Write the listing description for the given property data.',
		'parameters'  => array(
			'type'       => 'object',
			'properties' => array(
				'description' => array( 'type' => 'string' ),
				'seo_title'   => array( 'type' => 'string', 'description' => 'Max 60 characters.' ),
			),
			'required'   => array( 'description' ),
		),
	);

	$user_message = 'Tone: ' . $tone . '. Target length: about ' . $words[ $length ] . " words.\n"
		. 'Property data: ' . wp_json_encode( $property );

	$system = Houzi_AI_Prompt_Library::get( 'describe', array( 'language' => $language ) );
	$result = Houzi_AI_Gateway::complete(
		'describe',
		$system,
		array( array( 'role' => 'user', 'content' => $user_message ) ),
		$tool,
		array( 'max_tokens' => 1500, 'temperature' => 0.7 )
	);
	if ( is_wp_error( $result ) ) {
		houzi_ai_send_gateway_error( $result );
		return;
	}

	$args = $result['tool_args'];
	wp_send_json( array(
		'success'     => true,
		'description' => isset( $args['description'] ) ? wp_kses_post( $args['description'] ) : '',
		'seo_title'   => isset( $args['seo_title'] ) ? sanitize_text_field( $args['seo_title'] ) : '',
	), 200 );
}

/*
|--------------------------------------------------------------------------
| POST /ai-suggestions   (home "Tailored for You" taxonomy subtitles)
|--------------------------------------------------------------------------
*/

/**
 * A compact, curated set of the site's OWN taxonomy terms for the home
 * "Tailored for You" cards: the most-used statuses, types and features. The
 * names/slugs are authoritative (the site's real taxonomy) — only the subtitle
 * copy is model-generated, so this works for any white-label vocabulary.
 */
function houzi_ai_suggestion_terms() {
	$plan = array(
		array( 'taxonomy' => 'property_status',  'kind' => 'status',  'limit' => 2 ),
		array( 'taxonomy' => 'property_type',    'kind' => 'type',    'limit' => 2 ),
		array( 'taxonomy' => 'property_feature', 'kind' => 'feature', 'limit' => 2 ),
	);
	$out = array();
	foreach ( $plan as $p ) {
		$terms = get_terms( array(
			'taxonomy'   => $p['taxonomy'],
			'hide_empty' => false,
			'orderby'    => 'count',
			'order'      => 'DESC',
			'number'     => $p['limit'],
		) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			continue;
		}
		foreach ( $terms as $term ) {
			$out[] = array(
				'taxonomy' => $p['taxonomy'],
				'kind'     => $p['kind'],
				'slug'     => $term->slug,
				'name'     => $term->name,
			);
		}
	}
	return $out;
}

function houziAiSuggestions( $request ) {
	if ( ! houzi_ai_guard( $request, 'suggestions' ) ) {
		return;
	}

	$language = Houzi_AI_Settings::resolve_language( isset( $_POST['language'] ) ? $_POST['language'] : '' );

	$terms = houzi_ai_suggestion_terms();
	if ( empty( $terms ) ) {
		wp_send_json( array( 'success' => true, 'items' => array() ), 200 );
		return;
	}

	// The subtitles are site-level content (identical for every user) and stable,
	// so cache the generated set server-side, keyed by the term set + language +
	// lite model. It regenerates only when the taxonomies, language or model
	// change. This keeps cost to ~one generation per day per site even though the
	// app also caches per user per day.
	$signature   = md5( wp_json_encode( wp_list_pluck( $terms, 'slug' ) ) . '|' . $language . '|' . Houzi_AI_Settings::lite_model() );
	$cache_key   = 'houzi_ai_suggestions_' . $signature;
	$subtitles   = get_transient( $cache_key );

	if ( ! is_array( $subtitles ) ) {
		$tool = array(
			'name'        => 'write_taxonomy_subtitles',
			'description' => 'Write one short marketing subtitle for each provided taxonomy term.',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'items' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'slug'     => array( 'type' => 'string' ),
								'subtitle' => array( 'type' => 'string', 'description' => 'Max ~40 characters, no ending period.' ),
							),
							'required'   => array( 'slug', 'subtitle' ),
						),
					),
				),
				'required'   => array( 'items' ),
			),
		);

		$payload = array();
		foreach ( $terms as $t ) {
			$payload[] = array( 'slug' => $t['slug'], 'name' => $t['name'], 'kind' => $t['kind'] );
		}
		$user_message = 'Write a subtitle for each of these terms: ' . wp_json_encode( $payload );

		$system = Houzi_AI_Prompt_Library::get( 'suggestions', array( 'language' => $language ) );
		$result = Houzi_AI_Gateway::complete(
			'suggestions',
			$system,
			array( array( 'role' => 'user', 'content' => $user_message ) ),
			$tool,
			array( 'model' => Houzi_AI_Settings::lite_model(), 'max_tokens' => 700, 'temperature' => 0.6 )
		);
		if ( is_wp_error( $result ) ) {
			houzi_ai_send_gateway_error( $result );
			return;
		}

		$subtitles = array();
		$args      = isset( $result['tool_args'] ) ? $result['tool_args'] : array();
		if ( isset( $args['items'] ) && is_array( $args['items'] ) ) {
			foreach ( $args['items'] as $row ) {
				if ( isset( $row['slug'], $row['subtitle'] ) ) {
					$subtitles[ (string) $row['slug'] ] = sanitize_text_field( $row['subtitle'] );
				}
			}
		}
		set_transient( $cache_key, $subtitles, DAY_IN_SECONDS );
	}

	// Never trust the model for slug/name/taxonomy — only for the subtitle copy.
	// Map it back onto our authoritative term list.
	$items = array();
	foreach ( $terms as $t ) {
		$items[] = array(
			'taxonomy' => $t['taxonomy'],
			'slug'     => $t['slug'],
			'name'     => $t['name'],
			'subtitle' => isset( $subtitles[ $t['slug'] ] ) ? $subtitles[ $t['slug'] ] : '',
		);
	}

	wp_send_json( array( 'success' => true, 'items' => $items ), 200 );
}

/*
|--------------------------------------------------------------------------
| POST /ai-ask-listing
|--------------------------------------------------------------------------
*/
function houziAiAskListing( $request ) {
	if ( ! houzi_ai_guard( $request, 'ask_listing' ) ) {
		return;
	}

	$listing_id = isset( $_POST['listing_id'] ) ? intval( $_POST['listing_id'] ) : 0;
	$question   = isset( $_POST['question'] ) ? sanitize_text_field( $_POST['question'] ) : '';

	if ( $listing_id <= 0 || '' === trim( $question ) ) {
		houzi_ai_send_error( 'invalid_request', 'Please provide listing_id and question.', 400 );
		return;
	}
	if ( strlen( $question ) > 1000 ) {
		houzi_ai_send_error( 'invalid_request', 'Question is too long (max 1000 characters).', 400 );
		return;
	}

	$post = get_post( $listing_id );
	if ( empty( $post ) || 'property' !== $post->post_type || 'publish' !== $post->post_status ) {
		houzi_ai_send_error( 'invalid_request', 'Listing not found.', 404 );
		return;
	}

	$context  = houzi_ai_property_context( $listing_id, true );
	$language = Houzi_AI_Settings::resolve_language( isset( $_POST['language'] ) ? $_POST['language'] : '' );

	$conversation_id = isset( $_POST['conversation_id'] ) ? sanitize_text_field( $_POST['conversation_id'] ) : '';
	if ( ! wp_is_uuid( $conversation_id ) ) {
		$conversation_id = wp_generate_uuid4();
	}

	$tool = array(
		'name'        => 'answer_listing_question',
		'description' => 'Answer the question about this specific listing.',
		'parameters'  => array(
			'type'       => 'object',
			'properties' => array(
				'answer'                => array( 'type' => 'string' ),
				'grounded'              => array( 'type' => 'boolean', 'description' => 'True only when the listing data answers the question.' ),
				'suggest_contact_agent' => array( 'type' => 'boolean' ),
			),
			'required'   => array( 'answer', 'grounded', 'suggest_contact_agent' ),
		),
	);

	$messages = houzi_ai_load_conversation( $conversation_id );
	if ( empty( $messages ) ) {
		$messages[] = array( 'role' => 'user', 'content' => 'Listing data: ' . wp_json_encode( $context ) );
		$messages[] = array( 'role' => 'assistant', 'content' => 'Understood. I will answer questions using only this listing data.' );
	}
	$messages[] = array( 'role' => 'user', 'content' => $question );

	$system = Houzi_AI_Prompt_Library::get( 'ask_listing', array( 'language' => $language ) );
	$result = Houzi_AI_Gateway::complete( 'ask_listing', $system, $messages, $tool );
	if ( is_wp_error( $result ) ) {
		houzi_ai_send_gateway_error( $result );
		return;
	}

	$args       = $result['tool_args'];
	$answer     = isset( $args['answer'] ) ? sanitize_text_field( $args['answer'] ) : '';
	$messages[] = array( 'role' => 'assistant', 'content' => $answer );
	houzi_ai_save_conversation( $conversation_id, $messages );

	wp_send_json( array(
		'success'               => true,
		'answer'                => $answer,
		'grounded'              => ! empty( $args['grounded'] ),
		'suggest_contact_agent' => ! empty( $args['suggest_contact_agent'] ),
		'conversation_id'       => $conversation_id,
	), 200 );
}

/*
|--------------------------------------------------------------------------
| CRM Copilot
|--------------------------------------------------------------------------
| These reuse the same Houzez CRM classes the existing /leads, /lead-details
| and /enquiry-matched-listing endpoints rely on. Those classes scope their
| queries to the logged-in agent, same as the existing endpoints.
*/

function houzi_ai_crm_available() {
	return class_exists( 'Houzez_Leads' ) && class_exists( 'Houzez_Enquiry' ) && class_exists( 'Houzez_CRM_Notes' );
}

/**
 * Compact, prompt-safe context about one lead: profile, enquiries, viewed
 * listings, notes. Caps everything — this feeds a prompt, not a report.
 */
function houzi_ai_lead_context( $lead_id ) {
	$lead = Houzez_Leads::get_lead( $lead_id );
	if ( empty( $lead ) ) {
		return null;
	}

	// Some CRM class methods read the id from the request, like the existing
	// /lead-listing-viewed endpoint does.
	$_GET['lead-id'] = $lead_id;

	$context = array(
		'name'    => isset( $lead->display_name ) ? $lead->display_name : '',
		'email'   => isset( $lead->email ) ? $lead->email : '',
		'type'    => isset( $lead->enquiry_user_type ) ? $lead->enquiry_user_type : '',
		'message' => isset( $lead->message ) ? $lead->message : '',
	);

	$enquiries = Houzez_Enquiry::get_enquires();
	$context['enquiries'] = array();
	if ( ! empty( $enquiries['data']['results'] ) ) {
		foreach ( array_slice( $enquiries['data']['results'], 0, 10 ) as $enquiry ) {
			$context['enquiries'][] = array(
				'type'     => isset( $enquiry->enquiry_type ) ? $enquiry->enquiry_type : '',
				'criteria' => maybe_unserialize( $enquiry->enquiry_meta ),
				'date'     => isset( $enquiry->time ) ? $enquiry->time : '',
			);
		}
	}

	$viewed = Houzez_Leads::get_lead_viewed_listings();
	$context['viewed_listings'] = array();
	if ( ! empty( $viewed['data']['results'] ) ) {
		foreach ( array_slice( $viewed['data']['results'], 0, 10 ) as $listing ) {
			$listing_id = intval( $listing->listing_id );
			$context['viewed_listings'][] = array(
				'title'   => get_the_title( $listing_id ),
				'price'   => get_post_meta( $listing_id, 'fave_property_price', true ),
				'address' => get_post_meta( $listing_id, 'fave_property_map_address', true ),
				'time'    => isset( $listing->time ) ? $listing->time : '',
			);
		}
	}

	$notes = Houzez_CRM_Notes::get_notes( $lead_id, 'lead' );
	$context['notes'] = array();
	if ( ! empty( $notes['data']['results'] ) ) {
		foreach ( array_slice( $notes['data']['results'], 0, 10 ) as $note ) {
			$context['notes'][] = isset( $note->note ) ? wp_strip_all_tags( $note->note ) : '';
		}
	}

	return $context;
}

function houziAiCrmLeadSummary( $request ) {
	if ( ! houzi_ai_guard( $request, 'crm', true ) ) {
		return;
	}
	if ( ! houzi_ai_crm_available() ) {
		houzi_ai_send_error( 'invalid_request', 'Houzez CRM is not available on this site.', 400 );
		return;
	}

	$lead_id = isset( $_POST['lead_id'] ) ? intval( $_POST['lead_id'] ) : 0;
	if ( $lead_id <= 0 ) {
		houzi_ai_send_error( 'invalid_request', 'Please provide lead_id.', 400 );
		return;
	}

	$context = houzi_ai_lead_context( $lead_id );
	if ( null === $context ) {
		houzi_ai_send_error( 'invalid_request', 'Lead not found.', 404 );
		return;
	}

	$language = Houzi_AI_Settings::resolve_language( isset( $_POST['language'] ) ? $_POST['language'] : '' );

	$tool = array(
		'name'        => 'summarize_lead',
		'description' => 'Summarize this CRM lead for the agent.',
		'parameters'  => array(
			'type'       => 'object',
			'properties' => array(
				'summary'     => array( 'type' => 'string', 'description' => 'At most 3 short sentences.' ),
				'next_action' => array( 'type' => 'string', 'description' => 'One concrete next step.' ),
				'signals'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Up to 4 notable behavior signals.' ),
			),
			'required'   => array( 'summary', 'next_action' ),
		),
	);

	$system = Houzi_AI_Prompt_Library::get( 'crm_lead_summary', array( 'language' => $language ) );
	$result = Houzi_AI_Gateway::complete(
		'crm',
		$system,
		array( array( 'role' => 'user', 'content' => 'Lead data: ' . wp_json_encode( $context ) ) ),
		$tool
	);
	if ( is_wp_error( $result ) ) {
		houzi_ai_send_gateway_error( $result );
		return;
	}

	$args = $result['tool_args'];
	wp_send_json( array(
		'success'     => true,
		'summary'     => isset( $args['summary'] ) ? sanitize_text_field( $args['summary'] ) : '',
		'next_action' => isset( $args['next_action'] ) ? sanitize_text_field( $args['next_action'] ) : '',
		'signals'     => ( isset( $args['signals'] ) && is_array( $args['signals'] ) )
			? array_slice( array_map( 'sanitize_text_field', $args['signals'] ), 0, 4 )
			: array(),
	), 200 );
}

function houziAiCrmRankMatches( $request ) {
	if ( ! houzi_ai_guard( $request, 'crm', true ) ) {
		return;
	}
	if ( ! houzi_ai_crm_available() || ! function_exists( 'matched_listings' ) ) {
		houzi_ai_send_error( 'invalid_request', 'Houzez CRM is not available on this site.', 400 );
		return;
	}

	$enquiry_id = isset( $_POST['enquiry_id'] ) ? intval( $_POST['enquiry_id'] ) : 0;
	if ( $enquiry_id <= 0 ) {
		houzi_ai_send_error( 'invalid_request', 'Please provide enquiry_id.', 400 );
		return;
	}

	$enquiry = Houzez_Enquiry::get_enquiry( $enquiry_id );
	if ( empty( $enquiry ) ) {
		houzi_ai_send_error( 'invalid_request', 'Enquiry not found.', 404 );
		return;
	}

	// Candidates: the same matched query the CRM screens already use.
	$matched_query = matched_listings( $enquiry->enquiry_meta );
	$candidates    = array();
	if ( ! empty( $matched_query->posts ) ) {
		foreach ( array_slice( $matched_query->posts, 0, 15 ) as $property_post ) {
			$candidate = houzi_ai_property_context( $property_post->ID );
			if ( null !== $candidate ) {
				$candidates[] = $candidate;
			}
		}
	}
	if ( empty( $candidates ) ) {
		wp_send_json( array( 'success' => true, 'ranked' => array() ), 200 );
		return;
	}

	// The lead's viewing behavior is the ranking signal competitors don't have.
	$_GET['lead-id'] = intval( $enquiry->lead_id );
	$viewed          = Houzez_Leads::get_lead_viewed_listings();
	$viewed_titles   = array();
	if ( ! empty( $viewed['data']['results'] ) ) {
		foreach ( array_slice( $viewed['data']['results'], 0, 10 ) as $listing ) {
			$viewed_titles[] = get_the_title( intval( $listing->listing_id ) );
		}
	}

	$language = Houzi_AI_Settings::resolve_language( isset( $_POST['language'] ) ? $_POST['language'] : '' );

	$tool = array(
		'name'        => 'rank_matched_properties',
		'description' => 'Rank candidate properties by fit for this lead.',
		'parameters'  => array(
			'type'       => 'object',
			'properties' => array(
				'ranked' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'property_id' => array( 'type' => 'integer' ),
							'score'       => array( 'type' => 'integer', 'description' => '0-100 fit score.' ),
							'why'         => array( 'type' => 'string', 'description' => 'One short sentence.' ),
						),
						'required'   => array( 'property_id', 'score', 'why' ),
					),
				),
			),
			'required'   => array( 'ranked' ),
		),
	);

	$user_message = 'Enquiry criteria: ' . wp_json_encode( maybe_unserialize( $enquiry->enquiry_meta ) ) . "\n"
		. 'Previously viewed by this lead: ' . wp_json_encode( $viewed_titles ) . "\n"
		. 'Candidate properties: ' . wp_json_encode( $candidates );

	$system = Houzi_AI_Prompt_Library::get( 'crm_rank_matches', array( 'language' => $language ) );
	$result = Houzi_AI_Gateway::complete( 'crm', $system, array( array( 'role' => 'user', 'content' => $user_message ) ), $tool, array( 'max_tokens' => 2048 ) );
	if ( is_wp_error( $result ) ) {
		houzi_ai_send_gateway_error( $result );
		return;
	}

	// Validate: only candidate ids, each at most once, score clamped.
	$candidate_ids = wp_list_pluck( $candidates, 'id' );
	$ranked        = array();
	$seen          = array();
	if ( ! empty( $result['tool_args']['ranked'] ) && is_array( $result['tool_args']['ranked'] ) ) {
		foreach ( $result['tool_args']['ranked'] as $entry ) {
			$property_id = isset( $entry['property_id'] ) ? intval( $entry['property_id'] ) : 0;
			if ( ! in_array( $property_id, $candidate_ids, true ) || isset( $seen[ $property_id ] ) ) {
				continue;
			}
			$seen[ $property_id ] = true;
			$ranked[]             = array(
				'property_id' => $property_id,
				'score'       => max( 0, min( 100, isset( $entry['score'] ) ? intval( $entry['score'] ) : 0 ) ),
				'why'         => isset( $entry['why'] ) ? sanitize_text_field( $entry['why'] ) : '',
			);
		}
	}

	wp_send_json( array( 'success' => true, 'ranked' => $ranked ), 200 );
}

function houziAiCrmDraftEmail( $request ) {
	if ( ! houzi_ai_guard( $request, 'crm', true ) ) {
		return;
	}
	if ( ! houzi_ai_crm_available() ) {
		houzi_ai_send_error( 'invalid_request', 'Houzez CRM is not available on this site.', 400 );
		return;
	}

	$lead_id      = isset( $_POST['lead_id'] ) ? intval( $_POST['lead_id'] ) : 0;
	$property_ids = isset( $_POST['property_ids'] ) ? (array) $_POST['property_ids'] : array();
	$property_ids = array_slice( array_filter( array_map( 'intval', $property_ids ) ), 0, 5 );

	if ( $lead_id <= 0 || empty( $property_ids ) ) {
		houzi_ai_send_error( 'invalid_request', 'Please provide lead_id and property_ids.', 400 );
		return;
	}

	$lead = Houzez_Leads::get_lead( $lead_id );
	if ( empty( $lead ) ) {
		houzi_ai_send_error( 'invalid_request', 'Lead not found.', 404 );
		return;
	}

	$properties = array();
	foreach ( $property_ids as $property_id ) {
		$post = get_post( $property_id );
		if ( empty( $post ) || 'property' !== $post->post_type ) {
			continue;
		}
		// Only surface published listings, or ones this agent can edit — never
		// leak another agent's draft/pending listing into an email draft.
		if ( 'publish' !== $post->post_status
			&& intval( $post->post_author ) !== get_current_user_id()
			&& ! current_user_can( 'edit_post', $property_id ) ) {
			continue;
		}
		$property = houzi_ai_property_context( $property_id );
		if ( null !== $property ) {
			$property['link'] = get_permalink( $property_id );
			$properties[]     = $property;
		}
	}
	if ( empty( $properties ) ) {
		houzi_ai_send_error( 'invalid_request', 'No valid properties found for the given ids.', 400 );
		return;
	}

	$tone = isset( $_POST['tone'] ) ? sanitize_text_field( $_POST['tone'] ) : 'professional';
	$tone = in_array( $tone, array( 'professional', 'casual', 'luxury' ), true ) ? $tone : 'professional';

	$agent    = wp_get_current_user();
	$language = Houzi_AI_Settings::resolve_language( isset( $_POST['language'] ) ? $_POST['language'] : '' );

	$tool = array(
		'name'        => 'draft_outreach_email',
		'description' => 'Draft the outreach email for the agent to review.',
		'parameters'  => array(
			'type'       => 'object',
			'properties' => array(
				'subject' => array( 'type' => 'string' ),
				'body'    => array( 'type' => 'string', 'description' => 'Plain text email body.' ),
			),
			'required'   => array( 'subject', 'body' ),
		),
	);

	$user_message = 'Tone: ' . $tone . "\n"
		. 'Agent name: ' . $agent->display_name . "\n"
		. 'Lead: ' . wp_json_encode( array(
			'name'    => isset( $lead->display_name ) ? $lead->display_name : '',
			'message' => isset( $lead->message ) ? $lead->message : '',
		) ) . "\n"
		. 'Properties to present: ' . wp_json_encode( $properties );

	$system = Houzi_AI_Prompt_Library::get( 'crm_draft_email', array( 'language' => $language ) );
	$result = Houzi_AI_Gateway::complete( 'crm', $system, array( array( 'role' => 'user', 'content' => $user_message ) ), $tool, array( 'max_tokens' => 1200, 'temperature' => 0.7 ) );
	if ( is_wp_error( $result ) ) {
		houzi_ai_send_gateway_error( $result );
		return;
	}

	$args = $result['tool_args'];
	// Draft only — sending stays on the existing send-matched-listing-email path.
	wp_send_json( array(
		'success' => true,
		'subject' => isset( $args['subject'] ) ? sanitize_text_field( $args['subject'] ) : '',
		'body'    => isset( $args['body'] ) ? sanitize_textarea_field( $args['body'] ) : '',
	), 200 );
}

/*
|--------------------------------------------------------------------------
| touch-base advertisement
|--------------------------------------------------------------------------
*/

/**
 * Which AI features this site actually offers right now (master switch + key
 * + per-feature toggle). Consumed by getMetaData() so the app can show/hide
 * AI affordances without a separate call.
 */
function houzi_ai_touch_base_features() {
	$ready = Houzi_AI_Settings::is_enabled() && '' !== Houzi_AI_Settings::api_key();
	return array(
		'enabled'     => $ready,
		'search'      => $ready && Houzi_AI_Settings::feature_enabled( 'search' ),
		'describe'    => $ready && Houzi_AI_Settings::feature_enabled( 'describe' ),
		'ask_listing' => $ready && Houzi_AI_Settings::feature_enabled( 'ask_listing' ),
		// CRM AI is only real when the Houzez CRM classes are actually present.
		'crm'         => $ready && Houzi_AI_Settings::feature_enabled( 'crm' ) && houzi_ai_crm_available(),
		'suggestions' => $ready && Houzi_AI_Settings::feature_enabled( 'suggestions' ),
	);
}
