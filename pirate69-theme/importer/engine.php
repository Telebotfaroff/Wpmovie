<?php
defined('ABSPATH') || exit;

function pirate69_log($job_id, $level, $message, $context = array()) {
	global $wpdb;
	$table = $wpdb->prefix . 'p69_import_logs';
	$wpdb->insert($table, array(
		'job_id' => absint($job_id), 'level' => sanitize_key($level),
		'message' => sanitize_text_field($message),
		'context' => wp_json_encode($context), 'created_at' => current_time('mysql', true),
	), array('%d','%s','%s','%s','%s'));
}

function pirate69_create_import_job($category) {
	global $wpdb;
	$categories = pirate69_source_categories();
	if (!isset($categories[$category])) return new WP_Error('p69_bad_category', 'Unknown catalog category.');
	$table = $wpdb->prefix . 'p69_import_jobs';
	$now = current_time('mysql', true);
	$wpdb->insert($table, array(
		'job_type' => 'sync', 'category' => $category, 'status' => 'pending',
		'cursor' => '', 'total_items' => 0, 'processed_items' => 0, 'error_count' => 0,
		'payload' => wp_json_encode(array('phase' => 'index', 'chunks' => array(), 'chunk_pos' => 0, 'item_pos' => 0, 'index_count' => 0)),
		'created_at' => $now, 'updated_at' => $now,
	));
	$id = (int) $wpdb->insert_id;
	if ($id) {
		pirate69_log($id, 'info', 'Import job created.', array('category' => $category));
		wp_schedule_single_event(time() + 5, 'pirate69_process_import_job', array($id));
	}
	return $id ?: new WP_Error('p69_job_create_failed', 'Could not create import job.');
}

function pirate69_schedule_job($job_id, $delay = 10) {
	if (!wp_next_scheduled('pirate69_process_import_job', array((int) $job_id))) {
		wp_schedule_single_event(time() + max(5, absint($delay)), 'pirate69_process_import_job', array((int) $job_id));
	}
}

function pirate69_process_import_job($job_id) {
	global $wpdb;
	$table = $wpdb->prefix . 'p69_import_jobs';
	$job = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $job_id), ARRAY_A);
	if (!$job || !in_array($job['status'], array('pending','running'), true)) return;
	$wpdb->update($table, array('status' => 'running', 'updated_at' => current_time('mysql', true)), array('id' => $job_id));
	$state = json_decode($job['payload'], true);
	if (!is_array($state)) $state = array();
	$category = $job['category'];
	$categories = pirate69_source_categories();
	if (!isset($categories[$category])) { pirate69_fail_job($job_id, 'Invalid category.'); return; }

	if (($state['phase'] ?? 'index') === 'index') {
		$index = pirate69_fetch_json($categories[$category]['index']);
		if (is_wp_error($index)) { pirate69_fail_job($job_id, $index->get_error_message()); return; }
		$chunks = array();
		foreach ($index as $row) {
			if (!is_array($row) || empty($row['id']) || empty($row['chunk'])) continue;
			$chunks[absint($row['chunk'])] = true;
		}
		$chunk_ids = array_keys($chunks);
		sort($chunk_ids, SORT_NUMERIC);
		$state = array('phase' => 'chunks', 'chunks' => $chunk_ids, 'chunk_pos' => 0, 'item_pos' => 0, 'index_count' => count($index));
		$wpdb->update($table, array('total_items' => count($index), 'payload' => wp_json_encode($state), 'updated_at' => current_time('mysql', true)), array('id' => $job_id));
		pirate69_log($job_id, 'info', 'Index loaded.', array('items' => count($index), 'chunks' => count($chunk_ids)));
		pirate69_schedule_job($job_id, 5);
		return;
	}

	$chunks = $state['chunks'] ?? array();
	$chunk_pos = absint($state['chunk_pos'] ?? 0);
	if (!isset($chunks[$chunk_pos])) {
		$wpdb->update($table, array('status' => 'completed', 'payload' => wp_json_encode($state), 'updated_at' => current_time('mysql', true)), array('id' => $job_id));
		pirate69_log($job_id, 'success', 'Import completed.', array('processed' => (int) $job['processed_items']));
		return;
	}

	$chunk_number = absint($chunks[$chunk_pos]);
	$chunk = pirate69_fetch_json($category . '/chunk-' . $chunk_number . '.json');
	if (is_wp_error($chunk)) { pirate69_fail_job($job_id, $chunk->get_error_message()); return; }
	$records = array_values($chunk);
	$offset = absint($state['item_pos'] ?? 0);
	$settings = pirate69_source_config()['settings'];
	$batch_size = max(1, min(30, absint($settings['batch_size'])));
	$batch = array_slice($records, $offset, $batch_size);
	$processed = 0;
	$errors = 0;
	foreach ($batch as $record) {
		if (!is_array($record) || empty($record['id'])) continue;
		$result = pirate69_import_record($record, $category);
		if (is_wp_error($result)) {
			$errors++;
			pirate69_log($job_id, 'error', $result->get_error_message(), array('source_id' => $record['id']));
		}
		$processed++;
	}
	$offset += count($batch);
	if ($offset >= count($records)) { $state['chunk_pos'] = $chunk_pos + 1; $state['item_pos'] = 0; }
	else { $state['item_pos'] = $offset; }
	$wpdb->query($wpdb->prepare("UPDATE {$table} SET processed_items = processed_items + %d, error_count = error_count + %d, payload = %s, updated_at = %s WHERE id = %d", $processed, $errors, wp_json_encode($state), current_time('mysql', true), $job_id));
	pirate69_schedule_job($job_id, 5);
}

