<?php
/**
 * Plugin Name: All-in-One Event Calendar REST API
 * Plugin URI: https://github.com/gserafini/ai1ec-rest-api
 * GitHub Plugin URI: gserafini/ai1ec-rest-api
 * Description: REST API endpoints for All-in-One Event Calendar by Time.ly plugin
 * Version: 1.0.0
 * Author: Gabriel Serafini
 * Author URI: https://gabrielserafini.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * Text Domain: ai1ec-rest-api
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check if All-in-One Event Calendar is active
function ai1ec_rest_api_check_dependencies() {
    global $wpdb;
    // Check if AI1EC tables exist
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}ai1ec_events'");
    if (!$table_exists) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p><strong>AI1EC REST API:</strong> All-in-One Event Calendar plugin must be installed and activated.</p></div>';
        });
        return false;
    }
    return true;
}
add_action('plugins_loaded', 'ai1ec_rest_api_check_dependencies');

// Plugin activation hook - flush permalinks to register new routes
function ai1ec_rest_api_activate() {
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'ai1ec_rest_api_activate');

// Disable canonical redirects for REST API requests
add_filter('redirect_canonical', 'ai1ec_rest_api_disable_canonical_redirect', 10, 2);
function ai1ec_rest_api_disable_canonical_redirect($redirect_url, $requested_url) {
    if (strpos($requested_url, '/wp-json/') !== false || strpos($requested_url, rest_get_url_prefix()) !== false) {
        return false;
    }
    return $redirect_url;
}

// Register REST API routes
add_action('rest_api_init', 'ai1ec_rest_api_register_routes');

function ai1ec_rest_api_register_routes() {
    $namespace = 'ai1ec/v1';

    // Event endpoints - use ai1ec_api prefix to avoid conflicts with custom post type
    register_rest_route($namespace, '/ai1ec_api_events', [
        [
            'methods' => 'GET',
            'callback' => 'ai1ec_rest_get_events',
            'permission_callback' => 'ai1ec_rest_read_permission',
        ],
        [
            'methods' => 'POST',
            'callback' => 'ai1ec_rest_create_event',
            'permission_callback' => 'ai1ec_rest_write_permission',
        ],
    ]);

    register_rest_route($namespace, '/ai1ec_api_events/(?P<id>\d+)', [
        [
            'methods' => 'GET',
            'callback' => 'ai1ec_rest_get_event',
            'permission_callback' => 'ai1ec_rest_read_permission',
        ],
        [
            'methods' => ['POST', 'PUT', 'PATCH'],
            'callback' => 'ai1ec_rest_update_event',
            'permission_callback' => 'ai1ec_rest_write_permission',
        ],
        [
            'methods' => 'DELETE',
            'callback' => 'ai1ec_rest_delete_event',
            'permission_callback' => 'ai1ec_rest_write_permission',
        ],
    ]);

    // Category endpoints
    register_rest_route($namespace, '/ai1ec_api_categories', [
        [
            'methods' => 'GET',
            'callback' => 'ai1ec_rest_get_categories',
            'permission_callback' => 'ai1ec_rest_read_permission',
        ],
    ]);
}

// Permission callbacks
function ai1ec_rest_read_permission() {
    return true; // Public read access
}

function ai1ec_rest_write_permission() {
    return current_user_can('edit_posts');
}

// Get AI1EC registry (if available)
function ai1ec_rest_get_registry() {
    global $ai1ec_registry;
    if (isset($ai1ec_registry)) {
        return $ai1ec_registry;
    }
    return null;
}

// Format event data for API response
function ai1ec_rest_format_event($event_data) {
    if (!$event_data) {
        return null;
    }

    // Get post data
    $post_id = $event_data['post_id'] ?? 0;
    $post = get_post($post_id);

    if (!$post) {
        return null;
    }

    // Get categories
    $categories = wp_get_post_terms($post_id, 'events_categories', ['fields' => 'ids']);

    // Get tags
    $tags = wp_get_post_terms($post_id, 'events_tags', ['fields' => 'names']);

    return [
        'id' => $post_id,
        'title' => $post->post_title,
        'description' => $post->post_content,
        'start_date' => date('Y-m-d H:i:s', $event_data['start'] ?? 0),
        'end_date' => date('Y-m-d H:i:s', $event_data['end'] ?? 0),
        'allday' => (bool)($event_data['allday'] ?? false),
        'instant_event' => (bool)($event_data['instant_event'] ?? false),
        'timezone' => $event_data['timezone_name'] ?? '',
        'venue' => $event_data['venue'] ?? '',
        'address' => $event_data['address'] ?? '',
        'city' => $event_data['city'] ?? '',
        'province' => $event_data['province'] ?? '',
        'postal_code' => $event_data['postal_code'] ?? '',
        'country' => $event_data['country'] ?? '',
        'cost' => $event_data['cost'] ?? '',
        'contact_name' => $event_data['contact_name'] ?? '',
        'contact_phone' => $event_data['contact_phone'] ?? '',
        'contact_email' => $event_data['contact_email'] ?? '',
        'contact_url' => $event_data['contact_url'] ?? '',
        'ticket_url' => $event_data['ticket_url'] ?? '',
        'show_map' => (bool)($event_data['show_map'] ?? true),
        'categories' => $categories,
        'tags' => $tags,
        'status' => $post->post_status,
        'slug' => $post->post_name,
        'url' => get_permalink($post_id),
        'created_date' => $post->post_date,
        'modified_date' => $post->post_modified,
    ];
}

// Get events list
function ai1ec_rest_get_events($request) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'ai1ec_events';

    // Get query parameters
    $limit = $request->get_param('limit') ?? 50;
    $offset = $request->get_param('offset') ?? 0;

    // Query events
    $sql = $wpdb->prepare(
        "SELECT * FROM $table_name ORDER BY start DESC LIMIT %d OFFSET %d",
        $limit,
        $offset
    );

    $events = $wpdb->get_results($sql, ARRAY_A);

    $formatted_events = [];
    foreach ($events as $event) {
        $formatted = ai1ec_rest_format_event($event);
        if ($formatted) {
            $formatted_events[] = $formatted;
        }
    }

    return rest_ensure_response([
        'success' => true,
        'events' => $formatted_events,
        'count' => count($formatted_events),
    ]);
}

// Get single event
function ai1ec_rest_get_event($request) {
    global $wpdb;

    $post_id = intval($request['id']);
    $table_name = $wpdb->prefix . 'ai1ec_events';

    $event = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_name WHERE post_id = %d", $post_id),
        ARRAY_A
    );

    if (!$event) {
        return new WP_Error('not_found', 'Event not found', ['status' => 404]);
    }

    $formatted = ai1ec_rest_format_event($event);

    return rest_ensure_response($formatted);
}

// Create event
function ai1ec_rest_create_event($request) {
    global $wpdb;

    $params = $request->get_json_params();

    // Validate required fields
    if (empty($params['title'])) {
        return new WP_Error('missing_title', 'Event title is required', ['status' => 400]);
    }
    if (empty($params['start_date'])) {
        return new WP_Error('missing_start', 'Start date is required', ['status' => 400]);
    }

    // Create WordPress post
    $post_data = [
        'post_title' => sanitize_text_field($params['title']),
        'post_content' => wp_kses_post($params['description'] ?? ''),
        'post_status' => $params['status'] ?? 'draft',
        'post_type' => 'ai1ec_event',
    ];

    $post_id = wp_insert_post($post_data);

    if (is_wp_error($post_id) || $post_id === 0) {
        return new WP_Error('creation_failed', 'Failed to create event', ['status' => 500]);
    }

    // Parse dates
    $start_timestamp = strtotime($params['start_date']);
    $end_timestamp = isset($params['end_date']) ? strtotime($params['end_date']) : $start_timestamp + 3600;

    // Insert into ai1ec_events table
    $table_name = $wpdb->prefix . 'ai1ec_events';
    $event_data = [
        'post_id' => $post_id,
        'start' => $start_timestamp,
        'end' => $end_timestamp,
        'timezone_name' => $params['timezone'] ?? '',
        'allday' => isset($params['allday']) ? (int)$params['allday'] : 0,
        'instant_event' => isset($params['instant_event']) ? (int)$params['instant_event'] : 0,
        'venue' => sanitize_text_field($params['venue'] ?? ''),
        'address' => sanitize_text_field($params['address'] ?? ''),
        'city' => sanitize_text_field($params['city'] ?? ''),
        'province' => sanitize_text_field($params['province'] ?? ''),
        'postal_code' => sanitize_text_field($params['postal_code'] ?? ''),
        'country' => sanitize_text_field($params['country'] ?? ''),
        'cost' => sanitize_text_field($params['cost'] ?? ''),
        'contact_name' => sanitize_text_field($params['contact_name'] ?? ''),
        'contact_phone' => sanitize_text_field($params['contact_phone'] ?? ''),
        'contact_email' => sanitize_email($params['contact_email'] ?? ''),
        'contact_url' => esc_url_raw($params['contact_url'] ?? ''),
        'ticket_url' => esc_url_raw($params['ticket_url'] ?? ''),
        'show_map' => isset($params['show_map']) ? (int)$params['show_map'] : 1,
    ];

    $result = $wpdb->insert($table_name, $event_data);

    if ($result === false) {
        wp_delete_post($post_id, true);
        return new WP_Error('creation_failed', 'Failed to create event data', ['status' => 500]);
    }

    // Set categories if provided
    if (!empty($params['categories']) && is_array($params['categories'])) {
        wp_set_post_terms($post_id, $params['categories'], 'events_categories');
    }

    // Set tags if provided
    if (!empty($params['tags']) && is_array($params['tags'])) {
        wp_set_post_terms($post_id, $params['tags'], 'events_tags');
    }

    // Get created event
    $created_event = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_name WHERE post_id = %d", $post_id),
        ARRAY_A
    );

    return rest_ensure_response([
        'success' => true,
        'event_id' => $post_id,
        'event' => ai1ec_rest_format_event($created_event),
        'message' => 'Event created successfully',
    ]);
}

// Update event
function ai1ec_rest_update_event($request) {
    global $wpdb;

    $post_id = intval($request['id']);
    $params = $request->get_json_params();

    $table_name = $wpdb->prefix . 'ai1ec_events';

    // Check if event exists
    $event = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_name WHERE post_id = %d", $post_id),
        ARRAY_A
    );

    if (!$event) {
        return new WP_Error('not_found', 'Event not found', ['status' => 404]);
    }

    // Update post if title or description provided
    $post_update = [];
    if (isset($params['title'])) {
        $post_update['post_title'] = sanitize_text_field($params['title']);
    }
    if (isset($params['description'])) {
        $post_update['post_content'] = wp_kses_post($params['description']);
    }
    if (isset($params['status'])) {
        $post_update['post_status'] = sanitize_text_field($params['status']);
    }

    if (!empty($post_update)) {
        $post_update['ID'] = $post_id;
        wp_update_post($post_update);
    }

    // Update event data
    $event_update = [];
    if (isset($params['start_date'])) {
        $event_update['start'] = strtotime($params['start_date']);
    }
    if (isset($params['end_date'])) {
        $event_update['end'] = strtotime($params['end_date']);
    }
    if (isset($params['timezone'])) {
        $event_update['timezone_name'] = sanitize_text_field($params['timezone']);
    }
    if (isset($params['allday'])) {
        $event_update['allday'] = (int)$params['allday'];
    }
    if (isset($params['venue'])) {
        $event_update['venue'] = sanitize_text_field($params['venue']);
    }
    if (isset($params['address'])) {
        $event_update['address'] = sanitize_text_field($params['address']);
    }
    if (isset($params['city'])) {
        $event_update['city'] = sanitize_text_field($params['city']);
    }
    if (isset($params['province'])) {
        $event_update['province'] = sanitize_text_field($params['province']);
    }
    if (isset($params['postal_code'])) {
        $event_update['postal_code'] = sanitize_text_field($params['postal_code']);
    }
    if (isset($params['country'])) {
        $event_update['country'] = sanitize_text_field($params['country']);
    }
    if (isset($params['cost'])) {
        $event_update['cost'] = sanitize_text_field($params['cost']);
    }
    if (isset($params['contact_name'])) {
        $event_update['contact_name'] = sanitize_text_field($params['contact_name']);
    }
    if (isset($params['contact_phone'])) {
        $event_update['contact_phone'] = sanitize_text_field($params['contact_phone']);
    }
    if (isset($params['contact_email'])) {
        $event_update['contact_email'] = sanitize_email($params['contact_email']);
    }
    if (isset($params['contact_url'])) {
        $event_update['contact_url'] = esc_url_raw($params['contact_url']);
    }
    if (isset($params['ticket_url'])) {
        $event_update['ticket_url'] = esc_url_raw($params['ticket_url']);
    }
    if (isset($params['show_map'])) {
        $event_update['show_map'] = (int)$params['show_map'];
    }

    if (!empty($event_update)) {
        $wpdb->update(
            $table_name,
            $event_update,
            ['post_id' => $post_id]
        );
    }

    // Update categories if provided
    if (isset($params['categories']) && is_array($params['categories'])) {
        wp_set_post_terms($post_id, $params['categories'], 'events_categories');
    }

    // Update tags if provided
    if (isset($params['tags']) && is_array($params['tags'])) {
        wp_set_post_terms($post_id, $params['tags'], 'events_tags');
    }

    // Get updated event
    $updated_event = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_name WHERE post_id = %d", $post_id),
        ARRAY_A
    );

    return rest_ensure_response([
        'success' => true,
        'event' => ai1ec_rest_format_event($updated_event),
        'message' => 'Event updated successfully',
    ]);
}

// Delete event
function ai1ec_rest_delete_event($request) {
    global $wpdb;

    $post_id = intval($request['id']);
    $table_name = $wpdb->prefix . 'ai1ec_events';

    // Check if event exists
    $event = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_name WHERE post_id = %d", $post_id),
        ARRAY_A
    );

    if (!$event) {
        return new WP_Error('not_found', 'Event not found', ['status' => 404]);
    }

    // Delete from ai1ec_events table first
    $wpdb->delete($table_name, ['post_id' => $post_id]);

    // Delete WordPress post (this also deletes terms)
    $result = wp_delete_post($post_id, true);

    if (!$result) {
        return new WP_Error('deletion_failed', 'Failed to delete event', ['status' => 500]);
    }

    return rest_ensure_response([
        'success' => true,
        'message' => 'Event deleted successfully',
    ]);
}

// Get categories
function ai1ec_rest_get_categories($request) {
    $terms = get_terms([
        'taxonomy' => 'events_categories',
        'hide_empty' => false,
    ]);

    if (is_wp_error($terms)) {
        return new WP_Error('query_failed', 'Failed to get categories', ['status' => 500]);
    }

    $categories = [];
    foreach ($terms as $term) {
        $categories[] = [
            'id' => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
            'description' => $term->description,
            'count' => $term->count,
        ];
    }

    return rest_ensure_response([
        'success' => true,
        'categories' => $categories,
        'count' => count($categories),
    ]);
}
