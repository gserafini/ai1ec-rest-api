<?php
/**
 * Plugin Name: All-in-One Event Calendar REST API
 * Plugin URI: https://github.com/gserafini/ai1ec-rest-api
 * GitHub Plugin URI: gserafini/ai1ec-rest-api
 * Description: REST API endpoints for All-in-One Event Calendar by Time.ly plugin
 * Version: 1.1.2
 * Author: Gabriel Serafini
 * Author URI: https://gabrielserafini.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * Text Domain: ai1ec-rest-api
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Silently check if All-in-One Event Calendar is active
// If not active, plugin does nothing (safe for network activation)
function ai1ec_rest_api_check_dependencies() {
	global $wpdb;
	// Check if AI1EC tables exist
	$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}ai1ec_events'" );
	return (bool) $table_exists;
}

// Plugin activation hook - flush permalinks to register new routes
function ai1ec_rest_api_activate() {
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'ai1ec_rest_api_activate' );

// Disable canonical redirects for REST API requests
add_filter( 'redirect_canonical', 'ai1ec_rest_api_disable_canonical_redirect', 10, 2 );
function ai1ec_rest_api_disable_canonical_redirect( $redirect_url, $requested_url ) {
	if ( strpos( $requested_url, '/wp-json/' ) !== false || strpos( $requested_url, rest_get_url_prefix() ) !== false ) {
		return false;
	}
	return $redirect_url;
}

// Register REST API routes
add_action( 'rest_api_init', 'ai1ec_rest_api_register_routes' );

function ai1ec_rest_api_register_routes() {
	// Exit silently if AI1EC is not active
	if ( ! ai1ec_rest_api_check_dependencies() ) {
		return;
	}

	$namespace = 'ai1ec/v1';

	// Event endpoints - use ai1ec_api prefix to avoid conflicts with custom post type
	register_rest_route(
		$namespace,
		'/ai1ec_api_events',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'ai1ec_rest_get_events',
				'permission_callback' => 'ai1ec_rest_read_permission',
			),
			array(
				'methods'             => 'POST',
				'callback'            => 'ai1ec_rest_create_event',
				'permission_callback' => 'ai1ec_rest_write_permission',
			),
		)
	);

	register_rest_route(
		$namespace,
		'/ai1ec_api_events/(?P<id>\d+)',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'ai1ec_rest_get_event',
				'permission_callback' => 'ai1ec_rest_read_permission',
			),
			array(
				'methods'             => array( 'POST', 'PUT', 'PATCH' ),
				'callback'            => 'ai1ec_rest_update_event',
				'permission_callback' => 'ai1ec_rest_write_permission',
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => 'ai1ec_rest_delete_event',
				'permission_callback' => 'ai1ec_rest_write_permission',
			),
		)
	);

	// Category endpoints
	register_rest_route(
		$namespace,
		'/ai1ec_api_categories',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'ai1ec_rest_get_categories',
				'permission_callback' => 'ai1ec_rest_read_permission',
			),
			array(
				'methods'             => 'POST',
				'callback'            => 'ai1ec_rest_create_category',
				'permission_callback' => 'ai1ec_rest_write_permission',
			),
		)
	);
}

// Permission callbacks
function ai1ec_rest_read_permission() {
	return true; // Public read access
}

function ai1ec_rest_write_permission() {
	return current_user_can( 'edit_posts' );
}

// Convert datetime string to GMT Unix timestamp
function ai1ec_rest_datetime_to_gmt( $datetime_str, $timezone_name = 'UTC' ) {
	try {
		$dt = new DateTime( $datetime_str, new DateTimeZone( $timezone_name ) );
		$dt->setTimezone( new DateTimeZone( 'UTC' ) );
		return $dt->getTimestamp();
	} catch ( Exception $e ) {
		// Fallback to strtotime if timezone conversion fails
		return strtotime( $datetime_str );
	}
}

// Upload image from URL and set as featured image
function ai1ec_rest_set_featured_image_from_url( $post_id, $image_url, $alt_text = '' ) {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	// Download the image
	$tmp = download_url( $image_url );
	if ( is_wp_error( $tmp ) ) {
		return $tmp;
	}

	// Set up the array of arguments for wp_handle_sideload
	$file_array = array(
		'name'     => basename( $image_url ),
		'tmp_name' => $tmp,
	);

	// Upload the file to the WordPress media library
	$attachment_id = media_handle_sideload( $file_array, $post_id );

	// Clean up temp file
	if ( file_exists( $tmp ) ) {
		wp_delete_file( $tmp );
	}

	if ( is_wp_error( $attachment_id ) ) {
		return $attachment_id;
	}

	// Set alt text if provided
	if ( ! empty( $alt_text ) ) {
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );
	}

	// Set as featured image
	set_post_thumbnail( $post_id, $attachment_id );

	return $attachment_id;
}

// Get AI1EC registry (if available)
function ai1ec_rest_get_registry() {
	global $ai1ec_registry;
	if ( isset( $ai1ec_registry ) ) {
		return $ai1ec_registry;
	}
	return null;
}

// Format event data for API response
function ai1ec_rest_format_event( $event_data ) {
	if ( ! $event_data ) {
		return null;
	}

	// Get post data
	$post_id = $event_data['post_id'] ?? 0;
	$post    = get_post( $post_id );

	if ( ! $post ) {
		return null;
	}

	// Get categories
	$taxonomy_exists  = taxonomy_exists( 'events_categories' );
	$categories       = wp_get_post_terms( $post_id, 'events_categories', array( 'fields' => 'ids' ) );
	$categories_debug = array(
		'result'          => $categories,
		'taxonomy_exists' => $taxonomy_exists,
		'post_type'       => get_post_type( $post_id ),
	);
	if ( is_wp_error( $categories ) ) {
		$categories = array();
	} elseif ( false === $categories || null === $categories ) {
		$categories = array();
	}

	// Get tags
	$tags       = wp_get_post_terms( $post_id, 'events_tags', array( 'fields' => 'names' ) );
	$tags_debug = $tags;
	if ( is_wp_error( $tags ) ) {
		$tags = array();
	}

	return array(
		'id'                 => $post_id,
		'title'              => $post->post_title,
		'description'        => $post->post_content,
		'start_date'         => gmdate( 'Y-m-d H:i:s', $event_data['start'] ?? 0 ),
		'end_date'           => gmdate( 'Y-m-d H:i:s', $event_data['end'] ?? 0 ),
		'allday'             => (bool) ( $event_data['allday'] ?? false ),
		'instant_event'      => (bool) ( $event_data['instant_event'] ?? false ),
		'timezone'           => $event_data['timezone_name'] ?? '',
		'venue'              => $event_data['venue'] ?? '',
		'address'            => $event_data['address'] ?? '',
		'city'               => $event_data['city'] ?? '',
		'province'           => $event_data['province'] ?? '',
		'postal_code'        => $event_data['postal_code'] ?? '',
		'country'            => $event_data['country'] ?? '',
		'cost'               => $event_data['cost'] ?? '',
		'contact_name'       => $event_data['contact_name'] ?? '',
		'contact_phone'      => $event_data['contact_phone'] ?? '',
		'contact_email'      => $event_data['contact_email'] ?? '',
		'contact_url'        => $event_data['contact_url'] ?? '',
		'ticket_url'         => $event_data['ticket_url'] ?? '',
		'show_map'           => (bool) ( $event_data['show_map'] ?? true ),
		'categories'         => $categories,
		'tags'               => $tags,
		'status'             => $post->post_status,
		'slug'               => $post->post_name,
		'url'                => get_permalink( $post_id ),
		'featured_image_url' => get_the_post_thumbnail_url( $post_id, 'full' ) ? get_the_post_thumbnail_url( $post_id, 'full' ) : null,
		'created_date'       => $post->post_date,
		'modified_date'      => $post->post_modified,
		'debug_cat_raw'      => is_wp_error( $categories_debug ) ? $categories_debug->get_error_message() : $categories_debug,
		'debug_tag_raw'      => is_wp_error( $tags_debug ) ? $tags_debug->get_error_message() : $tags_debug,
	);
}

// Get events list
function ai1ec_rest_get_events( $request ) {
	global $wpdb;

	$table_name = $wpdb->prefix . 'ai1ec_events';

	// Get query parameters
	$limit  = $request->get_param( 'limit' ) ?? 50;
	$offset = $request->get_param( 'offset' ) ?? 0;

	// Query events
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
	$sql = $wpdb->prepare( "SELECT * FROM $table_name ORDER BY start DESC LIMIT %d OFFSET %d", $limit, $offset );

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above with $wpdb->prepare().
	$events = $wpdb->get_results( $sql, ARRAY_A );

	$formatted_events = array();
	foreach ( $events as $event ) {
		$formatted = ai1ec_rest_format_event( $event );
		if ( $formatted ) {
			$formatted_events[] = $formatted;
		}
	}

	return rest_ensure_response(
		array(
			'success' => true,
			'events'  => $formatted_events,
			'count'   => count( $formatted_events ),
		)
	);
}

// Get single event
function ai1ec_rest_get_event( $request ) {
	global $wpdb;

	$post_id    = intval( $request['id'] );
	$table_name = $wpdb->prefix . 'ai1ec_events';

	$event = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM $table_name WHERE post_id = %d", $post_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
		ARRAY_A
	);

	if ( ! $event ) {
		return new WP_Error( 'not_found', 'Event not found', array( 'status' => 404 ) );
	}

	$formatted = ai1ec_rest_format_event( $event );

	return rest_ensure_response( $formatted );
}

// Create event
function ai1ec_rest_create_event( $request ) {
	global $wpdb;

	$params = $request->get_json_params();

	// Validate required fields
	if ( empty( $params['title'] ) ) {
		return new WP_Error( 'missing_title', 'Event title is required', array( 'status' => 400 ) );
	}
	if ( empty( $params['start_date'] ) ) {
		return new WP_Error( 'missing_start', 'Start date is required', array( 'status' => 400 ) );
	}

	// Validate post status against allowed values.
	$allowed_statuses = array( 'draft', 'publish', 'pending', 'private' );
	$post_status      = isset( $params['status'] ) && in_array( $params['status'], $allowed_statuses, true )
		? $params['status']
		: 'draft';

	// Create WordPress post
	$post_data = array(
		'post_title'   => sanitize_text_field( $params['title'] ),
		'post_content' => wp_kses_post( $params['description'] ?? '' ),
		'post_status'  => $post_status,
		'post_type'    => 'ai1ec_event',
	);

	$post_id = wp_insert_post( $post_data );

	if ( is_wp_error( $post_id ) || 0 === $post_id ) {
		return new WP_Error( 'creation_failed', 'Failed to create event', array( 'status' => 500 ) );
	}

	// Parse dates - convert to GMT Unix timestamps
	$timezone        = $params['timezone'] ?? 'UTC';
	$start_timestamp = ai1ec_rest_datetime_to_gmt( $params['start_date'], $timezone );
	$end_timestamp   = isset( $params['end_date'] ) ? ai1ec_rest_datetime_to_gmt( $params['end_date'], $timezone ) : $start_timestamp + 3600;

	// Insert into ai1ec_events table
	$table_name = $wpdb->prefix . 'ai1ec_events';
	$event_data = array(
		'post_id'       => $post_id,
		'start'         => $start_timestamp,
		'end'           => $end_timestamp,
		'timezone_name' => $params['timezone'] ?? '',
		'allday'        => isset( $params['allday'] ) ? (int) $params['allday'] : 0,
		'instant_event' => isset( $params['instant_event'] ) ? (int) $params['instant_event'] : 0,
		'venue'         => sanitize_text_field( $params['venue'] ?? '' ),
		'address'       => sanitize_text_field( $params['address'] ?? '' ),
		'city'          => sanitize_text_field( $params['city'] ?? '' ),
		'province'      => sanitize_text_field( $params['province'] ?? '' ),
		'postal_code'   => sanitize_text_field( $params['postal_code'] ?? '' ),
		'country'       => sanitize_text_field( $params['country'] ?? '' ),
		'cost'          => sanitize_text_field( $params['cost'] ?? '' ),
		'contact_name'  => sanitize_text_field( $params['contact_name'] ?? '' ),
		'contact_phone' => sanitize_text_field( $params['contact_phone'] ?? '' ),
		'contact_email' => sanitize_email( $params['contact_email'] ?? '' ),
		'contact_url'   => esc_url_raw( $params['contact_url'] ?? '' ),
		'ticket_url'    => esc_url_raw( $params['ticket_url'] ?? '' ),
		'show_map'      => isset( $params['show_map'] ) ? (int) $params['show_map'] : 1,
	);

	$result = $wpdb->insert( $table_name, $event_data );

	if ( false === $result ) {
		wp_delete_post( $post_id, true );
		return new WP_Error( 'creation_failed', 'Failed to create event data', array( 'status' => 500 ) );
	}

	// Set categories if provided
	$cat_result = null;
	if ( ! empty( $params['categories'] ) && is_array( $params['categories'] ) ) {
		$cat_result = wp_set_object_terms( $post_id, $params['categories'], 'events_categories', false );
		// Force term count update
		wp_update_term_count_now( $params['categories'], 'events_categories' );
	}

	// Set tags if provided
	$tag_result = null;
	if ( ! empty( $params['tags'] ) && is_array( $params['tags'] ) ) {
		$tag_result = wp_set_post_terms( $post_id, $params['tags'], 'events_tags', false );
	}

	// Set featured image if provided
	if ( ! empty( $params['featured_image_url'] ) ) {
		$alt_text = $params['featured_image_alt'] ?? '';
		ai1ec_rest_set_featured_image_from_url( $post_id, $params['featured_image_url'], $alt_text );
	}

	// Get created event
	$created_event = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM $table_name WHERE post_id = %d", $post_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
		ARRAY_A
	);

	$response = array(
		'success'  => true,
		'event_id' => $post_id,
		'event'    => ai1ec_rest_format_event( $created_event ),
		'message'  => 'Event created successfully',
	);

	// Add debug info for category/tag updates
	if ( null !== $cat_result ) {
		$response['debug_create_cat'] = is_wp_error( $cat_result ) ? $cat_result->get_error_message() : $cat_result;
	}
	if ( null !== $tag_result ) {
		$response['debug_create_tag'] = is_wp_error( $tag_result ) ? $tag_result->get_error_message() : $tag_result;
	}

	return rest_ensure_response( $response );
}

// Update event.
function ai1ec_rest_update_event( $request ) {
	global $wpdb;

	$post_id = intval( $request['id'] );
	$params  = $request->get_json_params();

	$table_name = $wpdb->prefix . 'ai1ec_events';

	// Check if event exists
	$event = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM $table_name WHERE post_id = %d", $post_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
		ARRAY_A
	);

	if ( ! $event ) {
		return new WP_Error( 'not_found', 'Event not found', array( 'status' => 404 ) );
	}

	// Update post if title or description provided
	$post_update = array();
	if ( isset( $params['title'] ) ) {
		$post_update['post_title'] = sanitize_text_field( $params['title'] );
	}
	if ( isset( $params['description'] ) ) {
		$post_update['post_content'] = wp_kses_post( $params['description'] );
	}
	if ( isset( $params['status'] ) ) {
		$allowed_statuses = array( 'draft', 'publish', 'pending', 'private' );
		if ( in_array( $params['status'], $allowed_statuses, true ) ) {
			$post_update['post_status'] = $params['status'];
		}
	}

	if ( ! empty( $post_update ) ) {
		$post_update['ID'] = $post_id;
		wp_update_post( $post_update );
	}

	// Update event data
	$event_update = array();

	// Get timezone for date conversion (use provided timezone or existing one)
	$timezone = $params['timezone'] ?? $event['timezone_name'] ?? 'UTC';

	if ( isset( $params['start_date'] ) ) {
		$event_update['start'] = ai1ec_rest_datetime_to_gmt( $params['start_date'], $timezone );
	}
	if ( isset( $params['end_date'] ) ) {
		$event_update['end'] = ai1ec_rest_datetime_to_gmt( $params['end_date'], $timezone );
	}
	if ( isset( $params['timezone'] ) ) {
		$event_update['timezone_name'] = sanitize_text_field( $params['timezone'] );
	}
	if ( isset( $params['allday'] ) ) {
		$event_update['allday'] = (int) $params['allday'];
	}
	if ( isset( $params['venue'] ) ) {
		$event_update['venue'] = sanitize_text_field( $params['venue'] );
	}
	if ( isset( $params['address'] ) ) {
		$event_update['address'] = sanitize_text_field( $params['address'] );
	}
	if ( isset( $params['city'] ) ) {
		$event_update['city'] = sanitize_text_field( $params['city'] );
	}
	if ( isset( $params['province'] ) ) {
		$event_update['province'] = sanitize_text_field( $params['province'] );
	}
	if ( isset( $params['postal_code'] ) ) {
		$event_update['postal_code'] = sanitize_text_field( $params['postal_code'] );
	}
	if ( isset( $params['country'] ) ) {
		$event_update['country'] = sanitize_text_field( $params['country'] );
	}
	if ( isset( $params['cost'] ) ) {
		$event_update['cost'] = sanitize_text_field( $params['cost'] );
	}
	if ( isset( $params['contact_name'] ) ) {
		$event_update['contact_name'] = sanitize_text_field( $params['contact_name'] );
	}
	if ( isset( $params['contact_phone'] ) ) {
		$event_update['contact_phone'] = sanitize_text_field( $params['contact_phone'] );
	}
	if ( isset( $params['contact_email'] ) ) {
		$event_update['contact_email'] = sanitize_email( $params['contact_email'] );
	}
	if ( isset( $params['contact_url'] ) ) {
		$event_update['contact_url'] = esc_url_raw( $params['contact_url'] );
	}
	if ( isset( $params['ticket_url'] ) ) {
		$event_update['ticket_url'] = esc_url_raw( $params['ticket_url'] );
	}
	if ( isset( $params['show_map'] ) ) {
		$event_update['show_map'] = (int) $params['show_map'];
	}

	if ( ! empty( $event_update ) ) {
		$wpdb->update(
			$table_name,
			$event_update,
			array( 'post_id' => $post_id )
		);
	}

	// Update categories if provided
	$cat_result = null;
	if ( isset( $params['categories'] ) && is_array( $params['categories'] ) ) {
		// First clear existing terms
		wp_set_object_terms( $post_id, array(), 'events_categories' );
		// Then set new terms
		$cat_result = wp_set_object_terms( $post_id, $params['categories'], 'events_categories', false );
		// Force term count update
		wp_update_term_count_now( $params['categories'], 'events_categories' );
	}

	// Update tags if provided
	$tag_result = null;
	if ( isset( $params['tags'] ) && is_array( $params['tags'] ) ) {
		$tag_result = wp_set_post_terms( $post_id, $params['tags'], 'events_tags', false );
	}

	// Update featured image if provided
	if ( isset( $params['featured_image_url'] ) ) {
		$alt_text = $params['featured_image_alt'] ?? '';
		ai1ec_rest_set_featured_image_from_url( $post_id, $params['featured_image_url'], $alt_text );
	}

	// Get updated event
	$updated_event = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM $table_name WHERE post_id = %d", $post_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
		ARRAY_A
	);

	$response = array(
		'success' => true,
		'event'   => ai1ec_rest_format_event( $updated_event ),
		'message' => 'Event updated successfully',
	);

	// Add debug info for category/tag updates
	if ( null !== $cat_result ) {
		$response['debug_cat'] = is_wp_error( $cat_result ) ? $cat_result->get_error_message() : $cat_result;
	}
	if ( null !== $tag_result ) {
		$response['debug_tag'] = is_wp_error( $tag_result ) ? $tag_result->get_error_message() : $tag_result;
	}

	return rest_ensure_response( $response );
}

// Delete event
function ai1ec_rest_delete_event( $request ) {
	global $wpdb;

	$post_id    = intval( $request['id'] );
	$table_name = $wpdb->prefix . 'ai1ec_events';

	// Check if event exists
	$event = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM $table_name WHERE post_id = %d", $post_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
		ARRAY_A
	);

	if ( ! $event ) {
		return new WP_Error( 'not_found', 'Event not found', array( 'status' => 404 ) );
	}

	// Delete from ai1ec_events table first
	$wpdb->delete( $table_name, array( 'post_id' => $post_id ) );

	// Delete WordPress post (this also deletes terms)
	$result = wp_delete_post( $post_id, true );

	if ( ! $result ) {
		return new WP_Error( 'deletion_failed', 'Failed to delete event', array( 'status' => 500 ) );
	}

	return rest_ensure_response(
		array(
			'success' => true,
			'message' => 'Event deleted successfully',
		)
	);
}

// Get categories.
function ai1ec_rest_get_categories( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by WP REST API callback signature.
	$terms = get_terms(
		array(
			'taxonomy'   => 'events_categories',
			'hide_empty' => false,
		)
	);

	if ( is_wp_error( $terms ) ) {
		return new WP_Error( 'query_failed', 'Failed to get categories', array( 'status' => 500 ) );
	}

	$categories = array();
	foreach ( $terms as $term ) {
		$categories[] = array(
			'id'          => $term->term_id,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
			'count'       => $term->count,
		);
	}

	return rest_ensure_response(
		array(
			'success'    => true,
			'categories' => $categories,
			'count'      => count( $categories ),
		)
	);
}

// Create category
function ai1ec_rest_create_category( $request ) {
	$params = $request->get_json_params();

	// Validate required fields
	if ( empty( $params['name'] ) ) {
		return new WP_Error( 'missing_name', 'Category name is required', array( 'status' => 400 ) );
	}

	// Prepare term data
	$args = array();
	if ( ! empty( $params['slug'] ) ) {
		$args['slug'] = sanitize_title( $params['slug'] );
	}
	if ( ! empty( $params['description'] ) ) {
		$args['description'] = sanitize_text_field( $params['description'] );
	}

	// Create term
	$result = wp_insert_term(
		sanitize_text_field( $params['name'] ),
		'events_categories',
		$args
	);

	if ( is_wp_error( $result ) ) {
		return new WP_Error( 'creation_failed', $result->get_error_message(), array( 'status' => 500 ) );
	}

	// Get the created term
	$term = get_term( $result['term_id'], 'events_categories' );

	return rest_ensure_response(
		array(
			'success'  => true,
			'category' => array(
				'id'          => $term->term_id,
				'name'        => $term->name,
				'slug'        => $term->slug,
				'description' => $term->description,
				'count'       => $term->count,
			),
			'message'  => 'Category created successfully',
		)
	);
}
