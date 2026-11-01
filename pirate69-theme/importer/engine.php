<?php
defined('ABSPATH') || exit;

/**
 * Persist importer activity without exposing source payloads on frontend requests.
 */
function pirate69_log($job_id, $level, $message, $context = array()) {
	global $wpdb;
	$wpdb->insert($wpdb->prefix . 'p69_import_logs', array(
		'job_id' => absint($job_id),
		'level' => sanitize_key($level),
		'message' => sanitize_text_field($message),
		'context' => wp_json_encode($context),
		'created_at' => current_time('mysql', true),
	), array('%d', '%s', '%s', '%s', '%s'));
}

function pirate69_create_import_job($category) {
	global $wpdb;
	if (!isset(pirate69_source_categories()[$category])) {
		return new WP_Error('p69_bad_category', 'Unknown catalog category.');
	}
	$table = $wpdb->prefix . 'p69_import_jobs';
	$active = $wpdb->get_var($wpdb->prepare(
		"SELECT id FROM {$table} WHERE category = %s AND status IN ('pending','running','paused') ORDER BY id DESC LIMIT 1",
		$category
	));
	if ($active) {
		return (int) $active;
	}
	$now = current_time('mysql', true);
	$wpdb->insert($table, array(
		'job_type' => 'sync',
		'category' => $category,
		'status' => 'pending',
		'cursor' => '',
		'total_items' => 0,
		'processed_items' => 0,
		'error_count' => 0,
		'payload' => wp_json_encode(array(
			'phase' => 'index',
			'chunks' => array(),
			'chunk_pos' => 0,
			'item_pos' => 0,
			'retries' => 0,
		)),
		'created_at' => $now,
		'updated_at' => $now,
	));
	$id = (int) $wpdb->insert_id;
	if (!$id) {
		return new WP_Error('p69_job_create_failed', 'Could not create import job.');
	}
	pirate69_log($id, 'info', 'Import job queued.', array('category' => $category));
	pirate69_schedule_job($id, 5);
	return $id;
}

function pirate69_schedule_job($job_id, $delay = 10) {
	$args = array((int) $job_id);
	if (!wp_next_scheduled('pirate69_process_import_job', $args)) {
		wp_schedule_single_event(time() + max(5, absint($delay)), 'pirate69_process_import_job', $args);
	}
}

/**
 * One cron invocation handles one bounded batch. A transient lock prevents overlapping
 * cron requests from processing the same persisted cursor twice.
 */
