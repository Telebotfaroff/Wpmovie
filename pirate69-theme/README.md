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