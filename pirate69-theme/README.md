# Pirate69 WordPress Theme

Standalone WordPress theme for the Pirate69 movie and TV catalog.

## Sprint 1
- Dark cinematic responsive design and hybrid hero/catalog homepage.
- Movie and TV Series post types.
- Genre, language, industry and quality taxonomies.
- Initial appearance settings.
- Versioned custom tables for download links, import jobs and logs.
- No streaming/player functionality and no required companion plugin.

## Sprint 2 importer
- Reads the public JSON index and matching category chunk files from GitHub.
- Categories: Hollywood, Bollywood and X-Hub.
- Stores source IDs and SHA-256 record fingerprints; unchanged records skip post/link writes.
- Chunk responses are cached briefly so batches from the same chunk do not repeatedly download the JSON.
- Each cron invocation processes a bounded batch and persists its cursor.
- Retries temporary source failures up to three times.
- Prevents overlapping workers for the same job.
- Stores imported download links separately from manual links.
- Downloads poster images into the Media Library when enabled, validates image type and limits files to 8 MB.
- Manual start, pause, resume, retry, job progress and activity logs in wp-admin.
- Daily sync queues all configured categories. WP-Cron requires site traffic; configure a real cron ping to wp-cron.php on low-traffic sites.
- Frontend page views never fetch source JSON.

## Requirements
WordPress 6.2+, PHP 8.1+, MySQL 5.7+ or MariaDB 10.4+.

## Install
Upload the pirate69-theme directory as a theme, or zip that directory and upload it through Appearance > Themes > Add New. Activate Pirate69. Open Appearance > Pirate69 Settings and Pirate69 Importer.

## Source defaults
Owner: 7xmovies
Repository: database
Branch: main

The source schema is documented in the 7xmovies/database repository. Index records map IDs to chunk numbers; chunk objects contain detailed metadata and downloadLinks.

## Data model note
Post types are registered by the theme. WordPress content remains in the database if the theme is switched, but the post types are registered only while Pirate69 is active.
