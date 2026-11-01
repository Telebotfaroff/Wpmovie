<?php
defined('ABSPATH') || exit;

/**
 * Create or add columns to Pirate69 tables.
 *
 * This is intentionally additive: it never drops tables or existing rows.
 * Keep one column per line because WordPress dbDelta parses this SQL format.
 */
function pirate69_install_schema() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset = $wpdb->get_charset_collate();
	$links = $wpdb->prefix . 'p69_download_links';
	$jobs  = $wpdb->prefix . 'p69_import_jobs';
	$logs  = $wpdb->prefix . 'p69_import_logs';
	$source_map = $wpdb->prefix . 'p69_source_map';
	$posters = $wpdb->prefix . 'p69_poster_queue';

	$sql = array();

	$sql[] = "CREATE TABLE {$links} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		post_id bigint(20) unsigned NOT NULL,
		source_key varchar(191) NOT NULL DEFAULT '',
		label varchar(191) NOT NULL DEFAULT '',
		url text NOT NULL,
		quality varchar(80) NOT NULL DEFAULT '',
		file_size varchar(80) NOT NULL DEFAULT '',
		audio varchar(120) NOT NULL DEFAULT '',
		server varchar(120) NOT NULL DEFAULT '',
		season smallint(5) unsigned NOT NULL DEFAULT 0,
		episode varchar(40) NOT NULL DEFAULT '',
		is_batch tinyint(1) NOT NULL DEFAULT 0,
		sort_order int(11) NOT NULL DEFAULT 0,
		is_enabled tinyint(1) NOT NULL DEFAULT 1,
		origin varchar(20) NOT NULL DEFAULT 'manual',
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY post_id (post_id),
		KEY source_key (source_key),
		KEY post_enabled (post_id,is_enabled)
	) {$charset};";

	$sql[] = "CREATE TABLE {$jobs} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		job_type varchar(40) NOT NULL DEFAULT 'sync',
		category varchar(80) NOT NULL DEFAULT '',
		status varchar(24) NOT NULL DEFAULT 'pending',
		total_items int(10) unsigned NOT NULL DEFAULT 0,
		processed_items int(10) unsigned NOT NULL DEFAULT 0,
		created_count int(10) unsigned NOT NULL DEFAULT 0,
		updated_count int(10) unsigned NOT NULL DEFAULT 0,
		skipped_count int(10) unsigned NOT NULL DEFAULT 0,
		error_count int(10) unsigned NOT NULL DEFAULT 0,
		payload longtext NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY status_created (status,created_at),
		KEY category (category)
	) {$charset};";

	$sql[] = "CREATE TABLE {$logs} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		job_id bigint(20) unsigned NOT NULL DEFAULT 0,
		level varchar(20) NOT NULL DEFAULT 'info',
		message text NOT NULL,
		context longtext NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY job_id (job_id),
		KEY level_created (level,created_at)
	) {$charset};";

	$sql[] = "CREATE TABLE {$source_map} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		category varchar(80) NOT NULL DEFAULT '',
		source_id varchar(191) NOT NULL DEFAULT '',
		source_hash char(64) NOT NULL DEFAULT '',
		post_id bigint(20) unsigned NOT NULL,
		fingerprint char(64) NOT NULL DEFAULT '',
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY source_lookup (category,source_hash),
		KEY post_id (post_id)
	) {$charset};";

	$sql[] = "CREATE TABLE {$posters} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		post_id bigint(20) unsigned NOT NULL,
		poster_url text NOT NULL,
		status varchar(20) NOT NULL DEFAULT 'queued',
		attempts smallint(5) unsigned NOT NULL DEFAULT 0,
		last_error text NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY post_id (post_id),
		KEY status_updated (status,updated_at)
	) {$charset};";

	$errors = array();
	foreach ($sql as $statement) {
		$wpdb->last_error = '';
		dbDelta($statement);
		if (!empty($wpdb->last_error)) {
			$errors[] = $wpdb->last_error;
		}
	}

	$missing = array();
	foreach (array($links, $jobs, $logs, $source_map, $posters) as $table) {
		$found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
		if ($found !== $table) {
			$missing[] = $table;
		}
	}

	if ($missing) {
		$errors[] = 'Missing tables: ' . implode(', ', $missing);
	}

	// Verify the additive series columns before marking the migration complete.
	$link_columns = $wpdb->get_col("SHOW COLUMNS FROM {$links}", 0);
	foreach (array('season', 'episode', 'is_batch') as $column) {
		if (!in_array($column, $link_columns, true)) {
			$errors[] = 'Missing download-link column: ' . $column;
		}
	}
	$job_columns = $wpdb->get_col("SHOW COLUMNS FROM {$jobs}", 0);
	foreach (array('created_count', 'updated_count', 'skipped_count') as $column) {
		if (!in_array($column, $job_columns, true)) {
			$errors[] = 'Missing import-job column: ' . $column;
		}
	}

	$errors = array_values(array_unique(array_filter($errors)));
	update_option('pirate69_schema_errors', $errors, false);
	if (!$errors) {
		update_option('pirate69_db_version', '2.2.0', false);
	}
	return empty($errors);
}

function pirate69_schema_is_ready() {
	global $wpdb;
	foreach (array('p69_download_links', 'p69_import_jobs', 'p69_import_logs', 'p69_source_map', 'p69_poster_queue') as $suffix) {
		$table = $wpdb->prefix . $suffix;
		$found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
		if ($found !== $table) {
			return false;
		}
	}
	return true;
}

function pirate69_ensure_schema() {
	$version = get_option('pirate69_db_version');
	if ($version !== '2.2.0' || !pirate69_schema_is_ready()) {
		pirate69_install_schema();
	}

	if (!pirate69_schema_is_ready() || get_option('pirate69_db_version') !== '2.2.0') {
		$errors = get_option('pirate69_schema_errors', array());
		$message = $errors
			? implode(' | ', array_map('sanitize_text_field', $errors))
			: 'The database tables or required columns are not available.';
		return new WP_Error('p69_schema_missing', 'Pirate69 schema migration is incomplete. ' . $message);
	}
	return true;
}
add_action('admin_init', 'pirate69_ensure_schema');
