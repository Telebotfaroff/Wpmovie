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

	foreach ($sql as $statement) {
		dbDelta($statement);
	}
	update_option('pirate69_db_version', '1.0.1', false);
}

function pirate69_maybe_upgrade_schema() {
	if (get_option('pirate69_db_version') !== '1.0.1') {
		pirate69_install_schema();
	}
}
add_action('after_switch_theme', 'pirate69_install_schema');
add_action('admin_init', 'pirate69_maybe_upgrade_schema');