function pirate69_fail_job($job_id, $message) {
	global $wpdb;
	$wpdb->update($wpdb->prefix . 'p69_import_jobs', array('status' => 'failed', 'updated_at' => current_time('mysql', true)), array('id' => $job_id));
	pirate69_log($job_id, 'error', $message);
}

function pirate69_import_record($record, $category) {
	global $wpdb;
	$source_id = sanitize_text_field($record['id'] ?? '');
	if (!$source_id) return new WP_Error('p69_missing_id', 'Record has no source ID.');
	$post_type = !empty($record['isSeries']) ? 'p69_tv_show' : 'p69_movie';
	$title = sanitize_text_field($record['cleanTitle'] ?? $record['fullTitle'] ?? $record['title'] ?? $source_id);
	$fingerprint = hash('sha256', wp_json_encode($record));
	$existing = get_posts(array('post_type' => array('p69_movie','p69_tv_show'), 'post_status' => array('publish','draft','private'), 'meta_key' => '_p69_source_id', 'meta_value' => $source_id, 'numberposts' => 1, 'fields' => 'ids'));
	$post_id = $existing ? (int) $existing[0] : 0;
	$old_fingerprint = $post_id ? get_post_meta($post_id, '_p69_source_fingerprint', true) : '';
	if ($post_id && hash_equals((string) $old_fingerprint, $fingerprint)) {
		$poster_settings = pirate69_source_config()['settings'];
		if (!empty($poster_settings['poster_import']) && !has_post_thumbnail($post_id) && !empty($record['poster'])) {
			$poster_result = pirate69_import_poster($post_id, $record['poster']);
			if (is_wp_error($poster_result)) update_post_meta($post_id, '_p69_poster_status', 'failed'); else update_post_meta($post_id, '_p69_poster_status', 'downloaded');
		}
		return $post_id;
	}

	$post_data = array('post_type' => $post_type, 'post_status' => 'publish');
	$imported_title = $post_id ? get_post_meta($post_id, '_p69_imported_title', true) : '';
	if (!$post_id || get_the_title($post_id) === $imported_title) {
		$post_data['post_title'] = $title;
	}
	if ($post_id) {
		$post_data['ID'] = $post_id;
		$updated = wp_update_post(wp_slash($post_data), true);
	} else {
		$updated = wp_insert_post(wp_slash($post_data), true);
	}
	if (is_wp_error($updated)) return $updated;
	$post_id = (int) $updated;
	update_post_meta($post_id, '_p69_source_id', $source_id);
	update_post_meta($post_id, '_p69_imported_title', $title);
	update_post_meta($post_id, '_p69_source_category', $category);
	update_post_meta($post_id, '_p69_source_url', esc_url_raw($record['sourceUrl'] ?? ''));
	update_post_meta($post_id, '_p69_source_fingerprint', $fingerprint);
	update_post_meta($post_id, '_p69_release_year', sanitize_text_field($record['year'] ?? ''));
	update_post_meta($post_id, '_p69_quality', sanitize_text_field($record['quality'] ?? ''));
	update_post_meta($post_id, '_p69_original_poster_url', esc_url_raw($record['poster'] ?? ''));
	update_post_meta($post_id, '_p69_last_synced', current_time('mysql', true));
	update_post_meta($post_id, '_p69_import_status', 'synced');

	$taxonomy = 'p69_industry';
	if (taxonomy_exists($taxonomy)) wp_set_object_terms($post_id, $category, $taxonomy, false);
	pirate69_replace_imported_links($post_id, $record['downloadLinks'] ?? array(), $source_id);

	$poster_settings = pirate69_source_config()['settings'];
	if (!empty($poster_settings['poster_import']) && !has_post_thumbnail($post_id) && !empty($record['poster'])) {
		pirate69_import_poster($post_id, $record['poster']);
	}
	return $post_id;
}

