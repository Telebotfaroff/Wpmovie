# Pirate69 WordPress Theme

Presentation theme for the Pirate69 movie and TV catalog.

## Features
- Dark cinematic responsive design and hybrid hero/catalog homepage.
- Responsive movie and series cards and detailed single-title layouts.
- Download links grouped by quality, with server, size and audio metadata.
- Theme customization for accent color, homepage hero and catalog page size.
- No streaming/player functionality.

## Required companion plugin
Install and activate **Pirate69 Movie Manager** from the `pirate69-movie-manager/` directory in this repository. The plugin owns:
- Movie and TV Series post types and genre/language/industry/quality taxonomies.
- GitHub JSON importer, resumable queue, retries, progress and logs.
- Download-link storage and schema migrations.
- Daily scheduled sync and importer settings.

Keeping content and importer logic in the plugin means switching or updating the theme will not disable catalog registration or scheduled imports.

## Install
1. Install and activate the Pirate69 Movie Manager plugin.
2. Zip the `pirate69-theme` directory and upload it under Appearance > Themes > Add New.
3. Activate Pirate69.
4. Open Pirate69 Importer to configure the source and queue imports.

## Requirements
WordPress 6.2+, PHP 7.4+, MySQL 5.7+ or MariaDB 10.4+.

## Import source
Default source is `7xmovies/database` on branch `main`. Configure GitHub owner, repository, branch, timeout, batch size and poster import in Pirate69 Importer > Settings. The importer reads the index/chunk JSON files and processes bounded batches through WP-Cron. On low-traffic sites configure a server cron to request `wp-cron.php` periodically.

## Existing installations
The plugin uses the existing `p69_movie`, `p69_tv_show`, taxonomy names and `{prefix}p69_*` table names. Existing catalog posts, metadata and download-link rows are retained. Activate the plugin before switching themes or running imports. Do not delete the old theme database tables.

## Validation
Source is committed to GitHub. Run the PHP checks workflow and test activation on staging before bulk import; no live WordPress runtime test has been performed here.