function pirate69_process_import_job($job_id) {
	global $wpdb;
	$job_id = absint($job_id);
	$lock_key = 'p69_job_lock_' . $job_id;
	if (!$job_id) {
		return;
	}
	// add_option() is an atomic insert on the options table, unlike the
	// default non-persistent object cache which is isolated per PHP request.
	$lock_value = (string) time();
	if (!add_option($lock_key, $lock_value, '', false)) {
		$existing_lock = (int) get_option($lock_key);
		if ($existing_lock && (time() - $existing_lock) > 180) {
			delete_option($lock_key);
		}
		if (!add_option($lock_key, $lock_value, '', false)) {
			return;
		}
	}
	$table = $wpdb->prefix . 'p69_import_jobs';
	try {
		$job = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $job_id), ARRAY_A);
		if (!$job || !in_array($job['status'], array('pending', 'running'), true)) {
			return;
		}
		$wpdb->update($table, array('status' => 'running', 'updated_at' => current_time('mysql', true)), array('id' => $job_id));
		$state = json_decode($job['payload'], true);
		if (!is_array($state)) {
			$state = array('phase' => 'index', 'chunks' => array(), 'chunk_pos' => 0, 'item_pos' => 0, 'retries' => 0);
		}
		$categories = pirate69_source_categories();
		$category = $job['category'];
		if (!isset($categories[$category])) {
			pirate69_fail_job($job_id, 'Invalid catalog category.');
			return;
		}

		if (($state['phase'] ?? 'index') === 'index') {
			$index = pirate69_fetch_json($categories[$category]['index']);
			if (is_wp_error($index)) {
				pirate69_retry_or_fail_job($job_id, $state, $index->get_error_message());
				return;
			}
			$chunk_map = array();
			$valid_count = 0;
			foreach ($index as $row) {
				if (!is_array($row) || empty($row['id']) || empty($row['chunk'])) {
					continue;
				}
				$chunk_number = absint($row['chunk']);
				if (!$chunk_number) {
					continue;
				}
				$chunk_map[$chunk_number][] = sanitize_text_field($row['id']);
				$valid_count++;
			}
			ksort($chunk_map, SORT_NUMERIC);
			$state = array(
				'phase' => 'chunks',
				'chunks' => array_keys($chunk_map),
				'chunk_map' => $chunk_map,
				'chunk_pos' => 0,
				'item_pos' => 0,
				'retries' => 0,
			);
			$wpdb->update($table, array(
				'total_items' => $valid_count,
				'payload' => wp_json_encode($state),
				'updated_at' => current_time('mysql', true),
			), array('id' => $job_id));
			pirate69_log($job_id, 'info', 'Catalog index loaded.', array('items' => $valid_count, 'chunks' => count($chunk_map)));
			pirate69_schedule_job($job_id, 5);
			return;
		}

		$chunks = $state['chunks'] ?? array();
		$chunk_pos = absint($state['chunk_pos'] ?? 0);
		if (!isset($chunks[$chunk_pos])) {
			$wpdb->update($table, array('status' => 'completed', 'updated_at' => current_time('mysql', true)), array('id' => $job_id));
			pirate69_log($job_id, 'success', 'Import completed.', array(
				'processed' => (int) $job['processed_items'],
				'errors' => (int) $job['error_count'],
			));
			return;
		}

		$chunk_number = absint($chunks[$chunk_pos]);
		$chunk_path = $category . '/chunk-' . $chunk_number . '.json';
		$cache_key = 'p69_chunk_' . md5(pirate69_source_config()['base'] . $chunk_path);
		$records = get_transient($cache_key);
		if (!is_array($records)) {
			$chunk = pirate69_fetch_json($chunk_path);
			if (is_wp_error($chunk)) {
				pirate69_retry_or_fail_job($job_id, $state, $chunk->get_error_message());
				return;
			}
			$records = array();
			$allowed_ids = array_fill_keys($state['chunk_map'][$chunk_number] ?? array(), true);
			foreach ($chunk as $key => $record) {
				if (!is_array($record)) {
					continue;
				}
				$id = sanitize_text_field($record['id'] ?? (is_string($key) ? $key : ''));
				if ($id && isset($allowed_ids[$id])) {
					$record['id'] = $id;
					$records[] = $record;
				}
			}
			set_transient($cache_key, $records, 10 * MINUTE_IN_SECONDS);
		}

		$offset = absint($state['item_pos'] ?? 0);
		$settings = pirate69_source_config()['settings'];
		$batch_size = max(1, min(30, absint($settings['batch_size'] ?? 10)));
		$batch = array_slice($records, $offset, $batch_size);
		$processed = 0;
		$errors = 0;
		foreach ($batch as $record) {
			$result = pirate69_import_record($record, $category);
			if (is_wp_error($result)) {
				$errors++;
				pirate69_log($job_id, 'error', $result->get_error_message(), array('source_id' => $record['id'] ?? ''));
			}
			$processed++;
		}

		$offset += count($batch);
		if ($offset >= count($records)) {
			$state['chunk_pos'] = $chunk_pos + 1;
			$state['item_pos'] = 0;
		} else {
			$state['item_pos'] = $offset;
		}
		$state['retries'] = 0;
		$wpdb->query($wpdb->prepare(
			"UPDATE {$table} SET processed_items = processed_items + %d, error_count = error_count + %d, payload = %s, updated_at = %s WHERE id = %d",
			$processed, $errors, wp_json_encode($state), current_time('mysql', true), $job_id
		));
		pirate69_schedule_job($job_id, 5);
	} finally {
		delete_option($lock_key);
	}
}

function pirate69_retry_or_fail_job($job_id, $state, $message) {
	$retries = absint($state['retries'] ?? 0) + 1;
	if ($retries <= 3) {
		$state['retries'] = $retries;
		global $wpdb;
		$wpdb->update($wpdb->prefix . 'p69_import_jobs', array(
			'status' => 'pending',
			'payload' => wp_json_encode($state),
			'updated_at' => current_time('mysql', true),
		), array('id' => $job_id));
		pirate69_log($job_id, 'warning', 'Temporary source error; retry scheduled.', array('attempt' => $retries, 'error' => $message));
		pirate69_schedule_job($job_id, 15 * $retries);
		return;
	}
	pirate69_fail_job($job_id, $message);
}

function pirate69_fail_job($job_id, $message) {
	global $wpdb;
	$wpdb->update($wpdb->prefix . 'p69_import_jobs', array('status' => 'failed', 'updated_at' => current_time('mysql', true)), array('id' => $job_id));
	pirate69_log($job_id, 'error', $message);
}

