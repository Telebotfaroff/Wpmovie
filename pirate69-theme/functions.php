<?php
defined('ABSPATH') || exit;

if (!defined('PIRATE69_VERSION')) define('PIRATE69_VERSION', '1.0.1');
if (!defined('PIRATE69_DIR')) define('PIRATE69_DIR', get_template_directory());
if (!defined('PIRATE69_URI')) define('PIRATE69_URI', get_template_directory_uri());

require_once PIRATE69_DIR . '/inc/setup.php';
require_once PIRATE69_DIR . '/inc/assets.php';
require_once PIRATE69_DIR . '/inc/post-types.php';
require_once PIRATE69_DIR . '/inc/database.php';
require_once PIRATE69_DIR . '/inc/theme-options.php';

/*
 * Cron callbacks must be registered during WP-Cron requests too.
 * Avoid loading importer code on ordinary public page requests.
 */
if (is_admin() || (defined('DOING_CRON') && DOING_CRON)) {
	require_once PIRATE69_DIR . '/importer/github-client.php';
	require_once PIRATE69_DIR . '/importer/engine.php';
}
if (is_admin()) {
	require_once PIRATE69_DIR . '/admin/importer.php';
	require_once PIRATE69_DIR . '/admin/settings.php';
}
