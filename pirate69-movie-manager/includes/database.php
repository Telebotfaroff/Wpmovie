<?php
defined('ABSPATH') || exit;

function pirate69_install_schema() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	$links = $wpdb->prefix . 'p69_download_links';
	$jobs = $wpdb->prefix . 'p69_import_jobs';
	$logs = $wpdb->prefix . 'p69_import_logs';

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
		cursor varchar(191) NOT NULL DEFAULT '',
		total_items int(10) unsigned NOT NULL DEFAULT 0,
		processed_items int(10) unsigned NOT NULL DEFAULT 0,
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

	$schema_errors = array();
	foreach ($sql as $statement) {
		$changes = dbDelta($statement);
		if (!empty($wpdb->last_error)) {
			$schema_errors[] = $wpdb->last_error;
		}
		// dbDelta can report no changes without creating a missing table on
		// hosts with unusual MySQL compatibility. Fall back to native CREATE.
		if (preg_match('/^CREATE TABLE ([^ ]+)/i', $statement, $match)) {
			$table_name = trim($match[1], "`");
			$found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table_name)));
			if ($found !== $table_name) {
				$create_sql = preg_replace('/^CREATE TABLE /i', 'CREATE TABLE IF NOT EXISTS ', $statement);
				$result = $wpdb->query($create_sql);
				if (false === $result && !empty($wpdb->last_error)) {
					$schema_errors[] = $table_name . ': ' . $wpdb->last_error;
				}
			}
		}
	}
	update_option('pirate69_db_version', '1.0.3', false);
	update_option('pirate69_schema_errors', $schema_errors, false);
}

function pirate69_schema_is_ready() {
	global $wpdb;
	$tables = array(
		$wpdb->prefix . 'p69_download_links',
		$wpdb->prefix . 'p69_import_jobs',
		$wpdb->prefix . 'p69_import_logs',
	);
	foreach ($tables as $table) {
		$found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
		if ($found !== $table) return false;
	}
	return true;
}

function pirate69_ensure_schema() {
	if (get_option('pirate69_db_version') !== '1.0.3' || !pirate69_schema_is_ready()) {
		pirate69_install_schema();
	}
	if (!pirate69_schema_is_ready()) {
		global $wpdb;
		$missing = array();
		foreach (array('p69_download_links', 'p69_import_jobs', 'p69_import_logs') as $suffix) {
			$table = $wpdb->prefix . $suffix;
			$found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
			if ($found !== $table) $missing[] = $table;
		}
		$details = get_option('pirate69_schema_errors', array());
		$detail_text = $details ? implode(' | ', array_map('sanitize_text_field', $details)) : ($wpdb->last_error ?: 'MySQL returned no error; verify that the database user has CREATE and ALTER privileges.');
		return new WP_Error(
			'p69_schema_missing',
			'Pirate69 could not create these database tables: ' . implode(', ', $missing) . '. Details: ' . $detail_text
		);
	}
	return true;
}

function pirate69_maybe_upgrade_schema() {
	pirate69_ensure_schema();
}
add_action('after_switch_theme', 'pirate69_install_schema');
add_action('admin_init', 'pirate69_maybe_upgrade_schema');
