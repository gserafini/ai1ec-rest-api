=== All-in-One Event Calendar REST API ===
Contributors: gabrielserafini
Tags: events, rest-api, all-in-one-event-calendar, api, calendar
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.2.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

REST API endpoints for All-in-One Event Calendar by Time.ly plugin (legacy v3.x).

== Description ==

REST API endpoints for the legacy All-in-One Event Calendar by Time.ly (version 3.x).

Since Time.ly discontinued the free plugin, this helps maintain event management for legacy installations.

= Features =

* Full CRUD operations for events
* Venue and location management
* Category management
* WordPress application password authentication
* Automatic `ai1ec_event_instances` record creation (required for calendar/widget display)
* Reliable multisite category assignment (direct SQL avoids taxonomy conflicts)

== Installation ==

1. Download the plugin ZIP from GitHub releases
2. Upload to WordPress via Plugins → Add New → Upload Plugin
3. Activate the plugin
4. Requires All-in-One Event Calendar v3.x to be installed

== Frequently Asked Questions ==

= Which version of All-in-One Event Calendar is supported? =

This plugin is designed for the legacy version 3.x of All-in-One Event Calendar by Time.ly.

== Changelog ==

= 1.2.0 =
* Feature: Automatically create ai1ec_event_instances records on event creation (fixes events not appearing on calendar/widgets)
* Feature: Sync event_instances when dates are updated
* Feature: Clean up event_instances on event deletion
* Feature: New ai1ec_rest_set_event_categories() for reliable multisite category assignment using direct SQL with correct term_taxonomy_id lookup
* Fix: wp_set_object_terms() could assign wrong taxonomy on multisite (e.g., category instead of events_categories)

= 1.1.2 =
* Security: Add post_status validation for event creation
* Tested up to WordPress 6.9
* Coding standards: PHPCS WordPress-Extra formatting improvements

= 1.1.1 =
* Maintenance release with minor fixes

= 1.0.0 - 2025-10-30 =
* Initial release
* Full CRUD operations for events
* Category listing
* Direct database access for performance
