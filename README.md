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
