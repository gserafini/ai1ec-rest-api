# All-in-One Event Calendar REST API

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.2%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPLv2%20or%20later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/github/v/release/gserafini/ai1ec-rest-api)](https://github.com/gserafini/ai1ec-rest-api/releases)

REST API endpoints for All-in-One Event Calendar by Time.ly plugin (legacy version 3.x).

## Overview

This plugin provides RESTful API endpoints for the legacy All-in-One Event Calendar by Time.ly (version 3.x). Since Time.ly discontinued the free plugin and it's no longer available in the WordPress plugin directory, this API plugin helps maintain and automate event management for sites still using the legacy version.

**Note**: This plugin is designed for legacy All-in-One Event Calendar v3.x installations.

## Features

* Full CRUD operations for events
* Venue and location management
* Category management
* Contact information support
* WordPress application password authentication
* Git Updater support for automatic updates
* Automatic `ai1ec_event_instances` record creation (v1.2.0+) - required for calendar and widget display
* Reliable multisite category assignment (v1.2.0+) - uses direct SQL to avoid taxonomy conflicts

## Multisite Support

On WordPress multisite, the same term (e.g., "Lectures") can exist in multiple taxonomies with different `term_taxonomy_id` values. The standard `wp_set_object_terms()` function may resolve to the wrong taxonomy.

Starting with v1.2.0, this plugin uses direct SQL with `term_taxonomy_id` lookup specifically for the `events_categories` taxonomy, ensuring correct category assignment on multisite installations.

AI1EC also requires records in both `ai1ec_events` and `ai1ec_event_instances` tables for events to appear on calendar pages and agenda widgets. v1.2.0+ handles this automatically on create, update, and delete.

## API Endpoints

Base URL: `https://your-site.com/wp-json/ai1ec/v1`

- `GET /ai1ec_api_events` - List events
- `GET /ai1ec_api_events/{id}` - Get single event
- `POST /ai1ec_api_events` - Create event
- `POST /ai1ec_api_events/{id}` - Update event
- `DELETE /ai1ec_api_events/{id}` - Delete event
- `GET /ai1ec_api_categories` - List categories

See full documentation at [https://github.com/gserafini/ai1ec-rest-api](https://github.com/gserafini/ai1ec-rest-api)

## License

GPL v2 or later
