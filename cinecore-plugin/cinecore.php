<?php
/**
 * Plugin Name:       CineCore Core System
 * Plugin URI:        https://nexcine.com/
 * Description:       Core system for Nexcine movie & TV series ecosystem. Modular architecture, handles CPTs, taxonomies, TMDB, Telegram, search, filter, subtitles, SEO, link checking.
 * Version:           2.0.0
 * Author:            Nexcine
 * Author URI:        https://nexcine.com/
 * Text Domain:       cinecore
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package CineCore
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

// Constants.
define( 'CINECORE_VERSION', '2.0.0' );
define( 'CINECORE_DB_VERSION', '2.0' );
define( 'CINECORE_PLUGIN_FILE', __FILE__ );
define( 'CINECORE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CINECORE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load core modules - order matters (meta/cache first).
require_once CINECORE_PLUGIN_DIR . 'includes/class-cinecore-meta.php';
require_once CINECORE_PLUGIN_DIR . 'includes/class-cinecore-cache.php';
require_once CINECORE_PLUGIN_DIR . 'includes/class-cinecore-media.php';
require_once CINECORE_PLUGIN_DIR . 'includes/class-cinecore-migrations.php';
require_once CINECORE_PLUGIN_DIR . 'includes/class-cinecore-upvotes.php';
require_once CINECORE_PLUGIN_DIR . 'includes/class-cinecore-core.php';
require_once CINECORE_PLUGIN_DIR . 'includes/class-cinecore-post-types.php';
require_once CINECORE_PLUGIN_DIR . 'includes/class-cinecore-taxonomies.php';
require_once CINECORE_PLUGIN_DIR . 'includes/class-cinecore-settings.php';
require_once CINECORE_PLUGIN_DIR . 'includes/class-cinecore-tmdb.php';
require_once CINECORE_PLUGIN_DIR . 'includes/class-cinecore-telegram.php';
require_once CINECORE_PLUGIN_DIR . 'includes/class-cinecore-search.php';
require_once CINECORE_PLUGIN_DIR . 'includes/class-cinecore-filter.php';
require_once CINECORE_PLUGIN_DIR . 'includes/class-cinecore-subtitles.php';
require_once CINECORE_PLUGIN_DIR . 'includes/class-cinecore-link-checker.php';
require_once CINECORE_PLUGIN_DIR . 'includes/class-cinecore-seo.php';

// Admin Content Manager (loaded only in admin).
if ( is_admin() ) {
	require_once CINECORE_PLUGIN_DIR . 'admin/class-cinecore-admin.php';
	require_once CINECORE_PLUGIN_DIR . 'admin/class-cinecore-movie-manager.php';
	require_once CINECORE_PLUGIN_DIR . 'admin/class-cinecore-tv-manager.php';
	// Ensure admin assets directory exists.
}

/**
 * Main bootstrap.
 *
 * @since 2.0.0
 * @return CineCore
 */
function cinecore() {
	return CineCore::get_instance();
}

// Initialize - core handles CPT/tax + instantiates all modules.
cinecore();

// Migrations - run on init (idempotent).
add_action( 'init', array( 'CineCore_Migrations', 'init' ), 20 );

// Activation.
function cinecore_activate() {
	// Ensure CPTs registered before flush.
	$core = CineCore::get_instance();
	$core->register_post_types();
	$core->register_taxonomies();

	// Subtitle CPT + role.
	$sub = new CineCore_Subtitle_Ecosystem();
	$sub->register_cpt();
	$sub->ensure_role();

	// Upvotes table.
	CineCore_Upvotes::create_table();

	// DB version.
	update_option( 'cinecore_db_version', CINECORE_DB_VERSION, false );

	// Cron.
	CineCore_Dead_Link_Checker::schedule();

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'cinecore_activate' );

// Deactivation.
function cinecore_deactivate() {
	flush_rewrite_rules();
	CineCore_Dead_Link_Checker::unschedule();
}
register_deactivation_hook( __FILE__, 'cinecore_deactivate' );
