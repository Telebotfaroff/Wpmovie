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

function pirate69_create_import_job($category, $job_type = 'sync', $target = '') {
	global $wpdb;
	$schema = pirate69_ensure_schema();
	if (is_wp_error($schema)) return $schema;
	$categories = pirate69_source_categories();
	if (!isset($categories[$category]) || !in_array($job_type,array('sync','preview','homepage','chunk','movie'),true)) {
		return new WP_Error('p69_bad_category', 'Unknown catalog category.');
	}
	if (in_array($job_type, array('chunk','movie'), true) && '' === (string) $target) {
		return new WP_Error('p69_missing_target', 'A chunk number or movie source ID is required.');
	}
	$table = $wpdb->prefix . 'p69_import_jobs';
	$active = 0;
	if (in_array($job_type,array('chunk','movie'),true)) {
		$active_rows = $wpdb->get_results($wpdb->prepare("SELECT id,payload FROM {$table} WHERE category=%s AND job_type=%s AND status IN ('pending','running','paused') ORDER BY id DESC", $category, $job_type), ARRAY_A);
		foreach ((array)$active_rows as $active_row) {
			$active_state = json_decode($active_row['payload'] ?? '', true);
			if (is_array($active_state) && (string)($active_state['target'] ?? '') === (string)$target) { $active = (int)$active_row['id']; break; }
		}
	} else {
		$active = $wpdb->get_var($wpdb->prepare(
			"SELECT id FROM {$table} WHERE category = %s AND job_type = %s AND status IN ('pending','running','paused') ORDER BY id DESC LIMIT 1",
			$category, $job_type
		));
	}
	if ($active) {
		return (int) $active;
	}
	$now = current_time('mysql', true);
	$inserted = $wpdb->insert($table, array(
		'job_type' => $job_type,
		'category' => $category,
		'status' => 'pending',
		'total_items' => 0,
		'processed_items' => 0,
		'error_count' => 0,
		'payload' => wp_json_encode(array(
			'phase' => ('homepage' === $job_type ? 'feed' : 'index'),
			'chunks' => array(),
			'chunk_pos' => 0,
			'item_pos' => 0,
			'retries' => 0,
			'preview' => ('preview' === $job_type),
			'target' => (string) $target,
		)),
		'created_at' => $now,
		'updated_at' => $now,
	));
	if (false === $inserted) {
		$error = $wpdb->last_error ? $wpdb->last_error : 'The database rejected the insert without returning an error.';
		pirate69_log(0, 'error', 'Import job insert failed.', array('db_error' => $error, 'table' => $table));
		return new WP_Error('p69_job_create_failed', 'Could not create import job: ' . $error);
	}
	$id = (int) $wpdb->insert_id;
	if (!$id) {
		return new WP_Error('p69_job_create_failed', 'The database insert completed but returned no job ID.');
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
	$lock_value = time() . ':' . wp_generate_password(20, false, false);
	if (!add_option($lock_key, $lock_value, '', false)) {
		$existing_lock = (string) get_option($lock_key, '');
		$lock_time = (int) strtok($existing_lock, ':');
		if ($lock_time > 0 && (time() - $lock_time) > 300) {
			// Compare-and-delete: never remove a newer worker's lock.
			$wpdb->query($wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
				$lock_key,
				maybe_serialize($existing_lock)
			));
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

		if (($state['phase'] ?? 'index') === 'feed') {
			$feed=pirate69_fetch_json($categories[$category]['feed']);
			if(is_wp_error($feed)){pirate69_retry_or_fail_job($job_id,$state,$feed->get_error_message());return;}
			$state['phase']='feed_records';$state['records']=array_values(array_filter($feed,'is_array'));$state['item_pos']=0;
			$wpdb->update($table,array('total_items'=>count($state['records']),'payload'=>wp_json_encode($state),'updated_at'=>current_time('mysql',true)),array('id'=>$job_id));
			pirate69_log($job_id,'info','Homepage feed loaded.',array('items'=>count($state['records'])));pirate69_schedule_job($job_id,5);return;
		}
		if (($state['phase'] ?? '') === 'feed_records') {
			$records=$state['records']??array();$offset=absint($state['item_pos']??0);$size=pirate69_effective_batch_size();$batch=array_slice($records,$offset,$size);
			$counts=pirate69_process_record_batch($batch,$category,$job_id,!empty($state['preview']));$offset+=absint($counts['processed']);$state['item_pos']=$offset;
			$wpdb->query($wpdb->prepare("UPDATE {$table} SET processed_items=processed_items+%d,created_count=created_count+%d,updated_count=updated_count+%d,skipped_count=skipped_count+%d,error_count=error_count+%d,payload=%s,updated_at=%s WHERE id=%d",absint($counts['processed']),$counts['created'],$counts['updated'],$counts['skipped'],$counts['errors'],wp_json_encode($state),current_time('mysql',true),$job_id));
			if($offset>=count($records)){$wpdb->update($table,array('status'=>'completed','updated_at'=>current_time('mysql',true)),array('id'=>$job_id));pirate69_log($job_id,'success','Homepage feed processing completed.',$counts);}else{pirate69_schedule_job($job_id,5);}return;
		}
		if (($state['phase'] ?? 'index') === 'index') {
			$index = pirate69_fetch_json($categories[$category]['index']);
			if (is_wp_error($index)) {
				pirate69_retry_or_fail_job($job_id, $state, $index->get_error_message());
				return;
			}
			$chunk_map = array(); $index_rows = array(); $valid_count = 0;
			foreach ($index as $row) {
				if (!is_array($row) || empty($row['id']) || empty($row['chunk'])) continue;
				$chunk_number = absint($row['chunk']); if (!$chunk_number) continue;
				$id = sanitize_text_field($row['id']); $chunk_map[$chunk_number][] = $id; $index_rows[$id] = $row; $valid_count++;
			}
			ksort($chunk_map, SORT_NUMERIC);
			$job_type = sanitize_key($job['job_type'] ?? 'sync');
			$target = (string) ($state['target'] ?? '');
			if ('chunk' === $job_type) {
				$wanted_chunk = absint($target);
				$chunk_map = isset($chunk_map[$wanted_chunk]) ? array($wanted_chunk => $chunk_map[$wanted_chunk]) : array();
			} elseif ('movie' === $job_type) {
				$wanted_row = $index_rows[$target] ?? null;
				$wanted_chunk = is_array($wanted_row) ? absint($wanted_row['chunk'] ?? 0) : 0;
				$chunk_map = ($wanted_chunk && isset($chunk_map[$wanted_chunk])) ? array($wanted_chunk => array($target)) : array();
				$index_rows = isset($index_rows[$target]) ? array($target => $index_rows[$target]) : array();
			}
			if (in_array($job_type, array('chunk','movie'), true) && !$chunk_map) {
				pirate69_fail_job($job_id, 'The selected chunk or movie ID was not found in the source index.');
				return;
			}
			$job_total = array_sum(array_map('count', $chunk_map));
			$state = array(
				'phase' => 'chunks',
				'chunks' => array_keys($chunk_map),
				'chunk_map' => $chunk_map,
				'index_rows' => $index_rows,
				'chunk_pos' => 0,
				'item_pos' => 0,
				'retries' => 0,
				'preview' => !empty($state['preview']),
				'target' => $target,
			);
			$wpdb->update($table, array(
				'total_items' => $job_total,
				'payload' => wp_json_encode($state),
				'updated_at' => current_time('mysql', true),
			), array('id' => $job_id));
			pirate69_log($job_id, 'info', 'Catalog index loaded.', array('items' => $job_total, 'catalog_items' => $valid_count, 'chunks' => count($chunk_map), 'job_type' => $job_type, 'target' => $target));
			pirate69_schedule_job($job_id, 5);
			return;
		}

		if (($state['phase'] ?? '') === 'chunk_records') {
			$records=$state['records']??array();$offset=absint($state['item_pos']??0);$size=pirate69_effective_batch_size();$batch=array_slice($records,$offset,$size);
			$counts=pirate69_process_record_batch($batch,$category,$job_id,!empty($state['preview']));$offset+=absint($counts['processed']);
			if($offset>=count($records)){$state['chunk_pos']=absint($state['chunk_pos']??0)+1;$state['item_pos']=0;$state['phase']='chunks';unset($state['records']);}else{$state['item_pos']=$offset;}
			$wpdb->query($wpdb->prepare("UPDATE {$table} SET processed_items=processed_items+%d,created_count=created_count+%d,updated_count=updated_count+%d,skipped_count=skipped_count+%d,error_count=error_count+%d,payload=%s,updated_at=%s WHERE id=%d",absint($counts['processed']),$counts['created'],$counts['updated'],$counts['skipped'],$counts['errors'],wp_json_encode($state),current_time('mysql',true),$job_id));
			pirate69_schedule_job($job_id,5);return;
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
					$record = pirate69_merge_index_metadata($record, $state['index_rows'][$id] ?? array());
					$records[] = $record;
				}
			}
			set_transient($cache_key, $records, 10 * MINUTE_IN_SECONDS);
		}

		$state['records']=$records;$state['phase']='chunk_records';$state['item_pos']=0;
		$wpdb->update($table,array('payload'=>wp_json_encode($state),'updated_at'=>current_time('mysql',true)),array('id'=>$job_id));
		pirate69_schedule_job($job_id,5);return;

		$state['retries'] = 0;
		$wpdb->query($wpdb->prepare(
			"UPDATE {$table} SET processed_items = processed_items + %d, error_count = error_count + %d, payload = %s, updated_at = %s WHERE id = %d",
			$processed, $errors, wp_json_encode($state), current_time('mysql', true), $job_id
		));
		pirate69_schedule_job($job_id, 5);
	} finally {
		// Release only the lock owned by this worker.
		$wpdb->query($wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
			$lock_key,
			maybe_serialize($lock_value)
		));
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


