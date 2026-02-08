<?php
/**
 * Upstream Compatibility Test for All-in-One Event Calendar REST API.
 *
 * Verifies that the upstream AI1EC plugin still provides the database tables,
 * column schemas, post types, and taxonomies that ai1ec-rest-api depends on.
 *
 * Note: AI1EC is DISCONTINUED. This test exists to confirm the installed
 * version hasn't been corrupted or partially uninstalled, and to serve as
 * a compatibility baseline if a fork or replacement emerges.
 *
 * Run inside WordPress via WP-CLI:
 *   wp eval-file tests/test-upstream-compat.php
 *
 * Exit code: 0 = all pass, 1 = failures detected.
 *
 * @package AI1EC_REST_API
 */

// ─── Minimal test harness ───
// Note: wp eval-file includes this file inside a function scope, so top-level
// variables are LOCAL. We must declare them global so the helper functions
// (which also use `global`) share the same counters.

global $compat_passed, $compat_failed, $compat_skipped;
$compat_passed  = 0;
$compat_failed  = 0;
$compat_skipped = 0;

function compat_pass( $msg ) {
	global $compat_passed;
	++$compat_passed;
	echo "  [PASS] $msg\n";
}

function compat_fail( $msg ) {
	global $compat_failed;
	++$compat_failed;
	echo "  [FAIL] $msg\n";
}

function compat_skip( $msg ) {
	global $compat_skipped;
	++$compat_skipped;
	echo "  [SKIP] $msg\n";
}

function assert_true( $condition, $msg ) {
	if ( $condition ) {
		compat_pass( $msg );
	} else {
		compat_fail( $msg );
	}
}

// ─── Load .upstream metadata ───

$upstream_file = dirname( __DIR__ ) . '/.upstream';
if ( ! file_exists( $upstream_file ) ) {
	echo "[ERROR] .upstream file not found at $upstream_file\n";
	exit( 1 );
}

$upstream = json_decode( file_get_contents( $upstream_file ), true );
if ( ! $upstream ) {
	echo "[ERROR] Failed to parse .upstream JSON\n";
	exit( 1 );
}

// ─── Detect installed version ───

$installed_version = 'unknown';
if ( function_exists( 'get_plugins' ) ) {
	$plugins = get_plugins();
	foreach ( $plugins as $file => $data ) {
		if ( strpos( $file, 'all-in-one-event-calendar' ) !== false ) {
			$installed_version = $data['Version'];
			break;
		}
	}
} else {
	$ai1ec_main = WP_PLUGIN_DIR . '/all-in-one-event-calendar/all-in-one-event-calendar.php';
	if ( file_exists( $ai1ec_main ) ) {
		$header = get_file_data( $ai1ec_main, array( 'Version' => 'Version' ) );
		if ( ! empty( $header['Version'] ) ) {
			$installed_version = $header['Version'];
		}
	}
}

echo "\n=== Upstream Compatibility: ai1ec-rest-api ===\n";
echo "Tested against: {$upstream['parent_plugin']} {$upstream['tested_version']}\n";
echo "Installed:      {$upstream['parent_plugin']} $installed_version\n";

if ( ! empty( $upstream['note'] ) ) {
	echo "Note:           {$upstream['note']}\n";
}

if ( $installed_version !== $upstream['tested_version'] ) {
	echo "  ** VERSION DRIFT DETECTED **\n";
}
echo "\n";

// ─── Layer 1: Table existence ───

echo "--- Layer 1: Table Existence ---\n";
global $wpdb;
foreach ( $upstream['tables_used'] as $table ) {
	$full_table = $wpdb->prefix . $table;
	$exists     = $wpdb->get_var( "SHOW TABLES LIKE '$full_table'" );
	assert_true( $exists === $full_table, "Table $full_table exists" );
}
echo "\n";

// ─── Layer 2: Column existence ───

echo "--- Layer 2: Column Schema ---\n";
foreach ( $upstream['table_columns'] as $table => $expected_columns ) {
	$full_table     = $wpdb->prefix . $table;
	$actual_columns = $wpdb->get_col( "DESCRIBE $full_table", 0 );

	if ( empty( $actual_columns ) ) {
		compat_fail( "Could not describe $full_table (table may not exist)" );
		continue;
	}

	foreach ( $expected_columns as $col ) {
		assert_true(
			in_array( $col, $actual_columns, true ),
			"$full_table has column '$col'"
		);
	}
}
echo "\n";

// ─── Layer 3: Post type registration ───

echo "--- Layer 3: Post Type & Taxonomies ---\n";
foreach ( $upstream['post_types_used'] as $post_type ) {
	assert_true(
		post_type_exists( $post_type ),
		"Post type '$post_type' is registered"
	);
}

foreach ( $upstream['taxonomies_used'] as $taxonomy ) {
	assert_true(
		taxonomy_exists( $taxonomy ),
		"Taxonomy '$taxonomy' is registered"
	);
}
echo "\n";

// ─── Layer 4: Taxonomy-post type association ───

echo "--- Layer 4: Taxonomy Associations ---\n";
foreach ( $upstream['taxonomies_used'] as $taxonomy ) {
	if ( taxonomy_exists( $taxonomy ) ) {
		$tax_object  = get_taxonomy( $taxonomy );
		$object_type = $tax_object->object_type;
		assert_true(
			in_array( 'ai1ec_event', $object_type, true ),
			"Taxonomy '$taxonomy' is attached to 'ai1ec_event' post type"
		);
	} else {
		compat_skip( "Taxonomy '$taxonomy' association (taxonomy missing)" );
	}
}
echo "\n";

// ─── Summary ───

global $compat_passed, $compat_failed, $compat_skipped;
$total = $compat_passed + $compat_failed;
echo "=== Results: $compat_passed/$total passed";
if ( $compat_failed > 0 ) {
	echo ", $compat_failed failed";
}
if ( $compat_skipped > 0 ) {
	echo ", $compat_skipped skipped";
}
echo " ===\n\n";

if ( $compat_failed > 0 ) {
	exit( 1 );
}
exit( 0 );
