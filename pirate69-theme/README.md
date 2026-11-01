# Pirate69 WordPress Theme

Standalone WordPress theme for the Pirate69 movie and TV catalog.

## Features
- Dark cinematic responsive design and hybrid hero/catalog homepage.
- Movie and TV Series post types.
- Genre, language, industry and quality taxonomies.
- Detailed movie and series pages with poster, year, quality, catalog ID, taxonomy terms and source-page link.
- Download links grouped by quality, with server, size and audio metadata.
- Download links are read from the theme's custom table; imported and manually added links can coexist.
- GitHub JSON importer with index/chunk reader, incremental fingerprint checks, resumable batch jobs, retries, poster sideloading and admin controls.
- No streaming/player functionality and no required companion plugin.

## Requirements
WordPress 6.2+, PHP 8.1+, MySQL 5.7+ or MariaDB 10.4+.

## Install
Upload the `pirate69-theme` directory as a theme, or zip that directory and upload it through Appearance > Themes > Add New. Activate Pirate69, then open Appearance > Pirate69 Settings and Pirate69 Importer.

Activation creates the versioned tables and schedules daily sync. Theme upgrades do not delete existing content.

## Import source
Default source is `7xmovies/database` on the `main` branch. Configure owner, repository, branch, timeout, batch size and poster import under the theme settings. Start category imports from Pirate69 Importer.

The importer processes a bounded batch per WP-Cron invocation. On low-traffic sites, configure a real server cron to request `wp-cron.php` periodically so queued imports progress reliably.

## Download links
The single movie/series template queries enabled rows from `{prefix}p69_download_links`. Links are grouped by quality and display label, server, file size and audio metadata when present. Only enabled links are shown. The importer replaces rows marked `origin=imported` and preserves rows marked `origin=manual`.

## Data behavior
Movie and series records are registered by the theme. Their database content remains if switching themes, but the post types and theme-specific download tables are intended to be used while Pirate69 is active. Back up the WordPress database and uploads directory before bulk imports.

## Validation status
Source files are committed to GitHub. A live WordPress/PHP runtime test has not yet been performed; test on a staging WordPress site before running the first full catalog sync.