/** Resolve source IDs through an indexed lookup, with a one-time legacy fallback. */
function pirate69_find_source_post($category, $source_id) {
	global $wpdb;
	$table = $wpdb->prefix . 'p69_source_map';
	$post_id = (int) $wpdb->get_var($wpdb->prepare(
		"SELECT post_id FROM {$table} WHERE category = %s AND source_hash = %s LIMIT 1",
		$category, hash('sha256', $source_id)
	));
	if ($post_id && get_post($post_id)) return $post_id;
	$legacy = get_posts(array(
		'post_type' => array('p69_movie','p69_tv_show'), 'post_status' => 'any',
		'meta_key' => '_p69_source_id', 'meta_value' => $source_id,
		'numberposts' => 1, 'fields' => 'ids', 'no_found_rows' => true,
	));
	if (!$legacy) return 0;
	$post_id = (int) $legacy[0];
	$wpdb->replace($table, array(
		'category' => $category, 'source_id' => $source_id, 'source_hash' => hash('sha256', $source_id), 'post_id' => $post_id,
		'fingerprint' => (string) get_post_meta($post_id, '_p69_source_fingerprint', true),
		'updated_at' => current_time('mysql', true),
	), array('%s','%s','%s','%d','%s','%s'));
	return $post_id;
}

