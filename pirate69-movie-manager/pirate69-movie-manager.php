<?php
/**
 * Plugin Name: Pirate69 Movie Manager
 * Description: Movie catalog content types, GitHub JSON imports, download-link storage and scheduled sync for Pirate69.
 * Version: 2.5.2
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Pirate69
 * Text Domain: pirate69-movie-manager
 */
defined('ABSPATH') || exit;

define('P69MM_VERSION', '2.5.2');
define('P69MM_FILE', __FILE__);
define('P69MM_DIR', plugin_dir_path(__FILE__));

require_once P69MM_DIR . 'includes/database.php';
require_once P69MM_DIR . 'includes/post-types.php';
require_once P69MM_DIR . 'includes/github-client.php';
require_once P69MM_DIR . 'includes/import-engine.php';
require_once P69MM_DIR . 'includes/poster-manager.php';
require_once P69MM_DIR . 'includes/tmdb.php';
require_once P69MM_DIR . 'includes/github-updater.php';

if (is_admin()) {
    require_once P69MM_DIR . 'admin/importer.php';
    require_once P69MM_DIR . 'admin/settings.php';
}

function p69mm_activate() {
    pirate69_install_schema();
    pirate69_register_content_types();
    if (!wp_next_scheduled('pirate69_daily_sync')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'pirate69_daily_sync');
    }
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'p69mm_activate');

function p69mm_deactivate() {
    $timestamp = wp_next_scheduled('pirate69_daily_sync');
    if ($timestamp) wp_unschedule_event($timestamp, 'pirate69_daily_sync');
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'p69mm_deactivate');

function p69mm_admin_notice_missing_theme_support() {
    if (!current_user_can('manage_options') || !function_exists('get_template')) return;
    if (get_template() !== 'pirate69-theme') return;
    if (!post_type_exists('p69_movie')) {
        echo '<div class="notice notice-warning"><p><strong>Pirate69 Movie Manager:</strong> Movie catalog features are unavailable. Please activate the Pirate69 Movie Manager plugin.</p></div>';
    }
}
add_action('admin_notices', 'p69mm_admin_notice_missing_theme_support');