function pirate69_replace_imported_links($post_id, $links, $source_id) {
	global $wpdb;
	$table = $wpdb->prefix . 'p69_download_links';
	$wpdb->delete($table, array('post_id' => $post_id, 'origin' => 'imported'), array('%d','%s'));
	if (!is_array($links)) return;
	foreach ($links as $i => $link) {
		if (!is_array($link) || empty($link['url'])) continue;
		$url = esc_url_raw($link['url']);
		if (!$url || !wp_http_validate_url($url)) continue;
		$wpdb->insert($table, array(
			'post_id' => $post_id, 'source_key' => substr($source_id . ':' . $i, 0, 191),
			'label' => sanitize_text_field($link['label'] ?? ''),
			'url' => $url, 'quality' => sanitize_text_field($link['quality'] ?? ''),
			'file_size' => sanitize_text_field($link['size'] ?? ''),
			'audio' => sanitize_text_field($link['audio'] ?? ''),
			'server' => sanitize_text_field($link['server'] ?? ''),
			'sort_order' => absint($i), 'is_enabled' => 1, 'origin' => 'imported',
			'created_at' => current_time('mysql', true), 'updated_at' => current_time('mysql', true),
		));
	}
}

function pirate69_import_poster($post_id, $url) {
	if (!function_exists('media_handle_sideload')) require_once ABSPATH . 'wp-admin/includes/media.php';
	if (!function_exists('download_url')) require_once ABSPATH . 'wp-admin/includes/file.php';
	if (!function_exists('wp_generate_attachment_metadata')) require_once ABSPATH . 'wp-admin/includes/image.php';
	$url = esc_url_raw($url);
	if (!$url || !wp_http_validate_url($url)) return new WP_Error('p69_bad_poster_url', 'Poster URL is invalid.');
	$tmp = download_url($url, 20);
	if (is_wp_error($tmp)) return $tmp;
	$size = filesize($tmp);
	if (!$size || $size > 8 * MB_IN_BYTES) { @unlink($tmp); return new WP_Error('p69_poster_size', 'Poster is empty or larger than 8 MB.'); }
	$filetype = wp_check_filetype(basename((string) wp_parse_url($url, PHP_URL_PATH)));
	$allowed = array('jpg','jpeg','png','webp','gif');
	if (empty($filetype['ext']) || !in_array(strtolower($filetype['ext']), $allowed, true)) { @unlink($tmp); return new WP_Error('p69_poster_type', 'Poster file type is not supported.'); }
	$file = array('name' => sanitize_file_name(basename((string) wp_parse_url($url, PHP_URL_PATH))), 'tmp_name' => $tmp);
	$attachment_id = media_handle_sideload($file, $post_id);
	if (is_wp_error($attachment_id)) { @unlink($tmp); update_post_meta($post_id, '_p69_poster_status', 'failed'); return $attachment_id; }
	set_post_thumbnail($post_id, $attachment_id);
	update_post_meta($post_id, '_p69_poster_attachment_id', (int) $attachment_id);
	update_post_meta($post_id, '_p69_poster_status', 'downloaded');
	return $attachment_id;
}
add_action('pirate69_process_import_job', 'pirate69_process_import_job');
