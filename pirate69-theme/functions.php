<?php
defined('ABSPATH') || exit;

if (!defined('PIRATE69_VERSION')) define('PIRATE69_VERSION', '1.0.2');
if (!defined('PIRATE69_DIR')) define('PIRATE69_DIR', get_template_directory());
if (!defined('PIRATE69_URI')) define('PIRATE69_URI', get_template_directory_uri());

require_once PIRATE69_DIR . '/inc/setup.php';
require_once PIRATE69_DIR . '/inc/assets.php';
require_once PIRATE69_DIR . '/inc/theme-options.php';

/* Content types, importer jobs, database tables and cron now belong to
 * the Pirate69 Movie Manager plugin so catalog data survives theme changes.
 */