function pirate69_import_record($record, $category) {
	global $wpdb;
	$source_id = sanitize_text_field($record['id'] ?? '');
	if (!$source_id) {
		return new WP_Error('p69_missing_id', 'Record has no source ID.');
	}
	$post_type = !empty($record['isSeries']) ? 'p69_tv_show' : 'p69_movie';
	$title = sanitize_text_field($record['cleanTitle'] ?? $record['fullTitle'] ?? $record['title'] ?? $source_id);
	$fingerprint = hash('sha256', wp_json_encode($record));
	$existing = get_posts(array(
		'post_type' => array('p69_movie', 'p69_tv_show'),
		'post_status' => array('publish', 'draft', 'private'),
		'meta_key' => '_p69_source_id',
		'meta_value' => $source_id,
		'numberposts' => 1,
		'fields' => 'ids',
		'no_found_rows' => true,
	));
	$post_id = $existing ? (int) $existing[0] : 0;
	$old_fingerprint = $post_id ? get_post_meta($post_id, '_p69_source_fingerprint', true) : '';
	if ($post_id && hash_equals((string) $old_fingerprint, $fingerprint)) {
		$settings = pirate69_source_config()['settings'];
		if (!empty($settings['poster_import']) && !has_post_thumbnail($post_id) && !empty($record['poster'])) {
			$poster_result = pirate69_import_poster($post_id, $record['poster']);
			if (is_wp_error($poster_result)) {
				update_post_meta($post_id, '_p69_poster_status', 'failed');
				pirate69_log(0, 'warning', 'Poster retry failed.', array('post_id' => $post_id, 'error' => $poster_result->get_error_message()));
			}
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
	if (is_wp_error($updated)) {
		return $updated;
	}
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

	if (taxonomy_exists('p69_industry')) {
		wp_set_object_terms($post_id, $category, 'p69_industry', false);
	}
	pirate69_replace_imported_links($post_id, $record['downloadLinks'] ?? array(), $source_id);

	$settings = pirate69_source_config()['settings'];
	if (!empty($settings['poster_import']) && !has_post_thumbnail($post_id) && !empty($record['poster'])) {
		$poster_result = pirate69_import_poster($post_id, $record['poster']);
		if (is_wp_error($poster_result)) {
			update_post_meta($post_id, '_p69_poster_status', 'failed');
			pirate69_log(0, 'warning', 'Poster download failed.', array('post_id' => $post_id, 'error' => $poster_result->get_error_message()));
		}
	}
	return $post_id;
}

function pirate69_replace_imported_links($post_id, $links, $source_id) {
	global $wpdb;
	$table = $wpdb->prefix . 'p69_download_links';
	$wpdb->delete($table, array('post_id' => $post_id, 'origin' => 'imported'), array('%d', '%s'));
	if (!is_array($links)) {
		return;
	}
	foreach ($links as $i => $link) {
		if (!is_array($link) || empty($link['url'])) {
			continue;
		}
		$url = esc_url_raw($link['url']);
		if (!$url || !wp_http_validate_url($url)) {
			continue;
		}
		$wpdb->insert($table, array(
			'post_id' => $post_id,
			'source_key' => substr($source_id . ':' . $i, 0, 191),
			'label' => sanitize_text_field($link['label'] ?? ''),
			'url' => $url,
			'quality' => sanitize_text_field($link['quality'] ?? ''),
			'file_size' => sanitize_text_field($link['size'] ?? ''),
			'audio' => sanitize_text_field($link['audio'] ?? ''),
			'server' => sanitize_text_field($link['server'] ?? ''),
			'sort_order' => absint($i),
			'is_enabled' => 1,
			'origin' => 'imported',
			'created_at' => current_time('mysql', true),
			'updated_at' => current_time('mysql', true),
		));
	}
}

function pirate69_import_poster($post_id, $url) {
	if (!function_exists('media_handle_sideload')) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
	}
	if (!function_exists('download_url')) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	if (!function_exists('wp_generate_attachment_metadata')) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
	$url = esc_url_raw($url);
	if (!$url || !wp_http_validate_url($url)) {
		return new WP_Error('p69_bad_poster_url', 'Poster URL is invalid.');
	}
	$tmp = download_url($url, 20);
	if (is_wp_error($tmp)) {
		return $tmp;
	}
	$size = filesize($tmp);
	if (!$size || $size > 8 * MB_IN_BYTES) {
		@unlink($tmp);
		return new WP_Error('p69_poster_size', 'Poster is empty or larger than 8 MB.');
	}
	$check = wp_check_filetype_and_ext($tmp, basename((string) wp_parse_url($url, PHP_URL_PATH)));
	$allowed = array('image/jpeg', 'image/png', 'image/webp', 'image/gif');
	if (empty($check['type']) || !in_array($check['type'], $allowed, true)) {
		@unlink($tmp);
		return new WP_Error('p69_poster_type', 'Poster is not a supported image.');
	}
	$name = sanitize_file_name(basename((string) wp_parse_url($url, PHP_URL_PATH)));
	if (!$name) {
		$name = 'pirate69-poster-' . absint($post_id) . '.' . ( $check['ext'] ?: 'jpg' );
	}
	$file = array('name' => $name, 'tmp_name' => $tmp);
	$attachment_id = media_handle_sideload($file, $post_id);
	if (is_wp_error($attachment_id)) {
		@unlink($tmp);
		return $attachment_id;
	}
	set_post_thumbnail($post_id, $attachment_id);
	update_post_meta($post_id, '_p69_poster_attachment_id', (int) $attachment_id);
	update_post_meta($post_id, '_p69_poster_status', 'downloaded');
	return $attachment_id;
}

add_action('pirate69_process_import_job', 'pirate69_process_import_job');

function pirate69_daily_sync() {
	foreach (array_keys(pirate69_source_categories()) as $category) {
		pirate69_create_import_job($category);
	}
}
add_action('pirate69_daily_sync', 'pirate69_daily_sync');

function pirate69_schedule_daily_sync() {
	if (!wp_next_scheduled('pirate69_daily_sync')) {
		wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'pirate69_daily_sync');
	}
}
add_action('after_switch_theme', 'pirate69_schedule_daily_sync');