function pirate69_import_record($record, $category, $dry_run = false) {
	global $wpdb;
	$source_id = sanitize_text_field($record['id'] ?? '');
	if (!$source_id) {
		return new WP_Error('p69_missing_id', 'Record has no source ID.');
	}
	$post_type = !empty($record['isSeries']) ? 'p69_tv_show' : 'p69_movie';
	if ($dry_run) { $found = pirate69_find_source_post($category, $source_id); if (!$found) return array('status' => 'created'); $old = get_post_meta($found, '_p69_source_fingerprint', true); return array('status' => hash_equals((string) $old, hash('sha256', wp_json_encode($record))) ? 'skipped' : 'updated'); }
	$title = sanitize_text_field($record['cleanTitle'] ?? $record['fullTitle'] ?? $record['title'] ?? $source_id);
	$fingerprint = hash('sha256', wp_json_encode($record));
	$post_id = pirate69_find_source_post($category, $source_id);
	$created_now = !$post_id;
	$old_fingerprint = $post_id ? get_post_meta($post_id, '_p69_source_fingerprint', true) : '';
	if ($post_id && hash_equals((string) $old_fingerprint, $fingerprint)) {
		$settings = pirate69_source_config()['settings'];
		if (!empty($settings['poster_import']) && !has_post_thumbnail($post_id) && !empty($record['poster'])) {
			pirate69_queue_poster($post_id, $record['poster']);
		}
		if (function_exists('pirate69_schedule_tmdb_sync')) pirate69_schedule_tmdb_sync($post_id);
		return array('status' => 'skipped', 'post_id' => $post_id);
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
	$wpdb->replace($wpdb->prefix . 'p69_source_map', array('category'=>$category,'source_id'=>$source_id,'source_hash'=>hash('sha256',$source_id),'post_id'=>$post_id,'fingerprint'=>$fingerprint,'updated_at'=>current_time('mysql',true)), array('%s','%s','%s','%d','%s','%s'));
	update_post_meta($post_id, '_p69_release_year', sanitize_text_field($record['year'] ?? ''));
	update_post_meta($post_id, '_p69_quality', sanitize_text_field($record['quality'] ?? ''));
	update_post_meta($post_id, '_p69_season', absint($record['season'] ?? 0));
	update_post_meta($post_id, '_p69_is_series', !empty($record['isSeries']) ? 1 : 0);
	update_post_meta($post_id, '_p69_full_title', sanitize_text_field($record['fullTitle'] ?? $title));
	update_post_meta($post_id, '_p69_original_poster_url', esc_url_raw($record['poster'] ?? ''));
	update_post_meta($post_id, '_p69_last_synced', current_time('mysql', true));
	update_post_meta($post_id, '_p69_import_status', 'synced');

	if (taxonomy_exists('p69_industry')) {
		wp_set_object_terms($post_id, $category, 'p69_industry', false);
	}
	pirate69_replace_imported_links($post_id, $record['downloadLinks'] ?? array(), $source_id);

	$settings = pirate69_source_config()['settings'];
	if (!empty($settings['poster_import']) && !has_post_thumbnail($post_id) && !empty($record['poster'])) {
		pirate69_queue_poster($post_id, $record['poster']);
	}
	// TMDB matching runs separately so slow API/image requests never block catalog batches.
	if (function_exists('pirate69_schedule_tmdb_sync')) pirate69_schedule_tmdb_sync($post_id);
	return array('status' => $created_now ? 'created' : 'updated', 'post_id' => $post_id);
}

function pirate69_replace_imported_links($post_id, $links, $source_id) {
	global $wpdb;
	$table = $wpdb->prefix . 'p69_download_links';
	if (!is_array($links)) $links = array();
	$rows = array();
	foreach ($links as $i => $link) {
		if (!is_array($link) || empty($link['url'])) continue;
		$url = esc_url_raw($link['url']);
		if (!$url || !wp_http_validate_url($url)) continue;
		$rows[] = array(
			$post_id, substr($source_id . ':' . $i, 0, 191),
			sanitize_text_field($link['label'] ?? ''), $url,
			sanitize_text_field($link['quality'] ?? ''), sanitize_text_field($link['size'] ?? ''),
			sanitize_text_field($link['audio'] ?? ''), sanitize_text_field($link['server'] ?? ''),
			absint($link['season'] ?? 0),
			sanitize_text_field(is_scalar($link['episode'] ?? '') ? (string)($link['episode'] ?? '') : ''),
			!empty($link['isBatch']) ? 1 : 0, absint($i), 1, 'imported',
			current_time('mysql', true), current_time('mysql', true)
		);
	}
	$wpdb->delete($table, array('post_id'=>$post_id,'origin'=>'imported'), array('%d','%s'));
	if (!$rows) return;
	$placeholders = array();
	$values = array();
	foreach ($rows as $row) {
		$placeholders[] = '(%d,%s,%s,%s,%s,%s,%s,%s,%d,%s,%d,%d,%d,%s,%s,%s)';
		$values = array_merge($values, $row);
	}
	$sql = "INSERT INTO {$table} (post_id,source_key,label,url,quality,file_size,audio,server,season,episode,is_batch,sort_order,is_enabled,origin,created_at,updated_at) VALUES " . implode(',', $placeholders);
	$wpdb->query($wpdb->prepare($sql, $values));
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
	$existing = get_posts(array('post_type'=>'attachment','post_status'=>'inherit','meta_key'=>'_p69_source_poster_url','meta_value'=>$url,'numberposts'=>1,'fields'=>'ids','no_found_rows'=>true));
	if ($existing) { set_post_thumbnail($post_id,(int)$existing[0]); update_post_meta($post_id,'_p69_poster_attachment_id',(int)$existing[0]); update_post_meta($post_id,'_p69_poster_status','downloaded'); return (int)$existing[0]; }
	$tmp = download_url($url, 20);
	if (is_wp_error($tmp)) {
		return $tmp;
	}
	$size = filesize($tmp);
	if (!$size || $size > max(1,min(20,absint(pirate69_source_config()['settings']['poster_max_mb'] ?? 8))) * MB_IN_BYTES) {
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
	update_post_meta($attachment_id, '_p69_source_poster_url', $url);
	set_post_thumbnail($post_id, $attachment_id);
	update_post_meta($post_id, '_p69_poster_attachment_id', (int) $attachment_id);
	update_post_meta($post_id, '_p69_poster_status', 'downloaded');
	return $attachment_id;
}



function pirate69_effective_batch_size() {
 $settings = pirate69_source_config()['settings'];
 $mode = sanitize_key($settings['import_mode'] ?? 'balanced');
 $presets = array('safe'=>1,'balanced'=>3,'fast'=>5);
 if (isset($presets[$mode])) return $presets[$mode];
 return max(1, min(10, absint($settings['batch_size'] ?? 5)));
}

function pirate69_process_record_batch($batch,$category,$job_id,$preview=false){
 $counts=array('created'=>0,'updated'=>0,'skipped'=>0,'errors'=>0,'processed'=>0);
 $started=microtime(true);$time_budget=8;
 foreach($batch as $record){
  if($counts['processed']>0 && (microtime(true)-$started)>=$time_budget)break;
  $result=pirate69_import_record($record,$category,$preview);$attempt=1;
  while(is_wp_error($result)&&$attempt<3){$attempt++;$result=pirate69_import_record($record,$category,$preview);}
  $counts['processed']++;
  if(is_wp_error($result)){$counts['errors']++;pirate69_log($job_id,'error',$result->get_error_message(),array('source_id'=>$record['id']??'','attempts'=>$attempt));}
  elseif(is_array($result)&&isset($result['status']))$counts[$result['status']]++;
  else $counts['updated']++;
 }
 return $counts;
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
// Daily sync is scheduled by the plugin activation hook.
