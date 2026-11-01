# Pirate69 WordPress Theme

Standalone WordPress theme for the Pirate69 movie and TV catalog.

## Sprint 1
- Dark cinematic responsive design and hybrid hero/catalog homepage.
- Movie and TV Series post types.
- Genre, language, industry and quality taxonomies.
- Initial appearance settings.
- Versioned custom tables for download links, import jobs and logs.
- No streaming/player functionality and no required companion plugin.

## Requirements
WordPress 6.2+, PHP 8.1+, MySQL 5.7+ or MariaDB 10.4+.

## Install
Upload the pirate69-theme directory as a theme, or zip that directory and upload it through Appearance > Themes > Add New. Activate Pirate69, then open Appearance > Pirate69 Settings.

Activation creates the versioned tables. Theme upgrades do not delete existing content.

## Next sprint
GitHub JSON source configuration, index/chunk reader, incremental resumable import queue, poster sideloading into the Media Library and importer dashboard.

Post types are registered by the theme as requested. Their database content remains if switching themes, but the post types are only registered while Pirate69 is active.

## JSON importer (Sprint 2)

Open **Pirate69 Importer** in the WordPress dashboard and queue Hollywood, Bollywood or X-Hub. Jobs read the matching `*-index.json` file, then process its numbered chunk files in small batches. Each source ID is stored as post metadata and a SHA-256 record fingerprint avoids rewriting unchanged records. Imported download links are stored in the `wp_p69_download_links` table; manual-origin links are not deleted during sync.

Importer settings are available under **Pirate69 Importer → Settings**. Defaults point to `7xmovies/database` on `main`. Batch size is configurable from 1–30. Poster downloads can be switched off; when enabled, posters are sideloaded to the WordPress Media Library (8 MB maximum, common image formats only). Failed poster imports can be attempted again on a later sync.

A daily WP-Cron event queues all three catalogs. WP-Cron runs when the site receives traffic; for low-traffic sites configure a real server cron to request `wp-cron.php` periodically. Jobs can also be started manually from the importer page. Jobs have persisted state, progress counters, pause/resume/retry controls and logs.

The importer reads JSON only in scheduled/admin background jobs, not on public page views. It does not delete WordPress posts when a source item disappears.